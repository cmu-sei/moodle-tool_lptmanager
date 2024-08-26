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
 * Page to sync a learning plan template as a CSV.
 *
 * @package    tool_lptmanager
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
use core_competency\api;

admin_externalpage_setup('toollpsync');

$pagetitle = get_string('syncnavlink', 'tool_lptmanager');

$context = context_system::instance();

$url = new moodle_url("/admin/tool/lptmanager/sync.php");
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title($pagetitle);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($pagetitle);

$form = new \tool_lptmanager\form\sync($url->out(false), array('persistent' => null, 'context' => $context));

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/lp/learningplans.php', array('pagecontextid' => $context->id)));
} else if ($data = $form->get_data()) {
    require_sesskey();

    // TODO should just call functions from lp_importer to create and link learning plan templates/competencies
    $syncer = new \tool_lptmanager\lp_importer();
    
        // Extract the regex value from the form data
        $regexvalue = $data->regexvalue;

        // Split the string by dashes
        $parts = explode('-', $regexvalue);

        // Check if the array has the expected parts
        if (isset($parts[1])) {
            $extracted_competency_value = $parts[1];
        } else {
            echo "No match found.";
        }

        // Define filters for competency search
        $filters = array();
    
        // Get the list of competencies based on the regex filter
        $competencies = api::list_competencies($filters);
    
        // Process the competencies as needed
        foreach ($competencies as $competency) {
            $idnumber = $competency->get('idnumber');
            if (strpos($idnumber, $extracted_competency_value) !== false) {
                $syncer->sync_learning_plan_template($competency);
            }
        }
    
        die();
}

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);

$form->display();

echo $OUTPUT->footer();
