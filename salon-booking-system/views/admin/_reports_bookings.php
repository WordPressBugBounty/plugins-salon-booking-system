<div class="sln-box sln-box--main">
	<h2 class="sln-box-title"><?php esc_html_e('Reports','salon-booking-system') ?></h2>
	<div class="row">
		<?php
		if ( ! function_exists( 'cal_days_in_month' ) ) {
            // phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended
			// Fallback in case the calendar extension is not loaded in PHP
			// Only supports Gregorian calendar
			function cal_days_in_month( $calendar, $month, $year ) {
				return (new SLN_DateTime)->setTime(0,0)->setDate($year,$month,1)->format('t');
			}
		}

		$report = SLN_Admin_Reports_AbstractReport::createReportObj($_GET);
		$report->build();
?>
	</div>
</div>
<?php

