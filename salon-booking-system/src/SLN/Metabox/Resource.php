<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Metabox_Resource extends SLN_Metabox_Abstract
{
    protected $fields = array(
        'unit'      => 'int',
        'enabled'   => 'bool',
        'services'  => '',
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
            $postType.'-details',
            __('Resource details', 'salon-booking-system'),
            array($this, 'details_meta_box'),
            $postType,
            'normal',
            'high'
        );

	do_action('sln.resource.add_meta_boxes');
        remove_meta_box('postexcerpt', $postType, 'side');
    }

    public function details_meta_box($object, $box)
    {
        echo $this->getPlugin()->loadView(
            'metabox/resource',
            array(
                'metabox'  => $this,
                'settings' => $this->getPlugin()->getSettings(),
                'resource' => $this->getPlugin()->createResource($object),
                'postType' => $this->getPostType(),
                'helper'   => new SLN_Metabox_Helper(),
            )
        );
        do_action($this->getPostType().'_details_meta_box', $object, $box);
    }

    protected function getFieldList()
    {
        return apply_filters('sln.metabox.resource.getFieldList', $this->fields);
    }

    protected function enqueueAssets()
    {
        parent::enqueueAssets();
    }

    public function save_post($post_id, $post)
    {
        parent::save_post($post_id, $post);
    }
}
