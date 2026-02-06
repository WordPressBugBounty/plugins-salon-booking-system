<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>
<?php if (is_array($bookings) && count($bookings)): ?>
    <ul class="unstyled list-unstyled event-list">
        <?php foreach ($bookings as $booking): ?>
            <li class="search-result">
                <div class="search-result__block search-result__id">
                    <?php echo esc_html($booking['id']); ?>
                </div>
        <div class="search-result__block search-result__customer customer_name">
            <div class="search-result__customer-info">
                <span class="search-result__customer-name"><?php echo esc_html($booking['customer']); ?></span>
                <?php if (!empty($booking['shop_name'])): ?>
                    <span class="search-result__shop-name"><?php echo esc_html($booking['shop_name']); ?></span>
                <?php endif; ?>
            </div>
        </div>
                <div class="search-result__block search-result__time">
                    <?php
                    $dateTime = $booking['calendar_time'];
                    $parts = explode(' ', $dateTime);
                    $day = $parts[0];
                    $date = $parts[1] . ' ' . $parts[2] . ' ' . $parts[3];
                    $time = $parts[4];
                    ?>
                    <span class="search-result__time_day"><?php echo esc_html($day); ?></span>
                    <span class="search-result__time_date"><?php echo esc_html($date); ?></span>
                    <span class="search-result__time_time"><?php echo esc_html($time); ?></span>
                </div>
                <div class="search-result__block search-result__amount_and_status">
                    <div class="search-result__amount">
                        <?php echo $booking['amount']; ?>
                    </div>
                    <div class="search-result__status" style="color: <?php echo esc_attr($booking['status_color']); ?>;">
                        <span class="search-result__status-indicator" style="background-color: <?php echo esc_attr($booking['status_color']); ?>"></span>
                        <span class="search-result__status-label"><?php echo esc_html($booking['status']); ?></span>
                    </div>
                </div>
                <div class="search-result__block search-result__details">
                    <a href="#" data-bookingid='<?php echo esc_html($booking['id']); ?>' class="sln-btn sln-btn--calendar-view--pill sln-details-search"><?php esc_html_e('Details', 'salon-booking-system'); ?></a>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p style="text-align:center; font-size:1rem;"><?php esc_html_e('No results', 'salon-booking-system'); ?></p>
<?php endif; ?>