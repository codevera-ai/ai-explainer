<?php
/**
 * Support Settings Section
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle support settings tab rendering
 */
class ExplainerPlugin_Support {
    
    /**
     * Render the support settings tab content
     */
    public function render() {
        ?>
        <div class="tab-content" id="support-tab" style="display: none;">
            <h2><?php echo esc_html__('Support & documentation', 'ai-explainer'); ?></h2>

            <!-- Core Features Overview -->
            <details class="support-section">
                <summary><h3><?php echo esc_html__('Core features overview', 'ai-explainer'); ?></h3></summary>
                <div class="features-grid">
                    <?php $this->render_features_overview(); ?>
                </div>
            </details>

            <!-- Quick Start Guide Section -->
            <details class="support-section">
                <summary><h3><?php echo esc_html__('Quick start guide', 'ai-explainer'); ?></h3></summary>
                <div class="help-steps">
                    <div class="help-step">
                        <h4><?php echo esc_html__('1. Choose your AI provider', 'ai-explainer'); ?></h4>
                        <p><?php echo esc_html__('Hit the AI provider tab and pick your service. We\'ve got OpenAI, Claude (pro only), and Google Gemini (pro only). Each provider uses the latest model automatically.', 'ai-explainer'); ?></p>
                        <?php $this->render_model_list(); ?>
                    </div>

                    <div class="help-step">
                        <h4><?php echo esc_html__('2. Add your API key', 'ai-explainer'); ?></h4>
                        <p><?php echo esc_html__('Grab your API key from your chosen provider. We encrypt all keys automatically.', 'ai-explainer'); ?></p>
                        <ul>
                            <li><strong>OpenAI:</strong> Get your key from <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a></li>
                            <li><strong>Claude (pro only):</strong> Get your key from <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a></li>
                            <li><strong>Google Gemini (pro only):</strong> Get your key from <a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com</a></li>
                        </ul>
                    </div>

                    <div class="help-step">
                        <h4><?php echo esc_html__('3. Test and activate', 'ai-explainer'); ?></h4>
                        <p><?php echo esc_html__('Hit "test API key" to check it works, then flip the switch in basic settings. Done.', 'ai-explainer'); ?></p>
                    </div>

                    <div class="help-step">
                        <h4><?php echo esc_html__('4. Scan your content', 'ai-explainer'); ?></h4>
                        <p><?php echo esc_html__('Hit the post scan tab to let AI find confusing terms in your content. Or manage terms manually through the AI explanations box on any post. Turn on auto-scan in basic settings to catch new content automatically.', 'ai-explainer'); ?></p>
                    </div>

                    <div class="help-step">
                        <h4><?php echo esc_html__('5. Fine-tune features', 'ai-explainer'); ?></h4>
                        <p><?php echo esc_html__('Set reading levels, turn on user help, control the slider visibility, or dive into the JavaScript API if you\'re building custom stuff.', 'ai-explainer'); ?></p>
                    </div>
                </div>
            </details>
            
            <!-- Advanced Features Guide -->
            <?php $this->render_advanced_features(); ?>

            <!-- How Users Get Explanations Section -->
            <details class="support-section">
                <summary><h3><?php echo esc_html__('How your users get help', 'ai-explainer'); ?></h3></summary>
                <div class="help-usage">
                    <ol>
                        <li><?php echo esc_html__('They highlight text on your site', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('A toggle button appears so they can switch on explanations', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Now selecting text gets them instant AI help', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Explanations show up in tooltips they can close when they\'re done', 'ai-explainer'); ?></li>
                    </ol>
                </div>
            </details>

            <!-- Troubleshooting Section -->
            <?php $this->render_troubleshooting_section(); ?>
            
            <!-- Contact Information -->
            <?php $this->render_contact_section(); ?>
            
            
            <!-- System Information -->
            <?php $this->render_system_info(); ?>
        </div>
        <?php
    }
    
    /**
     * Render AI model list
     */
    private function render_model_list() {
        ?>
        <ul>
            <li><strong>OpenAI:</strong> Uses GPT-5.1, the latest model automatically selected</li>
            <li><strong>Claude (pro only):</strong> Uses Claude Haiku 4.5, fast and cost-effective</li>
            <li><strong>Google Gemini (pro only):</strong> Uses Gemini 2.5 Flash, high performance with competitive pricing</li>
        </ul>
        <?php
    }
    
    /**
     * Render troubleshooting section
     */
    private function render_troubleshooting_section() {
        ?>
        <details class="support-section">
            <summary><h3><?php echo esc_html__('Troubleshooting', 'ai-explainer'); ?></h3></summary>
            <div class="help-troubleshooting">
                <div class="help-issue">
                    <h4><?php echo esc_html__('Explanations not working?', 'ai-explainer'); ?></h4>
                    <ul>
                        <li><?php echo esc_html__('Check the plugin is turned on in basic settings', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Double-check your API key for typos', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Hit "test API key" to check it works', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Turn on debug mode to see what\'s wrong', 'ai-explainer'); ?></li>
                    </ul>
                </div>

                <div class="help-issue">
                    <h4><?php echo esc_html__('API costs getting steep?', 'ai-explainer'); ?></h4>
                    <ul>
                        <li><?php echo esc_html__('Turn on caching in performance settings', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Set rate limits so users don\'t go crazy', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Consider switching to Claude Haiku 4.5 or Gemini 2.5 Flash for lower costs', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Limit text selection length to keep prompts short', 'ai-explainer'); ?></li>
                    </ul>
                </div>

                <div class="help-issue">
                    <h4><?php echo esc_html__('Tooltips showing up in weird places?', 'ai-explainer'); ?></h4>
                    <ul>
                        <li><?php echo esc_html__('Your theme might be messing with tooltip positioning', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Check your content rules for conflicting selectors', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Theme styles might be overriding plugin styles', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Try different positioning in appearance settings', 'ai-explainer'); ?></li>
                    </ul>
                </div>

            </div>
        </details>
        <?php
    }
    
    /**
     * Render contact section
     */
    private function render_contact_section() {
        ?>
        <details class="support-section">
            <summary><h3><?php echo esc_html__('Support', 'ai-explainer'); ?></h3></summary>
            <div class="developer-info">
                <p><strong><?php echo esc_html__('Developer:', 'ai-explainer'); ?></strong> AI Explainer</p>
                <p><strong><?php echo esc_html__('Support:', 'ai-explainer'); ?></strong> <a href="mailto:info@wpaiexplainer.com">info@wpaiexplainer.com</a></p>
                <p><strong><?php echo esc_html__('Website:', 'ai-explainer'); ?></strong> <a href="https://wpaiexplainer.com" target="_blank">wpaiexplainer.com</a></p>
            </div>
        </details>

        <details class="support-section">
            <summary><h3><?php echo esc_html__('Need help?', 'ai-explainer'); ?></h3></summary>
            <div class="support-options">
                <div class="support-option">
                    <h4><?php echo esc_html__('1. Check above first', 'ai-explainer'); ?></h4>
                    <p><?php echo esc_html__('Most answers are in the quick start guide and troubleshooting bits above.', 'ai-explainer'); ?></p>
                </div>

                <div class="support-option">
                    <h4><?php echo esc_html__('2. Turn on debug mode', 'ai-explainer'); ?></h4>
                    <p><?php echo esc_html__('If stuff\'s broken, flip on debug mode in advanced to see what\'s happening.', 'ai-explainer'); ?></p>
                </div>

                <div class="support-option">
                    <h4><?php echo esc_html__('3. Drop us a line', 'ai-explainer'); ?></h4>
                    <p><?php echo esc_html__('Found a bug or got an idea? Email us at', 'ai-explainer'); ?> <a href="mailto:info@wpaiexplainer.com">info@wpaiexplainer.com</a> <?php echo esc_html__('and we\'ll sort it out.', 'ai-explainer'); ?></p>
                </div>
            </div>
        </details>
        <?php
    }
    
    
    /**
     * Render system information section
     */
    private function render_system_info() {
        global $wpdb;

        // Check database tables
        $job_queue_table = $wpdb->prefix . 'wpaie_scheduled_actions';
        $selections_table = $wpdb->prefix . 'ai_explainer_selections';

        $job_queue_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $job_queue_table)) === $job_queue_table;
        $selections_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $selections_table)) === $selections_table;

        // Count records
        $job_count = $job_queue_exists ? $wpdb->get_var("SELECT COUNT(*) FROM `{$job_queue_table}`") : 0;
        $selections_count = $selections_exists ? $wpdb->get_var("SELECT COUNT(*) FROM `{$selections_table}`") : 0;

        // Check WordPress cron
        $cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;

        // Check if any AI provider is configured
        $providers = array('openai', 'claude', 'gemini');
        $configured_provider = 'None';
        foreach ($providers as $provider) {
            $key_option = "explainer_{$provider}_api_key";
            if (get_option($key_option)) {
                $configured_provider = ucfirst($provider);
                break;
            }
        }

        ?>
        <details class="support-section">
            <summary><h3><?php echo esc_html__('System information', 'ai-explainer'); ?></h3></summary>
            <div class="system-info">
                <table class="form-table">
                    <tr>
                        <th><?php echo esc_html__('Plugin version:', 'ai-explainer'); ?></th>
                        <td><?php echo esc_html(defined('EXPLAINER_PLUGIN_VERSION') ? EXPLAINER_PLUGIN_VERSION : 'Unknown'); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('WordPress version:', 'ai-explainer'); ?></th>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('PHP version:', 'ai-explainer'); ?></th>
                        <td><?php echo esc_html(PHP_VERSION); ?> <?php echo version_compare(PHP_VERSION, '7.4', '>=') ? '(compatible)' : '(requires 7.4+)'; ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Current theme:', 'ai-explainer'); ?></th>
                        <td><?php echo esc_html(wp_get_theme()->get('Name') . ' v' . wp_get_theme()->get('Version')); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Plugin status:', 'ai-explainer'); ?></th>
                        <td><?php echo get_option('explainer_enabled', false) ? 'Enabled' : 'Disabled'; ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('AI provider:', 'ai-explainer'); ?></th>
                        <td><?php echo esc_html($configured_provider); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('WordPress cron:', 'ai-explainer'); ?></th>
                        <td><?php echo $cron_disabled ? 'Disabled' : 'Enabled'; ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Database tables:', 'ai-explainer'); ?></th>
                        <td>
                            WPAIE Scheduler: <?php echo $job_queue_exists ? 'Available' : 'Missing'; ?>
                            | Selections: <?php echo $selections_exists ? 'Available' : 'Missing'; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Data records:', 'ai-explainer'); ?></th>
                        <td>
                            Jobs: <?php echo number_format($job_count); ?>
                            | Selections: <?php echo number_format($selections_count); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Cache status:', 'ai-explainer'); ?></th>
                        <td><?php echo get_option('explainer_cache_enabled', false) ? 'Enabled' : 'Disabled'; ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Debug mode:', 'ai-explainer'); ?></th>
                        <td><?php echo get_option('explainer_debug_mode', false) ? 'Enabled' : 'Disabled'; ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Server time:', 'ai-explainer'); ?></th>
                        <td><?php echo esc_html(current_time('Y-m-d H:i:s T')); ?></td>
                    </tr>
                </table>

                <div style="margin-top: 20px;">
                    <p><strong><?php echo esc_html__('Quick health check:', 'ai-explainer'); ?></strong></p>
                    <ul>
                        <?php if (!get_option('explainer_enabled', false)): ?>
                            <li style="color: #d63638;">Plugin is disabled - enable it in basic settings</li>
                        <?php endif; ?>

                        <?php if ($configured_provider === 'None'): ?>
                            <li style="color: #d63638;">No AI provider configured - set up API key in AI provider tab</li>
                        <?php endif; ?>

                        <?php if ($cron_disabled): ?>
                            <li style="color: #d63638;">WordPress cron disabled - background jobs may not process automatically</li>
                        <?php endif; ?>

                        <?php if (!$job_queue_exists || !$selections_exists): ?>
                            <li style="color: #d63638;">Database tables missing - try deactivating and reactivating the plugin</li>
                        <?php endif; ?>

                        <?php if (version_compare(PHP_VERSION, '7.4', '<')): ?>
                            <li style="color: #d63638;">PHP version too old - upgrade to 7.4 or higher</li>
                        <?php endif; ?>

                        <?php
                        $issues = 0;
                        if (!get_option('explainer_enabled', false)) $issues++;
                        if ($configured_provider === 'None') $issues++;
                        if ($cron_disabled) $issues++;
                        if (!$job_queue_exists || !$selections_exists) $issues++;
                        if (version_compare(PHP_VERSION, '7.4', '<')) $issues++;

                        if ($issues === 0): ?>
                            <li style="color: #00a32a;">System looks healthy - no issues detected</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </details>
        <?php
    }
    
    /**
     * Render features overview
     */
    private function render_features_overview() {
        ?>
        <div class="features-overview">
            <div class="feature-card">
                <h4><?php echo esc_html__('AI text explanations', 'ai-explainer'); ?></h4>
                <p><?php echo esc_html__('Your visitors select text and get instant AI help in neat tooltips.', 'ai-explainer'); ?></p>
            </div>

            <div class="feature-card">
                <h4><?php echo esc_html__('Multiple AI providers', 'ai-explainer'); ?></h4>
                <p><?php echo esc_html__('Choose from OpenAI, Claude (pro only), and Google Gemini (pro only). Each uses the latest model automatically.', 'ai-explainer'); ?></p>
            </div>

            <div class="feature-card">
                <h4><?php echo esc_html__('Basic customisation', 'ai-explainer'); ?></h4>
                <p><?php echo esc_html__('Custom prompts and basic settings to control how explanations work.', 'ai-explainer'); ?></p>
            </div>

            <div class="feature-card">
                <h4><?php echo esc_html__('Keeps things secure', 'ai-explainer'); ?></h4>
                <p><?php echo esc_html__('Your API keys stay encrypted and security checks keep the bad guys out.', 'ai-explainer'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render JavaScript API guide
     */
    private function render_javascript_api_guide() {
        ?>
        <details class="support-section">
            <summary><h3><?php echo esc_html__('JavaScript API & events', 'ai-explainer'); ?></h3></summary>

            <div class="js-api-info">
                <h4><?php echo esc_html__('Available JavaScript events', 'ai-explainer'); ?></h4>
                <p><?php echo esc_html__('The plugin fires custom events that you can listen to for analytics tracking, learning management system integration, or custom functionality.', 'ai-explainer'); ?></p>

                <div class="js-events-list">
                    <div class="js-event">
                        <h5><code>explainerPopupOnOpen</code></h5>
                        <p><?php echo esc_html__('Fired when any tooltip opens (including loading state). Event detail includes selectedText, explanation, position, and type.', 'ai-explainer'); ?></p>
                    </div>

                    <div class="js-event">
                        <h5><code>explainerPopupOnClose</code></h5>
                        <p><?php echo esc_html__('Fired when tooltip closes. Event detail includes selectedText and wasVisible.', 'ai-explainer'); ?></p>
                    </div>

                    <div class="js-event">
                        <h5><code>explainerExplanationLoaded</code></h5>
                        <p><?php echo esc_html__('Fired when explanation has finished loading and is displayed to user. Includes event data for analytics tracking and learning management systems.', 'ai-explainer'); ?></p>
                    </div>

                    <div class="js-event">
                        <h5><code>explainer:openTermsModal</code> <em>(Admin)</em></h5>
                        <p><?php echo esc_html__('Admin-only event fired when the AI terms modal is opened from the post editor. Used for modal integration and customisation.', 'ai-explainer'); ?></p>
                    </div>
                </div>

                <h4><?php echo esc_html__('Dynamic content support', 'ai-explainer'); ?></h4>
                <p><?php echo esc_html__('For sites with AJAX-loaded content or single-page applications, use the reinitialise function:', 'ai-explainer'); ?></p>
                <code>window.ExplainerPlugin.reinitialise();</code>
                <p><?php echo esc_html__('Call this after adding new content to the page that should support explanations.', 'ai-explainer'); ?></p>

                <h4><?php echo esc_html__('Example usage', 'ai-explainer'); ?></h4>
                <pre style="background: #f1f1f1; padding: 10px; border-radius: 4px; overflow-x: auto;">
// Track explanation usage for analytics
document.addEventListener('explainerExplanationLoaded', function(event) {
    const data = event.detail;
    console.log('Explanation loaded:', data.selectedText);

    // Send to analytics platform
    if (typeof gtag !== 'undefined') {
        gtag('event', 'explanation_loaded', {
            'custom_parameter': data.selectedText.length
        });
    }
});

// Reinitialise after dynamic content loads
fetch('/api/content').then(response => response.text())
    .then(html => {
        document.getElementById('content').innerHTML = html;
        window.ExplainerPlugin.reinitialise();
    });
                </pre>
            </div>
        </details>
        <?php
    }
    
    /**
     * Render advanced features guide
     */
    private function render_advanced_features() {
        ?>
        <details class="support-section">
            <summary><h3><?php echo esc_html__('Advanced features guide', 'ai-explainer'); ?></h3></summary>

            <div class="advanced-feature">
                <h4><?php echo esc_html__('Upgrade to pro', 'ai-explainer'); ?></h4>
                <p><?php echo esc_html__('Unlock advanced features with the pro version:', 'ai-explainer'); ?></p>
                <ul>
                    <li><?php echo esc_html__('Popular selections analytics to track what confuses visitors', 'ai-explainer'); ?></li>
                    <li><?php echo esc_html__('AI-powered post scanning to auto-find terms that need explaining', 'ai-explainer'); ?></li>
                    <li><?php echo esc_html__('Turn popular selections into blog posts automatically', 'ai-explainer'); ?></li>
                    <li><?php echo esc_html__('Advanced content targeting rules and appearance customisation', 'ai-explainer'); ?></li>
                    <li><?php echo esc_html__('Reading level slider and custom prompts for each level', 'ai-explainer'); ?></li>
                    <li><?php echo esc_html__('Performance optimisation with caching and rate limiting', 'ai-explainer'); ?></li>
                </ul>
            </div>
        </details>
        <?php
    }
    
    /**
     * Render job queue help section
     */
    private function render_job_queue_help() {
        ?>
        <details class="support-section">
            <summary><h3><?php echo esc_html__('Job queue & background processing', 'ai-explainer'); ?></h3></summary>
            <p><?php echo esc_html__('The plugin uses the WPAIE scheduler system to handle AI processing tasks efficiently without slowing down your site. This replaces the need for external vendor dependencies.', 'ai-explainer'); ?></p>

            <div class="job-queue-info">
                <div class="help-issue">
                    <h4><?php echo esc_html__('Understanding job statuses', 'ai-explainer'); ?></h4>
                    <ul>
                        <li><strong><?php echo esc_html__('Pending:', 'ai-explainer'); ?></strong> <?php echo esc_html__('Job created, waiting to be processed', 'ai-explainer'); ?></li>
                        <li><strong><?php echo esc_html__('Processing:', 'ai-explainer'); ?></strong> <?php echo esc_html__('Currently being handled by AI', 'ai-explainer'); ?></li>
                        <li><strong><?php echo esc_html__('Completed:', 'ai-explainer'); ?></strong> <?php echo esc_html__('Successfully finished', 'ai-explainer'); ?></li>
                        <li><strong><?php echo esc_html__('Failed:', 'ai-explainer'); ?></strong> <?php echo esc_html__('Encountered an error (can be retried)', 'ai-explainer'); ?></li>
                    </ul>
                </div>

                <div class="help-issue">
                    <h4><?php echo esc_html__('Managing jobs', 'ai-explainer'); ?></h4>
                    <ul>
                        <li><?php echo esc_html__('Monitor progress in real-time with live updates', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Cancel jobs that are no longer needed', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Retry failed jobs with improved error handling', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Clear completed jobs to maintain clean queue', 'ai-explainer'); ?></li>
                    </ul>
                </div>

                <div class="help-issue">
                    <h4><?php echo esc_html__('Job queue not processing?', 'ai-explainer'); ?></h4>
                    <ul>
                        <li><?php echo esc_html__('Check that WordPress cron is working or enable server cron in advanced settings', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Verify your server can make outbound HTTPS requests', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Configure server cron using the instructions in the advanced tab for reliable processing', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Enable debug mode to see detailed processing logs', 'ai-explainer'); ?></li>
                    </ul>
                </div>
            </div>
        </details>
        <?php
    }
    
    /**
     * Render performance guide
     */
    private function render_performance_guide() {
        ?>
        <details class="support-section">
            <summary><h3><?php echo esc_html__('Performance & optimisation', 'ai-explainer'); ?></h3></summary>

            <div class="performance-tips">
                <div class="help-issue">
                    <h4><?php echo esc_html__('Reducing API costs', 'ai-explainer'); ?></h4>
                    <ul>
                        <li><?php echo esc_html__('Enable caching to reuse explanations for identical text selections', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Set appropriate rate limits to control usage per user/session', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Consider Claude Haiku 4.5 or Gemini 2.5 Flash for the most economical pricing', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Limit maximum text selection length to reduce token consumption', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Configure shorter explanation lengths for common queries', 'ai-explainer'); ?></li>
                    </ul>
                </div>

                <div class="help-issue">
                    <h4><?php echo esc_html__('Improving site speed', 'ai-explainer'); ?></h4>
                    <ul>
                        <li><?php echo esc_html__('Assets are loaded conditionally - only on pages where needed', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Use content rules to limit plugin loading to specific areas', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Background processing handles AI calls separately from page loads', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Monitor database growth and clean completed jobs regularly', 'ai-explainer'); ?></li>
                    </ul>
                </div>

                <div class="help-issue">
                    <h4><?php echo esc_html__('Scaling for higher traffic', 'ai-explainer'); ?></h4>
                    <ul>
                        <li><?php echo esc_html__('Configure caching settings for popular content', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Use CDN for static assets and cached explanation data', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Consider object caching (Redis/Memcached) for busy sites', 'ai-explainer'); ?></li>
                        <li><?php echo esc_html__('Monitor API quotas and implement fallback mechanisms', 'ai-explainer'); ?></li>
                    </ul>
                </div>
            </div>
        </details>
        <?php
    }
}