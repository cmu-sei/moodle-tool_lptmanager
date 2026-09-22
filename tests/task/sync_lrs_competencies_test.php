<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/*
Learning Plan Template Manager for Moodle

Copyright 2026 Carnegie Mellon University.

NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS.
CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO,
WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL.
CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Licensed under a GNU General Public License - Version 3, 29 June 2007-style license, please see license.txt or contact permission@sei.cmu.edu for full
terms.

[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution. Please see Copyright notice for non-US Government use and distribution.

This Software includes and/or makes use of Third-Party Software each subject to its own license.

DM24-1177
*/

/**
 * LRS competency sync task tests.
 *
 * @package    tool_lptmanager
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lptmanager\task;

use core\http_client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;

/**
 * Tests for the LRS competency sync task.
 */
final class sync_lrs_competencies_test extends \advanced_testcase {
    /**
     * Build a task whose HTTP client answers with a canned response.
     *
     * @param int $status Status code to answer with.
     * @param string $body Body to answer with.
     * @param array $captured Receives the outgoing request and its resolved options.
     * @return sync_lrs_competencies
     */
    private function create_task_answering(int $status, string $body, array &$captured): sync_lrs_competencies {
        $response = new Response($status, [], $body);
        $handler = static function (RequestInterface $request, array $options) use ($response, &$captured): PromiseInterface {
            $captured = ['request' => $request, 'options' => $options];
            return Create::promiseFor($response);
        };

        $task = $this->getMockBuilder(sync_lrs_competencies::class)
            ->onlyMethods(['create_client'])
            ->getMock();
        $task->method('create_client')->willReturn(new http_client(['mock' => $handler]));

        return $task;
    }

    /**
     * Call the protected fetch_statements() on a task.
     *
     * @param sync_lrs_competencies $task Task to call.
     * @return object|null Decoded response.
     */
    private function fetch_statements(sync_lrs_competencies $task): ?object {
        $fetchstatements = \Closure::bind(
            static function (sync_lrs_competencies $task): ?object {
                return $task->fetch_statements('https://lrs.example.test/xapi/statements', 'key', 'secret');
            },
            null,
            sync_lrs_competencies::class
        );
        return $fetchstatements($task);
    }

    public function test_fetch_statements_configures_bounded_timeouts(): void {
        $this->resetAfterTest(true);
        set_config('lrs_request_timeout', 20, 'tool_lptmanager');
        $captured = [];
        $task = $this->create_task_answering(200, '{"statements": []}', $captured);

        $response = $this->fetch_statements($task);

        $this->assertEquals([], $response->statements);
        $this->assertSame(5, $captured['options'][RequestOptions::CONNECT_TIMEOUT]);
        $this->assertSame(20, $captured['options'][RequestOptions::TIMEOUT]);
    }

    public function test_fetch_statements_verifies_the_peer_certificate(): void {
        $this->resetAfterTest(true);
        $captured = [];
        $task = $this->create_task_answering(200, '{"statements": []}', $captured);

        $this->fetch_statements($task);

        // The request carries the LRS credentials in an Authorization header, so an unverified peer
        // would expose them to anyone on the path. Verification is core's default; this guards
        // against the plugin ever passing an option that turns it off.
        $this->assertTrue($captured['options'][RequestOptions::VERIFY]);
        $this->assertSame(
            'Basic ' . base64_encode('key:secret'),
            $captured['request']->getHeaderLine('Authorization')
        );
    }

    public function test_fetch_statements_reports_an_error_status_without_throwing(): void {
        $this->resetAfterTest(true);
        $captured = [];
        $task = $this->create_task_answering(500, 'upstream exploded', $captured);

        // A cron task must log and move on, not let a bad gateway abort the run.
        $this->assertNull($this->fetch_statements($task));
    }

    public function test_fetch_statements_rejects_non_object_json(): void {
        $this->resetAfterTest(true);
        $captured = [];
        $task = $this->create_task_answering(200, '[]', $captured);

        $this->assertNull($this->fetch_statements($task));
    }

    public function test_failed_sync_attempts_both_verbs_and_retains_last_sync_time(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $lastsync = '2026-08-26T18:45:00+00:00';
        set_config('enable_lrs_sync', 1, 'tool_lptmanager');
        set_config('lrs_endpoint', 'https://lrs.example.test/xapi', 'tool_lptmanager');
        set_config('lrs_api_key', 'key', 'tool_lptmanager');
        set_config('lrs_api_secret', 'secret', 'tool_lptmanager');
        set_config('lrs_last_sync', $lastsync, 'tool_lptmanager');

        $task = $this->getMockBuilder(sync_lrs_competencies::class)
            ->onlyMethods(['sync_verb'])
            ->getMock();
        $task->expects($this->exactly(2))
            ->method('sync_verb')
            ->willReturnOnConsecutiveCalls(
                ['count' => 0, 'checkpoint' => null, 'complete' => false],
                ['count' => 0, 'checkpoint' => null, 'complete' => true]
            );

        $task->execute();

        $this->assertSame($lastsync, get_config('tool_lptmanager', 'lrs_last_sync'));
    }

    public function test_partial_sync_saves_the_shared_safe_checkpoint(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $syncstart = '2026-09-03T12:00:00+00:00';
        $checkpoint = '2026-09-03T11:30:00+00:00';
        set_config('enable_lrs_sync', 1, 'tool_lptmanager');
        set_config('lrs_endpoint', 'https://lrs.example.test/xapi', 'tool_lptmanager');
        set_config('lrs_api_key', 'key', 'tool_lptmanager');
        set_config('lrs_api_secret', 'secret', 'tool_lptmanager');

        $task = $this->getMockBuilder(sync_lrs_competencies::class)
            ->onlyMethods(['get_sync_start_time', 'sync_verb'])
            ->getMock();
        $task->method('get_sync_start_time')->willReturn($syncstart);
        $task->method('sync_verb')->willReturnOnConsecutiveCalls(
            ['count' => 5, 'checkpoint' => $checkpoint, 'complete' => false],
            ['count' => 8, 'checkpoint' => null, 'complete' => true]
        );

        $task->execute();

        $this->assertSame($checkpoint, get_config('tool_lptmanager', 'lrs_last_sync'));
    }

    public function test_complete_sync_saves_the_timestamp_captured_before_paging(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $events = [];
        $syncstart = '2026-09-02T12:00:00+00:00';
        set_config('enable_lrs_sync', 1, 'tool_lptmanager');
        set_config('lrs_endpoint', 'https://lrs.example.test/xapi', 'tool_lptmanager');
        set_config('lrs_api_key', 'key', 'tool_lptmanager');
        set_config('lrs_api_secret', 'secret', 'tool_lptmanager');

        $task = $this->getMockBuilder(sync_lrs_competencies::class)
            ->onlyMethods(['get_sync_start_time', 'sync_verb'])
            ->getMock();
        $task->method('get_sync_start_time')
            ->willReturnCallback(static function () use (&$events, $syncstart): string {
                $events[] = 'timestamp';
                return $syncstart;
            });
        $task->method('sync_verb')
            ->willReturnCallback(static function () use (&$events): array {
                $events[] = 'sync';
                return ['count' => 0, 'checkpoint' => null, 'complete' => true];
            });

        $task->execute();

        $this->assertSame(['timestamp', 'sync', 'sync'], $events);
        $this->assertSame($syncstart, get_config('tool_lptmanager', 'lrs_last_sync'));
    }

    public function test_sync_verb_records_progress_when_page_limit_is_reached(): void {
        $this->resetAfterTest(true);
        set_config('lrs_max_pages_per_verb', 1, 'tool_lptmanager');
        $response = (object) [
            'statements' => [(object) [
                'id' => 'example-statement',
                'stored' => '2026-09-03T12:00:00.500Z',
            ]],
            'more' => '/xapi/statements?cursor=next',
        ];

        $task = $this->getMockBuilder(sync_lrs_competencies::class)
            ->onlyMethods(['fetch_statements', 'process_statement'])
            ->getMock();
        $task->expects($this->once())
            ->method('fetch_statements')
            ->willReturn($response);
        $task->method('process_statement')->willReturn(true);

        $syncverb = \Closure::bind(
            static function (sync_lrs_competencies $task): array {
                return $task->sync_verb(
                    'https://lrs.example.test/xapi',
                    'key',
                    'secret',
                    sync_lrs_competencies::VERB_ASSERTED,
                    false
                );
            },
            null,
            sync_lrs_competencies::class
        );

        $result = $syncverb($task);
        $this->assertSame(1, $result['count']);
        $this->assertSame('2026-09-03T12:00:00+00:00', $result['checkpoint']);
        $this->assertFalse($result['complete']);
    }

    public function test_sync_verb_stops_at_a_pagination_url_on_another_host(): void {
        $this->resetAfterTest(true);
        $requested = [];
        $task = $this->getMockBuilder(sync_lrs_competencies::class)
            ->onlyMethods(['fetch_statements'])
            ->getMock();
        $task->method('fetch_statements')
            ->willReturnCallback(static function (string $url) use (&$requested): object {
                $requested[] = $url;
                return (object) [
                    'statements' => [],
                    'more' => 'https://attacker.example.test/xapi/statements?cursor=next',
                ];
            });

        $result = $this->run_sync_verb($task);

        // Following this would send the Authorization header, and so the LRS key and secret, to a
        // host the LRS response picked.
        $this->assertCount(1, $requested);
        $this->assertFalse($result['complete']);
    }

    public function test_sync_verb_stops_at_a_pagination_url_with_a_foreign_scheme(): void {
        $this->resetAfterTest(true);
        $requested = [];
        $task = $this->getMockBuilder(sync_lrs_competencies::class)
            ->onlyMethods(['fetch_statements'])
            ->getMock();
        $task->method('fetch_statements')
            ->willReturnCallback(static function (string $url) use (&$requested): object {
                $requested[] = $url;
                return (object) [
                    'statements' => [],
                    'more' => 'file:///etc/passwd',
                ];
            });

        $result = $this->run_sync_verb($task);

        $this->assertCount(1, $requested);
        $this->assertFalse($result['complete']);
    }

    public function test_sync_verb_resolves_a_relative_pagination_url(): void {
        $this->resetAfterTest(true);
        $requested = [];
        $responses = [
            (object) ['statements' => [], 'more' => '/xapi/statements?cursor=next'],
            (object) ['statements' => []],
        ];
        $task = $this->getMockBuilder(sync_lrs_competencies::class)
            ->onlyMethods(['fetch_statements'])
            ->getMock();
        $task->method('fetch_statements')
            ->willReturnCallback(static function (string $url) use (&$requested, &$responses): object {
                $requested[] = $url;
                return array_shift($responses);
            });

        $result = $this->run_sync_verb($task);

        $this->assertTrue($result['complete']);
        $this->assertSame('https://lrs.example.test/xapi/statements?cursor=next', $requested[1]);
    }

    public function test_sync_verb_follows_a_pagination_url_on_the_configured_host(): void {
        $this->resetAfterTest(true);
        $requested = [];
        $more = 'https://lrs.example.test/xapi/statements?cursor=next';
        $responses = [
            (object) ['statements' => [], 'more' => $more],
            (object) ['statements' => []],
        ];
        $task = $this->getMockBuilder(sync_lrs_competencies::class)
            ->onlyMethods(['fetch_statements'])
            ->getMock();
        $task->method('fetch_statements')
            ->willReturnCallback(static function (string $url) use (&$requested, &$responses): object {
                $requested[] = $url;
                return array_shift($responses);
            });

        $result = $this->run_sync_verb($task);

        $this->assertTrue($result['complete']);
        $this->assertSame($more, $requested[1]);
    }

    public function test_competency_idnumber_comes_from_the_tla_extension(): void {
        $this->resetAfterTest(true);
        $object = (object) [
            'id' => 'https://elsewhere.example.test/activities/lab-4',
            'definition' => (object) [
                'extensions' => (object) [
                    'https://w3id.org/xapi/tla/extensions/competency-identifier' => 'T0023',
                ],
            ],
        ];

        $this->assertSame('T0023', $this->extract_competency_idnumber($object));
    }

    public function test_competency_idnumber_comes_from_a_prefixed_object_iri(): void {
        $this->resetAfterTest(true);
        set_config('competency_iri_prefix', 'https://niccs.example.test/ksat/', 'tool_lptmanager');
        $object = (object) ['id' => 'https://niccs.example.test/ksat/T0023'];

        $this->assertSame('T0023', $this->extract_competency_idnumber($object));
    }

    public function test_an_unprefixed_object_iri_resolves_no_competency(): void {
        $this->resetAfterTest(true);
        set_config('competency_iri_prefix', 'https://niccs.example.test/ksat/', 'tool_lptmanager');

        // Reading the last path segment of any IRI would let this unrelated activity grade T0023.
        $object = (object) ['id' => 'https://elsewhere.example.test/activities/T0023'];

        $this->assertNull($this->extract_competency_idnumber($object));
    }

    public function test_a_path_below_the_iri_prefix_resolves_no_competency(): void {
        $this->resetAfterTest(true);
        set_config('competency_iri_prefix', 'https://niccs.example.test/ksat/', 'tool_lptmanager');
        $object = (object) ['id' => 'https://niccs.example.test/ksat/T0023/evidence'];

        $this->assertNull($this->extract_competency_idnumber($object));
    }

    public function test_a_blank_framework_allowlist_allows_no_framework(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $framework = $this->getDataGenerator()->get_plugin_generator('core_competency')->create_framework([
            'idnumber' => 'https://niccs.example.test/framework/nice',
        ]);
        set_config('lrs_sync_frameworks', '', 'tool_lptmanager');

        $allowed = $this->get_allowed_framework_ids();

        $this->assertSame([], $allowed);
        $this->assertNotContains((int) $framework->get('id'), $allowed);
    }

    public function test_the_framework_allowlist_resolves_configured_iris(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $allowedframework = $generator->create_framework([
            'idnumber' => 'https://niccs.example.test/framework/nice',
        ]);
        $otherframework = $generator->create_framework([
            'idnumber' => 'https://niccs.example.test/framework/other',
        ]);
        set_config('lrs_sync_frameworks', "https://niccs.example.test/framework/nice\n", 'tool_lptmanager');

        $allowed = $this->get_allowed_framework_ids();

        $this->assertSame([(int) $allowedframework->get('id')], $allowed);
        $this->assertNotContains((int) $otherframework->get('id'), $allowed);
    }

    /**
     * Run the protected extract_competency_idnumber() method.
     *
     * @param object $object The xAPI statement object.
     * @return string|null The resolved competency idnumber.
     */
    private function extract_competency_idnumber(object $object): ?string {
        $extract = \Closure::bind(
            static function (sync_lrs_competencies $task, object $object): ?string {
                return $task->extract_competency_idnumber($object);
            },
            null,
            sync_lrs_competencies::class
        );
        return $extract(new sync_lrs_competencies(), $object);
    }

    /**
     * Run the protected get_allowed_framework_ids() method.
     *
     * @return int[] Framework IDs the sync is allowed to grade.
     */
    private function get_allowed_framework_ids(): array {
        $allowed = \Closure::bind(
            static function (sync_lrs_competencies $task): array {
                return $task->get_allowed_framework_ids();
            },
            null,
            sync_lrs_competencies::class
        );
        return $allowed(new sync_lrs_competencies());
    }

    /**
     * Run the protected sync_verb() method against the test endpoint.
     *
     * @param sync_lrs_competencies $task The task under test.
     * @return array{count: int, checkpoint: ?string, complete: bool} Sync result.
     */
    private function run_sync_verb(sync_lrs_competencies $task): array {
        $syncverb = \Closure::bind(
            static function (sync_lrs_competencies $task): array {
                return $task->sync_verb(
                    'https://lrs.example.test/xapi',
                    'key',
                    'secret',
                    sync_lrs_competencies::VERB_ASSERTED,
                    false
                );
            },
            null,
            sync_lrs_competencies::class
        );
        return $syncverb($task);
    }
}
