<?php
/**
 * Advanced Threads System - Post Types & Taxonomies
 *
 * @package AdvancedThreadsSystem
 * @subpackage PostTypes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'ATS_Post_Types' ) ) {

class ATS_Post_Types {

    /** @var ATS_Post_Types|null */
    private static $instance = null;

    /** Slugs */
    private $post_type     = 'ats_thread';
    private $tax_category  = 'ats_category';
    private $tax_tag       = 'ats_tag';

    /** Singleton */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Constructor */
    private function __construct() {
        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'init', array( $this, 'register_taxonomies' ) );

        add_action( 'add_meta_boxes', array( $this, 'add_thread_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_thread_meta' ), 10, 2 );

        // Admin list table
        add_filter( 'manage_' . $this->post_type . '_posts_columns', array( $this, 'add_thread_columns' ) );
        add_action( 'manage_' . $this->post_type . '_posts_custom_column', array( $this, 'display_thread_columns' ), 10, 2 );
        add_filter( 'manage_edit-' . $this->post_type + '_sortable_columns', array( $this, 'sortable_thread_columns' ) );
        add_action( 'pre_get_posts', array( $this, 'handle_thread_sorting' ) );
    }

    /** Register Custom Post Type */
    public function register_post_types() {
        $labels = array(
            'name'                  => _x( 'Threads', 'Post Type General Name', 'advanced-threads' ),
            'singular_name'         => _x( 'Thread', 'Post Type Singular Name', 'advanced-threads' ),
            'menu_name'             => __( 'Threads', 'advanced-threads' ),
            'name_admin_bar'        => __( 'Thread', 'advanced-threads' ),
            'add_new'               => __( 'Add New', 'advanced-threads' ),
            'add_new_item'          => __( 'Add New Thread', 'advanced-threads' ),
            'edit_item'             => __( 'Edit Thread', 'advanced-threads' ),
            'new_item'              => __( 'New Thread', 'advanced-threads' ),
            'view_item'             => __( 'View Thread', 'advanced-threads' ),
            'search_items'          => __( 'Search Threads', 'advanced-threads' ),
            'not_found'             => __( 'Not found', 'advanced-threads' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'advanced-threads' ),
            'all_items'             => __( 'All Threads', 'advanced-threads' ),
            'archives'              => __( 'Thread Archives', 'advanced-threads' ),
        );

        $args = array(
            'label'                 => __( 'Threads', 'advanced-threads' ),
            'labels'                => $labels,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 25,
            'menu_icon'             => 'dashicons-format-chat',
            'capability_type'       => 'post',
            'has_archive'           => true,
            'rewrite'               => array( 'slug' => 'threads', 'with_front' => false ),
            'supports'              => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ),
            'show_in_rest'          => true,
        );

        register_post_type( $this->post_type, $args );
    }

    /** Register Taxonomies */
    public function register_taxonomies() {
        // Categories (hierarchical)
        $category_labels = array(
            'name'              => _x( 'Thread Categories', 'Taxonomy General Name', 'advanced-threads' ),
            'singular_name'     => _x( 'Thread Category', 'Taxonomy Singular Name', 'advanced-threads' ),
            'menu_name'         => __( 'Categories', 'advanced-threads' ),
            'all_items'         => __( 'All Categories', 'advanced-threads' ),
            'parent_item'       => __( 'Parent Category', 'advanced-threads' ),
            'parent_item_colon' => __( 'Parent Category:', 'advanced-threads' ),
            'new_item_name'     => __( 'New Category Name', 'advanced-threads' ),
            'add_new_item'      => __( 'Add New Category', 'advanced-threads' ),
            'edit_item'         => __( 'Edit Category', 'advanced-threads' ),
            'update_item'       => __( 'Update Category', 'advanced-threads' ),
            'view_item'         => __( 'View Category', 'advanced-threads' ),
            'search_items'      => __( 'Search Categories', 'advanced-threads' ),
            'not_found'         => __( 'Not Found', 'advanced-threads' ),
        );

        $category_args = array(
            'labels'            => $category_labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud'     => false,
            'rewrite'           => array( 'slug' => 'threads/category', 'with_front' => false ),
            'show_in_rest'      => true,
        );

        register_taxonomy( $this->tax_category, array( $this->post_type ), $category_args );

        // Tags (non-hierarchical)
        $tag_labels = array(
            'name'                       => _x( 'Thread Tags', 'Taxonomy General Name', 'advanced-threads' ),
            'singular_name'              => _x( 'Thread Tag', 'Taxonomy Singular Name', 'advanced-threads' ),
            'menu_name'                  => __( 'Tags', 'advanced-threads' ),
            'all_items'                  => __( 'All Tags', 'advanced-threads' ),
            'new_item_name'              => __( 'New Tag Name', 'advanced-threads' ),
            'add_new_item'               => __( 'Add New Tag', 'advanced-threads' ),
            'edit_item'                  => __( 'Edit Tag', 'advanced-threads' ),
            'update_item'                => __( 'Update Tag', 'advanced-threads' ),
            'view_item'                  => __( 'View Tag', 'advanced-threads' ),
            'separate_items_with_commas' => __( 'Separate tags with commas', 'advanced-threads' ),
            'add_or_remove_items'        => __( 'Add or remove tags', 'advanced-threads' ),
            'choose_from_most_used'      => __( 'Choose from the most used', 'advanced-threads' ),
            'popular_items'              => __( 'Popular Tags', 'advanced-threads' ),
            'search_items'               => __( 'Search Tags', 'advanced-threads' ),
            'not_found'                  => __( 'Not Found', 'advanced-threads' ),
        );

        $tag_args = array(
            'labels'            => $tag_labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'update_count_callback' => '_update_post_term_count',
            'rewrite'           => array( 'slug' => 'threads/tag', 'with_front' => false ),
            'show_in_rest'      => true,
        );

        register_taxonomy( $this->tax_tag, array( $this->post_type ), $tag_args );
    }

    /** Meta boxes */
    public function add_thread_meta_boxes() {
        add_meta_box(
            'ats_thread_details',
            __( 'Thread Details', 'advanced-threads' ),
            array( $this, 'render_thread_meta_box' ),
            $this->post_type,
            'side',
            'default'
        );
    }

    /** Render meta box */
    public function render_thread_meta_box( $post ) {
        wp_nonce_field( 'ats_thread_meta_nonce', 'ats_thread_meta_nonce' );

        $type       = get_post_meta( $post->ID, '_ats_thread_type', true );
        $difficulty = get_post_meta( $post->ID, '_ats_thread_difficulty', true );
        $status     = get_post_meta( $post->ID, '_ats_thread_status', true );

        ?>
        <p>
            <label for="ats_thread_type"><strong><?php _e( 'Thread type', 'advanced-threads' ); ?></strong></label><br/>
            <select id="ats_thread_type" name="ats_thread_type">
                <option value="discussion" <?php selected( $type, 'discussion' ); ?>><?php _e( 'Discussion', 'advanced-threads' ); ?></option>
                <option value="question" <?php selected( $type, 'question' ); ?>><?php _e( 'Question', 'advanced-threads' ); ?></option>
                <option value="announcement" <?php selected( $type, 'announcement' ); ?>><?php _e( 'Announcement', 'advanced-threads' ); ?></option>
                <option value="poll" <?php selected( $type, 'poll' ); ?>><?php _e( 'Poll', 'advanced-threads' ); ?></option>
                <option value="guide" <?php selected( $type, 'guide' ); ?>><?php _e( 'Guide/Tutorial', 'advanced-threads' ); ?></option>
            </select>
        </p>
        <p>
            <label for="ats_thread_difficulty"><strong><?php _e( 'Difficulty', 'advanced-threads' ); ?></strong></label><br/>
            <select id="ats_thread_difficulty" name="ats_thread_difficulty">
                <option value=""><?php _e( 'Not specified', 'advanced-threads' ); ?></option>
                <option value="beginner" <?php selected( $difficulty, 'beginner' ); ?>><?php _e( 'Beginner', 'advanced-threads' ); ?></option>
                <option value="intermediate" <?php selected( $difficulty, 'intermediate' ); ?>><?php _e( 'Intermediate', 'advanced-threads' ); ?></option>
                <option value="advanced" <?php selected( $difficulty, 'advanced' ); ?>><?php _e( 'Advanced', 'advanced-threads' ); ?></option>
            </select>
        </p>
        <p>
            <label for="ats_thread_status"><strong><?php _e( 'Status', 'advanced-threads' ); ?></strong></label><br/>
            <select id="ats_thread_status" name="ats_thread_status">
                <option value="open" <?php selected( $status, 'open' ); ?>><?php _e( 'Open', 'advanced-threads' ); ?></option>
                <option value="closed" <?php selected( $status, 'closed' ); ?>><?php _e( 'Closed', 'advanced-threads' ); ?></option>
            </select>
        </p>
        <?php
    }

    /** Save meta */
    public function save_thread_meta( $post_id, $post ) {
        if ( ! isset( $_POST['ats_thread_meta_nonce'] ) || ! wp_verify_nonce( $_POST['ats_thread_meta_nonce'], 'ats_thread_meta_nonce' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( $post->post_type !== $this->post_type ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $type       = isset( $_POST['ats_thread_type'] ) ? sanitize_text_field( $_POST['ats_thread_type'] ) : '';
        $difficulty = isset( $_POST['ats_thread_difficulty'] ) ? sanitize_text_field( $_POST['ats_thread_difficulty'] ) : '';
        $status     = isset( $_POST['ats_thread_status'] ) ? sanitize_text_field( $_POST['ats_thread_status'] ) : 'open';

        update_post_meta( $post_id, '_ats_thread_type', $type );
        update_post_meta( $post_id, '_ats_thread_difficulty', $difficulty );
        update_post_meta( $post_id, '_ats_thread_status', $status );
    }

    /** Admin columns */
    public function add_thread_columns( $columns ) {
        $new = array();
        // Keep checkbox and title first
        if ( isset( $columns['cb'] ) ) {
            $new['cb'] = $columns['cb'];
        }
        $new['title']   = __( 'Title', 'advanced-threads' );
        $new['author']  = __( 'Author', 'advanced-threads' );
        $new['ats_type']   = __( 'Type', 'advanced-threads' );
        $new['ats_status'] = __( 'Status', 'advanced-threads' );
        $new['date']    = $columns['date'];
        return $new;
    }

    public function display_thread_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'ats_type':
                $type = get_post_meta( $post_id, '_ats_thread_type', true );
                echo esc_html( $type ? $type : '—' );
                break;
            case 'ats_status':
                $status = get_post_meta( $post_id, '_ats_thread_status', true );
                echo esc_html( $status ? $status : '—' );
                break;
        }
    }

    public function sortable_thread_columns( $columns ) {
        $columns['ats_type']   = 'ats_type';
        $columns['ats_status'] = 'ats_status';
        return $columns;
    }

    public function handle_thread_sorting( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }
        $orderby = $query->get( 'orderby' );
        if ( 'ats_type' === $orderby ) {
            $query->set( 'meta_key', '_ats_thread_type' );
            $query->set( 'orderby', 'meta_value' );
        } elseif ( 'ats_status' === $orderby ) {
            $query->set( 'meta_key', '_ats_thread_status' );
            $query->set( 'orderby', 'meta_value' );
        }
    }
} // class

} // class_exists guard

// Bootstrap
add_action( 'init', function() {
    ATS_Post_Types::get_instance();
} );
