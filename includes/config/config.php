<?php
/**
 * Global Configuration
 * 
 * Central configuration file for AI Explainer plugin settings
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

return array(
    /**
     * Mock Mode Settings
     * Control testing/development modes across the plugin
     */
    'mock_modes' => array(
        // Enable mock explanations instead of real AI API calls
        'mock_explanations' => false,
        
        // Enable mock content generation instead of real AI API calls  
        'mock_posts' => false,
    ),
    
    /**
     * Development Settings
     * Settings for development and debugging
     */
    'development' => array(
        // Enable detailed debug logging
        'debug_logging' => false,
        
        // Log API requests/responses (sensitive data)
        'log_api_calls' => false,
    ),
    
    /**
     * Debug Logging Configuration
     * Granular control over debug logging sections
     */
    'debug_logging' => array(
        // Global debug logging enable/disable
        'enabled' => false,
        
        // Minimum log level (debug, info, warning, error)
        'min_level' => 'debug',
        
        // Auto-rotate log files when they exceed this size (MB)
        'rotate_size_mb' => 10,
        
        // Debug section controls - each can be independently toggled
        'sections' => array(
            'core' => true,                    // Core plugin functionality
            'event_bus' => true,               // Event bus and webhook system - ENABLED FOR DEBUG  
            'job_queue' => true,               // Job queue processing
            'content_generator' => false,      // Content generation
            'blog_creator' => true,            // Blog creation widget - ENABLED FOR DEBUG
            'api_proxy' => false,              // API proxy requests
            'selection_tracker' => false,     // Text selection tracking
            'admin' => true,                   // Admin panel functionality - ENABLED FOR DEBUG
            'database' => false,               // Database operations
            'background_processor' => true,    // Background job processing - ENABLED FOR DEBUG
            'realtime_adapter' => false,       // Real-time adapter (JavaScript)
            'transport_detector' => false,     // Transport detection (JavaScript)
            'performance' => false,            // Performance metrics
            'security' => false,               // Security operations
            'cron' => false,                   // Cron job execution
            'migration' => false,              // Database migrations
            'config' => false,                 // Configuration loading
            'webhook' => false,                // Webhook emission
            'formatter' => false,              // Data formatting
            'permissions' => false,            // Permission checks
            'validation' => false,             // Input validation
            'cache' => false,                  // Caching operations
            'plugin_init' => false,            // Plugin initialization
            'sse_endpoint' => false,           // SSE endpoint processing
            'sse_permissions' => false,        // SSE permission checks
            'sse_diagnostic' => false,         // SSE diagnostic tests
            'ajax' => true,                    // AJAX requests and responses - ENABLED FOR DEBUG
            'javascript' => true,              // JavaScript console output (captured)
        ),
    ),
    
    /**
     * Performance Settings
     * Cache and performance-related configurations
     */
    'performance' => array(
        // Cache AI responses (in seconds, 0 = disabled)
        'cache_explanations' => 0,
        
        // Maximum concurrent AI requests
        'max_concurrent_requests' => 3,
    ),
    
    /**
     * Feature Flags
     * Enable/disable specific features
     */
    'features' => array(
        // Enable the blog post generator feature
        'blog_generator' => true,
        
        // Enable usage tracking
        'usage_tracking' => true,
        
        // Enable A/B testing for tooltips
        'tooltip_ab_testing' => false,
        
        // Enable internal webhook system for real-time updates
        'webhook_system' => true,
        
        // Enable event bus system for topic-based event routing
        'event_bus' => true,
        
        // Enable database integration for real-time job status updates
        'database_integration' => true,
    ),
    
    /**
     * Real-time System Settings
     * Configuration for webhook and real-time features
     */
    'realtime' => array(
        // Enable webhook firing after database writes
        'webhook_enabled' => true,
        
        // Recursion prevention timeout in seconds
        'webhook_recursion_timeout' => 5,
        
        // Event ID generation strategy
        'webhook_event_id_strategy' => 'uuid',
        
        // Maximum memory per webhook emission in MB
        'webhook_max_memory' => 1,
        
        // Maximum processing time per webhook in milliseconds
        'webhook_max_time' => 10,
        
        // Event Bus Configuration
        'event_bus_max_events_per_topic' => 50,
        'event_bus_retention_time' => 3600, // 1 hour in seconds
        'event_bus_cleanup_interval' => 300, // 5 minutes in seconds
        'event_bus_memory_limit_per_topic' => 1.0, // MB
        'event_bus_batch_size' => 10,
        'event_bus_idle_threshold' => 1800, // 30 minutes in seconds
        
        // SSE Streaming Configuration
        'sse_enabled' => true,
        'sse_session_duration' => 300, // seconds - 5 minutes for job monitoring
        'sse_heartbeat_interval' => 10, // seconds - more frequent heartbeats for better connectivity
        'sse_connection_limit' => 25, // concurrent connections per server
        'sse_poll_interval' => 1, // seconds between event checks
        'sse_memory_limit_per_connection' => 50, // MB
        'sse_buffer_size' => 4096, // bytes
        'sse_nginx_compatibility' => true, // adds X-Accel-Buffering header
    ),
);