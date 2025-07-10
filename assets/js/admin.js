jQuery(document).ready(function($) {
    
    // Initialize
    init();
    
    function init() {
        bindEvents();
        setupMessageListener();
        loadSpreadsheets();
        initializeReportConfigs();
    }
    
    function bindEvents() {
        // Setup help toggle
        $('.setup-help-toggle').on('click', function(e) {
            e.preventDefault();
            $(this).siblings('.setup-help-content').slideToggle();
        });

        console.log('Attaching click handler to #test-daily-export-btn');
        console.log('Button element:', $('#test-daily-export-btn'));
        console.log('Button length:', $('#test-daily-export-btn').length);
        $('#test-daily-export-btn').on('click', function(e) {
            console.log('Test daily export button clicked!');
            testDailyExport();
        });
        
        // Connect to Google Sheets button
        $('#connect-google-sheets').on('click', function() {
            const $button = $(this);
            const $spinner = $button.siblings('.spinner');
            
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            
            $.ajax({
                url: mfxReporting.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mfx_get_auth_url',
                    nonce: mfxReporting.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Open popup for OAuth
                        const popup = window.open(
                            response.data.auth_url,
                            'google_oauth',
                            'width=500,height=600,scrollbars=yes,resizable=yes'
                        );
                        
                        // Listen for popup messages
                        const messageListener = function(event) {
                            if (event.origin !== window.location.origin) return;
                            
                            if (event.data.type === 'oauth_success') {
                                popup.close();
                                window.removeEventListener('message', messageListener);
                                showMessage('Successfully connected to Google Sheets!', 'success');
                                setTimeout(() => location.reload(), 1500);
                            } else if (event.data.type === 'oauth_error') {
                                popup.close();
                                window.removeEventListener('message', messageListener);
                                showMessage('Connection failed: ' + event.data.message, 'error');
                            }
                        };
                        
                        window.addEventListener('message', messageListener);
                        
                        // Check if popup was closed manually
                        const checkClosed = setInterval(() => {
                            if (popup.closed) {
                                clearInterval(checkClosed);
                                window.removeEventListener('message', messageListener);
                            }
                        }, 1000);
                        
                    } else {
                        showMessage('Error: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    showMessage('Connection failed. Please try again.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });
        
        // Test connection button
        $('#test-google-connection').on('click', function() {
            const $button = $(this);
            const $spinner = $button.siblings('.spinner');
            
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            
            $.ajax({
                url: mfxReporting.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mfx_test_connection',
                    nonce: mfxReporting.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('Connection test successful!', 'success');
                    } else {
                        showMessage('Connection test failed: ' + response.data.message, 'error');
                    }
                },
                error: function(e) {
                    showMessage('Connection test failed. Please try again.' + e, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Connection');
                    $spinner.removeClass('is-active');
                }
            });
        });
        
        // Disconnect button
        $('#disconnect-google').on('click', function() {
            if (!confirm('Are you sure you want to disconnect from Google Sheets?')) {
                return;
            }
            
            const $button = $(this);
            const $spinner = $button.siblings('.spinner');
            
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            
            $.ajax({
                url: mfxReporting.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mfx_disconnect_google',
                    nonce: mfxReporting.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('Successfully disconnected from Google Sheets.', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showMessage('Disconnect failed: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    showMessage('Disconnect failed. Please try again.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });
        
        // Save scheduled reports
        $('#save-scheduled-reports').on('click', function() {
            const $button = $(this);
            const $spinner = $button.siblings('.spinner');
            const $form = $('#scheduled-reports-form');
            
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            
            // Get form data
            const formData = {
                action: 'mfx_save_scheduled_reports',
                nonce: mfxReporting.nonce,
                daily_spreadsheet: $('#daily_spreadsheet').val(),
                weekly_spreadsheet: $('#weekly_spreadsheet').val(),
                monthly_spreadsheet: $('#monthly_spreadsheet').val()
            };
            
            // Only include enabled reports (those with selected spreadsheets)
            if (formData.daily_spreadsheet) {
                formData.daily_enabled = '1';
            }
            if (formData.weekly_spreadsheet) {
                formData.weekly_enabled = '1';
            }
            if (formData.monthly_spreadsheet) {
                formData.monthly_enabled = '1';
            }
            
            $.ajax({
                url: mfxReporting.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        showMessage('Scheduled reports settings saved successfully!', 'success');
                        // Reload page to show saved values
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showMessage('Save failed: ' + (response.data?.message || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showMessage('Save failed. Please try again.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });      
        // Initialize any other functionality here if needed
    }

    function setupMessageListener() {
        // Listen for messages from OAuth popup
        window.addEventListener('message', function(event) {
            if (event.data.type === 'oauth_success') {
                showMessage(mfxReporting.strings.connected, 'success');
                // Reload page to show connected state
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            } else if (event.data.type === 'oauth_error') {
                showMessage('OAuth Error: ' + event.data.message, 'error');
            }
        });
    }

    function loadSpreadsheets() {
        if (!$('.spreadsheet-dropdown').length) {
            return;
        }

        $.ajax({
            url: mfxReporting.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mfx_get_spreadsheets',
                nonce: mfxReporting.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Check if data has spreadsheets array (from getSpreadsheets method)
                    const spreadsheets = response.data.spreadsheets || response.data;
                    populateSpreadsheetDropdowns(spreadsheets);
                } else {
                }
            },
            error: function(xhr, status, error) {
            }
        });
    }

    function populateSpreadsheetDropdowns(spreadsheets) {
        $('.spreadsheet-dropdown').each(function() {
            const $select = $(this);
            const currentValue = $select.data('current-value') || '';

            // Clear existing options except the first one
            $select.find('option:not(:first)').remove();

            // Add spreadsheet options
            spreadsheets.forEach(function(sheet) {
                const $option = $('<option></option>')
                    .attr('value', sheet.id)
                    .text(sheet.name);

                if (sheet.id === currentValue) {
                    $option.prop('selected', true);
                }

                $select.append($option);
            });
        });
    }

    function initializeReportConfigs() {
        // Initialize any other functionality here if needed
    }

    function connectToGoogleSheets() {
        const $button = $('#connect-google-sheets');
        const $spinner = $button.siblings('.spinner');

        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        showMessage(mfxReporting.strings.openingPopup, 'info');

        // Get OAuth URL from server
        $.ajax({
            url: mfxReporting.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mfx_get_auth_url',
                nonce: mfxReporting.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Open OAuth popup
                    const popup = window.open(
                        response.data.auth_url,
                        'google_oauth',
                        'width=500,height=600,scrollbars=yes,resizable=yes'
                    );

                    if (!popup || popup.closed || typeof popup.closed == 'undefined') {
                        showMessage(mfxReporting.strings.popupBlocked, 'error');
                        $button.prop('disabled', false);
                        $spinner.removeClass('is-active');
                        return;
                    }

                    // Check if popup is closed manually
                    const checkClosed = setInterval(function() {
                        if (popup.closed) {
                            clearInterval(checkClosed);
                            $button.prop('disabled', false);
                            $spinner.removeClass('is-active');
                            hideMessage();
                        }
                    }, 1000);

                } else {
                    showMessage(response.data.message, 'error');
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            },
            error: function() {
                showMessage(mfxReporting.strings.error, 'error');
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
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
                    showMessage(response.data.message, 'success');
                } else {
                    showMessage(response.data.message, 'error');
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

    function disconnectGoogle() {
        if (!confirm('Are you sure you want to disconnect from Google Sheets?')) {
            return;
        }

        const $button = $('#disconnect-google');
        const $spinner = $button.siblings('.spinner');

        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        showMessage(mfxReporting.strings.disconnecting, 'info');

        $.ajax({
            url: mfxReporting.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mfx_disconnect_google',
                nonce: mfxReporting.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    // Reload page to show disconnected state
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage(response.data.message, 'error');
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

    function testDailyExport() {
        console.log('testDailyExport function called');
        
        const testBtn = $('#test-daily-export-btn');
        const dateInput = $('#test-export-date');
        const resultDiv = $('#test-export-result');
        
        console.log('Button:', testBtn);
        console.log('Date input:', dateInput);
        console.log('Result div:', resultDiv);

        // Disable button and show loading
        testBtn.prop('disabled', true).text('Exporting...');
        resultDiv.hide();

        console.log('Making AJAX request...');

        $.ajax({
            url: mfxReporting.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mfx_reporting_test_daily_export',
                nonce: mfxReporting.nonce,
                date: dateInput.val()
            },
            success: function(response) {
                console.log('AJAX Success:', response);
                if (response.success) {
                    resultDiv
                        .removeClass('notice-error')
                        .addClass('notice-success')
                        .html(`
                            <p><strong>Export Successful!</strong></p>
                            <p>${response.data.message}</p>
                            ${response.data.revenue ? `<p>Revenue: $${response.data.revenue}</p>` : ''}
                            ${response.data.order_count ? `<p>Orders: ${response.data.order_count}</p>` : ''}
                            ${response.data.sheet_name ? `<p>Sheet: ${response.data.sheet_name}</p>` : ''}
                        `)
                        .show();
                } else {
                    resultDiv
                        .removeClass('notice-success')
                        .addClass('notice-error')
                        .html(`<p><strong>Export Failed:</strong> ${response.data.message}</p>`)
                        .show();
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', xhr, status, error);
                resultDiv
                    .removeClass('notice-success')
                    .addClass('notice-error')
                    .html('<p><strong>Error:</strong> Failed to communicate with server</p>')
                    .show();
            },
            complete: function() {
                console.log('AJAX Complete');
                // Re-enable button
                testBtn.prop('disabled', false).text('Test Daily Export');
            }
        });
    }

    function showMessage(message, type) {
        const $messages = $('#connection-messages');
        const $messageText = $('#connection-message-text');

        // Remove existing classes
        $messages.removeClass('notice-success notice-error notice-info notice-warning');

        // Add appropriate class
        $messages.addClass('notice-' + type);

        // Set message and show
        $messageText.text(message);
        $messages.slideDown();

        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                $messages.slideUp();
            }, 5000);
        }
    }

    function hideMessage() {
        $('#connection-messages').slideUp();
    }

    // Spinner styles
    $('.spinner').css({
        'float': 'none',
        'margin-left': '10px',
        'margin-top': '0',
        'vertical-align': 'middle'
    });
});
