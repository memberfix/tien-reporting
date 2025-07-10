<?php

namespace MFX_Reporting\Controllers;

use MFX_Reporting\Views\AdminView;
use MFX_Reporting\Services\GoogleSheetsService;

/**
 * Admin Controller - Handles admin functionality
 */
class AdminController {
    
    private $admin_view;
    private $google_sheets_service;
    
    public function __construct() {
        $this->admin_view = new AdminView();
        $this->google_sheets_service = new GoogleSheetsService();
        
        $this->init();
    }
    
    private function init() {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('wp_ajax_mfx_get_auth_url', [$this, 'handleGetAuthUrl']);
        add_action('wp_ajax_mfx_test_google_connection', [$this, 'handleTestConnection']);
        add_action('wp_ajax_mfx_disconnect_google', [$this, 'handleDisconnect']);
        add_action('wp_ajax_mfx_get_spreadsheets', [$this, 'handleGetSpreadsheets']);
        add_action('wp_ajax_mfx_save_scheduled_reports', [$this, 'handleSaveScheduledReports']);
        add_action('admin_init', [$this, 'handleOAuthCallback']);
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu() {
        add_menu_page(
            __('MFX Reporting', 'mfx-reporting'),
            __('MFX Reporting', 'mfx-reporting'),
            'manage_options',
            'mfx-reporting',
            [$this, 'renderSettingsPage'],
            'dashicons-chart-area',
            30
        );
    }
    
    /**
     * Render settings page
     */
    public function renderSettingsPage() {
        $this->admin_view->renderSettingsPage();
    }
    
    /**
     * Handle OAuth callback
     */
    public function handleOAuthCallback() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'mfx-reporting') {
            return;
        }
        
        if (!isset($_GET['action']) || $_GET['action'] !== 'oauth_callback') {
            return;
        }
        
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            wp_die(__('Invalid OAuth callback parameters', 'mfx-reporting'));
        }
        
        try {
            $this->google_sheets_service->exchangeCodeForToken($_GET['code'], $_GET['state']);
            
            // Redirect to close popup and refresh parent
            echo '<script>
                if (window.opener) {
                    window.opener.postMessage({type: "oauth_success"}, "*");
                    window.close();
                } else {
                    window.location.href = "' . admin_url('admin.php?page=mfx-reporting&connected=1') . '";
                }
            </script>';
            exit;
            
        } catch (\Exception $e) {
            echo '<script>
                if (window.opener) {
                    window.opener.postMessage({type: "oauth_error", message: "' . esc_js($e->getMessage()) . '"}, "*");
                    window.close();
                } else {
                    wp_die("OAuth Error: ' . esc_html($e->getMessage()) . '");
                }
            </script>';
            exit;
        }
    }
    
    /**
     * AJAX: Get Google OAuth URL
     */
    public function handleGetAuthUrl() {
        check_ajax_referer('mfx_reporting_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'mfx-reporting')]);
        }
        
        try {
            $auth_url = $this->google_sheets_service->getAuthUrl();
            wp_send_json_success(['auth_url' => $auth_url]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * AJAX: Test Google connection
     */
    public function handleTestConnection() {
        check_ajax_referer('mfx_reporting_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'mfx-reporting')]);
        }
        
        $result = $this->google_sheets_service->testConnection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Disconnect from Google
     */
    public function handleDisconnect() {
        check_ajax_referer('mfx_reporting_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'mfx-reporting')]);
        }
        
        $result = $this->google_sheets_service->disconnect();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Get spreadsheets
     */
    public function handleGetSpreadsheets() {
        check_ajax_referer('mfx_reporting_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'mfx-reporting')]);
        }
        
        try {
            $spreadsheets = $this->google_sheets_service->getSpreadsheets();
            wp_send_json_success($spreadsheets);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * AJAX: Save scheduled reports
     */
    public function handleSaveScheduledReports() {
        error_log('MFX Debug: handleSaveScheduledReports called');
        error_log('MFX Debug: POST data: ' . print_r($_POST, true));
        
        try {
            check_ajax_referer('mfx_reporting_nonce', 'nonce');
            error_log('MFX Debug: Nonce verified');
        } catch (Exception $e) {
            error_log('MFX Debug: Nonce verification failed: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        if (!current_user_can('manage_options')) {
            error_log('MFX Debug: User lacks manage_options capability');
            wp_send_json_error(['message' => __('Insufficient permissions', 'mfx-reporting')]);
            return;
        }
        
        error_log('MFX Debug: Permissions verified, calling saveScheduledReports');
        
        try {
            $result = $this->google_sheets_service->saveScheduledReports($_POST);
            error_log('MFX Debug: saveScheduledReports result: ' . print_r($result, true));
            wp_send_json_success($result);
        } catch (\Exception $e) {
            error_log('MFX Debug: Exception in saveScheduledReports: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
