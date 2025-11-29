/**
 * Job Queue Admin JavaScript
 * 
 * Handles admin interface interactions for WPAIE Scheduler job queue
 */

(function($) {
    'use strict';

    /**
     * Job Queue Admin Handler
     */
    var JobQueueAdmin = {
        
        /**
         * WPAIE Scheduler adapter instance
         */
        wpaieSchedulerAdapter: null,
        
        /**
         * Polling timer for job updates
         */
        pollingTimer: null,
        
        /**
         * Cron polling timer for server cron updates
         */
        cronPollingTimer: null,
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initialiseWPAIESchedulerSystem();
            this.initializeCronAwareUpdates();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Retry job button
            $(document).on('click', '.retry-job', this.handleRetryJob);
            
            // Cancel job button
            $(document).on('click', '.cancel-job', this.handleCancelJob);
            
            // Run job button
            $(document).on('click', '.run-job-btn', this.handleRunJob);
            
            // Manual refresh button
            $(document).on('click', '.refresh-jobs', this.handleRefreshJobs);
            
            // Pagination buttons
            $(document).on('click', '.tablenav-pages .button', this.handlePaginationClick);
            
            // Current page input enter key
            $(document).on('keypress', '.current-page', this.handlePageInputEnter);
        },
        
        /**
         * Handle retry job action
         * 
         * @param {Event} e Click event
         */
        handleRetryJob: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var jobId = $button.data('job-id');
            var progressId = $button.data('progress-id');
            
            if (!jobId && !progressId) {
                return;
            }
            
            // Disable button during request
            $button.prop('disabled', true).text('Retrying...');
            
            // Make AJAX request to retry the job
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'explainer_retry_job',
                    nonce: $('input[name="nonce"]').val() || explainerJobQueue.admin_nonce,
                    job_id: jobId || progressId
                },
                success: function(response) {
                    if (response.success) {
                        var $row = $button.closest('tr');
                        
                        // Remove any error message and update styling
                        JobQueueAdmin.removeErrorFromStatus($row);
                        $row.removeClass('job-row-failed');
                        
                        // Update job status in the table
                        $row.find('.job-status').text('pending').removeClass('status-failed').addClass('status-pending');
                        
                        // Remove the retry button and show appropriate buttons for pending status
                        $button.remove();
                        
                        // Refresh the page section to show updated status
                        if (typeof WPAIExplainerAdmin !== 'undefined' && WPAIExplainerAdmin.refreshJobQueue) {
                            setTimeout(function() {
                                WPAIExplainerAdmin.refreshJobQueue();
                            }, 1000);
                        } else {
                            // Fallback: reload the page
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        }
                    } else {
                        // Show error message
                        window.ExplainerPlugin.Notifications.error('Failed to retry job: ' + (response.data.message || 'Unknown error'));
                        
                        // Re-enable button
                        $button.prop('disabled', false).text('Retry');
                    }
                },
                error: function(xhr, status, error) {
                    window.ExplainerPlugin.Notifications.error('Failed to retry job. Please try again or refresh the page.');
                    
                    // Re-enable button
                    $button.prop('disabled', false).text('Retry');
                }
            });
        },
        
        /**
         * Handle cancel job action
         * 
         * @param {Event} e Click event
         */
        handleCancelJob: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            
            // Check if button is disabled
            if ($button.is(':disabled')) {
                window.ExplainerPlugin.Notifications.info('Cannot cancel job while it is processing');
                return;
            }
            
            var jobId = $button.data('job-id');
            
            if (!jobId) {
                return;
            }
            
            // Use the notification system's confirm method
            window.ExplainerPlugin.Notifications.confirm(
                'Are you sure you want to delete this job? This action cannot be undone.',
                {
                    title: 'Delete Job',
                    confirmText: 'Delete',
                    cancelText: 'Cancel'
                }
            ).then(function(confirmed) {
                if (!confirmed) {
                    return;
                }
                
                // Continue with deletion logic
                JobQueueAdmin.performJobDeletion($button, jobId);
            });
        },
        
        /**
         * Perform the actual job deletion
         * 
         * @param {jQuery} $button Button element
         * @param {string} jobId Job ID
         */
        performJobDeletion: function($button, jobId) {
            // Disable button during request
            $button.prop('disabled', true).text('Deleting...');
            
            var $row = $button.closest('tr');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'explainer_job_cancel',
                    job_id: jobId,
                    nonce: explainerJobQueue?.admin_nonce || window.wpAIExplainerJobStatus?.nonce
                },
                success: function(response) {
                    if (response.success && response.data.deleted) {
                        // Remove the entire row from the table with animation
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Check if table is now empty
                            var $tbody = $('table tbody');
                            if ($tbody.find('tr').length === 0) {
                                $tbody.html(
                                    '<tr>' +
                                    '<td colspan="7">' +
                                    '<div class="job-queue-empty">' +
                                    '<h3>No Jobs Found</h3>' +
                                    '<p>There are no recent jobs to display. Jobs will appear here after they are created through the plugin\'s features.</p>' +
                                    '</div>' +
                                    '</td>' +
                                    '</tr>'
                                );
                            }
                        });
                        
                    } else {
                        $button.prop('disabled', false).text('Cancel');
                        window.ExplainerPlugin.Notifications.error(response.data?.message || 'Failed to delete job');
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false).text('Cancel');
                    window.ExplainerPlugin.Notifications.error('Failed to delete job: ' + error);
                }
            });
        },
        
        /**
         * Handle run job action
         * 
         * @param {Event} e Click event
         */
        handleRunJob: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var jobType = $button.data('job-type');
            var jobId = $button.data('job-id');
            
            if (!jobType || !jobId) {
                return;
            }
            
            // Show the progress modal
            JobQueueAdmin.showProgressModal(jobId, jobType, $button);
            
        },
        
        /**
         * Show progress modal and start job processing
         */
        showProgressModal: function(jobId, jobType, $button) {
            var self = this;
            
            // Show the modal
            var $modal = $('#job-progress-modal');
            $modal.show();
            
            // Set modal title based on job type
            var modalTitle = jobType === 'ai_term_scan' ? 'Scanning Post for Terms' : 'Creating Blog Post';
            $modal.find('.modal-title').text(modalTitle);
            
            // Bind close modal event
            $modal.find('.close-modal-btn').off('click').on('click', function() {
                self.closeProgressModal();
            });
            
            // Disable the table button with appropriate text
            var buttonText = jobType === 'ai_term_scan' ? 'Scanning...' : 'Creating...';
            $button.prop('disabled', true).text(buttonText);
            
            // Store references for later use
            $modal.data('jobId', jobId);
            $modal.data('jobType', jobType);
            $modal.data('sourceButton', $button);
            
            // Reset modal to progress state
            $modal.find('.progress-section').show();
            $modal.find('.completion-section').hide();
            $modal.find('.error-section').hide();
            
            // Reset progress elements
            $modal.find('.progress-fill').css('width', '0%');
            $modal.find('.progress-percent').text('0%');
            
            // Set initial stage text based on job type
            var initialStageText = jobType === 'ai_term_scan' ? 'Preparing to scan post...' : 'Preparing to create blog post...';
            $modal.find('.stage-text').text(initialStageText);
            $modal.find('.current-stage').text('Initialising...');
            
            // Start progress animation and job execution
            self.startModalProgress(jobId, jobType, $button);
        },
        
        /**
         * Start progress animation in modal
         */
        startModalProgress: function(jobId, jobType, $button) {
            var self = this;
            var $modal = $('#job-progress-modal');
            var startTime = Date.now();
            var totalDuration = 5 * 60 * 1000; // 5 minutes in milliseconds
            var jobCompleted = false;
            var stages;
            
            // Function to generate randomised time intervals
            function generateRandomTimes(baseStages) {
                var randomisedStages = [];
                var lastTime = 0;
                
                for (var i = 0; i < baseStages.length; i++) {
                    var stage = baseStages[i];
                    // Generate random interval between 3-20 seconds, with later stages having longer intervals
                    var minInterval = 3000 + (i * 2000); // Minimum gets longer for later stages
                    var maxInterval = 20000 + (i * 5000); // Maximum gets longer for later stages
                    var randomInterval = Math.floor(Math.random() * (maxInterval - minInterval)) + minInterval;
                    
                    lastTime += randomInterval;
                    
                    randomisedStages.push({
                        percent: stage.percent,
                        message: stage.message,
                        time: lastTime
                    });
                }
                
                return randomisedStages;
            }
            
            if (jobType === 'ai_term_scan') {
                var baseStages = [
                    { percent: 10, message: "Initialising term scanner" },
                    { percent: 25, message: "Analysing post content" },
                    { percent: 50, message: "Extracting technical terms" },
                    { percent: 75, message: "Generating explanations" },
                    { percent: 90, message: "Storing results" },
                    { percent: 98, message: "Finalising scan" }
                ];
                stages = generateRandomTimes(baseStages);
            } else {
                var baseStages = [
                    { percent: 5, message: "Initialising content generation" },
                    { percent: 15, message: "Analysing selected text" },
                    { percent: 30, message: "Generating blog post content" },
                    { percent: 50, message: "Creating title and structure" },
                    { percent: 65, message: "Processing featured image" },
                    { percent: 80, message: "Generating SEO metadata" },
                    { percent: 95, message: "Finalising blog post" }
                ];
                stages = generateRandomTimes(baseStages);
            }
            
            var currentStage = 0;
            var $progressFill = $modal.find('.progress-fill');
            var $progressPercent = $modal.find('.progress-percent');
            var $stageText = $modal.find('.stage-text');
            var $currentStage = $modal.find('.current-stage');
            
            // Start the actual job immediately in parallel
            self.executeModalJob(jobId, jobType, function(success, response) {
                jobCompleted = true;
                if (success) {
                    self.showJobSuccess(response);
                } else {
                    self.showJobError(response);
                }
            });
            
            // Update progress function
            function updateProgress() {
                var elapsed = Date.now() - startTime;
                
                // If job completed early, stop the fake progress
                if (jobCompleted) {
                    return; // Success/error handler takes over
                }
                
                // Check if we should move to next stage
                if (currentStage < stages.length && elapsed >= stages[currentStage].time) {
                    var stage = stages[currentStage];
                    $progressFill.css('width', stage.percent + '%');
                    $progressPercent.text(stage.percent + '%');
                    $stageText.text(stage.message);
                    $currentStage.text(stage.message);
                    currentStage++;
                }
                
                // Continue animation until job is done or 5 minutes passed
                if (elapsed < totalDuration && !jobCompleted) {
                    setTimeout(updateProgress, 2000); // Update every 2 seconds
                } else if (!jobCompleted) {
                    // Timeout reached but job not done - keep showing last stage
                    $currentStage.text('Taking longer than expected - still processing...');
                    setTimeout(updateProgress, 5000); // Check less frequently
                }
            }
            
            // Start the progress animation
            updateProgress();
        },
        
        /**
         * Execute the actual job via AJAX and poll for completion
         */
        executeModalJob: function(jobId, jobType, callback) {
            var self = this;
            
            // Start the job
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wp_ai_explainer_run_job',
                    job_id: jobId,
                    job_type: jobType,
                    nonce: explainerJobQueue?.admin_nonce || window.wpAIExplainerJobStatus?.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Job started successfully, now poll for completion
                        var numericJobId = response.data.numeric_job_id || jobId.replace('jq_', '');
                        self.pollJobCompletion(numericJobId, jobType, callback);
                    } else {
                        callback(false, response.data || 'Failed to start job');
                    }
                },
                error: function(xhr, status, error) {
                    callback(false, 'Failed to start job: ' + error);
                }
            });
        },
        
        /**
         * Poll for job completion and return post data when complete
         */
        pollJobCompletion: function(jobId, jobType, callback) {
            var self = this;
            var pollCount = 0;
            var maxPolls = 150; // 5 minutes at 2-second intervals
            
            function checkStatus() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wp_ai_explainer_get_job_status',
                        job_id: jobId,
                        nonce: explainerJobQueue?.admin_nonce || window.wpAIExplainerJobStatus?.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            var status = response.data.status;
                            
                            if (status === 'completed') {
                                // Job completed successfully, get post information
                                self.getJobPostInfo(jobId, callback);
                                return;
                            } else if (status === 'failed') {
                                callback(false, response.data.error_message || 'Job failed');
                                return;
                            } else if (pollCount >= maxPolls) {
                                callback(false, 'Job timed out - taking longer than expected');
                                return;
                            }
                            
                            // Job still processing, continue polling
                            pollCount++;
                            setTimeout(checkStatus, 2000); // Check every 2 seconds
                        } else {
                            callback(false, 'Unable to check job status');
                        }
                    },
                    error: function() {
                        if (pollCount >= maxPolls) {
                            callback(false, 'Job timed out');
                        } else {
                            pollCount++;
                            setTimeout(checkStatus, 2000);
                        }
                    }
                });
            }
            
            // Start polling
            checkStatus();
        },
        
        /**
         * Get post information for completed job
         */
        getJobPostInfo: function(jobId, callback) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wp_ai_explainer_get_job_post_info',
                    job_id: jobId,
                    nonce: explainerJobQueue?.admin_nonce || window.wpAIExplainerJobStatus?.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        callback(true, response.data);
                    } else {
                        // Job completed but no post info - return basic success
                        callback(true, {
                            post_title: 'Blog post created successfully',
                            message: 'Post information not available'
                        });
                    }
                },
                error: function() {
                    callback(true, {
                        post_title: 'Blog post created successfully',
                        message: 'Post information not available'
                    });
                }
            });
        },
        
        /**
         * Show job success in modal
         */
        showJobSuccess: function(result) {
            var $modal = $('#job-progress-modal');
            var jobType = $modal.data('jobType');
            
            // Rush progress bar to 100%
            $modal.find('.progress-fill').css({'width': '100%', 'transition': 'width 0.5s ease'});
            $modal.find('.progress-percent').text('100%');
            
            // Set completion message based on job type
            var completionMessage = jobType === 'ai_term_scan' ? 'Post scan completed successfully' : 'Blog post created successfully';
            $modal.find('.current-stage').text(completionMessage);
            
            setTimeout(function() {
                // Hide progress section and show completion section
                $modal.find('.progress-section').hide();
                $modal.find('.completion-section').show();
                
                // Update modal with appropriate information based on job type
                if (result) {
                    
                    // Update post title (common for both job types)
                    if (result.post_title) {
                        $modal.find('.post-title').text(result.post_title);
                    }
                    
                    // Update post thumbnail (common for both job types)
                    if (result.thumbnail) {
                        $modal.find('.post-thumbnail').html(
                            '<img src="' + result.thumbnail + '" alt="' + (result.post_title || '') + '">'
                        );
                    }
                    
                    if (jobType === 'ai_term_scan') {
                        // Term scan specific information
                        var termsText = 'Terms found: ' + (result.terms_found || 0);
                        $modal.find('.post-status').text(termsText);
                        
                        
                        // Update action buttons for term scan
                        if (result.edit_link) {
                            $modal.find('.edit-post-btn').attr('href', result.edit_link).text('Edit Post').removeClass('disabled');
                        }
                        if (result.view_link) {
                            $modal.find('.view-post-btn').attr('href', result.view_link).text('View Post').removeClass('disabled');
                        }
                    } else {
                        // Blog creation specific information
                        $modal.find('.post-status').text('Status: Draft');
                        
                        
                        // Update action buttons for blog creation
                        if (result.edit_link) {
                            $modal.find('.edit-post-btn').attr('href', result.edit_link).text('Edit Post').removeClass('disabled');
                        }
                        if (result.view_link) {
                            $modal.find('.view-post-btn').attr('href', result.view_link).text('Preview Post').removeClass('disabled');
                        }
                    }
                }
            }, 1000);
        },
        
        /**
         * Show job error in modal
         */
        showJobError: function(errorMessage) {
            var $modal = $('#job-progress-modal');
            
            // Update progress bar to red
            $modal.find('.progress-fill').css('background', '#dc3545');
            $modal.find('.current-stage').text('Job failed');
            
            setTimeout(function() {
                // Hide progress section and show error section
                $modal.find('.progress-section').hide();
                $modal.find('.error-section').show();
                
                // Show error message
                $modal.find('.error-message').text(errorMessage || 'Unknown error occurred');
                
                // Bind retry button
                $modal.find('.retry-job-btn').off('click').on('click', function() {
                    var jobId = $modal.data('jobId');
                    var jobType = $modal.data('jobType');
                    var $button = $modal.data('sourceButton');
                    
                    // Close modal and restart
                    JobQueueAdmin.closeProgressModal();
                    setTimeout(function() {
                        JobQueueAdmin.showProgressModal(jobId, jobType, $button);
                    }, 300);
                });
            }, 1000);
        },
        
        /**
         * Close progress modal and update table
         */
        closeProgressModal: function() {
            var $modal = $('#job-progress-modal');
            var $button = $modal.data('sourceButton');
            
            // Hide modal
            $modal.hide();
            
            // Re-enable and update button text
            if ($button) {
                $button.prop('disabled', false).text('Run Job');
            }
            
            // Refresh page to show updated job status
            setTimeout(function() {
                location.reload();
            }, 500);
        },
        
        /**
         * Handle refresh jobs action
         * 
         * @param {Event} e Click event
         */
        handleRefreshJobs: function(e) {
            e.preventDefault();
            
            location.reload();
        },
        
        /**
         * Handle pagination button clicks
         * 
         * @param {Event} e Click event
         */
        handlePaginationClick: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var page = $button.data('page');
            
            if (!page || $button.hasClass('disabled')) {
                return;
            }
            
            // Build URL with current filters and new page number
            var url = new URL(window.location);
            url.searchParams.set('paged', page);
            
            // Navigate to new page
            window.location.href = url.toString();
        },
        
        /**
         * Handle enter key in current page input
         * 
         * @param {Event} e Keypress event
         */
        handlePageInputEnter: function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                
                var $input = $(this);
                var page = parseInt($input.val());
                var totalPages = parseInt($('.total-pages').text());
                
                if (page && page > 0 && page <= totalPages) {
                    // Build URL with current filters and new page number
                    var url = new URL(window.location);
                    url.searchParams.set('paged', page);
                    
                    // Navigate to new page
                    window.location.href = url.toString();
                } else {
                    // Reset to current page if invalid
                    $input.val($input.data('current-page'));
                }
            }
        },
        
        /**
         * Initialise WPAIE Scheduler system
         */
        initialiseWPAIESchedulerSystem: function() {
            
            try {
                // Check if WPAIE Scheduler adapter is available
                if (typeof ExplainerPlugin !== 'undefined' && ExplainerPlugin.WPAIESchedulerAdapter) {
                    this.wpaieSchedulerAdapter = new ExplainerPlugin.WPAIESchedulerAdapter({
                        enableDebugLogging: window.wpAIExplainerJobStatus?.debug || false
                    });
                    
                    // Initialize the adapter
                    this.wpaieSchedulerAdapter.initialise().then(() => {
                        this.startProgressMonitoring();
                        this.startPeriodicMonitoringCheck();
                    }).catch((error) => {
                        this.fallbackToPolling();
                    });
                    
                    // Add error handler
                    this.wpaieSchedulerAdapter.onError((errorInfo) => {
                        // Handle error silently
                    });
                } else {
                    this.fallbackToPolling();
                }
            } catch (error) {
                this.fallbackToPolling();
            }
        },
        
        /**
         * Start monitoring active job progress
         */
        startProgressMonitoring: function() {
            // Find all active job progress elements
            var $activeJobs = $('tr[data-job-id], .job-progress-item[data-progress-id]');
            
            $activeJobs.each(function() {
                var $jobElement = $(this);
                var progressId = $jobElement.data('progress-id') || $jobElement.data('job-id');
                var status = JobQueueAdmin.getJobStatusFromElement($jobElement);
                
                // Only monitor active jobs
                if (progressId && ['pending', 'processing'].includes(status)) {
                    // Add jq_ prefix for job-queue jobs if not already present
                    var jobId = progressId.toString().startsWith('jq_') ? progressId : 'jq_' + progressId;
                    
                    JobQueueAdmin.wpaieSchedulerAdapter.connect(
                        jobId,
                        function(progressData, context) {
                            JobQueueAdmin.handleProgressUpdate(jobId, progressData);
                        }
                    ).catch(function(error) {
                        // Handle monitoring error silently
                    });
                }
            });
            
        },
        
        /**
         * Start periodic check for new pending jobs that need monitoring
         */
        startPeriodicMonitoringCheck: function() {
            var self = this;
            
            // Check every 5 seconds for new pending jobs
            setInterval(function() {
                var $activeJobs = $('tr[data-job-id], .job-progress-item[data-progress-id]');
                
                $activeJobs.each(function() {
                    var $jobElement = $(this);
                    var progressId = $jobElement.data('progress-id') || $jobElement.data('job-id');
                    var status = self.getJobStatusFromElement($jobElement);
                    
                    // Check if this job needs monitoring but isn't being monitored yet
                    if (progressId && ['pending', 'processing'].includes(status)) {
                        // Add jq_ prefix for job-queue jobs if not already present
                        var jobId = progressId.toString().startsWith('jq_') ? progressId : 'jq_' + progressId;
                        
                        // Check if we're already monitoring this job
                        if (!self.wpaieSchedulerAdapter.connections.has(jobId)) {
                            
                            self.wpaieSchedulerAdapter.connect(
                                jobId,
                                function(progressData, context) {
                                    self.handleProgressUpdate(jobId, progressData);
                                }
                            ).catch(function(error) {
                                // Handle monitoring error silently
                            });
                        }
                    }
                });
            }, 5000); // Check every 5 seconds
        },
        
        /**
         * Handle progress update from Action Scheduler
         * 
         * @param {string} progressId Progress ID
         * @param {object} progressData Progress data
         */
        handleProgressUpdate: function(progressId, progressData) {
            console.log('JobQueueAdmin.handleProgressUpdate called:', {
                progressId: progressId,
                status: progressData.status,
                fullData: progressData
            });
            
            // Handle deleted jobs by removing them from the display
            if (progressData.status === 'deleted') {
                console.log('Job marked as deleted, removing:', progressId);
                this.removeDeletedJob(progressId);
                return;
            }
            
            // Convert job ID format (remove jq_ prefix if present)
            var numericJobId = progressId.toString().replace('jq_', '');
            
            // Update progress item if it exists
            var $jobElement = $('.job-progress-item[data-progress-id="' + progressId + '"]');
            if ($jobElement.length) {
                this.updateProgressItem($jobElement, progressData);
            }
            
            // Update table row if it exists - try multiple selectors
            var $rowElement = $('tr[data-job-id="' + numericJobId + '"], tr[data-job-id="jq_' + numericJobId + '"], .job-row[data-progress-id="' + progressId + '"]');
            if ($rowElement.length) {
                this.updateJobTableRow($rowElement, progressData);
            }
        },
        
        /**
         * Remove deleted job from display
         * 
         * @param {string} progressId Progress ID of deleted job
         */
        removeDeletedJob: function(progressId) {
            console.log('removeDeletedJob called for:', progressId);
            var numericJobId = progressId.toString().replace('jq_', '');
            console.log('Numeric job ID:', numericJobId);
            
            // Remove progress item if it exists
            var $jobElement = $('.job-progress-item[data-progress-id="' + progressId + '"]');
            console.log('Found progress items:', $jobElement.length);
            if ($jobElement.length) {
                console.log('Removing progress item');
                $jobElement.fadeOut(300, function() {
                    $(this).remove();
                });
            }
            
            // Remove table row if it exists - try multiple selectors
            var $rowElement = $('tr[data-job-id="' + numericJobId + '"], tr[data-job-id="jq_' + numericJobId + '"], .job-row[data-progress-id="' + progressId + '"]');
            console.log('Found table rows:', $rowElement.length);
            if ($rowElement.length) {
                console.log('Removing table row');
                $rowElement.fadeOut(300, function() {
                    $(this).remove();
                });
            }

            console.log('removeDeletedJob completed for:', progressId);
        },
        
        /**
         * Update progress item display
         * 
         * @param {jQuery} $element Job element
         * @param {object} progressData Progress data
         */
        updateProgressItem: function($element, progressData) {
            // Handle deleted jobs - don't update, just remove
            if (progressData.status === 'deleted') {
                console.log('updateProgressItem: Job is deleted, removing element');
                $element.fadeOut(300, function() {
                    $(this).remove();
                });
                return;
            }
            
            // Update progress bar
            var $progressBar = $element.find('.progress-bar');
            if ($progressBar.length) {
                var percent = Math.max(0, Math.min(100, progressData.progress_percent || 0));
                $progressBar.css('width', percent + '%');
            }
            
            // Update progress text
            var $progressText = $element.find('.progress-text');
            if ($progressText.length && progressData.progress_text) {
                $progressText.text(progressData.progress_text);
            }
            
            // Update progress percentage
            var $progressPercent = $element.find('.progress-percent');
            if ($progressPercent.length) {
                var percent = Math.max(0, Math.min(100, progressData.progress_percent || 0));
                $progressPercent.text(percent + '%');
            }
            
            // Update status class
            $element.removeClass('status-pending status-processing status-completed status-failed');
            $element.addClass('status-' + progressData.status);
            
            // Handle completion or failure
            if (progressData.status === 'completed') {
                this.handleJobCompletion($element, progressData);
            } else if (progressData.status === 'failed') {
                this.handleJobFailure($element, progressData);
            }
        },
        
        /**
         * Update job table row display
         * 
         * @param {jQuery} $element Row element
         * @param {object} progressData Progress data
         */
        updateJobTableRow: function($element, progressData) {
            // Handle deleted jobs - don't update, just remove
            if (progressData.status === 'deleted') {
                console.log('updateJobTableRow: Job is deleted, removing row');
                $element.fadeOut(300, function() {
                    $(this).remove();
                });
                return;
            }
            
            var hasChanges = false;
            
            // Check if status cell needs updating
            var $statusCell = $element.find('.job-status');
            if ($statusCell.length) {
                var currentStatus = $statusCell.text().toLowerCase();
                var newStatus = progressData.status;
                
                if (currentStatus !== newStatus) {
                    // Remove all status classes and add the new one
                    $statusCell.removeClass('status-pending status-processing status-completed status-failed status-cancelled status-starting');
                    $statusCell.addClass('status-' + newStatus);
                    
                    // Update the text with proper capitalisation
                    var statusText = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    $statusCell.text(statusText);
                    
                    hasChanges = true;
                }
            }
            
            // Update the appropriate column for completed jobs based on job type
            if (progressData.status === 'completed') {
                // Check if we have post information for any completed job (blog creation or term scan)
                if (progressData.post_info && progressData.post_info.post_id) {
                    var postInfo = progressData.post_info;
                    
                    // Determine target column based on job type
                    var targetColumnIndex;
                    var updateContext;
                    
                    // Both job types update Result column (index 4) for consistency
                    targetColumnIndex = 4;
                    updateContext = 'result';
                    
                    var $targetCell = $element.find('td').eq(targetColumnIndex);
                    if ($targetCell.length) {
                        // Result column for all completed jobs - use completed-post format
                        var currentTitle = $targetCell.find('.post-title').text();
                        var newTitle = postInfo.post_title || postInfo.title || 'Untitled';
                        
                        if (currentTitle !== newTitle || !$targetCell.find('.completed-post').length) {
                            var cellHtml = '<div class="completed-post">';
                            
                            // Add thumbnail if available
                            if (postInfo.thumbnail) {
                                cellHtml += '<img src="' + postInfo.thumbnail + '" ' +
                                           'alt="' + (postInfo.post_title || postInfo.title || '') + '" ' +
                                           'class="post-thumbnail-small">';
                            }
                            
                            cellHtml += '<div class="post-details">';
                            
                            // Use post_title for both job types
                            var displayTitle = postInfo.post_title || postInfo.title || 'Untitled';
                            cellHtml += '<div class="post-title">' + displayTitle + '</div>';
                            
                            cellHtml += '<div class="post-links">';
                            
                            // Add edit link if available
                            if (postInfo.edit_link) {
                                cellHtml += '<a href="' + postInfo.edit_link + '" class="edit-link">Edit</a>';
                            }
                            
                            // Add view link
                            if (postInfo.view_link) {
                                if (postInfo.edit_link) cellHtml += '<span class="separator">|</span>';
                                cellHtml += '<a href="' + postInfo.view_link + '" class="view-link" target="_blank">View</a>';
                            }
                            
                            cellHtml += '</div></div></div>';
                            
                            $targetCell.html(cellHtml);
                            hasChanges = true;
                        }
                    }
                } else {
                    // For completed jobs without post_info, handle both potential columns
                    // Check Result column (index 4) first for blog creation jobs
                    var $resultCell = $element.find('td').eq(4);
                    if ($resultCell.length && ($resultCell.find('.completion-indicator').length || $resultCell.html().trim() !== '<span class="no-result">&nbsp;</span>')) {
                        $resultCell.html('<span class="no-result">&nbsp;</span>');
                        hasChanges = true;
                    }
                }
            }
            
            // Update actions column based on status (column index 6)
            var $actionsCell = $element.find('td').eq(6); // Actions is column index 6
            if ($actionsCell.length && progressData.status === 'completed') {
                // Check if buttons need to be removed
                var $buttonsToRemove = $actionsCell.find('.run-job-btn, .cancel-job');
                if ($buttonsToRemove.length > 0) {
                    $buttonsToRemove.remove();
                    hasChanges = true;
                }
            } else if ($actionsCell.length && progressData.status === 'failed') {
                // Ensure retry button exists for failed jobs
                if (!$actionsCell.find('.retry-job').length) {
                    var $runButton = $actionsCell.find('.run-job-btn');
                    if ($runButton.length) {
                        // Convert existing run button to retry button
                        $runButton.removeClass('run-job-btn btn-primary')
                                .addClass('retry-job btn-secondary btn-with-icon')
                                .html('<span class="dashicons dashicons-update btn-icon-size"></span> Retry');
                    } else {
                        // Add new retry button if no run button exists
                        $actionsCell.find('.job-actions').prepend(
                            '<button class="btn-base btn-secondary btn-sm btn-with-icon retry-job" data-job-id="' + progressData.id + '">' +
                            '<span class="dashicons dashicons-update btn-icon-size"></span> Retry' +
                            '</button>'
                        );
                    }
                    hasChanges = true;
                }
                // Remove cancel button if present
                var $cancelButton = $actionsCell.find('.cancel-job');
                if ($cancelButton.length > 0) {
                    $cancelButton.remove();
                    hasChanges = true;
                }
                
                // Add error message below the status text
                if (progressData.error_message) {
                    this.addErrorToStatus($element, progressData.error_message);
                }
                
                if (hasChanges) {
                }
            } else if ($actionsCell.length && progressData.status === 'processing') {
                // Disable cancel button for processing jobs
                var $cancelButton = $actionsCell.find('.cancel-job');
                if ($cancelButton.length && !$cancelButton.is(':disabled')) {
                    $cancelButton.prop('disabled', true).attr('title', 'Cannot cancel job while processing');
                    hasChanges = true;
                }
            } else if ($actionsCell.length && progressData.status === 'pending') {
                // Re-enable cancel button for pending jobs
                var $cancelButton = $actionsCell.find('.cancel-job');
                if ($cancelButton.length && $cancelButton.is(':disabled')) {
                    $cancelButton.prop('disabled', false).removeAttr('title');
                    hasChanges = true;
                }
            }
            
            // Update row status class only if status changed
            var currentRowStatus = '';
            var classList = $element.attr('class');
            if (classList) {
                var classes = classList.split(/\s+/);
                for (var i = 0; i < classes.length; i++) {
                    if (classes[i].startsWith('status-')) {
                        currentRowStatus = classes[i].replace('status-', '');
                        break;
                    }
                }
            }
            
            if (currentRowStatus !== progressData.status) {
                $element.removeClass('status-pending status-processing status-completed status-failed status-cancelled status-starting job-row-failed');
                $element.addClass('status-' + progressData.status);
                
                // Add subtle styling for failed jobs
                if (progressData.status === 'failed') {
                    $element.addClass('job-row-failed');
                }
                
                hasChanges = true;
            }
            
            // Update statistics whenever job status changes
            if (hasChanges) {
                this.updateStatistics();
            }
        },
        
        /**
         * Update statistics list with current counts
         */
        updateStatistics: function() {
            // Count jobs by status from the current table
            var stats = {
                pending: 0,
                processing: 0,
                completed: 0,
                failed: 0
            };
            
            // Count from visible table rows
            $('.job-queue-table tbody tr').each(function() {
                var $statusCell = $(this).find('.job-status');
                if ($statusCell.length) {
                    var statusClass = '';
                    var classList = $statusCell.attr('class');
                    if (classList) {
                        var classes = classList.split(/\s+/);
                        for (var i = 0; i < classes.length; i++) {
                            if (classes[i].startsWith('status-')) {
                                statusClass = classes[i].replace('status-', '');
                                break;
                            }
                        }
                    }
                    
                    if (stats.hasOwnProperty(statusClass)) {
                        stats[statusClass]++;
                    }
                }
            });
            
            // Update the status items in the new layout
            $('.status-item').each(function() {
                var $item = $(this);
                var $label = $item.find('.status-label');
                var $value = $item.find('.status-value');
                
                if ($label.length && $value.length) {
                    var labelText = $label.text().toLowerCase();
                    
                    // Skip the Processing Mode item - it should not be updated with job counts
                    if (labelText.includes('processing mode')) {
                        return; // Skip this item
                    }
                    
                    if (labelText.includes('pending')) {
                        if (parseInt($value.text()) !== stats.pending) {
                            $value.text(stats.pending);
                        }
                    } else if (labelText.includes('processing')) {
                        if (parseInt($value.text()) !== stats.processing) {
                            $value.text(stats.processing);
                        }
                    } else if (labelText.includes('completed')) {
                        if (parseInt($value.text()) !== stats.completed) {
                            $value.text(stats.completed);
                        }
                    } else if (labelText.includes('failed')) {
                        if (parseInt($value.text()) !== stats.failed) {
                            $value.text(stats.failed);
                        }
                    }
                }
            });
        },
        
        /**
         * Update table row display (legacy method for compatibility)
         * 
         * @param {jQuery} $element Row element
         * @param {object} progressData Progress data
         */
        updateTableRow: function($element, progressData) {
            // Delegate to the new method
            this.updateJobTableRow($element, progressData);
        },

        /**
         * Handle job completion
         * 
         * @param {jQuery} $element Job element
         * @param {object} progressData Progress data
         */
        handleJobCompletion: function($element, progressData) {
            // Add completion actions if result data is available
            if (progressData.result_data) {
                var resultData = typeof progressData.result_data === 'string' ? 
                    JSON.parse(progressData.result_data) : progressData.result_data;
                
                // For blog creation jobs, show post links
                if (resultData.post_url || resultData.edit_url) {
                    var $successActions = $element.find('.job-success-actions');
                    if (!$successActions.length) {
                        $successActions = $('<div class="job-success-actions"></div>');
                        $element.find('.progress-container').append($successActions);
                    }
                    
                    $successActions.empty();
                    
                    if (resultData.post_url) {
                        $successActions.append(
                            $('<a>').attr('href', resultData.post_url)
                                   .attr('target', '_blank')
                                   .text('View Post')
                                   .addClass('button button-small')
                        );
                    }
                    
                    if (resultData.edit_url) {
                        if (resultData.post_url) {
                            $successActions.append(' ');
                        }
                        $successActions.append(
                            $('<a>').attr('href', resultData.edit_url)
                                   .attr('target', '_blank')
                                   .text('Edit Post')
                                   .addClass('button button-small button-primary')
                        );
                    }
                }
            }
            
            // Remove cancel button if present
            $element.find('.cancel-job').remove();
        },
        
        /**
         * Handle job failure
         * 
         * @param {jQuery} $element Job element
         * @param {object} progressData Progress data
         */
        handleJobFailure: function($element, progressData) {
            // Show error message if available
            if (progressData.error_message) {
                var $errorMessage = $element.find('.job-error-message');
                if (!$errorMessage.length) {
                    $errorMessage = $('<div class="job-error-message"></div>');
                    $element.find('.progress-container').append($errorMessage);
                }
                
                $errorMessage.text('Error: ' + progressData.error_message);
            }
            
            // Replace cancel button with retry button
            var $cancelButton = $element.find('.cancel-job');
            if ($cancelButton.length) {
                $cancelButton.removeClass('cancel-job')
                          .addClass('retry-job')
                          .text('Retry');
            }
        },
        
        /**
         * Get job status from DOM element
         * 
         * @param {jQuery} $element Job element
         * @return {string} Job status
         */
        getJobStatusFromElement: function($element) {
            // For Job Queue Admin table rows - look for .job-status span
            var $statusSpan = $element.find('.job-status');
            if ($statusSpan.length > 0) {
                var classList = $statusSpan.attr('class');
                if (classList) {
                    var classes = classList.split(/\s+/);
                    for (var i = 0; i < classes.length; i++) {
                        var className = classes[i];
                        if (className.startsWith('status-')) {
                            return className.replace('status-', '');
                        }
                    }
                }
            }
            
            // For WPAIE Scheduler progress items - check element itself
            var elementClass = $element.attr('class');
            if (elementClass) {
                var classes = elementClass.split(/\s+/);
                for (var i = 0; i < classes.length; i++) {
                    var className = classes[i];
                    if (className.startsWith('status-')) {
                        return className.replace('status-', '');
                    }
                }
            }
            
            return 'unknown';
        },
        
        /**
         * Start monitoring a specific job
         * 
         * @param {string} jobId Job ID to monitor
         */
        startJobMonitoring: function(jobId) {
            
            if (this.wpaieSchedulerAdapter) {
                // Use real-time monitoring if available
                this.wpaieSchedulerAdapter.connect(
                    jobId,
                    function(progressData, context) {
                        JobQueueAdmin.handleProgressUpdate(jobId, progressData);
                    }
                ).catch(function(error) {
                    // Handle monitoring error silently
                });
            } else {
                // Use polling fallback
                this.pollJobStatus(jobId);
            }
        },
        
        /**
         * Poll status for a specific job
         * 
         * @param {string} jobId Job ID to poll
         */
        pollJobStatus: function(jobId) {
            var self = this;
            
            var pollTimer = setInterval(function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wp_ai_explainer_get_job_status',
                        job_id: jobId,
                        nonce: explainerJobQueue?.admin_nonce || window.wpAIExplainerJobStatus?.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            self.handleProgressUpdate(jobId, response.data);
                            
                            // Stop polling if job is completed or failed
                            if (['completed', 'failed'].includes(response.data.status)) {
                                clearInterval(pollTimer);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        // Handle polling error silently
                    }
                });
            }, 2000); // Poll every 2 seconds for individual job
        },
        
        /**
         * Fallback to AJAX polling when Action Scheduler is not available
         */
        fallbackToPolling: function() {
            
            this.pollingTimer = setInterval(function() {
                JobQueueAdmin.refreshJobStatuses();
            }, 3000); // Poll every 3 seconds
        },
        
        /**
         * Refresh job statuses via AJAX
         */
        refreshJobStatuses: function() {
            var $activeJobs = $('tr[data-job-id], .job-progress-item[data-progress-id]');
            
            if ($activeJobs.length === 0) {
                return;
            }
            
            var progressIds = [];
            $activeJobs.each(function() {
                var $element = $(this);
                var progressId = $element.data('progress-id') || $element.data('job-id');
                var status = JobQueueAdmin.getJobStatusFromElement($element);
                
                // Only check active jobs
                if (progressId && ['pending', 'processing'].includes(status)) {
                    // Add jq_ prefix for job-queue jobs if not already present
                    var jobId = progressId.toString().startsWith('jq_') ? progressId : 'jq_' + progressId;
                    progressIds.push(jobId);
                }
            });
            
            if (progressIds.length === 0) {
                // No active jobs, stop polling
                if (this.pollingTimer) {
                    clearInterval(this.pollingTimer);
                    this.pollingTimer = null;
                }
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wp_ai_explainer_get_job_statuses',
                    progress_ids: progressIds,
                    nonce: explainerJobQueue.admin_nonce
                },
                success: function(response) {
                    console.log('AJAX response received:', response);
                    if (response.success && response.data) {
                        console.log('Processing job status updates:', response.data);
                        $.each(response.data, function(progressId, progressData) {
                            console.log('Processing job:', progressId, progressData);
                            JobQueueAdmin.handleProgressUpdate(progressId, progressData);
                        });
                    }
                },
                error: function(xhr, status, error) {
                    // Handle refresh error silently
                }
            });
        },
        
        /**
         * Initialize cron-aware updates for server cron processing
         */
        initializeCronAwareUpdates: function() {
            // Check if server cron is enabled
            if (this.isCronEnabled()) {
                this.startStatusPolling();
            }
        },
        
        /**
         * Check if server cron is enabled
         * 
         * @return {boolean} True if cron is enabled
         */
        isCronEnabled: function() {
            // Check the localized script data first (most reliable)
            if (typeof explainerJobQueue !== 'undefined' && explainerJobQueue.cron_enabled) {
                return true;
            }
            
            // Fallback checks for other indicators
            return window.explainerJobQueue?.cron_enabled || 
                   $('body').hasClass('cron-enabled') ||
                   $('#cron-status').text().toLowerCase().includes('enabled');
        },
        
        /**
         * Start status polling for cron-triggered job updates
         */
        startStatusPolling: function() {
            var self = this;
            
            // Only start if not already polling
            if (this.cronPollingTimer) {
                return;
            }
            
            
            this.cronPollingTimer = setInterval(function() {
                self.updateJobRowStatuses();
            }, 3000); // Poll every 3 seconds for cron updates
        },
        
        /**
         * Stop status polling
         */
        stopStatusPolling: function() {
            if (this.cronPollingTimer) {
                clearInterval(this.cronPollingTimer);
                this.cronPollingTimer = null;
            }
        },
        
        /**
         * Enhanced job status refresh that works with both manual and cron jobs
         * Also checks for new jobs that should be added to the table
         */
        updateJobRowStatuses: function() {
            var $jobRows = $('tr[data-job-id]');
            
            
            var progressIds = [];
            $jobRows.each(function() {
                var jobId = $(this).data('job-id');
                if (jobId) {
                    // Convert to progress ID format that PHP expects (jq_123)
                    var progressId = jobId.toString().startsWith('jq_') ? jobId : 'jq_' + jobId;
                    progressIds.push(progressId);
                }
            });
            
            
            // Make AJAX call to get both status updates and check for new jobs
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wp_ai_explainer_get_job_statuses',
                    progress_ids: progressIds,
                    include_new_jobs: 'true', // Request new jobs as well (string for PHP compatibility)
                    nonce: explainerJobQueue?.admin_nonce || window.wpAIExplainerJobStatus?.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Update each existing job row with the latest status
                        $.each(response.data, function(progressId, progressData) {
                            // Convert back to numeric job ID for row selector
                            var numericJobId = progressId.toString().replace('jq_', '');
                            var $row = $('tr[data-job-id="' + numericJobId + '"]');
                            if ($row.length && progressData.status) {
                                JobQueueAdmin.updateJobTableRow($row, progressData);
                            }
                        });
                        
                        // Check for new jobs that should be added to the table
                        if (response.data.new_jobs && response.data.new_jobs.length > 0) {
                            JobQueueAdmin.addNewJobRows(response.data.new_jobs);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    // Handle status update error silently
                }
            });
        },
        
        /**
         * Add new job rows to the table
         * 
         * @param {Array} newJobs Array of new job data
         */
        addNewJobRows: function(newJobs) {
            var $tbody = $('.job-queue-table tbody');
            var $emptyMessage = $tbody.find('.job-queue-empty');
            
            // Remove empty message if present
            if ($emptyMessage.length) {
                $emptyMessage.closest('tr').remove();
            }
            
            // Add each new job as a table row
            $.each(newJobs, function(index, jobData) {
                var newRowHtml = JobQueueAdmin.createJobRowHtml(jobData);
                $tbody.prepend(newRowHtml); // Add to top for newest-first ordering
            });
            
            // Update statistics after adding new jobs
            this.updateStatistics();
        },
        
        /**
         * Create HTML for a job row
         * 
         * @param {Object} jobData Job data object
         * @return {string} HTML for the job row
         */
        createJobRowHtml: function(jobData) {
            var statusClass = 'status-' + jobData.status;
            var rowClass = 'status-' + jobData.status;
            
            // Format the created date
            var createdDate = jobData.created_at || '';
            
            // Create term/explanation display based on job type
            var termExplanation = 'N/A';
            
            // Check for completed jobs with post_info (AJAX updates)
            if (jobData.post_info && jobData.post_info.job_type === 'ai_term_scan' && jobData.post_info.post_title) {
                // Completed post scan jobs - show post information
                termExplanation = '<div class="job-term-explanation">' +
                    '<div><strong>Post:</strong> <span class="job-term">' + 
                    JobQueueAdmin.escapeHtml(jobData.post_info.post_title) + '</span></div>' +
                    '<div><strong>ID:</strong> <span class="job-explanation">' + (jobData.post_info.post_id || 'Unknown') + '</span></div>' +
                    '</div>';
            }
            // Check for new term scan jobs (no post_info yet)  
            else if (jobData.job_type === 'ai_term_scan') {
                // New term scan jobs - show what we can from job data
                if (jobData.post_title && jobData.post_id) {
                    termExplanation = '<div class="job-term-explanation">' +
                        '<div><strong>Post:</strong> <span class="job-term">' + 
                        JobQueueAdmin.escapeHtml(jobData.post_title) + '</span></div>' +
                        '<div><strong>ID:</strong> <span class="job-explanation">' + jobData.post_id + '</span></div>' +
                        '</div>';
                } else {
                    termExplanation = '<div class="job-term-explanation">' +
                        '<div><strong>Status:</strong> <span class="job-term">Scanning post...</span></div>' +
                        '</div>';
                }
            } else if (jobData.selection_text) {
                // Blog creation jobs - show selection text
                termExplanation = '<div class="job-term-explanation">' +
                    '<div><strong>Term:</strong> <span class="job-term">' + 
                    JobQueueAdmin.escapeHtml(jobData.selection_text) + '</span></div>' +
                    '<div><strong>Explanation:</strong> <span class="job-explanation">No explanation available</span></div>' +
                    '</div>';
            }
            
            // Create result column content
            var resultContent = '';
            if (jobData.status === 'failed' && jobData.error_message) {
                resultContent = '<span class="error-result">' + JobQueueAdmin.escapeHtml(jobData.error_message) + '</span>';
            }
            
            // Create action buttons based on status
            var actionButtons = '';
            if (jobData.status === 'pending') {
                actionButtons = '<div class="job-actions">' +
                    '<button class="btn-base btn-primary btn-sm cancel-job" data-job-id="' + jobData.queue_id + '">' +
                    'Cancel</button></div>';
            } else if (jobData.status === 'failed') {
                actionButtons = '<div class="job-actions">' +
                    '<button class="btn-base btn-secondary btn-sm btn-with-icon retry-job" data-job-id="' + jobData.queue_id + '">' +
                    '<span class="dashicons dashicons-update btn-icon-size"></span> Retry</button></div>';
            }
            
            return '<tr class="' + rowClass + '" data-job-id="' + jobData.queue_id + '">' +
                '<td class="job-id-cell">' + jobData.queue_id + '</td>' +
                '<td class="job-type-cell">' + JobQueueAdmin.formatJobType(jobData.job_type) + '</td>' +
                '<td class="job-term-cell">' + termExplanation + '</td>' +
                '<td class="job-status-cell"><span class="job-status ' + statusClass + '">' + 
                JobQueueAdmin.capitalizeFirst(jobData.status) + '</span></td>' +
                '<td class="job-result-cell">' + resultContent + '</td>' +
                '<td class="job-created-cell">' + createdDate + '</td>' +
                '<td class="job-actions-cell">' + actionButtons + '</td>' +
                '</tr>';
        },
        
        /**
         * Format job type for display
         * 
         * @param {string} jobType Raw job type
         * @return {string} Formatted job type
         */
        formatJobType: function(jobType) {
            switch(jobType) {
                case 'blog_creation':
                    return 'Blog Creation';
                case 'ai_term_scan':
                    return 'Term Scan';
                default:
                    return jobType.replace(/_/g, ' ').replace(/\b\w/g, function(l){ return l.toUpperCase(); });
            }
        },
        
        /**
         * Capitalize first letter of string
         * 
         * @param {string} str Input string
         * @return {string} Capitalized string
         */
        capitalizeFirst: function(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1);
        },
        
        /**
         * Escape HTML characters
         * 
         * @param {string} text Input text
         * @return {string} Escaped text
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        /**
         * Add error message below the status text
         * 
         * @param {jQuery} $jobRow The job row element
         * @param {string} errorMessage The error message to display
         */
        addErrorToStatus: function($jobRow, errorMessage) {
            var $statusCell = $jobRow.find('.job-status');
            if ($statusCell.length) {
                // Remove any existing error message
                $statusCell.find('.status-error').remove();
                
                // Add error message below the status
                var $errorDiv = $('<div class="status-error" style="' +
                    'font-size: 11px; ' +
                    'color: #d63638; ' +
                    'margin-top: 2px; ' +
                    'line-height: 1.3;' +
                '">' + errorMessage + '</div>');
                
                $statusCell.append($errorDiv);
            }
        },
        
        /**
         * Remove error message from status
         * 
         * @param {jQuery} $jobRow The job row element
         */
        removeErrorFromStatus: function($jobRow) {
            $jobRow.find('.status-error').remove();
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        JobQueueAdmin.init();
    });
    
    // Make available globally
    window.JobQueueAdmin = JobQueueAdmin;
    
})(jQuery);