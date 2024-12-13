<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * 
*/

$isPro = defined('SLN_VERSION_PAY') && SLN_VERSION_PAY;

?>
<a class="calbar day-calbar" data-toggle="tooltip" href="#" data-day="<?php echo $start->format('Y-m-d'); ?>" data-html="true" data-original-title='<?php echo $stats[$start->format('Y-m-d')]["text"];?>'>
    <?php if(isset($stats[$start->format('Y-m-d')]['free'])): ?>
        <span class="busy" style="width: <?php echo $stats[$start->format('Y-m-d')]['busy']; ?>%"></span>
        <span class="free" style="width: <?php echo $stats[$start->format('Y-m-d')]['free']; ?>%"></span>
    <?php endif; ?>
</a>
<div id="cal-day-box">
	<div class="cal-day-panel__wrapper clearfix">
		<div class="row-fluid clearfix cal-row-head">
			<?php if(!empty($headers) && count($headers)): ?>
				<div class="cal-day-assistants">
					<?php foreach($headers as $head):
						if(!is_null($head)): ?>
							<div class="cal-day-assistant" data-assistant="<?php echo $head['id']; ?>"><?php echo $head['name']; ?></div>
						<?php else: ?>
							<div class="cal-day-assistant"></div>
						<?php endif;
					endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<div id="cal-day-panel" class="clearfix">
			<?php if(!empty($headers) && isset($headers)){
				foreach($headers as $attCol => $att){
					if(!empty($att)){
						for($line = 0; $line < $lines; $line++):
							$holiday_by_line = $calendar->hasHolidaysByLine($line); ?>
							<div style="margin-left: <?php echo ($attCol+1)*200; ?>px; top: <?php echo $line * 100; ?>px;" class="att-time-slot <?php echo $calendar->hasAttendantHoliday($line, $att['id']) && !in_array($calendar->getTimeByLine($line), $att['unavailable_times']) || $holiday_by_line ? 'blocked' : ''; ?>" data-index="<?php echo $line; ?>" data-att-id="<?php echo $att['id']; ?>" <?php echo ($holiday_by_line) ? 'title="'. __('Holiday rule', 'salon-booking-system').'"' : ''; ?>>
								<button type="button" class="sln-btn sln-btn--cal-day-select sln-btn--icon sln-icon--checkmark"><?php esc_html_e('SELECT', 'salon-booking-system'); ?></button>
								<div class="att-row-actions">
									<span class="cal-day-click-tip"><?php esc_html_e('Click on the "ending time" row', 'salon-booking-system')?></span>
									<button type="button" class="sln-btn sln-btn--cal-day sln-btn--cal-day--add sln-btn--icon sln-icon--plus" data-action="add-event-by-date" data-event-date="<?php echo $start->format('Y-m-d'); ?>"
	                                    data-event-time="<?php echo $calendar->getTimeByLine($line); ?>"
	                                    data-att-id="<?php echo $att['id']; ?>"
	                                    data-pos="<?php echo $attCol; ?>"
		                            ></button>
		                            <button
		                                    type="button"
		                                    class="block_date sln-btn sln-btn--cal-day sln-btn--cal-day--lock sln-btn--icon sln-icon--lock"
		                                    data-att-id="<?php echo $att['id']; ?>"
		                            ></button>
		                        </div>
		                    </div>
		                    <?php if(in_array($calendar->getTimeByLine($line), $att['unavailable_times'])): ?>
								<div style="margin-left: <?php echo ($attCol + 1)*200; ?>px; top: <?php echo $line*100; ?>px;" class="att-unavailable-highlight"></div>
							<?php endif; ?>
						<?php endfor;
					}
				}
			} ?>
			<?php if($borders):
				for($index = 0; $index < $borders+1; $index++): ?>
					<div class="day-event-panel-border" style="margin-left: <?php echo 200*$index; ?>px;"></div>
				<?php endfor;
			endif;
            $counter = 0;
			foreach($by_hour as $bsEvent): ?>
                <div class="day-event day-highlight day-event-main-block dh-<?php echo $bsEvent->displayClass; echo ($counter != $bsEvent->id && !str_contains($bsEvent->displayClass, 'break-down')) ? ' day-event--bdtop': ' ';?>" style="margin-left: <?php echo ($bsEvent->left + 1) * 200; ?>px; top: <?php echo $bsEvent->top * 100; ?>px; height: <?php echo $bsEvent->lines*100; ?>px !important;" data-toggle="tooltip" data-option="day" data-event-id="<?php echo $bsEvent->id; ?>" data-tooltip-id="<?php echo $bsEvent->id; ?>" data-html="true" title="<div class='sln-mouth-tooltip'><?php echo $bsEvent->main ? $bsEvent->title : ''; ?><div style='display: grid; margin-top:0.5rem; margin-left:0.7rem;'>
					<span class='sln-value-tooltip' style='text-align: left;'>
						<div class='head-info-tooltip'><?php echo $bsEvent->amount; ?></div>
						<div class='title-info-tooltip'><?php esc_html_e('Total amount', 'salon-booking-system'); ?></div>
					</span>
					<span class='sln-value-tooltip' id='discount-tooltip'  style='text-align: left; margin-top:0.5rem; <?php echo $isPro ? '' : 'display: none'; ?>'>
						<div class='head-info-tooltip'><?php echo $isPro ? $bsEvent->discount : '- -'; ?></div>
						<div class='title-info-tooltip'><?php esc_html_e('Discount', 'salon-booking-system'); ?></div>
					</span>
					<span class='sln-value-tooltip' id='deposit-tooltip' style='text-align: left; margin-top:0.5rem; <?php echo $isPro ? '' : 'display: none'; ?>'>
						<div class='head-info-tooltip'><?php echo $isPro ? $bsEvent->deposit : '- -'; ?></div>
						<div class='title-info-tooltip'><?php esc_html_e('Deposit', 'salon-booking-system'); ?></div>
					</span>
					<span class='sln-value-tooltip' id='due-tooltip' style='text-align: left; margin-top:0.5rem; <?php echo $isPro ? '' : 'dosplay: none;'; ?>'>
						<div class='head-info-tooltip'><?php echo $isPro ? $bsEvent->due : '- -'; ?></div>
						<div class='title-info-tooltip'><?php esc_html_e('Due', 'salon-booking-system'); ?></div>
					</span>
				</div>
				<div class='icons-container' style='margin-top:0.5rem' data-event-id='<?php echo $bsEvent->id; ?>' data-event-class='<?php echo $bsEvent->displayClass; ?>'>
					<div>
						<a style='text-decoration: none;' class='sln-pen-icon-tooltip' href='' data-bookingid='<?php echo $bsEvent->id; ?>'></a>
					</div>
					<div data-dup-icon='<?php echo $isPro ? 'true': 'false';?>' >
						<a class='sln-dup-icon-tooltip' data-bookingid='<?php echo $bsEvent->id; ?>'></a>
					</div>
					<div>
						<a target='_blanck' class='sln-user-icon-tooltip <?php echo $plugin->getSettings()->get('enabled_force_guest_checkout') ? 'disabled' : '' ?>' href='admin.php?page=salon-customers&id=<?php echo $bsEvent->customerId; ?>'></a>
					</div>
					<div class='sln-trash-icon-tooltip'></div>
				</div>
				<div class='sln-confirm-delete-tooltip'>
					<div style='display:block;'>
						<div><strong><?php esc_html_e('Are you sure?', 'salon-booking-system'); ?></strong></div>
						<div style='display:inline-block;'>
							<a class='sln-dtn-danger-tooltip' href='<?php echo get_delete_post_link($bsEvent->id); ?>'>
								<?php esc_html_e('Yes, delete', 'salon-booking-system'); ?>
							</a>
							<a class='sln-dtn-close-tooltip' aria-label='Close'>
								<span aria-hidden='true'>&times;</span>
							</a>
						</div>
					</div>
				</div>
				</div>">
                    <?php $counter = $bsEvent->id; ?>
					<span data-event-id="<?php echo $bsEvent->id; ?>" data-event-class="<?php echo $bsEvent->displayClass; ?>" class="event-item day-event-item" style="display: <?php echo $bsEvent->display_state;?>;">
						<span class="day-event-item__customer hide"><?php echo $bsEvent->customer; ?></span>
						<span class="day-event-item__from-time hide"><?php esc_html_e('from', 'salon-booking-system'); ?> <?php echo $bsEvent->from; ?></span>
						<span class="day-event-item__to-time hide"><?php esc_html_e('to', 'salon-booking-system'); ?> <?php echo $bsEvent->to; ?></span>
						<span class="day-event-item__calendar-day <?php echo $isPro ? '' : 'sln-free-version'; ?>" data-card-id="<?php echo $bsEvent->id; ?>"><?php echo $bsEvent->calendar_day;
							if($bsEvent->main): ?>
								<div class="more_details">
									<ul>
										<li class="booking-total-amount">
											<?php esc_html_e('Total amount', 'salon-booking-system'); ?>
											<span class="amount_value"><?php echo $isPro ? $bsEvent->amount : '- -'; ?></span>
										</li>
										<li class="booking_discount_amount">
											<?php esc_html_e('Discount', 'salon-booking-system'); ?>
											<span class='amount_value'><?php echo $isPro ? $bsEvent->discount : '- -'; ?></span>
										</li>
										<li class="booking_deposit_amount">
											<?php esc_html_e('Deposit', 'salon-booking-system'); ?>
											<span class="amount_value"><?php echo $isPro ? $bsEvent->deposit : '- -'; ?></span>
										</li>
										<li class="booking_due_amount">
											<?php esc_html_e('Due', 'salon-booking-system'); ?>
											<span class="amount_value"><?php echo $isPro ? $bsEvent->due : '- -'; ?></span>
										</li>
										<?php if(!$isPro): ?>
											<a href="https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=free%20version&utm_medium=booking%20details&utm_campaign=go_pro"
											class="booking_tool_item_promolink"
											target="_blank"
											><?php esc_html_e('unlock this feature for', 'salon-booking-system'); ?>
											<strong><79 € / <?php esc_html_e('year', 'salon-booking-system');?></strong></a>
										<?php endif; ?>
									</ul>
									<div class="booking_tools">
										<div class="booking_tools_item">
											<a href="#"><i class="sln-btn--icon sln-icon--pen"></i></a>
										</div>
										<div class="booking_tools_item <?php echo $isPro ? '' : 'disabled'; ?>">
											<a href="#"><i class="sln-btn--icon sln-icon-trash" style="--font-weight: 800;"></i></a>
											<?php if(!$isPro): ?>
												<a href="https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=free%20version&utm_medium=booking%20details&utm_campaign=go_pro"
												class="booking_tool_item_promolink"
												target="_blank"
												><?php esc_html_e('unlock this feature for', 'salon-booking-system'); ?>
												<strong><79 € / <?php esc_html_e('year', 'salon-booking-system');?></strong></a>
											<?php endif; ?>
										</div>
										<div class="booking_tools_item <?php echo $isPro ? '' : 'disabled'; ?>">
											<a class="dusplicate_link" href="<?php echo $isPro && !empty($bsEvent->duplicate_url) ? $bsEvent->duplicate_url : '#'; ?>"><i class="sln-btn--icon sln-icon--copy" style="--font-weight: 800;"></i></a>
											<?php if(!$isPro): ?>
												<a href="https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=free%20version&utm_medium=booking%20details&utm_campaign=go_pro"
												class="booking_tool_item_promolink"
												target="_blank"
												><?php esc_html_e('unlock this feature for', 'salon-booking-system'); ?>
												<strong><79 € / <?php esc_html_e('year', 'salon-booking-system');?></strong></a>
											<?php endif; ?>
										</div>
										<div class="booking_tools_item <?php echo $isPro ? '' : 'disabled'; ?>">
											<a href="#"><i class="sln-btn--icon sln-icon--user-check"></i></a>
											<?php if(!$isPro): ?>
												<a href="https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=free%20version&utm_medium=booking%20details&utm_campaign=go_pro"
												class="booking_tool_item_promolink"
												target="_blank"
												><?php esc_html_e('unlock this feature for', 'salon-booking-system'); ?>
												<strong><79 € / <?php esc_html_e('year', 'salon-booking-system');?></strong></a>
											<?php endif; ?>
										</div>
										<?php if(!$isPro): ?>
											<a href="https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=free%20version&utm_medium=booking%20details_mobile_device&utm_campaign=go_pro" class="more_details_promolink" target="_blank"
											><span><?php esc_html_e('unlock this feature', 'salon-booking-system'); ?> </span>
											<strong><?php esc_html_e('buy pro', 'salon-booking-system');?></strong></a>
										<?php endif; ?>
									</div>
									<div role="alert">
										<strong><?php esc_html_e('Are you sure?', 'salon-booking-system'); ?></strong>
										<a class="btn btn-danger btn-ok" href="<?php echo get_delete_post_link($bsEvent->id); ?>"><?php esc_html_e('Yes, delete.', 'salon-booking-system'); ?></a>
										<button type="button" class="close" aria-label="Close"><span aria-hidden="true">&times;</span></button>
									</div>
								</div>
							<?php endif; ?>
						</span>
					</span>
					<div class="events-list--title">
						<div class="sln-event-popup" data-event-id="<?php echo $bsEvent->id; ?>"><?php echo $bsEvent->tooltipTitle; ?></div>
					</div>
				</div>
			<?php endforeach; ?>

			<div id="cal-day-panel-hour">
				<?php for($line = 0; $line < $lines; $line++): ?>
					<div class="row-fluid cal-day-hour-part <?php echo ($calendar->hasHolidaysByLine($line) || $calendar->hasHolidaysDaylyByLine($line)) && !$calendar->getAttendantMode() ? 'blocked' : '' ?>" <?php echo $calendar->hasHolidaysByLine($line) ? 'title="'.__('Holiday rule', 'salon-booking-system').'"':'' ?>>
						<div class="span1 col-xs-1"><b><?php echo $calendar->getTimeByLine($line); ?></b></div>
						<div class="span1 col-xs-3 cal-day-hour-part-first-column"></div>
						<div class="span10 col-xs-8"></div>
						<button type="button" class="sln-btn sln-btn--cal-day-select sln-btn--icon sln-icon--checkmark"><?php esc_html_e('SELECT', 'salon-booking-system'); ?></button>
						<div class="cal-day-hour-part__rowactions">
							<span class="cal-day-click-tip"><?php esc_html_e('Click on the "ending time" row', 'salon-booking-system')?></span>
							<button
								type="button"
								class="sln-btn sln-btn--cal-day sln-btn--cal-day--add sln-btn--icon sln-icon--plus"
								data-action="add-event-by-date"
								data-event-date="<?php echo $start->format('Y-m-d'); ?>"
								data-event-time="<?php echo $calendar->getTimeByLine($line); ?>"
							></button>
							<button
								type="button"
								class="block_date sln-btn sln-btn--cal-day sln-btn--cal-day--lock sln-btn--icon sln-icon--lock"
							></button>
						</div>
					</div>
				<?php endfor; ?>
			</div>
		</div>
	</div>
</div>
