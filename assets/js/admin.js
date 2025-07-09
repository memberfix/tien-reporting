jQuery(document).ready(function($) {
    
    // Initialize
    init();
    
    function init() {
        bindEvents();
        setupMessageListener();
    }
    
    function bindEvents() {
        // Setup help toggle
        $('.setup-help-toggle').on('click', function(e) {
            e.preventDefault();
            $(this).siblings('.setup-help-content').slideToggle();
        });
        
        // Connect to Google Sheets button
        $('#connect-google-sheets').on('click', connectToGoogleSheets);
        
        // Test connection button
        $('#test-google-connection').on('click', testConnection);
        
        // Disconnect button
        $('#disconnect-google').on('click', disconnectGoogle);
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
