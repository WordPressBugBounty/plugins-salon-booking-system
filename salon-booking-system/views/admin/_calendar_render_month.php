<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/** @var SLN_Settings $settings
 * @var $bookings
 * @var $format
 * @var $calendar
 * @var $statusCounts
 */

global $wp_locale;
$day = 0;
?>


<div class="cal-row-fluid cal-row-head">
	<?php
	$weekDay = $settings->get('week_start');
	do {
	?>
		<div class="cal-cell1">
			<?php echo $wp_locale->get_weekday($weekDay); ?>
		</div>
	<?php
		$weekDay = ++$weekDay % 7;
	} while ($weekDay != $settings->get('week_start'));
	?>
</div>

<div class="cal-month-box">
	<?php for ($week_number = 0; $week_number < 6 && !$calendar->isStopIteration(); $week_number++): ?>
		<div class="cal-row-fluid cal-before-eventlist">
			<?php for ($i = 1; $i < 8; $i++) : ?>
				<div class="cal-cell1 cal-cell" data-cal-row="-day<?php echo $i; ?>">
					<?php echo $calendar->renderMonthDay($week_number, $day++, $stats); ?>
				</div>
			<?php endfor; ?>
		</div>
	<?php endfor; ?>
</div>