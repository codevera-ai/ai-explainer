<?php
/**
 * Central hook loader for the plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register and execute all hooks for the plugin
 */
class ExplainerPlugin_Loader {
    
    /**
     * Array of actions registered with WordPress
     * @var array
     */
    protected $actions;
    
    /**
     * Array of filters registered with WordPress
     * @var array
     */
    protected $filters;
    
    /**
     * Initialize the loader
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }
    
    /**
     * Add an action to the collection
     *
     * @param string $hook The name of the WordPress action that is being registered
     * @param object $component A reference to the instance of the object on which the action is defined
     * @param string $callback The name of the function definition on the $component
     * @param int $priority The priority at which the function should be fired
     * @param int $accepted_args The number of arguments that should be passed to the $callback
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }
    
    /**
     * Add a filter to the collection
     *
     * @param string $hook The name of the WordPress filter that is being registered
     * @param object $component A reference to the instance of the object on which the filter is defined
     * @param string $callback The name of the function definition on the $component
     * @param int $priority The priority at which the function should be fired
     * @param int $accepted_args The number of arguments that should be passed to the $callback
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }
    
    /**
     * A utility function that is used to register the actions and hooks into a single collection
     *
     * @param array $hooks The collection of hooks that is being registered
     * @param string $hook The name of the WordPress filter that is being registered
     * @param object $component A reference to the instance of the object on which the filter is defined
     * @param string $callback The name of the function definition on the $component
     * @param int $priority The priority at which the function should be fired
     * @param int $accepted_args The number of arguments that should be passed to the $callback
     * @return array The collection of actions and filters registered with WordPress
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );
        
        return $hooks;
    }
    
    /**
     * Register the filters and actions with WordPress
     */
    public function run() {
        $this->debug_log('Starting hook registration', array(
            'total_actions' => count($this->actions),
            'total_filters' => count($this->filters)
        ));
        
        // Register all actions
        foreach ($this->actions as $hook) {
            $this->debug_log('Registering action', array(
                'hook' => $hook['hook'],
                'component' => get_class($hook['component']),
                'callback' => $hook['callback'],
                'priority' => $hook['priority']
            ));
            
            add_action(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
        
        // Register all filters
        foreach ($this->filters as $hook) {
            $this->debug_log('Registering filter', array(
                'hook' => $hook['hook'],
                'component' => get_class($hook['component']),
                'callback' => $hook['callback'],
                'priority' => $hook['priority']
            ));
            
            add_filter(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
        
        $this->debug_log('Hook registration completed', array(
            'registered_actions' => count($this->actions),
            'registered_filters' => count($this->filters)
        ));
    }
    
    /**
     * Get all registered actions
     * @return array
     */
    public function get_actions() {
        return $this->actions;
    }
    
    /**
     * Get all registered filters
     * @return array
     */
    public function get_filters() {
        return $this->filters;
    }
    
    /**
     * Remove an action from the collection
     *
     * @param string $hook The name of the WordPress action
     * @param object $component The component object
     * @param string $callback The callback function name
     * @return bool True if removed, false if not found
     */
    public function remove_action($hook, $component, $callback) {
        foreach ($this->actions as $key => $action) {
            if ($action['hook'] === $hook && 
                $action['component'] === $component && 
                $action['callback'] === $callback) {
                
                unset($this->actions[$key]);
                remove_action($hook, array($component, $callback));
                return true;
            }
        }
        return false;
    }
    
    /**
     * Remove a filter from the collection
     *
     * @param string $hook The name of the WordPress filter
     * @param object $component The component object
     * @param string $callback The callback function name
     * @return bool True if removed, false if not found
     */
    public function remove_filter($hook, $component, $callback) {
        foreach ($this->filters as $key => $filter) {
            if ($filter['hook'] === $hook && 
                $filter['component'] === $component && 
                $filter['callback'] === $callback) {
                
                unset($this->filters[$key]);
                remove_filter($hook, array($component, $callback));
                return true;
            }
        }
        return false;
    }
    
    /**
     * Debug logging method using unified logger
     */
    private function debug_log($message, $data = array()) {
        $logger = ExplainerPlugin_Logger::get_instance();
        return $logger->debug($message, $data, 'Loader');
    }
}