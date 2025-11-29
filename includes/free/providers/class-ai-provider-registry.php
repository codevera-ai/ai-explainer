<?php
/**
 * AI Provider Registry
 * 
 * Central registry for loading and managing AI provider configurations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Provider Registry class
 */
class ExplainerPlugin_AI_Provider_Registry {
    
    /**
     * Cached configuration data
     * 
     * @var array
     */
    private static $config = null;
    
    /**
     * Get the complete AI providers configuration
     * 
     * @return array Complete providers configuration
     */
    public static function get_config() {
        if (self::$config === null) {
            $config_file = EXPLAINER_PLUGIN_PATH . 'includes/config/ai-providers.php';
            self::$config = require $config_file;
            
            // Allow themes/plugins to filter the configuration
            self::$config = apply_filters('explainer_ai_providers_config', self::$config);
        }
        
        return self::$config;
    }
    
    /**
     * Get configuration for a specific provider
     * 
     * @param string $provider_key Provider key (openai, claude, etc.)
     * @return array|null Provider configuration or null if not found
     */
    public static function get_provider_config($provider_key) {
        $config = self::get_config();
        return isset($config[$provider_key]) ? $config[$provider_key] : null;
    }
    
    /**
     * Get all available providers (keys and names only)
     * 
     * @return array Array of provider_key => provider_name
     */
    public static function get_available_providers() {
        $config = self::get_config();
        $providers = array();
        
        foreach ($config as $provider_key => $provider_data) {
            $providers[$provider_key] = $provider_data['name'];
        }
        
        return $providers;
    }
    
    /**
     * Get all available providers with their class names
     * 
     * @return array Array of provider_key => class_name
     */
    public static function get_provider_classes() {
        $config = self::get_config();
        $providers = array();
        
        foreach ($config as $provider_key => $provider_data) {
            $providers[$provider_key] = $provider_data['class'];
        }
        
        return $providers;
    }
    
    /**
     * Get models for a specific provider
     * 
     * @param string $provider_key Provider key
     * @return array Provider models or empty array if not found
     */
    public static function get_provider_models($provider_key) {
        $provider_config = self::get_provider_config($provider_key);
        return isset($provider_config['models']) ? $provider_config['models'] : array();
    }
    
    /**
     * Get models for a provider formatted for WordPress admin dropdowns
     * 
     * @param string $provider_key Provider key
     * @return array Array of model_key => label for WordPress admin
     */
    public static function get_provider_models_for_admin($provider_key) {
        $models = self::get_provider_models($provider_key);
        $admin_models = array();
        
        foreach ($models as $model_key => $model_data) {
            // Support both new detailed format and legacy simple format
            if (is_array($model_data) && isset($model_data['label'])) {
                $admin_models[$model_key] = $model_data['label'];
            } else {
                // Legacy fallback for simple string values
                $admin_models[$model_key] = $model_data;
            }
        }
        
        return $admin_models;
    }
    
    /**
     * Get all models from all providers
     * 
     * @return array Array of provider:model => model_info
     */
    public static function get_all_models() {
        $config = self::get_config();
        $all_models = array();
        
        foreach ($config as $provider_key => $provider_data) {
            if (isset($provider_data['models']) && is_array($provider_data['models'])) {
                foreach ($provider_data['models'] as $model_key => $model_data) {
                    $combined_key = $provider_key . ':' . $model_key;
                    
                    // Support both new detailed format and legacy simple format
                    if (is_array($model_data)) {
                        $all_models[$combined_key] = array_merge($model_data, array(
                            'provider' => $provider_key,
                            'provider_name' => $provider_data['name'],
                            'model' => $model_key
                        ));
                    } else {
                        // Legacy fallback for simple string values
                        $all_models[$combined_key] = array(
                            'provider' => $provider_key,
                            'provider_name' => $provider_data['name'],
                            'model' => $model_key,
                            'label' => $model_data,
                            'max_tokens' => 4096, // Default fallback
                            'cost_per_1k_tokens' => 0.002, // Default fallback
                            'temperature' => 0.7
                        );
                    }
                }
            }
        }
        
        return $all_models;
    }
    
    /**
     * Get API key option name for a provider
     * 
     * @param string $provider_key Provider key
     * @return string|null API key option name or null if not found
     */
    public static function get_api_key_option($provider_key) {
        $provider_config = self::get_provider_config($provider_key);
        return isset($provider_config['api_key_option']) ? $provider_config['api_key_option'] : null;
    }
    
    /**
     * Get API endpoint for a provider
     * 
     * @param string $provider_key Provider key
     * @return string|null API endpoint or null if not found
     */
    public static function get_api_endpoint($provider_key) {
        $provider_config = self::get_provider_config($provider_key);
        return isset($provider_config['endpoint']) ? $provider_config['endpoint'] : null;
    }
    
    /**
     * Get API key validation info for a provider
     * 
     * @param string $provider_key Provider key
     * @return array API key validation info (prefix, min_length, max_length)
     */
    public static function get_api_key_validation($provider_key) {
        $provider_config = self::get_provider_config($provider_key);
        
        return array(
            'prefix' => isset($provider_config['api_key_prefix']) ? $provider_config['api_key_prefix'] : '',
            'min_length' => isset($provider_config['api_key_min_length']) ? $provider_config['api_key_min_length'] : 10,
            'max_length' => isset($provider_config['api_key_max_length']) ? $provider_config['api_key_max_length'] : 200
        );
    }
    
    /**
     * Check if a provider exists
     * 
     * @param string $provider_key Provider key to check
     * @return bool True if provider exists
     */
    public static function provider_exists($provider_key) {
        $config = self::get_config();
        return isset($config[$provider_key]);
    }
    
    /**
     * Check if a model exists for a provider
     * 
     * @param string $provider_key Provider key
     * @param string $model_key Model key
     * @return bool True if model exists for the provider
     */
    public static function model_exists($provider_key, $model_key) {
        $models = self::get_provider_models($provider_key);
        return isset($models[$model_key]);
    }
    
    /**
     * Get model configuration for a specific provider and model
     * 
     * @param string $provider_key Provider key
     * @param string $model_key Model key
     * @return array|null Model configuration or null if not found
     */
    public static function get_model_config($provider_key, $model_key) {
        $models = self::get_provider_models($provider_key);
        return isset($models[$model_key]) ? $models[$model_key] : null;
    }
    
    /**
     * Clear configuration cache (useful for testing)
     */
    public static function clear_cache() {
        self::$config = null;
    }
    
    /**
     * Get provider configuration for JavaScript (admin interface)
     * 
     * @return array Provider configuration formatted for JavaScript
     */
    public static function get_js_config() {
        $config = self::get_config();
        $js_config = array();
        
        foreach ($config as $provider_key => $provider_data) {
            $js_config[$provider_key] = array(
                'name' => $provider_data['name'],
                'models' => self::get_provider_models_for_admin($provider_key),
                'api_key_option' => $provider_data['api_key_option'],
                'has_region' => isset($provider_data['has_region']) ? $provider_data['has_region'] : false
            );
        }
        
        return $js_config;
    }
}