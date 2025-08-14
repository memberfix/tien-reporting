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
     * Register all scheduled actions
     */
    public function registerScheduledActions() {
        // Schedule weekly action (runs every Monday at midnight)
        $this->scheduleWeeklyAction();
        
        // Schedule monthly action (runs on 1st of each month at midnight)
        $this->scheduleMonthlyAction();
    }
    
    /**
     * Schedule weekly action
     */
    private function scheduleWeeklyAction() {
        // Check if weekly action is already scheduled
        if (!as_next_scheduled_action(self::WEEKLY_HOOK)) {
            // Schedule for next Monday at midnight
            $next_monday = strtotime('next monday midnight');
            as_schedule_recurring_action($next_monday, WEEK_IN_SECONDS, self::WEEKLY_HOOK, [], 'mfx-reporting');
        }
    }
    
    /**
     * Schedule monthly action
     */
    private function scheduleMonthlyAction() {
        // Check if monthly action is already scheduled
        if (!as_next_scheduled_action(self::MONTHLY_HOOK)) {
            // Schedule for first day of next month at midnight
            $first_next_month = strtotime('first day of next month midnight');
            as_schedule_recurring_action($first_next_month, MONTH_IN_SECONDS, self::MONTHLY_HOOK, [], 'mfx-reporting');
        }
    }
    
    /**
     * Unregister all scheduled actions
     */
    public function unregisterScheduledActions() {
        as_unschedule_all_actions(self::WEEKLY_HOOK, [], 'mfx-reporting');
        as_unschedule_all_actions(self::MONTHLY_HOOK, [], 'mfx-reporting');
    }
    
    /**
     * Handle weekly export scheduled action
     */
    public function handleWeeklyExport() {
        try {
            error_log('MFX Reporting: Starting weekly export scheduled action');
            
            $google_sheets_service = new GoogleSheetsService();
            
            // Check if weekly reports are enabled
            if (!$google_sheets_service->isFrequencyEnabled('weekly')) {
                error_log('MFX Reporting: Weekly reports are not enabled, skipping export');
                return;
            }
            
            // Export weekly report with comprehensive metrics
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
     */
    public function handleMonthlyExport() {
        try {
            error_log('MFX Reporting: Starting monthly export scheduled action');
            
            $google_sheets_service = new GoogleSheetsService();
            
            // Check if monthly reports are enabled
            if (!$google_sheets_service->isFrequencyEnabled('monthly')) {
                error_log('MFX Reporting: Monthly reports are not enabled, skipping export');
                return;
            }
            
            // Export monthly report with comprehensive metrics
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
     * Manually trigger weekly export (for testing)
     */
    public function triggerWeeklyExport($date = null) {
        $google_sheets_service = new GoogleSheetsService();
        return $google_sheets_service->exportComprehensiveReport('weekly', $date);
    }
    
    /**
     * Manually trigger monthly export (for testing)
     */
    public function triggerMonthlyExport($date = null) {
        $google_sheets_service = new GoogleSheetsService();
        return $google_sheets_service->exportComprehensiveReport('monthly', $date);
    }
    
    /**
     * Get next scheduled times for all actions
     */
    public function getScheduledTimes() {
        return [
            'weekly' => as_next_scheduled_action(self::WEEKLY_HOOK, [], 'mfx-reporting'),
            'monthly' => as_next_scheduled_action(self::MONTHLY_HOOK, [], 'mfx-reporting')
        ];
    }
    
    /**
     * Get all scheduled actions for this plugin
     */
    public function getScheduledActions() {
        $actions = [];
        
        // Get weekly actions
        $weekly_actions = as_get_scheduled_actions([
            'hook' => self::WEEKLY_HOOK,
            'group' => 'mfx-reporting',
            'status' => 'pending'
        ]);
        
        // Get monthly actions
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
