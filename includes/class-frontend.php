<?php
/**
 * Frontend functionality for AI Explainer Plugin
 *
 * Handles meta tag output to WordPress head section following Yoast SEO approach
 *
 * @package WP_AI_Explainer
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend class for handling meta tag output
 */
class WP_AI_Explainer_Frontend {

    /**
     * Prefix for all plugin meta values in the database
     *
     * @var string
     */
    public static $meta_prefix = '_wp_ai_explainer_';

    /**
     * Constructor - register hooks
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Add meta tags to head with early priority (following Yoast approach)
        add_action('wp_head', array($this, 'output_meta_tags'), 1);
    }

    /**
     * Output meta tags to WordPress head section
     *
     * @return void
     */
    public function output_meta_tags() {
        // Only output on frontend
        if (is_admin()) {
            return;
        }

        // Get current post
        global $post;
        if (!$post instanceof WP_Post) {
            return;
        }

        // Check if we should output meta tags for this post
        if (!$this->should_output_meta_tags($post)) {
            return;
        }

        // Generate and output meta tags
        $meta_tags = $this->generate_meta_tags($post);
        if (!empty($meta_tags)) {
            echo $meta_tags; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Meta tags are escaped in generate_meta_tags()
        }
    }

    /**
     * Check if we should output meta tags for this post
     *
     * @param WP_Post $post Current post object
     * @return bool
     */
    private function should_output_meta_tags($post) {
        // Only output on single posts/pages for now
        if (!is_singular()) {
            return false;
        }

        // Check if plugin is enabled
        $enabled = get_option('wp_ai_explainer_enabled', true);
        if (!$enabled) {
            return false;
        }

        // Check if we have AI explanation data for this post
        $has_explanations = $this->has_ai_explanations($post->ID);
        
        return $has_explanations;
    }

    /**
     * Check if post has AI explanations stored
     *
     * @param int $post_id Post ID
     * @return bool
     */
    private function has_ai_explanations($post_id) {
        // Check for cached explanations or explanation metadata
        $explanations_count = $this->get_meta($post_id, 'explanations_count');
        $has_cache = $this->get_meta($post_id, 'has_cached_explanations');
        
        return !empty($explanations_count) || !empty($has_cache);
    }

    /**
     * Generate meta tags for the current post
     *
     * @param WP_Post $post Current post object
     * @return string Generated meta tags HTML
     */
    private function generate_meta_tags($post) {
        $meta_tags = '';

        // AI Explainer availability meta tag
        $meta_tags .= '<meta name="ai-explainer-available" content="true" />' . "\n";

        // AI provider information
        $provider = get_option('wp_ai_explainer_provider', 'openai');
        $meta_tags .= '<meta name="ai-explainer-provider" content="' . esc_attr($provider) . '" />' . "\n";

        // Explanation count if available
        $explanations_count = $this->get_meta($post->ID, 'explanations_count');
        if (!empty($explanations_count)) {
            $meta_tags .= '<meta name="ai-explainer-count" content="' . esc_attr($explanations_count) . '" />' . "\n";
        }

        // Content analysis meta
        $content_analyzed = $this->get_meta($post->ID, 'content_analyzed');
        if (!empty($content_analyzed)) {
            $meta_tags .= '<meta name="ai-explainer-analyzed" content="' . esc_attr($content_analyzed) . '" />' . "\n";
        }

        // Last updated timestamp
        $last_updated = $this->get_meta($post->ID, 'last_explanation_time');
        if (!empty($last_updated)) {
            $meta_tags .= '<meta name="ai-explainer-updated" content="' . esc_attr($last_updated) . '" />' . "\n";
        }

        // Plugin version
        if (defined('WP_AI_EXPLAINER_VERSION')) {
            $meta_tags .= '<meta name="ai-explainer-version" content="' . esc_attr(WP_AI_EXPLAINER_VERSION) . '" />' . "\n";
        }

        return $meta_tags;
    }

    /**
     * Get meta value for post (following Yoast pattern)
     *
     * @param int    $post_id Post ID
     * @param string $key     Meta key (without prefix)
     * @return string Meta value or empty string
     */
    public static function get_meta($post_id, $key) {
        $meta_value = get_post_meta($post_id, self::$meta_prefix . $key, true);
        return is_string($meta_value) ? $meta_value : '';
    }

    /**
     * Update meta value for post (following Yoast pattern)
     *
     * @param int    $post_id Post ID
     * @param string $key     Meta key (without prefix)
     * @param mixed  $value   Meta value
     * @return bool|int Meta ID on success, false on failure
     */
    public static function update_meta($post_id, $key, $value) {
        // Sanitize value based on type
        if (is_string($value)) {
            $value = sanitize_text_field($value);
        } elseif (is_array($value) || is_object($value)) {
            $value = serialize($value);
        }

        return update_post_meta($post_id, self::$meta_prefix . $key, $value);
    }

    /**
     * Delete meta value for post
     *
     * @param int    $post_id Post ID
     * @param string $key     Meta key (without prefix)
     * @return bool True on success, false on failure
     */
    public static function delete_meta($post_id, $key) {
        return delete_post_meta($post_id, self::$meta_prefix . $key);
    }

    /**
     * Track explanation usage (called when explanation is generated)
     *
     * @param int    $post_id Post ID
     * @param string $text    Explained text
     * @param string $explanation Generated explanation
     * @return void
     */
    public static function track_explanation($post_id, $text, $explanation) {
        // Update explanation count
        $current_count = (int) self::get_meta($post_id, 'explanations_count');
        self::update_meta($post_id, 'explanations_count', $current_count + 1);

        // Update last explanation time
        self::update_meta($post_id, 'last_explanation_time', current_time('mysql'));

        // Mark content as analyzed
        self::update_meta($post_id, 'content_analyzed', 'true');

        // Store cached explanations flag
        self::update_meta($post_id, 'has_cached_explanations', 'true');
    }

    /**
     * Clear explanation metadata for post
     *
     * @param int $post_id Post ID
     * @return void
     */
    public static function clear_explanation_meta($post_id) {
        self::delete_meta($post_id, 'explanations_count');
        self::delete_meta($post_id, 'last_explanation_time');
        self::delete_meta($post_id, 'content_analyzed');
        self::delete_meta($post_id, 'has_cached_explanations');
    }
}