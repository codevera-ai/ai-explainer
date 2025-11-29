/**
 * Quality Testing Admin JavaScript
 *
 * Handles admin interface interactions for AI response quality testing
 */

(function($) {
    'use strict';

    /**
     * Quality Testing Admin Controller
     */
    var QualityTestingAdmin = {
        
        /**
         * Initialize the admin interface
         */
        init: function() {
            this.bindEvents();
            this.initializeInterface();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Run tests form submission
            $('#quality-test-form').on('submit', this.handleRunTests.bind(this));
            
            // Export results button
            $('#export-results-btn').on('click', this.handleExportResults.bind(this));
            
            // Tab switching
            $(document).on('click', '.explainer-tab-btn', this.handleTabSwitch.bind(this));
        },

        /**
         * Initialize interface elements
         */
        initializeInterface: function() {
            // Hide spinner initially
            $('.spinner').hide();
        },

        /**
         * Handle run tests form submission
         */
        handleRunTests: function(e) {
            e.preventDefault();
            
            var $form = $(e.target);
            var $button = $('#run-tests-btn');
            var $spinner = $form.find('.spinner');
            
            // Validate form
            if (!this.validateTestForm($form)) {
                return;
            }

            // Prepare data
            var formData = {
                action: 'run_quality_tests',
                nonce: explainerQualityTesting.nonce,
                providers: $form.find('input[name="providers[]"]:checked').map(function() {
                    return $(this).val();
                }).get(),
                reading_levels: $form.find('input[name="reading_levels[]"]:checked').map(function() {
                    return $(this).val();
                }).get(),
                include_cross_provider_comparison: $form.find('input[name="include_cross_provider_comparison"]:checked').length > 0 ? 1 : 0,
                include_reading_level_validation: $form.find('input[name="include_reading_level_validation"]:checked').length > 0 ? 1 : 0,
                include_context_leakage_tests: $form.find('input[name="include_context_leakage_tests"]:checked').length > 0 ? 1 : 0,
                generate_detailed_report: $form.find('input[name="generate_detailed_report"]:checked').length > 0 ? 1 : 0
            };

            // Update UI state
            $button.prop('disabled', true).text(explainerQualityTesting.strings.runningTests);
            $spinner.show().addClass('is-active');

            // Show progress message
            this.showProgressMessage('Initializing quality tests...');

            // Make AJAX request
            $.ajax({
                url: explainerQualityTesting.ajaxUrl,
                type: 'POST',
                data: formData,
                timeout: 300000, // 5 minutes timeout
                success: this.handleTestSuccess.bind(this),
                error: this.handleTestError.bind(this),
                complete: function() {
                    // Reset UI state
                    $button.prop('disabled', false).text('Run Quality Tests');
                    $spinner.hide().removeClass('is-active');
                }
            });
        },

        /**
         * Validate test form
         */
        validateTestForm: function($form) {
            var providers = $form.find('input[name="providers[]"]:checked').length;
            var readingLevels = $form.find('input[name="reading_levels[]"]:checked').length;

            if (providers === 0) {
                alert('Please select at least one provider to test.');
                return false;
            }

            if (readingLevels === 0) {
                alert('Please select at least one reading level to test.');
                return false;
            }

            return true;
        },

        /**
         * Show progress message
         */
        showProgressMessage: function(message) {
            var $container = $('#test-results-container');
            
            if ($container.find('.explainer-testing-progress').length === 0) {
                $container.prepend(
                    '<div class="explainer-testing-progress">' +
                    '<p><strong>Running Tests:</strong> <span class="progress-text">' + message + '</span></p>' +
                    '<div class="progress-bar"><div class="progress-fill"></div></div>' +
                    '</div>'
                );
            } else {
                $container.find('.progress-text').text(message);
            }
            
            $container.show();
        },

        /**
         * Update progress message
         */
        updateProgressMessage: function(message, percent) {
            var $progressText = $('.progress-text');
            var $progressFill = $('.progress-fill');
            
            if ($progressText.length > 0) {
                $progressText.text(message);
            }
            
            if ($progressFill.length > 0 && percent !== undefined) {
                $progressFill.css('width', percent + '%');
            }
        },

        /**
         * Handle successful test completion
         */
        handleTestSuccess: function(response) {
            console.log('Quality tests completed successfully', response);
            
            if (response.success && response.data) {
                // Remove progress message
                $('.explainer-testing-progress').fadeOut(300, function() {
                    $(this).remove();
                });
                
                // Update results content
                $('#test-results-content').html(response.data.html);
                $('#test-results-container').show();
                
                // Show success message
                this.showNotice('success', explainerQualityTesting.strings.testsCompleted);
                
                // Scroll to results
                $('html, body').animate({
                    scrollTop: $('#test-results-container').offset().top - 50
                }, 1000);
                
            } else {
                this.handleTestError(response);
            }
        },

        /**
         * Handle test errors
         */
        handleTestError: function(response) {
            console.error('Quality tests failed', response);
            
            // Remove progress message
            $('.explainer-testing-progress').fadeOut(300, function() {
                $(this).remove();
            });
            
            var errorMessage = explainerQualityTesting.strings.testsFailed;
            
            if (response && response.data && response.data.message) {
                errorMessage += ': ' + response.data.message;
            } else if (response && response.responseText) {
                try {
                    var errorData = JSON.parse(response.responseText);
                    if (errorData.data && errorData.data.message) {
                        errorMessage += ': ' + errorData.data.message;
                    }
                } catch (e) {
                    errorMessage += ': ' + response.responseText.substring(0, 100);
                }
            }
            
            this.showNotice('error', errorMessage);
        },

        /**
         * Handle export results
         */
        handleExportResults: function(e) {
            e.preventDefault();
            
            var $button = $(e.target);
            var originalText = $button.text();
            
            // Update button state
            $button.prop('disabled', true).text(explainerQualityTesting.strings.exportingResults);
            
            // Prepare data
            var formData = {
                action: 'export_test_results',
                nonce: explainerQualityTesting.nonce,
                format: 'json' // Could be made configurable
            };
            
            // Make AJAX request
            $.ajax({
                url: explainerQualityTesting.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success && response.data) {
                        // Trigger download
                        QualityTestingAdmin.downloadData(
                            response.data.data,
                            response.data.filename,
                            response.data.mime_type
                        );
                        
                        QualityTestingAdmin.showNotice('success', explainerQualityTesting.strings.exportCompleted);
                    } else {
                        QualityTestingAdmin.handleExportError(response);
                    }
                },
                error: QualityTestingAdmin.handleExportError.bind(this),
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Handle export errors
         */
        handleExportError: function(response) {
            console.error('Export failed', response);
            
            var errorMessage = 'Export failed';
            if (response && response.data && response.data.message) {
                errorMessage += ': ' + response.data.message;
            }
            
            this.showNotice('error', errorMessage);
        },

        /**
         * Trigger data download
         */
        downloadData: function(data, filename, mimeType) {
            var blob = new Blob([data], { type: mimeType });
            var url = window.URL.createObjectURL(blob);
            
            var link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.style.display = 'none';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            window.URL.revokeObjectURL(url);
        },

        /**
         * Handle tab switching
         */
        handleTabSwitch: function(e) {
            var $button = $(e.target);
            var tab = $button.data('tab');
            
            if (!tab) return;
            
            // Update active button
            $('.explainer-tab-btn').removeClass('active');
            $button.addClass('active');
            
            // Update active panel
            $('.explainer-results-panel').removeClass('active');
            $('#' + tab + '-panel').addClass('active');
        },

        /**
         * Show admin notice
         */
        showNotice: function(type, message) {
            var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            
            var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Add dismiss button
            $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
            
            // Insert after h1
            $('.wrap h1').first().after($notice);
            
            // Handle dismiss
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(300, function() {
                    $notice.remove();
                });
            });
            
            // Auto-dismiss success notices after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut(300, function() {
                        $notice.remove();
                    });
                }, 5000);
            }
        },

        /**
         * Format score for display
         */
        formatScore: function(score) {
            return Math.round(score * 10) / 10;
        },

        /**
         * Get score CSS class
         */
        getScoreClass: function(score) {
            if (score >= 90) return 'explainer-score-excellent';
            if (score >= 80) return 'explainer-score-good';
            if (score >= 70) return 'explainer-score-fair';
            return 'explainer-score-poor';
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        QualityTestingAdmin.init();
    });

    // Expose for debugging
    window.QualityTestingAdmin = QualityTestingAdmin;

})(jQuery);