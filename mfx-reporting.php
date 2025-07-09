<?php
/**
 * Plugin Name: Memberfix Reporting
 * Description: WooCommerce reporting plugin with Google Sheets integration
 * Version: 1.0.0
 * Author: Memberfix Team
 * Author URI: https://memberfix.rocks
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mfx-reporting
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MFX_REPORTING_VERSION', '1.0.0');
define('MFX_REPORTING_PLUGIN_FILE', __FILE__);
define('MFX_REPORTING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MFX_REPORTING_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if WooCommerce is active
 */
function mfx_reporting_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo __('MFX Reporting requires WooCommerce to be installed and active.', 'mfx-reporting');
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * Autoloader for plugin classes
 */
spl_autoload_register(function ($class) {
    $prefix = 'MFX_Reporting\\';
    $base_dir = MFX_REPORTING_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Initialize the plugin
 */
function mfx_reporting_init() {
    if (!mfx_reporting_check_woocommerce()) {
        return;
    }
    
    // Initialize the main plugin class
    MFX_Reporting\Core\Plugin::getInstance();
}

// Initialize plugin
add_action('plugins_loaded', 'mfx_reporting_init');
