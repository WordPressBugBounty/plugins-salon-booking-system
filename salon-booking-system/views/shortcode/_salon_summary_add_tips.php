<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin $plugin
 * @var int $size
 */
?>

<div class="sln-summary__tips">
	<div class="sln-input sln-input--simple sln-input--lon sr-only">
	    <?php
	    $args = array(
		    'key'          => 'Leave a tip',
		    'label'        => __('Leave a tip', 'salon-booking-system'),
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
			    'sln[tips]',
			    '',
			    array('attrs' => array('placeholder' => __('key in the desired amount', 'salon-booking-system')))
		    ); ?>
		</div>
		<div class="sln-btn sln-btn--emphasis sln-btn--borderonly sln-btn--medium">
			<button data-salon-toggle="tips" id="sln_tips_btn" type="button" onclick="sln_applyTipsAmount();">
			    <?php esc_html_e('Apply', 'salon-booking-system'); ?>
			</button>
		</div>
	</div>
	<div id="sln_tips_status"></div>
</div>