/**
 * Form Validation Module
 * Handles all form validation functionality
 */

(function($) {
    'use strict';

    // Module namespace
    window.WPAIExplainer = window.WPAIExplainer || {};
    window.WPAIExplainer.Admin = window.WPAIExplainer.Admin || {};
    window.WPAIExplainer.Admin.FormValidation = {

        /**
         * Initialize form validation functionality
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Form submission validation
            $('form').on('submit', this.validateForm.bind(this));
            
            // Real-time validation for numeric fields
            $('input[type="number"]:not([name="explainer_cache_duration"]):not([name="explainer_rate_limit_logged"]):not([name="explainer_rate_limit_anonymous"])').on('input', this.validateNumericField.bind(this));
            
            // Specific field validations
            $('input[name="explainer_cache_duration"]').on('input', this.validateCacheDuration.bind(this));
            $('input[name="explainer_rate_limit_logged"], input[name="explainer_rate_limit_anonymous"]').on('input', this.validateRateLimit.bind(this));
            $('textarea[name="explainer_custom_prompt"]').on('input', this.validateCustomPrompt.bind(this));
            
            // CSS selector validation
            $('textarea[name="explainer_included_selectors"], textarea[name="explainer_excluded_selectors"]').on('input', this.validateCSSSelectors.bind(this));
            
            // API key validation
            $('input[name="explainer_openai_api_key"]').on('input', this.handleApiKeyInput.bind(this));
            $('input[name="explainer_claude_api_key"]').on('input', this.handleClaudeApiKeyInput.bind(this));
        },

        /**
         * Validate form before submission
         */
        validateForm: function(e) {
            const errors = [];
            
            // Validate API keys based on provider
            const provider = $('#explainer_api_provider').val();
            
            if (provider === 'openai') {
                const apiKey = $('input[name="explainer_openai_api_key"]').val();
                if (apiKey && !this.isValidOpenAIApiKey(apiKey)) {
                    errors.push('Invalid OpenAI API key format. Keys should start with "sk-" and contain only alphanumeric characters, hyphens, underscores, and dots.');
                }
            } else if (provider === 'claude') {
                const claudeKey = $('input[name="explainer_claude_api_key"]').val();
                if (claudeKey && !this.isValidClaudeApiKey(claudeKey)) {
                    errors.push('Invalid Claude API key format. Keys should start with "sk-ant-" and contain only alphanumeric characters, hyphens, and underscores.');
                }
            }
            
            // Validate cache duration
            const cacheDuration = parseInt($('input[name="explainer_cache_duration"]').val());
            if (cacheDuration < 1 || cacheDuration > 168) {
                errors.push('Cache duration must be between 1 and 168 hours.');
            }
            
            // Validate rate limits
            const rateLimitLogged = parseInt($('input[name="explainer_rate_limit_logged"]').val());
            const rateLimitAnon = parseInt($('input[name="explainer_rate_limit_anonymous"]').val());
            
            if (rateLimitLogged < 1 || rateLimitLogged > 100) {
                errors.push('Rate limit for logged-in users must be between 1 and 100 requests per hour.');
            }
            
            if (rateLimitAnon < 1 || rateLimitAnon > 50) {
                errors.push('Rate limit for anonymous users must be between 1 and 50 requests per hour.');
            }
            
            // Validate custom prompt
            const customPrompt = $('textarea[name="explainer_custom_prompt"]').val();
            if (customPrompt && !customPrompt.includes('{{selectedtext}}')) {
                errors.push('Custom prompt must include the {{selectedtext}} placeholder.');
            }
            
            // Show errors if any
            if (errors.length > 0) {
                e.preventDefault();
                const errorMessage = 'Please fix the following errors:\n\n• ' + errors.join('\n• ');
                if (window.ExplainerPlugin?.replaceAlert) {
                    window.ExplainerPlugin.replaceAlert(errorMessage, 'error');
                } else {
                    alert(errorMessage);
                }
                return false;
            }
            
            return true;
        },

        /**
         * Validate numeric field
         */
        validateNumericField: function(e) {
            const field = $(e.currentTarget);
            const value = parseInt(field.val());
            const min = parseInt(field.attr('min'));
            const max = parseInt(field.attr('max'));
            const fieldName = field.attr('name');
            
            this.removeFeedback(field, 'field-feedback');
            
            if (isNaN(value) || value < min || value > max) {
                this.showFieldFeedback(field, `Must be between ${min} and ${max}`, 'error', 'field-feedback');
            } else {
                this.showFieldFeedback(field, '', 'success', 'field-feedback');
            }
        },

        /**
         * Validate cache duration
         */
        validateCacheDuration: function(e) {
            const field = $(e.currentTarget);
            const duration = parseInt(field.val());
            const parentLabel = field.closest('label');
            
            this.removeFeedback(parentLabel, 'duration-feedback');
            
            if (isNaN(duration) || duration < 1 || duration > 168) {
                const feedback = $('<div class="duration-feedback error"></div>');
                feedback.html('<span class="invalid">Must be between 1 and 168 hours</span>');
                parentLabel.after(feedback);
            }
        },

        /**
         * Validate rate limit
         */
        validateRateLimit: function(e) {
            const field = $(e.currentTarget);
            const value = parseInt(field.val());
            const isLogged = field.attr('name') === 'explainer_rate_limit_logged';
            const max = isLogged ? 100 : 50;
            const parentLabel = field.closest('label');
            
            this.removeFeedback(parentLabel, 'rate-limit-feedback');
            
            if (isNaN(value) || value < 1 || value > max) {
                const feedback = $('<div class="rate-limit-feedback error"></div>');
                feedback.html(`<span class="invalid">Must be between 1 and ${max}</span>`);
                parentLabel.after(feedback);
            }
        },

        /**
         * Validate custom prompt
         */
        validateCustomPrompt: function(e) {
            const field = $(e.currentTarget);
            const prompt = field.val();
            const errors = [];
            
            this.removeFeedback(field, 'field-feedback');
            
            // Check for {{selectedtext}} placeholder
            if (prompt && !prompt.includes('{{selectedtext}}')) {
                errors.push('Must include {{selectedtext}} placeholder');
            }
            
            // Length check removed - no character limit
            
            if (errors.length > 0) {
                this.showFieldFeedback(field, errors.join(', '), 'error', 'field-feedback');
            } else {
                this.showFieldFeedback(field, '', 'success', 'field-feedback');
            }
        },

        /**
         * Validate CSS selectors
         */
        validateCSSSelectors: function(e) {
            const field = $(e.currentTarget);
            const value = field.val();
            
            this.removeFeedback(field, 'field-feedback');
            
            if (this.isValidCSSSelectors(value)) {
                this.showFieldFeedback(field, '', 'success', 'field-feedback');
            } else {
                this.showFieldFeedback(field, 'Invalid CSS selector format', 'error', 'field-feedback');
            }
        },

        /**
         * Handle OpenAI API key input
         */
        handleApiKeyInput: function(e) {
            const field = $(e.currentTarget);
            const apiKey = field.val();
            const fieldName = field.attr('name');
            
            this.removeFeedback(field, 'api-key-feedback');
            
            if (!apiKey.trim()) {
                return;
            }
            
            if (this.isValidOpenAIApiKey(apiKey)) {
                this.showFieldFeedback(field, '', 'success', 'api-key-feedback');
            } else {
                this.showFieldFeedback(field, '✗ Invalid OpenAI API key format', 'error', 'api-key-feedback');
            }
        },

        /**
         * Handle Claude API key input
         */
        handleClaudeApiKeyInput: function(e) {
            const field = $(e.currentTarget);
            const apiKey = field.val();
            const fieldName = field.attr('name');
            
            this.removeFeedback(field, 'api-key-feedback');
            
            if (!apiKey.trim()) {
                return;
            }
            
            if (this.isValidClaudeApiKey(apiKey)) {
                this.showFieldFeedback(field, '', 'success', 'api-key-feedback');
            } else {
                this.showFieldFeedback(field, '✗ Invalid Claude API key format', 'error', 'api-key-feedback');
            }
        },

        /**
         * Check if OpenAI API key has valid format
         */
        isValidOpenAIApiKey: function(apiKey) {
            if (!apiKey || typeof apiKey !== 'string') {
                return false;
            }
            
            apiKey = apiKey.trim();
            
            // OpenAI API keys start with 'sk-'
            if (!apiKey.startsWith('sk-')) {
                return false;
            }
            
            // Check minimum length
            if (apiKey.length < 20) {
                return false;
            }
            
            // Check allowed characters (alphanumeric, hyphens, underscores, dots)
            const allowedPattern = /^sk-[a-zA-Z0-9\-_.]+$/;
            return allowedPattern.test(apiKey);
        },

        /**
         * Check if Claude API key has valid format
         */
        isValidClaudeApiKey: function(apiKey) {
            if (!apiKey || typeof apiKey !== 'string') {
                return false;
            }
            
            apiKey = apiKey.trim();
            
            // Claude API keys start with 'sk-ant-'
            if (!apiKey.startsWith('sk-ant-')) {
                return false;
            }
            
            // Check minimum length
            if (apiKey.length < 20) {
                return false;
            }
            
            // Check allowed characters (alphanumeric, hyphens, underscores)
            const allowedPattern = /^sk-ant-[a-zA-Z0-9\-_]+$/;
            return allowedPattern.test(apiKey);
        },

        /**
         * Validate CSS selectors
         */
        isValidCSSSelectors: function(selectors) {
            if (!selectors.trim()) {
                return true; // Empty is valid
            }
            
            try {
                const selectorList = selectors.split(',');
                for (let i = 0; i < selectorList.length; i++) {
                    const selector = selectorList[i].trim();
                    if (selector) {
                        // Try to create a CSS rule to validate selector
                        document.querySelector(selector);
                    }
                }
                return true;
            } catch (e) {
                return false;
            }
        },

        /**
         * Show field feedback
         */
        showFieldFeedback: function(field, message, type, className) {
            const fieldName = field.attr('name');
            let feedback = field.siblings(`.${className}[data-field="${fieldName}"]`);
            
            if (feedback.length === 0) {
                feedback = $(`<div class="${className}" data-field="${fieldName}"></div>`);
                field.after(feedback);
            }
            
            if (message) {
                feedback.html(`<span class="invalid">${message}</span>`)
                    .removeClass('success').addClass('error');
                field.addClass('error');
            } else {
                feedback.html('').removeClass('error').addClass('success');
                field.removeClass('error');
            }
        },

        /**
         * Remove feedback elements
         */
        removeFeedback: function(element, className) {
            element.siblings(`.${className}`).remove();
        }
    };

})(jQuery);