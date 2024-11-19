# Learning Plan Template Manager for Moodle 

## Description 

**Learning Plan Template Manager** is a plugin for Moodle that allows for the import, export, and automatic creation of learning plan templates from a competency framework. This plugin was specifically developed for work roles in the NIST NICE Cybersecurity Framework.

Happy Moodling! 🎓

## Features

- Import learning plan templates in CSV format. 
- Export existing learning plan templates for external use.  
- Sync learning plan templates with competency frameworks and automatically create learning plan templates.  

## Installation

1. Download the plugin from this repo.
2. Extract the plugin into the following directory in your Moodle installation:

```
admin/tool/lptmanager
```

Or:

1. Download the plugin from this repo.
2. In Moodle, with site administrator permissions, navigate to **Site administration**, **Plugins**, **Install plugins**.
3. Under **Install plugin from ZIP file**, click **Choose a file...**, then follow the onscreen instructions to upload and install the **moodle-tool_lptmanager-main.zip** file. 

## Usage

### Importing learning plan templates

1. In Moodle, navigate to **Site administration**, **Competencies**, **Import learning plan templates**.
2. Upload a properly formatted learning plan template description .CSV file and click **Import**.
3. Confirm the column mappings and click **Continue**.

### Exporting learning plan templates

1. Navigate to **Site administration**, **Competencies**, **Export learning plan templates**.
2. Select a learning plan from the list of learning plans (or select *Export All Learning Plans*) and click **Export**.
3. Save the file as a .CSV file.

### Syncing Learning Plan Templates

1. Navigate to **Site administration**, **Competencies**, **Sync learning plan templates from competency framework**.
2. Select a framework, enter a competency name or ID (*regex pattern*) and click Sync to automatically create the learning plan templates. For example: *Cyber Operations Planning* is the competency; *CE-WRL-002* is the regex pattern.
3. Click **Sync**.

## Future Development

- A form-based interface to specify regex patterns.
- An approval process to review templates before their creation.
- Reporting features to monitor and manage generated templates.

## Contributing  

Contributions are welcome. To report issues, suggest features, or contribute code, please follow the steps below.   

1. Open an issue in the [GitHub Issues](#) section.
2. Create a new branch for your feature or fix.
3. Make your updates.
4. Create and submit your pull request.

## License  

Learning Plan Template Manager for Moodle

Copyright 2024 Carnegie Mellon University.

NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT. Licensed under a GNU GENERAL PUBLIC LICENSE - Version 3, 29 June 2007-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.

[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution. Please see Copyright notice for non-US Government use and distribution.

This Software includes and/or makes use of Third-Party Software each subject to its own license.

DM24-1177
