<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**@var SLN_Plugin $plugin
 * @var $booking
 * @var $day
 * @var $dayClass
 * @var start
 * @var end
 */

$today = new DateTime();
$cur_date = null;
if ($today->format('dY-m-d') == $start->format('dY-m-d')) {
	$cur_date = ' cal-day-today';
}
?>
<div class="cal-month-day <?php echo $dayClass;
													echo count($booking) ? ' has-events' : '';
													echo $cur_date; ?>">
	<a class="calbar month-calbar" href="#" data-day="<?php echo $day; ?>" data-booking-date="<?php echo $start->format('Y-m-d'); ?>" data-booking-count="<?php echo count($booking); ?>" data-has-bookings="<?php echo count($booking) > 0 ? '1' : '0'; ?>">
		<?php if (isset($stats['free'])): ?>
			<span class="busy busy-<?php echo $stats['busy']; ?>" style="width: <?php echo $stats['busy']; ?>%"></span>
			<span class="free free-<?php echo $stats['free']; ?>" style="width: <?php echo $stats['free']; ?>%"></span>
		<?php endif; ?>
	</a>
	<!-- Booking data for tooltip (lightweight JSON) -->
	<?php 
		$cancelledCount = 0;
		$pendingCount = 0;
		if (count($booking)): 
			$bookingData = array();
			foreach ($booking as $item) {
				if (isset($item['id'])) {
					$b = $plugin->createBooking($item['id']);
					if ($b) {
						$status = $b->getStatus();
						
						// Count cancelled bookings while building tooltip data
						if ($status == SLN_Enum_BookingStatus::CANCELED) {
							$cancelledCount++;
						}
						
						// Count pending bookings (pending or pending payment)
						if ($status == SLN_Enum_BookingStatus::PENDING || $status == SLN_Enum_BookingStatus::PENDING_PAYMENT) {
							$pendingCount++;
						}
						
						// Get shop name for Multi-Shop support
						$shopName = '';
						if (class_exists('\SalonMultishop\Addon')) {
							$shopId = get_post_meta($b->getId(), '_sln_booking_shop', true);
							if (!empty($shopId)) {
								$shopName = get_the_title($shopId);
							}
						}
						
						$bookingData[] = array(
							'id' => $b->getId(),
							'firstName' => $b->getFirstName(),
							'lastName' => $b->getLastName(),
							'startsAt' => $b->getStartsAt()->format('Y-m-d H:i:s'),
							'shopName' => $shopName,
							'status' => $status
						);
					}
				}
			}
	?>
	<script type="application/json" class="calbar-booking-data" data-date="<?php echo $start->format('Y-m-d'); ?>"><?php echo wp_json_encode($bookingData); ?></script>
	<?php endif; ?>
	<!-- Bokkings count -->
	<div class="booking-count-wrapper" data-cal-date="<?php echo $start->format('Y-m-d'); ?>" data-cal-view="day">
		<span class="booking-count"><?php echo count($booking); ?></span>
		<?php if ($cancelledCount > 0): ?>
			<span class="booking-count-cancelled"><?php echo $cancelledCount; ?></span>
		<?php endif; ?>
		<?php if ($pendingCount > 0): ?>
			<span class="booking-count-pending"><?php echo $pendingCount; ?></span>
		<?php endif; ?>
	</div>
	<span class="pull-right month-day-number" data-cal-date="<?php echo $start->format('Y-m-d'); ?>" data-cal-view="day" data-toggle="tooltip" title="<?php esc_html_e('Go to daily view', 'salon-booking-system') ?>"><?php echo $day ?></span>
	<?php if (count($booking)): ?>
		<!-- <div class="events-list <?php echo (count($booking) > 12) ? 'event-list--overflow' : ''; ?>" data-cal-start="<?php echo $start->format('Y-m-d'); ?>" data-cal-end="<?php echo $end->format('Y-m-d') ?>">
			<?php for ($i = 0; $i < count($booking) && $i <= 12; $i++): ?>
				<a href="<?php echo $booking[$i]['id'] ? get_edit_post_link($booking[$i]['id']) : 'javascript:void(0)' ?>" data-event-id="<?php echo $booking[$i]['id'] ?>" data-event-class="<?php echo $booking[$i]['class'] ?>" class="pull-left event <?php echo $booking[$i]['class']; ?>" data-toggle="tooltip" data-html="true"></a>
			<?php endfor; ?>
		</div> -->
		<div class="events-list--title">
			<?php foreach ($booking as $item): ?>
				<div class="sln-event-popup" data-event-id="<?php echo $item['id']; ?>">
					<?php echo $item['title']; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<div class="event-list--sliders hide">
			<div id="cal-slide-content" class="cal-event-list">
				<ul class="unstyled list-unstyled">
					<?php foreach ($booking as $item): ?>
						<li>
							<span class="pull-left event<?php echo $item['class']; ?>"></span>&nbsp;
							<a href="<?php echo get_edit_post_link($item['id']); ?>" data-event-id="<?php echo $item['id']; ?>" data-event-class="<?php echo $item['class']; ?>" class="event-item"><?php echo str_replace('<br>', ' ', $item['title']); ?><strong><?php echo $item['amount']; ?></strong> <span class="sln-calendar-event-callto"><?php esc_html_e('Edit reservation', 'salon-booking-system'); ?></span></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	<?php endif; ?>
</div>