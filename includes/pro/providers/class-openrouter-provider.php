<?php
/**
 * OpenRouter Provider Implementation
 * 
 * Handles OpenRouter API integration for the AI Explainer plugin
 * Provides access to 400+ AI models through a unified API
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * OpenRouter AI Provider
 */
class ExplainerPlugin_OpenRouter_Provider extends ExplainerPlugin_Abstract_AI_Provider {
    
    /**
     * OpenRouter API endpoint
     */
    private const API_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';
    
    /**
     * OpenRouter fee percentage (5.5%)
     */
    private const OPENROUTER_FEE = 0.055;
    
    /**
     * Get provider name
     * 
     * @return string Provider name
     */
    public function get_name() {
        return 'OpenRouter';
    }
    
    /**
     * Get provider key
     * 
     * @return string Provider key
     */
    public function get_key() {
        return 'openrouter';
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
     * Get available models
     * 
     * @return array Array of model key => label pairs
     */
    public function get_models() {
        return ExplainerPlugin_AI_Provider_Registry::get_provider_models_for_admin('openrouter');
    }
    
    /**
     * Validate API key format using shared validation logic
     * 
     * @param string $api_key API key to validate
     * @return bool True if valid format
     */
    public function validate_api_key($api_key) {
        // Use shared validation with OpenRouter-specific prefix
        return $this->validate_api_key_base($api_key, 'sk-or-');
    }
    
    /**
     * Prepare request headers
     * 
     * @param string $api_key API key
     * @return array Request headers
     */
    public function get_request_headers($api_key) {
        return array_merge($this->get_common_headers(), array(
            'Authorization' => 'Bearer ' . $api_key,
            'HTTP-Referer' => home_url(),
            'X-Title' => get_bloginfo('name') . ' - AI Explainer Plugin'
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
        
        // Check for OpenRouter-specific errors
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
        // OpenRouter pricing matrix (base prices + 5.5% fee)
        $pricing = array(
            // Free models (no cost)
            'meta-llama/llama-3.2-3b-instruct:free' => 0,
            'microsoft/phi-3-mini-128k-instruct:free' => 0,
            'google/gemma-2-9b-it:free' => 0,
            
            // Premium models (estimated pricing per 1K tokens with OpenRouter fee)
            'anthropic/claude-3.5-sonnet' => 0.003 * (1 + self::OPENROUTER_FEE),
            'anthropic/claude-3-haiku' => 0.00025 * (1 + self::OPENROUTER_FEE),
            'openai/gpt-4o-mini' => 0.00015 * (1 + self::OPENROUTER_FEE),
            'openai/gpt-4o' => 0.0025 * (1 + self::OPENROUTER_FEE),
            'openai/gpt-3.5-turbo' => 0.0005 * (1 + self::OPENROUTER_FEE),
            'google/gemini-pro-1.5' => 0.00125 * (1 + self::OPENROUTER_FEE),
            'google/gemini-flash-1.5' => 0.000075 * (1 + self::OPENROUTER_FEE),
            'meta-llama/llama-3.2-90b-instruct' => 0.0009 * (1 + self::OPENROUTER_FEE),
            'mistralai/mistral-7b-instruct' => 0.00025 * (1 + self::OPENROUTER_FEE),
            'cohere/command-r-plus' => 0.003 * (1 + self::OPENROUTER_FEE),
        );
        
        // Default fallback pricing for unknown models
        $rate = $pricing[$model] ?? (0.001 * (1 + self::OPENROUTER_FEE));
        
        return ($tokens_used / 1000) * $rate;
    }
    
    /**
     * Check if response indicates quota exceeded for OpenRouter
     * 
     * @param int $response_code HTTP response code
     * @param array $data Parsed response data
     * @return bool True if quota exceeded
     */
    protected function is_quota_exceeded_error($response_code, $data) {
        // HTTP 402 Payment Required is common for quota issues
        if ($response_code === 402) {
            return true;
        }
        
        // HTTP 429 can also indicate quota exceeded
        if ($response_code === 429) {
            return true;
        }
        
        // Check for specific error messages in the response
        if (isset($data['error'])) {
            $error_message = strtolower($data['error']['message'] ?? '');
            
            // OpenRouter quota-related keywords
            $quota_keywords = array(
                'quota',
                'credit',
                'balance',
                'insufficient',
                'exceeded',
                'limit',
                'payment'
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
     * Get OpenRouter-specific quota exceeded message
     * 
     * @param array $data Parsed response data
     * @return string User-friendly error message
     */
    protected function get_quota_exceeded_message($data) {
        // Try to get specific error message from OpenRouter
        $api_message = '';
        if (isset($data['error']['message'])) {
            $api_message = $data['error']['message'];
        }
        
        $base_message = __('OpenRouter API credits exhausted. The plugin has been automatically disabled to prevent further charges.', 'ai-explainer');
        
        if (!empty($api_message)) {
            /* translators: %s: error message from the OpenRouter API */
            $base_message .= ' ' . sprintf(__('OpenRouter error: %s', 'ai-explainer'), $api_message);
        }
        
        $base_message .= ' ' . __('Please add credits to your OpenRouter account, then manually re-enable the plugin when ready.', 'ai-explainer');
        
        return $base_message;
    }
    
    /**
     * Perform test request for OpenRouter
     * 
     * @param string $api_key API key
     * @return array Test result
     */
    protected function perform_test_request($api_key) {
        // Use a free model for testing to avoid charges
        $test_body = array(
            'model' => 'meta-llama/llama-3.2-3b-instruct:free',
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
                'message' => __('Invalid API key. Please check your OpenRouter API key.', 'ai-explainer')
            );
        }
        
        if ($response_code === 402) {
            return array(
                'success' => false,
                'message' => __('OpenRouter account has insufficient credits. Please add credits to your account.', 'ai-explainer')
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
            'message' => __('OpenRouter API key is valid and working.', 'ai-explainer')
        );
    }
}