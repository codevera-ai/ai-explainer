<?php
/**
 * Meta box template for AI Explanations - Status Dashboard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Enhanced status configuration with WordPress-native styling
$status_config = array(
    'not_scanned' => array(
        'label' => __('Not Scanned', 'ai-explainer'),
        'icon' => 'dashicons-minus',
        'class' => 'status-idle',
        'color' => '#646970',
        'description' => __('Post has not been scanned for AI terms yet', 'ai-explainer')
    ),
    'queued' => array(
        'label' => __('Queued', 'ai-explainer'),
        'icon' => 'dashicons-clock',
        'class' => 'status-queued',
        'color' => '#0073aa',
        'description' => __('Post is queued for AI scanning', 'ai-explainer')
    ),
    'processing' => array(
        'label' => __('Processing', 'ai-explainer'),
        'icon' => 'dashicons-update',
        'class' => 'status-processing',
        'color' => '#ff922b',
        'description' => __('Post is currently being processed', 'ai-explainer')
    ),
    'processed' => array(
        'label' => __('Processed', 'ai-explainer'),
        'icon' => 'dashicons-yes-alt',
        'class' => 'status-success',
        'color' => '#00a32a',
        'description' => __('Post has been successfully scanned', 'ai-explainer')
    ),
    'outdated' => array(
        'label' => __('Outdated', 'ai-explainer'),
        'icon' => 'dashicons-warning',
        'class' => 'status-warning',
        'color' => '#ffb900',
        'description' => __('Post has been modified since last scan', 'ai-explainer')
    ),
    'error' => array(
        'label' => __('Failed', 'ai-explainer'),
        'icon' => 'dashicons-dismiss',
        'class' => 'status-error',
        'color' => '#d63638',
        'description' => __('Scanning failed with an error', 'ai-explainer')
    )
);

$current_status_config = isset($status_config[$status]) ? $status_config[$status] : $status_config['not_scanned'];

// Queue and processing states
$is_queued = ($status === 'queued' || $status === 'processing');
$is_processing = ($status === 'processing');

// Frontend display default
$default_enabled = in_array($status, ['queued', 'processing', 'processed']);
$inline_enabled = $scan_enabled !== null ? $scan_enabled : $default_enabled;
?>

<div class="explainer-status-dashboard">
    
    <!-- Status Overview Card -->
    <div class="status-overview-card">
        <div class="status-primary">
            <div class="status-indicator <?php echo esc_attr($current_status_config['class']); ?>" 
             data-status="<?php echo esc_attr($status); ?>" 
             role="status" 
             aria-label="<?php echo esc_attr($current_status_config['description']); ?>">
                <span class="dashicons <?php echo esc_attr($current_status_config['icon']); ?> status-icon"></span>
                <div class="status-text">
                    <span class="status-label"><?php echo esc_html($current_status_config['label']); ?></span>
                    <?php if ($status === 'processed' && $term_count > 0): ?>
                        <span class="status-meta"><?php
                            echo esc_html( sprintf(
                                /* translators: %d: number of terms found */
                                _n('%d term found', '%d terms found', $term_count, 'ai-explainer'),
                                $term_count
                            ) );
                        ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($last_scan_date): ?>
        <div class="status-meta-info">
            <span class="dashicons dashicons-calendar-alt"></span>
            <span><?php
                echo esc_html( sprintf(
                    /* translators: %s: formatted date and time of last scan */
                    __('Last scanned: %s', 'ai-explainer'),
                    date_i18n('M j, Y \a\t g:i A', strtotime($last_scan_date))
                ) );
            ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Controls Grid -->
    <div class="controls-grid">
        
        <!-- Queue Control -->
        <div class="control-item">
            <div class="control-header">
                <label class="control-label"><?php esc_html_e('Scan Control', 'ai-explainer'); ?></label>
                <button type="button" 
                        class="button button-primary scan-toggle-btn <?php echo $is_queued ? 'queued' : ''; ?>"
                        data-queued="<?php echo $is_queued ? '1' : '0'; ?>"
                        <?php disabled($is_processing); ?>>
                    <span class="dashicons <?php echo $is_queued ? 'dashicons-yes' : 'dashicons-search'; ?>"></span>
                    <span class="btn-text-scan"><?php esc_html_e('Scan Post', 'ai-explainer'); ?></span>
                    <span class="btn-text-queued"><?php esc_html_e('Remove from Queue', 'ai-explainer'); ?></span>
                </button>
            </div>
            
            <?php if ($status === 'processing'): ?>
                <p class="control-help processing">
                    <span class="dashicons dashicons-update spin"></span>
                    <?php esc_html_e('Currently processing...', 'ai-explainer'); ?>
                </p>
            <?php elseif ($status === 'processed'): ?>
                <p class="control-help success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('Re-scan to update terms', 'ai-explainer'); ?>
                </p>
            <?php elseif ($status === 'error'): ?>
                <p class="control-help error">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e('Retry scanning', 'ai-explainer'); ?>
                </p>
            <?php elseif ($status === 'outdated'): ?>
                <p class="control-help warning">
                    <span class="dashicons dashicons-clock"></span>
                    <?php esc_html_e('Content updated - scan recommended', 'ai-explainer'); ?>
                </p>
            <?php elseif ($status === 'queued'): ?>
                <p class="control-help queued">
                    <span class="dashicons dashicons-clock"></span>
                    <?php esc_html_e('Click to remove from queue', 'ai-explainer'); ?>
                </p>
                <!-- <div class="control-actions">
                    <button type="button" class="button button-link refresh-status-btn" 
                            data-post-id="<?php echo esc_attr($post->ID); ?>">
                        <?php esc_html_e('Refresh Status', 'ai-explainer'); ?>
                    </button>
                    <span class="spinner"></span>
                </div> -->
            <?php else: ?>
                <p class="control-help">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e('Queue post for AI term scanning', 'ai-explainer'); ?>
                </p>
            <?php endif; ?>
            
            <input type="hidden" 
                   name="explainer_queue_post" 
                   value="<?php echo $is_queued ? '1' : '0'; ?>" 
                   class="queue-input" />
        </div>

        <!-- Frontend Display Toggle -->
        <div class="control-item">
            <div class="control-header">
                <label class="control-label"><?php esc_html_e('Frontend Display', 'ai-explainer'); ?></label>
            </div>
            <div class="control-content">
                <label class="toggle-switch">
                    <span class="sr-only"><?php esc_html_e('Toggle frontend display', 'ai-explainer'); ?></span>
                    <input type="checkbox" 
                           name="explainer_scan_enabled" 
                           value="1" 
                           <?php checked($inline_enabled); ?>
                           class="display-checkbox"
                           aria-describedby="frontend-display-help" />
                    <span class="toggle-slider" aria-hidden="true"></span>
                </label>
                <span class="toggle-label"><?php esc_html_e('Enable explanations on frontend', 'ai-explainer'); ?></span>
            </div>
            <?php if ($inline_enabled): ?>
                <p class="control-help success" id="frontend-display-help">
                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                    <?php esc_html_e('Tooltips visible to visitors', 'ai-explainer'); ?>
                    <?php
                    // Show auto-scan indicator if this was auto-enabled
                    $post_age = time() - strtotime($post->post_date);
                    $is_new_post = $post_age < (60 * 5); // Within 5 minutes
                    $auto_scan_enabled = false;
                    
                    if ($is_new_post && $inline_enabled) {
                        if ($post->post_type === 'post' && get_option('explainer_auto_scan_posts', false)) {
                            $auto_scan_enabled = true;
                        } elseif ($post->post_type === 'page' && get_option('explainer_auto_scan_pages', false)) {
                            $auto_scan_enabled = true;
                        }
                    }
                    
                    if ($auto_scan_enabled): ?>
                        <br><small style="color: #0073aa; font-style: italic;">
                            <span class="dashicons dashicons-update-alt" style="font-size: 12px; vertical-align: middle;"></span>
                            <?php esc_html_e('Auto-enabled by admin setting', 'ai-explainer'); ?>
                        </small>
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <p class="control-help" id="frontend-display-help">
                    <span class="dashicons dashicons-hidden" aria-hidden="true"></span>
                    <?php esc_html_e('Tooltips hidden from visitors', 'ai-explainer'); ?>
                </p>
            <?php endif; ?>
        </div>

    </div>

    <!-- Additional Actions -->
    <?php if (($status === 'processed' || $status === 'outdated') && $term_count > 0): ?>
    <div class="additional-actions">
        <button type="button" class="button button-secondary manage-terms-full-width" data-explainer-open-terms-modal data-post-id="<?php echo esc_attr($post->ID); ?>">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e('Manage Terms', 'ai-explainer'); ?>
        </button>
    </div>
    <?php endif; ?>


</div>

<script>
// Define explainerAdmin object for inline script
window.explainerAdmin = {
    ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
    nonce: '<?php echo esc_js(wp_create_nonce('explainer_admin_nonce')); ?>'
};

document.addEventListener('DOMContentLoaded', function() {
    console.log('WP AI Explainer: DOM loaded, looking for elements');
    const scanBtn = document.querySelector('.scan-toggle-btn');
    const queueInput = document.querySelector('.queue-input');
    
    console.log('WP AI Explainer: Elements found - scanBtn:', scanBtn, 'queueInput:', queueInput);
    console.log('WP AI Explainer: explainerAdmin object:', window.explainerAdmin);
    
    if (scanBtn && queueInput && typeof explainerAdmin !== 'undefined') {
        console.log('WP AI Explainer: Setting up click handler');
        scanBtn.addEventListener('click', function(e) {
            console.log('WP AI Explainer: Button clicked!', e);
            e.preventDefault();
            
            if (this.disabled) {
                console.log('WP AI Explainer: Button is disabled, returning');
                return;
            }
            
            const isQueued = this.getAttribute('data-queued') === '1';
            const newQueued = !isQueued;
            const postId = <?php echo esc_js($post->ID); ?>;
            
            // Disable button during request
            this.disabled = true;
            this.style.opacity = '0.6';
            
            // Add loading state
            this.classList.add('loading');
            this.querySelector('.dashicons').classList.add('dashicons-update');
            
            // Show spinner in control actions
            const spinner = document.querySelector('.control-actions .spinner');
            if (spinner) {
                spinner.classList.add('is-active');
            }
            
            // Make AJAX request
            const formData = new FormData();
            formData.append('action', 'explainer_toggle_post_queue');
            formData.append('post_id', postId);
            formData.append('queue_action', newQueued ? 'queue' : 'dequeue');
            formData.append('nonce', explainerAdmin.nonce);
            
            console.log('WP AI Explainer: Making AJAX request to:', explainerAdmin.ajaxUrl);
            console.log('WP AI Explainer: Form data:', {
                action: 'explainer_toggle_post_queue',
                post_id: postId,
                queue_action: newQueued ? 'queue' : 'dequeue',
                nonce: explainerAdmin.nonce
            });
            
            fetch(explainerAdmin.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('WP AI Explainer: Response received:', response);
                return response.json();
            })
            .then(data => {
                console.log('WP AI Explainer: Response data:', data);
                if (data.success) {
                    // Update button state
                    this.setAttribute('data-queued', newQueued ? '1' : '0');
                    this.classList.toggle('queued', newQueued);
                    
                    // Update hidden input
                    queueInput.value = newQueued ? '1' : '0';
                    
                    // Update status display
                    const statusIndicator = document.querySelector('.status-indicator');
                    if (statusIndicator) {
                        if (newQueued) {
                            // Update to queued status
                            statusIndicator.setAttribute('data-status', 'queued');
                            statusIndicator.className = 'status-indicator status-queued';
                            statusIndicator.setAttribute('aria-label', 'Post is queued for AI scanning');
                            
                            const statusIcon = statusIndicator.querySelector('.status-icon');
                            if (statusIcon) {
                                statusIcon.className = 'dashicons dashicons-clock status-icon';
                            }
                            
                            const statusLabel = statusIndicator.querySelector('.status-label');
                            if (statusLabel) {
                                statusLabel.textContent = 'Queued';
                            }
                        } else {
                            // Update to not_scanned status when dequeued
                            statusIndicator.setAttribute('data-status', 'not_scanned');
                            statusIndicator.className = 'status-indicator status-idle';
                            statusIndicator.setAttribute('aria-label', 'Post has not been scanned for AI terms yet');
                            
                            const statusIcon = statusIndicator.querySelector('.status-icon');
                            if (statusIcon) {
                                statusIcon.className = 'dashicons dashicons-minus status-icon';
                            }
                            
                            const statusLabel = statusIndicator.querySelector('.status-label');
                            if (statusLabel) {
                                statusLabel.textContent = 'Not Scanned';
                            }
                        }
                    }
                    
                    // Update help text
                    const controlHelp = document.querySelector('.control-help');
                    if (controlHelp) {
                        const helpIcon = controlHelp.querySelector('.dashicons');
                        const helpTextNode = controlHelp.lastChild;
                        
                        if (newQueued) {
                            // Change to "Click to remove from queue"
                            controlHelp.className = 'control-help queued';
                            if (helpIcon) {
                                helpIcon.className = 'dashicons dashicons-clock';
                            }
                            if (helpTextNode && helpTextNode.nodeType === Node.TEXT_NODE) {
                                helpTextNode.textContent = 'Click to remove from queue';
                            }
                        } else {
                            // Change to "Queue post for AI term scanning"
                            controlHelp.className = 'control-help';
                            if (helpIcon) {
                                helpIcon.className = 'dashicons dashicons-search';
                            }
                            if (helpTextNode && helpTextNode.nodeType === Node.TEXT_NODE) {
                                helpTextNode.textContent = 'Queue post for AI term scanning';
                            }
                        }
                    }
                    
                    // Show success message briefly
                    const helpText = this.parentNode.querySelector('.help-text');
                    if (helpText) {
                        const originalText = helpText.textContent;
                        helpText.textContent = data.data.message;
                        helpText.style.color = '#00a32a';
                        setTimeout(() => {
                            helpText.textContent = originalText;
                            helpText.style.color = '';
                        }, 3000);
                    }
                } else {
                    console.log('WP AI Explainer: Error response from server:', data);
                    // Show error message
                    alert('Error: ' + (data.data.message || 'Failed to update queue status'));
                }
            })
            .catch(error => {
                console.error('WP AI Explainer: AJAX request failed:', error);
                alert('Error: Failed to communicate with server');
            })
            .finally(() => {
                // Remove loading state
                this.classList.remove('loading');
                this.querySelector('.dashicons').classList.remove('dashicons-update');
                
                // Hide spinner in control actions
                const spinner = document.querySelector('.control-actions .spinner');
                if (spinner) {
                    spinner.classList.remove('is-active');
                }
                
                // Re-enable button
                this.disabled = false;
                this.style.opacity = '';
            });
        });
    } else {
        console.log('WP AI Explainer: Could not set up click handler. Missing elements or explainerAdmin:', {
            scanBtn: !!scanBtn,
            queueInput: !!queueInput,
            explainerAdmin: typeof explainerAdmin !== 'undefined'
        });
    }
    
    // Handle refresh status button
    const refreshBtn = document.querySelector('.refresh-status-btn');
    if (refreshBtn && typeof explainerAdmin !== 'undefined') {
        refreshBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const postId = <?php echo esc_js($post->ID); ?>;
            
            // Show loading state
            const spinner = document.querySelector('.control-actions .spinner');
            if (spinner) {
                spinner.classList.add('is-active');
            }
            
            this.style.opacity = '0.6';
            this.style.pointerEvents = 'none';
            
            // Make AJAX request to refresh status
            const formData = new FormData();
            formData.append('action', 'explainer_refresh_post_status');
            formData.append('post_id', postId);
            formData.append('nonce', explainerAdmin.nonce);
            
            fetch(explainerAdmin.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload the page to show updated status
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.data.message || 'Failed to refresh status'));
                }
            })
            .catch(error => {
                console.error('Status refresh error:', error);
                alert('Error: Failed to communicate with server');
            })
            .finally(() => {
                // Hide loading state
                if (spinner) {
                    spinner.classList.remove('is-active');
                }
                
                this.style.opacity = '';
                this.style.pointerEvents = '';
            });
        });
    }
});
</script>

<style>
/* Status Dashboard Styles - WordPress Admin Native Design */
.explainer-status-dashboard {
    padding: 0;
    background: #ffffff;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    box-sizing: border-box;
    max-width: 100%;
    overflow: hidden;
}

.explainer-status-dashboard *,
.explainer-status-dashboard *:before,
.explainer-status-dashboard *:after {
    box-sizing: border-box;
}

/* Status Overview Card */
.status-overview-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #f1f3f4 100%);
    border: 1px solid #e1e5e9;
    border-radius: 6px;
    padding: 10px 12px 0px 12px;
    margin-bottom: 14px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: hidden;
}

.status-overview-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--status-gradient-start, #646970), var(--status-gradient-end, #8c8f94));
    border-radius: 8px 8px 0 0;
}

.status-primary {
    margin-bottom: 8px;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-icon {
    font-size: 18px;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.status-text {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.status-label {
    font-size: 13px;
    font-weight: 600;
    color: #1d2327;
    line-height: 1.2;
}

.status-meta {
    font-size: 12px;
    color: #646970;
    font-weight: 400;
}

.status-meta-info {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #646970;
    padding-top: 8px;
    border-top: 1px solid #e0e0e0;
}

.status-meta-info .dashicons {
    font-size: 14px;
    opacity: 0.7;
}

/* Status-specific colours with enhanced visual hierarchy */
.status-idle {
    --status-gradient-start: #6c757d;
    --status-gradient-end: #8c8f94;
}
.status-idle .status-icon { 
    color: #6c757d;
    background: rgba(108, 117, 125, 0.1);
    border-radius: 50%;
    padding: 4px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.status-queued {
    --status-gradient-start: #0073aa;
    --status-gradient-end: #005177;
}
.status-queued .status-icon { 
    color: #0073aa;
    background: rgba(0, 115, 170, 0.15);
    border-radius: 50%;
    padding: 4px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: pulse 2s ease-in-out infinite;
}

.status-processing {
    --status-gradient-start: #ff922b;
    --status-gradient-end: #e67e22;
}
.status-processing .status-icon { 
    color: #ff922b;
    background: rgba(255, 146, 43, 0.15);
    border-radius: 50%;
    padding: 4px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: spin 2s linear infinite;
}

.status-success {
    --status-gradient-start: #00a32a;
    --status-gradient-end: #008a20;
}
.status-success .status-icon { 
    color: #00a32a;
    background: rgba(0, 163, 42, 0.15);
    border-radius: 50%;
    padding: 4px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.status-warning {
    --status-gradient-start: #ffb900;
    --status-gradient-end: #e6a700;
}
.status-warning .status-icon { 
    color: #ffb900;
    background: rgba(255, 185, 0, 0.15);
    border-radius: 50%;
    padding: 4px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: pulse 2s ease-in-out infinite;
}

.status-error {
    --status-gradient-start: #d63638;
    --status-gradient-end: #b32d2e;
}
.status-error .status-icon { 
    color: #d63638;
    background: rgba(214, 54, 56, 0.15);
    border-radius: 50%;
    padding: 4px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

/* Controls Grid */
.controls-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 16px;
}

.control-item {
    background: linear-gradient(135deg, #ffffff 0%, #fafafa 100%);
    border: 1px solid #e1e5e9;
    border-radius: 6px;
    padding: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
    position: relative;
}

.control-item:hover {
    border-color: #c3c4c7;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
}

.control-header {
    margin-bottom: 6px;
}

.control-label {
    font-size: 13px;
    font-weight: 600;
    color: #1d2327;
    margin: 0 0 10px 0;
    display: block;
}

.control-content {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
}

.toggle-label {
    font-size: 12px;
    color: #646970;
}

.control-actions {
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.control-actions .spinner {
    float: none;
    margin: 0;
    visibility: hidden;
}

.control-actions .spinner.is-active {
    visibility: visible;
}

.control-help {
    font-size: 12px;
    color: #646970;
    margin: 6px 0 0 0;
    line-height: 1.4;
}

.control-help {
    display: flex;
    align-items: center;
    gap: 6px;
}

.control-help .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.control-help.processing { 
    color: #ff922b; 
    font-weight: 500; 
    background: rgba(255, 146, 43, 0.1);
    padding: 8px 12px;
    border-radius: 6px;
    border-left: 3px solid #ff922b;
}

.control-help.success { 
    color: #00a32a; 
    font-weight: 500; 
    background: rgba(0, 163, 42, 0.1);
    padding: 8px 12px;
    border-radius: 6px;
    border-left: 3px solid #00a32a;
}

.control-help.error { 
    color: #d63638; 
    font-weight: 500; 
    background: rgba(214, 54, 56, 0.1);
    padding: 8px 12px;
    border-radius: 6px;
    border-left: 3px solid #d63638;
}

.control-help.warning { 
    color: #ffb900; 
    font-weight: 500; 
    background: rgba(255, 185, 0, 0.1);
    padding: 8px 12px;
    border-radius: 6px;
    border-left: 3px solid #ffb900;
}

.control-help.queued { 
    color: #0073aa; 
    font-weight: 500; 
    background: rgba(0, 115, 170, 0.1);
    padding: 8px 12px;
    border-radius: 6px;
    border-left: 3px solid #0073aa;
}

.control-help .spin {
    animation: spin 2s linear infinite;
}

/* Scan Toggle Button */
.scan-toggle-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 12px;
    height: 32px;
    width: 100%;
    padding: 0 12px;
    padding: 0 16px;
    white-space: nowrap;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.scan-toggle-btn:not(.queued) {
    background: linear-gradient(135deg, #0073aa 0%, #005177 100%);
    border-color: #0073aa;
    box-shadow: 0 2px 4px rgba(0, 115, 170, 0.2);
}

.scan-toggle-btn:not(.queued):hover {
    background: linear-gradient(135deg, #005177 0%, #004155 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 115, 170, 0.3);
}

.scan-toggle-btn .dashicons {
    font-size: 16px;
    margin: 7px 0 0 0;
}

.scan-toggle-btn .btn-text-queued {
    display: none;
}

.scan-toggle-btn.queued .btn-text-scan {
    display: none;
}

.scan-toggle-btn.queued .btn-text-queued {
    display: inline;
}

.scan-toggle-btn.queued {
    background: linear-gradient(135deg, #d63638 0%, #b32d2e 100%);
    border-color: #d63638;
    color: #ffffff;
    box-shadow: 0 2px 4px rgba(214, 54, 56, 0.2);
}

.scan-toggle-btn.queued:hover:not(:disabled) {
    background: linear-gradient(135deg, #b32d2e 0%, #991b1b 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(214, 54, 56, 0.3);
}

/* Loading state for scan button */
.scan-toggle-btn.loading {
    opacity: 0.8;
    cursor: wait;
    pointer-events: none;
}

.scan-toggle-btn.loading .dashicons {
    animation: spin 1s linear infinite;
}

.scan-toggle-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Toggle Switch - Enhanced for better interaction */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 42px;
    height: 24px;
    cursor: pointer;
    z-index: 10;
    margin: 0;
    padding: 0;
}

.toggle-switch input {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
    z-index: 20;
    cursor: pointer;
}

.toggle-slider {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #dcdcde 0%, #c3c4c7 100%);
    border-radius: 28px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid #c3c4c7;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
    z-index: 1;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 1px;
    top: 1px;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 50%;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), 0 1px 3px rgba(0, 0, 0, 0.1);
    z-index: 2;
}

.toggle-switch input:checked + .toggle-slider {
    background: linear-gradient(135deg, #00a32a 0%, #008a20 100%);
    border-color: #00a32a;
    box-shadow: inset 0 2px 4px rgba(0, 163, 42, 0.2), 0 0 0 1px rgba(0, 163, 42, 0.1);
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(18px);
    background: linear-gradient(135deg, #ffffff 0%, #f0f8ff 100%);
    box-shadow: 0 2px 8px rgba(0, 163, 42, 0.3), 0 1px 3px rgba(0, 0, 0, 0.1);
    left: -6px;
    top: 1px;
}

.toggle-switch input:focus + .toggle-slider {
    outline: 2px solid #0073aa;
    outline-offset: 2px;
    box-shadow: 0 0 0 4px rgba(0, 115, 170, 0.2);
}

.toggle-switch:hover .toggle-slider {
    border-color: #a7aaad;
}

.toggle-switch:hover input:checked + .toggle-slider {
    border-color: #008a20;
}

/* Additional Actions */
.additional-actions {
    margin-bottom: 12px;
    padding: 10px 12px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #e1e5e9;
    border-radius: 6px;
    border-left: 4px solid #0073aa;
}

.manage-terms-full-width {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.view-terms-link {
    display: flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
}

.view-terms-link .dashicons {
    font-size: 16px;
}


.refresh-status-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #646970;
    text-decoration: none;
    padding: 8px 12px;
    border: 1px solid #c3c4c7;
    border-radius: 6px;
    background: #ffffff;
    transition: all 0.2s ease;
    cursor: pointer;
}

.refresh-status-btn:hover {
    color: #0073aa;
    border-color: #0073aa;
    background: #f0f6fc;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 115, 170, 0.1);
}

.refresh-status-btn .dashicons {
    font-size: 14px;
}

.dashboard-footer .spinner {
    float: none;
    margin: 0;
    visibility: hidden;
}

.dashboard-footer .spinner.is-active {
    visibility: visible;
}

/* Responsive Design */
@media (max-width: 782px) {
    .controls-grid {
        gap: 12px;
    }
    
    .control-content {
        gap: 8px;
    }
    
    .scan-toggle-btn {
        width: 100%;
        justify-content: center;
        height: 44px;
        padding: 0 20px;
        font-size: 14px;
    }
    
    .status-overview-card {
        padding: 16px;
    }
    
    .control-item {
        padding: 16px;
    }
    
}

@media (max-width: 600px) {
    .status-indicator {
        gap: 8px;
    }
    
    .status-icon {
        font-size: 18px;
        width: 18px;
        height: 18px;
    }
    
    .status-label {
        font-size: 13px;
    }
}

/* Accessibility Improvements */
.control-item:focus-within {
    outline: 2px solid #0073aa;
    outline-offset: 2px;
}

.scan-toggle-btn:focus {
    outline: 2px solid #ffffff;
    outline-offset: 2px;
    box-shadow: 0 0 0 4px rgba(0, 115, 170, 0.3);
}

.refresh-status-btn:focus {
    outline: 2px solid #0073aa;
    outline-offset: 2px;
}

/* Screen reader text */
.sr-only {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .control-item {
        border: 2px solid #000000;
    }
    
    .status-overview-card {
        border: 2px solid #000000;
    }
    
    .toggle-slider {
        border: 2px solid #000000;
    }
    
    .scan-toggle-btn {
        border: 2px solid #000000;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .control-item {
        transition: none;
    }
    
    .scan-toggle-btn {
        transition: none;
    }
    
    .toggle-slider,
    .toggle-slider:before {
        transition: none;
    }
    
    .status-processing .status-icon,
    .control-help .spin {
        animation: none;
    }
    
    .status-queued .status-icon,
    .status-warning .status-icon {
        animation: none;
    }
}
</style>