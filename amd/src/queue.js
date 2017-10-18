/*
 * @package    local_datahub
 * @copyright  2016 Remote-Learner.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*jslint vars: true, plusplus: true, devel: true, nomen: true, indent: 4, maxerr: 50 */ /*global define */

 /**
  * @module local_datahub/queue
  */
define(['jquery', 'jqueryui', 'core/templates', 'core/notification'],
        function($, jqueryui, templates, notification) {

    /**
     * All timestamps are presumed received from AJAX and passed back
     * as being in format UTC seconds timestamp. (Not milliseconds.)
     */

    // General storage containers.
    var session_key = null;
    // Status for queue. True = paused.
    var queue_paused = 0;
    var queue_state_rendered = false;
    var queue_sortable = {};
    var start_order = [];
    var finish_order = [];
    var completed_start = null;
    var completed_end = null;
    // Log string for appending to displayed feedback.
    var log = '';
    // If true, append log to existing feedback and purge.
    var log_alert = false;
    // Array of timeouts for the 4 feedback elements in page markup.
    var timeouts = [null, null, null, null];
    // Strings that we don't want to fetch twice.
    var datesequence_error = M.util.get_string('queuedateordererror', 'local_datahub');
    var startinvalid_error = M.util.get_string('queuestartinvaliderror', 'local_datahub');
    var endinvalid_error = M.util.get_string('queueendinvaliderror', 'local_datahub');
    var tznotify = M.util.get_string('queuenotimezone', 'local_datahub');
    var nojobs_error = M.util.get_string('queuenojobserror', 'local_datahub');
    var refresh_error = M.util.get_string('queuerefresherror', 'local_datahub');
    var reschedule_error = M.util.get_string('queuerescheduleerror', 'local_datahub');
    var reschedule_success = M.util.get_string('queuereschedulesuccess', 'local_datahub');
    var cancel_error = M.util.get_string('queuecancelerror', 'local_datahub');
    var cancel_success = M.util.get_string('queuecancelsuccess', 'local_datahub');
    var invalid_reschedule_date = M.util.get_string('queueinvalidresched', 'local_datahub');
    var resched_error = M.util.get_string('queuedragrescheduleerror', 'local_datahub');
    var resched_success = M.util.get_string('queuedragreschedulesuccess', 'local_datahub');
    var resched_nochange = M.util.get_string('queuedragnochange', 'local_datahub');
    var pause_error = M.util.get_string('queuepauseerror', 'local_datahub');
    var pause_success = M.util.get_string('queuepausesuccess', 'local_datahub');
    var unpause_error = M.util.get_string('queueunpauseerror', 'local_datahub');
    var unpause_success = M.util.get_string('queueunpausesuccess', 'local_datahub');

    /**
     * Sets timezone display to inform user client
     * time zone is being used.
     */
    function set_timezone_display() {
        // Can only do this in current browsers
        // that support the Internationalization API.
        if (window.Intl && typeof window.Intl === 'object'){
            // We have Intl so get the timezone.
            var clienttz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            // Sometimes returned undefined so verify.
            if (clienttz) {
                tznotify = M.util.get_string('queuetimezone', 'local_datahub', clienttz);
            }
        }
        $('#timezone_alert p').text(tznotify);
    }

    /**
     * Pads a numeric value for use as a date in an iso string or
     * other format.
     * @param  Number value Numeric value to convert, pad, and slice.
     * @return String       The string produced by padding and slicing.
     */
    function pad_value(value) {
        return ('0' + String(value)).slice(-2);
    }

    /**
     * Show spinner indicating that table data is loading.
     * @param  string section Section to show or hide, either "queue" or "completed"
     * @return none
     */
    function show_loading_state(section) {
        // Y.log('show_loading_state for section ' + section);
        $('.local_datahub_' + section + ' #loading_indicator').addClass('loading');
    }

    /**
     * Hide spinner indicating that table data is loading.
     * @param  string section Section to show or hide, either "queue" or "completed"
     * @return none
     */
    function hide_loading_state(section) {
        // Y.log('hide_loading_state for section ' + section);
        $('.local_datahub_' + section + ' #loading_indicator').removeClass('loading');
    }

    /**
     * Show indicator while reschedule request is running.
     * @param  object row jQuery object of the selected row.
     * @return none
     */
    function show_reschedule_indicator(row) {
        // Y.log('show_reschedule_indicator');
        row.find('.reschedule-indicator').addClass('loading');
    }

    /**
     * Remove indicator shown while reschedule request is running.
     * @param  object row jQuery object of the selected row.
     * @return none
     */
    function hide_reschedule_indicator(row) {
        row.find('.reschedule-indicator').removeClass('loading');
    }

    /**
     * Show indicator while pause jobs request is running.
     * @return none
     */
    function show_pause_indicator() {
        // Y.log('show_pause_indicator');
        $('#pausecontinue_parent .processing-indicator').addClass('loading');
    }

    /**
     * Hide indicator once pause jobs request is done running.
     * @return none
     */
    function hide_pause_indicator() {
        $('#pausecontinue_parent .processing-indicator').removeClass('loading');
    }

    function get_well_index(well) {
        var wells = $('.queue_alert, .queue_success');
        return wells.index(well);
    }

    /**
     * Show positive and negative feedback.
     * Also hide the feedback after an interval if the user
     * hasn't closed it.
     * @param  string type     Type of notification, either alert or success.
     * @param  string msg      Message to display in alert.
     * @param  object tableobj jQuery object of selected table if it's not the query table.
     * @return none
     */
    function show_feedback(type, msg, tableobj) {
        // Y.log('show_feedback');
        if (!tableobj) {
            // Y.log('no table object');
            tableobj = $('.local_datahub_queue');
        }
        if (log_alert === true) {
            // If log contains alert content,
            // change type to alert.
            type = 'alert';
            // Reset alert tracker.
            log_alert = false;
        }
        if (log.length >= 1) {
            // If log has content, append to msg.
            msg = log + msg;
            // Clear log.
            log = '';
        }
        // If we have content, then display the feedback.
        if (msg.length >= 1) {
            // Insert content.
            tableobj.find('.queue_' + type + '_msg').html(msg);
            // Fetch well and well index.
            var well = tableobj.find('.queue_' + type);
            var index = get_well_index(well);
            // If shown, hide and show. If hidden, show.
            if (well.hasClass('show')) {
                well.removeClass('show');
                setTimeout(function() {
                    well.addClass('show');
                }, 400);
            } else {
                well.addClass('show');
            }
            // Clear any existing timeout.
            if (timeouts[index]) {
                clearInterval(timeouts[index]);
            }
            // Set timeout.
            timeouts[index] = setTimeout(function() {
                // Hide it after 45 seconds if the user hasn't.
                if (well.hasClass('show')) {
                    well.removeClass('show');
                }
            }, 30000);
        }
    }

    function hide_feedback(type, tableobj) {
        if (!tableobj) {
            // Y.log('no table object');
            tableobj = $('.local_datahub_queue');
        }
        var well = tableobj.find('.queue_' + type);
        if (well.hasClass('show')) {
            well.removeClass('show');
        }
    }

    /**
     * Checks for font-awesome in the page and if it's not there
     * injects the link into the page head.
     * @return none
     */
    function check_fontawesome() {
        var span = document.createElement('span');
        span.className = 'fa';
        span.style.display = 'none';
        document.body.insertBefore(span, document.body.firstChild);
        function css(element, property) {
            return window.getComputedStyle(element, null).getPropertyValue(property);
        }
        if (css(span, 'font-family') !== 'FontAwesome') {
            var fa_tag = '<link href="//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">';
            $('head').children('script, link').last().after(fa_tag);
        }
        document.body.removeChild(span);
    }

    /**
     * Build timestamp using the form fields in the designated row.
     * @param  object row           jQuery object of the row
     * @return string schedulestamp UTC timestamp
     */
    function build_timestamp(row) {
        // Y.log('build_timestamp()');
        var now = Date.now();
        var pickerval = row.find('input.reschedule_task').val();
        var parse = Date.parse(pickerval);
        // Y.log('parsed pickerval = ' + parse);
        // If time is invalid or is before current time, then error.
        // Otherwise we build the timestamp and send it back.
        if (parse <= now || isNaN(parse)) {
            return false;
        } else {
            return parse / 1000;
        }
    }

    /**
     * Govern standard behavior of rescheduling radio buttons.
     * @return none
     */
    function govern_reschedule_radios() {
        // Y.log('govern_reschedule_radios');
        var radios = $('#queue_job_table .job-reschedule-row input[type="radio"]');
        radios.change(function(e) {
            // If the changed input is checked and
            // the changed input indicates schedule,
            // then enable the neighboring datetime selects.
            var dateinput = $(e.currentTarget).parents('form').find('.queue_date_select input');
            if ($(e.currentTarget).is(':checked') &&
                $(e.currentTarget).val() == 'schedule') {
                dateinput.prop("disabled", false);
            } else {
                dateinput.prop("disabled", true);
            }
        });
    }

    /**
     * Govern feedback close buttons.
     * @return none
     */
    function govern_feedback_buttons() {
        // When close is clicked, hide and clear contents.
        $('.queue_alert button.close, .queue_success button.close').on('click', function(e) {
            // Y.log('clicked feedback button');
            e.stopPropagation();
            $(e.currentTarget).parents('.alert').removeClass('show');
        });
    }

    function get_now_isostring(includetime) {
        var now = Date.now();
        var d = new Date(now);
        var iso = d.getFullYear() + '-' + pad_value(d.getMonth() + 1) + '-' + pad_value(d.getDate());
        // If we want hours and minutes
        // then we add them.
        if (includetime) {
            iso += 'T' + pad_value(d.getHours() + 1) + ':' + pad_value(d.getMinutes());
        }
        return iso;
    }

    function get_reschedule_minmax(type) {
        // Y.log('get_reschedule_minmax for ' + type);
        var now = Date.now(); // Now timestamp.
        var d = new Date(now); // Now date object.
        if (type == 'min') {
            return d.getFullYear() + '-' + pad_value(d.getMonth() + 1) + '-' + pad_value(d.getDate()) + 'T00:00';
        } else {
            return (d.getFullYear() + 5) + '-' + pad_value(d.getMonth() + 1) + '-' + pad_value(d.getDate()) + 'T00:00';
        }
    }

    function update_reschedule_dates() {
        // Y.log('update_date_display');
        var max = get_reschedule_minmax('max');
        // Y.log('reschedule max = ' + max);
        var min = get_reschedule_minmax('min');
        // Y.log('reschedule min = ' + min);
        $('input.reschedule_task').attr('max', max);
        $('input.reschedule_task').attr('min', min);
        $('input.reschedule_task').attr('value', get_now_isostring(true));
    }

    /**
     * Check queue state passed from PHP. If paused,
     * simulate a click to correctly convey paused state.
     * @return none
     */
    function check_queue_state() {
        // If queue state is 1 and queue pause state hasn't
        // been checked for this page load, simulate click.
        if (queue_paused && !queue_state_rendered) {
            $('#pause_scheduled').click();
            queue_state_rendered = true;
        }
    }

    /**
     * Render jobs queue.
     * @param  object data JSON object with jobs information.
     * @return none
     */
    function renderjobsqueue(obj, existingjobs) {
        // Y.log('renderjobsqueue');
        var info = obj.data.pop();
        if (info.status) {
            queue_paused = true;
        } else {
            queue_paused = false;
        }
        $('#pause_scheduled').attr('status', queue_paused);
        if (obj.data.length <= 0) {
            // Y.log('There are no queue jobs to show.');
            var nojobsdata = {'type': 'queue'};
            templates.render('local_datahub/nojobs', nojobsdata)
                .done(function(newhtml) {
                    // console.log(newhtml);
                    existingjobs.remove();
                    $('.table.jobs .insert-inside').html(newhtml);
                    add_default_listeners();
                    govern_reschedule_radios();
                    init_draggables();
                    show_feedback('alert', nojobs_error);
                    check_queue_state();
                })
                .fail(notification.exception);
        } else {
            // Render jobs.
            templates.render('local_datahub/queuejobs', obj)
                .done(function(newhtml) {
                    // console.log(newhtml);
                    existingjobs.remove();
                    $('.table.jobs .insert-inside').html(newhtml);
                    add_default_listeners();
                    govern_reschedule_radios();
                    init_draggables();
                    show_feedback('success', '');
                    update_reschedule_dates();
                    check_queue_state();
                })
                .fail(notification.exception);
        }
    }

    /**
     * Fetch the fresh list of jobs for the queue.
     * @return none
     */
    function refresh_jobs() {
        // Y.log('refresh_jobs');
        // If there are existing jobs, remove them.
        var existingjobs = $('.table.jobs .job-row, .table.jobs .job-reschedule-row');
        if (existingjobs.length >= 1) {
            existingjobs.hide();
        }
        show_loading_state('queue');
        // Get basic data for rendering the students list.
        $.ajax({
            method: "GET",
            url: M.cfg.wwwroot +
                    '/local/datahub/importplugins/version2/ajax.php?mode=getqueuelist' +
                    '&sesskey=' + session_key,
            dataType: 'json',
            timeout: 30000,
            data: {},
            error: function(req, resulttype, exc) {
                Y.log(req + ' ' + resulttype + ' ' + exc);
                hide_loading_state('queue');
                show_feedback('alert', refresh_error);
                existingjobs.show();
            },
            success: function(data) {
                var obj = {};
                obj = data;
                // console.log('refresh jobs success, logging object');
                // console.log(obj);
                if (obj && obj.success === true) {
                    // Y.log('pause success');
                    // Success!
                    hide_loading_state('queue');
                    renderjobsqueue(obj, existingjobs);
                } else {
                    // Y.log('pause fail');
                    // Show error text in the page.
                    hide_loading_state('queue');
                    existingjobs.show();
                    show_feedback('alert', refresh_error);
                }
            }
        });
    }

    /**
     * Store the starting jobs order.
     */
    function set_start_order() {
        // Y.log('set_start_order');
        start_order = [];
        var rows = $('#queue_job_table tr[data-type="processing"], ' +
                '#queue_job_table tr[data-type="waiting"]');
        // Y.log('logging start_order');
        // Y.log(rows);
        rows.each(function(i, row) {
            var taskid = $(row).attr('data-job-id');
            start_order.push(taskid);
        });
        // Y.log(start_order);
    }

    /**
     * Compare sort order for start and stop.
     * @param  Array   stop        Array of stop order.
     * @return Boolean sortchanged Boolean of whether or not sort order has changed.
     */
    function check_sort_order(rows) {
        // Y.log('check_sort_order');
        finish_order = [];
        rows.each(function(i, row) {
            var taskid = $(row).attr('data-job-id');
            finish_order.push(taskid);
        });
        var sortchanged = false;
        for (var i=0; i<=finish_order.length; i++) {
            if (start_order[i] !== finish_order[i]) {
                sortchanged = true;
                // Y.log('sort order changed');
            }
        }
        return sortchanged;
    }

    /**
     * Unpause processing of jobs and set up default listeners
     * if successful.
     * @param string affix String to be appended to existing feedback.
     * @return none
     */
    function unpause_processing(refresh) {
        // Y.log('enable_processing');
        // Avoid unset params.
        if (!refresh) {
            // Y.log('refresh not set');
            refresh = false;
        }
        // Call AJAX to unpause.
        $.ajax({
            method: "GET",
            // Toggle true or false response to test.
            url: M.cfg.wwwroot + '/local/datahub/importplugins/version2/ajax.php?mode=pausequeue' +
                    '&enabled=0' +
                    '&sesskey=' + session_key,
            dataType: 'json',
            timeout: 30000,
            data: {},
            error: function(req, resulttype, exc) {
                Y.log(req + ' ' + resulttype + ' ' + exc);
                // Show error text in the page,
                // or pass output on to refresh function.
                if (refresh) {
                    log = log + unpause_error + '<br>';
                    log_alert = true;
                    refresh_jobs();
                } else {
                    show_feedback('alert', unpause_error);
                }
                hide_pause_indicator();
                remove_drag_listeners();
            },
            success: function(data) {
                var obj = {};
                obj = data;
                // console.log(obj);
                if (obj && obj.success === true) {
                    // Y.log('unpause success');
                    // Success!
                    // Show error text in the page,
                    // or pass output on to refresh function.
                    if (refresh) {
                        log = log + unpause_success + '<br>';
                        refresh_jobs();
                    } else {
                        show_feedback('success', unpause_success);
                    }
                    hide_pause_indicator();
                    remove_drag_listeners();
                } else {
                    // Y.log('unpause fail');
                    // Show error text in the page,
                    // or pass output on to refresh function.
                    if (refresh) {
                        log = log + unpause_error + '<br>';
                        log_alert = true;
                        refresh_jobs();
                    } else {
                        show_feedback('alert', unpause_error);
                    }
                    hide_pause_indicator();
                    remove_drag_listeners();
                }
            }
        });
    }

    /**
     * Build and send new job order via AJAX.
     * @return none
     */
    function reorder_jobs() {
        // Y.log('reorder_jobs');
        // If there is a reordered class on the table
        var rows = $('#queue_job_table tr[data-type="processing"], ' +
                '#queue_job_table tr[data-type="waiting"]');
        var order_is_changed = check_sort_order(rows);
        // then build an array of IDs in order.
        if (!!order_is_changed && rows.length >= 1) {
            $.ajax({
                type: 'GET',
                url: M.cfg.wwwroot + '/local/datahub/importplugins/version2/ajax.php?mode=reorderqueue' +
                        '&sesskey=' + session_key +
                        '&order=' + finish_order.toString(),
                dataType: 'json',
                timeout: 30000,
                // data: JSON.stringify({paramName: finish_order}),
                error: function(req, resulttype, exc) {
                    Y.log(req + ' ' + resulttype + ' ' + exc);
                    // Show error text in the page.
                    hide_pause_indicator();
                    remove_drag_listeners();
                    // Add output to logs.
                    log = log + resched_error + '<br>';
                    log_alert = true;
                    // Call unpause with refresh set to truel
                    unpause_processing(true);
                },
                success: function(data) {
                    var obj = {};
                    obj = data;
                    // console.log(obj);
                    // renderjobsqueue(obj);
                    if (obj && obj.success === true) {
                        // Y.log('pause success');
                        // Success!
                        // Call function to do drag listening.
                        hide_pause_indicator();
                        // Add output to logs.
                        log = log + resched_success + '<br>';
                        // Call unpause with refresh set to true;
                        unpause_processing(true);
                        remove_drag_listeners();
                    } else {
                        // Y.log('pause fail');
                        // Show error text in the page.
                        hide_pause_indicator();
                        // Add output to logs.
                        log = log + resched_error + '<br>';
                        log_alert = true;
                        // Call unpause with refresh set to true.
                        unpause_processing(true);
                        remove_drag_listeners();
                    }
                }
            });
        } else {
            hide_pause_indicator();
            // Unpause processing.
            log = log + resched_nochange + '<br>';
            log_alert = true;
            unpause_processing(false);
        }
    }

    /**
     * Pause processing of jobs and set up drag listeners
     * if successful.
     * @return none
     */
    function pause_processing() {
        // Y.log('pause_processing');
        // Call AJAX to pause.
        $.ajax({
            method: "GET",
            // Toggle true or false response to test.
            url: M.cfg.wwwroot + '/local/datahub/importplugins/version2/ajax.php?mode=pausequeue' +
                    '&enabled=1' +
                    '&sesskey=' + session_key,
            dataType: 'json',
            timeout: 30000,
            data: {},
            error: function(req, resulttype, exc) {
                Y.log(req + ' ' + resulttype + ' ' + exc);
                // Show error text in the page.
                show_feedback('alert', pause_error);
                hide_pause_indicator();
                add_default_listeners();
            },
            success: function(data) {
                var obj = {};
                obj = data;
                // console.log(obj);
                if (obj && obj.success === true) {
                    // Y.log('pause success');
                    // Success!
                    // Call function to do drag listening.
                    show_feedback('success', pause_success);
                    hide_pause_indicator();
                    add_drag_listeners();
                } else {
                    // Y.log('pause fail');
                    // Show error text in the page.
                    show_feedback('alert', pause_error);
                    hide_pause_indicator();
                    add_default_listeners();
                }
            }
        });
    }

    /**
     * Save rescheduled task, wait for response.
     * @param  object row   jQuery row object.
     * @param  string jobid String of the job id.
     * @return none
     */
    function save_reschedule(row, jobid) {
        // Y.log('save_reschedule()');
        // Fades all controls.
        row.children('p, label, .queue_date_select').css('opacity', '0.5');
        // Shows processing icon.
        show_reschedule_indicator(row);
        // Build datestring and fetch timestamp.
        var now = row.find('input[value="runnow"]:checked');
        var timestamp = null;
        if (now.length >= 1) {
            // Y.log('the run now radio is checked');
            timestamp = Math.floor(Date.now() / 1000);
        } else {
            timestamp = build_timestamp(row, jobid);
        }
        // Y.log('timestamp is ' + timestamp);
        if (timestamp) {
            // Call AJAX to pause.
            $.ajax({
                method: "GET",
                // Toggle true or false response to test.
                url: M.cfg.wwwroot +
                     '/local/datahub/importplugins/version2/ajax.php?mode=reschedule' +
                     '&sesskey=' + session_key +
                     '&itemid=' + jobid +
                     '&time=' + timestamp,
                dataType: 'json',
                timeout: 30000,
                data: {},
                error: function(req, resulttype, exc) {
                    Y.log(req + ' ' + resulttype + ' ' + exc);
                    // Show error text in the page.
                    show_feedback('alert', reschedule_error);
                    // Hide processing indicator.
                    hide_reschedule_indicator(row);
                    // Set control opacity back to normal.
                    row.children('p, label, .queue_date_select').css('opacity', '1');
                    // Reset normal controls.
                    add_default_listeners();
                },
                success: function(data) {
                    var obj = {};
                    obj = data;
                    // Y.log(obj);
                    // Hide the panel.
                    row.hide();
                    if (obj && obj.success === true) {
                        // Success!
                        refresh_jobs();
                        show_feedback('success', reschedule_success);
                        hide_reschedule_indicator(row);
                    } else {
                        refresh_jobs();
                        // Show error text in the page.
                        show_feedback('alert', reschedule_error);
                        // Hide processing indicator.
                        hide_reschedule_indicator(row);
                        // Set control opacity back to normal.
                        row.children('p, label, .queue_date_select').css('opacity', '1');
                    }
                    // Reset normal controls.
                    add_default_listeners();
                }
            });
        } else {
            // Timestamp is invalid so we just sit here and
            // let the user enter a different one.
            show_feedback('alert', invalid_reschedule_date);
            // Hide processing indicator.
            hide_reschedule_indicator(row);
        }
    }

    /**
     * Cancel job.
     * @param  object row   jQuery row object.
     * @param  string jobid String of the job id.
     * @return none
     */
    function cancel_job(row, jobid) {
        // Y.log('cancel_job');
        // Show loading indicator.
        show_reschedule_indicator(row);
        // Call AJAX to pause.
        $.ajax({
            method: "GET",
            // Toggle true or false response to test.
            url: M.cfg.wwwroot + '/local/datahub/importplugins/version2/ajax.php?mode=cancelitem' +
                    '&sesskey=' + session_key +
                    '&itemid=' + jobid,
            dataType: 'json',
            timeout: 30000,
            data: {},
            error: function(req, resulttype, exc) {
                Y.log(req + ' ' + resulttype + ' ' + exc);
                // Show error text in the page.
                show_feedback('alert', cancel_error);
                add_default_listeners();
                hide_reschedule_indicator(row);
            },
            success: function(data) {
                var obj = {};
                obj = data;
                // console.log(obj);
                if (obj && obj.success === true) {
                    // Success!
                    hide_reschedule_indicator(row);
                    refresh_jobs();
                    show_feedback('success', cancel_success);
                } else {
                    // Show error text in the page.
                    hide_reschedule_indicator(row);
                    show_feedback('alert', cancel_error);
                    add_default_listeners();
                }
            }
        });
    }

    function remove_reschedule_listeners(jobid) {
        $('*[data-reschedule-id="' + jobid + '"] .queue-date-cancel, ' +
            '*[data-reschedule-id="' + jobid + '"] .queue-date-save').unbind('click');
    }

    function add_reschedule_listeners(jobid) {
        // Y.log('add_reschedule_listeners for ' + jobid);
        // Cancel button.
        var cancelbtn = $('*[data-reschedule-id="' + jobid + '"] .queue-date-cancel');
        var savebtn = $('*[data-reschedule-id="' + jobid + '"] .queue-date-save');
        var row = $('*[data-reschedule-id="' + jobid + '"]');
        // Hides the row and removes listeners.
        cancelbtn.on('click', function() {
            // Y.log('cancel clicked');
            // Y.log(cancelbtn.parent('job-reschedule-row'));
            row.hide();
            remove_reschedule_listeners(jobid);
            add_default_listeners();
        });
        // Save button.
        savebtn.on('click', function() {
            // Y.log('save clicked');
            save_reschedule(row, jobid);
        });
    }

    function add_drag_listeners() {
        // Y.log('add_drag_listeners');
        set_start_order();
        finish_order = [];
        // Add classes to manage active states.
        $('#queue_job_table').addClass('drag-active');
        $('#pause_scheduled').prop('disabled', 'disabled');
        $('#continue_scheduled').removeAttr('disabled').on('click', function(e) {
            e.stopImmediatePropagation();
            show_pause_indicator(e.currentTarget);
            // Clear feedback.
            hide_feedback('alert', $('.local_datahub_queue'));
            hide_feedback('success', $('.local_datahub_queue'));
            reorder_jobs();
        });
        queue_sortable.sortable('enable');
    }

    function remove_drag_listeners() {
        // Y.log('remove_drag_listeners');
        // Remove classes to manage active states.
        $('#queue_job_table').removeClass('drag-active');
        // Remove drag listeners.
        $('#continue_scheduled').prop('disabled', 'disabled');
        $('#continue_scheduled').unbind('click');
        $('#pause_scheduled').removeAttr('disabled');
        queue_sortable.sortable('disable');
        add_default_listeners();
    }

    function init_draggables() {
        // Y.log('init_draggables');
        queue_sortable = $('#queue_job_table tbody.draggable-parent').sortable({
            axis: 'y',
            disabled: true,
            handle: 'i.drag-job',
            items: 'tr.draggable'
        });
    }

    function add_default_listeners() {
        // Y.log('add listeners');
        // Pause.
        $('#pause_scheduled').on('click', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            // Show processing icon.
            show_pause_indicator(e.currentTarget);
            // Clear feedback.
            hide_feedback('alert', $('.local_datahub_queue'));
            hide_feedback('success', $('.local_datahub_queue'));
            // Remove all listeners.
            remove_default_listeners();
            // Call pause processing.
            pause_processing();
        });
        // Reschedule.
        $('.reschedule_job').on('click', function(e) {
            e.preventDefault();
            // Clear feedback.
            hide_feedback('alert', $('.local_datahub_queue'));
            hide_feedback('success', $('.local_datahub_queue'));
            // Remove listeners.
            remove_default_listeners();
            // Get the job id.
            var jobid = $(e.currentTarget).attr('data-id');
            // Add cancel and save listeners.
            add_reschedule_listeners(jobid);
            // Call reschedule, sending the job id.
            $('*[data-reschedule-id="'+ jobid +'"]').show();
        });
        // Cancel job.
        $('.cancel_job').on('click', function(e) {
            e.preventDefault();
            // Clear feedback.
            hide_feedback('alert', $('.local_datahub_queue'));
            hide_feedback('success', $('.local_datahub_queue'));
            // Remove listeners.
            remove_default_listeners();
            // Get the job id.
            var jobid = $(e.currentTarget).attr('data-id');
            var row = $(e.currentTarget).parents('.job-row');
            // Call cancel, sending the job id.
            cancel_job(row, jobid);
        });
    }

    function remove_default_listeners() {
        // Y.log('remove listeners');
        // Pause all files.
        $('#pause_scheduled, .cancel_job, .reschedule_job').unbind('click');
        $('#pause_scheduled').unbind('click');
    }

    /* Completed tasks table variables and functions here. */

    function add_complete_listeners() {
        // Click event for the refresh button.
        $('#completed_refresh').on('click', function() {
            hide_feedback('alert', $('.local_datahub_completed'));
            hide_feedback('success', $('.local_datahub_completed'));
            update_date_objects(datesequence_error, startinvalid_error, endinvalid_error);

        });
    }

    function remove_complete_listeners() {
        $('#completed_refresh').unbind('click');
    }

    function update_date_objects(datesequence_error, startinvalid_error, endinvalid_error) {
        // Y.log('update_date_objects()');
        // Fetch the ISOs from the fields.
        var startfield = Date.parse($('#completed_startdate').val());
        var endfield = Date.parse($('#completed_enddate').val());
        // Validate timestamps.
        if (isNaN(startfield)) {
            show_feedback('alert', startinvalid_error, $('.local_datahub_completed'));
            return false;
        }
        if (isNaN(endfield)) {
            show_feedback('alert', endinvalid_error, $('.local_datahub_completed'));
            return false;
        }
        if (startfield <= endfield) {
            // Start is before end, proceed.
            // Update both date objects with the current field data.
            var s = new Date(startfield);
            // completed_start.setTime(s.getTime());
            completed_start = new Date(s.getUTCFullYear(), s.getUTCMonth(), s.getUTCDate());
            var e = new Date(endfield);
            // completed_end.setTime(e.getTime());
            completed_end = new Date(e.getUTCFullYear(), e.getUTCMonth(), e.getUTCDate(),  23, 59, 59);
            // Refresh the display with the new data.
            refresh_complete();
            remove_complete_listeners();
        } else {
            // Start is not before end. Do not proceed.
            show_feedback('alert', datesequence_error, $('.local_datahub_completed'));
        }
    }

    function get_datepicker_minmax(type) {
        // Y.log('get_datepicker_minmax for ' + type);
        var now = Date.now(); // Now timestamp.
        var d = new Date(now); // Now date object.
        if (type == 'min') {
            return (d.getFullYear() - 5) + '-' + pad_value(d.getMonth() + 1) + '-' + pad_value(d.getDate());
        } else {
            return d.getFullYear() + '-' + pad_value(d.getMonth() + 1) + '-' + pad_value(d.getDate());
        }
    }

    function update_date_display() {
        // Y.log('update_date_display');
        var max = get_datepicker_minmax('max');
        var min = get_datepicker_minmax('min');
        var startdatestring = completed_start.getFullYear() + '-' +
            ('0' + String(completed_start.getMonth() + 1)).slice(-2) + '-' +
            ('0' + String(completed_start.getDate())).slice(-2);
        var enddatestring = completed_end.getFullYear() + '-' +
            ('0' + String(completed_end.getMonth() + 1)).slice(-2) + '-' +
            ('0' + String(completed_end.getDate())).slice(-2);
        $('#completed_startdate').attr('value', startdatestring);
        $('#completed_enddate').attr('value', enddatestring);
        $('#completed_startdate, #completed_enddate').attr('max', max);
        $('#completed_startdate, #completed_enddate').attr('min', min);
    }

    function rendercompletequeue(obj, existingcomplete) {
        // Y.log('rendercompletequeue');
        completed_start = new Date(obj.start * 1000);
        completed_end = new Date(obj.end * 1000);
        // update_date_display();
        hide_loading_state('completed');
        if (obj.data.length <= 0) {
            // Y.log('no jobs to show');
            var data = {'type': 'completed'};
            templates.render('local_datahub/nojobs', data)
                .done(function(newhtml) {
                    // There are no jobs to show.
                    $('.table.completed-jobs .insert-inside').html(newhtml);
                    existingcomplete.remove();
                    add_complete_listeners();
                    show_feedback('alert', nojobs_error, $('.local_datahub_completed'));
                    update_date_display();
                })
                .fail(notification.exception);

        } else {
            // Render jobs.
            templates.render('local_datahub/completejobs', obj)
                .done(function(newhtml) {
                    // console.log(newhtml);
                    existingcomplete.remove();
                    add_complete_listeners();
                    $('.table.completed-jobs .insert-inside').html(newhtml);
                    update_date_display();
                })
                .fail(notification.exception);
        }
    }

    /**
     * Set the global variables for the default time range
     * for the completed table as date objects.
     * @return none
     */
    function set_default_completed_range() {
        // console.log('default_completed_range');
        var n = Date.now();
        if (!completed_end) {
            completed_end = new Date(n);
        }
        if (!completed_start) {
            // One week before now.
            n = n - (7*24*60*60*1000);
            completed_start = new Date(n);
        }
    }

    /**
     * Refresh completed jobs list.
     * @return none
     */
    function refresh_complete() {
        // console.log('refresh_complete');
        // If there are existing jobs, remove them.
        var existingcomplete = $('.table.completed-jobs .job-row');
        if (existingcomplete.length >= 1) {
            existingcomplete.hide();
        }
        show_loading_state('completed');
        var startstamp = String(Math.floor(completed_start/1000));
        var endstamp = String(Math.floor(completed_end/1000));
        // Y.log('startstamp = ' + startstamp + ' , endstamp = ' + endstamp);
        // Get basic data for rendering the students list.
        $.ajax({
            method: "GET",
            url: M.cfg.wwwroot +
                    '/local/datahub/importplugins/version2/ajax.php?mode=getcompleted' +
                    '&sesskey=' + session_key +
                    '&start=' + startstamp +
                    '&end=' + endstamp,
            dataType: 'json',
            timeout: 30000,
            data: {},
            error: function(req, resulttype, exc) {
                Y.log(req + ' ' + resulttype + ' ' + exc);
                hide_loading_state('completed');
                existingcomplete.show();
                show_feedback('alert', refresh_error, $('.local_datahub_completed'));
            },
            success: function(data) {
                var obj = {};
                obj = data;
                // console.log('refresh_complete, logging object');
                // console.log(obj);
                if (obj && obj.success === true) {
                    // Y.log('refresh_complete AJAX successful');
                    // Success!
                    hide_loading_state('completed');
                    rendercompletequeue(obj, existingcomplete);
                } else {
                    // Y.log('pause fail');
                    // Show error text in the page.
                    hide_loading_state('completed');
                    existingcomplete.show();
                    show_feedback('alert', refresh_error, $('.local_datahub_completed'));
                }
            }
        });
    }

    /* End completed tasks table variables and functions. */

    /**
     * Set up on page load.
     * @return none
     */
    function setup() {
        // Y.log('setup');
        set_default_completed_range();
        // Check for font awesome.
        check_fontawesome();
        // Set timezone display.
        set_timezone_display();
        // Refresh jobs.
        refresh_jobs();
        // Refresh completed jobs.
        refresh_complete();
        // Govern click to close button on alerts.
        govern_feedback_buttons();
    }

    //
    // Inits for block interface
    //
    return /** @alias module:local_datahub/queue/init */ {

        /**
         * init
         * @access public
         */
        init: function(sesskey, paused) {
            session_key = sesskey;
            queue_paused = paused;
            Y.log('Queue jobs list initialized.');
            $(document).ready(function(){
                setup();
            });
        }
    };
});