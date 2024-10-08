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
 * Links and settings
 *
 * This file contains links and settings used by tool_lptmanager
 *
 * @package    tool_lptmanager
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

if (get_config('core_competency', 'enabled')) {
    // Manage learning plan templates page.
    $temp = new admin_externalpage(
        'toollptmanager',
        get_string('pluginname', 'tool_lptmanager'),
        new moodle_url('/admin/tool/lptmanager/index.php'),
        'moodle/competency:competencymanage'
    );
    $ADMIN->add('competencies', $temp);

    // Export learning plan templates page.
    $temp = new admin_externalpage(
        'toollpexport',
        get_string('exportnavlink', 'tool_lptmanager'),
        new moodle_url('/admin/tool/lptmanager/export.php'),
        'moodle/competency:competencymanage'
    );
    $ADMIN->add('competencies', $temp);

    // Sync learning plan templates page.
    $temp = new admin_externalpage(
        'toollpsync',
        get_string('syncnavlink', 'tool_lptmanager'),
        new moodle_url('/admin/tool/lptmanager/sync.php'),
        'moodle/competency:competencymanage'
    );
    $ADMIN->add('competencies', $temp);
}

// No report settings.
$settings = null;
