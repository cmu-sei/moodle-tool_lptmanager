@tool @tool_lptmanager
Feature: Learning Plan Template Manager pages
  In order to manage learning plan templates in bulk
  As an admin
  I need the import, export, create and LRS settings pages to work

  Scenario: The plugin pages are registered in site administration and load
    Given I log in as "admin"
    When I navigate to "Competencies > Learning Plan Template Manager" in site administration
    Then I should see "CSV learning plan template description file"
    And I navigate to "Competencies > Export learning plan templates" in site administration
    And I should see "No learning plan templates have been created yet"
    And I navigate to "Competencies > Create learning plan templates from competency framework" in site administration
    And I should see "No competency frameworks are currently available"
    And I navigate to "Plugins > Admin tools > LRS Competency Sync" in site administration
    And I should see "SQL LRS Connection"
    And I should see "Enable LRS sync"

  # Stops at the column-mapping step: pressing "Confirm" runs lp_importer::import(), which
  # always calls debugging() on success, and Behat fails any page carrying debugging output.
  @javascript @_file_upload
  Scenario: Uploading a CSV file leads to the column mapping step
    Given I log in as "admin"
    And I navigate to "Competencies > Learning Plan Template Manager" in site administration
    And I should not see "Select course category"
    When I set the field "Use specific category" to "1"
    Then I should see "Select course category"
    And I set the field "Use specific category" to "0"
    And I should not see "Select course category"
    And I upload "admin/tool/lptmanager/tests/fixtures/behat_templates.csv" file to "CSV learning plan template description file" filemanager
    And I press "Import"
    And I should see "Confirm the column mappings"
    And the field "Short name" matches value "Short name"
    And the field "Competency Framework ID Number" matches value "Competency Framework ID Number"
    And the field "Cross-referenced competency ID numbers" matches value "Cross-referenced competency ID numbers"

  Scenario: Templates are created from the competencies matching an ID number pattern
    Given the following "core_competency > frameworks" exist:
      | shortname       | idnumber |
      | Behat framework | BFW      |
    And the following "core_competency > competencies" exist:
      | shortname     | competencyframework | idnumber |
      | Work role one | BFW                 | WR-001   |
      | Knowledge one | BFW                 | K-001    |
    And the following "core_competency > related_competencies" exist:
      | competency | relatedcompetency |
      | WR-001     | K-001             |
    And I log in as "admin"
    And I navigate to "Competencies > Create learning plan templates from competency framework" in site administration
    When I set the following fields to these values:
      | List of competency frameworks | Behat framework |
      | Competency Name               | WR-             |
    And I press "Create"
    Then I should see "Learning Plan Templates to be Created"
    And I should see "WR-001" in the table row containing "Work role one"
    And I should not see "Knowledge one"
    And I press "Confirm"
    And I should see "Created learning plan template 'Work role one' for framework 'Behat framework'."
    And I press "Continue"
    # The new template carries the competencies cross-referenced from the matching one.
    And I click on "Work role one" "link"
    And I should see "Knowledge one"

  @javascript
  Scenario: The export form lists existing templates and can switch to exporting all of them
    Given the following "core_competency > templates" exist:
      | shortname |
      | LPT1      |
    And I log in as "admin"
    When I navigate to "Competencies > Export learning plan templates" in site administration
    Then the "List of learning plans" select box should contain "LPT1"
    And the "List of learning plans" "field" should be enabled
    And I set the field "Export All Learning Plans" to "1"
    And the "List of learning plans" "field" should be disabled
