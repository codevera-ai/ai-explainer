<?php
/**
 * Cost Calculation Manager
 * 
 * Implements strategy pattern for provider-specific cost calculations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cost calculation manager using strategy pattern
 */
class ExplainerPlugin_Cost_Calculator {
    
    /**
     * Cost strategies registry
     * 
     * @var array
     */
    private static $strategies = array();
    
    /**
     * Default strategy cache
     * 
     * @var array
     */
    private static $default_strategies = null;
    
    /**
     * Register a cost strategy
     * 
     * @param string $provider_key Provider key
     * @param ExplainerPlugin_Cost_Strategy_Interface $strategy Cost strategy
     */
    public static function register_strategy($provider_key, ExplainerPlugin_Cost_Strategy_Interface $strategy) {
        self::$strategies[$provider_key] = $strategy;
    }
    
    /**
     * Get cost strategy for provider
     * 
     * @param string $provider_key Provider key
     * @return ExplainerPlugin_Cost_Strategy_Interface|null Cost strategy or null if not found
     */
    public static function get_strategy($provider_key) {
        // Initialize default strategies if not done yet
        if (self::$default_strategies === null) {
            self::initialize_default_strategies();
        }
        
        return isset(self::$strategies[$provider_key]) ? self::$strategies[$provider_key] : null;
    }
    
    /**
     * Calculate cost using appropriate strategy
     * 
     * @param string $provider_key Provider key
     * @param int $tokens_used Number of tokens used
     * @param string $model Model identifier
     * @param array $context Additional context
     * @return float Cost in USD
     */
    public static function calculate_cost($provider_key, $tokens_used, $model, $context = array()) {
        $strategy = self::get_strategy($provider_key);
        
        if (!$strategy) {
            // Fallback to basic calculation
            return self::fallback_cost_calculation($tokens_used, $model);
        }
        
        return $strategy->calculate_cost($tokens_used, $model, $context);
    }
    
    /**
     * Check if model is free
     * 
     * @param string $provider_key Provider key
     * @param string $model Model identifier
     * @return bool True if model is free
     */
    public static function is_free_model($provider_key, $model) {
        $strategy = self::get_strategy($provider_key);
        
        if (!$strategy) {
            return false; // Assume paid if strategy not found
        }
        
        return $strategy->is_free_model($model);
    }
    
    /**
     * Get pricing information for display
     * 
     * @param string $provider_key Provider key
     * @param string $model Model identifier
     * @return array Pricing information
     */
    public static function get_pricing_info($provider_key, $model) {
        $strategy = self::get_strategy($provider_key);
        
        if (!$strategy) {
            return array(
                'cost_per_1k_tokens' => 0.001, // Default fallback
                'is_free' => false,
                'currency' => 'USD',
                'notes' => 'Pricing information not available'
            );
        }
        
        return $strategy->get_pricing_info($model);
    }
    
    /**
     * Get all registered strategies
     * 
     * @return array Provider key => strategy pairs
     */
    public static function get_all_strategies() {
        // Initialize default strategies if not done yet
        if (self::$default_strategies === null) {
            self::initialize_default_strategies();
        }
        
        return self::$strategies;
    }
    
    /**
     * Initialize default cost strategies for all providers
     */
    private static function initialize_default_strategies() {
        self::$default_strategies = true;
        
        // Initialize strategies based on existing provider calculate_cost methods
        $available_providers = ExplainerPlugin_Provider_Factory::get_available_providers_config();
        
        foreach ($available_providers as $provider_key => $class_name) {
            if (!isset(self::$strategies[$provider_key])) {
                // Create wrapper strategy that delegates to provider's calculate_cost method
                self::$strategies[$provider_key] = new ExplainerPlugin_Provider_Cost_Strategy($provider_key);
            }
        }
    }
    
    /**
     * Fallback cost calculation for unknown providers
     * 
     * @param int $tokens_used Number of tokens used
     * @param string $model Model identifier
     * @return float Cost in USD
     */
    private static function fallback_cost_calculation($tokens_used, $model) {
        // Conservative fallback pricing
        $fallback_rate = 0.002; // $0.002 per 1K tokens
        return ($tokens_used / 1000) * $fallback_rate;
    }
    
    /**
     * Clear strategies cache (useful for testing)
     */
    public static function clear_strategies() {
        self::$strategies = array();
        self::$default_strategies = null;
    }
}

/**
 * Wrapper strategy that delegates to existing provider methods
 */
class ExplainerPlugin_Provider_Cost_Strategy implements ExplainerPlugin_Cost_Strategy_Interface {
    
    /**
     * Provider key
     * 
     * @var string
     */
    private $provider_key;
    
    /**
     * Provider instance
     * 
     * @var ExplainerPlugin_AI_Provider_Interface
     */
    private $provider;
    
    /**
     * Constructor
     * 
     * @param string $provider_key Provider key
     */
    public function __construct($provider_key) {
        $this->provider_key = $provider_key;
        $this->provider = ExplainerPlugin_Provider_Factory::get_provider($provider_key);
    }
    
    /**
     * Calculate cost for API usage
     * 
     * @param int $tokens_used Number of tokens used
     * @param string $model Model identifier
     * @param array $context Additional context for cost calculation
     * @return float Cost in USD
     */
    public function calculate_cost($tokens_used, $model, $context = array()) {
        if (!$this->provider || !method_exists($this->provider, 'calculate_cost')) {
            return 0.0;
        }
        
        return $this->provider->calculate_cost($tokens_used, $model);
    }
    
    /**
     * Get cost per token for model
     * 
     * @param string $model Model identifier
     * @return float Cost per token in USD
     */
    public function get_cost_per_token($model) {
        // Calculate cost for 1000 tokens and divide by 1000
        $cost_per_1k = $this->calculate_cost(1000, $model);
        return $cost_per_1k / 1000;
    }
    
    /**
     * Check if model is free
     * 
     * @param string $model Model identifier
     * @return bool True if model is free
     */
    public function is_free_model($model) {
        $cost = $this->calculate_cost(1000, $model);
        return $cost <= 0.0;
    }
    
    /**
     * Get pricing information for display
     * 
     * @param string $model Model identifier
     * @return array Pricing information
     */
    public function get_pricing_info($model) {
        $cost_per_1k = $this->calculate_cost(1000, $model);
        $is_free = $cost_per_1k <= 0.0;
        
        return array(
            'cost_per_1k_tokens' => $cost_per_1k,
            'cost_per_token' => $cost_per_1k / 1000,
            'is_free' => $is_free,
            'currency' => 'USD',
            'model' => $model,
            'provider' => $this->provider_key,
            'formatted_cost' => $is_free ? 'Free' : '$' . number_format($cost_per_1k, 6) . ' per 1K tokens'
        );
    }
    
    /**
     * Get strategy name
     * 
     * @return string Strategy name
     */
    public function get_strategy_name() {
        return $this->provider_key . '_cost_strategy';
    }
}