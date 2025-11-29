<?php
/**
 * Basic Settings Section
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle basic settings tab rendering
 */
class ExplainerPlugin_Basic_Settings {

    /**
     * Check if user has advanced access
     */
    private function has_advanced_access() {
        return explainer_can_use_premium_features();
    }

    /**
     * Render premium field with disabled state and upgrade indicator
     */
    private function render_premium_field($field_html, $field_name) {
        if ($this->has_advanced_access()) {
            // Field HTML is pre-built with escaped values, output as-is
            echo wp_kses_post($field_html);
        } else {
            // Wrap in premium disabled container
            echo '<div class="premium-feature-wrapper">';
            echo '<div class="premium-field-disabled">';
            // Make the field disabled - field HTML is pre-built with escaped values
            echo wp_kses_post(str_replace(array('<select', '<input'), array('<select disabled="disabled"', '<input disabled="disabled"'), $field_html));
            echo '</div>';
            echo '<div class="premium-indicator" style="margin-top: 8px; color: #f39c12; font-size: 13px; font-weight: 600;">';
            echo '<span class="dashicons dashicons-lock" style="font-size: 14px; margin-right: 4px;"></span>';
            echo esc_html__('Pro', 'ai-explainer');
            echo ' <a href="' . esc_url(wpaie_freemius()->get_upgrade_url()) . '" class="premium-upgrade-link" style="color: #f39c12; text-decoration: none; margin-left: 8px;">' . esc_html__('Upgrade Now', 'ai-explainer') . '</a>';
            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * Render the basic settings tab content
     */
    public function render() {
        // Check if help should be shown
        $has_api_key = !empty(get_option('explainer_openai_api_key'));
        $has_selector = !empty(get_option('explainer_included_selectors'));
        $is_dismissed = get_user_meta(get_current_user_id(), 'explainer_getting_started_dismissed', true);

        // Show getting started guide until explicitly dismissed
        // Step progression will indicate what's complete
        $show_help = !$is_dismissed;

        // Determine active step based on completion
        if (!$has_api_key) {
            $active_step = 1;
            $progress_percent = 0;
        } elseif (!$has_selector) {
            $active_step = 2;
            $progress_percent = 33;
        } else {
            $active_step = 3;
            $progress_percent = 66;
        }

        if ($show_help): ?>
            <div class="explainer-getting-started" style="position: relative; background: white; border: 1px solid #e9ecef; border-radius: 8px; padding: 0; margin: 20px 0 24px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden;">
                <div style="background: linear-gradient(135deg, var(--plugin-primary, #0F172A) 0%, var(--plugin-vibrant, #ec4899) 100%); padding: 24px 32px; color: white;">
                    <h3 style="margin: 0 0 8px 0; color: white; font-size: 22px; font-weight: 700;">Getting started with AI Explainer</h3>
                    <p style="margin: 0; color: rgba(255,255,255,0.9); font-size: 15px;">Follow these simple steps to activate AI-powered explanations on your site</p>
                </div>

                <details open>
                    <summary style="cursor: pointer; font-weight: 600; padding: 16px 32px; color: var(--plugin-vibrant, #ec4899); font-size: 14px; list-style: none; display: flex; align-items: center; background: #fafafa; border-bottom: 1px solid #e9ecef;">
                        <span style="margin-right: 8px; transition: transform 0.2s ease;">▼</span>
                        <span style="flex: 1;">Quick setup guide</span>
                        <span style="background: white; color: var(--plugin-vibrant, #ec4899); padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; border: 1px solid #fce7f3;">3 minutes</span>
                    </summary>

                    <div style="padding: 32px;">
                        <div class="explainer-setup-steps" style="display: flex; gap: 24px; margin-bottom: 32px; position: relative;">
                            <div style="position: absolute; top: 24px; left: 40px; right: 40px; height: 2px; background: linear-gradient(90deg, var(--plugin-vibrant, #ec4899) 0%, var(--plugin-vibrant, #ec4899) <?php echo esc_attr($progress_percent); ?>%, #e9ecef <?php echo esc_attr($progress_percent); ?>%, #e9ecef 100%); z-index: 0;"></div>

                            <div style="flex: 1; position: relative; z-index: 1;">
                                <div style="width: 48px; height: 48px; background: <?php echo $active_step >= 1 ? 'var(--plugin-vibrant, #ec4899)' : '#e9ecef'; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: <?php echo $active_step >= 1 ? 'white' : '#94a3b8'; ?>; font-weight: 700; font-size: 20px; margin: 0 auto 16px; box-shadow: <?php echo $active_step >= 1 ? '0 4px 12px rgba(236, 72, 153, 0.25)' : 'none'; ?>; border: 4px solid white;">1</div>
                                <strong style="color: var(--plugin-primary, #0F172A); font-weight: 700; font-size: 16px; display: block; margin-bottom: 12px; text-align: center;">Add API key</strong>
                                <p style="margin: 0 0 12px 0; font-size: 14px; color: #64748b; line-height: 1.6; text-align: center;">Visit the <a href="#ai-provider" style="color: var(--plugin-vibrant, #ec4899); text-decoration: none; font-weight: 600; border-bottom: 1px solid transparent;">AI Provider tab</a> and enter your key</p>
                                <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer" style="display: block; text-align: center; color: var(--plugin-vibrant, #ec4899); text-decoration: none; font-size: 13px; font-weight: 600;">
                                    Get API key →
                                </a>
                            </div>

                            <div style="flex: 1; position: relative; z-index: 1;">
                                <div style="width: 48px; height: 48px; background: <?php echo $active_step >= 2 ? 'var(--plugin-vibrant, #ec4899)' : '#e9ecef'; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: <?php echo $active_step >= 2 ? 'white' : '#94a3b8'; ?>; font-weight: 700; font-size: 20px; margin: 0 auto 16px; box-shadow: <?php echo $active_step >= 2 ? '0 4px 12px rgba(236, 72, 153, 0.25)' : 'none'; ?>; border: 4px solid white;">2</div>
                                <strong style="color: var(--plugin-primary, #0F172A); font-weight: 700; font-size: 16px; display: block; margin-bottom: 12px; text-align: center;">Set content areas</strong>
                                <p style="margin: 0; font-size: 14px; color: #64748b; line-height: 1.6; text-align: center;">Go to <a href="#content" style="color: var(--plugin-vibrant, #ec4899); text-decoration: none; font-weight: 600; border-bottom: 1px solid transparent;">Content Rules</a> to choose where explanations appear</p>
                            </div>

                            <div style="flex: 1; position: relative; z-index: 1;">
                                <div style="width: 48px; height: 48px; background: <?php echo $active_step >= 3 ? 'var(--plugin-vibrant, #ec4899)' : '#e9ecef'; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: <?php echo $active_step >= 3 ? 'white' : '#94a3b8'; ?>; font-weight: 700; font-size: 20px; margin: 0 auto 16px; box-shadow: <?php echo $active_step >= 3 ? '0 4px 12px rgba(236, 72, 153, 0.25)' : 'none'; ?>; border: 4px solid white;">3</div>
                                <strong style="color: var(--plugin-primary, #0F172A); font-weight: 700; font-size: 16px; display: block; margin-bottom: 12px; text-align: center;">Test it out</strong>
                                <p style="margin: 0; font-size: 14px; color: #64748b; line-height: 1.6; text-align: center;">Visit your site and select text to see explanations in action</p>
                            </div>
                        </div>

                        <div style="text-align: center;">
                            <button type="button" class="button" id="dismiss-getting-started" style="background: var(--plugin-vibrant, #ec4899); border: none; color: white; padding: 12px 24px; font-weight: 600; font-size: 14px; border-radius: 6px; transition: all 0.2s ease; cursor: pointer; box-shadow: 0 2px 6px rgba(236, 72, 153, 0.2);">
                                Got it, hide this guide
                            </button>
                        </div>
                    </div>
                </details>
            </div>

            <style>
            .explainer-getting-started details[open] summary span:first-of-type {
                transform: rotate(0deg);
                display: inline-block;
            }
            .explainer-getting-started details:not([open]) summary span:first-of-type {
                transform: rotate(-90deg);
            }
            .explainer-getting-started details summary span:first-of-type {
                transition: transform 0.2s ease;
            }
            .explainer-getting-started details summary:hover {
                background: #f5f5f5;
            }
            .explainer-getting-started #dismiss-getting-started:hover {
                background: var(--plugin-primary, #0F172A);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(236, 72, 153, 0.3);
            }
            .explainer-getting-started a:hover {
                border-bottom-color: currentColor !important;
            }
            @media (max-width: 960px) {
                .explainer-setup-steps {
                    flex-direction: column !important;
                }
                .explainer-setup-steps > div:first-child::after {
                    display: none !important;
                }
            }
            </style>

            <script>
            jQuery(document).ready(function($) {
                $('#dismiss-getting-started').on('click', function() {
                    $('.explainer-getting-started').fadeOut();
                    $.post(ajaxurl, {
                        action: 'explainer_dismiss_getting_started',
                        nonce: '<?php echo wp_create_nonce('explainer_dismiss_getting_started'); ?>'
                    });
                });

                // Handle tab navigation from getting started links
                $('.explainer-getting-started a[href^="#"]').on('click', function(e) {
                    e.preventDefault();
                    var targetTab = $(this).attr('href');
                    $('.nav-tab[href="' + targetTab + '"]').trigger('click');
                });
            });
            </script>
        <?php endif;
        ?>
        <div class="tab-content" id="basic-tab">
            <div class="plugin-section">
                <h3><?php echo esc_html__('Essential Configuration', 'ai-explainer'); ?></h3>
                <p><?php echo esc_html__('Configure core system settings to get your AI explanation system running.', 'ai-explainer'); ?></p>
            </div>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Plugin Status', 'ai-explainer'); ?></th>
                    <td>
                        <?php 
                        $is_auto_disabled = explainer_is_auto_disabled();
                        $is_enabled = get_option('explainer_enabled', true);
                        ?>
                        
                        <?php if ($is_auto_disabled): ?>
                            <!-- Auto-disabled state -->
                            <div class="explainer-status-disabled">
                                <p><span class="dashicons dashicons-warning" style="color: #dc3232;"></span> 
                                <strong style="color: #dc3232;"><?php echo esc_html__('Plugin Automatically Disabled', 'ai-explainer'); ?></strong></p>
                                
                                <?php 
                                $stats = explainer_get_usage_exceeded_stats();
                                if (!empty($stats['reason'])): ?>
                                    <p><strong><?php echo esc_html__('Reason:', 'ai-explainer'); ?></strong> <?php echo esc_html($stats['reason']); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($stats['provider'])): ?>
                                    <p><strong><?php echo esc_html__('Provider:', 'ai-explainer'); ?></strong> <?php echo esc_html($stats['provider']); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($stats['time_since'])): ?>
                                    <p><strong><?php echo esc_html__('Disabled:', 'ai-explainer'); ?></strong> <?php echo esc_html($stats['time_since']); ?></p>
                                <?php endif; ?>
                                
                                <div class="explainer-reenable-section" style="margin-top: 15px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                                    <h4><?php echo esc_html__('Re-enable Plugin', 'ai-explainer'); ?></h4>
                                    <p><?php echo esc_html__('Before re-enabling, please ensure you have resolved the API usage limit issues with your provider.', 'ai-explainer'); ?></p>
                                    <button type="button" class="btn-base btn-primary explainer-reenable-btn-settings" 
                                            data-nonce="<?php echo esc_attr(wp_create_nonce('explainer_reenable_plugin')); ?>">
                                        <?php echo esc_html__('Re-enable Plugin Now', 'ai-explainer'); ?>
                                    </button>
                                    <p class="description" style="margin-top: 10px;">
                                        <?php echo esc_html__('This will clear the auto-disable flag and restore normal plugin functionality.', 'ai-explainer'); ?>
                                    </p>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Normal enabled/disabled state -->
                            <div class="plugin-field">
                                <label class="plugin-toggle-label">
                                    <input type="hidden" name="explainer_enabled" value="0" />
                                    <input type="checkbox" name="explainer_enabled" id="explainer_enabled" value="1" <?php checked($is_enabled, true); ?> />
                                    <span class="plugin-toggle-text"><?php echo esc_html__('Enable intelligent text explanations', 'ai-explainer'); ?></span>
                                </label>
                                
                                <details class="plugin-help">
                                    <summary><?php echo esc_html__('What does this do?', 'ai-explainer'); ?></summary>
                                    <div class="plugin-help-content">
                                        <p><?php echo esc_html__('Activates the AI explanation system site-wide. When enabled, users can select text on your website to receive intelligent AI-generated explanations via elegant tooltips.', 'ai-explainer'); ?></p>
                                        <p><strong><?php echo esc_html__('Result:', 'ai-explainer'); ?></strong> <?php echo esc_html__('Enhanced user engagement and content comprehension.', 'ai-explainer'); ?></p>
                                    </div>
                                </details>
                                
                                <?php if (!$is_enabled): ?>
                                    <p style="color: #d63638; margin-top: 12px; font-style: italic;">
                                        <span class="dashicons dashicons-info" style="color: #d63638;"></span>
                                        <?php echo esc_html__('System currently disabled manually.', 'ai-explainer'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="explainer_language"><?php echo esc_html__('Language', 'ai-explainer'); ?></label>
                    </th>
                    <td>
                        <div class="select-wrapper">
                            <select name="explainer_language" id="explainer_language" class="styled-select">
                                <option value="en_US" <?php selected(get_option('explainer_language', 'en_GB'), 'en_US'); ?>>
                                    <?php echo esc_html__('English (United States)', 'ai-explainer'); ?>
                                </option>
                                <option value="en_GB" <?php selected(get_option('explainer_language', 'en_GB'), 'en_GB'); ?>>
                                    <?php echo esc_html__('English (United Kingdom)', 'ai-explainer'); ?>
                                </option>
                                <option value="es_ES" <?php selected(get_option('explainer_language', 'en_GB'), 'es_ES'); ?>>
                                    <?php echo esc_html__('Spanish (Spain)', 'ai-explainer'); ?>
                                </option>
                                <option value="de_DE" <?php selected(get_option('explainer_language', 'en_GB'), 'de_DE'); ?>>
                                    <?php echo esc_html__('German (Germany)', 'ai-explainer'); ?>
                                </option>
                                <option value="fr_FR" <?php selected(get_option('explainer_language', 'en_GB'), 'fr_FR'); ?>>
                                    <?php echo esc_html__('French (France)', 'ai-explainer'); ?>
                                </option>
                                <option value="hi_IN" <?php selected(get_option('explainer_language', 'en_GB'), 'hi_IN'); ?>>
                                    <?php echo esc_html__('Hindi (India)', 'ai-explainer'); ?>
                                </option>
                                <option value="zh_CN" <?php selected(get_option('explainer_language', 'en_GB'), 'zh_CN'); ?>>
                                    <?php echo esc_html__('Chinese (Simplified)', 'ai-explainer'); ?>
                                </option>
                            </select>
                        </div>
                        <p class="description"><?php echo esc_html__('Select the language for the AI explanations.', 'ai-explainer'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Onboarding Tutorial', 'ai-explainer'); ?></th>
                    <td>
                        <div class="plugin-field">
                            <label class="plugin-toggle-label">
                                <input type="hidden" name="explainer_onboarding_enabled" value="0" />
                                <input type="checkbox" name="explainer_onboarding_enabled" id="explainer_onboarding_enabled" value="1" <?php checked(get_option('explainer_onboarding_enabled', true), true); ?> />
                                <span class="plugin-toggle-text"><?php echo esc_html__('Show onboarding tutorial for new users', 'ai-explainer'); ?></span>
                            </label>
                            
                            <details class="plugin-help">
                                <summary><?php echo esc_html__('What does this do?', 'ai-explainer'); ?></summary>
                                <div class="plugin-help-content">
                                    <p><?php echo esc_html__('When enabled, first-time users will see a helpful tutorial panel that introduces them to the AI explanation feature. The tutorial appears at the bottom of the page and shows users how to select text to get explanations.', 'ai-explainer'); ?></p>
                                    <p><strong><?php echo esc_html__('Result:', 'ai-explainer'); ?></strong> <?php echo esc_html__('Better user adoption and understanding of your AI explanation feature.', 'ai-explainer'); ?></p>
                                    <p><strong><?php echo esc_html__('Note:', 'ai-explainer'); ?></strong> <?php echo esc_html__('Users who have already seen the tutorial won\'t see it again, even if this is re-enabled.', 'ai-explainer'); ?></p>
                                </div>
                            </details>
                        </div>
                    </td>
                </tr>
                
                <?php if (explainer_feature_available('auto_scan_content')): ?>
                <tr>
                    <th scope="row"><?php echo esc_html__('Auto-scan New Content', 'ai-explainer'); ?></th>
                    <td>
                        <div class="plugin-field" style="margin-bottom: 15px;">
                            <label class="plugin-toggle-label">
                                <input type="hidden" name="explainer_auto_scan_posts" value="0" />
                                <input type="checkbox" name="explainer_auto_scan_posts" id="explainer_auto_scan_posts" value="1" <?php checked(get_option('explainer_auto_scan_posts', false), true); ?> />
                                <span class="plugin-toggle-text"><?php echo esc_html__('Automatically scan new posts', 'ai-explainer'); ?></span>
                            </label>

                            <details class="plugin-help">
                                <summary><?php echo esc_html__('What does this do?', 'ai-explainer'); ?></summary>
                                <div class="plugin-help-content">
                                    <p><?php echo esc_html__('When enabled, newly created posts will automatically be queued for AI term scanning without manual intervention. The scan status will be set to enabled by default.', 'ai-explainer'); ?></p>
                                    <p><strong><?php echo esc_html__('Result:', 'ai-explainer'); ?></strong> <?php echo esc_html__('New posts will have AI explanations ready without additional setup.', 'ai-explainer'); ?></p>
                                    <p><strong><?php echo esc_html__('Note:', 'ai-explainer'); ?></strong> <?php echo esc_html__('This only affects new posts created after enabling this setting.', 'ai-explainer'); ?></p>
                                </div>
                            </details>
                        </div>

                        <div class="plugin-field">
                            <label class="plugin-toggle-label">
                                <input type="hidden" name="explainer_auto_scan_pages" value="0" />
                                <input type="checkbox" name="explainer_auto_scan_pages" id="explainer_auto_scan_pages" value="1" <?php checked(get_option('explainer_auto_scan_pages', false), true); ?> />
                                <span class="plugin-toggle-text"><?php echo esc_html__('Automatically scan new pages', 'ai-explainer'); ?></span>
                            </label>

                            <details class="plugin-help">
                                <summary><?php echo esc_html__('What does this do?', 'ai-explainer'); ?></summary>
                                <div class="plugin-help-content">
                                    <p><?php echo esc_html__('When enabled, newly created pages will automatically be queued for AI term scanning without manual intervention. The scan status will be set to enabled by default.', 'ai-explainer'); ?></p>
                                    <p><strong><?php echo esc_html__('Result:', 'ai-explainer'); ?></strong> <?php echo esc_html__('New pages will have AI explanations ready without additional setup.', 'ai-explainer'); ?></p>
                                    <p><strong><?php echo esc_html__('Note:', 'ai-explainer'); ?></strong> <?php echo esc_html__('This only affects new pages created after enabling this setting.', 'ai-explainer'); ?></p>
                                </div>
                            </details>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php if (explainer_feature_available('seo_metadata')): ?>
                <tr>
                    <th scope="row"><?php echo esc_html__('SEO Metadata Display', 'ai-explainer'); ?></th>
                    <td>
                        <?php
                        // Check if an SEO plugin is active
                        $admin_instance = ExplainerPlugin_Admin::get_instance();
                        $has_seo_plugin = $admin_instance->has_active_seo_plugin();
                        $seo_plugin_name = $admin_instance->get_active_seo_plugin_name();
                        $seo_setting_value = $has_seo_plugin ? false : get_option('explainer_output_seo_metadata', true);
                        ?>
                        <div class="plugin-field">
                            <label class="plugin-toggle-label">
                                <input type="hidden" name="explainer_output_seo_metadata" value="0" />
                                <input type="checkbox" name="explainer_output_seo_metadata" id="explainer_output_seo_metadata" value="1" <?php checked($seo_setting_value, true); ?> <?php disabled($has_seo_plugin, true); ?> />
                                <span class="plugin-toggle-text"><?php echo esc_html__('Output SEO metadata for blog posts created by this plugin', 'ai-explainer'); ?></span>
                            </label>

                            <?php if ($has_seo_plugin): ?>
                                <div class="seo-plugin-notice" style="margin-top: 10px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
                                    <p style="margin: 0; color: #856404;">
                                        <span class="dashicons dashicons-info" style="color: #856404;"></span>
                                        <strong><?php echo esc_html__('SEO Plugin Detected:', 'ai-explainer'); ?></strong>
                                        <?php echo esc_html($seo_plugin_name); ?>
                                    </p>
                                    <p style="margin: 5px 0 0 0; color: #856404; font-size: 13px;">
                                        <?php echo esc_html__('This option is disabled because you have an SEO plugin installed that will handle metadata output automatically.', 'ai-explainer'); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <details class="plugin-help">
                                <summary><?php echo esc_html__('What does this do?', 'ai-explainer'); ?></summary>
                                <div class="plugin-help-content">
                                    <?php if ($has_seo_plugin): ?>
                                        <p><?php echo esc_html__('This setting would output SEO metadata (title, description, keywords) to the page HTML for blog posts created from popular selections.', 'ai-explainer'); ?></p>
                                        <p><strong><?php echo esc_html__('Current Status:', 'ai-explainer'); ?></strong> <?php
                                        /* translators: %s: name of the active SEO plugin */
                                        printf(esc_html__('Disabled because %s is active and will handle SEO metadata automatically.', 'ai-explainer'), esc_html($seo_plugin_name)); ?></p>
                                    <?php else: ?>
                                        <p><?php echo esc_html__('When enabled, the plugin will output SEO metadata (title, description, keywords) to the page HTML for blog posts created from popular selections. This only affects posts created by this plugin, not existing content.', 'ai-explainer'); ?></p>
                                        <p><strong><?php echo esc_html__('Note:', 'ai-explainer'); ?></strong> <?php echo esc_html__('If you install an SEO plugin like Yoast or RankMath later, this setting will be automatically disabled to avoid conflicts.', 'ai-explainer'); ?></p>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <style>
        /* Custom select styling with down arrow */
        .select-wrapper {
            position: relative;
            display: inline-block;
            width: auto;
            min-width: 250px;
        }

        .select-wrapper::after {
            content: '\25BC';
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            pointer-events: none;
            color: #555;
            font-size: 10px;
        }

        .select-wrapper select.styled-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding-right: 35px;
            background-color: #fff;
            cursor: pointer;
        }
        </style>
        <?php
    }
}