/**
 * Admin Settings JavaScript
 * Extracted from admin-settings.php template
 */
jQuery(document).ready(function($) {
    // Function to show a specific tab
    function showTab(tabHash) {
        // Remove active class from all tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').hide();
        
        // Add active class to clicked tab
        $('.nav-tab[href="' + tabHash + '"]').addClass('nav-tab-active');
        
        // Show corresponding content
        const target = tabHash + '-tab';
        $(target).show();
        
        // Handle body class for Post Scan and Support tabs to hide Save Changes button
        if (tabHash === '#support' || tabHash === '#post-scan') {
            $('body').addClass('popular-tab-active');
        } else {
            $('body').removeClass('popular-tab-active');
        }

        // Store the active tab in localStorage
        localStorage.setItem('explainer_active_tab', tabHash);
    }
    
    // Tab navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        const tabHash = $(this).attr('href');
        showTab(tabHash);
    });
    
    // Handle tab switching from notice links
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
    
    // Restore active tab on page load
    function restoreActiveTab() {
        const savedTab = localStorage.getItem('explainer_active_tab');
        
        // Check if saved tab exists and is valid
        if (savedTab && $('.nav-tab[href="' + savedTab + '"]').length > 0) {
            showTab(savedTab);
        } else {
            // Default to first tab (basic) if no saved tab or invalid tab
            showTab('#basic');
        }
    }
    
    // Initialize the correct tab on page load
    restoreActiveTab();

    // API key visibility toggle for OpenAI
    $('#toggle-api-key-visibility').on('click', function() {
        const input = $('#explainer_openai_api_key');
        const button = $(this);
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            button.text('Hide');
        } else {
            input.attr('type', 'password');
            button.text('Show');
        }
    });
    
    // API key visibility toggle for Claude
    $('#toggle-claude-key-visibility').on('click', function() {
        const input = $('#explainer_claude_api_key');
        const button = $(this);
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            button.text('Hide');
        } else {
            input.attr('type', 'password');
            button.text('Show');
        }
    });
    
    // Real-time tooltip preview
    function updateTooltipPreview() {
        const bgColor = $('#explainer_tooltip_bg_color').val();
        const textColor = $('#explainer_tooltip_text_color').val();
        const footerColor = $('#explainer_tooltip_footer_color').val();
        
        // Detect site font from paragraph elements
        const siteFont = detectSiteFont();
        
        // Use CSS custom properties for dynamic updates (affects both background and arrow)
        document.documentElement.style.setProperty('--explainer-tooltip-bg-color', bgColor);
        document.documentElement.style.setProperty('--explainer-tooltip-text-color', textColor);
        document.documentElement.style.setProperty('--explainer-tooltip-footer-color', footerColor);
        document.documentElement.style.setProperty('--explainer-site-font', siteFont);
    }
    
    // Detect the site's paragraph font
    function detectSiteFont() {
        // Try to find a paragraph element to get its font
        const paragraph = document.querySelector('p, article p, main p, .content p, .entry-content p, .post-content p');
        
        if (paragraph) {
            const computedStyle = window.getComputedStyle(paragraph);
            const fontFamily = computedStyle.getPropertyValue('font-family');
            
            if (fontFamily && fontFamily !== 'inherit') {
                return fontFamily;
            }
        }
        
        // Fallback: check body font
        const body = document.body;
        if (body) {
            const bodyStyle = window.getComputedStyle(body);
            const bodyFont = bodyStyle.getPropertyValue('font-family');
            
            if (bodyFont && bodyFont !== 'inherit') {
                return bodyFont;
            }
        }
        
        // Final fallback to system font
        return '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
    }
    
    // Real-time button preview
    function updateButtonPreview() {
        const enabledColor = $('#explainer_button_enabled_color').val();
        const disabledColor = $('#explainer_button_disabled_color').val();
        const textColor = $('#explainer_button_text_color').val();
        
        $('#preview-button-enabled').css({
            'background-color': enabledColor,
            'color': textColor
        });
        
        $('#preview-button-disabled').css({
            'background-color': disabledColor,
            'color': textColor
        });
    }
    
    // Helper function to convert hex to rgba
    function hexToRgba(hex, alpha = 1) {
        // Remove # if present
        hex = hex.replace('#', '');
        
        // Handle 3-character hex
        if (hex.length === 3) {
            hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        }
        
        // Convert to RGB
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
        
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }
    
    // Real-time slider preview
    function updateSliderPreview() {
        const trackColorHex = $('#explainer_slider_track_color').val();
        const thumbColor = $('#explainer_slider_thumb_color').val();

        // Skip if elements don't exist (free version)
        if (!trackColorHex || !thumbColor) {
            return;
        }

        // Convert track color to rgba with full opacity
        const trackColor = hexToRgba(trackColorHex, 1.0);

        // Update CSS custom properties
        document.documentElement.style.setProperty('--explainer-slider-track-color', trackColor);
        document.documentElement.style.setProperty('--explainer-slider-thumb-color', thumbColor);

        // Set active label background and border colors with transparency
        const activeLabelBg = hexToRgba(thumbColor, 0.15);
        const activeLabelBorder = hexToRgba(thumbColor, 0.3);
        document.documentElement.style.setProperty('--explainer-active-label-bg', activeLabelBg);
        document.documentElement.style.setProperty('--explainer-active-label-border', activeLabelBorder);
    }

    // Only bind events if elements exist (pro features)
    if ($('#explainer_tooltip_bg_color').length) {
        $('#explainer_tooltip_bg_color, #explainer_tooltip_text_color, #explainer_tooltip_footer_color').on('input', updateTooltipPreview);
        updateTooltipPreview();
    }

    if ($('#explainer_button_enabled_color').length) {
        $('#explainer_button_enabled_color, #explainer_button_disabled_color, #explainer_button_text_color').on('input', updateButtonPreview);
        updateButtonPreview();
    }

    if ($('#explainer_slider_track_color').length) {
        $('#explainer_slider_track_color, #explainer_slider_thumb_color').on('input', updateSliderPreview);
        updateSliderPreview();
    }
    
    // Language change handler
    $('#explainer_language').on('change', function() {
        updatePreviewLanguage();
    });
    
    // Update preview language
    function updatePreviewLanguage() {
        const selectedLanguage = $('#explainer_language').val();
        const selectedProvider = $('#explainer_api_provider').val();
        
        // Define localized strings
        const strings = {
            'en_US': {
                'title': 'Explanation',
                'content': 'This is how your tooltip will look with the selected colours. It matches the actual frontend design with proper spacing and typography.',
                'disclaimer': 'AI-generated content may not always be accurate',
                'powered_by': 'Powered by'
            },
            'en_GB': {
                'title': 'Explanation', 
                'content': 'This is how your tooltip will look with the selected colours. It matches the actual frontend design with proper spacing and typography.',
                'disclaimer': 'AI-generated content may not always be accurate',
                'powered_by': 'Powered by'
            },
            'es_ES': {
                'title': 'Explicación',
                'content': 'Así es como se verá tu tooltip con los colores seleccionados. Coincide con el diseño frontend real con el espaciado y tipografía adecuados.',
                'disclaimer': 'El contenido generado por IA puede no ser siempre preciso',
                'powered_by': 'Desarrollado por'
            },
            'de_DE': {
                'title': 'Erklärung',
                'content': 'So wird Ihr Tooltip mit den ausgewählten Farben aussehen. Es entspricht dem tatsächlichen Frontend-Design mit angemessenen Abständen und Typografie.',
                'disclaimer': 'KI-generierte Inhalte sind möglicherweise nicht immer korrekt',
                'powered_by': 'Unterstützt von'
            },
            'fr_FR': {
                'title': 'Explication',
                'content': 'Voici à quoi ressemblera votre tooltip avec les couleurs sélectionnées. Il correspond au design frontend réel avec un espacement et une typographie appropriés.',
                'disclaimer': 'Le contenu généré par IA peut ne pas toujours être précis',
                'powered_by': 'Propulsé par'
            },
            'hi_IN': {
                'title': 'व्याख्या',
                'content': 'चयनित रंगों के साथ आपका टूलटिप इस तरह दिखेगा। यह उचित स्पेसिंग और टाइपोग्राफी के साथ वास्तविक फ्रंटएंड डिज़ाइन से मेल खाता है।',
                'disclaimer': 'AI-जनरेटेड सामग्री हमेशा सटीक नहीं हो सकती',
                'powered_by': 'द्वारा संचालित'
            },
            'zh_CN': {
                'title': '解释',
                'content': '这是您的工具提示在所选颜色下的外观。它与实际的前端设计相匹配，具有适当的间距和排版。',
                'disclaimer': 'AI生成的内容可能并不总是准确的',
                'powered_by': '技术支持'
            }
        };
        
        // Get strings for selected language, fallback to English
        const langStrings = strings[selectedLanguage] || strings['en_GB'];
        
        // Update preview text
        $('#preview-tooltip-title').text(langStrings.title);
        $('#preview-tooltip-content').text(langStrings.content);
        $('#preview-disclaimer').text(langStrings.disclaimer);
        
        // Update provider text
        let providerName = 'OpenAI'; // Default
        switch(selectedProvider) {
            case 'claude':
                providerName = 'Claude';
                break;
            case 'openrouter':
                providerName = 'OpenRouter';
                break;
            case 'gemini':
                providerName = 'Google Gemini';
                break;
            case 'openai':
            default:
                providerName = 'OpenAI';
                break;
        }
        $('#preview-provider').text(langStrings.powered_by + ' ' + providerName);
    }
    
    // Initialize preview language
    updatePreviewLanguage();
    
    // Update preview when provider changes too
    $('#explainer_api_provider').on('change', function() {
        updatePreviewLanguage();
        switchProviderFields();
    });
    
    // Function to switch provider fields and models based on selection
    function switchProviderFields() {
        const selectedProvider = $('#explainer_api_provider').val();
        const modelSelect = $('#explainer_api_model');
        
        // Hide all provider-specific fields
        $('.api-key-row').hide();
        
        // Clear existing options
        modelSelect.empty();
        
        // Get current saved value
        const currentValue = modelSelect.data('current-value');
        
        // Hide all provider-specific help content
        $('.provider-help').hide();
        
        // Show fields for selected provider and populate models
        let models = [];
        let defaultValue = '';
        
        switch(selectedProvider) {
            case 'openai':
                $('.openai-fields').show();
                $('#openai-help').show();
                models = modelSelect.data('openai-models');
                defaultValue = 'gpt-3.5-turbo';
                break;
            case 'claude':
                $('.claude-fields').show();
                $('#claude-help').show();
                models = modelSelect.data('claude-models');
                defaultValue = 'claude-3-haiku-20240307';
                break;
            case 'openrouter':
                $('.openrouter-fields').show();
                $('#openrouter-help').show();
                models = modelSelect.data('openrouter-models');
                defaultValue = 'qwen/qwen-3-coder-480b:free';
                break;
            case 'gemini':
                $('.gemini-fields').show();
                $('#gemini-help').show();
                models = modelSelect.data('gemini-models');
                defaultValue = 'gemini-1.5-flash';
                break;
        }
        
        // Populate model options
        if (models && models.length > 0) {
            let valueToSelect = defaultValue;
            let foundCurrentValue = false;
            
            // Add all options
            models.forEach(function(model) {
                const option = $('<option></option>')
                    .attr('value', model.value)
                    .text(model.label);
                modelSelect.append(option);
                
                // Check if current saved value exists in this provider's models
                if (model.value === currentValue) {
                    valueToSelect = currentValue;
                    foundCurrentValue = true;
                }
            });
            
            // Set the selected value
            modelSelect.val(valueToSelect);
            
            // Update the data attribute for next time
            if (!foundCurrentValue) {
                modelSelect.data('current-value', valueToSelect);
            }
        }
    }
    
    // Initialize provider fields on page load
    switchProviderFields();
    
    // ==================== POST SCAN FUNCTIONALITY ====================
    
    // Post search functionality
    let searchTimeout;
    const searchInput = $('#post-search-input');
    const searchResults = $('#post-search-results');
    const searchLoading = $('#post-search-loading');
    
    // Debounced search function
    searchInput.on('input', function() {
        const searchTerm = $(this).val().trim();
        
        clearTimeout(searchTimeout);
        
        if (searchTerm.length < 2) {
            searchResults.hide().empty();
            return;
        }
        
        searchTimeout = setTimeout(function() {
            performPostSearch(searchTerm);
        }, 300);
    });
    
    // Perform post search
    function performPostSearch(searchTerm) {
        searchLoading.show();
        searchResults.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            timeout: 10000, // 10 second timeout
            data: {
                action: 'explainer_search_posts',
                search_term: searchTerm,
                nonce: explainerSettings.nonce
            },
            success: function(response) {
                searchLoading.hide();
                
                if (response.success && response.data) {
                    displaySearchResults(response.data);
                } else {
                    searchResults.html('<div class="notice notice-error"><p>' + 
                        (response.data || 'Error searching posts.') + '</p></div>').show();
                }
            },
            error: function(xhr, status, error) {
                searchLoading.hide();
                let errorMessage = 'Network error occurred.';
                
                if (xhr.status === 429) {
                    errorMessage = 'Search rate limit exceeded. Please wait a moment.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Insufficient permissions.';
                } else if (xhr.status === 0) {
                    errorMessage = 'Network connection lost. Please check your connection.';
                }
                
                searchResults.html('<div class="notice notice-error"><p>' + errorMessage + '</p></div>').show();
            }
        });
    }
    
    // Display search results
    function displaySearchResults(posts) {
        if (posts.length === 0) {
            searchResults.html('<div class="notice notice-warning"><p>No posts found matching your search.</p></div>').show();
            return;
        }
        
        let html = '<div class="post-search-results-table">';
        html += '<table class="wp-list-table widefat fixed striped">';
        html += '<thead><tr>';
        html += '<th class="manage-column column-title column-primary">Post Title</th>';
        html += '<th class="manage-column">Status</th>';
        html += '<th class="manage-column">Actions</th>';
        html += '</tr></thead><tbody>';
        
        posts.forEach(function(post) {
            html += '<tr>';
            html += '<td class="column-title column-primary">';
            html += '<strong><a href="' + post.edit_link + '" target="_blank">' + escapeHtml(post.title) + '</a></strong>';
            html += '<div class="row-actions">';
            html += '<span class="edit"><a href="' + post.edit_link + '" target="_blank">Edit</a> | </span>';
            html += '<span class="view"><a href="' + post.permalink + '" target="_blank">View</a></span>';
            html += '</div>';
            html += '</td>';
            html += '<td>' + getStatusBadge(post.status) + '</td>';
            html += '<td>' + getActionButton(post) + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table></div>';
        searchResults.html(html).show();
    }
    
    // Get status badge HTML
    function getStatusBadge(status) {
        const badges = {
            'not_scanned': '<span class="post-status-badge status-not-scanned">Not Scanned</span>',
            'queued': '<span class="post-status-badge status-queued">Queued for Scan</span>',
            'processing': '<span class="post-status-badge status-processing">Scanning</span>',
            'processed': '<span class="post-status-badge status-processed">Already Scanned</span>',
            'outdated': '<span class="post-status-badge status-outdated">Outdated Scan</span>',
            'error': '<span class="post-status-badge status-error">Scan Failed</span>'
        };
        
        return badges[status] || badges['not_scanned'];
    }
    
    // Get action button HTML
    function getActionButton(post) {
        const buttons = {
            'not_scanned': '<button type="button" class="btn-base btn-primary btn-sm process-post-btn" data-post-id="' + post.id + '">Process Post</button>',
            'queued': '<button type="button" class="btn-base btn-secondary btn-sm" disabled>Queued</button>',
            'processing': '<button type="button" class="btn-base btn-secondary btn-sm" disabled>Processing</button>',
            'processed': '<button type="button" class="btn-base btn-primary btn-sm process-post-btn" data-post-id="' + post.id + '">Re-process Post</button>',
            'outdated': '<button type="button" class="btn-base btn-primary btn-sm process-post-btn" data-post-id="' + post.id + '">Re-process Post</button>',
            'error': '<button type="button" class="btn-base btn-primary btn-sm process-post-btn" data-post-id="' + post.id + '">Retry Scan</button>'
        };
        
        return buttons[post.status] || buttons['not_scanned'];
    }
    
    // Process post button handler
    $(document).on('click', '.process-post-btn', function() {
        const button = $(this);
        const postId = button.data('post-id');
        const originalText = button.text();
        
        button.prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'explainer_process_post',
                post_id: postId,
                nonce: explainerSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    button.removeClass('button-primary').addClass('button-secondary')
                          .text('Queued').prop('disabled', true);
                    
                    // Update status badge
                    button.closest('tr').find('.post-status-badge')
                          .removeClass().addClass('post-status-badge status-queued').text('Queued');
                    
                    // Show success message
                    showAdminNotice('Post queued for processing successfully.', 'success');
                    
                    // Refresh the post scan job queue panel if it exists
                    if (window.SharedJobQueue && $('#post-scan-job-status-panel').length > 0) {
                        window.SharedJobQueue.loadJobs('post-scan-job-status-panel');
                    }
                    
                    // Refresh processed posts table
                    loadProcessedPosts();
                } else {
                    button.prop('disabled', false).text(originalText);
                    showAdminNotice(response.data || 'Error processing post.', 'error');
                }
            },
            error: function() {
                button.prop('disabled', false).text(originalText);
                showAdminNotice('Network error occurred.', 'error');
            }
        });
    });
    
    // View terms button handler
    $(document).on('click', '.view-terms-btn', function() {
        const postId = $(this).data('post-id');
        showTermsModal(postId);
    });
    
    // Show terms modal
    function showTermsModal(postId) {
        const modal = $('#view-terms-modal');
        const termsLoading = $('#terms-loading');
        const termsContent = $('#terms-content');
        
        modal.show();
        termsLoading.show();
        termsContent.empty();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'explainer_get_post_terms',
                post_id: postId,
                nonce: explainerSettings.nonce
            },
            success: function(response) {
                termsLoading.hide();
                
                if (response.success && response.data && response.data.length > 0) {
                    displayTerms(response.data);
                } else {
                    termsContent.html('<div class="notice notice-info"><p>No terms found for this post.</p></div>');
                }
            },
            error: function(xhr, status, error) {
                termsLoading.hide();
                termsContent.html('<div class="notice notice-error"><p>Network error occurred.</p></div>');
            }
        });
    }
    
    // Display terms in modal
    function displayTerms(terms) {
        const termsContent = $('#terms-content');
        
        if (terms.length === 0) {
            termsContent.html('<p>No terms found for this post.</p>');
            return;
        }
        
        let html = '<table class="wp-list-table widefat fixed striped">';
        html += '<thead><tr>';
        html += '<th class="manage-column">Term</th>';
        html += '<th class="manage-column">Explanation</th>';
        html += '<th class="manage-column">Added</th>';
        html += '</tr></thead><tbody>';
        
        terms.forEach(function(term) {
            html += '<tr>';
            html += '<td><strong>' + escapeHtml(term.selected_text || '') + '</strong></td>';
            
            // Ensure we have a string for the explanation
            const explanation = term.explanation || 'No explanation available';
            html += '<td>' + escapeHtml(String(explanation)) + '</td>';
            
            html += '<td>' + (term.created_at || '') + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        termsContent.html(html);
    }
    
    // Modal close handlers
    $(document).on('click', '.explainer-modal-close', function(e) {
        $(this).closest('.explainer-modal').hide();
    });
    
    // Close modal when clicking on backdrop
    $(document).on('click', '.explainer-modal', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // Load processed posts on page load and tab switch
    function loadProcessedPosts(page = 1, sortBy = 'processed_date', sortOrder = 'desc') {
        const container = $('#processed-posts-container');
        const loading = $('#processed-posts-loading');
        
        // Check tab status
        const postScanTab = $('.nav-tab[href="#post-scan"]');
        const isActive = postScanTab.hasClass('nav-tab-active');
        console.log('Tab status check:', {
            tabExists: postScanTab.length > 0,
            hasActiveClass: isActive,
            allClasses: postScanTab.attr('class')
        });
        
        // Only load if post-scan tab is active
        if (!isActive) {
            console.log('Post scan tab not active, skipping load');
            return;
        }
        
        console.log('Loading processed posts...');
        
        loading.show();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'explainer_get_processed_posts_paginated',
                page: page,
                per_page: 20,
                orderby: sortBy === 'processed_date' ? 'processed_date' : sortBy,
                order: sortOrder,
                nonce: wpAiExplainer.nonce
            },
            success: function(response) {
                loading.hide();
                
                if (response.success && response.data) {
                    // Convert paginated response format to match existing display function
                    var adaptedData = {
                        posts: response.data.items,
                        pagination: response.data.pagination_html,
                        total: response.data.pagination ? response.data.pagination.total_items : 0,
                        page: response.data.pagination ? response.data.pagination.current_page : 1,
                        total_pages: response.data.pagination ? response.data.pagination.total_pages : 1
                    };
                    displayProcessedPosts(adaptedData);
                } else {
                    var errorMsg = response && response.data ? response.data : 'Error loading processed posts.';
                    $('#processed-posts-tbody').html('<tr><td colspan="4">' + errorMsg + '</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                loading.hide();
                console.error('AJAX Error:', { xhr: xhr, status: status, error: error });
                $('#processed-posts-tbody').html('<tr><td colspan="4">Network error occurred.</td></tr>');
            }
        });
    }
    
    // Display processed posts
    function displayProcessedPosts(data) {
        const tbody = $('#processed-posts-tbody');
        const pagination = $('#processed-posts-pagination');
        
        if (data.posts.length === 0) {
            tbody.html('<tr><td colspan="4">No processed posts found.</td></tr>');
            pagination.empty();
            return;
        }
        
        let html = '';
        data.posts.forEach(function(post) {
            html += '<tr>';
            html += '<td class="column-title column-primary">';
            html += '<strong><a href="' + post.edit_link + '" target="_blank">' + escapeHtml(post.title) + '</a></strong>';
            html += '<div class="row-actions">';
            html += '<span class="edit"><a href="' + post.edit_link + '" target="_blank">Edit</a> | </span>';
            html += '<span class="view"><a href="' + post.view_link + '" target="_blank">View Post</a></span>';
            html += '</div>';
            html += '</td>';
            html += '<td>' + post.processed_date + '</td>';
            html += '<td>' + post.term_count + '</td>';
            html += '<td><button type="button" class="btn-base btn-secondary btn-sm view-terms-btn" data-post-id="' + post.id + '">View Terms</button></td>';
            html += '</tr>';
        });
        
        tbody.html(html);
        
        // Update pagination
        if (data.pagination) {
            pagination.html(data.pagination);
        }
    }
    
    // Sort functionality
    $(document).on('click', '.sort-link', function(e) {
        e.preventDefault();
        
        const sortBy = $(this).data('sort');
        let sortOrder = 'asc';
        
        // Toggle sort order if clicking same column
        if ($(this).hasClass('sorted-asc')) {
            sortOrder = 'desc';
        } else if ($(this).hasClass('sorted-desc')) {
            sortOrder = 'asc';
        }
        
        // Remove existing sort classes
        $('.sort-link').removeClass('sorted-asc sorted-desc');
        
        // Add sort class
        $(this).addClass('sorted-' + sortOrder);
        
        // Load posts with new sort
        loadProcessedPosts(1, sortBy, sortOrder);
    });
    
    // Tab switch handler for post scan
    $('.nav-tab[href="#post-scan"]').on('click', function() {
        console.log('Post scan tab clicked!');
        
        // Refresh the post scan job queue if panel exists
        if (window.SharedJobQueue && $('#post-scan-job-status-panel').length > 0) {
            window.SharedJobQueue.loadJobs('post-scan-job-status-panel');
        }
        
        // Load processed posts when post-scan tab is activated
        setTimeout(function() {
            console.log('About to call loadProcessedPosts after timeout');
            loadProcessedPosts();
        }, 100);
    });
    
    // Tab switch handler for popular selections (explanations job queue)
    $('.nav-tab[href="#popular"]').on('click', function() {
        console.log('Popular selections tab clicked!');
        
        // Refresh the explanations job queue if panel exists
        if (window.SharedJobQueue && $('#explainer-job-status-panel').length > 0) {
            window.SharedJobQueue.loadJobs('explainer-job-status-panel');
        }
    });
    
    // Utility functions
    function escapeHtml(text) {
        if (text == null || text == undefined) {
            return '';
        }
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function showAdminNotice(message, type = 'info') {
        const notices = $('#admin-messages');
        const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        notices.html(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }
    
    // Initialize processed posts if Post Scan tab is active on page load
    setTimeout(function() {
        if ($('.nav-tab[href="#post-scan"]').hasClass('nav-tab-active')) {
            console.log('Post Scan tab active on page load, loading processed posts');
            loadProcessedPosts();
        }
    }, 250); // Slight delay to ensure DOM is ready
    
    // Expose loadProcessedPosts globally for use by SharedJobQueue
    window.loadProcessedPosts = loadProcessedPosts;
    
    // ===================================================================
    // PAGINATION INTEGRATION
    // ===================================================================
    
    // Initialize pagination controllers when they become available
    $(document).ready(function() {
        // Wait for ExplainerPlugin.PaginationController to be available
        function initializePaginationControllers() {
            if (typeof window.ExplainerPlugin === 'undefined' || 
                typeof window.ExplainerPlugin.PaginationController === 'undefined') {
                setTimeout(initializePaginationControllers, 100);
                return;
            }
            
            // Initialize Popular Selections pagination
            if ($('#popular-tab').length) {
                window.popularSelectionsPagination = new window.ExplainerPlugin.PaginationController({
                    container: '#selections-table-container',
                    paginationContainer: '#selections-pagination',
                    loadingSelector: '#selections-loading',
                    tableBodySelector: '#selections-table-body',
                    searchSelector: '#selections-search',
                    ajaxAction: 'explainer_get_popular_selections',
                    nonce: wpAiExplainer.nonce,
                    perPage: 20,
                    orderBy: 'count',
                    order: 'desc',
                    onAfterLoad: function(data) {
                        // Update summary
                        const summary = data.pagination.total_items > 0 
                            ? `Showing ${data.items.length} of ${data.pagination.total_items} selections`
                            : 'No selections found';
                        $('#selections-summary').text(summary);
                    }
                });
                
                // Override updateTableContent for popular selections
                window.popularSelectionsPagination.updateTableContent = function(items) {
                    const tbody = $('#selections-table-body');
                    if (!items || items.length === 0) {
                        tbody.html('<tr><td colspan="5" style="text-align: center; padding: 20px;">No text selections found.</td></tr>');
                        return;
                    }
                    
                    let html = '';
                    items.forEach(function(item) {
                        html += `<tr>
                            <td><strong>${escapeHtml(item.selected_text)}</strong><br>
                                <small style="color: #666;">${escapeHtml(item.url || 'Unknown page')}</small>
                            </td>
                            <td style="text-align: center;"><strong>${item.count}</strong></td>
                            <td>${formatDate(item.first_seen)}</td>
                            <td>${formatDate(item.last_seen || item.first_seen)}</td>
                            <td>
                                <button type="button" class="btn-base btn-primary btn-sm create-blog-post-btn" 
                                        data-text="${escapeHtml(item.selected_text)}" 
                                        ${!window.explainerHasApiKeys ? 'disabled' : ''}>
                                    Create Blog Post
                                </button>
                            </td>
                        </tr>`;
                    });
                    tbody.html(html);
                };
            }
            
            // Initialize Processed Posts pagination
            if ($('#post-scan-tab').length) {
                window.processedPostsPagination = new window.ExplainerPlugin.PaginationController({
                    container: '#processed-posts-container',
                    paginationContainer: '#processed-posts-pagination',
                    loadingSelector: '#processed-posts-loading',
                    tableBodySelector: '#processed-posts-tbody',
                    ajaxAction: 'explainer_get_processed_posts_paginated',
                    nonce: wpAiExplainer.nonce,
                    perPage: 20,
                    orderBy: 'processed_date',
                    order: 'desc',
                });
                
                // Override updateTableContent for processed posts
                window.processedPostsPagination.updateTableContent = function(items) {
                    const tbody = $('#processed-posts-tbody');
                    if (!items || items.length === 0) {
                        tbody.html('<tr><td colspan="4" style="text-align: center; padding: 20px;">No processed posts found.</td></tr>');
                        return;
                    }
                    
                    let html = '';
                    items.forEach(function(item) {
                        const editUrl = `/wp-admin/post.php?post=${item.id}&action=edit`;
                        html += `<tr>
                            <td class="column-primary">
                                <strong><a href="${editUrl}" target="_blank">${escapeHtml(item.title)}</a></strong>
                            </td>
                            <td>${formatDate(item.processed_date)}</td>
                            <td style="text-align: center;">${item.term_count}</td>
                            <td>
                                <button type="button" class="btn-base btn-secondary btn-sm view-terms-btn" 
                                        data-post-id="${item.id}" data-post-title="${escapeHtml(item.title)}">
                                    View Terms
                                </button>
                            </td>
                        </tr>`;
                    });
                    tbody.html(html);
                };
                
                // Replace the original loadProcessedPosts function
                window.loadProcessedPosts = function(page = 1, sortBy = 'processed_date', sortOrder = 'desc') {
                    if (window.processedPostsPagination) {
                        window.processedPostsPagination.loadPage(page, sortBy, sortOrder);
                    }
                };
            }
        }
        
        // Start initialization
        initializePaginationControllers();
    });
    
    // Helper functions for table content formatting
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
    }
    
    function formatDate(dateString) {
        if (!dateString) return 'Unknown';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        } catch (e) {
            return dateString;
        }
    }

    // ===================================================================
    // UPGRADE TAB SCROLL ANIMATIONS
    // ===================================================================

    // Initialize Intersection Observer for fade-in animations
    function initScrollAnimations() {
        // Check if Intersection Observer is supported
        if (!('IntersectionObserver' in window)) {
            // Fallback: show all elements immediately
            $('.fade-in-up').addClass('is-visible');
            return;
        }

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    $(entry.target).addClass('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        // Observe all fade-in-up elements in the upgrade tab
        $('#upgrade-tab .fade-in-up').each(function() {
            observer.observe(this);
        });
    }

    // Initialize scroll animations when upgrade tab is shown
    $('.nav-tab[href="#upgrade"]').on('click', function() {
        setTimeout(function() {
            initScrollAnimations();
        }, 50);
    });

    // Initialize on page load if upgrade tab is active
    if ($('.nav-tab[href="#upgrade"]').hasClass('nav-tab-active')) {
        initScrollAnimations();
    }

    // ===================================================================
    // ADVANCED CONTENT OPTIONS TOGGLE
    // ===================================================================

    // Toggle visibility of advanced content area controls
    $('#toggle-advanced-content-options').on('click', function() {
        const $button = $(this);
        const $rows = $('.advanced-content-options');
        const $toggleRow = $('.advanced-content-options-toggle');

        // Toggle the rows
        $rows.slideToggle(200);

        // Toggle expanded class on parent row for CSS styling
        $toggleRow.toggleClass('expanded');

        // Toggle button text
        const currentText = $button.text().trim();
        if (currentText.includes('Show')) {
            $button.text('Hide advanced content area controls');
        } else {
            $button.text('Show advanced content area controls');
        }
    });

    // ===================================================================
    // SETUP MODE (AUTO-DETECT CONTENT AREA)
    // ===================================================================

    // Toggle setup mode button (enable/disable)
    $('#toggle-setup-mode').on('click', function() {
        const $button = $(this);
        const isActive = $button.data('active') === 1;
        const originalText = $button.text();
        const action = isActive ? 'explainer_disable_setup_mode' : 'explainer_enable_setup_mode';

        $button.prop('disabled', true).text(isActive ? 'Disabling...' : 'Enabling...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: action,
                nonce: explainerSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (isActive) {
                        // Disabled setup mode
                        $button.removeClass('btn-secondary').addClass('btn-primary');
                        $button.text('Enable Setup Mode');
                        $button.data('active', 0);
                        $('#setup-mode-status').hide();
                        $('#setup-mode-message').html('');
                        showAdminNotice('Setup mode disabled.', 'success');
                    } else {
                        // Enabled setup mode
                        $button.removeClass('btn-primary').addClass('btn-secondary');
                        $button.text('Disable Setup Mode');
                        $button.data('active', 1);
                        $('#setup-mode-status').show();

                        // Build message with link
                        const message = response.data.message + ' <a href="' + response.data.post_url + '" style="text-decoration: underline;">Visit your website</a> to select text from a post or page.';
                        $('#setup-mode-message').html(message);

                        showAdminNotice(response.data.message, 'success');
                    }

                    $button.prop('disabled', false);
                } else {
                    $button.prop('disabled', false).text(originalText);
                    showAdminNotice(response.data || 'Error toggling setup mode.', 'error');
                }
            },
            error: function() {
                $button.prop('disabled', false).text(originalText);
                showAdminNotice('Network error occurred.', 'error');
            }
        });
    });

    // ===================================================================
    // SETUP MODE - AUTO-EXPAND AFTER REDIRECT
    // ===================================================================

    // Check if redirected after setup mode completion
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('setup_complete') === '1') {
        // Auto-expand advanced content options if not already visible
        const $advancedRows = $('.advanced-content-options');
        if (!$advancedRows.is(':visible')) {
            $('#toggle-advanced-content-options').trigger('click');
        }

        // Add success message below the included areas textarea
        const $includedTextarea = $('#explainer_included_selectors');
        if ($includedTextarea.length) {
            // Remove any existing setup success message
            $includedTextarea.siblings('.setup-success-message').remove();

            // Create and insert success message
            const $message = $('<p class="setup-success-message" style="color: #46b450; margin-top: 8px; font-weight: 500;">Your selector was saved.</p>');
            $includedTextarea.after($message);

            // Auto-remove message after 5 seconds
            setTimeout(function() {
                $message.fadeOut(400, function() {
                    $(this).remove();
                });
            }, 5000);
        }

        // Clean up URL (remove setup_complete parameter)
        const cleanUrl = window.location.pathname + '?' +
            Array.from(urlParams.entries())
                .filter(([key]) => key !== 'setup_complete')
                .map(([key, value]) => `${key}=${value}`)
                .join('&');
        window.history.replaceState({}, '', cleanUrl);
    }

});