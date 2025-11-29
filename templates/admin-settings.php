<?php
/**
 * New modular admin settings template - dramatically reduced from 2088 lines
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Settings renderer should be passed as a variable from class-admin.php
if (!isset($settings_renderer)) {
    wp_die(esc_html('Settings renderer not available'));
}
?>

<div class="wrap explainer-admin-wrap">
    <h1><?php echo esc_html__('WP AI Explainer Settings', 'ai-explainer'); ?></h1>

    <?php settings_errors(); ?>

    <!-- Premium Upgrade Banner -->
    <?php if (!explainer_can_use_premium_features()): ?>
    <div class="explainer-upgrade-banner">
        <div class="upgrade-banner-content">
            <div class="upgrade-banner-text">
                <div class="upgrade-banner-icon">
                    <span class="dashicons dashicons-star-filled"></span>
                </div>
                <div class="upgrade-banner-message">
                    <h3><?php echo esc_html__('Unlock premium features', 'ai-explainer'); ?></h3>
                    <p><?php echo esc_html__('Get access to advanced AI providers, custom styling, bulk operations, analytics, and priority support.', 'ai-explainer'); ?></p>
                </div>
            </div>
            <div class="upgrade-banner-actions">
                <a href="<?php echo esc_url(wpaie_freemius()->get_upgrade_url()); ?>"
                   class="upgrade-banner-button">
                    <?php echo esc_html__('Upgrade to premium', 'ai-explainer'); ?>
                    <span class="dashicons dashicons-arrow-right-alt"></span>
                </a>
            </div>
        </div>
        <div class="upgrade-banner-features">
            <div class="feature-column">
                <ul>
                    <li><?php echo esc_html__('Claude, GPT-4, Gemini and OpenRouter access', 'ai-explainer'); ?></li>
                    <li><?php echo esc_html__('Custom tooltip styling', 'ai-explainer'); ?></li>
                    <li><?php echo esc_html__('AI blog creation', 'ai-explainer'); ?></li>
                </ul>
            </div>
            <div class="feature-column">
                <ul>
                    <li><?php echo esc_html__('Reading level prompts', 'ai-explainer'); ?></li>
                    <li><?php echo esc_html__('Cost control and performance', 'ai-explainer'); ?></li>
                    <li><?php echo esc_html__('Advanced caching', 'ai-explainer'); ?></li>
                </ul>
            </div>
            <div class="feature-column">
                <ul>
                    <li><?php echo esc_html__('Advanced configuration', 'ai-explainer'); ?></li>
                    <li><?php echo esc_html__('AI post scanning', 'ai-explainer'); ?></li>
                    <li><?php echo esc_html__('Regular updates', 'ai-explainer'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="explainer-admin-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#basic" class="nav-tab nav-tab-active"><?php echo esc_html__('Basic Settings', 'ai-explainer'); ?></a>
            <a href="#ai-provider" class="nav-tab"><?php echo esc_html__('AI Provider', 'ai-explainer'); ?></a>
            <?php if ($settings_renderer->get_section('content')): ?>
            <a href="#content" class="nav-tab"><?php echo esc_html__('Content Rules', 'ai-explainer'); ?></a>
            <?php endif; ?>
            <?php if ($settings_renderer->get_section('performance')): ?>
            <a href="#performance" class="nav-tab"><?php echo esc_html__('Performance', 'ai-explainer'); ?></a>
            <?php endif; ?>
            <?php if ($settings_renderer->get_section('appearance')): ?>
            <a href="#appearance" class="nav-tab"><?php echo esc_html__('Appearance', 'ai-explainer'); ?></a>
            <?php endif; ?>
            <?php if ($settings_renderer->get_section('popular')): ?>
            <a href="#popular" class="nav-tab"><?php echo esc_html__('Explanations', 'ai-explainer'); ?></a>
            <?php endif; ?>
            <?php if ($settings_renderer->get_section('post-scan')): ?>
            <a href="#post-scan" class="nav-tab"><?php echo esc_html__('Post Scan', 'ai-explainer'); ?></a>
            <?php endif; ?>
            <?php if ($settings_renderer->get_section('advanced')): ?>
            <a href="#advanced" class="nav-tab"><?php echo esc_html__('Advanced', 'ai-explainer'); ?></a>
            <?php endif; ?>
            <a href="#support" class="nav-tab"><?php echo esc_html__('Support', 'ai-explainer'); ?></a>
            <?php if (!explainer_can_use_premium_features()): ?>
            <a href="#upgrade" class="nav-tab nav-tab-upgrade"><?php echo esc_html__('Upgrade', 'ai-explainer'); ?></a>
            <?php endif; ?>
        </nav>
    </div>

    <div id="admin-messages"></div>
    
    <form method="post" action="options.php" id="wp-ai-explainer-admin-form">
        <?php settings_fields('explainer_settings'); ?>
        
        <?php
        // Render each section using the modular system
        $settings_renderer->get_section('basic')->render();
        $settings_renderer->get_section('ai-provider')->render();

        // Pro sections (conditional rendering)
        if ($settings_renderer->get_section('content')) {
            $settings_renderer->get_section('content')->render();
        }
        if ($settings_renderer->get_section('performance')) {
            $settings_renderer->get_section('performance')->render();
        }
        if ($settings_renderer->get_section('appearance')) {
            $settings_renderer->get_section('appearance')->render();
        }
        if ($settings_renderer->get_section('popular')) {
            $settings_renderer->get_section('popular')->render();
        }
        if ($settings_renderer->get_section('post-scan')) {
            $settings_renderer->get_section('post-scan')->render();
        }
        if ($settings_renderer->get_section('advanced')) {
            $settings_renderer->get_section('advanced')->render();
        }

        $settings_renderer->get_section('support')->render();
        ?>

        <?php if (!explainer_can_use_premium_features()): ?>
        <!-- Upgrade Tab -->
        <div id="upgrade-tab" class="tab-content" style="display: none;">
            <div class="explainer-upgrade-tab">
                <div class="upgrade-tab-hero">
                    <div class="upgrade-tab-icon">
                        <span class="dashicons dashicons-star-filled"></span>
                    </div>
                    <h2><?php echo esc_html__('Unlock the full power of WP AI Explainer', 'ai-explainer'); ?></h2>
                    <p><?php echo esc_html__('Take your content to the next level with advanced AI providers, customisation options, and powerful features.', 'ai-explainer'); ?></p>
                    <a href="<?php echo esc_url(wpaie_freemius()->get_upgrade_url()); ?>" class="upgrade-tab-cta">
                        <?php echo esc_html__('Upgrade to premium', 'ai-explainer'); ?>
                        <span class="dashicons dashicons-arrow-right-alt"></span>
                    </a>
                </div>

                <div class="upgrade-tab-features">
                    <div class="upgrade-feature-card fade-in-up">
                        <div class="feature-card-icon">
                            <span class="dashicons dashicons-admin-settings"></span>
                        </div>
                        <h3><?php echo esc_html__('Advanced AI providers', 'ai-explainer'); ?></h3>
                        <p><?php echo esc_html__('Access Claude, GPT-4, Gemini, and more powerful AI models for better explanations.', 'ai-explainer'); ?></p>
                    </div>

                    <div class="upgrade-feature-card fade-in-up">
                        <div class="feature-card-icon">
                            <span class="dashicons dashicons-admin-appearance"></span>
                        </div>
                        <h3><?php echo esc_html__('Custom styling', 'ai-explainer'); ?></h3>
                        <p><?php echo esc_html__('Fully customise tooltip colours, positioning, and appearance to match your brand.', 'ai-explainer'); ?></p>
                    </div>

                    <div class="upgrade-feature-card fade-in-up">
                        <div class="feature-card-icon">
                            <span class="dashicons dashicons-welcome-write-blog"></span>
                        </div>
                        <h3><?php echo esc_html__('AI blog creation', 'ai-explainer'); ?></h3>
                        <p><?php echo esc_html__('Generate complete blog posts with images and SEO metadata from your most popular explained terms.', 'ai-explainer'); ?></p>
                    </div>

                    <div class="upgrade-feature-card fade-in-up">
                        <div class="feature-card-icon">
                            <span class="dashicons dashicons-book-alt"></span>
                        </div>
                        <h3><?php echo esc_html__('Reading level prompts', 'ai-explainer'); ?></h3>
                        <p><?php echo esc_html__('Customise explanations for different reading levels, from simple to expert.', 'ai-explainer'); ?></p>
                    </div>

                    <div class="upgrade-feature-card fade-in-up">
                        <div class="feature-card-icon">
                            <span class="dashicons dashicons-dashboard"></span>
                        </div>
                        <h3><?php echo esc_html__('Cost control and performance', 'ai-explainer'); ?></h3>
                        <p><?php echo esc_html__('Manage API costs with spending limits, rate limiting, and performance optimisation controls.', 'ai-explainer'); ?></p>
                    </div>

                    <div class="upgrade-feature-card fade-in-up">
                        <div class="feature-card-icon">
                            <span class="dashicons dashicons-search"></span>
                        </div>
                        <h3><?php echo esc_html__('AI post scanning', 'ai-explainer'); ?></h3>
                        <p><?php echo esc_html__('Automatically scan your posts to find and explain complex terms for your readers.', 'ai-explainer'); ?></p>
                    </div>
                </div>

                <div class="upgrade-pricing-section fade-in-up">
                    <h2><?php echo esc_html__('Choose your plan', 'ai-explainer'); ?></h2>
                    <table class="pricing-table">
                        <thead>
                            <tr>
                                <th class="feature-column"><?php echo esc_html__('Features', 'ai-explainer'); ?></th>
                                <th class="plan-column">
                                    <div class="plan-header">
                                        <h3><?php echo esc_html__('Free', 'ai-explainer'); ?></h3>
                                        <div class="plan-price">
                                            <span class="price">£0</span>
                                            <span class="period"><?php echo esc_html__('forever', 'ai-explainer'); ?></span>
                                        </div>
                                    </div>
                                </th>
                                <th class="plan-column plan-premium">
                                    <div class="plan-badge"><?php echo esc_html__('Popular', 'ai-explainer'); ?></div>
                                    <div class="plan-header">
                                        <h3><?php echo esc_html__('Pro', 'ai-explainer'); ?></h3>
                                        <div class="plan-price">
                                            <?php
                                            // Get Pro pricing from Freemius
                                            $pricing_data = wpaie_freemius()->get_api_plugin_scope()->get('/pricing.json');
                                            $pro_price = '$79.99'; // Fallback
                                            $currency_symbol = '$';

                                            if (!is_wp_error($pricing_data) && isset($pricing_data->plans)) {
                                                foreach ($pricing_data->plans as $plan) {
                                                    // Look for the Pro plan (not free)
                                                    if (isset($plan->name) && strtolower($plan->name) !== 'free' && isset($plan->pricing)) {
                                                        foreach ($plan->pricing as $pricing) {
                                                            if (isset($pricing->annual_price)) {
                                                                // Get currency symbol
                                                                if (isset($pricing->currency)) {
                                                                    $currency_map = array(
                                                                        'usd' => '$',
                                                                        'gbp' => '£',
                                                                        'eur' => '€'
                                                                    );
                                                                    $currency_symbol = isset($currency_map[$pricing->currency]) ? $currency_map[$pricing->currency] : $pricing->currency;
                                                                }
                                                                $pro_price = $currency_symbol . number_format($pricing->annual_price, 0);
                                                                break 2;
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                            ?>
                                            <span class="price"><?php echo esc_html($pro_price); ?></span>
                                            <span class="period"><?php echo esc_html__('per year', 'ai-explainer'); ?></span>
                                        </div>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="feature-name"><?php echo esc_html__('AI explanations', 'ai-explainer'); ?></td>
                                <td class="feature-check"><span class="dashicons dashicons-yes"></span></td>
                                <td class="feature-check"><span class="dashicons dashicons-yes"></span></td>
                            </tr>
                            <tr>
                                <td class="feature-name"><?php echo esc_html__('OpenAI GPT-3.5 access', 'ai-explainer'); ?></td>
                                <td class="feature-check"><span class="dashicons dashicons-yes"></span></td>
                                <td class="feature-check"><span class="dashicons dashicons-yes"></span></td>
                            </tr>
                            <tr>
                                <td class="feature-name"><?php echo esc_html__('Basic caching', 'ai-explainer'); ?></td>
                                <td class="feature-check"><span class="dashicons dashicons-yes"></span></td>
                                <td class="feature-check"><span class="dashicons dashicons-yes"></span></td>
                            </tr>
                            <tr>
                                <td class="feature-name"><?php echo esc_html__('Reading level prompts', 'ai-explainer'); ?></td>
                                <td class="feature-check"><span class="dashicons dashicons-yes"></span></td>
                                <td class="feature-check"><span class="dashicons dashicons-yes"></span></td>
                            </tr>
                            <tr>
                                <td class="feature-name"><?php echo esc_html__('Claude, GPT-4, Gemini<br>and OpenRouter access', 'ai-explainer'); ?></td>
                                <td class="feature-check"><span class="dashicons dashicons-no"></span></td>
                                <td class="feature-check"><span class="dashicons dashicons-yes"></span></td>
                            </tr>
                            <tr>
                                <td class="feature-name"><?php echo esc_html__('AI post scanning', 'ai-explainer'); ?></td>
                                <td class="feature-check"><span class="dashicons dashicons-no"></span></td>
                                <td class="feature-check"><span class="dashicons dashicons-yes"></span></td>
                            </tr>
                            <tr>
                                <td class="feature-name"><?php echo esc_html__('AI blog creation', 'ai-explainer'); ?></td>
                                <td class="feature-check"><span class="dashicons dashicons-no"></span></td>
                                <td class="feature-check"><span class="dashicons dashicons-yes"></span></td>
                            </tr>
                            <tr>
                                <td class="feature-name"><?php echo esc_html__('Custom tooltip styling', 'ai-explainer'); ?></td>
                                <td class="feature-check"><span class="dashicons dashicons-no"></span></td>
                                <td class="feature-check"><span class="dashicons dashicons-yes"></span></td>
                            </tr>
                            <tr>
                                <td class="feature-name"><?php echo esc_html__('Cost control and performance', 'ai-explainer'); ?></td>
                                <td class="feature-check"><span class="dashicons dashicons-no"></span></td>
                                <td class="feature-check"><span class="dashicons dashicons-yes"></span></td>
                            </tr>
                            <tr>
                                <td class="feature-name"><?php echo esc_html__('Caching control', 'ai-explainer'); ?></td>
                                <td class="feature-check"><span class="dashicons dashicons-no"></span></td>
                                <td class="feature-check"><span class="dashicons dashicons-yes"></span></td>
                            </tr>
                            <tr class="cta-row">
                                <td></td>
                                <td></td>
                                <td>
                                    <a href="<?php echo esc_url(wpaie_freemius()->get_upgrade_url()); ?>" class="pricing-cta">
                                        <?php echo esc_html__('Upgrade now', 'ai-explainer'); ?>
                                        <span class="dashicons dashicons-arrow-right-alt"></span>
                                    </a>
                                    <p class="money-back-guarantee">
                                        <span class="dashicons dashicons-shield-alt"></span>
                                        <?php echo esc_html__('7-day money-back guarantee', 'ai-explainer'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="upgrade-tab-footer fade-in-up">
                    <h3><?php echo esc_html__('Ready to upgrade?', 'ai-explainer'); ?></h3>
                    <p><?php echo esc_html__('Join thousands of users already using premium features.', 'ai-explainer'); ?></p>
                    <a href="<?php echo esc_url(wpaie_freemius()->get_upgrade_url()); ?>" class="upgrade-tab-cta">
                        <?php echo esc_html__('Upgrade now', 'ai-explainer'); ?>
                        <span class="dashicons dashicons-arrow-right-alt"></span>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Regular submit button (always enabled) -->
        <?php submit_button('Save Changes', 'primary', 'submit', false, array('form' => 'wp-ai-explainer-admin-form')); ?>
    </form>
</div>