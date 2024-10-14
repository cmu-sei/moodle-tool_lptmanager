# Learning Plan Template Manager for Moodle

## Description
This is an admin tool plugin that allows for the import, export, and automatic creation of Learning Plan Templates from a Competency Framework.

## Installation
* Download the plugin and extract into admin/tool/lptmanager

## Usage
The plugin currently contains a temporary cron task that will create learning plan templates based on competencies with the regex -WRL- in the name. This regex matches the work roles listed in the NIST NICE Framework for Cybersecurity. This task is temporary because a form is being developed that will allow the user to specify the regex and confirm the templates prior to their creation.

To run the task manually:
php admin/cli/scheduled_task.php --execute=\\tool_lptmanager\\task\\cron_task

## License
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

