<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Update_Page
{
    /** @var SLN_Update_Manager */
    private $updater;
    private $pageSlug;
    private $pageName;

    public function __construct(SLN_Update_Manager $updater)
    {
        $this->updater  = $updater;
        $this->pageName = $this->updater->get('name').' License';
        $this->pageSlug = $this->updater->get('slug').'-license';
        add_plugins_page($this->pageName, $this->pageName, 'manage_options', $this->pageSlug, array($this, 'render'));
        add_action('admin_notices', array($this, 'hook_admin_notices'));
    }

    public function hook_admin_notices()
    {
        if (!$this->updater->isValid() && (empty($_GET['page']) || $_GET['page'] != $this->pageSlug)) {
            $licenseUrl = admin_url('/plugins.php?page='.$this->pageSlug);
            ?>
            <div id="sln-setting-error" class="updated error">
                <h3><?php echo esc_html($this->updater->get('name')).esc_html__(' needs a valid license', 'salon-booking-system') ?></h3>
                <p><a href="<?php echo $licenseUrl ?>"><?php _e('<p>Please insert your license key', 'salon-booking-system'); ?></a>
                </p>
            </div>
            <?php
        }
    }

    public function render()
    {
        $this->updater->checkLicense();

        if (isset($_POST['submit']) && isset($_POST['license_key'])) {
            $response = $this->updater->activateLicense($_POST['license_key']);
            if (is_wp_error($response)) {
                ?>
                <div id="sln-setting-error" class="updated error">
                    <p><?php echo 'ERROR: '.$response->get_error_code().' - '.$response->get_error_message() ?></p>
                </div>
                <?php
            } else {
                ?>
                <div id="sln-setting-error" class="updated success">
                    <p><?php echo esc_html__('License updated with success', 'salon-booking-system') ?></p>
                </div>
                <?php
            }
        }
        if (isset($_POST['license_deactivate'])) {
            $response = $this->updater->deactivateLicense();
            if (is_wp_error($response)) {
                ?>
                <div id="sln-setting-error" class="updated error">
                    <p><?php echo $response->get_error_code().' - '.$response->get_error_message() ?></p>
                </div>
                <?php
            } else {

                ?>
                <div id="sln-setting-error" class="updated success">
                    <p><?php echo esc_html__('License deactivated with success', 'salon-booking-system') ?></p>
                </div>
                <?php
            }
        }
        $license = $this->updater->get('license_key');
        $status  = $this->updater->get('license_status');
        $data    = $this->updater->get('license_data');
        ?>
        <div class="wrap">
        <h2><?php echo $this->pageName ?></h2>
        <form method="post" action="?page=<?php echo $this->pageSlug ?>">
            <table class="form-table">
                <tbody>
                <tr valign="top">
                    <th scope="row" valign="top">
                        <?php esc_html_e('License Key', 'salon-booking-system'); ?>
                    </th>
                    <td>
                        <input id="license_key" name="license_key" type="text" class="regular-text"
                               required="required"
                               value="<?php esc_attr_e($license); ?>"/>
                        <?php if (empty($license)): ?>
                            <label class="description" for="license_key"><?php esc_html_e(
                                    'Enter your license key',
                                    'salon-booking-system'
                                ); ?></label>
                        <?php endif ?>
                    </td>
                </tr>
                <?php if ($license) { ?>
                    <tr valign="top">
                        <th scope="row" valign="top">
                            <?php esc_html_e('License State', 'salon-booking-system'); ?>
                        </th>
                        <td class="status">
                            <?php if ($status == 'valid') { ?>
                                <span class="title" style="color:green;position: relative; top: -10px;"><?php esc_html_e('active', 'salon-booking-system'); ?></span>
                                <?php wp_nonce_field('nonce', 'nonce'); ?>&nbsp;
                                <input type="submit" class="button-secondary" name="license_deactivate"
                                       value="<?php esc_html_e('Deactivate License', 'salon-booking-system'); ?>"/>
                                <span class="check_license" style="margin-left: 10px;"><svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M30.6666 5.33335V13.3334M30.6666 13.3334H22.6666M30.6666 13.3334L24.4799 7.52002C23.0469 6.0863 21.2741 5.03896 19.3268 4.47572C17.3796 3.91247 15.3214 3.85169 13.3443 4.29903C11.3672 4.74637 9.53565 5.68726 8.02054 7.03391C6.50543 8.38056 5.35614 10.0891 4.67992 12M1.33325 26.6667V18.6667M1.33325 18.6667H9.33325M1.33325 18.6667L7.51992 24.48C8.95291 25.9137 10.7257 26.9611 12.673 27.5243C14.6202 28.0876 16.6784 28.1484 18.6555 27.701C20.6326 27.2537 22.4642 26.3128 23.9793 24.9661C25.4944 23.6195 26.6437 21.911 27.3199 20" stroke="#3574CB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg> </span>
                            <?php } elseif ($status == 'invalid') { ?>
                                <span style="color:red;"><?php esc_html_e('invalid', 'salon-booking-system'); ?></span>
                                <?php
                            } else { ?>
                                <span style="color:orange;">
                                    <?php esc_html_e('error', 'salon-booking-system'); ?>
                                    <?php echo ' '.$status ?>
                                </span>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" valign="top">
                            <?php esc_html_e('Payment id', 'salon-booking-system'); ?>
                        </th>
                        <td class="payment_id" >
                            <?php echo $data->payment_id ?>
                        </td>
                    </tr>
                    <tr valign="top" >
                        <th scope="row" valign="top">
                            <?php esc_html_e('Customer name', 'salon-booking-system'); ?>
                        </th>
                        <td class="customer_name">
                            <?php echo $data->customer_name ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" valign="top">
                            <?php esc_html_e('Customer email', 'salon-booking-system'); ?>
                        </th>
                        <td  class="customer_email">
                            <?php echo $data->customer_email ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" valign="top">
                            <?php esc_html_e('Expires', 'salon-booking-system'); ?>
                        </th>
                        <td class="expires">
                            <?php echo $data->expires ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
            <?php if ($status != 'valid') { ?>
                <?php submit_button(); ?>
            <?php } ?>
        </form>
        <style>
            @keyframes rotate {
                100% {
                    transform: rotate(360deg);
                }
            }

            .rotate {
                animation: rotate 1s linear infinite;
            }
        </style>
        <script>
            jQuery('.check_license').on('click',function(){
                var svg = jQuery(this).find('svg');
                svg.addClass('rotate');
                var license_key = jQuery('#license_key').val()
                jQuery.ajax({
                    url: ajaxurl, // Provided automatically by WordPress in admin
                    method: 'POST',
                    data: {
                        action: 'sln_refresh_license_status', // This must match the PHP handler,
                        key: license_key
                        // You can add more data if needed
                    },
                    success: function (response) {
                        if(response.data.status){
                            jQuery('.status .title').replaceWith('<span class="title" style="color:green;position: relative; top: -10px;">'+response.data.status_title+'</span>')
                            jQuery('.payment_id').text(response.data.payment_id)
                            jQuery('.customer_name').text(response.data.customer_name)
                            jQuery('.customer_email').text(response.data.customer_email)
                            jQuery('.expires').text(response.data.expires)
                        } else {
                            jQuery('.status .title').replaceWith('<span class="title" style="color:red;position: relative; top: -10px;">'+response.data.status_title+'</span>')
                            jQuery('.payment_id').text(response.data.payment_id)
                            jQuery('.customer_name').text(response.data.customer_name)
                            jQuery('.customer_email').text(response.data.customer_email)
                            jQuery('.expires').text(response.data.expires)
                        }
                        ///jQuery('.status .title').replace('')
                        // Optionally handle UI updates here
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX error:', error);
                    },
                    complete: function (data) {
                        //jQuery('.status .title').replace('')
                        svg.removeClass('rotate');
                    }
                });


            })
        </script>
        <?php
    }
}