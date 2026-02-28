<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLB_Discount_Metabox_Discount extends SLN_Metabox_Abstract
{
    protected $fields = array(
        'amount'             => 'float',
        'amount_type'        => '',
        'usages_limit_total' => 'int',
        'usages_limit'       => 'int',
        'from'               => '',
        'to'                 => '',
        'services'           => '',
        'attendants'         => '',
        'shops'              => '',
        'type'               => '',
        'code'               => '',
        'rules'              => '',
        'email_notify'       => '',
        'hide_from_account'  => '',
        'exclusion_rules'    => '',
    );

    protected function init()
    {
        parent::init();
        add_action('admin_print_styles-edit.php', array($this, 'admin_print_styles'));
	add_action('in_admin_header', array($this, 'in_admin_header'));
    }

    public function add_meta_boxes()
    {
        $postType = $this->getPostType();
        add_meta_box(
            'postexcerpt',
            __('Discount details', 'salon-booking-system'),
            array($this, 'details_meta_box'),
            $postType,
            'normal',
            'high'
        );

        remove_meta_box('postexcerpt', $postType, 'side');
        add_meta_box(
            'sln_service-details',
            __('&nbsp', 'salon-booking-system'),
            array($this, 'advanced_meta_box'),
            $postType,
            'normal'
        );

        remove_meta_box('postimagediv', $postType, 'side');

        add_meta_box(
            'sln_discount-exclusion-rules',
            __('Exclusion rules', 'salon-booking-system'),
            array($this, 'exclusion_rules_meta_box'),
            $postType,
            'normal'
        );

        if (isset($_GET['post'])) {
            add_meta_box(
                'sln_attendant-details',
                __('Discount usage history', 'salon-booking-system'),
                array($this, 'history_meta_box'),
                $postType,
                'normal'
            );
        }
    }

    public function details_meta_box($object, $box)
    {
        echo $this->getPlugin()->loadView(
            'metabox/_discount_details',
            array(
                'metabox'  => $this,
                'settings' => $this->getPlugin()->getSettings(),
                'discount'   => SLB_Discount_Plugin::getInstance()->createDiscount($object),
                'postType' => $this->getPostType(),
                'helper'   => new SLN_Metabox_Helper(),
            )
        );
        do_action($this->getPostType().'_details_meta_box', $object, $box);
    }

    public function advanced_meta_box($object, $box)
    {
        echo $this->getPlugin()->loadView(
            'metabox/_discount_advanced',
            array(
                'metabox'  => $this,
                'settings' => $this->getPlugin()->getSettings(),
                'discount'   => SLB_Discount_Plugin::getInstance()->createDiscount($object),
                'postType' => $this->getPostType(),
                'helper'   => new SLN_Metabox_Helper(),
            )
        );
        do_action($this->getPostType().'_advanced_meta_box', $object, $box);
    }

    public function history_meta_box($object, $box)
    {
        echo $this->getPlugin()->loadView(
            'metabox/_discount_history',
            array(
                'metabox'  => $this,
                'settings' => $this->getPlugin()->getSettings(),
                'discount'   => SLB_Discount_Plugin::getInstance()->createDiscount($object),
                'postType' => $this->getPostType(),
                'helper'   => new SLN_Metabox_Helper(),
            )
        );
        do_action($this->getPostType().'_history_meta_box', $object, $box);
    }

    public function exclusion_rules_meta_box($object, $box)
    {
        echo $this->getPlugin()->loadView(
            'metabox/_discount_exclusion_rules',
            array(
                'metabox'  => $this,
                'settings' => $this->getPlugin()->getSettings(),
                'discount' => SLB_Discount_Plugin::getInstance()->createDiscount($object),
                'postType' => $this->getPostType(),
                'helper'   => new SLN_Metabox_Helper(),
            )
        );
        do_action($this->getPostType().'_exclusion_rules_meta_box', $object, $box);
    }

    protected function getFieldList()
    {
        return apply_filters('sln.metabox.discount.getFieldList', $this->fields);
    }

    /**
     * Sanitize and normalize submitted exclusion rules before saving.
     *
     * @param array|null $data
     * @return array
     */
    public static function processExclusionRules($data = null)
    {
        if (empty($data) || !is_array($data)) {
            return array();
        }

        $data = array_values($data);

        foreach ($data as &$rule) {
            $rule['mode'] = isset($rule['mode']) && in_array($rule['mode'], array('weekdays', 'specific_dates'), true)
                ? $rule['mode']
                : 'weekdays';

            // Weekday checkbox values (keys 1–7: 1=Sun … 7=Sat)
            if (!isset($rule['days']) || !is_array($rule['days'])) {
                $rule['days'] = array();
            } else {
                $rule['days'] = array_map('intval', $rule['days']);
            }

            // Comma-separated YYYY-MM-DD dates from the JS calendar
            $rule['specific_dates'] = isset($rule['specific_dates'])
                ? sanitize_text_field($rule['specific_dates'])
                : '';

            // Date-range scope
            if (isset($rule['always']) && (int) $rule['always'] === 1) {
                $rule['always']    = true;
                $rule['from_date'] = null;
                $rule['to_date']   = null;
            } else {
                $rule['always']    = false;
                $rule['from_date'] = !empty($rule['from_date'])
                    ? SLN_TimeFunc::evalPickedDate(sanitize_text_field($rule['from_date']))
                    : null;
                $rule['to_date'] = !empty($rule['to_date'])
                    ? SLN_TimeFunc::evalPickedDate(sanitize_text_field($rule['to_date']))
                    : null;
            }
        }
        unset($rule);

        return $data;
    }

    public function save_post($post_id, $post)
    {
        $pt = $this->getPostType();
        $h  = new SLN_Metabox_Helper;
        if ( ! $h->isValidRequest($pt, $post_id, $post)) {
            return;
        }

        if (!isset($_POST[$h::getFieldName($pt, 'services')])) {
            $_POST[$h::getFieldName($pt, 'services')] = array();
        }

        if (!empty($_POST[$h::getFieldName($pt, 'from')])) {
            $_POST[$h::getFieldName($pt, 'from')] = SLN_TimeFunc::evalPickedDate(sanitize_text_field(wp_unslash($_POST[$h::getFieldName($pt, 'from')])));
        }
        if (!empty($_POST[$h::getFieldName($pt, 'to')])) {
            $_POST[$h::getFieldName($pt, 'to')] = SLN_TimeFunc::evalPickedDate(sanitize_text_field(wp_unslash($_POST[$h::getFieldName($pt, 'to')])));
        }

        $k = $h::getFieldName($pt, 'rules');
        if(isset($_POST[$k])) {
            $_POST[$k] = SLB_Discount_Helper_DiscountItems::processSubmission($_POST[$k]);
        }

        $exclusionKey = $h::getFieldName($pt, 'exclusion_rules');
        if (defined('SLN_VERSION_PAY')) {
            if (isset($_POST[$exclusionKey])) {
                $_POST[$exclusionKey] = self::processExclusionRules($_POST[$exclusionKey]);
            }
        } else {
            // Free edition: strip any submitted exclusion rules to prevent saving
            unset($_POST[$exclusionKey]);
        }

        $meta = $h->processRequest($pt, $this->getFieldList());
        foreach ($meta as $meta_key => $new_meta_value) {
            update_post_meta($post_id, $meta_key, $new_meta_value);
        }
    }
}