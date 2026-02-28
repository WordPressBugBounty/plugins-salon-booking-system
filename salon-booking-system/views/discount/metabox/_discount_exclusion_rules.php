<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Plugin $plugin
 * @var SLB_Discount_Wrapper_Discount $discount
 * @var string $postType
 * @var SLN_Metabox_Helper $helper
 */

$exclusionRules = $discount->getExclusionRulesRaw();
$base           = $helper->getFieldName($postType, 'exclusion_rules');
$isPro          = defined('SLN_VERSION_PAY');
?>
<div class="sln-profeature <?php echo ! $isPro ? 'sln-profeature--disabled sln-profeature__tooltip-wrapper' : ''; ?>">
	<?php echo $plugin->loadView(
		'metabox/_pro_feature_tooltip',
		array(
			'additional_classes' => 'sln-profeature--section',
		)
	); ?>
	<div class="sln-profeature__input">
		<div id="sln-discount-exclusion-rules" class="sln-box sln-box--main sln-booking-rules sln-box--haspanel">
			<h2 class="sln-box-title sln-box__paneltitle">
				<?php esc_html_e( 'Exclusion rules', 'salon-booking-system' ); ?>
				<span class="block"><?php esc_html_e( 'Create one or more rules to define when this discount cannot be applied.', 'salon-booking-system' ); ?></span>
			</h2>
			<div class="collapse sln-box__panelcollapse">
				<div class="row">
					<div class="sln-booking-rules-wrapper">
						<?php
						$n = 0;
						foreach ( $exclusionRules as $row ) :
							$n ++;
							echo $plugin->loadView(
								'metabox/_discount_exclusion_rule_row',
								array(
									'prefix'     => $base . "[$n]",
									'row'        => $row,
									'rulenumber' => $n,
								)
							);
						endforeach;
						?>
					</div>
					<div class="col-xs-12 sln-box__actions">
						<button data-collection="addnew" type="button"
						        class="sln-btn sln-btn--main--tonal sln-btn--big sln-btn--icon sln-icon--file">
							<?php esc_html_e( 'Add exclusion rule', 'salon-booking-system' ); ?>
						</button>
					</div>
					<div data-collection="prototype" data-count="<?php echo count( $exclusionRules ); ?>">
						<?php echo $plugin->loadView(
							'metabox/_discount_exclusion_rule_row',
							array(
								'row'        => array(),
								'rulenumber' => '__new__',
								'prefix'     => $base . '[__new__]',
							)
						); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
