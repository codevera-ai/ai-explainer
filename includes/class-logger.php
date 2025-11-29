<?php
/**
 * Logger class for comprehensive debugging and logging functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unified logging system for the AI Explainer plugin
 */
class ExplainerPlugin_Logger {
    
    /**
     * Log levels
     */
    const LOG_ERROR = 'error';
    const LOG_WARNING = 'warning';
    const LOG_INFO = 'info';
    const LOG_DEBUG = 'debug';
    const LOG_API = 'api';
    const LOG_PERFORMANCE = 'performance';
    
    /**
     * Maximum number of log lines to keep in debug file
     */
    const MAX_LOG_LINES = 1000;
    
    /**
     * Debug log file path
     */
    private $debug_log_path;
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Debug mode flag
     */
    private $debug_mode = false;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor for singleton
     */
    private function __construct() {
        $this->debug_mode = get_option('explainer_debug_mode', false);
        $this->debug_log_path = EXPLAINER_PLUGIN_PATH . 'debug.log';
    }
    
    /**
     * Main logging method
     * 
     * @param string $level Log level (error, warning, info, debug, api, performance)
     * @param string $message Log message
     * @param array $context Additional context data
     * @param string $component Component/class that is logging
     */
    public function log($level, $message, $context = array(), $component = '') {
        // Don't log if debug mode is disabled (except for errors)
        if (!$this->debug_mode && $level !== self::LOG_ERROR) {
            return;
        }
        
        // Write directly to plugin debug file
        $this->write_to_debug_file($level, $message, $context, $component);
    }
    
    /**
     * Convenience methods for different log levels
     */
    public function error($message, $context = array(), $component = '') {
        $this->log(self::LOG_ERROR, $message, $context, $component);
    }
    
    public function warning($message, $context = array(), $component = '') {
        $this->log(self::LOG_WARNING, $message, $context, $component);
    }
    
    public function info($message, $context = array(), $component = '') {
        $this->log(self::LOG_INFO, $message, $context, $component);
    }
    
    public function debug($message, $context = array(), $component = '') {
        $this->log(self::LOG_DEBUG, $message, $context, $component);
    }
    
    public function api($message, $context = array(), $component = '') {
        $this->log(self::LOG_API, $message, $context, $component);
    }
    
    public function performance($message, $context = array(), $component = '') {
        $this->log(self::LOG_PERFORMANCE, $message, $context, $component);
    }
    
    /**
     * Log API requests with comprehensive data
     * 
     * @param string $provider AI provider (openai, claude)
     * @param string $endpoint API endpoint
     * @param array $request_data Request data (will be sanitized)
     * @param array $response_data Response data (will be sanitized)
     * @param float $duration Request duration in seconds
     * @param bool $success Whether the request was successful
     */
    public function log_api_request($provider, $endpoint, $request_data = array(), $response_data = array(), $duration = 0, $success = true) {
        // Sanitize sensitive data
        $safe_request = $this->sanitize_api_data($request_data);
        $safe_response = $this->sanitize_api_data($response_data);
        
        $context = array(
            'provider' => $provider,
            'endpoint' => $endpoint,
            'duration' => round($duration, 3),
            'success' => $success,
            'request_size' => strlen(json_encode($safe_request)),
            'response_size' => strlen(json_encode($safe_response)),
            'request_data' => $safe_request,
            'response_data' => $safe_response
        );
        
        $message = sprintf('%s API %s: %s (%.3fs)', ucfirst($provider), esc_html($success ? 'success' : 'failure'), esc_html($endpoint), esc_html($duration));
        
        $this->api($message, $context, 'API_Proxy');
    }
    
    /**
     * Log performance metrics
     * 
     * @param string $operation Operation name
     * @param float $duration Duration in seconds
     * @param array $metrics Additional metrics
     */
    public function log_performance($operation, $duration, $metrics = array()) {
        $context = array_merge(array(
            'operation' => $operation,
            'duration' => round($duration, 3),
            'memory_before' => $metrics['memory_before'] ?? null,
            'memory_after' => $metrics['memory_after'] ?? null,
            'memory_delta' => isset($metrics['memory_before'], $metrics['memory_after']) 
                ? $metrics['memory_after'] - $metrics['memory_before'] : null
        ), $metrics);
        
        $message = sprintf('Performance: %s completed in %.3fs', esc_html($operation), esc_html($duration));
        
        $this->performance($message, $context, 'Performance');
    }
    
    /**
     * Write log entry to plugin debug file with rotation
     */
    private function write_to_debug_file($level, $message, $context = array(), $component = '') {
        // Format the log entry
        $timestamp = current_time('Y-m-d H:i:s');
        $memory = round(memory_get_usage(true) / 1024 / 1024, 2);
        
        $formatted_message = sprintf("[%s] [%s] [%s] %s", esc_html($timestamp), strtoupper($level), esc_html($component ?: 'UNKNOWN'), esc_html($message));
        
        // Add context if provided (but limit size)
        if (!empty($context)) {
            $context_str = $this->format_context($context);
            $formatted_message .= " | Context: {$context_str}";
        }
        
        // Add memory usage
        $formatted_message .= " | Memory: {$memory}MB\n";
        
        // Rotate log if it's getting too large
        $this->rotate_log_if_needed();
        
        // Write to file
        file_put_contents($this->debug_log_path, $formatted_message, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Format context data for logging (with size limits)
     */
    private function format_context($context) {
        if (empty($context)) {
            return '';
        }
        
        // Convert to JSON but limit size
        $json_context = json_encode($context, JSON_UNESCAPED_SLASHES);
        
        // If context is too large, truncate it
        if (strlen($json_context) > 500) {
            $json_context = substr($json_context, 0, 497) . '...';
        }
        
        return $json_context;
    }
    
    /**
     * Rotate debug log file if it exceeds line limit
     */
    private function rotate_log_if_needed() {
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if (!$wp_filesystem->exists($this->debug_log_path)) {
            return;
        }

        // Count lines in the file
        $content = $wp_filesystem->get_contents($this->debug_log_path);
        if ($content === false) {
            return;
        }

        $line_count = substr_count($content, "\n") + 1;

        // If we exceed the limit, keep only the latest lines
        if ($line_count >= self::MAX_LOG_LINES) {
            $lines = explode("\n", $content);
            $keep_lines = array_slice($lines, -floor(self::MAX_LOG_LINES * 0.8)); // Keep 80% of max
            $wp_filesystem->put_contents($this->debug_log_path, implode("\n", $keep_lines));
        }
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', sanitize_text_field(wp_unslash($_SERVER[$key]))) as $ip) {
                    $ip = trim($ip);

                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    }
    
    /**
     * Sanitize API data to remove sensitive information
     */
    private function sanitize_api_data($data) {
        if (is_array($data)) {
            $sanitized = array();
            foreach ($data as $key => $value) {
                // Remove or mask sensitive keys
                if (in_array(strtolower($key), array('api_key', 'authorization', 'token', 'password', 'secret'))) {
                    $sanitized[$key] = '[REDACTED]';
                } else if (is_array($value) || is_object($value)) {
                    $sanitized[$key] = $this->sanitize_api_data($value);
                } else {
                    $sanitized[$key] = $value;
                }
            }
            return $sanitized;
        }
        
        return $data;
    }
    
    /**
     * Get recent logs from debug file
     * 
     * @param int $limit Maximum number of lines to return
     * @return array Log lines (newest first)
     */
    public function get_logs($limit = 100) {
        if (!file_exists($this->debug_log_path)) {
            return array();
        }
        
        $lines = file($this->debug_log_path);
        if (!$lines) {
            return array();
        }
        
        // Return newest lines first
        $lines = array_reverse($lines);
        return array_slice($lines, 0, $limit);
    }
    
    /**
     * Clear debug log file
     */
    public function clear_logs() {
        if (file_exists($this->debug_log_path)) {
            file_put_contents($this->debug_log_path, '', LOCK_EX);
        }
    }
    
    /**
     * Get debug log file statistics
     */
    public function get_log_stats() {
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $stats = array(
            'file_exists' => $wp_filesystem->exists($this->debug_log_path),
            'file_size' => 0,
            'line_count' => 0,
            'file_path' => $this->debug_log_path,
            'last_modified' => null
        );

        if ($wp_filesystem->exists($this->debug_log_path)) {
            $stats['file_size'] = $wp_filesystem->size($this->debug_log_path);
            $stats['last_modified'] = wp_date('Y-m-d H:i:s', $wp_filesystem->mtime($this->debug_log_path));

            // Count lines
            $content = $wp_filesystem->get_contents($this->debug_log_path);
            if ($content !== false) {
                $stats['line_count'] = substr_count($content, "\n") + 1;
            }
        }

        return $stats;
    }
    
    /**
     * Get debug log file path
     */
    public function get_debug_log_path() {
        return $this->debug_log_path;
    }
    
    /**
     * Check if debug mode is enabled
     */
    public function is_debug_enabled() {
        return $this->debug_mode;
    }
    
    /**
     * Refresh debug mode setting
     */
    public function refresh_debug_mode() {
        $this->debug_mode = get_option('explainer_debug_mode', false);
    }
}

// Convenience function for global access
if (!function_exists('explainer_log')) {
    function explainer_log($level, $message, $context = array(), $component = '') {
        ExplainerPlugin_Logger::get_instance()->log($level, $message, $context, $component);
    }
}

// Convenience shorthand functions
if (!function_exists('explainer_log_error')) {
    function explainer_log_error($message, $context = array(), $component = '') {
        ExplainerPlugin_Logger::get_instance()->error($message, $context, $component);
    }
}

if (!function_exists('explainer_log_warning')) {
    function explainer_log_warning($message, $context = array(), $component = '') {
        ExplainerPlugin_Logger::get_instance()->warning($message, $context, $component);
    }
}

if (!function_exists('explainer_log_info')) {
    function explainer_log_info($message, $context = array(), $component = '') {
        ExplainerPlugin_Logger::get_instance()->info($message, $context, $component);
    }
}

if (!function_exists('explainer_log_debug')) {
    function explainer_log_debug($message, $context = array(), $component = '') {
        ExplainerPlugin_Logger::get_instance()->debug($message, $context, $component);
    }
}

if (!function_exists('explainer_log_api')) {
    function explainer_log_api($message, $context = array(), $component = '') {
        ExplainerPlugin_Logger::get_instance()->api($message, $context, $component);
    }
}

if (!function_exists('explainer_log_performance')) {
    function explainer_log_performance($message, $context = array(), $component = '') {
        ExplainerPlugin_Logger::get_instance()->performance($message, $context, $component);
    }
}