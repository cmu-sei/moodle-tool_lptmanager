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
 * Crosswalk CSV import.
 *
 * @package    tool_lptmanager
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lptmanager\crosswalk;

/**
 * Imports a crosswalk CSV into tool_lptmanager_crosswalk.
 *
 * The CSV has a header row naming its columns: source_framework, source_idnumber,
 * target_framework, target_idnumber, relation, provenance, confidence, status and note. Columns
 * may come in any order; reviewed_by and reviewed_at are ignored, since a name in a file is not
 * a reviewer on this site. A row is identified by its source and target framework and competency,
 * so importing a newer file updates rows in place. Rows a person has reviewed here are kept as
 * they are, and rows missing from the file are left alone.
 *
 * The file is checked in full first and nothing is imported if any row is invalid.
 *
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class importer {
    /** @var string[] Columns every file must have. */
    private const REQUIRED = ['source_framework', 'source_idnumber', 'target_framework', 'target_idnumber', 'relation'];

    /** @var int Longest framework or competency ID number Moodle stores. */
    private const IDNUMBER_MAX = 100;

    /** @var int Errors reported before giving up on a file. */
    private const MAX_ERRORS = 20;

    /**
     * Import a crosswalk CSV.
     *
     * @param string $content The CSV.
     * @return \stdClass added, updated, unchanged and kept counts, and errors: a list of messages.
     *     When there are errors, nothing was imported.
     */
    public static function import(string $content): \stdClass {
        global $DB;

        $result = (object) ['added' => 0, 'updated' => 0, 'unchanged' => 0, 'kept' => 0, 'errors' => []];
        $rows = self::parse($content, $result->errors);
        if ($result->errors) {
            return $result;
        }

        $existing = [];
        foreach (array_unique(array_map(fn($r) => $r->sourceframework . "\n" . $r->targetframework, $rows)) as $pair) {
            [$source, $target] = explode("\n", $pair);
            foreach (store::get_rows($source, $target) as $record) {
                $existing[self::key($record)] = $record;
            }
        }

        $now = time();
        $transaction = $DB->start_delegated_transaction();
        foreach ($rows as $row) {
            $record = $existing[self::key($row)] ?? null;
            if ($record === null) {
                $row->timecreated = $now;
                $row->timemodified = $now;
                $DB->insert_record(store::TABLE, $row);
                $result->added++;
            } else if ($record->timereviewed) {
                $result->kept++;
            } else if (self::same($record, $row)) {
                $result->unchanged++;
            } else {
                $row->id = $record->id;
                $row->timemodified = $now;
                $DB->update_record(store::TABLE, $row);
                $result->updated++;
            }
        }
        $transaction->allow_commit();

        return $result;
    }

    /**
     * Parse and check every row.
     *
     * @param string $content The CSV.
     * @param string[] $errors Filled with one message per problem.
     * @return \stdClass[] Records ready to store.
     */
    private static function parse(string $content, array &$errors): array {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, preg_replace('/^\xEF\xBB\xBF/', '', $content));
        rewind($handle);

        $header = fgetcsv($handle, 0, ',', '"', '');
        $header = $header ? array_map('trim', $header) : [];
        $missing = array_diff(self::REQUIRED, $header);
        if ($missing) {
            $errors[] = get_string('crosswalkmissingcolumns', 'tool_lptmanager', implode(', ', $missing));
            fclose($handle);
            return [];
        }

        $rows = [];
        $seen = [];
        $line = 1;
        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false && count($errors) < self::MAX_ERRORS) {
            $line++;
            if ($cells === [null]) {
                continue;
            }
            $cells = array_pad(array_map('trim', $cells), count($header), '');
            $cell = array_combine($header, array_slice($cells, 0, count($header)));

            $problem = self::check($cell);
            if ($problem === null) {
                $row = (object) [
                    'sourceframework' => $cell['source_framework'],
                    'sourceidnumber' => $cell['source_idnumber'],
                    'targetframework' => $cell['target_framework'],
                    'targetidnumber' => $cell['target_idnumber'] === '' ? null : $cell['target_idnumber'],
                    'relation' => $cell['relation'],
                    'provenance' => ($cell['provenance'] ?? '') === '' ? null : $cell['provenance'],
                    'confidence' => ($cell['confidence'] ?? '') === '' ? null : round((float) $cell['confidence'], 2),
                    'status' => ($cell['status'] ?? '') === '' ? store::PROPOSED : $cell['status'],
                    'note' => ($cell['note'] ?? '') === '' ? null : $cell['note'],
                ];
                if (isset($seen[self::key($row)])) {
                    $problem = get_string('crosswalkduplicaterow', 'tool_lptmanager', $seen[self::key($row)]);
                }
                $seen[self::key($row)] = $line;
                $rows[] = $row;
            }
            if ($problem !== null) {
                $errors[] = get_string('crosswalkrowerror', 'tool_lptmanager', (object) ['line' => $line, 'error' => $problem]);
            }
        }
        fclose($handle);

        if (!$rows && !$errors) {
            $errors[] = get_string('crosswalkempty', 'tool_lptmanager');
        }

        return $rows;
    }

    /**
     * What is wrong with one row, if anything.
     *
     * @param array $cell Column name to value.
     * @return string|null
     */
    private static function check(array $cell): ?string {
        foreach (['source_framework', 'source_idnumber', 'target_framework'] as $column) {
            if ($cell[$column] === '') {
                return get_string('crosswalkemptycell', 'tool_lptmanager', $column);
            }
        }
        foreach (['source_framework', 'source_idnumber', 'target_framework', 'target_idnumber'] as $column) {
            if (\core_text::strlen($cell[$column]) > self::IDNUMBER_MAX) {
                return get_string('crosswalktoolong', 'tool_lptmanager', $column);
            }
        }
        if (!in_array($cell['relation'], store::RELATIONS, true)) {
            return get_string('crosswalkbadrelation', 'tool_lptmanager', $cell['relation']);
        }
        if ($cell['relation'] === 'deprecated' && $cell['target_idnumber'] !== '') {
            return get_string('crosswalkdeprecatedtarget', 'tool_lptmanager');
        }
        if ($cell['relation'] !== 'deprecated' && $cell['target_idnumber'] === '') {
            return get_string('crosswalkemptycell', 'tool_lptmanager', 'target_idnumber');
        }
        $status = $cell['status'] ?? '';
        if ($status !== '' && !in_array($status, store::STATUSES, true)) {
            return get_string('crosswalkbadstatus', 'tool_lptmanager', $status);
        }
        $confidence = $cell['confidence'] ?? '';
        if ($confidence !== '' && (!is_numeric($confidence) || $confidence < 0 || $confidence > 1)) {
            return get_string('crosswalkbadconfidence', 'tool_lptmanager', $confidence);
        }

        return null;
    }

    /**
     * The identity of a row: both frameworks and both competencies.
     *
     * @param \stdClass $row
     * @return string
     */
    private static function key(\stdClass $row): string {
        return implode("\n", [$row->sourceframework, $row->targetframework, $row->sourceidnumber,
            $row->targetidnumber ?? '']);
    }

    /**
     * Whether a stored row already says what the file says.
     *
     * @param \stdClass $record Stored row.
     * @param \stdClass $row Row from the file.
     * @return bool
     */
    private static function same(\stdClass $record, \stdClass $row): bool {
        $confidence = fn($v) => $v === null ? null : round((float) $v, 2);

        return $record->relation === $row->relation
            && $record->provenance === $row->provenance
            && $confidence($record->confidence) === $confidence($row->confidence)
            && $record->status === $row->status
            && $record->note === $row->note;
    }
}
