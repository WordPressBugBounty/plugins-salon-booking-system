<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin $plugin
 * @var int $size
 * @var float $tipsValue
 */
?>
<div class="sln-summary-row sln-summary-row--tips sln-list__item sln-list__item--db <?php echo !$tipsValue ? 'hide' : '' ?>">
	<div class="sln-data-val">
		<span id="sln_tips_value"><?php echo $plugin->format()->money($tipsValue, false, false, true); ?></span>
	</div>
	<div class="sln-data-desc">
		<?php
		$args = array(
			'key'          => 'Tips',
			'label'        => __('Tips', 'salon-booking-system'),
			'tag'          => 'span',
			'textClasses'  => 'text-min label',
			'inputClasses' => 'input-min',
			'tagClasses'   => 'label',
		);
		echo $plugin->loadView('shortcode/_editable_snippet', $args);
		?>
	</div>
</div>