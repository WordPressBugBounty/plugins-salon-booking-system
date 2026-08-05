<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
 // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<div id="sln-setting-error" class="updated error">
    <p><?php printf( __('You are going to reach the bookings limit for the Salon Booking free version. <a href="%s" target="blank">Please upgrade Salon Booking to a PRO version</a>.','salon-booking-system'), esc_url( defined('SLN_PRICING_URL') ? SLN_PRICING_URL : 'https://www.salonbookingsystem.com/plugin-pricing-2/' ) ) ?>

    <p><?php _e('<strong>Do you want a 20% discount ? <a href="http://salonbookingsystem.com/invite-friends-get-20-discount-first-purchase/" target="blank">INVITE YOUR FRIENDS!</a></strong></p>','salon-booking-system');?></p>
</div>

