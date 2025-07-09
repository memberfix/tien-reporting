<?php

namespace MFX_Reporting\Views;

/**
 * Admin View - Handles HTML rendering for admin pages
 */
class AdminView {
    
    /**
     * Render settings page
     */
    public function renderSettingsPage() {
        $scheduled_reports = get_option('mfx_reporting_scheduled_reports', []);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <!-- Google Sheets Connection Section -->
            <div class="settings-section">
                <h3><?php _e('Google Sheets Integration', 'mfx-reporting'); ?></h3>
                
                <form method="post" action="options.php">
                    <?php
                    settings_fields('mfx_reporting_settings');
                    do_settings_sections('mfx_reporting_settings');
                    ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="google_service_account_json">
                                    <?php _e('Service Account JSON', 'mfx-reporting'); ?>
                                </label>
                            </th>
                            <td>
                                <textarea 
                                    id="google_service_account_json" 
                                    name="mfx_reporting_google_service_account_json" 
                                    rows="8" 
                                    cols="80" 
                                    class="large-text code"
                                    placeholder="<?php _e('Paste your Google Service Account JSON here...', 'mfx-reporting'); ?>"
                                ><?php echo esc_textarea(get_option('mfx_reporting_google_service_account_json', '')); ?></textarea>
                                <p class="description">
                                    <?php _e('Upload your Google Service Account JSON credentials.', 'mfx-reporting'); ?>
                                    <a href="#" class="help-toggle"><?php _e('Need help?', 'mfx-reporting'); ?></a>
                                </p>
                                <div class="help-content">
                                    <p><?php _e('To get your Service Account JSON:', 'mfx-reporting'); ?></p>
                                    <ol>
                                        <li><?php _e('Go to Google Cloud Console', 'mfx-reporting'); ?></li>
                                        <li><?php _e('Create or select a project', 'mfx-reporting'); ?></li>
                                        <li><?php _e('Enable Google Sheets API', 'mfx-reporting'); ?></li>
                                        <li><?php _e('Create Service Account credentials', 'mfx-reporting'); ?></li>
                                        <li><?php _e('Download the JSON key file', 'mfx-reporting'); ?></li>
                                    </ol>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="google_spreadsheet_id">
                                    <?php _e('Spreadsheet ID', 'mfx-reporting'); ?>
                                </label>
                            </th>
                            <td>
                                <input 
                                    type="text" 
                                    id="google_spreadsheet_id" 
                                    name="mfx_reporting_google_spreadsheet_id" 
                                    value="<?php echo esc_attr(get_option('mfx_reporting_google_spreadsheet_id', '')); ?>" 
                                    class="regular-text"
                                    placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms"
                                />
                                <p class="description">
                                    <?php _e('The ID of your Google Spreadsheet (found in the URL).', 'mfx-reporting'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="google_worksheet_name">
                                    <?php _e('Worksheet Name', 'mfx-reporting'); ?>
                                </label>
                            </th>
                            <td>
                                <input 
                                    type="text" 
                                    id="google_worksheet_name" 
                                    name="mfx_reporting_google_worksheet_name" 
                                    value="<?php echo esc_attr(get_option('mfx_reporting_google_worksheet_name', 'Reports')); ?>" 
                                    class="regular-text"
                                    placeholder="Reports"
                                />
                                <p class="description">
                                    <?php _e('Name of the worksheet where data will be exported.', 'mfx-reporting'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="button-group">
                        <?php submit_button(__('Save Settings', 'mfx-reporting'), 'primary', 'submit', false); ?>
                        <button type="button" id="test-google-connection" class="button button-secondary">
                            <?php _e('Test Connection', 'mfx-reporting'); ?>
                        </button>
                        <button type="button" id="connect-google-sheets" class="button button-secondary">
                            <?php _e('Connect to Google Sheets', 'mfx-reporting'); ?>
                        </button>
                        <span class="spinner"></span>
                    </div>
                </form>
            </div>

            <!-- Scheduled Reports Section -->
            <div class="settings-section" id="scheduled-reports-section" style="display: none;">
                <h3><?php _e('Scheduled Reports', 'mfx-reporting'); ?></h3>
                <p class="description">
                    <?php _e('Configure automatic report generation and export to Google Sheets.', 'mfx-reporting'); ?>
                </p>

                <form id="scheduled-reports-form">
                    <?php wp_nonce_field('mfx_reporting_nonce', 'mfx_reporting_nonce'); ?>
                    
                    <!-- Daily Reports -->
                    <div class="report-frequency-section">
                        <h4>
                            <label>
                                <input type="checkbox" id="daily_enabled" name="daily_enabled" value="1" 
                                    <?php checked(!empty($scheduled_reports['daily']['enabled'])); ?>>
                                <?php _e('Daily Reports', 'mfx-reporting'); ?>
                            </label>
                        </h4>
                        <div class="report-config" id="daily-config">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="daily_spreadsheet"><?php _e('Spreadsheet', 'mfx-reporting'); ?></label>
                                    </th>
                                    <td>
                                        <select id="daily_spreadsheet" name="daily_spreadsheet" class="regular-text spreadsheet-dropdown">
                                            <option value=""><?php _e('Select a spreadsheet...', 'mfx-reporting'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="daily_worksheet"><?php _e('Worksheet Name', 'mfx-reporting'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="daily_worksheet" name="daily_worksheet" 
                                            value="<?php echo esc_attr($scheduled_reports['daily']['worksheet_name'] ?? 'Daily Reports'); ?>" 
                                            class="regular-text" placeholder="Daily Reports">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Weekly Reports -->
                    <div class="report-frequency-section">
                        <h4>
                            <label>
                                <input type="checkbox" id="weekly_enabled" name="weekly_enabled" value="1" 
                                    <?php checked(!empty($scheduled_reports['weekly']['enabled'])); ?>>
                                <?php _e('Weekly Reports', 'mfx-reporting'); ?>
                            </label>
                        </h4>
                        <div class="report-config" id="weekly-config">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="weekly_spreadsheet"><?php _e('Spreadsheet', 'mfx-reporting'); ?></label>
                                    </th>
                                    <td>
                                        <select id="weekly_spreadsheet" name="weekly_spreadsheet" class="regular-text spreadsheet-dropdown">
                                            <option value=""><?php _e('Select a spreadsheet...', 'mfx-reporting'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="weekly_worksheet"><?php _e('Worksheet Name', 'mfx-reporting'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="weekly_worksheet" name="weekly_worksheet" 
                                            value="<?php echo esc_attr($scheduled_reports['weekly']['worksheet_name'] ?? 'Weekly Reports'); ?>" 
                                            class="regular-text" placeholder="Weekly Reports">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Monthly Reports -->
                    <div class="report-frequency-section">
                        <h4>
                            <label>
                                <input type="checkbox" id="monthly_enabled" name="monthly_enabled" value="1" 
                                    <?php checked(!empty($scheduled_reports['monthly']['enabled'])); ?>>
                                <?php _e('Monthly Reports', 'mfx-reporting'); ?>
                            </label>
                        </h4>
                        <div class="report-config" id="monthly-config">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="monthly_spreadsheet"><?php _e('Spreadsheet', 'mfx-reporting'); ?></label>
                                    </th>
                                    <td>
                                        <select id="monthly_spreadsheet" name="monthly_spreadsheet" class="regular-text spreadsheet-dropdown">
                                            <option value=""><?php _e('Select a spreadsheet...', 'mfx-reporting'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="monthly_worksheet"><?php _e('Worksheet Name', 'mfx-reporting'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="monthly_worksheet" name="monthly_worksheet" 
                                            value="<?php echo esc_attr($scheduled_reports['monthly']['worksheet_name'] ?? 'Monthly Reports'); ?>" 
                                            class="regular-text" placeholder="Monthly Reports">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="button-group">
                        <button type="button" id="save-scheduled-reports" class="button button-primary">
                            <?php _e('Save Scheduled Reports', 'mfx-reporting'); ?>
                        </button>
                        <span class="spinner"></span>
                    </div>
                </form>
            </div>

            <!-- Connection Status -->
            <div id="connection-status" class="notice" style="display: none;">
                <p id="connection-message"></p>
            </div>
        </div>
        <?php
    }
}
