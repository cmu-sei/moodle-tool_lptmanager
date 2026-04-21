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
Licensed under a GNU GENERAL PUBLIC LICENSE - Version 3, 29 June 2007-style license, please see license.txt or contact permission@sei.cmu.edu for full
terms.

[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution. Please see Copyright notice for non-US Government use and distribution.

This Software includes and/or makes use of Third-Party Software each subject to its own license.

DM24-1177
*/

/**
 * Scheduled task to sync competency assertion statements from SQL LRS into Moodle learning plans.
 *
 * @package    tool_lptmanager
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lptmanager\task;

use core_competency\api;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

class sync_lrs_competencies extends \core\task\scheduled_task {

    /** @var string TLA MOM asserted verb IRI. */
    const VERB_ASSERTED = 'https://w3id.org/xapi/tla/verbs/asserted';

    /** @var string TLA MOM validated verb IRI. */
    const VERB_VALIDATED = 'https://w3id.org/xapi/tla/verbs/validated';

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('synclrscompetencies', 'tool_lptmanager');
    }

    /**
     * Execute the sync task.
     */
    public function execute() {
        $enabled = get_config('tool_lptmanager', 'enable_lrs_sync');
        if (!$enabled) {
            mtrace('LRS competency sync is disabled.');
            return;
        }

        // The competency API requires an authenticated user context.
        $admin = get_admin();
        \core\session\manager::set_user($admin);

        $endpoint = get_config('tool_lptmanager', 'lrs_endpoint');
        $apikey = get_config('tool_lptmanager', 'lrs_api_key');
        $apisecret = get_config('tool_lptmanager', 'lrs_api_secret');

        if (empty($endpoint) || empty($apikey) || empty($apisecret)) {
            mtrace('LRS connection not configured. Skipping sync.');
            return;
        }

        $lastsync = get_config('tool_lptmanager', 'lrs_last_sync');

        $assertedcount = $this->sync_verb($endpoint, $apikey, $apisecret, self::VERB_ASSERTED, $lastsync);
        $validatedcount = $this->sync_verb($endpoint, $apikey, $apisecret, self::VERB_VALIDATED, $lastsync);

        set_config('lrs_last_sync', date('c'), 'tool_lptmanager');
        mtrace("LRS sync complete. Processed {$assertedcount} asserted, {$validatedcount} validated statements.");
    }

    /**
     * Fetch and process competency statements for a given verb.
     *
     * @param string $endpoint LRS xAPI endpoint URL.
     * @param string $apikey LRS API key.
     * @param string $apisecret LRS API secret.
     * @param string $verb The verb IRI to query.
     * @param string|false $since ISO 8601 timestamp to fetch statements since, or false.
     * @return int Number of statements successfully processed.
     */
    private function sync_verb(string $endpoint, string $apikey, string $apisecret, string $verb, $since): int {
        $count = 0;
        $url = $this->build_query_url($endpoint, $verb, $since);

        while ($url) {
            $response = $this->fetch_statements($url, $apikey, $apisecret);
            if ($response === null) {
                break;
            }

            $statements = $response->statements ?? [];
            foreach ($statements as $statement) {
                if ($this->process_statement($statement, $verb)) {
                    $count++;
                }
            }

            $url = !empty($response->more) ? $this->resolve_more_url($endpoint, $response->more) : null;
        }

        return $count;
    }

    /**
     * Build the xAPI statements query URL.
     *
     * @param string $endpoint LRS endpoint base URL.
     * @param string $verb Verb IRI to filter by.
     * @param string|false $since ISO 8601 timestamp or false.
     * @return string
     */
    private function build_query_url(string $endpoint, string $verb, $since): string {
        $endpoint = rtrim($endpoint, '/');
        $params = [
            'verb' => $verb,
            'ascending' => 'true',
            'limit' => 100,
        ];
        if ($since) {
            $params['since'] = $since;
        }
        return $endpoint . '/statements?' . http_build_query($params, '', '&');
    }

    /**
     * Resolve a "more" URL from the LRS response into a full URL.
     *
     * @param string $endpoint LRS endpoint base URL.
     * @param string $more The more URL or path from the LRS.
     * @return string
     */
    private function resolve_more_url(string $endpoint, string $more): string {
        if (strpos($more, 'http') === 0) {
            return $more;
        }
        $parts = parse_url(rtrim($endpoint, '/'));
        return ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? 'localhost')
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . $more;
    }

    /**
     * Fetch statements from the LRS.
     *
     * @param string $url Full request URL.
     * @param string $apikey LRS API key.
     * @param string $apisecret LRS API secret.
     * @return object|null Decoded JSON response or null on failure.
     */
    private function fetch_statements(string $url, string $apikey, string $apisecret): ?object {
        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Basic ' . base64_encode($apikey . ':' . $apisecret),
            'X-Experience-API-Version: 1.0.3',
            'Accept: application/json',
        ]);

        $response = $curl->get($url);
        $httpcode = $curl->get_info()['http_code'] ?? 0;

        if ($httpcode !== 200) {
            mtrace("LRS request failed with HTTP {$httpcode}: " . substr($response, 0, 500));
            return null;
        }

        $decoded = json_decode($response);
        if ($decoded === null) {
            mtrace('Failed to decode LRS response as JSON.');
            return null;
        }

        return $decoded;
    }

    /**
     * Process a single xAPI competency assertion statement.
     *
     * @param object $statement The xAPI statement object.
     * @param string $verb The verb IRI.
     * @return bool True if the statement was successfully processed.
     */
    private function process_statement(object $statement, string $verb): bool {
        global $DB;

        $statementid = $statement->id ?? null;
        if (empty($statementid)) {
            mtrace('Statement missing id, skipping.');
            return false;
        }

        // Check if already synced.
        if ($DB->record_exists('tool_lptmanager_lrs_sync', ['statementid' => $statementid])) {
            return false;
        }

        // Resolve the learner. For "validated" statements the actor is the instructor;
        // the learner is in context.extensions. For "asserted" the actor is the learner.
        $extensions = $statement->context->extensions ?? new \stdClass();
        if ($verb === self::VERB_VALIDATED) {
            $learneragent = $extensions->{'https://w3id.org/xapi/tla/extensions/learner'} ?? null;
            if ($learneragent === null) {
                mtrace('Validated statement missing learner extension, falling back to actor.');
                $learneragent = $statement->actor ?? null;
            }
        } else {
            $learneragent = $statement->actor ?? null;
        }
        $user = $this->resolve_actor($learneragent);
        if ($user === null) {
            return false;
        }

        // Extract competency idnumber from the xAPI object.
        $idnumber = $this->extract_competency_idnumber($statement->object ?? null);
        if ($idnumber === null) {
            return false;
        }

        // Extract the framework IRI from contextActivities.grouping if present.
        $frameworkiri = $this->extract_framework_iri($statement);

        // Find and grade matching competencies across all of the user's learning plans.
        $result = $this->grade_competency_in_plans($user, $idnumber, $frameworkiri, $statementid);

        $competencyid = $result['competencyid'];
        if ($competencyid === null) {
            // Not in any plan — look up a competency record for the sync log.
            $conditions = ['idnumber' => $idnumber];
            if ($frameworkiri !== null) {
                $fw = $DB->get_record('competency_framework', ['idnumber' => $frameworkiri]);
                if ($fw) {
                    $conditions['competencyframeworkid'] = $fw->id;
                }
            }
            $competencyrecord = $DB->get_record('competency', $conditions);
            if (!$competencyrecord) {
                mtrace("Competency with idnumber '{$idnumber}' not found in Moodle.");
                return false;
            }
            $competencyid = (int) $competencyrecord->id;
        }

        // Extract Crucible extensions if present.
        $exerciseid = $extensions->{'https://crucible.sei.cmu.edu/xapi/ext/exercise-id'} ?? null;
        $runid = $extensions->{'https://crucible.sei.cmu.edu/xapi/ext/run-id'} ?? null;

        // Record the sync.
        $record = new \stdClass();
        $record->statementid = $statementid;
        $record->userid = $user->id;
        $record->competencyid = $competencyid;
        $record->planid = $result['planid'];
        $record->verb = $verb;
        $record->exerciseid = $exerciseid;
        $record->runid = $runid;
        $record->timecreated = time();
        $DB->insert_record('tool_lptmanager_lrs_sync', $record);

        return true;
    }

    /**
     * Resolve an xAPI actor to a Moodle user.
     *
     * Looks up by account.name → user.idnumber (Keycloak sub), falling back to mbox → email.
     *
     * @param object|null $actor The xAPI actor object.
     * @return object|null The Moodle user record or null.
     */
    private function resolve_actor(?object $actor): ?object {
        global $DB;

        if ($actor === null) {
            mtrace('Statement has no actor.');
            return null;
        }

        // Try account.name → user.idnumber (Keycloak sub claim).
        if (!empty($actor->account->name)) {
            $user = $DB->get_record('user', ['idnumber' => $actor->account->name, 'deleted' => 0]);
            if ($user) {
                return $user;
            }
        }

        // Fallback: mbox → email.
        if (!empty($actor->mbox)) {
            $email = str_replace('mailto:', '', $actor->mbox);
            $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
            if ($user) {
                return $user;
            }
        }

        $actorname = $actor->account->name ?? $actor->mbox ?? 'unknown';
        mtrace("Could not resolve actor '{$actorname}' to a Moodle user.");
        return null;
    }

    /**
     * Extract a competency idnumber from an xAPI activity object.
     *
     * @param object|null $object The xAPI object.
     * @return string|null The competency idnumber or null.
     */
    private function extract_competency_idnumber(?object $object): ?string {
        if ($object === null) {
            mtrace('Statement has no object.');
            return null;
        }

        $idnumber = null;

        // Try the TLA extension first.
        if (!empty($object->definition->extensions->{'https://w3id.org/xapi/tla/extensions/competency-identifier'})) {
            $idnumber = $object->definition->extensions->{'https://w3id.org/xapi/tla/extensions/competency-identifier'};
        }

        // Fallback: extract from the IRI path (e.g., .../ksat/T0023).
        if ($idnumber === null && !empty($object->id)) {
            $prefix = get_config('tool_lptmanager', 'competency_iri_prefix');
            if ($prefix && strpos($object->id, $prefix) === 0) {
                $idnumber = substr($object->id, strlen($prefix));
            } else {
                $idnumber = basename(parse_url($object->id, PHP_URL_PATH));
            }
        }

        if (empty($idnumber)) {
            mtrace('Could not extract competency identifier from statement object.');
            return null;
        }

        return $idnumber;
    }

    /**
     * Extract the competency framework IRI from contextActivities.grouping.
     *
     * @param object $statement The xAPI statement.
     * @return string|null The framework IRI or null if not present.
     */
    private function extract_framework_iri(object $statement): ?string {
        $grouping = $statement->context->contextActivities->grouping ?? [];
        foreach ($grouping as $activity) {
            $type = $activity->definition->type ?? '';
            if ($type === 'https://w3id.org/xapi/tla/activity-types/competency-framework') {
                return $activity->id ?? null;
            }
        }
        return null;
    }

    /**
     * Find and grade a competency by idnumber across all of the user's learning plans.
     *
     * Matches by idnumber rather than competency ID so the correct framework-specific
     * competency is used when the same idnumber exists in multiple frameworks.
     * When a framework IRI is provided, only competencies from that framework are matched.
     *
     * @param object $user The Moodle user record.
     * @param string $idnumber The competency idnumber from the xAPI statement.
     * @param string|null $frameworkiri The framework IRI from contextActivities.grouping, or null.
     * @param string $statementid The xAPI statement ID (for evidence note).
     * @return array{competencyid: int|null, planid: int|null} The first matched competency and plan IDs.
     */
    private function grade_competency_in_plans(object $user, string $idnumber,
            ?string $frameworkiri, string $statementid): array {
        global $DB;

        $result = ['competencyid' => null, 'planid' => null];
        $plans = api::list_user_plans($user->id);

        // Resolve framework ID from the IRI for filtering.
        $frameworkid = null;
        if ($frameworkiri !== null) {
            $fw = $DB->get_record('competency_framework', ['idnumber' => $frameworkiri]);
            if ($fw) {
                $frameworkid = (int) $fw->id;
            } else {
                mtrace("Framework with idnumber '{$frameworkiri}' not found, ignoring framework filter.");
            }
        }

        foreach ($plans as $plan) {
            $plancompetencies = api::list_plan_competencies($plan);
            foreach ($plancompetencies as $pc) {
                if ($pc->competency->get('idnumber') !== $idnumber) {
                    continue;
                }
                if ($frameworkid !== null && $pc->competency->get('competencyframeworkid') != $frameworkid) {
                    continue;
                }
                $competency = $pc->competency;
                $this->grade_competency($plan, $competency, $statementid);
                if ($result['competencyid'] === null) {
                    $result['competencyid'] = $competency->get('id');
                    $result['planid'] = $plan->get('id');
                }
            }
        }

        if ($result['competencyid'] === null) {
            mtrace("No learning plan found for user {$user->id} containing competency {$idnumber}.");
        }

        return $result;
    }

    /**
     * Grade a competency in a learning plan.
     *
     * Resolves the proficient grade value from the competency's scale configuration.
     *
     * @param \core_competency\plan $plan The learning plan.
     * @param \core_competency\competency $competency The competency to grade.
     * @param string $statementid The xAPI statement ID for the evidence note.
     */
    private function grade_competency(\core_competency\plan $plan,
            \core_competency\competency $competency, string $statementid): void {
        $grade = $this->get_proficient_grade($competency);
        if ($grade === null) {
            mtrace("Could not determine proficient grade for competency {$competency->get('idnumber')}.");
            return;
        }

        $note = get_string('syncevidencenote', 'tool_lptmanager', $statementid);

        try {
            api::grade_competency_in_plan($plan->get('id'), $competency->get('id'), $grade, $note);
            mtrace("Graded competency {$competency->get('idnumber')} in plan {$plan->get('id')} for user.");
        } catch (\Exception $e) {
            mtrace("Failed to grade competency {$competency->get('idnumber')}: {$e->getMessage()}");
        }
    }

    /**
     * Get the grade value that represents proficiency for a competency.
     *
     * Reads the scale configuration from the competency or its framework to find
     * which scale value is marked as proficient.
     *
     * @param \core_competency\competency $competency The competency.
     * @return int|null The proficient grade value (1-based scale index), or null.
     */
    private function get_proficient_grade(\core_competency\competency $competency): ?int {
        $scaleconfig = $competency->get('scaleconfiguration');
        if (empty($scaleconfig)) {
            $framework = new \core_competency\competency_framework($competency->get('competencyframeworkid'));
            $scaleconfig = $framework->get('scaleconfiguration');
        }

        if (empty($scaleconfig)) {
            return null;
        }

        $config = json_decode($scaleconfig);
        if (!is_array($config)) {
            return null;
        }

        // Find the highest scale value marked as proficient.
        $proficientgrade = null;
        foreach ($config as $item) {
            if (!empty($item->proficient)) {
                $proficientgrade = $item->id ?? $proficientgrade;
            }
        }

        return $proficientgrade;
    }
}
