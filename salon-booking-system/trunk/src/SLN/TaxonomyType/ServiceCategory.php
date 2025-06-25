<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_TaxonomyType_ServiceCategory extends SLN_TaxonomyType_Abstract
{
    public function __construct(SLN_Plugin $plugin, $taxonomyType, $postTypes)
    {
        parent::__construct($plugin, $taxonomyType, $postTypes);

	add_action('manage_'.$taxonomyType.'_custom_column', array($this, 'manage_column'), 10, 3);
	add_filter('manage_edit-'.$taxonomyType.'_columns', array($this, 'manage_columns'));
	add_filter('manage_edit-'.$taxonomyType.'_sortable_columns', array($this, 'manage_sortable_columns'));
	add_filter('terms_clauses', array($this, 'sort_by_term_id'), 10, 3);
	add_action('in_admin_header', array($this, 'in_admin_header'));
    add_action('sln_service_category_add_form_fields', array($this, 'service_category_meta_fields_form'));
    add_action($taxonomyType.'_term_new_form_tag', array($this, 'term_new_form_tag'));
    add_action($taxonomyType.'_term_edit_form_tag', array($this, 'term_new_form_tag'));
    add_action('saved_'.$taxonomyType, array($this, 'saved_taxonomy'), 10, 4);
    add_action($taxonomyType.'_edit_form_fields', array($this, 'service_category_meta_fields_table'), 10, 2);
    add_action('pre_delete_term', array($this, 'delete'), 10, 2);
    wp_register_script(
        'salon-customServiceCategory',
        SLN_PLUGIN_URL . '/js/admin/customServiceCategory.js',
        array('jquery'),
        SLN_Action_InitScripts::ASSETS_VERSION,
        true
    );
    wp_enqueue_script(
        'salon-customServiceCategory',
        SLN_PLUGIN_URL . '/js/admin/customServiceCategory.js',
        array('jquery'),
        SLN_Action_InitScripts::ASSETS_VERSION,
        true
    );
    }

    protected function getTaxonomyTypeArgs()
    {
        // hook into the init action and call create_book_taxonomies when it fires
        $labels = array(
	    'name'	    => _x('Service Categories', 'taxonomy general name', 'salon-booking-system'),
	    'singular_name' => _x('Service Category', 'taxonomy singular name', 'salon-booking-system'),
	    'search_items'  => __('Search Service Category', 'salon-booking-system'),
	    'all_items'	    => __('All Service Categories', 'salon-booking-system'),
	    'edit_item'	    => __('Edit Service Category', 'salon-booking-system'),
	    'update_item'   => __('Update Service Category', 'salon-booking-system'),
	    'add_new_item'  => __('Add New Service Category', 'salon-booking-system'),
	    'new_item_name' => __('New Service Category Name', 'salon-booking-system'),
	    'menu_name'	    => __('Service Category', 'salon-booking-system'),
	);

	$postTypeCapability = 'edit_' . $this->postTypes[0] . 's';

	$args = array(
	    'hierarchical'	=> true,
	    'labels'		=> $labels,
	    'show_in_menu'	=> 'salon',
	    'show_ui'		=> true,
	    'show_admin_column' => true,
            'meta_box_cb'       => false,
	    'query_var'		=> true,
	    'rewrite'		=> array('slug' => 'servicecategory'),
	    'capabilities'	=> array(
		'manage_terms' => $postTypeCapability,
		'edit_terms'   => $postTypeCapability,
		'delete_terms' => $postTypeCapability,
		'assign_terms' => $postTypeCapability,
	    ),
	);

        return $args;
    }

    public function initAdmin()
    {
	global $submenu;

	$tax_name = $this->taxonomyType;
        $taxonomy = get_taxonomy($tax_name);
        foreach ($taxonomy->object_type as $pt) {
            # Add our own
            add_meta_box("unique-{$tax_name}-div", $taxonomy->labels->singular_name, array($this, 'unique_taxonomies_metabox'), $pt, 'side', 'low', array('taxonomy' => $tax_name));
        }
        add_filter('get_terms_orderby', array($this, 'set_the_terms_in_order'), 10, 4);

	if (!$submenu || !isset($submenu['salon'])) {
	    return;
	}

	$submenu['salon'] = array_merge(
	    array_slice($submenu['salon'], 0, 4),
	    array(
		array(
		    __('Services Categories', 'salon-booking-system'),
		    'edit_sln_services',
		    add_query_arg(array('taxonomy' => SLN_Plugin::TAXONOMY_SERVICE_CATEGORY, 'post_type' => SLN_Plugin::POST_TYPE_SERVICE), 'edit-tags.php'),
		    __('Services Categories', 'salon-booking-system')
		),
	    ),
	    array_slice($submenu['salon'], 4)
	);

	add_filter( 'parent_file', array($this, 'set_current_menu') );
    }

    function set_current_menu( $parent_file ) {

        global $submenu_file, $current_screen, $pagenow;

        # Set the submenu as active/current while anywhere in your Custom Post Type (nwcm_news)
        if ( $current_screen->post_type == SLN_Plugin::POST_TYPE_SERVICE ) {

            if ( $pagenow == 'edit-tags.php' || $pagenow == 'term.php' ) {
                $submenu_file = add_query_arg(array('taxonomy' => SLN_Plugin::TAXONOMY_SERVICE_CATEGORY, 'post_type' => $current_screen->post_type), 'edit-tags.php');
            }

            $parent_file = 'salon';
        }

        return $parent_file;

    }

    public function set_the_terms_in_order($terms, $id, $taxonomy)
    {

        if ($taxonomy[0] == SLN_Plugin::TAXONOMY_SERVICE_CATEGORY && get_option(SLN_Plugin::CATEGORY_ORDER)) {
            $order = get_option(SLN_Plugin::CATEGORY_ORDER, '""');
            return "FIELD(t.term_id, $order)";
        }

        return $terms;
    }

    function terms_radiolist($post_id, $taxonomy, $echo = true)
    {
        $terms = get_terms(['taxonomy'=>$taxonomy,'hide_empty' => false]);
        if (empty($terms))
            return;
        $name = ( $taxonomy == 'category' ) ? 'post_category' : "tax_input[{$taxonomy}]";

        $post_terms = get_the_terms($post_id, $taxonomy);
        $nu_post_terms = array();
        if (!empty($post_terms)) {
            foreach ($post_terms as $post_term)
                $nu_post_terms[] = $post_term->term_id;
        }

        $output = '';
        foreach ($terms as $term) {
            $term = new SLN_Wrapper_ServiceCategory($term);
            $output .= "<li class='selectit'>";
            $output .= "<label>";
            $output .= "<input type='radio' name='{$name}[]' value='". esc_attr($term->getName()) . "' " . checked(in_array($term->getId(), $nu_post_terms), true, false) . "/>";
            $output .= " ".esc_html__($term->getName(),'salon-booking-system')."</label>";
            $output .= "</li>";
        }
        $output .= "<li class='selectit'><label><input type='radio' name='{$name}[]' value='' " . checked(empty($nu_post_terms), true, false) . "/>" . __('Not defined', 'salon-booking-system') . "</label></li>";
        if ($echo)
            echo $output;
        else
            return $output;
    }

    function unique_taxonomies_metabox($post, $box)
    {
        if (!isset($box['args']) || !is_array($box['args']))
            $args = array();
        else
            $args = $box['args'];

        $defaults = array('taxonomy' => 'category');
        extract(wp_parse_args($args, $defaults), EXTR_SKIP);
        $tax = get_taxonomy($taxonomy);

        ?>
        <div id="taxonomy-<?php echo $taxonomy; ?>" class="categorydiv">
            <?php
            $name = ( $taxonomy == 'category' ) ? 'post_category' : 'tax_input[' . $taxonomy . ']';
            echo "<input type='hidden' name='{$name}' value='0' />"; // Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.

            ?>
            <ul id="<?php echo $taxonomy; ?>checklist" class="list:<?php echo $taxonomy ?> categorychecklist form-no-clear">
            <?php $this->terms_radiolist($post->ID, $taxonomy) ?>
            </ul>
            <?php if (!current_user_can($tax->cap->assign_terms)) { ?>
                <p><em><?php esc_html_e('You cannot modify this taxonomy.', 'salon-booking-system'); ?></em></p>
        <?php } ?>
        <?php if (current_user_can($tax->cap->edit_terms)) { ?>
                <div id="<?php echo $taxonomy; ?>-adder" class="wp-hidden-children">
                    <h4>
                        <a href="<?php echo admin_url('edit-tags.php?taxonomy=' . $taxonomy) ?>">
                            _<?php esc_html_e('Manage service categories', 'salon-booking-system') ?>
                        </a>
                    </h4>
                </div>
        <?php } ?>
        </div>
        <?php
    }

    public function manage_columns($columns)
    {
        $new_columns = array(
            'term_id' => __('ID', 'salon-booking-system'),
	);

        return array_merge(array_slice($columns, 0, 1), $new_columns, array_slice($columns, 1));
    }

    public function manage_column($value, $column_name, $term_id)
    {
	switch ($column_name) {
            case 'term_id' :
		$term = get_term($term_id, $this->taxonomyType);
                echo edit_term_link($term_id, '<p>', '</p>', $term);
                break;
        }
    }

    public function manage_sortable_columns($columns)
    {
	$columns['term_id'] = 'term_id';

        return $columns;
    }

    public function sort_by_term_id($query, $taxonomies, $args) {

	global $pagenow;

	if(is_admin() && $pagenow == 'edit-tags.php' && $taxonomies[0] == $this->taxonomyType && isset($_GET['orderby']) && $_GET['orderby'] == 'term_id') {
	    $query['orderby'] = "ORDER BY t.term_id";
	}

	return $query;
    }

    public function in_admin_header() {
	if (isset($_GET['taxonomy']) && $_GET['taxonomy'] == $this->taxonomyType) {
	    echo '<div class="sln-help-button-in-header-page">';
	    echo $this->plugin->loadView('admin/help');
	    echo '</div>';
	}
    }

    public function term_new_form_tag(){
        echo ' enctype="multipart/form-data" ';
        return;
    }

    public function delete($term, $taxonomy){
        if($taxonomy == $this->taxonomyType){
            wp_delete_attachment(get_term_meta($term, $this->taxonomyType.'_image', true), true);
            delete_term_meta($term, $this->taxonomyType.'_image');
        }
    }

    public function saved_taxonomy($term_id, $tt_id, $update, $args){
        $new_image = isset($_POST['sln_service_category_image_name']) ? $_POST['sln_service_category_image_name'] : null;
        if(
            !$update && !empty($new_image) 
            && isset($_FILES[$args['taxonomy']. '_image']['tmp_name']) 
            && !empty($_FILES[$args['taxonomy']. '_image']['tmp_name']) 
            && exif_imagetype($_FILES[$args['taxonomy']. '_image']['tmp_name'])
        ){
            $_FILES[$args['taxonomy']. '_image']['name'] = $this->taxonomyType.'_image_'.$term_id. '.'. explode('/', $_FILES[$args['taxonomy']. '_image']['type'])[1];
            
			$attId = media_handle_upload($this->taxonomyType.'_image', 0);

			if (!is_wp_error($attId)) {
				update_term_meta($term_id, $this->taxonomyType. '_image', $attId);
			}
            
        }elseif(empty($new_image)){
            wp_delete_attachment(get_term_meta($term_id, $args['taxonomy']. '_image', true), true);
            update_term_meta($term_id, $this->taxonomyType. '_image', '');
        }elseif(
            $update 
            && isset($_FILES[$args['taxonomy']. '_image']['tmp_name']) 
            && !empty($_FILES[$args['taxonomy']. '_image']['tmp_name']) 
            && exif_imagetype($_FILES[$args['taxonomy']. '_image']['tmp_name'])
        ){
            wp_delete_attachment(get_term_meta($term_id, $args['taxonomy']. '_image', true), true);
            $_FILES[$args['taxonomy']. '_image']['name'] = $this->taxonomyType.'_image_'.$term_id. '.'. explode('/', $_FILES[$args['taxonomy']. '_image']['type'])[1];
            $attId = media_handle_upload($this->taxonomyType.'_image', 0);
			if (!is_wp_error($attId)) {
				update_term_meta($term_id, $this->taxonomyType. '_image', $attId);
            }
        }
    }

    public function service_category_meta_fields_form($category){ ?>
        <label for="<?php echo $this->taxonomyType. '_image'; ?>"><?php esc_html_e('Category image', 'salon-booking-system')?> </label>
        <div class="sln-logo-box">
                <div id="logo" class="preview-logo hide">
                    <div class="preview-logo-img">
                        <img src="" width="150" height="150">
                    </div>
                    <button type="button" class="sln-btn sln-btn--light sln-btn-medium sln-btn--icon sln-icon--trash" data-action="delete-logo" data-target-remove="logo"
                            data-target-reset="taxonomy_image_logo" data-target-show="select_logo">
                            <?php esc_html_e('Remove this image', 'salon-booking-system')?>
                    </button>
                </div>
            <div id="select_logo" class="select-logo" data-action="select-logo" data-target="<?php echo $this->taxonomyType. '_image'; ?>">
                <span class="dashicons dashicons-upload"></span>
            </div>
            <div class="hide">
                <input class="info" type="file" name="<?php echo $this->taxonomyType. '_image'; ?>" id="<?php echo $this->taxonomyType. '_image'; ?>" data-action="select-file-logo" data-target="taxonomy_image_logo" accept="image/png">
                <input type="hidden" name="<?php echo $this->taxonomyType.'_image_name'?>" id="taxonomy_image_logo" value="">
            </div>        
        </div>
    <?php }

    public function service_category_meta_fields_table($tax, $tt){
        $image_url = '';
        if(get_term_meta($tax->term_id, $this->taxonomyType. '_image', true)){
            $image_url = wp_get_attachment_image_url(get_term_meta($tax->term_id, $this->taxonomyType. '_image', true));
        }

        ?>
        <tr id="sln-salon--admin" class="form-field term-image-wrap ui-sortable-handle">
            <th scope="row" >
                <label for="<?php echo $this->taxonomyType. '_image'; ?>"><?php esc_html_e('Category image', 'salon-booking-system'); ?> </label>
                <td class="sln-logo-box">
                    
                        <div id="logo" class="preview-logo <?php echo empty($image_url)? 'hide': ''?>">
                            <div class="preview-logo-img">
                                <img src="<?php echo $image_url; ?>" width="150" height="150">
                            </div>
                            <button type="button" class="sln-btn sln-btn--light sln-btn-medium sln-btn--icon sln-icon--trash" data-action="delete-logo" data-target-remove="logo"
                                    data-target-reset="taxonomy_image_logo" data-target-show="select_logo">
                                    <?php esc_html_e('Remove this image', 'salon-booking-system')?>
                            </button>
                        </div>
                    <div id="select_logo" class="select-logo <?php echo empty($image_url) ? '' : 'hide'?>" data-action="select-logo" data-target="<?php echo $this->taxonomyType. '_image'; ?>">
                        <span class="dashicons dashicons-upload"></span>
                    </div>
                    <div class="hide">
                        <input class="info" type="file" name="<?php echo $this->taxonomyType. '_image'; ?>" id="<?php echo $this->taxonomyType. '_image'; ?>" data-action="select-file-logo" data-target="taxonomy_image_logo" accept="image/png">
                        <input type="hidden" name="<?php echo $this->taxonomyType.'_image_name'?>" id="taxonomy_image_logo" value="<?php echo $image_url ?>">
                    </div>
                    
                </td>
            </th>
        </tr>
    <?php }

}
