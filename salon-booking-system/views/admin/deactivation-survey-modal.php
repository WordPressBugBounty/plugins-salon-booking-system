<?php
/**
 * Deactivation Survey Modal Template
 */
?>

<div id="sln-deactivation-survey-overlay" class="sln-deactivation-survey-overlay">
    <div class="sln-deactivation-survey-modal">
        <div class="sln-survey-content">
            <div class="sln-survey-header">
                <h2>Help Us Improve (Takes 10 Seconds)</h2>
                <p><strong>We read every response.</strong> Your feedback directly shapes our roadmap and helps thousands of salon owners.</p>
            </div>

            <div class="sln-survey-body">
                <div class="sln-survey-question">
                    <label class="sln-survey-label">What stopped you from using Salon Booking System?</label>
                    <div class="sln-survey-options">
                        <div class="sln-survey-option" data-reason="setup_too_complex">
                            <input type="radio" name="deactivation_reason" id="reason_complex" value="setup_too_complex">
                            <label for="reason_complex">
                                <span class="dashicons dashicons-admin-settings" style="color: #e74c3c; margin-right: 8px;"></span>
                                Setup too complex
                            </label>
                        </div>

                        <div class="sln-survey-option" data-reason="not_what_expected">
                            <input type="radio" name="deactivation_reason" id="reason_expected" value="not_what_expected">
                            <label for="reason_expected">
                                <span class="dashicons dashicons-warning" style="color: #f39c12; margin-right: 8px;"></span>
                                It's not what I expected
                            </label>
                        </div>

                        <div class="sln-survey-option" data-reason="missing_feature">
                            <input type="radio" name="deactivation_reason" id="reason_features" value="missing_feature">
                            <label for="reason_features">
                                <span class="dashicons dashicons-admin-plugins" style="color: #9b59b6; margin-right: 8px;"></span>
                                Missing a key feature <span style="color: #646970; font-weight: 400;">(which one?)</span>
                            </label>
                        </div>

                        <div class="sln-survey-option" data-reason="just_trying">
                            <input type="radio" name="deactivation_reason" id="reason_testing" value="just_trying">
                            <label for="reason_testing">
                                <span class="dashicons dashicons-admin-tools" style="color: #3498db; margin-right: 8px;"></span>
                                Just trying it out
                            </label>
                        </div>
                    </div>
                </div>

                <div class="sln-survey-question">
                    <label class="sln-survey-label" for="deactivation_feedback">
                        What would have made you stay? <span style="color: #646970; font-weight: 400;">(optional but super helpful!)</span>
                    </label>
                    <textarea 
                        id="deactivation_feedback" 
                        class="sln-survey-textarea" 
                        placeholder="E.g., 'Needed a setup wizard', 'Missing SMS reminders', 'Too confusing to add services'..."
                        maxlength="500"
                    ></textarea>
                </div>
            </div>

            <div class="sln-survey-footer">
                <a href="#" class="sln-survey-skip" id="sln-survey-skip">Skip & Deactivate</a>
                <button type="button" class="sln-survey-submit" id="sln-survey-submit" disabled>
                    Submit & Deactivate
                </button>
            </div>
        </div>

        <div class="sln-survey-loading" style="display: none;">
            <div class="sln-survey-spinner"></div>
            <p style="color: #646970;">Sending feedback...</p>
        </div>
    </div>
</div>
