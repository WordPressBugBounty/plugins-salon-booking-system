<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
$args = array(
    'key'          => '',
    'label'        => '',
    'tag'          => 'h2',
    'textClasses'  => 'salon-step-title',
    'inputClasses' => '',
    'tagClasses'   => 'salon-step-title',
);

foreach($args as $k => $v) {
	if (!isset($$k)) {
		$$k = $v;
	}
}
$value = SLN_Plugin::getInstance()->getSettings()->getCustomText($key, $label);

if(current_user_can('manage_options')) {
	?>
	<div class="editable">
        <<?php echo tag_escape($tag); ?> class="text <?php echo esc_attr($textClasses) ?>">
            <?php echo wp_kses_post($value); ?>
        </<?php echo tag_escape($tag); ?>>
		<div class="input <?php echo esc_attr($inputClasses) ?>">
			<input class="sln-edit-text" id="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" />
		</div>
		<i class="fa fa-cog fa-fw"></i>
	</div>
	<?php
} else {
	?>
	<<?php echo tag_escape($tag); ?> class="<?php echo esc_attr($tagClasses) ?>"><?php echo wp_kses_post($value); ?></<?php echo tag_escape($tag); ?>>
	<?php
}