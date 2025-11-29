<?php
/**
 * Tooltip Template Partial
 * Reusable tooltip structure to eliminate duplication in JavaScript
 * 
 * This template provides the base HTML structure that JavaScript can clone
 * instead of creating the entire tooltip structure programmatically
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Hidden tooltip template for JavaScript cloning -->
<div id="explainer-tooltip-template" class="explainer-tooltip-template" style="display: none !important;">
    
    <!-- Base tooltip structure -->
    <div class="explainer-tooltip" role="tooltip" aria-live="polite" aria-atomic="true">
        
        <!-- Tooltip header -->
        <div class="explainer-tooltip-header">
            <span class="explainer-tooltip-title">
                <!-- Title will be populated by JavaScript -->
            </span>
            <button class="btn-base btn-icon explainer-tooltip-close" 
                    aria-label="<?php esc_attr_e('Close explanation', 'ai-explainer'); ?>" 
                    type="button" 
                    tabindex="0">
                <span aria-hidden="true">×</span>
            </button>
        </div>
        
        <!-- Tooltip content area -->
        <div class="explainer-tooltip-content">
            <!-- Content will be populated by JavaScript -->
        </div>
        
        <!-- Tooltip footer (optional) -->
        <div class="explainer-tooltip-footer" style="display: none;">
            <div class="explainer-disclaimer">
                <?php esc_html_e('AI-generated content may not always be accurate', 'ai-explainer'); ?>
            </div>
            <div class="explainer-provider">
                <!-- Provider text will be populated by JavaScript -->
            </div>
        </div>
        
    </div>
    
    <!-- Loading state template -->
    <div class="explainer-tooltip loading" role="tooltip" aria-live="polite" aria-atomic="true">
        <div class="explainer-tooltip-header">
            <span class="explainer-tooltip-title">
                <?php esc_html_e('Loading', 'ai-explainer'); ?>...
            </span>
        </div>
        <div class="explainer-tooltip-content">
            <div class="explainer-loading">
                <div class="explainer-spinner" aria-hidden="true"></div>
                <span><?php esc_html_e('Loading explanation', 'ai-explainer'); ?>...</span>
            </div>
        </div>
    </div>
    
    <!-- Error state template -->
    <div class="explainer-tooltip error" role="tooltip" aria-live="assertive" aria-atomic="true">
        <div class="explainer-tooltip-header">
            <span class="explainer-tooltip-title">
                <?php esc_html_e('Error', 'ai-explainer'); ?>
            </span>
            <button class="btn-base btn-icon explainer-tooltip-close" 
                    aria-label="<?php esc_attr_e('Close error message', 'ai-explainer'); ?>" 
                    type="button" 
                    tabindex="0">
                <span aria-hidden="true">×</span>
            </button>
        </div>
        <div class="explainer-tooltip-content">
            <!-- Error message will be populated by JavaScript -->
        </div>
    </div>
    
</div>

<!-- Screen reader announcement template -->
<div id="explainer-sr-announcement-template" class="explainer-sr-only" 
     aria-live="assertive" aria-atomic="true" style="display: none !important;">
    <!-- Announcement text will be populated by JavaScript -->
</div>