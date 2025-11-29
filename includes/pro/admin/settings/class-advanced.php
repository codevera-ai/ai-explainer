<?php
/**
 * Advanced Settings Section
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle advanced settings tab rendering
 */
class ExplainerPlugin_Advanced {
    
    /**
     * Render the advanced settings tab content
     */
    public function render() {
        ?>
        <div class="tab-content" id="advanced-tab" style="display: none;">
            <h2><?php echo esc_html__('Advanced Configuration', 'ai-explainer'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Debug Mode', 'ai-explainer'); ?></th>
                    <td>
                        <label for="explainer_debug_mode">
                            <input type="hidden" name="explainer_debug_mode" value="0" />
                            <input type="checkbox" name="explainer_debug_mode" id="explainer_debug_mode" value="1" <?php checked(get_option('explainer_debug_mode', false), true); ?> />
                            <?php echo esc_html__('Enable debug mode for troubleshooting', 'ai-explainer'); ?>
                        </label>
                        <p class="description"><?php echo esc_html__('Enables detailed console logging and API prompt capture for debugging purposes. Only enable when troubleshooting issues.', 'ai-explainer'); ?></p>
                        
                        <?php if (get_option('explainer_debug_mode', false)): ?>
                        <?php $this->render_debug_tools(); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Cron Management', 'ai-explainer'); ?></th>
                    <td>
                        <label for="explainer_enable_cron">
                            <input type="hidden" name="explainer_enable_cron" value="0" />
                            <input type="checkbox" name="explainer_enable_cron" id="explainer_enable_cron" value="1" <?php checked(get_option('explainer_enable_cron', false), true); ?> />
                            <?php echo esc_html__('Enable server cron for automatic job processing', 'ai-explainer'); ?>
                        </label>
                        <p class="description"><?php echo esc_html__('When enabled, jobs will be processed by your server cron (external cron job) for reliable background processing. When disabled, jobs will automatically fallback to WordPress cron (triggered by site visits).', 'ai-explainer'); ?></p>
                        
                        <?php $this->render_cron_instructions(); ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Reset Settings', 'ai-explainer'); ?></th>
                    <td>
                        <div class="reset-settings-section">
                            <button type="button" class="btn-base btn-secondary" id="reset-settings">
                                <?php echo esc_html__('Reset to Defaults', 'ai-explainer'); ?>
                            </button>
                            <p class="description"><?php echo esc_html__('Reset all plugin settings to their default values. This action cannot be undone.', 'ai-explainer'); ?></p>
                        </div>
                    </td>
                </tr>
            </table>
            
        </div>
        <?php
    }
    
    private function render_debug_tools() {
        ?>
        <div class="debug-actions" style="margin-top: 15px; padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px;">
            <h4><?php echo esc_html__('Debug Tools', 'ai-explainer'); ?></h4>
            
            <!-- Debug Controls -->
            <div class="debug-controls" style="margin-bottom: 15px;">
                <button type="button" class="btn-base btn-primary" id="view-debug-logs">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php echo esc_html__('View Debug Logs', 'ai-explainer'); ?>
                </button>
                <button type="button" class="btn-base btn-secondary" id="refresh-debug-logs" style="display: none;">
                    <span class="dashicons dashicons-update"></span>
                    <?php echo esc_html__('Refresh', 'ai-explainer'); ?>
                </button>
                <button type="button" class="btn-base btn-secondary" id="download-debug-logs" style="display: none;">
                    <span class="dashicons dashicons-download"></span>
                    <?php echo esc_html__('Download', 'ai-explainer'); ?>
                </button>
                <button type="button" class="btn-base btn-danger" id="delete-debug-logs">
                    <span class="dashicons dashicons-trash"></span>
                    <?php echo esc_html__('Delete All Logs', 'ai-explainer'); ?>
                </button>
            </div>
            
            <?php $this->render_debug_statistics(); ?>
            <?php $this->render_debug_filters(); ?>
            <?php $this->render_debug_log_viewer(); ?>
            <?php $this->render_debug_realtime_controls(); ?>
        </div>
        
        <?php
    }
    
    /**
     * Render debug statistics section
     */
    private function render_debug_statistics() {
        ?>
        <!-- Debug Statistics -->
        <div id="debug-stats" style="display: none; margin-bottom: 15px; padding: 10px; background: #fff; border-left: 4px solid #007cba; border-radius: 4px;">
            <h5 style="margin: 0 0 10px 0;"><?php echo esc_html__('Log Statistics', 'ai-explainer'); ?></h5>
            <div id="debug-stats-content">
                <p><?php echo esc_html__('Loading statistics...', 'ai-explainer'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render debug filters section
     */
    private function render_debug_filters() {
        ?>
        <!-- Log Filters -->
        <div id="debug-filters" style="display: none; margin-bottom: 15px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
            <h5 style="margin: 0 0 10px 0;"><?php echo esc_html__('Filter Logs', 'ai-explainer'); ?></h5>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <label for="log-level-filter">
                    <strong><?php echo esc_html__('Level:', 'ai-explainer'); ?></strong>
                    <select id="log-level-filter" style="margin-left: 5px;">
                        <option value=""><?php echo esc_html__('All Levels', 'ai-explainer'); ?></option>
                        <option value="error"><?php echo esc_html__('Error', 'ai-explainer'); ?></option>
                        <option value="warning"><?php echo esc_html__('Warning', 'ai-explainer'); ?></option>
                        <option value="info"><?php echo esc_html__('Info', 'ai-explainer'); ?></option>
                        <option value="debug"><?php echo esc_html__('Debug', 'ai-explainer'); ?></option>
                        <option value="api"><?php echo esc_html__('API', 'ai-explainer'); ?></option>
                        <option value="performance"><?php echo esc_html__('Performance', 'ai-explainer'); ?></option>
                    </select>
                </label>
                <label for="log-component-filter">
                    <strong><?php echo esc_html__('Component:', 'ai-explainer'); ?></strong>
                    <select id="log-component-filter" style="margin-left: 5px;">
                        <option value=""><?php echo esc_html__('All Components', 'ai-explainer'); ?></option>
                    </select>
                </label>
                <button type="button" class="btn-base btn-secondary btn-sm" id="apply-log-filters">
                    <?php echo esc_html__('Apply Filters', 'ai-explainer'); ?>
                </button>
                <button type="button" class="btn-base btn-secondary btn-sm" id="clear-log-filters">
                    <?php echo esc_html__('Clear', 'ai-explainer'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    
    /**
     * Render cron instructions for auto-processing
     */
    private function render_cron_instructions() {
        // Generate cron token if it doesn't exist
        if (empty(get_option('explainer_cron_token'))) {
            $token = wp_generate_password(32, false);
            update_option('explainer_cron_token', $token);
        } else {
            $token = get_option('explainer_cron_token');
        }
        
        $cron_url = home_url('/?explainer_cron=run&token=' . $token);
        $curl_command = '*/5 * * * * curl -s "' . $cron_url . '" > /dev/null 2>&1';
        $crontab_command = 'crontab -e';
        ?>
        <div id="cron-instructions" class="cron-instructions" style="margin-top: 10px; padding: 12px; background: #f9f9f9; border-left: 4px solid #0073aa; display: none;">
            <h4><?php echo esc_html__('Server Cron Setup Instructions', 'ai-explainer'); ?></h4>
            
            <div style="margin-bottom: 15px;">
                <p><strong><?php echo esc_html__('Step 1: Open your server cron configuration', 'ai-explainer'); ?></strong></p>
                <div class="cron-command-container" style="position: relative; background: #fff; padding: 8px; border-radius: 3px; font-family: monospace; font-size: 12px; margin: 8px 0;">
                    <span id="crontab-command"><?php echo esc_html($crontab_command); ?></span>
                    <button type="button" class="btn-base btn-secondary btn-sm copy-btn" data-copy-target="crontab-command" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%);">
                        <?php echo esc_html__('Copy', 'ai-explainer'); ?>
                    </button>
                </div>
                <p><small><?php echo esc_html__('This opens your server\'s cron editor. Use "sudo crontab -e" if you need admin privileges.', 'ai-explainer'); ?></small></p>
            </div>
            
            <div style="margin-bottom: 15px;">
                <p><strong><?php echo esc_html__('Step 2: Add this line to your crontab', 'ai-explainer'); ?></strong></p>
                <div class="cron-command-container" style="position: relative; background: #fff; padding: 8px; border-radius: 3px; font-family: monospace; font-size: 12px; margin: 8px 0; word-break: break-all; padding-right: 70px;">
                    <span id="curl-command"><?php echo esc_html($curl_command); ?></span>
                    <button type="button" class="btn-base btn-secondary btn-sm copy-btn" data-copy-target="curl-command" style="position: absolute; right: 5px; top: 5px;">
                        <?php echo esc_html__('Copy', 'ai-explainer'); ?>
                    </button>
                </div>
                <p><small><?php echo esc_html__('This processes pending jobs every 5 minutes. Save and exit the cron editor after adding this line.', 'ai-explainer'); ?></small></p>
            </div>
            
            <div style="margin-bottom: 15px;">
                <p><strong><?php echo esc_html__('Step 3: Verify cron is working', 'ai-explainer'); ?></strong></p>
                <div class="cron-command-container" style="position: relative; background: #fff; padding: 8px; border-radius: 3px; font-family: monospace; font-size: 12px; margin: 8px 0;">
                    <span id="test-command">curl -s "<?php echo esc_html($cron_url); ?>"</span>
                    <button type="button" class="btn-base btn-secondary btn-sm copy-btn" data-copy-target="test-command" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%);">
                        <?php echo esc_html__('Copy', 'ai-explainer'); ?>
                    </button>
                </div>
                <p><small><?php echo esc_html__('Test the cron URL manually to verify it works. You should see a JSON response with job processing results.', 'ai-explainer'); ?></small></p>
            </div>
            
            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                <strong><?php echo esc_html__('Cron Modes:', 'ai-explainer'); ?></strong>
                <ul style="margin: 5px 0 0 20px;">
                    <li><strong><?php echo esc_html__('Server Cron (Enabled):', 'ai-explainer'); ?></strong> <?php echo esc_html__('Processes blog creation and post scan jobs reliably via external cron', 'ai-explainer'); ?></li>
                    <li><strong><?php echo esc_html__('WordPress Cron (Disabled):', 'ai-explainer'); ?></strong> <?php echo esc_html__('Jobs process automatically when visitors browse your site (less reliable)', 'ai-explainer'); ?></li>
                </ul>
            </div>
            
            <div style="margin-top: 15px; padding: 10px; background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 4px;">
                <strong><?php echo esc_html__('Jobs Processed:', 'ai-explainer'); ?></strong>
                <ul style="margin: 5px 0 0 20px; font-size: 12px;">
                    <li><strong><?php echo esc_html__('Blog Creation:', 'ai-explainer'); ?></strong> <?php echo esc_html__('Generates blog posts from selected text explanations', 'ai-explainer'); ?></li>
                    <li><strong><?php echo esc_html__('Post Scan:', 'ai-explainer'); ?></strong> <?php echo esc_html__('Scans existing posts for terms needing explanations', 'ai-explainer'); ?></li>
                </ul>
            </div>
            
            <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
                <strong><?php echo esc_html__('Alternative Timing Options:', 'ai-explainer'); ?></strong>
                <ul style="margin: 5px 0 0 20px; font-size: 12px;">
                    <li><code>*/1 * * * *</code> - <?php echo esc_html__('Every minute (high-frequency sites)', 'ai-explainer'); ?></li>
                    <li><code>*/5 * * * *</code> - <?php echo esc_html__('Every 5 minutes (recommended)', 'ai-explainer'); ?></li>
                    <li><code>*/15 * * * *</code> - <?php echo esc_html__('Every 15 minutes (low-frequency sites)', 'ai-explainer'); ?></li>
                </ul>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add click handlers for copy buttons
            document.querySelectorAll('.copy-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-copy-target');
                    const targetElement = document.getElementById(targetId);
                    
                    if (targetElement) {
                        // Create temporary textarea for copying
                        const textarea = document.createElement('textarea');
                        textarea.value = targetElement.textContent;
                        document.body.appendChild(textarea);
                        textarea.select();
                        
                        try {
                            document.execCommand('copy');
                            this.textContent = '<?php echo esc_js(__('Copied!', 'ai-explainer')); ?>';
                            
                            // Reset button text after 2 seconds
                            setTimeout(() => {
                                this.textContent = '<?php echo esc_js(__('Copy', 'ai-explainer')); ?>';
                            }, 2000);
                        } catch (err) {
                            console.error('Copy failed:', err);
                        }
                        
                        document.body.removeChild(textarea);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render debug log viewer section
     */
    private function render_debug_log_viewer() {
        ?>
        <!-- Debug Log Viewer -->
        <div id="debug-logs-viewer" style="display: none; margin-bottom: 15px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h5 style="margin: 0;"><?php echo esc_html__('Debug Logs', 'ai-explainer'); ?></h5>
                <span id="debug-logs-count" style="font-size: 12px; color: #666;"></span>
            </div>
            <div id="debug-logs-list" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: #fff;"></div>
        </div>
        
        <!-- Live Log Viewer (keeping original for compatibility) -->
        <div id="unified-log-viewer" style="display: none; margin-bottom: 15px;">
            <div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">
                <div style="background: #f8f9fa; padding: 10px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                    <h5 style="margin: 0;"><?php echo esc_html__('Live Debug Logs', 'ai-explainer'); ?></h5>
                    <div>
                        <label style="margin-right: 10px;">
                            <input type="checkbox" id="unified-auto-refresh" checked>
                            <?php echo esc_html__('Auto-refresh', 'ai-explainer'); ?>
                        </label>
                        <button type="button" class="btn-base btn-secondary btn-sm" id="unified-viewer-clear">
                            <?php echo esc_html__('Clear View', 'ai-explainer'); ?>
                        </button>
                        <button type="button" class="btn-base btn-secondary btn-sm" id="unified-viewer-close">
                            <?php echo esc_html__('Close', 'ai-explainer'); ?>
                        </button>
                    </div>
                </div>
                <div id="unified-log-content" style="height: 400px; overflow-y: auto; padding: 15px; font-family: monospace; font-size: 12px; line-height: 1.4; background: #fafafa;">
                    <div id="unified-log-loading" style="text-align: center; color: #666; padding: 20px;">
                        <?php echo esc_html__('Loading logs...', 'ai-explainer'); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Enhanced Log Filters -->
        <div id="enhanced-log-filters" style="display: none; margin-bottom: 15px; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
            <h5 style="margin: 0 0 12px 0;"><?php echo esc_html__('Advanced Log Filters', 'ai-explainer'); ?></h5>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div>
                    <label for="log-section-filter">
                        <strong><?php echo esc_html__('Section:', 'ai-explainer'); ?></strong><br>
                        <select id="log-section-filter" style="width: 100%; margin-top: 4px;">
                            <option value=""><?php echo esc_html__('All Sections', 'ai-explainer'); ?></option>
                        </select>
                    </label>
                </div>
                <div>
                    <label for="log-date-filter">
                        <strong><?php echo esc_html__('Date Range:', 'ai-explainer'); ?></strong><br>
                        <select id="log-date-filter" style="width: 100%; margin-top: 4px;">
                            <option value="today"><?php echo esc_html__('Today', 'ai-explainer'); ?></option>
                            <option value="yesterday"><?php echo esc_html__('Yesterday', 'ai-explainer'); ?></option>
                            <option value="week"><?php echo esc_html__('Last 7 Days', 'ai-explainer'); ?></option>
                            <option value="month"><?php echo esc_html__('Last 30 Days', 'ai-explainer'); ?></option>
                            <option value="all"><?php echo esc_html__('All Time', 'ai-explainer'); ?></option>
                        </select>
                    </label>
                </div>
                <div>
                    <label for="log-search-filter">
                        <strong><?php echo esc_html__('Search:', 'ai-explainer'); ?></strong><br>
                        <input type="text" id="log-search-filter" placeholder="<?php esc_attr_e('Search in logs...', 'ai-explainer'); ?>" 
                               style="width: 100%; margin-top: 4px; padding: 4px 8px;">
                    </label>
                </div>
                <div style="display: flex; align-items: end; gap: 8px;">
                    <button type="button" class="btn-base btn-secondary btn-sm" id="apply-enhanced-filters">
                        <?php echo esc_html__('Apply', 'ai-explainer'); ?>
                    </button>
                    <button type="button" class="btn-base btn-secondary btn-sm" id="export-filtered-logs">
                        <?php echo esc_html__('Export', 'ai-explainer'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <style>
            .log-entry {
                margin-bottom: 8px;
                padding: 8px;
                border-left: 3px solid #ccc;
                background: #fff;
                border-radius: 3px;
            }
            
            .log-entry.error {
                border-left-color: #d63638;
                background-color: #fef7f7;
            }
            
            .log-entry.warning {
                border-left-color: #dba617;
                background-color: #fffbf0;
            }
            
            .log-entry.info {
                border-left-color: #2271b1;
                background-color: #f0f6fc;
            }
            
            .log-entry.debug {
                border-left-color: #8c8f94;
                background-color: #f6f7f7;
            }
            
            .log-meta {
                font-size: 10px;
                color: #666;
                margin-bottom: 4px;
            }
            
            .log-message {
                font-weight: 500;
                margin-bottom: 4px;
            }
            
            .log-context {
                font-size: 11px;
                color: #666;
                background: rgba(0,0,0,0.05);
                padding: 4px 6px;
                border-radius: 2px;
                margin-top: 4px;
                max-height: 100px;
                overflow-y: auto;
            }
            
            .log-context pre {
                margin: 0;
                white-space: pre-wrap;
                word-wrap: break-word;
            }
            
            #debug-log-content {
                scrollbar-width: thin;
                scrollbar-color: #ccc #f0f0f0;
            }
            
            #debug-log-content::-webkit-scrollbar {
                width: 8px;
            }
            
            #debug-log-content::-webkit-scrollbar-track {
                background: #f0f0f0;
            }
            
            #debug-log-content::-webkit-scrollbar-thumb {
                background: #ccc;
                border-radius: 4px;
            }
            
            #debug-log-content::-webkit-scrollbar-thumb:hover {
                background: #999;
            }
            
            .debug-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin-top: 10px;
            }
            
            .debug-stat-item {
                text-align: center;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 4px;
                border: 1px solid #e0e0e0;
            }
            
            .debug-stat-value {
                font-size: 18px;
                font-weight: bold;
                color: #2271b1;
                display: block;
            }
            
            .debug-stat-label {
                font-size: 12px;
                color: #666;
                margin-top: 4px;
            }
            
            .log-message {
                word-wrap: break-word;
                word-break: break-word;
                overflow-wrap: break-word;
                white-space: pre-wrap;
                max-width: 100%;
            }
        </style>
        <?php
    }
    
    /**
     * Render debug realtime controls section
     */
    private function render_debug_realtime_controls() {
        ?>
        <div id="debug-realtime-controls" style="display: none; margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 4px;">
            <label style="display: flex; align-items: center; gap: 8px; font-weight: 500;">
                <input type="checkbox" id="realtime-logs-toggle" style="margin: 0;">
                <?php echo esc_html__('Auto refresh logs (every 5 seconds)', 'ai-explainer'); ?>
            </label>
        </div>
        <?php
    }
    
}