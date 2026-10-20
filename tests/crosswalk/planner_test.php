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
 * Crosswalk link report tests.
 *
 * @package    tool_lptmanager
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lptmanager\crosswalk;

/**
 * Tests for the crosswalk planner.
 *
 * @covers \tool_lptmanager\crosswalk\planner
 */
final class planner_test extends \advanced_testcase {
    /** @var \core_competency\competency[] Source competencies by ID number. */
    private array $source = [];

    /** @var \core_competency\competency[] Target competencies by ID number. */
    private array $target = [];

    /**
     * Two framework versions and a crosswalk between them with one row of each kind.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $from = $lpg->create_framework(['idnumber' => 'FW-1']);
        $to = $lpg->create_framework(['idnumber' => 'FW-2']);
        foreach (['K0001', 'K0002', 'K0003', 'K0004', 'K0005', 'K0006', 'K0007', 'K0008', 'K0009'] as $idnumber) {
            $this->source[$idnumber] = $lpg->create_competency(
                ['competencyframeworkid' => $from->get('id'), 'idnumber' => $idnumber]);
        }
        foreach (['K0001', 'K0002', 'K0004', 'K0006', 'K0007'] as $idnumber) {
            $this->target[$idnumber] = $lpg->create_competency(
                ['competencyframeworkid' => $to->get('id'), 'idnumber' => $idnumber]);
        }

        $result = importer::import(implode("\n", [
            'source_framework,source_idnumber,target_framework,target_idnumber,relation,status',
            'FW-1,K0001,FW-2,K0001,exactMatch,approved',
            'FW-1,K0002,FW-2,K0002,closeMatch,proposed',
            'FW-1,K0003,FW-2,,deprecated,proposed',
            'FW-1,K0004,FW-2,K0004,broadMatch,approved',
            'FW-1,K0005,FW-2,K0099,exactMatch,approved',
            'FW-1,K0006,FW-2,K0006,closeMatch,approved',
            'FW-1,K0006,FW-2,K0007,closeMatch,approved',
            'FW-1,K0007,FW-2,K0007,exactMatch,rejected',
            'FW-1,K0009,FW-2,K0001,exactMatch,approved',
        ]) . "\n");
        $this->assertEquals([], $result->errors);
    }

    public function test_each_competency_gets_one_outcome(): void {
        $planner = new planner('FW-1', 'FW-2');
        $outcome = fn($idnumber) => $planner->classify($idnumber)->outcome;

        $this->assertEquals(planner::AUTO, $outcome('K0001'));
        $this->assertEquals($this->target['K0001']->get('id'), $planner->classify('K0001')->targetid);
        $this->assertEquals(planner::REVIEW, $outcome('K0002'));
        $this->assertEquals(planner::MANUAL, $outcome('K0003'), 'deprecated');
        $this->assertEquals(planner::MANUAL, $outcome('K0004'), 'broader');
        $this->assertEquals(planner::MANUAL, $outcome('K0005'), 'target not installed');
        $this->assertNull($planner->classify('K0005')->targetid);
        $this->assertEquals(planner::MANUAL, $outcome('K0006'), 'two targets');
        $this->assertEquals(planner::MANUAL, $outcome('K0007'), 'rejected');
        $this->assertEquals(planner::UNMAPPED, $outcome('K0008'));
    }

    public function test_the_report_covers_every_kind_of_link_to_the_source_framework_only(): void {
        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $course = $this->getDataGenerator()->create_course(['shortname' => 'C1']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'Reading']);
        $template = $lpg->create_template(['shortname' => 'Analyst']);
        $user = $this->getDataGenerator()->create_user();
        $plan = $lpg->create_plan(['userid' => $user->id, 'name' => 'My plan']);

        $lpg->create_course_competency(['courseid' => $course->id, 'competencyid' => $this->source['K0001']->get('id')]);
        $lpg->create_course_competency(['courseid' => $course->id, 'competencyid' => $this->target['K0002']->get('id')]);
        $lpg->create_course_module_competency(['cmid' => $page->cmid, 'competencyid' => $this->source['K0002']->get('id')]);
        $lpg->create_template_competency(['templateid' => $template->get('id'),
            'competencyid' => $this->source['K0003']->get('id')]);
        $lpg->create_plan_competency(['planid' => $plan->get('id'), 'competencyid' => $this->source['K0008']->get('id')]);
        $lpg->create_plan_competency(['planid' => $plan->get('id'), 'competencyid' => $this->source['K0009']->get('id')]);

        $report = (new planner('FW-1', 'FW-2'))->link_report();

        $this->assertEquals([planner::AUTO => 2, planner::REVIEW => 1, planner::MANUAL => 1, planner::UNMAPPED => 1],
            $report->totals);
        $summary = array_map(fn($l) => [$l->outcome, $l->holder, $l->idnumber], $report->links);
        $this->assertEquals([
            [planner::AUTO, 'Course: C1', 'K0001'],
            [planner::AUTO, 'Learning plan: My plan', 'K0009'],
            [planner::REVIEW, 'Activity: Reading (C1)', 'K0002'],
            [planner::MANUAL, 'Learning plan template: Analyst', 'K0003'],
            [planner::UNMAPPED, 'Learning plan: My plan', 'K0008'],
        ], $summary);
    }

    public function test_a_source_framework_that_is_not_installed_has_no_links(): void {
        $report = (new planner('FW-9', 'FW-2'))->link_report();

        $this->assertEquals(0, array_sum($report->totals));
        $this->assertEquals([], $report->links);
    }
}
