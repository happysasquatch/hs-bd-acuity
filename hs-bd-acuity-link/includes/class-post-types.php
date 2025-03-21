/**
 * Register post types
 */
private function register_post_types() {
    // Class post type
    $labels = array(
        'name'                  => _x('Acuity Classes', 'Post type general name', 'acuity-integration'),
        'singular_name'         => _x('Acuity Class', 'Post type singular name', 'acuity-integration'),
        'menu_name'             => _x('Acuity Classes', 'Admin Menu text', 'acuity-integration'),
        'name_admin_bar'        => _x('Acuity Class', 'Add New on Toolbar', 'acuity-integration'),
        'add_new'               => __('Add New', 'acuity-integration'),
        'add_new_item'          => __('Add New Class', 'acuity-integration'),
        'new_item'              => __('New Class', 'acuity-integration'),
        'edit_item'             => __('Edit Class', 'acuity-integration'),
        'view_item'             => __('View Class', 'acuity-integration'),
        'all_items'             => __('All Classes', 'acuity-integration'),
        'search_items'          => __('Search Classes', 'acuity-integration'),
        'parent_item_colon'     => __('Parent Classes:', 'acuity-integration'),
        'not_found'             => __('No classes found.', 'acuity-integration'),
        'not_found_in_trash'    => __('No classes found in Trash.', 'acuity-integration'),
        'featured_image'        => _x('Class Cover Image', 'Overrides the "Featured Image" phrase', 'acuity-integration'),
        'set_featured_image'    => _x('Set cover image', 'Overrides the "Set featured image" phrase', 'acuity-integration'),
        'remove_featured_image' => _x('Remove cover image', 'Overrides the "Remove featured image" phrase', 'acuity-integration'),
        'use_featured_image'    => _x('Use as cover image', 'Overrides the "Use as featured image" phrase', 'acuity-integration'),
        'archives'              => _x('Class archives', 'The post type archive label used in nav menus', 'acuity-integration'),
        'insert_into_item'      => _x('Insert into class', 'Overrides the "Insert into post" phrase', 'acuity-integration'),
        'uploaded_to_this_item' => _x('Uploaded to this class', 'Overrides the "Uploaded to this post" phrase', 'acuity-integration'),
        'filter_items_list'     => _x('Filter classes list', 'Screen reader text for the filter links', 'acuity-integration'),
        'items_list_navigation' => _x('Classes list navigation', 'Screen reader text for the pagination', 'acuity-integration'),
        'items_list'            => _x('Classes list', 'Screen reader text for the items list', 'acuity-integration'),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,            // Changed to false since this is admin-only
        'publicly_queryable' => false,            // Changed to false
        'show_ui'            => true,
        'show_in_menu'       => false,            // Hide from main menu since we have a custom menu
        'query_var'          => false,            // Changed to false
        'rewrite'            => false,            // Changed to false
        'capability_type'    => 'post',
        'has_archive'        => false,            // Changed to false
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array('title', 'editor', 'thumbnail', 'custom-fields'),
        'show_in_rest'       => true,             // Enable Gutenberg editor
        'menu_icon'          => 'dashicons-calendar-alt',
        'capabilities'       => array(
            'create_posts'   => Acuity_Integration_Constants::CAPABILITY,
            'edit_post'      => Acuity_Integration_Constants::CAPABILITY,
            'read_post'      => Acuity_Integration_Constants::CAPABILITY,
            'delete_post'    => Acuity_Integration_Constants::CAPABILITY,
            'edit_posts'     => Acuity_Integration_Constants::CAPABILITY,
            'edit_others_posts' => Acuity_Integration_Constants::CAPABILITY,
            'publish_posts'  => Acuity_Integration_Constants::CAPABILITY,
            'read_private_posts' => Acuity_Integration_Constants::CAPABILITY,
        ),
    );

    register_post_type(Acuity_Integration_Constants::POST_TYPE, $args);
}
