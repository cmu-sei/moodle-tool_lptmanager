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
        $this->assertSame(15, $options['CURLOPT_TIMEOUT']);
    }

    public function test_failed_sync_retains_last_sync_time(): void {
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
        $task->method('sync_verb')->willReturn(null);

        $task->execute();

        $this->assertSame($lastsync, get_config('tool_lptmanager', 'lrs_last_sync'));
    }
}
