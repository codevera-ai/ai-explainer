<?php
/**
 * Popular Selections Settings Section
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle popular selections settings tab rendering
 */
class ExplainerPlugin_Popular_Selections {
    
    
    /**
     * Render the popular selections settings tab content
     */
    public function render() {
        // Check if API keys are configured - use provider-specific option names
        $has_api_keys = !empty(get_option('explainer_openai_api_key')) ||
                       !empty(get_option('explainer_claude_api_key')) ||
                       !empty(get_option('explainer_openrouter_api_key')) ||
                       !empty(get_option('explainer_gemini_api_key'));
        ?>
        <div class="tab-content" id="popular-tab" style="display: none;">
            <h2><?php echo esc_html__('Explanations Dashboard', 'ai-explainer'); ?></h2>
            <p><?php echo esc_html__('View and manage AI explanations for text selections on your site. Edit explanations across different reading levels and create blog posts from trending content.', 'ai-explainer'); ?></p>
            
            <?php if (!$has_api_keys): ?>
            <script>
            jQuery(document).ready(function($) {
                function showApiConfigNotice() {
                    if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                        window.ExplainerPlugin.Notifications.warning('<?php echo esc_js(__('To create blog posts from selections, please configure your AI provider API key in the AI Provider tab.', 'ai-explainer')); ?>', {
                            title: '<?php echo esc_js(__('API Configuration Required', 'ai-explainer')); ?>',
                            duration: 8000,
                            actions: [
                                {
                                    text: '<?php echo esc_js(__('AI Provider', 'ai-explainer')); ?>',
                                    primary: true,
                                    callback: function() {
                                        $('.nav-tab[href="#ai-provider"]').trigger('click');
                                    }
                                }
                            ]
                        });
                    } else {
                        setTimeout(showApiConfigNotice, 100);
                    }
                }
                
                // Show notice when popular tab is activated
                if ($('#popular-tab').is(':visible') || window.location.hash === '#popular') {
                    showApiConfigNotice();
                }
                
                // Also show when popular tab is clicked
                $('.nav-tab[href="#popular"]').on('click', function() {
                    setTimeout(showApiConfigNotice, 100);
                });
            });
            </script>
            <?php endif; ?>
            
            <?php $this->render_javascript($has_api_keys); ?>
            <?php $this->render_styles(); ?>
            
            <div class="popular-selections-dashboard">
                <!-- Search and filters -->
                <div class="dashboard-controls" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="text" id="selections-search" placeholder="<?php echo esc_attr__('Search selections...', 'ai-explainer'); ?>" style="min-width: 200px;" />

                    <button type="button" id="clear-search" class="btn-base btn-secondary btn-sm" title="<?php echo esc_attr__('Clear search', 'ai-explainer'); ?>">
                        <?php echo esc_html__('Clear', 'ai-explainer'); ?>
                    </button>

                    <button type="button" id="refresh-selections" class="btn-base btn-secondary">
                        <?php echo esc_html__('Refresh', 'ai-explainer'); ?>
                    </button>

                    <button type="button" id="delete-all-selections" class="btn-base btn-danger" style="background-color: #dc2626; color: white; border-color: #dc2626;">
                        <?php echo esc_html__('Delete all explanations', 'ai-explainer'); ?>
                    </button>

                    <div id="selections-summary" style="margin-left: auto; font-style: italic; color: #666;">
                        <?php echo esc_html__('Loading...', 'ai-explainer'); ?>
                    </div>
                </div>
                
                <!-- Loading indicator -->
                <div id="selections-loading" style="display: none; text-align: center; padding: 40px;">
                    <div class="spinner is-active" style="float: none; margin: 0 auto;"></div>
                    <p><?php echo esc_html__('Loading popular selections...', 'ai-explainer'); ?></p>
                </div>
                
                <!-- Results table -->
                <div id="selections-table-container">
                    <!-- Table will be populated via AJAX -->
                </div>
                
                <!-- Pagination -->
                <div id="selections-pagination" style="margin-top: 20px; display: none;">
                    <div class="tablenav">
                        <div class="alignleft">
                            <div class="pagination-links">
                                <button type="button" id="prev-page" class="btn-base btn-secondary" disabled>&laquo; <?php echo esc_html__('Previous', 'ai-explainer'); ?></button>
                                <span class="page-numbers current" id="current-page-info">1</span>
                                <button type="button" id="next-page" class="btn-base btn-secondary"><?php echo esc_html__('Next', 'ai-explainer'); ?> &raquo;</button>
                            </div>
                        </div>
                        <div class="alignright">
                            <span id="pagination-info" style="line-height: 32px; color: #666;"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Created Posts List -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd;">
                    <h3><?php echo esc_html__('Created Blog Posts', 'ai-explainer'); ?></h3>
                    <p><?php echo esc_html__('Posts created using the "Create Post" button, with real-time status updates.', 'ai-explainer'); ?></p>
                    
                    <!-- Created Posts Controls -->
                    <div class="created-posts-controls" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <input type="text" id="created-posts-search" placeholder="<?php echo esc_attr__('Search posts...', 'ai-explainer'); ?>" style="min-width: 200px;" />
                        
                        <button type="button" id="clear-created-posts-search" class="btn-base btn-secondary btn-sm" title="<?php echo esc_attr__('Clear search', 'ai-explainer'); ?>">
                            <?php echo esc_html__('Clear', 'ai-explainer'); ?>
                        </button>
                        
                        <button type="button" id="refresh-created-posts" class="btn-base btn-secondary">
                            <?php echo esc_html__('Refresh', 'ai-explainer'); ?>
                        </button>
                        
                        <div id="created-posts-summary" style="margin-left: auto; font-style: italic; color: #666;">
                            <?php echo esc_html__('Loading...', 'ai-explainer'); ?>
                        </div>
                    </div>
                    
                    <!-- Created Posts Loading -->
                    <div id="created-posts-loading" style="display: none; text-align: center; padding: 40px;">
                        <div class="spinner is-active" style="float: none; margin: 0 auto;"></div>
                        <p><?php echo esc_html__('Loading created posts...', 'ai-explainer'); ?></p>
                    </div>
                    
                    <!-- Created Posts Table -->
                    <div id="created-posts-table-container">
                        <!-- Table will be populated via AJAX -->
                    </div>
                    
                    <!-- Created Posts Pagination -->
                    <div id="created-posts-pagination" style="margin-top: 20px; display: none;">
                        <div class="tablenav">
                            <div class="alignleft">
                                <div class="pagination-links">
                                    <button type="button" id="created-posts-prev-page" class="btn-base btn-secondary" disabled>&laquo; <?php echo esc_html__('Previous', 'ai-explainer'); ?></button>
                                    <span class="page-numbers current" id="created-posts-current-page-info">1</span>
                                    <button type="button" id="created-posts-next-page" class="btn-base btn-secondary"><?php echo esc_html__('Next', 'ai-explainer'); ?> &raquo;</button>
                                </div>
                            </div>
                            <div class="alignright">
                                <span id="created-posts-pagination-info" style="line-height: 32px; color: #666;"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Job Queue Panel -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd;">
                </div>
                
            </div>
        </div>
        <?php
    }
    
    /**
     * Render JavaScript for popular selections
     */
    private function render_javascript($has_api_keys) {
        ?>
        <script type="text/javascript">
            // Pass API key status and version to JavaScript
            window.explainerHasApiKeys = <?php echo $has_api_keys ? 'true' : 'false'; ?>;
            window._explainerFeatureLevel = 2;

            // Handle tab switching from notice
            jQuery(document).ready(function($) {
                $('.nav-tab-link').on('click', function(e) {
                    e.preventDefault();
                    var targetTab = $(this).attr('href');

                    // Switch to the target tab
                    $('.nav-tab').removeClass('nav-tab-active');
                    $('.nav-tab[href="' + targetTab + '"]').addClass('nav-tab-active');

                    // Show target tab content and hide others
                    $('.tab-content').hide();
                    $(targetTab + '-tab').show();
                });
            });
        </script>
        <?php
    }
    
    /**
     * Render CSS styles for popular selections
     */
    private function render_styles() {
        ?>
        <style>
            /* API key status container styling */
            .api-key-status {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
                margin-bottom: 10px;
            }
            
            .api-key-status .dashicons {
                flex-shrink: 0;
            }
            
            .api-key-status strong {
                flex-shrink: 0;
            }
            
            .api-key-status code {
                flex-shrink: 0;
                font-family: monospace;
                background: #f1f1f1;
                padding: 2px 6px;
                border-radius: 3px;
            }
            
            .api-key-status .delete-api-key-btn {
                flex-shrink: 0;
                white-space: nowrap;
                margin-left: auto;
            }
            
            /* API key input and button container styling */
            .api-key-input-container {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                margin-bottom: 10px;
            }
            
            .api-key-input-container .regular-text {
                flex: 1;
                min-width: 300px;
            }
            
            .api-key-input-container .button {
                white-space: nowrap;
                flex-shrink: 0;
            }
            
            /* Blog creator button styling */
            .create-blog-post-btn {
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 11px;
                line-height: 1.3;
            }
            
            .create-blog-post-btn:disabled {
                background: #f6f7f7 !important;
                border-color: #ddd !important;
                color: #a0a5aa !important;
                cursor: not-allowed !important;
                opacity: 0.6 !important;
            }
            
            .create-blog-post-btn:disabled:hover {
                background: #f6f7f7 !important;
                border-color: #ddd !important;
                color: #a0a5aa !important;
            }
            
            /* API key notice styling */
            .nav-tab-link {
                color: #8b5cf6;
                text-decoration: none;
            }
            
            .nav-tab-link:hover {
                color: #005177;
                text-decoration: underline;
            }
            
            /* Created posts specific styling */
            #created-posts-table .post-action-link {
                color: #0073aa;
                text-decoration: none;
                font-size: 12px;
            }
            
            #created-posts-table .post-action-link:hover {
                text-decoration: underline;
            }
            
            #created-posts-table td {
                vertical-align: top;
                padding: 12px 8px;
            }
            
            #created-posts-table th {
                font-weight: 600;
                padding: 12px 8px;
            }
            
            .created-posts-controls input[type="text"] {
                padding: 6px 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            
            .created-posts-controls input[type="text"]:focus {
                border-color: #8b5cf6;
                box-shadow: 0 0 0 1px #8b5cf6;
                outline: none;
            }
        </style>
        <?php
    }
    
}