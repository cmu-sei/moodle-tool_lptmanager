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

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the LRS competency sync task.
 */
final class sync_lrs_competencies_test extends \advanced_testcase {
    public function test_fetch_statements_configures_bounded_timeouts(): void {
        $this->resetAfterTest(true);
        set_config('lrs_request_timeout', 20, 'tool_lptmanager');
        $curl = $this->getMockBuilder(\curl::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setopt', 'setHeader', 'get', 'get_info', 'get_errno'])
            ->getMock();
        $options = [];
        $curl->expects($this->once())
            ->method('setopt')
            ->willReturnCallback(static function (array $curloptions) use (&$options): void {
                $options = $curloptions;
            });
        $curl->method('get')->willReturn('{"statements": []}');
        $curl->method('get_info')->willReturn(['http_code' => 200]);
        $curl->method('get_errno')->willReturn(0);

        $task = $this->getMockBuilder(sync_lrs_competencies::class)
            ->onlyMethods(['create_curl'])
            ->getMock();
        $task->method('create_curl')->willReturn($curl);

        $fetchstatements = \Closure::bind(
            static function (sync_lrs_competencies $task): ?object {
                return $task->fetch_statements('https://lrs.example.test/xapi/statements', 'key', 'secret');
            },
            null,
            sync_lrs_competencies::class
        );
        $response = $fetchstatements($task);

        $this->assertEquals([], $response->statements);
        $this->assertSame(5, $options['CURLOPT_CONNECTTIMEOUT']);
        $this->assertSame(20, $options['CURLOPT_TIMEOUT']);
        $this->assertFalse($options['CURLOPT_FOLLOWLOCATION']);
    }

    public function test_fetch_statements_rejects_non_object_json(): void {
        $this->resetAfterTest(true);
        $curl = $this->getMockBuilder(\curl::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setopt', 'setHeader', 'get', 'get_info', 'get_errno'])
            ->getMock();
        $curl->method('get')->willReturn('[]');
        $curl->method('get_info')->willReturn(['http_code' => 200]);
        $curl->method('get_errno')->willReturn(0);

        $task = $this->getMockBuilder(sync_lrs_competencies::class)
            ->onlyMethods(['create_curl'])
            ->getMock();
        $task->method('create_curl')->willReturn($curl);

        $fetchstatements = \Closure::bind(
            static function (sync_lrs_competencies $task): ?object {
                return $task->fetch_statements('https://lrs.example.test/xapi/statements', 'key', 'secret');
            },
            null,
            sync_lrs_competencies::class
        );

        $this->assertNull($fetchstatements($task));
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
            ->willReturnOnConsecutiveCalls(null, 0);

        $task->execute();

        $this->assertSame($lastsync, get_config('tool_lptmanager', 'lrs_last_sync'));
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
            ->willReturnCallback(static function () use (&$events): int {
                $events[] = 'sync';
                return 0;
            });

        $task->execute();

        $this->assertSame(['timestamp', 'sync', 'sync'], $events);
        $this->assertSame($syncstart, get_config('tool_lptmanager', 'lrs_last_sync'));
    }

    public function test_sync_verb_rejects_repeated_pagination_urls(): void {
        $this->resetAfterTest(true);
        set_config('lrs_max_pages_per_verb', 20, 'tool_lptmanager');
        $response = (object) [
            'statements' => [],
            'more' => '/xapi/statements?cursor=repeat',
        ];

        $task = $this->getMockBuilder(sync_lrs_competencies::class)
            ->onlyMethods(['fetch_statements'])
            ->getMock();
        $task->expects($this->exactly(2))
            ->method('fetch_statements')
            ->willReturn($response);

        $syncverb = \Closure::bind(
            static function (sync_lrs_competencies $task): ?int {
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

        $this->assertNull($syncverb($task));
    }
}
