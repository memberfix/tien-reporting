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
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
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
                        <span class="spinner"></span>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
}
