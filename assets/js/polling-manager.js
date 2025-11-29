/**
 * Centralized Polling Manager for WP AI Explainer
 * 
 * Manages all AJAX polling across admin panels with intelligent
 * tab-awareness, background detection, and dynamic intervals
 */

(function($) {
    'use strict';

    window.ExplainerPollingManager = {
        
        /**
         * Active polling timers and configurations
         */
        activePolls: new Map(),
        
        /**
         * Global polling pause state (for background tabs)
         */
        globalPaused: false,
        
        /**
         * Debug mode
         */
        debugMode: false,
        
        /**
         * Initialize the polling manager
         */
        init: function() {
            this.setupBackgroundDetection();
            this.setupTabChangeDetection();
            this.log('Polling Manager initialised');
        },
        
        /**
         * Start conditional polling for a panel
         * 
         * @param {string} panelId Panel identifier
         * @param {Object} options Polling configuration
         */
        startConditionalPolling: function(panelId, options) {
            // Stop any existing polling for this panel
            this.stopPolling(panelId);
            
            const config = {
                interval: options.interval || 3000,
                requiresActiveTab: options.requiresActiveTab !== false, // Default true
                requiresProcessingJobs: options.requiresProcessingJobs !== false, // Default true
                maxIdleCycles: options.maxIdleCycles || 10,
                dynamicInterval: options.dynamicInterval !== false, // Default true
                minInterval: options.minInterval || 2000,
                maxInterval: options.maxInterval || 8000,
                callback: options.callback || function() {}
            };
            
            let idleCycles = 0;
            let currentInterval = config.interval;
            let intervalId = null;
            
            const poll = () => {
                // Skip if globally paused (background tab)
                if (this.globalPaused) {
                    this.log('Global pause active, skipping poll for ' + panelId);
                    return;
                }
                
                // Tab activity check
                if (config.requiresActiveTab && !this.isPanelTabActive(panelId)) {
                    this.log('Tab inactive for ' + panelId + ', skipping poll');
                    return;
                }
                
                // Processing jobs check
                const hasProcessingJobs = this.hasProcessingJobs(panelId);
                if (config.requiresProcessingJobs && !hasProcessingJobs) {
                    idleCycles++;
                    this.log('No processing jobs for ' + panelId + ', idle cycle ' + idleCycles);
                    
                    if (idleCycles >= config.maxIdleCycles) {
                        this.log('Max idle cycles reached for ' + panelId + ', stopping poll');
                        this.stopPolling(panelId);
                        return;
                    }
                } else {
                    idleCycles = 0; // Reset idle counter
                }
                
                // Adjust interval dynamically
                if (config.dynamicInterval) {
                    const newInterval = this.calculateOptimalInterval(panelId, hasProcessingJobs, config);
                    if (newInterval !== currentInterval) {
                        this.log('Adjusting interval for ' + panelId + ' from ' + currentInterval + 'ms to ' + newInterval + 'ms');
                        currentInterval = newInterval;
                        this.restartPolling(panelId, currentInterval);
                        return;
                    }
                }
                
                // Execute the actual polling
                this.log('Executing poll for ' + panelId);
                config.callback();
            };
            
            // Execute the first poll immediately to avoid race conditions
            this.log('Executing immediate first poll for ' + panelId + ' to avoid race conditions');
            setTimeout(poll, 100); // Small delay to allow UI updates
            
            // Start the interval for subsequent polls
            intervalId = setInterval(poll, currentInterval);
            
            // Store the polling configuration
            this.activePolls.set(panelId, {
                intervalId: intervalId,
                config: config,
                currentInterval: currentInterval,
                idleCycles: idleCycles,
                startTime: Date.now()
            });
            
            this.log('Started conditional polling for ' + panelId + ' with ' + currentInterval + 'ms interval');
        },
        
        /**
         * Stop polling for a specific panel
         * 
         * @param {string} panelId Panel identifier
         */
        stopPolling: function(panelId) {
            if (this.activePolls.has(panelId)) {
                const poll = this.activePolls.get(panelId);
                clearInterval(poll.intervalId);
                this.activePolls.delete(panelId);
                this.log('Stopped polling for ' + panelId);
                return true;
            }
            return false;
        },
        
        /**
         * Restart polling with new interval
         * 
         * @param {string} panelId Panel identifier
         * @param {number} newInterval New interval in milliseconds
         */
        restartPolling: function(panelId, newInterval) {
            if (this.activePolls.has(panelId)) {
                const poll = this.activePolls.get(panelId);
                clearInterval(poll.intervalId);
                
                // Update interval and restart
                poll.currentInterval = newInterval;
                poll.intervalId = setInterval(() => {
                    poll.config.callback();
                }, newInterval);
                
                this.activePolls.set(panelId, poll);
            }
        },
        
        /**
         * Calculate optimal polling interval based on activity
         * 
         * @param {string} panelId Panel identifier
         * @param {boolean} hasActivity Whether there is current activity
         * @param {Object} config Polling configuration
         * @return {number} Optimal interval in milliseconds
         */
        calculateOptimalInterval: function(panelId, hasActivity, config) {
            if (hasActivity) {
                // Faster polling when active
                return config.minInterval;
            } else {
                // Gradual slowdown when idle
                const poll = this.activePolls.get(panelId);
                const timeSinceStart = Date.now() - poll.startTime;
                const minutesSinceStart = timeSinceStart / (1000 * 60);
                
                // Increase interval every 2 minutes of inactivity, up to max
                const multiplier = Math.min(Math.floor(minutesSinceStart / 2) + 1, 4);
                return Math.min(config.interval * multiplier, config.maxInterval);
            }
        },
        
        /**
         * Check if panel's tab is currently active
         * 
         * @param {string} panelId Panel identifier
         * @return {boolean} True if tab is active
         */
        isPanelTabActive: function(panelId) {
            const panelTabMap = {
                // Job queue panels removed from tabs - only main job queue page remains
            };
            
            const expectedTab = panelTabMap[panelId];
            if (!expectedTab) return true; // Unknown panel, allow polling
            
            const currentTab = $('.nav-tab-active').attr('href');
            return currentTab === '#' + expectedTab;
        },
        
        /**
         * Check if panel has processing jobs
         * 
         * @param {string} panelId Panel identifier
         * @return {boolean} True if has processing jobs
         */
        hasProcessingJobs: function(panelId) {
            // Check DOM for processing job indicators
            const $panel = $('#' + panelId);
            if ($panel.length === 0) return false;
            
            // Look for processing status badges
            const processingJobs = $panel.find('.job-status-processing').length;
            
            // Also check for pending jobs that might transition to processing
            const pendingJobs = $panel.find('.job-status-pending').length;
            
            // If polling just started (within 30 seconds), be more lenient
            const poll = this.activePolls.get(panelId);
            const justStarted = poll && (Date.now() - poll.startTime) < 30000;
            
            if (justStarted && (processingJobs > 0 || pendingJobs > 0)) {
                this.log('Panel ' + panelId + ' has activity (processing: ' + processingJobs + ', pending: ' + pendingJobs + ') and just started - continuing poll');
                return true;
            }
            
            return processingJobs > 0;
        },
        
        /**
         * Setup background tab detection
         */
        setupBackgroundDetection: function() {
            const self = this;
            
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    self.pauseAllPolling();
                } else {
                    self.resumeAllPolling();
                }
            });
        },
        
        /**
         * Setup tab change detection
         */
        setupTabChangeDetection: function() {
            const self = this;
            
            // Listen for WordPress admin tab changes
            $(document).on('click', '.nav-tab', function() {
                setTimeout(function() {
                    self.handleTabChange();
                }, 100); // Small delay to ensure tab change is processed
            });
        },
        
        /**
         * Handle tab change event
         */
        handleTabChange: function() {
            this.log('Tab change detected, reviewing active polls');
            
            // Resume polling for newly active panels, pause for inactive ones
            this.activePolls.forEach((poll, panelId) => {
                if (poll.config.requiresActiveTab) {
                    const isActive = this.isPanelTabActive(panelId);
                    this.log('Panel ' + panelId + ' active: ' + isActive);
                    
                    if (isActive) {
                        // Immediate poll when tab becomes active
                        poll.config.callback();
                    }
                }
            });
        },
        
        /**
         * Pause all active polling (for background tabs)
         */
        pauseAllPolling: function() {
            this.globalPaused = true;
            this.log('Paused all polling (background tab)');
        },
        
        /**
         * Resume all active polling (when tab becomes active)
         */
        resumeAllPolling: function() {
            this.globalPaused = false;
            this.log('Resumed all polling (tab active)');
            
            // Immediate refresh when returning to tab
            this.activePolls.forEach((poll, panelId) => {
                if (this.isPanelTabActive(panelId)) {
                    this.log('Immediate poll on resume for ' + panelId);
                    poll.config.callback();
                }
            });
        },
        
        /**
         * Get polling statistics for debugging
         * 
         * @return {Object} Statistics object
         */
        getStats: function() {
            const stats = {
                activePollCount: this.activePolls.size,
                globalPaused: this.globalPaused,
                panels: {}
            };
            
            this.activePolls.forEach((poll, panelId) => {
                stats.panels[panelId] = {
                    currentInterval: poll.currentInterval,
                    idleCycles: poll.idleCycles,
                    uptime: Date.now() - poll.startTime,
                    tabActive: this.isPanelTabActive(panelId),
                    hasProcessingJobs: this.hasProcessingJobs(panelId)
                };
            });
            
            return stats;
        },
        
        /**
         * Enable or disable debug logging
         * 
         * @param {boolean} enabled Whether to enable debug mode
         */
        setDebugMode: function(enabled) {
            this.debugMode = enabled;
            this.log('Debug mode ' + (enabled ? 'enabled' : 'disabled'));
        },
        
        /**
         * Debug logging
         * 
         * @param {string} message Log message
         */
        log: function(message) {
            if (this.debugMode) {
                console.log('[PollingManager] ' + message);
            }
        },
        
        /**
         * Stop all polling (for cleanup)
         */
        stopAllPolling: function() {
            this.activePolls.forEach((poll, panelId) => {
                clearInterval(poll.intervalId);
            });
            this.activePolls.clear();
            this.log('Stopped all polling');
        }
    };
    
    // Auto-initialise when document is ready
    $(document).ready(function() {
        ExplainerPollingManager.init();
        
        // Enable debug mode if WordPress debug is enabled
        if (typeof window.wpAiExplainer !== 'undefined' && window.wpAiExplainer.debugMode) {
            ExplainerPollingManager.setDebugMode(true);
        }
    });
    
})(jQuery);