<?php
/**
 * Blog Post Creator
 * 
 * Creates WordPress blog posts from selected text using AI providers
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Blog Creator class
 */
class ExplainerPlugin_Blog_Creator {
    
    /**
     * Content generator instance
     */
    private $content_generator;
    
    /**
     * Migration helper for backward compatibility
     */
    private $migration_helper = null;
    
    /**
     * Initialize the blog creator
     */
    public function __construct() {
        // Don't initialize dependencies here to avoid circular dependencies
        
        // Ensure blog posts table exists
        $this->ensure_blog_posts_table_exists();
        
        // Register AJAX handlers
        add_action('wp_ajax_explainer_create_blog_post', array($this, 'handle_create_blog_post'));
        add_action('wp_ajax_explainer_estimate_blog_cost', array($this, 'handle_estimate_cost'));
    }
    
    /**
     * Get content generator instance (lazy loaded)
     */
    private function get_content_generator() {
        if (!$this->content_generator) {
            $this->content_generator = new ExplainerPlugin_Content_Generator();
        }
        return $this->content_generator;
    }
    
    /**
     * Get migration helper for job queue operations
     */
    private function get_migration_helper() {
        if (!$this->migration_helper) {
            require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-job-queue-migration.php';
        }
        return true;
    }
    
    /**
     * Ensure the blog posts table exists
     */
    private function ensure_blog_posts_table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_explainer_blog_posts';

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;

        if (!$table_exists) {
            ExplainerPlugin_Debug_Logger::debug(' Blog posts table does not exist, creating it', 'Blog_Creator');
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id int(11) NOT NULL AUTO_INCREMENT,
                post_id bigint(20) UNSIGNED NOT NULL,
                source_selection text NOT NULL,
                ai_provider varchar(50) NOT NULL,
                post_length varchar(20) NOT NULL,
                generation_cost decimal(10,5) DEFAULT 0.00000,
                generated_image tinyint(1) DEFAULT 0,
                generated_seo tinyint(1) DEFAULT 0,
                created_by bigint(20) UNSIGNED NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_post_id (post_id),
                KEY idx_created_by (created_by),
                KEY idx_created_at (created_at),
                KEY idx_ai_provider (ai_provider)
            ) $charset_collate;";
            
            $result = dbDelta($sql);

            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
                ExplainerPlugin_Debug_Logger::debug(' Blog posts table created successfully', 'Blog_Creator');
            } else {
                ExplainerPlugin_Debug_Logger::debug(' Failed to create blog posts table', 'Blog_Creator');
            }
        } else {
            ExplainerPlugin_Debug_Logger::debug(' Blog posts table already exists', 'Blog_Creator');
        }
    }
    
    /**
     * Create job from request data
     * 
     * @param string $selection_text The selected text
     * @param array $options Generation options
     * @return string|false Job ID on success, false on failure
     */
    public function create_job_from_request($selection_text, $options) {
        explainer_log_debug('Blog Creator: Creating job from request data', array(
            'selection_length' => strlen($selection_text),
            'options_count' => count($options),
            'user_id' => get_current_user_id()
        ), 'Blog_Creator');
        
        // Add user ID to options for background processing
        $options['created_by'] = get_current_user_id();
        
        explainer_log_debug('Blog Creator: Added user ID to options', array(
            'user_id' => $options['created_by'],
            'updated_options_count' => count($options)
        ), 'Blog_Creator');
        
        // Add job to queue using new system
        explainer_log_debug('Blog Creator: Adding job to queue', array(
            'selection_length' => strlen($selection_text),
            'ai_provider' => $options['ai_provider'] ?? 'unknown'
        ), 'Blog_Creator');
        
        // Ensure migration helper is loaded
        $this->get_migration_helper();
        
        // Create job using new system
        $job_id = \WPAIExplainer\JobQueue\ExplainerPlugin_Job_Queue_Migration::create_blog_job($selection_text, $options);
        
        if ($job_id) {
            explainer_log_info('Blog Creator: Job added to queue successfully', array(
                'job_id' => $job_id,
                'user_id' => get_current_user_id(),
                'ai_provider' => $options['ai_provider'] ?? 'unknown'
            ), 'Blog_Creator');
            
            // New system handles processing automatically based on cron settings
            explainer_log_debug('Blog Creator: Job queued for processing', array(
                'job_id' => $job_id
            ), 'Blog_Creator');
        } else {
            explainer_log_error('Blog Creator: Failed to add job to queue', array(
                'user_id' => get_current_user_id(),
                'selection_length' => strlen($selection_text),
                'ai_provider' => $options['ai_provider'] ?? 'unknown'
            ), 'Blog_Creator');
        }
        
        return $job_id;
    }
    
    /**
     * Process job by ID
     * 
     * @param int $job_id Job ID from queue
     * @return array Result with post_id and metadata
     */
    public function process_job($job_id) {
        ExplainerPlugin_Debug_Logger::debug("Starting process_job for job ID: {$job_id}", 'Blog_Creator');
        
        // Get job data from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';

        $job = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE queue_id = %d", $job_id),
            ARRAY_A
        );
        
        if (!$job) {
            throw new \Exception(esc_html("Job not found with ID: {$job_id}"));
        }
        
        // Parse job data
        $job_data = maybe_unserialize($job['data']);
        if (!is_array($job_data)) {
            $job_data = array();
        }
        
        // Add job ID to data for progress tracking
        $job_data['job_id'] = $job_id;
        
        // Process the job
        return $this->process_job_data($job_data);
    }

    /**
     * Process job data for background processing
     * 
     * @param array $job_data Job data from queue
     * @return array Result with post_id and metadata
     */
    public function process_job_data($job_data) {
        ExplainerPlugin_Debug_Logger::debug('Starting process_job_data in Blog Creator', 'Blog_Creator');
        
        try {
            // Store job ID for progress updates
            $job_id = isset($job_data['job_id']) ? $job_data['job_id'] : null;
            
            // Update progress: Initialising
            $this->update_job_progress($job_id, 'initialising');
            
            // Validate job data
            if (!isset($job_data['selection_text']) || empty($job_data['selection_text'])) {
                throw new \Exception(esc_html('Missing selection_text in job data'));
            }
            
            if (!isset($job_data['options'])) {
                $job_data['options'] = array(); // Set default options
            }
            
            // Update progress: Analysing selection
            $this->update_job_progress($job_id, 'analysing_selection');
            ExplainerPlugin_Debug_Logger::debug('Job data validated, generating blog post content', 'Blog_Creator');
            
            // Update progress: Generating content
            $this->update_job_progress($job_id, 'generating_content');
            
            // Generate blog post content
            $result = $this->get_content_generator()->generate_blog_post(
                $job_data['selection_text'],
                $job_data['options']
            );
            
            if (!$result || empty($result['content'])) {
                ExplainerPlugin_Debug_Logger::error('Content generator returned empty result', 'Blog_Creator');
                throw new Exception(__('Failed to generate blog post content.', 'ai-explainer'));
            }
            
            // Update progress based on what's being generated
            if (isset($job_data['options']['generate_image']) && $job_data['options']['generate_image']) {
                $this->update_job_progress($job_id, 'processing_images');
            }
            
            if (isset($job_data['options']['generate_seo']) && $job_data['options']['generate_seo']) {
                $this->update_job_progress($job_id, 'generating_seo');
            }
            
            // Update progress: Creating post
            $this->update_job_progress($job_id, 'creating_post');
            ExplainerPlugin_Debug_Logger::debug('Blog content generated successfully, creating WordPress post', 'Blog_Creator');
            
            // Create WordPress post
            $post_id = $this->create_wordpress_post($result, $job_data['options']);
            
            if (is_wp_error($post_id)) {
                ExplainerPlugin_Debug_Logger::error('Failed to create WordPress post: ' . $post_id->get_error_message(), 'Blog_Creator');
                throw new Exception(sprintf(
                    /* translators: %s: error message from WordPress when post creation fails */
                    __('Failed to create blog post: %s', 'ai-explainer'),
                    $post_id->get_error_message()
                ));
            }
            
            ExplainerPlugin_Debug_Logger::debug("WordPress post created successfully with ID: {$post_id}", 'Blog_Creator');
            
            // Update progress: Finalising
            $this->update_job_progress($job_id, 'finalising');
            
            // Send notification email (non-blocking)
            try {
                $this->send_notification_email($post_id, $job_data['options'], $result);
                ExplainerPlugin_Debug_Logger::debug("Notification email sent for post {$post_id}", 'Blog_Creator');
            } catch (Exception $e) {
                ExplainerPlugin_Debug_Logger::error("Failed to send notification email: " . $e->getMessage(), 'Blog_Creator');
                // Don't fail the whole job for email issues
            }
            
            // Track cost and usage (non-blocking)
            try {
                $this->track_blog_creation($post_id, $job_data['options'], $result);
                ExplainerPlugin_Debug_Logger::debug("Cost tracking completed for post {$post_id}", 'Blog_Creator');
            } catch (Exception $e) {
                ExplainerPlugin_Debug_Logger::error("Failed to track costs: " . $e->getMessage(), 'Blog_Creator');
                // Don't fail the whole job for tracking issues
            }
            
            $return_data = array(
                'post_id' => $post_id,
                'edit_link' => admin_url('post.php?post=' . $post_id . '&action=edit'),
                'preview_link' => get_preview_post_link($post_id),
                'cost' => $result['cost'] ?? 0
            );
            
            ExplainerPlugin_Debug_Logger::debug("Blog creation process completed successfully for post {$post_id}", 'Blog_Creator');
            return $return_data;
            
        } catch (Exception $e) {
            ExplainerPlugin_Debug_Logger::error("Blog creation process failed: " . $e->getMessage(), 'Blog_Creator');
            throw $e; // Re-throw to be handled by caller
        }
    }
    
    /**
     * Update job progress in database
     * 
     * @param int|null $job_id Job ID
     * @param string $stage Progress stage
     */
    private function update_job_progress($job_id, $stage) {
        if (!$job_id) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';

        // Get current job data
        $current_data = $wpdb->get_var(
            $wpdb->prepare("SELECT data FROM {$table_name} WHERE queue_id = %d", $job_id)
        );
        
        if ($current_data) {
            $job_data = maybe_unserialize($current_data);
            if (!is_array($job_data)) {
                $job_data = array();
            }
        } else {
            $job_data = array();
        }
        
        // Update progress stage
        $job_data['progress_stage'] = $stage;

        // Save updated data
        $wpdb->update(
            $table_name,
            array('data' => maybe_serialize($job_data)),
            array('queue_id' => $job_id),
            array('%s'),
            array('%d')
        );
        
        ExplainerPlugin_Debug_Logger::debug("Updated job {$job_id} progress to stage: {$stage}", 'Blog_Creator');
    }
    
    /**
     * Handle AJAX request to create blog post
     */
    public function handle_create_blog_post() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Debug logging only, nonce verified immediately after on line 362
        explainer_log_info('Blog Creator: AJAX request received for blog post creation', array(
            'user_id' => get_current_user_id(),
            'user_agent' => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')),
            'request_method' => sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? 'unknown')),
            'post_data_keys' => array_keys($_POST)
        ), 'Blog_Creator');
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        // Verify nonce
        try {
            check_ajax_referer('explainer_admin_nonce', 'nonce');
            explainer_log_debug('Blog Creator: Nonce verification passed', array(
                'user_id' => get_current_user_id()
            ), 'Blog_Creator');
        } catch (Exception $e) {
            explainer_log_error('Blog Creator: Nonce verification failed', array(
                'user_id' => get_current_user_id(),
                'exception_message' => $e->getMessage(),
                'nonce_field' => isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : 'missing'
            ), 'Blog_Creator');
            wp_send_json_error(array(
                'message' => __('Security verification failed.', 'ai-explainer')
            ));
        }
        
        // Check permissions
        if (!current_user_can('publish_posts')) {
            explainer_log_warning('Blog Creator: Permission denied', array(
                'user_id' => get_current_user_id(),
                'user_roles' => wp_get_current_user()->roles ?? [],
                'required_capability' => 'publish_posts'
            ), 'Blog_Creator');
            wp_send_json_error(array(
                'message' => __('You do not have permission to create blog posts.', 'ai-explainer')
            ));
        }
        
        explainer_log_debug('Blog Creator: Permission check passed', array(
            'user_id' => get_current_user_id(),
            'user_login' => wp_get_current_user()->user_login ?? 'unknown'
        ), 'Blog_Creator');
        
        // Check for duplicate request (prevent double-submission)
        $selection_text_preview = isset($_POST['selection_text']) ? sanitize_textarea_field(wp_unslash($_POST['selection_text'])) : '';
        // Use consistent hash without time component for proper duplicate detection
        $request_hash = md5($selection_text_preview . get_current_user_id());
        $lock_key = 'explainer_blog_creation_' . $request_hash;
        
        explainer_log_debug('Blog Creator: Checking for duplicate request', array(
            'request_hash' => $request_hash,
            'lock_key' => $lock_key,
            'selection_preview' => substr($selection_text_preview, 0, 100) . '...'
        ), 'Blog_Creator');
        
        // Check if lock exists and if it's still valid
        $existing_lock = get_transient($lock_key);
        if ($existing_lock) {
            // Check if this is a stale lock (shouldn't happen with 5 second timeout, but just in case)
            explainer_log_warning('Blog Creator: Duplicate request detected', array(
                'user_id' => get_current_user_id(),
                'lock_key' => $lock_key,
                'selection_preview' => substr($selection_text_preview, 0, 50) . '...',
                'existing_lock' => $existing_lock
            ), 'Blog_Creator');
            
            // Clear the lock if it's been more than 5 seconds (failsafe)
            delete_transient($lock_key);
            
            // For now, allow the request to proceed after clearing the lock
            explainer_log_info('Blog Creator: Cleared stale lock and proceeding', array(
                'lock_key' => $lock_key
            ), 'Blog_Creator');
        }
        
        // Set new lock for 5 seconds to prevent rapid duplicate clicks
        set_transient($lock_key, true, 5);
        explainer_log_debug('Blog Creator: Request lock set', array(
            'lock_key' => $lock_key,
            'duration_seconds' => 5
        ), 'Blog_Creator');
        
        // Get and validate input
        $selection_text = isset($_POST['selection_text']) ? sanitize_textarea_field(wp_unslash($_POST['selection_text'])) : '';
        $explanation = isset($_POST['explanation']) ? sanitize_textarea_field(wp_unslash($_POST['explanation'])) : '';
        $content_prompt = isset($_POST['content_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['content_prompt'])) : '';
        $post_length = isset($_POST['post_length']) ? sanitize_text_field(wp_unslash($_POST['post_length'])) : 'medium';
        $ai_provider = isset($_POST['ai_provider']) ? sanitize_text_field(wp_unslash($_POST['ai_provider'])) : 'openai-gpt35';
        $image_size = isset($_POST['image_size']) ? sanitize_text_field(wp_unslash($_POST['image_size'])) : 'none';
        $generate_image = $image_size !== 'none';
        $generate_seo = isset($_POST['generate_seo']) && sanitize_text_field(wp_unslash($_POST['generate_seo'])) === '1';
        $selection_id = isset($_POST['selection_id']) && is_numeric($_POST['selection_id']) ? absint(wp_unslash($_POST['selection_id'])) : null;
        
        explainer_log_debug('Blog Creator: Input values extracted and sanitized', array(
            'selection_text_length' => strlen($selection_text),
            'content_prompt_length' => strlen($content_prompt),
            'post_length' => $post_length,
            'ai_provider' => $ai_provider,
            'image_size' => $image_size,
            'generate_image' => $generate_image,
            'generate_seo' => $generate_seo,
            'selection_preview' => substr($selection_text, 0, 100) . '...'
        ), 'Blog_Creator');
        
        // Validate inputs
        $validation_errors = array();
        
        if (empty($selection_text)) {
            $validation_errors[] = 'Selection text is empty';
        }
        
        // Prevent error messages from being submitted as blog content
        $error_message_patterns = array(
            'Failed to get AI response',
            'No explanation available', 
            'API key not configured',
            'Error:',
            'Exception:',
            'Fatal error',
            'Warning:',
            'Notice:'
        );
        
        foreach ($error_message_patterns as $pattern) {
            if (stripos($selection_text, $pattern) !== false) {
                $validation_errors[] = 'Cannot create blog post from error messages or system notifications';
                break;
            }
        }
        
        if (strlen($selection_text) > 2000) {
            $validation_errors[] = 'Selection text too long (maximum 2000 characters)';
        }
        
        if (!in_array($post_length, array('short', 'medium', 'long', 'detailed'))) {
            $validation_errors[] = 'Invalid post length specified';
        }
        
        // Get all valid provider identifiers from the system (matching data attributes in admin)
        $valid_providers = array(
            // OpenAI models
            'gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo',
            // Claude models  
            'claude-3-haiku-20240307', 'claude-3-sonnet-20240229', 'claude-3-opus-20240229',
            // OpenRouter models
            'qwen/qwen-3-coder-480b:free', 'google/gemini-2.5-flash-lite', 'anthropic/claude-3.7-sonnet',
            'openai/gpt-4.1-mini', 'qwen/qwen-3-235b', 'qwen/qwen-2.5-72b-instruct',
            'mistralai/mistral-large-2407', 'z-ai/glm-4.5', 'z-ai/glm-4.5-air',
            'meta-llama/llama-3.2-3b-instruct:free',
            // Gemini models
            'gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-2.0-flash-exp',
            // Legacy simplified provider names for backwards compatibility
            'openai-gpt35', 'openai-gpt4', 'claude-sonnet', 'claude-haiku', 'claude-opus',
            'openai', 'claude', 'openrouter', 'gemini'
        );
        
        if (!in_array($ai_provider, $valid_providers)) {
            $validation_errors[] = 'Invalid AI provider specified: ' . $ai_provider;
        }
        
        if (!empty($validation_errors)) {
            explainer_log_warning('Blog Creator: Input validation failed', array(
                'user_id' => get_current_user_id(),
                'validation_errors' => $validation_errors,
                'selection_length' => strlen($selection_text),
                'post_length' => $post_length,
                'ai_provider' => $ai_provider
            ), 'Blog_Creator');

            wp_send_json_error(array(
                'message' => sprintf(
                    /* translators: %s: comma-separated list of validation errors (e.g. "Selection text is empty, Invalid AI provider") */
                    __('Input validation failed: %s', 'ai-explainer'),
                    implode(', ', $validation_errors)
                )
            ));
        }
        
        explainer_log_debug('Blog Creator: Input validation passed', array(
            'selection_text_length' => strlen($selection_text),
            'ai_provider' => $ai_provider,
            'post_length' => $post_length
        ), 'Blog_Creator');
        
        if (empty($content_prompt)) {
            ExplainerPlugin_Debug_Logger::debug(' Validation failed: Content prompt is empty', 'Blog_Creator');
            wp_send_json_error(array(
                'message' => __('Content prompt is required.', 'ai-explainer')
            ));
        }
        
        // Validate post length
        if (!in_array($post_length, array('short', 'medium', 'long'))) {
            ExplainerPlugin_Debug_Logger::debug(' Invalid post length "' . $post_length . '", defaulting to medium');
            $post_length = 'medium';
        }
        
        // Build options array
        $options = array(
            'selection_text' => $selection_text,
            'explanation' => $explanation,
            'prompt' => $content_prompt,
            'length' => $post_length,
            'ai_provider' => $ai_provider,
            'generate_image' => $generate_image,
            'image_size' => $image_size,
            'generate_seo' => $generate_seo,
            'selection_id' => $selection_id
        );
        
        explainer_log_debug('Blog Creator: Options array built', array(
            'options_keys' => array_keys($options),
            'ai_provider' => $options['ai_provider'],
            'length' => $options['length'],
            'generate_image' => $options['generate_image'],
            'generate_seo' => $options['generate_seo']
        ), 'Blog_Creator');
        
        // Always use background processing (managed by cron)
        explainer_log_info('Blog Creator: Using background processing mode', array(
            'user_id' => get_current_user_id(),
            'ai_provider' => $ai_provider,
            'selection_length' => strlen($selection_text)
        ), 'Blog_Creator');
        
        try {
            // Queue the job for background processing
            ExplainerPlugin_Debug_Logger::debug('Blog Creator: Attempting to queue job for background processing', array(), 'Blog_Creator');
            
            $job_id = $this->create_job_from_request($selection_text, $options);
            
            if (!$job_id) {
                explainer_log_error('Blog Creator: Failed to queue job for background processing', array(
                    'user_id' => get_current_user_id(),
                    'selection_length' => strlen($selection_text),
                    'ai_provider' => $ai_provider
                ), 'Blog_Creator');
                
                // Clear the request lock on error
                delete_transient($lock_key);
                
                wp_send_json_error(array(
                    'message' => __('Failed to queue blog post creation. Please try again.', 'ai-explainer')
                ));
                return; // Exit early
            }
            
            explainer_log_info('Blog Creator: Job queued successfully for background processing', array(
                'job_id' => $job_id,
                'user_id' => get_current_user_id(),
                'ai_provider' => $ai_provider,
                'selection_preview' => substr($selection_text, 0, 100) . '...'
            ), 'Blog_Creator');
            
            // Clear the request lock on success
            delete_transient($lock_key);
            explainer_log_debug('Blog Creator: Request lock cleared after successful queueing', array(
                'lock_key' => $lock_key,
                'job_id' => $job_id
            ), 'Blog_Creator');
            
            $cron_enabled = get_option('explainer_enable_cron', false);
            $message = $cron_enabled 
                ? __('Blog post queued successfully! It will be processed automatically.', 'ai-explainer')
                : __('Blog post queued successfully! Click "Run Job" to process it manually.', 'ai-explainer');
            
            wp_send_json_success(array(
                'message' => $message,
                'job_id' => $job_id,
                'queued' => true,
                'cron_enabled' => $cron_enabled
            ));
            return; // Exit after success
            
        } catch (Exception $e) {
            ExplainerPlugin_Debug_Logger::debug(' Exception caught in background queuing: ' . $e->getMessage());

            // Clear the request lock on error
            delete_transient($lock_key);
            ExplainerPlugin_Debug_Logger::debug(' Request lock cleared due to error: ' . $lock_key);

            wp_send_json_error(array(
                'message' => sprintf(
                    /* translators: %s: detailed error message explaining why the blog post failed to queue */
                    __('Error queuing blog post: %s', 'ai-explainer'),
                    $e->getMessage()
                )
            ));
            return; // Exit after error
        }
    }
    
    /**
     * Handle AJAX request to estimate blog post cost
     */
    public function handle_estimate_cost() {
        // Verify nonce
        check_ajax_referer('explainer_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Permission denied.', 'ai-explainer')
            ));
        }
        
        // Get input parameters
        $post_length = isset($_POST['post_length']) ? sanitize_text_field(wp_unslash($_POST['post_length'])) : 'medium';
        $ai_provider = isset($_POST['ai_provider']) ? sanitize_text_field(wp_unslash($_POST['ai_provider'])) : 'openai-gpt35';
        $image_size = isset($_POST['image_size']) ? sanitize_text_field(wp_unslash($_POST['image_size'])) : 'none';
        $generate_image = $image_size !== 'none';
        $generate_seo = isset($_POST['generate_seo']) && sanitize_text_field(wp_unslash($_POST['generate_seo'])) === '1';
        $prompt_text = isset($_POST['prompt_text']) ? sanitize_textarea_field(wp_unslash($_POST['prompt_text'])) : '';
        
        // Build options for cost estimation
        $options = array(
            'length' => $post_length,
            'ai_provider' => $ai_provider,
            'generate_image' => $generate_image,
            'image_size' => $image_size,
            'generate_seo' => $generate_seo,
            'prompt' => $prompt_text
        );
        
        try {
            $cost_calculator = new ExplainerPlugin_Blog_Cost_Calculator();
            $cost_breakdown = $cost_calculator->estimate_cost($options);
            
            wp_send_json_success(array(
                'total_cost' => $cost_breakdown['total'],
                'content_cost' => $cost_breakdown['content'],
                'image_cost' => $cost_breakdown['image'],
                'seo_cost' => $cost_breakdown['seo'],
                'breakdown' => $cost_breakdown
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(
                    /* translators: %s: technical error message explaining why cost estimation failed */
                    __('Error estimating cost: %s', 'ai-explainer'),
                    $e->getMessage()
                )
            ));
        }
    }
    
    /**
     * Create WordPress post from generated content
     * 
     * @param array $content_result Generated content result
     * @param array $options Generation options
     * @return int|WP_Error Post ID on success, WP_Error on failure
     */
    private function create_wordpress_post($content_result, $options) {
        ExplainerPlugin_Debug_Logger::debug(' Post Creator: Starting WordPress post creation', 'Blog_Creator');
        ExplainerPlugin_Debug_Logger::debug(' Post Creator: Content result keys: ' . implode(', ', array_keys($content_result)));
        
        // Extract title from content and remove it from content body
        ExplainerPlugin_Debug_Logger::debug(' Post Creator: Extracting title from content', 'Blog_Creator');
        $title = $this->extract_title_from_content($content_result['content']);
        $content_body = $content_result['content'];
        
        if (!empty($title)) {
            ExplainerPlugin_Debug_Logger::debug(' Post Creator: Title extracted: ' . $title);
            // Remove the title from the content body (first line)
            $content_lines = explode("\n", $content_body, 2);
            if (count($content_lines) > 1) {
                $content_body = trim($content_lines[1]);
                ExplainerPlugin_Debug_Logger::debug(' Post Creator: Title removed from content body', 'Blog_Creator');
            }
        } else {
            $title = sprintf(
                /* translators: %s: first 50 characters of the selected text that was used to generate the blog post */
                __('AI Generated Post: %s', 'ai-explainer'),
                substr($options['selection_text'], 0, 50) . '...'
            );
            ExplainerPlugin_Debug_Logger::debug(' Post Creator: No title found, using fallback: ' . $title);
        }
        
        // Prepare post data
        ExplainerPlugin_Debug_Logger::debug(' Post Creator: Preparing post data', 'Blog_Creator');
        $post_data = array(
            'post_title' => $title,
            'post_content' => $content_body,
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
            'post_type' => 'post',
            'post_excerpt' => $this->generate_excerpt($content_body),
            'meta_input' => array(
                '_explainer_source_selection' => $options['selection_text'],
                '_explainer_generation_options' => $options,
                '_explainer_ai_provider' => $options['ai_provider'],
                '_explainer_generation_cost' => $content_result['cost'] ?? 0,
                '_explainer_generation_date' => current_time('mysql')
            )
        );
        
        ExplainerPlugin_Debug_Logger::debug(' Post Creator: Post data prepared:', 'Blog_Creator');
        ExplainerPlugin_Debug_Logger::debug(' Post Creator: - Title: ' . $title);
        ExplainerPlugin_Debug_Logger::debug(' Post Creator: - Content body length: ' . strlen($content_body));
        ExplainerPlugin_Debug_Logger::debug(' Post Creator: - Status: draft', 'Blog_Creator');
        ExplainerPlugin_Debug_Logger::debug(' Post Creator: - Author: ' . get_current_user_id());
        ExplainerPlugin_Debug_Logger::debug(' Post Creator: - Meta fields: ' . count($post_data['meta_input']));
        
        // Create the post
        ExplainerPlugin_Debug_Logger::debug(' Post Creator: Calling wp_insert_post', 'Blog_Creator');
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            ExplainerPlugin_Debug_Logger::debug(' Post Creator: wp_insert_post failed: ' . $post_id->get_error_message());
            return $post_id;
        }
        
        ExplainerPlugin_Debug_Logger::debug(' Post Creator: Post created successfully with ID: ' . $post_id);
        
        // Set featured image if generated
        ExplainerPlugin_Debug_Logger::debug(' Post Creator: Checking for featured image in content result', 'Blog_Creator');
        ExplainerPlugin_Debug_Logger::debug(' Post Creator: Content result keys: ' . implode(', ', array_keys($content_result)));
        ExplainerPlugin_Debug_Logger::debug('Post Creator: Featured image ID value', 'Blog_Creator', array('featured_image_id' => $content_result['featured_image_id'] ?? 'NOT_SET'));
        
        if (isset($content_result['featured_image_id']) && $content_result['featured_image_id']) {
            ExplainerPlugin_Debug_Logger::debug(' Post Creator: Setting featured image: ' . $content_result['featured_image_id']);
            $image_result = set_post_thumbnail($post_id, $content_result['featured_image_id']);
            ExplainerPlugin_Debug_Logger::debug(' Post Creator: Featured image set result: ' . ($image_result ? 'success' : 'failed'));
            
            // Additional verification
            $verify_thumbnail = get_post_thumbnail_id($post_id);
            ExplainerPlugin_Debug_Logger::debug(' Post Creator: Verified thumbnail ID after setting: ' . $verify_thumbnail);
        } else {
            ExplainerPlugin_Debug_Logger::debug(' Post Creator: No featured image to set - either not isset or falsy value', 'Blog_Creator');
        }
        
        // Add SEO metadata if generated
        if (isset($content_result['seo_data']) && is_array($content_result['seo_data'])) {
            ExplainerPlugin_Debug_Logger::debug(' Post Creator: Adding SEO metadata, ' . count($content_result['seo_data']) . ' fields');
            foreach ($content_result['seo_data'] as $key => $value) {
                $meta_result = update_post_meta($post_id, $key, $value);
                ExplainerPlugin_Debug_Logger::debug(' Post Creator: SEO meta ' . $key . ': ' . ($meta_result ? 'success' : 'failed'));
            }
        } else {
            ExplainerPlugin_Debug_Logger::debug(' Post Creator: No SEO metadata to add', 'Blog_Creator');
        }
        
        // Add to default category if none specified
        if (!has_category('', $post_id)) {
            $default_category = get_option('default_category', 1);
            ExplainerPlugin_Debug_Logger::debug(' Post Creator: Adding to default category: ' . $default_category);
            $category_result = wp_set_post_categories($post_id, array($default_category));
            ExplainerPlugin_Debug_Logger::debug(' Post Creator: Category assignment result: ' . ($category_result ? 'success' : 'failed'));
        } else {
            ExplainerPlugin_Debug_Logger::debug(' Post Creator: Post already has categories assigned', 'Blog_Creator');
        }
        
        ExplainerPlugin_Debug_Logger::debug(' Post Creator: WordPress post creation completed successfully', 'Blog_Creator');
        return $post_id;
    }
    
    /**
     * Extract title from generated content
     * 
     * @param string $content Generated content
     * @return string Extracted title or empty string
     */
    private function extract_title_from_content($content) {
        ExplainerPlugin_Debug_Logger::debug(' Post Creator: Extracting title from content (first 200 chars): ' . substr($content, 0, 200));
        
        // Clean up content first
        $content = trim($content);
        
        // Remove any HTML tags for title extraction
        $plain_content = wp_strip_all_tags($content);
        
        // Look for various title patterns (AI should put title on first line)
        $patterns = array(
            '/^(.+)$/m',               // First line as title (most common for AI)
            '/^#\s+(.+)$/m',           // Markdown H1
            '/^(.+)\n[=]{3,}$/m',      // Underlined title
            '/^(.+)\n[-]{3,}$/m',      // Underlined subtitle
            '/^(.{10,100})[.!?]\s*\n/m' // First sentence as title (extended length)
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $plain_content, $matches)) {
                $title = trim($matches[1]);
                
                // Clean up the title
                $title = preg_replace('/^(Title:\s*|Blog Post:\s*|Article:\s*)/i', '', $title);
                $title = trim($title);
                
                // Check if it's a reasonable title length and doesn't contain line breaks
                if (strlen($title) >= 10 && strlen($title) <= 150 && !preg_match('/\n/', $title)) {
                    ExplainerPlugin_Debug_Logger::debug(' Post Creator: Title extracted successfully: ' . $title);
                    return $title;
                }
            }
        }
        
        ExplainerPlugin_Debug_Logger::debug(' Post Creator: No suitable title found in content', 'Blog_Creator');
        return '';
    }
    
    /**
     * Generate excerpt from content
     * 
     * @param string $content Full content
     * @return string Generated excerpt
     */
    private function generate_excerpt($content) {
        // Remove HTML and markdown
        $plain_text = wp_strip_all_tags($content);
        $plain_text = preg_replace('/[#*_`\-=]/u', '', $plain_text);
        
        // Get first paragraph or 155 characters
        $paragraphs = explode("\n\n", $plain_text);
        $first_paragraph = trim($paragraphs[0]);
        
        if (strlen($first_paragraph) <= 155) {
            return $first_paragraph;
        }
        
        return substr($first_paragraph, 0, 152) . '...';
    }
    
    /**
     * Send notification email to user
     * 
     * @param int $post_id Created post ID
     * @param array $options Generation options
     * @param array $result Generation result
     */
    private function send_notification_email($post_id, $options, $result) {
        // Check if notifications are enabled
        if (!get_option('explainer_blog_notifications_enabled', true)) {
            return;
        }
        
        $user = wp_get_current_user();
        $post = get_post($post_id);
        
        if (!$user || !$post) {
            return;
        }
        
        // Prepare email content
        $subject = sprintf(
            /* translators: %s: name of the WordPress site sending the notification */
            __('[%s] Your AI-generated blog post is ready', 'ai-explainer'),
            get_bloginfo('name')
        );

        $message = sprintf(
            /* translators: 1: user's display name, 2: title of the generated post, 3: excerpt from selected text (max 100 chars), 4: generation cost in pounds, 5: URL to edit the post, 6: URL to preview the post */
            __('Hi %1$s,

Your AI-generated blog post is ready!

Title: %2$s
Source Selection: "%3$s"
Status: Draft
Cost: £%4$s

Edit your post: %5$s
Preview: %6$s

Happy writing!
- AI Explainer', 'ai-explainer'),
            $user->display_name,
            $post->post_title,
            substr($options['selection_text'], 0, 100) . (strlen($options['selection_text']) > 100 ? '...' : ''),
            number_format($result['cost'] ?? 0, 3),
            admin_url('post.php?post=' . $post_id . '&action=edit'),
            get_preview_post_link($post_id)
        );
        
        // Send email
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Track blog creation for analytics
     * 
     * @param int $post_id Created post ID
     * @param array $options Generation options
     * @param array $result Generation result
     */
    private function track_blog_creation($post_id, $options, $result) {
        global $wpdb;
        
        // Store in blog creation tracking table (if it exists)
        $table_name = $wpdb->prefix . 'ai_explainer_blog_posts';

        // Check if table exists before attempting insert
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        
        if ($table_exists) {
            ExplainerPlugin_Debug_Logger::debug(' Inserting blog creation record into database', 'Blog_Creator');
            $blog_data = array(
                'post_id' => $post_id,
                'source_selection' => $options['selection_text'],
                'ai_provider' => $options['ai_provider'],
                'post_length' => $options['length'],
                'generation_cost' => $result['cost'] ?? 0,
                'generated_image' => $options['generate_image'] ? 1 : 0,
                'generated_seo' => $options['generate_seo'] ? 1 : 0,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            );
            
            $insert_result = $wpdb->insert(
                $table_name,
                $blog_data,
                array('%d', '%s', '%s', '%s', '%f', '%d', '%d', '%d', '%s')
            );
            
            if ($insert_result === false) {
                ExplainerPlugin_Debug_Logger::debug(' Failed to insert blog creation record: ' . $wpdb->last_error);
            } else {
                // Fire webhook after successful blog creation tracking
                $insert_id = $wpdb->insert_id;
                do_action('wp_ai_explainer_content_after_write',
                    'content_generator',
                    $insert_id,
                    'created',
                    null, // No previous state for new records
                    array_merge($blog_data, array('id' => $insert_id)),
                    array(), // No changed columns for new records
                    get_current_user_id()
                );
                
                ExplainerPlugin_Debug_Logger::debug(' Blog creation record inserted successfully', 'Blog_Creator');
            }
        } else {
            ExplainerPlugin_Debug_Logger::debug(' Blog creation tracking table does not exist, skipping database insert', 'Blog_Creator');
        }
        
        // Update selection tracking if class and method exist
        if (class_exists('ExplainerPlugin_Selection_Tracker')) {
            $selection_tracker = new ExplainerPlugin_Selection_Tracker();
            if (method_exists($selection_tracker, 'track_blog_creation')) {
                ExplainerPlugin_Debug_Logger::debug(' Updating selection tracking', 'Blog_Creator');
                $selection_tracker->track_blog_creation($options['selection_text']);
            } else {
                ExplainerPlugin_Debug_Logger::debug(' track_blog_creation method does not exist in Selection_Tracker', 'Blog_Creator');
            }
        } else {
            ExplainerPlugin_Debug_Logger::debug(' ExplainerPlugin_Selection_Tracker class does not exist', 'Blog_Creator');
        }
    }
    
    /**
     * Get blog creation statistics
     *
     * @return array Statistics data
     */
    public function get_blog_creation_stats() {
        global $wpdb;

        // Try to get cached stats first
        $cache_key = 'wpaie_blog_creation_stats';
        $cached_stats = wp_cache_get($cache_key, 'wpaie_blog_stats');

        if (false !== $cached_stats) {
            return $cached_stats;
        }

        $table_name = $wpdb->prefix . 'ai_explainer_blog_posts';

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;

        if (!$table_exists) {
            $default_stats = array(
                'total_posts' => 0,
                'total_cost' => 0,
                'posts_this_month' => 0,
                'cost_this_month' => 0
            );
            // Cache the default stats for 5 minutes
            wp_cache_set($cache_key, $default_stats, 'wpaie_blog_stats', 300);
            return $default_stats;
        }

        // Get statistics
        $stats = array();

        // Total posts and cost
        $total_stats = $wpdb->get_row(
            $wpdb->prepare("SELECT COUNT(*) as total_posts, SUM(generation_cost) as total_cost FROM {$table_name}")
        );

        // This month stats
        $month_stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(*) as posts_this_month, SUM(generation_cost) as cost_this_month
                 FROM {$table_name}
                 WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
                 AND YEAR(created_at) = YEAR(CURRENT_DATE())"
            )
        );

        $stats = array(
            'total_posts' => (int)($total_stats->total_posts ?? 0),
            'total_cost' => (float)($total_stats->total_cost ?? 0),
            'posts_this_month' => (int)($month_stats->posts_this_month ?? 0),
            'cost_this_month' => (float)($month_stats->cost_this_month ?? 0)
        );

        // Cache stats for 5 minutes
        wp_cache_set($cache_key, $stats, 'wpaie_blog_stats', 300);

        return $stats;
    }
}

/**
 * Blog Cost Calculator class
 */
class ExplainerPlugin_Blog_Cost_Calculator {
    
    /**
     * Pricing rates per 1000 tokens (in GBP)
     */
    private $pricing_rates = array(
        'openai-gpt35' => array('input' => 0.0012, 'output' => 0.0016),  // $1.50/$2.00 per 1M tokens converted to GBP per 1K
        'openai-gpt4' => array('input' => 0.024, 'output' => 0.048),     // $30/$60 per 1M tokens converted to GBP per 1K
        'claude-haiku' => array('input' => 0.0002, 'output' => 0.001),   // $0.25/$1.25 per 1M tokens converted to GBP per 1K
        'claude-sonnet' => array('input' => 0.0024, 'output' => 0.012)   // $3/$15 per 1M tokens converted to GBP per 1K
    );
    
    /**
     * Fixed costs (in GBP)
     */
    private $fixed_costs = array(
        'image_generation' => 0.04,
        'seo_generation' => 0.02
    );
    
    /**
     * Estimate cost for blog post generation
     * 
     * @param array $options Generation options
     * @return array Cost breakdown
     */
    public function estimate_cost($options) {
        $costs = array(
            'content' => 0,
            'image' => 0,
            'seo' => 0,
            'total' => 0
        );
        
        // Content generation cost
        $estimated_tokens = $this->estimate_tokens($options['length'], $options['prompt'] ?? '');
        $provider_rate = $this->pricing_rates[$options['ai_provider']] ?? $this->pricing_rates['openai-gpt35'];
        
        // Calculate cost (rates are per 1000 tokens, so divide by 1000)
        $costs['content'] = (($estimated_tokens['input'] / 1000) * $provider_rate['input']) + 
                           (($estimated_tokens['output'] / 1000) * $provider_rate['output']);
        
        // Image generation cost
        if ($options['generate_image']) {
            $costs['image'] = $this->fixed_costs['image_generation'];
        }
        
        // SEO metadata cost
        if ($options['generate_seo']) {
            $costs['seo'] = $this->fixed_costs['seo_generation'];
        }
        
        // Calculate total
        $costs['total'] = $costs['content'] + $costs['image'] + $costs['seo'];
        
        // Round all values to 3 decimal places
        foreach ($costs as $key => $value) {
            $costs[$key] = round($value, 3);
        }
        
        return $costs;
    }
    
    /**
     * Estimate tokens for content generation
     * 
     * @param string $length Post length (short, medium, long)
     * @param string $prompt Custom prompt
     * @return array Token estimates
     */
    private function estimate_tokens($length, $prompt) {
        // Base token estimates for different lengths
        $base_tokens = array(
            'short' => array('input' => 200, 'output' => 400),   // 500 words
            'medium' => array('input' => 300, 'output' => 750),  // 1000 words
            'long' => array('input' => 400, 'output' => 1500)    // 2000 words
        );
        
        $tokens = $base_tokens[$length] ?? $base_tokens['medium'];
        
        // Add tokens for custom prompt length
        $prompt_tokens = ceil(strlen($prompt) / 4); // Rough estimate: 4 chars per token
        $tokens['input'] += $prompt_tokens;
        
        return $tokens;
    }
}