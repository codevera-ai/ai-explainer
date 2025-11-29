<?php
/**
 * Claude Provider Implementation
 * 
 * Handles Claude (Anthropic) API integration for the AI Explainer plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Claude AI Provider
 */
class ExplainerPlugin_Claude_Provider extends ExplainerPlugin_Abstract_AI_Provider {
    
    /**
     * Claude API endpoint
     */
    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    
    /**
     * Claude API version
     */
    private const API_VERSION = '2023-06-01';
    
    /**
     * Get provider name
     * 
     * @return string Provider name
     */
    public function get_name() {
        return 'Claude';
    }
    
    /**
     * Get provider key
     * 
     * @return string Provider key
     */
    public function get_key() {
        return 'claude';
    }
    
    /**
     * Get available models
     * 
     * @return array Array of model key => label pairs
     */
    public function get_models() {
        return ExplainerPlugin_AI_Provider_Registry::get_provider_models_for_admin('claude');
    }
    
    /**
     * Get API endpoint URL
     * 
     * @return string API endpoint URL
     */
    public function get_api_endpoint() {
        return self::API_ENDPOINT;
    }
    
    /**
     * Validate API key format using shared validation logic
     * 
     * @param string $api_key API key to validate
     * @return bool True if valid format
     */
    public function validate_api_key($api_key) {
        // Use shared validation with Claude-specific prefix
        return $this->validate_api_key_base($api_key, 'sk-ant-');
    }
    
    /**
     * Prepare request headers
     * 
     * @param string $api_key API key
     * @return array Request headers
     */
    public function get_request_headers($api_key) {
        return array_merge($this->get_common_headers(), array(
            'x-api-key' => $api_key,
            'anthropic-version' => self::API_VERSION
        ));
    }
    
    /**
     * Prepare request body
     * 
     * @param string $prompt The prompt text
     * @param string $model Model to use
     * @param array $options Additional options
     * @return array Request body
     */
    public function prepare_request_body($prompt, $model, $options = array()) {
        $defaults = array(
            'temperature' => $this->get_config('temperature', 0.7),
            'max_tokens' => $this->get_max_tokens()
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Claude uses a system message and user message structure
        $system_message = 'You are a helpful assistant that explains text in simple, clear terms. Keep explanations concise and accessible.';
        
        return array(
            'model' => $model,
            'max_tokens' => $options['max_tokens'],
            'temperature' => $options['temperature'],
            'system' => $system_message,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );
    }
    
    /**
     * Parse API response
     * 
     * @param array $response WordPress HTTP response
     * @param string $model Model used
     * @return array Parsed response
     */
    public function parse_response($response, $model) {
        // Check for common errors first
        $error = $this->handle_common_errors($response);
        if ($error) {
            return $error;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check for Claude-specific errors
        if (isset($data['error'])) {
            $error_message = $data['error']['message'] ?? __('Unknown API error.', 'ai-explainer');
            return array(
                'success' => false,
                'error' => $error_message
            );
        }
        
        // Extract explanation from Claude response structure
        if (!isset($data['content'][0]['text'])) {
            return array(
                'success' => false,
                'error' => __('No explanation received from API.', 'ai-explainer')
            );
        }
        
        $explanation = trim($data['content'][0]['text']);
        $tokens_used = $data['usage']['output_tokens'] ?? 0;
        $cost = $this->calculate_cost($tokens_used, $model);
        
        return array(
            'success' => true,
            'explanation' => $explanation,
            'tokens_used' => $tokens_used,
            'cost' => $cost
        );
    }
    
    /**
     * Calculate cost for tokens used
     * 
     * @param int $tokens_used Number of tokens used
     * @param string $model Model used
     * @return float Cost in USD
     */
    public function calculate_cost($tokens_used, $model) {
        // Claude pricing (as of 2024 - prices may change)
        // Note: Claude pricing is typically per million tokens, converted to per token
        $pricing = array(
            'claude-haiku-4-5' => 1.25 / 1000000    // $1.25 per 1M tokens
        );

        $rate = $pricing[$model] ?? $pricing['claude-haiku-4-5'];

        return $tokens_used * $rate;
    }
    
    /**
     * Get default configuration for Claude
     * 
     * @return array Default configuration
     */
    protected function get_default_config() {
        return array_merge(parent::get_default_config(), array(
            'max_tokens' => 150,
            'timeout' => 10,
            'temperature' => 0.7
        ));
    }
    
    /**
     * Check if response indicates quota exceeded for Claude
     * 
     * @param int $response_code HTTP response code
     * @param array $data Parsed response data
     * @return bool True if quota exceeded
     */
    protected function is_quota_exceeded_error($response_code, $data) {
        // Check for Claude quota exceeded error conditions
        
        // HTTP 403 typically indicates quota/billing issues
        if ($response_code === 403) {
            return true;
        }
        
        // Check for specific error types in the response
        if (isset($data['error'])) {
            $error = $data['error'];
            $error_type = $error['type'] ?? '';
            $error_message = strtolower($error['message'] ?? '');
            
            // Claude error types that indicate quota/billing issues
            $quota_error_types = array(
                'credit_insufficient',
                'quota_exceeded',
                'billing_error',
                'account_suspended',
                'payment_required',
                'insufficient_credits'
            );
            
            if (in_array($error_type, $quota_error_types, true)) {
                return true;
            }
            
            // Check for quota-related keywords in error message
            $quota_keywords = array(
                'quota',
                'credit',
                'billing',
                'payment',
                'exceeded',
                'insufficient',
                'limit',
                'usage',
                'account suspended',
                'suspended'
            );
            
            foreach ($quota_keywords as $keyword) {
                if (strpos($error_message, $keyword) !== false) {
                    // Additional check to ensure it's not just a rate limit
                    if (strpos($error_message, 'rate') === false || 
                        strpos($error_message, 'quota') !== false ||
                        strpos($error_message, 'credit') !== false ||
                        strpos($error_message, 'billing') !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get Claude-specific quota exceeded message
     * 
     * @param array $data Parsed response data
     * @return string User-friendly error message
     */
    protected function get_quota_exceeded_message($data) {
        // Try to get specific error message from Claude
        $api_message = '';
        if (isset($data['error']['message'])) {
            $api_message = $data['error']['message'];
        }
        
        $base_message = __('Claude API usage limit exceeded. The plugin has been automatically disabled to prevent further charges.', 'ai-explainer');
        
        if (!empty($api_message)) {
            /* translators: %s: error message from the Claude API */
            $base_message .= ' ' . sprintf(__('Claude error: %s', 'ai-explainer'), $api_message);
        }
        
        $base_message .= ' ' . __('Please check your Anthropic account billing and usage limits, then manually re-enable the plugin when ready.', 'ai-explainer');
        
        return $base_message;
    }
    
    /**
     * Perform test request for Claude
     * 
     * @param string $api_key API key
     * @return array Test result
     */
    protected function perform_test_request($api_key) {
        $test_body = array(
            'model' => 'claude-haiku-4-5',
            'max_tokens' => 10,
            'temperature' => 0,
            'system' => 'You are a helpful assistant.',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Say "API key is working" if you can read this.'
                )
            )
        );
        
        $headers = $this->get_request_headers($api_key);
        
        $response = wp_remote_post($this->get_api_endpoint(), array(
            'timeout' => 5,
            'headers' => $headers,
            'body' => json_encode($test_body),
            'sslverify' => true
        ));
        
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
                'message' => __('Invalid API key. Please check your Claude API key.', 'ai-explainer')
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
                /* translators: %d: HTTP status code from the API response */
                'message' => sprintf(esc_html__('API error (HTTP %d). Please try again.', 'ai-explainer'), esc_html($response_code))
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Claude API key is valid and working.', 'ai-explainer')
        );
    }
    
    /**
     * Get maximum tokens for Claude
     * 
     * @return int Maximum tokens
     */
    public function get_max_tokens() {
        // Claude models have high context windows, but we keep explanations concise
        return $this->get_config('max_tokens', 150);
    }
}