<?php
/**
 * AJAX Pagination Handlers for AI Explainer
 * 
 * Handles AJAX requests for paginated data across all admin sections.
 *
 * @package WPAIExplainer
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX handlers for pagination functionality
 */
class ExplainerPlugin_Pagination_Handlers {
    
    /**
     * Constructor - register AJAX hooks
     */
    public function __construct() {
        // Debug logging disabled - pagination working correctly
        // file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . ' - Pagination handlers constructor called' . "\n", FILE_APPEND);
        
        // Test handler to verify AJAX is working
        add_action('wp_ajax_test_pagination_handler', array($this, 'handle_test'));
        
        // Popular selections pagination
        add_action('wp_ajax_explainer_get_popular_selections', array($this, 'handle_get_popular_selections'));
        
        // Enhanced existing handlers with pagination support
        add_action('wp_ajax_explainer_get_processed_posts_paginated', array($this, 'handle_get_processed_posts_paginated'));
        add_action('wp_ajax_explainer_get_jobs_paginated', array($this, 'handle_get_jobs_paginated'));
        add_action('wp_ajax_get_post_scan_queue_status_paginated', array($this, 'handle_get_post_scan_queue_paginated'));
        
        // Created posts pagination
        add_action('wp_ajax_explainer_get_created_posts', array($this, 'handle_get_created_posts'));
        
        // Job queue pagination with filters
        add_action('wp_ajax_explainer_get_job_queue_paginated', array($this, 'handle_get_job_queue_paginated'));

        // Delete all selections
        add_action('wp_ajax_explainer_delete_all_selections', array($this, 'handle_delete_all_selections'));

        // Log registered actions for debugging
        add_action('wp_loaded', array($this, 'debug_registered_actions'));
    }
    
    /**
     * Debug method to check if actions are registered
     */
    public function debug_registered_actions() {
        global $wp_filter;
        
        $actions_to_check = [
            'wp_ajax_explainer_get_popular_selections',
            'wp_ajax_explainer_get_processed_posts_paginated',
            'wp_ajax_explainer_get_jobs_paginated',
            'wp_ajax_get_post_scan_queue_status_paginated',
            'wp_ajax_explainer_get_created_posts',
            'wp_ajax_explainer_get_job_queue_paginated'
        ];
        
        foreach ($actions_to_check as $action) {
            $registered = isset($wp_filter[$action]) && !empty($wp_filter[$action]);
            // Debug: Check if action is registered
        }
    }
    
    /**
     * Simple test handler to verify AJAX functionality
     */
    public function handle_test() {
        wp_send_json_success(array('message' => 'Test handler working', 'timestamp' => current_time('mysql')));
    }
    
    /**
     * Handle popular selections AJAX request with pagination
     */
    public function handle_get_popular_selections() {
        // Debug logging disabled - pagination working correctly
        // file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . ' - handle_get_popular_selections called with POST: ' . print_r($_POST, true) . "\n", FILE_APPEND);
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_admin_nonce')) {
            ExplainerPlugin_Debug_Logger::warning('Nonce verification failed. POST nonce: ' . (isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : 'not set'), 'Pagination');
            wp_send_json_error('Security check failed');
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Check if selections table exists
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) !== $table_name) {
            wp_send_json_success(array(
                'items' => array(),
                'pagination' => array(
                    'current_page' => 1,
                    'per_page' => 20,
                    'total_items' => 0,
                    'total_pages' => 0,
                    'has_previous' => false,
                    'has_next' => false,
                    'offset' => 0,
                    'limit' => 20,
                ),
                'pagination_html' => '',
            ));
        }
        
        // Get and validate parameters
        $params = ExplainerPlugin_Pagination::validate_pagination_params(array(
            'page' => isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1,
            'per_page' => isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 20,
            'orderby' => isset($_POST['orderby']) ? sanitize_text_field(wp_unslash($_POST['orderby'])) : 'count',
            'order' => isset($_POST['order']) ? sanitize_text_field(wp_unslash($_POST['order'])) : 'desc',
            'search' => isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '',
        ));
        
        try {
            ExplainerPlugin_Debug_Logger::ajax_request('explainer_get_popular_selections', $_POST);
            
            // Check if pagination class exists
            if (!class_exists('ExplainerPlugin_Pagination')) {
                ExplainerPlugin_Debug_Logger::error('ExplainerPlugin_Pagination class not found', 'Pagination');
                wp_send_json_error('Pagination class not available');
                return;
            }
            
            // Get total count for pagination
            $search_term = isset($params['search']) ? $params['search'] : '';
            $total_count = $this->get_popular_selections_count($search_term);
            ExplainerPlugin_Debug_Logger::info("Total count of selections: {$total_count}", 'Pagination');
            
            // Create pagination instance
            $pagination = new ExplainerPlugin_Pagination($total_count, $params['per_page'], $params['page']);
            
            // Check if method exists
            if (!method_exists($pagination, 'get_pagination_args')) {
                ExplainerPlugin_Debug_Logger::error('get_pagination_args method not found', 'Pagination');
                wp_send_json_error('Pagination method not available');
                return;
            }
            
            $pagination_args = $pagination->get_pagination_args();
            
            // Get popular selections data
            $selections = $this->get_popular_selections_data($params, $pagination_args);
            ExplainerPlugin_Debug_Logger::info("Selections data count: " . count($selections), 'Pagination');
            
            $response_data = array(
                'items' => $selections,
                'pagination' => $pagination->get_pagination_data(),
                'pagination_html' => $pagination->get_pagination_html('selections-pagination'),
            );
            
            ExplainerPlugin_Debug_Logger::ajax_response('explainer_get_popular_selections', true, $response_data);
            wp_send_json_success($response_data);
            
        } catch (Exception $e) {
            ExplainerPlugin_Debug_Logger::error('Error fetching popular selections: ' . $e->getMessage(), 'Pagination');
            ExplainerPlugin_Debug_Logger::ajax_response('explainer_get_popular_selections', false, $e->getMessage());
            wp_send_json_error('Failed to load popular selections');
        }
    }
    
    /**
     * Handle processed posts AJAX request with pagination
     */
    public function handle_get_processed_posts_paginated() {
        // Debug logging disabled - pagination working correctly  
        // file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . ' - handle_get_processed_posts_paginated called with POST: ' . print_r($_POST, true) . "\n", FILE_APPEND);
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Restore actual data fetching logic

        // Original complex logic follows...

        // Get and validate parameters
        $params = ExplainerPlugin_Pagination::validate_pagination_params(array(
            'page' => isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1,
            'per_page' => isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 20,
            'orderby' => isset($_POST['orderby']) ? sanitize_text_field(wp_unslash($_POST['orderby'])) : 'processed_date',
            'order' => isset($_POST['order']) ? sanitize_text_field(wp_unslash($_POST['order'])) : 'desc',
        ));
        
        try {
            global $wpdb;
            
            // Check if job queue table exists
            $table_name = $wpdb->prefix . 'ai_explainer_job_queue';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) !== $table_name) {
                wp_send_json_success(array(
                    'items' => array(),
                    'pagination' => array(
                        'current_page' => 1,
                        'per_page' => 20,
                        'total_items' => 0,
                        'total_pages' => 0,
                        'has_previous' => false,
                        'has_next' => false,
                        'offset' => 0,
                        'limit' => 20,
                    ),
                    'pagination_html' => '',
                ));
            }
            
            // Get total count
            $count_query = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ai_explainer_job_queue
                WHERE job_type = %s AND status = %s",
                'ai_term_scan',
                'completed'
            );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $total_count = $wpdb->get_var($count_query);
            
            // Create pagination instance
            $pagination = new ExplainerPlugin_Pagination($total_count, $params['per_page'], $params['page']);
            $pagination_args = $pagination->get_pagination_args();
            
            // Build ORDER BY clause
            $allowed_orderby = array('processed_date', 'status', 'queue_id');
            $orderby = in_array($params['orderby'], $allowed_orderby) ? $params['orderby'] : 'processed_date';
            $order = $params['order'] === 'asc' ? 'ASC' : 'DESC';
            
            if ($orderby === 'processed_date') {
                $order_clause = "j.completed_at {$order}";
            } elseif ($orderby === 'status') {
                $order_clause = "j.status {$order}";
            } else {
                $order_clause = "j.queue_id {$order}";
            }
            
            // Get processed posts with pagination
            // Note: post_id is stored in serialized data field, so we extract it using WordPress functions
            // Build the query safely without interpolation in prepare
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
            $base_query = "SELECT j.*, j.data as job_data
                FROM {$wpdb->prefix}ai_explainer_job_queue j
                WHERE j.job_type = %s AND j.status = %s
                ORDER BY " . $order_clause . "
                LIMIT %d OFFSET %d";

            $query = $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $base_query,
                'ai_term_scan',
                'completed',
                $pagination_args['limit'],
                $pagination_args['offset']
            );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $processed_posts = $wpdb->get_results($query);
            
            // Format data for response
            $formatted_posts = array();
            foreach ($processed_posts as $job) {
                // Unserialize the job data to extract post_id and title
                $job_data = maybe_unserialize($job->data);
                $post_id = isset($job_data['post_id']) ? (int) $job_data['post_id'] : 0;
                $post_title = isset($job_data['post_title']) ? $job_data['post_title'] : __('(No title)', 'ai-explainer');
                
                // If we don't have title in job data, try to get it from posts table
                if (!$post_title && $post_id) {
                    $post_title = get_the_title($post_id) ?: __('(No title)', 'ai-explainer');
                }
                
                // Count terms if this is term scan job
                $term_count = 0;
                if (isset($job_data['scan_type']) && $job_data['scan_type'] === 'ai_term_scan') {
                    // Try to get term count from result_data
                    $result_data = maybe_unserialize($job->result_data);
                    if ($result_data && isset($result_data['terms_found'])) {
                        $term_count = (int) $result_data['terms_found'];
                    } elseif ($result_data && isset($result_data['result']['terms_found'])) {
                        $term_count = (int) $result_data['result']['terms_found'];
                    } else {
                        // Fallback: count from selections table
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        $term_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}ai_explainer_selections WHERE post_id = %d AND source = %s",
                            $post_id,
                            'ai_scan'
                        ));
                    }
                }
                
                $formatted_posts[] = array(
                    'id' => $post_id,
                    'title' => $post_title,
                    'processed_date' => $job->completed_at,
                    'term_count' => $term_count,
                    'job_id' => $job->queue_id,
                    'status' => $job->status,
                );
            }
            
            wp_send_json_success(array(
                'items' => $formatted_posts,
                'pagination' => $pagination->get_pagination_data(),
                'pagination_html' => $pagination->get_pagination_html('processed-posts-pagination'),
            ));
            
        } catch (Exception $e) {
            if (ExplainerPlugin_Debug_Logger::is_enabled()) {
                ExplainerPlugin_Debug_Logger::error('Error fetching processed posts: ' . $e->getMessage(), 'admin');
            }
            wp_send_json_error('Failed to load processed posts');
        }
    }
    
    /**
     * Handle job queue AJAX request with pagination (for blog posts)
     */
    public function handle_get_jobs_paginated() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Get and validate parameters
        $params = ExplainerPlugin_Pagination::validate_pagination_params(array(
            'page' => isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1,
            'per_page' => isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 20,
            'orderby' => isset($_POST['orderby']) ? sanitize_text_field(wp_unslash($_POST['orderby'])) : 'created_at',
            'order' => isset($_POST['order']) ? sanitize_text_field(wp_unslash($_POST['order'])) : 'desc',
        ));

        $job_type_filter = isset($_POST['job_type_filter']) ? sanitize_text_field(wp_unslash($_POST['job_type_filter'])) : 'blog_creation';
        
        try {
            global $wpdb;
            
            // Check if job queue table exists
            $table_name = $wpdb->prefix . 'ai_explainer_job_queue';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) !== $table_name) {
                wp_send_json_success(array(
                    'items' => array(),
                    'pagination' => array(
                        'current_page' => 1,
                        'per_page' => 10,
                        'total_items' => 0,
                        'total_pages' => 0,
                        'has_previous' => false,
                        'has_next' => false,
                        'offset' => 0,
                        'limit' => 10,
                    ),
                    'pagination_html' => '',
                ));
            }
            
            // Get total count
            $count_query = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ai_explainer_job_queue WHERE job_type = %s",
                $job_type_filter
            );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $total_count = $wpdb->get_var($count_query);
            
            // Create pagination instance
            $pagination = new ExplainerPlugin_Pagination($total_count, $params['per_page'], $params['page']);
            $pagination_args = $pagination->get_pagination_args();
            
            // Build ORDER BY clause
            $allowed_orderby = array('created_at', 'status', 'progress');
            $orderby = in_array($params['orderby'], $allowed_orderby) ? $params['orderby'] : 'created_at';
            $order = $params['order'] === 'asc' ? 'ASC' : 'DESC';
            
            // Get jobs with pagination
            // Build the query safely without interpolation in prepare
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
            $base_query = "SELECT * FROM {$wpdb->prefix}ai_explainer_job_queue
                WHERE job_type = %s
                ORDER BY " . $orderby . " " . $order . "
                LIMIT %d OFFSET %d";

            $query = $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $base_query,
                $job_type_filter,
                $pagination_args['limit'],
                $pagination_args['offset']
            );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $jobs = $wpdb->get_results($query);
            
            // Format jobs data
            $formatted_jobs = array();
            foreach ($jobs as $job) {
                $job_data = json_decode($job->job_data, true);
                $formatted_job = array(
                    'job_id' => $job->id,
                    'status' => $job->status,
                    'progress' => $job->progress,
                    'progress_percent' => $job->progress ?: 0,
                    'progress_text' => $this->get_progress_text($job->status, $job->progress),
                    'status_text' => $this->get_status_text($job->status),
                    'created_at' => $job->created_at,
                    'started_at' => $job->started_at,
                    'completed_at' => $job->completed_at,
                    'error_message' => $job->error_message,
                    'queue_position' => '', // Will be calculated if needed
                );

                // Add blog_creation specific data
                if ($job_type_filter === 'blog_creation' && $job_data) {
                    $selection_text = $job_data['selection_text'] ?? '';
                    $options = $job_data['options'] ?? array();
                    
                    $formatted_job['title'] = wp_trim_words($selection_text, 8, '...');
                    $formatted_job['explanation_preview'] = wp_trim_words($selection_text, 15, '...');
                    
                    // For completed jobs, try to get post information
                    if ($job->status === 'completed') {
                        $post_info = $this->get_completed_blog_post_info($job->id, $job->created_by);
                        if ($post_info) {
                            $formatted_job['post_title'] = $post_info['post_title'];
                            $formatted_job['post_thumbnail'] = $post_info['thumbnail'];
                            $formatted_job['edit_link'] = $post_info['edit_link'];
                            $formatted_job['view_link'] = $post_info['view_link'];
                            /* translators: %s: post title */
                            $formatted_job['completed_message'] = sprintf( __('Blog post "%s" created successfully', 'ai-explainer'), esc_html($post_info['post_title']) );
                        } else {
                            $formatted_job['completed_message'] = __('Blog post creation completed', 'ai-explainer');
                        }
                    }
                } else {
                    // Default title for other job types
                    $formatted_job['title'] = $job_data['title'] ?? __('Job', 'ai-explainer') . ' #' . $job->id;
                    $formatted_job['explanation_preview'] = '';
                }
                
                $formatted_jobs[] = $formatted_job;
            }
            
            wp_send_json_success(array(
                'items' => $formatted_jobs,
                'pagination' => $pagination->get_pagination_data(),
                'pagination_html' => $pagination->get_pagination_html('jobs-pagination'),
            ));
            
        } catch (Exception $e) {
            if (ExplainerPlugin_Debug_Logger::is_enabled()) {
                ExplainerPlugin_Debug_Logger::error('Error fetching jobs: ' . $e->getMessage(), 'admin');
            }
            wp_send_json_error('Failed to load jobs');
        }
    }
    
    /**
     * Handle post scan queue AJAX request with pagination
     */
    public function handle_get_post_scan_queue_paginated() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Use same handler as jobs but filter for ai_term_scan
        $_POST['job_type_filter'] = 'ai_term_scan'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified above
        $this->handle_get_jobs_paginated();
    }
    
    /**
     * Get popular selections count for pagination
     *
     * @param string $search Search term
     * @return int Total count
     */
    private function get_popular_selections_count($search = '') {
        global $wpdb;

        try {
            // Create cache key based on search term
            $cache_key = 'popular_selections_count_' . md5($search);
            $cache_group = 'wp_ai_explainer';

            // Check cache first
            $cached_result = wp_cache_get($cache_key, $cache_group);
            if (false !== $cached_result) {
                return (int) $cached_result;
            }

            $where_clause = '1=1';
            $params = array();

            if (!empty($search)) {
                $where_clause .= ' AND selected_text LIKE %s';
                $params[] = '%' . $wpdb->esc_like($search) . '%';
            }

            // Build base query without interpolation
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
            $base_query = "SELECT COUNT(DISTINCT selected_text) FROM {$wpdb->prefix}ai_explainer_selections WHERE " . $where_clause;

            if (!empty($params)) {
                $query = $wpdb->prepare($base_query, ...$params); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            } else {
                $query = $base_query;
            }
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $result = $wpdb->get_var($query);

            if ($wpdb->last_error) {
                ExplainerPlugin_Debug_Logger::db_query($query, null, $wpdb->last_error);
                return 0;
            } else {
                ExplainerPlugin_Debug_Logger::db_query($query, (int) $result);
            }

            // Cache the result for 5 minutes
            wp_cache_set($cache_key, (int) $result, $cache_group, 300);

            return (int) $result;
        } catch (Exception $e) {
            ExplainerPlugin_Debug_Logger::error("Exception in get_popular_selections_count: " . $e->getMessage(), 'Database');
            return 0;
        }
    }
    
    /**
     * Get popular selections data with pagination
     *
     * @param array $params Query parameters
     * @param array $pagination_args Pagination arguments
     * @return array Selections data
     */
    private function get_popular_selections_data($params, $pagination_args) {
        global $wpdb;
        
        try {
            $where_clause = '1=1';
            $query_params = array();
            
            if (!empty($params['search'])) {
                $where_clause .= ' AND selected_text LIKE %s';
                $query_params[] = '%' . $wpdb->esc_like($params['search']) . '%';
            }
            
            // Build ORDER BY clause
            $allowed_orderby = array('count', 'first_seen', 'last_seen', 'selected_text');
            $orderby = in_array($params['orderby'], $allowed_orderby) ? $params['orderby'] : 'count';
            $order = $params['order'] === 'asc' ? 'ASC' : 'DESC';

            // Build base query without interpolation
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
            $base_query = "SELECT id, selected_text, source_urls,
                             COUNT(*) as count,
                             MIN(first_seen) as first_seen,
                             MAX(last_seen) as last_seen
                      FROM {$wpdb->prefix}ai_explainer_selections
                      WHERE " . $where_clause . "
                      GROUP BY selected_text
                      ORDER BY " . $orderby . " " . $order . "
                      LIMIT %d OFFSET %d";

            $query_params[] = $pagination_args['limit'];
            $query_params[] = $pagination_args['offset'];

            $prepared_query = $wpdb->prepare($base_query, ...$query_params); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results($prepared_query);
            
            if ($wpdb->last_error) {
                ExplainerPlugin_Debug_Logger::db_query($prepared_query, null, $wpdb->last_error);
                return array();
            } else {
                ExplainerPlugin_Debug_Logger::db_query($prepared_query, count($results));
            }
            
            // Format results
            $formatted_results = array();
            foreach ($results as $selection) {
                // Get all reading level data for this text selection
                $reading_level_data = $this->get_reading_level_data($selection->selected_text);
                
                $formatted_results[] = array(
                    'id' => $selection->id,
                    'selected_text' => $selection->selected_text,
                    'count' => $selection->count,
                    'first_seen' => $selection->first_seen,
                    'last_seen' => $selection->last_seen,
                    'url' => $selection->source_urls,
                    'reading_level_data' => $reading_level_data,
                    'reading_level_count' => count($reading_level_data),
                );
            }
            
            return $formatted_results;
        } catch (Exception $e) {
            ExplainerPlugin_Debug_Logger::error("Exception in get_popular_selections_data: " . $e->getMessage(), 'Database');
            return array();
        }
    }
    
    /**
     * Get reading level data for a specific text selection
     *
     * @param string $selected_text The text to get reading levels for
     * @return array Reading level data grouped by reading level
     */
    private function get_reading_level_data($selected_text) {
        global $wpdb;

        try {
            // Create cache key based on selected text
            $cache_key = 'reading_level_data_' . md5($selected_text);
            $cache_group = 'wp_ai_explainer';

            // Check cache first
            $cached_result = wp_cache_get($cache_key, $cache_group);
            if (false !== $cached_result) {
                return $cached_result;
            }

            $query = $wpdb->prepare(
                "SELECT reading_level,
                       COUNT(*) as selection_count,
                       MAX(id) as id,
                       MAX(ai_explanation) as ai_explanation,
                       GROUP_CONCAT(DISTINCT source_urls) as source_urls,
                       MIN(first_seen) as first_seen,
                       MAX(last_seen) as last_seen,
                       MAX(enabled) as enabled
                FROM {$wpdb->prefix}ai_explainer_selections
                WHERE selected_text = %s
                GROUP BY reading_level
                ORDER BY
                    CASE reading_level
                        WHEN 'simple' THEN 1
                        WHEN 'standard' THEN 2
                        WHEN 'advanced' THEN 3
                        ELSE 4
                    END",
                $selected_text
            );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results($query);

            if ($wpdb->last_error) {
                ExplainerPlugin_Debug_Logger::db_query($query, null, $wpdb->last_error);
                return array();
            }

            // Group by reading level
            $reading_levels = array();
            foreach ($results as $row) {
                $reading_levels[$row->reading_level] = array(
                    'id' => $row->id,
                    'reading_level' => $row->reading_level,
                    'ai_explanation' => $row->ai_explanation,
                    'source_urls' => $row->source_urls,
                    'first_seen' => $row->first_seen,
                    'last_seen' => $row->last_seen,
                    'enabled' => (bool) $row->enabled,
                    'selection_count' => (int) $row->selection_count,
                );
            }

            // Cache the result for 5 minutes
            wp_cache_set($cache_key, $reading_levels, $cache_group, 300);

            return $reading_levels;
        } catch (Exception $e) {
            ExplainerPlugin_Debug_Logger::error("Exception in get_reading_level_data: " . $e->getMessage(), 'Database');
            return array();
        }
    }
    
    /**
     * Handle created posts AJAX request with pagination and search
     */
    public function handle_get_created_posts() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Get and validate parameters
        $params = ExplainerPlugin_Pagination::validate_pagination_params(array(
            'page' => isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1,
            'per_page' => isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 10,
            'orderby' => isset($_POST['orderby']) ? sanitize_text_field(wp_unslash($_POST['orderby'])) : 'created_at',
            'order' => isset($_POST['order']) ? sanitize_text_field(wp_unslash($_POST['order'])) : 'desc',
            'search' => isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '',
        ));
        
        try {
            global $wpdb;
            
            // Check if tables exist
            $blog_posts_table = $wpdb->prefix . 'ai_explainer_blog_posts';
            $job_queue_table = $wpdb->prefix . 'ai_explainer_job_queue';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $blog_posts_table)) !== $blog_posts_table) {
                wp_send_json_success(array(
                    'items' => array(),
                    'pagination' => array(
                        'current_page' => 1,
                        'per_page' => 10,
                        'total_items' => 0,
                        'total_pages' => 0,
                        'has_previous' => false,
                        'has_next' => false,
                        'offset' => 0,
                        'limit' => 10,
                    ),
                    'pagination_html' => '',
                ));
            }
            
            // Build search conditions
            $where_conditions = array();
            $query_params = array();
            
            if (!empty($params['search'])) {
                $where_conditions[] = "(p.post_title LIKE %s OR bp.source_selection LIKE %s)";
                $query_params[] = '%' . $wpdb->esc_like($params['search']) . '%';
                $query_params[] = '%' . $wpdb->esc_like($params['search']) . '%';
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

            // Get total count - build base query without interpolation
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
            $base_count_query = "SELECT COUNT(*) FROM {$blog_posts_table} bp
                           LEFT JOIN {$wpdb->posts} p ON bp.post_id = p.ID
                           " . $where_clause;

            if (!empty($query_params)) {
                $count_query = $wpdb->prepare($base_count_query, ...$query_params); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            } else {
                $count_query = $base_count_query;
            }
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $total_count = $wpdb->get_var($count_query);
            
            // Create pagination instance
            $pagination = new ExplainerPlugin_Pagination($total_count, $params['per_page'], $params['page']);
            $pagination_args = $pagination->get_pagination_args();
            
            // Build ORDER BY clause
            $allowed_orderby = array('created_at', 'post_title', 'ai_provider', 'generation_cost');
            $orderby = in_array($params['orderby'], $allowed_orderby) ? $params['orderby'] : 'created_at';
            $order = $params['order'] === 'asc' ? 'ASC' : 'DESC';
            
            if ($orderby === 'created_at') {
                $order_clause = "bp.created_at {$order}";
            } elseif ($orderby === 'post_title') {
                $order_clause = "p.post_title {$order}";
            } else {
                $order_clause = "bp.{$orderby} {$order}";
            }
            
            // Main query - simplified without job status to avoid duplicates
            // Build base query without interpolation
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
            $base_main_query = "SELECT bp.*,
                                 p.post_title,
                                 p.post_status,
                                 p.post_date
                          FROM {$blog_posts_table} bp
                          LEFT JOIN {$wpdb->posts} p ON bp.post_id = p.ID
                          " . $where_clause . "
                          ORDER BY " . $order_clause . "
                          LIMIT %d OFFSET %d";

            $final_params = array_merge($query_params, [$pagination_args['limit'], $pagination_args['offset']]);
            $prepared_query = $wpdb->prepare($base_main_query, ...$final_params); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results($prepared_query);
            
            // Format results
            $formatted_posts = array();
            foreach ($results as $row) {
                $post_id = (int) $row->post_id;
                $post_title = $row->post_title ?: __('(Untitled)', 'ai-explainer');
                
                // Get thumbnail
                $thumbnail_url = '';
                if (has_post_thumbnail($post_id)) {
                    $thumbnail_url = get_the_post_thumbnail_url($post_id, 'thumbnail');
                }
                
                // Determine status - if post exists, it's completed
                $status = 'completed';
                if (!$post_id || !$row->post_status) {
                    $status = 'unknown';
                }
                
                // Format source selection and AI explanation for display
                $source_selection = wp_trim_words($row->source_selection, 15, '...');
                
                // Get reading level data for this selection (same as popular selections)
                $reading_level_data = $this->get_reading_level_data($row->source_selection);
                
                
                // Try to get explanation in priority order: standard, simple, expert, detailed, child
                $priority_levels = ['standard', 'simple', 'expert', 'detailed', 'child'];
                $ai_explanation = 'No explanation found';
                
                foreach ($priority_levels as $level) {
                    if (isset($reading_level_data[$level]) && !empty($reading_level_data[$level]['ai_explanation'])) {
                        $ai_explanation = wp_trim_words($reading_level_data[$level]['ai_explanation'], 20, '...');
                        break;
                    }
                }
                
                $formatted_posts[] = array(
                    'id' => $row->id,
                    'post_id' => $post_id,
                    'title' => $post_title,
                    'edit_link' => $post_id ? admin_url('post.php?post=' . $post_id . '&action=edit') : '',
                    'view_link' => $post_id ? get_permalink($post_id) : '',
                    'term' => $source_selection,
                    'explanation' => $ai_explanation,
                    'thumbnail_url' => $thumbnail_url,
                    'created_at' => $row->created_at,
                    'status' => $status,
                    'ai_provider' => $row->ai_provider,
                    'generation_cost' => $row->generation_cost,
                );
            }
            
            wp_send_json_success(array(
                'items' => $formatted_posts,
                'pagination' => $pagination->get_pagination_data(),
                'pagination_html' => $pagination->get_pagination_html('created-posts-pagination'),
            ));
            
        } catch (Exception $e) {
            if (ExplainerPlugin_Debug_Logger::is_enabled()) {
                ExplainerPlugin_Debug_Logger::error('Error fetching created posts: ' . $e->getMessage(), 'admin');
            }
            wp_send_json_error('Failed to load created posts: ' . $e->getMessage());
        }
    }
    
    /**
     * Get progress text for job status
     * 
     * @param string $status Job status
     * @param int $progress Progress percentage
     * @return string Progress text
     */
    private function get_progress_text($status, $progress) {
        switch ($status) {
            case 'pending':
                return __('Waiting to start...', 'ai-explainer');
            case 'processing':
                /* translators: %d: percentage complete */
                return sprintf(__('Processing... %d%% complete', 'ai-explainer'), $progress ?: 0);
            case 'completed':
                return __('Completed', 'ai-explainer');
            case 'failed':
                return __('Failed', 'ai-explainer');
            case 'cancelled':
                return __('Cancelled', 'ai-explainer');
            default:
                return '';
        }
    }
    
    /**
     * Get status text for display
     * 
     * @param string $status Job status
     * @return string Status text
     */
    private function get_status_text($status) {
        switch ($status) {
            case 'pending':
                return __('Pending', 'ai-explainer');
            case 'processing':
                return __('Processing', 'ai-explainer');
            case 'completed':
                return __('Completed', 'ai-explainer');
            case 'failed':
                return __('Failed', 'ai-explainer');
            case 'cancelled':
                return __('Cancelled', 'ai-explainer');
            default:
                return ucfirst($status);
        }
    }
    
    /**
     * Get completed blog post information
     *
     * @param int $job_id Job ID
     * @param int $created_by User ID who created the job
     * @return array|null Post information or null if not found
     */
    private function get_completed_blog_post_info($job_id, $created_by) {
        global $wpdb;

        // Create cache key based on job_id and created_by
        $cache_key = 'blog_post_info_' . $job_id . '_' . $created_by;
        $cache_group = 'wp_ai_explainer';

        // Check cache first
        $cached_result = wp_cache_get($cache_key, $cache_group);
        if (false !== $cached_result) {
            return $cached_result;
        }

        // Try to find the post in the blog posts table
        $blog_posts_table = $wpdb->prefix . 'ai_explainer_blog_posts';

        // Check if blog posts table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $blog_posts_table)) !== $blog_posts_table) {
            return null;
        }

        // Look for blog post created around the same time by the same user
        // Build base query without interpolation
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        $base_query = "SELECT bp.post_id, p.post_title, p.post_status
            FROM {$blog_posts_table} bp
            JOIN {$wpdb->posts} p ON bp.post_id = p.ID
            WHERE bp.created_by = %d
            ORDER BY bp.created_at DESC
            LIMIT 1";

        $post_query = $wpdb->prepare($base_query, $created_by); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $post_result = $wpdb->get_row($post_query);

        if (!$post_result) {
            // Cache null result to avoid repeated queries
            wp_cache_set($cache_key, null, $cache_group, 300);
            return null;
        }

        $post_id = (int) $post_result->post_id;
        $post_title = $post_result->post_title ?: __('(Untitled)', 'ai-explainer');

        // Get thumbnail
        $thumbnail_url = '';
        if (has_post_thumbnail($post_id)) {
            $thumbnail_url = get_the_post_thumbnail_url($post_id, 'thumbnail');
        }

        $result = array(
            'post_id' => $post_id,
            'title' => $post_title,
            'thumbnail' => $thumbnail_url,
            'edit_link' => admin_url('post.php?post=' . $post_id . '&action=edit'),
            'view_link' => get_permalink($post_id),
        );

        // Cache the result for 5 minutes
        wp_cache_set($cache_key, $result, $cache_group, 300);

        return $result;
    }
    
    /**
     * Handle job queue paginated AJAX request
     */
    public function handle_get_job_queue_paginated() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Get and validate parameters
        $params = ExplainerPlugin_Pagination::validate_pagination_params(array(
            'page' => isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1,
            'per_page' => isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 20,
            'orderby' => isset($_POST['orderby']) ? sanitize_text_field(wp_unslash($_POST['orderby'])) : 'created_at',
            'order' => isset($_POST['order']) ? sanitize_text_field(wp_unslash($_POST['order'])) : 'desc',
            'search' => isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '',
        ));

        // Get filter parameters
        $filters = array(
            'search' => isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '',
            'job_type' => isset($_POST['job_type']) ? sanitize_text_field(wp_unslash($_POST['job_type'])) : 'all',
            'status' => isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'all'
        );
        
        try {
            // Get job queue admin instance
            if (!class_exists('ExplainerPlugin_Job_Queue_Admin')) {
                wp_send_json_error('Job queue admin class not found');
            }
            
            $job_queue_admin = new ExplainerPlugin_Job_Queue_Admin();
            
            // Get total count and jobs with filters
            $total_count = $job_queue_admin->get_jobs_count($filters);
            
            // Create pagination instance
            $pagination = new ExplainerPlugin_Pagination($total_count, $params['per_page'], $params['page']);
            $pagination_args = $pagination->get_pagination_args();
            
            // Get jobs
            $jobs = $job_queue_admin->get_jobs($filters, $pagination_args['limit'], $pagination_args['offset']);
            
            // Format jobs for response
            $formatted_jobs = array();
            foreach ($jobs as $job) {
                $formatted_jobs[] = array(
                    'id' => $job['id'],
                    'widget' => $job['widget'],
                    'status' => $job['status'],
                    'created_at' => $job['created_at'],
                    'term' => $job['term'] ?? '',
                    'explanation' => $job['explanation'] ?? '',
                    'post_info' => $job['post_info'] ?? null
                );
            }
            
            wp_send_json_success(array(
                'items' => $formatted_jobs,
                'pagination' => $pagination->get_pagination_data(),
                'pagination_html' => $pagination->get_pagination_html('job-queue-pagination'),
                'total_count' => $total_count,
                'filters' => $filters
            ));
            
        } catch (Exception $e) {
            if (ExplainerPlugin_Debug_Logger::is_enabled()) {
                ExplainerPlugin_Debug_Logger::error('Error fetching job queue: ' . $e->getMessage(), 'admin');
            }
            wp_send_json_error('Failed to load job queue: ' . $e->getMessage());
        }
    }

    /**
     * Handle delete all selections AJAX request
     */
    public function handle_delete_all_selections() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        try {
            global $wpdb;

            $table_name = $wpdb->prefix . 'ai_explainer_selections';

            // Check if table exists
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) !== $table_name) {
                wp_send_json_error('Selections table does not exist');
            }

            // Get count before deletion for the response
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $count_before = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

            // Delete all records - use DELETE instead of TRUNCATE to respect foreign key constraints
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $deleted = $wpdb->query("DELETE FROM {$table_name}");

            if ($deleted === false) {
                ExplainerPlugin_Debug_Logger::error('Failed to delete all selections: ' . $wpdb->last_error, 'Database');
                wp_send_json_error('Failed to delete all explanations');
            }

            // Clear cache
            wp_cache_delete('popular_selections_count_*', 'wp_ai_explainer');
            wp_cache_flush();

            ExplainerPlugin_Debug_Logger::info("Deleted all selections. Count before: {$count_before}", 'Admin');

            wp_send_json_success(array(
                'message' => 'All explanations deleted successfully',
                'deleted_count' => $count_before,
            ));

        } catch (Exception $e) {
            ExplainerPlugin_Debug_Logger::error('Error deleting all selections: ' . $e->getMessage(), 'Database');
            wp_send_json_error('Failed to delete all explanations: ' . $e->getMessage());
        }
    }
}