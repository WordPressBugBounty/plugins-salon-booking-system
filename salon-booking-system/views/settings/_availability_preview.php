<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
if (!is_array($availabilities)) {
    $availabilities = array();
}

$settings = SLN_Plugin::getInstance()->getSettings();
$interval = $settings->get('interval', 30);
$weekStart = $settings->get('week_start', 1);
$daysMapping = SLN_Func::getDays();
?>

<div class="availability-preview-box sln-box sln-box--main sln-box--haspanel">
    <h2 class="sln-box-title sln-box__paneltitle"><?php esc_html_e('Opening hours preview', 'salon-booking-system'); ?>
        <span class="block"><?php esc_html_e('Visual overview of your booking availability based on current rules and session duration.', 'salon-booking-system'); ?></span>
    </h2>
    <div class="collapse sln-box__panelcollapse">
        <div class="row">
            <div class="col-xs-12">
                <div class="sln-availability-preview-info">
                    <div class="row">
                        <div class="col-xs-12 col-md-6">
                            <div class="preview-info-item">
                                <div class="preview-info-icon">
                                    <svg fill="#0978bd" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                         viewBox="0 0 24 24">
                                        <path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm7 14h-8v-9h2v7h6v2z"/>
                                    </svg>
                                </div>
                                <div class="preview-info-content">
                                    <div class="preview-info-label"><?php esc_html_e('Session Duration', 'salon-booking-system'); ?></div>
                                    <div class="preview-info-value"
                                         id="preview-session-info"><?php echo $interval; ?><?php esc_html_e('minutes', 'salon-booking-system'); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xs-12 col-md-6">
                            <div class="preview-info-item">
                                <div class="preview-info-icon">
                                    <svg fill="#0978bd" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                         viewBox="0 0 24 24">
                                        <path d="M6 22v-16h16v7.543c0 4.107-6 2.457-6 2.457s1.518 6-2.638 6h-7.362zm18-7.614v-10.386h-20v20h10.189c3.163 0 9.811-7.223 9.811-9.614zm-10 1.614h-5v-1h5v1zm5-4h-10v1h10v-1zm0-3h-10v1h10v-1zm2-7h-19v19h-2v-21h21v2z"/>
                                    </svg>
                                </div>
                                <div class="preview-info-content">
                                    <div class="preview-info-label"><?php esc_html_e('Active Rules', 'salon-booking-system'); ?></div>
                                    <div class="preview-info-value"
                                         id="preview-rules-count"><?php echo count($availabilities); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sln-availability-preview-table-wrapper">
                    <div class="sln-availability-preview-table"></div>

                    <div class="sln-availability-preview-loading" style="display: none;">
                        <div class="loading-spinner"></div>
                        <p><?php esc_html_e('Updating preview...', 'salon-booking-system'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script type="text/javascript">
        window.sln_availability_preview_config = {
            interval: <?php echo (int)$interval; ?>,
            week_start: <?php echo (int)$weekStart; ?>,
            days_mapping: <?php echo wp_json_encode($daysMapping); ?>
        };
    </script>
</div>
