<?php
/**
 * Database Setup and Management
 * 
 * Centralized database table creation and management for the AI Explainer plugin.
 * This class handles all database operations including table creation, updates, and cleanup.
 * 
 * @package WP_AI_Explainer
 * @subpackage Core
 * @since 3.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ExplainerPlugin_Database_Setup
 * 
 * Manages all database operations for the AI Explainer plugin including:
 * - Table creation and schema management
 * - Database version tracking and migrations
 * - Table verification and cleanup
 * 
 * @since 3.0.0
 */
class ExplainerPlugin_Database_Setup {
    
    /**
     * Database version for tracking migrations
     * 
     * @var string
     */
    const DB_VERSION = '3.1.1';
    
    /**
     * Option name for storing database version
     * 
     * @var string
     */
    const DB_VERSION_OPTION = 'explainer_database_version';
    
    /**
     * Initialize database setup
     * 
     * Creates all required tables and performs any necessary migrations.
     * Safe to call multiple times - will only create missing tables.
     * 
     * @since 3.0.0
     * @return bool True if all tables created successfully, false otherwise
     */
    public static function initialize() {
        $installed_version = get_option(self::DB_VERSION_OPTION);
        
        // Always run table creation (it's safe - will only create missing tables)
        $tables_created = self::create_all_tables();
        
        if ($tables_created) {
            // Update database version
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
            
            if (function_exists('explainer_log_debug')) {
                explainer_log_debug('Database initialization completed', array(
                    'previous_version' => $installed_version,
                    'current_version' => self::DB_VERSION
                ), 'Database_Setup');
            }
        }
        
        return $tables_created;
    }
    
    /**
     * Create all required database tables
     * 
     * Creates tables based on current live database schema.
     * Uses IF NOT EXISTS to safely handle existing tables.
     * 
     * @since 3.0.0
     * @return bool True if all tables exist after creation attempt
     */
    public static function create_all_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        $success = true;
        
        // 1. Selections table
        $selections_table = $wpdb->prefix . 'ai_explainer_selections';
        $selections_sql = "CREATE TABLE {$selections_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            post_id int(11) DEFAULT NULL,
            text_hash varchar(64) NOT NULL,
            selected_text text NOT NULL,
            ai_explanation text DEFAULT NULL,
            explanation_cached_at datetime DEFAULT NULL,
            manually_edited tinyint(1) DEFAULT 0,
            selection_count int(11) DEFAULT 1,
            first_seen datetime DEFAULT CURRENT_TIMESTAMP,
            last_seen datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            source_urls text DEFAULT NULL,
            enabled tinyint(1) DEFAULT 0,
            source enum('manual','user_selection','ai_scan') DEFAULT 'user_selection',
            reading_level varchar(20) NOT NULL DEFAULT 'standard',
            PRIMARY KEY (id),
            UNIQUE KEY uniq_text_post_level (text_hash, post_id, reading_level),
            KEY idx_count (selection_count),
            KEY idx_last_seen (last_seen),
            KEY idx_cached_at (explanation_cached_at),
            KEY idx_manually_edited (manually_edited),
            KEY idx_reading_level (reading_level),
            KEY idx_post_id (post_id),
            KEY idx_enabled (enabled),
            KEY idx_source (source)
        ) {$charset_collate};";

        $result = dbDelta($selections_sql);
        if (!self::table_exists($selections_table)) {
            $success = false;
        }

        // 2. Blog posts table
        $blog_posts_table = $wpdb->prefix . 'ai_explainer_blog_posts';
        $blog_posts_sql = "CREATE TABLE {$blog_posts_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            source_selection text NOT NULL,
            ai_provider varchar(50) NOT NULL,
            post_length varchar(20) NOT NULL,
            generation_cost decimal(10,5) DEFAULT 0.00000,
            generated_image tinyint(1) DEFAULT 0,
            generated_seo tinyint(1) DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_created_by (created_by),
            KEY idx_created_at (created_at),
            KEY idx_ai_provider (ai_provider)
        ) {$charset_collate};";

        $result = dbDelta($blog_posts_sql);
        if (!self::table_exists($blog_posts_table)) {
            $success = false;
        }

        // 3. Job queue table
        $job_queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        $job_queue_sql = "CREATE TABLE {$job_queue_table} (
            queue_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_type varchar(50) NOT NULL,
            status enum('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
            data longtext DEFAULT NULL,
            priority int(3) DEFAULT 10,
            attempts int(11) NOT NULL DEFAULT 0,
            max_attempts int(11) NOT NULL DEFAULT 3,
            scheduled_at datetime DEFAULT NULL,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            error_message text DEFAULT NULL,
            progress_message varchar(255) DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            selection_text text NOT NULL,
            options longtext NOT NULL,
            result_data longtext DEFAULT NULL,
            post_id bigint(20) unsigned DEFAULT NULL,
            retry_count int(3) DEFAULT 0,
            explanation_id int(11) DEFAULT NULL,
            PRIMARY KEY (queue_id),
            KEY idx_job_type (job_type),
            KEY idx_status (status),
            KEY idx_priority (priority),
            KEY idx_scheduled_at (scheduled_at),
            KEY idx_status_priority_scheduled (status, priority DESC, scheduled_at),
            KEY idx_created_by (created_by),
            KEY idx_priority_created (priority, created_at),
            KEY idx_post_id (post_id),
            KEY idx_explanation_id (explanation_id)
        ) {$charset_collate};";

        $result = dbDelta($job_queue_sql);
        if (!self::table_exists($job_queue_table)) {
            $success = false;
        }

        // 4. Job meta table
        $job_meta_table = $wpdb->prefix . 'ai_explainer_job_meta';
        $job_meta_sql = "CREATE TABLE {$job_meta_table} (
            meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            queue_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext DEFAULT NULL,
            PRIMARY KEY (meta_id),
            KEY idx_queue_id (queue_id),
            KEY idx_meta_key (meta_key),
            KEY idx_queue_meta (queue_id, meta_key)
        ) {$charset_collate};";

        $result = dbDelta($job_meta_sql);
        if (!self::table_exists($job_meta_table)) {
            $success = false;
        }

        // 5. Job progress table
        $job_progress_table = $wpdb->prefix . 'ai_explainer_job_progress';
        $job_progress_sql = "CREATE TABLE {$job_progress_table} (
            progress_id varchar(50) NOT NULL,
            action_id bigint(20) unsigned DEFAULT NULL,
            job_type varchar(50) NOT NULL,
            status enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
            progress_percent int(11) NOT NULL DEFAULT 0,
            progress_text varchar(255) DEFAULT NULL,
            result_data longtext DEFAULT NULL,
            error_message text DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (progress_id),
            KEY idx_action_id (action_id),
            KEY idx_status (status),
            KEY idx_job_type (job_type),
            KEY idx_created_by (created_by),
            KEY idx_updated_at (updated_at)
        ) {$charset_collate};";

        $result = dbDelta($job_progress_sql);
        if (!self::table_exists($job_progress_table)) {
            $success = false;
        }

        // 6. Job relationships table
        $job_relationships_table = $wpdb->prefix . 'ai_explainer_job_relationships';
        $job_relationships_sql = "CREATE TABLE {$job_relationships_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            post_id bigint(20) unsigned NOT NULL,
            job_type varchar(50) NOT NULL,
            terms_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_job_id (job_id),
            KEY idx_post_id (post_id),
            KEY idx_job_type (job_type),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        $result = dbDelta($job_relationships_sql);
        if (!self::table_exists($job_relationships_table)) {
            $success = false;
        }
        
        // Add foreign key constraints separately (dbDelta doesn't handle them well)
        if ($success) {
            $success = self::add_foreign_key_constraints();
        }
        
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('Database table creation completed', array(
                'success' => $success,
                'tables_verified' => self::verify_all_tables()
            ), 'Database_Setup');
        }
        
        return $success;
    }
    
    /**
     * Add foreign key constraints to tables
     * 
     * @since 3.1.0
     * @return bool True if all constraints added successfully
     */
    public static function add_foreign_key_constraints() {
        global $wpdb;
        
        $success = true;
        
        // Foreign key: job_queue.explanation_id -> selections.id (CASCADE DELETE)
        $job_queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        $selections_table = $wpdb->prefix . 'ai_explainer_selections';
        
        // Check if foreign key already exists
        $existing_fk = $wpdb->get_var($wpdb->prepare("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = %s 
            AND COLUMN_NAME = 'explanation_id' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ", $job_queue_table));
        
        if (!$existing_fk) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are prefixed and safe
            $fk_sql = "ALTER TABLE {$job_queue_table}
                       ADD CONSTRAINT fk_job_explanation
                       FOREIGN KEY (explanation_id)
                       REFERENCES {$selections_table}(id)
                       ON DELETE CASCADE";

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL statement with no user input
            $result = $wpdb->query($fk_sql);
            
            if ($result === false) {
                $success = false;
                if (function_exists('explainer_log_debug')) {
                    explainer_log_debug('Failed to add foreign key constraint', array(
                        'table' => $job_queue_table,
                        'error' => $wpdb->last_error
                    ), 'Database_Setup');
                }
            } else {
                if (function_exists('explainer_log_debug')) {
                    explainer_log_debug('Foreign key constraint added successfully', array(
                        'table' => $job_queue_table,
                        'constraint' => 'fk_job_explanation'
                    ), 'Database_Setup');
                }
            }
        }
        
        return $success;
    }
    
    /**
     * Check if a specific table exists
     * 
     * @since 3.0.0
     * @param string $table_name Full table name including prefix
     * @return bool True if table exists, false otherwise
     */
    public static function table_exists($table_name) {
        global $wpdb;
        
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )
        ) === $table_name;
        
        return $table_exists;
    }
    
    /**
     * Verify all required tables exist
     * 
     * @since 3.0.0
     * @return array Status of each required table
     */
    public static function verify_all_tables() {
        global $wpdb;
        
        $required_tables = array(
            'selections' => $wpdb->prefix . 'ai_explainer_selections',
            'blog_posts' => $wpdb->prefix . 'ai_explainer_blog_posts',
            'job_queue' => $wpdb->prefix . 'ai_explainer_job_queue',
            'job_meta' => $wpdb->prefix . 'ai_explainer_job_meta',
            'job_progress' => $wpdb->prefix . 'ai_explainer_job_progress',
            'job_relationships' => $wpdb->prefix . 'ai_explainer_job_relationships'
        );
        
        $table_status = array();
        
        foreach ($required_tables as $key => $table_name) {
            $table_status[$key] = self::table_exists($table_name);
        }
        
        return $table_status;
    }
    
    /**
     * Get current database version
     * 
     * @since 3.0.0
     * @return string|false Database version or false if not installed
     */
    public static function get_installed_version() {
        return get_option(self::DB_VERSION_OPTION, false);
    }
    
    /**
     * Check if database needs initialization
     * 
     * @since 3.0.0
     * @return bool True if database needs initialization
     */
    public static function needs_initialization() {
        $installed_version = self::get_installed_version();
        $table_status = self::verify_all_tables();
        
        // Need initialization if version doesn't match or any table is missing
        return ($installed_version !== self::DB_VERSION) || in_array(false, $table_status, true);
    }
    
    /**
     * Drop all plugin tables
     * 
     * Used during plugin uninstall to clean up the database.
     * Handles foreign key constraints properly.
     * 
     * @since 3.0.0
     * @return void
     */
    public static function drop_all_tables() {
        global $wpdb;
        
        // Tables to drop in correct order (respecting foreign key constraints)
        $tables_to_drop = array(
            $wpdb->prefix . 'ai_explainer_job_relationships',
            $wpdb->prefix . 'ai_explainer_job_meta',
            $wpdb->prefix . 'ai_explainer_job_progress', 
            $wpdb->prefix . 'ai_explainer_job_queue',
            $wpdb->prefix . 'ai_explainer_blog_posts',
            $wpdb->prefix . 'ai_explainer_selections'
        );
        
        // Temporarily disable foreign key checks for clean removal
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
        
        foreach ($tables_to_drop as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }
        
        // Re-enable foreign key checks
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
        
        // Remove database version option
        delete_option(self::DB_VERSION_OPTION);
        
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('All plugin tables dropped', array(
                'tables_dropped' => count($tables_to_drop)
            ), 'Database_Setup');
        }
    }
    
    /**
     * Reset all plugin tables
     * 
     * Truncates all data from plugin tables while preserving structure.
     * Useful for development and testing purposes.
     * 
     * @since 3.0.0
     * @return void
     */
    public static function reset_all_tables() {
        global $wpdb;
        
        $tables_to_reset = array(
            $wpdb->prefix . 'ai_explainer_job_relationships',
            $wpdb->prefix . 'ai_explainer_job_meta',
            $wpdb->prefix . 'ai_explainer_job_progress',
            $wpdb->prefix . 'ai_explainer_job_queue',
            $wpdb->prefix . 'ai_explainer_blog_posts',
            $wpdb->prefix . 'ai_explainer_selections'
        );
        
        // Temporarily disable foreign key checks
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
        
        foreach ($tables_to_reset as $table) {
            if (self::table_exists($table)) {
                $wpdb->query("TRUNCATE TABLE `{$table}`");
            }
        }
        
        // Re-enable foreign key checks
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
        
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('All plugin tables reset', array(
                'tables_reset' => count($tables_to_reset)
            ), 'Database_Setup');
        }
    }
}