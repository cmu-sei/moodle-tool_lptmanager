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

use core\http_client;
use core_competency\api;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

/**
 * Scheduled task that syncs LRS competency statements into Moodle learning plans.
 */
class sync_lrs_competencies extends \core\task\scheduled_task {
    /** @var string TLA MOM asserted verb IRI. */
    const VERB_ASSERTED = 'https://w3id.org/xapi/tla/verbs/asserted';

    /** @var string TLA MOM validated verb IRI. */
    const VERB_VALIDATED = 'https://w3id.org/xapi/tla/verbs/validated';

    /** @var string TLA extension naming the learner of a validated statement. */
    const EXT_LEARNER = 'https://w3id.org/xapi/tla/extensions/learner';

    /** @var int Maximum time to establish a connection to the LRS. */
    const CONNECT_TIMEOUT_SECONDS = 5;

    /** @var int Default maximum total duration of an LRS request. */
    const DEFAULT_REQUEST_TIMEOUT_SECONDS = 15;

    /** @var int Maximum allowed total duration of an LRS request. */
    const MAX_REQUEST_TIMEOUT_SECONDS = 300;

    /** @var int Default maximum number of LRS pages to process per verb. */
    const DEFAULT_MAX_PAGES_PER_VERB = 20;

    /** @var int Maximum allowed number of LRS pages to process per verb. */
    const MAX_PAGES_PER_VERB = 1000;

    /** @var int[]|null Framework IDs the sync may grade, or null before the allowlist is read. */
    private ?array $allowedframeworkids = null;

    /** @var array<string, array<string, string[]>> Per verb, trusted authority keys and the learner homePages each may name. */
    private array $trustedauthorities = [];

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

        if (!$this->get_trusted_authorities(self::VERB_ASSERTED) && !$this->get_trusted_authorities(self::VERB_VALIDATED)) {
            mtrace('No trusted LRS sources are configured; nothing will be synced.');
            return;
        }

        $lastsync = get_config('tool_lptmanager', 'lrs_last_sync');
        $syncstart = $this->get_sync_start_time();

        $asserted = $this->sync_verb($endpoint, $apikey, $apisecret, self::VERB_ASSERTED, $lastsync);
        $validated = $this->sync_verb($endpoint, $apikey, $apisecret, self::VERB_VALIDATED, $lastsync);

        if (!$asserted['complete']) {
            mtrace('LRS assertion sync did not complete.');
        }
        if (!$validated['complete']) {
            mtrace('LRS validation sync did not complete.');
        }
        $checkpoint = $this->get_shared_checkpoint($asserted, $validated, $syncstart);
        if ($checkpoint === null) {
            mtrace('LRS sync did not reach a safe shared checkpoint; retaining the previous sync time.');
            return;
        }

        set_config('lrs_last_sync', $checkpoint, 'tool_lptmanager');
        if (!$asserted['complete'] || !$validated['complete']) {
            mtrace("LRS sync made partial progress through {$checkpoint}. Processed {$asserted['count']} asserted, {$validated['count']} validated statements.");
            return;
        }

        mtrace("LRS sync complete. Processed {$asserted['count']} asserted, {$validated['count']} validated statements.");
    }

    /**
     * Fetch and process competency statements for a given verb.
     *
     * @param string $endpoint LRS xAPI endpoint URL.
     * @param string $apikey LRS API key.
     * @param string $apisecret LRS API secret.
     * @param string $verb The verb IRI to query.
     * @param string|false $since ISO 8601 timestamp to fetch statements since, or false.
     * @return array{count: int, checkpoint: ?string, complete: bool} Sync result.
     */
    protected function sync_verb(string $endpoint, string $apikey, string $apisecret, string $verb, $since): array {
        if (!$this->get_trusted_authorities($verb)) {
            mtrace("No trusted LRS sources are configured for {$verb}; skipping it.");
            return $this->create_sync_result(0, null, true);
        }

        $count = 0;
        $url = $this->build_query_url($endpoint, $verb, $since);
        $visitedurls = [];
        $maxpages = $this->get_max_pages_per_verb();
        $checkpoint = null;

        for ($page = 0; $url; $page++) {
            if ($page >= $maxpages) {
                mtrace("LRS sync reached the {$maxpages}-page limit for {$verb}.");
                return $this->create_sync_result($count, $checkpoint, false);
            }
            if (isset($visitedurls[$url])) {
                mtrace("LRS sync detected a repeated pagination URL for {$verb}.");
                return $this->create_sync_result($count, $checkpoint, false);
            }
            $visitedurls[$url] = true;

            $response = $this->fetch_statements($url, $apikey, $apisecret);
            if ($response === null) {
                return $this->create_sync_result($count, $checkpoint, false);
            }

            $statements = $response->statements ?? null;
            if (!is_array($statements)) {
                mtrace('LRS response statements must be an array.');
                return $this->create_sync_result($count, $checkpoint, false);
            }
            foreach ($statements as $statement) {
                if (!is_object($statement)) {
                    mtrace('LRS statement must be an object.');
                    return $this->create_sync_result($count, $checkpoint, false);
                }
                if ($this->process_statement($statement, $verb)) {
                    $count++;
                }
                $stored = $this->get_statement_stored_time($statement);
                if ($stored === null) {
                    return $this->create_sync_result($count, $checkpoint, false);
                }
                $checkpoint = $stored;
            }

            $more = $response->more ?? null;
            if ($more === null || $more === '') {
                $url = null;
            } else if (!is_string($more)) {
                mtrace('LRS response pagination URL must be a string.');
                return $this->create_sync_result($count, $checkpoint, false);
            } else {
                $url = $this->resolve_more_url($endpoint, $more);
                if ($url === null) {
                    return $this->create_sync_result($count, $checkpoint, false);
                }
            }
        }

        return $this->create_sync_result($count, $checkpoint, true);
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
     * The xAPI spec defines "more" as a relative IRL, so the usual case is a path resolved against
     * the configured endpoint. An absolute URL is honoured only when it addresses that same
     * endpoint: the request carries the LRS key and secret in an Authorization header, so a
     * hostile or compromised LRS that could name any host here would be handed those credentials.
     *
     * @param string $endpoint LRS endpoint base URL.
     * @param string $more The more URL or path from the LRS.
     * @return string|null Full URL, or null if it does not address the configured endpoint.
     */
    private function resolve_more_url(string $endpoint, string $more): ?string {
        $origin = $this->get_origin(rtrim($endpoint, '/'));
        if ($origin === null) {
            mtrace('LRS endpoint is not a valid URL.');
            return null;
        }

        // Anything carrying a scheme is treated as absolute, which also rejects non-HTTP schemes:
        // get_origin() finds no host in them.
        if (preg_match('|^[a-z][a-z0-9+.\-]*:|i', $more)) {
            if ($this->get_origin($more) !== $origin) {
                mtrace("LRS pagination URL does not address the configured endpoint: {$more}");
                return null;
            }
            return $more;
        }

        return $origin . '/' . ltrim($more, '/');
    }

    /**
     * Reduce a URL to a comparable scheme://host[:port] origin.
     *
     * @param string $url URL to reduce.
     * @return string|null Lowercased origin, or null if the URL has no host.
     */
    private function get_origin(string $url): ?string {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $origin = strtolower($parts['scheme'] ?? 'http') . '://' . strtolower($parts['host']);
        if (isset($parts['port'])) {
            $origin .= ':' . (int) $parts['port'];
        }
        return $origin;
    }

    /**
     * Fetch statements from the LRS.
     *
     * @param string $url Full request URL.
     * @param string $apikey LRS API key.
     * @param string $apisecret LRS API secret.
     * @return object|null Decoded JSON response or null on failure.
     */
    protected function fetch_statements(string $url, string $apikey, string $apisecret): ?object {
        try {
            $response = $this->create_client()->get($url, [
                // A scheduled task shares Moodle's cron worker with unrelated work. Do
                // not allow an unavailable LRS to hold it indefinitely.
                RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
                RequestOptions::TIMEOUT => $this->get_request_timeout_seconds(),
                // Report an error status through the same path as every other failure here.
                RequestOptions::HTTP_ERRORS => false,
                RequestOptions::HEADERS => [
                    'Authorization' => 'Basic ' . base64_encode($apikey . ':' . $apisecret),
                    'X-Experience-API-Version' => '1.0.3',
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (GuzzleException $e) {
            mtrace('LRS request failed: ' . $e->getMessage());
            return null;
        }

        $httpcode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($httpcode !== 200) {
            mtrace("LRS request failed with HTTP {$httpcode}: " . substr($body, 0, 500));
            return null;
        }

        $decoded = json_decode($body);
        if (!is_object($decoded)) {
            mtrace('LRS response must be a JSON object.');
            return null;
        }

        return $decoded;
    }

    /**
     * Create the HTTP client used for LRS requests.
     *
     * Core's Guzzle client rather than the older \curl wrapper: the credentials travel in an
     * Authorization header, and this client verifies the peer certificate by default and drops
     * that header on a cross-origin redirect. The \curl wrapper does neither.
     *
     * @return http_client
     */
    protected function create_client(): http_client {
        return new http_client();
    }

    /**
     * Get the timestamp saved after a complete sync.
     *
     * @return string ISO 8601 timestamp in UTC.
     */
    protected function get_sync_start_time(): string {
        return gmdate('c');
    }

    /**
     * Get the safe checkpoint both verb syncs have reached.
     *
     * @param array{count: int, checkpoint: ?string, complete: bool} $asserted Asserted sync result.
     * @param array{count: int, checkpoint: ?string, complete: bool} $validated Validated sync result.
     * @param string $syncstart Timestamp captured before paging.
     * @return string|null Checkpoint, or null if progress cannot safely be recorded.
     */
    private function get_shared_checkpoint(array $asserted, array $validated, string $syncstart): ?string {
        $assertedcheckpoint = $asserted['complete'] ? $syncstart : $asserted['checkpoint'];
        $validatedcheckpoint = $validated['complete'] ? $syncstart : $validated['checkpoint'];
        if ($assertedcheckpoint === null || $validatedcheckpoint === null) {
            return null;
        }
        return strtotime($assertedcheckpoint) <= strtotime($validatedcheckpoint)
            ? $assertedcheckpoint
            : $validatedcheckpoint;
    }

    /**
     * Create a result for a verb synchronization.
     *
     * @param int $count Number of statements processed.
     * @param string|null $checkpoint Last safely processed statement timestamp.
     * @param bool $complete Whether the complete result set was paged.
     * @return array{count: int, checkpoint: ?string, complete: bool} Sync result.
     */
    private function create_sync_result(int $count, ?string $checkpoint, bool $complete): array {
        return [
            'count' => $count,
            'checkpoint' => $checkpoint,
            'complete' => $complete,
        ];
    }

    /**
     * Get a safe checkpoint timestamp from an LRS statement.
     *
     * @param object $statement LRS statement.
     * @return string|null Timestamp rounded down to whole seconds, or null if unavailable.
     */
    private function get_statement_stored_time(object $statement): ?string {
        $stored = $statement->stored ?? null;
        if (!is_string($stored) || $stored === '') {
            mtrace('LRS statement is missing a stored timestamp.');
            return null;
        }
        $timestamp = strtotime($stored);
        if ($timestamp === false) {
            mtrace('LRS statement has an invalid stored timestamp.');
            return null;
        }
        return gmdate('c', $timestamp);
    }

    /**
     * Get the configured LRS request timeout within safe bounds.
     *
     * @return int Timeout in seconds.
     */
    private function get_request_timeout_seconds(): int {
        $timeout = (int) get_config('tool_lptmanager', 'lrs_request_timeout');
        if ($timeout < 1) {
            return self::DEFAULT_REQUEST_TIMEOUT_SECONDS;
        }
        return min($timeout, self::MAX_REQUEST_TIMEOUT_SECONDS);
    }

    /**
     * Get the configured page limit within safe bounds.
     *
     * @return int Maximum pages per verb.
     */
    private function get_max_pages_per_verb(): int {
        $maxpages = (int) get_config('tool_lptmanager', 'lrs_max_pages_per_verb');
        if ($maxpages < 1) {
            return self::DEFAULT_MAX_PAGES_PER_VERB;
        }
        return min($maxpages, self::MAX_PAGES_PER_VERB);
    }

    /**
     * Process a single xAPI competency assertion statement.
     *
     * @param object $statement The xAPI statement object.
     * @param string $verb The verb IRI.
     * @return bool True if the statement was successfully processed.
     */
    protected function process_statement(object $statement, string $verb): bool {
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

        // The LRS stamps the authority from the credential that wrote the statement, so it names
        // the source and cannot be supplied by the client. Anyone holding a write credential can
        // otherwise pick the learner and the competency.
        $learnerhomepages = $this->get_learner_homepages_for_authority($statement->authority ?? null, $verb);
        if ($learnerhomepages === null) {
            $authority = $this->describe_authority($statement->authority ?? null);
            mtrace("Statement {$statementid} was written by {$authority}, not a trusted source for {$verb}, skipping.");
            return false;
        }

        // Resolve the learner. For "validated" statements the actor is the instructor;
        // the learner is in context.extensions. For "asserted" the actor is the learner.
        $extensions = $statement->context->extensions ?? new \stdClass();
        if ($verb === self::VERB_VALIDATED) {
            // Falling back to the actor would grade the instructor who did the validating.
            $learneragent = $extensions->{self::EXT_LEARNER} ?? null;
            if (!is_object($learneragent)) {
                mtrace("Validated statement {$statementid} has no learner extension, skipping.");
                return false;
            }
        } else {
            $learneragent = $statement->actor ?? null;
        }
        $user = $this->resolve_actor($learneragent, $learnerhomepages);
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
            // The allowlist governs the log as well as the grading. When an idnumber exists in both
            // an allowed and a disallowed framework and the statement named no framework, the
            // record found here may be the disallowed one; losing a log row is the safe direction.
            $framework = (int) $competencyrecord->competencyframeworkid;
            if (!in_array($framework, $this->get_allowed_framework_ids(), true)) {
                mtrace("Competency '{$idnumber}' is outside the allowed competency frameworks.");
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
     * Only an account under a homePage the source may name is accepted, and its name is matched
     * to user.idnumber (the Keycloak sub). Without that check a Moodle user id or any other
     * system's account name could collide with someone's idnumber. Email (mbox) is not used: no
     * Crucible app identifies learners by it.
     *
     * @param object|null $actor The xAPI actor object.
     * @param string[] $learnerhomepages Normalised account homePages the source may name learners under.
     * @return object|null The Moodle user record or null.
     */
    private function resolve_actor(?object $actor, array $learnerhomepages): ?object {
        global $DB;

        if ($actor === null) {
            mtrace('Statement has no actor.');
            return null;
        }

        $account = $actor->account ?? null;
        $homepage = is_object($account) ? ($account->homePage ?? null) : null;
        $name = is_object($account) ? ($account->name ?? null) : null;
        if (!is_string($homepage) || !is_string($name) || $name === '') {
            mtrace('Learner has no account identity.');
            return null;
        }

        if (!in_array($this->normalise_url($homepage), $learnerhomepages, true)) {
            mtrace("Learner account homePage '{$homepage}' is not one this source may name learners under.");
            return null;
        }

        $user = $DB->get_record('user', ['idnumber' => $name, 'deleted' => 0]);
        if ($user) {
            return $user;
        }

        mtrace("Could not resolve actor '{$name}' to a Moodle user.");
        return null;
    }

    /**
     * Find the learner homePages a statement's authority may name for a verb.
     *
     * Only an Agent authority with an account is accepted. Group authorities (an OIDC client
     * acting for a user) are refused until a writer needs them: trusting one safely means
     * deciding which member is the client, and no source writes that way today.
     *
     * @param mixed $authority The statement's authority.
     * @param string $verb The verb IRI.
     * @return string[]|null The learner homePages, or null if the authority is not trusted.
     */
    private function get_learner_homepages_for_authority($authority, string $verb): ?array {
        if (!is_object($authority) || ($authority->objectType ?? 'Agent') !== 'Agent') {
            return null;
        }

        $account = $authority->account ?? null;
        $homepage = is_object($account) ? ($account->homePage ?? null) : null;
        $name = is_object($account) ? ($account->name ?? null) : null;
        if (!is_string($homepage) || !is_string($name)) {
            return null;
        }

        return $this->get_trusted_authorities($verb)[$this->get_authority_key($homepage, $name)] ?? null;
    }

    /**
     * Describe a statement authority for the task log, so an admin can copy it into the settings.
     *
     * @param mixed $authority The statement's authority.
     * @return string
     */
    private function describe_authority($authority): string {
        if (!is_object($authority)) {
            return 'no authority';
        }
        if (($authority->objectType ?? 'Agent') === 'Group') {
            return 'a Group authority';
        }
        $account = $authority->account ?? null;
        $homepage = is_object($account) ? ($account->homePage ?? null) : null;
        $name = is_object($account) ? ($account->name ?? null) : null;
        if (!is_string($homepage) || !is_string($name)) {
            return 'an authority without an account';
        }
        return "'{$homepage} {$name}'";
    }

    /**
     * Read the trusted sources configured for a verb.
     *
     * Each line names a credential as the LRS stamps its authority (homePage, then name) and the
     * account homePage of the learners it may grade: "this credential may grade learners
     * identified under this homePage". A credential trusted for several learner homePages takes
     * one line each. An empty list trusts no source.
     *
     * @param string $verb The verb IRI.
     * @return array<string, string[]> Trusted authority keys and their normalised learner homePages.
     */
    private function get_trusted_authorities(string $verb): array {
        if (isset($this->trustedauthorities[$verb])) {
            return $this->trustedauthorities[$verb];
        }

        $settingname = $verb === self::VERB_VALIDATED ? 'lrs_trusted_validators' : 'lrs_trusted_asserters';
        $lines = array_filter(array_map('trim', explode("\n", (string) get_config('tool_lptmanager', $settingname))));

        $trusted = [];
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', $line);
            if (count($parts) !== 3) {
                mtrace("Ignoring trusted source '{$line}' in {$settingname}: expected an authority homePage, "
                    . "an authority name and a learner homePage.");
                continue;
            }
            $trusted[$this->get_authority_key($parts[0], $parts[1])][] = $this->normalise_url($parts[2]);
        }

        $this->trustedauthorities[$verb] = $trusted;
        return $trusted;
    }

    /**
     * Build the lookup key for an authority account.
     *
     * @param string $homepage The account homePage.
     * @param string $name The account name.
     * @return string
     */
    private function get_authority_key(string $homepage, string $name): string {
        return $this->normalise_url($homepage) . ' ' . $name;
    }

    /**
     * Normalise a URL so a trailing slash does not decide a match.
     *
     * @param string $url The URL.
     * @return string
     */
    private function normalise_url(string $url): string {
        return rtrim(trim($url), '/');
    }

    /**
     * Extract a competency idnumber from an xAPI activity object.
     *
     * The identifier has to be asserted deliberately: either through the TLA extension, or as the
     * remainder of an object IRI under the configured competency prefix. Reading the last path
     * segment of any other IRI would let an unrelated activity ending in, say, /T0023 grade
     * competency T0023, and a competency grade is a credential.
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
        $extension = $object->definition->extensions->{'https://w3id.org/xapi/tla/extensions/competency-identifier'}
            ?? null;
        if ($extension !== null && !is_string($extension)) {
            mtrace('Statement competency identifier extension must be a string.');
            return null;
        }
        if ($extension !== null && trim($extension) !== '') {
            $idnumber = trim($extension);
        }

        // Otherwise take the remainder of an object IRI under the configured prefix.
        if ($idnumber === null && !empty($object->id) && is_string($object->id)) {
            $prefix = get_config('tool_lptmanager', 'competency_iri_prefix');
            if (empty($prefix) || strpos($object->id, $prefix) !== 0) {
                mtrace("Statement object '{$object->id}' is not under the configured competency IRI prefix.");
                return null;
            }
            $idnumber = trim(substr($object->id, strlen($prefix)), '/');
        }

        if (empty($idnumber)) {
            mtrace('Could not extract competency identifier from statement object.');
            return null;
        }

        // The remainder has to name one competency, not a path below the prefix.
        if (strpos($idnumber, '/') !== false) {
            mtrace("Competency identifier '{$idnumber}' is not a single path segment.");
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
     * Get the set of framework IDs the sync is allowed to grade.
     *
     * An empty allowlist permits nothing rather than everything. A competency grade is a
     * credential, and a cleared setting is far more likely an accident than a deliberate decision
     * to accept assertions against every framework on the site.
     *
     * @return int[] Allowed framework IDs, empty if no framework may be graded.
     */
    private function get_allowed_framework_ids(): array {
        global $DB;

        if ($this->allowedframeworkids !== null) {
            return $this->allowedframeworkids;
        }

        $setting = get_config('tool_lptmanager', 'lrs_sync_frameworks');
        $iris = array_filter(array_map('trim', explode("\n", (string) $setting)));
        if (!$iris) {
            mtrace('No allowed competency frameworks are configured; nothing will be synced.');
        }

        $ids = [];
        foreach ($iris as $iri) {
            $fw = $DB->get_record('competency_framework', ['idnumber' => $iri]);
            if ($fw) {
                $ids[] = (int) $fw->id;
            } else {
                mtrace("Allowlist framework '{$iri}' not found in Moodle.");
            }
        }

        $this->allowedframeworkids = $ids;
        return $ids;
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
    private function grade_competency_in_plans(
        object $user,
        string $idnumber,
        ?string $frameworkiri,
        string $statementid
    ): array {
        global $DB;

        $result = ['competencyid' => null, 'planid' => null];
        $plans = api::list_user_plans($user->id);

        // Resolve framework ID from the statement's grouping IRI.
        $frameworkid = null;
        if ($frameworkiri !== null) {
            $fw = $DB->get_record('competency_framework', ['idnumber' => $frameworkiri]);
            if ($fw) {
                $frameworkid = (int) $fw->id;
            } else {
                mtrace("Framework with idnumber '{$frameworkiri}' not found, ignoring framework filter.");
            }
        }

        // Build the set of allowed framework IDs from the allowlist setting.
        $allowedframeworkids = $this->get_allowed_framework_ids();

        foreach ($plans as $plan) {
            $plancompetencies = api::list_plan_competencies($plan);
            foreach ($plancompetencies as $pc) {
                if ($pc->competency->get('idnumber') !== $idnumber) {
                    continue;
                }
                $compframeworkid = (int) $pc->competency->get('competencyframeworkid');
                if ($frameworkid !== null && $compframeworkid !== $frameworkid) {
                    continue;
                }
                if (!in_array($compframeworkid, $allowedframeworkids, true)) {
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
    private function grade_competency(
        \core_competency\plan $plan,
        \core_competency\competency $competency,
        string $statementid
    ): void {
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
