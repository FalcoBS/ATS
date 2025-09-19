<?php
/**
 * Advanced Threads System - Core Class
 * 
 * @package AdvancedThreadsSystem
 * @subpackage Core
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ATS_Core {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->init_managers();
    }
    
    private function init_hooks() {
        // Plugin lifecycle hooks
        add_action('wp_loaded', array($this, 'check_dependencies'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_head', array($this, 'add_meta_tags'));
        
        // Template hooks
        add_filter('template_include', array($this, 'load_templates'));
        add_filter('the_content', array($this, 'filter_thread_content'));
        
        // User hooks
        add_action('wp_login', array($this, 'update_last_seen'), 10, 2);
        add_action('wp_logout', array($this, 'update_last_seen_logout'));
        
        // Rewrite rules
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // Cron jobs
        add_action('ats_daily_cleanup', array($this, 'daily_cleanup'));
        if (!wp_next_scheduled('ats_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'ats_daily_cleanup');
        }
    }
    
    private function init_managers() {
        // Initialize manager classes with error handling
        try {
            $this->thread_manager = new ATS_Thread_Manager();
            $this->user_manager = new ATS_User_Manager();
            $this->vote_manager = new ATS_Vote_Manager();
        } catch (Exception $e) {
            error_log('ATS Core: Failed to initialize managers - ' . $e->getMessage());
        }
    }
    
    /**
     * Check plugin dependencies
     */
    public function check_dependencies() {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo __('Advanced Threads System requires WordPress 5.0 or higher.', 'advanced-threads');
                echo '</p></div>';
            });
            return false;
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo __('Advanced Threads System requires PHP 7.4 or higher.', 'advanced-threads');
                echo '</p></div>';
            });
            return false;
        }
        
        return true;
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Check if we're on a threads page
        if (!$this->is_threads_page()) {
            return;
        }
        
        // Main CSS
        wp_enqueue_style(
            'ats-frontend',
            ATS_PLUGIN_URL . 'assets/css/threads-frontend.css',
            array(),
            ATS_VERSION
        );
        
        // Responsive CSS
        wp_enqueue_style(
            'ats-responsive',
            ATS_PLUGIN_URL . 'assets/css/threads-responsive.css',
            array('ats-frontend'),
            ATS_VERSION
        );
        
        // Main JavaScript
        wp_enqueue_script(
            'ats-frontend',
            ATS_PLUGIN_URL . 'assets/js/threads-frontend.js',
            array('jquery'),
            ATS_VERSION,
            true
        );
        
        // Editor JavaScript (if rich editor is enabled)
        if (get_option('ats_enable_rich_editor', 1)) {
            wp_enqueue_script(
                'ats-editor',
                ATS_PLUGIN_URL . 'assets/js/threads-editor.js',
                array('ats-frontend'),
                ATS_VERSION,
                true
            );
        }
        
        // Localize script with data
        wp_localize_script('ats-frontend', 'ats_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ats_nonce'),
            'user_id' => get_current_user_id(),
            'is_logged_in' => is_user_logged_in(),
            'settings' => array(
                'threads_per_page' => get_option('ats_threads_per_page', 20),
                'replies_per_page' => get_option('ats_replies_per_page', 50),
                'enable_voting' => get_option('ats_enable_voting', 1),
                'enable_following' => get_option('ats_enable_following', 1),
                'max_content_length' => get_option('ats_max_content_length', 10000),
                'enable_rich_editor' => get_option('ats_enable_rich_editor', 1),
                'enable_image_uploads' => get_option('ats_enable_image_uploads', 1)
            ),
            'strings' => array(
                'loading' => __('Loading...', 'advanced-threads'),
                'error' => __('An error occurred', 'advanced-threads'),
                'success' => __('Success!', 'advanced-threads'),
                'confirm_delete' => __('Are you sure you want to delete this?', 'advanced-threads'),
                'login_required' => __('You must be logged in to perform this action', 'advanced-threads'),
                'vote_success' => __('Vote recorded', 'advanced-threads'),
                'follow_success' => __('Now following', 'advanced-threads'),
                'unfollow_success' => __('Unfollowed', 'advanced-threads'),
                'reply_posted' => __('Reply posted successfully', 'advanced-threads'),
                'content_too_long' => __('Content is too long', 'advanced-threads'),
                'invalid_file_type' => __('Invalid file type', 'advanced-threads'),
                'file_too_large' => __('File is too large', 'advanced-threads')
            )
        ));
    }
    
    /**
     * Add custom rewrite rules
     */
    public function add_rewrite_rules() {
        // Thread single view
        add_rewrite_rule(
            '^threads/([^/]+)/?$',
            'index.php?post_type=ats_thread&name=$matches[1]',
            'top'
        );
        
        // Thread category archive
        add_rewrite_rule(
            '^threads/category/([^/]+)/?$',
            'index.php?ats_category=$matches[1]',
            'top'
        );
        
        // Thread category with pagination
        add_rewrite_rule(
            '^threads/category/([^/]+)/page/([0-9]{1,})/?$',
            'index.php?ats_category=$matches[1]&paged=$matches[2]',
            'top'
        );
        
        // User profile
        add_rewrite_rule(
            '^profile/([^/]+)/?$',
            'index.php?ats_user_profile=$matches[1]',
            'top'
        );
        
        // User profile tabs
        add_rewrite_rule(
            '^profile/([^/]+)/([^/]+)/?$',
            'index.php?ats_user_profile=$matches[1]&ats_profile_tab=$matches[2]',
            'top'
        );
        
        // Search results
        add_rewrite_rule(
            '^threads/search/([^/]+)/?$',
            'index.php?ats_search=$matches[1]',
            'top'
        );
        
        // Tag archive
        add_rewrite_rule(
            '^threads/tag/([^/]+)/?$',
            'index.php?ats_tag=$matches[1]',
            'top'
        );
    }
    
    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'ats_category';
        $vars[] = 'ats_user_profile';
        $vars[] = 'ats_profile_tab';
        $vars[] = 'ats_search';
        $vars[] = 'ats_tag';
        return $vars;
    }
    
    /**
     * Load custom templates
     */
    public function load_templates($template) {
        // Check for custom query vars
        if (get_query_var('ats_category')) {
            $custom_template = $this->locate_template('archive-thread-category.php');
            return $custom_template ?: $template;
        }
        
        if (get_query_var('ats_user_profile')) {
            $custom_template = $this->locate_template('user-profile.php');
            return $custom_template ?: $template;
        }
        
        if (get_query_var('ats_search')) {
            $custom_template = $this->locate_template('search-threads.php');
            return $custom_template ?: $template;
        }
        
        // Check for single thread template
        if (is_singular('ats_thread')) {
            $custom_template = $this->locate_template('single-thread.php');
            return $custom_template ?: $template;
        }
        
        // Check for thread archive template
        if (is_post_type_archive('ats_thread')) {
            $custom_template = $this->locate_template('archive-thread.php');
            return $custom_template ?: $template;
        }
        
        return $template;
    }
    
    /**
     * Locate template file
     */
    private function locate_template($template_name) {
        // Check theme directory first
        $theme_template = locate_template(array(
            'advanced-threads/' . $template_name,
            'ats-templates/' . $template_name,
            $template_name
        ));
        
        if ($theme_template) {
            return $theme_template;
        }
        
        // Check plugin templates directory
        $plugin_template = ATS_PLUGIN_PATH . 'templates/' . $template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
        
        return false;
    }
    
    /**
     * Filter thread content
     */
    public function filter_thread_content($content) {
        if (!is_singular('ats_thread')) {
            return $content;
        }
        
        global $post;
        
        // Get thread data
        $thread_data = $this->thread_manager->get_thread_by_post_id($post->ID);
        if (!$thread_data) {
            return $content;
        }
        
        // Add thread meta information
        $meta_html = $this->get_thread_meta_html($thread_data);
        $voting_html = $this->get_voting_html($thread_data);
        $actions_html = $this->get_thread_actions_html($thread_data);
        
        // Combine content with thread elements
        $thread_content = $meta_html . $content . $voting_html . $actions_html;
        
        return $thread_content;
    }
    
    /**
     * Generate thread meta HTML
     */
    private function get_thread_meta_html($thread) {
        ob_start();
        ?>
        <div class="ats-thread-meta">
            <div class="thread-author">
                <img src="<?php echo esc_url($thread->author_avatar ?: get_avatar_url($thread->author_id)); ?>" 
                     alt="<?php echo esc_attr($thread->author_name); ?>" class="author-avatar">
                <div class="author-info">
                    <a href="<?php echo esc_url($this->get_user_profile_url($thread->author_id)); ?>" 
                       class="author-name"><?php echo esc_html($thread->author_name); ?></a>
                    <div class="thread-date">
                        <?php echo sprintf(__('Posted %s ago', 'advanced-threads'), 
                            human_time_diff(strtotime($thread->created_at))); ?>
                    </div>
                </div>
            </div>
            <div class="thread-stats">
                <span class="views"><?php echo number_format($thread->view_count); ?> <?php _e('views', 'advanced-threads'); ?></span>
                <span class="replies"><?php echo number_format($thread->reply_count); ?> <?php _e('replies', 'advanced-threads'); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate voting HTML
     */
    private function get_voting_html($thread) {
        if (!get_option('ats_enable_voting', 1)) {
            return '';
        }
        
        $user_vote = '';
        if (is_user_logged_in()) {
            $user_vote = $this->vote_manager->get_user_vote(get_current_user_id(), $thread->id, 'thread');
        }
        
        ob_start();
        ?>
        <div class="ats-voting-section">
            <button class="vote-btn upvote <?php echo $user_vote === 'up' ? 'active' : ''; ?>" 
                    data-thread-id="<?php echo $thread->id; ?>" data-vote="up">
                <i class="ats-icon-upvote"></i>
                <span class="vote-count"><?php echo number_format($thread->upvotes); ?></span>
            </button>
            <button class="vote-btn downvote <?php echo $user_vote === 'down' ? 'active' : ''; ?>" 
                    data-thread-id="<?php echo $thread->id; ?>" data-vote="down">
                <i class="ats-icon-downvote"></i>
                <span class="vote-count"><?php echo number_format($thread->downvotes); ?></span>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate thread actions HTML
     */
    private function get_thread_actions_html($thread) {
        ob_start();
        ?>
        <div class="ats-thread-actions">
            <button class="share-btn" onclick="atsShareThread()">
                <i class="ats-icon-share"></i> <?php _e('Share', 'advanced-threads'); ?>
            </button>
            
            <?php if (is_user_logged_in()): ?>
                <button class="bookmark-btn" data-thread-id="<?php echo $thread->id; ?>">
                    <i class="ats-icon-bookmark"></i> <?php _e('Bookmark', 'advanced-threads'); ?>
                </button>
                
                <?php if (get_option('ats_enable_following', 1)): ?>
                    <button class="follow-thread-btn" data-thread-id="<?php echo $thread->id; ?>" data-action="follow">
                        <i class="ats-icon-follow"></i> <?php _e('Follow Thread', 'advanced-threads'); ?>
                    </button>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (current_user_can('edit_posts') || get_current_user_id() === $thread->author_id): ?>
                <button class="edit-thread-btn" data-thread-id="<?php echo $thread->id; ?>">
                    <i class="ats-icon-edit"></i> <?php _e('Edit', 'advanced-threads'); ?>
                </button>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Add meta tags for threads
     */
    public function add_meta_tags() {
        if (!is_singular('ats_thread')) {
            return;
        }
        
        global $post;
        $thread_data = $this->thread_manager->get_thread_by_post_id($post->ID);
        
        if (!$thread_data) {
            return;
        }
        
        // Open Graph tags
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($thread_data->title) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink()) . '" />' . "\n";
        
        if ($thread_data->excerpt) {
            echo '<meta property="og:description" content="' . esc_attr($thread_data->excerpt) . '" />' . "\n";
        }
        
        if ($thread_data->featured_image) {
            echo '<meta property="og:image" content="' . esc_url($thread_data->featured_image) . '" />' . "\n";
        }
        
        // Twitter Card tags
        echo '<meta name="twitter:card" content="summary" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($thread_data->title) . '" />' . "\n";
        
        // Schema.org structured data
        if (get_option('ats_enable_structured_data', 1)) {
            $this->add_structured_data($thread_data);
        }
    }
    
    /**
     * Add structured data for threads
     */
    private function add_structured_data($thread) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'DiscussionForumPosting',
            'headline' => $thread->title,
            'text' => wp_strip_all_tags($thread->content),
            'datePublished' => date('c', strtotime($thread->created_at)),
            'dateModified' => date('c', strtotime($thread->updated_at)),
            'author' => array(
                '@type' => 'Person',
                'name' => $thread->author_name,
                'url' => $this->get_user_profile_url($thread->author_id)
            ),
            'interactionStatistic' => array(
                array(
                    '@type' => 'InteractionCounter',
                    'interactionType' => 'https://schema.org/CommentAction',
                    'userInteractionCount' => $thread->reply_count
                ),
                array(
                    '@type' => 'InteractionCounter',
                    'interactionType' => 'https://schema.org/ViewAction',
                    'userInteractionCount' => $thread->view_count
                )
            )
        );
        
        if ($thread->featured_image) {
            $schema['image'] = $thread->featured_image;
        }
        
        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
    
    /**
     * Update user last seen timestamp
     */
    public function update_last_seen($user_login, $user) {
        $this->user_manager->update_last_seen($user->ID);
    }
    
    /**
     * Update last seen on logout
     */
    public function update_last_seen_logout() {
        if (is_user_logged_in()) {
            $this->user_manager->update_last_seen(get_current_user_id());
        }
    }
    
    /**
     * Daily cleanup cron job
     */
    public function daily_cleanup() {
        global $wpdb;
        
        // Clean up old thread views (keep only last 30 days)
        $views_table = $wpdb->prefix . 'ats_thread_views';
        $wpdb->query("DELETE FROM $views_table WHERE viewed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        // Clean up old notifications (keep only last 60 days)
        $notifications_table = $wpdb->prefix . 'ats_notifications';
        $wpdb->query("DELETE FROM $notifications_table WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY) AND is_read = 1");
        
        // Update user statistics
        $this->user_manager->update_all_user_stats();
        
        // Log cleanup
        error_log('ATS: Daily cleanup completed');
    }
    
    /**
     * Check if current page is a threads-related page
     */
    private function is_threads_page() {
        return (
            is_singular('ats_thread') ||
            is_post_type_archive('ats_thread') ||
            get_query_var('ats_category') ||
            get_query_var('ats_user_profile') ||
            get_query_var('ats_search') ||
            is_page($this->get_threads_page_id()) ||
            $this->is_threads_shortcode_page()
        );
    }
    
    /**
     * Check if current page contains threads shortcodes
     */
    private function is_threads_shortcode_page() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        $shortcodes = array(
            'ats_threads_listing',
            'ats_user_profile',
            'ats_create_thread_form',
            'ats_leaderboard'
        );
        
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get threads page ID
     */
    private function get_threads_page_id() {
        return get_option('ats_page_threads', 0);
    }
    
    /**
     * Get user profile URL
     */
    public function get_user_profile_url($user_id) {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return '#';
        }
        
        return home_url('/profile/' . $user->user_login . '/');
    }
    
    /**
     * Get managers
     */
    public function get_thread_manager() {
        return $this->thread_manager;
    }
    
    public function get_user_manager() {
        return $this->user_manager;
    }
    
    public function get_vote_manager() {
        return $this->vote_manager;
    }
}
            