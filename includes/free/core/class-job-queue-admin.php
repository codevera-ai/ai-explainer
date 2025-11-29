<?php
/**
 * Job Queue Admin Interface
 * 
 * Provides WordPress admin functionality for job queue management.
 * 
 * @package ExplainerPlugin
 * @subpackage JobQueue
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Job Queue Admin Class
 *
 * Handles admin interface for job queue system including
 * management pages and AJAX operations.
 */
class ExplainerPlugin_Job_Queue_Admin {
    
    /**
     * Admin page slug
     * 
     * @var string
     */
    private const MENU_SLUG = 'wp-ai-explainer-job-queue';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into WordPress admin
        add_action('admin_menu', array($this, 'add_admin_menu'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // AJAX handlers for admin operations
        add_action('wp_ajax_explainer_job_queue_status', array($this, 'ajax_get_queue_status'));
        add_action('wp_ajax_explainer_job_retry', array($this, 'ajax_retry_job'));
        add_action('wp_ajax_explainer_job_cancel', array($this, 'ajax_cancel_job'));
        add_action('wp_ajax_explainer_get_recent_jobs', array($this, 'ajax_get_recent_jobs'));
        add_action('wp_ajax_wp_ai_explainer_run_job', array($this, 'ajax_run_job'));
        add_action('wp_ajax_wp_ai_explainer_get_job_status', array($this, 'ajax_get_job_status'));
        add_action('wp_ajax_wp_ai_explainer_get_job_statuses', array($this, 'ajax_get_job_statuses'));
        add_action('wp_ajax_wp_ai_explainer_get_job_post_info', array($this, 'ajax_get_job_post_info'));
        add_action('wp_ajax_explainer_execute_job_async', array($this, 'ajax_execute_job_async'));
        
        // Register cron hook for async job execution
        add_action('explainer_execute_job_async', array($this, 'execute_job_async'), 10, 2);
        add_action('wp_ajax_nopriv_explainer_execute_job_async', array($this, 'ajax_execute_job_async'));
        
        // Cron job processing
        add_action('explainer_process_blog_queue_recurring', array($this, 'process_pending_jobs_cron'));
    }
    
    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        // Job Queue is a premium-only feature
        if (!explainer_can_use_premium_features()) {
            return;
        }

        add_submenu_page(
            'wp-ai-explainer-admin',
            __('Job Queue', 'ai-explainer'),
            __('Job Queue', 'ai-explainer'),
            'manage_options',
            self::MENU_SLUG,
            array($this, 'render_admin_page')
        );
    }

    /**
     * Enqueue admin assets
     * 
     * @param string $hook_suffix Current admin page hook suffix
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Check hook suffix
        
        // Only load on our admin pages or dashboard
        if (strpos($hook_suffix, self::MENU_SLUG) === false && $hook_suffix !== 'index.php') {
            return;
        }
        
        // Enqueue main plugin styles (includes notification CSS)
        wp_enqueue_style(
            'explainer-style',
            EXPLAINER_PLUGIN_URL . 'assets/css/style.css',
            array(),
            EXPLAINER_PLUGIN_VERSION
        );
        
        // Enqueue admin styles
        wp_enqueue_style(
            'wp-ai-explainer-job-queue-admin',
            EXPLAINER_PLUGIN_URL . 'assets/css/job-queue-admin.css',
            array('explainer-style'),
            EXPLAINER_PLUGIN_VERSION
        );
        
        // Enqueue notification system first
        wp_enqueue_script(
            'explainer-notifications',
            EXPLAINER_PLUGIN_URL . 'assets/js/notification-system.js',
            array('jquery'),
            EXPLAINER_PLUGIN_VERSION,
            true
        );
        
        // Enqueue admin scripts
        wp_enqueue_script(
            'wp-ai-explainer-job-queue-admin',
            EXPLAINER_PLUGIN_URL . 'assets/js/job-queue-admin.js',
            array('jquery', 'explainer-notifications'),
            EXPLAINER_PLUGIN_VERSION . '.' . time(),
            true
        );
        
        // Localize script configuration
        wp_localize_script('wp-ai-explainer-job-queue-admin', 'explainerJobQueue', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('explainer_job_queue_nonce'),
            'admin_nonce' => wp_create_nonce('explainer_admin_nonce'),
            'cron_enabled' => get_option('explainer_enable_cron', false),
            'site_url' => home_url(),
            'strings' => array(
                'confirm_cancel' => __('Are you sure you want to cancel this job?', 'ai-explainer'),
                'confirm_retry' => __('Are you sure you want to retry this job?', 'ai-explainer'),
                'processing' => __('Processing...', 'ai-explainer'),
                'error' => __('An error occurred. Please try again.', 'ai-explainer')
            )
        ));
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Verify premium access
        if (!explainer_can_use_premium_features()) {
            wp_die(
                esc_html__('This feature is only available in the premium version.', 'ai-explainer'),
                esc_html__('Premium Feature', 'ai-explainer'),
                array('response' => 403)
            );
        }

        // Get manager instance
        $manager = ExplainerPlugin_Job_Queue_Manager::get_instance();
        
        // Get job statistics
        $stats = $this->get_queue_statistics();
        
        // Get filter parameters from request - Read-only GET params for admin filtering, no data modification
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $filters = array(
            'search' => isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '',
            'job_type' => isset($_GET['job_type']) ? sanitize_text_field(wp_unslash($_GET['job_type'])) : 'all',
            'status' => isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'all'
        );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        // Get pagination parameters
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET param for pagination, no data modification
        $current_page = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 20;
        
        // Get total count and jobs with filters
        $total_jobs = $this->get_jobs_count($filters);
        $jobs = $this->get_jobs($filters, $per_page, ($current_page - 1) * $per_page);
        
        // Create pagination instance
        $pagination = null;
        if (class_exists('ExplainerPlugin_Pagination')) {
            $pagination = new ExplainerPlugin_Pagination($total_jobs, $per_page, $current_page);
        }
        
        ?>
        <div class="wrap job-queue-wrap">
            <!-- Page Header with Integrated Status -->
            <div class="job-queue-header-container">
                <div class="job-queue-header-left">
                    <h1><?php esc_html_e('Job Queue Management', 'ai-explainer'); ?></h1>
                    <p><?php esc_html_e('Monitor and manage background processing jobs for blog creation and content analysis.', 'ai-explainer'); ?></p>
                </div>
                <div class="job-queue-header-right">
                    <div class="job-queue-status-list">
                        <div class="status-item">
                            <span class="status-label"><?php esc_html_e('Pending Jobs', 'ai-explainer'); ?></span>
                            <span class="status-value"><?php echo esc_html($stats['pending']); ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-label"><?php esc_html_e('Processing', 'ai-explainer'); ?></span>
                            <span class="status-value"><?php echo esc_html($stats['processing']); ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-label"><?php esc_html_e('Completed', 'ai-explainer'); ?></span>
                            <span class="status-value"><?php echo esc_html($stats['completed']); ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-label"><?php esc_html_e('Failed', 'ai-explainer'); ?></span>
                            <span class="status-value"><?php echo esc_html($stats['failed']); ?></span>
                        </div>
                        <div class="status-item">
                            <span class="status-label"><?php esc_html_e('Processing Mode', 'ai-explainer'); ?></span>
                            <span class="status-value" style="color: <?php echo esc_attr(get_option('explainer_enable_cron', false) ? '#10b981' : '#f59e0b'); ?>;">
                                <?php echo get_option('explainer_enable_cron', false) ? esc_html__('Automatic (Cron)', 'ai-explainer') : esc_html__('Manual Only', 'ai-explainer'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Search and Filter Controls -->
            <div class="job-queue-filters">
                <form method="get" class="job-filters-form">
                    <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Echoing page param for GET form, no data modification ?>
                    <input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? esc_attr(sanitize_text_field(wp_unslash($_GET['page']))) : ''; ?>" />
                    
                    <div class="filter-group">
                        <label for="job-search" class="screen-reader-text"><?php esc_html_e('Search Jobs', 'ai-explainer'); ?></label>
                        <input type="search" 
                               id="job-search" 
                               name="search" 
                               value="<?php echo esc_attr($filters['search']); ?>" 
                               placeholder="<?php esc_attr_e('Search jobs...', 'ai-explainer'); ?>"
                               class="job-search-input" />
                    </div>
                    
                    <div class="filter-group">
                        <label for="job-type-filter"><?php esc_html_e('Job Type:', 'ai-explainer'); ?></label>
                        <select id="job-type-filter" name="job_type" class="job-type-select">
                            <option value="all" <?php selected($filters['job_type'], 'all'); ?>><?php esc_html_e('All Types', 'ai-explainer'); ?></option>
                            <option value="blog_creation" <?php selected($filters['job_type'], 'blog_creation'); ?>><?php esc_html_e('Blog Creation', 'ai-explainer'); ?></option>
                            <option value="ai_term_scan" <?php selected($filters['job_type'], 'ai_term_scan'); ?>><?php esc_html_e('Term Scan', 'ai-explainer'); ?></option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="job-status-filter"><?php esc_html_e('Status:', 'ai-explainer'); ?></label>
                        <select id="job-status-filter" name="status" class="job-status-select">
                            <option value="all" <?php selected($filters['status'], 'all'); ?>><?php esc_html_e('All Statuses', 'ai-explainer'); ?></option>
                            <option value="pending" <?php selected($filters['status'], 'pending'); ?>><?php esc_html_e('Pending', 'ai-explainer'); ?></option>
                            <option value="processing" <?php selected($filters['status'], 'processing'); ?>><?php esc_html_e('Processing', 'ai-explainer'); ?></option>
                            <option value="completed" <?php selected($filters['status'], 'completed'); ?>><?php esc_html_e('Completed', 'ai-explainer'); ?></option>
                            <option value="failed" <?php selected($filters['status'], 'failed'); ?>><?php esc_html_e('Failed', 'ai-explainer'); ?></option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <input type="submit" value="<?php esc_attr_e('Filter', 'ai-explainer'); ?>" class="button button-primary" />
                        <?php if (!empty($filters['search']) || $filters['job_type'] !== 'all' || $filters['status'] !== 'all') : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)); ?>" class="button button-secondary"><?php esc_html_e('Clear', 'ai-explainer'); ?></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Jobs Table -->
            <h2 class="job-queue-section-title">
                <?php
                if (!empty($filters['search']) || $filters['job_type'] !== 'all' || $filters['status'] !== 'all') {
                    echo esc_html(sprintf(
                        /* translators: %d: number of filtered jobs */
                        __('Filtered Jobs (%d total)', 'ai-explainer'),
                        $total_jobs
                    ));
                } else {
                    echo esc_html(sprintf(
                        /* translators: %d: total number of jobs */
                        __('All Jobs (%d total)', 'ai-explainer'),
                        $total_jobs
                    ));
                }
                ?>
            </h2>
            
            <!-- Top Pagination -->
            <?php if ($pagination && $pagination->get_pagination_args()['total_pages'] > 1) : ?>
            <div class="tablenav top">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pagination class handles escaping internally
                echo $pagination->get_pagination_html('jobs-pagination-top');
                ?>
            </div>
            <?php endif; ?>
            
            <div class="job-queue-table">
                <table class="wp-list-table widefat fixed">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'ai-explainer'); ?></th>
                        <th><?php esc_html_e('Type', 'ai-explainer'); ?></th>
                        <th><?php esc_html_e('Content', 'ai-explainer'); ?></th>
                        <th><?php esc_html_e('Status', 'ai-explainer'); ?></th>
                        <th><?php esc_html_e('Result', 'ai-explainer'); ?></th>
                        <th><?php esc_html_e('Created', 'ai-explainer'); ?></th>
                        <th><?php esc_html_e('Actions', 'ai-explainer'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jobs)) : ?>
                        <tr>
                            <td colspan="7">
                                <div class="job-queue-empty">
                                    <h3><?php esc_html_e('No Jobs Found', 'ai-explainer'); ?></h3>
                                    <?php if (!empty($filters['search']) || $filters['job_type'] !== 'all' || $filters['status'] !== 'all') : ?>
                                        <p><?php esc_html_e('No jobs match the current filters. Try adjusting your search criteria.', 'ai-explainer'); ?></p>
                                    <?php else : ?>
                                        <p><?php esc_html_e('There are no jobs to display. Jobs will appear here after they are created through the plugin\'s features.', 'ai-explainer'); ?></p>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($jobs as $job) : ?>
                            <tr data-job-id="<?php echo esc_attr($job['id']); ?>"<?php if ($job['status'] === 'failed') echo ' class="job-row-failed"'; ?>>
                                <td><?php echo esc_html($job['id']); ?></td>
                                <td>
                                    <?php 
                                    $type_labels = array(
                                        'blog_creation' => __('Blog Creation', 'ai-explainer'),
                                        'ai_term_scan' => __('Term Scan', 'ai-explainer')
                                    );
                                    echo esc_html($type_labels[$job['widget']] ?? ucfirst($job['widget']));
                                    ?>
                                </td>
                                <td class="term-content-column">
                                    <?php if ($job['widget'] === 'blog_creation' && !empty($job['term'])) : ?>
                                        <div class="job-term">
                                            <strong><?php esc_html_e('Term:', 'ai-explainer'); ?></strong>
                                            <span><?php echo esc_html($job['term']); ?></span>
                                        </div>
                                        <?php if (!empty($job['explanation'])) : ?>
                                        <div class="job-explanation">
                                            <strong><?php esc_html_e('Explanation:', 'ai-explainer'); ?></strong>
                                            <span><?php echo esc_html($job['explanation']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    <?php elseif ($job['widget'] === 'ai_term_scan' && !empty($job['post_info'])) : ?>
                                        <div class="job-post-info">
                                            <strong><?php esc_html_e('Post:', 'ai-explainer'); ?></strong>
                                            <span><?php echo esc_html($job['post_info']['post_title']); ?></span>
                                            <span class="post-id">(ID: <?php echo esc_html($job['post_info']['post_id']); ?>)</span>
                                        </div>
                                    <?php else : ?>
                                        <span class="no-content"><?php esc_html_e('N/A', 'ai-explainer'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    // Map 'paused' to 'cancelled' for display purposes
                                    $display_status = $job['status'] === 'paused' ? 'cancelled' : $job['status'];
                                    $display_text = $job['status'] === 'paused' ? 'Cancelled' : ucfirst($job['status']);
                                    ?>
                                    <span class="job-status status-<?php echo esc_attr($display_status); ?>">
                                        <?php echo esc_html($display_text); ?>
                                        <?php if ($job['status'] === 'failed' && !empty($job['error_message'])) : ?>
                                            <div class="status-error" style="font-size: 11px; color: #d63638; margin-top: 2px; line-height: 1.3;">
                                                <?php echo esc_html($job['error_message']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="job-result-column">
                                    <?php if (in_array($job['widget'], ['blog_creation', 'ai_term_scan']) && $job['status'] === 'completed' && !empty($job['post_info'])) : ?>
                                        <div class="completed-post">
                                            <?php if (!empty($job['post_info']['thumbnail'])) : ?>
                                                <img src="<?php echo esc_url($job['post_info']['thumbnail']); ?>" 
                                                     alt="<?php echo esc_attr($job['post_info']['post_title']); ?>" 
                                                     class="post-thumbnail-small">
                                            <?php endif; ?>
                                            <div class="post-details">
                                                <div class="post-title"><?php echo esc_html($job['post_info']['post_title']); ?></div>
                                                <div class="post-links">
                                                    <a href="<?php echo esc_url($job['post_info']['edit_link']); ?>" class="edit-link">
                                                        <?php esc_html_e('Edit', 'ai-explainer'); ?>
                                                    </a>
                                                    <span class="separator">|</span>
                                                    <?php if ($job['widget'] === 'ai_term_scan' && !empty($job['post_info']['view_link'])) : ?>
                                                        <a href="<?php echo esc_url($job['post_info']['view_link']); ?>" class="view-link" target="_blank">
                                                            <?php esc_html_e('View Post', 'ai-explainer'); ?>
                                                        </a>
                                                    <?php else : ?>
                                                        <a href="<?php echo esc_url($job['post_info']['view_link']); ?>" class="view-link" target="_blank">
                                                            <?php esc_html_e('View', 'ai-explainer'); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else : ?>
                                        <span class="no-result">&nbsp;</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($job['created_at']); ?></td>
                                <td class="job-actions-cell">
                                    <div class="job-actions">
                                        <?php if ($job['status'] === 'failed') : ?>
                                            <button class="btn-base btn-secondary btn-sm btn-with-icon retry-job" data-job-id="<?php echo esc_attr($job['id']); ?>">
                                                <span class="dashicons dashicons-update btn-icon-size"></span>
                                                <?php esc_html_e('Retry', 'ai-explainer'); ?>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($job['status'] === 'pending' && !get_option('explainer_enable_cron', false)) : ?>
                                            <button class="btn-base btn-primary btn-sm btn-with-icon run-job run-job-btn" data-job-id="jq_<?php echo esc_attr($job['id']); ?>" data-job-type="<?php echo esc_attr($job['widget']); ?>">
                                                <span class="dashicons dashicons-controls-play btn-icon-size"></span>
                                                <?php esc_html_e('Run Job', 'ai-explainer'); ?>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (in_array($job['status'], array('pending', 'processing'))) : ?>
                                            <button class="btn-base btn-destructive btn-sm cancel-job" 
                                                    data-job-id="<?php echo esc_attr($job['id']); ?>"
                                                    <?php if ($job['status'] === 'processing') : ?>disabled title="<?php esc_attr_e('Cannot cancel job while processing', 'ai-explainer'); ?>"<?php endif; ?>>
                                                <?php esc_html_e('Cancel', 'ai-explainer'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                </table>
            </div>
            
            <!-- Bottom Pagination -->
            <?php if ($pagination && $pagination->get_pagination_args()['total_pages'] > 1) : ?>
            <div class="tablenav bottom">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pagination class handles escaping internally
                echo $pagination->get_pagination_html('jobs-pagination-bottom');
                ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Progress Modal -->
        <div id="job-progress-modal" class="job-progress-modal" style="display: none;">
            <div class="job-progress-modal-content">
                <div class="modal-header">
                    <h2 class="modal-title"><?php esc_html_e('Processing Job', 'ai-explainer'); ?></h2>
                    <div class="modal-warning">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('Important: Don\'t refresh or navigate away - this will stop the process!', 'ai-explainer'); ?>
                    </div>
                </div>
                
                <div class="modal-body">
                    <div class="progress-section">
                        <div class="progress-info">
                            <div class="progress-stage">
                                <span class="stage-text"><?php esc_html_e('Preparing to create blog post', 'ai-explainer'); ?></span>
                            </div>
                            <div class="progress-percent">0%</div>
                        </div>
                        
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        
                        <div class="stage-details">
                            <div class="current-stage"><?php esc_html_e('Initialising', 'ai-explainer'); ?></div>
                            <div class="estimated-time"><?php esc_html_e('Estimated time: 2-5 minutes', 'ai-explainer'); ?></div>
                        </div>
                    </div>
                    
                    <!-- Completion Section (hidden initially) -->
                    <div class="completion-section" style="display: none;">
                        <div class="completion-header">
                            <h3><?php esc_html_e('Blog Post Created Successfully', 'ai-explainer'); ?></h3>
                        </div>
                        
                        <div class="post-preview">
                            <div class="post-thumbnail">
                                <!-- Featured image will be inserted here -->
                            </div>
                            <div class="post-details">
                                <h4 class="post-title"><?php esc_html_e('Blog Post Title', 'ai-explainer'); ?></h4>
                                <div class="post-meta">
                                    <span class="post-status"><?php esc_html_e('Status: Draft', 'ai-explainer'); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="post-actions">
                            <a href="#" class="button button-primary edit-post-btn" target="_blank">
                                <span class="dashicons dashicons-edit"></span>
                                <?php esc_html_e('Edit Post', 'ai-explainer'); ?>
                            </a>
                            <a href="#" class="button button-secondary view-post-btn" target="_blank">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php esc_html_e('Preview Post', 'ai-explainer'); ?>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Error Section (hidden initially) -->
                    <div class="error-section" style="display: none;">
                        <div class="error-header">
                            <h3><?php esc_html_e('Job Failed', 'ai-explainer'); ?></h3>
                        </div>
                        <div class="error-message">
                            <!-- Error message will be inserted here -->
                        </div>
                        <div class="error-actions">
                            <button type="button" class="btn-base btn-secondary retry-job-btn">
                                <?php esc_html_e('Retry Job', 'ai-explainer'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-base btn-secondary close-modal-btn">
                        <?php esc_html_e('Close', 'ai-explainer'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Notifications Container -->
        <div class="explainer-notifications-container" id="explainer-notifications-container"></div>
        <?php
    }

    /**
     * Get queue statistics
     *
     * @return array Statistics data
     */
    private function get_queue_statistics() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';

        // Get counts by status
        $stats = array(
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'completed_today' => 0
        );

        // Check cache first
        $cache_key = 'explainer_queue_stats';
        $cached_stats = wp_cache_get($cache_key, 'ai-explainer');

        if (false !== $cached_stats && is_array($cached_stats)) {
            return $cached_stats;
        }

        // Query for status counts
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table_name} GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );

        foreach ($results as $row) {
            if (isset($stats[$row['status']])) {
                $stats[$row['status']] = (int) $row['count'];
            }
        }

        // Get today's completed count
        $today_start = wp_date('Y-m-d 00:00:00');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $stats['completed_today'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE status = 'completed' AND completed_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $today_start
            )
        );

        // Cache for 30 seconds
        wp_cache_set($cache_key, $stats, 'ai-explainer', 30);

        return $stats;
    }
    
    /**
     * Get jobs with filters and pagination
     *
     * @param array $filters Filter parameters
     * @param int $limit Number of jobs to retrieve
     * @param int $offset Offset for pagination
     * @return array Jobs data
     */
    public function get_jobs($filters = array(), $limit = 20, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';

        // Build cache key from filters
        $cache_key = 'explainer_jobs_' . md5(serialize($filters) . '_' . $limit . '_' . $offset);
        $cached_jobs = wp_cache_get($cache_key, 'ai-explainer');

        if (false !== $cached_jobs && is_array($cached_jobs)) {
            return $cached_jobs;
        }

        // Build WHERE clauses based on filters
        $where_clauses = array();
        $params = array();
        
        // Job type filter
        if (!empty($filters['job_type']) && $filters['job_type'] !== 'all') {
            $where_clauses[] = "job_type = %s";
            $params[] = $filters['job_type'];
        }
        
        // Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where_clauses[] = "status = %s";
            $params[] = $filters['status'];
        }
        
        // Search term
        if (!empty($filters['search'])) {
            $where_clauses[] = "(data LIKE %s OR queue_id LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_clause = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        // Build ORDER BY clause
        $order_by = "CASE status 
                        WHEN 'pending' THEN 1 
                        WHEN 'processing' THEN 2 
                        WHEN 'completed' THEN 3 
                        WHEN 'failed' THEN 4 
                        ELSE 5 
                    END,
                    created_at DESC";
        
        // Build final query
        $query = "SELECT queue_id, queue_id as id, job_type as widget, status, created_at, completed_at, data, created_by, error_message, post_id, result_data, selection_text, options, explanation_id FROM {$table_name} {$where_clause} ORDER BY {$order_by} LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        $results = $wpdb->get_results(
            $wpdb->prepare($query, ...$params), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
            ARRAY_A
        );
        
        if (!$results) {
            return array();
        }
        
        // Process each job to add job-specific data
        foreach ($results as &$job) {
            if ($job['widget'] === 'blog_creation') {
                // Try to get data from explanation_id first (best approach)
                $selection_text = '';
                $explanation = '';
                $explanation_id = $job['explanation_id'] ?? null;

                if ($explanation_id) {
                    // Fetch from explanations table using ID
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $explanation_data = $wpdb->get_row($wpdb->prepare(
                        "SELECT selected_text, ai_explanation FROM {$wpdb->prefix}ai_explainer_selections WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        $explanation_id
                    ));

                    if (function_exists('explainer_log_debug')) {
                        explainer_log_debug('Job Queue Admin: Explanation lookup by ID', array(
                            'job_id' => $job['queue_id'],
                            'explanation_id' => $explanation_id,
                            'found_data' => !empty($explanation_data),
                            'has_explanation' => !empty($explanation_data->ai_explanation ?? null)
                        ), 'Job_Queue_Admin');
                    }
                    
                    if ($explanation_data) {
                        $selection_text = $explanation_data->selected_text;
                        $explanation = $explanation_data->ai_explanation;
                        $job['explanation_link'] = admin_url('admin.php?page=wp-ai-explainer-admin&explanation_id=' . $explanation_id);
                    }
                }
                
                // Fallback to stored data if explanation_id lookup failed
                if (empty($selection_text)) {
                    if (!empty($job['selection_text'])) {
                        // Use new column format
                        $selection_text = $job['selection_text'];
                        $options = maybe_unserialize($job['options']);
                        // Check if explanation is stored in options
                        if (is_array($options) && !empty($options['explanation'])) {
                            $explanation = $options['explanation'];
                        }
                    } else {
                        // Fall back to old serialized data format
                        $job_data = maybe_unserialize($job['data']);
                        if ($job_data) {
                            $selection_text = $job_data['selection_text'] ?? '';
                            // Check if explanation is in the job data
                            if (!empty($job_data['explanation'])) {
                                $explanation = $job_data['explanation'];
                            }
                        }
                    }
                    
                    // If we still don't have an explanation, try to fetch from selections table as fallback
                    if (empty($explanation) && !empty($selection_text)) {
                        $explanation = $this->get_ai_explanation_for_selection($selection_text);
                    }
                }
                
                // Debug: Log final state
                if (function_exists('explainer_log_debug')) {
                    explainer_log_debug('Job Queue Admin: Final explanation state', array(
                        'job_id' => $job['queue_id'],
                        'explanation_id' => $explanation_id,
                        'selection_text_length' => strlen($selection_text),
                        'explanation_length' => strlen($explanation ?? ''),
                        'explanation_empty' => empty($explanation),
                        'explanation_preview' => substr($explanation ?? '', 0, 50)
                    ), 'Job_Queue_Admin');
                }
                
                // Set term and explanation (preserve explanation if already found)
                $job['term'] = wp_trim_words($selection_text, 8, '...');
                if (!empty($explanation)) {
                    $job['explanation'] = $explanation;
                } else {
                    $job['explanation'] = '';
                }
                $job['explanation_id'] = $explanation_id;
            }
            
            // Get post information based on job type
            if ($job['widget'] === 'blog_creation' && $job['status'] === 'completed') {
                // Only get blog post info for completed blog creation jobs
                ExplainerPlugin_Debug_Logger::debug("Calling get_blog_post_info for job {$job['queue_id']}", 'Job_Queue_Admin');
                $post_info = $this->get_blog_post_info($job);
                ExplainerPlugin_Debug_Logger::debug("get_blog_post_info returned", 'Job_Queue_Admin', array('post_info' => $post_info));
                $job['post_info'] = $post_info;
            } elseif ($job['widget'] === 'ai_term_scan') {
                // Get term scan info for all term scan jobs (post already exists)
                ExplainerPlugin_Debug_Logger::debug("Calling get_term_scan_info for job {$job['queue_id']}", 'Job_Queue_Admin');
                $post_info = $this->get_term_scan_info($job);
                ExplainerPlugin_Debug_Logger::debug("get_term_scan_info returned", 'Job_Queue_Admin', array('post_info' => $post_info));
                $job['post_info'] = $post_info;
            }
        }

        // Cache results for 30 seconds
        wp_cache_set($cache_key, $results, 'ai-explainer', 30);

        return $results;
    }

    /**
     * Get total count of jobs with filters
     *
     * @param array $filters Filter parameters
     * @return int Total count
     */
    public function get_jobs_count($filters = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';

        // Build cache key from filters
        $cache_key = 'explainer_jobs_count_' . md5(serialize($filters));
        $cached_count = wp_cache_get($cache_key, 'ai-explainer');

        if (false !== $cached_count) {
            return (int) $cached_count;
        }

        // Build WHERE clauses based on filters
        $where_clauses = array();
        $params = array();

        // Job type filter
        if (!empty($filters['job_type']) && $filters['job_type'] !== 'all') {
            $where_clauses[] = "job_type = %s";
            $params[] = $filters['job_type'];
        }

        // Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where_clauses[] = "status = %s";
            $params[] = $filters['status'];
        }

        // Search term
        if (!empty($filters['search'])) {
            $where_clauses[] = "(data LIKE %s OR queue_id LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $where_clause = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        $query = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";

        if (!empty($params)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
            $count = (int) $wpdb->get_var($wpdb->prepare($query, ...$params));
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        // Cache for 30 seconds
        wp_cache_set($cache_key, $count, 'ai-explainer', 30);

        return $count;
    }
    
    /**
     * Get recent jobs (legacy method for compatibility)
     * 
     * @param int $limit Number of jobs to retrieve
     * @return array Recent jobs data
     */
    private function get_recent_jobs($limit = 20) {
        return $this->get_jobs(array(), $limit, 0);
    }
    
    /**
     * AJAX handler for getting queue status
     */
    public function ajax_get_queue_status() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_job_queue_nonce')) {
            wp_die(esc_html('Invalid nonce'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html('Insufficient permissions'));
        }

        // Check premium access
        if (!explainer_can_use_premium_features()) {
            wp_send_json_error(array('message' => __('This feature is only available in the premium version.', 'ai-explainer')));
        }

        $stats = $this->get_queue_statistics();
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX handler for retrying a job
     */
    public function ajax_retry_job() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_job_queue_nonce')) {
            wp_die(esc_html('Invalid nonce'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html('Insufficient permissions'));
        }

        // Check premium access
        if (!explainer_can_use_premium_features()) {
            wp_send_json_error(array('message' => __('This feature is only available in the premium version.', 'ai-explainer')));
        }

        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
        if (!$job_id) {
            wp_send_json_error('Invalid job ID');
        }
        
        // Get job data before update for webhook
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $job_before = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE queue_id = %d", $job_id), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );

        // Reset job status to pending
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $result = $wpdb->update(
            $table_name,
            array(
                'status' => 'pending',
                'error_message' => null,
                'attempts' => 0
            ),
            array('queue_id' => $job_id),
            array('%s', '%s', '%d'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to retry job');
        }
        
        
        wp_send_json_success('Job queued for retry');
    }
    
    /**
     * Get blog post information for completed job
     * 
     * @param array $job Job data array containing job details
     * @return array|null Post information or null if not found
     */
    private function get_blog_post_info($job) {
        $post_id = null;
        
        // First priority: Check the new post_id column
        if (isset($job['post_id']) && $job['post_id']) {
            $post_id = (int) $job['post_id'];
            ExplainerPlugin_Debug_Logger::debug("get_blog_post_info: Found post_id {$post_id} in job post_id column", 'Job_Queue_Admin');
        }
        
        // Second priority: Check result_data column
        if (!$post_id && isset($job['result_data']) && $job['result_data']) {
            $result_data = maybe_unserialize($job['result_data']);
            if ($result_data && is_array($result_data) && isset($result_data['post_id'])) {
                $post_id = (int) $result_data['post_id'];
                ExplainerPlugin_Debug_Logger::debug("get_blog_post_info: Found post_id {$post_id} in result_data column", 'Job_Queue_Admin');
            }
        }
        
        // Third priority: Check job data (legacy)
        if (!$post_id) {
            $job_data = maybe_unserialize($job['data']);
            if ($job_data && isset($job_data['result']['post_id'])) {
                $post_id = (int) $job_data['result']['post_id'];
                ExplainerPlugin_Debug_Logger::debug("get_blog_post_info: Found post_id {$post_id} in job result data", 'Job_Queue_Admin');
            }
        }
        
        // Fourth priority: Failsafe lookup via post meta (bidirectional relationship)
        if (!$post_id && isset($job['queue_id'])) {
            $post_id = $this->get_post_id_by_job_queue_id($job['queue_id']);
            if ($post_id) {
                ExplainerPlugin_Debug_Logger::debug("get_blog_post_info: Found post_id {$post_id} via failsafe post meta lookup for job {$job['queue_id']}", 'Job_Queue_Admin');
            }
        }

        // If we still don't have a post_id, return null
        if (!$post_id) {
            ExplainerPlugin_Debug_Logger::debug("get_blog_post_info: No post_id found for job", 'Job_Queue_Admin');
            return null;
        }
        
        // Get post from WordPress
        $post = get_post($post_id);
        if (!$post) {
            ExplainerPlugin_Debug_Logger::debug("get_blog_post_info: Post {$post_id} not found in WordPress", 'Job_Queue_Admin');
            return null;
        }
        
        $post_title = $post->post_title ?: __('(Untitled)', 'ai-explainer');
        
        // Get thumbnail
        $thumbnail_url = '';
        if (has_post_thumbnail($post_id)) {
            $thumbnail_url = get_the_post_thumbnail_url($post_id, array(40, 40));
        }
        
        ExplainerPlugin_Debug_Logger::debug("get_blog_post_info: Successfully retrieved post info for post {$post_id}: '{$post_title}'", 'Job_Queue_Admin');
        
        return array(
            'post_id' => $post_id,
            'post_title' => $post_title,
            'thumbnail' => $thumbnail_url,
            'edit_link' => admin_url('post.php?post=' . $post_id . '&action=edit'),
            'view_link' => get_permalink($post_id),
        );
    }
    
    /**
     * Get AI explanation for selected text from selections table
     * 
     * @param string $selection_text The selected text to find explanation for
     * @return string The AI explanation or empty string if not found
     */
    private function get_ai_explanation_for_selection($selection_text) {
        if (empty($selection_text)) {
            return '';
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';


        // Query for the AI explanation
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $explanation = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ai_explanation FROM {$table_name} WHERE selected_text = %s AND ai_explanation IS NOT NULL AND ai_explanation != '' ORDER BY last_seen DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $selection_text
            )
        );
        
        if ($explanation) {
            // Truncate long explanations for display
            return wp_trim_words($explanation, 25, '...');
        }
        
        // Fallback if no explanation found
        return __('No explanation available', 'ai-explainer');
    }
    
    /**
     * Get term scan information for completed job
     * 
     * @param array $job Job data array containing job details
     * @return array|null Term scan information or null if not found
     */
    private function get_term_scan_info($job) {
        $post_id = null;
        
        // First priority: Check the new post_id column
        if (isset($job['post_id']) && $job['post_id']) {
            $post_id = (int) $job['post_id'];
        }
        
        // Second priority: Check job data
        if (!$post_id) {
            $job_data = maybe_unserialize($job['data']);
            if ($job_data && isset($job_data['post_id'])) {
                $post_id = (int) $job_data['post_id'];
            }
        }
        
        // Third priority: Failsafe lookup via post meta (bidirectional relationship)
        if (!$post_id && isset($job['queue_id'])) {
            $post_id = $this->get_post_id_by_job_queue_id($job['queue_id']);
        }
        
        if (!$post_id) {
            return null;
        }
        
        // Verify post exists
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }
        
        $post_title = $post->post_title ?: __('(Untitled)', 'ai-explainer');
        
        // Get number of terms found from post meta
        $terms_count = get_post_meta($post_id, '_wp_ai_explainer_scan_terms_count', true);
        $terms_count = $terms_count ? (int) $terms_count : 0;
        
        // Get scan completion date
        $scan_completed = get_post_meta($post_id, '_wp_ai_explainer_scan_completed', true);
        
        // Get thumbnail
        $thumbnail_url = '';
        if (has_post_thumbnail($post_id)) {
            $thumbnail_url = get_the_post_thumbnail_url($post_id, array(40, 40));
        }
        
        return array(
            'job_type' => $job['widget'] ?? 'ai_term_scan',
            'post_id' => $post_id,
            'post_title' => $post_title,
            'thumbnail' => $thumbnail_url,
            'terms_found' => $terms_count,
            'scan_completed' => $scan_completed,
            'edit_link' => admin_url('post.php?post=' . $post_id . '&action=edit'),
            'view_link' => get_permalink($post_id),
            'results_link' => admin_url('admin.php?page=wp-ai-explainer-admin&tab=post-scan'),
        );
    }
    
    /**
     * AJAX handler for cancelling a job
     */
    public function ajax_cancel_job() {
        // Verify nonce
        check_ajax_referer('explainer_admin_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-explainer')));
        }

        // Check premium access
        if (!explainer_can_use_premium_features()) {
            wp_send_json_error(array('message' => __('This feature is only available in the premium version.', 'ai-explainer')));
        }

        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        if (empty($job_id)) {
            wp_send_json_error(array('message' => __('Invalid job ID', 'ai-explainer')));
        }
        
        // Get job data before deletion for logging
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';
        $numeric_job_id = intval(str_replace('jq_', '', $job_id));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $job_before = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE queue_id = %d", $numeric_job_id), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );
        
        if (!$job_before) {
            wp_send_json_error(array('message' => __('Job not found', 'ai-explainer')));
        }
        
        ExplainerPlugin_Debug_Logger::debug("Deleting job {$numeric_job_id} from database", 'Job_Queue_Admin');

        // Delete the job completely from the database
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $result = $wpdb->delete(
            $table_name,
            array('queue_id' => $numeric_job_id),
            array('%d')
        );
        
        if ($result !== false && $result > 0) {
            ExplainerPlugin_Debug_Logger::debug("Job {$numeric_job_id} deleted successfully", 'Job_Queue_Admin');
            wp_send_json_success(array(
                'message' => __('Job deleted successfully', 'ai-explainer'),
                'deleted' => true
            ));
        } else {
            ExplainerPlugin_Debug_Logger::error("Failed to delete job {$numeric_job_id}", 'Job_Queue_Admin');
            wp_send_json_error(array('message' => __('Failed to delete job', 'ai-explainer')));
        }
    }
    
    /**
     * AJAX handler for getting recent jobs
     */
    public function ajax_get_recent_jobs() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_admin_nonce')) {
            wp_die(esc_html('Invalid nonce'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html('Insufficient permissions'));
        }

        // Check premium access
        if (!explainer_can_use_premium_features()) {
            wp_send_json_error(array('message' => __('This feature is only available in the premium version.', 'ai-explainer')));
        }

        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('Job Queue Admin: AJAX get recent jobs called', array(), 'Job_Queue_Admin');
        }
        
        $recent_jobs = $this->get_recent_jobs(20);
        wp_send_json_success($recent_jobs);
    }
    
    /**
     * AJAX handler for running a job manually
     */
    public function ajax_run_job() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        // Check premium access
        if (!explainer_can_use_premium_features()) {
            wp_send_json_error(array('message' => __('This feature is only available in the premium version.', 'ai-explainer')));
        }

        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        $job_type = isset($_POST['job_type']) ? sanitize_text_field(wp_unslash($_POST['job_type'])) : '';

        if (empty($job_id) || empty($job_type)) {
            wp_send_json_error('Missing job ID or type');
        }
        
        // Remove 'jq_' prefix from job ID
        $numeric_job_id = intval(str_replace('jq_', '', $job_id));
        
        // Get job data from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $job = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE queue_id = %d", $numeric_job_id), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );
        
        if (!$job) {
            wp_send_json_error('Job not found');
        }
        
        if ($job['status'] !== 'pending') {
            wp_send_json_error('Job is not in pending status');
        }

        // Update job status to processing
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table requires direct query
        $wpdb->update(
            $table_name,
            array('status' => 'processing', 'started_at' => current_time('mysql')),
            array('queue_id' => $numeric_job_id),
            array('%s', '%s'),
            array('%d')
        );
        
        // Trigger async job execution via HTTP request
        $this->trigger_async_job_execution($numeric_job_id, $job_type);
        
        // Return job ID so frontend can poll for completion
        wp_send_json_success(array(
            'message' => 'Job started successfully and will process in background',
            'job_id' => $job_id,
            'numeric_job_id' => $numeric_job_id
        ));
    }
    
    /**
     * Execute job synchronously and return result for modal display
     */
    private function execute_job_synchronously($job_id, $job_type) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';

        // Get job data
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $job = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE queue_id = %d", $job_id), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );
        
        if (!$job) {
            return array('success' => false, 'message' => 'Job not found');
        }
        
        if ($job_type === 'blog_creation') {
            // Execute blog creation job synchronously
            if (ExplainerPlugin_Debug_Logger::is_enabled()) {
                ExplainerPlugin_Debug_Logger::debug('Executing blog creation job synchronously for job ID: ' . $job_id, 'job_queue');
            }
            $result = $this->execute_blog_creation_job($job);
            if (ExplainerPlugin_Debug_Logger::is_enabled()) {
                ExplainerPlugin_Debug_Logger::debug('Blog creation job result', 'job_queue', array('result' => $result));
            }

            if ($result) {
                if (ExplainerPlugin_Debug_Logger::is_enabled()) {
                    ExplainerPlugin_Debug_Logger::debug('Job completed successfully, getting post info...', 'job_queue');
                }
                // Get the created post information
                $post_info = $this->get_completed_blog_post_info($job_id, $job['created_by']);
                if (ExplainerPlugin_Debug_Logger::is_enabled()) {
                    ExplainerPlugin_Debug_Logger::debug('Post info retrieved', 'job_queue', array('post_info' => $post_info));
                }

                if ($post_info) {
                    return array(
                        'success' => true,
                        'data' => $post_info
                    );
                } else {
                    return array(
                        'success' => true,
                        'data' => array(
                            'post_title' => 'Blog Post Created',
                            'message' => 'Blog post created successfully'
                        )
                    );
                }
            } else {
                if (ExplainerPlugin_Debug_Logger::is_enabled()) {
                    ExplainerPlugin_Debug_Logger::error('Blog creation job returned false/failed', 'job_queue');
                }
                return array('success' => false, 'message' => 'Blog creation failed');
            }
        }
        
        return array('success' => false, 'message' => 'Unsupported job type');
    }
    
    /**
     * Execute a blog creation job
     * 
     * @param array $job Job data
     */
    private function execute_blog_creation_job($job) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';
        
        ExplainerPlugin_Debug_Logger::debug("Starting execute_blog_creation_job for job {$job['queue_id']}", 'Job_Queue_Admin');
        
        // Add timeout handling - jobs should complete within 5 minutes
        $start_time = time();
        $timeout_seconds = 300; // 5 minutes
        
        try {
            // Decode job data
            $job_data = maybe_unserialize($job['data']);
            if (!$job_data) {
                throw new Exception('Invalid job data');
            }
            
            ExplainerPlugin_Debug_Logger::debug("Job data decoded successfully for job {$job['queue_id']}", 'Job_Queue_Admin');
            
            // Get the blog creator instance
            if (!class_exists('ExplainerPlugin_Blog_Creator')) {
                require_once EXPLAINER_PLUGIN_PATH . 'includes/class-blog-creator.php';
            }
            
            $blog_creator = new ExplainerPlugin_Blog_Creator();
            ExplainerPlugin_Debug_Logger::debug("Blog creator instance created for job {$job['queue_id']}", 'Job_Queue_Admin');
            
            // Add job ID to job data for progress updates
            $job_data['job_id'] = $job['queue_id'];
            
            // Execute the job directly for immediate processing
            ExplainerPlugin_Debug_Logger::debug("Calling process_job_data for job {$job['queue_id']}", 'Job_Queue_Admin');
            $result = $blog_creator->process_job_data($job_data);
            
            // Check if we timed out
            if ((time() - $start_time) > $timeout_seconds) {
                throw new Exception('Job execution timed out after ' . $timeout_seconds . ' seconds');
            }
            
            ExplainerPlugin_Debug_Logger::debug("Blog creation completed successfully for job {$job['queue_id']}", 'Job_Queue_Admin');
            
            // Update job status to completed
            $update_result = $wpdb->update(
                $table_name,
                array(
                    'status' => 'completed',
                    'completed_at' => current_time('mysql')
                ),
                array('queue_id' => $job['queue_id']),
                array('%s', '%s'),
                array('%d')
            );
            
            if ($update_result === false) {
                ExplainerPlugin_Debug_Logger::error("Failed to update job {$job['queue_id']} status to completed", 'Job_Queue_Admin');
                throw new Exception('Failed to update job status to completed');
            }
            
            ExplainerPlugin_Debug_Logger::debug("Job {$job['queue_id']} marked as completed successfully", 'Job_Queue_Admin');
            
        } catch (Exception $e) {
            ExplainerPlugin_Debug_Logger::error("Job {$job['queue_id']} failed with error: " . $e->getMessage(), 'Job_Queue_Admin');
            
            // Update job status to failed
            $update_result = $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => current_time('mysql')
                ),
                array('queue_id' => $job['queue_id']),
                array('%s', '%s', '%s'),
                array('%d')
            );
            
            if ($update_result === false) {
                ExplainerPlugin_Debug_Logger::error("Failed to update job {$job['queue_id']} status to failed", 'Job_Queue_Admin');
            }
            
            throw $e; // Re-throw to be handled by the calling method
        }
    }
    
    /**
     * AJAX handler for getting individual job status
     */
    public function ajax_get_job_status() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        // Check premium access
        if (!explainer_can_use_premium_features()) {
            wp_send_json_error(array('message' => __('This feature is only available in the premium version.', 'ai-explainer')));
        }

        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        if (empty($job_id)) {
            wp_send_json_error('Missing job ID');
        }

        // Remove 'jq_' prefix from job ID
        $numeric_job_id = intval(str_replace('jq_', '', $job_id));


        // Get job status from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $job = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE queue_id = %d", $numeric_job_id), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );
        
        if (!$job) {
            wp_send_json_error('Job not found');
        }
        
        // Get job data for detailed progress
        $job_data = null;
        if ($job['data']) {
            $job_data = maybe_unserialize($job['data']);
        }
        
        // Format status data
        $status_data = array(
            'status' => $job['status'],
            'progress_percent' => $this->get_progress_percentage($job['status'], $job_data),
            'progress_text' => $this->get_progress_text($job['status'], $job_data),
            'error_message' => $job['error_message']
        );
        
        // Add post information for completed jobs based on job type
        if ($job['status'] === 'completed') {
            if ($job['job_type'] === 'blog_creation') {
                $post_info = $this->get_blog_post_info($job);
                if ($post_info) {
                    $status_data['post_info'] = $post_info;
                }
            } elseif ($job['job_type'] === 'ai_term_scan') {
                $post_info = $this->get_term_scan_info($job);
                if ($post_info) {
                    $status_data['post_info'] = $post_info;
                }
            }
        }
        
        wp_send_json_success($status_data);
    }
    
    /**
     * AJAX handler to get multiple job statuses
     */
    public function ajax_get_job_statuses() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        // Check premium access
        if (!explainer_can_use_premium_features()) {
            wp_send_json_error(array('message' => __('This feature is only available in the premium version.', 'ai-explainer')));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';

        // Sanitise progress_ids array
        $progress_ids = isset($_POST['progress_ids']) && is_array($_POST['progress_ids']) ? array_map('sanitize_text_field', wp_unslash($_POST['progress_ids'])) : array();
        $requested_progress_ids = array(); // Track requested IDs for deleted job detection

        // If no specific progress IDs provided, get all active jobs
        if (empty($progress_ids) || !is_array($progress_ids)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $jobs = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE status IN ('pending', 'processing') ORDER BY created_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    50
                ),
                ARRAY_A
            );
        } else {
            // Get specific jobs by progress IDs
            $job_ids = array();
            foreach ($progress_ids as $progress_id) {
                $progress_id = sanitize_text_field($progress_id);
                $requested_progress_ids[] = $progress_id;
                $numeric_job_id = intval(str_replace('jq_', '', $progress_id));
                if ($numeric_job_id > 0) {
                    $job_ids[] = $numeric_job_id;
                }
            }

            if (empty($job_ids)) {
                wp_send_json_error('No valid job IDs provided');
            }

            $placeholders = implode(',', array_fill(0, count($job_ids), '%d'));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $jobs = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE queue_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    ...$job_ids
                ),
                ARRAY_A
            );
        }
        
        $job_statuses = array();
        foreach ($jobs as $job) {
            // Get job data for detailed progress
            $job_data = null;
            if ($job['data']) {
                $job_data = maybe_unserialize($job['data']);
            }
            
            // Format status data for JavaScript
            $status_data = array(
                'id' => isset($job['queue_id']) ? $job['queue_id'] : (isset($job['id']) ? $job['id'] : 0),
                'status' => $job['status'],
                'progress_percent' => $this->get_progress_percentage($job['status'], $job_data),
                'progress_text' => $this->get_progress_text($job['status'], $job_data),
                'error_message' => $job['error_message']
            );
            
            // Add explanation data for blog creation jobs
            if ($job['job_type'] === 'blog_creation') {
                // Try to get explanation data from explanation_id first (best approach)
                $selection_text = '';
                $explanation = '';
                $explanation_id = $job['explanation_id'] ?? null;

                if ($explanation_id) {
                    // Fetch from explanations table using ID
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $explanation_data = $wpdb->get_row($wpdb->prepare(
                        "SELECT selected_text, ai_explanation FROM {$wpdb->prefix}ai_explainer_selections WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        $explanation_id
                    ));

                    if ($explanation_data) {
                        $selection_text = $explanation_data->selected_text;
                        $explanation = $explanation_data->ai_explanation;
                    }
                }
                
                // Fallback to stored data if explanation_id lookup failed
                if (empty($selection_text)) {
                    if (!empty($job['selection_text'])) {
                        // Use new column format
                        $selection_text = $job['selection_text'];
                        $options = maybe_unserialize($job['options']);
                        // Check if explanation is stored in options
                        if (is_array($options) && !empty($options['explanation'])) {
                            $explanation = $options['explanation'];
                        }
                    } else {
                        // Fall back to old serialized data format
                        $job_data_full = maybe_unserialize($job['data']);
                        if ($job_data_full) {
                            $selection_text = $job_data_full['selection_text'] ?? '';
                            // Check if explanation is in the job data
                            if (!empty($job_data_full['explanation'])) {
                                $explanation = $job_data_full['explanation'];
                            }
                        }
                    }
                    
                    // If we still don't have an explanation, try to fetch from selections table as fallback
                    if (empty($explanation) && !empty($selection_text)) {
                        $explanation = $this->get_ai_explanation_for_selection($selection_text);
                    }
                }
                
                // Add explanation data to status response
                if (!empty($selection_text)) {
                    $status_data['term'] = wp_trim_words($selection_text, 8, '...');
                    $status_data['explanation'] = $explanation ?: '';
                    $status_data['explanation_id'] = $explanation_id;
                }
            }
            
            // Add post information for completed jobs
            if ($job['status'] === 'completed') {
                if ($job['job_type'] === 'blog_creation') {
                    $post_info = $this->get_blog_post_info($job);
                    if ($post_info) {
                        $status_data['post_info'] = array(
                            'post_id' => $post_info['post_id'],
                            'title' => $post_info['post_title'],
                            'thumbnail' => $post_info['thumbnail'],
                            'edit_link' => $post_info['edit_link'],
                            'view_link' => $post_info['view_link'],
                            'job_type' => 'blog_creation'
                        );
                    }
                } elseif ($job['job_type'] === 'ai_term_scan') {
                    $post_info = $this->get_term_scan_info($job);
                    if ($post_info) {
                        $status_data['post_info'] = array(
                            'post_id' => $post_info['post_id'],
                            'post_title' => $post_info['post_title'],
                            'thumbnail' => $post_info['thumbnail'],
                            'edit_link' => $post_info['edit_link'],
                            'view_link' => $post_info['view_link'],
                            'job_type' => 'ai_term_scan'
                        );
                    }
                }
            }
            
            // Key by progress ID (jq_123 format) for JavaScript to access correctly
            $job_id = isset($job['queue_id']) ? $job['queue_id'] : (isset($job['id']) ? $job['id'] : 0);
            $progress_id = 'jq_' . $job_id;
            $job_statuses[$progress_id] = $status_data;
        }
        
        // Check for deleted jobs (only when specific progress IDs were requested)
        if (!empty($requested_progress_ids)) {
            $found_progress_ids = array_keys($job_statuses);
            foreach ($requested_progress_ids as $requested_id) {
                if (!in_array($requested_id, $found_progress_ids)) {
                    // Job was requested but not found - mark as deleted
                    $job_statuses[$requested_id] = array(
                        'id' => intval(str_replace('jq_', '', $requested_id)),
                        'status' => 'deleted',
                        'progress_percent' => 0,
                        'progress_text' => 'Job deleted',
                        'error_message' => ''
                    );
                }
            }
        }
        
        $response_data = $job_statuses;
        
        // Check if client requested new jobs - sanitise input
        $include_new_jobs = isset($_POST['include_new_jobs']) && sanitize_text_field(wp_unslash($_POST['include_new_jobs'])) === 'true';
        if ($include_new_jobs) {
            // Find jobs that are newer than any currently displayed
            $latest_displayed_date = null;
            if (!empty($progress_ids)) {
                // Get the most recent creation date from displayed jobs
                $job_ids = array();
                foreach ($progress_ids as $progress_id) {
                    $progress_id = sanitize_text_field($progress_id);
                    $numeric_job_id = intval(str_replace('jq_', '', $progress_id));
                    if ($numeric_job_id > 0) {
                        $job_ids[] = $numeric_job_id;
                    }
                }

                if (!empty($job_ids)) {
                    $placeholders = implode(',', array_fill(0, count($job_ids), '%d'));
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                    $latest_displayed_date = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT MAX(created_at) FROM {$table_name} WHERE queue_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                            ...$job_ids
                        )
                    );
                }
            }


            // Get new jobs that aren't currently displayed
            if ($latest_displayed_date) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $new_jobs = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$table_name} WHERE created_at > %s ORDER BY created_at DESC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        $latest_displayed_date
                    ),
                    ARRAY_A
                );
            } else {
                // If no displayed jobs, get the most recent jobs
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $new_jobs = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        10
                    ),
                    ARRAY_A
                );
                
                // Filter out jobs that are already displayed
                if (!empty($progress_ids)) {
                    $displayed_job_ids = array();
                    foreach ($progress_ids as $progress_id) {
                        $numeric_job_id = intval(str_replace('jq_', '', sanitize_text_field($progress_id)));
                        if ($numeric_job_id > 0) {
                            $displayed_job_ids[] = $numeric_job_id;
                        }
                    }
                    
                    $new_jobs = array_filter($new_jobs, function($job) use ($displayed_job_ids) {
                        $job_id = isset($job['queue_id']) ? $job['queue_id'] : (isset($job['id']) ? $job['id'] : 0);
                        return !in_array($job_id, $displayed_job_ids);
                    });
                }
            }
            
            if (!empty($new_jobs)) {
                // Format new jobs for the response
                $formatted_new_jobs = array();
                foreach ($new_jobs as $job) {
                    $job_data = array(
                        'queue_id' => $job['queue_id'],
                        'job_type' => $job['job_type'],
                        'status' => $job['status'],
                        'selection_text' => $job['selection_text'],
                        'created_at' => $job['created_at'],
                        'error_message' => $job['error_message']
                    );
                    
                    // For term scan jobs, include post information
                    if ($job['job_type'] === 'ai_term_scan') {
                        $post_id = null;
                        
                        // Try to get post_id from multiple sources
                        if (!empty($job['post_id'])) {
                            $post_id = (int) $job['post_id'];
                        } elseif (!empty($job['data'])) {
                            $job_data_decoded = maybe_unserialize($job['data']);
                            if (is_array($job_data_decoded) && isset($job_data_decoded['post_id'])) {
                                $post_id = (int) $job_data_decoded['post_id'];
                            }
                        }
                        
                        if ($post_id) {
                            $post = get_post($post_id);
                            if ($post) {
                                $job_data['post_id'] = $post_id;
                                $job_data['post_title'] = $post->post_title;
                            }
                        }
                    }
                    
                    $formatted_new_jobs[] = $job_data;
                }
                $response_data['new_jobs'] = $formatted_new_jobs;
            }
        }
        
        wp_send_json_success($response_data);
    }
    
    /**
     * AJAX handler for getting completed job post information
     */
    public function ajax_get_job_post_info() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        // Check premium access
        if (!explainer_can_use_premium_features()) {
            wp_send_json_error(array('message' => __('This feature is only available in the premium version.', 'ai-explainer')));
        }

        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        if (empty($job_id)) {
            wp_send_json_error('Missing job ID');
        }

        // Remove 'jq_' prefix from job ID
        $numeric_job_id = intval(str_replace('jq_', '', $job_id));

        // Get job from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $job = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE queue_id = %d", $numeric_job_id), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );

        if (!$job) {
            wp_send_json_error('Job not found');
        }

        if ($job['status'] !== 'completed') {
            wp_send_json_error('Job not completed yet');
        }
        
        // Get information based on job type
        if ($job['job_type'] === 'blog_creation') {
            $post_info = $this->get_blog_post_info($job);
            if ($post_info) {
                wp_send_json_success($post_info);
            } else {
                wp_send_json_error('Post information not found');
            }
        } elseif ($job['job_type'] === 'ai_term_scan') {
            $scan_info = $this->get_term_scan_info($job);
            if ($scan_info) {
                wp_send_json_success($scan_info);
            } else {
                wp_send_json_error('Term scan information not found');
            }
        } else {
            wp_send_json_error('Unsupported job type');
        }
    }

    /**
     * AJAX handler for async job execution
     */
    public function ajax_execute_job_async() {
        $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;

        // Verify nonce with job-specific nonce - sanitise nonce before verification
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_async_job_' . $job_id)) {
            wp_send_json_error('Invalid nonce');
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        // Check premium access
        if (!explainer_can_use_premium_features()) {
            wp_send_json_error(array('message' => __('This feature is only available in the premium version.', 'ai-explainer')));
        }

        $job_type = isset($_POST['job_type']) ? sanitize_text_field(wp_unslash($_POST['job_type'])) : '';

        if (!$job_id || !$job_type) {
            wp_send_json_error('Missing job parameters');
        }

        // Execute the job based on type
        try {
            if ($job_type === 'blog_creation') {
                $blog_creator = new ExplainerPlugin_Blog_Creator();
                $result = $blog_creator->process_job($job_id);
            } else {
                wp_send_json_error('Unknown job type: ' . $job_type);
                return;
            }

            // Mark job as completed
            $this->update_job_status($job_id, 'completed', $result);
            wp_send_json_success(array('message' => 'Job completed successfully', 'result' => $result));

        } catch (Exception $e) {
            // Mark job as failed
            $this->update_job_status($job_id, 'failed', null, $e->getMessage());
            wp_send_json_error('Job failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get progress percentage for job status
     * 
     * @param string $status Job status
     * @param array $job_data Job data for detailed progress
     * @return int Progress percentage
     */
    private function get_progress_percentage($status, $job_data = null) {
        switch ($status) {
            case 'pending':
                return 0;
            case 'processing':
                // Check for detailed progress information
                if ($job_data && isset($job_data['progress_stage'])) {
                    switch ($job_data['progress_stage']) {
                        case 'initialising':
                            return 10;
                        case 'analysing_selection':
                            return 20;
                        case 'generating_content':
                            return 40;
                        case 'processing_images':
                            return 60;
                        case 'generating_seo':
                            return 70;
                        case 'creating_post':
                            return 85;
                        case 'finalising':
                            return 95;
                        default:
                            return 50;
                    }
                }
                return 50;
            case 'completed':
                return 100;
            case 'failed':
                return 0;
            default:
                return 0;
        }
    }
    
    /**
     * Get progress text for job status
     * 
     * @param string $status Job status
     * @param array $job_data Job data for detailed progress
     * @return string Progress text
     */
    private function get_progress_text($status, $job_data = null) {
        switch ($status) {
            case 'pending':
                return __('Waiting to start...', 'ai-explainer');
            case 'processing':
                // Check for detailed progress information
                if ($job_data && isset($job_data['progress_stage'])) {
                    switch ($job_data['progress_stage']) {
                        case 'initialising':
                            return __('Initialising content generation...', 'ai-explainer');
                        case 'analysing_selection':
                            return __('Analysing selected text...', 'ai-explainer');
                        case 'generating_content':
                            return __('Generating blog post content...', 'ai-explainer');
                        case 'processing_images':
                            return __('Processing featured image...', 'ai-explainer');
                        case 'generating_seo':
                            return __('Generating SEO metadata...', 'ai-explainer');
                        case 'creating_post':
                            return __('Creating WordPress post...', 'ai-explainer');
                        case 'finalising':
                            return __('Finalising blog post...', 'ai-explainer');
                        default:
                            return __('Creating blog post...', 'ai-explainer');
                    }
                }
                return __('Creating blog post...', 'ai-explainer');
            case 'completed':
                return __('Blog post created successfully', 'ai-explainer');
            case 'failed':
                return __('Job failed', 'ai-explainer');
            default:
                return __('Unknown status', 'ai-explainer');
        }
    }
    
    /**
     * Trigger async job execution via HTTP request
     * 
     * @param int $job_id Job ID
     * @param string $job_type Job type
     */
    private function trigger_async_job_execution($job_id, $job_type) {
        ExplainerPlugin_Debug_Logger::debug("Starting synchronous execution for job {$job_id} type {$job_type}", 'Job_Queue_Admin');
        
        // Execute the job synchronously for reliable completion
        try {
            switch ($job_type) {
                case 'blog_creation':
                    $blog_creator = new ExplainerPlugin_Blog_Creator();
                    $result = $blog_creator->process_job($job_id);
                    
                    // Mark job as completed
                    $this->update_job_status($job_id, 'completed', $result);
                    ExplainerPlugin_Debug_Logger::debug("Job {$job_id} completed successfully", 'Job_Queue_Admin');
                    break;
                    
                case 'ai_term_scan':
                    // Load the post scan widget class
                    if (!class_exists('\WPAIExplainer\JobQueue\ExplainerPlugin_Post_Scan_Widget')) {
                        require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/widgets/class-post-scan-widget.php';
                    }

                    $post_scan_widget = new \WPAIExplainer\JobQueue\ExplainerPlugin_Post_Scan_Widget();
                    $result = $post_scan_widget->process_job($job_id);
                    
                    // Mark job as completed
                    $this->update_job_status($job_id, 'completed', $result);
                    ExplainerPlugin_Debug_Logger::debug("Term scan job {$job_id} completed successfully", 'Job_Queue_Admin');
                    break;
                    
                default:
                    throw new Exception('Unsupported job type: ' . $job_type);
            }
        } catch (Exception $e) {
            // Mark job as failed
            $this->update_job_status($job_id, 'failed', null, $e->getMessage());
            ExplainerPlugin_Debug_Logger::error("Job {$job_id} failed: " . $e->getMessage(), 'Job_Queue_Admin');
        }
    }

    /**
     * Update job status in database
     * 
     * @param int $job_id Job ID
     * @param string $status Job status
     * @param mixed $result Job result data
     * @param string $error_message Error message if failed
     */
    private function update_job_status($job_id, $status, $result = null, $error_message = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';

        $update_data = array(
            'status' => $status,
            'completed_at' => current_time('mysql')
        );

        if ($error_message) {
            $update_data['error_message'] = $error_message;
        }

        if ($result && $status === 'completed') {
            // Store result data
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $current_data = $wpdb->get_var(
                $wpdb->prepare("SELECT data FROM {$table_name} WHERE queue_id = %d", $job_id) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );
            
            $job_data = $current_data ? maybe_unserialize($current_data) : array();
            if (!is_array($job_data)) {
                $job_data = array();
            }
            
            $job_data['result'] = $result;
            $update_data['data'] = maybe_serialize($job_data);
            
            // Extract post_id from result and store in post_id column for fast access
            $post_id = null;
            if (isset($result['post_id'])) {
                $post_id = (int) $result['post_id'];
            } elseif (isset($result['post']['ID'])) {
                $post_id = (int) $result['post']['ID'];
            } elseif (isset($job_data['post_id'])) {
                $post_id = (int) $job_data['post_id'];
            }
            
            if ($post_id) {
                $update_data['post_id'] = $post_id;
                ExplainerPlugin_Debug_Logger::debug("Storing post_id {$post_id} for completed job {$job_id}", 'Job_Queue_Admin');
            }
        }
        
        $update_result = $wpdb->update(
            $table_name,
            $update_data,
            array('queue_id' => $job_id),
            array_fill(0, count($update_data), '%s'),
            array('%d')
        );
        
        if ($update_result === false) {
            ExplainerPlugin_Debug_Logger::error("Failed to update job {$job_id} status to {$status}", 'Job_Queue_Admin');
        } else {
            ExplainerPlugin_Debug_Logger::debug("Updated job {$job_id} status to {$status}", 'Job_Queue_Admin');
        }
    }

    /**
     * Execute job asynchronously (called via WordPress cron)
     *
     * @param int $job_id Job ID
     * @param string $job_type Job type
     */
    public function execute_job_async($job_id, $job_type) {
        ExplainerPlugin_Debug_Logger::debug("Starting async job execution for job {$job_id} type {$job_type}", 'Job_Queue_Admin');

        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';

        // Get job data from database
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $job = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE queue_id = %d", $job_id), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );
        
        if (!$job) {
            ExplainerPlugin_Debug_Logger::error("Job {$job_id} not found for async execution", 'Job_Queue_Admin');
            return;
        }
        
        try {
            switch ($job_type) {
                case 'blog_creation':
                    $this->execute_blog_creation_job($job);
                    break;
                    
                default:
                    throw new Exception('Unsupported job type: ' . $job_type);
            }
            
            ExplainerPlugin_Debug_Logger::debug("Async job {$job_id} completed successfully", 'Job_Queue_Admin');
            
        } catch (Exception $e) {
            ExplainerPlugin_Debug_Logger::error("Async job {$job_id} failed: " . $e->getMessage(), 'Job_Queue_Admin');
            
            // Update job status to failed
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => current_time('mysql')
                ),
                array('queue_id' => $job_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
        }
    }
    
    /**
     * Process blog creation job in background
     * 
     * @param int $job_id Job ID
     * @param array $job_data Job data
     */
    public function process_blog_creation_background($job_id, $job_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_job_queue';
        
        try {
            // Get the blog creator instance
            if (!class_exists('ExplainerPlugin_Blog_Creator')) {
                require_once EXPLAINER_PLUGIN_PATH . 'includes/class-blog-creator.php';
            }
            
            $blog_creator = new ExplainerPlugin_Blog_Creator();
            
            // Add job ID to job data for progress updates
            $job_data['job_id'] = $job_id;
            
            // Process the job
            $result = $blog_creator->process_job_data($job_data);
            
            // Update job status to completed
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'completed',
                    'completed_at' => current_time('mysql')
                ),
                array('queue_id' => $job_id),
                array('%s', '%s'),
                array('%d')
            );
            
            ExplainerPlugin_Debug_Logger::debug("Background job {$job_id} completed successfully", 'Job_Queue_Admin');
            
        } catch (Exception $e) {
            // Update job status to failed
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => current_time('mysql')
                ),
                array('queue_id' => $job_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            
            ExplainerPlugin_Debug_Logger::error("Background job {$job_id} failed: " . $e->getMessage(), 'Job_Queue_Admin');
        }
    }
    
    /**
     * Get post ID by job queue ID using post meta (failsafe lookup)
     * 
     * @param int $job_queue_id The job queue ID to search for
     * @return int|null The post ID if found, null otherwise
     */
    private function get_post_id_by_job_queue_id($job_queue_id) {
        global $wpdb;
        
        // Query posts with the job queue ID stored in meta
        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_wp_ai_explainer_job_queue_id' 
                 AND meta_value = %d 
                 LIMIT 1",
                $job_queue_id
            )
        );
        
        return $post_id ? (int) $post_id : null;
    }
    
    /**
     * Process pending jobs via cron
     */
    public function process_pending_jobs_cron() {
        $manager = ExplainerPlugin_Job_Queue_Manager::get_instance();
        if ($manager) {
            // Process up to 5 pending jobs
            $result = $manager->process_queue('blog_creation', 5);
            
            if (function_exists('explainer_log_debug')) {
                explainer_log_debug('Cron job processing completed', array(
                    'result' => $result
                ), 'Job_Queue_Admin');
            }
        }
    }
}
