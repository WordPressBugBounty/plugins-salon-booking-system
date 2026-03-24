<?php

namespace SLB_PWA;

/**
 * Builds Upcoming-tab promo slides from EDD store products tagged "featured" (download category).
 */
class FeaturedAddonPromos {

    private const CORE_PLUGIN_PRODUCT_ID = 23261;

    /**
     * Parent / generic EDD category slugs (same idea as views/admin/extensions.php).
     *
     * @var string[]
     */
    private static $parent_category_slugs = array(
        'add-ons',
        'featured',
        'focus-on',
        'subscription',
        'bundles',
        'growth',
        'bundle',
    );

    /**
     * @return array<int, array{title: string, body: string, href: string, category?: string}>
     */
    public static function get_slides() {
        if ( ! apply_filters( 'sln_pwa_featured_addon_promos_enabled', true ) ) {
            return array();
        }

        $products = self::get_edd_products();
        if ( empty( $products ) || ! is_array( $products ) ) {
            return array();
        }

        $slides   = array();
        $limit    = (int) apply_filters( 'sln_pwa_featured_addon_promo_limit', 20 );
        $limit    = max( 1, min( 50, $limit ) );

        foreach ( $products as $product ) {
            if ( count( $slides ) >= $limit ) {
                break;
            }
            if ( ! is_object( $product ) || ! isset( $product->info ) || ! is_object( $product->info ) ) {
                continue;
            }

            $info = $product->info;

            if ( ! isset( $info->id ) || (int) $info->id === self::CORE_PLUGIN_PRODUCT_ID ) {
                continue;
            }

            if ( ! empty( $product->is_all_access_product ) ) {
                continue;
            }

            $cats = isset( $info->category ) && is_array( $info->category ) ? $info->category : array();
            if ( ! self::categories_include_featured( $cats ) ) {
                continue;
            }
            if ( ! self::has_non_parent_category( $cats ) ) {
                continue;
            }

            $title = isset( $info->title ) ? sanitize_text_field( wp_strip_all_tags( (string) $info->title ) ) : '';
            if ( $title === '' ) {
                continue;
            }

            $href = isset( $info->permalink ) ? esc_url_raw( (string) $info->permalink ) : '';
            if ( $href === '' ) {
                continue;
            }

            $raw_content = isset( $info->content ) ? (string) $info->content : '';
            $body        = self::clean_description( $raw_content );
            if ( $body === '' ) {
                $body = $title;
            }

            $slide = array(
                'title' => $title,
                'body'  => $body,
                'href'  => $href,
            );
            $category_label = self::get_display_category( $cats );
            if ( $category_label !== '' ) {
                $slide['category'] = $category_label;
            }
            $slides[] = $slide;
        }

        return apply_filters( 'sln_pwa_featured_addon_promos', $slides );
    }

    /**
     * @param array<int, object> $categories EDD category objects.
     */
    private static function categories_include_featured( array $categories ) {
        foreach ( $categories as $cat ) {
            $slug = isset( $cat->slug ) ? strtolower( (string) $cat->slug ) : '';
            if ( $slug === 'featured' ) {
                return true;
            }
        }
        return false;
    }

    /**
     * True if there is at least one category slug not in the generic parent list (real add-on type).
     *
     * @param array<int, object> $categories EDD category objects.
     */
    private static function has_non_parent_category( array $categories ) {
        foreach ( $categories as $cat ) {
            $slug = isset( $cat->slug ) ? strtolower( (string) $cat->slug ) : '';
            if ( $slug !== '' && ! in_array( $slug, self::$parent_category_slugs, true ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Human-readable add-on category: first EDD term that is not a generic parent (same rules as Extensions card badge).
     *
     * @param array<int, object> $categories EDD category objects.
     */
    private static function get_display_category( array $categories ) {
        if ( empty( $categories ) ) {
            return '';
        }
        foreach ( $categories as $cat ) {
            $slug = isset( $cat->slug ) ? strtolower( (string) $cat->slug ) : '';
            $name = isset( $cat->name ) ? trim( (string) $cat->name ) : '';
            if ( $name === '' || in_array( $slug, self::$parent_category_slugs, true ) ) {
                continue;
            }
            $name = (string) preg_replace_callback(
                '/\b(sms|pos|ovh|api)\b/i',
                function ( $m ) {
                    return strtoupper( $m[0] );
                },
                ucwords( strtolower( $name ) )
            );
            return sanitize_text_field( $name );
        }
        return '';
    }

    private static function clean_description( $content ) {
        $content = trim( wp_strip_all_tags( preg_replace( '/\[\/?[\w\-]+[^\]]*\]/', '', (string) $content ) ) );
        $content = preg_replace( '/\s+/', ' ', $content );
        if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
            if ( mb_strlen( $content ) > 160 ) {
                $content = mb_substr( $content, 0, 160 ) . '...';
            }
        } elseif ( strlen( $content ) > 160 ) {
            $content = substr( $content, 0, 160 ) . '...';
        }
        return $content;
    }

    /**
     * @return array<int, object>
     */
    private static function get_edd_products() {
        global $sln_license;

        if ( isset( $sln_license ) ) {
            $products = $sln_license->getEddProducts();
            return is_array( $products ) ? $products : array();
        }

        if ( defined( 'SLN_ITEM_SLUG' ) && defined( 'SLN_API_KEY' ) && defined( 'SLN_API_TOKEN' ) && defined( 'SLN_STORE_URL' ) ) {
            $transient_key = SLN_ITEM_SLUG . '_products_cache';
            $products      = get_transient( $transient_key );
            if ( false === $products ) {
                $api_params = array(
                    'key'    => SLN_API_KEY,
                    'token'  => SLN_API_TOKEN,
                    'number' => -1,
                );
                $stored_license = get_option( SLN_ITEM_SLUG . '_license_key' );
                if ( $stored_license ) {
                    $api_params['license'] = $stored_license;
                }
                $url      = add_query_arg( $api_params, SLN_STORE_URL . '/edd-api/products' );
                $response = wp_remote_get( $url, array( 'timeout' => 15, 'sslverify' => false ) );
                if ( ! is_wp_error( $response ) ) {
                    $data     = json_decode( wp_remote_retrieve_body( $response ) );
                    $products = isset( $data->products ) ? $data->products : array();
                    if ( ! empty( $products ) ) {
                        set_transient( $transient_key, $products, HOUR_IN_SECONDS );
                    }
                } else {
                    $products = array();
                }
            }
            return is_array( $products ) ? $products : array();
        }

        return array();
    }
}
