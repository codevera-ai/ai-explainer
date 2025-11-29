<?php
/**
 * Database Integration Initializer
 * 
 * Initializes all database hook integrations for the plugin.
 * 
 * @package WP_AI_Explainer
 * @subpackage Database
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Integration Initializer Class
 * 
 * Coordinates the initialization of all database webhook integrations
 * across the plugin's various components.
 */
class ExplainerPlugin_Database_Integration_Init {
    
    /**
     * Instance of database hook integration manager
     * 
     * @var ExplainerPlugin_Database_Hook_Integration|null
     */
    private $integration_manager = null;
    
    /**
     * Initialization status
     * 
     * @var bool
     */
    private $initialized = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into WordPress initialization
        add_action('init', array($this, 'initialize'), 20);
        add_action('admin_init', array($this, 'admin_initialize'), 20);
    }
    
    /**
     * Initialize database integrations
     * 
     * @return bool True if initialization successful
     */
    public function initialize() {
        if ($this->initialized) {
            return true;
        }
        
        try {
            // Check if required classes are available
            if (!class_exists('ExplainerPlugin_Database_Hook_Integration')) {
                $this->log_error('Database hook integration class not available');
                return false;
            }
            
            if (!class_exists('ExplainerPlugin_Webhook_Emitter')) {
                $this->log_warning('Webhook emitter class not available, database integration will be disabled');
                return false;
            }
            
            // Get integration manager instance
            $this->integration_manager = ExplainerPlugin_Database_Hook_Integration::get_instance();
            
            if (!$this->integration_manager) {
                $this->log_error('Failed to get database integration manager instance');
                return false;
            }
            
            // Initialize all integrations
            $success = $this->integration_manager->initialise_integrations();
            
            if ($success) {
                $this->initialized = true;
                $this->log_info('Database integrations initialized successfully');
                
                // Hook for admin status checks
                add_action('wp_ajax_explainer_database_integration_status', array($this, 'ajax_get_integration_status'));
                add_action('wp_ajax_explainer_database_integration_test', array($this, 'ajax_test_integrations'));
                
                return true;
            } else {
                $this->log_error('Failed to initialize database integrations');
                return false;
            }
            
        } catch (Exception $e) {
            $this->log_error('Exception during database integration initialization', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            
            return false;
        }
    }
    
    /**
     * Initialize admin-specific functionality
     * 
     * @return void
     */
    public function admin_initialize() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        
        // Add admin notices for integration status if needed
        add_action('admin_notices', array($this, 'maybe_show_integration_notices'));
        
        // Add integration status to plugin settings
        add_filter('explainer_plugin_admin_integration_status', array($this, 'add_integration_status_to_admin'));
    }
    
    /**
     * AJAX handler for getting integration status
     * 
     * @return void
     */
    public function ajax_get_integration_status() {
        // Verify nonce
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'explainer_admin_nonce')) {
            wp_die(esc_html('Security check failed'));
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html('Insufficient permissions'));
        }
        
        if (!$this->integration_manager) {
            wp_send_json_error('Integration manager not available');
        }
        
        $status = $this->integration_manager->get_integration_status();
        $performance_metrics = $this->integration_manager->get_performance_metrics();
        
        wp_send_json_success(array(
            'status' => $status,
            'performance' => $performance_metrics,
            'initialized' => $this->initialized
        ));
    }
    
    /**
     * AJAX handler for testing integrations
     * 
     * @return void
     */
    public function ajax_test_integrations() {
        // Verify nonce
        if (!wp_verify_nonce(isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '', 'explainer_admin_nonce')) {
            wp_die(esc_html('Security check failed'));
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html('Insufficient permissions'));
        }
        
        if (!$this->integration_manager) {
            wp_send_json_error('Integration manager not available');
        }
        
        $test_results = $this->integration_manager->test_integration();
        
        wp_send_json_success(array(
            'test_results' => $test_results,
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Show admin notices for integration issues
     * 
     * @return void
     */
    public function maybe_show_integration_notices() {
        if (!$this->initialized && current_user_can('manage_options')) {
            $screen = get_current_screen();
            
            // Only show on plugin pages
            if ($screen && strpos($screen->id, 'explainer') !== false) {
                echo '<div class="notice notice-warning"><p>';
                echo esc_html__('AI Explainer database integrations are not initialized. Real-time features may not work properly.', 'ai-explainer');
                echo '</p></div>';
            }
        }
    }
    
    /**
     * Add integration status to admin interface
     * 
     * @param array $status Current status array
     * @return array Modified status array
     */
    public function add_integration_status_to_admin($status) {
        if (!$this->integration_manager) {
            $status['database_integration'] = array(
                'status' => 'disabled',
                'message' => 'Database integration manager not available'
            );
            return $status;
        }
        
        $integration_status = $this->integration_manager->get_integration_status();
        $performance = $this->integration_manager->get_performance_metrics();
        
        $status['database_integration'] = array(
            'status' => $integration_status['global_enabled'] ? 'enabled' : 'disabled',
            'initialized' => $this->initialized,
            'integrations' => $integration_status['integrations'],
            'performance' => array(
                'total_operations' => $performance['total_operations'] ?? 0,
                'success_rate' => $performance['success_rate'] ?? 0,
                'average_time_ms' => $performance['average_time_ms'] ?? 0
            )
        );
        
        return $status;
    }
    
    /**
     * Get integration manager instance
     * 
     * @return ExplainerPlugin_Database_Hook_Integration|null
     */
    public function get_integration_manager() {
        return $this->integration_manager;
    }
    
    /**
     * Check if integrations are initialized
     * 
     * @return bool True if initialized
     */
    public function is_initialized() {
        return $this->initialized;
    }
    
    /**
     * Get initialization status with details
     * 
     * @return array Status information
     */
    public function get_initialization_status() {
        return array(
            'initialized' => $this->initialized,
            'integration_manager_available' => $this->integration_manager !== null,
            'webhook_emitter_available' => class_exists('ExplainerPlugin_Webhook_Emitter'),
            'required_classes' => array(
                'ExplainerPlugin_Database_Hook_Integration' => class_exists('ExplainerPlugin_Database_Hook_Integration'),
                'ExplainerPlugin_DB_Operation_Wrapper' => class_exists('ExplainerPlugin_DB_Operation_Wrapper'),
                'ExplainerPlugin_Webhook_Emitter' => class_exists('ExplainerPlugin_Webhook_Emitter')
            )
        );
    }
    
    /**
     * Reset integrations (for testing/debugging)
     * 
     * @return bool True if reset successful
     */
    public function reset_integrations() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $this->initialized = false;
        $this->integration_manager = null;
        
        // Reinitialize
        return $this->initialize();
    }
    
    /**
     * Log debug message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    private function log_debug($message, $context = array()) {
        if (function_exists('explainer_log_debug')) {
            explainer_log_debug($message, $context, 'Database_Integration_Init');
        }
    }
    
    /**
     * Log info message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    private function log_info($message, $context = array()) {
        if (function_exists('explainer_log_info')) {
            explainer_log_info($message, $context, 'Database_Integration_Init');
        }
    }
    
    /**
     * Log warning message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    private function log_warning($message, $context = array()) {
        if (function_exists('explainer_log_warning')) {
            explainer_log_warning($message, $context, 'Database_Integration_Init');
        } else if (ExplainerPlugin_Debug_Logger::is_enabled()) {
            ExplainerPlugin_Debug_Logger::warning($message, 'database', $context);
        }
    }
    
    /**
     * Log error message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    private function log_error($message, $context = array()) {
        if (function_exists('explainer_log_error')) {
            explainer_log_error($message, $context, 'Database_Integration_Init');
        } else if (ExplainerPlugin_Debug_Logger::is_enabled()) {
            ExplainerPlugin_Debug_Logger::error($message, 'database', $context);
        }
    }
}