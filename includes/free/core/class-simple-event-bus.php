<?php
/**
 * Simple Event Bus System
 * 
 * Minimal event routing system that provides basic functionality
 * without the complexity of the original system.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple Event Bus Class
 * 
 * Provides basic event handling interface for compatibility
 */
class WP_AI_Explainer_Event_Bus {
    
    /**
     * Static instance for singleton pattern
     * 
     * @var WP_AI_Explainer_Event_Bus|null
     */
    private static $instance = null;
    
    /**
     * Stored events for SSE streaming
     * 
     * @var array
     */
    private $events = array();
    
    /**
     * Constructor
     */
    private function __construct() {
        // Basic initialization
    }
    
    /**
     * Get singleton instance
     * 
     * @return WP_AI_Explainer_Event_Bus
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Handle webhook event
     * 
     * @param array $event_payload Event data
     * @return bool Success status
     */
    public function handle_webhook_event($event_payload) {
        // Store the event for SSE streaming using WordPress transients
        $event_id = uniqid();
        $event = array(
            'id' => $event_id,
            'type' => 'job_update',
            'data' => $event_payload,
            'timestamp' => time(),
        );
        
        // Store event in WordPress transient (shareable between processes)
        $queue_key = 'explainer_sse_events';
        $events = get_transient($queue_key) ?: array();
        
        // Add new event
        $events[] = $event;
        
        // Keep only last 50 events to avoid transient size issues
        if (count($events) > 50) {
            $events = array_slice($events, -50);
        }
        
        // Store back with 5 minute expiry
        set_transient($queue_key, $events, 300);
        
        // Log for debugging
        if (ExplainerPlugin_Debug_Logger::is_enabled()) {
            ExplainerPlugin_Debug_Logger::debug('Storing event in transient', 'event_bus', array('event' => $event));
        }
        
        // Trigger WordPress action for any listeners
        do_action('wp_ai_explainer_simple_event', $event_payload);
        
        return true;
    }
    
    /**
     * Get events for a specific topic (for SSE streaming)
     * 
     * @param string $topic Topic name
     * @param string $since Last event ID or timestamp
     * @param int $limit Maximum number of events (default 10)
     * @return array Events data
     * @throws Exception If topic is invalid or events cannot be retrieved
     */
    public function get_events_for_topic($topic, $since = null, $limit = 10) {
        // Validate inputs
        if (empty($topic) || !is_string($topic)) {
            throw new \Exception(esc_html('Invalid topic provided'));
        }
        
        if ($limit <= 0 || $limit > 100) {
            $limit = 10; // Default to safe limit
        }
        
        try {
            // Get events from WordPress transient
            $queue_key = 'explainer_sse_events';
            $all_events = get_transient($queue_key);
            
            if (!is_array($all_events)) {
                $all_events = array();
            }
            
            $filtered_events = array();
            
            // For job queue topic, return relevant job events
            if ($topic === 'wp_ai_explainer_job_queue_list') {
                foreach ($all_events as $event) {
                    if (isset($event['data']['entity']) && $event['data']['entity'] === 'job_queue') {
                        $filtered_events[] = $event;
                    }
                }
            }
            
            // If since is provided, filter events after that timestamp/ID
            if ($since && is_string($since)) {
                $filtered_events = array_filter($filtered_events, function($event) use ($since) {
                    return isset($event['id']) && $event['id'] > $since || 
                           (isset($event['timestamp']) && $event['timestamp'] > strtotime($since));
                });
            }
            
            // Limit results and ensure newest events are returned
            $filtered_events = array_slice($filtered_events, -$limit);
            
            // Log for debugging
            if (ExplainerPlugin_Debug_Logger::is_enabled()) {
                ExplainerPlugin_Debug_Logger::debug('Retrieved ' . count($filtered_events) . ' events for topic ' . $topic, 'event_bus');
            }
            
            return array(
                'events' => $filtered_events,
                'last_event_id' => !empty($filtered_events) ? end($filtered_events)['id'] : null,
            );
            
        } catch (Exception $e) {
            // Log the error
            if (ExplainerPlugin_Debug_Logger::is_enabled()) {
                ExplainerPlugin_Debug_Logger::error('Simple Event Bus error in get_events_for_topic: ' . $e->getMessage(), 'event_bus');
            }
            throw $e;
        }
    }
}