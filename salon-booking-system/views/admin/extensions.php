<?php // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped

global $sln_license;

if ( isset( $sln_license ) ) {
    $products = $sln_license->getEddProducts();
} elseif ( defined( 'SLN_ITEM_SLUG' ) && defined( 'SLN_API_KEY' ) && defined( 'SLN_API_TOKEN' ) && defined( 'SLN_STORE_URL' ) ) {
    // License system not initialised (e.g. combined CC+PAY build) — query the store directly.
    $transient_key = SLN_ITEM_SLUG . '_products_cache';
    $products      = get_transient( $transient_key );
    if ( false === $products ) {
        $url      = add_query_arg(
            [ 'key' => SLN_API_KEY, 'token' => SLN_API_TOKEN, 'number' => -1 ],
            SLN_STORE_URL . '/edd-api/products'
        );
        $response = wp_remote_get( $url, [ 'timeout' => 15, 'sslverify' => false ] );
        if ( ! is_wp_error( $response ) ) {
            $data     = json_decode( wp_remote_retrieve_body( $response ) );
            $products = isset( $data->products ) ? $data->products : [];
            if ( ! empty( $products ) ) {
                set_transient( $transient_key, $products, HOUR_IN_SECONDS );
            }
        } else {
            $products = [];
        }
    }
    if ( ! is_array( $products ) ) {
        $products = [];
    }
} else {
    $products = [];
}

$salonPluginID = 23261;

// ── Edition detection ──────────────────────────────────────────────────────────
// CC:   SLN_VERSION_CODECANYON defined (build keeps both PAY + CC constants)
// PAY:  SLN_VERSION_PAY only, no CC constant — includes SE sub-edition
// FREE: neither PAY nor CC defined
$edition_is_cc   = defined( 'SLN_VERSION_CODECANYON' );
$edition_is_pay  = ! $edition_is_cc && defined( 'SLN_VERSION_PAY' ) && SLN_VERSION_PAY;
// FREE = ! $edition_is_cc && ! $edition_is_pay

// ── License validity ───────────────────────────────────────────────────────────
// Only PAY/SE use an all-access subscription plan. CC sells add-ons individually;
// FREE requires an upgrade. Neither CC nor FREE should ever read a stale stored status.
if ( $edition_is_pay ) {
    if ( isset( $sln_license ) ) {
        $license_valid = $sln_license->isValid();
    } else {
        // Fallback: license manager not initialised yet — read stored status directly.
        $license_valid = defined( 'SLN_ITEM_SLUG' )
            && get_option( SLN_ITEM_SLUG . '_license_status' ) === 'valid';
    }
} else {
    // CC / FREE: no subscription plan, never treat as "included".
    $license_valid = false;
}

// EDD category slug → filter tab key (uses real EDD taxonomy slugs as primary keys)
$ext_category_map = [
    // Real EDD taxonomy slugs
    'payment-platform'   => 'payments',
    'payments-and-trust' => 'payments',
    'sms-provider'       => 'notifications',
    'email-marketing'    => 'notifications',
    'mailchimp'          => 'integrations',
    'georeferencing'     => 'integrations',
    'multi-locations'    => 'integrations',
    'productivity'       => 'customization',
    // Legacy / extra slugs
    'payments'           => 'payments',
    'payment'            => 'payments',
    'stripe'             => 'payments',
    'paypal'             => 'payments',
    'woocommerce'        => 'integrations',
    'calendar'           => 'calendar',
    'google-calendar'    => 'calendar',
    'apple-calendar'     => 'calendar',
    'outlook'            => 'calendar',
    'notifications'      => 'notifications',
    'sms'                => 'notifications',
    'email'              => 'notifications',
    'whatsapp'           => 'notifications',
    'slack'              => 'notifications',
    'integrations'       => 'integrations',
    'zapier'             => 'integrations',
    'customization'      => 'customization',
    'branding'           => 'customization',
    'reports'            => 'reports',
    'analytics'          => 'reports',
];

if ( ! function_exists( 'ext_resolve_category' ) ) {
    function ext_resolve_category( $product_categories, $slug_map ) {
        if ( empty( $product_categories ) || ! is_array( $product_categories ) ) {
            return '';
        }
        foreach ( $product_categories as $cat ) {
            $slug = isset( $cat->slug ) ? strtolower( $cat->slug ) : '';
            if ( isset( $slug_map[ $slug ] ) ) {
                return $slug_map[ $slug ];
            }
        }
        return '';
    }
}

/**
 * Returns the human-readable category label to display on the card badge.
 * Uses the most specific EDD taxonomy term, skipping generic parent ones.
 */
if ( ! function_exists( 'ext_get_display_category' ) ) {
    function ext_get_display_category( $product_categories ) {
        $generic = [ 'add-ons', 'featured', 'focus-on', 'subscription', 'bundles', 'growth', 'bundle' ];
        if ( empty( $product_categories ) || ! is_array( $product_categories ) ) {
            return '';
        }
        foreach ( $product_categories as $cat ) {
            $slug = isset( $cat->slug ) ? strtolower( $cat->slug ) : '';
            $name = isset( $cat->name ) ? trim( $cat->name ) : '';
            if ( $name && ! in_array( $slug, $generic, true ) ) {
                // Normalise capitalisation: "Sms provider" → "SMS Provider"
                $name = preg_replace_callback( '/\b(sms|pos|ovh|api)\b/i', fn($m) => strtoupper($m[0]), ucwords( strtolower( $name ) ) );
                return $name;
            }
        }
        return '';
    }
}

/**
 * Returns a semantic SVG icon based on the product title keywords,
 * falling back to a category-level icon when no specific match is found.
 */
if ( ! function_exists( 'ext_get_semantic_icon' ) ) {
    function ext_get_semantic_icon( $title, $category_key = '' ) {
        $S  = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"';
        $t  = strtolower( $title );

        // ── Specific product / brand matches ──────────────────────────────

        // Walk-In Totem / Kiosk — tablet with check-in arrow
        if ( strpos( $t, 'walk-in' ) !== false || strpos( $t, 'totem' ) !== false || strpos( $t, 'kiosk' ) !== false ) {
            return "<svg $S><rect x=\"5\" y=\"2\" width=\"14\" height=\"20\" rx=\"2\"/><line x1=\"9\" y1=\"18\" x2=\"15\" y2=\"18\"/><polyline points=\"8 10 11 13 16 8\"/></svg>";
        }

        // Migrator — two arrows rotating (migration / sync)
        if ( strpos( $t, 'migrat' ) !== false ) {
            return "<svg $S><polyline points=\"17 1 21 5 17 9\"/><path d=\"M3 11V9a4 4 0 0 1 4-4h14\"/><polyline points=\"7 23 3 19 7 15\"/><path d=\"M21 13v2a4 4 0 0 1-4 4H3\"/></svg>";
        }

        // Multi-Shop / Multi-Location — two buildings
        if ( strpos( $t, 'multi' ) !== false && ( strpos( $t, 'shop' ) !== false || strpos( $t, 'location' ) !== false ) ) {
            return "<svg $S><path d=\"M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z\"/><polyline points=\"9 22 9 12 15 12 15 22\"/></svg>";
        }

        // WooCommerce — shopping cart
        if ( strpos( $t, 'woocommerce' ) !== false ) {
            return "<svg $S><circle cx=\"9\" cy=\"21\" r=\"1\"/><circle cx=\"20\" cy=\"21\" r=\"1\"/><path d=\"M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6\"/></svg>";
        }

        // SOAP Notes / Client notes — clipboard
        if ( strpos( $t, 'soap' ) !== false || strpos( $t, 'note' ) !== false ) {
            return "<svg $S><path d=\"M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2\"/><rect x=\"8\" y=\"2\" width=\"8\" height=\"4\" rx=\"1\" ry=\"1\"/><line x1=\"8\" y1=\"13\" x2=\"16\" y2=\"13\"/><line x1=\"8\" y1=\"17\" x2=\"13\" y2=\"17\"/></svg>";
        }

        // Geo Referencing — map pin with dot
        if ( strpos( $t, 'geo' ) !== false || strpos( $t, 'referenc' ) !== false ) {
            return "<svg $S><path d=\"M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z\"/><circle cx=\"12\" cy=\"10\" r=\"3\"/></svg>";
        }

        // Packages / Bundles / Membership — gift box
        if ( strpos( $t, 'package' ) !== false || strpos( $t, 'bundle' ) !== false || strpos( $t, 'membership' ) !== false ) {
            return "<svg $S><polyline points=\"20 12 20 22 4 22 4 12\"/><rect x=\"2\" y=\"7\" width=\"20\" height=\"5\"/><line x1=\"12\" y1=\"22\" x2=\"12\" y2=\"7\"/><path d=\"M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z\"/><path d=\"M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z\"/></svg>";
        }

        // Communicator — envelope (email marketing)
        if ( strpos( $t, 'communicator' ) !== false ) {
            return "<svg $S><path d=\"M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z\"/><polyline points=\"22,6 12,13 2,6\"/></svg>";
        }

        // Mailchimp — envelope with star (email marketing)
        if ( strpos( $t, 'mailchimp' ) !== false ) {
            return "<svg $S><path d=\"M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z\"/><polyline points=\"22 6 12 13 2 6\"/><polygon points=\"12 2 13.5 6.5 18 6.5 14.25 9.25 15.75 13.75 12 11 8.25 13.75 9.75 9.25 6 6.5 10.5 6.5\" fill=\"currentColor\" stroke=\"none\" opacity=\"0.5\" transform=\"scale(0.45) translate(14.5, 1)\"/></svg>";
        }

        // SMS providers — speech bubble with signal dots
        if ( strpos( $t, 'sms' ) !== false ) {
            return "<svg $S><path d=\"M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z\"/><line x1=\"8\" y1=\"10\" x2=\"8\" y2=\"10\"/><line x1=\"12\" y1=\"10\" x2=\"12\" y2=\"10\"/><line x1=\"16\" y1=\"10\" x2=\"16\" y2=\"10\"/></svg>";
        }

        // Square payment — square with $ inside
        if ( strpos( $t, 'square' ) !== false && strpos( $t, 'payment' ) !== false ) {
            return "<svg $S><rect x=\"3\" y=\"3\" width=\"18\" height=\"18\" rx=\"3\"/><line x1=\"12\" y1=\"8\" x2=\"12\" y2=\"16\"/><path d=\"M15 10a3 2 0 1 0-6 0 3 2 0 0 0 6 0\"/><path d=\"M15 14a3 2 0 1 1-6 0 3 2 0 0 1 6 0\"/></svg>";
        }

        // Swish — mobile with contactless arrow
        if ( strpos( $t, 'swish' ) !== false ) {
            return "<svg $S><rect x=\"5\" y=\"2\" width=\"14\" height=\"20\" rx=\"2\"/><line x1=\"9\" y1=\"18\" x2=\"15\" y2=\"18\"/><path d=\"M14 8c1 .9 1.5 2 1.5 3.5S15 14.1 14 15\"/><path d=\"M11 9.5c.5.5.8 1.2.8 2s-.3 1.5-.8 2\"/></svg>";
        }

        // Wallet / Viva Wallet — wallet / card holder
        if ( strpos( $t, 'wallet' ) !== false ) {
            return "<svg $S><path d=\"M21 12V7H5a2 2 0 0 1 0-4h14v4\"/><path d=\"M3 5v14a2 2 0 0 0 2 2h16v-5\"/><path d=\"M18 12a2 2 0 0 0 0 4h4v-4z\"/></svg>";
        }

        // Branded / Branding / Basic version — palette
        if ( strpos( $t, 'brand' ) !== false || strpos( $t, 'basic' ) !== false ) {
            return "<svg $S><circle cx=\"13.5\" cy=\"6.5\" r=\".5\" fill=\"currentColor\"/><circle cx=\"17.5\" cy=\"10.5\" r=\".5\" fill=\"currentColor\"/><circle cx=\"8.5\" cy=\"7.5\" r=\".5\" fill=\"currentColor\"/><circle cx=\"6.5\" cy=\"12.5\" r=\".5\" fill=\"currentColor\"/><path d=\"M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z\"/></svg>";
        }

        // Custom feature / Development — wrench + code
        if ( strpos( $t, 'custom feature' ) !== false || strpos( $t, 'development' ) !== false ) {
            return "<svg $S><path d=\"M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z\"/></svg>";
        }

        // Generic payment — all remaining payment-platform products
        if ( strpos( $t, 'payment' ) !== false || strpos( $t, 'pay' ) !== false || $category_key === 'payments' ) {
            return "<svg $S><rect x=\"1\" y=\"4\" width=\"22\" height=\"16\" rx=\"2\" ry=\"2\"/><line x1=\"1\" y1=\"10\" x2=\"23\" y2=\"10\"/><circle cx=\"5.5\" cy=\"15\" r=\"1\" fill=\"currentColor\" stroke=\"none\"/><line x1=\"8\" y1=\"15\" x2=\"12\" y2=\"15\"/></svg>";
        }

        // ── Category-level fallbacks ────────────────────────────────────
        $category_icons = [
            'notifications' => "<svg $S><path d=\"M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9\"/><path d=\"M13.73 21a2 2 0 0 1-3.46 0\"/></svg>",
            'calendar'      => "<svg $S><rect x=\"3\" y=\"4\" width=\"18\" height=\"18\" rx=\"2\" ry=\"2\"/><line x1=\"16\" y1=\"2\" x2=\"16\" y2=\"6\"/><line x1=\"8\" y1=\"2\" x2=\"8\" y2=\"6\"/><line x1=\"3\" y1=\"10\" x2=\"21\" y2=\"10\"/></svg>",
            'integrations'  => "<svg $S><circle cx=\"18\" cy=\"5\" r=\"3\"/><circle cx=\"6\" cy=\"12\" r=\"3\"/><circle cx=\"18\" cy=\"19\" r=\"3\"/><line x1=\"8.59\" y1=\"13.51\" x2=\"15.42\" y2=\"17.49\"/><line x1=\"15.41\" y1=\"6.51\" x2=\"8.59\" y2=\"10.49\"/></svg>",
            'customization' => "<svg $S><circle cx=\"13.5\" cy=\"6.5\" r=\".5\" fill=\"currentColor\"/><circle cx=\"17.5\" cy=\"10.5\" r=\".5\" fill=\"currentColor\"/><circle cx=\"8.5\" cy=\"7.5\" r=\".5\" fill=\"currentColor\"/><circle cx=\"6.5\" cy=\"12.5\" r=\".5\" fill=\"currentColor\"/><path d=\"M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z\"/></svg>",
            'reports'       => "<svg $S><line x1=\"18\" y1=\"20\" x2=\"18\" y2=\"10\"/><line x1=\"12\" y1=\"20\" x2=\"12\" y2=\"4\"/><line x1=\"6\" y1=\"20\" x2=\"6\" y2=\"14\"/><line x1=\"2\" y1=\"20\" x2=\"22\" y2=\"20\"/></svg>",
        ];
        if ( isset( $category_icons[ $category_key ] ) ) {
            return $category_icons[ $category_key ];
        }

        // Default tag icon
        return "<svg $S><path d=\"M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z\"/><line x1=\"7\" y1=\"7\" x2=\"7.01\" y2=\"7\"/></svg>";
    }
}

if ( ! function_exists( 'ext_clean_description' ) ) {
    function ext_clean_description( $content ) {
        $content = trim( wp_strip_all_tags( preg_replace( '/\[\/?[\w\-]+[^\]]*\]/', '', $content ) ) );
        $content = preg_replace( '/\s+/', ' ', $content );
        if ( mb_strlen( $content ) > 120 ) {
            $content = mb_substr( $content, 0, 120 ) . '...';
        }
        return $content;
    }
}

// Top-level / parent taxonomy slugs — products tagged ONLY with these are NOT add-ons.
$ext_parent_slugs = [ 'add-ons', 'featured', 'focus-on', 'subscription', 'bundles', 'growth', 'bundle' ];

// Populated while building cards: slug → display label (for filter tabs)
$ext_filter_terms = [];

// Build cards array
$ext_cards = [];
foreach ( $products as $product ) {
    $info = $product->info;
    if ( $info->id == $salonPluginID ) continue;
    if ( $product->is_all_access_product ) continue;

    $is_excluded = (bool) $product->is_excluded_from_all_access;
    $files       = isset( $product->files ) ? (array) $product->files : [];

    if ( ! $is_excluded && empty( $files ) ) continue;

    // Only show products that have at least one taxonomy term that is a
    // child of the "add-ons" parent — i.e. a specific non-generic category.
    $cats_check = isset( $info->category ) && is_array( $info->category ) ? $info->category : [];
    $has_child_term = false;
    foreach ( $cats_check as $cat ) {
        $slug = isset( $cat->slug ) ? strtolower( $cat->slug ) : '';
        if ( $slug && ! in_array( $slug, $ext_parent_slugs, true ) ) {
            $has_child_term = true;
            break;
        }
    }
    if ( ! $has_child_term ) continue;

    // Determine install/activation state
    $is_installed = false;
    $is_activated = false;
    $has_update   = false;
    foreach ( $files as $file ) {
        $res = SLN_Action_Ajax_InstallPlugin::get_plugin( $file->name );
        if ( $res['success'] ) {
            $is_installed = true;
            if ( $res['check_version'] ) {
                $is_activated = $res['is_activate'];
            } else {
                $has_update = true;
            }
        }
    }

    // Determine button state and availability label, edition-aware.
    $btn_href = $info->permalink; // default CTA link — overridden below when needed

    if ( $is_installed ) {
        // Already on disk: show install-state controls regardless of edition.
        if ( $has_update ) {
            $btn_state  = 'update';
            $btn_label  = __( 'Update', 'salon-booking-system' );
            $btn_action = 'install';
        } elseif ( $is_activated ) {
            $btn_state  = 'active';
            $btn_label  = __( 'Active', 'salon-booking-system' );
            $btn_action = 'deactivate';
        } else {
            $btn_state  = 'activate';
            $btn_label  = __( 'Activate', 'salon-booking-system' );
            $btn_action = 'activate';
        }
        $availability      = 'included';
        // "Included in your plan" only makes sense for PAY/SE; use neutral label otherwise.
        $availability_text = $edition_is_pay
            ? __( 'Included in your plan', 'salon-booking-system' )
            : __( 'Installed', 'salon-booking-system' );

    } elseif ( $edition_is_cc ) {
        // CodeCanyon: no subscription plan — each add-on is a separate purchase.
        $btn_state         = 'purchase';
        $btn_label         = __( 'Purchase', 'salon-booking-system' );
        $btn_action        = '';
        $availability      = 'not-included';
        $availability_text = __( 'Available as add-on', 'salon-booking-system' );

    } elseif ( ! $edition_is_pay ) {
        // FREE edition: user must upgrade to a Pro plan.
        $btn_state         = 'purchase';
        $btn_label         = __( 'Upgrade to Pro', 'salon-booking-system' );
        $btn_action        = '';
        $btn_href          = defined( 'SLN_STORE_URL' ) ? SLN_STORE_URL . '/pricing/' : $info->permalink;
        $availability      = 'not-included';
        $availability_text = __( 'Requires Pro plan', 'salon-booking-system' );

    } elseif ( $is_excluded ) {
        // PAY/SE plan but this specific add-on is sold separately.
        $btn_state         = 'purchase';
        $btn_label         = __( 'Purchase', 'salon-booking-system' );
        $btn_action        = '';
        $availability      = 'not-included';
        $availability_text = __( 'Not included in your plan', 'salon-booking-system' );

    } elseif ( $license_valid ) {
        // PAY/SE with a valid active license — add-on is part of the plan.
        $btn_state         = 'install';
        $btn_label         = __( 'Install', 'salon-booking-system' );
        $btn_action        = 'install';
        $availability      = 'included';
        $availability_text = __( 'Included in your plan', 'salon-booking-system' );

    } else {
        // PAY/SE but license is not active — prompt to activate it.
        $btn_state         = 'purchase';
        $btn_label         = __( 'Activate License', 'salon-booking-system' );
        $btn_action        = '';
        $btn_href          = admin_url( 'admin.php?page=salon' );
        $availability      = 'not-included';
        $availability_text = __( 'Requires active license', 'salon-booking-system' );
    }

    $cats = isset( $info->category ) && is_array( $info->category ) ? $info->category : [];

    // Collect non-generic child slugs for this product and register them as filter terms
    $child_slugs = [];
    foreach ( $cats as $cat ) {
        $slug = isset( $cat->slug ) ? strtolower( $cat->slug ) : '';
        $name = isset( $cat->name ) ? trim( $cat->name ) : '';
        if ( $slug && ! in_array( $slug, $ext_parent_slugs, true ) ) {
            $child_slugs[] = $slug;
            if ( ! isset( $ext_filter_terms[ $slug ] ) && $name ) {
                $norm = preg_replace_callback( '/\b(sms|pos|ovh|api)\b/i', fn( $m ) => strtoupper( $m[0] ), ucwords( strtolower( $name ) ) );
                $ext_filter_terms[ $slug ] = $norm;
            }
        }
    }

    // Space-separated slugs for data-category; abstract key kept for icon fallback only
    $category_key   = implode( ' ', $child_slugs );
    $icon_key       = ext_resolve_category( $cats, $ext_category_map );
    $category_label = ext_get_display_category( $cats );
    $version        = $product->licensing->version ?? '';

    $ext_cards[] = [
        'id'                => (int) $info->id,
        'title'             => $info->title,
        'description'       => ext_clean_description( $info->content ),
        'version'           => $version,
        'permalink'         => $info->permalink,
        'category_key'      => $category_key,
        'category_label'    => $category_label,
        'icon_svg'          => ext_get_semantic_icon( $info->title, $icon_key ),
        'btn_state'         => $btn_state,
        'btn_label'         => $btn_label,
        'btn_action'        => $btn_action,
        'btn_href'          => $btn_href,
        'availability'      => $availability,
        'availability_text' => $availability_text,
    ];
}

$ext_total = count( $ext_cards );

// Sort filter terms alphabetically by display label
asort( $ext_filter_terms );

$check_svg  = '<svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 6L5 9L10 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$loader_svg = '<svg class="ext-loader-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>';
?>

<div class="wrap ext-page-wrap">

    <?php if ( ! $license_valid ) : ?>
    <div class="ext-banner" id="ext-banner">
        <div class="ext-banner__inner">
            <div class="ext-banner__top">
                <div class="ext-banner__heading">
                    <span class="ext-banner__crown">
                        <img src="<?php echo esc_url( SLN_PLUGIN_URL . '/img/crown.svg' ); ?>" width="24" height="24" alt="" aria-hidden="true">
                    </span>
                    <h2><?php esc_html_e( 'Upgrade to unlock premium extensions', 'salon-booking-system' ); ?></h2>
                </div>
                <button class="ext-banner__close" id="ext-banner-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'salon-booking-system' ); ?>">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </button>
            </div>
            <p class="ext-banner__desc"><?php esc_html_e( 'Get access to premium payment gateways, SMS notifications, advanced reports, and more with our professional plans.', 'salon-booking-system' ); ?></p>
            <div class="ext-banner__plans">
                <div class="ext-banner__plan">
                    <div class="ext-banner__plan-top">
                        <div class="ext-banner__plan-info">
                            <div class="ext-banner__plan-name"><?php esc_html_e( 'Basic', 'salon-booking-system' ); ?></div>
                            <div class="ext-banner__plan-desc"><?php esc_html_e( 'Perfect for small salons and individual stylists', 'salon-booking-system' ); ?></div>
                        </div>
                        <div class="ext-banner__plan-price">
                            <div class="ext-banner__plan-amount">$59</div>
                            <div class="ext-banner__plan-period"><?php esc_html_e( '/year', 'salon-booking-system' ); ?></div>
                        </div>
                    </div>
                    <a href="<?php echo esc_url( SLN_STORE_URL . '/pricing/' ); ?>" target="_blank" class="ext-banner__plan-btn">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 8l3.5 3.5L13 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php esc_html_e( 'Select Basic', 'salon-booking-system' ); ?>
                    </a>
                </div>
                <div class="ext-banner__plan">
                    <div class="ext-banner__plan-top">
                        <div class="ext-banner__plan-info">
                            <div class="ext-banner__plan-name"><?php esc_html_e( 'Business Plan', 'salon-booking-system' ); ?></div>
                            <div class="ext-banner__plan-desc"><?php esc_html_e( 'Advanced features for growing salon businesses', 'salon-booking-system' ); ?></div>
                        </div>
                        <div class="ext-banner__plan-price">
                            <div class="ext-banner__plan-amount">$129</div>
                            <div class="ext-banner__plan-period"><?php esc_html_e( '/year', 'salon-booking-system' ); ?></div>
                        </div>
                    </div>
                    <a href="<?php echo esc_url( SLN_STORE_URL . '/pricing/' ); ?>" target="_blank" class="ext-banner__plan-btn">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 8l3.5 3.5L13 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php esc_html_e( 'Select Business Plan', 'salon-booking-system' ); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="ext-page-header">
        <h1 class="ext-page-header__title"><?php esc_html_e( 'Extensions', 'salon-booking-system' ); ?></h1>
        <div class="ext-page-header__meta">
            <p class="ext-page-header__subtitle"><?php esc_html_e( 'Enhance your booking system with powerful add-ons', 'salon-booking-system' ); ?></p>
            <span class="ext-page-header__count"><?php echo $ext_total . ' ' . esc_html__( 'extensions', 'salon-booking-system' ); ?></span>
        </div>
    </div>

    <div class="ext-filter-bar">
        <div class="ext-search">
            <span class="ext-search__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </span>
            <input type="text" id="ext-search" class="ext-search__input" placeholder="<?php esc_attr_e( 'Search extensions…', 'salon-booking-system' ); ?>" autocomplete="off">
        </div>
        <div class="ext-filter-tabs">
            <button class="ext-filter-tab ext-filter-tab--active" data-filter="all"><?php esc_html_e( 'All', 'salon-booking-system' ); ?></button>
            <?php foreach ( $ext_filter_terms as $slug => $label ) : ?>
            <button class="ext-filter-tab" data-filter="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ( $ext_total > 0 ) : ?>
    <div class="ext-grid" id="ext-grid">
        <?php foreach ( $ext_cards as $card ) : ?>
        <div class="ext-card"
             data-id="<?php echo esc_attr( $card['id'] ); ?>"
             data-action="<?php echo esc_attr( $card['btn_action'] ); ?>"
             data-category="<?php echo esc_attr( $card['category_key'] ); ?>"
             data-title="<?php echo esc_attr( strtolower( $card['title'] ) ); ?>">

            <div class="ext-card__top">
                <div class="ext-card__icon"><?php echo $card['icon_svg']; ?></div>
                <?php if ( $card['category_label'] ) : ?>
                <span class="ext-card__badge ext-card__badge--<?php echo esc_attr( $card['category_key'] ); ?>">
                    <?php echo esc_html( $card['category_label'] ); ?>
                </span>
                <?php endif; ?>
            </div>

            <div class="ext-card__body">
                <h3 class="ext-card__name"><?php echo esc_html( $card['title'] ); ?></h3>
                <?php if ( $card['version'] ) : ?>
                <p class="ext-card__version">Version <?php echo esc_html( $card['version'] ); ?></p>
                <?php endif; ?>
                <p class="ext-card__desc"><?php echo esc_html( $card['description'] ); ?></p>
            </div>

            <div class="ext-card__footer">
                <div class="ext-error"></div>
                <p class="ext-card__availability ext-card__availability--<?php echo esc_attr( $card['availability'] ); ?>">
                    <?php echo esc_html( $card['availability_text'] ); ?>
                </p>
                <div class="ext-card__action">
                    <?php if ( $card['btn_state'] === 'active' ) : ?>
                        <span class="ext-btn ext-btn--active">
                            <?php echo $check_svg; ?>
                            <?php echo esc_html( $card['btn_label'] ); ?>
                        </span>
                    <?php elseif ( $card['btn_state'] === 'purchase' ) : ?>
                        <a href="<?php echo esc_url( $card['btn_href'] ); ?>" target="_blank" rel="noopener" class="ext-btn ext-btn--filled">
                            <?php echo esc_html( $card['btn_label'] ); ?>
                        </a>
                    <?php else : ?>
                        <a href="#" class="ext-btn ext-btn--outline extensions-button blue">
                            <span class="label"><?php echo esc_html( $card['btn_label'] ); ?></span>
                            <span class="loader" style="display:none;"><?php echo $loader_svg; ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        </div>
        <?php endforeach; ?>
    </div>
    <?php else : ?>
    <p class="ext-empty"><?php esc_html_e( 'No extensions found or an error occurred while fetching data.', 'salon-booking-system' ); ?></p>
    <?php endif; ?>

</div>
