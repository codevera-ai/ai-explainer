<?php
/**
 * Selection tracking functionality
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle selection tracking and data management
 */
class ExplainerPlugin_Selection_Tracker {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Constructor will be called by main plugin
    }
    
    /**
     * Track a text selection
     * 
     * @param string $selected_text The text that was selected
     * @param string $source_url The URL where the selection occurred
     * @param int $post_id The post ID where the selection occurred (0 for global/legacy selections)
     * @param bool $enabled Whether the selection is enabled for display
     * @param string $source The source of the selection ('manual', 'user_selection', 'ai_scan')
     * @param string $reading_level The reading level for the selection
     * @return bool True on success, false on failure
     */
    public function track_selection( $selected_text, $source_url = '', $post_id = 0, $enabled = true, $source = 'user_selection', $reading_level = 'standard' ) {
        if ( empty( $selected_text ) ) {
            return false;
        }
        
        // Generate hash for the text (case-insensitive)
        $text_hash = hash( 'sha256', strtolower( trim( $selected_text ) ) );
        
        // Get current URL if not provided
        if ( empty( $source_url ) ) {
            $source_url = $this->get_current_url();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        
        // Check if this text hash already exists for the specific post and reading level
        // Note: Using direct concatenation for table name as WordPress core does
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE text_hash = %s AND post_id = %d AND reading_level = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $text_hash,
            $post_id,
            $reading_level
        ) );
        
        if ( $existing ) {
            // Update existing record
            $current_urls = json_decode( $existing->source_urls, true ) ?: array();
            
            // Add new URL if not already present
            if ( ! in_array( $source_url, $current_urls, true ) ) {
                $current_urls[] = $source_url;
                // Keep only last 10 URLs to prevent bloat
                if ( count( $current_urls ) > 10 ) {
                    $current_urls = array_slice( $current_urls, -10 );
                }
            }
            
            $result = $wpdb->update(
                $table_name,
                array(
                    'selection_count' => $existing->selection_count + 1,
                    'last_seen' => current_time( 'mysql' ),
                    'source_urls' => wp_json_encode( $current_urls ),
                ),
                array( 'text_hash' => $text_hash ),
                array( '%d', '%s', '%s' ),
                array( '%s' )
            );
            
            // Fire webhook after successful update
            if ( false !== $result && class_exists('ExplainerPlugin_Webhook_Emitter') ) {
                $selection_after = $wpdb->get_row(
                    $wpdb->prepare( "SELECT * FROM {$table_name} WHERE text_hash = %s AND post_id = %d AND reading_level = %s", $text_hash, $post_id, $reading_level ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    ARRAY_A
                );

                $webhook_emitter = ExplainerPlugin_Webhook_Emitter::get_instance();
                $webhook_emitter->emit_after_write(
                    'selection_tracker',
                    $existing->id,
                    'updated',
                    (array) $existing,
                    $selection_after,
                    array( 'selection_count', 'last_seen', 'source_urls' ),
                    get_current_user_id()
                );
            }

            // Invalidate caches after successful update
            if ( false !== $result ) {
                $this->invalidate_selection_caches( $post_id );
            }

            return false !== $result;
        } else {
            // Insert new record
            $result = $wpdb->insert(
                $table_name,
                array(
                    'text_hash' => $text_hash,
                    'selected_text' => sanitize_textarea_field( $selected_text ),
                    'post_id' => $post_id,
                    'enabled' => $enabled ? 1 : 0,
                    'source' => $source,
                    'reading_level' => $reading_level,
                    'selection_count' => 1,
                    'first_seen' => current_time( 'mysql' ),
                    'last_seen' => current_time( 'mysql' ),
                    'source_urls' => wp_json_encode( array( $source_url ) ),
                ),
                array( '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
            );
            
            // Fire webhook after successful insert
            if ( false !== $result && class_exists('ExplainerPlugin_Webhook_Emitter') ) {
                $insert_id = $wpdb->insert_id;
                $selection_after = $wpdb->get_row(
                    $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $insert_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    ARRAY_A
                );

                $webhook_emitter = ExplainerPlugin_Webhook_Emitter::get_instance();
                $webhook_emitter->emit_after_write(
                    'selection_tracker',
                    $insert_id,
                    'created',
                    null,
                    $selection_after,
                    array(),
                    get_current_user_id()
                );
            }

            // Invalidate caches after successful insert
            if ( false !== $result ) {
                $this->invalidate_selection_caches( $post_id );
            }

            return false !== $result;
        }
    }
    
    /**
     * Get popular selections
     *
     * @param int $limit Number of selections to return
     * @param int $min_count Minimum selection count to include
     * @return array Array of selection data
     */
    public function get_popular_selections($limit = 20, $min_count = 2) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';

        // Create cache key based on parameters
        $cache_key = 'popular_selections_' . md5(serialize(array($limit, $min_count)));
        $cached_selections = wp_cache_get($cache_key, 'explainer_selections');

        if (false !== $cached_selections) {
            return $cached_selections;
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} -- phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
             WHERE selection_count >= %d
             ORDER BY selection_count DESC, last_seen DESC
             LIMIT %d",
            $min_count,
            $limit
        ));

        if (!$results) {
            return array();
        }

        // Process results
        $selections = array();
        foreach ($results as $row) {
            $selections[] = array(
                'id' => $row->id,
                'text_hash' => $row->text_hash,
                'selected_text' => $row->selected_text,
                'post_id' => $row->post_id ?? 0,
                'enabled' => (bool)($row->enabled ?? false),
                'source' => $row->source ?? 'user_selection',
                'reading_level' => $row->reading_level ?? 'standard',
                'selection_count' => (int)$row->selection_count,
                'first_seen' => $row->first_seen,
                'last_seen' => $row->last_seen,
                'source_urls' => json_decode($row->source_urls, true) ?: array(),
                'text_preview' => $this->get_text_preview($row->selected_text),
                'ai_explanation' => $row->ai_explanation ?? null,
                'explanation_cached_at' => $row->explanation_cached_at ?? null,
                'manually_edited' => (bool)($row->manually_edited ?? false)
            );
        }

        // Cache for 5 minutes
        wp_cache_set($cache_key, $selections, 'explainer_selections', 300);

        return $selections;
    }

    /**
     * Get popular selections grouped by text_hash with all reading levels
     * This method groups multiple reading levels of the same text into single entries
     *
     * @param int $limit Maximum number of unique texts to return
     * @param int $min_count Minimum selection count for inclusion
     * @return array Array of grouped selection data
     */
    public function get_popular_selections_grouped($limit = 20, $min_count = 2) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';

        // Create cache key based on parameters
        $cache_key = 'popular_grouped_' . md5(serialize(array($limit, $min_count)));
        $cached_selections = wp_cache_get($cache_key, 'explainer_selections');

        if (false !== $cached_selections) {
            return $cached_selections;
        }

        // Get all selections, grouped by text_hash
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT text_hash, selected_text,
                    SUM(selection_count) as total_count,
                    MIN(first_seen) as first_seen,
                    MAX(last_seen) as last_seen,
                    GROUP_CONCAT(DISTINCT reading_level ORDER BY reading_level) as reading_levels,
                    COUNT(DISTINCT reading_level) as reading_level_count
             FROM {$table_name} -- phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
             GROUP BY text_hash, selected_text
             HAVING total_count >= %d
             ORDER BY total_count DESC, last_seen DESC
             LIMIT %d",
            $min_count,
            $limit
        ));
        
        if (!$results) {
            return array();
        }
        
        // For each grouped result, get all individual reading level records
        $grouped_selections = array();
        foreach ($results as $group) {
            // Get all individual records for this text_hash, prioritizing records with explanations
            // Order by reading level descending (expert/technical first, child/simple last)
            $level_records = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_name} -- phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                 WHERE text_hash = %s
                 ORDER BY
                    CASE reading_level
                        WHEN 'expert' THEN 1
                        WHEN 'detailed' THEN 2
                        WHEN 'standard' THEN 3
                        WHEN 'simple' THEN 4
                        WHEN 'child' THEN 5
                        ELSE 6
                    END,
                    CASE
                        WHEN ai_explanation IS NOT NULL AND ai_explanation != '' THEN 0
                        ELSE 1
                    END,
                    selection_count DESC",
                $group->text_hash
            ));
            
            // Combine all source URLs from all reading levels
            $all_source_urls = array();
            $reading_level_data = array();
            
            foreach ($level_records as $record) {
                $source_urls = json_decode($record->source_urls, true) ?: array();
                $all_source_urls = array_merge($all_source_urls, $source_urls);
                
                // Only use this record if we don't already have a better one for this reading level
                if (!isset($reading_level_data[$record->reading_level])) {
                    $reading_level_data[$record->reading_level] = array(
                        'id' => $record->id,
                        'selection_count' => (int)$record->selection_count,
                        'ai_explanation' => $record->ai_explanation,
                        'explanation_cached_at' => $record->explanation_cached_at,
                        'manually_edited' => (bool)($record->manually_edited ?? false),
                        'source_urls' => $source_urls
                    );
                } else {
                    // If we already have this reading level, only replace if this one has an explanation and the existing doesn't
                    $existing = $reading_level_data[$record->reading_level];
                    $existing_has_explanation = !empty($existing['ai_explanation']);
                    $current_has_explanation = !empty($record->ai_explanation);
                    
                    if ($current_has_explanation && !$existing_has_explanation) {
                        $reading_level_data[$record->reading_level] = array(
                            'id' => $record->id,
                            'selection_count' => (int)$record->selection_count,
                            'ai_explanation' => $record->ai_explanation,
                            'explanation_cached_at' => $record->explanation_cached_at,
                            'manually_edited' => (bool)($record->manually_edited ?? false),
                            'source_urls' => $source_urls
                        );
                    }
                }
            }
            
            // Remove duplicates from combined source URLs
            $all_source_urls = array_unique($all_source_urls);
            
            $grouped_selections[] = array(
                'text_hash' => $group->text_hash,
                'selected_text' => $group->selected_text,
                'text_preview' => $this->get_text_preview($group->selected_text),
                'total_selection_count' => (int)$group->total_count,
                'reading_level_count' => (int)$group->reading_level_count,
                'reading_levels' => explode(',', $group->reading_levels),
                'first_seen' => $group->first_seen,
                'last_seen' => $group->last_seen,
                'source_urls' => $all_source_urls,
                'reading_level_data' => $reading_level_data
            );
        }

        // Cache for 5 minutes
        wp_cache_set($cache_key, $grouped_selections, 'explainer_selections', 300);

        return $grouped_selections;
    }
    
    /**
     * Get selections for a specific post
     *
     * @param int $post_id Post ID to get selections for
     * @param bool $enabled_only Whether to only return enabled selections
     * @param int $limit Number of selections to return
     * @return array Array of selection data
     */
    public function get_post_selections($post_id, $enabled_only = true, $limit = 50) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';

        // Create cache key based on parameters
        $cache_key = 'post_selections_' . md5(serialize(array($post_id, $enabled_only, $limit)));
        $cached_selections = wp_cache_get($cache_key, 'explainer_selections');

        if (false !== $cached_selections) {
            return $cached_selections;
        }

        $where_clause = 'WHERE post_id = %d';
        $params = array($post_id);

        if ($enabled_only) {
            $where_clause .= ' AND enabled = 1';
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} -- phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
             $where_clause
             ORDER BY selection_count DESC, last_seen DESC
             LIMIT %d",
            array_merge($params, array($limit))
        ));
        
        if (!$results) {
            return array();
        }
        
        // Process results
        $selections = array();
        foreach ($results as $row) {
            $selections[] = array(
                'id' => $row->id,
                'text_hash' => $row->text_hash,
                'selected_text' => $row->selected_text,
                'post_id' => $row->post_id,
                'enabled' => (bool)$row->enabled,
                'source' => $row->source,
                'reading_level' => $row->reading_level,
                'selection_count' => (int)$row->selection_count,
                'first_seen' => $row->first_seen,
                'last_seen' => $row->last_seen,
                'source_urls' => json_decode($row->source_urls, true) ?: array(),
                'text_preview' => $this->get_text_preview($row->selected_text),
                'ai_explanation' => $row->ai_explanation ?? null,
                'explanation_cached_at' => $row->explanation_cached_at ?? null,
                'manually_edited' => (bool)($row->manually_edited ?? false)
            );
        }

        // Cache for 2 minutes
        wp_cache_set($cache_key, $selections, 'explainer_selections', 120);

        return $selections;
    }
    
    /**
     * Enable or disable a selection for frontend display
     * 
     * @param int $selection_id Selection ID
     * @param bool $enabled Whether to enable the selection
     * @return bool Success
     */
    public function set_selection_enabled($selection_id, $enabled) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        
        // Get selection data before update for webhook
        $selection_before = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $selection_id), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );
        
        $result = $wpdb->update(
            $table_name,
            array('enabled' => $enabled ? 1 : 0),
            array('id' => $selection_id),
            array('%d'),
            array('%d')
        );
        
        // Fire webhook after successful update
        if (false !== $result && $selection_before && class_exists('ExplainerPlugin_Webhook_Emitter')) {
            $selection_after = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $selection_id), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                ARRAY_A
            );
            
            $webhook_emitter = ExplainerPlugin_Webhook_Emitter::get_instance();
            $webhook_emitter->emit_after_write(
                'selection_tracker',
                $selection_id,
                'updated',
                $selection_before,
                $selection_after,
                array('enabled'),
                get_current_user_id()
            );
        }
        
        return false !== $result;
    }
    
    /**
     * Bulk enable/disable selections for a post
     * 
     * @param int $post_id Post ID
     * @param bool $enabled Whether to enable selections
     * @return int Number of affected rows
     */
    public function set_post_selections_enabled($post_id, $enabled) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        
        $result = $wpdb->update(
            $table_name,
            array('enabled' => $enabled ? 1 : 0),
            array('post_id' => $post_id),
            array('%d'),
            array('%d')
        );
        
        // Fire webhook for bulk operation after successful update
        if ($result > 0 && class_exists('ExplainerPlugin_Webhook_Emitter')) {
            $webhook_emitter = ExplainerPlugin_Webhook_Emitter::get_instance();
            $webhook_emitter->emit_after_write(
                'selection_tracker',
                $post_id,
                'bulk',
                null,
                $result, // Number of affected rows
                array('enabled_bulk_update'),
                get_current_user_id()
            );
        }
        
        return $result ?: 0;
    }
    
    /**
     * Get selection statistics
     *
     * @return array Statistics data
     */
    public function get_selection_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';

        // Check cache first
        $cache_key = 'selection_stats';
        $cached_stats = wp_cache_get($cache_key, 'explainer_selections');

        if (false !== $cached_stats) {
            return $cached_stats;
        }

        $stats = array(
            'total_unique_selections' => 0,
            'total_selection_count' => 0,
            'most_popular_text' => '',
            'most_popular_count' => 0,
            'selections_today' => 0,
            'selections_this_week' => 0,
            'selections_this_month' => 0
        );

        // Get basic counts
        $counts = $wpdb->get_row(
            "SELECT COUNT(*) as unique_count, SUM(selection_count) as total_count FROM {$table_name}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
        
        if ($counts) {
            $stats['total_unique_selections'] = (int)$counts->unique_count;
            $stats['total_selection_count'] = (int)$counts->total_count;
        }
        
        // Get most popular selection
        $most_popular = $wpdb->get_row(
            "SELECT selected_text, selection_count FROM {$table_name} ORDER BY selection_count DESC LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
        
        if ($most_popular) {
            $stats['most_popular_text'] = $this->get_text_preview($most_popular->selected_text);
            $stats['most_popular_count'] = (int)$most_popular->selection_count;
        }
        
        // Get time-based statistics
        $today = current_time('Y-m-d');
        $week_ago = wp_date('Y-m-d', strtotime('-7 days', current_time('timestamp')));
        $month_ago = wp_date('Y-m-d', strtotime('-30 days', current_time('timestamp')));
        
        $stats['selections_today'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT SUM(selection_count) FROM {$table_name} WHERE DATE(last_seen) = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $today
        ));

        $stats['selections_this_week'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT SUM(selection_count) FROM {$table_name} WHERE last_seen >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $week_ago
        ));

        $stats['selections_this_month'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT SUM(selection_count) FROM {$table_name} WHERE last_seen >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $month_ago
        ));

        // Cache for 10 minutes
        wp_cache_set($cache_key, $stats, 'explainer_selections', 600);

        return $stats;
    }
    
    /**
     * Clean up old selection data
     * 
     * @param int $days_to_keep Number of days to keep data
     * @return int Number of records deleted
     */
    public function cleanup_old_data($days_to_keep = 90) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        
        $cutoff_date = wp_date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days", current_time('timestamp')));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE last_seen < %s AND selection_count < 2", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $cutoff_date
        ));
        
        // Fire webhook for bulk cleanup operation
        if ($deleted > 0 && class_exists('ExplainerPlugin_Webhook_Emitter')) {
            $webhook_emitter = ExplainerPlugin_Webhook_Emitter::get_instance();
            $webhook_emitter->emit_after_write(
                'selection_tracker',
                0, // No specific selection ID for bulk operations
                'bulk',
                null,
                array(
                    'operation_type' => 'bulk_cleanup',
                    'days_to_keep' => $days_to_keep,
                    'cutoff_date' => $cutoff_date,
                    'affected_rows' => $deleted
                ),
                array('bulk_cleanup_operation'),
                get_current_user_id()
            );
        }
        
        return $deleted ?: 0;
    }
    
    /**
     * Get selection by hash
     * 
     * @param string $hash Text hash
     * @return array|null Selection data or null if not found
     */
    public function get_selection_by_hash($hash) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE text_hash = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $hash
        ));
        
        if (!$result) {
            return null;
        }
        
        return array(
            'id' => $result->id,
            'text_hash' => $result->text_hash,
            'selected_text' => $result->selected_text,
            'post_id' => $result->post_id ?? 0,
            'enabled' => (bool)($result->enabled ?? false),
            'source' => $result->source ?? 'user_selection',
            'reading_level' => $result->reading_level ?? 'standard',
            'selection_count' => (int)$result->selection_count,
            'first_seen' => $result->first_seen,
            'last_seen' => $result->last_seen,
            'source_urls' => json_decode($result->source_urls, true) ?: array(),
            'text_preview' => $this->get_text_preview($result->selected_text),
            'ai_explanation' => $result->ai_explanation ?? null,
            'explanation_cached_at' => $result->explanation_cached_at ?? null,
            'manually_edited' => (bool)($result->manually_edited ?? false)
        );
    }
    
    /**
     * Search selections by text content
     * 
     * @param string $search_term Search term
     * @param int $limit Number of results to return
     * @return array Array of matching selections
     */
    public function search_selections($search_term, $limit = 10) {
        if (empty($search_term)) {
            return array();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} -- phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
             WHERE selected_text LIKE %s
             ORDER BY selection_count DESC, last_seen DESC
             LIMIT %d",
            '%' . $wpdb->esc_like($search_term) . '%',
            $limit
        ));
        
        if (!$results) {
            return array();
        }
        
        $selections = array();
        foreach ($results as $row) {
            $selections[] = array(
                'id' => $row->id,
                'text_hash' => $row->text_hash,
                'selected_text' => $row->selected_text,
                'post_id' => $row->post_id ?? 0,
                'enabled' => (bool)($row->enabled ?? false),
                'source' => $row->source ?? 'user_selection',
                'reading_level' => $row->reading_level ?? 'standard',
                'selection_count' => (int)$row->selection_count,
                'first_seen' => $row->first_seen,
                'last_seen' => $row->last_seen,
                'source_urls' => json_decode($row->source_urls, true) ?: array(),
                'text_preview' => $this->get_text_preview($row->selected_text),
                'ai_explanation' => $row->ai_explanation ?? null,
                'explanation_cached_at' => $row->explanation_cached_at ?? null,
                'manually_edited' => (bool)($row->manually_edited ?? false)
            );
        }
        
        return $selections;
    }
    
    /**
     * Get current URL
     * 
     * @return string Current URL
     */
    private function get_current_url() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Called from API proxy which verifies nonces
        if (isset($_POST['source_url']) && !empty($_POST['source_url'])) {
            return esc_url_raw(wp_unslash($_POST['source_url']));
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        // Fallback to server variables
        $protocol = is_ssl() ? 'https://' : 'http://';
        $host = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));

        return $protocol . $host . $uri;
    }
    
    /**
     * Get text preview (first 100 characters)
     * 
     * @param string $text Full text
     * @return string Text preview
     */
    private function get_text_preview($text) {
        $text = trim($text);
        if (strlen($text) <= 100) {
            return $text;
        }
        
        return substr($text, 0, 97) . '...';
    }
    
    /**
     * Validate text for tracking
     * 
     * @param string $text Text to validate
     * @return bool True if valid for tracking
     */
    private function is_valid_for_tracking($text) {
        $text = trim($text);
        
        // Check minimum length (at least 3 characters)
        if (strlen($text) < 3) {
            return false;
        }
        
        // Check maximum length (no more than 500 characters to prevent abuse)
        if (strlen($text) > 500) {
            return false;
        }
        
        // Check if text contains only whitespace or special characters
        if (!preg_match('/[a-zA-Z0-9]/', $text)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Test method to verify database table exists
     *
     * @return bool True if table exists and is accessible
     */
    public function test_database_connection() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_explainer_selections';

        try {
            $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
            return $result === $table_name;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Invalidate all selection-related caches
     *
     * @param int $post_id Optional post ID to invalidate specific post caches
     * @return void
     */
    private function invalidate_selection_caches( $post_id = 0 ) {
        // Delete all popular selections cache variants
        $cache_keys_to_delete = array(
            'popular_selections_*',
            'popular_grouped_*',
            'selection_stats'
        );

        // WordPress doesn't support wildcard cache deletion, so we'll use a cache group flush
        wp_cache_flush_group( 'explainer_selections' );

        // If specific post ID provided, delete post-specific caches
        if ( $post_id > 0 ) {
            // Note: WordPress cache doesn't support pattern matching,
            // so we rely on the group flush above
        }
    }
}