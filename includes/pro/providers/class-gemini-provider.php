<?php
/**
 * Google Gemini Provider Implementation
 * 
 * Handles Google Gemini API integration for the AI Explainer plugin
 * Provides Google's flagship multimodal AI model with competitive pricing
 * 
 * Note: Uses unique authentication method via URL parameter instead of header
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Google Gemini AI Provider
 */
class ExplainerPlugin_Gemini_Provider extends ExplainerPlugin_Abstract_AI_Provider {
    
    /**
     * Gemini API base endpoint (model will be inserted dynamically)
     */
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';
    
    /**
     * Get provider name
     * 
     * @return string Provider name
     */
    public function get_name() {
        return 'Google Gemini';
    }
    
    /**
     * Get provider key
     * 
     * @return string Provider key
     */
    public function get_key() {
        return 'gemini';
    }
    
    /**
     * Get API endpoint URL with model parameter
     * Dynamic endpoint construction based on model
     * 
     * @param string $model Model to use (optional, for dynamic endpoint)
     * @return string API endpoint URL
     */
    public function get_api_endpoint($model = '') {
        if (empty($model)) {
            // Return base endpoint if no model specified
            return self::API_BASE;
        }
        
        return self::API_BASE . '/' . $model . ':generateContent';
    }
    
    /**
     * Get available models
     * 
     * @return array Array of model key => label pairs
     */
    public function get_models() {
        return ExplainerPlugin_AI_Provider_Registry::get_provider_models_for_admin('gemini');
    }
    
    /**
     * Validate API key format using shared validation logic
     * Google API keys start with 'AIza' and are 39 characters total
     * 
     * @param string $api_key API key to validate
     * @return bool True if valid format
     */
    public function validate_api_key($api_key) {
        // Use shared validation with Google-specific prefix and length
        return $this->validate_api_key_base($api_key, 'AIza', 39, 39);
    }
    
    /**
     * Prepare request headers (Gemini doesn't use Authorization header)
     * 
     * @param string $api_key API key (not used in headers for Gemini)
     * @return array Request headers
     */
    public function get_request_headers($api_key) {
        // Gemini uses API key in URL parameter, not in headers
        return $this->get_common_headers();
    }
    
    /**
     * Override make_request to handle Gemini's unique authentication
     * 
     * @param string $api_key API key
     * @param string $prompt Prompt text
     * @param string $model Model to use
     * @param array $options Additional options
     * @return array WordPress HTTP response
     */
    public function make_request($api_key, $prompt, $model, $options = array()) {
        $this->debug_log('Starting Gemini API request', array(
            'provider' => $this->get_name(),
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'api_key_configured' => !empty($api_key),
            'api_key_length' => strlen($api_key)
        ));
        
        // Build endpoint with model
        $endpoint = $this->get_api_endpoint($model);
        
        // Add API key as query parameter (Gemini's unique auth method)
        $endpoint .= '?key=' . urlencode($api_key);
        
        $headers = $this->get_request_headers($api_key);
        $body = $this->prepare_request_body($prompt, $model, $options);
        
        $timeout = isset($options['timeout']) ? intval($options['timeout']) : $this->get_timeout();
        
        $args = array(
            'timeout' => $timeout,
            'headers' => $headers,
            'body' => json_encode($body),
            'sslverify' => true,
            'user-agent' => $this->get_config('user_agent')
        );
        
        $start_time = microtime(true);
        $response = wp_remote_post($endpoint, $args);
        $response_time = microtime(true) - $start_time;
        
        $this->debug_log('Gemini API request completed', array(
            'response_time_seconds' => round($response_time, 3),
            'is_wp_error' => is_wp_error($response),
            'response_code' => is_wp_error($response) ? 'error' : wp_remote_retrieve_response_code($response),
            'response_size_bytes' => is_wp_error($response) ? 0 : strlen(wp_remote_retrieve_body($response))
        ));
        
        return $response;
    }
    
    /**
     * Prepare request body (Gemini uses different format than OpenAI)
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
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Gemini uses a different request format
        return array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => $prompt
                        )
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => $options['temperature'],
                'maxOutputTokens' => $options['max_tokens']
            )
        );
    }
    
    /**
     * Parse API response (Gemini uses different response format)
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
        
        // Check for Gemini-specific errors
        if (isset($data['error'])) {
            $error_message = $data['error']['message'] ?? __('Unknown API error.', 'ai-explainer');
            return array(
                'success' => false,
                'error' => $error_message
            );
        }
        
        // Extract explanation from Gemini response format
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return array(
                'success' => false,
                'error' => __('No explanation received from API.', 'ai-explainer')
            );
        }
        
        $explanation = trim($data['candidates'][0]['content']['parts'][0]['text']);
        $tokens_used = $data['usageMetadata']['totalTokenCount'] ?? 0;
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
        // Gemini pricing (2024) - tiered pricing based on model
        $pricing = array(
            'gemini-2.5-flash' => 0.000075      // $0.075 per 1M tokens (combined)
        );

        $rate = $pricing[$model] ?? $pricing['gemini-2.5-flash'];

        return ($tokens_used / 1000) * $rate;
    }
    
    /**
     * Check if response indicates quota exceeded for Google Gemini
     * 
     * @param int $response_code HTTP response code
     * @param array $data Parsed response data
     * @return bool True if quota exceeded
     */
    protected function is_quota_exceeded_error($response_code, $data) {
        // HTTP 429 is common for quota exceeded
        if ($response_code === 429) {
            return true;
        }
        
        // HTTP 403 can also indicate quota or API key restrictions
        if ($response_code === 403) {
            return true;
        }
        
        // Check for specific error messages in Google's format
        if (isset($data['error'])) {
            $error_message = strtolower($data['error']['message'] ?? '');
            $error_code = $data['error']['code'] ?? 0;
            
            // Google quota-related error codes
            if (in_array($error_code, [403, 429])) {
                return true;
            }
            
            // Google quota-related keywords
            $quota_keywords = array(
                'quota',
                'exceeded',
                'limit',
                'rate limit',
                'daily limit',
                'api key',
                'billing',
                'insufficient'
            );
            
            foreach ($quota_keywords as $keyword) {
                if (strpos($error_message, $keyword) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get Google-specific quota exceeded message
     * 
     * @param array $data Parsed response data
     * @return string User-friendly error message
     */
    protected function get_quota_exceeded_message($data) {
        $api_message = '';
        if (isset($data['error']['message'])) {
            $api_message = $data['error']['message'];
        }
        
        $base_message = __('Google Gemini API quota exceeded. The plugin has been automatically disabled to prevent further charges.', 'ai-explainer');
        
        if (!empty($api_message)) {
            /* translators: %s: error message from the Google API */
            $base_message .= ' ' . sprintf(__('Google error: %s', 'ai-explainer'), $api_message);
        }
        
        $base_message .= ' ' . __('Please check your Google AI Studio quota and billing settings, then manually re-enable the plugin when ready.', 'ai-explainer');
        
        return $base_message;
    }
    
    /**
     * Perform test request for Gemini (custom implementation for unique auth)
     * 
     * @param string $api_key API key
     * @return array Test result
     */
    protected function perform_test_request($api_key) {
        // Use fastest model for testing
        $test_model = 'gemini-2.5-flash';
        $endpoint = $this->get_api_endpoint($test_model) . '?key=' . urlencode($api_key);
        
        $test_body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => 'Say "API key is working" if you can read this.'
                        )
                    )
                )
            ),
            'generationConfig' => array(
                'maxOutputTokens' => 10,
                'temperature' => 0
            )
        );
        
        $headers = $this->get_request_headers($api_key);
        
        $response = wp_remote_post($endpoint, array(
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
        
        if ($response_code === 400) {
            return array(
                'success' => false,
                'message' => __('Invalid API key format. Please check your Google AI Studio API key.', 'ai-explainer')
            );
        }
        
        if ($response_code === 403) {
            return array(
                'success' => false,
                'message' => __('API key denied or quota exceeded. Please check your Google AI Studio settings.', 'ai-explainer')
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
            'message' => __('Google Gemini API key is valid and working.', 'ai-explainer')
        );
    }
}