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
use SLB_API_Mobile\Helper\UserRoleHelper;
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
                    SLN_Enum_BookingStatus::toBackendWrapper(),
                    empty($_GET['post']) && SLN_Plugin::getInstance()->getSettings()->getDefaultBookingStatus() ?
                        SLN_Plugin::getInstance()->getSettings()->getDefaultBookingStatus() :
                        $booking->getStatus(),
                    array('map' => true)
                );
                ?>
            </div>
            <div class="sln-set-default-booking-status--block-labels sln-profeature <?php echo !defined("SLN_VERSION_PAY")  ? 'sln-set-default-booking-status--block-label-disabled sln-profeature--disabled  sln-profeature__tooltip-wrapper' : '' ?>" data-default-status="<?php echo SLN_Plugin::getInstance()->getSettings()->getDefaultBookingStatus() ?>">
                <?php echo $plugin->loadView(
                    'metabox/_pro_feature_tooltip',
                    array(
                        // 'cta_url' => 'https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=default_status&utm_medium=free-edition-back-end&utm_campaign=unlock_feature&utm_id=GOPRO',
                        'additional_classes' => 'sln-profeature--button--bare- sln-profeature--sln-booking__header',
                        'trigger' => 'sln-set-default-booking-status',
                    )
                ); ?>
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
    $user_role_helper = new UserRoleHelper();

    $hide_phone = $user_role_helper->is_hide_customer_phone();
    $hide_email = $user_role_helper->is_hide_customer_email();

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
                                        if($hide_email){
                                            $additional_opts[2]['type'] = 'password';
                                        }

                                    }
                                    if ($key == 'phone') {
                                        $additional_opts[2]['type'] = 'tel';
                                        if($hide_phone){
                                            $additional_opts[2]['type'] = 'password';
                                        }

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
                                                    $upload_dir = wp_get_upload_dir();
                                                    $custom_path = implode('/', array_filter(array($upload_dir['baseurl'], 'salonbookingsystem/user/' . $booking->getCustomer()->getId(), $file['file'])));
                                                    $custom_path2 = implode('/', array_filter(array($upload_dir['baseurl'], 'salonbookingsystem/user/0', $file['file'])));

                                                    $default_path = implode('/', array_filter(array($upload_dir['baseurl'], trim($file['subdir'], '/'), $file['file'])));

                                                    if (file_exists(str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $default_path))) {
                                                        $file_url = $default_path;
                                                    } elseif (file_exists(str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $custom_path))) {
                                                        $file_url = $custom_path;
                                                    } elseif (file_exists(str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $custom_path2))) {
                                                        $file_url = $custom_path2;
                                                    } else {
                                                        $file_url = null;
                                                    }

                                                    $file_name = preg_replace('/^[0-9]+_/i', '', $file['file']);
                                                }
                                                ?>
                                                <a href="<?php echo $file_url ?>" download><?php echo esc_attr($file_name) ?></a>
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
                                <?php $current_rating = (int) (method_exists($booking, 'getRating') ? $booking->getRating() : 0); ?>
                                <input class="sln-rating__input sln-rating__input-0" type="radio" value="-1" id="skip-rating" name="rating-radio" autocomplete="off" <?php echo ($current_rating <= 0 ? 'checked' : ''); ?> />
                                <label class="sln-rating__label hidden"></label>
                                <input class="sln-rating__input sln-rating__input-1" type="radio" id="rt-1" value="1" name="rating-radio" autocomplete="off" <?php echo ($current_rating === 1 ? 'checked' : ''); ?> />
                                <label class="sln-rating__label sln-rating__label-1" for="rt-1"></label>
                                <input class="sln-rating__input sln-rating__input-2" type="radio" id="rt-2" value="2" name="rating-radio" autocomplete="off" <?php echo ($current_rating === 2 ? 'checked' : ''); ?> />
                                <label class="sln-rating__label sln-rating__label-2" for="rt-2"></label>
                                <input class="sln-rating__input sln-rating__input-3" type="radio" id="rt-3" value="3" name="rating-radio" autocomplete="off" <?php echo ($current_rating === 3 ? 'checked' : ''); ?> />
                                <label class="sln-rating__label sln-rating__label-3" for="rt-3"></label>
                                <input class="sln-rating__input sln-rating__input-4" type="radio" id="rt-4" value="4" name="rating-radio" autocomplete="off" <?php echo ($current_rating === 4 ? 'checked' : ''); ?> />
                                <label class="sln-rating__label sln-rating__label-4" for="rt-4"></label>
                                <input class="sln-rating__input sln-rating__input-5" type="radio" id="rt-5" value="5" name="rating-radio" autocomplete="off" <?php echo ($current_rating === 5 ? 'checked' : ''); ?> />
                                <label class="sln-rating__label sln-rating__label-5" for="rt-5"></label>
                                <!--<label class="skip-button" for="skip-rating">&times;</label>-->
                            </div>
                            <?php if ($current_rating <= 0): ?>
                                <div class="sln-rating__notice">
                                    <small class="description"><?php esc_html_e('Not rated yet', 'salon-booking-system'); ?> &middot; 
                                        <?php if (defined('SLN_VERSION_PAY') && SLN_VERSION_PAY): ?>
                                            <a href="#" id="sln-request-feedback" data-booking-id="<?php echo (int) $booking->getId(); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('sln_send_feedback_' . (int) $booking->getId())); ?>"><?php esc_html_e('Request a feedback from customer by email', 'salon-booking-system'); ?></a>
                                        <?php else: ?>
                                            <span class="sln-profeature__tooltip-wrapper" title="<?php echo esc_attr__('Upgrade to PRO edition to use this feature', 'salon-booking-system'); ?>" style="cursor:not-allowed;opacity:.6;">
                                                <?php esc_html_e('Request a feedback from customer by email', 'salon-booking-system'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <script>
                                jQuery(function($){
                                    $(document).on('click', '#sln-request-feedback', function(e){
                                        e.preventDefault();
                                        var $a = $(this);
                                        var bid = $a.data('booking-id');
                                        var nonce = $a.data('nonce');
                                        $a.prop('disabled', true);
                                        $.post(ajaxurl, { action: 'sln_send_feedback_email', booking_id: bid, nonce: nonce }, function(resp){
                                            if(resp && resp.success){
                                                alert('<?php echo esc_js(__('Feedback request sent.', 'salon-booking-system')); ?>');
                                            } else {
                                                alert('<?php echo esc_js(__('Could not send feedback request.', 'salon-booking-system')); ?>');
                                            }
                                        }).always(function(){
                                            $a.prop('disabled', false);
                                        });
                                    });
                                });
                                </script>
                            <?php else: ?>
                                <?php
                                // Fetch latest feedback comment (rating review)
                                $comments = get_comments(array(
                                    'post_id' => (int) $booking->getId(),
                                    'type'    => 'sln_review',
                                    'number'  => 1,
                                    'status'  => 'all',
                                    'orderby' => 'comment_date_gmt',
                                    'order'   => 'DESC',
                                ));
                                if (!empty($comments)) {
                                    $c = $comments[0];
                                    $ts = strtotime($c->comment_date_gmt . ' GMT');
                                    $dt = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $ts);
                                    ?>
                                    <div class="sln-rating__notice">
                                        <small class="description">
                                            <?php echo sprintf(
                                                /* translators: 1: customer name, 2: date time */
                                                esc_html__('Rated by %1$s on %2$s', 'salon-booking-system'),
                                                esc_html($c->comment_author),
                                                esc_html($dt)
                                            ); ?>
                                        </small>
                                    </div>
                                <?php } ?>
                            <?php endif; ?>
                        </div>
                    <?php endif ?>
                    <!-- THIS IS THE BUNCH OF HTML TO SHOW FOR THE RATING -->

                    <?php
                    // No-Show Count and Toggle
                    $customer = $booking->getCustomer();
                    $bookingId = $booking->getId();
                    $isNoShow = get_post_meta($bookingId, 'no_show', true);
                    $customerId = $customer ? $customer->getId() : null;
                    
                    // Get customer no-show count
                    $customerNoShowCount = 0;
                    if ($customer && !$customer->isEmpty() && $customerId) {
                        global $wpdb;
                        
                        // Get all bookings for this customer
                        // Note: Don't filter by post_status - SLN uses custom statuses (sln-b-*)
                        // Note: Filter by post_author in PHP to handle string/int comparison issues
                        $allBookingsQuery = $wpdb->prepare(
                            "SELECT p.ID, p.post_author
                            FROM {$wpdb->posts} p
                            WHERE p.post_type = %s",
                            SLN_Plugin::POST_TYPE_BOOKING
                        );
                        $allBookingsRaw = $wpdb->get_results($allBookingsQuery);
                        
                        // Filter for matching customer ID (handles post_author stored as string vs int)
                        $allBookings = array();
                        foreach ($allBookingsRaw as $b) {
                            if ($b->post_author == $customerId) {
                                $allBookings[] = $b;
                            }
                        }
                        
                        // Count no-shows manually
                        foreach ($allBookings as $customerBooking) {
                            $noShowValue = get_post_meta($customerBooking->ID, 'no_show', true);
                            if ($noShowValue == 1 || $noShowValue === '1') {
                                $customerNoShowCount++;
                            }
                        }
                    }
                    ?>

                    <!-- No-Show Tracking Section - 70% CTA Wrapper -->
                    <div class="col-xs-12 col-sm-8 col-xl-6 sln-noshow-wrapper sln-profeature <?php echo !defined("SLN_VERSION_PAY") ? 'sln-profeature--disabled sln-profeature__tooltip-wrapper' : '' ?>">
                        <?php echo $plugin->loadView(
                            'metabox/_pro_feature_tooltip',
                            array(
                                'cta_url' => 'https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=noshow_tracking&utm_medium=free-edition-back-end&utm_campaign=unlock_feature&utm_id=GOPRO',
                                'additional_classes' => 'sln-profeature--button--bare sln-profeature--noshow-tracking',
                                'trigger' => 'sln-noshow-tracking',
                            )
                        ); ?>
                        <label class="sln-booking-customer-score--title">
                            <?php esc_html_e('No-show count', 'salon-booking-system') ?>
                        </label>
                        <div class="sln-noshow">
                            <div class="sln-noshow-count">
                                <span class="sln-noshow-count__value"><?php echo esc_html($customerNoShowCount); ?></span>
                            </div>
                            <div class="sln-noshow-toggle">
                                <a href="#" 
                                   id="sln-noshow-toggle-btn"
                                   class="sln-noshow-toggle__button <?php echo $isNoShow ? 'active' : ''; ?>" 
                                   data-bookingid="<?php echo esc_attr($bookingId); ?>" 
                                   data-no-show="<?php echo $isNoShow ? '1' : '0'; ?>"
                                   data-nonce="<?php echo esc_attr(wp_create_nonce('ajax_post_validation')); ?>"
                                   aria-label="<?php esc_attr_e('Toggle no-show status', 'salon-booking-system'); ?>">
                                    <i class="sln-icon sln-icon--no-show"></i>
                                </a>
                            </div>
                        </div>
                        <!-- Hidden field to persist no-show state on form submit -->
                        <input type="hidden" id="_sln_booking_no_show" name="_sln_booking_no_show" value="<?php echo $isNoShow ? '1' : ''; ?>" />
                    </div>
                    <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('#sln-noshow-toggle-btn').on('click', function(e) {
                            e.preventDefault();
                            
                            // Check if in free edition disabled mode
                            if ($(this).closest('.sln-profeature--disabled').length) {
                                return false;
                            }
                            
                            var $btn = $(this);
                            var bookingId = $btn.data('bookingid');
                            var currentNoShow = parseInt($btn.data('no-show'));
                            var nonce = $btn.data('nonce');
                            
                            // Use unified no-show toggle function
                            window.slnToggleNoShow({
                                bookingId: bookingId,
                                currentNoShow: currentNoShow,
                                nonce: nonce,
                                $button: $btn,
                                onSuccess: function(data) {
                                    // Update hidden form field for persistence on page save
                                    $('#_sln_booking_no_show').val(data.noShow == 1 ? '1' : '');
                                    
                                    // Update count
                                    var $count = $('.sln-noshow-count__value');
                                    var oldCount = parseInt($count.text()) || 0;
                                    var newCount = data.noShow == 1 ? oldCount + 1 : Math.max(0, oldCount - 1);
                                    $count.text(newCount);
                                }
                            });
                        });
                    });
                    </script>

                </div>
                <div class="row">
                    <div class="col-xs-12 col-md-6">
                        <div class="sln-checkbox--nu- sln-switch" id="sln-save-new-customer-wrapper">
                            <input type="checkbox" id="_sln_booking_createuser" name="_sln_booking_createuser" />
                            <label for="_sln_booking_createuser"><?php esc_html_e('Save as new customer', 'salon-booking-system') ?></label>
                            <?php 
                            // Only set origin on NEW bookings (preserve original booking channel)
                            // Check if this booking already has an origin set
                            if (!metadata_exists('post', $booking->getId(), '_sln_booking_origin_source')) : 
                            ?>
                            <input type="hidden" id="_sln_booking_origin_source" name="_sln_booking_origin_source" value="<?php echo SLN_Enum_BookingOrigin::ORIGIN_ADMIN ?>" />
                            <?php endif; ?>
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
                        <input type="text" id="sln-duration" value="<?php echo esc_attr($booking->getDuration()->format('H:i')) ?>" class="form-control" readonly />
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
                        <div class="form-group sln_meta_field">
                            <label for="<?php echo $helper->getFieldName($postType, 'deposit') ?>"><?php echo __('Deposit', 'salon-booking-system') . ' ' . SLN_Enum_PaymentDepositType::getLabel($settings->getPaymentDepositValue()) . ' (' . $settings->getCurrencySymbol() . ')' ?></label>
                            <?php SLN_Form::fieldText(
                                $helper->getFieldName($postType, 'deposit'),
                                $booking->getDeposit()
                            ); ?>
                        </div>
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
                
                <!-- Current Booking Notes (Editable) -->
                <div class="row">
                    <div class="col-xs-12 col-sm-6">
                        <div class="form-group sln_meta_field sln-input--simple">
                            <label style="font-size: 0.8rem;"><?php esc_html_e('Current booking - customer message', 'salon-booking-system'); ?></label>
                            <?php SLN_Form::fieldTextarea(
                                $helper->getFieldName($postType, 'note'),
                                $booking->getNote()
                            ); ?>
                        </div>
                    </div>
                    <div class="col-xs-12 col-sm-6">
                        <div class="form-group sln_meta_field sln-input--simple">
                            <label style="font-size: 0.8rem;"><?php esc_html_e('Current booking - administration note', 'salon-booking-system'); ?></label>
                            <?php SLN_Form::fieldTextarea(
                                $helper->getFieldName($postType, 'admin_note'),
                                $booking->getAdminNote()
                            ); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Customer Record Notes (Read-Only) -->
                <?php 
                $customer_id = $booking->getUserId();
                // Only show customer record notes if customer has a valid user profile
                if ($customer_id && $customer_id > 0 && get_userdata($customer_id)):
                    $customer_personal_note = get_user_meta($customer_id, '_sln_personal_note', true);
                    $customer_admin_note = get_user_meta($customer_id, '_sln_administration_note', true);
                    $customer_edit_url = get_edit_user_link($customer_id);
                ?>
                <!-- Separator Row -->
                <div class="row" style="margin: 20px 0;">
                    <div class="col-xs-12">
                        <hr style="border: 0; border-top: 1px solid #e5e5e5; margin: 0;">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-xs-12 col-sm-6">
                        <div class="form-group sln_meta_field sln-input--simple">
                            <label style="display: flex; align-items: center; justify-content: space-between; font-size: 0.8rem;">
                                <span><?php esc_html_e('Customer record - personal note', 'salon-booking-system'); ?></span>
                                <?php if ($customer_edit_url): ?>
                                    <a href="<?php echo esc_url($customer_edit_url); ?>" target="_blank" class="sln-icon--customerurl" style="font-size: 13px; padding: 0; border: none; background: transparent; width: auto; height: auto;">
                                        <?php esc_html_e('edit this field', 'salon-booking-system'); ?>
                                    </a>
                                <?php endif; ?>
                            </label>
                            <textarea class="form-control" rows="5" readonly style="background-color: #f5f5f5; cursor: not-allowed;"><?php echo esc_textarea($customer_personal_note); ?></textarea>
                            <small class="form-text text-muted" style="color: #999; font-size: 12px; margin-top: 5px;">
                                <?php esc_html_e('Read only field', 'salon-booking-system'); ?>
                            </small>
                        </div>
                    </div>
                    <div class="col-xs-12 col-sm-6">
                        <div class="form-group sln_meta_field sln-input--simple">
                            <label style="display: flex; align-items: center; justify-content: space-between; font-size: 0.8rem;">
                                <span><?php esc_html_e('Customer record - administration note', 'salon-booking-system'); ?></span>
                                <?php if ($customer_edit_url): ?>
                                    <a href="<?php echo esc_url($customer_edit_url); ?>" target="_blank" class="sln-icon--customerurl" style="font-size: 13px; padding: 0; border: none; background: transparent; width: auto; height: auto;">
                                        <?php esc_html_e('edit this field', 'salon-booking-system'); ?>
                                    </a>
                                <?php endif; ?>
                            </label>
                            <textarea class="form-control" rows="5" readonly style="background-color: #f5f5f5; cursor: not-allowed;"><?php echo esc_textarea($customer_admin_note); ?></textarea>
                            <small class="form-text text-muted" style="color: #999; font-size: 12px; margin-top: 5px;">
                                <?php esc_html_e('Read only field', 'salon-booking-system'); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endif; // End customer record notes check ?>
                <!-- collapse END -->
            </div>
        </div><!-- sln-booking__notes // END -->

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
    <style>
    .sln-btn--big.hide-important{
        display:none!important;
    }
    </style>
        <script>
            jQuery(document).ready(function() {
                if( $('#_sln_booking_email').val() == ''){
                    $('[data-action="clone-edited-booking"]').addClass('hide-important');
                    $('[data-action="delete-edited-booking"]').addClass('hide-important');
                    $('[data-action="save-edited-booking"]').addClass('hide-important');
                }
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

                jQuery("[name=week_time]").on("change", function() {
                    $("[name=unit_times_input]").trigger('click')
                })
                jQuery("[name=unit_times_input]").on("click", function() {
                        var times = parseInt($(this).val());
                        var week_time = parseInt($('select[name="week_time"]').val());
			            var label = times === 1 ? $('.times').data('text_s') : $('.times').data('text_m');
                        let dateStr = $('#_sln_booking_date').data('value').replace('00:00:00','').trim(); // '08/07/2025'

                        function parseFlexibleDate(dateStr) {
                            let parts;

                        if (/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) {
                            parts = dateStr.split('/');
                            return new Date(parts[2], parts[1] - 1, parts[0]); // yyyy, mm, dd
                        }

                        if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
                            parts = dateStr.split('-');
                            return new Date(parts[0], parts[1] - 1, parts[2]); // yyyy, mm, dd
                        }

                        if (/^\d{2}-\d{2}-\d{4}$/.test(dateStr)) {
                            parts = dateStr.split('-');
                            return new Date(parts[2], parts[0] - 1, parts[1]); // yyyy, mm, dd
                        }
                        if (/^\d{2} [A-Za-z]{3} \d{4}$/.test(dateStr)) {
                            parts = dateStr.split(' ');
                            const monthMap = {
                                Jan: 0, Feb: 1, Mar: 2, Apr: 3, May: 4, Jun: 5,
                                Jul: 6, Aug: 7, Sep: 8, Oct: 9, Nov: 10, Dec: 11
                            };
                            let day = parseInt(parts[0]);
                            let month = monthMap[parts[1]];
                            let year = parseInt(parts[2]);
                            return new Date(year, month, day);
                        }
                            return null;
                        }

                        let date = parseFlexibleDate(dateStr);

                        if (date && !isNaN(date)) {
                        date.setDate(date.getDate() + 7 * week_time *times);

                        var newDateStr =
                        String(date.getDate()).padStart(2, '0') + '/' +
                        String(date.getMonth() + 1).padStart(2, '0') + '/' +
                        date.getFullYear();

                        $('.time_until .time_date').text(newDateStr);
                        $('.clone-info .times').text(label);
                        } else {
                        console.error("wrong date1: " + dateStr);
                        }
                });

                jQuery("[data-action=clone-edited-booking]").on("click", function() {


                    if(($('[data-action=clone-edited-booking].confirm').length == 0)){


                        let dateStr =$('#_sln_booking_date').data('value').replace('00:00:00','').trim();

                        function parseFlexibleDate(dateStr) {
                            let parts;

                            if (/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) {
                                parts = dateStr.split('/');
                                return new Date(parts[2], parts[1] - 1, parts[0]); // yyyy, mm, dd
                            }

                            if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
                                parts = dateStr.split('-');
                                return new Date(parts[0], parts[1] - 1, parts[2]); // yyyy, mm, dd
                            }

                            if (/^\d{2}-\d{2}-\d{4}$/.test(dateStr)) {
                                parts = dateStr.split('-');
                                return new Date(parts[2], parts[0] - 1, parts[1]); // yyyy, mm, dd
                            }
                            if (/^\d{2} [A-Za-z]{3} \d{4}$/.test(dateStr)) {
                                parts = dateStr.split(' ');
                                const monthMap = {
                                    Jan: 0, Feb: 1, Mar: 2, Apr: 3, May: 4, Jun: 5,
                                    Jul: 6, Aug: 7, Sep: 8, Oct: 9, Nov: 10, Dec: 11
                                };
                                let day = parseInt(parts[0]);
                                let month = monthMap[parts[1]];
                                let year = parseInt(parts[2]);
                                return new Date(year, month, day);
                            }
                            return null;
                        }

                        let date = parseFlexibleDate(dateStr);

                            if (date && !isNaN(date)) {
                                date.setDate(date.getDate() + 7);

                                var newDateStr =
                                    String(date.getDate()).padStart(2, '0') + '/' +
                                    String(date.getMonth() + 1).padStart(2, '0') + '/' +
                                    date.getFullYear();

                                $('.time_until .time_date').text(newDateStr);
                            } else {
                                console.error("wrong date: " + dateStr);
                            }

                            $("[data-action=clone-edited-booking]").text($("[data-action=clone-edited-booking]").data('confirm'));
                            $("[data-action=clone-edited-booking]").addClass('confirm');
                            $('[data-action="delete-edited-booking"]').addClass('hide-important');
                            $('[data-action="save-edited-booking"]').addClass('hide-important');
                            $('.clone-info').show();
                            return false;
                           }
                            if (sln_validateBooking()) {
                                var bookingId = $('#post_ID').val();
                                var unit_times = $('.clone-info input').val();
                                var week_time = $('.clone-info select').val();
                                var data = "&action=salon&method=DuplicateClone&bookingId="+bookingId+"&unit="+unit_times+"&week_time="+week_time+"&security=" + salon.ajax_nonce;
                                $.ajax({
                                    url: salon.ajax_url,
                                    data: data,
                                    method: "POST",
                                    dataType: "json",
                                    success: function (data) {
                                        if (window.opener) {
                                            window.opener.location.reload();
                                        }
                                        window.close();
                                    },
                                });
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
                <div class="clone-info" style="font-family: 'Open Sans';display:none;">
                    <?php esc_html_e('Clone this booking', 'salon-booking-system') ?>
                    <input type="number" name="unit_times_input" min="1" value="1" style="width: 50px;"/>
                    <span class="times" data-text_s="<?php esc_html_e('time', 'salon-booking-system') ?>" data-text_m="<?php esc_html_e('times', 'salon-booking-system') ?>"><?php esc_html_e('time', 'salon-booking-system') ?></span>
                    <select name="week_time" >
                    <option value="1"><?php esc_html_e('every week', 'salon-booking-system') ?> </option>
                    <option value="2"><?php esc_html_e('every two weeks', 'salon-booking-system') ?> </option>
                    <option value="3"><?php esc_html_e('every three week', 'salon-booking-system') ?> </option>
                    <option value="4"><?php esc_html_e('every four week', 'salon-booking-system') ?> </option>
                    </select>
                    <span class="time_until" style="margin-left: 10px;font-size:13px;" ><?php esc_html_e('until', 'salon-booking-system') ?> <span class="time_date">%date</span></span>
                </div>
                <button type="button" class="sln-btn sln-btn--nu sln-btn--nu--lowhemph sln-btn--big" aria-hidden="true" data-confirm="<?php esc_html_e('Confirm', 'salon-booking-system') ?>" data-action="clone-edited-booking"><?php esc_html_e('Clone', 'salon-booking-system') ?></button>
                <button type="button" class="sln-btn sln-btn--nu sln-btn--nu--lowhemph sln-btn--big" aria-hidden="true" data-action="delete-edited-booking">
                    <?php esc_html_e('Delete', 'salon-booking-system') ?>
                </button>
                      <button type="button" class="sln-btn sln-btn--nu sln-btn--nu--lowhemph sln-btn--big" aria-hidden="true" onclick="window.close()">
                    <?php esc_html_e('Close', 'salon-booking-system') ?>
                </button>
            </div>
        </div>
    <?php endif; ?>