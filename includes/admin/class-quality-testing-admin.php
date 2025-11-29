<?php
/**
 * Admin interface for AI response quality testing
 *
 * Provides admin dashboard integration for running and viewing
 * AI response quality validation tests.
 *
 * @package WP_AI_Explainer
 * @subpackage Admin
 * @since 3.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Quality testing admin interface
 */
class ExplainerPlugin_Quality_Testing_Admin {

    /**
     * Test suite instance
     */
    private $test_suite;

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->test_suite = ExplainerPlugin_Response_Quality_Test_Suite::get_instance();
        $this->logger = ExplainerPlugin_Logger::get_instance();
        
        // Hook into admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_run_quality_tests', array($this, 'handle_run_quality_tests'));
        add_action('wp_ajax_export_test_results', array($this, 'handle_export_test_results'));
    }

    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wp-ai-explainer-admin',
            __('Quality Testing', 'ai-explainer'),
            __('Quality Testing', 'ai-explainer'),
            'manage_options',
            'explainer-quality-testing',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook_suffix Admin page hook suffix
     */
    public function enqueue_admin_scripts($hook_suffix) {
        if (strpos($hook_suffix, 'explainer-quality-testing') === false) {
            return;
        }

        wp_enqueue_script(
            'explainer-quality-testing',
            EXPLAINER_PLUGIN_URL . 'assets/js/admin/quality-testing.js',
            array('jquery'),
            ExplainerPlugin_Config::VERSION,
            true
        );

        wp_localize_script('explainer-quality-testing', 'explainerQualityTesting', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('explainer_quality_testing'),
            'strings' => array(
                'runningTests' => __('Running quality tests...', 'ai-explainer'),
                'testsCompleted' => __('Quality tests completed', 'ai-explainer'),
                'testsFailed' => __('Quality tests failed', 'ai-explainer'),
                'exportingResults' => __('Exporting results...', 'ai-explainer'),
                'exportCompleted' => __('Export completed', 'ai-explainer')
            )
        ));

        wp_enqueue_style(
            'explainer-quality-testing',
            EXPLAINER_PLUGIN_URL . 'assets/css/admin/quality-testing.css',
            array(),
            ExplainerPlugin_Config::VERSION
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Check if tests have been run recently
        $recent_results = get_transient('explainer_quality_test_results');
        
        ?>
        <div class="wrap explainer-quality-testing">
            <h1><?php esc_html_e('AI Response Quality Testing', 'ai-explainer'); ?></h1>
            
            <div class="explainer-quality-testing-intro">
                <p><?php esc_html_e('Test AI provider response quality, focus accuracy, and reading level compliance with enhanced context integration.', 'ai-explainer'); ?></p>
            </div>

            <div class="explainer-testing-controls">
                <div class="explainer-card">
                    <h2><?php esc_html_e('Run Quality Tests', 'ai-explainer'); ?></h2>
                    
                    <form id="quality-test-form">
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="test-providers"><?php esc_html_e('Providers to Test', 'ai-explainer'); ?></label>
                                    </th>
                                    <td>
                                        <fieldset>
                                            <legend class="screen-reader-text"><?php esc_html_e('Select providers to test', 'ai-explainer'); ?></legend>
                                            <?php
                                            $available_providers = array('openai' => 'OpenAI', 'claude' => 'Claude', 'gemini' => 'Gemini');
                                            $default_providers = array('openai', 'claude');
                                            
                                            foreach ($available_providers as $provider_key => $provider_name) {
                                                $checked = in_array($provider_key, $default_providers) ? 'checked' : '';
                                                echo '<label><input type="checkbox" name="providers[]" value="' . esc_attr($provider_key) . '" ' . esc_attr($checked) . '> ' . esc_html($provider_name) . '</label><br>';
                                            }
                                            ?>
                                        </fieldset>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="test-reading-levels"><?php esc_html_e('Reading Levels', 'ai-explainer'); ?></label>
                                    </th>
                                    <td>
                                        <fieldset>
                                            <legend class="screen-reader-text"><?php esc_html_e('Select reading levels to test', 'ai-explainer'); ?></legend>
                                            <?php
                                            $reading_levels = ExplainerPlugin_Config::get_reading_level_labels();
                                            $default_levels = array('simple', 'standard', 'detailed');
                                            
                                            foreach ($reading_levels as $level_key => $level_label) {
                                                $checked = in_array($level_key, $default_levels) ? 'checked' : '';
                                                echo '<label><input type="checkbox" name="reading_levels[]" value="' . esc_attr($level_key) . '" ' . esc_attr($checked) . '> ' . esc_html($level_label) . '</label><br>';
                                            }
                                            ?>
                                        </fieldset>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="include-cross-provider"><?php esc_html_e('Test Options', 'ai-explainer'); ?></label>
                                    </th>
                                    <td>
                                        <fieldset>
                                            <legend class="screen-reader-text"><?php esc_html_e('Select test options', 'ai-explainer'); ?></legend>
                                            <label><input type="checkbox" name="include_cross_provider_comparison" value="1" checked> <?php esc_html_e('Cross-provider comparison', 'ai-explainer'); ?></label><br>
                                            <label><input type="checkbox" name="include_reading_level_validation" value="1" checked> <?php esc_html_e('Reading level validation', 'ai-explainer'); ?></label><br>
                                            <label><input type="checkbox" name="include_context_leakage_tests" value="1" checked> <?php esc_html_e('Context leakage testing', 'ai-explainer'); ?></label><br>
                                            <label><input type="checkbox" name="generate_detailed_report" value="1" checked> <?php esc_html_e('Generate detailed report', 'ai-explainer'); ?></label>
                                        </fieldset>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary" id="run-tests-btn">
                                <?php esc_html_e('Run Quality Tests', 'ai-explainer'); ?>
                            </button>
                            <span class="spinner"></span>
                        </p>
                    </form>
                </div>
            </div>

            <div id="test-results-container" style="<?php echo esc_attr($recent_results ? '' : 'display: none;'); ?>">
                <div class="explainer-card">
                    <h2><?php esc_html_e('Test Results', 'ai-explainer'); ?>
                        <button type="button" class="button" id="export-results-btn" style="margin-left: 10px;">
                            <?php esc_html_e('Export Results', 'ai-explainer'); ?>
                        </button>
                    </h2>
                    
                    <div id="test-results-content">
                        <?php
                        if ($recent_results) {
                            $this->render_test_results($recent_results);
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .explainer-quality-testing .explainer-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .explainer-quality-testing .explainer-card h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e5e5;
        }
        
        .explainer-testing-progress {
            background: #f0f6fc;
            border: 1px solid #0073aa;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .explainer-test-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .explainer-summary-item {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 15px;
            text-align: center;
        }
        
        .explainer-summary-value {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
            display: block;
        }
        
        .explainer-summary-label {
            color: #666;
            font-size: 14px;
        }
        
        .explainer-results-tabs {
            border-bottom: 1px solid #e5e5e5;
            margin-bottom: 20px;
        }
        
        .explainer-results-tabs button {
            background: none;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }
        
        .explainer-results-tabs button.active {
            border-bottom-color: #0073aa;
            color: #0073aa;
        }
        
        .explainer-results-panel {
            display: none;
        }
        
        .explainer-results-panel.active {
            display: block;
        }
        
        .explainer-score-bar {
            background: #e5e5e5;
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            margin: 5px 0;
        }
        
        .explainer-score-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        
        .explainer-score-excellent { background: #46b450; }
        .explainer-score-good { background: #00a0d2; }
        .explainer-score-fair { background: #ffb900; }
        .explainer-score-poor { background: #dc3232; }
        </style>
        <?php
    }

    /**
     * Handle AJAX request to run quality tests
     */
    public function handle_run_quality_tests() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_quality_testing')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        try {
            // Parse test configuration
            $config = array(
                'providers' => isset($_POST['providers']) ? array_map('sanitize_text_field', wp_unslash((array) $_POST['providers'])) : array('openai'),
                'reading_levels' => isset($_POST['reading_levels']) ? array_map('sanitize_text_field', wp_unslash((array) $_POST['reading_levels'])) : array('standard'),
                'include_cross_provider_comparison' => !empty($_POST['include_cross_provider_comparison']),
                'include_reading_level_validation' => !empty($_POST['include_reading_level_validation']),
                'include_context_leakage_tests' => !empty($_POST['include_context_leakage_tests']),
                'generate_detailed_report' => !empty($_POST['generate_detailed_report'])
            );

            $this->logger->info('Starting quality test suite from admin', array(
                'user_id' => get_current_user_id(),
                'config' => $config
            ));

            // Run tests
            $results = $this->test_suite->run_comprehensive_tests($config);

            // Cache results for 1 hour
            set_transient('explainer_quality_test_results', $results, HOUR_IN_SECONDS);

            wp_send_json_success(array(
                'results' => $results,
                'html' => $this->render_test_results($results, false)
            ));

        } catch (Exception $e) {
            $this->logger->error('Quality test suite failed in admin', array(
                'error' => $e->getMessage(),
                'user_id' => get_current_user_id()
            ));

            wp_send_json_error(array(
                'message' => 'Test execution failed: ' . $e->getMessage()
            ));
        }
    }

    /**
     * Handle AJAX request to export test results
     */
    public function handle_export_test_results() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'explainer_quality_testing')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        try {
            $results = get_transient('explainer_quality_test_results');

            if (!$results) {
                wp_send_json_error(array('message' => 'No recent test results found'));
            }

            $format = isset($_POST['format']) ? sanitize_text_field(wp_unslash($_POST['format'])) : 'json';
            $exported_data = $this->test_suite->export_results($results, $format);

            // Generate filename
            $timestamp = wp_date('Y-m-d_H-i-s');
            $filename = "explainer-quality-tests_{$timestamp}.{$format}";

            wp_send_json_success(array(
                'data' => $exported_data,
                'filename' => $filename,
                'mime_type' => $format === 'csv' ? 'text/csv' : 'application/json'
            ));

        } catch (Exception $e) {
            $this->logger->error('Export test results failed', array(
                'error' => $e->getMessage(),
                'user_id' => get_current_user_id()
            ));

            wp_send_json_error(array(
                'message' => 'Export failed: ' . $e->getMessage()
            ));
        }
    }

    /**
     * Render test results HTML
     *
     * @param array $results Test results
     * @param bool $echo Whether to echo output
     * @return string HTML output
     */
    private function render_test_results($results, $echo = true) {
        ob_start();
        ?>
        
        <div class="explainer-test-summary">
            <div class="explainer-summary-item">
                <span class="explainer-summary-value"><?php echo esc_html($results['test_summary']['total_tests_run']); ?></span>
                <span class="explainer-summary-label"><?php esc_html_e('Total Tests', 'ai-explainer'); ?></span>
            </div>
            <div class="explainer-summary-item">
                <span class="explainer-summary-value"><?php echo esc_html($results['test_summary']['tests_passed']); ?></span>
                <span class="explainer-summary-label"><?php esc_html_e('Passed', 'ai-explainer'); ?></span>
            </div>
            <div class="explainer-summary-item">
                <span class="explainer-summary-value"><?php echo esc_html($results['test_summary']['tests_failed']); ?></span>
                <span class="explainer-summary-label"><?php esc_html_e('Failed', 'ai-explainer'); ?></span>
            </div>
            <div class="explainer-summary-item">
                <span class="explainer-summary-value">
                    <?php
                    $pass_rate = $results['test_summary']['total_tests_run'] > 0
                        ? round(($results['test_summary']['tests_passed'] / $results['test_summary']['total_tests_run']) * 100, 1)
                        : 0;
                    echo esc_html($pass_rate . '%');
                    ?>
                </span>
                <span class="explainer-summary-label"><?php esc_html_e('Pass Rate', 'ai-explainer'); ?></span>
            </div>
        </div>

        <div class="explainer-results-tabs">
            <button type="button" class="explainer-tab-btn active" data-tab="overview"><?php esc_html_e('Overview', 'ai-explainer'); ?></button>
            <button type="button" class="explainer-tab-btn" data-tab="scenarios"><?php esc_html_e('Scenarios', 'ai-explainer'); ?></button>
            <button type="button" class="explainer-tab-btn" data-tab="providers"><?php esc_html_e('Providers', 'ai-explainer'); ?></button>
            <button type="button" class="explainer-tab-btn" data-tab="recommendations"><?php esc_html_e('Recommendations', 'ai-explainer'); ?></button>
        </div>

        <div class="explainer-results-panels">
            <div id="overview-panel" class="explainer-results-panel active">
                <?php $this->render_overview_panel($results); ?>
            </div>
            
            <div id="scenarios-panel" class="explainer-results-panel">
                <?php $this->render_scenarios_panel($results); ?>
            </div>
            
            <div id="providers-panel" class="explainer-results-panel">
                <?php $this->render_providers_panel($results); ?>
            </div>
            
            <div id="recommendations-panel" class="explainer-results-panel">
                <?php $this->render_recommendations_panel($results); ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.explainer-tab-btn').on('click', function() {
                var tab = $(this).data('tab');
                
                $('.explainer-tab-btn').removeClass('active');
                $(this).addClass('active');
                
                $('.explainer-results-panel').removeClass('active');
                $('#' + tab + '-panel').addClass('active');
            });
        });
        </script>
        
        <?php
        $output = ob_get_clean();

        if ($echo) {
            // Output is already escaped at individual variable level throughout the template
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $output;
        }

        return $output;
    }

    /**
     * Render overview panel
     *
     * @param array $results Test results
     */
    private function render_overview_panel($results) {
        if (!empty($results['quality_report']['summary'])) {
            $summary = $results['quality_report']['summary'];
            ?>
            <h3><?php esc_html_e('Quality Report Summary', 'ai-explainer'); ?></h3>
            
            <div class="explainer-score-section">
                <h4><?php esc_html_e('Average Scores', 'ai-explainer'); ?></h4>
                <?php
                $scores = array(
                    'term_focus' => __('Term Focus', 'ai-explainer'),
                    'context_leakage' => __('Context Leakage Prevention', 'ai-explainer'),
                    'response_quality' => __('Response Quality', 'ai-explainer'),
                    'overall' => __('Overall Score', 'ai-explainer')
                );
                
                foreach ($scores as $score_key => $score_label) {
                    if (isset($summary['average_scores'][$score_key])) {
                        $score = $summary['average_scores'][$score_key];
                        $class = $this->get_score_class($score);
                        ?>
                        <div class="explainer-score-item">
                            <label><?php echo esc_html($score_label); ?>: <?php echo esc_html($score); ?>%</label>
                            <div class="explainer-score-bar">
                                <div class="explainer-score-fill <?php echo esc_attr($class); ?>" style="width: <?php echo esc_attr($score); ?>%;"></div>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>

            <?php if (!empty($summary['common_issues'])): ?>
            <div class="explainer-issues-section">
                <h4><?php esc_html_e('Most Common Issues', 'ai-explainer'); ?></h4>
                <ul>
                    <?php foreach (array_slice($summary['common_issues'], 0, 5, true) as $issue => $count): ?>
                        <li><?php echo esc_html($issue); ?> <span class="count">(<?php echo esc_html($count); ?> times)</span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="explainer-overall-assessment">
                <h4><?php esc_html_e('Overall Assessment', 'ai-explainer'); ?></h4>
                <p><strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $summary['overall_quality']))); ?></strong></p>
            </div>
            <?php
        }
    }

    /**
     * Render scenarios panel
     *
     * @param array $results Test results
     */
    private function render_scenarios_panel($results) {
        if (!empty($results['scenario_results'])) {
            ?>
            <h3><?php esc_html_e('Test Scenarios Results', 'ai-explainer'); ?></h3>
            
            <?php foreach ($results['scenario_results'] as $scenario_id => $scenario_tests): ?>
                <div class="explainer-scenario-section">
                    <h4><?php echo esc_html(ucfirst(str_replace('_', ' ', $scenario_id))); ?></h4>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Provider', 'ai-explainer'); ?></th>
                                <th><?php esc_html_e('Reading Level', 'ai-explainer'); ?></th>
                                <th><?php esc_html_e('Overall Score', 'ai-explainer'); ?></th>
                                <th><?php esc_html_e('Focus Score', 'ai-explainer'); ?></th>
                                <th><?php esc_html_e('Leakage Score', 'ai-explainer'); ?></th>
                                <th><?php esc_html_e('Quality Score', 'ai-explainer'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scenario_tests as $test_key => $validation): ?>
                                <?php if (!isset($validation['error'])): ?>
                                    <?php 
                                    $parts = explode('_', $test_key, 2);
                                    $provider = ucfirst($parts[0]);
                                    $reading_level = ucfirst(str_replace('_', ' ', $parts[1] ?? 'unknown'));
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($provider); ?></td>
                                        <td><?php echo esc_html($reading_level); ?></td>
                                        <td><span class="<?php echo esc_attr($this->get_score_class($validation['overall_score'] ?? 0)); ?>"><?php echo esc_html($validation['overall_score'] ?? 0); ?>%</span></td>
                                        <td><?php echo esc_html($validation['term_focus_score'] ?? 0); ?>%</td>
                                        <td><?php echo esc_html($validation['context_leakage_score'] ?? 0); ?>%</td>
                                        <td><?php echo esc_html($validation['response_quality_score'] ?? 0); ?>%</td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
            <?php
        }
    }

    /**
     * Render providers panel
     *
     * @param array $results Test results
     */
    private function render_providers_panel($results) {
        if (!empty($results['cross_provider_comparison'])) {
            ?>
            <h3><?php esc_html_e('Cross-Provider Comparison', 'ai-explainer'); ?></h3>
            
            <?php foreach ($results['cross_provider_comparison'] as $comparison_key => $comparison): ?>
                <?php if (!isset($comparison['error']) && !empty($comparison['comparison_summary'])): ?>
                    <div class="explainer-comparison-section">
                        <h4><?php echo esc_html(str_replace('comparison_', 'Reading Level: ', $comparison_key)); ?></h4>
                        
                        <div class="explainer-comparison-summary">
                            <p><strong><?php esc_html_e('Best Provider:', 'ai-explainer'); ?></strong> <?php echo esc_html(ucfirst($comparison['comparison_summary']['best_provider'])); ?></p>
                            <p><strong><?php esc_html_e('Average Score:', 'ai-explainer'); ?></strong> <?php echo esc_html($comparison['comparison_summary']['average_score']); ?>%</p>
                            <p><strong><?php esc_html_e('Quality Consistency:', 'ai-explainer'); ?></strong> <?php echo esc_html($comparison['comparison_summary']['consistent_quality'] ? __('Yes', 'ai-explainer') : __('No', 'ai-explainer')); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php
        }
    }

    /**
     * Render recommendations panel
     *
     * @param array $results Test results
     */
    private function render_recommendations_panel($results) {
        if (!empty($results['recommendations'])) {
            $recommendations = $results['recommendations'];
            ?>
            <h3><?php esc_html_e('Recommendations', 'ai-explainer'); ?></h3>
            
            <?php if (!empty($recommendations['immediate_actions'])): ?>
                <div class="explainer-recommendations-section">
                    <h4 style="color: #dc3232;"><?php esc_html_e('Immediate Actions Required', 'ai-explainer'); ?></h4>
                    <ul>
                        <?php foreach ($recommendations['immediate_actions'] as $action): ?>
                            <li><?php echo esc_html($action); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($recommendations['system_improvements'])): ?>
                <div class="explainer-recommendations-section">
                    <h4 style="color: #ffb900;"><?php esc_html_e('System Improvements', 'ai-explainer'); ?></h4>
                    <ul>
                        <?php foreach ($recommendations['system_improvements'] as $improvement): ?>
                            <li><?php echo esc_html($improvement); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($recommendations['monitoring_suggestions'])): ?>
                <div class="explainer-recommendations-section">
                    <h4 style="color: #00a0d2;"><?php esc_html_e('Monitoring Suggestions', 'ai-explainer'); ?></h4>
                    <ul>
                        <?php foreach ($recommendations['monitoring_suggestions'] as $suggestion): ?>
                            <li><?php echo esc_html($suggestion); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($recommendations['provider_specific'])): ?>
                <div class="explainer-recommendations-section">
                    <h4><?php esc_html_e('Provider-Specific Recommendations', 'ai-explainer'); ?></h4>
                    <?php foreach ($recommendations['provider_specific'] as $provider => $provider_recs): ?>
                        <h5><?php echo esc_html(ucfirst($provider)); ?></h5>
                        <ul>
                            <?php foreach ($provider_recs as $rec): ?>
                                <li><?php echo esc_html($rec); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php
        }
    }

    /**
     * Get CSS class for score
     *
     * @param float $score Score value
     * @return string CSS class
     */
    private function get_score_class($score) {
        if ($score >= 90) return 'explainer-score-excellent';
        if ($score >= 80) return 'explainer-score-good';
        if ($score >= 70) return 'explainer-score-fair';
        return 'explainer-score-poor';
    }
}

// Initialize the admin interface
new ExplainerPlugin_Quality_Testing_Admin();