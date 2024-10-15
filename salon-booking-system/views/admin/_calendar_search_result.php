<?php if(is_array($bookings) && count($bookings)): ?>
    <ul class="unstyled list-unstyled event-list">
		<?php foreach($bookings as $booking): ?>
            <li class="search-result">
            <li class="search-result" style="margin-bottom:0px">
                <div class="search-result-content" id="card_prenotazione">
                    <div class="prima riga">
                        <div class="blocco">
                            <p class="card_value" style="font-weight: 650"><?php echo esc_html($booking['id']); ?></p>
                        </div>
                        <div class="blocco">
                            <p class="card_value card_customer_name"><?php echo esc_html($booking['customer']); ?></p>
                        </div>
                        <div class="blocco card_data">
                            <p class="card_value" style="color: #0e0e0e; font-size: 15px; font-weight: 520"><?php echo esc_html($booking['time']); ?></p>
                        </div>
                        <div class="blocco" style="margin-left: auto; margin-right: 1rem">
                            <p class="bottone main"><a href="#" data-bookingid='<?php echo esc_html($booking['id']); ?>' class="event sln-details-search"><?php esc_html_e('Details', 'salon-booking-system'); ?></a></p>
                        </div>
                    </div>
                </div>
            </li>
		<?php endforeach; ?>
    </ul>
<?php else:?>
    <p style="text-align:center; font-size:1rem;"><?php esc_html_e('No results', 'salon-booking-system'); ?></p>
<?php endif; ?>