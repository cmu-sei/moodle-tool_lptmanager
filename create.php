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

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
use core_competency\api;

admin_externalpage_setup('toollpcreate');

$pagetitle = get_string('createnavlink', 'tool_lptmanager');
$context = context_system::instance();
$url = new moodle_url("/admin/tool/lptmanager/create.php");
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title($pagetitle);
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();

if (optional_param('cancel', 0, PARAM_BOOL)) {
    redirect(new moodle_url('/admin/tool/lptmanager/create.php', ['pagecontextid' => $context->id]));
}

if (optional_param('confirm', 0, PARAM_BOOL)) {
    // Step 3: Confirmation form submitted, proceed to create.
    require_sesskey();

    $competencies_json = required_param('competencies', PARAM_RAW);
    $competencies = json_decode($competencies_json, true);
    $frameworkid = required_param('frameworkid', PARAM_INT);

    $creator = new \tool_lptmanager\lp_importer();

    foreach ($competencies as $competencyid) {
        $competency = \core_competency\competency::get_record(['id' => $competencyid]);
        if ($competency) {
            $creator->create_and_link_learning_plan_template($competency, $frameworkid);
        }
    }

    $urlparams = ['pagecontextid' => $context->id];
    $frameworksurl = new moodle_url('/admin/tool/lp/learningplans.php', $urlparams);
    echo $OUTPUT->continue_button($frameworksurl);
    die();

} else {
    $form = new \tool_lptmanager\form\create($url->out(false), ['persistent' => null, 'context' => $context]);
    if ($form->is_cancelled()) {
        redirect(new moodle_url('/admin/tool/lp/learningplans.php', ['pagecontextid' => $context->id]));
    } else if ($data = $form->get_data()) {
        require_sesskey();
        $frameworkid = !empty($data->frameworkid) ? (int)$data->frameworkid : 0;

        // Extract the regex value from the form data
        $regexvalue = $data->regexvalue;

        // Define filters for competency search
        $filters = array();
        if ($frameworkid) {
            $filters['competencyframeworkid'] = $frameworkid;
        }

        // Get the list of competencies based on the regex filter
        $competencies = api::list_competencies($filters);
        $matching_competencies = [];

        // Process the competencies to find matches.
        foreach ($competencies as $competency) {
            $idnumber = $competency->get('idnumber');
            if (str_contains($idnumber, $regexvalue) !== false) {
                $matching_competencies[] = $competency->get('id');
            }
        }

        if (empty($matching_competencies)) {
            echo $OUTPUT->notification(get_string('nocompetenciesfound', 'tool_lptmanager'), 'notifyproblem');
            // Display the form again.
            echo $OUTPUT->heading($pagetitle);
            $form->display();
        } else {
            // Display the confirmation form.
            $confirm_form = new \tool_lptmanager\form\create_confirm(null, ['competencies' => $matching_competencies, 'frameworkid' => $frameworkid]);

            // Output heading for confirmation.
            echo $OUTPUT->heading(get_string('confirm_create_heading', 'tool_lptmanager'));

            $confirm_form->display();
        }
    } else {
        // Step 1: Display the create form.
        echo $OUTPUT->heading($pagetitle);
        $form->display();
    }
}

echo $OUTPUT->footer();
