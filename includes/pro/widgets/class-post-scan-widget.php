<?php
/**
 * Post Scan Widget
 * 
 * Processes AI term scanning jobs using the job queue system
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

// Ensure abstract base class is loaded
if (!class_exists('\WPAIExplainer\JobQueue\ExplainerPlugin_Job_Queue_Widget')) {
    require_once dirname(dirname(__DIR__)) . '/free/core/abstract-job-queue-widget.php';
}

/**
 * Post Scan Widget class
 * 
 * Handles background processing of AI term scanning for posts
 */
class ExplainerPlugin_Post_Scan_Widget extends \WPAIExplainer\JobQueue\ExplainerPlugin_Job_Queue_Widget {
    
    /**
     * Term extraction service instance
     * 
     * @var \ExplainerPlugin_Term_Extraction_Service
     */
    private $term_extraction_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct('ai_term_scan');
    }
    
    /**
     * Get widget configuration
     * 
     * @return array Configuration array
     */
    public function get_config() {
        return array(
            'name' => __('AI Term Scanning', 'ai-explainer'),
            'description' => __('Scans post content for terms requiring AI explanations', 'ai-explainer'),
            'batch_size' => 1, // Process one post at a time
            'priority' => 10,
            'max_attempts' => 3,
            'retry_delay' => 300, // 5 minutes between retries
            'timeout' => 60, // 1 minute per post scan
            'capability' => 'edit_posts',
            'supports_progress' => true,
            'supports_cancel' => true
        );
    }
    
    /**
     * Process a job by ID (interface compatibility with Blog Creator)
     * 
     * @param int $job_id Job ID to process
     * @return array Result array
     * @throws \Exception On processing failure
     */
    public function process_job($job_id) {
        try {
            \ExplainerPlugin_Debug_Logger::debug("Starting process_job for term scan job ID: {$job_id}", 'PostScanWidget');
            
            // Get job data from database
            global $wpdb;
            $table_name = $wpdb->prefix . 'ai_explainer_job_queue';

            $job = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}ai_explainer_job_queue` WHERE queue_id = %d", $job_id),
                ARRAY_A
            );
            
            if (!$job) {
                throw new \Exception(esc_html("Term scan job not found with ID: {$job_id}"));
            }
            
            \ExplainerPlugin_Debug_Logger::debug("Retrieved job data from database", 'PostScanWidget');
            
            // Set current job for the widget framework
            $this->current_job = $job;
            
            // Parse job data
            $job_data = maybe_unserialize($job['data']);
            if (!is_array($job_data)) {
                $job_data = array();
            }
            
            // Add job ID for progress tracking
            $job_data['job_id'] = $job_id;
            
            \ExplainerPlugin_Debug_Logger::debug("Processing term scan job data", 'PostScanWidget', array('job_data' => $job_data));
            
            // Get items and process the first one
            \ExplainerPlugin_Debug_Logger::debug("Getting items to process", 'PostScanWidget');
            $items = $this->get_items();
            if (empty($items)) {
                throw new \Exception(esc_html("No items to process for term scan job: {$job_id}"));
            }
            
            \ExplainerPlugin_Debug_Logger::debug("Found " . count($items) . " items to process", 'PostScanWidget');
            
            // Process the first (and only) item for term scan jobs
            \ExplainerPlugin_Debug_Logger::debug("Starting to process item", 'PostScanWidget');
            $result = $this->process_item($items[0]);
            
            \ExplainerPlugin_Debug_Logger::debug("Term scan job completed", 'PostScanWidget', array('result' => $result));
            
            return $result;
        } catch (\Exception $e) {
            \ExplainerPlugin_Debug_Logger::error("Term scan job failed: " . $e->getMessage(), 'PostScanWidget');
            \ExplainerPlugin_Debug_Logger::error("Stack trace: " . $e->getTraceAsString(), 'PostScanWidget');
            throw $e;
        } catch (\Error $e) {
            \ExplainerPlugin_Debug_Logger::error("PHP Error in term scan job: " . $e->getMessage(), 'PostScanWidget');
            \ExplainerPlugin_Debug_Logger::error("Stack trace: " . $e->getTraceAsString(), 'PostScanWidget');
            throw new \Exception(esc_html("PHP Error: " . $e->getMessage()));
        }
    }
    
    /**
     * Get items to process
     * 
     * For post scanning, this returns the job data itself as a single item
     * 
     * @return array Array of items to process
     */
    public function get_items() {
        if (!$this->current_job) {
            return array();
        }
        
        // Handle both serialized and JSON data formats
        $job_data = maybe_unserialize($this->current_job['data']);
        if (!is_array($job_data)) {
            // Try JSON decode if unserialize didn't work
            $job_data = json_decode($this->current_job['data'], true);
        }
        if (!is_array($job_data)) {
            $job_data = array();
        }

        // Post scanning processes one job at a time
        // The job data contains all necessary information
        $items = array(
            array(
                'job_id' => $this->current_job['queue_id'],
                'post_content' => $job_data['post_content'] ?? '',
                'options' => array(
                    'post_id' => $job_data['post_id'] ?? 0,
                    'post_title' => $job_data['post_title'] ?? '',
                    'scan_type' => $job_data['scan_type'] ?? 'ai_term_scan',
                    'created_by' => $this->current_job['created_by'] ?? get_current_user_id()
                )
            )
        );

        return $items;
    }
    
    /**
     * Process a single post scan job
     * 
     * @param array $item Item containing job data
     * @return array Result array with terms found and metadata
     * @throws \Exception On processing failure
     */
    public function process_item($item) {
        $this->log('Starting AI term scan', 'info');
        
        // Debug: Log what we received
        \ExplainerPlugin_Debug_Logger::debug('Post scan item received', 'PostScanWidget', array('item' => $item));

        // Validate input - handle both structured and flattened formats
        if (empty($item['post_content'])) {
            throw new \Exception(esc_html(__('No content provided for post scanning', 'ai-explainer')));
        }
        
        // Check for post_id in multiple possible locations
        $post_id = 0;
        if (!empty($item['options']['post_id'])) {
            $post_id = absint($item['options']['post_id']);
        } elseif (!empty($item['post_id'])) {
            $post_id = absint($item['post_id']);
        }
        
        if (empty($post_id)) {
            throw new \Exception(esc_html(__('No post ID specified', 'ai-explainer')));
        }
        
        // Verify post exists
        $post = get_post($post_id);
        if (!$post) {
            /* translators: %d: post ID */
            throw new \Exception(esc_html(sprintf(__('Post with ID %d not found', 'ai-explainer'), $post_id)));
        }
        
        // Validate API key before processing (use same logic as blog creation widget)
        $ai_provider = $item['options']['ai_provider'] ?? get_option('explainer_api_provider', 'openai');
        $this->validate_api_key($ai_provider);
        
        // Get term extraction service instance
        $term_extraction_service = $this->get_term_extraction_service();
        
        // Set user context
        $options = $item['options'] ?? array();
        $user_id = $options['created_by'] ?? $item['created_by'] ?? $this->current_job['created_by'] ?? get_current_user_id();
        if ($user_id && function_exists('wp_set_current_user')) {
            wp_set_current_user($user_id);
        }
        
        $this->log(sprintf('Scanning post ID %d for AI terms', $post_id), 'info');
        
        // Extract terms from post content
        $terms_found = $term_extraction_service->extract_terms(
            $item['post_content'],
            $post_id,
            $options
        );
        
        if (!is_array($terms_found)) {
            throw new \Exception(esc_html(__('Failed to extract terms from post content', 'ai-explainer')));
        }
        
        // Extract the actual terms array from the response structure
        $terms_array = isset($terms_found['terms']) ? $terms_found['terms'] : $terms_found;
        $actual_count = is_array($terms_array) ? count($terms_array) : 0;
        
        $this->log(sprintf('Found %d terms requiring explanation', $actual_count), 'info');
        
        // Store terms in database
        $this->store_extracted_terms($post_id, $terms_array, $options);
        
        // Update post meta
        update_post_meta($post_id, '_wp_ai_explainer_scan_completed', current_time('mysql'));
        update_post_meta($post_id, '_wp_ai_explainer_scan_terms_count', $actual_count);
        update_post_meta($post_id, '_wp_ai_explainer_scan_provider', $options['ai_provider'] ?? 'openai');
        
        // Store result metadata
        $this->update_job_meta('post_id', $post_id);
        $this->update_job_meta('terms_found', $actual_count);
        $this->update_job_meta('processing_cost', 0); // Cost tracking would need API integration
        
        return array(
            'success' => true,
            'post_id' => $post_id,
            'terms_found' => $actual_count,
            'terms' => $terms_array,
            'cost' => 0, // Cost tracking would need API integration
            'post_url' => get_permalink($post_id),
            'edit_url' => get_edit_post_link($post_id, 'raw')
        );
    }
    
    /**
     * Called when job completes successfully
     */
    public function on_complete() {
        $post_id = $this->get_job_meta('post_id');
        $terms_found = $this->get_job_meta('terms_found', 0);
        
        if ($post_id) {
            $this->log(sprintf('Post scan completed. Post ID: %d, Terms found: %d', $post_id, $terms_found), 'info');
            
            // Auto-enable terms visibility for processed posts
            if ($terms_found > 0) {
                $selection_tracker = new \ExplainerPlugin_Selection_Tracker();
                $selection_tracker->set_post_selections_enabled($post_id, true);
                $this->log(sprintf('Auto-enabled terms visibility for post ID: %d', $post_id), 'info');
                
                // Update post meta to reflect scan enabled state
                update_post_meta($post_id, '_wp_ai_explainer_scan_enabled', 'yes');
            }
            
            // Remove the scan job ID from post meta
            delete_post_meta($post_id, '_wp_ai_explainer_scan_job_id');
            
            // Send notification if enabled
            $this->send_completion_notification($post_id, $terms_found);
        }
    }
    
    /**
     * Called when job fails permanently
     */
    public function on_failure() {
        $post_id = $this->get_job_meta('post_id');
        
        $this->log('Post scan job failed after maximum attempts', 'error');
        
        // Clean up post meta
        if ($post_id) {
            delete_post_meta($post_id, '_wp_ai_explainer_scan_job_id');
            update_post_meta($post_id, '_wp_ai_explainer_scan_failed', current_time('mysql'));
        }
    }
    
    /**
     * Get term extraction service instance
     * 
     * @return \ExplainerPlugin_Term_Extraction_Service
     */
    private function get_term_extraction_service() {
        if (!$this->term_extraction_service) {
            if (!class_exists('\ExplainerPlugin_Term_Extraction_Service')) {
                require_once dirname(__DIR__) . '/services/class-term-extraction-service.php';
            }
            $this->term_extraction_service = new \ExplainerPlugin_Term_Extraction_Service();
        }
        return $this->term_extraction_service;
    }
    
    /**
     * Store extracted terms in the database
     * 
     * @param int $post_id Post ID
     * @param array $terms Array of extracted terms
     * @param array $options Processing options
     */
    private function store_extracted_terms($post_id, $terms, $options) {
        global $wpdb;
        
        $selections_table = $wpdb->prefix . 'ai_explainer_selections';
        
        // Remove any existing AI scan terms for this post
        $wpdb->delete(
            $selections_table,
            array(
                'post_id' => $post_id,
                'source' => 'ai_scan'
            ),
            array('%d', '%s')
        );
        
        // Validate and process terms
        if (!is_array($terms)) {
            \ExplainerPlugin_Debug_Logger::warning('Terms is not an array, skipping storage', 'PostScanWidget');
            return;
        }
        
        // Insert new terms
        foreach ($terms as $term) {
            \ExplainerPlugin_Debug_Logger::debug('Inserting term', 'PostScanWidget', array('term' => $term));
            
            // Handle different term data structures
            $term_text = '';
            $term_explanation = '';
            
            if (is_array($term)) {
                $term_text = $term['term'] ?? $term['text'] ?? '';
                $term_explanation = $term['explanation'] ?? $term['definition'] ?? '';
            } elseif (is_string($term)) {
                $term_text = $term;
                $term_explanation = ''; // No explanation available
            }
            
            // Skip if no term text
            if (empty($term_text)) {
                \ExplainerPlugin_Debug_Logger::warning('Skipping term with no text', 'PostScanWidget');
                continue;
            }
            
            $insert_result = $wpdb->insert(
                $selections_table,
                array(
                    'post_id' => $post_id,
                    'text_hash' => md5($term_text),
                    'selected_text' => sanitize_text_field($term_text),
                    'ai_explanation' => wp_kses_post($term_explanation),
                    'source' => 'ai_scan',
                    'enabled' => 1,
                    'selection_count' => 1,
                    'first_seen' => current_time('mysql'),
                    'last_seen' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
            );
            
            if ($insert_result === false) {
                \ExplainerPlugin_Debug_Logger::error('Failed to insert term: ' . $wpdb->last_error, 'PostScanWidget');
            } else {
                \ExplainerPlugin_Debug_Logger::info('Successfully inserted term: ' . $term_text, 'PostScanWidget');
            }
        }
        
        $this->log(sprintf('Stored %d terms in database for post %d', count($terms), $post_id), 'info');
    }
    
    /**
     * Send completion notification email
     * 
     * @param int $post_id Post ID
     * @param int $terms_found Number of terms found
     */
    private function send_completion_notification($post_id, $terms_found) {
        $notify_email = get_option('explainer_scan_notification_email');
        
        if (!$notify_email) {
            return;
        }
        
        $post_title = get_the_title($post_id);
        $edit_link = get_edit_post_link($post_id, 'raw');
        $admin_link = admin_url('admin.php?page=wp-ai-explainer-admin&tab=post-scan');

        $subject = sprintf(
            /* translators: Email subject. 1: site name, 2: post title */
            __('[%1$s] Post Scan Complete: %2$s', 'ai-explainer'),
            get_bloginfo('name'),
            $post_title
        );

        $message = sprintf(
            /* translators: 1: post title, 2: number of terms found, 3: edit post link, 4: view results link */
            __("AI term scanning has completed for a post.\n\nPost: %1\$s\nTerms Found: %2\$d\n\nEdit Post: %3\$s\nView Results: %4\$s\n\n--\nAI Explainer", 'ai-explainer'),
            $post_title,
            $terms_found,
            $edit_link,
            $admin_link
        );
        
        wp_mail($notify_email, $subject, $message);
        
        $this->log('Notification email sent', 'info');
    }
    
    /**
     * Validate API key for the specified provider
     * 
     * @since 2.4.0
     * @param string $ai_provider AI provider identifier
     * @throws \Exception If API key is invalid or not configured
     */
    private function validate_api_key($ai_provider) {
        // Map provider names to option keys
        $provider_map = array(
            'openai-gpt35' => 'explainer_openai_api_key',
            'openai-gpt4' => 'explainer_openai_api_key',
            'gpt-3.5-turbo' => 'explainer_openai_api_key',
            'gpt-4' => 'explainer_openai_api_key',
            'gpt-4-turbo' => 'explainer_openai_api_key',
            'openai' => 'explainer_openai_api_key',
            'claude-sonnet' => 'explainer_claude_api_key',
            'claude-haiku' => 'explainer_claude_api_key',
            'claude-opus' => 'explainer_claude_api_key',
            'claude-3-haiku-20240307' => 'explainer_claude_api_key',
            'claude-3-sonnet-20240229' => 'explainer_claude_api_key',
            'claude-3-opus-20240229' => 'explainer_claude_api_key',
            'claude' => 'explainer_claude_api_key'
        );
        
        // Determine which API key to check
        $option_key = null;
        if (isset($provider_map[$ai_provider])) {
            $option_key = $provider_map[$ai_provider];
        } else if (strpos($ai_provider, 'openai') !== false || strpos($ai_provider, 'gpt') !== false) {
            $option_key = 'explainer_openai_api_key';
        } else if (strpos($ai_provider, 'claude') !== false) {
            $option_key = 'explainer_claude_api_key';
        }
        
        if (!$option_key) {
            /* translators: %s: AI provider name */
            throw new \Exception(esc_html(sprintf(__('Unknown AI provider: %s', 'ai-explainer'), $ai_provider)));
        }

        // Check if encrypted API key exists
        $encrypted_api_key = get_option($option_key, '');
        if (empty($encrypted_api_key)) {
            $provider_name = strpos($option_key, 'openai') !== false ? 'OpenAI' : 'Claude';
            /* translators: %s: AI provider name */
            throw new \Exception(esc_html(sprintf(__('No API key configured for %s. Please configure your API key in the plugin settings.', 'ai-explainer'), $provider_name)));
        }
        
        // Get decrypted API key for validation
        try {
            $provider_slug = null;
            if (strpos($option_key, 'openai') !== false) {
                $provider_slug = 'openai';
            } elseif (strpos($option_key, 'claude') !== false) {
                $provider_slug = 'claude';
            }
            
            if ($provider_slug) {
                // Get API proxy instance to decrypt the key
                $api_proxy = new \ExplainerPlugin_API_Proxy();
                $decrypted_api_key = $api_proxy->get_decrypted_api_key_for_provider($provider_slug, false);
                
                if (empty($decrypted_api_key)) {
                    $provider_name = ucfirst($provider_slug);
                    /* translators: %s: AI provider name */
                    throw new \Exception(esc_html(sprintf(__('Failed to decrypt %s API key. Please reconfigure your API key in the plugin settings.', 'ai-explainer'), $provider_name)));
                }

                // Validate the decrypted API key using existing provider system
                $provider = \ExplainerPlugin_Provider_Factory::get_provider($provider_slug);
                if ($provider && !$provider->validate_api_key($decrypted_api_key)) {
                    $provider_name = ucfirst($provider_slug);
                    /* translators: %s: AI provider name */
                    throw new \Exception(esc_html(sprintf(__('Invalid %s API key format. Please check your API key in the plugin settings.', 'ai-explainer'), $provider_name)));
                }
            }
        } catch (\Exception $e) {
            // Re-throw validation errors
            throw $e;
        }
        
        $this->log(sprintf('API key validation passed for provider: %s', $ai_provider), 'info');
    }
}