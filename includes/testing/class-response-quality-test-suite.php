<?php
/**
 * AI Response Quality Test Suite
 *
 * Comprehensive testing framework for validating AI provider response quality
 * with enhanced context integration.
 *
 * @package WP_AI_Explainer
 * @subpackage Testing
 * @since 3.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test suite for AI response quality validation
 */
class ExplainerPlugin_Response_Quality_Test_Suite {

    /**
     * Quality validator instance
     */
    private $validator;

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Debug logger instance
     */
    private $debug_logger;

    /**
     * API proxy for making actual requests
     */
    private $api_proxy;

    /**
     * Constructor
     */
    public function __construct() {
        $this->validator = ExplainerPlugin_Quality_Validator::get_instance();
        $this->logger = ExplainerPlugin_Logger::get_instance();
        $this->debug_logger = ExplainerPlugin_Debug_Logger::get_instance();
        $this->api_proxy = new ExplainerPlugin_API_Proxy();
    }

    /**
     * Get singleton instance
     *
     * @return ExplainerPlugin_Response_Quality_Test_Suite
     */
    public static function get_instance() {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * Run comprehensive quality test suite
     *
     * @param array $test_config Test configuration options
     * @return array Complete test results
     */
    public function run_comprehensive_tests($test_config = array()) {
        $test_start = microtime(true);
        
        $default_config = array(
            'providers' => array('openai', 'claude'),
            'reading_levels' => array('very_simple', 'simple', 'standard', 'detailed', 'expert'),
            'test_scenarios' => $this->get_default_test_scenarios(),
            'include_cross_provider_comparison' => true,
            'include_reading_level_validation' => true,
            'include_context_leakage_tests' => true,
            'generate_detailed_report' => true
        );
        
        $config = array_merge($default_config, $test_config);
        
        $results = array(
            'test_summary' => array(
                'started_at' => current_time('mysql'),
                'total_scenarios' => count($config['test_scenarios']),
                'providers_tested' => count($config['providers']),
                'reading_levels_tested' => count($config['reading_levels']),
                'total_tests_run' => 0,
                'tests_passed' => 0,
                'tests_failed' => 0
            ),
            'scenario_results' => array(),
            'cross_provider_comparison' => array(),
            'reading_level_analysis' => array(),
            'context_leakage_analysis' => array(),
            'quality_report' => array(),
            'recommendations' => array()
        );

        $this->debug_logger->log('Starting comprehensive quality test suite', array(
            'providers' => $config['providers'],
            'scenarios' => count($config['test_scenarios']),
            'reading_levels' => count($config['reading_levels'])
        ));

        try {
            // Run scenario tests
            foreach ($config['test_scenarios'] as $scenario_id => $scenario) {
                $this->debug_logger->log("Running test scenario: {$scenario_id}", array(
                    'selected_term' => $scenario['selected_term'],
                    'context_type' => $scenario['context']['type'] ?? 'standard'
                ));

                $scenario_results = $this->run_scenario_tests($scenario, $config);
                $results['scenario_results'][$scenario_id] = $scenario_results;
                
                // Update summary counts
                foreach ($scenario_results as $test_result) {
                    $results['test_summary']['total_tests_run']++;
                    
                    if (isset($test_result['overall_score']) && $test_result['overall_score'] >= 70) {
                        $results['test_summary']['tests_passed']++;
                    } else {
                        $results['test_summary']['tests_failed']++;
                    }
                }
            }

            // Cross-provider comparison
            if ($config['include_cross_provider_comparison']) {
                $results['cross_provider_comparison'] = $this->run_cross_provider_comparison($config);
            }

            // Reading level validation
            if ($config['include_reading_level_validation']) {
                $results['reading_level_analysis'] = $this->run_reading_level_validation($config);
            }

            // Context leakage tests
            if ($config['include_context_leakage_tests']) {
                $results['context_leakage_analysis'] = $this->run_context_leakage_tests($config);
            }

            // Generate quality report
            if ($config['generate_detailed_report']) {
                $all_validations = $this->extract_all_validations($results['scenario_results']);
                $results['quality_report'] = $this->validator->generate_quality_report($all_validations);
            }

            // Generate comprehensive recommendations
            $results['recommendations'] = $this->generate_comprehensive_recommendations($results);

        } catch (Exception $e) {
            $this->logger->error('Comprehensive test suite failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));

            $results['error'] = $e->getMessage();
        }

        $test_time = microtime(true) - $test_start;
        $results['test_summary']['completed_at'] = current_time('mysql');
        $results['test_summary']['total_duration_seconds'] = round($test_time, 2);

        $this->debug_logger->log('Comprehensive test suite completed', array(
            'total_tests' => $results['test_summary']['total_tests_run'],
            'pass_rate' => round($results['test_summary']['tests_passed'] / max(1, $results['test_summary']['total_tests_run']) * 100, 1),
            'duration_seconds' => $results['test_summary']['total_duration_seconds']
        ));

        return $results;
    }

    /**
     * Get default test scenarios for comprehensive testing
     *
     * @return array Default test scenarios
     */
    private function get_default_test_scenarios() {
        return array(
            'technical_term_with_context' => array(
                'selected_term' => 'API',
                'context' => array(
                    'type' => 'enhanced',
                    'title' => 'Understanding Web Development Technologies',
                    'paragraph' => 'Modern web applications rely on various technologies including databases, servers, and APIs to function effectively. Understanding these components is essential for developers.',
                    'before' => 'web applications use',
                    'after' => 'to communicate with servers',
                    'full' => 'web applications use API to communicate with servers'
                ),
                'previous_terms' => array('database', 'server', 'HTTP'),
                'expected_focus' => 'API only, not database, server, or HTTP'
            ),
            'simple_term_complex_context' => array(
                'selected_term' => 'cache',
                'context' => array(
                    'type' => 'enhanced',
                    'title' => 'Database Optimization and Performance Strategies',
                    'paragraph' => 'Database performance optimization involves indexing, query optimization, connection pooling, caching strategies, and proper schema design to achieve maximum efficiency.',
                    'before' => 'strategies include',
                    'after' => 'and connection pooling',
                    'full' => 'strategies include cache and connection pooling'
                ),
                'previous_terms' => array('database', 'indexing', 'query optimization'),
                'expected_focus' => 'cache only, avoid explaining database concepts'
            ),
            'acronym_with_expansion' => array(
                'selected_term' => 'CSS',
                'context' => array(
                    'type' => 'enhanced',
                    'title' => 'Frontend Development Best Practices',
                    'paragraph' => 'Frontend development involves HTML for structure, CSS for styling, and JavaScript for interactivity. These technologies work together to create user interfaces.',
                    'before' => 'involves HTML for structure,',
                    'after' => 'for styling, and JavaScript',
                    'full' => 'involves HTML for structure, CSS for styling, and JavaScript'
                ),
                'previous_terms' => array('HTML', 'JavaScript', 'DOM'),
                'expected_focus' => 'CSS only, not HTML or JavaScript'
            ),
            'domain_specific_term' => array(
                'selected_term' => 'blockchain',
                'context' => array(
                    'type' => 'enhanced',
                    'title' => 'Cryptocurrency and Digital Finance',
                    'paragraph' => 'Digital finance technologies including cryptocurrency, blockchain, smart contracts, and decentralized applications are reshaping traditional financial systems.',
                    'before' => 'technologies including cryptocurrency,',
                    'after' => ', smart contracts, and decentralized',
                    'full' => 'technologies including cryptocurrency, blockchain, smart contracts, and decentralized'
                ),
                'previous_terms' => array('cryptocurrency', 'smart contract', 'token'),
                'expected_focus' => 'blockchain only, not cryptocurrency or smart contracts'
            ),
            'term_with_similar_context' => array(
                'selected_term' => 'authentication',
                'context' => array(
                    'type' => 'enhanced',
                    'title' => 'Web Security and User Management',
                    'paragraph' => 'Web security involves authentication, authorization, session management, and encryption to protect user data and system resources.',
                    'before' => 'security involves',
                    'after' => ', authorization, session management',
                    'full' => 'security involves authentication, authorization, session management'
                ),
                'previous_terms' => array('authorization', 'session', 'encryption'),
                'expected_focus' => 'authentication only, distinguish from authorization'
            )
        );
    }

    /**
     * Run tests for a specific scenario
     *
     * @param array $scenario Test scenario configuration
     * @param array $config Overall test configuration
     * @return array Scenario test results
     */
    private function run_scenario_tests($scenario, $config) {
        $scenario_results = array();
        
        foreach ($config['providers'] as $provider) {
            foreach ($config['reading_levels'] as $reading_level) {
                $test_key = "{$provider}_{$reading_level}";
                
                try {
                    // Get AI response (would make actual API call in production)
                    $response = $this->get_test_response($scenario, $provider, $reading_level);
                    
                    // Validate response quality
                    $validation = $this->validator->validate_response_focus(
                        $scenario['selected_term'],
                        $response,
                        $scenario['context'],
                        $provider,
                        $reading_level
                    );
                    
                    $validation['test_metadata'] = array(
                        'scenario' => $scenario,
                        'provider' => $provider,
                        'reading_level' => $reading_level,
                        'response' => $response,
                        'expected_focus' => $scenario['expected_focus'] ?? 'N/A'
                    );
                    
                    $scenario_results[$test_key] = $validation;
                    
                } catch (Exception $e) {
                    $this->logger->error("Scenario test failed: {$test_key}", array(
                        'error' => $e->getMessage(),
                        'selected_term' => $scenario['selected_term']
                    ));
                    
                    $scenario_results[$test_key] = array(
                        'error' => $e->getMessage(),
                        'overall_score' => 0,
                        'test_metadata' => array(
                            'scenario' => $scenario,
                            'provider' => $provider,
                            'reading_level' => $reading_level
                        )
                    );
                }
            }
        }
        
        return $scenario_results;
    }

    /**
     * Get test response for scenario (simulated for now, would integrate with real API)
     *
     * @param array $scenario Test scenario
     * @param string $provider AI provider
     * @param string $reading_level Target reading level
     * @return string AI response
     */
    private function get_test_response($scenario, $provider, $reading_level) {
        // In production, this would make actual API calls
        // For testing, we'll simulate responses based on the scenario
        
        $selected_term = $scenario['selected_term'];
        $context = $scenario['context'];
        
        // Simulate different provider behaviors
        $provider_behaviors = array(
            'openai' => array(
                'focus_strength' => 0.8,
                'context_leakage_tendency' => 0.2,
                'verbosity' => 'medium'
            ),
            'claude' => array(
                'focus_strength' => 0.9,
                'context_leakage_tendency' => 0.1,
                'verbosity' => 'concise'
            )
        );
        
        $behavior = $provider_behaviors[$provider] ?? $provider_behaviors['openai'];
        
        // Generate test response based on provider behavior and reading level
        return $this->generate_test_response($selected_term, $context, $reading_level, $behavior);
    }

    /**
     * Generate test response based on parameters
     *
     * @param string $selected_term Selected term
     * @param array $context Context information
     * @param string $reading_level Target reading level
     * @param array $behavior Provider behavior simulation
     * @return string Generated response
     */
    private function generate_test_response($selected_term, $context, $reading_level, $behavior) {
        // Base responses by reading level
        $base_responses = array(
            'very_simple' => "{$selected_term} is a simple thing that helps with computers.",
            'simple' => "{$selected_term} is something used in technology to make things work better.",
            'standard' => "{$selected_term} is a technological concept that serves specific functions in computing systems.",
            'detailed' => "{$selected_term} represents a specialized technology component that facilitates specific operations within complex computing environments.",
            'expert' => "{$selected_term} constitutes a sophisticated technological abstraction that enables optimized resource utilization and system interoperability."
        );
        
        $response = $base_responses[$reading_level] ?? $base_responses['standard'];
        
        // Simulate context leakage based on behavior
        if ($behavior['context_leakage_tendency'] > 0.3) {
            // Add context term explanation (this is what we want to detect and prevent)
            if (isset($context['previous_terms']) && !empty($context['previous_terms'])) {
                $context_term = $context['previous_terms'][0];
                $response .= " It works together with {$context_term}, which is another important technology component.";
            }
        }
        
        // Adjust verbosity
        if ($behavior['verbosity'] === 'concise') {
            // Keep response short
            $words = explode(' ', $response);
            $response = implode(' ', array_slice($words, 0, 15));
        } elseif ($behavior['verbosity'] === 'verbose') {
            // Add more detail
            $response .= " This technology is widely used across various applications and provides significant benefits for system performance and user experience.";
        }
        
        return trim($response);
    }

    /**
     * Run cross-provider comparison analysis
     *
     * @param array $config Test configuration
     * @return array Cross-provider comparison results
     */
    private function run_cross_provider_comparison($config) {
        $comparison_results = array();
        $test_scenario = reset($config['test_scenarios']); // Use first scenario for comparison
        
        foreach ($config['reading_levels'] as $reading_level) {
            $comparison_key = "comparison_{$reading_level}";
            
            try {
                $comparison = $this->validator->compare_providers(
                    $test_scenario['selected_term'],
                    $test_scenario['context'],
                    $reading_level,
                    $config['providers']
                );
                
                $comparison_results[$comparison_key] = $comparison;
                
            } catch (Exception $e) {
                $this->logger->error("Cross-provider comparison failed for {$reading_level}", array(
                    'error' => $e->getMessage()
                ));
                
                $comparison_results[$comparison_key] = array(
                    'error' => $e->getMessage()
                );
            }
        }
        
        return $comparison_results;
    }

    /**
     * Run reading level validation analysis
     *
     * @param array $config Test configuration
     * @return array Reading level analysis results
     */
    private function run_reading_level_validation($config) {
        $reading_level_results = array(
            'consistency_analysis' => array(),
            'complexity_progression' => array(),
            'level_specific_issues' => array()
        );
        
        $test_scenario = reset($config['test_scenarios']);
        
        foreach ($config['providers'] as $provider) {
            $provider_responses = array();
            
            // Collect responses across all reading levels for this provider
            foreach ($config['reading_levels'] as $level) {
                $response = $this->get_test_response($test_scenario, $provider, $level);
                $provider_responses[$level] = $response;
            }
            
            // Analyze consistency and progression
            $reading_level_results['consistency_analysis'][$provider] = 
                $this->analyze_reading_level_consistency($provider_responses);
                
            $reading_level_results['complexity_progression'][$provider] = 
                $this->analyze_complexity_progression($provider_responses);
        }
        
        return $reading_level_results;
    }

    /**
     * Analyze reading level consistency for a provider
     *
     * @param array $responses Responses across reading levels
     * @return array Consistency analysis
     */
    private function analyze_reading_level_consistency($responses) {
        $analysis = array(
            'complexity_increases_appropriately' => true,
            'length_progression' => array(),
            'vocabulary_progression' => array(),
            'issues' => array()
        );
        
        $prev_length = 0;
        $prev_avg_word_length = 0;
        
        foreach ($responses as $level => $response) {
            $length = strlen($response);
            $words = preg_split('/\s+/', $response);
            $avg_word_length = array_sum(array_map('strlen', $words)) / count($words);
            
            $analysis['length_progression'][$level] = $length;
            $analysis['vocabulary_progression'][$level] = round($avg_word_length, 1);
            
            // Check if complexity is increasing appropriately
            if ($prev_length > 0 && $length < $prev_length * 0.8) {
                $analysis['complexity_increases_appropriately'] = false;
                $analysis['issues'][] = "Response length decreased inappropriately from previous level to {$level}";
            }
            
            if ($prev_avg_word_length > 0 && $avg_word_length < $prev_avg_word_length * 0.9) {
                $analysis['complexity_increases_appropriately'] = false;
                $analysis['issues'][] = "Word complexity decreased inappropriately at {$level} level";
            }
            
            $prev_length = $length;
            $prev_avg_word_length = $avg_word_length;
        }
        
        return $analysis;
    }

    /**
     * Analyze complexity progression across reading levels
     *
     * @param array $responses Responses across reading levels
     * @return array Complexity progression analysis
     */
    private function analyze_complexity_progression($responses) {
        $analysis = array(
            'progression_score' => 0,
            'level_analysis' => array(),
            'overall_assessment' => 'unknown'
        );
        
        $expected_complexity_order = array('very_simple', 'simple', 'standard', 'detailed', 'expert');
        $complexity_scores = array();
        
        foreach ($responses as $level => $response) {
            $complexity_score = $this->calculate_text_complexity($response);
            $complexity_scores[$level] = $complexity_score;
            
            $analysis['level_analysis'][$level] = array(
                'complexity_score' => $complexity_score,
                'word_count' => str_word_count($response),
                'character_count' => strlen($response)
            );
        }
        
        // Check if complexity progresses appropriately
        $progression_correct = true;
        for ($i = 1; $i < count($expected_complexity_order); $i++) {
            $current_level = $expected_complexity_order[$i];
            $prev_level = $expected_complexity_order[$i - 1];
            
            if (isset($complexity_scores[$current_level]) && isset($complexity_scores[$prev_level])) {
                if ($complexity_scores[$current_level] <= $complexity_scores[$prev_level]) {
                    $progression_correct = false;
                    break;
                }
            }
        }
        
        $analysis['progression_score'] = $progression_correct ? 100 : 50;
        $analysis['overall_assessment'] = $progression_correct ? 'appropriate' : 'needs_improvement';
        
        return $analysis;
    }

    /**
     * Calculate basic text complexity score
     *
     * @param string $text Text to analyze
     * @return float Complexity score
     */
    private function calculate_text_complexity($text) {
        $words = preg_split('/\s+/', $text);
        $sentences = preg_split('/[.!?]+/', $text);
        $sentences = array_filter($sentences, 'trim');
        
        $avg_word_length = array_sum(array_map('strlen', $words)) / count($words);
        $avg_sentence_length = count($words) / max(1, count($sentences));
        
        // Simple complexity calculation
        return $avg_word_length * 0.6 + $avg_sentence_length * 0.4;
    }

    /**
     * Run context leakage tests
     *
     * @param array $config Test configuration
     * @return array Context leakage test results
     */
    private function run_context_leakage_tests($config) {
        $leakage_results = array(
            'scenarios_tested' => count($config['test_scenarios']),
            'total_leakage_incidents' => 0,
            'leakage_by_provider' => array(),
            'leakage_by_scenario' => array(),
            'common_leakage_patterns' => array()
        );
        
        foreach ($config['providers'] as $provider) {
            $leakage_results['leakage_by_provider'][$provider] = array(
                'incidents' => 0,
                'leakage_score_avg' => 0
            );
        }
        
        $total_tests = 0;
        $total_leakage_score = 0;
        
        foreach ($config['test_scenarios'] as $scenario_id => $scenario) {
            $scenario_leakage = array(
                'incidents' => 0,
                'details' => array()
            );
            
            foreach ($config['providers'] as $provider) {
                $response = $this->get_test_response($scenario, $provider, 'standard');
                
                $leakage_analysis = $this->validator->analyze_context_leakage(
                    $scenario['selected_term'],
                    $response,
                    $scenario['context']
                );
                
                if ($leakage_analysis['score'] < 80) {
                    $leakage_results['total_leakage_incidents']++;
                    $leakage_results['leakage_by_provider'][$provider]['incidents']++;
                    $scenario_leakage['incidents']++;
                    $scenario_leakage['details'][$provider] = $leakage_analysis;
                }
                
                $total_leakage_score += $leakage_analysis['score'];
                $total_tests++;
            }
            
            $leakage_results['leakage_by_scenario'][$scenario_id] = $scenario_leakage;
        }
        
        // Calculate averages
        if ($total_tests > 0) {
            foreach ($config['providers'] as $provider) {
                $provider_tests = count($config['test_scenarios']);
                $leakage_results['leakage_by_provider'][$provider]['leakage_score_avg'] = 
                    round($total_leakage_score / $provider_tests, 1);
            }
        }
        
        return $leakage_results;
    }

    /**
     * Extract all validation results from scenario results
     *
     * @param array $scenario_results All scenario test results
     * @return array Flattened validation results
     */
    private function extract_all_validations($scenario_results) {
        $all_validations = array();
        
        foreach ($scenario_results as $scenario_id => $scenario_tests) {
            foreach ($scenario_tests as $test_key => $validation) {
                if (!isset($validation['error'])) {
                    $all_validations["{$scenario_id}_{$test_key}"] = $validation;
                }
            }
        }
        
        return $all_validations;
    }

    /**
     * Generate comprehensive recommendations based on all test results
     *
     * @param array $results Complete test results
     * @return array Comprehensive recommendations
     */
    private function generate_comprehensive_recommendations($results) {
        $recommendations = array(
            'immediate_actions' => array(),
            'system_improvements' => array(),
            'monitoring_suggestions' => array(),
            'provider_specific' => array()
        );
        
        // Analyze pass rate
        $pass_rate = $results['test_summary']['tests_passed'] / 
                    max(1, $results['test_summary']['total_tests_run']);
                    
        if ($pass_rate < 0.8) {
            $recommendations['immediate_actions'][] = 
                'Overall pass rate is below 80% - immediate prompt template review needed';
        }
        
        // Analyze cross-provider results
        if (!empty($results['cross_provider_comparison'])) {
            foreach ($results['cross_provider_comparison'] as $comparison) {
                if (isset($comparison['comparison_summary']) && 
                    !$comparison['comparison_summary']['consistent_quality']) {
                    $recommendations['system_improvements'][] = 
                        'Inconsistent quality across providers - consider provider-specific prompt optimisation';
                }
            }
        }
        
        // Analyze context leakage
        if (!empty($results['context_leakage_analysis']) && 
            $results['context_leakage_analysis']['total_leakage_incidents'] > 0) {
            $recommendations['immediate_actions'][] = 
                'Context leakage detected - strengthen prompt instructions to focus only on selected terms';
        }
        
        // Reading level analysis
        if (!empty($results['reading_level_analysis'])) {
            foreach ($results['reading_level_analysis']['consistency_analysis'] as $provider => $analysis) {
                if (!$analysis['complexity_increases_appropriately']) {
                    $recommendations['provider_specific'][$provider][] = 
                        'Reading level complexity progression needs improvement';
                }
            }
        }
        
        // Quality report recommendations
        if (!empty($results['quality_report']['recommendations'])) {
            $recommendations['system_improvements'] = array_merge(
                $recommendations['system_improvements'],
                $results['quality_report']['recommendations']
            );
        }
        
        // Add monitoring suggestions
        $recommendations['monitoring_suggestions'] = array(
            'Implement automated quality testing in CI/CD pipeline',
            'Set up alerts for quality score drops below 70%',
            'Monitor context leakage incidents weekly',
            'Track reading level compliance across providers'
        );
        
        return $recommendations;
    }

    /**
     * Export test results for external analysis
     *
     * @param array $results Test results
     * @param string $format Export format (json, csv)
     * @return string Exported data
     */
    public function export_results($results, $format = 'json') {
        switch ($format) {
            case 'json':
                return json_encode($results, JSON_PRETTY_PRINT);
                
            case 'csv':
                return $this->convert_results_to_csv($results);
                
            default:
                throw new InvalidArgumentException(esc_html("Unsupported export format: {$format}"));
        }
    }

    /**
     * Convert results to CSV format
     *
     * @param array $results Test results
     * @return string CSV data
     */
    private function convert_results_to_csv($results) {
        $csv_data = array();
        $csv_data[] = array(
            'Scenario', 'Provider', 'Reading Level', 'Overall Score', 
            'Focus Score', 'Leakage Score', 'Quality Score', 'Issues'
        );
        
        foreach ($results['scenario_results'] as $scenario_id => $scenario_tests) {
            foreach ($scenario_tests as $test_key => $validation) {
                if (!isset($validation['error'])) {
                    $parts = explode('_', $test_key, 2);
                    $provider = $parts[0];
                    $reading_level = $parts[1] ?? 'unknown';
                    
                    $csv_data[] = array(
                        $scenario_id,
                        $provider,
                        $reading_level,
                        $validation['overall_score'] ?? 0,
                        $validation['term_focus_score'] ?? 0,
                        $validation['context_leakage_score'] ?? 0,
                        $validation['response_quality_score'] ?? 0,
                        implode('; ', $validation['issues'] ?? array())
                    );
                }
            }
        }
        
        // Convert to CSV string
        $output = '';
        foreach ($csv_data as $row) {
            $output .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }
        
        return $output;
    }
}