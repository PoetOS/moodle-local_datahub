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
 * @param Function notification Moodle core modal JS API.
 * @return Function initialise Inits for horizontal scroll and filtering functionality.
 **/
define(['jquery', 'core/log'], function($, log) { //'core/modal_factory', ModalFactory,

    /**
     * Bind event listeners
     */
    function bindEvents() {
        // Set up CSV modal.
        var $modalplaceholder = $("#csvmodal");
        var csvmodal = new M.core.dialogue({
            headerContent: '',
            bodyContent: $modalplaceholder.html(),
            draggable: true,
            visible: false,
            center: true,
            modal: true,
            width: 800
        });

        // Remove the original markup so we don't bind events to the wrong elements.
        $modalplaceholder.remove();

        // CSV template builder button launches CSV template modal.
        $('#csvtemplatelauncher').on('click', function(event) {
            csvmodal.show();
            event.preventDefault();
        });

        // CSV template type select bindings.
        $('#csvtemplatebuilder #csvtemplatetypeselect').on('change', function() {
            var file = $(this).val();
            Y.log("selected template file: "+file);
            $("#removecsvfield").attr("disabled", "disabled");
            $("#addcsvfield").attr("disabled", "disabled");
            $("#csvtemplatedownload").attr("disabled", "disabled");
            loadCSV(file);
        });

        // Add fields to template fields functionality.
        $('#csvtemplatebuilder #addselect').on('change', function() {
            var fields = $(this).val();
            if (fields != null && fields.length > 0) {
                $("#addcsvfield").removeAttr("disabled");
            } else {
                $("#addcsvfield").attr("disabled", "disabled");
            }
        });
        $('#addcsvfield').on('click', function() {
            var fields = $("#csvtemplatebuilder #addselect").val();
            for (var i=0; i<fields.length; i++) {
                var field = fields[i];
                $("#csvtemplatebuilder #addselect option[value='"+field+"']").appendTo($("#csvtemplatebuilder #removeselect"));
            }
            $("#csvtemplatebuilder #removeselect").trigger('change');
            $("#csvtemplatebuilder #addselect").trigger('change');
            setCSVfields();
        });

        // Remove fields from template fields functionality.
        $('#csvtemplatebuilder #removeselect').on('change', function() {
            var fields = $(this).val();
            if (fields != null && fields.length > 0) {
                $("#removecsvfield").removeAttr("disabled");
            } else {
                $("#removecsvfield").attr("disabled", "disabled");
            }
        });
        $('#removecsvfield').on('click', function() {
            var fields = $("#csvtemplatebuilder #removeselect").val();
            for (var i=0; i<fields.length; i++) {
                var field = fields[i];
                $("#csvtemplatebuilder #removeselect option[value='"+field+"']").appendTo($("#csvtemplatebuilder #addselect"));
            }
            $("#csvtemplatebuilder #removeselect").trigger('change');
            $("#csvtemplatebuilder #addselect").trigger('change');
            setCSVfields();
        });
    }

    /**
     * Sets CSV fields value to hidden input when changes are made.
     */
    function setCSVfields() {
        $csvfields = $('#removeselect option');
        var fields = {};
        $csvfields.each(function() {
            fields[$(this).val()] = 1;
        });
        Y.log('Current CSV fields:');
        Y.log(fields);
        $("form#csvbuilder input[name='fields']").val(JSON.stringify(fields));
        $('#csvtemplatedownload').removeAttr('disabled');
    }

    /*
     * Handle loading of CSV files/fields.
     */
    function loadCSV(file) {
        $('#csvtemplatebuilder #removeselect, #csvtemplatebuilder #addselect').html('');
        if (file) {
            $.ajax({
                type: 'GET',
                url: './templates/csv/'+file+'?v=1',
                dataType: 'text',
                success: function(data) {
                    var headers = data.split("\n")[0];
                    var fields = headers.split(",");
                    $("form#csvbuilder input[name='file']").val(file);
                    populateCSVfields(fields);
                }
            });
        }
    }

    /*
     * Populate the CSV fields in the tempalte builder interface.
     */
    function populateCSVfields(fields) {
        for (var i=0; i<fields.length; i++) {
            var field = fields[i];
            // Check for *required fields.
            if (field.substr(0, 1) == "*") {
                $("#csvtemplatebuilder #removeselect").append('<option disabled>'+field+'</option>');
            } else {
                $("#csvtemplatebuilder #addselect").append('<option value="'+field+'">'+field+'</option>');
            }
        }
        setCSVfields();
    }

    return {
        /**
         * Initialize CSV template builder
         */
        initialize: function () {
            $(document).ready( function() {
                Y.log('csvtemplatebuilder.js init.');
                setTimeout(bindEvents, 500);
            });
        }
    };
});