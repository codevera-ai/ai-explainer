/**
 * WP AI Explainer - In-Page Notification System
 * Replaces native alert/confirm/prompt with branded, accessible notifications
 */

(function() {
    'use strict';

    if (!window.ExplainerPlugin) {
        window.ExplainerPlugin = {};
    }

    const NotificationSystem = {
        container: null,
        notifications: new Map(),
        zIndex: 99999,
        
        init() {
            this.createContainer();
            this.bindKeyboardEvents();
        },

        createContainer() {
            if (this.container) return;

            this.container = document.createElement('div');
            this.container.className = 'explainer-notifications-container';
            this.container.setAttribute('aria-live', 'polite');
            this.container.setAttribute('aria-atomic', 'false');
            
            // Enhanced positioning for WordPress admin
            this.container.style.position = 'fixed';
            this.container.style.zIndex = '999999';
            this.container.style.maxWidth = '400px';
            this.container.style.width = '100%';
            this.container.style.pointerEvents = 'none';
            
            // Detect WordPress admin and adjust positioning
            if (document.body.classList.contains('wp-admin')) {
                this.container.style.top = '60px';
                this.container.style.right = '40px';
            } else {
                this.container.style.top = '20px';
                this.container.style.right = '20px';
            }
            
            document.body.appendChild(this.container);
        },

        show(options = {}) {
            const defaults = {
                type: 'info',
                title: '',
                message: '',
                duration: 10000,
                dismissible: true,
                actions: null,
                priority: 'normal'
            };

            const config = { ...defaults, ...options };
            const id = this.generateId();

            if (config.priority === 'high') {
                this.clearAll();
            }

            const notification = this.createNotification(id, config);
            this.notifications.set(id, { element: notification, config });
            this.container.appendChild(notification);

            requestAnimationFrame(() => {
                notification.classList.add('explainer-notification-visible');
            });

            if (config.duration > 0) {
                this.startTimer(id, config.duration);
            }

            return id;
        },

        createNotification(id, config) {
            const notification = document.createElement('div');
            notification.className = `explainer-notification explainer-notification-${config.type}`;
            notification.setAttribute('role', config.actions ? 'dialog' : 'alert');
            notification.setAttribute('aria-labelledby', `notification-title-${id}`);
            notification.setAttribute('data-notification-id', id);

            const iconHtml = this.getIcon(config.type);
            const actionsHtml = config.actions ? this.renderActions(config.actions, id) : '';

            const contentDiv = document.createElement('div');
            contentDiv.className = 'explainer-notification-content';
            
            const iconDiv = document.createElement('div');
            iconDiv.className = 'explainer-notification-icon';
            iconDiv.style.width = '20px';
            iconDiv.style.height = '20px';
            iconDiv.style.flexShrink = '0';
            iconDiv.style.display = 'flex';
            iconDiv.style.alignItems = 'center';
            iconDiv.style.justifyContent = 'center';
            iconDiv.style.overflow = 'hidden';
            iconDiv.innerHTML = iconHtml;
            
            const bodyDiv = document.createElement('div');
            bodyDiv.className = 'explainer-notification-body';
            
            if (config.title) {
                const titleDiv = document.createElement('div');
                titleDiv.className = 'explainer-notification-title';
                titleDiv.id = `notification-title-${id}`;
                titleDiv.textContent = config.title;
                bodyDiv.appendChild(titleDiv);
            }
            
            const messageDiv = document.createElement('div');
            messageDiv.className = 'explainer-notification-message';
            messageDiv.textContent = config.message;
            bodyDiv.appendChild(messageDiv);
            
            if (config.actions) {
                const actionsDiv = document.createElement('div');
                actionsDiv.innerHTML = actionsHtml;
                bodyDiv.appendChild(actionsDiv);
            }
            
            contentDiv.appendChild(iconDiv);
            contentDiv.appendChild(bodyDiv);
            
            if (config.dismissible) {
                const closeBtn = document.createElement('button');
                closeBtn.type = 'button';
                closeBtn.className = 'explainer-notification-close';
                closeBtn.setAttribute('aria-label', 'Close notification');
                closeBtn.innerHTML = '×';
                closeBtn.onclick = () => this.hide(id);
                contentDiv.appendChild(closeBtn);
            }
            
            notification.appendChild(contentDiv);

            // Add timer bar if notification has duration (dismissible notifications can still have timer bars)
            if (config.duration > 0) {
                const timerBar = document.createElement('div');
                timerBar.className = 'explainer-notification-timer-bar';
                
                const timerProgress = document.createElement('div');
                timerProgress.className = 'explainer-notification-timer-progress';
                timerBar.appendChild(timerProgress);
                
                notification.appendChild(timerBar);
            }

            return notification;
        },

        getIcon(type) {
            const icons = {
                success: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
                error: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
                warning: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
                info: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
                loading: '<svg viewBox="0 0 20 20" fill="currentColor" class="explainer-notification-spinner"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/></svg>'
            };
            return icons[type] || icons.info;
        },

        renderActions(actions, notificationId) {
            if (!Array.isArray(actions) || actions.length === 0) return '';

            const buttonsHtml = actions.map((action, index) => {
                const buttonClass = action.primary ? 'explainer-notification-button-primary' : 'explainer-notification-button-secondary';
                const onClick = `ExplainerPlugin.Notifications.handleAction('${notificationId}', ${index})`;
                return `<button type="button" class="explainer-notification-button ${buttonClass}" onclick="${onClick}">${this.escapeHtml(action.text)}</button>`;
            }).join('');

            return `<div class="explainer-notification-actions">${buttonsHtml}</div>`;
        },

        handleAction(notificationId, actionIndex) {
            const notification = this.notifications.get(notificationId);
            if (!notification) return;

            const action = notification.config.actions[actionIndex];
            if (action && action.callback) {
                action.callback();
            }

            this.hide(notificationId);
        },

        startTimer(id, duration) {
            const notification = this.notifications.get(id);
            if (!notification) return;

            const timerProgress = notification.element.querySelector('.explainer-notification-timer-progress');
            if (!timerProgress) return;

            // Force inline styles to ensure visibility
            timerProgress.style.background = 'linear-gradient(90deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%)';
            timerProgress.style.boxShadow = '0 0 4px rgba(255, 255, 255, 0.3)';

            // Start timer animation
            timerProgress.style.animation = `explainer-timer-countdown ${duration}ms linear`;
            
            // Hide notification when timer completes
            const timer = setTimeout(() => {
                this.hide(id);
            }, duration);

            // Store timer reference for potential cancellation
            notification.timer = timer;
        },

        hide(id) {
            const notification = this.notifications.get(id);
            if (!notification) return;

            // Clear timer if it exists
            if (notification.timer) {
                clearTimeout(notification.timer);
                delete notification.timer;
            }

            notification.element.classList.add('explainer-notification-hiding');
            
            setTimeout(() => {
                if (notification.element && notification.element.parentNode) {
                    notification.element.parentNode.removeChild(notification.element);
                }
                this.notifications.delete(id);
            }, 300);
        },

        clearAll() {
            this.notifications.forEach((notification, id) => {
                this.hide(id);
            });
        },

        success(message, options = {}) {
            return this.show({ ...options, type: 'success', message });
        },

        error(message, options = {}) {
            return this.show({ ...options, type: 'error', message, duration: 8000 });
        },

        warning(message, options = {}) {
            return this.show({ ...options, type: 'warning', message, duration: 6000 });
        },

        info(message, options = {}) {
            return this.show({ ...options, type: 'info', message });
        },

        loading(message, options = {}) {
            return this.show({ ...options, type: 'loading', message, duration: 0, dismissible: false });
        },

        confirm(message, options = {}) {
            return new Promise((resolve) => {
                const actions = [
                    {
                        text: options.cancelText || 'Cancel',
                        callback: () => resolve(false)
                    },
                    {
                        text: options.confirmText || 'Confirm',
                        primary: true,
                        callback: () => resolve(true)
                    }
                ];

                this.show({
                    type: 'warning',
                    title: options.title || 'Confirmation Required',
                    message,
                    actions,
                    dismissible: false,
                    priority: 'high',
                    ...options
                });
            });
        },

        bindKeyboardEvents() {
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const visibleNotifications = Array.from(this.notifications.keys());
                    if (visibleNotifications.length > 0) {
                        const lastNotification = visibleNotifications[visibleNotifications.length - 1];
                        const notification = this.notifications.get(lastNotification);
                        if (notification && notification.config.dismissible) {
                            this.hide(lastNotification);
                        }
                    }
                }
            });
        },

        generateId() {
            return 'notification-' + Math.random().toString(36).substr(2, 9) + '-' + Date.now();
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    window.ExplainerPlugin.Notifications = NotificationSystem;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            NotificationSystem.init();
        });
    } else {
        NotificationSystem.init();
    }

    window.ExplainerPlugin.replaceAlert = function(message, type = 'info') {
        return NotificationSystem.show({
            type: type,
            message: message,
            duration: type === 'error' ? 8000 : 5000
        });
    };

    window.ExplainerPlugin.replaceConfirm = function(message, options = {}) {
        return NotificationSystem.confirm(message, options);
    };

})();