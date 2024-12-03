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
 * This file contains the exporter for a learning plan template.
 *
 * @package   tool_lptmanager
 * @copyright 2024 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lptmanager;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

use core_competency\api;
use stdClass;
use csv_export_writer;
use context_system;

class lp_exporter {

    /** @var $error string */
    protected $error = '';
    protected $template;

    /**
     * Constructor
     */
    public function __construct() {
    }

    /**
     * Export all the competencies from this framework to a csv file.
     */
    public function export_one($templateid) {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');

        $this->template = api::read_template($templateid);

	    $writer = new csv_export_writer();
        $filename = clean_param(preg_replace('/\s+/', '_', $this->template->get('shortname')) . '-' . $this->template->get('id'), PARAM_FILE);
        $writer->set_filename($filename);
        $headers = lp_importer::list_required_headers();
        $writer->add_data($headers);

        // Order and number of columns must match lp_importer::list_required_headers().
        $row = array(
            $this->template->get('shortname'),
            $this->template->get('description'),
	    $this->template->get('descriptionformat'),
        );

        $competencies = api::list_competencies_in_template($this->template->get('id'));

	$related = "";
	$framework = "";

	foreach ($competencies as $competency) {
        // Initialize the framework if it's empty.
        if ($framework === "") {
            $framework = $competency->get_framework();
            if (!$framework || !is_object($framework)) {
                // If no valid framework is found, set framework to null and break.
                $framework = null;
                break;
            }
        } else if ($framework->get('id') !== $competency->get('competencyframeworkid')) {
            debugging("multiple frameworks in learning plan", DEBUG_DEVELOPER);
            throw new \Exception(
                "Multiple frameworks in learning plan: " . $framework->get('id') .
                " and " . $competency->get('competencyframeworkidnumber')
            );
        }
    
        // Append competency ID numbers to $related.
        if ($related === "") {
            $related = $competency->get('idnumber');
        } else {
            $related .= "," . $competency->get('idnumber');
        }
    }
    
    // Add framework ID number to the row only if $framework is valid.
    if ($framework && is_object($framework)) {
        $row[] = $framework->get('idnumber');
    } else {
        $row[] = ""; // Leave the framework column empty if no valid framework is found.
    }
    
    $row[] = $related;
    $writer->add_data($row);
    $writer->download_file();    
    }

    /**
     * Export all the learning plans to a csv file.
     */
    public function export() {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');

        $context = context_system::instance();
        $templates = api::list_templates('shortname', 'ASC', null, null, $context);

        $writer = new csv_export_writer();
        $filename = clean_param("Learning_Plan_Templates", PARAM_FILE);
        $writer->set_filename($filename);
        $headers = lp_importer::list_required_headers();
        $writer->add_data($headers);

        foreach ($templates as $template) {
            $this->template = api::read_template($template->get('id'));

            // Order and number of columns must match lp_importer::list_required_headers().
            $row = array(
                $this->template->get('shortname'),
                $this->template->get('description'),
                $this->template->get('descriptionformat'),
            );

            $competencies = api::list_competencies_in_template($this->template->get('id'));

	    $related = "";
	    $framework = "";

            foreach ($competencies as $competency) {
                if ($framework === "") {
                    $framework = $competency->get_framework();
                } else if ($framework->get('id') !== $competency->get('competencyframeworkid')) {
                    debugging("multiple frameworks in learning plan", DEBUG_DEVELOPER);
                    print_error("multiple frameworks in learning plan: " . $framework->get('id') . " and " . $competency->get('competencyframeworkidnumber'));
                    // TODO throw exception
                }
                if ($related === "") {
                    $related = $competency->get('idnumber');
                } else {
                    $related .= "," . $competency->get('idnumber');
                }
            }
            // Add framework ID number to the row only if $framework is valid.
            if ($framework && is_object($framework)) {
                $row[] = $framework->get('idnumber');
            } else {
                $row[] = ""; // Leave the framework column empty if no valid framework is found.
            }
            $row[] = $related;

            $writer->add_data($row);
        }
        $writer->download_file();
    }
}
