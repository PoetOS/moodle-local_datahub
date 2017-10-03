<?php

require_once(__DIR__.'/../../../../../../lib/behat/behat_base.php');

class behat_dhimport_version2 extends behat_base {

    /**
     * Opens dashboard.
     *
     * @Given /^I go to the Data Hub Version 2 UI$/
     */
    public function i_go_to_the_data_hub_version_2_ui() {
        $this->getSession()->visit($this->locate_path('/local/datahub/importplugins/version2/'));
    }


    /**
     * @Then I ensure version2 directories are set up
     */
    public function i_ensure_version2_directories_are_set_up() {
        global $CFG;
        $dest = $CFG->dataroot.'/'.get_config('dhimport_version2', 'schedule_files_path');
        @mkdir($dest, 0777, true);
    }

    /**
     * @Given I set the import time to two hours from now
     */
    public function i_set_them_import_time_to_an_hour_from_now() {
        // KNOWN ISSUE: this step may not work if the server time zone does not match the browser time zone.
        $page = $this->getSession()->getPage();

        // Set the month field.
        $monthselect = $page->find('css', '#id_month');
        if ($monthselect === null) {
            throw new ElementNotFoundException(
                $this->getSession(), 'form field', 'id|name|label|value', $locator
            );
        }
        $monthselect->selectOption(date('n_Y'));

        // Set the day field.
        $dayselect = $page->find('css', '#id_day');
        if ($dayselect === null) {
            throw new ElementNotFoundException(
                $this->getSession(), 'form field', 'id|name|label|value', $locator
            );
        }
        $dayselect->selectOption(date('j'));

        // Set the time field.
        $timeselect = $page->find('css', '#id_time');
        if ($timeselect === null) {
            throw new ElementNotFoundException(
                $this->getSession(), 'form field', 'id|name|label|value', $locator
            );
        }
        $hour = intval(date('G'));
        $hour += 2;
        $timeselect->selectOption($hour);
    }

    /**
     * @Given /^I forgivingly check visibility for "([^"]*)" "([^"]*)"$/
     * @param  String  $selector  CSS or Xpath selector
     * @param  String  $findtype  'css_element' or 'xpath_element' to pass to $page->find()
     * @return Boolean            Boolean for visibility of element.
     */
    public function i_forgivingly_check_visibility_for($selector, $findtype) {
        $session = $this->getSession();
        $page = $session->getPage();
        $findtype = str_replace('_element', '', $findtype);
        $el = $page->find($findtype, $selector);
        if (!!$el) {
            // Credit for original version of this iterative checking, to counteract
            // failures on elements created by JavaScript, goes to Charles Verge.
            // See commit 0827ce234 in moodle-local_datahub.git.
            $count = 10;
            while ($count > 0) {
                try {
                    $el->isVisible();
                    $count = -10;
                    return true;
                } catch (\Exception $e) {
                    $count--;
                    $this->getSession()->wait(self::TIMEOUT * 1000, self::PAGE_READY_JS);
                }
            }
        }
    }

    /**
     * @Given /^I forgivingly click "([^"]*)" "([^"]*)"$/
     * @param  String $selector  Selector for the element to click.
     * @param  String $findtype  Selector type, "css" or "xpath" (will also accept either as "*_element")
     * @return Boolean
     */
    public function i_forgivingly_click($selector, $findtype) {
        $session = $this->getSession();
        $page = $session->getPage();
        // Remove the MDL search string suffix if it's there.
        $findtype = str_replace('_element', '', $findtype);
        $el = $page->find($findtype, $selector);
        if (!!$el) {
            // Credit for original version of this iterative clicking, to counteract
            // failures on elements created by JavaScript, goes to Charles Verge.
            // See commit 0827ce234 in moodle-local_datahub.git.
            $count = 10;
            while ($count > 0) {
                try {
                    $el->click();
                    $count = -10;
                } catch (\Exception $e) {
                    $count--;
                    $this->getSession()->wait(self::TIMEOUT * 1000, self::PAGE_READY_JS);
                }
            }
        }
    }

    /**
     * Tests whether a css-selected element has the given class.
     *
     * @Given /^The element "(?P<selector>(?:[^"]|\\")*)" should have class "(?P<class>(?:[^"]|\\")*)"$/
     */
    public function item_hasclass($selector, $class) {
        $session = $this->getSession();
        $page = $session->getPage();
        $session->wait(5000);
        $element = $page->find('css', $selector);
        if (!$element) {
            $message = sprintf('Could not find element using the selector "%s"', $selector);
            throw new \Exception($message);
        }
        $hasclass = $element->hasClass($class);
        if (!$hasclass) {
            $message = sprintf('Class "%1s" not found for selector "%2s"', $class, $selector);
            throw new \Exception($message);
        }
    }

    /**
     * Checks for visibility of a given css element
     *
     * @Given /^"(?P<selector>(?:[^"]|\\")*)" css element should be visible$/
     */
    public function is_csselement_visible($selector) {
        $session = $this->getSession();
        $page = $session->getPage();
        $element = $page->find('css', $selector);
        if (!$element) {
            $message = sprintf('Could not find element using the selector "%s"', $selector);
            throw new \Exception($message);
        }
        $isvisible = $element->isVisible();
        return $isvisible;
    }

    /**
     * @given /^I reorder "(?P<selector>(?:[^"]|\\")*)" draggables$/
     * @param  String   $selector  CSS selector for draggables to reorder
     * @return Boolean
     */
    public function i_reorder_draggables($selector) {
        $session = $this->getSession();
        $page = $session->getPage();
        // Get draggables first and last.
        $draggables = $page->findAll('css', '.drag-active .job-row.draggable');
        // Execute JS to reorder draggables.
        $this->getSession()->getDriver()->evaluateScript(
            "function(){
                var draggables = document.getElementsByClassName('job-row draggable');
                var first = draggables[0];
                var last = draggables[draggables.length - 1];
                var table = document.getElementsByClassName('jobs drag-active');
                var parent = table[0].getElementsByTagName('tbody');
                parent[0].insertBefore(last, first);
                var newset = document.getElementsByClassName('job-row draggable');
                parent[0].removeChild(newset[newset.length - 1]);
            }()"
        );
        // Fetch new draggables, and if they have been reordered, return true.
        $newdraggables = $page->findAll('css', '.drag-active .job-row.draggable');
        if ($draggables == $newdraggables) {
            $message = sprintf('Unable to reorder draggables with selector "%s".', $selector);
            throw new \Exception($message);
        } else {
             return true;
        }
    }

    /**
     * @given /^I cancel a job$/
     * @return Boolean
     */
    public function i_cancel_a_job() {
        $session = $this->getSession();
        $page = $session->getPage();
        $cancel_btn = $page->find('css', 'a.cancel_job');
        $cancel_btn->click();
        return true;
    }

    /**
     * @given /^I reschedule a job to "(?P<time>(?:[^"]|\\")*)"$/
     * @param  String $time String intidating a datetime, or "now" or "cancel"
     * @return Boolean
     */
    public function i_reschedule_a_job_to($time) {
        $session = $this->getSession();
        $page = $session->getPage();
        // For simplicity we just select the first one.
        $reschedule_btn = $page->find('css', 'a.reschedule_job');
        $reschedule_btn->click();
        $session->wait(2000);
        // Once again, just use the first one.
        $submit_btn = $page->find('css', 'button.queue-date-save');
        try {
            switch ($time) {
                case "cancel":
                    $cancel_btn = $page->find('css', 'button.queue-date-cancel');
                    $cancel_btn->click();
                    return true;
                case "now":
                    $submit_btn->click();
                    return true;
                default:
                    // Enable the datepicker by selecting the radio button.
                    $setdateradios = $page->findAll('xpath', '//table[@id="queue_job_table"]/tbody/tr/td/form/fieldset/label/input[@value="schedule"]');
                    $setdateradios[0]->click();
                    $input = $page->find('css', 'input.reschedule_task');
                    $input->setValue($time);
                    $submit_btn->click();
                    return true;
            }
        } catch (\Exception $e) {
            $message = sprintf('Unable to reschedule job to time "%1s".', $time);
            throw new \Exception($e.': '.$message);
        }
    }

    /**
     * @Given /^I insert "(?P<count>(?:[^"]|\\")*)" "(?P<type>(?:[^"]|\\")*)" jobs$/
     * @param  String  $count    Number of records to insert.
     * @param  String  $type     Either "queued", "completed", "processing", or "scheduled"
     * @return none
     */
    public function i_insert_jobs($count, $type) {
        global $CFG, $DB, $USER;
        $count = (int)$count;
        if ($count <= 0) {
            $message = sprintf('Count of "%1s" records does not make sense here.', $count);
            throw new \Exception($message);
        }
        $records = array();
        for ($x = 0; $x < $count; $x++) {
            $record = new stdClass();
            $record->filename = 'inserted_record_'.$type.'_'.$x.'.csv';
            $record->userid = $USER->id;
            $record->queueorder = $x + 1;
            $record->state = 0;
            $record->status = 0;
            $record->timecompleted = 0;
            $record->timemodified = time();
            $record->timecreated = time();
            $record->scheduledtime = 0;
            if ($type == 'completed') {
                $record->status = 1;
                $record->timecompleted = (time() - ($x * 24 * 60 * 60)) / 1000;
            } else if ($type == 'processing') {
                $record->status = 3;
                $process = new stdClass();
                $process->filelines = 200;
                $process->linenumber = rand(0, 200);
                $record->state = @serialize($process);
            } else if ($type == 'scheduled') {
                $record->status = 4;
                $future = new DateTime();
                $future->add(new DateInterval('P3D'));
                $record->scheduledtime = date_timestamp_get($future);
            }
            $records[] = $record;
        }
        $DB->insert_records('dhimport_version2_queue', $records);
        // Verify that inserting records worked.
        $areinserted = $DB->count_records('dhimport_version2_queue');
        if ($areinserted <= 0) {
            $message = 'Error: no records inserted.';
            throw new \Exception($message);
        }
        // Run cron so everybody shows up.
        $cron = file_get_contents($CFG->wwwroot.'/admin/cli/cron.php');
    }

}
