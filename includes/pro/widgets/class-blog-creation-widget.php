<?php
/**
 * Blog Creation Widget
 * 
 * Processes blog post creation jobs using the job queue system
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
 * Blog Creation Widget class
 * 
 * Handles background processing of blog post creation from text selections
 */
class ExplainerPlugin_Blog_Creation_Widget extends \WPAIExplainer\JobQueue\ExplainerPlugin_Job_Queue_Widget {
    
    /**
     * Content generator instance
     * 
     * @var \ExplainerPlugin_Content_Generator
     */
    private $content_generator;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct('blog_creation');
        
        // Ensure blog posts tracking table exists
        $this->ensure_blog_posts_table();
    }
    
    /**
     * Get widget configuration
     * 
     * @return array Configuration array
     */
    public function get_config() {
        return array(
            'name' => __('Blog Post Creation', 'ai-explainer'),
            'description' => __('Creates blog posts from selected text using AI providers', 'ai-explainer'),
            'batch_size' => 1, // Process one blog post at a time
            'priority' => 10,
            'max_attempts' => 3,
            'retry_delay' => 300, // 5 minutes between retries
            'timeout' => 120, // 2 minutes per blog post
            'capability' => 'edit_posts',
            'supports_progress' => true,
            'supports_cancel' => true
        );
    }
    
    /**
     * Get items to process
     * 
     * For blog creation, this returns the job data itself as a single item
     * 
     * @return array Array of items to process
     */
    public function get_items() {
        if (!$this->current_job) {
            return array();
        }
        
        // Handle both old format (data column) and new format (separate columns)
        $selection_text = '';
        $options = array();
        $explanation_id = $this->current_job['explanation_id'] ?? null;
        
        // Try to fetch explanation from database by ID if available
        if ($explanation_id) {
            global $wpdb;
            $selections_table = $wpdb->prefix . 'ai_explainer_selections';

            // Check cache first
            $cache_key = 'explanation_' . $explanation_id;
            $cache_group = 'wp_ai_explainer';
            $explanation_data = wp_cache_get($cache_key, $cache_group);

            if (false === $explanation_data) {
                $explanation_data = $wpdb->get_row($wpdb->prepare(
                    "SELECT selected_text, ai_explanation FROM {$wpdb->prefix}ai_explainer_selections WHERE id = %d",
                    $explanation_id
                ));

                // Cache for 1 hour
                if ($explanation_data) {
                    wp_cache_set($cache_key, $explanation_data, $cache_group, HOUR_IN_SECONDS);
                }
            }
            
            if ($explanation_data) {
                $selection_text = $explanation_data->selected_text;
                // Get options from job data
                if (!empty($this->current_job['options'])) {
                    $options = maybe_unserialize($this->current_job['options']) ?: array();
                }
                // Ensure explanation is available in options
                if (!empty($explanation_data->ai_explanation)) {
                    $options['explanation'] = $explanation_data->ai_explanation;
                }
            }
        }
        
        // Fallback to stored data if explanation_id lookup failed
        if (empty($selection_text)) {
            // Try new format first (separate columns) for better performance
            if (!empty($this->current_job['selection_text']) && !empty($this->current_job['options'])) {
                // New format: data is in separate columns
                $selection_text = $this->current_job['selection_text'];
                $options = maybe_unserialize($this->current_job['options']) ?: array();
            } elseif (!empty($this->current_job['data'])) {
                // Old format: data is serialized in 'data' column (backward compatibility)
                $job_data = maybe_unserialize($this->current_job['data']);
                if (is_array($job_data)) {
                    $selection_text = $job_data['selection_text'] ?? '';
                    $options = $job_data['options'] ?? array();
                }
            }
        }
        
        // Blog creation processes one job at a time
        // The job data contains all necessary information
        return array(
            array(
                'job_id' => $this->current_job['queue_id'],
                'selection_text' => $selection_text,
                'options' => $options
            )
        );
    }
    
    /**
     * Process a single blog creation job
     * 
     * @param array $item Item containing job data
     * @return array Result array with post_id and metadata
     * @throws \Exception On processing failure
     */
    public function process_item($item) {
        $this->log('Starting blog post creation', 'info');
        
        // Report initial progress - this ensures job shows as "processing" before validation
        $this->update_progress_with_message(10, 100, __('Validating configuration...', 'ai-explainer'));
        
        // Validate input
        if (empty($item['selection_text'])) {
            throw new \Exception(esc_html(__('No text provided for blog post creation', 'ai-explainer')));
        }
        
        if (empty($item['options']['ai_provider'])) {
            throw new \Exception(esc_html(__('No AI provider specified', 'ai-explainer')));
        }
        
        // Validate API key before processing starts (after progress update)
        $this->validate_api_key($item['options']['ai_provider']);
        
        // Update progress to show we're connecting to AI provider
        $this->update_progress_with_message(20, 100, __('Connecting to AI provider...', 'ai-explainer'));
        
        // Get content generator instance
        $content_generator = $this->get_content_generator();
        
        // Set user context for post creation
        $user_id = $item['options']['created_by'] ?? get_current_user_id();
        if ($user_id && function_exists('wp_set_current_user')) {
            wp_set_current_user($user_id);
        }
        
        $this->log(sprintf('Generating blog post with %s provider', esc_html($item['options']['ai_provider'])), 'info');
        
        // Update progress for content generation
        $this->update_progress_with_message(35, 100, __('Generating blog content...', 'ai-explainer'));
        
        // Generate blog post content
        $result = $content_generator->generate_blog_post(
            $item['selection_text'],
            $item['options']
        );
        
        if (!$result || empty($result['content'])) {
            throw new \Exception(esc_html(__('Failed to generate blog post content', 'ai-explainer')));
        }
        
        $this->log('Blog post content generated successfully', 'info');
        
        // Check if image generation was requested to show appropriate progress
        $has_image = !empty($item['options']['generate_image']) && !empty($result['featured_image_id']);
        $has_seo = !empty($result['seo_data']);
        
        // Update progress for image processing
        if ($has_image) {
            $this->update_progress_with_message(60, 100, __('Processing featured image...', 'ai-explainer'));
        } else if ($has_seo) {
            $this->update_progress_with_message(60, 100, __('Creating SEO metadata...', 'ai-explainer'));
        } else {
            $this->update_progress_with_message(60, 100, __('Creating WordPress post...', 'ai-explainer'));
        }
        
        // Create WordPress post
        $post_data = array(
            'post_title' => sanitize_text_field($result['title'] ?? __('AI Generated Blog Post', 'ai-explainer')),
            'post_content' => wp_kses_post($result['content']),
            'post_status' => $item['options']['post_status'] ?? 'draft',
            'post_author' => $user_id,
            'post_type' => 'post',
            'meta_input' => array(
                '_explainer_generated_from' => substr($item['selection_text'], 0, 500),
                '_explainer_ai_provider' => $item['options']['ai_provider'],
                '_explainer_generation_options' => $item['options']
            )
        );
        
        // Add categories if specified
        if (!empty($item['options']['categories'])) {
            $post_data['post_category'] = array_map('intval', (array)$item['options']['categories']);
        }
        
        // Insert the post
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            /* translators: %s: WordPress error message describing why the post could not be created */
            throw new \Exception(sprintf(esc_html(__('Failed to create WordPress post: %s', 'ai-explainer')), esc_html($post_id->get_error_message())));
        }
        
        $this->log(sprintf('Blog post created with ID: %d', esc_html($post_id)), 'info');
        
        // Handle featured image if requested and generated
        if ($has_image) {
            $this->update_progress_with_message(75, 100, __('Attaching featured image...', 'ai-explainer'));
            $this->log(sprintf('Setting featured image with ID: %d', esc_html($result['featured_image_id'])), 'info');
            $image_result = set_post_thumbnail($post_id, $result['featured_image_id']);
            if ($image_result) {
                $this->log('Featured image set successfully', 'info');
            } else {
                $this->log('Failed to set featured image', 'warning');
            }
        } elseif (!empty($item['options']['generate_image'])) {
            $this->log('Image generation was requested but no featured_image_id was provided in result', 'warning');
        }
        
        // Handle SEO metadata if generated
        if ($has_seo) {
            if (!$has_image) {
                $this->update_progress_with_message(75, 100, __('Adding SEO metadata...', 'ai-explainer'));
            } else {
                $this->update_progress_with_message(85, 100, __('Adding SEO metadata...', 'ai-explainer'));
            }
            $this->save_seo_metadata($post_id, $result['seo_data']);
        }
        
        // Final progress update
        $this->update_progress_with_message(95, 100, __('Finalising blog post...', 'ai-explainer'));
        
        // Track blog post creation
        $this->track_blog_post($post_id, $item, $result);
        
        // Store result metadata
        $this->update_job_meta('post_id', $post_id);
        $this->update_job_meta('post_url', get_permalink($post_id));
        $this->update_job_meta('edit_url', get_edit_post_link($post_id, 'raw'));
        $this->update_job_meta('generation_cost', $result['cost'] ?? 0);
        
        // Complete!
        $this->update_progress_with_message(100, 100, __('Blog post created successfully!', 'ai-explainer'));
        
        return array(
            'success' => true,
            'post_id' => $post_id,
            'post_url' => get_permalink($post_id),
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'title' => get_the_title($post_id),
            'cost' => $result['cost'] ?? 0
        );
    }
    
    /**
     * Called when job completes successfully
     */
    public function on_complete() {
        $post_id = $this->get_job_meta('post_id');
        
        if ($post_id) {
            $this->log(sprintf('Blog creation job completed. Post ID: %d', esc_html($post_id)), 'info');
            
            // Send notification email if enabled
            $this->send_completion_notification($post_id);
        }
    }
    
    /**
     * Called when job fails permanently
     */
    public function on_failure() {
        $this->log('Blog creation job failed after maximum attempts', 'error');
        
        // Clean up any partial data
        $post_id = $this->get_job_meta('post_id');
        if ($post_id && get_post_status($post_id) === 'auto-draft') {
            wp_delete_post($post_id, true);
        }
    }
    
    /**
     * Get content generator instance
     * 
     * @return \ExplainerPlugin_Content_Generator
     */
    private function get_content_generator() {
        if (!$this->content_generator) {
            if (!class_exists('\ExplainerPlugin_Content_Generator')) {
                require_once dirname(__DIR__) . '/services/class-content-generator.php';
            }
            $this->content_generator = new \ExplainerPlugin_Content_Generator();
        }
        return $this->content_generator;
    }
    
    /**
     * Set featured image for post
     * 
     * @param int $post_id Post ID
     * @param string $image_url Image URL
     * @param string $title Image title
     */
    private function set_featured_image($post_id, $image_url, $title) {
        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        
        $attachment_id = media_sideload_image($image_url, $post_id, $title, 'id');
        
        if (!is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
            $this->log('Featured image set successfully', 'info');
        } else {
            $this->log('Failed to set featured image: ' . $attachment_id->get_error_message(), 'warning');
        }
    }
    
    /**
     * Save SEO metadata
     * 
     * @param int $post_id Post ID
     * @param array $seo_data SEO data
     */
    private function save_seo_metadata($post_id, $seo_data) {
        // Save as post meta for compatibility with SEO plugins
        if (!empty($seo_data['meta_description'])) {
            update_post_meta($post_id, '_wp_ai_explainer_meta_description', sanitize_text_field($seo_data['meta_description']));
        }
        
        if (!empty($seo_data['focus_keywords'])) {
            update_post_meta($post_id, '_wp_ai_explainer_focus_keywords', sanitize_text_field($seo_data['focus_keywords']));
        }
        
        // Add tags if provided
        if (!empty($seo_data['tags'])) {
            wp_set_post_tags($post_id, $seo_data['tags']);
        }
        
        $this->log('SEO metadata saved', 'info');
    }
    
    /**
     * Track blog post creation in database
     *
     * @param int $post_id Post ID
     * @param array $item Job item data
     * @param array $result Generation result
     */
    private function track_blog_post($post_id, $item, $result) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_explainer_blog_posts';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $table_name,
            array(
                'post_id' => $post_id,
                'source_selection' => $item['selection_text'],
                'ai_provider' => $item['options']['ai_provider'],
                'post_length' => $item['options']['post_length'] ?? 'medium',
                'generation_cost' => $result['cost'] ?? 0,
                'generated_image' => !empty($item['options']['generate_image']) ? 1 : 0,
                'generated_seo' => !empty($item['options']['generate_seo']) ? 1 : 0,
                'created_by' => $item['options']['created_by'] ?? get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%f', '%d', '%d', '%d', '%s')
        );

        // Invalidate relevant caches
        wp_cache_delete('blog_posts_by_user_' . ($item['options']['created_by'] ?? get_current_user_id()), 'wp_ai_explainer');
        wp_cache_delete('blog_posts_count', 'wp_ai_explainer');
    }
    
    /**
     * Send completion notification email
     * 
     * @param int $post_id Post ID
     */
    private function send_completion_notification($post_id) {
        $notify_email = get_option('explainer_blog_notification_email');
        
        if (!$notify_email) {
            return;
        }
        
        $post_title = get_the_title($post_id);
        $edit_link = get_edit_post_link($post_id, 'raw');
        $preview_link = get_permalink($post_id);

        $subject = sprintf(
            /* translators: 1: Site name, 2: Blog post title */
            __('[%1$s] Blog Post Created: %2$s', 'ai-explainer'),
            get_bloginfo('name'),
            $post_title
        );

        /* translators: 1: Blog post title, 2: Edit link URL, 3: Preview link URL */
        $message = sprintf(
            __("A new blog post has been created successfully.\n\nTitle: %1\$s\n\nEdit: %2\$s\nPreview: %3\$s\n\n--\nAI Explainer", 'ai-explainer'),
            $post_title,
            $edit_link,
            $preview_link
        );
        
        wp_mail($notify_email, $subject, $message);
        
        $this->log('Notification email sent', 'info');
    }
    
    /**
     * Ensure blog posts tracking table exists
     */
    private function ensure_blog_posts_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_explainer_blog_posts';

        // Check if table exists using prepare()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) !== $table_name) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            
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
            
            dbDelta($sql);
        }
    }
    
    /**
     * Update progress with custom message
     * 
     * @param int $completed Progress completed (0-100)
     * @param int $total Progress total (always 100 for percentage)
     * @param string $message Custom progress message
     */
    private function update_progress_with_message($completed, $total, $message) {
        // Get current job ID for debugging
        $job_id = $this->get_current_job_id();
        
        // Call parent progress update method
        $this->update_progress($completed, $total);
        
        // Store custom progress message in job meta
        $meta_result = $this->update_job_meta('progress_text', $message);
        
        // Log progress for debugging
        /* translators: 1: Progress percentage, 2: Progress message, 3: Job ID, 4: Meta storage status (yes/no) */
        $this->log(sprintf('Progress: %1$d%% - %2$s (Job ID: %3$s, Meta stored: %4$s)', esc_html($completed), esc_html($message), esc_html($job_id), esc_html($meta_result ? 'yes' : 'no')), 'info');
        
        // Also log to WordPress debug log for easier access
        if (function_exists('explainer_log_info')) {
            explainer_log_info('Blog Creation Progress', array(
                'progress_percentage' => $completed,
                'message' => $message,
                'job_id' => $job_id
            ), 'blog_creator');
        }
        
    }
    
    /**
     * Validate API key for the specified provider
     * 
     * @param string $ai_provider AI provider identifier
     * @throws \Exception If no API key is configured
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
            throw new \Exception(sprintf(esc_html(__('Unknown AI provider: %s', 'ai-explainer')), esc_html($ai_provider)));
        }
        
        // Check if encrypted API key exists
        $encrypted_api_key = get_option($option_key, '');
        if (empty($encrypted_api_key)) {
            $provider_name = strpos($option_key, 'openai') !== false ? 'OpenAI' : 'Claude';
            /* translators: %s: AI provider name (e.g. OpenAI or Claude) */
            throw new \Exception(sprintf(esc_html(__('No API key configured for %s. Please configure your API key in the plugin settings.', 'ai-explainer')), esc_html($provider_name)));
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
                    /* translators: %s: AI provider name (e.g. OpenAI or Claude) */
                    throw new \Exception(sprintf(esc_html(__('Failed to decrypt %s API key. Please reconfigure your API key in the plugin settings.', 'ai-explainer')), esc_html($provider_name)));
                }
                
                // Validate the decrypted API key using existing provider system
                $provider = \ExplainerPlugin_Provider_Factory::get_provider($provider_slug);
                if ($provider && !$provider->validate_api_key($decrypted_api_key)) {
                    $provider_name = ucfirst($provider_slug);
                    /* translators: %s: AI provider name (e.g. OpenAI or Claude) */
                    throw new \Exception(sprintf(esc_html(__('Invalid %s API key format. Please check your API key in the plugin settings.', 'ai-explainer')), esc_html($provider_name)));
                }
            }
        } catch (\Exception $e) {
            // Re-throw validation errors
            throw $e;
        }
        
        $this->log(sprintf('API key validation passed for provider: %s', esc_html($ai_provider)), 'info');
    }
    
}