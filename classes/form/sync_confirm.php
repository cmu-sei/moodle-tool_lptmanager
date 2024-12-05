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

 defined('MOODLE_INTERNAL') || die();
 
 use moodleform;
 use core_competency\competency;
 
 require_once($CFG->libdir . '/formslib.php');
 
 class sync_confirm extends \moodleform {
     public function definition() {
         $mform = $this->_form;
         $competencies = $this->_customdata['competencies'];
 
         $mform->addElement('hidden', 'confirm', 1);
         $mform->setType('confirm', PARAM_BOOL);
 
         $mform->addElement('hidden', 'competencies', json_encode($competencies));
         $mform->setType('competencies', PARAM_RAW);
 
         $mform->addElement('html', '<div class="alert alert-info">' . get_string('confirm_sync', 'tool_lptmanager') . '</div>');
 
         // Start table.
         $tablehtml = '<table class="generaltable">
                         <thead>
                             <tr>
                                 <th>' . get_string('competencyshortname', 'tool_lptmanager') . '</th>
                                 <th>' . get_string('competencyidnumber', 'tool_lptmanager') . '</th>
                                 <th>' . get_string('competencydescription', 'tool_lptmanager') . '</th>
                             </tr>
                         </thead>
                         <tbody>';
 
         foreach ($competencies as $competencyid) {
             $competency = competency::get_record(['id' => $competencyid]);
             if ($competency) {
                 $shortname = s($competency->get('shortname'));
                 $idnumber = s($competency->get('idnumber'));
                 $description = format_text($competency->get('description'), FORMAT_HTML);
 
                 $tablehtml .= '<tr>
                                 <td>' . $shortname . '</td>
                                 <td>' . $idnumber . '</td>
                                 <td>' . $description . '</td>
                                </tr>';
             }
         }
 
         $tablehtml .= '</tbody></table>';
 
         $mform->addElement('html', $tablehtml);
 
         $this->add_action_buttons(true, get_string('confirm', 'tool_lptmanager'));
     }
 }