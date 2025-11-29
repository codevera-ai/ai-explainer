/**
 * Provider Configuration Module
 * Handles AI provider configuration loading and switching
 */

(function($) {
    'use strict';

    // Module namespace
    window.WPAIExplainer = window.WPAIExplainer || {};
    window.WPAIExplainer.Admin = window.WPAIExplainer.Admin || {};
    window.WPAIExplainer.Admin.ProviderConfig = {

        // Provider configuration data
        providerConfig: {},

        /**
         * Initialize provider configuration functionality
         */
        init: function() {
            return this.loadProviderConfiguration().always(() => {
                this.bindEvents();
                this.handleProviderChange(); // Trigger initial setup
            });
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Use the correct element ID from the HTML
            const providerSelect = $('#explainer_api_provider');
            if (providerSelect.length) {
                providerSelect.on('change', this.handleProviderChange.bind(this));
            }
        },

        /**
         * Load provider configuration from the server
         */
        loadProviderConfiguration: function() {
            console.log('Loading provider configuration...');
            
            const ajaxUrl = window.wpAiExplainer?.ajaxUrl || window.ajaxurl;
            const nonce = window.wpAiExplainer?.nonce;

            if (!ajaxUrl || !nonce) {
                console.error('AJAX configuration not found for provider config loading');
                return $.Deferred().reject();
            }

            return $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_ai_explainer_get_provider_config',
                    nonce: nonce
                },
                success: (response) => {
                    console.log('AJAX response received:', response);
                    if (response.success && response.data) {
                        this.providerConfig = response.data;
                        console.log('Provider configuration loaded successfully:', this.providerConfig);
                    } else {
                        console.error('Failed to load provider configuration:', response);
                        this.showWarning('Could not load provider configuration. Some features may not work correctly.');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX error loading provider configuration:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    this.showError('Could not load provider configuration. Please refresh the page.');
                }
            });
        },

        /**
         * Handle provider change - show/hide relevant fields
         */
        handleProviderChange: function() {
            const selectedProvider = $('#explainer_api_provider').val();
            console.log('Provider changed to:', selectedProvider);

            // Hide all API key rows using CSS class selectors that match the actual HTML
            $('.api-key-row').hide();

            // Show the specific provider's API key row
            $(`.${selectedProvider}-fields`).show();

            // Set the model to the provider's default model
            const providerData = this.providerConfig[selectedProvider];
            if (providerData && providerData.default_model) {
                // Create a hidden field to store the model value if it doesn't exist
                let modelField = $('#explainer_api_model');
                if (modelField.length === 0) {
                    // Create hidden input for model
                    modelField = $('<input type="hidden" name="explainer_api_model" id="explainer_api_model">');
                    $('form').append(modelField);
                }
                modelField.val(providerData.default_model);
                console.log('Set model to:', providerData.default_model);
            }

            // Trigger provider change event for other modules
            $(document).trigger('wpai:providerChanged', [selectedProvider, this.providerConfig[selectedProvider]]);
        },

        /**
         * Update the model dropdown based on selected provider
         */
        updateModelDropdown: function(providerKey, models) {
            const modelSelect = $('#explainer_api_model');
            if (!modelSelect.length || !models) {
                return;
            }

            // Store current selection
            const currentModel = modelSelect.val();

            // Clear existing options
            modelSelect.empty();

            // Add new options using models object (key -> label)
            Object.keys(models).forEach((modelKey) => {
                const label = models[modelKey];
                const option = new Option(label, modelKey);
                
                // Restore selection if it exists in new provider
                if (modelKey === currentModel) {
                    option.selected = true;
                }
                
                modelSelect.append(option);
            });

            // If no selection was restored, select the first option
            if (!modelSelect.val() && Object.keys(models).length > 0) {
                modelSelect.val(Object.keys(models)[0]);
            }

            // Trigger change event
            modelSelect.trigger('change');
        },

        /**
         * Fallback method to update model dropdown using data attributes
         */
        updateModelDropdownFromDataAttributes: function(providerKey) {
            const modelSelect = $('#explainer_api_model');
            if (!modelSelect.length) {
                return;
            }

            const dataAttr = `data-${providerKey}-models`;
            const modelsData = modelSelect.attr(dataAttr);
            
            if (modelsData) {
                try {
                    const models = JSON.parse(modelsData);
                    const modelOptions = {};
                    models.forEach(model => {
                        modelOptions[model.value] = model.label;
                    });
                    this.updateModelDropdown(providerKey, modelOptions);
                } catch (e) {
                    console.error('Error parsing model data for provider:', providerKey, e);
                }
            }
        },

        /**
         * Get provider data for a specific provider
         */
        getProviderData: function(providerKey) {
            return this.providerConfig[providerKey] || null;
        },

        /**
         * Get all available providers
         */
        getAvailableProviders: function() {
            return Object.keys(this.providerConfig);
        },

        /**
         * Check if provider configuration is loaded
         */
        isConfigLoaded: function() {
            return Object.keys(this.providerConfig).length > 0;
        },

        /**
         * Get current selected provider
         */
        getCurrentProvider: function() {
            const selectedProvider = $('#explainer_api_provider').val();
            return this.getProviderData(selectedProvider);
        },

        /**
         * Get models for a specific provider
         */
        getProviderModels: function(providerKey) {
            const provider = this.getProviderData(providerKey);
            return provider ? provider.models : {};
        },

        /**
         * Show warning message
         */
        showWarning: function(message) {
            console.warn(message);
            if (window.ExplainerPlugin?.Notifications) {
                window.ExplainerPlugin.Notifications.warning(message);
            } else {
                console.log(`[WARNING] ${message}`);
            }
        },

        /**
         * Show error message
         */
        showError: function(message) {
            console.error(message);
            if (window.ExplainerPlugin?.Notifications) {
                window.ExplainerPlugin.Notifications.error('Error: ' + message);
            } else {
                console.log(`[ERROR] Error: ${message}`);
            }
        }
    };

})(jQuery);
