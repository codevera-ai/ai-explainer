<?php
/**
 * AI Response Quality Validator
 *
 * Validates AI provider responses for quality, focus, and compliance
 * with enhanced context requirements.
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
 * Quality validation utilities for AI responses
 */
class ExplainerPlugin_Quality_Validator {

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Debug logger instance
     */
    private $debug_logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = ExplainerPlugin_Logger::get_instance();
        $this->debug_logger = ExplainerPlugin_Debug_Logger::get_instance();
    }

    /**
     * Get singleton instance
     *
     * @return ExplainerPlugin_Quality_Validator
     */
    public static function get_instance() {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * Validate AI response quality for selected term focus
     *
     * @param string $selected_term The term that was selected
     * @param string $ai_response The AI's explanation response
     * @param array $context Enhanced context provided to AI
     * @param string $provider AI provider used
     * @param string $reading_level Target reading level
     * @return array Validation results
     */
    public function validate_response_focus($selected_term, $ai_response, $context = array(), $provider = 'unknown', $reading_level = 'standard') {
        $validation_start = microtime(true);
        
        $results = array(
            'term_focus_score' => 0,
            'context_leakage_score' => 0,
            'reading_level_appropriate' => false,
            'response_quality_score' => 0,
            'issues' => array(),
            'strengths' => array(),
            'recommendations' => array(),
            'analysis_details' => array()
        );

        try {
            // Validate term focus
            $focus_analysis = $this->analyze_term_focus($selected_term, $ai_response);
            $results['term_focus_score'] = $focus_analysis['score'];
            $results['analysis_details']['focus'] = $focus_analysis;

            // Check for context term leakage
            $leakage_analysis = $this->analyze_context_leakage($selected_term, $ai_response, $context);
            $results['context_leakage_score'] = $leakage_analysis['score'];
            $results['analysis_details']['leakage'] = $leakage_analysis;

            // Validate reading level appropriateness
            $reading_level_analysis = $this->analyze_reading_level_compliance($ai_response, $reading_level);
            $results['reading_level_appropriate'] = $reading_level_analysis['appropriate'];
            $results['analysis_details']['reading_level'] = $reading_level_analysis;

            // Overall quality assessment
            $quality_analysis = $this->analyze_response_quality($ai_response, $selected_term);
            $results['response_quality_score'] = $quality_analysis['score'];
            $results['analysis_details']['quality'] = $quality_analysis;

            // Compile issues and recommendations
            $results = $this->compile_validation_results($results);

            $validation_time = microtime(true) - $validation_start;

            $this->debug_logger->log('Response quality validation completed', array(
                'selected_term' => $selected_term,
                'provider' => $provider,
                'reading_level' => $reading_level,
                'focus_score' => $results['term_focus_score'],
                'leakage_score' => $results['context_leakage_score'],
                'quality_score' => $results['response_quality_score'],
                'validation_time_ms' => round($validation_time * 1000, 2),
                'issues_count' => count($results['issues']),
                'strengths_count' => count($results['strengths'])
            ));

        } catch (Exception $e) {
            $this->logger->error('Quality validation failed', array(
                'error' => $e->getMessage(),
                'selected_term' => $selected_term,
                'provider' => $provider,
                'response_length' => strlen($ai_response)
            ));

            $results['issues'][] = 'Validation process failed: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Analyze how well the response focuses on the selected term
     *
     * @param string $selected_term Term that should be explained
     * @param string $response AI response
     * @return array Focus analysis results
     */
    private function analyze_term_focus($selected_term, $response) {
        $analysis = array(
            'score' => 0,
            'term_mentions' => 0,
            'first_mention_position' => -1,
            'explanation_directly_addresses_term' => false,
            'issues' => array(),
            'strengths' => array()
        );

        // Normalise term for comparison
        $term_lower = strtolower(trim($selected_term));
        $response_lower = strtolower($response);

        // Count mentions of the selected term
        $analysis['term_mentions'] = substr_count($response_lower, $term_lower);

        // Find first mention position
        $first_pos = strpos($response_lower, $term_lower);
        if ($first_pos !== false) {
            $analysis['first_mention_position'] = $first_pos;
        }

        // Check if response starts by addressing the term
        $response_start = substr($response_lower, 0, 50);
        $analysis['explanation_directly_addresses_term'] = strpos($response_start, $term_lower) !== false;

        // Score calculation
        $score = 0;

        // Term mentioned at least once (+30 points)
        if ($analysis['term_mentions'] > 0) {
            $score += 30;
            $analysis['strengths'][] = 'Selected term is mentioned in the response';
        } else {
            $analysis['issues'][] = 'Selected term is not mentioned in the response';
        }

        // Term mentioned early in response (+20 points)
        if ($analysis['first_mention_position'] >= 0 && $analysis['first_mention_position'] < 100) {
            $score += 20;
            $analysis['strengths'][] = 'Selected term is mentioned early in the response';
        }

        // Response directly addresses the term (+30 points)
        if ($analysis['explanation_directly_addresses_term']) {
            $score += 30;
            $analysis['strengths'][] = 'Response directly addresses the selected term';
        } else {
            $analysis['issues'][] = 'Response does not directly address the selected term';
        }

        // Multiple appropriate mentions (+20 points)
        if ($analysis['term_mentions'] >= 2 && $analysis['term_mentions'] <= 4) {
            $score += 20;
            $analysis['strengths'][] = 'Term is mentioned an appropriate number of times';
        } elseif ($analysis['term_mentions'] > 4) {
            $analysis['issues'][] = 'Term is mentioned too frequently (possible over-repetition)';
        }

        $analysis['score'] = min(100, $score);
        return $analysis;
    }

    /**
     * Analyze if response inappropriately explains context terms
     *
     * @param string $selected_term Term that should be explained
     * @param string $response AI response
     * @param array $context Context provided to AI
     * @return array Leakage analysis results
     */
    private function analyze_context_leakage($selected_term, $response, $context) {
        $analysis = array(
            'score' => 100, // Start at 100 (no leakage) and deduct
            'context_terms_explained' => array(),
            'previous_terms_explained' => array(),
            'title_terms_explained' => array(),
            'issues' => array(),
            'strengths' => array()
        );

        try {
            // Extract context terms for checking
            $context_terms = $this->extract_context_terms($context);
            $response_lower = strtolower($response);
            $selected_term_lower = strtolower($selected_term);

            // Check for explanations of context terms
            foreach ($context_terms as $term_type => $terms) {
                foreach ($terms as $term) {
                    $term_lower = strtolower($term);
                    
                    // Skip if this is the selected term itself
                    if ($term_lower === $selected_term_lower) {
                        continue;
                    }

                    // Check if term is being explained (not just mentioned)
                    if ($this->is_term_being_explained($term, $response)) {
                        $analysis['score'] -= 20; // Deduct points for explaining context terms
                        $analysis[$term_type . '_explained'][] = $term;
                        $analysis['issues'][] = "Response inappropriately explains context term: '$term'";
                    }
                }
            }

            // Ensure score doesn't go below 0
            $analysis['score'] = max(0, $analysis['score']);

            // Add strengths if no leakage detected
            if ($analysis['score'] >= 80) {
                $analysis['strengths'][] = 'Response maintains focus on selected term without explaining context terms';
            }

        } catch (Exception $e) {
            $this->logger->error('Context leakage analysis failed', array(
                'error' => $e->getMessage(),
                'selected_term' => $selected_term
            ));
            $analysis['issues'][] = 'Failed to analyze context leakage: ' . $e->getMessage();
        }

        return $analysis;
    }

    /**
     * Extract terms from context for leakage checking
     *
     * @param array $context Enhanced context object
     * @return array Categorized context terms
     */
    private function extract_context_terms($context) {
        $terms = array(
            'context_terms' => array(),
            'previous_terms' => array(),
            'title_terms' => array()
        );

        if (!is_array($context)) {
            return $terms;
        }

        // Extract terms from title
        if (!empty($context['title'])) {
            $title_words = $this->extract_significant_words($context['title']);
            $terms['title_terms'] = array_merge($terms['title_terms'], $title_words);
        }

        // Extract terms from paragraph context
        if (!empty($context['paragraph'])) {
            $context_words = $this->extract_significant_words($context['paragraph']);
            $terms['context_terms'] = array_merge($terms['context_terms'], $context_words);
        }

        // Extract terms from before/after context
        if (!empty($context['before'])) {
            $before_words = $this->extract_significant_words($context['before']);
            $terms['context_terms'] = array_merge($terms['context_terms'], $before_words);
        }

        if (!empty($context['after'])) {
            $after_words = $this->extract_significant_words($context['after']);
            $terms['context_terms'] = array_merge($terms['context_terms'], $after_words);
        }

        return $terms;
    }

    /**
     * Extract significant words from text (excluding common words)
     *
     * @param string $text Text to analyze
     * @return array Significant words
     */
    private function extract_significant_words($text) {
        // Common words to exclude
        $common_words = array(
            'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
            'a', 'an', 'this', 'that', 'these', 'those', 'is', 'are', 'was', 'were', 'be',
            'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
            'should', 'may', 'might', 'can', 'from', 'up', 'about', 'into', 'through',
            'during', 'before', 'after', 'above', 'below', 'between', 'among', 'all',
            'any', 'both', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'only',
            'own', 'same', 'so', 'than', 'too', 'very', 'just', 'now'
        );

        $words = preg_split('/[^a-zA-Z]+/', strtolower($text));
        $significant_words = array();

        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) >= 3 && !in_array($word, $common_words)) {
                $significant_words[] = $word;
            }
        }

        return array_unique($significant_words);
    }

    /**
     * Check if a term is being explained (not just mentioned) in response
     *
     * @param string $term Term to check
     * @param string $response AI response
     * @return bool Whether term is being explained
     */
    private function is_term_being_explained($term, $response) {
        $term_lower = strtolower($term);
        $response_lower = strtolower($response);

        // Find positions of the term
        $positions = array();
        $offset = 0;
        while (($pos = strpos($response_lower, $term_lower, $offset)) !== false) {
            $positions[] = $pos;
            $offset = $pos + 1;
        }

        // Check if any occurrence is followed by explanation indicators
        $explanation_indicators = array(
            'is', 'are', 'means', 'refers to', 'defined as', 'known as',
            'represents', 'indicates', 'describes', 'involves', 'consists of'
        );

        foreach ($positions as $pos) {
            $after_term = substr($response_lower, $pos + strlen($term_lower), 50);
            
            foreach ($explanation_indicators as $indicator) {
                if (strpos($after_term, $indicator) !== false && strpos($after_term, $indicator) < 20) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Analyze reading level compliance
     *
     * @param string $response AI response
     * @param string $target_level Target reading level
     * @return array Reading level analysis
     */
    private function analyze_reading_level_compliance($response, $target_level) {
        $analysis = array(
            'appropriate' => true,
            'estimated_level' => 'unknown',
            'word_complexity_score' => 0,
            'sentence_complexity_score' => 0,
            'issues' => array(),
            'strengths' => array()
        );

        // Basic complexity indicators by level
        $level_expectations = array(
            'very_simple' => array('max_avg_word_length' => 5, 'max_avg_sentence_length' => 10),
            'simple' => array('max_avg_word_length' => 6, 'max_avg_sentence_length' => 15),
            'standard' => array('max_avg_word_length' => 7, 'max_avg_sentence_length' => 20),
            'detailed' => array('max_avg_word_length' => 8, 'max_avg_sentence_length' => 25),
            'expert' => array('max_avg_word_length' => 10, 'max_avg_sentence_length' => 30)
        );

        $expectations = $level_expectations[$target_level] ?? $level_expectations['standard'];

        // Calculate metrics
        $words = preg_split('/\s+/', $response);
        $avg_word_length = array_sum(array_map('strlen', $words)) / count($words);
        
        $sentences = preg_split('/[.!?]+/', $response);
        $sentences = array_filter($sentences, 'trim');
        $avg_sentence_length = count($words) / count($sentences);

        $analysis['word_complexity_score'] = $avg_word_length;
        $analysis['sentence_complexity_score'] = $avg_sentence_length;

        // Check compliance
        if ($avg_word_length > $expectations['max_avg_word_length']) {
            $analysis['appropriate'] = false;
            $analysis['issues'][] = "Words too complex for {$target_level} level (avg: " . round($avg_word_length, 1) . " chars)";
        }

        if ($avg_sentence_length > $expectations['max_avg_sentence_length']) {
            $analysis['appropriate'] = false;
            $analysis['issues'][] = "Sentences too long for {$target_level} level (avg: " . round($avg_sentence_length, 1) . " words)";
        }

        if ($analysis['appropriate']) {
            $analysis['strengths'][] = "Response complexity appropriate for {$target_level} level";
        }

        return $analysis;
    }

    /**
     * Analyze overall response quality
     *
     * @param string $response AI response
     * @param string $selected_term Selected term
     * @return array Quality analysis
     */
    private function analyze_response_quality($response, $selected_term) {
        $analysis = array(
            'score' => 0,
            'length_appropriate' => false,
            'clear_and_concise' => false,
            'grammatically_correct' => false,
            'provides_value' => false,
            'issues' => array(),
            'strengths' => array()
        );

        $response_length = strlen($response);
        $word_count = str_word_count($response);
        
        // Length appropriateness (50-300 characters ideal for explanations)
        if ($response_length >= 50 && $response_length <= 300) {
            $analysis['length_appropriate'] = true;
            $analysis['score'] += 25;
            $analysis['strengths'][] = 'Response length is appropriate';
        } elseif ($response_length < 50) {
            $analysis['issues'][] = 'Response too short (may lack detail)';
        } else {
            $analysis['issues'][] = 'Response too long (may be verbose)';
        }

        // Basic grammar and structure checks
        if ($this->has_proper_sentence_structure($response)) {
            $analysis['grammatically_correct'] = true;
            $analysis['score'] += 25;
            $analysis['strengths'][] = 'Response has proper sentence structure';
        } else {
            $analysis['issues'][] = 'Response may have grammatical issues';
        }

        // Clarity and conciseness
        if ($word_count <= 50 && $word_count >= 10) {
            $analysis['clear_and_concise'] = true;
            $analysis['score'] += 25;
            $analysis['strengths'][] = 'Response is clear and concise';
        } elseif ($word_count > 50) {
            $analysis['issues'][] = 'Response may be too verbose';
        } else {
            $analysis['issues'][] = 'Response may be too brief';
        }

        // Value assessment (basic heuristic)
        if (strlen($selected_term) > 0 && $response_length > strlen($selected_term) * 3) {
            $analysis['provides_value'] = true;
            $analysis['score'] += 25;
            $analysis['strengths'][] = 'Response provides substantive explanation';
        } else {
            $analysis['issues'][] = 'Response may not provide sufficient explanation value';
        }

        return $analysis;
    }

    /**
     * Check if response has proper sentence structure
     *
     * @param string $response Response to check
     * @return bool Whether structure is proper
     */
    private function has_proper_sentence_structure($response) {
        // Basic checks for proper sentence structure
        $trimmed = trim($response);
        
        // Should start with capital letter
        if (empty($trimmed) || !ctype_upper($trimmed[0])) {
            return false;
        }
        
        // Should end with proper punctuation
        $last_char = substr($trimmed, -1);
        if (!in_array($last_char, array('.', '!', '?'))) {
            return false;
        }
        
        // Should contain at least one complete sentence
        $sentences = preg_split('/[.!?]+/', $trimmed);
        $complete_sentences = array_filter($sentences, function($sentence) {
            return strlen(trim($sentence)) > 5; // Basic sentence length check
        });
        
        return count($complete_sentences) >= 1;
    }

    /**
     * Compile final validation results with recommendations
     *
     * @param array $results Partial validation results
     * @return array Complete validation results
     */
    private function compile_validation_results($results) {
        // Calculate overall score
        $overall_score = (
            $results['term_focus_score'] * 0.4 +
            $results['context_leakage_score'] * 0.3 +
            $results['response_quality_score'] * 0.3
        );

        $results['overall_score'] = round($overall_score, 1);

        // Compile all issues
        $all_issues = array();
        foreach ($results['analysis_details'] as $analysis) {
            if (isset($analysis['issues'])) {
                $all_issues = array_merge($all_issues, $analysis['issues']);
            }
        }
        $results['issues'] = array_unique($all_issues);

        // Compile all strengths
        $all_strengths = array();
        foreach ($results['analysis_details'] as $analysis) {
            if (isset($analysis['strengths'])) {
                $all_strengths = array_merge($all_strengths, $analysis['strengths']);
            }
        }
        $results['strengths'] = array_unique($all_strengths);

        // Generate recommendations based on issues
        $results['recommendations'] = $this->generate_recommendations($results);

        return $results;
    }

    /**
     * Generate improvement recommendations based on validation results
     *
     * @param array $results Validation results
     * @return array Recommendations
     */
    private function generate_recommendations($results) {
        $recommendations = array();

        // Focus-related recommendations
        if ($results['term_focus_score'] < 70) {
            $recommendations[] = 'Improve prompt instructions to better focus on the selected term';
            $recommendations[] = 'Consider adding emphasis markers around the selected term in prompts';
        }

        // Context leakage recommendations
        if ($results['context_leakage_score'] < 80) {
            $recommendations[] = 'Strengthen instructions to avoid explaining context terms';
            $recommendations[] = 'Add explicit warnings about not explaining background terms';
        }

        // Quality recommendations
        if ($results['response_quality_score'] < 70) {
            $recommendations[] = 'Review response length and clarity guidelines';
            $recommendations[] = 'Consider provider-specific prompt optimisation';
        }

        // Reading level recommendations
        if (!$results['reading_level_appropriate']) {
            $recommendations[] = 'Adjust prompt complexity for target reading level';
            $recommendations[] = 'Add reading level specific vocabulary guidelines';
        }

        return $recommendations;
    }

    /**
     * Test multiple providers with the same input for comparison
     *
     * @param string $selected_term Term to explain
     * @param array $context Enhanced context
     * @param string $reading_level Target reading level
     * @param array $providers List of providers to test
     * @return array Cross-provider comparison results
     */
    public function compare_providers($selected_term, $context, $reading_level, $providers = array()) {
        $comparison_start = microtime(true);
        
        if (empty($providers)) {
            $providers = array('openai', 'claude'); // Default providers
        }

        $results = array(
            'selected_term' => $selected_term,
            'reading_level' => $reading_level,
            'providers_tested' => count($providers),
            'provider_results' => array(),
            'comparison_summary' => array(),
            'recommendations' => array()
        );

        foreach ($providers as $provider) {
            try {
                // This would integrate with actual API calls
                // For now, we'll simulate response testing
                $mock_response = $this->get_mock_response($selected_term, $provider, $reading_level);
                
                $validation = $this->validate_response_focus(
                    $selected_term,
                    $mock_response,
                    $context,
                    $provider,
                    $reading_level
                );

                $results['provider_results'][$provider] = $validation;

            } catch (Exception $e) {
                $this->logger->error("Provider comparison failed for {$provider}", array(
                    'error' => $e->getMessage(),
                    'selected_term' => $selected_term
                ));

                $results['provider_results'][$provider] = array(
                    'error' => $e->getMessage(),
                    'overall_score' => 0
                );
            }
        }

        // Generate comparison summary
        $results['comparison_summary'] = $this->generate_comparison_summary($results['provider_results']);
        
        $comparison_time = microtime(true) - $comparison_start;
        
        $this->debug_logger->log('Provider comparison completed', array(
            'selected_term' => $selected_term,
            'providers_tested' => count($providers),
            'comparison_time_ms' => round($comparison_time * 1000, 2),
            'best_provider' => $results['comparison_summary']['best_provider'] ?? 'unknown'
        ));

        return $results;
    }

    /**
     * Generate summary of provider comparison
     *
     * @param array $provider_results Results from each provider
     * @return array Comparison summary
     */
    private function generate_comparison_summary($provider_results) {
        $summary = array(
            'best_provider' => null,
            'worst_provider' => null,
            'average_score' => 0,
            'score_range' => array('min' => 100, 'max' => 0),
            'consistent_quality' => false
        );

        $scores = array();
        foreach ($provider_results as $provider => $result) {
            if (!isset($result['error']) && isset($result['overall_score'])) {
                $scores[$provider] = $result['overall_score'];
            }
        }

        if (!empty($scores)) {
            // Find best and worst
            arsort($scores);
            $summary['best_provider'] = array_key_first($scores);
            $summary['worst_provider'] = array_key_last($scores);

            // Calculate statistics
            $summary['average_score'] = round(array_sum($scores) / count($scores), 1);
            $summary['score_range']['min'] = min($scores);
            $summary['score_range']['max'] = max($scores);

            // Check consistency (scores within 20 points considered consistent)
            $score_range = $summary['score_range']['max'] - $summary['score_range']['min'];
            $summary['consistent_quality'] = $score_range <= 20;
        }

        return $summary;
    }

    /**
     * Get mock response for testing (would be replaced with real API calls)
     *
     * @param string $selected_term Term to explain
     * @param string $provider Provider name
     * @param string $reading_level Target reading level
     * @return string Mock response
     */
    private function get_mock_response($selected_term, $provider, $reading_level) {
        // Mock responses for testing - would be replaced with real API integration
        $mock_responses = array(
            'openai' => array(
                'very_simple' => "{$selected_term} is a simple word that means something easy to understand.",
                'simple' => "{$selected_term} refers to a concept that is straightforward and uncomplicated.",
                'standard' => "{$selected_term} is a term used to describe something that is clear and easily understood.",
                'detailed' => "{$selected_term} represents a concept characterized by clarity and simplicity, lacking complexity or confusion.",
                'expert' => "{$selected_term} denotes a characteristic or quality indicating lack of complexity, ambiguity, or convolution."
            ),
            'claude' => array(
                'very_simple' => "A {$selected_term} is something that's easy to get.",
                'simple' => "{$selected_term} means something that's not hard to understand or do.",
                'standard' => "{$selected_term} describes something that is easily understood without confusion.",
                'detailed' => "{$selected_term} is a term that describes the quality of being easily understood, clear, and uncomplicated.",
                'expert' => "{$selected_term} refers to the characteristic of lacking complexity, being readily comprehensible and unambiguous."
            )
        );

        $provider_responses = $mock_responses[$provider] ?? $mock_responses['openai'];
        return $provider_responses[$reading_level] ?? $provider_responses['standard'];
    }

    /**
     * Generate comprehensive quality report
     *
     * @param array $validation_results Multiple validation results
     * @return array Quality report
     */
    public function generate_quality_report($validation_results) {
        $report = array(
            'summary' => array(
                'total_tests' => count($validation_results),
                'average_scores' => array(),
                'common_issues' => array(),
                'overall_quality' => 'unknown'
            ),
            'detailed_results' => $validation_results,
            'recommendations' => array(),
            'generated_at' => current_time('mysql')
        );

        if (!empty($validation_results)) {
            // Calculate average scores
            $total_focus = $total_leakage = $total_quality = $total_overall = 0;
            $issue_counts = array();

            foreach ($validation_results as $result) {
                $total_focus += $result['term_focus_score'];
                $total_leakage += $result['context_leakage_score'];
                $total_quality += $result['response_quality_score'];
                $total_overall += $result['overall_score'];

                // Count issues
                foreach ($result['issues'] as $issue) {
                    $issue_counts[$issue] = ($issue_counts[$issue] ?? 0) + 1;
                }
            }

            $count = count($validation_results);
            $report['summary']['average_scores'] = array(
                'term_focus' => round($total_focus / $count, 1),
                'context_leakage' => round($total_leakage / $count, 1),
                'response_quality' => round($total_quality / $count, 1),
                'overall' => round($total_overall / $count, 1)
            );

            // Most common issues
            arsort($issue_counts);
            $report['summary']['common_issues'] = array_slice($issue_counts, 0, 5, true);

            // Overall quality assessment
            $overall_avg = $report['summary']['average_scores']['overall'];
            if ($overall_avg >= 90) {
                $report['summary']['overall_quality'] = 'excellent';
            } elseif ($overall_avg >= 80) {
                $report['summary']['overall_quality'] = 'good';
            } elseif ($overall_avg >= 70) {
                $report['summary']['overall_quality'] = 'acceptable';
            } else {
                $report['summary']['overall_quality'] = 'needs_improvement';
            }

            // Generate system-wide recommendations
            $report['recommendations'] = $this->generate_system_recommendations($report);
        }

        return $report;
    }

    /**
     * Generate system-wide recommendations based on quality report
     *
     * @param array $report Quality report
     * @return array System recommendations
     */
    private function generate_system_recommendations($report) {
        $recommendations = array();
        $scores = $report['summary']['average_scores'];
        $common_issues = $report['summary']['common_issues'];

        // Focus-related recommendations
        if ($scores['term_focus'] < 80) {
            $recommendations[] = 'System-wide improvement needed: Enhance prompt templates to better focus on selected terms';
        }

        // Context leakage recommendations
        if ($scores['context_leakage'] < 85) {
            $recommendations[] = 'System-wide improvement needed: Strengthen context isolation in prompts';
        }

        // Quality recommendations
        if ($scores['response_quality'] < 75) {
            $recommendations[] = 'System-wide improvement needed: Review response quality guidelines across providers';
        }

        // Issue-specific recommendations
        if (isset($common_issues['Response too long (may be verbose)'])) {
            $recommendations[] = 'Consider adding length constraints to prompts';
        }

        if (isset($common_issues['Selected term is not mentioned in the response'])) {
            $recommendations[] = 'Add explicit requirements for term mention in prompts';
        }

        return $recommendations;
    }
}