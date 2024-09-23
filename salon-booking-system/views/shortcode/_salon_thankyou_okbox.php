<?php if ($pendingPayment): ?>
<div class="sln-thankyou--okbox">
	<h2 class="sln-thankyou__status sln-thankyou__status--pending">
		<img class="sln-thankyou__img" src="<?php echo SLN_PLUGIN_URL.'/img/pay_icon.png' ?>" alt="">
		<span class="sln-thankyou__status__message"><?php echo esc_html__('Choose a payment option', 'salon-booking-system') ?></span>
	</h2>
	<div class="sln-thankyou__status__row">
		<?php esc_html_e('Pending booking number', 'salon-booking-system') ?>:
		<strong><?php echo $booking->getId() ?></strong>
	</div>
	<?php if($booking->getDeposit() > 0){ ?>
		<div class="sln-thankyou__status__row">
		    <?php esc_html_e('Total amount of the reservation', 'salon-booking-system') ?>:
		    <strong>
			<?php echo $plugin->format()->moneyFormatted($booking->getAmount()) ?>
		    </strong>
		</div>
		<div class="sln-thankyou__status__row">
		    <?php esc_html_e('Amount to be paid in advance', 'salon-booking-system') ?>:
		    <strong>
			<?php echo $plugin->format()->moneyFormatted($booking->getDeposit()) ?>
		    </strong>
		</div>
	<?php }else{ ?>
		<div class="sln-thankyou__status__row">
		    <?php esc_html_e('Amount to be paid', 'salon-booking-system') ?>:
		    <strong>
			<?php echo $plugin->format()->moneyFormatted($booking->getAmount()) ?>
		    </strong>
		</div>
	<?php } ?>
</div>
<?php else: ?>
<div class="sln-thankyou--okbox">
		    <h2 class="sln-thankyou__status <?php if($confirmation): ?> sln-thankyou__status--attention<?php else : ?> sln-thankyou__status--ok<?php endif ?>">
			<?php if($confirmation): ?>
			    <span class="sln-thankyou__icon sln-thankyou__icon--time"></span>
			<?php else : ?>
			    <span class="sln-thankyou__icon sln-thankyou__icon--checked"></span>
			<?php endif ?>
			<span class="sln-thankyou__status__message"><?php echo $confirmation ? esc_html__('Your booking is pending', 'salon-booking-system') : esc_html__('Your booking is completed', 'salon-booking-system') ?></span>
		    </h2>
		    <p class="sln-thankyou__label"><?php esc_html_e('Booking number', 'salon-booking-system') ?></p>
		    <h3 class="sln-thankyou__id"><?php echo $booking->getId() ?></h3>
</div>
<?php endif; ?>
