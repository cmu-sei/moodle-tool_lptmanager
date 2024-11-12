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
 * This file contains the class to import a competency framework.
 *
 * @package   tool_lptmanager
 * @copyright 2024 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lptmanager;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

use core_competency\api;
use core_competency\external;
use grade_scale;
use stdClass;
use context_system;
use csv_import_reader;
use core\progress;

class lp_importer {

    /** @var string $error The errors message from reading the xml */
    protected $error = '';

    /** @var array $flat The flat competencies tree */
    protected $flat = array();
    /** @var array $framework The framework info */
    protected $framework = array();
    protected $mappings = array();
    protected $importid = 0;
    protected $importer = null;
    protected $foundheaders = array();
    /** @var bool $useprogressbar Control whether importing should use progress bars or not. */
    protected $useprogressbar = false;
    /** @var \core\progress\display_if_slow|null $progress The progress bar instance. */
    protected $progress = null;
    protected $categoryid = null;

    /**
     * Store an error message for display later
     * @param string $msg
     */
    public function fail($msg) {
        $this->error = $msg;
        return false;
    }

    /**
     * Get the CSV import id
     * @return string The import id.
     */
    public function get_importid() {
        return $this->importid;
    }

    /**
     * Get the list of headers required for import.
     * @return array The headers (lang strings)
     */
    public static function list_required_headers() {
        return array(
            get_string('shortname', 'tool_lptmanager'),
            get_string('description', 'tool_lptmanager'),
            get_string('descriptionformat', 'tool_lptmanager'),
            get_string('competencyframeworkidnumber', 'tool_lptmanager'),
            get_string('relatedidnumbers', 'tool_lptmanager'),
        );
    }

    /**
     * Get the list of headers found in the import.
     * @return array The found headers (names from import)
     */
    public function list_found_headers() {
        return $this->foundheaders;
    }

    /**
     * Read the data from the mapping form.
     * @param array The mapping data.
     */
    protected function read_mapping_data($data) {
        if ($data) {
            return array(
                'shortname' => $data->header0,
                'description' => $data->header1,
                'descriptionformat' => $data->header2,
                'competencyframeworkidnumber' => $data->header3,
                'relatedidnumbers' => $data->header4
            );
        } else {
            return array(
                'shortname' => 0,
                'description' => 1,
                'descriptionformat' => 2,
                'competencyframeworkidnumber' => 3,
                'relatedidnumbers' => 4
            );
        }
    }

    /**
     * Get the a column from the imported data.
     * @param array The imported raw row
     * @param index The column index we want
     * @return string The column data.
     */
    protected function get_column_data($row, $index) {
        if ($index < 0) {
            return '';
        }
        return isset($row[$index]) ? $row[$index] : '';
    }

    /**
     * Constructor - parses the raw text for sanity.
     * @param string $text The raw csv text.
     * @param string $encoding The encoding of the csv file.
     * @param string delimiter The specified delimiter for the file.
     * @param string importid The id of the csv import.
     * @param array mappingdata The mapping data from the import form.
     * @param bool $useprogressbar Whether progress bar should be displayed, to avoid html output on CLI.
     */
    public function __construct($text = null, $encoding = null, $delimiter = null, $importid = 0, $mappingdata = null,
            $useprogressbar = false, $categoryid=null) {

        global $CFG;

        // The format of our records is:
        // Parent ID number, ID number, Shortname, Description, Description format, Scale values, Scale configuration,
        // Rule type, Rule outcome, Rule config, Is framework, Taxonomy.

        // The idnumber is concatenated with the category names.
        require_once($CFG->libdir . '/csvlib.class.php');
        $this->categoryid = $categoryid;

        $type = 'competency_template';

        if (!$importid) {
            if ($text === null) {
                return;
            }
            $this->importid = csv_import_reader::get_new_iid($type);

            $this->importer = new csv_import_reader($this->importid, $type);

            if (!$this->importer->load_csv_content($text, $encoding, $delimiter)) {
                $this->fail(get_string('invalidimportfile', 'tool_lptmanager'));
                $this->importer->cleanup();
                return;
            }

        } else {
            $this->importid = $importid;
            $this->importer = new csv_import_reader($this->importid, $type);
        }

        if (!$this->importer->init()) {
            $this->fail(get_string('invalidimportfile', 'tool_lptmanager'));
            $this->importer->cleanup();
            return;
        }

        $this->foundheaders = $this->importer->get_columns();
        $this->useprogressbar = $useprogressbar;

        // We are calling from browser, display progress bar.
        if ($this->useprogressbar === true) {
            $this->progress = new \core\progress\display_if_slow(get_string('processingfile', 'tool_lptmanager'));
        } else {
            // Avoid html output on CLI scripts.
            $this->progress = new \core\progress\none();
        }   

        // TODO this process bar does not work
        $this->progress->start_progress('');

        while ($row = $this->importer->next()) {
            $mapping = $this->read_mapping_data($mappingdata);
            $shortname = $this->get_column_data($row, $mapping['shortname']);
            $description = $this->get_column_data($row, $mapping['description']);
            $descriptionformat = $this->get_column_data($row, $mapping['descriptionformat']);
            $competencyframeworkidnumber = $this->get_column_data($row, $mapping['competencyframeworkidnumber']);
            $relatedidnumbers = $this->get_column_data($row, $mapping['relatedidnumbers']);

            $competency = new stdClass();
            $competency->shortname = shorten_text(clean_param($shortname, PARAM_TEXT), 100);
            $competency->description = clean_param($description, PARAM_RAW);
            $competency->descriptionformat = clean_param($descriptionformat, PARAM_INT);
            $competency->competencyframeworkidnumber = shorten_text(clean_param($competencyframeworkidnumber, PARAM_TEXT), 100);
            $competency->relatedidnumbers = $relatedidnumbers;
            array_push($this->framework, $competency);
            $this->progress->progress(\core\progress\base::INDETERMINATE);
        }

        $this->importer->close();
        $this->progress->end_progress();
    }

    /**
     * Get parse errors.
     * @return array of errors from parsing the xml.
     */
    public function get_error() {
        return $this->error;
    }

    /**
     * Recursive function to add a competency with all it's children.
     *
     * @param stdClass $record Raw data for the new competency
     * @param competency $parent
     * @param competency_framework $framework
     */
    public function create_learning_plan_template($workrole, $categoryid = null) {

        $context = context_system::instance();
        $templates = api::list_templates('shortname', 'ASC', null, null, $context);
    
        foreach ($templates as $template) {
            if ($workrole->shortname === $template->get('shortname')) {
                debugging("template already exists", DEBUG_DEVELOPER);
                return;
            }
        }
    
        // Add template with category if provided
        $record = new \stdClass();
        $record->shortname = $workrole->shortname;
        $record->description = $workrole->description;
        $record->contextid = $context->id;
    
        // If categoryid is set, associate the template with that category
        if (!empty($categoryid)) {
            $record->coursecategoryid = $categoryid;
        }
    
        $lp = api::create_template($record);
    
        // The rest of the logic for processing competencies...
    }    


    /**
     * Recursive function to sync and add a competency with all it's children.
     *
     * @param stdClass $record Raw data for the new competency
     * @param competency $parent
     * @param competency_framework $framework
     */
    public function sync_learning_plan_template($workrole) {
        // check for existing template
        $context = context_system::instance();
        $templates = api::list_templates('shortname', 'ASC', null, null, $context);

        foreach ($templates as $template) {
            if ($workrole->get('shortname') === $template->get('shortname')) {
                debugging("template already exists", DEBUG_DEVELOPER);
                // TODO alert user
                return;
            }
        }

        // add template
        $record = new \stdClass();
        $record->shortname = $workrole->get('shortname');
        $record->description = $workrole->get('description');
        $record->contextid = 1;
        $lp = api::create_template($record);

        $competencyframeworkid = "";
        $frameworks = api::list_frameworks('shortname', 'ASC', null, null, $context);
        foreach ($frameworks as $framework) {
            if ($framework->get('id') === $workrole->get('competencyframeworkid')) {
                $competencyframeworkid = $framework->get('id');
            }
	}
	if ($competencyframeworkid === "") {
            print_error("could not find competencyframeworkid " . $workrole->get('competencyframeworkid'));
	}

    $relatedcompetencies = api::list_related_competencies($workrole->get('id'));
    foreach ($relatedcompetencies as $related) {
        $relatedid = $related->get('idnumber');
        $filters = array('idnumber' => $relatedid, 'competencyframeworkid' => $competencyframeworkid);
        $competencies = api::list_competencies($filters);
        foreach ($competencies as $competency) {
            if ($competency->get('idnumber') === $relatedid) {
                api::add_competency_to_template($lp->get('id'), $competency->get('id'));
            }
        }
    }
    }

    /**
     * Do the job.
     * @return competency_framework
     */
    public function import() {
        if ($this->useprogressbar === true) {
            $this->progress = new \core\progress\display_if_slow(get_string('importingfile', 'tool_lptmanager'));
        } else {
            $this->progress = new \core\progress\none();
        }
        $this->progress->start_progress('', count($this->framework));
    
        foreach ($this->framework as $record) {
            $record->contextid = context_system::instance()->id;
            $this->create_learning_plan_template($record, $this->categoryid); // Use the stored category ID
            $this->progress->increment_progress();
        }
        $this->progress->end_progress();
    
        $this->importer->cleanup();
    }
    
}
