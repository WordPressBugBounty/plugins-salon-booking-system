<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin $plugin
 * @var SLN_Settings $settings
 * @var int $size
 * @var float $tipsValue
 */

$taxValue = $bb->getTaxFromTotal();
if('exclusive' == $settings->get('enter_tax_price')):
?>
<div class="sln-summary-row sln-summary-row--tax sln-list__item sln-list__item--db">
	<div class="sln-data-val">
		<span id="sln_amount_exclude_tax_value"><?php echo $plugin->format()->money($bb->getAmount() - $taxValue, false, false, true); ?></span>
	</div>
	<div class="sln-data-desc">
		<?php
		$args = array(
			'key'          => 'Total amount tax excluded ',
			'label'        => __('Total amount tax excluded', 'salon-booking-system'),
			'tag'          => 'span',
			'textClasses'  => 'text-min label',
			'inputClasses' => 'input-min',
			'tagClasses'   => 'label',
		);
		echo $plugin->loadView('shortcode/_editable_snippet', $args);
		?>
	</div>
</div>
<?php endif; ?>
<div class="sln-summary-row sln-summary-row--tax sln-list__item sln-list__item--db">
	<div class="sln-data-val">
		<span id="sln_tax_value"><?php echo $plugin->format()->money($taxValue, false, false, true); ?></span>
	</div>
	<div class="sln-data-desc">
		<?php
		$args = array(
			'key'          => 'TAX' . '(' . $settings->get('tax_value') . '%)',
			'label'        => __('TAX', 'salon-booking-system') . ', ' . $settings->get('tax_value') . '%',
			'tag'          => 'span',
			'textClasses'  => 'text-min label',
			'inputClasses' => 'input-min',
			'tagClasses'   => 'label',
		);
		echo $plugin->loadView('shortcode/_editable_snippet', $args);
		?>
	</div>
</div>