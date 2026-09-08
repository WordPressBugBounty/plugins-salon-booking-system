<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$variant         = isset($variant) ? $variant : 'notice';
$step            = isset($step) ? (int) $step : 1;
$review_url      = isset($review_url) ? $review_url : SLN_Admin_ReviewRequest::REVIEW_URL;
$nonce           = isset($nonce) ? $nonce : '';
$bookings_count  = isset($bookings_count) ? (int) $bookings_count : SLN_Admin_ReviewRequest::MIN_BOOKINGS;
if ($bookings_count < 1) {
    $bookings_count = SLN_Admin_ReviewRequest::MIN_BOOKINGS;
}
$bookings_html   = '<strong>' . esc_html(number_format_i18n($bookings_count)) . '</strong>';
$step_class      = 2 === $step ? 'is-step-2' : 'is-step-1';
$wrap_class      = 'banner' === $variant
    ? 'sln-review-request-wrap sln-review-request-wrap--banner'
    : 'notice sln-review-request-wrap sln-review-request-wrap--notice';
?>
<div class="<?php echo esc_attr($wrap_class); ?>">
    <div class="sln-review-request <?php echo esc_attr($step_class); ?>" role="region" aria-labelledby="sln-review-request-title">
        <button type="button" class="sln-review-request__dismiss" onclick="slnReviewRequestDismiss('later')" aria-label="<?php esc_attr_e('Dismiss this notice', 'salon-booking-system'); ?>">
            <span aria-hidden="true">&times;</span>
        </button>

        <div class="sln-review-request__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="24" height="24" xmlns="http://www.w3.org/2000/svg" focusable="false">
                <path fill="currentColor" d="M12 2.6l2.55 6.18 6.75.62-5.14 4.42 1.54 6.58L12 16.86 6.3 20.4l1.54-6.58-5.14-4.42 6.75-.62L12 2.6z"/>
            </svg>
        </div>

        <div class="sln-review-request__main">
            <div class="sln-review-request__step sln-review-request__step--1">
                <h2 id="sln-review-request-title" class="sln-review-request__title">
                    <?php
                    echo wp_kses(
                        sprintf(
                            /* translators: %s: number of successful bookings, already wrapped in <strong>. */
                            _n(
                                'You\'ve taken %s booking with Salon Booking System',
                                'You\'ve taken %s bookings with Salon Booking System',
                                $bookings_count,
                                'salon-booking-system'
                            ),
                            $bookings_html
                        ),
                        array('strong' => array())
                    );
                    ?>
                </h2>
                <p class="sln-review-request__text">
                    <?php esc_html_e('Is it helping the salon?', 'salon-booking-system'); ?>
                </p>
                <div class="sln-review-request__actions">
                    <button type="button" class="sln-review-request__btn sln-review-request__btn--primary sln-review-request-yes" onclick="slnReviewRequestDismiss('positive')">
                        <?php esc_html_e('Yes, it\'s helping', 'salon-booking-system'); ?>
                    </button>
                    <button type="button" class="sln-review-request__btn sln-review-request__btn--ghost" onclick="slnReviewRequestDismiss('done')">
                        <?php esc_html_e('Not really', 'salon-booking-system'); ?>
                    </button>
                </div>
            </div>

            <div class="sln-review-request__step sln-review-request__step--2">
                <h2 class="sln-review-request__title">
                    <?php esc_html_e('Would you rate it on WordPress.org?', 'salon-booking-system'); ?>
                </h2>
                <p class="sln-review-request__text">
                    <?php esc_html_e('About a minute. Reviews from salon owners are how other salons find the free plugin.', 'salon-booking-system'); ?>
                </p>
                <div class="sln-review-request__actions">
                    <a href="<?php echo esc_url($review_url); ?>" target="_blank" rel="noopener" class="sln-review-request__btn sln-review-request__btn--primary" onclick="slnReviewRequestDismiss('reviewed')">
                        <?php esc_html_e('Rate it on WordPress.org', 'salon-booking-system'); ?>
                        <span class="sln-review-request__stars" aria-hidden="true">★★★★★</span>
                    </a>
                    <button type="button" class="sln-review-request__btn sln-review-request__btn--ghost" onclick="slnReviewRequestDismiss('later')">
                        <?php esc_html_e('Remind me in 30 days', 'salon-booking-system'); ?>
                    </button>
                    <button type="button" class="sln-review-request__link" onclick="slnReviewRequestDismiss('done')">
                        <?php esc_html_e('I already left a review', 'salon-booking-system'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function slnReviewRequestDismiss(mode) {
    var $card = jQuery('.sln-review-request');
    if (mode === 'positive') {
        $card.removeClass('is-step-1').addClass('is-step-2');
    } else {
        jQuery('.sln-review-request-wrap').fadeOut();
    }
    jQuery.post(ajaxurl, {
        action: 'sln_review_request_dismiss',
        mode: mode,
        security: '<?php echo esc_attr($nonce); ?>'
    });
}
</script>
