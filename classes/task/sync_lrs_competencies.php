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
        return $endpoint . '/statements?' . http_build_query($params);
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

        // Extract competency from object.
        $competency = $this->resolve_competency($statement->object ?? null);
        if ($competency === null) {
            return false;
        }

        // Find the user's learning plan containing this competency and grade it.
        $planid = $this->grade_competency_in_plans($user, $competency, $statementid);

        // Extract Crucible extensions if present.
        $exerciseid = $extensions->{'https://crucible.sei.cmu.edu/xapi/ext/exercise-id'} ?? null;
        $runid = $extensions->{'https://crucible.sei.cmu.edu/xapi/ext/run-id'} ?? null;

        // Record the sync.
        $record = new \stdClass();
        $record->statementid = $statementid;
        $record->userid = $user->id;
        $record->competencyid = $competency->get('id');
        $record->planid = $planid;
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
     * Resolve an xAPI activity object to a Moodle competency.
     *
     * Extracts the competency identifier from the object's extensions or IRI and
     * looks it up in Moodle's competency table by idnumber.
     *
     * @param object|null $object The xAPI object.
     * @return \core_competency\competency|null The Moodle competency or null.
     */
    private function resolve_competency(?object $object): ?\core_competency\competency {
        global $DB;

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

        $record = $DB->get_record('competency', ['idnumber' => $idnumber]);
        if (!$record) {
            mtrace("Competency with idnumber '{$idnumber}' not found in Moodle.");
            return null;
        }

        return new \core_competency\competency($record->id);
    }

    /**
     * Find the user's learning plans that contain the given competency and grade it.
     *
     * @param object $user The Moodle user record.
     * @param \core_competency\competency $competency The Moodle competency.
     * @param string $statementid The xAPI statement ID (for evidence note).
     * @return int|null The plan ID that was graded, or null if none found.
     */
    private function grade_competency_in_plans(object $user, \core_competency\competency $competency,
            string $statementid): ?int {
        $plans = api::list_user_plans($user->id);

        foreach ($plans as $plan) {
            $plancompetencies = api::list_plan_competencies($plan);
            foreach ($plancompetencies as $pc) {
                if ($pc->competency->get('id') == $competency->get('id')) {
                    $this->grade_competency($plan, $competency, $statementid);
                    return $plan->get('id');
                }
            }
        }

        mtrace("No learning plan found for user {$user->id} containing competency {$competency->get('idnumber')}.");
        return null;
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
