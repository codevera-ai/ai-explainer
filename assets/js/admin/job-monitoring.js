/**
 * Job Monitoring Module
 * Handles job queue monitoring and job status dashboard functionality
 */

(function($) {
    'use strict';

    // Module namespace
    window.WPAIExplainer = window.WPAIExplainer || {};
    window.WPAIExplainer.Admin = window.WPAIExplainer.Admin || {};
    window.WPAIExplainer.Admin.JobMonitoring = {

        // Private state
        queueStatusVisible: false,
        queueLogsVisible: false,
        refreshInterval: null,
        jobStatusConfig: null,
        pollInterval: null,
        pollsWithoutChange: 0,
        lastJobsHash: '',
        
        // Real-time system
        realtimeAdapter: null,
        isRealtimeEnabled: false,
        subscribedTopics: new Set(),
        fallbackMode: false,

        /**
         * Initialize job monitoring functionality
         */
        init: function() {
            // Initialize real-time adapter if available
            this.initializeRealtimeSystem();
            window.ExplainerLogger?.info('JobMonitoring: Initializing job monitoring system', {
                timestamp: new Date().toISOString(),
                url: window.location.href
            }, 'JobMonitoring');
            
            window.ExplainerLogger?.debug('JobMonitoring: Binding events', {}, 'JobMonitoring');
            this.bindEvents();
            
            window.ExplainerLogger?.debug('JobMonitoring: Initializing job status', {}, 'JobMonitoring');
            this.initializeJobStatus();
            
            window.ExplainerLogger?.debug('JobMonitoring: Initializing job queue', {}, 'JobMonitoring');
            this.initializeJobQueue();
            
            // Initialize cron-based UI updates
            this.updateJobQueueUI();
            
            window.ExplainerLogger?.info('JobMonitoring: Job monitoring initialization complete', {}, 'JobMonitoring');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Job Queue Status Controls
            $('#view-job-queue-status').on('click', this.toggleQueueStatus.bind(this));
            $('#view-job-queue-logs').on('click', this.toggleQueueLogs.bind(this));
            $('#force-process-queue').on('click', this.forceProcessQueue.bind(this));
            $('#refresh-queue-status').on('click', this.refreshQueueStatus.bind(this));

            // Job Log Filtering
            $('#filter-job-logs').on('click', this.filterJobLogs.bind(this));
            $('#clear-job-log-filter').on('click', this.clearJobLogFilter.bind(this));

            // Job Details Modal
            $('#close-job-details-modal').on('click', this.closeJobDetailsModal.bind(this));
            $(document).on('keydown', this.handleModalKeydown.bind(this));

            // Job Status Dashboard
            $('#refresh-jobs').on('click', this.refreshJobs.bind(this));
            $('#clear-completed-jobs').on('click', this.clearCompletedJobs.bind(this));
            $('#retry-load-jobs').on('click', this.retryLoadJobs.bind(this));

            // Dynamic job actions
            $(document).on('click', '.cancel-job', this.cancelJob.bind(this));
            $(document).on('click', '.retry-job', this.retryJob.bind(this));
            $(document).on('click', '.run-job-btn', this.runSingleJob.bind(this));
        },

        /**
         * Initialize job status dashboard
         */
        initializeJobStatus: function() {
            window.ExplainerLogger?.debug('JobMonitoring: Job queue panels removed from settings tabs', {
                panelExists: false, // Panels removed for simplification
                configExists: !!window.ExplainerJobStatus,
                configData: window.ExplainerJobStatus
            }, 'JobMonitoring');
            
            // Job queue panels removed from settings tabs - early return
            window.ExplainerLogger?.info('JobMonitoring: Job queue panels removed from settings tabs - use dedicated Job Queue page', {}, 'JobMonitoring');
            return;
            
            this.jobStatusConfig = window.ExplainerJobStatus.config;
            window.ExplainerLogger?.info('JobMonitoring: Job status dashboard initialized successfully', {
                config: this.jobStatusConfig
            }, 'JobMonitoring');
            
            this.loadJobs(false);
            
            // Use real-time updates if available, otherwise fallback to polling
            if (this.isRealtimeEnabled) {
                this.subscribeToJobEvents();
            } else {
                this.startPolling();
            }
        },

        /**
         * Initialize job queue monitoring
         */
        initializeJobQueue: function() {
            // Initialize if job queue elements exist
            if ($('#view-job-queue-status').length === 0) {
                return;
            }
        },

        /**
         * Toggle queue status visibility
         */
        toggleQueueStatus: function(e) {
            e.preventDefault();
            
            if (this.queueStatusVisible) {
                this.hideQueueStatus();
            } else {
                this.showQueueStatus();
            }
        },

        /**
         * Show queue status
         */
        showQueueStatus: function() {
            $('#view-job-queue-status').html('<span class="dashicons dashicons-hidden"></span> Hide Queue Status');
            $('#queue-status-dashboard').show();
            $('#refresh-queue-status').show();
            this.loadQueueStatus();
            this.queueStatusVisible = true;
            
            // Use real-time updates if available, otherwise fallback to polling
            if (this.isRealtimeEnabled) {
                this.subscribeToQueueStatusEvents();
            } else {
                // Fallback: Start auto-refresh every 30 seconds
                this.refreshInterval = setInterval(() => {
                    this.loadQueueStatus(false);
                }, 30000);
            }
        },

        /**
         * Hide queue status
         */
        hideQueueStatus: function() {
            $('#view-job-queue-status').html('<span class="dashicons dashicons-dashboard"></span> View Queue Status');
            $('#queue-status-dashboard').hide();
            $('#refresh-queue-status').hide();
            this.queueStatusVisible = false;
            
            // Clean up both polling and real-time subscriptions
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
            
            // Unsubscribe from queue status events when hiding
            if (this.isRealtimeEnabled && this.realtimeAdapter) {
                try {
                    this.realtimeAdapter.disconnect('job_queue:list');
                    this.subscribedTopics.delete('job_queue:list');
                } catch (error) {
                    window.ExplainerLogger?.error('JobMonitoring: Error unsubscribing from queue status events', {
                        error: error.message
                    }, 'JobMonitoring');
                }
            }
        },

        /**
         * Toggle queue logs visibility
         */
        toggleQueueLogs: function(e) {
            e.preventDefault();
            
            if (this.queueLogsVisible) {
                this.hideQueueLogs();
            } else {
                this.showQueueLogs();
            }
        },

        /**
         * Show queue logs
         */
        showQueueLogs: function() {
            $('#view-job-queue-logs').html('<span class="dashicons dashicons-hidden"></span> Hide Job Logs');
            $('#job-queue-logs-display').show();
            this.loadJobQueueLogs();
            this.queueLogsVisible = true;
        },

        /**
         * Hide queue logs
         */
        hideQueueLogs: function() {
            $('#view-job-queue-logs').html('<span class="dashicons dashicons-list-view"></span> View Job Logs');
            $('#job-queue-logs-display').hide();
            this.queueLogsVisible = false;
        },

        /**
         * Load queue status
         */
        loadQueueStatus: function(showLoading = false) {
            if (showLoading) {
                $('#queue-stats').html('<div style="text-align: center; padding: 20px;">Loading queue status...</div>');
            }
            
            const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
            const nonce = window.wpAiExplainer?.nonce;

            if (!ajaxUrl || !nonce) {
                $('#queue-stats').html('<div style="color: red; padding: 10px;">AJAX configuration not found.</div>');
                return;
            }
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'explainer_get_job_queue_status',
                    nonce: nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderQueueStatus(response.data);
                    } else {
                        $('#queue-stats').html('<div style="color: red; padding: 10px;">Error: ' + (response.data?.message || 'Failed to load queue status') + '</div>');
                    }
                },
                error: () => {
                    $('#queue-stats').html('<div style="color: red; padding: 10px;">Network error loading queue status</div>');
                }
            });
        },

        /**
         * Load job queue logs
         */
        loadJobQueueLogs: function(jobId = '') {
            $('#job-logs-list').html('<p style="padding: 20px; margin: 0; text-align: center; color: #666;">Loading job logs...</p>');
            
            const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
            const nonce = window.wpAiExplainer?.nonce;

            if (!ajaxUrl || !nonce) {
                $('#job-logs-list').html('<p style="color: red; padding: 20px;">AJAX configuration not found.</p>');
                return;
            }
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'explainer_view_job_queue_logs',
                    nonce: nonce,
                    job_id: jobId,
                    limit: 50
                },
                success: (response) => {
                    if (response.success) {
                        this.renderJobLogs(response.data.logs, jobId);
                    } else {
                        $('#job-logs-list').html('<p style="color: red; padding: 20px;">Error: ' + (response.data?.message || 'Failed to load logs') + '</p>');
                    }
                },
                error: () => {
                    $('#job-logs-list').html('<p style="color: red; padding: 20px;">Network error loading logs</p>');
                }
            });
        },

        /**
         * Render queue status
         */
        renderQueueStatus: function(data) {
            // Render statistics
            let statsHtml = '';
            if (data.stats) {
                const stats = data.stats;
                const statItems = [
                    { label: 'Total Jobs', value: stats.total_jobs || 0, class: 'total' },
                    { label: 'Pending', value: stats.pending || 0, class: 'pending' },
                    { label: 'Processing', value: stats.processing || 0, class: 'processing' },
                    { label: 'Completed', value: stats.completed || 0, class: 'completed' },
                    { label: 'Failed', value: stats.failed || 0, class: 'failed' }
                ];
                
                statsHtml = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 20px;">';
                statItems.forEach((item) => {
                    statsHtml += `<div class="stat-item ${item.class}" style="background: #f9f9f9; padding: 15px; border-radius: 4px; text-align: center;">`;
                    statsHtml += `<div style="font-size: 24px; font-weight: bold; color: #2271b1;">${item.value}</div>`;
                    statsHtml += `<div style="color: #666; font-size: 12px;">${item.label}</div>`;
                    statsHtml += '</div>';
                });
                statsHtml += '</div>';
            }
            
            // Render recent jobs
            let jobsHtml = '';
            if (data.recent_jobs && data.recent_jobs.length > 0) {
                jobsHtml = '<div style="margin-top: 20px;"><h4>Recent Jobs</h4>';
                jobsHtml += '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">';
                
                data.recent_jobs.forEach((job) => {
                    const statusColor = this.getStatusColor(job.status);
                    jobsHtml += '<div style="padding: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">';
                    jobsHtml += `<div style="flex: 1;"><strong>Job #${job.id}</strong> - ${this.escapeHtml(job.title || 'Untitled')}</div>`;
                    jobsHtml += `<div style="margin: 0 10px; font-size: 12px; color: #666;">${job.created_at}</div>`;
                    jobsHtml += `<div style="background: ${statusColor}; color: white; padding: 4px 8px; border-radius: 3px; font-size: 11px;">${job.status.toUpperCase()}</div>`;
                    jobsHtml += `<button onclick="WPAIExplainer.Admin.JobMonitoring.showJobDetails(${job.id})" style="margin-left: 10px; background: ${window.wpAiExplainer?.brandColors?.primary || '#8b5cf6'}; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer;">Details</button>`;
                    jobsHtml += '</div>';
                });
                
                jobsHtml += '</div></div>';
            }
            
            $('#queue-stats').html(statsHtml + jobsHtml);
        },

        /**
         * Render job logs
         */
        renderJobLogs: function(logs, jobIdFilter = '') {
            let logsHtml = '';
            
            if (logs && logs.length > 0) {
                logsHtml = '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">';
                
                logs.forEach((log) => {
                    const timestamp = new Date(log.timestamp).toLocaleString();
                    const statusColor = this.getStatusColor(log.status || 'info');
                    
                    logsHtml += '<div style="padding: 10px; border-bottom: 1px solid #eee; font-family: monospace; font-size: 12px;">';
                    logsHtml += '<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">';
                    logsHtml += `<span style="background: ${statusColor}; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px;">JOB #${log.job_id}</span>`;
                    logsHtml += `<span style="color: #666;">${timestamp}</span>`;
                    logsHtml += '</div>';
                    logsHtml += `<div style="margin: 5px 0;">${this.escapeHtml(log.message)}</div>`;
                    
                    if (log.context) {
                        logsHtml += '<details style="margin-top: 5px;">';
                        logsHtml += '<summary style="cursor: pointer; color: #666; font-size: 11px;">Context</summary>';
                        logsHtml += '<pre style="margin: 5px 0; padding: 5px; background: #f9f9f9; border-radius: 3px; overflow-x: auto;">';
                        logsHtml += this.escapeHtml(JSON.stringify(log.context, null, 2));
                        logsHtml += '</pre>';
                        logsHtml += '</details>';
                    }
                    logsHtml += '</div>';
                });
                
                logsHtml += '</div>';
            } else {
                const message = jobIdFilter ? 
                    'No logs found for job ID: ' + jobIdFilter : 
                    'No job queue logs available.';
                logsHtml = '<p style="padding: 20px; margin: 0; text-align: center; color: #666;">' + message + '</p>';
            }
            
            $('#job-logs-list').html(logsHtml);
        },

        /**
         * Force process queue
         */
        forceProcessQueue: function(e) {
            e.preventDefault();
            
            const button = $(e.currentTarget);
            const originalText = button.text();
            
            button.text('Processing...').prop('disabled', true);
            
            const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
            const nonce = window.wpAiExplainer?.nonce;

            if (!ajaxUrl || !nonce) {
                if (window.ExplainerPlugin?.Notifications) {
                    window.ExplainerPlugin.Notifications.error('AJAX configuration not found.');
                } else {
                    console.log(`[ERROR] AJAX configuration not found.`);
                }
                button.text(originalText).prop('disabled', false);
                return;
            }
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'explainer_force_process_queue',
                    nonce: nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage('Queue processing initiated.', 'success');
                        this.loadQueueStatus(true);
                    } else {
                        this.showMessage('Failed to process queue: ' + (response.data?.message || 'Unknown error'), 'error');
                    }
                },
                error: () => {
                    this.showMessage('Connection error while processing queue.', 'error');
                },
                complete: () => {
                    button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Event handlers for job status dashboard
         */
        refreshQueueStatus: function(e) {
            e.preventDefault();
            this.loadQueueStatus(true);
        },

        filterJobLogs: function(e) {
            e.preventDefault();
            const jobId = $('#job-log-filter').val().trim();
            this.loadJobQueueLogs(jobId);
        },

        clearJobLogFilter: function(e) {
            e.preventDefault();
            $('#job-log-filter').val('');
            this.loadJobQueueLogs();
        },

        closeJobDetailsModal: function(e) {
            e.preventDefault();
            $('#job-details-modal').hide();
        },

        handleModalKeydown: function(e) {
            if (e.key === 'Escape') {
                $('#job-details-modal').hide();
            }
        },

        refreshJobs: function(e) {
            e.preventDefault();
            this.loadJobs(true);
        },

        retryLoadJobs: function(e) {
            e.preventDefault();
            this.loadJobs(true);
        },

        /**
         * Job status dashboard methods
         */
        loadJobs: function(showLoading = false) {
            window.ExplainerLogger?.debug('JobMonitoring: loadJobs() called', {
                showLoading: showLoading,
                hasConfig: !!this.jobStatusConfig,
                config: this.jobStatusConfig
            }, 'JobMonitoring');
            
            if (!this.jobStatusConfig) {
                window.ExplainerLogger?.error('JobMonitoring: loadJobs() - No job status config available', {}, 'JobMonitoring');
                return;
            }
            
            if (showLoading) {
                window.ExplainerLogger?.debug('JobMonitoring: Showing loading state', {}, 'JobMonitoring');
                $('#explainer-jobs-list').html('<div class="explainer-loading">Loading jobs...</div>');
            }
            
            const startTime = performance.now();
            window.ExplainerLogger?.debug('JobMonitoring: Starting AJAX request to load jobs', {
                url: this.jobStatusConfig.ajax_url,
                action: 'explainer_get_jobs',
                hasNonce: !!this.jobStatusConfig.nonce
            }, 'JobMonitoring');
            
            $.ajax({
                url: this.jobStatusConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'explainer_get_jobs',
                    nonce: this.jobStatusConfig.nonce
                },
                success: (response) => {
                    const duration = performance.now() - startTime;
                    window.ExplainerLogger?.debug('JobMonitoring: AJAX request successful', {
                        duration: Math.round(duration),
                        responseSuccess: response.success,
                        responseData: response.data,
                        jobsCount: response.data?.jobs?.length || 0
                    }, 'JobMonitoring');
                    
                    if (response.success) {
                        window.ExplainerLogger?.info('JobMonitoring: Jobs loaded successfully', {
                            jobsCount: response.data.jobs.length,
                            jobs: response.data.jobs
                        }, 'JobMonitoring');
                        
                        this.renderJobs(response.data.jobs);
                        this.lastJobsHash = JSON.stringify(response.data.jobs);
                        this.pollsWithoutChange = 0;
                    } else {
                        window.ExplainerLogger?.error('JobMonitoring: Server returned error', {
                            errorMessage: response.data?.message || 'Failed to load jobs',
                            responseData: response.data
                        }, 'JobMonitoring');
                        
                        $('#explainer-jobs-list').html('<div class="explainer-error">Error: ' + (response.data?.message || 'Failed to load jobs') + '</div>');
                    }
                },
                error: (xhr, status, error) => {
                    const duration = performance.now() - startTime;
                    window.ExplainerLogger?.error('JobMonitoring: AJAX request failed', {
                        duration: Math.round(duration),
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        statusCode: xhr.status
                    }, 'JobMonitoring');
                    
                    $('#explainer-jobs-list').html('<div class="explainer-error">Connection error. <button id="retry-load-jobs">Retry</button></div>');
                }
            });
        },

        renderJobs: function(jobs) {
            window.ExplainerLogger?.debug('JobMonitoring: renderJobs() called', {
                jobsExists: !!jobs,
                jobsLength: jobs ? jobs.length : 0,
                jobsData: jobs
            }, 'JobMonitoring');
            
            if (!jobs || jobs.length === 0) {
                window.ExplainerLogger?.info('JobMonitoring: No jobs to render', {
                    jobsIsNull: jobs === null,
                    jobsIsUndefined: jobs === undefined,
                    jobsLength: jobs ? jobs.length : 'N/A'
                }, 'JobMonitoring');
                
                $('#explainer-jobs-list').html('<div class="explainer-no-jobs">No jobs found.</div>');
                return;
            }
            
            window.ExplainerLogger?.debug('JobMonitoring: Rendering jobs HTML', {
                jobsCount: jobs.length
            }, 'JobMonitoring');
            
            let html = '';
            jobs.forEach((job, index) => {
                window.ExplainerLogger?.debug(`JobMonitoring: Rendering job ${index + 1}/${jobs.length}`, {
                    jobId: job.id,
                    jobStatus: job.status,
                    jobData: job
                }, 'JobMonitoring');
                
                html += this.renderJobTemplate(job);
            });
            
            window.ExplainerLogger?.debug('JobMonitoring: Setting HTML content', {
                htmlLength: html.length,
                targetElement: '#explainer-jobs-list'
            }, 'JobMonitoring');
            
            $('#explainer-jobs-list').html(html);
            
            window.ExplainerLogger?.debug('JobMonitoring: Binding job actions', {}, 'JobMonitoring');
            this.bindJobActions();
            
            window.ExplainerLogger?.info('JobMonitoring: Jobs rendered successfully', {
                jobsCount: jobs.length,
                finalHtmlLength: html.length
            }, 'JobMonitoring');
        },

        renderJobTemplate: function(job) {
            window.ExplainerLogger?.debug('JobMonitoring: renderJobTemplate() called', {
                jobId: job.id,
                jobData: job
            }, 'JobMonitoring');
            
            // Get template from script tag
            let templateElement = document.getElementById('job-item-template');
            if (!templateElement) {
                window.ExplainerLogger?.error('JobMonitoring: Job template element not found', {
                    templateId: 'job-item-template',
                    availableElements: Array.from(document.querySelectorAll('script[type="text/template"]')).map(el => el.id)
                }, 'JobMonitoring');
                return '<div>Job template not available</div>';
            }
            
            window.ExplainerLogger?.debug('JobMonitoring: Template element found', {
                templateLength: templateElement.innerHTML.length
            }, 'JobMonitoring');
            
            let template = templateElement.innerHTML;
            
            // Replace template variables
            const replacements = {
                job_id: job.job_id,
                title: this.escapeHtml(job.title || job.selection_preview || job.selection_text || 'Untitled'),
                selection_preview: this.escapeHtml(job.selection_preview || job.selection_text || 'Untitled'),
                status: job.status,
                status_text: this.getStatusText(job.status),
                error_message: this.escapeHtml(job.error_message || ''),
                completed_at: job.completed_at || '',
                edit_link: job.edit_link || '',
                preview_link: job.preview_link || '',
                queue_position: job.queue_position || '1',
                progress_percent: job.progress_percent || '0',
                progress_text: job.progress_text || 'Processing...'
            };
            
            window.ExplainerLogger?.debug('JobMonitoring: Template replacements', {
                replacements: replacements
            }, 'JobMonitoring');
            
            Object.entries(replacements).forEach(([key, value]) => {
                const regex = new RegExp(`\\{\\{${key}\\}\\}`, 'g');
                template = template.replace(regex, value);
            });
            
            // Handle conditional template blocks
            window.ExplainerLogger?.debug('JobMonitoring: Processing conditional blocks', {
                jobStatus: job.status
            }, 'JobMonitoring');
            
            template = this.processConditionalBlocks(template, job);
            
            window.ExplainerLogger?.debug('JobMonitoring: Template processing complete', {
                finalTemplateLength: template.length
            }, 'JobMonitoring');
            
            return template;
        },

        /**
         * Process conditional template blocks
         */
        processConditionalBlocks: function(template, job) {
            // Handle conditional visibility based on job status
            const isProcessing = job.status === 'processing';
            const isFailed = job.status === 'failed';
            const isCompleted = job.status === 'completed';
            const isPending = job.status === 'pending';
            
            // Process {{#unless_processing}}...{{/unless_processing}}
            template = template.replace(/\{\{#unless_processing\}\}([\s\S]*?)\{\{\/unless_processing\}\}/g, function(match, content) {
                return isProcessing ? '' : content;
            });
            
            // Process {{#unless_failed}}...{{/unless_failed}}
            template = template.replace(/\{\{#unless_failed\}\}([\s\S]*?)\{\{\/unless_failed\}\}/g, function(match, content) {
                return isFailed ? '' : content;
            });
            
            // Process {{#unless_completed}}...{{/unless_completed}}
            template = template.replace(/\{\{#unless_completed\}\}([\s\S]*?)\{\{\/unless_completed\}\}/g, function(match, content) {
                return isCompleted ? '' : content;
            });
            
            // Process {{#unless_pending}}...{{/unless_pending}}
            template = template.replace(/\{\{#unless_pending\}\}([\s\S]*?)\{\{\/unless_pending\}\}/g, function(match, content) {
                return isPending ? '' : content;
            });
            
            
            return template;
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
                    window.ExplainerLogger?.info('JobMonitoring: Real-time system initialized successfully', {
                        adapterType: this.realtimeAdapter.constructor.name
                    }, 'JobMonitoring');
                } else {
                    this.fallbackMode = true;
                    window.ExplainerLogger?.warning('JobMonitoring: Real-time system not available, falling back to polling', {}, 'JobMonitoring');
                }
            } catch (error) {
                this.fallbackMode = true;
                window.ExplainerLogger?.error('JobMonitoring: Failed to initialize real-time system, falling back to polling', {
                    error: error.message
                }, 'JobMonitoring');
            }
        },

        /**
         * Subscribe to job-related events
         */
        subscribeToJobEvents: function() {
            if (!this.isRealtimeEnabled || !this.realtimeAdapter) {
                return;
            }

            // Subscribe to job status updates
            this.realtimeAdapter.subscribe('job:status', (data) => {
                window.ExplainerLogger?.debug('JobMonitoring: Received job status update', {
                    data: data
                }, 'JobMonitoring');
                
                // Refresh jobs list
                this.loadJobs(false);
            });

            this.subscribedTopics.add('job:status');
            window.ExplainerLogger?.info('JobMonitoring: Subscribed to job events', {
                topics: Array.from(this.subscribedTopics)
            }, 'JobMonitoring');
        },

        /**
         * Subscribe to queue status events
         */
        subscribeToQueueStatusEvents: function() {
            if (!this.isRealtimeEnabled || !this.realtimeAdapter) {
                return;
            }

            // Subscribe to queue updates
            this.realtimeAdapter.subscribe('job_queue:list', (data) => {
                window.ExplainerLogger?.debug('JobMonitoring: Received queue update', {
                    data: data
                }, 'JobMonitoring');
                
                // Refresh queue status
                this.loadQueueStatus(false);
            });

            this.subscribedTopics.add('job_queue:list');
            window.ExplainerLogger?.info('JobMonitoring: Subscribed to queue status events', {}, 'JobMonitoring');
        },

        /**
         * Start polling (fallback when real-time is not available)
         */
        startPolling: function() {
            if (this.pollInterval) {
                return; // Already polling
            }

            window.ExplainerLogger?.info('JobMonitoring: Starting job status polling (fallback mode)', {}, 'JobMonitoring');

            this.pollInterval = setInterval(() => {
                // Only poll if page is visible (avoid unnecessary load when user is away)
                if (!document.hidden) {
                    this.loadJobs(false);
                }
            }, 15000); // Poll every 15 seconds

            // Stop polling when page becomes hidden for more than 2 minutes
            let hiddenTimeout;
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    hiddenTimeout = setTimeout(() => {
                        this.stopPolling();
                        window.ExplainerLogger?.debug('JobMonitoring: Stopped polling due to page being hidden', {}, 'JobMonitoring');
                    }, 120000); // 2 minutes
                } else {
                    clearTimeout(hiddenTimeout);
                    if (!this.pollInterval) {
                        this.startPolling(); // Resume polling
                        window.ExplainerLogger?.debug('JobMonitoring: Resumed polling as page became visible', {}, 'JobMonitoring');
                    }
                }
            });
        },

        /**
         * Stop polling
         */
        stopPolling: function() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
                window.ExplainerLogger?.debug('JobMonitoring: Stopped job status polling', {}, 'JobMonitoring');
            }
        },

        /**
         * Bind job action buttons
         */
        bindJobActions: function() {
            // Cancel job action
            $('.cancel-job').off('click').on('click', this.cancelJob.bind(this));
            
            // Retry job action
            $('.retry-job').off('click').on('click', this.retryJob.bind(this));
            
            // Run single job action
            $('.run-job-btn').off('click').on('click', this.runSingleJob.bind(this));
        },

        /**
         * Cancel a job
         */
        cancelJob: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const jobId = $button.data('job-id');
            
            if (!jobId) {
                this.showMessage('Invalid job ID', 'error');
                return;
            }
            
            const originalText = $button.text();
            $button.text('Cancelling...').prop('disabled', true);
            
            const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
            const nonce = window.wpAiExplainer?.nonce;

            if (!ajaxUrl || !nonce) {
                this.showMessage('AJAX configuration not found', 'error');
                $button.text(originalText).prop('disabled', false);
                return;
            }
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'explainer_cancel_job',
                    nonce: nonce,
                    job_id: jobId
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage('Job cancelled successfully', 'success');
                        this.loadJobs(false); // Refresh job list
                    } else {
                        this.showMessage('Failed to cancel job: ' + (response.data?.message || 'Unknown error'), 'error');
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: () => {
                    this.showMessage('Connection error while cancelling job', 'error');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Retry a job
         */
        retryJob: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const jobId = $button.data('job-id');
            
            if (!jobId) {
                this.showMessage('Invalid job ID', 'error');
                return;
            }
            
            const originalText = $button.text();
            $button.text('Retrying...').prop('disabled', true);
            
            const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
            const nonce = window.wpAiExplainer?.nonce;

            if (!ajaxUrl || !nonce) {
                this.showMessage('AJAX configuration not found', 'error');
                $button.text(originalText).prop('disabled', false);
                return;
            }
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'explainer_retry_job',
                    nonce: nonce,
                    job_id: jobId
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage('Job queued for retry', 'success');
                        this.loadJobs(false); // Refresh job list
                    } else {
                        this.showMessage('Failed to retry job: ' + (response.data?.message || 'Unknown error'), 'error');
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: () => {
                    this.showMessage('Connection error while retrying job', 'error');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Run a single job
         */
        runSingleJob: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const jobId = $button.data('job-id');
            const jobType = $button.data('job-type') || 'blog_creation';
            
            if (!jobId) {
                this.showMessage('Invalid job ID', 'error');
                return;
            }
            
            // Use the shared JobQueueAdmin functionality if available
            if (window.JobQueueAdmin) {
                window.JobQueueAdmin.handleRunJob.call($button[0], e);
                return;
            }
            
            // Fallback implementation
            const originalText = $button.text();
            $button.text('Running...').prop('disabled', true);
            
            const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
            const nonce = window.wpAiExplainer?.nonce;

            if (!ajaxUrl || !nonce) {
                this.showMessage('AJAX configuration not found', 'error');
                $button.text(originalText).prop('disabled', false);
                return;
            }
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'explainer_run_single_job',
                    nonce: nonce,
                    job_id: jobId,
                    job_type: jobType
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage('Job completed successfully', 'success');
                        this.loadJobs(false); // Refresh job list
                    } else {
                        this.showMessage('Job failed: ' + (response.data?.message || 'Unknown error'), 'error');
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: () => {
                    this.showMessage('Connection error while running job', 'error');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Show job details modal
         */
        showJobDetails: function(jobId) {
            const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
            const nonce = window.wpAiExplainer?.nonce;

            if (!ajaxUrl || !nonce) {
                this.showMessage('AJAX configuration not found', 'error');
                return;
            }
            
            // Show modal with loading state
            $('#job-details-modal').show();
            $('#job-details-content').html('<div style="text-align: center; padding: 20px;">Loading job details...</div>');
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'explainer_get_job_details',
                    nonce: nonce,
                    job_id: jobId
                },
                success: (response) => {
                    if (response.success) {
                        this.renderJobDetails(response.data);
                    } else {
                        $('#job-details-content').html('<div style="color: red; padding: 20px;">Error: ' + (response.data?.message || 'Failed to load job details') + '</div>');
                    }
                },
                error: () => {
                    $('#job-details-content').html('<div style="color: red; padding: 20px;">Connection error loading job details</div>');
                }
            });
        },

        /**
         * Render job details modal content
         */
        renderJobDetails: function(job) {
            let html = '<div style="padding: 20px;">';
            html += `<h3>Job #${job.id} Details</h3>`;
            html += `<p><strong>Status:</strong> <span class="job-status-${job.status}">${this.getStatusText(job.status)}</span></p>`;
            html += `<p><strong>Created:</strong> ${job.created_at}</p>`;
            
            if (job.completed_at) {
                html += `<p><strong>Completed:</strong> ${job.completed_at}</p>`;
            }
            
            if (job.error_message) {
                html += `<p><strong>Error:</strong> <span style="color: red;">${this.escapeHtml(job.error_message)}</span></p>`;
            }
            
            if (job.title) {
                html += `<p><strong>Title:</strong> ${this.escapeHtml(job.title)}</p>`;
            }
            
            if (job.selection_text) {
                html += `<p><strong>Selection:</strong></p>`;
                html += `<div style="background: #f9f9f9; padding: 10px; border-radius: 4px; margin: 10px 0; max-height: 200px; overflow-y: auto;">${this.escapeHtml(job.selection_text)}</div>`;
            }
            
            html += '</div>';
            $('#job-details-content').html(html);
        },

        /**
         * Clear completed jobs
         */
        clearCompletedJobs: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const originalText = $button.text();
            
            // Confirm action
            if (!confirm('Are you sure you want to clear all completed jobs? This action cannot be undone.')) {
                return;
            }
            
            $button.text('Clearing...').prop('disabled', true);
            
            const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
            const nonce = window.wpAiExplainer?.nonce;

            if (!ajaxUrl || !nonce) {
                this.showMessage('AJAX configuration not found', 'error');
                $button.text(originalText).prop('disabled', false);
                return;
            }
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'explainer_clear_completed_jobs',
                    nonce: nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage(`Cleared ${response.data.cleared_count} completed jobs`, 'success');
                        this.loadJobs(false); // Refresh job list
                    } else {
                        this.showMessage('Failed to clear jobs: ' + (response.data?.message || 'Unknown error'), 'error');
                    }
                },
                error: () => {
                    this.showMessage('Connection error while clearing jobs', 'error');
                },
                complete: () => {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Cleanup when module is unloaded
         */
        destroy: function() {
            // Stop polling
            this.stopPolling();
            
            // Clear refresh interval
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
            
            // Unsubscribe from real-time events
            if (this.isRealtimeEnabled && this.realtimeAdapter) {
                this.subscribedTopics.forEach(topic => {
                    try {
                        this.realtimeAdapter.disconnect(topic);
                    } catch (error) {
                        window.ExplainerLogger?.error('JobMonitoring: Error unsubscribing from topic', {
                            topic: topic,
                            error: error.message
                        }, 'JobMonitoring');
                    }
                });
                this.subscribedTopics.clear();
            }
            
            window.ExplainerLogger?.info('JobMonitoring: Module destroyed and cleaned up', {}, 'JobMonitoring');
        },

        /**
         * Update job queue UI based on cron setting
         * Controls visibility of manual run buttons and status polling
         */
        updateJobQueueUI: function() {
            const cronEnabled = $('#explainer_enable_cron').is(':checked');
            
            window.ExplainerLogger?.info('JobMonitoring: Updating job queue UI', {
                cronEnabled: cronEnabled,
                currentPolling: !!this.pollInterval
            }, 'JobMonitoring');

            if (cronEnabled) {
                // Cron enabled mode: hide manual run buttons, enable auto status updates
                this.setCronEnabledMode();
            } else {
                // Cron disabled mode: show manual run buttons, disable auto polling
                this.setCronDisabledMode();
            }
        },

        /**
         * Set UI for cron enabled mode
         */
        setCronEnabledMode: function() {
            // Hide manual run buttons
            $('.run-job-btn').hide();
            
            // Disable cancel buttons for processing jobs
            $('.cancel-job').each(function() {
                const $btn = $(this);
                const $row = $btn.closest('tr');
                const status = $row.find('.job-status').text().toLowerCase().trim();
                
                if (status === 'processing') {
                    $btn.prop('disabled', true)
                         .addClass('disabled')
                         .attr('title', 'Cannot cancel jobs when server cron is enabled');
                }
            });
            
            // Start frequent AJAX status checks
            this.startStatusPolling();
            
            window.ExplainerLogger?.info('JobMonitoring: Cron enabled mode activated', {}, 'JobMonitoring');
        },

        /**
         * Set UI for cron disabled mode  
         */
        setCronDisabledMode: function() {
            // Show manual run buttons for pending jobs
            $('.run-job-btn').show();
            
            // Enable cancel buttons
            $('.cancel-job').prop('disabled', false)
                           .removeClass('disabled')
                           .removeAttr('title');
            
            // Stop frequent status polling (WP cron will handle background processing)
            this.stopStatusPolling();
            
            window.ExplainerLogger?.info('JobMonitoring: Cron disabled mode activated', {}, 'JobMonitoring');
        },

        /**
         * Start frequent status polling for cron enabled mode
         */
        startStatusPolling: function() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
            }
            
            // Poll every 2 seconds when cron is enabled
            this.pollInterval = setInterval(() => {
                this.refreshJobStatuses();
            }, 2000);
            
            window.ExplainerLogger?.debug('JobMonitoring: Status polling started (2s interval)', {}, 'JobMonitoring');
        },

        /**
         * Stop status polling for cron disabled mode
         */
        stopStatusPolling: function() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
                window.ExplainerLogger?.debug('JobMonitoring: Status polling stopped', {}, 'JobMonitoring');
            }
        },

        /**
         * Refresh job statuses via AJAX
         */
        refreshJobStatuses: function() {
            const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
            const nonce = window.wpAiExplainer?.nonce;
            
            if (!ajaxUrl || !nonce) {
                window.ExplainerLogger?.warning('JobMonitoring: Missing AJAX config for status refresh', {}, 'JobMonitoring');
                return;
            }
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_ai_explainer_get_job_statuses',
                    nonce: nonce
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.updateJobRows(response.data.jobs);
                    }
                },
                error: (xhr, status, error) => {
                    window.ExplainerLogger?.error('JobMonitoring: Failed to refresh job statuses', {
                        status: status,
                        error: error
                    }, 'JobMonitoring');
                }
            });
        },

        /**
         * Update job table rows with new status data
         */
        updateJobRows: function(jobs) {
            if (!jobs || !Array.isArray(jobs)) {
                return;
            }
            
            jobs.forEach(job => {
                const $row = $(`tr[data-job-id="${job.id}"]`);
                if (!$row.length) return;
                
                // Update status badge
                const $statusBadge = $row.find('.job-status');
                const currentStatus = $statusBadge.text().toLowerCase().trim();
                const newStatus = job.status.toLowerCase();
                
                if (currentStatus !== newStatus) {
                    $statusBadge.removeClass()
                               .addClass('job-status')
                               .addClass(`status-${newStatus}`)
                               .text(this.getStatusText(newStatus));
                    
                    // Update action buttons based on new status
                    this.updateActionButtons($row, newStatus);
                    
                    window.ExplainerLogger?.debug('JobMonitoring: Updated job status', {
                        jobId: job.id,
                        oldStatus: currentStatus,
                        newStatus: newStatus
                    }, 'JobMonitoring');
                }
                
                // Update result column if job completed
                if (newStatus === 'completed' && job.result) {
                    this.updateResultColumn($row, job);
                }
            });
        },

        /**
         * Update action buttons based on job status
         */
        updateActionButtons: function($row, status) {
            const $actions = $row.find('.column-actions, td:last-child');
            const cronEnabled = $('#explainer_enable_cron').is(':checked');
            
            // Clear existing action buttons
            $actions.find('.btn-base').remove();
            
            // Add appropriate buttons based on status and cron mode
            if (status === 'pending' && !cronEnabled) {
                $actions.prepend('<button class="btn-base btn-primary btn-sm run-job-btn" data-job-id="' + $row.data('job-id') + '"><span class="dashicons dashicons-controls-play"></span> Run Job</button>');
            }
            
            if (status === 'failed') {
                $actions.prepend('<button class="btn-base btn-secondary btn-sm retry-job" data-job-id="' + $row.data('job-id') + '"><span class="dashicons dashicons-update"></span> Retry</button>');
            }
            
            if (['pending', 'processing'].includes(status)) {
                const disabled = cronEnabled && status === 'processing' ? ' disabled' : '';
                $actions.append('<button class="btn-base btn-outline btn-sm cancel-job' + disabled + '" data-job-id="' + $row.data('job-id') + '"><span class="dashicons dashicons-no"></span> Cancel</button>');
            }
        },

        /**
         * Update result column for completed jobs
         */
        updateResultColumn: function($row, job) {
            const $resultCol = $row.find('.job-result-column');
            if (job.result && job.result.post_id) {
                const resultHtml = `
                    <div class="completed-post">
                        <a href="${job.result.edit_url}" class="post-link" target="_blank">
                            ${this.escapeHtml(job.result.title)}
                        </a>
                        <div class="post-actions">
                            <a href="${job.result.view_url}" target="_blank" class="view-post">View</a>
                            <a href="${job.result.edit_url}" class="edit-post">Edit</a>
                        </div>
                    </div>
                `;
                $resultCol.html(resultHtml);
            }
        },

        /**
         * Utility methods
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        getStatusColor: function(status) {
            const colors = {
                'pending': '#ffc107',
                'processing': '#17a2b8',
                'completed': '#28a745',
                'failed': '#dc3545',
                'cancelled': '#6c757d'
            };
            return colors[status] || '#6c757d';
        },

        getStatusText: function(status) {
            const texts = {
                'pending': 'Pending',
                'processing': 'Processing',
                'completed': 'Completed',
                'failed': 'Failed',
                'cancelled': 'Cancelled'
            };
            return texts[status] || 'Unknown';
        },

        showMessage: function(message, type = 'info') {
            // Try to use the notification system if available
            if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                window.ExplainerPlugin.Notifications[type](message);
            } else {
                // Fallback to console
                console.log(`[${type.toUpperCase()}] ${message}`);
            }
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        if (window.WPAIExplainer && window.WPAIExplainer.Admin) {
            window.WPAIExplainer.Admin.JobMonitoring.init();
        }
    });

})(jQuery);