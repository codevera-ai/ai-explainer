<?php
/**
 * Plugin Name: AI Explainer
 * Plugin URI: https://wpaiexplainer.com
 * Description: AI-powered text explanation system. Users select text to receive explanations via tooltips with multiple AI provider support and customisation options.
 * Version: 1.3.30
 * Author: AI Explainer
 * Author URI: https://wpaiexplainer.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-explainer
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check for plugin conflict before loading anything
 *
 * Determine if this is the pro version
 */
$is_pro_version = file_exists(dirname(__FILE__) . '/includes/pro/providers/class-claude-provider.php');

/**
 * Define a constant to mark which version is loaded
 * This helps the other version detect conflicts
 */
if (!defined('EXPLAINER_PLUGIN_VERSION_TYPE')) {
    define('EXPLAINER_PLUGIN_VERSION_TYPE', $is_pro_version ? 'pro' : 'free');
}

/**
 * Conflict detection logic
 * - If this is PRO and FREE is already loaded: Show conflict and stop
 * - If this is FREE and PRO is already loaded: Show conflict and stop
 */
$has_conflict = false;

if ($is_pro_version) {
    // Pro version: check if free version is already loaded
    if (defined('EXPLAINER_PLUGIN_VERSION_TYPE') && EXPLAINER_PLUGIN_VERSION_TYPE === 'free') {
        $has_conflict = true;
    }
} else {
    // Free version: check if pro version is already loaded
    if (defined('EXPLAINER_PLUGIN_VERSION_TYPE') && EXPLAINER_PLUGIN_VERSION_TYPE === 'pro') {
        $has_conflict = true;
    }
}

if ($has_conflict) {
    $current_version = $is_pro_version ? __('Pro', 'ai-explainer') : __('Free', 'ai-explainer');
    $other_version = $is_pro_version ? __('Free', 'ai-explainer') : __('Pro', 'ai-explainer');

    // Add admin notice to inform the user
    add_action('admin_notices', function () use ($current_version, $other_version) {
        ?>
        <div class="notice notice-error">
            <p><strong><?php esc_html_e('AI Explainer Plugin Conflict', 'ai-explainer'); ?></strong></p>
            <p>
                <?php
                printf(
                    /* translators: 1: Current version name, 2: Other version name */
                    esc_html__('The %1$s version cannot be activated because the %2$s version is already active. Please deactivate the %2$s version first.', 'ai-explainer'),
                    '<strong>' . esc_html($current_version) . '</strong>',
                    '<strong>' . esc_html($other_version) . '</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    });

    // Add plugin row notice
    add_action('after_plugin_row_' . plugin_basename(__FILE__), function ($plugin_file, $plugin_data, $status) use ($current_version, $other_version) {
        $colspan = version_compare(get_bloginfo('version'), '5.5', '>=') ? 4 : 3;
        ?>
        <tr class="plugin-update-tr active">
            <td colspan="<?php echo esc_attr($colspan); ?>" class="plugin-update colspanchange">
                <div class="update-message notice inline notice-error notice-alt">
                    <p>
                        <strong><?php esc_html_e('Plugin Conflict:', 'ai-explainer'); ?></strong>
                        <?php
                        printf(
                            /* translators: %s: Other version name */
                            esc_html__('Cannot activate whilst the %s version is active. Please deactivate the other version first.', 'ai-explainer'),
                            '<strong>' . esc_html($other_version) . '</strong>'
                        );
                        ?>
                    </p>
                </div>
            </td>
        </tr>
        <?php
    }, 10, 3);

    // Stop loading this plugin to prevent fatal errors
    return;
}

// Define plugin constants
define('EXPLAINER_PLUGIN_VERSION', '1.3.30');
define('EXPLAINER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EXPLAINER_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('EXPLAINER_PLUGIN_FILE', __FILE__);
define('EXPLAINER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Legacy constants for backwards compatibility with WPAIE scheduler system
if (!defined('WP_AI_EXPLAINER_VERSION')) {
    define('WP_AI_EXPLAINER_VERSION', EXPLAINER_PLUGIN_VERSION);
}
if (!defined('WP_AI_EXPLAINER_PLUGIN_DIR')) {
    define('WP_AI_EXPLAINER_PLUGIN_DIR', EXPLAINER_PLUGIN_PATH);
}
if (!defined('WP_AI_EXPLAINER_PLUGIN_URL')) {
    define('WP_AI_EXPLAINER_PLUGIN_URL', EXPLAINER_PLUGIN_URL);
}
if (!defined('WP_AI_EXPLAINER_PLUGIN_FILE')) {
    define('WP_AI_EXPLAINER_PLUGIN_FILE', EXPLAINER_PLUGIN_FILE);
}

// FREEMIUS SDK - DISABLED FOR FREE VERSION
// Freemius is kept in the codebase but not initialized to remove all licensing UI
// To re-enable: Uncomment the entire block below

/*
// Initialize Freemius SDK (load from Composer vendor directory in plugin folder)
$freemius_sdk_path = dirname(__FILE__) . '/vendor/freemius/wordpress-sdk/start.php';
if (file_exists($freemius_sdk_path) && !function_exists('wpaie_freemius')) {

    // Create a helper function for easy SDK access.
    function wpaie_freemius()
    {
        global $wpaie_freemius;

        if (!isset($wpaie_freemius)) {
            // Include Freemius SDK from Composer vendor directory in plugin folder.
            require_once dirname(__FILE__) . '/vendor/freemius/wordpress-sdk/start.php';

            // Determine if this is the premium version based on pro files existence
            $is_premium = file_exists(dirname(__FILE__) . '/includes/pro/providers/class-claude-provider.php');

            $wpaie_freemius = fs_dynamic_init(array(
                'id' => '20866',
                'slug' => 'ai-explainer',
                'premium_slug' => 'wp-ai-explainer-pro',
                'type' => 'plugin',
                'public_key' => 'pk_bc522cd2f331b288155de81dcef1e',
                'is_premium' => $is_premium,
                'has_addons' => false,
                'has_paid_plans' => true,
                'menu' => false,
            ));
        }

        return $wpaie_freemius;
    }

    // Init Freemius.
    wpaie_freemius();
    // Signal that SDK was initiated.
    do_action('wpaie_freemius_loaded');

    // Remove Freemius admin pages after initialization
    wpaie_freemius()->add_action('admin_init', 'wpaie_remove_freemius_pages');

    // Hook uninstall cleanup to Freemius
    wpaie_freemius()->add_action('after_uninstall', 'wpaiex_uninstall_cleanup');

    // Filter the redirect URL after license activation
    wpaie_freemius()->add_filter('after_connect_url', 'wpaiex_after_license_activation_url');

    /**
     * Redirect to plugin home page after successful license activation
     *
     * @param string $url The default redirect URL.
     * @return string The modified redirect URL.
     */
    function wpaiex_after_license_activation_url($url)
    {
        // Redirect to plugin home page instead of plugins.php
        return admin_url('admin.php?page=wp-ai-explainer-admin');
    }

    /**
     * Remove Freemius admin pages that we don't want to show
     */
    function wpaie_remove_freemius_pages()
    {
        if (function_exists('wpaie_freemius')) {
            // Remove the sticky upgrade notice from Freemius
            wpaie_freemius()->remove_sticky_admin_notice('plan_upgraded');

            // Suppress future admin notices
            wpaie_freemius()->add_filter('show_admin_notice', function ($show, $notice) {
                if (isset($notice['id']) && $notice['id'] === 'plan_upgraded') {
                    return false;
                }
                return $show;
            }, 10, 2);

            // Hide Freemius submenus except pricing page (needed for checkout)
            $unique_affix = wpaie_freemius()->get_unique_affix();
            wpaie_freemius()->add_filter("{$unique_affix}_is_submenu_visible", function ($is_visible, $menu_id) {
                // Allow pricing page to be accessible (for purchase links)
                if ($menu_id === 'pricing') {
                    return true;
                }
                // Hide all other Freemius submenus
                return false;
            }, PHP_INT_MAX, 2);

            // Prevent Freemius from showing activation page on our custom account page
            wpaie_freemius()->add_filter("{$unique_affix}_show_delegation_option", '__return_false', PHP_INT_MAX);

            // Allow pricing page to be added, but hide other Freemius admin pages
            wpaie_freemius()->add_filter("{$unique_affix}_add_submenu_items", function ($items) {
                // Filter to only allow pricing page
                if (is_array($items)) {
                    return array_filter($items, function ($item) {
                        return (isset($item['menu_slug']) && $item['menu_slug'] === 'pricing');
                    });
                }
                return array();
            }, PHP_INT_MAX);

            // Prevent Freemius from overriding our custom account page
            wpaie_freemius()->add_filter('fs_show_submenu_item_account', '__return_false', PHP_INT_MAX);
        }
    }

    /**
     * Prevent Freemius from hijacking our account page - multiple interception points
     */
/*
    // Hook 1: Very early admin_init to prevent Freemius routing
    add_action('admin_init', function () {
        if (function_exists('wpaie_freemius') && isset($_GET['page']) && $_GET['page'] === 'wp-ai-explainer-admin-account') {
            // Remove Freemius's admin page actions to prevent it from rendering
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');

            // Prevent Freemius from checking if this is its page
            $fs = wpaie_freemius();
            $unique_affix = $fs->get_unique_affix();

            // Override Freemius's is_activation_mode check
            $fs->add_filter("{$unique_affix}_is_activation_mode", '__return_false', PHP_INT_MAX);

            // Prevent Freemius from showing its account page
            $fs->add_filter("{$unique_affix}_show_account_page", '__return_false', PHP_INT_MAX);
        }
    }, 1);

    // Hook 2: Even earlier - on plugins_loaded to prevent any Freemius checks
    add_action('plugins_loaded', function () {
        if (function_exists('wpaie_freemius') && isset($_GET['page']) && $_GET['page'] === 'wp-ai-explainer-admin-account') {
            $fs = wpaie_freemius();
            $unique_affix = $fs->get_unique_affix();

            // Tell Freemius this is NOT its account page
            $fs->add_filter('fs_is_plugin_page', '__return_false', PHP_INT_MAX);

            // Prevent Freemius from forcing activation
            $fs->add_filter('fs_redirect_on_activation', '__return_false', PHP_INT_MAX);
            $fs->add_filter("{$unique_affix}_connect_url", '__return_empty_string', PHP_INT_MAX);
        }
    }, 999);

    // Hook 3: Admin menu - completely remove any Freemius admin menu additions
    add_action('admin_menu', function () {
        // Remove any Freemius pages that might have been added
        remove_submenu_page('wp-ai-explainer-admin', 'wp-ai-explainer-admin-account-freemius');
        // Note: Pricing page NOT removed so checkout_url() works - already hidden by filters above (lines 188, 191)
        remove_submenu_page('wp-ai-explainer-admin', 'wp-ai-explainer-admin-contact');
        remove_submenu_page('wp-ai-explainer-admin', 'wp-ai-explainer-admin-affiliation');
    }, 999);

    // Hook 4: Tell Freemius our custom page is not their account page
    add_filter('fs_is_custom_dashboard_page_' . wpaie_freemius()->get_id(), function ($is_custom, $page_slug) {
        // Our custom account page should bypass Freemius routing
        if ($page_slug === 'wp-ai-explainer-admin-account') {
            return true; // Tell Freemius this is our custom dashboard page
        }
        return $is_custom;
    }, 10, 2);
}
*/

/**
 * Main plugin class
 */
class ExplainerPlugin
{

    /**
     * Plugin instance
     * @var ExplainerPlugin
     */
    private static $instance = null;

    /**
     * Plugin loader
     * @var ExplainerPlugin_Loader
     */
    private $loader;

    /**
     * Get plugin instance
     * @return ExplainerPlugin
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->set_locale();
        $this->check_database_upgrades();
        $this->init_job_queue();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies()
    {
        // Core loader class
        require_once EXPLAINER_PLUGIN_PATH . 'includes/class-loader.php';

        // Helper functions (free)
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/helpers.php';

        // Brand colors configuration
        require_once EXPLAINER_PLUGIN_PATH . 'includes/brand-colors.php';

        // Centralized configuration management (eliminates settings duplication)
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-config.php';

        // Comprehensive logging system
        require_once EXPLAINER_PLUGIN_PATH . 'includes/class-logger.php';

        // Plugin-specific debug logger
        require_once EXPLAINER_PLUGIN_PATH . 'includes/class-debug-logger.php';

        // Unified debug management system

        // Initialize logger and log plugin start
        if (get_option('explainer_debug_mode', false)) {
            $logger = ExplainerPlugin_Logger::get_instance();
            $logger->info('Plugin dependencies loaded successfully', array(), 'Main');
        }

        // Frontend meta tag functionality (Yoast-style)
        require_once EXPLAINER_PLUGIN_PATH . 'includes/class-frontend.php';

        // Frontend setup mode (free)
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/frontend/class-setup-mode.php';

        // Admin functionality (free)
        if (is_admin()) {
            require_once EXPLAINER_PLUGIN_PATH . 'includes/free/admin/class-admin.php';
        }

        // AI Provider system (free base + conditional pro)
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/providers/interface-ai-provider.php';
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/providers/abstract-ai-provider.php';
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/providers/class-ai-provider-registry.php'; // Load registry first
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/providers/class-openai-provider.php'; // Free provider

        // Pro providers (conditional - check for files AND valid license)
        if (file_exists(EXPLAINER_PLUGIN_PATH . 'includes/pro/providers/class-claude-provider.php')) {
            // Check if license guard exists (pro version only)
            $license_guard_path = EXPLAINER_PLUGIN_PATH . 'includes/pro/class-license-guard.php';

            if (file_exists($license_guard_path)) {
                // Pro version with license guard - enforce licensing
                require_once $license_guard_path;

                if (ExplainerPlugin_License_Guard::can_use_pro_features()) {
                    // Valid license/trial - load pro providers
                    require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/providers/class-claude-provider.php';
                    require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/providers/class-openrouter-provider.php';
                    require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/providers/class-gemini-provider.php';
                }
            } else {
                // No license guard (backwards compatibility) - load pro providers
                require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/providers/class-claude-provider.php';
                require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/providers/class-openrouter-provider.php';
                require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/providers/class-gemini-provider.php';
            }
        }

        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/providers/class-provider-factory.php';

        // DRY refactoring - centralized configuration and HTTP client (free)
        require_once EXPLAINER_PLUGIN_PATH . 'includes/class-provider-config.php';
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-http-client.php';

        // Strategy pattern for cost calculations
        require_once EXPLAINER_PLUGIN_PATH . 'includes/interfaces/interface-cost-strategy.php';
        require_once EXPLAINER_PLUGIN_PATH . 'includes/class-cost-calculator.php';

        // DRY validation and testing framework
        require_once EXPLAINER_PLUGIN_PATH . 'includes/class-dry-validator.php';

        // Encryption service for secure API key handling (free/security)
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/security/class-encryption-service.php';
        require_once EXPLAINER_PLUGIN_PATH . 'includes/class-encryption-migration.php';

        // API proxy for secure AI integration (free)
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-api-proxy.php';

        // Selection tracking system
        require_once EXPLAINER_PLUGIN_PATH . 'includes/class-selection-tracker.php';

        // Auto-scan handler for new posts/pages
        require_once EXPLAINER_PLUGIN_PATH . 'includes/class-auto-scan-handler.php';

        // Real-time webhook system
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-event-payload.php';
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug('Event payload class loaded', array(
                'class_exists' => class_exists('ExplainerPlugin_Event_Payload') ? 'YES' : 'NO'
            ), 'plugin_init');
        }

        // Event bus system (Story 1.2)
        // Simple event bus (replaces complex topic routing system)
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-simple-event-bus.php';

        // WPAIE Scheduler integration (DISABLED - using simplified job queue)
        // Scheduler system has been consolidated into the main job queue for simplicity
        // if (file_exists(EXPLAINER_PLUGIN_PATH . 'includes/wpaie-scheduler/wpaie-scheduler.php')) {
        //     require_once EXPLAINER_PLUGIN_PATH . 'includes/wpaie-scheduler/wpaie-scheduler.php';
        //     require_once EXPLAINER_PLUGIN_PATH . 'includes/wpaie-scheduler/class-wp-ai-explainer-wpaie-loader.php';
        //     require_once EXPLAINER_PLUGIN_PATH . 'includes/wpaie-scheduler/class-wp-ai-explainer-wpaie-migration.php';
        // }

        // Security system (Story 2.3)
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-endpoint-security.php';
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-rate-limiter.php';

        // Database integration system (depends on webhook system)
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-db-operation-wrapper.php';
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-database-integration-init.php';

        // Initialize event bus system early to ensure webhook subscriptions are registered
        if (ExplainerPlugin_Config::is_feature_enabled('event_bus')) {
            add_action('plugins_loaded', array($this, 'init_event_bus_system'), 5); // High priority to run early
        }

        // Initialize WPAIE Scheduler system (DISABLED - using simplified job queue)
        // add_action('plugins_loaded', array($this, 'init_wpaie_scheduler_system'));

        // Initialize security system
        add_action('init', array($this, 'init_security_system'));

        // Initialize encryption migration
        add_action('init', array($this, 'init_encryption_migration'), 5);

        // Initialize cron management system
        add_action('plugins_loaded', array($this, 'init_cron_system'));

        // Pro services (conditional - check for files AND valid license)
        if (is_admin() && file_exists(EXPLAINER_PLUGIN_PATH . 'includes/pro/services/class-blog-creator.php')) {
            // Check if license guard exists (pro version only)
            $license_guard_path = EXPLAINER_PLUGIN_PATH . 'includes/pro/class-license-guard.php';

            if (file_exists($license_guard_path)) {
                // Pro version with license guard - enforce licensing
                require_once $license_guard_path;

                if (ExplainerPlugin_License_Guard::can_use_pro_features()) {
                    // Valid license/trial - load pro services
                    require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/services/class-content-generator.php';
                    require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/services/class-blog-creator.php';
                    require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/services/class-term-extraction-service.php';

                    // Database integration admin and testing components
                    require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-database-integration-test-suite.php';
                    require_once EXPLAINER_PLUGIN_PATH . 'includes/admin/class-database-integration-admin-test.php';

                    explainer_log_info("=== MAIN PLUGIN: Simplified architecture - direct execution via Job Queue Manager ===", array(), 'Main');

                    // Initialize database integration admin test interface
                    new ExplainerPlugin_Database_Integration_Admin_Test();
                }
            } else {
                // No license guard (backwards compatibility) - load pro services
                require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/services/class-content-generator.php';
                require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/services/class-blog-creator.php';
                require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/services/class-term-extraction-service.php';

                // Database integration admin and testing components
                require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-database-integration-test-suite.php';
                require_once EXPLAINER_PLUGIN_PATH . 'includes/admin/class-database-integration-admin-test.php';

                explainer_log_info("=== MAIN PLUGIN: Simplified architecture - direct execution via Job Queue Manager ===", array(), 'Main');

                // Initialize database integration admin test interface
                new ExplainerPlugin_Database_Integration_Admin_Test();
            }
        }

        // Theme compatibility system
        require_once EXPLAINER_PLUGIN_PATH . 'includes/class-theme-compatibility.php';

        // Security enhancements
        require_once EXPLAINER_PLUGIN_PATH . 'includes/free/security/class-security.php';


        // Load localization helper
        require_once EXPLAINER_PLUGIN_PATH . 'includes/class-localization.php';

        // Initialize loader
        $this->loader = new ExplainerPlugin_Loader();
    }

    /**
     * Initialize job queue system
     */
    private function init_job_queue()
    {
        try {
            // Load job queue core files in dependency order
            // 1. Configuration (provides constants and defaults)
            require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-job-queue-config.php';

            // 2. Database setup (consolidated table creation)
            require_once EXPLAINER_PLUGIN_PATH . 'includes/class-database-setup.php';

            // 3. Abstract base class (foundation for widgets)
            require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/abstract-job-queue-widget.php';

            // 4. Manager class (requires base class and installer)
            require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-job-queue-manager.php';

            // Get job queue manager singleton instance
            $manager = ExplainerPlugin_Job_Queue_Manager::get_instance();

            // Defer widget registration until WordPress is fully loaded
            add_action('init', function () use ($manager) {
                $this->register_job_widgets($manager);
            });

            // Load admin interface if in admin context
            if (is_admin()) {
                require_once EXPLAINER_PLUGIN_PATH . 'includes/free/core/class-job-queue-admin.php';
                add_action('init', function () {
                    new ExplainerPlugin_Job_Queue_Admin();
                });
            }

            // Log successful initialization in debug mode
            if (get_option('explainer_debug_mode', false)) {
                $logger = ExplainerPlugin_Logger::get_instance();
                $logger->info('Job queue system initialized successfully', array(), 'JobQueue');
            }

        } catch (Exception $e) {
            // Log error but don't break plugin initialization
            ExplainerPlugin_Debug_Logger::error('Job queue initialization error: ' . $e->getMessage(), 'JobQueue');

            // Display admin notice if in admin context
            if (is_admin()) {
                add_action('admin_notices', function () use ($e) {
                    $message = __('WP AI Explainer: Job queue system could not be initialized. ', 'ai-explainer') . $e->getMessage();
                    ?>
                    <script>
                        jQuery(document).ready(function ($) {
                            function showJobQueueError() {
                                if (window.ExplainerPlugin && window.ExplainerPlugin.Notifications) {
                                    window.ExplainerPlugin.Notifications.error('<?php echo esc_js($message); ?>', {
                                        title: '<?php echo esc_js(__('Job Queue Error', 'ai-explainer')); ?>',
                                        duration: 0
                                    });
                                } else {
                                    setTimeout(showJobQueueError, 100);
                                }
                            }
                            showJobQueueError();
                        });
                    </script>
                    <?php
                });
            }
        }
    }

    /**
     * Register job widgets with the queue manager
     * 
     * @param ExplainerPlugin_Job_Queue_Manager $manager Job queue manager instance
     */
    private function register_job_widgets($manager)
    {
        try {
            // Pro widgets (conditional - check for files AND valid license)
            if (file_exists(EXPLAINER_PLUGIN_PATH . 'includes/pro/widgets/class-post-analysis-widget.php')) {
                // Check if license guard exists (pro version only)
                $license_guard_path = EXPLAINER_PLUGIN_PATH . 'includes/pro/class-license-guard.php';

                if (file_exists($license_guard_path)) {
                    // Pro version with license guard - enforce licensing
                    require_once $license_guard_path;

                    if (ExplainerPlugin_License_Guard::can_use_pro_features()) {
                        // Valid license/trial - register pro widgets
                        // Register Post Analysis Widget
                        require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/widgets/class-post-analysis-widget.php';
                        $post_analysis_widget = new \WPAIExplainer\JobQueue\ExplainerPlugin_Post_Analysis_Widget();
                        $manager->register_widget($post_analysis_widget);

                        // Register Blog Creation Widget
                        require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/widgets/class-blog-creation-widget.php';
                        $blog_creation_widget = new \WPAIExplainer\JobQueue\ExplainerPlugin_Blog_Creation_Widget();
                        $manager->register_widget($blog_creation_widget);

                        // Register Post Scan Widget
                        ExplainerPlugin_Debug_Logger::info('About to register Post Scan Widget...', 'Widgets');
                        require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/widgets/class-post-scan-widget.php';
                        ExplainerPlugin_Debug_Logger::info('Post Scan Widget class file loaded', 'Widgets');
                        $post_scan_widget = new \WPAIExplainer\JobQueue\ExplainerPlugin_Post_Scan_Widget();
                        ExplainerPlugin_Debug_Logger::info('Post Scan Widget instance created', 'Widgets');
                        $registration_result = $manager->register_widget($post_scan_widget);
                        if (!$registration_result) {
                            ExplainerPlugin_Debug_Logger::error('Failed to register Post Scan Widget - registration returned false', 'Widgets');
                        } else {
                            ExplainerPlugin_Debug_Logger::info('Successfully registered Post Scan Widget for job type: ai_term_scan', 'Widgets');
                        }
                    }
                } else {
                    // No license guard (backwards compatibility) - register pro widgets
                    // Register Post Analysis Widget
                    require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/widgets/class-post-analysis-widget.php';
                    $post_analysis_widget = new \WPAIExplainer\JobQueue\ExplainerPlugin_Post_Analysis_Widget();
                    $manager->register_widget($post_analysis_widget);

                    // Register Blog Creation Widget
                    require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/widgets/class-blog-creation-widget.php';
                    $blog_creation_widget = new \WPAIExplainer\JobQueue\ExplainerPlugin_Blog_Creation_Widget();
                    $manager->register_widget($blog_creation_widget);

                    // Register Post Scan Widget
                    ExplainerPlugin_Debug_Logger::info('About to register Post Scan Widget...', 'Widgets');
                    require_once EXPLAINER_PLUGIN_PATH . 'includes/pro/widgets/class-post-scan-widget.php';
                    ExplainerPlugin_Debug_Logger::info('Post Scan Widget class file loaded', 'Widgets');
                    $post_scan_widget = new \WPAIExplainer\JobQueue\ExplainerPlugin_Post_Scan_Widget();
                    ExplainerPlugin_Debug_Logger::info('Post Scan Widget instance created', 'Widgets');
                    $registration_result = $manager->register_widget($post_scan_widget);
                    if (!$registration_result) {
                        ExplainerPlugin_Debug_Logger::error('Failed to register Post Scan Widget - registration returned false', 'Widgets');
                    } else {
                        ExplainerPlugin_Debug_Logger::info('Successfully registered Post Scan Widget for job type: ai_term_scan', 'Widgets');
                    }
                }
            }

            // Future widgets can be added here:
            // Example:
            // require_once EXPLAINER_PLUGIN_PATH . 'includes/widgets/class-term-extraction-widget.php';
            // $term_widget = new ExplainerPlugin_Term_Extraction_Widget();
            // $manager->register_widget($term_widget);

            // Log widget registration in debug mode
            if (get_option('explainer_debug_mode', false)) {
                $logger = ExplainerPlugin_Logger::get_instance();
                $logger->info('Job widgets registered successfully', array(
                    'widgets' => array('post_analysis', 'blog_creation')
                ), 'JobQueue');
            }

        } catch (Exception $e) {
            // Log widget registration error
            ExplainerPlugin_Debug_Logger::error('Widget registration error: ' . $e->getMessage(), 'Widgets');
            ExplainerPlugin_Debug_Logger::error('Widget registration error file: ' . $e->getFile() . ':' . $e->getLine(), 'Widgets');
            ExplainerPlugin_Debug_Logger::error('Widget registration error trace: ' . $e->getTraceAsString(), 'Widgets');

            // Continue execution - widgets are optional
            if (get_option('explainer_debug_mode', false)) {
                $logger = ExplainerPlugin_Logger::get_instance();
                $logger->error('Widget registration failed', array(
                    'error' => $e->getMessage()
                ), 'JobQueue');
            }
        }
    }

    /**
     * Set plugin locale for internationalization
     */
    private function set_locale()
    {
        $this->loader->add_action('plugins_loaded', $this, 'setup_locale_override');
    }

    /**
     * Check if database needs upgrading
     */
    private function check_database_upgrades()
    {
        // Database setup is now handled by centralized database setup class
        // during plugin activation - no manual upgrade needed here
    }


    /**
     * Setup locale override for custom language preferences
     */
    public function setup_locale_override()
    {
        // Check for custom language setting, fallback to WordPress locale
        $selected_language = get_option('explainer_language', get_locale());

        if (!empty($selected_language) && $selected_language !== get_locale()) {
            // Override locale for this plugin only if different from WordPress default
            add_filter('plugin_locale', array($this, 'override_plugin_locale'), 10, 2);
        }
    }

    /**
     * Override plugin locale for this plugin only
     */
    public function override_plugin_locale($locale, $domain)
    {
        if ($domain === 'ai-explainer') {
            $selected_language = get_option('explainer_language', get_locale());
            if (!empty($selected_language)) {
                return $selected_language;
            }
        }
        return $locale;
    }

    /**
     * Define admin hooks
     */
    private function define_admin_hooks()
    {
        if (is_admin() || wp_doing_ajax()) {
            $plugin_admin = ExplainerPlugin_Admin::get_instance();
            // Admin menu and settings
            $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');

            // Account submenu - DISABLED FOR FREE VERSION
            // No need for account/license pages when all features are free
            // if (file_exists(EXPLAINER_PLUGIN_PATH . 'includes/pro/providers/class-claude-provider.php')) {
            //     $this->loader->add_action('admin_menu', $plugin_admin, 'add_account_submenu', 99);
            //     $this->loader->add_action('admin_menu', $plugin_admin, 'add_purchase_license_submenu', 100);
            // }

            $this->loader->add_action('admin_init', $plugin_admin, 'settings_init');
            $this->loader->add_action('admin_init', $plugin_admin, 'process_freemius_actions', 5);

            // Meta box registration
            $this->loader->add_action('add_meta_boxes', $plugin_admin, 'add_meta_boxes');
            $this->loader->add_action('save_post', $plugin_admin, 'save_meta_box_data');

            // Post cleanup
            $this->loader->add_action('before_delete_post', $plugin_admin, 'cleanup_post_metadata');

            // Admin notices
            $this->loader->add_action('admin_notices', $plugin_admin, 'display_usage_exceeded_notice');
            $this->loader->add_action('admin_notices', $plugin_admin, 'display_api_key_notice');

            // AJAX handlers
            $this->loader->add_action('wp_ajax_explainer_reenable_plugin', $plugin_admin, 'handle_reenable_plugin');
            $this->loader->add_action('wp_ajax_explainer_dismiss_usage_notice', $plugin_admin, 'handle_dismiss_usage_notice');
            $this->loader->add_action('wp_ajax_explainer_dismiss_api_key_notice', $plugin_admin, 'handle_dismiss_api_key_notice');
            $this->loader->add_action('wp_ajax_explainer_dismiss_api_key_reset_notice', $plugin_admin, 'handle_dismiss_api_key_reset_notice');

            // Meta box AJAX handlers
            $this->loader->add_action('wp_ajax_explainer_get_meta_box_status', $plugin_admin, 'handle_get_meta_box_status');
            $this->loader->add_action('wp_ajax_explainer_toggle_post_queue', $plugin_admin, 'handle_toggle_post_queue');
            $this->loader->add_action('wp_ajax_explainer_refresh_post_status', $plugin_admin, 'handle_refresh_post_status');

            // AI Terms Modal AJAX handlers
            $this->loader->add_action('wp_ajax_explainer_load_ai_terms', $plugin_admin, 'handle_load_ai_terms');
            // NOTE: explainer_update_term_status handler is registered in class-admin.php to avoid conflicts
            $this->loader->add_action('wp_ajax_explainer_delete_term', $plugin_admin, 'handle_delete_term');
            $this->loader->add_action('wp_ajax_explainer_bulk_action_terms', $plugin_admin, 'handle_bulk_action_terms');

            // Admin scripts and styles
            $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
            $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        }
    }

    /**
     * Define public hooks
     */
    private function define_public_hooks()
    {
        // Initialize classes (needed for both frontend and AJAX)
        $api_proxy = new ExplainerPlugin_API_Proxy();

        if (!is_admin()) {
            // Initialize frontend meta tag handler (Yoast-style)
            $frontend = new WP_AI_Explainer_Frontend();

            // Initialize theme compatibility
            $theme_compatibility = new ExplainerPlugin_Theme_Compatibility();

            // Initialize setup mode (auto-detect content areas)
            $setup_mode = new ExplainerPlugin_Setup_Mode();

            // Initialize security
            $security = new ExplainerPlugin_Security();

            // Frontend scripts and styles
            $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_styles');
            $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_scripts');

            // Include tooltip template in footer for JavaScript cloning
            $this->loader->add_action('wp_footer', $this, 'include_tooltip_template');

            // Register SEO title filter
            add_filter('document_title_parts', array($this, 'filter_document_title'), 10, 1);
            add_action('wp_head', array($this, 'add_resource_hints'), 1);
        }

        // Ajax handlers - register for both admin and frontend
        $this->loader->add_action('init', $this, 'register_ajax_handlers');

        // Initialize auto-scan handler for new posts/pages (needs to work in both admin and frontend contexts)
        ExplainerPlugin_Auto_Scan_Handler::init();
    }


    /**
     * Register AJAX handlers
     */
    public function register_ajax_handlers()
    {
        $this->debug_log('Registering AJAX handlers', array(
            'timestamp' => current_time('mysql'),
            'is_admin' => is_admin(),
            'doing_ajax' => defined('DOING_AJAX') && DOING_AJAX
        ));

        // Get API proxy instance
        $api_proxy = new ExplainerPlugin_API_Proxy();

        // Register AJAX handlers for both logged-in and non-logged-in users
        add_action('wp_ajax_explainer_get_explanation', array($api_proxy, 'get_explanation'));
        add_action('wp_ajax_nopriv_explainer_get_explanation', array($api_proxy, 'get_explanation'));

        // Register AJAX handler for debug logging from JavaScript
        add_action('wp_ajax_explainer_debug_log', array($api_proxy, 'handle_debug_log'));
        add_action('wp_ajax_nopriv_explainer_debug_log', array($api_proxy, 'handle_debug_log'));

        // Register AJAX handler for localised strings
        add_action('wp_ajax_explainer_get_localized_strings', array($this, 'get_localized_strings'));
        add_action('wp_ajax_nopriv_explainer_get_localized_strings', array($this, 'get_localized_strings'));

        $this->debug_log('AJAX handlers registered successfully', array(
            'main_explanation_handler' => 'explainer_get_explanation',
            'debug_log_handler' => 'explainer_debug_log',
            'localised_strings_handler' => 'explainer_get_localized_strings',
            'supports_non_logged_in' => true
        ));
    }

    /**
     * Get localised strings for frontend
     */
    public function get_localized_strings()
    {
        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_nonce')) {
            wp_send_json_error(array('message' => __('Invalid nonce', 'ai-explainer')));
        }

        // Get selected language, fallback to WordPress locale
        $selected_language = get_option('explainer_language', get_locale());

        // Define localised strings
        $strings = array(
            'en_US' => array(
                'explanation' => 'Explanation',
                'loading' => 'Loading...',
                'error' => 'Error',
                'disclaimer' => 'AI-generated content may not always be accurate',
                'powered_by' => 'Powered by',
                'failed_to_get_explanation' => 'Failed to get explanation',
                'connection_error' => 'Connection error. Please try again.',
                'loading_explanation' => 'Loading explanation...',
                'selection_too_short' => 'Selection too short (minimum %d characters)',
                'selection_too_long' => 'Selection too long (maximum %d characters)',
                'selection_word_count' => 'Selection must be between %d and %d words',
                'ai_explainer_enabled' => 'AI Explainer enabled. Select text to get explanations.',
                'ai_explainer_disabled' => 'AI Explainer disabled.',
                'blocked_word_found' => 'Your selection contains blocked content'
            ),
            'en_GB' => array(
                'explanation' => 'Explanation',
                'loading' => 'Loading...',
                'error' => 'Error',
                'disclaimer' => 'AI-generated content may not always be accurate',
                'powered_by' => 'Powered by',
                'failed_to_get_explanation' => 'Failed to get explanation',
                'connection_error' => 'Connection error. Please try again.',
                'loading_explanation' => 'Loading explanation...',
                'selection_too_short' => 'Selection too short (minimum %d characters)',
                'selection_too_long' => 'Selection too long (maximum %d characters)',
                'selection_word_count' => 'Selection must be between %d and %d words',
                'ai_explainer_enabled' => 'AI Explainer enabled. Select text to get explanations.',
                'ai_explainer_disabled' => 'AI Explainer disabled.',
                'blocked_word_found' => 'Your selection contains blocked content'
            ),
            'es_ES' => array(
                'explanation' => 'Explicación',
                'loading' => 'Cargando...',
                'error' => 'Error',
                'disclaimer' => 'El contenido generado por IA puede no ser siempre preciso',
                'powered_by' => 'Desarrollado por',
                'failed_to_get_explanation' => 'Error al obtener la explicación',
                'connection_error' => 'Error de conexión. Por favor, inténtalo de nuevo.',
                'loading_explanation' => 'Cargando explicación...',
                'selection_too_short' => 'Selección demasiado corta (mínimo %d caracteres)',
                'selection_too_long' => 'Selección demasiado larga (máximo %d caracteres)',
                'selection_word_count' => 'La selección debe tener entre %d y %d palabras',
                'ai_explainer_enabled' => 'Explicador IA activado. Selecciona texto para obtener explicaciones.',
                'ai_explainer_disabled' => 'Explicador IA desactivado.',
                'blocked_word_found' => 'Su selección contiene contenido bloqueado'
            ),
            'de_DE' => array(
                'explanation' => 'Erklärung',
                'loading' => 'Wird geladen...',
                'error' => 'Fehler',
                'disclaimer' => 'KI-generierte Inhalte sind möglicherweise nicht immer korrekt',
                'powered_by' => 'Unterstützt von',
                'failed_to_get_explanation' => 'Erklärung konnte nicht abgerufen werden',
                'connection_error' => 'Verbindungsfehler. Bitte versuchen Sie es erneut.',
                'loading_explanation' => 'Erklärung wird geladen...',
                'selection_too_short' => 'Auswahl zu kurz (mindestens %d Zeichen)',
                'selection_too_long' => 'Auswahl zu lang (maximal %d Zeichen)',
                'selection_word_count' => 'Auswahl muss zwischen %d und %d Wörtern enthalten',
                'ai_explainer_enabled' => 'KI-Erklärer aktiviert. Text auswählen für Erklärungen.',
                'ai_explainer_disabled' => 'KI-Erklärer deaktiviert.',
                'blocked_word_found' => 'Ihre Auswahl enthält blockierten Inhalt'
            ),
            'fr_FR' => array(
                'explanation' => 'Explication',
                'loading' => 'Chargement...',
                'error' => 'Erreur',
                'disclaimer' => 'Le contenu généré par IA peut ne pas toujours être précis',
                'powered_by' => 'Propulsé par',
                'failed_to_get_explanation' => 'Impossible d\'obtenir l\'explication',
                'connection_error' => 'Erreur de connexion. Veuillez réessayer.',
                'loading_explanation' => 'Chargement de l\'explication...',
                'selection_too_short' => 'Sélection trop courte (minimum %d caractères)',
                'selection_too_long' => 'Sélection trop longue (maximum %d caractères)',
                'selection_word_count' => 'La sélection doit contenir entre %d et %d mots',
                'ai_explainer_enabled' => 'Explicateur IA activé. Sélectionnez du texte pour obtenir des explications.',
                'ai_explainer_disabled' => 'Explicateur IA désactivé.',
                'blocked_word_found' => 'Votre sélection contient du contenu bloqué'
            ),
            'hi_IN' => array(
                'explanation' => 'व्याख्या',
                'loading' => 'लोड हो रहा है...',
                'error' => 'त्रुटि',
                'disclaimer' => 'AI-जनरेटेड सामग्री हमेशा सटीक नहीं हो सकती',
                'powered_by' => 'द्वारा संचालित',
                'failed_to_get_explanation' => 'व्याख्या प्राप्त करने में विफल',
                'connection_error' => 'कनेक्शन त्रुटि। कृपया पुनः प्रयास करें।',
                'loading_explanation' => 'व्याख्या लोड हो रही है...',
                'selection_too_short' => 'चयन बहुत छोटा है (न्यूनतम %d वर्ण)',
                'selection_too_long' => 'चयन बहुत लंबा है (अधिकतम %d वर्ण)',
                'selection_word_count' => 'चयन में %d और %d शब्दों के बीच होना चाहिए',
                'ai_explainer_enabled' => 'AI व्याख्याकार सक्षम। व्याख्या पाने के लिए टेक्स्ट चुनें।',
                'ai_explainer_disabled' => 'AI व्याख्याकार अक्षम।',
                'blocked_word_found' => 'आपके चयन में अवरुद्ध सामग्री है'
            ),
            'zh_CN' => array(
                'explanation' => '解释',
                'loading' => '加载中...',
                'error' => '错误',
                'disclaimer' => 'AI生成的内容可能并不总是准确的',
                'powered_by' => '技术支持',
                'failed_to_get_explanation' => '获取解释失败',
                'connection_error' => '连接错误。请重试。',
                'loading_explanation' => '正在加载解释...',
                'selection_too_short' => '选择太短（最少%d个字符）',
                'selection_too_long' => '选择太长（最多%d个字符）',
                'selection_word_count' => '选择必须在%d到%d个单词之间',
                'ai_explainer_enabled' => 'AI解释器已启用。选择文本以获取解释。',
                'ai_explainer_disabled' => 'AI解释器已禁用。',
                'blocked_word_found' => '您的选择包含被阻止的内容'
            )
        );

        // Get strings for selected language, fallback to English
        $localised_strings = isset($strings[$selected_language]) ? $strings[$selected_language] : $strings['en_GB'];

        wp_send_json_success(array(
            'language' => $selected_language,
            'strings' => $localised_strings
        ));
    }

    /**
     * Enqueue public styles
     */
    public function enqueue_public_styles()
    {
        if (!$this->should_load_assets()) {
            return;
        }

        wp_enqueue_style(
            'explainer-plugin-style',
            EXPLAINER_PLUGIN_URL . 'assets/css/style.css',
            array(),
            EXPLAINER_PLUGIN_VERSION,
            'all'
        );

        // Onboarding CSS - loaded conditionally based on admin setting
        if (get_option('explainer_onboarding_enabled', true)) {
            wp_enqueue_style(
                'explainer-plugin-onboarding',
                EXPLAINER_PLUGIN_URL . 'assets/css/onboarding.css',
                array('explainer-plugin-style'),
                EXPLAINER_PLUGIN_VERSION,
                'all'
            );
        }
    }

    /**
     * Enqueue public scripts
     */
    public function enqueue_public_scripts()
    {
        if (!$this->should_load_assets()) {
            return;
        }

        $this->enqueue_scripts_conditionally();
    }

    /**
     * Conditionally enqueue scripts in footer for better performance
     */
    public function enqueue_scripts_conditionally()
    {
        // Skip enqueuing on admin pages
        if (is_admin()) {
            return;
        }


        // Debug utility (load first as it's used by other scripts)
        wp_enqueue_script(
            'explainer-plugin-debug',
            EXPLAINER_PLUGIN_URL . 'assets/js/debug.js',
            array(),
            EXPLAINER_PLUGIN_VERSION,
            array(
                'in_footer' => true,
                'strategy' => 'defer'
            )
        );

        // Logger (load after debug utility)
        wp_enqueue_script(
            'explainer-plugin-logger',
            EXPLAINER_PLUGIN_URL . 'assets/js/logger.js',
            array('explainer-plugin-debug'),
            EXPLAINER_PLUGIN_VERSION,
            array(
                'in_footer' => true,
                'strategy' => 'defer'
            )
        );

        // Shared utilities (load after logger)
        wp_enqueue_script(
            'explainer-plugin-utils',
            EXPLAINER_PLUGIN_URL . 'assets/js/utils.js',
            array('explainer-plugin-logger'),
            EXPLAINER_PLUGIN_VERSION,
            array(
                'in_footer' => true,
                'strategy' => 'defer'
            )
        );

        // Tooltip script - using full version with footer support
        $tooltip_url = EXPLAINER_PLUGIN_URL . 'assets/js/tooltip.js';

        wp_enqueue_script(
            'explainer-plugin-tooltip',
            $tooltip_url,
            array('explainer-plugin-utils'),
            EXPLAINER_PLUGIN_VERSION,
            array(
                'in_footer' => true,
                'strategy' => 'defer'
            )
        );

        // Main explainer script
        $main_url = EXPLAINER_PLUGIN_URL . 'assets/js/explainer.js';

        wp_enqueue_script(
            'explainer-plugin-main',
            $main_url,
            array('explainer-plugin-utils', 'explainer-plugin-tooltip'),
            EXPLAINER_PLUGIN_VERSION,
            array(
                'in_footer' => true,
                'strategy' => 'defer'
            )
        );

        // Onboarding script - loaded conditionally based on admin setting
        if (get_option('explainer_onboarding_enabled', true)) {
            wp_enqueue_script(
                'explainer-plugin-onboarding',
                EXPLAINER_PLUGIN_URL . 'assets/js/onboarding.js',
                array('explainer-plugin-utils'),
                EXPLAINER_PLUGIN_VERSION,
                array(
                    'in_footer' => true,
                    'strategy' => 'defer'
                )
            );
        }

        // Localise script for Ajax with optimised settings
        // Localise the debug configuration for JavaScript
        wp_localize_script('explainer-plugin-debug', 'ExplainerDebugConfig', ExplainerPlugin_Config::get_js_debug_config());

        // Localise the logger for frontend use
        wp_localize_script('explainer-plugin-logger', 'explainer', array(
            'debugMode' => get_option('explainer_debug_mode', false),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('explainer_nonce')
        ));

        wp_localize_script('explainer-plugin-main', 'explainerAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('explainer_nonce'),
            'post_id' => is_singular() ? get_the_ID() : 0,
            'settings' => array_merge($this->get_optimized_settings(), array(
                'tooltip_url' => EXPLAINER_PLUGIN_URL . 'assets/js/tooltip.js',
                'onboarding_enabled' => get_option('explainer_onboarding_enabled', true)
            )),
            'debug' => get_option('explainer_debug_mode', false)
        ));
    }

    /**
     * Add resource hints for better performance
     */
    public function add_resource_hints()
    {
        if (!$this->should_load_assets()) {
            return;
        }

        // DNS prefetch for AI APIs
        echo '<link rel="dns-prefetch" href="//api.openai.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//api.anthropic.com">' . "\n";

        // Preload critical CSS
        printf(
            '<link rel="preload" href="%s" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n",
            esc_url(EXPLAINER_PLUGIN_URL . 'assets/css/style.css?ver=' . EXPLAINER_PLUGIN_VERSION)
        );
    }

    /**
     * Include tooltip template in footer for JavaScript cloning
     * Eliminates duplication in tooltip creation
     */
    public function include_tooltip_template()
    {
        if (!$this->should_load_assets()) {
            return;
        }

        // Include the reusable tooltip template
        include_once EXPLAINER_PLUGIN_PATH . 'templates/tooltip-template.php';
    }

    /**
     * Get optimised settings for frontend using centralised configuration
     */
    private function get_optimized_settings()
    {
        static $settings = null;

        if ($settings === null) {
            // Use centralized configuration to eliminate duplication
            $settings = ExplainerPlugin_Config::get_js_config();
        }

        return $settings;
    }

    /**
     * Check if current page is likely to need the plugin
     */
    private function is_content_page()
    {
        // Skip on admin, login, and feed pages
        if (is_admin() || $this->is_login_page() || is_feed()) {
            return false;
        }

        // Skip on search and 404 pages (usually less content)
        if (is_search() || is_404()) {
            return false;
        }

        // Load on content pages
        return is_singular() || is_home() || is_archive() || is_category() || is_tag();
    }

    /**
     * Check if plugin assets should be loaded
     */
    private function should_load_assets()
    {
        // Don't load on admin pages
        if (is_admin()) {
            return false;
        }

        // Don't load if plugin is disabled
        if (!get_option('explainer_enabled', true)) {
            return false;
        }

        // Don't load on login/register pages
        if ($this->is_login_page()) {
            return false;
        }

        return true;
    }

    /**
     * Check if current page is login/register page
     */
    private function is_login_page()
    {
        // Check for WordPress login page
        if (function_exists('is_login') && is_login()) {
            return true;
        }

        // Check for common login/register URLs
        $request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
        $login_pages = array(
            'wp-login.php',
            'wp-register.php',
            '/login',
            '/register',
            '/signup',
            '/sign-up'
        );

        foreach ($login_pages as $page) {
            if (strpos($request_uri, $page) !== false) {
                return true;
            }
        }

        // Check if we're in the admin area
        if (is_admin()) {
            return true;
        }

        return false;
    }

    /**
     * Initialize event bus system early to register webhook subscriptions
     */
    public function init_event_bus_system()
    {
        try {
            // Initialize event bus singleton early to ensure webhook subscriptions are registered
            // This is critical for the hook chain to work properly
            $event_bus = WP_AI_Explainer_Event_Bus::get_instance();

            // Log event bus initialization if debug mode is enabled
            if (get_option('explainer_debug_mode', false)) {
                $logger = ExplainerPlugin_Logger::get_instance();
                $logger->info('Event bus system initialized early for webhook subscriptions', array(
                    'event_bus_enabled' => ExplainerPlugin_Config::is_feature_enabled('event_bus', true),
                    'subscriptions_registered' => true
                ), 'EventBus');
            }

        } catch (Exception $e) {
            // Log error but don't break plugin functionality
            ExplainerPlugin_Debug_Logger::error('Event bus initialization error: ' . $e->getMessage(), 'EventBus');
        }
    }

    // Database integration system removed during SSE to WPAIE Scheduler migration

    /**
     * Initialize WPAIE Scheduler system
     */
    public function init_wpaie_scheduler_system()
    {
        // DISABLED: Scheduler system consolidated into simplified job queue
        return;
        try {
            // Check if WPAIE Scheduler library exists
            $wpaie_library_path = EXPLAINER_PLUGIN_PATH . 'includes/wpaie-scheduler/wpaie-scheduler.php';
            if (!file_exists($wpaie_library_path)) {
                throw new Exception(__('WPAIE Scheduler library not found', 'ai-explainer'));
            }

            // Initialize WPAIE Scheduler loader
            if (class_exists('WP_AI_Explainer_WPAIE_Loader')) {
                $wpaie_loader = WP_AI_Explainer_WPAIE_Loader::get_instance();
                // Loader sets up hooks automatically in constructor

                // Run migration if needed
                if (class_exists('WP_AI_Explainer_WPAIE_Migration')) {
                    $migration_needed = get_option('wp_ai_explainer_needs_wpaie_migration', false);
                    if ($migration_needed) {
                        WP_AI_Explainer_WPAIE_Migration::execute_migration();
                        delete_option('wp_ai_explainer_needs_wpaie_migration');
                        set_transient('wp_ai_explainer_migration_completed', true, 300); // 5 minutes
                    }
                }

                // Log Action Scheduler initialization if debug mode is enabled
                if (get_option('explainer_debug_mode', false)) {
                    $logger = ExplainerPlugin_Logger::get_instance();
                    $logger->info('WPAIE Scheduler system initialized', array(
                        'library_loaded' => class_exists('WPAIEScheduler'),
                        'migration_completed' => !get_option('wp_ai_explainer_needs_wpaie_migration', false)
                    ), 'WPAIEScheduler');
                }
            }

        } catch (Exception $e) {
            // Log error but don't break plugin functionality
            ExplainerPlugin_Debug_Logger::error('WPAIE Scheduler initialization error: ' . $e->getMessage(), 'WPAIEScheduler');

            // Display admin notice if in admin context
            if (is_admin()) {
                add_action('admin_notices', function () use ($e) {
                    $message = __('WP AI Explainer: Action Scheduler system could not be initialized. Background processing may not work properly. ', 'ai-explainer') . $e->getMessage();
                    echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>';
                });
            }
        }
    }

    /**
     * Initialize security system
     */
    public function init_security_system()
    {
        try {
            // Initialize endpoint security manager
            $security = new ExplainerPlugin_Endpoint_Security();

            // Initialize rate limiter
            $rate_limiter = new ExplainerPlugin_Rate_Limiter();

            // Log security system initialization if debug mode is enabled
            if (get_option('explainer_debug_mode', false)) {
                $logger = ExplainerPlugin_Logger::get_instance();
                $logger->info('Security system initialized', array(
                    'endpoint_security_enabled' => true,
                    'rate_limiting_enabled' => true,
                    'ip_blocking_enabled' => true,
                    'payload_filtering_enabled' => true
                ), 'Security_System');
            }

        } catch (Exception $e) {
            // Log error but don't break plugin functionality
            ExplainerPlugin_Debug_Logger::error('Security system initialization error: ' . $e->getMessage(), 'Security_System');

            // Display admin notice if in admin context
            if (is_admin()) {
                add_action('admin_notices', function () use ($e) {
                    $message = __('WP AI Explainer: Security system could not be initialized. Real-time endpoints may be vulnerable. ', 'ai-explainer') . $e->getMessage();
                    echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
                });
            }
        }
    }

    /**
     * Initialize encryption migration system
     */
    public function init_encryption_migration()
    {
        try {
            // Run migration if needed
            ExplainerPlugin_Encryption_Migration::maybe_migrate();

        } catch (Exception $e) {
            // Log error but don't break plugin functionality
            ExplainerPlugin_Debug_Logger::error('Encryption migration error: ' . $e->getMessage(), 'Encryption_Migration');

            // Display admin notice if in admin context
            if (is_admin()) {
                add_action('admin_notices', function () use ($e) {
                    $message = __('WP AI Explainer: API key encryption migration failed. Please check your API keys in settings. ', 'ai-explainer') . $e->getMessage();
                    echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
                });
            }
        }
    }

    /**
     * Initialize cron management system
     */
    public function init_cron_system()
    {
        try {
            // Load cron classes
            require_once EXPLAINER_PLUGIN_PATH . 'includes/class-cron-endpoint.php';
            require_once EXPLAINER_PLUGIN_PATH . 'includes/class-cron-manager.php';

            // Initialize cron endpoint for external cron processing
            ExplainerPlugin_Cron_Endpoint::get_instance();

            // Initialize cron manager for WordPress/server cron switching
            ExplainerPlugin_Cron_Manager::get_instance();

            explainer_log_info('Cron system initialized successfully', array(
                'endpoint_enabled' => true,
                'manager_enabled' => true,
                'current_cron_setting' => get_option('explainer_enable_cron', false)
            ), 'CronSystem');

        } catch (Exception $e) {
            // Log error but don't break plugin functionality
            ExplainerPlugin_Debug_Logger::error('Cron system initialization error: ' . $e->getMessage(), 'CronSystem');

            // Display admin notice if in admin context
            if (is_admin()) {
                add_action('admin_notices', function () use ($e) {
                    $message = __('WP AI Explainer: Cron system could not be initialized. Automatic job processing may not work. ', 'ai-explainer') . $e->getMessage();
                    echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
                });
            }
        }
    }

    /**
     * Run the plugin
     */
    public function run()
    {
        $this->debug_log('Starting plugin execution', array(
            'version' => EXPLAINER_PLUGIN_VERSION,
            'is_admin' => is_admin(),
            'current_screen' => is_admin() && function_exists('get_current_screen') ? (get_current_screen() ? get_current_screen()->id : 'admin_early') : 'frontend'
        ));

        // Log plugin initialization in debug mode only
        if (get_option('explainer_debug_mode', false)) {
            $logger = ExplainerPlugin_Logger::get_instance();
            $logger->info('Plugin run() method called', array(
                'is_admin' => is_admin(),
                'timestamp' => current_time('mysql')
            ), 'Main');
        }

        // Hook debug logging to run after WordPress is fully initialized
        add_action('init', array($this, 'debug_plugin_initialization'), 1);

        $this->loader->run();

        $this->debug_log('Plugin execution completed', array(
            'loader_run' => true,
            'wpaie_scheduler_initialized' => true,
            'hooks_registered' => true
        ));

        // Log successful initialization in debug mode only
        if (get_option('explainer_debug_mode', false)) {
            $logger = ExplainerPlugin_Logger::get_instance();
            $logger->info('Loader has been run, hooks registered', array(), 'Main');
        }
    }

    /**
     * Debug logging method using shared utility
     */
    private function debug_log($message, $data = array())
    {
        $logger = ExplainerPlugin_Logger::get_instance();
        return $logger->debug($message, $data, 'Main');
    }

    /**
     * Debug plugin initialization options to identify logged in vs logged out differences
     */
    public function debug_plugin_initialization()
    {
        // Only run debug logging when debug mode is enabled
        if (!get_option('explainer_debug_mode', false)) {
            return;
        }

        // Skip if WordPress user functions aren't loaded yet
        if (!function_exists('is_user_logged_in') || !function_exists('get_current_user_id')) {
            return;
        }

        $logger = ExplainerPlugin_Logger::get_instance();
        $logger->debug('Plugin initialization complete', array(
            'user_id' => get_current_user_id(),
            'is_logged_in' => is_user_logged_in(),
            'is_admin' => is_admin(),
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => EXPLAINER_PLUGIN_VERSION
        ), 'Main');
    }

    /**
     * Filter document title for plugin-created posts
     */
    public function filter_document_title($title_parts)
    {
        // Only modify if setting is enabled
        $setting_enabled = get_option('explainer_output_seo_metadata', true);
        if (!$setting_enabled) {
            return $title_parts;
        }

        // Only modify on single posts
        if (!is_single()) {
            return $title_parts;
        }

        global $post;
        if (!$post) {
            return $title_parts;
        }

        // Check if this post was created by our plugin
        $plugin_created = get_post_meta($post->ID, '_wp_ai_explainer_source_selection', true);
        if (empty($plugin_created)) {
            return $title_parts;
        }

        // Check if an SEO plugin is already handling this
        if ($this->has_active_seo_plugin()) {
            return $title_parts;
        }

        // Get our custom SEO title
        $seo_title = get_post_meta($post->ID, '_wp_ai_explainer_seo_title', true);

        if (!empty($seo_title)) {
            $title_parts['title'] = $seo_title;
        }

        return $title_parts;
    }


    /**
     * Check if an active SEO plugin is detected
     */
    public function has_active_seo_plugin()
    {
        $debug_mode = get_option('explainer_debug_mode', false);

        // Check for common SEO plugins
        $seo_plugins = array(
            'wordpress-seo/wp-seo.php' => 'Yoast SEO',
            'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
            'seo-by-rank-math/rank-math.php' => 'Rank Math',
            'wp-seopress/seopress.php' => 'SEOPress',
            'autodescription/autodescription.php' => 'The SEO Framework'
        );

        if ($debug_mode) {
            ExplainerPlugin_Debug_Logger::debug('SEO Plugin Check: Checking for active SEO plugins', 'SEO');
        }

        foreach ($seo_plugins as $plugin_path => $plugin_name) {
            $is_active = is_plugin_active($plugin_path);
            if ($debug_mode) {
                ExplainerPlugin_Debug_Logger::debug('SEO Plugin Check: ' . $plugin_name . ' (' . $plugin_path . ') = ' . ($is_active ? 'ACTIVE' : 'inactive'), 'SEO');
            }
            if ($is_active) {
                if ($debug_mode) {
                    ExplainerPlugin_Debug_Logger::info('SEO Plugin Check: Found active SEO plugin: ' . $plugin_name, 'SEO');
                }
                return true;
            }
        }

        if ($debug_mode) {
            ExplainerPlugin_Debug_Logger::debug('SEO Plugin Check: No active SEO plugins detected', 'SEO');
        }

        return false;
    }

    /**
     * Get the name of active SEO plugin
     */
    public function get_active_seo_plugin_name()
    {
        // Check for common SEO plugins
        $seo_plugins = array(
            'wordpress-seo/wp-seo.php' => 'Yoast SEO',
            'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
            'seo-by-rank-math/rank-math.php' => 'Rank Math',
            'wp-seopress/seopress.php' => 'SEOPress',
            'autodescription/autodescription.php' => 'The SEO Framework'
        );

        foreach ($seo_plugins as $plugin_path => $plugin_name) {
            if (is_plugin_active($plugin_path)) {
                return $plugin_name;
            }
        }

        return '';
    }

    /**
     * Add custom cron schedules for background processor
     * 
     * @param array $schedules Existing cron schedules
     * @return array Modified schedules
     */
    // Simplified architecture - cron methods removed
    // Manual job execution now uses direct Job Queue Manager execution
}

/**
 * Plugin activation hook
 */
function wpaiex_activate_plugin()
{
    require_once EXPLAINER_PLUGIN_PATH . 'includes/class-activator.php';
    ExplainerPlugin_Activator::activate();
}

/**
 * Plugin deactivation hook
 */
function wpaiex_deactivate_plugin()
{
    require_once EXPLAINER_PLUGIN_PATH . 'includes/class-deactivator.php';
    ExplainerPlugin_Deactivator::deactivate();
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'wpaiex_activate_plugin');
register_deactivation_hook(__FILE__, 'wpaiex_deactivate_plugin');

/**
 * Add settings link to plugin action links
 * 
 * @param array $links Array of plugin action links
 * @return array Modified array of plugin action links
 */
function wpaiex_plugin_action_links($links)
{
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url('admin.php?page=wp-ai-explainer-admin'),
        __('Settings', 'ai-explainer')
    );

    // Add settings link to the beginning of the array
    array_unshift($links, $settings_link);

    return $links;
}

// Register the settings link filter
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wpaiex_plugin_action_links');

/**
 * Uninstall cleanup function for Freemius
 * This is called after Freemius tracks the uninstall event
 */
function wpaiex_uninstall_cleanup()
{
    // Check if user wants to keep data
    if (get_option('explainer_keep_data_on_uninstall', false)) {
        return;
    }

    // Remove all plugin options
    wpaiex_remove_plugin_options();

    // Drop custom database tables
    wpaiex_drop_database_tables();

    // Clear all caches
    wpaiex_clear_all_caches();

    // Clear transients
    wpaiex_clear_transients();

    // Remove custom directories
    wpaiex_remove_directories();

    // Clear scheduled events
    wpaiex_clear_scheduled_events();

    // Remove user meta
    wpaiex_remove_user_meta();

    // Clean up rewrite rules
    flush_rewrite_rules();
}

/**
 * Remove all plugin options
 */
function wpaiex_remove_plugin_options()
{
    $options = array(
        'explainer_enabled',
        'explainer_api_model',
        'explainer_max_selection_length',
        'explainer_min_selection_length',
        'explainer_max_words',
        'explainer_min_words',
        'explainer_cache_enabled',
        'explainer_cache_duration',
        'explainer_rate_limit_enabled',
        'explainer_rate_limit_logged',
        'explainer_rate_limit_anonymous',
        'explainer_included_selectors',
        'explainer_excluded_selectors',
        'explainer_tooltip_bg_color',
        'explainer_tooltip_text_color',
        'explainer_toggle_position',
        'explainer_debug_mode',
        'explainer_custom_prompt',
        'explainer_debug_logs',
        'explainer_plugin_activated',
        'explainer_plugin_deactivated',
        'explainer_db_version',
        'explainer_keep_data_on_uninstall',
        'explainer_encryption_salt',
    );

    foreach ($options as $option) {
        delete_option($option);
    }

    // Remove any options that start with explainer_
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for uninstall cleanup
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'explainer_%'));
}

/**
 * Drop custom database tables
 */
function wpaiex_drop_database_tables()
{
    global $wpdb;

    // Drop new job queue tables
    // Note: Job queue installer removed - tables dropped manually below
    // $plugin_path = dirname(__FILE__);
    // require_once $plugin_path . '/includes/free/core/class-job-queue-installer.php';
    // \WPAIExplainer\JobQueue\ExplainerPlugin_Job_Queue_Installer::drop_tables();

    // Drop old blog queue table (if exists)
    $old_blog_queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dropping custom plugin table on uninstall
    $wpdb->query("DROP TABLE IF EXISTS $old_blog_queue_table");

    // Drop AI explainer selections table
    $selections_table = $wpdb->prefix . 'ai_explainer_selections';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dropping custom plugin table on uninstall
    $wpdb->query("DROP TABLE IF EXISTS $selections_table");

    // Drop blog posts table
    $blog_posts_table = $wpdb->prefix . 'ai_explainer_blog_posts';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dropping custom plugin table on uninstall
    $wpdb->query("DROP TABLE IF EXISTS $blog_posts_table");

    // Drop blog queue table
    $blog_queue_table = $wpdb->prefix . 'ai_explainer_job_queue';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching -- Dropping custom plugin table on uninstall
    $wpdb->query("DROP TABLE IF EXISTS $blog_queue_table");
}

/**
 * Clear all caches
 */
function wpaiex_clear_all_caches()
{
    // Clear WordPress object cache
    wp_cache_flush();

    // Clear plugin-specific cache
    wp_cache_delete_group('explainer_explanations');
    wp_cache_delete_group('explainer_settings');

    // Clear file-based cache
    wpaiex_clear_file_cache();
}

/**
 * Clear file-based cache
 */
function wpaiex_clear_file_cache()
{
    $upload_dir = wp_upload_dir();
    $cache_dir = $upload_dir['basedir'] . '/explainer-plugin/cache';

    if (is_dir($cache_dir)) {
        $files = glob($cache_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                wp_delete_file($file);
            }
        }
    }
}

/**
 * Clear transients
 */
function wpaiex_clear_transients()
{
    global $wpdb;

    // Clear rate limiting transients
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for uninstall cleanup
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_explainer_rate_limit_%'));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for uninstall cleanup
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_explainer_rate_limit_%'));

    // Clear cache transients
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for uninstall cleanup
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_explainer_cache_%'));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for uninstall cleanup
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_explainer_cache_%'));

    // Clear settings transients
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for uninstall cleanup
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_explainer_settings_%'));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for uninstall cleanup
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_explainer_settings_%'));

    // Clear API test transients
    delete_transient('explainer_api_test');
}

/**
 * Remove custom directories
 */
function wpaiex_remove_directories()
{
    $upload_dir = wp_upload_dir();
    $plugin_dir = $upload_dir['basedir'] . '/explainer-plugin';

    if (is_dir($plugin_dir)) {
        wpaiex_remove_directory_recursive($plugin_dir);
    }
}

/**
 * Recursively remove directory
 */
function wpaiex_remove_directory_recursive($dir)
{
    if (!is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), array('.', '..'));

    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            wpaiex_remove_directory_recursive($path);
        } else {
            wp_delete_file($path);
        }
    }

    // Use WordPress filesystem to remove directory
    require_once ABSPATH . 'wp-admin/includes/file.php';
    if (function_exists('WP_Filesystem')) {
        WP_Filesystem();
        global $wp_filesystem;
        if ($wp_filesystem) {
            $wp_filesystem->rmdir($dir, true);
        }
    }
}

/**
 * Clear scheduled events
 */
function wpaiex_clear_scheduled_events()
{
    // Clear cache cleanup event
    wp_clear_scheduled_hook('explainer_cache_cleanup');

    // Clear log cleanup event
    wp_clear_scheduled_hook('explainer_log_cleanup');

    // Clear debug log cleanup event
    wp_clear_scheduled_hook('explainer_debug_cleanup');

    // Clear any other scheduled events
    wp_clear_scheduled_hook('explainer_daily_cleanup');
    wp_clear_scheduled_hook('explainer_weekly_cleanup');
}

/**
 * Remove user meta
 */
function wpaiex_remove_user_meta()
{
    global $wpdb;

    // Remove user preferences
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for uninstall cleanup
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", 'explainer_%'));

    // Remove user debug preferences
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for uninstall cleanup
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", 'explainer_debug_%'));

    // Remove user settings
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for uninstall cleanup
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", 'explainer_preferences_%'));
}

/**
 * Initialize and run the plugin
 */
function wpaiex_run_plugin()
{
    $plugin = ExplainerPlugin::get_instance();
    $plugin->run();
}

/**
 * Add proactive message on inactive Pro plugin when Free is active
 * This shows BEFORE the user tries to activate, guiding them to deactivate Free first
 */
function wpaiex_add_inactive_pro_message()
{
    // Only run on plugins page
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'plugins') {
        return;
    }

    // Check if this is the pro version
    $is_pro = file_exists(dirname(__FILE__) . '/includes/pro/providers/class-claude-provider.php');

    // Only proceed if this is the pro version
    if (!$is_pro) {
        return;
    }

    // Check if this plugin is inactive and free version is active
    $this_plugin = plugin_basename(__FILE__);

    // Check if we're not already active
    if (is_plugin_active($this_plugin)) {
        return;
    }

    // Look for free version - check common locations
    $possible_free_plugins = array(
        'wp-ai-explainer/ai-explainer.php',
        'ai-explainer/ai-explainer.php',
        'ai-explainer-free/ai-explainer.php',
    );

    $free_is_active = false;
    foreach ($possible_free_plugins as $free_plugin) {
        if (is_plugin_active($free_plugin)) {
            $free_is_active = true;
            break;
        }
    }

    // If free version is active, show proactive message
    if ($free_is_active) {
        add_action('after_plugin_row_' . $this_plugin, function ($plugin_file, $plugin_data, $status) {
            $colspan = version_compare(get_bloginfo('version'), '5.5', '>=') ? 4 : 3;
            ?>
            <tr class="plugin-update-tr">
                <td colspan="<?php echo esc_attr($colspan); ?>" class="plugin-update colspanchange">
                    <div class="update-message notice inline notice-warning notice-alt">
                        <p>
                            <strong><?php esc_html_e('Action Required:', 'ai-explainer'); ?></strong>
                            <?php esc_html_e('Please deactivate the Free version before activating the Pro version.', 'ai-explainer'); ?>
                        </p>
                    </div>
                </td>
            </tr>
            <?php
        }, 10, 3);
    }
}

// Add proactive message for inactive pro plugin
add_action('current_screen', 'wpaiex_add_inactive_pro_message');

/**
 * Add proactive message on active Free plugin when Pro is installed (but inactive)
 * This shows on FREE plugin to guide them to uninstall Free before activating Pro
 */
function wpaiex_add_free_upgrade_message()
{
    // Only run on plugins page
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'plugins') {
        return;
    }

    // Check if this is the free version
    $is_free = !file_exists(dirname(__FILE__) . '/includes/pro/providers/class-claude-provider.php');

    // Only proceed if this is the free version
    if (!$is_free) {
        return;
    }

    // Check if this plugin is active
    $this_plugin = plugin_basename(__FILE__);
    if (!is_plugin_active($this_plugin)) {
        return;
    }

    // Look for pro version - check common locations
    $possible_pro_plugins = array(
        'wp-ai-explainer-pro/ai-explainer.php',
        'ai-explainer-pro/ai-explainer.php',
    );

    $pro_exists = false;
    $pro_is_active = false;

    foreach ($possible_pro_plugins as $pro_plugin) {
        // Check if the pro plugin file exists
        $plugin_path = WP_PLUGIN_DIR . '/' . $pro_plugin;
        if (file_exists($plugin_path)) {
            $pro_exists = true;
            // Check if it has the pro marker file
            $pro_marker = dirname($plugin_path) . '/includes/pro/providers/class-claude-provider.php';
            if (file_exists($pro_marker)) {
                // Check if it's active
                if (is_plugin_active($pro_plugin)) {
                    $pro_is_active = true;
                }
                break;
            }
        }
    }

    // If pro version exists but is not active, show upgrade message
    if ($pro_exists && !$pro_is_active) {
        add_action('after_plugin_row_' . $this_plugin, function ($plugin_file, $plugin_data, $status) {
            $colspan = version_compare(get_bloginfo('version'), '5.5', '>=') ? 4 : 3;
            ?>
            <tr class="plugin-update-tr active">
                <td colspan="<?php echo esc_attr($colspan); ?>" class="plugin-update colspanchange">
                    <div class="update-message notice inline notice-warning notice-alt"
                        style="padding: 12px; border-left-width: 4px;">
                        <p style="margin: 0; font-size: 14px;">
                            <strong
                                style="font-size: 15px;"><?php esc_html_e('Pro Version Detected:', 'ai-explainer'); ?></strong>
                            <?php esc_html_e('Please deactivate and uninstall the Free version before activating the Pro version.', 'ai-explainer'); ?>
                        </p>
                    </div>
                </td>
            </tr>
            <?php
        }, 10, 3);
    }
}

// Add proactive message for free plugin when pro is installed
add_action('current_screen', 'wpaiex_add_free_upgrade_message');

/**
 * Redirect users to account page if they don't have a valid license in pro version
 * Forces license activation before using the plugin
 */
function wpaiex_force_license_activation()
{
    // Only run in admin
    if (!is_admin()) {
        return;
    }

    // Skip during AJAX requests
    if (wp_doing_ajax()) {
        return;
    }

    // Check if pro files exist
    if (!file_exists(EXPLAINER_PLUGIN_PATH . 'includes/pro/providers/class-claude-provider.php')) {
        return;
    }

    // Check if license guard exists
    $license_guard_path = EXPLAINER_PLUGIN_PATH . 'includes/pro/class-license-guard.php';
    if (!file_exists($license_guard_path)) {
        return;
    }

    // Load license guard
    require_once $license_guard_path;

    // Check if user can use pro features
    if (ExplainerPlugin_License_Guard::can_use_pro_features()) {
        return; // License is valid, no redirect needed
    }

    // Disable the plugin by setting enabled option to false
    if (get_option('explainer_enabled', '1') === '1') {
        update_option('explainer_enabled', '0');
    }

    // Only redirect if user is actually on a plugin page
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking current page, no action performed
    $is_plugin_page = isset($_GET['page']) && (
        strpos($_GET['page'], 'wp-ai-explainer-') === 0 ||
        strpos($_GET['page'], 'ai-explainer-') === 0
    );

    if (!$is_plugin_page) {
        return; // Not on a plugin page, no redirect needed
    }

    // Don't redirect if already on account page (avoid redirect loop)
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking current page, no action performed
    if (isset($_GET['page']) && $_GET['page'] === 'wp-ai-explainer-admin-account') {
        return;
    }

    // Don't redirect if on pricing page (allow purchasing license)
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking current page, no action performed
    if (isset($_GET['page']) && $_GET['page'] === 'ai-explainer-pricing') {
        return;
    }

    // Don't redirect on plugins page (allow deactivation)
    $screen = get_current_screen();
    if ($screen && $screen->id === 'plugins') {
        return;
    }

    // Redirect to account page
    wp_safe_redirect(admin_url('admin.php?page=wp-ai-explainer-admin-account'));
    exit;
}

// Add redirect to force license activation
add_action('admin_init', 'wpaiex_force_license_activation', 5);

/**
 * Add admin notice when pro files exist but no valid license
 * Informs users they need to activate their license to use pro features
 */
function wpaiex_license_activation_notice()
{
    // Only run in admin
    if (!is_admin()) {
        return;
    }

    // Check if pro files exist
    if (!file_exists(EXPLAINER_PLUGIN_PATH . 'includes/pro/providers/class-claude-provider.php')) {
        return;
    }

    // Check if license guard exists
    $license_guard_path = EXPLAINER_PLUGIN_PATH . 'includes/pro/class-license-guard.php';
    if (!file_exists($license_guard_path)) {
        return;
    }

    // Load license guard
    require_once $license_guard_path;

    // Check if user can use pro features
    if (ExplainerPlugin_License_Guard::can_use_pro_features()) {
        return; // License is valid, no notice needed
    }

    // Don't show notice on account or pricing page
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking current page, no action performed
    if (isset($_GET['page']) && in_array($_GET['page'], array('wp-ai-explainer-admin-account', 'ai-explainer-pricing'))) {
        return;
    }

    // Get license status message
    $message = ExplainerPlugin_License_Guard::get_license_status_message();

    if (!empty($message)) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php echo wp_kses_post($message); ?></p>
        </div>
        <?php
    }
}

// Add license activation notice to admin
add_action('admin_notices', 'wpaiex_license_activation_notice');

// Start the plugin
wpaiex_run_plugin();
