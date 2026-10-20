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
 * Stored competency crosswalks.
 *
 * @package    tool_lptmanager
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lptmanager\crosswalk;

/**
 * Reads and reviews the mappings in tool_lptmanager_crosswalk.
 *
 * A crosswalk is every row with the same source and target framework ID number. Each row maps one
 * source competency to one target competency with a SKOS relation, or marks it deprecated with no
 * target. Rows are proposed until a person approves or rejects them, except rows imported as
 * approved because the way they were derived needs no review (same code and text).
 *
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class store {
    /** @var string The crosswalk table. */
    public const TABLE = 'tool_lptmanager_crosswalk';

    /** @var string[] The relations a row may have; deprecated means no successor. */
    public const RELATIONS = ['exactMatch', 'closeMatch', 'broadMatch', 'narrowMatch', 'relatedMatch', 'deprecated'];

    /** @var string A mapping waiting for review. */
    public const PROPOSED = 'proposed';
    /** @var string A mapping links may be moved through. */
    public const APPROVED = 'approved';
    /** @var string A mapping a reviewer turned down. */
    public const REJECTED = 'rejected';
    /** @var string[] Every status. */
    public const STATUSES = [self::PROPOSED, self::APPROVED, self::REJECTED];

    /**
     * Every crosswalk, with its row count per status.
     *
     * @return \stdClass[] sourceframework, targetframework, total, proposed, approved, rejected.
     */
    public static function get_crosswalks(): array {
        global $DB;

        $sql = "SELECT sourceframework, targetframework, COUNT(1) AS total,
                       SUM(CASE WHEN status = :proposed THEN 1 ELSE 0 END) AS proposed,
                       SUM(CASE WHEN status = :approved THEN 1 ELSE 0 END) AS approved,
                       SUM(CASE WHEN status = :rejected THEN 1 ELSE 0 END) AS rejected
                  FROM {" . self::TABLE . "}
              GROUP BY sourceframework, targetframework
              ORDER BY sourceframework, targetframework";
        $params = ['proposed' => self::PROPOSED, 'approved' => self::APPROVED, 'rejected' => self::REJECTED];

        // A source framework can have several targets, so the rows have no unique first column.
        $crosswalks = [];
        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            $crosswalks[] = $record;
        }
        $records->close();

        return $crosswalks;
    }

    /**
     * The rows of one crosswalk, ordered by source competency.
     *
     * @param string $source Source framework ID number.
     * @param string $target Target framework ID number.
     * @param string|null $status Only rows with this status, or all.
     * @param int $from Offset.
     * @param int $limit Page size, 0 for all.
     * @return \stdClass[]
     */
    public static function get_rows(string $source, string $target, ?string $status = null, int $from = 0,
            int $limit = 0): array {
        global $DB;

        $conditions = ['sourceframework' => $source, 'targetframework' => $target];
        if ($status !== null) {
            $conditions['status'] = $status;
        }

        return array_values($DB->get_records(self::TABLE, $conditions, 'sourceidnumber, targetidnumber, id', '*',
            $from, $limit));
    }

    /**
     * Count the rows of one crosswalk.
     *
     * @param string $source Source framework ID number.
     * @param string $target Target framework ID number.
     * @param string|null $status Only rows with this status, or all.
     * @return int
     */
    public static function count_rows(string $source, string $target, ?string $status = null): int {
        global $DB;

        $conditions = ['sourceframework' => $source, 'targetframework' => $target];
        if ($status !== null) {
            $conditions['status'] = $status;
        }

        return $DB->count_records(self::TABLE, $conditions);
    }

    /**
     * Approve or reject a row, recording who did it.
     *
     * @param int $id Row id.
     * @param string $status approved or rejected.
     * @param int $userid The reviewer.
     */
    public static function review(int $id, string $status, int $userid): void {
        global $DB;

        if ($status !== self::APPROVED && $status !== self::REJECTED) {
            throw new \coding_exception("A review approves or rejects, not '{$status}'");
        }
        $DB->get_record(self::TABLE, ['id' => $id], 'id', MUST_EXIST);
        $now = time();
        $DB->update_record(self::TABLE, (object) [
            'id' => $id,
            'status' => $status,
            'reviewedby' => $userid,
            'timereviewed' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * A framework's short name.
     *
     * @param string $idnumber Framework ID number.
     * @return string|null Null when no framework on this site has the ID number.
     */
    public static function get_framework_shortname(string $idnumber): ?string {
        global $DB;

        $shortname = $DB->get_field('competency_framework', 'shortname', ['idnumber' => $idnumber]);
        return $shortname === false ? null : $shortname;
    }

    /**
     * Competency short names by ID number, for the ones asked about that the framework has.
     *
     * @param string $framework Framework ID number.
     * @param array $idnumbers Competency ID numbers; nulls and duplicates are ignored.
     * @return string[]
     */
    public static function get_competency_names(string $framework, array $idnumbers): array {
        global $DB;

        $frameworkid = $DB->get_field('competency_framework', 'id', ['idnumber' => $framework]);
        $idnumbers = array_values(array_unique(array_filter($idnumbers, fn($i) => $i !== null && $i !== '')));
        if (!$frameworkid || !$idnumbers) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($idnumbers, SQL_PARAMS_NAMED);
        $params['frameworkid'] = $frameworkid;

        return $DB->get_records_select_menu('competency', "competencyframeworkid = :frameworkid AND idnumber {$insql}",
            $params, '', 'idnumber, shortname');
    }
}
