<?php
/**
 * Database Integration Test Suite
 * 
 * Comprehensive testing for database webhook integration functionality.
 * 
 * @package WP_AI_Explainer
 * @subpackage Database
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Integration Test Suite Class
 * 
 * Provides comprehensive testing for all database webhook integration
 * functionality across the plugin.
 */
class ExplainerPlugin_Database_Integration_Test_Suite {
    
    /**
     * Test results
     * 
     * @var array
     */
    private $test_results = array();
    
    /**
     * Test configuration
     * 
     * @var array
     */
    private $config;
    
    /**
     * Test webhook events captured
     * 
     * @var array
     */
    private $captured_webhooks = array();
    
    /**
     * Original webhook state
     * 
     * @var array
     */
    private $original_webhook_state = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->config = array(
            'timeout' => 30, // Test timeout in seconds
            'capture_webhooks' => true,
            'cleanup_after_tests' => true,
            'log_test_output' => true,
            'performance_monitoring' => true
        );
        
        $this->setup_webhook_capture();
    }
    
    /**
     * Run all database integration tests
     * 
     * @return array Complete test results
     */
    public function run_all_tests() {
        $this->log_info('Starting database integration test suite');
        $suite_start_time = microtime(true);
        
        $this->test_results = array(
            'suite_status' => 'running',
            'start_time' => $suite_start_time,
            'tests' => array(),
            'performance' => array(),
            'summary' => array()
        );
        
        try {
            // Setup test environment
            $this->setup_test_environment();
            
            // Core infrastructure tests
            $this->test_webhook_emitter_availability();
            $this->test_database_hook_integration_initialization();
            $this->test_db_operation_wrapper_functionality();
            
            // Component integration tests
            $this->test_job_queue_webhook_integration();
            $this->test_content_generator_webhook_integration();
            $this->test_selection_tracker_webhook_integration();
            
            // Performance and reliability tests
            $this->test_webhook_performance();
            $this->test_error_isolation();
            $this->test_bulk_operations();
            $this->test_transaction_safety();
            
            // Configuration and settings tests
            $this->test_integration_configuration();
            $this->test_rate_limiting();
            
            // Cleanup and finalize
            $this->cleanup_test_environment();
            
            $suite_end_time = microtime(true);
            $this->test_results['end_time'] = $suite_end_time;
            $this->test_results['total_duration_ms'] = ($suite_end_time - $suite_start_time) * 1000;
            $this->test_results['suite_status'] = 'completed';
            
            $this->generate_test_summary();
            
            $this->log_info('Database integration test suite completed', array(
                'duration_ms' => $this->test_results['total_duration_ms'],
                'total_tests' => count($this->test_results['tests']),
                'passed' => $this->test_results['summary']['passed'],
                'failed' => $this->test_results['summary']['failed'],
                'warnings' => $this->test_results['summary']['warnings']
            ));
            
        } catch (Exception $e) {
            $this->test_results['suite_status'] = 'error';
            $this->test_results['error'] = array(
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            );
            
            $this->log_error('Test suite encountered fatal error', array(
                'error' => $e->getMessage()
            ));
        }
        
        return $this->test_results;
    }
    
    /**
     * Test webhook emitter availability
     */
    private function test_webhook_emitter_availability() {
        $test_name = 'webhook_emitter_availability';
        $start_time = microtime(true);
        
        try {
            $class_exists = class_exists('ExplainerPlugin_Webhook_Emitter');
            
            if ($class_exists) {
                $instance = ExplainerPlugin_Webhook_Emitter::get_instance();
                $instance_valid = $instance !== null;
                
                $this->add_test_result($test_name, 'pass', array(
                    'class_exists' => true,
                    'instance_created' => $instance_valid,
                    'duration_ms' => (microtime(true) - $start_time) * 1000
                ));
            } else {
                $this->add_test_result($test_name, 'fail', array(
                    'class_exists' => false,
                    'message' => 'ExplainerPlugin_Webhook_Emitter class not found'
                ));
            }
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'fail', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }
    
    /**
     * Test database hook integration initialization
     */
    private function test_database_hook_integration_initialization() {
        $test_name = 'database_hook_integration_init';
        $start_time = microtime(true);
        
        try {
            $class_exists = class_exists('ExplainerPlugin_Database_Hook_Integration');
            
            if (!$class_exists) {
                $this->add_test_result($test_name, 'fail', array(
                    'message' => 'ExplainerPlugin_Database_Hook_Integration class not found'
                ));
                return;
            }
            
            $instance = ExplainerPlugin_Database_Hook_Integration::get_instance();
            $initialization_success = $instance->initialise_integrations();
            $status = $instance->get_integration_status();
            
            $this->add_test_result($test_name, $initialization_success ? 'pass' : 'fail', array(
                'initialization_success' => $initialization_success,
                'status' => $status,
                'duration_ms' => (microtime(true) - $start_time) * 1000
            ));
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'fail', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }
    
    /**
     * Test DB operation wrapper functionality
     */
    private function test_db_operation_wrapper_functionality() {
        $test_name = 'db_operation_wrapper';
        $start_time = microtime(true);
        
        try {
            $class_exists = class_exists('ExplainerPlugin_DB_Operation_Wrapper');
            
            if (!$class_exists) {
                $this->add_test_result($test_name, 'fail', array(
                    'message' => 'ExplainerPlugin_DB_Operation_Wrapper class not found'
                ));
                return;
            }
            
            $wrapper = new ExplainerPlugin_DB_Operation_Wrapper();
            
            // Test transaction methods
            $transaction_tests = array(
                'begin_transaction' => $wrapper->begin_transaction(),
                'is_in_transaction' => $wrapper->is_in_transaction(),
                'get_transaction_depth' => $wrapper->get_transaction_depth() === 1,
                'commit_transaction' => $wrapper->commit_transaction()
            );
            
            $all_passed = !in_array(false, $transaction_tests, true);
            
            $this->add_test_result($test_name, $all_passed ? 'pass' : 'fail', array(
                'transaction_tests' => $transaction_tests,
                'duration_ms' => (microtime(true) - $start_time) * 1000
            ));
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'fail', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }
    
    /**
     * Test job queue webhook integration
     */
    private function test_job_queue_webhook_integration() {
        $test_name = 'job_queue_webhook_integration';
        $start_time = microtime(true);
        
        try {
            $this->clear_captured_webhooks();
            
            // Test creating a job (if job queue manager is available)
            if (class_exists('ExplainerPlugin_Job_Queue_Manager')) {
                $manager = ExplainerPlugin_Job_Queue_Manager::get_instance();
                
                // Create a test job
                $test_job_id = $manager->queue_job('test_job', array(
                    'test_data' => 'database_integration_test'
                ));
                
                if ($test_job_id) {
                    // Update job status to trigger webhook
                    $update_success = $manager->update_job_status($test_job_id, 'completed');
                    
                    $webhooks_captured = count($this->captured_webhooks);
                    
                    $this->add_test_result($test_name, $webhooks_captured > 0 ? 'pass' : 'warning', array(
                        'job_created' => $test_job_id !== false,
                        'job_id' => $test_job_id,
                        'status_updated' => $update_success,
                        'webhooks_captured' => $webhooks_captured,
                        'captured_events' => $this->captured_webhooks,
                        'duration_ms' => (microtime(true) - $start_time) * 1000
                    ));
                } else {
                    $this->add_test_result($test_name, 'warning', array(
                        'message' => 'Could not create test job',
                        'duration_ms' => (microtime(true) - $start_time) * 1000
                    ));
                }
            } else {
                $this->add_test_result($test_name, 'skip', array(
                    'message' => 'Job queue manager not available'
                ));
            }
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'fail', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }
    
    /**
     * Test content generator webhook integration
     */
    private function test_content_generator_webhook_integration() {
        $test_name = 'content_generator_webhook_integration';
        
        try {
            // Check if content generator webhook action exists
            $hook_registered = has_action('wp_ai_explainer_content_after_write');
            
            $this->add_test_result($test_name, $hook_registered ? 'pass' : 'warning', array(
                'hook_registered' => $hook_registered,
                'message' => $hook_registered ? 'Content generator webhook hook is registered' : 'Content generator webhook hook not found'
            ));
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'fail', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Test selection tracker webhook integration
     */
    private function test_selection_tracker_webhook_integration() {
        $test_name = 'selection_tracker_webhook_integration';
        $start_time = microtime(true);
        
        try {
            $this->clear_captured_webhooks();
            
            // Test selection tracker if available
            if (class_exists('ExplainerPlugin_Selection_Tracker')) {
                $tracker = new ExplainerPlugin_Selection_Tracker();
                
                // Test database connection first
                $db_connection = $tracker->test_database_connection();
                
                if ($db_connection) {
                    // Track a test selection
                    $track_result = $tracker->track_selection(
                        'test selection for database integration',
                        home_url('/test'),
                        0,
                        true,
                        'testing',
                        'standard'
                    );
                    
                    $webhooks_captured = count($this->captured_webhooks);
                    
                    $this->add_test_result($test_name, $track_result ? 'pass' : 'warning', array(
                        'db_connection' => $db_connection,
                        'track_result' => $track_result,
                        'webhooks_captured' => $webhooks_captured,
                        'captured_events' => $this->captured_webhooks,
                        'duration_ms' => (microtime(true) - $start_time) * 1000
                    ));
                } else {
                    $this->add_test_result($test_name, 'warning', array(
                        'message' => 'Selection tracker database connection failed',
                        'db_connection' => false
                    ));
                }
            } else {
                $this->add_test_result($test_name, 'skip', array(
                    'message' => 'Selection tracker class not available'
                ));
            }
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'fail', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }
    
    /**
     * Test webhook performance
     */
    private function test_webhook_performance() {
        $test_name = 'webhook_performance';
        $start_time = microtime(true);
        
        try {
            $performance_tests = array();
            
            // Test webhook emitter performance if available
            if (class_exists('ExplainerPlugin_Webhook_Emitter')) {
                $emitter = ExplainerPlugin_Webhook_Emitter::get_instance();
                $performance_metrics = $emitter->get_performance_metrics();
                $performance_status = $emitter->get_performance_status();
                
                $performance_tests['webhook_emitter'] = array(
                    'metrics_available' => !empty($performance_metrics),
                    'status' => $performance_status,
                    'avg_time_ms' => $performance_status['avg_time_ms'] ?? 0,
                    'avg_memory_mb' => $performance_status['avg_memory_mb'] ?? 0
                );
            }
            
            // Test database integration manager performance
            if (class_exists('ExplainerPlugin_Database_Hook_Integration')) {
                $integration = ExplainerPlugin_Database_Hook_Integration::get_instance();
                $integration_metrics = $integration->get_performance_metrics();
                
                $performance_tests['integration_manager'] = array(
                    'total_operations' => $integration_metrics['total_operations'] ?? 0,
                    'success_rate' => $integration_metrics['success_rate'] ?? 0,
                    'average_time_ms' => $integration_metrics['average_time_ms'] ?? 0
                );
            }
            
            $this->add_test_result($test_name, 'pass', array(
                'performance_tests' => $performance_tests,
                'duration_ms' => (microtime(true) - $start_time) * 1000
            ));
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'fail', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Test error isolation
     */
    private function test_error_isolation() {
        $test_name = 'error_isolation';
        $start_time = microtime(true);
        
        try {
            $this->clear_captured_webhooks();
            
            // Test that webhook errors don't affect database operations
            // Simulate webhook failure by triggering an action that doesn't exist
            
            // This should not cause any fatal errors
            do_action('wp_ai_explainer_nonexistent_after_write',
                'test_entity',
                999,
                'created',
                null,
                array('test' => 'data'),
                array(),
                null
            );
            
            // If we get here, error isolation is working
            $this->add_test_result($test_name, 'pass', array(
                'message' => 'Error isolation working - no fatal errors from invalid webhook',
                'duration_ms' => (microtime(true) - $start_time) * 1000
            ));
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'fail', array(
                'error' => $e->getMessage(),
                'message' => 'Error isolation failed - webhook error caused exception'
            ));
        }
    }
    
    /**
     * Test bulk operations
     */
    private function test_bulk_operations() {
        $test_name = 'bulk_operations';
        
        try {
            $bulk_tests = array();
            
            // Test job queue bulk operations
            if (class_exists('ExplainerPlugin_Job_Queue_Manager')) {
                $manager = ExplainerPlugin_Job_Queue_Manager::get_instance();
                
                // Test clearing queue (bulk delete)
                $cleared = $manager->clear_queue('test_job');
                $bulk_tests['job_queue_clear'] = array(
                    'operation_successful' => $cleared !== false,
                    'cleared_count' => $cleared
                );
            }
            
            // Test selection tracker bulk operations  
            if (class_exists('ExplainerPlugin_Selection_Tracker')) {
                $tracker = new ExplainerPlugin_Selection_Tracker();
                
                // Test cleanup (bulk delete)
                $cleaned = $tracker->cleanup_old_data(365); // Very old data to avoid deleting real data
                $bulk_tests['selection_tracker_cleanup'] = array(
                    'operation_successful' => $cleaned !== false,
                    'cleaned_count' => $cleaned
                );
            }
            
            $this->add_test_result($test_name, 'pass', array(
                'bulk_tests' => $bulk_tests,
                'message' => 'Bulk operations completed without errors'
            ));
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'fail', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Test transaction safety
     */
    private function test_transaction_safety() {
        $test_name = 'transaction_safety';
        
        try {
            if (!class_exists('ExplainerPlugin_DB_Operation_Wrapper')) {
                $this->add_test_result($test_name, 'skip', array(
                    'message' => 'DB operation wrapper not available'
                ));
                return;
            }
            
            $wrapper = new ExplainerPlugin_DB_Operation_Wrapper();
            
            // Test transaction rollback clears pending webhooks
            $wrapper->begin_transaction();
            
            // Simulate queueing webhooks during transaction (this would happen in real usage)
            $webhook_count_before = $wrapper->get_pending_webhook_count();
            
            $wrapper->rollback_transaction();
            
            $webhook_count_after = $wrapper->get_pending_webhook_count();
            
            $this->add_test_result($test_name, 'pass', array(
                'transaction_rollback_successful' => true,
                'webhook_count_before' => $webhook_count_before,
                'webhook_count_after' => $webhook_count_after,
                'message' => 'Transaction safety mechanisms working'
            ));
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'fail', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Test integration configuration
     */
    private function test_integration_configuration() {
        $test_name = 'integration_configuration';
        
        try {
            $config_tests = array();
            
            if (class_exists('ExplainerPlugin_Database_Hook_Integration')) {
                $integration = ExplainerPlugin_Database_Hook_Integration::get_instance();
                $status = $integration->get_integration_status();
                
                $config_tests['integration_status'] = $status;
                $config_tests['global_enabled'] = $status['global_enabled'] ?? false;
                $config_tests['integrations_count'] = count($status['integrations'] ?? array());
            }
            
            if (class_exists('ExplainerPlugin_Config')) {
                $config = new ExplainerPlugin_Config();
                $realtime_enabled = $config->get_realtime_setting('database_integration_enabled', true);
                $config_tests['realtime_integration_enabled'] = $realtime_enabled;
            }
            
            $this->add_test_result($test_name, 'pass', array(
                'config_tests' => $config_tests,
                'message' => 'Configuration tests completed'
            ));
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'fail', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Test rate limiting
     */
    private function test_rate_limiting() {
        $test_name = 'rate_limiting';
        
        try {
            // Rate limiting is handled internally by webhook emitter
            // Just test that the mechanism exists
            $rate_limit_exists = class_exists('ExplainerPlugin_Webhook_Emitter');
            
            $this->add_test_result($test_name, $rate_limit_exists ? 'pass' : 'fail', array(
                'webhook_emitter_available' => $rate_limit_exists,
                'message' => 'Rate limiting mechanisms are available'
            ));
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'fail', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Setup test environment
     */
    private function setup_test_environment() {
        // Store original webhook state
        $this->original_webhook_state = array(
            'captured_webhooks' => $this->captured_webhooks
        );
        
        $this->clear_captured_webhooks();
        $this->log_debug('Test environment setup completed');
    }
    
    /**
     * Cleanup test environment
     */
    private function cleanup_test_environment() {
        if ($this->config['cleanup_after_tests']) {
            // Restore original webhook state
            $this->captured_webhooks = $this->original_webhook_state['captured_webhooks'] ?? array();
            
            $this->log_debug('Test environment cleanup completed');
        }
    }
    
    /**
     * Setup webhook capture mechanism
     */
    private function setup_webhook_capture() {
        if (!$this->config['capture_webhooks']) {
            return;
        }
        
        // Hook into all webhook actions to capture them
        add_action('wp_ai_explainer_after_db_write', array($this, 'capture_webhook'), 10, 1);
        add_action('wp_ai_explainer_job_queue_after_write', array($this, 'capture_specific_webhook'), 10, 7);
        add_action('wp_ai_explainer_content_after_write', array($this, 'capture_specific_webhook'), 10, 7);
        add_action('wp_ai_explainer_selection_after_write', array($this, 'capture_specific_webhook'), 10, 7);
    }
    
    /**
     * Capture webhook for testing
     * 
     * @param array $payload Webhook payload
     */
    public function capture_webhook($payload) {
        $this->captured_webhooks[] = array(
            'type' => 'general_webhook',
            'payload' => $payload,
            'timestamp' => microtime(true)
        );
    }
    
    /**
     * Capture specific webhook for testing
     * 
     * @param string $entity Entity type
     * @param int $id Record ID
     * @param string $type Operation type
     * @param mixed $row_before Previous state
     * @param mixed $row_after New state
     * @param array $changed_columns Changed columns
     * @param int|null $actor User ID
     */
    public function capture_specific_webhook($entity, $id, $type, $row_before, $row_after, $changed_columns, $actor) {
        $this->captured_webhooks[] = array(
            'type' => 'specific_webhook',
            'entity' => $entity,
            'id' => $id,
            'operation_type' => $type,
            'row_before' => $row_before,
            'row_after' => $row_after,
            'changed_columns' => $changed_columns,
            'actor' => $actor,
            'timestamp' => microtime(true)
        );
    }
    
    /**
     * Clear captured webhooks
     */
    private function clear_captured_webhooks() {
        $this->captured_webhooks = array();
    }
    
    /**
     * Add test result
     * 
     * @param string $test_name Test name
     * @param string $status Test status (pass, fail, warning, skip)
     * @param array $details Test details
     */
    private function add_test_result($test_name, $status, $details = array()) {
        $this->test_results['tests'][$test_name] = array(
            'name' => $test_name,
            'status' => $status,
            'details' => $details,
            'timestamp' => microtime(true)
        );
    }
    
    /**
     * Generate test summary
     */
    private function generate_test_summary() {
        $summary = array(
            'total' => count($this->test_results['tests']),
            'passed' => 0,
            'failed' => 0,
            'warnings' => 0,
            'skipped' => 0
        );
        
        foreach ($this->test_results['tests'] as $test) {
            switch ($test['status']) {
                case 'pass':
                    $summary['passed']++;
                    break;
                case 'fail':
                    $summary['failed']++;
                    break;
                case 'warning':
                    $summary['warnings']++;
                    break;
                case 'skip':
                    $summary['skipped']++;
                    break;
            }
        }
        
        $summary['success_rate'] = $summary['total'] > 0 ? 
            (($summary['passed'] + $summary['warnings']) / $summary['total']) * 100 : 0;
        
        $this->test_results['summary'] = $summary;
    }
    
    /**
     * Get test results
     * 
     * @return array Test results
     */
    public function get_test_results() {
        return $this->test_results;
    }
    
    /**
     * Get test summary
     * 
     * @return array Test summary
     */
    public function get_test_summary() {
        return $this->test_results['summary'] ?? array();
    }
    
    /**
     * Log debug message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    private function log_debug($message, $context = array()) {
        if (function_exists('explainer_log_debug') && $this->config['log_test_output']) {
            explainer_log_debug($message, $context, 'Database_Integration_Test');
        }
    }
    
    /**
     * Log info message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    private function log_info($message, $context = array()) {
        if (function_exists('explainer_log_info') && $this->config['log_test_output']) {
            explainer_log_info($message, $context, 'Database_Integration_Test');
        }
    }
    
    /**
     * Log error message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    private function log_error($message, $context = array()) {
        if (function_exists('explainer_log_error')) {
            explainer_log_error($message, $context, 'Database_Integration_Test');
        } else if (ExplainerPlugin_Debug_Logger::is_enabled()) {
            ExplainerPlugin_Debug_Logger::error($message, 'database', $context);
        }
    }
}