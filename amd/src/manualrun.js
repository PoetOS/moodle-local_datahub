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
define(['jquery', 'local_datahub/papaparse', 'core/templates', 'core/modal_factory', 'core/log', 'core/str'],
        function($, Papa, Templates, ModalFactory, log, str) {

    "use strict"; // jshint ;_;
    var scheduling_url = '';
    var mod_strings = [];
    var confirm_modal;

    /**
     * Launch a Bootstrap modal to warn the user about the
     * size of the CSV.
     * @return none
     */
    function launch_warning() {
        Y.log('launch_warning()');
        confirm_modal.show();
        $('#confirm_manualrun_modal').click(function(e) {
            Y.log('click confirm');
            window.location.href = scheduling_url;
        });
        $('#close_manualrun_modal').click(function(e) {
            Y.log('click close');
            confirm_modal.hide();
            $('#close_manualrun_modal, #confirm_manualrun_modal').unbind('click');
        });
    }

    /**
     * Build Bootstrap modal for reuse when large files are added.
     * @return none
     */
    function build_modal() {
        // Build modal for display if csv too large.
        var trigger = $('#create_modal');
        var footerHTML = '<button type="button" class="btn btn-secondary" id="close_manualrun_modal">Close</button>' +
                         '<button type="button" class="btn btn-primary" id="confirm_manualrun_modal">' +
                            mod_strings[2] +
                         '</button>';
        ModalFactory.create({
            title: mod_strings[0],
            body: '<p>' + mod_strings[1] + '</p>',
            footer: footerHTML,
        }, trigger)
        .done(function(modal) {
            // Do what you want with your new modal.
            Y.log('modal created');
            confirm_modal = modal;
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
        var path = $('#' + targetid).parents('.fitem').find('.filepicker-filename a');
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
                Y.log('length is ' + length);
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
                str.get_strings([
                        {'key': 'importwarningheader', component: 'local_datahub'},
                        {'key': 'importwarningcontent', component: 'local_datahub'},
                        {'key': 'importwarningconfirm', component: 'local_datahub'}
                    ]).done(function(s) {
                        mod_strings = s;
                        console.log(s);
                        build_modal();
                    }).fail(console.log('Loading of strings failed.'));
                bind_events();
            });
        }
    };
});

