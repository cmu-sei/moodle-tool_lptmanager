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

Copyright 2024 Carnegie Mellon University.

NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS.
CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO,
WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL.
CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Licensed under a GNU GENERAL PUBLIC LICENSE - Version 3, 29 June 2007-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.

[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution. Please see Copyright notice for non-US Government use and distribution.

This Software includes and/or makes use of Third-Party Software each subject to its own license.

DM24-1177
*/

/**
 * Privacy Subsystem implementation for tool_lptmanager.
 *
 * @package    tool_lptmanager
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lptmanager\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for tool_lptmanager.
 *
 * The LRS sync task logs one row per xAPI statement it applied to a learner. Those rows belong
 * to the learner, so they are reported in the learner's user context. The competency ratings and
 * evidence the sync writes are stored and reported by core_competency.
 *
 * Crosswalk mappings record who last approved or rejected them. Those are site-wide records, so
 * the reviews are reported in the system context, and deleting a reviewer's data removes their
 * name from the mapping but keeps the decision.
 *
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string The sync log table. */
    private const TABLE = 'tool_lptmanager_lrs_sync';

    /** @var string The crosswalk table. */
    private const CROSSWALK = \tool_lptmanager\crosswalk\store::TABLE;

    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::TABLE, [
            'statementid' => 'privacy:metadata:lrs_sync:statementid',
            'userid' => 'privacy:metadata:lrs_sync:userid',
            'competencyid' => 'privacy:metadata:lrs_sync:competencyid',
            'planid' => 'privacy:metadata:lrs_sync:planid',
            'verb' => 'privacy:metadata:lrs_sync:verb',
            'exerciseid' => 'privacy:metadata:lrs_sync:exerciseid',
            'runid' => 'privacy:metadata:lrs_sync:runid',
            'timecreated' => 'privacy:metadata:lrs_sync:timecreated',
        ], 'privacy:metadata:lrs_sync');

        $collection->add_database_table(self::CROSSWALK, [
            'reviewedby' => 'privacy:metadata:crosswalk:reviewedby',
            'status' => 'privacy:metadata:crosswalk:status',
            'timereviewed' => 'privacy:metadata:crosswalk:timereviewed',
        ], 'privacy:metadata:crosswalk');

        return $collection;
    }

    /**
     * Get the contexts that hold data for the given user.
     *
     * @param int $userid The user to search.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {" . self::TABLE . "} s ON s.userid = ctx.instanceid
                 WHERE ctx.contextlevel = :contextlevel
                   AND s.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_USER, 'userid' => $userid]);

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :contextlevel
                   AND EXISTS (SELECT 1 FROM {" . self::CROSSWALK . "} c WHERE c.reviewedby = :userid)";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_SYSTEM, 'userid' => $userid]);

        return $contextlist;
    }

    /**
     * Get the users who have data in the given context.
     *
     * @param userlist $userlist The userlist to fill.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof \context_system) {
            $userlist->add_from_sql('reviewedby', "SELECT reviewedby FROM {" . self::CROSSWALK . "}", []);
            return;
        }
        if (!$context instanceof \context_user) {
            return;
        }

        $sql = "SELECT userid FROM {" . self::TABLE . "} WHERE userid = :userid";
        $userlist->add_from_sql('userid', $sql, ['userid' => $context->instanceid]);
    }

    /**
     * Export the user's sync log.
     *
     * @param approved_contextlist $contextlist The approved contexts to export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                self::export_crosswalk_reviews($context, $userid);
                continue;
            }
            if (!$context instanceof \context_user || (int) $context->instanceid !== (int) $userid) {
                continue;
            }

            $records = $DB->get_records(self::TABLE, ['userid' => $userid], 'timecreated, id');
            if (!$records) {
                continue;
            }

            $statements = [];
            foreach ($records as $record) {
                $statements[] = (object) [
                    'statementid' => $record->statementid,
                    'competencyid' => $record->competencyid,
                    'planid' => $record->planid,
                    'verb' => $record->verb,
                    'exerciseid' => $record->exerciseid,
                    'runid' => $record->runid,
                    'timecreated' => transform::datetime($record->timecreated),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('privacy:path:lrssync', 'tool_lptmanager')],
                (object) ['statements' => $statements]
            );
        }
    }

    /**
     * Delete all data in the given context.
     *
     * @param \context $context The context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context instanceof \context_system) {
            $DB->set_field_select(self::CROSSWALK, 'reviewedby', null, 'reviewedby IS NOT NULL');
        }
        if ($context instanceof \context_user) {
            $DB->delete_records(self::TABLE, ['userid' => $context->instanceid]);
        }
    }

    /**
     * Delete the user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to delete in.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                $DB->set_field(self::CROSSWALK, 'reviewedby', null, ['reviewedby' => $userid]);
            }
            if ($context instanceof \context_user && (int) $context->instanceid === (int) $userid) {
                $DB->delete_records(self::TABLE, ['userid' => $userid]);
            }
        }
    }

    /**
     * Delete the data of the approved users in the given context.
     *
     * @param approved_userlist $userlist The approved users to delete for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if ($context instanceof \context_system && $userlist->get_userids()) {
            [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
            $DB->set_field_select(self::CROSSWALK, 'reviewedby', null, "reviewedby {$insql}", $params);
            return;
        }
        if (!$context instanceof \context_user) {
            return;
        }

        // A user context only ever holds its own user's rows.
        if (in_array((int) $context->instanceid, array_map('intval', $userlist->get_userids()), true)) {
            $DB->delete_records(self::TABLE, ['userid' => $context->instanceid]);
        }
    }

    /**
     * Export the crosswalk mappings a user last reviewed.
     *
     * @param \context $context The system context.
     * @param int $userid The reviewer.
     */
    private static function export_crosswalk_reviews(\context $context, int $userid): void {
        global $DB;

        $records = $DB->get_records(self::CROSSWALK, ['reviewedby' => $userid], 'timereviewed, id');
        if (!$records) {
            return;
        }

        $reviews = [];
        foreach ($records as $record) {
            $reviews[] = (object) [
                'sourceframework' => $record->sourceframework,
                'sourceidnumber' => $record->sourceidnumber,
                'targetframework' => $record->targetframework,
                'targetidnumber' => $record->targetidnumber,
                'relation' => $record->relation,
                'status' => $record->status,
                'timereviewed' => transform::datetime($record->timereviewed),
            ];
        }

        writer::with_context($context)->export_data(
            [get_string('privacy:path:crosswalks', 'tool_lptmanager')],
            (object) ['reviews' => $reviews]
        );
    }
}
