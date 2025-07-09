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

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.4 or higher
- Composer (for dependency management)

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

### 2. Create Service Account

1. Go to "APIs & Services" → "Credentials"
2. Click "Create Credentials" → "Service Account"
3. Fill in service account details
4. Click "Create and Continue"
5. Skip role assignment (click "Continue")
6. Click "Done"

### 3. Generate Service Account Key

1. Click on the created service account
2. Go to "Keys" tab
3. Click "Add Key" → "Create New Key"
4. Select "JSON" format
5. Download the JSON file

### 4. Create Google Sheet

1. Create a new Google Sheet
2. Copy the spreadsheet ID from the URL:
   ```
   https://docs.google.com/spreadsheets/d/[SPREADSHEET_ID]/edit
   ```
3. Share the sheet with the service account email (found in JSON file)
4. Give "Editor" permissions

## Plugin Configuration

1. **Navigate to Settings**
   - Go to WordPress Admin → MFX Reporting → Settings

2. **Google Sheets Configuration**
   - Upload the service account JSON file
   - Enter your Google Spreadsheet ID
   - Set the worksheet name (default: "Reports")
   - Test the connection

3. **Report Settings**
   - Choose report frequency (Manual, Daily, Weekly, Monthly)
   - Select which KPIs to track
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
│   │   ├── AdminController.php
│   │   ├── ReportController.php
│   │   └── SettingsController.php
│   ├── Core/
│   │   └── Plugin.php
│   ├── Models/
│   │   └── ReportModel.php
│   ├── Services/
│   │   ├── GoogleSheetsService.php
│   │   └── WooCommerceDataService.php
│   └── Views/
│       └── AdminView.php
├── composer.json
├── mfx-reporting.php
└── README.md
```

## Available KPIs

- **Total Sales**: Sum of all completed orders
- **Total Revenue**: Revenue excluding taxes and shipping
- **Order Count**: Number of orders placed
- **Average Order Value**: Average value per order
- **Customer Count**: Unique customers who made purchases
- **Refund Amount**: Total refunds processed
- **Top Products**: Best-selling products by revenue
- **Category Performance**: Sales by product category
- **Customer Lifetime Value**: Repeat customer analysis

## Scheduled Reports

The plugin supports automated report generation:

- **Manual**: Generate reports on-demand only
- **Daily**: Generate reports every day at midnight
- **Weekly**: Generate reports every Monday
- **Monthly**: Generate reports on the 1st of each month

## Troubleshooting

### Google Sheets Connection Issues

1. **Invalid JSON File**
   - Ensure you downloaded the correct service account JSON
   - Verify the file is not corrupted

2. **Permission Denied**
   - Share the Google Sheet with the service account email
   - Grant "Editor" permissions

3. **API Not Enabled**
   - Enable Google Sheets API in Google Cloud Console
   - Wait a few minutes for activation

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
