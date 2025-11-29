<?php
/**
 * Post Scan Settings Section
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post Scan section renderer
 * 
 * Handles the rendering of the Post Scan admin interface including:
 * - Post search functionality 
 * - Processed posts table
 * - Terms viewing modal
 * 
 * @since 1.2.0
 */
class ExplainerPlugin_Post_Scan {
    
    
    /**
     * Render the post scan section
     */
    public function render() {
        ?>
        <div class="tab-content" id="post-scan-tab" style="display: none;">
            <div class="explainer-section">
                <h2><?php echo esc_html__('Post Scan Management', 'ai-explainer'); ?></h2>
                <p class="description">
                    <?php echo esc_html__('Search for posts to initiate AI term scans and view processing status.', 'ai-explainer'); ?>
                </p>
                
                <!-- Post Search Interface -->
                <div class="post-search-section">
                    <h3><?php echo esc_html__('Search Posts', 'ai-explainer'); ?></h3>
                    
                    <div class="post-search-form">
                        <input type="text" 
                               id="post-search-input" 
                               placeholder="<?php echo esc_attr__('Start typing to search for posts...', 'ai-explainer'); ?>"
                               class="regular-text">
                        <div id="post-search-loading" class="spinner" style="display: none;"></div>
                    </div>
                    
                    <div id="post-search-results" class="post-search-results" style="display: none;">
                        <!-- Search results will be populated here -->
                    </div>
                </div>
                
                <!-- Processed Posts Table -->
                <div class="processed-posts-section">
                    <h3><?php echo esc_html__('Processed Posts', 'ai-explainer'); ?></h3>
                    <p class="description">
                        <?php echo esc_html__('Posts that have been processed for AI term explanations.', 'ai-explainer'); ?>
                    </p>
                    
                    <div id="processed-posts-loading" class="spinner" style="display: none;"></div>
                    <div id="processed-posts-container">
                        <table class="wp-list-table widefat fixed striped" id="processed-posts-table">
                            <thead>
                                <tr>
                                    <th class="manage-column column-title column-primary">
                                        <a href="#" class="sort-link" data-sort="title">
                                            <?php echo esc_html__('Post Title', 'ai-explainer'); ?>
                                            <span class="sorting-indicators">
                                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                                                <span class="sorting-indicator desc" aria-hidden="true"></span>
                                            </span>
                                        </a>
                                    </th>
                                    <th class="manage-column">
                                        <a href="#" class="sort-link" data-sort="processed_date">
                                            <?php echo esc_html__('Date Processed', 'ai-explainer'); ?>
                                            <span class="sorting-indicators">
                                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                                                <span class="sorting-indicator desc" aria-hidden="true"></span>
                                            </span>
                                        </a>
                                    </th>
                                    <th class="manage-column">
                                        <a href="#" class="sort-link" data-sort="term_count">
                                            <?php echo esc_html__('Terms Found', 'ai-explainer'); ?>
                                            <span class="sorting-indicators">
                                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                                                <span class="sorting-indicator desc" aria-hidden="true"></span>
                                            </span>
                                        </a>
                                    </th>
                                    <th class="manage-column column-actions">
                                        <?php echo esc_html__('Actions', 'ai-explainer'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="processed-posts-tbody">
                                <!-- Processed posts will be populated here -->
                            </tbody>
                        </table>
                        
                        <div class="tablenav bottom">
                            <div class="alignleft actions bulkactions">
                                <div class="tablenav-pages" id="processed-posts-pagination">
                                    <!-- Pagination will be populated here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- View Terms Modal -->
        <div id="view-terms-modal" class="explainer-modal" style="display: none;">
            <div class="explainer-modal-content">
                <div class="explainer-modal-header">
                    <h3><?php echo esc_html__('Terms Found', 'ai-explainer'); ?></h3>
                    <button type="button" class="btn-base btn-secondary btn-sm explainer-modal-close" aria-label="<?php echo esc_attr__('Close', 'ai-explainer'); ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="explainer-modal-body">
                    <div id="terms-loading" class="spinner" style="display: none;"></div>
                    <div id="terms-content">
                        <!-- Terms will be populated here -->
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
}