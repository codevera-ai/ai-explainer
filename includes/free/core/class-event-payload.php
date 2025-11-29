<?php
/**
 * Event Payload Builder
 * 
 * Builds and sanitises event payloads for webhook system
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Event Payload Builder Class
 * 
 * Handles construction of event payloads with proper sanitisation,
 * security filtering, and capability checking.
 */
class ExplainerPlugin_Event_Payload {
    
    /**
     * Configuration instance
     * 
     * @var ExplainerPlugin_Config
     */
    private $config;
    
    /**
     * Sensitive data fields to exclude from payloads
     * 
     * @var array
     */
    private $sensitive_fields = array(
        'api_key',
        'password',
        'secret',
        'token',
        'private_key',
        'auth_key',
        'encryption_key',
        'hash',
        'salt',
        'nonce',
        'session_id',
        'cookie'
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->config = new ExplainerPlugin_Config();
    }
    
    /**
     * Build event payload
     * 
     * @param string $entity Entity type
     * @param int $id Primary key
     * @param string $type Operation type
     * @param array|null $row_before Previous state
     * @param array|null $row_after New state
     * @param array $changed_columns Changed column names
     * @param int|null $actor User ID
     * @return array|null Event payload or null on failure
     */
    public function build_payload($entity, $id, $type, $row_before = null, $row_after = null, $changed_columns = array(), $actor = null) {
        try {
            // Validate input parameters
            if (empty($entity) || empty($id) || empty($type)) {
                $this->log_error('Invalid payload parameters', array(
                    'entity' => $entity,
                    'id' => $id,
                    'type' => $type
                ));
                return null;
            }
            
            // Generate unique event ID
            $event_id = $this->generate_event_id();
            
            // Build base payload
            $payload = array(
                'entity' => sanitize_text_field($entity),
                'id' => intval($id),
                'type' => sanitize_text_field($type),
                'occurred_at' => gmdate('Y-m-d\TH:i:s\Z'), // UTC timestamp
                'event_id' => $event_id,
                'actor' => $actor ? intval($actor) : null
            );
            
            // Add row data based on operation type
            switch ($type) {
                case 'created':
                    $payload['row_after'] = $this->sanitise_row_data($row_after);
                    break;
                    
                case 'updated':
                    $payload['row_before'] = $this->sanitise_row_data($row_before);
                    $payload['row_after'] = $this->sanitise_row_data($row_after);
                    $payload['changed_columns'] = $this->sanitise_changed_columns($changed_columns);
                    break;
                    
                case 'deleted':
                    $payload['row_before'] = $this->sanitise_row_data($row_before);
                    break;
                    
                case 'bulk':
                    // For bulk operations, we might not have individual row data
                    if ($row_after !== null) {
                        $payload['affected_count'] = intval($row_after);
                    }
                    if ($changed_columns) {
                        $payload['operation_details'] = $this->sanitise_bulk_details($changed_columns);
                    }
                    break;
            }
            
            // Check user permissions for payload data
            $payload = $this->filter_payload_by_permissions($payload, $actor);
            
            $this->log_debug('Event payload built successfully', array(
                'entity' => $entity,
                'id' => $id,
                'type' => $type,
                'event_id' => $event_id,
                'payload_size' => strlen(wp_json_encode($payload))
            ));
            
            return $payload;
            
        } catch (Exception $e) {
            $this->log_error('Event payload build exception', array(
                'entity' => $entity,
                'id' => $id,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            
            return null;
        }
    }
    
    /**
     * Generate unique event ID
     * 
     * @return string Unique event ID
     */
    private function generate_event_id() {
        $strategy = $this->config->get_realtime_setting('webhook_event_id_strategy', 'uuid');
        
        switch ($strategy) {
            case 'uuid':
                return $this->generate_uuid();
                
            case 'timestamp':
                return 'evt_' . time() . '_' . wp_rand(1000, 9999);
                
            case 'hash':
                return 'evt_' . hash('sha256', uniqid('', true) . wp_rand());
                
            default:
                return $this->generate_uuid();
        }
    }
    
    /**
     * Generate UUID v4
     * 
     * @return string UUID
     */
    private function generate_uuid() {
        // Generate 16 random bytes
        if (function_exists('random_bytes')) {
            try {
                $data = random_bytes(16);
            } catch (Exception $e) {
                // Fallback to wp_generate_password if random_bytes fails
                $data = wp_generate_password(16, false);
                $data = substr($data, 0, 16);
            }
        } else {
            // Fallback for older PHP versions
            $data = wp_generate_password(16, false);
            $data = substr($data, 0, 16);
        }
        
        // Ensure we have exactly 16 bytes
        if (strlen($data) !== 16) {
            $data = str_pad($data, 16, "\0");
        }
        
        // Set version (4) and variant (2) bits
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant 2
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Sanitise row data and remove sensitive fields
     * 
     * @param array|null $row_data Row data
     * @return array|null Sanitised row data
     */
    private function sanitise_row_data($row_data) {
        if ($row_data === null || !is_array($row_data)) {
            return null;
        }
        
        $sanitised = array();
        
        foreach ($row_data as $key => $value) {
            // Skip sensitive fields
            if ($this->is_sensitive_field($key)) {
                continue;
            }
            
            // Sanitise key
            $clean_key = sanitize_key($key);
            
            // Sanitise value based on type
            $clean_value = $this->sanitise_field_value($value);
            
            $sanitised[$clean_key] = $clean_value;
        }
        
        return $sanitised;
    }
    
    /**
     * Check if field contains sensitive data
     * 
     * @param string $field_name Field name
     * @return bool True if sensitive
     */
    private function is_sensitive_field($field_name) {
        $field_lower = strtolower($field_name);
        
        foreach ($this->sensitive_fields as $sensitive) {
            if (strpos($field_lower, $sensitive) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Sanitise field value based on type
     * 
     * @param mixed $value Field value
     * @return mixed Sanitised value
     */
    private function sanitise_field_value($value) {
        if ($value === null) {
            return null;
        }
        
        if (is_string($value)) {
            // Limit string length to prevent payload bloat
            if (strlen($value) > 1000) {
                return substr(sanitize_textarea_field($value), 0, 997) . '...';
            }
            return sanitize_textarea_field($value);
        }
        
        if (is_numeric($value)) {
            return is_float($value) ? floatval($value) : intval($value);
        }
        
        if (is_bool($value)) {
            return (bool) $value;
        }
        
        if (is_array($value)) {
            // Recursively sanitise arrays (with depth limit)
            return $this->sanitise_array_recursive($value, 0, 3);
        }
        
        // For other types, convert to string and sanitise
        return sanitize_textarea_field(strval($value));
    }
    
    /**
     * Recursively sanitise array with depth limit
     * 
     * @param array $array Array to sanitise
     * @param int $current_depth Current recursion depth
     * @param int $max_depth Maximum allowed depth
     * @return array|string Sanitised array or truncated indicator
     */
    private function sanitise_array_recursive($array, $current_depth, $max_depth) {
        if ($current_depth >= $max_depth) {
            return '[Array too deep]';
        }
        
        $sanitised = array();
        $count = 0;
        
        foreach ($array as $key => $value) {
            // Limit array size to prevent payload bloat
            if ($count >= 50) {
                $sanitised['...'] = '[Array truncated]';
                break;
            }
            
            $clean_key = is_string($key) ? sanitize_key($key) : intval($key);
            $clean_value = is_array($value) ? 
                $this->sanitise_array_recursive($value, $current_depth + 1, $max_depth) :
                $this->sanitise_field_value($value);
            
            $sanitised[$clean_key] = $clean_value;
            $count++;
        }
        
        return $sanitised;
    }
    
    /**
     * Sanitise changed columns array
     * 
     * @param array $changed_columns Changed column names
     * @return array Sanitised column names
     */
    private function sanitise_changed_columns($changed_columns) {
        if (!is_array($changed_columns)) {
            return array();
        }
        
        $sanitised = array();
        
        foreach ($changed_columns as $column) {
            if (is_string($column) && !$this->is_sensitive_field($column)) {
                $sanitised[] = sanitize_key($column);
            }
        }
        
        return array_unique($sanitised);
    }
    
    /**
     * Sanitise bulk operation details
     * 
     * @param mixed $details Bulk operation details
     * @return array Sanitised details
     */
    private function sanitise_bulk_details($details) {
        if (is_array($details)) {
            return $this->sanitise_array_recursive($details, 0, 2);
        }
        
        if (is_string($details)) {
            return sanitize_textarea_field($details);
        }
        
        return array('details' => strval($details));
    }
    
    /**
     * Filter payload based on user permissions
     * 
     * @param array $payload Event payload
     * @param int|null $actor User ID
     * @return array Filtered payload
     */
    private function filter_payload_by_permissions($payload, $actor) {
        // If no actor or actor is admin, return full payload
        if (!$actor || user_can($actor, 'manage_options')) {
            return $payload;
        }
        
        // For non-admin users, filter sensitive entity data
        $filtered = $payload;
        
        // Remove detailed row data for non-admin users
        if (isset($filtered['row_before'])) {
            $filtered['row_before'] = $this->filter_row_for_user($filtered['row_before'], $actor);
        }
        
        if (isset($filtered['row_after'])) {
            $filtered['row_after'] = $this->filter_row_for_user($filtered['row_after'], $actor);
        }
        
        return $filtered;
    }
    
    /**
     * Filter row data for specific user permissions
     * 
     * @param array|null $row_data Row data
     * @param int $user_id User ID
     * @return array|null Filtered row data
     */
    private function filter_row_for_user($row_data, $user_id) {
        if (!is_array($row_data)) {
            return $row_data;
        }
        
        // For non-admin users, only include basic fields
        $allowed_fields = array(
            'id',
            'status',
            'created_at',
            'updated_at',
            'type',
            'title',
            'name'
        );
        
        $filtered = array();
        
        foreach ($row_data as $key => $value) {
            if (in_array($key, $allowed_fields, true)) {
                $filtered[$key] = $value;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Validate payload structure
     * 
     * @param array $payload Event payload
     * @return bool True if valid
     */
    public function validate_payload($payload) {
        if (!is_array($payload)) {
            return false;
        }
        
        $required_fields = array('entity', 'id', 'type', 'occurred_at', 'event_id');
        
        foreach ($required_fields as $field) {
            if (!isset($payload[$field]) || empty($payload[$field])) {
                return false;
            }
        }
        
        // Validate field types
        if (!is_string($payload['entity']) || !is_numeric($payload['id']) || !is_string($payload['type'])) {
            return false;
        }
        
        // Validate operation type
        $valid_types = array('created', 'updated', 'deleted', 'bulk');
        if (!in_array($payload['type'], $valid_types, true)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get payload size in bytes
     * 
     * @param array $payload Event payload
     * @return int Size in bytes
     */
    public function get_payload_size($payload) {
        return strlen(wp_json_encode($payload));
    }
    
    /**
     * Validate input parameters before payload building
     * 
     * @param string $entity Entity type
     * @param mixed $id Primary key
     * @param string $type Operation type
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validate_input_parameters($entity, $id, $type) {
        $errors = array();
        
        // Validate entity
        if (empty($entity) || !is_string($entity)) {
            $errors[] = 'Entity must be a non-empty string';
        } elseif (strlen($entity) > 50) {
            $errors[] = 'Entity name too long (max 50 characters)';
        } elseif (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $entity)) {
            $errors[] = 'Entity name contains invalid characters';
        }
        
        // Validate ID
        if (empty($id) && $id !== 0) {
            $errors[] = 'ID cannot be empty';
        } elseif (!is_numeric($id)) {
            $errors[] = 'ID must be numeric';
        } elseif (intval($id) <= 0) {
            $errors[] = 'ID must be a positive integer';
        }
        
        // Validate type
        $valid_types = array('created', 'updated', 'deleted', 'bulk');
        if (empty($type) || !is_string($type)) {
            $errors[] = 'Type must be a non-empty string';
        } elseif (!in_array($type, $valid_types, true)) {
            $errors[] = 'Type must be one of: ' . implode(', ', $valid_types);
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
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
            explainer_log_debug($message, $context, 'Event_Payload');
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
            explainer_log_error($message, $context, 'Event_Payload');
        } else if (ExplainerPlugin_Debug_Logger::is_enabled()) {
            ExplainerPlugin_Debug_Logger::error($message, 'event_bus', $context);
        }
    }
}