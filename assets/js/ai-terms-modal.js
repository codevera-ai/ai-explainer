/**
 * AI Terms Management Modal Controller
 * Integrates with WP AI Explainer plugin architecture
 * Handles modal display, term management, and API interactions
 */

(function() {
    'use strict';
    
    // Ensure ExplainerPlugin namespace exists
    window.ExplainerPlugin = window.ExplainerPlugin || {};
    
    // Modal configuration
    const config = {
        itemsPerPage: 20,
        maxConcurrentRequests: 3,
        debounceDelay: 300,
        searchMinLength: 2,
        maxRetries: 3,
        animationDuration: 300
    };
    
    // Modal state management
    const state = {
        isOpen: false,
        isLoading: false,
        postId: null,
        terms: [],
        filteredTerms: [],
        currentPage: 1,
        totalPages: 1,
        totalTerms: 0,
        searchQuery: '',
        statusFilter: '',
        sortField: 'selected_text',
        sortDirection: 'asc',
        debounceTimer: null,
        loadingStates: new Map(),
        cache: new Map(),
        showCreateForm: false,
        createFormData: {
            termText: '',
            explanations: {}
        },
        heightCalculated: false
    };
    
    // DOM elements
    const elements = {
        backdrop: null,
        container: null,
        modal: null,
        searchInput: null,
        searchClear: null,
        statusFilter: null,
        createTermButton: null,
        createTermForm: null,
        termsList: null,
        emptyState: null,
        loadingState: null,
        paginationInfo: null,
        paginationControls: null,
        pageNumbers: null,
        prevButton: null,
        nextButton: null,
        closeButton: null,
        cancelButton: null,
        template: null,
        termRowTemplate: null,
        srAnnouncements: null,
        createFormElements: {
            termTextInput: null,
            testTermButton: null,
            testResult: null,
            explanationTextareas: {},
            saveButton: null,
            cancelButton: null
        }
    };
    
    /**
     * AI Terms Modal Controller Class
     */
    class AITermsModal {
        constructor() {
            this.initializeModal();
            this.bindEvents();
            this.setupAccessibility();
            
            // Integrate with plugin's debug system
            if (window.ExplainerPlugin && window.ExplainerPlugin.Logger) {
                this.logger = window.ExplainerPlugin.Logger;
                this.logger.debug('AITermsModal initialized', 'Modal');
            }
        }
        
        /**
         * Initialize modal DOM structure
         */
        initializeModal() {
            // Get template from DOM
            elements.template = document.getElementById('ai-terms-modal-template');
            if (!elements.template) {
                console.error('AI Terms Modal template not found');
                return;
            }
            
            // Clone template content
            const templateContent = elements.template.querySelector('.explainer-modal-backdrop');
            if (!templateContent) {
                console.error('Modal template content not found');
                return;
            }
            
            // Create modal instance
            elements.backdrop = templateContent.cloneNode(true);
            elements.backdrop.style.display = '';
            elements.backdrop.setAttribute('id', 'ai-terms-modal');
            
            // Cache DOM references
            this.cacheElements();
            
            // Get term row template
            elements.termRowTemplate = elements.template.querySelector('.explainer-term-row-template');
            
            // Get screen reader announcements
            elements.srAnnouncements = document.getElementById('ai-terms-modal-sr-announcements');
            
            // Append to body
            document.body.appendChild(elements.backdrop);
            
            this.debug('Modal DOM initialized');
        }
        
        /**
         * Cache DOM element references
         */
        cacheElements() {
            elements.container = elements.backdrop.querySelector('.explainer-modal-container');
            elements.searchInput = elements.backdrop.querySelector('#ai-terms-search');
            elements.searchClear = elements.backdrop.querySelector('.explainer-search-clear');
            elements.statusFilter = elements.backdrop.querySelector('#ai-terms-status-filter');
            elements.createTermButton = elements.backdrop.querySelector('.explainer-create-term');
            elements.createTermForm = elements.backdrop.querySelector('.explainer-create-term-form');
            elements.termsList = elements.backdrop.querySelector('.explainer-terms-list');
            elements.emptyState = elements.backdrop.querySelector('.explainer-terms-empty');
            elements.loadingState = elements.backdrop.querySelector('.explainer-terms-loading');
            elements.paginationInfo = elements.backdrop.querySelector('.explainer-pagination-info');
            elements.paginationControls = elements.backdrop.querySelector('.explainer-pagination-controls');
            elements.pageNumbers = elements.backdrop.querySelector('.explainer-page-numbers');
            elements.prevButton = elements.backdrop.querySelector('.explainer-page-prev');
            elements.nextButton = elements.backdrop.querySelector('.explainer-page-next');
            elements.closeButton = elements.backdrop.querySelector('.explainer-modal-close');
            elements.cancelButton = elements.backdrop.querySelector('.explainer-modal-cancel');
            
            // Cache create form elements
            elements.createFormElements.termTextInput = elements.backdrop.querySelector('#explainer-new-term-text');
            elements.createFormElements.testTermButton = elements.backdrop.querySelector('.explainer-test-term');
            elements.createFormElements.testResult = elements.backdrop.querySelector('.explainer-test-result');
            elements.createFormElements.saveButton = elements.backdrop.querySelector('.explainer-save-new-term');
            elements.createFormElements.cancelButton = elements.backdrop.querySelector('.explainer-cancel-create');
            
            // Cache explanation textareas
            const readingLevels = ['very_simple', 'simple', 'standard', 'detailed', 'expert'];
            readingLevels.forEach(level => {
                elements.createFormElements.explanationTextareas[level] = 
                    elements.backdrop.querySelector(`#explainer-create-${level}`);
            });
            
            // Debug DOM element caching
            this.debug('DOM elements cached:', {
                termsList: !!elements.termsList,
                emptyState: !!elements.emptyState,
                loadingState: !!elements.loadingState
            });
        }
        
        /**
         * Bind event listeners
         */
        bindEvents() {
            // Modal close events
            elements.closeButton?.addEventListener('click', () => this.close());
            elements.cancelButton?.addEventListener('click', () => this.close());
            elements.backdrop?.addEventListener('click', (e) => {
                if (e.target === elements.backdrop) {
                    this.close();
                }
            });
            
            // Keyboard events
            document.addEventListener('keydown', (e) => {
                if (state.isOpen && e.key === 'Escape') {
                    this.close();
                }
            });
            
            // Search events
            elements.searchInput?.addEventListener('input', (e) => {
                this.handleSearch(e.target.value);
            });
            
            elements.searchClear?.addEventListener('click', () => {
                this.clearSearch();
            });
            
            // Filter events
            elements.statusFilter?.addEventListener('change', (e) => {
                this.handleStatusFilter(e.target.value);
            });
            
            // Create term button
            elements.createTermButton?.addEventListener('click', () => {
                this.showCreateForm();
            });
            
            // Create form events
            elements.createFormElements.testTermButton?.addEventListener('click', () => {
                this.testTermInContent();
            });
            
            elements.createFormElements.saveButton?.addEventListener('click', () => {
                this.saveNewTerm();
            });
            
            elements.createFormElements.cancelButton?.addEventListener('click', () => {
                this.hideCreateForm();
            });
            
            // Create form close button - use event delegation
            document.addEventListener('click', (e) => {
                if (e.target.matches('.explainer-create-form-close') || e.target.closest('.explainer-create-form-close')) {
                    this.hideCreateForm();
                }
            });
            
            // Clear validation errors when user starts typing
            document.addEventListener('input', (e) => {
                if (e.target.matches('.explainer-text-input, .explainer-explanation-textarea')) {
                    e.target.classList.remove('error');
                }
            });
            
            // Pagination events
            elements.prevButton?.addEventListener('click', () => {
                this.goToPage(state.currentPage - 1);
            });
            
            elements.nextButton?.addEventListener('click', () => {
                this.goToPage(state.currentPage + 1);
            });
            
            // Edit panel event delegation
            elements.termsList?.addEventListener('click', (e) => {
                this.handleTermAction(e);
            });
            
            this.debug('Event listeners bound');
        }
        
        /**
         * Setup accessibility features
         */
        setupAccessibility() {
            // Set initial ARIA attributes
            elements.backdrop?.setAttribute('role', 'dialog');
            elements.backdrop?.setAttribute('aria-modal', 'true');
            elements.backdrop?.setAttribute('aria-labelledby', 'ai-terms-modal-title');
            
            // Trap focus within modal when open
            this.setupFocusTrap();
            
            this.debug('Accessibility features setup');
        }
        
        /**
         * Setup focus trap for modal
         */
        setupFocusTrap() {
            const focusableElements = elements.container?.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            
            if (!focusableElements || focusableElements.length === 0) return;
            
            const firstFocusable = focusableElements[0];
            const lastFocusable = focusableElements[focusableElements.length - 1];
            
            elements.backdrop?.addEventListener('keydown', (e) => {
                if (e.key !== 'Tab') return;
                
                if (e.shiftKey) {
                    if (document.activeElement === firstFocusable) {
                        e.preventDefault();
                        lastFocusable.focus();
                    }
                } else {
                    if (document.activeElement === lastFocusable) {
                        e.preventDefault();
                        firstFocusable.focus();
                    }
                }
            });
        }
        
        /**
         * Open modal and load terms
         * @param {string|number} postId - The post ID to load terms for
         */
        async open(postId = null) {
            if (state.isOpen) return;
            
            // Store post ID if provided
            if (postId) {
                state.postId = postId;
            }
            
            // If no post ID, try to get it from current page
            if (!state.postId) {
                const currentPagePostId = this.getCurrentPagePostId();
                if (currentPagePostId) {
                    state.postId = currentPagePostId;
                } else {
                    this.handleError('No post ID available for loading terms');
                    return;
                }
            }
            
            state.isOpen = true;
            
            // Show modal with animation
            elements.backdrop.classList.add('visible');
            await this.nextFrame();
            
            // Prevent body scroll
            document.body.classList.add('explainer-modal-open');
            
            // Focus first element
            elements.searchInput?.focus();
            
            // Load initial terms
            await this.loadTerms();
            
            // Announce to screen readers
            this.announce('AI terms management modal opened');
            
            this.debug('Modal opened for post ID:', state.postId);
        }
        
        /**
         * Close modal
         */
        async close() {
            if (!state.isOpen) return;
            
            state.isOpen = false;
            
            // Hide modal with animation
            elements.backdrop.classList.remove('visible');
            
            await this.delay(config.animationDuration);
            
            // Restore body scroll
            document.body.classList.remove('explainer-modal-open');
            
            // Clear selections and state
            this.resetState();
            
            // Reset height calculation flag for next time
            state.heightCalculated = false;
            
            // Remove any dynamic height styles
            if (elements.container) {
                elements.container.style.height = '';
                const content = elements.container.querySelector('.explainer-modal-content');
                if (content) {
                    content.style.maxHeight = '';
                    content.style.overflowY = '';
                }
            }
            
            // Announce to screen readers
            this.announce('AI terms management modal closed');
            
            this.debug('Modal closed');
        }
        
        /**
         * Calculate and set dynamic modal height based on content
         */
        calculateDynamicHeight() {
            if (!elements.container || state.heightCalculated) return;
            
            try {
                // Get the natural height of the modal container after content is loaded
                const naturalHeight = elements.container.offsetHeight;
                
                // Set this height explicitly to prevent further changes
                elements.container.style.height = naturalHeight + 'px';
                
                // Mark as calculated so we don't do this again
                state.heightCalculated = true;
                
                this.debug('Dynamic height set to natural height:', naturalHeight + 'px');
                
            } catch (error) {
                console.error('Error calculating dynamic height:', error);
            }
        }
        
        /**
         * Load terms from server
         */
        async loadTerms(reset = false) {
            if (reset) {
                state.currentPage = 1;
            }
            
            state.isLoading = true;
            this.showLoadingState();
            
            this.debug('Loading terms - showing loading state, hiding empty state and terms list');
            
            try {
                const response = await this.apiRequest('load_ai_terms', {
                    post_id: state.postId,
                    page: state.currentPage,
                    per_page: config.itemsPerPage,
                    // Remove client-side filters from server request - we'll handle these client-side
                    search: '', 
                    status: '',
                    sort_field: state.sortField,
                    sort_direction: state.sortDirection
                });
                
                if (response.success) {
                    state.terms = response.data.terms || [];
                    state.totalTerms = response.data.total || 0;
                    state.totalPages = Math.ceil(state.totalTerms / config.itemsPerPage);
                    
                    this.debug(`Loaded ${state.terms.length} terms, total: ${state.totalTerms}`);
                    
                    // Apply client-side filters to the loaded terms
                    this.applyFilters();
                    
                    this.updatePagination();
                    
                } else {
                    throw new Error(response.data?.message || 'Failed to load terms');
                }
                
            } catch (error) {
                this.handleError('Error loading terms: ' + error.message);
                this.showEmptyState();
            } finally {
                state.isLoading = false;
                this.hideLoadingState();
                this.debug('Finished loading - hiding loading state');
                
                // Calculate dynamic height only once after first load
                if (!state.heightCalculated) {
                    // Wait 2 seconds to ensure terms are fully loaded and rendered
                    setTimeout(() => {
                        this.calculateDynamicHeight();
                    }, 2000);
                }
            }
        }
        
        /**
         * Render terms in the list
         */
        renderTerms() {
            if (!elements.termsList || !elements.termRowTemplate) return;
            
            // Clear existing terms
            elements.termsList.innerHTML = '';
            
            // Use filtered terms for rendering
            const termsToRender = state.filteredTerms.length > 0 || state.searchQuery || state.statusFilter 
                ? state.filteredTerms 
                : state.terms;
            
            console.log('🔍 DEBUG: renderTerms - About to render terms:', termsToRender.length);
            console.log('🔍 DEBUG: renderTerms - First term sample:', termsToRender[0]);
            
            // Render each term
            let renderedCount = 0;
            termsToRender.forEach(term => {
                const termRow = this.createTermRow(term);
                if (termRow) {
                    elements.termsList.appendChild(termRow);
                    renderedCount++;
                }
            });
            
            console.log(`🔍 DEBUG: renderTerms - Rendered ${renderedCount} of ${termsToRender.length} terms`);
            this.debug(`Rendered ${renderedCount} of ${termsToRender.length} terms (${termsToRender.length - renderedCount} skipped due to missing IDs)`);
        }
        
        /**
         * Create a term row element
         */
        createTermRow(term) {
            // Skip terms without valid IDs to prevent broken toggles
            if (!term.id || term.id === '' || term.id === null || term.id === undefined) {
                this.debug('Skipping term without valid ID:', term);
                return null;
            }
            
            // Clone the entire template container (includes both row and edit panel)
            const template = elements.termRowTemplate;
            const rowContainer = template.cloneNode(true);
            // Remove the display:none style from the cloned container
            rowContainer.style.display = '';
            rowContainer.classList.remove('explainer-term-row-template');
            const row = rowContainer.querySelector('.explainer-term-row');
            
            // Set term ID
            row.setAttribute('data-term-id', term.id);
            
            
            // Populate term text
            const termText = row.querySelector('.explainer-term-value');
            termText.textContent = term.text || '';
            termText.setAttribute('title', term.text || '');
            
            // Add new term styling if created in last 24 hours
            if (term.is_new) {
                row.classList.add('explainer-new-term');
                
                // Add new icon next to term text
                const newIcon = document.createElement('span');
                newIcon.className = 'explainer-new-icon dashicons dashicons-star-filled';
                newIcon.setAttribute('title', 'New term (created in the last 24 hours)');
                newIcon.setAttribute('aria-label', 'New term');
                termText.appendChild(newIcon);
            }
            
            // Populate status toggle
            const toggle = row.querySelector('.explainer-term-toggle');
            const statusText = row.querySelector('.explainer-status-text');
            toggle.checked = term.enabled;
            toggle.addEventListener('change', (e) => {
                this.handleTermStatusToggle(term.id, e.target.checked);
            });
            
            // Update status text
            statusText.textContent = term.enabled ? 'Enabled' : 'Disabled';
            
            // Populate usage count
            const usageCount = row.querySelector('.explainer-usage-count');
            usageCount.textContent = term.usage_count || 0;
            
            // Bind action buttons
            const editButton = row.querySelector('.explainer-term-edit');
            const deleteButton = row.querySelector('.explainer-term-delete');
            
            // Note: Edit functionality handled by event delegation in handleTermAction
            
            deleteButton.addEventListener('click', () => {
                this.handleTermDelete(term.id);
            });
            
            // Set accessibility labels
            editButton.setAttribute('aria-label', `Edit term: ${term.text}`);
            deleteButton.setAttribute('aria-label', `Delete term: ${term.text}`);
            toggle.setAttribute('aria-label', `Toggle status for term: ${term.text}`);
            
            // Populate explanation textareas with existing data
            console.log('🔍 DEBUG: createTermRow - Processing term:', term);
            console.log('🔍 DEBUG: createTermRow - term.explanations:', term.explanations);
            console.log('🔍 DEBUG: createTermRow - typeof term.explanations:', typeof term.explanations);
            
            if (term.explanations) {
                console.log('🔍 DEBUG: createTermRow - Found explanations, looking for reading level blocks');
                const readingLevelBlocks = rowContainer.querySelectorAll('.explainer-reading-level-block');
                console.log('🔍 DEBUG: createTermRow - Found reading level blocks:', readingLevelBlocks.length);
                
                readingLevelBlocks.forEach((block, index) => {
                    const level = block.dataset.level;
                    const textarea = block.querySelector('.explainer-explanation-text');
                    console.log(`🔍 DEBUG: createTermRow - Block ${index}: level=${level}, textarea found=${!!textarea}`);
                    
                    if (textarea && term.explanations[level]) {
                        textarea.value = term.explanations[level];
                        console.log(`🔍 DEBUG: createTermRow - Populated ${level} with:`, term.explanations[level]);
                    } else {
                        console.log(`🔍 DEBUG: createTermRow - No explanation for ${level}, available levels:`, Object.keys(term.explanations || {}));
                    }
                });
            } else {
                console.log('🔍 DEBUG: createTermRow - No explanations found for term:', term.text);
            }
            
            return rowContainer;
        }
        
        /**
         * Handle search input
         */
        handleSearch(query) {
            // Clear existing debounce
            if (state.debounceTimer) {
                clearTimeout(state.debounceTimer);
            }
            
            // Update search state
            state.searchQuery = query.trim();
            
            // Show/hide clear button
            if (elements.searchClear) {
                elements.searchClear.style.display = state.searchQuery ? 'flex' : 'none';
            }
            
            // Debounce search for performance
            state.debounceTimer = setTimeout(() => {
                if (state.searchQuery.length === 0 || state.searchQuery.length >= config.searchMinLength) {
                    this.applyFilters();
                    this.announce(`Search updated. ${state.filteredTerms.length} terms found.`);
                }
            }, config.debounceDelay);
            
            this.debug(`Search query: "${state.searchQuery}"`);
        }
        
        /**
         * Clear search
         */
        clearSearch() {
            state.searchQuery = '';
            elements.searchInput.value = '';
            elements.searchClear.style.display = 'none';
            
            this.applyFilters();
            this.announce('Search cleared');
        }
        
        /**
         * Handle status filter change
         */
        handleStatusFilter(status) {
            state.statusFilter = status;
            this.applyFilters();
            
            const filterText = status === 'enabled' ? 'enabled' : status === 'disabled' ? 'disabled' : 'all';
            this.announce(`Filter changed to ${filterText} terms. ${state.filteredTerms.length} terms found.`);
        }
        
        /**
         * Apply client-side filters to terms
         */
        applyFilters() {
            // Start with all terms
            let filtered = [...state.terms];
            
            // Check if any filters are active
            const hasSearchFilter = state.searchQuery && state.searchQuery.length >= config.searchMinLength;
            const hasStatusFilter = state.statusFilter && state.statusFilter !== '';
            const hasActiveFilters = hasSearchFilter || hasStatusFilter;
            
            // Apply search filter
            if (hasSearchFilter) {
                const query = state.searchQuery.toLowerCase();
                filtered = filtered.filter(term => 
                    term.text && term.text.toLowerCase().includes(query)
                );
            }
            
            // Apply status filter
            if (hasStatusFilter) {
                if (state.statusFilter === 'enabled') {
                    filtered = filtered.filter(term => term.enabled === true || term.enabled === 1);
                } else if (state.statusFilter === 'disabled') {
                    filtered = filtered.filter(term => term.enabled === false || term.enabled === 0);
                }
                // 'all' or empty filter shows all terms
            }
            
            // Sort filtered terms alphabetically
            filtered.sort((a, b) => {
                const textA = (a.term || a.text || '').toLowerCase();
                const textB = (b.term || b.text || '').toLowerCase();
                return textA.localeCompare(textB);
            });
            
            // Update filtered terms
            state.filteredTerms = filtered;
            
            // Re-render terms with filtered results
            this.renderTerms();
            
            // Update pagination based on whether filters are active
            if (hasActiveFilters) {
                this.updateFilteredPagination();
            } else {
                // Restore normal pagination when no filters are active
                this.restoreNormalPagination();
            }
            
            // Show/hide empty state
            if (state.filteredTerms.length === 0) {
                this.showEmptyState();
            } else {
                this.hideEmptyState();
            }
            
            this.debug(`Applied filters: ${state.filteredTerms.length} of ${state.terms.length} terms shown`);
        }
        
        /**
         * Handle select all checkbox
         */
        handleSelectAll(checked) {
            // Bulk selection functionality removed
            return;
        }
        
        /**
         * Handle individual term selection
         */
        handleTermSelect(termId, checked) {
            // Individual term selection functionality removed
            return;
        }
        
        /**
         * Handle term status toggle
         */
        async handleTermStatusToggle(termId, enabled) {
            const term = state.terms.find(t => t.id === termId);
            if (!term) return;
            
            console.log('[WP AI Explainer DEBUG] Toggle clicked - termId:', termId, 'enabled:', enabled, 'term:', term);
            
            // Set loading state for this term
            this.setTermLoading(termId, true);
            
            try {
                console.log('[WP AI Explainer DEBUG] About to make API request with data:', {
                    term_id: termId,
                    enabled: enabled
                });
                
                const response = await this.apiRequest('update_term_status', {
                    term_id: termId,
                    enabled: enabled
                });
                
                console.log('[WP AI Explainer DEBUG] API response received:', response);
                
                if (response.success) {
                    // Update local state
                    term.enabled = enabled;
                    
                    // Update status text
                    const row = elements.termsList.querySelector(`[data-term-id="${termId}"]`);
                    const statusText = row?.querySelector('.explainer-status-text');
                    if (statusText) {
                        statusText.textContent = enabled ? 'Enabled' : 'Disabled';
                    }
                    
                    this.announce(`Term ${enabled ? 'enabled' : 'disabled'}: ${term.text}`);
                    
                    // Show success notification
                    console.log('🔍 DEBUG: Notification system available:', !!window.ExplainerPlugin?.Notifications);
                    console.log('🔍 DEBUG: Notification method:', typeof window.ExplainerPlugin?.Notifications?.show);
                    if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                        const message = `Term ${enabled ? 'enabled' : 'disabled'} successfully`;
                        console.log('🔍 DEBUG: Calling notification with message:', message);
                        window.ExplainerPlugin.Notifications.success(message);
                    } else {
                        console.log('🔍 DEBUG: Notification system not available');
                    }
                } else {
                    throw new Error(response.data?.message || 'Failed to update term status');
                }
                
            } catch (error) {
                // Revert toggle state
                const row = elements.termsList.querySelector(`[data-term-id="${termId}"]`);
                const toggle = row?.querySelector('.explainer-term-toggle');
                if (toggle) {
                    toggle.checked = !enabled;
                }
                
                this.handleError(`Error updating term status: ${error.message}`);
            } finally {
                this.setTermLoading(termId, false);
            }
        }
        
        // Note: handleTermEdit method removed - edit functionality now handled by handleTermAction via event delegation
        
        /**
         * Handle term deletion
         */
        async handleTermDelete(termId) {
            const term = state.terms.find(t => t.id === termId);
            if (!term) return;
            
            // Show confirmation dialog using notification system
            this.showDeleteConfirmation(term, termId);
        }
        
        /**
         * Show delete confirmation using notification system
         */
        showDeleteConfirmation(term, termId) {
            // Check if notification system supports confirmation
            if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications && window.ExplainerPlugin.Notifications.confirm) {
                // Use notification system confirmation if available
                window.ExplainerPlugin.Notifications.confirm(
                    `Are you sure you want to delete the term "${term.text}"? This action cannot be undone.`,
                    {
                        title: 'Confirm Deletion',
                        confirmText: 'Delete',
                        cancelText: 'Cancel',
                        type: 'danger'
                    }
                ).then((confirmed) => {
                    if (confirmed) {
                        this.executeTermDeletion(termId, term);
                    }
                }).catch(() => {
                    // User cancelled or error occurred
                });
            } else {
                // Fallback to browser confirm
                if (confirm(`Are you sure you want to delete the term "${term.text}"? This action cannot be undone.`)) {
                    this.executeTermDeletion(termId, term);
                }
            }
        }
        
        /**
         * Execute the actual term deletion
         */
        async executeTermDeletion(termId, term) {
            this.setTermLoading(termId, true);
            
            try {
                const response = await this.apiRequest('delete_term', {
                    term_id: termId
                });
                
                if (response.success) {
                    // Remove from local state
                    const index = state.terms.findIndex(t => t.id === termId);
                    if (index !== -1) {
                        state.terms.splice(index, 1);
                    }
                    
                    // Re-render terms
                    this.renderTerms();
                    this.updatePagination();
                    
                    // Show empty state if no terms left
                    if (state.terms.length === 0) {
                        this.showEmptyState();
                    }
                    
                    this.announce(`Term deleted: ${term.text}`);
                    
                    // Show success notification
                    if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                        window.ExplainerPlugin.Notifications.success('Term deleted successfully');
                    }
                } else {
                    throw new Error(response.data?.message || 'Failed to delete term');
                }
                
            } catch (error) {
                this.handleError(`Error deleting term: ${error.message}`);
            } finally {
                this.setTermLoading(termId, false);
            }
        }
        
        /**
         * Handle bulk actions
         */
        async handleBulkAction() {
            // Bulk action functionality removed
            return;
        }
        
        /**
         * Go to specific page
         */
        async goToPage(page) {
            if (page < 1 || page > state.totalPages || page === state.currentPage) {
                return;
            }
            
            state.currentPage = page;
            await this.loadTerms();
            
            this.announce(`Page ${page} of ${state.totalPages}`);
        }
        
        /**
         * Update pagination display
         */
        updatePagination() {
            if (!elements.paginationInfo || !elements.pageNumbers) return;
            
            // Update pagination info
            const start = Math.max(1, (state.currentPage - 1) * config.itemsPerPage + 1);
            const end = Math.min(state.totalTerms, state.currentPage * config.itemsPerPage);
            
            elements.paginationInfo.querySelector('.explainer-showing-start').textContent = start;
            elements.paginationInfo.querySelector('.explainer-showing-end').textContent = end;
            elements.paginationInfo.querySelector('.explainer-total-terms').textContent = state.totalTerms;
            
            // Update prev/next buttons
            if (elements.prevButton) {
                elements.prevButton.disabled = state.currentPage <= 1;
            }
            if (elements.nextButton) {
                elements.nextButton.disabled = state.currentPage >= state.totalPages;
            }
            
            // Update page numbers
            this.updatePageNumbers();
        }
        
        /**
         * Update pagination for filtered results
         */
        updateFilteredPagination() {
            if (!elements.paginationInfo || !elements.pageNumbers) return;
            
            // For client-side filtering, we show all filtered results without pagination
            // Update pagination info to show filtered counts
            const totalFiltered = state.filteredTerms.length;
            
            elements.paginationInfo.querySelector('.explainer-showing-start').textContent = totalFiltered > 0 ? 1 : 0;
            elements.paginationInfo.querySelector('.explainer-showing-end').textContent = totalFiltered;
            elements.paginationInfo.querySelector('.explainer-total-terms').textContent = totalFiltered;
            
            // Hide pagination controls for filtered results (showing all at once)
            if (elements.prevButton) {
                elements.prevButton.style.display = 'none';
            }
            if (elements.nextButton) {
                elements.nextButton.style.display = 'none';
            }
            if (elements.pageNumbers) {
                elements.pageNumbers.style.display = 'none';
            }
        }
        
        /**
         * Restore normal pagination when filters are cleared
         */
        restoreNormalPagination() {
            if (!elements.paginationInfo || !elements.pageNumbers) return;
            
            // Show pagination controls
            if (elements.prevButton) {
                elements.prevButton.style.display = '';
            }
            if (elements.nextButton) {
                elements.nextButton.style.display = '';
            }
            if (elements.pageNumbers) {
                elements.pageNumbers.style.display = '';
            }
            
            // Update pagination with normal values
            this.updatePagination();
        }
        
        /**
         * Update page numbers display
         */
        updatePageNumbers() {
            if (!elements.pageNumbers) return;
            
            elements.pageNumbers.innerHTML = '';
            
            const maxVisible = 5;
            let startPage = Math.max(1, state.currentPage - Math.floor(maxVisible / 2));
            let endPage = Math.min(state.totalPages, startPage + maxVisible - 1);
            
            // Adjust start page if we're near the end
            if (endPage - startPage + 1 < maxVisible) {
                startPage = Math.max(1, endPage - maxVisible + 1);
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const pageButton = document.createElement('button');
                pageButton.className = 'explainer-page-number';
                pageButton.textContent = i;
                pageButton.setAttribute('aria-label', `Go to page ${i}`);
                
                if (i === state.currentPage) {
                    pageButton.classList.add('current');
                    pageButton.setAttribute('aria-current', 'page');
                }
                
                pageButton.addEventListener('click', () => {
                    this.goToPage(i);
                });
                
                elements.pageNumbers.appendChild(pageButton);
            }
        }
        
        /**
         * Update select all checkbox state
         */
        updateSelectAllState() {
            // Select all state functionality removed
            return;
        }
        
        /**
         * Update bulk actions visibility
         */
        updateBulkActionsVisibility() {
            // Bulk actions visibility functionality removed
            return;
        }
        
        /**
         * Show loading state
         */
        showLoadingState() {
            this.debug('showLoadingState called');
            if (elements.loadingState) {
                elements.loadingState.classList.remove('hidden');
                this.debug('Showed loading state by removing hidden class');
            } else {
                this.debug('Loading state element not found');
            }
            if (elements.emptyState) {
                elements.emptyState.classList.add('hidden');
                this.debug('Hid empty state by adding hidden class');
            }
            if (elements.termsList) {
                elements.termsList.classList.add('hidden');
                this.debug('Hid terms list by adding hidden class');
            }
        }
        
        /**
         * Hide loading state
         */
        hideLoadingState() {
            this.debug('hideLoadingState called');
            if (elements.loadingState) {
                elements.loadingState.classList.add('hidden');
                this.debug('Hid loading state by adding hidden class');
            } else {
                this.debug('Loading state element not found');
            }
            if (elements.termsList) {
                elements.termsList.classList.remove('hidden');
                this.debug('Showed terms list by removing hidden class');
            }
        }
        
        /**
         * Show empty state
         */
        showEmptyState() {
            this.debug('showEmptyState called');
            if (elements.emptyState) {
                elements.emptyState.classList.remove('hidden');
                this.debug('Showed empty state by removing hidden class');
            } else {
                this.debug('Empty state element not found');
            }
            if (elements.termsList) {
                elements.termsList.classList.add('hidden');
                this.debug('Hid terms list by adding hidden class');
            }
        }
        
        /**
         * Hide empty state
         */
        hideEmptyState() {
            this.debug('hideEmptyState called');
            if (elements.emptyState) {
                elements.emptyState.classList.add('hidden');
                this.debug('Hid empty state by adding hidden class');
            } else {
                this.debug('Empty state element not found');
            }
            if (elements.termsList) {
                elements.termsList.classList.remove('hidden');
                this.debug('Showed terms list by removing hidden class');
            } else {
                this.debug('Terms list element not found');
            }
        }
        
        /**
         * Set loading state for individual term
         */
        setTermLoading(termId, loading) {
            const row = elements.termsList.querySelector(`[data-term-id="${termId}"]`);
            if (!row) return;
            
            if (loading) {
                row.classList.add('loading');
                state.loadingStates.set(termId, true);
            } else {
                row.classList.remove('loading');
                state.loadingStates.delete(termId);
            }
        }
        
        /**
         * Reset modal state
         */
        resetState() {
            state.terms = [];
            state.filteredTerms = [];
            // Selected terms removed
            state.currentPage = 1;
            state.totalPages = 1;
            state.totalTerms = 0;
            state.searchQuery = '';
            state.statusFilter = '';
            state.loadingStates.clear();
            state.postId = null;
            
            // Reset form elements
            if (elements.searchInput) elements.searchInput.value = '';
            if (elements.statusFilter) elements.statusFilter.value = '';
            if (elements.bulkSelect) elements.bulkSelect.value = '';
            if (elements.searchClear) elements.searchClear.style.display = 'none';
            
            // Bulk actions removed
        }
        
        /**
         * Get post ID from current page URL or form fields
         */
        getCurrentPagePostId() {
            // Method 1: Check URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            let postId = urlParams.get('post');
            
            if (postId) {
                return postId;
            }
            
            // Method 2: Check for post ID in hidden input (common in WordPress forms)
            const hiddenPostId = document.querySelector('input[name="post_ID"]');
            if (hiddenPostId) {
                return hiddenPostId.value;
            }
            
            // Method 3: Check for post ID in meta box context
            const metaBox = document.querySelector('[data-post-id]');
            if (metaBox) {
                return metaBox.getAttribute('data-post-id');
            }
            
            return null;
        }
        
        /**
         * Make API request to WordPress AJAX endpoint
         */
        async apiRequest(action, data = {}) {
            const formData = new FormData();
            formData.append('action', `explainer_${action}`);
            formData.append('nonce', window.explainerAjax?.nonce || '');
            
            console.log('[WP AI Explainer DEBUG] API Request setup:');
            console.log('  Action:', `explainer_${action}`);
            console.log('  Nonce:', window.explainerAjax?.nonce);
            console.log('  AJAX URL:', window.explainerAjax?.ajaxurl);
            console.log('  Data to send:', data);
            
            // Add data parameters
            Object.entries(data).forEach(([key, value]) => {
                formData.append(key, value);
                console.log('  FormData added:', key, '=', value);
            });
            
            try {
                const response = await fetch(window.explainerAjax?.ajaxurl || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                this.debug(`API request ${action}:`, result);
                
                return result;
                
            } catch (error) {
                this.debug(`API request ${action} failed:`, error);
                throw error;
            }
        }
        
        /**
         * Handle errors with user-friendly notifications
         */
        handleError(message) {
            console.error('AI Terms Modal Error:', message);
            
            // Show notification if available
            if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                window.ExplainerPlugin.Notifications.error(message, {
                    title: 'Error',
                    duration: 5000
                });
            } else {
                // Fallback to alert
                alert(message);
            }
            
            // Announce error to screen readers
            this.announce(`Error: ${message}`);
        }
        
        /**
         * Announce message to screen readers
         */
        announce(message) {
            if (elements.srAnnouncements) {
                elements.srAnnouncements.textContent = message;
                // Clear after announcement
                setTimeout(() => {
                    elements.srAnnouncements.textContent = '';
                }, 1000);
            }
        }
        
        /**
         * Debug logging helper
         */
        debug(message, data = null) {
            // Force debug logging temporarily to diagnose the state management issue
            console.log('[AITermsModal]', message, data);
            
            if (this.logger) {
                this.logger.debug(message, data, 'AITermsModal');
            } else if (window.explainerAjax?.debug) {
                console.log('[AITermsModal]', message, data);
            }
        }
        
        /**
         * Utility: Wait for next animation frame
         */
        nextFrame() {
            return new Promise(resolve => requestAnimationFrame(resolve));
        }
        
        /**
         * Utility: Delay execution
         */
        delay(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }
        
        /**
         * Handle term action clicks (edit, delete, toggles)
         */
        handleTermAction(e) {
            const target = e.target.closest('button') || e.target.closest('.explainer-level-toggle') || e.target.closest('.explainer-level-header');
            if (!target) return;
            
            // Handle edit button click
            if (target.classList.contains('explainer-term-edit')) {
                e.preventDefault();
                const termRow = target.closest('.explainer-term-row');
                this.toggleEditPanel(termRow);
                return;
            }
            
            // Handle reading level toggle click
            if (target.classList.contains('explainer-level-toggle') || target.closest('.explainer-level-header')) {
                e.preventDefault();
                const levelBlock = target.closest('.explainer-reading-level-block');
                this.toggleReadingLevel(levelBlock);
                return;
            }
            
            // Handle edit panel action buttons
            if (target.classList.contains('explainer-cancel-edit')) {
                e.preventDefault();
                const termRow = target.closest('.explainer-term-row');
                this.closeEditPanel(termRow);
                return;
            }
            
            if (target.classList.contains('explainer-save-term')) {
                e.preventDefault();
                const termRow = target.closest('.explainer-term-row');
                this.saveTermChanges(termRow);
                return;
            }
            
            if (target.classList.contains('explainer-save-explanation')) {
                console.log('🔍 DEBUG: Save explanation button clicked', target);
                e.preventDefault();
                const levelBlock = target.closest('.explainer-reading-level-block');
                
                // Find termRow by looking for the edit panel container and then finding the associated term row
                const editPanel = target.closest('.explainer-term-edit-panel');
                const container = editPanel?.parentElement;
                const termRow = container?.querySelector('.explainer-term-row');
                
                console.log('🔍 DEBUG: Found levelBlock:', levelBlock);
                console.log('🔍 DEBUG: Found editPanel:', editPanel);
                console.log('🔍 DEBUG: Found container:', container);
                console.log('🔍 DEBUG: Found termRow:', termRow);
                this.saveExplanation(termRow, levelBlock);
                return;
            }
            
            if (target.classList.contains('explainer-clear-explanation')) {
                e.preventDefault();
                const levelBlock = target.closest('.explainer-reading-level-block');
                this.clearExplanation(levelBlock);
                return;
            }
        }
        
        /**
         * Toggle edit panel visibility
         */
        toggleEditPanel(termRow) {
            if (!termRow) return;
            
            // Look for edit panel in the parent container (since we now return the full container)
            const container = termRow.parentElement;
            const editPanel = container?.querySelector('.explainer-term-edit-panel') || termRow.querySelector('.explainer-term-edit-panel');
            const editButton = termRow.querySelector('.explainer-term-edit');
            
            if (!editPanel || !editButton) return;
            
            const isVisible = editPanel.style.display !== 'none';
            
            if (isVisible) {
                this.closeEditPanel(termRow);
            } else {
                this.openEditPanel(termRow);
            }
        }
        
        /**
         * Open edit panel and load term data
         */
        async openEditPanel(termRow) {
            if (!termRow) return;
            
            const container = termRow.parentElement;
            const editPanel = container?.querySelector('.explainer-term-edit-panel') || termRow.querySelector('.explainer-term-edit-panel');
            const editButton = termRow.querySelector('.explainer-term-edit');
            const termId = termRow.dataset.termId;
            
            if (!editPanel || !editButton || !termId) return;
            
            // Update button state
            editButton.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6 6 18"/>
                    <path d="m6 6 12 12"/>
                </svg>
                <span class="explainer-sr-only">Cancel edit</span>
            `;
            editButton.setAttribute('aria-label', 'Cancel edit');
            
            // Show panel with slide animation
            editPanel.style.display = 'block';
            await this.nextFrame();
            
            // Load existing explanations for this term
            await this.loadTermExplanations(termId, editPanel);
            
            this.debug('Opened edit panel for term:', termId);
        }
        
        /**
         * Close edit panel
         */
        closeEditPanel(termRow) {
            if (!termRow) return;
            
            const container = termRow.parentElement;
            const editPanel = container?.querySelector('.explainer-term-edit-panel') || termRow.querySelector('.explainer-term-edit-panel');
            const editButton = termRow.querySelector('.explainer-term-edit');
            
            if (!editPanel || !editButton) return;
            
            // Reset button state
            editButton.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 20h9"/>
                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                </svg>
                <span class="explainer-sr-only">Edit</span>
            `;
            editButton.setAttribute('aria-label', 'Edit term');
            
            // Hide panel
            editPanel.style.display = 'none';
            
            // Reset all reading level toggles
            const levelToggles = editPanel.querySelectorAll('.explainer-level-toggle');
            const levelContents = editPanel.querySelectorAll('.explainer-level-content');
            
            levelToggles.forEach(toggle => {
                toggle.classList.remove('dashicons-arrow-down-alt2');
                toggle.classList.add('dashicons-arrow-right-alt2');
            });
            
            levelContents.forEach(content => {
                content.style.display = 'none';
            });
            
            this.debug('Closed edit panel for term');
        }
        
        /**
         * Toggle reading level content
         */
        toggleReadingLevel(levelBlock) {
            if (!levelBlock) return;
            
            const toggle = levelBlock.querySelector('.explainer-level-toggle');
            const content = levelBlock.querySelector('.explainer-level-content');
            
            if (!toggle || !content) return;
            
            const isVisible = content.style.display !== 'none';
            
            if (isVisible) {
                // Hide content
                content.style.display = 'none';
                toggle.classList.remove('dashicons-arrow-down-alt2');
                toggle.classList.add('dashicons-arrow-right-alt2');
            } else {
                // Show content
                content.style.display = 'block';
                toggle.classList.remove('dashicons-arrow-right-alt2');
                toggle.classList.add('dashicons-arrow-down-alt2');
            }
        }
        
        /**
         * Load existing explanations for a term
         */
        async loadTermExplanations(termId, editPanel) {
            try {
                // Find the term data in our state
                const term = state.terms.find(t => t.id === parseInt(termId));
                if (!term) {
                    this.debug('Term not found in state for ID:', termId);
                    return;
                }
                
                this.debug('Loading explanations for term:', term.text);
                
                // Use explanations from loaded term data (already includes explanations)
                if (term.explanations && Object.keys(term.explanations).length > 0) {
                    // Populate textareas with existing explanations
                    const explanations = term.explanations;
                    
                    Object.keys(explanations).forEach(levelKey => {
                        const levelBlock = editPanel.querySelector(`[data-level="${levelKey}"]`);
                        if (levelBlock) {
                            const textarea = levelBlock.querySelector('.explainer-explanation-text');
                            if (textarea && explanations[levelKey]) {
                                textarea.value = explanations[levelKey];
                            }
                        }
                    });
                    
                    this.debug(`Loaded explanations for term "${term.text}":`, explanations);
                } else {
                    this.debug('No existing explanations found for term:', term.text);
                    // Clear all textareas
                    const textareas = editPanel.querySelectorAll('.explainer-explanation-text');
                    textareas.forEach(textarea => {
                        textarea.value = '';
                    });
                }
                
            } catch (error) {
                this.debug('Error loading term explanations:', error.message);
                // Clear textareas on error
                const textareas = editPanel.querySelectorAll('.explainer-explanation-text');
                textareas.forEach(textarea => {
                    textarea.value = '';
                });
            }
        }
        
        /**
         * Save term changes
         */
        async saveTermChanges(termRow) {
            if (!termRow) return;
            
            const termId = termRow.dataset.termId;
            const container = termRow.parentElement;
            const editPanel = container?.querySelector('.explainer-term-edit-panel') || termRow.querySelector('.explainer-term-edit-panel');
            
            if (!termId || !editPanel) return;
            
            // Get term text for the save operation
            const termText = termRow.querySelector('.explainer-term-value')?.textContent;
            if (!termText) {
                this.handleError('Unable to find term text for saving');
                return;
            }
            
            // Collect explanation data
            const explanations = {};
            const levelBlocks = editPanel.querySelectorAll('.explainer-reading-level-block');
            
            levelBlocks.forEach(block => {
                const level = block.dataset.level;
                const textarea = block.querySelector('.explainer-explanation-text');
                if (textarea && textarea.value.trim()) {
                    explanations[level] = textarea.value.trim();
                }
            });
            
            this.debug('Saving term changes:', { termId, termText, explanations });
            
            // Show saving state
            const saveButton = editPanel.querySelector('.explainer-save-term');
            const originalText = saveButton?.textContent;
            if (saveButton) {
                saveButton.textContent = 'Saving...';
                saveButton.disabled = true;
            }
            
            try {
                // Make AJAX call to save the explanations
                const response = await this.apiRequest('save_term_explanations', {
                    term_id: termId,
                    term_text: termText,
                    post_id: state.postId,
                    explanations: JSON.stringify(explanations)
                });
                
                if (response.success) {
                    this.announce('Term explanations saved successfully');
                    this.closeEditPanel(termRow);
                } else {
                    throw new Error(response.data?.message || 'Failed to save explanations');
                }
                
            } catch (error) {
                this.handleError('Error saving explanations: ' + error.message);
            } finally {
                // Restore button state
                if (saveButton) {
                    saveButton.textContent = originalText;
                    saveButton.disabled = false;
                }
            }
        }
        
        /**
         * Generate explanation for a reading level
         */
        async generateExplanation(termRow, levelBlock) {
            if (!termRow || !levelBlock) return;
            
            const termId = termRow.dataset.termId;
            const termText = termRow.querySelector('.explainer-term-value')?.textContent;
            const level = levelBlock.dataset.level;
            const textarea = levelBlock.querySelector('.explainer-explanation-text');
            const saveBtn = levelBlock.querySelector('.explainer-save-explanation');
            
            if (!termId || !termText || !level || !textarea || !saveBtn) return;
            
            // Update button state
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Generating...';
            saveBtn.disabled = true;
            
            try {
                // Here you would make an AJAX call to generate the explanation
                // For now, just simulate a delay and add placeholder text
                await this.delay(1000);
                
                textarea.value = `Generated ${level} explanation for "${termText}"`;
                
                this.announce(`${level} explanation generated for ${termText}`);
            } catch (error) {
                this.handleError('Failed to generate explanation: ' + error.message);
            } finally {
                // Restore button state
                saveBtn.textContent = originalText;
                saveBtn.disabled = false;
            }
        }
        
        /**
         * Save explanation for a reading level
         */
        async saveExplanation(termRow, levelBlock) {
            console.log('🔍 DEBUG: saveExplanation called', { termRow, levelBlock });
            if (!termRow || !levelBlock) {
                console.log('🔍 DEBUG: Missing termRow or levelBlock');
                return;
            }
            
            const termId = termRow.dataset.termId;
            const termText = termRow.querySelector('.explainer-term-value')?.textContent;
            const level = levelBlock.dataset.level;
            const textarea = levelBlock.querySelector('.explainer-explanation-text');
            const saveBtn = levelBlock.querySelector('.explainer-save-explanation');
            
            console.log('🔍 DEBUG: Extracted data:', { termId, termText, level, textarea, saveBtn });
            
            if (!termId || !termText || !level || !textarea || !saveBtn) {
                console.log('🔍 DEBUG: Missing required elements - termId:', termId, 'termText:', termText, 'level:', level, 'textarea:', textarea, 'saveBtn:', saveBtn);
                return;
            }
            
            const explanationText = textarea.value.trim();
            console.log('🔍 DEBUG: Explanation text:', explanationText);
            if (!explanationText) {
                console.log('🔍 DEBUG: No explanation text, showing error');
                this.handleError('Please enter an explanation before saving');
                return;
            }
            
            // Update button state
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Saving...';
            saveBtn.disabled = true;
            console.log('🔍 DEBUG: Button state updated, making AJAX request');
            
            try {
                const requestData = {
                    term_id: termId,
                    term_text: termText,
                    post_id: this.getCurrentPostId(),
                    explanations: JSON.stringify({
                        [level]: explanationText
                    })
                };
                console.log('🔍 DEBUG: AJAX request data:', requestData);
                
                const response = await this.apiRequest('save_term_explanations', requestData);
                console.log('🔍 DEBUG: AJAX response:', response);
                
                if (response.success) {
                    this.announce(`${level} explanation saved for ${termText}`);
                    
                    // Show success notification
                    if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                        window.ExplainerPlugin.Notifications.success('Explanation saved successfully');
                    }
                } else {
                    throw new Error(response.data?.message || 'Failed to save explanation');
                }
            } catch (error) {
                this.handleError('Failed to save explanation: ' + error.message);
                
                // Show error notification
                if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                    window.ExplainerPlugin.Notifications.error('Failed to save explanation');
                }
            } finally {
                // Restore button state
                saveBtn.textContent = originalText;
                saveBtn.disabled = false;
            }
        }
        
        /**
         * Get current post ID
         */
        getCurrentPostId() {
            // Try to get post ID from WordPress editor
            if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
                const editor = wp.data.select('core/editor');
                if (editor && editor.getCurrentPostId) {
                    return editor.getCurrentPostId();
                }
            }
            
            // Fallback to URL parameter or global
            const urlParams = new URLSearchParams(window.location.search);
            const postId = urlParams.get('post') || urlParams.get('post_id') || window.pagenow === 'post' ? urlParams.get('post') : null;
            
            return postId ? parseInt(postId, 10) : state.postId || 0;
        }
        
        /**
         * Show validation error for a field
         */
        showValidationError(field, message) {
            if (!field) return;
            
            // Add error class
            field.classList.add('error');
            
            // Show error message
            this.handleError(message);
            
            // Scroll to field
            this.scrollToField(field);
        }
        
        /**
         * Clear all validation errors
         */
        clearValidationErrors() {
            const errorFields = elements.createTermForm?.querySelectorAll('.error');
            if (errorFields) {
                errorFields.forEach(field => field.classList.remove('error'));
            }
        }
        
        /**
         * Scroll to field smoothly
         */
        scrollToField(field) {
            if (!field || !elements.createFormContent) return;
            
            // Calculate field position relative to form content
            const formRect = elements.createFormContent.getBoundingClientRect();
            const fieldRect = field.getBoundingClientRect();
            const scrollTop = elements.createFormContent.scrollTop;
            
            // Calculate target scroll position
            const targetScroll = scrollTop + (fieldRect.top - formRect.top) - 20; // 20px offset
            
            // Smooth scroll to field
            elements.createFormContent.scrollTo({
                top: Math.max(0, targetScroll),
                behavior: 'smooth'
            });
        }
        
        /**
         * Clear explanation for a reading level
         */
        clearExplanation(levelBlock) {
            if (!levelBlock) return;
            
            const textarea = levelBlock.querySelector('.explainer-explanation-text');
            if (textarea) {
                textarea.value = '';
                this.announce('Explanation cleared');
            }
        }
        
        /**
         * Show create new term form
         */
        showCreateForm() {
            if (!elements.createTermForm) return;
            
            // Hide terms list and show create form
            elements.termsList.style.display = 'none';
            elements.emptyState.style.display = 'none';
            elements.createTermForm.classList.remove('hidden');
            
            // Lock background scrolling
            if (elements.modal) {
                const modalContainer = elements.modal.querySelector('.explainer-modal-container');
                if (modalContainer) {
                    modalContainer.classList.add('scroll-locked');
                }
            }
            
            // Reset form
            this.resetCreateForm();
            
            // Focus on term input
            elements.createFormElements.termTextInput?.focus();
            
            state.showCreateForm = true;
            this.announce('Create new term form opened');
        }
        
        /**
         * Hide create new term form
         */
        hideCreateForm() {
            if (!elements.createTermForm) return;
            
            // Hide create form and show terms list
            elements.createTermForm.classList.add('hidden');
            elements.termsList.style.display = 'block';
            
            // Unlock background scrolling
            if (elements.modal) {
                const modalContainer = elements.modal.querySelector('.explainer-modal-container');
                if (modalContainer) {
                    modalContainer.classList.remove('scroll-locked');
                }
            }
            
            // Reset form
            this.resetCreateForm();
            
            state.showCreateForm = false;
            this.announce('Create new term form closed');
        }
        
        /**
         * Reset create form to initial state
         */
        resetCreateForm() {
            // Clear term text input
            if (elements.createFormElements.termTextInput) {
                elements.createFormElements.termTextInput.value = '';
            }
            
            // Clear all explanation textareas
            Object.values(elements.createFormElements.explanationTextareas).forEach(textarea => {
                if (textarea) textarea.value = '';
            });
            
            
            // Hide test result
            if (elements.createFormElements.testResult) {
                elements.createFormElements.testResult.classList.add('hidden');
            }
            
            // Reset form data
            state.createFormData = {
                termText: '',
                explanations: {}
            };
        }
        
        /**
         * Test if term exists in post content
         */
        testTermInContent() {
            // Find the term input (may need to re-query if not cached)
            const termInput = elements.createFormElements.termTextInput || document.querySelector('#explainer-new-term-text');
            const termText = termInput?.value?.trim();
            
            if (!termText) {
                this.handleError('Please enter a term to test');
                return;
            }
            
            this.debug(`Testing term: "${termText}"`);
            
            // Get post content from editor (TinyMCE or block editor)
            let postContent = '';
            
            // Try to get content from WordPress editor
            if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
                // Block editor (Gutenberg)
                const editor = wp.data.select('core/editor');
                if (editor) {
                    postContent = editor.getEditedPostContent() || '';
                }
            } else if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
                // Classic editor
                postContent = tinyMCE.activeEditor.getContent() || '';
            }
            
            // Fallback to textarea content
            if (!postContent) {
                const contentTextarea = document.querySelector('#content, [name="content"]');
                if (contentTextarea) {
                    postContent = contentTextarea.value || '';
                }
            }
            
            // Strip HTML tags for text search
            const textContent = postContent.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            
            // Test if term exists (case insensitive)
            const termFound = textContent.toLowerCase().includes(termText.toLowerCase());
            
            // Show result - re-query elements to ensure they exist
            const testResultElement = elements.createFormElements.testResult || document.querySelector('.explainer-test-result');
            
            if (testResultElement) {
                testResultElement.classList.remove('hidden');
                
                const foundElement = testResultElement.querySelector('.explainer-test-found');
                const notFoundElement = testResultElement.querySelector('.explainer-test-not-found');
                
                // Hide both initially
                if (foundElement) foundElement.style.display = 'none';
                if (notFoundElement) notFoundElement.style.display = 'none';
                
                // Show the correct one based on result
                if (termFound) {
                    if (foundElement) foundElement.style.display = 'flex';
                    this.announce(`Term "${termText}" found in post content`);
                    this.debug(`Term "${termText}" found in content`);
                } else {
                    if (notFoundElement) notFoundElement.style.display = 'flex';
                    this.announce(`Term "${termText}" not found in post content`);
                    this.debug(`Term "${termText}" not found in content`);
                }
            } else {
                this.debug('Test result element not found');
            }
        }
        
        /**
         * Save new term with explanations
         */
        async saveNewTerm() {
            // Clear any existing error states
            this.clearValidationErrors();
            
            const termText = elements.createFormElements.termTextInput?.value?.trim();
            if (!termText) {
                this.showValidationError(elements.createFormElements.termTextInput, 'Please enter a term');
                return;
            }
            
            // Collect explanations from textareas
            const explanations = {};
            Object.entries(elements.createFormElements.explanationTextareas).forEach(([level, textarea]) => {
                if (textarea && textarea.value.trim()) {
                    explanations[level] = textarea.value.trim();
                }
            });
            
            // Check if standard level explanation is provided (mandatory)
            if (!explanations.standard || !explanations.standard.trim()) {
                this.handleError('Standard reading level explanation is required');
                
                // Highlight the standard textarea
                const standardTextarea = elements.createFormElements.explanationTextareas.standard;
                if (standardTextarea) {
                    standardTextarea.classList.add('error');
                    standardTextarea.focus();
                }
                return;
            }
            
            
            // Update button state
            const saveButton = elements.createFormElements.saveButton;
            const originalText = saveButton?.textContent || 'Save new term';
            if (saveButton) {
                saveButton.textContent = 'Saving...';
                saveButton.disabled = true;
            }
            
            try {
                const response = await this.apiRequest('create_term', {
                    term_text: termText,
                    explanations: JSON.stringify(explanations),
                    post_id: state.postId
                });
                
                if (response.success) {
                    this.announce('New term created successfully');
                    
                    // Hide create form and refresh terms list
                    this.hideCreateForm();
                    await this.loadTerms();
                    
                    // Show success notification
                    if (window.ExplainerPlugin.Notifications) {
                        window.ExplainerPlugin.Notifications.success('Term created successfully');
                    }
                } else {
                    throw new Error(response.data?.message || 'Failed to create term');
                }
                
            } catch (error) {
                this.handleError('Error creating term: ' + error.message);
            } finally {
                // Restore button state
                if (saveButton) {
                    saveButton.textContent = originalText;
                    saveButton.disabled = false;
                }
            }
        }
    }
    
    // Export to global namespace
    window.ExplainerPlugin.AITermsModal = AITermsModal;
    
    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.ExplainerPlugin.aiTermsModal = new AITermsModal();
        });
    } else {
        window.ExplainerPlugin.aiTermsModal = new AITermsModal();
    }
    
})();