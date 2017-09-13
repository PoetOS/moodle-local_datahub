@local @local_datahub @dhimport_version2 @javascript
Feature: CVS builder

    @dhimport_version2 @csv_builder_course
    Scenario: Build Course CSV Template
        Given I log in as "admin"
        And I go to the Data Hub Version 2 UI
        And I change window size to "large"
        And I click on "Create CSV Template File" "link"
        Then I should see "Template Type:" in the "#csvtemplatebuilder .csvheader h4" "css_element"

        And I set the field "csvtemplatetypeselect" to "course.csv"
        And I wait "1" seconds
        And I set the field "addselect" to "fullname,idnumber,summary,category"
        And I click on "#addcsvfield" "css_element"
        And I set the field "removeselect" to "summary"
        And I click on "#removecsvfield" "css_element"
        And I click on "Download CSV Template" "button"

    @dhimport_version2 @csv_builder_enrolment
    Scenario: Build Enrolment CSV Template
        Given I log in as "admin"
        And I go to the Data Hub Version 2 UI
        And I change window size to "large"
        And I click on "Create CSV Template File" "link"
        Then I should see "Template Type:" in the "#csvtemplatebuilder .csvheader h4" "css_element"

        And I set the field "csvtemplatetypeselect" to "enrolment.csv"
        And I wait "1" seconds
        And I set the field "addselect" to "group,status"
        And I click on "#addcsvfield" "css_element"
        And I set the field "removeselect" to "status"
        And I click on "#removecsvfield" "css_element"
        And I click on "Download CSV Template" "button"

    @dhimport_version2 @csv_builder_user
    Scenario: Build User CSV Template
        Given I log in as "admin"
        And I go to the Data Hub Version 2 UI
        And I change window size to "large"
        And I click on "Create CSV Template File" "link"
        Then I should see "Template Type:" in the "#csvtemplatebuilder .csvheader h4" "css_element"

        And I set the field "csvtemplatetypeselect" to "user.csv"
        And I wait "1" seconds
        And I set the field "addselect" to "city,country,auth"
        And I click on "#addcsvfield" "css_element"
        And I set the field "removeselect" to "auth"
        And I click on "#removecsvfield" "css_element"
        And I click on "Download CSV Template" "button"