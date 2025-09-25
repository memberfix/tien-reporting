# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Development Setup
```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# For development (includes dev dependencies)
composer install
```

### WordPress Plugin Development
This is a WordPress plugin that must be placed in `/wp-content/plugins/` directory and activated through WordPress admin. There are no npm scripts - this is purely a PHP-based WordPress plugin.

## Architecture Overview

This is a **WordPress plugin** called "Memberfix Reporting" that provides WooCommerce reporting with Google Sheets integration. The plugin follows a **singleton pattern** with an MVC-like architecture.

### Core Structure
- **Entry Point**: `mfx-reporting.php` - Main plugin file that handles initialization and WordPress integration
- **Main Class**: `includes/Core/Plugin.php` - Singleton pattern implementation that orchestrates all components
- **Namespace**: All classes use `MFX_Reporting\` namespace with PSR-4 autoloading

### Key Components

#### Controllers
- `AdminController.php` - Handles WordPress admin interface, AJAX endpoints, and OAuth callbacks for Google Sheets integration

#### Services
- `GoogleSheetsService.php` - Manages OAuth2 authentication and Google Sheets API interactions
- `WooCommerceDataService.php` - Extracts WooCommerce sales data and metrics
- `ActionSchedulerService.php` - Handles scheduled report generation
- `DebugLogger.php` - Logging service for debugging

#### Views
- `AdminView.php` - Renders WordPress admin pages and interfaces

### Dependencies
- **PHP**: Requires PHP 7.4+
- **WordPress**: 5.0+, with WooCommerce 3.0+ required
- **Google API Client**: Uses `google/apiclient` (^2.15) for Google Sheets integration
- **WordPress**: Uses WordPress hooks, admin system, and AJAX handlers

### Google Sheets Integration
The plugin uses OAuth2 flow to authenticate with Google Sheets API. Key constants required:
- `MFX_GOOGLE_CLIENT_ID` - Google OAuth client ID
- `MFX_GOOGLE_CLIENT_SECRET` - Google OAuth client secret

### Data Flow
1. WooCommerceDataService extracts sales metrics from WooCommerce
2. Data can be manually exported or scheduled via ActionSchedulerService
3. GoogleSheetsService handles authentication and pushes data to Google Sheets
4. AdminController manages all WordPress admin interactions and AJAX endpoints

### WordPress Integration
- Uses WordPress admin menu system (`add_menu_page`)
- Implements WordPress AJAX handlers with `wp_ajax_` hooks
- Follows WordPress coding standards and security practices (nonces, sanitization)
- Uses WordPress options API for storing settings and OAuth tokens

## Important Notes
- This is a WordPress plugin that integrates with WooCommerce, Woocommerce Subscriptions, and Google Sheets, not a standalone application
- All Google API credentials must be configured as WordPress constants
- The plugin checks for WooCommerce dependency before initialization
- Uses WordPress's built-in autoloader alongside Composer autoloader