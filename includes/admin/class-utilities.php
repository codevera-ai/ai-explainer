<?php
/**
 * Utilities class for common helper functions
 * Extracted from class-admin.php to improve maintainability
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle common utility functions used across admin functionality
 */
class ExplainerPlugin_Utilities {
    
    /**
     * Get text preview with ellipsis for long text
     * 
     * @param string $text Text to preview
     * @return string Truncated text with ellipsis if needed
     */
    public function get_text_preview($text) {
        $text = trim($text);
        if (strlen($text) <= 100) {
            return $text;
        }
        
        return substr($text, 0, 97) . '...';
    }
    
    /**
     * Format date using WordPress settings
     * 
     * @param string $date Date string to format
     * @return string Formatted date
     */
    public function format_date($date) {
        $timestamp = strtotime($date);
        if (!$timestamp) {
            return $date;
        }
        
        // Use WordPress date format
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        
        return date_i18n($date_format . ' ' . $time_format, $timestamp);
    }
    
    /**
     * Get icon for log level
     * 
     * @param string $level Log level
     * @return string Icon emoji
     */
    public function get_log_level_icon($level) {
        $icons = array(
            'error' => '🔴',
            'warning' => '🟡', 
            'info' => '🔵',
            'debug' => '🟢',
            'api' => '🌐',
            'performance' => '⚡'
        );
        
        return isset($icons[$level]) ? $icons[$level] : '⚪';
    }
    
    /**
     * Get icon for job status
     * 
     * @param string $status Job status
     * @return string Icon emoji
     */
    public function get_job_status_icon($status) {
        $icons = array(
            'pending' => '⏳',
            'processing' => '⚙️',
            'completed' => '✅',
            'failed' => '❌',
            'cancelled' => '🚫'
        );
        
        return isset($icons[$status]) ? $icons[$status] : '❓';
    }
    
    /**
     * Get language from WordPress locale
     * 
     * @param string $locale WordPress locale (e.g., en_GB, fr_FR)
     * @return string Language name
     */
    public function get_language_from_locale($locale) {
        // Map common locales to readable language names
        $language_map = array(
            'en_GB' => 'British English',
            'en_US' => 'American English',
            'en_AU' => 'Australian English',
            'en_CA' => 'Canadian English',
            'fr_FR' => 'French',
            'de_DE' => 'German',
            'es_ES' => 'Spanish',
            'it_IT' => 'Italian',
            'pt_PT' => 'Portuguese',
            'nl_NL' => 'Dutch',
            'sv_SE' => 'Swedish',
            'da_DK' => 'Danish',
            'no_NO' => 'Norwegian'
        );
        
        // Return mapped language or default to English
        return isset($language_map[$locale]) ? $language_map[$locale] : 'English';
    }
}