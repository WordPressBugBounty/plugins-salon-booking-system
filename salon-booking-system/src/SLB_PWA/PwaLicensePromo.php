<?php

namespace SLB_PWA;

/**
 * Optional first promo card on the Upcoming tab: upgrade CTAs by edition / license tier.
 */
class PwaLicensePromo {

    /**
     * @return array<string, string>|null { kind: free_pro|basic_business, href: string }
     */
    public static function get_for_pwa() {
        $promo = null;

        $edition_cc  = defined( 'SLN_VERSION_CODECANYON' ) && SLN_VERSION_CODECANYON;
        $edition_pay = ! $edition_cc && defined( 'SLN_VERSION_PAY' ) && SLN_VERSION_PAY;

        if ( $edition_pay && defined( 'SLN_ITEM_SLUG' ) ) {
            $status = (string) get_option( SLN_ITEM_SLUG . '_license_status', '' );
            if ( in_array( $status, array( 'valid', 'site_inactive' ), true ) ) {
                $data     = get_option( SLN_ITEM_SLUG . '_license_data' );
                $price_id = self::read_price_id( $data );
                if ( 1 === $price_id ) {
                    $default_href = defined( 'SLN_STORE_URL' )
                        ? trailingslashit( SLN_STORE_URL ) . 'pricing/'
                        : 'https://www.salonbookingsystem.com/pricing/';
                    $promo = array(
                        'kind' => 'basic_business',
                        'href' => (string) apply_filters( 'sln_pwa_business_plan_upgrade_url', $default_href ),
                    );
                }
            }
        }

        if ( null === $promo ) {
            $is_free_wp = ! $edition_pay
                && ! $edition_cc
                && ! ( defined( 'SLN_SPECIAL_EDITION' ) && SLN_SPECIAL_EDITION );
            if ( $is_free_wp ) {
                $promo = array(
                    'kind' => 'free_pro',
                    'href' => (string) apply_filters(
                        'sln_pwa_pro_pricing_url',
                        'https://www.salonbookingsystem.com/plugin-pricing/'
                    ),
                );
            }
        }

        return apply_filters( 'sln_pwa_license_upgrade_promo', $promo );
    }

    /**
     * @param mixed $data License payload from EDD (object or array).
     */
    private static function read_price_id( $data ) {
        if ( is_object( $data ) && isset( $data->price_id ) ) {
            return (int) $data->price_id;
        }
        if ( is_array( $data ) && isset( $data['price_id'] ) ) {
            return (int) $data['price_id'];
        }
        return 0;
    }
}
