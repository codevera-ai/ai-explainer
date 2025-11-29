<?php
/**
 * Encryption Service Class
 * 
 * Provides consistent encryption/decryption functionality across all WordPress contexts
 * including admin, frontend, AJAX, cron, and CLI environments.
 * 
 * @package WP_AI_Explainer
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encryption Service Class
 */
class ExplainerPlugin_Encryption_Service {
    
    /**
     * Single instance
     * 
     * @var ExplainerPlugin_Encryption_Service
     */
    private static $instance = null;
    
    /**
     * Encryption salt option name
     * 
     * @var string
     */
    private static $salt_option = 'explainer_encryption_salt';
    
    /**
     * Get singleton instance
     * 
     * @return ExplainerPlugin_Encryption_Service
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor
     */
    private function __construct() {
        // Ensure salt exists on instantiation
        $this->get_encryption_salt();
    }
    
    /**
     * Get or create encryption salt
     * 
     * @return string
     */
    private function get_encryption_salt() {
        $salt = get_option(self::$salt_option, '');
        
        if (empty($salt)) {
            // Generate a cryptographically secure salt
            $salt = $this->generate_secure_salt();
            update_option(self::$salt_option, $salt);
        }
        
        return $salt;
    }
    
    /**
     * Generate a cryptographically secure salt
     * 
     * @return string
     */
    private function generate_secure_salt() {
        // Use WordPress constants and site URL for uniqueness
        $unique_data = ABSPATH . get_site_url() . time() . uniqid('', true);
        
        // Generate random bytes if available
        if (function_exists('random_bytes')) {
            try {
                $random = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $random = md5(microtime(true) . wp_rand());
            }
        } else {
            $random = md5(microtime(true) . wp_rand());
        }
        
        // Combine and hash
        return hash('sha256', $unique_data . $random);
    }
    
    /**
     * Encrypt API key using context-independent method
     * 
     * @param string $api_key The API key to encrypt
     * @return string|false Encrypted data or false on failure
     */
    public function encrypt_api_key($api_key) {
        if (empty($api_key)) {
            return false;
        }
        
        try {
            $salt = $this->get_encryption_salt();
            
            // Use HMAC-SHA256 for consistent hashing across contexts
            $hash = hash_hmac('sha256', $api_key, $salt);
            
            // Combine API key and hash
            $data = $api_key . '|' . $hash;
            
            // Base64 encode for safe storage
            return base64_encode($data);
            
        } catch (Exception $e) {
            if (ExplainerPlugin_Debug_Logger::is_enabled()) {
                ExplainerPlugin_Debug_Logger::error('Failed to encrypt API key - ' . $e->getMessage(), 'security');
            }
            return false;
        }
    }
    
    /**
     * Decrypt API key using context-independent method
     * 
     * @param string $encrypted_key The encrypted API key
     * @param bool $verbose Enable verbose error reporting
     * @return string|false Decrypted API key or false on failure
     */
    public function decrypt_api_key($encrypted_key, $verbose = false) {
        if (empty($encrypted_key)) {
            if ($verbose && ExplainerPlugin_Debug_Logger::is_enabled()) {
                ExplainerPlugin_Debug_Logger::warning('Empty encrypted key provided', 'security');
            }
            return false;
        }
        
        try {
            // Base64 decode
            $decoded = base64_decode($encrypted_key, true);
            if ($decoded === false) {
                if ($verbose && ExplainerPlugin_Debug_Logger::is_enabled()) {
                    ExplainerPlugin_Debug_Logger::warning('Base64 decode failed', 'security');
                }
                return false;
            }
            
            // Split into API key and hash
            $parts = explode('|', $decoded);
            if (count($parts) !== 2) {
                if ($verbose && ExplainerPlugin_Debug_Logger::is_enabled()) {
                    ExplainerPlugin_Debug_Logger::warning('Invalid encrypted data format', 'security');
                }
                return false;
            }
            
            $api_key = $parts[0];
            $stored_hash = $parts[1];
            
            // Validate API key format
            if (!$this->is_valid_api_key_format($api_key)) {
                if ($verbose && ExplainerPlugin_Debug_Logger::is_enabled()) {
                    ExplainerPlugin_Debug_Logger::warning('Invalid API key format', 'security');
                }
                return false;
            }
            
            // Get salt and verify hash using HMAC-SHA256
            $salt = $this->get_encryption_salt();
            $expected_hash = hash_hmac('sha256', $api_key, $salt);
            
            // Use hash_equals for timing-safe comparison
            if (!hash_equals($expected_hash, $stored_hash)) {
                if ($verbose && ExplainerPlugin_Debug_Logger::is_enabled()) {
                    ExplainerPlugin_Debug_Logger::warning('Hash verification failed', 'security', array(
                        'expected' => $expected_hash,
                        'stored' => $stored_hash,
                        'salt_length' => strlen($salt)
                    ));
                }
                return false;
            }
            
            return $api_key;
            
        } catch (Exception $e) {
            if ($verbose && ExplainerPlugin_Debug_Logger::is_enabled()) {
                ExplainerPlugin_Debug_Logger::error('Exception during decryption - ' . $e->getMessage(), 'security');
            }
            return false;
        }
    }
    
    /**
     * Validate API key format
     * 
     * @param string $api_key The API key to validate
     * @return bool True if valid format
     */
    private function is_valid_api_key_format($api_key) {
        if (empty($api_key)) {
            return false;
        }
        
        // OpenAI API keys start with "sk-" and are typically 51+ characters
        if (strpos($api_key, 'sk-') === 0 && strlen($api_key) >= 20) {
            return true;
        }
        
        // Claude API keys start with "sk-ant-" and are typically longer
        if (strpos($api_key, 'sk-ant-') === 0 && strlen($api_key) >= 30) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Migrate existing encrypted data from old format
     * 
     * @param string $old_encrypted_key Encrypted key using wp_hash
     * @param string $api_key Plain text API key (if available)
     * @return string|false New encrypted format or false on failure
     */
    public function migrate_from_wp_hash($old_encrypted_key, $api_key = null) {
        // If we have the plain text key, simply re-encrypt it
        if (!empty($api_key)) {
            return $this->encrypt_api_key($api_key);
        }
        
        // Try to decrypt using the old wp_hash method first
        $decrypted = $this->decrypt_wp_hash_format($old_encrypted_key);
        if ($decrypted !== false) {
            // Re-encrypt using new method
            return $this->encrypt_api_key($decrypted);
        }
        
        return false;
    }
    
    /**
     * Attempt to decrypt using old wp_hash format
     * 
     * @param string $encrypted_key Old format encrypted key
     * @return string|false Decrypted key or false
     */
    private function decrypt_wp_hash_format($encrypted_key) {
        try {
            $decoded = base64_decode($encrypted_key, true);
            if ($decoded === false) {
                return false;
            }
            
            $parts = explode('|', $decoded);
            if (count($parts) !== 2) {
                return false;
            }
            
            $api_key = $parts[0];
            $stored_hash = $parts[1];
            
            // Try to verify using wp_hash (may fail in CLI context)
            $salt = $this->get_encryption_salt();
            $expected_hash = wp_hash($api_key . $salt);
            
            if (hash_equals($expected_hash, $stored_hash)) {
                return $api_key;
            }
            
            return false;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Test encryption/decryption functionality
     * 
     * @return array Test results
     */
    public function test_encryption() {
        $test_key = 'sk-test1234567890abcdefghijklmnopqrstuvwxyz123456';
        $results = array();
        
        // Test encryption
        $encrypted = $this->encrypt_api_key($test_key);
        $results['encryption'] = $encrypted !== false;
        
        // Test decryption
        if ($encrypted) {
            $decrypted = $this->decrypt_api_key($encrypted);
            $results['decryption'] = $decrypted === $test_key;
        } else {
            $results['decryption'] = false;
        }
        
        // Test round trip
        $results['round_trip'] = $results['encryption'] && $results['decryption'];
        
        return $results;
    }
}