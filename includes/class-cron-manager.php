<?php
/**
 * Cron Management System
 * 
 * Manages the switching between WordPress cron and external server cron
 * based on the explainer_enable_cron setting.
 */

if (!defined('ABSPATH')) {
    exit;
}

class ExplainerPlugin_Cron_Manager {
    
    /**
     * @var ExplainerPlugin_Cron_Manager
     */
    private static $instance;
    
    /**
     * WordPress cron hook name for job processing
     */
    const WP_CRON_HOOK = 'explainer_process_jobs';
    
    /**
     * Get singleton instance
     * 
     * @return ExplainerPlugin_Cron_Manager
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Hook to monitor cron setting changes
        add_action('update_option_explainer_enable_cron', array($this, 'handle_cron_setting_change'), 10, 2);
        
        // Register WordPress cron hook
        add_action(self::WP_CRON_HOOK, array($this, 'process_jobs_via_wp_cron'));
        
        // Initialize cron system on plugin load
        add_action('init', array($this, 'initialize_cron_system'));
    }
    
    /**
     * Initialize the cron system based on current settings
     */
    public function initialize_cron_system() {
        $cron_enabled = get_option('explainer_enable_cron', false);
        
        if ($cron_enabled) {
            $this->enable_server_cron();
        } else {
            $this->enable_wp_cron();
        }
    }
    
    /**
     * Handle cron setting changes
     * 
     * @param mixed $old_value
     * @param mixed $new_value
     */
    public function handle_cron_setting_change($old_value, $new_value) {
        if ($new_value) {
            // Server cron enabled - disable WordPress cron
            $this->enable_server_cron();
        } else {
            // Server cron disabled - enable WordPress cron
            $this->enable_wp_cron();
        }
    }
    
    /**
     * Enable server cron mode
     * - Disables WordPress cron processing for plugin jobs
     * - Clears any existing WordPress cron schedules
     */
    private function enable_server_cron() {
        // Clear any existing WordPress cron schedules
        $this->clear_wp_cron_schedules();
        
        explainer_log_info('Cron Manager: Server cron enabled - WordPress cron disabled for plugin jobs', array(), 'CronManager');
    }
    
    /**
     * Enable WordPress cron mode
     * - Enables WordPress cron processing for plugin jobs
     * - Schedules recurring job processing
     */
    private function enable_wp_cron() {
        // Clear any existing schedules first
        $this->clear_wp_cron_schedules();
        
        // Schedule recurring job processing every 5 minutes
        if (!wp_next_scheduled(self::WP_CRON_HOOK)) {
            wp_schedule_event(time(), 'explainer_5min', self::WP_CRON_HOOK);
        }
        
        explainer_log_info('Cron Manager: WordPress cron enabled for plugin jobs', array(), 'CronManager');
    }
    
    /**
     * Clear all WordPress cron schedules for this plugin
     */
    private function clear_wp_cron_schedules() {
        $timestamp = wp_next_scheduled(self::WP_CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::WP_CRON_HOOK);
        }
        
        // Clear any other instances
        wp_clear_scheduled_hook(self::WP_CRON_HOOK);
    }
    
    /**
     * Process jobs via WordPress cron
     * This runs when WordPress cron is enabled
     */
    public function process_jobs_via_wp_cron() {
        // Only process if server cron is disabled
        if (get_option('explainer_enable_cron', false)) {
            explainer_log_info('Cron Manager: Skipping WordPress cron - server cron enabled', array(), 'CronManager');
            return;
        }
        
        try {
            explainer_log_info('Cron Manager: Processing jobs via WordPress cron', array(), 'CronManager');
            
            // Get job queue manager instance
            $manager = ExplainerPlugin_Job_Queue_Manager::get_instance();
            
            // Process pending jobs
            $processed_count = $this->process_pending_jobs($manager);
            
            explainer_log_info('Cron Manager: WordPress cron processed jobs', array(
                'processed_count' => $processed_count
            ), 'CronManager');
            
        } catch (Exception $e) {
            explainer_log_error('Cron Manager: WordPress cron processing error', array(
                'error' => $e->getMessage()
            ), 'CronManager');
        }
    }
    
    /**
     * Process pending jobs
     * 
     * @param ExplainerPlugin_Job_Queue_Manager $manager
     * @return int Number of jobs processed
     */
    private function process_pending_jobs($manager) {
        $processed_count = 0;
        
        // Get pending jobs for both job types
        $blog_jobs = $manager->get_queue_status('blog_creation');
        $scan_jobs = $manager->get_queue_status('post_scan');
        
        // Process blog creation jobs
        if (!empty($blog_jobs['pending'])) {
            foreach ($blog_jobs['pending'] as $job) {
                if ($this->process_single_job($manager, $job['id'])) {
                    $processed_count++;
                }
                
                // Limit processing to prevent timeouts
                if ($processed_count >= 5) {
                    break;
                }
            }
        }
        
        // Process post scan jobs (if we haven't hit the limit)
        if ($processed_count < 5 && !empty($scan_jobs['pending'])) {
            foreach ($scan_jobs['pending'] as $job) {
                if ($this->process_single_job($manager, $job['id'])) {
                    $processed_count++;
                }
                
                // Limit processing to prevent timeouts
                if ($processed_count >= 5) {
                    break;
                }
            }
        }
        
        return $processed_count;
    }
    
    /**
     * Process a single job
     * 
     * @param ExplainerPlugin_Job_Queue_Manager $manager
     * @param int $job_id
     * @return bool Success status
     */
    private function process_single_job($manager, $job_id) {
        try {
            $result = $manager->execute_job_directly($job_id);
            
            if ($result && $result['success']) {
                return true;
            } else {
                $error_msg = isset($result['message']) ? $result['message'] : 'Unknown error';
                explainer_log_error("Cron Manager: Job $job_id failed", array(
                    'error' => $error_msg
                ), 'CronManager');
                return false;
            }
            
        } catch (Exception $e) {
            explainer_log_error("Cron Manager: Exception processing job $job_id", array(
                'error' => $e->getMessage()
            ), 'CronManager');
            return false;
        }
    }
    
    /**
     * Get the appropriate cron URL based on current settings
     * 
     * @return array Cron information
     */
    public function get_cron_info() {
        $cron_enabled = get_option('explainer_enable_cron', false);
        
        if ($cron_enabled) {
            // Server cron mode
            $token = get_option('explainer_cron_token', '');
            if (empty($token)) {
                $token = wp_generate_password(32, false);
                update_option('explainer_cron_token', $token);
            }
            
            return array(
                'mode' => 'server',
                'url' => ExplainerPlugin_Cron_Endpoint::get_cron_url($token),
                'command' => '*/5 * * * * curl -s "' . ExplainerPlugin_Cron_Endpoint::get_cron_url($token) . '" > /dev/null 2>&1',
                'description' => 'External server cron enabled. Use the command above in your server crontab.'
            );
        } else {
            // WordPress cron mode
            return array(
                'mode' => 'wordpress',
                'url' => home_url('/wp-cron.php'),
                'command' => null,
                'description' => 'WordPress cron enabled. Jobs will be processed automatically when visitors access your site.'
            );
        }
    }
    
    /**
     * Add custom cron schedule for 5-minute intervals
     * 
     * @param array $schedules
     * @return array
     */
    public static function add_cron_schedules($schedules) {
        $schedules['explainer_5min'] = array(
            'interval' => 300, // 5 minutes in seconds
            'display' => __('Every 5 Minutes', 'ai-explainer')
        );
        
        return $schedules;
    }
}

// Add custom cron schedule
add_filter('cron_schedules', array('ExplainerPlugin_Cron_Manager', 'add_cron_schedules'));