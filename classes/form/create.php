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
 * This file contains the form create a learning plan template.
 *
 * @package   tool_lptmanager
 * @copyright 2024 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lptmanager\form;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

use moodleform;
use context_system;
use core_competency\api;

require_once($CFG->libdir.'/formslib.php');

class create extends moodleform {

    /**
     * Define the form - called by parent constructor
     */
    public function definition() {
        $mform = $this->_form;
        $context = context_system::instance();

        $mform->addElement('html', get_string('createnote','tool_lptmanager'));

	    $frameworks = api::list_frameworks('shortname', 'ASC', null, null, $context);
        $options = array(); 
            foreach ($frameworks as $framework) {
                $options[$framework->get('id')] = $framework->get('shortname');
        }
        if (empty($options)) {
                $mform->addElement('html', '<div class="alert alert-warning">'.get_string('noframeworks_help', 'tool_lptmanager').'</div>');
        } else {
            $mform->addElement('select', 'frameworkid', get_string('listcompetencyframeworkscaption', 'tool_lp'), $options);
            $mform->setType('frameworkid', PARAM_INT);
            $mform->addRule('frameworkid', null, 'required', null, 'client');
            $mform->addElement('text', 'regexvalue', get_string('competencyname', 'tool_lptmanager'));
            $mform->setType('regexvalue', PARAM_RAW); // Not using PARAM_TEXT as it may strip some regex special characters
            $mform->addRule('regexvalue', null, 'required', null, 'client');
            $mform->addHelpButton('regexvalue', 'competencyname', 'tool_lptmanager');
            $this->add_action_buttons(true, get_string('create', 'tool_lptmanager'));
        }
        
        // regex value should checked inside the competency's idnumber field, ie: WRL inside of CE-WRL-001
        $mform->setDisableShortforms();
    }
}
