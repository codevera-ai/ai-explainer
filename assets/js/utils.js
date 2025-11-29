/**
 * WP AI Explainer - Shared JavaScript Utilities
 * Eliminates code duplication across explainer.js and tooltip.js
 * Provides centralized utility functions and debug logging
 */

(function() {
    'use strict';
    
    // Initialize namespace
    window.ExplainerPlugin = window.ExplainerPlugin || {};
    
    /**
     * Shared utility functions and state management
     * Eliminates 60%+ code duplication between explainer.js and tooltip.js
     */
    window.ExplainerPlugin.Utils = {
        
        // Shared state for utilities
        state: {
            localizedStrings: null,
            debugMode: false,
            cache: new Map()
        },
        
        /**
         * Centralized debug logging function
         * Uses configurable debug system that respects PHP config settings
         * 
         * @param {string} section Debug section (must match PHP config sections)
         * @param {string} message Log message
         * @param {object} data Additional data to log
         */
        debugLog: function(section, message, data = {}) {
            // Use new configurable debug system if available
            if (window.ExplainerDebug) {
                window.ExplainerDebug.log(section, message, data);
            } else if (window.ExplainerLogger) {
                // Fallback to central logger if available
                window.ExplainerLogger.debug(message, data, section);
            } else {
                // Final fallback to console
                if (console && console.log) {
                    console.log('[ExplainerPlugin:' + section + ']', message, data);
                }
            }
        },

        /**
         * Legacy debug function for backwards compatibility
         * @deprecated Use debugLog instead
         */
        debug: function(component, message, data = {}) {
            // Map component names to debug sections
            const sectionMap = {
                'Main': 'core',
                'Tooltip': 'core',
                'Utils': 'core',
                'Selection': 'selection_tracker',
                'API': 'api_proxy',
                'Admin': 'admin'
            };
            
            const section = sectionMap[component] || 'core';
            this.debugLog(section, message, data);
        },
        
        /**
         * Load localized strings from server with caching
         * Eliminates duplicate string loading logic
         * 
         * @returns {Promise} Promise that resolves with localized strings
         */
        loadLocalizedStrings: function() {
            // Return cached strings if available
            if (this.state.localizedStrings) {
                return Promise.resolve(this.state.localizedStrings);
            }
            
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', window.explainerAjax?.ajaxurl || '/wp-admin/admin-ajax.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onload = () => {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success && response.data.strings) {
                                this.state.localizedStrings = response.data.strings;
                                resolve(this.state.localizedStrings);
                            } else {
                                this._setFallbackStrings();
                                resolve(this.state.localizedStrings);
                            }
                        } catch (e) {
                            this._setFallbackStrings();
                            resolve(this.state.localizedStrings);
                        }
                    } else {
                        this._setFallbackStrings();
                        resolve(this.state.localizedStrings);
                    }
                };
                
                xhr.onerror = () => {
                    this._setFallbackStrings();
                    resolve(this.state.localizedStrings);
                };
                
                const data = 'action=explainer_get_localized_strings' + 
                            (window.explainerAjax?.nonce ? '&nonce=' + encodeURIComponent(window.explainerAjax.nonce) : '');
                xhr.send(data);
            });
        },
        
        /**
         * Get localized string with fallback
         * 
         * @param {string} key String key
         * @param {string} fallback Fallback string
         * @returns {string} Localized string
         */
        getLocalizedString: function(key, fallback = '') {
            if (this.state.localizedStrings && this.state.localizedStrings[key]) {
                return this.state.localizedStrings[key];
            }
            return fallback || key;
        },
        
        /**
         * Set fallback English strings when server loading fails
         * @private
         */
        _setFallbackStrings: function() {
            this.state.localizedStrings = {
                explanation: 'Explanation',
                loading: 'Loading...',
                error: 'Error',
                disclaimer: 'AI-generated content may not always be accurate',
                powered_by: 'Powered by',
                failed_to_get_explanation: 'Failed to get explanation',
                connection_error: 'Connection error. Please try again.',
                loading_explanation: 'Loading explanation...',
                selection_too_short: 'Selection too short (minimum %d characters)',
                selection_too_long: 'Selection too long (maximum %d characters)',
                selection_word_count: 'Selection must be between %d and %d words',
                ai_explainer_enabled: 'AI Explainer enabled. Select text to get explanations.',
                ai_explainer_disabled: 'AI Explainer disabled.',
                blocked_word_found: 'Your selection contains blocked content'
            };
        },
        
        /**
         * Debounce function utility
         * Prevents excessive function calls
         * 
         * @param {Function} func Function to debounce
         * @param {number} delay Delay in milliseconds
         * @returns {Function} Debounced function
         */
        debounce: function(func, delay) {
            let debounceTimer;
            return function(...args) {
                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }
                debounceTimer = setTimeout(() => func.apply(this, args), delay);
            };
        },
        
        /**
         * Throttle function utility
         * Limits function execution rate
         * 
         * @param {Function} func Function to throttle
         * @param {number} delay Delay in milliseconds
         * @returns {Function} Throttled function
         */
        throttle: function(func, delay) {
            let throttleTimer;
            return function(...args) {
                if (!throttleTimer) {
                    throttleTimer = setTimeout(() => {
                        func.apply(this, args);
                        throttleTimer = null;
                    }, delay);
                }
            };
        },
        
        /**
         * Memoization utility for caching function results
         * 
         * @param {Function} func Function to memoize
         * @param {Function} keyFunc Optional key generation function
         * @returns {Function} Memoized function
         */
        memoize: function(func, keyFunc) {
            return (...args) => {
                const key = keyFunc ? keyFunc(...args) : args[0];
                if (this.state.cache.has(key)) {
                    return this.state.cache.get(key);
                }
                const result = func.apply(this, args);
                this.state.cache.set(key, result);
                return result;
            };
        },
        
        /**
         * Request idle callback utility with fallback
         * 
         * @param {Function} callback Callback function
         * @returns {number} Request ID
         */
        requestIdleCallback: function(callback) {
            if (window.requestIdleCallback) {
                return window.requestIdleCallback(callback);
            }
            return setTimeout(callback, 1);
        },
        
        /**
         * Initialize utilities with settings
         * 
         * @param {object} settings Settings object
         */
        init: function(settings = {}) {
            this.state.debugMode = settings.debug_mode || false;
            
            // Load localized strings asynchronously
            this.loadLocalizedStrings().then(() => {
                this.debugLog('Utils', 'Localized strings loaded successfully', {
                    stringCount: Object.keys(this.state.localizedStrings).length
                });
            });
            
            this.debugLog('Utils', 'Shared utilities initialized', {
                debugMode: this.state.debugMode,
                cacheSize: this.state.cache.size
            });
        },
        
        /**
         * Clean up utilities and clear cache
         */
        cleanup: function() {
            this.state.cache.clear();
            this.state.localizedStrings = null;
            this.debugLog('Utils', 'Utilities cleaned up');
        }
    };
    
    /**
     * Auto-initialize if explainerAjax is available
     */
    if (typeof explainerAjax !== 'undefined' && explainerAjax.settings) {
        window.ExplainerPlugin.Utils.init(explainerAjax.settings);
    }
    
})();