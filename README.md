# MFX Reporting

A WordPress plugin that provides WooCommerce sales reporting with automated Google Sheets export. Designed for subscription-based businesses using WooCommerce Subscriptions.

## Features

- **Comprehensive Sales Metrics**: Net revenue, gross revenue, discounts, refunds
- **Subscription Analytics**: Trial starts, new members, cancellations, net subscriber growth
- **Customer Lifetime Value**: Rolling LTV calculation based on active subscribers
- **Google Sheets Integration**: OAuth2 authentication with automatic data export
- **Scheduled Reports**: Automated weekly (Monday) and monthly (1st of month) exports
- **Detailed Data Export**: Individual order details and cancellation reasons
- **Manual Testing**: Test exports with custom date overrides

## Requirements

- PHP 7.4+
- WordPress 5.0+ (tested up to 6.3)
- WooCommerce 3.0+ (tested up to 8.0)
- WooCommerce Subscriptions
- Action Scheduler (bundled with WooCommerce Subscriptions)
- Composer

## Installation

1. Clone or download the plugin to your WordPress plugins directory:
   ```bash
   cd /path/to/wordpress/wp-content/plugins/
   git clone [repository-url] mfx-reporting
   cd mfx-reporting
   ```

2. Install dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. Activate the plugin via WordPress Admin > Plugins

## Google Sheets Setup

### 1. Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the following APIs:
   - Google Sheets API
   - Google Drive API

### 2. Create OAuth 2.0 Credentials

1. Go to APIs & Services > Credentials
2. Click "Create Credentials" > "OAuth client ID"
3. Configure the OAuth consent screen:
   - Choose "External" user type
   - Add scopes: `spreadsheets` and `drive.readonly`
4. Select "Web application" as application type
5. Add authorized redirect URI:
   ```
   https://yourdomain.com/wp-admin/admin.php?page=mfx-reporting&action=oauth_callback
   ```
6. Save the Client ID and Client Secret

### 3. Configure WordPress

Add the following constants to your `wp-config.php`:

```php
define('MFX_GOOGLE_CLIENT_ID', 'your-client-id-here');
define('MFX_GOOGLE_CLIENT_SECRET', 'your-client-secret-here');
```

## Usage

### Initial Setup

1. Navigate to WordPress Admin > MFX Reporting
2. Click "Connect to Google Sheets" to authenticate
3. Grant permissions in the Google OAuth popup
4. Select spreadsheets for weekly and monthly reports
5. Save your scheduled report preferences

### Scheduled Reports

Reports are automatically generated:
- **Weekly**: Every Monday at midnight (covers the previous 7 days ending Sunday)
- **Monthly**: 1st of each month at midnight (covers the entire previous month)

For example, a monthly report running on January 1st will report on all of December.

Each export creates a new sheet in your selected spreadsheet with the date range as the sheet name.

### Manual Testing

Use the "Test Export" buttons to manually trigger exports with optional custom dates. This is useful for:
- Verifying the connection works
- Generating historical reports
- Testing before enabling scheduled exports

## Metrics Tracked

| Metric | Description |
|--------|-------------|
| Net Revenue | Gross revenue minus discounts and refunds |
| Gross Revenue | Total order subtotals (excludes taxes, shipping, fees) |
| Discounts Given | Total discount amounts applied |
| Refunds | Total refund amounts processed |
| Trials Started | Number of free trial subscriptions initiated |
| New Members | New paid subscribers (excludes trials) |
| Cancellations | Paid subscription cancellations (excludes trial cancellations) |
| Net Paid Subscriber Growth | New members minus cancellations |
| Rolling LTV | Average revenue per active customer (monthly normalized) |
| Trial Order Percentage | Percentage of orders containing trials |

## Export Format

Each Google Sheets export includes:

1. **Summary Section**: All metrics with period dates
2. **Detailed Orders**: Order ID, date, customer, email, subtotal, discount, total, trial/new member flags
3. **Detailed Cancellations**: Subscription ID, date, customer, email, cancellation reason

## File Structure

```
mfx-reporting/
├── mfx-reporting.php          # Main plugin file
├── composer.json              # Dependencies
├── includes/
│   ├── Core/
│   │   └── Plugin.php         # Plugin orchestration
│   ├── Controllers/
│   │   └── AdminController.php # Admin UI and AJAX handlers
│   ├── Views/
│   │   └── AdminView.php      # HTML rendering
│   └── Services/
│       ├── GoogleSheetsService.php      # Google API integration
│       ├── WooCommerceDataService.php   # Data extraction
│       ├── ActionSchedulerService.php   # Scheduled tasks
│       └── DebugLogger.php              # Debug logging
└── assets/
    ├── css/admin.css          # Admin styles
    └── js/admin.js            # Admin JavaScript
```

## Troubleshooting

### OAuth Authentication Failed

- Verify `MFX_GOOGLE_CLIENT_ID` and `MFX_GOOGLE_CLIENT_SECRET` are set in `wp-config.php`
- Check that the redirect URI in Google Cloud Console exactly matches your site URL
- Ensure your site uses HTTPS

### Permission Denied

- Verify you have edit access to the selected Google Spreadsheet
- Check that both Google Sheets API and Google Drive API are enabled

### Missing Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### WooCommerce Not Detected

The plugin requires WooCommerce to be installed and activated. It will display an admin notice if WooCommerce is not found.

### Scheduled Reports Not Running

- Verify Action Scheduler is installed (bundled with WooCommerce Subscriptions)
- Check that the report frequency is enabled in settings
- View scheduled actions at Tools > Scheduled Actions

## Development

For development with dev dependencies:

```bash
composer install
```

## License

GPL v2 or later
