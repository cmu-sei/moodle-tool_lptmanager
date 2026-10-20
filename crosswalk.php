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
 * Import, review and report on competency framework crosswalks.
 *
 * @package    tool_lptmanager
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_lptmanager\crosswalk\planner;
use tool_lptmanager\crosswalk\store;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('toollpcrosswalk');

$source = optional_param('source', '', PARAM_RAW_TRIMMED);
$target = optional_param('target', '', PARAM_RAW_TRIMMED);
$view = optional_param('view', 'rows', PARAM_ALPHA);
$status = optional_param('status', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 100;

$context = context_system::instance();
$baseurl = new moodle_url('/admin/tool/lptmanager/crosswalk.php');
$pagetitle = get_string('crosswalknavlink', 'tool_lptmanager');
$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_title($pagetitle);
$PAGE->set_pagelayout('admin');

$status = in_array($status, store::STATUSES, true) ? $status : '';
$crosswalkurl = new moodle_url($baseurl, ['source' => $source, 'target' => $target, 'view' => $view]);
if ($status !== '') {
    $crosswalkurl->param('status', $status);
}

// Approve or reject, then come back to the same page of the same list.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '' && $source !== '' && $target !== '') {
    require_sesskey();
    $statuses = ['approve' => store::APPROVED, 'reject' => store::REJECTED];
    if (!isset($statuses[$action])) {
        throw new moodle_exception('invalidparameter', 'debug');
    }
    store::review(required_param('id', PARAM_INT), $statuses[$action], $USER->id);
    redirect(new moodle_url($crosswalkurl, ['page' => $page]));
}

$frameworkname = function (string $idnumber): string {
    $shortname = store::get_framework_shortname($idnumber);
    return $shortname === null
        ? get_string('crosswalkframeworknotinstalled', 'tool_lptmanager', s($idnumber))
        : format_string($shortname);
};

if ($source === '' || $target === '') {
    $form = new \tool_lptmanager\form\crosswalk_import($baseurl->out(false));
    $notice = null;
    if ($data = $form->get_data()) {
        $result = \tool_lptmanager\crosswalk\importer::import($form->get_file_content('crosswalkfile'));
        $notice = $result->errors
            ? $OUTPUT->notification(get_string('crosswalkimportfailed', 'tool_lptmanager') .
                html_writer::alist(array_map('s', $result->errors)), 'error')
            : $OUTPUT->notification(get_string('crosswalkimported', 'tool_lptmanager', $result), 'success');
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading($pagetitle);
    if ($notice) {
        echo $notice;
    }

    $crosswalks = store::get_crosswalks();
    if (!$crosswalks) {
        echo $OUTPUT->notification(get_string('crosswalknone', 'tool_lptmanager'), 'info', false);
    } else {
        $table = new html_table();
        $table->head = [
            get_string('crosswalksource', 'tool_lptmanager'),
            get_string('crosswalktarget', 'tool_lptmanager'),
            get_string('crosswalkmappings', 'tool_lptmanager'),
            get_string('crosswalkstatusapproved', 'tool_lptmanager'),
            get_string('crosswalkstatusproposed', 'tool_lptmanager'),
            get_string('crosswalkstatusrejected', 'tool_lptmanager'),
            '',
        ];
        foreach ($crosswalks as $c) {
            $params = ['source' => $c->sourceframework, 'target' => $c->targetframework];
            $table->data[] = [
                $frameworkname($c->sourceframework),
                $frameworkname($c->targetframework),
                $c->total,
                $c->approved,
                $c->proposed,
                $c->rejected,
                html_writer::link(new moodle_url($baseurl, $params + ['view' => 'rows']),
                    get_string('crosswalkreview', 'tool_lptmanager')) . ' | ' .
                html_writer::link(new moodle_url($baseurl, $params + ['view' => 'report']),
                    get_string('crosswalkreport', 'tool_lptmanager')),
            ];
        }
        echo html_writer::table($table);
    }

    echo $OUTPUT->heading(get_string('crosswalkimport', 'tool_lptmanager'), 3);
    echo html_writer::div(get_string('crosswalkimportnote', 'tool_lptmanager'));
    $form->display();
    echo $OUTPUT->footer();
    die();
}

$a = (object) [
    'source' => $frameworkname($source),
    'target' => $frameworkname($target),
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('crosswalkheading', 'tool_lptmanager', $a));
echo html_writer::div(html_writer::link($baseurl, get_string('crosswalkback', 'tool_lptmanager')), 'mb-3');

// Short names often start with the ID number already, as NICE's do; then the short name says it all.
$competency = function (?string $idnumber, array $names): string {
    if ($idnumber === null) {
        return '';
    }
    $name = $names[$idnumber] ?? null;
    if ($name !== null && str_starts_with($name, $idnumber)) {
        return format_string($name);
    }
    return html_writer::tag('strong', s($idnumber)) . ($name === null ? '' : '<br>' . format_string($name));
};

if ($view === 'report') {
    $report = (new planner($source, $target))->link_report();
    echo html_writer::div(get_string('crosswalkreportnote', 'tool_lptmanager'), 'mb-3');

    $totals = new html_table();
    $totals->head = array_map(fn($o) => get_string('crosswalkoutcome' . $o, 'tool_lptmanager'), planner::OUTCOMES);
    $totals->data[] = array_values($report->totals);
    echo html_writer::table($totals);

    if (!$report->links) {
        echo $OUTPUT->notification(get_string('crosswalknolinks', 'tool_lptmanager'), 'info', false);
    } else {
        $names = store::get_competency_names($source, array_column($report->links, 'idnumber'));
        $table = new html_table();
        $table->head = [
            get_string('crosswalkoutcome', 'tool_lptmanager'),
            get_string('crosswalklinkedfrom', 'tool_lptmanager'),
            get_string('crosswalksourcecompetency', 'tool_lptmanager'),
            get_string('crosswalkreason', 'tool_lptmanager'),
        ];
        foreach ($report->links as $link) {
            $table->data[] = [
                get_string('crosswalkoutcome' . $link->outcome, 'tool_lptmanager'),
                $link->holderurl ? html_writer::link($link->holderurl, $link->holder) : $link->holder,
                $competency($link->idnumber, $names),
                s($link->reason),
            ];
        }
        echo html_writer::table($table);
    }
    echo $OUTPUT->footer();
    die();
}

$filter = ['' => get_string('crosswalkstatusall', 'tool_lptmanager')];
foreach (store::STATUSES as $s) {
    $filter[$s] = get_string('crosswalkstatus' . $s, 'tool_lptmanager');
}
$selecturl = new moodle_url($baseurl, ['source' => $source, 'target' => $target, 'view' => 'rows']);
echo $OUTPUT->single_select($selecturl, 'status', $filter, $status, null, null,
    ['label' => get_string('crosswalkstatus', 'tool_lptmanager')]);

$rowstatus = $status === '' ? null : $status;
$count = store::count_rows($source, $target, $rowstatus);
$rows = store::get_rows($source, $target, $rowstatus, $page * $perpage, $perpage);
$sourcenames = store::get_competency_names($source, array_column($rows, 'sourceidnumber'));
$targetnames = store::get_competency_names($target, array_column($rows, 'targetidnumber'));
$reviewers = $DB->get_records_list('user', 'id', array_filter(array_unique(array_column($rows, 'reviewedby'))), '',
    implode(',', array_merge(['id'], \core_user\fields::get_name_fields())));

$table = new html_table();
$table->head = [
    get_string('crosswalksourcecompetency', 'tool_lptmanager'),
    get_string('crosswalkrelation', 'tool_lptmanager'),
    get_string('crosswalktargetcompetency', 'tool_lptmanager'),
    get_string('crosswalkprovenance', 'tool_lptmanager'),
    get_string('crosswalkconfidence', 'tool_lptmanager'),
    get_string('crosswalkstatus', 'tool_lptmanager'),
    get_string('crosswalknote', 'tool_lptmanager'),
    '',
];
foreach ($rows as $row) {
    $statuscell = get_string('crosswalkstatus' . $row->status, 'tool_lptmanager');
    if ($row->reviewedby && isset($reviewers[$row->reviewedby])) {
        $statuscell .= '<br>' . html_writer::span(get_string('crosswalkreviewedby', 'tool_lptmanager', (object) [
            'user' => fullname($reviewers[$row->reviewedby]),
            'date' => userdate($row->timereviewed, get_string('strftimedatetimeshort', 'langconfig')),
        ]), 'small');
    }
    $actions = '';
    foreach (['approve' => store::APPROVED, 'reject' => store::REJECTED] as $act => $result) {
        if ($row->status !== $result) {
            $actions .= $OUTPUT->single_button(
                new moodle_url($crosswalkurl, ['action' => $act, 'id' => $row->id, 'page' => $page]),
                get_string('crosswalk' . $act, 'tool_lptmanager'),
                'post',
                ['type' => $act === 'approve' ? single_button::BUTTON_PRIMARY : single_button::BUTTON_SECONDARY]
            );
        }
    }
    $table->data[] = [
        $competency($row->sourceidnumber, $sourcenames),
        s($row->relation),
        $competency($row->targetidnumber, $targetnames),
        s($row->provenance ?? ''),
        $row->confidence === null ? '' : format_float($row->confidence, 2),
        $statuscell,
        s($row->note ?? ''),
        $actions,
    ];
}

echo $OUTPUT->paging_bar($count, $page, $perpage, $crosswalkurl);
echo html_writer::table($table);
echo $OUTPUT->paging_bar($count, $page, $perpage, $crosswalkurl);
echo $OUTPUT->footer();
