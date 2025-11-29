<?php
/**
 * AI Provider Settings Section
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle AI provider settings tab rendering
 */
class ExplainerPlugin_AI_Provider {

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
            echo wp_kses_post($field_html);
        } else {
            // Wrap in premium disabled container
            echo '<div class="premium-feature-wrapper">';
            echo '<div class="premium-field-disabled">';
            // Make the field disabled
            $disabled_html = str_replace(array('<select', '<input', '<textarea'), array('<select disabled="disabled"', '<input disabled="disabled"', '<textarea disabled="disabled"'), $field_html);
            echo wp_kses_post($disabled_html);
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
     * Render the AI provider settings tab content
     */
    public function render() {
        ?>
        <div class="tab-content" id="ai-provider-tab" style="display: none;">
            <div class="plugin-section">
                <h3><?php echo esc_html__('AI Provider Configuration', 'ai-explainer'); ?></h3>
                <p><?php echo esc_html__('Configure your AI provider, model selection, and API keys. Each provider offers different models with varying costs and capabilities.', 'ai-explainer'); ?></p>
            </div>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="explainer_api_provider"><?php echo esc_html__('AI Provider', 'ai-explainer'); ?></label>
                    </th>
                    <td>
                        <div class="select-wrapper">
                            <select name="explainer_api_provider" id="explainer_api_provider" class="styled-select">
                                <option value="openai" <?php selected(get_option('explainer_api_provider', 'openai'), 'openai'); ?>>
                                    <?php echo esc_html__('OpenAI (GPT Models)', 'ai-explainer'); ?>
                                </option>
                                <?php if (explainer_feature_available('provider_claude')): ?>
                                <option value="claude" <?php selected(get_option('explainer_api_provider', 'openai'), 'claude'); ?>>
                                    <?php echo esc_html__('Claude (Anthropic)', 'ai-explainer'); ?>
                                </option>
                                <?php endif; ?>
                                <?php if (explainer_feature_available('provider_gemini')): ?>
                                <option value="gemini" <?php selected(get_option('explainer_api_provider', 'openai'), 'gemini'); ?>>
                                    <?php echo esc_html__('Google Gemini', 'ai-explainer'); ?>
                                </option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <p class="description"><?php echo esc_html__('Choose your AI provider. Each provider has different models and pricing.', 'ai-explainer'); ?></p>

                        <?php $this->render_provider_help(); ?>
                    </td>
                </tr>
                
                <?php $this->render_api_key_fields(); ?>

                <tr class="reading-level-slider-row">
                    <th scope="row">
                        <label for="explainer_show_reading_level_slider"><?php echo esc_html__('Reading Level Slider', 'ai-explainer'); ?></label>
                    </th>
                    <td>
                        <label for="explainer_show_reading_level_slider">
                            <input type="hidden" name="explainer_show_reading_level_slider" value="0" />
                            <input type="checkbox" name="explainer_show_reading_level_slider" id="explainer_show_reading_level_slider" value="1" <?php checked(get_option('explainer_show_reading_level_slider', true), true); ?> />
                            <?php echo esc_html__('Show reading level slider on tooltips', 'ai-explainer'); ?>
                        </label>
                        <p class="description"><?php echo esc_html__('Displays the reading level slider allowing users to switch between explanation complexity levels. When disabled, all explanations default to standard reading level.', 'ai-explainer'); ?></p>
                    </td>
                </tr>

                <tr class="reading-level-prompts-row">
                    <th scope="row">
                        <label><?php echo esc_html__('Reading Level Prompts', 'ai-explainer'); ?></label>
                    </th>
                    <td>
                        <?php $this->render_reading_level_prompts(); ?>
                        <p class="description">
                            <?php echo esc_html__('Customize prompts for each reading level. Use {{selectedtext}} where you want the selected text to appear.', 'ai-explainer'); ?>
                        </p>
                        <br />
                        <button type="button" class="btn-base btn-secondary" id="reset-all-prompts-default"><?php echo esc_html__('Reset All to Default', 'ai-explainer'); ?></button>
                    </td>
                </tr>
            </table>
        </div>

        <style>
        .form-table .reading-level-slider-row {
            border-bottom: 0 !important;
        }
        .form-table .reading-level-slider-row th,
        .form-table .reading-level-slider-row td {
            border-bottom: 0 !important;
            box-shadow: none !important;
            padding-bottom: 20px !important;
        }
        .form-table .reading-level-prompts-row {
            border-top: 0 !important;
        }
        .form-table .reading-level-prompts-row th,
        .form-table .reading-level-prompts-row td {
            border-top: 0 !important;
            box-shadow: none !important;
            padding-top: 0 !important;
        }
        .form-table .reading-level-slider-row + .reading-level-prompts-row th,
        .form-table .reading-level-slider-row + .reading-level-prompts-row td {
            box-shadow: none !important;
        }
        .prompt-section {
            margin-bottom: 5px;
        }
        .prompt-header:hover {
            background: #e8e8e8 !important;
        }
        .prompt-toggle {
            transition: transform 0.2s ease;
        }
        .prompt-toggle.rotated {
            transform: rotate(180deg);
        }
        .reading-level-prompt {
            font-family: Consolas, Monaco, monospace;
            font-size: 13px;
        }
        .character-count {
            color: #666;
            font-size: 12px;
        }

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

        <script>
        (function($) {
            // Function to close all accordions
            function closeAllAccordions() {
                $('.prompt-content').hide();
                $('.prompt-toggle').removeClass('rotated');
            }

            // Close immediately
            closeAllAccordions();

            // Close on document ready (in case this runs before DOM is ready)
            $(document).ready(closeAllAccordions);

            // Close when AI Provider tab is shown
            $('a[href="#ai-provider"]').on('click', function() {
                setTimeout(closeAllAccordions, 50);
            });

            // Use event delegation from document level to handle dynamically loaded content
            $(document).on('click', '.prompt-header', function() {
                const $content = $(this).next('.prompt-content');
                const $toggle = $(this).find('.prompt-toggle');

                $content.slideToggle(200);
                $toggle.toggleClass('rotated');

                // Update character count for this section when opened
                $content.find('.reading-level-prompt').each(function() {
                    const count = $(this).val().length;
                    $(this).closest('.prompt-content').find('.char-count').text(count);
                });
            });

            // Update character counts on input
            $(document).on('input', '.reading-level-prompt', function() {
                const count = $(this).val().length;
                $(this).closest('.prompt-content').find('.char-count').text(count);

                // Validate {{selectedtext}} placeholder
                const hasPlaceholder = $(this).val().includes('{{selectedtext}}');
                const $validation = $(this).closest('.prompt-content').find('.prompt-validation');

                if (!hasPlaceholder && $(this).val().trim() !== '') {
                    $validation.html('<span style="color: #d63384;">⚠ Missing {{selectedtext}} placeholder</span>');
                } else {
                    $validation.html('');
                }
            });

            // Reset individual prompt
            $(document).on('click', '.reset-prompt-btn', function() {
                const level = $(this).data('level');
                const defaultPrompt = $(this).data('default');
                const $textarea = $('#explainer_prompt_' + level);

                if (confirm('<?php echo esc_js(__('Reset this prompt to default?', 'ai-explainer')); ?>')) {
                    $textarea.val(defaultPrompt).trigger('input');
                }
            });

            // Reset all prompts
            $(document).on('click', '#reset-all-prompts-default', function() {
                if (confirm('<?php echo esc_js(__('Reset all prompts to default values?', 'ai-explainer')); ?>')) {
                    $('.reset-prompt-btn').each(function() {
                        const level = $(this).data('level');
                        const defaultPrompt = $(this).data('default');
                        $('#explainer_prompt_' + level).val(defaultPrompt).trigger('input');
                    });
                }
            });
        })(jQuery);
        </script>
        <?php
    }
    
    /**
     * Render provider help content
     */
    private function render_provider_help() {
        ?>
        <!-- Provider-specific help content -->
        <div id="provider-help-content">
            <!-- OpenAI Help -->
            <div class="provider-help openai-help" id="openai-help">
                <div class="provider-info-card">
                    <h4><?php echo esc_html__('OpenAI Provider Information', 'ai-explainer'); ?></h4>
                    <p><?php echo esc_html__('OpenAI offers reliable GPT models with consistent performance. Ideal for general-purpose text explanations.', 'ai-explainer'); ?></p>

                    <div class="model-info">
                        <h5><?php echo esc_html__('Model', 'ai-explainer'); ?></h5>
                        <p><?php echo esc_html__('This plugin uses GPT-5.1, the latest model from OpenAI. The model is automatically selected and optimised for generating clear, concise explanations.', 'ai-explainer'); ?></p>
                    </div>

                    <div class="pricing-note">
                        <h5><?php echo esc_html__('Pricing', 'ai-explainer'); ?></h5>
                        <p><?php echo esc_html__('Pricing varies by model and changes frequently. Please check the OpenAI website for current rates before setting up billing.', 'ai-explainer'); ?></p>
                    </div>

                    <div class="setup-instructions">
                        <h5><?php echo esc_html__('Setup Instructions', 'ai-explainer'); ?></h5>
                        <ol>
                            <li><?php echo esc_html__('Visit', 'ai-explainer'); ?> <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com/api-keys</a></li>
                            <li><?php echo esc_html__('Create a new API key', 'ai-explainer'); ?></li>
                            <li><?php echo esc_html__('Add billing information to your OpenAI account', 'ai-explainer'); ?></li>
                            <li><?php echo esc_html__('Enter the API key below and test it', 'ai-explainer'); ?></li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Claude Help -->
            <div class="provider-help claude-help" id="claude-help" style="display: none;">
                <div class="provider-info-card">
                    <h4><?php echo esc_html__('Claude Provider Information', 'ai-explainer'); ?></h4>
                    <p><?php echo esc_html__('Claude by Anthropic excels at nuanced, thoughtful explanations. Great for complex topics requiring careful reasoning.', 'ai-explainer'); ?></p>

                    <div class="model-info">
                        <h5><?php echo esc_html__('Model', 'ai-explainer'); ?></h5>
                        <p><?php echo esc_html__('This plugin uses Claude Haiku 4.5, the latest fast and efficient model from Anthropic. The model is automatically selected and optimised for generating clear explanations whilst keeping costs low.', 'ai-explainer'); ?></p>
                    </div>

                    <div class="pricing-note">
                        <h5><?php echo esc_html__('Pricing', 'ai-explainer'); ?></h5>
                        <p><?php echo esc_html__('Pricing varies by model and changes frequently. Please check the Anthropic website for current rates before adding credits to your account.', 'ai-explainer'); ?></p>
                    </div>

                    <div class="setup-instructions">
                        <h5><?php echo esc_html__('Setup Instructions', 'ai-explainer'); ?></h5>
                        <ol>
                            <li><?php echo esc_html__('Visit', 'ai-explainer'); ?> <a href="https://console.anthropic.com/account/keys" target="_blank">console.anthropic.com/account/keys</a></li>
                            <li><?php echo esc_html__('Create a new API key', 'ai-explainer'); ?></li>
                            <li><?php echo esc_html__('Add credits to your Anthropic account', 'ai-explainer'); ?></li>
                            <li><?php echo esc_html__('Enter the API key below and test it', 'ai-explainer'); ?></li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Gemini Help -->
            <div class="provider-help gemini-help" id="gemini-help" style="display: none;">
                <div class="provider-info-card">
                    <h4><?php echo esc_html__('Google Gemini Provider Information', 'ai-explainer'); ?></h4>
                    <p><?php echo esc_html__('Google Gemini offers advanced AI capabilities with competitive pricing. Ideal for high-quality text explanations with excellent performance.', 'ai-explainer'); ?></p>

                    <div class="model-info">
                        <h5><?php echo esc_html__('Model', 'ai-explainer'); ?></h5>
                        <p><?php echo esc_html__('This plugin uses Gemini 2.5 Flash, the latest high-performance model from Google. The model is automatically selected and optimised for fast, accurate explanations.', 'ai-explainer'); ?></p>
                    </div>

                    <div class="pricing-note">
                        <h5><?php echo esc_html__('Pricing', 'ai-explainer'); ?></h5>
                        <p><?php echo esc_html__('Pricing varies by model and changes frequently. Please check the Google AI Studio website for current rates before setting up your API key.', 'ai-explainer'); ?></p>
                    </div>

                    <div class="setup-instructions">
                        <h5><?php echo esc_html__('Setup Instructions', 'ai-explainer'); ?></h5>
                        <ol>
                            <li><?php echo esc_html__('Visit', 'ai-explainer'); ?> <a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com/app/apikey</a></li>
                            <li><?php echo esc_html__('Create a new API key', 'ai-explainer'); ?></li>
                            <li><?php echo esc_html__('Enter the API key below and test it', 'ai-explainer'); ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render API key fields for all providers
     */
    private function render_api_key_fields() {
        // OpenAI API Key (always available)
        $this->render_single_api_key_field('openai', 'OpenAI API Key', 'https://platform.openai.com/api-keys', 'platform.openai.com');

        // Claude API Key (pro only)
        if (explainer_feature_available('provider_claude')) {
            $this->render_single_api_key_field('claude', 'Claude API Key', 'https://console.anthropic.com/account/keys', 'console.anthropic.com');
        }

        // OpenRouter API Key (pro only)
        if (explainer_feature_available('provider_openrouter')) {
            $this->render_single_api_key_field('openrouter', 'OpenRouter API Key', 'https://openrouter.ai/keys', 'openrouter.ai');
        }

        // Gemini API Key (pro only)
        if (explainer_feature_available('provider_gemini')) {
            $this->render_single_api_key_field('gemini', 'Gemini API Key', 'https://aistudio.google.com/app/apikey', 'aistudio.google.com');
        }
    }
    
    /**
     * Render a single API key field
     */
    private function render_single_api_key_field($provider, $title, $signup_url, $signup_text) {
        // Use the same option names as the API proxy
        $option_name = "explainer_{$provider}_api_key";
        $key_configured = !empty(get_option($option_name, ''));
        $masked_key = '';
        
        if ($key_configured) {
            $api_proxy = new ExplainerPlugin_API_Proxy();
            $decrypted_api_key = $api_proxy->get_decrypted_api_key_for_provider($provider);
            if (!empty($decrypted_api_key)) {
                $masked_key = substr($decrypted_api_key, 0, 3) . '...' . substr($decrypted_api_key, -4);
            }
        }
        
        $is_hidden = $provider !== 'openai';
        ?>
        <tr class="api-key-row <?php echo esc_attr($provider); ?>-fields" <?php echo $is_hidden ? 'style="display: none;"' : ''; ?>>
            <th scope="row">
                <label for="explainer_<?php echo esc_attr($provider); ?>_api_key"><?php echo esc_html($title); ?></label>
            </th>
            <td>
                <?php if ($key_configured): ?>
                    <div class="api-key-status configured">
                        <span class="dashicons dashicons-yes-alt" style="color: #10b981;"></span>
                        <strong><?php echo esc_html__('API Key Configured:', 'ai-explainer'); ?></strong> 
                        <code><?php echo esc_html($masked_key); ?></code>
                        <button type="button" class="btn-base btn-primary test-stored-api-key" data-provider="<?php echo esc_attr($provider); ?>">
                            <?php echo esc_html__('Test Stored Key', 'ai-explainer'); ?>
                        </button>
                        <button type="button" class="btn-base btn-destructive delete-api-key-btn" id="delete-<?php echo esc_attr($provider); ?>-api-key">
                            <span class="dashicons dashicons-trash"></span>
                            <?php echo esc_html__('Delete', 'ai-explainer'); ?>
                        </button>
                    </div>
                <?php else: ?>
                    <div class="api-key-status not-configured">
                        <span class="dashicons dashicons-warning"></span>
                        <strong><?php echo esc_html__('No API Key Configured', 'ai-explainer'); ?></strong>
                    </div>
                <?php endif; ?>
                
                <div class="api-key-input-container">
                    <input type="password" name="explainer_<?php echo esc_attr($provider); ?>_api_key" id="explainer_<?php echo esc_attr($provider); ?>_api_key" value="" class="regular-text" placeholder="<?php echo esc_attr__('Enter new API key (leave empty to keep current)', 'ai-explainer'); ?>" autocomplete="new-password" />
                    <?php if ($key_configured): ?>
                        <input type="hidden" name="explainer_<?php echo esc_attr($provider); ?>_api_key_has_existing" value="1" />
                    <?php endif; ?>
                    <button type="button" class="btn-base btn-secondary btn-sm" id="toggle-<?php echo esc_attr($provider); ?>-key-visibility">
                        <?php echo esc_html__('Show', 'ai-explainer'); ?>
                    </button>
                    <button type="button" class="btn-base btn-primary" id="test-<?php echo esc_attr($provider); ?>-api-key">
                        <?php echo esc_html__('Test API Key', 'ai-explainer'); ?>
                    </button>
                </div>
                <p class="description">
                    <?php
                    printf(
                        /* translators: 1: API provider name (e.g. OpenAI, Claude), 2: HTML link to signup page */
                        esc_html__('Enter your %1$s API key. Get one from %2$s', 'ai-explainer'),
                        esc_html($title),
                        '<a href="' . esc_url($signup_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($signup_text) . '</a>'
                    );
                    ?>
                </p>
                <div id="<?php echo esc_attr($provider); ?>-api-key-status"></div>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Render expandable reading level prompts
     */
    private function render_reading_level_prompts() {
        $reading_levels = ExplainerPlugin_Config::get_reading_level_labels();
        $default_prompts = ExplainerPlugin_Config::get_default_reading_level_prompts();
        
        ?>
        <div class="reading-level-prompts-container">
            <?php foreach ($reading_levels as $level => $label): ?>
                <?php 
                $option_name = "explainer_prompt_{$level}";
                $current_prompt = get_option($option_name, $default_prompts[$level]);
                ?>
                <div class="prompt-section" data-level="<?php echo esc_attr($level); ?>">
                    <div class="prompt-header" style="background: #f1f1f1; padding: 10px; border: 1px solid #ddd; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                        <strong><?php echo esc_html($label); ?></strong>
                        <span class="prompt-toggle dashicons dashicons-arrow-down"></span>
                    </div>
                    <div class="prompt-content" style="display: none; border: 1px solid #ddd; border-top: none; padding: 15px; background: #fff;">
                        <textarea 
                            name="<?php echo esc_attr($option_name); ?>" 
                            id="<?php echo esc_attr($option_name); ?>" 
                            rows="4" 
                            cols="60" 
                            class="large-text code reading-level-prompt"
                            data-level="<?php echo esc_attr($level); ?>"
                        ><?php echo esc_textarea($current_prompt); ?></textarea>
                        <div class="prompt-meta" style="margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="character-count">
                                <span class="char-count">0</span> <?php echo esc_html__('characters', 'ai-explainer'); ?>
                            </div>
                            <button 
                                type="button" 
                                class="btn-base btn-secondary btn-sm reset-prompt-btn" 
                                data-level="<?php echo esc_attr($level); ?>"
                                data-default="<?php echo esc_attr($default_prompts[$level]); ?>"
                            >
                                <?php echo esc_html__('Reset', 'ai-explainer'); ?>
                            </button>
                        </div>
                        <div class="prompt-validation" style="margin-top: 5px;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render model selection dropdown with data attributes
     */
    private function render_model_select() {
        ?>
        <div class="select-wrapper">
            <select name="explainer_api_model" id="explainer_api_model" class="styled-select"
                data-current-value="<?php echo esc_attr(get_option('explainer_api_model', 'gpt-5.1')); ?>"
                data-openai-models='<?php echo json_encode([
                    ["value" => "gpt-5.1", "label" => __("GPT-5.1", "ai-explainer")]
                ]); ?>'
                data-claude-models='<?php echo json_encode([
                    ["value" => "claude-3-haiku-20240307", "label" => __("Claude 3 Haiku (Fast and efficient)", "ai-explainer")],
                    ["value" => "claude-3-sonnet-20240229", "label" => __("Claude 3 Sonnet (Balanced)", "ai-explainer")],
                    ["value" => "claude-3-opus-20240229", "label" => __("Claude 3 Opus (Highest quality)", "ai-explainer")]
                ]); ?>'>
                <!-- Options populated dynamically by JavaScript -->
            </select>
        </div>
        <?php
    }
}