<?php
/**
 * Webhook System Test Suite
 * 
 * Comprehensive testing for webhook infrastructure components
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Webhook Test Suite Class
 * 
 * Tests webhook emitter, event payload builder, and integration points.
 */
class ExplainerPlugin_Webhook_Test_Suite {
    
    /**
     * Test results
     * 
     * @var array
     */
    private $results = array();
    
    /**
     * Test configuration
     * 
     * @var ExplainerPlugin_Config
     */
    private $config;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->config = new ExplainerPlugin_Config();
    }
    
    /**
     * Run all webhook tests
     * 
     * @return array Test results
     */
    public function run_all_tests() {
        $this->log_info('Starting webhook system test suite');
        
        // Test individual components
        $this->test_event_payload_builder();
        $this->test_webhook_emitter();
        $this->test_configuration_system();
        $this->test_integration_points();
        $this->test_performance_constraints();
        $this->test_error_handling();
        $this->test_security_filtering();
        
        // Generate summary
        $summary = $this->generate_test_summary();
        
        $this->log_info('Webhook system test suite completed', $summary);
        
        return array(
            'results' => $this->results,
            'summary' => $summary
        );
    }
    
    /**
     * Test event payload builder
     */
    private function test_event_payload_builder() {
        $this->log_info('Testing Event Payload Builder');
        
        try {
            $payload_builder = new ExplainerPlugin_Event_Payload();
            
            // Test 1: Basic payload creation
            $payload = $payload_builder->build_payload(
                'selection_tracker',
                123,
                'created',
                null,
                array('id' => 123, 'text' => 'Test selection'),
                array(),
                1
            );
            
            $this->assert_test(
                'event_payload_basic_creation',
                !empty($payload) && isset($payload['event_id']),
                'Basic payload creation should succeed',
                $payload
            );
            
            // Test 2: Payload validation
            if ($payload) {
                $valid = $payload_builder->validate_payload($payload);
                $this->assert_test(
                    'event_payload_validation',
                    $valid,
                    'Generated payload should be valid',
                    array('payload' => $payload, 'valid' => $valid)
                );
            }
            
            // Test 3: Sensitive data filtering
            $sensitive_payload = $payload_builder->build_payload(
                'test_entity',
                456,
                'updated',
                array('id' => 456, 'api_key' => 'secret123', 'password' => 'hidden'),
                array('id' => 456, 'api_key' => 'newsecret', 'name' => 'Updated'),
                array('api_key', 'name'),
                1
            );
            
            $has_sensitive_data = isset($sensitive_payload['row_before']['api_key']) || 
                                  isset($sensitive_payload['row_after']['api_key']);
            
            $this->assert_test(
                'event_payload_sensitive_filtering',
                !$has_sensitive_data,
                'Sensitive data should be filtered from payloads',
                $sensitive_payload
            );
            
            // Test 4: UUID generation
            $payload1 = $payload_builder->build_payload('test', 1, 'created', null, array('id' => 1), array(), 1);
            $payload2 = $payload_builder->build_payload('test', 2, 'created', null, array('id' => 2), array(), 1);
            
            $unique_ids = $payload1['event_id'] !== $payload2['event_id'];
            
            $this->assert_test(
                'event_payload_unique_ids',
                $unique_ids,
                'Event IDs should be unique',
                array(
                    'id1' => $payload1['event_id'] ?? 'missing',
                    'id2' => $payload2['event_id'] ?? 'missing'
                )
            );
            
        } catch (Exception $e) {
            $this->assert_test(
                'event_payload_exception',
                false,
                'Event payload builder should not throw exceptions',
                array('error' => $e->getMessage())
            );
        }
    }
    
    /**
     * Test webhook emitter
     */
    private function test_webhook_emitter() {
        $this->log_info('Testing Webhook Emitter');
        
        try {
            $webhook_emitter = ExplainerPlugin_Webhook_Emitter::get_instance();
            
            // Test 1: Singleton pattern
            $webhook_emitter2 = ExplainerPlugin_Webhook_Emitter::get_instance();
            $is_singleton = $webhook_emitter === $webhook_emitter2;
            
            $this->assert_test(
                'webhook_emitter_singleton',
                $is_singleton,
                'Webhook emitter should implement singleton pattern',
                array('same_instance' => $is_singleton)
            );
            
            // Test 2: Performance metrics tracking
            $initial_metrics = $webhook_emitter->get_performance_metrics();
            $initial_count = count($initial_metrics);
            
            // Simulate webhook emission
            $webhook_emitter->emit_after_write(
                'test_entity',
                999,
                'created',
                null,
                array('id' => 999, 'test' => true),
                array(),
                1
            );
            
            $updated_metrics = $webhook_emitter->get_performance_metrics();
            $metrics_updated = count($updated_metrics) > $initial_count;
            
            $this->assert_test(
                'webhook_emitter_metrics',
                $metrics_updated,
                'Performance metrics should be tracked',
                array(
                    'initial_count' => $initial_count,
                    'updated_count' => count($updated_metrics)
                )
            );
            
            // Test 3: Performance status
            $performance_status = $webhook_emitter->get_performance_status();
            $has_status_fields = isset($performance_status['time_status']) && 
                                isset($performance_status['memory_status']);
            
            $this->assert_test(
                'webhook_emitter_performance_status',
                $has_status_fields,
                'Performance status should include time and memory status',
                $performance_status
            );
            
        } catch (Exception $e) {
            $this->assert_test(
                'webhook_emitter_exception',
                false,
                'Webhook emitter should not throw exceptions',
                array('error' => $e->getMessage())
            );
        }
    }
    
    /**
     * Test configuration system
     */
    private function test_configuration_system() {
        $this->log_info('Testing Configuration System');
        
        // Test 1: Feature flag access
        $webhook_enabled = ExplainerPlugin_Config::is_feature_enabled('webhook_system', true);
        
        $this->assert_test(
            'config_feature_flags',
            is_bool($webhook_enabled),
            'Feature flags should return boolean values',
            array('webhook_system' => $webhook_enabled)
        );
        
        // Test 2: Realtime settings
        $webhook_timeout = ExplainerPlugin_Config::get_realtime_setting('webhook_recursion_timeout', 5);
        
        $this->assert_test(
            'config_realtime_settings',
            is_numeric($webhook_timeout),
            'Realtime settings should return expected types',
            array('webhook_recursion_timeout' => $webhook_timeout)
        );
        
        // Test 3: Default values
        $nonexistent_setting = ExplainerPlugin_Config::get_realtime_setting('nonexistent_setting', 'default_value');
        
        $this->assert_test(
            'config_default_values',
            $nonexistent_setting === 'default_value',
            'Default values should be returned for nonexistent settings',
            array('nonexistent_setting' => $nonexistent_setting)
        );
    }
    
    /**
     * Test integration points
     */
    private function test_integration_points() {
        $this->log_info('Testing Integration Points');
        
        // Test 1: WordPress action hook
        $hook_exists = has_action('wp_ai_explainer_after_db_write');
        
        $this->assert_test(
            'integration_wordpress_hook',
            $hook_exists !== false,
            'WordPress action hook should be registered or available',
            array('hook_registered' => $hook_exists !== false)
        );
        
        // Test 2: Class dependencies
        $classes_exist = class_exists('ExplainerPlugin_Webhook_Emitter') && 
                        class_exists('ExplainerPlugin_Event_Payload') &&
                        class_exists('ExplainerPlugin_Config');
        
        $this->assert_test(
            'integration_class_dependencies',
            $classes_exist,
            'All required classes should be available',
            array(
                'ExplainerPlugin_Webhook_Emitter' => class_exists('ExplainerPlugin_Webhook_Emitter'),
                'ExplainerPlugin_Event_Payload' => class_exists('ExplainerPlugin_Event_Payload'),
                'ExplainerPlugin_Config' => class_exists('ExplainerPlugin_Config')
            )
        );
        
        // Test 3: Recursion flag clearing
        ExplainerPlugin_Webhook_Emitter::clear_recursion_flag();
        
        $this->assert_test(
            'integration_recursion_clearing',
            true, // If no exception thrown, test passes
            'Recursion flag clearing should not cause errors',
            array('completed' => true)
        );
    }
    
    /**
     * Test performance constraints
     */
    private function test_performance_constraints() {
        $this->log_info('Testing Performance Constraints');
        
        try {
            $webhook_emitter = ExplainerPlugin_Webhook_Emitter::get_instance();
            
            // Reset metrics for clean test
            $webhook_emitter->reset_performance_metrics();
            
            // Test multiple emissions
            $start_time = microtime(true);
            
            for ($i = 0; $i < 5; $i++) {
                $webhook_emitter->emit_after_write(
                    'performance_test',
                    $i,
                    'created',
                    null,
                    array('id' => $i, 'iteration' => $i),
                    array(),
                    1
                );
            }
            
            $elapsed_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
            
            $avg_time = $webhook_emitter->get_average_processing_time();
            $avg_memory = $webhook_emitter->get_average_memory_usage();
            
            $this->assert_test(
                'performance_processing_time',
                $elapsed_time < 1000, // Should complete within 1 second
                'Webhook processing should meet performance requirements',
                array(
                    'total_time_ms' => $elapsed_time,
                    'avg_time_ms' => $avg_time,
                    'avg_memory_mb' => $avg_memory
                )
            );
            
        } catch (Exception $e) {
            $this->assert_test(
                'performance_exception',
                false,
                'Performance testing should not cause exceptions',
                array('error' => $e->getMessage())
            );
        }
    }
    
    /**
     * Test error handling
     */
    private function test_error_handling() {
        $this->log_info('Testing Error Handling');
        
        try {
            $payload_builder = new ExplainerPlugin_Event_Payload();
            $webhook_emitter = ExplainerPlugin_Webhook_Emitter::get_instance();
            
            // Test 1: Invalid payload parameters
            $invalid_payload = $payload_builder->build_payload('', 0, '', null, null, array(), null);
            
            $this->assert_test(
                'error_handling_invalid_payload',
                $invalid_payload === null,
                'Invalid payload parameters should return null',
                array('result' => $invalid_payload)
            );
            
            // Test 2: Webhook emission with invalid parameters
            $emission_result = $webhook_emitter->emit_after_write('', 0, '', null, null, array(), null);
            
            $this->assert_test(
                'error_handling_invalid_emission',
                $emission_result === false,
                'Invalid emission parameters should return false',
                array('result' => $emission_result)
            );
            
        } catch (Exception $e) {
            $this->assert_test(
                'error_handling_exception',
                false,
                'Error handling should not throw exceptions',
                array('error' => $e->getMessage())
            );
        }
    }
    
    /**
     * Test security filtering
     */
    private function test_security_filtering() {
        $this->log_info('Testing Security Filtering');
        
        try {
            $payload_builder = new ExplainerPlugin_Event_Payload();
            
            // Create payload with potentially sensitive data
            $sensitive_data = array(
                'id' => 123,
                'username' => 'testuser',
                'password' => 'secret123',
                'api_key' => 'sk-abc123',
                'private_key' => 'pk-xyz789',
                'user_token' => 'ut-456def',
                'safe_field' => 'this is safe'
            );
            
            $payload = $payload_builder->build_payload(
                'security_test',
                123,
                'created',
                null,
                $sensitive_data,
                array(),
                1
            );
            
            // Check that sensitive fields were filtered
            $has_password = isset($payload['row_after']['password']);
            $has_api_key = isset($payload['row_after']['api_key']);
            $has_private_key = isset($payload['row_after']['private_key']);
            $has_token = isset($payload['row_after']['user_token']);
            $has_safe_field = isset($payload['row_after']['safe_field']);
            
            $properly_filtered = !$has_password && !$has_api_key && !$has_private_key && !$has_token && $has_safe_field;
            
            $this->assert_test(
                'security_sensitive_data_filtering',
                $properly_filtered,
                'Sensitive data should be filtered while preserving safe data',
                array(
                    'has_password' => $has_password,
                    'has_api_key' => $has_api_key,
                    'has_private_key' => $has_private_key,
                    'has_token' => $has_token,
                    'has_safe_field' => $has_safe_field,
                    'filtered_payload' => $payload['row_after'] ?? 'missing'
                )
            );
            
        } catch (Exception $e) {
            $this->assert_test(
                'security_filtering_exception',
                false,
                'Security filtering should not throw exceptions',
                array('error' => $e->getMessage())
            );
        }
    }
    
    /**
     * Assert test result
     * 
     * @param string $test_name Test name
     * @param bool $condition Test condition
     * @param string $description Test description
     * @param array $data Test data
     */
    private function assert_test($test_name, $condition, $description, $data = array()) {
        $this->results[] = array(
            'test' => $test_name,
            'passed' => $condition,
            'description' => $description,
            'data' => $data,
            'timestamp' => current_time('mysql')
        );
        
        $status = $condition ? 'PASS' : 'FAIL';
        $this->log_info("[$status] $test_name: $description", $data);
    }
    
    /**
     * Generate test summary
     * 
     * @return array Summary statistics
     */
    private function generate_test_summary() {
        $total_tests = count($this->results);
        $passed_tests = count(array_filter($this->results, function($result) {
            return $result['passed'];
        }));
        $failed_tests = $total_tests - $passed_tests;
        $success_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 2) : 0;
        
        return array(
            'total_tests' => $total_tests,
            'passed_tests' => $passed_tests,
            'failed_tests' => $failed_tests,
            'success_rate' => $success_rate,
            'overall_status' => $failed_tests === 0 ? 'SUCCESS' : 'FAILURE'
        );
    }
    
    /**
     * Get test results
     * 
     * @return array All test results
     */
    public function get_results() {
        return $this->results;
    }
    
    /**
     * Get failed tests only
     * 
     * @return array Failed test results
     */
    public function get_failed_tests() {
        return array_filter($this->results, function($result) {
            return !$result['passed'];
        });
    }
    
    /**
     * Perform webhook system health check
     * 
     * @return array Health check results
     */
    public function health_check() {
        $health_results = array(
            'overall_status' => 'healthy',
            'checks' => array(),
            'timestamp' => current_time('mysql')
        );
        
        // Check 1: Webhook system enabled
        $webhook_enabled = ExplainerPlugin_Config::is_feature_enabled('webhook_system', true);
        $health_results['checks']['webhook_system_enabled'] = array(
            'status' => $webhook_enabled ? 'pass' : 'fail',
            'message' => $webhook_enabled ? 'Webhook system is enabled' : 'Webhook system is disabled'
        );
        
        // Check 2: Classes loaded
        $classes_loaded = class_exists('ExplainerPlugin_Webhook_Emitter') && class_exists('ExplainerPlugin_Event_Payload');
        $health_results['checks']['classes_loaded'] = array(
            'status' => $classes_loaded ? 'pass' : 'fail',
            'message' => $classes_loaded ? 'All webhook classes are loaded' : 'Some webhook classes are missing'
        );
        
        // Check 3: Performance metrics within limits
        if ($classes_loaded) {
            try {
                $webhook_emitter = ExplainerPlugin_Webhook_Emitter::get_instance();
                $performance_status = $webhook_emitter->get_performance_status();
                $performance_good = $performance_status['time_status'] === 'good' && $performance_status['memory_status'] === 'good';
                
                $health_results['checks']['performance_status'] = array(
                    'status' => $performance_good ? 'pass' : 'warn',
                    'message' => $performance_good ? 'Performance within limits' : 'Performance metrics showing warnings',
                    'data' => $performance_status
                );
            } catch (Exception $e) {
                $health_results['checks']['performance_status'] = array(
                    'status' => 'fail',
                    'message' => 'Could not retrieve performance metrics: ' . $e->getMessage()
                );
            }
        }
        
        // Check 4: WordPress hooks registered
        $hook_registered = has_action('wp_ai_explainer_after_db_write');
        $health_results['checks']['wordpress_hooks'] = array(
            'status' => $hook_registered !== false ? 'pass' : 'warn',
            'message' => $hook_registered !== false ? 'WordPress action hooks are registered' : 'No listeners found for webhook action'
        );
        
        // Determine overall status
        $failed_checks = array_filter($health_results['checks'], function($check) {
            return $check['status'] === 'fail';
        });
        
        $warn_checks = array_filter($health_results['checks'], function($check) {
            return $check['status'] === 'warn';
        });
        
        if (!empty($failed_checks)) {
            $health_results['overall_status'] = 'unhealthy';
        } elseif (!empty($warn_checks)) {
            $health_results['overall_status'] = 'degraded';
        }
        
        return $health_results;
    }
    
    /**
     * Log info message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     */
    private function log_info($message, $context = array()) {
        if (function_exists('explainer_log_info')) {
            explainer_log_info($message, $context, 'Webhook_Test');
        } else if (ExplainerPlugin_Debug_Logger::is_enabled()) {
            ExplainerPlugin_Debug_Logger::info($message, 'webhook', $context);
        }
    }
}