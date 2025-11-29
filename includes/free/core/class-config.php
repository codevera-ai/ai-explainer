<?php
/**
 * Plugin Configuration Class
 * 
 * Centralizes all configuration constants, default settings, and configuration logic
 * Eliminates duplication of settings defaults across multiple files
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include brand colors configuration
require_once EXPLAINER_PLUGIN_PATH . 'includes/brand-colors.php';

/**
 * ExplainerPlugin_Config class
 * 
 * Provides centralized configuration management for the plugin
 */
class ExplainerPlugin_Config {

    /**
     * Plugin version
     */
    const VERSION = '1.3.10';

    /**
     * Database version
     */
    const DB_VERSION = '1.2';

    /**
     * Minimum WordPress version
     */
    const MIN_WP_VERSION = '5.0';

    /**
     * Minimum PHP version
     */
    const MIN_PHP_VERSION = '7.4';

    /**
     * Plugin text domain
     */
    const TEXT_DOMAIN = 'ai-explainer';

    /**
     * Option prefix for all plugin options
     */
    const OPTION_PREFIX = 'explainer_';

    /**
     * Transient prefix for caching
     */
    const TRANSIENT_PREFIX = 'explainer_cache_';

    /**
     * Rate limiting transient prefix
     */
    const RATE_LIMIT_PREFIX = 'explainer_rate_';

    /**
     * Theme-specific selector presets
     * Maps theme text domain to optimised content selectors
     *
     * @var array
     */
    private static $theme_presets = array(
        // Popular free themes
        'astra' => '.ast-content, .entry-content',
        'generatepress' => '.entry-content, .inside-article',
        'neve' => '.nv-content-wrap, .entry-content',
        'kadence' => '.entry-content',
        'blocksy' => '.entry-content',
        'oceanwp' => '.entry-content, .entry',
        'hello-elementor' => '.entry-content, article',
        'go' => '.entry-content',
        'hestia' => '.entry-content',
        'zakra' => '.entry-content',
        'colormag' => '.entry-content',
        'sydney' => '.entry-content',

        // Premium multi-purpose themes
        'avada' => '.post-content, .fusion-post-content',
        'divi' => '.et_pb_section, .entry-content',
        'dt-the7' => '.entry-content, .post-content',
        'enfold' => '.entry-content-wrapper',
        'x' => '.entry-content',
        'bridge' => '.post-content',
        'betheme' => '.entry-content, .the_content_wrapper',
        'flatsome' => '.entry-content',
        'salient' => '.post-content',
        'jupiter' => '.entry-content',
        'uncode' => '.post-content',

        // Page builder themes
        'bb-theme' => '.entry-content, .fl-post-content',
        'bricks' => '.brxe-section, article',
        'oxygen' => '.ct-section, article',

        // WooCommerce themes
        'storefront' => '.entry-content',
        'shopkeeper' => '.entry-content',
        'porto' => '.post-content, .entry-content',
        'electro' => '.entry-content',
        'woodmart' => '.entry-content, .product-content',
        'shoptimizer' => '.entry-content',
        'botiga' => '.entry-content',

        // Magazine & blog themes
        'newspaper' => '.td-post-content',
        'newsmag' => '.td-post-content',
        'sahifa' => '.entry-content',
        'jannah' => '.entry-content',
        'soledad' => '.entry-content',
        'publisher' => '.entry-content',
        'bimber' => '.entry-content',
        'viral' => '.entry-content',
        'zeen' => '.entry-content',
        'jnews' => '.entry-content',

        // Portfolio & creative themes
        'oshine' => '.entry-content',
        'kalium' => '.entry-content',
        'h-code' => '.entry-content',
        'brooklyn' => '.entry-content',
        'massive-dynamic' => '.entry-content',

        // Corporate & business themes
        'thegem' => '.entry-content, .post-content',
        'consulting' => '.entry-content',
        'total' => '.entry-content, .wpex-content',
        'kallyas' => '.entry-content',
        'stockholm' => '.entry-content',

        // WordPress default themes
        'twentytwentyfive' => '.entry-content',
        'twentytwentyfour' => '.entry-content',
        'twentytwentythree' => '.entry-content',
        'twentytwentytwo' => '.entry-content',
        'twentytwentyone' => '.entry-content',
        'twentytwenty' => '.entry-content',
        'twentynineteen' => '.entry-content',
    );

    /**
     * Get default selectors based on active theme
     * Returns theme-specific preset if available, otherwise smart defaults
     *
     * @return string CSS selectors for content areas
     */
    public static function get_default_selectors() {
        // Get active theme
        $theme = wp_get_theme();
        $theme_slug = $theme->get('TextDomain');

        // Check if we have a theme-specific preset
        if (isset(self::$theme_presets[$theme_slug])) {
            return self::$theme_presets[$theme_slug];
        }

        // Fallback to comprehensive smart defaults
        return '.entry-content, .elementor-widget-container, .post-content, .et_pb_section, .ast-content, .wpb_wrapper, .fl-post-content, .td-post-content, .fusion-text, .nv-content-wrap, .wp-block-post-content, article .content, main article, .entry-content-wrapper, .brxe-section, article, main';
    }

    /**
     * Get default plugin settings
     * Centralizes all default values to eliminate duplication
     *
     * @return array Default settings
     */
    public static function get_default_settings() {
        return array(
            // Core functionality settings
            'enabled' => true,
            'api_provider' => 'openai',
            'api_model' => 'gpt-5.1',
            'custom_prompt' => self::get_default_custom_prompt(),
            
            // API key settings (encrypted storage)
            'openai_api_key' => '',
            'claude_api_key' => '',
            
            // Text selection limits
            'max_selection_length' => 200,
            'min_selection_length' => 3,
            'max_words' => 30,
            'min_words' => 1,
            
            // DOM selectors
            'included_selectors' => self::get_default_selectors(),
            'excluded_selectors' => '',
            
            // UI customization
            'toggle_position' => 'bottom-right',
            'tooltip_bg_color' => '#333333',
            'tooltip_text_color' => '#ffffff',
            'tooltip_footer_color' => '#ffffff',
            'button_enabled_color' => explainer_get_brand_color('button_enabled', '#8b5cf6'),
            'button_disabled_color' => explainer_get_brand_color('button_disabled', '#94a3b8'),
            'button_text_color' => '#ffffff',
            'slider_track_color' => '#ffffff',
            'slider_thumb_color' => '#8b5cf6',
            
            // Footer options
            'show_disclaimer' => true,
            'show_provider' => true,
            'show_reading_level_slider' => true,
            
            // Performance settings
            'cache_enabled' => true,
            'cache_duration' => 24, // hours
            
            // Rate limiting
            'rate_limit_enabled' => true,
            'rate_limit_logged' => 20,     // requests per hour for logged-in users
            'rate_limit_anonymous' => 10,  // requests per hour for anonymous users
            
            // Advanced features
            'blocked_words' => '',
            'language' => '',  // Empty = use WordPress locale
            'debug_mode' => false,
            'output_seo_metadata' => true,
            
            // Admin interface settings
            'show_usage_stats' => true,
            'auto_disable_on_quota' => true,
        );
    }
    
    /**
     * Get API provider configurations
     * Centralized provider settings and limits
     * 
     * @return array Provider configurations
     */
    public static function get_provider_configs() {
        return array(
            'openai' => array(
                'name' => 'OpenAI',
                'models' => array(
                    'gpt-5.1' => 'GPT-5.1'
                ),
                'default_model' => 'gpt-5.1',
                'api_key_prefix' => 'sk-',
                'api_key_min_length' => 20,
                'api_key_max_length' => 200,
                'max_tokens' => 150,
                'timeout' => 10,
                'temperature' => 0.7
            ),
            'claude' => array(
                'name' => 'Claude (Anthropic)',
                'models' => array(
                    'claude-haiku-4-5' => 'Claude Haiku 4.5'
                ),
                'default_model' => 'claude-haiku-4-5',
                'api_key_prefix' => 'sk-ant-',
                'api_key_min_length' => 20,
                'api_key_max_length' => 200,
                'max_tokens' => 150,
                'timeout' => 10,
                'temperature' => 0.7
            ),
            'gemini' => array(
                'name' => 'Google Gemini',
                'models' => array(
                    'gemini-2.5-flash' => 'Gemini 2.5 Flash'
                ),
                'default_model' => 'gemini-2.5-flash',
                'api_key_prefix' => 'AIza',
                'api_key_min_length' => 39,
                'api_key_max_length' => 39,
                'max_tokens' => 150,
                'timeout' => 10,
                'temperature' => 0.7
            )
        );
    }
    
    /**
     * Get cache duration options for admin interface
     * 
     * @return array Cache duration options
     */
    public static function get_cache_duration_options() {
        return array(
            '1' => __('1 hour', 'ai-explainer'),
            '6' => __('6 hours', 'ai-explainer'),
            '12' => __('12 hours', 'ai-explainer'),
            '24' => __('1 day', 'ai-explainer'),
            '72' => __('3 days', 'ai-explainer'),
            '168' => __('1 week', 'ai-explainer')
        );
    }
    
    /**
     * Get toggle position options
     * 
     * @return array Position options
     */
    public static function get_toggle_position_options() {
        return array(
            'bottom-right' => __('Bottom Right', 'ai-explainer'),
            'bottom-left' => __('Bottom Left', 'ai-explainer'),
            'top-right' => __('Top Right', 'ai-explainer'),
            'top-left' => __('Top Left', 'ai-explainer')
        );
    }
    
    /**
     * Get language options for admin interface
     * 
     * @return array Language options
     */
    public static function get_language_options() {
        return array(
            '' => __('Use WordPress Language', 'ai-explainer'),
            'en_US' => __('English (US)', 'ai-explainer'),
            'en_GB' => __('English (UK)', 'ai-explainer'),
            'es_ES' => __('Spanish', 'ai-explainer'),
            'fr_FR' => __('French', 'ai-explainer'),
            'de_DE' => __('German', 'ai-explainer'),
            'hi_IN' => __('Hindi', 'ai-explainer'),
            'zh_CN' => __('Chinese (Simplified)', 'ai-explainer')
        );
    }
    
    /**
     * Get setting with default fallback
     * Ensures consistent defaults across all usage
     * 
     * @param string $setting_key Setting key (without prefix)
     * @param mixed $default Optional default override
     * @return mixed Setting value or default
     */
    public static function get_setting($setting_key, $default = null) {
        $defaults = self::get_default_settings();
        $option_name = self::OPTION_PREFIX . $setting_key;
        
        // Use provided default or get from defaults array
        $fallback = $default !== null ? $default : (isset($defaults[$setting_key]) ? $defaults[$setting_key] : '');
        
        return get_option($option_name, $fallback);
    }
    
    /**
     * Update setting with validation
     * 
     * @param string $setting_key Setting key (without prefix)
     * @param mixed $value Setting value
     * @return bool Success status
     */
    public static function update_setting($setting_key, $value) {
        $option_name = self::OPTION_PREFIX . $setting_key;
        return update_option($option_name, $value);
    }
    
    /**
     * Get all plugin options with defaults
     * 
     * @return array All plugin settings with defaults applied
     */
    public static function get_all_settings() {
        $defaults = self::get_default_settings();
        $settings = array();
        
        foreach ($defaults as $key => $default_value) {
            $settings[$key] = self::get_setting($key);
        }
        
        return $settings;
    }
    
    /**
     * Get option name with prefix
     * 
     * @param string $key Setting key
     * @return string Full option name
     */
    public static function get_option_name($key) {
        return self::OPTION_PREFIX . $key;
    }
    
    /**
     * Get transient name with prefix
     * 
     * @param string $key Transient key
     * @return string Full transient name
     */
    public static function get_transient_name($key) {
        return self::TRANSIENT_PREFIX . $key;
    }
    
    /**
     * Get rate limit transient name
     * 
     * @param string $key Rate limit key
     * @return string Full rate limit transient name
     */
    public static function get_rate_limit_name($key) {
        return self::RATE_LIMIT_PREFIX . $key;
    }
    
    /**
     * Global configuration data (loaded from config file)
     * 
     * @var array
     */
    private static $global_config = null;
    
    /**
     * Load global configuration from config file
     * 
     * @return array Global configuration
     */
    public static function get_global_config() {
        if (self::$global_config === null) {
            $config_file = EXPLAINER_PLUGIN_PATH . 'includes/config/config.php';
            if (file_exists($config_file)) {
                self::$global_config = require_once $config_file;
                
                // Allow themes/plugins to filter the configuration
                self::$global_config = apply_filters('explainer_plugin_global_config', self::$global_config);
            } else {
                self::$global_config = array();
            }
        }
        
        return self::$global_config;
    }
    
    /**
     * Get a specific global configuration value using dot notation
     * 
     * @param string $key Dot notation key (e.g., 'mock_modes.mock_explanations')
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public static function get_global($key, $default = null) {
        $config = self::get_global_config();
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    /**
     * Check if mock explanations are enabled
     * 
     * @return bool True if mock mode enabled
     */
    public static function is_mock_explanations_enabled() {
        // Check global config first, then fallback to legacy constant
        $config_enabled = self::get_global('mock_modes.mock_explanations', false);
        
        if ($config_enabled) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if mock posts are enabled
     * 
     * @return bool True if mock mode enabled
     */
    public static function is_mock_posts_enabled() {
        // Check global config first, then fallback to legacy constant
        $config_enabled = self::get_global('mock_modes.mock_posts', false);
        
        if ($config_enabled) {
            return true;
        }
        
        // Legacy constant fallback
        return defined('EXPLAINER_MOCK_CONTENT_GENERATION') && EXPLAINER_MOCK_CONTENT_GENERATION === true;
    }
    
    /**
     * Check if debug logging is enabled
     * 
     * @return bool True if debug logging enabled
     */
    public static function is_debug_logging_enabled() {
        return self::get_global('development.debug_logging', false) || (defined('WP_DEBUG') && WP_DEBUG);
    }
    
    /**
     * Check if API call logging is enabled
     * 
     * @return bool True if API logging enabled
     */
    public static function is_api_logging_enabled() {
        return self::get_global('development.log_api_calls', false);
    }
    
    /**
     * Get cache duration for explanations from global config
     * 
     * @return int Cache duration in seconds (0 = disabled)
     */
    public static function get_explanation_cache_duration() {
        return (int) self::get_global('performance.cache_explanations', 0);
    }
    
    /**
     * Get maximum concurrent requests from global config
     * 
     * @return int Maximum concurrent requests
     */
    public static function get_max_concurrent_requests() {
        return (int) self::get_global('performance.max_concurrent_requests', 3);
    }
    
    /**
     * Check if a feature is enabled
     * 
     * @param string $feature Feature name
     * @param bool $default Default value if not set
     * @return bool True if feature enabled
     */
    public static function is_feature_enabled($feature, $default = false) {
        return self::get_global("features.{$feature}", $default);
    }
    
    
    /**
     * Get feature flag setting
     * 
     * @param string $flag Flag name
     * @param bool $default Default value if not set
     * @return bool Flag value
     */
    public static function get_feature_flag($flag, $default = false) {
        return self::get_global("features.{$flag}", $default);
    }
    
    /**
     * Get all mock mode settings
     * 
     * @return array Mock mode configuration
     */
    public static function get_mock_modes() {
        return self::get_global('mock_modes', array(
            'mock_explanations' => false,
            'mock_posts' => false
        ));
    }
    
    /**
     * Check if running on DDEV development environment
     * 
     * @return bool True if DDEV detected
     */
    public static function is_ddev_environment() {
        static $is_ddev = null;
        
        if ($is_ddev !== null) {
            return $is_ddev;
        }
        
        $is_ddev = false;
        
        // Check for DDEV environment indicators
        $ddev_indicators = array(
            // DDEV sets these environment variables
            'DDEV_SITENAME',
            'DDEV_TLD',
            'DDEV_HOSTNAME',
            'IS_DDEV_PROJECT',
            // DDEV container indicators
            'DDEV_PROJECT',
            'DDEV_PROJECT_TYPE'
        );
        
        // Check environment variables
        foreach ($ddev_indicators as $indicator) {
            if (!empty($_SERVER[$indicator])) {
                $is_ddev = true;
                break;
            }
        }

        // Check hostname patterns (DDEV uses .ddev.site by default)
        if (!$is_ddev && isset($_SERVER['HTTP_HOST'])) {
            $host = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']));
            if (strpos($host, '.ddev.site') !== false || strpos($host, '.ddev.local') !== false) {
                $is_ddev = true;
            }
        }
        
        // Check WordPress site URL for DDEV patterns
        if (!$is_ddev) {
            $site_url = get_site_url();
            if (strpos($site_url, '.ddev.site') !== false || strpos($site_url, '.ddev.local') !== false) {
                $is_ddev = true;
            }
        }
        
        // Check for DDEV-specific file structure indicators
        if (!$is_ddev && defined('ABSPATH')) {
            // DDEV projects often have .ddev directory in project root
            $possible_ddev_paths = array(
                ABSPATH . '../.ddev',
                ABSPATH . '../../.ddev',
                ABSPATH . '../../../.ddev'
            );
            
            foreach ($possible_ddev_paths as $path) {
                if (is_dir($path)) {
                    $is_ddev = true;
                    break;
                }
            }
        }
        
        return $is_ddev;
    }
    
    /**
     * Clear configuration cache (useful for testing)
     */
    public static function clear_cache() {
        self::$global_config = null;
    }

    /**
     * Get JavaScript configuration for frontend
     * 
     * @return array JavaScript configuration
     */
    public static function get_js_config() {
        return array(
            'enabled' => self::get_setting('enabled'),
            'max_selection_length' => (int) self::get_setting('max_selection_length'),
            'min_selection_length' => (int) self::get_setting('min_selection_length'),
            'max_words' => (int) self::get_setting('max_words'),
            'min_words' => (int) self::get_setting('min_words'),
            'included_selectors' => self::get_setting('included_selectors'),
            'excluded_selectors' => self::get_setting('excluded_selectors'),
            'toggle_position' => self::get_setting('toggle_position'),
            'tooltip_bg_color' => self::get_setting('tooltip_bg_color'),
            'tooltip_text_color' => self::get_setting('tooltip_text_color'),
            'button_enabled_color' => self::get_setting('button_enabled_color'),
            'button_disabled_color' => self::get_setting('button_disabled_color'),
            'button_text_color' => self::get_setting('button_text_color'),
            'slider_track_color' => self::get_setting('slider_track_color'),
            'slider_thumb_color' => self::get_setting('slider_thumb_color'),
            'cache_enabled' => self::get_setting('cache_enabled'),
            'cache_duration' => (int) self::get_setting('cache_duration'),
            'rate_limit_enabled' => self::get_setting('rate_limit_enabled'),
            'rate_limit_logged' => (int) self::get_setting('rate_limit_logged'),
            'rate_limit_anonymous' => (int) self::get_setting('rate_limit_anonymous'),
            'mobile_selection_delay' => (int) self::get_setting('mobile_selection_delay'),
            'api_provider' => self::get_setting('api_provider'),
            'api_model' => self::get_setting('api_model'),
            'custom_prompt' => self::get_setting('custom_prompt'),
            'show_disclaimer' => self::get_setting('show_disclaimer'),
            'show_provider' => self::get_setting('show_provider'),
            'show_reading_level_slider' => (bool) self::get_setting('show_reading_level_slider'),
            'tooltip_footer_color' => self::get_setting('tooltip_footer_color'),
            'debug_mode' => self::get_setting('debug_mode'),
            'stop_word_filtering_enabled' => self::get_setting('stop_word_filtering_enabled'),
            'reading_level_labels' => self::get_reading_level_labels(),
            'api_key_configured' => self::is_api_key_configured()
        );
    }

    /**
     * Get JavaScript debug configuration
     * 
     * @return array Debug configuration for JavaScript
     */
    public static function get_js_debug_config() {
        $debug_config = self::get_global('debug_logging', array());
        
        // Extract only the settings needed for JavaScript
        return array(
            'enabled' => isset($debug_config['enabled']) ? (bool) $debug_config['enabled'] : false,
            'sections' => isset($debug_config['sections']) ? $debug_config['sections'] : array()
        );
    }
    
    /**
     * Get default custom prompt
     * 
     * @return string Default custom prompt template
     */
    public static function get_default_custom_prompt() {
        return 'Explain only this term: {{selectedtext}}. Provide a clear, concise explanation in 1-2 sentences. Do not mention or explain any other terms, concepts, or context provided.';
    }
    
    /**
     * Get default reading level prompts
     * Centralizes all reading level prompt templates
     *
     * @return array Reading level prompts
     */
    public static function get_default_reading_level_prompts() {
        return array(
            'very_simple' => 'Explain this term to a 10-year-old using simple language, analogies, and relatable examples: {{selectedtext}}. Keep it clear and straightforward without being patronising.',
            'simple' => 'Explain this term as if talking to someone who has never heard it before: {{selectedtext}}. Use plain language, avoid jargon, and keep it straightforward and accessible.',
            'standard' => 'Explain this term clearly and concisely: {{selectedtext}}. Provide a balanced explanation that covers the key points without oversimplifying or overcomplicating.',
            'detailed' => 'Explain this term with comprehensive context, practical examples, and background information: {{selectedtext}}. Cover how it works, why it matters, how it relates to other concepts, and include real-world applications. Help the reader develop a thorough understanding.',
            'expert' => 'Explain this term for a professional or academic audience with deep technical detail: {{selectedtext}}. Use precise terminology, discuss nuances and edge cases, reference theoretical frameworks where relevant, and assume the reader has advanced knowledge in the subject area. Include technical implications and considerations.'
        );
    }
    
    /**
     * Get specific reading level prompt
     * 
     * @param string $reading_level Reading level key
     * @return string Prompt template for the reading level
     */
    public static function get_default_reading_level_prompt($reading_level) {
        $prompts = self::get_default_reading_level_prompts();
        
        if (isset($prompts[$reading_level])) {
            return $prompts[$reading_level];
        }
        
        // Fallback to standard level
        return $prompts['standard'];
    }
    
    /**
     * Get default term extraction prompt
     * 
     * @return string Default term extraction prompt template
     */
    public static function get_default_term_extraction_prompt() {
        return 'Analyze the following blog post content and identify technical terms, jargon, acronyms, or concepts that readers might find confusing or need explanation. Return exactly 10-50 terms as a valid JSON array with format: [{"term": "technical_term", "explanation": "clear explanation"}]

Requirements:
- Focus on technical, domain-specific, or potentially confusing terms
- Avoid common words everyone knows (the, and, is, etc.)
- Provide clear, concise explanations (1-2 sentences maximum)
- Return ONLY valid JSON array, no other text
- Each explanation should be suitable for general audiences

Content to analyze:
{{post_content}}';
    }
    
    /**
     * Get reading level labels
     * 
     * @return array Reading level labels for admin interface
     */
    public static function get_reading_level_labels() {
        return array(
            'very_simple' => __('Basic', 'ai-explainer'),
            'simple' => __('Simple', 'ai-explainer'),
            'standard' => __('Standard', 'ai-explainer'),
            'detailed' => __('Detailed', 'ai-explainer'),
            'expert' => __('Expert', 'ai-explainer')
        );
    }
    
    /**
     * Check if an API key is configured for the current provider
     * 
     * @return bool True if API key is configured
     */
    public static function is_api_key_configured() {
        // Use the factory method to check if we have a valid API key
        $decrypted_key = ExplainerPlugin_Provider_Factory::get_current_decrypted_api_key();
        return !empty($decrypted_key);
    }
}