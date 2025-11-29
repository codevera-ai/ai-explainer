<?php
/**
 * Abstract Job Queue Widget Base Class
 * 
 * Provides foundational functionality for job queue processing widgets
 * with standardised behaviour, logging, progress tracking, and error handling.
 * 
 * @package WP_AI_Explainer
 * @subpackage Core
 * @since 2.2.0
 */

namespace WPAIExplainer\JobQueue;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract class ExplainerPlugin_Job_Queue_Widget
 * 
 * Base class for all job queue processing widgets. Enforces consistent interface
 * and provides utility methods for logging, progress tracking, and metadata management.
 * 
 * @since 2.2.0
 */
abstract class ExplainerPlugin_Job_Queue_Widget {
    
    /**
     * Job type identifier for this widget
     * 
     * @var string
     */
    protected $job_type;
    
    /**
     * Reference to the job queue manager instance
     * 
     * @var object|null
     */
    protected $manager = null;
    
    /**
     * Current job being processed
     * 
     * @var array|null
     */
    protected $current_job = null;
    
    /**
     * Constructor
     * 
     * @since 2.2.0
     * @param string $job_type Unique identifier for this job type
     * @throws \InvalidArgumentException If job_type is empty or invalid
     */
    public function __construct($job_type) {
        if (empty($job_type) || !is_string($job_type)) {
            throw new \InvalidArgumentException(esc_html('Job type must be a non-empty string'));
        }
        
        // Sanitise job type for security
        $this->job_type = sanitize_key($job_type);
        
        // Note: Permission validation moved to process_batch() method
        // to avoid calling user functions before WordPress is fully loaded
    }
    
    /**
     * Get widget configuration
     * 
     * Must return an array with required configuration keys:
     * - name: Human-readable widget name
     * - description: Widget purpose description
     * - batch_size: Number of items to process per batch
     * - priority: Default job priority (1-100)
     * - max_attempts: Maximum retry attempts for failures
     * 
     * @since 2.2.0
     * @return array Widget configuration array
     */
    abstract public function get_config();
    
    /**
     * Get items to be processed
     * 
     * Returns an array of items that need processing. Each item
     * should be a self-contained unit of work.
     * 
     * @since 2.2.0
     * @return array Array of items to process
     */
    abstract public function get_items();
    
    /**
     * Process a single item
     * 
     * Processes one item from the batch. Should return result array
     * or throw exception on failure.
     * 
     * @since 2.2.0
     * @param mixed $item The item to process
     * @return array Processing result
     * @throws \Exception On processing failure
     */
    abstract public function process_item($item);
    
    /**
     * Validate user permissions
     * 
     * Ensures the current user has sufficient permissions to execute
     * job queue operations.
     * 
     * @since 2.2.0
     * @throws \Exception If user lacks required permissions
     */
    protected function validate_permissions() {
        // Skip permission check for CLI/cron contexts
        if (defined('WP_CLI') || defined('DOING_CRON')) {
            return;
        }
        
        // Check WordPress admin capability
        if (!current_user_can('manage_options')) {
            $error_message = __('Insufficient permissions for job queue operations', 'ai-explainer');
            
            // Log the permission failure
            $this->log($error_message, 'error');
            
            // Use WordPress error handling
            wp_die(
                esc_html($error_message),
                esc_html__('Permission Denied', 'ai-explainer'),
                array('response' => 403)
            );
        }
    }
    
    /**
     * Log a message
     * 
     * Logs messages to WordPress error log and optionally to custom
     * logging system for job queue operations.
     * 
     * @since 2.2.0
     * @param string $message Message to log
     * @param string $level Log level (info, warning, error, debug)
     */
    protected function log($message, $level = 'info') {
        // Sanitise inputs
        $message = sanitize_text_field($message);
        $level = sanitize_key($level);

        // Format log entry
        $log_entry = sprintf('[%s] [JobQueue:%s] [%s] %s', current_time('Y-m-d H:i:s'), esc_html($this->job_type), strtoupper($level), esc_html($message));

        // Store in job metadata if processing
        if ($this->current_job) {
            $this->add_to_job_log($log_entry);
        }
        
        // Trigger WordPress action for external logging
        do_action('explainer_job_queue_log', $message, $level, $this->job_type);
    }
    
    /**
     * Update processing progress
     * 
     * Updates the progress of the current job processing batch.
     * 
     * @since 2.2.0
     * @param int $completed Number of items completed
     * @param int $total Total number of items
     */
    protected function update_progress($completed, $total) {
        // Validate inputs
        $completed = absint($completed);
        $total = absint($total);
        
        if ($total === 0) {
            return;
        }
        
        // Calculate percentage
        $percentage = min(100, round(($completed / $total) * 100));
        
        // Create progress data
        $progress_data = array(
            'job_type' => $this->job_type,
            'completed' => $completed,
            'total' => $total,
            'percentage' => $percentage,
            'timestamp' => current_time('timestamp'),
            'job_id' => $this->get_current_job_id()
        );
        
        // Store in transient for quick access
        $transient_key = 'explainer_job_queue_progress_' . $this->job_type;
        set_transient($transient_key, $progress_data, HOUR_IN_SECONDS);
        
        // Update job metadata
        if ($this->current_job) {
            $this->update_job_meta('progress_completed', $completed);
            $this->update_job_meta('progress_total', $total);
            $this->update_job_meta('progress_percentage', $percentage);
        }
        
        // Trigger progress update action
        do_action('explainer_job_queue_progress_update', $progress_data);
    }
    
    /**
     * Check if processing should continue
     * 
     * Checks various conditions to determine if job processing
     * should continue or be halted.
     * 
     * @since 2.2.0
     * @return bool True if should continue, false otherwise
     */
    protected function should_continue() {
        // Check for stop flag in transient
        $stop_flag = get_transient('explainer_job_queue_stop_' . $this->job_type);
        if ($stop_flag) {
            $this->log('Processing stopped by user request', 'info');
            return false;
        }
        
        // Check memory usage (80% threshold)
        $memory_limit = $this->get_memory_limit();
        $memory_usage = memory_get_usage(true);
        if ($memory_usage > ($memory_limit * 0.8)) {
            $this->log('Stopping due to high memory usage', 'warning');
            return false;
        }
        
        // Check execution time (80% of max_execution_time)
        $max_execution_time = ini_get('max_execution_time');
        if ($max_execution_time > 0) {
            $request_time = defined('WP_START_TIMESTAMP') ? WP_START_TIMESTAMP : (isset($_SERVER['REQUEST_TIME']) ? absint(wp_unslash($_SERVER['REQUEST_TIME'])) : time());
            $elapsed_time = time() - $request_time;
            if ($elapsed_time > ($max_execution_time * 0.8)) {
                $this->log('Stopping due to execution time limit', 'warning');
                return false;
            }
        }
        
        // Allow custom conditions via filter
        return apply_filters('explainer_job_queue_should_continue', true, $this->job_type, $this->current_job);
    }
    
    /**
     * Get current job ID
     * 
     * Returns the ID of the currently processing job.
     * 
     * @since 2.2.0
     * @return int|null Job ID or null if no job is set
     */
    protected function get_current_job_id() {
        return isset($this->current_job['queue_id']) ? absint($this->current_job['queue_id']) : null;
    }
    
    /**
     * Hook called before processing a batch
     * 
     * Override in child classes to perform setup operations
     * before batch processing begins.
     * 
     * @since 2.2.0
     * @param array $items Items to be processed
     */
    public function before_batch($items) {
        // Validate permissions when actually processing jobs
        // This is deferred from constructor to ensure WordPress is fully loaded
        $this->validate_permissions();
        
        // Default empty implementation
        // Child classes can override for custom behaviour
    }
    
    /**
     * Hook called after processing a batch
     * 
     * Override in child classes to perform cleanup operations
     * after batch processing completes.
     * 
     * @since 2.2.0
     * @param array $items Items that were processed
     * @param array $results Processing results
     */
    public function after_batch($items, $results) {
        // Default empty implementation
        // Child classes can override for custom behaviour
    }
    
    /**
     * Hook called when an error occurs
     * 
     * Override in child classes for custom error handling.
     * Default implementation logs error and determines retry.
     * 
     * @since 2.2.0
     * @param mixed $item Item that caused the error
     * @param \Exception $error The error that occurred
     * @return bool True to retry, false to skip
     */
    public function on_error($item, $error) {
        // Log the error
        $this->log(
            sprintf('Error processing item: %s', esc_html($error->getMessage())), 'error');
        
        // Default retry behaviour based on attempts
        if ($this->current_job) {
            $attempts = isset($this->current_job['attempts']) ? absint($this->current_job['attempts']) : 0;
            $max_attempts = isset($this->current_job['max_attempts']) ? absint($this->current_job['max_attempts']) : 3;
            
            return $attempts < $max_attempts;
        }
        
        return false;
    }
    
    /**
     * Hook called when job completes successfully
     * 
     * Override in child classes for custom completion handling.
     * 
     * @since 2.2.0
     */
    protected function on_complete() {
        $this->log('Job completed successfully', 'info');
        
        // Clear progress transient
        delete_transient('explainer_job_queue_progress_' . $this->job_type);
        
        // Trigger completion action
        do_action('explainer_job_queue_complete', $this->job_type, $this->current_job);
    }
    
    /**
     * Hook called when job fails permanently
     * 
     * Override in child classes for custom failure handling.
     * 
     * @since 2.2.0
     */
    public function on_failure() {
        $this->log('Job failed permanently', 'error');
        
        // Clear progress transient
        delete_transient('explainer_job_queue_progress_' . $this->job_type);
        
        // Trigger failure action
        do_action('explainer_job_queue_failure', $this->job_type, $this->current_job);
    }
    
    /**
     * Update job metadata
     * 
     * Stores metadata associated with the current job in the database.
     * 
     * @since 2.2.0
     * @param string $key Metadata key
     * @param mixed $value Metadata value
     * @return bool True on success, false on failure
     */
    protected function update_job_meta($key, $value) {
        global $wpdb;
        
        $job_id = $this->get_current_job_id();
        if (!$job_id) {
            return false;
        }
        
        // Sanitise key
        $key = sanitize_key($key);
        
        // Serialise complex values
        $value = maybe_serialize($value);
        
        // Check if meta exists
        $meta_table = $wpdb->prefix . 'ai_explainer_job_meta';
        $existing = $wpdb->get_var($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefixed with $wpdb->prefix
            "SELECT meta_id FROM {$meta_table} WHERE queue_id = %d AND meta_key = %s",
            $job_id,
            $key
        ));
        
        if ($existing) {
            // Update existing meta
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Custom table with proper indexes (meta_key, queue_id+meta_key)
            $result = $wpdb->update(
                $meta_table,
                array('meta_value' => $value),
                array(
                    'queue_id' => $job_id,
                    'meta_key' => $key
                ),
                array('%s'),
                array('%d', '%s')
            );
        } else {
            // Insert new meta
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Custom table with proper indexes (meta_key, queue_id+meta_key)
            $result = $wpdb->insert(
                $meta_table,
                array(
                    'queue_id' => $job_id,
                    'meta_key' => $key,
                    'meta_value' => $value
                ),
                array('%d', '%s', '%s')
            );
        }
        
        return $result !== false;
    }
    
    /**
     * Get job metadata
     * 
     * Retrieves metadata associated with the current job from the database.
     * 
     * @since 2.2.0
     * @param string $key Metadata key
     * @param mixed $default Default value if meta doesn't exist
     * @return mixed Metadata value or default
     */
    protected function get_job_meta($key, $default = null) {
        global $wpdb;
        
        $job_id = $this->get_current_job_id();
        if (!$job_id) {
            return $default;
        }
        
        // Sanitise key
        $key = sanitize_key($key);
        
        // Retrieve meta value
        $meta_table = $wpdb->prefix . 'ai_explainer_job_meta';
        $value = $wpdb->get_var($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefixed with $wpdb->prefix
            "SELECT meta_value FROM {$meta_table} WHERE queue_id = %d AND meta_key = %s",
            $job_id,
            $key
        ));
        
        if ($value === null) {
            return $default;
        }
        
        // Unserialise if needed
        return maybe_unserialize($value);
    }
    
    /**
     * Set the current job context
     * 
     * Sets the job that is currently being processed.
     * 
     * @since 2.2.0
     * @param array $job Job data from database
     */
    public function set_current_job($job) {
        $this->current_job = $job;
    }
    
    /**
     * Get the job type identifier
     * 
     * Returns the job type string for this widget.
     * 
     * @since 2.2.0
     * @return string Job type identifier
     */
    public function get_job_type() {
        return $this->job_type;
    }
    
    /**
     * Set the job queue manager reference
     * 
     * @since 2.2.0
     * @param object $manager Job queue manager instance
     */
    public function set_manager($manager) {
        $this->manager = $manager;
    }
    
    /**
     * Get memory limit in bytes
     * 
     * @since 2.2.0
     * @return int Memory limit in bytes
     */
    private function get_memory_limit() {
        $memory_limit = ini_get('memory_limit');
        
        if ($memory_limit == -1) {
            // Unlimited
            return PHP_INT_MAX;
        }
        
        // Convert to bytes
        $unit = strtolower(substr($memory_limit, -1));
        $value = (int) $memory_limit;
        
        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Add entry to job log
     * 
     * @since 2.2.0
     * @param string $entry Log entry to add
     */
    private function add_to_job_log($entry) {
        $log = $this->get_job_meta('processing_log', array());
        
        // Limit log size to prevent excessive database growth
        if (count($log) >= 1000) {
            array_shift($log);
        }
        
        $log[] = $entry;
        $this->update_job_meta('processing_log', $log);
    }
}