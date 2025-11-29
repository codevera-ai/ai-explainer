<?php
/**
 * Secure API proxy for OpenAI integration
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Handle secure API proxy functionality
 */
class ExplainerPlugin_API_Proxy {
    
    /**
     * OpenAI API endpoint
     */
    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    
    /**
     * API timeout in seconds
     */
    private const API_TIMEOUT = 10;
    
    /**
     * Maximum tokens for explanation
     */
    private const MAX_TOKENS = 150;
    
    /**
     * Cache for decrypted API keys (per-request caching)
     */
    private $decrypted_key_cache = array();
    
    /**
     * Selection tracker instance
     */
    private $selection_tracker;
    
    /**
     * Initialize the API proxy
     */
    public function __construct() {
        // Constructor will be called by main plugin
        $this->selection_tracker = new ExplainerPlugin_Selection_Tracker();
        
        // AJAX actions are added in the main plugin file
    }
    
    /**
     * Handle debug log entries from JavaScript
     */
    public function handle_debug_log() {
        // Verify nonce for security
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'explainer_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
            return;
        }
        
        // Only process debug logs if debug mode is enabled
        if ( ! get_option( 'explainer_debug_mode', false ) ) {
            wp_send_json_success();
            return;
        }
        
        // Get debug message and data
        $message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
        $data = isset( $_POST['data'] ) ? sanitize_text_field( wp_unslash( $_POST['data'] ) ) : '{}';
        
        if ( ! empty( $message ) ) {
            // Parse data as JSON if possible
            $parsed_data = json_decode( $data, true );
            if ( JSON_ERROR_NONE !== json_last_error() ) {
                $parsed_data = array( 'raw_data' => $data );
            }
            
            // Log the JavaScript debug message
            $this->debug_log( $message, $parsed_data );
        }
        
        wp_send_json_success();
    }
    
    /**
     * Handle Ajax request for explanation with enhanced security
     */
    public function get_explanation() {
        // Only log detailed information in debug mode
        if ( get_option( 'explainer_debug_mode', false ) ) {
            $this->debug_log( 'Explanation request started', array(
                'timestamp' => current_time( 'mysql' ),
                'user_id' => get_current_user_id(),
                'request_method' => sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'unknown' ) )
            ) );
        }
        
        // Enhanced CSRF protection
        if ( ! $this->verify_request_security() ) {
            if ( get_option( 'explainer_debug_mode', false ) ) {
                $this->debug_log( 'Security validation failed', array(
                    'reason' => 'Enhanced CSRF protection failed'
                ) );
            }
            wp_send_json_error( array( 'message' => __( 'Security validation failed', 'ai-explainer' ) ) );
        }
        
        // Verify nonce for security
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'explainer_nonce' ) ) {
            if ( get_option( 'explainer_debug_mode', false ) ) {
                $this->debug_log( 'Nonce validation failed', array(
                    'nonce_provided' => isset( $_POST['nonce'] )
                ) );
            }
            wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'ai-explainer' ) ) );
        }
        
        // Check if user has permission
        if ( ! $this->user_can_request_explanation() ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request', 'ai-explainer' ) ) );
        }
        
        // Get and validate input
        $selected_text = $this->sanitize_and_validate_input( $_POST ?? array() );
        if ( ! $selected_text ) {
            return;
        }
        
        // Get reading level parameter
        $reading_level = isset($_POST['reading_level']) ? sanitize_text_field( wp_unslash( $_POST['reading_level'] ) ) : 'standard';
        $reading_level = $this->sanitize_reading_level($reading_level);
        
        // Enhanced rate limiting with DDoS protection
        if ( $this->is_rate_limited() ) {
            $base_message = __( 'Rate limit exceeded. Please wait before making another request.', 'ai-explainer' );
            $polite_message = ExplainerPlugin_Localization::get_polite_error_message( $base_message );
            wp_send_json_error( array( 'message' => $polite_message ) );
        }
        
        // Track the selection before proceeding to API call
        $this->track_text_selection( $selected_text );
        
        // Check if API key is configured
        $api_key = $this->get_api_key();
        if ( ! $api_key ) {
            wp_send_json_error( array( 'message' => __( 'API key not configured. Please check your settings.', 'ai-explainer' ) ) );
        }
        
        // Get all cached explanations first
        $all_cached_explanations = $this->get_all_cached_explanations($selected_text);
        
        // Check if the requested reading level is already cached
        if (isset($all_cached_explanations[$reading_level])) {
            $cached_explanation = $all_cached_explanations[$reading_level];
            
            $this->debug_log('CACHE HIT - Returning cached explanation with bulk data', array(
                'explanation_length' => strlen($cached_explanation),
                'provider' => get_option('explainer_api_provider', 'openai'),
                'response_source' => 'cache',
                'total_cached_levels' => count($all_cached_explanations),
                'cached_levels' => array_keys($all_cached_explanations)
            ));
            
            // Track cached explanation for frontend meta tags
            $post_id = $this->get_current_post_id();
            if ($post_id) {
                WP_AI_Explainer_Frontend::track_explanation($post_id, $selected_text, $cached_explanation);
            }
            
            // Remove the requested level from cached explanations to avoid duplication
            $other_cached_explanations = $all_cached_explanations;
            unset($other_cached_explanations[$reading_level]);
            
            wp_send_json_success(array(
                'explanation' => $cached_explanation,
                'reading_level' => $reading_level,
                'cached' => true,
                'cached_explanations' => $other_cached_explanations,
                'provider' => get_option('explainer_api_provider', 'openai')
            ));
        }
        
        $this->debug_log('CACHE MISS - Proceeding to API request', array(
            'cache_enabled' => get_option('explainer_cache_enabled', true),
            'reason' => 'No cached explanation found'
        ));
        
        // Prepare for API request
        $this->debug_log('Preparing API request', array(
            'text_length' => strlen($selected_text),
            'user_id' => get_current_user_id(),
            'provider' => get_option('explainer_api_provider', 'openai'),
            'model' => get_option('explainer_api_model', 'gpt-3.5-turbo')
        ));
        
        // Make API request
        $start_time = microtime(true);
        $result = $this->make_api_request($selected_text, $api_key, $reading_level);
        $response_time = microtime(true) - $start_time;
        
        if ($result['success']) {
            // Cache the successful response
            $this->debug_log('Caching successful explanation', array(
                'cache_enabled' => get_option('explainer_cache_enabled', true),
                'cache_duration_hours' => get_option('explainer_cache_duration', 24)
            ));
            
            $cache_result = $this->cache_explanation($selected_text, $result['explanation'], $reading_level);
            if (!$cache_result) {
                $this->debug_log('CACHE STORAGE FAILED', array(
                    'reason' => 'cache_explanation returned false',
                    'cache_enabled' => get_option('explainer_cache_enabled', true)
                ));
            } else {
                $this->debug_log('Explanation cached successfully', array('status' => 'success'));
            }
            
            // Success logging
            $this->debug_log('=== API REQUEST SUCCESSFUL ===', array(
                'explanation_length' => strlen($result['explanation']),
                'tokens_used' => $result['tokens_used'] ?? 'unknown',
                'cost' => $result['cost'] ?? 'unknown',
                'response_time_seconds' => round($response_time, 3),
                'provider' => get_option('explainer_api_provider', 'openai'),
                'cached_for_future' => $cache_result
            ));
            
            $this->debug_log('=== EXPLANATION REQUEST COMPLETED SUCCESSFULLY ===', array(
                'total_response_time_seconds' => round($response_time, 3),
                'final_status' => 'success'
            ));
            
            // Save the explanation to the selections table
            $this->save_explanation_to_selection($selected_text, $result['explanation'], $reading_level);
            
            // Track explanation for frontend meta tags
            $post_id = $this->get_current_post_id();
            if ($post_id) {
                WP_AI_Explainer_Frontend::track_explanation($post_id, $selected_text, $result['explanation']);
            }
            
            wp_send_json_success(array(
                'explanation' => $result['explanation'],
                'reading_level' => $reading_level,
                'cached' => false,
                'cached_explanations' => $all_cached_explanations,
                'tokens_used' => $result['tokens_used'],
                'cost' => $result['cost'],
                'response_time' => round($response_time, 3),
                'provider' => get_option('explainer_api_provider', 'openai')
            ));
        } else {
            // Check if this is a quota exceeded error that should disable the plugin
            if (isset($result['disable_plugin']) && $result['disable_plugin'] === true) {
                $this->debug_log('QUOTA EXCEEDED - Auto-disabling plugin', array(
                    'error_type' => $result['error_type'] ?? 'quota_exceeded',
                    'provider' => get_option('explainer_api_provider', 'openai'),
                    'error_message' => $result['error']
                ));
                $this->handle_quota_exceeded_error($result);
            }
            
            // Failure logging
            $this->debug_log('=== API REQUEST FAILED ===', array(
                'error_message' => $result['error'],
                'error_type' => $result['error_type'] ?? 'unknown',
                'disable_plugin_triggered' => $result['disable_plugin'] ?? false,
                'response_time_seconds' => round($response_time, 3),
                'provider' => get_option('explainer_api_provider', 'openai'),
                'user_impact' => 'Request failed, no explanation provided'
            ));
            
            $this->debug_log('=== EXPLANATION REQUEST COMPLETED WITH ERROR ===', array(
                'total_response_time_seconds' => round($response_time, 3),
                'final_status' => 'error',
                'error_message' => $result['error']
            ));
            
            // Pass through all error details to frontend
            $error_response = array('message' => $result['error']);
            
            // Include error_type if available (for API key errors)
            if (isset($result['error_type'])) {
                $error_response['error_type'] = $result['error_type'];
            }
            
            wp_send_json_error($error_response);
        }
    }
    
    /**
     * Check if user can request explanations
     */
    private function user_can_request_explanation() {
        // Allow all users for now, but could be restricted based on settings
        return true;
    }
    
    /**
     * Sanitize and validate input
     */
    private function sanitize_and_validate_input($post_data) {
        // Get selected text
        $selected_text = isset($post_data['text']) ? sanitize_textarea_field( wp_unslash( $post_data['text'] ) ) : '';
        
        if (empty($selected_text)) {
            wp_send_json_error(array('message' => __('Text selection is required', 'ai-explainer')));
            return false;
        }
        
        // Get admin settings for validation
        $min_length = get_option('explainer_min_selection_length', 3);
        $max_length = get_option('explainer_max_selection_length', 200);
        $min_words = get_option('explainer_min_words', 1);
        $max_words = get_option('explainer_max_words', 30);
        
        // Basic sanitization first
        $selected_text = wp_strip_all_tags($selected_text);
        $selected_text = html_entity_decode($selected_text, ENT_QUOTES, 'UTF-8');
        $selected_text = trim($selected_text);
        $selected_text = preg_replace('/\s+/', ' ', $selected_text);
        
        // Check minimum length first
        if (strlen($selected_text) < $min_length) {
            /* translators: %d: minimum number of characters required */
            wp_send_json_error(array('message' => sprintf(__('Text selection is too short (minimum %d characters)', 'ai-explainer'), $min_length)));
            return false;
        }

        // Check maximum length
        if (strlen($selected_text) > $max_length) {
            /* translators: %d: maximum number of characters allowed */
            wp_send_json_error(array('message' => sprintf(__('Text selection is too long (maximum %d characters)', 'ai-explainer'), $max_length)));
            return false;
        }

        // Check word count
        $word_count = explainer_count_words($selected_text);
        if ($word_count < $min_words) {
            /* translators: %d: minimum number of words required */
            wp_send_json_error(array('message' => sprintf(__('Text selection has too few words (minimum %d words)', 'ai-explainer'), $min_words)));
            return false;
        }

        if ($word_count > $max_words) {
            /* translators: %d: maximum number of words allowed */
            wp_send_json_error(array('message' => sprintf(__('Text selection has too many words (maximum %d words)', 'ai-explainer'), $max_words)));
            return false;
        }
        
        // Use helper function for final security validation
        $validated_text = explainer_sanitize_text_selection($selected_text);
        if (!$validated_text) {
            // Check if this was due to a blocked word
            $blocked_word = apply_filters('explainer_blocked_word_found', false);
            if ($blocked_word !== false) {
                wp_send_json_error(array('message' => __('Your selection contains blocked content', 'ai-explainer')));
            } else {
                wp_send_json_error(array('message' => __('Text selection contains invalid content', 'ai-explainer')));
            }
            return false;
        }
        
        return $validated_text;
    }
    
    /**
     * Check if user is rate limited with enhanced DDoS protection
     */
    private function is_rate_limited() {
        $user_identifier = explainer_get_user_identifier();
        return explainer_check_advanced_rate_limit($user_identifier);
    }
    
    /**
     * Get API key from options based on current provider
     */
    private function get_api_key() {
        $provider_key = get_option('explainer_api_provider', 'openai');
        
        $this->debug_log('Retrieving API key via factory', array(
            'provider' => $provider_key,
            'factory_method' => 'ExplainerPlugin_Provider_Factory::get_current_decrypted_api_key'
        ));
        
        // Use the factory method to get decrypted key directly
        $decrypted_key = ExplainerPlugin_Provider_Factory::get_current_decrypted_api_key();
        
        if (empty($decrypted_key)) {
            $this->debug_log('API KEY RETRIEVAL FAILED', array(
                'provider' => $provider_key,
                'reason' => 'Factory returned empty key',
                'encrypted_key_exists' => !empty(get_option('explainer_openai_api_key', '')) || !empty(get_option('explainer_claude_api_key', ''))
            ));
            return false;
        }
        
        $this->debug_log('API key retrieved and decrypted successfully', array(
            'provider' => $provider_key,
            'key_length' => strlen($decrypted_key),
            'key_format_valid' => $this->is_valid_api_key_format($decrypted_key)
        ));
        
        return $decrypted_key;
    }
    
    /**
     * Get decrypted API key for admin display
     */
    public function get_decrypted_api_key() {
        return $this->get_api_key();
    }
    
    /**
     * Get decrypted API key for specific provider
     * 
     * @param string $provider Provider key (openai, claude)
     * @param bool $for_api_call Whether this is for an actual API call (affects debug logging)
     * @return string Decrypted API key or empty string
     */
    public function get_decrypted_api_key_for_provider($provider, $for_api_call = false) {
        // Get provider-specific API key
        $option_name = "explainer_{$provider}_api_key";
        $encrypted_key = get_option($option_name, '');
        
        if (empty($encrypted_key)) {
            return '';
        }
        
        // Decrypt the key
        return $this->decrypt_api_key($encrypted_key, $for_api_call);
    }
    
    /**
     * Encrypt API key for storage
     */
    public function encrypt_api_key($api_key) {
        if (empty($api_key)) {
            $this->debug_log('Encrypt API Key: Empty key provided');
            return '';
        }
        
        // Check if the key is already encrypted to prevent double encryption
        if ($this->is_encrypted_key($api_key)) {
            $this->debug_log('Encrypt API Key: Key already encrypted, returning as-is');
            return $api_key; // Already encrypted, return as-is
        }
        
        // Validate that this looks like a real API key before encrypting
        if (!$this->is_valid_api_key_format($api_key)) {
            $this->debug_log('Encrypt API Key: Invalid key format', array('key_prefix' => substr($api_key, 0, 3) . '...'));
            // Return empty string for invalid keys
            return '';
        }
        
        // Use new encryption service for consistent hashing across contexts
        $encryption_service = ExplainerPlugin_Encryption_Service::get_instance();
        $encrypted = $encryption_service->encrypt_api_key($api_key);
        
        if ($encrypted) {
            $this->debug_log('Encrypt API Key: Successfully encrypted with new service', array('key_prefix' => substr($api_key, 0, 3) . '...'));
            return $encrypted;
        }
        
        $this->debug_log('Encrypt API Key: New encryption service failed, using fallback');
        
        // Fallback to old method for backwards compatibility
        $salt = $this->get_encryption_salt();
        $encrypted = base64_encode($api_key . '|' . wp_hash($api_key . $salt));
        $this->debug_log('Encrypt API Key: Successfully encrypted with fallback method', array('key_prefix' => substr($api_key, 0, 3) . '...'));
        
        return $encrypted;
    }
    
    /**
     * Check if a key is already encrypted
     * 
     * @param string $key Key to check
     * @return bool True if key appears to be encrypted
     */
    private function is_encrypted_key($key) {
        // Encrypted keys are base64 encoded and contain a pipe character when decoded
        $decoded = base64_decode($key, true);
        
        // Check if it's valid base64 and contains the pipe separator
        if ($decoded === false) {
            return false;
        }
        
        $parts = explode('|', $decoded);
        return count($parts) === 2;
    }
    
    /**
     * Get persistent encryption salt from database
     * 
     * @return string Encryption salt
     */
    private function get_encryption_salt() {
        $salt = get_option('explainer_encryption_salt', '');
        
        if (empty($salt)) {
            // Check if any API keys exist before generating new salt
            $openai_key = get_option('explainer_openai_api_key', '');
            $claude_key = get_option('explainer_claude_api_key', '');
            
            if (!empty($openai_key) || !empty($claude_key)) {
                // API keys exist but no salt - this means keys are inaccessible
                // Wipe the keys and set admin notice
                update_option('explainer_openai_api_key', '');
                update_option('explainer_claude_api_key', '');
                update_option('explainer_api_keys_reset_notice', true);
                
                $this->debug_log('Found existing API keys but no salt - keys wiped', array(
                    'openai_key_exists' => !empty($openai_key),
                    'claude_key_exists' => !empty($claude_key)
                ));
            }
            
            // Generate new salt
            $salt = wp_generate_password(64, true, true);
            update_option('explainer_encryption_salt', $salt, 'yes');
            
            $this->debug_log('Generated fallback encryption salt', array(
                'salt_length' => strlen($salt),
                'reason' => 'No existing salt found during encryption/decryption'
            ));
        }
        
        return $salt;
    }
    
    /**
     * Validate API key format for any provider
     * 
     * @param string $api_key Key to validate
     * @return bool True if valid format
     */
    private function is_valid_api_key_format($api_key) {
        if (!$api_key || !is_string($api_key)) {
            return false;
        }
        
        $api_key = trim($api_key);
        
        // Check for OpenAI format (sk-...)
        if (str_starts_with($api_key, 'sk-')) {
            return preg_match('/^sk-[a-zA-Z0-9._-]+$/', $api_key) && strlen($api_key) >= 20 && strlen($api_key) <= 200;
        }
        
        // Check for Claude format (sk-ant-...)
        if (str_starts_with($api_key, 'sk-ant-')) {
            return preg_match('/^sk-ant-[a-zA-Z0-9_-]+$/', $api_key) && strlen($api_key) >= 20 && strlen($api_key) <= 200;
        }
        
        return false;
    }
    
    /**
     * Decrypt API key from storage
     */
    private function decrypt_api_key($encrypted_key, $for_api_call = false) {
        if (empty($encrypted_key)) {
            if ($for_api_call) {
                $this->debug_log('Decrypt API Key: Empty key provided');
            }
            return '';
        }
        
        // Check cache first to avoid repeated decryption
        $cache_key = md5($encrypted_key);
        if (isset($this->decrypted_key_cache[$cache_key])) {
            return $this->decrypted_key_cache[$cache_key];
        }
        
        // Check if the key is already in plain text (not encrypted)
        if ($this->is_valid_api_key_format($encrypted_key)) {
            if ($for_api_call) {
                $this->debug_log('Decrypt API Key: Key already in plain text', array('key_prefix' => substr($encrypted_key, 0, 3) . '...'));
            }
            $this->decrypted_key_cache[$cache_key] = $encrypted_key;
            return $encrypted_key; // Already decrypted, return as-is
        }
        
        // Try new encryption service first (context-independent)
        $encryption_service = ExplainerPlugin_Encryption_Service::get_instance();
        $decrypted = $encryption_service->decrypt_api_key($encrypted_key, $for_api_call);
        
        if (!empty($decrypted)) {
            if ($for_api_call) {
                $this->debug_log('Decrypt API Key: Successfully decrypted with new service', array('key_prefix' => substr($decrypted, 0, 3) . '...'));
            }
            $this->decrypted_key_cache[$cache_key] = $decrypted;
            return $decrypted;
        }
        
        // Fallback to old wp_hash method for backwards compatibility
        if ($for_api_call) {
            $this->debug_log('Decrypt API Key: New service failed, trying legacy method');
        }
        
        // Attempt to decrypt with old method
        $decoded = base64_decode($encrypted_key, true);
        if ($decoded === false) {
            if ($for_api_call) {
                $this->debug_log('Decrypt API Key: Invalid base64 format');
            }
            // Not valid base64, might be an unencrypted key that failed format validation
            $this->decrypted_key_cache[$cache_key] = '';
            return '';
        }
        
        $parts = explode('|', $decoded);
        
        if (count($parts) !== 2) {
            if ($for_api_call) {
                $this->debug_log('Decrypt API Key: Not in encrypted format, checking if plain text');
            }
            // Not in our encrypted format, check if it's a valid plain text key
            if ($this->is_valid_api_key_format($encrypted_key)) {
                $this->decrypted_key_cache[$cache_key] = $encrypted_key;
                return $encrypted_key;
            }
            $this->decrypted_key_cache[$cache_key] = '';
            return '';
        }
        
        $api_key = $parts[0];
        $hash = $parts[1];
        
        // Verify hash using old wp_hash method
        $salt = $this->get_encryption_salt();
        if (wp_hash($api_key . $salt) === $hash) {
            // Validate decrypted key format
            if ($this->is_valid_api_key_format($api_key)) {
                if ($for_api_call) {
                    $this->debug_log('Decrypt API Key: Successfully decrypted with legacy method', array('key_prefix' => substr($api_key, 0, 3) . '...'));
                }
                $this->decrypted_key_cache[$cache_key] = $api_key;
                
                // Auto-migrate to new encryption format
                $new_encrypted = $encryption_service->encrypt_api_key($api_key);
                if ($new_encrypted && $new_encrypted !== $encrypted_key) {
                    if ($for_api_call) {
                        $this->debug_log('Decrypt API Key: Auto-migrating to new encryption format');
                    }
                    // Update the stored key with new format (will be done by calling code)
                }
                
                return $api_key;
            } else {
                if ($for_api_call) {
                    $this->debug_log('Decrypt API Key: Decrypted key has invalid format');
                }
            }
        } else {
            if ($for_api_call) {
                $this->debug_log('Decrypt API Key: Legacy hash verification failed');
            }
        }
        
        // All decryption methods failed
        if ($for_api_call) {
            $this->debug_log('Decrypt API Key: All decryption methods failed');
        }
        $this->decrypted_key_cache[$cache_key] = '';
        return '';
    }
    
    /**
     * Get cached explanation from database
     */
    private function get_cached_explanation($text, $reading_level = 'standard') {
        if (!get_option('explainer_cache_enabled', true)) {
            return false;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        $text_hash = hash('sha256', strtolower(trim($text)));
        $cache_duration = (int) get_option('explainer_cache_duration', 24) * HOUR_IN_SECONDS;
        
        // Get cached explanation if it exists and hasn't expired (or is manually edited)
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT ai_explanation, explanation_cached_at, manually_edited
             FROM $table_name
             WHERE text_hash = %s
             AND reading_level = %s
             AND ai_explanation IS NOT NULL
             AND ai_explanation != ''
             AND (
                 manually_edited = 1
                 OR (explanation_cached_at IS NOT NULL AND explanation_cached_at > %s)
             )",
            $text_hash,
            $reading_level,
            gmdate('Y-m-d H:i:s', time() - $cache_duration)
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        
        if ($result && !empty($result->ai_explanation)) {
            $this->debug_log('DATABASE CACHE HIT', array(
                'text_hash' => $text_hash,
                'reading_level' => $reading_level,
                'cached_at' => $result->explanation_cached_at,
                'manually_edited' => $result->manually_edited,
                'explanation_length' => strlen($result->ai_explanation)
            ));
            return $result->ai_explanation;
        }
        
        return false;
    }
    
    /**
     * Get all cached explanations for a text across all reading levels
     */
    private function get_all_cached_explanations($text) {
        if (!get_option('explainer_cache_enabled', true)) {
            return array();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        $text_hash = hash('sha256', strtolower(trim($text)));
        $cache_duration = (int) get_option('explainer_cache_duration', 24) * HOUR_IN_SECONDS;
        
        // Get all cached explanations for this text across all reading levels
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT reading_level, ai_explanation, explanation_cached_at, manually_edited
             FROM $table_name
             WHERE text_hash = %s
             AND ai_explanation IS NOT NULL
             AND ai_explanation != ''
             AND (
                 manually_edited = 1
                 OR (explanation_cached_at IS NOT NULL AND explanation_cached_at > %s)
             )",
            $text_hash,
            gmdate('Y-m-d H:i:s', time() - $cache_duration)
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        
        $cached_explanations = array();
        if ($results) {
            foreach ($results as $result) {
                $cached_explanations[$result->reading_level] = $result->ai_explanation;
            }
            
            $this->debug_log('BULK CACHE HIT', array(
                'text_hash' => $text_hash,
                'cached_levels' => array_keys($cached_explanations),
                'total_cached' => count($cached_explanations)
            ));
        }
        
        return $cached_explanations;
    }
    
    /**
     * Cache explanation in database (handled by save_explanation_to_selection)
     */
    private function cache_explanation($text, $explanation, $reading_level = 'standard') {
        // Caching is now handled by save_explanation_to_selection
        // This method maintained for compatibility
        return true;
    }
    
    /**
     * Make API request using provider pattern
     */
    private function make_api_request($selected_text, $api_key, $reading_level = 'standard') {
        // Get current provider
        $this->debug_log('Initializing AI provider', array(
            'provider_key' => get_option('explainer_api_provider', 'openai')
        ));
        
        $provider = ExplainerPlugin_Provider_Factory::get_current_provider();
        
        if (!$provider) {
            $this->debug_log('PROVIDER INITIALIZATION FAILED', array(
                'provider_key' => get_option('explainer_api_provider', 'openai'),
                'error' => 'Factory returned null provider',
                'available_providers' => array_keys(ExplainerPlugin_Provider_Factory::get_available_providers())
            ));
            return array(
                'success' => false,
                'error' => __('No AI provider configured.', 'ai-explainer')
            );
        }
        
        $this->debug_log('Provider initialized successfully', array(
            'provider_name' => $provider->get_name(),
            'provider_class' => get_class($provider)
        ));
        
        // Get context if available (nonce already verified above)
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified at start of get_explanation() method
        $context_raw = isset($_POST['context']) ? sanitize_textarea_field(wp_unslash($_POST['context'])) : null;
        $context = null;
        
        // Try to decode as JSON for enhanced context object, fallback to simple string
        if ($context_raw) {
            if (is_string($context_raw) && (strpos($context_raw, '{') === 0 || strpos($context_raw, '[') === 0)) {
                $context_decoded = json_decode($context_raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($context_decoded)) {
                    $context = $context_decoded;
                } else {
                    $context = sanitize_textarea_field($context_raw);
                }
            } else {
                $context = sanitize_textarea_field($context_raw);
            }
        }
        
        // Prepare the prompt
        $this->debug_log('Preparing prompt for API request', array(
            'custom_prompt_enabled' => !empty(get_option('explainer_custom_prompt', '')),
            'language' => get_option('explainer_language', 'en_GB'),
            'context_provided' => !empty($context)
        ));
        
        $prompt = $this->prepare_prompt($selected_text, $context, $reading_level);
        
        // Debug logging - log the prompt being sent (without API key)
        $this->debug_log('Prompt prepared for API', array(
            'provider' => $provider->get_name(),
            'prompt_length' => strlen($prompt),
            'prompt_preview' => substr($prompt, 0, 100) . '...',
            'selected_text_length' => strlen($selected_text),
            'context_provided' => !empty($context)
        ));
        
        // Get AI model setting
        $model = get_option('explainer_api_model', 'gpt-3.5-turbo');
        
        $this->debug_log('Making API request to provider', array(
            'provider' => $provider->get_name(),
            'model' => $model,
            'api_key_configured' => !empty($api_key),
            'api_key_length' => strlen($api_key)
        ));
        
        // Check if mock mode is enabled (via global config, constant, WordPress option, or test flag for E2E testing)
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Read-only check for test flag, no data modification
        $mock_mode_enabled = ExplainerPlugin_Config::is_mock_explanations_enabled()
                           || get_option('explainer_mock_mode', false)
                           || (isset($_POST['test_mock_mode']) && $_POST['test_mock_mode'] === 'true');
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        if ($mock_mode_enabled) {
            $this->debug_log('Using mock mode for explanation testing', array(
                'selected_text_preview' => substr($selected_text, 0, 50) . '...',
                'prompt_preview' => substr($prompt, 0, 100) . '...',
                'provider' => $provider->get_name(),
                'model' => $model
            ));
            
            $response = $this->generate_mock_api_response($selected_text, $prompt, $provider->get_name(), $model);
            $api_response_time = 0.1; // Mock fast response time
        } else {
            // Make request using provider
            $api_start_time = microtime(true);
            $response = $provider->make_request($api_key, $prompt, $model);
            $api_response_time = microtime(true) - $api_start_time;
        }
        
        $this->debug_log('Provider API call completed', array(
            'response_time_seconds' => round($api_response_time, 3),
            'response_received' => !empty($response),
            'response_size_bytes' => !empty($response) ? strlen(json_encode($response)) : 0
        ));
        
        // Parse response using provider
        $this->debug_log('Parsing API response', array(
            'parser' => get_class($provider) . '::parse_response',
            'model' => $model
        ));
        
        $parsed_result = $provider->parse_response($response, $model);
        
        $this->debug_log('API response parsed', array(
            'success' => $parsed_result['success'] ?? false,
            'has_explanation' => !empty($parsed_result['explanation']) ?? false,
            'explanation_length' => !empty($parsed_result['explanation']) ? strlen($parsed_result['explanation']) : 0,
            'has_error' => !empty($parsed_result['error']) ?? false,
            'error_type' => $parsed_result['error_type'] ?? 'unknown'
        ));
        
        return $parsed_result;
    }
    
    /**
     * Prepare prompt for API request
     */
    private function prepare_prompt($selected_text, $context = null, $reading_level = 'standard') {
        $start_time = microtime(true);
        
        // Get reading level specific prompt template
        $prompt_template = $this->get_reading_level_prompt($reading_level);
        
        // Validate that template contains {{selectedtext}} placeholder
        if (!str_contains($prompt_template, '{{selectedtext}}')) {
            // Fallback to default if invalid template
            $prompt_template = $this->get_reading_level_prompt('standard');
        }
        
        // Add language instruction based on selected language
        $selected_language = get_option('explainer_language', 'en_GB');
        $language_instruction = $this->get_language_instruction($selected_language);
        
        if (!empty($language_instruction)) {
            $prompt_template = $language_instruction . ' ' . $prompt_template;
        }
        
        // Replace {{selectedtext}} with the selected text
        $prompt = str_replace('{{selectedtext}}', $selected_text, $prompt_template);
        
        // Get current post ID for previous terms lookup
        $post_id = $this->get_current_post_id();
        
        // Performance timing: Database query start
        $db_query_start = microtime(true);
        $previous_terms_count = 0;
        
        // Add enhanced context if available
        if ($context) {
            $context_parts = array();
            
            // Add post title if available
            if (is_array($context) && !empty($context['title'])) {
                $context_parts[] = "Post: " . sanitize_text_field($context['title']);
            }
            
            // Add previous terms if we have a post ID
            if ($post_id) {
                $previous_terms = $this->get_previous_terms($post_id, 10);
                $previous_terms_count = count($previous_terms);
                
                if (!empty($previous_terms)) {
                    // Remove current selection from previous terms to avoid duplication
                    $previous_terms = array_filter($previous_terms, function($term) use ($selected_text) {
                        return strcasecmp(trim($term), trim($selected_text)) !== 0;
                    });
                    
                    if (!empty($previous_terms)) {
                        $context_parts[] = "Previous terms: " . implode(', ', array_slice($previous_terms, 0, 10));
                    }
                }
            }
            
            $db_query_time = microtime(true) - $db_query_start;
            
            // Add paragraph context if available (prioritise over character-based context)
            if (is_array($context) && !empty($context['paragraph'])) {
                $context_parts[] = "Context: " . sanitize_textarea_field($context['paragraph']);
            } elseif (is_array($context)) {
                // Fallback to original context format
                $context_text = '';
                if (!empty($context['before'])) {
                    $context_text .= "..." . $context['before'];
                }
                $context_text .= "[" . $selected_text . "]";
                if (!empty($context['after'])) {
                    $context_text .= $context['after'] . "...";
                }
                if (!empty($context_text)) {
                    $context_parts[] = "Context: " . $context_text;
                }
            } elseif (is_string($context)) {
                // Legacy string context support
                $context_parts[] = "Context: " . $context;
            }
            
            // Add context parts to prompt
            if (!empty($context_parts)) {
                $prompt .= "\n\n" . implode("\n", $context_parts);
            }
        } else {
            $db_query_time = 0; // No context, no query
        }
        
        $total_time = microtime(true) - $start_time;
        
        // Performance logging
        $this->debug_log('Prompt preparation performance', array(
            'total_time_ms' => round($total_time * 1000, 2),
            'db_query_time_ms' => round($db_query_time * 1000, 2),
            'post_id' => $post_id,
            'previous_terms_found' => $previous_terms_count,
            'context_type' => is_array($context) ? 'enhanced' : (is_string($context) ? 'legacy' : 'none'),
            'context_parts_count' => isset($context_parts) ? count($context_parts) : 0,
            'final_prompt_length' => strlen($prompt)
        ));
        
        // Performance warning if preparation takes too long
        if ($total_time * 1000 > 50) { // 50ms threshold
            $this->debug_log('Prompt preparation exceeded performance target', array(
                'time_ms' => round($total_time * 1000, 2),
                'target_ms' => 50,
                'context_processing' => isset($context_parts) ? count($context_parts) : 0
            ));
        }
        
        return $prompt;
    }
    
    /**
     * Get language instruction for AI prompt based on selected language
     */
    private function get_language_instruction($language_code) {
        $language_instructions = array(
            'en_US' => 'Please respond in American English.',
            'en_GB' => 'Please respond in British English.',
            'es_ES' => 'Por favor responde en español.',
            'de_DE' => 'Bitte antworten Sie auf Deutsch.',
            'fr_FR' => 'Veuillez répondre en français.',
            'hi_IN' => 'कृपया हिंदी में उत्तर दें।',
            'zh_CN' => '请用中文回答。'
        );
        
        return isset($language_instructions[$language_code]) ? $language_instructions[$language_code] : '';
    }
    
    /**
     * Get reading level specific prompt template
     */
    private function get_reading_level_prompt($reading_level) {
        $option_name = "explainer_prompt_{$reading_level}";
        $stored_prompt = get_option($option_name);
        
        // Use stored prompt if available, otherwise use default from config
        if (!empty($stored_prompt)) {
            return $stored_prompt;
        }
        
        // Get default from config class
        return ExplainerPlugin_Config::get_default_reading_level_prompt($reading_level);
    }
    
    /**
     * Get reading level modifier for AI prompt (deprecated - kept for backward compatibility)
     * @deprecated Use get_reading_level_prompt() instead
     */
    private function get_reading_level_modifier($reading_level) {
        $reading_level_modifiers = array(
            'very_simple' => 'Explain this to an 8-year-old.',
            'simple' => 'Explain in plain English without jargon.',
            'standard' => '', // No modifier for standard
            'detailed' => 'Explain this in more detail with added context.',
            'expert' => 'Explain this for a professional audience with technical language.'
        );
        
        return isset($reading_level_modifiers[$reading_level]) ? $reading_level_modifiers[$reading_level] : '';
    }
    
    /**
     * Sanitize reading level input
     */
    private function sanitize_reading_level($reading_level) {
        $valid_levels = array('very_simple', 'simple', 'standard', 'detailed', 'expert');
        
        if (!in_array($reading_level, $valid_levels, true)) {
            return 'standard'; // Default fallback
        }
        
        return $reading_level;
    }
    
    /**
     * Handle API response (now handled by providers)
     * 
     * @deprecated This method is now handled by individual providers
     */
    private function handle_api_response($response, $model) {
        // This method is deprecated and replaced by provider-specific parsing
        // Kept for backward compatibility
        $provider = ExplainerPlugin_Provider_Factory::get_current_provider();
        
        if (!$provider) {
            return array(
                'success' => false,
                'error' => __('No AI provider configured.', 'ai-explainer')
            );
        }
        
        return $provider->parse_response($response, $model);
    }
    
    /**
     * Debug logging method using shared utility
     */
    private function debug_log($message, $data = array()) {
        $logger = ExplainerPlugin_Logger::get_instance();
        return $logger->debug($message, $data, 'API_Proxy');
    }
    
    /**
     * Get direct explanation for given prompt (for internal use)
     * 
     * @param string $prompt The prompt to send to AI
     * @param string $provider_key Provider key (openai-gpt35, openai-gpt4, etc.)
     * @return string|false Generated explanation or false on failure
     */
    public function get_direct_explanation($prompt, $provider_key = null, $options = array()) {
        try {
            // Get provider
            if ($provider_key) {
                // Parse provider key (e.g., 'openai-gpt35' -> 'openai' and 'gpt-3.5-turbo')
                $provider_map = array(
                    'openai-gpt35' => array('provider' => 'openai', 'model' => 'gpt-3.5-turbo'),
                    'openai-gpt4' => array('provider' => 'openai', 'model' => 'gpt-4'),
                    'claude-haiku' => array('provider' => 'claude', 'model' => 'claude-3-haiku-20240307'),
                    'claude-sonnet' => array('provider' => 'claude', 'model' => 'claude-3-sonnet-20240229')
                );
                
                if (!isset($provider_map[$provider_key])) {
                    return false;
                }
                
                $provider_info = $provider_map[$provider_key];
                $provider = ExplainerPlugin_Provider_Factory::get_provider($provider_info['provider']);
                $model = $provider_info['model'];
            } else {
                $provider = ExplainerPlugin_Provider_Factory::get_current_provider();
                $model = get_option('explainer_model', 'gpt-3.5-turbo');
            }
            
            if (!$provider) {
                return false;
            }
            
            // Get API key
            $api_key = $this->get_decrypted_api_key_for_provider($provider->get_key(), true);
            if (!$api_key) {
                if (function_exists('explainer_log_error')) {
                    explainer_log_error('No API key configured for provider: ' . $provider->get_key(), array('provider' => $provider->get_key()), 'api_proxy');
                }
                throw new Exception('No API key configured for provider: ' . $provider->get_key());
            }
            
            // Make request
            if (function_exists('explainer_log_debug')) {
                explainer_log_debug('Making request with options', array('options' => $options), 'api_proxy');
            }
            $response = $provider->make_request($api_key, $prompt, $model, $options);
            
            if (is_wp_error($response)) {
                if (function_exists('explainer_log_error')) {
                    explainer_log_error('Request failed with WP_Error: ' . $response->get_error_message(), array(), 'api_proxy');
                }
                return false;
            }
            
            // Parse response
            $parsed_response = $provider->parse_response($response, $model);
            
            if (!$parsed_response || !isset($parsed_response['explanation'])) {
                ExplainerPlugin_Debug_Logger::debug('API Proxy: Failed to parse response or no explanation found', 'api_proxy');
                if (function_exists('explainer_log_debug')) {
                    explainer_log_debug('Parsed response keys: ' . (is_array($parsed_response) ? implode(', ', array_keys($parsed_response)) : 'not an array'), array(), 'api_proxy');
                }
                return false;
            }
            
            return $parsed_response['explanation'];
            
        } catch (Exception $e) {
            if (function_exists('explainer_log_error')) {
                explainer_log_error('Direct explanation error: ' . $e->getMessage(), array(), 'api_proxy');
            }
            return false;
        }
    }
    
    /**
     * Test API key validity using provider pattern
     */
    public function test_api_key($api_key) {
        return ExplainerPlugin_Provider_Factory::test_current_api_key($api_key);
    }
    
    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private function get_user_ip() {
        return explainer_get_client_ip();
    }
    
    /**
     * Verify request security with comprehensive checks
     *
     * @return bool True if request is secure
     */
    private function verify_request_security() {
        // Check if request is POST
        if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) !== 'POST' ) {
            return false;
        }
        
        // Nonce verification is handled by the main function after this security check
        
        // Check user agent (basic bot detection)
        $user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
        if (empty($user_agent) || strlen($user_agent) < 10) {
            return false;
        }
        
        // Check for common bot patterns
        $bot_patterns = array(
            '/bot/i', '/crawler/i', '/spider/i', '/scraper/i',
            '/curl/i', '/wget/i', '/python/i', '/java/i'
        );
        
        foreach ($bot_patterns as $pattern) {
            if (preg_match($pattern, $user_agent)) {
                return false;
            }
        }
        
        // Check request timing (prevent replay attacks)
        $request_time = isset( $_SERVER['REQUEST_TIME'] ) ? (int) sanitize_text_field( wp_unslash( $_SERVER['REQUEST_TIME'] ) ) : time();
        if (abs(time() - $request_time) > 300) { // 5 minute window
            return false;
        }
        
        // Check for suspicious headers
        $suspicious_headers = array(
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP'
        );
        
        $proxy_count = 0;
        foreach ($suspicious_headers as $header) {
            if (isset($_SERVER[$header])) {
                $proxy_count++;
            }
        }
        
        // Too many proxy headers could indicate spoofing
        if ($proxy_count > 2) {
            return false;
        }
        
        return true;
    }
    
    
    /**
     * Handle quota exceeded error and auto-disable plugin
     * 
     * @param array $result API result containing quota exceeded error
     */
    private function handle_quota_exceeded_error($result) {
        // Get current provider name for logging
        $provider = get_option('explainer_api_provider', 'openai');
        $provider_name = $provider === 'openai' ? 'OpenAI' : 'Claude';
        
        // Get the error message
        $error_message = $result['error'] ?? __('API usage limit exceeded.', 'ai-explainer');
        
        // Auto-disable the plugin using helper function
        $disabled = explainer_auto_disable_plugin($error_message, $provider_name);
        
        if ($disabled) {
            // Additional logging for this critical event
            $this->debug_log('Plugin auto-disabled due to quota exceeded', array(
                'provider' => $provider_name,
                'error_message' => $error_message,
                'error_type' => $result['error_type'] ?? 'quota_exceeded',
                'user_id' => get_current_user_id(),
                'timestamp' => current_time('mysql')
            ));
            
            // Log to PHP error log as well for server-level tracking
            // Debug logging disabled for production
        }
    }
    
    /**
     * Track text selection for analytics
     * 
     * @param string $selected_text The text that was selected
     */
    private function track_text_selection($selected_text) {
        if (!$this->selection_tracker || empty($selected_text)) {
            return;
        }
        
        try {
            // Get source URL from POST data
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified at start of get_explanation() method
            $source_url = isset($_POST['source_url']) ? esc_url_raw(wp_unslash($_POST['source_url'])) : '';
            
            // Debug logging for URL tracking issues
            if (get_option('explainer_debug_mode', false)) {
                if (function_exists('explainer_log_debug')) {
                    explainer_log_debug('$_POST source_url = ' . ($source_url ?: 'empty'), array(), 'api_proxy');
                }
                if (function_exists('explainer_log_debug')) {
                    explainer_log_debug('wp_get_referer() = ' . (wp_get_referer() ?: 'empty'), array(), 'api_proxy');
                }
            }
            
            // Track the selection
            $tracked = $this->selection_tracker->track_selection($selected_text, $source_url);
            
            $this->debug_log('Selection tracking attempted', array(
                'text_length' => strlen($selected_text),
                'text_preview' => substr($selected_text, 0, 50) . '...',
                'source_url' => $source_url,
                'tracking_success' => $tracked
            ));
            
        } catch (Exception $e) {
            $this->debug_log('Selection tracking failed', array(
                'error' => $e->getMessage(),
                'text_length' => strlen($selected_text)
            ));
        }
    }
    
    /**
     * Get selection tracker instance
     * 
     * @return ExplainerPlugin_Selection_Tracker
     */
    public function get_selection_tracker() {
        return $this->selection_tracker;
    }
    
    
    /**
     * Save the AI explanation to the selections table
     * 
     * @param string $selected_text The text that was selected
     * @param string $explanation The AI-generated explanation
     * @param string $reading_level The reading level for this explanation
     */
    private function save_explanation_to_selection($selected_text, $explanation, $reading_level = 'standard') {
        if (empty($selected_text) || empty($explanation)) {
            return;
        }
        
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'ai_explainer_selections';
            
            // Get current post ID for proper record matching
            $current_post_id = $this->get_current_post_id();
            $use_post_id = ($current_post_id !== null) ? $current_post_id : 0;
            
            // Find existing selection record for this text and post to get the correct hash
            // This ensures reading level variations use the same hash as the original selection
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix
            $existing_record = $wpdb->get_row($wpdb->prepare(
                "SELECT text_hash, id FROM $table_name
                 WHERE selected_text = %s AND post_id = %d
                 ORDER BY id ASC
                 LIMIT 1",
                $selected_text,
                $use_post_id
            ));
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            
            if ($existing_record) {
                // Use the hash from existing record to ensure consistency
                $text_hash = $existing_record->text_hash;
            } else {
                // Generate hash for new text (fallback for new selections)
                $text_hash = hash('sha256', strtolower(trim($selected_text)));
            }
            
            // Check if this explanation has been manually edited for this reading level and post
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix
            $manually_edited = $wpdb->get_var($wpdb->prepare(
                "SELECT manually_edited FROM $table_name WHERE text_hash = %s AND reading_level = %s AND post_id = %d",
                $text_hash,
                $reading_level,
                $use_post_id
            ));
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            
            // Only update if not manually edited
            if (!$manually_edited) {
                // Try to update first, if no rows affected, insert new record
                // Include post_id in WHERE clause to match the unique constraint
                // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $wpdb->update uses prepared statements internally
                $result = $wpdb->update(
                    $table_name,
                    array(
                        'ai_explanation' => sanitize_textarea_field($explanation),
                        'explanation_cached_at' => current_time('mysql', true),
                        'manually_edited' => 0
                    ),
                    array(
                        'text_hash' => $text_hash,
                        'reading_level' => $reading_level,
                        'post_id' => $use_post_id
                    ),
                    array('%s', '%s', '%d'),
                    array('%s', '%s', '%d')
                );
                // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
                
                // If no rows were updated, insert a new record
                if ($result === 0) {
                    // Use the existing record we found above, or find any record with this text
                    if (!$existing_record) {
                        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix
                        $base_record = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM $table_name
                             WHERE selected_text = %s
                             ORDER BY
                                CASE WHEN post_id = %d THEN 0 ELSE 1 END,
                                id ASC
                             LIMIT 1",
                            $selected_text,
                            $use_post_id
                        ));
                        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    } else {
                        // Get full record data for the existing record we found
                        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix
                        $base_record = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM $table_name WHERE id = %d",
                            $existing_record->id
                        ));
                        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    }
                    
                    if ($base_record) {
                        // Use the post_id from current context (already calculated above)

                        // Insert new reading level variation, copying all important fields from base record
                        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $wpdb->insert uses prepared statements internally
                        $wpdb->insert(
                            $table_name,
                            array(
                                'post_id' => $use_post_id,  // Use current context post_id if available
                                'text_hash' => $base_record->text_hash,  // Use same hash as base record
                                'selected_text' => $selected_text,
                                'ai_explanation' => sanitize_textarea_field($explanation),
                                'explanation_cached_at' => current_time('mysql', true),
                                'manually_edited' => 0,
                                'reading_level' => $reading_level,
                                'selection_count' => 1,
                                'first_seen' => current_time('mysql'),
                                'last_seen' => current_time('mysql'),
                                'source_urls' => $base_record->source_urls,
                                'enabled' => $base_record->enabled,  // Copy enabled status from base record
                                'source' => $base_record->source     // Copy source from base record
                            ),
                            array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s')
                        );
                        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

                        $result = $wpdb->insert_id;
                    }
                }
                
                $this->debug_log('Explanation saved to selections table', array(
                    'text_hash' => $text_hash,
                    'reading_level' => $reading_level,
                    'text_preview' => substr($selected_text, 0, 50) . '...',
                    'explanation_length' => strlen($explanation),
                    'update_success' => $result !== false,
                    'was_manually_edited' => false
                ));
            } else {
                $this->debug_log('Explanation NOT saved - manually edited', array(
                    'text_hash' => $text_hash,
                    'reading_level' => $reading_level,
                    'text_preview' => substr($selected_text, 0, 50) . '...',
                    'explanation_length' => strlen($explanation),
                    'was_manually_edited' => true
                ));
            }
            
        } catch (Exception $e) {
            $this->debug_log('Failed to save explanation to selections table', array(
                'error' => $e->getMessage(),
                'text_preview' => substr($selected_text, 0, 50) . '...',
                'reading_level' => $reading_level
            ));
        }
    }
    
    /**
     * Get current post ID from request context
     *
     * @return int|null Post ID or null if not found
     */
    private function get_current_post_id() {
        // Try to get post ID from POST data (if sent by frontend)
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Called from get_explanation which verifies nonces
        if (isset($_POST['post_id']) && is_numeric($_POST['post_id'])) {
            return absint($_POST['post_id']);
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        // Try to get from referer URL
        $referer = wp_get_referer();
        if ($referer) {
            $post_id = url_to_postid($referer);
            if ($post_id > 0) {
                return $post_id;
            }
        }
        
        // Try global post
        global $post;
        if ($post instanceof WP_Post) {
            return $post->ID;
        }
        
        return null;
    }
    
    /**
     * Get previous terms selected for the current post
     *
     * @param int $post_id Post ID to get previous terms for
     * @param int $limit Maximum number of previous terms to return
     * @return array Array of previous term texts
     */
    private function get_previous_terms($post_id, $limit = 10) {
        global $wpdb;
        
        // Input validation
        if (!$post_id || !is_numeric($post_id)) {
            $this->debug_log('Previous terms lookup: Invalid post ID', array(
                'post_id' => $post_id,
                'type' => gettype($post_id)
            ));
            return array();
        }
        
        $start_time = microtime(true);
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        
        try {
            // Check if table exists before querying
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.tables 
                 WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table_name
            ));
            
            if (!$table_exists) {
                $this->debug_log('Previous terms lookup: Table does not exist', array(
                    'table' => $table_name,
                    'post_id' => $post_id
                ));
                return array();
            }
            
            // Get distinct selected terms for this post, ordered by most recent
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix
            $previous_terms = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT selected_text
                 FROM $table_name
                 WHERE post_id = %d
                 AND selected_text IS NOT NULL
                 AND selected_text != ''
                 ORDER BY first_seen DESC
                 LIMIT %d",
                absint($post_id),
                absint($limit)
            ));
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            
            // Check for database errors
            if ($wpdb->last_error) {
                $this->debug_log('Previous terms lookup: Database error', array(
                    'error' => $wpdb->last_error,
                    'query' => $wpdb->last_query,
                    'post_id' => $post_id
                ));
                return array();
            }
            
            $query_time = (microtime(true) - $start_time) * 1000;
            $result_count = is_array($previous_terms) ? count($previous_terms) : 0;
            
            // Performance monitoring
            $this->debug_log('Previous terms lookup: Query completed', array(
                'post_id' => $post_id,
                'query_time_ms' => round($query_time, 2),
                'result_count' => $result_count,
                'limit' => $limit
            ));
            
            // Performance warning for slow queries
            if ($query_time > 50) {
                $this->debug_log('Previous terms lookup: Slow query detected', array(
                    'query_time_ms' => round($query_time, 2),
                    'threshold_ms' => 50,
                    'post_id' => $post_id
                ));
            }
            
            return is_array($previous_terms) ? $previous_terms : array();
            
        } catch (Exception $e) {
            $query_time = (microtime(true) - $start_time) * 1000;
            
            $this->debug_log('Previous terms lookup: Exception caught', array(
                'error' => $e->getMessage(),
                'post_id' => $post_id,
                'query_time_ms' => round($query_time, 2),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            
            // Return empty array to allow graceful fallback
            return array();
        }
    }
    
    /**
     * Debug all plugin options to identify the issue with logged in vs logged out experience
     */
    private function debug_all_plugin_options() {
        // Skip if WordPress user functions aren't loaded yet
        if (!function_exists('is_user_logged_in') || !function_exists('get_current_user_id')) {
            if (function_exists('explainer_log_debug')) {
                explainer_log_debug('API PROXY DEBUG SKIPPED - WordPress user functions not loaded yet', array(), 'api_proxy');
            }
            return;
        }
        
        $user_id = get_current_user_id();
        $is_logged_in = is_user_logged_in();
        
        // Get all plugin options
        $plugin_options = array(
            'explainer_api_provider' => get_option('explainer_api_provider', 'NOT_SET'),
            'explainer_openai_api_key' => get_option('explainer_openai_api_key', 'NOT_SET'),
            'explainer_claude_api_key' => get_option('explainer_claude_api_key', 'NOT_SET'),
            'explainer_api_model' => get_option('explainer_api_model', 'NOT_SET'),
            'explainer_custom_prompt' => get_option('explainer_custom_prompt', 'NOT_SET'),
            'explainer_language' => get_option('explainer_language', 'NOT_SET'),
            'explainer_tooltip_bg_color' => get_option('explainer_tooltip_bg_color', 'NOT_SET'),
            'explainer_tooltip_text_color' => get_option('explainer_tooltip_text_color', 'NOT_SET'),
            'explainer_toggle_position' => get_option('explainer_toggle_position', 'NOT_SET'),
            'explainer_show_accuracy_disclaimer' => get_option('explainer_show_accuracy_disclaimer', 'NOT_SET'),
            'explainer_show_provider_attribution' => get_option('explainer_show_provider_attribution', 'NOT_SET'),
            'explainer_rate_limit_enabled' => get_option('explainer_rate_limit_enabled', 'NOT_SET'),
            'explainer_rate_limit_logged' => get_option('explainer_rate_limit_logged', 'NOT_SET'),
            'explainer_rate_limit_anonymous' => get_option('explainer_rate_limit_anonymous', 'NOT_SET'),
            'explainer_cache_enabled' => get_option('explainer_cache_enabled', 'NOT_SET'),
            'explainer_cache_duration' => get_option('explainer_cache_duration', 'NOT_SET'),
            'explainer_debug_mode' => get_option('explainer_debug_mode', 'NOT_SET'),
            'explainer_min_selection_length' => get_option('explainer_min_selection_length', 'NOT_SET'),
            'explainer_max_selection_length' => get_option('explainer_max_selection_length', 'NOT_SET'),
            'explainer_min_words' => get_option('explainer_min_words', 'NOT_SET'),
            'explainer_max_words' => get_option('explainer_max_words', 'NOT_SET'),
            'explainer_disabled' => get_option('explainer_disabled', 'NOT_SET'),
            'explainer_disabled_reason' => get_option('explainer_disabled_reason', 'NOT_SET'),
        );
        
        // Log user status and all options
        $this->debug_log('=== PLUGIN OPTIONS DEBUG ===', array(
            'user_id' => $user_id,
            'is_logged_in' => $is_logged_in,
            'current_user_roles' => $is_logged_in ? wp_get_current_user()->roles : 'anonymous',
            'options_count' => count($plugin_options),
            'all_plugin_options' => $plugin_options
        ));
        
        // Also check if the options exist in the database directly
        global $wpdb;
        $option_names = array_keys($plugin_options);
        $placeholders = implode(', ', array_fill(0, count($option_names), '%s'));

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and placeholders are safe
        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders dynamically generated based on array count
        $db_options = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options}
                 WHERE option_name IN ($placeholders)",
                $option_names
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        
        $db_options_formatted = array();
        foreach ($db_options as $option) {
            $db_options_formatted[$option['option_name']] = $option['option_value'];
        }
        
        $this->debug_log('=== DATABASE OPTIONS CHECK ===', array(
            'user_id' => $user_id,
            'is_logged_in' => $is_logged_in,
            'db_options_found' => count($db_options_formatted),
            'db_options' => $db_options_formatted,
            'missing_from_db' => array_diff($option_names, array_keys($db_options_formatted))
        ));
        
        // Compare get_option() vs direct database query
        $comparison = array();
        foreach ($option_names as $option_name) {
            $get_option_value = get_option($option_name, 'DEFAULT_NOT_FOUND');
            $db_value = isset($db_options_formatted[$option_name]) ? $db_options_formatted[$option_name] : 'NOT_IN_DB';
            $matches = ($get_option_value === $db_value);
            
            $comparison[$option_name] = array(
                'get_option' => $get_option_value,
                'db_direct' => $db_value,
                'matches' => $matches
            );
        }
        
        $this->debug_log('=== GET_OPTION VS DATABASE COMPARISON ===', array(
            'user_id' => $user_id,
            'is_logged_in' => $is_logged_in,
            'comparison' => $comparison
        ));
    }
    
    /**
     * Generate mock API response for testing purposes
     * This returns a raw API response that the provider's parse_response method can handle
     * 
     * @param string $selected_text The selected text
     * @param string $prompt The full prompt sent to the API (includes custom template)
     * @param string $provider_name Provider name (OpenAI, Claude, etc.)
     * @param string $model Model name
     * @return array Raw mock API response
     */
    private function generate_mock_api_response($selected_text, $prompt, $provider_name, $model) {
        // Create a mock explanation that includes the custom prompt for testing
        /* translators: 1: selected text preview, 2: provider name, 3: model name, 4: custom prompt evidence */
        $mock_explanation = sprintf(
            __("Mock explanation for: '%1\$s' [Provider: %2\$s, Model: %3\$s] [%4\$s]", 'ai-explainer'),
            substr($selected_text, 0, 30) . (strlen($selected_text) > 30 ? '...' : ''),
            $provider_name,
            $model,
            $this->extract_custom_prompt_evidence($prompt)
        );
        
        // Return raw API response format that providers expect
        if (strtolower($provider_name) === 'openai') {
            // OpenAI API response format
            return array(
                'choices' => array(
                    array(
                        'message' => array(
                            'content' => $mock_explanation
                        ),
                        'finish_reason' => 'stop'
                    )
                ),
                'usage' => array(
                    'total_tokens' => 25,
                    'prompt_tokens' => 15,
                    'completion_tokens' => 10
                ),
                'model' => $model
            );
        } else {
            // Claude API response format
            return array(
                'content' => array(
                    array(
                        'text' => $mock_explanation
                    )
                ),
                'usage' => array(
                    'input_tokens' => 15,
                    'output_tokens' => 10
                ),
                'model' => $model
            );
        }
    }

    /**
     * Generate mock explanation for testing purposes (legacy method)
     * @deprecated Use generate_mock_api_response instead
     */
    private function generate_mock_explanation($selected_text, $prompt, $provider_name, $model) {
        // Create a mock explanation that includes the custom prompt for testing
        /* translators: 1: selected text preview, 2: provider name, 3: model name, 4: custom prompt evidence */
        $mock_explanation = sprintf(
            __("Mock explanation for: '%1\$s' [Provider: %2\$s, Model: %3\$s] [Custom prompt detected: %4\$s]", 'ai-explainer'),
            substr($selected_text, 0, 30) . (strlen($selected_text) > 30 ? '...' : ''),
            $provider_name,
            $model,
            $this->extract_custom_prompt_evidence($prompt)
        );
        
        // Return in the format expected by the provider's parse_response method
        return array(
            'explanation' => $mock_explanation,
            'tokens_used' => 25, // Mock token count
            'model' => $model,
            'provider' => $provider_name,
            'mock_mode' => true
        );
    }
    
    /**
     * Extract evidence that custom prompt was used (for testing verification)
     * 
     * @param string $prompt The full prompt
     * @return string Evidence string showing custom prompt usage
     */
    private function extract_custom_prompt_evidence($prompt) {
        // Get the default prompt to compare
        $default_prompt = get_option('explainer_custom_prompt', ExplainerPlugin_Config::get_default_custom_prompt());
        
        // If prompt contains the default text, mark as default
        if (strpos($prompt, 'Explain this text clearly and concisely') !== false) {
            return 'Default template used';
        }
        
        // Otherwise, show first part of custom prompt for verification
        $prompt_preview = substr($prompt, 0, 50);
        return "Custom template: " . $prompt_preview . (strlen($prompt) > 50 ? '...' : '');
    }
}