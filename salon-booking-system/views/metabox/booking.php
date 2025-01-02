<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Metabox_Helper $helper
 * @var SLN_Plugin $plugin
 * @var SLN_Settings $settings
 * @var SLN_Wrapper_Booking $booking
 * @var string $mode
 * @var SLN_DateTime|null $date
 * @var SLN_DateTime|null $time
 */
$helper->showNonce($postType);
SLN_Action_InitScripts::enqueueCustomBookingUser();
$additional_fields = SLN_Enum_CheckoutFields::forBooking();
$checkoutFields = $additional_fields->selfClone()->required()->keys();
$customer_fields = SLN_Enum_CheckoutFields::forBookingAndCustomer()->filter('additional', true, false)->keys();

$isAttendants = $plugin->getSettings()->isAttendantsEnabled();
$isMultipleAttendants = $plugin->getSettings()->isMultipleAttendantsEnabled();
$isAttendants = $isAttendants || $booking->getAttendant();
$isMultipleAttendants = $isAttendants && ($isMultipleAttendants || (count($booking->getAttendants(true)) > 1));

?>
<?php if (isset($_SESSION['_sln_booking_user_errors'])): ?>
    <div class="error">
        <?php foreach ($_SESSION['_sln_booking_user_errors'] as $error): ?>
            <p><?php echo esc_attr($error) ?></p>
        <?php endforeach ?>
    </div>
    <?php unset($_SESSION['_sln_booking_user_errors']); ?>
<?php endif ?>

<?php
$additional_classes = array();
$header_classes = array();
if (class_exists('SalonMultishop\Wrapper\ShopService')) {
    array_push($additional_classes, "sln-booking__detail--multishop");
    array_push($header_classes, "sln-booking__header--multishop");
}
if ($plugin->getSettings()->get('confirmation') && $booking->getStatus() == SLN_Enum_BookingStatus::PENDING) {
    array_push($additional_classes, "sln-booking__detail--confirmation");
    array_push($header_classes, "sln-booking__header--confirmation");
}
?>
<div class="sln-bootstrap  <?php echo implode(' ', $additional_classes); ?>" id="detailsWrapper" style="opacity: 0;">
    <div class="sln-booking__header  <?php echo implode(' ', $header_classes); ?>">
        <div class="sln-booking__header__title">
            <h1>#<?php echo $booking->getId(); ?></h1>
        </div>
        <?php if ($plugin->getSettings()->get('confirmation') && $booking->getStatus() == SLN_Enum_BookingStatus::PENDING): ?>
            <div class="sln-booking__header__confirmation">
                <h2><?php esc_html_e('Approve this booking', 'salon-booking-system') ?></h2>
                <button id="booking-accept" class="sln-btn sln-btn--small--round sln-btn--icon sln-icon--approve"
                    data-status="<?php echo SLN_Enum_BookingStatus::CONFIRMED ?>">
                    <span class="sr-only"><?php esc_html_e('Accept', 'salon-booking-system') ?></span>
                </button>
                <button id="booking-refuse" class="sln-btn sln-btn--small--round sln-btn--icon sln-icon--deny"
                    data-status="<?php echo SLN_Enum_BookingStatus::CANCELED ?>">
                    <span class="sr-only"><?php esc_html_e('Refuse', 'salon-booking-system') ?></span>
                </button>
            </div>
        <?php endif; ?>
        <?php do_action('sln.template.booking.metabox', $booking); ?>
        <div class="sln-booking__header__status">
            <div class="form-group sln_meta_field sln-select">
                <label id="sln-booking__status__label" class="sr-only" data-default_status="<?php echo SLN_Plugin::getInstance()->getSettings()->getDefaultBookingStatus(); ?>" data-booking_status="<?php echo $booking->getStatus(); ?>"><?php esc_html_e('Status', 'salon-booking-system'); ?> <?php echo $booking->getStatus(); ?></label>
                <?php
                SLN_Form::fieldSelect(
                    $helper->getFieldName($postType, 'status'),
                    SLN_Enum_BookingStatus::toArray(),
                    empty($_GET['post']) && SLN_Plugin::getInstance()->getSettings()->getDefaultBookingStatus() ?
                        SLN_Plugin::getInstance()->getSettings()->getDefaultBookingStatus() :
                        $booking->getStatus(),
                    array('map' => true)
                );
                ?>
            </div>
            <div class="sln-set-default-booking-status--block-labels <?php echo !defined("SLN_VERSION_PAY")  ? 'sln-set-default-booking-status--block-label-disabled' : '' ?>" data-default-status="<?php echo SLN_Plugin::getInstance()->getSettings()->getDefaultBookingStatus() ?>">
                <span class="sln-booking-pro-feature-tooltip">
                    <a href="https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=default_status&utm_medium=free-edition-back-end&utm_campaign=unlock_feature&utm_id=GOPRO" target="_blank">
                        <?php echo esc_html__('Switch to PRO to unlock this feature', 'salon-booking-system') ?>
                    </a>
                </span>
                <?php if (isset($_GET['action']) && $_GET['action'] == 'duplicate'): ?>
                    <span id="sln-booking-cloned-notice" class="<?php echo in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ? 'sln-disabled' : '' ?>">
                        <?php echo esc_html__('Please set a new date and time', 'salon-booking-system'); ?>
                    </span>
                <?php else: ?>
                    <div class="sln-set-default-booking-status--label-message">
                        <a href="#" class="sln-set-default-booking-status--label-set hide">
                            <?php esc_html_e('Set as default status', 'salon-booking-system') ?>
                        </a>
                        <span class="sln-set-default-booking-status--label-current"><?php esc_html_e('Default status', 'salon-booking-system') ?></span>
                        <span class="sln-set-default-booking-status--label-done hide">
                            <?php esc_html_e('Done !', 'salon-booking-system') ?>
                        </span>
                    </div>
                <?php endif; ?>
                <div class="sln-set-default-booking-status--alert-loading hide"></div>
            </div>
        </div>
    </div><!-- sln-booking__header // END -->
    <!-- <div class="sln-box sln-box--main sln-box--main--transp sln_bookingeditor_view__wrapper">
                <div class="row">
                    <div class="col-xs-12 col-sm-4 col-sm-offset-8-">
                    </div>
                </div>
            </div> -->
    <div class="row sln-box__row--flex sln-box__row--flex--alend">
        <div class="col-sm-10">
            <ul class="sln-admin__tabs__nav" role="tablist">
                <li class="sln-admin__tabs__nav__item active" role="presentation">
                    <a data-target="#sln-booking__customer" aria-controls="sln-booking__customer" role="tab" data-toggle="tab">
                        <span><?php esc_html_e('Client', 'salon-booking-system') ?></span>
                    </a>
                </li>
                <li class="sln-admin__tabs__nav__item" role="presentation">
                    <a data-target="#salon-step-date" aria-controls="salon-step-date" role="tab" data-toggle="tab">
                        <span><?php esc_html_e('Date', 'salon-booking-system') ?></span>
                    </a>
                </li>
                <li class="sln-admin__tabs__nav__item" role="presentation">
                    <a data-target="#sln-booking__services" aria-controls="sln-booking__services" role="tab" data-toggle="tab">
                        <?php if ($isMultipleAttendants || $isAttendants): ?>
                            <span><?php esc_html_e('Service', 'salon-booking-system'); ?></span>
                        <?php else: ?>
                            <span><?php esc_html_e('Service', 'salon-booking-system'); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="sln-admin__tabs__nav__item" role="presentation">
                    <a data-target="#sln-booking__totals" aria-controls="sln-booking__totals" role="tab" data-toggle="tab">
                        <span><?php esc_html_e('Totals', 'salon-booking-system'); ?></span>
                    </a>
                </li>
                <li class="sln-admin__tabs__nav__item" role="presentation">
                    <a data-target="#sln-booking__notes" aria-controls="sln-booking__notes" role="tab" data-toggle="tab">
                        <span><?php esc_html_e('Notes', 'salon-booking-system') ?></span>
                    </a>
                </li>
                <?php if (class_exists('\SalonSOAP\Addon')) { ?>
                    <li class="sln-admin__tabs__nav__item" role="presentation">
                        <a data-target="#sln-booking__soap" aria-controls="sln-booking__soap" role="tab" data-toggle="tab">
                            <span><?php esc_html_e('SOAP Notes', 'salon-booking-system') ?></span>
                        </a>
                    </li>
                <?php } ?>
            </ul>
        </div>
        <div class="col-sm-2">

            <div class="sln-switch sln-switch--segmented sln-switch--viewmode">
                <input type="checkbox" id="_sln_bookingeditor_view" name="_sln_bookingeditor_view" checked />
                <label for="_sln_bookingeditor_view">
                    <span class="sr-only sln-switch--on__text"><?php esc_html_e('List view on', 'salon-booking-system') ?></span>
                    <span class="sr-only sln-switch--off__text"><?php esc_html_e('List view off', 'salon-booking-system') ?></span>
                </label>
                <!-- <input type="hidden" id="_sln_booking_origin_source" name="_sln_booking_origin_source" value="<?php echo SLN_Enum_BookingOrigin::ORIGIN_ADMIN ?>"/> -->
            </div>
        </div>
    </div>

    <?php

    $selectedDate = !empty($date) ? $date : $booking->getDate(SLN_TimeFunc::getWpTimezone());
    $selectedTime = !empty($time) ? $time : $booking->getTime(SLN_TimeFunc::getWpTimezone());

    $intervalDate = clone $selectedDate;
    $intervals = $plugin->getIntervals($intervalDate);

    $edit_last_author = get_userdata(get_post_meta($booking->getId(), '_edit_last', true));
    ?>
    <div id="sln-booking__tabscontent" class="tab-content">
        <div id="sln-booking__customer" role="tabpanel" class="sln-box sln-box--main sln-booking__customer tab-pane sln-admin__tabpanel sln-admin__tabpanel--customer active <?php echo in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ? 'sln-disabled' : '' ?>">
            <div class="sln-booking__customer <?php echo in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ? 'sln-disabled' : '' ?>">
                <h4 class="sln-box-title--nu--sec"><?php esc_html_e('Client', 'salon-booking-system') ?></h4>
                <div class="row sln-box__row--flex--alcenter-">
                    <div class="col-xs-12 col-sm-6">
                        <div class="sln-select">
                            <select id="sln-update-user-field"
                                data-nomatches="<?php esc_html_e('no users found', 'salon-booking-system') ?>"
                                data-placeholder="<?php esc_html_e('Search for a user', 'salon-booking-system') ?>"
                                class="form-control">
                            </select>
                            <p class="help-block"><?php esc_html_e('Just start typing a name, email, or phone number in the search field to quickly find the user you need!', 'salon-booking-system') ?></p>
                        </div>
                    </div>
                    <div class="col-xs-12 col-sm-4">
                        <?php if (preg_match('/post\-new\.php/i', $_SERVER['REQUEST_URI'])): ?>
                            <span><a target="_blank" class="sln-btn sln-icon--customerurl sln-trigger--customerfile hide"><?php esc_html_e('Open customer file', 'salon-booking-system') ?></a></span>
                        <?php endif; ?>
                    </div>
                    <div class="col-xs-12 col-sm-2">
                        <button id="sln-booking__customer__reset" class="sln-btn sln-booking__customer__reset sln-booking--reset hide" data-collection="reset"><?php esc_html_e('Reset', 'salon-booking-system') ?></button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-12 col-sm-4" id="sln-update-user-message"></div>
                </div>
                <div class="row sln-box__row--flex">
                    <?php
                    $customer = $booking->getCustomer();

                    if ($additional_fields) {
                        foreach ($additional_fields as $key => $field) {
                            if ($field['type'] === 'html') {
                                continue;
                            }
                            $is_customer_field = $field->isCustomer();
                            $value = $is_customer_field && $customer && $field->isAdditional() ? $field->getValue($customer->getId())
                                : (
                                    in_array('_sln_booking_' . $key, get_post_custom_keys($booking->getId()) ?? array()) ? $booking->getMeta($key) : (null !== $field['default_value'] ? $field['default_value'] : '')
                                );
                            $method_name = 'field' . ucfirst($field['type']);
                            $width = $field['width'];
                    ?>
                            <div class="col-xs-12 <?php
                                                    if ($width == 12) {
                                                        echo ' col-sm-8 col-xl-6';
                                                    } else if ($width == 6) {
                                                        echo ' col-sm-4 col-xl-3';
                                                    } else {
                                                        echo esc_attr($width);
                                                    }
                                                    ?> sln-input--simple <?php echo 'sln-' . esc_attr($field['type']); ?> sln-booking-user-field">
                                <div class="form-group sln_meta_field">
                                    <?php
                                    if ($field['type'] === 'checkbox') {
                                        echo '<h6 class="sln-gst-label">' . esc_html__(sprintf('%s', $field['label']), 'salon-booking-system') . '</h6>';
                                    } else {
                                        echo '<label for="' . esc_attr($key) . '">' . esc_html__(sprintf('%s', $field['label']), 'salon-booking-system') . '</label>';
                                    }
                                    ?>
                                    <!-- <label for="<?php echo esc_attr($key) ?>"><?php echo esc_html__(sprintf('%s', $field['label']), 'salon-booking-system') ?></label> -->
                                    <?php
                                    $additional_opts = array(
                                        $is_customer_field && $field->isAdditional() ? '_sln_' . $key :
                                            $helper->getFieldName($postType, $key),
                                        $value,
                                        array('required' => $field->isRequired()),
                                    );
                                    if ($key === 'email') {
                                        $additional_opts[2]['type'] = 'email';
                                        $additional_opts[2]['required'] = false;
                                    }
                                    if ($key == 'phone') {
                                        $additional_opts[2]['type'] = 'tel';
                                    }

                                    if ($field['type'] === 'checkbox') {

                                        $additional_opts = array_merge(array_slice($additional_opts, 0, 2), array($field['label']), array_slice($additional_opts, 2));
                                        $method_name = $method_name . 'Button';
                                    }
                                    if ($field['type'] === 'select') {
                                        $additional_opts = array_merge(array_slice($additional_opts, 0, 1), [$field->getSelectOptions()], array_slice($additional_opts, 1), [true]);
                                    }
                                    if ($field['type'] === 'file') {
                                        $files = $booking->getMeta($key);
                                        if (!is_array($files)) {
                                            $files = array($files);
                                        } ?>
                                        <div class="sln_meta_field_file">
                                            <?php foreach ($files as $file): ?>
                                                <?php
                                                if ($file) {
                                                    $file_url = implode('/', array_filter(array(wp_get_upload_dir()['baseurl'], trim($file['subdir'], '/'), $file['file'])));
                                                    $file_name = preg_replace('/^[0-9]+_/i', '', $file['file']);
                                                }
                                                ?>
                                                <a href="<?php echo $file_url ?>" download="<?php echo $file_url ?>"><?php echo esc_attr($file_name) ?></a>
                                            <?php endforeach; ?>
                                        </div><?php
                                            } else {
                                                call_user_func_array(array('SLN_Form', $method_name), $additional_opts);
                                            }
                                                ?>
                                </div>
                            </div>
                    <?php if ($key === 'address') {
                                /*echo '<div class="col-xs-12"><div class="sln-separator"></div></div>';
                        echo '<div class="col-xs-12">
<h5 class="sln-box-title--nu--ter">' . esc_html__('Additional informations', 'salon-booking-system') . '</h5>
</div>';*/
                            }
                        }
                    } ?>
                    <?php SLN_Form::fieldText('_sln_booking_sms_prefix', $booking->getMeta('sms_prefix') ? $booking->getMeta('sms_prefix') : $plugin->getSettings()->get('sms_prefix'), array('type' => 'hidden')); ?>

                    <?php SLN_Form::fieldText('_sln_booking_default_sms_prefix', $plugin->getSettings()->get('sms_prefix'), array('type' => 'hidden')); ?>

                    <?php if ($plugin->getSettings()->get('enable_customer_fidelity_score')): ?>
                        <div class="col-xs-12 col-sm-4 col-xl-3 sln-booking-customer-score">
                            <label class="sln-booking-customer-score--title">
                                <?php esc_html_e('Fidelity score', 'salon-booking-system') ?>
                            </label>
                            <div class="sln-booking-customer-score--value">
                                <?php echo $booking->getCustomer() ? esc_attr($booking->getCustomer()->getFidelityScore()) : 0 ?>
                            </div>
                        </div>
                    <?php endif ?>

                    <!-- THIS IS THE BUNCH OF HTML TO SHOW FOR THE RATING -->
                    <?php if ("rating"): ?>
                        <div class="col-xs-12 col-sm-4 col-xl-3 sln-rating__wrapper">
                            <label class="sln-booking-customer-score--title">
                                <?php esc_html_e('Rating', 'salon-booking-system') ?>
                            </label>
                            <div class="sln-rating">
                                <input class="sln-rating__input sln-rating__input-0" checked type="radio" value="-1" id="skip-rating" name="rating-radio" autocomplete="off" />
                                <label class="sln-rating__label hidden"></label>
                                <input class="sln-rating__input sln-rating__input-1" type="radio" id="rt-1" value="1" name="rating-radio" autocomplete="off" />
                                <label class="sln-rating__label sln-rating__label-1" for="rt-1"></label>
                                <input class="sln-rating__input sln-rating__input-2" type="radio" id="rt-2" value="2" name="rating-radio" autocomplete="off" />
                                <label class="sln-rating__label sln-rating__label-2" for="rt-2"></label>
                                <input class="sln-rating__input sln-rating__input-3" type="radio" id="rt-3" value="3" name="rating-radio" autocomplete="off" />
                                <label class="sln-rating__label sln-rating__label-3" for="rt-3"></label>
                                <input class="sln-rating__input sln-rating__input-4" type="radio" id="rt-4" value="4" name="rating-radio" autocomplete="off" />
                                <label class="sln-rating__label sln-rating__label-4" for="rt-4"></label>
                                <input class="sln-rating__input sln-rating__input-5" type="radio" id="rt-5" value="5" name="rating-radio" autocomplete="off" />
                                <label class="sln-rating__label sln-rating__label-5" for="rt-5"></label>
                                <!--<label class="skip-button" for="skip-rating">&times;</label>-->
                            </div>
                        </div>
                    <?php endif ?>
                    <!-- THIS IS THE BUNCH OF HTML TO SHOW FOR THE RATING -->

                </div>
                <div class="row">
                    <div class="col-xs-12 col-md-6">
                        <div class="sln-checkbox--nu- sln-switch">
                            <input type="checkbox" id="_sln_booking_createuser" name="_sln_booking_createuser" />
                            <label for="_sln_booking_createuser"><?php esc_html_e('Save as new customer', 'salon-booking-system') ?></label>
                            <input type="hidden" id="_sln_booking_origin_source" name="_sln_booking_origin_source" value="<?php echo SLN_Enum_BookingOrigin::ORIGIN_ADMIN ?>" />
                        </div>
                    </div>
                </div>
            </div>


        </div><!-- sln-booking__customer // END -->

        <div id="salon-step-date"
            class="sln-box sln-box--main tab-pane sln-admin__tabpanel sln-admin__tabpanel--date" role="tabpanel"
            data-intervals="<?php echo esc_attr(wp_json_encode($intervals->toArray())); ?>"
            data-isnew="<?php echo $booking->isNew() ? 1 : 0 ?>"
            data-deposit_amount="<?php echo $settings->getPaymentDepositAmount() ?>"
            data-deposit_is_fixed="<?php echo (int) $settings->isPaymentDepositFixedAmount() ?>"
            data-m_attendant_enabled="<?php echo $settings->get('m_attendant_enabled') ?>"
            data-mode="<?php echo $mode ?>"
            data-required_user_fields="<?php echo $checkoutFields->implode(',') ?>"
            data-customer_fields="<?php echo $customer_fields->implode(',') ?>"
            data-booking_id="<?php echo $booking->getId() ?>">
            <div data-intervals="<?php echo esc_attr(wp_json_encode($intervals->toArray())); ?>"
                data-isnew="<?php echo $booking->isNew() ? 1 : 0 ?>"
                data-deposit_amount="<?php echo $settings->getPaymentDepositAmount() ?>"
                data-deposit_is_fixed="<?php echo (int) $settings->isPaymentDepositFixedAmount() ?>"
                data-m_attendant_enabled="<?php echo $settings->get('m_attendant_enabled') ?>"
                data-mode="<?php echo $mode ?>"
                data-required_user_fields="<?php echo $checkoutFields->implode(',') ?>"
                data-customer_fields="<?php echo $customer_fields->implode(',') ?>"
                data-booking_id="<?php echo $booking->getId() ?>">
                <h4 class="sln-box-title--nu--sec"><?php esc_html_e('Date', 'salon-booking-system') ?></h4>
                <div class="row form-inline">
                    <?php if (!empty($edit_last_author)): ?>
                        <div class="booking-last-edit hide">
                            <?php esc_html_e('Last edit', 'salon-booking-system') ?>&nbsp;<span class="booking-last-edit-date"><?php echo get_the_modified_date('d.m.Y', $booking->getId()) ?></span>&nbsp;@ &nbsp;<span class="booking-last-edit-time"><?php echo get_post_modified_time('H.i', false, $booking->getId()) ?></span>&nbsp;<?php esc_html_e('by', 'salon-booking-system') ?>&nbsp;<span class="booking-last-edit-author"><?php echo $edit_last_author->display_name ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($mode === 'sln_editor'): ?>
                        <script>
                            jQuery(function() {
                                parent.jQuery('#sln-booking-editor-modal .booking-last-edit-div').html(jQuery('.booking-last-edit').html())
                            });
                        </script>
                    <?php endif; ?>
                    <div class="col-xs-12 col-sm-4 col-md-3 <?php echo in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ? 'sln-disabled' : '' ?>">
                        <div class="form-group sln-input--simple">
                            <label for="<?php echo SLN_Form::makeID($helper->getFieldName($postType, 'date')) ?>"><?php esc_html_e('Select a day', 'salon-booking-system') ?></label>
                            <?php SLN_Form::FieldJSDate(
                                $helper->getFieldName($postType, 'date'),
                                $selectedDate,
                                array(
                                    'popup-class' => ($mode === 'sln_editor' ? 'off-sm-md-support' : ''),
                                    'extending-classes' => (isset($_GET['action']) && $_GET['action'] == 'duplicate' ? 'cloned-data' : ''),
                                )
                            ); ?>
                        </div>
                    </div>
                    <div class="col-xs-12 col-sm-4 col-md-3 <?php echo in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ? 'sln-disabled' : '' ?>">
                        <div class="form-group sln-input--simple">
                            <label for="<?php echo SLN_Form::makeID($helper->getFieldName($postType, 'time')) ?>"><?php esc_html_e('Select an hour', 'salon-booking-system') ?></label>
                            <?php SLN_Form::fieldJSTime(
                                $helper->getFieldName($postType, 'time'),
                                $selectedTime,
                                array(
                                    'interval' => $plugin->getSettings()->get('interval'),
                                    'popup-class' => ($mode === 'sln_editor' ? 'off-sm-md-support' : ''),
                                    'extending-classes' => (isset($_GET['action']) && $_GET['action'] == 'duplicate' ? 'cloned-data' : ''),
                                )
                            ); ?>
                        </div>
                    </div>
                    <div class="col-xs-12 col-sm-4 col-md-5 <?php echo in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ? 'sln-disabled' : '' ?>">
                        <div class="row">
                            <div class="col-xs-12 col-md-6">

                            </div>

                        </div>
                    </div>
                </div>

                <div class="row form-inline">

                    <div class="col-xs-12 col-md-6 col-sm-6" id="sln-notifications" data-valid-message="<?php esc_html_e('OK! the date and time slot you selected is available', 'salon-booking-system'); ?>"></div>

                </div>

            </div>
        </div><!-- salon-step-date // END -->

        <div id="sln-booking__services" role="tabpanel" class="sln-box sln-box--main tab-pane sln-admin__tabpanel sln-admin__tabpanel--services <?php echo in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ? 'sln-disabled' : '' ?>">
            <h4 class="sln-box-title--nu--sec"><?php esc_html_e('Service', 'salon-booking-system'); ?></h4>
            <?php echo $plugin->loadView('metabox/_booking_services', compact('booking')); ?>
        </div><!-- sln-booking__services // END -->

        <div id="sln-booking__totals" role="tabpanel" class="sln-box sln-box--main tab-pane sln-admin__tabpanel sln-admin__tabpanel--totals">
            <h4 class="sln-box-title--nu--sec"><?php esc_html_e('Totals', 'salon-booking-system'); ?></h4>
            <div class="sln-box__fl sln-box__fl--75">
                <div class="sln-box__fl__item sln-input--simple">
                    <div class="form-group sln_meta_field sln-select">
                        <label><?php esc_html_e('Duration', 'salon-booking-system'); ?></label>
                        <input type="text" id="sln-duration" value="<?php echo esc_attr($booking->getDuration()->format('H:i')) ?>" class="form-control" />
                    </div>
                </div>
                <div class="sln-box__fl__item sln-input--simple">
                    <?php $helper->showFieldText(
                        $helper->getFieldName($postType, 'amount'),
                        apply_filters('sln.template.metabox.booking.total_amount_label', __('Amount', 'salon-booking-system') . ' (' . $settings->getCurrencySymbol() . ')', $booking),
                        $booking->getAmount()
                    ); ?>
                </div>
                <?php echo $booking->getTransactionId() ? '<div class="sln-box__fl__item sln-box__fl__item--transaction sln-input--simple"><label>' . esc_html__("Transaction ID", 'salon-booking-system') . '</label><h5 class="sln-box-title--nu--ter sln-box-title--nu--dark">' . esc_attr(implode(', ', $booking->getTransactionId())) . '</h5></div>' :
                    '';
                ?>
                <?php if ($settings->isTipRequestEnabled()): ?>
                    <div class="sln-box__fl__item sln-input--simple">
                        <?php $helper->showFieldText(
                            $helper->getFieldName($postType, 'tips'),
                            __('Tip', 'salon-booking-system'),
                            $booking->getTips()
                        ) ?>
                    </div>
                <?php endif; ?>
                <?php if ($settings->isPayEnabled()) { ?>
                    <div class="sln-box__fl__item sln-input--simple">
                        <?php $helper->showFieldText(
                            $helper->getFieldName($postType, 'deposit'),
                            __('Deposit', 'salon-booking-system') . ' ' . SLN_Enum_PaymentDepositType::getLabel($settings->getPaymentDepositValue()) . ' (' . $settings->getCurrencySymbol() . ')',
                            $booking->getDeposit()
                        ); ?>
                    </div>
                <?php } ?>
                <div class="sln-box__fl__item sln-input--simple">
                    <div class="form-group sln_meta_field">
                        <label for="<?php echo $helper->getFieldName($postType, 'remainedAmount') ?>"><?php echo esc_html__('Amount to be paid', 'salon-booking-system') ?></label>
                        <?php SLN_Form::fieldText(
                            $helper->getFieldName($postType, 'remainedAmount'),
                            $booking->getRemaingAmountAfterPay(),
                            [
                                'attrs' => [
                                    'readonly' => 'readonly',
                                ],
                            ]
                        ); ?>
                    </div>
                </div>

                <?php SLN_Form::fieldText(
                    $helper->getFieldName($postType, 'paid_remained_amount'),
                    $booking->getPaidRemainedAmount(),
                    [
                        'type' => 'hidden',
                    ]
                ); ?>

                <?php
                $enableDiscountSystem = $plugin->getSettings()->get('enable_discount_system');
                if ($enableDiscountSystem) {
                    $coupons = $plugin->getRepository(SLB_Discount_Plugin::POST_TYPE_DISCOUNT)->getAll();
                    if ($coupons) {
                        $couponArr = array();
                        foreach ($coupons as $coupon) {
                            $couponArr[$coupon->getId()] = $coupon->getTitle();
                        }
                        $discount_helper = new SLB_Discount_Helper_Booking();

                        $discounts = $discount_helper->getBookingDiscountIds($booking);

                        $tmpCoupons = array();

                        foreach ($discounts as $discountID) {
                            if (!empty($couponArr[$discountID])) {
                                $tmpCoupons[$discountID] = $couponArr[$discountID];
                                unset($couponArr[$discountID]);
                            }
                        }

                        $couponArr = $tmpCoupons + $couponArr;

                ?>
                        <div class="sln-box__fl__item sln-input--simple">
                            <div class="form-group sln_meta_field sln-select sln-select2-selection__search-primary">
                                <label><?php esc_html_e('Discount', 'salon-booking-system'); ?></label>
                                <?php SLN_Form::fieldSelect(
                                    $helper->getFieldName($postType, 'discounts[]'),
                                    $couponArr,
                                    $discount_helper->getBookingDiscountIds($booking),
                                    array(
                                        'map' => true,
                                        'empty_value' => 'No Discounts',
                                    )
                                ); ?>
                                <span class="help-block" style="display: none"><?php printf(
                                                                                    // translators: %s will be replaced by the "Update booking"
                                                                                    esc_html__('Please click on "%s" button to see the updated prices', 'salon-booking-system'),
                                                                                    esc_html__("Update booking", 'salon-booking-system')
                                                                                ); ?></span>
                            </div>
                        </div>
                <?php }
                }
                do_action('sln.template.metabox.booking.total_amount_row', $booking); ?>
                <div class="sln-box__fl__item sln-box__fl__item--2col">
                    <button class="sln-btn sln-btn--borderonly sln-btn--bigger sln-btn--fullwidth" id="calculate-total"><?php esc_html_e('Update totals', 'salon-booking-system') ?></button>
                    <span class="sln-calc-total-loading"></span>
                </div>

            </div>
        </div><!-- sln-booking__totals // END -->

        <div id="sln-booking__notes" role="tabpanel" class="sln-box sln-box--main tab-pane sln-admin__tabpanel sln-admin__tabpanel--notes">
            <div class="sln_booking-details__notes">
                <h4 class="sln-box-title--nu--sec">
                    <?php esc_html_e('Notes', 'salon-booking-system') ?>
                </h4>
                <div class="row">
                    <div class="col-xs-12 col-sm-6">
                        <div class="form-group sln_meta_field sln-input--simple">
                            <label><?php esc_html_e('Personal message', 'salon-booking-system'); ?></label>
                            <?php SLN_Form::fieldTextarea(
                                $helper->getFieldName($postType, 'note'),
                                $booking->getNote()
                            ); ?>
                        </div>
                    </div>
                    <div class="col-xs-12 col-sm-6">
                        <div class="form-group sln_meta_field sln-input--simple">
                            <label><?php esc_html_e('Administration notes', 'salon-booking-system'); ?></label>
                            <?php SLN_Form::fieldTextarea(
                                $helper->getFieldName($postType, 'admin_note'),
                                $booking->getAdminNote()
                            ); ?>
                        </div>
                    </div>
                </div>
                <!-- collapse END -->
            </div>
        </div><!-- sln-booking__totals // END -->

        <?php if (class_exists('\SalonSOAP\Addon')) { ?>
            <div id="sln-booking__soap" role="tabpanel" class="sln-box sln-box--main tab-pane sln-admin__tabpanel sln-admin__tabpanel--soap">
                <div class="sln_booking-details__notes">
                    <h4 class="sln-box-title--nu--sec">SOAP Notes</h4>
                    <?php echo $plugin->loadView('metabox/soap_notes', array('postType' => $postType, 'booking' => $booking, 'helper' => $helper)); ?>
                </div>
            </div><!-- sln-booking__soap // END -->
        <?php } //// if (class_exists('\SalonSOAP\Addon')) // END //// 
        ?>
        <!-- ˇ .tab-content // END -->
    </div>
    <!-- ^ .tab-content // END -->

    <!--
<div class="sln-box__collapsewrp <?php echo in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ? 'sln-disabled' : '' ?>" id="collapseMoreDetailsWrapper">
    <h1>TEST</h1>
    <div class="sln-box sln-box--main  sln-box--header">
        <button class="sln-btn sln-btn--big sln-btn--icon sln-btn--icon--left--alt sln-icon--arrow--up sln-btn--textonly collapsed" type="button" data-toggle="collapse" data-target="#collapseMoreDetails" aria-expanded="false" aria-controls="collapseMoreDetails">
            <?php esc_html_e('Show more details', 'salon-booking-system') ?>
        </button>
    </div>
    <div class="sln-box__collapse collapse" id="collapseMoreDetails">
        
    
    
</div>
</div> collapse wrapper END -->
    <?php if (preg_match('/post\-new\.php/i', $_SERVER['REQUEST_URI'])): ?>
        <?php SLN_Form::fieldText(
            'sln_action',
            'create',
            array('type' => 'hidden')
        ); ?>
    <?php else: ?>
        <?php SLN_Form::fieldText(
            'sln_action',
            'edit',
            array('type' => 'hidden')
        ); ?>
    <?php endif; ?>
    <?php SLN_Form::fieldText(
        'sln_action_source',
        'page',
        array('type' => 'hidden')
    ); ?>
    <?php if (!empty($_GET['mode']) && $_GET['mode'] === 'sln_editor'): ?>
        <?php SLN_Form::fieldText(
            'sln_action_source',
            'popup',
            array('type' => 'hidden')
        ); ?>
    <?php endif; ?>
    <?php if (isset($_GET['sln_editor_popup'])): ?>
        <script>
            jQuery(document).ready(function() {

                jQuery('.sln-last-edit').html(jQuery('.booking-last-edit').html())

                jQuery("[data-action=save-edited-booking]").on("click", function() {
                    if (sln_validateBooking()) {
                        jQuery("#save-post").trigger("click");
                    }
                });

                jQuery("[data-action=delete-edited-booking]").on("click", function() {
                    if (sln_validateBooking()) {
                        var href = jQuery(".submitdelete").attr("href");
                        jQuery.get(href).success(function() {
                            window.close();
                        });
                    }
                });

                jQuery("[data-action=duplicate-edited-booking]").on("click", function() {

                    if (jQuery(this).closest('.sln-duplicate-booking--disabled').length > 0) {
                        return false;
                    }

                    if (sln_validateBooking()) {
                        var href = '<?php echo admin_url('/post-new.php?post_type=sln_booking&action=duplicate&post=%id&mode=sln_editor&sln_editor_popup=1') ?>';
                        href = href.replace('%id', jQuery('#post_ID').val());
                        window.location.href = href;
                    }
                });
            })
        </script>
        <div class="sln-editor-popup-actions pull-right">
            <div class="sln-last-edit"></div>
            <div class="sln-editor-popup-actions-list">
                <button type="button" class="sln-btn sln-btn--nu sln-btn--nu--highemph sln-btn--big" aria-hidden="true" data-action="save-edited-booking">
                    <?php esc_html_e('Save', 'salon-booking-system') ?>
                </button>
                <div class="sln-duplicate-booking <?php echo !defined("SLN_VERSION_PAY")  ? 'sln-duplicate-booking--disabled' : '' ?> <?php echo isset($_GET['action']) && $_GET['action'] === 'duplicate' ? 'hide' : '' ?>">
                    <span class="sln-booking-pro-feature-tooltip">
                        <a href="https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=default_status&utm_medium=free-edition-back-end&utm_campaign=unlock_feature&utm_id=GOPRO" target="_blank">
                            <?php echo esc_html__('Switch to PRO to unlock this feature', 'salon-booking-system') ?>
                        </a>
                    </span>
                    <button type="button" class="sln-btn sln-btn--nu sln-btn--nu--lowhemph sln-btn--big" aria-hidden="true" data-action="duplicate-edited-booking"><?php esc_html_e('Duplicate', 'salon-booking-system') ?></button>
                </div>
                <button type="button" class="sln-btn sln-btn--nu sln-btn--nu--lowhemph sln-btn--big" aria-hidden="true" data-action="delete-edited-booking">
                    <?php esc_html_e('Delete', 'salon-booking-system') ?>
                </button>
            </div>
        </div>
    <?php endif; ?>