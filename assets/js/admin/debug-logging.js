/**
 * Debug Logging Module
 * Handles debug log viewing and management functionality
 */

(function($) {
    'use strict';

    // Module namespace
    window.WPAIExplainer = window.WPAIExplainer || {};
    window.WPAIExplainer.Admin = window.WPAIExplainer.Admin || {};
    window.WPAIExplainer.Admin.DebugLogging = {

        // Private state
        debugPanelVisible: false,
        realtimeInterval: null,

        /**
         * Initialize debug logging functionality
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            $('#view-debug-logs').on('click', this.toggleDebugPanel.bind(this));
            $('#refresh-debug-logs').on('click', this.refreshDebugLogs.bind(this));
            $('#download-debug-logs').on('click', this.downloadDebugLogs.bind(this));
            $('#delete-debug-logs').on('click', this.deleteDebugLogs.bind(this));
            $('#apply-log-filters').on('click', this.applyLogFilters.bind(this));
            $('#clear-log-filters').on('click', this.clearLogFilters.bind(this));
            $('#realtime-logs-toggle').on('change', this.toggleRealtimeLogs.bind(this));
        },

        /**
         * Toggle debug panel visibility
         */
        toggleDebugPanel: function(e) {
            e.preventDefault();
            
            if (this.debugPanelVisible) {
                this.hideDebugPanel();
            } else {
                this.showDebugPanel();
            }
        },

        /**
         * Show debug panel
         */
        showDebugPanel: function() {
            this.logDebugAction('Opening debug panel');
            
            // Update button text
            $('#view-debug-logs').html('<span class="dashicons dashicons-hidden"></span> Hide Debug Logs');
            
            // Show all debug sections
            $('#debug-filters, #debug-logs-viewer, #debug-realtime-controls').show();
            $('#refresh-debug-logs, #download-debug-logs').show();
            
            // Load initial data
            this.loadDebugLogs();
            
            this.debugPanelVisible = true;
        },

        /**
         * Hide debug panel
         */
        hideDebugPanel: function() {
            this.logDebugAction('Closing debug panel');
            
            // Update button text
            $('#view-debug-logs').html('<span class="dashicons dashicons-visibility"></span> View Debug Logs');
            
            // Hide all debug sections
            $('#debug-filters, #debug-logs-viewer, #debug-realtime-controls').hide();
            $('#refresh-debug-logs, #download-debug-logs').hide();
            
            // Stop realtime updates
            if (this.realtimeInterval) {
                clearInterval(this.realtimeInterval);
                this.realtimeInterval = null;
                $('#realtime-logs-toggle').prop('checked', false);
            }
            
            this.debugPanelVisible = false;
        },


        /**
         * Load debug logs
         */
        loadDebugLogs: function(filters) {
            const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
            const nonce = window.wpAiExplainer?.nonce;

            if (!ajaxUrl || !nonce) {
                $('#debug-logs-list').html('<p style="padding: 20px; color: #d63638;">AJAX configuration not found.</p>');
                return;
            }

            const data = {
                action: 'explainer_view_debug_logs',
                nonce: nonce,
                limit: 100
            };
            
            if (filters) {
                if (filters.level) data.level = filters.level;
                if (filters.component) data.component = filters.component;
            }
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.displayDebugLogs(response.data);
                        this.updateComponentFilter(response.data.components);
                    } else {
                        $('#debug-logs-list').html('<p style="padding: 20px; color: #d63638;">Failed to load logs: ' + (response.data?.message || 'Unknown error') + '</p>');
                    }
                },
                error: () => {
                    $('#debug-logs-list').html('<p style="padding: 20px; color: #d63638;">Connection error while loading logs.</p>');
                }
            });
        },

        /**
         * Display debug logs
         */
        displayDebugLogs: function(data) {
            const logs = data.logs || [];
            let html = '';
            
            $('#debug-logs-count').text(logs.length + ' entries');
            
            if (logs.length === 0) {
                html = '<p style="padding: 20px; margin: 0; text-align: center; color: #666;">No logs found matching the current filters.</p>';
            } else {
                logs.forEach((log) => {
                    const levelColor = this.getLevelColor(log.level);
                    const timestamp = new Date(log.timestamp).toLocaleString();
                    
                    html += '<div style="border-bottom: 1px solid #eee; padding: 10px; font-family: monospace; font-size: 12px;">';
                    html += '<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">';
                    html += '<span style="background: ' + levelColor + '; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold;">' + log.level.toUpperCase() + '</span>';
                    html += '<span style="color: #666;">' + timestamp + '</span>';
                    if (log.component) {
                        html += '<span style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 10px;">' + this.escapeHtml(log.component) + '</span>';
                    }
                    html += '</div>';
                    html += '<div style="margin: 5px 0; font-weight: 500;">' + this.escapeHtml(log.message) + '</div>';
                    
                    if (log.context && Object.keys(log.context).length > 0) {
                        html += '<details style="margin-top: 5px;">';
                        html += '<summary style="cursor: pointer; color: #666; font-size: 11px;">Context Data</summary>';
                        html += '<pre style="margin: 5px 0; padding: 5px; background: #f9f9f9; border-radius: 3px; font-size: 11px; overflow-x: auto;">';
                        html += this.escapeHtml(JSON.stringify(log.context, null, 2));
                        html += '</pre>';
                        html += '</details>';
                    }
                    html += '</div>';
                });
            }
            
            $('#debug-logs-list').html(html);
        },

        /**
         * Update component filter dropdown
         */
        updateComponentFilter: function(components) {
            const select = $('#log-component-filter');
            const currentValue = select.val();
            
            select.find('option:not(:first)').remove();
            
            if (components && components.length > 0) {
                components.forEach((component) => {
                    select.append('<option value="' + this.escapeHtml(component) + '">' + this.escapeHtml(component) + '</option>');
                });
                
                // Restore previous selection if it still exists
                if (currentValue && components.indexOf(currentValue) !== -1) {
                    select.val(currentValue);
                }
            }
        },

        /**
         * Refresh debug logs
         */
        refreshDebugLogs: function(e) {
            e.preventDefault();
            this.logDebugAction('Refreshing debug logs');
            this.loadDebugLogs(this.getCurrentFilters());
        },

        /**
         * Apply log filters
         */
        applyLogFilters: function(e) {
            e.preventDefault();
            const filters = this.getCurrentFilters();
            this.logDebugAction('Applying log filters', filters);
            this.loadDebugLogs(filters);
        },

        /**
         * Clear log filters
         */
        clearLogFilters: function(e) {
            e.preventDefault();
            this.logDebugAction('Clearing log filters');
            $('#log-level-filter').val('');
            $('#log-component-filter').val('');
            this.loadDebugLogs();
        },

        /**
         * Get current filter values
         */
        getCurrentFilters: function() {
            return {
                level: $('#log-level-filter').val(),
                component: $('#log-component-filter').val()
            };
        },

        /**
         * Download debug logs
         */
        downloadDebugLogs: function(e) {
            e.preventDefault();
            this.logDebugAction('Downloading debug logs');
            
            const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
            const nonce = window.wpAiExplainer?.nonce;

            if (!ajaxUrl || !nonce) {
                if (window.ExplainerPlugin?.Notifications) {
                    window.ExplainerPlugin.Notifications.error('AJAX configuration not found.');
                } else {
                    console.log(`[ERROR] AJAX configuration not found.`);
                }
                return;
            }
            
            // Create a form and submit it to trigger download
            const form = $('<form>').attr({
                method: 'POST',
                action: ajaxUrl
            }).append(
                $('<input>').attr({name: 'action', value: 'explainer_download_debug_logs', type: 'hidden'}),
                $('<input>').attr({name: 'nonce', value: nonce, type: 'hidden'})
            );
            
            $('body').append(form);
            form.submit();
            form.remove();
        },

        /**
         * Delete debug logs
         */
        deleteDebugLogs: function(e) {
            e.preventDefault();
            
            const performDelete = () => {
                const button = $('#delete-debug-logs');
                const originalHtml = button.html();
                
                button.html('<span class="dashicons dashicons-update"></span> Deleting...').prop('disabled', true);
                
                const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
                const nonce = window.wpAiExplainer?.nonce;

                if (!ajaxUrl || !nonce) {
                    if (window.ExplainerPlugin?.Notifications) {
                        window.ExplainerPlugin.Notifications.error('AJAX configuration not found.');
                    } else {
                        console.log(`[ERROR] AJAX configuration not found.`);
                    }
                    button.html(originalHtml).prop('disabled', false);
                    return;
                }
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'explainer_delete_debug_logs',
                        nonce: nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            this.showMessage(response.data.message, 'success');
                            // Clear the display immediately
                            $('#debug-logs-list').html('<p style="padding: 20px; margin: 0; text-align: center; color: #666;">No logs found matching the current filters.</p>');
                            $('#debug-logs-count').text('0 entries');
                            this.loadDebugLogs();
                        } else {
                            this.showMessage('Failed to delete logs: ' + (response.data?.message || 'Unknown error'), 'error');
                        }
                    },
                    error: () => {
                        this.showMessage('Connection error while deleting logs.', 'error');
                    },
                    complete: () => {
                        button.html(originalHtml).prop('disabled', false);
                    }
                });
            };
            
            if (window.ExplainerPlugin?.replaceConfirm) {
                window.ExplainerPlugin.replaceConfirm(
                    'Are you sure you want to delete all debug logs? This action cannot be undone.',
                    {
                        confirmText: 'Delete All Logs',
                        cancelText: 'Cancel',
                        type: 'warning'
                    }
                ).then((confirmed) => {
                    if (confirmed) {
                        performDelete();
                    }
                });
                return;
            } else {
                if (confirm('Are you sure you want to delete all debug logs? This action cannot be undone.')) {
                    performDelete();
                }
                return;
            }
        },

        /**
         * Toggle realtime log updates
         */
        toggleRealtimeLogs: function(e) {
            const enabled = $(e.currentTarget).is(':checked');
            
            if (enabled) {
                this.logDebugAction('Enabling real-time log updates');
                this.realtimeInterval = setInterval(() => {
                    this.loadDebugLogs(this.getCurrentFilters());
                }, 5000);
            } else {
                this.logDebugAction('Disabling real-time log updates');
                if (this.realtimeInterval) {
                    clearInterval(this.realtimeInterval);
                    this.realtimeInterval = null;
                }
            }
        },

        /**
         * Get color for log level
         */
        getLevelColor: function(level) {
            const colors = {
                'error': '#d63638',
                'warning': '#dba617',
                'info': '#2271b1',
                'debug': '#50575e'
            };
            return colors[level.toLowerCase()] || '#50575e';
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                '\'': '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        /**
         * Log debug action (if logging function is available)
         */
        logDebugAction: function(message, context = {}) {
            if (typeof window.explainerLogInfo === 'function') {
                window.explainerLogInfo(message, context, 'AdminDebug');
            } else {
                console.log('[AdminDebug]', message, context);
            }
        },

        /**
         * Show message (fallback if not available elsewhere)
         */
        showMessage: function(message, type) {
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
                console.log(`[${type.toUpperCase()}] ${message}`);
            }
        }
    };

})(jQuery);