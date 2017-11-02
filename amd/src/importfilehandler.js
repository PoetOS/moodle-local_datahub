/* jshint ignore:start */
/**
 *
 * This AMD module provides JavaScript checks the size and length
 * of CSV files to be uploaded using the manual imports interface.
 * If the files are too long to be run manually without risking a
 * timeout, the module presents the user with a warning.
 *
 * @author    Eric Bjella <eric.bjella@remote-learner.net>
 * @copyright 2017 onwards Remote Learner US Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @param Function $ jQuery
 * @param Function log Moodle core JS logging utility
 * @param Function notification Moodle core notification modal
 * @param Function Papa CSV parsing library
 * @return Function initialise Inits for horizontal scroll and filtering functionality.
 **/
define(['jquery', 'core/log', 'core/notification', 'local_datahub/papaparse'], function($, log, notification, Papa) {

    var csvCheckObj = {};
    var validCSV = false;
    var validSchedule = true;

    /**
     * Bind event listeners
     */
    function bindEvents() {
        $('#uploadformwrapper #id_version2importfile').change(function() {
            checkCSV();
            var file = $(this).val();
            $('input[name="queueschedule"]').removeAttr('disabled');
        });

        $('#uploadformwrapper input#id_queueschedule_0').click(function() {
            $('#uploadformwrapper select.timeselect').attr('disabled', 'disabled');
            validSchedule = true;
            clearTimeSelects();
        });
        $('#uploadformwrapper input#id_queueschedule_1').click(function() {
            $('#uploadformwrapper select#id_month').removeAttr('disabled');
            validSchedule = false;
            populateMonthSelect();
        });

        $('#uploadformwrapper select#id_month').change(function() {
            var monthYear = $(this).val();
            // Must have valid MONTH_YEAR format for value.
            if (monthYear != 'choose') {
                populateDaySelect(monthYear);
                $('#uploadformwrapper select#id_day').removeAttr('disabled');
            } else {
                $('#uploadformwrapper select#id_day').attr('disabled', 'disabled');
            }
        });

        $('#uploadformwrapper select#id_day').change(function() {
            var day = $(this).val();
            var monthYear = $("#uploadformwrapper select#id_month").val();
            if (day != 'choose') {
                populateTimeSelect(monthYear, day);
                $('#uploadformwrapper select#id_time').removeAttr('disabled');
            } else {
                $('#uploadformwrapper select#id_time').attr('disabled', 'disabled');
            }
        });

        $('#uploadformwrapper select#id_time').change(function() {
            if ($(this).val() != 'choose') {
                var monthyear = $('#uploadformwrapper select#id_month').val().split('_');
                var day = $('#uploadformwrapper select#id_day').val();
                var hour = $(this).val();
                var queueTime = new Date();
                queueTime.setFullYear(monthyear[1]);
                queueTime.setMonth(monthyear[0]);
                queueTime.setDate(day);
                queueTime.setHours(hour);
                queueTime.setMinutes(0);
                queueTime.setSeconds(0);
                var utcTimestamp = Math.floor(queueTime.getTime()/1000);
                Y.log('utcTimestamp: '+utcTimestamp);
                $('#uploadformwrapper #queuetimestamp').val(utcTimestamp);
                validSchedule = true;
            } else {
                $('#uploadformwrapper #queuetimestamp').val(0);
            }
        });

        // Update timezone message with timezone according to javascript.
        var jstimezone = new Date().toString().match(/([A-Z]+[\+-][0-9]+.*)/)[1];
        if (window.Intl && typeof window.Intl === 'object'){
            // We have Intl so get the timezone.
            var jstimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        }
        $('#timezoneholder').html(jstimezone);

        // Validation interval checker.
        setInterval(function() {
            if (validCSV && validSchedule) {
                $('#uploadformwrapper #id_submit').removeAttr('disabled');
            } else {
                $('#uploadformwrapper #id_submit').attr('disabled', 'disabled');
            }
        }, 500);
    }

    /**
     * Front-end CSV validation of uploaded file.
     */
    function checkCSV() {
        // Build a path to fetch the CSV string from MDL drafts.
        var path = $('#uploadformwrapper .filepicker-filename a');
        path = $(path).attr('href');
        Y.log('Import file selected: '+path);
        // Make an AJAX request.
        var parsedHeaders;
        csv_data = $.ajax({
            type: "GET",
            url: path,
            complete: function(result) {
                Y.log('CSV fetch now complete.');
                var headerRow = result.responseText.split("\n", 1);
                var parsedData = Papa.parse(headerRow[0]);
                var headerValues = parsedData.data[0];
                var headerHash = {};
                // Look for *action type header and compare against validator object.
                for (var i=0; i<headerValues.length; i++) {
                    var header = headerValues[i];
                    if (header.indexOf('action') > -1) {
                        var csvType = header.replace('action', '');
                    }
                    headerHash[header] = 1;
                }

                if (csvType != undefined && csvCheckObj[csvType] != undefined) {
                    // Check that all required fields are in the uploaded CSV.
                    var missingRequiredFields = false;
                    var missingFields = new Array();
                    var enrolmentIDcheckComplete = false;
                    for (var i=0; i<csvCheckObj[csvType].length; i++) {
                        var header = csvCheckObj[csvType][i];
                        if (header.indexOf('*') == 0) {
                            header = header.replace('*', '');
                            // Special case for enrolments where username, email, OR idnumber is required.
                            if (csvType == 'enrolment'
                                    && (header == 'username' || header == 'email' || header == 'idnumber')) {
                                // Only perform this check one to avoid multiple errors/false positive.
                                if (enrolmentIDcheckComplete === false) {
                                    enrolmentIDcheckComplete = true;
                                    if (headerHash['username'] == undefined
                                            && headerHash['email'] == undefined
                                            && headerHash['idnumber'] == undefined) {
                                        missingRequiredFields = true;
                                        missingFields.push('username, email, or idnumber');
                                    }
                                }
                            } else if (headerHash[header] == undefined) {
                                missingRequiredFields = true;
                                missingFields.push(header);
                            }
                        }
                    }

                    // Gather error message(s).
                    var errorMessage = new Array(M.util.get_string('validationerrorimporttype', 'dhimport_version2', csvType));
                    if (missingRequiredFields) {
                        errorMessage.push(M.util.get_string('validationerrormissingrequired', 'dhimport_version2', missingFields.join(', ')));
                    }

                    // Show error message(s).
                    if (missingRequiredFields) {
                        var title = M.util.get_string('validationerrorheader', 'dhimport_version2');
                        var messages = errorMessage.join('<br />');
                        notification.alert(title, messages);
                    } else {
                        validCSV = true;
                    }
                } else {
                    var title = M.util.get_string('validationerrorheader', 'dhimport_version2');
                    var messages = M.util.get_string('validationerrorunknowntype', 'dhimport_version2');
                    notification.alert(title, messages);
                }
            }
        });
    }

    /**
     * Clear month, day, and time selects.
     */
    function clearTimeSelects() {
        $('#uploadformwrapper select.timeselect option:not(:first-child)').remove();
    }

    /**
     * Populates month select when schedule radio is selected.
     */
    function populateMonthSelect() {
        var d = new Date();
        var month = new Array();
        // TODO: localized month strings.
        month[0] = "January";
        month[1] = "February";
        month[2] = "March";
        month[3] = "April";
        month[4] = "May";
        month[5] = "June";
        month[6] = "July";
        month[7] = "August";
        month[8] = "September";
        month[9] = "October";
        month[10] = "November";
        month[11] = "December";
        var curMonth = d.getMonth();
        var curYear = d.getFullYear();
        var options = new Array();
        for (var i=0; i<12; i++) {
            var $option = '<option value="'+curMonth+'_'+curYear+'">'+month[curMonth]+' '+curYear+'</option>';
            $('#uploadformwrapper select#id_month').append($option);
            curMonth++;
            if (curMonth > 11) {
                curMonth = 0;
                curYear++;
            }
        }
    }

    /**
     * Checks if the selected month/year is the current month/year.
     */
    function selectedMonthIsThisMonth(selectedMonthYear) {
        var d = new Date();
        var thisMonth = d.getMonth();
        var thisYear = d.getFullYear();
        var thisMonthYear = thisMonth+'_'+thisYear;
        if (thisMonthYear == selectedMonthYear) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Populates day select when month select is changed.
     * @param string The selected month and year in MM_YYYY format.
     */
    function populateDaySelect(selectedMonthYear) {
        var selectedDate = new Date();
        var dateParts = selectedMonthYear.split('_');
        var selectedMonth = parseInt(dateParts[0]);
        var selectedYear = parseInt(dateParts[1]);
        selectedDate.setFullYear(selectedYear);
        selectedDate.setMonth(selectedMonth+1);
        selectedDate.setDate(0);
        var daysInSelectedMonth = selectedDate.getDate();
        var thisDay = 1;
        if (selectedMonthIsThisMonth(selectedMonthYear)) {
            var d = new Date();
            thisDay = d.getDate();
            daysInMonth = new Date().getDate();
        }
        $('#uploadformwrapper select#id_day option:not(:first-child)').remove();
        $('#uploadformwrapper select#id_time option:not(:first-child)').remove();
        for (var i=thisDay; i<=daysInSelectedMonth; i++) {
            var $option = '<option>'+i+'</option>';
            $('#uploadformwrapper select#id_day').append($option);
        }
    }

    /**
     * Populates day select when month select is changed.
     * @param integer The selected day.
     * @return boolean Whether or not the selected day is today.
     */
    function selectedDayIsThisDay(selectedDay) {
        var d = new Date();
        if (d.getDate() == selectedDay) {
            return true;
        } else {
            return false;
        }
    }

    /*
     * Format time as 12 hour am/pm style string.
     * @param integer The current hour 0-23.
     * @return string The formatted time string.
     */
    function formatTime(hour) {
        var ampm = hour >= 12 ? 'pm' : 'am';
        hour = hour % 12;
        hour = hour ? hour : 12; // The hour '0' should be '12'.
        var minute = '00';
        var strTime = hour+':'+minute+' '+ampm;
        return strTime;
    }

    /**
     * Populates day select when month select is changed.
     * @param string The selected month/year in MM_YYYY format.
     * @param integer The selected day.
     * @return boolean Whether or not the selected day is today.
     */
    function populateTimeSelect(selectedMonthYear, selectedDay) {
        var d = new Date();
        var ampm = 'am';
        var startHour = 0;
        if (selectedMonthIsThisMonth(selectedMonthYear) && selectedDayIsThisDay(selectedDay)) {
            startHour = d.getHours()+1;
        }
        $('#uploadformwrapper select#id_time option:not(:first-child)').remove();
        var hoursInDay = 23;
        for (var hour=startHour; hour<=hoursInDay; hour++) {
            var timeString = formatTime(hour);
            var $option = '<option value="'+hour+'">'+timeString+'</option>';
            $('#uploadformwrapper select#id_time').append($option);
        }
    }

    return {
        /**
         * Initialize CSV template builder
         */
        init: function(csvfiletypes) {
            // Load template CSVs for validation.
            var csvtemplates = JSON.parse(csvfiletypes);
            var csvcount = 0;
            (function loadCSVmodel(){
                var fileType = csvtemplates[csvcount].replace('.csv', '');
                csvCheckObj[fileType] = new Array();
                $.ajax({
                    type: 'GET',
                    url: './templates/csv/'+csvtemplates[csvcount]+'?v=2',
                    dataType: 'text',
                    success: function(data) {
                        var headers = data.split("\n")[0];
                        var fields = headers.split(",");
                        csvCheckObj[fileType] = fields;
                        csvcount++;
                        if (csvcount < csvtemplates.length) {
                            loadCSVmodel();
                        }
                    }
                });
            })();
            $(document).ready(function(){
                bindEvents();
            });
        }
    };
});