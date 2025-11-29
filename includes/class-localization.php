<?php
/**
 * Localization and cultural adaptation helper
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle localization, number formatting, and cultural adaptations
 */
class ExplainerPlugin_Localization {
    
    /**
     * Get localized number format
     */
    public static function format_number($number, $decimals = 0) {
        $language = get_option('explainer_language', 'en_GB');
        
        $formats = array(
            'en_US' => array('decimal' => '.', 'thousands' => ','),
            'en_GB' => array('decimal' => '.', 'thousands' => ','),
            'es_ES' => array('decimal' => ',', 'thousands' => '.'),
            'de_DE' => array('decimal' => ',', 'thousands' => '.'),
            'fr_FR' => array('decimal' => ',', 'thousands' => ' '),
            'hi_IN' => array('decimal' => '.', 'thousands' => ','),
            'zh_CN' => array('decimal' => '.', 'thousands' => ',')
        );
        
        $format = isset($formats[$language]) ? $formats[$language] : $formats['en_GB'];
        
        return number_format($number, $decimals, $format['decimal'], $format['thousands']);
    }
    
    /**
     * Get localized date format
     */
    public static function format_date($timestamp) {
        $language = get_option('explainer_language', 'en_GB');
        
        $formats = array(
            'en_US' => 'M j, Y g:i A',     // Jan 1, 2024 3:45 PM
            'en_GB' => 'j M Y H:i',        // 1 Jan 2024 15:45
            'es_ES' => 'j/n/Y H:i',        // 1/1/2024 15:45
            'de_DE' => 'j.n.Y H:i',        // 1.1.2024 15:45
            'fr_FR' => 'j/n/Y H:i',        // 1/1/2024 15:45
            'hi_IN' => 'j/n/Y H:i',        // 1/1/2024 15:45
            'zh_CN' => 'Y年n月j日 H:i'      // 2024年1月1日 15:45
        );
        
        $format = isset($formats[$language]) ? $formats[$language] : $formats['en_GB'];
        
        return gmdate($format, $timestamp);
    }
    
    /**
     * Get localized currency symbol (for future use with API costs)
     */
    public static function get_currency_symbol() {
        $language = get_option('explainer_language', 'en_GB');
        
        $symbols = array(
            'en_US' => '$',
            'en_GB' => '£',
            'es_ES' => '€',
            'de_DE' => '€',
            'fr_FR' => '€',
            'hi_IN' => '₹',
            'zh_CN' => '¥'
        );
        
        return isset($symbols[$language]) ? $symbols[$language] : '$';
    }
    
    /**
     * Get cultural adaptation for error messages
     */
    public static function get_polite_error_message($base_message) {
        $language = get_option('explainer_language', 'en_GB');
        
        $politeness_prefixes = array(
            'en_US' => '',
            'en_GB' => 'I\'m sorry, but ',
            'es_ES' => 'Disculpe, ',
            'de_DE' => 'Entschuldigung, ',
            'fr_FR' => 'Désolé, ',
            'hi_IN' => 'क्षमा करें, ',
            'zh_CN' => '抱歉，'
        );
        
        $prefix = isset($politeness_prefixes[$language]) ? $politeness_prefixes[$language] : '';
        
        return $prefix . $base_message;
    }
    
    /**
     * Get localized help text adaptations
     */
    public static function get_help_text_style() {
        $language = get_option('explainer_language', 'en_GB');
        
        $styles = array(
            'en_US' => 'direct',      // Direct, concise instructions
            'en_GB' => 'polite',      // More polite, formal language
            'es_ES' => 'formal',      // Formal, respectful tone
            'de_DE' => 'formal',      // Formal, structured approach
            'fr_FR' => 'polite',      // Polite, elegant phrasing
            'hi_IN' => 'respectful',  // Respectful, traditional approach
            'zh_CN' => 'respectful'   // Respectful, formal tone
        );
        
        return isset($styles[$language]) ? $styles[$language] : 'polite';
    }
    
    /**
     * Get reading direction for the language
     */
    public static function get_text_direction() {
        $language = get_option('explainer_language', 'en_GB');
        
        // All currently supported languages are LTR
        // Future RTL languages (Arabic, Hebrew) would return 'rtl'
        return 'ltr';
    }
    
    /**
     * Get appropriate tooltip positioning based on text direction
     */
    public static function get_tooltip_positioning_preference() {
        $direction = self::get_text_direction();
        $language = get_option('explainer_language', 'en_GB');
        
        $preferences = array(
            'en_US' => 'bottom-right',
            'en_GB' => 'bottom-right',
            'es_ES' => 'bottom-right',
            'de_DE' => 'bottom-right',
            'fr_FR' => 'bottom-right',
            'hi_IN' => 'bottom-right',
            'zh_CN' => 'bottom-left'  // Different preference for Chinese
        );
        
        return isset($preferences[$language]) ? $preferences[$language] : 'bottom-right';
    }
    
    /**
     * Get localized validation messages
     */
    public static function get_validation_message($type, $context = array()) {
        $language = get_option('explainer_language', 'en_GB');
        
        $messages = array(
            'text_too_short' => array(
                'en_US' => 'Please select at least {min} characters.',
                'en_GB' => 'Please select at least {min} characters.',
                'es_ES' => 'Por favor selecciona al menos {min} caracteres.',
                'de_DE' => 'Bitte wählen Sie mindestens {min} Zeichen aus.',
                'fr_FR' => 'Veuillez sélectionner au moins {min} caractères.',
                'hi_IN' => 'कृपया कम से कम {min} अक्षर चुनें।',
                'zh_CN' => '请至少选择 {min} 个字符。'
            ),
            'text_too_long' => array(
                'en_US' => 'Please select no more than {max} characters.',
                'en_GB' => 'Please select no more than {max} characters.',
                'es_ES' => 'Por favor selecciona no más de {max} caracteres.',
                'de_DE' => 'Bitte wählen Sie nicht mehr als {max} Zeichen aus.',
                'fr_FR' => 'Veuillez sélectionner au maximum {max} caractères.',
                'hi_IN' => 'कृपया {max} से अधिक अक्षर न चुनें।',
                'zh_CN' => '请选择不超过 {max} 个字符。'
            )
        );
        
        if (!isset($messages[$type][$language])) {
            $language = 'en_GB'; // Fallback
        }
        
        $message = $messages[$type][$language];
        
        // Replace placeholders
        foreach ($context as $key => $value) {
            $message = str_replace('{' . $key . '}', $value, $message);
        }
        
        return $message;
    }
}