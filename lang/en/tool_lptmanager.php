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
$string['createnavlink'] = 'Create learning plan templates';
$string['encoding'] = 'Encoding';
$string['export'] = 'Export';
$string['exportall'] = 'Export All Learning Plans';
$string['exportall_help'] = 'Export All Learning Plans instead of just one';
$string['exportnavlink'] = 'Export learning plan templates';
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
$string['pluginname'] = 'Import learning plan templates';
$string['processingfile'] = 'Processing file';
$string['relatedidnumbers'] = 'Cross-referenced competency ID numbers';
$string['shortname'] = 'Short name';
$string['learningplansimported'] = 'Learning plan template imported successfully.';
$string['privacy:metadata'] = 'The Import learning plan templates plugin does not store any personal data.';
$string['noframeworks'] = 'No Competency Frameworks Found';
$string['noframeworks_help'] = 'No competency frameworks are currently available. Please check your competency frameworks settings or add the necessary frameworks to proceed with the create process.';
$string['competencyname'] = 'Competency Name';
$string['competencyname_help'] = 'Enter the name or pattern to match competencies by their name.';
$string['crontask'] = 'Create Learning Plan Templates from Competencies with string WRL';
$string['usecategory'] = 'Use specific category';
$string['usecategory_desc'] = 'Use specific category';
$string['usecategory_desc_help'] = 'Select this option to import the learning plan into a specified course category. If no course category is selected, the default System category will be used.';
$string['coursecategory'] = 'Select course category';
$string['importnote'] = '<strong>Instructions:</strong> Before importing learning plan templates into Moodle, ensure that any associated competency frameworks have already been imported.';
$string['exportnote'] = '<strong>Instructions:</strong> After exporting the learning plan templates, you may also wish to export any competency frameworks used by these learning plan templates';
$string['confirm_create'] = 'Please confirm the creation of the following competencies:';
$string['confirm_create_heading'] = 'Learning Plan Templates to be Created';
$string['nocompetenciesfound'] = 'No competencies were found matching your criteria. Please verify your competency regex or framework selection and try again.';
$string['competencyshortname'] = 'Competency Shortname';
$string['competencyidnumber'] = 'Competency ID';
$string['competencydescription'] = 'Description';
$string['createnote'] = '<strong>Instructions:</strong> Enter an expression to match competency ID numbers. For each matching competency, the tool will generate a learning plan template and it will add the cross-referenced competencies to the learning plan template.';
