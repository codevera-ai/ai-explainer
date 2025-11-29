/**
 * Reusable Pagination JavaScript Library for WP AI Explainer
 * 
 * Provides AJAX-based pagination functionality for admin tables
 * with loading states, error handling, and WordPress integration.
 * 
 * @package WPAIExplainer
 * @since 1.3.0
 */

(function($) {
    'use strict';
    
    // Ensure ExplainerPlugin namespace exists
    if (typeof window.ExplainerPlugin === 'undefined') {
        window.ExplainerPlugin = {};
    }
    
    /**
     * Pagination controller class
     */
    class PaginationController {
        
        /**
         * Constructor
         * 
         * @param {Object} options Configuration options
         */
        constructor(options) {
            this.options = $.extend({
                container: '',                // Container selector for the table
                paginationContainer: '',      // Pagination controls container
                loadingSelector: '',          // Loading indicator selector
                tableBodySelector: '',        // Table body selector
                ajaxAction: '',              // AJAX action name
                nonce: '',                   // WordPress nonce
                perPage: 20,                 // Items per page
                currentPage: 1,              // Current page
                orderBy: '',                 // Default sort column
                order: 'desc',               // Default sort order
                searchSelector: '',          // Search input selector
                additionalData: {},          // Additional data to send with requests
                onBeforeLoad: null,          // Callback before loading
                onAfterLoad: null,           // Callback after loading
                onError: null,               // Callback on error
                debounceDelay: 300,          // Debounce delay for search
            }, options);
            
            this.isLoading = false;
            this.searchTimeout = null;
            
            this.init();
        }
        
        /**
         * Initialize the pagination controller
         */
        init() {
            this.bindEvents();
            this.loadPage(this.options.currentPage);
        }
        
        /**
         * Bind event handlers
         */
        bindEvents() {
            const self = this;
            
            // Pagination click events
            $(document).on('click', this.options.paginationContainer + ' button[data-page]', function(e) {
                e.preventDefault();
                if (!$(this).hasClass('disabled') && !self.isLoading) {
                    const page = parseInt($(this).data('page'));
                    self.loadPage(page);
                }
            });
            
            // Page input change
            $(document).on('change', this.options.paginationContainer + ' .current-page', function(e) {
                e.preventDefault();
                if (!self.isLoading) {
                    const page = parseInt($(this).val());
                    if (page > 0) {
                        self.loadPage(page);
                    }
                }
            });
            
            // Page input enter key
            $(document).on('keypress', this.options.paginationContainer + ' .current-page', function(e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    if (!self.isLoading) {
                        const page = parseInt($(this).val());
                        if (page > 0) {
                            self.loadPage(page);
                        }
                    }
                }
            });
            
            // Sort links
            if (this.options.container) {
                $(document).on('click', this.options.container + ' .sort-link', function(e) {
                    e.preventDefault();
                    if (!self.isLoading) {
                        const sortBy = $(this).data('sort');
                        self.handleSort(sortBy);
                    }
                });
            }
            
            // Search functionality
            if (this.options.searchSelector) {
                $(document).on('input', this.options.searchSelector, function(e) {
                    const searchTerm = $(this).val();
                    self.handleSearch(searchTerm);
                });
            }
        }
        
        /**
         * Load a specific page
         * 
         * @param {number} page Page number to load
         * @param {string} sortBy Sort column
         * @param {string} sortOrder Sort order
         */
        loadPage(page = 1, sortBy = null, sortOrder = null) {
            if (this.isLoading) {
                return;
            }
            
            // Update current state
            this.options.currentPage = page;
            if (sortBy !== null) {
                this.options.orderBy = sortBy;
            }
            if (sortOrder !== null) {
                this.options.order = sortOrder;
            }
            
            this.setLoadingState(true);
            
            // Prepare AJAX data
            const ajaxData = {
                action: this.options.ajaxAction,
                nonce: this.options.nonce,
                page: this.options.currentPage,
                per_page: this.options.perPage,
                orderby: this.options.orderBy,
                order: this.options.order,
                ...this.options.additionalData
            };
            
            // Debug logging
            console.log('Pagination AJAX request:', ajaxData);
            
            // Add search term if available
            if (this.options.searchSelector) {
                const searchTerm = $(this.options.searchSelector).val();
                if (searchTerm) {
                    ajaxData.search = searchTerm;
                }
            }
            
            // Call before load callback
            if (typeof this.options.onBeforeLoad === 'function') {
                this.options.onBeforeLoad.call(this, ajaxData);
            }
            
            // Make AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: ajaxData,
                dataType: 'json',
                success: (response) => {
                    this.handleSuccess(response);
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    this.handleError(textStatus, errorThrown);
                },
                complete: () => {
                    this.setLoadingState(false);
                }
            });
        }
        
        /**
         * Handle successful AJAX response
         * 
         * @param {Object} response AJAX response
         */
        handleSuccess(response) {
            if (response.success) {
                const data = response.data;
                
                // Update table content
                this.updateTableContent(data.items);
                
                // Update pagination controls
                this.updatePaginationControls(data.pagination, data.pagination_html);
                
                // Update sort indicators
                this.updateSortIndicators();
                
                // Call after load callback
                if (typeof this.options.onAfterLoad === 'function') {
                    this.options.onAfterLoad.call(this, data);
                }
            } else {
                this.handleError('server_error', response.data || 'Unknown server error');
            }
        }
        
        /**
         * Handle AJAX error
         * 
         * @param {string} textStatus Error status
         * @param {string} errorThrown Error message
         */
        handleError(textStatus, errorThrown) {
            console.error('Pagination AJAX error:', textStatus, errorThrown);
            
            // Show error message in table
            if (this.options.tableBodySelector) {
                const errorMessage = 'Failed to load data. Please try again.';
                const colspan = $(this.options.tableBodySelector).closest('table').find('thead th').length || 4;
                $(this.options.tableBodySelector).html(
                    `<tr><td colspan="${colspan}" style="text-align: center; padding: 20px; color: #d63638;">${errorMessage}</td></tr>`
                );
            }
            
            // Call error callback
            if (typeof this.options.onError === 'function') {
                this.options.onError.call(this, textStatus, errorThrown);
            }
            
            // Show notification if available
            if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                window.ExplainerPlugin.Notifications.error('Failed to load data. Please try again.');
            }
        }
        
        /**
         * Update table content with new data
         * 
         * @param {Array} items Data items to display
         */
        updateTableContent(items) {
            // This method should be overridden by specific implementations
            // or passed as a callback in options
            console.warn('updateTableContent method should be implemented for specific table types');
        }
        
        /**
         * Update pagination controls
         * 
         * @param {Object} paginationData Pagination metadata
         * @param {string} paginationHtml Pagination HTML
         */
        updatePaginationControls(paginationData, paginationHtml) {
            if (this.options.paginationContainer && paginationHtml) {
                $(this.options.paginationContainer).html(paginationHtml);
                
                // Show/hide pagination based on total pages
                if (paginationData.total_pages <= 1) {
                    $(this.options.paginationContainer).hide();
                } else {
                    $(this.options.paginationContainer).show();
                }
            }
        }
        
        /**
         * Update sort indicators
         */
        updateSortIndicators() {
            if (!this.options.container) return;
            
            // Remove all existing sort classes
            $(this.options.container + ' .sort-link').removeClass('sorted asc desc');
            
            // Add current sort class
            if (this.options.orderBy) {
                const sortLink = $(this.options.container + ' .sort-link[data-sort="' + this.options.orderBy + '"]');
                sortLink.addClass('sorted ' + this.options.order);
            }
        }
        
        /**
         * Handle sort column click
         * 
         * @param {string} sortBy Column to sort by
         */
        handleSort(sortBy) {
            let newOrder = 'desc';
            
            // If clicking the same column, toggle order
            if (this.options.orderBy === sortBy) {
                newOrder = this.options.order === 'desc' ? 'asc' : 'desc';
            }
            
            this.loadPage(1, sortBy, newOrder);
        }
        
        /**
         * Handle search input with debouncing
         * 
         * @param {string} searchTerm Search term
         */
        handleSearch(searchTerm) {
            // Clear existing timeout
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }
            
            // Set new timeout
            this.searchTimeout = setTimeout(() => {
                this.loadPage(1); // Reset to page 1 for new search
            }, this.options.debounceDelay);
        }
        
        /**
         * Set loading state
         * 
         * @param {boolean} loading Whether loading or not
         */
        setLoadingState(loading) {
            this.isLoading = loading;
            
            // Show/hide loading indicator
            if (this.options.loadingSelector) {
                if (loading) {
                    $(this.options.loadingSelector).show();
                } else {
                    $(this.options.loadingSelector).hide();
                }
            }
            
            // Disable/enable pagination controls
            if (this.options.paginationContainer) {
                $(this.options.paginationContainer + ' button').prop('disabled', loading);
                $(this.options.paginationContainer + ' input').prop('disabled', loading);
            }
            
            // Add loading class to container
            if (this.options.container) {
                if (loading) {
                    $(this.options.container).addClass('pagination-loading');
                } else {
                    $(this.options.container).removeClass('pagination-loading');
                }
            }
        }
        
        /**
         * Refresh current page
         */
        refresh() {
            this.loadPage(this.options.currentPage);
        }
        
        /**
         * Go to specific page
         * 
         * @param {number} page Page number
         */
        goToPage(page) {
            this.loadPage(page);
        }
        
        /**
         * Set additional data for AJAX requests
         * 
         * @param {Object} data Additional data
         */
        setAdditionalData(data) {
            this.options.additionalData = {...this.options.additionalData, ...data};
        }
        
        /**
         * Get current page
         * 
         * @return {number} Current page number
         */
        getCurrentPage() {
            return this.options.currentPage;
        }
        
        /**
         * Destroy the pagination controller
         */
        destroy() {
            // Remove event handlers
            $(document).off('click', this.options.paginationContainer + ' button[data-page]');
            $(document).off('change', this.options.paginationContainer + ' .current-page');
            $(document).off('keypress', this.options.paginationContainer + ' .current-page');
            
            if (this.options.container) {
                $(document).off('click', this.options.container + ' .sort-link');
            }
            
            if (this.options.searchSelector) {
                $(document).off('input', this.options.searchSelector);
            }
            
            // Clear timeout
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }
        }
    }
    
    // Expose PaginationController to ExplainerPlugin namespace
    window.ExplainerPlugin.PaginationController = PaginationController;
    
    /**
     * Utility function to create a pagination controller
     * 
     * @param {Object} options Configuration options
     * @return {PaginationController} Pagination controller instance
     */
    window.ExplainerPlugin.createPagination = function(options) {
        return new PaginationController(options);
    };
    
})(jQuery);