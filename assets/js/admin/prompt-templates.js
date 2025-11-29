/**
 * Prompt Templates Module
 * Handles custom prompt template functionality
 */

(function($) {
    'use strict';

    // Module namespace
    window.WPAIExplainer = window.WPAIExplainer || {};
    window.WPAIExplainer.Admin = window.WPAIExplainer.Admin || {};
    window.WPAIExplainer.Admin.PromptTemplates = {

        /**
         * Initialize prompt templates functionality
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            $('#reset-prompt-default').on('click', this.resetPromptToDefault.bind(this));
            $('textarea[name="explainer_custom_prompt"]').on('input', this.validatePrompt.bind(this));
        },

        /**
         * Reset prompt to default
         */
        resetPromptToDefault: function(e) {
            e.preventDefault();
            
            const performReset = () => {
                const defaultPrompt = (typeof wpAiExplainer !== 'undefined' && wpAiExplainer.defaultPrompt) || 'Please provide a clear, concise explanation of the following text in 1-2 sentences: {{selectedtext}}';
                const promptField = $('textarea[name="explainer_custom_prompt"]');
                
                promptField.val(defaultPrompt);
                
                // Trigger validation
                this.validatePrompt({ currentTarget: promptField[0] });
                
                this.showMessage('Prompt reset to default. Remember to save your settings.', 'success');
            };
            
            if (window.ExplainerPlugin?.replaceConfirm) {
                window.ExplainerPlugin.replaceConfirm(
                    'Reset the prompt template to default? Your custom prompt will be lost.',
                    {
                        confirmText: 'Reset to Default',
                        cancelText: 'Cancel',
                        type: 'warning'
                    }
                ).then((confirmed) => {
                    if (confirmed) {
                        performReset();
                    }
                });
            } else {
                if (confirm('Reset the prompt template to default? Your custom prompt will be lost.')) {
                    performReset();
                }
            }
        },

        /**
         * Validate custom prompt
         */
        validatePrompt: function(e) {
            const field = $(e.currentTarget);
            const prompt = field.val();
            const errors = [];
            
            this.removeFeedback(field);
            
            // Check for {{selectedtext}} placeholder
            if (prompt && !prompt.includes('{{selectedtext}}')) {
                errors.push('Must include {{selectedtext}} placeholder');
            }
            
            // Length check removed - no character limit
            
            // Check for potential security issues
            if (this.containsUnsafeContent(prompt)) {
                errors.push('Contains potentially unsafe content');
            }
            
            if (errors.length > 0) {
                this.showFieldFeedback(field, errors.join(', '), 'error');
            } else {
                this.showFieldFeedback(field, '', 'success');
            }
        },

        /**
         * Check if prompt contains unsafe content
         */
        containsUnsafeContent: function(prompt) {
            const unsafePatterns = [
                /<script/i,
                /javascript:/i,
                /on\w+\s*=/i,
                /<iframe/i,
                /<object/i,
                /<embed/i
            ];
            
            return unsafePatterns.some(pattern => pattern.test(prompt));
        },

        /**
         * Show field feedback
         */
        showFieldFeedback: function(field, message, type) {
            const fieldName = field.attr('name');
            let feedback = field.siblings(`.field-feedback[data-field="${fieldName}"]`);
            
            if (feedback.length === 0) {
                feedback = $(`<div class="field-feedback" data-field="${fieldName}"></div>`);
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
        removeFeedback: function(field) {
            field.siblings('.field-feedback').remove();
        },

        /**
         * Show message
         */
        showMessage: function(message, type) {
            if (window.ExplainerPlugin?.Notifications) {
                switch (type) {
                    case 'success':
                        window.ExplainerPlugin.Notifications.success(message);
                        break;
                    case 'warning':
                        window.ExplainerPlugin.Notifications.warning(message);
                        break;
                    case 'info':
                        window.ExplainerPlugin.Notifications.info(message);
                        break;
                    case 'error':
                    default:
                        window.ExplainerPlugin.Notifications.error(message);
                }
            } else {
                console.log(`[${type.toUpperCase()}] ${message}`);
            }
        }
    };

})(jQuery);