<?php
/**
 * Job Queue Configuration Class
 *
 * Centralised configuration for job queue system settings and requirements.
 * Provides static methods for accessing configuration without instantiation.
 *
 * @link       https://wpaiexplainer.com
 * @since      1.4.0
 *
 * @package    WP_AI_Explainer
 * @subpackage WP_AI_Explainer/includes/core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Static configuration class for job queue system.
 *
 * Manages all job queue settings, defaults, requirements, and validation
 * using static methods for global access without instantiation.
 *
 * @since      1.4.0
 * @package    WP_AI_Explainer
 * @subpackage WP_AI_Explainer/includes/core
 */
final class ExplainerPlugin_Job_Queue_Config
{
    /**
     * Job queue system version for feature tracking.
     *
     * @since 1.4.0
     * @var string
     */
    const VERSION = '1.4.0';

    /**
     * Database schema version for migrations.
     *
     * @since 1.4.0
     * @var string
     */
    const DB_VERSION = '1.0';

    /**
     * Minimum WordPress version required.
     *
     * @since 1.4.0
     * @var string
     */
    const MIN_WP_VERSION = '5.0';

    /**
     * Minimum PHP version required.
     *
     * @since 1.4.0
     * @var string
     */
    const MIN_PHP_VERSION = '7.4';

    /**
     * Prevent instantiation of static class.
     *
     * @since 1.4.0
     */
    private function __construct() {}

    /**
     * Prevent cloning of static class.
     *
     * @since 1.4.0
     */
    private function __clone() {}

    /**
     * Prevent unserialization of static class.
     *
     * @since 1.4.0
     */
    public function __wakeup()
    {
        throw new Exception(esc_html(__('Cannot unserialize static class', 'ai-explainer')));
    }

    /**
     * Get comprehensive default settings for job queue system.
     *
     * Returns all configurable options with sensible defaults including
     * batch sizes, timeouts, retry settings, notifications, and cleanup.
     *
     * @since 1.4.0
     * @return array Default settings array
     */
    public static function get_default_settings()
    {
        return array(
            'max_batch_size' => 50,
            'default_timeout' => 30,
            'max_attempts' => 3,
            'retry_delay' => 300,
            'cleanup_days' => 30,
            'log_retention_days' => 7,
            'enable_progress_tracking' => true,
            'enable_email_notifications' => false,
            'notification_email' => get_option('admin_email', ''),
            'memory_limit' => '512M',
            'max_execution_time' => 0,
            'chunk_processing' => true,
            'error_handling_mode' => 'continue',
            'priority_levels' => array('low', 'normal', 'high', 'critical'),
            'default_priority' => 'normal',
            'enable_logging' => true,
            'log_level' => 'info',
            'batch_delay' => 1,
            'maintenance_mode' => false,
            'auto_cleanup' => true,
            'queue_limit' => 1000
        );
    }

    /**
     * Get JavaScript configuration for admin interface.
     *
     * Provides frontend-safe settings with localisation, AJAX URLs,
     * nonces, and brand colours for admin interface integration.
     *
     * @since 1.4.0
     * @return array JavaScript configuration array
     */
    public static function get_js_config()
    {
        return array(
            'settings' => self::get_js_settings(),
            'strings' => self::get_localised_strings(),
            'ajax' => array(
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('job_queue_ajax_nonce')
            ),
            'brandColors' => function_exists('explainer_get_brand_colors_for_js') 
                ? explainer_get_brand_colors_for_js() 
                : array(),
            'version' => self::VERSION
        );
    }

    /**
     * Get safe JavaScript settings.
     *
     * Returns settings that are safe to expose to frontend JavaScript,
     * excluding sensitive server configuration.
     *
     * @since 1.4.0
     * @return array Safe settings for JavaScript
     */
    private static function get_js_settings()
    {
        $defaults = self::get_default_settings();
        
        return array(
            'maxBatchSize' => $defaults['max_batch_size'],
            'defaultTimeout' => $defaults['default_timeout'],
            'enableProgressTracking' => $defaults['enable_progress_tracking'],
            'priorityLevels' => $defaults['priority_levels'],
            'defaultPriority' => $defaults['default_priority'],
            'batchDelay' => $defaults['batch_delay'],
            'queueLimit' => $defaults['queue_limit']
        );
    }

    /**
     * Get localised strings for JavaScript.
     *
     * Returns all user-facing strings with internationalisation support
     * for use in admin interface components.
     *
     * @since 1.4.0
     * @return array Localised strings array
     */
    private static function get_localised_strings()
    {
        return array(
            'confirmStart' => __('Are you sure you want to start the job queue?', 'ai-explainer'),
            'confirmStop' => __('Are you sure you want to stop the job queue?', 'ai-explainer'),
            'confirmClear' => __('Are you sure you want to clear all jobs? This cannot be undone.', 'ai-explainer'),
            'confirmRetry' => __('Are you sure you want to retry failed jobs?', 'ai-explainer'),
            'processing' => __('Processing...', 'ai-explainer'),
            'starting' => __('Starting job queue...', 'ai-explainer'),
            'stopping' => __('Stopping job queue...', 'ai-explainer'),
            'clearing' => __('Clearing jobs...', 'ai-explainer'),
            'retrying' => __('Retrying failed jobs...', 'ai-explainer'),
            'success' => __('Operation completed successfully', 'ai-explainer'),
            'error' => __('An error occurred. Please try again.', 'ai-explainer'),
            'networkError' => __('Network error. Please check your connection.', 'ai-explainer'),
            'jobsRemaining' => __('jobs remaining', 'ai-explainer'),
            'jobsCompleted' => __('jobs completed', 'ai-explainer'),
            'jobsFailed' => __('jobs failed', 'ai-explainer'),
            'queueEmpty' => __('Queue is empty', 'ai-explainer'),
            'queueRunning' => __('Queue is running', 'ai-explainer'),
            'queueStopped' => __('Queue is stopped', 'ai-explainer'),
            'noJobsFound' => __('No jobs found', 'ai-explainer'),
            'refreshing' => __('Refreshing...', 'ai-explainer')
        );
    }

    /**
     * Get default widget configuration template.
     *
     * Provides standardised defaults for new widgets including
     * batch sizes, timeouts, priorities, and brand integration.
     *
     * @since 1.4.0
     * @return array Default widget configuration
     */
    public static function get_default_widget_config()
    {
        return array(
            'batch_size' => 25,
            'timeout' => 60,
            'max_attempts' => 3,
            'retry_delay' => 300,
            'priority' => 'normal',
            'memory_limit' => '256M',
            'enable_progress_tracking' => true,
            'enable_brand_colors' => true,
            'error_handling' => 'continue',
            'chunk_processing' => true,
            'auto_start' => false,
            'notification_events' => array('completion', 'failure'),
            'ui_config' => array(
                'show_progress_bar' => true,
                'show_status_text' => true,
                'show_controls' => true,
                'theme' => 'default'
            ),
            'performance_config' => array(
                'throttle_requests' => true,
                'request_delay' => 100,
                'concurrent_limit' => 5
            )
        );
    }

    /**
     * Validate system requirements for job queue.
     *
     * Performs comprehensive environment checking including PHP version,
     * WordPress version, database tables, and file permissions.
     *
     * @since 1.4.0
     * @return array Validation results with errors and warnings
     */
    public static function validate_requirements()
    {
        global $wpdb;
        
        $results = array(
            'valid' => true,
            'errors' => array(),
            'warnings' => array(),
            'checks' => array()
        );

        // Check PHP version
        $php_version = PHP_VERSION;
        $min_php = self::MIN_PHP_VERSION;
        $php_check = array(
            'name' => 'PHP Version',
            'current' => $php_version,
            'required' => $min_php,
            'status' => version_compare($php_version, $min_php, '>=') ? 'pass' : 'fail'
        );
        
        if ($php_check['status'] === 'fail') {
            $results['valid'] = false;
            $results['errors'][] = sprintf(
                /* translators: %1$s: minimum PHP version required, %2$s: current PHP version */
                __('PHP %1$s or higher is required. Current version: %2$s', 'ai-explainer'),
                $min_php,
                $php_version
            );
        }
        $results['checks']['php_version'] = $php_check;

        // Check WordPress version
        $wp_version = get_bloginfo('version');
        $min_wp = self::MIN_WP_VERSION;
        $wp_check = array(
            'name' => 'WordPress Version',
            'current' => $wp_version,
            'required' => $min_wp,
            'status' => version_compare($wp_version, $min_wp, '>=') ? 'pass' : 'fail'
        );
        
        if ($wp_check['status'] === 'fail') {
            $results['valid'] = false;
            $results['errors'][] = sprintf(
                /* translators: %1$s: minimum WordPress version required, %2$s: current WordPress version */
                __('WordPress %1$s or higher is required. Current version: %2$s', 'ai-explainer'),
                $min_wp,
                $wp_version
            );
        }
        $results['checks']['wp_version'] = $wp_check;

        // Check database tables
        $required_tables = array(
            $wpdb->prefix . 'ai_explainer_job_queue',
            $wpdb->prefix . 'explainer_job_queue_logs'
        );
        
        $missing_tables = array();
        foreach ($required_tables as $table) {
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if (!$table_exists) {
                $missing_tables[] = $table;
            }
        }
        
        $db_check = array(
            'name' => 'Database Tables',
            'status' => empty($missing_tables) ? 'pass' : 'fail',
            'missing_tables' => $missing_tables
        );
        
        if (!empty($missing_tables)) {
            $results['valid'] = false;
            $results['errors'][] = sprintf(
                /* translators: %1$s: comma-separated list of missing database table names */
                __('Missing database tables: %s', 'ai-explainer'),
                implode(', ', $missing_tables)
            );
        }
        $results['checks']['database'] = $db_check;

        // Check file permissions
        $upload_dir = wp_upload_dir();
        $test_file = $upload_dir['basedir'] . '/explainer_test_' . time() . '.tmp';
        
        $file_check = array(
            'name' => 'File Permissions',
            'path' => $upload_dir['basedir'],
            'status' => 'unknown'
        );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Required to verify upload directory permissions for job queue log files before attempting write operations
        if (is_writable($upload_dir['basedir'])) {
            // Test actual write permissions
            $write_test = @file_put_contents($test_file, 'test');
            if ($write_test !== false) {
                wp_delete_file($test_file);
                $file_check['status'] = 'pass';
            } else {
                $file_check['status'] = 'fail';
                $results['warnings'][] = __('Upload directory is not writable for log files', 'ai-explainer');
            }
        } else {
            $file_check['status'] = 'fail';
            $results['warnings'][] = __('Upload directory is not writable for log files', 'ai-explainer');
        }
        $results['checks']['file_permissions'] = $file_check;

        // Check memory limit
        $memory_limit = ini_get('memory_limit');
        $memory_check = array(
            'name' => 'Memory Limit',
            'current' => $memory_limit,
            'status' => 'info'
        );
        
        // Convert memory limit to bytes for comparison
        $memory_bytes = self::convert_memory_to_bytes($memory_limit);
        $min_memory_bytes = self::convert_memory_to_bytes('256M');
        
        if ($memory_bytes > 0 && $memory_bytes < $min_memory_bytes) {
            $results['warnings'][] = sprintf(
                /* translators: %1$s: current memory limit value (e.g., 128M) */
                __('Memory limit (%s) may be too low for processing large batches. Recommended: 256M or higher', 'ai-explainer'),
                $memory_limit
            );
        }
        $results['checks']['memory'] = $memory_check;

        return $results;
    }

    /**
     * Convert memory limit string to bytes.
     *
     * @since 1.4.0
     * @param string $memory_limit Memory limit string (e.g., '256M', '1G')
     * @return int Memory limit in bytes
     */
    private static function convert_memory_to_bytes($memory_limit)
    {
        if ($memory_limit == '-1') {
            return -1; // Unlimited
        }
        
        $memory_limit = trim($memory_limit);
        $last_char = strtolower(substr($memory_limit, -1));
        $value = (int) substr($memory_limit, 0, -1);
        
        switch ($last_char) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return (int) $memory_limit;
        }
    }
}