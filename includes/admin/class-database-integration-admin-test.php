<?php
/**
 * Database Integration Admin Test Interface
 * 
 * Provides admin interface for running and viewing database integration tests.
 * 
 * @package WP_AI_Explainer
 * @subpackage Admin
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Integration Admin Test Class
 * 
 * Handles the admin interface for database integration testing.
 */
class ExplainerPlugin_Database_Integration_Admin_Test {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_explainer_run_database_integration_tests', array($this, 'ajax_run_tests'));
        add_action('wp_ajax_explainer_get_database_integration_test_results', array($this, 'ajax_get_test_results'));
    }
    
    /**
     * AJAX handler for running database integration tests
     * 
     * @return void
     */
    public function ajax_run_tests() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'explainer_admin_nonce')) {
            wp_die(esc_html('Security check failed'));
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html('Insufficient permissions'));
        }
        
        try {
            // Check if test suite is available
            if (!class_exists('ExplainerPlugin_Database_Integration_Test_Suite')) {
                wp_send_json_error('Database integration test suite not available');
                return;
            }
            
            // Run the tests
            $test_suite = new ExplainerPlugin_Database_Integration_Test_Suite();
            $results = $test_suite->run_all_tests();
            
            // Store results in transient for later retrieval
            $transient_key = 'explainer_db_integration_test_results_' . get_current_user_id();
            set_transient($transient_key, $results, HOUR_IN_SECONDS);
            
            wp_send_json_success(array(
                'message' => 'Database integration tests completed',
                'results' => $results,
                'transient_key' => $transient_key
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Test execution failed',
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX handler for getting test results
     * 
     * @return void
     */
    public function ajax_get_test_results() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'explainer_admin_nonce')) {
            wp_die(esc_html('Security check failed'));
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html('Insufficient permissions'));
        }

        $transient_key = isset($_POST['transient_key']) ? sanitize_text_field(wp_unslash($_POST['transient_key'])) : '';
        
        if (empty($transient_key)) {
            wp_send_json_error('No transient key provided');
            return;
        }
        
        $results = get_transient($transient_key);
        
        if ($results === false) {
            wp_send_json_error('Test results not found or expired');
            return;
        }
        
        wp_send_json_success(array(
            'results' => $results,
            'formatted_results' => $this->format_test_results($results)
        ));
    }
    
    /**
     * Format test results for display
     * 
     * @param array $results Raw test results
     * @return string Formatted HTML
     */
    private function format_test_results($results) {
        if (empty($results)) {
            return '<div class="notice notice-warning"><p>No test results available.</p></div>';
        }
        
        $html = '<div class="explainer-test-results">';
        
        // Summary section
        if (isset($results['summary'])) {
            $summary = $results['summary'];
            $status_class = $summary['failed'] > 0 ? 'notice-error' : 
                           ($summary['warnings'] > 0 ? 'notice-warning' : 'notice-success');
            
            $html .= '<div class="notice ' . $status_class . '">';
            $html .= '<h3>Test Summary</h3>';
            $html .= '<p><strong>Total Tests:</strong> ' . $summary['total'] . '</p>';
            $html .= '<p><strong>Passed:</strong> ' . $summary['passed'] . '</p>';
            $html .= '<p><strong>Failed:</strong> ' . $summary['failed'] . '</p>';
            $html .= '<p><strong>Warnings:</strong> ' . $summary['warnings'] . '</p>';
            $html .= '<p><strong>Skipped:</strong> ' . $summary['skipped'] . '</p>';
            $html .= '<p><strong>Success Rate:</strong> ' . round($summary['success_rate'], 1) . '%</p>';
            
            if (isset($results['total_duration_ms'])) {
                $html .= '<p><strong>Total Duration:</strong> ' . round($results['total_duration_ms'], 2) . 'ms</p>';
            }
            
            $html .= '</div>';
        }
        
        // Individual test results
        if (isset($results['tests']) && !empty($results['tests'])) {
            $html .= '<h3>Individual Test Results</h3>';
            $html .= '<div class="explainer-individual-tests">';
            
            foreach ($results['tests'] as $test_name => $test) {
                $status_class = $this->get_status_class($test['status']);
                $status_icon = $this->get_status_icon($test['status']);
                
                $html .= '<div class="test-result ' . $status_class . '">';
                $html .= '<h4>' . $status_icon . ' ' . esc_html(ucwords(str_replace('_', ' ', $test_name))) . '</h4>';
                $html .= '<p><strong>Status:</strong> ' . ucfirst($test['status']) . '</p>';
                
                if (isset($test['details'])) {
                    $html .= $this->format_test_details($test['details']);
                }
                
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Format test details
     * 
     * @param array $details Test details
     * @return string Formatted details HTML
     */
    private function format_test_details($details) {
        if (empty($details)) {
            return '';
        }
        
        $html = '<div class="test-details">';
        
        foreach ($details as $key => $value) {
            if (is_array($value)) {
                $html .= '<p><strong>' . esc_html(ucwords(str_replace('_', ' ', $key))) . ':</strong></p>';
                $html .= '<pre>' . esc_html(wp_json_encode($value, JSON_PRETTY_PRINT)) . '</pre>';
            } else {
                $html .= '<p><strong>' . esc_html(ucwords(str_replace('_', ' ', $key))) . ':</strong> ' . esc_html($value) . '</p>';
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get CSS class for test status
     * 
     * @param string $status Test status
     * @return string CSS class
     */
    private function get_status_class($status) {
        switch ($status) {
            case 'pass':
                return 'test-pass';
            case 'fail':
                return 'test-fail';
            case 'warning':
                return 'test-warning';
            case 'skip':
                return 'test-skip';
            default:
                return 'test-unknown';
        }
    }
    
    /**
     * Get icon for test status
     * 
     * @param string $status Test status
     * @return string Icon HTML
     */
    private function get_status_icon($status) {
        switch ($status) {
            case 'pass':
                return '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>';
            case 'fail':
                return '<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>';
            case 'warning':
                return '<span class="dashicons dashicons-warning" style="color: #ffb900;"></span>';
            case 'skip':
                return '<span class="dashicons dashicons-minus" style="color: #72777c;"></span>';
            default:
                return '<span class="dashicons dashicons-marker" style="color: #72777c;"></span>';
        }
    }
    
    /**
     * Render test interface HTML
     * 
     * @return string HTML for test interface
     */
    public function render_test_interface() {
        ob_start();
        ?>
        <div class="explainer-database-integration-tests">
            <h2><?php esc_html_e('Database Integration Tests', 'ai-explainer'); ?></h2>
            <p><?php esc_html_e('Run comprehensive tests to verify database webhook integration functionality.', 'ai-explainer'); ?></p>
            
            <div class="test-controls">
                <button type="button" id="run-database-tests" class="btn-base btn-primary">
                    <?php esc_html_e('Run Database Integration Tests', 'ai-explainer'); ?>
                </button>
                <span id="test-status" class="test-status"></span>
            </div>
            
            <div id="test-results-container" class="test-results-container" style="display: none;">
                <h3><?php esc_html_e('Test Results', 'ai-explainer'); ?></h3>
                <div id="test-results-content"></div>
            </div>
        </div>
        
        <style>
        .explainer-database-integration-tests {
            max-width: 1200px;
        }
        
        .test-controls {
            margin: 20px 0;
        }
        
        .test-status {
            margin-left: 10px;
            font-style: italic;
        }
        
        .test-results-container {
            margin-top: 20px;
        }
        
        .explainer-test-results {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 15px;
        }
        
        .explainer-individual-tests {
            margin-top: 15px;
        }
        
        .test-result {
            margin: 10px 0;
            padding: 10px;
            border-left: 4px solid #ddd;
            background: #f9f9f9;
        }
        
        .test-result.test-pass {
            border-left-color: #46b450;
        }
        
        .test-result.test-fail {
            border-left-color: #dc3232;
        }
        
        .test-result.test-warning {
            border-left-color: #ffb900;
        }
        
        .test-result.test-skip {
            border-left-color: #72777c;
        }
        
        .test-details {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        
        .test-details pre {
            background: #f1f1f1;
            padding: 8px;
            border-radius: 3px;
            overflow-x: auto;
            font-size: 12px;
            max-height: 300px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#run-database-tests').on('click', function() {
                var $button = $(this);
                var $status = $('#test-status');
                var $resultsContainer = $('#test-results-container');
                var $resultsContent = $('#test-results-content');
                
                $button.prop('disabled', true);
                $status.text('<?php esc_js(__('Running tests...', 'ai-explainer')); ?>');
                $resultsContainer.hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'explainer_run_database_integration_tests',
                        nonce: '<?php echo esc_attr(wp_create_nonce('explainer_admin_nonce')); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.text('<?php esc_js(__('Tests completed successfully!', 'ai-explainer')); ?>');
                            
                            if (response.data.results) {
                                var results = response.data.results;
                                var summary = results.summary || {};
                                
                                var summaryText = 'Total: ' + (summary.total || 0) + 
                                                ', Passed: ' + (summary.passed || 0) + 
                                                ', Failed: ' + (summary.failed || 0) + 
                                                ', Warnings: ' + (summary.warnings || 0);
                                
                                $status.text('Tests completed - ' + summaryText);
                                
                                // Format and display results
                                displayTestResults(response.data.results);
                            }
                        } else {
                            $status.text('<?php esc_js(__('Tests failed: ', 'ai-explainer')); ?>' + (response.data.message || 'Unknown error'));
                        }
                    },
                    error: function() {
                        $status.text('<?php esc_js(__('Error running tests - check console for details', 'ai-explainer')); ?>');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                    }
                });
            });
            
            function displayTestResults(results) {
                var html = formatTestResults(results);
                $('#test-results-content').html(html);
                $('#test-results-container').show();
            }
            
            function formatTestResults(results) {
                // This would be the client-side formatting
                // For now, just show raw JSON in a readable format
                return '<pre>' + JSON.stringify(results, null, 2) + '</pre>';
            }
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
}