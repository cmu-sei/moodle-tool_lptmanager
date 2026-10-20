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
 * Strings for component 'tool_lptmanager', language 'en'
 *
 * @package    tool_lptmanager
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['confirmcolumnmappings'] = 'Confirm the column mappings';
$string['confirm'] = 'Confirm';
$string['csvdelimiter'] = 'CSV separator';
$string['description'] = 'Description';
$string['descriptionformat'] = 'Description format';
$string['competencyframeworkid'] = 'Competency Framework ID';
$string['competencyframeworkidnumber'] = 'Competency Framework ID Number';
$string['create'] = 'Create';
$string['createall'] = 'Create All Learning Plan Templates';
$string['createall_help'] = 'Create All Learning Plan Templates instead of just one';
$string['encoding'] = 'Encoding';
$string['export'] = 'Export';
$string['exportall'] = 'Export All Learning Plans';
$string['exportall_help'] = 'Export All Learning Plans instead of just one';
$string['exportnavlink'] = 'Export learning plan templates';
$string['multipleframeworks'] = 'Learning plan template \'{$a->template}\' cannot be exported: its competencies belong to more than one competency framework (\'{$a->first}\' and \'{$a->second}\'). An exported template can only reference one framework.';
$string['createnavlink'] = 'Create learning plan templates from competency framework';
$string['id'] = 'ID';
$string['idnumber'] = 'ID number';
$string['importfile'] = 'CSV learning plan template description file';
$string['importfile_help'] = 'A learning plan template may be imported via text file. The format of the file can be determined by creating a new learning plan template on the site and then exporting it.';
$string['importfile_link'] = 'admin/tool/lptmanager';
$string['import'] = 'Import';
$string['importingfile'] = 'Importing file data';
$string['invalidimportfile'] = 'File format is invalid.';
$string['notemplates'] = 'No learning plan templates have been created yet';
$string['parentidnumber'] = 'Parent ID number';
$string['pluginname'] = 'Learning Plan Template Manager';
$string['processingfile'] = 'Processing file';
$string['relatedidnumbers'] = 'Cross-referenced competency ID numbers';
$string['shortname'] = 'Short name';
$string['learningplansimported'] = 'Learning plan template imported successfully.';
$string['templatecreated'] = 'Learning plan template \'{$a}\' created successfully.';
$string['templateexists'] = 'A template with the shortname \'{$a}\' already exists.';
$string['frameworknotfound'] = 'Competency framework \'{$a->framework}\' not found. Learning plan template \'{$a->template}\' was created without its cross-referenced competencies.';
$string['privacy:metadata:lrs_sync'] = 'A log of the xAPI competency statements the LRS sync task has applied to a user\'s learning plans, kept so that no statement is applied twice.';
$string['privacy:metadata:lrs_sync:competencyid'] = 'The competency the statement rated.';
$string['privacy:metadata:lrs_sync:exerciseid'] = 'The Crucible exercise the statement came from.';
$string['privacy:metadata:lrs_sync:planid'] = 'The learning plan the competency was rated in.';
$string['privacy:metadata:lrs_sync:runid'] = 'The Crucible exercise run the statement came from.';
$string['privacy:metadata:lrs_sync:statementid'] = 'The ID of the xAPI statement in the LRS.';
$string['privacy:metadata:lrs_sync:timecreated'] = 'The time the statement was applied.';
$string['privacy:metadata:lrs_sync:userid'] = 'The user the statement was about.';
$string['privacy:metadata:lrs_sync:verb'] = 'The xAPI verb of the statement (asserted or validated).';
$string['privacy:path:lrssync'] = 'LRS competency sync';
$string['noframeworks'] = 'No Competency Frameworks Found';
$string['noframeworks_help'] = 'No competency frameworks are currently available. Please check your competency frameworks settings or add the necessary frameworks to proceed with the create process.';
$string['competencyname'] = 'Competency Name';
$string['competencyname_help'] = 'Enter text to search for in competency ID numbers. Every competency whose ID number contains this text is matched, for example WRL matches CE-WRL-001. The text is matched literally, not as a regular expression.';
$string['usecategory'] = 'Use specific category';
$string['usecategory_desc'] = 'Use specific category';
$string['usecategory_desc_help'] = 'Select this option to import the learning plan into a specified course category. If no course category is selected, the default System category will be used.';
$string['coursecategory'] = 'Select course category';
$string['importnote'] = '<strong>Instructions:</strong> Before importing learning plan templates into Moodle, ensure that any associated competency frameworks have already been imported.';
$string['exportnote'] = '<strong>Instructions:</strong> After exporting the learning plan templates, you may also wish to export any competency frameworks used by these learning plan templates';
$string['confirm_create'] = 'Please confirm the creation of the following competencies:';
$string['confirm_create_heading'] = 'Learning Plan Templates to be Created';
$string['nocompetenciesfound'] = 'No competencies were found matching your criteria. Please verify your search text or framework selection and try again.';
$string['competencyshortname'] = 'Competency Shortname';
$string['competencyidnumber'] = 'Competency ID';
$string['competencydescription'] = 'Description';
$string['createnote'] = '<strong>Instructions:</strong> Enter text to match against competency ID numbers. For each competency whose ID number contains the text, the tool will generate a learning plan template and it will add the cross-referenced competencies to the learning plan template.';
$string['synclrscompetencies'] = 'Sync competency assertions from SQL LRS';
$string['syncevidencenote'] = 'Demonstrated in Crucible exercise. xAPI statement: {$a}';
$string['lrssettings'] = 'LRS Competency Sync';
$string['lrssettingsheading'] = 'SQL LRS Connection';
$string['lrssettingsdesc'] = 'Configure the connection to SQL LRS for syncing competency assertion statements into Moodle learning plans.';
$string['enablersksyncsetting'] = 'Enable LRS sync';
$string['enablersksyncsetting_desc'] = 'When enabled, a scheduled task will periodically query the SQL LRS for competency assertion statements and update learner plans.';
$string['lrsendpoint'] = 'LRS endpoint';
$string['lrsendpoint_desc'] = 'The xAPI statements endpoint URL for SQL LRS (e.g., http://lrsql:9274/xapi).';
$string['lrsrequesttimeout'] = 'LRS request timeout';
$string['lrsrequesttimeout_desc'] = 'Maximum total time in seconds for each LRS request (1-300). The default is 15 seconds.';
$string['lrsmaxpagesperverb'] = 'LRS page limit per verb';
$string['lrsmaxpagesperverb_desc'] = 'Maximum number of statement pages to process for each verb in one task run (1-1000). The default is 20.';
$string['lrsapikey'] = 'LRS API key';
$string['lrsapikey_desc'] = 'API key for authenticating with the SQL LRS xAPI endpoint.';
$string['lrsapisecret'] = 'LRS API secret';
$string['lrsapisecret_desc'] = 'API secret for authenticating with the SQL LRS xAPI endpoint.';
$string['competencyiriprefix'] = 'Competency IRI prefix';
$string['competencyiriprefix_desc'] = 'Prefix to strip from xAPI competency object IRIs to extract the idnumber (e.g., https://niccs.cisa.gov/workforce-development/nice-framework/ksat/). The remainder after stripping is matched against Moodle competency idnumber fields. Statements whose object IRI does not start with this prefix are skipped unless they carry a TLA competency-identifier extension.';
$string['lrssyncframeworks'] = 'Allowed competency frameworks';
$string['lrssyncframeworks_desc'] = 'Restrict which competency frameworks the LRS sync is allowed to grade. Enter one framework idnumber (IRI) per line. Only competencies belonging to these frameworks will be graded. Leave empty to sync nothing.';
$string['crosswalkapprove'] = 'Approve';
$string['crosswalkback'] = 'Back to all crosswalks';
$string['crosswalkbadconfidence'] = 'confidence must be a number from 0 to 1, not \'{$a}\'.';
$string['crosswalkbadrelation'] = 'relation must be exactMatch, closeMatch, broadMatch, narrowMatch, relatedMatch or deprecated, not \'{$a}\'.';
$string['crosswalkbadstatus'] = 'status must be proposed, approved or rejected, not \'{$a}\'.';
$string['crosswalkconfidence'] = 'Confidence';
$string['crosswalkdeprecatedtarget'] = 'A deprecated row has no target_idnumber.';
$string['crosswalkduplicaterow'] = 'The same mapping is on line {$a}.';
$string['crosswalkempty'] = 'The file has a header row but no mappings.';
$string['crosswalkemptycell'] = '{$a} is empty.';
$string['crosswalkfile'] = 'Crosswalk CSV file';
$string['crosswalkfile_help'] = 'A CSV file with a header row naming the columns source_framework, source_idnumber, target_framework, target_idnumber, relation, provenance, confidence, status and note. Frameworks are given by their ID number and competencies by theirs. The relation is a SKOS mapping relation (exactMatch, closeMatch, broadMatch, narrowMatch or relatedMatch) or deprecated, which has no target. The status is proposed, approved or rejected; an empty status is proposed.';
$string['crosswalkframeworknotinstalled'] = '{$a} (not installed)';
$string['crosswalkheading'] = 'Crosswalk: {$a->source} to {$a->target}';
$string['crosswalkholderactivity'] = 'Activity: {$a->activity} ({$a->course})';
$string['crosswalkholdercourse'] = 'Course: {$a}';
$string['crosswalkholderplan'] = 'Learning plan: {$a}';
$string['crosswalkholderquestion'] = 'Question bank entry {$a}';
$string['crosswalkholdertemplate'] = 'Learning plan template: {$a}';
$string['crosswalkimport'] = 'Import a crosswalk';
$string['crosswalkimported'] = 'Crosswalk imported: {$a->added} added, {$a->updated} updated, {$a->unchanged} unchanged, {$a->kept} kept because they were reviewed on this site.';
$string['crosswalkimportfailed'] = 'Nothing was imported. Fix these rows and import the file again:';
$string['crosswalkimportnote'] = 'Importing a newer file for the same frameworks updates its mappings in place. Mappings approved or rejected on this site are kept as they are, and mappings missing from the file are left alone.';
$string['crosswalklinkedfrom'] = 'Linked from';
$string['crosswalkmappings'] = 'Mappings';
$string['crosswalkmissingcolumns'] = 'The header row is missing these columns: {$a}.';
$string['crosswalknavlink'] = 'Competency framework crosswalks';
$string['crosswalknolinks'] = 'Nothing on this site links to a competency in the source framework.';
$string['crosswalknone'] = 'No crosswalks have been imported yet.';
$string['crosswalknote'] = 'Note';
$string['crosswalkoutcome'] = 'Outcome';
$string['crosswalkoutcomeauto'] = 'Moves automatically';
$string['crosswalkoutcomemanual'] = 'Needs a decision';
$string['crosswalkoutcomereview'] = 'Needs review';
$string['crosswalkoutcomeunmapped'] = 'Not mapped';
$string['crosswalkprovenance'] = 'Source';
$string['crosswalkreason'] = 'Why';
$string['crosswalkreasonapproved'] = 'Approved {$a->relation} to {$a->target}.';
$string['crosswalkreasondeprecated'] = 'Deprecated with no successor: pick a replacement, or keep or drop the link.';
$string['crosswalkreasonloose'] = '{$a->relation} to {$a->target} is not close enough to move the link without a person.';
$string['crosswalkreasonnotarget'] = '{$a->relation} to {$a->target}, which is not in the target framework on this site.';
$string['crosswalkreasonproposed'] = 'Proposed {$a->relation} to {$a->target}: approve or reject the mapping.';
$string['crosswalkreasonrejected'] = 'Every mapping for it was rejected: pick a replacement, or keep or drop the link.';
$string['crosswalkreasonseveral'] = 'Maps to more than one target ({$a}): pick one.';
$string['crosswalkreasonunmapped'] = 'The crosswalk has no mapping for it.';
$string['crosswalkreject'] = 'Reject';
$string['crosswalkrelation'] = 'Relation';
$string['crosswalkreport'] = 'Link report';
$string['crosswalkreportnote'] = 'What moving every course, activity, learning plan template, learning plan and question link from the source framework to the target would do. Nothing is changed.';
$string['crosswalkreview'] = 'Review mappings';
$string['crosswalkreviewedby'] = 'by {$a->user}, {$a->date}';
$string['crosswalkrowerror'] = 'Line {$a->line}: {$a->error}';
$string['crosswalksource'] = 'Source framework';
$string['crosswalksourcecompetency'] = 'Source competency';
$string['crosswalkstatus'] = 'Status';
$string['crosswalkstatusall'] = 'All';
$string['crosswalkstatusapproved'] = 'Approved';
$string['crosswalkstatusproposed'] = 'Proposed';
$string['crosswalkstatusrejected'] = 'Rejected';
$string['crosswalktarget'] = 'Target framework';
$string['crosswalktargetcompetency'] = 'Target competency';
$string['crosswalktoolong'] = '{$a} is longer than 100 characters.';
$string['privacy:metadata:crosswalk'] = 'Competency framework crosswalk mappings, which record who last approved or rejected each one.';
$string['privacy:metadata:crosswalk:reviewedby'] = 'The user who last approved or rejected the mapping.';
$string['privacy:metadata:crosswalk:status'] = 'Whether the mapping was approved or rejected.';
$string['privacy:metadata:crosswalk:timereviewed'] = 'The time the mapping was approved or rejected.';
$string['privacy:path:crosswalks'] = 'Competency framework crosswalk reviews';
