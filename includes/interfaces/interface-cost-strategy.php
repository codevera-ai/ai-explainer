<?php
/**
 * Cost Calculation Strategy Interface
 * 
 * Defines strategy pattern for provider-specific cost calculations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for cost calculation strategies
 */
interface ExplainerPlugin_Cost_Strategy_Interface {
    
    /**
     * Calculate cost for API usage
     * 
     * @param int $tokens_used Number of tokens used
     * @param string $model Model identifier
     * @param array $context Additional context for cost calculation
     * @return float Cost in USD
     */
    public function calculate_cost($tokens_used, $model, $context = array());
    
    /**
     * Get cost per token for model
     * 
     * @param string $model Model identifier
     * @return float Cost per token in USD
     */
    public function get_cost_per_token($model);
    
    /**
     * Check if model is free
     * 
     * @param string $model Model identifier
     * @return bool True if model is free
     */
    public function is_free_model($model);
    
    /**
     * Get pricing information for display
     * 
     * @param string $model Model identifier
     * @return array Pricing information
     */
    public function get_pricing_info($model);
    
    /**
     * Get strategy name
     * 
     * @return string Strategy name
     */
    public function get_strategy_name();
}