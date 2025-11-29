/**
 * Meta box JavaScript functionality for AI Explanations
 */

(function($) {
    'use strict';

    // Meta box namespace
    window.ExplainerMetaBox = {
        
        /**
         * Initialize meta box functionality
         */
        init: function() {
            this.bindEvents();
            this.setupPolling();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Refresh status button
            $(document).on('click', '.refresh-status-btn', this.refreshStatus.bind(this));
            
            // Queue checkbox handler
            $(document).on('change', '.queue-checkbox', this.handleQueueToggle.bind(this));
            
            // View terms link
            $(document).on('click', '.view-terms-link', this.handleViewTerms.bind(this));
        },

        /**
         * Set up polling for processing jobs
         */
        setupPolling: function() {
            const $metaBox = $('.ai-explanations-meta-box');
            if ($metaBox.length === 0) {
                return;
            }

            const status = $metaBox.find('.status-indicator').data('status');
            
            if (status === 'processing' || status === 'queued') {
                this.startPolling();
            }
        },

        /**
         * Start status polling
         */
        startPolling: function() {
            this.pollingInterval = setInterval(() => {
                this.refreshStatus(null, true);
            }, 10000); // Poll every 10 seconds
        },

        /**
         * Stop status polling
         */
        stopPolling: function() {
            if (this.pollingInterval) {
                clearInterval(this.pollingInterval);
                this.pollingInterval = null;
            }
        },

        /**
         * Refresh status from server
         */
        refreshStatus: function(event, isPolling = false) {
            if (event) {
                event.preventDefault();
            }

            const $button = $('.refresh-status-btn');
            const $spinner = $('.status-refresh .spinner');
            const postId = $button.data('post-id');

            if (!postId) {
                return;
            }

            // Show loading state
            if (!isPolling) {
                $button.prop('disabled', true);
                $spinner.addClass('is-active');
            }

            // Get admin AJAX nonce
            const nonce = window.explainerAdmin ? window.explainerAdmin.nonce : '';

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'explainer_get_meta_box_status',
                    post_id: postId,
                    nonce: nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateStatusDisplay(response.data);
                    } else {
                        console.error('Status refresh failed:', response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX error:', error);
                },
                complete: () => {
                    if (!isPolling) {
                        $button.prop('disabled', false);
                        $spinner.removeClass('is-active');
                    }
                }
            });
        },

        /**
         * Update status display with new data
         */
        updateStatusDisplay: function(data) {
            const $statusIndicator = $('.status-indicator');
            const $metaBox = $('.ai-explanations-meta-box');
            
            // Status configuration (matches PHP template)
            const statusConfig = {
                'not_scanned': {
                    label: 'Not Scanned',
                    icon: '—',
                    color: '#7c8993',
                    description: 'Post has not been scanned for AI terms yet'
                },
                'queued': {
                    label: 'Queued',
                    icon: '⏰',
                    color: '#0073aa',
                    description: 'Post is queued for AI scanning'
                },
                'processing': {
                    label: 'Processing',
                    icon: '⟲',
                    color: '#ff922b',
                    description: 'Post is currently being processed'
                },
                'processed': {
                    label: 'Processed',
                    icon: '✓',
                    color: '#00a32a',
                    description: 'Post has been successfully scanned'
                },
                'outdated': {
                    label: 'Outdated',
                    icon: '⚠',
                    color: '#ffb900',
                    description: 'Post has been modified since last scan'
                },
                'error': {
                    label: 'Failed',
                    icon: '✗',
                    color: '#d63638',
                    description: 'Scanning failed with an error'
                }
            };

            const config = statusConfig[data.status] || statusConfig['not_scanned'];

            // Update status indicator
            $statusIndicator.attr('data-status', data.status);
            $statusIndicator.find('.status-icon').html(config.icon).css('color', config.color);
            $statusIndicator.find('.status-label').text(config.label);
            
            // Update description
            $('.status-description').text(config.description);

            // Update term count
            const $termCount = $('.term-count');
            const $termsSection = $('.terms-section');
            
            if (data.status === 'processed' && data.term_count > 0) {
                if ($termCount.length === 0) {
                    $('.status-description').after('<p class="term-count">' + 
                        data.term_count + ' term' + (data.term_count > 1 ? 's' : '') + ' found</p>');
                } else {
                    $termCount.text(data.term_count + ' term' + (data.term_count > 1 ? 's' : '') + ' found');
                }

                // Show/update terms section
                if ($termsSection.length === 0 && data.term_count > 0) {
                    const termsHTML = `
                        <div class="terms-section">
                            <h4>Extracted Terms</h4>
                            <a href="#" class="button button-secondary view-terms-link" data-post-id="${$('.refresh-status-btn').data('post-id')}">
                                View ${data.term_count} Terms
                            </a>
                        </div>
                    `;
                    $('.display-section').after(termsHTML);
                }
            } else {
                $termCount.remove();
                $termsSection.remove();
            }

            // Update queue checkbox state
            const $queueCheckbox = $('.queue-checkbox');
            const queueChecked = (data.status === 'queued' || data.status === 'processing');
            const queueDisabled = (data.status === 'processing' || data.status === 'processed');
            
            $queueCheckbox.prop('checked', queueChecked);
            $queueCheckbox.prop('disabled', queueDisabled);

            // Update help text
            this.updateHelpText(data.status);

            // Handle polling based on status
            if (data.status === 'processing' || data.status === 'queued') {
                if (!this.pollingInterval) {
                    this.startPolling();
                }
            } else {
                this.stopPolling();
            }
        },

        /**
         * Update help text based on status
         */
        updateHelpText: function(status) {
            const $helpText = $('.queue-section .help-text');
            
            $helpText.removeClass('processing');
            
            if (status === 'processed') {
                $helpText.text('Post is already processed. Uncheck to allow re-scanning if modified.');
            } else if (status === 'processing') {
                $helpText.addClass('processing').text('Currently processing... Please wait.');
            } else {
                $helpText.remove();
            }
        },

        /**
         * Handle queue checkbox toggle
         */
        handleQueueToggle: function(event) {
            const $checkbox = $(event.target);
            const postId = $('.refresh-status-btn').data('post-id');
            const action = $checkbox.is(':checked') ? 'queue' : 'dequeue';
            
            if (!postId) {
                return;
            }

            // Prevent multiple rapid clicks
            $checkbox.prop('disabled', true);

            const nonce = window.explainerAdmin ? window.explainerAdmin.nonce : '';

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'explainer_toggle_post_queue',
                    post_id: postId,
                    queue_action: action,
                    nonce: nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Show success message
                        this.showNotice(response.data.message, 'success');
                        
                        // Refresh status after a short delay
                        setTimeout(() => {
                            this.refreshStatus(null, false);
                        }, 500);
                    } else {
                        // Revert checkbox state
                        $checkbox.prop('checked', !$checkbox.is(':checked'));
                        this.showNotice(response.data.message, 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Queue toggle error:', error);
                    
                    // Revert checkbox state
                    $checkbox.prop('checked', !$checkbox.is(':checked'));
                    this.showNotice('Network error occurred', 'error');
                },
                complete: () => {
                    $checkbox.prop('disabled', false);
                }
            });
        },

        /**
         * Handle view terms link
         */
        handleViewTerms: function(event) {
            event.preventDefault();
            
            const $link = $(event.target);
            const postId = $link.data('post-id');
            
            if (!postId) {
                return;
            }

            // For now, just show a placeholder notification
            // In a future enhancement, this would open a modal with term management
            if (window.ExplainerPlugin?.replaceAlert) {
                window.ExplainerPlugin.replaceAlert('Term management modal will be implemented in a future version.', 'info');
            } else {
                alert('Term management modal will be implemented in a future version.');
            }
        },

        /**
         * Show notice message
         */
        showNotice: function(message, type = 'info') {
            if (window.ExplainerPlugin?.Notifications) {
                switch (type) {
                    case 'success':
                        window.ExplainerPlugin.Notifications.success(message);
                        break;
                    case 'error':
                        window.ExplainerPlugin.Notifications.error(message);
                        break;
                    case 'warning':
                        window.ExplainerPlugin.Notifications.warning(message);
                        break;
                    case 'info':
                    default:
                        window.ExplainerPlugin.Notifications.info(message);
                }
                return;
            }
            
            // Fallback to WordPress notices
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Insert after .wrap or at top of page
            if ($('.wrap').length) {
                $('.wrap').after($notice);
            } else {
                $('body').prepend($notice);
            }

            // Auto-dismiss after 3 seconds
            setTimeout(() => {
                $notice.fadeOut(() => {
                    $notice.remove();
                });
            }, 3000);
        },

        /**
         * Clean up on page unload
         */
        destroy: function() {
            this.stopPolling();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ExplainerMetaBox.init();
    });

    // Clean up on page unload
    $(window).on('beforeunload', function() {
        ExplainerMetaBox.destroy();
    });

})(jQuery);