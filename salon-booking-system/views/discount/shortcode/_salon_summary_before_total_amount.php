<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin $plugin
 * @var int $size
 * @var float $discountValue
 */
?>
<div class="sln-summary-row sln-summary-row--discount sln-list__item sln-list__item--db <?php echo !$discountValue ? 'hide' : '' ?> ">
	<div class="sln-data-val">
		<span id="sln_discount_value"><?php echo $plugin->format()->money($discountValue, false, false, true); ?></span>
	</div>
	<div class="sln-data-desc">
		<?php
		$args = array(
			'label'        => __('Discount', 'salon-booking-system'),
			'tag'          => 'span',
			'textClasses'  => 'text-min label',
			'inputClasses' => 'input-min',
			'tagClasses'   => 'label',
		);
		echo $plugin->loadView('shortcode/_editable_snippet', $args);
		?>
	</div>
</div>