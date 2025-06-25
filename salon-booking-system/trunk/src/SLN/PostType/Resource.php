<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_PostType_Resource extends SLN_PostType_Abstract
{

    public function init()
    {
        if (!defined('SLN_VERSION_PAY') || !SLN_VERSION_PAY || !SLN_Plugin::getInstance()->getSettings()->isResourcesEnabled()) {
            return;
        }

        parent::init();
        $admin_role = get_role('administrator');
        if(!$admin_role->has_cap('edit_sln_resources') || !$admin_role->capabilities['edit_sln_resources']){
            SLN_UserRole_SalonStaff::addCapabilitiesForRole('administrator');
        }

        if (is_admin()) {
            add_action('pre_get_posts', array($this, 'admin_posts_sort'));
            add_action('wp_insert_post', array($this, 'wp_insert_post'));
            add_action('manage_'.$this->getPostType().'_posts_custom_column', array($this, 'manage_column'), 10, 2);
            add_filter('manage_'.$this->getPostType().'_posts_columns', array($this, 'manage_columns'));
            add_filter('manage_edit-'.$this->getPostType().'_sortable_columns', array($this, 'custom_columns_sort'));
            add_action('admin_head-post-new.php', array($this, 'posttype_admin_css'));
            add_action('admin_head-post.php', array($this, 'posttype_admin_css'));
            add_action('admin_enqueue_scripts', array($this, 'load_scripts'));
            add_action('wp_ajax_sln_resource', array($this, 'ajax'));
	    add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_custom_box' ), 10, 2 );
	    add_action( 'save_post', array( $this, 'save_post' ), 50);
        }
    }

    public function custom_columns_sort( $columns ) {
        $custom = array(
            'title' => 'title',
        );
        return $custom;
    }

    /**
     * @param WP_Query $query
     */
    function admin_posts_sort($query)
    {
        global $pagenow, $post_type;

        if (
            is_admin() && 'edit.php' == $pagenow 
            && $post_type == $this->getPostType() 
            && (is_array($query->get('post_type')) ? in_array($this->getPostType(), $query->get('post_type')) : $query->get('post_type') === $this->getPostType())
            && $query->get('orderby') !== 'title'
        ) {
            /** @var SLN_Repository_ServiceRepository $repo */
            $repo = $this->getPlugin()->getRepository($this->getPostType());
            foreach ($repo->getStandardCriteria() as $k => $v) {
                $query->set($k, $v);
            }

            $this->setPostsOrderByFilter();
        }
    }

	public function setPostsOrderByFilter() {
		add_filter('posts_orderby', array($this, 'postsOrderby'), 10, 2);
	}

	/**
	 * @param string $orderby
	 * @param WP_Query $query
	 *
	 * @return string
	 */
	public function postsOrderby($orderby, $query) {
        global $wpdb;
		remove_filter('posts_orderby', array($this, 'postsOrderby'), 10);

		return str_replace("{$wpdb->postmeta}.meta_value", "CAST({$wpdb->postmeta}.meta_value AS DECIMAL)", $orderby);
	}



    public function load_scripts($hook)
    {
        if ('edit.php' === $hook &&
                isset($_GET['post_type']) &&
            $this->getPostType() === $_GET['post_type']) {

            $event = 'Page views of back-end plugin pages';
            $data  = array(
                'page' => 'resources',
            );

            SLN_Action_InitScripts::mixpanelTrack($event, $data);
        }
    }

    public function wp_insert_post($post_id, $wp_error = false)
    {

    }

    public function ajax()
    {
        if(!current_user_can('edit_sln_resources')){
            wp_die('<p>' . esc_html__('Sory, you not allowed to ajax.'). '</p>', 403);
        }
        if (isset($_POST['method']) && current_user_can('edit_sln_resources')) {
            $method = 'ajax_'.sanitize_text_field(wp_unslash($_POST['method']));
            if (method_exists($this, $method)) {
                $this->$method();
            }
        }
        die();
    }

    public function manage_columns($columns)
    {
        $new_columns = array(
            'cb'        => $columns['cb'],
            'ID'        => __('Resource ID', 'salon-booking-system'),
            'title'     => $columns['title'],
            'unit'      => __('Units per session', 'salon-booking-system'),
            'enabled'   => __('Is active', 'salon-booking-system'),
            'services'  => __('Services assigned', 'salon-booking-system'),
        );

        return $new_columns;
    }

    public function manage_column($column, $post_id)
    {
        $obj = $this->getPlugin()->createResource($post_id);
        switch ($column) {
            case 'ID' :
                echo edit_post_link($post_id, '<p>', '</p>', $post_id);
                break;
            case 'unit':
		$units = $obj->getUnitPerHour();
                echo $units ? $units : '-';
                break;
            case 'enabled' :
                echo $obj->getEnabled() ? esc_html__('YES', 'salon-booking-system') : esc_html__('NO', 'salon-booking-system');
                break;
            case 'services' :
                echo count($obj->getServices());
                break;
        }
    }

    public function enter_title_here($title, $post)
    {

        if ($this->getPostType() === $post->post_type) {
            $title = __('Enter resource name', 'salon-booking-system');
        }

        return $title;
    }

    public function updated_messages($messages)
    {
        global $post, $post_ID;

        $messages[$this->getPostType()] = array(
            0 => '', // Unused. Messages start at index 1.
            1 => sprintf(
                __('Resource updated.', 'salon-booking-system')
            ),
            2 => '',
            3 => '',
            4 => __('Resource updated.', 'salon-booking-system'),
            5 => isset($_GET['revision']) ? sprintf(
                // translators: %s will be replaced by the revision
                __('Resource restored to revision from %s', 'salon-booking-system'),
                wp_post_revision_title((int)$_GET['revision'], false)
            ) : false,
            6 => sprintf(
                __('Resource published.', 'salon-booking-system')
            ),
            7 => __('Resource saved.', 'salon-booking-system'),
            8 => sprintf(
                __('Resource submitted.', 'salon-booking-system')
            ),
            9 => sprintf(
                // translators: %1$s will be replaced by the date
                __(
                    'Resource scheduled for: <strong>%1$s</strong>. ',
                    'salon-booking-system'
                ),
                SLN_TimeFunc::translateDate(__('M j, Y @ G:i', 'salon-booking-system'), SLN_TimeFunc::getPostTimestamp($post))
            ),
            10 => sprintf(
                __('Resource draft updated.', 'salon-booking-system')
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
            'rewrite' => true,
            'supports' => array(
                'title',
            ),
            'labels' => array(
                'name' => __('Resources', 'salon-booking-system'),
                'singular_name' => __('Resource', 'salon-booking-system'),
                'menu_name' => __('Salon', 'salon-booking-system'),
                'name_admin_bar' => __('Salon Resource', 'salon-booking-system'),
                'all_items' => __('Resources', 'salon-booking-system'),
                'add_new' => __('Add Resource', 'salon-booking-system'),
                'add_new_item' => __('Add New Resource', 'salon-booking-system'),
                'edit_item' => __('Edit Resource', 'salon-booking-system'),
                'new_item' => __('New Resource', 'salon-booking-system'),
                'view_item' => __('View Resource', 'salon-booking-system'),
                'search_items' => __('Search Resources', 'salon-booking-system'),
                'not_found' => __('No resources found', 'salon-booking-system'),
                'not_found_in_trash' => __('No resources found in trash', 'salon-booking-system'),
                'archive_title' => __('Resources Archive', 'salon-booking-system'),
            ),
            'capability_type' => array($this->getPostType(), $this->getPostType().'s'),
            'map_meta_cap' => true
        );
    }

    public function posttype_admin_css()
    {
        global $post_type;
        if ($post_type == $this->getPostType()) {
            $this->getPlugin()->loadView('metabox/_resource_head');
        }
    }

    public function quick_edit_custom_box( $column_name, $post_type ) {

    }

    public function save_post( $post_id ) {

    }

}
