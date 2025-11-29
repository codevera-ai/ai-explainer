<?php
/**
 * Appearance Settings Section
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle appearance settings tab rendering
 */
class ExplainerPlugin_Appearance {
    
    /**
     * Render the appearance settings tab content
     */
    public function render() {
        ?>
        <div class="tab-content" id="appearance-tab" style="display: none;">
            <h2><?php echo esc_html__('Appearance Customization', 'ai-explainer'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Toggle Button Position', 'ai-explainer'); ?></th>
                    <td>
                        <select name="explainer_toggle_position" id="explainer_toggle_position">
                            <option value="bottom-right" <?php selected(get_option('explainer_toggle_position', 'bottom-right'), 'bottom-right'); ?>>
                                <?php echo esc_html__('Bottom Right', 'ai-explainer'); ?>
                            </option>
                            <option value="bottom-left" <?php selected(get_option('explainer_toggle_position', 'bottom-right'), 'bottom-left'); ?>>
                                <?php echo esc_html__('Bottom Left', 'ai-explainer'); ?>
                            </option>
                            <option value="top-right" <?php selected(get_option('explainer_toggle_position', 'bottom-right'), 'top-right'); ?>>
                                <?php echo esc_html__('Top Right', 'ai-explainer'); ?>
                            </option>
                            <option value="top-left" <?php selected(get_option('explainer_toggle_position', 'bottom-right'), 'top-left'); ?>>
                                <?php echo esc_html__('Top Left', 'ai-explainer'); ?>
                            </option>
                        </select>
                        <p class="description"><?php echo esc_html__('Choose where to position the toggle button on the page.', 'ai-explainer'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Tooltip Colours', 'ai-explainer'); ?></th>
                    <td>
                        <table class="colour-settings-table">
                            <tr>
                                <td style="width: 200px; padding-right: 15px;">
                                    <label for="explainer_tooltip_bg_color" style="font-weight: 500;">
                                        <?php echo esc_html__('Background Colour', 'ai-explainer'); ?>
                                    </label>
                                    <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                        <?php echo esc_html__('Main tooltip background', 'ai-explainer'); ?>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="explainer_tooltip_bg_color" id="explainer_tooltip_bg_color" value="<?php echo esc_attr(get_option('explainer_tooltip_bg_color', '#1e293b')); ?>" class="color-field color-picker" data-default-color="#1e293b" />
                                </td>
                            </tr>
                            <tr>
                                <td style="padding-right: 15px; padding-top: 15px;">
                                    <label for="explainer_tooltip_text_color" style="font-weight: 500;">
                                        <?php echo esc_html__('Text Colour', 'ai-explainer'); ?>
                                    </label>
                                    <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                        <?php echo esc_html__('Main content text colour', 'ai-explainer'); ?>
                                    </div>
                                </td>
                                <td style="padding-top: 15px;">
                                    <input type="text" name="explainer_tooltip_text_color" id="explainer_tooltip_text_color" value="<?php echo esc_attr(get_option('explainer_tooltip_text_color', '#ffffff')); ?>" class="color-field color-picker" data-default-color="#ffffff" />
                                </td>
                            </tr>
                            <tr>
                                <td style="padding-right: 15px; padding-top: 15px;">
                                    <label for="explainer_tooltip_footer_color" style="font-weight: 500;">
                                        <?php echo esc_html__('Footer Text Colour', 'ai-explainer'); ?>
                                    </label>
                                    <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                        <?php echo esc_html__('Disclaimer and attribution text', 'ai-explainer'); ?>
                                    </div>
                                </td>
                                <td style="padding-top: 15px;">
                                    <input type="text" name="explainer_tooltip_footer_color" id="explainer_tooltip_footer_color" value="<?php echo esc_attr(get_option('explainer_tooltip_footer_color', '#ffffff')); ?>" class="color-field color-picker" data-default-color="#ffffff" />
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Toggle Button Colours', 'ai-explainer'); ?></th>
                    <td>
                        <table class="colour-settings-table">
                            <tr>
                                <td style="width: 200px; padding-right: 15px;">
                                    <label for="explainer_button_enabled_color" style="font-weight: 500;">
                                        <?php echo esc_html__('Enabled Button Colour', 'ai-explainer'); ?>
                                    </label>
                                    <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                        <?php echo esc_html__('When explanations are active', 'ai-explainer'); ?>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="explainer_button_enabled_color" id="explainer_button_enabled_color" value="<?php echo esc_attr(get_option('explainer_button_enabled_color', '#dc2626')); ?>" class="color-field color-picker" data-default-color="#dc2626" />
                                </td>
                            </tr>
                            <tr>
                                <td style="padding-right: 15px; padding-top: 15px;">
                                    <label for="explainer_button_disabled_color" style="font-weight: 500;">
                                        <?php echo esc_html__('Disabled Button Colour', 'ai-explainer'); ?>
                                    </label>
                                    <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                        <?php echo esc_html__('When explanations are inactive', 'ai-explainer'); ?>
                                    </div>
                                </td>
                                <td style="padding-top: 15px;">
                                    <input type="text" name="explainer_button_disabled_color" id="explainer_button_disabled_color" value="<?php echo esc_attr(get_option('explainer_button_disabled_color', '#374151')); ?>" class="color-field color-picker" data-default-color="#374151" />
                                </td>
                            </tr>
                            <tr>
                                <td style="padding-right: 15px; padding-top: 15px;">
                                    <label for="explainer_button_text_color" style="font-weight: 500;">
                                        <?php echo esc_html__('Button Text Colour', 'ai-explainer'); ?>
                                    </label>
                                    <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                        <?php echo esc_html__('Text and icon colour on button', 'ai-explainer'); ?>
                                    </div>
                                </td>
                                <td style="padding-top: 15px;">
                                    <input type="text" name="explainer_button_text_color" id="explainer_button_text_color" value="<?php echo esc_attr(get_option('explainer_button_text_color', '#ffffff')); ?>" class="color-field color-picker" data-default-color="#ffffff" />
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Tooltip Footer', 'ai-explainer'); ?></th>
                    <td>
                        <fieldset>
                            <label for="explainer_show_disclaimer">
                                <input type="hidden" name="explainer_show_disclaimer" value="0" />
                                <input type="checkbox" name="explainer_show_disclaimer" id="explainer_show_disclaimer" value="1" <?php checked(get_option('explainer_show_disclaimer', true), true); ?> />
                                <?php echo esc_html__('Show accuracy disclaimer', 'ai-explainer'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Displays "AI-generated content may not always be accurate" at the bottom of explanations.', 'ai-explainer'); ?></p>
                            <br>
                            <label for="explainer_show_provider">
                                <input type="hidden" name="explainer_show_provider" value="0" />
                                <input type="checkbox" name="explainer_show_provider" id="explainer_show_provider" value="1" <?php checked(get_option('explainer_show_provider', true), true); ?> />
                                <?php echo esc_html__('Show AI provider attribution', 'ai-explainer'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Displays "Powered by OpenAI" or "Powered by Claude" to credit the AI provider.', 'ai-explainer'); ?></p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Reading Level Slider Colours', 'ai-explainer'); ?></th>
                    <td>
                        <table class="colour-settings-table">
                            <tr>
                                <td style="width: 200px; padding-right: 15px;">
                                    <label for="explainer_slider_track_color" style="font-weight: 500;">
                                        <?php echo esc_html__('Track Colour', 'ai-explainer'); ?>
                                    </label>
                                    <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                        <?php echo esc_html__('Background slider track', 'ai-explainer'); ?>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="explainer_slider_track_color" id="explainer_slider_track_color" value="<?php echo esc_attr(get_option('explainer_slider_track_color', '#ffffff')); ?>" class="color-field color-picker" data-default-color="#ffffff" />
                                </td>
                            </tr>
                            <tr>
                                <td style="padding-right: 15px; padding-top: 15px;">
                                    <label for="explainer_slider_thumb_color" style="font-weight: 500;">
                                        <?php echo esc_html__('Handle Colour', 'ai-explainer'); ?>
                                    </label>
                                    <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                        <?php echo esc_html__('Draggable slider handle', 'ai-explainer'); ?>
                                    </div>
                                </td>
                                <td style="padding-top: 15px;">
                                    <input type="text" name="explainer_slider_thumb_color" id="explainer_slider_thumb_color" value="<?php echo esc_attr(get_option('explainer_slider_thumb_color', '#8b5cf6')); ?>" class="color-field color-picker" data-default-color="#8b5cf6" />
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Preview', 'ai-explainer'); ?></th>
                    <td>
                        <?php $this->render_preview_section(); ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render the preview section
     */
    private function render_preview_section() {
        ?>
        <div id="tooltip-preview" class="tooltip-preview">
            <div class="explainer-tooltip explainer-tooltip-preview">
                <div class="explainer-tooltip-header">
                    <span class="explainer-tooltip-title" id="preview-tooltip-title"><?php echo esc_html__('Explanation', 'ai-explainer'); ?></span>
                    <button class="explainer-tooltip-close" type="button" id="preview-close-button">×</button>
                </div>
                <div class="explainer-tooltip-content">
                    <span id="preview-tooltip-content"><?php echo esc_html__('This is how your tooltip will look with the selected colours. It matches the actual frontend design with proper spacing and typography.', 'ai-explainer'); ?></span>
                </div>
                <div class="explainer-tooltip-footer">
                    <!-- Reading Level Slider Preview (Static) -->
                    <div class="explainer-reading-level-slider" style="display: <?php echo get_option('explainer_show_reading_level_slider', true) ? 'block' : 'none'; ?>;">
                        <label class="explainer-slider-label">Reading Level:</label>
                        <svg class="explainer-multi-slider" width="100%" height="50" viewBox="0 0 300 50">
                            <!-- Slider Rail -->
                            <rect class="slider-rail" x="8" y="13.5" width="284" height="8" rx="4"></rect>

                            <!-- Thumb positions: 8, 79, 150, 221, 292 (standard=150, index 2) -->

                            <!-- Very Simple thumb -->
                            <g class="thumb-group" data-level="very_simple" data-index="0">
                                <circle class="slider-thumb" cx="8" cy="17.5" r="8"></circle>
                            </g>

                            <!-- Simple thumb -->
                            <g class="thumb-group" data-level="simple" data-index="1">
                                <circle class="slider-thumb" cx="79" cy="17.5" r="8"></circle>
                            </g>

                            <!-- Standard thumb (active) -->
                            <g class="thumb-group active" data-level="standard" data-index="2">
                                <circle class="slider-thumb active" cx="150" cy="17.5" r="8"></circle>
                            </g>

                            <!-- Detailed thumb -->
                            <g class="thumb-group" data-level="detailed" data-index="3">
                                <circle class="slider-thumb" cx="221" cy="17.5" r="8"></circle>
                            </g>

                            <!-- Expert thumb -->
                            <g class="thumb-group" data-level="expert" data-index="4">
                                <circle class="slider-thumb" cx="292" cy="17.5" r="8"></circle>
                            </g>

                            <!-- Labels -->
                            <text class="thumb-label" x="8" y="45" text-anchor="start">Basic</text>
                            <text class="thumb-label" x="79" y="45" text-anchor="middle">Simple</text>
                            <text class="thumb-label" x="150" y="45" text-anchor="middle">Standard</text>
                            <text class="thumb-label" x="221" y="45" text-anchor="middle">Detailed</text>
                            <text class="thumb-label" x="292" y="45" text-anchor="end">Expert</text>
                        </svg>
                    </div>

                    <!-- Mobile Reading Level Dropdown Preview (Static) -->
                    <div class="explainer-reading-level-mobile" style="display: <?php echo get_option('explainer_show_reading_level_slider', true) ? 'block' : 'none'; ?>;">
                        <label style="font-size: 12px; margin-bottom: 5px; display: block; color: inherit;">Reading Level:</label>
                        <select style="width: 100%; padding: 4px 8px; border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.1); color: inherit; border-radius: 4px;">
                            <option>Basic</option>
                            <option>Simple</option>
                            <option selected>Standard</option>
                            <option>Detailed</option>
                            <option>Expert</option>
                        </select>
                    </div>

                    <div class="explainer-disclaimer" id="preview-disclaimer"><?php echo esc_html__('AI-generated content may not always be accurate', 'ai-explainer'); ?></div>
                    <div class="explainer-provider" id="preview-provider"><?php echo esc_html__('Powered by OpenAI', 'ai-explainer'); ?></div>
                </div>
            </div>
        </div>
        
        <div id="button-preview" class="button-preview" style="margin-top: 20px;">
            <h4><?php echo esc_html__('Toggle Button Preview:', 'ai-explainer'); ?></h4>
            <div style="display: flex; gap: 15px; align-items: center;">
                <button type="button" class="preview-explainer-button enabled" id="preview-button-enabled">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <?php echo esc_html__('Explainer', 'ai-explainer'); ?>
                </button>
                <span><?php echo esc_html__('(Enabled)', 'ai-explainer'); ?></span>
                
                <button type="button" class="preview-explainer-button disabled" id="preview-button-disabled">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <?php echo esc_html__('Explainer', 'ai-explainer'); ?>
                </button>
                <span><?php echo esc_html__('(Disabled)', 'ai-explainer'); ?></span>
            </div>
        </div>
        <?php
    }
}