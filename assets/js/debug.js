/**
 * JavaScript Debug Utility
 * 
 * Configurable console logging that respects the PHP debug config settings.
 * This integrates with the PHP debug configuration system.
 */

(function(window) {
    'use strict';

    // Debug configuration object
    let debugConfig = {
        enabled: false,
        sections: {}
    };

    // Check if debug configuration was loaded from PHP
    if (window.ExplainerDebugConfig && typeof window.ExplainerDebugConfig === 'object') {
        debugConfig = window.ExplainerDebugConfig;
    }

    /**
     * Main debug logging function
     * @param {string} section - Debug section (must match PHP config sections)
     * @param {string} message - Log message
     * @param {*} data - Optional data to log
     * @param {string} level - Log level (log, warn, error, debug)
     */
    function debugLog(section, message, data = null, level = 'log') {
        // Check if debug logging is globally enabled
        if (!debugConfig.enabled) {
            return;
        }

        // Check if this specific section is enabled
        if (!debugConfig.sections || !debugConfig.sections[section]) {
            return;
        }

        // Validate console method exists
        if (!console || typeof console[level] !== 'function') {
            return;
        }

        // Format the log message
        const prefix = `[WP AI Explainer:${section}]`;
        
        // Log based on level
        if (data !== null) {
            console[level](prefix, message, data);
        } else {
            console[level](prefix, message);
        }
    }

    /**
     * Convenience methods for different log levels
     */
    const Debug = {
        /**
         * Standard debug log
         */
        log: function(section, message, data = null) {
            debugLog(section, message, data, 'log');
        },

        /**
         * Warning log
         */
        warn: function(section, message, data = null) {
            debugLog(section, message, data, 'warn');
        },

        /**
         * Error log
         */
        error: function(section, message, data = null) {
            debugLog(section, message, data, 'error');
        },

        /**
         * Debug level log
         */
        debug: function(section, message, data = null) {
            debugLog(section, message, data, 'debug');
        },

        /**
         * Information log
         */
        info: function(section, message, data = null) {
            debugLog(section, message, data, 'info');
        },

        /**
         * Check if debug is enabled for a specific section
         */
        isEnabled: function(section) {
            return debugConfig.enabled && debugConfig.sections && debugConfig.sections[section] === true;
        },

        /**
         * Get current debug configuration
         */
        getConfig: function() {
            return debugConfig;
        },

        /**
         * Update debug configuration (useful for dynamic updates)
         */
        updateConfig: function(newConfig) {
            if (newConfig && typeof newConfig === 'object') {
                debugConfig = { ...debugConfig, ...newConfig };
            }
        }
    };

    // Make Debug available globally
    window.ExplainerDebug = Debug;

    // Also make it available on the main plugin object if it exists
    if (window.ExplainerPlugin) {
        window.ExplainerPlugin.Debug = Debug;
    }

})(window);