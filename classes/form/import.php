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
 * This file contains the form for importing a learning plan template from a file.
 *
 * @package   tool_lptmanager
 * @copyright 2024 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lptmanager\form;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

use moodleform;
use core_competency\api;
use core_text;
use csv_import_reader;

require_once($CFG->libdir.'/formslib.php');

class import extends moodleform {

    /**
     * Define the form - called by parent constructor
     */
    public function definition() {
        global $CFG, $DB;
        require_once($CFG->libdir . '/csvlib.class.php');
    
        $mform = $this->_form;
    
        // File picker for import file.
        $element = $mform->createElement('filepicker', 'importfile', get_string('importfile', 'tool_lptmanager'));
        $mform->addElement($element);
        $mform->addHelpButton('importfile', 'importfile', 'tool_lptmanager');
        $mform->addRule('importfile', null, 'required');
        $mform->addElement('hidden', 'confirm', 0);
        $mform->setType('confirm', PARAM_BOOL);
    
        // CSV delimiter and encoding options.
        $choices = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter_name', get_string('csvdelimiter', 'tool_lptmanager'), $choices);
        $mform->setDefault('delimiter_name', array_key_exists('cfg', $choices) ? 'cfg' : 'comma');
    
        $choices = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'tool_lptmanager'), $choices);
        $mform->setDefault('encoding', 'UTF-8');
    
        // Checkbox to decide if importing to a specific category.
        // Add the checkbox element without the description.
        // Add the checkbox element without the description.
        $mform->addElement('advcheckbox', 'usecategory', get_string('usecategory', 'tool_lptmanager'));
        $mform->setType('usecategory', PARAM_BOOL);

        // Add a help button with the description.
        $mform->addHelpButton('usecategory', 'usecategory_desc', 'tool_lptmanager');

        // Dropdown for selecting course category.
        $categories = $DB->get_records_menu('course_categories', null, 'name', 'id, name');
        $mform->addElement('select', 'categoryid', get_string('coursecategory', 'tool_lptmanager'), $categories);
        $mform->setType('categoryid', PARAM_INT);
        $mform->hideIf('categoryid', 'usecategory', 'notchecked');
        $this->add_action_buttons(false, get_string('import', 'tool_lptmanager'));
    }

    /**
     * Display an error on the import form.
     * @param string $msg
     */
    public function set_import_error($msg) {
        $mform = $this->_form;

        $mform->setElementError('importfile', $msg);
    }

}
