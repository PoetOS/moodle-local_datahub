<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package dhimport_version2
 * @author Remote-Learner.net Inc
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2017 Remote Learner.net Inc http://www.remote-learner.net
 */

namespace local_datahub;
use \local_datahub\dblogger;
use \local_datahub\fslogger;

require_once($CFG->dirroot.'/local/datahub/lib/rlip_importplugin.class.php');

/**
 * Base class for import plugins.
 */
abstract class importplugin_base extends \rlip_dataplugin implements importplugin {
    /** @var File provider. */
    protected $provider = null;

    /** @var dblogger Database logger object. */
    protected $dblogger = null;

    /* @var fslogger File-system logger object. */
    protected $fslogger = null;

    /* @var int Track which import line we are on. */
    protected $linenumber = 0;

    /* @var Type of import, true if manual. */
    protected $manual = false;

    /* @var string Stores entity type. */
    protected $tmp_entity = '';

    /* @var array Stores import actions. */
    protected $tmp_import_actions = [];

    /**
     * Import plugin constructor.
     *
     * @param object $provider The import file provider that will be used to obtain import files.
     * @param bool $manual Set to true if a manual run.
     */
    public function __construct($provider = null, $manual = false) {
        global $CFG;
        require_once($CFG->dirroot.'/local/datahub/lib/rlip_fileplugin.class.php');

        if ($provider !== null) {
            $plugin = $this->get_plugin();
            $this->provider = $provider;
            $this->dblogger = $this->provider->get_dblogger();
            $this->dblogger->set_plugin($plugin);
            $this->manual = $manual;
        }
    }

    /**
     * Set the class fslogger.
     *
     * @param fslogger $fslogger The fslogger object to set.
     */
    public function set_fslogger(fslogger $fslogger) {
        $this->fslogger = $fslogger;
    }

    /**
     * Get the set fslogger.
     *
     * @return fslogger The set fslogger, or null if none set.
     */
    public function get_fslogger() {
        return $this->fslogger;
    }

    /**
     * Set the class dblogger.
     *
     * @param dblogger $dblogger The dblogger object to set.
     */
    public function set_dblogger(dblogger $dblogger) {
        $this->dblogger = $dblogger;
    }

    /**
     * Get the set dblogger.
     *
     * @return dblogger The set dblogger, or null if none set.
     */
    public function get_dblogger() {
        return $this->dblogger;
    }

    /**
     * Get the current import plugin.
     *
     * @return string The current plugin component name (i.e. "dhimport_version2").
     */
    abstract protected function get_plugin();

    /**
     * Determines whether the current plugin supports the supplied feature.
     *
     * @param string $feature A feature description, either in the form [entity] or [entity]_[action].
     * @return array|bool An array of actions for a supplied entity, an array of required fields for
     *               a supplied action, or false on error.
     */
    public function plugin_supports($feature) {
        $parts = explode('_', $feature);
        if (count($parts) == 1) {
            // Is this entity supported?
            return $this->plugin_supports_entity($feature);
        } else if (count($parts) == 2) {
            // Is this action supported?
            list($entity, $action) = $parts;
            return $this->plugin_supports_action($entity, $action);
        }
        return false;
    }

    /**
     * Determine whether the current plugin supports a particular entity.
     *
     * @param string $entity The name of the entity.
     * @return array|bool An array of supported actions for the entity, or false if not supported.
     */
    abstract protected function plugin_supports_entity($entity);

    /**
     * Determine whether the current plugin supports an action for an entity.
     *
     * @param string $entity The name of the entity.
     * @param string $action The action.
     * @return array|bool An array of required fields for the entity and action, or false if not supported.
     */
    abstract protected function plugin_supports_action($entity, $action);

    /**
     * Re-indexes an import record based on the import header
     *
     * @param array $header Field names from the input file header
     * @param array $record One record of import data
     * @return object An object with the supplied data, indexed by the columns names.
     */
    protected function index_record($header, $record) {
        $result = new \stdClass;

        // TODO: add more error checking.

        // Iterate through header fields.
        foreach ($header as $index => $shortname) {
            // Look up the value from the import data.
            $value = $record[$index];
            // Index the result based on the header shortname.
            $result->$shortname = $value;
        }

        return $result;
    }

    /**
     * Validate that the action field is included in the header
     *
     * @param string $entity Type of entity, such as 'user'
     * @param array $header The list of supplied header columns
     * @param string $filename The name of the import file, to use in logging
     * @return bool True if the action column is correctly specified, otherwise false
     */
    abstract protected function check_action_header($entity, $header, $filename);

    /**
     * Validate that all required fields are included in the header
     *
     * @param string $entity Type of entity, such as 'user'
     * @param array $header The list of supplied header columns
     * @param string $filename The name of the import file, to use in logging
     * @return bool True if the action column is correctly specified, otherwise false.
     */
    abstract protected function check_required_headers($entity, $header, $filename);

    /**
     * Entry point for processing a single record.
     *
     * @param string $entity The type of entity.
     * @param \stdClass $record One record of import data.
     * @param string $filename Import file name to user for logging.
     * @return bool True on success, otherwise false.
     */
    abstract protected function process_record($entity, $record, $filename);

    /**
     * Hook run after a file header is read.
     *
     * @param string $entity The type of entity.
     * @param array  $header The header record.
     * @param string $filename The name of the file being processed.
     */
    protected function header_read_hook($entity, $header, $filename) {
        // Nothing by default.
    }

    /**
     * A hook to modify the import entities.
     *
     * @param array $entities The entities passed to the run method.
     * @param int $targetstarttime The timestamp for when this task was meant to be run.
     * @param int $lastruntime The last time the export was run. (N/A for import).
     * @param int $maxruntime The max time in seconds to complete import. (default/0 = unlimited).
     * @param object $state Previous ran state data to continue from.
     * @return array A modified entities array, if needed.
     */
    protected function hook_import_entities($entities, $targetstarttime, $lastruntime, $maxruntime, $state) {
        return $entities;
    }

    /**
     * Validate the file can be processed, and return the number of records.
     *
     * Note: This just validates that we can deal with the file, it does not check data.
     *
     * @param \rlip_fileplugin_base $fileplugin The file plugin handling the file.
     * @return int|null The number of records, or null if the file cannot be validated.
     */
    protected function validate_file(\rlip_fileplugin_base $fileplugin) {
        // Must count import files lines in case of error and scan for acceptable character encoding.
        $filelines = 0;
        $filename = $fileplugin->get_filename();
        $fileplugin->open(RLIP_FILE_READ);
        $encodingok = true;
        $firstbadline = 0;
        while ($lineitems = $fileplugin->read()) {
            ++$filelines;
            if ($encodingok) {
                foreach ($lineitems as $item) {
                    if (!mb_check_encoding($item, 'utf-8')) {
                        $encodingok = false;
                        $firstbadline = $filelines;
                    }
                }
            }
        }

        // Track the total number of records to process.
        // TODO: find a way to get this number more generically, e.g. for non-flat formats like CSV.
        $this->dblogger->set_totalrecords($filelines - 1);

        $fileplugin->close();

        // Do logging for either bad encoding or bad data.
        if ($encodingok === false || $filelines === 1) {
            // Handle unacceptable character encoding.
            if (!$encodingok) {
                $message = "Import file {$filename} was not processed because it contains unacceptable character encoding. ";
                $message .= "Please fix (convert to UTF-8) the import file and re-upload it.";

                if ($this->fslogger) {
                    $this->fslogger->log_failure($message, 0, $filename, $firstbadline);
                }
                if ($this->dblogger) {
                    $this->dblogger->signal_invalid_encoding($message);
                    $this->dblogger->flush($filename);
                }
            }

            // Handle files with header but no records or lines end with CR only.
            if ($filelines <= 1) {
                if ($this->fslogger) {
                    $message = 'Could not read data, make sure import file lines end with LF (linefeed) character: 0x0A';
                    $this->fslogger->log_failure($message, 0, $filename, 1);
                }
                $this->dblogger->track_success(false, true);
                $this->dblogger->flush($filename);
            }

            if (!$this->manual) {
                // Delete processed import file.
                if (!$fileplugin->delete()) {
                    $message = "Error when attempting to delete temporary file '".$filename."'";
                    mtrace($message);
                    $this->fslogger->log_failure($message, 0, $filename);
                }
            }

            return null;
        }

        return $filelines;
    }

    /**
     * A hook that can be implemented in implementations that is called before processing every line.
     *
     * If this returns true, the processing stops in the same fashion as if the time limit was exceeded.
     *
     * @param int $starttime The timestamp the job started.
     * @param int $maxruntime The maximum number of seconds allowed for the job.
     * @return bool If true, stop processing. If false, continue as normal.
     */
    protected function hook_should_stop_processing($starttime, $maxruntime) {
        return false;
    }

    /**
     * Entry point for processing an import file
     *
     * @param string $entity The type of entity
     * @param int $maxruntime The max time in seconds to complete import (default/0 = unlimited)
     * @param object $state Previous ran state data to continue from
     * @return mixed object Current state of import processing,
     *                             null for success, false if file is skipped.
     */
    protected function process_import_file($entity, $maxruntime = 0, $state = null) {
        global $CFG;

        if (!$state) {
            $state = new \stdClass;
        }

        $starttime = time();

        // Set up loggers.

        // Track the start time as the current time.
        $this->dblogger->set_starttime($starttime);

        // Set up fslogger with this starttime for this entity.
        $this->fslogger = $this->provider->get_fslogger($this->dblogger->plugin, $entity, $this->manual, $starttime);

        $this->dblogger->set_log_path($this->provider->get_log_path());
        $this->dblogger->set_entity_type($entity);
        // ELIS-8255: set endtime as now, to avoid endtime = epoch + timezone.
        $this->dblogger->set_endtime(time());

        // Fetch a file plugin for the current file.
        $fileplugin = $this->provider->get_import_file($entity);
        if ($fileplugin === false) {
            // No error because we're just going to skip this entity.
            return false;
        }

        // Validate file.
        $filename = $fileplugin->get_filename();
        $filelines = $this->validate_file($fileplugin);
        if ($filelines === null) {
            return null;
        }

        $fileplugin->open(RLIP_FILE_READ);
        if (!$header = $fileplugin->read()) {
            // No error because we're just going to skip this entity.
            return false;
        }
        // Initialize line number.
        $this->linenumber = 0;

        // Header read, so increment line number.
        $this->linenumber++;

        // Check that the file directory is valid.
        $filepath = get_config($this->dblogger->plugin, 'logfilelocation');
        if (!$writable = is_writable($CFG->dataroot.'/'.$filepath)) {
            // Invalid folder specified for the logfile.
            // Log this message...
            $this->dblogger->set_endtime(time());
            $this->fslogger->set_logfile_status(false);
            $this->dblogger->set_logfile_status(false);
            $this->dblogger->flush($filename);
            return null;
        } else {
            $this->fslogger->set_logfile_status(true);
            $this->dblogger->set_logfile_status(true);
        }

        $this->header_read_hook($entity, $header, $fileplugin->get_filename());

        if (!$this->check_action_header($entity, $header, $filename)) {
            // Action field not specified in the header, so we can't continue.
            $this->dblogger->set_endtime(time());
            $this->dblogger->flush($filename);
            return null;
        }

        if (!$this->check_required_headers($entity, $header, $filename)) {
            // A required field is missing from the header, so we can't continue.
            $this->dblogger->set_endtime(time());
            $this->dblogger->flush($filename);
            return null;
        }

        // Main processing loop.
        while ($record = $fileplugin->read()) {
            if (isset($state->linenumber)) {
                if ($this->linenumber <= $state->linenumber) {
                    $this->linenumber++;
                    continue;
                }
                unset($state->linenumber);
            }
            // Check if we should continue processing.
            $timeexceeded = ($maxruntime && (time() - $starttime) > $maxruntime) ? true : false;
            $shouldstopprocessing = $this->hook_should_stop_processing($starttime, $maxruntime);
            if ($timeexceeded === true || $shouldstopprocessing === true) {
                if ($timeexceeded === true) {
                    $this->dblogger->signal_maxruntime_exceeded();
                }
                $state->result = false;
                $state->entity = $entity;
                $state->filelines = $filelines;
                $state->linenumber = $this->linenumber;
                // Clean-up before exiting ...
                $fileplugin->close();
                $this->dblogger->set_endtime(time());
                // Flush db log record.
                $this->dblogger->flush($filename);
                return $state;
            }
            // Index the import record with the appropriate keys.
            $record = $this->index_record($header, $record);

            // Track return value.
            // TODO: change second parameter when in the cron.
            $result = $this->process_record($entity, $record, $filename);

            $this->dblogger->track_success($result, true);
        }

        $fileplugin->close();

        // Track the end time as the current time.
        $this->dblogger->set_endtime(time());

        // Flush db log record.
        $this->dblogger->flush($filename);

        if (!$this->manual) {
            // Delete processed import file.
            if (!$fileplugin->delete()) {
                $message = "Error when attempting to delete temporary file '".$filename."'";
                mtrace($message);
                $this->fslogger->log_failure($message, 0, $filename);
            }
        }

        return null;
    }

    /**
     * Mainline for running the import
     *
     * @param int $targetstarttime The timestamp for when this task was meant to be run.
     * @param int $lastruntime The last time the export was run. (N/A for import).
     * @param int $maxruntime The max time in seconds to complete import. (default/0 = unlimited).
     * @param object $state Previous ran state data to continue from.
     * @return object State data to pass back on re-entry, null on success.
     *           ->result false on error, i.e. time limit exceeded.
     */
    public function run($targetstarttime = 0, $lastruntime = 0, $maxruntime = 0, $state = null) {
        // Track the provided target start time.
        $this->dblogger->set_targetstarttime($targetstarttime);

        if (!$state) {
            $state = new \stdClass;
        }

        // Determine the entities that represent the different files to process.
        $entities = $this->get_import_entities();
        $entities = $this->hook_import_entities($entities, $targetstarttime, $lastruntime, $maxruntime, $state);

        // Track whether some file was processed.
        $fileprocessed = false;

        // Process each import file.
        foreach ($entities as $entity) {
            $starttime = time();
            if (isset($state->entity)) {
                if ($entity != $state->entity) {
                    continue;
                }
                unset($state->entity);
            }

            $result = $this->process_import_file($entity, $maxruntime, $state);

            // Flag a file having been processed if method was successful.
            $fileprocessed = $fileprocessed || ($result === null);

            if ($result !== null && $result !== false) {
                if ($this->fslogger) {
                    // TODO: look at a better way to do this for non-flat.
                    // File formats like XML.
                    $a = new \stdClass;
                    $a->entity = $result->entity;
                    $a->recordsprocessed = $result->linenumber - 1;
                    $a->totalrecords = $result->filelines - 1;
                    $strid = 'importexceedstimelimit_b';
                    if ($this->manual) {
                        $strid = 'manualimportexceedstimelimit_b';
                    }
                    $msg = get_string($strid, 'local_datahub', $a);
                    $this->fslogger->log_failure($msg);
                }
                return $result;
            }
            if ($maxruntime) {
                $usedtime = time() - $starttime;

                // NOTE: if no file was processed, we should keep running.
                // This will never hapen in practise but is helpful in unit testing.
                if ($usedtime < $maxruntime || !$fileprocessed) {
                    $maxruntime -= $usedtime;
                } else if (($nextentity = next($entities)) !== false) {
                    // Import time limit already exceeded, log & exit.
                    $this->dblogger->signal_maxruntime_exceeded();
                    $filename = '{unknown}'; // Default if $fileplugin false.
                    // Fetch a file plugin for the current file.
                    $fileplugin = $this->provider->get_import_file($entity);
                    if ($fileplugin !== false) {
                        $filename = $fileplugin->get_filename();
                    }
                    // Flush db log record.
                    // TODO: set end time?
                    $this->dblogger->flush($filename);
                    $state = new \stdClass;
                    $state->result = false;
                    $state->entity = $nextentity;
                    if ($this->fslogger) {
                        $strid = 'importexceedstimelimit';
                        if ($this->manual) {
                            $strid = 'manualimportexceedstimelimit';
                        }
                        $msg = get_string($strid, 'local_datahub', $state);
                        $this->fslogger->log_failure($msg);
                    }
                    return $state;
                } else {
                    // Actually we're done, no next entities.
                    return null;
                }
            }
        }
        return null;
    }
}

