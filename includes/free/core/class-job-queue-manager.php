<?php
/**
 * Job Queue Manager
 * 
 * Central singleton manager that coordinates widget registration, job processing,
 * and queue operations for the AI Explainer plugin background processing system.
 * 
 * @package WP_AI_Explainer
 * @subpackage Core
 * @since 2.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Import the namespaced abstract class
use WPAIExplainer\JobQueue\ExplainerPlugin_Job_Queue_Widget;

/**
 * Class ExplainerPlugin_Job_Queue_Manager
 * 
 * Singleton manager for coordinating job queue operations with widget registration,
 * job processing, queue management, and AJAX handling for administrative control.
 * 
 * @since 2.3.0
 */
class ExplainerPlugin_Job_Queue_Manager {
    
    /**
     * Single instance of the manager
     * 
     * @var ExplainerPlugin_Job_Queue_Manager|null
     */
    private static $instance = null;
    
    /**
     * Registered job queue widgets
     * 
     * @var array
     */
    private $widgets = array();
    
    /**
     * Currently active jobs
     * 
     * @var array
     */
    private $active_jobs = array();
    
    /**
     * Cached brand colours
     * 
     * @var array|null
     */
    private $brand_colours = null;
    
    /**
     * Private constructor to enforce singleton pattern
     * 
     * @since 2.3.0
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Get single instance of the manager
     * 
     * @since 2.3.0
     * @return ExplainerPlugin_Job_Queue_Manager Single instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Prevent cloning of the instance
     * 
     * @since 2.3.0
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization of the instance
     * 
     * @since 2.3.0
     */
    public function __wakeup() {
        throw new \Exception(esc_html('Cannot unserialize singleton'));
    }
    
    /**
     * Initialise WordPress hooks
     * 
     * @since 2.3.0
     */
    private function init_hooks() {
        // AJAX handlers for admin operations
        add_action('wp_ajax_explainer_job_queue_admin', array($this, 'handle_ajax'));
        
        // Ensure database tables exist
        add_action('init', array($this, 'check_database'));
        
        // Cache brand colours on init
        add_action('init', array($this, 'cache_brand_colours'));
    }
    
    /**
     * Check and create database tables if needed
     * 
     * @since 2.3.0
     */
    public function check_database() {
        ExplainerPlugin_Database_Setup::initialize();
    }
    
    /**
     * Cache brand colours for interface styling
     * 
     * @since 2.3.0
     */
    public function cache_brand_colours() {
        if ($this->brand_colours === null) {
            $this->brand_colours = explainer_get_brand_colors();
        }
    }
    
    /**
     * Register a job queue widget
     * 
     * Validates and stores a job queue widget with configuration validation
     * and permission checking.
     * 
     * @since 2.3.0
     * @param ExplainerPlugin_Job_Queue_Widget $widget Widget instance to register
     * @return bool True on successful registration, false otherwise
     * @throws \InvalidArgumentException If widget is invalid
     */
    public function register_widget($widget) {
        // Validate widget type
        if (!($widget instanceof ExplainerPlugin_Job_Queue_Widget)) {
            throw new \InvalidArgumentException(esc_html('Widget must extend ExplainerPlugin_Job_Queue_Widget'));
        }
        
        // Simplified permission checking - allow registration in more contexts
        $is_cli = defined('WP_CLI') && WP_CLI;
        $is_direct_execution = defined('EXPLAINER_DIRECT_JOB_EXECUTION') && EXPLAINER_DIRECT_JOB_EXECUTION;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET check for cron context, no data modification
        $is_server_cron = (isset($_GET['explainer_cron']) && $_GET['explainer_cron'] === 'run');
        $is_wp_cron = defined('DOING_CRON') && DOING_CRON;
        $is_admin_with_capability = is_admin() && current_user_can('manage_options');
        $is_ajax_admin = (defined('DOING_AJAX') && DOING_AJAX) && is_admin();

        // Allow registration in safe contexts with fallback for cron operations
        $allow_registration = $is_cli ||
                              $is_direct_execution ||
                              $is_server_cron ||
                              $is_wp_cron ||
                              $is_admin_with_capability ||
                              $is_ajax_admin;
                              
        if (!$allow_registration) {
            // Log with context but continue processing for backward compatibility
            $this->log('Widget registration in restricted context, allowing with warning', 'warning', array(
                'is_cli' => $is_cli,
                'is_ajax' => defined('DOING_AJAX') && DOING_AJAX,
                'is_admin' => is_admin(),
                'is_direct_execution' => $is_direct_execution,
                'is_server_cron' => $is_server_cron,
                'is_wp_cron' => $is_wp_cron,
                'user_id' => get_current_user_id(),
                'request_uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : 'unknown'
            ));
        }
        
        // Get widget configuration
        $config = $widget->get_config();
        
        // Validate configuration
        if (!$this->validate_config($config)) {
            throw new \InvalidArgumentException(esc_html('Invalid widget configuration'));
        }
        
        // Get job type from widget
        $job_type = $widget->get_job_type();
        
        // Check if widget is already registered to prevent duplicates
        if (isset($this->widgets[$job_type])) {
            return true; // Widget already registered, skip logging and action
        }
        
        // Set manager reference in widget
        $widget->set_manager($this);
        
        // Store widget
        $this->widgets[$job_type] = $widget;
        
        // Log successful registration
        $this->log(sprintf('Widget registered: %s (%s)', esc_html($config['name']), esc_html($job_type)), 'info', array(
            'job_type' => $job_type, 'widget_config' => $config
        ));
        
        // Trigger action for external handling
        do_action('explainer_job_queue_widget_registered', $job_type, $widget, $config);
        
        return true;
    }
    
    /**
     * Validate widget configuration
     * 
     * Ensures required configuration keys are present and valid.
     * 
     * @since 2.3.0
     * @param array $config Configuration array to validate
     * @return bool True if valid, false otherwise
     */
    private function validate_config($config) {
        // Updated to match story requirements
        $required_keys = array('name', 'description', 'batch_size', 'priority', 'max_attempts');
        
        foreach ($required_keys as $key) {
            if (!isset($config[$key])) {
                return false;
            }
        }
        
        // Validate data types
        if (!is_string($config['name']) || empty($config['name'])) {
            return false;
        }
        
        if (!is_string($config['description']) || empty($config['description'])) {
            return false;
        }
        
        if (!is_int($config['batch_size']) || $config['batch_size'] < 1) {
            return false;
        }
        
        if (!is_int($config['priority']) || $config['priority'] < 1 || $config['priority'] > 100) {
            return false;
        }
        
        if (!is_int($config['max_attempts']) || $config['max_attempts'] < 1) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get registered widget by job type
     * 
     * @since 2.3.0
     * @param string $job_type Job type identifier
     * @return ExplainerPlugin_Job_Queue_Widget|null Widget instance or null
     */
    public function get_widget($job_type) {
        $job_type = sanitize_key($job_type);
        
        // Additional security: ensure job_type is alphanumeric with underscores only
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $job_type)) {
            $this->log(sprintf('Invalid job type format: %s', esc_html($job_type)), 'warning', array(
                'job_type' => $job_type
            ));
            return null;
        }
        
        return isset($this->widgets[$job_type]) ? $this->widgets[$job_type] : null;
    }
    
    /**
     * Get all registered widgets
     * 
     * @since 2.3.0
     * @return array Array of registered widgets
     */
    public function get_all_widgets() {
        return $this->widgets;
    }
    
    /**
     * Queue a single job
     * 
     * Adds a job to the queue with specified data and options.
     * 
     * @since 2.3.0
     * @param string $job_type Job type identifier
     * @param mixed $data Job data to process
     * @param array $options Optional job options
     * @return int|false Job ID on success, false on failure
     */
    public function queue_job($job_type, $data, $options = array()) {
        global $wpdb;
        
        // Sanitise job type
        $job_type = sanitize_key($job_type);
        
        // Check if widget is registered
        $widget = $this->get_widget($job_type);
        if (!$widget) {
            return false;
        }
        
        // Get widget config for defaults
        $config = $widget->get_config();
        
        // Merge options with defaults
        $defaults = array(
            'priority' => $config['priority'],
            'max_attempts' => $config['max_attempts'],
            'scheduled_at' => null,
            'created_by' => get_current_user_id()
        );
        $options = array_merge($defaults, $options);
        
        // Prepare job data
        $job_data = array(
            'job_type' => $job_type,
            'status' => 'pending',
            'data' => maybe_serialize($data),
            'priority' => absint($options['priority']),
            'max_attempts' => absint($options['max_attempts']),
            'scheduled_at' => $options['scheduled_at'],
            'created_by' => absint($options['created_by']),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        // For blog creation jobs, also populate separate columns for better admin display
        if ($job_type === 'blog_creation' && is_array($data)) {
            $job_data['selection_text'] = $data['selection_text'] ?? '';
            $job_data['options'] = maybe_serialize($data['options'] ?? array());
            
            // Store explanation_id if provided
            if (isset($data['options']['selection_id']) && is_numeric($data['options']['selection_id'])) {
                $job_data['explanation_id'] = absint($data['options']['selection_id']);
            }
        }
        
        // Insert job into database
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        
        // Prepare format array based on job data keys
        $formats = array();
        foreach ($job_data as $key => $value) {
            switch ($key) {
                case 'priority':
                case 'max_attempts':
                case 'created_by':
                case 'explanation_id':
                    $formats[] = '%d';
                    break;
                default:
                    $formats[] = '%s';
                    break;
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $result = $wpdb->insert(
            $queue_table,
            $job_data,
            $formats
        );
        
        if ($result === false) {
            return false;
        }
        
        $job_id = $wpdb->insert_id;
        
        // Store metadata if provided
        if (isset($options['metadata']) && is_array($options['metadata'])) {
            foreach ($options['metadata'] as $key => $value) {
                $this->store_job_meta($job_id, sanitize_key($key), $value);
            }
        }
        
        // Trigger legacy action
        do_action('explainer_job_queue_job_queued', $job_id, $job_type, $data);

        // Invalidate cache after queuing job
        wp_cache_delete('queue_status_' . $job_type, 'explainer_job_queue');
        wp_cache_delete('processing_statistics', 'explainer_job_queue');

        return $job_id;
    }
    
    /**
     * Queue multiple jobs in batch
     * 
     * @since 2.3.0
     * @param string $job_type Job type identifier
     * @param array $jobs Array of job data
     * @param array $options Common options for all jobs
     * @return array Array of job IDs
     */
    public function queue_multiple_jobs($job_type, $jobs, $options = array()) {
        $job_ids = array();
        
        foreach ($jobs as $job_data) {
            $job_id = $this->queue_job($job_type, $job_data, $options);
            if ($job_id) {
                $job_ids[] = $job_id;
            }
        }
        
        return $job_ids;
    }
    
    /**
     * Populate queue using widget's get_items() method
     * 
     * @since 2.3.0
     * @param string $job_type Job type identifier
     * @return array Array of queued job IDs
     */
    public function populate_queue($job_type) {
        $widget = $this->get_widget($job_type);
        if (!$widget) {
            return array();
        }
        
        // Get items from widget
        $items = $widget->get_items();
        
        // Queue each item
        return $this->queue_multiple_jobs($job_type, $items);
    }
    
    /**
     * Process jobs in queue (simplified architecture)
     * 
     * Processes pending jobs with streamlined execution.
     * Only supports blog_creation and post_scan job types.
     * 
     * @since 2.5.0
     * @param string $job_type Job type to process
     * @param int $batch_size Number of jobs to process in this batch
     * @return array Processing results
     */
    public function process_queue($job_type, $batch_size = 1) {
        // Validate job type
        if (!in_array($job_type, ['blog_creation', 'post_scan', 'ai_term_scan'])) {
            return array('error' => sprintf(esc_html('Unsupported job type: %s'), esc_html($job_type)));
        }
        
        // Always process one job at a time for simplicity
        $batch_size = 1;
        
        return $this->process_jobs_simple($job_type, $batch_size);
    }
    
    /**
     * Process jobs with simplified architecture
     * 
     * @since 2.5.0
     * @param string $job_type Job type to process
     * @param int $batch_size Number of jobs to process
     * @return array Processing results
     */
    private function process_jobs_simple($job_type, $batch_size) {
        global $wpdb;
        
        // Clean up stale jobs first
        $this->cleanup_stale_jobs();
        
        // Check if processing should continue
        if (!$this->should_continue_processing($job_type)) {
            return array('stopped' => true, 'reason' => 'Processing stopped');
        }
        
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';

        // Get pending jobs
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $jobs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$queue_table}
                WHERE job_type = %s
                AND status = 'pending'
                AND (scheduled_at IS NULL OR scheduled_at <= %s)
                AND attempts < max_attempts
                ORDER BY priority DESC, created_at ASC
                LIMIT %d",
                $job_type,
                current_time('mysql'),
                $batch_size
            ),
            ARRAY_A
        );
        
        if (empty($jobs)) {
            return array('processed' => 0, 'message' => 'No jobs to process');
        }
        
        $results = array(
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'job_results' => array()
        );
        
        // Process each job
        foreach ($jobs as $job) {
            $job_id = $job['queue_id'];
            
            try {
                // Execute job directly
                $execution_result = $this->execute_job_directly($job_id);
                
                if ($execution_result['success']) {
                    $results['successful']++;
                    $results['job_results'][$job_id] = array(
                        'status' => 'success',
                        'result' => $execution_result
                    );
                } else {
                    $results['failed']++;
                    $results['job_results'][$job_id] = array(
                        'status' => 'failed',
                        'error' => $execution_result['message']
                    );
                }
                
            } catch (\Exception $e) {
                $results['failed']++;
                $results['job_results'][$job_id] = array(
                    'status' => 'error',
                    'error' => $e->getMessage()
                );
                
                $this->log(sprintf('Job %d failed: %s', esc_html($job_id), esc_html($e->getMessage())), 'error');
            }
            
            $results['processed']++;
        }
        
        return $results;
    }
    
    /**
     * Process widget queue privately with bulletproof sequential processing control
     * 
     * @since 2.3.0
     * @param ExplainerPlugin_Job_Queue_Widget $widget Widget to process
     * @param int $batch_size Batch size (limited to 1 for sequential processing)
     * @return array Processing results
     */
    private function process_widget_queue($widget, $batch_size) {
        global $wpdb;
        
        $job_type = $widget->get_job_type();
        
        // Self-healing: Clean up stale jobs first
        $this->cleanup_stale_jobs();
        
        // Check if processing should continue
        if (!$this->should_continue_processing($job_type)) {
            return array('stopped' => true, 'reason' => 'Processing stopped');
        }
        
        // Bulletproof sequential processing with deadlock detection
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        
        // Use FOR UPDATE to prevent race conditions
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $processing_jobs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT queue_id, started_at FROM {$queue_table}
                 WHERE status = %s
                 ORDER BY started_at ASC
                 FOR UPDATE",
                'processing'
            )
        );
        
        if (!empty($processing_jobs)) {
            // Check for deadlocked jobs (processing > 10 minutes)
            $deadlock_threshold = wp_date('Y-m-d H:i:s', strtotime('-10 minutes'));
            $deadlocked_jobs = array();
            
            foreach ($processing_jobs as $proc_job) {
                if ($proc_job->started_at && $proc_job->started_at < $deadlock_threshold) {
                    $deadlocked_jobs[] = $proc_job->queue_id;
                }
            }
            
            if (!empty($deadlocked_jobs)) {
                $this->recover_deadlocked_jobs($deadlocked_jobs);
                $this->log(sprintf('Recovered %d deadlocked jobs', count($deadlocked_jobs)), 'warning');
            } else {
                return array('processed' => 0, 'message' => 'Jobs already processing, maintaining sequential execution');
            }
        }
        
        // Limit to 1 job for sequential processing
        $batch_size = min(1, $batch_size);

        // Get pending jobs with priority ordering and health check
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $jobs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$queue_table}
                WHERE job_type = %s
                AND status = 'pending'
                AND (scheduled_at IS NULL OR scheduled_at <= %s)
                AND attempts < max_attempts
                ORDER BY priority DESC, created_at ASC
                LIMIT %d",
                $job_type,
                current_time('mysql'),
                $batch_size
            ),
            ARRAY_A
        );
        
        if (empty($jobs)) {
            return array('processed' => 0, 'message' => 'No jobs to process');
        }
        
        $results = array(
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'recovered' => 0,
            'job_results' => array()
        );
        
        // Process each job with bulletproof error handling
        foreach ($jobs as $job) {
            if (!$this->should_continue_processing($job_type)) {
                $this->log(sprintf('Processing stopped for job type: %s', esc_html($job_type)), 'info');
                break;
            }
            
            // Atomic job locking with deadlock prevention
            $lock_acquired = $this->acquire_job_lock($job['queue_id']);
            if (!$lock_acquired) {
                $this->log(sprintf('Failed to acquire lock for job %d', esc_html($job['queue_id'])), 'warning');
                continue;
            }
            
            try {
                // Set current job context
                $widget->set_current_job($job);
                
                // Track processing time for performance monitoring
                $start_time = microtime(true);
                
                // Use consolidated job execution method
                $execution_result = $this->execute_job_internal($job['queue_id'], $widget, $job, 300);
                
                $processing_time = microtime(true) - $start_time;
                
                if ($execution_result['success']) {
                    $results['successful']++;
                    $results['job_results'][$job['queue_id']] = array(
                        'status' => 'success',
                        'result' => $execution_result['result'] ?? $execution_result,
                        'processing_time' => $processing_time
                    );
                } else {
                    $results['failed']++;
                    $results['job_results'][$job['queue_id']] = array(
                        'status' => 'failed',
                        'error' => $execution_result['message'] ?? 'Job execution failed',
                        'processing_time' => $processing_time
                    );
                }
                
                // Log performance for monitoring
                if ($processing_time > 30.0) { // Log slow processing (reduced threshold)
                    $this->log(sprintf('Slow job processing detected: Job %d took %.2fs', esc_html($job['queue_id']), esc_html($processing_time)), 'warning', array(
                        'job_id' => $job['queue_id'], 'processing_time' => $processing_time, 'job_type' => $job_type
                    ));
                }
                
            } catch (\Exception $error) {
                // Bulletproof error handling with recovery
                $recovery_result = $this->handle_job_error_with_recovery($job, $error, $widget);
                
                $processing_time = microtime(true) - $start_time;
                
                if ($recovery_result['recovered']) {
                    $results['recovered']++;
                    $this->log(sprintf('Job %d recovered after error: %s', esc_html($job['queue_id']), esc_html($error->getMessage())), 'info');
                } else {
                    $results['failed']++;
                    $this->log(sprintf('Job %d failed permanently: %s', esc_html($job['queue_id']), esc_html($error->getMessage())), 'error');
                }
                
                $results['job_results'][$job['queue_id']] = array(
                    'status' => $recovery_result['recovered'] ? 'recovered' : 'error',
                    'error' => $error->getMessage(),
                    'processing_time' => $processing_time,
                    'recovery_attempted' => true
                );
            } finally {
                // Always release job lock
                $this->release_job_lock($job['queue_id']);
                
                // Clear widget context
                $widget->set_current_job(null);
            }
            
            $results['processed']++;
        }
        
        // Health monitoring and system diagnostics
        $this->perform_health_check();
        
        return $results;
    }
    
    /**
     * Handle job processing error
     * 
     * @since 2.3.0
     * @param array $job Job data
     * @param \Exception $error Error that occurred
     * @param ExplainerPlugin_Job_Queue_Widget $widget Widget instance
     */
    private function handle_job_error($job, $error, $widget) {
        global $wpdb;
        
        $job_id = $job['queue_id'];
        $attempts = absint($job['attempts']) + 1;
        $max_attempts = absint($job['max_attempts']);
        
        // Check if widget wants to retry
        $should_retry = $widget->on_error($job['data'], $error);
        
        if ($should_retry && $attempts < $max_attempts) {
            // Reset to pending for retry
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
            $wpdb->update(
                $wpdb->prefix . 'ai_explainer_job_queue',
                array(
                    'status' => 'pending',
                    'attempts' => $attempts,
                    'error_message' => $error->getMessage(),
                    'started_at' => null,
                    'updated_at' => current_time('mysql')
                ),
                array('queue_id' => $job_id),
                array('%s', '%d', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Mark as permanently failed
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
            $wpdb->update(
                $wpdb->prefix . 'ai_explainer_job_queue',
                array(
                    'status' => 'failed',
                    'attempts' => $attempts,
                    'error_message' => $error->getMessage(),
                    'completed_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('queue_id' => $job_id),
                array('%s', '%d', '%s', '%s', '%s'),
                array('%d')
            );
            
            // Log permanent failure
            $this->log(sprintf('Job %d permanently failed: %s', esc_html($job_id), esc_html($error->getMessage())), 'error', array(
                'job_id' => $job_id, 'attempts' => $attempts, 'max_attempts' => $max_attempts, 'error' => $error->getMessage()
            ));
            
            // Call widget failure handler
            $widget->on_failure();
        }
    }
    
    /**
     * Mark jobs as processing
     * 
     * @since 2.3.0
     * @param array $job_ids Array of job IDs
     */
    private function mark_jobs_processing($job_ids) {
        global $wpdb;
        
        if (empty($job_ids)) {
            return;
        }
        
        $placeholders = implode(',', array_fill(0, count($job_ids), '%d'));
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';

        $current_time = current_time('mysql');

        // Prepare values array for prepare() - must be spread as individual parameters
        $prepare_values = array_merge(
            array('processing', $current_time, $current_time),
            $job_ids
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}ai_explainer_job_queue
                SET status = %s, started_at = %s, updated_at = %s
                WHERE queue_id IN ({$placeholders})",
                ...$prepare_values
            )
        );
    }
    
    
    /**
     * Check if processing should continue
     * 
     * @since 2.3.0
     * @param string $job_type Job type identifier
     * @return bool True if should continue, false otherwise
     */
    public function should_continue_processing($job_type) {
        // Check stop flag
        $stop_flag = get_transient('explainer_job_queue_stop_' . $job_type);
        if ($stop_flag) {
            return false;
        }
        
        // Check pause flag
        $pause_flag = get_transient('explainer_job_queue_pause_' . $job_type);
        if ($pause_flag) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Stop job processing
     * 
     * @since 2.3.0
     * @param string $job_type Job type identifier
     * @return bool True on success
     */
    public function stop_job($job_type) {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $job_type = sanitize_key($job_type);
        set_transient('explainer_job_queue_stop_' . $job_type, true, HOUR_IN_SECONDS);
        
        return true;
    }
    
    /**
     * Pause job processing
     * 
     * @since 2.3.0
     * @param string $job_type Job type identifier
     * @return bool True on success
     */
    public function pause_job($job_type) {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $job_type = sanitize_key($job_type);
        set_transient('explainer_job_queue_pause_' . $job_type, true, HOUR_IN_SECONDS);
        
        return true;
    }
    
    /**
     * Resume job processing
     * 
     * @since 2.3.0
     * @param string $job_type Job type identifier
     * @return bool True on success
     */
    public function resume_job($job_type) {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $job_type = sanitize_key($job_type);
        delete_transient('explainer_job_queue_pause_' . $job_type);
        
        return true;
    }
    
    /**
     * Clear queue jobs
     * 
     * @since 2.3.0
     * @param string $job_type Job type identifier
     * @param string $status Optional status filter
     * @return int Number of jobs cleared
     */
    public function clear_queue($job_type, $status = null) {
        global $wpdb;
        
        if (!current_user_can('manage_options')) {
            return 0;
        }
        
        $job_type = sanitize_key($job_type);
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        
        if ($status) {
            $status = sanitize_key($status);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
            $deleted = $wpdb->delete(
                $queue_table,
                array('job_type' => $job_type, 'status' => $status),
                array('%s', '%s')
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
            $deleted = $wpdb->delete(
                $queue_table,
                array('job_type' => $job_type),
                array('%s')
            );
        }

        // Invalidate cache after clearing queue
        if ($deleted) {
            wp_cache_delete('queue_status_' . $job_type, 'explainer_job_queue');
            wp_cache_delete('processing_statistics', 'explainer_job_queue');
        }

        return $deleted ? $deleted : 0;
    }
    
    /**
     * Get queue status information
     *
     * @since 2.3.0
     * @param string $job_type Job type identifier
     * @return array Status information
     */
    public function get_queue_status($job_type) {
        global $wpdb;

        $job_type = sanitize_key($job_type);
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';

        // Check cache first
        $cache_key = 'queue_status_' . $job_type;
        $cached_status = wp_cache_get($cache_key, 'explainer_job_queue');

        if (false !== $cached_status) {
            return $cached_status;
        }

        // Get counts by status
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query, caching handled manually above
        $status_counts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) as count
                FROM {$queue_table}
                WHERE job_type = %s
                GROUP BY status",
                $job_type
            ),
            ARRAY_A
        );

        $counts = array(
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'paused' => 0
        );

        foreach ($status_counts as $row) {
            $counts[$row['status']] = absint($row['count']);
        }

        $total = array_sum($counts);
        $progress_percentage = $total > 0 ? round(($counts['completed'] / $total) * 100) : 0;

        $status = array(
            'job_type' => $job_type,
            'counts' => $counts,
            'total' => $total,
            'progress_percentage' => $progress_percentage,
            'is_stopped' => (bool) get_transient('explainer_job_queue_stop_' . $job_type),
            'is_paused' => (bool) get_transient('explainer_job_queue_pause_' . $job_type)
        );

        // Cache for 10 seconds (short cache since queue status changes frequently)
        wp_cache_set($cache_key, $status, 'explainer_job_queue', 10);

        return $status;
    }
    
    /**
     * Get detailed job progress (simplified)
     * 
     * @since 2.5.0
     * @param string $job_type Job type identifier
     * @return array Progress information
     */
    public function get_job_progress($job_type) {
        // Return simplified status without complex transient tracking
        return $this->get_queue_status($job_type);
    }
    
    /**
     * Get single job by ID
     *
     * @since 2.3.0
     * @param int $queue_id Queue ID
     * @return array|null Job data or null
     */
    public function get_job($queue_id) {
        global $wpdb;

        $queue_id = absint($queue_id);
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';

        // Check cache first
        $cache_key = 'job_' . $queue_id;
        $cached_job = wp_cache_get($cache_key, 'explainer_job_queue');

        if (false !== $cached_job) {
            return $cached_job;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query, caching handled manually above
        $job = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$queue_table} WHERE queue_id = %d",
                $queue_id
            ),
            ARRAY_A
        );

        // Cache for 30 seconds
        if ($job) {
            wp_cache_set($cache_key, $job, 'explainer_job_queue', 30);
        }

        return $job;
    }
    
    /**
     * Get jobs with optional filtering
     * 
     * @since 2.3.0
     * @param array $args Query arguments
     * @return array Jobs array
     */
    public function get_jobs($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'job_type' => null,
            'status' => null,
            'limit' => 50,
            'offset' => 0,
            'order_by' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = array_merge($defaults, $args);
        
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        
        try {
            $where_conditions = array('1=1');
            $where_values = array();
            
            // Build WHERE clause with proper validation
            if (!empty($args['job_type'])) {
                $where_conditions[] = 'job_type = %s';
                $where_values[] = sanitize_key($args['job_type']);
            }
            
            if (!empty($args['status'])) {
                if (is_array($args['status'])) {
                    $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                    $where_conditions[] = "status IN ($placeholders)";
                    foreach ($args['status'] as $status) {
                        $where_values[] = sanitize_key($status);
                    }
                } else {
                    $where_conditions[] = 'status = %s';
                    $where_values[] = sanitize_key($args['status']);
                }
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            // Validate and sanitize order parameters
            $allowed_order_by = array('created_at', 'updated_at', 'priority', 'queue_id', 'status');
            $order_by = in_array($args['order_by'], $allowed_order_by) ? $args['order_by'] : 'created_at';
            $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
            $limit = max(1, min(1000, absint($args['limit']))); // Limit between 1-1000
            $offset = max(0, absint($args['offset']));
            
            // Build final query
            $query = "SELECT * FROM {$queue_table} WHERE {$where_clause} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d";

            // Execute query with proper error handling
            if (!empty($where_values)) {
                $where_values[] = $limit;
                $where_values[] = $offset;
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query uses placeholders, values are prepared
                $results = $wpdb->get_results($wpdb->prepare($query, $where_values), ARRAY_A);
            } else {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query uses placeholders, values are prepared
                $results = $wpdb->get_results($wpdb->prepare($query, $limit, $offset), ARRAY_A);
            }
            
            // Check for database errors
            if ($wpdb->last_error) {
                $this->log('Database error in get_jobs: ' . $wpdb->last_error, 'error', array(
                    'query' => $query,
                    'args' => $args,
                    'where_values' => $where_values
                ));
                return array();
            }
            
            return is_array($results) ? $results : array();
            
        } catch (Exception $e) {
            $this->log('Exception in get_jobs: ' . $e->getMessage(), 'error', array(
                'args' => $args,
                'trace' => $e->getTraceAsString()
            ));
            return array();
        }
    }
    
    /**
     * Get a specific job by ID
     * 
     * @param int $job_id Job ID
     * @return object|null Job object or null if not found
     */
    public function get_job_by_id($job_id) {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';

        $job_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$queue_table} WHERE queue_id = %d",
                $job_id
            ),
            ARRAY_A
        );
        
        if (!$job_data) {
            return null;
        }
        
        // Convert to object and decode data
        $job = (object) $job_data;
        $job->job_id = $job->queue_id; // Ensure consistent property naming
        
        if (!empty($job->data)) {
            $decoded_data = maybe_unserialize($job->data);
            if (is_array($decoded_data)) {
                $job->selection_text = $decoded_data['selection_text'] ?? '';
                $job->options = $decoded_data['options'] ?? array();
            }
        }
        
        return $job;
    }
    
    /**
     * Get next pending job for processing
     * 
     * @return object|null Job object or null if no pending jobs
     */
    public function get_next_pending_job() {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $job_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$queue_table} WHERE status = %s ORDER BY priority DESC, created_at ASC LIMIT 1",
                'pending'
            ),
            ARRAY_A
        );
        
        if (!$job_data) {
            return null;
        }
        
        // Convert to object and decode data
        $job = (object) $job_data;
        $job->job_id = $job->queue_id; // Ensure consistent property naming
        
        if (!empty($job->data)) {
            $decoded_data = maybe_unserialize($job->data);
            if (is_array($decoded_data)) {
                $job->selection_text = $decoded_data['selection_text'] ?? '';
                $job->options = $decoded_data['options'] ?? array();
            }
        }
        
        return $job;
    }
    
    /**
     * Update job status
     * 
     * @param int $job_id Job ID
     * @param string $status New status
     * @param mixed $result_data Optional result data
     * @return bool Success
     */
    public function update_job_status($job_id, $status, $result_data = null) {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        
        // Debug: Method entry
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('update_job_status called', array(
                'job_id' => $job_id,
                'status' => $status,
                'has_result_data' => $result_data !== null
            ), 'Job_Queue_Manager');
        }
        
        
        
        $update_data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );
        
        if ($status === 'processing') {
            $update_data['started_at'] = current_time('mysql');
            // Add progress message if provided
            if ($result_data && is_string($result_data)) {
                $update_data['progress_message'] = $result_data;
            }
        } elseif ($status === 'completed') {
            $update_data['completed_at'] = current_time('mysql');
            // Clear progress message on completion
            $update_data['progress_message'] = '';
            
            // Store the full result data (even if null, we store it for debugging)
            if ($result_data !== null) {
                $update_data['result_data'] = is_array($result_data) ? serialize($result_data) : $result_data;
            } else {
                // Store null result as empty serialized array for debugging
                $update_data['result_data'] = serialize(array('error' => 'No result data provided'));
            }
            
            // Extract post_id from result_data and store in post_id column
            if ($result_data && is_array($result_data)) {
                $post_id = null;
                if (isset($result_data['post_id'])) {
                    $post_id = (int) $result_data['post_id'];
                } elseif (isset($result_data['post']['ID'])) {
                    $post_id = (int) $result_data['post']['ID'];
                }
                
                if ($post_id) {
                    $update_data['post_id'] = $post_id;
                    if (function_exists('explainer_log_debug')) {
                        explainer_log_debug("Storing post_id {$post_id} for completed job {$job_id}", array(), 'Job_Queue_Manager');
                    }
                }
            }
        } elseif ($status === 'failed') {
            $update_data['progress_message'] = '';
            
            // Store the full result data (even if null, we store it for debugging)
            if ($result_data !== null) {
                $update_data['result_data'] = is_array($result_data) ? serialize($result_data) : $result_data;
                
                // Extract error message for the error_message column
                if (is_array($result_data) && isset($result_data['message'])) {
                    $update_data['error_message'] = $result_data['message'];
                } elseif (is_string($result_data)) {
                    $update_data['error_message'] = $result_data;
                }
            } else {
                // Store null result as empty serialized array for debugging
                $update_data['result_data'] = serialize(array('error' => 'No result data provided'));
                $update_data['error_message'] = 'No result data provided';
            }
        }
        
        // Debug: Database update attempt
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('Attempting database update', array(
                'job_id' => $job_id,
                'update_data' => $update_data,
                'table' => $queue_table
            ), 'Job_Queue_Manager');
        }
        
        $result = $wpdb->update(
            $queue_table,
            $update_data,
            array('queue_id' => $job_id),
            array_fill(0, count($update_data), '%s'),
            array('%d')
        );

        // Invalidate cache after update
        if ($result !== false) {
            wp_cache_delete('job_' . $job_id, 'explainer_job_queue');

            // Also invalidate queue status cache if we have job data
            $job = $wpdb->get_row(
                $wpdb->prepare("SELECT job_type FROM {$queue_table} WHERE queue_id = %d", $job_id),
                ARRAY_A
            );
            if ($job && isset($job['job_type'])) {
                wp_cache_delete('queue_status_' . $job['job_type'], 'explainer_job_queue');
            }
        }

        // Debug: Database update result
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('Database update result', array(
                'job_id' => $job_id,
                'result' => $result,
                'result_type' => gettype($result),
                'wpdb_last_error' => $wpdb->last_error ?: 'none',
                'affected_rows' => $wpdb->rows_affected
            ), 'Job_Queue_Manager');
        }

        // Additional debug for completed jobs to track result_data storage
        if ($status === 'completed') {
            if (function_exists('explainer_log_debug')) {
                explainer_log_debug('Job marked as completed', array(
                    'job_id' => $job_id,
                    'result_data_stored' => isset($update_data['result_data']),
                    'result_data_length' => isset($update_data['result_data']) ? strlen($update_data['result_data']) : 0,
                    'post_id_stored' => isset($update_data['post_id']) ? $update_data['post_id'] : 'none'
                ), 'Job_Queue_Manager');
            }
        }


        return $result !== false;
    }
    
    /**
     * Retry failed jobs
     * 
     * @since 2.3.0
     * @param string $job_type Job type identifier
     * @return int Number of jobs reset for retry
     */
    public function retry_failed_jobs($job_type) {
        global $wpdb;
        
        if (!current_user_can('manage_options')) {
            return 0;
        }
        
        $job_type = sanitize_key($job_type);
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        
        $updated = $wpdb->update(
            $queue_table,
            array(
                'status' => 'pending',
                'attempts' => 0,
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null,
                'updated_at' => current_time('mysql')
            ),
            array(
                'job_type' => $job_type,
                'status' => 'failed'
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s'),
            array('%s', '%s')
        );
        
        return $updated ? $updated : 0;
    }
    
    /**
     * Handle AJAX requests
     * 
     * @since 2.3.0
     */
    public function handle_ajax() {
        // Verify nonce
        if (!check_ajax_referer('explainer_job_queue_admin', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }

        // Get operation and job type
        $operation = isset($_POST['operation']) ? sanitize_text_field(wp_unslash($_POST['operation'])) : '';
        $job_type = isset($_POST['job_type']) ? sanitize_key(wp_unslash($_POST['job_type'])) : '';
        
        if (empty($operation)) {
            wp_send_json_error(array('message' => 'Operation required'));
            return;
        }
        
        // Log AJAX request
        $this->log(sprintf('Processing AJAX operation: %s for job type: %s', esc_html($operation), esc_html($job_type)), 'debug', array(
            'operation' => $operation, 'job_type' => $job_type, 'user_id' => get_current_user_id()
        ));
        
        // Execute operation
        try {
            switch ($operation) {
            case 'populate':
                $result = $this->populate_queue($job_type);
                wp_send_json_success(array(
                    'message' => sprintf('Queued %d jobs', count($result)), 'job_ids' => $result
                ));
                break;
                
            case 'process':
                $batch_size = absint($_POST['batch_size'] ?? 10);
                $result = $this->process_queue($job_type, $batch_size);
                wp_send_json_success($result);
                break;
                
            case 'stop':
                $result = $this->stop_job($job_type);
                wp_send_json_success(array('stopped' => $result));
                break;
                
            case 'pause':
                $result = $this->pause_job($job_type);
                wp_send_json_success(array('paused' => $result));
                break;
                
            case 'resume':
                $result = $this->resume_job($job_type);
                wp_send_json_success(array('resumed' => $result));
                break;
                
            case 'clear':
                $status = sanitize_key($_POST['status'] ?? '');
                $cleared = $this->clear_queue($job_type, $status);
                wp_send_json_success(array('cleared' => $cleared));
                break;
                
            case 'status':
                $status = $this->get_queue_status($job_type);
                wp_send_json_success($status);
                break;
                
            case 'retry':
                $retried = $this->retry_failed_jobs($job_type);
                wp_send_json_success(array('retried' => $retried));
                break;
                
            default:
                wp_send_json_error(array('message' => 'Unknown operation'));
                break;
            }
        } catch (\Exception $e) {
            // Log AJAX error
            $this->log(sprintf('AJAX operation failed: %s - %s', esc_html($operation), esc_html($e->getMessage())), 'error', array(
                'operation' => $operation, 'job_type' => $job_type, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()
            ));
            
            wp_send_json_error(array(
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Store job metadata
     * 
     * @since 2.3.0
     * @param int $job_id Job ID
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return bool Success
     */
    private function store_job_meta($job_id, $key, $value) {
        global $wpdb;
        
        $meta_table = $wpdb->prefix . 'ai_explainer_job_meta';
        
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Custom table with proper indexes (meta_key, queue_id+meta_key)
        return $wpdb->insert(
            $meta_table,
            array(
                'queue_id' => $job_id,
                'meta_key' => sanitize_key($key),
                'meta_value' => maybe_serialize($value)
            ),
            array('%d', '%s', '%s')
        ) !== false;
    }
    
    /**
     * Get processing statistics for all job types
     *
     * @since 2.3.0
     * @return array Processing statistics
     */
    public function get_processing_statistics() {
        global $wpdb;

        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';

        // Check cache first
        $cache_key = 'processing_statistics';
        $cached_stats = wp_cache_get($cache_key, 'explainer_job_queue');

        if (false !== $cached_stats) {
            return $cached_stats;
        }

        // Get overall statistics
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $stats = $wpdb->get_results(
            "SELECT
                job_type,
                status,
                COUNT(*) as count,
                AVG(CASE WHEN completed_at IS NOT NULL AND started_at IS NOT NULL
                    THEN TIMESTAMPDIFF(SECOND, started_at, completed_at)
                    ELSE NULL END) as avg_processing_time,
                MAX(CASE WHEN completed_at IS NOT NULL AND started_at IS NOT NULL
                    THEN TIMESTAMPDIFF(SECOND, started_at, completed_at)
                    ELSE NULL END) as max_processing_time
            FROM {$queue_table}
            GROUP BY job_type, status",
            ARRAY_A
        );

        $formatted_stats = array();
        foreach ($stats as $stat) {
            $job_type = $stat['job_type'];
            if (!isset($formatted_stats[$job_type])) {
                $formatted_stats[$job_type] = array(
                    'pending' => 0,
                    'processing' => 0,
                    'completed' => 0,
                    'failed' => 0,
                    'avg_processing_time' => 0,
                    'max_processing_time' => 0
                );
            }

            $formatted_stats[$job_type][$stat['status']] = intval($stat['count']);
            if ($stat['status'] === 'completed') {
                $formatted_stats[$job_type]['avg_processing_time'] = floatval($stat['avg_processing_time']);
                $formatted_stats[$job_type]['max_processing_time'] = floatval($stat['max_processing_time']);
            }
        }

        // Cache for 1 minute
        wp_cache_set($cache_key, $formatted_stats, 'explainer_job_queue', 60);

        return $formatted_stats;
    }
    
    /**
     * Get cached brand colours
     * 
     * @since 2.3.0
     * @return array Brand colours array
     */
    public function get_brand_colours() {
        if ($this->brand_colours === null) {
            $this->cache_brand_colours();
        }
        
        return $this->brand_colours;
    }
    
    /**
     * Internal job execution method - single source of truth
     * 
     * This method handles all job execution regardless of entry point (admin, cron, etc)
     * and ensures consistent result storage and status updates.
     * 
     * @since 2.4.1
     * @param int $job_id Queue job ID
     * @param ExplainerPlugin_Job_Queue_Widget $widget Widget instance
     * @param array $job Job data
     * @param int $timeout_seconds Maximum execution time
     * @return array Result array with 'success', 'message', and optional data
     */
    private function execute_job_internal($job_id, $widget, $job, $timeout_seconds = 300) {
        try {
            // Track processing time
            $start_time = microtime(true);
            
            // Set time limit slightly higher than our timeout
            $old_time_limit = ini_get('max_execution_time');
            if ($old_time_limit < $timeout_seconds + 30) {
                // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Required for long-running job processing
                @set_time_limit($timeout_seconds + 30);
            }
            
            // Set current job context
            $widget->set_current_job($job);
            
            // Unserialize job data - handle both array and object formats
            $job_data = is_array($job) ? ($job['data'] ?? '') : ($job->data ?? '');
            $item = maybe_unserialize($job_data);
            
            // Process the job
            $result = $this->process_item_with_checks($widget, $item, $start_time, $timeout_seconds);
            
            $processing_time = microtime(true) - $start_time;
            
            // Build complete result data
            $complete_result = array(
                'success' => true,
                'message' => __('Job completed successfully', 'ai-explainer'),
                'result' => $result,
                'processing_time' => $processing_time,
                'job_id' => $job_id,
                'completed_at' => current_time('mysql')
            );
            
            // Update job status with complete result data
            $this->update_job_status($job_id, 'completed', $complete_result);
            
            // Restore time limit
            if ($old_time_limit !== false) {
                // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Restoring previous time limit
                @set_time_limit($old_time_limit);
            }
            
            return $complete_result;
            
        } catch (Exception $e) {
            // Build complete error result data
            $error_result = array(
                'success' => false,
                /* translators: %s: error message */
                'message' => sprintf(esc_html__('Job execution failed: %s', 'ai-explainer'), esc_html($e->getMessage())),
                'error' => $e->getMessage(),
                'job_id' => $job_id,
                'failed_at' => current_time('mysql'),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine()
            );
            
            // Update job status as failed with error details
            $this->update_job_status($job_id, 'failed', $error_result);
            
            // Restore time limit
            if (isset($old_time_limit) && $old_time_limit !== false) {
                // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Restoring previous time limit
                @set_time_limit($old_time_limit);
            }
            
            return $error_result;
        }
    }

    /**
     * Execute job directly (simplified architecture)
     * 
     * Single execution path for both manual and cron execution.
     * Supports only blog_creation and post_scan job types.
     * 
     * @since 2.5.0
     * @param int $job_id Queue job ID
     * @return array Result array with 'success', 'message', and optional data
     */
    public function execute_job_directly($job_id) {
        // Set execution context to allow widget registration
        if (!defined('EXPLAINER_DIRECT_JOB_EXECUTION')) {
            define('EXPLAINER_DIRECT_JOB_EXECUTION', true);
        }
        
        // Get job details
        $job = $this->get_job_by_id($job_id);
        if (!$job) {
            return array(
                'success' => false,
                'message' => __('Job not found', 'ai-explainer')
            );
        }
        
        // Validate job type (simplified architecture)
        if (!in_array($job->job_type, ['blog_creation', 'post_scan', 'ai_term_scan'])) {
            return array(
                'success' => false,
                /* translators: %s: job type name */
                'message' => sprintf(esc_html__('Unsupported job type: %s. Only blog_creation and post_scan are supported.', 'ai-explainer'), esc_html($job->job_type))
            );
        }
        
        // Update job status to processing
        $this->update_job_status($job_id, 'processing');
        
        $this->log("Executing job directly - Type: {$job->job_type}, ID: {$job_id}", 'info');
        
        try {
            // Execute based on job type using simplified logic
            if ($job->job_type === 'blog_creation') {
                return $this->execute_blog_creation_job($job_id, $job);
            } else if ($job->job_type === 'post_scan' || $job->job_type === 'ai_term_scan') {
                return $this->execute_post_scan_job($job_id, $job);
            }
        } catch (\Exception $e) {
            $error_result = array(
                'success' => false,
                /* translators: %s: error message */
                'message' => sprintf(esc_html__('Job execution failed: %s', 'ai-explainer'), esc_html($e->getMessage())),
                'error' => $e->getMessage(),
                'job_id' => $job_id
            );
            
            $this->update_job_status($job_id, 'failed', $error_result);
            return $error_result;
        }
        
        return array(
            'success' => false,
            'message' => __('Unknown job execution path', 'ai-explainer')
        );
    }
    
    /**
     * Clean up stale jobs that have been processing for too long with enhanced logic
     * 
     * @since 2.4.0
     * @param int $timeout_minutes Jobs stuck in processing for this many minutes will be reset
     * @return int Number of jobs cleaned up
     */
    private function cleanup_stale_jobs($timeout_minutes = 10) {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        
        try {
            // Get stuck jobs with more detailed analysis
            $cutoff_time = current_time('mysql', true);
            $cutoff_timestamp = strtotime($cutoff_time) - ($timeout_minutes * 60);
            $cutoff_formatted = wp_date('Y-m-d H:i:s', $cutoff_timestamp);
            
            // Find specific stuck jobs first for logging
            $stuck_jobs = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT queue_id, job_type, started_at,
                           TIMESTAMPDIFF(MINUTE, started_at, %s) as minutes_stuck,
                           attempts, max_attempts
                    FROM {$queue_table}
                    WHERE status = 'processing'
                    AND started_at IS NOT NULL
                    AND started_at < %s",
                    $cutoff_time,
                    $cutoff_formatted
                )
            );
            
            if (empty($stuck_jobs)) {
                return 0;
            }
            
            $cleaned = 0;
            
            foreach ($stuck_jobs as $job) {
                // Determine if we should retry or fail based on attempts
                $should_retry = $job->attempts < $job->max_attempts;
                
                if ($should_retry) {
                    // Reset to pending for retry with backoff
                    $backoff_seconds = min(300, pow(2, $job->attempts) * 30);
                    $retry_at = wp_date('Y-m-d H:i:s', time() + $backoff_seconds);
                    
                    $updated = $wpdb->update(
                        $queue_table,
                        array(
                            'status' => 'pending',
                            'started_at' => null,
                            'scheduled_at' => $retry_at,
                            'attempts' => $job->attempts + 1,
                            'updated_at' => current_time('mysql'),
                            'progress_message' => sprintf('Auto-recovered after %d minutes, retry in %d seconds', esc_html($job->minutes_stuck), esc_html($backoff_seconds)
                        )), array('queue_id' => $job->queue_id), array('%s', '%s', '%s', '%d', '%s', '%s'), array('%d'));
                } else {
                    // Mark as failed - too many attempts
                    $updated = $wpdb->update(
                        $queue_table,
                        array(
                            'status' => 'failed',
                            'completed_at' => current_time('mysql'),
                            'attempts' => $job->attempts + 1,
                            'error_message' => sprintf('Job timed out after %d minutes and exceeded maximum retry attempts (%d)', esc_html($job->minutes_stuck), esc_html($job->max_attempts)), 'updated_at' => current_time('mysql'), 'progress_message' => ''
                        ), array('queue_id' => $job->queue_id), array('%s', '%s', '%d', '%s', '%s', '%s'), array('%d'));
                }
                
                if ($updated) {
                    $cleaned++;
                    $action = $should_retry ? 'recovered for retry' : 'failed permanently';
                    $this->log(sprintf('Stuck job %d (%s) %s after %d minutes', esc_html($job->queue_id), esc_html($job->job_type), esc_html($action), esc_html($job->minutes_stuck)), 'warning');
                }
            }
            
            if ($cleaned > 0) {
                $this->log(sprintf('Cleaned up %d stale jobs older than %d minutes', esc_html($cleaned), esc_html($timeout_minutes)), 'info', array(
                    'timeout_minutes' => $timeout_minutes, 'cutoff_time' => $cutoff_formatted
                ));
            }
            
            return $cleaned;
            
        } catch (Exception $e) {
            $this->log('Exception in cleanup_stale_jobs: ' . $e->getMessage(), 'error');
            return 0;
        }
    }
    
    /**
     * Recover deadlocked jobs
     * 
     * @since 2.4.0
     * @param array $job_ids Array of job IDs that are deadlocked
     * @return int Number of jobs recovered
     */
    private function recover_deadlocked_jobs($job_ids) {
        global $wpdb;
        
        if (empty($job_ids)) {
            return 0;
        }
        
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        $placeholders = implode(',', array_fill(0, count($job_ids), '%d'));

        $current_time = current_time('mysql');

        // Prepare values array for prepare() - must be spread as individual parameters
        $params = array_merge(
            array('pending', $current_time, 'Recovered from deadlock'),
            $job_ids
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $recovered = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}ai_explainer_job_queue
                SET status = %s,
                    started_at = NULL,
                    updated_at = %s,
                    attempts = attempts + 1,
                    progress_message = %s
                WHERE queue_id IN ({$placeholders})",
                ...$params
            )
        );
        
        return $recovered;
    }
    
    /**
     * Acquire an exclusive lock on a job with enhanced error handling
     * 
     * @since 2.4.0
     * @param int $job_id Job ID to lock
     * @return bool True if lock acquired, false otherwise
     */
    private function acquire_job_lock($job_id) {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        
        try {
            // Start transaction for atomic operation
            $wpdb->query('START TRANSACTION');
            
            // Check current job status with row lock
            $current_status = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT status FROM {$queue_table} WHERE queue_id = %d FOR UPDATE",
                    $job_id
                )
            );
            
            if ($current_status !== 'pending') {
                $wpdb->query('ROLLBACK');
                $this->log("Cannot acquire lock for job $job_id - current status: $current_status", 'debug');
                return false;
            }
            
            // Update status to processing
            $updated = $wpdb->update(
                $queue_table,
                array(
                    'status' => 'processing',
                    'started_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                    'progress_message' => 'Job locked for processing'
                ),
                array('queue_id' => $job_id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            if ($updated === 1) {
                $wpdb->query('COMMIT');
                $this->log("Successfully acquired lock for job $job_id", 'debug');
                return true;
            } else {
                $wpdb->query('ROLLBACK');
                $this->log("Failed to update job $job_id status to processing", 'warning', array(
                    'wpdb_error' => $wpdb->last_error,
                    'affected_rows' => $wpdb->rows_affected
                ));
                return false;
            }
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $this->log("Exception acquiring lock for job $job_id: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Release the lock on a job
     * 
     * @since 2.4.0
     * @param int $job_id Job ID to unlock
     * @return bool True if lock released, false otherwise
     */
    private function release_job_lock($job_id) {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        
        // Only release if still in processing state (avoid conflicts)
        $updated = $wpdb->update(
            $queue_table,
            array(
                'updated_at' => current_time('mysql'),
                'progress_message' => 'Job lock released'
            ),
            array(
                'queue_id' => $job_id,
                'status' => 'processing'
            ),
            array('%s', '%s'),
            array('%d', '%s')
        );
        
        return $updated !== false;
    }
    
    
    /**
     * Process item with periodic timeout checks
     * 
     * @since 2.4.0
     * @param ExplainerPlugin_Job_Queue_Widget $widget Widget to execute
     * @param mixed $item Item to process
     * @param int $start_time Start timestamp
     * @param int $timeout_seconds Timeout in seconds
     * @return mixed Processing result
     * @throws \Exception If timeout exceeded
     */
    private function process_item_with_checks($widget, $item, $start_time, $timeout_seconds) {
        // Check for timeout before processing
        if ((time() - $start_time) > $timeout_seconds) {
            throw new \Exception(sprintf(esc_html('Job execution timed out after %d seconds'), esc_html($timeout_seconds)));
        }
        
        // Execute the actual processing
        return $widget->process_item($item);
    }
    
    /**
     * Handle job error with automatic recovery mechanisms
     * 
     * @since 2.4.0
     * @param array $job Job data
     * @param \Exception $error Error that occurred
     * @param ExplainerPlugin_Job_Queue_Widget $widget Widget instance
     * @return array Recovery result with 'recovered' boolean and 'reason' string
     */
    private function handle_job_error_with_recovery($job, $error, $widget) {
        global $wpdb;
        
        $job_id = $job['queue_id'];
        $attempts = absint($job['attempts']) + 1;
        $max_attempts = absint($job['max_attempts']);
        
        $recovery_result = array(
            'recovered' => false,
            'reason' => '',
            'action' => 'failed'
        );
        
        // Determine if error is recoverable
        $is_recoverable = $this->is_error_recoverable($error);
        $should_retry = $widget->on_error($job['data'], $error) && $is_recoverable;
        
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        
        if ($should_retry && $attempts < $max_attempts) {
            // Calculate exponential backoff delay
            $backoff_delay = min(300, pow(2, $attempts) * 10); // Max 5 minutes
            $retry_at = wp_date('Y-m-d H:i:s', time() + $backoff_delay);
            
            // Reset to pending for retry with backoff
            $wpdb->update(
                $queue_table,
                array(
                    'status' => 'pending',
                    'attempts' => $attempts,
                    'error_message' => $error->getMessage(),
                    'started_at' => null,
                    'scheduled_at' => $retry_at,
                    'updated_at' => current_time('mysql'),
                    'progress_message' => sprintf('Retry scheduled in %d seconds (attempt %d/%d)', esc_html($backoff_delay), esc_html($attempts), esc_html($max_attempts)
                )), array('queue_id' => $job_id), array('%s', '%d', '%s', '%s', '%s', '%s', '%s'), array('%d'));
            
            $recovery_result['recovered'] = true;
            $recovery_result['reason'] = sprintf('Scheduled retry in %d seconds (attempt %d/%d)', esc_html($backoff_delay), esc_html($attempts), esc_html($max_attempts));
            $recovery_result['action'] = 'retry_scheduled';
            
        } else {
            // Mark as permanently failed
            $wpdb->update(
                $queue_table,
                array(
                    'status' => 'failed',
                    'attempts' => $attempts,
                    'error_message' => $error->getMessage(),
                    'completed_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                    'progress_message' => 'Job failed permanently'
                ),
                array('queue_id' => $job_id),
                array('%s', '%d', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            $recovery_result['reason'] = $attempts >= $max_attempts ? 'Max attempts reached' : 'Non-recoverable error';
            $recovery_result['action'] = 'permanently_failed';
            
            // Call widget failure handler
            $widget->on_failure();
        }
        
        return $recovery_result;
    }
    
    /**
     * Check if an error is recoverable
     * 
     * @since 2.4.0
     * @param \Exception $error The error to check
     * @return bool True if error is recoverable, false otherwise
     */
    private function is_error_recoverable($error) {
        $message = strtolower($error->getMessage());
        
        // Non-recoverable errors
        $non_recoverable_patterns = array(
            'invalid api key',
            'no api key configured',
            'api key format',
            'please configure your api key',
            'authentication failed',
            'quota exceeded',
            'permission denied',
            'invalid request format',
            'malformed data'
        );
        
        foreach ($non_recoverable_patterns as $pattern) {
            if (strpos($message, $pattern) !== false) {
                return false;
            }
        }
        
        // Recoverable errors (network, timeouts, temporary issues)
        $recoverable_patterns = array(
            'timeout',
            'connection',
            'network',
            'server error',
            'service unavailable',
            'rate limit',
            'database',
            'mysql'
        );
        
        foreach ($recoverable_patterns as $pattern) {
            if (strpos($message, $pattern) !== false) {
                return true;
            }
        }
        
        // Default to recoverable for unknown errors
        return true;
    }
    
    /**
     * Perform comprehensive health check of the job queue system
     * 
     * @since 2.4.0
     * @return array Health check results
     */
    private function perform_health_check() {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        $health_status = array(
            'status' => 'healthy',
            'issues' => array(),
            'metrics' => array(),
            'recommendations' => array()
        );
        
        try {
            // Check for database connectivity
            $db_check = $wpdb->get_var("SELECT 1");
            if ($db_check !== '1') {
                $health_status['issues'][] = 'Database connectivity issue';
                $health_status['status'] = 'critical';
            }
            
            // Check queue table health
            $queue_stats = $wpdb->get_row(
                "SELECT
                    COUNT(*) as total_jobs,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_jobs,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_jobs,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_jobs,
                    AVG(attempts) as avg_attempts
                FROM {$queue_table}
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            
            $health_status['metrics'] = array(
                'total_jobs_24h' => intval($queue_stats->total_jobs),
                'pending_jobs' => intval($queue_stats->pending_jobs),
                'processing_jobs' => intval($queue_stats->processing_jobs),
                'failed_jobs' => intval($queue_stats->failed_jobs),
                'avg_attempts' => floatval($queue_stats->avg_attempts)
            );
            
            // Check for stuck processing jobs
            $stuck_jobs = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$queue_table}
                    WHERE status = 'processing'
                    AND started_at < %s",
                    wp_date('Y-m-d H:i:s', strtotime('-10 minutes'))
                )
            );
            
            if ($stuck_jobs > 0) {
                $health_status['issues'][] = sprintf('%d jobs stuck in processing state', esc_html($stuck_jobs));
                $health_status['status'] = 'warning';
            }
            
            // Check failure rate
            if ($queue_stats->total_jobs > 0) {
                $failure_rate = ($queue_stats->failed_jobs / $queue_stats->total_jobs) * 100;
                if ($failure_rate > 20) {
                    $health_status['issues'][] = sprintf('High failure rate: %.1f%%', $failure_rate);
                    $health_status['status'] = 'warning';
                }
                $health_status['metrics']['failure_rate'] = round($failure_rate, 1);
            }
            
            // Check average retry attempts
            if ($queue_stats->avg_attempts > 2) {
                $health_status['issues'][] = sprintf('High average retry attempts: %.1f', $queue_stats->avg_attempts);
                $health_status['recommendations'][] = 'Consider investigating frequent job failures';
            }
            
            // Store health status in transient for monitoring
            set_transient('explainer_job_queue_health', $health_status, 300); // 5 minutes
            
        } catch (\Exception $e) {
            $health_status['status'] = 'critical';
            $health_status['issues'][] = 'Health check failed: ' . $e->getMessage();
        }
        
        // Log health issues
        if (!empty($health_status['issues'])) {
            $this->log('Job queue health issues detected', 'warning', array(
                'status' => $health_status['status'],
                'issues' => $health_status['issues'],
                'metrics' => $health_status['metrics']
            ));
        }
        
        return $health_status;
    }
    
    /**
     * Get current job queue health status
     * 
     * @since 2.4.0
     * @return array Health status or null if not available
     */
    public function get_health_status() {
        return get_transient('explainer_job_queue_health');
    }
    
    /**
     * Log a message using the plugin's logging system with enhanced context
     * 
     * @since 2.3.0
     * @param string $message Message to log
     * @param string $level Log level (error, warning, info, debug)
     * @param array $context Optional context data
     */
    private function log($message, $level = 'info', $context = array()) {
        $enhanced_context = array_merge(array(
            'component' => 'job_queue_manager',
            'class' => __CLASS__,
            'timestamp' => current_time('mysql'),
            'memory_usage' => memory_get_usage(true),
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : 'cli',
            'user_id' => get_current_user_id()
        ), $context);
        
        if (class_exists('ExplainerPlugin_Logger')) {
            $logger = ExplainerPlugin_Logger::get_instance();
            $logger->log($message, $level, $enhanced_context);
        } else if (ExplainerPlugin_Debug_Logger::is_enabled()) {
            // Enhanced fallback to debug logger if main logger not available
            ExplainerPlugin_Debug_Logger::log($message, $level, 'job_queue', $enhanced_context);
        }
    }
    
    /**
     * Execute blog creation job (simplified)
     * 
     * @since 2.5.0
     * @param int $job_id Job ID
     * @param object $job Job object
     * @return array Result array
     */
    private function execute_blog_creation_job($job_id, $job) {
        // Get widget for blog creation
        $widget = $this->get_widget('blog_creation');
        if (!$widget) {
            throw new \Exception(esc_html(__('Blog creation widget not registered', 'ai-explainer')));
        }
        
        // Set current job context
        $widget->set_current_job((array) $job);
        
        // Process the job
        $items = $widget->get_items();
        if (empty($items)) {
            throw new \Exception(esc_html(__('No items to process for blog creation', 'ai-explainer')));
        }
        
        $result = $widget->process_item($items[0]);
        
        // Update job status with complete result
        $complete_result = array(
            'success' => true,
            'message' => __('Blog post created successfully', 'ai-explainer'),
            'result' => $result,
            'job_id' => $job_id,
            'post_id' => $result['post_id'] ?? null
        );
        
        $this->update_job_status($job_id, 'completed', $complete_result);
        
        // Create relationship tracking
        if (!empty($result['post_id'])) {
            $this->create_job_post_relationship($job_id, $result['post_id'], 'blog_creation');
        }
        
        return $complete_result;
    }
    
    /**
     * Execute post scan job (simplified)
     * 
     * @since 2.5.0
     * @param int $job_id Job ID
     * @param object $job Job object
     * @return array Result array
     */
    private function execute_post_scan_job($job_id, $job) {
        // Get widget for post scan
        $widget = $this->get_widget('ai_term_scan');
        if (!$widget) {
            throw new \Exception(esc_html(__('Post scan widget not registered', 'ai-explainer')));
        }
        
        // Set current job context
        $widget->set_current_job((array) $job);
        
        // Process the job
        $items = $widget->get_items();
        if (empty($items)) {
            throw new \Exception(esc_html(__('No items to process for post scan', 'ai-explainer')));
        }
        
        $result = $widget->process_item($items[0]);
        
        // Update job status with complete result
        $complete_result = array(
            'success' => true,
            /* translators: %d: number of terms found */
            'message' => sprintf(esc_html__('Found %d terms requiring explanation', 'ai-explainer'), esc_html($result['terms_found'] ?? 0)),
            'result' => $result,
            'job_id' => $job_id,
            'post_id' => $result['post_id'] ?? null,
            'terms_found' => $result['terms_found'] ?? 0
        );
        
        $this->update_job_status($job_id, 'completed', $complete_result);
        
        // Create relationship tracking for explanations
        if (!empty($result['post_id']) && !empty($result['terms_found'])) {
            $this->create_job_post_relationship($job_id, $result['post_id'], 'post_scan', $result['terms_found']);
        }
        
        return $complete_result;
    }
    
    /**
     * Create job-post relationship tracking
     * 
     * @since 2.5.0
     * @param int $job_id Job ID
     * @param int $post_id Post ID
     * @param string $job_type Job type
     * @param int $terms_count Number of terms found (for post_scan jobs)
     */
    private function create_job_post_relationship($job_id, $post_id, $job_type, $terms_count = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_explainer_job_relationships';
        
        // Ensure table exists
        $this->ensure_relationships_table();
        
        $wpdb->insert(
            $table_name,
            array(
                'job_id' => $job_id,
                'post_id' => $post_id,
                'job_type' => $job_type,
                'terms_count' => $terms_count,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%d', '%s')
        );
        
        $this->log(sprintf('Created job-post relationship: Job %d -> Post %d (%s)', esc_html($job_id), esc_html($post_id), esc_html($job_type)), 'info');
    }
    
    /**
     * Get job relationships for a post
     * 
     * @since 2.5.0
     * @param int $post_id Post ID
     * @return array Job relationships
     */
    public function get_post_job_relationships($post_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_explainer_job_relationships';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT jr.*, jq.status, jq.created_at as job_created_at
            FROM $table_name jr
            LEFT JOIN {$wpdb->prefix}ai_explainer_job_queue jq ON jr.job_id = jq.queue_id
            WHERE jr.post_id = %d
            ORDER BY jr.created_at DESC
        ", $post_id), ARRAY_A);
    }
    
    /**
     * Get posts created by jobs
     * 
     * @since 2.5.0
     * @param string $job_type Optional job type filter
     * @return array Posts with job information
     */
    public function get_job_created_posts($job_type = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_explainer_job_relationships';
        
        $where_clause = '';
        $params = array();
        
        if ($job_type) {
            $where_clause = 'WHERE jr.job_type = %s';
            $params[] = $job_type;
        }
        
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';

        $query = "SELECT jr.*, p.post_title, p.post_status, p.post_date,
                jq.status as job_status, jq.created_at as job_created_at
            FROM {$table_name} jr
            LEFT JOIN {$wpdb->posts} p ON jr.post_id = p.ID
            LEFT JOIN {$queue_table} jq ON jr.job_id = jq.queue_id
            {$where_clause}
            ORDER BY jr.created_at DESC
            LIMIT 50";

        if ($params) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query uses placeholders, values are prepared
            return $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is static with no user input
            return $wpdb->get_results($query, ARRAY_A);
        }
    }
    
    /**
     * Ensure job relationships table exists
     * 
     * @since 2.5.0
     */
    private function ensure_relationships_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_explainer_job_relationships';

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE {$table_name} (
                id int(11) NOT NULL AUTO_INCREMENT,
                job_id bigint(20) UNSIGNED NOT NULL,
                post_id bigint(20) UNSIGNED NOT NULL,
                job_type varchar(50) NOT NULL,
                terms_count int(11) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_job_id (job_id),
                KEY idx_post_id (post_id),
                KEY idx_job_type (job_type),
                KEY idx_created_at (created_at)
            ) $charset_collate;";
            
            dbDelta($sql);
        }
    }
    
    /**
     * Get comprehensive debugging information about the job queue system
     * 
     * @since 2.4.1
     * @return array Debug information
     */
    public function get_debug_info() {
        global $wpdb;
        
        $debug_info = array(
            'timestamp' => current_time('mysql'),
            'system_status' => 'collecting',
            'errors' => array(),
            'warnings' => array()
        );
        
        try {
            // Database connectivity
            $debug_info['database']['connectivity'] = $wpdb->get_var("SELECT 1") === '1' ? 'OK' : 'FAILED';
            
            // Table status
            $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
            $debug_info['database']['queue_table_exists'] = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $queue_table)) === $queue_table;

            if ($debug_info['database']['queue_table_exists']) {
                // Job statistics
                $stats = $wpdb->get_results(
                    "SELECT
                        status,
                        COUNT(*) as count,
                        MIN(created_at) as oldest,
                        MAX(created_at) as newest
                    FROM {$queue_table}
                    GROUP BY status",
                    ARRAY_A
                );
                
                $debug_info['jobs']['statistics'] = array();
                foreach ($stats as $stat) {
                    $debug_info['jobs']['statistics'][$stat['status']] = array(
                        'count' => intval($stat['count']),
                        'oldest' => $stat['oldest'],
                        'newest' => $stat['newest']
                    );
                }
                
                // Stuck jobs check
                $stuck_jobs = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT queue_id, job_type, started_at,
                               TIMESTAMPDIFF(MINUTE, started_at, %s) as minutes_processing
                        FROM {$queue_table}
                        WHERE status = 'processing'
                        AND started_at IS NOT NULL
                        AND started_at < %s",
                        current_time('mysql'),
                        wp_date('Y-m-d H:i:s', strtotime('-10 minutes'))
                    )
                );
                
                $debug_info['jobs']['stuck_jobs'] = array();
                foreach ($stuck_jobs as $job) {
                    $debug_info['jobs']['stuck_jobs'][] = array(
                        'job_id' => $job->queue_id,
                        'job_type' => $job->job_type,
                        'minutes_stuck' => $job->minutes_processing
                    );
                    
                    if ($job->minutes_processing > 15) {
                        $debug_info['errors'][] = "Job {$job->queue_id} stuck for {$job->minutes_processing} minutes";
                    } elseif ($job->minutes_processing > 5) {
                        $debug_info['warnings'][] = "Job {$job->queue_id} processing for {$job->minutes_processing} minutes";
                    }
                }
            }
            
            // Widget registration status
            $debug_info['widgets']['registered'] = array();
            foreach ($this->widgets as $type => $widget) {
                $debug_info['widgets']['registered'][$type] = array(
                    'class' => get_class($widget),
                    'config' => $widget->get_config()
                );
            }
            
            // Health status
            $health = $this->get_health_status();
            if ($health) {
                $debug_info['health'] = $health;
                if ($health['status'] !== 'healthy') {
                    $debug_info['warnings'] = array_merge($debug_info['warnings'], $health['issues']);
                }
            }
            
            // System configuration
            $debug_info['configuration'] = array(
                'cron_enabled' => get_option('explainer_enable_cron', false),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'wp_cron_disabled' => defined('DISABLE_WP_CRON') ? DISABLE_WP_CRON : false
            );
            
            // Recent activity
            if ($debug_info['database']['queue_table_exists']) {
                $recent_jobs = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT queue_id, job_type, status, created_at, started_at, completed_at, error_message
                        FROM {$queue_table}
                        WHERE updated_at > %s
                        ORDER BY updated_at DESC
                        LIMIT 10",
                        wp_date('Y-m-d H:i:s', strtotime('-1 hour'))
                    ),
                    ARRAY_A
                );

                $debug_info['recent_activity'] = $recent_jobs;
            }
            
            $debug_info['system_status'] = 'healthy';
            if (!empty($debug_info['errors'])) {
                $debug_info['system_status'] = 'error';
            } elseif (!empty($debug_info['warnings'])) {
                $debug_info['system_status'] = 'warning';
            }
            
        } catch (Exception $e) {
            $debug_info['errors'][] = 'Debug collection failed: ' . $e->getMessage();
            $debug_info['system_status'] = 'error';
        }
        
        return $debug_info;
    }
}