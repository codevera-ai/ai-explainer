<?php
/**
 * Security enhancements for the Explainer Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle security-related functionality
 */
class ExplainerPlugin_Security {
    
    /**
     * Initialize security features
     */
    public function __construct() {
        add_action('init', array($this, 'add_security_headers'));
        add_action('wp_head', array($this, 'add_content_security_policy'));
        add_action('wp_ajax_explainer_get_explanation', array($this, 'validate_ajax_request'), 1);
        add_action('wp_ajax_nopriv_explainer_get_explanation', array($this, 'validate_ajax_request'), 1);
        add_filter('wp_die_handler', array($this, 'custom_die_handler'));
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        if (!headers_sent()) {
            // Prevent XSS attacks
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            
            // HSTS for HTTPS sites
            if (is_ssl()) {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            }
            
            // Remove potentially sensitive headers
            header_remove('X-Powered-By');
            header_remove('Server');
        }
    }
    
    /**
     * Add enhanced Content Security Policy
     */
    public function add_content_security_policy() {
        // Check if CSP should be applied
        if (!$this->should_apply_csp()) {
            return;
        }
        
        // Generate nonce for inline scripts
        $nonce = $this->generate_csp_nonce();
        
        // Build CSP with nonce support
        $csp_directives = array(
            'default-src' => "'self'",
            'script-src' => $this->get_script_src_directive($nonce),
            'style-src' => $this->get_style_src_directive($nonce),
            'img-src' => "'self' data: https:",
            'font-src' => "'self' data:",
            'connect-src' => $this->get_connect_src_directive(),
            'frame-ancestors' => "'self'",
            'frame-src' => "'none'",
            'object-src' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'manifest-src' => "'self'",
            'media-src' => "'self'",
            'worker-src' => "'self'",
            'child-src' => "'self'"
        );
        
        // Add upgrade insecure requests for HTTPS sites
        if (is_ssl()) {
            $csp_directives['upgrade-insecure-requests'] = '';
        }
        
        // Apply filters for customization
        $csp_directives = apply_filters('explainer_csp_directives', $csp_directives);
        
        // Build CSP string
        $csp = $this->build_csp_string($csp_directives);
        
        // Set CSP header and meta tag
        if (!headers_sent()) {
            header('Content-Security-Policy: ' . $csp);
        }
        
        echo '<meta http-equiv="Content-Security-Policy" content="' . esc_attr($csp) . '">' . "\n";
        
        // Store nonce for use in scripts
        $this->store_csp_nonce($nonce);
    }
    
    /**
     * Check if CSP should be applied
     */
    private function should_apply_csp() {
        // Apply CSP if explainer plugin is active on this page
        // Default to disabled to prevent theme conflicts unless explicitly enabled
        return (
            !is_admin() && 
            get_option('explainer_csp_enabled', false) &&
            get_option('explainer_enabled', true) &&
            $this->is_content_page()
        );
    }
    
    /**
     * Generate CSP nonce
     */
    private function generate_csp_nonce() {
        static $nonce = null;
        
        if ($nonce === null) {
            $nonce = wp_generate_uuid4();
        }
        
        return $nonce;
    }
    
    /**
     * Get script-src directive
     */
    private function get_script_src_directive($nonce) {
        $sources = array(
            "'self'",
            "'nonce-{$nonce}'"
        );
        
        // Add WordPress specific sources (using dynamic paths for compatibility with renamed directories)
        $wp_sources = array(
            includes_url(),
            content_url(),
            admin_url()
        );
        $sources = array_merge($sources, $wp_sources);
        
        // Add trusted external sources if needed
        $external_sources = get_option('explainer_csp_script_sources', array());
        if (!empty($external_sources)) {
            $sources = array_merge($sources, $external_sources);
        }
        
        // Allow unsafe-eval for WordPress modules (needed for Interactivity API)
        if (get_option('explainer_csp_allow_eval', true)) {
            $sources[] = "'unsafe-eval'";
        }
        
        // Allow specific inline event handlers if necessary (minimal)
        if (get_option('explainer_csp_allow_inline_handlers', false)) {
            $sources[] = "'unsafe-inline'";
        }
        
        return implode(' ', $sources);
    }
    
    /**
     * Get style-src directive
     */
    private function get_style_src_directive($nonce) {
        $sources = array(
            "'self'",
            "'nonce-{$nonce}'"
        );
        
        // Allow inline styles for compatibility with WordPress themes
        // This is necessary because many themes use inline styles
        $sources[] = "'unsafe-inline'";
        
        // Add trusted external style sources
        $external_sources = get_option('explainer_csp_style_sources', array());
        if (!empty($external_sources)) {
            $sources = array_merge($sources, $external_sources);
        }
        
        return implode(' ', $sources);
    }
    
    /**
     * Get connect-src directive
     */
    private function get_connect_src_directive() {
        $sources = array(
            "'self'",
            admin_url('admin-ajax.php'),
            site_url()
        );
        
        // Add OpenAI API if configured
        if (get_option('explainer_openai_api_key')) {
            $sources[] = 'https://api.openai.com';
        }
        
        // Add any additional connect sources from settings
        $additional_sources = get_option('explainer_csp_connect_sources', array());
        if (!empty($additional_sources)) {
            $sources = array_merge($sources, $additional_sources);
        }
        
        return implode(' ', array_unique($sources));
    }
    
    /**
     * Build CSP string from directives
     */
    private function build_csp_string($directives) {
        $csp_parts = array();
        
        foreach ($directives as $directive => $value) {
            if (empty($value)) {
                $csp_parts[] = $directive;
            } else {
                $csp_parts[] = $directive . ' ' . $value;
            }
        }
        
        return implode('; ', $csp_parts);
    }
    
    /**
     * Store CSP nonce for use in scripts
     */
    private function store_csp_nonce($nonce) {
        // Store in global for PHP use
        $GLOBALS['explainer_csp_nonce'] = $nonce;
        
        // Add filter to inject nonce into WordPress inline scripts
        add_filter('script_loader_tag', array($this, 'add_nonce_to_inline_scripts'), 10, 3);
        add_filter('style_loader_tag', array($this, 'add_nonce_to_inline_styles'), 10, 4);
        
        // Hook into wp_print_inline_script_tag for WordPress 5.7+
        if (function_exists('wp_print_inline_script_tag')) {
            add_filter('wp_inline_script_attributes', array($this, 'add_nonce_to_wp_inline_scripts'), 10, 2);
        }
    }
    
    /**
     * Get stored CSP nonce
     */
    public function get_csp_nonce() {
        return $GLOBALS['explainer_csp_nonce'] ?? null;
    }
    
    /**
     * Add nonce to inline scripts
     */
    public function add_nonce_to_inline_scripts($tag, $handle, $src) {
        $nonce = $this->get_csp_nonce();
        if (!$nonce || $src) {
            return $tag;
        }
        
        // Add nonce to script tag if it doesn't already have one
        if (strpos($tag, 'nonce=') === false && strpos($tag, '<script') !== false) {
            $tag = str_replace('<script', '<script nonce="' . esc_attr($nonce) . '"', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Add nonce to inline styles
     */
    public function add_nonce_to_inline_styles($tag, $handle, $href, $media) {
        $nonce = $this->get_csp_nonce();
        if (!$nonce || $href) {
            return $tag;
        }
        
        // Add nonce to style tag if it doesn't already have one
        if (strpos($tag, 'nonce=') === false && strpos($tag, '<style') !== false) {
            $tag = str_replace('<style', '<style nonce="' . esc_attr($nonce) . '"', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Add nonce to WordPress inline script attributes (WordPress 5.7+)
     */
    public function add_nonce_to_wp_inline_scripts($attributes, $data) {
        $nonce = $this->get_csp_nonce();
        if ($nonce) {
            $attributes['nonce'] = $nonce;
        }
        return $attributes;
    }
    
    /**
     * Check if current page should have CSP
     */
    private function is_content_page() {
        return (
            is_singular() || 
            is_home() || 
            is_archive() || 
            is_category() || 
            is_tag()
        ) && !is_admin();
    }
    
    /**
     * Validate AJAX requests
     */
    public function validate_ajax_request() {
        // Additional security validation before processing
        if (!$this->is_valid_ajax_request()) {
            wp_die(esc_html__('Invalid request', 'ai-explainer'), 'Security Error', array('response' => 403));
        }
    }
    
    /**
     * Check if current request is for explainer plugin
     */
    private function is_explainer_request() {
        return (
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            (isset($_POST['action']) && sanitize_text_field( wp_unslash( $_POST['action'] ) ) === 'explainer_get_explanation') ||
            (strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), 'explainer') !== false)
        );
    }
    
    /**
     * Validate AJAX request security
     */
    private function is_valid_ajax_request() {
        // Check if it's an AJAX request
        if (!wp_doing_ajax()) {
            return false;
        }
        
        // Check action
        if (!isset($_POST['action']) || $_POST['action'] !== 'explainer_get_explanation') {
            return false;
        }
        
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'explainer_nonce')) {
            return false;
        }
        
        // Check user agent
        $user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
        if (empty($user_agent) || strlen($user_agent) < 10) {
            return false;
        }
        
        // Check referer
        if (!wp_get_referer()) {
            return false;
        }
        
        // Check rate limiting
        $user_identifier = explainer_get_user_identifier();
        if (explainer_check_advanced_rate_limit($user_identifier)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Custom die handler for security errors
     */
    public function custom_die_handler($handler) {
        if (wp_doing_ajax() && $this->is_explainer_request()) {
            return array($this, 'ajax_die_handler');
        }
        return $handler;
    }
    
    /**
     * AJAX die handler
     */
    public function ajax_die_handler($message, $title = '', $args = array()) {
        $response = array(
            'success' => false,
            'data' => array(
                'message' => $message,
                'title' => $title
            )
        );
        
        wp_send_json($response);
    }
    
    /**
     * Log security events
     */
    public function log_security_event($event_type, $details = array()) {
        if (!get_option('explainer_security_logging', true)) {
            return;
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event_type' => sanitize_text_field($event_type),
            'user_id' => get_current_user_id(),
            'user_ip' => explainer_get_client_ip(),
            'user_agent' => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
            'details' => sanitize_text_field(json_encode($details))
        );
        
        // Log to WordPress debug log
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            // Use WordPress logging mechanism instead of error_log
            $message = 'Explainer Plugin Security: ' . wp_json_encode($log_entry);
            // Write to debug.log using WordPress method
            if ( function_exists( 'wp_debug_log' ) ) {
                wp_debug_log( $message );
            }
        }
        
        // Store in database if needed
        if (get_option('explainer_security_database_logging', false)) {
            $this->store_security_log($log_entry);
        }
    }
    
    /**
     * Store security log in database
     */
    private function store_security_log($log_entry) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'explainer_security_log';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct query needed for security event logging
        $wpdb->insert(
            $table_name,
            $log_entry,
            array('%s', '%s', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Check for suspicious activity patterns
     */
    public function check_suspicious_activity($user_identifier) {
        // Check for multiple failed requests
        $failed_requests = get_transient("explainer_failed_requests_{$user_identifier}");
        if ($failed_requests && $failed_requests > 10) {
            $this->log_security_event('suspicious_activity', array(
                'user_identifier' => $user_identifier,
                'failed_requests' => $failed_requests
            ));
            return true;
        }
        
        // Check for rapid requests
        $request_times = get_transient("explainer_request_times_{$user_identifier}");
        if ($request_times && is_array($request_times) && count($request_times) > 5) {
            $time_diff = max($request_times) - min($request_times);
            if ($time_diff < 10) { // 5 requests in 10 seconds
                $this->log_security_event('rapid_requests', array(
                    'user_identifier' => $user_identifier,
                    'request_count' => count($request_times),
                    'time_span' => $time_diff
                ));
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Block suspicious IP addresses
     */
    public function block_suspicious_ip($ip_address) {
        if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
            return false;
        }
        
        $blocked_ips = get_option('explainer_blocked_ips', array());
        if (!in_array($ip_address, $blocked_ips)) {
            $blocked_ips[] = $ip_address;
            update_option('explainer_blocked_ips', $blocked_ips);
            
            $this->log_security_event('ip_blocked', array(
                'ip_address' => $ip_address
            ));
        }
        
        return true;
    }
    
    /**
     * Check if IP is blocked
     */
    public function is_ip_blocked($ip_address) {
        $blocked_ips = get_option('explainer_blocked_ips', array());
        return in_array($ip_address, $blocked_ips);
    }
    
    /**
     * Clean up old security logs
     */
    public function cleanup_security_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'explainer_security_log';
        $retention_days = get_option('explainer_security_log_retention', 30);
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for security log cleanup
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $retention_days
        ));
    }
    
    /**
     * Get security statistics
     */
    public function get_security_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'explainer_security_log';
        
        $stats = array(
            'total_events' => 0,
            'today_events' => 0,
            'blocked_ips' => 0,
            'recent_events' => array()
        );
        
        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for table existence check
        if ($wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name) {
            return $stats;
        }
        
        // Total events
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct query needed for security statistics
        $stats['total_events'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Today's events
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct query needed for security statistics, table name is safe
        $stats['today_events'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE DATE(timestamp) = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            current_time('Y-m-d')
        ));
        
        // Blocked IPs
        $blocked_ips = get_option('explainer_blocked_ips', array());
        $stats['blocked_ips'] = count($blocked_ips);
        
        // Recent events
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct query needed for security statistics, table name is safe
        $stats['recent_events'] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            10
        ));
        
        return $stats;
    }
}