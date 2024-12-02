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
 * A scheduled task for learning plan template manager cron.
 *
 * @package    tool_lptmanager
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lptmanager\task;

use core_competency\api;
use core_competency\external;

defined('MOODLE_INTERNAL') || die();

class cron_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('crontask', 'tool_lptmanager');
    }

    /**
     * Run competencies cron.
     */
    public function execute() {
        global $CFG, $DB;

        $now = time();

        mtrace(' processing competencies subplugins ...');


        // TODO get competencies from the framework
        // check for and add learning plan templates for each competency with "WRL"
        // add cross referenced competecnies to the learning plan
        // Get a list of competencies.
        global $PAGE;
        $context = $PAGE->context;
        $frameworks = api::list_frameworks('shortname', 'ASC', null, null, $context);
        $workroles = array();

        echo "found " . count($frameworks) . " frameworks\n"; 

        foreach ($frameworks as $framework) {

                echo "checking framework id " . $framework->get('id') . "\n";

                $frameworkid = $framework->get('id');
                $comps = api::list_competencies(array('competencyframeworkid' => $frameworkid), 'shortname', 'ASC', null, null);

                echo "found " . count($comps) . " competencies in framework $frameworkid\n";

                foreach ($comps as $comp) {
                        if (str_contains($comp->get('idnumber'), "WRL")) {
                                array_push($workroles, $comp);
                        }
                }
        }
        echo "found " . count($workroles) . " work roles as competencies\n";

        $templates = api::list_templates('shortname', 'ASC', null, null, $context);
        echo "found " . count($templates) . " learning plan templates\n";

        // TODO for each work role, check for a learning plan
        foreach ($workroles as $workrole) {
            echo "searching for " . $workrole->get('shortname') . "\n";
            $found = 0;
            foreach ($templates as $template) {
                if ($workrole->get('shortname') === $template->get('shortname')) {
                    echo "we got a matching learning plan template for " . $workrole->get('shortname') . "\n";

                    $found = 1;

                    // TODO get work role tsks
                    $relateds = api::list_related_competencies($workrole->get('id'));
                    echo "found " . count($relateds) . " related comps for workrole\n";

                    $tsks = array();
                    $tsks = api::list_competencies_in_template($template->get('id'));
                    echo "there are " . count($tsks) . " competencies mapped to this work role's learning plan template\n";

                    // TODO map them
                    foreach ($relateds as $related) {
                        // TODO make sure competency is not already there
                        echo "attempting to add " . $related ->get('id') . " to " . $template->get('id') . "\n";
                        api::add_competency_to_template($template->get('id'), $related->get('id'));
                    }

                }
            }
            if (!$found) {
                // add template
                $record = new \stdClass();
                $record->shortname = $workrole->get('shortname');
                $record->description = $workrole->get('description');
                //api::create_template($record);
                $template = array();
                $template['shortname'] = $workrole->get('shortname');
                $template['description'] = $workrole->get('description');
                $template['contextid'] = 1;
                external::create_template($template);
            }
        }
        mtrace('done');
    }
}
