<?php

namespace MFX_Reporting\Services;

/**
 * Cron Service
 * Handles WordPress cron jobs for scheduled reports
 */
class CronService {
    
    /**
     * Cron hook names
     */
    const DAILY_HOOK = 'mfx_reporting_daily_export';
    const WEEKLY_HOOK = 'mfx_reporting_weekly_export';
    const MONTHLY_HOOK = 'mfx_reporting_monthly_export';
    
    /**
     * Register all cron jobs
     */
    public function registerCronJobs() {
        // Register daily cron job (runs at midnight)
        if (!wp_next_scheduled(self::DAILY_HOOK)) {
            wp_schedule_event(strtotime('tomorrow midnight'), 'daily', self::DAILY_HOOK);
        }
        
        // Register weekly cron job (runs every Monday at midnight)
        if (!wp_next_scheduled(self::WEEKLY_HOOK)) {
            wp_schedule_event(strtotime('next monday midnight'), 'weekly', self::WEEKLY_HOOK);
        }
        
        // Register monthly cron job (runs on 1st of each month at midnight)
        if (!wp_next_scheduled(self::MONTHLY_HOOK)) {
            wp_schedule_event(strtotime('first day of next month midnight'), 'monthly', self::MONTHLY_HOOK);
        }
    }
    
    /**
     * Unregister all cron jobs
     */
    public function unregisterCronJobs() {
        wp_clear_scheduled_hook(self::DAILY_HOOK);
        wp_clear_scheduled_hook(self::WEEKLY_HOOK);
        wp_clear_scheduled_hook(self::MONTHLY_HOOK);
    }
    
    /**
     * Handle daily export cron job
     */
    public function handleDailyExport() {
        try {
            error_log('MFX Reporting: Starting daily export cron job');
            
            $google_sheets_service = new GoogleSheetsService();
            
            // Check if daily reports are enabled
            if (!$google_sheets_service->isFrequencyEnabled('daily')) {
                error_log('MFX Reporting: Daily reports are not enabled, skipping export');
                return;
            }
            
            // Export daily report
            $result = $google_sheets_service->exportDailyReport();
            
            if ($result['success']) {
                error_log('MFX Reporting: Daily export completed successfully - ' . $result['message']);
            } else {
                error_log('MFX Reporting: Daily export failed - ' . $result['message']);
            }
            
        } catch (\Exception $e) {
            error_log('MFX Reporting: Daily export cron job failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle weekly export cron job
     */
    public function handleWeeklyExport() {
        try {
            error_log('MFX Reporting: Starting weekly export cron job');
            
            $google_sheets_service = new GoogleSheetsService();
            
            // Check if weekly reports are enabled
            if (!$google_sheets_service->isFrequencyEnabled('weekly')) {
                error_log('MFX Reporting: Weekly reports are not enabled, skipping export');
                return;
            }
            
            // TODO: Implement weekly export logic
            error_log('MFX Reporting: Weekly export not yet implemented');
            
        } catch (\Exception $e) {
            error_log('MFX Reporting: Weekly export cron job failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle monthly export cron job
     */
    public function handleMonthlyExport() {
        try {
            error_log('MFX Reporting: Starting monthly export cron job');
            
            $google_sheets_service = new GoogleSheetsService();
            
            // Check if monthly reports are enabled
            if (!$google_sheets_service->isFrequencyEnabled('monthly')) {
                error_log('MFX Reporting: Monthly reports are not enabled, skipping export');
                return;
            }
            
            // TODO: Implement monthly export logic
            error_log('MFX Reporting: Monthly export not yet implemented');
            
        } catch (\Exception $e) {
            error_log('MFX Reporting: Monthly export cron job failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Manually trigger daily export (for testing)
     */
    public function triggerDailyExport($date = null) {
        $google_sheets_service = new GoogleSheetsService();
        return $google_sheets_service->exportDailyReport($date);
    }
    
    /**
     * Get next scheduled times for all cron jobs
     */
    public function getScheduledTimes() {
        return [
            'daily' => wp_next_scheduled(self::DAILY_HOOK),
            'weekly' => wp_next_scheduled(self::WEEKLY_HOOK),
            'monthly' => wp_next_scheduled(self::MONTHLY_HOOK)
        ];
    }
}
