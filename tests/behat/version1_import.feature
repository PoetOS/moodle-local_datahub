@local @local_datahub @javascript @_file_upload

Feature: Import a version1 file.

    Background:
        Given I log in as "admin"


    # T33.1 #1
    Scenario: version1 user import succeeds.
        Given I make a Datahub "version1" manual "user" import with file "user_t33_1.csv"
        Then I should see "All lines from import file user_t33_1.csv were successfully processed. (1 of 1)"

    # T33.2 #1
    Scenario: version1 course import succeeds.
        Given I make a Datahub "version1" manual "course" import with file "course_t33_2.csv"
        Then I should see "All lines from import file course_t33_2.csv were successfully processed. (1 of 1)"

    # T33.6 #1
    Scenario: version1 create/update/delete user imports succeeds.
        Given I make a Datahub "version1" manual "user" import with file "create_user_t33_6.csv"
        Then I should see "All lines from import file create_user_t33_6.csv were successfully processed. (2 of 2)"
        Then the Datahub "import_version1_manual_user_" log file should contain '\[create_user_t33_6.csv line 2\] User with username "testuser", email "test@user.com", idnumber "testuser" successfully created.'
        Then the Datahub "import_version1_manual_user_" log file should contain '\[create_user_t33_6.csv line 3\] User with username "testuser2", email "test@user2.com" successfully created.'

    # Commentted-out test cases missing .csv file attachments in wiki (?)
          # T33.6 #2
    #     Given I make a Datahub "version1" manual "user" import with file "update_user_t33_6.csv"
    #     Then I should see "All lines from import file update_user_t33_6.csv were successfully processed. (8 of 8)"

    # T33.6 #3
    #     Given I make a Datahub "version1" manual "user" import with file "delete_user_t33_6.csv"
    #     Then I should see "All lines from import file delete_user_t33_6.csv were successfully processed. (14 of 14)"

    # T33.6 #4
    Scenario: version1 create/update/delete course imports succeeds.
        Given I make a Datahub "version1" manual "course" import with file "create_course_t33_6.csv"
        Then I should see "All lines from import file create_course_t33_6.csv were successfully processed. (1 of 1)"
        Then the Datahub "import_version1_manual_course_" log file should contain '\[create_course_t33_6.csv line 2\] Course with shortname "testcourse2" successfully created.'

          # T33.6 #6
    #     Given I make a Datahub "version1" manual "course" import with file "update_course_t33_6.csv"
    #     Then I should see "All lines from import file update_course_t33_6.csv were successfully processed. (2 of 2)"

          # T33.6 #7
    #     Given I make a Datahub "version1" manual "course" import with file "delete_course_t33_6.csv"
    #     Then I should see "All lines from import file delete_course_t33_6.csv were successfully processed. (2 of 2)"

    @datahub_manualimport_warn @javascript @_file_upload
    Scenario: User is shown a warning about large files in the manual import interface.
        Given I navigate to "/local/datahub/importplugins/manualrun.php?plugin=dhimport_version1"
        And I upload "200users.csv" file to field "User file"
        Then I forgivingly check visibility of ".modal-content" "css"
        Then I forgivingly click on "#close_manualrun_modal" "css"
        And I upload "25users.csv" file to field "User file"
        Then ".modal-dialog" "css_element" should not be visible
