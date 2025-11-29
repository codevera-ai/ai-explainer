/**
 * Setup Mode JavaScript
 * Handles auto-detection of content areas via text selection
 */

(function() {
    'use strict';

    // Check if setup mode config is available
    if (typeof explainerSetupMode === 'undefined') {
        return;
    }

    // Track if detection is in progress
    let detectionInProgress = false;

    /**
     * Detect content container from selected element
     * Walks up the DOM tree to find the first content-related container
     */
    function detectContentContainer(element) {
        let current = element;

        // Walk up the DOM tree until we find a content container
        while (current && current !== document.body) {
            const tagName = current.tagName ? current.tagName.toUpperCase() : '';
            const className = (current.className || '').toString().toLowerCase();
            const id = (current.id || '').toLowerCase();

            // Stop at first semantic tag or content-related class/id
            if (tagName === 'ARTICLE' ||
                tagName === 'MAIN' ||
                className.includes('content') ||
                className.includes('post') ||
                className.includes('entry') ||
                className.includes('article') ||
                id.includes('content') ||
                id.includes('post') ||
                id.includes('entry')) {
                return current;
            }

            current = current.parentElement;
        }

        // Fallback: try to find article or main in document
        const fallback = document.querySelector('article, main, [class*="content"], [class*="post"]');
        if (fallback) {
            return fallback;
        }

        // Last resort: return body (but warn user)
        return document.body;
    }

    /**
     * Build CSS selector for element
     * Priority: ID > first class > tag name
     */
    function buildSelector(element) {
        // Priority 1: Use ID if available
        if (element.id) {
            return '#' + element.id;
        }

        // Priority 2: Use first class name
        if (element.className && typeof element.className === 'string') {
            const classes = element.className.trim().split(/\s+/);
            if (classes.length > 0 && classes[0]) {
                return '.' + classes[0];
            }
        }

        // Priority 3: Use tag name
        return element.tagName ? element.tagName.toLowerCase() : '';
    }

    /**
     * Save detected selector via AJAX
     */
    function saveDetectedSelector(selector) {
        if (detectionInProgress) {
            return;
        }

        detectionInProgress = true;

        // Show loading state
        const banner = document.getElementById('explainer-setup-mode-banner');
        if (banner) {
            banner.style.opacity = '0.7';
            banner.style.pointerEvents = 'none';
        }

        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'explainer_save_detected_selector');
        formData.append('selector', selector);
        formData.append('nonce', explainerSetupMode.nonce);

        // Send AJAX request
        fetch(explainerSetupMode.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            detectionInProgress = false;

            if (data.success) {
                // Show success message
                let message = 'Setup complete! Detected selector: ' + selector;
                if (selector === 'body') {
                    message += '\n\nNote: Body selector may not be accurate. Please verify in settings.';
                }
                message += '\n\nReturn to settings to continue.';

                alert(message);

                // Redirect to settings page
                window.location.href = data.data.settings_url;
            } else {
                alert('Error: ' + (data.data ? data.data.message : 'Failed to save selector'));

                // Restore banner
                if (banner) {
                    banner.style.opacity = '1';
                    banner.style.pointerEvents = 'auto';
                }
            }
        })
        .catch(error => {
            detectionInProgress = false;
            alert('Network error. Please try again.');
            console.error('Setup mode error:', error);

            // Restore banner
            if (banner) {
                banner.style.opacity = '1';
                banner.style.pointerEvents = 'auto';
            }
        });
    }

    /**
     * Disable setup mode via AJAX
     */
    function disableSetupMode() {
        const formData = new FormData();
        formData.append('action', 'explainer_disable_setup_mode');
        formData.append('nonce', explainerSetupMode.nonce);

        fetch(explainerSetupMode.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove banner
                const banner = document.getElementById('explainer-setup-mode-banner');
                if (banner) {
                    banner.remove();
                }
            } else {
                alert('Error: ' + (data.data ? data.data.message : 'Failed to disable setup mode'));
            }
        })
        .catch(error => {
            alert('Network error. Please try again.');
            console.error('Setup mode error:', error);
        });
    }

    /**
     * Handle text selection
     */
    function handleTextSelection() {
        if (detectionInProgress) {
            return;
        }

        const selection = window.getSelection();
        const selectedText = selection.toString().trim();

        // Validate selection
        if (!selectedText || selectedText.length < 5) {
            return;
        }

        // Get the selected element
        const range = selection.getRangeAt(0);
        const selectedElement = range.commonAncestorContainer;

        // Check if selection is in banner (ignore if so)
        const banner = document.getElementById('explainer-setup-mode-banner');
        if (banner && banner.contains(selectedElement)) {
            return;
        }

        // Detect content container
        const container = detectContentContainer(selectedElement.nodeType === Node.TEXT_NODE ? selectedElement.parentElement : selectedElement);

        // Build selector
        const selector = buildSelector(container);

        // Confirm with user
        const confirmMessage = 'Detected content area selector: ' + selector + '\n\nSave this selector?';
        if (confirm(confirmMessage)) {
            saveDetectedSelector(selector);
        }
    }

    /**
     * Initialize setup mode
     */
    function init() {
        // Add mouseup event listener for text selection
        document.addEventListener('mouseup', function(e) {
            // Small delay to ensure selection is complete
            setTimeout(handleTextSelection, 100);
        });

        // Add cancel button handler
        const cancelBtn = document.getElementById('explainer-setup-cancel');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Cancel setup mode? No selector will be saved.')) {
                    disableSetupMode();
                }
            });
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
