<?php
/**
 * AI Content Generator
 * 
 * Generates blog post content using AI providers
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Mock mode toggle - set to true to enable mock content generation for testing  
// LEGACY: Maintained for backwards compatibility, use ExplainerPlugin_Config::is_mock_posts_enabled() instead
define('EXPLAINER_MOCK_CONTENT_GENERATION', false);

/**
 * Content Generator class
 */
class ExplainerPlugin_Content_Generator {
    
    /**
     * API Proxy instance
     */
    private $api_proxy;
    
    /**
     * Initialize the content generator
     */
    public function __construct() {
        $this->api_proxy = new ExplainerPlugin_API_Proxy();
    }
    
    /**
     * Generate blog post content from selected text
     * 
     * @param string $selection_text The selected text to base content on
     * @param array $options Generation options
     * @return array|bool Generated content result or false on failure
     */
    public function generate_blog_post($selection_text, $options) {
        explainer_log_info('Content Generator: Starting blog post generation', array(
            'selection_length' => strlen($selection_text),
            'options' => array_keys($options),
            'ai_provider' => $options['ai_provider'] ?? 'unknown',
            'post_length' => $options['length'] ?? 'unknown',
            'generate_image' => $options['generate_image'] ?? false,
            'generate_seo' => $options['generate_seo'] ?? false,
            'mock_mode' => ExplainerPlugin_Config::is_mock_posts_enabled()
        ), 'Content_Generator');
        
        $generation_start_time = microtime(true);
        
        // Check if mock mode is enabled
        if (ExplainerPlugin_Config::is_mock_posts_enabled()) {
            explainer_log_info('Content Generator: Using mock mode for testing', array(
                'selection_preview' => substr($selection_text, 0, 100) . '...',
                'ai_provider' => $options['ai_provider'] ?? 'unknown',
                'post_length' => $options['length'] ?? 'unknown'
            ), 'Content_Generator');
            
            return $this->generate_mock_content($selection_text, $options);
        }
        
        try {
            // Validate inputs
            if (empty($selection_text) || empty($options['prompt'])) {
                explainer_log_error('Content Generator: Input validation failed', array(
                    'selection_empty' => empty($selection_text),
                    'prompt_empty' => empty($options['prompt']),
                    'selection_length' => strlen($selection_text),
                    'prompt_length' => isset($options['prompt']) ? strlen($options['prompt']) : 0
                ), 'Content_Generator');
                
                throw new Exception(__('Selection text and prompt are required.', 'ai-explainer'));
            }
            
            explainer_log_debug('Content Generator: Input validation passed', array(
                'selection_length' => strlen($selection_text),
                'prompt_length' => strlen($options['prompt']),
                'ai_provider' => $options['ai_provider'] ?? 'unknown'
            ), 'Content_Generator');
            
            // Initialize result array
            $result = array(
                'content' => '',
                'title' => '',
                'seo_data' => null,
                'featured_image_id' => null,
                'cost' => 0
            );
            
            // Get AI provider - use GPT-4 for long posts with OpenAI
            $provider_key = $options['ai_provider'];
            if ($options['length'] === 'long' && strpos($provider_key, 'openai') === 0) {
                $provider_key = 'openai-gpt4';
                ExplainerPlugin_Debug_Logger::debug('Content Generator: Switching to GPT-4 for long post (was: ' . $options['ai_provider'] . ')', 'General');
            }
            
            if (function_exists('explainer_log_debug')) {
                explainer_log_debug('Getting provider for: ' . $provider_key, array(), 'content_generator');
            }
            $provider = $this->get_provider($provider_key);
            if (!$provider) {
                if (function_exists('explainer_log_error')) {
                    explainer_log_error('Invalid provider specified: ' . $provider_key, array(), 'content_generator');
                }
                throw new Exception(__('Invalid AI provider specified.', 'ai-explainer'));
            }
            
            ExplainerPlugin_Debug_Logger::debug('Content Generator: Provider obtained successfully', 'General');
            
            // Build content prompt
            ExplainerPlugin_Debug_Logger::debug('Content Generator: Building content prompt', 'General');
            $content_prompt = $this->build_content_prompt($selection_text, $options);
            if (function_exists('explainer_log_debug')) {
                explainer_log_debug('Content prompt length: ' . strlen($content_prompt), array(), 'content_generator');
            }
            ExplainerPlugin_Debug_Logger::debug('Content Generator: Full prompt being sent: ' . substr($content_prompt, 0, 1000) . '...', 'General');
            
            // Generate main content
            ExplainerPlugin_Debug_Logger::debug('Content Generator: Calling API proxy for content generation', 'General');
            
            // Set max_tokens and timeout based on post length
            $api_options = array();
            if ($options['length'] === 'long') {
                $api_options['max_tokens'] = 2500; // Allow up to 2500 tokens for long posts
                $api_options['timeout'] = 60; // 60 seconds for long posts
                ExplainerPlugin_Debug_Logger::debug('Content Generator: Setting max_tokens to 2500 and timeout to 60s for long post', 'General');
            } elseif ($options['length'] === 'medium') {
                $api_options['max_tokens'] = 1000; // Medium posts get 1000 tokens
                $api_options['timeout'] = 30; // 30 seconds for medium posts
                ExplainerPlugin_Debug_Logger::debug('Content Generator: Setting max_tokens to 1000 and timeout to 30s for medium post', 'General');
            } else {
                $api_options['max_tokens'] = 500; // Short posts get 500 tokens
                $api_options['timeout'] = 15; // 15 seconds for short posts
                ExplainerPlugin_Debug_Logger::debug('Content Generator: Setting max_tokens to 500 and timeout to 15s for short post', 'General');
            }
            
            $content_response = $this->api_proxy->get_direct_explanation($content_prompt, $provider_key, $api_options);
            
            if (!$content_response || empty($content_response)) {
                ExplainerPlugin_Debug_Logger::debug('Content Generator: API call failed or returned empty response', 'General');
                // Check if it's an API key issue
                $provider_key = $options['ai_provider'];
                $provider_name = 'OpenAI';
                if (strpos($provider_key, 'claude') === 0) {
                    $provider_name = 'Claude';
                }

                throw new Exception(sprintf(
                    /* translators: %s: AI provider name (e.g. OpenAI, Claude) */
                    __('Failed to generate content. Please check that your %s API key is configured in Settings → AI Explainer.', 'ai-explainer'),
                    $provider_name
                ));
            }
            
            if (function_exists('explainer_log_debug')) {
                explainer_log_debug('API call successful, response length: ' . strlen($content_response), array(), 'content_generator');
            }
            
            
            $result['content'] = $this->format_blog_content($content_response);
            if (function_exists('explainer_log_debug')) {
                explainer_log_debug('Content formatted, final length: ' . strlen($result['content']), array(), 'content_generator');
            }
            
            // Extract title from first line of content (before HTML formatting)
            $content_lines = explode("\n", trim($content_response));
            $title = '';
            if (!empty($content_lines[0])) {
                // Remove any markdown formatting from the title line
                $title = trim(preg_replace('/^#+\s*/', '', $content_lines[0]));
                // Remove any remaining markdown or unwanted formatting
                $title = trim(wp_strip_all_tags($title));
                if (!empty($title)) {
                    $result['title'] = $title;
                    if (function_exists('explainer_log_debug')) {
                        explainer_log_debug('Extracted title: ' . $title, array(), 'content_generator');
                    }
                } else {
                    ExplainerPlugin_Debug_Logger::debug('Content Generator: First line empty after processing, no title extracted', 'General');
                }
            } else {
                ExplainerPlugin_Debug_Logger::debug('Content Generator: No first line found, no title extracted', 'General');
            }
            
            // Estimate cost for content generation
            ExplainerPlugin_Debug_Logger::debug('Content Generator: Calculating costs', 'General');
            if (class_exists('ExplainerPlugin_Blog_Cost_Calculator')) {
                $cost_calculator = new ExplainerPlugin_Blog_Cost_Calculator();
                $cost_breakdown = $cost_calculator->estimate_cost($options);
                $result['cost'] = $cost_breakdown['content'];
                if (function_exists('explainer_log_debug')) {
                    explainer_log_debug('Content cost calculated: £' . $result['cost'], array(), 'content_generator');
                }
            } else {
                $result['cost'] = 0; // Default cost if calculator not available
                ExplainerPlugin_Debug_Logger::debug('Content Generator: Cost calculator not available, setting cost to 0', 'General');
            }
            
            // Generate SEO metadata if requested
            if ($options['generate_seo']) {
                ExplainerPlugin_Debug_Logger::debug('Content Generator: Generating SEO metadata', 'General');
                $seo_data = $this->generate_seo_metadata($result['content'], $selection_text);
                if ($seo_data) {
                    $result['seo_data'] = $seo_data;
                    if (isset($cost_breakdown)) {
                        $result['cost'] += $cost_breakdown['seo'];
                    }
                    ExplainerPlugin_Debug_Logger::debug('Content Generator: SEO metadata generated successfully', 'General');
                } else {
                    ExplainerPlugin_Debug_Logger::debug('Content Generator: SEO metadata generation failed', 'General');
                }
            } else {
                ExplainerPlugin_Debug_Logger::debug('Content Generator: SEO metadata generation skipped', 'General');
            }
            
            // Generate featured image if requested
            if ($options['generate_image']) {
                ExplainerPlugin_Debug_Logger::debug('Content Generator: Generating featured image', 'General');
                $image_size = isset($options['image_size']) ? $options['image_size'] : 'square';
                $image_id = $this->generate_featured_image($result['content'], $selection_text, $image_size);
                if ($image_id) {
                    $result['featured_image_id'] = $image_id;
                    if (isset($cost_breakdown)) {
                        $result['cost'] += $cost_breakdown['image'];
                    }
                    if (function_exists('explainer_log_debug')) {
                        explainer_log_debug('Featured image generated successfully, ID: ' . $image_id, array(), 'content_generator');
                    }
                } else {
                    ExplainerPlugin_Debug_Logger::debug('Content Generator: Featured image generation failed', 'General');
                }
            } else {
                ExplainerPlugin_Debug_Logger::debug('Content Generator: Featured image generation skipped', 'General');
            }
            
            ExplainerPlugin_Debug_Logger::debug('Content Generator: Blog post generation completed successfully', 'General');
            if (function_exists('explainer_log_debug')) {
                explainer_log_debug('Final cost: £' . $result['cost'], array(), 'content_generator');
            }
            
            return $result;
            
        } catch (Exception $e) {
            if (function_exists('explainer_log_error')) {
                explainer_log_error('Exception caught: ' . $e->getMessage(), array(), 'content_generator');
            }
            if (function_exists('explainer_log_error')) {
                explainer_log_error('Exception trace', array('trace' => $e->getTraceAsString()), 'content_generator');
            }
            return false;
        }
    }
    
    /**
     * Get AI provider instance
     * 
     * @param string $provider_key Provider key (openai-gpt35, openai-gpt4, claude-haiku, claude-sonnet)
     * @return object|null Provider instance or null if not found
     */
    private function get_provider($provider_key) {
        // Map provider keys to actual providers
        $provider_map = array(
            'openai-gpt35' => array('provider' => 'openai', 'model' => 'gpt-3.5-turbo'),
            'openai-gpt4' => array('provider' => 'openai', 'model' => 'gpt-4'),
            'claude-haiku' => array('provider' => 'claude', 'model' => 'claude-3-haiku-20240307'),
            'claude-sonnet' => array('provider' => 'claude', 'model' => 'claude-3-sonnet-20240229')
        );
        
        if (!isset($provider_map[$provider_key])) {
            return null;
        }
        
        $provider_info = $provider_map[$provider_key];
        
        // Get provider from factory
        $provider = ExplainerPlugin_Provider_Factory::get_provider($provider_info['provider']);
        
        if ($provider && method_exists($provider, 'set_model')) {
            $provider->set_model($provider_info['model']);
        }
        
        return $provider;
    }
    
    /**
     * Build comprehensive prompt for content generation
     * 
     * @param string $selection_text Selected text
     * @param array $options Generation options
     * @return string Complete prompt
     */
    private function build_content_prompt($selection_text, $options) {
        // Get WordPress locale for language
        $locale = get_locale();
        $language = $this->get_language_from_locale($locale);
        
        // Get explanation for the selected text first
        $explanation = $this->get_explanation_for_text($selection_text);
        
        // Use the prompt from options and replace placeholders if they exist
        $prompt = isset($options['prompt']) ? $options['prompt'] : '';
        
        // Replace all placeholders (only if they exist - they might already be replaced by frontend)
        $prompt = str_replace('{{selectedtext}}', $selection_text, $prompt);
        $prompt = str_replace('{{explanation}}', $explanation, $prompt);
        $prompt = str_replace('{{wplang}}', $language, $prompt);
        
        // Add length guidance based on the post_length option
        $length = isset($options['length']) ? $options['length'] : 'medium';
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('Using length setting: ' . $length, array(), 'content_generator');
        }
        $length_guidance = $this->get_length_guidance($length);
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('Length guidance: ' . $length_guidance, array(), 'content_generator');
        }
        
        // Add structured content requirements for better SEO
        $structured_requirements = "\n\n=== CRITICAL LENGTH REQUIREMENTS ===\n";
        $structured_requirements .= $length_guidance . "\n";
        
        // Add specific structure for long posts
        if ($length === 'long') {
            $structured_requirements .= "\nYour article MUST include ALL of these sections to reach 1000+ words:\n";
            $structured_requirements .= "1. Introduction (100-150 words)\n";
            $structured_requirements .= "2. What are [topic]? - Definition section (200-300 words)\n";
            $structured_requirements .= "3. Why [topic] matters - Importance section (200-300 words)\n";
            $structured_requirements .= "4. How [topic] works - Process/mechanism section (200-300 words)\n";
            $structured_requirements .= "5. Real-world examples and applications (200-300 words)\n";
            $structured_requirements .= "6. Common misconceptions or challenges (150-200 words)\n";
            $structured_requirements .= "7. Conclusion and key takeaways (100-150 words)\n";
            $structured_requirements .= "\nEach section should be substantial with detailed explanations, examples, and analysis.\n";
        }
        
        $structured_requirements .= "\n=== FORMATTING REQUIREMENTS ===\n";
        $structured_requirements .= "1. Start with an SEO-optimized title on the first line (no 'Title:' prefix)\n";
        $structured_requirements .= "2. Structure the content with proper headings using ## for H2 and ### for H3\n";
        $structured_requirements .= "3. Use descriptive, keyword-rich headings that improve SEO\n";
        $structured_requirements .= "4. Include an introduction paragraph after the title\n";
        $structured_requirements .= "5. Break content into logical sections with H2/H3 headings\n";
        $structured_requirements .= "6. End with a conclusion section\n";
        $structured_requirements .= "7. Write in " . $language . " with proper spelling and grammar\n";
        
        // Add final reminder for long posts
        if ($length === 'long') {
            $structured_requirements .= "\n!!! FINAL REMINDER: This must be at least 1000 words. Do not stop writing until you have covered all sections thoroughly. !!!\n";
        }
        
        return $prompt . $structured_requirements;
    }
    
    /**
     * Get language from WordPress locale
     * 
     * @param string $locale WordPress locale (e.g., en_GB, fr_FR)
     * @return string Language name
     */
    private function get_language_from_locale($locale) {
        // Map common locales to readable language names
        $language_map = array(
            'en_GB' => 'British English',
            'en_US' => 'American English',
            'en_AU' => 'Australian English',
            'en_CA' => 'Canadian English',
            'fr_FR' => 'French',
            'de_DE' => 'German',
            'es_ES' => 'Spanish',
            'it_IT' => 'Italian',
            'pt_PT' => 'Portuguese',
            'nl_NL' => 'Dutch',
            'sv_SE' => 'Swedish',
            'da_DK' => 'Danish',
            'no_NO' => 'Norwegian'
        );
        
        // Return mapped language or default to English
        return isset($language_map[$locale]) ? $language_map[$locale] : 'English';
    }
    
    /**
     * Get explanation for selected text using the current AI provider
     * 
     * @param string $selection_text The selected text to explain
     * @return string Explanation text
     */
    private function get_explanation_for_text($selection_text) {
        try {
            // Get current plugin settings
            $options = get_option('explainer_plugin_options', array());
            $current_provider = isset($options['ai_provider']) ? $options['ai_provider'] : 'openai-gpt35';
            
            // Build simple explanation prompt
            $explanation_prompt = 'Please provide a clear, concise explanation of the following text in 1-2 sentences: ' . $selection_text;
            
            // Get explanation from API proxy
            $explanation = $this->api_proxy->get_direct_explanation($explanation_prompt, $current_provider);
            
            // Return explanation or fallback
            return !empty($explanation) ? trim($explanation) : 'A concept that requires further explanation and context.';
            
        } catch (Exception $e) {
            if (function_exists('explainer_log_error')) {
                explainer_log_error('Explanation generation error: ' . $e->getMessage(), array(), 'content_generator');
            }
            return 'A concept that requires further explanation and context.';
        }
    }
    
    /**
     * Get length guidance for AI
     * 
     * @param string $length Length option (short, medium, long)
     * @return string Length guidance text
     */
    private function get_length_guidance($length) {
        switch($length) {
            case 'short':
                return "CRITICAL: This must be a SHORT blog post of exactly 500 words. Keep it concise and focused. Count your words carefully and reach exactly this target. Do NOT state the word count at the end of the post.";
            
            case 'medium':
                return "CRITICAL: This must be a MEDIUM blog post of exactly 1000 words. Provide good detail and reach exactly this word count. Do NOT state the word count at the end of the post.";
            
            case 'long':
                return "CRITICAL: This must be a LONG, IN-DEPTH blog post of exactly 2000 words. Write extensively with multiple sections, detailed explanations, examples, and comprehensive coverage. This should be a substantial, thorough article. Count your words and ensure you reach exactly 2000 words. Do NOT state the word count at the end of the post.";
            
            default:
                return "CRITICAL: This must be a MEDIUM blog post of exactly 1000 words. Provide good detail and reach exactly this word count. Do NOT state the word count at the end of the post.";
        }
    }
    
    /**
     * Get formatting instructions for AI
     * 
     * @return string Formatting instructions
     */
    private function get_formatting_instructions() {
        return __('Format the blog post with:
- A compelling title (but do not include "Title:" prefix)
- Clear paragraph structure
- Use headings (##) to break up sections
- Include bullet points or numbered lists where appropriate
- Write in an engaging, conversational tone
- Ensure the content is original and informative
- End with a conclusion that summarizes key points', 'ai-explainer');
    }
    
    /**
     * Get humanization instructions for AI
     * 
     * @return string Humanization instructions
     */
    private function get_humanization_instructions() {
        // Get WordPress locale to determine language
        $locale = get_locale();
        $language_instruction = '';
        
        // Add language-specific instruction
        if (strpos($locale, 'en_GB') === 0) {
            $language_instruction = 'Write in British English with UK spelling (colour, realise, centre, etc.) and British terminology.';
        } elseif (strpos($locale, 'en_') === 0) {
            $language_instruction = 'Write in the appropriate English variant for your locale.';
        } else {
            $language_instruction = 'Write in the language appropriate for the WordPress installation locale.';
        }
        
        // Build the humanization prompt without WordPress translation functions
        $humanization_prompt = 'You are a skilled human editor with a natural, confident writing style. Your task is to rewrite the following text to sound like it was written by a thoughtful human, not AI.

### Goals:
- Make the writing feel natural, warm, and subtly varied like something written by a native speaker who cares about clarity.
- Use natural contractions, colloquialisms, and non-robotic phrasing.
- Vary sentence length and structure. Add occasional rhetorical questions or pauses.
- Preserve the meaning and intent of the original text.
- If the tone is formal, keep it formal but human. If it is casual, make it sound more relaxed and real.
- Avoid anything that sounds like AI filler, such as: "In today s fast paced world," "This article will explore...," or generic overstatements.
- Remove repetition and overly perfect grammar that gives AI away.
- Avoid "listicle" tone unless intended.
- Do not hallucinate or introduce new facts.
- Never add emojis and absolutely never use any type of dash or hyphen for punctuation (em dash, en dash, hyphens). Use commas, periods, semicolons, or parentheses instead.
- ' . $language_instruction . '

### Final Check:
- Your output should be extremely hard for an AI detector to flag.
- Sound like a clever person, not a machine.
- Preserve the structure and flow, but feel free to break up long sentences, merge short ones, or reorder slightly for rhythm.
- Double check that no dashes or hyphens appear anywhere in your output.

### Input:';
        
        return $humanization_prompt;
    }
    
    /**
     * Format generated content for WordPress
     * 
     * @param string $raw_content Raw content from AI
     * @return string Formatted content
     */
    private function format_blog_content($raw_content) {
        // Clean up the content
        $content = trim($raw_content);
        
        // Remove any title prefixes if AI included them despite instructions
        $content = preg_replace('/^(Title:\s*|Blog Post:\s*|Article:\s*)/i', '', $content);
        
        // Remove any standalone markdown headers without content (like ###)
        $content = preg_replace('/^#{1,6}\s*$/m', '', $content);
        
        // Convert markdown-style headers to WordPress format
        $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content);
        $content = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $content);
        
        // Convert markdown-style bold and italic
        $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $content);
        
        // Convert markdown-style lists
        $content = preg_replace('/^\* (.+)$/m', '• $1', $content);
        $content = preg_replace('/^\d+\. (.+)$/m', '$0', $content);
        
        // Clean up multiple consecutive line breaks
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        
        // Convert line breaks to paragraphs (WordPress wpautop function)
        $content = wpautop($content);
        
        // Fix any double paragraph tags around headers
        $content = preg_replace('/<p>(<h[1-6]>.*?<\/h[1-6]>)<\/p>/', '$1', $content);
        
        // Remove empty paragraphs
        $content = preg_replace('/<p>\s*<\/p>/', '', $content);
        
        return $content;
    }
    
    /**
     * Generate SEO metadata
     * 
     * @param string $content Generated content
     * @param string $selection_text Original selection
     * @return array|null SEO data or null on failure
     */
    private function generate_seo_metadata($content, $selection_text) {
        try {
            // Build SEO prompt
            $seo_prompt = sprintf(
                /* translators: 1: blog post content excerpt, 2: original topic excerpt */
                __('Based on this blog post content, generate SEO metadata:

Content: %1$s

Please provide:
1. SEO Title (50-60 characters, engaging and descriptive)
2. Meta Description (150-160 characters, compelling summary)
3. Focus Keyword (1-3 words from the original topic: "%2$s")

Format your response as:
SEO Title: [title]
Meta Description: [description]
Focus Keyword: [keyword]', 'ai-explainer'),
                substr(wp_strip_all_tags($content), 0, 1000),
                substr($selection_text, 0, 100)
            );
            
            $seo_response = $this->api_proxy->get_direct_explanation($seo_prompt);
            
            if (!$seo_response) {
                return null;
            }
            
            // Parse SEO response
            $seo_data = array();
            
            if (preg_match('/SEO Title:\s*(.+)$/m', $seo_response, $matches)) {
                $seo_data['_wp_ai_explainer_seo_title'] = trim($matches[1]);
            }
            
            if (preg_match('/Meta Description:\s*(.+)$/m', $seo_response, $matches)) {
                $seo_data['_wp_ai_explainer_meta_description'] = trim($matches[1]);
            }
            
            if (preg_match('/Focus Keyword:\s*(.+)$/m', $seo_response, $matches)) {
                $seo_data['_wp_ai_explainer_keywords'] = trim($matches[1]);
            }
            
            return !empty($seo_data) ? $seo_data : null;
            
        } catch (Exception $e) {
            if (function_exists('explainer_log_error')) {
                explainer_log_error('SEO generation error: ' . $e->getMessage(), array(), 'content_generator');
            }
            return null;
        }
    }
    
    /**
     * Generate featured image using AI
     * 
     * @param string $content Generated content
     * @param string $selection_text Original selection
     * @param string $image_size Image size (portrait, square, wide)
     * @return int|null Attachment ID or null on failure
     */
    private function generate_featured_image($content, $selection_text, $image_size = 'square') {
        try {
            ExplainerPlugin_Debug_Logger::debug('Content Generator: Starting image generation', array(
                'image_size' => $image_size,
                'content_length' => strlen($content),
                'selection_length' => strlen($selection_text)
            ), 'Content_Generator');
            
            // Check if OpenAI API key is available for DALL-E
            // We need to specifically get the OpenAI API key, not the current provider key
            $api_proxy = new ExplainerPlugin_API_Proxy();
            $openai_key = $api_proxy->get_decrypted_api_key_for_provider('openai', true);
            
            if (empty($openai_key)) {
                ExplainerPlugin_Debug_Logger::debug('Content Generator: No OpenAI API key available for DALL-E image generation', array(), 'Content_Generator');
                return null;
            }
            
            ExplainerPlugin_Debug_Logger::debug('Content Generator: OpenAI API key found, proceeding with image generation', array(), 'Content_Generator');
            
            // Create image prompt from content
            $image_prompt = $this->create_image_prompt($content, $selection_text);
            ExplainerPlugin_Debug_Logger::debug('Content Generator: Image prompt created', array(
                'prompt_length' => strlen($image_prompt),
                'prompt_preview' => substr($image_prompt, 0, 100) . '...'
            ), 'Content_Generator');
            
            // Call DALL-E API
            $image_url = $this->call_dalle_api($image_prompt, $openai_key, $image_size);
            
            if (!$image_url) {
                ExplainerPlugin_Debug_Logger::debug('Content Generator: DALL-E API call failed or returned no image URL', array(), 'Content_Generator');
                return null;
            }
            
            ExplainerPlugin_Debug_Logger::debug('Content Generator: DALL-E API call successful', array(
                'image_url' => $image_url
            ), 'Content_Generator');
            
            // Download and save image to WordPress media library
            $attachment_id = $this->save_image_to_media_library($image_url, $image_prompt);
            
            if ($attachment_id) {
                ExplainerPlugin_Debug_Logger::debug('Content Generator: Image saved to media library successfully', array(
                    'attachment_id' => $attachment_id
                ), 'Content_Generator');
            } else {
                ExplainerPlugin_Debug_Logger::debug('Content Generator: Failed to save image to media library', array(), 'Content_Generator');
            }
            
            return $attachment_id;
            
        } catch (Exception $e) {
            ExplainerPlugin_Debug_Logger::debug('Content Generator: Image generation exception: ' . $e->getMessage(), array(
                'trace' => $e->getTraceAsString()
            ), 'Content_Generator');
            if (function_exists('explainer_log_error')) {
                explainer_log_error('Image generation error: ' . $e->getMessage(), array(), 'content_generator');
            }
            return null;
        }
    }
    
    /**
     * Create image prompt from content
     * 
     * @param string $content Blog content
     * @param string $selection_text Original selection
     * @return string Image prompt
     */
    private function create_image_prompt($content, $selection_text) {
        // Extract key concepts from content
        $key_concepts = $this->extract_key_concepts($content, $selection_text);
        
        // Build descriptive prompt
        $prompt = sprintf('A professional, modern illustration representing %s. Clean, minimalist design with vibrant colours, suitable for a blog header image. No text or words in the image.', esc_html($key_concepts));
        
        return $prompt;
    }
    
    /**
     * Extract key concepts for image generation
     * 
     * @param string $content Blog content
     * @param string $selection_text Original selection
     * @return string Key concepts
     */
    private function extract_key_concepts($content, $selection_text) {
        // Start with original selection as base concept
        $concepts = array(substr($selection_text, 0, 50));
        
        // Extract headings from content
        if (preg_match_all('/<h[1-3]>(.+?)<\/h[1-3]>/i', $content, $matches)) {
            $concepts = array_merge($concepts, array_slice($matches[1], 0, 2));
        }
        
        // Join concepts
        $key_concepts = implode(', ', array_unique($concepts));
        
        // Limit length
        if (strlen($key_concepts) > 100) {
            $key_concepts = substr($key_concepts, 0, 97) . '...';
        }
        
        return $key_concepts;
    }
    
    /**
     * Call DALL-E API to generate image
     * 
     * @param string $prompt Image prompt
     * @param string $api_key OpenAI API key
     * @param string $image_size Image size (portrait, square, wide)
     * @return string|null Image URL or null on failure
     */
    private function call_dalle_api($prompt, $api_key, $image_size = 'square') {
        $url = 'https://api.openai.com/v1/images/generations';
        
        // Map image size to DALL-E dimensions
        $size_map = array(
            'portrait' => '1024x1792',
            'square' => '1024x1024',
            'wide' => '1792x1024'
        );
        
        $dalle_size = isset($size_map[$image_size]) ? $size_map[$image_size] : '1024x1024';
        
        ExplainerPlugin_Debug_Logger::debug('Content Generator: Preparing DALL-E API call', array(
            'url' => $url,
            'image_size' => $image_size,
            'dalle_size' => $dalle_size,
            'prompt_length' => strlen($prompt),
            'api_key_present' => !empty($api_key),
            'api_key_length' => strlen($api_key)
        ), 'Content_Generator');
        
        $data = array(
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => $dalle_size,
            'quality' => 'standard'
        );
        
        $args = array(
            'timeout' => 60, // Increased timeout for image generation
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($data)
        );
        
        ExplainerPlugin_Debug_Logger::debug('Content Generator: Making DALL-E API request', array(
            'request_data' => $data,
            'timeout' => $args['timeout']
        ), 'Content_Generator');
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            ExplainerPlugin_Debug_Logger::debug('Content Generator: DALL-E API request failed with WP Error', array(
                'error_message' => $error_message,
                'error_code' => $response->get_error_code()
            ), 'Content_Generator');
            if (function_exists('explainer_log_error')) {
                explainer_log_error('DALL-E API WP Error: ' . $error_message, array(), 'content_generator');
            }
            return null;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        ExplainerPlugin_Debug_Logger::debug('Content Generator: DALL-E API response received', array(
            'response_code' => $response_code,
            'response_body_length' => strlen($body),
            'response_body_preview' => substr($body, 0, 500) . '...'
        ), 'Content_Generator');
        
        if ($response_code !== 200) {
            ExplainerPlugin_Debug_Logger::debug('Content Generator: DALL-E API returned non-200 status', array(
                'status_code' => $response_code,
                'response_body' => $body
            ), 'Content_Generator');
            if (function_exists('explainer_log_error')) {
                explainer_log_error('DALL-E API Error', array(
                    'response_code' => $response_code,
                    'body' => $body
                ), 'content_generator');
            }
            return null;
        }
        
        $response_data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            ExplainerPlugin_Debug_Logger::debug('Content Generator: Failed to decode DALL-E API response JSON', array(
                'json_error' => json_last_error_msg(),
                'response_body' => $body
            ), 'Content_Generator');
            if (function_exists('explainer_log_error')) {
                explainer_log_error('DALL-E API JSON decode error: ' . json_last_error_msg(), array(), 'content_generator');
            }
            return null;
        }
        
        if (isset($response_data['data'][0]['url'])) {
            $image_url = $response_data['data'][0]['url'];
            ExplainerPlugin_Debug_Logger::debug('Content Generator: DALL-E API returned image URL successfully', array(
                'image_url' => $image_url
            ), 'Content_Generator');
            return $image_url;
        }
        
        ExplainerPlugin_Debug_Logger::debug('Content Generator: DALL-E API response missing expected image URL', array(
            'response_data_keys' => array_keys($response_data),
            'response_data' => $response_data
        ), 'Content_Generator');
        if (function_exists('explainer_log_error')) {
            explainer_log_error('DALL-E API response missing image URL', array(
                'response_data' => $response_data
            ), 'content_generator');
        }
        
        return null;
    }
    
    /**
     * Save image from URL to WordPress media library
     * 
     * @param string $image_url Image URL
     * @param string $description Image description
     * @return int|null Attachment ID or null on failure
     */
    private function save_image_to_media_library($image_url, $description) {
        ExplainerPlugin_Debug_Logger::debug('Content Generator: Starting image save to media library', array(
            'image_url' => $image_url,
            'description_length' => strlen($description)
        ), 'Content_Generator');
        
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Download image
        ExplainerPlugin_Debug_Logger::debug('Content Generator: Downloading image from URL', array(
            'image_url' => $image_url
        ), 'Content_Generator');
        
        $temp_file = download_url($image_url);
        
        if (is_wp_error($temp_file)) {
            $error_message = $temp_file->get_error_message();
            ExplainerPlugin_Debug_Logger::debug('Content Generator: Failed to download image', array(
                'error_message' => $error_message,
                'error_code' => $temp_file->get_error_code()
            ), 'Content_Generator');
            if (function_exists('explainer_log_error')) {
                explainer_log_error('Image download error: ' . $error_message, array(), 'content_generator');
            }
            return null;
        }
        
        ExplainerPlugin_Debug_Logger::debug('Content Generator: Image downloaded successfully', array(
            'temp_file' => $temp_file,
            'file_size' => file_exists($temp_file) ? filesize($temp_file) : 'file_not_found'
        ), 'Content_Generator');
        
        // Prepare file array
        $filename = 'ai-generated-' . time() . '.png';
        $file_array = array(
            'name' => $filename,
            'tmp_name' => $temp_file
        );
        
        ExplainerPlugin_Debug_Logger::debug('Content Generator: Preparing to upload to media library', array(
            'filename' => $filename,
            'temp_file_exists' => file_exists($temp_file)
        ), 'Content_Generator');
        
        // Upload to media library
        $attachment_id = media_handle_sideload($file_array, 0, $description);

        // Clean up temp file
        wp_delete_file($temp_file);
        
        if (is_wp_error($attachment_id)) {
            $error_message = $attachment_id->get_error_message();
            ExplainerPlugin_Debug_Logger::debug('Content Generator: Failed to upload image to media library', array(
                'error_message' => $error_message,
                'error_code' => $attachment_id->get_error_code()
            ), 'Content_Generator');
            if (function_exists('explainer_log_error')) {
                explainer_log_error('Media library upload error: ' . $error_message, array(), 'content_generator');
            }
            return null;
        }
        
        ExplainerPlugin_Debug_Logger::debug('Content Generator: Image uploaded to media library successfully', array(
            'attachment_id' => $attachment_id,
            'attachment_url' => wp_get_attachment_url($attachment_id)
        ), 'Content_Generator');
        
        return $attachment_id;
    }
    
    
    /**
     * Validate generation options
     * 
     * @param array $options Options to validate
     * @return bool True if valid
     */
    public function validate_options($options) {
        // Required fields
        if (empty($options['selection_text']) || empty($options['prompt'])) {
            return false;
        }
        
        // Valid length options
        if (!in_array($options['length'], array('short', 'medium', 'long'))) {
            return false;
        }
        
        // Valid provider options
        $valid_providers = array('openai-gpt35', 'openai-gpt4', 'claude-haiku', 'claude-sonnet');
        if (!in_array($options['ai_provider'], $valid_providers)) {
            return false;
        }
        
        // Prompt validation - no longer require placeholder since it might be replaced already
        // Just ensure prompt is not empty
        
        return true;
    }
    
    /**
     * Generate mock content for testing purposes
     * 
     * @param string $selection_text The selected text to base content on
     * @param array $options Generation options
     * @return array Mock content result
     */
    private function generate_mock_content($selection_text, $options) {
        explainer_log_info('Content Generator: Generating mock content for testing', array(
            'selection_length' => strlen($selection_text),
            'post_length' => $options['length'] ?? 'medium',
            'ai_provider' => $options['ai_provider'] ?? 'unknown',
            'generate_image' => $options['generate_image'] ?? false,
            'generate_seo' => $options['generate_seo'] ?? false
        ), 'Content_Generator');
        
        // Simulate processing time (1-3 seconds)
        $processing_time = wp_rand(1, 3);
        explainer_log_debug('Content Generator: Simulating processing delay', array(
            'delay_seconds' => $processing_time
        ), 'Content_Generator');
        sleep($processing_time);
        
        // Get mock content based on length
        $mock_content = $this->get_mock_content_by_length($selection_text, $options['length'] ?? 'medium');
        
        // Format the mock content (convert markdown to HTML, same as real API content)
        $formatted_content = $this->format_blog_content($mock_content);
        
        // Extract title from first line of mock content (before HTML formatting)
        $content_lines = explode("\n", trim($mock_content));
        $title = '';
        if (!empty($content_lines[0])) {
            // Remove any markdown formatting from the title line
            $title = trim(preg_replace('/^#+\s*/', '', $content_lines[0]));
            // Remove any remaining markdown or unwanted formatting
            $title = trim(wp_strip_all_tags($title));
        }
        
        // Initialize result array
        $result = array(
            'content' => $formatted_content,
            'title' => $title,
            'seo_data' => null,
            'featured_image_id' => null,
            'cost' => $this->calculate_mock_cost($options)
        );
        
        // Generate mock SEO data if requested
        if (!empty($options['generate_seo'])) {
            ExplainerPlugin_Debug_Logger::debug('Content Generator: Generating mock SEO data', array(), 'Content_Generator', 'CONTENT_GENERATOR');
            $result['seo_data'] = $this->generate_mock_seo_data($selection_text);
        }
        
        // Generate mock featured image if requested
        if (!empty($options['generate_image'])) {
            ExplainerPlugin_Debug_Logger::debug('Content Generator: Generating mock featured image', array(), 'Content_Generator', 'CONTENT_GENERATOR');
            $result['featured_image_id'] = $this->generate_mock_featured_image();
        }
        
        explainer_log_info('Content Generator: Mock content generation completed', array(
            'content_length' => strlen($result['content']),
            'has_seo_data' => !empty($result['seo_data']),
            'has_featured_image' => !empty($result['featured_image_id']),
            'total_cost' => $result['cost']
        ), 'Content_Generator');
        
        return $result;
    }
    
    /**
     * Get mock content based on post length
     * 
     * @param string $selection_text Original selected text
     * @param string $length Post length (short, medium, long)
     * @return string Mock blog post content
     */
    private function get_mock_content_by_length($selection_text, $length) {
        // Create a topic from the selection text (first few words)
        $topic_words = explode(' ', $selection_text);
        $topic = implode(' ', array_slice($topic_words, 0, 3));
        
        // Generate title
        $title = "Understanding " . ucwords($topic) . ": A Comprehensive Guide";
        
        switch ($length) {
            case 'short':
                return $this->generate_short_mock_content($title, $topic, $selection_text);
            case 'long':
                return $this->generate_long_mock_content($title, $topic, $selection_text);
            case 'medium':
            default:
                return $this->generate_medium_mock_content($title, $topic, $selection_text);
        }
    }
    
    /**
     * Generate short mock content (500 words)
     */
    private function generate_short_mock_content($title, $topic, $selection_text) {
        return $title . "\n\n" .
               "## Introduction\n\n" .
               ucfirst($topic) . " is an important concept. " .
               "This concept can be described as \"" . substr($selection_text, 0, 100) . "\".\n\n" .
               
               "## What is " . ucwords($topic) . "?\n\n" .
               $topic . " affects how we approach various challenges. " .
               "Understanding this concept helps with decision-making and effectiveness. " .
               "The core idea involves considering multiple factors.\n\n" .
               
               "## Key Benefits\n\n" .
               "Understanding " . strtolower($topic) . " helps with:\n\n" .
               "• Better decisions\n" .
               "• Problem-solving\n" .
               "• Resource allocation\n" .
               "• Daily task efficiency\n\n" .
               
               "## Conclusion\n\n" .
               ucfirst($topic) . " is a valuable tool for navigating complex situations. By applying these principles, " .
               "you can achieve better outcomes and make more informed choices.";
    }
    
    /**
     * Generate medium mock content (1000 words)
     */
    private function generate_medium_mock_content($title, $topic, $selection_text) {
        return $title . "\n\n" .
               "## Introduction\n\n" .
               "In the modern landscape of innovation and progress, " . strtolower($topic) . " has emerged " .
               "as a cornerstone concept that influences numerous fields and applications. " .
               "Originally described as \"" . substr($selection_text, 0, 150) . "\", this fascinating " .
               "area of study continues to evolve and shape our understanding of complex systems.\n\n" .
               
               "The importance of " . strtolower($topic) . " cannot be overstated, particularly as we " .
               "face increasingly sophisticated challenges in technology, business, and society. " .
               "This comprehensive guide will explore the fundamental principles, practical applications, " .
               "and future implications of this critical concept.\n\n" .
               
               "## Understanding the Fundamentals\n\n" .
               ucwords($topic) . " encompasses a broad range of principles and methodologies that work " .
               "together to create effective solutions. At its core, this concept involves the systematic " .
               "analysis of problems and the development of strategic approaches to address them.\n\n" .
               
               "The key components include:\n\n" .
               "• **Analysis and Assessment**: Thorough evaluation of existing conditions\n" .
               "• **Strategic Planning**: Development of comprehensive action plans\n" .
               "• **Implementation**: Practical application of theoretical concepts\n" .
               "• **Monitoring and Adjustment**: Continuous improvement through feedback\n\n" .
               
               "## Practical Applications\n\n" .
               "The versatility of " . strtolower($topic) . " makes it applicable across various domains. " .
               "In business environments, organisations leverage these principles to optimise operations, " .
               "improve customer satisfaction, and drive sustainable growth.\n\n" .
               
               "Educational institutions have also recognised the value of incorporating " . strtolower($topic) . " " .
               "into their curricula, helping students develop critical thinking skills and practical " .
               "problem-solving abilities that serve them throughout their careers.\n\n" .
               
               "## Benefits and Advantages\n\n" .
               "Implementing " . strtolower($topic) . " principles offers numerous benefits:\n\n" .
               "1. **Enhanced Efficiency**: Streamlined processes and reduced waste\n" .
               "2. **Improved Decision Making**: Data-driven insights and strategic thinking\n" .
               "3. **Risk Mitigation**: Proactive identification and management of potential issues\n" .
               "4. **Innovation Catalyst**: Foundation for creative problem-solving approaches\n" .
               "5. **Competitive Advantage**: Differentiation through superior methodologies\n\n" .
               
               "## Future Perspectives\n\n" .
               "As we look towards the future, " . strtolower($topic) . " will undoubtedly continue " .
               "to evolve and adapt to emerging challenges and opportunities. Technological advancements, " .
               "changing social dynamics, and global interconnectedness will all influence how these " .
               "principles are applied and refined.\n\n" .
               
               "## Conclusion\n\n" .
               "The exploration of " . strtolower($topic) . " reveals its fundamental importance in " .
               "addressing contemporary challenges and driving progress across multiple sectors. " .
               "By understanding and applying these principles, individuals and organisations can " .
               "achieve significant improvements in effectiveness, efficiency, and innovation. " .
               "As this field continues to develop, staying informed about new developments and " .
               "best practices will be essential for maximising its potential benefits.";
    }
    
    /**
     * Generate long mock content (2000 words)
     */
    private function generate_long_mock_content($title, $topic, $selection_text) {
        return $title . "\n\n" .
               "## Introduction\n\n" .
               "In the intricate tapestry of modern knowledge and innovation, " . strtolower($topic) . " " .
               "stands as one of the most significant and transformative concepts of our time. " .
               "This comprehensive exploration delves deep into the multifaceted nature of this subject, " .
               "examining not only its theoretical foundations but also its profound practical implications " .
               "across diverse fields and industries.\n\n" .
               
               "Originally conceptualised as \"" . substr($selection_text, 0, 200) . "\", this area of " .
               "study has evolved far beyond its initial scope to become a fundamental pillar in " .
               "understanding complex systems, processes, and interactions. The journey of discovery " .
               "that surrounds " . strtolower($topic) . " continues to unfold, revealing new insights " .
               "and applications that challenge our conventional thinking and push the boundaries " .
               "of what we thought possible.\n\n" .
               
               "## Historical Context and Evolution\n\n" .
               "The development of " . strtolower($topic) . " can be traced through several distinct " .
               "phases, each contributing unique perspectives and methodologies to our current understanding. " .
               "Early pioneers in this field laid the groundwork for what would become a revolutionary " .
               "approach to problem-solving and system optimisation.\n\n" .
               
               "Throughout the decades, researchers and practitioners have continually refined and " .
               "expanded upon these foundational concepts, integrating new technologies, methodologies, " .
               "and philosophical approaches. This evolution has been marked by significant breakthroughs " .
               "that have fundamentally altered how we perceive and interact with complex challenges.\n\n" .
               
               "## Core Principles and Theoretical Framework\n\n" .
               "At the heart of " . strtolower($topic) . " lies a sophisticated framework of " .
               "interconnected principles that work synergistically to create powerful solutions. " .
               "These principles form the backbone of effective implementation and provide the " .
               "theoretical foundation upon which practical applications are built.\n\n" .
               
               "### Primary Components\n\n" .
               "The fundamental elements include:\n\n" .
               "• **Systematic Analysis**: Comprehensive examination of all relevant factors\n" .
               "• **Strategic Integration**: Harmonious combination of diverse elements\n" .
               "• **Adaptive Methodology**: Flexible approaches that respond to changing conditions\n" .
               "• **Continuous Optimisation**: Ongoing refinement and improvement processes\n" .
               "• **Holistic Perspective**: Consideration of broader implications and interconnections\n\n" .
               
               "### Secondary Considerations\n\n" .
               "Supporting these primary components are several secondary considerations that enhance " .
               "the overall effectiveness of " . strtolower($topic) . " implementations:\n\n" .
               "• Risk assessment and mitigation strategies\n" .
               "• Resource allocation and management protocols\n" .
               "• Quality assurance and control mechanisms\n" .
               "• Stakeholder engagement and communication frameworks\n" .
               "• Performance measurement and evaluation systems\n\n" .
               
               "## Practical Applications Across Industries\n\n" .
               "The versatility and adaptability of " . strtolower($topic) . " have made it invaluable " .
               "across numerous sectors and applications. From manufacturing and healthcare to " .
               "education and environmental management, the principles and methodologies associated " .
               "with this concept have proven their worth time and again.\n\n" .
               
               "### Business and Commercial Applications\n\n" .
               "In the corporate world, " . strtolower($topic) . " has revolutionised operational " .
               "efficiency and strategic planning. Companies that have successfully integrated these " .
               "principles report significant improvements in productivity, customer satisfaction, " .
               "and profitability. The ability to adapt quickly to market changes whilst maintaining " .
               "operational excellence has become a defining characteristic of successful organisations.\n\n" .
               
               "### Educational and Research Contexts\n\n" .
               "Academic institutions have embraced " . strtolower($topic) . " as both a subject " .
               "of study and a methodology for enhancing educational outcomes. Research programmes " .
               "dedicated to advancing our understanding of these concepts continue to produce " .
               "groundbreaking insights that benefit society as a whole.\n\n" .
               
               "## Implementation Strategies and Best Practices\n\n" .
               "Successfully implementing " . strtolower($topic) . " requires careful planning, " .
               "strategic thinking, and a deep understanding of the specific context and requirements. " .
               "The following strategies have proven most effective:\n\n" .
               
               "1. **Comprehensive Assessment**: Thorough evaluation of current state and requirements\n" .
               "2. **Stakeholder Engagement**: Active involvement of all relevant parties\n" .
               "3. **Phased Implementation**: Gradual rollout with regular evaluation points\n" .
               "4. **Continuous Training**: Ongoing education and skill development\n" .
               "5. **Performance Monitoring**: Regular assessment and adjustment of strategies\n\n" .
               
               "## Challenges and Considerations\n\n" .
               "Despite its many advantages, implementing " . strtolower($topic) . " is not without " .
               "challenges. Common obstacles include resistance to change, resource constraints, " .
               "technical complexities, and the need for specialised expertise. However, these " .
               "challenges can be effectively managed through proper planning, adequate resource " .
               "allocation, and strong leadership commitment.\n\n" .
               
               "## Future Trends and Developments\n\n" .
               "The future of " . strtolower($topic) . " promises exciting developments as " .
               "emerging technologies, changing social dynamics, and evolving business requirements " .
               "continue to shape its evolution. Artificial intelligence, machine learning, and " .
               "advanced analytics are opening new possibilities for application and refinement.\n\n" .
               
               "Sustainability considerations are also becoming increasingly important, with " .
               "practitioners seeking ways to integrate environmental and social responsibility " .
               "into their approaches. This trend towards sustainable and ethical implementation " .
               "represents a significant opportunity for innovation and positive impact.\n\n" .
               
               "## Conclusion\n\n" .
               "This comprehensive examination of " . strtolower($topic) . " reveals its fundamental " .
               "importance as both a theoretical concept and a practical methodology. The principles, " .
               "applications, and future potential discussed throughout this exploration demonstrate " .
               "the transformative power of this approach when properly understood and implemented.\n\n" .
               
               "As we continue to face increasingly complex challenges in our interconnected world, " .
               "the relevance and value of " . strtolower($topic) . " will only continue to grow. " .
               "By embracing these concepts and integrating them into our personal and professional " .
               "practices, we can achieve remarkable improvements in effectiveness, efficiency, and " .
               "overall success. The journey of discovery and application continues, promising " .
               "even greater innovations and achievements in the years to come.";
    }
    
    /**
     * Generate mock SEO data
     * 
     * @param string $selection_text Original selection
     * @return array Mock SEO metadata
     */
    private function generate_mock_seo_data($selection_text) {
        $topic_words = explode(' ', $selection_text);
        $main_topic = implode(' ', array_slice($topic_words, 0, 2));
        
        return array(
            '_wp_ai_explainer_seo_title' => "Complete Guide to " . ucwords($main_topic) . " - Expert Insights",
            '_wp_ai_explainer_meta_description' => "Discover everything you need to know about " . strtolower($main_topic) . ". Expert analysis, practical tips, and comprehensive insights in this detailed guide.",
            '_wp_ai_explainer_keywords' => strtolower($main_topic) . ", guide, analysis, tips"
        );
    }
    
    /**
     * Generate mock featured image
     * 
     * @return int Mock attachment ID
     */
    private function generate_mock_featured_image() {
        // Return a mock attachment ID (in real implementation, this would be a real image)
        return 999999; // Obviously fake ID for testing
    }
    
    /**
     * Calculate mock cost based on options
     * 
     * @param array $options Generation options
     * @return float Mock cost in GBP
     */
    private function calculate_mock_cost($options) {
        $base_cost = 0.05; // Base cost for content generation
        
        // Add cost based on length
        switch ($options['length'] ?? 'medium') {
            case 'short':
                $base_cost += 0.02;
                break;
            case 'long':
                $base_cost += 0.08;
                break;
            case 'medium':
            default:
                $base_cost += 0.05;
                break;
        }
        
        // Add cost for image generation
        if (!empty($options['generate_image'])) {
            $base_cost += 0.04;
        }
        
        // Add cost for SEO generation
        if (!empty($options['generate_seo'])) {
            $base_cost += 0.02;
        }
        
        return round($base_cost, 3);
    }
}