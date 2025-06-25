<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
$fbLoginEnabled = $plugin->getSettings()->get('enabled_fb_login');
ob_start(); ?>
<label for="login_name"><?php esc_html_e('E-mail', 'salon-booking-system') ?></label>
<input name="login_name" type="text" class="sln-input sln-input--text"/>
<span class="help-block"><a href="<?php echo wp_lostpassword_url() ?>" class="tec-link"><?php esc_html_e('Forgot password?', 'salon-booking-system') ?></a></span>
<?php
$fieldEmail = ob_get_clean();

ob_start();
?>
<label for="login_password"><?php esc_html_e('Password', 'salon-booking-system') ?></label>
<input name="login_password" type="password" class="sln-input sln-input--text"/>
<?php
$fieldPassword = ob_get_clean();

if ($size == '900') { ?>
    <div class="row">
        <div class="col-xs-12 col-sm-6 col-md-4 sln-input sln-input--simple"><?php echo $fieldEmail?></div>
        <div class="col-xs-12 col-sm-6 col-md-4 sln-input sln-input--simple"><?php echo $fieldPassword?></div>
        <div class="col-xs-12 col-sm-6 col-md-4 pull-right sln-input sln-input--simple">
            <?php echo apply_filters('login_form_middle', '', array()); ?>
        </div>
        <div class="col-xs-12 col-sm-6 col-md-4 pull-right sln-input sln-input--simple">
            <?php echo apply_filters('login_form_bottom', '', array()); ?>
        </div>
        <div class="col-xs-12 col-sm-6 col-md-4 pull-right sln-input sln-input--simple">
            <label for="login_name">&nbsp;</label>
            <div class="sln-btn sln-btn--emphasis sln-btn--medium sln-btn--fullwidth">
                <button type="submit"
                    <?php if ($ajaxEnabled): ?>
                        data-salon-data="<?php echo "sln_step_page={$current}&{$submitName}=next" ?>" data-salon-toggle="next"
                    <?php endif ?>
                        name="<?php echo $submitName ?>" value="next">
                    <?php echo esc_html__('Login', 'salon-booking-system') ?> <i class="glyphicon glyphicon-user"></i>
                </button>
            </div>
            <?php if ($fbLoginEnabled): ?>
                <a href="<?php echo add_query_arg(array('referrer' => urlencode($bookingDetailsPageUrl)), SLN_Helper_FacebookLogin::getRedirectUri()) ?>" class="sln-btn sln-btn--fullwidth sln-btn--nobkg sln-btn--medium sln-btn--fb"><svg class="sln-fblogin--icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0v24h24v-24h-24zm16 7h-1.923c-.616 0-1.077.252-1.077.889v1.111h3l-.239 3h-2.761v8h-3v-8h-2v-3h2v-1.923c0-2.022 1.064-3.077 3.461-3.077h2.539v3z"/></svg><?php esc_html_e('log-in with Facebook', 'salon-booking-system'); ?></a>
            <?php endif ?>
        </div>
    </div>
<?php
// IF SIZE 900 // END
} else if ($size == '600') { ?>
    <div class="row">
        <div class="col-xs-12 col-sm-6 sln-input sln-input--simple"><?php echo $fieldEmail?></div>
        <div class="col-xs-12 col-sm-6 sln-input sln-input--simple"><?php echo $fieldPassword?></div>
    </div>
    <div class="row">
        <div class="col-xs-12"> 
            <div class="sln-box--formactions form-actions">
                <?php if ($fbLoginEnabled): ?>
                    <a href="<?php echo add_query_arg(array('referrer' => urlencode($bookingDetailsPageUrl)), SLN_Helper_FacebookLogin::getRedirectUri()) ?>" class="sln-btn sln-btn--fullwidth sln-btn--nobkg sln-btn--medium sln-btn--fb"><svg class="sln-fblogin--icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0v24h24v-24h-24zm16 7h-1.923c-.616 0-1.077.252-1.077.889v1.111h3l-.239 3h-2.761v8h-3v-8h-2v-3h2v-1.923c0-2.022 1.064-3.077 3.461-3.077h2.539v3z"/></svg><?php esc_html_e('log-in with Facebook', 'salon-booking-system'); ?></a>
                <?php endif ?>           
                <div class="sln-btn sln-btn--emphasis sln-btn--medium sln-btn--fullwidth sln-btn--login">
                    <button type="submit"<?php if ($ajaxEnabled): ?>data-salon-data="<?php echo "sln_step_page={$current}&{$submitName}=next" ?>" data-salon-toggle="next" <?php endif ?> name="<?php echo $submitName ?>" value="next">
                        <?php echo esc_html__('Login','salon-booking-system')?> <i class="glyphicon glyphicon-user"></i>
                    </button>
                </div>
            </div>
        <?php echo apply_filters('login_form_middle', '', array()); ?>
        <?php echo apply_filters('login_form_bottom', '', array()); ?>
        </div>
    </div>
<?php
// IF SIZE 600 // END
} else if ($size == '400') { ?>
    <div class="row">
        <div class="col-xs-12 sln-input sln-input--simple"><?php echo $fieldEmail?></div>
        <div class="col-xs-12 sln-input sln-input--simple"><?php echo $fieldPassword?></div>
        <div class="col-xs-12 sln-input sln-input--simple">
            <div class="row">
                <?php echo apply_filters('login_form_middle', '', array()); ?>
            </div>
        </div>
        <div class="col-xs-12 sln-input sln-input--simple">
            <div class="row">
                <?php echo apply_filters('login_form_bottom', '', array()); ?>
            </div>
        </div>
        <div class="col-xs-12 sln-input sln-input--simple">
            <label for="login_name">&nbsp;</label>
            <div class="sln-btn sln-btn--emphasis sln-btn--medium sln-btn--fullwidth">
            <button type="submit"
                <?php if ($ajaxEnabled): ?>
                    data-salon-data="<?php echo "sln_step_page={$current}&{$submitName}=next" ?>" data-salon-toggle="next"
                <?php endif ?>
                    name="<?php echo $submitName ?>" value="next">
                <?php echo esc_html__('Login','salon-booking-system')?> <i class="glyphicon glyphicon-user"></i>
            </button>
            </div>
            <?php if ($fbLoginEnabled): ?>
                <a href="<?php echo add_query_arg(array('referrer' => urlencode($bookingDetailsPageUrl)), SLN_Helper_FacebookLogin::getRedirectUri()) ?>" class="sln-btn sln-btn--fullwidth sln-btn--nobkg sln-btn--medium sln-btn--fb"><svg class="sln-fblogin--icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0v24h24v-24h-24zm16 7h-1.923c-.616 0-1.077.252-1.077.889v1.111h3l-.239 3h-2.761v8h-3v-8h-2v-3h2v-1.923c0-2.022 1.064-3.077 3.461-3.077h2.539v3z"/></svg><?php esc_html_e('log-in with Facebook', 'salon-booking-system'); ?></a>
            <?php endif ?>
        </div>
    </div>
<?php
// IF SIZE 400 // END
} ?>