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
 * Crosswalk CSV import and review tests.
 *
 * @package    tool_lptmanager
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lptmanager\crosswalk;

/**
 * Tests for the crosswalk importer and store.
 *
 * @covers \tool_lptmanager\crosswalk\importer
 * @covers \tool_lptmanager\crosswalk\store
 */
final class importer_test extends \advanced_testcase {
    /** @var string Header in the order the generator writes it. */
    private const HEADER = 'source_framework,source_idnumber,target_framework,target_idnumber,relation,provenance,' .
        'confidence,status,reviewed_by,reviewed_at,note';

    /**
     * A CSV of the given rows under the standard header.
     *
     * @param string ...$rows
     * @return string
     */
    private function csv(string ...$rows): string {
        return implode("\n", array_merge([self::HEADER], $rows)) . "\n";
    }

    /**
     * The stored row for a source competency.
     *
     * @param string $idnumber
     * @return \stdClass
     */
    private function row(string $idnumber): \stdClass {
        global $DB;

        return $DB->get_record(store::TABLE, ['sourceidnumber' => $idnumber], '*', MUST_EXIST);
    }

    public function test_rows_are_added_then_updated_in_place(): void {
        $this->resetAfterTest();

        $result = importer::import($this->csv(
            'FW-1,K0001,FW-2,K0001,exactMatch,derived:code-identity,1,approved,,,',
            'FW-1,K0002,FW-2,K0002,closeMatch,derived:code-identity,0.5,proposed,,,"Same code, text changed"',
            'FW-1,K0003,FW-2,,deprecated,derived:code-identity,,proposed,,,Not in FW-2',
        ));
        $this->assertEquals([], $result->errors);
        $this->assertEquals(3, $result->added);
        $this->assertNull($this->row('K0003')->targetidnumber);
        $this->assertEquals('Same code, text changed', $this->row('K0002')->note);

        $result = importer::import($this->csv(
            'FW-1,K0001,FW-2,K0001,exactMatch,derived:code-identity,1,approved,,,',
            'FW-1,K0002,FW-2,K0002,closeMatch,derived:code-identity,0.75,proposed,,,"Same code, text changed"',
            'FW-1,K0003,FW-2,,deprecated,derived:code-identity,,proposed,,,Not in FW-2',
            'FW-1,K0004,FW-2,K0004,exactMatch,derived:code-identity,1,approved,,,',
        ));
        $this->assertEquals([1, 1, 2, 0], [$result->added, $result->updated, $result->unchanged, $result->kept]);
        $this->assertEquals(0.75, (float) $this->row('K0002')->confidence);
    }

    public function test_a_row_reviewed_on_the_site_is_kept_on_reimport(): void {
        $this->resetAfterTest();
        $reviewer = $this->getDataGenerator()->create_user();

        importer::import($this->csv('FW-1,K0002,FW-2,K0002,closeMatch,derived:code-identity,0.5,proposed,,,'));
        store::review($this->row('K0002')->id, store::REJECTED, $reviewer->id);

        $result = importer::import($this->csv('FW-1,K0002,FW-2,K0002,closeMatch,derived:code-identity,0.9,approved,,,'));
        $this->assertEquals(1, $result->kept);
        $row = $this->row('K0002');
        $this->assertEquals(store::REJECTED, $row->status);
        $this->assertEquals(0.5, (float) $row->confidence);
        $this->assertEquals($reviewer->id, $row->reviewedby);
    }

    public function test_one_invalid_row_imports_nothing(): void {
        global $DB;
        $this->resetAfterTest();

        $result = importer::import($this->csv(
            'FW-1,K0001,FW-2,K0001,exactMatch,,1,approved,,,',
            'FW-1,K0002,FW-2,K0002,sameAs,,1,approved,,,',
            'FW-1,K0003,FW-2,K0003,deprecated,,,proposed,,,',
            'FW-1,K0004,FW-2,,closeMatch,,,proposed,,,',
            'FW-1,K0005,FW-2,K0005,closeMatch,,1.5,maybe,,,',
            'FW-1,K0001,FW-2,K0001,exactMatch,,1,approved,,,',
        ));

        $this->assertCount(5, $result->errors);
        $this->assertStringContainsString('Line 3:', $result->errors[0]);
        $this->assertStringContainsString("'sameAs'", $result->errors[0]);
        $this->assertStringContainsString('deprecated row has no target_idnumber', $result->errors[1]);
        $this->assertStringContainsString('target_idnumber is empty', $result->errors[2]);
        $this->assertStringContainsString("'maybe'", $result->errors[3]);
        $this->assertStringContainsString('same mapping is on line 2', $result->errors[4]);
        $this->assertEquals(0, $DB->count_records(store::TABLE));
    }

    public function test_a_file_without_the_required_columns_is_refused(): void {
        $this->resetAfterTest();

        $result = importer::import("source_framework,source_idnumber,relation\nFW-1,K0001,exactMatch\n");

        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('target_framework, target_idnumber', $result->errors[0]);
    }

    public function test_columns_may_come_in_any_order_and_status_defaults_to_proposed(): void {
        $this->resetAfterTest();

        $result = importer::import("\xEF\xBB\xBFrelation,target_idnumber,target_framework,source_idnumber,source_framework\n" .
            "exactMatch,K0001,FW-2,K0001,FW-1\n");

        $this->assertEquals(1, $result->added);
        $this->assertEquals(store::PROPOSED, $this->row('K0001')->status);
    }

    public function test_review_records_the_reviewer_and_only_approves_or_rejects(): void {
        $this->resetAfterTest();
        $reviewer = $this->getDataGenerator()->create_user();
        importer::import($this->csv('FW-1,K0002,FW-2,K0002,closeMatch,,0.5,proposed,,,'));
        $id = $this->row('K0002')->id;

        store::review($id, store::APPROVED, $reviewer->id);
        $row = $this->row('K0002');
        $this->assertEquals(store::APPROVED, $row->status);
        $this->assertEquals($reviewer->id, $row->reviewedby);
        $this->assertNotEmpty($row->timereviewed);

        $this->expectException(\coding_exception::class);
        store::review($id, store::PROPOSED, $reviewer->id);
    }

    public function test_crosswalks_are_listed_per_source_and_target_pair(): void {
        $this->resetAfterTest();
        importer::import($this->csv(
            'FW-1,K0001,FW-2,K0001,exactMatch,,1,approved,,,',
            'FW-1,K0001,FW-3,K0001,exactMatch,,1,approved,,,',
            'FW-1,K0002,FW-3,K0002,closeMatch,,0.5,proposed,,,',
        ));

        $crosswalks = store::get_crosswalks();

        $this->assertCount(2, $crosswalks);
        $this->assertEquals(['FW-1', 'FW-2', 1, 1, 0], [$crosswalks[0]->sourceframework, $crosswalks[0]->targetframework,
            (int) $crosswalks[0]->total, (int) $crosswalks[0]->approved, (int) $crosswalks[0]->proposed]);
        $this->assertEquals(['FW-1', 'FW-3', 2, 1, 1], [$crosswalks[1]->sourceframework, $crosswalks[1]->targetframework,
            (int) $crosswalks[1]->total, (int) $crosswalks[1]->approved, (int) $crosswalks[1]->proposed]);
    }
}
