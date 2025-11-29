<?php
/**
 * Theme compatibility and optimization
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle theme compatibility and CSS optimization
 */
class ExplainerPlugin_Theme_Compatibility {
    
    /**
     * Current theme name
     */
    private $current_theme;
    
    /**
     * Theme-specific CSS overrides
     */
    private $theme_overrides = array();
    
    /**
     * Initialize theme compatibility
     */
    public function __construct() {
        $this->current_theme = get_template();
        $this->load_theme_overrides();
        
        // Add theme-specific hooks
        add_action('wp_head', array($this, 'add_theme_compatibility_css'), 15);
        add_action('wp_footer', array($this, 'add_theme_compatibility_js'), 25);
        
        // Optimize for popular themes
        $this->optimize_for_popular_themes();
    }
    
    /**
     * Load theme-specific overrides
     */
    private function load_theme_overrides() {
        // Popular WordPress themes and their specific needs
        $this->theme_overrides = array(
            'twentytwentyfour' => array(
                'selectors' => array(
                    'included' => 'main, .wp-block-post-content, .entry-content',
                    'excluded' => 'nav, header, footer, .wp-block-navigation, .wp-site-blocks > header, .wp-site-blocks > footer'
                ),
                'css' => array(
                    '.explainer-toggle' => array(
                        'z-index' => '999999',
                        'position' => 'fixed'
                    ),
                    '.explainer-tooltip' => array(
                        'font-family' => 'var(--wp--preset--font-family--system-font, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif)',
                        'z-index' => '999998'
                    )
                )
            ),
            'twentytwentythree' => array(
                'selectors' => array(
                    'included' => 'main, .wp-block-post-content, .entry-content',
                    'excluded' => 'nav, header, footer, .wp-block-navigation'
                ),
                'css' => array(
                    '.explainer-toggle' => array(
                        'z-index' => '999999'
                    )
                )
            ),
            'astra' => array(
                'selectors' => array(
                    'included' => 'main, .entry-content, .ast-article-post, .site-content',
                    'excluded' => 'nav, header, footer, .main-header-menu, .ast-mobile-menu'
                ),
                'css' => array(
                    '.explainer-toggle' => array(
                        'z-index' => '999999'
                    ),
                    '.explainer-tooltip' => array(
                        'font-family' => 'inherit'
                    )
                )
            ),
            'generatepress' => array(
                'selectors' => array(
                    'included' => 'main, .entry-content, .inside-article, .content-area',
                    'excluded' => 'nav, header, footer, .main-navigation, .site-header'
                ),
                'css' => array(
                    '.explainer-toggle' => array(
                        'z-index' => '999999'
                    )
                )
            ),
            'neve' => array(
                'selectors' => array(
                    'included' => 'main, .entry-content, .nv-content-wrap, .single-post-container',
                    'excluded' => 'nav, header, footer, .header-menu-sidebar, .hfg-header'
                ),
                'css' => array(
                    '.explainer-toggle' => array(
                        'z-index' => '999999'
                    )
                )
            ),
            'oceanwp' => array(
                'selectors' => array(
                    'included' => 'main, .entry-content, .content-area, #main',
                    'excluded' => 'nav, header, footer, .oceanwp-mobile-menu, .site-header'
                ),
                'css' => array(
                    '.explainer-toggle' => array(
                        'z-index' => '999999'
                    )
                )
            ),
            'storefront' => array(
                'selectors' => array(
                    'included' => 'main, .entry-content, .site-content, .content-area',
                    'excluded' => 'nav, header, footer, .site-header, .main-navigation'
                ),
                'css' => array(
                    '.explainer-toggle' => array(
                        'z-index' => '999999'
                    )
                )
            ),
            'kadence' => array(
                'selectors' => array(
                    'included' => 'main, .entry-content, .content-container, .single-content',
                    'excluded' => 'nav, header, footer, .main-navigation, .site-header'
                ),
                'css' => array(
                    '.explainer-toggle' => array(
                        'z-index' => '999999'
                    )
                )
            )
        );
    }
    
    /**
     * Add theme-specific CSS
     */
    public function add_theme_compatibility_css() {
        if (!$this->has_theme_overrides()) {
            return;
        }
        
        $overrides = $this->theme_overrides[$this->current_theme];
        
        if (!empty($overrides['css'])) {
            echo '<style id="explainer-theme-compatibility">' . "\n";
            echo '/* AI Explainer Plugin - Theme Compatibility: ' . esc_html($this->current_theme) . ' */' . "\n";
            
            foreach ($overrides['css'] as $selector => $properties) {
                echo esc_html($selector) . ' {' . "\n";
                foreach ($properties as $property => $value) {
                    echo '    ' . esc_html($property) . ': ' . esc_html($value) . ' !important;' . "\n";
                }
                echo '}' . "\n";
            }
            
            echo '</style>' . "\n";
        }
    }
    
    /**
     * Add theme-specific JavaScript
     */
    public function add_theme_compatibility_js() {
        if (!$this->has_theme_overrides()) {
            return;
        }
        
        $overrides = $this->theme_overrides[$this->current_theme];
        
        if (!empty($overrides['selectors'])) {
            ?>
            <script>
            // AI Explainer Plugin - Theme Compatibility
            (function() {
                if (window.ExplainerPlugin && window.ExplainerPlugin.config) {
                    <?php if (!empty($overrides['selectors']['included'])): ?>
                    window.ExplainerPlugin.config.includedSelectors = '<?php echo esc_js($overrides['selectors']['included']); ?>';
                    <?php endif; ?>
                    
                    <?php if (!empty($overrides['selectors']['excluded'])): ?>
                    window.ExplainerPlugin.config.excludedSelectors = '<?php echo esc_js($overrides['selectors']['excluded']); ?>';
                    <?php endif; ?>
                }
            })();
            </script>
            <?php
        }
    }
    
    /**
     * Check if current theme has overrides
     */
    private function has_theme_overrides() {
        return isset($this->theme_overrides[$this->current_theme]);
    }
    
    /**
     * Optimize for popular themes
     */
    private function optimize_for_popular_themes() {
        switch ($this->current_theme) {
            case 'twentytwentyfour':
                $this->optimize_for_twenty_twenty_four();
                break;
                
            case 'astra':
                $this->optimize_for_astra();
                break;
                
            case 'generatepress':
                $this->optimize_for_generatepress();
                break;
                
            case 'neve':
                $this->optimize_for_neve();
                break;
                
            case 'oceanwp':
                $this->optimize_for_oceanwp();
                break;
                
            case 'storefront':
                $this->optimize_for_storefront();
                break;
                
            case 'kadence':
                $this->optimize_for_kadence();
                break;
        }
    }
    
    /**
     * Optimize for Twenty Twenty-Four theme
     */
    private function optimize_for_twenty_twenty_four() {
        add_action('wp_head', function() {
            echo '<style>
                .explainer-tooltip {
                    --wp--preset--color--base: #fff;
                    --wp--preset--color--contrast: #000;
                }
                .wp-site-blocks .explainer-toggle {
                    position: fixed !important;
                }
            </style>';
        }, 20);
    }
    
    /**
     * Optimize for Astra theme
     */
    private function optimize_for_astra() {
        add_action('wp_head', function() {
            echo '<style>
                .explainer-tooltip {
                    font-family: inherit;
                }
                .ast-desktop .explainer-toggle {
                    bottom: 20px !important;
                }
            </style>';
        }, 20);
    }
    
    /**
     * Optimize for GeneratePress theme
     */
    private function optimize_for_generatepress() {
        add_action('wp_head', function() {
            echo '<style>
                .explainer-tooltip {
                    font-family: inherit;
                }
                .generate-columns-container .explainer-toggle {
                    position: fixed !important;
                }
            </style>';
        }, 20);
    }
    
    /**
     * Optimize for Neve theme
     */
    private function optimize_for_neve() {
        add_action('wp_head', function() {
            echo '<style>
                .explainer-tooltip {
                    font-family: var(--nv-text-font-family, inherit);
                }
                .nv-content-wrap .explainer-toggle {
                    position: fixed !important;
                }
            </style>';
        }, 20);
    }
    
    /**
     * Optimize for OceanWP theme
     */
    private function optimize_for_oceanwp() {
        add_action('wp_head', function() {
            echo '<style>
                .explainer-tooltip {
                    font-family: inherit;
                }
                #main .explainer-toggle {
                    position: fixed !important;
                }
            </style>';
        }, 20);
    }
    
    /**
     * Optimize for Storefront theme
     */
    private function optimize_for_storefront() {
        add_action('wp_head', function() {
            echo '<style>
                .explainer-tooltip {
                    font-family: inherit;
                }
                .storefront-breadcrumb + .explainer-toggle {
                    bottom: 70px !important;
                }
            </style>';
        }, 20);
    }
    
    /**
     * Optimize for Kadence theme
     */
    private function optimize_for_kadence() {
        add_action('wp_head', function() {
            echo '<style>
                .explainer-tooltip {
                    font-family: var(--global-body-font-family, inherit);
                }
                .content-container .explainer-toggle {
                    position: fixed !important;
                }
            </style>';
        }, 20);
    }
    
    /**
     * Get theme-specific selectors
     */
    public function get_theme_selectors() {
        if (!$this->has_theme_overrides()) {
            return array();
        }
        
        return $this->theme_overrides[$this->current_theme]['selectors'] ?? array();
    }
    
    /**
     * Detect potential theme conflicts
     */
    public function detect_theme_conflicts() {
        $conflicts = array();
        
        // Check for common conflicting CSS
        $conflicting_styles = array(
            'z-index' => 'High z-index values that might interfere with tooltips',
            'position: fixed' => 'Fixed positioning that might conflict with toggle button',
            'overflow: hidden' => 'Hidden overflow that might clip tooltips',
            'pointer-events: none' => 'Disabled pointer events that might prevent interaction'
        );
        
        // Check for conflicting JavaScript
        $conflicting_scripts = array(
            'selection' => 'Scripts that interfere with text selection',
            'tooltip' => 'Other tooltip libraries that might conflict',
            'overlay' => 'Overlay scripts that might interfere with tooltips'
        );
        
        return array(
            'css' => $conflicting_styles,
            'js' => $conflicting_scripts,
            'recommendations' => $this->get_theme_recommendations()
        );
    }
    
    /**
     * Get theme-specific recommendations
     */
    private function get_theme_recommendations() {
        $recommendations = array();
        
        switch ($this->current_theme) {
            case 'twentytwentyfour':
                $recommendations[] = 'Use wp-block-post-content for better content targeting';
                $recommendations[] = 'Consider FSE block editor compatibility';
                break;
                
            case 'astra':
                $recommendations[] = 'Configure excluded selectors to avoid Astra Pro modules';
                $recommendations[] = 'Test with various Astra layout options';
                break;
                
            case 'generatepress':
                $recommendations[] = 'Test with GeneratePress Premium modules';
                $recommendations[] = 'Consider Elements addon compatibility';
                break;
                
            default:
                $recommendations[] = 'Test tooltip positioning with theme header/footer';
                $recommendations[] = 'Verify selection works in theme content areas';
                break;
        }
        
        return $recommendations;
    }
    
    /**
     * Get current theme information
     */
    public function get_theme_info() {
        $theme = wp_get_theme();
        
        return array(
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'template' => $this->current_theme,
            'parent' => $theme->parent() ? $theme->parent()->get('Name') : null,
            'supported' => $this->has_theme_overrides(),
            'optimizations' => $this->has_theme_overrides() ? count($this->theme_overrides[$this->current_theme]) : 0
        );
    }
}