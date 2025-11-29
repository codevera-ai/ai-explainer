<?php
/**
 * AI Provider Factory
 * 
 * Creates and manages AI provider instances
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Factory class for AI providers
 */
class ExplainerPlugin_Provider_Factory {
    
    /**
     * Provider instances cache
     * 
     * @var array
     */
    private static $providers = array();
    
    /**
     * Available providers (loaded from registry)
     * 
     * @var array|null
     */
    private static $available_providers = null;
    
    /**
     * Load available providers from registry
     * 
     * @return array Available providers array
     */
    private static function get_available_providers_list() {
        if (self::$available_providers === null) {
            self::$available_providers = ExplainerPlugin_AI_Provider_Registry::get_provider_classes();
        }
        
        return self::$available_providers;
    }
    
    /**
     * Get provider instance
     * 
     * @param string $provider_key Provider key (openai, claude, openrouter, gemini)
     * @param array $config Provider configuration
     * @return ExplainerPlugin_AI_Provider_Interface|null Provider instance or null if not found
     */
    public static function get_provider($provider_key, $config = array()) {
        $available_providers = self::get_available_providers_list();
        
        // Check if provider is available
        if (!isset($available_providers[$provider_key])) {
            return null;
        }
        
        // Return cached instance if available
        if (isset(self::$providers[$provider_key])) {
            return self::$providers[$provider_key];
        }
        
        $class_name = $available_providers[$provider_key];
        
        // Check if class exists
        if (!class_exists($class_name)) {
            return null;
        }
        
        // Create and cache provider instance
        self::$providers[$provider_key] = new $class_name($config);
        
        return self::$providers[$provider_key];
    }
    
    /**
     * Get current provider based on settings
     * 
     * @return ExplainerPlugin_AI_Provider_Interface|null Current provider or null if not configured
     */
    public static function get_current_provider() {
        $provider_key = get_option('explainer_api_provider', 'openai');
        return self::get_provider($provider_key);
    }
    
    /**
     * Get available providers list
     * 
     * @return array Array of provider_key => provider_name
     */
    public static function get_available_providers() {
        return ExplainerPlugin_AI_Provider_Registry::get_available_providers();
    }
    
    /**
     * Get available providers configuration array
     * 
     * @return array Array of provider_key => class_name
     */
    public static function get_available_providers_config() {
        return self::get_available_providers_list();
    }
    
    /**
     * Get available models for current provider
     * 
     * @return array Array of model_key => model_label
     */
    public static function get_current_provider_models() {
        $provider = self::get_current_provider();
        
        if (!$provider) {
            return array();
        }
        
        return $provider->get_models();
    }
    
    /**
     * Get API key for current provider (returns encrypted key)
     * 
     * @return string Encrypted API key or empty string if not configured
     */
    public static function get_current_api_key() {
        $provider_key = get_option('explainer_api_provider', 'openai');
        $api_key_option = ExplainerPlugin_AI_Provider_Registry::get_api_key_option($provider_key);
        
        if (!$api_key_option) {
            return '';
        }
        
        return get_option($api_key_option, '');
    }
    
    /**
     * Get decrypted API key for current provider
     * 
     * @return string Decrypted API key or empty string if not configured
     */
    public static function get_current_decrypted_api_key() {
        $provider_key = get_option('explainer_api_provider', 'openai');
        $api_proxy = new ExplainerPlugin_API_Proxy();
        
        return $api_proxy->get_decrypted_api_key_for_provider($provider_key, true);
    }
    
    /**
     * Validate API key for current provider
     * 
     * @param string $api_key API key to validate
     * @return bool True if valid
     */
    public static function validate_current_api_key($api_key) {
        $provider = self::get_current_provider();
        
        if (!$provider) {
            return false;
        }
        
        return $provider->validate_api_key($api_key);
    }
    
    /**
     * Test API key for current provider
     * 
     * @param string $api_key API key to test
     * @return array Test result
     */
    public static function test_current_api_key($api_key) {
        $provider = self::get_current_provider();
        
        if (!$provider) {
            return array(
                'success' => false,
                'message' => __('No provider configured.', 'ai-explainer')
            );
        }
        
        return $provider->test_api_key($api_key);
    }
    
    /**
     * Clear provider cache
     */
    public static function clear_cache() {
        self::$providers = array();
        self::$available_providers = null;
        ExplainerPlugin_AI_Provider_Registry::clear_cache();
    }
    
    /**
     * Register a new provider (runtime registration)
     * 
     * @param string $key Provider key
     * @param string $class_name Provider class name
     */
    public static function register_provider($key, $class_name) {
        $available_providers = self::get_available_providers_list();
        $available_providers[$key] = $class_name;
        self::$available_providers = $available_providers;
    }
    
    /**
     * Check if a provider is available
     * 
     * @param string $provider_key Provider key
     * @return bool True if available
     */
    public static function is_provider_available($provider_key) {
        return ExplainerPlugin_AI_Provider_Registry::provider_exists($provider_key);
    }
}