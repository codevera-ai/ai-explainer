<?php
/**
 * AI Terms Management Modal Template
 * Complete modal structure for managing AI-generated terms
 * 
 * This template provides the modal HTML structure that integrates
 * with the existing WordPress media modal patterns
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Hidden modal template for JavaScript initialization -->
<div id="ai-terms-modal-template" class="ai-terms-modal-template" style="display: none !important;">
    
    <!-- Modal backdrop -->
    <div class="explainer-modal-backdrop">
        
        <!-- Modal container -->
        <div class="explainer-modal-container" role="dialog" aria-modal="true" aria-labelledby="ai-terms-modal-title">
            
            <!-- Modal header -->
            <div class="explainer-modal-header">
                <h2 id="ai-terms-modal-title" class="explainer-modal-title">
                    <?php esc_html_e('Manage AI terms', 'ai-explainer'); ?>
                </h2>
                <button class="btn-base btn-icon explainer-modal-close" 
                        aria-label="<?php esc_attr_e('Close modal', 'ai-explainer'); ?>" 
                        type="button">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            
            <!-- Modal toolbar -->
            <div class="explainer-modal-toolbar">
                
                <!-- Search and filters -->
                <div class="explainer-toolbar-left">
                    <div class="explainer-search-container">
                        <input type="search" 
                               id="ai-terms-search"
                               class="explainer-search-input"
                               placeholder="<?php esc_attr_e('Search terms...', 'ai-explainer'); ?>"
                               aria-label="<?php esc_attr_e('Search AI terms', 'ai-explainer'); ?>">
                        <button type="button" class="btn-base btn-ghost explainer-search-clear" 
                                aria-label="<?php esc_attr_e('Clear search', 'ai-explainer'); ?>"
                                style="display: none;">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    
                    <div class="explainer-filter-container">
                        <select id="ai-terms-status-filter" 
                                class="explainer-filter-select"
                                aria-label="<?php esc_attr_e('Filter by status', 'ai-explainer'); ?>">
                            <option value=""><?php esc_html_e('All statuses', 'ai-explainer'); ?></option>
                            <option value="enabled"><?php esc_html_e('Enabled', 'ai-explainer'); ?></option>
                            <option value="disabled"><?php esc_html_e('Disabled', 'ai-explainer'); ?></option>
                        </select>
                    </div>
                </div>
                
                <!-- Create new term -->
                <div class="explainer-toolbar-right">
                    <div class="explainer-create-actions">
                        <button type="button" class="btn-base btn-primary explainer-create-term">
                            <?php esc_html_e('Create new term', 'ai-explainer'); ?>
                        </button>
                    </div>
                </div>
                
            </div>
            
            <!-- Modal content area -->
            <div class="explainer-modal-content">
                
                <!-- Terms list container -->
                <div class="explainer-terms-container">
                    
                    <!-- Terms header row -->
                    <div class="explainer-terms-header">
                        <div class="explainer-header-term">
                            <?php esc_html_e('Term', 'ai-explainer'); ?>
                        </div>
                        <div class="explainer-header-status">
                            <?php esc_html_e('Status', 'ai-explainer'); ?>
                        </div>
                        <div class="explainer-header-usage">
                            <?php esc_html_e('Usage count', 'ai-explainer'); ?>
                        </div>
                        <div class="explainer-header-actions">
                            <?php esc_html_e('Actions', 'ai-explainer'); ?>
                        </div>
                    </div>
                    
                    <!-- Terms list -->
                    <div class="explainer-terms-list" 
                         role="region" 
                         aria-label="<?php esc_attr_e('AI terms list', 'ai-explainer'); ?>">
                        <!-- Terms will be populated by JavaScript -->
                    </div>
                    
                    <!-- Empty state -->
                    <div class="explainer-terms-empty hidden">
                        <div class="explainer-empty-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <h3 class="explainer-empty-title">
                            <?php esc_html_e('No terms found', 'ai-explainer'); ?>
                        </h3>
                        <p class="explainer-empty-message">
                            <?php esc_html_e('Start using the AI explainer feature to generate terms automatically.', 'ai-explainer'); ?>
                        </p>
                    </div>
                    
                    <!-- Loading state -->
                    <div class="explainer-terms-loading hidden">
                        <div class="explainer-loading-spinner">
                            <svg class="explainer-spinner" width="24" height="24" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="32" stroke-dashoffset="32">
                                    <animate attributeName="stroke-dasharray" dur="2s" values="0 32;16 16;0 32;0 32" repeatCount="indefinite"/>
                                    <animate attributeName="stroke-dashoffset" dur="2s" values="0;-16;-32;-32" repeatCount="indefinite"/>
                                </circle>
                            </svg>
                        </div>
                        <span><?php esc_html_e('Loading terms...', 'ai-explainer'); ?></span>
                    </div>
                    
                </div>
                
                <!-- Create new term form -->
                <div class="explainer-create-term-form hidden">
                    <div class="explainer-create-form-overlay"></div>
                    <div class="explainer-create-form-container">
                        <div class="explainer-create-form-header">
                            <h3 class="explainer-create-title">
                                <?php esc_html_e('Create new term', 'ai-explainer'); ?>
                            </h3>
                            <button type="button" class="btn-base btn-icon explainer-create-form-close" 
                                    aria-label="<?php esc_attr_e('Close create form', 'ai-explainer'); ?>">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        
                        <div class="explainer-create-form-content">
                            <div class="explainer-create-field">
                                <label for="explainer-new-term-text" class="explainer-field-label">
                                <?php esc_html_e('Term text', 'ai-explainer'); ?>
                            </label>
                            <input type="text" 
                                   id="explainer-new-term-text"
                                   class="explainer-text-input"
                                   placeholder="<?php esc_attr_e('Enter the term...', 'ai-explainer'); ?>"
                                   aria-describedby="explainer-term-text-help">
                            <p id="explainer-term-text-help" class="explainer-field-help">
                                <?php esc_html_e('Enter the exact text that should trigger an explanation.', 'ai-explainer'); ?>
                            </p>
                            <p class="explainer-field-warning">
                                <span class="dashicons dashicons-warning"></span>
                                <strong><?php esc_html_e('Important: The term must match exactly as it appears in your post content, including capitalisation and punctuation, otherwise the explanation feature will not work.', 'ai-explainer'); ?></strong>
                            </p>
                        </div>
                        
                        <div class="explainer-create-field">
                            <div class="explainer-field-actions">
                                <button type="button" class="btn-base btn-secondary explainer-test-term">
                                    <?php esc_html_e('Test if term exists in post', 'ai-explainer'); ?>
                                </button>
                                <div class="explainer-test-result hidden">
                                    <span class="explainer-test-found">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php esc_html_e('Term found in post content', 'ai-explainer'); ?>
                                    </span>
                                    <span class="explainer-test-not-found">
                                        <span class="dashicons dashicons-dismiss"></span>
                                        <?php esc_html_e('Term not found in post content', 'ai-explainer'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="explainer-create-explanations">
                            <h4 class="explainer-explanations-title">
                                <?php esc_html_e('Explanations by reading level', 'ai-explainer'); ?>
                            </h4>
                            
                            <?php
                            $reading_levels = ExplainerPlugin_Config::get_reading_level_labels();
                            foreach ($reading_levels as $level_key => $level_label) :
                            ?>
                            <div class="explainer-create-level-block" data-level="<?php echo esc_attr($level_key); ?>">
                                <label for="explainer-create-<?php echo esc_attr($level_key); ?>" class="explainer-level-label">
                                    <?php echo esc_html($level_label); ?>
                                    <?php if ($level_key === 'standard'): ?>
                                        <span class="explainer-required-indicator" title="<?php esc_attr_e('Required', 'ai-explainer'); ?>">*</span>
                                    <?php endif; ?>
                                </label>
                                <textarea id="explainer-create-<?php echo esc_attr($level_key); ?>"
                                          class="explainer-explanation-textarea<?php echo $level_key === 'standard' ? ' required' : ''; ?>"
                                          placeholder="<?php
                                          /* translators: %s: reading level label */
                                          echo esc_attr(sprintf(__('Explanation for %s level...', 'ai-explainer'), strtolower($level_label))); ?>"
                                          rows="3"
                                          <?php echo $level_key === 'standard' ? 'required' : ''; ?>></textarea>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="explainer-create-actions">
                            <button type="button" class="btn-base btn-primary explainer-save-new-term">
                                <?php esc_html_e('Save new term', 'ai-explainer'); ?>
                            </button>
                            <button type="button" class="btn-base btn-secondary explainer-cancel-create">
                                <?php esc_html_e('Cancel', 'ai-explainer'); ?>
                            </button>
                        </div>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Modal footer -->
            <div class="explainer-modal-footer">
                
                <!-- Pagination controls -->
                <div class="explainer-pagination-container">
                    <div class="explainer-pagination-info">
                        <span class="explainer-showing-text">
                            <?php esc_html_e('Showing', 'ai-explainer'); ?>
                            <span class="explainer-showing-start">1</span>
                            <?php esc_html_e('to', 'ai-explainer'); ?>
                            <span class="explainer-showing-end">10</span>
                            <?php esc_html_e('of', 'ai-explainer'); ?>
                            <span class="explainer-total-terms">0</span>
                            <?php esc_html_e('terms', 'ai-explainer'); ?>
                        </span>
                    </div>
                    
                    <div class="explainer-pagination-controls">
                        <button type="button" 
                                class="btn-base btn-secondary explainer-page-prev"
                                aria-label="<?php esc_attr_e('Previous page', 'ai-explainer'); ?>"
                                disabled>
                            <span aria-hidden="true">‹</span>
                            <span class="explainer-sr-only"><?php esc_html_e('Previous', 'ai-explainer'); ?></span>
                        </button>
                        
                        <div class="explainer-page-numbers">
                            <!-- Page numbers will be populated by JavaScript -->
                        </div>
                        
                        <button type="button" 
                                class="btn-base btn-secondary explainer-page-next"
                                aria-label="<?php esc_attr_e('Next page', 'ai-explainer'); ?>"
                                disabled>
                            <span aria-hidden="true">›</span>
                            <span class="explainer-sr-only"><?php esc_html_e('Next', 'ai-explainer'); ?></span>
                        </button>
                    </div>
                </div>
                
                <!-- Footer actions -->
                <div class="explainer-footer-actions">
                    <button type="button" class="btn-base btn-secondary explainer-modal-cancel">
                        <?php esc_html_e('Close', 'ai-explainer'); ?>
                    </button>
                </div>
                
            </div>
            
        </div>
        
    </div>
    
    <!-- Term row template -->
    <div class="explainer-term-row-template" style="display: none;">
        <div class="explainer-term-row" data-term-id="">
            <div class="explainer-term-text">
                <span class="explainer-term-value"></span>
            </div>
            <div class="explainer-term-status">
                <label class="explainer-toggle-switch">
                    <input type="checkbox" 
                           class="explainer-toggle-input explainer-term-toggle"
                           aria-label="<?php esc_attr_e('Toggle term status', 'ai-explainer'); ?>">
                    <span class="explainer-toggle-slider"></span>
                </label>
                <span class="explainer-status-text">
                    <?php esc_html_e('Enabled', 'ai-explainer'); ?>
                </span>
            </div>
            <div class="explainer-term-usage">
                <span class="explainer-usage-count">0</span>
            </div>
            <div class="explainer-term-actions">
                <button type="button" 
                        class="btn-base btn-ghost btn-sm explainer-term-edit"
                        aria-label="<?php esc_attr_e('Edit term', 'ai-explainer'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20h9"/>
                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                    </svg>
                    <span class="explainer-sr-only"><?php esc_html_e('Edit', 'ai-explainer'); ?></span>
                </button>
                <button type="button" 
                        class="btn-base btn-ghost btn-sm explainer-term-delete"
                        aria-label="<?php esc_attr_e('Delete term', 'ai-explainer'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3,6 5,6 21,6"/>
                        <path d="m19,6v14a2,2 0 0,1-2,2H7a2,2 0 0,1-2-2V6m3,0V4a2,2 0 0,1 2-2h4a2,2 0 0,1 2,2v2"/>
                        <line x1="10" y1="11" x2="10" y2="17"/>
                    </svg>
                    <span class="explainer-sr-only"><?php esc_html_e('Delete', 'ai-explainer'); ?></span>
                </button>
            </div>
        </div>
        
        <!-- Expandable edit panel (initially hidden) -->
        <div class="explainer-term-edit-panel" style="display: none;">
            <div class="explainer-edit-panel-container">
                
                <!-- Reading level blocks -->
                <div class="explainer-reading-levels-container">
                    <?php
                    $reading_levels = ExplainerPlugin_Config::get_reading_level_labels();
                    foreach ($reading_levels as $level_key => $level_label) :
                    ?>
                    <div class="explainer-reading-level-block" data-level="<?php echo esc_attr($level_key); ?>">
                        <div class="explainer-level-header">
                            <span class="explainer-level-label">
                                <?php echo esc_html($level_label); ?>
                            </span>
                            <span class="explainer-level-toggle dashicons dashicons-arrow-right-alt2"></span>
                        </div>
                        <div class="explainer-level-content" style="display: none;">
                            <textarea class="explainer-explanation-text"
                                      placeholder="<?php
                                      /* translators: %s: reading level label */
                                      echo esc_attr(sprintf(__('Explanation for %s level...', 'ai-explainer'), strtolower($level_label))); ?>"
                                      rows="3"></textarea>
                            <div class="explainer-level-actions">
                                <button type="button" class="btn-base btn-secondary btn-sm explainer-save-explanation">
                                    <?php esc_html_e('Save', 'ai-explainer'); ?>
                                </button>
                                <button type="button" class="btn-base btn-ghost btn-sm explainer-clear-explanation">
                                    <?php esc_html_e('Clear', 'ai-explainer'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Panel actions -->
                <div class="explainer-edit-panel-actions">
                    <button type="button" class="btn-base btn-primary explainer-save-term">
                        <?php esc_html_e('Save changes', 'ai-explainer'); ?>
                    </button>
                    <button type="button" class="btn-base btn-secondary explainer-cancel-edit">
                        <?php esc_html_e('Cancel', 'ai-explainer'); ?>
                    </button>
                </div>
                
            </div>
        </div>
    </div>
    
</div>

<!-- Screen reader announcements for modal actions -->
<div id="ai-terms-modal-sr-announcements" class="explainer-sr-only" 
     aria-live="assertive" aria-atomic="true" style="display: none !important;">
    <!-- Announcement text will be populated by JavaScript -->
</div>