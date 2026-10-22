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
    /** @var string LRS authority homePage the test LRS stamps. */
    private const LRS = 'https://lrs.example.test';

    /** @var string Credential the test Moodle writes with. */
    private const MOODLE_CREDENTIAL = '019e73d0-8404-888f-aa9b-9e604f7e704e';

    /** @var string Realm the trusted Moodle credential identifies learners under. */
    private const ISSUER = 'https://keycloak.example.test/realms/crucible';

    /** @var string Realm only the Blueprint credential identifies learners under. */
    private const OTHER_ISSUER = 'https://keycloak.example.test/realms/other';

    /** @var string Allowed competency framework. */
    private const FRAMEWORK = 'https://niccs.example.test/framework/nice';

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
        $this->configure_trust();
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
        $this->configure_trust();

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
        $this->configure_trust();

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
        $this->configure_trust();
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
        $this->configure_trust();
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
        $this->configure_trust();
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
        $this->configure_trust();
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
        $this->configure_trust();
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

    public function test_sync_without_trusted_sources_fetches_nothing(): void {
        $this->resetAfterTest(true);
        $this->expectOutputRegex('/No trusted LRS sources are configured; nothing will be synced/');
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
        $task->expects($this->never())->method('sync_verb');

        $task->execute();

        // Keeping the checkpoint means statements written meanwhile are read once sources are trusted.
        $this->assertSame($lastsync, get_config('tool_lptmanager', 'lrs_last_sync'));
    }

    public function test_a_trusted_source_without_a_learner_homepage_is_ignored(): void {
        $this->resetAfterTest(true);
        $this->expectOutputRegex('/Ignoring trusted source .* expected an authority homePage, an authority name and a learner/');

        // A credential with no learner homePage would leave the source free to name any learner.
        set_config('lrs_trusted_asserters', self::LRS . ' ' . self::MOODLE_CREDENTIAL, 'tool_lptmanager');
        $task = $this->getMockBuilder(sync_lrs_competencies::class)
            ->onlyMethods(['create_client'])
            ->getMock();
        $task->expects($this->never())->method('create_client');

        $result = $this->run_sync_verb($task);

        $this->assertSame(['count' => 0, 'checkpoint' => null, 'complete' => true], $result);
    }

    public function test_sync_verb_skips_a_verb_with_no_trusted_source(): void {
        $this->resetAfterTest(true);
        $this->expectOutputRegex('/No trusted LRS sources are configured for .*asserted; skipping it/');
        set_config('lrs_trusted_validators', self::LRS . ' ' . self::MOODLE_CREDENTIAL . ' ' . self::ISSUER, 'tool_lptmanager');

        $task = $this->getMockBuilder(sync_lrs_competencies::class)
            ->onlyMethods(['create_client'])
            ->getMock();
        $task->expects($this->never())->method('create_client');

        $result = $this->run_sync_verb($task);

        $this->assertSame(['count' => 0, 'checkpoint' => null, 'complete' => true], $result);
    }

    public function test_an_assertion_from_a_trusted_source_is_applied(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->expectOutputRegex('/Graded competency T0023/');
        $this->configure_trust();
        $learner = $this->create_learner_with_plan();

        $this->assertTrue($this->process_statement($this->asserted_statement(), sync_lrs_competencies::VERB_ASSERTED));

        $this->assertTrue($DB->record_exists('tool_lptmanager_lrs_sync', [
            'statementid' => 'statement-1',
            'userid' => $learner->id,
        ]));
    }

    public function test_an_assertion_from_an_untrusted_source_is_skipped(): void {
        global $DB;
        $this->resetAfterTest(true);
        // The log names the skipped authority so an admin can copy it into the settings.
        $this->expectOutputRegex("/written by 'https:\\/\\/lrs\\.example\\.test cmi5-player-credential', not a trusted source for .*asserted/");
        $this->configure_trust();
        $this->create_learner_with_plan();
        $statement = $this->asserted_statement();

        // A cmi5 player, a browser client or any other holder of a write credential.
        $statement->authority->account->name = 'cmi5-player-credential';

        $this->assertFalse($this->process_statement($statement, sync_lrs_competencies::VERB_ASSERTED));
        $this->assertFalse($DB->record_exists('tool_lptmanager_lrs_sync', ['statementid' => 'statement-1']));
    }

    public function test_an_assertion_without_an_authority_is_skipped(): void {
        $this->resetAfterTest(true);
        $this->expectOutputRegex('/written by no authority, not a trusted source/');
        $this->configure_trust();
        $this->create_learner_with_plan();
        $statement = $this->asserted_statement();
        unset($statement->authority);

        $this->assertFalse($this->process_statement($statement, sync_lrs_competencies::VERB_ASSERTED));
    }

    public function test_a_group_authority_is_refused_even_with_a_listed_member(): void {
        $this->resetAfterTest(true);
        $this->expectOutputRegex('/written by a Group authority, not a trusted source/');
        $this->configure_trust();
        $this->create_learner_with_plan();
        $statement = $this->asserted_statement();

        // The shape SQL LRS stamps for an OIDC client acting for a user. Trusting any member would
        // let the user member vouch for the statement; nothing writes this way today.
        $statement->authority = (object) [
            'objectType' => 'Group',
            'member' => [
                (object) ['account' => (object) ['homePage' => self::LRS, 'name' => self::MOODLE_CREDENTIAL]],
                (object) ['account' => (object) ['homePage' => self::ISSUER, 'name' => 'someone']],
            ],
        ];

        $this->assertFalse($this->process_statement($statement, sync_lrs_competencies::VERB_ASSERTED));
    }

    public function test_a_source_may_only_name_learners_under_its_own_homepage(): void {
        $this->resetAfterTest(true);
        $this->expectOutputRegex("/Learner account homePage '.*realms\\/crucible' is not one this source may name learners under/");
        $this->configure_trust();
        $this->create_learner_with_plan();
        $statement = $this->asserted_statement();

        // Blueprint's credential is trusted, but only for learners in the other realm.
        $statement->authority->account->name = 'blueprint-credential';

        $this->assertFalse($this->process_statement($statement, sync_lrs_competencies::VERB_ASSERTED));
    }

    public function test_an_asserting_source_is_not_trusted_to_validate(): void {
        $this->resetAfterTest(true);
        $this->expectOutputRegex('/not a trusted source for .*validated/');
        $this->configure_trust();
        $this->create_learner_with_plan();
        $statement = $this->validated_statement();
        $statement->authority->account->name = 'blueprint-credential';

        $this->assertFalse($this->process_statement($statement, sync_lrs_competencies::VERB_VALIDATED));
    }

    public function test_a_validation_from_a_trusted_source_grades_the_named_learner(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->expectOutputRegex('/Graded competency T0023/');
        $this->configure_trust();
        $learner = $this->create_learner_with_plan();

        $this->assertTrue($this->process_statement($this->validated_statement(), sync_lrs_competencies::VERB_VALIDATED));

        $this->assertTrue($DB->record_exists('tool_lptmanager_lrs_sync', [
            'statementid' => 'statement-1',
            'userid' => $learner->id,
        ]));
    }

    public function test_a_validation_without_a_learner_does_not_grade_the_validator(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->expectOutputRegex('/has no learner extension, skipping/');
        $this->configure_trust();
        $this->create_learner_with_plan();
        $statement = $this->validated_statement();
        unset($statement->context->extensions->{sync_lrs_competencies::EXT_LEARNER});

        // The actor is the instructor. Falling back to it would grade their plan instead.
        $statement->actor = $this->account_agent(self::ISSUER, 'learner-sub');

        $this->assertFalse($this->process_statement($statement, sync_lrs_competencies::VERB_VALIDATED));
        $this->assertFalse($DB->record_exists('tool_lptmanager_lrs_sync', ['statementid' => 'statement-1']));
    }

    public function test_a_learner_from_an_untrusted_issuer_is_not_resolved(): void {
        $this->resetAfterTest(true);
        $this->expectOutputRegex('/homePage .https:\/\/moodle\.example\.test. is not one this source may name learners under/');
        $this->configure_trust();
        $this->create_learner_with_plan();
        $statement = $this->asserted_statement();

        // The default logstore_xapi actor: a Moodle user id under the Moodle site URL, which would
        // otherwise match any user whose ID number happens to be that value.
        $statement->actor = $this->account_agent('https://moodle.example.test', 'learner-sub');

        $this->assertFalse($this->process_statement($statement, sync_lrs_competencies::VERB_ASSERTED));
    }

    public function test_a_learner_identified_only_by_email_is_not_resolved(): void {
        $this->resetAfterTest(true);
        $this->expectOutputRegex('/Learner has no account identity/');
        $this->configure_trust();
        $learner = $this->create_learner_with_plan();
        $statement = $this->asserted_statement();
        $statement->actor = (object) ['objectType' => 'Agent', 'mbox' => 'mailto:' . $learner->email];

        $this->assertFalse($this->process_statement($statement, sync_lrs_competencies::VERB_ASSERTED));
    }

    public function test_an_already_proficient_competency_is_not_regraded(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->expectOutputRegex('/already proficient/');
        $this->configure_trust();
        $learner = $this->create_learner_with_plan();
        $this->assertTrue($this->process_statement($this->asserted_statement(), sync_lrs_competencies::VERB_ASSERTED));
        $evidence = $DB->count_records('competency_evidence');

        // The validated statement logstore_xapi sends back for the rating above.
        $echo = $this->validated_statement();
        $echo->id = 'statement-2';
        $this->assertTrue($this->process_statement($echo, sync_lrs_competencies::VERB_VALIDATED));

        $this->assertSame($evidence, $DB->count_records('competency_evidence'));
        $this->assertTrue($DB->record_exists('tool_lptmanager_lrs_sync', [
            'statementid' => 'statement-2',
            'userid' => $learner->id,
        ]));
    }

    public function test_an_assertion_against_an_uninstalled_framework_version_is_skipped(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->expectOutputRegex('/is not installed/');
        $this->configure_trust();
        $this->create_learner_with_plan();
        $statement = $this->asserted_statement();
        $this->name_framework($statement, self::FRAMEWORK . '-v9');

        $this->assertFalse($this->process_statement($statement, sync_lrs_competencies::VERB_ASSERTED));

        $this->assertFalse($DB->record_exists('competency_usercomp', ['proficiency' => 1]));
        $this->assertFalse($DB->record_exists('tool_lptmanager_lrs_sync', ['statementid' => 'statement-1']));
    }

    public function test_an_assertion_against_a_disallowed_framework_is_skipped(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->expectOutputRegex('/is not an allowed competency framework/');
        $this->configure_trust();
        $learner = $this->create_learner_with_plan();
        $this->add_framework_version_to_plan($learner, self::FRAMEWORK . '-v2');
        $statement = $this->asserted_statement();
        $this->name_framework($statement, self::FRAMEWORK . '-v2');

        $this->assertFalse($this->process_statement($statement, sync_lrs_competencies::VERB_ASSERTED));

        $this->assertFalse($DB->record_exists('competency_usercomp', ['proficiency' => 1]));
    }

    public function test_an_assertion_grades_only_the_framework_it_names(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->expectOutputRegex('/Graded competency T0023/');
        $this->configure_trust();
        $learner = $this->create_learner_with_plan();
        $other = $this->add_framework_version_to_plan($learner, self::FRAMEWORK . '-v2');
        set_config('lrs_sync_frameworks', self::FRAMEWORK . "\n" . self::FRAMEWORK . '-v2', 'tool_lptmanager');
        $statement = $this->asserted_statement();
        $this->name_framework($statement, self::FRAMEWORK . '-v2');

        $this->assertTrue($this->process_statement($statement, sync_lrs_competencies::VERB_ASSERTED));

        $graded = $DB->get_fieldset_select('competency_usercomp', 'competencyid', 'userid = ? AND proficiency = 1', [$learner->id]);
        $this->assertSame([(int) $other->get('id')], array_map('intval', $graded));
    }

    public function test_an_assertion_naming_no_framework_is_skipped_when_the_code_is_ambiguous(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->expectOutputRegex('/more than one allowed framework/');
        $this->configure_trust();
        $learner = $this->create_learner_with_plan();
        $this->add_framework_version_to_plan($learner, self::FRAMEWORK . '-v2');
        set_config('lrs_sync_frameworks', self::FRAMEWORK . "\n" . self::FRAMEWORK . '-v2', 'tool_lptmanager');

        $this->assertFalse($this->process_statement($this->asserted_statement(), sync_lrs_competencies::VERB_ASSERTED));

        $this->assertFalse($DB->record_exists('competency_usercomp', ['proficiency' => 1]));
    }

    /**
     * Trust the test Moodle credential to assert and validate for the test realm, and the Blueprint
     * credential to assert for the other realm.
     */
    private function configure_trust(): void {
        set_config('lrs_trusted_asserters', self::LRS . ' ' . self::MOODLE_CREDENTIAL . ' ' . self::ISSUER . "/\n"
            . self::LRS . ' blueprint-credential ' . self::OTHER_ISSUER, 'tool_lptmanager');
        set_config('lrs_trusted_validators', self::LRS . '/ ' . self::MOODLE_CREDENTIAL . ' ' . self::ISSUER, 'tool_lptmanager');
    }

    /**
     * Add another framework holding T0023 and put its T0023 in the learner's plan.
     *
     * @param \stdClass $learner The learner from create_learner_with_plan().
     * @param string $iri The framework idnumber.
     * @return \core_competency\competency The framework's T0023.
     */
    private function add_framework_version_to_plan(\stdClass $learner, string $iri): \core_competency\competency {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['idnumber' => $iri]);
        $competency = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'idnumber' => 'T0023',
        ]);
        $plan = \core_competency\api::list_user_plans($learner->id);
        $generator->create_plan_competency(['planid' => reset($plan)->get('id'), 'competencyid' => $competency->get('id')]);
        return $competency;
    }

    /**
     * Name a competency framework in a statement's contextActivities.grouping.
     *
     * @param object $statement The statement to change.
     * @param string $iri The framework IRI.
     */
    private function name_framework(object $statement, string $iri): void {
        $statement->context->contextActivities = (object) ['grouping' => [(object) [
            'id' => $iri,
            'definition' => (object) ['type' => 'https://w3id.org/xapi/tla/activity-types/competency-framework'],
        ]]];
    }

    /**
     * Create a learner whose active plan holds competency T0023 in an allowed framework.
     *
     * @return \stdClass The learner.
     */
    private function create_learner_with_plan(): \stdClass {
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['idnumber' => self::FRAMEWORK]);
        $competency = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'idnumber' => 'T0023',
        ]);
        $learner = $this->getDataGenerator()->create_user(['idnumber' => 'learner-sub']);
        $plan = $generator->create_plan(['userid' => $learner->id, 'status' => \core_competency\plan::STATUS_ACTIVE]);
        $generator->create_plan_competency(['planid' => $plan->get('id'), 'competencyid' => $competency->get('id')]);
        set_config('lrs_sync_frameworks', self::FRAMEWORK, 'tool_lptmanager');
        return $learner;
    }

    /**
     * Build an xAPI agent identified by an account.
     *
     * @param string $homepage Account homePage.
     * @param string $name Account name.
     * @return object
     */
    private function account_agent(string $homepage, string $name): object {
        return (object) ['objectType' => 'Agent', 'account' => (object) ['homePage' => $homepage, 'name' => $name]];
    }

    /**
     * Build an asserted statement about the test learner, written by the test Moodle credential.
     *
     * @return object
     */
    private function asserted_statement(): object {
        return (object) [
            'id' => 'statement-1',
            'actor' => $this->account_agent(self::ISSUER, 'learner-sub'),
            'object' => (object) [
                'id' => 'https://elsewhere.example.test/activities/lab-4',
                'definition' => (object) [
                    'extensions' => (object) [
                        'https://w3id.org/xapi/tla/extensions/competency-identifier' => 'T0023',
                    ],
                ],
            ],
            'context' => (object) ['extensions' => new \stdClass()],
            'authority' => $this->account_agent(self::LRS, self::MOODLE_CREDENTIAL),
        ];
    }

    /**
     * Build a validated statement: an instructor validating the test learner.
     *
     * @return object
     */
    private function validated_statement(): object {
        $statement = $this->asserted_statement();
        $statement->actor = $this->account_agent(self::ISSUER, 'instructor-sub');
        $statement->context->extensions->{sync_lrs_competencies::EXT_LEARNER} =
            $this->account_agent(self::ISSUER, 'learner-sub');
        return $statement;
    }

    /**
     * Run the protected process_statement() method.
     *
     * @param object $statement The xAPI statement.
     * @param string $verb The verb IRI.
     * @return bool Whether the statement was applied.
     */
    private function process_statement(object $statement, string $verb): bool {
        $process = \Closure::bind(
            static function (sync_lrs_competencies $task, object $statement, string $verb): bool {
                return $task->process_statement($statement, $verb);
            },
            null,
            sync_lrs_competencies::class
        );
        return $process(new sync_lrs_competencies(), $statement, $verb);
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
