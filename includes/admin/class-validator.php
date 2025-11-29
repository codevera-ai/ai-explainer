<?php
/**
 * Validator class for handling all input validation and sanitization
 * Extracted from class-admin.php to improve maintainability
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle validation and sanitization of all plugin settings
 */
class ExplainerPlugin_Validator {
    
    /**
     * Validate custom prompt template
     */
    public function validate_custom_prompt($value) {
        // Sanitize as plain text only
        $sanitized = sanitize_textarea_field($value);
        
        // Set default if empty
        if (empty($sanitized)) {
            $sanitized = ExplainerPlugin_Config::get_default_custom_prompt();
        }
        
        // Check for required {{selectedtext}} variable
        if (!str_contains($sanitized, '{{selectedtext}}')) {
            // Return default if validation fails
            return ExplainerPlugin_Config::get_default_custom_prompt();
        }
        
        // Length limit removed - no character restriction
        
        return $sanitized;
    }
    
    /**
     * Validate API provider
     */
    public function validate_api_provider($value) {
        $valid_providers = array('openai', 'claude', 'openrouter', 'gemini');
        
        if (!in_array($value, $valid_providers)) {
            return 'openai'; // Default to OpenAI if invalid
        }
        
        return $value;
    }
    
    /**
     * Validate language setting
     */
    public function validate_language($value) {
        $valid_languages = array('en_US', 'en_GB', 'es_ES', 'de_DE', 'fr_FR', 'hi_IN', 'zh_CN');
        
        if (!in_array($value, $valid_languages)) {
            return 'en_GB'; // Default to British English if invalid
        }
        
        return $value;
    }
    
    /**
     * Validate maximum selection length
     */
    public function validate_max_selection_length($value) {
        $value = absint($value);
        
        if ($value < 50 || $value > 1000) {
            return 200; // Default value
        }
        
        return $value;
    }
    
    /**
     * Validate minimum selection length
     */
    public function validate_min_selection_length($value) {
        $value = absint($value);
        
        if ($value < 1 || $value > 50) {
            return 3; // Default value
        }
        
        return $value;
    }
    
    /**
     * Validate maximum words
     */
    public function validate_max_words($value) {
        $value = absint($value);
        
        if ($value < 5 || $value > 100) {
            return 30; // Default value
        }
        
        return $value;
    }
    
    /**
     * Validate minimum words
     */
    public function validate_min_words($value) {
        $value = absint($value);
        
        if ($value < 1 || $value > 10) {
            return 1; // Default value
        }
        
        return $value;
    }
    
    /**
     * Validate cache duration
     */
    public function validate_cache_duration($value) {
        $value = absint($value);
        
        if ($value < 1 || $value > 168) {
            return 24; // Default value
        }
        
        return $value;
    }
    
    /**
     * Validate rate limit for logged users
     */
    public function validate_rate_limit_logged($value) {
        $value = absint($value);
        
        if ($value < 1 || $value > 100) {
            return 20; // Default value
        }
        
        return $value;
    }
    
    /**
     * Validate rate limit for anonymous users
     */
    public function validate_rate_limit_anonymous($value) {
        $value = absint($value);
        
        if ($value < 1 || $value > 50) {
            return 10; // Default value
        }
        
        return $value;
    }
    
    /**
     * Validate mobile selection delay
     */
    public function validate_mobile_selection_delay($value) {
        $value = absint($value);
        
        if ($value < 0 || $value > 5000) {
            return 1000; // Default value
        }
        
        return $value;
    }
    
    /**
     * Validate toggle position
     */
    public function validate_toggle_position($value) {
        $valid_positions = array('bottom-right', 'bottom-left', 'top-right', 'top-left');
        
        if (!in_array($value, $valid_positions)) {
            return 'bottom-right'; // Default position
        }
        
        return $value;
    }
    
    
    /**
     * Sanitize blocked words list
     */
    public function sanitize_blocked_words($value) {
        if (empty($value)) {
            return '';
        }
        
        // Split by newlines
        $words = explode("\n", $value);
        $sanitized_words = array();
        
        foreach ($words as $word) {
            // Trim whitespace
            $word = trim($word);
            
            // Skip empty lines
            if (empty($word)) {
                continue;
            }
            
            // Sanitize the word (allow letters, numbers, spaces, hyphens, and common punctuation)
            $word = preg_replace('/[^a-zA-Z0-9\s\-_.,!?\'"]/u', '', $word);
            
            // Limit word length to prevent abuse
            if (strlen($word) > 100) {
                $word = substr($word, 0, 100);
            }
            
            // Add to sanitized list
            if (!empty($word)) {
                $sanitized_words[] = $word;
            }
        }
        
        // Limit total number of blocked words to 500
        $sanitized_words = array_slice($sanitized_words, 0, 500);
        
        // Join back with newlines
        return implode("\n", $sanitized_words);
    }
    
    /**
     * Validate term extraction prompt
     */
    public function validate_term_extraction_prompt($value) {
        // Check if term extraction service is available (pro feature)
        if (!class_exists('ExplainerPlugin_Term_Extraction_Service')) {
            // In free version, just sanitize and return
            return sanitize_textarea_field($value);
        }

        // Sanitize as textarea
        $sanitized = sanitize_textarea_field($value);

        // Set default if empty
        if (empty($sanitized)) {
            $sanitized = ExplainerPlugin_Term_Extraction_Service::get_default_prompt();
        }

        // Use the service validation method
        $validation_result = ExplainerPlugin_Term_Extraction_Service::validate_prompt_template($sanitized);

        if (!$validation_result['success']) {
            // Return default if validation fails
            add_settings_error(
                'explainer_term_extraction_prompt',
                'invalid_prompt_template',
                /* translators: %s: error message describing why the prompt template is invalid */
                sprintf(__('Term extraction prompt template invalid: %s. Using default template.', 'ai-explainer'), esc_html($validation_result['error']))
            );
            return ExplainerPlugin_Term_Extraction_Service::get_default_prompt();
        }

        return $sanitized;
    }
    
    /**
     * Validate minimum terms count
     */
    public function validate_term_extraction_min_terms($value) {
        $int_value = intval($value);
        
        // Ensure it's within valid range (10-50)
        if ($int_value < 10) {
            $int_value = 10;
        } elseif ($int_value > 50) {
            $int_value = 50;
        }
        
        return $int_value;
    }
    
    /**
     * Validate maximum terms count
     */
    public function validate_term_extraction_max_terms($value) {
        $int_value = intval($value);
        
        // Ensure it's within valid range (10-50)
        if ($int_value < 10) {
            $int_value = 10;
        } elseif ($int_value > 50) {
            $int_value = 50;
        }
        
        // Ensure max is not less than min
        $min_terms = get_option('explainer_term_extraction_min_terms', 10);
        if ($int_value < $min_terms) {
            $int_value = $min_terms;
        }
        
        return $int_value;
    }
}