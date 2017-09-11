@local @local_datahub @dhimport_version2 @javascript
Feature: Import File Schedule

    @dhimport_version2 @schedule_asap_import
    Scenario: Schedule ASAP Import
        Given I log in as "admin"
        And I ensure version2 directories are set up
        And I go to the Data Hub Version 2 UI
        And I upload "version2_users.csv" file to field "Import file"
        And I click on "#id_queueschedule_0" "css_element"
        And I click on "Save to Queue" "button"
        Then I should see "File uploaded and added to the queue successfully. Click here to view the queue." in the ".dhimport_version2_alert" "css_element"

    @dhimport_version2 @schedule_timed_import
    Scenario: Schedule Timed Import
        Given I log in as "admin"
        And I ensure version2 directories are set up
        And I go to the Data Hub Version 2 UI
        And I upload "version2_users.csv" file to field "Import file"
        And I click on "#id_queueschedule_1" "css_element"
        And I set the import time to two hours from now
        And I click on "Save to Queue" "button"
        Then I should see "File uploaded and added to the queue successfully. Click here to view the queue." in the ".dhimport_version2_alert" "css_element"

    @dhimport_version2 @unknown_file
    Scenario: Test Unknown File Validation
        Given I log in as "admin"
        And I go to the Data Hub Version 2 UI
        And I upload "version2_unknown.csv" file to field "Import file"
        Then I should see "The import file format did not match one that can be processed." in the ".confirmation-message" "css_element"

    @dhimport_version2 @missing_required
    Scenario: Test Missing Required Fields Validation
        Given I log in as "admin"
        And I go to the Data Hub Version 2 UI
        And I upload "version2_users_missing_required.csv" file to field "Import file"
        Then I should see "The following required field(s) were missing: username" in the ".confirmation-message" "css_element"
