<?php

namespace MFX_Reporting\Services;

class DebugLogger {
    
    private static $log_file;
    private static $enabled = true;
    
    /**
     * Initialize the logger
     */
    public static function init() {
        $plugin_dir = dirname(dirname(dirname(__FILE__))); // Go up to plugin root
        $log_dir = $plugin_dir . '/logs/';
        
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        self::$log_file = $log_dir . 'debug-' . date('Y-m-d') . '.log';
    }
    
    /**
     * Log a debug message
     */
    public static function log($message, $data = null) {
        if (!self::$enabled) {
            return;
        }
        
        if (!self::$log_file) {
            self::init();
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $formatted_message = "[$timestamp] $message";
        
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $formatted_message .= "\n" . print_r($data, true);
            } else {
                $formatted_message .= " " . $data;
            }
        }
        
        $formatted_message .= "\n";
        
        file_put_contents(self::$log_file, $formatted_message, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Enable/disable logging
     */
    public static function setEnabled($enabled) {
        self::$enabled = $enabled;
    }
    
    /**
     * Get log file path
     */
    public static function getLogFile() {
        if (!self::$log_file) {
            self::init();
        }
        return self::$log_file;
    }
    
    /**
     * Clear today's log file
     */
    public static function clearLog() {
        if (!self::$log_file) {
            self::init();
        }
        
        if (file_exists(self::$log_file)) {
            unlink(self::$log_file);
        }
    }
}
