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

}
