/**
 * Blog Creator Modal JavaScript
 * 
 * Handles modal interactions, cost estimation, and blog post creation
 */

(function($) {
    'use strict';
    
    /**
     * Blog Creator namespace
     */
    window.ExplainerBlogCreator = {
        
        /**
         * Current selection data
         */
        currentSelection: null,
        
        /**
         * Request in progress flag
         */
        isCreatingPost: false,
        
        /**
         * Initialization flag to prevent duplicate initialization
         */
        isInitialized: false,
        
        /**
         * Real-time system
         */
        realtimeAdapter: null,
        isRealtimeEnabled: false,
        subscribedTopics: new Set(),
        currentJobId: null,
        
        /**
         * Initialize the blog creator
         */
        init: function() {
            // Prevent duplicate initialization
            if (this.isInitialized) {
                if (window.ExplainerDebug) {
                    window.ExplainerDebug.log('blog-creator', 'Already initialized, skipping duplicate init');
                }
                return;
            }
            
            // Initialize real-time system
            this.initializeRealtimeSystem();
            
            this.bindEvents();
            this.isInitialized = true;
            if (window.ExplainerDebug) {
                window.ExplainerDebug.log('blog-creator', 'Blog creator initialized');
            }
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Modal open/close events - use namespaced events to prevent duplicates
            $(document).off('click.blogcreator', '.create-blog-post-btn').on('click.blogcreator', '.create-blog-post-btn', function(e) {
                e.preventDefault();
                
                // Check if button is disabled
                if ($(this).prop('disabled')) {
                    return false;
                }
                
                var selectionData = $(this).data('selection');
                self.openModal(selectionData);
            });
            
            // Delete selection button handler (only for buttons within blog creation context)
            $(document).off('click.blogcreator', '.delete-selection-btn').on('click.blogcreator', '.delete-selection-btn', function(e) {
                // Only handle if this is within a blog creation context (modal open or specific container)
                if (!$('#blog-creation-modal').is(':visible')) {
                    return; // Let other handlers deal with it
                }
                
                e.preventDefault();
                
                var $button = $(this);
                var selectionId = $button.data('id');
                
                if (!selectionId) {
                    return;
                }
                
                // Confirm deletion
                if (window.ExplainerPlugin?.replaceConfirm) {
                    window.ExplainerPlugin.replaceConfirm('Are you sure you want to delete this selection? This action cannot be undone.', {
                        type: 'warning',
                        confirmText: 'Delete',
                        cancelText: 'Cancel'
                    }).then((confirmed) => {
                        if (confirmed) {
                            self.deleteSelection(selectionId, $button);
                        }
                    });
                } else {
                    if (confirm('Are you sure you want to delete this selection? This action cannot be undone.')) {
                        self.deleteSelection(selectionId, $button);
                    }
                }
            });
            
            $('#close-blog-modal, #cancel-creation, #modal-backdrop').off('click.blogcreator').on('click.blogcreator', function(e) {
                e.preventDefault();
                self.closeModal();
            });
            
            
            // Form submission - unbind first to prevent duplicate handlers
            $('#create-post-submit').off('click.blogcreator').on('click.blogcreator', function(e) {
                e.preventDefault();
                self.createBlogPost();
            });
            
            // Escape key closes modal - use namespaced events to prevent duplicates
            $(document).off('keydown.blogcreator').on('keydown.blogcreator', function(e) {
                if (e.keyCode === 27 && $('#blog-creation-modal').is(':visible')) {
                    self.closeModal();
                }
            });
            
            // Prevent modal close when clicking inside modal content - use namespaced events
            $('.modal-content').off('click.blogcreator').on('click.blogcreator', function(e) {
                e.stopPropagation();
            });
        },
        
        /**
         * Open the blog creation modal
         * 
         * @param {Object} selectionData Selection data from popular selections table
         */
        openModal: function(selectionData) {
            console.log('[BLOG CREATOR DEBUG] Opening modal with selection data:', selectionData);
            console.log('[BLOG CREATOR DEBUG] Type of selectionData:', typeof selectionData);
            console.log('[BLOG CREATOR DEBUG] selectionData.selected_text:', selectionData ? selectionData.selected_text : 'selectionData is null/undefined');
            
            // Handle case where selectionData might be a string that needs parsing
            if (typeof selectionData === 'string') {
                try {
                    selectionData = JSON.parse(selectionData);
                    console.log('[BLOG CREATOR DEBUG] Parsed string data to object:', selectionData);
                } catch(e) {
                    console.error('[BLOG CREATOR DEBUG] Failed to parse selection data string:', e);
                    this.showError('Invalid selection data format.');
                    return;
                }
            }
            
            if (!selectionData || !selectionData.selected_text) {
                console.error('[BLOG CREATOR DEBUG] Invalid selection data provided:', selectionData);
                this.showError('Invalid selection data provided.');
                return;
            }
            
            this.currentSelection = selectionData;
            console.log('[BLOG CREATOR DEBUG] Current selection set:', this.currentSelection);
            
            // Populate modal with selection data
            $('#modal-selection-preview').text(selectionData.text_preview || selectionData.selected_text);
            $('#modal-selection-text').val(selectionData.selected_text);
            console.log('[BLOG CREATOR DEBUG] Modal populated with text:', selectionData.selected_text.substring(0, 100) + '...');
            
            // Reset form state
            this.resetModal();
            console.log('[BLOG CREATOR DEBUG] Modal form reset completed');
            
            // Load explanation for this selection and populate prompt when ready
            this.loadExplanationAndPopulatePrompt(selectionData);
            
            // Show modal
            $('#blog-creation-modal, #modal-backdrop').fadeIn(300);
            console.log('[BLOG CREATOR DEBUG] Modal displayed');
            
            // Focus on prompt textarea
            $('#content-prompt').focus();
            
            console.log('[BLOG CREATOR DEBUG] Modal initialization completed');
        },
        
        /**
         * Close the blog creation modal
         */
        closeModal: function() {
            // Clear any running progress simulation
            this.clearProgressSimulation();
            
            // Clean up real-time subscriptions
            this.cleanup();
            
            $('#blog-creation-modal, #modal-backdrop').fadeOut(300);
            this.currentSelection = null;
            this.currentExplanation = null; // Clear stored explanation
            this.resetModal();
        },
        
        /**
         * Reset modal to initial state
         */
        resetModal: function() {
            // Clear any running progress simulation and reset flags
            this.clearProgressSimulation();
            this.isCreatingPost = false;
            this.progressComplete = false;
            
            // Hide progress and messages
            $('#blog-progress, #modal-messages').hide();
            
            // Show form options
            $('#generation-options').show();
            
            // Reset form
            $('#blog-creation-form')[0].reset();
            
            // Set default values
            $('#post-length-select').val('medium');
            $('#generate-seo').prop('checked', true);
            $('#image-size-select').val('none');
            // Don't override the textarea value - let it use the default from the template
            
            // Re-enable submit button and restore text
            $('#create-post-submit').prop('disabled', false).text('Create Blog Post').show();
            
            // Reset Cancel button text
            $('#cancel-creation').text('Cancel');
            
            // Clear messages
            $('#message-content, #message-actions').empty().hide();
            
            // Hide post success details
            $('#post-success-details').hide();
            
            // Reset explanation section
            $('#explanation-section').hide();
            $('#explanation-loading, #explanation-display, #explanation-error').hide();
            $('#explanation-display').empty();
            $('#explanation-error').empty();
            
            // Reset progress bar
            this.updateProgress(0, '');
        },
        
        /**
         * Update modal buttons after form submission
         */
        updateModalButtons: function() {
            // Change Cancel button to Close
            $('#cancel-creation').text('Close');
            
            // Hide the Creating... button (which was the submit button)
            $('#create-post-submit').hide();
        },
        
        /**
         * Load AI explanation for a selection and populate the prompt with actual content
         * 
         * @param {Object} selectionData Selection data from popular selections table
         */
        loadExplanationAndPopulatePrompt: function(selectionData) {
            console.log('[BLOG CREATOR DEBUG] Loading explanation and populating prompt for selection:', selectionData);
            
            if (!selectionData || !selectionData.id) {
                console.log('[BLOG CREATOR DEBUG] No selection data or ID provided, populating prompt with fallback');
                this.populatePromptWithContent(selectionData.selected_text, 'A concept that requires further explanation and context.');
                return;
            }
            
            // Show explanation section and loading state
            $('#explanation-section').show();
            $('#explanation-loading').show();
            $('#explanation-display, #explanation-error').hide();
            
            var self = this;
            
            // Make AJAX request to get explanation
            $.post(ajaxurl, {
                action: 'explainer_get_selection_explanation',
                nonce: $('input[name="nonce"]').val(),
                selection_id: selectionData.id
            })
            .done(function(response) {
                console.log('[BLOG CREATOR DEBUG] Explanation response:', response);
                $('#explanation-loading').hide();
                
                var explanation = 'A concept that requires further explanation and context.'; // fallback
                
                if (response.success && response.data.explanation) {
                    explanation = response.data.explanation;
                    $('#explanation-display').html('<p>' + explanation + '</p>').show();
                    console.log('[BLOG CREATOR DEBUG] Explanation loaded successfully');
                } else {
                    $('#explanation-error').text('No explanation available for this selection.').show();
                    console.log('[BLOG CREATOR DEBUG] No explanation available, using fallback');
                }
                
                // Populate prompt with actual content
                self.populatePromptWithContent(selectionData.selected_text, explanation);
            })
            .fail(function(xhr) {
                console.error('[BLOG CREATOR DEBUG] Failed to load explanation:', xhr);
                $('#explanation-loading').hide();
                $('#explanation-error').text('Failed to load explanation.').show();
                
                // Populate prompt with fallback explanation
                self.populatePromptWithContent(selectionData.selected_text, 'A concept that requires further explanation and context.');
            });
        },
        
        /**
         * Populate the prompt textarea with actual content instead of placeholders
         * 
         * @param {string} selectedText The selected text
         * @param {string} explanation The explanation for the selected text
         */
        populatePromptWithContent: function(selectedText, explanation) {
            console.log('[BLOG CREATOR DEBUG] Populating prompt with actual content');
            
            // Store the explanation for use in form submission
            this.currentExplanation = explanation;
            console.log('[BLOG CREATOR DEBUG] Stored explanation for form submission:', explanation);
            
            // Get the current prompt template
            var promptTemplate = $('#content-prompt').val();
            
            // Get WordPress language from PHP (if available) or default to 'English'
            var wpLang = 'English'; // Default fallback
            if (typeof explainer_blog_creator_vars !== 'undefined' && explainer_blog_creator_vars.wp_language) {
                wpLang = explainer_blog_creator_vars.wp_language;
            }
            
            // Replace placeholders with actual content
            var populatedPrompt = promptTemplate
                .replace(/\{\{selectedtext\}\}/g, selectedText)
                .replace(/\{\{explanation\}\}/g, explanation)
                .replace(/\{\{wplang\}\}/g, wpLang);
            
            // Update the textarea with populated content
            $('#content-prompt').val(populatedPrompt);
            
            console.log('[BLOG CREATOR DEBUG] Prompt populated with actual content');
            console.log('[BLOG CREATOR DEBUG] Selected text length:', selectedText.length);
            console.log('[BLOG CREATOR DEBUG] Explanation length:', explanation.length);
            console.log('[BLOG CREATOR DEBUG] Final prompt length:', populatedPrompt.length);
        },
        
        
        /**
         * Create blog post
         */
        createBlogPost: function() {
            var self = this;
            console.log('[BLOG CREATOR DEBUG] Starting blog post creation process');
            
            // Prevent duplicate requests
            if (this.isCreatingPost) {
                console.log('[BLOG CREATOR DEBUG] Request already in progress, ignoring duplicate');
                return;
            }
            
            // Set request in progress flag immediately
            this.isCreatingPost = true;
            
            // Validate form
            if (!this.validateForm()) {
                console.error('[BLOG CREATOR DEBUG] Form validation failed');
                this.isCreatingPost = false; // Reset flag on validation failure
                return;
            }
            console.log('[BLOG CREATOR DEBUG] Form validation passed');
            
            // Disable submit button and add visual feedback
            $('#create-post-submit').prop('disabled', true).text('Creating...');
            
            // Hide form options and show progress
            $('#generation-options').hide();
            $('#blog-progress').show();
            
            // Reset progress
            console.log('[BLOG CREATOR DEBUG] Setting initial progress');
            this.updateProgress(0, 'Preparing content generation...');
            
            // Prepare form data
            var formData = {
                action: 'explainer_create_blog_post',
                nonce: $('input[name="nonce"]').val(),
                selection_text: $('#modal-selection-text').val(),
                explanation: this.currentExplanation || '',
                content_prompt: $('#content-prompt').val(),
                post_length: $('#post-length-select').val(),
                ai_provider: $('#ai-provider-hidden').val(),
                image_size: $('#image-size-select').val(),
                generate_seo: $('#generate-seo').is(':checked') ? 1 : 0,
                selection_id: this.currentSelection && this.currentSelection.id ? this.currentSelection.id : null
            };
            
            console.log('[BLOG CREATOR DEBUG] Form data prepared:', {
                action: formData.action,
                selection_text_length: formData.selection_text.length,
                content_prompt_length: formData.content_prompt.length,
                post_length: formData.post_length,
                ai_provider: formData.ai_provider,
                image_size: formData.image_size,
                generate_seo: formData.generate_seo,
                nonce_present: !!formData.nonce
            });
            
            // Simulate progress during generation  
            console.log('[BLOG CREATOR DEBUG] Starting progress simulation');
            this.simulateProgress();
            
            // Submit form
            console.log('[BLOG CREATOR DEBUG] Sending AJAX request to:', ajaxurl);
            $.post(ajaxurl, formData)
                .done(function(response) {
                    console.log('[BLOG CREATOR DEBUG] AJAX request completed successfully:', response);
                    
                    // Clear progress simulation
                    self.clearProgressSimulation();
                    self.updateProgress(100, 'Complete!');
                    
                    setTimeout(function() {
                        // Reset request flag
                        self.isCreatingPost = false;
                        
                        if (response.success) {
                            console.log('[BLOG CREATOR DEBUG] Blog post creation successful:', response.data);
                            
                            // Check if job was queued for background processing or completed synchronously
                            if (response.data.queued) {
                                console.log('[BLOG CREATOR DEBUG] Job queued for background processing:', response.data.job_id);
                                self.showBackgroundQueued(response.data);
                            } else {
                                console.log('[BLOG CREATOR DEBUG] Blog post created synchronously');
                                self.showSuccess(response.data);
                            }
                        } else {
                            console.error('[BLOG CREATOR DEBUG] Blog post creation failed:', response.data);
                            self.showError(response.data.message || 'Unknown error occurred');
                        }
                    }, 500);
                })
                .fail(function(xhr) {
                    console.error('[BLOG CREATOR DEBUG] AJAX request failed:', xhr);
                    
                    // Clear progress simulation and show error
                    self.clearProgressSimulation();
                    self.updateProgress(0, 'Error occurred');
                    
                    var errorMessage = 'Network error occurred. Please try again.';
                    try {
                        var errorData = JSON.parse(xhr.responseText);
                        console.error('[BLOG CREATOR DEBUG] Error response data:', errorData);
                        if (errorData.data && errorData.data.message) {
                            errorMessage = errorData.data.message;
                        }
                    } catch (e) {
                        console.error('[BLOG CREATOR DEBUG] Failed to parse error response:', e);
                    }
                    
                    setTimeout(function() {
                        // Reset request flag
                        self.isCreatingPost = false;
                        self.showError(errorMessage);
                    }, 500);
                });
        },
        
        /**
         * Validate form before submission
         * 
         * @return {boolean} True if valid
         */
        validateForm: function() {
            var selectionText = $('#modal-selection-text').val();
            var contentPrompt = $('#content-prompt').val();
            
            if (!selectionText || selectionText.trim().length === 0) {
                this.showError('Selection text is required.');
                return false;
            }
            
            if (!contentPrompt || contentPrompt.trim().length === 0) {
                this.showError('Content prompt is required.');
                return false;
            }
            
            if (contentPrompt.length > 5000) {
                this.showError('Content prompt cannot exceed 5000 characters.');
                return false;
            }
            
            return true;
        },
        
        /**
         * Simulate progress during blog post creation
         */
        simulateProgress: function() {
            var self = this;
            
            // Check if image generation is enabled
            var generateImage = $('#image-size-select').val() !== 'none';
            var generateSEO = $('#generate-seo').is(':checked');
            
            // Build dynamic stages based on what's enabled
            var stages = [
                { progress: 20, text: 'Connecting to AI provider...', delay: 400 },
                { progress: 50, text: 'Queuing for processing...', delay: 600 }
            ];
            
            // Add image generation stage if enabled (for user feedback, even though it's queued)
            if (generateImage) {
                stages.push({ progress: 70, text: 'Image generation queued...', delay: 400 });
            }
            
            // Add SEO stage if enabled
            if (generateSEO) {
                stages.push({ progress: generateImage ? 80 : 70, text: 'SEO generation queued...', delay: 400 });
            }
            
            // Final stages - these happen quickly for queued jobs
            stages.push({ progress: 95, text: 'Finalising...', delay: 200 });
            
            console.log('[BLOG CREATOR DEBUG] Progress stages:', stages);
            
            var currentStage = 0;
            var progressInterval;
            
            // Store the interval ID so we can clear it later
            this.progressInterval = null;
            
            function runNextStage() {
                if (currentStage < stages.length && !self.progressComplete) {
                    var stage = stages[currentStage];
                    console.log('[BLOG CREATOR DEBUG] Running stage:', currentStage, stage);
                    self.updateProgress(stage.progress, stage.text);
                    currentStage++;
                    
                    // Schedule next stage with dynamic delay
                    self.progressInterval = setTimeout(runNextStage, stage.delay);
                }
            }
            
            // Start the progress simulation immediately, then begin stages
            this.progressComplete = false;
            
            // Run the first stage immediately so user sees it
            setTimeout(function() {
                runNextStage();
            }, 100);
            
            // Safety timeout to clear progress after 30 seconds
            setTimeout(function() {
                self.clearProgressSimulation();
            }, 30000);
        },
        
        /**
         * Clear progress simulation when request completes
         */
        clearProgressSimulation: function() {
            console.log('[BLOG CREATOR DEBUG] Clearing progress simulation');
            this.progressComplete = true;
            if (this.progressInterval) {
                clearTimeout(this.progressInterval);
                this.progressInterval = null;
            }
        },
        
        /**
         * Update progress indicator
         * 
         * @param {number} percent Progress percentage (0-100)
         * @param {string} text Progress text
         */
        updateProgress: function(percent, text) {
            $('#progress-fill').css('width', percent + '%');
            $('#progress-text').text(text);
        },
        
        /**
         * Show background queued message
         * 
         * @param {Object} data Background queue data
         */
        showBackgroundQueued: function(data) {
            $('#blog-progress').hide();
            
            var message = data.message || 'Blog post queued for background processing!';
            var jobId = data.job_id || 'Unknown';
            
            // Store current job ID for real-time tracking
            this.currentJobId = jobId;
            
            // Subscribe to job-specific updates if real-time is available
            if (this.isRealtimeEnabled && jobId && jobId !== 'Unknown') {
                this.subscribeToJobUpdates(jobId);
            }
            
            // Show success notification for queued job
            if (window.ExplainerPlugin?.Notifications) {
                window.ExplainerPlugin.Notifications.info(message + ' Job ID: ' + jobId);
            }
            
            $('#message-content').html(
                '<div class="notice notice-info"><p>' + 
                '<strong>Queued!</strong> ' + message +
                '</p><p>Job ID: <code>' + jobId + '</code></p>' +
                '<p>Your blog post is being created. You can monitor its progress in the job queue below.</p>' +
                '</div>'
            );
            
            // Show action to view jobs
            var actions = '<a href="#" id="view-job-queue" class="button button-primary">' +
                          'View Job Queue</a>'
            
            $('#message-actions').html(actions).show();
            
            // Update modal buttons - change Cancel to Close and hide Creating button
            this.updateModalButtons();
            
            $('#modal-messages').show();
            
            // Handle click to navigate to job queue page
            $('#view-job-queue').on('click', function(e) {
                e.preventDefault();
                
                // Close the modal first
                $('#blog-creation-modal, #modal-backdrop').fadeOut(300);
                
                // Navigate to the dedicated Job Queue admin page
                window.location.href = 'admin.php?page=wp-ai-explainer-job-queue';
            });
        },
        
        /**
         * Show success message
         * 
         * @param {Object} data Success data
         */
        showSuccess: function(data) {
            $('#blog-progress').hide();
            
            var message = data.message || 'Blog post created successfully!';
            
            // Show success notification
            if (window.ExplainerPlugin?.Notifications) {
                window.ExplainerPlugin.Notifications.success(message);
            }
            
            $('#message-content').html(
                '<div class="notice notice-success"><p>' + 
                '<strong>Blog Post Created Successfully</strong>' +
                '</p></div>'
            );
            
            // Show post details if available
            if (data.title || data.edit_link || data.preview_link || data.post_url) {
                this.showPostDetails(data);
            } else {
                // Fallback to legacy action buttons if no post details available
                this.showLegacyActions(data);
            }
            
            // Update modal buttons - change Cancel to Close and hide Creating button
            this.updateModalButtons();
            
            $('#modal-messages').show();
        },
        
        /**
         * Show post details in the success state
         * 
         * @param {Object} data Success data with post information
         */
        showPostDetails: function(data) {
            // Update post title
            var postTitle = data.title || 'Untitled Blog Post';
            $('#post-title').text(postTitle);
            
            // Update post status (most blog posts are created as drafts)
            var postStatus = data.post_status || 'Draft';
            $('#post-status-text').text(postStatus);
            
            // Handle featured image if available
            if (data.featured_image && data.featured_image_url) {
                $('#post-thumbnail-img').attr({
                    'src': data.featured_image_url,
                    'alt': postTitle
                });
                $('#post-thumbnail').show();
            } else {
                $('#post-thumbnail').hide();
            }
            
            // Set up action buttons with working links
            if (data.edit_link || data.edit_url) {
                var editUrl = data.edit_link || data.edit_url;
                $('#edit-post-link').attr('href', editUrl).show();
            } else {
                $('#edit-post-link').hide();
            }
            
            if (data.preview_link || data.post_url) {
                var previewUrl = data.preview_link || data.post_url;
                $('#preview-post-link').attr('href', previewUrl).show();
            } else {
                $('#preview-post-link').hide();
            }
            
            // Show the post success details
            $('#post-success-details').show();
            $('#message-actions').hide(); // Hide legacy actions
        },
        
        /**
         * Show legacy action buttons (fallback)
         * 
         * @param {Object} data Success data
         */
        showLegacyActions: function(data) {
            // Show action buttons if links are available
            if (data.edit_link || data.preview_link) {
                var actions = '';
                
                if (data.edit_link) {
                    actions += '<a href="' + data.edit_link + '" class="button button-primary" target="_blank">' +
                               'Edit Post</a> ';
                }
                
                if (data.preview_link) {
                    actions += '<a href="' + data.preview_link + '" class="button" target="_blank">' +
                               'Preview Post</a>';
                }
                
                $('#edit-post-link').attr('href', data.edit_link || '#');
                $('#preview-post-link').attr('href', data.preview_link || '#');
                
                $('#message-actions').html(actions).show();
            }
            
            $('#post-success-details').hide();
        },
        
        /**
         * Delete a selection
         * 
         * @param {number} selectionId The selection ID
         * @param {jQuery} $button The delete button element
         */
        deleteSelection: function(selectionId, $button) {
            var self = this;
            
            // Disable button and show loading state
            $button.prop('disabled', true).text('Deleting...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'explainer_delete_selection',
                    nonce: wpAiExplainer.nonce,
                    selection_id: selectionId
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        if (window.ExplainerPlugin?.Notifications) {
                            window.ExplainerPlugin.Notifications.success('Selection deleted successfully.');
                        } else if (window.ExplainerAdmin && typeof window.ExplainerAdmin.showNotice === 'function') {
                            window.ExplainerAdmin.showNotice('Selection deleted successfully.', 'success');
                        }
                        
                        // Reload the popular selections data to refresh counts and data
                        // Check if we're on the popular selections tab and reload if needed
                        if ($('.nav-tab[href="#popular"]').hasClass('nav-tab-active')) {
                            // Trigger the refresh button click to reload data with updated counts
                            var refreshBtn = $('#refresh-selections');
                            if (refreshBtn.length > 0) {
                                refreshBtn.trigger('click');
                            } else {
                                // Fallback: reload the page after a short delay if refresh button not found
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1000);
                            }
                        } else {
                            // If not on popular selections tab, just remove the DOM element
                            $button.closest('tr').fadeOut(300, function() {
                                $(this).remove();
                                
                                // Check if table is now empty
                                var remainingRows = $('#selections-table-body tr').length;
                                if (remainingRows === 0) {
                                    $('#selections-table-body').html(
                                        '<tr><td colspan="5" style="text-align: center; padding: 40px;">' +
                                        'No text selections yet. Data will appear here once users start selecting text on your website and generating AI explanations.' +
                                        '</td></tr>'
                                    );
                                }
                            });
                        }
                    } else {
                        // Re-enable button and show error
                        $button.prop('disabled', false).text('Delete');
                        if (window.ExplainerPlugin?.replaceAlert) {
                            window.ExplainerPlugin.replaceAlert('Error: ' + (response.data.message || 'Failed to delete selection'), 'error');
                        } else {
                            alert('Error: ' + (response.data.message || 'Failed to delete selection'));
                        }
                    }
                },
                error: function() {
                    // Re-enable button and show error
                    $button.prop('disabled', false).text('Delete');
                    if (window.ExplainerPlugin?.replaceAlert) {
                        window.ExplainerPlugin.replaceAlert('Error: Network error occurred while deleting selection', 'error');
                    } else {
                        alert('Error: Network error occurred while deleting selection');
                    }
                }
            });
        },
        
        /**
         * Show error message
         * 
         * @param {string} message Error message
         */
        showError: function(message) {
            $('#blog-progress').hide();
            $('#generation-options').show();
            $('#create-post-submit').prop('disabled', false).text('Create Blog Post');
            
            // Show error notification
            if (window.ExplainerPlugin?.Notifications) {
                window.ExplainerPlugin.Notifications.error(message);
            }
            
            $('#message-content').html(
                '<div class="notice notice-error"><p>' + 
                '<strong>Error:</strong> ' + message +
                '</p></div>'
            );
            
            $('#message-actions').hide();
            $('#modal-messages').show();
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
                    
                    console.log('[BLOG CREATOR DEBUG] Real-time system initialized');
                    
                    // Initialize the adapter
                    this.realtimeAdapter.initialize().catch((error) => {
                        console.error('[BLOG CREATOR DEBUG] Failed to initialize real-time adapter:', error.message);
                        this.isRealtimeEnabled = false;
                    });
                    
                } else {
                    console.log('[BLOG CREATOR DEBUG] Real-time adapter not available');
                    this.isRealtimeEnabled = false;
                }
            } catch (error) {
                console.error('[BLOG CREATOR DEBUG] Error initializing real-time system:', error.message);
                this.isRealtimeEnabled = false;
            }
        },
        
        /**
         * Subscribe to specific job updates
         */
        subscribeToJobUpdates: function(jobId) {
            if (!this.realtimeAdapter || !jobId) {
                return;
            }
            
            const topic = `wp_ai_explainer_job_queue_${jobId}`;
            
            try {
                this.realtimeAdapter.connect(topic, (eventData, context) => {
                    console.log('[BLOG CREATOR DEBUG] Received job update event', {
                        jobId,
                        eventData,
                        context
                    });
                    
                    // Handle job completion
                    if (eventData.status === 'completed' && eventData.job_id === jobId) {
                        this.handleJobCompletion(eventData);
                    } else if (eventData.status === 'failed' && eventData.job_id === jobId) {
                        this.handleJobFailure(eventData);
                    }
                });
                
                this.subscribedTopics.add(topic);
                
                console.log('[BLOG CREATOR DEBUG] Subscribed to job updates', {
                    jobId,
                    topic
                });
                
            } catch (error) {
                console.error('[BLOG CREATOR DEBUG] Failed to subscribe to job updates:', error.message);
            }
        },
        
        /**
         * Handle job completion
         */
        handleJobCompletion: function(eventData) {
            console.log('[BLOG CREATOR DEBUG] Job completed', eventData);
            
            // Show completion notification
            if (window.ExplainerPlugin?.Notifications) {
                window.ExplainerPlugin.Notifications.success('Blog post creation completed!');
            }
            
            // Update the modal with completion message if still open
            if ($('#blog-creation-modal').is(':visible')) {
                this.showSuccess({
                    message: eventData.message || 'Blog post created successfully!',
                    title: eventData.title,
                    edit_link: eventData.edit_link || eventData.edit_url,
                    preview_link: eventData.preview_link || eventData.post_url,
                    post_url: eventData.post_url,
                    edit_url: eventData.edit_url,
                    post_status: eventData.post_status,
                    featured_image: eventData.featured_image,
                    featured_image_url: eventData.featured_image_url
                });
            }
            
            // Clean up subscription
            if (this.currentJobId === eventData.job_id) {
                this.unsubscribeFromJobUpdates(eventData.job_id);
                this.currentJobId = null;
            }
        },
        
        /**
         * Handle job failure
         */
        handleJobFailure: function(eventData) {
            console.log('[BLOG CREATOR DEBUG] Job failed', eventData);
            
            // Show error notification
            if (window.ExplainerPlugin?.Notifications) {
                window.ExplainerPlugin.Notifications.error('Blog post creation failed: ' + (eventData.error_message || 'Unknown error'));
            }
            
            // Update the modal with error message if still open
            if ($('#blog-creation-modal').is(':visible')) {
                this.showError(eventData.error_message || 'Blog post creation failed');
            }
            
            // Clean up subscription
            if (this.currentJobId === eventData.job_id) {
                this.unsubscribeFromJobUpdates(eventData.job_id);
                this.currentJobId = null;
            }
        },
        
        /**
         * Unsubscribe from specific job updates
         */
        unsubscribeFromJobUpdates: function(jobId) {
            if (!this.realtimeAdapter || !jobId) {
                return;
            }
            
            const topic = `wp_ai_explainer_job_queue_${jobId}`;
            
            try {
                this.realtimeAdapter.disconnect(topic);
                this.subscribedTopics.delete(topic);
                
                console.log('[BLOG CREATOR DEBUG] Unsubscribed from job updates', {
                    jobId,
                    topic
                });
                
            } catch (error) {
                console.error('[BLOG CREATOR DEBUG] Error unsubscribing from job updates:', error.message);
            }
        },
        
        /**
         * Clean up all subscriptions
         */
        cleanup: function() {
            if (this.realtimeAdapter) {
                try {
                    this.realtimeAdapter.disconnectAll();
                    this.subscribedTopics.clear();
                    this.currentJobId = null;
                    
                    console.log('[BLOG CREATOR DEBUG] Cleaned up all real-time subscriptions');
                } catch (error) {
                    console.error('[BLOG CREATOR DEBUG] Error cleaning up subscriptions:', error.message);
                }
            }
        }
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Auto-initialize if modal exists on page
        if ($('#blog-creation-modal').length > 0) {
            ExplainerBlogCreator.init();
        }
    });
    
})(jQuery);