<?php

namespace MFX_Reporting\Services;

/**
 * Action Scheduler Service
 * Handles scheduled reports using Action Scheduler instead of WP Cron
 */
class ActionSchedulerService {
    
    /**
     * Action hook names
     */
    const WEEKLY_HOOK = 'mfx_reporting_weekly_export';
    const MONTHLY_HOOK = 'mfx_reporting_monthly_export';
    
    /**
     * Register all scheduled actions for weekly and monthly exports
     * Called on plugin initialization to ensure recurring actions are scheduled
     */
    public function registerScheduledActions() {
        $this->scheduleWeeklyAction();
        $this->scheduleMonthlyAction();
    }
    
    /**
     * Schedule weekly export action to run every Monday at midnight
     * Only schedules if no existing action is found to prevent duplicates
     */
    private function scheduleWeeklyAction() {
        if (!as_next_scheduled_action(self::WEEKLY_HOOK)) {
            $next_monday = strtotime('next monday midnight');
            as_schedule_recurring_action($next_monday, WEEK_IN_SECONDS, self::WEEKLY_HOOK, [], 'mfx-reporting');
        }
    }
    
    /**
     * Schedule monthly export action to run on first day of each month at midnight
     * Only schedules if no existing action is found to prevent duplicates
     */
    private function scheduleMonthlyAction() {
        if (!as_next_scheduled_action(self::MONTHLY_HOOK)) {
            $first_next_month = strtotime('first day of next month midnight');
            as_schedule_recurring_action($first_next_month, MONTH_IN_SECONDS, self::MONTHLY_HOOK, [], 'mfx-reporting');
        }
    }
    
    /**
     * Unregister all scheduled actions for this plugin
     * Called during plugin deactivation to clean up scheduled tasks
     */
    public function unregisterScheduledActions() {
        as_unschedule_all_actions(self::WEEKLY_HOOK, [], 'mfx-reporting');
        as_unschedule_all_actions(self::MONTHLY_HOOK, [], 'mfx-reporting');
    }
    
    /**
     * Handle weekly export scheduled action
     * Validates credentials and settings, then exports weekly report to Google Sheets
     * Includes comprehensive error handling and logging
     */
    public function handleWeeklyExport() {
        try {
            error_log('MFX Reporting: Starting weekly export scheduled action');
            
            if (!defined('MFX_GOOGLE_CLIENT_ID') || !defined('MFX_GOOGLE_CLIENT_SECRET')) {
                error_log('MFX Reporting: Google credentials not configured, skipping weekly export');
                return;
            }
            
            $google_sheets_service = new GoogleSheetsService();
            
            if (!$google_sheets_service->isFrequencyEnabled('weekly')) {
                error_log('MFX Reporting: Weekly reports are not enabled, skipping export');
                return;
            }
            
            $result = $google_sheets_service->exportComprehensiveReport('weekly');
            
            if ($result['success']) {
                error_log('MFX Reporting: Weekly export completed successfully - ' . $result['message']);
            } else {
                error_log('MFX Reporting: Weekly export failed - ' . $result['message']);
            }
            
        } catch (\Exception $e) {
            error_log('MFX Reporting: Weekly export scheduled action failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle monthly export scheduled action
     * Validates credentials and settings, then exports monthly report to Google Sheets
     * Includes comprehensive error handling and logging
     */
    public function handleMonthlyExport() {
        try {
            error_log('MFX Reporting: Starting monthly export scheduled action');
            
            if (!defined('MFX_GOOGLE_CLIENT_ID') || !defined('MFX_GOOGLE_CLIENT_SECRET')) {
                error_log('MFX Reporting: Google credentials not configured, skipping monthly export');
                return;
            }
            
            $google_sheets_service = new GoogleSheetsService();
            
            if (!$google_sheets_service->isFrequencyEnabled('monthly')) {
                error_log('MFX Reporting: Monthly reports are not enabled, skipping export');
                return;
            }
            
            $result = $google_sheets_service->exportComprehensiveReport('monthly');
            
            if ($result['success']) {
                error_log('MFX Reporting: Monthly export completed successfully - ' . $result['message']);
            } else {
                error_log('MFX Reporting: Monthly export failed - ' . $result['message']);
            }
            
        } catch (\Exception $e) {
            error_log('MFX Reporting: Monthly export scheduled action failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Manually trigger weekly export for testing purposes
     * Bypasses scheduled execution and runs export immediately with optional date override
     */
    public function triggerWeeklyExport($date = null) {
        $google_sheets_service = new GoogleSheetsService();
        return $google_sheets_service->exportComprehensiveReport('weekly', $date);
    }
    
    /**
     * Manually trigger monthly export for testing purposes  
     * Bypasses scheduled execution and runs export immediately with optional date override
     */
    public function triggerMonthlyExport($date = null) {
        $google_sheets_service = new GoogleSheetsService();
        return $google_sheets_service->exportComprehensiveReport('monthly', $date);
    }
    
    /**
     * Get next scheduled execution times for all recurring actions
     * Returns timestamps for weekly and monthly export schedules
     */
    public function getScheduledTimes() {
        return [
            'weekly' => as_next_scheduled_action(self::WEEKLY_HOOK, [], 'mfx-reporting'),
            'monthly' => as_next_scheduled_action(self::MONTHLY_HOOK, [], 'mfx-reporting')
        ];
    }
    
    /**
     * Get all pending scheduled actions for this plugin
     * Returns detailed action objects grouped by frequency for monitoring purposes
     */
    public function getScheduledActions() {
        $weekly_actions = as_get_scheduled_actions([
            'hook' => self::WEEKLY_HOOK,
            'group' => 'mfx-reporting',
            'status' => 'pending'
        ]);
        
        $monthly_actions = as_get_scheduled_actions([
            'hook' => self::MONTHLY_HOOK,
            'group' => 'mfx-reporting',
            'status' => 'pending'
        ]);
        
        return [
            'weekly' => $weekly_actions,
            'monthly' => $monthly_actions
        ];
    }
}
