<?php

namespace MFX_Reporting\Services;

/**
 * Google Sheets Service - Handles OAuth2 authentication and API calls
 */
class GoogleSheetsService {
    
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    
    public function __construct() {
        // These should be set in WordPress admin or wp-config.php
        $this->client_id = MFX_GOOGLE_CLIENT_ID;
        $this->client_secret = MFX_GOOGLE_CLIENT_SECRET;
        $this->redirect_uri = admin_url('admin.php?page=mfx-reporting&action=oauth_callback');
    }
    
    /**
     * Get Google OAuth2 authorization URL
     */
    public function getAuthUrl() {
        error_log("client_id: " . $this->client_id);
        error_log("client_secret: " . $this->client_secret);
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => 'https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/drive.readonly',
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => wp_create_nonce('mfx_google_oauth')
        ];
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken($code, $state) {
        // Verify state parameter
        if (!wp_verify_nonce($state, 'mfx_google_oauth')) {
            throw new \Exception('Invalid state parameter');
        }
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirect_uri
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception('Failed to exchange code for token: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            throw new \Exception('OAuth error: ' . $data['error_description']);
        }
        
        // Store tokens
        update_option('mfx_reporting_google_access_token', $data['access_token']);
        if (isset($data['refresh_token'])) {
            update_option('mfx_reporting_google_refresh_token', $data['refresh_token']);
        }
        update_option('mfx_reporting_google_token_expires', time() + $data['expires_in']);
        
        return $data;
    }
    
    /**
     * Get valid access token (refresh if needed)
     */
    private function getAccessToken() {
        $access_token = get_option('mfx_reporting_google_access_token');
        $expires_at = get_option('mfx_reporting_google_token_expires', 0);
        
        // Check if token needs refresh
        if (time() >= $expires_at - 300) { // Refresh 5 minutes before expiry
            $this->refreshAccessToken();
            $access_token = get_option('mfx_reporting_google_access_token');
        }
        
        return $access_token;
    }
    
    /**
     * Refresh access token using refresh token
     */
    private function refreshAccessToken() {
        $refresh_token = get_option('mfx_reporting_google_refresh_token');
        
        if (!$refresh_token) {
            throw new \Exception('No refresh token available');
        }
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token'
            ]
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception('Failed to refresh token: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            throw new \Exception('Token refresh error: ' . $data['error_description']);
        }
        
        update_option('mfx_reporting_google_access_token', $data['access_token']);
        update_option('mfx_reporting_google_token_expires', time() + $data['expires_in']);
    }
    
    /**
     * Test Google Sheets connection
     */
    public function testConnection() {
        try {
            $access_token = $this->getAccessToken();
            
            $response = wp_remote_get('https://www.googleapis.com/drive/v3/about?fields=user', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ]
            ]);
            
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['error'])) {
                throw new \Exception($data['error']['message']);
            }
            
            return [
                'success' => true,
                'message' => 'Connection successful',
                'user' => $data['user'] ?? null
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if user is connected to Google Sheets
     */
    public function isConnected() {
        $access_token = get_option('mfx_reporting_google_access_token');
        $refresh_token = get_option('mfx_reporting_google_refresh_token');
        
        return !empty($access_token) || !empty($refresh_token);
    }
    
    /**
     * Get user's spreadsheets
     */
    public function getSpreadsheets() {
        try {
            $access_token = $this->getAccessToken();
            
            $response = wp_remote_get('https://www.googleapis.com/drive/v3/files?q=mimeType%3D%27application%2Fvnd.google-apps.spreadsheet%27&pageSize=100', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ]
            ]);
            
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['error'])) {
                throw new \Exception($data['error']['message']);
            }
            
            $spreadsheets = [];
            if (isset($data['files'])) {
                foreach ($data['files'] as $file) {
                    $spreadsheets[] = [
                        'id' => $file['id'],
                        'name' => $file['name']
                    ];
                }
            }
            
            return [
                'success' => true,
                'spreadsheets' => $spreadsheets
            ];
            
        } catch (\Exception $e) {
            error_log('MFX Reporting - Failed to get spreadsheets: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to get spreadsheets: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Disconnect user (revoke tokens)
     */
    public function disconnect() {
        try {
            $access_token = get_option('mfx_google_access_token');
            
            if ($access_token) {
                // Revoke the token
                wp_remote_post('https://oauth2.googleapis.com/revoke', [
                    'body' => ['token' => $access_token],
                    'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']
                ]);
            }
            
            // Clear stored tokens
            delete_option('mfx_google_access_token');
            delete_option('mfx_google_refresh_token');
            delete_option('mfx_google_token_expires');
            
            return [
                'success' => true,
                'message' => 'Successfully disconnected from Google Sheets.'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error disconnecting: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Save scheduled reports settings
     */
    public function saveScheduledReports($data) {
        $scheduled_reports = [];
        
        // Process daily reports
        if (!empty($data['daily_enabled'])) {
            $scheduled_reports['daily'] = [
                'enabled' => true,
                'spreadsheet_id' => sanitize_text_field($data['daily_spreadsheet'] ?? ''),
                'worksheet_name' => sanitize_text_field($data['daily_worksheet'] ?? 'Daily Reports')
            ];
        }
        
        // Process weekly reports
        if (!empty($data['weekly_enabled'])) {
            $scheduled_reports['weekly'] = [
                'enabled' => true,
                'spreadsheet_id' => sanitize_text_field($data['weekly_spreadsheet'] ?? ''),
                'worksheet_name' => sanitize_text_field($data['weekly_worksheet'] ?? 'Weekly Reports')
            ];
        }
        
        // Process monthly reports
        if (!empty($data['monthly_enabled'])) {
            $scheduled_reports['monthly'] = [
                'enabled' => true,
                'spreadsheet_id' => sanitize_text_field($data['monthly_spreadsheet'] ?? ''),
                'worksheet_name' => sanitize_text_field($data['monthly_worksheet'] ?? 'Monthly Reports')
            ];
        }
        
        // Save to WordPress options
        update_option('mfx_reporting_scheduled_reports', $scheduled_reports);
        
        return [
            'success' => true,
            'message' => 'Scheduled reports settings saved successfully.',
            'data' => $scheduled_reports
        ];
    }
    
    /**
     * Get saved scheduled reports settings
     */
    public function getScheduledReports() {
        return get_option('mfx_reporting_scheduled_reports', []);
    }
    
    /**
     * Get specific spreadsheet ID for a frequency
     */
    public function getSpreadsheetId($frequency) {
        $scheduled_reports = $this->getScheduledReports();
        
        if (isset($scheduled_reports[$frequency]) && isset($scheduled_reports[$frequency]['spreadsheet_id'])) {
            return $scheduled_reports[$frequency]['spreadsheet_id'];
        }
        
        return null;
    }
    
    /**
     * Check if a specific frequency is enabled
     */
    public function isFrequencyEnabled($frequency) {
        $scheduled_reports = $this->getScheduledReports();
        
        return isset($scheduled_reports[$frequency]) && 
               isset($scheduled_reports[$frequency]['enabled']) && 
               $scheduled_reports[$frequency]['enabled'] === true;
    }
    
    /**
     * Create a new sheet in a spreadsheet
     */
    public function createSheet($spreadsheet_id, $sheet_name) {
        if (!$this->isConnected()) {
            throw new \Exception('Not connected to Google Sheets');
        }
        
        $access_token = $this->getAccessToken();
        if (!$access_token) {
            throw new \Exception('Unable to get valid access token');
        }
        
        $request_body = [
            'requests' => [
                [
                    'addSheet' => [
                        'properties' => [
                            'title' => $sheet_name
                        ]
                    ]
                ]
            ]
        ];
        
        $response = wp_remote_post(
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}:batchUpdate",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($request_body)
            ]
        );
        
        if (is_wp_error($response)) {
            throw new \Exception('Failed to create sheet: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            throw new \Exception('Google Sheets API error: ' . $data['error']['message']);
        }
        
        return $data;
    }
    
    /**
     * Write data to a specific sheet
     */
    public function writeToSheet($spreadsheet_id, $sheet_name, $data, $range = 'A1') {
        if (!$this->isConnected()) {
            throw new \Exception('Not connected to Google Sheets');
        }
        
        $access_token = $this->getAccessToken();
        if (!$access_token) {
            throw new \Exception('Unable to get valid access token');
        }
        
        $full_range = $sheet_name . '!' . $range;
        
        $request_body = [
            'values' => $data
        ];
        
        $response = wp_remote_request(
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$full_range}?valueInputOption=RAW",
            [
                'method' => 'PUT',
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($request_body)
            ]
        );
        
        if (is_wp_error($response)) {
            throw new \Exception('Failed to write to sheet: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            throw new \Exception('Google Sheets API error: ' . $data['error']['message']);
        }
        
        return $data;
    }
    
    /**
     * Export daily report to Google Sheets
     */
    public function exportDailyReport($date = null) {
        if (!$date) {
            $date = date('Y-m-d', strtotime('-1 day')); // Default to yesterday
        }
        
        // Check if daily reports are enabled
        if (!$this->isFrequencyEnabled('daily')) {
            throw new \Exception('Daily reports are not enabled');
        }
        
        $spreadsheet_id = $this->getSpreadsheetId('daily');
        if (!$spreadsheet_id) {
            throw new \Exception('No spreadsheet configured for daily reports');
        }
        
        // Get WooCommerce data
        $wc_service = new WooCommerceDataService();
        $orders_data = $wc_service->getDailyOrdersData($date);
        
        if (empty($orders_data['orders'])) {
            error_log("MFX Reporting: No orders found for date {$date}");
            return [
                'success' => true,
                'message' => "No orders found for {$date}",
                'revenue' => 0
            ];
        }
        
        // Format data for Google Sheets
        $formatted_data = $wc_service->formatForGoogleSheets($orders_data);
        
        // Create sheet name with date
        $sheet_name = date('Y-m-d', strtotime($date));
        
        try {
            // Create new sheet
            $this->createSheet($spreadsheet_id, $sheet_name);
            
            // Write data to sheet
            $this->writeToSheet($spreadsheet_id, $sheet_name, $formatted_data);
            
            error_log("MFX Reporting: Successfully exported daily report for {$date}. Revenue: $" . number_format($orders_data['total_revenue'], 2));
            
            return [
                'success' => true,
                'message' => "Daily report exported successfully for {$date}",
                'revenue' => $orders_data['total_revenue'],
                'order_count' => $orders_data['order_count'],
                'sheet_name' => $sheet_name
            ];
            
        } catch (\Exception $e) {
            error_log("MFX Reporting: Failed to export daily report for {$date}: " . $e->getMessage());
            throw $e;
        }
    }
}