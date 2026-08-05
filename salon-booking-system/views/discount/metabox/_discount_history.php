<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * @var SLN_Plugin $plugin
 * @var SLN_Settings $settings
 * @var SLN_Metabox_Helper $helper
 * @var SLB_Discount_Wrapper_Discount $discount
 * @var string $postType
 *
 */
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>

<div class="sln-box--sub row">
    <div class="col-xs-12"><h2 class="sln-box-title"><?php echo sprintf(
            // translators: %d: name of the total usages number
			esc_html__('Total usage ( %d )', 'salon-booking-system'), esc_attr($discount->getTotalUsagesNumber())) ?></h2></div>
        <div class="col-xs-12 sln-table">
        <?php
        global $post;
        $_post = $post;

        $wp_list_table = new SLB_Discount_Admin_DiscountsHistoryList();
        $wp_list_table->prepare_items();
        $wp_list_table->display();

        $post = $_post;
        ?>
    </div>
</div>

<div class="sln-clear"></div>
<?php do_action('sln.template.discount_history.metabox', $discount); ?>