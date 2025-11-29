<?php
/**
 * Abstract AI Provider Base Class
 * 
 * Provides common functionality for all AI providers
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract base class for AI providers
 */
abstract class ExplainerPlugin_Abstract_AI_Provider implements ExplainerPlugin_AI_Provider_Interface {
    
    /**
     * Provider configuration
     * 
     * @var array
     */
    protected $config = array();
    
    /**
     * Constructor
     * 
     * @param array $config Provider configuration
     */
    public function __construct($config = array()) {
        $this->config = wp_parse_args($config, $this->get_default_config());
    }
    
    /**
     * Debug logging method using shared utility
     */
    protected function debug_log($message, $data = array()) {
        $logger = ExplainerPlugin_Logger::get_instance();
        return $logger->debug($message, $data, 'AI_Provider_' . $this->get_name());
    }
    
    /**
     * Get default configuration
     * 
     * @return array Default configuration
     */
    protected function get_default_config() {
        return array(
            'max_tokens' => 150,
            'timeout' => 10,
            'temperature' => 0.7,
            'user_agent' => 'WordPress/ExplainerPlugin/1.0'
        );
    }
    
    /**
     * Get configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Configuration value
     */
    protected function get_config($key, $default = null) {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }
    
    /**
     * Make HTTP request to API
     * 
     * @param string $api_key API key
     * @param string $prompt Prompt text
     * @param string $model Model to use
     * @param array $options Additional options
     * @return array WordPress HTTP response
     */
    public function make_request($api_key, $prompt, $model, $options = array()) {
        $this->debug_log('Starting API request', array(
            'provider' => $this->get_name(),
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'api_key_configured' => !empty($api_key),
            'api_key_length' => strlen($api_key),
            'endpoint' => $this->get_api_endpoint()
        ));
        
        $headers = $this->get_request_headers($api_key);
        $body = $this->prepare_request_body($prompt, $model, $options);
        
        $this->debug_log('Request prepared', array(
            'headers_count' => count($headers),
            'body_size_bytes' => strlen(json_encode($body)),
            'timeout' => $this->get_timeout()
        ));
        
        // Use timeout from options if provided, otherwise use default
        $timeout = isset($options['timeout']) ? intval($options['timeout']) : $this->get_timeout();
        $this->debug_log('Using timeout: ' . $timeout . ' seconds (from ' . (isset($options['timeout']) ? 'options' : 'default') . ')');
        
        $args = array(
            'timeout' => $timeout,
            'headers' => $headers,
            'body' => json_encode($body),
            'sslverify' => true,
            'user-agent' => $this->get_config('user_agent')
        );
        
        $start_time = microtime(true);
        $response = wp_remote_post($this->get_api_endpoint(), $args);
        $response_time = microtime(true) - $start_time;
        
        $this->debug_log('API request completed', array(
            'response_time_seconds' => round($response_time, 3),
            'is_wp_error' => is_wp_error($response),
            'response_code' => is_wp_error($response) ? 'error' : wp_remote_retrieve_response_code($response),
            'response_size_bytes' => is_wp_error($response) ? 0 : strlen(wp_remote_retrieve_body($response))
        ));
        
        return $response;
    }
    
    /**
     * Test API key validity
     * 
     * @param string $api_key API key to test
     * @return array Test result with success and message
     */
    public function test_api_key($api_key) {
        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => __('API key is required.', 'ai-explainer')
            );
        }
        
        if (!$this->validate_api_key($api_key)) {
            return array(
                'success' => false,
                'message' => __('Invalid API key format.', 'ai-explainer')
            );
        }
        
        return $this->perform_test_request($api_key);
    }
    
    /**
     * Perform actual test request
     * 
     * @param string $api_key API key
     * @return array Test result
     */
    protected function perform_test_request($api_key) {
        $test_prompt = $this->get_test_prompt();
        $models = $this->get_models();
        $test_model = key($models); // Use first available model
        
        $response = $this->make_request($api_key, $test_prompt, $test_model);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => __('Connection failed. Please check your internet connection.', 'ai-explainer')
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 401) {
            return array(
                'success' => false,
                /* translators: %s name of the AI provider (OpenAI, Claude, etc.) */
                'message' => sprintf(esc_html__('Invalid API key. Please check your %s API key.', 'ai-explainer'), esc_html($this->get_name()))
            );
        }

        if ($response_code === 429) {
            return array(
                'success' => false,
                'message' => __('Rate limit exceeded. Please try again later.', 'ai-explainer')
            );
        }

        if ($response_code !== 200) {
            return array(
                'success' => false,
                /* translators: %d HTTP status code from the API response */
                'message' => sprintf(esc_html__('API error (HTTP %d). Please try again.', 'ai-explainer'), esc_html($response_code))
            );
        }

        return array(
            'success' => true,
            /* translators: %s name of the AI provider (OpenAI, Claude, etc.) */
            'message' => sprintf(esc_html__('%s API key is valid and working.', 'ai-explainer'), esc_html($this->get_name()))
        );
    }
    
    /**
     * Get test prompt for API key validation
     * 
     * @return string Test prompt
     */
    protected function get_test_prompt() {
        return 'Say "API key is working" if you can read this.';
    }
    
    /**
     * Get maximum tokens (default implementation)
     * 
     * @return int Maximum tokens
     */
    public function get_max_tokens() {
        return $this->get_config('max_tokens', 150);
    }
    
    /**
     * Get request timeout (default implementation)
     * 
     * @return int Timeout in seconds
     */
    public function get_timeout() {
        return $this->get_config('timeout', 10);
    }
    
    /**
     * Common request headers
     * 
     * @return array Common headers
     */
    protected function get_common_headers() {
        return array(
            'Content-Type' => 'application/json',
            'User-Agent' => $this->get_config('user_agent')
        );
    }
    
    /**
     * Handle common API errors
     * 
     * @param array $response WordPress HTTP response
     * @return array|null Error array or null if no error
     */
    protected function handle_common_errors($response) {
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => __('API request failed. Please try again.', 'ai-explainer')
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        // Check for API key issues first (401 = unauthorized)
        if ($response_code === 401) {
            return array(
                'success' => false,
                'error_type' => 'api_key_invalid',
                'disable_plugin' => true,
                'error' => __('Invalid API key. Please check your API key settings.', 'ai-explainer')
            );
        }
        
        // Check for quota/billing issues 
        $quota_error = $this->check_quota_exceeded($response);
        if ($quota_error) {
            return $quota_error;
        }
        
        if ($response_code !== 200) {
            return array(
                'success' => false,
                'error' => __('Explanation temporarily unavailable. Please try again later.', 'ai-explainer')
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => __('Invalid API response format.', 'ai-explainer')
            );
        }
        
        return null; // No common errors found
    }
    
    /**
     * Check if the API response indicates quota/billing exceeded
     * 
     * @param array $response WordPress HTTP response
     * @return array|null Error array with disable_plugin flag or null if no quota error
     */
    protected function check_quota_exceeded($response) {
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Parse response body for error details
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $data = array();
        }
        
        // Check for provider-specific quota errors
        if ($this->is_quota_exceeded_error($response_code, $data)) {
            return array(
                'success' => false,
                'error' => $this->get_quota_exceeded_message($data),
                'disable_plugin' => true,
                'error_type' => 'quota_exceeded'
            );
        }
        
        return null;
    }
    
    /**
     * Check if response indicates quota exceeded (provider-specific implementation)
     * 
     * @param int $response_code HTTP response code
     * @param array $data Parsed response data
     * @return bool True if quota exceeded
     */
    protected function is_quota_exceeded_error($response_code, $data) {
        // Default implementation - providers should override this
        return false;
    }
    
    /**
     * Get user-friendly message for quota exceeded error
     * 
     * @param array $data Parsed response data
     * @return string User-friendly error message
     */
    protected function get_quota_exceeded_message($data) {
        return sprintf(
            /* translators: %1$s AI provider name, %2$s AI provider name for account reference */
            __('API usage limit exceeded for %1$s. The plugin has been automatically disabled to prevent further charges. Please check your %2$s account billing and usage limits, then manually re-enable the plugin when ready.', 'ai-explainer'),
            $this->get_name(),
            $this->get_name()
        );
    }
    
    /**
     * Shared API key validation logic to eliminate duplication
     * 
     * @param string $api_key API key to validate
     * @param string $prefix Required prefix for the API key
     * @param int $min_length Minimum length for the API key
     * @param int $max_length Maximum length for the API key
     * @return bool True if valid format
     */
    protected function validate_api_key_base($api_key, $prefix, $min_length = 20, $max_length = 200) {
        // Basic type and presence validation
        if (!$api_key || !is_string($api_key)) {
            return false;
        }
        
        // Remove any whitespace
        $api_key = trim($api_key);
        
        // Check for required prefix
        if (!str_starts_with($api_key, $prefix)) {
            return false;
        }
        
        // Check minimum length
        if (strlen($api_key) < $min_length) {
            return false;
        }
        
        // Check maximum reasonable length
        if (strlen($api_key) > $max_length) {
            return false;
        }
        
        // Check that it contains only valid characters (alphanumeric, hyphens, underscores, dots)
        // Updated to support newer OpenAI key formats like sk-proj-... and sk-org-...
        // Escape the prefix for regex to handle special characters like hyphens
        $escaped_prefix = preg_quote($prefix, '/');
        if (!preg_match('/^' . $escaped_prefix . '[a-zA-Z0-9._-]+$/', $api_key)) {
            return false;
        }
        
        return true;
    }
}