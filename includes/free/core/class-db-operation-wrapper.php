<?php
/**
 * Database Operation Wrapper
 * 
 * Provides transaction-aware wrapper for database operations with webhook firing.
 * 
 * @package WP_AI_Explainer
 * @subpackage Database
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Operation Wrapper Class
 * 
 * Wraps WordPress database operations to provide transaction awareness
 * and consistent webhook firing after successful operations.
 */
class ExplainerPlugin_DB_Operation_Wrapper {
    
    /**
     * WordPress database instance
     * 
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Transaction depth counter
     * 
     * @var int
     */
    private $transaction_depth = 0;
    
    /**
     * Pending webhook operations
     * 
     * @var array
     */
    private $pending_webhooks = array();
    
    /**
     * Configuration instance
     * 
     * @var ExplainerPlugin_Config
     */
    private $config;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->config = new ExplainerPlugin_Config();
    }
    
    /**
     * Begin database transaction
     * 
     * @return bool True if transaction started successfully
     */
    public function begin_transaction() {
        if ($this->transaction_depth === 0) {
            $result = $this->wpdb->query('START TRANSACTION');
            if ($result === false) {
                $this->log_error('Failed to start database transaction');
                return false;
            }
        }
        
        $this->transaction_depth++;
        $this->log_debug('Database transaction started', array(
            'depth' => $this->transaction_depth
        ));
        
        return true;
    }
    
    /**
     * Commit database transaction and fire pending webhooks
     * 
     * @return bool True if commit successful
     */
    public function commit_transaction() {
        if ($this->transaction_depth <= 0) {
            $this->log_warning('Attempted to commit transaction with no active transaction');
            return false;
        }
        
        $this->transaction_depth--;
        
        if ($this->transaction_depth === 0) {
            $result = $this->wpdb->query('COMMIT');
            if ($result === false) {
                $this->log_error('Failed to commit database transaction');
                $this->clear_pending_webhooks();
                return false;
            }
            
            // Fire all pending webhooks after successful commit
            $this->fire_pending_webhooks();
            
            $this->log_debug('Database transaction committed successfully');
        }
        
        return true;
    }
    
    /**
     * Rollback database transaction and clear pending webhooks
     * 
     * @return bool True if rollback successful
     */
    public function rollback_transaction() {
        if ($this->transaction_depth <= 0) {
            $this->log_warning('Attempted to rollback transaction with no active transaction');
            return false;
        }
        
        $result = $this->wpdb->query('ROLLBACK');
        if ($result === false) {
            $this->log_error('Failed to rollback database transaction');
        }
        
        // Clear pending webhooks since transaction was rolled back
        $this->clear_pending_webhooks();
        $this->transaction_depth = 0;
        
        $this->log_debug('Database transaction rolled back', array(
            'cleared_webhooks' => count($this->pending_webhooks)
        ));
        
        return $result !== false;
    }
    
    /**
     * Perform database insert with webhook firing
     * 
     * @param string $table Table name
     * @param array $data Data to insert
     * @param array $format Data format
     * @param string $entity Entity type for webhook
     * @param array $webhook_data Additional webhook data
     * @return int|false Insert ID on success, false on failure
     */
    public function insert_with_webhook($table, $data, $format, $entity, $webhook_data = array()) {
        $result = $this->wpdb->insert($table, $data, $format);
        
        if ($result === false) {
            $this->log_error('Database insert failed', array(
                'table' => $table,
                'error' => $this->wpdb->last_error
            ));
            return false;
        }
        
        $insert_id = $this->wpdb->insert_id;
        
        // Queue webhook for firing
        $this->queue_webhook(
            $entity,
            $insert_id,
            'created',
            null, // No previous state for inserts
            array_merge($data, array('id' => $insert_id)),
            array(),
            $webhook_data['actor'] ?? null
        );
        
        $this->log_debug('Database insert successful with webhook queued', array(
            'table' => $table,
            'insert_id' => $insert_id,
            'entity' => $entity
        ));
        
        return $insert_id;
    }
    
    /**
     * Perform database update with webhook firing
     * 
     * @param string $table Table name
     * @param array $data Data to update
     * @param array $where Where conditions
     * @param array $format Data format
     * @param array $where_format Where format
     * @param string $entity Entity type for webhook
     * @param int $record_id Record ID for webhook
     * @param array $row_before Previous state
     * @param array $changed_columns Changed column names
     * @param array $webhook_data Additional webhook data
     * @return int|false Number of rows updated, false on failure
     */
    public function update_with_webhook($table, $data, $where, $format, $where_format, $entity, $record_id, $row_before = null, $changed_columns = array(), $webhook_data = array()) {
        $result = $this->wpdb->update($table, $data, $where, $format, $where_format);
        
        if ($result === false) {
            $this->log_error('Database update failed', array(
                'table' => $table,
                'error' => $this->wpdb->last_error
            ));
            return false;
        }
        
        // Only fire webhook if rows were actually updated
        if ($result > 0) {
            // Get updated row state
            $row_after = $this->get_row_after_update($table, $where, $data);
            
            $this->queue_webhook(
                $entity,
                $record_id,
                'updated',
                $row_before,
                $row_after,
                $changed_columns,
                $webhook_data['actor'] ?? null
            );
            
            $this->log_debug('Database update successful with webhook queued', array(
                'table' => $table,
                'rows_affected' => $result,
                'entity' => $entity,
                'record_id' => $record_id
            ));
        }
        
        return $result;
    }
    
    /**
     * Perform database delete with webhook firing
     * 
     * @param string $table Table name
     * @param array $where Where conditions
     * @param array $where_format Where format
     * @param string $entity Entity type for webhook
     * @param int $record_id Record ID for webhook
     * @param array $row_before Previous state
     * @param array $webhook_data Additional webhook data
     * @return int|false Number of rows deleted, false on failure
     */
    public function delete_with_webhook($table, $where, $where_format, $entity, $record_id, $row_before = null, $webhook_data = array()) {
        $result = $this->wpdb->delete($table, $where, $where_format);
        
        if ($result === false) {
            $this->log_error('Database delete failed', array(
                'table' => $table,
                'error' => $this->wpdb->last_error
            ));
            return false;
        }
        
        // Only fire webhook if rows were actually deleted
        if ($result > 0) {
            $this->queue_webhook(
                $entity,
                $record_id,
                'deleted',
                $row_before,
                null, // No new state for deletes
                array(),
                $webhook_data['actor'] ?? null
            );
            
            $this->log_debug('Database delete successful with webhook queued', array(
                'table' => $table,
                'rows_affected' => $result,
                'entity' => $entity,
                'record_id' => $record_id
            ));
        }
        
        return $result;
    }
    
    /**
     * Perform bulk database operation with single webhook
     * 
     * @param string $operation_type Type of bulk operation (update, delete)
     * @param string $table Table name
     * @param mixed $operation_data Operation-specific data
     * @param string $entity Entity type for webhook
     * @param array $webhook_data Additional webhook data
     * @return int|false Number of affected rows, false on failure
     */
    public function bulk_operation_with_webhook($operation_type, $table, $operation_data, $entity, $webhook_data = array()) {
        $start_time = microtime(true);
        
        // Begin transaction for bulk operation
        $in_transaction = $this->transaction_depth > 0;
        if (!$in_transaction) {
            $this->begin_transaction();
        }
        
        try {
            $affected_rows = 0;
            
            switch ($operation_type) {
                case 'bulk_update':
                    $affected_rows = $this->perform_bulk_update($table, $operation_data);
                    break;
                    
                case 'bulk_delete':
                    $affected_rows = $this->perform_bulk_delete($table, $operation_data);
                    break;
                    
                case 'bulk_cleanup':
                    $affected_rows = $this->perform_bulk_cleanup($table, $operation_data);
                    break;
                    
                default:
                    throw new Exception('Unknown bulk operation type: ' . $operation_type);
            }
            
            if ($affected_rows === false || $affected_rows < 0) {
                throw new \Exception(esc_html('Bulk operation failed'));
            }
            
            // Queue bulk webhook
            if ($affected_rows > 0) {
                $this->queue_webhook(
                    $entity,
                    0, // No specific record ID for bulk operations
                    'bulk',
                    null,
                    array(
                        'operation_type' => $operation_type,
                        'affected_rows' => $affected_rows,
                        'execution_time_ms' => (microtime(true) - $start_time) * 1000
                    ),
                    array('bulk_operation'),
                    $webhook_data['actor'] ?? null
                );
            }
            
            // Commit transaction if we started it
            if (!$in_transaction) {
                $this->commit_transaction();
            }
            
            $this->log_info('Bulk operation completed successfully', array(
                'operation_type' => $operation_type,
                'table' => $table,
                'affected_rows' => $affected_rows,
                'entity' => $entity,
                'execution_time_ms' => (microtime(true) - $start_time) * 1000
            ));
            
            return $affected_rows;
            
        } catch (Exception $e) {
            // Rollback transaction if we started it
            if (!$in_transaction) {
                $this->rollback_transaction();
            }
            
            $this->log_error('Bulk operation failed', array(
                'operation_type' => $operation_type,
                'table' => $table,
                'entity' => $entity,
                'error' => $e->getMessage(),
                'execution_time_ms' => (microtime(true) - $start_time) * 1000
            ));
            
            return false;
        }
    }
    
    /**
     * Queue webhook for firing after transaction commit
     * 
     * @param string $entity Entity type
     * @param int $id Record ID
     * @param string $type Operation type
     * @param array|null $row_before Previous state
     * @param array|null $row_after New state
     * @param array $changed_columns Changed columns
     * @param int|null $actor User ID
     * @return void
     */
    private function queue_webhook($entity, $id, $type, $row_before, $row_after, $changed_columns, $actor) {
        // If not in transaction, fire webhook immediately
        if ($this->transaction_depth === 0) {
            $this->fire_webhook($entity, $id, $type, $row_before, $row_after, $changed_columns, $actor);
            return;
        }
        
        // Queue webhook for firing after transaction commit
        $this->pending_webhooks[] = array(
            'entity' => $entity,
            'id' => $id,
            'type' => $type,
            'row_before' => $row_before,
            'row_after' => $row_after,
            'changed_columns' => $changed_columns,
            'actor' => $actor,
            'queued_at' => microtime(true)
        );
        
        $this->log_debug('Webhook queued for transaction commit', array(
            'entity' => $entity,
            'id' => $id,
            'type' => $type,
            'pending_count' => count($this->pending_webhooks)
        ));
    }
    
    /**
     * Fire all pending webhooks
     * 
     * @return void
     */
    private function fire_pending_webhooks() {
        if (empty($this->pending_webhooks)) {
            return;
        }
        
        $fired_count = 0;
        $failed_count = 0;
        
        foreach ($this->pending_webhooks as $webhook) {
            $success = $this->fire_webhook(
                $webhook['entity'],
                $webhook['id'],
                $webhook['type'],
                $webhook['row_before'],
                $webhook['row_after'],
                $webhook['changed_columns'],
                $webhook['actor']
            );
            
            if ($success) {
                $fired_count++;
            } else {
                $failed_count++;
            }
        }
        
        $this->log_info('Pending webhooks fired after transaction commit', array(
            'total_queued' => count($this->pending_webhooks),
            'fired_successfully' => $fired_count,
            'failed' => $failed_count
        ));
        
        $this->clear_pending_webhooks();
    }
    
    /**
     * Fire individual webhook
     * 
     * @param string $entity Entity type
     * @param int $id Record ID
     * @param string $type Operation type
     * @param array|null $row_before Previous state
     * @param array|null $row_after New state
     * @param array $changed_columns Changed columns
     * @param int|null $actor User ID
     * @return bool Success status
     */
    private function fire_webhook($entity, $id, $type, $row_before, $row_after, $changed_columns, $actor) {
        try {
            // Fire appropriate WordPress action based on entity type
            $action_name = "wp_ai_explainer_{$entity}_after_write";
            
            do_action(
                $action_name,
                $entity,
                $id,
                $type,
                $row_before,
                $row_after,
                $changed_columns,
                $actor
            );
            
            return true;
            
        } catch (Exception $e) {
            $this->log_error('Failed to fire webhook', array(
                'entity' => $entity,
                'id' => $id,
                'type' => $type,
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Clear all pending webhooks
     * 
     * @return void
     */
    private function clear_pending_webhooks() {
        $cleared_count = count($this->pending_webhooks);
        $this->pending_webhooks = array();
        
        if ($cleared_count > 0) {
            $this->log_debug('Pending webhooks cleared', array(
                'cleared_count' => $cleared_count
            ));
        }
    }
    
    /**
     * Get row state after update
     * 
     * @param string $table Table name
     * @param array $where Where conditions used in update
     * @param array $updated_data Data that was updated
     * @return array|null Row data or null if not found
     */
    private function get_row_after_update($table, $where, $updated_data) {
        if (empty($where)) {
            return null;
        }
        
        // Build WHERE clause
        $where_clause = array();
        $where_values = array();
        
        foreach ($where as $column => $value) {
            $where_clause[] = "`{$column}` = %s";
            $where_values[] = $value;
        }
        
        $where_sql = implode(' AND ', $where_clause);

        // Table name interpolation is safe as it's constructed from internal logic
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is from internal logic, placeholders are in $where_sql
        $query = "SELECT * FROM `{$table}` WHERE {$where_sql} LIMIT 1";

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare($query, $where_values), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query uses placeholders, values are prepared
            ARRAY_A
        );
        
        return $row ?: null;
    }
    
    /**
     * Perform bulk update operation
     * 
     * @param string $table Table name
     * @param array $operation_data Operation data
     * @return int|false Number of affected rows
     */
    private function perform_bulk_update($table, $operation_data) {
        // Implementation depends on specific bulk update requirements
        // This is a placeholder for bulk update logic
        return 0;
    }
    
    /**
     * Perform bulk delete operation
     * 
     * @param string $table Table name
     * @param array $operation_data Operation data
     * @return int|false Number of affected rows
     */
    private function perform_bulk_delete($table, $operation_data) {
        // Implementation depends on specific bulk delete requirements
        // This is a placeholder for bulk delete logic
        return 0;
    }
    
    /**
     * Perform bulk cleanup operation
     * 
     * @param string $table Table name
     * @param array $operation_data Operation data
     * @return int|false Number of affected rows
     */
    private function perform_bulk_cleanup($table, $operation_data) {
        // Implementation depends on specific cleanup requirements
        // This is a placeholder for cleanup logic
        return 0;
    }
    
    /**
     * Check if currently in transaction
     * 
     * @return bool True if in transaction
     */
    public function is_in_transaction() {
        return $this->transaction_depth > 0;
    }
    
    /**
     * Get transaction depth
     * 
     * @return int Current transaction depth
     */
    public function get_transaction_depth() {
        return $this->transaction_depth;
    }
    
    /**
     * Get pending webhook count
     * 
     * @return int Number of pending webhooks
     */
    public function get_pending_webhook_count() {
        return count($this->pending_webhooks);
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
            explainer_log_debug($message, $context, 'DB_Operation_Wrapper');
        }
    }
    
    /**
     * Log info message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    private function log_info($message, $context = array()) {
        if (function_exists('explainer_log_info')) {
            explainer_log_info($message, $context, 'DB_Operation_Wrapper');
        }
    }
    
    /**
     * Log warning message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    private function log_warning($message, $context = array()) {
        if (function_exists('explainer_log_warning')) {
            explainer_log_warning($message, $context, 'DB_Operation_Wrapper');
        } else if (ExplainerPlugin_Debug_Logger::is_enabled()) {
            ExplainerPlugin_Debug_Logger::warning($message, 'database', $context);
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
            explainer_log_error($message, $context, 'DB_Operation_Wrapper');
        } else if (ExplainerPlugin_Debug_Logger::is_enabled()) {
            ExplainerPlugin_Debug_Logger::error($message, 'database', $context);
        }
    }
}