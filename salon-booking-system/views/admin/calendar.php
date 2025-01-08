<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
$addAjax = apply_filters('sln.template.calendar.ajaxUrl', '');
$ai = $plugin->getSettings()->getAvailabilityItems();
list($timestart, $timeend) = $ai->getTimeMinMax();
$timesplit = $plugin->getSettings()->getInterval();
$holidays_rules = apply_filters('sln.get-day-holidays-rules', $plugin->getSettings()->getDailyHolidayItems());

$holidays_assistants_rules  = array();
$assistants                 = $plugin->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT)->getAll();
foreach ($assistants as $att) {
    $holidays_assistants_rules[$att->getId()] = $att->getMeta('holidays_daily')?:array();
}
$holidays_assistants_rules = apply_filters('sln.get-day-holidays-assistants-rules', $holidays_assistants_rules, $assistants);
$day_calendar_holydays_ajax_data = apply_filters('sln.get-day-calendar-holidays-ajax-data', array());
$day_calendar_columns = $plugin->getSettings()->get('parallels_hour') * 2 + 1;
$replace_booking_modal_with_popup = $plugin->getSettings()->get('replace_booking_modal_with_popup');

$holidays = $plugin->getSettings()->get('holidays');
?>
<script type="text/javascript">
    var salon;
    var calendar_translations = {
        'Go to daily view': '<?php esc_html_e('Go to daily view', 'salon-booking-system')?>'
    };
    var salon_default_duration = <?php echo $timesplit; ?>;
    var daily_rules = JSON.parse('<?php echo wp_json_encode($holidays_rules); ?>');
    var daily_assistants_rules = JSON.parse('<?php echo wp_json_encode($holidays_assistants_rules); ?>');
    var holidays_rules_locale = {
        'block':'<?php esc_html_e('Block', 'salon-booking-system');?>',
        'block_confirm':'<?php esc_html_e('CONFIRM', 'salon-booking-system');?>',
        'unblock':'<?php esc_html_e('Unlock', 'salon-booking-system');?>',
        'unblock_these_rows':'<?php esc_html_e('UNLOCK', 'salon-booking-system');?>',
    }
    var sln_search_translation = {
        'tot':'<?php esc_html_e('Tot.', 'salon-booking-system');?>',
        'edit':'<?php esc_html_e('Edit', 'salon-booking-system');?>',
        'cancel':'<?php esc_html_e('Cancel', 'salon-booking-system');?>',
        'no_results':'<?php esc_html_e('No results', 'salon-booking-system');?>'
    }
    var calendar_locale = {
        'add_event':'<?php esc_html_e('Add book', 'salon-booking-system');?>',
    }

    var dayCalendarHolydaysAjaxData = JSON.parse('<?php echo wp_json_encode($day_calendar_holydays_ajax_data); ?>');

    var dayCalendarColumns = '<?php echo $day_calendar_columns ?>';

<?php $today = new DateTime()?>
jQuery(function($){
    sln_initSalonCalendar(
        $,
        salon.ajax_url+"&action=salon&method=calendar&security="+salon.ajax_nonce+'<?php echo $addAjax ?>',
//        '<?php echo SLN_PLUGIN_URL ?>/js/events.json.php',
        '<?php echo $today->format('Y-m-d') ?>',
        '<?php echo SLN_PLUGIN_URL ?>/views/js/calendar/',
        '<?php echo $plugin->getSettings()->get('calendar_view') ?: 'month' ?>',
        '<?php echo $plugin->getSettings()->get('week_start') ?: 0 ?>'
    );
});
jQuery("body").addClass("sln-body");

var replaceBookingModalWithPopup = +'<?php echo $replace_booking_modal_with_popup ?>';

</script>
<style>
.day-calbar,
.week-calbar{
    display: block;
    margin: 8px 15px 8px 15px;
    height: 8px;
    width: 100%;
    background-color: #dfdfdf;
}
.week-calbar{
    margin-top: -8px;
}
.month-calbar{
    display: block;
    height: 8px;
    width: 100%;
    background-color: #dfdfdf;
}
.calbar .busy{
    display: block;
    background-color: red;
    height: 8px;
    float: left;
}
.calbar .free{
    display: block;
    height: 8px;
    float: left;
    background-color: green;
}
.calbar-tooltip{
    background-color: #c7dff3;
    display: inline-block;
    width: 340px;
    height: 50px;
    padding: 5px;
    margin: -20px 0 -10px -80px;
}
.calbar-tooltip span{
    float: left;
    display: block;
    width: 33%;
    color: #666;
}
.calbar-tooltip strong{
    font-size: 16px;
    color: #0C6EB6;
    display: block;
    clear: both;
}
#cal-day-box .day-event-panel-border{
    position: absolute;
    height: inherit;
    width: 1px;
    background-color: #d4d4d4;
    top: -10px;
    left: 81px;
}
#cal-day-box .day-event{
    width: 199px !important;
    max-width: 199px !important;
    left: 82px;
}
#cal-day-box .cal-day-assistants{
    margin: 0 0 0 280px;
    width: 91.2%;
}
#cal-day-box .cal-day-assistant{
    display: inline-block;
    text-align: center;
    width: 200px !important;
    margin-right: -4px;
    font-size: 1.2em;
    font-weight: 600
}
#cal-day-box .day-highlight{
    border-left: none !important;
    cursor: pointer;
}
#cal-day-box .day-highlight:hover{
    text-decoration: underline;
}

.cal-day-hour-part .block_date,.cal-day-hour-part [data-action=add-event-by-date] {
    width: 5%;
    min-width: 5% !important;
    padding: 0 0.3rem;
    height: 28px;
    display: none;
}

.col-xs-12.col-md-6.mt-md-5.sln-box-title.current-view--title{margin-top: 60px; font-weight: 600; text-transform: uppercase;}

@media only screen and (min-width: 1200px) {
    .cal-day-hour-part [data-action=add-event-by-date] {
        width: 7%;
    }
}
.cal-day-hour-part{
    position: relative;
}
.cal-day-hour-part.active .block_date,.cal-day-hour-part.active [data-action=add-event-by-date] {
    display: inline-block;
    z-index: 99;
}
.cal-day-hour-part.selected [data-action=add-event-by-date]{
    display: none;
}
.cal-day-hour-part.active .block_date{
    transform: translateY(-50%);
}
#cal-day-box .cal-day-assistants{
    width: auto;
}

</style>
<div class="sln-bootstrap sln-calendar-plugin-update-notice--wrapper">
     <?php if (!defined("SLN_VERSION_PAY")): ?>
<div class="row">
    <div class="col-xs-12 sln-notice__wrapper">
        <div class="sln-notice sln-notice--bold sln-notice--subscription-free-version">
            <div class="sln-notice--bold__text">
                <h2><?php _e('<strong>Unlock exclusive features by upgrading to our Pro Edition.</strong>', 'salon-booking-system')?></h2>
             <p><?php esc_html_e('Join over 2,000 satisfied customers, get access to our Mobile Web App, to more than 20 Pro Features and our Priority Support.
', 'salon-booking-system') ?></p>
               <p><?php _e('<strong>Unlock the full potential of your Salon for a special price.</strong>', 'salon-booking-system')?></p>
            </div>
            <a href="https://www.salonbookingsystem.com/checkout?edd_action=add_to_cart&download_id=64398&edd_options%5Bprice_id%5D=2&discount=GOPRO15" target="_blank" class="sln-notice--plugin_update__action"><?php esc_html_e('Get 15% discount', 'salon-booking-system')?></a>
        </div>
    </div>
</div>
    <?php else: ?>
    <?php
global $sln_license;
if ($sln_license) {
    $sln_license->checkSubscription();
    $subscriptions_data = $sln_license->get('subscriptions_data');
}
$subscription = isset($subscriptions_data->subscriptions[0]) ? $subscriptions_data->subscriptions[0] : null;
$expire_days = $subscription ? ceil((strtotime($subscription->info->expiration) - current_time('timestamp')) / (24 * 3600)) : 0;
$expire = sprintf(
        // translators: %s the name of the expire days
        _n('%s day', '%s days', $expire_days, 'salon-booking-system'), $expire_days);
?>
    <?php if ($sln_license && !$sln_license->get('license_data') && !in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ): ?>
        <?php
$page_slug = $sln_license->get('slug') . '-license';
$license_url = admin_url('/plugins.php?page=' . $page_slug);
?>
        <div class="row">
            <div class="col-xs-12 sln-notice__wrapper">
                <div class="sln-notice sln-notice--bold sln-notice--subscription-expired">
                    <div class="sln-notice--bold__text">
                        <h2><?php _e('<strong>Attention:</strong> Please activate your license first', 'salon-booking-system')?></h2>
                    </div>
                    <a href="<?php echo $license_url ?>" target="_blank" class="sln-notice--plugin_update__action"><?php esc_html_e('Activate your license', 'salon-booking-system')?></a>
                </div>
            </div>
        </div>
    <?php endif;?>
    <?php if ($subscription && !in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles)): ?>
        <?php if ($subscription->info->status === 'cancelled'): ?>
        <div class="row">
            <div class="col-xs-12 sln-notice__wrapper">
                <div class="sln-notice sln-notice--bold sln-notice--subscription-cancelled">
                    <div class="sln-notice--bold__text">
                        <h2><?php _e('<strong>Your subscription has been cancelled!</strong>', 'salon-booking-system')?></h2>
                     <p><?php echo sprintf(
                            // translators: %s will be replaced by the license expiration time
                             esc_html__('Your license will expire in %s, then you need to purchase a new one at its full price to continue using our services.', 'salon-booking-system'), $expire) ?></p>
                       <p><?php _e('<strong>Renew it before the expiration and get a discounted price.</strong>', 'salon-booking-system')?></p>
                    </div>
                    <a href="https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=plugin-back-end_pro&utm_medium=license-status-notice&utm_campaign=renew-license&utm_id=renew-license" target="_blank" class="sln-notice--plugin_update__action"><?php esc_html_e('Renew for 15% off', 'salon-booking-system')?></a>
                </div>
            </div>
        </div>
        <?php elseif ($subscription->info->status === 'active'): ?>
        <div class="row">
            <div class="col-xs-12 sln-notice__wrapper">
                <div class="sln-notice sln-notice--bold sln-notice--subscription-active">
                    <div class="sln-notice--bold__text">
                        <h2><?php _e('<strong>Your subscription is active</strong>', 'salon-booking-system')?></h2>
                     <p><?php echo sprintf(
                            // translators: %s will be replaced by the license expiration time
                             esc_html__('Your license will expire in %s, then will be automatically renewed.', 'salon-booking-system'), $expire) ?></p>
                       <p><?php _e('<strong>If you are happy with us, please submit a positive review.</strong>', 'salon-booking-system')?></p>
                    </div>
                    <a href="https://reviews.capterra.com/new/166320?utm_source=vp&utm_medium=none&utm_campaign=vendor_request_paid" target="_blank" class="sln-notice--plugin_update__action"><?php esc_html_e('Leave a review', 'salon-booking-system')?></a>
                </div>
            </div>
        </div>
        <?php elseif ($subscription->info->status === 'expired'): ?>
        <?php 
        $expire_days = ceil((strtotime($sln_license->get('license_data')->expires) - current_time('timestamp')) / (24 * 3600));
        $expire = sprintf(
                // translators: %s the name of the expire days
                _n('%s day', '%s days', $expire_days, 'salon-booking-system'), $expire_days);
        ?>
        <div class="row">
            <div class="col-xs-12 sln-notice__wrapper">
                <div class="sln-notice sln-notice--bold sln-notice--subscription-cancelled">
                    <div class="sln-notice--bold__text">
                        <h2><?php _e('<strong>Your subscription is expired!</strong>', 'salon-booking-system')?></h2>
                     <p><?php echo sprintf(
                            // translators: %s will be replaced by the license expiration time
                             __('<strong>Attention:</strong> your subscription to <strong>Salon Booking System “Business Plan”</strong> is expired but your license is still active and <strong>it will expire in %s</strong>', 'salon-booking-system'), $expire)?></p>
                     <p><?php _e('<strong>Renew it now and get a discounted price.</strong>', 'salon-booking-system')?></p>
                    </div>
                    <a href="https://www.salonbookingsystem.com/checkout?edd_action=add_to_cart&download_id=64398&edd_options%5Bprice_id%5D=2&discount=GETBACK30&utm_source=plugin-back-end_pro&utm_medium=license-status-notice&utm_campaign=renew-license&utm_id=renew-expired-license" target="_blank" class="sln-notice--plugin_update__action"><?php esc_html_e('Renew for 30% off', 'salon-booking-system')?></a>
                </div>
            </div>
        </div>
        <?php endif;?>
    <?php endif;?>
    <?php endif;?>
</div>
<div class="clearfix"></div>
<style>
    body {overflow: hidden;}
    body.sln-body--scrolldef {
        overflow: auto;
    }
    #sln-pageloading,
    #sln-viewloading,
    #sln-modalloading {
        position: absolute;
        top: 0;
        right: 0;
        left: -20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }
    #sln-pageloading {min-height: calc(100vh - 32px); background-color: rgb(231, 237, 241);}
    @media screen and (max-width: 789px) {
       #sln-pageloading {
            min-height: calc(100vh - 46px);
            top: 0;
            left: -10px;
        } 
    }
    @media screen and (max-width: 660px) {
       #sln-pageloading {
            min-height: calc(100vh - 46px);
            top: 46px;
            left: -10px;
        } 
    }
    #sln-viewloading {bottom: 0; left: 0; justify-content: start; padding-top: 4.55rem; background-color: rgba(231, 237, 241, 0.75);}
    #sln-modalloading {bottom: 0; left: 0; background-color: rgba(231, 237, 241, 0.75);} 
    #sln-pageloading img,
    #sln-viewloading img,
    #sln-modalloading img {
        max-width: 60px;
        animation: swing ease-in-out 1s infinite alternate;
    }
    #sln-pageloading h1,
    #sln-viewloading h1,
    #sln-modalloading h1{
        margin: 1.2em 0 0 0;
        color: #375F99;
        font-weight: 500;
        font-size: 1.5em;
    }
    #sln-pageloading img,
    #sln-pageloading h1,
    #sln-viewloading img,
    #sln-viewloading h1,
    #sln-modalloading img,
    #sln-modalloading h1 {
        transition: all 150ms ease-out;
        transform: scale(1);
    }
    @keyframes swing {
        0% { transform: rotate(3deg); }
        100% { transform: rotate(-3deg); }
    }
</style>
<div id="sln-pageloading" class="sln-pageloading">
    <img 
                    src="<?php echo SLN_PLUGIN_URL . '/img/admin-loading.png'; ?>"
                    alt="img"
                    border="0">
    <h1><?php esc_html_e('We are loading your appointments calendar..', 'salon-booking-system')?></h1>
</div>
<div class="container-fluid sln-calendar--wrapper sln-calendar--wrapper--loading--">
<div class="sln-calendar--wrapper--sub" style="opacity: 0;">

<div class="row">
    <div class="col-xs-12 col-md-6 col-md-push-6 btn-group">
        <?php include 'help.php'?>
    </div>

          <?php do_action('sln.template.calendar.navtabwrapper')?>
</div>
<?php
$useragent=$_SERVER['HTTP_USER_AGENT'];

$is_phone = preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4));
if($is_phone && defined('SLN_VERSION_PAY') && SLN_VERSION_PAY): ?>
    <div id="sln-note-phone-device" class="sln-popup">
        <div class="sln-popup--close"></div>
        <div class="sln-popup-content">
            <p class="sln-popup--text sln-popup--question"><?php esc_html_e('Why don\'t you use our brand-new Web App?', 'salon-booking-system') ?></p>
            <p class="sln-popup--text sln-popup--offer"><?php esc_html_e('It\'s easy and optimised for mobile device.') ?></p>
        </div>
        <a class="sln-popup--button" href="<?php echo site_url('/salon-booking-pwa') ?>"><?php esc_html_e('Open the Web App', 'salon-booking-system') ?></a>
    </div>
<?php endif ?>
<div class="row" style="display: flex">
    <div class="col-xs-12 <?php echo !defined("SLN_VERSION_PAY") ? 'col-md-6' : '' ?>">
    <div class="row">
        <div class="col-xs-12 btn-group nav-tab-wrapper sln-nav-tab-wrapper">
        <div class="sln-btn sln-btn--borderonly sln-btn--large" data-calendar-view="day">
        <button class="" data-calendar-view="day"><?php esc_html_e('Day', 'salon-booking-system')?></button>
        </div>
        <div class="sln-btn sln-btn--borderonly sln-btn--large" data-calendar-view="week">
        <button class="" data-calendar-view="week"><?php esc_html_e('Week', 'salon-booking-system')?></button>
        </div>
        <div class="sln-btn sln-btn--borderonly sln-btn--large" data-calendar-view="month">
        <button class=" active" data-calendar-view="month"><?php esc_html_e('Month', 'salon-booking-system')?></button>
        </div>
        <div class="sln-btn sln-btn--borderonly sln-btn--large" data-calendar-view="year">
        <button class="" data-calendar-view="year"><?php esc_html_e('Year', 'salon-booking-system')?></button>
        </div>
        </div>
    </div>
    </div>
    </div>
    <div class="row">
    <?php if (!defined("SLN_VERSION_PAY") && isset($_COOKIE['sln-notice__dismiss']) && $_COOKIE['sln-notice__dismiss']): ?>
    <div class="col-xs-12 sln-notice__wrapper">
        <div class="sln-notice sln-notice--review">
                <h2><?php esc_html_e('Are you happy with us?', 'salon-booking-system') ?> <?php _e('Share your love for <strong>Salon Booking System</strong> leaving a positive review.', 'salon-booking-system') ?>
                    <?php esc_html_e("Let's grow our community.", 'salon-booking-system') ?>
                    <a href="https://wordpress.org/support/plugin/salon-booking-system/reviews/?filter=5#new-post" target="_blank" class="sln-notice--action">
                        <?php esc_html_e('Submit a review', 'salon-booking-system')?>
                    </a>
                </h2>
                <button type="button" class="sln-notice__dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
        </div>
    </div>
    <?php endif;?>
</div>
<div class="row">
    <div class="col-xs-12 col-md-6 mt-md-5 sln-box-title current-view--title"></div>
    <?php if($plugin->getSettings()->isAttendantsEnabled()): ?>
        <div class="col-xs-12 col-md-6 form-group sln-switch cal-day-filter">
        <div class="pull-right">
            <span class="sln-fake-label"><?php esc_html_e('Assistants view', 'salon-booking-system')?></span>
            <?php
    SLN_Form::fieldCheckbox(
        "sln-calendar-assistants-mode-switch",
        ($checked = get_user_meta(get_current_user_id(), '_assistants_mode', true)) !== '' ? $checked && $checked != 'false' : false
    )
    ?>
            <label for="sln-calendar-assistants-mode-switch" class="sln-switch-btn" data-on="On" data-off="Off"></label>
        </div>
        </div>
    <?php endif; ?>
</div>

<div id="sln-calendar-view" class="row sln-calendar-view sln-box" id="holidays_arr" data-holidays='<?php echo wp_json_encode($holidays); ?>'>
    <div class="col-xs-12 form-inline">
        <div class="row">
            <div class="col-xs-12 col-sm-6 col-sm-push-6">
                <div class="sln-calendar-viewnav btn-group">
                    <div class="sln-btn sln-btn--light sln-btn--large  sln-btn--icon sln-btn--icon--left sln-icon--arrow--left" data-calendar-view="day">
                        <button class="f-row" data-calendar-nav="prev"><?php esc_html_e('Previous', 'salon-booking-system')?></button>
                    </div>
                    <div class="sln-btn sln-btn--light sln-btn--large" data-calendar-view="day">
                        <button class="f-row" data-calendar-nav="today"><?php esc_html_e('Today', 'salon-booking-system')?></button>
                    </div>
                    <div class="sln-btn sln-btn--light sln-btn--large  sln-btn--icon sln-icon--arrow--right" data-calendar-view="day">
                        <button class="f-row f-row--end" data-calendar-nav="next"><?php esc_html_e('Next', 'salon-booking-system')?></button>
                    </div>
                </div>
            </div>
            <div class="col-xs-12 col-sm-4 col-lg-4 col-md-6 col-sm-pull-6">
                <div class="cal-day-search cal-day-filter">
                    <div class="sln-calendar-booking-search-wrapper"><div class="sln-calendar-booking-search-input-wrapper"><?php
SLN_Form::fieldText(
    "sln-calendar-booking-search", false, ['attrs' => [
        'size' => 32,
        'placeholder' => __("Start typing customer name or booking ID", 'salon-booking-system'),
    ],
    ]
)
?></div>
                    <div class="sln-calendar-booking-search-icon">

                    </div>
                    </div>
                    <div id="search-results-list" class="sln-calendar-search-results-list"></div>
                </div>
            </div>
            <div class="col-xs-12 col-sm-2 col-sm-pull-5 col-lg-pull-4">
                <div class="cal-day-filter cal-day-pagination" style="display: none"></div>
            </div>
        </div>
    </div>

        <div class="clearfix"></div>
        <div id="calendar" data-timestart="<?php echo $timestart ?>" data-timeend="<?php echo $timeend ?>" data-timesplit="<?php echo $timesplit ?>"></div>
    <div class="clearfix"></div>

<div id="sln-viewloading" class="sln-viewloading sln-viewloading--inactive">
    <img 
                    src="<?php echo SLN_PLUGIN_URL . '/img/admin-loading.png'; ?>"
                    alt="img"
                    border="0">
    <h1><?php esc_html_e('We are loading your appointments..', 'salon-booking-system')?></h1>
</div>
<!-- row sln-calendar-wrapper // END -->
</div>

<div class="row">
    <div class="form-group col-xs-12 sln-free-locked-slots-block">
        <button class="sln-btn sln-btn--main--tonal sln-btn--big sln-free-locked-slots sln-icon--unlock sln-btn--icon">
            <?php esc_html_e('Free locked slots', 'salon-booking-system')?>
        </button>
    </div>
</div>

<div id="sln-booking-editor-modal" class="modal fade">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div id="sln-modalloading" class="sln-modalloading">
                    <img 
                                    src="<?php echo SLN_PLUGIN_URL . '/img/admin-loading.png'; ?>"
                                    alt="img"
                                    border="0">
                    <h1 class="sln-modalloading__text--saving"><?php esc_html_e('We are processing your request..', 'salon-booking-system')?></h1>
                    <div id="sln-modalloading__inner" class="sln-modalloading__inner">
                        <svg class="animated-check" viewBox="0 0 24 24">
                            <path d="M4.1 12.7L9 17.6 20.3 6.3" fill="none" />
                        </svg>
                        <h1 class="sln-modalloading__text--saved"><?php esc_html_e('Booking Saved', 'salon-booking-system')?></h1>
                    </div>
                </div>
            <div class="modal-body">
               <div class="sln-booking-editor--wrapper">
                    <div class="sln-booking-editor--wrapper--sub" style="opacity: 0">
                        <iframe name="booking_editor" class="booking-editor" width="100%" height="600px" frameborder="0"
                                data-src-template-edit-booking="<?php echo admin_url('/post.php?post=%id&action=edit&mode=sln_editor') ?>"
                                data-src-template-new-booking="<?php echo admin_url('/post-new.php?post_type=sln_booking&date=%date&time=%time&mode=sln_editor') ?>"
                                data-src-template-duplicate-booking="<?php echo admin_url('/post-new.php?post_type=sln_booking&action=duplicate&post=%id&mode=sln_editor') ?>"></iframe>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="booking-last-edit-div pull-left-"></div>
                <div class="pull-right- modal-footer__actions">
                    <button type="button" class="sln-btn sln-btn--nu sln-btn--nu--highemph sln-btn--big" aria-hidden="true" data-action="save-edited-booking"><?php esc_html_e('Save', 'salon-booking-system')?></button>
                    <div class="sln-duplicate-booking <?php echo !defined("SLN_VERSION_PAY")  ? 'sln-duplicate-booking--disabled' : '' ?>">
                        <span class="sln-booking-pro-feature-tooltip">
                        <a href="https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=default_status&utm_medium=free-edition-back-end&utm_campaign=unlock_feature&utm_id=GOPRO" target="_blank">
                            <?php echo esc_html__('Switch to PRO to unlock this feature', 'salon-booking-system') ?>
                        </a>
                        </span>
                        <button type="button" class="sln-btn sln-btn--nu sln-btn--nu--lowhemph sln-btn--big" aria-hidden="true" data-action="duplicate-edited-booking"><?php esc_html_e('Duplicate', 'salon-booking-system')?></button>
                    </div>
                    <button type="button" class="sln-btn sln-btn--nu sln-btn--nu--lowhemph sln-btn--big" aria-hidden="true" data-action="delete-edited-booking"><?php esc_html_e('Delete', 'salon-booking-system')?></button>
                    <button type="button" class="sln-btn sln-btn--nu sln-btn--nu--medhemph sln-btn--big" data-dismiss="modal" aria-hidden="true"><?php esc_html_e('Close', 'salon-booking-system')?></button>
                </div>
                <div class="modal-footer__flyingactions">
        <?php
        if (!defined("SLN_VERSION_PAY")) {
            $tellafriendurl = "https://www.salonbookingsystem.com/refer-a-friend/?utm_source=plugin-back-end_free&utm_medium=refer-a-friend-link&utm_campaign=refer_a_fiend&utm_id=refer-a-friend";
        } else {
            $tellafriendurl = "https://www.salonbookingsystem.com/refer-a-friend/?utm_source=plugin-back-end_pro&utm_medium=refer-a-friend-link&utm_campaign=refer_a_fiend&utm_id=refer-a-friend";
        }
        ?>
                <?php if ( ! in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ): ?>
                    <a class="sln-btn sln-btn--inline--icon" href="<?php echo $tellafriendurl; ?>" target="_blank"><span><?php esc_html_e('Refer a friend and get a 30% discount', 'salon-booking-system')?></span></a>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (current_user_can('export_reservations_csv_sln_calendar')): ?>
    <div class="row">
    <div class="col-xs-12 col-md-9">
        <form action="<?php echo admin_url('admin.php?page=' . SLN_Admin_Tools::PAGE) ?>" method="post">
        <h2><?php esc_html_e('Export reservations into a CSV file', 'salon-booking-system')?></h2>
        <div class="row">
            <?php
$f = $plugin->getSettings()->get('date_format');
$weekStart = $plugin->getSettings()->get('week_start');
$jsFormat = SLN_Enum_DateFormat::getJsFormat($f);
?>
            <div class="form-group col-xs-12 col-md-4 sln_datepicker sln-input--simple">
            <label for="<?php echo SLN_Form::makeID("export[from]") ?>"><?php esc_html_e('from', 'salon-booking-system')?></label>
            <input type="text" class="form-control sln-input" id="<?php echo SLN_Form::makeID("export[from]") ?>" name="export[from]"
                required="required" data-format="<?php echo $jsFormat ?>" data-weekstart="<?php echo $weekStart ?>"
                data-locale="<?php echo SLN_Plugin::getInstance()->getSettings()->getDateLocale() ?>"
        autocomplete="off"
            />
            </div>
            <div class="form-group col-xs-12 col-md-4 sln_datepicker sln-input--simple">
            <label for="<?php echo SLN_Form::makeID("export[to]") ?>"><?php esc_html_e('to', 'salon-booking-system')?></label>
            <input type="text" class="form-control sln-input" id="<?php echo SLN_Form::makeID("export[to]") ?>" name="export[to]"
                required="required" data-format="<?php echo $jsFormat ?>" data-weekstart="<?php echo $weekStart ?>"
                data-locale="<?php echo SLN_Plugin::getInstance()->getSettings()->getDateLocale() ?>"
        autocomplete="off"
            />
            </div>
        </div>
        <div class="row">
            <div class="form-group col-xs-12 col-md-4">
            <button type="submit" id="action" name="sln-tools-export-bookings" value="export"
                class="sln-btn sln-btn--main--tonal sln-btn--big sln-btn--icon sln-icon--file">
                <?php esc_html_e('Export bookings', 'salon-booking-system')?></button>
            </div>
            <?php do_action('sln.tools.export_button'); ?>
        </div>
        </form>
    </div>
    <div class="col-xs-12 col-md-3 pull-right"></div>
    </div>
<?php endif;?>
<div class="row sln-calendar-sidebar">
<div class="col-xs-12 col-md-9">
    <h4><?php esc_html_e('Bookings status legend', 'salon-booking-system')?></h4>
<ul>
<li><span class="pull-left event event-warning"></span><?php echo SLN_Enum_BookingStatus::getLabel(SLN_Enum_BookingStatus::PENDING) ?></li>
<li><span class="pull-left event event-success"></span><?php echo SLN_Enum_BookingStatus::getLabel(SLN_Enum_BookingStatus::PAID) ?> <?php esc_html_e('or', 'salon-booking-system')?> <?php echo SLN_Enum_BookingStatus::getLabel(SLN_Enum_BookingStatus::CONFIRMED) ?></li>
<li><span class="pull-left event event-info"></span><?php echo SLN_Enum_BookingStatus::getLabel(SLN_Enum_BookingStatus::PAY_LATER) ?></li>
<li><span class="pull-left event event-danger"></span><?php echo SLN_Enum_BookingStatus::getLabel(SLN_Enum_BookingStatus::CANCELED) ?></li>
</ul>
<div class="clearfix"></div>
        </div>
    <div class="col-xs-12 col-md-3">
        <?php if ( ! in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ): ?>
        <div class="sln-help-button__block">
        <button class="sln-help-button sln-btn sln-btn--nobkg sln-btn--big sln-btn--icon sln-icon--helpchat sln-btn--icon--al visible-md-inline-block visible-lg-inline-block"><?php esc_html_e('Do you need help ?', 'salon-booking-system')?></button>
        <button class="sln-help-button sln-btn sln-btn--mainmedium sln-btn--small--round sln-btn--icon  sln-icon--helpchat sln-btn--icon--al hidden-md hidden-lg"><?php esc_html_e('Do you need help ?', 'salon-booking-system')?> </button>
    </div>
    <?php endif; ?>
    </div>
</div>
</div>
</div>
