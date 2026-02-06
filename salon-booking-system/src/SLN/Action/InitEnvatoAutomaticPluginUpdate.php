<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Action_InitEnvatoAutomaticPluginUpdate
{
    protected $data;
    protected $pageName;
    protected $pageSlug;
    
    /**
     * Default Envato API token for support verification
     * This is used as fallback if user doesn't provide their own token
     */
    const DEFAULT_API_TOKEN = 'xm5rdEMBD3d9wEWNrDvJECxWEWHY121N';
    
    /**
     * CodeCanyon Product ID for Salon Booking System
     */
    const CODECANYON_PRODUCT_ID = '15963435';

    public function __construct() {

	$this->data = array(
	    'name' => SLN_ITEM_NAME,
	    'slug' => SLN_ITEM_SLUG,
	);

	add_action( 'plugins_loaded', array($this, 'init') );

	$this->pageName = $this->data['name'].' Codecanyon Automatic Update';
        $this->pageSlug = $this->data['slug'].'-codecanyon-automatic-update';

	add_action('admin_menu', array($this, 'hook_admin_menu'));
	add_action('admin_notices', array($this, 'support_expiration_notice'));
	add_action('wp_ajax_sln_refresh_support_status', array($this, 'ajax_refresh_support_status'));
    }

    public function init() {

	include SLN_PLUGIN_DIR . '/envato-automatic-plugin-update/envato-plugin-update.php';

	// Use hardcoded product ID for automatic updates
	PresetoPluginUpdateEnvato::instance()->add_item(array(
	    'id'	=> self::CODECANYON_PRODUCT_ID,
	    'basename'	=> SLN_PLUGIN_BASENAME,
	));
    }

    public function hook_admin_menu() {
        add_plugins_page($this->pageName, $this->pageName, 'manage_options', $this->pageSlug, array($this, 'render'));
    }

    /**
     * Display admin notice for support expiration
     */
    public function support_expiration_notice() {
        // Don't show on the license page itself
        if (isset($_GET['page']) && $_GET['page'] === $this->pageSlug) {
            return;
        }

        $purchase_code = $this->get_purchase_code();
        $api_token = $this->get_api_token_with_fallback();
        
        if (empty($purchase_code) || empty($api_token)) {
            return;
        }

        $support_data = $this->check_support_expiration($purchase_code, $api_token);
        if (!$support_data || is_wp_error($support_data)) {
            return;
        }

        $is_expired = $support_data['supported_until'] < time();
        $days_remaining = ceil(($support_data['supported_until'] - time()) / DAY_IN_SECONDS);

        // Show notice if expired or expiring soon (< 30 days)
        if ($is_expired) {
            ?>
            <div class="notice notice-error is-dismissible">
                <h3><?php esc_html_e('Support Expired', 'salon-booking-system'); ?></h3>
                <p>
                    <?php esc_html_e('Your CodeCanyon support for', 'salon-booking-system'); ?> 
                    <strong><?php echo esc_html($this->data['name']); ?></strong> 
                    <?php esc_html_e('has expired on', 'salon-booking-system'); ?> 
                    <strong><?php echo esc_html(date_i18n(get_option('date_format'), $support_data['supported_until'])); ?></strong>.
                </p>
                <p>
                    <?php esc_html_e('You can still receive updates, but for bug fixes and priority support, please renew your support.', 'salon-booking-system'); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url($support_data['renew_url']); ?>" target="_blank" class="button button-primary">
                        <?php esc_html_e('Renew Support', 'salon-booking-system'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('plugins.php?page=' . $this->pageSlug)); ?>" class="button button-secondary">
                        <?php esc_html_e('View Support Details', 'salon-booking-system'); ?>
                    </a>
                </p>
            </div>
            <?php
        } elseif ($days_remaining <= 30 && $days_remaining > 0) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <h3><?php esc_html_e('Support Expiring Soon', 'salon-booking-system'); ?></h3>
                <p>
                    <?php esc_html_e('Your CodeCanyon support for', 'salon-booking-system'); ?> 
                    <strong><?php echo esc_html($this->data['name']); ?></strong> 
                    <?php 
                    printf(
                        esc_html__('will expire in %d days on', 'salon-booking-system'),
                        $days_remaining
                    );
                    ?> 
                    <strong><?php echo esc_html(date_i18n(get_option('date_format'), $support_data['supported_until'])); ?></strong>.
                </p>
                <p>
                    <a href="<?php echo esc_url($support_data['renew_url']); ?>" target="_blank" class="button button-primary">
                        <?php esc_html_e('Renew Support', 'salon-booking-system'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('plugins.php?page=' . $this->pageSlug)); ?>" class="button button-secondary">
                        <?php esc_html_e('View Support Details', 'salon-booking-system'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * AJAX handler to refresh support status
     */
    public function ajax_refresh_support_status() {
        check_ajax_referer('sln_refresh_support_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'salon-booking-system'));
            return;
        }

        $purchase_code = $this->get_purchase_code();
        $api_token = $this->get_api_token_with_fallback();
        
        if (empty($purchase_code)) {
            wp_send_json_error(__('No purchase code found', 'salon-booking-system'));
            return;
        }
        
        if (empty($api_token)) {
            wp_send_json_error(__('No Envato API token available', 'salon-booking-system'));
            return;
        }

        // Clear cache
        $cache_key = 'sln_envato_support_' . md5($purchase_code);
        delete_transient($cache_key);

        // Fetch fresh data
        $support_data = $this->check_support_expiration($purchase_code, $api_token);

        if (is_wp_error($support_data)) {
            wp_send_json_error($support_data->get_error_message());
            return;
        }

        wp_send_json_success($support_data);
    }

    public function render() {

        // Handle form submission
        if (isset($_POST['submit'])) {
            check_admin_referer('sln_codecanyon_settings', 'sln_codecanyon_nonce');

            $updated = false;

            if (isset($_POST['purchase_code'])) {
                $purchase_code = sanitize_text_field($_POST['purchase_code']);
                $this->save_purchase_code($purchase_code);
                
                // Clear support cache when purchase code changes
                if (!empty($purchase_code)) {
                    $cache_key = 'sln_envato_support_' . md5($purchase_code);
                    delete_transient($cache_key);
                }
                
                $updated = true;
            }
            
            if (isset($_POST['envato_api_token'])) {
                $api_token = sanitize_text_field($_POST['envato_api_token']);
                $this->save_api_token($api_token);
                
                // Clear support cache when API token changes
                $purchase_code = $this->get_purchase_code();
                if (!empty($purchase_code)) {
                    $cache_key = 'sln_envato_support_' . md5($purchase_code);
                    delete_transient($cache_key);
                }
                
                $updated = true;
            }

            if ($updated) {
                ?>
                <div id="sln-setting-error" class="updated success notice is-dismissible">
                    <p><?php echo esc_html__('Settings saved successfully', 'salon-booking-system') ?></p>
                </div>
                <?php
            }
        }

        $purchase_code = $this->get_purchase_code();
        $api_token = $this->get_api_token();
        $using_default_token = empty($api_token);

	?>

	<div class="wrap">
	<h2><?php echo esc_html($this->pageName); ?></h2>
	
	<p><?php esc_html_e('Configure your CodeCanyon settings and check your support status.', 'salon-booking-system'); ?></p>
	
        <form method="post" action="?page=<?php echo esc_attr($this->pageSlug); ?>">
            <?php wp_nonce_field('sln_codecanyon_settings', 'sln_codecanyon_nonce'); ?>
            
            <table class="form-table">
                <tbody>
		    <tr valign="top">
			<th scope="row" valign="top">
			    <?php esc_html_e('Purchase Code', 'salon-booking-system'); ?>
			    <span style="color: red;">*</span>
			</th>
			<td>
			    <input id="purchase_code" name="purchase_code" type="text" class="regular-text"
				   value="<?php echo esc_attr($purchase_code); ?>" placeholder="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX"/>
			    <p class="description">
			        <?php esc_html_e('Enter your Envato purchase code to verify support status and enable automatic updates.', 'salon-booking-system'); ?>
			        <br>
			        <a href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code-" target="_blank">
			            <?php esc_html_e('Where can I find my purchase code?', 'salon-booking-system'); ?>
			        </a>
			    </p>
			</td>
		    </tr>
		    
		    <?php /* API token field hidden - using built-in default token */ ?>
		    <input type="hidden" id="envato_api_token" name="envato_api_token" value="<?php echo esc_attr($api_token); ?>"/>
                </tbody>
            </table>
	    <?php submit_button(); ?>
        </form>
        
        <?php
        // Display support status if purchase code is provided
        if (!empty($purchase_code)) {
            $api_token_to_use = $this->get_api_token_with_fallback();
            $this->render_support_status($purchase_code, $api_token_to_use);
        } else {
            ?>
            <div class="notice notice-info" style="margin-top: 30px; padding: 20px;">
                <h3><?php esc_html_e('Enter Your Purchase Code', 'salon-booking-system'); ?></h3>
                <p>
                    <?php esc_html_e('Please enter your CodeCanyon purchase code above to:', 'salon-booking-system'); ?>
                </p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php esc_html_e('Verify your support status', 'salon-booking-system'); ?></li>
                    <li><?php esc_html_e('Enable automatic plugin updates', 'salon-booking-system'); ?></li>
                    <li><?php esc_html_e('Get support renewal reminders', 'salon-booking-system'); ?></li>
                </ul>
            </div>
            <?php
        }
        ?>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#sln-refresh-support-status').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var statusContainer = $('#sln-support-status-container');
                
                button.prop('disabled', true).text('<?php esc_html_e('Refreshing...', 'salon-booking-system'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sln_refresh_support_status',
                        nonce: '<?php echo wp_create_nonce('sln_refresh_support_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('<?php esc_html_e('Error:', 'salon-booking-system'); ?> ' + response.data);
                            button.prop('disabled', false).text('<?php esc_html_e('Refresh Support Status', 'salon-booking-system'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php esc_html_e('Failed to refresh support status', 'salon-booking-system'); ?>');
                        button.prop('disabled', false).text('<?php esc_html_e('Refresh Support Status', 'salon-booking-system'); ?>');
                    }
                });
            });
        });
        </script>
        
	</div>
        <?php
    }

    /**
     * Render support status section
     */
    protected function render_support_status($purchase_code, $api_token) {
        $support_data = $this->check_support_expiration($purchase_code, $api_token);
        
        if (is_wp_error($support_data)) {
            ?>
            <div id="sln-support-status-container" class="notice notice-error" style="margin-top: 30px; padding: 20px;">
                <h3><?php esc_html_e('Support Verification Error', 'salon-booking-system'); ?></h3>
                <p>
                    <strong><?php esc_html_e('Error:', 'salon-booking-system'); ?></strong> 
                    <?php echo esc_html($support_data->get_error_message()); ?>
                </p>
                <p class="description">
                    <?php esc_html_e('Please make sure:', 'salon-booking-system'); ?>
                </p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php esc_html_e('Your purchase code is correct', 'salon-booking-system'); ?></li>
                    <li><?php esc_html_e('You have an active internet connection', 'salon-booking-system'); ?></li>
                </ul>
                <p>
                    <button type="button" id="sln-refresh-support-status" class="button button-secondary">
                        <?php esc_html_e('Refresh Support Status', 'salon-booking-system'); ?>
                    </button>
                </p>
            </div>
            <?php
            return;
        }

        $is_expired = $support_data['supported_until'] < time();
        $days_remaining = ceil(($support_data['supported_until'] - time()) / DAY_IN_SECONDS);
        $border_color = $is_expired ? '#dc3545' : ($days_remaining <= 30 ? '#ffc107' : '#28a745');
        $bg_color = $is_expired ? '#f8d7da' : ($days_remaining <= 30 ? '#fff3cd' : '#d4edda');
        $text_color = $is_expired ? '#721c24' : ($days_remaining <= 30 ? '#856404' : '#155724');
        
        ?>
        <div id="sln-support-status-container" style="margin-top: 30px; padding: 20px; border-left: 4px solid <?php echo esc_attr($border_color); ?>; background: <?php echo esc_attr($bg_color); ?>;">
            <h3 style="margin-top: 0;"><?php esc_html_e('Support Status', 'salon-booking-system'); ?></h3>
            
            <?php if ($is_expired): ?>
                <p style="color: <?php echo esc_attr($text_color); ?>; font-size: 16px; margin: 15px 0;">
                    <strong>⚠️ <?php esc_html_e('Your support has expired', 'salon-booking-system'); ?></strong>
                </p>
                <table class="form-table" style="background: white; padding: 10px; border-radius: 4px; margin: 15px 0;">
                    <tr>
                        <th scope="row" style="padding-left: 10px;"><?php esc_html_e('Expired on:', 'salon-booking-system'); ?></th>
                        <td><strong><?php echo esc_html(date_i18n(get_option('date_format'), $support_data['supported_until'])); ?></strong></td>
                    </tr>
                    <tr>
                        <th scope="row" style="padding-left: 10px;"><?php esc_html_e('Buyer:', 'salon-booking-system'); ?></th>
                        <td><?php echo esc_html($support_data['buyer']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" style="padding-left: 10px;"><?php esc_html_e('Purchase Date:', 'salon-booking-system'); ?></th>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), $support_data['purchase_date'])); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" style="padding-left: 10px;"><?php esc_html_e('License Type:', 'salon-booking-system'); ?></th>
                        <td><?php echo esc_html(ucfirst($support_data['license'])); ?></td>
                    </tr>
                </table>
                <p>
                    <?php esc_html_e('You can still receive plugin updates, but for bug fixes and priority support, please renew your support.', 'salon-booking-system'); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url($support_data['renew_url']); ?>" target="_blank" class="button button-primary">
                        <?php esc_html_e('Renew Support Now', 'salon-booking-system'); ?>
                    </a>
                    <button type="button" id="sln-refresh-support-status" class="button button-secondary" style="margin-left: 10px;">
                        <?php esc_html_e('Refresh Support Status', 'salon-booking-system'); ?>
                    </button>
                </p>
            <?php else: ?>
                <p style="color: <?php echo esc_attr($text_color); ?>; font-size: 16px; margin: 15px 0;">
                    <strong>✅ <?php esc_html_e('Your support is active', 'salon-booking-system'); ?></strong>
                </p>
                <table class="form-table" style="background: white; padding: 10px; border-radius: 4px; margin: 15px 0;">
                    <tr>
                        <th scope="row" style="padding-left: 10px;"><?php esc_html_e('Support until:', 'salon-booking-system'); ?></th>
                        <td><strong><?php echo esc_html(date_i18n(get_option('date_format'), $support_data['supported_until'])); ?></strong></td>
                    </tr>
                    <tr>
                        <th scope="row" style="padding-left: 10px;"><?php esc_html_e('Days remaining:', 'salon-booking-system'); ?></th>
                        <td><strong><?php echo esc_html($days_remaining); ?></strong> <?php esc_html_e('days', 'salon-booking-system'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" style="padding-left: 10px;"><?php esc_html_e('Buyer:', 'salon-booking-system'); ?></th>
                        <td><?php echo esc_html($support_data['buyer']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" style="padding-left: 10px;"><?php esc_html_e('Purchase Date:', 'salon-booking-system'); ?></th>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), $support_data['purchase_date'])); ?></td>
                    </tr>
                    <tr>
                        <th scope="row" style="padding-left: 10px;"><?php esc_html_e('License Type:', 'salon-booking-system'); ?></th>
                        <td><?php echo esc_html(ucfirst($support_data['license'])); ?></td>
                    </tr>
                </table>
                
                <?php if ($days_remaining <= 30): ?>
                    <div style="color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px; border-left: 3px solid #ffc107; margin: 15px 0;">
                        <strong>⚠️ <?php esc_html_e('Support Expiring Soon', 'salon-booking-system'); ?></strong>
                        <p style="margin: 5px 0 0 0;">
                            <?php esc_html_e('Your support will expire soon. Consider renewing to continue receiving priority support and updates.', 'salon-booking-system'); ?>
                        </p>
                    </div>
                    <p>
                        <a href="<?php echo esc_url($support_data['renew_url']); ?>" target="_blank" class="button button-primary">
                            <?php esc_html_e('Renew Support Early', 'salon-booking-system'); ?>
                        </a>
                        <button type="button" id="sln-refresh-support-status" class="button button-secondary" style="margin-left: 10px;">
                            <?php esc_html_e('Refresh Support Status', 'salon-booking-system'); ?>
                        </button>
                    </p>
                <?php else: ?>
                    <p>
                        <button type="button" id="sln-refresh-support-status" class="button button-secondary">
                            <?php esc_html_e('Refresh Support Status', 'salon-booking-system'); ?>
                        </button>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
            
            <p style="margin-top: 20px; padding-top: 15px; border-top: 1px solid rgba(0,0,0,0.1); font-size: 12px; color: #666;">
                <strong><?php esc_html_e('Purchase Code:', 'salon-booking-system'); ?></strong> 
                <code><?php echo esc_html($this->mask_purchase_code($purchase_code)); ?></code>
                <br>
                <em><?php esc_html_e('Support data is cached for 24 hours. Click "Refresh" to update immediately.', 'salon-booking-system'); ?></em>
            </p>
        </div>
        <?php
    }

    /**
     * Check support expiration via Envato API (Direct API call)
     * 
     * @param string $purchase_code The Envato purchase code
     * @param string $api_token The Envato API Personal Token
     * @return array|WP_Error Support data or error
     */
    protected function check_support_expiration($purchase_code, $api_token) {
        if (empty($purchase_code)) {
            return new WP_Error('empty_code', __('Purchase code is required', 'salon-booking-system'));
        }
        
        if (empty($api_token)) {
            return new WP_Error('empty_token', __('Envato API token is required', 'salon-booking-system'));
        }
        
        // Get cached support data (cache for 24 hours)
        $cache_key = 'sln_envato_support_' . md5($purchase_code);
        $cached = get_transient($cache_key);
        
        if ($cached !== false && !empty($cached)) {
            return $cached;
        }
        
        // Make direct API call to Envato
        $api_url = 'https://api.envato.com/v3/market/author/sale';
        
        $response = wp_remote_get(
            add_query_arg('code', $purchase_code, $api_url),
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_token,
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
                ),
                'timeout' => 15,
            )
        );
        
        // Check for errors
        if (is_wp_error($response)) {
            return new WP_Error(
                'api_error',
                sprintf(
                    __('API connection error: %s', 'salon-booking-system'),
                    $response->get_error_message()
                )
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Handle HTTP errors
        if ($response_code !== 200) {
            $error_data = json_decode($response_body);
            $error_message = isset($error_data->error) ? $error_data->error : __('Unknown API error', 'salon-booking-system');
            
            if ($response_code === 404) {
                return new WP_Error('invalid_code', __('Invalid purchase code. Please check and try again.', 'salon-booking-system'));
            } elseif ($response_code === 401) {
                return new WP_Error('invalid_token', __('Invalid API token or insufficient permissions.', 'salon-booking-system'));
            } else {
                return new WP_Error(
                    'api_error',
                    sprintf(__('API Error (HTTP %d): %s', 'salon-booking-system'), $response_code, $error_message)
                );
            }
        }
        
        // Parse response
        $purchase_data = json_decode($response_body);
        
        if (!$purchase_data || !isset($purchase_data->sold_at)) {
            return new WP_Error('invalid_response', __('Invalid API response. Please try again.', 'salon-booking-system'));
        }
        
        // Extract support data
        $support_data = array(
            'buyer' => isset($purchase_data->buyer) ? $purchase_data->buyer : '',
            'purchase_date' => isset($purchase_data->sold_at) ? strtotime($purchase_data->sold_at) : 0,
            'supported_until' => isset($purchase_data->supported_until) ? strtotime($purchase_data->supported_until) : 0,
            'item_id' => isset($purchase_data->item->id) ? $purchase_data->item->id : '',
            'item_name' => isset($purchase_data->item->name) ? $purchase_data->item->name : '',
            'license' => isset($purchase_data->license) ? $purchase_data->license : 'regular',
            'support_amount' => isset($purchase_data->support_amount) ? $purchase_data->support_amount : '0.00',
        );
        
        // Build correct renewal URL - Envato format
        // Format: https://codecanyon.net/item/{item_id}/support
        $support_data['renew_url'] = sprintf(
            'https://codecanyon.net/item/%s/support',
            self::CODECANYON_PRODUCT_ID
        );
        
        // Cache for 24 hours
        set_transient($cache_key, $support_data, DAY_IN_SECONDS);
        
        return $support_data;
    }

    /**
     * Mask purchase code for display (show only first/last 4 chars)
     * 
     * @param string $code Purchase code
     * @return string Masked purchase code
     */
    protected function mask_purchase_code($code) {
        if (empty($code)) {
            return '';
        }
        
        if (strlen($code) <= 12) {
            return $code;
        }
        
        return substr($code, 0, 4) . '-****-****-' . substr($code, -4);
    }

    /**
     * Get CodeCanyon product ID (auto-discovered from purchase verification)
     * 
     * @return string Product ID
     */
    protected function get_codecanyon_product_id() {
	return get_option($this->data['slug'].'_codecanyon_product_id');
    }

    /**
     * Save CodeCanyon product ID (auto-saved when purchase is verified)
     * 
     * @param string $codecanyon_product_id Product ID
     */
    protected function save_codecanyon_product_id($codecanyon_product_id) {
	update_option($this->data['slug'].'_codecanyon_product_id', sanitize_text_field($codecanyon_product_id));
    }

    /**
     * Get purchase code
     * 
     * @return string Purchase code
     */
    protected function get_purchase_code() {
        return get_option($this->data['slug'].'_purchase_code');
    }

    /**
     * Save purchase code
     * 
     * @param string $purchase_code Purchase code
     */
    protected function save_purchase_code($purchase_code) {
        update_option($this->data['slug'].'_purchase_code', sanitize_text_field($purchase_code));
    }
    
    /**
     * Get Envato API token (user-provided)
     * 
     * @return string API token
     */
    protected function get_api_token() {
        return get_option($this->data['slug'].'_envato_api_token');
    }
    
    /**
     * Get Envato API token with fallback to default
     * 
     * @return string API token (user's or default)
     */
    protected function get_api_token_with_fallback() {
        $user_token = $this->get_api_token();
        return !empty($user_token) ? $user_token : self::DEFAULT_API_TOKEN;
    }
    
    /**
     * Save Envato API token
     * 
     * @param string $api_token API token
     */
    protected function save_api_token($api_token) {
        update_option($this->data['slug'].'_envato_api_token', sanitize_text_field($api_token));
    }

}
