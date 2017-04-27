/* jshint ignore:start */
/**
 * File size check for manual import.
 *
 * This AMD module provides JavaScript checks the size and length
 * of CSV files to be uploaded using the manual imports interface.
 * If the files are too long to be run manually without risking a
 * timeout, the module presents the user with a warning.
 *
 * @author    Amy Groshek <amy@remote-learner.net>
 * @copyright 2017 onwards Remote Learner US Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @param Function $ jQuery
 * @param Function Papa CSV parsing library
 * @param Function bootstrap Bootstrap library, included from bootstrapbase theme.
 * @param Function log Moodle core JS logging utility
 * @return Function initialise Inits for horizontal scroll and filtering functionality.
 **/
define(['jquery', 'local_datahub/papaparse', 'theme_bootstrapbase/bootstrap', 'core/log'],
        function($, Papa, bootstrap, log) {

    "use strict"; // jshint ;_;
    var scheduling_url = '';

    /**
     * Launch a Bootstrap modal to warn the user about the
     * size of the CSV.
     * @return none
     */
    function launch_warning() {
        Y.log('launch_warning()');
        $('#manual_import_modal').modal({show: true, backdrop: true});
        $('#modal_manual_import_confirm').click(function(e) {
            window.location.href = scheduling_url;
        });
    }

    /**
     * Checks uploaded CSV for number of lines and launches an
     * alert if the CSV has too many lines for manual import.
     * @param  string  targetid  ID of changed filepicker form field.
     * @return none
     */
    function check_csv(targetid) {
        // Build a path to fetch the CSV string from MDL drafts.
        var parentid = 'fitem_' + targetid;
        var path = $('#' + parentid + ' .filepicker-filename a');
        path = $(path).attr('href');
        // Make an AJAX request.
        var csv_data;
        var to_parse;
        var parsed_data;
        csv_data = $.ajax({
            type: "GET",
            url: path,
            dataType: "text/csv",
            error: function(result) {
                Y.log('Error fetching CSV for size check: ');
                Y.log(result.errors);
            },
            complete: function(result) {
                Y.log('CSV fetch complete.');
                to_parse = result.responseText;
                parsed_data = Papa.parse(to_parse);
                var length = parsed_data.data.length - 2;
                if (length > 50) {
                    launch_warning();
                }
            }
        });
    }

    /**
     * Bind event listeners to each of the form elements.
     * @return none
     */
    function bind_events() {
        Y.log('bind_events');
        $('#id_file0, #id_file1, #id_file2').on('change', function(e) {
            Y.log('Change to: ' + e.target);
            var targetid = $(e.target).attr('id');
            check_csv(targetid);
        });
    }

    return {
        /**
         * Initialize form field listeners to check CSV length.
         * @schedulingurl  string  Path to import scheduling interface.
         * @return none
         */
        initialize: function (schedulingurl) {
            $(document).ready( function() {
                Y.log('manualrun.js init.');
                Y.log(schedulingurl);
                scheduling_url = schedulingurl;
                bind_events();
            });
        }
    };
});

