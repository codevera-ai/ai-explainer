<?php
/**
 * HTTP Client Abstraction
 * 
 * Provides unified HTTP request handling for all providers
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unified HTTP client for API requests
 */
class ExplainerPlugin_HTTP_Client {
    
    /**
     * Logger instance
     * 
     * @var ExplainerPlugin_Logger
     */
    private $logger;
    
    /**
     * Default request configuration
     * 
     * @var array
     */
    private $default_config;
    
    /**
     * Constructor
     * 
     * @param array $config Default configuration
     */
    public function __construct($config = array()) {
        $this->logger = ExplainerPlugin_Logger::get_instance();
        $this->default_config = wp_parse_args($config, array(
            'timeout' => 10,
            'user_agent' => 'WordPress/ExplainerPlugin/1.0',
            'sslverify' => true,
            'follow_redirects' => false,
            'max_redirects' => 3
        ));
    }
    
    /**
     * Make HTTP POST request
     * 
     * @param string $url Request URL
     * @param array $data Request data
     * @param array $headers Request headers
     * @param array $options Request options
     * @return array|WP_Error WordPress HTTP response or error
     */
    public function post($url, $data = array(), $headers = array(), $options = array()) {
        return $this->make_request('POST', $url, $data, $headers, $options);
    }
    
    /**
     * Make HTTP GET request
     * 
     * @param string $url Request URL
     * @param array $headers Request headers
     * @param array $options Request options
     * @return array|WP_Error WordPress HTTP response or error
     */
    public function get($url, $headers = array(), $options = array()) {
        return $this->make_request('GET', $url, null, $headers, $options);
    }
    
    /**
     * Make HTTP request with unified handling
     * 
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url Request URL
     * @param mixed $data Request data
     * @param array $headers Request headers
     * @param array $options Request options
     * @return array|WP_Error WordPress HTTP response or error
     */
    private function make_request($method, $url, $data = null, $headers = array(), $options = array()) {
        // Merge options with defaults
        $options = wp_parse_args($options, $this->default_config);
        
        // Prepare request arguments
        $args = array(
            'method' => strtoupper($method),
            'timeout' => $options['timeout'],
            'sslverify' => $options['sslverify'],
            'user-agent' => $options['user_agent'],
            'headers' => $this->prepare_headers($headers),
            'redirection' => $options['follow_redirects'] ? $options['max_redirects'] : 0
        );
        
        // Add body for POST requests
        if ($method === 'POST' && $data !== null) {
            $args['body'] = is_array($data) ? json_encode($data) : $data;
            
            // Ensure Content-Type is set for JSON data
            if (is_array($data) && !isset($args['headers']['Content-Type'])) {
                $args['headers']['Content-Type'] = 'application/json';
            }
        }
        
        // Log request details
        $this->log_request($method, $url, $args, $options);
        
        // Make the request
        $start_time = microtime(true);
        $response = wp_remote_request($url, $args);
        $response_time = microtime(true) - $start_time;
        
        // Log response details
        $this->log_response($response, $response_time, $url);
        
        return $response;
    }
    
    /**
     * Prepare and validate headers
     * 
     * @param array $headers Raw headers
     * @return array Processed headers
     */
    private function prepare_headers($headers) {
        $processed_headers = array();
        
        foreach ($headers as $name => $value) {
            // Sanitize header name and value
            $name = sanitize_text_field($name);
            $value = sanitize_text_field($value);
            
            // Skip empty headers
            if (!empty($name) && !empty($value)) {
                $processed_headers[$name] = $value;
            }
        }
        
        return $processed_headers;
    }
    
    /**
     * Parse JSON response with error handling
     * 
     * @param array|WP_Error $response WordPress HTTP response
     * @return array Parsed response with success flag
     */
    public function parse_json_response($response) {
        // Handle WP_Error
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'Connection failed: ' . $response->get_error_message(),
                'error_type' => 'connection_error'
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Parse JSON body
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => 'Invalid JSON response from API',
                'error_type' => 'parse_error',
                'response_code' => $response_code,
                'raw_body' => $body
            );
        }
        
        return array(
            'success' => $response_code >= 200 && $response_code < 300,
            'response_code' => $response_code,
            'data' => $data,
            'raw_response' => $response
        );
    }
    
    /**
     * Check if response indicates rate limiting
     * 
     * @param array|WP_Error $response WordPress HTTP response
     * @return bool True if rate limited
     */
    public function is_rate_limited($response) {
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 429;
    }
    
    /**
     * Check if response indicates authentication error
     * 
     * @param array|WP_Error $response WordPress HTTP response
     * @return bool True if authentication failed
     */
    public function is_auth_error($response) {
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 401 || $response_code === 403;
    }
    
    /**
     * Check if response indicates quota/billing error
     * 
     * @param array|WP_Error $response WordPress HTTP response
     * @return bool True if quota exceeded
     */
    public function is_quota_error($response) {
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 402 || $response_code === 429;
    }
    
    /**
     * Get response time from response headers
     * 
     * @param array $response WordPress HTTP response
     * @return float|null Response time in seconds or null if not available
     */
    public function get_response_time($response) {
        if (is_wp_error($response)) {
            return null;
        }
        
        $headers = wp_remote_retrieve_headers($response);
        
        // Some APIs provide response time in headers
        if (isset($headers['x-response-time'])) {
            return floatval($headers['x-response-time']) / 1000; // Convert ms to seconds
        }
        
        return null;
    }
    
    /**
     * Log request details
     * 
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array $args Request arguments
     * @param array $options Request options
     */
    private function log_request($method, $url, $args, $options) {
        $this->logger->debug('HTTP request initiated', array(
            'method' => $method,
            'url' => $url,
            'timeout' => $args['timeout'],
            'headers_count' => count($args['headers']),
            'body_size_bytes' => isset($args['body']) ? strlen($args['body']) : 0,
            'user_agent' => $args['user-agent']
        ), 'HTTP_Client');
    }
    
    /**
     * Log response details
     * 
     * @param array|WP_Error $response WordPress HTTP response
     * @param float $response_time Response time in seconds
     * @param string $url Request URL for context
     */
    private function log_response($response, $response_time, $url) {
        if (is_wp_error($response)) {
            $this->logger->debug('HTTP request failed', array(
                'url' => $url,
                'response_time_seconds' => round($response_time, 3),
                'error_code' => $response->get_error_code(),
                'error_message' => $response->get_error_message()
            ), 'HTTP_Client');
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $body_size = strlen(wp_remote_retrieve_body($response));
            
            $this->logger->debug('HTTP request completed', array(
                'url' => $url,
                'response_time_seconds' => round($response_time, 3),
                'response_code' => $response_code,
                'response_size_bytes' => $body_size,
                'success' => $response_code >= 200 && $response_code < 300
            ), 'HTTP_Client');
        }
    }
    
    /**
     * Create client with provider-specific defaults
     * 
     * @param string $provider_key Provider key
     * @return ExplainerPlugin_HTTP_Client Configured HTTP client
     */
    public static function create_for_provider($provider_key) {
        $provider_config = ExplainerPlugin_Provider_Config::get_provider_config_by_key($provider_key);
        
        $config = array();
        if ($provider_config) {
            $config['timeout'] = $provider_config['timeout'];
            $config['user_agent'] = 'WordPress/ExplainerPlugin/1.0 (' . $provider_config['name'] . ')';
        }
        
        return new self($config);
    }
}