<?php

namespace MFX_Reporting\Services;

/**
 * Google Sheets Service - Handles Google Sheets API integration
 */
class GoogleSheetsService {
    
    /**
     * Google Client instance
     */
    private $client;
    
    /**
     * Google Sheets service
     */
    private $sheets_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->initializeClient();
    }
    
    /**
     * Initialize Google Client
     */
    private function initializeClient() {
        if (!class_exists('Google\Client')) {
            return false;
        }
        
        $service_account_json = get_option('mfx_reporting_google_service_account_json', '');
        
        if (empty($service_account_json)) {
            return false;
        }
        
        try {
            $credentials = json_decode($service_account_json, true);
            
            if (!$credentials) {
                return false;
            }
            
            $this->client = new \Google\Client();
            $this->client->setAuthConfig($credentials);
            $this->client->addScope(\Google\Service\Sheets::SPREADSHEETS);
            $this->client->setAccessType('offline');
            
            $this->sheets_service = new \Google\Service\Sheets($this->client);
            
            return true;
        } catch (Exception $e) {
            error_log('Google Sheets Service Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test Google Sheets connection
     */
    public function testConnection() {
        if (!$this->sheets_service) {
            return [
                'success' => false,
                'message' => __('Google Sheets service not initialized. Please check your credentials.', 'mfx-reporting')
            ];
        }
        
        try {
            // Try to access a test spreadsheet or create one
            $spreadsheet_id = get_option('mfx_reporting_google_spreadsheet_id', '');
            
            if (empty($spreadsheet_id)) {
                return [
                    'success' => false,
                    'message' => __('Please provide a Spreadsheet ID.', 'mfx-reporting')
                ];
            }
            
            $response = $this->sheets_service->spreadsheets->get($spreadsheet_id);
            
            return [
                'success' => true,
                'message' => __('Successfully connected to Google Sheets!', 'mfx-reporting'),
                'spreadsheet_title' => $response->getProperties()->getTitle()
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => __('Connection failed: ', 'mfx-reporting') . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get all spreadsheets from Google Drive
     */
    public function getAllSpreadsheets() {
        if (!$this->client) {
            return [
                'success' => false,
                'message' => __('Google client not initialized.', 'mfx-reporting')
            ];
        }
        
        try {
            $drive_service = new \Google\Service\Drive($this->client);
            
            $response = $drive_service->files->listFiles([
                'q' => "mimeType='application/vnd.google-apps.spreadsheet'",
                'pageSize' => 100,
                'fields' => 'files(id,name,createdTime,modifiedTime)'
            ]);
            
            $spreadsheets = [];
            foreach ($response->getFiles() as $file) {
                $spreadsheets[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'created' => $file->getCreatedTime(),
                    'modified' => $file->getModifiedTime()
                ];
            }
            
            return [
                'success' => true,
                'spreadsheets' => $spreadsheets
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => __('Failed to fetch spreadsheets: ', 'mfx-reporting') . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get worksheets from a specific spreadsheet
     */
    public function getWorksheets($spreadsheet_id) {
        if (!$this->sheets_service) {
            return [
                'success' => false,
                'message' => __('Google Sheets service not initialized.', 'mfx-reporting')
            ];
        }
        
        try {
            $response = $this->sheets_service->spreadsheets->get($spreadsheet_id);
            $sheets = $response->getSheets();
            
            $worksheets = [];
            foreach ($sheets as $sheet) {
                $properties = $sheet->getProperties();
                $worksheets[] = [
                    'id' => $properties->getSheetId(),
                    'title' => $properties->getTitle(),
                    'index' => $properties->getIndex()
                ];
            }
            
            return [
                'success' => true,
                'worksheets' => $worksheets
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => __('Failed to fetch worksheets: ', 'mfx-reporting') . $e->getMessage()
            ];
        }
    }
    
    /**
     * Create or update scheduled report settings
     */
    public function saveScheduledReports($settings) {
        $scheduled_reports = [
            'daily' => [
                'enabled' => !empty($settings['daily_enabled']),
                'spreadsheet_id' => $settings['daily_spreadsheet'] ?? '',
                'worksheet_name' => $settings['daily_worksheet'] ?? 'Daily Reports'
            ],
            'weekly' => [
                'enabled' => !empty($settings['weekly_enabled']),
                'spreadsheet_id' => $settings['weekly_spreadsheet'] ?? '',
                'worksheet_name' => $settings['weekly_worksheet'] ?? 'Weekly Reports'
            ],
            'monthly' => [
                'enabled' => !empty($settings['monthly_enabled']),
                'spreadsheet_id' => $settings['monthly_spreadsheet'] ?? '',
                'worksheet_name' => $settings['monthly_worksheet'] ?? 'Monthly Reports'
            ]
        ];
        
        update_option('mfx_reporting_scheduled_reports', $scheduled_reports);
        
        return [
            'success' => true,
            'message' => __('Scheduled reports settings saved successfully!', 'mfx-reporting')
        ];
    }
}
