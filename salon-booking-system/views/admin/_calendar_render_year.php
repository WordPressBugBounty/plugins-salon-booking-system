<?php
/**
 * @var $calendar
 * @var $bookings
 * @var $settings
 * @var $format
*/

global $wp_locale;
?>
<div class="cal-year-box">
	<div class="row row-fluid">
		<?php for($i = 0; $i < 12; $i++): ?>
			<div class="span3 col-xs-4 col-md-3 cal-cell" data-cal-row="-month<?php echo $i+1; ?>">
				<span class="pull-right" data-cal-date="<?php echo date_modify($calendar->getFrom(), $i . ' month')->format('Y-m-d') ?>" data-cal-view="month"><?php echo $wp_locale->get_month($i+1);?></span>
				<?php $count = $calendar->countBookingsByMonth($i);
				if($count): ?>
					<small class="cal-events-num badge badge-importent pull-left"><?php echo $count; ?></small>
				<?php endif; ?>
			</div>
		<?php endfor; ?>
	</div>
</div>
