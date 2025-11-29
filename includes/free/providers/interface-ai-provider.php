<?php
/**
 * AI Provider Interface
 * 
 * Defines the contract for AI provider implementations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for AI providers
 */
interface ExplainerPlugin_AI_Provider_Interface {
    
    /**
     * Get the provider name
     * 
     * @return string Provider name
     */
    public function get_name();
    
    /**
     * Get the provider key (unique identifier)
     * 
     * @return string Provider key
     */
    public function get_key();
    
    /**
     * Get available models for this provider
     * 
     * @return array Array of model key => label pairs
     */
    public function get_models();
    
    /**
     * Get the API endpoint URL
     * 
     * @return string API endpoint URL
     */
    public function get_api_endpoint();
    
    /**
     * Validate API key format
     * 
     * @param string $api_key API key to validate
     * @return bool True if valid format
     */
    public function validate_api_key($api_key);
    
    /**
     * Prepare request headers
     * 
     * @param string $api_key API key
     * @return array Request headers
     */
    public function get_request_headers($api_key);
    
    /**
     * Prepare request body
     * 
     * @param string $prompt The prompt text
     * @param string $model Model to use
     * @param array $options Additional options
     * @return array Request body
     */
    public function prepare_request_body($prompt, $model, $options = array());
    
    /**
     * Parse API response
     * 
     * @param array $response WordPress HTTP response
     * @param string $model Model used
     * @return array Parsed response with success, explanation, tokens_used, cost
     */
    public function parse_response($response, $model);
    
    /**
     * Calculate cost for tokens used
     * 
     * @param int $tokens_used Number of tokens used
     * @param string $model Model used
     * @return float Cost in USD
     */
    public function calculate_cost($tokens_used, $model);
    
    /**
     * Get maximum tokens for this provider
     * 
     * @return int Maximum tokens
     */
    public function get_max_tokens();
    
    /**
     * Get request timeout in seconds
     * 
     * @return int Timeout in seconds
     */
    public function get_timeout();
}