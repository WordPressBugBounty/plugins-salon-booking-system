<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * 
*/
$weekStart = $calendar->getFrom();
$isPro = (defined('SLN_VERSION_PAY') && SLN_VERSION_PAY);

if($calendar->getAttendantMode()){
	foreach($attendant as $att): ?>
		<div class="cal-row-fluid">
			<div class="weekday-attendant cal-cell0">
				<div class="day-highlight dh-attendant"><?php echo SLN_Plugin::getInstance()->createAttendant($att)->getName(); ?></div>
			</div>
			<?php for($i = 0; $i < 7; $i++): ?>
				<div class="weekday<?php echo $i;?> cal-cell0 <?php echo $calendar->isAttendantAvailable($att, $i, true) ? '' : 'not-available'; ?>">
					<?php foreach($bookings[$att] as $booking):
						if($booking->from == $weekStart->format('N')): ?>
							<div class="day-highlight dh-<?php echo $booking->displayClass; ?>" data-event-class="<?php echo $booking->displayClass; ?>" data-toggle="tooltip" data-placement="right" data-option="day" data-tooltip-id="<?php echo $booking->id; ?>" data-html="true" title="<div class='sln-mouth-tooltip'><?php echo $booking->title; ?></div>
                                <div style='display: grid; margin-top:0.5rem; margin-left:0.7rem;'>
                                    <span class='sln-value-tooltip' style='text-align: left;'>
                                        <div class='head-info-tooltip'><?php echo $booking->amount?></div>
                                        <div class='title-info-tooltip'><?php esc_html_e('Total amount', 'salon-booking-system'); ?></div>
                                    </span>
                                    <span class='sln-value-tooltip' id='discount-tooltip' style='text-align: left; margin-top:0.5rem; <?php echo $isPro ? '': 'display: none;' ?>'>
                                        <div class='head-info-tooltip'><?php echo $isPro ? $booking->discount : '- -' ?></div>
                                        <div class='title-info-tooltip'><?php esc_html_e('Discount', 'salon-booking-system');?></div>
                                    </span>
                                    <span class='sln-value-tooltip' id='deposit-tooltip' style='text-align: left; margin-top:0.5rem; <?php echo $isPro ? '': 'display: none;' ?>'>
                                        <div class='head-info-tooltip'><?php echo $isPro ? $booking->deposit : '- -' ?></div>
                                        <div class='title-info-tooltip'><?php esc_html_e('Deposit', 'salon-booking-system'); ?></div>
                                    </span>
                                    <span class='sln-value-tooltip' id='due-tooltip' style='text-align: left; margin-top:0.5rem; <?php echo $isPro ? '': 'display: none;' ?>'>
                                        <div class='head-info-tooltip'><?php echo $isPro ? $booking->due : '- -' ?></div>
                                        <div class='title-info-tooltip'><?php esc_html_e('Due', 'salon-booking-system'); ?></div>
                                    </span>
                                </div>
                                <div class='icons-container' style='margin-top:0.5rem;' data-event-id='<?php echo $booking->id; ?>' data-event-class='<?php echo $booking->displayClass; ?>'>
                                    <div>
                                        <a style='text-decoration: none;' class='sln-pen-icon-tooltip' href='' data-bookingid='<?php echo $booking->id;?>></a>
                                    </div>
                                    <div data-dup-icon='<?php echo $isPro ? 'true': 'false' ?>' >
                                        <a class='sln-dup-icon-tooltip' data-bookingid='<?php echo $booking->id; ?>'></a>
                                    </div>
                                    <div>
                                        <a target='_blanck' class='sln-user-icon-tooltip <?php echo $plugin->getSettings()->get('enabled_force_guest_checkout') ? 'disabled' : '' ?>' href='admin.php?page=salon-customers&id=<?php echo $booking->customerId; ?>'></a>
                                    </div>
                                    <div class='sln-trash-icon-tooltip'></div>
                                </div>
                                <div class='sln-confirm-delete-tooltip'>
                                    <div style='display:block;'>
                                        <div><strong><?php esc_html_e('Are you sure?', 'salon-booking-system');?></strong></div>
                                        <div style='display:inline-block;'>
                                            <a class='sln-dtn-danger-tooltip' href='<?php echo get_delete_post_link($booking->id); ?>'>
                                                <?php esc_html_e('Yes, delete.', 'salon-booking-system'); ?>
                                            </a>
                                            <a class='sln-dtn-close-tooltip' aria-label='Close'>
                                                <span aria-hidden='true'>&times;</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                </div>">
                                <span class="sln-week-days-tooltip" data-tooltip-id="<?php echo $booking->id; ?>">
                                	<span data-event-id="<?php echo $booking->id; ?>" class="event-item cal-event-week event<?php echo $booking->id; ?>"><?php echo $booking->title; ?></span>
                                	<span class="sln-event-header-more-icon sln-event-header-more-icon-vertical" data-tooltip-id="<?php echo $booking->id;?>"></span>
                                </span>
                            </div>
                        <?php endif;
                    endforeach; ?>
                </div>
			    <?php $weekStart->modify('1 day');
            endfor; ?>
		</div>
	<?php endforeach;
}else{ ?>
	<div class="cal-row-fluid">
		<?php for($i = 0; $i < 7; $i++): ?>
			<div class="weekday<?php echo $i; ?> cal-cell1">
				<?php foreach($bookings as $booking): 
                    if($booking->from == $weekStart->format('N')): ?>
    					<div class="day-highlight dh-<?php echo $booking->displayClass; ?>" data-event-class="<?php echo $booking->displayClass; ?>" data-toggle="tooltip" data-placement="right" data-option="day" data-tooltip-id="<?php echo $booking->id; ?>" data-html="true" title="<div class='tootltip-inner'><div class='sln-mouth-tooltip'><?php echo $booking->title; ?></div>
                            <div style='display: grid; margin-top:0.5rem; margin-left:0.7rem;'>
                                <span class='sln-value-tooltip' style='text-align: left;'>
                                    <div class='head-info-tooltip'><?php echo $booking->amount; ?></div>
                                    <div class='title-info-tooltip'><?php esc_html_e('Total amount', 'salon-booking-system'); ?></div>
                                </span>
                                <span class='sln-value-tooltip' id='discount-tooltip' style='text-align: left; margin-top:0.5rem; <?php echo $isPro ? '' : 'display: none;' ?>'>
                                    <div class='head-info-tooltip'><?php echo $isPro ? $booking->discount : '- -' ?></div>
                                    <div class='title-info-tooltip'><?php esc_html_e('Discount', 'salon-booking-system'); ?></div>
                                </span>
                                <span class='sln-value-tooltip' id='deposit-tooltip' style='text-align: left; margin-top:0.5rem; <?php echo $isPro ? '': 'display: none;' ?>'>
                                    <div class='head-info-tooltip'><?php echo $isPro ? $booking->deposit : '- -' ?></div>
                                    <div class='title-info-tooltip'><?php esc_html_e('Deposit', 'salon-booking-system'); ?></div>
                                </span>
                                <span class='sln-value-tooltip' id='due-tooltip' style='text-align: left; margin-top:0.5rem; <?php echo $isPro ? '': 'display: none;' ?>'>
                                    <div class='head-info-tooltip'><?php echo $isPro ? $booking->due : '- -' ?></div>
                                    <div class='title-info-tooltip'><?php esc_html_e('Due', 'salon-booking-system'); ?></div>
                                </span>
                            </div>
                            <div class='icons-container' style='margin-top:0.5rem;' data-event-id='<?php echo $booking->id; ?>' data-event-class='<?php echo $booking->displayClass; ?>'>
                                <div>
                                    <a style='text-decoration: none;' class='sln-pen-icon-tooltip' href='' data-bookingid='<?php echo $booking->id; ?>'></a>
                                </div>
                                <div data-dup-icon='<?php echo $isPro ? 'true': 'false' ?>' >
                                    <a class='sln-dup-icon-tooltip' data-bookingid='<?php echo $booking->id; ?>'></a>
                                </div>
                                <div>
                                    <a target='_blanck' class='sln-user-icon-tooltip <?php echo $plugin->getSettings()->get('enabled_force_guest_checkout') ? 'disabled' : '' ?>' href='admin.php?page=salon-customers&id=<?php echo $booking->customerId; ?>'></a>
                                </div>
                                <div class='sln-trash-icon-tooltip'></div>
                            </div>
                            <div class='sln-confirm-delete-tooltip'>
                                <div style='display:block;'>
                                    <div><strong><?php esc_html_e('Are you sure?', 'salon-booking-system');?></strong></div>
                                    <div style='display:inline-block;'>
                                        <a class='sln-dtn-danger-tooltip' href='<?php echo get_delete_post_link($booking->id); ?>'>
                                            <?php esc_html_e('Yes, delete.', 'salon-booking-system'); ?>
                                        </a>
                                        <a class='sln-dtn-close-tooltip' aria-label='Close'>
                                            <span aria-hidden='true'>&times;</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            </div></div>">
                            <span class="sln-week-days-tooltip" data-tooltip-id="<?php echo $booking->id; ?>">
                            	<span data-event-id="<?php echo $booking->id; ?>" class="event-item cal-event-week event<?php echo $booking->id; ?>"><?php echo $booking->title; ?></span>
                            	<span class="sln-event-header-more-icon sln-event-header-more-icon-vertical" data-tooltip-id="<?php echo $booking->id; ?>"></span>
                            </span>
    					</div>
                    <?php endif;
				endforeach; ?>
			</div>
		  <?php $weekStart->modify('1 day');
        endfor; ?>
	</div>
<?php } ?>