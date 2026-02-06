/**
 * Deactivation Survey Modal
 * Shows a feedback modal when user tries to deactivate the plugin
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        
        var deactivateUrl = '';
        var modalShown = false;

        /**
         * Intercept deactivation link click
         */
        $(document).on('click', 'tr[data-plugin="' + slnDeactivationSurvey.plugin_slug + '"] .deactivate a', function(e) {
            e.preventDefault();
            
            // Store the deactivation URL
            deactivateUrl = $(this).attr('href');
            
            // Show survey modal
            showSurveyModal();
        });

        /**
         * Show the survey modal
         */
        function showSurveyModal() {
            if (modalShown) {
                return; // Already showing
            }
            
            modalShown = true;
            $('#sln-deactivation-survey-overlay').fadeIn(200);
            
            // Focus first radio option
            setTimeout(function() {
                $('#reason_complex').focus();
            }, 300);
        }

        /**
         * Hide the survey modal
         */
        function hideSurveyModal() {
            $('#sln-deactivation-survey-overlay').fadeOut(200);
            modalShown = false;
        }

        /**
         * Enable/disable submit button based on selection
         */
        $(document).on('change', 'input[name="deactivation_reason"]', function() {
            $('#sln-survey-submit').prop('disabled', false);
        });

        /**
         * Handle option click (select the radio)
         */
        $(document).on('click', '.sln-survey-option', function() {
            $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
            
            // Visual feedback
            $('.sln-survey-option').removeClass('selected');
            $(this).addClass('selected');
        });

        /**
         * Skip survey - proceed with deactivation
         */
        $(document).on('click', '#sln-survey-skip', function(e) {
            e.preventDefault();
            
            // Proceed with deactivation without survey data
            proceedWithDeactivation();
        });

        /**
         * Submit survey
         */
        $(document).on('click', '#sln-survey-submit', function(e) {
            e.preventDefault();
            
            var reason = $('input[name="deactivation_reason"]:checked').val();
            var feedback = $('#deactivation_feedback').val().trim();
            
            if (!reason) {
                alert('Please select a reason before submitting.');
                return;
            }

            // Show loading state
            $('.sln-survey-content').hide();
            $('.sln-survey-loading').show();
            
            // Send survey data via AJAX
            $.ajax({
                url: slnDeactivationSurvey.ajax_url,
                type: 'POST',
                data: {
                    action: 'sln_deactivation_survey',
                    nonce: slnDeactivationSurvey.nonce,
                    reason: reason,
                    feedback: feedback,
                    rating: 0 // Can be extended later
                },
                success: function(response) {
                    if (response.success) {
                        console.log('✅ Deactivation survey submitted successfully');
                        
                        // Show brief thank you message
                        $('.sln-survey-loading').html(
                            '<div style="text-align: center; padding: 30px;">' +
                            '<div style="font-size: 48px; margin-bottom: 15px;">✓</div>' +
                            '<h3 style="margin: 0 0 10px; color: #1d2327;">Thank you for your feedback!</h3>' +
                            '<p style="color: #646970; margin: 0;">Deactivating plugin...</p>' +
                            '</div>'
                        );
                        
                        // Proceed with deactivation after brief delay
                        setTimeout(function() {
                            proceedWithDeactivation();
                        }, 1500);
                    } else {
                        console.error('❌ Survey submission failed:', response);
                        // Proceed anyway - don't block deactivation
                        proceedWithDeactivation();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Survey submission error:', error);
                    // Proceed anyway - don't block deactivation
                    proceedWithDeactivation();
                }
            });
        });

        /**
         * Proceed with plugin deactivation
         */
        function proceedWithDeactivation() {
            hideSurveyModal();
            
            // Navigate to the deactivation URL
            if (deactivateUrl) {
                window.location.href = deactivateUrl;
            }
        }

        /**
         * Close modal on overlay click
         */
        $(document).on('click', '#sln-deactivation-survey-overlay', function(e) {
            if (e.target === this) {
                // User clicked outside modal - treat as skip
                proceedWithDeactivation();
            }
        });

        /**
         * ESC key to close
         */
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && modalShown) {
                proceedWithDeactivation();
            }
        });

        console.log('📊 SLN Deactivation Survey loaded');
        console.log('📍 Days active:', slnDeactivationSurvey.days_active);
        console.log('📈 Setup progress:', slnDeactivationSurvey.setup_progress + '%');
        console.log('✅ First booking:', slnDeactivationSurvey.completed_first_booking ? 'Yes' : 'No');
    });

})(jQuery);
