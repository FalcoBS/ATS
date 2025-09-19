<?php
/**
 * Advanced Threads System - Installer
 * 
 * @package AdvancedThreadsSystem
 * @subpackage Installer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ATS_Installer {
    
    /**
     * Create plugin database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Threads table
        $threads_table = $wpdb->prefix . 'ats_threads';
        $sql_threads = "CREATE TABLE IF NOT EXISTS $threads_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            author_id bigint(20) NOT NULL,
            title text NOT NULL,
            content longtext NOT NULL,
            excerpt text,
            featured_image varchar(255),
            category varchar(100),
            tags text,
            status varchar(20) DEFAULT 'published',
            upvotes int(11) DEFAULT 0,
            downvotes int(11) DEFAULT 0,
            reply_count int(11) DEFAULT 0,
            view_count int(11) DEFAULT 0,
            is_pinned tinyint(1) DEFAULT 0,
            is_locked tinyint(1) DEFAULT 0,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id),
            KEY author_id (author_id),
            KEY category (category),
            KEY status (status),
            KEY created_at (created_at),
            KEY last_activity (last_activity),
            KEY is_pinned (is_pinned),
            FULLTEXT KEY search_content (title, content)
        ) $charset_collate;";
        
        // Replies table
        $replies_table = $wpdb->prefix . 'ats_replies';
        $sql_replies = "CREATE TABLE IF NOT EXISTS $replies_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            thread_id bigint(20) NOT NULL,
            parent_id bigint(20) DEFAULT NULL,
            author_id bigint(20) NOT NULL,
            content longtext NOT NULL,
            attachments text,
            upvotes int(11) DEFAULT 0,
            downvotes int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'published',
            is_solution tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY thread_id (thread_id),
            KEY parent_id (parent_id),
            KEY author_id (author_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY is_solution (is_solution),
            FULLTEXT KEY search_content (content)
        ) $charset_collate;";
        
        // Votes table
        $votes_table = $wpdb->prefix . 'ats_votes';
        $sql_votes = "CREATE TABLE IF NOT EXISTS $votes_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            thread_id bigint(20) DEFAULT NULL,
            reply_id bigint(20) DEFAULT NULL,
            vote_type enum('up','down') NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_vote (user_id, thread_id, reply_id),
            KEY user_id (user_id),
            KEY thread_id (thread_id),
            KEY reply_id (reply_id),
            KEY vote_type (vote_type)
        ) $charset_collate;";
        
        // User profiles table
        $profiles_table = $wpdb->prefix . 'ats_user_profiles';
        $sql_profiles = "CREATE TABLE IF NOT EXISTS $profiles_table (
            user_id bigint(20) NOT NULL,
            display_name varchar(255),
            bio text,
            location varchar(255),
            website varchar(255),
            avatar varchar(255),
            cover_image varchar(255),
            reputation int(11) DEFAULT 0,
            threads_count int(11) DEFAULT 0,
            replies_count int(11) DEFAULT 0,
            likes_received int(11) DEFAULT 0,
            followers_count int(11) DEFAULT 0,
            following_count int(11) DEFAULT 0,
            badge varchar(100),
            title varchar(255),
            social_links text,
            preferences text,
            last_seen datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            KEY reputation (reputation),
            KEY last_seen (last_seen)
        ) $charset_collate;";
        
        // Follows table
        $follows_table = $wpdb->prefix . 'ats_follows';
        $sql_follows = "CREATE TABLE IF NOT EXISTS $follows_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            follower_id bigint(20) NOT NULL,
            following_id bigint(20) NOT NULL,
            following_type enum('user','thread','category') NOT NULL,
            notifications tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_follow (follower_id, following_id, following_type),
            KEY follower_id (follower_id),
            KEY following_id (following_id),
            KEY following_type (following_type)
        ) $charset_collate;";
        
        // Categories table
        $categories_table = $wpdb->prefix . 'ats_categories';
        $sql_categories = "CREATE TABLE IF NOT EXISTS $categories_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            color varchar(7) DEFAULT '#1976d2',
            icon varchar(50),
            parent_id bigint(20) DEFAULT NULL,
            thread_count int(11) DEFAULT 0,
            sort_order int(11) DEFAULT 0,
            is_private tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY parent_id (parent_id),
            KEY sort_order (sort_order)
        ) $charset_collate;";
        
        // Notifications table
        $notifications_table = $wpdb->prefix . 'ats_notifications';
        $sql_notifications = "CREATE TABLE IF NOT EXISTS $notifications_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text,
            action_url varchar(500),
            related_id bigint(20),
            related_type varchar(50),
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Thread views table (for analytics)
        $views_table = $wpdb->prefix . 'ats_thread_views';
        $sql_views = "CREATE TABLE IF NOT EXISTS $views_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            thread_id bigint(20) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45),
            user_agent text,
            viewed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY thread_id (thread_id),
            KEY user_id (user_id),
            KEY viewed_at (viewed_at)
        ) $charset_collate;";
        
        // Reports table (for moderation)
        $reports_table = $wpdb->prefix . 'ats_reports';
        $sql_reports = "CREATE TABLE IF NOT EXISTS $reports_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            reporter_id bigint(20) NOT NULL,
            reported_type enum('thread','reply','user') NOT NULL,
            reported_id bigint(20) NOT NULL,
            reason varchar(100) NOT NULL,
            description text,
            status enum('pending','resolved','dismissed') DEFAULT 'pending',
            moderator_id bigint(20) DEFAULT NULL,
            moderator_note text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY reporter_id (reporter_id),
            KEY reported_type (reported_type),
            KEY reported_id (reported_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Execute table creation
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_threads);
        dbDelta($sql_replies);
        dbDelta($sql_votes);
        dbDelta($sql_profiles);
        dbDelta($sql_follows);
        dbDelta($sql_categories);
        dbDelta($sql_notifications);
        dbDelta($sql_views);
        dbDelta($sql_reports);
        
        // Update database version
        update_option('ats_db_version', ATS_VERSION);
    }
    
    /**
     * Create default pages
     */
    public static function create_pages() {
        $pages = array(
            'threads' => array(
                'title' => __('Threads', 'advanced-threads'),
                'content' => '[ats_threads_listing]',
                'template' => 'threads-listing'
            ),
            'profile' => array(
                'title' => __('User Profile', 'advanced-threads'),
                'content' => '[ats_user_profile]',
                'template' => 'user-profile'
            ),
            'create-thread' => array(
                'title' => __('Create Thread', 'advanced-threads'),
                'content' => '[ats_create_thread_form]',
                'template' => 'create-thread'
            ),
            'leaderboard' => array(
                'title' => __('Leaderboard', 'advanced-threads'),
                'content' => '[ats_leaderboard]',
                'template' => 'leaderboard'
            )
        );
        
        foreach ($pages as $slug => $page_data) {
            // Check if page already exists
            $existing_page = get_page_by_path($slug);
            
            if (!$existing_page) {
                $page_id = wp_insert_post(array(
                    'post_title' => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_name' => $slug,
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_author' => get_current_user_id()
                ));
                
                if ($page_id && !is_wp_error($page_id)) {
                    // Save page ID in options
                    update_option('ats_page_' . str_replace('-', '_', $slug), $page_id);
                    
                    // Add custom template meta
                    update_post_meta($page_id, '_ats_template', $page_data['template']);
                }
            } else {
                // Update existing page ID in options
                update_option('ats_page_' . str_replace('-', '_', $slug), $existing_page->ID);
            }
        }
    }
    
    /**
     * Set default plugin options
     */
    public static function set_default_options() {
        $default_options = array(
            // General settings
            'ats_threads_per_page' => 20,
            'ats_replies_per_page' => 50,
            'ats_enable_voting' => 1,
            'ats_enable_following' => 1,
            'ats_enable_notifications' => 1,
            
            // Permissions
            'ats_who_can_create_threads' => 'subscriber',
            'ats_who_can_reply' => 'subscriber',
            'ats_who_can_vote' => 'subscriber',
            'ats_require_approval' => 0,
            'ats_enable_anonymous_posting' => 0,
            
            // Content settings
            'ats_max_thread_title_length' => 200,
            'ats_max_content_length' => 10000,
            'ats_enable_rich_editor' => 1,
            'ats_enable_image_uploads' => 1,
            'ats_max_image_size' => 2048, // KB
            'ats_allowed_file_types' => 'jpg,jpeg,png,gif,pdf,doc,docx',
            
            // Display settings
            'ats_default_sort_order' => 'newest',
            'ats_show_author_info' => 1,
            'ats_show_vote_counts' => 1,
            'ats_enable_thread_previews' => 1,
            'ats_threads_layout' => 'card',
            
            // Reputation settings
            'ats_points_new_thread' => 10,
            'ats_points_new_reply' => 5,
            'ats_points_upvote_received' => 2,
            'ats_points_downvote_received' => -1,
            'ats_points_solution_marked' => 15,
            
            // Notification settings
            'ats_notify_new_reply' => 1,
            'ats_notify_new_follower' => 1,
            'ats_notify_thread_upvote' => 1,
            'ats_email_notifications' => 1,
            
            // Moderation settings
            'ats_enable_auto_moderation' => 0,
            'ats_spam_threshold' => 5,
            'ats_enable_user_reporting' => 1,
            'ats_blocked_words' => '',
            
            // SEO settings
            'ats_enable_seo' => 1,
            'ats_meta_description_length' => 160,
            'ats_enable_breadcrumbs' => 1,
            'ats_enable_structured_data' => 1
        );
        
        foreach ($default_options as $option_name => $option_value) {
            if (!get_option($option_name)) {
                add_option($option_name, $option_value);
            }
        }
        
        // Create default categories
        self::create_default_categories();
        
        // Set version
        update_option('ats_version', ATS_VERSION);
        update_option('ats_activation_date', current_time('mysql'));
    }
    
    /**
     * Create default categories
     */
    private static function create_default_categories() {
        global $wpdb;
        
        $categories_table = $wpdb->prefix . 'ats_categories';
        
        // Check if categories already exist
        $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM $categories_table");
        
        if ($existing_count == 0) {
            $default_categories = array(
                array(
                    'name' => __('General Discussion', 'advanced-threads'),
                    'slug' => 'general-discussion',
                    'description' => __('General topics and discussions', 'advanced-threads'),
                    'color' => '#1976d2',
                    'icon' => 'chat',
                    'sort_order' => 1
                ),
                array(
                    'name' => __('Questions & Answers', 'advanced-threads'),
                    'slug' => 'questions-answers',
                    'description' => __('Ask questions and get help from the community', 'advanced-threads'),
                    'color' => '#388e3c',
                    'icon' => 'help-circle',
                    'sort_order' => 2
                ),
                array(
                    'name' => __('Announcements', 'advanced-threads'),
                    'slug' => 'announcements',
                    'description' => __('Important announcements and updates', 'advanced-threads'),
                    'color' => '#f57c00',
                    'icon' => 'megaphone',
                    'sort_order' => 3
                ),
                array(
                    'name' => __('Feature Requests', 'advanced-threads'),
                    'slug' => 'feature-requests',
                    'description' => __('Suggest new features and improvements', 'advanced-threads'),
                    'color' => '#7b1fa2',
                    'icon' => 'lightbulb',
                    'sort_order' => 4
                )
            );
            
            foreach ($default_categories as $category) {
                $wpdb->insert(
                    $categories_table,
                    $category,
                    array('%s', '%s', '%s', '%s', '%s', '%d')
                );
            }
        }
    }
    
    /**
     * Check if database needs update
     */
    public static function maybe_update_database() {
        $current_version = get_option('ats_db_version');
        
        if (version_compare($current_version, ATS_VERSION, '<')) {
            self::update_database($current_version);
        }
    }
    
    /**
     * Update database schema
     */
    private static function update_database($from_version) {
        // Future database updates will go here
        // Example:
        /*
        if (version_compare($from_version, '1.1.0', '<')) {
            // Add new columns or tables for version 1.1.0
            global $wpdb;
            
            $table = $wpdb->prefix . 'ats_threads';
            $wpdb->query("ALTER TABLE $table ADD COLUMN new_field varchar(255) DEFAULT NULL");
        }
        */
        
        // Update version
        update_option('ats_db_version', ATS_VERSION);
    }
    
    /**
     * Create user profile on user registration
     */
    public static function create_user_profile($user_id) {
        global $wpdb;
        
        $user = get_user_by('ID', $user_id);
        if (!$user) return;
        
        $profiles_table = $wpdb->prefix . 'ats_user_profiles';
        
        // Check if profile already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $profiles_table WHERE user_id = %d",
            $user_id
        ));
        
        if (!$existing) {
            $wpdb->insert(
                $profiles_table,
                array(
                    'user_id' => $user_id,
                    'display_name' => $user->display_name ?: $user->user_login,
                    'avatar' => '',
                    'bio' => '',
                    'reputation' => 0,
                    'threads_count' => 0,
                    'replies_count' => 0,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s')
            );
        }
    }
}

// Hook into user registration
add_action('user_register', array('ATS_Installer', 'create_user_profile'));

// Check for database updates on admin_init
add_action('admin_init', array('ATS_Installer', 'maybe_update_database'));
?>