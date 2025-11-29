<?php
/**
 * Asset Manager class for handling CSS and JavaScript enqueueing
 * Extracted from class-admin.php to improve maintainability
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle all admin asset enqueueing (CSS, JavaScript, localization)
 */
class ExplainerPlugin_Asset_Manager {
    
    /**
     * Utilities instance
     */
    private $utilities;
    
    /**
     * Initialize asset manager
     */
    public function __construct() {
        require_once EXPLAINER_PLUGIN_PATH . 'includes/admin/class-utilities.php';
        $this->utilities = new ExplainerPlugin_Utilities();
    }
    
    /**
     * Enqueue admin styles
     */
    public function enqueue_styles($hook) {
        // Enqueue on settings page and account page
        if ($hook === 'toplevel_page_wp-ai-explainer-admin' || $hook === 'ai-explainer_page_wp-ai-explainer-admin-account') {
            $this->enqueue_settings_styles();
        }

        // Enqueue duplicate menu fix on all admin pages
        $this->enqueue_admin_menu_fix();

        // Enqueue on post edit pages for meta box
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            $this->enqueue_meta_box_styles();
        }
    }
    
    /**
     * Enqueue styles for settings page
     */
    private function enqueue_settings_styles() {

        wp_enqueue_style('wp-color-picker', false, array(), EXPLAINER_PLUGIN_VERSION);
        
        // Include brand colors configuration
        require_once EXPLAINER_PLUGIN_PATH . 'includes/brand-colors.php';
        
        // Enqueue premium branding framework
        wp_enqueue_style(
            'explainer-plugin-branding',
            EXPLAINER_PLUGIN_URL . 'assets/css/plugin-branding.css',
            array(),
            EXPLAINER_PLUGIN_VERSION
        );
        
        // Add dynamic brand colors CSS
        wp_add_inline_style(
            'explainer-plugin-branding',
            explainer_get_brand_css_variables()
        );
        
        // Enqueue main plugin stylesheet (includes notification system)
        wp_enqueue_style(
            'explainer-plugin-style',
            EXPLAINER_PLUGIN_URL . 'assets/css/style.css',
            array('explainer-plugin-branding'),
            EXPLAINER_PLUGIN_VERSION,
            'all'
        );
        
        wp_enqueue_style(
            'explainer-admin',
            EXPLAINER_PLUGIN_URL . 'assets/css/admin.css',
            array('wp-color-picker', 'explainer-plugin-branding'),
            EXPLAINER_PLUGIN_VERSION
        );
        
        // Enqueue blog creator styles
        wp_enqueue_style(
            'explainer-blog-creator',
            EXPLAINER_PLUGIN_URL . 'assets/css/blog-creator.css',
            array('explainer-admin'),
            EXPLAINER_PLUGIN_VERSION . '.' . time()
        );
        
        // Enqueue pagination styles
        wp_enqueue_style(
            'explainer-pagination',
            EXPLAINER_PLUGIN_URL . 'assets/css/pagination.css',
            array('explainer-admin'),
            EXPLAINER_PLUGIN_VERSION
        );
    }
    
    /**
     * Enqueue styles for meta box
     */
    private function enqueue_meta_box_styles() {
        // Include brand colors configuration
        require_once EXPLAINER_PLUGIN_PATH . 'includes/brand-colors.php';
        
        // Enqueue premium branding framework for meta boxes
        wp_enqueue_style(
            'explainer-plugin-branding',
            EXPLAINER_PLUGIN_URL . 'assets/css/plugin-branding.css',
            array(),
            EXPLAINER_PLUGIN_VERSION
        );
        
        // Enqueue main plugin stylesheet (includes notification system)
        wp_enqueue_style(
            'explainer-plugin-style',
            EXPLAINER_PLUGIN_URL . 'assets/css/style.css',
            array('explainer-plugin-branding'),
            EXPLAINER_PLUGIN_VERSION,
            'all'
        );
        
        // Add dynamic brand colors CSS
        wp_add_inline_style(
            'explainer-plugin-branding',
            explainer_get_brand_css_variables()
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {

        // Enqueue on settings page and account page
        if ($hook === 'toplevel_page_wp-ai-explainer-admin' || $hook === 'ai-explainer_page_wp-ai-explainer-admin-account') {
            $this->enqueue_settings_scripts();
        }

        // Enqueue on post edit pages for meta box
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            $this->enqueue_meta_box_scripts();
        }
    }
    
    /**
     * Enqueue scripts for settings page
     */
    private function enqueue_settings_scripts() {

        wp_enqueue_script('wp-color-picker', false, array(), EXPLAINER_PLUGIN_VERSION, true);
        
        // Enqueue debug utility first as other scripts depend on it
        wp_enqueue_script(
            'explainer-debug',
            EXPLAINER_PLUGIN_URL . 'assets/js/debug.js',
            array(),
            EXPLAINER_PLUGIN_VERSION,
            true
        );

        // Enqueue logger after debug utility
        wp_enqueue_script(
            'explainer-logger',
            EXPLAINER_PLUGIN_URL . 'assets/js/logger.js',
            array('explainer-debug'),
            EXPLAINER_PLUGIN_VERSION,
            true
        );
        
        // Enqueue notification system
        wp_enqueue_script(
            'explainer-notifications',
            EXPLAINER_PLUGIN_URL . 'assets/js/notification-system.js',
            array('jquery'),
            EXPLAINER_PLUGIN_VERSION,
            true
        );
        
        wp_enqueue_script(
            'explainer-admin',
            EXPLAINER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker', 'explainer-logger', 'explainer-notifications'),
            EXPLAINER_PLUGIN_VERSION,
            true
        );
        
        // Enqueue settings tab functionality (critical for admin tabs to work)
        wp_enqueue_script(
            'explainer-admin-settings',
            EXPLAINER_PLUGIN_URL . 'includes/admin/settings/assets/settings-scripts.js',
            array('jquery'),
            EXPLAINER_PLUGIN_VERSION,
            true
        );
        
        // Localize settings script
        $has_api_keys = !empty(get_option('explainer_openai_api_key')) ||
                       !empty(get_option('explainer_claude_api_key')) ||
                       !empty(get_option('explainer_openrouter_api_key')) ||
                       !empty(get_option('explainer_gemini_api_key'));
        
        wp_localize_script('explainer-admin-settings', 'explainerSettings', array(
            'hasApiKeys' => $has_api_keys,
            'nonce' => wp_create_nonce('explainer_settings_nonce')
        ));
        
        // Enqueue blog creator script
        wp_enqueue_script(
            'explainer-blog-creator',
            EXPLAINER_PLUGIN_URL . 'assets/js/blog-creator.js',
            array('jquery', 'explainer-admin'),
            EXPLAINER_PLUGIN_VERSION . '.' . time(),
            true
        );
        
        // Enqueue pagination script
        wp_enqueue_script(
            'explainer-pagination',
            EXPLAINER_PLUGIN_URL . 'assets/js/pagination.js',
            array('jquery', 'explainer-admin'),
            EXPLAINER_PLUGIN_VERSION,
            true
        );
        
        // Enqueue debug logging script for advanced settings debug panel
        wp_enqueue_script(
            'explainer-debug-logging',
            EXPLAINER_PLUGIN_URL . 'assets/js/admin/debug-logging.js',
            array('jquery', 'explainer-admin'),
            EXPLAINER_PLUGIN_VERSION,
            true
        );
        
        // Localise debug configuration
        wp_localize_script('explainer-debug', 'ExplainerDebugConfig', ExplainerPlugin_Config::get_js_debug_config());

        wp_localize_script('explainer-admin', 'wpAiExplainer', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('explainer_admin_nonce'),
            'pluginUrl' => EXPLAINER_PLUGIN_URL,
            'brandColors' => explainer_get_brand_colors_for_js(),
            'defaultPrompt' => ExplainerPlugin_Config::get_default_custom_prompt()
        ));
        
        // Localize the logger with debug mode setting
        wp_localize_script('explainer-logger', 'explainer', array(
            'debugMode' => get_option('explainer_debug_mode', false),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('explainer_admin_nonce')
        ));
        
        // Localize blog creator script with additional data
        wp_localize_script('explainer-blog-creator', 'explainer_blog_creator_vars', array(
            'wp_language' => $this->utilities->get_language_from_locale(get_locale())
        ));
    }
    
    /**
     * Enqueue scripts for meta box
     */
    private function enqueue_meta_box_scripts() {
        // Check if user has capability to see meta box
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Enqueue notification system for meta box
        wp_enqueue_script(
            'explainer-notifications',
            EXPLAINER_PLUGIN_URL . 'assets/js/notification-system.js',
            array('jquery'),
            EXPLAINER_PLUGIN_VERSION,
            true
        );
        
        // Note: External meta-box.js removed due to conflicting selectors (.queue-checkbox vs .scan-toggle-btn)
        // Template uses inline JavaScript with explainerAdmin object defined directly in PHP
    }

    /**
     * Enqueue admin menu duplicate fix
     */
    private function enqueue_admin_menu_fix() {
        // Add inline script to hide duplicate Account menus
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                // Find duplicate Account menu items under AI Explainer
                var accountMenus = $("#adminmenu .wp-submenu a[href*=\"wp-ai-explainer-admin-account\"]");
                if (accountMenus.length > 1) {
                    console.log("Found " + accountMenus.length + " Account menu items, hiding duplicates");
                    // Hide all but the first one
                    accountMenus.slice(1).closest("li").hide();
                }
            });
        ');
    }
}