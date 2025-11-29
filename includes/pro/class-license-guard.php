<?php
/**
 * License Guard for Pro Features
 *
 * This file only exists in the pro version and enforces license validation
 * for premium features. It will not be included in the free build.
 *
 * @package WP_AI_Explainer
 * @subpackage Pro
 * @since 1.3.29
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * License Guard Class
 *
 * Handles license validation for pro features using Freemius SDK
 */
class ExplainerPlugin_License_Guard
{

    /**
     * Check if pro features can be used
     *
     * TEMPORARY OVERRIDE: Always returns true for free testing period.
     * This allows all pro features to be used without a license to gather
     * user feedback and testing data.
     *
     * To re-enable license checks in the future:
     * 1. Uncomment the original code below
     * 2. Remove the "return true;" line
     * 3. Test license activation flow
     *
     * @return bool True if user can use pro features, false otherwise
     */
    public static function can_use_pro_features()
    {
        // TEMPORARY: Always return true for free testing period
        // This allows all pro features to be used without a license
        // TODO: Re-enable license checks when ready to monetize
        return true;

        // Original license check (commented out for free testing period):
        // if (!function_exists('wpaie_freemius')) {
        //     return false;
        // }
        // return wpaie_freemius()->can_use_premium_code();
    }


    /**
     * Check if license is active (not trial)
     *
     * @return bool True if user has an active paid license
     */
    public static function has_active_license()
    {
        if (!function_exists('wpaie_freemius')) {
            return false;
        }

        return wpaie_freemius()->is_paying();
    }

    /**
     * Check if user is in trial mode
     *
     * @return bool True if user is in trial mode
     */
    public static function is_trial()
    {
        if (!function_exists('wpaie_freemius')) {
            return false;
        }

        return wpaie_freemius()->is_trial();
    }

    /**
     * Get license status message for admin notices
     *
     * @return string|null Message to display, or null if licensed
     */
    public static function get_license_status_message()
    {
        if (!function_exists('wpaie_freemius')) {
            return __('Freemius SDK not loaded. Please contact support.', 'ai-explainer');
        }

        if (self::can_use_pro_features()) {
            return null; // All good, no message needed
        }

        // No valid license or trial
        return sprintf(
            /* translators: %s: URL to account page */
            __('Pro features require a valid license. Please <a href="%s">activate your license</a> to use premium providers and features.', 'ai-explainer'),
            admin_url('admin.php?page=wp-ai-explainer-admin-account')
        );
    }
}
