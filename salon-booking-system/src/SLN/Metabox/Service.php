<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Metabox_Service extends SLN_Metabox_Abstract
{
    protected $fields = array(
        'availabilities' => '',
        'price'          => 'float',
        'duration'       => 'time',
        'secondary'      => 'bool',
        'exclusive'      => 'bool',
        'hide_on_frontend' => 'bool',
        'secondary_display_mode'    => '',
        'secondary_parent_services' => '',
        'attendants'     => 'bool',
        'parallel_exec'  => 'bool',
        'unit'           => 'int',
        'break_duration' => 'int',
        'exec_order'     => 'int',
        'break_duration_data' => '',
        'variable_price_enabled' => 'bool',
        'variable_price' => '',
        'variable_duration' => 'bool',
        'max_variable_duration' => 'int',
	    'multiple_attendants_for_service' => 'bool',
        'multiple_count_attendants' => 'int',
        'offset_for_service' => 'bool',
        'offset_for_service_interval' => 'int',
        'lock_for_service' => 'bool',
        'lock_for_service_interval' => '',
    );

    protected function init()
    {
        parent::init();
        add_action('admin_print_styles-edit.php', array($this, 'admin_print_styles'));
        add_action('admin_enqueue_scripts', array($this, 'admin_print_styles_tag'));
	add_action('in_admin_header', array($this, 'in_admin_header'));
    }

    public function admin_print_styles_tag()
    {
        global $taxnow, $pagenow;
        if(($pagenow == 'edit-tags.php' || $pagenow == 'term.php') && $taxnow == SLN_Plugin::TAXONOMY_SERVICE_CATEGORY) {
            $this->enqueueAssets();
        }
    }

    public function add_meta_boxes()
    {
        $postType = $this->getPostType();
        add_meta_box(
            $postType.'-details',
            __('Service details', 'salon-booking-system'),
            array($this, 'details_meta_box'),
            $postType,
            'normal',
            'high'
        );

	do_action('sln.service.add_meta_boxes');

        remove_meta_box('postexcerpt', $postType, 'side');
        add_meta_box(
            'postexcerpt',
            __('Service description', 'salon-booking-system'),
            array($this, 'post_excerpt_meta_box'),
            $postType,
            'normal',
            'high'
        );
    }

    public function post_excerpt_meta_box($post)
    {
        ?>
        <label class="screen-reader-text" for="excerpt">
            <?php esc_html_e('Service Description', 'salon-booking-system') ?>
        </label>
        <textarea rows="1" cols="40" name="excerpt"
                  id="excerpt"><?php echo $post->post_excerpt; // textarea_escaped
            ?></textarea>
        <p><?php esc_html_e('A very short description of this service. It is optional', 'salon-booking-system'); ?></p>
        <?php
    }


    public function details_meta_box($object, $box)
    {
        echo $this->getPlugin()->loadView(
            'metabox/service',
            array(
                'metabox'  => $this,
                'settings' => $this->getPlugin()->getSettings(),
                'service'  => $this->getPlugin()->createService($object),
                'postType' => $this->getPostType(),
                'helper'   => new SLN_Metabox_Helper(),
            )
        );
        do_action($this->getPostType().'_details_meta_box', $object, $box);
    }

    protected function getFieldList()
    {
        return apply_filters('sln.metabox.service.getFieldList', $this->fields);
    }

    protected function enqueueAssets()
    {
        parent::enqueueAssets();
        SLN_Action_InitScripts::enqueueCustomSliderRange();
        SLN_Action_InitScripts::enqueueCustomMetaService();
    }


    public function save_post($post_id, $post)
    {
        $k = '_sln_service_availabilities';
        if(isset($_POST[$k])) {
            $_POST[$k] = SLN_Helper_AvailabilityItems::processSubmission($_POST[$k]);
            // Allow add-ons to sanitize/modify service availability data before saving
            $_POST[$k] = apply_filters('sln.service.availability_data.before_save', $_POST[$k], $post_id);
        }
        parent::save_post($post_id, $post);
        
        // Force clear ALL caches - WordPress object cache + options
        SLN_Plugin::addLog('[Service Save] Clearing all caches');
        wp_cache_delete('salon_cache', 'options');
        wp_cache_flush(); // Clear all object cache
        delete_option('salon_cache');
        delete_transient('salon_cache'); // Clear any transients too
        SLN_Plugin::addLog('[Service Save] All caches cleared, now rebuilding...');
        
        // Refresh booking cache when service settings change (e.g., break duration)
        $this->getPlugin()->getBookingCache()->refreshAll();
        
        // Force the cache to be saved immediately
        $this->getPlugin()->getBookingCache()->save();
        
        SLN_Plugin::addLog('[Service Save] Cache rebuild and save complete');
    }
}
