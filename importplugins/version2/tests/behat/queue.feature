@local @local_datahub @local_datahub_queue @dhimport_version2 @javascript

Feature: Queue tab with queue of scheduled tasks and table of completed tasks

    Scenario: Proper display of visual elements on the queue tab
        Given I log in as "admin"
        And I go to the Data Hub Version 2 UI
        And I click on "Queue" "link" in the "ul.nav-tabs" "css_element"
        Then I should see "Data Hub Version 2" in the "//h2" "xpath_element"
        And I should see "Queued / Scheduled Files" in the ".local_datahub_queue h3" "css_element"
        And I should see "These are files that have been added to the queue or have been scheduled to run at a later time."
        And "#pause_scheduled" "css_element" should be visible
        And "#continue_scheduled" "css_element" should be visible
        And "table.jobs" "css_element" should be visible
        And I should see "Completed Files" in the ".local_datahub_completed h3" "css_element"
        And I should see "These are files for this date range that have already been processed. To select a new date range, edit the text field or use the arrow buttons. Then select the Refresh button."
        And "#completed_startdate" "css_element" should be visible
        And "#completed_enddate" "css_element" should be visible
        And "#completed_refresh" "css_element" should be visible
        And "table.completed-jobs" "css_element" should be visible
        And I should see "All dates and times are displayed in the"
        And I should see "There are no jobs to display"

    Scenario: Scheduled, in progress, and waiting jobs are displayed
        Given I log in as "admin"
        And I insert "8" "queued" jobs
        And I insert "8" "completed" jobs
        And I insert "2" "scheduled" jobs
        And I insert "2" "processing" jobs
        And I go to the Data Hub Version 2 UI
        And I click on "Queue" "link" in the "ul.nav-tabs" "css_element"
        And I forgivingly check visibility for ".local_datahub_queue table tr.job-row" "css_element"
        And I forgivingly check visibility for ".local_datahub_completed table tr.job-row" "css_element"
        And I forgivingly check visibility for "//tr[@data-type='scheduled']" "xpath_element"
        And I forgivingly check visibility for "//tr[@data-type='processing']" "xpath_element"
        And I forgivingly check visibility for ".progress-parent" "css_element"
        And I forgivingly check visibility for ".progress-child" "css_element"
        And I forgivingly check visibility for ".records-count" "css_element"


    Scenario: Completed jobs are displayed
        Given I log in as "admin"
        And I insert "8" "completed" jobs
        And I go to the Data Hub Version 2 UI
        And I click on "Queue" "link" in the "ul.nav-tabs" "css_element"
        And I forgivingly check visibility for ".local_datahub_completed table tr.job-row" "css_element"

    Scenario: Pause jobs button successfully pauses jobs or shows notification. Continue processing button successfully reorders and continues or shows notification
        Given I log in as "admin"
        And I insert "8" "queued" jobs
        And I insert "8" "completed" jobs
        And I go to the Data Hub Version 2 UI
        And I click on "Queue" "link" in the "ul.nav-tabs" "css_element"
        And pause processing config is "disabled"
        And I click on "#pause_scheduled" "css_element"
        And pause processing config is "enabled"
        And I forgivingly check visibility for ".local_datahub_queue table .queue_success" "css_element"
        And the "#pause_scheduled" "css_element" should be disabled
        And I reload the page
        And the "#pause_scheduled" "css_element" should be disabled
        And I click on "#continue_scheduled" "css_element"
        And I forgivingly check visibility for ".local_datahub_queue table .queue_alert" "css_element"
        And pause processing config is "disabled"
        And I reload the page
        And the "#continue_scheduled" "css_element" should be disabled
        And I click on "#pause_scheduled" "css_element"
        And pause processing config is "enabled"
        And I wait "15" seconds
        And The queue should be paused
        Then The element "table.jobs" should have class "drag-active"
        And I reorder ".local_datahub_queue table.jobs.drag-active .job-row.draggable" draggables
        And I click on "#continue_scheduled" "css_element"
        And I forgivingly check visibility for ".local_datahub_queue table .queue_success" "css_element"
        And the "#continue_scheduled" "css_element" should be disabled
        And I wait "2" seconds
        And pause processing config is "disabled"

    Scenario: Cancel button successfully reschedules or shows notification
        Given I log in as "admin"
        And I insert "8" "queued" jobs
        And I insert "8" "completed" jobs
        And I go to the Data Hub Version 2 UI
        And I click on "Queue" "link" in the "ul.nav-tabs" "css_element"
        And I forgivingly check visibility for ".local_datahub_queue table .job-row" "css_element"
        And I cancel a job
        Then I forgivingly check visibility for ".queue_success_msg" "css_element"

    Scenario: Reschedule button successfully reschedules or cancels
        Given I log in as "admin"
        And I go to the Data Hub Version 2 UI
        And I insert "2" "scheduled" jobs
        And I click on "Queue" "link" in the "ul.nav-tabs" "css_element"
        And I wait "5" seconds
        And I reschedule a job to "cancel"
        And I reschedule a job to "now"
        Then I forgivingly check visibility for ".queue_success_msg" "css_element"
        And I reschedule a job to "2018-09-02T00:00"
        Then I forgivingly check visibility for ".queue_success_msg" "css_element"

    Scenario: Pause queue with no files in queue
        Given I log in as "admin"
        And I go to the Data Hub Version 2 UI
        And The queue should be waiting
        And I click on "Queue" "link" in the "ul.nav-tabs" "css_element"
        And I click on "#pause_scheduled" "css_element"
        And I forgivingly check visibility for ".local_datahub_queue table .queue_success" "css_element"
        And I wait "15" seconds
        And the "#pause_scheduled" "css_element" should be disabled
        And I click on "#continue_scheduled" "css_element"
        And I forgivingly check visibility for ".local_datahub_queue table .queue_alert" "css_element"
        And I click on "#pause_scheduled" "css_element"
        And I wait "15" seconds
        And The queue should be paused
        Then The element "table.jobs" should have class "drag-active"
        And I click on "#continue_scheduled" "css_element"
        And I forgivingly check visibility for ".local_datahub_queue table .queue_success" "css_element"
        And the "#continue_scheduled" "css_element" should be disabled
        And I wait "15" seconds
        And The queue should be waiting
