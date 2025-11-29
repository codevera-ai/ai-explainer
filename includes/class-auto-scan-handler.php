<?php
/**
 * Auto-scan Handler - Automatically enable scan status for new posts/pages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle automatic scanning for new posts and pages
 */
class ExplainerPlugin_Auto_Scan_Handler {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // Hook into post creation
        add_action('save_post', array(__CLASS__, 'handle_post_save'), 10, 3);
        
        // Also hook into transition_post_status for better coverage
        add_action('transition_post_status', array(__CLASS__, 'handle_post_transition'), 10, 3);
    }
    
    /**
     * Handle post save event
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public static function handle_post_save($post_id, $post, $update) {
        // Only process new posts (not updates)
        if ($update) {
            return;
        }
        
        // Skip if this is an autosave or revision
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Only process posts and pages
        if (!in_array($post->post_type, array('post', 'page'))) {
            return;
        }
        
        // Check if auto-scan is enabled for this post type
        $should_auto_scan = self::should_auto_scan($post->post_type);
        if (!$should_auto_scan) {
            return;
        }
        
        // Enable scan status for this post
        self::enable_scan_status($post_id);
        
        // Log the action for debugging
        if (class_exists('ExplainerPlugin_Debug_Logger')) {
            ExplainerPlugin_Debug_Logger::info(
                sprintf('Auto-scan enabled for new %s (ID: %d)', esc_html($post->post_type), esc_html($post_id)), 'AutoScan');
        }
    }
    
    /**
     * Handle post status transition
     * 
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param WP_Post $post Post object
     */
    public static function handle_post_transition($new_status, $old_status, $post) {
        // Only handle transitions to 'publish' from 'draft' or 'auto-draft'
        if ($new_status !== 'publish') {
            return;
        }
        
        if (!in_array($old_status, array('draft', 'auto-draft'))) {
            return;
        }
        
        // Only process posts and pages
        if (!in_array($post->post_type, array('post', 'page'))) {
            return;
        }
        
        // Check if scan status is already set (from save_post hook)
        $existing_status = get_post_meta($post->ID, 'explainer_scan_enabled', true);
        if ($existing_status !== '') {
            return; // Already handled
        }
        
        // Check if auto-scan is enabled for this post type
        $should_auto_scan = self::should_auto_scan($post->post_type);
        if (!$should_auto_scan) {
            return;
        }
        
        // Enable scan status for this post
        self::enable_scan_status($post->ID);
        
        // Log the action for debugging
        if (class_exists('ExplainerPlugin_Debug_Logger')) {
            ExplainerPlugin_Debug_Logger::info(
                sprintf('Auto-scan enabled for published %s (ID: %d)', esc_html($post->post_type), esc_html($post->ID)), 'AutoScan');
        }
    }
    
    /**
     * Check if auto-scan should be enabled for the given post type
     * 
     * @param string $post_type Post type (post or page)
     * @return bool Whether auto-scan should be enabled
     */
    private static function should_auto_scan($post_type) {
        // Check global plugin enabled status first
        if (!get_option('explainer_enabled', true)) {
            return false;
        }
        
        // Check post type specific setting
        switch ($post_type) {
            case 'post':
                return get_option('explainer_auto_scan_posts', false);
            case 'page':
                return get_option('explainer_auto_scan_pages', false);
            default:
                return false;
        }
    }
    
    /**
     * Enable scan status for a post
     * 
     * @param int $post_id Post ID
     */
    private static function enable_scan_status($post_id) {
        // Set the scan enabled meta field to 1
        update_post_meta($post_id, 'explainer_scan_enabled', '1');
    }
}