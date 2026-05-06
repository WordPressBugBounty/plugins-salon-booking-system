<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

class SLB_Discount_PostType_Discount extends SLN_PostType_Abstract
{

    public function init()
    {
        parent::init();

        if (is_admin()) {
            add_action('manage_'.$this->getPostType().'_posts_custom_column', array($this, 'manage_column'), 10, 2);
            add_filter('manage_'.$this->getPostType().'_posts_columns', array($this, 'manage_columns'));
            add_filter('manage_edit-'.$this->getPostType().'_sortable_columns', array($this, 'custom_columns_sort'));
            add_action('admin_head-post-new.php', array($this, 'posttype_admin_css'));
            add_action('admin_head-post.php', array($this, 'posttype_admin_css'));
            add_action('admin_head', array($this, 'admin_list_coupon_column_styles'));
            add_action('admin_enqueue_scripts', array($this, 'load_scripts'));
            add_action('wp_ajax_sln_discount', array($this, 'ajax'));
            add_filter('post_row_actions', array($this, 'post_row_actions'), 10, 2);
            add_filter('posts_join', array($this, 'search_join'), 10, 2);
            add_filter('posts_search', array($this, 'search_where'), 10, 2);
            add_filter('posts_groupby', array($this, 'search_groupby'), 10, 2);
            add_filter('posts_where', array($this, 'restrict_admin_list_statuses'), 10, 2);
        }
    }

    public function custom_columns_sort( $columns ) {
        $custom = array(
            'title' => 'title',
        );
        return $custom;
    }

    public function load_scripts()
    {
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-sortable');
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->id === 'edit-' . $this->getPostType()) {
            wp_enqueue_script(
                'sln-discount-list',
                SLN_PLUGIN_URL . '/js/discount/admin-discount-list.js',
                array('jquery'),
                SLN_Action_InitScripts::ASSETS_VERSION,
                true
            );
            wp_localize_script(
                'sln-discount-list',
                'slnDiscountListL10n',
                array(
                    'copied'     => __('Copied!', 'salon-booking-system'),
                    'copyFailed' => __('Could not copy to clipboard.', 'salon-booking-system'),
                )
            );
        }
    }

    /**
     * Compact layout for coupon code + copy control on the discounts list table.
     */
    public function admin_list_coupon_column_styles()
    {
        if (!function_exists('get_current_screen')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'edit-' . $this->getPostType()) {
            return;
        }
        echo '<style>
            .column-discount_coupon_code{width:14%;}
            .sln-discount-coupon-cell{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap;vertical-align:middle;}
            .sln-discount-coupon-code{font-size:13px;background:rgba(0,0,0,.04);padding:2px 6px;border-radius:2px;}
            .sln-copy-coupon-code{
                margin:0;padding:4px;border:none;background:transparent;box-shadow:none;border-radius:4px;
                line-height:0;color:#50575e;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;
            }
            .sln-copy-coupon-code:hover,.sln-copy-coupon-code:focus{color:#2271b1;background:rgba(0,0,0,.04);box-shadow:none;}
            .sln-copy-coupon-code:focus{outline:1px solid #2271b1;outline-offset:1px;}
            .sln-copy-coupon-code .sln-copy-coupon-icon{display:block;}
            .sln-copy-coupon-code.sln-is-copied{color:#00a32a;}
        </style>';
    }

    public function ajax()
    {
        if(!current_user_can('edit_sln_discounts')){
            wp_die('<p>' . esc_html__('Sory, you not allowed to ajax.'). '</p>', 403);
        }
        $method = sanitize_text_field( wp_unslash($_POST['method'])  );
        if (isset($method)) {
            $method = 'ajax_'.$method;
            if (method_exists($this, $method)) {
                $this->$method();
            }
        }
        die();
    }

    public function post_row_actions($actions, $post) {
        if ($post->post_type === $this->getPostType()) {
            unset($actions['inline hide-if-no-js']);
        }
        return $actions;
    }

    public function manage_columns($columns)
    {
        $new_columns = array(
            'cb' => $columns['cb'],
            'title' => $columns['title'],
            'discount_coupon_code' => __('Coupon code', 'salon-booking-system'),
            'discount_type' => __('Type', 'salon-booking-system'),
            'discount_amount' => __('Amount', 'salon-booking-system'),
            'active' => __('Active', 'salon-booking-system'),
            'discount_usages' => __('Usage (used / limit)', 'salon-booking-system'),
        );

        return $new_columns;
    }

    public function manage_column($column, $post_id)
    {
        $obj = SLB_Discount_Plugin::getInstance()->createDiscount($post_id);
        switch ($column) {
            case 'discount_coupon_code':
                if ($obj->getDiscountType() !== SLB_Discount_Enum_DiscountType::DISCOUNT_CODE) {
                    echo '&mdash;';
                    break;
                }
                $coupon_code = $obj->getCouponCode();
                if ($coupon_code === '') {
                    echo '&mdash;';
                    break;
                }
                $copy_label = __('Copy to clipboard', 'salon-booking-system');
                // Two overlapping sheets (standard “copy” glyph), stroke-only — no box border on the control.
                $copy_icon = '<svg class="sln-copy-coupon-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="7" width="13" height="13" rx="2" ry="2"/><rect x="8" y="2" width="13" height="13" rx="2" ry="2"/></svg>';
                echo '<span class="sln-discount-coupon-cell">';
                echo '<code class="sln-discount-coupon-code">' . esc_html($coupon_code) . '</code>';
                printf(
                    '<button type="button" class="sln-copy-coupon-code" data-code="%1$s" title="%2$s" data-default-title="%2$s" aria-label="%2$s">%3$s</button>',
                    esc_attr($coupon_code),
                    esc_attr($copy_label),
                    $copy_icon
                );
                echo '</span>';
                break;
            case 'discount_type':
                $type = SLB_Discount_Enum_DiscountType::getLabel($obj->getDiscountType());
                echo esc_html($type);
                break;
            case 'discount_amount':
                $amount = $obj->getAmountString();
                echo esc_html($amount);
                break;
            case 'active':
                $now   = new SLN_DateTime(current_time('mysql'));
                $nowTs = $now->getTimestamp();
                $startTs = $obj->getStartsAt() ? $obj->getStartsAt()->getTimestamp() : null;
                $endTs   = $obj->getEndsAt()   ? $obj->getEndsAt()->getTimestamp()   : null;
                $isActive = ($startTs === null || $nowTs >= $startTs) && ($endTs === null || $nowTs <= $endTs);
                $status = $isActive ? __('Yes', 'salon-booking-system') : __('No', 'salon-booking-system');
                echo esc_html($status);
                break;
            case 'discount_usages':
                $used  = (int) $obj->getTotalUsagesNumber();
                $limit = $obj->isUnlimitedTotalUsages() ? '&infin;' : (int) $obj->getTotalUsagesLimit();
                echo esc_html($used) . ' / ' . ( is_int($limit) ? esc_html($limit) : $limit );
                break;
        }
    }

    public function enter_title_here($title, $post)
    {

        if ($this->getPostType() === $post->post_type) {
            $title = __('Enter discount name', 'salon-booking-system');
        }

        return $title;
    }

    public function updated_messages($messages)
    {
        $messages[$this->getPostType()] = array(
            0 => '', // Unused. Messages start at index 1.
            1 => sprintf(
                __('Discount updated.', 'salon-booking-system')
            ),
            2 => '',
            3 => '',
            4 => __('Discount updated.', 'salon-booking-system'),
            5 => isset($_GET['revision']) ? sprintf(
                // translators: %s will be replaced by the revision title
                __('Discount restored to revision from %s', 'salon-booking-system'),
                wp_post_revision_title((int)$_GET['revision'], false)
            ) : false,
            6 => sprintf(
                __('Discount published.', 'salon-booking-system')
            ),
            7 => __('Discount saved.', 'salon-booking-system'),
            8 => sprintf(
                __('Discount submitted.', 'salon-booking-system')
            ),
            9 => '',
            10 => sprintf(
                __('Discount draft updated.', 'salon-booking-system')
            ),
        );


        return $messages;
    }

    protected function getPostTypeArgs()
    {
        return array(
            'public' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => true,
            'show_in_menu' => 'salon',
            'rewrite' => false,
            'supports' => array(
                'title',
                'excerpt',
                'thumbnail',
            ),
            'labels' => array(
                'name' => __('Discounts', 'salon-booking-system'),
                'singular_name' => __('Discount', 'salon-booking-system'),
                'menu_name' => __('Salon', 'salon-booking-system'),
                'name_admin_bar' => __('Salon Discount', 'salon-booking-system'),
                'all_items' => __('Discounts', 'salon-booking-system'),
                'add_new' => __('Add Discount', 'salon-booking-system'),
                'add_new_item' => __('Add New Discount', 'salon-booking-system'),
                'edit_item' => __('Edit Discount', 'salon-booking-system'),
                'new_item' => __('New Discount', 'salon-booking-system'),
                'view_item' => __('View Discount', 'salon-booking-system'),
                'search_items' => __('Search Discounts', 'salon-booking-system'),
                'not_found' => __('No discounts found', 'salon-booking-system'),
                'not_found_in_trash' => __('No discounts found in trash', 'salon-booking-system'),
                'archive_title' => __('Discounts Archive', 'salon-booking-system'),
            ),
            'capability_type' => array($this->getPostType(), $this->getPostType().'s'),
            'map_meta_cap' => true
        );
    }

    public function posttype_admin_css()
    {
        global $post_type;
        if ($post_type == $this->getPostType()) {
            $this->getPlugin()->loadView('metabox/_discount_head');
        }
    }

    /**
     * Restrict the discount admin list to standard WordPress post statuses.
     *
     * Booking custom statuses (confirmed, canceled, etc.) are registered
     * globally with show_in_admin_all_list=true. Without this fix, using
     * post_status=all in the discount list URL causes WordPress to include
     * those statuses in the query, surfacing discounts that were accidentally
     * assigned a booking status alongside revisions with status=inherit.
     */
    public function restrict_admin_list_statuses( $where, $query ) {
        global $pagenow, $wpdb;

        trigger_error( '[SLB_Discount] restrict_admin_list_statuses fired. pagenow=' . $pagenow . ' post_type_get=' . $query->get('post_type') . ' GET_post_type=' . ( isset($_GET['post_type']) ? $_GET['post_type'] : 'N/A' ), E_USER_NOTICE );

        if (
            ! is_admin()
            || $pagenow !== 'edit.php'
            || ! isset( $_GET['post_type'] )
            || sanitize_key( $_GET['post_type'] ) !== $this->getPostType()
            || $query->get( 'post_type' ) !== $this->getPostType()
        ) {
            return $where;
        }

        $requested_status = isset( $_GET['post_status'] ) ? sanitize_key( $_GET['post_status'] ) : '';

        // Only intercept the catch-all "all" request.
        // Explicit single-status filters (e.g. ?post_status=draft) are left untouched.
        if ( $requested_status !== 'all' && $requested_status !== '' ) {
            return $where;
        }

        $statuses      = array( 'publish', 'draft', 'pending', 'private', 'future' );
        $placeholders  = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
        $where        .= $wpdb->prepare(
            " AND {$wpdb->posts}.post_status IN ($placeholders)",
            $statuses
        );

        return $where;
    }

    public function search_join($join, $query)
    {
        global $wpdb;

        if ($query->is_main_query() && $query->get('post_type') === $this->getPostType() && $query->get('s')) {
            $join .= ' LEFT JOIN ' . $wpdb->postmeta . ' AS slb_dc_meta ON ' . $wpdb->posts . '.ID = slb_dc_meta.post_id'
                . ' AND slb_dc_meta.meta_key = \'_' . $this->getPostType() . '_code\'';
        }

        return $join;
    }

    public function search_where($search, $query)
    {
        global $wpdb;

        if ($query->is_main_query() && $query->get('post_type') === $this->getPostType() && $query->get('s') && ! empty($search)) {
            $term     = '%' . $wpdb->esc_like($query->get('s')) . '%';
            // Keep the meta search inside the existing WordPress search group.
            // Appending a raw "OR ..." at the end can break SQL precedence and
            // unexpectedly include rows outside the post_type/status constraints.
            $search_expression = preg_replace('/^\s*AND\s*/', '', $search);
            $search            = $wpdb->prepare(
                " AND ( {$search_expression} OR slb_dc_meta.meta_value LIKE %s )",
                $term
            );
        }

        return $search;
    }

    public function search_groupby($groupby, $query)
    {
        global $wpdb;

        if ($query->is_main_query() && $query->get('post_type') === $this->getPostType() && $query->get('s')) {
            $groupby = $wpdb->posts . '.ID';
        }

        return $groupby;
    }
}