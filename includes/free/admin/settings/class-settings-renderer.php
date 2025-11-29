<?php
/**
 * Settings renderer class - coordinates all settings sections
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main settings renderer that coordinates all tab sections
 */
class ExplainerPlugin_Settings_Renderer {
    
    /**
     * Array of section instances
     */
    private $sections = array();
    
    /**
     * Initialize the renderer
     */
    public function __construct() {
        $this->load_sections();
    }
    
    /**
     * Load all section classes
     */
    private function load_sections() {
        // Load tabs with Pro-first, Free-fallback logic
        $this->load_tab_class('sections/class-basic-settings.php');
        $this->load_tab_class('sections/class-ai-provider.php');
        $this->load_tab_class('sections/class-content-rules.php');
        $this->load_tab_class('sections/class-support.php');

        $this->sections = array(
            'basic' => new ExplainerPlugin_Basic_Settings(),
            'ai-provider' => new ExplainerPlugin_AI_Provider(),
            'content' => new ExplainerPlugin_Content_Rules(),
            'support' => new ExplainerPlugin_Support()
        );

        // Pro-only tabs (load if available)
        if ($this->load_tab_class('class-appearance.php')) {
            $this->sections['appearance'] = new ExplainerPlugin_Appearance();
        }
        if ($this->load_tab_class('class-performance.php')) {
            $this->sections['performance'] = new ExplainerPlugin_Performance();
        }
        if ($this->load_tab_class('class-post-scan.php')) {
            $this->sections['post-scan'] = new ExplainerPlugin_Post_Scan();
        }
        if ($this->load_tab_class('class-popular-selections.php')) {
            $this->sections['popular'] = new ExplainerPlugin_Popular_Selections();
        }
        if ($this->load_tab_class('class-advanced.php')) {
            $this->sections['advanced'] = new ExplainerPlugin_Advanced();
        }
    }

    /**
     * Load tab class with Pro-first, Free-fallback logic
     *
     * @param string $file_path Relative path from settings directory
     * @return bool True if file was loaded, false otherwise
     */
    private function load_tab_class($file_path) {
        // Try Pro version first
        $pro_path = EXPLAINER_PLUGIN_PATH . 'includes/pro/admin/settings/' . $file_path;
        if (file_exists($pro_path)) {
            require_once $pro_path;
            return true;
        }

        // Fallback to Free version
        $free_path = EXPLAINER_PLUGIN_PATH . 'includes/free/admin/settings/' . $file_path;
        if (file_exists($free_path)) {
            require_once $free_path;
            return true;
        }

        return false;
    }
    
    
    /**
     * Get a specific section renderer
     */
    public function get_section($section_name) {
        return isset($this->sections[$section_name]) ? $this->sections[$section_name] : null;
    }
    
    /**
     * Enqueue CSS assets only (JavaScript now handled by Asset Manager)
     */
    public function enqueue_assets() {
        // Enqueue CSS
        wp_enqueue_style(
            'explainer-admin-settings',
            EXPLAINER_PLUGIN_URL . 'includes/admin/settings/assets/settings-styles.css',
            array(),
            EXPLAINER_PLUGIN_VERSION
        );
    }
}