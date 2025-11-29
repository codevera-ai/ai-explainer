/**
 * Admin Core Module
 * Main orchestrator for all admin functionality
 */

(function($) {
    'use strict';

    // Initialize global namespace
    window.WPAIExplainer = window.WPAIExplainer || {};
    window.WPAIExplainer.Admin = window.WPAIExplainer.Admin || {};
    
    window.WPAIExplainer.Admin.Core = {
        
        // Real-time system variables
        realtimeAdapter: null,
        isRealtimeEnabled: false,
        subscribedTopics: new Set(),
        fallbackMode: false,
        
        /**
         * Initialize all admin modules
         */
        init: function() {
            console.log('WP AI Explainer Admin Core initializing...');
            
            // Initialize real-time system first
            this.initializeRealtimeSystem();
            
            // Initialize real-time status updates
            this.initializeRealtimeStatusUpdates();
            
            // Initialize modules in order of dependency
            this.initializeProviderConfiguration()
                .then(() => {
                    // Provider config loaded, now initialize other modules
                    this.initializeApiTesting();
                    this.initializeFormValidation();
                    this.initializeDebugLogging();
                    this.initializeJobMonitoring();
                    this.initializeColorPickers();
                    this.initializeOtherFeatures();
                    
                    console.log('WP AI Explainer Admin Core initialized successfully');
                })
                .catch((error) => {
                    console.error('Failed to initialize admin core:', error);
                    // Continue with initialization even if provider config fails
                    this.initializeApiTesting();
                    this.initializeFormValidation();
                    this.initializeDebugLogging();
                    this.initializeJobMonitoring();
                    this.initializeColorPickers();
                    this.initializeOtherFeatures();
                });
        },

        /**
         * Initialize provider configuration module
         */
        initializeProviderConfiguration: function() {
            if (typeof window.WPAIExplainer.Admin.ProviderConfig !== 'undefined') {
                return window.WPAIExplainer.Admin.ProviderConfig.init();
            }
            return $.Deferred().resolve();
        },

        /**
         * Initialize API testing module
         */
        initializeApiTesting: function() {
            if (typeof window.WPAIExplainer.Admin.ApiTesting !== 'undefined') {
                window.WPAIExplainer.Admin.ApiTesting.init();
                console.log('API Testing module initialized');
            }
        },

        /**
         * Initialize form validation module
         */
        initializeFormValidation: function() {
            if (typeof window.WPAIExplainer.Admin.FormValidation !== 'undefined') {
                window.WPAIExplainer.Admin.FormValidation.init();
                console.log('Form Validation module initialized');
            }
        },

        /**
         * Initialize debug logging module
         */
        initializeDebugLogging: function() {
            if (typeof window.WPAIExplainer.Admin.DebugLogging !== 'undefined') {
                window.WPAIExplainer.Admin.DebugLogging.init();
                console.log('Debug Logging module initialized');
            }
        },

        /**
         * Initialize job monitoring module
         */
        initializeJobMonitoring: function() {
            window.ExplainerLogger?.debug('Core: Initializing Job Monitoring module', {
                moduleExists: typeof window.WPAIExplainer.Admin.JobMonitoring !== 'undefined',
                availableModules: Object.keys(window.WPAIExplainer?.Admin || {})
            }, 'Core');
            
            if (typeof window.WPAIExplainer.Admin.JobMonitoring !== 'undefined') {
                window.ExplainerLogger?.debug('Core: Job Monitoring module found, calling init()', {}, 'Core');
                window.WPAIExplainer.Admin.JobMonitoring.init();
                console.log('Job Monitoring module initialized');
                window.ExplainerLogger?.info('Core: Job Monitoring module initialized successfully', {}, 'Core');
            } else {
                window.ExplainerLogger?.warning('Core: Job Monitoring module not found', {
                    availableModules: Object.keys(window.WPAIExplainer?.Admin || {}),
                    windowObject: window.WPAIExplainer
                }, 'Core');
            }
        },

        /**
         * Initialise colour pickers
         */
        initializeColorPickers: function() {
            // WordPress colour picker
            if ($.fn.wpColorPicker) {
                $('.color-picker, .color-field').wpColorPicker({
                    change: function(event, ui) {
                        // Trigger change event for validation
                        $(this).trigger('change');
                    }
                });
                console.log('Colour pickers initialised');
            }
            
            // Initialise appearance preview functionality
            this.initialiseAppearancePreview();
        },

        /**
         * Initialise appearance preview functionality
         */
        initialiseAppearancePreview: function() {
            // Skip if appearance elements don't exist (free version)
            if (!$('#explainer_tooltip_bg_color').length) {
                return;
            }

            // Update preview when colour inputs change
            const colourInputs = [
                '#explainer_tooltip_bg_color',
                '#explainer_tooltip_text_color',
                '#explainer_button_enabled_color',
                '#explainer_button_disabled_color',
                '#explainer_button_text_color',
                '#explainer_tooltip_footer_color'
            ];

            // Function to update CSS custom properties
            const updatePreviewStyles = () => {
                const root = document.documentElement;

                // Tooltip colours
                const bgColour = $('#explainer_tooltip_bg_color').val() || '#1e293b';
                const textColour = $('#explainer_tooltip_text_color').val() || '#ffffff';
                const footerColour = $('#explainer_tooltip_footer_color').val() || '#ffffff';

                // Button colours
                const enabledColour = $('#explainer_button_enabled_color').val() || '#dc2626';
                const disabledColour = $('#explainer_button_disabled_color').val() || '#374151';
                const buttonTextColour = $('#explainer_button_text_color').val() || '#ffffff';
                
                // Set CSS custom properties
                root.style.setProperty('--explainer-tooltip-bg-color', bgColour);
                root.style.setProperty('--explainer-tooltip-text-color', textColour);
                root.style.setProperty('--explainer-tooltip-footer-color', footerColour);
                
                // Update button preview styles directly
                $('#preview-button-enabled').css({
                    'background-color': enabledColour,
                    'color': buttonTextColour
                });
                
                $('#preview-button-disabled').css({
                    'background-color': disabledColour, 
                    'color': buttonTextColour
                });
                
                // Update disclaimer, provider, and reading level slider visibility
                const showDisclaimer = $('#explainer_show_disclaimer').is(':checked');
                const showProvider = $('#explainer_show_provider').is(':checked');
                const showReadingLevelSlider = $('#explainer_show_reading_level_slider').is(':checked');

                $('#preview-disclaimer').toggle(showDisclaimer);
                $('#preview-provider').toggle(showProvider);
                // Force explicit display style for reading level slider in admin preview only
                $('.tooltip-preview .explainer-reading-level-slider, .tooltip-preview .explainer-reading-level-mobile').each(function() {
                    if (showReadingLevelSlider) {
                        $(this).css('display', 'block');
                    } else {
                        // Use !important to override any conflicting CSS
                        this.style.setProperty('display', 'none', 'important');
                    }
                });
            };
            
            // Bind to colour input changes
            colourInputs.forEach(selector => {
                $(document).on('change input', selector, updatePreviewStyles);
                
                // Also bind to WordPress colour picker change events
                $(document).on('wpcolorpicker:change', selector, updatePreviewStyles);
            });
            
            // Bind to checkbox changes
            $(document).on('change', '#explainer_show_disclaimer, #explainer_show_provider, #explainer_show_reading_level_slider', updatePreviewStyles);
            
            // Force tooltip preview visibility (override frontend CSS)
            const forceTooltipVisibility = () => {
                const tooltip = document.querySelector('.explainer-tooltip-preview');
                if (tooltip) {
                    tooltip.style.setProperty('visibility', 'visible', 'important');
                    tooltip.style.setProperty('opacity', '1', 'important');
                    tooltip.style.setProperty('position', 'relative', 'important');
                    tooltip.style.setProperty('transform', 'none', 'important');
                }
            };
            
            // Initial update and visibility fix
            setTimeout(() => {
                updatePreviewStyles();
                forceTooltipVisibility();
            }, 100);
            
            console.log('Appearance preview initialised');
        },

        /**
         * Initialize other legacy features
         */
        initializeOtherFeatures: function() {
            this.initializePopularSelections();
            this.initializeCronManagement();
            this.initializeResetFunctionality();
            this.initializePromptManagement();
            this.initializeBlockedWords();
            this.initializeReenableButton();
        },

        /**
         * Initialize popular selections dashboard
         */
        initializePopularSelections: function() {
            if ($('#popular-tab').length === 0) {
                return;
            }

            let autoRefreshTimer = null;

            // Load data immediately if popular tab is already active
            if ($('.nav-tab[href="#popular"]').hasClass('nav-tab-active')) {
                // Hide static content immediately and show loading
                $('#selections-table-container table tbody').html('<tr><td colspan="5" style="text-align: center; padding: 20px;"><div class="spinner is-active" style="float: none; margin: 0 auto;"></div> Loading popular selections...</td></tr>');
                loadPopularSelections(true);
                if ((window._explainerFeatureLevel || 1) > 1) {
                    loadCreatedPosts(true);
                }
                startAutoRefresh();
            }

            // Initialize when the popular selections tab is clicked
            $(document).on('click', '.nav-tab[href="#popular"]', function() {
                // Show loading immediately when tab is clicked
                $('#selections-table-container table tbody').html('<tr><td colspan="5" style="text-align: center; padding: 20px;"><div class="spinner is-active" style="float: none; margin: 0 auto;"></div> Loading popular selections...</td></tr>');
                loadPopularSelections(true);
                if ((window._explainerFeatureLevel || 1) > 1) {
                    loadCreatedPosts(true);
                }
                startAutoRefresh();
            });

            // Stop auto-refresh when switching away from popular selections tab
            $(document).on('click', '.nav-tab:not([href="#popular"])', function() {
                stopAutoRefresh();
            });

            // Handle refresh button click
            $(document).on('click', '#refresh-selections', function(e) {
                e.preventDefault();
                loadPopularSelections(true);
            });

            // Handle search functionality
            $(document).on('input', '#selections-search', function() {
                clearTimeout(window.searchTimeout);
                window.searchTimeout = setTimeout(function() {
                    loadPopularSelections(true);
                }, 500); // Debounce search by 500ms
            });

            // Handle search form submission (if Enter is pressed)
            $(document).on('keypress', '#selections-search', function(e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    loadPopularSelections(true);
                }
            });

            // Handle clear search button click
            $(document).on('click', '#clear-search', function(e) {
                e.preventDefault();
                $('#selections-search').val('');
                loadPopularSelections(true);
            });

            // Handle delete all selections button click
            $(document).on('click', '#delete-all-selections', function(e) {
                e.preventDefault();

                const $button = $(this);
                const originalText = $button.text();

                // Show inline confirmation message
                const confirmationHtml = '<div id="delete-all-confirmation" style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin-bottom: 15px; border-radius: 4px;">' +
                    '<p style="margin: 0 0 10px 0; font-weight: bold;">Are you sure you want to delete all explanations?</p>' +
                    '<p style="margin: 0 0 15px 0; color: #666;">This action cannot be undone. All explanation data will be permanently removed.</p>' +
                    '<button type="button" id="confirm-delete-all" class="btn-base btn-danger" style="background-color: #dc2626; color: white; border-color: #dc2626; margin-right: 10px;">Yes, delete all</button>' +
                    '<button type="button" id="cancel-delete-all" class="btn-base btn-secondary">Cancel</button>' +
                    '</div>';

                // Insert confirmation above the table
                if ($('#delete-all-confirmation').length === 0) {
                    $('.dashboard-controls').after(confirmationHtml);
                }
            });

            // Handle delete all confirmation
            $(document).on('click', '#confirm-delete-all', function(e) {
                e.preventDefault();

                const $button = $(this);
                $button.prop('disabled', true).text('Deleting...');

                const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
                const nonce = window.wpAiExplainer?.nonce;

                if (!ajaxUrl || !nonce) {
                    $('#delete-all-confirmation').html('<p style="color: red;">AJAX configuration not found.</p>');
                    return;
                }

                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'explainer_delete_all_selections',
                        nonce: nonce
                    },
                    success: function(response) {
                        $('#delete-all-confirmation').remove();

                        if (response.success) {
                            // Show success message
                            const successHtml = '<div id="delete-success-message" style="background: #d4edda; border: 1px solid #28a745; padding: 15px; margin-bottom: 15px; border-radius: 4px; color: #155724;">' +
                                '<p style="margin: 0;"><strong>Success!</strong> All explanations have been deleted (' + (response.data.deleted_count || 0) + ' records removed).</p>' +
                                '</div>';
                            $('.dashboard-controls').after(successHtml);

                            // Remove success message after 5 seconds
                            setTimeout(function() {
                                $('#delete-success-message').fadeOut(function() {
                                    $(this).remove();
                                });
                            }, 5000);

                            // Reload the selections table
                            loadPopularSelections(true);
                        } else {
                            // Show error message
                            const errorHtml = '<div id="delete-error-message" style="background: #f8d7da; border: 1px solid #dc2626; padding: 15px; margin-bottom: 15px; border-radius: 4px; color: #721c24;">' +
                                '<p style="margin: 0;"><strong>Error!</strong> ' + (response.data || 'Failed to delete explanations.') + '</p>' +
                                '</div>';
                            $('.dashboard-controls').after(errorHtml);

                            // Remove error message after 5 seconds
                            setTimeout(function() {
                                $('#delete-error-message').fadeOut(function() {
                                    $(this).remove();
                                });
                            }, 5000);
                        }
                    },
                    error: function() {
                        $('#delete-all-confirmation').remove();

                        const errorHtml = '<div id="delete-error-message" style="background: #f8d7da; border: 1px solid #dc2626; padding: 15px; margin-bottom: 15px; border-radius: 4px; color: #721c24;">' +
                            '<p style="margin: 0;"><strong>Error!</strong> Network error occurred while deleting explanations.</p>' +
                            '</div>';
                        $('.dashboard-controls').after(errorHtml);

                        // Remove error message after 5 seconds
                        setTimeout(function() {
                            $('#delete-error-message').fadeOut(function() {
                                $(this).remove();
                            });
                        }, 5000);
                    }
                });
            });

            // Handle delete all cancellation
            $(document).on('click', '#cancel-delete-all', function(e) {
                e.preventDefault();
                $('#delete-all-confirmation').fadeOut(function() {
                    $(this).remove();
                });
            });

            // Created posts handlers only in full feature mode
            if ((window._explainerFeatureLevel || 1) > 1) {
                $(document).on('click', '#refresh-created-posts', function(e) {
                    e.preventDefault();
                    loadCreatedPosts(true);
                });

                // Handle created posts search functionality
                $(document).on('input', '#created-posts-search', function() {
                    clearTimeout(window.createdPostsSearchTimeout);
                    window.createdPostsSearchTimeout = setTimeout(function() {
                        loadCreatedPosts(true);
                    }, 500); // Debounce search by 500ms
                });

                // Handle created posts clear search button click
                $(document).on('click', '#clear-created-posts-search', function(e) {
                    e.preventDefault();
                    $('#created-posts-search').val('');
                    loadCreatedPosts(true);
                });
            }

            // Handle pagination button clicks
            $(document).on('click', '#selections-pagination button[data-page]', function(e) {
                e.preventDefault();
                const page = parseInt($(this).data('page'));
                if (page > 0) {
                    loadPopularSelections(true, page);
                }
            });

            // Handle pagination page input change
            $(document).on('change', '#selections-pagination .current-page', function(e) {
                e.preventDefault();
                const page = parseInt($(this).val());
                if (page > 0) {
                    loadPopularSelections(true, page);
                }
            });

            // Handle pagination page input enter key
            $(document).on('keypress', '#selections-pagination .current-page', function(e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    const page = parseInt($(this).val());
                    if (page > 0) {
                        loadPopularSelections(true, page);
                    }
                }
            });

            function startAutoRefresh() {
                stopAutoRefresh(); // Clear any existing timer
                
                // Use real-time updates if available, otherwise fallback to polling
                if (window.WPAIExplainer.Admin.Core.isRealtimeEnabled) {
                    window.WPAIExplainer.Admin.Core.subscribeToPopularSelectionsEvents();
                    window.WPAIExplainer.Admin.Core.subscribeToCreatedPostsEvents();
                } else {
                    // Fallback: Auto-refresh every 30 seconds while on the popular selections tab
                    autoRefreshTimer = setInterval(function() {
                        // Only refresh if the popular tab is still active
                        if ($('.nav-tab[href="#popular"]').hasClass('nav-tab-active')) {
                            // Check if any selections are expanded (have arrow-down-alt2 class)
                            var hasExpandedSelections = $('.dashicons-arrow-down-alt2').length > 0;
                            
                            if (!hasExpandedSelections) {
                                loadPopularSelections(false); // Don't show loading indicator for auto-refresh
                            }
                            // Skip auto-refresh if selections are expanded to preserve user state

                            // Also auto-refresh created posts (only in full feature mode)
                            if ((window._explainerFeatureLevel || 1) > 1 && !$('#created-posts-loading').is(':visible')) {
                                loadCreatedPosts(false);
                            }
                        } else {
                            stopAutoRefresh();
                        }
                    }, 30000); // 30 seconds
                }
            }

            function stopAutoRefresh() {
                if (autoRefreshTimer) {
                    clearInterval(autoRefreshTimer);
                    autoRefreshTimer = null;
                }
                
                // Unsubscribe from real-time events
                if (window.WPAIExplainer.Admin.Core.isRealtimeEnabled) {
                    window.WPAIExplainer.Admin.Core.unsubscribeFromPopularSelectionsEvents();
                    window.WPAIExplainer.Admin.Core.unsubscribeFromCreatedPostsEvents();
                }
            }

            function loadPopularSelections(showLoading = false, page = 1) {
                if (showLoading) {
                    $('#selections-loading').show();
                }
                
                const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
                const nonce = window.wpAiExplainer?.nonce;

                if (!ajaxUrl || !nonce) {
                    $('#selections-loading').hide();
                    $('#selections-table-container').html('<div style="color: red; padding: 10px;">AJAX configuration not found.</div>');
                    return;
                }

                // Get search term from input field
                const searchTerm = $('#selections-search').val() || '';
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'explainer_get_popular_selections',
                        nonce: nonce,
                        page: page,
                        per_page: 20,
                        filter: 'all',
                        search: searchTerm
                    },
                    success: function(response) {
                        $('#selections-loading').hide();
                        if (response.success && response.data.items) {
                            renderPopularSelections(response.data.items, searchTerm);
                            updateSummary(response.data.pagination, searchTerm);
                            
                            // Update pagination HTML
                            if (response.data.pagination_html) {
                                $('#selections-pagination').html(response.data.pagination_html).show();
                            } else {
                                $('#selections-pagination').hide();
                            }
                        } else {
                            const message = searchTerm ? 
                                `No selections found matching "${searchTerm}".` : 
                                'No popular selections available.';
                            $('#selections-table-container').html(`<div style="color: #666; padding: 20px; text-align: center;">${message}</div>`);
                        }
                    },
                    error: function() {
                        $('#selections-loading').hide();
                        $('#selections-table-container').html('<div style="color: red; padding: 10px;">Error loading popular selections.</div>');
                    }
                });
            }

            function renderPopularSelections(selections, searchTerm = '') {
                if (!selections || selections.length === 0) {
                    const message = searchTerm ? 
                        `No selections found matching "${searchTerm}".` : 
                        'No popular selections found.';
                    $('#selections-table-container').html(`<div style="color: #666; padding: 20px; text-align: center;">${message}</div>`);
                    return;
                }

                // Check feature level (2 = full features, 1 = limited)
                const _fl = window._explainerFeatureLevel || 1;

                let html = '<table class="wp-list-table widefat fixed striped" id="selections-table">';
                html += '<thead>';
                html += '<tr>';
                if (_fl > 1) {
                    // Pro version - equal distribution
                    html += '<th>' + 'Selected Text' + '</th>';
                    html += '<th>' + 'Count' + '</th>';
                    html += '<th>' + 'First Seen' + '</th>';
                    html += '<th>' + 'Last Seen' + '</th>';
                    html += '<th>' + 'Actions' + '</th>';
                } else {
                    // Free version - spread to fill space
                    html += '<th style="width: 60%;">' + 'Selected Text' + '</th>';
                    html += '<th style="width: 10%;">' + 'Count' + '</th>';
                    html += '<th style="width: 17.5%;">' + 'First Seen' + '</th>';
                    html += '<th style="width: 12.5%;">' + 'Last Seen' + '</th>';
                }
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';

                selections.forEach(function(selection, index) {
                    // Check if this is grouped data (with reading_level_data) or legacy data
                    const isGrouped = !!selection.reading_level_data;
                    const uniqueId = isGrouped ? ('hash-' + index) : selection.id;
                    
                    // Main selection row
                    html += '<tr class="selection-row" data-selection-id="' + uniqueId + '">';
                    html += '<td>';
                    html += '<div style="display: flex; align-items: center; gap: 8px;">';
                    html += '<button type="button" class="btn-base btn-secondary btn-sm show-explanation-btn" data-selection-id="' + uniqueId + '" title="Show reading levels and explanations">';
                    html += '<span class="dashicons dashicons-arrow-right-alt2"></span>';
                    html += '</button>';
                    html += '<span class="clickable-text" data-selection-id="' + uniqueId + '">';
                    html += '<strong>' + escapeHtml(selection.text_preview || selection.selected_text.substring(0, 100)) + '</strong>';
                    html += '</span>';
                    
                    // Show reading level count badge for grouped data
                    if (isGrouped && selection.reading_level_count > 1) {
                        html += '<span class="reading-level-badge" style="background: #8b5cf6; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 8px;">';
                        html += selection.reading_level_count + ' levels';
                        html += '</span>';
                    }
                    
                    html += '</div>';
                    html += '</td>';
                    html += '<td><span class="selection-count">' + selection.count + '</span></td>';
                    html += '<td>' + selection.first_seen + '</td>';
                    html += '<td>' + selection.last_seen + '</td>';

                    // Actions column only in full feature mode
                    if (_fl > 1) {
                        html += '<td class="actions">';
                        // Properly escape the JSON for HTML attribute
                        var escapedSelection = JSON.stringify(selection).replace(/'/g, '&#39;').replace(/"/g, '&quot;');
                        html += '<button type="button" class="btn-base btn-primary btn-sm create-blog-post-btn" data-hash="' + selection.text_hash + '" data-selection=\'' + escapedSelection + '\'>';
                        html += 'Create Blog Post';
                        html += '</button> ';

                        // Delete button for all selections (both grouped and non-grouped)
                        const deleteId = isGrouped ? selection.id : selection.id;
                        html += '<button type="button" class="btn-base btn-destructive btn-sm delete-selection-btn" data-id="' + deleteId + '" title="Delete Selection (removes all reading levels)">';
                        html += 'Delete';
                        html += '</button>';

                        html += '</td>';
                    }
                    html += '</tr>';

                    // Hidden explanation row
                    html += '<tr class="explanation-row" id="explanation-row-' + uniqueId + '" style="display: none;">';
                    html += '<td colspan="' + (_fl > 1 ? '5' : '4') + '">';
                    html += '<div class="explanation-content">';
                    
                    if (isGrouped) {
                        // Render reading level breakdown for grouped data
                        html += renderReadingLevelsBreakdown(selection, _fl);
                    } else {
                        html += '<div class="explanation-loading" style="display: none;">';
                        html += '<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>';
                        html += 'Loading explanation...';
                        html += '</div>';
                        html += '<div class="explanation-details" style="display: none;"></div>';
                    }
                    
                    html += '</div>';
                    html += '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                $('.popular-selections-table').remove(); // Remove any existing table
                $('#selections-table-container').html(html);
                
                // Initialize click handlers for the new table
                initializeSelectionClickHandlers();
            }
            
            /**
             * Render the reading levels breakdown for grouped selections
             */
            function renderReadingLevelsBreakdown(selection, _fl) {
                let html = '<div class="reading-levels-breakdown" style="padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">';
                
                // Header showing source URLs if available
                if (selection.source_urls && selection.source_urls.length > 0) {
                    html += '<div style="margin-bottom: 15px;">';
                    html += '<strong>Source URL' + (selection.source_urls.length > 1 ? 's' : '') + ':</strong>';
                    html += '<div style="margin-top: 5px;">';
                    
                    selection.source_urls.forEach(function(url, index) {
                        if (url && url.trim()) {
                            html += '<div style="margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">';
                            html += '<span style="color: #666; font-size: 12px; min-width: 20px;">' + (index + 1) + '.</span>';
                            html += '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener" style="word-break: break-all; line-height: 1.4;">' + escapeHtml(url) + '</a>';
                            html += '</div>';
                        }
                    });
                    
                    if (selection.source_urls.length > 1) {
                        html += '<div style="margin-top: 8px; font-size: 12px; color: #666; font-style: italic;">';
                        html += 'This text appears on ' + selection.source_urls.length + ' different pages';
                        html += '</div>';
                    }
                    
                    html += '</div>';
                    html += '</div>';
                }
                
                // Reading levels section
                html += '<div class="reading-levels-section">';
                html += '<h4 style="margin: 0 0 10px 0; font-size: 14px; color: #333;">Reading Levels (' + selection.reading_level_count + ' available)</h4>';
                
                // Debug: Log available reading level data (remove after debugging)
                // console.log('🔍 DEBUGGING: Available reading level data:', selection.reading_level_data);
                // console.log('🔍 DEBUGGING: Reading levels array:', selection.reading_levels);
                
                // Define reading level order and labels (expert to very simple, descending difficulty)
                const levelOrder = ['expert', 'detailed', 'standard', 'simple', 'child'];
                const levelLabels = {
                    'expert': 'Expert',
                    'detailed': 'Detailed',
                    'standard': 'Standard',
                    'simple': 'Simple',
                    'child': 'Very Simple'
                };
                
                // Sort available reading levels by difficulty (expert first, child last)
                const availableLevels = Object.keys(selection.reading_level_data);
                const sortedLevels = availableLevels.sort(function(a, b) {
                    const aIndex = levelOrder.indexOf(a);
                    const bIndex = levelOrder.indexOf(b);
                    // If level not in predefined order, put it at the end
                    const aPos = aIndex === -1 ? 999 : aIndex;
                    const bPos = bIndex === -1 ? 999 : bIndex;
                    return aPos - bPos;
                });
                
                sortedLevels.forEach(function(levelKey) {
                    const levelData = selection.reading_level_data[levelKey];
                    const levelId = 'level-' + levelData.id;
                    
                    html += '<div class="reading-level-item" style="border: 1px solid #ccc; border-radius: 4px; margin-bottom: 10px; background: white;">';
                    
                    // Level header (clickable)
                    html += '<div class="level-header" style="padding: 10px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: #f5f5f5;" ';
                    html += 'data-level-id="' + levelId + '">';
                    html += '<div>';
                    html += '<strong>' + (levelLabels[levelKey] || levelKey.charAt(0).toUpperCase() + levelKey.slice(1)) + '</strong>';
                    html += '<span style="margin-left: 10px; color: #666; font-size: 12px;">(' + levelData.selection_count + ' selections)</span>';
                    html += '</div>';
                    html += '<span class="level-toggle dashicons dashicons-arrow-right-alt2" style="color: #666;"></span>';
                    html += '</div>';
                    
                    // Level content (initially hidden)
                    html += '<div class="level-content" id="content-' + levelId + '" style="display: none; padding: 15px;">';
                    
                    // Debug: Log level data in detail (remove after debugging)
                    // console.log('🔍 DEBUGGING: Processing level ' + levelKey + ':', {
                    //     id: levelData.id,
                    //     explanation: levelData.ai_explanation,
                    //     explanation_raw: JSON.stringify(levelData.ai_explanation),
                    //     explanation_type: typeof levelData.ai_explanation,
                    //     explanation_length: levelData.ai_explanation ? levelData.ai_explanation.length : 0,
                    //     has_explanation: !!levelData.ai_explanation
                    // });
                    
                    // More robust explanation checking
                    function hasValidExplanation(explanation) {
                        if (!explanation) return false;
                        if (explanation === null || explanation === undefined) return false;
                        
                        // Convert to string and trim
                        const trimmed = String(explanation).trim();
                        if (trimmed === '' || trimmed === 'null' || trimmed === 'undefined') return false;
                        
                        // Check for common placeholder values
                        if (trimmed === '0' || trimmed === 'false' || trimmed === 'NaN') return false;
                        
                        return true;
                    }
                    
                    const hasExplanation = hasValidExplanation(levelData.ai_explanation);
                    
                    // console.log('🔍 DEBUGGING: hasExplanation result for ' + levelKey + ':', hasExplanation);
                    // console.log('🔍 DEBUGGING: Trimmed explanation:', levelData.ai_explanation ? String(levelData.ai_explanation).trim() : 'N/A');
                    
                    if (hasExplanation) {
                            html += '<div class="explanation-text-container">';

                            // Display mode (always show with edit button)
                            html += '<div class="explanation-display-mode" data-selection-id="' + levelData.id + '">';
                            html += '<div class="explanation-text" style="display: inline-block; width: calc(100% - 60px); margin-right: 10px; line-height: 1.4;">';
                            html += escapeHtml(levelData.ai_explanation);
                            html += '</div>';
                            html += '<button type="button" class="btn-base btn-ghost btn-sm edit-explanation-btn" data-selection-id="' + levelData.id + '" title="Edit explanation">';
                            html += '<span class="dashicons dashicons-edit" style="font-size: 16px; width: 16px; height: 16px;"></span>';
                            html += '</button>';
                            html += '</div>';

                            // Edit mode (always available)
                            html += '<div class="explanation-edit-mode" style="display: none;" data-selection-id="' + levelData.id + '">';
                            html += '<textarea class="explanation-edit-field" rows="3" style="width: 100%; margin-bottom: 8px; resize: vertical; min-height: 60px;">' + escapeHtml(levelData.ai_explanation) + '</textarea>';
                            html += '<div class="explanation-edit-actions" style="display: flex; gap: 8px;">';
                            html += '<button type="button" class="btn-base btn-primary btn-sm save-explanation-btn" data-selection-id="' + levelData.id + '">Save</button>';
                            html += '<button type="button" class="btn-base btn-secondary btn-sm cancel-edit-btn" data-selection-id="' + levelData.id + '">Cancel</button>';
                            html += '</div>';
                            html += '</div>';

                            html += '</div>';
                        } else {
                            // No explanation available
                            const levelLabel = levelLabels[levelKey] || levelKey.charAt(0).toUpperCase() + levelKey.slice(1);
                            html += '<div style="color: #666; font-style: italic;' + (_fl > 1 ? ' margin-bottom: 15px;' : '') + '">';
                            html += 'No AI explanation has been generated yet for the <strong>' + levelLabel + '</strong> reading level.';
                            html += '</div>';

                            // Generate button only in full feature mode
                            if (_fl > 1) {
                                html += '<div>';
                                html += '<button type="button" class="btn-base btn-primary btn-sm generate-explanation-btn" ';
                                html += 'data-selection-id="' + levelData.id + '" data-level="' + levelKey + '" data-text="' + escapeHtml(selection.selected_text.substring(0, 200)) + '">';
                                html += 'Generate ' + levelLabel + ' Explanation';
                                html += '</button>';
                                html += '</div>';
                            }
                        }

                        // Level actions only in full feature mode
                        if (_fl > 1) {
                            html += '<div class="level-actions" style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #eee; display: flex; gap: 8px;">';
                            html += '<button type="button" class="btn-base btn-destructive btn-sm delete-selection-btn" data-id="' + levelData.id + '" title="Delete this reading level">';
                            html += 'Delete Level';
                            html += '</button>';
                            html += '</div>';
                        }
                        
                    html += '</div>'; // level-content
                    html += '</div>'; // reading-level-item
                });
                
                html += '</div>'; // reading-levels-section
                html += '</div>'; // reading-levels-breakdown
                
                return html;
            }

            function updateSummary(pagination, searchTerm = '') {
                if (pagination) {
                    // Calculate start and end items from pagination data
                    const startItem = pagination.total_items === 0 ? 0 : (pagination.current_page - 1) * pagination.per_page + 1;
                    const endItem = Math.min(pagination.current_page * pagination.per_page, pagination.total_items);
                    
                    let summaryText = 'Showing ' + startItem + '-' + endItem + ' of ' + pagination.total_items + ' selections';
                    if (searchTerm) {
                        summaryText += ` matching "${searchTerm}"`;
                    }
                    $('#selections-summary').text(summaryText);
                }
            }

            /**
             * Initialize click handlers for selection expansion
             */
            function initializeSelectionClickHandlers() {
                // Handle main row expansion (show reading levels breakdown)
                $(document).off('click.explanations').on('click.explanations', '.show-explanation-btn, .clickable-text', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const selectionId = $(this).data('selection-id');
                    const explanationRow = $('#explanation-row-' + selectionId);
                    const arrowBtn = $('.show-explanation-btn[data-selection-id="' + selectionId + '"]');
                    const arrow = arrowBtn.find('.dashicons');
                    
                    if (explanationRow.is(':visible')) {
                        // Hide explanation
                        explanationRow.slideUp(200);
                        arrow.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
                        arrowBtn.attr('title', 'Show reading levels and explanations');
                    } else {
                        // Show explanation - for grouped data, it's already rendered, just show it
                        explanationRow.slideDown(200);
                        arrow.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
                        arrowBtn.attr('title', 'Hide reading levels and explanations');
                        
                        // If this is legacy data (starts with a number, not 'hash-'), load via AJAX
                        if (!selectionId.startsWith('hash-')) {
                            loadAndShowExplanation(selectionId);
                        }
                    }
                });
                
                // Handle individual reading level expansion
                $(document).off('click.levels').on('click.levels', '.level-header', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const levelId = $(this).data('level-id');
                    const contentDiv = $('#content-' + levelId);
                    const toggleIcon = $(this).find('.level-toggle');
                    
                    if (contentDiv.is(':visible')) {
                        // Hide level content
                        contentDiv.slideUp(200);
                        toggleIcon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
                    } else {
                        // Show level content
                        contentDiv.slideDown(200);
                        toggleIcon.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
                    }
                });
                
                // Initialize existing explanation editing handlers
                initializeExplanationEditHandlers();
                
                // Handle generate explanation button clicks
                $(document).off('click.generate').on('click.generate', '.generate-explanation-btn', function(e) {
                    e.preventDefault();
                    const button = $(this);
                    const selectionId = button.data('selection-id');
                    const level = button.data('level');
                    const text = button.data('text');
                    
                    generateExplanationForLevel(selectionId, level, text, button);
                });
            }
            
            /**
             * Load created posts via AJAX
             */
            function loadCreatedPosts(showLoading = false, page = 1) {
                if (showLoading) {
                    $('#created-posts-loading').show();
                }
                
                const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
                const nonce = window.wpAiExplainer?.nonce;

                if (!ajaxUrl || !nonce) {
                    $('#created-posts-loading').hide();
                    $('#created-posts-table-container').html('<div style="color: red; padding: 10px;">AJAX configuration not found.</div>');
                    return;
                }

                // Get search term from input field
                const searchTerm = $('#created-posts-search').val() || '';
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'explainer_get_created_posts',
                        nonce: nonce,
                        page: page,
                        per_page: 10,
                        search: searchTerm,
                        orderby: 'created_at',
                        order: 'desc'
                    },
                    success: function(response) {
                        $('#created-posts-loading').hide();
                        if (response.success && response.data.items) {
                            renderCreatedPosts(response.data.items, searchTerm);
                            updateCreatedPostsSummary(response.data.pagination, searchTerm);
                            
                            // Update pagination HTML
                            if (response.data.pagination_html) {
                                $('#created-posts-pagination').html(response.data.pagination_html).show();
                            } else {
                                $('#created-posts-pagination').hide();
                            }
                        } else {
                            const message = searchTerm ? 
                                `No created posts found matching "${searchTerm}".` : 
                                'No created posts available.';
                            $('#created-posts-table-container').html(`<div style="color: #666; padding: 20px; text-align: center;">${message}</div>`);
                            $('#created-posts-summary').text('No posts found');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#created-posts-loading').hide();
                        $('#created-posts-table-container').html('<div style="color: red; padding: 10px;">Error loading created posts.</div>');
                        $('#created-posts-summary').text('Error loading posts');
                    }
                });
            }
            
            // Expose loadCreatedPosts globally
            window.loadCreatedPosts = loadCreatedPosts;
            
            /**
             * Render created posts table
             */
            function renderCreatedPosts(posts, searchTerm = '') {
                if (!posts || posts.length === 0) {
                    const message = searchTerm ? 
                        `No posts found matching "${searchTerm}".` : 
                        'No created posts found.';
                    $('#created-posts-table-container').html(`<div style="color: #666; padding: 20px; text-align: center;">${message}</div>`);
                    return;
                }
                
                let html = '<table class="wp-list-table widefat fixed striped" id="created-posts-table">';
                html += '<thead>';
                html += '<tr>';
                html += '<th style="width: 30%;">Title</th>';
                html += '<th style="width: 20%;">Term/Explanation</th>';
                html += '<th style="width: 80px;">Thumbnail</th>';
                html += '<th style="width: 100px;">Status</th>';
                html += '<th style="width: 120px;">Date Created</th>';
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';
                
                posts.forEach(function(post) {
                    html += '<tr>';
                    
                    // Title column with view/edit links
                    html += '<td>';
                    if (post.post_id && post.title) {
                        html += '<strong><a href="' + post.view_link + '" target="_blank" title="View post">' + escapeHtml(post.title) + '</a></strong>';
                        html += '<div class="post-actions" style="margin-top: 4px;">';
                        if (post.edit_link) {
                            html += '<a href="' + post.edit_link + '" class="post-action-link" title="Edit post">Edit</a>';
                        }
                        if (post.view_link) {
                            html += ' | <a href="' + post.view_link + '" target="_blank" class="post-action-link" title="View post">View</a>';
                        }
                        html += '</div>';
                    } else {
                        html += '<em style="color: #666;">Post not created yet</em>';
                    }
                    html += '</td>';
                    
                    // Term and explanation
                    html += '<td>';
                    html += '<div style="font-size: 12px;">';
                    if (post.term) {
                        html += '<strong>Term:</strong> ' + escapeHtml(post.term) + '<br>';
                    }
                    if (post.explanation) {
                        html += '<strong>Explanation:</strong> ' + escapeHtml(post.explanation);
                    }
                    html += '</div>';
                    html += '</td>';
                    
                    // Thumbnail
                    html += '<td>';
                    if (post.thumbnail_url) {
                        html += '<img src="' + post.thumbnail_url + '" alt="Thumbnail" style="width: 50px; height: 50px; object-fit: cover; border-radius: 3px;">';
                    } else {
                        html += '<div style="width: 50px; height: 50px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 3px; color: #666; font-size: 12px;">No image</div>';
                    }
                    html += '</td>';
                    
                    // Status with colour coding
                    html += '<td>';
                    const statusStyles = {
                        pending: 'background: #f0f8ff; color: #0073aa; border: 1px solid #0073aa;',
                        processing: 'background: #fff3cd; color: #856404; border: 1px solid #ffc107;',
                        completed: 'background: #d4edda; color: #155724; border: 1px solid #28a745;',
                        failed: 'background: #f8d7da; color: #721c24; border: 1px solid #dc3545;',
                        paused: 'background: #e2e3e5; color: #383d41; border: 1px solid #6c757d;',
                        unknown: 'background: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6;'
                    };
                    
                    const status = post.status || 'unknown';
                    const style = statusStyles[status] || statusStyles.unknown;
                    
                    html += '<span style="padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; ' + style + '">';
                    html += status.charAt(0).toUpperCase() + status.slice(1);
                    html += '</span>';
                    
                    if (post.error_message && status === 'failed') {
                        html += '<div style="margin-top: 4px; font-size: 11px; color: #721c24;" title="' + escapeHtml(post.error_message) + '">Error: Click to see details</div>';
                    }
                    
                    html += '</td>';
                    
                    // Date created
                    html += '<td>';
                    if (post.created_at) {
                        const date = new Date(post.created_at);
                        html += '<div style="font-size: 12px;">';
                        html += date.toLocaleDateString() + '<br>';
                        html += '<span style="color: #666;">' + date.toLocaleTimeString() + '</span>';
                        html += '</div>';
                    } else {
                        html += '<em style="color: #666;">Unknown</em>';
                    }
                    html += '</td>';
                    
                    html += '</tr>';
                });
                
                html += '</tbody>';
                html += '</table>';
                
                $('#created-posts-table-container').html(html);
            }
            
            /**
             * Update created posts summary
             */
            function updateCreatedPostsSummary(pagination, searchTerm) {
                let summaryText = '';
                
                if (pagination.total_items > 0) {
                    const start = (pagination.current_page - 1) * pagination.per_page + 1;
                    const end = Math.min(start + pagination.per_page - 1, pagination.total_items);
                    
                    if (searchTerm) {
                        summaryText = `Showing ${start}-${end} of ${pagination.total_items} posts matching "${searchTerm}"`;
                    } else {
                        summaryText = `Showing ${start}-${end} of ${pagination.total_items} created posts`;
                    }
                } else {
                    summaryText = searchTerm ? `No posts found matching "${searchTerm}"` : 'No created posts found';
                }
                
                $('#created-posts-summary').text(summaryText);
            }
            
            /**
             * Generate explanation for a specific reading level
             */
            function generateExplanationForLevel(selectionId, level, text, button) {
                const originalText = button.text();
                const levelContainer = button.closest('.level-content');
                
                button.prop('disabled', true).text('Generating...');
                
                const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
                const nonce = window.wpAiExplainer?.nonce;
                
                if (!ajaxUrl || !nonce) {
                    if (window.ExplainerPlugin?.replaceAlert) {
                        window.ExplainerPlugin.replaceAlert('AJAX configuration not found.', 'error');
                    } else {
                        alert('AJAX configuration not found.');
                    }
                    button.prop('disabled', false).text(originalText);
                    return;
                }
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'explainer_generate_reading_level_explanation',
                        nonce: nonce,
                        selection_id: selectionId,
                        reading_level: level,
                        selected_text: text
                    },
                    success: function(response) {
                        button.prop('disabled', false).text(originalText);
                        
                        if (response.success && response.data && response.data.explanation) {
                            // Replace the "no explanation" content with the new explanation
                            const explanation = response.data.explanation;
                            
                            let html = '<div class="explanation-text-container">';
                            html += '<div class="explanation-display-mode" data-selection-id="' + selectionId + '">';
                            html += '<div class="explanation-text" style="display: inline-block; width: calc(100% - 60px); margin-right: 10px; line-height: 1.4;">';
                            html += escapeHtml(explanation);
                            html += '</div>';
                            html += '<button type="button" class="btn-base btn-ghost btn-sm edit-explanation-btn" data-selection-id="' + selectionId + '" title="Edit explanation">';
                            html += '<span class="dashicons dashicons-edit" style="font-size: 16px; width: 16px; height: 16px;"></span>';
                            html += '</button>';
                            html += '</div>';
                            html += '<div class="explanation-edit-mode" style="display: none;" data-selection-id="' + selectionId + '">';
                            html += '<textarea class="explanation-edit-field" rows="3" style="width: 100%; margin-bottom: 8px; resize: vertical; min-height: 60px;">' + escapeHtml(explanation) + '</textarea>';
                            html += '<div class="explanation-edit-actions" style="display: flex; gap: 8px;">';
                            html += '<button type="button" class="btn-base btn-primary btn-sm save-explanation-btn" data-selection-id="' + selectionId + '">Save</button>';
                            html += '<button type="button" class="btn-base btn-secondary btn-sm cancel-edit-btn" data-selection-id="' + selectionId + '">Cancel</button>';
                            html += '</div>';
                            html += '</div>';
                            html += '</div>';
                            
                            // Find the content area and replace everything before the level-actions div
                            const actionsDiv = levelContainer.find('.level-actions');
                            levelContainer.children().not('.level-actions').remove();
                            levelContainer.prepend(html);
                            
                            // Reinitialize handlers for the new content
                            initializeExplanationEditHandlers();
                            
                        } else {
                            const errorMsg = response.data?.message || 'Failed to generate explanation';
                            if (window.ExplainerPlugin?.replaceAlert) {
                                window.ExplainerPlugin.replaceAlert('Error generating explanation: ' + errorMsg, 'error');
                            } else {
                                alert('Error generating explanation: ' + errorMsg);
                            }
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).text(originalText);
                        if (window.ExplainerPlugin?.replaceAlert) {
                            window.ExplainerPlugin.replaceAlert('Network error while generating explanation. Please try again.', 'error');
                        } else {
                            alert('Network error while generating explanation. Please try again.');
                        }
                    }
                });
            }
            
            /**
             * Initialize explanation editing functionality for all visible explanations
             */
            function initializeExplanationEditHandlers() {
                // Edit button click handlers - need to re-initialize for dynamically added content
                $(document).off('click.edit-explanations').on('click.edit-explanations', '.edit-explanation-btn', function(e) {
                    e.preventDefault();
                    const selectionId = $(this).data('selection-id');
                    const container = $(this).closest('.explanation-text-container');
                    
                    container.find('.explanation-display-mode').hide();
                    container.find('.explanation-edit-mode').show();
                    
                    // Focus the textarea and position cursor at end
                    const textarea = container.find('.explanation-edit-field');
                    setTimeout(() => {
                        textarea.focus();
                        const len = textarea.val().length;
                        textarea[0].setSelectionRange(len, len);
                    }, 50);
                });
                
                // Cancel edit button click handlers
                $(document).off('click.cancel-explanations').on('click.cancel-explanations', '.cancel-edit-btn', function(e) {
                    e.preventDefault();
                    const container = $(this).closest('.explanation-text-container');
                    
                    container.find('.explanation-edit-mode').hide();
                    container.find('.explanation-display-mode').show();
                    
                    // Reset textarea to original value
                    const originalText = container.find('.explanation-text').text();
                    container.find('.explanation-edit-field').val(originalText);
                });
                
                // Save button click handlers
                $(document).off('click.save-explanations').on('click.save-explanations', '.save-explanation-btn', function(e) {
                    e.preventDefault();
                    const selectionId = $(this).data('selection-id');
                    const saveBtn = $(this);
                    saveExplanationEditFromButton(saveBtn, selectionId);
                });
                
                // ESC key to cancel editing
                $(document).off('keydown.edit-explanations').on('keydown.edit-explanations', '.explanation-edit-field', function(e) {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        $(this).closest('.explanation-text-container').find('.cancel-edit-btn').click();
                    }
                });
                
                // Delete selection button handlers
                $(document).off('click.delete-selections').on('click.delete-selections', '.delete-selection-btn', function(e) {
                    e.preventDefault();
                    const button = $(this);
                    const selectionId = button.data('id');
                    
                    if (!selectionId) {
                        return;
                    }
                    
                    handleDeleteSelection(selectionId, button);
                });
            }

            /**
             * Load explanation data via AJAX and show the expanded row
             */
            function loadAndShowExplanation(selectionId) {
                const explanationRow = $('#explanation-row-' + selectionId);
                const arrowBtn = $('.show-explanation-btn[data-selection-id="' + selectionId + '"]');
                const arrow = arrowBtn.find('.dashicons');
                const loadingDiv = explanationRow.find('.explanation-loading');
                const detailsDiv = explanationRow.find('.explanation-details');
                
                // Show loading state
                explanationRow.show();
                loadingDiv.show();
                detailsDiv.hide();
                arrow.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
                arrowBtn.attr('title', 'Hide explanation and source URL');
                
                const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
                const nonce = window.wpAiExplainer?.nonce;
                
                if (!ajaxUrl || !nonce) {
                    loadingDiv.hide();
                    detailsDiv.html('<div style="color: red; padding: 10px;">AJAX configuration not found.</div>').show();
                    return;
                }
                
                // Make AJAX request to get explanation
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'explainer_get_selection_explanation',
                        nonce: nonce,
                        selection_id: selectionId
                    },
                    success: function(response) {
                        loadingDiv.hide();
                        
                        if (response.success) {
                            renderExplanationDetails(selectionId, response.data);
                        } else {
                            detailsDiv.html('<div style="color: #d63638; padding: 10px; background: #fcf0f1; border: 1px solid #d63638; border-radius: 4px;">Error: ' + (response.data?.message || 'Failed to load explanation') + '</div>').show();
                        }
                    },
                    error: function() {
                        loadingDiv.hide();
                        detailsDiv.html('<div style="color: #d63638; padding: 10px; background: #fcf0f1; border: 1px solid #d63638; border-radius: 4px;">Network error loading explanation</div>').show();
                    }
                });
            }

            /**
             * Render the explanation details in the expanded row
             */
            function renderExplanationDetails(selectionId, data) {
                const detailsDiv = $('#explanation-row-' + selectionId + ' .explanation-details');
                
                let html = '<div style="padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0;">';
                
                // Source URLs section
                if (data.source_urls && data.source_urls.length > 0) {
                    html += '<div style="margin-bottom: 15px;">';
                    html += '<strong>Source URL' + (data.source_urls.length > 1 ? 's' : '') + ':</strong>';
                    html += '<div style="margin-top: 5px;">';
                    
                    data.source_urls.forEach(function(url, index) {
                        if (url && url.trim()) {
                            html += '<div style="margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">';
                            html += '<span style="color: #666; font-size: 12px; min-width: 20px;">' + (index + 1) + '.</span>';
                            html += '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener" style="word-break: break-all; line-height: 1.4;">' + escapeHtml(url) + '</a>';
                            html += '</div>';
                        }
                    });
                    
                    if (data.source_urls.length > 1) {
                        html += '<div style="margin-top: 8px; font-size: 12px; color: #666; font-style: italic;">';
                        html += 'This text selection appears on ' + data.source_urls.length + ' different pages';
                        html += '</div>';
                    }
                    
                    html += '</div>';
                    html += '</div>';
                }
                
                // AI Explanation section
                html += '<div style="margin-bottom: 10px;">';
                html += '<strong>AI Explanation:</strong>';
                html += '</div>';
                
                if (data.explanation && data.explanation.trim()) {
                    html += '<div class="explanation-text-container">';
                    
                    // Display mode - text with edit icon
                    html += '<div class="explanation-display-mode" data-selection-id="' + selectionId + '">';
                    html += '<div class="explanation-text" style="display: inline-block; width: calc(100% - 60px); margin-right: 10px;">';
                    html += escapeHtml(data.explanation);
                    html += '</div>';
                    html += '<button type="button" class="btn-base btn-ghost btn-sm edit-explanation-btn" data-selection-id="' + selectionId + '" title="Edit explanation">';
                    html += '<span class="dashicons dashicons-edit" style="font-size: 16px; width: 16px; height: 16px;"></span>';
                    html += '</button>';
                    html += '</div>';
                    
                    // Edit mode - textarea with save/cancel (initially hidden)
                    html += '<div class="explanation-edit-mode" style="display: none;" data-selection-id="' + selectionId + '">';
                    html += '<textarea class="explanation-edit-field" rows="3" style="width: 100%; margin-bottom: 8px; resize: vertical; min-height: 60px;">' + escapeHtml(data.explanation) + '</textarea>';
                    html += '<div class="explanation-edit-actions" style="display: flex; gap: 8px;">';
                    html += '<button type="button" class="btn-base btn-primary btn-sm save-explanation-btn" data-selection-id="' + selectionId + '">Save</button>';
                    html += '<button type="button" class="btn-base btn-secondary btn-sm cancel-edit-btn" data-selection-id="' + selectionId + '">Cancel</button>';
                    html += '</div>';
                    html += '</div>';
                    
                    html += '</div>';
                } else {
                    html += '<div style="color: #666; font-style: italic;">No explanation available for this selection.</div>';
                }
                
                html += '</div>';
                
                detailsDiv.html(html).show();
                
                // Initialize edit functionality for this explanation
                initializeSpecificExplanationEditHandlers(selectionId);
            }

            /**
             * Initialize edit/save handlers for explanation text (specific to a selection)
             */
            function initializeSpecificExplanationEditHandlers(selectionId) {
                const selector = '[data-selection-id="' + selectionId + '"]';
                
                // Edit button click - switch to inline edit mode
                $(document).off('click.edit-' + selectionId).on('click.edit-' + selectionId, '.edit-explanation-btn' + selector, function(e) {
                    e.preventDefault();
                    const container = $(this).closest('.explanation-text-container');
                    container.find('.explanation-display-mode').hide();
                    container.find('.explanation-edit-mode').show();
                    
                    // Focus the textarea and position cursor at end
                    const textarea = container.find('.explanation-edit-field');
                    setTimeout(() => {
                        textarea.focus();
                        const len = textarea.val().length;
                        textarea[0].setSelectionRange(len, len);
                    }, 50);
                });
                
                // Cancel edit button click - return to display mode
                $(document).off('click.cancel-' + selectionId).on('click.cancel-' + selectionId, '.cancel-edit-btn' + selector, function(e) {
                    e.preventDefault();
                    const container = $(this).closest('.explanation-text-container');
                    container.find('.explanation-edit-mode').hide();
                    container.find('.explanation-display-mode').show();
                    
                    // Reset textarea to original value
                    const originalText = container.find('.explanation-text').text();
                    container.find('.explanation-edit-field').val(originalText);
                });
                
                // Save button click
                $(document).off('click.save-' + selectionId).on('click.save-' + selectionId, '.save-explanation-btn' + selector, function(e) {
                    e.preventDefault();
                    const saveBtn = $(this);
                    saveExplanationEditFromButton(saveBtn, selectionId);
                });
                
                // ESC key to cancel editing
                $(document).off('keydown.edit-' + selectionId).on('keydown.edit-' + selectionId, '.explanation-edit-field' + selector, function(e) {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        $(this).closest('.explanation-text-container').find('.cancel-edit-btn').click();
                    }
                });
            }

            /**
             * Save edited explanation via AJAX (using button context)
             */
            function saveExplanationEditFromButton(saveBtn, selectionId) {
                const container = saveBtn.closest('.explanation-text-container');
                const textarea = container.find('.explanation-edit-field');
                const newExplanation = textarea.val().trim();
                
                if (!newExplanation) {
                    if (window.ExplainerPlugin?.replaceAlert) {
                        window.ExplainerPlugin.replaceAlert('Please enter an explanation before saving.', 'warning');
                    } else {
                        alert('Please enter an explanation before saving.');
                    }
                    textarea.focus();
                    return;
                }
                
                // Disable save button and show loading state
                const originalSaveText = saveBtn.text();
                saveBtn.prop('disabled', true).text('Saving...');
                
                const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
                const nonce = window.wpAiExplainer?.nonce;
                
                if (!ajaxUrl || !nonce) {
                    if (window.ExplainerPlugin?.replaceAlert) {
                        window.ExplainerPlugin.replaceAlert('AJAX configuration not found.', 'error');
                    } else {
                        alert('AJAX configuration not found.');
                    }
                    saveBtn.prop('disabled', false).text(originalSaveText);
                    return;
                }
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'explainer_save_selection_explanation',
                        nonce: nonce,
                        selection_id: selectionId,
                        explanation: newExplanation
                    },
                    success: function(response) {
                        saveBtn.prop('disabled', false).text(originalSaveText);
                        
                        if (response.success) {
                            // Update the display text
                            container.find('.explanation-text').html(escapeHtml(newExplanation));
                            
                            // Switch back to display mode
                            container.find('.explanation-edit-mode').hide();
                            container.find('.explanation-display-mode').show();
                            
                            if (window.ExplainerPlugin?.replaceAlert) {
                                window.ExplainerPlugin.replaceAlert('Explanation updated successfully!', 'success');
                            }
                        } else {
                            const errorMsg = response.data?.message || 'Failed to save explanation';
                            if (window.ExplainerPlugin?.replaceAlert) {
                                window.ExplainerPlugin.replaceAlert('Error saving explanation: ' + errorMsg, 'error');
                            } else {
                                alert('Error saving explanation: ' + errorMsg);
                            }
                        }
                    },
                    error: function() {
                        saveBtn.prop('disabled', false).text(originalSaveText);
                        if (window.ExplainerPlugin?.replaceAlert) {
                            window.ExplainerPlugin.replaceAlert('Network error while saving explanation. Please try again.', 'error');
                        } else {
                            alert('Network error while saving explanation. Please try again.');
                        }
                    }
                });
            }

            /**
             * Save edited explanation via AJAX (legacy function)
             */
            function saveExplanationEdit(selectionId) {
                const container = $('#explanation-row-' + selectionId + ' .explanation-text-container');
                const textarea = container.find('.explanation-edit-field');
                const saveBtn = container.find('.save-explanation-btn');
                const newExplanation = textarea.val().trim();
                
                if (!newExplanation) {
                    if (window.ExplainerPlugin?.replaceAlert) {
                        window.ExplainerPlugin.replaceAlert('Please enter an explanation before saving.', 'warning');
                    } else {
                        alert('Please enter an explanation before saving.');
                    }
                    textarea.focus();
                    return;
                }
                
                // Disable save button and show loading state
                const originalSaveText = saveBtn.text();
                saveBtn.prop('disabled', true).text('Saving...');
                
                const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
                const nonce = window.wpAiExplainer?.nonce;
                
                if (!ajaxUrl || !nonce) {
                    if (window.ExplainerPlugin?.replaceAlert) {
                        window.ExplainerPlugin.replaceAlert('AJAX configuration not found.', 'error');
                    } else {
                        alert('AJAX configuration not found.');
                    }
                    saveBtn.prop('disabled', false).text(originalSaveText);
                    return;
                }
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'explainer_save_selection_explanation',
                        nonce: nonce,
                        selection_id: selectionId,
                        explanation: newExplanation
                    },
                    success: function(response) {
                        saveBtn.prop('disabled', false).text(originalSaveText);
                        
                        if (response.success) {
                            // Update the display text
                            container.find('.explanation-text').html(escapeHtml(newExplanation));
                            
                            // Switch back to display mode
                            container.find('.explanation-edit-mode').hide();
                            container.find('.explanation-display-mode').show();
                            
                            // Show brief success feedback
                            const displayMode = container.find('.explanation-display-mode');
                            const originalBg = displayMode.css('background-color');
                            displayMode.css('background-color', '#d1e7dd').animate({'background-color': originalBg}, 1500);
                            
                        } else {
                            if (window.ExplainerPlugin?.replaceAlert) {
                                window.ExplainerPlugin.replaceAlert('Error saving explanation: ' + (response.data?.message || 'Unknown error'), 'error');
                            } else {
                                alert('Error saving explanation: ' + (response.data?.message || 'Unknown error'));
                            }
                        }
                    },
                    error: function() {
                        saveBtn.prop('disabled', false).text(originalSaveText);
                        if (window.ExplainerPlugin?.replaceAlert) {
                            window.ExplainerPlugin.replaceAlert('Network error saving explanation. Please try again.', 'error');
                        } else {
                            alert('Network error saving explanation. Please try again.');
                        }
                    }
                });
            }

            console.log('Popular selections dashboard initialized');
        },

        /**
         * Initialize cron management
         */
        initializeCronManagement: function() {
            // Skip if cron field doesn't exist (free version)
            if (!$('#explainer_enable_cron').length) {
                return;
            }

            $('#explainer_enable_cron').on('change', function() {
                const instructionsDiv = $('#cron-instructions');

                if ($(this).is(':checked')) {
                    instructionsDiv.slideDown();
                } else {
                    instructionsDiv.slideUp();
                }

                // Update job queue UI when cron setting changes
                if (typeof window.WPAIExplainer.Admin.JobMonitoring !== 'undefined') {
                    window.WPAIExplainer.Admin.JobMonitoring.updateJobQueueUI();
                }
            });
            
            // Initialize on page load
            const cronEnabled = $('#explainer_enable_cron').is(':checked');
            if (cronEnabled) {
                $('#cron-instructions').show();
            }

            console.log('Cron management initialized');
        },

        /**
         * Initialize reset functionality
         */
        initializeResetFunctionality: function() {
            $('#reset-settings').on('click', function(e) {
                e.preventDefault();
                
                const performReset = () => {
                    const button = $(this);
                    const originalText = button.text();
                    
                    button.text('Resetting...').prop('disabled', true);
                    
                    const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
                    const nonce = window.wpAiExplainer?.nonce;

                    if (!ajaxUrl || !nonce) {
                        if (window.ExplainerPlugin?.replaceAlert) {
                            window.ExplainerPlugin.replaceAlert('AJAX configuration not found.', 'error');
                        } else {
                            alert('AJAX configuration not found.');
                        }
                        button.text(originalText).prop('disabled', false);
                        return;
                    }
                    
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'explainer_reset_settings',
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                window.WPAIExplainer.Admin.Core.showMessage('Settings reset successfully. Please reload the page.', 'success');
                                setTimeout(() => {
                                    location.reload();
                                }, 2000);
                            } else {
                                window.WPAIExplainer.Admin.Core.showMessage('Failed to reset settings: ' + (response.data?.message || 'Unknown error'), 'error');
                            }
                        },
                        error: function() {
                            window.WPAIExplainer.Admin.Core.showMessage('Connection error while resetting settings.', 'error');
                        },
                        complete: function() {
                            button.text(originalText).prop('disabled', false);
                        }
                    });
                };
                
                if (window.ExplainerPlugin?.replaceConfirm) {
                    window.ExplainerPlugin.replaceConfirm(
                        'Are you sure you want to reset all settings to default? This cannot be undone.',
                        {
                            confirmText: 'Reset All Settings',
                            cancelText: 'Cancel',
                            type: 'error'
                        }
                    ).then((confirmed) => {
                        if (confirmed) {
                            performReset();
                        }
                    });
                    return;
                } else {
                    if (confirm('Are you sure you want to reset all settings to default? This cannot be undone.')) {
                        performReset();
                    }
                    return;
                }
            });

            console.log('Reset functionality initialized');
        },

        /**
         * Initialize prompt management
         */
        initializePromptManagement: function() {
            // Skip if reset prompt button doesn't exist (free version)
            if (!$('#reset-prompt-default').length) {
                return;
            }

            $('#reset-prompt-default').on('click', function(e) {
                e.preventDefault();

                const performPromptReset = () => {
                    const defaultPrompt = (typeof wpAiExplainer !== 'undefined' && wpAiExplainer.defaultPrompt) || 'Please provide a clear, concise explanation of the following text in 1-2 sentences: {{selectedtext}}';
                    $('textarea[name="explainer_custom_prompt"]').val(defaultPrompt);

                    // Trigger validation if available
                    if (typeof window.WPAIExplainer.Admin.FormValidation !== 'undefined') {
                        $('textarea[name="explainer_custom_prompt"]').trigger('input');
                    }

                    window.WPAIExplainer.Admin.Core.showMessage('Prompt reset to default. Remember to save your settings.', 'success');
                };

                if (window.ExplainerPlugin?.replaceConfirm) {
                    window.ExplainerPlugin.replaceConfirm(
                        'Reset the prompt template to default? Your custom prompt will be lost.',
                        {
                            confirmText: 'Reset to Default',
                            cancelText: 'Cancel',
                            type: 'warning'
                        }
                    ).then((confirmed) => {
                        if (confirmed) {
                            performPromptReset();
                        }
                    });
                    return;
                } else {
                    if (confirm('Reset the prompt template to default? Your custom prompt will be lost.')) {
                        performPromptReset();
                    }
                    return;
                }

                const defaultPrompt = (typeof wpAiExplainer !== 'undefined' && wpAiExplainer.defaultPrompt) || 'Please provide a clear, concise explanation of the following text in 1-2 sentences: {{selectedtext}}';
                $('textarea[name="explainer_custom_prompt"]').val(defaultPrompt);
                
                // Trigger validation if available
                if (typeof window.WPAIExplainer.Admin.FormValidation !== 'undefined') {
                    $('textarea[name="explainer_custom_prompt"]').trigger('input');
                }
                
                window.WPAIExplainer.Admin.Core.showMessage('Prompt reset to default. Remember to save your settings.', 'success');
            });

            console.log('Prompt management initialized');
        },

        /**
         * Initialize blocked words functionality
         */
        initializeBlockedWords: function() {
            // Skip if blocked words field doesn't exist (free version)
            if (!$('#explainer_blocked_words').length) {
                return;
            }

            function updateBlockedWordsCount() {
                const value = $('#explainer_blocked_words').val();
                if (!value) {
                    $('#blocked-words-count').text(0);
                    return;
                }
                const words = value.split('\n').filter(word => word.trim() !== '');
                $('#blocked-words-count').text(words.length);
            }

            $('#explainer_blocked_words').on('input', updateBlockedWordsCount);

            $('#clear-blocked-words').on('click', function(e) {
                e.preventDefault();

                const performClearWords = () => {
                    $('#explainer_blocked_words').val('');
                    updateBlockedWordsCount();
                    window.WPAIExplainer.Admin.Core.showMessage('Blocked words cleared.', 'success');
                };

                if (window.ExplainerPlugin?.replaceConfirm) {
                    window.ExplainerPlugin.replaceConfirm(
                        'Clear all blocked words? This cannot be undone.',
                        {
                            confirmText: 'Clear All Words',
                            cancelText: 'Cancel',
                            type: 'warning'
                        }
                    ).then((confirmed) => {
                        if (confirmed) {
                            performClearWords();
                        }
                    });
                    return;
                } else {
                    if (confirm('Clear all blocked words? This cannot be undone.')) {
                        performClearWords();
                    }
                    return;
                }

                $('#explainer_blocked_words').val('');
                updateBlockedWordsCount();
                window.WPAIExplainer.Admin.Core.showMessage('Blocked words cleared.', 'success');
            });

            $('#load-default-blocked-words').on('click', function(e) {
                e.preventDefault();

                const defaultWords = 'spam\nscam\nfraud\ninappropriate\noffensive';
                $('#explainer_blocked_words').val(defaultWords);
                updateBlockedWordsCount();
                window.WPAIExplainer.Admin.Core.showMessage('Default blocked words loaded.', 'success');
            });

            // Initialize count
            updateBlockedWordsCount();

            console.log('Blocked words functionality initialized');
        },

        /**
         * Initialize re-enable plugin button functionality
         */
        initializeReenableButton: function() {
            $(document).on('click', '.explainer-reenable-btn-settings', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const nonce = $button.data('nonce');
                
                // Confirm action
                if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                    window.ExplainerPlugin.Notifications.confirm(
                        'Are you sure you want to re-enable the AI Explainer plugin? Make sure you have resolved the usage limit issues first.',
                        {
                            title: 'Confirm Re-enable',
                            confirmText: 'Re-enable',
                            cancelText: 'Cancel'
                        }
                    ).then(function(confirmed) {
                        if (confirmed) {
                            // Show loading state
                            $button.prop('disabled', true).text('Re-enabling...');
                            
                            const loadingId = window.ExplainerPlugin.Notifications.loading('Re-enabling plugin...');
                            
                            // Make AJAX request
                            $.post(ajaxurl, {
                                action: 'explainer_reenable_plugin',
                                nonce: nonce
                            })
                            .done(function(response) {
                                window.ExplainerPlugin.Notifications.hide(loadingId);
                                
                                if (response.success) {
                                    window.ExplainerPlugin.Notifications.success('Plugin has been successfully re-enabled.');
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 2000);
                                } else {
                                    window.ExplainerPlugin.Notifications.error('Error re-enabling plugin: ' + (response.data.message || 'Unknown error'));
                                    $button.prop('disabled', false).text('Re-enable Plugin Now');
                                }
                            })
                            .fail(function() {
                                window.ExplainerPlugin.Notifications.hide(loadingId);
                                window.ExplainerPlugin.Notifications.error('Failed to re-enable plugin. Please try again.');
                                $button.prop('disabled', false).text('Re-enable Plugin Now');
                            });
                        }
                    });
                } else {
                    // Fallback to regular confirm dialog
                    if (confirm('Are you sure you want to re-enable the AI Explainer plugin? Make sure you have resolved the usage limit issues first.')) {
                        $button.prop('disabled', true).text('Re-enabling...');
                        
                        $.post(ajaxurl, {
                            action: 'explainer_reenable_plugin',
                            nonce: nonce
                        })
                        .done(function(response) {
                            if (response.success) {
                                alert('Plugin has been successfully re-enabled.');
                                window.location.reload();
                            } else {
                                alert('Error: ' + (response.data.message || 'Unknown error'));
                                $button.prop('disabled', false).text('Re-enable Plugin Now');
                            }
                        })
                        .fail(function() {
                            alert('Failed to re-enable plugin. Please try again.');
                            $button.prop('disabled', false).text('Re-enable Plugin Now');
                        });
                    }
                }
            });
            
            console.log('Re-enable button functionality initialized');
        },

        /**
         * Utility method to show messages
         */
        showMessage: function(message, type) {
            // Use the brand notification system instead of inline WordPress notices
            if (window.ExplainerPlugin?.Notifications) {
                switch (type) {
                    case 'success':
                        window.ExplainerPlugin.Notifications.success(message);
                        break;
                    case 'warning':
                        window.ExplainerPlugin.Notifications.warning(message);
                        break;
                    case 'info':
                        window.ExplainerPlugin.Notifications.info(message);
                        break;
                    case 'error':
                    default:
                        window.ExplainerPlugin.Notifications.error(message);
                }
            } else {
                // Fallback: log to console if notification system not available
                console.log(`[${type.toUpperCase()}] ${message}`);
            }
        },

        /**
         * Initialize real-time status updates
         */
        initializeRealtimeStatusUpdates: function() {
            // Update status display when enable checkbox changes
            $('#realtime_enabled').on('change', function() {
                const enabled = $(this).is(':checked');
                const statusIndicator = $('.realtime-status-indicator');
                const statusText = $('.realtime-status-text');
                
                if (enabled) {
                    statusIndicator.removeClass('realtime-status-inactive').addClass('realtime-status-active');
                    statusText.text('Enabled');
                } else {
                    statusIndicator.removeClass('realtime-status-active').addClass('realtime-status-inactive');
                    statusText.text('Disabled');
                }
            });
            
            // Update transport mode display when dropdown changes
            $('#realtime_transport').on('change', function() {
                const mode = $(this).val();
                const modeDisplay = $('.transport-mode-display');
                
                if (mode) {
                    const displayText = mode.charAt(0).toUpperCase() + mode.slice(1);
                    modeDisplay.text(displayText);
                }
            });
            
            console.log('Real-time status updates initialized');
        },

        /**
         * Initialize real-time system
         */
        initializeRealtimeSystem: function() {
            try {
                // Check if real-time adapter is available
                if (window.ExplainerPlugin && window.ExplainerPlugin.RealtimeAdapter) {
                    this.realtimeAdapter = new window.ExplainerPlugin.RealtimeAdapter({
                        enableDebugLogging: true
                    });
                    this.isRealtimeEnabled = true;
                    this.fallbackMode = false;
                    
                    console.log('AdminCore: Real-time system initialized');
                    
                    // Initialize the adapter
                    this.realtimeAdapter.initialize().catch((error) => {
                        console.error('AdminCore: Failed to initialize real-time adapter:', error.message);
                        this.fallbackToPolling();
                    });
                    
                } else {
                    console.warn('AdminCore: Real-time adapter not available, using polling fallback');
                    this.fallbackToPolling();
                }
            } catch (error) {
                console.error('AdminCore: Error initializing real-time system:', error.message);
                this.fallbackToPolling();
            }
        },
        
        /**
         * Fallback to polling mode
         */
        fallbackToPolling: function() {
            this.isRealtimeEnabled = false;
            this.fallbackMode = true;
            this.realtimeAdapter = null;
            
            console.log('AdminCore: Switched to polling fallback mode');
        },
        
        /**
         * Subscribe to popular selections real-time events
         */
        subscribeToPopularSelectionsEvents: function() {
            if (!this.realtimeAdapter) {
                return;
            }
            
            try {
                // Subscribe to popular selections updates
                this.realtimeAdapter.connect('popular_selections:updated', (eventData, context) => {
                    console.log('AdminCore: Received popular_selections:updated event', {
                        eventData,
                        context
                    });
                    
                    // Only update if popular tab is active and selections aren't expanded
                    if ($('.nav-tab[href="#popular"]').hasClass('nav-tab-active')) {
                        var hasExpandedSelections = $('.dashicons-arrow-down-alt2').length > 0;
                        if (!hasExpandedSelections) {
                            // Use loadPopularSelections from the outer scope
                            if (typeof loadPopularSelections === 'function') {
                                loadPopularSelections(false);
                            }
                        }
                    }
                });
                
                this.subscribedTopics.add('popular_selections:updated');
                
                console.log('AdminCore: Subscribed to popular selections events');
                
            } catch (error) {
                console.error('AdminCore: Failed to subscribe to popular selections events:', error.message);
                this.fallbackToPolling();
            }
        },
        
        /**
         * Subscribe to created posts real-time events
         */
        subscribeToCreatedPostsEvents: function() {
            if (!this.realtimeAdapter) {
                return;
            }
            
            try {
                // Subscribe to created posts updates
                this.realtimeAdapter.connect('created_posts:updated', (eventData, context) => {
                    console.log('AdminCore: Received created_posts:updated event', {
                        eventData,
                        context
                    });
                    
                    // Only update if not currently loading
                    if (!$('#created-posts-loading').is(':visible')) {
                        // Use loadCreatedPosts from the outer scope
                        if (typeof loadCreatedPosts === 'function') {
                            loadCreatedPosts(false);
                        }
                    }
                });
                
                this.subscribedTopics.add('created_posts:updated');
                
                console.log('AdminCore: Subscribed to created posts events');
                
            } catch (error) {
                console.error('AdminCore: Failed to subscribe to created posts events:', error.message);
                this.fallbackToPolling();
            }
        },
        
        /**
         * Unsubscribe from popular selections events
         */
        unsubscribeFromPopularSelectionsEvents: function() {
            if (!this.realtimeAdapter) {
                return;
            }
            
            try {
                this.realtimeAdapter.disconnect('popular_selections:updated');
                this.subscribedTopics.delete('popular_selections:updated');
                
                console.log('AdminCore: Unsubscribed from popular selections events');
                
            } catch (error) {
                console.error('AdminCore: Error unsubscribing from popular selections events:', error.message);
            }
        },
        
        /**
         * Unsubscribe from created posts events
         */
        unsubscribeFromCreatedPostsEvents: function() {
            if (!this.realtimeAdapter) {
                return;
            }
            
            try {
                this.realtimeAdapter.disconnect('created_posts:updated');
                this.subscribedTopics.delete('created_posts:updated');
                
                console.log('AdminCore: Unsubscribed from created posts events');
                
            } catch (error) {
                console.error('AdminCore: Error unsubscribing from created posts events:', error.message);
            }
        },
        
        /**
         * Unsubscribe from all real-time events
         */
        unsubscribeFromAllEvents: function() {
            if (!this.realtimeAdapter) {
                return;
            }
            
            try {
                this.realtimeAdapter.disconnectAll();
                this.subscribedTopics.clear();
                
                console.log('AdminCore: Unsubscribed from all real-time events');
                
            } catch (error) {
                console.error('AdminCore: Error unsubscribing from events:', error.message);
            }
        }
    };

    /**
     * Utility function for HTML escaping
     */
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            '\'': '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    /**
     * Handle selection deletion with confirmation
     */
    function handleDeleteSelection(selectionId, button) {
        // Get selection text for confirmation message
        const selectionTextElement = button.closest('tr').find('.clickable-text');
        const selectionText = selectionTextElement.length > 0 ? selectionTextElement.text().trim() : 'this selection';
        const shortText = selectionText && selectionText.length > 50 ? selectionText.substring(0, 50) + '...' : selectionText || 'this selection';
        
        // Show confirmation using the notification system
        if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
            window.ExplainerPlugin.Notifications.show({
                message: `Are you sure you want to delete the explanation for "${shortText}"? This will remove all reading levels and cannot be undone.`,
                type: 'warning',
                duration: 0, // Don't auto-hide
                actions: [
                    {
                        text: 'Delete',
                        type: 'destructive',
                        callback: () => {
                            performSimpleDelete(selectionId, button);
                            if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                                window.ExplainerPlugin.Notifications.clearAll();
                            }
                        }
                    },
                    {
                        text: 'Cancel',
                        type: 'default',
                        callback: () => {
                            if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                                window.ExplainerPlugin.Notifications.clearAll();
                            }
                        }
                    }
                ]
            });
        } else {
            // Fallback to native confirm
            if (confirm(`Are you sure you want to delete the explanation for "${shortText}"? This will remove all reading levels and cannot be undone.`)) {
                performSimpleDelete(selectionId, button);
            }
        }
    }
    
    /**
     * Simple delete: opacity fade then AJAX refresh
     */
    function performSimpleDelete(selectionId, button) {
        const row = button.closest('tr');
        
        // Simple opacity fade to 0
        row.animate({ opacity: 0 }, 300, function() {
            // After fade, do the AJAX delete
            const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
            const nonce = window.wpAiExplainer?.nonce;
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'explainer_delete_selection',
                    nonce: nonce,
                    selection_id: selectionId
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                            window.ExplainerPlugin.Notifications.success(response.data?.message || 'Selection deleted successfully.');
                        }
                        
                        // Simply remove the row from DOM
                        row.remove();
                        
                        // Update the count display
                        const currentCount = $('#selections-table tbody tr').length;
                        const summaryEl = $('#selections-summary');
                        if (currentCount === 0) {
                            summaryEl.text('No selections found.');
                        } else {
                            summaryEl.text(`Showing 1-${currentCount} of ${currentCount} selections`);
                        }
                    } else {
                        // Show error and restore row
                        const errorMsg = response.data?.message || 'Failed to delete selection';
                        if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                            window.ExplainerPlugin.Notifications.error(errorMsg);
                        }
                        row.animate({ opacity: 1 }, 200);
                    }
                },
                error: function() {
                    if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                        window.ExplainerPlugin.Notifications.error('Network error while deleting selection.');
                    }
                    row.animate({ opacity: 1 }, 200);
                }
            });
        });
    }
    
    /**
     * Update the selection summary count display
     */
    function updateSelectionSummary() {
        const visibleRows = $('#selections-table tbody tr:visible').length;
        const summaryText = visibleRows === 1 ? '1 selection' : `${visibleRows} selections`;
        $('#selections-summary').text(summaryText);
    }
    
    /**
     * Real-time Diagnostics UI Manager
     */
    window.WPAIExplainer.Admin.RealtimeDiagnostics = {
        
        testButton: null,
        refreshButton: null,
        statusDisplay: null,
        testInProgress: false,
        
        /**
         * Initialize diagnostics UI
         */
        init: function() {
            this.testButton = $('#realtime-test-connection');
            this.refreshButton = $('#realtime-refresh-status');
            this.statusDisplay = $('#realtime-status');
            
            if (this.testButton.length) {
                this.bindEvents();
                console.log('Real-time diagnostics UI initialized');
            }
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Test connection button
            this.testButton.on('click', function(e) {
                e.preventDefault();
                self.runConnectivityTest();
            });
            
            // Refresh status button
            this.refreshButton.on('click', function(e) {
                e.preventDefault();
                self.refreshStatus();
            });
        },
        
        /**
         * Run connectivity test
         */
        runConnectivityTest: function() {
            if (this.testInProgress) {
                return;
            }
            
            this.testInProgress = true;
            this.showTestInProgress();
            
            var self = this;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'explainer_test_realtime_connection',
                    nonce: explainerSettings.nonce
                },
                timeout: 30000, // 30 second timeout
                success: function(response) {
                    if (response.success) {
                        self.displayTestResults(response.data);
                    } else {
                        self.displayTestError(response.data || 'Unknown error occurred');
                    }
                },
                error: function(xhr, status, error) {
                    var errorMessage = status === 'timeout' ? 
                        'Test timed out - server may be slow or unresponsive' : 
                        'Network error: ' + error;
                    self.displayTestError(errorMessage);
                },
                complete: function() {
                    self.testInProgress = false;
                    self.hideTestInProgress();
                }
            });
        },
        
        /**
         * Show test in progress state
         */
        showTestInProgress: function() {
            this.testButton.prop('disabled', true)
                .html('<span class="dashicons dashicons-update dashicons-spin"></span> Testing Connection...');
            
            this.statusDisplay.show().html(
                '<div class="realtime-test-progress">' +
                '<p><strong>Running diagnostics...</strong></p>' +
                '<p>This may take up to 30 seconds to complete.</p>' +
                '</div>'
            );
        },
        
        /**
         * Hide test in progress state
         */
        hideTestInProgress: function() {
            this.testButton.prop('disabled', false)
                .html('<span class="dashicons dashicons-admin-tools"></span> Test Real-Time Connection');
            
            this.refreshButton.show();
        },
        
        /**
         * Display test results
         */
        displayTestResults: function(results) {
            var statusHTML = this.buildStatusHTML(results);
            this.statusDisplay.html(statusHTML);
            
            // Update live status indicators
            this.updateLiveStatusIndicators(results);
        },
        
        /**
         * Build status HTML from results
         */
        buildStatusHTML: function(results) {
            var html = '<div class="realtime-test-results">';
            html += '<h4>Connection Test Results</h4>';
            
            // Summary section
            html += '<div class="test-summary">';
            html += '<h5>Summary</h5>';
            html += '<ul>';
            results.summary.forEach(function(item) {
                html += '<li>' + item + '</li>';
            });
            html += '</ul>';
            html += '</div>';
            
            // Detailed results
            html += '<div class="test-details">';
            html += '<h5>Detailed Results</h5>';
            html += '<div class="test-grid">';
            
            // Action Scheduler Support
            html += '<div class="test-item">';
            html += '<strong>Action Scheduler:</strong> ';
            html += results.action_scheduler_supported?.status ? 
                '<span class="status-pass">✓ Available</span>' : 
                '<span class="status-fail">✗ Not Available</span>';
            if (results.action_scheduler_supported?.error) {
                html += '<br><small class="error-detail">' + results.action_scheduler_supported.error + '</small>';
            }
            html += '</div>';
            
            // Polling Support
            html += '<div class="test-item">';
            html += '<strong>Polling Fallback:</strong> ';
            html += results.polling_working.status ? 
                '<span class="status-pass">✓ Working</span>' : 
                '<span class="status-fail">✗ Failed</span>';
            if (results.polling_working.error) {
                html += '<br><small class="error-detail">' + results.polling_working.error + '</small>';
            }
            html += '</div>';
            
            // Authentication
            html += '<div class="test-item">';
            html += '<strong>Authentication:</strong> ';
            html += results.authentication.status ? 
                '<span class="status-pass">✓ Working</span>' : 
                '<span class="status-fail">✗ Issues detected</span>';
            html += '</div>';
            
            // Transport Mode
            html += '<div class="test-item">';
            html += '<strong>Current Transport:</strong> ';
            html += '<span class="transport-mode">' + results.current_transport.toUpperCase() + '</span>';
            html += '</div>';
            
            // Connection Latency
            html += '<div class="test-item">';
            html += '<strong>Connection Latency:</strong> ';
            var latencyClass = results.connection_latency.latency_ms > 1000 ? 'status-warning' : 'status-good';
            html += '<span class="' + latencyClass + '">' + results.connection_latency.latency_ms + 'ms</span>';
            html += '</div>';
            
            // CDN Interference
            html += '<div class="test-item">';
            html += '<strong>CDN Detection:</strong> ';
            html += results.cdn_interference.detected ? 
                '<span class="status-warning">⚠ CDN Detected</span>' : 
                '<span class="status-good">✓ No interference</span>';
            html += '<br><small>' + results.cdn_interference.recommendation + '</small>';
            html += '</div>';
            
            html += '</div>'; // test-grid
            html += '</div>'; // test-details
            
            // Recommendations
            if (results.recommendations.length > 0) {
                html += '<div class="test-recommendations">';
                html += '<h5>Recommendations</h5>';
                html += '<ul>';
                results.recommendations.forEach(function(rec) {
                    html += '<li>' + rec + '</li>';
                });
                html += '</ul>';
                html += '</div>';
            }
            
            // Test duration
            html += '<div class="test-metadata">';
            html += '<small>Test completed in ' + results.test_duration + 'ms</small>';
            html += '</div>';
            
            html += '</div>'; // realtime-test-results
            return html;
        },
        
        /**
         * Update live status indicators based on test results
         */
        updateLiveStatusIndicators: function(results) {
            var statusEl = $('#realtime-live-status');
            if (!statusEl.length) return;
            
            var systemStatus = results.sse_supported.status || results.polling_working.status ? 'active' : 'inactive';
            var transportStatus = results.current_transport;
            
            statusEl.find('.realtime-status-indicator')
                .removeClass('realtime-status-active realtime-status-inactive realtime-status-fallback')
                .addClass('realtime-status-' + systemStatus);
        },
        
        /**
         * Display test error
         */
        displayTestError: function(error) {
            this.statusDisplay.html(
                '<div class="realtime-test-error">' +
                '<h4>Test Failed</h4>' +
                '<p class="error-message">' + error + '</p>' +
                '<p>Please try again or check your server configuration.</p>' +
                '</div>'
            );
        },
        
        /**
         * Refresh current status
         */
        refreshStatus: function() {
            // For now, just re-run the test
            this.runConnectivityTest();
        }
    };

    // Auto-initialize when document is ready
    $(document).ready(function() {
        window.WPAIExplainer.Admin.Core.init();
        window.WPAIExplainer.Admin.RealtimeDiagnostics.init();
    });

})(jQuery);