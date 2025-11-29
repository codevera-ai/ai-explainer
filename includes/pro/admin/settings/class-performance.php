<?php
/**
 * Performance Settings Section
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle performance settings tab rendering
 */
class ExplainerPlugin_Performance {
    
    /**
     * Render the performance settings tab content
     */
    public function render() {
        ?>
        <div class="tab-content" id="performance-tab" style="display: none;">
            <h2><?php echo esc_html__('Performance & Caching', 'ai-explainer'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Caching', 'ai-explainer'); ?></th>
                    <td>
                        <fieldset>
                            <label for="explainer_cache_enabled">
                                <input type="hidden" name="explainer_cache_enabled" value="0" />
                                <input type="checkbox" name="explainer_cache_enabled" id="explainer_cache_enabled" value="1" <?php checked(get_option('explainer_cache_enabled', true), true); ?> />
                                <?php echo esc_html__('Enable explanation caching', 'ai-explainer'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Stores AI explanations in the database so identical text selections return instant cached results instead of making new API calls. This significantly reduces API costs and provides faster responses for repeated queries. Disable caching when testing provider changes, debugging explanation quality, updating content that needs fresh explanations, or measuring actual API performance without cache interference.', 'ai-explainer'); ?></p>
                            <br>
                            <label for="explainer_cache_duration">
                                <?php echo esc_html__('Cache Duration:', 'ai-explainer'); ?>
                                <input type="number" name="explainer_cache_duration" id="explainer_cache_duration" value="<?php echo esc_attr(get_option('explainer_cache_duration', 24)); ?>" min="1" max="168" class="small-text" />
                                <?php echo esc_html__('hours', 'ai-explainer'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('How long cached explanations remain valid before requiring a fresh API call (1-168 hours).', 'ai-explainer'); ?></p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Rate Limiting', 'ai-explainer'); ?></th>
                    <td>
                        <fieldset>
                            <label for="explainer_rate_limit_enabled">
                                <input type="hidden" name="explainer_rate_limit_enabled" value="0" />
                                <input type="checkbox" name="explainer_rate_limit_enabled" id="explainer_rate_limit_enabled" value="1" <?php checked(get_option('explainer_rate_limit_enabled', true), true); ?> />
                                <?php echo esc_html__('Enable rate limiting to prevent abuse', 'ai-explainer'); ?>
                            </label>
                            <br><br>
                            <label for="explainer_rate_limit_logged">
                                <?php echo esc_html__('Logged-in users:', 'ai-explainer'); ?>
                                <input type="number" name="explainer_rate_limit_logged" id="explainer_rate_limit_logged" value="<?php echo esc_attr(get_option('explainer_rate_limit_logged', 100)); ?>" min="1" max="100" class="small-text" />
                                <?php echo esc_html__('requests per minute', 'ai-explainer'); ?>
                            </label>
                            <br><br>
                            <label for="explainer_rate_limit_anonymous">
                                <?php echo esc_html__('Anonymous users:', 'ai-explainer'); ?>
                                <input type="number" name="explainer_rate_limit_anonymous" id="explainer_rate_limit_anonymous" value="<?php echo esc_attr(get_option('explainer_rate_limit_anonymous', 50)); ?>" min="1" max="50" class="small-text" />
                                <?php echo esc_html__('requests per minute', 'ai-explainer'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Set different rate limits for logged-in and anonymous users.', 'ai-explainer'); ?></p>
                            <p class="description">
                                <strong><?php echo esc_html__('How it works:', 'ai-explainer'); ?></strong> 
                                <?php echo esc_html__('Rate limits reset every minute. For example, "20 requests per minute" means users can make up to 20 explanation requests within any 60-second period. After 60 seconds, the counter resets. Cached explanations don\'t count toward the limit.', 'ai-explainer'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Mobile Experience', 'ai-explainer'); ?></th>
                    <td>
                        <fieldset>
                            <label for="explainer_mobile_selection_delay">
                                <?php echo esc_html__('Mobile Selection Delay:', 'ai-explainer'); ?>
                                <input type="number" name="explainer_mobile_selection_delay" id="explainer_mobile_selection_delay" value="<?php echo esc_attr(get_option('explainer_mobile_selection_delay', 1000)); ?>" min="0" max="5000" step="100" class="small-text" />
                                <?php echo esc_html__('milliseconds', 'ai-explainer'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Delay before showing explanations on mobile devices to allow users time to expand their text selection (0-5000ms). Recommended: 1000ms.', 'ai-explainer'); ?></p>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
}