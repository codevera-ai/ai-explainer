<?php
/**
 * Plugin deactivation functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle plugin deactivation
 */
class ExplainerPlugin_Deactivator {
    
    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clear scheduled events
        self::clear_scheduled_events();
        
        // Clear caches
        self::clear_caches();
        
        // Clear transients
        self::clear_transients();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set deactivation flag
        update_option('explainer_plugin_deactivated', true);
        delete_option('explainer_plugin_activated');
    }
    
    /**
     * Clear scheduled WordPress events
     */
    private static function clear_scheduled_events() {
        // Clear cache cleanup event
        wp_clear_scheduled_hook('explainer_cache_cleanup');
        
        // Clear log cleanup event
        wp_clear_scheduled_hook('explainer_log_cleanup');
        
        // Clear analytics cleanup event
        wp_clear_scheduled_hook('explainer_analytics_cleanup');
        
        // Clear background processing events
        wp_clear_scheduled_hook('explainer_process_blog_queue');
        wp_clear_scheduled_hook('explainer_process_blog_queue_recurring');
        wp_clear_scheduled_hook('explainer_cleanup_blog_queue');
    }
    
    /**
     * Clear all caches
     */
    private static function clear_caches() {
        // Clear WordPress object cache
        wp_cache_flush();
        
    }
    
    
    /**
     * Clear transients
     */
    private static function clear_transients() {
        global $wpdb;
        
        // Clear rate limiting transients
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for plugin deactivation cleanup
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_explainer_rate_limit_%' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for plugin deactivation cleanup
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_explainer_rate_limit_%' ) );
        
        // Clear API test transients
        delete_transient('explainer_api_test');
        
        // Clear settings cache transients
        delete_transient('explainer_settings_cache');
    }
}