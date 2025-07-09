<?php

namespace MFX_Reporting\Controllers;

use MFX_Reporting\Views\AdminView;
use MFX_Reporting\Services\GoogleSheetsService;

/**
 * Admin Controller - Handles admin menu and pages
 */
class AdminController {
    
    /**
     * Admin view instance
     */
    private $admin_view;
    
    /**
     * Google Sheets service instance
     */
    private $google_sheets_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->admin_view = new AdminView();
        $this->google_sheets_service = new GoogleSheetsService();
        $this->init();
    }
    
    /**
     * Initialize controller
     */
    private function init() {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        
        // AJAX handlers
        add_action('wp_ajax_mfx_test_google_connection', [$this, 'handleTestConnection']);
        add_action('wp_ajax_mfx_get_spreadsheets', [$this, 'handleGetSpreadsheets']);
        add_action('wp_ajax_mfx_save_scheduled_reports', [$this, 'handleSaveScheduledReports']);
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
            'dashicons-chart-line',
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
     * Handle test Google connection AJAX request
     */
    public function handleTestConnection() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mfx_reporting_nonce')) {
            wp_die(__('Security check failed', 'mfx-reporting'));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'mfx-reporting'));
        }
        
        $result = $this->google_sheets_service->testConnection();
        wp_send_json($result);
    }
    
    /**
     * Handle get spreadsheets AJAX request
     */
    public function handleGetSpreadsheets() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mfx_reporting_nonce')) {
            wp_die(__('Security check failed', 'mfx-reporting'));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'mfx-reporting'));
        }
        
        $result = $this->google_sheets_service->getAllSpreadsheets();
        wp_send_json($result);
    }
    
    /**
     * Handle save scheduled reports AJAX request
     */
    public function handleSaveScheduledReports() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mfx_reporting_nonce')) {
            wp_die(__('Security check failed', 'mfx-reporting'));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'mfx-reporting'));
        }
        
        $settings = [
            'daily_enabled' => $_POST['daily_enabled'] ?? false,
            'daily_spreadsheet' => sanitize_text_field($_POST['daily_spreadsheet'] ?? ''),
            'daily_worksheet' => sanitize_text_field($_POST['daily_worksheet'] ?? ''),
            'weekly_enabled' => $_POST['weekly_enabled'] ?? false,
            'weekly_spreadsheet' => sanitize_text_field($_POST['weekly_spreadsheet'] ?? ''),
            'weekly_worksheet' => sanitize_text_field($_POST['weekly_worksheet'] ?? ''),
            'monthly_enabled' => $_POST['monthly_enabled'] ?? false,
            'monthly_spreadsheet' => sanitize_text_field($_POST['monthly_spreadsheet'] ?? ''),
            'monthly_worksheet' => sanitize_text_field($_POST['monthly_worksheet'] ?? '')
        ];
        
        $result = $this->google_sheets_service->saveScheduledReports($settings);
        wp_send_json($result);
    }
}
