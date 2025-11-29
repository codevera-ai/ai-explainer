<?php
/**
 * Provider Configuration Management Class
 * 
 * Centralizes provider configuration and eliminates duplication
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralized provider configuration management
 */
class ExplainerPlugin_Provider_Config {
    
    /**
     * Provider configuration cache
     * 
     * @var array
     */
    private static $config_cache = null;
    
    /**
     * Get complete provider configuration
     * 
     * @return array Provider configuration
     */
    public static function get_provider_config() {
        if (self::$config_cache === null) {
            self::$config_cache = self::build_provider_config();
        }
        
        return self::$config_cache;
    }
    
    /**
     * Get configuration for a specific provider
     * 
     * @param string $provider_key Provider key
     * @return array|null Provider configuration or null if not found
     */
    public static function get_provider_config_by_key($provider_key) {
        $config = self::get_provider_config();
        return isset($config[$provider_key]) ? $config[$provider_key] : null;
    }
    
    /**
     * Build provider configuration from factory data
     * 
     * @return array Complete provider configuration
     */
    private static function build_provider_config() {
        $config = array();
        $registry_config = ExplainerPlugin_AI_Provider_Registry::get_config();
        
        foreach ($registry_config as $key => $provider_data) {
            // Create instance to get dynamic data (name, max_tokens, etc.)
            $provider = ExplainerPlugin_Provider_Factory::get_provider($key);
            if ($provider) {
                $config[$key] = array(
                    'name' => $provider_data['name'], // Use registry name for consistency
                    'models' => ExplainerPlugin_AI_Provider_Registry::get_provider_models_for_admin($key),
                    'apiKey' => self::get_api_key_option_name($key),
                    'hasRegion' => isset($provider_data['has_region']) ? $provider_data['has_region'] : false,
                    'hide_model_selector' => isset($provider_data['hide_model_selector']) ? $provider_data['hide_model_selector'] : false,
                    'endpoint' => isset($provider_data['endpoint']) ? $provider_data['endpoint'] : $provider->get_api_endpoint(),
                    'max_tokens' => $provider->get_max_tokens(), // Still need instance for this
                    'timeout' => $provider->get_timeout() // Still need instance for this
                );
            }
        }
        
        return $config;
    }
    
    /**
     * Get API key option name for provider
     * 
     * @param string $provider_key Provider key
     * @return string WordPress option name for API key
     */
    private static function get_api_key_option_name($provider_key) {
        // Use registry first, fallback to legacy mapping for backwards compatibility
        $registry_option = ExplainerPlugin_AI_Provider_Registry::get_api_key_option($provider_key);
        if ($registry_option) {
            return $registry_option;
        }
        
        // Legacy fallback mapping for backwards compatibility
        $key_mapping = array(
            'openai' => 'explainer_openai_api_key',
            'claude' => 'explainer_claude_api_key',
            'openrouter' => 'explainer_openrouter_api_key',
            'gemini' => 'explainer_gemini_api_key'
        );
        
        return isset($key_mapping[$provider_key]) ? $key_mapping[$provider_key] : 'explainer_' . $provider_key . '_api_key';
    }
    
    /**
     * Check if provider supports region configuration
     * 
     * @param string $provider_key Provider key
     * @return bool True if provider supports regions
     */
    private static function provider_has_region($provider_key) {
        return false;
    }
    
    /**
     * Get all provider names for dropdown
     * 
     * @return array Provider key => name pairs
     */
    public static function get_provider_names() {
        $config = self::get_provider_config();
        $names = array();
        
        foreach ($config as $key => $data) {
            $names[$key] = $data['name'];
        }
        
        return $names;
    }
    
    /**
     * Get models for a specific provider
     * 
     * @param string $provider_key Provider key
     * @return array Model key => label pairs
     */
    public static function get_provider_models($provider_key) {
        $provider_config = self::get_provider_config_by_key($provider_key);
        return $provider_config ? $provider_config['models'] : array();
    }
    
    /**
     * Get API key field name for provider
     * 
     * @param string $provider_key Provider key
     * @return string API key field name
     */
    public static function get_api_key_field($provider_key) {
        $provider_config = self::get_provider_config_by_key($provider_key);
        return $provider_config ? $provider_config['apiKey'] : '';
    }
    
    /**
     * Check if provider supports regions
     * 
     * @param string $provider_key Provider key
     * @return bool True if provider supports regions
     */
    public static function provider_supports_regions($provider_key) {
        $provider_config = self::get_provider_config_by_key($provider_key);
        return $provider_config ? $provider_config['hasRegion'] : false;
    }
    
    /**
     * Clear configuration cache (useful for testing)
     */
    public static function clear_cache() {
        self::$config_cache = null;
    }
    
    /**
     * Validate provider configuration
     * 
     * @param string $provider_key Provider key to validate
     * @return bool True if provider configuration is valid
     */
    public static function validate_provider_config($provider_key) {
        $config = self::get_provider_config_by_key($provider_key);
        
        if (!$config) {
            return false;
        }
        
        // Check required fields
        $required_fields = array('name', 'models', 'apiKey', 'endpoint');
        foreach ($required_fields as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                return false;
            }
        }
        
        return true;
    }
}