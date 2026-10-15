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
 * Privacy provider tests.
 *
 * @package    tool_lptmanager
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lptmanager\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Tests for the tool_lptmanager privacy provider.
 *
 * @covers \tool_lptmanager\privacy\provider
 */
final class provider_test extends provider_testcase {
    /**
     * Insert a sync log row for a user.
     *
     * @param int $userid The user the row belongs to.
     * @param string $statementid The xAPI statement ID.
     */
    private function add_sync_record(int $userid, string $statementid): void {
        global $DB;

        $DB->insert_record('tool_lptmanager_lrs_sync', (object) [
            'statementid' => $statementid,
            'userid' => $userid,
            'competencyid' => 7,
            'planid' => 3,
            'verb' => 'https://w3id.org/xapi/tla/verbs/asserted',
            'exerciseid' => '11111111-1111-1111-1111-111111111111',
            'runid' => '22222222-2222-2222-2222-222222222222',
            'timecreated' => time(),
        ]);
    }

    /**
     * Count a user's sync log rows.
     *
     * @param int $userid The user.
     * @return int
     */
    private function count_records(int $userid): int {
        global $DB;

        return $DB->count_records('tool_lptmanager_lrs_sync', ['userid' => $userid]);
    }

    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('tool_lptmanager'));
        $items = $collection->get_collection();

        $this->assertCount(1, $items);
        $this->assertEquals('tool_lptmanager_lrs_sync', $items[0]->get_name());
        $this->assertArrayHasKey('userid', $items[0]->get_privacy_fields());
    }

    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        $this->assertEmpty(provider::get_contexts_for_userid($user->id)->get_contextids());

        $this->add_sync_record($user->id, 'aaaaaaaa-0000-0000-0000-000000000001');
        $this->add_sync_record($user->id, 'aaaaaaaa-0000-0000-0000-000000000002');

        $contextids = provider::get_contexts_for_userid($user->id)->get_contextids();
        $this->assertEquals([\context_user::instance($user->id)->id], array_values($contextids));
        $this->assertEmpty(provider::get_contexts_for_userid($other->id)->get_contextids());
    }

    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->add_sync_record($user->id, 'aaaaaaaa-0000-0000-0000-000000000001');

        $userlist = new userlist(\context_user::instance($user->id), 'tool_lptmanager');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$user->id], $userlist->get_userids());

        $userlist = new userlist(\context_user::instance($other->id), 'tool_lptmanager');
        provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist->get_userids());

        $userlist = new userlist(\context_system::instance(), 'tool_lptmanager');
        provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist->get_userids());
    }

    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->add_sync_record($user->id, 'aaaaaaaa-0000-0000-0000-000000000001');
        $this->add_sync_record($user->id, 'aaaaaaaa-0000-0000-0000-000000000002');
        $context = \context_user::instance($user->id);

        $this->export_context_data_for_user($user->id, $context, 'tool_lptmanager');

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([get_string('privacy:path:lrssync', 'tool_lptmanager')]);
        $this->assertCount(2, $data->statements);
        $this->assertEquals('aaaaaaaa-0000-0000-0000-000000000001', $data->statements[0]->statementid);
        $this->assertEquals('22222222-2222-2222-2222-222222222222', $data->statements[0]->runid);
    }

    public function test_delete_data_for_all_users_in_context(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->add_sync_record($user->id, 'aaaaaaaa-0000-0000-0000-000000000001');
        $this->add_sync_record($other->id, 'aaaaaaaa-0000-0000-0000-000000000002');

        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertEquals(1, $this->count_records($user->id));

        provider::delete_data_for_all_users_in_context(\context_user::instance($user->id));
        $this->assertEquals(0, $this->count_records($user->id));
        $this->assertEquals(1, $this->count_records($other->id));
    }

    public function test_delete_data_for_user(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->add_sync_record($user->id, 'aaaaaaaa-0000-0000-0000-000000000001');
        $this->add_sync_record($other->id, 'aaaaaaaa-0000-0000-0000-000000000002');

        // Another user's context in the approved list must not delete this user's rows.
        $contextlist = new approved_contextlist($user, 'tool_lptmanager', [\context_user::instance($other->id)->id]);
        provider::delete_data_for_user($contextlist);
        $this->assertEquals(1, $this->count_records($other->id));

        $contextlist = new approved_contextlist($user, 'tool_lptmanager', [\context_user::instance($user->id)->id]);
        provider::delete_data_for_user($contextlist);
        $this->assertEquals(0, $this->count_records($user->id));
        $this->assertEquals(1, $this->count_records($other->id));
    }

    public function test_delete_data_for_users(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->add_sync_record($user->id, 'aaaaaaaa-0000-0000-0000-000000000001');
        $this->add_sync_record($other->id, 'aaaaaaaa-0000-0000-0000-000000000002');
        $context = \context_user::instance($user->id);

        provider::delete_data_for_users(new approved_userlist($context, 'tool_lptmanager', [$other->id]));
        $this->assertEquals(1, $this->count_records($user->id));

        provider::delete_data_for_users(new approved_userlist($context, 'tool_lptmanager', [$user->id]));
        $this->assertEquals(0, $this->count_records($user->id));
        $this->assertEquals(1, $this->count_records($other->id));
    }
}
