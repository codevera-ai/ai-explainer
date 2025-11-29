<?php
/**
 * Rate Limiter for Real-Time System
 * 
 * Provides comprehensive rate limiting and abuse prevention
 * for real-time endpoints including IP-based limiting and
 * automated blocking for abuse patterns.
 * 
 * @package WP_AI_Explainer
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rate limiter for abuse prevention
 * 
 * @since 1.2.0
 */
class ExplainerPlugin_Rate_Limiter {
    
    /**
     * IP-based rate limits
     * 
     * @since 1.2.0
     * @var array
     */
    private $ip_rate_limits = array(
        'endpoint_access' => array('limit' => 25, 'window' => 60),
        'failed_auth' => array('limit' => 5, 'window' => 300),
        'connection_attempts' => array('limit' => 15, 'window' => 60)
    );
    
    /**
     * Abuse detection thresholds
     * 
     * @since 1.2.0
     * @var array
     */
    private $abuse_thresholds = array(
        'rapid_requests' => array('limit' => 50, 'window' => 60),
        'failed_auth_pattern' => array('limit' => 10, 'window' => 600),
        'topic_scanning' => array('limit' => 20, 'window' => 300)
    );
    
    /**
     * Constructor
     * 
     * @since 1.2.0
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     * 
     * @since 1.2.0
     * @return void
     */
    private function init_hooks() {
        add_action('wp_ai_explainer_cleanup_rate_limits', array($this, 'cleanup_expired_records'));
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('wp_ai_explainer_cleanup_rate_limits')) {
            wp_schedule_event(time(), 'hourly', 'wp_ai_explainer_cleanup_rate_limits');
        }
    }
    
    /**
     * Check if IP is rate limited for specific action
     * 
     * @since 1.2.0
     * @param string $ip_address IP address to check
     * @param string $action_type Type of action
     * @return bool True if rate limited, false if allowed
     */
    public function is_ip_rate_limited($ip_address, $action_type) {
        if (!isset($this->ip_rate_limits[$action_type])) {
            return false;
        }
        
        $config = $this->ip_rate_limits[$action_type];
        $key = "explainer_ip_rate_limit_{$action_type}_{$ip_address}";
        
        $attempts = get_transient($key) ?: array();
        $window_start = time() - $config['window'];
        
        // Clean old attempts
        $attempts = array_filter($attempts, function($timestamp) use ($window_start) {
            return $timestamp > $window_start;
        });
        
        return count($attempts) >= $config['limit'];
    }
    
    /**
     * Record IP action for rate limiting
     * 
     * @since 1.2.0
     * @param string $ip_address IP address
     * @param string $action_type Type of action
     * @return void
     */
    public function record_ip_action($ip_address, $action_type) {
        if (!isset($this->ip_rate_limits[$action_type])) {
            return;
        }
        
        $config = $this->ip_rate_limits[$action_type];
        $key = "explainer_ip_rate_limit_{$action_type}_{$ip_address}";
        
        $attempts = get_transient($key) ?: array();
        $attempts[] = time();
        
        // Keep only recent attempts
        $window_start = time() - $config['window'];
        $attempts = array_filter($attempts, function($timestamp) use ($window_start) {
            return $timestamp > $window_start;
        });
        
        set_transient($key, $attempts, $config['window']);
    }
    
    /**
     * Check for abuse patterns
     * 
     * @since 1.2.0
     * @param string $ip_address IP address to check
     * @param string $pattern_type Type of abuse pattern
     * @return bool True if abuse detected, false otherwise
     */
    public function detect_abuse_pattern($ip_address, $pattern_type) {
        if (!isset($this->abuse_thresholds[$pattern_type])) {
            return false;
        }
        
        $config = $this->abuse_thresholds[$pattern_type];
        $key = "explainer_abuse_detect_{$pattern_type}_{$ip_address}";
        
        $incidents = get_transient($key) ?: array();
        $window_start = time() - $config['window'];
        
        // Clean old incidents
        $incidents = array_filter($incidents, function($timestamp) use ($window_start) {
            return $timestamp > $window_start;
        });
        
        return count($incidents) >= $config['limit'];
    }
    
    /**
     * Record abuse incident
     * 
     * @since 1.2.0
     * @param string $ip_address IP address
     * @param string $pattern_type Type of abuse pattern
     * @param array $context Additional context data
     * @return void
     */
    public function record_abuse_incident($ip_address, $pattern_type, $context = array()) {
        if (!isset($this->abuse_thresholds[$pattern_type])) {
            return;
        }
        
        $config = $this->abuse_thresholds[$pattern_type];
        $key = "explainer_abuse_detect_{$pattern_type}_{$ip_address}";
        
        $incidents = get_transient($key) ?: array();
        $incidents[] = time();
        
        // Keep only recent incidents
        $window_start = time() - $config['window'];
        $incidents = array_filter($incidents, function($timestamp) use ($window_start) {
            return $timestamp > $window_start;
        });
        
        set_transient($key, $incidents, $config['window']);
        
        // Log abuse incident
        $this->log_abuse_incident($ip_address, $pattern_type, $context);
        
        // Check if we should block this IP
        if (count($incidents) >= $config['limit']) {
            $this->initiate_temporary_block($ip_address, $pattern_type);
        }
    }
    
    /**
     * Check if IP is temporarily blocked
     * 
     * @since 1.2.0
     * @param string $ip_address IP address to check
     * @return bool True if blocked, false otherwise
     */
    public function is_ip_blocked($ip_address) {
        $key = "explainer_ip_blocked_{$ip_address}";
        $block_data = get_transient($key);
        
        if ($block_data && is_array($block_data)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Initiate temporary IP block
     * 
     * @since 1.2.0
     * @param string $ip_address IP address to block
     * @param string $reason Reason for blocking
     * @return void
     */
    public function initiate_temporary_block($ip_address, $reason) {
        $block_duration = $this->get_block_duration($reason);
        $key = "explainer_ip_blocked_{$ip_address}";
        
        $block_data = array(
            'reason' => $reason,
            'blocked_at' => time(),
            'duration' => $block_duration,
            'expires_at' => time() + $block_duration
        );
        
        set_transient($key, $block_data, $block_duration);
        
        // Log the block
        $this->log_security_event('ip_blocked', array(
            'ip_address' => $ip_address,
            'reason' => $reason,
            'duration' => $block_duration,
            'expires_at' => $block_data['expires_at']
        ));
        
        // Trigger action for external handling (e.g., firewall rules)
        do_action('wp_ai_explainer_ip_blocked', $ip_address, $block_data);
    }
    
    /**
     * Get block duration based on reason
     * 
     * @since 1.2.0
     * @param string $reason Reason for blocking
     * @return int Duration in seconds
     */
    private function get_block_duration($reason) {
        $durations = array(
            'rapid_requests' => 300,     // 5 minutes
            'failed_auth_pattern' => 900, // 15 minutes
            'topic_scanning' => 600,     // 10 minutes
            'default' => 300
        );
        
        return isset($durations[$reason]) ? $durations[$reason] : $durations['default'];
    }
    
    /**
     * Get remaining quota for user action
     * 
     * @since 1.2.0
     * @param int $user_id User ID
     * @param string $action_type Type of action
     * @return int Remaining quota
     */
    public function get_remaining_quota($user_id, $action_type) {
        // Get rate limit config from endpoint security
        $security = new ExplainerPlugin_Endpoint_Security();
        
        if (!$security->is_rate_limited($user_id, $action_type)) {
            return -1; // Unlimited or not applicable
        }
        
        // This is a simplified implementation
        // In a real scenario, we'd need access to the rate limit config
        return 0;
    }
    
    /**
     * Check request frequency for abuse detection
     * 
     * @since 1.2.0
     * @param string $ip_address IP address
     * @param string $endpoint Endpoint being accessed
     * @return bool True if suspicious frequency detected
     */
    public function check_request_frequency($ip_address, $endpoint) {
        $key = "explainer_freq_check_{$ip_address}_{$endpoint}";
        $requests = get_transient($key) ?: array();
        
        $current_time = time();
        $window_start = $current_time - 60; // 1 minute window
        
        // Clean old requests
        $requests = array_filter($requests, function($timestamp) use ($window_start) {
            return $timestamp > $window_start;
        });
        
        $requests[] = $current_time;
        set_transient($key, $requests, 60);
        
        // Check if frequency is suspicious (more than 30 requests per minute)
        if (count($requests) > 30) {
            $this->record_abuse_incident($ip_address, 'rapid_requests', array(
                'endpoint' => $endpoint,
                'request_count' => count($requests)
            ));
            return true;
        }
        
        return false;
    }
    
    /**
     * Clean up expired rate limit records
     * 
     * @since 1.2.0
     * @return void
     */
    public function cleanup_expired_records() {
        global $wpdb;
        
        try {
            // Clean up expired transients (WordPress doesn't auto-clean expired transients)
            $expired_transients = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT option_name FROM {$wpdb->options} 
                     WHERE option_name LIKE %s 
                     AND option_value < UNIX_TIMESTAMP()",
                    '_transient_timeout_explainer_%'
                )
            );
            
            $cleaned_count = 0;
            foreach ($expired_transients as $timeout_option) {
                $transient_name = str_replace('_transient_timeout_', '', $timeout_option);
                if (delete_transient($transient_name)) {
                    $cleaned_count++;
                }
            }
            
            // Log cleanup
            if ($cleaned_count > 0) {
                $this->log_security_event('rate_limit_cleanup', array(
                    'cleaned_records' => $cleaned_count,
                    'total_found' => count($expired_transients)
                ));
            }
            
        } catch (Exception $e) {
            $this->log_security_event('cleanup_error', array(
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode()
            ));
        }
    }
    
    /**
     * Get IP block information
     * 
     * @since 1.2.0
     * @param string $ip_address IP address
     * @return array|false Block information or false if not blocked
     */
    public function get_ip_block_info($ip_address) {
        $key = "explainer_ip_blocked_{$ip_address}";
        return get_transient($key);
    }
    
    /**
     * Manually unblock IP address (admin function)
     * 
     * @since 1.2.0
     * @param string $ip_address IP address to unblock
     * @return bool True if unblocked, false if not blocked
     */
    public function unblock_ip($ip_address) {
        $key = "explainer_ip_blocked_{$ip_address}";
        
        if (get_transient($key)) {
            delete_transient($key);
            
            $this->log_security_event('ip_unblocked', array(
                'ip_address' => $ip_address,
                'unblocked_by' => get_current_user_id()
            ));
            
            do_action('wp_ai_explainer_ip_unblocked', $ip_address);
            return true;
        }
        
        return false;
    }
    
    /**
     * Log abuse incident
     * 
     * @since 1.2.0
     * @param string $ip_address IP address
     * @param string $pattern_type Abuse pattern type
     * @param array $context Additional context
     * @return void
     */
    private function log_abuse_incident($ip_address, $pattern_type, $context = array()) {
        $this->log_security_event('abuse_detected', array(
            'ip_address' => $ip_address,
            'pattern_type' => $pattern_type,
            'context' => $context,
            'timestamp' => time()
        ));
    }
    
    /**
     * Log security event
     * 
     * @since 1.2.0
     * @param string $event_type Type of security event
     * @param array $data Event data to log
     * @return void
     */
    private function log_security_event($event_type, $data = array()) {
        if (class_exists('ExplainerPlugin_Debug_Logger') && ExplainerPlugin_Debug_Logger::is_enabled()) {
            ExplainerPlugin_Debug_Logger::warning('Rate Limiter: ' . $event_type, 'security', $data);
        }
    }
}