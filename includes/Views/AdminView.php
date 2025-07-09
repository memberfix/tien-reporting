<?php

namespace MFX_Reporting\Views;

use MFX_Reporting\Services\GoogleSheetsService;

/**
 * Admin View - Handles HTML rendering for admin pages
 */
class AdminView {
    
    private $google_sheets_service;
    
    public function __construct() {
        $this->google_sheets_service = new GoogleSheetsService();
    }
    
    /**
     * Render settings page
     */
    public function renderSettingsPage() {
        $is_connected = $this->google_sheets_service->isConnected();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <!-- Google Sheets Connection Section -->
            <div class="settings-section">
                <h3><?php _e('Google Sheets Integration', 'mfx-reporting'); ?></h3>
                <p class="description">
                    <?php _e('Connect your Google account to enable automatic report export to Google Sheets.', 'mfx-reporting'); ?>
                </p>
                
                <?php if ($is_connected): ?>
                    <!-- Connected State -->
                    <div class="connection-status connected">
                        <div class="status-icon">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                        <div class="status-content">
                            <h4><?php _e('Connected to Google Sheets', 'mfx-reporting'); ?></h4>
                            <p><?php _e('Your Google account is successfully connected and ready to use.', 'mfx-reporting'); ?></p>
                        </div>
                    </div>
                    
                    <div class="button-group">
                        <button type="button" id="test-google-connection" class="button button-secondary">
                            <?php _e('Test Connection', 'mfx-reporting'); ?>
                        </button>
                        <button type="button" id="disconnect-google" class="button button-secondary">
                            <?php _e('Disconnect', 'mfx-reporting'); ?>
                        </button>
                        <span class="spinner"></span>
                    </div>
                    
                <?php else: ?>
                    <!-- Not Connected State -->
                    <div class="connection-status not-connected">
                        <div class="status-icon">
                            <span class="dashicons dashicons-warning"></span>
                        </div>
                        <div class="status-content">
                            <h4><?php _e('Not Connected', 'mfx-reporting'); ?></h4>
                            <p><?php _e('Connect your Google account to start using Google Sheets integration.', 'mfx-reporting'); ?></p>
                        </div>
                    </div>
                    
                    <div class="button-group">
                        <button type="button" id="connect-google-sheets" class="button button-primary button-large">
                            <span class="dashicons dashicons-google"></span>
                            <?php _e('Connect to Google Sheets', 'mfx-reporting'); ?>
                        </button>
                        <span class="spinner"></span>
                    </div>
                    
                    <!-- Setup Instructions -->
                    <div class="setup-instructions">
                        <h4><?php _e('Setup Instructions', 'mfx-reporting'); ?></h4>
                        <p><?php _e('Before connecting, make sure you have:', 'mfx-reporting'); ?></p>
                        <ol>
                            <li><?php _e('A Google account with access to Google Sheets', 'mfx-reporting'); ?></li>
                            <li><?php _e('Google OAuth2 credentials configured (Client ID and Secret)', 'mfx-reporting'); ?></li>
                        </ol>
                        <p>
                            <a href="#" class="setup-help-toggle"><?php _e('Need help setting up OAuth2 credentials?', 'mfx-reporting'); ?></a>
                        </p>
                        <div class="setup-help-content">
                            <h5><?php _e('Setting up Google OAuth2 Credentials:', 'mfx-reporting'); ?></h5>
                            <ol>
                                <li><?php _e('Go to the Google Cloud Console', 'mfx-reporting'); ?></li>
                                <li><?php _e('Create a new project or select an existing one', 'mfx-reporting'); ?></li>
                                <li><?php _e('Enable the Google Sheets API and Google Drive API', 'mfx-reporting'); ?></li>
                                <li><?php _e('Go to "Credentials" and create OAuth 2.0 Client IDs', 'mfx-reporting'); ?></li>
                                <li><?php _e('Add your website domain to authorized origins', 'mfx-reporting'); ?></li>
                                <li><?php _e('Add the redirect URI:', 'mfx-reporting'); ?> <code><?php echo admin_url('admin.php?page=mfx-reporting&action=oauth_callback'); ?></code></li>
                                <li><?php _e('Copy the Client ID and Client Secret to your wp-config.php or WordPress options', 'mfx-reporting'); ?></li>
                            </ol>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Connection Messages -->
            <div id="connection-messages" class="notice" style="display: none;">
                <p id="connection-message-text"></p>
            </div>
            
            <?php if (isset($_GET['connected']) && $_GET['connected'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Successfully connected to Google Sheets!', 'mfx-reporting'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
