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
 * This file contains the form to confirm the import options for a framework.
 *
 * @package   tool_lptmanager
 * @copyright 2024 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 namespace tool_lptmanager\form;

defined('MOODLE_INTERNAL') || die();

use moodleform;

require_once($CFG->libdir . '/formslib.php');

class template_confirm extends moodleform {
    public function definition() {
        $importer = $this->_customdata; // Pass the importer object as custom data.
        $mform = $this->_form;

        // Add hidden fields for processing confirmation.
        $mform->addElement('hidden', 'importid', $importer->get_importid());
        $mform->setType('importid', PARAM_INT);
        $mform->addElement('hidden', 'categoryid', $importer->categoryid);
        $mform->setType('categoryid', PARAM_INT);

        $requiredheaders = $importer->list_required_headers();
        $foundheaders = $importer->list_found_headers();
        if (empty($foundheaders)) {
            $foundheaders = range(0, count($requiredheaders));
        }
        $foundheaders[-1] = get_string('none');
    
        // Display the table of learning plan templates.
        if (!empty($importer->framework)) {
            $tablehtml = '<table class="generaltable">';
            $tablehtml .= '<thead><tr><th>' .
                get_string('shortname', 'tool_lptmanager') . '</th><th>' .
                get_string('description', 'tool_lptmanager') . '</th></tr></thead>';
            $tablehtml .= '<tbody>';
            foreach ($importer->framework as $template) {
                $tablehtml .= '<tr><td>' . htmlspecialchars($template->shortname) . '</td>';
                $tablehtml .= '<td>' . htmlspecialchars($template->description) . '</td></tr>';
            }
            $tablehtml .= '</tbody></table>';
            $mform->addElement('html', $tablehtml);
        }


        // Add hidden fields if needed for further processing.
        $mform->addElement('hidden', 'confirmtemplate', 1); // Hidden field for template confirmation.
        $mform->setType('confirmtemplate', PARAM_BOOL);


        // Add final confirmation buttons (Confirm and Cancel).
        $this->add_action_buttons(true, get_string('confirmtemplate', 'tool_lptmanager'));
    }
}
