/**
 * AI Terms Modal Integration Helper
 * Simple integration functions for opening the modal from anywhere in the admin
 */

(function() {
    'use strict';
    
    // Ensure ExplainerPlugin namespace exists
    window.ExplainerPlugin = window.ExplainerPlugin || {};
    
    /**
     * Integration helper class
     */
    class AITermsIntegration {
        
        constructor() {
            this.bindGlobalEvents();
        }
        
        /**
         * Bind global events for opening the modal
         */
        bindGlobalEvents() {
            // Listen for clicks on elements with data-explainer-open-terms-modal
            document.addEventListener('click', (e) => {
                if (e.target.matches('[data-explainer-open-terms-modal]') || 
                    e.target.closest('[data-explainer-open-terms-modal]')) {
                    e.preventDefault();
                    
                    // Get the clicked element (or closest parent with the attribute)
                    const button = e.target.matches('[data-explainer-open-terms-modal]') 
                        ? e.target 
                        : e.target.closest('[data-explainer-open-terms-modal]');
                    
                    // Extract post ID from data attribute
                    const postId = button.getAttribute('data-post-id');
                    
                    this.openTermsModal(postId);
                }
            });
            
            // Listen for custom event to open modal
            document.addEventListener('explainer:openTermsModal', (e) => {
                const postId = e.detail?.postId;
                this.openTermsModal(postId);
            });
        }
        
        /**
         * Open the AI Terms modal
         * @param {string|number} postId - The post ID to load terms for
         */
        openTermsModal(postId = null) {
            if (window.ExplainerPlugin && window.ExplainerPlugin.aiTermsModal) {
                window.ExplainerPlugin.aiTermsModal.open(postId);
            } else {
                // Wait for modal to be initialized
                const checkModal = () => {
                    if (window.ExplainerPlugin && window.ExplainerPlugin.aiTermsModal) {
                        window.ExplainerPlugin.aiTermsModal.open(postId);
                    } else {
                        setTimeout(checkModal, 100);
                    }
                };
                checkModal();
            }
        }
        
        /**
         * Create a button that opens the terms modal
         * 
         * @param {Object} options Button configuration
         * @returns {HTMLElement} Button element
         */
        createModalButton(options = {}) {
            const defaults = {
                text: 'Manage AI Terms',
                className: 'btn-base btn-primary',
                icon: null,
                style: {}
            };
            
            const config = Object.assign({}, defaults, options);
            
            const button = document.createElement('button');
            button.type = 'button';
            button.className = config.className;
            button.setAttribute('data-explainer-open-terms-modal', 'true');
            
            // Add icon if provided
            if (config.icon) {
                const icon = document.createElement('span');
                icon.innerHTML = config.icon;
                icon.className = 'btn-icon-size';
                button.appendChild(icon);
            }
            
            // Add text
            const text = document.createElement('span');
            text.textContent = config.text;
            button.appendChild(text);
            
            // Apply custom styles
            Object.assign(button.style, config.style);
            
            return button;
        }
    }
    
    // Export to global namespace
    window.ExplainerPlugin.AITermsIntegration = AITermsIntegration;
    
    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.ExplainerPlugin.aiTermsIntegration = new AITermsIntegration();
        });
    } else {
        window.ExplainerPlugin.aiTermsIntegration = new AITermsIntegration();
    }
    
})();

/**
 * Global helper functions for easy integration
 */

// Simple function to open the modal
window.openExplainerTermsModal = function() {
    if (window.ExplainerPlugin && window.ExplainerPlugin.aiTermsIntegration) {
        window.ExplainerPlugin.aiTermsIntegration.openTermsModal();
    }
};

// Function to create a modal button and append it to an element
window.addExplainerTermsButton = function(targetSelector, buttonConfig = {}) {
    const target = document.querySelector(targetSelector);
    if (!target) {
        console.warn('Target element not found:', targetSelector);
        return null;
    }
    
    const integration = window.ExplainerPlugin?.aiTermsIntegration;
    if (!integration) {
        console.warn('AI Terms Integration not available');
        return null;
    }
    
    const button = integration.createModalButton(buttonConfig);
    target.appendChild(button);
    
    return button;
};