<?php
/**
 * Plugin activation functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include brand colors configuration
require_once EXPLAINER_PLUGIN_PATH . 'includes/brand-colors.php';

/**
 * Handle plugin activation
 */
class ExplainerPlugin_Activator {
    
    /**
     * Activate the plugin
     */
    public static function activate() {
        // Check WordPress version
        if (!self::check_wordpress_version()) {
            deactivate_plugins(EXPLAINER_PLUGIN_BASENAME);
            wp_die(esc_html__('This plugin requires WordPress 5.0 or higher.', 'ai-explainer'));
        }
        
        // Check PHP version
        if (!self::check_php_version()) {
            deactivate_plugins(EXPLAINER_PLUGIN_BASENAME);
            wp_die(esc_html__('This plugin requires PHP 7.4 or higher.', 'ai-explainer'));
        }
        
        // Initialize database setup (centralized table creation)
        require_once EXPLAINER_PLUGIN_PATH . 'includes/class-database-setup.php';
        ExplainerPlugin_Database_Setup::initialize();
        
        // Initialize WPAIE Scheduler system
        self::initialize_wpaie_system();
        
        // Set default options
        self::set_default_options();
        
        // Create necessary directories
        self::create_directories();
        
        // Set activation flag
        update_option('explainer_plugin_activated', true, 'yes');
        
        // Generate encryption salt for API keys
        self::generate_encryption_salt();
        
        // Setup background processing cron jobs
        self::setup_cron_jobs();
        
        // Clear any existing caches
        self::clear_caches();
    }
    
    /**
     * Check WordPress version compatibility
     */
    private static function check_wordpress_version() {
        global $wp_version;
        return version_compare($wp_version, '5.0', '>=');
    }
    
    /**
     * Check PHP version compatibility
     */
    private static function check_php_version() {
        return version_compare(PHP_VERSION, '7.4', '>=');
    }
    
    /**
     * Initialize WPAIE Scheduler system if needed
     * 
     * @since 3.0.0
     */
    private static function initialize_wpaie_system() {
        // Initialize WPAIE Scheduler system with fresh tables
        if (class_exists('WP_AI_Explainer_WPAIE_Migration')) {
            WP_AI_Explainer_WPAIE_Migration::execute_migration();
            WP_AI_Explainer_WPAIE_Migration::mark_migration_complete();
        }
    }
    
    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        // Load defaults from config class to maintain consistency
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-config.php';
        $config_defaults = ExplainerPlugin_Config::get_default_settings();
        
        // Convert to WordPress option names with prefix
        $default_options = array();
        foreach ($config_defaults as $key => $value) {
            $option_name = 'explainer_' . $key;
            $default_options[$option_name] = $value;
        }
        
        // Add plugin-specific activation options not in config
        $default_options['explainer_blog_notifications_enabled'] = true;
        
        foreach ($default_options as $option => $value) {
            add_option($option, $value, '', 'yes');
        }
    }
    
    /**
     * Create necessary directories
     */
    private static function create_directories() {
        $upload_dir = wp_upload_dir();
        $plugin_dir = $upload_dir['basedir'] . '/explainer-plugin';
        
        if (!file_exists($plugin_dir)) {
            wp_mkdir_p($plugin_dir);
        }
        
        // Create cache directory
        $cache_dir = $plugin_dir . '/cache';
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
        
        // Create logs directory
        $logs_dir = $plugin_dir . '/logs';
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
        }
        
        // Create .htaccess for security
        $htaccess_content = "Order deny,allow\nDeny from all\n";
        file_put_contents($plugin_dir . '/.htaccess', $htaccess_content);
    }
    
    /**
     * Setup background processing cron jobs
     */
    private static function setup_cron_jobs() {
        // Schedule recurring blog queue processing (every 2 minutes)
        if (!wp_next_scheduled('explainer_process_blog_queue_recurring')) {
            wp_schedule_event(time(), 'twominutes', 'explainer_process_blog_queue_recurring');
        }
        
        // Schedule queue cleanup (daily at 3am)
        if (!wp_next_scheduled('explainer_cleanup_blog_queue')) {
            $cleanup_time = strtotime('3:00 AM');
            if ($cleanup_time < time()) {
                $cleanup_time = strtotime('tomorrow 3:00 AM');
            }
            wp_schedule_event($cleanup_time, 'daily', 'explainer_cleanup_blog_queue');
        }
    }
    
    /**
     * Clear caches
     */
    private static function clear_caches() {
        // Clear WordPress object cache
        wp_cache_flush();
        
        // Clear any transients
        delete_transient('explainer_api_test');
        
    }
    
    /**
     * Migrate existing blog queue data to new job queue system
     */
    private static function migrate_blog_queue_data() {
        global $wpdb;
        
        // Check if migration has already been done
        $migration_done = get_option('explainer_blog_queue_migrated', false);
        if ($migration_done) {
            return;
        }
        
        // Check if old blog queue table exists
        $old_table = $wpdb->prefix . 'ai_explainer_job_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '$old_table'") !== $old_table) {
            // No old table, mark migration as done
            update_option('explainer_blog_queue_migrated', true);
            return;
        }
        
        // Load migration helper
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-job-queue-migration.php';
        
        // Perform migration
        $results = \WPAIExplainer\JobQueue\ExplainerPlugin_Job_Queue_Migration::migrate_blog_queue_data();
        
        // Log migration results
        if ($results['migrated'] > 0) {
            if (function_exists('explainer_log_info')) {
                explainer_log_info('Migrated ' . $results['migrated'] . ' blog queue jobs to new system', array(), 'migration');
            }
        }
        
        if ($results['errors'] > 0) {
            if (function_exists('explainer_log_error')) {
                explainer_log_error('Migration errors: ' . $results['errors'], array(), 'migration');
            }
            foreach ($results['messages'] as $message) {
                if (strpos($message, 'Failed') !== false) {
                    if (function_exists('explainer_log_error')) {
                        explainer_log_error($message, array(), 'migration');
                    }
                }
            }
        }
        
        // Mark migration as done
        update_option('explainer_blog_queue_migrated', true);
    }
    
    /**
     * Drop old job queue related tables to avoid migration conflicts
     */
    private static function drop_old_job_tables() {
        global $wpdb;
        
        $tables_to_drop = array(
            $wpdb->prefix . 'ai_explainer_job_queue',
            $wpdb->prefix . 'ai_explainer_job_meta',
            $wpdb->prefix . 'ai_explainer_events',
            $wpdb->prefix . 'ai_explainer_sse_connections',
            $wpdb->prefix . 'ai_explainer_webhooks',
            $wpdb->prefix . 'ai_explainer_topics'
        );
        
        foreach ($tables_to_drop as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }
        
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('Dropped old job queue tables for fresh WPAIE Scheduler installation', array(
                'tables_dropped' => count($tables_to_drop)
            ), 'Activator');
        }
    }
    
    /**
     * Generate and store encryption salt for API keys
     */
    private static function generate_encryption_salt() {
        // Check if salt already exists
        $existing_salt = get_option('explainer_encryption_salt', '');
        
        if (empty($existing_salt)) {
            // Check if any API keys exist before generating new salt
            $openai_key = get_option('explainer_openai_api_key', '');
            $claude_key = get_option('explainer_claude_api_key', '');
            
            if (!empty($openai_key) || !empty($claude_key)) {
                // API keys exist but no salt - this means keys are inaccessible
                // Wipe the keys and set admin notice
                self::handle_inaccessible_api_keys();
                
                if (function_exists('explainer_log_debug')) {
                    explainer_log_debug('Found existing API keys but no salt - keys wiped', array(
                        'openai_key_exists' => !empty($openai_key),
                        'claude_key_exists' => !empty($claude_key)
                    ), 'Activator');
                }
            }
            
            // Generate a cryptographically secure salt
            $salt = wp_generate_password(64, true, true);
            
            // Store it persistently in the database
            update_option('explainer_encryption_salt', $salt, 'yes');
            
            if (function_exists('explainer_log_debug')) {
                explainer_log_debug('Generated new encryption salt for API keys', array(
                    'salt_length' => strlen($salt)
                ), 'Activator');
            }
        } else {
            // Salt exists - verify existing API keys can be decrypted
            $keys_accessible = self::verify_api_keys_accessible($existing_salt);
            
            if (!$keys_accessible) {
                // Keys exist but can't be decrypted with current salt
                self::handle_inaccessible_api_keys();
                
                if (function_exists('explainer_log_debug')) {
                    explainer_log_debug('API keys exist but cannot be decrypted - keys wiped', array(
                        'existing_salt_length' => strlen($existing_salt)
                    ), 'Activator');
                }
            } else {
                if (function_exists('explainer_log_debug')) {
                    explainer_log_debug('Encryption salt already exists and API keys accessible', array(
                        'existing_salt_length' => strlen($existing_salt)
                    ), 'Activator');
                }
            }
        }
    }
    
    /**
     * Verify that existing API keys can be decrypted with current salt
     */
    private static function verify_api_keys_accessible($salt) {
        $openai_key = get_option('explainer_openai_api_key', '');
        $claude_key = get_option('explainer_claude_api_key', '');
        
        // If no keys exist, consider it accessible (nothing to decrypt)
        if (empty($openai_key) && empty($claude_key)) {
            return true;
        }
        
        // Test decryption of each key that exists
        if (!empty($openai_key)) {
            $decrypted = self::test_decrypt_key($openai_key, $salt);
            if (!$decrypted) {
                return false;
            }
        }
        
        if (!empty($claude_key)) {
            $decrypted = self::test_decrypt_key($claude_key, $salt);
            if (!$decrypted) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Test decrypt an API key with given salt
     */
    private static function test_decrypt_key($encrypted_key, $salt) {
        if (empty($encrypted_key)) {
            return true; // Empty key is "accessible"
        }
        
        // Check if the key is already in plain text (valid API key format)
        if (self::is_valid_api_key_format($encrypted_key)) {
            return true; // Plain text key is accessible
        }
        
        // Attempt to decrypt
        $decoded = base64_decode($encrypted_key, true);
        if ($decoded === false) {
            return false; // Invalid base64
        }
        
        $parts = explode('|', $decoded);
        if (count($parts) !== 2) {
            return false; // Not in encrypted format
        }
        
        $api_key = $parts[0];
        $hash = $parts[1];
        
        // Verify hash with current salt
        return wp_hash($api_key . $salt) === $hash && self::is_valid_api_key_format($api_key);
    }
    
    /**
     * Handle inaccessible API keys by wiping them and setting admin notice
     */
    private static function handle_inaccessible_api_keys() {
        // Wipe the inaccessible API keys
        update_option('explainer_openai_api_key', '');
        update_option('explainer_claude_api_key', '');
        
        // Set admin notice flag
        update_option('explainer_api_keys_reset_notice', true);
        
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('API keys wiped due to encryption issues - admin notice set', array(), 'Activator');
        }
    }
    
    /**
     * Validate API key format for any provider
     */
    private static function is_valid_api_key_format($api_key) {
        if (!$api_key || !is_string($api_key)) {
            return false;
        }
        
        $api_key = trim($api_key);
        
        // Check for OpenAI format (sk-...)
        if (str_starts_with($api_key, 'sk-')) {
            return preg_match('/^sk-[a-zA-Z0-9._-]+$/', $api_key) && strlen($api_key) >= 20 && strlen($api_key) <= 200;
        }
        
        // Check for Claude format (sk-ant-...)
        if (str_starts_with($api_key, 'sk-ant-')) {
            return preg_match('/^sk-ant-[a-zA-Z0-9_-]+$/', $api_key) && strlen($api_key) >= 20 && strlen($api_key) <= 200;
        }
        
        return false;
    }
    
}