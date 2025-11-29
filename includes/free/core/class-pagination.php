<?php
/**
 * Reusable Pagination Class for AI Explainer
 * 
 * Provides WordPress-style pagination functionality for admin tables
 * with AJAX support and consistent styling.
 *
 * @package WPAIExplainer
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pagination handler class following WordPress conventions
 */
class ExplainerPlugin_Pagination {
    
    /**
     * Total number of items
     * @var int
     */
    private $total_items;
    
    /**
     * Items per page
     * @var int
     */
    private $per_page;
    
    /**
     * Current page number
     * @var int
     */
    private $current_page;
    
    /**
     * Total number of pages
     * @var int
     */
    private $total_pages;
    
    /**
     * Constructor
     *
     * @param int $total_items Total number of items
     * @param int $per_page Items per page (default 20)
     * @param int $current_page Current page number (default 1)
     */
    public function __construct($total_items, $per_page = 20, $current_page = 1) {
        $this->total_items = absint($total_items);
        $this->per_page = absint($per_page) ?: 20;
        $this->current_page = max(1, absint($current_page));
        $this->total_pages = ceil($this->total_items / $this->per_page);
        
        // Ensure current page doesn't exceed total pages
        if ($this->current_page > $this->total_pages && $this->total_pages > 0) {
            $this->current_page = $this->total_pages;
        }
    }
    
    /**
     * Get pagination arguments for database queries
     *
     * @return array Array with offset and limit values
     */
    public function get_pagination_args() {
        return array(
            'offset' => $this->get_offset(),
            'limit' => $this->get_limit(),
            'current_page' => $this->current_page,
            'per_page' => $this->per_page,
            'total_items' => $this->total_items,
            'total_pages' => $this->total_pages,
            'has_previous' => $this->current_page > 1,
            'has_next' => $this->current_page < $this->total_pages,
        );
    }
    
    /**
     * Get offset for database LIMIT clause
     *
     * @return int Offset value
     */
    public function get_offset() {
        return ($this->current_page - 1) * $this->per_page;
    }
    
    /**
     * Get limit for database LIMIT clause
     *
     * @return int Limit value
     */
    public function get_limit() {
        return $this->per_page;
    }
    
    /**
     * Generate pagination HTML following WordPress admin conventions
     *
     * @param string $container_id Container ID for the pagination controls
     * @return string HTML for pagination controls
     */
    public function get_pagination_html($container_id = '') {
        if ($this->total_pages <= 1) {
            return '';
        }
        
        $container_id_attr = $container_id ? 'id="' . esc_attr($container_id) . '"' : '';
        
        $html = '<div class="tablenav-pages" ' . $container_id_attr . '>';
        $html .= '<span class="displaying-num">' . sprintf(
            /* translators: %s: Number of items */
            _n('%s item', '%s items', $this->total_items, 'ai-explainer'),
            number_format_i18n($this->total_items)
        ) . '</span>';
        
        $html .= $this->get_pagination_links();
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate pagination links
     *
     * @return string HTML for pagination links
     */
    private function get_pagination_links() {
        $links = '<span class="pagination-links">';
        
        // First page link
        if ($this->current_page > 1) {
            $links .= '<button type="button" class="first-page button" data-page="1" title="' . 
                      esc_attr__('Go to the first page', 'ai-explainer') . '">&laquo;</button> ';
        } else {
            $links .= '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span> ';
        }
        
        // Previous page link
        if ($this->current_page > 1) {
            $prev_page = $this->current_page - 1;
            $links .= '<button type="button" class="prev-page button" data-page="' . $prev_page . 
                      '" title="' . esc_attr__('Go to the previous page', 'ai-explainer') . '">&lsaquo;</button> ';
        } else {
            $links .= '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span> ';
        }
        
        // Page numbers
        $links .= $this->get_page_numbers();
        
        // Next page link
        if ($this->current_page < $this->total_pages) {
            $next_page = $this->current_page + 1;
            $links .= '<button type="button" class="next-page button" data-page="' . $next_page . 
                      '" title="' . esc_attr__('Go to the next page', 'ai-explainer') . '">&rsaquo;</button> ';
        } else {
            $links .= '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span> ';
        }
        
        // Last page link
        if ($this->current_page < $this->total_pages) {
            $links .= '<button type="button" class="last-page button" data-page="' . $this->total_pages . 
                      '" title="' . esc_attr__('Go to the last page', 'ai-explainer') . '">&raquo;</button>';
        } else {
            $links .= '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
        }
        
        $links .= '</span>';
        
        return $links;
    }
    
    /**
     * Generate page number links
     *
     * @return string HTML for page number display and input
     */
    private function get_page_numbers() {
        $page_links = '<span class="paging-input">';
        $page_links .= '<label for="current-page-selector" class="screen-reader-text">' . 
                       esc_html__('Current Page', 'ai-explainer') . '</label>';
        
        // Current page input
        $page_links .= '<input class="current-page" id="current-page-selector" type="text" ' .
                       'name="paged" value="' . $this->current_page . '" size="' . strlen($this->total_pages) . '" ' .
                       'aria-describedby="table-paging" />';
        
        $page_links .= '<span class="tablenav-paging-text"> ' . 
                       /* translators: %s: Total number of pages */
                       sprintf(__('of %s', 'ai-explainer'), '<span class="total-pages">' . $this->total_pages . '</span>') .
                       '</span>';
        
        $page_links .= '</span>';
        
        return $page_links;
    }
    
    /**
     * Get pagination data for AJAX responses
     *
     * @return array Pagination data array
     */
    public function get_pagination_data() {
        return array(
            'current_page' => $this->current_page,
            'per_page' => $this->per_page,
            'total_items' => $this->total_items,
            'total_pages' => $this->total_pages,
            'has_previous' => $this->current_page > 1,
            'has_next' => $this->current_page < $this->total_pages,
            'offset' => $this->get_offset(),
            'limit' => $this->get_limit(),
        );
    }
    
    /**
     * Validate and sanitise pagination parameters from request
     *
     * @param array $params Request parameters
     * @return array Validated parameters
     */
    public static function validate_pagination_params($params = array()) {
        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'orderby' => '',
            'order' => 'desc',
            'search' => '',
        );

        $params = wp_parse_args($params, $defaults);

        return array(
            'page' => max(1, absint($params['page'])),
            'per_page' => max(1, min(100, absint($params['per_page']))), // Cap at 100
            'orderby' => sanitize_key($params['orderby']),
            'order' => in_array(strtolower($params['order']), array('asc', 'desc')) ?
                      strtolower($params['order']) : 'desc',
            'search' => isset($params['search']) ? sanitize_text_field($params['search']) : '',
        );
    }
}