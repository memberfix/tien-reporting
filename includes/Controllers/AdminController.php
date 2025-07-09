<?php

namespace MFX_Reporting\Controllers;

use MFX_Reporting\Views\AdminView;

/**
 * Admin Controller - Handles admin menu and pages
 */
class AdminController {
    
    /**
     * Admin view instance
     */
    private $admin_view;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->admin_view = new AdminView();
        $this->init();
    }
    
    /**
     * Initialize controller
     */
    private function init() {
        add_action('admin_menu', [$this, 'addAdminMenu']);
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
}
