<?php
/**
 * Pro Admin AJAX Handlers
 *
 * Handles all AJAX requests for pro features
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pro Admin AJAX Handlers Class
 */
class ExplainerPlugin_Pro_Admin_Handlers {

    /**
     * Initialize pro AJAX handlers
     */
    public function __construct() {
        // Post scan handlers
        add_action('wp_ajax_explainer_search_posts', array($this, 'handle_search_posts'));
        add_action('wp_ajax_explainer_get_processed_posts', array($this, 'handle_get_processed_posts'));
        add_action('wp_ajax_explainer_process_post', array($this, 'handle_process_post'));
        add_action('wp_ajax_explainer_get_post_terms', array($this, 'handle_get_post_terms'));
        add_action('wp_ajax_explainer_load_post_terms', array($this, 'handle_load_post_terms'));
        add_action('wp_ajax_nopriv_explainer_load_post_terms', array($this, 'handle_load_post_terms'));
    }

    /**
     * Get admin instance (lazy loading to avoid circular reference)
     */
    private function get_admin() {
        return ExplainerPlugin_Admin::get_instance();
    }

    /**
     * Handle search posts AJAX request
     */
    public function handle_search_posts() {
        $this->get_admin()->handle_search_posts();
    }

    /**
     * Handle get processed posts AJAX request
     */
    public function handle_get_processed_posts() {
        $this->get_admin()->handle_get_processed_posts();
    }

    /**
     * Handle process post AJAX request
     */
    public function handle_process_post() {
        $this->get_admin()->handle_process_post();
    }

    /**
     * Handle get post terms AJAX request
     */
    public function handle_get_post_terms() {
        $this->get_admin()->handle_get_post_terms();
    }

    /**
     * Handle load post terms AJAX request
     */
    public function handle_load_post_terms() {
        $this->get_admin()->handle_load_post_terms();
    }
}
