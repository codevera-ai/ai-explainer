<?php
/**
 * Job Queue Migration Helper
 * 
 * Handles migration from old blog queue to new job queue system
 * 
 * @package WPAIExplainer
 * @subpackage JobQueue
 * @since 1.4.0
 */

namespace WPAIExplainer\JobQueue;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Job Queue Migration class
 * 
 * Provides methods to migrate from the old blog queue system to the new job queue
 */
class ExplainerPlugin_Job_Queue_Migration {
    
    /**
     * Migrate existing blog queue data to new job queue system
     * 
     * @return array Migration results
     */
    public static function migrate_blog_queue_data() {
        global $wpdb;
        
        $results = array(
            'migrated' => 0,
            'errors' => 0,
            'messages' => array()
        );
        
        // Check if old blog queue table exists
        $old_table = $wpdb->prefix . 'ai_explainer_job_queue';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom plugin table requires direct query, table name is prefixed and safe
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $old_table)) !== $old_table) {
            $results['messages'][] = 'Old blog queue table does not exist';
            return $results;
        }

        // Get all jobs from old queue
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom plugin table requires direct query, table name is prefixed and safe
        $old_jobs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $old_table ORDER BY created_at ASC"));
        
        if (empty($old_jobs)) {
            $results['messages'][] = 'No jobs to migrate';
            return $results;
        }
        
        // Get new queue tables
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        $meta_table = $wpdb->prefix . 'ai_explainer_job_meta';
        
        foreach ($old_jobs as $old_job) {
            try {
                // Prepare options data
                $options = json_decode($old_job->options, true);
                if (!is_array($options)) {
                    $options = array();
                }
                
                // Map old status to new status
                $status_map = array(
                    'pending' => 'pending',
                    'processing' => 'processing',
                    'completed' => 'completed',
                    'failed' => 'failed',
                    'cancelled' => 'paused'
                );
                $new_status = $status_map[$old_job->status] ?? 'pending';
                
                // Prepare job data for new system
                $job_data = array(
                    'selection_text' => $old_job->selection_text,
                    'options' => $options
                );
                
                // Insert into new queue table
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query for migration
                $wpdb->insert(
                    $queue_table,
                    array(
                        'job_type' => 'blog_creation',
                        'status' => $new_status,
                        'data' => json_encode($job_data),
                        'priority' => $old_job->priority ?? 10,
                        'attempts' => $old_job->retry_count ?? 0,
                        'max_attempts' => 3,
                        'scheduled_at' => $old_job->created_at,
                        'started_at' => $old_job->started_at,
                        'completed_at' => $old_job->completed_at,
                        'error_message' => $old_job->error_message,
                        'created_by' => $old_job->created_by,
                        'created_at' => $old_job->created_at,
                        'updated_at' => $old_job->updated_at
                    ),
                    array('%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
                );
                
                $new_queue_id = $wpdb->insert_id;
                
                if ($new_queue_id) {
                    // Migrate result data if exists
                    if (!empty($old_job->result_data)) {
                        $result_data = json_decode($old_job->result_data, true);
                        if (is_array($result_data)) {
                            foreach ($result_data as $key => $value) {
                                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Custom table with proper indexes (meta_key, queue_id+meta_key)
                                $wpdb->insert(
                                    $meta_table,
                                    array(
                                        'queue_id' => $new_queue_id,
                                        'meta_key' => $key,
                                        'meta_value' => maybe_serialize($value)
                                    ),
                                    array('%d', '%s', '%s')
                                );
                            }
                        }
                    }

                    // Store old job_id mapping for reference
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Custom table with proper indexes (meta_key, queue_id+meta_key)
                    $wpdb->insert(
                        $meta_table,
                        array(
                            'queue_id' => $new_queue_id,
                            'meta_key' => '_old_job_id',
                            'meta_value' => $old_job->job_id
                        ),
                        array('%d', '%s', '%s')
                    );
                    
                    $results['migrated']++;
                    $results['messages'][] = sprintf('Migrated job %s to new queue ID %d', esc_html($old_job->job_id), esc_html($new_queue_id));
                }
            } catch (\Exception $e) {
                $results['errors']++;
                $results['messages'][] = sprintf('Failed to migrate job %s: %s', esc_html($old_job->job_id), esc_html($e->getMessage()));
            }
        }
        
        return $results;
    }
    
    /**
     * Create a blog creation job using the new system
     * 
     * @param string $selection_text Selected text
     * @param array $options Generation options
     * @return int|false Job queue ID or false on failure
     */
    public static function create_blog_job($selection_text, $options) {
        try {
            if (function_exists('explainer_log_info')) {
                explainer_log_info('Migration: Starting blog job creation', array(), 'migration');
            }
            
            // Get job queue manager instance
            $manager = \ExplainerPlugin_Job_Queue_Manager::get_instance();
            
            if (!$manager) {
                if (function_exists('explainer_log_error')) {
                    explainer_log_error('Migration: Failed to get job queue manager instance', array(), 'migration');
                }
                return false;
            }
            
            // Prepare job data
            $job_data = array(
                'selection_text' => $selection_text,
                'options' => $options
            );
            
            if (function_exists('explainer_log_info')) {
                explainer_log_info('Migration: Queueing job with data', array(
                    'selection_length' => strlen($selection_text),
                    'ai_provider' => $options['ai_provider'] ?? 'unknown'
                ), 'migration');
            }
            
            // Queue the job
            $job_id = $manager->queue_job('blog_creation', $job_data);
            
            if ($job_id) {
                if (function_exists('explainer_log_info')) {
                    explainer_log_info('Migration: Job queued successfully with ID: ' . $job_id, array(), 'migration');
                }
                
                // Queue job for manual execution if cron is disabled, or automatic processing if cron is enabled
                if (!get_option('explainer_enable_cron', false)) {
                    if (function_exists('explainer_log_info')) {
                        explainer_log_info('Migration: Cron disabled, job queued for manual execution', array(), 'migration');
                    }
                } else {
                    if (function_exists('explainer_log_info')) {
                        explainer_log_info('Migration: Cron enabled, job will be processed by cron', array(), 'migration');
                    }
                }
            } else {
                if (function_exists('explainer_log_error')) {
                    explainer_log_error('Migration: Failed to queue job - queue_job returned false/null', array(), 'migration');
                }
            }
            
            return $job_id;
            
        } catch (\Exception $e) {
            if (function_exists('explainer_log_error')) {
                explainer_log_error('Migration: Exception during blog job creation: ' . $e->getMessage(), array(), 'migration');
            }
            if (function_exists('explainer_log_error')) {
                explainer_log_error('Migration: Stack trace', array('trace' => $e->getTraceAsString()), 'migration');
            }
            return false;
        }
    }
    
    /**
     * Get jobs in format compatible with old system
     * 
     * @param string $job_type Optional job type filter (defaults to 'blog_creation')
     * @param int $limit Number of jobs to return
     * @return array Jobs array
     */
    public static function get_jobs_legacy_format($job_type = null, $limit = 50) {
        global $wpdb;
        
        // Clean up stale processing jobs first
        self::cleanup_stale_jobs();
        
        // Ensure old cron jobs are cleared (one-time cleanup)
        static $cron_cleaned = false;
        if (!$cron_cleaned) {
            if (class_exists('\WPAIExplainer\JobQueue\ExplainerPlugin_Deprecated_Cleanup')) {
                \WPAIExplainer\JobQueue\ExplainerPlugin_Deprecated_Cleanup::cleanup_old_blog_queue(false);
            }
            $cron_cleaned = true;
        }
        
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        $meta_table = $wpdb->prefix . 'ai_explainer_job_meta';
        
        // Build query - exclude paused jobs (which map to cancelled in legacy format)
        $job_type = $job_type ?: 'blog_creation'; // Default to blog_creation if not specified
        $where = "WHERE job_type = %s AND status != 'paused'";

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefixed and safe
        $query = "SELECT * FROM $queue_table $where ORDER BY created_at DESC LIMIT %d";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query uses placeholders, values are prepared
        $jobs = $wpdb->get_results($wpdb->prepare($query, $job_type, $limit));
        
        $legacy_jobs = array();
        
        foreach ($jobs as $job) {
            // Get job data (handle both serialized and JSON formats)
            $data = maybe_unserialize($job->data);
            if (!is_array($data)) {
                // Try JSON decode as fallback
                $data = json_decode($job->data, true);
            }
            if (!is_array($data)) {
                $data = array();
            }
            
            // Get metadata
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefixed and safe
            $meta_query = $wpdb->prepare(
                "SELECT meta_key, meta_value FROM $meta_table WHERE queue_id = %d",
                $job->queue_id
            );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query uses placeholders, values are prepared
            $meta_rows = $wpdb->get_results($meta_query);
            
            $result_data = array();
            $old_job_id = '';
            
            foreach ($meta_rows as $meta) {
                if ($meta->meta_key === '_old_job_id') {
                    $old_job_id = $meta->meta_value;
                } else {
                    $result_data[$meta->meta_key] = maybe_unserialize($meta->meta_value);
                }
            }
            
            // Map status
            $status_map = array(
                'paused' => 'cancelled'
            );
            $legacy_status = $status_map[$job->status] ?? $job->status;
            
            // Create selection preview for display
            $selection_text = $data['selection_text'] ?? '';
            $selection_preview = $selection_text ? substr($selection_text, 0, 80) . '...' : 'Blog Post Creation';
            
            // Generate edit and preview links for completed jobs
            $edit_link = '';
            $preview_link = '';
            if ($job->status === 'completed') {
                $post_id = 0;
                
                // Try to get post_id from different metadata locations
                if (isset($result_data['post_id'])) {
                    $post_id = absint($result_data['post_id']);
                } elseif (isset($result_data['result']['post_id'])) {
                    $post_id = absint($result_data['result']['post_id']);
                } elseif (isset($result_data['result']) && is_array($result_data['result']) && isset($result_data['result']['post_id'])) {
                    $post_id = absint($result_data['result']['post_id']);
                }
                
                if ($post_id > 0) {
                    $edit_link = admin_url('post.php?post=' . $post_id . '&action=edit');
                    $preview_link = get_preview_post_link($post_id);
                    if (!$preview_link) {
                        $preview_link = get_permalink($post_id);
                    }
                }
            }
            
            // Create legacy format job
            $legacy_job = array(
                'id' => $job->queue_id,
                'job_id' => $old_job_id ?: 'jq_' . $job->queue_id,
                'status' => $legacy_status,
                'job_type' => 'blog_creation',
                'selection_text' => $selection_text,
                'selection_preview' => $selection_preview,
                'options' => json_encode($data['options'] ?? array()),
                'created_by' => $job->created_by,
                'created_at' => $job->created_at,
                'updated_at' => $job->updated_at,
                'started_at' => $job->started_at,
                'completed_at' => $job->completed_at,
                'error_message' => $job->error_message,
                'result_data' => !empty($result_data) ? json_encode($result_data) : null,
                'priority' => $job->priority,
                'retry_count' => $job->attempts,
                'edit_link' => $edit_link,
                'preview_link' => $preview_link
            );
            
            $legacy_jobs[] = (object)$legacy_job;
        }
        
        return $legacy_jobs;
    }
    
    /**
     * Cancel a job using the new system
     *
     * @param string $job_id Legacy job ID or queue ID
     * @return bool Success status
     */
    public static function cancel_job($job_id) {
        global $wpdb;

        // Handle both old format (job_xxx) and new format (numeric)
        if (strpos($job_id, 'jq_') === 0) {
            $queue_id = intval(substr($job_id, 3));
        } else {
            // Try to find job by old ID
            $meta_table = $wpdb->prefix . 'ai_explainer_job_meta';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefixed and safe
            $queue_id = $wpdb->get_var($wpdb->prepare(
                "SELECT queue_id FROM $meta_table WHERE meta_key = '_old_job_id' AND meta_value = %s",
                $job_id
            ));

            if (!$queue_id) {
                // Assume it's a numeric queue ID
                $queue_id = intval($job_id);
            }
        }
        
        if (!$queue_id) {
            return false;
        }
        
        // Delete the job entirely when cancelled
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        
        // First delete any job metadata
        $meta_table = $wpdb->prefix . 'ai_explainer_job_meta';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $wpdb->delete(
            $meta_table,
            array('queue_id' => $queue_id),
            array('%d')
        );

        // Then delete the job itself
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $result = $wpdb->delete(
            $queue_table,
            array('queue_id' => $queue_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Retry a job using the new system
     *
     * @param string $job_id Legacy job ID or queue ID
     * @return bool Success status
     */
    public static function retry_job($job_id) {
        global $wpdb;

        // Handle both old format (job_xxx) and new format (numeric)
        if (strpos($job_id, 'jq_') === 0) {
            $queue_id = intval(substr($job_id, 3));
        } else {
            // Try to find job by old ID
            $meta_table = $wpdb->prefix . 'ai_explainer_job_meta';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefixed and safe
            $queue_id = $wpdb->get_var($wpdb->prepare(
                "SELECT queue_id FROM $meta_table WHERE meta_key = '_old_job_id' AND meta_value = %s",
                $job_id
            ));

            if (!$queue_id) {
                // Assume it's a numeric queue ID
                $queue_id = intval($job_id);
            }
        }
        
        if (!$queue_id) {
            return false;
        }
        
        // Reset job status to pending and clear error
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $result = $wpdb->update(
            $queue_table,
            array(
                'status' => 'pending',
                'error_message' => null,
                'updated_at' => current_time('mysql'),
                'attempts' => 0
            ),
            array('queue_id' => $queue_id),
            array('%s', '%s', '%s', '%d'),
            array('%d')
        );
        
        if ($result !== false) {
            // Process immediately if cron is disabled
            if (!get_option('explainer_enable_cron', false)) {
                try {
                    $manager = \ExplainerPlugin_Job_Queue_Manager::get_instance();
                    $manager->process_queue('blog_creation', 1);
                } catch (\Exception $e) {
                    if (function_exists('explainer_log_error')) {
                        explainer_log_error('Failed to process retried job: ' . $e->getMessage(), array(), 'migration');
                    }
                }
            }
        }
        
        return $result !== false;
    }
    
    /**
     * Clean up stale processing jobs
     * 
     * Resets jobs that have been in 'processing' status for too long (likely due to crashes/timeouts)
     * 
     * @param int $timeout_minutes Jobs stuck in processing for this many minutes will be reset (default 10)
     * @return int Number of jobs reset
     */
    public static function cleanup_stale_jobs($timeout_minutes = 10) {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        
        // Calculate cutoff time
        $cutoff_time = wp_date('Y-m-d H:i:s', strtotime("-{$timeout_minutes} minutes"));
        
        // Reset jobs that have been processing for too long
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $reset_count = $wpdb->update(
            $queue_table,
            array(
                'status' => 'pending',
                'started_at' => null,
                'updated_at' => current_time('mysql')
            ),
            array(
                'job_type' => 'blog_creation',
                'status' => 'processing'
            ),
            array('%s', '%s', '%s'),
            array('%s', '%s')
        );
        
        // Add WHERE clause for timeout via raw query since wpdb doesn't support complex WHERE with UPDATE
        if ($reset_count === false) {
            // Fallback to direct query
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefixed and safe
            $reset_count = $wpdb->query($wpdb->prepare(
                "UPDATE $queue_table
                SET status = 'pending', started_at = NULL, updated_at = %s
                WHERE job_type = 'blog_creation'
                AND status = 'processing'
                AND started_at IS NOT NULL
                AND started_at < %s",
                current_time('mysql'),
                $cutoff_time
            ));
        }
        
        if ($reset_count > 0) {
            if (function_exists('explainer_log_info')) {
                explainer_log_info('Migration: Reset stale processing jobs', array(
                    'reset_count' => $reset_count,
                    'timeout_minutes' => $timeout_minutes
                ), 'migration');
            }
        }
        
        return $reset_count;
    }

    /**
     * Clear completed jobs
     * 
     * @return int Number of jobs cleared
     */
    public static function clear_completed_jobs() {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
        $meta_table = $wpdb->prefix . 'ai_explainer_job_meta';
        
        // Get completed blog creation jobs
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefixed and safe
        $completed_jobs = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT queue_id FROM $queue_table
                 WHERE job_type = %s AND status = %s",
                'blog_creation',
                'completed'
            )
        );
        
        if (empty($completed_jobs)) {
            return 0;
        }
        
        // Delete metadata for these jobs
        $placeholders = implode(',', array_fill(0, count($completed_jobs), '%d'));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name is prefixed and safe, placeholders are dynamically generated for IN clause
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $meta_table WHERE queue_id IN ($placeholders)",
            ...$completed_jobs
        ));

        // Delete the jobs
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name is prefixed and safe, placeholders are dynamically generated for IN clause
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $queue_table WHERE queue_id IN ($placeholders)",
            ...$completed_jobs
        ));
        
        return $deleted;
    }
}