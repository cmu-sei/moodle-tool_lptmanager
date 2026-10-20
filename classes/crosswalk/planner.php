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
 * What moving links through a crosswalk would do.
 *
 * @package    tool_lptmanager
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lptmanager\crosswalk;

/**
 * Classifies the links to a source framework's competencies by what a crosswalk says about them.
 *
 * Nothing is changed. Each competency gets one outcome:
 *  - auto: one approved exactMatch or closeMatch to an installed target competency; the link can move.
 *  - review: one proposed mapping; a person approves or rejects it first.
 *  - manual: deprecated, rejected, broader, narrower or related, more than one target, or a target
 *    that is not installed; a person picks the target, or drops or keeps the link.
 *  - unmapped: the crosswalk has no row for it.
 *
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class planner {
    /** @var string The link can be moved without a person. */
    public const AUTO = 'auto';
    /** @var string The mapping needs approving first. */
    public const REVIEW = 'review';
    /** @var string A person decides. */
    public const MANUAL = 'manual';
    /** @var string The crosswalk says nothing. */
    public const UNMAPPED = 'unmapped';
    /** @var string[] Every outcome, in report order. */
    public const OUTCOMES = [self::AUTO, self::REVIEW, self::MANUAL, self::UNMAPPED];

    /** @var string[] Relations close enough to move a link without choosing a target by hand. */
    private const MOVABLE = ['exactMatch', 'closeMatch'];

    /** @var string Source framework ID number. */
    private string $source;
    /** @var string Target framework ID number. */
    private string $target;
    /** @var \stdClass[][] Crosswalk rows by source competency ID number. */
    private array $rows = [];
    /** @var int[] Target competency ids by ID number; empty when the target framework is not installed. */
    private array $targetids = [];

    /**
     * Load a crosswalk.
     *
     * @param string $source Source framework ID number.
     * @param string $target Target framework ID number.
     */
    public function __construct(string $source, string $target) {
        global $DB;

        $this->source = $source;
        $this->target = $target;
        foreach (store::get_rows($source, $target) as $row) {
            $this->rows[$row->sourceidnumber][] = $row;
        }
        $targetid = $DB->get_field('competency_framework', 'id', ['idnumber' => $target]);
        if ($targetid) {
            $this->targetids = array_map('intval', array_flip(
                $DB->get_records_menu('competency', ['competencyframeworkid' => $targetid], '', 'id, idnumber')
            ));
        }
    }

    /**
     * What the crosswalk says about one source competency.
     *
     * @param string $idnumber Source competency ID number.
     * @return \stdClass outcome, targetid (the competency the link would move to, if one is known),
     *     row (the mapping it rests on, if exactly one) and reason (for a person).
     */
    public function classify(string $idnumber): \stdClass {
        $rows = $this->rows[$idnumber] ?? [];
        $live = array_values(array_filter($rows, fn($r) => $r->status !== store::REJECTED));
        $result = fn($outcome, $reason, $row = null, $targetid = null) =>
            (object) ['outcome' => $outcome, 'targetid' => $targetid, 'row' => $row, 'reason' => $reason];

        if (!$rows) {
            return $result(self::UNMAPPED, get_string('crosswalkreasonunmapped', 'tool_lptmanager'));
        }
        if (!$live) {
            return $result(self::MANUAL, get_string('crosswalkreasonrejected', 'tool_lptmanager'));
        }
        if (count($live) > 1) {
            $targets = implode(', ', array_map(fn($r) => $r->targetidnumber ?? $r->relation, $live));
            return $result(self::MANUAL, get_string('crosswalkreasonseveral', 'tool_lptmanager', $targets));
        }

        $row = $live[0];
        if ($row->relation === 'deprecated') {
            return $result(self::MANUAL, get_string('crosswalkreasondeprecated', 'tool_lptmanager'), $row);
        }
        $a = (object) ['relation' => $row->relation, 'target' => $row->targetidnumber];
        $targetid = $this->targetids[$row->targetidnumber] ?? null;
        if ($targetid === null) {
            return $result(self::MANUAL, get_string('crosswalkreasonnotarget', 'tool_lptmanager', $a), $row);
        }
        if ($row->status !== store::APPROVED) {
            return $result(self::REVIEW, get_string('crosswalkreasonproposed', 'tool_lptmanager', $a), $row, $targetid);
        }
        if (!in_array($row->relation, self::MOVABLE, true)) {
            return $result(self::MANUAL, get_string('crosswalkreasonloose', 'tool_lptmanager', $a), $row, $targetid);
        }

        return $result(self::AUTO, get_string('crosswalkreasonapproved', 'tool_lptmanager', $a), $row, $targetid);
    }

    /**
     * Every link on this site to a source framework competency, with its outcome.
     *
     * Covers course, activity, learning plan template and learning plan links, and question bank
     * links when qbank_competency is installed. Plans based on a template take their competencies
     * from the template, so they appear through it.
     *
     * @return \stdClass totals (outcome to count) and links: holder, holderurl (or null),
     *     idnumber, outcome and reason, ordered by outcome then holder.
     */
    public function link_report(): \stdClass {
        global $DB;

        $report = (object) ['totals' => array_fill_keys(self::OUTCOMES, 0), 'links' => []];
        $sourceid = $DB->get_field('competency_framework', 'id', ['idnumber' => $this->source]);
        if (!$sourceid) {
            return $report;
        }

        $outcomes = [];
        foreach ($this->holders((int) $sourceid) as $link) {
            $outcomes[$link->idnumber] ??= $this->classify($link->idnumber);
            $link->outcome = $outcomes[$link->idnumber]->outcome;
            $link->reason = $outcomes[$link->idnumber]->reason;
            $report->totals[$link->outcome]++;
            $report->links[] = $link;
        }

        $order = array_flip(self::OUTCOMES);
        usort($report->links, fn($a, $b) =>
            [$order[$a->outcome], $a->holder, $a->idnumber] <=> [$order[$b->outcome], $b->holder, $b->idnumber]);

        return $report;
    }

    /**
     * The links to one framework's competencies, and what holds each one.
     *
     * @param int $frameworkid
     * @return \stdClass[] holder, holderurl and idnumber.
     */
    private function holders(int $frameworkid): array {
        global $DB;

        $links = [];
        $params = ['frameworkid' => $frameworkid];
        $join = "JOIN {competency} comp ON comp.id = l.competencyid AND comp.competencyframeworkid = :frameworkid";

        $sql = "SELECT l.id, comp.idnumber, c.id AS courseid, c.shortname
                  FROM {competency_coursecomp} l {$join}
                  JOIN {course} c ON c.id = l.courseid";
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $links[] = $this->link(get_string('crosswalkholdercourse', 'tool_lptmanager', format_string($r->shortname)),
                new \moodle_url('/admin/tool/lp/coursecompetencies.php', ['courseid' => $r->courseid]), $r->idnumber);
        }

        $sql = "SELECT l.id, comp.idnumber, l.cmid, cm.course
                  FROM {competency_modulecomp} l {$join}
                  JOIN {course_modules} cm ON cm.id = l.cmid";
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $cm = get_fast_modinfo($r->course)->get_cm($r->cmid);
            $a = (object) ['activity' => $cm->get_formatted_name(), 'course' => format_string($cm->get_course()->shortname)];
            $links[] = $this->link(get_string('crosswalkholderactivity', 'tool_lptmanager', $a), $cm->url, $r->idnumber);
        }

        $sql = "SELECT l.id, comp.idnumber, t.id AS templateid, t.shortname, t.contextid
                  FROM {competency_templatecomp} l {$join}
                  JOIN {competency_template} t ON t.id = l.templateid";
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $links[] = $this->link(get_string('crosswalkholdertemplate', 'tool_lptmanager', format_string($r->shortname)),
                new \moodle_url('/admin/tool/lp/templatecompetencies.php',
                    ['templateid' => $r->templateid, 'pagecontextid' => $r->contextid]), $r->idnumber);
        }

        $sql = "SELECT l.id, comp.idnumber, p.id AS planid, p.name
                  FROM {competency_plancomp} l {$join}
                  JOIN {competency_plan} p ON p.id = l.planid";
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $links[] = $this->link(get_string('crosswalkholderplan', 'tool_lptmanager', format_string($r->name)),
                new \moodle_url('/admin/tool/lp/plan.php', ['id' => $r->planid]), $r->idnumber);
        }

        if ($DB->get_manager()->table_exists('qbank_competency_question')) {
            $sql = "SELECT l.id, comp.idnumber, l.questionbankentryid
                      FROM {qbank_competency_question} l {$join}";
            foreach ($DB->get_records_sql($sql, $params) as $r) {
                $links[] = $this->link(get_string('crosswalkholderquestion', 'tool_lptmanager', $r->questionbankentryid),
                    null, $r->idnumber);
            }
        }

        return $links;
    }

    /**
     * One link.
     *
     * @param string $holder What holds the link, for a person.
     * @param \moodle_url|null $url Where to see it.
     * @param string $idnumber The source competency.
     * @return \stdClass
     */
    private function link(string $holder, ?\moodle_url $url, string $idnumber): \stdClass {
        return (object) ['holder' => $holder, 'holderurl' => $url, 'idnumber' => $idnumber];
    }
}
