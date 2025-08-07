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
    const WEEKLY_HOOK = 'mfx_reporting_weekly_export';
    const MONTHLY_HOOK = 'mfx_reporting_monthly_export';
    
    /**
     * Register all cron jobs
     */
    public function registerCronJobs() {
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
        wp_clear_scheduled_hook(self::WEEKLY_HOOK);
        wp_clear_scheduled_hook(self::MONTHLY_HOOK);
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
            
            // Export weekly report with comprehensive metrics
            $result = $google_sheets_service->exportComprehensiveReport('weekly');
            
            if ($result['success']) {
                error_log('MFX Reporting: Weekly export completed successfully - ' . $result['message']);
            } else {
                error_log('MFX Reporting: Weekly export failed - ' . $result['message']);
            }
            
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
            
            // Export monthly report with comprehensive metrics
            $result = $google_sheets_service->exportComprehensiveReport('monthly');
            
            if ($result['success']) {
                error_log('MFX Reporting: Monthly export completed successfully - ' . $result['message']);
            } else {
                error_log('MFX Reporting: Monthly export failed - ' . $result['message']);
            }
            
        } catch (\Exception $e) {
            error_log('MFX Reporting: Monthly export cron job failed: ' . $e->getMessage());
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
     * Get next scheduled times for all cron jobs
     */
    public function getScheduledTimes() {
        return [
            'weekly' => wp_next_scheduled(self::WEEKLY_HOOK),
            'monthly' => wp_next_scheduled(self::MONTHLY_HOOK)
        ];
    }
}
