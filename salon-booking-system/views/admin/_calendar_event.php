<span class="name"><?php echo esc_html($booking->getDisplayName())?></span>|
<span class="date"><?php echo esc_html($booking->getStartsAt()->format('d/m/Y')) ?></span>|
<span class="time"><?php echo esc_html($booking->getStartsAt()->format('H:i')) ?></span>
