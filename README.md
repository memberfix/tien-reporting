# MFX Reporting - WooCommerce Reporting Plugin

A comprehensive WooCommerce reporting plugin that provides detailed sales analytics and integrates with Google Sheets for data export and visualization.

## Features

- **Sales Analytics**: Track total sales, revenue, orders, and customer metrics
- **Product Performance**: Analyze top-selling products and category performance
- **Customer Insights**: Monitor customer lifetime value and behavior
- **Google Sheets Integration**: Export reports directly to Google Sheets
- **Scheduled Reports**: Automated report generation and export
- **Modern Dashboard**: Clean, responsive admin interface
- **Customizable KPIs**: Enable/disable specific metrics based on your needs

## Requirements

- WordPress 5.0 or higher (tested up to 6.3)
- WooCommerce 3.0 or higher (tested up to 8.0)
- PHP 7.4 or higher
- Composer (for dependency management)
- Google Cloud Project with OAuth2 credentials

## Installation

1. **Download and Extract**
   ```bash
   # Clone or download the plugin to your WordPress plugins directory
   cd /path/to/wordpress/wp-content/plugins/
   git clone [repository-url] mfx-reporting
   cd mfx-reporting
   ```

2. **Install Dependencies**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "MFX Reporting" and click "Activate"

## Google Sheets Setup

### 1. Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable the Google Sheets API:
   - Go to "APIs & Services" → "Library"
   - Search for "Google Sheets API"
   - Click "Enable"
   - Also enable "Google Drive API" for file access

### 2. Create OAuth 2.0 Credentials

1. Go to "APIs & Services" → "Credentials"
2. Click "Create Credentials" → "OAuth client ID"
3. Configure consent screen if prompted:
   - Choose "External" user type
   - Fill required fields (App name, User support email, Developer contact)
   - Add scopes: `https://www.googleapis.com/auth/spreadsheets` and `https://www.googleapis.com/auth/drive.readonly`
4. Select "Web application" as application type
5. Add authorized redirect URI: `https://yourdomain.com/wp-admin/admin.php?page=mfx-reporting&action=oauth_callback`
6. Click "Create" and save the Client ID and Client Secret

### 3. Configure WordPress Constants

1. Add the following constants to your `wp-config.php` file:
   ```php
   define('MFX_GOOGLE_CLIENT_ID', 'your-client-id-here');
   define('MFX_GOOGLE_CLIENT_SECRET', 'your-client-secret-here');
   ```

### 4. Create Google Sheet

1. Create a new Google Sheet
2. Copy the spreadsheet ID from the URL:
   ```
   https://docs.google.com/spreadsheets/d/[SPREADSHEET_ID]/edit
   ```

## Plugin Configuration

1. **Navigate to Settings**
   - Go to WordPress Admin → MFX Reporting

2. **Google Sheets Authentication**
   - Click "Connect to Google Sheets" to start OAuth flow
   - You'll be redirected to Google to authorize the application
   - Grant permissions for Sheets and Drive access
   - You'll be redirected back to WordPress upon success

3. **Google Sheets Configuration**
   - Enter your Google Spreadsheet ID
   - Test the connection to verify access

4. **Report Settings**
   - Choose report frequency (Manual, Daily, Weekly, Monthly)
   - Configure scheduled exports
   - Save settings

## Usage

### Dashboard
- View key performance indicators
- Monitor sales trends
- Quick overview of business metrics

### Reports
- Generate custom reports by date range
- Export data to Google Sheets
- View detailed product and customer analytics

### Settings
- Configure Google Sheets integration
- Customize report frequency
- Enable/disable specific metrics

## File Structure

```
mfx-reporting/
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── includes/
│   ├── Controllers/
│   │   └── AdminController.php
│   ├── Core/
│   │   └── Plugin.php
│   ├── Services/
│   │   ├── ActionSchedulerService.php
│   │   ├── DebugLogger.php
│   │   ├── GoogleSheetsService.php
│   │   └── WooCommerceDataService.php
│   └── Views/
│       └── AdminView.php
├── composer.json
├── mfx-reporting.php
├── CLAUDE.md
└── README.md
```

## Available KPIs

- **Net Revenue**: Total sales minus discounts and refunds
- **Gross Revenue**: Total sales excluding taxes and shipping
- **Discounts Given**: Total discount amounts applied
- **Refunds**: Total refunds processed
- **Trials Started**: New trial subscriptions initiated
- **New Members**: New paying subscribers
- **Cancellations**: Subscription cancellations
- **Net Paid Subscriber Growth**: New members minus cancellations
- **Rolling LTV**: Customer lifetime value calculation
- **Trial Order Percentage**: Percentage of orders that are trials

## Scheduled Reports

The plugin supports automated report generation:

- **Manual**: Generate reports on-demand only
- **Daily**: Generate reports every day at midnight
- **Weekly**: Generate reports every Monday
- **Monthly**: Generate reports on the 1st of each month

## Troubleshooting

### Google Sheets Connection Issues

1. **OAuth Authentication Failed**
   - Verify `MFX_GOOGLE_CLIENT_ID` and `MFX_GOOGLE_CLIENT_SECRET` constants are set
   - Check that redirect URI in Google Cloud Console matches your site URL
   - Ensure constants contain valid OAuth credentials

2. **Permission Denied**
   - Verify you have access to the Google Spreadsheet
   - Check spreadsheet ID is correct
   - Ensure spreadsheet is not private or restricted

3. **API Not Enabled**
   - Enable both Google Sheets API and Google Drive API in Google Cloud Console
   - Wait a few minutes for activation
   - Check OAuth consent screen is properly configured

### Plugin Issues

1. **Missing Dependencies**
   ```bash
   composer install --no-dev
   ```

2. **WooCommerce Not Active**
   - Ensure WooCommerce plugin is installed and activated

3. **PHP Version**
   - Verify PHP 7.4 or higher is installed

## Support

For support and feature requests, please contact the development team or create an issue in the project repository.

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### Version 1.0.0
- Initial release
- Google Sheets integration
- Sales analytics dashboard
- Scheduled reporting
- Responsive admin interface
