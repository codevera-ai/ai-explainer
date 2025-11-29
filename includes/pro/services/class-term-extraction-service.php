<?php
/**
 * AI Term Extraction Service
 * 
 * Handles extraction of technical terms from post content using AI providers
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Term Extraction Service class
 */
class ExplainerPlugin_Term_Extraction_Service {
    
    /**
     * Default term extraction prompt template
     * @deprecated Use ExplainerPlugin_Config::get_default_term_extraction_prompt() instead
     */
    const DEFAULT_PROMPT = null;
    
    /**
     * Provider factory instance
     */
    private $provider_factory;
    
    /**
     * API proxy instance
     */
    private $api_proxy;
    
    /**
     * Selection tracker instance
     */
    private $selection_tracker;
    
    /**
     * Initialize the service
     */
    public function __construct() {
        $this->api_proxy = new ExplainerPlugin_API_Proxy();
        $this->selection_tracker = new ExplainerPlugin_Selection_Tracker();
    }
    
    /**
     * Extract terms from post content
     * 
     * @param string $post_content Post content to analyze
     * @param int    $post_id      Post ID for term association
     * @param array  $options      Extraction options
     * @return array Result array with success/error and extracted terms
     */
    public function extract_terms($post_content, $post_id, $options = array()) {
        ExplainerPlugin_Debug_Logger::debug("STEP TE-1: extract_terms method called", 'Term_Extraction_Service');
        explainer_log_info('Term Extraction Service: Starting term extraction', array(
            'post_id' => $post_id,
            'content_length' => strlen($post_content),
            'options' => $options
        ), 'Term_Extraction_Service');
        
        $extraction_start = microtime(true);
        ExplainerPlugin_Debug_Logger::debug("STEP TE-2: Extraction timer started at " . $extraction_start, 'Term_Extraction_Service');
        
        try {
            ExplainerPlugin_Debug_Logger::debug("STEP TE-3: Beginning input validation", 'Term_Extraction_Service');
            
            // Validate input
            if (empty($post_content)) {
                ExplainerPlugin_Debug_Logger::debug("STEP TE-3a: VALIDATION FAILED - Post content is empty", 'Term_Extraction_Service');
                throw new \Exception(esc_html('Post content is empty'));
            }
            ExplainerPlugin_Debug_Logger::debug("STEP TE-3b: Post content validation passed - length: " . strlen($post_content), 'Term_Extraction_Service');
            
            if (!$post_id || !is_numeric($post_id)) {
                ExplainerPlugin_Debug_Logger::debug("STEP TE-3c: VALIDATION FAILED - Invalid post ID: $post_id", 'Term_Extraction_Service');
                throw new \Exception(esc_html('Invalid post ID'));
            }
            ExplainerPlugin_Debug_Logger::debug("STEP TE-3d: Post ID validation passed: $post_id", 'Term_Extraction_Service');
            
            ExplainerPlugin_Debug_Logger::debug("STEP TE-4: Starting content preprocessing", 'Term_Extraction_Service');
            // Preprocess content
            $processed_content = $this->preprocess_content($post_content);
            ExplainerPlugin_Debug_Logger::debug("STEP TE-4a: Content preprocessing completed", 'Term_Extraction_Service');
            
            explainer_log_debug('Term Extraction Service: Content preprocessed', array(
                'post_id' => $post_id,
                'original_length' => strlen($post_content),
                'processed_length' => strlen($processed_content)
            ), 'Term_Extraction_Service');
            
            ExplainerPlugin_Debug_Logger::debug("STEP TE-5: Starting prompt preparation", 'Term_Extraction_Service');
            // Prepare extraction prompt
            $prompt = $this->prepare_extraction_prompt($processed_content, $options);
            ExplainerPlugin_Debug_Logger::debug("STEP TE-5a: Prompt preparation completed - length: " . strlen($prompt), 'Term_Extraction_Service');
            
            explainer_log_debug('Term Extraction Service: Prompt prepared', array(
                'post_id' => $post_id,
                'prompt_length' => strlen($prompt),
                'prompt_preview' => substr($prompt, 0, 200) . '...'
            ), 'Term_Extraction_Service');
            
            ExplainerPlugin_Debug_Logger::debug("STEP TE-6: Getting AI provider settings", 'Term_Extraction_Service');
            // Get AI provider settings
            $provider_key = $options['provider'] ?? get_option('explainer_api_provider', 'openai');
            $model = $options['model'] ?? get_option('explainer_api_model', 'gpt-3.5-turbo');
            ExplainerPlugin_Debug_Logger::debug("STEP TE-6a: Provider settings retrieved - provider: $provider_key, model: $model", 'Term_Extraction_Service');
            
            ExplainerPlugin_Debug_Logger::debug("STEP TE-7: Mapping provider key", 'Term_Extraction_Service');
            // Map to proper provider key format for API proxy
            $direct_provider_key = $this->map_provider_key($provider_key, $model);
            ExplainerPlugin_Debug_Logger::debug("STEP TE-7a: Provider key mapped to: $direct_provider_key", 'Term_Extraction_Service');
            
            ExplainerPlugin_Debug_Logger::debug("STEP TE-8: Preparing for AI request", 'Term_Extraction_Service');
            // Make AI request
            ExplainerPlugin_Debug_Logger::debug("STEP TE-8a: About to make AI request to $provider_key", 'Term_Extraction_Service');
            explainer_log_debug('Term Extraction Service: Making AI request', array(
                'post_id' => $post_id,
                'provider' => $provider_key,
                'model' => $model,
                'mapped_key' => $direct_provider_key
            ), 'Term_Extraction_Service');
            
            ExplainerPlugin_Debug_Logger::debug("STEP TE-8b: Setting execution time limit to 120 seconds", 'Term_Extraction_Service');
            ExplainerPlugin_Debug_Logger::debug("STEP TE-8c: Starting AI request timer", 'Term_Extraction_Service');
            $ai_start = microtime(true);

            // Set a timeout for the AI request to prevent infinite hangs
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required to prevent AI API requests from hanging indefinitely. Safe fallback for environments where WP-Cron is unreliable.
            set_time_limit(120); // 2 minutes max for AI request
            ExplainerPlugin_Debug_Logger::debug("STEP TE-8d: Time limit set, calling api_proxy->get_direct_explanation NOW", 'Term_Extraction_Service');
            
            try {
                ExplainerPlugin_Debug_Logger::debug("STEP TE-8e: ENTERING api_proxy->get_direct_explanation call", 'Term_Extraction_Service');
                $ai_response = $this->api_proxy->get_direct_explanation($prompt, $direct_provider_key, array(
                    'max_tokens' => 2000, // Increased for term extraction
                    'temperature' => 0.3   // Lower temperature for consistent JSON
                ));
                ExplainerPlugin_Debug_Logger::debug("STEP TE-8f: RETURNED from api_proxy->get_direct_explanation call", 'Term_Extraction_Service');
                $ai_duration = microtime(true) - $ai_start;
                ExplainerPlugin_Debug_Logger::debug("STEP TE-8g: AI request completed successfully in " . round($ai_duration, 2) . " seconds", 'Term_Extraction_Service');
            } catch (Exception $e) {
                $ai_duration = microtime(true) - $ai_start;
                ExplainerPlugin_Debug_Logger::debug("STEP TE-8h: AI request EXCEPTION after " . round($ai_duration, 2) . " seconds: " . $e->getMessage(), 'Term_Extraction_Service');
                throw new Exception(esc_html('AI request failed: ' . $e->getMessage()));
            }
            
            ExplainerPlugin_Debug_Logger::debug("STEP TE-9: Validating AI response", 'Term_Extraction_Service');
            if (!$ai_response) {
                ExplainerPlugin_Debug_Logger::debug("STEP TE-9a: VALIDATION FAILED - No AI response received", 'Term_Extraction_Service');
                throw new \Exception(esc_html('Failed to get AI response for term extraction'));
            }
            ExplainerPlugin_Debug_Logger::debug("STEP TE-9b: AI response validation passed - length: " . strlen($ai_response), 'Term_Extraction_Service');
            
            explainer_log_info('Term Extraction Service: AI response received', array(
                'post_id' => $post_id,
                'response_length' => strlen($ai_response),
                'ai_duration_seconds' => round($ai_duration, 3)
            ), 'Term_Extraction_Service');
            
            ExplainerPlugin_Debug_Logger::debug("STEP TE-10: Starting AI response parsing", 'Term_Extraction_Service');
            // Parse and validate response
            $extracted_terms = $this->parse_ai_response($ai_response);
            ExplainerPlugin_Debug_Logger::debug("STEP TE-10a: AI response parsing completed - extracted " . count($extracted_terms) . " terms", 'Term_Extraction_Service');
            
            if (empty($extracted_terms)) {
                ExplainerPlugin_Debug_Logger::debug("STEP TE-10b: PARSING FAILED - No terms extracted from AI response", 'Term_Extraction_Service');
                throw new \Exception(esc_html('No terms extracted from AI response'));
            }
            
            explainer_log_info('Term Extraction Service: Terms parsed successfully', array(
                'post_id' => $post_id,
                'extracted_count' => count($extracted_terms),
                'sample_terms' => array_slice(array_column($extracted_terms, 'term'), 0, 5)
            ), 'Term_Extraction_Service');
            
            ExplainerPlugin_Debug_Logger::debug("STEP TE-11: Starting term validation and deduplication", 'Term_Extraction_Service');
            // Remove duplicates and validate terms
            $validated_terms = $this->validate_and_deduplicate_terms($extracted_terms, $post_id);
            ExplainerPlugin_Debug_Logger::debug("STEP TE-11a: Term validation and deduplication completed - " . count($validated_terms) . " final terms", 'Term_Extraction_Service');
            
            explainer_log_info('Term Extraction Service: Terms validated and deduplicated', array(
                'post_id' => $post_id,
                'original_count' => count($extracted_terms),
                'validated_count' => count($validated_terms)
            ), 'Term_Extraction_Service');
            
            ExplainerPlugin_Debug_Logger::debug("STEP TE-12: Calculating final extraction duration", 'Term_Extraction_Service');
            $extraction_duration = microtime(true) - $extraction_start;
            ExplainerPlugin_Debug_Logger::debug("STEP TE-12a: Total extraction duration: " . round($extraction_duration, 3) . " seconds", 'Term_Extraction_Service');
            
            ExplainerPlugin_Debug_Logger::debug("STEP TE-13: Preparing successful return data", 'Term_Extraction_Service');
            explainer_log_info('Term Extraction Service: Extraction completed successfully', array(
                'post_id' => $post_id,
                'final_term_count' => count($validated_terms),
                'total_duration_seconds' => round($extraction_duration, 3),
                'ai_duration_seconds' => round($ai_duration, 3)
            ), 'Term_Extraction_Service');
            
            $return_data = array(
                'success' => true,
                'terms' => $validated_terms,
                'stats' => array(
                    'extracted_count' => count($extracted_terms),
                    'final_count' => count($validated_terms),
                    'extraction_duration' => $extraction_duration,
                    'ai_duration' => $ai_duration
                )
            );
            ExplainerPlugin_Debug_Logger::debug("STEP TE-13a: SUCCESS - Returning extraction results with " . count($validated_terms) . " terms", 'Term_Extraction_Service');
            return $return_data;
            
        } catch (Exception $e) {
            $extraction_duration = microtime(true) - $extraction_start;
            ExplainerPlugin_Debug_Logger::debug("STEP TE-ERROR: Exception caught after " . round($extraction_duration, 3) . " seconds: " . $e->getMessage(), 'Term_Extraction_Service');
            ExplainerPlugin_Debug_Logger::debug("STEP TE-ERROR-TRACE: Exception at " . $e->getFile() . ":" . $e->getLine(), 'Term_Extraction_Service');
            
            explainer_log_error('Term Extraction Service: Extraction failed', array(
                'post_id' => $post_id,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'extraction_duration_seconds' => round($extraction_duration, 3)
            ), 'Term_Extraction_Service');
            
            $error_data = array(
                'success' => false,
                'error' => $e->getMessage(),
                'stats' => array(
                    'extraction_duration' => $extraction_duration
                )
            );
            ExplainerPlugin_Debug_Logger::debug("STEP TE-ERROR-RETURN: Returning error response: " . $e->getMessage(), 'Term_Extraction_Service');
            return $error_data;
        }
    }
    
    /**
     * Preprocess content for term extraction
     * 
     * @param string $content Raw post content
     * @return string Processed content
     */
    private function preprocess_content($content) {
        // Remove HTML tags but preserve line breaks
        $content = wp_strip_all_tags($content, false);
        
        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        
        // Remove excessive whitespace but preserve paragraphs
        $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);
        $content = preg_replace('/[ \t]+/', ' ', $content);
        
        // Remove common WordPress shortcodes
        $content = preg_replace('/\[([^\]]+)\]/', '', $content);
        
        // Limit content length to prevent excessive API costs (max ~4000 words)
        $max_length = 16000; // Approximate 4000 words at 4 chars per word
        if (strlen($content) > $max_length) {
            $content = substr($content, 0, $max_length);
            // Try to cut at a sentence boundary
            $last_period = strrpos($content, '.');
            $last_exclamation = strrpos($content, '!');
            $last_question = strrpos($content, '?');
            
            $last_sentence_end = max($last_period, $last_exclamation, $last_question);
            if ($last_sentence_end !== false && $last_sentence_end > $max_length * 0.8) {
                $content = substr($content, 0, $last_sentence_end + 1);
            }
        }
        
        return trim($content);
    }
    
    /**
     * Prepare extraction prompt from template
     * 
     * @param string $content   Processed content
     * @param array  $options   Extraction options
     * @return string Generated prompt
     */
    private function prepare_extraction_prompt($content, $options = array()) {
        // Get custom prompt template or use default
        $prompt_template = get_option('explainer_term_extraction_prompt', ExplainerPlugin_Config::get_default_term_extraction_prompt());
        
        // Validate prompt template contains required placeholder
        if (strpos($prompt_template, '{{post_content}}') === false) {
            explainer_log_error('Term Extraction Service: Invalid prompt template - missing {{post_content}} placeholder', array(
                'template_preview' => substr($prompt_template, 0, 100) . '...'
            ), 'Term_Extraction_Service');
            
            $prompt_template = ExplainerPlugin_Config::get_default_term_extraction_prompt();
        }
        
        // Get term count limits from options or settings
        $min_terms = $options['min_terms'] ?? get_option('explainer_term_extraction_min_terms', 10);
        $max_terms = $options['max_terms'] ?? get_option('explainer_term_extraction_max_terms', 50);
        
        // Ensure valid range
        $min_terms = max(10, min($min_terms, 50));
        $max_terms = max($min_terms, min($max_terms, 50));
        
        // Replace term count in template if it uses default pattern
        $prompt_template = str_replace('exactly 10-50 terms', "exactly {$min_terms}-{$max_terms} terms", $prompt_template);
        
        // Replace content placeholder
        $prompt = str_replace('{{post_content}}', $content, $prompt_template);
        
        explainer_log_debug('Term Extraction Service: Prompt template processed', array(
            'template_length' => strlen($prompt_template),
            'final_prompt_length' => strlen($prompt),
            'min_terms' => $min_terms,
            'max_terms' => $max_terms,
            'content_length' => strlen($content)
        ), 'Term_Extraction_Service');
        
        return $prompt;
    }
    
    /**
     * Parse AI response and extract terms
     * 
     * @param string $ai_response Raw AI response
     * @return array Array of term objects
     * @throws Exception If parsing fails
     */
    private function parse_ai_response($ai_response) {
        explainer_log_debug('Term Extraction Service: Parsing AI response', array(
            'response_length' => strlen($ai_response),
            'response_preview' => substr(trim($ai_response), 0, 200) . '...'
        ), 'Term_Extraction_Service');
        
        // Clean up response - remove any text before first [ and after last ]
        $ai_response = trim($ai_response);
        
        // Try to extract JSON array from response
        $json_start = strpos($ai_response, '[');
        $json_end = strrpos($ai_response, ']');
        
        if ($json_start === false || $json_end === false) {
            throw new \Exception(esc_html('AI response does not contain valid JSON array'));
        }
        
        $json_content = substr($ai_response, $json_start, $json_end - $json_start + 1);
        
        explainer_log_debug('Term Extraction Service: Extracted JSON content', array(
            'json_length' => strlen($json_content),
            'json_preview' => substr($json_content, 0, 200) . '...'
        ), 'Term_Extraction_Service');
        
        // Decode JSON
        $parsed_terms = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(esc_html('Failed to parse AI response JSON: ' . json_last_error_msg()));
        }
        
        if (!is_array($parsed_terms)) {
            throw new \Exception(esc_html('AI response is not a JSON array'));
        }
        
        // Validate term structure
        $valid_terms = array();
        
        foreach ($parsed_terms as $index => $term_data) {
            if (!is_array($term_data)) {
                explainer_log_debug('Term Extraction Service: Skipping invalid term data at index ' . $index, array(
                    'term_data' => $term_data
                ), 'Term_Extraction_Service');
                continue;
            }
            
            if (!isset($term_data['term']) || !isset($term_data['explanation'])) {
                explainer_log_debug('Term Extraction Service: Skipping term missing required fields at index ' . $index, array(
                    'available_fields' => array_keys($term_data)
                ), 'Term_Extraction_Service');
                continue;
            }
            
            $term = trim($term_data['term']);
            $explanation = trim($term_data['explanation']);
            
            if (empty($term) || empty($explanation)) {
                explainer_log_debug('Term Extraction Service: Skipping empty term or explanation at index ' . $index, array(
                    'term' => $term,
                    'explanation_length' => strlen($explanation)
                ), 'Term_Extraction_Service');
                continue;
            }
            
            // Basic validation - term should be reasonable length
            if (strlen($term) < 2 || strlen($term) > 100) {
                explainer_log_debug('Term Extraction Service: Skipping term with invalid length at index ' . $index, array(
                    'term' => $term,
                    'term_length' => strlen($term)
                ), 'Term_Extraction_Service');
                continue;
            }
            
            // Explanation should be reasonable length
            if (strlen($explanation) < 10 || strlen($explanation) > 500) {
                explainer_log_debug('Term Extraction Service: Skipping explanation with invalid length at index ' . $index, array(
                    'term' => $term,
                    'explanation_length' => strlen($explanation)
                ), 'Term_Extraction_Service');
                continue;
            }
            
            $valid_terms[] = array(
                'term' => $term,
                'explanation' => $explanation
            );
        }
        
        explainer_log_info('Term Extraction Service: AI response parsed', array(
            'original_terms' => count($parsed_terms),
            'valid_terms' => count($valid_terms),
            'validation_success_rate' => count($parsed_terms) > 0 ? round((count($valid_terms) / count($parsed_terms)) * 100, 1) . '%' : '0%'
        ), 'Term_Extraction_Service');
        
        return $valid_terms;
    }
    
    /**
     * Validate and remove duplicate terms
     * 
     * @param array $terms   Array of term objects
     * @param int   $post_id Post ID for duplicate checking
     * @return array Deduplicated terms
     */
    private function validate_and_deduplicate_terms($terms, $post_id) {
        explainer_log_debug('Term Extraction Service: Starting term validation and deduplication', array(
            'post_id' => $post_id,
            'input_terms_count' => count($terms)
        ), 'Term_Extraction_Service');
        
        $deduplicated_terms = array();
        $seen_terms = array();
        
        // Get existing terms for this post to avoid duplicates
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        
        $existing_terms = $wpdb->get_col($wpdb->prepare(
            "SELECT LOWER(selected_text) FROM {$table_name} WHERE post_id = %d AND source = 'ai_scan'",
            $post_id
        ));
        
        $existing_terms_lookup = array_flip($existing_terms);
        
        explainer_log_debug('Term Extraction Service: Found existing terms', array(
            'post_id' => $post_id,
            'existing_terms_count' => count($existing_terms),
            'existing_terms_sample' => array_slice($existing_terms, 0, 5)
        ), 'Term_Extraction_Service');
        
        foreach ($terms as $term_data) {
            $term = $term_data['term'];
            $explanation = $term_data['explanation'];
            
            // Normalize term for duplicate checking
            $normalized_term = strtolower(trim($term));
            
            // Skip if we've already seen this term in current batch
            if (isset($seen_terms[$normalized_term])) {
                explainer_log_debug('Term Extraction Service: Skipping duplicate term in current batch', array(
                    'term' => $term,
                    'normalized' => $normalized_term
                ), 'Term_Extraction_Service');
                continue;
            }
            
            // Skip if term already exists for this post
            if (isset($existing_terms_lookup[$normalized_term])) {
                explainer_log_debug('Term Extraction Service: Skipping existing term for post', array(
                    'term' => $term,
                    'post_id' => $post_id,
                    'normalized' => $normalized_term
                ), 'Term_Extraction_Service');
                continue;
            }
            
            // Additional validation
            if ($this->is_common_word($term)) {
                explainer_log_debug('Term Extraction Service: Skipping common word', array(
                    'term' => $term
                ), 'Term_Extraction_Service');
                continue;
            }
            
            // Add to deduplicated list
            $seen_terms[$normalized_term] = true;
            $deduplicated_terms[] = array(
                'term' => $term,
                'explanation' => $explanation,
                'normalized' => $normalized_term
            );
        }
        
        explainer_log_info('Term Extraction Service: Term validation and deduplication completed', array(
            'post_id' => $post_id,
            'input_count' => count($terms),
            'existing_terms_count' => count($existing_terms),
            'deduplicated_count' => count($deduplicated_terms),
            'deduplication_rate' => count($terms) > 0 ? round(((count($terms) - count($deduplicated_terms)) / count($terms)) * 100, 1) . '%' : '0%'
        ), 'Term_Extraction_Service');
        
        return $deduplicated_terms;
    }
    
    /**
     * Check if term is a common word that shouldn't be extracted
     * 
     * @param string $term Term to check
     * @return bool True if common word
     */
    private function is_common_word($term) {
        $common_words = array(
            // Articles, prepositions, conjunctions
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
            // Common verbs
            'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'can', 'could', 'should',
            // Pronouns
            'i', 'you', 'he', 'she', 'it', 'we', 'they', 'this', 'that', 'these', 'those',
            // Common adjectives/adverbs
            'good', 'bad', 'big', 'small', 'new', 'old', 'first', 'last', 'long', 'short', 'high', 'low', 'very', 'more', 'most',
            // Numbers (spelled out)
            'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'
        );
        
        return in_array(strtolower(trim($term)), $common_words, true);
    }
    
    /**
     * Get default extraction prompt
     * 
     * @return string Default prompt template
     */
    public static function get_default_prompt() {
        return ExplainerPlugin_Config::get_default_term_extraction_prompt();
    }
    
    /**
     * Validate prompt template
     * 
     * @param string $prompt_template Prompt template to validate
     * @return array Validation result with success/error
     */
    public static function validate_prompt_template($prompt_template) {
        if (empty($prompt_template)) {
            return array(
                'success' => false,
                'error' => 'Prompt template cannot be empty'
            );
        }
        
        // Check for required placeholder
        if (strpos($prompt_template, '{{post_content}}') === false) {
            return array(
                'success' => false,
                'error' => 'Prompt template must contain {{post_content}} placeholder'
            );
        }
        
        // Check length
        if (strlen($prompt_template) > 2000) {
            return array(
                'success' => false,
                'error' => 'Prompt template is too long (maximum 2000 characters)'
            );
        }
        
        // Check for JSON format instruction
        if (strpos(strtolower($prompt_template), 'json') === false) {
            return array(
                'success' => false,
                'error' => 'Prompt template should instruct AI to return JSON format'
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Prompt template is valid'
        );
    }
    
    /**
     * Map provider and model to the format expected by API proxy
     * 
     * @param string $provider Provider key
     * @param string $model    Model name
     * @return string Mapped provider key
     */
    private function map_provider_key($provider, $model) {
        // Map provider-model combinations to API proxy expected format
        $provider_map = array(
            'openai' => array(
                'gpt-3.5-turbo' => 'openai-gpt35',
                'gpt-4' => 'openai-gpt4',
                'gpt-4o' => 'openai-gpt4',
                'gpt-4o-mini' => 'openai-gpt35'
            ),
            'claude' => array(
                'claude-3-haiku-20240307' => 'claude-haiku',
                'claude-3-sonnet-20240229' => 'claude-sonnet',
                'claude-3-opus-20240229' => 'claude-sonnet'
            )
        );
        
        // Return mapped key if exists, otherwise use fallback format
        if (isset($provider_map[$provider][$model])) {
            return $provider_map[$provider][$model];
        }
        
        // Fallback for unmapped combinations
        if ($provider === 'openai') {
            return 'openai-gpt35'; // Default to gpt-3.5
        } elseif ($provider === 'claude') {
            return 'claude-haiku'; // Default to haiku
        }
        
        // Final fallback
        return 'openai-gpt35';
    }
}