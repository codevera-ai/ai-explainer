<?php
/**
 * OpenAI Provider Implementation
 * 
 * Handles OpenAI API integration for the AI Explainer plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * OpenAI AI Provider
 */
class ExplainerPlugin_OpenAI_Provider extends ExplainerPlugin_Abstract_AI_Provider {
    
    /**
     * OpenAI API endpoint
     */
    private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    
    /**
     * Get provider name
     * 
     * @return string Provider name
     */
    public function get_name() {
        return 'OpenAI';
    }
    
    /**
     * Get provider key
     * 
     * @return string Provider key
     */
    public function get_key() {
        return 'openai';
    }
    
    /**
     * Get available models
     * 
     * @return array Array of model key => label pairs
     */
    public function get_models() {
        return ExplainerPlugin_AI_Provider_Registry::get_provider_models_for_admin('openai');
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
        // Use shared validation with OpenAI-specific prefix
        return $this->validate_api_key_base($api_key, 'sk-');
    }
    
    /**
     * Prepare request headers
     * 
     * @param string $api_key API key
     * @return array Request headers
     */
    public function get_request_headers($api_key) {
        return array_merge($this->get_common_headers(), array(
            'Authorization' => 'Bearer ' . $api_key
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
            'max_tokens' => $this->get_max_tokens(),
            'top_p' => 1.0,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0
        );
        
        $options = wp_parse_args($options, $defaults);
        
        return array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a helpful assistant that explains text in simple, clear terms. Keep explanations concise and accessible.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => $options['max_tokens'],
            'temperature' => $options['temperature'],
            'top_p' => $options['top_p'],
            'frequency_penalty' => $options['frequency_penalty'],
            'presence_penalty' => $options['presence_penalty']
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
        
        // Check for OpenAI-specific errors
        if (isset($data['error'])) {
            $error_message = $data['error']['message'] ?? __('Unknown API error.', 'ai-explainer');
            return array(
                'success' => false,
                'error' => $error_message
            );
        }
        
        // Extract explanation
        if (!isset($data['choices'][0]['message']['content'])) {
            return array(
                'success' => false,
                'error' => __('No explanation received from API.', 'ai-explainer')
            );
        }
        
        $explanation = trim($data['choices'][0]['message']['content']);
        $tokens_used = $data['usage']['total_tokens'] ?? 0;
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
        // OpenAI pricing (as of 2024 - prices may change)
        $pricing = array(
            'gpt-5.1' => 0.005 / 1000         // $0.005 per 1K tokens
        );

        $rate = $pricing[$model] ?? $pricing['gpt-5.1'];

        return $tokens_used * $rate;
    }
    
    /**
     * Get default configuration for OpenAI
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
     * Check if response indicates quota exceeded for OpenAI
     * 
     * @param int $response_code HTTP response code
     * @param array $data Parsed response data
     * @return bool True if quota exceeded
     */
    protected function is_quota_exceeded_error($response_code, $data) {
        // Check for OpenAI quota exceeded error conditions
        
        // HTTP 403 typically indicates quota exceeded
        if ($response_code === 403) {
            return true;
        }
        
        // Check for specific error types in the response
        if (isset($data['error'])) {
            $error = $data['error'];
            $error_type = $error['type'] ?? '';
            $error_code = $error['code'] ?? '';
            $error_message = strtolower($error['message'] ?? '');
            
            // OpenAI error types that indicate quota/billing issues
            $quota_error_types = array(
                'insufficient_quota',
                'quota_exceeded',
                'billing_not_active',
                'invalid_payment_method',
                'payment_required'
            );
            
            if (in_array($error_type, $quota_error_types, true)) {
                return true;
            }
            
            // Check for quota-related error codes
            $quota_error_codes = array(
                'insufficient_quota',
                'quota_exceeded',
                'billing_not_active'
            );
            
            if (in_array($error_code, $quota_error_codes, true)) {
                return true;
            }
            
            // Check for quota-related keywords in error message
            $quota_keywords = array(
                'quota',
                'billing',
                'payment',
                'credit',
                'exceeded',
                'insufficient',
                'limit',
                'usage'
            );
            
            foreach ($quota_keywords as $keyword) {
                if (strpos($error_message, $keyword) !== false) {
                    // Additional check to ensure it's not just a rate limit
                    if (strpos($error_message, 'rate') === false || 
                        strpos($error_message, 'quota') !== false ||
                        strpos($error_message, 'billing') !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get OpenAI-specific quota exceeded message
     * 
     * @param array $data Parsed response data
     * @return string User-friendly error message
     */
    protected function get_quota_exceeded_message($data) {
        // Try to get specific error message from OpenAI
        $api_message = '';
        if (isset($data['error']['message'])) {
            $api_message = $data['error']['message'];
        }
        
        $base_message = __('OpenAI API usage limit exceeded. The plugin has been automatically disabled to prevent further charges.', 'ai-explainer');
        
        if (!empty($api_message)) {
            /* translators: %s: error message from the OpenAI API */
            $base_message .= ' ' . sprintf(__('OpenAI error: %s', 'ai-explainer'), $api_message);
        }
        
        $base_message .= ' ' . __('Please check your OpenAI account billing and usage limits, then manually re-enable the plugin when ready.', 'ai-explainer');
        
        return $base_message;
    }
    
    /**
     * Perform test request for OpenAI
     *
     * @param string $api_key API key
     * @return array Test result
     */
    protected function perform_test_request($api_key) {
        $test_body = array(
            'model' => 'gpt-5.1',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Say "API key is working" if you can read this.'
                )
            ),
            'max_tokens' => 10,
            'temperature' => 0
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
                'error_type' => 'api_key_invalid',
                'disable_plugin' => true,
                'message' => __('Invalid API key. Please check your OpenAI API key.', 'ai-explainer')
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
            'message' => __('OpenAI API key is valid and working.', 'ai-explainer')
        );
    }
}