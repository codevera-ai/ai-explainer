/**
 * JavaScript Logger for WP AI Explainer Plugin
 * Unified client-side logging system that logs to console when debug mode is enabled
 */

(function() {
    'use strict';

    // Create the logger class
    class ExplainerLogger {
        constructor() {
            this.debugMode = false;
            this.logs = [];
            this.maxLogs = 500; // Maximum number of logs to keep in memory
            
            // Check if debug mode is enabled (set by PHP)
            this.checkDebugMode();
            
            // Log levels
            this.levels = {
                ERROR: 'error',
                WARNING: 'warning', 
                INFO: 'info',
                DEBUG: 'debug',
                USER: 'user',
                API: 'api',
                PERFORMANCE: 'performance'
            };
            
            // Console styling
            this.styles = {
                error: 'color: #ff4444; font-weight: bold;',
                warning: 'color: #ff8800; font-weight: bold;',
                info: 'color: #0066cc; font-weight: bold;',
                debug: 'color: #666666;',
                user: 'color: #8b4513; font-weight: bold;',
                api: 'color: #9932cc; font-weight: bold;',
                performance: 'color: #228b22; font-weight: bold;'
            };
        }
        
        /**
         * Check if debug mode is enabled from PHP settings
         */
        checkDebugMode() {
            // Check if explainerAjax object exists and has debug flag
            if (typeof window.explainerAjax !== 'undefined') {
                // Check both possible locations for debug mode
                if (window.explainerAjax.settings && window.explainerAjax.settings.debug_mode !== undefined) {
                    this.debugMode = window.explainerAjax.settings.debug_mode === true || window.explainerAjax.settings.debug_mode === '1';
                } else if (window.explainerAjax.debug !== undefined) {
                    this.debugMode = window.explainerAjax.debug === true || window.explainerAjax.debug === '1';
                }
            }
            
            // Fallback: check if old explainer object exists and has debug flag
            if (typeof window.explainer !== 'undefined' && (window.explainer.debugMode === true || window.explainer.debugMode === '1')) {
                this.debugMode = true;
            }
            
            // Also check for debug query parameter (for testing)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('explainer_debug') === '1') {
                this.debugMode = true;
            }
        }
        
        /**
         * Main logging method
         * @param {string} level - Log level
         * @param {string} message - Log message
         * @param {Object} context - Additional context data
         * @param {string} component - Component name
         */
        log(level, message, context = {}, component = '') {
            const logEntry = {
                timestamp: new Date().toISOString(),
                level: level,
                component: component,
                message: message,
                context: context,
                url: window.location.href,
                userAgent: navigator.userAgent,
                memory: this.getMemoryInfo()
            };
            
            // Store in memory
            this.storeLogEntry(logEntry);
            
            // Only log to console if debug mode is enabled (except for errors)
            if (this.debugMode || level === this.levels.ERROR) {
                this.logToConsole(logEntry);
            }
            
            // Send critical errors to server
            if (level === this.levels.ERROR) {
                this.sendErrorToServer(logEntry);
            }
        }
        
        /**
         * Convenience methods for different log levels
         */
        error(message, context = {}, component = '') {
            this.log(this.levels.ERROR, message, context, component);
        }
        
        warning(message, context = {}, component = '') {
            this.log(this.levels.WARNING, message, context, component);
        }
        
        info(message, context = {}, component = '') {
            this.log(this.levels.INFO, message, context, component);
        }
        
        debug(message, context = {}, component = '') {
            this.log(this.levels.DEBUG, message, context, component);
        }
        
        user(message, context = {}, component = '') {
            this.log(this.levels.USER, message, context, component);
        }
        
        api(message, context = {}, component = '') {
            this.log(this.levels.API, message, context, component);
        }
        
        performance(message, context = {}, component = '') {
            this.log(this.levels.PERFORMANCE, message, context, component);
        }
        
        /**
         * Log API requests with comprehensive data
         */
        logApiRequest(url, method, requestData, responseData, duration, success, component = 'API') {
            const context = {
                url: url,
                method: method,
                duration: Math.round(duration),
                success: success,
                requestSize: JSON.stringify(requestData || {}).length,
                responseSize: JSON.stringify(responseData || {}).length,
                requestData: this.sanitizeApiData(requestData),
                responseData: this.sanitizeApiData(responseData)
            };
            
            const message = `${method} ${url} ${success ? 'success' : 'failure'} (${duration}ms)`;
            this.api(message, context, component);
        }
        
        /**
         * Log user interactions
         */
        logUserInteraction(action, element, details = {}) {
            const context = {
                action: action,
                element: element,
                elementType: element ? element.tagName : null,
                elementId: element ? element.id : null,
                elementClass: element ? element.className : null,
                details: details,
                pageUrl: window.location.href,
                timestamp: Date.now()
            };
            
            const message = `User ${action} on ${element ? element.tagName : 'unknown element'}`;
            this.user(message, context, 'UserInteraction');
        }
        
        /**
         * Log performance metrics
         */
        logPerformance(operation, startTime, endTime, details = {}) {
            const duration = endTime - startTime;
            const context = {
                operation: operation,
                duration: Math.round(duration),
                startTime: startTime,
                endTime: endTime,
                details: details,
                performance: this.getPerformanceMetrics()
            };
            
            const message = `Performance: ${operation} completed in ${Math.round(duration)}ms`;
            this.performance(message, context, 'Performance');
        }
        
        /**
         * Store log entry in memory
         */
        storeLogEntry(logEntry) {
            this.logs.push(logEntry);
            
            // Keep only the latest entries
            if (this.logs.length > this.maxLogs) {
                this.logs = this.logs.slice(-this.maxLogs);
            }
        }
        
        /**
         * Log to browser console with styling
         */
        logToConsole(logEntry) {
            const style = this.styles[logEntry.level] || '';
            const prefix = `[WP AI Explainer] [${logEntry.level.toUpperCase()}]`;
            const component = logEntry.component ? ` [${logEntry.component}]` : '';
            const timestamp = new Date(logEntry.timestamp).toLocaleTimeString();
            
            const fullMessage = `${prefix}${component} ${logEntry.message}`;
            
            // Use appropriate console method based on level
            switch (logEntry.level) {
                case this.levels.ERROR:
                    console.error(`%c${fullMessage}`, style, logEntry.context);
                    break;
                case this.levels.WARNING:
                    console.warn(`%c${fullMessage}`, style, logEntry.context);
                    break;
                case this.levels.INFO:
                    console.info(`%c${fullMessage}`, style, logEntry.context);
                    break;
                default:
                    console.log(`%c${fullMessage}`, style, logEntry.context);
            }
            
            // Also log timestamp and context if there's additional data
            if (Object.keys(logEntry.context).length > 0) {
                console.log(`%c└─ Context:`, 'color: #999; font-size: 0.9em;', logEntry.context);
            }
            console.log(`%c└─ Time: ${timestamp}`, 'color: #999; font-size: 0.8em;');
        }
        
        /**
         * Send critical errors to server for logging
         */
        sendErrorToServer(logEntry) {
            // Only send if we have AJAX capability
            if (typeof window.jQuery === 'undefined' || !window.explainer || !window.explainer.ajaxurl) {
                return;
            }
            
            // Prepare data for server
            const data = {
                action: 'explainer_log_js_error',
                nonce: window.explainer.nonce,
                error_data: {
                    message: logEntry.message,
                    component: logEntry.component,
                    context: logEntry.context,
                    url: logEntry.url,
                    userAgent: logEntry.userAgent,
                    timestamp: logEntry.timestamp
                }
            };
            
            // Send via AJAX (don't wait for response)
            jQuery.post(window.explainer.ajaxurl, data).fail(function() {
                // Silently fail - we don't want to create infinite error loops
                console.warn('[WP AI Explainer] Failed to send error to server');
            });
        }
        
        /**
         * Get memory information if available
         */
        getMemoryInfo() {
            if (performance.memory) {
                return {
                    used: Math.round(performance.memory.usedJSHeapSize / 1024 / 1024),
                    total: Math.round(performance.memory.totalJSHeapSize / 1024 / 1024),
                    limit: Math.round(performance.memory.jsHeapSizeLimit / 1024 / 1024)
                };
            }
            return null;
        }
        
        /**
         * Get performance metrics
         */
        getPerformanceMetrics() {
            const nav = performance.getEntriesByType('navigation')[0];
            if (nav) {
                return {
                    domContentLoaded: Math.round(nav.domContentLoadedEventEnd - nav.navigationStart),
                    loadComplete: Math.round(nav.loadEventEnd - nav.navigationStart),
                    domInteractive: Math.round(nav.domInteractive - nav.navigationStart)
                };
            }
            return null;
        }
        
        /**
         * Sanitize API data to remove sensitive information
         */
        sanitizeApiData(data) {
            if (!data || typeof data !== 'object') {
                return data;
            }
            
            const sensitiveKeys = ['api_key', 'authorization', 'token', 'password', 'secret'];
            const sanitized = {};
            
            for (const [key, value] of Object.entries(data)) {
                if (sensitiveKeys.some(sensitive => key.toLowerCase().includes(sensitive))) {
                    sanitized[key] = '[REDACTED]';
                } else if (typeof value === 'object' && value !== null) {
                    sanitized[key] = this.sanitizeApiData(value);
                } else {
                    sanitized[key] = value;
                }
            }
            
            return sanitized;
        }
        
        /**
         * Get logs for debugging
         */
        getLogs(limit = 50, level = null) {
            let logs = [...this.logs];
            
            // Filter by level if specified
            if (level) {
                logs = logs.filter(log => log.level === level);
            }
            
            // Sort by timestamp (newest first)
            logs.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
            
            // Limit results
            return logs.slice(0, limit);
        }
        
        /**
         * Clear all logs
         */
        clearLogs() {
            this.logs = [];
            this.info('JavaScript logs cleared', {}, 'Logger');
        }
        
        /**
         * Get logging statistics
         */
        getStats() {
            const stats = {
                total: this.logs.length,
                byLevel: {},
                byComponent: {},
                memoryUsage: JSON.stringify(this.logs).length,
                oldestEntry: null,
                newestEntry: null
            };
            
            this.logs.forEach(log => {
                // Count by level
                stats.byLevel[log.level] = (stats.byLevel[log.level] || 0) + 1;
                
                // Count by component
                const component = log.component || 'Unknown';
                stats.byComponent[component] = (stats.byComponent[component] || 0) + 1;
                
                // Track timestamps
                const timestamp = new Date(log.timestamp);
                if (!stats.oldestEntry || timestamp < new Date(stats.oldestEntry)) {
                    stats.oldestEntry = log.timestamp;
                }
                if (!stats.newestEntry || timestamp > new Date(stats.newestEntry)) {
                    stats.newestEntry = log.timestamp;
                }
            });
            
            return stats;
        }
        
        /**
         * Enable or disable debug mode
         */
        setDebugMode(enabled) {
            this.debugMode = enabled;
            this.info(`Debug mode ${enabled ? 'enabled' : 'disabled'}`, {}, 'Logger');
        }
        
        /**
         * Check if debug mode is enabled
         */
        isDebugEnabled() {
            return this.debugMode;
        }
    }

    // Create global instance
    const logger = new ExplainerLogger();
    window.ExplainerLogger = logger;
    
    // Convenience global functions
    window.explainerLog = function(level, message, context, component) {
        window.ExplainerLogger.log(level, message, context, component);
    };
    
    // Convenience shorthand functions
    window.explainerLogError = function(message, context, component) {
        window.ExplainerLogger.error(message, context, component);
    };
    
    window.explainerLogWarning = function(message, context, component) {
        window.ExplainerLogger.warning(message, context, component);
    };
    
    window.explainerLogInfo = function(message, context, component) {
        window.ExplainerLogger.info(message, context, component);
    };
    
    window.explainerLogDebug = function(message, context, component) {
        window.ExplainerLogger.debug(message, context, component);
    };
    
    window.explainerLogUser = function(message, context, component) {
        window.ExplainerLogger.user(message, context, component);
    };
    
    window.explainerLogApi = function(message, context, component) {
        window.ExplainerLogger.api(message, context, component);
    };
    
    window.explainerLogPerformance = function(message, context, component) {
        window.ExplainerLogger.performance(message, context, component);
    };
    
    // Check for explainerAjax periodically until it's available (fallback)
    let checkCount = 0;
    let loggedInitialization = false;
    const recheckDebugMode = function() {
        if (typeof window.explainerAjax !== 'undefined' && window.explainerAjax.settings) {
            logger.checkDebugMode();
            
            // Log initialization message now that we have the correct debug mode
            if (!loggedInitialization) {
                loggedInitialization = true;
                logger.info('JavaScript logger initialized', {
                    debugMode: logger.isDebugEnabled(),
                    userAgent: navigator.userAgent,
                    url: window.location.href
                }, 'Logger');
            }
            return;
        }
        
        checkCount++;
        if (checkCount < 10) { // Try for up to 1 second
            setTimeout(recheckDebugMode, 100);
        } else if (!loggedInitialization) {
            // Fallback: log initialization even if explainerAjax wasn't found
            loggedInitialization = true;
            logger.info('JavaScript logger initialized', {
                debugMode: logger.isDebugEnabled(),
                userAgent: navigator.userAgent,
                url: window.location.href
            }, 'Logger');
        }
    };
    
    // Start checking after a short delay to allow explainerAjax to load
    setTimeout(recheckDebugMode, 50);
    
    // Catch and log unhandled JavaScript errors
    window.addEventListener('error', function(event) {
        window.ExplainerLogger.error('Unhandled JavaScript error', {
            message: event.message,
            filename: event.filename,
            lineno: event.lineno,
            colno: event.colno,
            error: event.error ? event.error.toString() : 'No error object'
        }, 'GlobalErrorHandler');
    });
    
    // Catch and log unhandled promise rejections
    window.addEventListener('unhandledrejection', function(event) {
        window.ExplainerLogger.error('Unhandled promise rejection', {
            reason: event.reason ? event.reason.toString() : 'Unknown reason',
            promise: event.promise
        }, 'GlobalErrorHandler');
    });

})();