/**
 * WP AI Explainer - Onboarding Panel
 * First-time user onboarding with slide-up panel
 */

(function() {
    'use strict';

    /**
     * OnboardingManager - Main controller for the onboarding experience
     */
    class OnboardingManager {
        constructor() {
            this.panel = null;
            this.isVisible = false;
            this.shouldShow = false;
            
            // Demo state (simplified)
            this.demoState = {
                elements: {}
            };
            
            // Debug logging
            this.debug = window.location.search.includes('debug=onboarding');
            
            this.log('OnboardingManager initialized');
        }
        
        /**
         * Initialize the onboarding system
         */
        init() {
            this.log('Initializing onboarding...');
            
            // Check if onboarding is enabled by admin
            if (window.explainerAjax && window.explainerAjax.settings && !window.explainerAjax.settings.onboarding_enabled) {
                this.log('Onboarding disabled by admin setting');
                return;
            }
            
            // Check if we should show onboarding
            this.shouldShow = this.checkFirstTimeUser();
            this.log('Should show onboarding:', this.shouldShow);
            
            if (!this.shouldShow) {
                this.log('Not showing onboarding - user has seen it before');
                return;
            }
            
            this.log('Setting up onboarding for first-time user');
            this.applyAdminColors(); // Apply admin colors before creating panel
            this.createPanel();
            this.setupEventListeners();
            this.showWithDelay(2000); // 2 second delay after page load
        }
        
        /**
         * Apply admin-configured colors to onboarding elements
         */
        applyAdminColors() {
            // Get colors from explainerAjax settings (same as main plugin)
            if (window.explainerAjax && window.explainerAjax.settings) {
                const settings = window.explainerAjax.settings;
                
                // Extract colors with fallbacks
                const bgColor = settings.tooltip_bg_color || '#333333';
                const textColor = settings.tooltip_text_color || '#ffffff';
                const footerColor = settings.tooltip_footer_color || '#ffffff';
                const primaryColor = settings.button_enabled_color || window.wpAiExplainer?.brandColors?.buttonEnabled || '#8b5cf6';
                
                // Apply the same CSS custom properties as the main plugin
                document.documentElement.style.setProperty('--onboarding-bg', bgColor);
                document.documentElement.style.setProperty('--onboarding-text', textColor);
                document.documentElement.style.setProperty('--onboarding-accent', primaryColor);
                document.documentElement.style.setProperty('--onboarding-footer', footerColor);
                
                this.log('Applied admin colors to onboarding:', {
                    bg: bgColor,
                    text: textColor,
                    accent: primaryColor,
                    footer: footerColor
                });
            } else {
                this.log('explainerAjax settings not found, using CSS fallbacks');
            }
        }
        
        /**
         * Check if this is a first-time user
         * @returns {boolean}
         */
        checkFirstTimeUser() {
            try {
                const hasSeenOnboarding = localStorage.getItem('explainer_onboarding_shown');
                const firstVisit = localStorage.getItem('explainer_first_visit');
                
                // Set first visit timestamp if not already set
                if (!firstVisit) {
                    localStorage.setItem('explainer_first_visit', Date.now().toString());
                }
                
                this.log('LocalStorage check:', {
                    hasSeenOnboarding: !!hasSeenOnboarding,
                    firstVisit: firstVisit,
                    isFirstTime: !hasSeenOnboarding
                });
                
                return !hasSeenOnboarding;
            } catch (error) {
                this.log('LocalStorage not available, assuming first-time user:', error);
                return true; // Fallback to showing onboarding
            }
        }
        
        /**
         * Mark onboarding as shown
         */
        markOnboardingShown() {
            try {
                localStorage.setItem('explainer_onboarding_shown', 'true');
                localStorage.setItem('explainer_onboarding_completed', Date.now().toString());
                this.log('Onboarding marked as shown');
            } catch (error) {
                this.log('Could not save onboarding state:', error);
            }
        }
        
        /**
         * Mark tutorial as completed
         */
        markTutorialCompleted() {
            try {
                localStorage.setItem('explainer_tutorial_completed', 'true');
                localStorage.setItem('explainer_tutorial_completed_time', Date.now().toString());
                this.log('Tutorial marked as completed');
            } catch (error) {
                this.log('Could not save tutorial completion state:', error);
            }
        }
        
        /**
         * Create the onboarding panel HTML structure
         */
        createPanel() {
            this.log('Creating onboarding panel...');
            
            // Create panel container
            this.panel = document.createElement('div');
            this.panel.className = 'explainer-onboarding-panel';
            this.panel.setAttribute('role', 'dialog');
            this.panel.setAttribute('aria-labelledby', 'explainer-onboarding-title');
            this.panel.setAttribute('aria-describedby', 'explainer-onboarding-description');
            
            // Panel HTML structure
            this.panel.innerHTML = `
                <div class="explainer-onboarding-header">
                    <h3 id="explainer-onboarding-title">Welcome to AI Explainer!</h3>
                    <button class="btn-base btn-icon explainer-onboarding-close" aria-label="Close welcome panel" title="Close">×</button>
                </div>
                
                <div class="explainer-onboarding-content">
                    <p id="explainer-onboarding-description">
                        Transform your reading experience by selecting any text to get instant AI explanations and insights.
                    </p>
                    
                    <div class="explainer-onboarding-demo">
                        <div class="explainer-demo-content">
                            <div class="explainer-demo-instruction" id="demo-instruction">
                                Try selecting the text "artificial intelligence" below:
                            </div>
                            <div class="explainer-demo-text explainer-onboarding-selectable" id="demo-text">
                                The concept of <span class="explainer-demo-target explainer-onboarding-selectable" data-demo-text="artificial intelligence">artificial intelligence</span> represents a fascinating intersection of computer science and human cognition.
                            </div>
                        </div>
                        <div class="explainer-demo-controls">
                            <div class="explainer-demo-step" id="demo-step">Ready to try the real feature!</div>
                        </div>
                    </div>
                </div>
                
                <div class="explainer-onboarding-actions">
                    <button class="btn-base btn-link explainer-onboarding-try" type="button">Try It Now</button>
                    <button class="btn-base btn-link explainer-onboarding-skip" type="button">Skip Tutorial</button>
                </div>
            `;
            
            // Add panel to document
            document.body.appendChild(this.panel);
            this.log('Panel created and added to DOM');
            
            // Initialize demo elements
            this.initializeDemoElements();
        }
        
        /**
         * Initialize demo elements and cache references
         */
        initializeDemoElements() {
            if (!this.panel) return;
            
            this.demoState.elements = {
                instruction: this.panel.querySelector('#demo-instruction'),
                target: this.panel.querySelector('.explainer-demo-target'),
                demoText: this.panel.querySelector('#demo-text'),
                stepIndicator: this.panel.querySelector('#demo-step')
            };
            
            this.log('Demo elements initialized', this.demoState.elements);
        }
        
        /**
         * Setup event listeners for panel interactions
         */
        setupEventListeners() {
            if (!this.panel) return;
            
            // Close button
            const closeBtn = this.panel.querySelector('.explainer-onboarding-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.handleSkip();
                });
            }
            
            // Try It Now button
            const tryBtn = this.panel.querySelector('.explainer-onboarding-try');
            if (tryBtn) {
                tryBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.handleTryNow();
                });
            }
            
            // Skip Tutorial button
            const skipBtn = this.panel.querySelector('.explainer-onboarding-skip');
            if (skipBtn) {
                skipBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.handleSkip();
                });
            }
            
            // Backdrop click (optional)
            document.addEventListener('click', (e) => {
                if (this.isVisible && !this.panel.contains(e.target)) {
                    this.handleSkip();
                }
            });
            
            // Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isVisible) {
                    this.handleSkip();
                }
            });
            
            // No demo-specific event listeners needed - real plugin handles selection
            
            this.log('Event listeners setup complete');
        }
        
        
        /**
         * Show panel with delay
         * @param {number} delay - Delay in milliseconds
         */
        showWithDelay(delay = 2000) {
            setTimeout(() => {
                this.show();
            }, delay);
        }
        
        /**
         * Show the onboarding panel
         */
        show() {
            if (!this.panel || this.isVisible) return;
            
            this.log('Showing onboarding panel');
            this.panel.classList.add('visible');
            this.isVisible = true;
            
            // Focus management for accessibility
            this.panel.focus();
        }
        
        /**
         * Hide the onboarding panel
         */
        hide() {
            if (!this.panel || !this.isVisible) return;
            
            this.log('Hiding onboarding panel');
            this.panel.classList.remove('visible');
            this.isVisible = false;
            
            // Dispatch event to notify main explainer to close any open tooltips
            const event = new CustomEvent('onboardingPanelHidden', {
                detail: { source: 'onboarding' }
            });
            document.dispatchEvent(event);
            this.log('Dispatched onboardingPanelHidden event');
            
            // Clean up after animation
            setTimeout(() => {
                if (this.panel && this.panel.parentNode) {
                    this.panel.parentNode.removeChild(this.panel);
                }
            }, 400); // Match CSS animation duration
        }
        
        /**
         * Handle "Try It Now" button click
         */
        handleTryNow() {
            this.log('User clicked "Try It Now"');
            this.markTutorialCompleted();
            this.hide();
            
            // Highlight the toggle button if it exists
            this.highlightToggleButton();
        }
        
        /**
         * Handle "Skip Tutorial" or close button click
         */
        handleSkip() {
            this.log('User skipped tutorial');
            this.markOnboardingShown();
            this.hide();
        }
        
        /**
         * Highlight the toggle button with a pulse effect
         */
        highlightToggleButton() {
            const toggle = document.querySelector('.explainer-toggle');
            if (toggle) {
                toggle.style.animation = 'explainer-pulse 2s ease-in-out 3';
                this.log('Toggle button highlighted');
                
                // Remove animation after completion
                setTimeout(() => {
                    if (toggle) toggle.style.animation = '';
                }, 6000);
            }
        }
        
        
        /**
         * Debug logging utility
         * @param {...any} args 
         */
        log(...args) {
            if (this.debug) {
                console.log('[Explainer Onboarding]', ...args);
            }
        }
    }

    // Global namespace
    if (!window.ExplainerPlugin) {
        window.ExplainerPlugin = {};
    }
    
    // Export the OnboardingManager
    window.ExplainerPlugin.OnboardingManager = OnboardingManager;
    
    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            const onboarding = new OnboardingManager();
            onboarding.init();
        });
    } else {
        // DOM is already ready
        const onboarding = new OnboardingManager();
        onboarding.init();
    }

})();