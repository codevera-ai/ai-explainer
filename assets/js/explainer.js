/**
 * WP AI Explainer - Main JavaScript
 * Handles text selection, validation, and UI interactions
 */

(function() {
    'use strict';
    
    // Namespace for the plugin
    window.ExplainerPlugin = window.ExplainerPlugin || {};
    
    // Plugin configuration
    const config = {
        minSelectionLength: 3,
        maxSelectionLength: 200,
        minWords: 1,
        maxWords: 30,
        debounceDelay: 300,
        contextLength: 50,
        enabled: true, // Start enabled by default
        includedSelectors: '', // Will be loaded from settings
        excludedSelectors: '', // Will be loaded from settings
        throttleDelay: 100,
        maxConcurrentRequests: 1,
        debugMode: false, // Will be loaded from settings
        mobileSelectionDelay: 1000, // Delay for mobile devices to allow selection adjustment
        // Stop word filtering configuration
        enableStopWordFiltering: true, // Will be loaded from settings
        stopWords: ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'can', 'must', 'shall', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them', 'my', 'your', 'his', 'our', 'their', 'mine', 'yours', 'his', 'hers', 'ours', 'theirs']
    };
    
    // Plugin state
    const state = {
        currentSelection: null,
        selectionPosition: null,
        selectionContext: null,
        isProcessing: false,
        lastSelection: null,
        activeRequests: 0,
        requestQueue: [],
        debounceTimer: null,
        throttleTimer: null,
        mobileDelayTimer: null,
        cache: new Map(),
        observers: [],
        localisedStrings: null
    };
    
    // DOM elements
    const elements = {
        toggleContainer: null,
        toggleButton: null,
        tooltip: null,
        helpIcon: null
    };
    
    // Debug logging using central logger
    const logger = {
        debug: (message, context = {}) => {
            if (window.ExplainerLogger) {
                window.ExplainerLogger.debug(message, context, 'Explainer');
            }
        },
        info: (message, context = {}) => {
            if (window.ExplainerLogger) {
                window.ExplainerLogger.info(message, context, 'Explainer');
            }
        },
        warn: (message, context = {}) => {
            if (window.ExplainerLogger) {
                window.ExplainerLogger.warning(message, context, 'Explainer');
            }
        },
        error: (message, context = {}) => {
            if (window.ExplainerLogger) {
                window.ExplainerLogger.error(message, context, 'Explainer');
            }
        },
        user: (message, context = {}) => {
            if (window.ExplainerLogger) {
                window.ExplainerLogger.user(message, context, 'Explainer');
            }
        }
    };
    
    // Performance utilities using shared implementations
    const utils = {
        // Safari-compatible AJAX helper
        ajax: function(url, options) {
            return new Promise(function(resolve, reject) {
                const xhr = new XMLHttpRequest();
                xhr.open(options.method || 'GET', url, true);
                
                if (options.headers) {
                    for (const key in options.headers) {
                        if (options.headers.hasOwnProperty(key)) {
                            xhr.setRequestHeader(key, options.headers[key]);
                        }
                    }
                }
                
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            try {
                                const data = JSON.parse(xhr.responseText);
                                resolve(data);
                            } catch (e) {
                                resolve(xhr.responseText);
                            }
                        } else {
                            reject(new Error('HTTP ' + xhr.status + ': ' + xhr.statusText));
                        }
                    }
                };
                
                xhr.onerror = function() {
                    reject(new Error('Network error'));
                };
                
                xhr.send(options.body || null);
            });
        },
        
        debounce: function(func, delay) {
            if (window.ExplainerPlugin && window.ExplainerPlugin.Utils) {
                return window.ExplainerPlugin.Utils.debounce(func, delay);
            }
            // Fallback implementation
            return function(...args) {
                if (state.debounceTimer) {
                    clearTimeout(state.debounceTimer);
                }
                state.debounceTimer = setTimeout(function() { 
                    func.apply(this, args); 
                }, delay);
            };
        },
        
        throttle: function(func, delay) {
            if (window.ExplainerPlugin && window.ExplainerPlugin.Utils) {
                return window.ExplainerPlugin.Utils.throttle(func, delay);
            }
            // Fallback implementation
            return function(...args) {
                if (!state.throttleTimer) {
                    state.throttleTimer = setTimeout(() => {
                        func.apply(this, args);
                        state.throttleTimer = null;
                    }, delay);
                }
            };
        },
        
        memoize: (func, keyFunc) => {
            if (window.ExplainerPlugin && window.ExplainerPlugin.Utils) {
                return window.ExplainerPlugin.Utils.memoize(func, keyFunc);
            }
            // Fallback implementation
            return function(...args) {
                const key = keyFunc ? keyFunc(...args) : args[0];
                if (state.cache.has(key)) {
                    return state.cache.get(key);
                }
                const result = func.apply(this, args);
                state.cache.set(key, result);
                return result;
            };
        },
        
        cleanupObservers: () => {
            state.observers.forEach(observer => {
                if (observer.disconnect) {
                    observer.disconnect();
                } else if (observer.removeEventListener) {
                    observer.removeEventListener();
                }
            });
            state.observers = [];
        },
        
        requestIdleCallback: (callback) => {
            if (window.ExplainerPlugin && window.ExplainerPlugin.Utils) {
                return window.ExplainerPlugin.Utils.requestIdleCallback(callback);
            }
            // Fallback implementation
            if (window.requestIdleCallback) {
                return window.requestIdleCallback(callback);
            }
            return setTimeout(callback, 1);
        }
    };

    /**
     * Mobile device detection utility
     */
    function isMobileDevice() {
        // Check for touch capability and screen size
        const hasTouchScreen = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        const hasSmallScreen = window.innerWidth <= 768;
        
        // Check user agent for mobile indicators
        const userAgent = navigator.userAgent.toLowerCase();
        const mobileKeywords = ['mobile', 'android', 'iphone', 'ipad', 'ipod', 'blackberry', 'windows phone'];
        const hasMobileUserAgent = mobileKeywords.some(keyword => userAgent.includes(keyword));
        
        // Device is considered mobile if it has touch AND (small screen OR mobile user agent)
        return hasTouchScreen && (hasSmallScreen || hasMobileUserAgent);
    }

    /**
     * Load and highlight terms for the current post
     */
    function loadAndHighlightPostTerms() {
        // Only load terms on single posts/pages
        if (typeof explainerAjax === 'undefined' || !explainerAjax.post_id || explainerAjax.post_id === 0) {
            logger.debug('Not loading post terms - not a single post/page or no post ID available');
            return;
        }
        
        logger.debug('Loading terms for post', {post_id: explainerAjax.post_id});
        
        const formData = new FormData();
        formData.append('action', 'explainer_load_post_terms');
        formData.append('post_id', explainerAjax.post_id);
        formData.append('nonce', explainerAjax.nonce);
        
        // Use XMLHttpRequest for better Safari compatibility
        const xhr = new XMLHttpRequest();
        xhr.open('POST', explainerAjax.ajaxurl, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success && data.data && data.data.terms) {
                            const terms = data.data.terms;
                            logger.info('Loaded terms', {
                                post_id: data.data.post_id,
                                term_count: data.data.count,
                                terms: terms.map(function(t) { return t.term; })
                            });
                            
                            if (terms.length > 0) {
                                highlightTermsInContent(terms);
                            }
                        } else {
                            logger.debug('No terms found for post', {
                                post_id: explainerAjax.post_id,
                                error: data.data || 'Unknown error'
                            });
                        }
                    } catch (e) {
                        logger.error('Failed to parse JSON response for post terms', {
                            error: e.message,
                            response: xhr.responseText.substring(0, 200)
                        });
                    }
                } else {
                    // 400 error means endpoint doesn't exist (free version) - silently skip
                    if (xhr.status !== 400) {
                        logger.error('Failed to load terms', {
                            status: xhr.status,
                            statusText: xhr.statusText
                        });
                    }
                }
            }
        };
        xhr.send(formData);
    }
    
    /**
     * Highlight terms in the content and make them clickable
     */
    function highlightTermsInContent(terms) {
        if (!terms || terms.length === 0) {
            return;
        }
        
        logger.debug('Highlighting terms in content', {term_count: terms.length});
        
        // Get content container(s)
        const contentSelectors = config.includedSelectors.split(',').map(s => s.trim()).filter(s => s);
        if (contentSelectors.length === 0) {
            logger.warn('No content selectors configured for term highlighting');
            return;
        }
        
        contentSelectors.forEach(selector => {
            const container = document.querySelector(selector);
            if (!container) {
                logger.debug('Content container not found', {selector});
                return;
            }
            
            logger.debug('Processing content container', {selector});
            
            // Create a map of terms for quick lookup
            const termMap = {};
            terms.forEach(term => {
                termMap[term.term.toLowerCase()] = term;
            });
            
            // Process text nodes in the container
            highlightTermsInElement(container, termMap);
        });
        
        logger.info('Term highlighting completed', {
            highlighted_terms: Object.keys(terms.reduce((acc, term) => {
                acc[term.term] = true;
                return acc;
            }, {}))
        });
    }
    
    /**
     * Recursively highlight terms in an element
     */
    function highlightTermsInElement(element, termMap) {
        // Skip script and style elements
        if (element.tagName === 'SCRIPT' || element.tagName === 'STYLE') {
            return;
        }
        
        // Process child nodes
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );
        
        const textNodes = [];
        let node;
        while (node = walker.nextNode()) {
            textNodes.push(node);
        }
        
        // Process each text node
        textNodes.forEach(textNode => {
            const text = textNode.textContent;
            let modifiedText = text;
            let hasChanges = false;
            
            // Check for each term
            Object.keys(termMap).forEach(termLower => {
                const term = termMap[termLower];
                const regex = new RegExp(`\\b(${escapeRegExp(term.term)})\\b`, 'gi');
                
                if (regex.test(modifiedText)) {
                    modifiedText = modifiedText.replace(regex, (match) => {
                        hasChanges = true;
                        return `<span class="explainer-highlighted-term" data-term-id="${term.id}" data-term="${encodeURIComponent(term.term)}" data-explanation="${encodeURIComponent(term.explanation)}" title="Click for explanation">${match}</span>`;
                    });
                }
            });
            
            // Replace the text node with highlighted version if changes were made
            if (hasChanges) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = modifiedText;
                
                // Replace the text node with the new elements
                while (tempDiv.firstChild) {
                    textNode.parentNode.insertBefore(tempDiv.firstChild, textNode);
                }
                textNode.parentNode.removeChild(textNode);
            }
        });
        
        // Add click event listeners to highlighted terms
        const highlightedTerms = element.querySelectorAll('.explainer-highlighted-term');
        highlightedTerms.forEach(termElement => {
            termElement.addEventListener('click', handleHighlightedTermClick);
            termElement.style.cursor = 'pointer';
            termElement.style.textDecoration = 'underline';
            termElement.style.textDecorationStyle = 'dotted';
            termElement.style.color = '#0073aa';
        });
    }
    
    /**
     * Handle clicks on highlighted terms
     */
    function handleHighlightedTermClick(event) {
        event.preventDefault();
        event.stopPropagation(); // Prevent event bubbling to selection handlers
        
        const termElement = event.target;
        const termId = termElement.getAttribute('data-term-id');
        const term = decodeURIComponent(termElement.getAttribute('data-term'));
        const explanation = decodeURIComponent(termElement.getAttribute('data-explanation'));
        
        // Get current reading level from localStorage, but use 'standard' if slider is disabled
        const currentReadingLevel = config.showReadingLevelSlider ?
            (localStorage.getItem('explainer_reading_level') || 'standard') : 'standard';
        
        logger.debug('Highlighted term clicked', {
            term_id: termId,
            term: term,
            explanation_length: explanation.length,
            reading_level: currentReadingLevel
        });
        
        // Show tooltip with cached explanation - let the tooltip handle reading levels
        if (window.ExplainerPlugin && window.ExplainerPlugin.showTooltip) {
            // Create position object for the tooltip
            const rect = termElement.getBoundingClientRect();
            const position = {
                x: rect.left + (rect.width / 2),
                y: rect.top,
                width: rect.width,
                height: rect.height,
                scrollX: window.scrollX || window.pageXOffset || 0,
                scrollY: window.scrollY || window.pageYOffset || 0
            };
            
            // Show tooltip with cached explanation and current reading level
            const options = {
                selectedText: term,
                cached: true,
                reading_level: currentReadingLevel, // This was the key fix - use current reading level
                showDisclaimer: config.showDisclaimer || false,
                showProvider: config.showProvider || false,
                showReadingLevelSlider: config.showReadingLevelSlider,
                provider: config.apiProvider || 'openai'
            };
            
            window.ExplainerPlugin.showTooltip(explanation, position, 'explanation', options);
        } else {
            logger.warn('Tooltip function not available for highlighted term');
        }
    }
    
    /**
     * Escape special regex characters
     */
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    /**
     * Initialize the plugin with enhanced accessibility
     */
    function init() {
        logger.debug('Attempting to initialize...');
        logger.info('Plugin initialization started', {
            url: window.location.href,
            timestamp: Date.now()
        });
        
        // Check if plugin should be loaded
        if (!shouldLoadPlugin()) {
            logger.debug('shouldLoadPlugin returned false, stopping initialization');
            logger.debug('Plugin initialization aborted - shouldLoadPlugin check failed');
            return;
        }
        
        logger.debug('Plugin should load, continuing initialization...');
        logger.debug('Plugin initialization continuing - all checks passed');
        
        // Load settings from localised data
        loadSettings();
        
        // Load localised strings
        loadLocalisedStrings();
        
        // Validate and enhance colour contrast
        validateColourContrast();
        
        
        // Create UI elements
        createToggleButton();
        
        // Apply colour settings to tooltips
        applyTooltipColors();
        
        // Enhance ARIA support
        enhanceARIASupport();
        
        // Set up event listeners with performance optimisation
        setupEventListeners();
        
        // Initialize selection system
        initializeSelectionSystem();
        
        // Set up cleanup on page unload
        window.addEventListener('beforeunload', cleanup);
        
        // Announce plugin availability to screen readers
        announceToScreenReader('AI Explainer plugin loaded. Press Ctrl+Shift+E to toggle, or use the button in the bottom right corner.');
        
        // Load and highlight terms for this post
        loadAndHighlightPostTerms();
        
        logger.info('AI Explainer Plugin initialized with accessibility enhancements');
    }
    
    /**
     * Check if plugin should be loaded
     */
    function shouldLoadPlugin() {
        // Don't load on admin pages
        if (document.body.classList.contains('wp-admin')) {
            return false;
        }
        
        // Don't load if explicitly disabled (fallback to enabled if not defined)
        if (typeof explainerAjax !== 'undefined' && explainerAjax.settings && !explainerAjax.settings.enabled) {
            return false;
        }
        
        // Add debug logging
        if (typeof explainerAjax === 'undefined') {
            logger.warn('explainerAjax object not found, plugin may not be properly localised');
        } else {
            logger.debug('explainerAjax found:', explainerAjax);
        }
        
        // Check if selection API is supported
        if (!window.getSelection) {
            logger.warn('Text selection not supported in this browser');
            return false;
        }
        
        return true;
    }
    
    /**
     * Load settings from WordPress localised data
     */
    function loadSettings() {
        logger.debug('Loading settings...');
        if (typeof explainerAjax !== 'undefined' && explainerAjax.settings) {
            logger.debug('Found explainerAjax.settings:', explainerAjax.settings);
            config.minSelectionLength = parseInt(explainerAjax.settings.min_selection_length) || 3;
            config.maxSelectionLength = parseInt(explainerAjax.settings.max_selection_length) || 200;
            config.minWords = parseInt(explainerAjax.settings.min_words) || 1;
            config.maxWords = parseInt(explainerAjax.settings.max_words) || 30;
            config.enabled = explainerAjax.settings.enabled !== false;
            config.showDisclaimer = explainerAjax.settings.show_disclaimer !== false;
            config.showProvider = explainerAjax.settings.show_provider !== false;
            config.showReadingLevelSlider = explainerAjax.settings.show_reading_level_slider;
            config.apiProvider = explainerAjax.settings.api_provider || 'openai';
            config.debugMode = explainerAjax.settings.debug_mode === true;
            config.mobileSelectionDelay = parseInt(explainerAjax.settings.mobile_selection_delay) || 1000;
            config.enableStopWordFiltering = explainerAjax.settings.stop_word_filtering_enabled !== false;
            
            logger.debug('Debug mode enabled:', config.debugMode);
            logger.debug('Mobile selection delay:', config.mobileSelectionDelay);
            
            // Use server settings directly, no fallback to hardcoded values
            config.includedSelectors = explainerAjax.settings.included_selectors || '';
            config.excludedSelectors = explainerAjax.settings.excluded_selectors || '';
            
            logger.debug('Loaded included selectors:', config.includedSelectors);
            logger.debug('Loaded excluded selectors:', config.excludedSelectors);
        } else {
            logger.debug('No explainerAjax.settings found, using minimal defaults');
            // Set sensible defaults when no server settings available
            config.includedSelectors = 'article, main, .content, .entry-content, .post-content';
            config.excludedSelectors = '';
            config.minSelectionLength = 3;
            config.maxSelectionLength = 200;
            config.minWords = 1;
            config.maxWords = 30;
            config.enabled = true;
            config.showDisclaimer = true;
            config.showProvider = true;
            config.apiProvider = 'openai';
        }
        logger.debug('Final config:', config);
    }
    
    /**
     * Load localised strings from server
     */
    function loadLocalisedStrings() {
        logger.debug('Loading localised strings...');
        
        if (!explainerAjax || !explainerAjax.ajaxurl) {
            logger.debug('No AJAX URL available, using default strings');
            state.localisedStrings = {
                'explanation': 'Explanation',
                'loading': 'Loading...',
                'error': 'Error',
                'disclaimer': 'AI-generated content may not always be accurate',
                'powered_by': 'Powered by',
                'failed_to_get_explanation': 'Failed to get explanation',
                'connection_error': 'Connection error. Please try again.',
                'loading_explanation': 'Loading explanation...',
                'selection_too_short': 'Selection too short (minimum %d characters)',
                'selection_too_long': 'Selection too long (maximum %d characters)', 
                'selection_word_count': 'Selection must be between %d and %d words',
                'ai_explainer_enabled': 'AI Explainer enabled. Select text to get explanations.',
                'ai_explainer_disabled': 'AI Explainer disabled.'
            };
            return;
        }
        
        // Fetch localised strings via AJAX (Safari-compatible)
        utils.ajax(explainerAjax.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'explainer_get_localized_strings',
                nonce: explainerAjax.nonce
            }).toString()
        })
        .then(function(data) {
            if (data.success && data.data && data.data.strings) {
                state.localisedStrings = data.data.strings;
                logger.debug('Localised strings loaded:', state.localisedStrings);
            } else {
                logger.debug('Failed to load localised strings, using defaults');
                state.localisedStrings = {
                    'explanation': 'Explanation',
                    'loading': 'Loading...',
                    'error': 'Error',
                    'disclaimer': 'AI-generated content may not always be accurate',
                    'powered_by': 'Powered by',
                    'failed_to_get_explanation': 'Failed to get explanation',
                    'connection_error': 'Connection error. Please try again.',
                    'loading_explanation': 'Loading explanation...',
                    'selection_too_short': 'Selection too short (minimum %d characters)',
                    'selection_too_long': 'Selection too long (maximum %d characters)', 
                    'selection_word_count': 'Selection must be between %d and %d words',
                    'ai_explainer_enabled': 'AI Explainer enabled. Select text to get explanations.',
                    'ai_explainer_disabled': 'AI Explainer disabled.'
                };
            }
        })
        .catch(function(error) {
            logger.error('Error loading localised strings:', error);
            state.localisedStrings = {
                'explanation': 'Explanation',
                'loading': 'Loading...',
                'error': 'Error',
                'disclaimer': 'AI-generated content may not always be accurate',
                'powered_by': 'Powered by',
                'failed_to_get_explanation': 'Failed to get explanation',
                'connection_error': 'Connection error. Please try again.',
                'loading_explanation': 'Loading explanation...',
                'selection_too_short': 'Selection too short (minimum %d characters)',
                'selection_too_long': 'Selection too long (maximum %d characters)', 
                'selection_word_count': 'Selection must be between %d and %d words',
                'ai_explainer_enabled': 'AI Explainer enabled. Select text to get explanations.',
                'ai_explainer_disabled': 'AI Explainer disabled.'
            };
        });
    }
    
    /**
     * Get localised string
     */
    function getLocalisedString(key, ...args) {
        // Define fallback strings for essential keys
        const fallbacks = {
            'loading_explanation': 'Loading explanation...',
            'explanation': 'Explanation',
            'loading': 'Loading...',
            'error': 'Error',
            'disclaimer': 'AI-generated content may not always be accurate',
            'powered_by': 'Powered by',
            'failed_to_get_explanation': 'Failed to get explanation',
            'connection_error': 'Connection error. Please try again.',
            'selection_too_short': 'Selection too short (minimum %d characters)',
            'selection_too_long': 'Selection too long (maximum %d characters)', 
            'selection_word_count': 'Selection must be between %d and %d words',
            'ai_explainer_enabled': 'AI Explainer enabled. Select text to get explanations.',
            'ai_explainer_disabled': 'AI Explainer disabled.'
        };
        
        let string = state.localisedStrings && state.localisedStrings[key] 
            ? state.localisedStrings[key] 
            : (fallbacks[key] || key);
        
        // Handle sprintf-style formatting
        if (args.length > 0) {
            let i = 0;
            string = string.replace(/%d/g, function() {
                return args[i++] || '';
            });
        }
        
        return string;
    }
    
    /**
     * Cleanup function for performance
     */
    function cleanup() {
        // Clear timers
        if (state.debounceTimer) {
            clearTimeout(state.debounceTimer);
        }
        if (state.throttleTimer) {
            clearTimeout(state.throttleTimer);
        }
        if (state.mobileDelayTimer) {
            clearTimeout(state.mobileDelayTimer);
        }
        
        // Clear cache
        state.cache.clear();
        
        // Cleanup observers
        utils.cleanupObservers();
        
        // Remove event listeners (none currently added at document level)
        
        // Clear active requests
        state.activeRequests = 0;
        state.requestQueue = [];
    }
    
    /**
     * Create toggle button with accessibility enhancements
     */
    function createToggleButton() {
        logger.debug('Creating toggle button...');
        
        // Create container for toggle and help icon
        elements.toggleContainer = document.createElement('div');
        elements.toggleContainer.id = 'explainer-toggle-container';
        elements.toggleContainer.className = 'explainer-toggle-container';
        
        // Add position class to container based on settings
        const position = getTogglePosition();
        elements.toggleContainer.classList.add(position);
        
        // Create toggle button
        elements.toggleButton = document.createElement('button');
        elements.toggleButton.id = 'explainer-toggle';
        elements.toggleButton.className = 'explainer-toggle';
        elements.toggleButton.setAttribute('aria-label', 'Toggle AI Explainer feature');
        elements.toggleButton.setAttribute('aria-pressed', config.enabled ? 'true' : 'false');
        elements.toggleButton.setAttribute('aria-describedby', 'explainer-description');
        elements.toggleButton.setAttribute('role', 'switch');
        elements.toggleButton.setAttribute('tabindex', '0');
        
        // Create the button content with icon and text
        const buttonHTML = `
            <svg class="explainer-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
            <span class="explainer-text">Explainer</span>
        `;
        
        elements.toggleButton.innerHTML = buttonHTML;
        
        // Apply custom colors from settings
        applyButtonColors();
        
        // Add screen reader description
        const description = document.createElement('span');
        description.id = 'explainer-description';
        description.className = 'explainer-sr-only';
        description.textContent = 'Toggle AI text explanation feature on or off';
        
        // Create help icon
        createHelpIcon();
        
        // Add elements to container
        elements.toggleContainer.appendChild(elements.toggleButton);
        if (elements.helpIcon) {
            elements.toggleContainer.appendChild(elements.helpIcon);
        }
        
        // Add container and description to page
        document.body.appendChild(elements.toggleContainer);
        document.body.appendChild(description);
        
        logger.debug('Toggle button created and added to DOM');
        logger.debug('Button element:', elements.toggleButton);
        
        // Set initial state
        updateToggleButton();
        
        
        // Add click handler directly to button
        elements.toggleButton.addEventListener('click', function(e) {
            logger.debug('Toggle button clicked!', e);
            logger.debug('Current enabled state:', config.enabled);
            e.stopPropagation(); // Prevent event bubbling
            e.preventDefault(); // Prevent default behavior
            togglePlugin();
            logger.debug('New enabled state:', config.enabled);
        });
        
        // Also prevent mouseup from triggering selection handler
        elements.toggleButton.addEventListener('mouseup', function(e) {
            e.stopPropagation(); // Prevent event bubbling to document
            e.preventDefault();
        });
        
        // Mobile touch handling - use touchstart to ensure iOS compatibility
        let touchStartTime = 0;
        let touchStartTarget = null;
        
        elements.toggleButton.addEventListener('touchstart', function(e) {
            logger.debug('Toggle button touch start');
            touchStartTime = Date.now();
            touchStartTarget = e.target;
            e.stopPropagation(); // Prevent event bubbling to document
            // Don't prevent default here as it can interfere with scrolling
        });
        
        elements.toggleButton.addEventListener('touchend', function(e) {
            logger.debug('Toggle button touch end');
            e.stopPropagation(); // Prevent event bubbling to document
            e.preventDefault(); // Prevent default to avoid triggering click events
            
            // Check if this was a quick tap (not a scroll or long press)
            const touchDuration = Date.now() - touchStartTime;
            const isSameTarget = e.target === touchStartTarget || 
                               e.target.closest('.explainer-toggle') === touchStartTarget ||
                               touchStartTarget.closest('.explainer-toggle') === e.target.closest('.explainer-toggle');
            
            if (touchDuration < 500 && isSameTarget) { // Less than 500ms and same target = tap
                logger.debug('Valid touch tap detected, toggling plugin');
                togglePlugin();
            } else {
                logger.debug('Touch cancelled - duration:', touchDuration, 'same target:', isSameTarget);
            }
        });
        
        // Handle touch cancel (when user scrolls away)
        elements.toggleButton.addEventListener('touchcancel', function(e) {
            logger.debug('Toggle button touch cancelled');
            touchStartTime = 0;
            touchStartTarget = null;
            e.stopPropagation();
        });
        
        logger.debug('Click handler added to toggle button');
    }
    
    /**
     * Update toggle button state
     */
    function updateToggleButton() {
        if (!elements.toggleButton) {
            logger.debug('updateToggleButton - no button element found');
            return;
        }
        
        logger.debug('updateToggleButton called, enabled:', config.enabled);
        logger.debug('Button current classes:', elements.toggleButton.className);
        
        elements.toggleButton.classList.toggle('enabled', config.enabled);
        elements.toggleButton.setAttribute('aria-pressed', config.enabled ? 'true' : 'false');
        elements.toggleButton.title = config.enabled ? 'Disable AI Explainer' : 'Enable AI Explainer';
        
        logger.debug('Button updated classes:', elements.toggleButton.className);
        
        // Update colors when state changes
        applyButtonColors();
    }
    
    /**
     * Apply custom button colors from settings
     */
    function applyButtonColors() {
        if (!elements.toggleButton) return;
        
        // Get colors from settings - settings should always be available
        let enabledColor = (window.wpAiExplainer && window.wpAiExplainer.brandColors && window.wpAiExplainer.brandColors.buttonEnabled) || '#8b5cf6';
        let disabledColor = (window.wpAiExplainer && window.wpAiExplainer.brandColors && window.wpAiExplainer.brandColors.buttonDisabled) || '#94a3b8';
        let textColor = '#ffffff';
        
        // Get colors from WordPress settings
        if (typeof explainerAjax !== 'undefined' && explainerAjax.settings) {
            enabledColor = explainerAjax.settings.button_enabled_color || enabledColor;
            disabledColor = explainerAjax.settings.button_disabled_color || disabledColor;
            textColor = explainerAjax.settings.button_text_color || textColor;
        }
        
        // Update CSS custom properties
        document.documentElement.style.setProperty('--explainer-button-enabled', enabledColor);
        document.documentElement.style.setProperty('--explainer-button-disabled', disabledColor);
        document.documentElement.style.setProperty('--explainer-button-text', textColor);
        
        // Force update the button's visual state by ensuring CSS classes are properly applied
        if (config.enabled) {
            elements.toggleButton.classList.add('enabled');
        } else {
            elements.toggleButton.classList.remove('enabled');
        }
        
        logger.debug('Button colors applied', {
            enabled: enabledColor,
            disabled: disabledColor,
            text: textColor,
            state: config.enabled ? 'enabled' : 'disabled',
            hasEnabledClass: elements.toggleButton.classList.contains('enabled')
        });
    }
    
    /**
     * Create help icon with accessibility enhancements
     */
    function createHelpIcon() {
        // Check if onboarding is enabled
        if (typeof explainerAjax !== 'undefined' && explainerAjax.settings && explainerAjax.settings.onboarding_enabled !== true && explainerAjax.settings.onboarding_enabled !== '1' && explainerAjax.settings.onboarding_enabled !== 1) {
            logger.debug('Help icon not created - onboarding disabled');
            elements.helpIcon = null;
            return;
        }
        
        logger.debug('Creating help icon...');
        
        elements.helpIcon = document.createElement('button');
        elements.helpIcon.id = 'explainer-help-icon';
        elements.helpIcon.className = 'explainer-help-icon';
        elements.helpIcon.setAttribute('aria-label', 'Show AI Explainer tutorial');
        elements.helpIcon.setAttribute('title', 'Show tutorial');
        elements.helpIcon.setAttribute('tabindex', '0');
        elements.helpIcon.setAttribute('type', 'button');
        
        // Create the help icon content (question mark)
        const helpIconHTML = `
            <svg class="explainer-help-svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/>
            </svg>
        `;
        
        elements.helpIcon.innerHTML = helpIconHTML;
        
        // Add click event listener
        elements.helpIcon.addEventListener('click', handleHelpIconClick);
        elements.helpIcon.addEventListener('keydown', handleHelpIconKeydown);
        
        logger.debug('Help icon created');
    }
    
    /**
     * Handle help icon click
     */
    function handleHelpIconClick(event) {
        event.preventDefault();
        event.stopPropagation();
        
        logger.debug('Help icon clicked - enabling plugin and opening onboarding panel');
        
        // Enable the plugin if it's not already enabled
        // This ensures the user can actually use the feature they're learning about
        if (!config.enabled) {
            logger.debug('Plugin was disabled, enabling it for help demo');
            config.enabled = true;
            updateToggleButton();
            saveUserPreferences();
            
            // Announce the change to screen readers
            announceToScreenReader('AI Explainer enabled for tutorial');
        }
        
        // Force show onboarding panel
        showOnboardingPanel();
    }
    
    /**
     * Handle help icon keyboard interaction
     */
    function handleHelpIconKeydown(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            handleHelpIconClick(event);
        }
    }
    
    /**
     * Show onboarding panel (force show even if user has seen it before)
     */
    function showOnboardingPanel() {
        // Check if onboarding manager is available
        if (window.ExplainerPlugin && window.ExplainerPlugin.OnboardingManager) {
            logger.debug('Creating new onboarding instance');
            
            const onboarding = new window.ExplainerPlugin.OnboardingManager();
            
            // Override the first-time check to force showing
            onboarding.shouldShow = true;
            onboarding.checkFirstTimeUser = () => true;
            
            // Apply colors and create panel
            onboarding.applyAdminColors();
            onboarding.createPanel();
            onboarding.setupEventListeners();
            onboarding.show();
            
            logger.debug('Onboarding panel force-opened');
        } else {
            logger.warn('Onboarding manager not available');
        }
    }
    
    /**
     * Apply custom tooltip colors and font from settings
     */
    function applyTooltipColors() {
        // Get colors from settings - settings should always be available
        let bgColor = '#333333';
        let textColor = '#ffffff';
        let footerColor = '#ffffff';
        let sliderTrackColor = 'rgba(255, 255, 255, 1.0)';
        let sliderThumbColor = '#8b5cf6';
        
        // Get colors from WordPress settings
        if (typeof explainerAjax !== 'undefined' && explainerAjax.settings) {
            bgColor = explainerAjax.settings.tooltip_bg_color || bgColor;
            textColor = explainerAjax.settings.tooltip_text_color || textColor;
            footerColor = explainerAjax.settings.tooltip_footer_color || footerColor;
            sliderThumbColor = explainerAjax.settings.slider_thumb_color || sliderThumbColor;
            
            // Convert track color hex to rgba with full opacity
            if (explainerAjax.settings.slider_track_color) {
                sliderTrackColor = hexToRgba(explainerAjax.settings.slider_track_color, 1.0);
            }
        }
        
        // Detect site's paragraph font
        const siteFont = detectSiteFont();
        
        // Update CSS custom properties
        document.documentElement.style.setProperty('--explainer-tooltip-bg-color', bgColor);
        document.documentElement.style.setProperty('--explainer-tooltip-text-color', textColor);
        document.documentElement.style.setProperty('--explainer-tooltip-footer-color', footerColor);
        document.documentElement.style.setProperty('--explainer-slider-track-color', sliderTrackColor);
        document.documentElement.style.setProperty('--explainer-slider-thumb-color', sliderThumbColor);
        document.documentElement.style.setProperty('--explainer-site-font', siteFont);
        
        // Set active label background and border colors with transparency
        // Extract hex color from sliderThumbColor if it's already set from settings
        let thumbHex = '#8b5cf6'; // fallback
        if (typeof explainerAjax !== 'undefined' && explainerAjax.settings && explainerAjax.settings.slider_thumb_color) {
            thumbHex = explainerAjax.settings.slider_thumb_color;
        }
        const activeLabelBg = hexToRgba(thumbHex, 0.15);
        const activeLabelBorder = hexToRgba(thumbHex, 0.3);
        document.documentElement.style.setProperty('--explainer-active-label-bg', activeLabelBg);
        document.documentElement.style.setProperty('--explainer-active-label-border', activeLabelBorder);
        
        logger.debug('Tooltip colors and font applied', {
            background: bgColor,
            text: textColor,
            footer: footerColor,
            sliderTrack: sliderTrackColor,
            sliderThumb: sliderThumbColor,
            font: siteFont
        });
    }
    
    /**
     * Convert hex color to rgba with specified opacity
     * @param {string} hex - Hex color (e.g., "#ffffff" or "#fff")
     * @param {number} alpha - Alpha value between 0 and 1
     * @return {string} RGBA color string
     */
    function hexToRgba(hex, alpha = 1) {
        // Remove # if present
        hex = hex.replace('#', '');
        
        // Handle 3-character hex
        if (hex.length === 3) {
            hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        }
        
        // Convert to RGB
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
        
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }
    
    /**
     * Detect the site's paragraph font
     */
    function detectSiteFont() {
        // Try to find a paragraph element to get its font
        const paragraph = document.querySelector('p, article p, main p, .content p, .entry-content p, .post-content p');
        
        if (paragraph) {
            const computedStyle = window.getComputedStyle(paragraph);
            const fontFamily = computedStyle.getPropertyValue('font-family');
            
            if (fontFamily && fontFamily !== 'inherit') {
                logger.debug('Site font detected from paragraph', { font: fontFamily });
                return fontFamily;
            }
        }
        
        // Fallback: check body font
        const body = document.body;
        if (body) {
            const bodyStyle = window.getComputedStyle(body);
            const bodyFont = bodyStyle.getPropertyValue('font-family');
            
            if (bodyFont && bodyFont !== 'inherit') {
                logger.debug('Site font detected from body', { font: bodyFont });
                return bodyFont;
            }
        }
        
        // Final fallback to system font
        const systemFont = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
        logger.debug('Using system font fallback', { font: systemFont });
        return systemFont;
    }
    
    /**
     * Get toggle button position from settings
     */
    function getTogglePosition() {
        if (typeof explainerAjax !== 'undefined' && explainerAjax.settings) {
            return explainerAjax.settings.toggle_position || 'bottom-right';
        }
        return 'bottom-right';
    }
    
    /**
     * Set up event listeners with accessibility support
     */
    function setupEventListeners() {
        // Toggle button keyboard only (click handler already added in createToggleButton)
        if (elements.toggleButton) {
            elements.toggleButton.addEventListener('keydown', handleToggleKeydown);
        }
        
        // Document-level selection events
        logger.debug('Setting up selection event listeners...');
        const debouncedHandler = utils.debounce(handleSelection, config.debounceDelay);
        document.addEventListener('mouseup', debouncedHandler);
        
        // Enhanced mobile selection handling
        document.addEventListener('touchend', function(e) {
            // Add slight delay for mobile to ensure selection is completed
            setTimeout(() => {
                debouncedHandler(e);
            }, 50);
        });
        
        // Add direct mouseup handler for testing
        document.addEventListener('mouseup', function(e) {
            logger.debug('Direct mouseup event detected', e);
        });
        
        // Keyboard events
        document.addEventListener('keydown', handleKeyDown);
        
        // Click outside to dismiss
        document.addEventListener('click', handleOutsideClick);
        
        // Window resize
        window.addEventListener('resize', handleResize);
        
        // Focus management
        document.addEventListener('focusin', handleFocusIn);
        document.addEventListener('focusout', handleFocusOut);
        
        // Onboarding coordination - close tooltip when onboarding panel closes
        document.addEventListener('onboardingPanelHidden', handleOnboardingPanelHidden);
    }
    
    /**
     * Initialize selection system
     */
    function initializeSelectionSystem() {
        // Load user preferences
        loadUserPreferences();
        
        // Update button state after loading preferences
        updateToggleButton();
        
        // Set up selection highlighting
        setupSelectionHighlighting();
        
        logger.debug('Selection system initialized');
    }
    
    /**
     * Handle text selection
     */
    function handleSelection(event) {
        logger.debug('handleSelection called', { enabled: config.enabled, isProcessing: state.isProcessing });
        logger.user('Text selection interaction started', {
            enabled: config.enabled,
            isProcessing: state.isProcessing,
            eventType: event ? event.type : 'unknown'
        });
        
        // Ignore clicks on highlighted terms (let the term click handler deal with those)
        if (event && event.target && event.target.classList && event.target.classList.contains('explainer-highlighted-term')) {
            logger.debug('Ignoring selection event on highlighted term');
            return;
        }
        
        // Check if the event came from the toggle button, help icon or tooltip elements
        if (event && event.target && event.target.closest) {
            const isToggleButton = event.target.closest('.explainer-toggle') || 
                                   event.target.classList.contains('explainer-toggle') ||
                                   event.target.id === 'explainer-toggle';
            const isHelpIcon = event.target.closest('.explainer-help-icon') || 
                              event.target.classList.contains('explainer-help-icon') ||
                              event.target.id === 'explainer-help-icon';
            const isTooltip = event.target.closest('.explainer-tooltip') || 
                             event.target.classList.contains('explainer-tooltip');
            
            if (isToggleButton) {
                logger.debug('Selection ignored - click on toggle button');
                return;
            }
            
            if (isHelpIcon) {
                logger.debug('Selection ignored - click on help icon');
                return;
            }
            
            if (isTooltip) {
                logger.debug('Selection ignored - click inside tooltip');
                return;
            }
        }
        
        if (!config.enabled || state.isProcessing) {
            logger.debug('Selection ignored', { enabled: config.enabled, isProcessing: state.isProcessing });
            return;
        }
        
        try {
            const selection = window.getSelection();
            logger.debug('Selection object retrieved', { 
                rangeCount: selection.rangeCount,
                toString: selection.toString(),
                type: selection.type
            });
            
            // Clear previous selection
            clearPreviousSelection();
            
            // Check if there's a valid selection
            if (!selection || selection.rangeCount === 0) {
                logger.debug('No valid selection found');
                return;
            }
            
            const selectedText = selection.toString().trim();
            logger.debug('Selected text:', selectedText);
            
            // Validate selection
            if (!validateSelection(selectedText, selection)) {
                logger.debug('Selection validation failed');
                return;
            }
            
            // Check if selection is in allowed content
            if (!isSelectableContent(selection)) {
                return;
            }
            
            // Store selection data
            storeSelectionData(selectedText, selection);
            
            // Track selection position
            trackSelectionPosition(selection);
            
            // Extract context
            extractSelectionContext(selection);
            
            // Trigger explanation request with mobile-aware timing
            triggerExplanationWithTiming();
            
        } catch (error) {
            logger.error('Selection handling error:', error);
            showError('Selection processing failed');
        }
    }
    
    /**
     * Validate text selection
     */
    function validateSelection(text, selection) {
        logger.debug('validateSelection called with text:', text);
        logger.debug('text length:', text.length);
        logger.debug('config limits:', {
            minLength: config.minSelectionLength,
            maxLength: config.maxSelectionLength,
            minWords: config.minWords,
            maxWords: config.maxWords
        });
        
        if (!text || text.length === 0) {
            logger.debug('Validation failed - empty text');
            return false;
        }
        
        // Check minimum length - silently ignore
        if (text.length < config.minSelectionLength) {
            logger.debug('Validation failed - too short (silently ignored)');
            return false;
        }
        
        // Check maximum length
        if (text.length > config.maxSelectionLength) {
            logger.debug('Validation failed - too long');
            showValidationMessage(getLocalisedString('selection_too_long', config.maxSelectionLength));
            return false;
        }
        
        // Check word count
        const wordCount = countWords(text);
        logger.debug('Word count:', wordCount);
        if (wordCount < config.minWords || wordCount > config.maxWords) {
            logger.debug('Validation failed - word count out of range');
            showValidationMessage(getLocalisedString('selection_word_count', config.minWords, config.maxWords));
            return false;
        }
        
        // Check for stop words only - silently ignore
        if (isStopWordOnly(text)) {
            logger.debug('Validation failed - stop words only (silently ignored)');
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if selection is in allowed content
     */
    function isSelectableContent(selection) {
        logger.debug('isSelectableContent called');
        logger.debug('selection.anchorNode:', selection.anchorNode);
        
        if (!selection.anchorNode) {
            logger.debug('No anchor node found');
            return false;
        }
        
        const element = selection.anchorNode.nodeType === Node.TEXT_NODE ? 
            selection.anchorNode.parentElement : selection.anchorNode;
        
        logger.debug('Target element:', element);
        logger.debug('Element tag:', element && element.tagName);
        logger.debug('Element classes:', element && element.className);
        
        // Check if element is in excluded areas
        // Always exclude our own elements and form inputs
        const alwaysExcluded = [
            '.explainer-tooltip', '.explainer-toggle', '.explainer-help-icon',
            'button', 'input', 'select', 'textarea'
        ];
        
        // Always allow onboarding demo content (cannot be excluded by admin)
        if (element.closest('.explainer-onboarding-selectable')) {
            logger.debug('Element is in onboarding demo area - always allowed');
            return true;
        }
        
        // Get user-configured excluded selectors
        const userExcluded = config.excludedSelectors ? 
            config.excludedSelectors.split(',').map(s => s.trim()).filter(s => s.length > 0) : 
            [];
        
        const excludedSelectors = [...alwaysExcluded, ...userExcluded];
        logger.debug('Final excluded selectors:', excludedSelectors);
        
        logger.debug('Checking excluded selectors...');
        for (const selector of excludedSelectors) {
            if (element.closest(selector)) {
                logger.debug('Found excluded selector:', selector);
                return false;
            }
        }
        logger.debug('No excluded selectors found');
        
        // Check if element is in allowed areas
        // Get user-configured allowed selectors - no fallbacks
        const allowedSelectors = config.includedSelectors ? 
            config.includedSelectors.split(',').map(s => s.trim()).filter(s => s.length > 0) : 
            [];
        logger.debug('Final allowed selectors:', allowedSelectors);
        
        logger.debug('Checking allowed selectors...');
        for (const selector of allowedSelectors) {
            if (element.closest(selector)) {
                logger.debug('Found allowed selector:', selector);
                return true;
            }
        }
        
        logger.debug('No user-configured allowed selectors matched');
        
        // IMPORTANT: DO NOT ADD FALLBACK SELECTORS HERE
        // The user has specifically requested multiple times that fallback selectors
        // should NOT be automatically applied when no admin selectors are configured.
        // If allowedSelectors.length === 0, the plugin should require explicit configuration.
        
        logger.debug('Element not in any allowed areas');
        return false;
    }
    
    /**
     * Store selection data
     */
    function storeSelectionData(text, selection) {
        state.currentSelection = {
            text: text,
            range: selection.getRangeAt(0).cloneRange(),
            element: selection.anchorNode.nodeType === Node.TEXT_NODE ? 
                selection.anchorNode.parentElement : selection.anchorNode,
            timestamp: Date.now()
        };
    }
    
    /**
     * Track selection position for tooltip placement
     */
    function trackSelectionPosition(selection) {
        try {
            const range = selection.getRangeAt(0);
            const rect = range.getBoundingClientRect();
            
            state.selectionPosition = {
                x: rect.left + (rect.width / 2),
                y: rect.top,
                width: rect.width,
                height: rect.height,
                scrollX: window.scrollX,
                scrollY: window.scrollY
            };
            
            logger.debug('Selection position calculated', {
                selectionRect: {
                    top: rect.top,
                    bottom: rect.bottom,
                    left: rect.left,
                    right: rect.right,
                    width: rect.width,
                    height: rect.height
                },
                finalPosition: state.selectionPosition,
                viewportHeight: window.innerHeight,
                scrollY: window.scrollY,
                selectionInViewportPercent: Math.round((rect.top / window.innerHeight) * 100) + '%',
                selectionBottomPercent: Math.round((rect.bottom / window.innerHeight) * 100) + '%',
                absoluteSelectionTop: rect.top + window.scrollY,
                absoluteSelectionBottom: rect.bottom + window.scrollY
            });
        } catch (error) {
            logger.error('Position tracking error:', error);
            state.selectionPosition = null;
        }
    }
    
    /**
     * Extract context around selection
     */
    function extractSelectionContext(selection) {
        const startTime = performance.now();
        let contextExtractionSuccess = false;
        
        try {
            // Validate selection object
            if (!selection || typeof selection.getRangeAt !== 'function' || selection.rangeCount === 0) {
                logger.warn('Context extraction: Invalid selection object', {
                    selectionExists: !!selection,
                    rangeCount: selection ? selection.rangeCount : 'undefined',
                    hasGetRangeAt: selection ? typeof selection.getRangeAt === 'function' : false
                });
                state.selectionContext = createFallbackContext();
                return;
            }
            
            const range = selection.getRangeAt(0);
            if (!range || !range.commonAncestorContainer) {
                logger.warn('Context extraction: Invalid range object', {
                    rangeExists: !!range,
                    hasContainer: !!(range && range.commonAncestorContainer)
                });
                state.selectionContext = createFallbackContext();
                return;
            }
            
            const container = range.commonAncestorContainer;
            
            // Performance timing: DOM traversal start
            const domTraversalStart = performance.now();
            
            // Try to find containing paragraph or block-level element with safety checks
            let paragraphElement = null;
            let currentElement = null;
            let traversalDepth = 0;
            const maxTraversalDepth = 50; // Prevent infinite loops
            
            try {
                currentElement = container.nodeType === Node.TEXT_NODE ? container.parentNode : container;
                
                // Validate starting element
                if (!currentElement || !currentElement.parentNode) {
                    logger.debug('Context extraction: Invalid starting element, using container directly');
                    currentElement = container;
                }
                
                // Traverse up DOM with safety limits
                while (currentElement && currentElement !== document.body && traversalDepth < maxTraversalDepth) {
                    traversalDepth++;
                    
                    if (currentElement.tagName && ['P', 'DIV', 'ARTICLE', 'SECTION', 'BLOCKQUOTE', 'LI', 'TD'].includes(currentElement.tagName)) {
                        paragraphElement = currentElement;
                        break;
                    }
                    
                    try {
                        currentElement = currentElement.parentNode;
                    } catch (domError) {
                        logger.debug('Context extraction: DOM traversal error', {
                            error: domError.message,
                            depth: traversalDepth
                        });
                        break;
                    }
                }
                
                if (traversalDepth >= maxTraversalDepth) {
                    logger.warn('Context extraction: Maximum traversal depth reached', {
                        depth: traversalDepth,
                        maxDepth: maxTraversalDepth
                    });
                }
                
            } catch (traversalError) {
                logger.warn('Context extraction: DOM traversal failed', {
                    error: traversalError.message,
                    depth: traversalDepth
                });
                // Continue with container as fallback
            }
            
            const domTraversalTime = performance.now() - domTraversalStart;
            
            // Extract text content with error handling
            let contextText = '';
            let paragraphText = '';
            
            try {
                if (paragraphElement && paragraphElement.textContent !== undefined) {
                    contextText = paragraphElement.textContent || '';
                    paragraphText = contextText;
                } else if (container && container.textContent !== undefined) {
                    contextText = container.textContent || '';
                    paragraphText = contextText;
                } else {
                    logger.warn('Context extraction: No text content available');
                    contextText = state.currentSelection.text || '';
                    paragraphText = contextText;
                }
            } catch (textError) {
                logger.error('Context extraction: Text extraction failed', {
                    error: textError.message,
                    hasParaElement: !!paragraphElement,
                    hasContainer: !!container
                });
                contextText = state.currentSelection.text || '';
                paragraphText = contextText;
            }
            
            // Find selection within context text with fallback
            const selectionText = state.currentSelection.text || '';
            let selectionStart = contextText.indexOf(selectionText);
            
            if (selectionStart === -1 && selectionText.length > 0) {
                // Try case-insensitive search as fallback
                const lowerContextText = contextText.toLowerCase();
                const lowerSelectionText = selectionText.toLowerCase();
                selectionStart = lowerContextText.indexOf(lowerSelectionText);
                
                if (selectionStart === -1) {
                    logger.debug('Context extraction: Selection not found in context, using basic context', {
                        contextLength: contextText.length,
                        selectionLength: selectionText.length,
                        contextPreview: contextText.substring(0, 100)
                    });
                    state.selectionContext = createFallbackContext(contextText, paragraphText);
                    return;
                }
            }
            
            // Extract context before and after with bounds checking
            const beforeStart = Math.max(0, selectionStart - config.contextLength);
            const afterEnd = Math.min(contextText.length, selectionStart + selectionText.length + config.contextLength);
            
            // Extract post title with enhanced sanitisation
            let postTitle = '';
            try {
                postTitle = (document.title || '').trim().replace(/[<>'"&]/g, '');
                if (postTitle.length > 200) {
                    postTitle = postTitle.substring(0, 200) + '...';
                }
            } catch (titleError) {
                logger.debug('Context extraction: Title extraction failed', {
                    error: titleError.message
                });
                postTitle = 'Unknown Page';
            }
            
            // Create enhanced context object with validation
            state.selectionContext = {
                before: contextText.substring(beforeStart, selectionStart) || '',
                after: contextText.substring(selectionStart + selectionText.length, afterEnd) || '',
                full: contextText.substring(beforeStart, afterEnd) || contextText,
                title: postTitle,
                paragraph: paragraphText
            };
            
            contextExtractionSuccess = true;
            
            const totalTime = performance.now() - startTime;
            
            // Enhanced performance logging
            logger.debug('Context extraction performance', {
                totalTime: Math.round(totalTime * 100) / 100,
                domTraversalTime: Math.round(domTraversalTime * 100) / 100,
                elementDetected: paragraphElement ? paragraphElement.tagName : 'TEXT_NODE',
                contextLength: contextText.length,
                paragraphLength: paragraphText.length,
                titleLength: postTitle.length,
                traversalDepth: traversalDepth,
                selectionFound: selectionStart !== -1,
                success: true
            });
            
            // Performance warning if extraction takes too long
            if (totalTime > 10) {
                logger.warn('Context extraction exceeded performance target', {
                    time: totalTime,
                    target: '10ms',
                    elementType: paragraphElement ? paragraphElement.tagName : 'TEXT_NODE',
                    contextLength: contextText.length
                });
            }
            
        } catch (error) {
            const totalTime = performance.now() - startTime;
            
            logger.error('Context extraction error:', error, {
                performanceTime: totalTime,
                errorType: error.name,
                errorMessage: error.message,
                success: false
            });
            
            // Create fallback context to maintain functionality
            state.selectionContext = createFallbackContext();
        }
    }
    
    /**
     * Create fallback context when enhanced extraction fails
     */
    function createFallbackContext(contextText = '', paragraphText = '') {
        const fallbackText = contextText || state.currentSelection.text || '';
        const fallbackTitle = document.title || 'Unknown Page';
        
        logger.debug('Creating fallback context', {
            fallbackTextLength: fallbackText.length,
            hasCurrentSelection: !!state.currentSelection.text,
            fallbackTitle: fallbackTitle
        });
        
        return {
            before: '',
            after: '',
            full: fallbackText,
            title: fallbackTitle.replace(/[<>'"&]/g, ''),
            paragraph: paragraphText || fallbackText
        };
    }
    
    /**
     * Clear previous selection
     */
    function clearPreviousSelection() {
        // Clear selection state
        state.lastSelection = state.currentSelection;
        state.currentSelection = null;
        state.selectionPosition = null;
        state.selectionContext = null;
        
        // Clear explanation cache for previous text selection when new text is selected
        // This ensures cached explanations are only kept for the current text selection
        if (state.lastSelection && state.lastSelection.text) {
            const lastTextHash = btoa(encodeURIComponent(state.lastSelection.text.toLowerCase().trim())).replace(/[^a-zA-Z0-9]/g, '').substring(0, 32);
            const keysToDelete = [];
            
            for (const [key] of state.cache) {
                if (key.startsWith('explanation_') && key.includes(lastTextHash)) {
                    keysToDelete.push(key);
                }
            }
            
            keysToDelete.forEach(key => state.cache.delete(key));
            
            if (keysToDelete.length > 0) {
                logger.debug('Cleared cached explanations for previous text selection', {
                    clearedKeys: keysToDelete.length,
                    remainingCacheSize: state.cache.size
                });
            }
        }
        
        // Clear visual highlights
        clearSelectionHighlight();
        
        // Hide any existing tooltips
        hideTooltip();
    }
    
    /**
     * Trigger explanation with mobile-aware timing
     */
    function triggerExplanationWithTiming() {
        // Clear any existing mobile delay timer
        if (state.mobileDelayTimer) {
            clearTimeout(state.mobileDelayTimer);
            state.mobileDelayTimer = null;
        }
        
        const isMobile = isMobileDevice();
        logger.debug('triggerExplanationWithTiming', { 
            isMobile,
            delay: isMobile ? config.mobileSelectionDelay : 0,
            selectedText: state.currentSelection && state.currentSelection.text
        });
        
        if (isMobile && config.mobileSelectionDelay > 0) {
            // On mobile, delay the explanation to allow user to adjust selection
            logger.debug('Mobile device detected, delaying explanation by', config.mobileSelectionDelay, 'ms');
            
            state.mobileDelayTimer = setTimeout(() => {
                // Check if selection still exists and plugin is still enabled
                const currentSelection = window.getSelection();
                const currentText = currentSelection ? currentSelection.toString().trim() : '';
                
                if (currentSelection && 
                    currentText && 
                    config.enabled && 
                    !state.isProcessing) {
                    
                    // Update stored selection with current selection after delay
                    logger.debug('Mobile delay completed, updating selection and triggering explanation');
                    logger.debug('Original selection:', state.currentSelection && state.currentSelection.text);
                    logger.debug('Final selection:', currentText);
                    
                    // Re-validate and store the current selection
                    if (validateSelection(currentText, currentSelection) && isSelectableContent(currentSelection)) {
                        // Update stored selection data with final selection
                        storeSelectionData(currentText, currentSelection);
                        trackSelectionPosition(currentSelection);
                        extractSelectionContext(currentSelection);
                        
                        requestExplanation();
                    } else {
                        logger.debug('Final selection failed validation, skipping explanation');
                    }
                } else {
                    logger.debug('Mobile delay completed but selection no longer valid, skipping explanation');
                }
                state.mobileDelayTimer = null;
            }, config.mobileSelectionDelay);
        } else {
            // On desktop or if mobile delay is disabled, trigger immediately
            logger.debug('Desktop device or no mobile delay, triggering explanation immediately');
            requestExplanation();
        }
    }
    
    /**
     * Request explanation from API
     */
    function requestExplanation() {
        logger.debug('requestExplanation called', { 
            hasSelection: !!state.currentSelection,
            selectionText: (state.currentSelection && state.currentSelection.text) || 'none'
        });
        
        if (!state.currentSelection) {
            logger.debug('No current selection, aborting request');
            return;
        }
        
        // Check if API key is configured before making request
        if (!window.explainerAjax?.settings?.api_key_configured) {
            logger.debug('API key not configured, showing setup message');
            showSetupRequiredMessage();
            return;
        }
        
        state.isProcessing = true;
        
        // Show loading state
        showLoadingState();
        
        // Get saved reading level from localStorage, but use 'standard' if slider is disabled
        const savedReadingLevel = config.showReadingLevelSlider ?
            (localStorage.getItem('explainer_reading_level') || 'standard') : 'standard';
        logger.debug('Using saved reading level for initial request', { 
            readingLevel: savedReadingLevel,
            defaultToStandard: !localStorage.getItem('explainer_reading_level'),
            localStorageValue: localStorage.getItem('explainer_reading_level')
        });
        
        // Prepare request data with context validation and fallback
        let contextData = null;
        let contextValidationSuccess = false;
        
        try {
            if (state.selectionContext) {
                // Validate context structure before sending
                const hasValidTitle = state.selectionContext.title && 
                                    typeof state.selectionContext.title === 'string' && 
                                    state.selectionContext.title.trim().length > 0;
                
                const hasValidFull = state.selectionContext.full && 
                                   typeof state.selectionContext.full === 'string';
                
                const hasValidStructure = typeof state.selectionContext.before === 'string' && 
                                        typeof state.selectionContext.after === 'string' && 
                                        typeof state.selectionContext.paragraph === 'string';
                
                if (hasValidTitle && hasValidFull && hasValidStructure) {
                    // Context appears valid, attempt serialization
                    contextData = JSON.stringify(state.selectionContext);
                    contextValidationSuccess = true;
                    logger.debug('Context validation successful', {
                        titleLength: state.selectionContext.title.length,
                        fullLength: state.selectionContext.full.length,
                        beforeLength: state.selectionContext.before.length,
                        afterLength: state.selectionContext.after.length,
                        paragraphLength: state.selectionContext.paragraph.length
                    });
                } else {
                    logger.warn('Context validation failed - invalid structure', {
                        hasTitle: hasValidTitle,
                        hasFull: hasValidFull,
                        hasStructure: hasValidStructure,
                        contextKeys: Object.keys(state.selectionContext)
                    });
                }
            } else {
                logger.debug('No selection context available for validation');
            }
        } catch (contextError) {
            logger.error('Context serialization failed', {
                error: contextError.message,
                contextExists: !!state.selectionContext,
                contextType: typeof state.selectionContext
            });
            contextData = null;
        }
        
        // If context validation failed, create fallback context
        if (!contextValidationSuccess && state.currentSelection && state.currentSelection.text) {
            try {
                const fallbackContext = createFallbackContext(state.currentSelection.text);
                contextData = JSON.stringify(fallbackContext);
                logger.debug('Using fallback context for request', {
                    fallbackTitle: fallbackContext.title,
                    fallbackTextLength: fallbackContext.full.length
                });
            } catch (fallbackError) {
                logger.error('Fallback context creation failed', {
                    error: fallbackError.message
                });
                contextData = null;
            }
        }
        
        const requestData = {
            action: 'explainer_get_explanation',
            nonce: explainerAjax.nonce,
            text: state.currentSelection.text,
            reading_level: savedReadingLevel,
            context: contextData,
            source_url: window.location.href
        };
        
        logger.debug('Making API request with data:', requestData);
        logger.debug('AJAX URL:', explainerAjax.ajaxurl);
        
        // Make Ajax request (Safari-compatible)
        utils.ajax(explainerAjax.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(requestData).toString()
        })
        .then(function(data) {
            state.isProcessing = false;
            logger.debug('API response received', { 
                success: data.success,
                hasData: !!data.data,
                cached: (data.data && data.data.cached) || false,
                tokensUsed: (data.data && data.data.tokens_used) || 0,
                responseTime: (data.data && data.data.response_time) || 0
            });
            
            if (data.success) {
                logger.debug('Explanation received successfully', {
                    explanationLength: (data.data.explanation && data.data.explanation.length) || 0,
                    cached: data.data.cached || false,
                    requestedReadingLevel: savedReadingLevel,
                    returnedReadingLevel: data.data.reading_level || 'unknown'
                });
                
                // Verify the explanation matches the requested reading level
                const returnedLevel = data.data.reading_level || 'standard';
                
                // Enhanced debugging for localStorage reading level issue
                console.log('🔧 READING LEVEL DEBUG:', {
                    requested: savedReadingLevel,
                    returned: returnedLevel,
                    matches: savedReadingLevel === returnedLevel,
                    willTriggerFix: savedReadingLevel !== 'standard' && returnedLevel === 'standard'
                });
                
                if (savedReadingLevel !== 'standard' && returnedLevel === 'standard') {
                    logger.warn('Reading level mismatch detected', {
                        requested: savedReadingLevel,
                        returned: returnedLevel,
                        willRequestCorrectLevel: true
                    });
                    
                    // Request the correct reading level
                    window.ExplainerPlugin.requestExplanation(state.currentSelection.text, savedReadingLevel)
                        .then(result => {
                            logger.debug('Correct reading level explanation received', {
                                readingLevel: savedReadingLevel,
                                explanationLength: result.explanation.length
                            });
                            showExplanation(result.explanation, result.provider, result);
                        })
                        .catch(error => {
                            logger.error('Failed to get correct reading level explanation:', error);
                            // Fallback: show the standard explanation we received
                            showExplanation(data.data.explanation, data.data.provider, data.data);
                        });
                    return;
                }
                
                showExplanation(data.data.explanation, data.data.provider, data.data);
            } else {
                // Debug: Log the full error response
                logger.error('Full error response received:', { data });
                
                // Check for API key invalid error that should disable functionality
                if (data.data && data.data.error_type === 'api_key_invalid') {
                    logger.error('API key invalid detected, calling handleApiKeyError');
                    handleApiKeyError(data.data.message || data.data.error);
                    return;
                }
                
                // Check if error_type is in the root data object instead
                if (data.error_type === 'api_key_invalid') {
                    logger.error('API key invalid detected at root level, calling handleApiKeyError');
                    handleApiKeyError(data.message || data.error);
                    return;
                }
                
                // Handle different error response formats
                let errorMessage = getLocalisedString('failed_to_get_explanation');
                if (data.data && data.data.message) {
                    errorMessage = data.data.message;
                } else if (data.data && data.data.error) {
                    errorMessage = data.data.error;
                } else if (data.message) {
                    errorMessage = data.message;
                } else if (typeof data === 'string') {
                    errorMessage = data;
                }
                logger.error('Regular API error occurred', { errorMessage });
                showError(errorMessage);
            }
        })
        .catch(function(error) {
            state.isProcessing = false;
            
            // Enhanced network error logging and classification
            let errorType = 'unknown';
            let errorDetails = {};
            
            if (error instanceof TypeError && error.message.includes('fetch')) {
                errorType = 'network_failure';
                errorDetails.likely_cause = 'Network connectivity issues or CORS';
            } else if (error instanceof SyntaxError) {
                errorType = 'response_parsing_failure';
                errorDetails.likely_cause = 'Invalid JSON response from server';
            } else if (error.name === 'AbortError') {
                errorType = 'request_timeout';
                errorDetails.likely_cause = 'Request was aborted or timed out';
            } else if (error.status) {
                errorType = 'http_error';
                errorDetails.http_status = error.status;
                errorDetails.status_text = error.statusText || 'Unknown';
            }
            
            logger.error('API request failed with detailed analysis', {
                error_type: errorType,
                error_message: error.message || error,
                error_name: error.name,
                error_stack: error.stack ? error.stack.split('\n').slice(0, 3) : 'No stack trace',
                details: errorDetails,
                selection_text_length: state.currentSelection ? state.currentSelection.text.length : 0,
                had_context: !!contextData,
                request_url: explainerAjax.ajaxurl
            });
            
            // Determine appropriate user-facing error message
            let userMessage = getLocalisedString('connection_error');
            if (errorType === 'network_failure') {
                userMessage = 'Unable to connect to the explanation service. Please check your internet connection and try again.';
            } else if (errorType === 'request_timeout') {
                userMessage = 'Request timed out. Please try again with a shorter text selection.';
            } else if (errorType === 'http_error' && errorDetails.http_status >= 500) {
                userMessage = 'The explanation service is temporarily unavailable. Please try again in a few moments.';
            }
            
            showError(userMessage);
        });
    }
    
    /**
     * Show loading state with fallback for tooltip failures
     */
    function showLoadingState() {
        try {
            if (window.ExplainerPlugin && window.ExplainerPlugin.showTooltip) {
                window.ExplainerPlugin.showTooltip(getLocalisedString('loading_explanation'), state.selectionPosition, 'loading');
            } else {
                // Fallback: log issue but continue processing
                logger.warn('Tooltip system unavailable during loading state', {
                    hasExplainerPlugin: !!window.ExplainerPlugin,
                    hasShowTooltip: !!(window.ExplainerPlugin && window.ExplainerPlugin.showTooltip),
                    selectionPosition: state.selectionPosition
                });
            }
        } catch (tooltipError) {
            // Tooltip system failed, log error but continue
            logger.error('Tooltip system failed during loading state', {
                error: tooltipError.message,
                hasSelection: !!state.currentSelection,
                position: state.selectionPosition
            });
            // Continue with request processing despite tooltip failure
        }
    }
    
    /**
     * Show explanation in tooltip with enhanced accessibility
     */
    function showExplanation(explanation, provider = null, responseData = null) {
        // Remove cache/API indicators - just use the explanation directly
        const cached = (responseData && responseData.cached) || false;
        const explanationWithIndicator = explanation;
        
        // Prepare footer options from admin settings
        const footerOptions = {
            showDisclaimer: config.showDisclaimer || false,
            showProvider: config.showProvider || false,
            showReadingLevelSlider: config.showReadingLevelSlider,
            provider: provider || config.apiProvider || 'openai',
            readingLevel: responseData && responseData.reading_level ? responseData.reading_level : 'standard'
        };
        
        if (window.ExplainerPlugin.updateTooltipContent) {
            // Update existing loading tooltip
            const options = { ...footerOptions, selectedText: state.currentSelection && state.currentSelection.text };
            window.ExplainerPlugin.updateTooltipContent(explanationWithIndicator, 'explanation', options);
        } else if (window.ExplainerPlugin.showTooltip) {
            // Show new tooltip
            const options = { ...footerOptions, selectedText: state.currentSelection && state.currentSelection.text };
            window.ExplainerPlugin.showTooltip(explanationWithIndicator, state.selectionPosition, 'explanation', options);
        }
        
        // Enhanced screen reader announcement with summary
        const summary = explanation.length > 100 ? 
            explanation.substring(0, 100) + '... (full explanation in tooltip)' : 
            explanation;
        announceToScreenReader('Explanation loaded: ' + summary, 'assertive');
        
        // Add landmark role for screen readers
        const tooltip = document.querySelector('.explainer-tooltip.visible');
        if (tooltip) {
            tooltip.setAttribute('role', 'region');
            tooltip.setAttribute('aria-labelledby', 'explainer-tooltip-title');
            tooltip.setAttribute('aria-describedby', 'explainer-tooltip-content');
        }
        
        // Dispatch custom event for explanation loaded
        const explanationLoadedEventData = {
            selectedText: (state.currentSelection && state.currentSelection.text) || '',
            explanation: explanation,
            provider: provider || config.apiProvider || 'openai',
            position: state.selectionPosition,
            timestamp: Date.now(),
            cached: (responseData && responseData.cached) || false,
            apiMetadata: {
                tokensUsed: (responseData && responseData.tokens_used) || null,
                responseTime: (responseData && responseData.response_time) || null,
                model: (responseData && responseData.model) || null,
                cost: (responseData && responseData.cost) || null
            },
            metadata: {
                explanationLength: explanation.length,
                wordCount: explanation.split(/\s+/).length,
                hasFooter: footerOptions.showDisclaimer || footerOptions.showProvider,
                selectionLength: (state.currentSelection && state.currentSelection.text && state.currentSelection.text.length) || 0,
                hasContext: !!state.selectionContext
            }
        };
        document.dispatchEvent(new CustomEvent('explainerExplanationLoaded', { detail: explanationLoadedEventData }));
        
        logger.debug('Explanation loaded event dispatched', { 
            selectedText: explanationLoadedEventData.selectedText,
            explanationLength: explanationLoadedEventData.explanation.length,
            provider: explanationLoadedEventData.provider
        });
    }
    
    /**
     * Show error message with comprehensive fallback handling
     */
    function showError(message) {
        try {
            if (window.ExplainerPlugin && window.ExplainerPlugin.updateTooltipContent) {
                // Update existing loading tooltip
                window.ExplainerPlugin.updateTooltipContent(message, 'error');
            } else if (window.ExplainerPlugin && window.ExplainerPlugin.showTooltip) {
                // Show new tooltip
                window.ExplainerPlugin.showTooltip(message, state.selectionPosition, 'error');
            } else {
                // Tooltip system unavailable - log error details
                logger.error('Tooltip system unavailable for error display', {
                    errorMessage: message,
                    hasExplainerPlugin: !!window.ExplainerPlugin,
                    hasUpdateTooltip: !!(window.ExplainerPlugin && window.ExplainerPlugin.updateTooltipContent),
                    hasShowTooltip: !!(window.ExplainerPlugin && window.ExplainerPlugin.showTooltip),
                    fallbackAction: 'Error logged but not displayed to user'
                });
                
                // Fallback: announce error to screen reader
                announceToScreenReader('Error: ' + message, 'assertive');
            }
        } catch (errorDisplayError) {
            // Error display system failed completely
            logger.error('Complete error display system failure', {
                originalError: message,
                displayError: errorDisplayError.message,
                fallbackAction: 'Screen reader announcement attempted'
            });
            
            // Final fallback: try screen reader announcement
            try {
                announceToScreenReader('An error occurred: ' + message, 'assertive');
            } catch (screenReaderError) {
                // Log complete failure
                logger.error('All error display methods failed', {
                    originalError: message,
                    displayError: errorDisplayError.message,
                    screenReaderError: screenReaderError.message
                });
            }
        }
    }
    
    /**
     * Show setup required message
     */
    function showSetupRequiredMessage() {
        const setupMessage = 'Please configure your API key in plugin settings to use AI explanations.';
        if (window.ExplainerPlugin.showTooltip) {
            window.ExplainerPlugin.showTooltip(setupMessage, state.selectionPosition, 'error', {
                selectedText: state.currentSelection ? state.currentSelection.text : 'Selected text'
            });
        }
    }
    
    /**
     * Show validation message
     */
    function showValidationMessage(message) {
        logger.debug('Validation:', message);
        
        // Show a temporary error tooltip at the current selection
        const selection = window.getSelection();
        if (selection.rangeCount > 0) {
            const range = selection.getRangeAt(0);
            const rect = range.getBoundingClientRect();
            
            // Create error tooltip
            const errorTooltip = document.createElement('div');
            errorTooltip.className = 'explainer-validation-error';
            errorTooltip.textContent = message;
            errorTooltip.style.cssText = `
                position: fixed;
                top: ${rect.top - 40}px;
                left: ${rect.left}px;
                background: #d63638;
                color: white;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 13px;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                z-index: 999999;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                max-width: 300px;
                word-wrap: break-word;
                pointer-events: none;
            `;
            
            document.body.appendChild(errorTooltip);
            
            // Remove after 3 seconds
            setTimeout(() => {
                if (errorTooltip.parentNode) {
                    errorTooltip.parentNode.removeChild(errorTooltip);
                }
            }, 3000);
        }
    }
    
    /**
     * Hide tooltip
     */
    function hideTooltip() {
        if (window.ExplainerPlugin.hideTooltip) {
            window.ExplainerPlugin.hideTooltip();
        }
    }
    
    /**
     * Handle API key invalid error by disabling functionality
     */
    function handleApiKeyError(errorMessage) {
        logger.error('🚨 handleApiKeyError called!', { errorMessage });
        
        // Disable functionality immediately
        config.enabled = false;
        logger.error('Config disabled, now:', config.enabled);
        
        // Hide the toggle container completely - no refresh needed!
        if (elements.toggleContainer) {
            logger.error('Toggle container found, hiding it now!');
            elements.toggleContainer.style.setProperty('display', 'none', 'important');
            logger.error('Toggle container display style set to:', elements.toggleContainer.style.display);
        } else {
            logger.error('❌ Toggle container NOT found in elements!', elements);
        }
        
        // Show generic error message to end user
        const userMessage = 'AI Explainer is temporarily unavailable. Please try again later.';
        showError(userMessage);
        
        // Announce to screen reader
        announceToScreenReader('AI Explainer disabled due to API key issue', 'assertive');
        
        // Save disabled state (user can re-enable manually)
        saveUserPreferences();
        
        logger.user('Plugin disabled due to API key error', {
            originalError: errorMessage,
            userCanReEnable: true,
            toggleHidden: true
        });
    }
    
    /**
     * Toggle plugin enabled state
     */
    function togglePlugin() {
        logger.debug('togglePlugin called, current state:', config.enabled);
        config.enabled = !config.enabled;
        logger.debug('togglePlugin new state:', config.enabled);
        updateToggleButton();
        saveUserPreferences();
        
        if (!config.enabled) {
            clearPreviousSelection();
        }
        
        // Announce state change to screen reader
        announceToScreenReader(config.enabled ? 
            getLocalisedString('ai_explainer_enabled') : 
            getLocalisedString('ai_explainer_disabled')
        );
        
        logger.debug('togglePlugin completed, final state:', config.enabled);
    }
    
    /**
     * Handle keyboard events with enhanced accessibility support
     */
    function handleKeyDown(event) {
        // Escape key dismisses tooltip
        if (event.key === 'Escape') {
            clearPreviousSelection();
            announceToScreenReader('Explanation closed', 'assertive');
            // Return focus to last focused element
            if (state.lastFocusedElement && typeof state.lastFocusedElement.focus === 'function') {
                state.lastFocusedElement.focus();
            }
        }
        
        // F1 key activates explainer on selected text
        if (event.key === 'F1' && event.altKey) {
            event.preventDefault();
            const selection = window.getSelection();
            if (selection && selection.toString().trim()) {
                announceToScreenReader('Getting explanation for selected text', 'assertive');
                handleSelection(event);
            } else {
                announceToScreenReader('Please select text first to get an explanation', 'assertive');
            }
        }
        
        // Ctrl+Shift+E toggles plugin (alternative to button)
        if (event.key === 'E' && event.ctrlKey && event.shiftKey) {
            event.preventDefault();
            togglePlugin();
            const status = config.enabled ? 'enabled' : 'disabled';
            announceToScreenReader(`AI Explainer ${status}`, 'assertive');
        }
        
        // Tab key navigation
        if (event.key === 'Tab') {
            handleTabNavigation(event);
        }
        
        // Arrow keys for tooltip navigation when focused
        if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
            handleArrowNavigation(event);
        }
    }
    
    /**
     * Handle toggle button keyboard events
     */
    function handleToggleKeydown(event) {
        // Enter or Space activates toggle
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            togglePlugin();
        }
    }
    
    /**
     * Handle tab navigation
     */
    function handleTabNavigation(event) {
        const tooltip = document.querySelector('.explainer-tooltip.visible');
        if (tooltip) {
            const focusableElements = tooltip.querySelectorAll('button, a, input, select, textarea, [tabindex]:not([tabindex="-1"])');
            
            if (focusableElements.length > 0) {
                const firstElement = focusableElements[0];
                const lastElement = focusableElements[focusableElements.length - 1];
                
                if (event.shiftKey) {
                    // Shift + Tab
                    if (document.activeElement === firstElement) {
                        event.preventDefault();
                        lastElement.focus();
                    }
                } else {
                    // Tab
                    if (document.activeElement === lastElement) {
                        event.preventDefault();
                        firstElement.focus();
                    }
                }
            }
        }
    }
    
    /**
     * Handle focus events
     */
    function handleFocusIn(event) {
        // Track focus for accessibility
        state.lastFocusedElement = event.target;
    }
    
    /**
     * Handle focus out events
     */
    function handleFocusOut(event) {
        // Handle focus management
        setTimeout(() => {
            const tooltip = document.querySelector('.explainer-tooltip.visible');
            if (tooltip && !tooltip.contains(document.activeElement)) {
                // Focus moved outside tooltip
                const nextElement = event.relatedTarget;
                if (!nextElement || !tooltip.contains(nextElement)) {
                    // Consider closing tooltip if focus completely left
                    if (!elements.toggleButton.contains(document.activeElement)) {
                        clearPreviousSelection();
                    }
                }
            }
        }, 0);
    }
    
    /**
     * Handle outside clicks
     */
    function handleOutsideClick(event) {
        // Clear selection if clicking outside
        if (event.target && event.target.closest && !event.target.closest('.explainer-tooltip, .explainer-toggle, .explainer-help-icon')) {
            clearPreviousSelection();
        }
    }
    
    /**
     * Handle window resize
     */
    function handleResize() {
        // Update tooltip position if visible
        if (state.currentSelection && state.selectionPosition) {
            hideTooltip();
        }
    }
    
    /**
     * Handle onboarding panel hidden event - close tooltip when onboarding closes
     */
    function handleOnboardingPanelHidden(event) {
        logger.debug('Onboarding panel hidden event received, closing tooltip');
        
        // Close any open tooltip
        if (elements.tooltip) {
            hideTooltip();
        }
        
        // Clear any current selection state
        clearPreviousSelection();
        
        logger.debug('Tooltip closed due to onboarding panel closure');
    }
    
    /**
     * Utility functions
     */
    
    /**
     * Count words in text
     */
    function countWords(text) {
        return text.trim().split(/\s+/).filter(word => word.length > 0).length;
    }
    
    /**
     * Check if text contains only stop words
     * Uses smart filtering: only filters very short selections to avoid false positives
     */
    function isStopWordOnly(text) {
        if (!config.enableStopWordFiltering) {
            return false;
        }
        
        const cleanText = text.toLowerCase().trim();
        const words = cleanText.split(/\s+/).filter(word => word.length > 0);
        
        // Smart filtering: only filter single words or very short selections (1-2 words)
        // This reduces false positives for meaningful phrases like "the end" or "the beginning"
        if (words.length > 2) {
            return false;
        }
        
        // Check if all words in the selection are stop words
        return words.length > 0 && words.every(word => config.stopWords.includes(word));
    }
    
    /**
     * Debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    /**
     * Setup selection highlighting
     */
    function setupSelectionHighlighting() {
        // Add CSS for selection highlighting
        const style = document.createElement('style');
        style.textContent = `
            .explainer-selection-highlight {
                background-color: rgba(255, 255, 0, 0.3);
                border-radius: 2px;
            }
        `;
        document.head.appendChild(style);
    }
    
    /**
     * Clear selection highlight
     */
    function clearSelectionHighlight() {
        const highlighted = document.querySelectorAll('.explainer-selection-highlight');
        highlighted.forEach(el => {
            el.classList.remove('explainer-selection-highlight');
        });
    }
    
    /**
     * Load user preferences
     */
    function loadUserPreferences() {
        try {
            const saved = localStorage.getItem('explainer-plugin-enabled');
            logger.debug('Loading user preferences - localStorage value:', saved);
            logger.debug('Config enabled before loading preferences:', config.enabled);
            
            if (saved !== null) {
                config.enabled = saved === 'true';
                logger.debug('Restored saved state from localStorage:', config.enabled);
            } else {
                logger.debug('No saved state found, keeping server default:', config.enabled);
            }
            
            logger.debug('Final enabled state after loading preferences:', config.enabled);
        } catch (error) {
            logger.warn('Could not load preferences:', error);
        }
    }
    
    /**
     * Save user preferences
     */
    function saveUserPreferences() {
        try {
            localStorage.setItem('explainer-plugin-enabled', config.enabled);
            logger.debug('Saved state to localStorage:', config.enabled);
        } catch (error) {
            logger.warn('Could not save preferences:', error);
        }
    }
    
    /**
     * Announce messages to screen readers with enhanced support
     */
    function announceToScreenReader(message, priority = 'polite') {
        // Create or reuse announcement container
        let announcementContainer = document.getElementById('explainer-announcements');
        if (!announcementContainer) {
            announcementContainer = document.createElement('div');
            announcementContainer.id = 'explainer-announcements';
            announcementContainer.className = 'explainer-sr-only';
            announcementContainer.setAttribute('aria-live', 'polite');
            announcementContainer.setAttribute('aria-atomic', 'true');
            document.body.appendChild(announcementContainer);
        }
        
        // Update priority if needed
        if (priority === 'assertive') {
            announcementContainer.setAttribute('aria-live', 'assertive');
        }
        
        // Clear previous content and add new message
        announcementContainer.textContent = '';
        setTimeout(() => {
            announcementContainer.textContent = message;
        }, 100);
        
        // Reset to polite after assertive announcements
        if (priority === 'assertive') {
            setTimeout(() => {
                announcementContainer.setAttribute('aria-live', 'polite');
            }, 1000);
        }
    }
    
    
    /**
     * Manage focus for accessibility with enhanced features
     */
    function manageFocus(element, options = {}) {
        if (!element || typeof element.focus !== 'function') {
            return false;
        }
        
        // Store current focus for restoration
        if (!options.skipStore) {
            state.lastFocusedElement = document.activeElement;
        }
        
        // Focus the element
        try {
            element.focus({ preventScroll: options.preventScroll || false });
            
            // Ensure focus is visible
            if (element.scrollIntoView && !options.preventScroll) {
                element.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                    inline: 'nearest'
                });
            }
            
            // Add temporary focus ring for better visibility
            if (options.enhancedFocus) {
                element.classList.add('explainer-enhanced-focus');
                setTimeout(() => {
                    element.classList.remove('explainer-enhanced-focus');
                }, 2000);
            }
            
            return true;
        } catch (error) {
            logger.warn('Focus management error:', error);
            return false;
        }
    }
    
    /**
     * Check if element is keyboard accessible
     */
    function isKeyboardAccessible(element) {
        const tabIndex = element.getAttribute('tabindex');
        return tabIndex !== '-1' && 
               !element.disabled && 
               element.offsetWidth > 0 && 
               element.offsetHeight > 0;
    }
    
    /**
     * Handle arrow key navigation for enhanced accessibility
     */
    function handleArrowNavigation(event) {
        const tooltip = document.querySelector('.explainer-tooltip.visible');
        if (!tooltip || !tooltip.contains(document.activeElement)) {
            return;
        }
        
        const focusableElements = tooltip.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        
        if (focusableElements.length <= 1) {
            return;
        }
        
        const currentIndex = Array.from(focusableElements).indexOf(document.activeElement);
        let newIndex = currentIndex;
        
        switch (event.key) {
            case 'ArrowDown':
            case 'ArrowRight':
                event.preventDefault();
                newIndex = (currentIndex + 1) % focusableElements.length;
                break;
            case 'ArrowUp':
            case 'ArrowLeft':
                event.preventDefault();
                newIndex = currentIndex === 0 ? focusableElements.length - 1 : currentIndex - 1;
                break;
        }
        
        if (newIndex !== currentIndex) {
            manageFocus(focusableElements[newIndex], { skipStore: true });
        }
    }
    
    
    /**
     * Enhance colour contrast validation
     */
    function validateColourContrast() {
        // Check if current page has sufficient contrast for our elements
        const computedStyle = window.getComputedStyle(document.body);
        const backgroundColor = computedStyle.backgroundColor;
        const textColor = computedStyle.color;
        
        // Basic contrast check - in a full implementation, this would use
        // proper contrast ratio calculations
        const isDarkBackground = backgroundColor.includes('rgb(0') || 
                                backgroundColor.includes('rgb(1') || 
                                backgroundColor.includes('rgb(2');
        
        if (isDarkBackground) {
            document.documentElement.style.setProperty(
                '--explainer-primary-color', 
                '#4a9eff'
            );
        }
    }
    
    /**
     * Add comprehensive ARIA labels and descriptions
     */
    function enhanceARIASupport() {
        // Enhanced toggle button ARIA
        if (elements.toggleButton) {
            elements.toggleButton.setAttribute('aria-expanded', 'false');
            elements.toggleButton.setAttribute('aria-haspopup', 'dialog');
            elements.toggleButton.setAttribute('aria-controls', 'explainer-tooltip-region');
            
            // Add comprehensive description
            const description = document.getElementById('explainer-description');
            if (description) {
                description.textContent = 'Activate this button to enable AI text explanations. When enabled, select any text to receive an AI-generated explanation in a popup tooltip.';
            }
        }
        
        // Add landmark roles for better navigation
        const mainContent = document.querySelector('main, #main, #content, article, .content');
        if (mainContent && !mainContent.getAttribute('role')) {
            mainContent.setAttribute('role', 'main');
        }
    }
    
    // Conflict resolution for common plugins
    function resolvePluginConflicts() {
        // Save original functions that might be overridden
        const originalGetSelection = window.getSelection;
        
        // Protect against jQuery conflicts
        if (window.jQuery) {
            const $ = window.jQuery;
            // Ensure our event handlers don't get overridden
            $(document).on('ready', function() {
                // Re-initialize if needed
                if (!elements.toggleButton) {
                    init();
                }
            });
        }
        
        // Protect against selection library conflicts
        if (window.rangy) {
            // Use native selection if rangy is present
            window.getSelection = originalGetSelection;
        }
        
        // Elementor compatibility
        if (window.elementorFrontend) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function() {
                // Re-initialize after Elementor loads
                utils.requestIdleCallback(init);
            });
        }
        
        // Gutenberg compatibility
        if (window.wp && window.wp.domReady) {
            window.wp.domReady(function() {
                // Re-initialize after Gutenberg loads
                utils.requestIdleCallback(init);
            });
        }
    }

    /**
     * Reinitialise the plugin for dynamically added content
     * Call this after adding new elements with allowed classes to the DOM
     */
    function reinitialise() {
        logger.debug('Reinitialising plugin for dynamically added content');
        
        // Clear any existing selection
        clearPreviousSelection();
        
        // The plugin doesn't need to re-attach event listeners since they're attached to the document
        // But we can refresh the selection system state
        updateToggleButton();
        
        logger.debug('Plugin reinitialised successfully');
    }

    // Public API
    window.ExplainerPlugin = {
        init: init,
        toggle: togglePlugin,
        config: config,
        state: state,
        clearSelection: clearPreviousSelection,
        resolveConflicts: resolvePluginConflicts,
        reinitialise: reinitialise
    };
    
        // Check if tooltip functions are available
    function checkTooltipAvailability() {
        logger.debug('Checking tooltip availability');
        logger.debug('ExplainerPlugin namespace:', window.ExplainerPlugin);
        logger.debug('showTooltip function:', window.ExplainerPlugin && window.ExplainerPlugin.showTooltip);
        
        if (!(window.ExplainerPlugin && window.ExplainerPlugin.showTooltip)) {
            logger.debug('Tooltip functions not found, loading dynamically...');
            loadTooltipScript();
        } else {
            logger.debug('Tooltip functions are available!');
        }
    }
    
    // Load tooltip script dynamically as fallback
    function loadTooltipScript() {
        if (window.tooltipScriptLoading) return; // Prevent multiple loads
        window.tooltipScriptLoading = true;
        
        logger.debug('Loading tooltip script dynamically');
        
        const scriptUrl = (explainerAjax.settings && explainerAjax.settings.tooltip_url) || '/wp-content/plugins/explainer-plugin/assets/js/tooltip-test.js';
        logger.debug('Script URL:', scriptUrl);
        
        const script = document.createElement('script');
        script.src = scriptUrl;
        script.onload = function() {
            logger.debug('Tooltip script loaded successfully');
            logger.debug('Checking if functions are now available:', !!(window.ExplainerPlugin && window.ExplainerPlugin.showTooltip));
            window.tooltipScriptLoading = false;
        };
        script.onerror = function(error) {
            logger.error('Failed to load tooltip script', { error: error, scriptUrl: scriptUrl });
            window.tooltipScriptLoading = false;
        };
        
        logger.debug('Adding script to head');
        document.head.appendChild(script);
    }

    // Public API - expose key functions for external use
    window.ExplainerPlugin.requestExplanation = function(selectedText, readingLevel = 'standard') {
        return new Promise((resolve, reject) => {
            if (!selectedText || typeof selectedText !== 'string') {
                reject(new Error('Invalid selected text provided'));
                return;
            }
            
            // Generate cache key for this specific request
            const textHash = btoa(encodeURIComponent(selectedText.toLowerCase().trim())).replace(/[^a-zA-Z0-9]/g, '').substring(0, 32);
            const cacheKey = `explanation_${textHash}_${readingLevel}`;
            
            // Check client-side cache first
            if (state.cache.has(cacheKey)) {
                const cachedResult = state.cache.get(cacheKey);
                logger.debug('Client cache HIT for requestExplanation', {
                    selectedText: selectedText.substring(0, 50) + '...',
                    readingLevel: readingLevel,
                    cacheKey: cacheKey
                });
                
                // Return cached result immediately
                setTimeout(() => resolve(cachedResult), 0);
                return;
            }
            
            logger.debug('Client cache MISS - making API request', {
                selectedText: selectedText.substring(0, 50) + '...',
                readingLevel: readingLevel,
                cacheKey: cacheKey
            });
            
            // Prepare request data with reading level
            const requestData = {
                action: 'explainer_get_explanation',
                nonce: explainerAjax.nonce,
                text: selectedText,
                reading_level: readingLevel,
                context: state.selectionContext ? JSON.stringify(state.selectionContext) : null,
                source_url: window.location.href
            };
            
            // Make Ajax request (Safari-compatible)
            utils.ajax(explainerAjax.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(requestData).toString()
            })
            .then(function(data) {
                logger.debug('Public API response received', {
                    success: data.success,
                    readingLevel: readingLevel,
                    cached: (data.data && data.data.cached) || false,
                    hasCachedExplanations: !!(data.data && data.data.cached_explanations)
                });
                
                if (data.success) {
                    // Prepare response data
                    const result = {
                        success: true,
                        explanation: data.data.explanation,
                        provider: data.data.provider,
                        cached: data.data.cached,
                        tokens_used: data.data.tokens_used,
                        response_time: data.data.response_time,
                        showDisclaimer: config.showDisclaimer || false,
                        showProvider: config.showProvider || false,
                        showReadingLevelSlider: config.showReadingLevelSlider
                    };
                    
                    // Cache the main result
                    state.cache.set(cacheKey, result);
                    
                    // Cache any additional explanations that came with the response
                    if (data.data.cached_explanations && typeof data.data.cached_explanations === 'object') {
                        let cachedCount = 0;
                        for (const [level, explanation] of Object.entries(data.data.cached_explanations)) {
                            const levelCacheKey = `explanation_${textHash}_${level}`;
                            const levelResult = {
                                success: true,
                                explanation: explanation,
                                provider: data.data.provider,
                                cached: true,
                                tokens_used: 0,
                                response_time: 0,
                                showDisclaimer: config.showDisclaimer || false,
                                showProvider: config.showProvider || false
                            };
                            state.cache.set(levelCacheKey, levelResult);
                            cachedCount++;
                        }
                        
                        logger.debug('Bulk cached explanations stored', {
                            selectedText: selectedText.substring(0, 50) + '...',
                            cachedLevels: Object.keys(data.data.cached_explanations),
                            totalCached: cachedCount
                        });
                    }
                    
                    // Implement cache size management to prevent memory issues
                    const maxCacheSize = 50; // Maximum 50 cached explanations
                    if (state.cache.size > maxCacheSize) {
                        let deleteCount = 0;
                        const targetSize = Math.floor(maxCacheSize * 0.8); // Remove 20% when limit exceeded
                        
                        for (const [key] of state.cache) {
                            if (key.startsWith('explanation_')) {
                                state.cache.delete(key);
                                deleteCount++;
                                if (state.cache.size <= targetSize) break;
                            }
                        }
                        
                        logger.debug('Cache size management triggered', {
                            deletedEntries: deleteCount,
                            newCacheSize: state.cache.size,
                            maxCacheSize: maxCacheSize
                        });
                    }
                    
                    resolve(result);
                } else {
                    // Handle error response
                    let errorMessage = getLocalisedString('failed_to_get_explanation');
                    if (data.data && data.data.message) {
                        errorMessage = data.data.message;
                    } else if (data.data && data.data.error) {
                        errorMessage = data.data.error;
                    } else if (data.message) {
                        errorMessage = data.message;
                    }
                    
                    resolve({
                        success: false,
                        error: errorMessage
                    });
                }
            })
            .catch(function(error) {
                logger.error('Public API request failed', {
                    error: error.message || error,
                    readingLevel: readingLevel
                });
                reject(error);
            });
        });
    };
    
    // Expose state for position access (needed by tooltip)
    window.ExplainerPlugin.state = state;

// Auto-initialize when DOM is ready
    logger.debug('Auto-initialization starting, readyState:', document.readyState);
    
    if (document.readyState === 'loading') {
        logger.debug('DOM still loading, waiting for DOMContentLoaded...');
        document.addEventListener('DOMContentLoaded', function() {
            logger.debug('DOMContentLoaded fired, initializing...');
            resolvePluginConflicts();
            init();
            checkTooltipAvailability();
        });
    } else {
        logger.debug('DOM already ready, initializing immediately...');
        resolvePluginConflicts();
        init();
        checkTooltipAvailability();
    }
    
})();