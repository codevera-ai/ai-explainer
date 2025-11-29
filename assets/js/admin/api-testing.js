/**
 * API Testing Module
 * Handles API key testing functionality for all providers
 */

(function($) {
    'use strict';

    // Module namespace
    window.WPAIExplainer = window.WPAIExplainer || {};
    window.WPAIExplainer.Admin = window.WPAIExplainer.Admin || {};
    window.WPAIExplainer.Admin.ApiTesting = {

        /**
         * Initialize API testing functionality
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Modern unified test buttons
            $('.test-api-key').on('click', this.handleApiKeyTest.bind(this));
            
            // Test stored API key buttons
            $('.test-stored-api-key').on('click', this.handleStoredApiKeyTest.bind(this));
            
            // Legacy support for specific provider buttons
            $('#test-api-key, #test-claude-api-key').on('click', this.handleLegacyApiKeyTest.bind(this));
            
            // Dynamic provider-specific test buttons (test-{provider}-api-key)
            $('[id^="test-"][id$="-api-key"]').on('click', this.handleLegacyApiKeyTest.bind(this));
            
            // API key show/hide functionality
            $('[id^="toggle-"][id$="-key-visibility"]').on('click', this.handleApiKeyToggle.bind(this));
            
            // API key deletion
            $('.delete-api-key, .delete-api-key-btn').on('click', this.handleApiKeyDelete.bind(this));
        },

        /**
         * Handle modern API key testing (with provider config)
         */
        handleApiKeyTest: function(e) {
            e.preventDefault();
            
            const button = $(e.currentTarget);
            const provider = $('#explainer_api_provider').val();
            
            // Check if provider configuration is available
            if (typeof window.WPAIExplainer.Admin.ProviderConfig !== 'undefined') {
                const providerData = window.WPAIExplainer.Admin.ProviderConfig.getProviderData(provider);
                
                if (!providerData) {
                    this.showError('Provider configuration not loaded. Please refresh the page.');
                    return;
                }
                
                const apiKeyField = $(`#${providerData.apiKey}`);
                const apiKey = apiKeyField.val();

                if (!apiKey.trim()) {
                    this.showError('Please enter an API key first.');
                    return;
                }

                this.testApiKey(button, provider, apiKey, 'wp_ai_explainer_test_api_key');
            } else {
                // Fallback to legacy testing
                this.handleLegacyApiKeyTest(e);
            }
        },

        /**
         * Handle legacy API key testing (backward compatibility)
         */
        handleLegacyApiKeyTest: function(e) {
            e.preventDefault();
            
            const button = $(e.currentTarget);
            const buttonId = button.attr('id');
            
            // Determine provider from button ID
            let provider, apiKey;
            if (buttonId === 'test-claude-api-key') {
                provider = 'claude';
                apiKey = $('input[name="explainer_claude_api_key"]').val().trim();
            } else if (buttonId === 'test-api-key') {
                provider = 'openai';
                apiKey = $('input[name="explainer_openai_api_key"]').val().trim();
            } else if (buttonId && buttonId.startsWith('test-') && buttonId.endsWith('-api-key')) {
                // Extract provider from button ID pattern: test-{provider}-api-key
                provider = buttonId.replace('test-', '').replace('-api-key', '');
                
                // Get API key input based on provider (use consistent naming)
                apiKey = $(`input[name="explainer_${provider}_api_key"]`).val();
                if (apiKey) {
                    apiKey = apiKey.trim();
                }
            } else {
                // Fallback to dropdown selection
                provider = $('#explainer_api_provider').val();
                if (provider) {
                    apiKey = $(`input[name="explainer_${provider}_api_key"]`).val();
                    if (apiKey) {
                        apiKey = apiKey.trim();
                    }
                }
            }

            if (!apiKey) {
                this.showError('Please enter an API key first.');
                return;
            }

            this.testApiKey(button, provider, apiKey, 'explainer_test_api_key');
        },

        /**
         * Perform API key test via AJAX
         */
        testApiKey: function(button, provider, apiKey, action) {
            const originalText = button.text();
            
            // Show loading state
            button.text('Testing...').prop('disabled', true);

            // Get AJAX URL and nonce based on available globals
            const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.explainerAdmin?.ajaxurl || window.ajaxurl;
            const nonce = window.wpAiExplainer?.nonce || window.explainerAdmin?.nonce;

            if (!ajaxUrl || !nonce) {
                this.showError('AJAX configuration not found.');
                button.text(originalText).prop('disabled', false);
                return;
            }

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: action,
                    provider: provider,
                    api_key: apiKey,
                    nonce: nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        
                        // Update status display if available
                        const statusDiv = button.siblings('.api-key-status');
                        if (statusDiv.length) {
                            this.showApiKeyMessage(statusDiv, response.data.message, 'success');
                        }
                    } else {
                        const errorMsg = response.data ? response.data.message : 'Test failed';
                        this.showError(errorMsg);
                        
                        // Update status display if available
                        const statusDiv = button.siblings('.api-key-status');
                        if (statusDiv.length) {
                            this.showApiKeyMessage(statusDiv, errorMsg, 'error');
                        }
                    }
                },
                error: (xhr, status, error) => {
                    console.error('API key test error:', { status, error, responseText: xhr.responseText });
                    this.showError('Connection failed. Please try again.');
                },
                complete: () => {
                    button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Handle testing stored API key
         */
        handleStoredApiKeyTest: function(e) {
            e.preventDefault();
            
            const button = $(e.currentTarget);
            const provider = button.data('provider');
            
            if (!provider) {
                this.showError('Provider not specified.');
                return;
            }

            this.testStoredApiKey(button, provider);
        },

        /**
         * Perform stored API key test via AJAX
         */
        testStoredApiKey: function(button, provider) {
            const originalText = button.text();
            
            // Show loading state
            button.text('Testing...').prop('disabled', true);

            // Get AJAX URL and nonce based on available globals
            const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.explainerAdmin?.ajaxurl || window.ajaxurl;
            const nonce = window.wpAiExplainer?.nonce || window.explainerAdmin?.nonce;

            if (!ajaxUrl || !nonce) {
                this.showError('AJAX configuration not found.');
                button.text(originalText).prop('disabled', false);
                return;
            }

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_ai_explainer_test_stored_api_key',
                    provider: provider,
                    nonce: nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message || 'Stored API key is working correctly!');
                        
                        // Update status display if available
                        const statusDiv = button.closest('.api-key-status');
                        if (statusDiv.length) {
                            this.showApiKeyMessage(statusDiv, response.data.message, 'success');
                        }
                    } else {
                        const errorMsg = response.data?.message || 'Stored API key test failed.';
                        this.showError(errorMsg);
                        
                        // Update status display if available
                        const statusDiv = button.closest('.api-key-status');
                        if (statusDiv.length) {
                            this.showApiKeyMessage(statusDiv, errorMsg, 'error');
                        }
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Stored API key test error:', { status, error, responseText: xhr.responseText });
                    this.showError('Connection failed. Please try again.');
                },
                complete: () => {
                    button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Handle API key show/hide toggle
         */
        handleApiKeyToggle: function(e) {
            e.preventDefault();
            
            const button = $(e.currentTarget);
            const input = button.siblings('input[type="password"], input[type="text"]');
            
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                button.text('Hide');
            } else {
                input.attr('type', 'password');
                button.text('Show');
            }
        },

        /**
         * Handle API key deletion
         */
        handleApiKeyDelete: function(e) {
            e.preventDefault();
            
            const performDelete = () => {
                const button = $(e.currentTarget);
                const provider = $('#explainer_api_provider').val();
                
                // Check if provider configuration is available
                let providerData;
                if (typeof window.WPAIExplainer.Admin.ProviderConfig !== 'undefined') {
                    providerData = window.WPAIExplainer.Admin.ProviderConfig.getProviderData(provider);
                    
                    if (!providerData) {
                        this.showError('Provider configuration not loaded. Please refresh the page.');
                        return;
                    }
                }

                // Get AJAX URL and nonce
                const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
                const nonce = window.wpAiExplainer?.nonce || window.explainerAdmin?.nonce;

                if (!ajaxUrl || !nonce) {
                    this.showError('AJAX configuration not found.');
                    return;
                }

                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wp_ai_explainer_delete_api_key',
                        provider: provider,
                        nonce: nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            // Clear the input field
                            if (providerData) {
                                const apiKeyField = $(`#${providerData.apiKey}`);
                                apiKeyField.val('');
                            }
                            
                            // Hide the delete button and configured status
                            button.closest('.api-key-status').hide();
                            
                            this.showSuccess('API key deleted successfully.');
                        } else {
                            const errorMsg = response.data ? response.data.message : 'Unknown error';
                            this.showError('Failed to delete API key: ' + errorMsg);
                        }
                    },
                    error: () => {
                        this.showError('Connection failed. Please try again.');
                    }
                });
            };
            
            if (window.ExplainerPlugin?.replaceConfirm) {
                window.ExplainerPlugin.replaceConfirm(
                    'Are you sure you want to delete this API key? This action cannot be undone.',
                    {
                        confirmText: 'Delete API Key',
                        cancelText: 'Cancel',
                        type: 'error'
                    }
                ).then((confirmed) => {
                    if (confirmed) {
                        performDelete();
                    }
                });
            } else {
                if (confirm('Are you sure you want to delete this API key? This action cannot be undone.')) {
                    performDelete();
                }
            }
        },

        /**
         * Show API key status message (legacy support)
         */
        showApiKeyMessage: function(container, message, type) {
            container.removeClass('success error').addClass(type);
            container.find('.message').text(message);
            container.show();
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            if (window.ExplainerPlugin?.Notifications) {
                window.ExplainerPlugin.Notifications.success(message);
            } else {
                console.log(`[SUCCESS] ${message}`);
            }
        },

        /**
         * Show error message
         */
        showError: function(message) {
            if (window.ExplainerPlugin?.Notifications) {
                window.ExplainerPlugin.Notifications.error(message);
            } else {
                console.log(`[ERROR] ${message}`);
            }
        }
    };

})(jQuery);