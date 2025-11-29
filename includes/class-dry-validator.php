<?php
/**
 * DRY Principles Validation Framework
 * 
 * Comprehensive testing and validation of DRY refactoring improvements
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * DRY refactoring validation and testing framework
 */
class ExplainerPlugin_DRY_Validator {
    
    /**
     * Logger instance
     * 
     * @var ExplainerPlugin_Logger
     */
    private $logger;
    
    /**
     * Validation results
     * 
     * @var array
     */
    private $results = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = ExplainerPlugin_Logger::get_instance();
    }
    
    /**
     * Run comprehensive DRY validation tests
     * 
     * @return array Validation results
     */
    public function run_validation() {
        $this->results = array(
            'timestamp' => current_time('mysql'),
            'tests' => array(),
            'summary' => array(
                'total_tests' => 0,
                'passed' => 0,
                'failed' => 0,
                'warnings' => 0
            )
        );
        
        // Test Phase 1: DRY violations elimination
        $this->test_api_key_processing_consolidation();
        $this->test_dynamic_provider_configuration();
        $this->test_javascript_hardcoding_elimination();
        
        // Test Phase 2: Architecture improvements
        $this->test_provider_config_class();
        $this->test_http_client_abstraction();
        $this->test_configuration_centralization();
        
        // Test Phase 3: Strategy pattern implementation
        $this->test_cost_calculation_strategy();
        $this->test_provider_integration();
        
        // Generate summary
        $this->generate_summary();
        
        return $this->results;
    }
    
    /**
     * Test API key processing consolidation
     */
    private function test_api_key_processing_consolidation() {
        $test_name = 'API Key Processing Consolidation';
        $this->log_test_start($test_name);
        
        try {
            // Check if ExplainerPlugin_Admin class has the consolidated method
            if (!class_exists('ExplainerPlugin_Admin')) {
                throw new \Exception(esc_html('ExplainerPlugin_Admin class not found'));
            }
            
            $admin = ExplainerPlugin_Admin::get_instance();
            $reflection = new ReflectionClass($admin);
            
            // Check for consolidated method
            if (!$reflection->hasMethod('process_provider_api_key_save')) {
                throw new \Exception(esc_html('Consolidated API key processing method not found'));
            }
            
            // Check that old duplicate methods don't exist or delegate to new method
            $old_methods = array(
                'process_claude_api_key_save',
                'process_openrouter_api_key_save',
                'process_gemini_api_key_save'
            );
            
            $duplicate_found = false;
            foreach ($old_methods as $method) {
                if ($reflection->hasMethod($method)) {
                    $method_obj = $reflection->getMethod($method);
                    $method_source = $this->get_method_source($method_obj);
                    
                    // Check if method delegates to consolidated method
                    if (strpos($method_source, 'process_provider_api_key_save') === false) {
                        $duplicate_found = true;
                        break;
                    }
                }
            }
            
            if ($duplicate_found) {
                throw new \Exception(esc_html('Duplicate API key processing methods still exist'));
            }
            
            $this->add_test_result($test_name, 'passed', 'API key processing successfully consolidated');
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'failed', $e->getMessage());
        }
    }
    
    /**
     * Test dynamic provider configuration
     */
    private function test_dynamic_provider_configuration() {
        $test_name = 'Dynamic Provider Configuration';
        $this->log_test_start($test_name);
        
        try {
            // Test AJAX endpoint exists
            if (!class_exists('ExplainerPlugin_Admin')) {
                throw new \Exception(esc_html('ExplainerPlugin_Admin class not found'));
            }
            
            $admin = ExplainerPlugin_Admin::get_instance();
            $reflection = new ReflectionClass($admin);
            
            if (!$reflection->hasMethod('handle_get_provider_config')) {
                throw new \Exception(esc_html('Dynamic provider configuration AJAX handler not found'));
            }
            
            // Test Provider Config class exists
            if (!class_exists('ExplainerPlugin_Provider_Config')) {
                throw new \Exception(esc_html('ExplainerPlugin_Provider_Config class not found'));
            }
            
            // Test configuration retrieval
            $config = ExplainerPlugin_Provider_Config::get_provider_config();
            
            if (empty($config)) {
                throw new \Exception(esc_html('Provider configuration is empty'));
            }
            
            // Validate configuration structure
            foreach ($config as $provider_key => $provider_data) {
                if (!isset($provider_data['name']) || !isset($provider_data['models'])) {
                    throw new \Exception(esc_html("Invalid configuration structure for provider: $provider_key"));
                }
            }
            
            $this->add_test_result($test_name, 'passed', 'Dynamic provider configuration working correctly');
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'failed', $e->getMessage());
        }
    }
    
    /**
     * Test JavaScript hardcoding elimination
     */
    private function test_javascript_hardcoding_elimination() {
        $test_name = 'JavaScript Hardcoding Elimination';
        $this->log_test_start($test_name);
        
        try {
            $js_file = EXPLAINER_PLUGIN_PATH . 'assets/js/admin-settings.js';
            
            if (!file_exists($js_file)) {
                throw new \Exception(esc_html('admin-settings.js file not found'));
            }
            
            $js_content = file_get_contents($js_file);
            
            // Check for hardcoded provider data removal
            if (strpos($js_content, 'const modelLabels = {') !== false) {
                throw new \Exception(esc_html('Hardcoded modelLabels object still exists'));
            }
            
            // Check for dynamic loading implementation
            if (strpos($js_content, 'loadProviderConfiguration') === false) {
                throw new \Exception(esc_html('Dynamic provider configuration loading not implemented'));
            }
            
            // Check for AJAX call to get provider config
            if (strpos($js_content, 'wp_ai_explainer_get_provider_config') === false) {
                throw new \Exception(esc_html('AJAX call for provider configuration not found'));
            }
            
            $this->add_test_result($test_name, 'passed', 'JavaScript hardcoding successfully eliminated');
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'failed', $e->getMessage());
        }
    }
    
    /**
     * Test Provider Config class functionality
     */
    private function test_provider_config_class() {
        $test_name = 'Provider Config Class';
        $this->log_test_start($test_name);
        
        try {
            if (!class_exists('ExplainerPlugin_Provider_Config')) {
                throw new \Exception(esc_html('ExplainerPlugin_Provider_Config class not found'));
            }
            
            // Test static methods exist
            $methods = array(
                'get_provider_config',
                'get_provider_config_by_key',
                'get_provider_names',
                'get_provider_models',
                'validate_provider_config'
            );
            
            $reflection = new ReflectionClass('ExplainerPlugin_Provider_Config');
            foreach ($methods as $method) {
                if (!$reflection->hasMethod($method)) {
                    throw new \Exception(esc_html("Method $method not found in Provider Config class"));
                }
            }
            
            // Test functionality
            $config = ExplainerPlugin_Provider_Config::get_provider_config();
            if (empty($config)) {
                throw new \Exception(esc_html('Provider configuration is empty'));
            }
            
            $names = ExplainerPlugin_Provider_Config::get_provider_names();
            if (empty($names)) {
                throw new \Exception(esc_html('Provider names are empty'));
            }
            
            $this->add_test_result($test_name, 'passed', 'Provider Config class working correctly');
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'failed', $e->getMessage());
        }
    }
    
    /**
     * Test HTTP Client abstraction
     */
    private function test_http_client_abstraction() {
        $test_name = 'HTTP Client Abstraction';
        $this->log_test_start($test_name);
        
        try {
            if (!class_exists('ExplainerPlugin_HTTP_Client')) {
                throw new \Exception(esc_html('ExplainerPlugin_HTTP_Client class not found'));
            }
            
            // Test client instantiation
            $client = new ExplainerPlugin_HTTP_Client();
            
            // Test required methods exist
            $methods = array('post', 'get', 'parse_json_response', 'is_rate_limited', 'is_auth_error');
            $reflection = new ReflectionClass($client);
            
            foreach ($methods as $method) {
                if (!$reflection->hasMethod($method)) {
                    throw new \Exception(esc_html("Method $method not found in HTTP Client class"));
                }
            }
            
            // Test factory method
            if (!$reflection->hasMethod('create_for_provider')) {
                throw new \Exception(esc_html('create_for_provider factory method not found'));
            }
            
            $this->add_test_result($test_name, 'passed', 'HTTP Client abstraction implemented correctly');
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'failed', $e->getMessage());
        }
    }
    
    /**
     * Test configuration centralization
     */
    private function test_configuration_centralization() {
        $test_name = 'Configuration Centralization';
        $this->log_test_start($test_name);
        
        try {
            // Test that provider factory uses centralized config
            if (!class_exists('ExplainerPlugin_Provider_Factory')) {
                throw new \Exception(esc_html('ExplainerPlugin_Provider_Factory class not found'));
            }
            
            $reflection = new ReflectionClass('ExplainerPlugin_Provider_Factory');
            if (!$reflection->hasMethod('get_available_providers_config')) {
                throw new \Exception(esc_html('Centralized config method not found in Provider Factory'));
            }
            
            // Test configuration consistency
            $factory_providers = ExplainerPlugin_Provider_Factory::get_available_providers_config();
            $config_providers = ExplainerPlugin_Provider_Config::get_provider_config();
            
            foreach ($factory_providers as $key => $class_name) {
                if (!isset($config_providers[$key])) {
                    throw new \Exception(esc_html("Provider $key missing from centralized configuration"));
                }
            }
            
            $this->add_test_result($test_name, 'passed', 'Configuration successfully centralized');
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'failed', $e->getMessage());
        }
    }
    
    /**
     * Test cost calculation strategy implementation
     */
    private function test_cost_calculation_strategy() {
        $test_name = 'Cost Calculation Strategy';
        $this->log_test_start($test_name);
        
        try {
            // Test strategy interface exists
            if (!interface_exists('ExplainerPlugin_Cost_Strategy_Interface')) {
                throw new \Exception(esc_html('Cost Strategy Interface not found'));
            }
            
            // Test cost calculator exists
            if (!class_exists('ExplainerPlugin_Cost_Calculator')) {
                throw new \Exception(esc_html('Cost Calculator class not found'));
            }
            
            // Test strategy registration and retrieval
            $reflection = new ReflectionClass('ExplainerPlugin_Cost_Calculator');
            $required_methods = array('register_strategy', 'get_strategy', 'calculate_cost');
            
            foreach ($required_methods as $method) {
                if (!$reflection->hasMethod($method)) {
                    throw new \Exception(esc_html("Method $method not found in Cost Calculator"));
                }
            }
            
            // Test strategy wrapper exists
            if (!class_exists('ExplainerPlugin_Provider_Cost_Strategy')) {
                throw new \Exception(esc_html('Provider Cost Strategy wrapper not found'));
            }
            
            $this->add_test_result($test_name, 'passed', 'Cost calculation strategy pattern implemented correctly');
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'failed', $e->getMessage());
        }
    }
    
    /**
     * Test provider integration
     */
    private function test_provider_integration() {
        $test_name = 'Provider Integration';
        $this->log_test_start($test_name);
        
        try {
            // Test that all new providers are properly registered
            $expected_providers = array('openai', 'claude', 'openrouter', 'gemini');
            $available_providers = ExplainerPlugin_Provider_Factory::get_available_providers_config();
            
            foreach ($expected_providers as $provider_key) {
                if (!isset($available_providers[$provider_key])) {
                    throw new \Exception(esc_html("Provider $provider_key not properly registered"));
                }
                
                // Test provider instantiation
                $provider = ExplainerPlugin_Provider_Factory::get_provider($provider_key);
                if (!$provider) {
                    throw new \Exception(esc_html("Provider $provider_key cannot be instantiated"));
                }
                
                // Test provider has required methods
                if (!method_exists($provider, 'get_name') || !method_exists($provider, 'get_models')) {
                    throw new \Exception(esc_html("Provider $provider_key missing required methods"));
                }
            }
            
            $this->add_test_result($test_name, 'passed', 'All providers properly integrated');
            
        } catch (Exception $e) {
            $this->add_test_result($test_name, 'failed', $e->getMessage());
        }
    }
    
    /**
     * Add test result
     * 
     * @param string $test_name Test name
     * @param string $status Test status (passed, failed, warning)
     * @param string $message Test message
     */
    private function add_test_result($test_name, $status, $message) {
        $this->results['tests'][] = array(
            'name' => $test_name,
            'status' => $status,
            'message' => $message,
            'timestamp' => current_time('mysql')
        );
        
        $this->results['summary']['total_tests']++;
        
        switch ($status) {
            case 'passed':
                $this->results['summary']['passed']++;
                break;
            case 'failed':
                $this->results['summary']['failed']++;
                break;
            case 'warning':
                $this->results['summary']['warnings']++;
                break;
        }
        
        $this->logger->info("DRY Validation Test: $test_name - $status", array('message' => $message), 'DRY_Validator');
    }
    
    /**
     * Log test start
     * 
     * @param string $test_name Test name
     */
    private function log_test_start($test_name) {
        $this->logger->debug("Starting DRY validation test: $test_name", array(), 'DRY_Validator');
    }
    
    /**
     * Generate validation summary
     */
    private function generate_summary() {
        $summary = $this->results['summary'];
        $success_rate = $summary['total_tests'] > 0 ? ($summary['passed'] / $summary['total_tests']) * 100 : 0;
        
        $this->results['summary']['success_rate'] = round($success_rate, 2);
        $this->results['summary']['overall_status'] = $summary['failed'] > 0 ? 'failed' : 
            ($summary['warnings'] > 0 ? 'warning' : 'passed');
        
        $this->logger->info('DRY Validation Summary', $this->results['summary'], 'DRY_Validator');
    }
    
    /**
     * Get method source code (simplified)
     * 
     * @param ReflectionMethod $method Method reflection
     * @return string Method source code
     */
    private function get_method_source(ReflectionMethod $method) {
        $filename = $method->getFileName();
        $start_line = $method->getStartLine() - 1;
        $end_line = $method->getEndLine();
        $length = $end_line - $start_line;
        
        $source = file($filename);
        return implode('', array_slice($source, $start_line, $length));
    }
    
    /**
     * Generate HTML report
     * 
     * @return string HTML report
     */
    public function generate_html_report() {
        if (empty($this->results)) {
            return '<p>No validation results available. Run validation first.</p>';
        }
        
        $summary = $this->results['summary'];
        $status_class = $summary['overall_status'] === 'passed' ? 'success' : 
            ($summary['overall_status'] === 'warning' ? 'warning' : 'error');
        
        $html = '<div class="dry-validation-report">';
        $html .= '<h2>DRY Refactoring Validation Report</h2>';
        $html .= '<div class="validation-summary ' . $status_class . '">';
        $html .= '<p><strong>Overall Status:</strong> ' . ucfirst($summary['overall_status']) . '</p>';
        $html .= '<p><strong>Success Rate:</strong> ' . $summary['success_rate'] . '%</p>';
        $html .= '<p><strong>Tests:</strong> ' . $summary['passed'] . ' passed, ' . $summary['failed'] . ' failed, ' . $summary['warnings'] . ' warnings</p>';
        $html .= '</div>';
        
        $html .= '<table class="validation-results">';
        $html .= '<thead><tr><th>Test</th><th>Status</th><th>Message</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($this->results['tests'] as $test) {
            $status_icon = $test['status'] === 'passed' ? '✅' : 
                ($test['status'] === 'warning' ? '⚠️' : '❌');
            
            $html .= '<tr class="' . $test['status'] . '">';
            $html .= '<td>' . esc_html($test['name']) . '</td>';
            $html .= '<td>' . $status_icon . ' ' . ucfirst($test['status']) . '</td>';
            $html .= '<td>' . esc_html($test['message']) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= '<p><small>Generated on ' . $this->results['timestamp'] . '</small></p>';
        $html .= '</div>';
        
        return $html;
    }
}