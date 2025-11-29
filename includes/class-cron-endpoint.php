<?php
/**
 * Simple Cron Endpoint Handler
 * 
 * Handles external cron requests for job processing when server cron is enabled.
 * Works with the simplified job queue architecture.
 */

if (!defined('ABSPATH')) {
    exit;
}

class ExplainerPlugin_Cron_Endpoint {
    
    /**
     * @var ExplainerPlugin_Cron_Endpoint
     */
    private static $instance;
    
    /**
     * Get singleton instance
     * 
     * @return ExplainerPlugin_Cron_Endpoint
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
        add_action('init', array($this, 'handle_cron_request'));
    }
    
    /**
     * Handle external cron endpoint requests
     */
    public function handle_cron_request() {
        // Check if this is a cron request
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- External cron endpoint uses token-based authentication
        if (!isset($_GET['explainer_cron']) || $_GET['explainer_cron'] !== 'run') {
            return;
        }

        // Prevent multiple cron instances running simultaneously
        $lock_key = 'explainer_cron_processing_lock';
        $lock_timeout = 120; // 2 minutes

        if (get_transient($lock_key)) {
            $this->send_response(array(
                'success' => false,
                'error' => 'Another cron process is already running',
                'processed' => 0,
                'timestamp' => time()
            ), 429);
            return;
        }

        // Acquire processing lock
        set_transient($lock_key, time(), $lock_timeout);

        try {
            // Verify server cron is enabled
            if (!get_option('explainer_enable_cron', false)) {
                $this->send_response(array(
                    'success' => false,
                    'error' => 'Server cron is disabled. Use WordPress cron instead.'
                ), 400);
                return;
            }

            // Enhanced security token check
            $expected_token = get_option('explainer_cron_token', '');
            if (!empty($expected_token)) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token-based auth replaces nonces for external cron
                $provided_token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
                if ($provided_token !== $expected_token) {
                    $this->send_response(array(
                        'success' => false,
                        'error' => 'Invalid security token'
                    ), 403);
                    return;
                }
            }
            
            // Process pending jobs using simplified job queue manager
            $processed_count = $this->process_pending_jobs();
            
            $this->send_response(array(
                'success' => true,
                'processed' => $processed_count,
                'timestamp' => time(),
                'mode' => 'server_cron'
            ));
            
        } catch (Exception $e) {
            if (ExplainerPlugin_Debug_Logger::is_enabled()) {
                ExplainerPlugin_Debug_Logger::error('Explainer Cron Error: ' . $e->getMessage(), 'cron');
            }

            $this->send_response(array(
                'success' => false,
                'error' => 'Processing error: ' . $e->getMessage(),
                'processed' => 0,
                'timestamp' => time()
            ), 500);
        } finally {
            // Always release the lock
            delete_transient($lock_key);
        }
    }
    
    /**
     * Process pending jobs using the simplified job queue manager
     * 
     * @return int Number of jobs processed
     */
    private function process_pending_jobs() {
        try {
            // Get job queue manager instance
            $manager = ExplainerPlugin_Job_Queue_Manager::get_instance();
            
            $processed_count = 0;
            $max_jobs_per_run = 10; // Prevent runaway processing
            
            // Process pending jobs one at a time
            for ($i = 0; $i < $max_jobs_per_run; $i++) {
                $job = $manager->get_next_pending_job();
                
                if (!$job) {
                    // No more pending jobs
                    break;
                }
                
                if ($this->process_single_job($manager, $job->queue_id)) {
                    $processed_count++;
                } else {
                    // If job processing fails, break to avoid infinite retry
                    break;
                }
            }
            
            return $processed_count;
            
        } catch (Exception $e) {
            if (ExplainerPlugin_Debug_Logger::is_enabled()) {
                ExplainerPlugin_Debug_Logger::error('Explainer Cron: Error processing jobs - ' . $e->getMessage(), 'cron');
            }
            throw $e;
        }
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
                return false;
            }
            
        } catch (Exception $e) {
            if (ExplainerPlugin_Debug_Logger::is_enabled()) {
                ExplainerPlugin_Debug_Logger::error("Explainer Cron: Exception processing job $job_id - " . $e->getMessage(), 'cron');
            }
            return false;
        }
    }
    
    /**
     * Send JSON response and exit
     * 
     * @param array $data Response data
     * @param int $status_code HTTP status code
     */
    private function send_response($data, $status_code = 200) {
        status_header($status_code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Get cron endpoint URL
     * 
     * @param string $token Security token
     * @return string
     */
    public static function get_cron_url($token = '') {
        $url = home_url('/?explainer_cron=run');
        
        if (!empty($token)) {
            $url = add_query_arg('token', $token, $url);
        }
        
        return $url;
    }
}