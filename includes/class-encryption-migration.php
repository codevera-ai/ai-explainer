<?php
/**
 * Encryption Migration Helper
 * 
 * Migrates API keys from wp_hash format to new HMAC-SHA256 format
 * 
 * @package WP_AI_Explainer
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encryption Migration Class
 */
class ExplainerPlugin_Encryption_Migration {
    
    /**
     * Migration completed option key
     */
    private static $migration_option = 'explainer_encryption_migration_completed';
    
    /**
     * Check if migration is needed and run it
     * 
     * @return bool True if migration was run or not needed
     */
    public static function maybe_migrate() {
        // Check if migration already completed
        if (get_option(self::$migration_option, false)) {
            return true;
        }
        
        $migration_needed = false;
        $results = array();
        
        // Check OpenAI API key
        $openai_key = get_option('explainer_openai_api_key', '');
        if (!empty($openai_key)) {
            $result = self::migrate_api_key('explainer_openai_api_key', $openai_key);
            $results['openai'] = $result;
            if ($result['migrated']) {
                $migration_needed = true;
            }
        }
        
        // Check Claude API key
        $claude_key = get_option('explainer_claude_api_key', '');
        if (!empty($claude_key)) {
            $result = self::migrate_api_key('explainer_claude_api_key', $claude_key);
            $results['claude'] = $result;
            if ($result['migrated']) {
                $migration_needed = true;
            }
        }

        // Log results
        if ($migration_needed) {
            if (function_exists('explainer_log_info')) {
                explainer_log_info('API key encryption migration completed', $results, 'encryption_migration');
            }
        }
        
        // Mark migration as completed
        update_option(self::$migration_option, true);
        
        return true;
    }
    
    /**
     * Migrate a specific API key
     * 
     * @param string $option_name WordPress option name
     * @param string $encrypted_key Current encrypted key
     * @return array Migration result
     */
    private static function migrate_api_key($option_name, $encrypted_key) {
        $result = array(
            'option' => $option_name,
            'migrated' => false,
            'error' => null,
            'new_format' => false
        );
        
        try {
            $encryption_service = ExplainerPlugin_Encryption_Service::get_instance();
            
            // Try to decrypt with new service first
            $decrypted = $encryption_service->decrypt_api_key($encrypted_key);
            
            if (!empty($decrypted)) {
                // Already in new format
                $result['new_format'] = true;
                return $result;
            }
            
            // Try migration from old format
            $migrated_key = $encryption_service->migrate_from_wp_hash($encrypted_key);
            
            if ($migrated_key !== false) {
                // Verify the migrated key works
                $test_decrypt = $encryption_service->decrypt_api_key($migrated_key);
                
                if (!empty($test_decrypt)) {
                    // Update the option with new encrypted format
                    update_option($option_name, $migrated_key);
                    $result['migrated'] = true;

                    // Log successful migration
                    if (ExplainerPlugin_Debug_Logger::is_enabled()) {
                        ExplainerPlugin_Debug_Logger::info("Successfully migrated {$option_name}", 'migration');
                    }
                } else {
                    $result['error'] = 'Migration verification failed';
                }
            } else {
                $result['error'] = 'Migration failed - could not decrypt old format';
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            if (ExplainerPlugin_Debug_Logger::is_enabled()) {
                ExplainerPlugin_Debug_Logger::error("Error migrating {$option_name} - " . $e->getMessage(), 'migration');
            }
        }
        
        return $result;
    }
    
    /**
     * Force re-migration (for debugging)
     * 
     * @return array Migration results
     */
    public static function force_migrate() {
        // Clear migration flag
        delete_option(self::$migration_option);
        
        // Run migration
        return self::maybe_migrate();
    }
    
    /**
     * Check if API keys need re-encryption from admin interface
     * 
     * @return array Status information
     */
    public static function check_encryption_status() {
        $status = array(
            'migration_completed' => get_option(self::$migration_option, false),
            'keys_status' => array()
        );
        
        $encryption_service = ExplainerPlugin_Encryption_Service::get_instance();
        
        // Check each API key
        $keys_to_check = array(
            'openai' => get_option('explainer_openai_api_key', ''),
            'claude' => get_option('explainer_claude_api_key', '')
        );
        
        foreach ($keys_to_check as $provider => $encrypted_key) {
            if (empty($encrypted_key)) {
                $status['keys_status'][$provider] = 'not_set';
                continue;
            }
            
            // Try to decrypt
            $decrypted = $encryption_service->decrypt_api_key($encrypted_key);
            
            if (!empty($decrypted)) {
                $status['keys_status'][$provider] = 'working';
            } else {
                $status['keys_status'][$provider] = 'failed';
            }
        }
        
        return $status;
    }
}