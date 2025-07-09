<?php

namespace MFX_Reporting\Core;

use MFX_Reporting\Controllers\AdminController;

/**
 * Main Plugin Class
 */
class Plugin {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Controllers
     */
    private $admin_controller;
    
    /**
     * Get plugin instance (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Initialize controllers
        $this->admin_controller = new AdminController();
        
        // Hook into WordPress
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }
    
    /**
     * Register plugin settings
     */
    public function registerSettings() {
        register_setting('mfx_reporting_settings', 'mfx_reporting_google_client_id');
        register_setting('mfx_reporting_settings', 'mfx_reporting_google_client_secret');
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueueAssets($hook) {
        if ($hook !== 'toplevel_page_mfx-reporting') {
            return;
        }
        
        wp_enqueue_style(
            'mfx-reporting-admin',
            MFX_REPORTING_PLUGIN_URL . 'assets/css/admin.css',
            [],
            MFX_REPORTING_VERSION
        );
        
        wp_enqueue_script(
            'mfx-reporting-admin',
            MFX_REPORTING_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            MFX_REPORTING_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script('mfx-reporting-admin', 'mfxReporting', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mfx_reporting_nonce'),
            'strings' => [
                'connecting' => __('Connecting...', 'mfx-reporting'),
                'connected' => __('Connected successfully!', 'mfx-reporting'),
                'connectionFailed' => __('Connection failed', 'mfx-reporting'),
                'disconnecting' => __('Disconnecting...', 'mfx-reporting'),
                'disconnected' => __('Disconnected successfully!', 'mfx-reporting'),
                'openingPopup' => __('Opening login popup...', 'mfx-reporting'),
                'popupBlocked' => __('Popup was blocked. Please allow popups and try again.', 'mfx-reporting'),
                'error' => __('An error occurred', 'mfx-reporting')
            ]
        ]);
    }
}
