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
 * This file contains the form sync a learning plan template.
 *
 * @package   tool_lptmanager
 * @copyright 2015 Damyon Wiese
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This file contains the form sync a learning plan template.
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

class sync extends moodleform {

    /**
     * Define the form - called by parent constructor
     */
    public function definition() {
        $mform = $this->_form;
        $context = context_system::instance();

	// Get templates
	$templates = api::list_templates('shortname', 'ASC', null, null, $context);
        $options = array();
        foreach ($templates as $template) {
            $options[$template->get('id')] = $template->get('shortname');
        }
        if (empty($options)) {
            $mform->addElement('static', 'templateid', '', get_string('notemplates', 'tool_lp'));
        } else {
            $mform->addElement('select', 'templateid', get_string('listtemplatescaption', 'tool_lp'), $options);
            $mform->setType('templateid', PARAM_INT);
            $mform->disabledIf('templateid', 'syncall', 'checked');
	}
        $mform->addElement('advcheckbox', 'syncall', get_string('syncall', 'tool_lptmanager'));
        $mform->setType('syncall', PARAM_BOOL);
        $mform->addHelpButton('syncall', 'syncall', 'tool_lptmanager');

	// Get nice frameworks
	$frameworks = api::list_frameworks('shortname', 'ASC', null, null, $context);
	$options = array(); 
        foreach ($frameworks as $framework) {
            $options[$framework->get('id')] = $framework->get('shortname');
	}
	if (empty($options)) {
            $mform->addElement('static', 'frameworkid', '', get_string('noframeworks', 'tool_lptmanager'));
        } else {
            $mform->addElement('select', 'frameworkid', get_string('listcompetencyframeworkscaption', 'tool_lp'), $options);
            $mform->setType('frameworkid', PARAM_INT);
	    $mform->addRule('frameworkid', null, 'required', null, 'client');
        }

        $this->add_action_buttons(true, get_string('sync', 'tool_lptmanager'));
        $mform->setDisableShortforms();
    }

}
