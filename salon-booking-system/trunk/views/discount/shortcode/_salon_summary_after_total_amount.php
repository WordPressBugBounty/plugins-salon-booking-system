<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin $plugin
 * @var int $size
 */
?>
<div class="sln-summary__discount">
	<div class="sln-input sln-input--simple sln-input--lon sr-only">
		<?php
		$args = array(
			'label'        => __('Enter discount code', 'salon-booking-system'),
			'tag'          => 'label',
			'textClasses'  => '',
			'inputClasses' => '',
			'tagClasses'   => '',
		);
		echo $plugin->loadView('shortcode/_editable_snippet', $args);
		?>
	</div>
	<div class="sln-summary__tabs__pane__content">
		<div class="sln-input sln-input--simple sln-input--lon">
			<?php SLN_Form::fieldText(
				'sln[discount]',
				'',
				array('attrs' => array('placeholder' => __('key in your coupon code', 'salon-booking-system')))
			); ?>
		</div>
		<div class="sln-btn sln-btn--emphasis sln-btn--borderonly sln-btn--medium">
			<button data-salon-toggle="discount" id="sln_discount_btn" type="button" onclick="sln_applyDiscountCode();">
				<?php esc_html_e('Apply', 'salon-booking-system'); ?>
			</button>
		</div>
	</div>
	<div id="sln_discount_status"></div>
</div>