@local @local_datahub
Feature: Tabbed user interface

    Scenario: Navigate through version 2 UI tabs
        Given I log in as "admin"
        And I go to the Data Hub Version 2 UI
        Then I should see "Data Hub Version 2" in the "//h2" "xpath_element"
        And I should see "Import" in the "ul.nav-tabs li.active a" "css_element"
        And "Queue" "link" should appear after "Import" "text"
        And "Settings" "link" should appear after "Queue" "link"

        And I click on "Queue" "link" in the "ul.nav-tabs" "css_element"
        Then I should see "Data Hub Version 2" in the "//h2" "xpath_element"
        And I should see "Queue" in the "ul.nav-tabs li.active a" "css_element"
        And "Import" "link" should appear before "Queue" "text"
        And "Settings" "link" should appear after "Queue" "text"

        And I click on "Settings" "link" in the "ul.nav-tabs" "css_element"
        Then I should see "Data Hub Version 2" in the "//h2" "xpath_element"
        And I should see "Settings" in the "ul.nav-tabs li.active a" "css_element"
        And "Queue" "link" should appear before "Settings" "text"
        And "Import" "link" should appear before "Queue" "link"