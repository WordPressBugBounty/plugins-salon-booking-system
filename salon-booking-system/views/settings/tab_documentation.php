<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.UnsafePrintingFunction
$enum = new SLN_Enum_ShortcodeStyle();
$curr = $this->settings->getStyleShortcode();
$colors = $this->settings->get('style_colors') ? $this->settings->get('style_colors') : array();
include $this->plugin->getViewFile('admin/utilities/settings_inpage_navbar');
sum(
	// link anchor, link text
);
?>
<div class="sln-box sln-box--pb sln-box--main">
    <div class="row">
        <div class="col-xs-12">
            <h2 class="sln-box-title">
                <?php esc_html_e('Here some useful resouces to understand how this plugin works:', 'salon-booking-system');?>
            </h2>
        </div>
        <div class="col-xs-12 col-sm-4  sln-box__megabtn_wrapper">
            <a href="https://salonbookingsystem.helpscoutdocs.com/" class="sln-btn sln-btn--borderonly sln-btn--mega sln-btn--icon sln-icon--docs" target="blank"><?php esc_html_e('Documentation', 'salon-booking-system')?></a>
        </div>
        <div class="col-xs-12 col-sm-4  sln-box__megabtn_wrapper">
            <a href="http://salonbookingsystem.com/category/video-tutorials/" class="sln-btn sln-btn--borderonly sln-btn--mega sln-btn--icon sln-icon--play" target="blank"><?php esc_html_e('Video tutorials', 'salon-booking-system')?></a>
        </div>
        <div class="col-xs-12 col-sm-4  sln-box__megabtn_wrapper">
            <a href="https://salonbookingsystem.helpscoutdocs.com/article/94-how-to-debug-issues" class="sln-btn sln-btn--borderonly sln-btn--mega sln-btn--icon sln-icon--lightbulb" target="blank"><?php esc_html_e('Tips and suggestions', 'salon-booking-system')?></a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12 col-md-4">
        <div class="sln-box sln-box--pb sln-box--main">
            <h3 class="sln-box-title--sec ">
                <?php esc_html_e('Are you experiencing an issue with our plugin? Follow this guide first:', 'salon-booking-system');?>
            </h3>
            <a href="https://www.salonbookingsystem.com/how-to-debug-salon-booking-plugin/" class="sln-btn sln-btn--borderonly sln-btn--mega sln-btn--icon sln-icon--medkit" target="blank">
                    <?php esc_html_e('Troubleshoot', 'salon-booking-system')?>
                </a>
        </div>
    </div>
    <div class="col-xs-12 col-md-8">
        <div class="sln-box sln-box--pb sln-box--main">
            <h3 class="sln-box-title--sec"><?php esc_html_e('If you need more assistance o report a bug follow these instructions:', 'salon-booking-system');?></h3>
            <div class="row">
                <div class="col-xs-12 col-md-6">
                    <h3 class="sln-box-title--ter"><?php _e('<strong>PRO</strong> users please send and email to', 'salon-booking-system');?> <a href="mailto:support@salonbookingsystem.com">support@salonbookingsystem.com</a></h3>
                </div>
                <div class="col-xs-12 col-md-6">
                    <h3 class="sln-box-title--ter"><?php _e('<strong>FREE</strong> users please post the problem on <a href="https://wordpress.org/support/plugin/salon-booking-system" target="blank">worpdress.org</a> official forum. ', 'salon-booking-system');?></h3>
                </div>
            </div>


        </div>
    </div>
    <div class="col-xs-12 col-md-4">
        <div class="sln-box sln-box--pb sln-box--main">
            <h3 class="sln-box-title--sec">
                <strong><?php esc_html_e('Rate us!', 'salon-booking-system');?></strong><br />
                <?php esc_html_e('Are you satisfied with Salon Booking System? Consider to leave a 5 stars rating on wordpress.org', 'salon-booking-system');?></h3>
            <a href="https://wordpress.org/support/view/plugin-reviews/salon-booking-system?filter=5" class="sln-btn sln-btn--borderonly sln-btn--mega sln-btn--icon  sln-icon--star" target="blank"><?php esc_html_e('Let\'s rate', 'salon-booking-system')?></a>
        </div>
    </div>
    <?php if( current_user_can( 'administrator') ): ?>
        <div class="col-xs-12 col-md-4">
            <div class="sln-box sln-box--main">
                <div class="sln-checkbox">
                <?php $this->row_input_checkbox('debug', __('Show detailed information during checkout', 'salon-booking-system'));?>
                <p><?php esc_html_e( 'Only admin will see it.', 'salon-booking-system') ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

 <?php
      if (defined("SLN_VERSION_PAY")) { ?>

    <div class="col-xs-12 col-md-4">
        <div class="sln-box sln-box--main">
            <div class="sln-checkbox">
            <?php $this->row_input_checkbox('enable_sln_worker_role', __('Enable SLN Worker role', 'salon-booking-system'));?>
            <p></p>
            </div>
        </div>
    </div>

<?php } ?>

</div>
