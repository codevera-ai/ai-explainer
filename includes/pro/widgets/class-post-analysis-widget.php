<?php
/**
 * Post Analysis Widget for Job Queue System
 * 
 * Processes WordPress posts to extract AI explanation terms using 
 * the existing API proxy and stores results as post metadata.
 * 
 * @package WP_AI_Explainer
 * @subpackage Widgets
 * @since 2.5.0
 */

namespace WPAIExplainer\JobQueue;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post Analysis Widget class
 * 
 * Extends the abstract job queue widget to provide automated post analysis
 * functionality with term extraction and metadata storage.
 * 
 * @since 2.5.0
 */
class ExplainerPlugin_Post_Analysis_Widget extends ExplainerPlugin_Job_Queue_Widget {
    
    /**
     * Term extraction service instance
     * 
     * @var \ExplainerPlugin_Term_Extraction_Service
     */
    private $term_extraction_service;
    
    /**
     * Batch processing statistics
     * 
     * @var array
     */
    private $batch_stats = array(
        'processed_count' => 0,
        'success_count' => 0,
        'error_count' => 0,
        'total_terms_extracted' => 0,
        'batch_start_time' => null
    );
    
    /**
     * Constructor
     * 
     * @since 2.5.0
     */
    public function __construct() {
        // Initialize with job type identifier
        parent::__construct('explainer_post_analysis');
        
        // Initialize term extraction service
        if (!class_exists('\ExplainerPlugin_Term_Extraction_Service')) {
            require_once dirname(__DIR__) . '/services/class-term-extraction-service.php';
        }
        $this->term_extraction_service = new \ExplainerPlugin_Term_Extraction_Service();
        
        // Widget initialised
    }
    
    /**
     * Get widget configuration
     * 
     * Returns configuration array with widget-specific settings
     * including display information, batch processing parameters,
     * and operational settings.
     * 
     * @since 2.5.0
     * @return array Widget configuration array
     */
    public function get_config() {
        return array(
            'name' => __('Post Analysis Widget', 'ai-explainer'),
            'description' => __('Processes WordPress posts to extract AI explanation terms and stores results as post metadata.', 'ai-explainer'),
            'dashicon' => 'dashicons-analytics',
            'batch_size' => 5, // Process 5 posts per batch for API rate limiting
            'priority' => 50, // Medium priority
            'max_attempts' => 3, // Retry failed items up to 3 times
            'timeout' => 30, // 30 second timeout per batch
            'enable_progress_tracking' => true,
            'continue_on_error' => true, // Continue processing other items if one fails
            'supports_email_notifications' => true
        );
    }
    
    /**
     * Get items to be processed
     *
     * Discovers published WordPress posts and pages that haven't been
     * analysed yet and returns them as structured item data.
     *
     * @since 2.5.0
     * @return array Array of items to process
     */
    public function get_items() {
        $this->log('Starting post discovery for analysis', 'info');

        try {
            // Create cache key based on post types
            $cache_key = 'wpaie_unanalysed_posts_' . md5(serialize(array('post', 'page')));

            // Try to get from cache first
            $posts = wp_cache_get($cache_key, 'wp_ai_explainer');

            if (false === $posts) {
                // Cache miss - query for published posts that haven't been analysed
                // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query is required to identify unanalysed posts. Performance is optimised with: 1) 15-minute wp_cache caching (line 131), 2) fields=ids to retrieve only IDs, 3) no_found_rows=true to skip pagination, 4) disabled meta/term cache updates.
                $posts = get_posts(array(
                    'post_type' => array('post', 'page'),
                    'post_status' => 'publish',
                    'posts_per_page' => -1, // Get all matching posts
                    'meta_query' => array(
                        array(
                            'key' => '_wp_ai_explainer_analysed',
                            'compare' => 'NOT EXISTS'
                        )
                    ),
                    'fields' => 'ids', // Only get post IDs for efficiency
                    'no_found_rows' => true, // Skip pagination count query
                    'update_post_meta_cache' => false, // Skip meta cache update
                    'update_post_term_cache' => false // Skip term cache update
                ));
                // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

                // Cache the results for 15 minutes
                wp_cache_set($cache_key, $posts, 'wp_ai_explainer', 15 * MINUTE_IN_SECONDS);

                $this->log('Post query executed and cached', 'debug', array(
                    'cache_key' => $cache_key,
                    'post_count' => count($posts)
                ));
            } else {
                $this->log('Post query served from cache', 'debug', array(
                    'cache_key' => $cache_key,
                    'post_count' => count($posts)
                ));
            }

            if (empty($posts)) {
                $this->log('No unprocessed posts found for analysis', 'info');
                return array();
            }

            // Convert post IDs to structured item data
            $items = array();
            foreach ($posts as $post_id) {
                $post = get_post($post_id);

                // Validate post exists and has content
                if (!$post || empty($post->post_content)) {
                    $this->log("Skipping post {$post_id}: invalid or empty content", 'debug');
                    continue;
                }

                // Check if post content is too short for meaningful analysis
                $word_count = str_word_count(wp_strip_all_tags($post->post_content));
                if ($word_count < 50) {
                    $this->log("Skipping post {$post_id}: content too short ({$word_count} words)", 'debug');
                    continue;
                }

                $items[] = array(
                    'post_id' => $post_id,
                    'post_title' => $post->post_title,
                    'post_content' => $post->post_content,
                    'post_type' => $post->post_type,
                    'word_count' => $word_count
                );
            }

            $this->log(
                sprintf(
                    /* translators: %d: number of posts found */
                    __('Post discovery completed: %d posts found for analysis', 'ai-explainer'),
                    count($items)
                ),
                'info',
                array(
                    'count' => count($items),
                    'total_queried' => count($posts),
                    'filtered_out' => count($posts) - count($items)
                )
            );

            return $items;

        } catch (\Exception $e) {
            $this->log('Error during post discovery: ' . $e->getMessage(), 'error');
            return array();
        }
    }
    
    /**
     * Process a single item
     * 
     * Processes one WordPress post by extracting AI explanation terms
     * using the term extraction service and storing results as post metadata.
     * 
     * @since 2.5.0
     * @param mixed $item The item to process (post data array)
     * @return array Processing result
     * @throws \Exception On processing failure
     */
    public function process_item($item) {
        if (!is_array($item) || !isset($item['post_id'])) {
            throw new \Exception(esc_html('Invalid item data: missing post_id'));
        }
        
        $post_id = absint($item['post_id']);
        $post_title = sanitize_text_field($item['post_title'] ?? '');
        $post_content = $item['post_content'] ?? '';
        
        $this->log("Starting analysis of post {$post_id}: '{$post_title}'", 'info');
        
        $processing_start = microtime(true);
        
        try {
            // Validate post still exists and is published
            $post = get_post($post_id);
            if (!$post || $post->post_status !== 'publish') {
                throw new \Exception(esc_html("Post {$post_id} no longer exists or is not published"));
            }
            
            // Check if post was already processed (race condition protection)
            if (get_post_meta($post_id, '_wp_ai_explainer_analysed', true)) {
                $this->log("Post {$post_id} was already processed, skipping", 'info');
                return array(
                    'success' => true,
                    'already_processed' => true,
                    'post_id' => $post_id,
                    'message' => 'Post already processed'
                );
            }
            
            // Extract terms using the term extraction service
            $extraction_result = $this->term_extraction_service->extract_terms(
                $post_content,
                $post_id,
                array(
                    'min_terms' => 10,
                    'max_terms' => 50
                )
            );
            
            if (!$extraction_result['success']) {
                throw new \Exception("Term extraction failed: " . $extraction_result['error']);
            }
            
            $extracted_terms = $extraction_result['terms'];
            $term_count = count($extracted_terms);
            
            $this->log("Term extraction completed for post {$post_id}: {$term_count} terms found", 'info');
            
            // Store results as post metadata
            $this->store_analysis_results($post_id, $extracted_terms);
            
            $processing_time = microtime(true) - $processing_start;
            
            $this->log("Post analysis completed successfully for post {$post_id}", 'info', array(
                'post_id' => $post_id,
                'post_title' => $post_title,
                'term_count' => $term_count,
                'processing_time_seconds' => round($processing_time, 3),
                'extraction_stats' => $extraction_result['stats'] ?? array()
            ));
            
            return array(
                'success' => true,
                'post_id' => $post_id,
                'post_title' => $post_title,
                'term_count' => $term_count,
                'processing_time' => $processing_time,
                'extraction_stats' => $extraction_result['stats'] ?? array()
            );
            
        } catch (\Exception $e) {
            $processing_time = microtime(true) - $processing_start;
            
            // Store error information as post metadata
            $this->store_analysis_error($post_id, $e->getMessage());
            
            $this->log("Post analysis failed for post {$post_id}: " . $e->getMessage(), 'error', array(
                'post_id' => $post_id,
                'post_title' => $post_title,
                'error_message' => $e->getMessage(),
                'processing_time_seconds' => round($processing_time, 3)
            ));
            
            // Re-throw the exception to trigger retry logic
            throw $e;
        }
    }
    
    /**
     * Store analysis results as post metadata
     *
     * Stores extracted terms and analysis metadata for a post.
     *
     * @since 2.5.0
     * @param int   $post_id        Post ID
     * @param array $extracted_terms Array of extracted terms
     */
    private function store_analysis_results($post_id, $extracted_terms) {
        $term_count = count($extracted_terms);
        $analysis_timestamp = current_time('mysql');

        // Store terms as serialised array
        update_post_meta($post_id, '_wp_ai_explainer_terms', $extracted_terms);

        // Store analysis completion timestamp
        update_post_meta($post_id, '_wp_ai_explainer_analysed', $analysis_timestamp);

        // Store term count for quick reference
        update_post_meta($post_id, '_wp_ai_explainer_term_count', $term_count);

        // Clear any previous error metadata
        delete_post_meta($post_id, '_wp_ai_explainer_analysis_error');

        // Clear cache as post has now been analysed
        $cache_key = 'wpaie_unanalysed_posts_' . md5(serialize(array('post', 'page')));
        wp_cache_delete($cache_key, 'wp_ai_explainer');

        $this->log("Analysis results stored for post {$post_id}", 'debug', array(
            'post_id' => $post_id,
            'term_count' => $term_count,
            'analysis_timestamp' => $analysis_timestamp
        ));
    }
    
    /**
     * Store analysis error as post metadata
     * 
     * Stores error information when post analysis fails.
     * 
     * @since 2.5.0
     * @param int    $post_id      Post ID
     * @param string $error_message Error message
     */
    private function store_analysis_error($post_id, $error_message) {
        $error_data = array(
            'error_message' => $error_message,
            'error_timestamp' => current_time('mysql'),
            'attempt_number' => $this->get_current_attempt_number()
        );
        
        update_post_meta($post_id, '_wp_ai_explainer_analysis_error', $error_data);
        
        $this->log("Analysis error stored for post {$post_id}", 'debug', array(
            'post_id' => $post_id,
            'error_message' => $error_message,
            'attempt_number' => $error_data['attempt_number']
        ));
    }
    
    /**
     * Get current attempt number from job context
     * 
     * @since 2.5.0
     * @return int Current attempt number
     */
    private function get_current_attempt_number() {
        if ($this->current_job && isset($this->current_job['attempts'])) {
            return absint($this->current_job['attempts']);
        }
        
        return 1;
    }
    
    /**
     * Hook called before processing a batch
     * 
     * Initialises batch statistics and logging.
     * 
     * @since 2.5.0
     * @param array $items Items to be processed
     */
    public function before_batch($items) {
        // Call parent to handle permission validation
        parent::before_batch($items);
        
        $this->batch_stats = array(
            'processed_count' => 0,
            'success_count' => 0,
            'error_count' => 0,
            'total_terms_extracted' => 0,
            'batch_start_time' => microtime(true)
        );
        
        $total_items = count($items);

        /* translators: %d: number of posts to analyse */
        $this->log(
            sprintf(
                /* translators: %d: number of posts to analyse */
                __('Starting batch processing: %d posts to analyse', 'ai-explainer'),
                $total_items
            ),
            'info',
            array(
                'batch_size' => $total_items,
                'job_id' => $this->get_current_job_id()
            )
        );
        
        // Store batch metadata
        $this->update_job_meta('batch_total_items', $total_items);
        $this->update_job_meta('batch_start_time', time());
    }
    
    /**
     * Hook called after processing a batch
     * 
     * Updates progress tracking and logs batch completion statistics.
     * 
     * @since 2.5.0
     * @param array $items   Items that were processed
     * @param array $results Processing results
     */
    public function after_batch($items, $results) {
        $batch_duration = microtime(true) - $this->batch_stats['batch_start_time'];
        
        // Calculate statistics from results
        foreach ($results as $result) {
            $this->batch_stats['processed_count']++;
            
            if (isset($result['success']) && $result['success']) {
                $this->batch_stats['success_count']++;
                if (isset($result['term_count'])) {
                    $this->batch_stats['total_terms_extracted'] += $result['term_count'];
                }
            } else {
                $this->batch_stats['error_count']++;
            }
        }
        
        $success_rate = $this->batch_stats['processed_count'] > 0 
            ? round(($this->batch_stats['success_count'] / $this->batch_stats['processed_count']) * 100, 1)
            : 0;
        
        $this->log("Batch processing completed", 'info', array(
            'total_processed' => $this->batch_stats['processed_count'],
            'successful' => $this->batch_stats['success_count'],
            'errors' => $this->batch_stats['error_count'],
            'success_rate_percent' => $success_rate,
            'total_terms_extracted' => $this->batch_stats['total_terms_extracted'],
            'batch_duration_seconds' => round($batch_duration, 3),
            'average_time_per_post' => $this->batch_stats['processed_count'] > 0 
                ? round($batch_duration / $this->batch_stats['processed_count'], 3) 
                : 0
        ));
        
        // Update job metadata with batch statistics
        $this->update_job_meta('batch_completed_items', $this->batch_stats['processed_count']);
        $this->update_job_meta('batch_success_count', $this->batch_stats['success_count']);
        $this->update_job_meta('batch_error_count', $this->batch_stats['error_count']);
        $this->update_job_meta('batch_terms_extracted', $this->batch_stats['total_terms_extracted']);
        $this->update_job_meta('batch_duration', $batch_duration);
    }
    
    /**
     * Hook called when an error occurs
     * 
     * Determines retry eligibility based on error type and logs
     * detailed error information.
     * 
     * @since 2.5.0
     * @param mixed      $item  Item that caused the error
     * @param \Exception $error The error that occurred
     * @return bool True to retry, false to skip
     */
    public function on_error($item, $error) {
        $post_id = isset($item['post_id']) ? absint($item['post_id']) : 'unknown';
        $post_title = isset($item['post_title']) ? sanitize_text_field($item['post_title']) : 'unknown';
        
        $this->log("Error processing post {$post_id}: " . $error->getMessage(), 'error', array(
            'post_id' => $post_id,
            'post_title' => $post_title,
            'error_message' => $error->getMessage(),
            'error_file' => $error->getFile(),
            'error_line' => $error->getLine()
        ));
        
        // Determine retry eligibility based on error type
        $should_retry = $this->should_retry_error($error);
        
        $this->log($should_retry ? "Error is retryable for post {$post_id}" : "Error is not retryable for post {$post_id}", 'info', array(
            'post_id' => $post_id,
            'retry_decision' => $should_retry,
            'current_attempts' => $this->get_current_attempt_number()
        ));
        
        return $should_retry;
    }
    
    /**
     * Determine if an error should trigger a retry
     * 
     * @since 2.5.0
     * @param \Exception $error The error that occurred
     * @return bool True if error is retryable
     */
    private function should_retry_error($error) {
        $error_message = strtolower($error->getMessage());
        
        // Don't retry if post no longer exists or is unpublished
        if (strpos($error_message, 'no longer exists') !== false || 
            strpos($error_message, 'not published') !== false) {
            return false;
        }
        
        // Don't retry permission errors
        if (strpos($error_message, 'permission') !== false || 
            strpos($error_message, 'unauthorized') !== false) {
            return false;
        }
        
        // Don't retry if post was already processed (race condition)
        if (strpos($error_message, 'already processed') !== false) {
            return false;
        }
        
        // Retry API communication errors
        if (strpos($error_message, 'api') !== false || 
            strpos($error_message, 'network') !== false ||
            strpos($error_message, 'timeout') !== false ||
            strpos($error_message, 'connection') !== false) {
            return true;
        }
        
        // Retry term extraction failures (might be temporary AI issues)
        if (strpos($error_message, 'term extraction failed') !== false) {
            return true;
        }
        
        // Default to retry for unknown errors
        return true;
    }
    
    /**
     * Hook called when job completes successfully
     * 
     * Sends completion notifications if enabled and logs final statistics.
     * 
     * @since 2.5.0
     */
    public function on_complete() {
        // Call parent completion handler
        parent::on_complete();
        
        // Get final job statistics
        $total_processed = $this->get_job_meta('batch_completed_items', 0);
        $total_success = $this->get_job_meta('batch_success_count', 0);
        $total_errors = $this->get_job_meta('batch_error_count', 0);
        $total_terms = $this->get_job_meta('batch_terms_extracted', 0);
        
        $this->log('Post analysis job completed successfully', 'info', array(
            'total_posts_processed' => $total_processed,
            'successful_analyses' => $total_success,
            'failed_analyses' => $total_errors,
            'total_terms_extracted' => $total_terms,
            'success_rate_percent' => $total_processed > 0 ? round(($total_success / $total_processed) * 100, 1) : 0
        ));
        
        // Send completion notification if enabled
        if ($this->should_send_completion_email()) {
            $this->send_completion_email($total_processed, $total_success, $total_errors, $total_terms);
        }
        
        // Trigger WordPress action for external integrations
        do_action('explainer_post_analysis_complete', array(
            'total_processed' => $total_processed,
            'total_success' => $total_success,
            'total_errors' => $total_errors,
            'total_terms' => $total_terms
        ));
    }
    
    /**
     * Check if completion email should be sent
     * 
     * @since 2.5.0
     * @return bool True if email should be sent
     */
    private function should_send_completion_email() {
        // Check if email notifications are enabled in job queue settings
        $job_queue_settings = get_option('explainer_job_queue_settings', array());
        
        return !empty($job_queue_settings['enable_email_notifications']) && 
               !empty($job_queue_settings['completion_email_enabled']);
    }
    
    /**
     * Send completion email notification
     * 
     * Sends an email summary of the post analysis job completion
     * using WordPress wp_mail() function.
     * 
     * @since 2.5.0
     * @param int $total_processed  Total posts processed
     * @param int $total_success    Successful analyses
     * @param int $total_errors     Failed analyses  
     * @param int $total_terms      Total terms extracted
     */
    private function send_completion_email($total_processed, $total_success, $total_errors, $total_terms) {
        try {
            // Get email settings
            $job_queue_settings = get_option('explainer_job_queue_settings', array());
            $admin_email = get_option('admin_email');
            $site_name = get_bloginfo('name');
            
            // Determine recipient
            $to_email = !empty($job_queue_settings['notification_email']) 
                ? $job_queue_settings['notification_email'] 
                : $admin_email;
            
            // Calculate success rate
            $success_rate = $total_processed > 0 
                ? round(($total_success / $total_processed) * 100, 1) 
                : 0;
            
            // Prepare email content
            $subject = sprintf(
                /* translators: 1: site name, 2: number of posts processed */
                __('[%1$s] Post Analysis Job Completed - %2$d Posts Processed', 'ai-explainer'),
                $site_name,
                $total_processed
            );

            $message = sprintf(
                /* translators: 1: total posts processed, 2: successfully analysed, 3: failed analyses, 4: success rate percentage, 5: total terms extracted, 6: job type, 7: completion time */
                __("Post Analysis Job Completion Summary\n\nTotal Posts Processed: %1\$d\nSuccessfully Analysed: %2\$d\nFailed Analyses: %3\$d\nSuccess Rate: %4\$s%%\nTotal Terms Extracted: %5\$d\n\nJob Type: %6\$s\nCompletion Time: %7\$s\n\nYou can view detailed logs in your WordPress admin area.\n\nThis is an automated notification from AI Explainer plugin.", 'ai-explainer'),
                $total_processed,
                $total_success,
                $total_errors,
                $success_rate,
                $total_terms,
                $this->job_type,
                current_time('Y-m-d H:i:s')
            );
            
            // Send email
            $mail_sent = wp_mail($to_email, $subject, $message);
            
            if ($mail_sent) {
                $this->log('Completion email sent successfully', 'info', array(
                    'recipient' => $to_email,
                    'subject' => $subject
                ));
            } else {
                $this->log('Failed to send completion email', 'error', array(
                    'recipient' => $to_email,
                    'subject' => $subject
                ));
            }
            
        } catch (\Exception $e) {
            $this->log('Error sending completion email: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Enhanced logging method with context
     * 
     * @since 2.5.0
     * @param string $message Log message
     * @param string $level   Log level
     * @param array  $context Additional context data
     */
    protected function log($message, $level = 'info', $context = array()) {
        // Add widget context to all log messages
        $enhanced_context = array_merge($context, array(
            'widget_type' => 'post_analysis',
            'job_type' => $this->job_type
        ));
        
        // Call parent logging method
        parent::log($message, $level);
    }
}