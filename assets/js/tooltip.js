/**
 * WP AI Explainer - Tooltip System
 * Handles tooltip display, positioning, and interactions
 */

(function() {
    'use strict';
    
    // Extend the main plugin namespace
    window.ExplainerPlugin = window.ExplainerPlugin || {};
    
    // Tooltip configuration
    const tooltipConfig = {
        maxWidth: 300,
        minWidth: 200,
        offset: 30, // Increased offset for better spacing
        animationDuration: 300,
        autoCloseDelay: 10000, // 10 seconds
        zIndex: 999998
    };
    
    // Tooltip state
    let currentTooltip = null;
    let autoCloseTimer = null;
    let isTooltipVisible = false;
    let localizedStrings = null;
    
    /**
     * Load localized strings using shared utility
     */
    function loadLocalizedStrings() {
        if (window.ExplainerPlugin && window.ExplainerPlugin.Utils) {
            return window.ExplainerPlugin.Utils.loadLocalizedStrings().then(strings => {
                localizedStrings = strings;
                return strings;
            });
        }
        
        // Fallback implementation (if shared utils not available)
        if (localizedStrings) {
            return Promise.resolve(localizedStrings);
        }
        
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.explainerAjax?.ajaxurl || '/wp-admin/admin-ajax.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success && response.data.strings) {
                            localizedStrings = response.data.strings;
                            resolve(localizedStrings);
                        } else {
                            // Fallback to English
                            localizedStrings = {
                                explanation: 'Explanation',
                                loading: 'Loading...',
                                error: 'Error',
                                disclaimer: 'AI-generated content may not always be accurate',
                                powered_by: 'Powered by',
                                failed_to_get_explanation: 'Failed to get explanation',
                                connection_error: 'Connection error. Please try again.',
                                loading_explanation: 'Loading explanation...'
                            };
                            resolve(localizedStrings);
                        }
                    } catch (e) {
                        // Fallback to English
                        localizedStrings = {
                            explanation: 'Explanation',
                            loading: 'Loading...',
                            error: 'Error',
                            disclaimer: 'AI-generated content may not always be accurate',
                            powered_by: 'Powered by',
                            failed_to_get_explanation: 'Failed to get explanation',
                            connection_error: 'Connection error. Please try again.',
                            loading_explanation: 'Loading explanation...'
                        };
                        resolve(localizedStrings);
                    }
                } else {
                    reject(new Error('Failed to load localized strings'));
                }
            };
            
            xhr.onerror = function() {
                reject(new Error('Network error'));
            };
            
            const data = 'action=explainer_get_localized_strings' + 
                        (window.explainerAjax?.nonce ? '&nonce=' + encodeURIComponent(window.explainerAjax.nonce) : '');
            xhr.send(data);
        });
    }
    
    /**
     * Get localized string using shared utility
     */
    function getLocalizedString(key, fallback = '') {
        if (window.ExplainerPlugin && window.ExplainerPlugin.Utils) {
            return window.ExplainerPlugin.Utils.getLocalizedString(key, fallback);
        }
        
        // Fallback implementation
        if (localizedStrings && localizedStrings[key]) {
            return localizedStrings[key];
        }
        return fallback || key;
    }
    
    // Debug logging using central logger
    const logger = {
        debug: (message, context = {}) => {
            if (window.ExplainerLogger) {
                window.ExplainerLogger.debug(message, context, 'Tooltip');
            }
        },
        info: (message, context = {}) => {
            if (window.ExplainerLogger) {
                window.ExplainerLogger.info(message, context, 'Tooltip');
            }
        },
        warn: (message, context = {}) => {
            if (window.ExplainerLogger) {
                window.ExplainerLogger.warning(message, context, 'Tooltip');
            }
        },
        error: (message, context = {}) => {
            if (window.ExplainerLogger) {
                window.ExplainerLogger.error(message, context, 'Tooltip');
            }
        }
    };


    /**
     * Show tooltip with content
     */
    function showTooltip(content, position, type = 'explanation', options = {}) {
        logger.debug('showTooltip called', { content: content?.substring(0, 50) + '...', position, type, options });
        
        // Clear any existing tooltip
        hideTooltip();
        
        // Create tooltip element
        currentTooltip = createTooltipElement(content, type, options);
        logger.debug('Tooltip element created', { hasElement: !!currentTooltip, className: currentTooltip?.className });
        
        // Add to DOM (starts invisible due to CSS opacity: 0)
        document.body.appendChild(currentTooltip);
        logger.debug('Tooltip added to DOM', { parentNode: currentTooltip.parentNode?.tagName });
        
        // Position tooltip while invisible
        positionTooltip(currentTooltip, position);
        logger.debug('Tooltip positioned', { left: currentTooltip.style.left, top: currentTooltip.style.top });
        
        
        // Force browser to process position changes before making visible
        // This ensures tooltip appears at final position without sliding
        currentTooltip.offsetHeight; // Force reflow
        
        // Debug: Check if classes are still there after reflow
        logger.debug('After reflow - classes check', { 
            classes: currentTooltip.className,
            hasAbove: currentTooltip.classList.contains('above'),
            hasBelow: currentTooltip.classList.contains('below')
        });
        
        // Show tooltip with animation
        setTimeout(() => {
            // Debug: Check classes before adding visible
            logger.debug('Before adding visible class', { 
                classes: currentTooltip.className,
                hasAbove: currentTooltip.classList.contains('above'),
                hasBelow: currentTooltip.classList.contains('below')
            });
            
            currentTooltip.classList.add('visible');
            isTooltipVisible = true;
            
            // Debug: Check final state
            logger.debug('Final tooltip state', { 
                classes: currentTooltip.className,
                hasVisible: currentTooltip.classList.contains('visible'),
                hasAbove: currentTooltip.classList.contains('above'),
                hasBelow: currentTooltip.classList.contains('below')
            });
        }, 10);
        
        // Don't set auto-close timer - let user control when to close
        
        // Add event listeners
        attachTooltipEventListeners();
        
        
        // Focus management for accessibility
        if (type !== 'loading') {
            focusTooltip();
        }
        
        // Dispatch custom event for third-party integration
        const eventData = {
            selectedText: options.selectedText || '',
            explanation: type === 'explanation' ? content : '',
            position: position,
            type: type
        };
        document.dispatchEvent(new CustomEvent('explainerPopupOnOpen', { detail: eventData }));
        
        // Check if we need to load a different reading level explanation
        if (type === 'explanation' && options.selectedText && options.cached) {
            const currentLevel = options.showReadingLevelSlider ?
                (options.reading_level || localStorage.getItem('explainer_reading_level') || 'standard') : 'standard';
            
            // If the current reading level is not 'standard', we need to load the appropriate explanation
            if (currentLevel !== 'standard') {
                logger.debug('Initial reading level check - loading explanation for level:', currentLevel);
                
                // Use setTimeout to ensure tooltip is fully rendered first
                setTimeout(() => {
                    handleReadingLevelChange(currentLevel, options.selectedText);
                }, 50);
            }
        }
        
        return currentTooltip;
    }
    
    /**
     * Hide tooltip
     */
    function hideTooltip() {
        if (!currentTooltip) {
            return;
        }
        
        clearAutoCloseTimer();
        removeTooltipEventListeners();
        
        
        // Dispatch close event before hiding
        const selectedText = currentTooltip.querySelector('.explainer-tooltip-title.selected-text')?.textContent || '';
        document.dispatchEvent(new CustomEvent('explainerPopupOnClose', { 
            detail: { selectedText: selectedText, wasVisible: isTooltipVisible } 
        }));
        
        // Animate out
        currentTooltip.classList.remove('visible');
        isTooltipVisible = false;
        
        // Remove from DOM after animation
        setTimeout(() => {
            if (currentTooltip && currentTooltip.parentNode) {
                currentTooltip.parentNode.removeChild(currentTooltip);
            }
            currentTooltip = null;
        }, tooltipConfig.animationDuration);
    }
    
    /**
     * Create tooltip element with accessibility features
     */
    function createTooltipElement(content, type, options = {}) {
        const tooltip = document.createElement('div');
        tooltip.className = `explainer-tooltip ${type}`;
        tooltip.setAttribute('role', 'tooltip');
        tooltip.setAttribute('aria-live', 'polite');
        tooltip.setAttribute('aria-atomic', 'true');
        
        // Add unique ID for accessibility
        const tooltipId = 'explainer-tooltip-' + Date.now();
        tooltip.id = tooltipId;
        
        // Add accessibility attributes
        tooltip.setAttribute('aria-describedby', tooltipId + '-content');
        tooltip.setAttribute('tabindex', '-1');
        
        // Create tooltip structure
        const header = createTooltipHeader(type, options.selectedText);
        const contentDiv = createTooltipContent(content, type, options);
        
        // Add ID to content for aria-describedby
        contentDiv.id = tooltipId + '-content';
        
        tooltip.appendChild(header);
        tooltip.appendChild(contentDiv);
        
        // Add footer for successful explanations only
        if (type === 'explanation') {
            const footer = createTooltipFooter(options);
            if (footer) {
                tooltip.appendChild(footer);
            }
        }
        
        return tooltip;
    }
    
    /**
     * Create tooltip header
     */
    function createTooltipHeader(type, selectedText) {
        const header = document.createElement('div');
        header.className = 'explainer-tooltip-header';
        
        // Title based on type
        const title = document.createElement('span');
        title.className = 'explainer-tooltip-title';
        
        switch (type) {
            case 'loading':
                title.textContent = getLocalizedString('loading', 'Loading...');
                break;
            case 'error':
                title.textContent = getLocalizedString('error', 'Error');
                break;
            case 'explanation':
            default:
                if (selectedText && selectedText.trim()) {
                    title.textContent = selectedText.trim();
                    // Add full text as title attribute for hover tooltip
                    title.setAttribute('title', selectedText.trim());
                    // Add class to indicate this is selected text (not the word "Explanation")
                    title.classList.add('selected-text');
                } else {
                    title.textContent = getLocalizedString('explanation', 'Explanation');
                }
                break;
        }
        
        header.appendChild(title);
        
        // Close button (not for loading states)
        if (type !== 'loading') {
            const closeButton = document.createElement('button');
            closeButton.className = 'explainer-tooltip-close';
            closeButton.setAttribute('aria-label', 'Close explanation');
            closeButton.setAttribute('type', 'button');
            closeButton.setAttribute('tabindex', '0');
            closeButton.innerHTML = '<span aria-hidden="true">×</span>';
            closeButton.addEventListener('click', hideTooltip);
            closeButton.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    hideTooltip();
                }
            });
            header.appendChild(closeButton);
        }
        
        return header;
    }
    
    /**
     * Create tooltip content
     */
    function createTooltipContent(content, type /*, options = {} */) {
        const contentDiv = document.createElement('div');
        contentDiv.className = 'explainer-tooltip-content';
        
        if (type === 'loading') {
            // Loading state with spinner
            const loadingDiv = document.createElement('div');
            loadingDiv.className = 'explainer-loading';
            
            const spinner = document.createElement('div');
            spinner.className = 'explainer-spinner';
            spinner.setAttribute('aria-hidden', 'true');
            
            const loadingText = document.createElement('span');
            loadingText.textContent = content;
            
            loadingDiv.appendChild(spinner);
            loadingDiv.appendChild(loadingText);
            contentDiv.appendChild(loadingDiv);
        } else {
            // Regular content
            if (typeof content === 'string') {
                // Convert line breaks to paragraphs
                const paragraphs = content.split('\n').filter(p => p.trim().length > 0);
                paragraphs.forEach(paragraph => {
                    const p = document.createElement('p');
                    p.textContent = paragraph.trim();
                    contentDiv.appendChild(p);
                });
            } else {
                // HTML content
                contentDiv.appendChild(content);
            }
        }
        
        return contentDiv;
    }
    
    /**
     * Create tooltip footer
     */
    function createTooltipFooter(options = {}) {
        const { showDisclaimer = false, showProvider = false, provider = '', showReadingLevelSlider = false } = options;

        const footerDiv = document.createElement('div');
        footerDiv.className = 'explainer-tooltip-footer';

        // Add reading level slider if enabled
        if (showReadingLevelSlider) {
            const sliderContainer = createReadingLevelSlider(options);
            if (sliderContainer) {
                footerDiv.appendChild(sliderContainer);
            }
        }
        
        if (showDisclaimer) {
            const disclaimerDiv = document.createElement('div');
            disclaimerDiv.className = 'explainer-disclaimer';
            disclaimerDiv.textContent = getLocalizedString('disclaimer', 'AI-generated content may not always be accurate');
            footerDiv.appendChild(disclaimerDiv);
        }
        
        if (showProvider && provider) {
            const providerDiv = document.createElement('div');
            providerDiv.className = 'explainer-provider';
            
            // Capitalize provider name
            const providerName = provider.charAt(0).toUpperCase() + provider.slice(1);
            const poweredByText = getLocalizedString('powered_by', 'Powered by');
            providerDiv.textContent = `${poweredByText} ${providerName}`;
            footerDiv.appendChild(providerDiv);
        }
        
        return footerDiv;
    }
    
    // Device detection utilities
    const DeviceDetection = {
        isTouchDevice: ('ontouchstart' in window) || (navigator.maxTouchPoints > 0),
        isMobileSize: window.matchMedia('(max-width: 768px)').matches,
        isTabletSize: window.matchMedia('(min-width: 769px) and (max-width: 1024px)').matches,
        
        shouldUseMobileSelector() {
            return this.isTouchDevice || this.isMobileSize;
        }
    };

    /**
     * Create reading level slider with progressive enhancement
     */
    function createReadingLevelSlider(options = {}) {
        // Determine which control to create based on device capabilities
        if (DeviceDetection.shouldUseMobileSelector()) {
            return createMobileDropdownSelector(options);
        } else {
            return createMultiThumbSlider(options);
        }
    }

    /**
     * Create mobile dropdown selector for touch devices
     */
    function createMobileDropdownSelector(options = {}) {
        // Use dynamic labels from PHP config with fallback
        const configLabels = window.explainerAjax?.settings?.reading_level_labels;
        const levels = configLabels ? 
            Object.entries(configLabels).map(([key, label]) => ({key, label})) :
            [
                { key: 'very_simple', label: 'Basic' },
                { key: 'simple', label: 'Simple' },
                { key: 'standard', label: 'Standard' },
                { key: 'detailed', label: 'Detailed' },
                { key: 'expert', label: 'Expert' }
            ];
        
        // Create mobile dropdown container
        const mobileContainer = document.createElement('div');
        mobileContainer.className = 'explainer-reading-level-mobile';
        
        // Create label
        const label = document.createElement('label');
        label.textContent = 'Reading Level:';
        label.className = 'explainer-mobile-label';
        label.setAttribute('for', 'explainer-reading-level-select');
        
        // Create select dropdown
        const select = document.createElement('select');
        select.className = 'explainer-reading-level-dropdown';
        select.id = 'explainer-reading-level-select';
        select.setAttribute('aria-label', 'Select reading level');
        
        // Populate options
        levels.forEach((level, index) => {
            const option = document.createElement('option');
            option.value = index;
            option.textContent = level.label;
            select.appendChild(option);
        });
        
        // Set default value to 'Standard'
        select.value = '2';

        // Determine initial reading level from options or localStorage, but use 'standard' if slider is disabled
        const currentLevel = options.showReadingLevelSlider ?
            (options.readingLevel || localStorage.getItem('explainer_reading_level') || 'standard') : 'standard';
        const levelIndex = levels.findIndex(level => level.key === currentLevel);
        if (levelIndex !== -1) {
            select.value = levelIndex.toString();
        } else {
            // Fallback to standard if level not found
            select.value = '2';
        }
        
        logger.debug('Mobile dropdown reading level set', { 
            fromOptions: options.readingLevel,
            fromLocalStorage: localStorage.getItem('explainer_reading_level'),
            finalLevel: currentLevel,
            selectValue: select.value 
        });
        
        // Handle selection change
        let changeTimeout;
        select.addEventListener('change', function(event) {
            const value = parseInt(event.target.value);
            const level = levels[value];
            
            // Debounce the actual explanation request
            clearTimeout(changeTimeout);
            changeTimeout = setTimeout(() => {
                handleReadingLevelChange(level.key, options.selectedText);
            }, 300);
        });
        
        mobileContainer.appendChild(label);
        mobileContainer.appendChild(select);
        
        return mobileContainer;
    }

    /**
     * Create multi-thumb SVG slider for desktop
     */
    function createMultiThumbSlider(options = {}) {
        // Use dynamic labels from PHP config with fallback
        const configLabels = window.explainerAjax?.settings?.reading_level_labels;
        const levels = configLabels ? 
            Object.entries(configLabels).map(([key, label]) => ({key, label})) :
            [
                { key: 'very_simple', label: 'Basic' },
                { key: 'simple', label: 'Simple' },
                { key: 'standard', label: 'Standard' },
                { key: 'detailed', label: 'Detailed' },
                { key: 'expert', label: 'Expert' }
            ];

        // Create main container
        const sliderContainer = document.createElement('div');
        sliderContainer.className = 'explainer-reading-level-slider';
        
        // Create label
        const label = document.createElement('label');
        label.className = 'explainer-slider-label';
        label.textContent = 'Reading Level:';
        
        // Create SVG multi-thumb slider
        const svgNS = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(svgNS, 'svg');
        svg.setAttribute('class', 'explainer-multi-slider');
        svg.setAttribute('width', '100%');
        svg.setAttribute('height', '50');
        svg.setAttribute('viewBox', '0 0 300 50');
        
        // Create slider rail
        const rail = document.createElementNS(svgNS, 'rect');
        rail.setAttribute('class', 'slider-rail');
        rail.setAttribute('x', '8');
        rail.setAttribute('y', '13.5');
        rail.setAttribute('width', '284');
        rail.setAttribute('height', '8');
        rail.setAttribute('rx', '4');
        svg.appendChild(rail);
        
        // Calculate thumb positions (evenly spaced)
        const railStart = 8;
        const railWidth = 284;
        const positions = [];
        for (let i = 0; i < 5; i++) {
            positions.push(railStart + (i * (railWidth / 4)));
        }

        // Determine initial reading level from options or localStorage, but use 'standard' if slider is disabled
        const currentLevel = options.showReadingLevelSlider ?
            (options.readingLevel || localStorage.getItem('explainer_reading_level') || 'standard') : 'standard';
        const levelIndex = levels.findIndex(level => level.key === currentLevel);
        let activeLevel = levelIndex !== -1 ? levelIndex : 2; // Default to standard if not found
        
        logger.debug('SVG slider reading level set', { 
            fromOptions: options.readingLevel,
            fromLocalStorage: localStorage.getItem('explainer_reading_level'),
            finalLevel: currentLevel,
            activeIndex: activeLevel 
        });
        
        // Create thumb groups for each level
        levels.forEach((level, index) => {
            const thumbGroup = document.createElementNS(svgNS, 'g');
            thumbGroup.setAttribute('class', `thumb-group ${index === activeLevel ? 'active' : ''}`);
            thumbGroup.setAttribute('data-level', level.key);
            thumbGroup.setAttribute('data-index', index);
            thumbGroup.style.cursor = 'pointer';
            
            // Create thumb circle
            const thumb = document.createElementNS(svgNS, 'circle');
            thumb.setAttribute('class', `slider-thumb ${index === activeLevel ? 'active' : ''}`);
            thumb.setAttribute('cx', positions[index]);
            thumb.setAttribute('cy', '17.5');
            thumb.setAttribute('r', '8');
            thumbGroup.appendChild(thumb);
            
            // Create text label
            const text = document.createElementNS(svgNS, 'text');
            text.setAttribute('class', 'thumb-label');
            text.setAttribute('x', positions[index]);
            text.setAttribute('y', '45');
            
            // Set text alignment: first left, last right, middle for others
            if (index === 0) {
                text.setAttribute('text-anchor', 'start');
            } else if (index === levels.length - 1) {
                text.setAttribute('text-anchor', 'end');
            } else {
                text.setAttribute('text-anchor', 'middle');
            }
            
            text.textContent = level.label;
            thumbGroup.appendChild(text);
            
            // Add click handler
            thumbGroup.addEventListener('click', function() {
                // Remove active class from all thumbs
                svg.querySelectorAll('.thumb-group').forEach(group => {
                    group.classList.remove('active');
                    group.querySelector('.slider-thumb').classList.remove('active');
                });
                
                // Add active class to clicked thumb
                thumbGroup.classList.add('active');
                thumb.classList.add('active');
                
                // Update active level
                activeLevel = index;
                
                // Handle reading level change
                handleReadingLevelChange(level.key, options.selectedText);
            });
            
            // Add keyboard support
            thumbGroup.setAttribute('tabindex', '0');
            thumbGroup.setAttribute('role', 'button');
            thumbGroup.setAttribute('aria-label', `Select ${level.label} reading level`);
            
            thumbGroup.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    thumbGroup.click();
                }
            });
            
            // Add hover effects
            thumbGroup.addEventListener('mouseenter', function() {
                if (!thumbGroup.classList.contains('active')) {
                    thumb.classList.add('hover');
                }
            });
            
            thumbGroup.addEventListener('mouseleave', function() {
                thumb.classList.remove('hover');
            });
            
            svg.appendChild(thumbGroup);
        });
        
        // Assemble the slider
        sliderContainer.appendChild(label);
        sliderContainer.appendChild(svg);
        
        return sliderContainer;
    }
    
    /**
     * Position tooltip relative to selection
     */
    function positionTooltip(tooltip, position) {
        if (!position) {
            // Fallback to center of screen
            position = {
                x: window.innerWidth / 2,
                y: window.innerHeight / 2,
                scrollX: window.scrollX,
                scrollY: window.scrollY
            };
        }
        
        // Get tooltip dimensions
        const tooltipRect = tooltip.getBoundingClientRect();
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        
        // Calculate position
        let left = position.x + position.scrollX;
        let top = position.y + position.scrollY;
        
        // Adjust for tooltip size and offset
        left -= tooltipRect.width / 2;
        
        // Simple positioning: check if tooltip would actually fit below using ABSOLUTE coordinates
        const selectionViewportY = position.y; // position.y is already viewport-relative from getBoundingClientRect()
        const selectionAbsoluteY = position.y + position.scrollY;
        const spaceAbove = selectionViewportY;
        const tooltipHeight = tooltipRect.height || 200; // Fallback for loading state
        
        // Calculate where tooltip bottom would be if positioned below (ABSOLUTE coordinates)
        const tooltipBottomIfBelow = selectionAbsoluteY + tooltipConfig.offset + tooltipHeight;
        const currentViewportBottom = window.scrollY + viewportHeight;
        const safetyMargin = 50; // Safety margin from viewport bottom
        const wouldFitBelow = tooltipBottomIfBelow <= currentViewportBottom - safetyMargin;
        const wouldFitAbove = spaceAbove >= 10 || selectionViewportY < 0; // Very lenient: 10px or text above viewport
        
        logger.debug('Tooltip positioning calculation', {
            selectionViewportY,
            selectionAbsoluteY,
            tooltipHeight,
            tooltipBottomIfBelow,
            currentViewportBottom,
            viewportHeight,
            scrollY: window.scrollY,
            wouldFitBelow,
            wouldFitAbove,
            spaceAbove
        });
        
        if (!wouldFitBelow && wouldFitAbove) {
            // Position above selection
            top = position.y + position.scrollY - tooltipHeight - tooltipConfig.offset;
            tooltip.classList.add('above');
            tooltip.classList.remove('below');
            logger.debug('Positioned tooltip above selection', {
                selectionViewportY,
                spaceAbove,
                tooltipHeight,
                tooltipBottomIfBelow,
                wouldFitBelow,
                wouldFitAbove
            });
        } else {
            // Position below selection (default)
            top = position.y + position.scrollY + tooltipConfig.offset;
            tooltip.classList.add('below');
            tooltip.classList.remove('above');
            logger.debug('Positioned tooltip below selection', {
                selectionViewportY,
                spaceAbove,
                tooltipHeight,
                tooltipBottomIfBelow,
                wouldFitBelow,
                wouldFitAbove
            });
        }
        
        // Viewport boundary checks
        const adjustedPosition = adjustForViewport(left, top, tooltipRect, viewportWidth, viewportHeight);
        left = adjustedPosition.left;
        top = adjustedPosition.top;
        
        // Apply position
        tooltip.style.left = left + 'px';
        tooltip.style.top = top + 'px';
        
        // Update arrow position if needed
        updateArrowPosition(tooltip, position, adjustedPosition);
    }
    
    /**
     * Adjust tooltip position for viewport boundaries
     */
    function adjustForViewport(left, top, tooltipRect, viewportWidth, viewportHeight) {
        const scrollX = window.scrollX;
        const scrollY = window.scrollY;
        const margin = 10;
        
        // Horizontal adjustments
        if (left < scrollX + margin) {
            left = scrollX + margin;
        } else if (left + tooltipRect.width > scrollX + viewportWidth - margin) {
            left = scrollX + viewportWidth - tooltipRect.width - margin;
        }
        
        // Vertical adjustments
        if (top < scrollY + margin) {
            top = scrollY + margin;
        } else if (top + tooltipRect.height > scrollY + viewportHeight - margin) {
            top = scrollY + viewportHeight - tooltipRect.height - margin;
        }
        
        return { left, top };
    }
    
    /**
     * Update arrow position based on tooltip adjustment
     */
    function updateArrowPosition(tooltip, originalPosition, adjustedPosition) {
        const tooltipRect = tooltip.getBoundingClientRect();
        const originalLeft = originalPosition.x + originalPosition.scrollX - tooltipRect.width / 2;
        const actualLeft = adjustedPosition.left;
        
        // Calculate arrow offset
        const arrowOffset = originalLeft - actualLeft;
        const maxOffset = tooltipRect.width / 2 - 20; // 20px from edge
        
        // Apply arrow positioning
        const clampedOffset = Math.max(-maxOffset, Math.min(maxOffset, arrowOffset));
        tooltip.style.setProperty('--arrow-offset', clampedOffset + 'px');
    }
    
    /**
     * Set auto-close timer
     */
    function setAutoCloseTimer() {
        clearAutoCloseTimer();
        autoCloseTimer = setTimeout(() => {
            hideTooltip();
        }, tooltipConfig.autoCloseDelay);
    }
    
    /**
     * Clear auto-close timer
     */
    function clearAutoCloseTimer() {
        if (autoCloseTimer) {
            clearTimeout(autoCloseTimer);
            autoCloseTimer = null;
        }
    }
    
    /**
     * Attach tooltip event listeners
     */
    function attachTooltipEventListeners() {
        if (!currentTooltip) return;
        
        // Prevent clicks inside tooltip from bubbling to document level
        currentTooltip.addEventListener('click', handleTooltipClick);
        currentTooltip.addEventListener('mouseup', handleTooltipMouseUp);
        
        // Touch events for mobile (no auto-close needed)
        currentTooltip.addEventListener('touchend', handleTooltipTouchEnd);
        
        // Mobile swipe dismissal
        setupSwipeGestures(currentTooltip);
        
        // Keyboard navigation
        currentTooltip.addEventListener('keydown', handleTooltipKeydown);
        
        // Focus management (no auto-close)
    }
    
    /**
     * Remove tooltip event listeners
     */
    function removeTooltipEventListeners() {
        if (!currentTooltip) return;
        
        currentTooltip.removeEventListener('click', handleTooltipClick);
        currentTooltip.removeEventListener('mouseup', handleTooltipMouseUp);
        currentTooltip.removeEventListener('touchend', handleTooltipTouchEnd);
        currentTooltip.removeEventListener('keydown', handleTooltipKeydown);
    }
    
    /**
     * Handle tooltip click events to prevent bubbling
     */
    function handleTooltipClick(event) {
        // Prevent clicks inside tooltip from triggering document-level selection handlers
        event.stopPropagation();
        logger.debug('Tooltip click event prevented from bubbling', {
            target: event.target.tagName,
            className: event.target.className
        });
    }
    
    /**
     * Handle tooltip mouseup events to prevent bubbling
     */
    function handleTooltipMouseUp(event) {
        // Prevent mouseup inside tooltip from triggering document-level selection handlers
        event.stopPropagation();
        logger.debug('Tooltip mouseup event prevented from bubbling', {
            target: event.target.tagName,
            className: event.target.className
        });
    }
    
    /**
     * Handle tooltip keyboard navigation
     */
    function handleTooltipKeydown(event) {
        switch (event.key) {
            case 'Escape':
                event.preventDefault();
                hideTooltip();
                break;
            case 'Tab':
                // Handle tab navigation within tooltip
                handleTooltipTabNavigation(event);
                break;
        }
    }
    
    /**
     * Handle tab navigation within tooltip
     */
    function handleTooltipTabNavigation(event) {
        const focusableElements = currentTooltip.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        
        if (focusableElements.length === 0) {
            return;
        }
        
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        
        if (event.shiftKey) {
            // Shift+Tab
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
    
    /**
     * Focus tooltip for accessibility
     */
    function focusTooltip() {
        if (!currentTooltip) return;
        
        // Announce tooltip to screen readers
        announceTooltip();
        
        // Focus close button if available
        const closeButton = currentTooltip.querySelector('.explainer-tooltip-close');
        if (closeButton) {
            closeButton.focus();
        } else {
            // Make tooltip focusable
            currentTooltip.setAttribute('tabindex', '-1');
            currentTooltip.focus();
        }
    }
    
    /**
     * Announce tooltip to screen readers
     */
    function announceTooltip() {
        const content = currentTooltip.querySelector('.explainer-tooltip-content');
        if (content) {
            const announcement = document.createElement('div');
            announcement.className = 'explainer-sr-only';
            announcement.setAttribute('aria-live', 'assertive');
            announcement.setAttribute('aria-atomic', 'true');
            announcement.textContent = 'Explanation dialog opened. ' + content.textContent;
            
            document.body.appendChild(announcement);
            
            setTimeout(() => {
                if (announcement.parentNode) {
                    announcement.parentNode.removeChild(announcement);
                }
            }, 1000);
        }
    }
    
    /**
     * Check if tooltip is currently visible
     */
    function isVisible() {
        return isTooltipVisible;
    }
    
    /**
     * Get current tooltip element
     */
    function getCurrentTooltip() {
        return currentTooltip;
    }
    
    /**
     * Update tooltip content (for loading -> success transitions)
     */
    function updateTooltipContent(content, type = 'explanation', options = {}) {
        if (!currentTooltip) return;
        
        // Preserve positioning and visibility classes
        const hasAbove = currentTooltip.classList.contains('above');
        const hasBelow = currentTooltip.classList.contains('below');
        const hasVisible = currentTooltip.classList.contains('visible');
        
        // Update tooltip class while preserving positioning
        let newClasses = `explainer-tooltip ${type}`;
        if (hasAbove) newClasses += ' above';
        if (hasBelow) newClasses += ' below';
        if (hasVisible) newClasses += ' visible';
        // Don't preserve loading class - it should be removed when content loads
        
        currentTooltip.className = newClasses;
        
        logger.debug('Updated tooltip classes', {
            newClasses: newClasses,
            preservedAbove: hasAbove,
            preservedBelow: hasBelow,
            preservedVisible: hasVisible,
            contentType: type
        });
        
        // Update header
        const header = currentTooltip.querySelector('.explainer-tooltip-header');
        if (header) {
            header.parentNode.removeChild(header);
        }
        
        // Update content
        const contentDiv = currentTooltip.querySelector('.explainer-tooltip-content');
        if (contentDiv) {
            contentDiv.parentNode.removeChild(contentDiv);
        }
        
        // Remove existing footer
        const footer = currentTooltip.querySelector('.explainer-tooltip-footer');
        if (footer) {
            footer.parentNode.removeChild(footer);
        }
        
        // Add new header and content
        const newHeader = createTooltipHeader(type, options.selectedText);
        const newContent = createTooltipContent(content, type, options);
        
        currentTooltip.appendChild(newHeader);
        currentTooltip.appendChild(newContent);
        
        // Add footer for successful explanations only
        if (type === 'explanation') {
            const newFooter = createTooltipFooter(options);
            if (newFooter) {
                currentTooltip.appendChild(newFooter);
            }
        }
        
        // Reattach event listeners
        attachTooltipEventListeners();
        
        // Recalculate position after content update (accounts for footer height changes)
        if (window.ExplainerPlugin.state && window.ExplainerPlugin.state.selectionPosition) {
            positionTooltip(currentTooltip, window.ExplainerPlugin.state.selectionPosition);
        }
        
        // Force browser to process position changes before making visible
        currentTooltip.offsetHeight; // Force reflow
        
        // Ensure tooltip is visible after repositioning
        currentTooltip.classList.add('visible');
        
        
        // Focus tooltip for non-loading states (no auto-close)
        if (type !== 'loading') {
            focusTooltip();
        }
    }
    
    /**
     * Handle tooltip touch end
     */
    function handleTooltipTouchEnd(event) {
        // No auto-close functionality needed
    }
    
    /**
     * Setup swipe gestures for mobile dismissal
     */
    function setupSwipeGestures(tooltip) {
        let startX = 0;
        let startY = 0;
        let endX = 0;
        let endY = 0;
        
        tooltip.addEventListener('touchstart', (event) => {
            startX = event.touches[0].clientX;
            startY = event.touches[0].clientY;
        });
        
        tooltip.addEventListener('touchmove', (event) => {
            endX = event.touches[0].clientX;
            endY = event.touches[0].clientY;
        });
        
        tooltip.addEventListener('touchend', () => {
            const deltaX = endX - startX;
            const deltaY = endY - startY;
            const minSwipeDistance = 50;
            
            // Swipe up or down to dismiss
            if (Math.abs(deltaY) > minSwipeDistance && Math.abs(deltaY) > Math.abs(deltaX)) {
                if (deltaY < 0 || deltaY > 0) { // Up or down swipe
                    hideTooltip();
                }
            }
            
            // Swipe left or right to dismiss
            if (Math.abs(deltaX) > minSwipeDistance && Math.abs(deltaX) > Math.abs(deltaY)) {
                if (deltaX < 0 || deltaX > 0) { // Left or right swipe
                    hideTooltip();
                }
            }
        });
    }
    
    /**
     * Handle window resize
     */
    function handleWindowResize() {
        // Re-evaluate device type for orientation changes
        DeviceDetection.isMobileSize = window.matchMedia('(max-width: 768px)').matches;
        DeviceDetection.isTabletSize = window.matchMedia('(min-width: 769px) and (max-width: 1024px)').matches;
        
        // Update touch device class
        if (DeviceDetection.isTouchDevice) {
            document.body.classList.add('explainer-touch-device');
        } else {
            document.body.classList.remove('explainer-touch-device');
        }
        
        if (currentTooltip && isTooltipVisible) {
            // Reposition tooltip
            const position = window.ExplainerPlugin.state?.selectionPosition;
            if (position) {
                positionTooltip(currentTooltip, position);
            }
        }
    }
    
    
    
    
    
    /**
     * Initialize tooltip system
     */
    function initializeTooltips() {
        // Load localized strings first
        loadLocalizedStrings().catch(error => {
            logger.warn('Failed to load localized strings, using fallback:', error);
        });
        
        // Apply touch device class for CSS override
        if (DeviceDetection.isTouchDevice) {
            document.body.classList.add('explainer-touch-device');
        }
        
        // Add window event listeners
        window.addEventListener('resize', handleWindowResize);
        
        // Add global click listener for outside clicks
        document.addEventListener('click', (event) => {
            if (currentTooltip && event.target && !currentTooltip.contains(event.target) && 
                (!event.target.closest || !event.target.closest('.explainer-toggle'))) {
                hideTooltip();
            }
        });
        
        // Add global escape key listener
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && isTooltipVisible) {
                hideTooltip();
            }
        });
    }
    
    /**
     * Handle reading level change
     */
    function handleReadingLevelChange(readingLevel, selectedText) {
        if (!selectedText || !currentTooltip) return;
        
        logger.debug('Reading level changed', { readingLevel, selectedText: selectedText?.substring(0, 50) + '...' });
        
        // Save preference to localStorage
        localStorage.setItem('explainer_reading_level', readingLevel);
        
        // Show loading state in content area
        const contentDiv = currentTooltip.querySelector('.explainer-tooltip-content');
        if (contentDiv) {
            // Store original content (currently unused but may be needed for error recovery)
            // const originalContent = contentDiv.innerHTML;
            
            // Show loading state
            contentDiv.innerHTML = `
                <div class="explainer-loading">
                    <div class="explainer-spinner" aria-hidden="true"></div>
                    <span>Loading ${readingLevel.replace('_', ' ')} explanation...</span>
                </div>
            `;
            
            // Add loading class to tooltip
            currentTooltip.classList.add('loading');
        }
        
        // Request new explanation from the explainer module
        if (window.ExplainerPlugin && window.ExplainerPlugin.requestExplanation) {
            window.ExplainerPlugin.requestExplanation(selectedText, readingLevel)
                .then(result => {
                    if (result.success && currentTooltip) {
                        // Remove cache/API indicators - just use the explanation directly
                        const cached = result.cached || false;
                        const explanationWithIndicator = result.explanation;
                        
                        // Remove loading class and update tooltip content with new explanation
                        currentTooltip.classList.remove('loading');
                        updateTooltipContent(explanationWithIndicator, 'explanation', {
                            selectedText: selectedText,
                            showDisclaimer: result.showDisclaimer,
                            showProvider: result.showProvider,
                            showReadingLevelSlider: result.showReadingLevelSlider,
                            provider: result.provider
                        });
                        
                        // Dispatch explanation loaded event
                        document.dispatchEvent(new CustomEvent('explainerExplanationLoaded', {
                            detail: {
                                selectedText: selectedText,
                                explanation: result.explanation,
                                readingLevel: readingLevel,
                                provider: result.provider,
                                cached: result.cached
                            }
                        }));
                        
                        logger.info('Reading level explanation updated', {
                            readingLevel,
                            explanationLength: result.explanation?.length,
                            cached: result.cached
                        });
                    } else {
                        // Show error state
                        if (currentTooltip) {
                            currentTooltip.classList.remove('loading');
                            updateTooltipContent(
                                result.error || 'Failed to get explanation for this reading level',
                                'error',
                                { selectedText: selectedText }
                            );
                        }
                        logger.error('Reading level explanation failed', {
                            readingLevel,
                            error: result.error
                        });
                    }
                })
                .catch(error => {
                    // Show error state
                    if (currentTooltip) {
                        currentTooltip.classList.remove('loading');
                        updateTooltipContent(
                            'Failed to get explanation for this reading level',
                            'error',
                            { selectedText: selectedText }
                        );
                    }
                    logger.error('Reading level explanation error', {
                        readingLevel,
                        error: error.message
                    });
                });
        } else {
            logger.error('ExplainerPlugin.requestExplanation not available for reading level change');
        }
    }
    
    /**
     * Cleanup tooltip system
     */
    function cleanupTooltips() {
        hideTooltip();
        window.removeEventListener('resize', handleWindowResize);
    }
    
    // Public API
    window.ExplainerPlugin.showTooltip = showTooltip;
    window.ExplainerPlugin.hideTooltip = hideTooltip;
    window.ExplainerPlugin.closePopup = hideTooltip; // Alias for third-party use
    window.ExplainerPlugin.updateTooltipContent = updateTooltipContent;
    window.ExplainerPlugin.isTooltipVisible = isVisible;
    window.ExplainerPlugin.getCurrentTooltip = getCurrentTooltip;
    window.ExplainerPlugin.initializeTooltips = initializeTooltips;
    window.ExplainerPlugin.cleanupTooltips = cleanupTooltips;
    
    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeTooltips);
    } else {
        initializeTooltips();
    }
    
})();