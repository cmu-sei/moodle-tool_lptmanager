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
 * Import a framework.
 *
 * @package    tool_lptmanager
 * @copyright  2024 Carnegie Mellon University
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
        $categoryid = $data->categoryid;
        $importid = $data->importid;
        $importer = new \tool_lptmanager\lp_importer(null, null, null, $importid, $data, true, $categoryid);

        $error = $importer->get_error();
        if ($error) {
            $form = new \tool_lptmanager\form\import($url->out(false));
            $form->set_import_error($error);
	    } else {
	        $importer->import();

            $urlparams = ['pagecontextid' => $context->id];
            $frameworksurl = new moodle_url('/admin/tool/lp/learningplans.php', $urlparams);
            echo $OUTPUT->continue_button($frameworksurl);
            die();
        }
    } else {
        $text = $form->get_file_content('importfile');
        $encoding = $data->encoding;
        $delimiter = $data->delimiter_name;
        $categoryid = !empty($data->usecategory) ? $data->categoryid : null;
        $importer = new \tool_lptmanager\lp_importer($text, $encoding, $delimiter, 0, null, true, $categoryid);
        $confirmform = new \tool_lptmanager\form\import_confirm(null, $importer);
        $form = $confirmform;
        $pagetitle = get_string('confirmcolumnmappings', 'tool_lptmanager');
    }
}

echo $OUTPUT->heading($pagetitle);

$form->display();

echo $OUTPUT->footer();
