<?php
/**
 * Admin functionality for the plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle admin functionality
 */
class ExplainerPlugin_Admin {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * API proxy instance
     */
    private $api_proxy;
    
    /**
     * Validator instance
     */
    private $validator;
    
    /**
     * Asset manager instance
     */
    private $asset_manager;
    
    /**
     * Utilities instance
     */
    private $utilities;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize the admin class
     */
    private function __construct() {
        // Debug logging to track admin initialization
        ExplainerPlugin_Debug_Logger::info('ExplainerPlugin_Admin constructor called', 'Admin');
        
        // Load admin helper classes
        require_once EXPLAINER_PLUGIN_PATH . 'includes/admin/class-validator.php';
        require_once EXPLAINER_PLUGIN_PATH . 'includes/admin/class-asset-manager.php';
        require_once EXPLAINER_PLUGIN_PATH . 'includes/admin/class-utilities.php';
        
        // Load pagination classes
        ExplainerPlugin_Debug_Logger::debug('Loading pagination classes', 'Admin');
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-pagination.php';
        ExplainerPlugin_Debug_Logger::debug('Loaded class-pagination.php', 'Admin');
        require_once EXPLAINER_PLUGIN_PATH . 'includes/admin/ajax/class-pagination-handlers.php';
        ExplainerPlugin_Debug_Logger::debug('Loaded class-pagination-handlers.php', 'Admin');
        
        $this->api_proxy = new ExplainerPlugin_API_Proxy();
        $this->validator = new ExplainerPlugin_Validator();
        $this->asset_manager = new ExplainerPlugin_Asset_Manager();
        $this->utilities = new ExplainerPlugin_Utilities();
        
        // Hook to process API keys before saving
        add_filter('pre_update_option_explainer_openai_api_key', array($this, 'process_openai_api_key_save'), 10, 2);
        add_filter('pre_update_option_explainer_claude_api_key', array($this, 'process_claude_api_key_save'), 10, 2);
        add_filter('pre_update_option_explainer_openrouter_api_key', array($this, 'process_openrouter_api_key_save'), 10, 2);
        add_filter('pre_update_option_explainer_gemini_api_key', array($this, 'process_gemini_api_key_save'), 10, 2);
        
        // Hook to process custom prompt before saving
        add_filter('pre_update_option_explainer_custom_prompt', array($this, 'process_custom_prompt_save'), 10, 2);
        
        // Hook to process term extraction prompt before saving
        add_filter('pre_update_option_explainer_term_extraction_prompt', array($this, 'process_term_extraction_prompt_save'), 10, 2);
        
        
        // Hook to process debug settings before saving
        add_filter('pre_update_option_explainer_debug_enabled', array($this, 'process_debug_enabled_save'), 10, 2);
        
        // Add hooks for all debug sections dynamically
        if (class_exists('ExplainerPlugin_Debug_Logger')) {
            $sections = ExplainerPlugin_Debug_Logger::get_all_sections();
            foreach ($sections as $section => $config) {
                add_filter('pre_update_option_explainer_debug_section_' . $section, array($this, 'process_debug_section_save'), 10, 2);
            }
        }

        // Hook validation for restricted configuration options
        $this->hook_config_validation();

        // Hooks are registered in the main plugin file through the loader system
        
        // Register AJAX handlers for job management
        add_action('wp_ajax_explainer_get_jobs', array($this, 'handle_get_jobs'));
        add_action('wp_ajax_explainer_cancel_job', array($this, 'handle_cancel_job'));
        add_action('wp_ajax_explainer_retry_job', array($this, 'handle_retry_job'));
        add_action('wp_ajax_explainer_clear_completed_jobs', array($this, 'handle_clear_completed_jobs'));
        add_action('wp_ajax_explainer_run_single_job', array($this, 'handle_run_single_job'));

        // Onboarding help dismissal
        add_action('wp_ajax_explainer_dismiss_getting_started', array($this, 'handle_dismiss_getting_started'));

        // Custom license activation handler - manually register Freemius AJAX handler (only if Freemius is loaded)
        if (function_exists('wpaie_freemius')) {
            $this->register_freemius_license_activation();
        }

        // Customize Freemius activation success message (only if Freemius is loaded)
        if (function_exists('wpaie_freemius')) {
            $this->customize_freemius_messages();
        }

        // Show Pro download banner for free users with active license (only if Freemius is loaded)
        if (function_exists('wpaie_freemius')) {
            add_action('admin_notices', array($this, 'show_pro_download_banner'));
        }

        // AI Terms Modal AJAX handlers registered below (lines 471-477) - removed duplicate registration
        
        // Debug: Log all AJAX requests to see what's happening - Debug logging only, no data modification
        add_action('wp_ajax_nopriv_explainer_run_single_job', function() {
            // phpcs:disable WordPress.Security.NonceVerification.Missing
            ExplainerPlugin_Debug_Logger::debug('NOPRIV AJAX called for explainer_run_single_job', 'admin', array(
                'user_id' => get_current_user_id(),
                'is_user_logged_in' => is_user_logged_in(),
                'cookies_set' => isset($_COOKIE[LOGGED_IN_COOKIE]) ? 'yes' : 'no',
                'nonce_provided' => isset($_POST['nonce']) ? 'yes' : 'no',
                'nonce_value' => isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : 'none',
                'all_cookies' => array_keys($_COOKIE),
                'wordpress_logged_in_cookie' => defined('LOGGED_IN_COOKIE') ? LOGGED_IN_COOKIE : 'undefined',
                'ajax_url' => admin_url('admin-ajax.php'),
                'request_uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : 'unknown'
            ));
            // phpcs:enable WordPress.Security.NonceVerification.Missing
            wp_send_json_error(array('message' => 'User not authenticated for job execution - check browser cookies and login status'));
        });
        add_action('admin_init', function() {
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- Debug logging only, no data modification
            if (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action'])) {
                if ($_POST['action'] === 'explainer_run_single_job') {
                    ExplainerPlugin_Debug_Logger::debug('AJAX action detected in admin_init: explainer_run_single_job', 'admin');
                }
            }
            // phpcs:enable WordPress.Security.NonceVerification.Missing
        });
        // Background job handler removed - using synchronous processing only
        
        
        // Register AJAX handlers for API key management
        add_action('wp_ajax_wp_ai_explainer_test_api_key', array($this, 'handle_test_api_key'));
        add_action('wp_ajax_wp_ai_explainer_test_stored_api_key', array($this, 'handle_test_stored_api_key'));
        add_action('wp_ajax_wp_ai_explainer_delete_api_key', array($this, 'handle_delete_api_key'));
        add_action('wp_ajax_wp_ai_explainer_get_provider_config', array($this, 'handle_get_provider_config'));
        
        // Pro AJAX handlers (conditional)
        if (file_exists(EXPLAINER_PLUGIN_PATH . 'includes/pro/admin/class-pro-admin-handlers.php')) {
            require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/admin/class-pro-admin-handlers.php';
            new ExplainerPlugin_Pro_Admin_Handlers();
        }

        // Pro blog creator (conditional)
        if (class_exists('ExplainerPlugin_Blog_Creator')) {
            new ExplainerPlugin_Blog_Creator();
        }
        
        // Initialize pagination handlers
        ExplainerPlugin_Debug_Logger::debug('About to instantiate ExplainerPlugin_Pagination_Handlers', 'Admin');
        try {
            new ExplainerPlugin_Pagination_Handlers();
            ExplainerPlugin_Debug_Logger::debug('ExplainerPlugin_Pagination_Handlers instantiated successfully', 'Admin');
        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/debug-admin.log', current_time('mysql') . ' - ERROR instantiating ExplainerPlugin_Pagination_Handlers: ' . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
    
    /**
     * Helper method to register settings with consistent patterns
     * Eliminates code duplication in settings registration
     * 
     * @param string $name Setting name (without 'explainer_' prefix)
     * @param string $type Setting type (boolean, text, textarea, color, validation_method)
     * @param string|callable $validator Custom validator (method name or callable)
     * @return void
     */
    private function register_plugin_setting($name, $type = 'text', $validator = null) {
        $full_name = 'explainer_' . $name;
        
        // Determine sanitization callback based on type
        $sanitize_callback = $this->get_sanitization_callback($type, $validator);
        
        register_setting('explainer_settings', $full_name, array(
            'sanitize_callback' => $sanitize_callback
        ));
    }
    
    /**
     * Get appropriate sanitization callback based on setting type
     *
     * @param string $type Setting type
     * @param string|callable $validator Custom validator
     * @return string|callable Sanitization callback
     */
    private function get_sanitization_callback($type, $validator = null) {
        // If custom validator provided, use it
        if ($validator !== null) {
            if (is_string($validator) && method_exists($this, $validator)) {
                return array($this, $validator);
            }
            if (is_callable($validator)) {
                return $validator;
            }
        }

        // Default sanitization based on type
        switch ($type) {
            case 'boolean':
                return 'absint';
            case 'color':
                return 'sanitize_hex_color';
            case 'textarea':
                return 'wp_kses_post';
            case 'text':
            default:
                return 'sanitize_text_field';
        }
    }

    /**
     * Sanitize API key value
     *
     * This is a pass-through sanitization callback required by WordPress Plugin Check.
     * Actual API key processing (encryption, validation) is handled by pre_update_option filters.
     *
     * @param string $value API key value
     * @return string Sanitized API key value
     */
    public function sanitize_api_key($value) {
        // Sanitize as text field to remove any malicious content
        // Actual encryption/processing happens in pre_update_option filters
        return sanitize_text_field($value);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Custom AI-themed icon as base64 data URI
        $icon_svg_base64 = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCIgZmlsbD0iY3VycmVudENvbG9yIj4KICA8cGF0aCBkPSJNMTAgMkM3LjIgMiA1IDQuMiA1IDdjMCAuOC4yIDEuNS41IDIuMkw0IDEwLjdjLS4zLjMtLjMuOCAwIDEuMWwuOS45Yy4zLjMuOC4zIDEuMSAwTDcuNSAxMWMuNy4zIDEuNC41IDIuMi41aC42Yy44IDAgMS41LS4yIDIuMi0uNUwxNCAxMi43Yy4zLjMuOC4zIDEuMSAwbC45LS45Yy4zLS4zLjMtLjggMC0xLjFsLTEuNS0xLjVjLjMtLjcuNS0xLjQuNS0yLjIgMC0yLjgtMi4yLTUtNS01em0tMiA1YzAtLjYuNC0xIDEtMXMxIC40IDEgMS0uNCAxLTEgMS0xLS40LTEtMXptMyAwYzAtLjYuNC0xIDEtMXMxIC40IDEgMS0uNCAxLTEgMS0xLS40LTEtMXoiLz4KICA8cGF0aCBkPSJNNiAxNGg4djFINnYtMXptMSAyaDZ2MUg3di0xem0xIDJoNHYxSDh2LTF6Ii8+CiAgPGNpcmNsZSBjeD0iOCIgY3k9IjUiIHI9Ii41Ii8+CiAgPGNpcmNsZSBjeD0iMTIiIGN5PSI1IiByPSIuNSIvPgogIDxjaXJjbGUgY3g9IjEwIiBjeT0iNCIgcj0iLjUiLz4KPC9zdmc+';
        
        add_menu_page(
            __('AI Explainer Settings', 'ai-explainer'),
            __('AI Explainer', 'ai-explainer'),
            'manage_options',
            'wp-ai-explainer-admin',
            array($this, 'settings_page'),
            $icon_svg_base64,
            30
        );
    }
    
    /**
     * Initialize settings using helper method to eliminate duplication
     */
    public function settings_init() {
        // Register all settings using helper method - eliminates 30+ lines of duplication
        $this->register_plugin_setting('enabled', 'boolean');
        $this->register_plugin_setting('onboarding_enabled', 'boolean');
        $this->register_plugin_setting('language', 'text', 'validate_language');
        $this->register_plugin_setting('api_provider', 'text', 'validate_api_provider');
        // API keys with sanitization callback (actual processing handled by pre_update_option filters)
        register_setting('explainer_settings', 'explainer_openai_api_key', array(
            'sanitize_callback' => array($this, 'sanitize_api_key')
        ));
        register_setting('explainer_settings', 'explainer_claude_api_key', array(
            'sanitize_callback' => array($this, 'sanitize_api_key')
        ));
        register_setting('explainer_settings', 'explainer_openrouter_api_key', array(
            'sanitize_callback' => array($this, 'sanitize_api_key')
        ));
        register_setting('explainer_settings', 'explainer_gemini_api_key', array(
            'sanitize_callback' => array($this, 'sanitize_api_key')
        ));
        $this->register_plugin_setting('api_model', 'text');
        $this->register_plugin_setting('custom_prompt', 'text', 'validate_custom_prompt');
        
        // Register reading level prompts
        $this->register_plugin_setting('prompt_very_simple', 'textarea', 'validate_reading_level_prompt');
        $this->register_plugin_setting('prompt_simple', 'textarea', 'validate_reading_level_prompt');
        $this->register_plugin_setting('prompt_standard', 'textarea', 'validate_reading_level_prompt');
        $this->register_plugin_setting('prompt_detailed', 'textarea', 'validate_reading_level_prompt');
        $this->register_plugin_setting('prompt_expert', 'textarea', 'validate_reading_level_prompt');
        $this->register_plugin_setting('max_selection_length', 'text', 'validate_max_selection_length');
        $this->register_plugin_setting('min_selection_length', 'text', 'validate_min_selection_length');
        $this->register_plugin_setting('max_words', 'text', 'validate_max_words');
        $this->register_plugin_setting('min_words', 'text', 'validate_min_words');
        $this->register_plugin_setting('cache_enabled', 'boolean');
        $this->register_plugin_setting('cache_duration', 'text', 'validate_cache_duration');
        $this->register_plugin_setting('rate_limit_enabled', 'boolean');
        $this->register_plugin_setting('rate_limit_logged', 'text', 'validate_rate_limit_logged');
        $this->register_plugin_setting('rate_limit_anonymous', 'text', 'validate_rate_limit_anonymous');
        $this->register_plugin_setting('mobile_selection_delay', 'text', 'validate_mobile_selection_delay');
        $this->register_plugin_setting('included_selectors', 'textarea');
        $this->register_plugin_setting('excluded_selectors', 'textarea');
        $this->register_plugin_setting('tooltip_bg_color', 'color');
        $this->register_plugin_setting('tooltip_text_color', 'color');
        $this->register_plugin_setting('button_enabled_color', 'color');
        $this->register_plugin_setting('button_disabled_color', 'color');
        $this->register_plugin_setting('button_text_color', 'color');
        $this->register_plugin_setting('toggle_position', 'text', 'validate_toggle_position');
        $this->register_plugin_setting('show_disclaimer', 'boolean');
        $this->register_plugin_setting('show_provider', 'boolean');
        $this->register_plugin_setting('show_reading_level_slider', 'boolean');
        $this->register_plugin_setting('tooltip_footer_color', 'color');
        $this->register_plugin_setting('slider_track_color', 'color');
        $this->register_plugin_setting('slider_thumb_color', 'color');
        $this->register_plugin_setting('debug_mode', 'boolean');
        $this->register_plugin_setting('mock_mode', 'boolean');
        $this->register_plugin_setting('blocked_words', 'text', 'sanitize_blocked_words');
        $this->register_plugin_setting('blocked_words_case_sensitive', 'boolean');
        $this->register_plugin_setting('blocked_words_whole_word', 'boolean');
        $this->register_plugin_setting('output_seo_metadata', 'boolean');
        $this->register_plugin_setting('enable_cron', 'boolean');
        $this->register_plugin_setting('stop_word_filtering_enabled', 'boolean');
        
        // Term extraction settings
        $this->register_plugin_setting('term_extraction_prompt', 'textarea', 'validate_term_extraction_prompt');
        $this->register_plugin_setting('term_extraction_min_terms', 'text', 'validate_term_extraction_min_terms');
        
        // Auto-scan settings
        $this->register_plugin_setting('auto_scan_posts', 'boolean');
        $this->register_plugin_setting('auto_scan_pages', 'boolean');
        
        $this->register_plugin_setting('term_extraction_max_terms', 'text', 'validate_term_extraction_max_terms');
        
        
        // Debug logging settings
        $this->register_plugin_setting('debug_enabled', 'boolean');
        
        // Register debug section settings dynamically
        if (class_exists('ExplainerPlugin_Debug_Logger')) {
            $sections = ExplainerPlugin_Debug_Logger::get_all_sections();
            foreach ($sections as $section => $config) {
                $this->register_plugin_setting('debug_section_' . $section, 'boolean');
            }
        }
        
        // Add settings sections
        add_settings_section(
            'explainer_basic_settings',
            __('Basic Settings', 'ai-explainer'),
            array($this, 'basic_settings_callback'),
            'explainer_settings'
        );
        
        add_settings_section(
            'explainer_advanced_settings',
            __('Advanced Settings', 'ai-explainer'),
            array($this, 'advanced_settings_callback'),
            'explainer_settings'
        );
        
        add_settings_section(
            'explainer_debug_settings',
            __('Debug Logging', 'ai-explainer'),
            array($this, 'debug_settings_callback'),
            'explainer_settings'
        );
        
        // Add settings fields
        add_settings_field(
            'explainer_enabled',
            __('Enable Plugin', 'ai-explainer'),
            array($this, 'enabled_field_callback'),
            'explainer_settings',
            'explainer_basic_settings'
        );
        
        add_settings_field(
            'explainer_language',
            __('Language', 'ai-explainer'),
            array($this, 'language_field_callback'),
            'explainer_settings',
            'explainer_basic_settings'
        );
        
        
        add_settings_field(
            'explainer_api_model',
            __('AI Model', 'ai-explainer'),
            array($this, 'api_model_field_callback'),
            'explainer_settings',
            'explainer_basic_settings'
        );
        
        add_settings_field(
            'explainer_custom_prompt',
            __('Custom Prompt Template', 'ai-explainer'),
            array($this, 'custom_prompt_field_callback'),
            'explainer_settings',
            'explainer_basic_settings'
        );
        
        add_settings_field(
            'explainer_cache_enabled',
            __('Enable Cache', 'ai-explainer'),
            array($this, 'cache_enabled_field_callback'),
            'explainer_settings',
            'explainer_advanced_settings'
        );
        
        add_settings_field(
            'explainer_cache_duration',
            __('Cache Duration (hours)', 'ai-explainer'),
            array($this, 'cache_duration_field_callback'),
            'explainer_settings',
            'explainer_advanced_settings'
        );
        
        add_settings_field(
            'explainer_rate_limit_enabled',
            __('Enable Rate Limiting', 'ai-explainer'),
            array($this, 'rate_limit_enabled_field_callback'),
            'explainer_settings',
            'explainer_advanced_settings'
        );
        
        add_settings_field(
            'explainer_rate_limit_logged',
            __('Rate Limit (logged in users)', 'ai-explainer'),
            array($this, 'rate_limit_logged_field_callback'),
            'explainer_settings',
            'explainer_advanced_settings'
        );
        
        add_settings_field(
            'explainer_rate_limit_anonymous',
            __('Rate Limit (anonymous users)', 'ai-explainer'),
            array($this, 'rate_limit_anonymous_field_callback'),
            'explainer_settings',
            'explainer_advanced_settings'
        );
        
        // Debug logging fields
        add_settings_field(
            'explainer_debug_enabled',
            __('Enable Debug Logging', 'ai-explainer'),
            array($this, 'debug_enabled_field_callback'),
            'explainer_settings',
            'explainer_debug_settings'
        );
        
        add_settings_field(
            'explainer_debug_sections',
            __('Debug Sections', 'ai-explainer'),
            array($this, 'debug_sections_field_callback'),
            'explainer_settings',
            'explainer_debug_settings'
        );
        
        // Handle Ajax requests
        add_action('wp_ajax_explainer_test_api_key', array($this, 'test_api_key'));
        add_action('wp_ajax_explainer_reset_settings', array($this, 'reset_settings'));
        add_action('wp_ajax_explainer_view_debug_logs', array($this, 'view_debug_logs'));
        add_action('wp_ajax_explainer_delete_debug_logs', array($this, 'delete_debug_logs'));
        add_action('wp_ajax_explainer_get_popular_selections', array($this, 'get_popular_selections'));
        add_action('wp_ajax_explainer_search_selections', array($this, 'search_selections'));
        add_action('wp_ajax_explainer_delete_api_key', array($this, 'delete_api_key'));
        add_action('wp_ajax_explainer_clear_debug_log', array($this, 'clear_debug_log'));
        add_action('wp_ajax_explainer_clear_all_selections', array($this, 'clear_all_selections'));
        add_action('wp_ajax_explainer_get_selection_explanation', array($this, 'get_selection_explanation'));
        add_action('wp_ajax_explainer_save_selection_explanation', array($this, 'save_selection_explanation'));
        add_action('wp_ajax_explainer_delete_selection', array($this, 'delete_selection'));
        add_action('wp_ajax_explainer_get_debug_stats', array($this, 'get_debug_stats'));
        add_action('wp_ajax_explainer_download_debug_logs', array($this, 'download_debug_logs'));
        add_action('wp_ajax_explainer_log_js_error', array($this, 'log_js_error'));
        add_action('wp_ajax_nopriv_explainer_log_js_error', array($this, 'log_js_error'));
        
        // Job Queue Monitoring AJAX handlers
        add_action('wp_ajax_explainer_view_job_queue_logs', array($this, 'view_job_queue_logs'));
        add_action('wp_ajax_explainer_get_job_queue_status', array($this, 'get_job_queue_status'));
        add_action('wp_ajax_explainer_get_job_details', array($this, 'get_job_details'));
        add_action('wp_ajax_explainer_force_process_queue', array($this, 'force_process_queue'));
        
        // AI Terms Modal AJAX handlers (moved to constructor for proper initialization)
        add_action('wp_ajax_explainer_load_ai_terms', array($this, 'handle_load_ai_terms'));
        add_action('wp_ajax_explainer_update_term_status', array($this, 'handle_update_term_status'));
        add_action('wp_ajax_explainer_delete_term', array($this, 'handle_delete_term'));
        add_action('wp_ajax_explainer_bulk_action_terms', array($this, 'handle_bulk_action_terms'));
        add_action('wp_ajax_explainer_get_term_explanations', array($this, 'handle_get_term_explanations'));
        add_action('wp_ajax_explainer_save_term_explanations', array($this, 'handle_save_term_explanations'));
        add_action('wp_ajax_explainer_create_term', array($this, 'handle_create_term'));

        // Setup mode AJAX handlers
        add_action('wp_ajax_explainer_enable_setup_mode', array($this, 'handle_enable_setup_mode'));
        add_action('wp_ajax_explainer_disable_setup_mode', array($this, 'handle_disable_setup_mode'));
        add_action('wp_ajax_explainer_save_detected_selector', array($this, 'handle_save_detected_selector'));

        // Real-time diagnostics AJAX handlers
    }

    /**
     * Add Account submenu with higher priority to position it at the bottom
     */
    public function add_account_submenu() {
        // Add Account submenu for Freemius license management
        if (function_exists('wpaie_freemius')) {
            add_submenu_page(
                'wp-ai-explainer-admin',
                __('Account', 'ai-explainer'),
                __('Account', 'ai-explainer'),
                'manage_options',
                'wp-ai-explainer-admin-account',
                array($this, 'account_page')
            );
        }
    }

    /**
     * Add Purchase License submenu (only for pro users without valid license)
     */
    public function add_purchase_license_submenu() {
        // Only show if Freemius is available and user doesn't have valid license
        if (!function_exists('wpaie_freemius')) {
            return;
        }

        $fs = wpaie_freemius();

        // Only show if user doesn't have valid license
        if ($fs->has_active_valid_license()) {
            return;
        }

        // Only show if pro files exist
        if (!file_exists(EXPLAINER_PLUGIN_PATH . 'includes/pro/providers/class-claude-provider.php')) {
            return;
        }

        // Add pricing page as a proper submenu item
        add_submenu_page(
            'wp-ai-explainer-admin',
            __('Purchase License', 'ai-explainer'),
            __('Purchase License', 'ai-explainer'),
            'manage_options',
            'ai-explainer-pricing',
            array($this, 'render_pricing_page')
        );

        // Make sure pricing page shows as child of AI Explainer menu
        add_filter('parent_file', array($this, 'set_pricing_parent_file'));
        add_filter('submenu_file', array($this, 'set_pricing_submenu_file'));
    }

    /**
     * Set parent file for pricing page to keep AI Explainer menu open
     */
    public function set_pricing_parent_file($parent_file) {
        global $plugin_page;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking current page
        if (isset($_GET['page']) && $_GET['page'] === 'ai-explainer-pricing') {
            return 'wp-ai-explainer-admin';
        }

        return $parent_file;
    }

    /**
     * Set submenu file for pricing page to highlight menu item
     */
    public function set_pricing_submenu_file($submenu_file) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking current page
        if (isset($_GET['page']) && $_GET['page'] === 'ai-explainer-pricing') {
            return 'ai-explainer-pricing';
        }

        return $submenu_file;
    }

    /**
     * Render pricing page - let Freemius handle it
     */
    public function render_pricing_page() {
        // Call Freemius pricing page renderer
        if (function_exists('wpaie_freemius')) {
            $fs = wpaie_freemius();

            // Set billing cycle parameter if not set
            if (!isset($_GET['billing_cycle'])) {
                $_GET['billing_cycle'] = 'annual';
            }

            // Call Freemius's internal pricing page renderer
            $fs->_pricing_page_render();
        }
    }


    /**
     * Manually register Freemius license activation AJAX handler
     */
    private function register_freemius_license_activation() {
        $fs = wpaie_freemius();

        // Manually register the AJAX handler that Freemius would normally register
        $ajax_action = $fs->get_ajax_action('activate_license');
        add_action('wp_ajax_' . $ajax_action, array($fs, '_activate_license_ajax_action'));
    }

    /**
     * Customize Freemius messages and content
     */
    private function customize_freemius_messages() {
        // Freemius customization temporarily disabled
        // The JavaScript was causing page display issues
    }

    /**
     * Show Pro download banner for free users with active license
     */
    public function show_pro_download_banner() {
        // Only show on plugin admin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'ai-explainer') === false) {
            return;
        }

        // Check if Freemius is loaded
        if (!function_exists('wpaie_freemius')) {
            return;
        }

        // Only show if user has free version installed but has active license
        if (!wpaie_freemius()->is_premium() && wpaie_freemius()->can_use_premium_code()) {
            $download_url = wpaie_freemius()->_get_latest_download_local_url();
            ?>
            <div class="notice notice-info is-dismissible">
                <h3><?php esc_html_e('Download AI Explainer Pro', 'ai-explainer'); ?></h3>
                <p><?php esc_html_e('You have an active license but the free version is currently installed. Download the Pro version to access all features.', 'ai-explainer'); ?></p>

                <h4><?php esc_html_e('Installation instructions:', 'ai-explainer'); ?></h4>
                <ol>
                    <li><?php esc_html_e('Click the download button below to get the Pro version', 'ai-explainer'); ?></li>
                    <li><?php esc_html_e('Go to Plugins → Add New → Upload Plugin', 'ai-explainer'); ?></li>
                    <li><?php esc_html_e('Upload the downloaded ZIP file', 'ai-explainer'); ?></li>
                    <li><?php esc_html_e('Click "Replace current with uploaded" when prompted', 'ai-explainer'); ?></li>
                    <li><?php esc_html_e('Activate the plugin', 'ai-explainer'); ?></li>
                </ol>

                <p>
                    <a href="<?php echo esc_url($download_url); ?>" class="button button-primary">
                        <?php esc_html_e('Download Pro version', 'ai-explainer'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Settings page callback
     */
    public function settings_page() {
        // Debug logging for admin page access
        $this->debug_admin_options_access();
        
        // Load the new modular settings renderer
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/admin/settings/class-settings-renderer.php';
        $settings_renderer = new ExplainerPlugin_Settings_Renderer();
        
        // Enqueue assets first
        $settings_renderer->enqueue_assets();
        
        // Pass renderer to template
        include EXPLAINER_PLUGIN_PATH . 'templates/admin-settings.php';
        
        // Include blog creator modal if user can publish posts
        if (current_user_can('publish_posts')) {
            include EXPLAINER_PLUGIN_PATH . 'templates/admin-blog-creator-modal.php';
        }
    }
    
    /**
     * Basic settings section callback
     */
    public function basic_settings_callback() {
        echo '<p>' . esc_html__('Configure the basic settings for the AI Explainer plugin.', 'ai-explainer') . '</p>';
    }
    
    /**
     * Advanced settings section callback
     */
    public function advanced_settings_callback() {
        echo '<p>' . esc_html__('Advanced configuration options for performance and rate limiting.', 'ai-explainer') . '</p>';
    }
    
    /**
     * Debug settings section callback
     */
    public function debug_settings_callback() {
        echo '<p>' . esc_html__('Control debug logging output by section. All logging goes to the plugin debug.log file.', 'ai-explainer') . '</p>';
        
        // Show log file info
        if (class_exists('ExplainerPlugin_Debug_Logger')) {
            $log_file = ExplainerPlugin_Debug_Logger::get_log_file();
            $log_size = ExplainerPlugin_Debug_Logger::get_log_file_size();
            $log_size_mb = round($log_size / 1024 / 1024, 2);
            
            echo '<p><strong>' . esc_html__('Debug Log File:', 'ai-explainer') . '</strong> <code>' . esc_html($log_file) . '</code></p>';
            echo '<p><strong>' . esc_html__('Current Size:', 'ai-explainer') . '</strong> ' . esc_html($log_size_mb) . ' MB</p>';
            
            if ($log_size > 0) {
                echo '<p><button type="button" class="btn-base btn-destructive" onclick="clearDebugLog()">' . esc_html__('Clear Debug Log', 'ai-explainer') . '</button></p>';
                echo '<script>
                    function clearDebugLog() {
                        if (confirm("' . esc_js(__('Are you sure you want to clear the debug log?', 'ai-explainer')) . '")) {
                            fetch(ajaxurl, {
                                method: "POST",
                                body: new FormData(Object.assign(document.createElement("form"), {
                                    innerHTML: `<input name="action" value="explainer_clear_debug_log">
                                               <input name="nonce" value="' . esc_attr(wp_create_nonce('explainer_clear_debug_log')) . '">`
                                }))
                            }).then(() => location.reload());
                        }
                    }
                </script>';
            }
        }
    }
    
    /**
     * Debug enabled field callback
     */
    public function debug_enabled_field_callback() {
        $enabled = ExplainerPlugin_Debug_Logger::is_enabled();
        ?>
        <label>
            <input type="checkbox" name="explainer_debug_enabled" value="1" <?php checked($enabled); ?> />
            <?php esc_html_e('Enable debug logging to plugin debug.log file', 'ai-explainer'); ?>
        </label>
        <?php
    }
    
    /**
     * Debug sections field callback
     */
    public function debug_sections_field_callback() {
        if (!class_exists('ExplainerPlugin_Debug_Logger')) {
            echo '<p>' . esc_html__('Debug logger not available.', 'ai-explainer') . '</p>';
            return;
        }
        
        $sections = ExplainerPlugin_Debug_Logger::get_all_sections();
        
        echo '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 10px 0;">';
        
        // Add "Enable All" and "Disable All" buttons
        echo '<div style="grid-column: 1 / -1; margin-bottom: 10px;">';
        echo '<button type="button" class="btn-base btn-secondary btn-sm" onclick="toggleAllDebugSections(true)">' . esc_html__('Enable All', 'ai-explainer') . '</button> ';
        echo '<button type="button" class="btn-base btn-secondary btn-sm" onclick="toggleAllDebugSections(false)">' . esc_html__('Disable All', 'ai-explainer') . '</button>';
        echo '</div>';
        
        foreach ($sections as $section => $config) {
            $field_name = 'explainer_debug_section_' . $section;
            $enabled = $config['enabled'];
            $description = $config['description'];
            
            echo '<div style="border: 1px solid #ddd; padding: 8px; border-radius: 3px;">';
            echo '<label>';
            echo '<input type="checkbox" name="' . esc_attr($field_name) . '" value="1" ' . checked($enabled, true, false) . ' class="debug-section-checkbox" /> ';
            echo '<strong>' . esc_html(str_replace('_', ' ', ucwords($section, '_'))) . '</strong>';
            echo '</label>';
            echo '<div style="font-size: 11px; color: #666; margin-top: 2px;">' . esc_html($description) . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Add JavaScript for bulk actions
        ?>
        <script>
            function toggleAllDebugSections(enable) {
                document.querySelectorAll('.debug-section-checkbox').forEach(function(checkbox) {
                    checkbox.checked = enable;
                });
            }
        </script>
        <?php
    }
    
    /**
     * Enabled field callback
     */
    public function enabled_field_callback() {
        $value = get_option('explainer_enabled', true);
        ?>
        <label>
            <input type="checkbox" name="explainer_enabled" value="1" <?php checked($value, true); ?> />
            <?php echo esc_html__('Enable the AI Explainer plugin', 'ai-explainer'); ?>
        </label>
        <?php
    }
    
    /**
     * Language field callback
     */
    public function language_field_callback() {
        $value = get_option('explainer_language', 'en_GB');
        $languages = array(
            'en_US' => __('English (United States)', 'ai-explainer'),
            'en_GB' => __('English (United Kingdom)', 'ai-explainer'),
            'es_ES' => __('Spanish (Spain)', 'ai-explainer'),
            'de_DE' => __('German (Germany)', 'ai-explainer'),
            'fr_FR' => __('French (France)', 'ai-explainer'),
            'hi_IN' => __('Hindi (India)', 'ai-explainer'),
            'zh_CN' => __('Chinese (Simplified)', 'ai-explainer')
        );
        ?>
        <select name="explainer_language" class="regular-text">
            <?php foreach ($languages as $code => $name): ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($value, $code); ?>>
                    <?php echo esc_html($name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php echo esc_html__('Select the language for the plugin interface and AI explanations.', 'ai-explainer'); ?>
        </p>
        <?php
    }
    
    
    /**
     * API model field callback
     */
    public function api_model_field_callback() {
        $value = get_option('explainer_api_model', 'gpt-3.5-turbo');
        $models = array(
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Recommended)',
            'gpt-4' => 'GPT-4 (Higher quality, more expensive)',
            'gpt-4-turbo' => 'GPT-4 Turbo (Fast and efficient)'
        );
        ?>
        <select name="explainer_api_model">
            <?php foreach ($models as $model => $label): ?>
                <option value="<?php echo esc_attr($model); ?>" <?php selected($value, $model); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
    
    /**
     * Cache enabled field callback
     */
    public function cache_enabled_field_callback() {
        $value = get_option('explainer_cache_enabled', true);
        ?>
        <label>
            <input type="checkbox" name="explainer_cache_enabled" value="1" <?php checked($value, true); ?> />
            <?php echo esc_html__('Enable caching to reduce API calls and costs', 'ai-explainer'); ?>
        </label>
        <?php
    }
    
    /**
     * Cache duration field callback
     */
    public function cache_duration_field_callback() {
        $value = get_option('explainer_cache_duration', 24);
        ?>
        <input type="number" name="explainer_cache_duration" value="<?php echo esc_attr($value); ?>" min="1" max="168" />
        <p class="description">
            <?php echo esc_html__('How long to cache explanations (1-168 hours)', 'ai-explainer'); ?>
        </p>
        <?php
    }
    
    /**
     * Rate limit enabled field callback
     */
    public function rate_limit_enabled_field_callback() {
        $value = get_option('explainer_rate_limit_enabled', true);
        ?>
        <label>
            <input type="checkbox" name="explainer_rate_limit_enabled" value="1" <?php checked($value, true); ?> />
            <?php echo esc_html__('Enable rate limiting to prevent abuse', 'ai-explainer'); ?>
        </label>
        <?php
    }
    
    /**
     * Rate limit logged field callback
     */
    public function rate_limit_logged_field_callback() {
        $value = get_option('explainer_rate_limit_logged', 100);
        ?>
        <input type="number" name="explainer_rate_limit_logged" value="<?php echo esc_attr($value); ?>" min="1" max="100" />
        <p class="description">
            <?php echo esc_html__('Requests per minute for logged in users', 'ai-explainer'); ?>
        </p>
        <?php
    }
    
    /**
     * Rate limit anonymous field callback
     */
    public function rate_limit_anonymous_field_callback() {
        $value = get_option('explainer_rate_limit_anonymous', 50);
        ?>
        <input type="number" name="explainer_rate_limit_anonymous" value="<?php echo esc_attr($value); ?>" min="1" max="50" />
        <p class="description">
            <?php echo esc_html__('Requests per minute for anonymous users', 'ai-explainer'); ?>
        </p>
        <?php
    }
    
    /**
     * Test API key via Ajax
     */
    public function test_api_key() {
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        // Get provider and potential new API key from request
        $provider = sanitize_text_field( wp_unslash( $_POST['provider'] ?? 'openai' ) );
        $new_api_key = isset($_POST['api_key']) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
        
        // Determine which API key to test
        $api_key = '';
        if (!empty($new_api_key)) {
            // Test the new key from the input field
            $api_key = $new_api_key;
        } else {
            // Fall back to stored API key
            $api_key = $this->api_proxy->get_decrypted_api_key_for_provider($provider);
        }
        
        if (empty($api_key)) {
            $provider_names = array(
                'openai' => 'OpenAI',
                'claude' => 'Claude',
                'openrouter' => 'OpenRouter',
                'gemini' => 'Google Gemini'
            );
            $provider_name = $provider_names[$provider] ?? 'AI Provider';
            /* translators: %s: AI provider name (OpenAI, Claude, etc.) */
            wp_send_json_error(array('message' => sprintf(__('Please enter a %s API key to test it.', 'ai-explainer'), $provider_name)));
        }
        
        $result = $this->api_proxy->test_api_key($api_key);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * View debug logs via Ajax
     */
    public function view_debug_logs() {
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        // Get filter parameters
        $limit = isset($_POST['limit']) ? absint(wp_unslash($_POST['limit'])) : 100;
        $level = isset($_POST['level']) ? sanitize_text_field(wp_unslash($_POST['level'])) : '';
        $component = isset($_POST['component']) ? sanitize_text_field(wp_unslash($_POST['component'])) : '';
        
        // Use the new debug logger to get recent logs
        $log_lines = ExplainerPlugin_Debug_Logger::get_recent_logs($limit * 2); // Get more to allow for filtering
        
        $logs = array();
        $components = array();
        
        foreach ($log_lines as $line) {
            if (empty($line)) continue;
            
            // Parse log line format: [timestamp] [level] [component] message
            if (preg_match('/^\[([^\]]+)\]\s+\[([^\]]+)\]\s+\[([^\]]+)\]\s+(.+)$/', $line, $matches)) {
                $log_timestamp = $matches[1];
                $log_level = strtolower($matches[2]);
                $log_component = $matches[3];
                $log_message = $matches[4];
                
                // Extract context if present
                $context = array();
                if (strpos($log_message, ' | Context: ') !== false) {
                    $parts = explode(' | Context: ', $log_message, 2);
                    $log_message = $parts[0];
                    if (isset($parts[1])) {
                        $context_part = $parts[1];
                        // Remove memory info if present
                        $context_part = preg_replace('/ \| Memory: [^|]+$/', '', $context_part);
                        $context = json_decode($context_part, true) ?: array();
                    }
                }
                
                // Apply filters
                if (!empty($level) && $log_level !== $level) continue;
                if (!empty($component) && stripos($log_component, $component) === false) continue;
                
                $logs[] = array(
                    'timestamp' => $log_timestamp,
                    'level' => $log_level,
                    'component' => $log_component,
                    'message' => $log_message,
                    'context' => $context
                );
                
                // Collect unique components
                if (!in_array($log_component, $components)) {
                    $components[] = $log_component;
                }
            }
        }
        
        // Limit results and sort components
        $logs = array_slice($logs, 0, $limit);
        sort($components);
        
        if (empty($logs)) {
            wp_send_json_success(array(
                'logs' => array(), 
                'components' => $components,
                'message' => __('No debug logs found.', 'ai-explainer')
            ));
        }
        
        wp_send_json_success(array(
            'logs' => $logs,
            'components' => $components,
            'total_count' => count($logs)
        ));
    }
    
    /**
     * Delete debug logs via Ajax
     */
    public function delete_debug_logs() {
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        // Use the new debug logger class to clear logs
        ExplainerPlugin_Debug_Logger::clear();
        
        wp_send_json_success(array('message' => __('Debug logs deleted successfully.', 'ai-explainer')));
    }
    
    /**
     * Clear debug log via AJAX
     */
    public function clear_debug_log() {
        check_ajax_referer('explainer_clear_debug_log', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        // Clear the debug log
        if (class_exists('ExplainerPlugin_Debug_Logger')) {
            ExplainerPlugin_Debug_Logger::clear();
            wp_send_json_success(array('message' => __('Debug log cleared successfully.', 'ai-explainer')));
        } else {
            wp_send_json_error(array('message' => __('Debug logger not available.', 'ai-explainer')));
        }
    }
    
    /**
     * Get debug statistics via Ajax
     */
    public function get_debug_stats() {
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        // Use the new debug logger to get stats
        $log_lines = ExplainerPlugin_Debug_Logger::get_recent_logs(1000);
        
        $stats = array(
            'total_logs' => 0,
            'by_level' => array(),
            'recent_activity' => array()
        );
        
        $level_counts = array();
        $component_counts = array();
        $recent_activity = array();
        
        foreach ($log_lines as $line) {
            if (empty($line)) continue;
            
            // Parse log line format: [timestamp] [level] [component] message
            if (preg_match('/^\[([^\]]+)\]\s+\[([^\]]+)\]\s+\[([^\]]+)\]\s+(.+)$/', $line, $matches)) {
                $stats['total_logs']++;
                
                $log_timestamp = $matches[1];
                $log_level = strtolower($matches[2]);
                $log_component = $matches[3];
                $log_message = $matches[4];
                
                // Count by level
                if (!isset($level_counts[$log_level])) {
                    $level_counts[$log_level] = 0;
                }
                $level_counts[$log_level]++;
                
                // Collect recent activity (last 50 entries)
                if (count($recent_activity) < 50) {
                    $recent_activity[] = sprintf('%s [%s]: %s', esc_html($log_timestamp), strtoupper($log_component), substr($log_message, 0, 80) . (strlen($log_message) > 80 ? '...' : ''));
                }
            }
        }
        
        $stats['by_level'] = $level_counts;
        $stats['recent_activity'] = array_reverse($recent_activity); // Show most recent first
        
        wp_send_json_success($stats);
    }
    
    /**
     * Download debug logs via Ajax
     */
    public function download_debug_logs() {
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied', 'ai-explainer'));
        }

        // Use the plugin-specific debug.log file path
        $debug_log_path = EXPLAINER_PLUGIN_PATH . 'debug.log';

        // Check if debug log file exists
        if (!file_exists($debug_log_path)) {
            wp_die(esc_html__('Debug log file not found. Enable debug mode and interact with the plugin to generate logs.', 'ai-explainer'));
        }

        // Get file content
        $log_content = file_get_contents($debug_log_path);

        if ($log_content === false) {
            wp_die(esc_html__('Could not read debug log file', 'ai-explainer'));
        }
        
        // If file is empty, provide helpful message
        if (empty($log_content)) {
            $log_content = "# AI Explainer Debug Log\n";
            $log_content .= "# Generated: " . current_time('mysql') . "\n";
            $log_content .= "# No debug entries found. Enable debug mode and interact with the plugin to generate logs.\n";
        }
        
        // Set headers for download
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="wp-ai-explainer-debug-' . wp_date('Y-m-d-H-i-s') . '.log"');
        header('Content-Length: ' . strlen($log_content));
        
        echo esc_html($log_content);
        exit;
    }
    
    /**
     * Log JavaScript errors via Ajax
     */
    public function log_js_error() {
        check_ajax_referer('explainer_nonce', 'nonce');

        // Allow both logged in and logged out users to report JS errors

        $error_data = isset($_POST['error_data']) ? map_deep(wp_unslash($_POST['error_data']), 'sanitize_text_field') : array();

        if (empty($error_data)) {
            wp_send_json_error(array('message' => __('No error data provided', 'ai-explainer')));
        }

        // Sanitize error data
        $sanitized_error = array(
            'message' => isset($error_data['message']) ? sanitize_text_field($error_data['message']) : '',
            'component' => isset($error_data['component']) ? sanitize_text_field($error_data['component']) : 'JavaScript',
            'url' => isset($error_data['url']) ? esc_url_raw($error_data['url']) : '',
            'userAgent' => isset($error_data['userAgent']) ? sanitize_text_field($error_data['userAgent']) : '',
            'timestamp' => isset($error_data['timestamp']) ? sanitize_text_field($error_data['timestamp']) : '',
            'context' => isset($error_data['context']) ? $error_data['context'] : array()
        );
        
        // Use logger to record the JavaScript error
        $logger = ExplainerPlugin_Logger::get_instance();
        $logger->error(
            'JavaScript Error: ' . $sanitized_error['message'],
            $sanitized_error,
            'JavaScript'
        );
        
        wp_send_json_success();
    }
    
    /**
     * View job queue specific logs via AJAX
     */
    public function view_job_queue_logs() {
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        $limit = isset($_POST['limit']) ? absint(wp_unslash($_POST['limit'])) : 50;
        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        
        $logger = ExplainerPlugin_Logger::get_instance();
        
        // Get logs filtered for job queue components
        $queue_components = array(
            'Blog_Creator',
            'Blog_Queue_Manager', 
            'Blog_Background_Processor',
            'Content_Generator'
        );
        
        $all_logs = $logger->get_logs($limit * 2); // Get more to filter
        $filtered_logs = array();
        
        foreach ($all_logs as $log) {
            // Filter by component
            if (in_array($log['component'], $queue_components)) {
                // If specific job ID requested, filter by that
                if ($job_id) {
                    $context_string = is_string($log['context']) ? $log['context'] : wp_json_encode($log['context']);
                    if (strpos($context_string, $job_id) !== false) {
                        $filtered_logs[] = $log;
                    }
                } else {
                    $filtered_logs[] = $log;
                }
            }
            
            if (count($filtered_logs) >= $limit) {
                break;
            }
        }
        
        // Add visual indicators and formatting
        foreach ($filtered_logs as &$log) {
            $log['formatted_time'] = human_time_diff(strtotime($log['timestamp']), current_time('timestamp')) . ' ago';
            $log['level_icon'] = $this->get_log_level_icon($log['level']);
            $log['level_class'] = 'log-level-' . strtolower($log['level']);
            
            // Parse context for better display
            if (is_string($log['context'])) {
                $context = json_decode($log['context'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $log['context'] = $context;
                }
            }
            
            // Extract job ID if present
            if (is_array($log['context']) && isset($log['context']['job_id'])) {
                $log['job_id'] = $log['context']['job_id'];
            }
        }
        
        wp_send_json_success(array(
            'logs' => $filtered_logs,
            'total_count' => count($filtered_logs),
            'job_id_filter' => $job_id,
            'components' => $queue_components
        ));
    }
    
    /**
     * Get job queue status dashboard data
     */
    public function get_job_queue_status() {
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        // Use new job queue system (simplified architecture)
        $queue_manager = ExplainerPlugin_Job_Queue_Manager::get_instance();
        
        // Get queue statistics
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';

        // Check cache first
        $cache_key = 'explainer_job_queue_stats';
        $stats = wp_cache_get($cache_key);

        if (false === $stats) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query, caching handled manually above
            $stats = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT
                        COUNT(*) as total_jobs,
                        SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) as processing,
                        SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) as failed,
                        SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) as cancelled
                    FROM {$table_name}",
                    'pending',
                    'processing',
                    'completed',
                    'failed',
                    'cancelled'
                ),
                ARRAY_A
            );
            wp_cache_set($cache_key, $stats, '', 60); // Cache for 1 minute
        }

        // Get recent jobs (last 10)
        $cache_key_jobs = 'explainer_recent_jobs';
        $recent_jobs = wp_cache_get($cache_key_jobs);

        if (false === $recent_jobs) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query, caching handled manually above
            $recent_jobs = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT queue_id as job_id, status, created_at, started_at, completed_at,
                           error_message, attempts as retry_count, created_by
                    FROM {$table_name}
                    ORDER BY created_at DESC
                    LIMIT %d",
                    10
                ),
                ARRAY_A
            );
            wp_cache_set($cache_key_jobs, $recent_jobs, '', 60); // Cache for 1 minute
        }
        
        // Add formatted times and user info
        foreach ($recent_jobs as &$job) {
            $job['created_ago'] = human_time_diff(strtotime($job['created_at']), current_time('timestamp')) . ' ago';
            $job['status_icon'] = $this->get_job_status_icon($job['status']);
            $job['status_class'] = 'job-status-' . $job['status'];
            
            if ($job['created_by']) {
                $user = get_user_by('id', $job['created_by']);
                $job['user_name'] = $user ? $user->display_name : 'Unknown User';
            }
            
            // Calculate duration if completed
            if ($job['completed_at'] && $job['started_at']) {
                $duration = strtotime($job['completed_at']) - strtotime($job['started_at']);
                $job['duration'] = $duration . 's';
            }
        }
        
        // Check if background processing is running
        $is_processing = $background_processor->is_processing();
        $next_scheduled = wp_next_scheduled('explainer_process_blog_queue');
        
        // Get system health indicators
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Checking table existence, not cached
        $health = array(
            'wp_cron_enabled' => !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON,
            'debug_mode_enabled' => get_option('explainer_debug_mode', false),
            'queue_table_exists' => $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name,
            'currently_processing' => $is_processing,
            'next_scheduled' => $next_scheduled ? wp_date('Y-m-d H:i:s', $next_scheduled) : null
        );
        
        wp_send_json_success(array(
            'stats' => $stats,
            'recent_jobs' => $recent_jobs,
            'health' => $health,
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Get detailed information about a specific job
     */
    public function get_job_details() {
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }

        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';

        if (empty($job_id)) {
            wp_send_json_error(array('message' => __('Job ID is required', 'ai-explainer')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';
        
        // Get job details
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT id as job_id, job_type, status, data, priority, created_by, created_at, updated_at,
                    started_at, completed_at, error_message, retry_count
             FROM {$table_name} WHERE id = %s",
            str_replace('jq_', '', $job_id)
        ), ARRAY_A);
        
        if (!$job) {
            wp_send_json_error(array('message' => __('Job not found', 'ai-explainer')));
        }
        
        // Decode JSON fields and handle the new data structure
        $job['options'] = json_decode($job['data'], true);
        $job['result_data'] = null;
        
        // Get result data from job meta if available
        $meta_table = $wpdb->prefix . 'explainer_job_meta';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $result_meta = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM $meta_table WHERE queue_id = %d AND meta_key = 'result'",
            str_replace('jq_', '', $job_id)
        ));
        
        if ($result_meta) {
            $job['result_data'] = maybe_unserialize($result_meta);
        }
        
        // Add formatted fields
        $job['created_ago'] = human_time_diff(strtotime($job['created_at']), current_time('timestamp')) . ' ago';
        $job['status_icon'] = $this->get_job_status_icon($job['status']);
        $job['status_class'] = 'job-status-' . $job['status'];
        
        // Calculate durations
        if ($job['started_at']) {
            $job['queue_time'] = strtotime($job['started_at']) - strtotime($job['created_at']);
        }
        
        if ($job['completed_at'] && $job['started_at']) {
            $job['processing_time'] = strtotime($job['completed_at']) - strtotime($job['started_at']);
        }
        
        // Get user info
        if ($job['created_by']) {
            $user = get_user_by('id', $job['created_by']);
            $job['user_info'] = $user ? array(
                'display_name' => $user->display_name,
                'user_email' => $user->user_email,
                'user_login' => $user->user_login
            ) : null;
        }
        
        // Get related logs
        $logger = ExplainerPlugin_Logger::get_instance();
        $all_logs = $logger->get_logs(200);
        $job_logs = array();
        
        foreach ($all_logs as $log) {
            $context_string = is_string($log['context']) ? $log['context'] : wp_json_encode($log['context']);
            if (strpos($context_string, $job_id) !== false) {
                $log['formatted_time'] = human_time_diff(strtotime($log['timestamp']), current_time('timestamp')) . ' ago';
                $log['level_icon'] = $this->get_log_level_icon($log['level']);
                $job_logs[] = $log;
            }
        }
        
        wp_send_json_success(array(
            'job' => $job,
            'logs' => array_slice($job_logs, 0, 20) // Limit to 20 most recent logs
        ));
    }
    
    /**
     * Force process the job queue
     */
    public function force_process_queue() {
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        // Log the manual trigger
        explainer_log_info('Admin manually triggered job queue processing (simplified architecture)', array(
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql')
        ), 'Admin');
        
        try {
            // Use simplified architecture - process via Job Queue Manager
            $job_manager = ExplainerPlugin_Job_Queue_Manager::get_instance();
            $result = $job_manager->process_queue('blog_creation', 1); // Process one job
            
            wp_send_json_success(array(
                'message' => __('Queue processing triggered successfully', 'ai-explainer'),
                'result' => $result
            ));
        } catch (Exception $e) {
            explainer_log_error('Failed to manually trigger queue processing', array(
                'error' => $e->getMessage(),
                'user_id' => get_current_user_id()
            ), 'Admin');

            wp_send_json_error(array(
                'message' => sprintf(
                    /* translators: %s: Error message */
                    __('Failed to process queue: %s', 'ai-explainer'),
                    $e->getMessage()
                )
            ));
        }
    }
    
    /**
     * Get icon for log level
     */
    private function get_log_level_icon($level) {
        return $this->utilities->get_log_level_icon($level);
    }
    
    /**
     * Get icon for job status
     */
    private function get_job_status_icon($status) {
        return $this->utilities->get_job_status_icon($status);
    }
    
    /**
     * Enqueue admin styles
     */
    public function enqueue_styles($hook) {
        $this->asset_manager->enqueue_styles($hook);
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        $this->asset_manager->enqueue_scripts($hook);
        
        // Enqueue modal scripts on post edit pages
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            wp_enqueue_script(
                'explainer-ai-terms-modal',
                EXPLAINER_PLUGIN_URL . 'assets/js/ai-terms-modal.js',
                array('jquery'),
                EXPLAINER_PLUGIN_VERSION,
                true
            );
            
            wp_enqueue_script(
                'explainer-ai-terms-integration',
                EXPLAINER_PLUGIN_URL . 'assets/js/ai-terms-integration.js',
                array('explainer-ai-terms-modal'),
                EXPLAINER_PLUGIN_VERSION,
                true
            );
            
            // Localize AJAX data for modal
            wp_localize_script(
                'explainer-ai-terms-modal',
                'explainerAjax',
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('explainer_nonce'),
                    'debug' => defined('WP_DEBUG') && WP_DEBUG
                )
            );
            
            // Add modal template to footer
            add_action('admin_footer', array($this, 'output_modal_template'));
        }
    }
    
    /**
     * Get language from WordPress locale
     * 
     * @param string $locale WordPress locale (e.g., en_GB, fr_FR)
     * @return string Language name
     */
    private function get_language_from_locale($locale) {
        return $this->utilities->get_language_from_locale($locale);
    }
    
    /**
     * Reset settings to defaults
     */
    public function reset_settings() {
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        // Get all default options
        $defaults = array(
            'explainer_enabled' => true,
            'explainer_api_provider' => 'openai',
            'explainer_claude_api_key' => '',
            'explainer_api_model' => 'gpt-3.5-turbo',
            'explainer_max_selection_length' => 200,
            'explainer_min_selection_length' => 3,
            'explainer_max_words' => 30,
            'explainer_min_words' => 1,
            'explainer_cache_enabled' => true,
            'explainer_cache_duration' => 24,
            'explainer_rate_limit_enabled' => true,
            'explainer_rate_limit_logged' => 100,
            'explainer_rate_limit_anonymous' => 50,
            'explainer_included_selectors' => ExplainerPlugin_Config::get_setting('included_selectors'),
            'explainer_excluded_selectors' => ExplainerPlugin_Config::get_setting('excluded_selectors'),
            'explainer_tooltip_bg_color' => '#333333',
            'explainer_tooltip_text_color' => '#ffffff',
            'explainer_button_enabled_color' => '#8b5cf6',
            'explainer_button_disabled_color' => '#94a3b8',
            'explainer_button_text_color' => '#ffffff',
            'explainer_toggle_position' => 'bottom-right',
            'explainer_show_disclaimer' => true,
            'explainer_show_provider' => true,
            'explainer_show_reading_level_slider' => true,
            'explainer_tooltip_footer_color' => '#ffffff',
            'explainer_slider_track_color' => 'rgba(255, 255, 255, 0.2)',
            'explainer_slider_thumb_color' => '#8b5cf6',
            'explainer_debug_mode' => false,
            'explainer_mock_mode' => false,
            'explainer_custom_prompt' => ExplainerPlugin_Config::get_default_custom_prompt(),
        );
        
        // Update all options to defaults
        foreach ($defaults as $option => $value) {
            update_option($option, $value);
        }
        
        wp_send_json_success(array('message' => __('Settings reset to defaults successfully', 'ai-explainer')));
    }
    
    
    /**
     * Generic API key processing method - eliminates code duplication
     * Handles encryption and preservation of existing keys for all providers
     * 
     * @param string $value New API key value
     * @param string $old_value Existing API key value
     * @return string Processed API key value
     */
    private function process_provider_api_key_save($value, $old_value) {
        if (!empty($value)) {
            // Encrypt the new API key before saving
            return $this->api_proxy->encrypt_api_key($value);
        }

        // Check if we have an existing key and the form indicates we should preserve it
        // Parse provider from current filter to get provider-specific POST key
        $current_provider = $this->parse_provider_from_current_filter();
        $provider_specific_key = $current_provider ? "explainer_{$current_provider}_api_key_has_existing" : 'explainer_api_key_has_existing';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress Settings API verifies nonces before calling sanitization callbacks
        $has_existing_indicator = isset($_POST[$provider_specific_key]) && sanitize_text_field(wp_unslash($_POST[$provider_specific_key])) === '1';

        if ($has_existing_indicator && !empty($old_value)) {
            // Preserve the existing encrypted key
            return $old_value;
        }

        // If no existing key indicator and empty value, allow the empty value to be saved
        return $value;
    }
    
    /**
     * Parse provider name from current WordPress filter hook
     * 
     * Extracts provider key from hooks like 'pre_update_option_explainer_{provider}_api_key'
     * Used to identify which AI provider is being processed during API key save operations.
     * 
     * @param string|null $filter_name Optional filter name to parse. Uses current_filter() if null.
     * @return string|null Provider key (openai, claude, gemini, openrouter) or null if not found
     */
    private function parse_provider_from_current_filter($filter_name = null) {
        // Get current filter name if not provided
        if ($filter_name === null) {
            $filter_name = current_filter();
        }
        
        // Validate input
        if (empty($filter_name) || !is_string($filter_name)) {
            return null;
        }
        
        // Pattern: pre_update_option_explainer_{provider}_api_key
        $pattern = '/^pre_update_option_explainer_([a-z]+)_api_key$/';
        
        if (preg_match($pattern, $filter_name, $matches)) {
            $extracted_provider = $matches[1];
            
            // Load known providers from config
            $providers_config = include(EXPLAINER_PLUGIN_PATH . 'includes/config/ai-providers.php');
            $known_providers = array_keys($providers_config);
            
            // Validate against known providers for security
            if (in_array($extracted_provider, $known_providers, true)) {
                return $extracted_provider;
            }
        }
        
        return null;
    }
    
    
    /**
     * Process OpenAI API key before saving
     */
    public function process_openai_api_key_save($value, $old_value) {
        // Process the API key normally using provider-specific storage
        return $this->process_provider_api_key_save($value, $old_value);
    }
    
    /**
     * Process Claude API key before saving - legacy wrapper
     * @deprecated Use process_provider_api_key_save instead
     */
    public function process_claude_api_key_save($value, $old_value) {
        return $this->process_provider_api_key_save($value, $old_value);
    }
    
    /**
     * Process OpenRouter API key before saving - legacy wrapper
     * @deprecated Use process_provider_api_key_save instead
     */
    public function process_openrouter_api_key_save($value, $old_value) {
        return $this->process_provider_api_key_save($value, $old_value);
    }
    
    /**
     * Process Gemini API key before saving - legacy wrapper
     * @deprecated Use process_provider_api_key_save instead
     */
    public function process_gemini_api_key_save($value, $old_value) {
        return $this->process_provider_api_key_save($value, $old_value);
    }
    
    /**
     * Custom prompt field callback
     */
    public function custom_prompt_field_callback() {
        $value = get_option('explainer_custom_prompt', ExplainerPlugin_Config::get_default_custom_prompt());
        ?>
        <textarea name="explainer_custom_prompt" rows="4" cols="60" class="large-text code"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php 
            // translators: {{selectedtext}} is a placeholder that will be replaced with the user's selected text
            echo esc_html__('Customize the prompt sent to the AI. Use {{selectedtext}} where you want the selected text to appear.', 'ai-explainer'); ?>
        </p>
        <p class="description">
            <strong><?php echo esc_html__('Example:', 'ai-explainer'); ?></strong> <?php 
            // translators: {{selectedtext}} is a placeholder that will be replaced with the user's selected text
            echo esc_html__('\"Explain this text in simple terms for a beginner: {{selectedtext}}\"', 'ai-explainer'); ?>
        </p>
        <br />
        <button type="button" class="btn-base btn-link" id="reset-prompt-default"><?php echo esc_html__('Reset to Default', 'ai-explainer'); ?></button>
        <?php
    }
    
    /**
     * Process custom prompt before saving
     */
    public function process_custom_prompt_save($value, $old_value) {
        // Verify nonce for security (WordPress handles this automatically for settings)
        if (!current_user_can('manage_options')) {
            return $old_value;
        }
        
        // Sanitize as plain text only
        $sanitized = sanitize_textarea_field($value);
        
        // Set default if empty
        if (empty($sanitized)) {
            $sanitized = ExplainerPlugin_Config::get_default_custom_prompt();
        }
        
        // Check for required {{selectedtext}} variable
        if (!str_contains($sanitized, '{{selectedtext}}')) {
            add_settings_error(
                'explainer_custom_prompt',
                'missing_selectedtext_variable',
                // translators: {{selectedtext}} is a placeholder that will be replaced with the user's selected text
                __('Custom prompt must contain {{selectedtext}} placeholder.', 'ai-explainer')
            );
            return $old_value; // Return old value if validation fails
        }
        
        // Length limit removed - no character restriction
        
        return $sanitized;
    }
    
    /**
     * Process term extraction prompt before saving
     */
    public function process_term_extraction_prompt_save($value, $old_value) {
        // Verify nonce for security (WordPress handles this automatically for settings)
        if (!current_user_can('manage_options')) {
            return $old_value;
        }
        
        // Use validator to process the value
        return $this->validator->validate_term_extraction_prompt($value);
    }

    /**
     * Process real-time enabled setting before saving
     */
    public function process_realtime_enabled_save($value, $old_value) {
        if (!current_user_can('manage_options')) {
            return $old_value;
        }
        
        // Convert to boolean
        return !empty($value) ? '1' : '0';
    }

    /**
     * Process real-time transport setting before saving
     */
    public function process_realtime_transport_save($value, $old_value) {
        if (!current_user_can('manage_options')) {
            return $old_value;
        }
        
        // Validate transport mode
        $allowed_transports = array('auto', 'sse', 'polling');
        if (!in_array($value, $allowed_transports, true)) {
            add_settings_error(
                'explainer_realtime_transport',
                'invalid_transport',
                __('Invalid transport mode selected. Using default (auto).', 'ai-explainer')
            );
            return 'auto';
        }
        
        return sanitize_text_field($value);
    }

    /**
     * Process real-time session duration setting before saving
     */
    public function process_realtime_session_duration_save($value, $old_value) {
        if (!current_user_can('manage_options')) {
            return $old_value;
        }
        
        $duration = intval($value);
        
        // Validate range: 30-300 seconds
        if ($duration < 30 || $duration > 300) {
            add_settings_error(
                'explainer_realtime_session_duration',
                'invalid_duration',
                __('Session duration must be between 30 and 300 seconds. Using default (90 seconds).', 'ai-explainer')
            );
            return 90;
        }
        
        return $duration;
    }

    /**
     * Process real-time heartbeat interval setting before saving
     */
    public function process_realtime_heartbeat_interval_save($value, $old_value) {
        if (!current_user_can('manage_options')) {
            return $old_value;
        }
        
        $interval = intval($value);
        
        // Validate range: 15-60 seconds
        if ($interval < 15 || $interval > 60) {
            add_settings_error(
                'explainer_realtime_heartbeat_interval',
                'invalid_heartbeat',
                __('Heartbeat interval must be between 15 and 60 seconds. Using default (30 seconds).', 'ai-explainer')
            );
            return 30;
        }
        
        return $interval;
    }

    /**
     * Process real-time polling interval setting before saving
     */
    public function process_realtime_polling_interval_save($value, $old_value) {
        if (!current_user_can('manage_options')) {
            return $old_value;
        }
        
        $interval = intval($value);
        
        // Validate range: 1-10 seconds
        if ($interval < 1 || $interval > 10) {
            add_settings_error(
                'explainer_realtime_polling_interval',
                'invalid_polling',
                __('Polling interval must be between 1 and 10 seconds. Using default (3 seconds).', 'ai-explainer')
            );
            return 3;
        }
        
        return $interval;
    }

    /**
     * Process real-time max events setting before saving
     */
    public function process_realtime_max_events_save($value, $old_value) {
        if (!current_user_can('manage_options')) {
            return $old_value;
        }
        
        $max_events = intval($value);
        
        // Validate range: 10-200 events
        if ($max_events < 10 || $max_events > 200) {
            add_settings_error(
                'explainer_realtime_max_events',
                'invalid_max_events',
                __('Maximum events per topic must be between 10 and 200. Using default (50 events).', 'ai-explainer')
            );
            return 50;
        }
        
        return $max_events;
    }

    /**
     * Process real-time debug logging setting before saving
     */
    public function process_realtime_debug_logging_save($value, $old_value) {
        if (!current_user_can('manage_options')) {
            return $old_value;
        }
        
        // Convert to boolean
        return !empty($value) ? '1' : '0';
    }
    
    /**
     * Process debug enabled setting before saving
     */
    public function process_debug_enabled_save($value, $old_value) {
        if (!current_user_can('manage_options')) {
            return $old_value;
        }
        
        // Convert to boolean and update the debug logger
        $enabled = !empty($value);
        
        if (class_exists('ExplainerPlugin_Debug_Logger')) {
            ExplainerPlugin_Debug_Logger::set_enabled($enabled);
        }
        
        return $enabled ? '1' : '0';
    }
    
    /**
     * Process debug section settings before saving
     */
    public function process_debug_section_save($value, $old_value) {
        if (!current_user_can('manage_options')) {
            return $old_value;
        }
        
        // Extract section name from current filter
        $current_filter = current_filter();
        if (preg_match('/explainer_debug_section_(.+)$/', $current_filter, $matches)) {
            $section = $matches[1];
            $enabled = !empty($value);
            
            if (class_exists('ExplainerPlugin_Debug_Logger')) {
                if ($enabled) {
                    ExplainerPlugin_Debug_Logger::enable_section($section);
                } else {
                    ExplainerPlugin_Debug_Logger::disable_section($section);
                }
            }
        }
        
        return !empty($value) ? '1' : '0';
    }
    
    /**
     * Validate custom prompt
     */
    public function validate_custom_prompt($value) {
        return $this->validator->validate_custom_prompt($value);
    }
    
    /**
     * Validate reading level prompt
     */
    public function validate_reading_level_prompt($value) {
        return $this->validator->validate_custom_prompt($value);
    }
    
    /**
     * Validate API provider
     */
    public function validate_api_provider($value) {
        return $this->validator->validate_api_provider($value);
    }
    
    /**
     * Validate language setting
     */
    public function validate_language($value) {
        return $this->validator->validate_language($value);
    }
    
    /**
     * Validate maximum selection length
     */
    public function validate_max_selection_length($value) {
        return $this->validator->validate_max_selection_length($value);
    }
    
    /**
     * Validate minimum selection length
     */
    public function validate_min_selection_length($value) {
        return $this->validator->validate_min_selection_length($value);
    }
    
    /**
     * Validate maximum words
     */
    public function validate_max_words($value) {
        return $this->validator->validate_max_words($value);
    }
    
    /**
     * Validate minimum words
     */
    public function validate_min_words($value) {
        return $this->validator->validate_min_words($value);
    }
    
    /**
     * Validate cache duration
     */
    public function validate_cache_duration($value) {
        return $this->validator->validate_cache_duration($value);
    }
    
    /**
     * Validate rate limit for logged users
     */
    public function validate_rate_limit_logged($value) {
        return $this->validator->validate_rate_limit_logged($value);
    }
    
    /**
     * Validate rate limit for anonymous users
     */
    public function validate_rate_limit_anonymous($value) {
        return $this->validator->validate_rate_limit_anonymous($value);
    }
    
    /**
     * Validate mobile selection delay
     */
    public function validate_mobile_selection_delay($value) {
        return $this->validator->validate_mobile_selection_delay($value);
    }
    
    /**
     * Validate toggle position
     */
    public function validate_toggle_position($value) {
        return $this->validator->validate_toggle_position($value);
    }
    
    
    /**
     * Display usage exceeded admin notice
     */
    public function display_usage_exceeded_notice() {
        // Check if we should show the notice
        if (!explainer_should_show_usage_notice()) {
            return;
        }
        
        // Get disable information
        $stats = explainer_get_usage_exceeded_stats();
        $reason = $stats['reason'];
        $provider = $stats['provider'];
        $time_since = $stats['time_since'];
        
        // Build the message
        $message = __('The plugin has been automatically disabled due to an API issue.', 'ai-explainer');
        if (!empty($reason)) {
            /* translators: %s: Reason for automatic plugin disabling */
            $message .= ' ' . sprintf(__('Reason: %s', 'ai-explainer'), $reason);
        }
        if (!empty($provider)) {
            /* translators: %s: Provider name that triggered the issue */
            $message .= ' ' . sprintf(__('Provider: %s', 'ai-explainer'), $provider);
        }
        if (!empty($time_since)) {
            /* translators: %s: Time since the plugin was disabled */
            $message .= ' ' . sprintf(__('Disabled: %s', 'ai-explainer'), $time_since);
        }
        $message .= ' ' . __('Please check your AI provider API key and account settings.', 'ai-explainer');
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Wait for notification system to be available
            function showUsageNotice() {
                if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                    var notificationId = window.ExplainerPlugin.Notifications.show({
                        type: 'error',
                        title: '<?php echo esc_js(__('AI Explainer Automatically Disabled', 'ai-explainer')); ?>',
                        message: '<?php echo esc_js($message); ?>',
                        duration: 0,
                        dismissible: false,
                        priority: 'high',
                        actions: [
                            {
                                text: '<?php echo esc_js(__('Re-enable Plugin', 'ai-explainer')); ?>',
                                primary: true,
                                callback: function() {
                                    handleReenablePlugin();
                                }
                            },
                            {
                                text: '<?php echo esc_js(__('Dismiss Notice', 'ai-explainer')); ?>',
                                callback: function() {
                                    handleDismissNotice();
                                }
                            },
                            {
                                text: '<?php echo esc_js(__('Plugin Settings', 'ai-explainer')); ?>',
                                callback: function() {
                                    window.location.href = '<?php echo esc_js(admin_url('options-general.php?page=wp-ai-explainer-admin')); ?>';
                                }
                            }
                        ]
                    });
                } else {
                    setTimeout(showUsageNotice, 100);
                }
            }
            
            function handleReenablePlugin() {
                if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                    window.ExplainerPlugin.Notifications.confirm(
                        '<?php echo esc_js(__('Are you sure you want to re-enable the AI Explainer plugin? Make sure you have resolved the usage limit issues first.', 'ai-explainer')); ?>',
                        {
                            title: '<?php echo esc_js(__('Confirm Re-enable', 'ai-explainer')); ?>',
                            confirmText: '<?php echo esc_js(__('Re-enable', 'ai-explainer')); ?>',
                            cancelText: '<?php echo esc_js(__('Cancel', 'ai-explainer')); ?>'
                        }
                    ).then(function(confirmed) {
                        if (confirmed) {
                            var loadingId = window.ExplainerPlugin.Notifications.loading('<?php echo esc_js(__('Re-enabling plugin...', 'ai-explainer')); ?>');
                            
                            $.post(ajaxurl, {
                                action: 'explainer_reenable_plugin',
                                nonce: '<?php echo esc_js(wp_create_nonce('explainer_reenable_plugin')); ?>'
                            })
                            .done(function(response) {
                                window.ExplainerPlugin.Notifications.hide(loadingId);
                                if (response.success) {
                                    window.ExplainerPlugin.Notifications.success('<?php echo esc_js(__('Plugin has been successfully re-enabled.', 'ai-explainer')); ?>');
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 2000);
                                } else {
                                    window.ExplainerPlugin.Notifications.error('<?php echo esc_js(__('Error re-enabling plugin:', 'ai-explainer')); ?> ' + (response.data.message || '<?php echo esc_js(__('Unknown error', 'ai-explainer')); ?>'));
                                }
                            })
                            .fail(function() {
                                window.ExplainerPlugin.Notifications.hide(loadingId);
                                window.ExplainerPlugin.Notifications.error('<?php echo esc_js(__('Failed to re-enable plugin. Please try again.', 'ai-explainer')); ?>');
                            });
                        }
                    });
                }
            }
            
            function handleDismissNotice() {
                if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                    var loadingId = window.ExplainerPlugin.Notifications.loading('<?php echo esc_js(__('Dismissing notice...', 'ai-explainer')); ?>');
                    
                    $.post(ajaxurl, {
                        action: 'explainer_dismiss_usage_notice',
                        nonce: '<?php echo esc_js(wp_create_nonce('explainer_dismiss_notice')); ?>'
                    })
                    .done(function(response) {
                        window.ExplainerPlugin.Notifications.hide(loadingId);
                        if (response.success) {
                            window.ExplainerPlugin.Notifications.info('<?php echo esc_js(__('Notice dismissed. Plugin remains disabled.', 'ai-explainer')); ?>');
                        } else {
                            window.ExplainerPlugin.Notifications.error('<?php echo esc_js(__('Error dismissing notice:', 'ai-explainer')); ?> ' + (response.data.message || '<?php echo esc_js(__('Unknown error', 'ai-explainer')); ?>'));
                        }
                    })
                    .fail(function() {
                        window.ExplainerPlugin.Notifications.hide(loadingId);
                        window.ExplainerPlugin.Notifications.error('<?php echo esc_js(__('Failed to dismiss notice. Please try again.', 'ai-explainer')); ?>');
                    });
                }
            }
            
            showUsageNotice();
        });
        </script>
        <?php
    }
    
    /**
     * Display API key configuration notice
     */
    public function display_api_key_notice() {
        // Only show on admin pages
        if (!is_admin()) {
            return;
        }
        
        // Check for API key reset notice (high priority)
        $api_keys_reset = get_option('explainer_api_keys_reset_notice', false);
        if ($api_keys_reset) {
            $this->display_api_key_reset_notice();
            return;
        }
        
        // Don't show if user has dismissed it
        if (get_user_meta(get_current_user_id(), '_explainer_api_key_notice_dismissed', true)) {
            return;
        }
        
        // Check if we have any API key configured
        $openai_key = trim(get_option('explainer_openai_api_key', ''));
        $claude_key = trim(get_option('explainer_claude_api_key', ''));
        
        // If we have at least one API key configured, don't show the notice
        if (!empty($openai_key) || !empty($claude_key)) {
            return;
        }
        
        // Get current provider to show contextual message
        $current_provider = get_option('explainer_api_provider', 'openai');
        $provider_name = ($current_provider === 'claude') ? 'Claude' : 'OpenAI';
        /* translators: %s: AI provider name (OpenAI or Claude) */
        $message = sprintf(__('Please configure your %s API key to start using AI explanations. The plugin is ready to go once you add your API credentials.', 'ai-explainer'), $provider_name);
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Wait for notification system to be available
            function showApiKeyNotice() {
                if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                    var notificationId = window.ExplainerPlugin.Notifications.show({
                        type: 'warning',
                        title: '<?php echo esc_js(__('AI Explainer Setup Required', 'ai-explainer')); ?>',
                        message: '<?php echo esc_js($message); ?>',
                        duration: 0,
                        dismissible: true,
                        priority: 'high',
                        actions: [
                            {
                                text: '<?php echo esc_js(__('Configure Now', 'ai-explainer')); ?>',
                                primary: true,
                                callback: function() {
                                    window.location.href = '<?php echo esc_js(admin_url('admin.php?page=wp-ai-explainer-admin&tab=ai-provider&scroll_to_api_key=1')); ?>';
                                }
                            }
                        ]
                    });
                    
                    // Store notification ID for manual dismissal handling
                    window.explainerApiKeyNotificationId = notificationId;
                    
                    // Override the built-in dismiss to call our AJAX handler
                    setTimeout(function() {
                        var notification = window.ExplainerPlugin.Notifications.notifications.get(notificationId);
                        if (notification && notification.element) {
                            var closeBtn = notification.element.querySelector('.explainer-notification-close');
                            if (closeBtn) {
                                closeBtn.onclick = function() {
                                    handleApiKeyNoticeDismiss();
                                };
                            }
                        }
                    }, 100);
                } else {
                    setTimeout(showApiKeyNotice, 100);
                }
            }
            
            function handleApiKeyNoticeDismiss() {
                if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                    $.post(ajaxurl, {
                        action: 'explainer_dismiss_api_key_notice',
                        nonce: '<?php echo esc_js(wp_create_nonce('explainer_dismiss_api_notice')); ?>'
                    })
                    .done(function(response) {
                        if (response.success) {
                            window.ExplainerPlugin.Notifications.hide(window.explainerApiKeyNotificationId);
                        } else {
                            console.error('Failed to dismiss API key notice');
                        }
                    })
                    .fail(function() {
                        console.error('Failed to dismiss notice');
                        // Still hide the notification even if dismissal fails
                        window.ExplainerPlugin.Notifications.hide(window.explainerApiKeyNotificationId);
                    });
                }
            }
            
            showApiKeyNotice();
        });
        
        // Global function for API key field pulsing
        function explainerPulseApiKeyField() {
            // Add delay to ensure page loads before pulsing
            setTimeout(function() {
                var apiKeyField = document.querySelector('#explainer_openai_api_key, #explainer_claude_api_key');
                
                if (apiKeyField) {
                    // Scroll to the field
                    apiKeyField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Add pulse effect
                    var originalBackground = apiKeyField.style.backgroundColor;
                    var pulseCount = 0;
                    var maxPulses = 3;
                    
                    function doPulse() {
                        if (pulseCount >= maxPulses) {
                            apiKeyField.style.backgroundColor = originalBackground;
                            return;
                        }
                        
                        // Pulse to highlight color
                        apiKeyField.style.backgroundColor = '#fff3cd';
                        apiKeyField.style.transition = 'background-color 0.3s ease';
                        
                        setTimeout(function() {
                            apiKeyField.style.backgroundColor = '#f8f9fa';
                            setTimeout(function() {
                                pulseCount++;
                                doPulse();
                            }, 300);
                        }, 300);
                    }
                    
                    setTimeout(doPulse, 500); // Start after scroll completes
                    
                    // Focus the field
                    setTimeout(function() {
                        apiKeyField.focus();
                    }, 1000);
                }
            }, 1000);
        }
        </script>
        <?php
    }
    
    /**
     * Display API key reset notice when keys had to be wiped due to encryption issues
     */
    private function display_api_key_reset_notice() {
        $current_provider = get_option('explainer_api_provider', 'openai');
        $provider_name = ($current_provider === 'claude') ? 'Claude' : 'OpenAI';
        /* translators: %s: AI provider name (OpenAI or Claude) */
        $message = sprintf(__('Your %s API key was reset due to encryption changes and needs to be re-entered. This happens occasionally when the system restarts. Please re-configure your API key to continue using AI explanations.', 'ai-explainer'), $provider_name);
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Wait for notification system to be available
            function showApiKeyResetNotice() {
                if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                    var notificationId = window.ExplainerPlugin.Notifications.show({
                        type: 'error',
                        title: '<?php echo esc_js(__('API Key Reset Required', 'ai-explainer')); ?>',
                        message: '<?php echo esc_js($message); ?>',
                        duration: 0,
                        dismissible: true,
                        priority: 'critical',
                        actions: [
                            {
                                text: '<?php echo esc_js(__('Re-enter API Key', 'ai-explainer')); ?>',
                                primary: true,
                                callback: function() {
                                    window.location.href = '<?php echo esc_js(admin_url('admin.php?page=wp-ai-explainer-admin&tab=ai-provider&scroll_to_api_key=1')); ?>';
                                }
                            }
                        ]
                    });
                    
                    // Store notification ID for manual dismissal handling
                    window.explainerApiKeyResetNotificationId = notificationId;
                    
                    // Override the built-in dismiss to call our AJAX handler
                    setTimeout(function() {
                        var notification = window.ExplainerPlugin.Notifications.notifications.get(notificationId);
                        if (notification && notification.element) {
                            var closeBtn = notification.element.querySelector('.explainer-notification-close');
                            if (closeBtn) {
                                closeBtn.onclick = function() {
                                    handleApiKeyResetNoticeDismiss();
                                };
                            }
                        }
                    }, 100);
                } else {
                    setTimeout(showApiKeyResetNotice, 100);
                }
            }
            
            function handleApiKeyResetNoticeDismiss() {
                if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                    $.post(ajaxurl, {
                        action: 'explainer_dismiss_api_key_reset_notice',
                        nonce: '<?php echo esc_js(wp_create_nonce('explainer_dismiss_api_reset_notice')); ?>'
                    })
                    .done(function(response) {
                        if (response.success) {
                            window.ExplainerPlugin.Notifications.hide(window.explainerApiKeyResetNotificationId);
                        } else {
                            console.error('Failed to dismiss API key reset notice');
                        }
                    })
                    .fail(function() {
                        console.error('Failed to dismiss reset notice');
                        // Still hide the notification even if dismissal fails
                        window.ExplainerPlugin.Notifications.hide(window.explainerApiKeyResetNotificationId);
                    });
                }
            }
            
            showApiKeyResetNotice();
        });
        </script>
        <?php
    }
    
    /**
     * Handle AJAX request to re-enable the plugin
     */
    public function handle_reenable_plugin() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'explainer_reenable_plugin' ) ) {
            wp_send_json_error(array('message' => __('Invalid nonce.', 'ai-explainer')));
        }
        
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-explainer')));
        }
        
        // Check if plugin is actually auto-disabled
        if (!explainer_is_auto_disabled()) {
            wp_send_json_error(array('message' => __('Plugin is not currently auto-disabled.', 'ai-explainer')));
        }
        
        // Re-enable the plugin
        $success = explainer_reenable_plugin();
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('Plugin has been successfully re-enabled.', 'ai-explainer')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to re-enable plugin.', 'ai-explainer')
            ));
        }
    }
    
    /**
     * Handle AJAX request to dismiss usage exceeded notice
     */
    public function handle_dismiss_usage_notice() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'explainer_dismiss_notice' ) ) {
            wp_send_json_error(array('message' => __('Invalid nonce.', 'ai-explainer')));
        }
        
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-explainer')));
        }
        
        // Dismiss the notice
        $success = explainer_dismiss_usage_notice();
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('Notice dismissed successfully.', 'ai-explainer')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to dismiss notice.', 'ai-explainer')
            ));
        }
    }
    
    /**
     * Handle AJAX request to dismiss API key notice
     */
    public function handle_dismiss_api_key_notice() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'explainer_dismiss_api_notice' ) ) {
            wp_send_json_error(array('message' => __('Invalid nonce.', 'ai-explainer')));
        }
        
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-explainer')));
        }
        
        // Set user meta to remember dismissal
        $success = update_user_meta(get_current_user_id(), '_explainer_api_key_notice_dismissed', true);
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('Notice dismissed successfully.', 'ai-explainer')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to dismiss notice.', 'ai-explainer')
            ));
        }
    }
    
    /**
     * Handle AJAX request to dismiss API key reset notice
     */
    public function handle_dismiss_api_key_reset_notice() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'explainer_dismiss_api_reset_notice' ) ) {
            wp_send_json_error(array('message' => __('Invalid nonce.', 'ai-explainer')));
        }
        
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-explainer')));
        }
        
        // Clear the reset notice flag
        $success = delete_option('explainer_api_keys_reset_notice');
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('API key reset notice dismissed successfully.', 'ai-explainer')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to dismiss API key reset notice.', 'ai-explainer')
            ));
        }
    }
    
    /**
     * Get popular selections via AJAX
     */
    public function get_popular_selections() {
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        // Get request parameters
        $page = isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 20;
        $filter = isset($_POST['filter']) ? sanitize_text_field(wp_unslash($_POST['filter'])) : 'all';
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        
        // Initialize selection tracker
        $tracker = new ExplainerPlugin_Selection_Tracker();
        
        // Check if database table exists
        if (!$tracker->test_database_connection()) {
            // Test again (tables should be created by centralized database setup)
            if (!$tracker->test_database_connection()) {
                wp_send_json_error(array('message' => __('Selection tracking database not available. Please contact administrator.', 'ai-explainer')));
            }
        }
        
        // Calculate offset
        $offset = ($page - 1) * $per_page;
        
        // Get selections based on filter
        $selections = array();
        $total_count = 0;
        
        if (!empty($search)) {
            // Search functionality
            $all_results = $tracker->search_selections($search, 1000); // Get more for accurate count
            $total_count = count($all_results);
            $selections = array_slice($all_results, $offset, $per_page);
        } else {
            // Filter by type
            switch ($filter) {
                case 'popular':
                    $min_count = 5;
                    break;
                case 'recent':
                    // Get all selections and filter by date
                    $all_selections = $tracker->get_popular_selections(1000, 1);
                    $week_ago = wp_date('Y-m-d H:i:s', strtotime('-7 days'));
                    $filtered = array_filter($all_selections, function($selection) use ($week_ago) {
                        return $selection['last_seen'] >= $week_ago;
                    });
                    $total_count = count($filtered);
                    $selections = array_slice($filtered, $offset, $per_page);
                    break;
                default:
                    $min_count = 1;
                    break;
            }
            
            if ($filter !== 'recent') {
                // Use the new grouped method to avoid duplicates per reading level
                $all_grouped_selections = $tracker->get_popular_selections_grouped(1000, $min_count);
                $total_count = count($all_grouped_selections);
                
                // Apply pagination to grouped results
                $selections = array_slice($all_grouped_selections, $offset, $per_page);
            }
        }
        
        // Format data for response
        $formatted_selections = array();
        foreach ($selections as $selection) {
            // Handle both old individual format (from search/recent) and new grouped format
            if (isset($selection['reading_level_data'])) {
                // New grouped format
                $formatted_selections[] = array(
                    'text_hash' => $selection['text_hash'],
                    'selected_text' => $selection['selected_text'],
                    'text_preview' => $selection['text_preview'],
                    'selection_count' => $selection['total_selection_count'],
                    'reading_level_count' => $selection['reading_level_count'],
                    'reading_levels' => $selection['reading_levels'],
                    'first_seen' => $this->format_date($selection['first_seen']),
                    'last_seen' => $this->format_date($selection['last_seen']),
                    'source_urls' => $selection['source_urls'],
                    'primary_url' => !empty($selection['source_urls']) ? $selection['source_urls'][0] : '',
                    'reading_level_data' => $selection['reading_level_data']
                );
            } else {
                // Legacy individual format (for search results and recent filter)
                $formatted_selections[] = array(
                    'id' => $selection['id'],
                    'text_hash' => $selection['text_hash'],
                    'selected_text' => $selection['selected_text'],
                    'text_preview' => $this->get_text_preview($selection['selected_text']),
                    'selection_count' => $selection['selection_count'],
                    'first_seen' => $this->format_date($selection['first_seen']),
                    'last_seen' => $this->format_date($selection['last_seen']),
                    'source_urls' => $selection['source_urls'],
                    'primary_url' => !empty($selection['source_urls']) ? $selection['source_urls'][0] : ''
                );
            }
        }
        
        // Calculate pagination info
        $total_pages = ceil($total_count / $per_page);
        $start_item = $offset + 1;
        $end_item = min($offset + $per_page, $total_count);
        
        wp_send_json_success(array(
            'selections' => $formatted_selections,
            'pagination' => array(
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_items' => $total_count,
                'per_page' => $per_page,
                'start_item' => $start_item,
                'end_item' => $end_item,
                'has_prev' => $page > 1,
                'has_next' => $page < $total_pages
            )
        ));
    }
    
    /**
     * Search selections via AJAX (alias for get_popular_selections with search)
     */
    public function search_selections() {
        // This is handled by the same method as get_popular_selections
        $this->get_popular_selections();
    }
    
    /**
     * Get text preview (first 100 characters)
     * 
     * @param string $text Full text
     * @return string Text preview
     */
    private function get_text_preview($text) {
        return $this->utilities->get_text_preview($text);
    }
    
    /**
     * Format date for display
     * 
     * @param string $date MySQL datetime string
     * @return string Formatted date
     */
    private function format_date($date) {
        return $this->utilities->format_date($date);
    }
    
    
    
    /**
     * Sanitize blocked words list
     * 
     * @param string $value The textarea value with blocked words
     * @return string Sanitized blocked words list
     */
    public function sanitize_blocked_words($value) {
        return $this->validator->sanitize_blocked_words($value);
    }
    
    /**
     * Delete API key via AJAX
     */
    public function delete_api_key() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_admin_nonce')) {
            wp_die(esc_html('Security check failed'));
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : '';
        
        if (!in_array($provider, array('openai', 'claude'))) {
            wp_send_json_error(array('message' => 'Invalid provider'));
        }
        
        $option_name = '';
        $provider_name = '';
        
        if ($provider === 'openai') {
            $option_name = 'explainer_openai_api_key';
            $provider_name = 'OpenAI';
        } else if ($provider === 'claude') {
            $option_name = 'explainer_claude_api_key';
            $provider_name = 'Claude';
        }
        
        // Delete the API key
        $deleted = delete_option($option_name);
        
        if ($deleted) {
            
            wp_send_json_success(array(
                'message' => sprintf('%s API key has been successfully deleted.', esc_html($provider_name)
            )));
        } else {
            wp_send_json_error(array(
                'message' => sprintf('Failed to delete %s API key. It may not have been configured.', esc_html($provider_name)
            )));
        }
    }
    
    
    /**
     * Clear all popular selections via AJAX
     */
    public function clear_all_selections() {
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';

        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Checking table existence, not cached
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) != $table_name) {
            wp_send_json_error(array('message' => __('Selections table does not exist.', 'ai-explainer')));
        }

        // Get count before deletion for confirmation
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom plugin table requires direct query, table name is prefixed and safe
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        if ($count == 0) {
            wp_send_json_success(array(
                'message' => __('No selections to clear.', 'ai-explainer'),
                'count' => 0
            ));
        }

        // Delete all selections
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom plugin table requires direct query, table name is prefixed and safe
        $deleted = $wpdb->query("TRUNCATE TABLE {$table_name}");
        
        if ($deleted !== false) {
            wp_send_json_success(array(
                'message' => sprintf(
                    /* translators: %d is the number of selections cleared */
                    __('%d selection(s) have been successfully cleared.', 'ai-explainer'),
                    $count
                ),
                'count' => $count
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to clear selections.', 'ai-explainer')));
        }
    }
    
    /**
     * Get AI explanation for a selection via AJAX
     */
    public function get_selection_explanation() {
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        $selection_id = isset($_POST['selection_id']) ? absint(wp_unslash($_POST['selection_id'])) : 0;
        
        if (!$selection_id) {
            wp_send_json_error(array('message' => __('Invalid selection ID', 'ai-explainer')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        
        // Get the selection
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $selection = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $selection_id
        ));
        
        if (!$selection) {
            wp_send_json_error(array('message' => __('Selection not found', 'ai-explainer')));
        }
        
        // Parse source URLs from JSON
        $source_urls = json_decode($selection->source_urls, true) ?: array();
        $primary_url = !empty($source_urls) ? $source_urls[0] : '';
        
        // Return explanation if we have one, otherwise show message that none exists
        if (!empty($selection->ai_explanation)) {
            wp_send_json_success(array(
                'explanation' => $selection->ai_explanation,
                'source' => 'cached',
                'source_urls' => $source_urls,
                'primary_url' => $primary_url,
                'selected_text' => $selection->selected_text
            ));
        } else {
            wp_send_json_success(array(
                'explanation' => __('No AI explanation available for this selection. Explanations are generated when users click on text selections on your website.', 'ai-explainer'),
                'source' => 'none',
                'source_urls' => $source_urls,
                'primary_url' => $primary_url,
                'selected_text' => $selection->selected_text
            ));
        }
    }
    
    /**
     * Save edited explanation via AJAX
     */
    public function save_selection_explanation() {
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        $selection_id = isset($_POST['selection_id']) ? absint(wp_unslash($_POST['selection_id'])) : 0;
        $explanation = isset($_POST['explanation']) ? wp_kses_post(wp_unslash($_POST['explanation'])) : '';
        
        if (!$selection_id) {
            wp_send_json_error(array('message' => __('Invalid selection ID', 'ai-explainer')));
        }
        
        if (empty($explanation)) {
            wp_send_json_error(array('message' => __('Explanation cannot be empty', 'ai-explainer')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        
        // Update the explanation and mark as manually edited
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $result = $wpdb->update(
            $table_name,
            array(
                'ai_explanation' => $explanation,
                'manually_edited' => 1
            ),
            array('id' => $selection_id),
            array('%s', '%d'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Explanation saved successfully', 'ai-explainer'),
                'explanation' => $explanation
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save explanation', 'ai-explainer')));
        }
    }
    
    /**
     * Delete individual selection via AJAX
     */
    public function delete_selection() {
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        $selection_id = isset($_POST['selection_id']) ? absint(wp_unslash($_POST['selection_id'])) : 0;
        
        if (!$selection_id) {
            wp_send_json_error(array('message' => __('Invalid selection ID', 'ai-explainer')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        
        // Get selection info before deletion for confirmation
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $selection = $wpdb->get_row($wpdb->prepare(
            "SELECT selected_text FROM $table_name WHERE id = %d",
            $selection_id
        ));

        if (!$selection) {
            wp_send_json_error(array('message' => __('Selection not found', 'ai-explainer')));
        }

        // Delete the selection
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $result = $wpdb->delete(
            $table_name,
            array('id' => $selection_id),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => sprintf(
                    /* translators: %s is the text that was deleted */
                    __('Selection "%s" has been successfully deleted.', 'ai-explainer'),
                    wp_trim_words($selection->selected_text, 5)
                )
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete selection', 'ai-explainer')));
        }
    }
    
    /**
     * AI Terms Modal AJAX Handlers
     */
    
    /**
     * Load AI terms for modal display
     */
    public function handle_load_ai_terms() {
        check_ajax_referer('explainer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $page = isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
        $sort_field = isset($_POST['sort_field']) ? sanitize_text_field(wp_unslash($_POST['sort_field'])) : 'selected_text';
        $sort_direction = isset($_POST['sort_direction']) ? sanitize_text_field(wp_unslash($_POST['sort_direction'])) : 'asc';
        
        // Validate post ID
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Valid post ID is required', 'ai-explainer')));
        }
        
        // Validate sort direction
        $sort_direction = in_array($sort_direction, array('asc', 'desc')) ? $sort_direction : 'asc';
        
        // Validate sort field
        $allowed_sort_fields = array('selected_text', 'selection_count', 'created_at');
        $sort_field = in_array($sort_field, $allowed_sort_fields) ? $sort_field : 'selected_text';
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';

        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Checking table existence, not cached
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) != $table_name) {
            wp_send_json_success(array(
                'terms' => array(),
                'total' => 0,
                'message' => __('Terms table not found. No terms available yet.', 'ai-explainer')
            ));
        }
        
        // Build WHERE clause
        $where_conditions = array();
        $where_params = array();
        
        // Filter by post ID
        $where_conditions[] = 'post_id = %d';
        $where_params[] = $post_id;
        
        // Filter to only show post scan terms (ai_scan source)
        $where_conditions[] = 'source = %s';
        $where_params[] = 'ai_scan';
        
        if (!empty($search)) {
            $where_conditions[] = 'selected_text LIKE %s';
            $where_params[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        if ($status === 'enabled') {
            $where_conditions[] = 'enabled = 1';
        } elseif ($status === 'disabled') {
            $where_conditions[] = 'enabled = 0';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Get total count (where_params will always have source filter now)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefixed and safe, where clause is constructed safely
        $count_sql = "SELECT COUNT(DISTINCT selected_text) FROM {$table_name} {$where_clause}";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query uses placeholders, values are prepared
        $total = $wpdb->get_var($wpdb->prepare($count_sql, $where_params));

        // Calculate offset
        $offset = ($page - 1) * $per_page;

        // Get terms with aggregated data
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and sort fields are prefixed and safe
        $sql = "
            SELECT
                MIN(id) as id,
                selected_text as text,
                MAX(enabled) as enabled,
                COUNT(*) as usage_count,
                MAX(last_seen) as latest_use,
                MIN(first_seen) as first_seen
            FROM {$table_name}
            {$where_clause}
            GROUP BY selected_text
            ORDER BY {$sort_field} {$sort_direction}
            LIMIT %d OFFSET %d
        ";

        $query_params = array_merge($where_params, array($per_page, $offset));

        // where_params will always have the source filter now, so we can simplify
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query uses placeholders, values are prepared
        $results = $wpdb->get_results($wpdb->prepare($sql, $query_params));
        
        // Format results for frontend
        $terms = array();
        if ($results) {
            foreach ($results as $result) {
                // Get all explanations for this term text from the current post (only from post scan source)
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefixed and safe
                $explanations_sql = "
                    SELECT reading_level, ai_explanation
                    FROM {$table_name}
                    WHERE selected_text = %s
                    AND post_id = %d
                    AND source = 'ai_scan'
                    AND ai_explanation IS NOT NULL
                    AND ai_explanation != ''
                ";
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query uses placeholders, values are prepared
                $explanations_results = $wpdb->get_results($wpdb->prepare($explanations_sql, $result->text, $post_id));

                // Format explanations by reading level
                $explanations = array();
                if ($explanations_results) {
                    foreach ($explanations_results as $exp) {
                        $explanations[$exp->reading_level] = $exp->ai_explanation;
                    }
                }
                
                // Check if term is new (created in the last 24 hours)
                $first_seen_timestamp = strtotime($result->first_seen);
                $current_time = time();
                
                // Handle invalid timestamps
                if ($first_seen_timestamp === false || $first_seen_timestamp === null || empty($result->first_seen)) {
                    // If no valid first_seen, treat as not new
                    $is_new = false;
                    $age_hours = 'unknown';
                } else {
                    $age_seconds = $current_time - $first_seen_timestamp;
                    $age_hours = $age_seconds / 3600;
                    $is_new = $age_seconds < (24 * 60 * 60); // 24 hours in seconds

                }
                
                $terms[] = array(
                    'id' => intval($result->id),
                    'text' => $result->text,
                    'enabled' => (bool) $result->enabled,
                    'usage_count' => intval($result->usage_count),
                    'latest_use' => $result->latest_use,
                    'first_seen' => $result->first_seen,
                    'is_new' => $is_new,
                    'explanations' => $explanations
                );
            }
        }

        wp_send_json_success(array(
            'terms' => $terms,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page
        ));
    }
    
    /**
     * Update term status (enable/disable)
     */
    public function handle_update_term_status() {
        check_ajax_referer('explainer_nonce', 'nonce');

        $term_id = isset($_POST['term_id']) ? absint(wp_unslash($_POST['term_id'])) : 0;
        // Properly handle boolean from FormData - "false" string should be false, not true
        $enabled_raw = isset($_POST['enabled']) ? sanitize_text_field(wp_unslash($_POST['enabled'])) : 'false';
        $enabled = ($enabled_raw === 'true' || $enabled_raw === true || $enabled_raw === '1' || $enabled_raw === 1);

        if (!$term_id) {
            wp_send_json_error(array('message' => __('Invalid term ID', 'ai-explainer')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';

        // Get the term data to check permissions and get text
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $term_data = $wpdb->get_row($wpdb->prepare(
            "SELECT post_id, selected_text FROM {$table_name} WHERE id = %d",
            $term_id
        ));

        if (!$term_data) {
            wp_send_json_error(array('message' => __('Term not found', 'ai-explainer')));
        }
        
        // Check permissions: for global terms (post_id = 0), check manage_options; for post-specific terms, check edit_post
        if ($term_data->post_id == 0) {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
            }
        } else {
            if (!current_user_can('edit_post', $term_data->post_id)) {
                wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
            }
        }
        
        // Update the specific term by ID
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $result = $wpdb->update(
            $table_name,
            array('enabled' => $enabled ? 1 : 0),
            array('id' => $term_id),
            array('%d'),
            array('%d')
        );
        
        if ($result !== false) {
            $status_text = $enabled ? __('enabled', 'ai-explainer') : __('disabled', 'ai-explainer');
            $response_data = array(
                'message' => sprintf(
                    /* translators: %1$s is the term text, %2$s is enabled/disabled */
                    __('Term "%1$s" has been %2$s successfully.', 'ai-explainer'),
                    wp_trim_words($term_data->selected_text, 5),
                    $status_text
                ),
                'enabled' => $enabled,
                'updated_rows' => $result,
                'debug_signature' => 'OUR_HANDLER_2024_' . time() // Unique signature to identify our handler
            );
            wp_send_json_success($response_data);
        } else {
            wp_send_json_error(array('message' => __('Failed to update term status', 'ai-explainer')));
        }
    }
    
    /**
     * Delete term (all instances)
     */
    public function handle_delete_term() {
        check_ajax_referer('explainer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        $term_id = isset($_POST['term_id']) ? absint(wp_unslash($_POST['term_id'])) : 0;
        
        if (!$term_id) {
            wp_send_json_error(array('message' => __('Invalid term ID', 'ai-explainer')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        
        // Get the term text first
        $term_text = $wpdb->get_var($wpdb->prepare(
            "SELECT selected_text FROM $table_name WHERE id = %d",
            $term_id
        ));
        
        if (!$term_text) {
            wp_send_json_error(array('message' => __('Term not found', 'ai-explainer')));
        }
        
        // Delete all instances of this term
        $result = $wpdb->delete(
            $table_name,
            array('selected_text' => $term_text),
            array('%s')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => sprintf(
                    /* translators: %s is the term text */
                    __('Term "%s" and all its instances have been successfully deleted.', 'ai-explainer'),
                    wp_trim_words($term_text, 5)
                ),
                'deleted_count' => $result
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete term', 'ai-explainer')));
        }
    }
    
    /**
     * Get existing explanations for a specific term text
     */
    public function handle_get_term_explanations() {
        check_ajax_referer('explainer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        $term_text = isset($_POST['term_text']) ? sanitize_text_field(wp_unslash($_POST['term_text'])) : '';
        
        if (empty($term_text)) {
            wp_send_json_error(array('message' => __('Term text is required', 'ai-explainer')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        
        // Generate text hash like the system does
        $text_hash = hash('sha256', strtolower(trim($term_text)));
        
        // Get post_id from request context
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        
        // Get all explanations for this text across all reading levels
        // Prefer explanations from the current post, fall back to any explanations
        $selections = $wpdb->get_results($wpdb->prepare(
            "SELECT reading_level, ai_explanation, source_urls, id, post_id
             FROM $table_name 
             WHERE text_hash = %s 
             AND (ai_explanation IS NOT NULL AND ai_explanation != '') 
             ORDER BY 
                CASE WHEN post_id = %d THEN 0 ELSE 1 END,
                updated_at DESC",
            $text_hash,
            $post_id
        ));
        
        // Initialize empty explanations for all configured reading levels
        $config = ExplainerPlugin_Config::get_reading_level_labels();
        $explanations = array();
        foreach ($config as $level_key => $level_label) {
            $explanations[$level_key] = '';
        }
        
        $has_existing = false;
        $source_urls = array();
        $selection_ids = array();
        
        // Populate explanations from database results
        if ($selections) {
            foreach ($selections as $selection) {
                $reading_level = $selection->reading_level;
                if (isset($explanations[$reading_level])) {
                    $explanations[$reading_level] = $selection->ai_explanation;
                    $has_existing = true;
                    
                    // Collect source URLs and selection IDs
                    $selection_ids[] = $selection->id;
                    $urls = json_decode($selection->source_urls, true);
                    if ($urls && is_array($urls)) {
                        $source_urls = array_merge($source_urls, $urls);
                    }
                }
            }
            // Remove duplicate URLs
            $source_urls = array_unique($source_urls);
        }
        
        wp_send_json_success(array(
            'explanations' => $explanations,
            'has_existing' => $has_existing,
            'term_text' => $term_text,
            'text_hash' => $text_hash,
            'selection_ids' => $selection_ids,
            'source_urls' => $source_urls
        ));
    }
    
    /**
     * Handle bulk actions on terms
     */
    public function handle_bulk_action_terms() {
        check_ajax_referer('explainer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        $action = isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '';
        $term_ids = isset($_POST['term_ids']) ? array_map('absint', wp_unslash((array) $_POST['term_ids'])) : array();
        
        if (empty($action) || empty($term_ids)) {
            wp_send_json_error(array('message' => __('Invalid bulk action parameters', 'ai-explainer')));
        }
        
        if (!in_array($action, array('enable', 'disable', 'delete'))) {
            wp_send_json_error(array('message' => __('Invalid bulk action', 'ai-explainer')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        
        // Get term texts for all IDs
        $placeholders = implode(',', array_fill(0, count($term_ids), '%d'));
        $term_texts = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT selected_text FROM $table_name WHERE id IN ($placeholders)",
            ...$term_ids
        ));
        
        if (empty($term_texts)) {
            wp_send_json_error(array('message' => __('No valid terms found', 'ai-explainer')));
        }
        
        $affected_rows = 0;
        
        // Perform bulk action
        switch ($action) {
            case 'enable':
                $placeholders = implode(',', array_fill(0, count($term_texts), '%s'));
                $affected_rows = $wpdb->query($wpdb->prepare(
                    "UPDATE $table_name SET enabled = 1 WHERE selected_text IN ($placeholders)",
                    ...$term_texts
                ));
                $message = sprintf(
                    /* translators: %d is the number of terms enabled */
                    __('%d terms have been enabled successfully.', 'ai-explainer'),
                    count($term_texts)
                );
                break;
                
            case 'disable':
                $placeholders = implode(',', array_fill(0, count($term_texts), '%s'));
                $affected_rows = $wpdb->query($wpdb->prepare(
                    "UPDATE $table_name SET enabled = 0 WHERE selected_text IN ($placeholders)",
                    ...$term_texts
                ));
                $message = sprintf(
                    /* translators: %d is the number of terms disabled */
                    __('%d terms have been disabled successfully.', 'ai-explainer'),
                    count($term_texts)
                );
                break;
                
            case 'delete':
                $placeholders = implode(',', array_fill(0, count($term_texts), '%s'));
                $affected_rows = $wpdb->query($wpdb->prepare(
                    "DELETE FROM $table_name WHERE selected_text IN ($placeholders)",
                    ...$term_texts
                ));
                $message = sprintf(
                    /* translators: %d is the number of terms deleted */
                    __('%d terms and all their instances have been deleted successfully.', 'ai-explainer'),
                    count($term_texts)
                );
                break;
        }
        
        if ($affected_rows !== false) {
            wp_send_json_success(array(
                'message' => $message,
                'affected_rows' => $affected_rows,
                'term_count' => count($term_texts)
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to perform bulk action', 'ai-explainer')));
        }
    }
    
    /**
     * Save term explanations for multiple reading levels
     */
    public function handle_save_term_explanations() {
        check_ajax_referer('explainer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        $term_id = isset($_POST['term_id']) ? absint(wp_unslash($_POST['term_id'])) : 0;
        $term_text = isset($_POST['term_text']) ? sanitize_text_field(wp_unslash($_POST['term_text'])) : '';
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $explanations_raw = isset($_POST['explanations']) ? sanitize_textarea_field(wp_unslash($_POST['explanations'])) : '';
        $explanations = !empty($explanations_raw) ? json_decode($explanations_raw, true) : array();
        
        if (!$term_id || empty($term_text)) {
            wp_send_json_error(array('message' => __('Invalid term data', 'ai-explainer')));
        }
        
        if (empty($explanations) || !is_array($explanations)) {
            wp_send_json_error(array('message' => __('No explanations provided', 'ai-explainer')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        
        // Generate text hash like the system does
        $text_hash = hash('sha256', strtolower(trim($term_text)));
        
        $saved_count = 0;
        $error_count = 0;
        $saved_levels = array();
        
        // Save explanations for each reading level
        foreach ($explanations as $reading_level => $explanation) {
            if (empty(trim($explanation))) {
                continue; // Skip empty explanations
            }
            
            $sanitized_explanation = wp_kses_post(trim($explanation));
            $sanitized_level = sanitize_text_field($reading_level);
            
            // Check if we already have a record for this term text, post, and reading level
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table_name WHERE text_hash = %s AND post_id = %d AND reading_level = %s",
                $text_hash,
                $post_id,
                $sanitized_level
            ));
            
            if ($existing) {
                // Update existing record
                $result = $wpdb->update(
                    $table_name,
                    array(
                        'ai_explanation' => $sanitized_explanation,
                        'manually_edited' => 1,
                        'last_seen' => current_time('mysql')
                    ),
                    array('id' => $existing->id),
                    array('%s', '%d', '%s'),
                    array('%d')
                );
                
                if ($result !== false) {
                    $saved_count++;
                    $saved_levels[] = $sanitized_level;
                } else {
                    $error_count++;
                }
            } else {
                // Create new record
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'selected_text' => $term_text,
                        'text_hash' => $text_hash,
                        'post_id' => $post_id,
                        'reading_level' => $sanitized_level,
                        'ai_explanation' => $sanitized_explanation,
                        'manually_edited' => 1,
                        'enabled' => 1,
                        'selection_count' => 1,
                        'source_urls' => wp_json_encode(array()),
                        'source' => 'manual',
                        'first_seen' => current_time('mysql'),
                        'last_seen' => current_time('mysql')
                    ),
                    array('%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s')
                );
                
                if ($result !== false) {
                    $saved_count++;
                    $saved_levels[] = $sanitized_level;
                } else {
                    $error_count++;
                }
            }
        }
        
        if ($saved_count > 0) {
            $message = sprintf(
                /* translators: %1$d is the number of explanations saved, %2$s is the term text */
                __('%1$d explanation(s) saved successfully for term "%2$s".', 'ai-explainer'),
                $saved_count,
                wp_trim_words($term_text, 5)
            );
            
            if ($error_count > 0) {
                $message .= ' ' . sprintf(
                    /* translators: %d is the number of explanations that failed to save */
                    __('%d explanation(s) failed to save.', 'ai-explainer'),
                    $error_count
                );
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'saved_count' => $saved_count,
                'error_count' => $error_count,
                'saved_levels' => $saved_levels
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to save any explanations', 'ai-explainer'),
                'error_count' => $error_count
            ));
        }
    }
    
    /**
     * AJAX handler to create a new term with explanations
     */
    public function handle_create_term() {
        check_ajax_referer('explainer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        $term_text = isset($_POST['term_text']) ? sanitize_text_field(wp_unslash($_POST['term_text'])) : '';
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $explanations = isset($_POST['explanations']) ? json_decode(sanitize_textarea_field(wp_unslash($_POST['explanations'])), true) : array();
        
        if (empty($term_text)) {
            wp_send_json_error(array('message' => __('Term text is required', 'ai-explainer')));
        }
        
        if (empty($explanations) || !is_array($explanations)) {
            wp_send_json_error(array('message' => __('At least one explanation is required', 'ai-explainer')));
        }
        
        // Check if standard reading level is provided (mandatory)
        if (empty(trim($explanations['standard']))) {
            wp_send_json_error(array('message' => __('Standard reading level explanation is required', 'ai-explainer')));
        }
        
        if ($post_id <= 0) {
            wp_send_json_error(array('message' => __('Valid post ID is required', 'ai-explainer')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        
        // Generate text hash like the system does
        $text_hash = hash('sha256', strtolower(trim($term_text)));
        
        $saved_count = 0;
        $error_count = 0;
        $saved_levels = array();
        
        // Save explanations for each reading level for current post only
        foreach ($explanations as $reading_level => $explanation) {
            if (empty(trim($explanation))) {
                continue; // Skip empty explanations
            }
            
            $sanitized_explanation = wp_kses_post(trim($explanation));
            $sanitized_level = sanitize_text_field($reading_level);
            
            // Check if we already have a record for this term text, post, and reading level
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table_name WHERE text_hash = %s AND post_id = %d AND reading_level = %s",
                $text_hash,
                $post_id,
                $sanitized_level
            ));
            
            if ($existing) {
                // Update existing record
                $result = $wpdb->update(
                    $table_name,
                    array(
                        'ai_explanation' => $sanitized_explanation,
                        'manually_edited' => 1,
                        'enabled' => 1
                    ),
                    array('id' => $existing->id),
                    array('%s', '%d', '%d'),
                    array('%d')
                );
            } else {
                // Create new record
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'selected_text' => $term_text,
                        'text_hash' => $text_hash,
                        'post_id' => $post_id,
                        'reading_level' => $sanitized_level,
                        'ai_explanation' => $sanitized_explanation,
                        'manually_edited' => 1,
                        'enabled' => 1,
                        'selection_count' => 1,
                        'source_urls' => wp_json_encode(array()),
                        'source' => 'ai_scan'
                    ),
                    array('%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s')
                );
            }
            
            if ($result !== false) {
                $saved_count++;
                if (!in_array($sanitized_level, $saved_levels)) {
                    $saved_levels[] = $sanitized_level;
                }
            } else {
                $error_count++;
            }
        }
        
        if ($saved_count > 0) {
            $levels_count = count($saved_levels);
            
            $message = sprintf(
                /* translators: %1$s is the term text, %2$d is the number of reading levels */
                __('Term "%1$s" created successfully with %2$d reading level(s).', 'ai-explainer'),
                wp_trim_words($term_text, 5),
                $levels_count
            );
            
            if ($error_count > 0) {
                $message .= ' ' . sprintf(
                    /* translators: %d is the number of explanations that failed to save */
                    __('%d explanation(s) failed to save.', 'ai-explainer'),
                    $error_count
                );
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'saved_count' => $saved_count,
                'error_count' => $error_count,
                'saved_levels' => $saved_levels,
                'posts_updated' => $posts_updated
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to create term', 'ai-explainer'),
                'error_count' => $error_count
            ));
        }
    }
    
    /**
     * Output SEO metadata for plugin-created posts
     */
    public function output_seo_metadata() {
        // Check if debug mode is enabled
        $debug_mode = get_option('explainer_debug_mode', false);
        
        if ($debug_mode) {
            ExplainerPlugin_Debug_Logger::debug('SEO Metadata: Starting output_seo_metadata()', 'Admin');
        }
        
        // Only output if setting is enabled
        $setting_enabled = get_option('explainer_output_seo_metadata', true);
        if ($debug_mode) {
            ExplainerPlugin_Debug_Logger::debug('SEO Metadata: Setting enabled = ' . ($setting_enabled ? 'true' : 'false'), 'Admin');
        }
        if (!$setting_enabled) {
            if ($debug_mode) {
                ExplainerPlugin_Debug_Logger::debug('SEO Metadata: Exiting - setting disabled', 'Admin');
            }
            return;
        }
        
        // Only output on single posts
        $is_single = is_single();
        if ($debug_mode) {
            ExplainerPlugin_Debug_Logger::debug('SEO Metadata: is_single() = ' . ($is_single ? 'true' : 'false'), 'Admin');
        }
        if (!$is_single) {
            if ($debug_mode) {
                ExplainerPlugin_Debug_Logger::debug('SEO Metadata: Exiting - not a single post', 'Admin');
            }
            return;
        }
        
        global $post;
        if ($debug_mode) {
            ExplainerPlugin_Debug_Logger::debug('SEO Metadata: Post ID = ' . ($post ? $post->ID : 'null'), 'Admin');
        }
        
        if (!$post) {
            if ($debug_mode) {
                ExplainerPlugin_Debug_Logger::debug('SEO Metadata: Exiting - no post object', 'Admin');
            }
            return;
        }
        
        // Check if this post was created by our plugin
        $plugin_created = get_post_meta($post->ID, '_wp_ai_explainer_source_selection', true);
        if ($debug_mode) {
            ExplainerPlugin_Debug_Logger::debug('SEO Metadata: Plugin created check = ' . ($plugin_created ? '"' . $plugin_created . '"' : 'empty'));
        }
        if (empty($plugin_created)) {
            if ($debug_mode) {
                ExplainerPlugin_Debug_Logger::debug('SEO Metadata: Exiting - post not created by plugin', 'Admin');
            }
            return;
        }
        
        // Check if an SEO plugin is already handling this (avoid duplicate meta tags)
        $has_seo_plugin = $this->has_active_seo_plugin();
        if ($debug_mode) {
            ExplainerPlugin_Debug_Logger::debug('SEO Metadata: Active SEO plugin detected = ' . ($has_seo_plugin ? 'true' : 'false'));
        }
        
        // If SEO plugin is active, let it handle the metadata to avoid duplicates
        if ($has_seo_plugin) {
            if ($debug_mode) {
                ExplainerPlugin_Debug_Logger::debug('SEO Metadata: Skipping output - SEO plugin will handle meta tags', 'Admin');
            }
            return;
        }
        
        // Get all post meta to see what's available
        if ($debug_mode) {
            $all_meta = get_post_meta($post->ID);
            ExplainerPlugin_Debug_Logger::debug('SEO Metadata: All post meta keys: ' . implode(', ', array_keys($all_meta)));
            
            // Check for SEO-related meta
            $seo_meta_keys = array_filter(array_keys($all_meta), function($key) {
                return strpos($key, 'seo') !== false || strpos($key, 'explainer') !== false || strpos($key, 'meta_description') !== false;
            });
            if (!empty($seo_meta_keys)) {
                ExplainerPlugin_Debug_Logger::debug('SEO Metadata: SEO-related meta keys found: ' . implode(', ', $seo_meta_keys));
                foreach ($seo_meta_keys as $key) {
                    ExplainerPlugin_Debug_Logger::debug('SEO Metadata key value', 'admin', array('key' => $key, 'value' => $all_meta[$key]));
                }
            } else {
                ExplainerPlugin_Debug_Logger::debug('SEO Metadata: No SEO-related meta keys found', 'Admin');
            }
        }
        
        // Get the SEO metadata using our native keys
        $seo_title = get_post_meta($post->ID, '_wp_ai_explainer_seo_title', true);
        $seo_description = get_post_meta($post->ID, '_wp_ai_wp_ai_explainer_meta_description', true);
        $seo_keywords = get_post_meta($post->ID, '_wp_ai_explainer_keywords', true);
        
        if ($debug_mode) {
            ExplainerPlugin_Debug_Logger::debug('SEO Metadata: Retrieved values:', 'Admin');
            ExplainerPlugin_Debug_Logger::debug('SEO Metadata: - Title: "' . $seo_title . '"');
            ExplainerPlugin_Debug_Logger::debug('SEO Metadata: - Description: "' . $seo_description . '"');
            ExplainerPlugin_Debug_Logger::debug('SEO Metadata: - Keywords: "' . $seo_keywords . '"');
        }
        
        $output_count = 0;
        
        // Output SEO metadata if available
        if (!empty($seo_title)) {
            echo '<title>' . esc_html($seo_title) . '</title>' . "\n";
            $output_count++;
            if ($debug_mode) {
                ExplainerPlugin_Debug_Logger::debug('SEO Metadata: Outputted title tag', 'Admin');
            }
        }
        
        if (!empty($seo_description)) {
            echo '<meta name="description" content="' . esc_attr($seo_description) . '">' . "\n";
            $output_count++;
            if ($debug_mode) {
                ExplainerPlugin_Debug_Logger::debug('SEO Metadata: Outputted description meta tag', 'Admin');
            }
        }
        
        if (!empty($seo_keywords)) {
            echo '<meta name="keywords" content="' . esc_attr($seo_keywords) . '">' . "\n";
            $output_count++;
            if ($debug_mode) {
                ExplainerPlugin_Debug_Logger::debug('SEO Metadata: Outputted keywords meta tag', 'Admin');
            }
        }
        
        // Add a generator meta tag to identify our plugin
        if ($output_count > 0) {
            echo '<meta name="generator" content="AI Explainer ' . esc_attr(EXPLAINER_PLUGIN_VERSION) . '">' . "\n";
            if ($debug_mode) {
                ExplainerPlugin_Debug_Logger::debug('SEO Metadata: Outputted generator meta tag', 'Admin');
            }
        }
        
        if ($debug_mode) {
            ExplainerPlugin_Debug_Logger::debug('SEO Metadata: Finished - outputted ' . $output_count . ' meta tags');
        }
    }
    
    /**
     * Check if an active SEO plugin is detected (public method for template use)
     */
    public function has_active_seo_plugin() {
        $debug_mode = get_option('explainer_debug_mode', false);
        
        // Check for common SEO plugins
        $seo_plugins = array(
            'wordpress-seo/wp-seo.php'                     => 'Yoast SEO',
            'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
            'seo-by-rank-math/rank-math.php'              => 'Rank Math',
            'wp-seopress/seopress.php'                    => 'SEOPress',
            'autodescription/autodescription.php'         => 'The SEO Framework'
        );
        
        if ($debug_mode) {
            ExplainerPlugin_Debug_Logger::debug(' SEO Plugin Check: Checking for active SEO plugins', 'Admin');
        }
        
        foreach ($seo_plugins as $plugin_path => $plugin_name) {
            $is_active = is_plugin_active($plugin_path);
            if ($debug_mode) {
                ExplainerPlugin_Debug_Logger::debug(' SEO Plugin Check: ' . $plugin_name . ' (' . $plugin_path . ') = ' . ($is_active ? 'ACTIVE' : 'inactive'));
            }
            if ($is_active) {
                if ($debug_mode) {
                    ExplainerPlugin_Debug_Logger::debug(' SEO Plugin Check: Found active SEO plugin: ' . $plugin_name);
                }
                return true;
            }
        }
        
        if ($debug_mode) {
            ExplainerPlugin_Debug_Logger::debug(' SEO Plugin Check: No active SEO plugins detected', 'Admin');
        }
        
        return false;
    }
    
    /**
     * Get the name of the detected active SEO plugin
     */
    public function get_active_seo_plugin_name() {
        $seo_plugins = array(
            'wordpress-seo/wp-seo.php'                     => 'Yoast SEO',
            'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
            'seo-by-rank-math/rank-math.php'              => 'Rank Math',
            'wp-seopress/seopress.php'                    => 'SEOPress',
            'autodescription/autodescription.php'         => 'The SEO Framework'
        );
        
        foreach ($seo_plugins as $plugin_path => $plugin_name) {
            if (is_plugin_active($plugin_path)) {
                return $plugin_name;
            }
        }
        
        return null;
    }
    
    /**
     * Debug function to test if wp_head hook is working
     */
    public function debug_wp_head_hook() {
        if (get_option('explainer_debug_mode', false)) {
            ExplainerPlugin_Debug_Logger::debug(' wp_head hook is firing - Admin class is working', 'Admin');
            global $post;
            if ($post) {
                ExplainerPlugin_Debug_Logger::debug(' wp_head hook - Current post ID: ' . $post->ID);
                ExplainerPlugin_Debug_Logger::debug(' wp_head hook - Post type: ' . $post->post_type);
                ExplainerPlugin_Debug_Logger::debug(' wp_head hook - is_single(): ' . (is_single() ? 'true' : 'false'));
            } else {
                ExplainerPlugin_Debug_Logger::debug(' wp_head hook - No post object available', 'Admin');
            }
        }
    }
    
    /**
     * Debug admin options access to identify differences between logged in/out users
     */
    private function debug_admin_options_access() {
        // Skip if WordPress user functions aren't loaded yet
        if (!function_exists('is_user_logged_in') || !function_exists('get_current_user_id')) {
            ExplainerPlugin_Debug_Logger::warning('Admin debug skipped - WordPress user functions not loaded yet', 'Admin');
            return;
        }
        
        $user_id = get_current_user_id();
        $is_logged_in = is_user_logged_in();
        
        // Get all plugin options in admin context
        $admin_options = array(
            'explainer_api_provider' => get_option('explainer_api_provider', 'NOT_SET'),
            'explainer_openai_api_key' => get_option('explainer_openai_api_key', 'NOT_SET'),
            'explainer_claude_api_key' => get_option('explainer_claude_api_key', 'NOT_SET'),
            'explainer_api_model' => get_option('explainer_api_model', 'NOT_SET'),
            'explainer_custom_prompt' => get_option('explainer_custom_prompt', 'NOT_SET'),
            'explainer_language' => get_option('explainer_language', 'NOT_SET'),
            'explainer_enabled' => get_option('explainer_enabled', 'NOT_SET'),
            'explainer_cache_enabled' => get_option('explainer_cache_enabled', 'NOT_SET'),
            'explainer_cache_duration' => get_option('explainer_cache_duration', 'NOT_SET'),
            'explainer_rate_limit_enabled' => get_option('explainer_rate_limit_enabled', 'NOT_SET'),
            'explainer_rate_limit_logged' => get_option('explainer_rate_limit_logged', 'NOT_SET'),
            'explainer_rate_limit_anonymous' => get_option('explainer_rate_limit_anonymous', 'NOT_SET'),
            'explainer_debug_mode' => get_option('explainer_debug_mode', 'NOT_SET'),
        );
        
        ExplainerPlugin_Debug_Logger::debug('Admin Options Access Debug', 'Admin');
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('User ID: ' . $user_id, array(), 'admin');
        }
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('Is Logged In: ' . ($is_logged_in ? 'true' : 'false'), array(), 'admin');
        }
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('Current User Roles: ' . ($is_logged_in ? implode(', ', wp_get_current_user()->roles) : 'anonymous'), array(), 'admin');
        }
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('Admin Options Count: ' . count($admin_options), array(), 'admin');
        }
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('Admin Options', array('options' => $admin_options), 'admin');
        }
        
        // Check database directly from admin context
        global $wpdb;
        $db_check = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} 
             WHERE option_name LIKE 'explainer_%' 
             ORDER BY option_name",
            ARRAY_A
        );
        
        $db_options_admin = array();
        foreach ($db_check as $option) {
            $db_options_admin[$option['option_name']] = $option['option_value'];
        }
        
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('=== ADMIN DATABASE OPTIONS ===', array(), 'admin');
            explainer_log_debug('User ID: ' . $user_id, array(), 'admin');
        }
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('Database Options Found: ' . count($db_options_admin), array(), 'admin');
        }
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('Database Options', array('options' => $db_options_admin), 'admin');
        }
    }

    /**
     * Handle dismissing getting started help section
     */
    public function handle_dismiss_getting_started() {
        check_ajax_referer('explainer_dismiss_getting_started', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }

        update_user_meta(get_current_user_id(), 'explainer_getting_started_dismissed', true);

        wp_send_json_success();
    }

    /**
     * Handle AJAX request to get jobs list
     */
    public function handle_get_jobs() {
        // Verify nonce
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        try {
            // Use new job queue system via migration helper
            require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-job-queue-migration.php';

            // Get job type filter from request
            $job_type_filter = isset($_POST['job_type_filter']) ? sanitize_text_field(wp_unslash($_POST['job_type_filter'])) : '';
            
            // Get jobs using the new system (formatted for legacy compatibility)
            $jobs = \WPAIExplainer\JobQueue\ExplainerPlugin_Job_Queue_Migration::get_jobs_legacy_format($job_type_filter, 100);
            
            // Format jobs for the frontend
            $formatted_jobs = array();
            $position = 1;
            
            foreach ($jobs as $job) {
                
                // Decode JSON data from migration helper
                $job_data = is_string($job->options) ? json_decode($job->options, true) : $job->options;
                $result_data = is_string($job->result_data) ? json_decode($job->result_data, true) : $job->result_data;
                
                // Extract title from completed jobs
                $title = 'Blog Post Creation';
                if ($job->status === 'completed') {
                    // Try to get title from job metadata
                    global $wpdb;
                    $meta_table = $wpdb->prefix . 'ai_explainer_job_meta';
                    
                    // First check for 'result' metadata which should contain the widget result with title
                    $result_meta = $wpdb->get_var($wpdb->prepare(
                        "SELECT meta_value FROM $meta_table WHERE queue_id = %d AND meta_key = 'result'",
                        $job->id
                    ));
                    
                    if ($result_meta) {
                        $widget_result = maybe_unserialize($result_meta);
                        if (is_array($widget_result) && !empty($widget_result['title'])) {
                            $title = $widget_result['title'];
                        }
                    }
                    
                    // Fallback: get title from WordPress post if we still don't have one
                    if ($title === 'Blog Post Creation') {
                        $post_id_meta = $wpdb->get_var($wpdb->prepare(
                            "SELECT meta_value FROM $meta_table WHERE queue_id = %d AND meta_key = 'post_id'",
                            $job->id
                        ));
                        
                        if ($post_id_meta) {
                            $post_id = maybe_unserialize($post_id_meta);
                            if ($post_id) {
                                $post_title = get_the_title($post_id);
                                if ($post_title) {
                                    $title = $post_title;
                                }
                            }
                        }
                    }
                }
                
                $formatted_job = array(
                    'job_id' => $job->job_id,
                    'status' => $job->status,
                    'title' => $title,
                    'status_text' => ucfirst($job->status),
                    'selection_text' => $job->selection_text,
                    'selection_preview' => wp_trim_words($job->selection_text, 10, '...'),
                    'explanation_preview' => wp_trim_words($job->selection_text, 20, '...')
                        ? wp_trim_words($job->selection_text, 20, '...') 
                        : __('No explanation available', 'ai-explainer'),
                    'ai_provider' => $job_data['ai_provider'] ?? 'unknown',
                    'post_length' => $job_data['length'] ?? 'medium',
                    'created_at' => wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($job->created_at)),
                    'updated_at' => wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($job->updated_at)),
                    'started_at' => $job->started_at ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($job->started_at)) : null,
                    'completed_at' => $job->completed_at ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($job->completed_at)) : null,
                    'error_message' => $job->error_message,
                    'retry_count' => (int)$job->retry_count,
                    'priority' => (int)$job->priority,
                    'cost' => 0,
                    'edit_link' => $job->edit_link ?? '',
                    'preview_link' => $job->preview_link ?? '',
                    'view_link' => '',
                    'queue_position' => $job->status === 'pending' ? $position : 0,
                    'progress_percent' => $this->get_job_progress_percent($job),
                    'progress_text' => $this->get_job_progress_text($job),
                    'completed_message' => $job->status === 'completed' ? __('Blog post created successfully!', 'ai-explainer') : ''
                );
                
                // Add result data for completed jobs
                if ($job->status === 'completed' && $result_data) {
                    $formatted_job['cost'] = number_format($result_data['cost'] ?? 0, 3);
                }
                
                // Increment position counter for pending jobs
                if ($job->status === 'pending') {
                    $position++;
                }
                
                $formatted_jobs[] = $formatted_job;
            }
            
            // Send jobs array directly as response.data for frontend compatibility
            wp_send_json_success($formatted_jobs);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Error loading jobs: ', 'ai-explainer') . $e->getMessage()));
        }
    }
    
    /**
     * Handle AJAX request to cancel a job
     */
    public function handle_cancel_job() {
        // Verify nonce
        check_ajax_referer('explainer_admin_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }

        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';

        if (empty($job_id)) {
            wp_send_json_error(array('message' => __('Invalid job ID', 'ai-explainer')));
        }

        try {
            // Use migration helper
            require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-job-queue-migration.php';

            $success = \WPAIExplainer\JobQueue\ExplainerPlugin_Job_Queue_Migration::cancel_job($job_id);
            
            if ($success) {
                wp_send_json_success(array('message' => __('Job cancelled successfully', 'ai-explainer')));
            } else {
                wp_send_json_error(array('message' => __('Failed to cancel job', 'ai-explainer')));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Error cancelling job: ', 'ai-explainer') . $e->getMessage()));
        }
    }
    
    /**
     * Handle AJAX request to retry a failed job
     */
    public function handle_retry_job() {
        // Verify nonce
        check_ajax_referer('explainer_admin_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }

        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';

        if (empty($job_id)) {
            wp_send_json_error(array('message' => __('Invalid job ID', 'ai-explainer')));
        }

        try {
            // Use migration helper
            require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-job-queue-migration.php';

            $success = \WPAIExplainer\JobQueue\ExplainerPlugin_Job_Queue_Migration::retry_job($job_id);
            
            if ($success) {
                // Simplified architecture - no background processing needed
                // Jobs will be executed directly via manual triggers
                
                wp_send_json_success(array('message' => __('Job queued for retry', 'ai-explainer')));
            } else {
                wp_send_json_error(array('message' => __('Failed to retry job', 'ai-explainer')));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Error retrying job: ', 'ai-explainer') . $e->getMessage()));
        }
    }
    
    /**
     * Handle AJAX request to clear completed jobs
     */
    public function handle_clear_completed_jobs() {
        // Verify nonce
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        try {
            // Use migration helper
            require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-job-queue-migration.php';
            
            $cleared_count = \WPAIExplainer\JobQueue\ExplainerPlugin_Job_Queue_Migration::clear_completed_jobs();

            wp_send_json_success(array(
                'message' => sprintf(
                    /* translators: %d: Number of jobs cleared */
                    __('%d completed jobs cleared successfully', 'ai-explainer'),
                    $cleared_count
                ),
                'cleared_count' => $cleared_count
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Error clearing jobs: ', 'ai-explainer') . $e->getMessage()));
        }
    }

    /**
     * Handle AJAX request to run a single job manually
     */
    /**
     * Handle manual job execution with direct execution (simplified architecture)
     * 
     * This method implements the simplified architecture by executing jobs directly
     * through the Job Queue Manager, ensuring all status updates fire webhooks for SSE.
     */
    public function handle_run_single_job() {
        ExplainerPlugin_Debug_Logger::debug('handle_run_single_job called - simplified architecture', 'admin');
        
        // Verify nonce and permissions
        check_ajax_referer('explainer_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        
        if (empty($job_id)) {
            wp_send_json_error(array('message' => __('Invalid job ID', 'ai-explainer')));
        }

        ExplainerPlugin_Debug_Logger::debug("Processing job via simplified architecture: $job_id", 'Admin');

        // Check if cron is enabled - only allow manual execution when cron is disabled
        $cron_enabled = get_option('explainer_enable_cron', false);
        if ($cron_enabled) {
            wp_send_json_error(array('message' => __('Manual job execution is not available when cron is enabled', 'ai-explainer')));
        }

        // Simple lock management with auto-cleanup
        $lock_key = 'explainer_manual_job_lock';
        $existing_lock = get_transient($lock_key);
        
        if ($existing_lock) {
            // Check if there's actually a job processing
            global $wpdb;
            $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
            $processing_jobs = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$queue_table} WHERE status = %s",
                'processing'
            ));
            
            if ($processing_jobs == 0) {
                // No jobs actually processing, clear stale lock
                delete_transient($lock_key);
                ExplainerPlugin_Debug_Logger::debug('Cleared stale lock', 'Admin');
            } else {
                wp_send_json_error(array('message' => __('Another job is currently running. Please wait for it to complete.', 'ai-explainer')));
            }
        }

        // Set lock with reasonable timeout (5 minutes)
        set_transient($lock_key, $job_id, 300);
        ExplainerPlugin_Debug_Logger::debug('Set job execution lock', 'Admin');

        try {
            // Load job queue system (simplified architecture)
            $job_manager = ExplainerPlugin_Job_Queue_Manager::get_instance();
            
            // Convert job ID to queue ID
            $queue_id = $this->convert_job_id_to_queue_id($job_id);
            if (!$queue_id) {
                delete_transient($lock_key);
                wp_send_json_error(array('message' => __('Invalid job ID format', 'ai-explainer')));
            }
            
            ExplainerPlugin_Debug_Logger::debug("Simplified Architecture: Direct execution for queue_id: $queue_id", 'Admin');
            
            // Set longer execution time for job processing
            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Required for long-running job processing
            @set_time_limit(300); // 5 minutes
            // phpcs:ignore WordPress.PHP.IniSet.Risky -- Required for memory-intensive job processing
            @ini_set('memory_limit', '256M');
            
            // Direct execution with guaranteed status updates that fire webhooks
            explainer_log_debug("About to update job status to processing", array('queue_id' => $queue_id), 'Admin');
            $job_manager->update_job_status($queue_id, 'processing');
            
            // Add a brief delay to make PROCESSING phase more visible (2-3 seconds)
            sleep(2);
            
            explainer_log_debug("About to call execute_job_directly", array('queue_id' => $queue_id, 'job_manager_class' => get_class($job_manager)), 'Admin');
            
            try {
                $result = $job_manager->execute_job_directly($queue_id);
                explainer_log_debug("execute_job_directly returned", array('result' => $result), 'Admin');
                // execute_job_directly handles status updates internally for normal execution
                explainer_log_debug("execute_job_directly handles status updates internally", array('queue_id' => $queue_id, 'success' => $result['success']), 'Admin');
            } catch (Error $e) {
                explainer_log_error("Fatal error in execute_job_directly", array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()), 'Admin');
                $result = array('success' => false, 'message' => 'Fatal error: ' . $e->getMessage());
                // For fatal errors caught here, we need to update the status since execute_job_directly didn't complete
                explainer_log_debug("Updating job status for fatal error", array('queue_id' => $queue_id), 'Admin');
                $job_manager->update_job_status($queue_id, 'failed', $result);
            } catch (Exception $e) {
                explainer_log_error("Exception in execute_job_directly", array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString()), 'Admin');
                $result = array('success' => false, 'message' => 'Exception: ' . $e->getMessage());
                // For exceptions caught here, we need to update the status since execute_job_directly didn't complete
                explainer_log_debug("Updating job status for exception", array('queue_id' => $queue_id), 'Admin');
                $job_manager->update_job_status($queue_id, 'failed', $result);
            }
            
            // Clean up lock
            delete_transient($lock_key);
            
            ExplainerPlugin_Debug_Logger::debug("Simplified job execution completed: " . ($result['success'] ? 'SUCCESS' : 'FAILED'), 'Admin');
            
            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => __('Job completed successfully', 'ai-explainer'),
                    'job_id' => $job_id,
                    'status' => 'completed',
                    'result' => $result
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $result['message'] ?? __('Job execution failed', 'ai-explainer'),
                    'job_id' => $job_id,
                    'status' => 'failed'
                ));
            }
            
        } catch (Exception $e) {
            // Clean up lock on error
            delete_transient($lock_key);
            
            if (isset($queue_id)) {
                $job_manager->update_job_status($queue_id, 'failed', array('error' => $e->getMessage()));
            }
            
            ExplainerPlugin_Debug_Logger::error("Simplified job execution failed: " . $e->getMessage(), 'Admin');
            wp_send_json_error(array('message' => __('Job execution failed: ', 'ai-explainer') . $e->getMessage()));
        }
    }
    
    /**
     * Convert job ID to queue ID
     * 
     * @param string $job_id The job ID to convert
     * @return int|false The queue ID or false on failure
     */
    private function convert_job_id_to_queue_id($job_id) {
        if (strpos($job_id, 'jq_') === 0) {
            return intval(substr($job_id, 3));
        } else {
            // Try to find job by old ID in meta table
            global $wpdb;
            $meta_table = $wpdb->prefix . 'ai_explainer_job_meta';
            $found_queue_id = $wpdb->get_var($wpdb->prepare(
                "SELECT queue_id FROM $meta_table WHERE meta_key = '_old_job_id' AND meta_value = %s",
                $job_id
            ));
            
            return $found_queue_id ? intval($found_queue_id) : false;
        }
    }
    
    
    /**
     * Handle API key testing via AJAX
     */
    public function handle_test_api_key() {
        // Check nonce for security
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in same line
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-explainer')));
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-explainer')));
        }

        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';

        if (empty($provider) || empty($api_key)) {
            wp_send_json_error(array('message' => __('Provider and API key are required.', 'ai-explainer')));
        }

        try {
            // Get provider instance
            $provider_instance = ExplainerPlugin_Provider_Factory::get_provider($provider);
            if (!$provider_instance) {
                wp_send_json_error(array('message' => __('Invalid provider specified.', 'ai-explainer')));
            }
            
            // Test the API key
            $test_result = $provider_instance->test_api_key($api_key);
            
            if ($test_result['success']) {
                wp_send_json_success(array('message' => $test_result['message']));
            } else {
                wp_send_json_error(array('message' => $test_result['message']));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Test failed: ', 'ai-explainer') . $e->getMessage()));
        }
    }
    
    /**
     * Handle testing stored API key via AJAX
     */
    public function handle_test_stored_api_key() {
        // Check nonce for security
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in same line
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-explainer')));
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-explainer')));
        }

        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : '';

        if (empty($provider)) {
            wp_send_json_error(array('message' => __('Provider is required.', 'ai-explainer')));
        }

        try {
            // Get the stored/encrypted API key for this provider
            $api_proxy = new ExplainerPlugin_API_Proxy();
            $stored_api_key = $api_proxy->get_decrypted_api_key_for_provider($provider);

            if (empty($stored_api_key)) {
                wp_send_json_error(array('message' => __('No API key found for this provider.', 'ai-explainer')));
            }
            
            // Get provider instance
            $provider_instance = ExplainerPlugin_Provider_Factory::get_provider($provider);
            if (!$provider_instance) {
                wp_send_json_error(array('message' => __('Invalid provider specified.', 'ai-explainer')));
            }
            
            // Test the stored API key
            $test_result = $provider_instance->test_api_key($stored_api_key);
            
            if ($test_result['success']) {
                wp_send_json_success(array('message' => $test_result['message']));
            } else {
                wp_send_json_error(array('message' => $test_result['message']));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Test failed: ', 'ai-explainer') . $e->getMessage()));
        }
    }
    
    /**
     * Handle API key deletion via AJAX
     */
    public function handle_delete_api_key() {
        // Check nonce for security
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in same line
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-explainer')));
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-explainer')));
        }

        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : '';

        if (empty($provider)) {
            wp_send_json_error(array('message' => __('Provider is required.', 'ai-explainer')));
        }

        // Map provider to option name (use correct option names from AI provider registry)
        $option_map = array(
            'openai' => 'explainer_openai_api_key',
            'claude' => 'explainer_claude_api_key',
            'openrouter' => 'explainer_openrouter_api_key',
            'gemini' => 'explainer_gemini_api_key'
        );

        if (!isset($option_map[$provider])) {
            wp_send_json_error(array('message' => __('Invalid provider specified.', 'ai-explainer')));
        }
        
        // Delete the API key
        // Always try to delete, but consider it successful if option doesn't exist
        $option_value = get_option($option_map[$provider]);
        
        if ($option_value === false) {
            // Option doesn't exist, already "deleted"
            wp_send_json_success(array('message' => __('API key deleted successfully.', 'ai-explainer')));
        } else {
            // Option exists, delete it
            $deleted = delete_option($option_map[$provider]);
            
            if ($deleted || get_option($option_map[$provider]) === false) {
                // Successfully deleted or no longer exists
                wp_send_json_success(array('message' => __('API key deleted successfully.', 'ai-explainer')));
            } else {
                wp_send_json_error(array('message' => __('Failed to delete API key.', 'ai-explainer')));
            }
        }
    }
    
    /**
     * Handle provider configuration request via AJAX
     * Provides dynamic provider data to eliminate JavaScript hardcoding
     */
    public function handle_get_provider_config() {
        // Check nonce for security
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in same line
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-explainer')));
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-explainer')));
        }
        
        try {
            // Use centralized provider configuration class
            $config = ExplainerPlugin_Provider_Config::get_provider_config();
            wp_send_json_success($config);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Failed to load provider configuration.', 'ai-explainer')));
        }
    }

    /**
     * Handle post search AJAX request
     */
    public function handle_search_posts() {
        // Verify nonce
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in same line
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_settings_nonce')) {
            wp_send_json_error(__('Security check failed.', 'ai-explainer'));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ai-explainer'));
            return;
        }

        $search_term = isset($_POST['search_term']) ? sanitize_text_field(wp_unslash($_POST['search_term'])) : '';

        if (empty($search_term) || strlen($search_term) < 2) {
            wp_send_json_error(__('Search term must be at least 2 characters.', 'ai-explainer'));
            return;
        }

        if (strlen($search_term) > 100) {
            wp_send_json_error(__('Search term too long.', 'ai-explainer'));
            return;
        }
        
        // Simple rate limiting - only allow 30 searches per user per minute
        $user_id = get_current_user_id();
        $rate_limit_key = "explainer_search_limit_{$user_id}";
        $current_searches = get_transient($rate_limit_key);
        
        if ($current_searches >= 30) {
            wp_send_json_error(__('Search rate limit exceeded. Please wait a minute.', 'ai-explainer'));
            return;
        }
        
        set_transient($rate_limit_key, ($current_searches ?: 0) + 1, 60);
        
        // Use custom SQL query for better partial matching
        global $wpdb;
        
        $like_term = '%' . $wpdb->esc_like($search_term) . '%';
        $post_ids = $wpdb->get_col($wpdb->prepare("
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE (post_title LIKE %s OR post_content LIKE %s)
            AND post_type IN ('post', 'page') 
            AND post_status IN ('publish', 'draft')
            AND post_password = ''
            ORDER BY 
                CASE WHEN post_title LIKE %s THEN 1 ELSE 2 END,
                post_date DESC
            LIMIT 10
        ", $like_term, $like_term, $like_term));
        
        // Debug logging
        ExplainerPlugin_Debug_Logger::debug('Post search results for term: ' . $search_term, array(
            'search_term' => $search_term,
            'like_term' => $like_term,
            'found_post_ids' => $post_ids,
            'count' => count($post_ids)
        ));
        
        if (empty($post_ids)) {
            wp_send_json_success(array());
            return;
        }
        
        // Get full post objects only for the IDs we need
        $posts = get_posts(array(
            'post__in' => $post_ids,
            'post_type' => array('post', 'page'),
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => 10,
            'orderby' => 'post__in'
        ));
        $results = array();
        
        foreach ($posts as $post) {
            $status = $this->determine_post_status($post->ID);
            
            $results[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'status' => $status,
                'edit_link' => get_edit_post_link($post->ID),
                'permalink' => get_permalink($post->ID)
            );
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Handle get processed posts AJAX request
     */
    public function handle_get_processed_posts() {
        // Verify nonce
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ai-explainer'));
            return;
        }
        
        $page = max(1, intval($_POST['page'] ?? 1));
        $sort_by = sanitize_key($_POST['sort_by'] ?? 'processed_date');
        $sort_order = sanitize_key($_POST['sort_order'] ?? 'desc');
        $per_page = 20;
        
        global $wpdb;
        
        // Get processed posts from job queue
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        $selections_table = $wpdb->prefix . 'ai_explainer_selections';
        
        $offset = ($page - 1) * $per_page;
        
        // Build ORDER BY clause with proper sanitization
        $allowed_sort_fields = array(
            'title' => 'p.post_title',
            'term_count' => 'term_count',
            'processed_date' => 'completed_at'
        );
        
        $allowed_sort_orders = array('asc' => 'ASC', 'desc' => 'DESC');
        
        // Sanitize sort parameters
        $sort_field = isset($allowed_sort_fields[$sort_by]) ? $allowed_sort_fields[$sort_by] : $allowed_sort_fields['processed_date'];
        $sort_direction = isset($allowed_sort_orders[$sort_order]) ? $allowed_sort_orders[$sort_order] : 'DESC';
        
        $order_clause = "{$sort_field} {$sort_direction}";
        
        // Build complete query with safe ORDER BY clause
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and order clause are safely constructed
        $query = "
            SELECT
                p.ID,
                p.post_title,
                MAX(q.completed_at) as completed_at,
                'completed' as status,
                MIN(q.created_at) as created_at,
                COUNT(DISTINCT s.id) as term_count
            FROM {$wpdb->posts} p
            INNER JOIN {$queue_table} q ON q.data LIKE CONCAT('%s:7:\"post_id\";i:', p.ID, ';%')
            LEFT JOIN {$selections_table} s ON s.post_id = p.ID AND s.source = 'ai_scan'
            WHERE q.job_type = 'ai_term_scan'
            AND q.status = 'completed'
            AND p.post_status = 'publish'
            GROUP BY p.ID, p.post_title
            ORDER BY {$order_clause}
            LIMIT {$per_page} OFFSET {$offset}
        ";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is safely constructed with escaped values
        $posts = $wpdb->get_results($query);

        // Get total count for pagination
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are safely constructed
        $total_query = "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$queue_table} q ON q.data LIKE CONCAT('%s:7:\"post_id\";i:', p.ID, ';%')
            WHERE q.job_type = 'ai_term_scan'
            AND q.status = 'completed'
            AND p.post_status = 'publish'
        ";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is safely constructed with escaped values
        $total_posts = $wpdb->get_var($total_query);
        $total_pages = ceil($total_posts / $per_page);
        
        // Format results
        $results = array();
        foreach ($posts as $post) {
            // Use appropriate date based on status
            $display_date = $post->status === 'completed' && $post->completed_at 
                ? mysql2date('M j, Y g:i a', $post->completed_at)
                : mysql2date('M j, Y g:i a', $post->created_at);
                
            // Map database status to display status
            $display_status = $post->status;
            if ($post->status === 'pending') {
                $display_status = 'queued';
            }
            
            $results[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'processed_date' => $display_date,
                'status' => $display_status,
                'term_count' => intval($post->term_count),
                'edit_link' => get_edit_post_link($post->ID),
                'view_link' => get_permalink($post->ID)
            );
        }
        
        // Build pagination
        $pagination = '';
        if ($total_pages > 1) {
            $pagination = paginate_links(array(
                'base' => '#',
                'format' => '',
                'current' => $page,
                'total' => $total_pages,
                'type' => 'list'
            ));
        }
        
        wp_send_json_success(array(
            'posts' => $results,
            'pagination' => $pagination,
            'total' => $total_posts,
            'page' => $page,
            'total_pages' => $total_pages
        ));
    }
    
    /**
     * Handle process post AJAX request
     */
    public function handle_process_post() {
        // Verify nonce
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in same line
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_settings_nonce')) {
            wp_send_json_error(__('Security check failed.', 'ai-explainer'));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ai-explainer'));
            return;
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if ($post_id <= 0) {
            wp_send_json_error(__('Invalid post ID.', 'ai-explainer'));
            return;
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(__('Post not found.', 'ai-explainer'));
            return;
        }
        
        // Create queue job for AI term scan using new job queue system
        $queue_manager = ExplainerPlugin_Job_Queue_Manager::get_instance();
        
        $job_data = array(
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_content' => $post->post_content,
            'scan_type' => 'ai_term_scan'
        );
        
        $job_id = $queue_manager->queue_job(
            'ai_term_scan',
            $job_data
        );
        
        if ($job_id) {
            wp_send_json_success(array(
                'message' => __('Post queued for processing.', 'ai-explainer'),
                'job_id' => $job_id,
                'post_title' => $post->post_title,
                'post_id' => $post_id,
                'job_type' => 'ai_term_scan'
            ));
        } else {
            wp_send_json_error(__('Failed to queue post for processing.', 'ai-explainer'));
        }
    }
    
    /**
     * Handle get post terms AJAX request
     */
    public function handle_get_post_terms() {
        // Verify nonce
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in same line
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_settings_nonce')) {
            wp_send_json_error(__('Security check failed.', 'ai-explainer'));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'ai-explainer'));
            return;
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if ($post_id <= 0) {
            wp_send_json_error(__('Invalid post ID.', 'ai-explainer'));
            return;
        }
        
        // Get terms for this post using direct query
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        $direct_results = $wpdb->get_results($wpdb->prepare(
            "SELECT selected_text, ai_explanation, first_seen, last_seen, enabled, selection_count 
             FROM $table_name 
             WHERE post_id = %d AND source = 'ai_scan'
             ORDER BY selection_count DESC, last_seen DESC 
             LIMIT 100",
            $post_id
        ), ARRAY_A);
        
        $results = array();
        foreach ($direct_results as $selection) {
            $results[] = array(
                'selected_text' => $selection['selected_text'],
                'explanation' => $selection['ai_explanation'] ?? '',
                'enabled' => $selection['enabled'],
                'selection_count' => $selection['selection_count'],
                'created_at' => mysql2date('M j, Y g:i a', $selection['first_seen'] ?? $selection['last_seen'])
            );
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Handle AJAX request to cancel a post scan
     */
    public function handle_cancel_post_scan() {
        // Verify nonce
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in same line
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'cancel_post_scan')) {
            wp_send_json_error(__('Security check failed.', 'ai-explainer'));
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions.', 'ai-explainer'));
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';

        if ($post_id <= 0 || empty($job_id)) {
            wp_send_json_error(__('Invalid post ID or job ID.', 'ai-explainer'));
            return;
        }
        
        // Use new job queue system
        $queue_manager = ExplainerPlugin_Job_Queue_Manager::get_instance();
        
        // Cancel the job
        $cancelled = $queue_manager->cancel_job(str_replace('jq_', '', $job_id));
        
        if ($cancelled) {
            // Remove the scan job ID from post meta
            delete_post_meta($post_id, '_wp_ai_explainer_scan_job_id');
            
            wp_send_json_success(array(
                'message' => __('Post scan cancelled successfully.', 'ai-explainer')
            ));
        } else {
            wp_send_json_error(__('Failed to cancel post scan. The job may have already completed.', 'ai-explainer'));
        }
    }
    
    /**
     * Handle AJAX request to get post scan queue status
     */
    public function handle_get_post_scan_queue_status() {
        // Verify nonce
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }
        
        global $wpdb;
        
        // Get posts currently in the scan queue from new job queue table
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        
        // Get queued jobs with ai_term_scan job type
        $queued_jobs = $wpdb->get_results($wpdb->prepare("
            SELECT 
                queue_id as job_id,
                status,
                created_at,
                updated_at,
                data as options,
                '' as result_data,
                error_message
            FROM {$queue_table}
            WHERE status IN ('pending', 'processing')
            AND job_type = %s
            ORDER BY created_at ASC
            LIMIT 50
        ", 'ai_term_scan'));
        
        $formatted_jobs = array();
        $position = 1;
        
        // Process each job to extract post information and format for frontend
        foreach ($queued_jobs as $job) {
            // Try to decode as JSON first, then fall back to maybe_unserialize
            $options = json_decode($job->options, true);
            if (!$options) {
                $options = maybe_unserialize($job->options);
            }
            $post_id = isset($options['post_id']) ? intval($options['post_id']) : 0;
            
            if ($post_id > 0) {
                $post = get_post($post_id);
                $post_title = $post ? $post->post_title : __('(Unknown Post)', 'ai-explainer');
                
                $formatted_jobs[] = array(
                    'job_id' => 'jq_' . $job->job_id,
                    'status' => $job->status,
                    'title' => $post_title,
                    'status_text' => ucfirst($job->status),
                    'created_at' => wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($job->created_at)),
                    'completed_at' => $job->status === 'completed' && !empty($job->updated_at) ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($job->updated_at)) : '',
                    'queue_position' => $position,
                    'error_message' => $job->error_message,
                    'progress_percent' => $this->get_job_progress_percent($job),
                    'progress_text' => $this->get_job_progress_text($job),
                    'completed_message' => $job->status === 'completed' ? __('Post scan completed successfully!', 'ai-explainer') : '',
                    'edit_link' => $post ? get_edit_post_link($post_id) : '',
                    'preview_link' => '',
                    'view_link' => $post ? get_permalink($post_id) : ''
                );
                $position++;
            }
        }
        
        ExplainerPlugin_Debug_Logger::debug(' handle_get_jobs sending ' . count($formatted_jobs) . ' formatted jobs');
        wp_send_json_success($formatted_jobs);
    }
    
    /**
     * Get job progress percentage
     * 
     * @param object $job Job object
     * @return int Progress percentage (0-100)
     */
    private function get_job_progress_percent($job) {
        switch ($job->status) {
            case 'pending':
                return 0;
            case 'processing':
                // Calculate time-based progress for processing jobs
                if (!empty($job->updated_at)) {
                    $processing_time = strtotime('now') - strtotime($job->updated_at);
                    // Estimate progress based on processing time (max 90% to avoid 100% while still processing)
                    $estimated_progress = min(90, 30 + ($processing_time / 60) * 30); // Start at 30%, add 30% per minute
                    return (int) round($estimated_progress);
                }
                return 50; // Default processing progress
            case 'completed':
                return 100;
            case 'failed':
                return 0;
            default:
                return 0;
        }
    }
    
    /**
     * Get job progress text
     * 
     * @param object $job Job object
     * @return string Progress text
     */
    private function get_job_progress_text($job) {
        // First, check if there's a custom progress message stored in job meta
        if ($job->status === 'processing') {
            global $wpdb;
            $meta_table = $wpdb->prefix . 'ai_explainer_job_meta';
            
            $custom_progress_text = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$meta_table} WHERE queue_id = %d AND meta_key = 'progress_text'",
                $job->queue_id
            ));
            
            // Debug log
            if (function_exists('explainer_log_debug')) {
                explainer_log_debug('Get Progress Text', array(
                    'job_id' => $job->queue_id,
                    'status' => $job->status,
                    'custom_text' => $custom_progress_text ? $custom_progress_text : 'none'
                ), 'admin');
            }
            
            if ($custom_progress_text) {
                return $custom_progress_text;
            }
        }
        
        // Fall back to default time-based progress messages
        switch ($job->status) {
            case 'pending':
                return '';
            case 'processing':
                // Provide different messages based on job type
                if ($job->job_type === 'ai_term_scan') {
                    // Calculate more specific progress text for term scanning
                    if (!empty($job->updated_at)) {
                        $processing_time = strtotime('now') - strtotime($job->updated_at);
                        if ($processing_time < 10) {
                            return __('Analysing post content...', 'ai-explainer');
                        } elseif ($processing_time < 30) {
                            return __('Extracting technical terms...', 'ai-explainer');
                        } elseif ($processing_time < 60) {
                            return __('Generating explanations...', 'ai-explainer');
                        } else {
                            return __('Finalising term extraction...', 'ai-explainer');
                        }
                    }
                    return __('Scanning post for terms...', 'ai-explainer');
                } else {
                    // Blog creation job
                    if (!empty($job->updated_at)) {
                        $processing_time = strtotime('now') - strtotime($job->updated_at);
                        if ($processing_time < 15) {
                            return __('Analysing source text...', 'ai-explainer');
                        } elseif ($processing_time < 30) {
                            return __('Generating content...', 'ai-explainer');
                        } elseif ($processing_time < 60) {
                            return __('Creating blog post...', 'ai-explainer');
                        } else {
                            return __('Finalising post creation...', 'ai-explainer');
                        }
                    }
                    return __('Creating blog post...', 'ai-explainer');
                }
            case 'completed':
            case 'failed':
            default:
                return '';
        }
    }
    
    
    
    /**
     * Determine post processing status based on queue and modification date
     */
    private function determine_post_status($post_id) {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        
        // Get latest job for this post - using serialized data pattern
        $latest_job = $wpdb->get_row($wpdb->prepare("
            SELECT status, completed_at, data
            FROM {$queue_table}
            WHERE job_type = 'ai_term_scan'
            AND data LIKE %s
            ORDER BY created_at DESC
            LIMIT 1
        ", '%s:7:"post_id";i:' . intval($post_id) . ';%'));
        
        if (!$latest_job) {
            return 'not_scanned';
        }
        
        switch ($latest_job->status) {
            case 'pending':
                return 'queued';
            case 'processing':
                return 'processing';
            case 'failed':
                return 'error';
            case 'completed':
                // Check if post was modified after completion
                $post_modified = get_post_modified_time('Y-m-d H:i:s', true, $post_id);
                $job_completed = $latest_job->completed_at;

                if ($post_modified > $job_completed) {
                    return 'outdated';
                } else {
                    return 'processed';
                }
            default:
                return 'not_scanned';
        }
    }
    
    /**
     * Get post scan status (public wrapper for determine_post_status)
     */
    public function get_post_scan_status($post_id) {
        return $this->determine_post_status($post_id);
    }
    
    /**
     * Register meta box for AI explanations
     */
    public function add_meta_boxes() {
        // Only show to users who can manage options
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Only show on posts and pages
        $post_types = array('post', 'page');

        foreach ($post_types as $post_type) {
            add_meta_box(
                'ai-explanations-meta-box',
                __('AI Explanations', 'ai-explainer'),
                array($this, 'meta_box_callback'),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Meta box callback function
     */
    public function meta_box_callback($post) {
        // Add nonce for security
        wp_nonce_field('ai_explanations_meta_box', 'ai_explanations_meta_box_nonce');
        
        // Get current post meta values
        $scan_enabled = get_post_meta($post->ID, '_wp_ai_explainer_scan_enabled', true);
        $job_id = get_post_meta($post->ID, '_wp_ai_explainer_scan_job_id', true);
        $last_scan_date = get_post_meta($post->ID, '_wp_ai_explainer_scan_completed', true);
        $term_count = get_post_meta($post->ID, '_wp_ai_explainer_scan_terms_count', true);
        
        // Determine current status
        $status = $this->get_post_scan_status($post->ID);
        
        // Include meta box template
        include EXPLAINER_PLUGIN_PATH . 'templates/meta-box-ai-explanations.php';
    }
    
    /**
     * Save meta box data
     */
    public function save_meta_box_data($post_id) {
        // Verify nonce
        if (!isset($_POST['ai_explanations_meta_box_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ai_explanations_meta_box_nonce'])), 'ai_explanations_meta_box')) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Skip auto saves
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Save frontend display preference
        $scan_enabled = isset($_POST['explainer_scan_enabled']) ? 1 : 0;
        update_post_meta($post_id, '_wp_ai_explainer_scan_enabled', $scan_enabled);
        
        // Handle queue this post checkbox - check both existence and value
        $queue_post = isset($_POST['explainer_queue_post']) && $_POST['explainer_queue_post'] === '1' ? 1 : 0;

        if ($queue_post) {
            $this->queue_single_post($post_id);
        } else {
            $this->dequeue_single_post($post_id);
        }
    }
    
    /**
     * Queue a single post for AI scan
     */
    private function queue_single_post($post_id) {
        // Check if already processed or queued
        $status = $this->get_post_scan_status($post_id);

        // Only prevent re-queuing posts that are currently queued or being processed
        // Allow re-queuing of 'processed', 'outdated', 'not_scanned', and 'error' posts
        if (in_array($status, array('queued', 'processing'))) {
            return false;
        }

        // Add job to queue using new job queue system
        $queue_manager = ExplainerPlugin_Job_Queue_Manager::get_instance();

        if (!$queue_manager) {
            return false;
        }

        if ($queue_manager) {

            // Get post content for scanning
            $post = get_post($post_id);
            if (!$post) {
                return false;
            }

            $post_content = $post->post_content;
            $options = array(
                'post_id' => $post_id,
                'post_title' => $post->post_title
            );
            $user_id = get_current_user_id();

            $job_id = $queue_manager->queue_job('ai_term_scan', array(
                'post_id' => $post_id,
                'post_content' => $post_content,
                'post_title' => $post->post_title,
                'scan_type' => 'ai_term_scan'
            ), array(
                'created_by' => $user_id,
                'priority' => 10
            ));

            if ($job_id) {
                update_post_meta($post_id, '_wp_ai_explainer_scan_job_id', $job_id);
                return true;
            }
        }

        return false;
    }
    
    /**
     * Dequeue a single post from AI scan
     */
    private function dequeue_single_post($post_id) {
        $job_id = get_post_meta($post_id, '_wp_ai_explainer_scan_job_id', true);

        if (!$job_id) {
            return false;
        }

        // Only dequeue if status is queued
        $status = $this->get_post_scan_status($post_id);

        if ($status === 'queued') {
            global $wpdb;
            $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';

            // Extract numeric job ID (remove jq_ prefix if present)
            $numeric_job_id = str_replace('jq_', '', $job_id);

            // Delete the job from the queue table
            $deleted = $wpdb->delete(
                $queue_table,
                array('queue_id' => $numeric_job_id, 'status' => 'pending'),
                array('%d', '%s')
            );

            if ($deleted) {
                delete_post_meta($post_id, '_wp_ai_explainer_scan_job_id');

                // Trigger action for other parts of the system to update
                do_action('explainer_job_dequeued', $post_id, $numeric_job_id);

                return true;
            }
        }

        return false;
    }
    
    /**
     * AJAX handler for meta box status updates
     */
    public function handle_get_meta_box_status() {
        // Verify nonce
        check_ajax_referer('explainer_admin_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }

        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'ai-explainer')));
        }

        $status = $this->get_post_scan_status($post_id);
        $term_count = get_post_meta($post_id, '_wp_ai_explainer_term_count', true);
        $last_scan_date = get_post_meta($post_id, '_wp_ai_explainer_last_scan_date', true);

        wp_send_json_success(array(
            'status' => $status,
            'term_count' => $term_count ? intval($term_count) : 0,
            'last_scan_date' => $last_scan_date,
        ));
    }
    
    /**
     * AJAX handler for refreshing post status from meta box
     */
    public function handle_refresh_post_status() {
        // Verify nonce
        check_ajax_referer('explainer_admin_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }

        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'ai-explainer')));
        }

        wp_send_json_success(array('message' => __('Status refreshed', 'ai-explainer')));
    }

    /**
     * AJAX handler for queuing/dequeuing posts from meta box
     */
    public function handle_toggle_post_queue() {
        // Verify nonce
        check_ajax_referer('explainer_admin_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }

        $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;
        $action = isset($_POST['queue_action']) ? sanitize_text_field(wp_unslash($_POST['queue_action'])) : '';
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'ai-explainer')));
        }
        
        if ($action === 'queue') {
            $result = $this->queue_single_post($post_id);
            if ($result) {
                wp_send_json_success(array('message' => __('Post queued for AI scan', 'ai-explainer')));
            } else {
                wp_send_json_error(array('message' => __('Failed to queue post', 'ai-explainer')));
            }
        } elseif ($action === 'dequeue') {
            $result = $this->dequeue_single_post($post_id);
            if ($result) {
                wp_send_json_success(array('message' => __('Post removed from queue', 'ai-explainer')));
            } else {
                wp_send_json_error(array('message' => __('Failed to remove post from queue', 'ai-explainer')));
            }
        } else {
            wp_send_json_error(array('message' => __('Invalid action', 'ai-explainer')));
        }
    }
    
    /**
     * Clean up post metadata when post is deleted
     */
    public function cleanup_post_metadata($post_id) {
        // Only clean up for posts that have AI explainer metadata
        $job_id = get_post_meta($post_id, '_wp_ai_explainer_scan_job_id', true);
        
        if ($job_id) {
            // Cancel any pending jobs for this post
            $queue_manager = ExplainerPlugin_Job_Queue_Manager::get_instance();
            if ($queue_manager) {
                $queue_manager->cancel_job(str_replace('jq_', '', $job_id));
            }
            
            // Clean up all AI explainer metadata
            delete_post_meta($post_id, '_wp_ai_explainer_scan_enabled');
            delete_post_meta($post_id, '_wp_ai_explainer_scan_job_id');
            delete_post_meta($post_id, '_wp_ai_explainer_last_scan_date');
            delete_post_meta($post_id, '_wp_ai_explainer_term_count');
        }
        
        // Clean up any related selection data
        if (class_exists('ExplainerPlugin_Selection_Tracker')) {
            global $wpdb;
            $selections_table = $wpdb->prefix . 'ai_explainer_selections';
            $wpdb->delete($selections_table, array('post_id' => $post_id), array('%d'));
        }
    }
    
    /**
     * Handle AJAX request to load post terms for frontend display
     */
    public function handle_load_post_terms() {
        try {
            // Verify nonce for security
            if (!check_ajax_referer('explainer_nonce', 'nonce', false)) {
                throw new Exception('Invalid nonce');
            }
            
            // Get post ID from request
            $post_id = intval($_POST['post_id'] ?? 0);
            if (!$post_id) {
                throw new Exception('Invalid post ID');
            }
            
            // Verify post exists and is accessible
            $post = get_post($post_id);
            if (!$post) {
                throw new Exception('Post not found');
            }
            
            // Allow published posts, or drafts if user can edit posts
            $can_access = $post->post_status === 'publish' || 
                         (in_array($post->post_status, ['draft', 'private']) && current_user_can('edit_post', $post_id));
            
            if (!$can_access) {
                throw new Exception('Post not found or not accessible');
            }
            
            // Get enabled AI-scanned terms for this post
            if (!class_exists('ExplainerPlugin_Selection_Tracker')) {
                throw new Exception('Selection tracker not available');
            }
            
            $selection_tracker = new ExplainerPlugin_Selection_Tracker();
            $terms = $selection_tracker->get_post_selections($post_id, true); // enabled_only = true
            
            // Format all enabled terms for frontend (both AI-scanned and manual selections)
            $ai_terms = array();
            foreach ($terms as $term) {
                if ($term['enabled'] && !empty($term['ai_explanation'])) {
                    $ai_terms[] = array(
                        'id' => $term['id'],
                        'term' => $term['selected_text'],
                        'explanation' => $term['ai_explanation'],
                        'reading_level' => $term['reading_level']
                    );
                }
            }
            
            wp_send_json_success(array(
                'post_id' => $post_id,
                'terms' => $ai_terms,
                'count' => count($ai_terms)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle real-time connection test AJAX request
     */
    public function handle_realtime_connection_test() {
        try {
            // Verify nonce
            if (!check_ajax_referer('explainer_settings_nonce', 'nonce', false)) {
                throw new Exception('Invalid nonce');
            }
            
            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new Exception('Insufficient permissions');
            }
            
            // Run comprehensive connection test
            $test_results = $this->run_realtime_diagnostics();
            
            wp_send_json_success($test_results);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Run comprehensive real-time diagnostics
     * 
     * @return array Test results
     */
    private function run_realtime_diagnostics() {
        $results = array();
        $start_time = microtime(true);
        
        // Test 1: Check if SSE endpoints exist and are accessible
        $results['sse_supported'] = $this->test_sse_endpoint_availability();
        
        // Test 2: Check if polling endpoints work
        $results['polling_working'] = $this->test_polling_endpoint_availability();
        
        // Test 3: Test authentication for real-time endpoints
        $results['authentication'] = $this->test_realtime_authentication();
        
        // Test 4: Detect current transport mode
        $results['current_transport'] = $this->detect_current_transport_mode();
        
        // Test 5: Measure connection latency
        $results['connection_latency'] = $this->measure_connection_latency();
        
        // Test 6: Check for CDN interference
        $results['cdn_interference'] = $this->detect_cdn_interference();
        
        // Test 7: Check hosting environment compatibility
        $results['hosting_compatibility'] = $this->check_hosting_compatibility();
        
        // Overall test duration
        $results['test_duration'] = round((microtime(true) - $start_time) * 1000, 2);
        
        // Generate summary and recommendations
        $results['summary'] = $this->generate_test_summary($results);
        $results['recommendations'] = $this->generate_recommendations($results);
        
        return $results;
    }

    /**
     * Test SSE endpoint availability
     */
    private function test_sse_endpoint_availability() {
        try {
            // Use the diagnostic-specific endpoint (unauthenticated)
            $sse_endpoint = rest_url('wp-ai-explainer/v1/stream-diagnostic/system:diagnostics');
            
            // Make completely unauthenticated request (no nonce, no cookies)
            // According to WordPress REST API docs: "If no nonce is provided the API 
            // will set the current user to 0, turning the request into an unauthenticated request"
            $response = wp_remote_get($sse_endpoint, array(
                'timeout' => 10,
                'cookies' => array(), // No cookies
                'headers' => array(
                    'Accept' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                    'X-Test' => 'realtime-diagnostics',
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url() . ' (Diagnostic Test)'
                )
                // No X-WP-Nonce header - this makes it unauthenticated
            ));
            
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $error_code = $response->get_error_code();
                
                // Log detailed error for debugging
                if (function_exists('explainer_log_error')) {
                    explainer_log_error('SSE Endpoint Test Error', array(
                        'error_code' => $error_code,
                        'error_message' => $error_message,
                        'endpoint' => $sse_endpoint
                    ), 'admin');
                }
                
                return array(
                    'status' => false,
                    'error' => $error_message,
                    'error_code' => $error_code,
                    'endpoint_tested' => $sse_endpoint
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $content_type = wp_remote_retrieve_header($response, 'content-type');
            $response_body = wp_remote_retrieve_body($response);
            
            // Log response details for debugging
            if (function_exists('explainer_log_debug')) {
                explainer_log_debug('SSE Endpoint Response', array(
                    'response_code' => $response_code,
                    'content_type' => $content_type,
                    'body_length' => strlen($response_body)
                ), 'admin');
            }
            
            // SSE endpoints should return 200 or might return 404 if topic doesn't exist
            $success = ($response_code === 200 || ($response_code === 404 && strpos($content_type, 'application/json') !== false));
            
            return array(
                'status' => $success,
                'response_code' => $response_code,
                'content_type' => $content_type,
                'supports_streaming' => strpos($content_type, 'text/event-stream') !== false || $response_code === 404,
                'error' => $success ? null : 'HTTP ' . $response_code . ' response'
            );
            
        } catch (Exception $e) {
            return array(
                'status' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Test polling endpoint availability
     */
    private function test_polling_endpoint_availability() {
        try {
            // Use the diagnostic-specific polling endpoint (unauthenticated)
            $polling_endpoint = rest_url('wp-ai-explainer/v1/poll-diagnostic/system:diagnostics');
            
            // Make completely unauthenticated request (no nonce, no cookies)
            // Following the same pattern as SSE diagnostic test
            $response = wp_remote_get($polling_endpoint, array(
                'timeout' => 10,
                'cookies' => array(), // No cookies
                'headers' => array(
                    'Accept' => 'application/json',
                    'X-Test' => 'realtime-diagnostics',
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url() . ' (Polling Diagnostic Test)'
                )
                // No X-WP-Nonce header - this makes it unauthenticated
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'status' => false,
                    'error' => $response->get_error_message()
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $content_type = wp_remote_retrieve_header($response, 'content-type');
            
            // Try to decode JSON response
            $data = json_decode($body, true);
            $is_json = (json_last_error() === JSON_ERROR_NONE);
            
            // Polling endpoints should return 200 or might return 404 if topic doesn't exist
            $success = ($response_code === 200 || ($response_code === 404 && $is_json));
            
            return array(
                'status' => $success,
                'response_code' => $response_code,
                'content_type' => $content_type,
                'valid_json' => $is_json,
                'has_events_array' => $is_json && isset($data['events'])
            );
            
        } catch (Exception $e) {
            return array(
                'status' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Test real-time authentication
     */
    private function test_realtime_authentication() {
        // For now, return basic WordPress authentication status
        return array(
            'status' => is_user_logged_in() && current_user_can('read'),
            'user_id' => get_current_user_id(),
            'can_access_realtime' => current_user_can('read')
        );
    }

    /**
     * Detect current transport mode
     */
    private function detect_current_transport_mode() {
        $configured_transport = get_option('explainer_realtime_transport', 'auto');
        $sse_enabled = ExplainerPlugin_Config::get_realtime_setting('sse_enabled', true);
        
        if ($configured_transport === 'polling' || !$sse_enabled) {
            return 'polling';
        } elseif ($configured_transport === 'sse') {
            return 'sse';
        } else {
            // Auto mode - would need actual browser-side testing
            return 'auto';
        }
    }

    /**
     * Measure connection latency
     */
    private function measure_connection_latency() {
        $start_time = microtime(true);
        
        // Simple ping to admin-ajax.php
        $response = wp_remote_get(admin_url('admin-ajax.php?action=heartbeat'), array(
            'timeout' => 5
        ));
        
        $latency = round((microtime(true) - $start_time) * 1000, 2);
        
        return array(
            'latency_ms' => $latency,
            'status' => !is_wp_error($response)
        );
    }

    /**
     * Detect CDN interference
     */
    private function detect_cdn_interference() {
        // Check for common CDN headers or URLs
        $site_url = home_url();
        $is_cloudflare = false;
        $is_cdn = false;
        
        // Check if we're behind CloudFlare (crude detection)
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP']) || isset($_SERVER['HTTP_CF_RAY'])) {
            $is_cloudflare = true;
            $is_cdn = true;
        }
        
        // Check for other CDN indicators
        $cdn_indicators = array('cdn', 'cloudfront', 'fastly', 'maxcdn', 'keycdn');
        foreach ($cdn_indicators as $indicator) {
            if (strpos($site_url, $indicator) !== false) {
                $is_cdn = true;
                break;
            }
        }
        
        return array(
            'detected' => $is_cdn,
            'cloudflare' => $is_cloudflare,
            'recommendation' => $is_cdn ? 'Consider using polling mode if SSE fails' : 'No CDN interference detected'
        );
    }

    /**
     * Check hosting environment compatibility
     */
    private function check_hosting_compatibility() {
        $php_version = PHP_VERSION;
        $wp_version = get_bloginfo('version');
        $server_software = isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown';
        
        $compatibility = array(
            'php_version' => $php_version,
            'php_compatible' => version_compare($php_version, '7.4', '>='),
            'wp_version' => $wp_version,
            'wp_compatible' => version_compare($wp_version, '5.0', '>='),
            'server_software' => $server_software,
            'supports_sse' => function_exists('ignore_user_abort') && function_exists('set_time_limit'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        );
        
        return $compatibility;
    }

    /**
     * Generate test summary
     */
    private function generate_test_summary($results) {
        $summary = array();
        
        if ($results['sse_supported']['status']) {
            $summary[] = '✓ SSE endpoints accessible';
        } else {
            $summary[] = '✗ SSE endpoints failed: ' . ($results['sse_supported']['error'] ?? 'Unknown error');
        }
        
        if ($results['polling_working']['status']) {
            $summary[] = '✓ Polling endpoints working';
        } else {
            $summary[] = '✗ Polling endpoints failed: ' . ($results['polling_working']['error'] ?? 'Unknown error');
        }
        
        if ($results['authentication']['status']) {
            $summary[] = '✓ Authentication working';
        } else {
            $summary[] = '✗ Authentication issues detected';
        }
        
        return $summary;
    }

    /**
     * Generate recommendations based on test results
     */
    private function generate_recommendations($results) {
        $recommendations = array();
        
        // SSE-specific recommendations
        if (!$results['sse_supported']['status']) {
            $recommendations[] = 'Consider using "Force Polling" mode for reliable connections';
            if ($results['cdn_interference']['detected']) {
                $recommendations[] = 'CDN detected - configure CDN to allow Server-Sent Events';
            }
        }
        
        // Performance recommendations
        if ($results['connection_latency']['latency_ms'] > 1000) {
            $recommendations[] = 'High latency detected - consider shorter heartbeat intervals';
        }
        
        // Hosting recommendations
        if (!$results['hosting_compatibility']['supports_sse']) {
            $recommendations[] = 'Server may not fully support SSE - polling mode recommended';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'Configuration looks good - no changes needed';
        }
        
        return $recommendations;
    }
    
    
    /**
     * AJAX handler to get terms for modal
     */
    
    /**
     * Output modal template in admin footer
     */
    public function output_modal_template() {
        include_once EXPLAINER_PLUGIN_PATH . 'templates/ai-terms-modal-template.php';
    }

    /**
     * Get list of restricted configuration options
     */
    private function get_restricted_config_keys() {
        return array(
            'explainer_claude_api_key',
            'explainer_openrouter_api_key',
            'explainer_gemini_api_key'
        );
    }

    /**
     * Validate access to advanced configuration options
     */
    public function validate_config_access($value, $old_value) {
        if (!explainer_can_use_premium_features()) {
            // Only show error if user is actually trying to set a non-empty value
            if (!empty($value) && $value !== $old_value) {
                $current_filter = current_filter();
                $setting_name = str_replace('pre_update_option_explainer_', '', $current_filter);
                $setting_name = str_replace('_', ' ', $setting_name);

                add_settings_error(
                    $current_filter,
                    'access_restricted',
                    /* translators: %s: Setting name that requires pro licence */
                    sprintf(__('Pro licence required for %s.', 'ai-explainer'), $setting_name)
                );
            }
            return $old_value;
        }

        return $value;
    }

    /**
     * Hook configuration validation to restricted settings
     */
    private function hook_config_validation() {
        $restricted_keys = $this->get_restricted_config_keys();

        foreach ($restricted_keys as $setting_key) {
            add_filter("pre_update_option_{$setting_key}", array($this, 'validate_config_access'), 10, 2);
        }
    }

    /**
     * Render Account page for license management
     */
    /**
     * Process Freemius actions early before any output
     * This must run on admin_init before headers are sent
     */
    public function process_freemius_actions() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Freemius handles nonce verification
        if (!isset($_GET['page']) || $_GET['page'] !== 'wp-ai-explainer-admin-account') {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Freemius handles nonce verification
        if (!isset($_POST['fs_action'])) {
            return;
        }

        if (!function_exists('wpaie_freemius')) {
            return;
        }

        $fs = wpaie_freemius();

        // Start output buffering to catch any output from Freemius
        ob_start();

        // Trigger Freemius account page load which handles actions
        $fs->_account_page_load();

        // Clean the buffer (discard any output)
        ob_end_clean();

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified by Freemius
        $fs_action = sanitize_text_field(wp_unslash($_POST['fs_action']));

        // Redirect back to our custom account page after action
        if ($fs_action === 'deactivate_license') {
            wp_safe_redirect(admin_url('admin.php?page=wp-ai-explainer-admin-account'));
            exit;
        }
    }

    public function account_page() {
        // Start output buffering to prevent Freemius from adding content after our page
        ob_start();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Account & License', 'ai-explainer'); ?></h1>

            <?php
            if (function_exists('wpaie_freemius')) {
                $fs = wpaie_freemius();

                if (!$fs->is_registered()) {
                    // Show manual activation form for non-registered users
                    $this->render_license_activation_form($fs);
                } else {
                    // Always show activation option first - allows upgrading/changing licenses
                    $this->render_license_activation_form($fs);

                    echo '<hr>';

                    // Show simplified account details for customers
                    $this->render_customer_account_info($fs);
                }
            } else {
                ?>
                <div class="notice notice-error">
                    <p><?php esc_html_e('Freemius integration not available.', 'ai-explainer'); ?></p>
                </div>
                <?php
            }
            ?>
        </div>
        <?php

        // Get our content and clean the buffer
        $content = ob_get_clean();

        // Output only our content and exit to prevent Freemius from adding the Account Details section
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content already escaped in render methods
        echo $content;

        // Add footer manually since we're exiting early
        include(ABSPATH . 'wp-admin/admin-footer.php');
        exit;
    }

    /**
     * Render the license activation form with premium benefits
     */
    private function render_license_activation_form($fs) {
        // Add Freemius license activation dialog (includes resend key modal)
        $fs->_add_license_activation_dialog_box();

        $license = $fs->_get_license();
        ?>
        <div class="license-activation-container">
            <h3><?php esc_html_e('Activate Your License', 'ai-explainer'); ?></h3>
            <p><?php esc_html_e('Enter your license key to unlock premium features.', 'ai-explainer'); ?></p>

            <div class="wpaie-license-actions">
                <button class="button button-primary activate-license-trigger <?php echo esc_attr($fs->get_unique_affix()); ?>">
                    <?php esc_html_e('Activate License', 'ai-explainer'); ?>
                </button>

                <?php if ($license && $fs->has_active_valid_license()): ?>
                <a href="#" class="button" id="show-subscription-info">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e('Manage Subscription', 'ai-explainer'); ?>
                </a>

                <form id="deactivate-license-form" method="POST" action="<?php echo esc_url(admin_url('admin.php?page=wp-ai-explainer-admin-account')); ?>" style="display: inline;">
                    <input type="hidden" name="fs_action" value="deactivate_license">
                    <?php wp_nonce_field('deactivate_license'); ?>
                    <a href="#" class="button" id="deactivate-license-trigger" data-confirm="<?php echo esc_attr(__('Deactivating your license will block all premium features, but will enable activating the license on another site. Are you sure you want to proceed?', 'ai-explainer')); ?>">
                        <span class="dashicons dashicons-admin-network"></span>
                        <?php esc_html_e('Deactivate License', 'ai-explainer'); ?>
                    </a>
                </form>
                <?php endif; ?>
            </div>

            <?php if (!$fs->has_active_valid_license()): ?>
            <p class="wpaie-help-links">
                <a href="#" class="show-license-resend-modal show-license-resend-modal-<?php echo esc_attr($fs->get_unique_affix()); ?>">
                    <?php esc_html_e('Find your license key', 'ai-explainer'); ?>
                </a>
                •
                <a href="<?php echo esc_url($fs->get_upgrade_url()); ?>">
                    <?php esc_html_e('Purchase new license', 'ai-explainer'); ?>
                </a>
            </p>
            <?php endif; ?>
        </div>

        <?php if ($license && $fs->has_active_valid_license()): ?>
        <div class="wpaie-account-notice" id="subscription-info" style="margin-top: 20px; display: none;">
            <div class="notice notice-info inline">
                <p>
                    <strong><?php esc_html_e('Manage Subscription', 'ai-explainer'); ?></strong>
                </p>
                <p>
                    <?php esc_html_e('This will take you to Freemius, our trusted licensing partner. Freemius is a premium platform that handles WordPress plugin payments and subscriptions securely.', 'ai-explainer'); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url($fs->get_account_url()); ?>" class="button button-primary" target="_blank">
                        <?php esc_html_e('Go to Account Dashboard', 'ai-explainer'); ?>
                    </a>
                    <button type="button" class="button" id="hide-subscription-info">
                        <?php esc_html_e('Cancel', 'ai-explainer'); ?>
                    </button>
                </p>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#show-subscription-info').on('click', function() {
                $('#subscription-info').slideToggle();
                $(this).prop('disabled', true).text('<?php esc_js(esc_html_e('Loading...', 'ai-explainer')); ?>');
                setTimeout(function() {
                    $('#show-subscription-info').prop('disabled', false).html('<span class="dashicons dashicons-admin-settings"></span> <?php esc_js(esc_html_e('Manage Subscription', 'ai-explainer')); ?>');
                }, 300);
            });

            $('#hide-subscription-info').on('click', function() {
                $('#subscription-info').slideUp();
            });

            $('#deactivate-license-trigger').on('click', function(e) {
                e.preventDefault();
                var $this = $(this);
                var confirmMsg = $this.data('confirm');

                if (confirm(confirmMsg)) {
                    // Submit the parent form
                    $('#deactivate-license-form').submit();
                }
            });
        });
        </script>
        <?php endif; ?>

        <style>
            .fs-modal-license-activation .fs-modal-footer {
                display: flex;
                gap: 10px;
                justify-content: flex-end;
                align-items: center;
            }
            .fs-modal-license-activation .fs-modal-footer .button {
                margin: 0;
            }
            .wpaie-license-actions {
                display: flex;
                gap: 10px;
                align-items: center;
                flex-wrap: wrap;
                margin-top: 15px;
            }
            .wpaie-license-actions .button {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                height: auto;
                line-height: normal;
                padding: 7px 14px;
            }
            .wpaie-license-actions .button .dashicons {
                margin: 0;
                width: 20px;
                height: 20px;
                font-size: 20px;
            }
        </style>
        <?php
    }

    /**
     * Render simplified customer account information
     */
    private function render_customer_account_info($fs) {
        $user = $fs->get_user();
        $license = $fs->_get_license();
        $site = $fs->get_site();

        $support_email = '';

        if ($license && isset($license->plan) && is_object($license->plan) && !empty($license->plan->support_email)) {
            $support_email = sanitize_email($license->plan->support_email);
        }

        if (!$support_email) {
            $plan_details = $fs->get_plan();
            if ($plan_details && !empty($plan_details->support_email)) {
                $support_email = sanitize_email($plan_details->support_email);
            }
        }

        $support_href = $support_email ? sprintf('mailto:%s', $support_email) : $fs->contact_url();
        $support_link_attrs = $support_email ? '' : ' target="_blank" rel="noreferrer noopener"';
        ?>
        <div class="wpaie-customer-account">
            <div class="wpaie-account-header">
                <h3><?php esc_html_e('Account Information', 'ai-explainer'); ?></h3>
                <div class="wpaie-account-status <?php echo esc_attr($fs->has_active_valid_license() ? 'status-active' : 'status-inactive'); ?>">
                    <?php echo esc_html($fs->has_active_valid_license() ? __('Active', 'ai-explainer') : __('Inactive', 'ai-explainer')); ?>
                </div>
            </div>

            <div class="wpaie-account-grid">
                <!-- User Information -->
                <div class="wpaie-account-section">
                    <h4><span class="dashicons dashicons-admin-users"></span><?php esc_html_e('User Details', 'ai-explainer'); ?></h4>

                    <?php if ($user && $user->email): ?>
                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('Email Address', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value"><?php echo esc_html($user->email); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($user && isset($user->first) && isset($user->last)): ?>
                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('Name', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value"><?php echo esc_html($user->first . ' ' . $user->last); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($user && isset($user->created)): ?>
                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('Customer Since', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value"><?php echo esc_html(wp_date('F j, Y', strtotime($user->created))); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($user && isset($user->is_verified)): ?>
                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('Email Verified', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value">
                            <?php
                            if ($user->is_verified) {
                                echo '<span style="color: #46b450;">✓ ' . esc_html__('Yes', 'ai-explainer') . '</span>';
                            } else {
                                echo '<span style="color: #dc3232;">✗ ' . esc_html__('No', 'ai-explainer') . '</span>';
                            }
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- License Information -->
                <?php if ($license): ?>
                <div class="wpaie-account-section">
                    <h4><span class="dashicons dashicons-awards"></span><?php esc_html_e('License Details', 'ai-explainer'); ?></h4>

                    <?php
                    // Try to get plan information from multiple sources
                    $plan_title = '';
                    if (isset($license->plan) && isset($license->plan->title)) {
                        $plan_title = $license->plan->title;
                    } elseif ($fs->get_plan()) {
                        $plan_obj = $fs->get_plan();
                        $plan_title = $plan_obj->title ?? $plan_obj->name ?? '';
                    } elseif (method_exists($fs, 'get_plan_title')) {
                        $plan_title = $fs->get_plan_title();
                    }

                    if ($plan_title): ?>
                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('Plan', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value"><?php echo esc_html($plan_title); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('License Type', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value">
                            <?php echo esc_html(isset($license->is_lifetime) && $license->is_lifetime ?
                                __('Lifetime', 'ai-explainer') :
                                __('Subscription', 'ai-explainer')); ?>
                        </span>
                    </div>

                    <?php if (!$license->is_lifetime() && $license->expiration): ?>
                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('Expires', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value"><?php echo esc_html(wp_date('F j, Y', strtotime($license->expiration))); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($license->quota) && isset($license->activated)): ?>
                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('Sites Used', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value"><?php echo esc_html($license->activated . ' / ' . $license->quota); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($license->created)): ?>
                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('Purchased', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value"><?php echo esc_html(wp_date('F j, Y', strtotime($license->created))); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Site Information -->
                <?php if ($site): ?>
                <div class="wpaie-account-section">
                    <h4><span class="dashicons dashicons-admin-home"></span><?php esc_html_e('Site Details', 'ai-explainer'); ?></h4>

                    <?php if (isset($site->title)): ?>
                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('Site Title', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value"><?php echo esc_html($site->title); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($site->url)): ?>
                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('Site URL', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value"><?php echo esc_html($site->url); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($site->version)): ?>
                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('Plugin Version', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value">v<?php echo esc_html($site->version); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($site->platform_version)): ?>
                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('WordPress Version', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value">v<?php echo esc_html($site->platform_version); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Usage Statistics -->
                <div class="wpaie-account-section wpaie-usage-stats">
                    <h4><span class="dashicons dashicons-chart-line"></span><?php esc_html_e('Usage Statistics', 'ai-explainer'); ?></h4>

                    <?php
                    // Get usage stats from database
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'ai_explainer_selections';

                    // Check cache first
                    $cache_key_total = 'explainer_total_explanations';
                    $total_explanations = wp_cache_get($cache_key_total);

                    if (false === $total_explanations) {
                        $total_explanations = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$table_name} WHERE ai_explanation IS NOT NULL AND ai_explanation != %s",
                            ''
                        ));
                        wp_cache_set($cache_key_total, $total_explanations, '', 300); // Cache for 5 minutes
                    }

                    $cache_key_monthly = 'explainer_monthly_explanations_' . wp_date('Y-m');
                    $monthly_explanations = wp_cache_get($cache_key_monthly);

                    if (false === $monthly_explanations) {
                        $monthly_explanations = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$table_name} WHERE ai_explanation IS NOT NULL AND ai_explanation != %s AND first_seen >= %s",
                            '',
                            wp_date('Y-m-01')
                        ));
                        wp_cache_set($cache_key_monthly, $monthly_explanations, '', 300); // Cache for 5 minutes
                    }
                    ?>

                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('Total Explanations', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value"><?php echo esc_html(number_format($total_explanations ?: 0)); ?></span>
                    </div>

                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('This Month', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value"><?php echo esc_html(number_format($monthly_explanations ?: 0)); ?></span>
                    </div>

                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('Current AI Provider', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value"><?php
                            $provider = get_option('explainer_api_provider', 'openai');
                            $provider_names = [
                                'openai' => 'OpenAI',
                                'claude' => 'Claude',
                                'gemini' => 'Gemini',
                                'openrouter' => 'OpenRouter'
                            ];
                            echo esc_html($provider_names[$provider] ?? ucfirst($provider));
                        ?></span>
                    </div>

                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('Current Model', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value"><?php echo esc_html(get_option('explainer_api_model', 'gpt-3.5-turbo')); ?></span>
                    </div>

                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('Cache Status', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value"><?php
                            $cache_enabled = get_option('explainer_cache_enabled', 1);
                            if ($cache_enabled) {
                                echo '<span style="color: #46b450;">✓ ' . esc_html__('Enabled', 'ai-explainer') . '</span>';
                            } else {
                                echo '<span style="color: #dc3232;">✗ ' . esc_html__('Disabled', 'ai-explainer') . '</span>';
                            }
                        ?></span>
                    </div>

                    <div class="wpaie-account-info-row">
                        <span class="wpaie-account-info-label"><?php esc_html_e('Cache Duration', 'ai-explainer'); ?></span>
                        <span class="wpaie-account-info-value"><?php echo esc_html(get_option('explainer_cache_duration', 24) . ' hours'); ?></span>
                    </div>
                </div>
            </div>

            <?php if ($license && $fs->has_active_valid_license()): ?>
            <div class="wpaie-account-actions">
                <a href="<?php echo esc_url($support_href); ?>" class="button"<?php echo wp_kses_post($support_link_attrs); ?>>
                    <span class="dashicons dashicons-sos"></span>
                    <?php esc_html_e('Contact Support', 'ai-explainer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-ai-explainer-admin')); ?>" class="button">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php esc_html_e('Plugin Settings', 'ai-explainer'); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle enabling setup mode
     */
    public function handle_enable_setup_mode() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_settings_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-explainer')));
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-explainer')));
        }

        // Enable setup mode
        update_option('explainer_setup_mode_active', true);

        // Get a random post or page URL
        $post_url = $this->get_random_post_url();

        wp_send_json_success(array(
            'message' => __('Setup mode enabled.', 'ai-explainer'),
            'post_url' => $post_url
        ));
    }

    /**
     * Get a random post or page URL for setup mode
     */
    private function get_random_post_url() {
        // Try to get a random published post first
        $post = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'orderby' => 'rand',
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));

        if (!empty($post)) {
            return get_permalink($post[0]);
        }

        // If no posts, try a random published page
        $page = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'orderby' => 'rand',
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));

        if (!empty($page)) {
            return get_permalink($page[0]);
        }

        // Fallback to home URL
        return home_url();
    }

    /**
     * Handle disabling setup mode (Cancel button on frontend or toggle in admin)
     */
    public function handle_disable_setup_mode() {
        // Check nonce - allow both frontend (setup_nonce) and admin (settings_nonce)
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        $valid_nonce = wp_verify_nonce($nonce, 'explainer_setup_nonce') || wp_verify_nonce($nonce, 'explainer_settings_nonce');

        if (!$valid_nonce) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-explainer')));
        }

        // Check user permissions (allow for logged-in admins on frontend)
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-explainer')));
        }

        // Delete setup mode option
        delete_option('explainer_setup_mode_active');

        wp_send_json_success(array(
            'message' => __('Setup mode disabled.', 'ai-explainer')
        ));
    }

    /**
     * Handle saving detected selector
     */
    public function handle_save_detected_selector() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_setup_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-explainer')));
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-explainer')));
        }

        // Get and sanitise selector
        $selector = isset($_POST['selector']) ? sanitize_text_field(wp_unslash($_POST['selector'])) : '';

        // Validate selector
        if (empty($selector)) {
            wp_send_json_error(array('message' => __('Selector cannot be empty.', 'ai-explainer')));
        }

        if (strlen($selector) > 200) {
            wp_send_json_error(array('message' => __('Selector is too long.', 'ai-explainer')));
        }

        // Basic CSS selector validation (allow #, ., letters, numbers, hyphens, spaces, commas)
        if (!preg_match('/^[#.\w\s,\-:>+~\[\]="\']+$/', $selector)) {
            wp_send_json_error(array('message' => __('Invalid selector format.', 'ai-explainer')));
        }

        // Get current included selectors
        $current = get_option('explainer_included_selectors', ExplainerPlugin_Config::get_default_selectors());

        // Prepend the new selector (unless it already exists)
        $selectors_array = array_map('trim', explode(',', $current));
        if (!in_array($selector, $selectors_array, true)) {
            array_unshift($selectors_array, $selector);
            $updated = implode(', ', $selectors_array);
            update_option('explainer_included_selectors', $updated);
        }

        // Delete setup mode option
        delete_option('explainer_setup_mode_active');

        wp_send_json_success(array(
            'message' => __('Selector saved successfully.', 'ai-explainer'),
            'selector' => $selector,
            'settings_url' => admin_url('admin.php?page=wp-ai-explainer-admin&tab=content&setup_complete=1')
        ));
    }

}
