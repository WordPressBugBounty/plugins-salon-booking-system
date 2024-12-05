<?php
/**
 * @var $plugin SLN_Plugin
 * @var $helper SLN_Admin_Settings
 */
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>
<div id="sln-booking_resources" class="sln-box sln-box--main sln-box--haspanel">
    <h2 class="sln-box-title sln-box__paneltitle"><?php esc_html_e('Resources', 'salon-booking-system');?></h2>
    <div class="collapse sln-box__panelcollapse">
        <div class="row">
            <div class="col-xs-4">
                <div class="sln-checkbox <?php echo !defined("SLN_VERSION_PAY") ? 'sln-resources-disabled' : '' ?>">
                    <span class="sln-booking-pro-feature-tooltip">
                        <a href="https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=default_status&utm_medium=free-edition-back-end&utm_campaign=unlock_feature&utm_id=GOPRO" target="_blank">
                            <?php echo esc_html__('Switch to PRO to unlock this feature', 'salon-booking-system') ?>
                        </a>
                    </span>
                    <div class="sln-resources--checkbox">
                        <?php $helper->row_input_checkbox('enable_resources', __('Enable “Resources based reservations”', 'salon-booking-system'));?>
                        <div class="sln-box-maininfo">
                            <p class="sln-box-info">
                               <?php esc_html_e('Enable this option to active the resources.', 'salon-booking-system')?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>