<?php
/**
 * Webhook Test Admin Interface
 * 
 * Admin interface for running webhook system tests
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Webhook Test Admin Class
 * 
 * Provides admin interface for webhook system testing.
 */
class ExplainerPlugin_Webhook_Test_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'handle_test_actions'));
    }
    
    /**
     * Handle test action requests
     */
    public function handle_test_actions() {
        // Only allow administrators to run tests
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if test action was requested
        if (isset($_POST['run_webhook_tests']) && isset($_POST['webhook_test_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['webhook_test_nonce'])), 'run_webhook_tests')) {
            $this->run_webhook_tests();
        }
    }
    
    /**
     * Run webhook tests and store results
     */
    private function run_webhook_tests() {
        try {
            // Load test suite
            require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-webhook-test-suite.php';
            
            $test_suite = new ExplainerPlugin_Webhook_Test_Suite();
            $results = $test_suite->run_all_tests();
            
            // Store results in transient for display
            set_transient('explainer_webhook_test_results', $results, 3600); // 1 hour
            
            // Add admin notice
            add_action('admin_notices', array($this, 'display_test_results_notice'));
            
        } catch (Exception $e) {
            $error_results = array(
                'summary' => array(
                    'total_tests' => 0,
                    'passed_tests' => 0,
                    'failed_tests' => 1,
                    'success_rate' => 0,
                    'overall_status' => 'FAILURE'
                ),
                'results' => array(
                    array(
                        'test' => 'test_suite_execution',
                        'passed' => false,
                        'description' => 'Test suite execution failed',
                        'data' => array('error' => $e->getMessage()),
                        'timestamp' => current_time('mysql')
                    )
                )
            );
            
            set_transient('explainer_webhook_test_results', $error_results, 3600);
            add_action('admin_notices', array($this, 'display_test_results_notice'));
        }
    }
    
    /**
     * Display test results notice
     */
    public function display_test_results_notice() {
        $results = get_transient('explainer_webhook_test_results');
        
        if (!$results) {
            return;
        }
        
        $summary = $results['summary'];
        $overall_status = $summary['overall_status'];
        $notice_type = $overall_status === 'SUCCESS' ? 'success' : 'error';
        
        echo '<div class="notice notice-' . esc_attr($notice_type) . ' is-dismissible">';
        echo '<h3>Webhook System Test Results</h3>';
        echo '<p><strong>Overall Status:</strong> ' . esc_html($overall_status) . '</p>';
        echo '<p>';
        echo '<strong>Total Tests:</strong> ' . intval($summary['total_tests']) . ' | ';
        echo '<strong>Passed:</strong> ' . intval($summary['passed_tests']) . ' | ';
        echo '<strong>Failed:</strong> ' . intval($summary['failed_tests']) . ' | ';
        echo '<strong>Success Rate:</strong> ' . floatval($summary['success_rate']) . '%';
        echo '</p>';
        
        // Show failed tests if any
        if ($summary['failed_tests'] > 0) {
            echo '<h4>Failed Tests:</h4>';
            echo '<ul>';
            foreach ($results['results'] as $result) {
                if (!$result['passed']) {
                    echo '<li><strong>' . esc_html($result['test']) . ':</strong> ' . esc_html($result['description']);
                    if (!empty($result['data'])) {
                        echo ' <code>' . esc_html(wp_json_encode($result['data'])) . '</code>';
                    }
                    echo '</li>';
                }
            }
            echo '</ul>';
        }
        
        echo '</div>';
        
        // Clear the transient after displaying
        delete_transient('explainer_webhook_test_results');
    }
    
    /**
     * Render test interface in admin settings
     */
    public function render_test_interface() {
        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="webhook-test-section">
            <h3><?php esc_html_e('Webhook System Testing', 'ai-explainer'); ?></h3>
            
            <p><?php esc_html_e('Test the webhook infrastructure to ensure all components are working correctly.', 'ai-explainer'); ?></p>
            
            <form method="post" action="">
                <?php wp_nonce_field('run_webhook_tests', 'webhook_test_nonce'); ?>
                <input type="submit" name="run_webhook_tests" class="btn-base btn-primary" 
                       value="<?php esc_attr_e('Run Webhook Tests', 'ai-explainer'); ?>" />
            </form>
            
            <div class="webhook-test-info">
                <h4><?php esc_html_e('Test Coverage', 'ai-explainer'); ?></h4>
                <ul>
                    <li><?php esc_html_e('Event Payload Builder functionality', 'ai-explainer'); ?></li>
                    <li><?php esc_html_e('Webhook Emitter operations', 'ai-explainer'); ?></li>
                    <li><?php esc_html_e('Configuration system access', 'ai-explainer'); ?></li>
                    <li><?php esc_html_e('Integration points and dependencies', 'ai-explainer'); ?></li>
                    <li><?php esc_html_e('Performance constraint validation', 'ai-explainer'); ?></li>
                    <li><?php esc_html_e('Error handling and recovery', 'ai-explainer'); ?></li>
                    <li><?php esc_html_e('Security filtering and data protection', 'ai-explainer'); ?></li>
                </ul>
            </div>
            
            <div class="webhook-system-status">
                <h4><?php esc_html_e('System Status', 'ai-explainer'); ?></h4>
                <?php $this->render_system_status(); ?>
            </div>
        </div>
        
        <style>
        .webhook-test-section {
            margin-top: 20px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .webhook-test-info ul {
            list-style-type: disc;
            margin-left: 20px;
        }
        
        .webhook-system-status {
            margin-top: 15px;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            margin: 5px 0;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-indicator.enabled {
            background-color: #46b450;
        }
        
        .status-indicator.disabled {
            background-color: #dc3232;
        }
        
        .status-indicator.warning {
            background-color: #ffb900;
        }
        </style>
        <?php
    }
    
    /**
     * Render webhook system status
     */
    private function render_system_status() {
        $webhook_enabled = ExplainerPlugin_Config::is_feature_enabled('webhook_system', true);
        $realtime_enabled = ExplainerPlugin_Config::get_realtime_setting('webhook_enabled', true);
        
        // Check if classes are loaded
        $emitter_available = class_exists('ExplainerPlugin_Webhook_Emitter');
        $payload_available = class_exists('ExplainerPlugin_Event_Payload');
        
        ?>
        <div class="status-item">
            <div class="status-indicator <?php echo $webhook_enabled ? 'enabled' : 'disabled'; ?>"></div>
            <span><?php esc_html_e('Webhook System Feature', 'ai-explainer'); ?>: 
                  <strong><?php echo $webhook_enabled ? esc_html__('Enabled', 'ai-explainer') : esc_html__('Disabled', 'ai-explainer'); ?></strong>
            </span>
        </div>
        
        <div class="status-item">
            <div class="status-indicator <?php echo $realtime_enabled ? 'enabled' : 'disabled'; ?>"></div>
            <span><?php esc_html_e('Real-time Events', 'ai-explainer'); ?>: 
                  <strong><?php echo $realtime_enabled ? esc_html__('Enabled', 'ai-explainer') : esc_html__('Disabled', 'ai-explainer'); ?></strong>
            </span>
        </div>
        
        <div class="status-item">
            <div class="status-indicator <?php echo $emitter_available ? 'enabled' : 'disabled'; ?>"></div>
            <span><?php esc_html_e('Webhook Emitter', 'ai-explainer'); ?>: 
                  <strong><?php echo $emitter_available ? esc_html__('Available', 'ai-explainer') : esc_html__('Not Available', 'ai-explainer'); ?></strong>
            </span>
        </div>
        
        <div class="status-item">
            <div class="status-indicator <?php echo $payload_available ? 'enabled' : 'disabled'; ?>"></div>
            <span><?php esc_html_e('Event Payload Builder', 'ai-explainer'); ?>: 
                  <strong><?php echo $payload_available ? esc_html__('Available', 'ai-explainer') : esc_html__('Not Available', 'ai-explainer'); ?></strong>
            </span>
        </div>
        
        <?php
        // Show performance metrics if webhook emitter is available
        if ($emitter_available && $webhook_enabled) {
            try {
                $webhook_emitter = ExplainerPlugin_Webhook_Emitter::get_instance();
                $performance_status = $webhook_emitter->get_performance_status();
                
                ?>
                <div class="status-item">
                    <div class="status-indicator <?php echo $performance_status['time_status'] === 'good' ? 'enabled' : 'warning'; ?>"></div>
                    <span><?php esc_html_e('Average Processing Time', 'ai-explainer'); ?>: 
                          <strong><?php echo esc_html($performance_status['avg_time_ms']); ?>ms</strong>
                    </span>
                </div>
                
                <div class="status-item">
                    <div class="status-indicator <?php echo $performance_status['memory_status'] === 'good' ? 'enabled' : 'warning'; ?>"></div>
                    <span><?php esc_html_e('Average Memory Usage', 'ai-explainer'); ?>: 
                          <strong><?php echo esc_html($performance_status['avg_memory_mb']); ?>MB</strong>
                    </span>
                </div>
                
                <div class="status-item">
                    <div class="status-indicator enabled"></div>
                    <span><?php esc_html_e('Total Webhook Emissions', 'ai-explainer'); ?>: 
                          <strong><?php echo intval($performance_status['total_emissions']); ?></strong>
                    </span>
                </div>
                <?php
                
            } catch (Exception $e) {
                ?>
                <div class="status-item">
                    <div class="status-indicator warning"></div>
                    <span><?php esc_html_e('Performance Metrics', 'ai-explainer'); ?>: 
                          <strong><?php esc_html_e('Error retrieving data', 'ai-explainer'); ?></strong>
                    </span>
                </div>
                <?php
            }
        }
    }
}