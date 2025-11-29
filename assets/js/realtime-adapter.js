/**
 * WP AI Explainer - WPAIE Scheduler Adapter
 * 
 * Simplified adapter for WPAIE Scheduler job status polling
 * that provides subscription interface for job progress updates
 */

(function() {
    'use strict';
    
    // Ensure plugin namespace exists
    window.ExplainerPlugin = window.ExplainerPlugin || {};
    
    /**
     * Default Configuration
     */
    const DEFAULT_CONFIG = {
        baseURL: '/wp-admin/admin-ajax.php',
        pollInterval: 2000,           // 2 seconds
        pollBackoffMax: 8000,         // 8 seconds
        enableDebugLogging: false,
        maxRetries: 3,
        retryDelay: 5000              // 5 seconds
    };
    
    /**
     * Connection Options
     */
    const DEFAULT_CONNECTION_OPTIONS = {
        priority: 'normal',           // 'high', 'normal', 'low'
        autoReconnect: true,
        maxRetries: 10,
        onStatusChange: null          // Callback for status changes
    };
    
    /**
     * WPAIE Scheduler Adapter Class
     */
    class WPAIExplainerWPAIESchedulerAdapter {
        
        /**
         * Constructor
         * 
         * @param {object} config Configuration options
         */
        constructor(config = {}) {
            this.config = this.mergeConfig(config);
            this.connections = new Map();
            this.pollTimers = new Map();
            this.globalErrorHandlers = [];
            this.isInitialised = false;
            
            // Global state
            this.globalPaused = false;
            this.shutdownInProgress = false;
            
            this.log('WPAIE Scheduler Adapter initialised', {
                config: this.config
            });
        }
        
        /**
         * Initialize the adapter
         * 
         * @return {Promise<void>}
         */
        async initialise() {
            if (this.isInitialised) {
                return;
            }
            
            try {
                // Setup page visibility handling
                this.initPageVisibilityAPI();
                
                // Setup beforeunload cleanup
                this.initCleanupHandlers();
                
                // Initialise configuration from WordPress
                await this.loadWordPressConfig();
                
                this.isInitialised = true;
                this.log('Adapter initialised successfully');
                
            } catch (error) {
                this.handleError('Adapter initialization failed', error);
                throw error;
            }
        }
        
        /**
         * Connect to job progress updates
         * 
         * @param {string} progressId Progress ID to monitor
         * @param {function} onUpdate Progress update callback
         * @param {object} options Connection options
         * @return {Promise<ProgressConnection>}
         */
        async connect(progressId, onUpdate, options = {}) {
            try {
                // Ensure adapter is initialised
                if (!this.isInitialised) {
                    await this.initialise();
                }
                
                // Merge options with defaults
                const connectionOptions = { ...DEFAULT_CONNECTION_OPTIONS, ...options };
                
                // Check if we already have a connection for this progress ID
                if (this.connections.has(progressId)) {
                    const existingConnection = this.connections.get(progressId);
                    this.log('Existing connection found for progress ID', { progressId });
                    
                    // Update the progress handler
                    existingConnection.updateProgressHandler(onUpdate);
                    return existingConnection;
                }
                
                // Create new progress connection
                const connection = new ProgressConnection(progressId, this, onUpdate, connectionOptions);
                this.connections.set(progressId, connection);
                
                this.log('Creating connection for progress ID', { progressId, options: connectionOptions });
                
                // Start the connection
                await connection.connect();
                
                return connection;
                
            } catch (error) {
                this.handleError('Failed to connect to progress updates', error, { progressId });
                throw error;
            }
        }
        
        /**
         * Disconnect from progress updates
         * 
         * @param {string} progressId Progress ID to disconnect from
         * @return {Promise<void>}
         */
        async disconnect(progressId) {
            try {
                const connection = this.connections.get(progressId);
                if (!connection) {
                    this.log('No connection found for progress ID', { progressId });
                    return;
                }
                
                this.log('Disconnecting from progress ID', { progressId });
                
                // Disconnect the connection
                await connection.disconnect();
                
                // Clean up
                this.connections.delete(progressId);
                this.clearPollTimer(progressId);
                
            } catch (error) {
                this.handleError('Failed to disconnect from progress updates', error, { progressId });
            }
        }
        
        /**
         * Disconnect from all progress monitoring
         * 
         * @return {Promise<void>}
         */
        async disconnectAll() {
            this.log('Disconnecting from all progress monitoring');
            
            const disconnectPromises = [];
            for (const progressId of this.connections.keys()) {
                disconnectPromises.push(this.disconnect(progressId));
            }
            
            await Promise.all(disconnectPromises);
            
            this.log('All connections disconnected');
        }
        
        /**
         * Add global error handler
         * 
         * @param {function} callback Error handler callback
         */
        onError(callback) {
            if (typeof callback === 'function') {
                this.globalErrorHandlers.push(callback);
            }
        }
        
        /**
         * Remove global error handler
         * 
         * @param {function} callback Error handler callback to remove
         */
        removeErrorHandler(callback) {
            const index = this.globalErrorHandlers.indexOf(callback);
            if (index > -1) {
                this.globalErrorHandlers.splice(index, 1);
            }
        }
        
        /**
         * Get connection status for a progress ID
         * 
         * @param {string} progressId Progress ID to check
         * @return {string} Connection status
         */
        getConnectionStatus(progressId) {
            const connection = this.connections.get(progressId);
            if (!connection) {
                return 'disconnected';
            }
            return connection.getStatus();
        }
        
        /**
         * Get comprehensive status for all connections
         * 
         * @return {object} Status object
         */
        getStatus() {
            const status = {
                initialised: this.isInitialised,
                totalConnections: this.connections.size,
                globalPaused: this.globalPaused,
                connections: {}
            };
            
            for (const [progressId, connection] of this.connections) {
                status.connections[progressId] = {
                    status: connection.getStatus(),
                    retryCount: connection.retryCount
                };
            }
            
            return status;
        }
        
        /**
         * Pause all connections
         */
        pauseAll() {
            if (this.globalPaused) {
                return;
            }
            
            this.log('Pausing all connections');
            this.globalPaused = true;
            
            for (const connection of this.connections.values()) {
                connection.pause();
            }
        }
        
        /**
         * Resume all connections
         */
        resumeAll() {
            if (!this.globalPaused) {
                return;
            }
            
            this.log('Resuming all connections');
            this.globalPaused = false;
            
            for (const connection of this.connections.values()) {
                connection.resume();
            }
        }
        
        /**
         * Merge configuration with defaults
         * 
         * @param {object} config User configuration
         * @return {object} Merged configuration
         */
        mergeConfig(config) {
            return { ...DEFAULT_CONFIG, ...config };
        }
        
        /**
         * Load configuration from WordPress
         * 
         * @return {Promise<void>}
         */
        async loadWordPressConfig() {
            try {
                // Try to get config from global WordPress variables
                if (window.wpAIExplainerJobStatus) {
                    const wpConfig = window.wpAIExplainerJobStatus;
                    
                    if (wpConfig.ajaxurl) {
                        this.config.baseURL = wpConfig.ajaxurl;
                    }
                    
                    if (wpConfig.polling_interval) {
                        this.config.pollInterval = wpConfig.polling_interval;
                    }
                    
                    this.log('WordPress configuration loaded', { wpConfig });
                }
            } catch (error) {
                this.log('Failed to load WordPress configuration', { error: error.message });
            }
        }
        
        /**
         * Initialize Page Visibility API
         */
        initPageVisibilityAPI() {
            // Listen for visibility changes
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.pauseAll();
                } else {
                    this.resumeAll();
                }
            });
            
            // Initial state check
            if (document.hidden) {
                this.pauseAll();
            }
        }
        
        /**
         * Initialize cleanup handlers
         */
        initCleanupHandlers() {
            // Clean up on page unload
            window.addEventListener('beforeunload', () => {
                this.shutdownInProgress = true;
                this.disconnectAll();
            });
            
            // Clean up on page hide (for mobile browsers)
            window.addEventListener('pagehide', () => {
                this.shutdownInProgress = true;
                this.disconnectAll();
            });
        }
        
        /**
         * Clear poll timer for a progress ID
         * 
         * @param {string} progressId Progress ID to clear timer for
         */
        clearPollTimer(progressId) {
            const timer = this.pollTimers.get(progressId);
            if (timer) {
                clearTimeout(timer);
                this.pollTimers.delete(progressId);
            }
        }
        
        /**
         * Handle global errors
         * 
         * @param {string} message Error message
         * @param {Error} error Error object
         * @param {object} context Additional context
         */
        handleError(message, error, context = {}) {
            const errorInfo = {
                message,
                error: error?.message || error,
                context,
                timestamp: new Date().toISOString()
            };
            
            this.log('Error: ' + message, errorInfo);
            
            // Call global error handlers
            this.globalErrorHandlers.forEach(handler => {
                try {
                    handler(errorInfo);
                } catch (handlerError) {
                    console.error('Error handler failed:', handlerError);
                }
            });
        }
        
        /**
         * Log message with context
         * 
         * @param {string} message Log message
         * @param {object} context Additional context
         */
        log(message, context = {}) {
            if (this.config.enableDebugLogging) {
                console.log('[ActionSchedulerAdapter]', message, {
                    timestamp: new Date().toISOString(),
                    connections: this.connections.size,
                    ...context
                });
            }
        }
    }
    
    /**
     * Individual Progress Connection Manager
     */
    class ProgressConnection {
        
        /**
         * Constructor
         * 
         * @param {string} progressId Progress ID
         * @param {WPAIExplainerActionSchedulerAdapter} adapter Parent adapter
         * @param {function} onUpdate Progress update handler
         * @param {object} options Connection options
         */
        constructor(progressId, adapter, onUpdate, options) {
            this.progressId = progressId;
            this.adapter = adapter;
            this.onUpdate = onUpdate;
            this.options = options;
            
            // Connection state
            this.status = 'disconnected';
            this.retryCount = 0;
            this.isPaused = false;
            this.pollTimer = null;
            
            this.log('ProgressConnection created', {
                progressId: this.progressId,
                options: this.options
            });
        }
        
        /**
         * Connect to progress updates
         * 
         * @return {Promise<void>}
         */
        async connect() {
            if (this.status === 'connected' || this.status === 'connecting') {
                this.log('Connection already exists or in progress');
                return;
            }
            
            try {
                this.status = 'connecting';
                this.retryCount = 0;
                
                this.log('Starting connection');
                
                // Start polling
                this.startPolling();
                
                this.log('Connection established');
                
            } catch (error) {
                this.status = 'disconnected';
                this.handleConnectionError('Connection failed', error);
                throw error;
            }
        }
        
        /**
         * Disconnect from progress updates
         * 
         * @return {Promise<void>}
         */
        async disconnect() {
            this.log('Disconnecting');
            
            this.status = 'disconnected';
            this.stopPolling();
            
            this.log('Disconnected');
        }
        
        /**
         * Pause the connection
         */
        pause() {
            if (this.isPaused) {
                return;
            }
            
            this.log('Pausing connection');
            this.isPaused = true;
            this.stopPolling();
        }
        
        /**
         * Resume the connection
         */
        resume() {
            if (!this.isPaused) {
                return;
            }
            
            this.log('Resuming connection');
            this.isPaused = false;
            
            if (this.status === 'connected' || this.status === 'connecting') {
                this.startPolling();
            }
        }
        
        /**
         * Update progress handler
         * 
         * @param {function} onUpdate New progress handler
         */
        updateProgressHandler(onUpdate) {
            this.onUpdate = onUpdate;
            this.log('Progress handler updated');
        }
        
        /**
         * Get connection status
         * 
         * @return {string} Status
         */
        getStatus() {
            return this.status;
        }
        
        /**
         * Start polling for progress updates
         */
        startPolling() {
            if (this.isPaused || !this.adapter.isInitialised) {
                return;
            }
            
            this.status = 'connected';
            this.poll();
        }
        
        /**
         * Stop polling
         */
        stopPolling() {
            if (this.pollTimer) {
                clearTimeout(this.pollTimer);
                this.pollTimer = null;
            }
        }
        
        /**
         * Perform polling request
         */
        async poll() {
            if (this.isPaused || this.status === 'disconnected') {
                return;
            }
            
            try {
                const response = await this.makeAjaxRequest();
                
                if (response.success && response.data) {
                    // Reset retry count on success
                    this.retryCount = 0;
                    
                    // Process progress update
                    this.handleProgressUpdate(response.data);
                    
                    // Check if job is complete
                    if (['completed', 'failed'].includes(response.data.status)) {
                        this.log('Job completed, stopping polling');
                        this.stopPolling();
                        return;
                    }
                } else {
                    this.log('Invalid response from server', response);
                    this.handlePollingError('Invalid response from server');
                }
                
            } catch (error) {
                this.log('Polling error', { error: error.message });
                this.handlePollingError(error.message);
            }
            
            // Schedule next poll if still active
            if (this.status === 'connected' && !this.isPaused) {
                this.pollTimer = setTimeout(() => this.poll(), this.adapter.config.pollInterval);
            }
        }
        
        /**
         * Make AJAX request for progress status
         * 
         * @return {Promise<object>} Response data
         */
        async makeAjaxRequest() {
            const formData = new FormData();
            formData.append('action', 'wp_ai_explainer_get_job_status');
            formData.append('progress_id', this.progressId);
            
            // Add nonce if available
            if (window.wpAIExplainerJobStatus && window.wpAIExplainerJobStatus.nonce) {
                formData.append('nonce', window.wpAIExplainerJobStatus.nonce);
            }
            
            const response = await fetch(this.adapter.config.baseURL, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return await response.json();
        }
        
        /**
         * Handle progress update
         * 
         * @param {object} progressData Progress data
         */
        handleProgressUpdate(progressData) {
            try {
                // Call progress update handler
                if (typeof this.onUpdate === 'function') {
                    this.onUpdate(progressData, {
                        progressId: this.progressId
                    });
                }
                
                // Call status change handler if provided
                if (this.options.onStatusChange) {
                    this.options.onStatusChange(progressData.status, progressData);
                }
                
                this.log('Progress update processed', {
                    status: progressData.status,
                    progress: progressData.progress_percent
                });
                
            } catch (error) {
                this.handleConnectionError('Progress update processing failed', error);
            }
        }
        
        /**
         * Handle polling errors with retry logic
         * 
         * @param {string} error Error message
         */
        handlePollingError(error) {
            this.retryCount++;
            
            if (this.retryCount >= this.adapter.config.maxRetries) {
                this.log('Max retries exceeded, stopping polling');
                this.status = 'failed';
                this.stopPolling();
            } else {
                this.log(`Retry ${this.retryCount}/${this.adapter.config.maxRetries} in ${this.adapter.config.retryDelay}ms`);
                
                // Retry after delay
                setTimeout(() => {
                    if (this.status === 'connected') {
                        this.poll();
                    }
                }, this.adapter.config.retryDelay);
            }
        }
        
        /**
         * Handle connection error
         * 
         * @param {string} message Error message
         * @param {Error} error Error object
         */
        handleConnectionError(message, error) {
            const errorInfo = {
                message,
                error: error?.message || error,
                progressId: this.progressId,
                retryCount: this.retryCount
            };
            
            this.log('Connection error: ' + message, errorInfo);
            
            // Update status
            if (this.retryCount >= this.options.maxRetries) {
                this.status = 'failed';
            } else {
                this.status = 'error';
            }
            
            // Pass to adapter error handler
            this.adapter.handleError('Progress connection error: ' + message, error, errorInfo);
        }
        
        /**
         * Log message with context
         * 
         * @param {string} message Log message
         * @param {object} context Additional context
         */
        log(message, context = {}) {
            this.adapter.log(`[${this.progressId}] ${message}`, {
                status: this.status,
                ...context
            });
        }
    }
    
    // Expose to plugin namespace
    ExplainerPlugin.WPAIESchedulerAdapter = WPAIExplainerWPAIESchedulerAdapter;
    
    // Provide convenience factory function
    ExplainerPlugin.createWPAIESchedulerAdapter = function(config) {
        return new WPAIExplainerWPAIESchedulerAdapter(config);
    };
    
    // Maintain compatibility with old RealtimeAdapter name
    ExplainerPlugin.RealtimeAdapter = WPAIExplainerWPAIESchedulerAdapter;
    ExplainerPlugin.createRealtimeAdapter = function(config) {
        return new WPAIExplainerWPAIESchedulerAdapter(config);
    };
    
})();