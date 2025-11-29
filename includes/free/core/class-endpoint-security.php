<?php
/**
 * Endpoint Security Manager
 * 
 * Provides comprehensive security for real-time endpoints including
 * authentication, authorisation, rate limiting, and payload filtering.
 * 
 * @package WP_AI_Explainer
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Endpoint security manager for real-time system
 * 
 * @since 1.2.0
 */
class ExplainerPlugin_Endpoint_Security {
    
    /**
     * Topic permission matrix
     * 
     * @since 1.2.0
     * @var array
     */
    private $topic_permissions = array(
        'job_queue:*' => array('manage_options'),
        'job_queue:list' => array('manage_options', 'edit_posts'),
        'content_generation:*' => array('manage_options'),
        'content_generation:list' => array('manage_options', 'edit_posts'),
        'selection_tracker:*' => array('manage_options'),
        'selection_tracker:list' => array('read')
    );
    
    /**
     * Rate limit configuration
     * 
     * @since 1.2.0
     * @var array
     */
    private $rate_limits = array(
        'stream_connection' => array('limit' => 10, 'window' => 60),
        'poll_request' => array('limit' => 100, 'window' => 60),
        'topic_subscription' => array('limit' => 5, 'concurrent' => true)
    );
    
    /**
     * Payload redaction rules
     * 
     * @since 1.2.0
     * @var array
     */
    private $redaction_rules = array(
        'job_queue' => array(
            'sensitive_fields' => array('api_key', 'auth_token', 'config'),
            'user_specific' => array('created_by', 'assigned_to')
        ),
        'content_generation' => array(
            'sensitive_fields' => array('provider_config', 'api_response_raw'),
            'public_fields' => array('title', 'status', 'created_at')
        )
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
        add_action('rest_api_init', array($this, 'register_security_headers'));
        add_filter('rest_pre_dispatch', array($this, 'apply_security_checks'), 10, 3);
    }
    
    /**
     * Authenticate request and return user
     * 
     * @since 1.2.0
     * @param WP_REST_Request $request The REST request
     * @return WP_User|WP_Error User object on success, WP_Error on failure
     */
    public function authenticate_request($request) {
        // Check WordPress user session first
        $user = wp_get_current_user();
        
        if ($user && $user->exists()) {
            return $user;
        }
        
        // Check for nonce-based authentication (frontend)
        $nonce = $request->get_header('X-WP-Nonce');
        if ($nonce) {
            $topic = $request->get_param('topic');
            if ($this->validate_nonce($nonce, $topic)) {
                // For nonce-based auth, return current user or anonymous
                return wp_get_current_user();
            }
        }
        
        // Check for cookie authentication
        if (is_user_logged_in()) {
            return wp_get_current_user();
        }
        
        return new WP_Error(
            'authentication_required',
            'Authentication is required to access this endpoint',
            array('status' => 401)
        );
    }
    
    /**
     * Check if user can access specific topic
     * 
     * @since 1.2.0
     * @param WP_User $user The user object
     * @param string $topic The topic to check access for
     * @return bool True if user has access, false otherwise
     */
    public function check_topic_permissions($user, $topic) {
        if (!$user || !$user->exists()) {
            return false;
        }
        
        // Check exact topic match first
        if (isset($this->topic_permissions[$topic])) {
            $required_caps = $this->topic_permissions[$topic];
            return $this->user_has_any_capability($user, $required_caps);
        }
        
        // Check wildcard patterns
        foreach ($this->topic_permissions as $pattern => $required_caps) {
            if (strpos($pattern, '*') !== false) {
                $pattern_regex = str_replace('*', '.*', preg_quote($pattern, '/'));
                if (preg_match('/^' . $pattern_regex . '$/', $topic)) {
                    return $this->user_has_any_capability($user, $required_caps);
                }
            }
        }
        
        // Default deny
        return false;
    }
    
    /**
     * Generate nonce for frontend topics
     * 
     * @since 1.2.0
     * @param array $topic_list List of topics to generate nonce for
     * @return string Generated nonce
     */
    public function generate_frontend_nonce($topic_list = array()) {
        $action = 'wp_ai_explainer_realtime';
        
        if (!empty($topic_list)) {
            $action .= '_' . implode('_', $topic_list);
        }
        
        return wp_create_nonce($action);
    }
    
    /**
     * Validate nonce for topic access
     * 
     * @since 1.2.0
     * @param string $nonce The nonce to validate
     * @param string $topic The topic being accessed
     * @return bool True if valid, false otherwise
     */
    public function validate_nonce($nonce, $topic) {
        $action = 'wp_ai_explainer_realtime_' . $topic;
        
        // Also check general realtime nonce
        $general_action = 'wp_ai_explainer_realtime';
        
        return wp_verify_nonce($nonce, $action) || wp_verify_nonce($nonce, $general_action);
    }
    
    /**
     * Check if user is rate limited for specific action
     * 
     * @since 1.2.0
     * @param int $user_id User ID to check
     * @param string $action_type Type of action (stream_connection, poll_request, etc.)
     * @return bool True if rate limited, false if allowed
     */
    public function is_rate_limited($user_id, $action_type) {
        if (!isset($this->rate_limits[$action_type])) {
            return false;
        }
        
        $config = $this->rate_limits[$action_type];
        $key = "explainer_rate_limit_{$action_type}_{$user_id}";
        
        if (!empty($config['concurrent'])) {
            // Concurrent limit check
            $current_count = get_transient($key) ?: 0;
            return $current_count >= $config['limit'];
        } else {
            // Time window limit check
            $attempts = get_transient($key) ?: array();
            $window_start = time() - $config['window'];
            
            // Clean old attempts
            $attempts = array_filter($attempts, function($timestamp) use ($window_start) {
                return $timestamp > $window_start;
            });
            
            return count($attempts) >= $config['limit'];
        }
    }
    
    /**
     * Record action for rate limiting
     * 
     * @since 1.2.0
     * @param int $user_id User ID
     * @param string $action_type Type of action
     * @return void
     */
    public function record_action($user_id, $action_type) {
        if (!isset($this->rate_limits[$action_type])) {
            return;
        }
        
        $config = $this->rate_limits[$action_type];
        $key = "explainer_rate_limit_{$action_type}_{$user_id}";
        
        if (!empty($config['concurrent'])) {
            // Increment concurrent count
            $current_count = get_transient($key) ?: 0;
            set_transient($key, $current_count + 1, 300); // 5 minute expiry
        } else {
            // Add timestamp to window
            $attempts = get_transient($key) ?: array();
            $attempts[] = time();
            
            // Keep only recent attempts
            $window_start = time() - $config['window'];
            $attempts = array_filter($attempts, function($timestamp) use ($window_start) {
                return $timestamp > $window_start;
            });
            
            set_transient($key, $attempts, $config['window']);
        }
    }
    
    /**
     * Release concurrent rate limit (for connection closes)
     * 
     * @since 1.2.0
     * @param int $user_id User ID
     * @param string $action_type Type of action
     * @return void
     */
    public function release_concurrent_limit($user_id, $action_type) {
        if (!isset($this->rate_limits[$action_type]) || 
            empty($this->rate_limits[$action_type]['concurrent'])) {
            return;
        }
        
        $key = "explainer_rate_limit_{$action_type}_{$user_id}";
        $current_count = get_transient($key) ?: 0;
        
        if ($current_count > 0) {
            set_transient($key, $current_count - 1, 300);
        }
    }
    
    /**
     * Filter event payload for user
     * 
     * @since 1.2.0
     * @param array $event Event data to filter
     * @param int $user_id User ID to filter for
     * @return array Filtered event data
     */
    public function filter_event_payload($event, $user_id) {
        if (empty($event['type'])) {
            return $event;
        }
        
        $event_type = explode(':', $event['type'])[0];
        
        if (!isset($this->redaction_rules[$event_type])) {
            return $event;
        }
        
        $rules = $this->redaction_rules[$event_type];
        
        // Remove sensitive fields
        if (!empty($rules['sensitive_fields']) && isset($event['data'])) {
            foreach ($rules['sensitive_fields'] as $field) {
                unset($event['data'][$field]);
            }
        }
        
        // Filter user-specific data
        if (!empty($rules['user_specific']) && isset($event['data'])) {
            foreach ($rules['user_specific'] as $field) {
                if (isset($event['data'][$field])) {
                    // Non-admin users can only see their own data
                    if (!current_user_can('manage_options') && $event['data'][$field] != $user_id) {
                        unset($event['data'][$field]);
                    }
                }
            }
        }
        
        return $event;
    }
    
    /**
     * Check stream permissions for REST endpoint
     * 
     * @since 1.2.0
     * @param WP_REST_Request $request The REST request
     * @return bool|WP_Error True if allowed, WP_Error on failure
     */
    public function check_stream_permissions($request) {
        $topic = $request->get_param('topic');
        
        if (empty($topic)) {
            return new WP_Error(
                'missing_topic',
                'Topic parameter is required',
                array('status' => 400)
            );
        }
        
        // Authenticate user
        $user = $this->authenticate_request($request);
        if (is_wp_error($user)) {
            return $user;
        }
        
        // Check topic access
        if (!$this->check_topic_permissions($user, $topic)) {
            return new WP_Error(
                'access_denied',
                'Insufficient permissions for topic: ' . $topic,
                array('status' => 403)
            );
        }
        
        // Check rate limits
        if ($this->is_rate_limited($user->ID, 'stream_connection')) {
            return new WP_Error(
                'rate_limited',
                'Connection rate limit exceeded',
                array('status' => 429)
            );
        }
        
        return true;
    }
    
    /**
     * Check polling permissions for REST endpoint
     * 
     * @since 1.2.0
     * @param WP_REST_Request $request The REST request
     * @return bool|WP_Error True if allowed, WP_Error on failure
     */
    public function check_polling_permissions($request) {
        $topic = $request->get_param('topic');
        
        if (empty($topic)) {
            return new WP_Error(
                'missing_topic',
                'Topic parameter is required',
                array('status' => 400)
            );
        }
        
        // Authenticate user
        $user = $this->authenticate_request($request);
        if (is_wp_error($user)) {
            return $user;
        }
        
        // Check topic access
        if (!$this->check_topic_permissions($user, $topic)) {
            return new WP_Error(
                'access_denied',
                'Insufficient permissions for topic: ' . $topic,
                array('status' => 403)
            );
        }
        
        // Check rate limits
        if ($this->is_rate_limited($user->ID, 'poll_request')) {
            return new WP_Error(
                'rate_limited',
                'Polling rate limit exceeded',
                array('status' => 429)
            );
        }
        
        return true;
    }
    
    /**
     * Register security headers for REST API
     * 
     * @since 1.2.0
     * @return void
     */
    public function register_security_headers() {
        // Add CORS headers for real-time endpoints
        add_filter('rest_pre_serve_request', array($this, 'add_security_headers'), 10, 4);
    }
    
    /**
     * Add security headers to response
     * 
     * @since 1.2.0
     * @param bool $served Whether the request has already been served
     * @param array $result The response data
     * @param WP_REST_Request $request The request object
     * @param WP_REST_Server $server The server instance
     * @return bool Whether the request has been served
     */
    public function add_security_headers($served, $result, $request, $server) {
        $route = $request->get_route();
        
        // Only apply to our realtime endpoints
        if (strpos($route, '/wp-ai-explainer/v1/') !== false) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // SSE specific headers
            if (strpos($route, '/stream/') !== false) {
                header('Content-Type: text/event-stream; charset=utf-8');
                header('X-Accel-Buffering: no'); // Nginx
                header('Connection: keep-alive');
            }
        }
        
        return $served;
    }
    
    /**
     * Apply security checks to REST requests
     * 
     * @since 1.2.0
     * @param mixed $result Response to replace the requested version with
     * @param WP_REST_Server $server Server instance
     * @param WP_REST_Request $request Request used to generate the response
     * @return mixed Modified result or original result
     */
    public function apply_security_checks($result, $server, $request) {
        $route = $request->get_route();
        
        // Only apply to our realtime endpoints
        if (strpos($route, '/wp-ai-explainer/v1/') === false) {
            return $result;
        }
        
        // Log security events
        $this->log_security_event('endpoint_access', array(
            'route' => $route,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $request->get_header('User-Agent')
        ));
        
        return $result;
    }
    
    /**
     * Get allowed topics for user
     * 
     * @since 1.2.0
     * @param int $user_id User ID
     * @return array List of allowed topics
     */
    public function get_allowed_topics_for_user($user_id) {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return array();
        }
        
        $allowed_topics = array();
        
        foreach ($this->topic_permissions as $topic => $required_caps) {
            if ($this->user_has_any_capability($user, $required_caps)) {
                $allowed_topics[] = $topic;
            }
        }
        
        return $allowed_topics;
    }
    
    /**
     * Check if user has any of the required capabilities
     * 
     * @since 1.2.0
     * @param WP_User $user User object
     * @param array $capabilities Required capabilities
     * @return bool True if user has any capability, false otherwise
     */
    private function user_has_any_capability($user, $capabilities) {
        foreach ($capabilities as $capability) {
            if ($user->has_cap($capability)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get client IP address
     * 
     * @since 1.2.0
     * @return string Client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', sanitize_text_field(wp_unslash($_SERVER[$key]))) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP,
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '127.0.0.1';
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
        if (class_exists('ExplainerPlugin_Logger')) {
            ExplainerPlugin_Logger::get_instance()->info('Security Event: ' . $event_type, $data);
        } else if (ExplainerPlugin_Debug_Logger::is_enabled()) {
            ExplainerPlugin_Debug_Logger::info('Security Event: ' . $event_type, 'security', $data);
        }
    }
}