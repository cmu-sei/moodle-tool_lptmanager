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
 * Import a framework.
 *
 * @package    tool_lptmanager
 * @copyright  2016 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('NO_OUTPUT_BUFFERING', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

admin_externalpage_setup('toollptmanager');

$pagetitle = get_string('pluginname', 'tool_lptmanager');

$context = context_system::instance();

$url = new moodle_url("/admin/tool/lptmanager/index.php");
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title($pagetitle);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($pagetitle);

$form = null;
echo $OUTPUT->header();
if (optional_param('needsconfirm', 0, PARAM_BOOL)) {
    $form = new \tool_lptmanager\form\import($url->out(false));
} else if (optional_param('confirm', 0, PARAM_BOOL)) {
    $importer = new \tool_lptmanager\lp_importer();
    $form = new \tool_lptmanager\form\import_confirm(null, $importer);
} else {
    $form = new \tool_lptmanager\form\import($url->out(false));
}

if ($form->is_cancelled()) {
    $form = new \tool_lptmanager\form\import($url->out(false));
} else if ($data = $form->get_data()) {
    require_sesskey();

    if ($data->confirm) {
        $importid = $data->importid;
        $importer = new \tool_lptmanager\lp_importer(null, null, null, $importid, $data, true);

        $error = $importer->get_error();
        if ($error) {
            $form = new \tool_lptmanager\form\import($url->out(false));
            $form->set_import_error($error);
	} else {
	    $importer->import();

            $urlparams = ['pagecontextid' => $context->id];
            $frameworksurl = new moodle_url('/admin/tool/lp/learningplans.php', $urlparams);
            echo $OUTPUT->notification(get_string('learningplansimported', 'tool_lptmanager'), 'notifysuccess');
            echo $OUTPUT->continue_button($frameworksurl);
            die();
        }
    } else {
        $text = $form->get_file_content('importfile');
        $encoding = $data->encoding;
        $delimiter = $data->delimiter_name;
        $importer = new \tool_lptmanager\lp_importer($text, $encoding, $delimiter, 0, null, true);
        $confirmform = new \tool_lptmanager\form\import_confirm(null, $importer);
        $form = $confirmform;
        $pagetitle = get_string('confirmcolumnmappings', 'tool_lptmanager');
    }
}

echo $OUTPUT->heading($pagetitle);

$form->display();

echo $OUTPUT->footer();
