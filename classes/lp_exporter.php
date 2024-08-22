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

/**
 * This file contains the exporter for a learning plan template.
 *
 * @package   tool_lptmanager
 * @copyright 2015 Damyon Wiese
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

/**
 * Export Competency framework.
 *
 * @package   tool_lptmanager
 * @copyright 2015 Damyon Wiese
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Export Competency framework.
 *
 * @package   tool_lptmanager
 * @copyright 2024 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class lp_exporter {

    /** @var $error string */
    protected $error = '';

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

	$row[] = $framework->get('idnumber');
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
            $row[] = $framework->get('idnumber');
            $row[] = $related;

            $writer->add_data($row);
        }
        $writer->download_file();
    }
}
