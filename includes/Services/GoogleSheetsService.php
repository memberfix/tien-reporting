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
        $this->client_id = get_option('mfx_reporting_google_client_id', '');
        $this->client_secret = get_option('mfx_reporting_google_client_secret', '');
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
            
            if (!$access_token) {
                return [
                    'success' => false,
                    'message' => 'No access token available. Please connect to Google Sheets first.'
                ];
            }
            
            // Test API call - get user's Drive files
            $response = wp_remote_get('https://www.googleapis.com/drive/v3/files?q=mimeType%3D%27application%2Fvnd.google-apps.spreadsheet%27&pageSize=1', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ]
            ]);
            
            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'message' => 'Connection failed: ' . $response->get_error_message()
                ];
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code !== 200) {
                return [
                    'success' => false,
                    'message' => 'API request failed with status: ' . $status_code
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Successfully connected to Google Sheets!'
            ];
            
        } catch (\Exception $e) {
            error_log('MFX Reporting - Google Sheets connection test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
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
     * Check if user is connected
     */
    public function isConnected() {
        $access_token = get_option('mfx_reporting_google_access_token');
        $refresh_token = get_option('mfx_reporting_google_refresh_token');
        
        return !empty($access_token) || !empty($refresh_token);
    }
    
    /**
     * Disconnect user (revoke tokens)
     */
    public function disconnect() {
        $access_token = get_option('mfx_reporting_google_access_token');
        
        if ($access_token) {
            // Revoke token
            wp_remote_post('https://oauth2.googleapis.com/revoke', [
                'body' => ['token' => $access_token]
            ]);
        }
        
        // Clear stored tokens
        delete_option('mfx_reporting_google_access_token');
        delete_option('mfx_reporting_google_refresh_token');
        delete_option('mfx_reporting_google_token_expires');
        
        return [
            'success' => true,
            'message' => 'Successfully disconnected from Google Sheets'
        ];
    }
}
