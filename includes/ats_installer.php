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
     * Install the plugin
     */
    public function install() {
        $this->create_tables();
        $this->create_default_categories();
        $this->create_default_pages();
        $this->set_default_options();
        $this->create_capabilities();
        $this->schedule_events();
        
        // Update version
        update_option('ats_version', ATS_VERSION);
        update_option('ats_install_date', current_time('mysql'));
    }
    
    /**
     * Update the plugin
     */
    public function update($from_version) {
        // Run version-specific updates
        if (version_compare($from_version, '1.0.0', '<')) {
            $this->update_to_1_0_0();
        }
        
        // Always check for table updates
        $this->create_tables();
        
        // Update version
        update_option('ats_version', ATS_VERSION);
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Threads table
        $threads_sql = "CREATE TABLE " . ATS_THREADS_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            content longtext NOT NULL,
            author_id bigint(20) unsigned NOT NULL,
            category varchar(100) DEFAULT '',
            tags text DEFAULT '',
            featured_image varchar(255) DEFAULT '',
            view_count bigint(20) unsigned DEFAULT 0,
            reply_count bigint(20) unsigned DEFAULT 0,
            upvotes bigint(20) unsigned DEFAULT 0,
            downvotes bigint(20) unsigned DEFAULT 0,
            is_pinned tinyint(1) DEFAULT 0,
            is_locked tinyint(1) DEFAULT 0,
            is_featured tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'published',
            last_activity_at datetime NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY author_id (author_id),
            KEY category (category),
            KEY status (status),
            KEY last_activity_at (last_activity_at),
            KEY created_at (created_at),
            KEY is_pinned (is_pinned),
            KEY is_featured (is_featured),
            KEY view_count (view_count)
        ) $charset_collate;";
        
        // Replies table
        $replies_sql = "CREATE TABLE " . ATS_REPLIES_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            thread_id bigint(20) unsigned NOT NULL,
            parent_id bigint(20) unsigned DEFAULT NULL,
            author_id bigint(20) unsigned NOT NULL,
            content longtext NOT NULL,
            upvotes bigint(20) unsigned DEFAULT 0,
            downvotes bigint(20) unsigned DEFAULT 0,
            is_solution tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'published',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY thread_id (thread_id),
            KEY parent_id (parent_id),
            KEY author_id (author_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY is_solution (is_solution)
        ) $charset_collate;";
        
        // Votes table
        $votes_sql = "CREATE TABLE " . ATS_VOTES_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            thread_id bigint(20) unsigned DEFAULT NULL,
            reply_id bigint(20) unsigned DEFAULT NULL,
            vote_type enum('up','down') NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_thread_vote (user_id, thread_id),
            UNIQUE KEY user_reply_vote (user_id, reply_id),
            KEY user_id (user_id),
            KEY thread_id (thread_id),
            KEY reply_id (reply_id)
        ) $charset_collate;";
        
        // User profiles table
        $profiles_sql = "CREATE TABLE " . ATS_USER_PROFILES_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            display_name varchar(100) NOT NULL,
            bio text DEFAULT '',
            avatar varchar(255) DEFAULT '',
            website varchar(255) DEFAULT '',
            location varchar(100) DEFAULT '',
            reputation_points bigint(20) DEFAULT 0,
            thread_count bigint(20) unsigned DEFAULT 0,
            reply_count bigint(20) unsigned DEFAULT 0,
            upvote_count bigint(20) unsigned DEFAULT 0,
            downvote_count bigint(20) unsigned DEFAULT 0,
            badge_count bigint(20) unsigned DEFAULT 0,
            last_seen datetime DEFAULT NULL,
            is_banned tinyint(1) DEFAULT 0,
            ban_reason text DEFAULT '',
            ban_expires datetime DEFAULT NULL,
            email_notifications tinyint(1) DEFAULT 1,
            push_notifications tinyint(1) DEFAULT 1,
            privacy_profile enum('public','private','friends') DEFAULT 'public',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY reputation_points (reputation_points),
            KEY last_seen (last_seen),
            KEY is_banned (is_banned)
        ) $charset_collate;";
        
        // Categories table
        $categories_sql = "CREATE TABLE " . ATS_CATEGORIES_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            description text DEFAULT '',
            color varchar(7) DEFAULT '#007cba',
            icon varchar(50) DEFAULT '',
            parent_id bigint(20) unsigned DEFAULT NULL,
            thread_count bigint(20) unsigned DEFAULT 0,
            sort_order int(11) DEFAULT 0,
            is_private tinyint(1) DEFAULT 0,
            required_role varchar(50) DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY parent_id (parent_id),
            KEY sort_order (sort_order),
            KEY is_private (is_private)
        ) $charset_collate;";
        
        // Notifications table
        $notifications_sql = "CREATE TABLE " . ATS_NOTIFICATIONS_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            action_url varchar(255) DEFAULT '',
            is_read tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL,
            read_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Follows table
        $follows_sql = "CREATE TABLE " . ATS_FOLLOWS_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            thread_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_thread_follow (user_id, thread_id),
            KEY user_id (user_id),
            KEY thread_id (thread_id)
        ) $charset_collate;";
        
        // Badges table
        $badges_sql = "CREATE TABLE " . ATS_BADGES_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            description text NOT NULL,
            icon varchar(50) NOT NULL,
            color varchar(7) DEFAULT '#007cba',
            type enum('bronze','silver','gold','platinum') DEFAULT 'bronze',
            criteria text NOT NULL,
            points_required int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY type (type),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // User badges table
        $user_badges_sql = "CREATE TABLE " . ATS_USER_BADGES_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            badge_id bigint(20) unsigned NOT NULL,
            earned_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_badge (user_id, badge_id),
            KEY user_id (user_id),
            KEY badge_id (badge_id),
            KEY earned_at (earned_at)
        ) $charset_collate;";
        
        // Reports table
        $reports_sql = "CREATE TABLE " . ATS_REPORTS_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            reporter_id bigint(20) unsigned NOT NULL,
            thread_id bigint(20) unsigned DEFAULT NULL,
            reply_id bigint(20) unsigned DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            type enum('spam','inappropriate','harassment','other') NOT NULL,
            reason text NOT NULL,
            status enum('pending','reviewed','resolved','dismissed') DEFAULT 'pending',
            moderator_id bigint(20) unsigned DEFAULT NULL,
            moderator_notes text DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY reporter_id (reporter_id),
            KEY thread_id (thread_id),
            KEY reply_id (reply_id),
            KEY user_id (user_id),
            KEY type (type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($threads_sql);
        dbDelta($replies_sql);
        dbDelta($votes_sql);
        dbDelta($profiles_sql);
        dbDelta($categories_sql);
        dbDelta($notifications_sql);
        dbDelta($follows_sql);
        dbDelta($badges_sql);
        dbDelta($user_badges_sql);
        dbDelta($reports_sql);
    }
    
    /**
     * Create default categories
     */
    private function create_default_categories() {
        global $wpdb;
        
        $default_categories = array(
            array(
                'name' => __('General Discussion', 'advanced-threads'),
                'slug' => 'general-discussion',
                'description' => __('General topics and discussions', 'advanced-threads'),
                'color' => '#007cba',
                'icon' => 'message-circle',
                'sort_order' => 1
            ),
            array(
                'name' => __('Questions & Answers', 'advanced-threads'),
                'slug' => 'questions-answers',
                'description' => __('Ask questions and get answers from the community', 'advanced-threads'),
                'color' => '#00a32a',
                'icon' => 'help-circle',
                'sort_order' => 2
            ),
            array(
                'name' => __('Announcements', 'advanced-threads'),
                'slug' => 'announcements',
                'description' => __('Important announcements and updates', 'advanced-threads'),
                'color' => '#d63638',
                'icon' => 'megaphone',
                'sort_order' => 3
            ),
            array(
                'name' => __('Feature Requests', 'advanced-threads'),
                'slug' => 'feature-requests',
                'description' => __('Suggest new features and improvements', 'advanced-threads'),
                'color' => '#f56e28',
                'icon' => 'lightbulb',
                'sort_order' => 4
            ),
            array(
                'name' => __('Bug Reports', 'advanced-threads'),
                'slug' => 'bug-reports',
                'description' => __('Report bugs and technical issues', 'advanced-threads'),
                'color' => '#8c8f94',
                'icon' => 'bug',
                'sort_order' => 5
            )
        );
        
        foreach ($default_categories as $category) {
            // Check if category already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM " . ATS_CATEGORIES_TABLE . " WHERE slug = %s",
                $category['slug']
            ));
            
            if (!$exists) {
                $wpdb->insert(
                    ATS_CATEGORIES_TABLE,
                    array_merge($category, array(
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ))
                );
            }
        }
    }
    
    /**
     * Create default pages
     */
    private function create_default_pages() {
        // Threads archive page
        $threads_page = get_page_by_path('threads');
        if (!$threads_page) {
            $threads_page_id = wp_insert_post(array(
                'post_title' => __('Threads', 'advanced-threads'),
                'post_content' => '[ats_threads_archive]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'threads'
            ));
            
            if ($threads_page_id && !is_wp_error($threads_page_id)) {
                update_option('ats_page_threads', $threads_page_id);
            }
        } else {
            update_option('ats_page_threads', $threads_page->ID);
        }
        
        // Create thread page
        $create_page = get_page_by_path('create-thread');
        if (!$create_page) {
            $create_page_id = wp_insert_post(array(
                'post_title' => __('Create Thread', 'advanced-threads'),
                'post_content' => '[ats_create_thread_form]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'create-thread'
            ));
            
            if ($create_page_id && !is_wp_error($create_page_id)) {
                update_option('ats_page_create_thread', $create_page_id);
            }
        } else {
            update_option('ats_page_create_thread', $create_page->ID);
        }
        
        // User profiles page
        $profiles_page = get_page_by_path('user-profiles');
        if (!$profiles_page) {
            $profiles_page_id = wp_insert_post(array(
                'post_title' => __('User Profiles', 'advanced-threads'),
                'post_content' => '[ats_user_profiles]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'user-profiles'
            ));
            
            if ($profiles_page_id && !is_wp_error($profiles_page_id)) {
                update_option('ats_page_user_profiles', $profiles_page_id);
            }
        } else {
            update_option('ats_page_user_profiles', $profiles_page->ID);
        }
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        $default_options = array(
            // General settings
            'ats_enable_threading' => 1,
            'ats_enable_voting' => 1,
            'ats_enable_following' => 1,
            'ats_enable_notifications' => 1,
            'ats_enable_user_profiles' => 1,
            'ats_enable_reputation_system' => 1,
            'ats_enable_badges' => 1,
            
            // Content settings
            'ats_threads_per_page' => 20,
            'ats_replies_per_page' => 50,
            'ats_max_content_length' => 10000,
            'ats_min_content_length' => 10,
            'ats_enable_rich_editor' => 1,
            'ats_enable_image_uploads' => 1,
            'ats_max_image_size' => 2048,
            'ats_allowed_image_types' => 'jpg,jpeg,png,gif,webp',
            
            // Moderation settings
            'ats_require_approval' => 0,
            'ats_enable_auto_moderation' => 1,
            'ats_spam_detection' => 1,
            'ats_max_links_per_post' => 3,
            'ats_moderate_first_posts' => 1,
            
            // User settings
            'ats_allow_guest_viewing' => 1,
            'ats_require_login_to_post' => 1,
            'ats_minimum_role_create_threads' => 'subscriber',
            'ats_minimum_role_reply' => 'subscriber',
            'ats_minimum_role_vote' => 'subscriber',
            
            // Email settings
            'ats_send_welcome_notification' => 1,
            'ats_email_new_threads' => 0,
            'ats_email_new_replies' => 1,
            'ats_email_mentions' => 1,
            'ats_email_weekly_digest' => 0,
            
            // Performance settings
            'ats_enable_caching' => 1,
            'ats_cache_duration' => 3600,
            'ats_enable_lazy_loading' => 1,
            'ats_optimize_images' => 1,
            
            // Display settings
            'ats_enable_dark_mode' => 1,
            'ats_show_user_avatars' => 1,
            'ats_show_online_status' => 1,
            'ats_show_last_activity' => 1,
            'ats_date_format' => 'relative',
            
            // Security settings
            'ats_enable_captcha' => 0,
            'ats_enable_rate_limiting' => 1,
            'ats_max_posts_per_hour' => 10,
            'ats_enable_ip_blocking' => 0,
            
            // Integration settings
            'ats_enable_social_login' => 0,
            'ats_enable_seo_optimization' => 1,
            'ats_enable_schema_markup' => 1,
            
            // Advanced settings
            'ats_enable_live_updates' => 1,
            'ats_websocket_server' => '',
            'ats_cdn_url' => '',
            'ats_custom_css' => '',
            'ats_custom_js' => '',
            
            // Privacy settings
            'ats_data_retention_days' => 365,
            'ats_anonymize_deleted_content' => 1,
            'ats_gdpr_compliance' => 1,
            'ats_cookie_consent' => 0,
            
            // Uninstall settings
            'ats_remove_data_on_uninstall' => 0
        );
        
        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }
    }
    
    /**
     * Create default badges
     */
    private function create_default_badges() {
        global $wpdb;
        
        $default_badges = array(
            array(
                'name' => __('First Post', 'advanced-threads'),
                'description' => __('Created your first thread or reply', 'advanced-threads'),
                'icon' => 'edit',
                'color' => '#cd7f32',
                'type' => 'bronze',
                'criteria' => 'first_post',
                'points_required' => 0
            ),
            array(
                'name' => __('Active Participant', 'advanced-threads'),
                'description' => __('Posted 10 threads or replies', 'advanced-threads'),
                'icon' => 'message-square',
                'color' => '#cd7f32',
                'type' => 'bronze',
                'criteria' => 'post_count_10',
                'points_required' => 0
            ),
            array(
                'name' => __('Helpful', 'advanced-threads'),
                'description' => __('Received 25 upvotes', 'advanced-threads'),
                'icon' => 'thumbs-up',
                'color' => '#c0c0c0',
                'type' => 'silver',
                'criteria' => 'upvotes_25',
                'points_required' => 0
            ),
            array(
                'name' => __('Popular', 'advanced-threads'),
                'description' => __('Thread received 100 views', 'advanced-threads'),
                'icon' => 'eye',
                'color' => '#c0c0c0',
                'type' => 'silver',
                'criteria' => 'thread_views_100',
                'points_required' => 0
            ),
            array(
                'name' => __('Expert', 'advanced-threads'),
                'description' => __('Received 100 upvotes', 'advanced-threads'),
                'icon' => 'award',
                'color' => '#ffd700',
                'type' => 'gold',
                'criteria' => 'upvotes_100',
                'points_required' => 0
            ),
            array(
                'name' => __('Community Leader', 'advanced-threads'),
                'description' => __('Posted 100 threads or replies', 'advanced-threads'),
                'icon' => 'users',
                'color' => '#ffd700',
                'type' => 'gold',
                'criteria' => 'post_count_100',
                'points_required' => 0
            ),
            array(
                'name' => __('Legend', 'advanced-threads'),
                'description' => __('Received 500 upvotes', 'advanced-threads'),
                'icon' => 'star',
                'color' => '#e5e4e2',
                'type' => 'platinum',
                'criteria' => 'upvotes_500',
                'points_required' => 0
            )
        );
        
        foreach ($default_badges as $badge) {
            // Check if badge already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM " . ATS_BADGES_TABLE . " WHERE name = %s",
                $badge['name']
            ));
            
            if (!$exists) {
                $wpdb->insert(
                    ATS_BADGES_TABLE,
                    array_merge($badge, array(
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ))
                );
            }
        }
    }
    
    /**
     * Create user capabilities
     */
    private function create_capabilities() {
        // Get roles
        $admin = get_role('administrator');
        $editor = get_role('editor');
        $author = get_role('author');
        $contributor = get_role('contributor');
        $subscriber = get_role('subscriber');
        
        // Admin capabilities
        if ($admin) {
            $admin->add_cap('ats_manage_settings');
            $admin->add_cap('ats_manage_categories');
            $admin->add_cap('ats_moderate_threads');
            $admin->add_cap('ats_moderate_replies');
            $admin->add_cap('ats_manage_users');
            $admin->add_cap('ats_view_reports');
            $admin->add_cap('ats_create_threads');
            $admin->add_cap('ats_reply_threads');
            $admin->add_cap('ats_vote');
            $admin->add_cap('ats_follow_threads');
        }
        
        // Editor capabilities
        if ($editor) {
            $editor->add_cap('ats_moderate_threads');
            $editor->add_cap('ats_moderate_replies');
            $editor->add_cap('ats_view_reports');
            $editor->add_cap('ats_create_threads');
            $editor->add_cap('ats_reply_threads');
            $editor->add_cap('ats_vote');
            $editor->add_cap('ats_follow_threads');
        }
        
        // Author capabilities
        if ($author) {
            $author->add_cap('ats_create_threads');
            $author->add_cap('ats_reply_threads');
            $author->add_cap('ats_vote');
            $author->add_cap('ats_follow_threads');
        }
        
        // Contributor capabilities
        if ($contributor) {
            $contributor->add_cap('ats_create_threads');
            $contributor->add_cap('ats_reply_threads');
            $contributor->add_cap('ats_vote');
            $contributor->add_cap('ats_follow_threads');
        }
        
        // Subscriber capabilities
        if ($subscriber) {
            $subscriber->add_cap('ats_create_threads');
            $subscriber->add_cap('ats_reply_threads');
            $subscriber->add_cap('ats_vote');
            $subscriber->add_cap('ats_follow_threads');
        }
    }
    
    /**
     * Schedule recurring events
     */
    private function schedule_events() {
        // Daily cleanup
        if (!wp_next_scheduled('ats_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'ats_daily_cleanup');
        }
        
        // Weekly digest
        if (!wp_next_scheduled('ats_weekly_digest')) {
            wp_schedule_event(time(), 'weekly', 'ats_weekly_digest');
        }
        
        // Hourly badge checks
        if (!wp_next_scheduled('ats_badge_check')) {
            wp_schedule_event(time(), 'hourly', 'ats_badge_check');
        }
        
        // Daily reputation calculation
        if (!wp_next_scheduled('ats_reputation_update')) {
            wp_schedule_event(time(), 'daily', 'ats_reputation_update');
        }
    }
    
    /**
     * Update to version 1.0.0
     */
    private function update_to_1_0_0() {
        // Add any version-specific updates here
        $this->create_default_badges();
        
        // Update existing user profiles
        $this->update_existing_user_profiles();
        
        // Migrate old data if needed
        $this->migrate_legacy_data();
    }
    
    /**
     * Update existing user profiles
     */
    private function update_existing_user_profiles() {
        global $wpdb;
        
        // Get all WordPress users
        $users = get_users(array('fields' => 'ID'));
        
        foreach ($users as $user_id) {
            // Check if profile already exists
            $profile_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM " . ATS_USER_PROFILES_TABLE . " WHERE user_id = %d",
                $user_id
            ));
            
            if (!$profile_exists) {
                $user = get_userdata($user_id);
                
                $wpdb->insert(
                    ATS_USER_PROFILES_TABLE,
                    array(
                        'user_id' => $user_id,
                        'display_name' => $user->display_name,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    )
                );
            }
        }
    }
    
    /**
     * Migrate legacy data
     */
    private function migrate_legacy_data() {
        // Placeholder for any legacy data migration
        // This would be used when upgrading from older versions
    }
    
    /**
     * Create sample content (for development/demo purposes)
     */
    public function create_sample_content() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Create sample threads
        $this->create_sample_threads();
        
        // Create sample replies
        $this->create_sample_replies();
        
        // Award sample badges
        $this->award_sample_badges();
    }
    
    /**
     * Create sample threads
     */
    private function create_sample_threads() {
        $sample_threads = array(
            array(
                'title' => __('Welcome to Advanced Threads System!', 'advanced-threads'),
                'content' => __('This is a sample thread to demonstrate the Advanced Threads System. Feel free to reply and test out the features!', 'advanced-threads'),
                'category' => 'general-discussion'
            ),
            array(
                'title' => __('How to create your first thread?', 'advanced-threads'),
                'content' => __('Here\'s a quick guide on how to create your first thread in the system. Simply click the "Create Thread" button and fill out the form.', 'advanced-threads'),
                'category' => 'questions-answers'
            ),
            array(
                'title' => __('New Features Coming Soon', 'advanced-threads'),
                'content' => __('We\'re excited to announce some new features coming to Advanced Threads System in the next release!', 'advanced-threads'),
                'category' => 'announcements'
            )
        );
        
        // Get admin user
        $admin_users = get_users(array('role' => 'administrator', 'number' => 1));
        $admin_id = !empty($admin_users) ? $admin_users[0]->ID : 1;
        
        foreach ($sample_threads as $thread_data) {
            // Create WordPress post
            $post_id = wp_insert_post(array(
                'post_title' => $thread_data['title'],
                'post_content' => $thread_data['content'],
                'post_status' => 'publish',
                'post_type' => 'ats_thread',
                'post_author' => $admin_id
            ));
            
            if ($post_id && !is_wp_error($post_id)) {
                // Create thread record
                global $wpdb;
                $wpdb->insert(
                    ATS_THREADS_TABLE,
                    array(
                        'post_id' => $post_id,
                        'title' => $thread_data['title'],
                        'content' => $thread_data['content'],
                        'author_id' => $admin_id,
                        'category' => $thread_data['category'],
                        'status' => 'published',
                        'last_activity_at' => current_time('mysql'),
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    )
                );
            }
        }
    }
    
    /**
     * Create sample replies
     */
    private function create_sample_replies() {
        global $wpdb;
        
        // Get first thread
        $thread = $wpdb->get_row("SELECT * FROM " . ATS_THREADS_TABLE . " LIMIT 1");
        
        if ($thread) {
            $admin_users = get_users(array('role' => 'administrator', 'number' => 1));
            $admin_id = !empty($admin_users) ? $admin_users[0]->ID : 1;
            
            $sample_replies = array(
                __('Great to see this system in action! The interface looks very user-friendly.', 'advanced-threads'),
                __('I\'m impressed with the voting system and user profiles. Well done!', 'advanced-threads'),
                __('Looking forward to using this for our community discussions.', 'advanced-threads')
            );
            
            foreach ($sample_replies as $content) {
                $wpdb->insert(
                    ATS_REPLIES_TABLE,
                    array(
                        'thread_id' => $thread->id,
                        'author_id' => $admin_id,
                        'content' => $content,
                        'status' => 'published',
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    )
                );
                
                // Update thread reply count
                $wpdb->update(
                    ATS_THREADS_TABLE,
                    array(
                        'reply_count' => $thread->reply_count + 1,
                        'last_activity_at' => current_time('mysql')
                    ),
                    array('id' => $thread->id)
                );
            }
        }
    }
    
    /**
     * Award sample badges
     */
    private function award_sample_badges() {
        global $wpdb;
        
        $admin_users = get_users(array('role' => 'administrator', 'number' => 1));
        if (empty($admin_users)) {
            return;
        }
        
        $admin_id = $admin_users[0]->ID;
        
        // Get "First Post" badge
        $badge = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . ATS_BADGES_TABLE . " WHERE criteria = %s LIMIT 1",
            'first_post'
        ));
        
        if ($badge) {
            // Check if user already has this badge
            $has_badge = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM " . ATS_USER_BADGES_TABLE . " WHERE user_id = %d AND badge_id = %d",
                $admin_id,
                $badge->id
            ));
            
            if (!$has_badge) {
                $wpdb->insert(
                    ATS_USER_BADGES_TABLE,
                    array(
                        'user_id' => $admin_id,
                        'badge_id' => $badge->id,
                        'earned_at' => current_time('mysql')
                    )
                );
                
                // Update user profile badge count
                $wpdb->query($wpdb->prepare(
                    "UPDATE " . ATS_USER_PROFILES_TABLE . " 
                     SET badge_count = badge_count + 1 
                     WHERE user_id = %d",
                    $admin_id
                ));
            }
        }
    }
}