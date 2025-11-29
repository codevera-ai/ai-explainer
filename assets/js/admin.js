/**
 * WP AI Explainer Admin - Main Entry Point
 * 
 * This file loads and orchestrates all admin modules.
 * It replaces the monolithic admin.js with a modular approach.
 */

(function($) {
    'use strict';

    /**
     * Module loader - ensures dependencies are loaded in correct order
     */
    const ModuleLoader = {
        
        baseModules: [
            'provider-config',
            'api-testing',
            'form-validation',
            'debug-logging',
            'job-monitoring',
            'core'
        ],
        
        loadedModules: [],
        
        /**
         * Get modules to load (with conditional filtering)
         */
        get modules() {
            const filteredModules = [...this.baseModules];
            
            // Skip job-monitoring on tabs that use new shared system
            if (this.isUsingSharedJobQueue()) {
                const index = filteredModules.indexOf('job-monitoring');
                if (index > -1) {
                    filteredModules.splice(index, 1);
                }
            }
            
            return filteredModules;
        },
        
        /**
         * Check if current page uses the new shared job queue system
         */
        isUsingSharedJobQueue: function() {
            const urlParams = new URLSearchParams(window.location.search);
            const currentTab = urlParams.get('tab');
            
            // Use shared system on popular tab (default/no tab) and post-scan tab
            return !currentTab || currentTab === 'popular' || currentTab === 'post-scan';
        },
        
        /**
         * Load a single module
         */
        loadModule: function(moduleName) {
            return new Promise((resolve, reject) => {
                const script = document.createElement('script');
                const basePath = this.getBasePath().replace(/\/$/, ''); // Remove trailing slash
                script.src = `${basePath}/admin/${moduleName}.js`;
                script.async = false; // Ensure order
                
                script.onload = () => {
                    this.loadedModules.push(moduleName);
                    if (window.ExplainerDebug) {
                        window.ExplainerDebug.log('admin', `✅ Loaded module: ${moduleName}`);
                    }
                    resolve(moduleName);
                };
                
                script.onerror = () => {
                    if (window.ExplainerDebug) {
                        window.ExplainerDebug.error('admin', `❌ Failed to load module: ${moduleName}`);
                    }
                    reject(new Error(`Failed to load ${moduleName}`));
                };
                
                document.head.appendChild(script);
            });
        },
        
        /**
         * Load all modules in sequence
         */
        loadAllModules: function() {
            if (window.ExplainerDebug) {
                window.ExplainerDebug.log('admin', '🚀 Loading WP AI Explainer admin modules...');
            }
            
            // Load modules sequentially to maintain dependency order
            return this.modules.reduce((promise, moduleName) => {
                return promise.then(() => this.loadModule(moduleName));
            }, Promise.resolve())
            .then(() => {
                if (window.ExplainerDebug) {
                    window.ExplainerDebug.log('admin', '✅ All admin modules loaded successfully');
                }
                this.initializeModules();
            })
            .catch((error) => {
                if (window.ExplainerDebug) {
                    window.ExplainerDebug.error('admin', '❌ Failed to load admin modules:', error);
                }
                // Continue with basic functionality
                this.initializeFallback();
            });
        },
        
        /**
         * Initialize all loaded modules
         */
        initializeModules: function() {
            // Core module handles initialization of all other modules
            if (window.WPAIExplainer?.Admin?.Core) {
                // Core will initialize itself and other modules
                if (window.ExplainerDebug) {
                    window.ExplainerDebug.log('admin', '🎯 Admin modules ready');
                }
            } else {
                if (window.ExplainerDebug) {
                    window.ExplainerDebug.warn('admin', '⚠️ Core module not available, using fallback');
                }
                this.initializeFallback();
            }
        },
        
        /**
         * Fallback initialization if modules fail to load
         */
        initializeFallback: function() {
            if (window.ExplainerDebug) {
                window.ExplainerDebug.log('admin', '🔄 Initializing fallback admin functionality');
            }
            
            // Basic form submission handler
            $('form').on('submit', function(e) {
                // Basic validation could go here
                return true;
            });
            
            // Basic API key testing
            $('.test-api-key, #test-api-key, #test-claude-api-key').on('click', function(e) {
                e.preventDefault();
                if (window.ExplainerPlugin?.replaceAlert) {
                    window.ExplainerPlugin.replaceAlert('Module system not loaded. Please refresh the page.', 'warning');
                } else {
                    alert('⚠️ Module system not loaded. Please refresh the page.');
                }
            });
        },
        
        /**
         * Get base path for module loading
         */
        getBasePath: function() {
            // Try to get path from WordPress localized script
            if (window.wpAiExplainer?.pluginUrl) {
                return window.wpAiExplainer.pluginUrl + '/assets/js';
            }
            
            if (window.explainerAdmin?.pluginUrl) {
                return window.explainerAdmin.pluginUrl + '/assets/js';
            }
            
            // Fallback - extract from current script
            const scripts = document.querySelectorAll('script[src*="admin"]');
            if (scripts.length > 0) {
                const src = scripts[scripts.length - 1].src;
                return src.substring(0, src.lastIndexOf('/'));
            }
            
            // Last resort fallback
            return '/wp-content/plugins/wp-ai-explainer/assets/js';
        }
    };

    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        if (window.ExplainerDebug) {
            window.ExplainerDebug.log('admin', '📄 WP AI Explainer Admin initializing...');
        }

        // Check if we're on an admin page that needs our modules
        if ($('.explainer-admin-wrap').length > 0 ||
            $('.explainer-admin-tabs').length > 0 ||
            $('input[name="explainer_openai_api_key"]').length > 0 ||
            $('#ai_provider').length > 0 ||
            $('h1:contains("WP AI Explainer Settings")').length > 0 ||
            window.location.href.includes('page=wp-ai-explainer-admin')) {

            if (window.ExplainerDebug) {
                window.ExplainerDebug.log('admin', '✅ WP AI Explainer admin page detected, loading modules...');
            }

            // Move WordPress update notices below the plugin header
            const $wrap = $('.explainer-admin-wrap');
            if ($wrap.length > 0) {
                const $header = $wrap.find('h1').first();
                if ($header.length > 0) {
                    // Find WordPress core notices that appear before our wrap
                    // This includes update notices, admin notices, and error messages
                    const noticeSelectors = [
                        '.update-nag',
                        '.notice.is-dismissible',
                        '.notice:not(.inline)',
                        '.error:not(.inline)',
                        '.updated:not(.inline)'
                    ];

                    // Collect all notices that are siblings before our wrap
                    const $notices = $(noticeSelectors.join(', ')).filter(function() {
                        const $notice = $(this);
                        // Only move if it's not inside our wrap and appears before it in the DOM
                        return !$notice.closest('.explainer-admin-wrap').length &&
                               $notice.parent().is($wrap.parent());
                    });

                    // Move all matching notices below the header
                    $notices.insertAfter($header);
                }
            }

            ModuleLoader.loadAllModules();
        } else {
            if (window.ExplainerDebug) {
                window.ExplainerDebug.log('admin', 'ℹ️ Not on WP AI Explainer admin page, skipping module loading');
            }
        }

        // Check if we need to scroll to API key field
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('scroll_to_api_key') === '1') {
            setTimeout(function() {
                const apiKeyField = document.querySelector('#explainer_openai_api_key, #explainer_claude_api_key');
                if (apiKeyField) {
                    apiKeyField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    apiKeyField.focus();

                    // Add a visual pulse effect
                    apiKeyField.style.transition = 'box-shadow 0.3s ease';
                    apiKeyField.style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.5)';
                    setTimeout(function() {
                        apiKeyField.style.boxShadow = '';
                    }, 2000);
                }
            }, 500);
        }
    });

    // Export for debugging
    window.WPAIExplainerModuleLoader = ModuleLoader;

})(jQuery);

/**
 * Global error handler for module loading issues
 */
window.addEventListener('error', function(e) {
    if (e.filename && e.filename.includes('wp-ai-explainer') && e.filename.includes('admin/')) {
        if (window.ExplainerDebug) {
            window.ExplainerDebug.error('admin', 'Module loading error:', {message: e.message, filename: e.filename});
        }
    }
});

/**
 * Debug info
 */
if (window.ExplainerDebug) {
    window.ExplainerDebug.log('admin', '🔧 WP AI Explainer Admin System loaded');
    window.ExplainerDebug.log('admin', '📊 Available globals:', {
        wpAiExplainer: typeof window.wpAiExplainer,
        explainerAdmin: typeof window.explainerAdmin,
        jQuery: typeof window.jQuery
    });
}