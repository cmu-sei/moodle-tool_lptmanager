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
 * Learning plan template CSV exporter tests.
 *
 * @package tool_lptmanager
 * @copyright 2024 Carnegie Mellon University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lptmanager;

/**
 * Tests for the learning plan template CSV exporter.
 */
final class export_test extends \advanced_testcase {
    /**
     * Create a template whose competencies come from two different frameworks.
     *
     * @return \core_competency\template The template.
     */
    private function create_mixed_framework_template(): \core_competency\template {
        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework1 = $lpg->create_framework(['idnumber' => 'FW1']);
        $framework2 = $lpg->create_framework(['idnumber' => 'FW2']);
        $c1 = $lpg->create_competency(['competencyframeworkid' => $framework1->get('id')]);
        $c2 = $lpg->create_competency(['competencyframeworkid' => $framework2->get('id')]);
        $template = $lpg->create_template(['shortname' => 'Mixed']);
        $lpg->create_template_competency(['templateid' => $template->get('id'), 'competencyid' => $c1->get('id')]);
        $lpg->create_template_competency(['templateid' => $template->get('id'), 'competencyid' => $c2->get('id')]);
        return $template;
    }

    public function test_export_one_rejects_a_template_spanning_several_frameworks(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $template = $this->create_mixed_framework_template();

        $a = (object) ['template' => 'Mixed', 'first' => 'FW1', 'second' => 'FW2'];
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('multipleframeworks', 'tool_lptmanager', $a));

        (new lp_exporter())->export_one($template->get('id'));
    }

    public function test_export_all_rejects_a_template_spanning_several_frameworks(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $this->create_mixed_framework_template();

        // The exception is thrown before download_file(), so no partial file is sent.
        $a = (object) ['template' => 'Mixed', 'first' => 'FW1', 'second' => 'FW2'];
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('multipleframeworks', 'tool_lptmanager', $a));

        (new lp_exporter())->export();
    }
}
