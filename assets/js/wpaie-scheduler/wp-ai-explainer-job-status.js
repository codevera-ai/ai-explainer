/**
 * WP AI Explainer Job Status Polling
 * 
 * Simple AJAX polling for job progress updates with WPAIE Scheduler integration
 * 
 * @package WP_AI_Explainer
 * @subpackage WPAIEScheduler
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    const JobStatusManager = {
        
        /**
         * Active polling intervals
         */
        activePolls: new Map(),
        
        /**
         * Polling configuration
         */
        config: {
            pollingInterval: 2000,
            maxRetries: 3,
            retryDelay: 5000
        },
        
        /**
         * Initialise the job status manager
         */
        init: function() {
            // Get configuration from WordPress
            if (typeof wpAIExplainerJobStatus !== 'undefined') {
                this.config.pollingInterval = wpAIExplainerJobStatus.polling_interval || 2000;
            }
            
            // Initialise active job polling
            this.initActiveJobPolling();
            
            // Bind event handlers
            this.bindEventHandlers();
            
            // Set up periodic cleanup
            this.setupCleanup();
            
            console.log('[WP AI Explainer] Job status manager initialised');
        },
        
        /**
         * Initialize polling for active jobs on page load
         */
        initActiveJobPolling: function() {
            const activeJobs = $('.job-progress-item[data-progress-id]');
            
            activeJobs.each(function() {
                const progressId = $(this).data('progress-id');
                const status = JobStatusManager.getJobStatusFromElement($(this));
                
                // Only poll if job is active
                if (progressId && ['pending', 'processing'].includes(status)) {
                    JobStatusManager.startPolling(progressId);
                }
            });
            
            console.log(`[WP AI Explainer] Started polling for ${this.activePolls.size} active jobs`);
        },
        
        /**
         * Bind event handlers for job actions
         */
        bindEventHandlers: function() {
            // Cancel job button
            $(document).on('click', '.cancel-job', function(e) {
                e.preventDefault();
                
                const progressId = $(this).data('progress-id');
                const confirmMessage = wpAIExplainerJobStatus.strings?.cancel_confirm || 
                                     'Are you sure you want to cancel this job?';
                
                if (progressId) {
                    var $button = $(this); // Capture the button reference
                    // Use the notification system's confirm method
                    window.ExplainerPlugin.Notifications.confirm(
                        confirmMessage,
                        {
                            title: 'Cancel Job',
                            confirmText: 'Cancel Job',
                            cancelText: 'Keep Running'
                        }
                    ).then(function(confirmed) {
                        if (confirmed) {
                            JobStatusManager.cancelJob(progressId, $button);
                        }
                    });
                }
            });
            
            // Retry job button
            $(document).on('click', '.retry-job', function(e) {
                e.preventDefault();
                
                const progressId = $(this).data('progress-id');
                if (progressId) {
                    JobStatusManager.retryJob(progressId, $(this));
                }
            });
            
            // Manual refresh button if present
            $(document).on('click', '.refresh-jobs', function(e) {
                e.preventDefault();
                JobStatusManager.refreshAllJobs();
            });
        },
        
        /**
         * Set up periodic cleanup of completed polls
         */
        setupCleanup: function() {
            setInterval(() => {
                this.cleanupCompletedPolls();
            }, 30000); // Clean up every 30 seconds
        },
        
        /**
         * Start polling for a specific job
         * 
         * @param {string} progressId - Progress ID to poll
         */
        startPolling: function(progressId) {
            // Don't start if already polling
            if (this.activePolls.has(progressId)) {
                console.log(`[WP AI Explainer] Already polling job ${progressId}`);
                return;
            }
            
            const pollData = {
                intervalId: null,
                retryCount: 0,
                lastUpdate: Date.now()
            };
            
            // Start the polling interval
            pollData.intervalId = setInterval(() => {
                this.checkJobStatus(progressId, pollData);
            }, this.config.pollingInterval);
            
            // Store the polling data
            this.activePolls.set(progressId, pollData);
            
            console.log(`[WP AI Explainer] Started polling job ${progressId}`);
            
            // Check status immediately
            this.checkJobStatus(progressId, pollData);
        },
        
        /**
         * Stop polling for a specific job
         * 
         * @param {string} progressId - Progress ID to stop polling
         */
        stopPolling: function(progressId) {
            if (this.activePolls.has(progressId)) {
                const pollData = this.activePolls.get(progressId);
                clearInterval(pollData.intervalId);
                this.activePolls.delete(progressId);
                
                console.log(`[WP AI Explainer] Stopped polling job ${progressId}`);
            }
        },
        
        /**
         * Check job status via AJAX
         * 
         * @param {string} progressId - Progress ID to check
         * @param {Object} pollData - Polling metadata
         */
        checkJobStatus: function(progressId, pollData) {
            $.ajax({
                url: wpAIExplainerJobStatus.ajaxurl,
                type: 'GET',
                data: {
                    action: 'wp_ai_explainer_get_job_status',
                    progress_id: progressId,
                    nonce: wpAIExplainerJobStatus.nonce
                },
                timeout: 10000, // 10 second timeout
                success: (response) => {
                    pollData.retryCount = 0; // Reset retry count on success
                    pollData.lastUpdate = Date.now();
                    
                    if (response.success && response.data) {
                        this.updateJobDisplay(progressId, response.data);
                        
                        // Stop polling if job is complete
                        if (['completed', 'failed'].includes(response.data.status)) {
                            this.stopPolling(progressId);
                        }
                    } else {
                        console.warn(`[WP AI Explainer] Invalid response for job ${progressId}:`, response);
                        this.handlePollingError(progressId, pollData, response.data || 'Invalid response');
                    }
                },
                error: (xhr, status, error) => {
                    console.error(`[WP AI Explainer] Failed to check status for job ${progressId}:`, error);
                    this.handlePollingError(progressId, pollData, error);
                }
            });
        },
        
        /**
         * Handle polling errors with retry logic
         * 
         * @param {string} progressId - Progress ID
         * @param {Object} pollData - Polling metadata
         * @param {string} error - Error message
         */
        handlePollingError: function(progressId, pollData, error) {
            pollData.retryCount++;
            
            if (pollData.retryCount >= this.config.maxRetries) {
                console.error(`[WP AI Explainer] Max retries exceeded for job ${progressId}, stopping polling`);
                this.stopPolling(progressId);
                this.showJobError(progressId, 'Connection lost - please refresh the page');
            } else {
                console.warn(`[WP AI Explainer] Retry ${pollData.retryCount}/${this.config.maxRetries} for job ${progressId}`);
                
                // Add delay before next retry
                setTimeout(() => {
                    this.checkJobStatus(progressId, pollData);
                }, this.config.retryDelay);
            }
        },
        
        /**
         * Update job display with new status data
         * 
         * @param {string} progressId - Progress ID
         * @param {Object} jobData - Job status data
         */
        updateJobDisplay: function(progressId, jobData) {
            const jobElement = $(`.job-progress-item[data-progress-id="${progressId}"]`);
            const rowElement = $(`.job-row[data-progress-id="${progressId}"]`);
            
            if (jobElement.length) {
                this.updateProgressItem(jobElement, jobData);
            }

            if (rowElement.length) {
                this.updateTableRow(rowElement, jobData);
            }
        },
        
        /**
         * Update progress item display
         * 
         * @param {jQuery} element - Job element
         * @param {Object} jobData - Job data
         */
        updateProgressItem: function(element, jobData) {
            // Update progress bar
            const progressBar = element.find('.progress-bar');
            if (progressBar.length) {
                progressBar.css('width', Math.max(0, Math.min(100, jobData.progress_percent)) + '%');
            }
            
            // Update progress text
            const progressText = element.find('.progress-text');
            if (progressText.length && jobData.progress_text) {
                progressText.text(jobData.progress_text);
            }
            
            // Update progress percentage
            const progressPercent = element.find('.progress-percent');
            if (progressPercent.length) {
                progressPercent.text(Math.max(0, Math.min(100, jobData.progress_percent)) + '%');
            }
            
            // Update status class
            element.removeClass('status-pending status-processing status-completed status-failed');
            element.addClass('status-' + jobData.status);
            
            // Handle completion or failure
            if (jobData.status === 'completed') {
                this.handleJobCompletion(element, jobData);
            } else if (jobData.status === 'failed') {
                this.handleJobFailure(element, jobData);
            }
        },
        
        /**
         * Update table row display
         * 
         * @param {jQuery} element - Row element
         * @param {Object} jobData - Job data
         */
        updateTableRow: function(element, jobData) {
            // Update status badge
            const statusBadge = element.find('.status-badge');
            if (statusBadge.length) {
                statusBadge.removeClass('status-pending status-processing status-completed status-failed');
                statusBadge.addClass('status-' + jobData.status);
                statusBadge.text(jobData.status.charAt(0).toUpperCase() + jobData.status.slice(1));
            }
            
            // Update mini progress bar
            const miniProgressFill = element.find('.mini-progress-fill');
            if (miniProgressFill.length) {
                miniProgressFill.css('width', Math.max(0, Math.min(100, jobData.progress_percent)) + '%');
            }
            
            // Update progress text
            const progressTextSmall = element.find('.progress-text-small');
            if (progressTextSmall.length) {
                progressTextSmall.text(Math.max(0, Math.min(100, jobData.progress_percent)) + '%');
            }
            
            // Update row status class
            element.removeClass('status-pending status-processing status-completed status-failed');
            element.addClass('status-' + jobData.status);
        },

        /**
         * Handle job completion
         * 
         * @param {jQuery} element - Job element
         * @param {Object} jobData - Job data
         */
        handleJobCompletion: function(element, jobData) {
            // Add completion actions if result data is available
            if (jobData.result_data) {
                const resultData = typeof jobData.result_data === 'string' ? 
                    JSON.parse(jobData.result_data) : jobData.result_data;
                
                // For blog creation jobs, show post links
                if (resultData.post_url || resultData.edit_url) {
                    let successActions = element.find('.job-success-actions');
                    if (!successActions.length) {
                        successActions = $('<div class="job-success-actions"></div>');
                        element.find('.progress-container').append(successActions);
                    }
                    
                    successActions.empty();
                    
                    if (resultData.post_url) {
                        successActions.append(
                            $('<a>').attr('href', resultData.post_url)
                                   .attr('target', '_blank')
                                   .text('View Post')
                                   .addClass('button button-small')
                        );
                    }
                    
                    if (resultData.edit_url) {
                        if (resultData.post_url) {
                            successActions.append(' ');
                        }
                        successActions.append(
                            $('<a>').attr('href', resultData.edit_url)
                                   .attr('target', '_blank')
                                   .text('Edit Post')
                                   .addClass('button button-small button-primary')
                        );
                    }
                }
            }
            
            // Remove cancel button if present
            element.find('.cancel-job').remove();
        },
        
        /**
         * Handle job failure
         * 
         * @param {jQuery} element - Job element
         * @param {Object} jobData - Job data
         */
        handleJobFailure: function(element, jobData) {
            // Show error message if available
            if (jobData.error_message) {
                let errorMessage = element.find('.job-error-message');
                if (!errorMessage.length) {
                    errorMessage = $('<div class="job-error-message"></div>');
                    element.find('.progress-container').append(errorMessage);
                }
                
                errorMessage.text('Error: ' + jobData.error_message);
            }
            
            // Replace cancel button with retry button
            const cancelButton = element.find('.cancel-job');
            if (cancelButton.length) {
                cancelButton.removeClass('cancel-job')
                          .addClass('retry-job')
                          .text('Retry');
            }
        },
        
        /**
         * Show job error in UI
         * 
         * @param {string} progressId - Progress ID
         * @param {string} message - Error message
         */
        showJobError: function(progressId, message) {
            const jobElement = $(`.job-progress-item[data-progress-id="${progressId}"]`);
            if (jobElement.length) {
                let errorMessage = jobElement.find('.job-polling-error');
                if (!errorMessage.length) {
                    errorMessage = $('<div class="job-polling-error"></div>');
                    jobElement.find('.progress-container').append(errorMessage);
                }
                
                errorMessage.text(message).css({
                    color: '#d63638',
                    fontSize: '12px',
                    fontStyle: 'italic',
                    marginTop: '5px'
                });
            }
        },
        
        /**
         * Cancel a job
         * 
         * @param {string} progressId - Progress ID
         * @param {jQuery} button - Cancel button element
         */
        cancelJob: function(progressId, button) {
            const originalText = button.text();
            const cancellingText = wpAIExplainerJobStatus.strings?.cancelling || 'Cancelling...';
            
            button.prop('disabled', true).text(cancellingText);
            
            $.ajax({
                url: wpAIExplainerJobStatus.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wp_ai_explainer_cancel_job',
                    progress_id: progressId,
                    nonce: wpAIExplainerJobStatus.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Stop polling
                        this.stopPolling(progressId);
                        
                        // Update UI to show cancelled state
                        const jobElement = $(`.job-progress-item[data-progress-id="${progressId}"]`);
                        if (jobElement.length) {
                            jobElement.addClass('status-failed');
                            jobElement.find('.progress-text').text('Cancelled by user');
                            button.remove();
                        }
                        
                        console.log(`[WP AI Explainer] Job ${progressId} cancelled successfully`);
                    } else {
                        button.prop('disabled', false).text(originalText);
                        window.ExplainerPlugin.Notifications.error(response.data || 'Failed to cancel job');
                    }
                },
                error: (xhr, status, error) => {
                    button.prop('disabled', false).text(originalText);
                    window.ExplainerPlugin.Notifications.error('Failed to cancel job: ' + error);
                    console.error(`[WP AI Explainer] Failed to cancel job ${progressId}:`, error);
                }
            });
        },
        
        /**
         * Retry a failed job
         * 
         * @param {string} progressId - Progress ID
         * @param {jQuery} button - Retry button element
         */
        retryJob: function(progressId, button) {
            var self = this;
            
            if (!progressId) {
                console.error('[WP AI Explainer] No progress ID provided for retry');
                return;
            }
            
            // Disable button during request
            button.prop('disabled', true).text('Retrying...');
            
            // Make AJAX request to retry the job
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'explainer_retry_job',
                    nonce: wpaiexplainer_job_vars.nonce || $('input[name="nonce"]').val(),
                    job_id: progressId
                },
                success: function(response) {
                    if (response.success) {
                        console.log('[WP AI Explainer] Job retry successful:', response);
                        
                        // Update the progress container to show pending status
                        var progressContainer = button.closest('[data-progress-id="' + progressId + '"]');
                        if (progressContainer.length) {
                            progressContainer.find('.progress-status').text('pending');
                            progressContainer.find('.progress-message').text('Job reset successfully. It will be processed again.');
                            
                            // Remove error styling and retry button
                            progressContainer.removeClass('error failed').addClass('pending');
                            button.remove();
                        }
                        
                        // Refresh status after a short delay
                        setTimeout(function() {
                            self.refreshJobStatus(progressId);
                        }, 1000);
                        
                    } else {
                        console.error('[WP AI Explainer] Job retry failed:', response);
                        window.ExplainerPlugin.Notifications.error('Failed to retry job: ' + (response.data && response.data.message || 'Unknown error'));
                        
                        // Re-enable button
                        button.prop('disabled', false).text('Retry');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[WP AI Explainer] AJAX error during job retry:', error);
                    window.ExplainerPlugin.Notifications.error('Failed to retry job. Please try again or refresh the page.');
                    
                    // Re-enable button
                    button.prop('disabled', false).text('Retry');
                }
            });
        },
        
        /**
         * Refresh all jobs on the page
         */
        refreshAllJobs: function() {
            console.log('[WP AI Explainer] Refreshing all jobs');
            location.reload();
        },
        
        /**
         * Get job status from DOM element
         * 
         * @param {jQuery} element - Job element
         * @return {string} Job status
         */
        getJobStatusFromElement: function(element) {
            const classList = element.attr('class').split(/\s+/);
            for (let className of classList) {
                if (className.startsWith('status-')) {
                    return className.replace('status-', '');
                }
            }
            return 'unknown';
        },
        
        /**
         * Clean up completed polling intervals
         */
        cleanupCompletedPolls: function() {
            const now = Date.now();
            const staleThreshold = 5 * 60 * 1000; // 5 minutes
            
            for (let [progressId, pollData] of this.activePolls) {
                // Remove polls that haven't been updated in a while
                if (now - pollData.lastUpdate > staleThreshold) {
                    console.log(`[WP AI Explainer] Cleaning up stale poll for job ${progressId}`);
                    this.stopPolling(progressId);
                }
            }
        },
        
        /**
         * Public method to start a job and begin polling
         * 
         * @param {string} jobType - Job type (blog_creation, post_analysis, etc.)
         * @param {Object} jobData - Job data
         */
        startJob: function(jobType, jobData) {
            const actionName = 'wp_ai_explainer_queue_' + jobType;
            
            $.ajax({
                url: wpAIExplainerJobStatus.ajaxurl,
                type: 'POST',
                data: {
                    action: actionName,
                    ...jobData,
                    nonce: wpAIExplainerJobStatus.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Add job to display if we have a container
                        this.addJobToDisplay(response.data);
                        
                        // Start polling
                        this.startPolling(response.data.progress_id);
                    } else {
                        window.ExplainerPlugin.Notifications.error('Failed to start job: ' + (response.data || 'Unknown error'));
                    }
                },
                error: (xhr, status, error) => {
                    window.ExplainerPlugin.Notifications.error('Failed to start job: ' + error);
                }
            });
        },
        
        /**
         * Add a new job to the display
         * 
         * @param {Object} jobData - Job data from server
         */
        addJobToDisplay: function(jobData) {
            // This would add a new job element to the active jobs container
            // Implementation depends on the specific UI structure
            console.log('[WP AI Explainer] Job started:', jobData);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        JobStatusManager.init();
    });
    
    // Make available globally for external use
    window.WPAIExplainerJobStatus = JobStatusManager;
    
})(jQuery);