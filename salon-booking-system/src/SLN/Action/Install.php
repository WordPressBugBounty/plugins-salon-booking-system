<?php

class SLN_Action_Install
{

    public static function execute($force = false)
    {
        $has_settings = (bool)get_option(SLN_Settings::KEY) ? count(get_option(SLN_Settings::KEY)) > 2 : false;
        if (!$has_settings || $force) {

            $data = require SLN_PLUGIN_DIR . '/_install_data.php';
	        $ids = SLN_Func::savePosts($data['posts']);
            $bookings = self::createDemoBookings($data['bookings'], $ids);
            SLN_Func::savePosts($bookings, true);

            if (isset($ids['thankyou'])) {
                $data['settings']['thankyou'] = $ids['thankyou'];
            }
            if (isset($ids['bookingmyaccount'])) {
                $data['settings']['bookingmyaccount'] = $ids['bookingmyaccount'];
            }
            if (isset($ids['booking'])) {
                $data['settings']['pay'] = $ids['booking'];

            }

            update_option(SLN_Settings::KEY, array_merge(get_option(SLN_Settings::KEY) ? get_option(SLN_Settings::KEY) : array(), $data['settings']));
        }

        new SLN_UserRole_SalonStaff(SLN_Plugin::getInstance(), SLN_Plugin::USER_ROLE_STAFF, __('Salon staff', 'salon-booking-system'));
        new SLN_UserRole_SalonCustomer(SLN_Plugin::getInstance(), SLN_Plugin::USER_ROLE_CUSTOMER, __('Salon customer', 'salon-booking-system'));
    }

    public static function isInstalled() {
	$adminRole = get_role('administrator');
    $has_settings = get_option(SLN_Settings::KEY) ? count(get_option(SLN_Settings::KEY)) > 2 : false;
    
        return $has_settings
                    && (!$adminRole 
                        || (
                            isset($adminRole->capabilities['manage_salon_settings']) 
                            && isset($adminRole->capabilities['create_sln_bookings']) 
                            && isset($adminRole->capabilities['edit_sln_discounts']) 
                            && isset($adminRole->capabilities['create_sln_resources'])
                        )
                    )
                    && get_role(SLN_Plugin::USER_ROLE_STAFF)
                    && get_role(SLN_Plugin::USER_ROLE_CUSTOMER);
    }

    /**
     * Show plugin changes. Code adapted from W3 Total Cache.
     */
    public static function inPluginUpdateMessage( $args ) {

        $transient_name = 'sln_upgrade_notice_' . $args['Version'];

        if ( false === ( $upgrade_notice = get_transient( $transient_name ) ) ) {
            $response = wp_safe_remote_get( 'https://plugins.svn.wordpress.org/salon-booking-system/trunk/readme.txt' );

            if ( ! is_wp_error( $response ) && ! empty( $response['body'] ) ) {
                $upgrade_notice = self::parseUpdateNotice( $response['body'] );
                set_transient( $transient_name, $upgrade_notice, DAY_IN_SECONDS );
            }
        }

        echo wp_kses_post( $upgrade_notice );
    }

    protected static function createDemoBookings($b_data, $pre_created_posts){
        if(count($pre_created_posts) != 8){
            return array();
        }
        for($b_count = 0; $b_count < count($b_data); $b_count++){
            foreach($b_data[$b_count]['meta']['_sln_booking_services'] as $key => $service){
                $b_data[$b_count]['meta']['_sln_booking_services'][$key]['service'] = $pre_created_posts[$service['service']];
                $b_data[$b_count]['meta']['_sln_booking_services'][$key]['attendant'] = $pre_created_posts[$service['attendant']];
            }
            $services = SLN_Wrapper_Booking_Services::build($b_data[$b_count]['meta']['_sln_booking_services'], new SLN_DateTime(
                $b_data[$b_count]['meta']['_sln_booking_date'] . ' '
                . $b_data[$b_count]['meta']['_sln_booking_time']
            ));
            $b_data[$b_count]['meta']['_sln_booking_services'] = $services->toArrayRecursive();
        }
        return $b_data;
    }

    /**
     * Parse update notice from readme file.
     * @param  string $content
     * @return string
     */
    private static function parseUpdateNotice( $content ) {
        // Output Upgrade Notice
        $matches        = null;
        $regexp         = '~==\s*Upgrade Notice\s*==\s*=\s*(.*)\s*=(.*)(=\s*' . preg_quote( SLN_Plugin::getInstance()->getSettings()->getVersion() ) . '\s*=|$)~Uis';
        $upgrade_notice = '';

        if ( preg_match( $regexp, $content, $matches ) ) {
            $version = trim( $matches[1] );
            $notices = (array) preg_split('~[\r\n]+~', trim( $matches[2] ) );

            if ( version_compare( SLN_Plugin::getInstance()->getSettings()->getVersion(), $version, '<' ) ) {

                $upgrade_notice .= '<div class="sln_plugin_upgrade_notice">';

                foreach ( $notices as $index => $line ) {
                    $upgrade_notice .= wp_kses_post( preg_replace( '~\[([^\]]*)\]\(([^\)]*)\)~', '<a href="${2}">${1}</a>', $line ) );
                }

                $upgrade_notice .= '</div> ';
            }
        }

        return wp_kses_post( $upgrade_notice );
    }
}
