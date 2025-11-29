<?php
/**
 * Plugin-specific Debug Logger for AI Explainer
 * 
 * Provides centralized sectioned logging functionality that outputs to the plugin's
 * own debug log file instead of WordPress core debug.log with configurable sections
 *
 * @package WPAIExplainer
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Debug logger class for plugin-specific sectioned logging
 */
class ExplainerPlugin_Debug_Logger {
    
    /**
     * Log file path
     * @var string
     */
    private static $log_file = null;
    
    /**
     * Whether debug logging is enabled
     * @var bool
     */
    private static $enabled = null;
    
    /**
     * Debug sections configuration
     * @var array
     */
    private static $debug_sections = null;
    
    /**
     * Default debug sections with descriptions
     * @var array
     */
    private static $default_sections = array(
        'core' => array('enabled' => true, 'description' => 'Core plugin functionality'),
        'event_bus' => array('enabled' => false, 'description' => 'Event bus and webhook system'),
        'job_queue' => array('enabled' => false, 'description' => 'Job queue processing'),
        'content_generator' => array('enabled' => false, 'description' => 'Content generation'),
        'blog_creator' => array('enabled' => false, 'description' => 'Blog creation widget'),
        'api_proxy' => array('enabled' => false, 'description' => 'API proxy requests'),
        'selection_tracker' => array('enabled' => false, 'description' => 'Text selection tracking'),
        'admin' => array('enabled' => false, 'description' => 'Admin panel functionality'),
        'database' => array('enabled' => false, 'description' => 'Database operations'),
        'background_processor' => array('enabled' => false, 'description' => 'Background job processing'),
        'realtime_adapter' => array('enabled' => false, 'description' => 'Real-time adapter (JavaScript)'),
        'transport_detector' => array('enabled' => false, 'description' => 'Transport detection (JavaScript)'),
        'performance' => array('enabled' => false, 'description' => 'Performance metrics'),
        'security' => array('enabled' => false, 'description' => 'Security operations'),
        'cron' => array('enabled' => false, 'description' => 'Cron job execution'),
        'migration' => array('enabled' => false, 'description' => 'Database migrations'),
        'config' => array('enabled' => false, 'description' => 'Configuration loading'),
        'webhook' => array('enabled' => false, 'description' => 'Webhook emission'),
        'formatter' => array('enabled' => false, 'description' => 'Data formatting'),
        'permissions' => array('enabled' => false, 'description' => 'Permission checks'),
        'validation' => array('enabled' => false, 'description' => 'Input validation'),
        'cache' => array('enabled' => false, 'description' => 'Caching operations'),
        'plugin_init' => array('enabled' => false, 'description' => 'Plugin initialization'),
        'ajax' => array('enabled' => false, 'description' => 'AJAX requests and responses'),
        'javascript' => array('enabled' => false, 'description' => 'JavaScript console output (captured)')
    );
    
    /**
     * Initialize the logger
     */
    public static function init() {
        if (self::$log_file === null) {
            self::$log_file = EXPLAINER_PLUGIN_PATH . 'debug.log';
        }
        
        if (self::$enabled === null) {
            // Check if we can load from config file system
            if (class_exists('ExplainerPlugin_Config')) {
                self::$enabled = ExplainerPlugin_Config::get_global('debug_logging.enabled', false);
            } else {
                // Fallback to WP_DEBUG or specific debug mode setting
                self::$enabled = (defined('WP_DEBUG') && WP_DEBUG) || get_option('explainer_debug_mode', false);
            }
        }
        
        if (self::$debug_sections === null) {
            self::load_debug_sections();
        }
    }
    
    /**
     * Load debug section settings from config file or WordPress options
     */
    private static function load_debug_sections() {
        // Try to load from config file system first
        if (class_exists('ExplainerPlugin_Config')) {
            $config_sections = ExplainerPlugin_Config::get_global('debug_logging.sections', array());
            
            if (!empty($config_sections)) {
                // Use config file sections
                self::$debug_sections = array();
                foreach ($config_sections as $section => $enabled) {
                    self::$debug_sections[$section] = array(
                        'enabled' => $enabled,
                        'description' => isset(self::$default_sections[$section]) ? 
                                        self::$default_sections[$section]['description'] : 
                                        'Custom section'
                    );
                }
                return;
            }
        }
        
        // Fallback to WordPress options
        $saved_sections = get_option('explainer_debug_sections', array());
        
        // Merge with defaults, preserving user settings
        self::$debug_sections = array();
        foreach (self::$default_sections as $section => $config) {
            if (isset($saved_sections[$section])) {
                self::$debug_sections[$section] = array(
                    'enabled' => $saved_sections[$section]['enabled'] ?? $config['enabled'],
                    'description' => $config['description']
                );
            } else {
                self::$debug_sections[$section] = $config;
            }
        }
    }
    
    /**
     * Save debug section settings to WordPress options
     */
    private static function save_debug_sections() {
        $sections_to_save = array();
        foreach (self::$debug_sections as $section => $config) {
            $sections_to_save[$section] = array('enabled' => $config['enabled']);
        }
        update_option('explainer_debug_sections', $sections_to_save);
    }
    
    /**
     * Check if a debug section is enabled
     * 
     * @param string $section Section name
     * @return bool True if section is enabled
     */
    public static function is_section_enabled($section) {
        self::init();
        
        // Normalise section name for common variations
        $section = self::normalize_section_name($section);
        
        return isset(self::$debug_sections[$section]) && self::$debug_sections[$section]['enabled'];
    }
    
    /**
     * Enable a debug section
     * 
     * @param string $section Section name
     * @return bool True on success
     */
    public static function enable_section($section) {
        self::init();
        $section = self::normalize_section_name($section);
        
        if (isset(self::$debug_sections[$section])) {
            self::$debug_sections[$section]['enabled'] = true;
            self::save_debug_sections();
            return true;
        }
        return false;
    }
    
    /**
     * Disable a debug section
     * 
     * @param string $section Section name  
     * @return bool True on success
     */
    public static function disable_section($section) {
        self::init();
        $section = self::normalize_section_name($section);
        
        if (isset(self::$debug_sections[$section])) {
            self::$debug_sections[$section]['enabled'] = false;
            self::save_debug_sections();
            return true;
        }
        return false;
    }
    
    /**
     * Enable all debug sections
     */
    public static function enable_all_sections() {
        self::init();
        foreach (self::$debug_sections as $section => $config) {
            self::$debug_sections[$section]['enabled'] = true;
        }
        self::save_debug_sections();
    }
    
    /**
     * Disable all debug sections
     */
    public static function disable_all_sections() {
        self::init();
        foreach (self::$debug_sections as $section => $config) {
            self::$debug_sections[$section]['enabled'] = false;
        }
        self::save_debug_sections();
    }
    
    /**
     * Get all debug sections with their status and descriptions
     * 
     * @return array Array of sections with enabled status and descriptions
     */
    public static function get_all_sections() {
        self::init();
        return self::$debug_sections;
    }
    
    /**
     * Normalise section names to handle common variations
     * 
     * @param string $section Raw section name
     * @return string Normalised section name
     */
    private static function normalize_section_name($section) {
        // Ensure section is a string
        if (!is_string($section)) {
            $section = is_array($section) ? implode('_', $section) : strval($section);
        }
        
        // Convert common patterns
        $section = strtolower($section);
        $section = str_replace(array(' ', '-'), '_', $section);
        
        // Handle common variations
        $mappings = array(
            'sse_endpoint' => 'sse_endpoint',
            'sse_permissions' => 'sse_permissions', 
            'sse_diagnostic' => 'sse_diagnostic',
            'sse_diagnostic_permissions' => 'sse_diagnostic',
            'plugin_init' => 'plugin_init',
            'event_bus' => 'event_bus',
            'job_queue' => 'job_queue',
            'background_processor' => 'background_processor',
            'content_generator' => 'content_generator',
            'blog_creator' => 'blog_creator'
        );
        
        return $mappings[$section] ?? $section;
    }
    
    /**
     * Log a message to the plugin debug file
     * 
     * @param string $message The message to log
     * @param string $level Log level (info, warning, error, debug)
     * @param string $section Debug section (default: core)
     * @param array $context Additional context data
     */
    public static function log($message, $level = 'info', $section = 'core', $context = array()) {
        self::init();
        
        if (!self::$enabled) {
            return;
        }
        
        // Check if this section is enabled for logging
        if (!self::is_section_enabled($section)) {
            return;
        }
        
        $timestamp = current_time('mysql');
        $level_formatted = strtoupper($level);
        $section_formatted = strtoupper($section);
        
        // Format the log entry
        $log_entry = "[{$timestamp}] [{$level_formatted}] [{$section_formatted}] {$message}";
        
        // Add context data if provided
        if (!empty($context)) {
            $context_json = wp_json_encode($context, JSON_UNESCAPED_SLASHES);
            $log_entry .= " | Context: {$context_json}";
        }
        
        // Add memory usage for debug level
        if ($level === 'debug') {
            $memory_mb = round(memory_get_usage() / 1024 / 1024, 2);
            $log_entry .= " | Memory: {$memory_mb}MB";
        }
        
        $log_entry .= PHP_EOL;
        
        // Write to plugin log file with file locking
        file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log info message
     * 
     * @param string $message Message to log
     * @param string $section Section name (default: core)
     * @param array $context Additional context data
     */
    public static function info($message, $section = 'core', $context = array()) {
        self::log($message, 'info', $section, $context);
    }
    
    /**
     * Log warning message
     * 
     * @param string $message Message to log  
     * @param string $section Section name (default: core)
     * @param array $context Additional context data
     */
    public static function warning($message, $section = 'core', $context = array()) {
        self::log($message, 'warning', $section, $context);
    }
    
    /**
     * Log error message
     * 
     * @param string $message Message to log
     * @param string $section Section name (default: core)
     * @param array $context Additional context data
     */
    public static function error($message, $section = 'core', $context = array()) {
        self::log($message, 'error', $section, $context);
    }
    
    /**
     * Log debug message
     * 
     * @param string $message Message to log
     * @param string $section Section name (default: core)
     * @param array $context Additional context data
     */
    public static function debug($message, $section = 'core', $context = array()) {
        self::log($message, 'debug', $section, $context);
    }
    
    /**
     * Log AJAX request details
     * 
     * @param string $action AJAX action name
     * @param array $data Request data (will be sanitised)
     */
    public static function ajax_request($action, $data = array()) {
        // Sanitise sensitive data
        $safe_data = $data;
        if (isset($safe_data['nonce'])) {
            $safe_data['nonce'] = '***HIDDEN***';
        }
        if (isset($safe_data['_wpnonce'])) {
            $safe_data['_wpnonce'] = '***HIDDEN***';
        }
        
        self::log("AJAX Request: {$action}", 'debug', 'ajax', array('data' => $safe_data));
    }
    
    /**
     * Log AJAX response
     * 
     * @param string $action AJAX action name
     * @param bool $success Whether request was successful
     * @param mixed $data Response data summary
     */
    public static function ajax_response($action, $success, $data = null) {
        $status = $success ? 'SUCCESS' : 'ERROR';
        $context = array('status' => $status);
        
        if ($data !== null) {
            if (is_array($data) && isset($data['items'])) {
                $context['items_count'] = count($data['items']);
            } else {
                $context['response_summary'] = substr(wp_json_encode($data), 0, 200);
            }
        }
        
        self::log("AJAX Response: {$action}", $success ? 'info' : 'error', 'ajax', $context);
    }
    
    /**
     * Log database query
     * 
     * @param string $query SQL query (will be truncated if too long)
     * @param int $results Number of results returned
     * @param string $error Database error if any
     */
    public static function db_query($query, $results = null, $error = '') {
        // Truncate very long queries
        $short_query = strlen($query) > 200 ? substr($query, 0, 200) . '...' : $query;
        $context = array('query' => $short_query);
        
        if ($results !== null) {
            $context['results_count'] = $results;
        }
        
        if (!empty($error)) {
            $context['error'] = $error;
            self::log("Database query failed", 'error', 'database', $context);
        } else {
            self::log("Database query executed", 'debug', 'database', $context);
        }
    }
    
    /**
     * Log raw error_log style message, attempting to extract section from message
     * 
     * @param string $message Log message
     * @param string $fallback_section Fallback section if none detected
     */
    public static function error_log_replacement($message, $fallback_section = 'core') {
        $section = $fallback_section;
        
        // Try to extract section from common patterns
        if (preg_match('/^\[([A-Z_\s]+)(?:\s+DEBUG)?\]\s*(.+)/', $message, $matches)) {
            $section = self::normalize_section_name($matches[1]);
            $message = $matches[2];
        } elseif (preg_match('/^(\w+):\s*(.+)/', $message, $matches)) {
            $section = self::normalize_section_name($matches[1]);
            $message = $matches[2];
        }
        
        self::log($message, 'debug', $section);
    }
    
    /**
     * Clear the debug log
     */
    public static function clear() {
        self::init();
        if (file_exists(self::$log_file)) {
            file_put_contents(self::$log_file, '');
        }
    }
    
    /**
     * Get the log file path
     * 
     * @return string Log file path
     */
    public static function get_log_file() {
        self::init();
        return self::$log_file;
    }
    
    /**
     * Check if logging is enabled
     * 
     * @return bool Whether logging is enabled
     */
    public static function is_enabled() {
        self::init();
        return self::$enabled;
    }
    
    /**
     * Enable or disable logging
     * 
     * @param bool $enabled Whether to enable logging
     */
    public static function set_enabled($enabled) {
        self::$enabled = (bool) $enabled;
    }
    
    /**
     * Get recent log entries
     * 
     * @param int $lines Number of lines to return
     * @return array Array of log lines
     */
    public static function get_recent_logs($lines = 50) {
        self::init();
        
        if (!file_exists(self::$log_file)) {
            return array();
        }
        
        $content = file_get_contents(self::$log_file);
        $log_lines = explode(PHP_EOL, trim($content));
        
        return array_slice($log_lines, -$lines);
    }
    
    /**
     * Get log file size in bytes
     * 
     * @return int File size in bytes
     */
    public static function get_log_file_size() {
        self::init();
        
        if (file_exists(self::$log_file)) {
            return filesize(self::$log_file);
        }
        return 0;
    }
    
    /**
     * Rotate log file if it gets too large
     *
     * @param int $max_size_mb Maximum file size in MB before rotation
     */
    public static function rotate_log_if_needed($max_size_mb = 10) {
        self::init();

        $max_size_bytes = $max_size_mb * 1024 * 1024;

        if (self::get_log_file_size() > $max_size_bytes) {
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }

            $backup_file = self::$log_file . '.old';

            // Move current log to backup
            if ($wp_filesystem->exists(self::$log_file)) {
                $wp_filesystem->move(self::$log_file, $backup_file, true);
            }

            // Create new empty log file
            $wp_filesystem->put_contents(self::$log_file, '');
            $wp_filesystem->chmod(self::$log_file, 0644);

            // Log the rotation
            self::info('Log file rotated due to size limit', 'core', array(
                'old_size_mb' => round($max_size_bytes / 1024 / 1024, 2),
                'backup_file' => $backup_file
            ));
        }
    }
}

/**
 * Global helper functions for sectioned debug logging
 * These replace the existing logging functions and route all output to plugin debug.log
 */

/**
 * Log debug message to plugin debug.log
 * 
 * @param string $message Log message
 * @param array $context Additional context data
 * @param string $section Debug section
 */
if (!function_exists('explainer_log_debug')) {
    function explainer_log_debug($message, $context = array(), $section = 'core') {
        ExplainerPlugin_Debug_Logger::debug($message, $section, $context);
    }
}

/**
 * Log info message to plugin debug.log
 * 
 * @param string $message Log message
 * @param array $context Additional context data
 * @param string $section Debug section
 */
if (!function_exists('explainer_log_info')) {
    function explainer_log_info($message, $context = array(), $section = 'core') {
        ExplainerPlugin_Debug_Logger::info($message, $section, $context);
    }
}

/**
 * Log warning message to plugin debug.log
 * 
 * @param string $message Log message
 * @param array $context Additional context data
 * @param string $section Debug section
 */
if (!function_exists('explainer_log_warning')) {
    function explainer_log_warning($message, $context = array(), $section = 'core') {
        ExplainerPlugin_Debug_Logger::warning($message, $section, $context);
    }
}

/**
 * Log error message to plugin debug.log
 * 
 * @param string $message Log message
 * @param array $context Additional context data
 * @param string $section Debug section
 */
if (!function_exists('explainer_log_error')) {
    function explainer_log_error($message, $context = array(), $section = 'core') {
        ExplainerPlugin_Debug_Logger::error($message, $section, $context);
    }
}

/**
 * Replacement for WordPress error_log() that routes to plugin debug.log
 * This function can be used to override error_log calls throughout the plugin
 * 
 * @param string $message Log message
 * @param int $message_type Message type (ignored, always logs to file)
 * @param string $destination Destination (ignored)
 * @param string $extra_headers Extra headers (ignored)
 */
if (!function_exists('explainer_error_log')) {
    function explainer_error_log($message, $message_type = 0, $destination = null, $extra_headers = null) {
        ExplainerPlugin_Debug_Logger::error_log_replacement($message);
    }
}