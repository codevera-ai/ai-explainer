<?php
/**
 * Setup Mode Frontend Handler
 * Allows administrators to auto-detect content areas by selecting text on the frontend
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle setup mode frontend functionality
 */
class ExplainerPlugin_Setup_Mode {

    /**
     * Constructor
     */
    public function __construct() {
        // Check if setup mode is active and user is admin
        add_action('wp_footer', array($this, 'maybe_render_setup_mode'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
    }

    /**
     * Check if setup mode should be active
     */
    private function is_setup_mode_active() {
        // Check if setup mode option is set
        $setup_mode = get_option('explainer_setup_mode_active');

        // Only show for logged-in administrators
        if (!$setup_mode || !current_user_can('manage_options')) {
            return false;
        }

        return true;
    }

    /**
     * Enqueue setup mode assets if active
     */
    public function maybe_enqueue_assets() {
        if (!$this->is_setup_mode_active()) {
            return;
        }

        // Enqueue setup mode JavaScript
        wp_enqueue_script(
            'explainer-setup-mode',
            EXPLAINER_PLUGIN_URL . 'assets/js/setup-mode.js',
            array('jquery'),
            EXPLAINER_PLUGIN_VERSION,
            true
        );

        // Enqueue setup mode CSS
        wp_enqueue_style(
            'explainer-setup-mode',
            EXPLAINER_PLUGIN_URL . 'assets/css/setup-mode.css',
            array(),
            EXPLAINER_PLUGIN_VERSION
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script(
            'explainer-setup-mode',
            'explainerSetupMode',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('explainer_setup_nonce'),
                'settingsUrl' => admin_url('admin.php?page=wp-ai-explainer-admin&tab=content')
            )
        );
    }

    /**
     * Render setup mode banner if active
     */
    public function maybe_render_setup_mode() {
        if (!$this->is_setup_mode_active()) {
            return;
        }

        ?>
        <div id="explainer-setup-mode-banner" class="explainer-setup-banner">
            <div class="explainer-setup-banner-inner">
                <span class="explainer-setup-icon">⚙️</span>
                <span class="explainer-setup-message">
                    <?php echo esc_html__('AI Explainer setup mode active - Select some text from your main content area to auto-detect the correct configuration', 'ai-explainer'); ?>
                </span>
                <button type="button" id="explainer-setup-cancel" class="explainer-setup-cancel-btn">
                    <?php echo esc_html__('Cancel', 'ai-explainer'); ?>
                </button>
            </div>
        </div>
        <?php
    }
}
