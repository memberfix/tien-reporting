jQuery(document).ready(function($) {
    
    // Initialize
    init();
    
    function init() {
        bindEvents();
        toggleReportConfigs();
    }
    
    function bindEvents() {
        // Help toggle
        $('.help-toggle').on('click', function(e) {
            e.preventDefault();
            $(this).siblings('.help-content').slideToggle();
        });
        
        // Test connection button
        $('#test-google-connection').on('click', testConnection);
        
        // Connect to Google Sheets button
        $('#connect-google-sheets').on('click', connectToGoogleSheets);
        
        // Checkbox toggles for report configs
        $('input[name$="_enabled"]').on('change', toggleReportConfigs);
        
        // Save scheduled reports
        $('#save-scheduled-reports').on('click', saveScheduledReports);
    }
    
    function testConnection() {
        const $button = $('#test-google-connection');
        const $spinner = $button.siblings('.spinner');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        showMessage(mfxReporting.strings.connecting, 'info');
        
        $.ajax({
            url: mfxReporting.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mfx_test_google_connection',
                nonce: mfxReporting.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.message, 'success');
                } else {
                    showMessage(response.message, 'error');
                }
            },
            error: function() {
                showMessage(mfxReporting.strings.connectionFailed, 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    }
    
    function connectToGoogleSheets() {
        const $button = $('#connect-google-sheets');
        const $spinner = $button.siblings('.spinner');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        showMessage(mfxReporting.strings.loadingSpreadsheets, 'info');
        
        $.ajax({
            url: mfxReporting.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mfx_get_spreadsheets',
                nonce: mfxReporting.nonce
            },
            success: function(response) {
                if (response.success) {
                    populateSpreadsheetDropdowns(response.spreadsheets);
                    $('#scheduled-reports-section').slideDown();
                    showMessage(mfxReporting.strings.connected, 'success');
                } else {
                    showMessage(response.message, 'error');
                }
            },
            error: function() {
                showMessage(mfxReporting.strings.connectionFailed, 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    }
    
    function populateSpreadsheetDropdowns(spreadsheets) {
        const $dropdowns = $('.spreadsheet-dropdown');
        
        // Clear existing options except the first one
        $dropdowns.each(function() {
            $(this).find('option:not(:first)').remove();
        });
        
        if (spreadsheets && spreadsheets.length > 0) {
            spreadsheets.forEach(function(sheet) {
                const option = `<option value="${sheet.id}">${sheet.name}</option>`;
                $dropdowns.append(option);
            });
        } else {
            const noSheetsOption = `<option value="" disabled>${mfxReporting.strings.noSpreadsheets}</option>`;
            $dropdowns.append(noSheetsOption);
        }
        
        // Restore previously selected values
        restoreSelectedValues();
    }
    
    function restoreSelectedValues() {
        // Get saved values from PHP (if any)
        const savedReports = window.mfxScheduledReports || {};
        
        if (savedReports.daily && savedReports.daily.spreadsheet_id) {
            $('#daily_spreadsheet').val(savedReports.daily.spreadsheet_id);
        }
        if (savedReports.weekly && savedReports.weekly.spreadsheet_id) {
            $('#weekly_spreadsheet').val(savedReports.weekly.spreadsheet_id);
        }
        if (savedReports.monthly && savedReports.monthly.spreadsheet_id) {
            $('#monthly_spreadsheet').val(savedReports.monthly.spreadsheet_id);
        }
    }
    
    function toggleReportConfigs() {
        $('input[name$="_enabled"]').each(function() {
            const $checkbox = $(this);
            const configId = $checkbox.attr('id').replace('_enabled', '-config');
            const $config = $('#' + configId);
            
            if ($checkbox.is(':checked')) {
                $config.slideDown();
            } else {
                $config.slideUp();
            }
        });
    }
    
    function saveScheduledReports() {
        const $button = $('#save-scheduled-reports');
        const $spinner = $button.siblings('.spinner');
        const $form = $('#scheduled-reports-form');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        showMessage(mfxReporting.strings.savingSettings, 'info');
        
        const formData = $form.serialize();
        formData += '&action=mfx_save_scheduled_reports';
        
        $.ajax({
            url: mfxReporting.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showMessage(response.message, 'success');
                } else {
                    showMessage(response.message, 'error');
                }
            },
            error: function() {
                showMessage(mfxReporting.strings.error, 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    }
    
    function showMessage(message, type) {
        const $status = $('#connection-status');
        const $message = $('#connection-message');
        
        // Remove existing classes
        $status.removeClass('notice-success notice-error notice-info notice-warning');
        
        // Add appropriate class
        $status.addClass('notice-' + type);
        
        // Set message and show
        $message.text(message);
        $status.slideDown();
        
        // Auto-hide after 5 seconds for success messages
        if (type === 'success') {
            setTimeout(function() {
                $status.slideUp();
            }, 5000);
        }
    }
    
    // Spinner styles
    $('.spinner').css({
        'float': 'none',
        'margin-left': '10px',
        'margin-top': '0',
        'vertical-align': 'middle'
    });
});
