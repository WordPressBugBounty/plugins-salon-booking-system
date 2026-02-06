<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var $statusCounts
 */
global $wp_locale;
$cell_class = $calendar->getAttendantMode() ? 'cal-cell0' : 'cal-cell1';
$weekStart = $calendar->getFrom();
?>


<div class="cal-week-box">
	<div class="cal-row-fluid cal-row-head">
		<?php if ($calendar->getAttendantMode()): ?>
			<div class="<?php echo $cell_class; ?>"></div>
		<?php endif;
		foreach ($stats as $stat) {
			$holiday = '';
			switch ($weekStart->format('N')) {
				case 0:
				case 6:
					$holiday = 'cal-day-weekend';
			} ?>
			<div class="<?php echo $cell_class; ?> <?php echo $holiday; ?>">
				<a href="#" class="calbar week-calbar" data-placement="<?php echo $weekStart->format('N') > 3 ? 'left' : 'top'; ?>" data-toggle="tooltip" data-html="true" data-day="<?php echo $weekStart->format('Y-m-d'); ?>" data-original-title='<?php echo $stat["text"]; ?>'>
					<?php if (isset($stat['free'])): ?>
						<span class="busy busy-<?php echo $stat['busy']; ?>" style="width: <?php echo $stat['busy']; ?>%"></span>
						<span class="free free-<?php echo $stat['free']; ?>" style="width: <?php echo $stat['free']; ?>%"></span>
					<?php endif; ?>
				</a>
				<?php echo $wp_locale->get_weekday($weekStart->format('w')); ?>
				<span class="week-day-date" data-cal-date="<?php echo $weekStart->format('Y-m-d'); ?>" data-cal-view="day"><?php echo $weekStart->format('j'); ?><small><?php echo $wp_locale->get_month_abbrev($wp_locale->get_month($weekStart->format('m'))); ?></small></span>
			</div><?php
						$weekStart->modify('1 day');
					} ?>
	</div>
	<hr>
	<?php echo $calendar->renderWeekDays(); ?>
</div>