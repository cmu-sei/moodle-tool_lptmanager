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
 * Page to export a learning plan template as a CSV.
 *
 * @package    tool_lptmanager
 * @copyright  2016 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

admin_externalpage_setup('toollpexport');

$pagetitle = get_string('exportnavlink', 'tool_lptmanager');

$context = context_system::instance();

$url = new moodle_url("/admin/tool/lptmanager/export.php");
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title($pagetitle);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($pagetitle);

$form = new \tool_lptmanager\form\export($url->out(false), array('persistent' => null, 'context' => $context));

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/lp/learningplans.php', array('pagecontextid' => $context->id)));
} else if ($data = $form->get_data()) {
    require_sesskey();

    $exporter = new \tool_lptmanager\lp_exporter();

    if ($data->exportall) {
        $exporter->export();
    } else {
        $exporter->export_one($data->templateid);
    }

    die();
}

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);

$form->display();

echo $OUTPUT->footer();
