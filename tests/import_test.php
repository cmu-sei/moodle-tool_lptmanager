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
 * Learning plan template CSV importer tests.
 *
 * @package tool_lptmanager
 * @copyright 2024 Carnegie Mellon University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lptmanager;

use core_competency\api;

/**
 * Tests for the learning plan template CSV importer.
 */
final class import_test extends \advanced_testcase {
    /**
     * A minimal CSV matching lp_importer's positional column mapping:
     * shortname, description, descriptionformat, competencyframeworkidnumber, relatedidnumbers.
     *
     * The framework and cross reference columns are deliberately empty so that no framework
     * lookup is attempted, which keeps the debugging output of an import deterministic.
     */
    private const CSV = <<<'CSV'
    Short name,Description,Description format,Competency Framework ID Number,Cross-referenced competency ID numbers
    Threat Analyst,"Analyses adversary activity.",1,,
    Incident Responder,"Responds to reported incidents.",1,,
    CSV;

    public function test_valid_csv_parses_without_error(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $importer = new lp_importer(self::CSV);

        $this->assertSame('', $importer->get_error());
        $this->assertGreaterThan(0, $importer->get_importid());
        $this->assertSame([
            'Short name',
            'Description',
            'Description format',
            'Competency Framework ID Number',
            'Cross-referenced competency ID numbers',
        ], $importer->list_found_headers());
    }

    public function test_import_creates_learning_plan_templates(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');

        $output = $this->run_import(self::CSV);

        // One debugging message is emitted per created template.
        $this->assertDebuggingCalledCount(2);
        $this->assertStringContainsString('created successfully', $output);
        $this->assertEqualsCanonicalizing(
            ['Incident Responder', 'Threat Analyst'],
            $this->get_system_template_shortnames()
        );

        $templates = api::list_templates('shortname', 'ASC', 0, 0, \context_system::instance());
        $template = reset($templates);
        $this->assertSame('Incident Responder', $template->get('shortname'));
        $this->assertSame('Responds to reported incidents.', $template->get('description'));
        $this->assertSame((int) \context_system::instance()->id, (int) $template->get('contextid'));
    }

    public function test_import_skips_duplicate_shortnames(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');

        $this->run_import(self::CSV);
        $this->assertDebuggingCalledCount(2);

        $output = $this->run_import(self::CSV);

        // The second pass skips both rows, again one debugging message per row.
        $this->assertDebuggingCalledCount(2);
        $this->assertStringContainsString('already exists', $output);
        $this->assertEqualsCanonicalizing(
            ['Incident Responder', 'Threat Analyst'],
            $this->get_system_template_shortnames()
        );
    }

    public function test_empty_file_reports_an_invalid_file_error(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $importer = new lp_importer('');

        $this->assertSame(get_string('invalidimportfile', 'tool_lptmanager'), $importer->get_error());
    }

    public function test_mismatched_columns_report_an_invalid_file_error(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $csv = <<<'CSV'
        Short name,Description,Description format,Competency Framework ID Number,Cross-referenced competency ID numbers
        Threat Analyst,"Analyses adversary activity."
        CSV;

        $importer = new lp_importer($csv);

        $this->assertSame(get_string('invalidimportfile', 'tool_lptmanager'), $importer->get_error());
    }

    /**
     * Parse and import a CSV, capturing the notifications the importer echoes.
     *
     * @param string $csv The raw CSV content.
     * @return string The captured output.
     */
    private function run_import(string $csv): string {
        $importer = new lp_importer($csv);
        $this->assertSame('', $importer->get_error());

        ob_start();
        $importer->import();
        return (string) ob_get_clean();
    }

    /**
     * Get the shortnames of every learning plan template in the system context.
     *
     * @return array The template shortnames.
     */
    private function get_system_template_shortnames(): array {
        $shortnames = [];
        foreach (api::list_templates('shortname', 'ASC', 0, 0, \context_system::instance()) as $template) {
            $shortnames[] = $template->get('shortname');
        }
        return $shortnames;
    }
}
