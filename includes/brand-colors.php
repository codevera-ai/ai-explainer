<?php
/**
 * Brand Colors Configuration
 * Single source of truth for all plugin brand colors
 * 
 * This file defines the brand color palette used throughout the plugin.
 * All CSS, JavaScript, and PHP files should reference these colors
 * to ensure consistency and easy brand updates.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get brand colors configuration
 * 
 * @return array Brand colors array
 */
function explainer_get_brand_colors() {
    return array(
        // Primary brand colours (purple/pink gradient system)
        'primary'   => '#0F172A',  // Primary Purple
        'secondary' => '#ec4899',  // Primary Pink  
        'vibrant'   => '#ec4899',  // Primary Pink (alias for secondary)
        'success'   => '#10b981',  // Modern Green
        'accent'    => '#64748b',  // Slate Grey
        
        // Supporting colours
        'bg_slate'     => '#f8f9fa',  // Clean Background
        'border'       => '#e9ecef',  // Subtle Border
        'light_purple' => '#f3f4f6',  // Soft Purple Background
        'light_pink'   => '#fdf2f8',  // Soft Pink Background
        
        // Semantic colours
        'error'   => '#d63638',  // WordPress Error Red
        'warning' => '#dba617',  // WordPress Warning Yellow
        'info'    => '#2271b1',  // WordPress Info Blue
        
        // Button states
        'button_enabled'  => '#0F172A',  // Primary Purple
        'button_disabled' => '#94a3b8',  // Muted Slate
        'button_text'     => '#ffffff',  // White text
        
        // Tooltip colours
        'tooltip_bg'   => '#333333',  // Dark background
        'tooltip_text' => '#ffffff',  // White text
    );
}

/**
 * Get CSS custom properties string
 * Generates CSS variables from brand colors
 * 
 * @return string CSS custom properties
 */
function explainer_get_brand_css_variables() {
    $colors = explainer_get_brand_colors();
    $css = ":root {\n";
    
    foreach ($colors as $name => $color) {
        $css_name = str_replace('_', '-', $name);
        $css .= "    --plugin-{$css_name}: {$color};\n";
    }
    
    $css .= "}\n";
    return $css;
}

/**
 * Get brand colors for JavaScript
 * Returns colors in format suitable for wp_localize_script()
 * 
 * @return array Colors formatted for JavaScript
 */
function explainer_get_brand_colors_for_js() {
    $colors = explainer_get_brand_colors();
    
    // Convert underscores to camelCase for JavaScript
    $js_colors = array();
    foreach ($colors as $name => $color) {
        $js_name = lcfirst(str_replace('_', '', ucwords($name, '_')));
        $js_colors[$js_name] = $color;
    }
    
    return $js_colors;
}

/**
 * Get a specific brand color
 * 
 * @param string $color_name Color name (e.g., 'primary', 'secondary')
 * @param string $default Default color if not found
 * @return string Color hex value
 */
function explainer_get_brand_color($color_name, $default = '#0F172A') {
    $colors = explainer_get_brand_colors();
    return isset($colors[$color_name]) ? $colors[$color_name] : $default;
}