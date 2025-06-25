<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var $date DateTime
 * @var $plugin SLN_Plugin
 * @var SLN_Shortcode_Salon_DateStep $step
 */
?>
<?php ob_start();?>
<?php SLN_Form::fieldJSDate('sln[date]', $date, array('inline' => true))?>
<input name="sln[date]" type="hidden" value="<?php echo esc_html(SLN_plugin::getInstance()->format()->date($date)) ?>"/>
<?php $datepicker = ob_get_clean();
ob_start();?>
<div id="sln_timepicker_viewdate"></div>
<?php SLN_Form::fieldJSTime('sln[time]', $date, array('interval' => $plugin->getSettings()->get('interval'), 'inline' => true))?>
<input name="sln[time]" type="hidden" value="<?php echo esc_html(SLN_plugin::getInstance()->format()->time($date)) ?>"/>
<?php $timepicker = ob_get_clean();?>

<div class="col-xs-12 <?php echo '900' == $size ? 'col-md-4' : '' ?> sln-input sln-input--datepicker">
    <?php echo $datepicker ?>
</div>
<div class="col-xs-12 <?php echo '900' == $size ? 'col-md-4' : '' ?> sln-input sln-input--datepicker">
    <?php echo $timepicker ?>
</div>


<input type="hidden" name="sln[customer_timezone]" value="<?php echo $bb->get('customer_timezone') ?>">
<?php if((bool)SLN_Plugin::getInstance()->getSettings()->get('debug') && current_user_can( 'administrator' ) ): ?>
                <div id="sln-debug-div">
                    <div id="sln-debug-sticky-panel" style="width: 100%">
                        <div id="close-debug-table"><?php esc_html_e( 'Close', 'salon-booking-system') ?></div>
                        <input type="hidden" name="sln[debug]" value="1">
                        <div id="disable-debug-table"><?php esc_html_e( 'Disable', 'salon-booking-system' ) ?></div>
                        <nav class="sln-inpage_navbar_inner">
                            <ul id="sln-settings-links" class="nav nav-pills sln-inpage_navbar">
                                <li class="nav-item sln-inpage_navbaritem"><a href=<?php echo get_admin_url(). '/admin.php?page=salon-settings&tab=booking'; ?> class="nav-link nav-link1 sln-inpage_navbarlink" target="_blank"><?php esc_html_e( 'Booking rules', 'salon-booking-system' ) ?></a></li>
                                <li class="nav-item sln-inpage_navbaritem"><a href=<?php echo get_admin_url(). '/edit.php?post_type=sln_attendant' ?> class="nav-link nav-link1 sln-inpage_navbarlink" target="_blank"><?php esc_html_e( 'Assistants', 'salon-booking-system' ) ?></a></li>
                                <li class="nav-item sln-inpage_navbaritem"><a href=<?php echo get_admin_url(). '/edit.php?post_type=sln_service' ?> class="nav-link nav-link1 sln-inpage_navbarlink" target="_blank"><?php esc_html_e( 'Services', 'salon-booking-system') ?></a></li>
                            </ul>
                        </nav>
                        <div class="sln-debug-move"><div class="bar"></div><div class="bar"></div><div class="bar"></div></div>
                    </div>
                    <div id="sln-debug-attendants" class="sln-row">
                        <?php foreach(SLN_Helper_Availability_AdminRuleLog::getInstance()->getAttendats() as $attendant_deb): ?>
                            <div class=sln-debug-time-slote><?php echo $attendant_deb->getName(); ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div id="sln-debug-table">
                        <?php foreach( SLN_Helper_Availability_AdminRuleLog::getInstance()->getLog() as $time => $rules ): ?>
                            <div class="sln-debug-time-slote">
                                <div class="sln-debug-popup">
                                    <?php $failedRule = '';
                                        foreach( $rules as $ruleName => $ruleValue ){
                                        echo '<p class="'. ( (!$ruleValue) ? 'sln-debug--failed"':'"' ).'>'. $ruleName. '</p>';
                                        if( !(bool)$ruleValue && empty( $failedRule ) ){
                                            $failedRule = $ruleName;
                                        }
                                    } ?>
                                </div>
                                <div class="sln-debug-time <?php echo ( !empty($failedRule) ) ? 'sln-debug--failed"' : '"' ; ?>">
                                    <?php echo "<p title=\"$failedRule\">". $time. '</p>'; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="sln-debug-notifications"></div>
                    <?php SLN_Helper_Availability_AdminRuleLog::getInstance()->clear(); ?>
                </div>
            <?php endif; ?>