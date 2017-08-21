<?php

require_once(__DIR__.'/../../../../../../lib/behat/behat_base.php');

class behat_dhimport_version2ui extends behat_base {

    /**
     * Opens dashboard.
     *
     * @Given /^I go to the Data Hub Version 2 UI$/
     */
    public function i_go_to_dhv2ui() {
        $this->getSession()->visit($this->locate_path('/local/datahub/importplugins/version2ui'));
    }


}
