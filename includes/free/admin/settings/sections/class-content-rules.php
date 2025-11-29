<?php
/**
 * Content Rules Settings Section
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle content rules settings tab rendering
 */
class ExplainerPlugin_Content_Rules {
    
    /**
     * Render the content rules settings tab content
     */
    public function render() {
        ?>
        <div class="tab-content" id="content-tab" style="display: none;">
            <h2><?php echo esc_html__('Content Selection Rules', 'ai-explainer'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Selection Length', 'ai-explainer'); ?></th>
                    <td>
                        <fieldset>
                            <label for="explainer_min_selection_length">
                                <?php echo esc_html__('Minimum characters:', 'ai-explainer'); ?>
                                <input type="number" name="explainer_min_selection_length" id="explainer_min_selection_length" value="<?php echo esc_attr(get_option('explainer_min_selection_length', 3)); ?>" min="1" max="50" class="small-text" />
                            </label>
                            <br><br>
                            <label for="explainer_max_selection_length">
                                <?php echo esc_html__('Maximum characters:', 'ai-explainer'); ?>
                                <input type="number" name="explainer_max_selection_length" id="explainer_max_selection_length" value="<?php echo esc_attr(get_option('explainer_max_selection_length', 200)); ?>" min="50" max="1000" class="small-text" />
                            </label>
                            <p class="description"><?php echo esc_html__('Set the minimum and maximum character limits for text selection.', 'ai-explainer'); ?></p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Word Count', 'ai-explainer'); ?></th>
                    <td>
                        <fieldset>
                            <label for="explainer_min_words">
                                <?php echo esc_html__('Minimum words:', 'ai-explainer'); ?>
                                <input type="number" name="explainer_min_words" id="explainer_min_words" value="<?php echo esc_attr(get_option('explainer_min_words', 1)); ?>" min="1" max="10" class="small-text" />
                            </label>
                            <br><br>
                            <label for="explainer_max_words">
                                <?php echo esc_html__('Maximum words:', 'ai-explainer'); ?>
                                <input type="number" name="explainer_max_words" id="explainer_max_words" value="<?php echo esc_attr(get_option('explainer_max_words', 30)); ?>" min="5" max="100" class="small-text" />
                            </label>
                            <p class="description"><?php echo esc_html__('Set the minimum and maximum word count limits for text selection.', 'ai-explainer'); ?></p>
                        </fieldset>
                    </td>
                </tr>

                <tr class="advanced-content-options-toggle">
                    <th scope="row"><?php echo esc_html__('Included Areas', 'ai-explainer'); ?></th>
                    <td>
                        <button type="button" class="btn-base btn-secondary" id="toggle-advanced-content-options">
                            <?php echo esc_html__('Show advanced content area controls', 'ai-explainer'); ?>
                        </button>
                        <p class="description">
                            <?php echo esc_html__('The plugin automatically works with most themes. Only enable this if you need to customise which areas of your site show explanations.', 'ai-explainer'); ?>
                        </p>
                    </td>
                </tr>

                <tr class="advanced-content-options advanced-content-options-included" style="display: none;">
                    <th scope="row"></th>
                    <td>
                        <textarea name="explainer_included_selectors" id="explainer_included_selectors" rows="4" cols="50" class="large-text"><?php echo esc_textarea(ExplainerPlugin_Config::get_setting('included_selectors')); ?></textarea>
                        <p class="description">
                            <?php echo esc_html__('CSS selectors for areas where text selection is allowed (comma-separated).', 'ai-explainer'); ?>
                            <br><strong><?php echo esc_html__('Tip:', 'ai-explainer'); ?></strong> <?php echo esc_html__('If you specify included areas, only text within these selectors will be selectable. This makes most excluded areas redundant unless they overlap with included areas.', 'ai-explainer'); ?>
                        </p>
                    </td>
                </tr>

                <tr class="advanced-content-options advanced-content-options-excluded" style="display: none;">
                    <th scope="row">
                        <label for="explainer_excluded_selectors"><?php echo esc_html__('Excluded Areas', 'ai-explainer'); ?></label>
                    </th>
                    <td>
                        <textarea name="explainer_excluded_selectors" id="explainer_excluded_selectors" rows="4" cols="50" class="large-text"><?php echo esc_textarea(ExplainerPlugin_Config::get_setting('excluded_selectors')); ?></textarea>
                        <p class="description">
                            <?php echo esc_html__('CSS selectors for areas where text selection is blocked (comma-separated).', 'ai-explainer'); ?>
                            <br><strong><?php echo esc_html__('Note:', 'ai-explainer'); ?></strong> <?php echo esc_html__('If you have included areas above, excluded areas only matter when they overlap with included selectors (e.g., include .content but exclude .content .ads).', 'ai-explainer'); ?>
                        </p>
                    </td>
                </tr>

                <tr class="advanced-content-options advanced-content-options-auto-detect" style="display: none;">
                    <th scope="row"><?php echo esc_html__('Auto-Detect Content Area', 'ai-explainer'); ?></th>
                    <td>
                        <div style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                            <p style="margin-top: 0;">
                                <?php echo esc_html__('Cannot find the right CSS selector? Let the plugin detect it automatically.', 'ai-explainer'); ?>
                            </p>
                            <p class="description" style="margin-bottom: 15px;">
                                <?php echo esc_html__('When enabled, visit your website and select some text from a blog post or page. The plugin will automatically detect the content area and save the correct selector.', 'ai-explainer'); ?>
                            </p>
                            <?php
                            $setup_mode_active = get_option('explainer_setup_mode_active');
                            $button_text = $setup_mode_active ? __('Disable Setup Mode', 'ai-explainer') : __('Enable Setup Mode', 'ai-explainer');
                            $button_class = $setup_mode_active ? 'btn-base btn-secondary' : 'btn-base btn-primary';
                            ?>
                            <button type="button" class="<?php echo esc_attr($button_class); ?>" id="toggle-setup-mode" data-active="<?php echo $setup_mode_active ? '1' : '0'; ?>">
                                <?php echo esc_html($button_text); ?>
                            </button>
                            <div id="setup-mode-status" style="margin-top: 10px; display: <?php echo $setup_mode_active ? 'block' : 'none'; ?>;">
                                <p style="color: #46b450; margin: 0;">
                                    <span class="dashicons dashicons-yes-alt" style="vertical-align: middle;"></span>
                                    <span id="setup-mode-message"><?php
                                        if ($setup_mode_active) {
                                            echo esc_html__('Setup mode is currently active.', 'ai-explainer');
                                        }
                                    ?></span>
                                </p>
                            </div>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="explainer_blocked_words"><?php echo esc_html__('Blocked Words', 'ai-explainer'); ?></label>
                    </th>
                    <td>
                        <textarea name="explainer_blocked_words" id="explainer_blocked_words" rows="8" cols="50" class="large-text" placeholder="<?php echo esc_attr__('Enter one word or phrase per line', 'ai-explainer'); ?>"><?php echo esc_textarea(get_option('explainer_blocked_words', '')); ?></textarea>
                        <p class="description">
                            <?php echo esc_html__('Enter words or phrases that should be blocked from getting AI explanations (one per line).', 'ai-explainer'); ?>
                            <br>
                            <span id="blocked-words-count">0</span> <?php echo esc_html__('words blocked', 'ai-explainer'); ?>
                        </p>
                        <p class="description" style="margin-top: 8px;">
                            <strong><?php echo esc_html__('How it works:', 'ai-explainer'); ?></strong>
                            <?php echo esc_html__('When a user selects text containing a blocked word, the explanation request is prevented. For example, if you block "password", selecting "reset your password" will not trigger an explanation.', 'ai-explainer'); ?>
                        </p>
                        
                        <div class="blocked-words-options" style="margin-top: 10px;">
                            <label>
                                <input type="hidden" name="explainer_blocked_words_case_sensitive" value="0" />
                                <input type="checkbox" name="explainer_blocked_words_case_sensitive" id="explainer_blocked_words_case_sensitive" value="1" <?php checked(get_option('explainer_blocked_words_case_sensitive', false), true); ?> />
                                <?php echo esc_html__('Case sensitive matching', 'ai-explainer'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="hidden" name="explainer_blocked_words_whole_word" value="0" />
                                <input type="checkbox" name="explainer_blocked_words_whole_word" id="explainer_blocked_words_whole_word" value="1" <?php checked(get_option('explainer_blocked_words_whole_word', false), true); ?> />
                                <?php echo esc_html__('Match whole words only', 'ai-explainer'); ?>
                            </label>
                        </div>
                        
                        <div class="blocked-words-actions" style="margin-top: 10px;">
                            <button type="button" class="btn-base btn-destructive" id="clear-blocked-words"><?php echo esc_html__('Clear All', 'ai-explainer'); ?></button>
                            <button type="button" class="btn-base btn-secondary" id="load-default-blocked-words"><?php echo esc_html__('Load Common Inappropriate Words', 'ai-explainer'); ?></button>
                        </div>
                        
                        <p class="description" style="margin-top: 10px;">
                            <strong><?php echo esc_html__('Note:', 'ai-explainer'); ?></strong> 
                            <?php echo esc_html__('Maximum 500 words, 100 characters per word. Special characters will be removed.', 'ai-explainer'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Stop Word Filtering', 'ai-explainer'); ?></th>
                    <td>
                        <fieldset>
                            <label for="explainer_stop_word_filtering_enabled">
                                <input type="hidden" name="explainer_stop_word_filtering_enabled" value="0" />
                                <input type="checkbox" name="explainer_stop_word_filtering_enabled" id="explainer_stop_word_filtering_enabled" value="1" <?php checked(get_option('explainer_stop_word_filtering_enabled', true), true); ?> />
                                <?php echo esc_html__('Filter common stop words', 'ai-explainer'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('Prevents API calls for selections containing only common words like "the", "and", "or". Helps reduce costs by filtering meaningless selections. Only applies to very short selections (1-2 words) to avoid blocking legitimate phrases.', 'ai-explainer'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
}