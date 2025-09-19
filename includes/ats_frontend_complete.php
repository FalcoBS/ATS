<?php
/**
 * Advanced Threads System - Frontend
 * 
 * @package AdvancedThreadsSystem
 * @subpackage Frontend
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ATS_Frontend {
    
    private static $instance = null;
    private $thread_manager;
    private $user_manager;
    private $vote_manager;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->thread_manager = new ATS_Thread_Manager();
        $this->user_manager = new ATS_User_Manager();
        $this->vote_manager = new ATS_Vote_Manager();
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Template hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_filter('the_content', array($this, 'enhance_thread_content'));
        add_filter('comments_template', array($this, 'custom_comments_template'));
        
        // Form handling
        add_action('wp_ajax_ats_create_thread', array($this, 'handle_create_thread'));
        add_action('wp_ajax_nopriv_ats_create_thread', array($this, 'handle_create_thread_guest'));
        
        // Single thread hooks
        add_action('wp_head', array($this, 'thread_view_tracking'));
        add_filter('body_class', array($this, 'add_body_classes'));
        
        // User profile hooks
        add_action('template_redirect', array($this, 'handle_profile_actions'));
        add_filter('document_title_parts', array($this, 'custom_title_parts'));
        
        // Search hooks
        add_action('pre_get_posts', array($this, 'modify_search_query'));
        add_filter('get_search_form', array($this, 'custom_search_form'));
        
        // Widget areas
        add_action('widgets_init', array($this, 'register_widget_areas'));
        
        // Navigation menus
        add_filter('wp_nav_menu_items', array($this, 'add_nav_menu_items'), 10, 2);
        
        // Login/Registration hooks
        add_action('wp_login', array($this, 'on_user_login'), 10, 2);
        add_action('user_register', array($this, 'on_user_register'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (!$this->is_threads_page()) {
            return;
        }
        
        // Main frontend CSS
        wp_enqueue_style(
            'ats-frontend',
            ATS_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            ATS_VERSION
        );
        
        // Responsive CSS
        wp_enqueue_style(
            'ats-responsive',
            ATS_PLUGIN_URL . 'assets/css/responsive.css',
            array('ats-frontend'),
            ATS_VERSION
        );
        
        // Dark mode CSS (if enabled)
        if (ats_get_option('enable_dark_mode', 1)) {
            wp_enqueue_style(
                'ats-dark-mode',
                ATS_PLUGIN_URL . 'assets/css/dark-mode.css',
                array('ats-frontend'),
                ATS_VERSION
            );
        }
        
        // Main JavaScript
        wp_enqueue_script(
            'ats-frontend',
            ATS_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            ATS_VERSION,
            true
        );
        
        // Rich editor (if enabled)
        if (ats_get_option('enable_rich_editor', 1)) {
            wp_enqueue_script(
                'ats-editor',
                ATS_PLUGIN_URL . 'assets/js/editor.js',
                array('ats-frontend'),
                ATS_VERSION,
                true
            );
        }
        
        // Image upload handler
        if (ats_get_option('enable_image_uploads', 1)) {
            wp_enqueue_script(
                'ats-upload',
                ATS_PLUGIN_URL . 'assets/js/upload.js',
                array('ats-frontend'),
                ATS_VERSION,
                true
            );
        }
        
        // Notifications system
        if (ats_get_option('enable_notifications', 1)) {
            wp_enqueue_script(
                'ats-notifications',
                ATS_PLUGIN_URL . 'assets/js/notifications.js',
                array('ats-frontend'),
                ATS_VERSION,
                true
            );
        }
        
        // Localize main script
        wp_localize_script('ats-frontend', 'atsConfig', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ats_frontend_nonce'),
            'user_id' => get_current_user_id(),
            'is_logged_in' => is_user_logged_in(),
            'current_page' => $this->get_current_page_type(),
            'settings' => array(
                'threads_per_page' => ats_get_option('threads_per_page', 20),
                'replies_per_page' => ats_get_option('replies_per_page', 50),
                'enable_voting' => ats_get_option('enable_voting', 1),
                'enable_following' => ats_get_option('enable_following', 1),
                'enable_live_updates' => ats_get_option('enable_live_updates', 1),
                'max_content_length' => ats_get_option('max_content_length', 10000),
                'auto_save_drafts' => ats_get_option('auto_save_drafts', 1),
                'enable_mentions' => ats_get_option('enable_mentions', 1)
            ),
            'strings' => array(
                'loading' => __('Loading...', 'advanced-threads'),
                'load_more' => __('Load More', 'advanced-threads'),
                'no_more_content' => __('No more content to load', 'advanced-threads'),
                'error_occurred' => __('An error occurred', 'advanced-threads'),
                'confirm_delete' => __('Are you sure you want to delete this?', 'advanced-threads'),
                'login_required' => __('Please log in to continue', 'advanced-threads'),
                'vote_recorded' => __('Your vote has been recorded', 'advanced-threads'),
                'vote_removed' => __('Your vote has been removed', 'advanced-threads'),
                'following' => __('Following', 'advanced-threads'),
                'follow' => __('Follow', 'advanced-threads'),
                'unfollow' => __('Unfollow', 'advanced-threads'),
                'reply_posted' => __('Reply posted successfully', 'advanced-threads'),
                'draft_saved' => __('Draft saved', 'advanced-threads'),
                'typing' => __('is typing...', 'advanced-threads'),
                'online_now' => __('Online now', 'advanced-threads'),
                'last_seen' => __('Last seen', 'advanced-threads'),
                'new_notification' => __('You have new notifications', 'advanced-threads'),
                'mark_all_read' => __('Mark all as read', 'advanced-threads'),
                'search_placeholder' => __('Search threads...', 'advanced-threads'),
                'no_results' => __('No results found', 'advanced-threads')
            )
        ));
    }
    
    /**
     * Enhance thread content with additional elements
     */
    public function enhance_thread_content($content) {
        if (!is_singular('ats_thread') || !is_main_query()) {
            return $content;
        }
        
        global $post;
        $thread = $this->thread_manager->get_thread_by_post_id($post->ID);
        
        if (!$thread) {
            return $content;
        }
        
        // Track view
        $this->track_thread_view($thread->id);
        
        // Build enhanced content
        $enhanced_content = '';
        
        // Thread header
        $enhanced_content .= $this->get_thread_header_html($thread);
        
        // Original content
        $enhanced_content .= '<div class="thread-main-content">' . $content . '</div>';
        
        // Thread footer (voting, actions, etc.)
        $enhanced_content .= $this->get_thread_footer_html($thread);
        
        // Thread replies
        $enhanced_content .= $this->get_thread_replies_html($thread);
        
        // Reply form
        if (is_user_logged_in() && !$thread->is_locked) {
            $enhanced_content .= $this->get_reply_form_html($thread);
        } elseif (!is_user_logged_in()) {
            $enhanced_content .= $this->get_login_prompt_html();
        } elseif ($thread->is_locked) {
            $enhanced_content .= $this->get_locked_thread_notice_html();
        }
        
        return $enhanced_content;
    }
    
    /**
     * Custom comments template for threads
     */
    public function custom_comments_template($template) {
        if (is_singular('ats_thread')) {
            $custom_template = locate_template(array(
                'advanced-threads/comments-thread.php',
                'ats-templates/comments-thread.php'
            ));
            
            if ($custom_template) {
                return $custom_template;
            }
            
            // Use plugin template
            $plugin_template = ATS_PLUGIN_PATH . 'templates/comments-thread.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Handle thread creation from frontend
     */
    public function handle_create_thread() {
        if (!wp_verify_nonce($_POST['thread_nonce'], 'create_thread')) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to create a thread', 'advanced-threads'));
        }
        
        $user_id = get_current_user_id();
        
        if (!ats_user_can('create_thread', $user_id)) {
            wp_send_json_error(__('You do not have permission to create threads', 'advanced-threads'));
        }
        
        // Validate and sanitize input
        $title = sanitize_text_field($_POST['thread_title']);
        $content = wp_kses_post($_POST['thread_content']);
        $category = sanitize_text_field($_POST['thread_category']);
        $tags = sanitize_text_field($_POST['thread_tags']);
        
        if (empty($title) || empty($content) || empty($category)) {
            wp_send_json_error(__('Please fill in all required fields', 'advanced-threads'));
        }
        
        // Check content length
        $max_length = ats_get_option('max_content_length', 10000);
        if (strlen($content) > $max_length) {
            wp_send_json_error(sprintf(__('Content is too long. Maximum %d characters allowed.', 'advanced-threads'), $max_length));
        }
        
        // Handle image upload if present
        $featured_image = '';
        if (!empty($_FILES['thread_image']) && ats_get_option('enable_image_uploads', 1)) {
            $upload_result = ats_process_image_upload($_FILES['thread_image']);
            if ($upload_result['success']) {
                $featured_image = $upload_result['url'];
            }
        }
        
        // Create thread data
        $thread_data = array(
            'title' => $title,
            'content' => $content,
            'author_id' => $user_id,
            'category' => $category,
            'tags' => $tags,
            'featured_image' => $featured_image,
            'status' => ats_get_option('require_approval', 0) ? 'pending' : 'published'
        );
        
        $thread_id = $this->thread_manager->create_thread($thread_data);
        
        if ($thread_id) {
            wp_send_json_success(array(
                'thread_id' => $thread_id,
                'message' => __('Thread created successfully!', 'advanced-threads'),
                'redirect_url' => $this->get_thread_url($thread_id)
            ));
        } else {
            wp_send_json_error(__('Failed to create thread. Please try again.', 'advanced-threads'));
        }
    }
    
    /**
     * Handle thread creation for guests
     */
    public function handle_create_thread_guest() {
        wp_send_json_error(__('You must be logged in to create threads', 'advanced-threads'));
    }
    
    /**
     * Track thread views
     */
    public function thread_view_tracking() {
        if (!is_singular('ats_thread')) {
            return;
        }
        
        global $post;
        $thread = $this->thread_manager->get_thread_by_post_id($post->ID);
        
        if ($thread) {
            // Use JavaScript to track view after page load
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    atsTrackThreadView(' . $thread->id . ');
                });
            </script>';
        }
    }
    
    /**
     * Add custom body classes
     */
    public function add_body_classes($classes) {
        if (is_singular('ats_thread')) {
            $classes[] = 'ats-single-thread';
            
            global $post;
            $thread = $this->thread_manager->get_thread_by_post_id($post->ID);
            
            if ($thread) {
                if ($thread->is_pinned) {
                    $classes[] = 'ats-pinned-thread';
                }
                if ($thread->is_locked) {
                    $classes[] = 'ats-locked-thread';
                }
                if ($thread->category) {
                    $classes[] = 'ats-category-' . sanitize_html_class($thread->category);
                }
            }
        } elseif (is_post_type_archive('ats_thread')) {
            $classes[] = 'ats-threads-archive';
        } elseif ($this->is_user_profile_page()) {
            $classes[] = 'ats-user-profile';
        }
        
        return $classes;
    }
    
    /**
     * Handle profile actions
     */
    public function handle_profile_actions() {
        if (!$this->is_user_profile_page()) {
            return;
        }
        
        $username = get_query_var('ats_user_profile');
        $tab = get_query_var('ats_profile_tab') ?: 'overview';
        
        $user = get_user_by('login', $username);
        if (!$user) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }
        
        // Handle AJAX tab loading
        if (wp_doing_ajax() && isset($_POST['action']) && $_POST['action'] === 'ats_load_profile_tab') {
            $this->handle_profile_tab_ajax($user->ID, $tab);
        }
    }
    
    /**
     * Custom title parts for threads pages
     */
    public function custom_title_parts($title) {
        if (is_singular('ats_thread')) {
            global $post;
            $thread = $this->thread_manager->get_thread_by_post_id($post->ID);
            
            if ($thread) {
                $title['title'] = $thread->title;
                
                if ($thread->category) {
                    $category = ats_get_category_by_slug($thread->category);
                    if ($category) {
                        $title['page'] = $category->name;
                    }
                }
            }
        } elseif ($this->is_user_profile_page()) {
            $username = get_query_var('ats_user_profile');
            $user = get_user_by('login', $username);
            
            if ($user) {
                $profile = $this->user_manager->get_user_profile($user->ID, false);
                $title['title'] = $profile ? $profile->display_name : $user->display_name;
                $title['page'] = __('User Profile', 'advanced-threads');
            }
        } elseif (get_query_var('ats_category')) {
            $category_slug = get_query_var('ats_category');
            $category = ats_get_category_by_slug($category_slug);
            
            if ($category) {
                $title['title'] = $category->name;
                $title['page'] = __('Thread Category', 'advanced-threads');
            }
        }
        
        return $title;
    }
    
    /**
     * Modify search query to include threads
     */
    public function modify_search_query($query) {
        if (!is_admin() && $query->is_main_query() && $query->is_search()) {
            $post_types = $query->get('post_type');
            
            if (empty($post_types)) {
                $post_types = array('post', 'page');
            }
            
            if (!in_array('ats_thread', (array)$post_types)) {
                $post_types[] = 'ats_thread';
                $query->set('post_type', $post_types);
            }
        }
    }
    
    /**
     * Custom search form with thread search
     */
    public function custom_search_form($form) {
        if (!$this->is_threads_page()) {
            return $form;
        }
        
        $search_value = get_search_query();
        
        $custom_form = '
        <form role="search" method="get" class="ats-search-form" action="' . esc_url(home_url('/')) . '">
            <div class="search-field-wrapper">
                <input type="search" class="search-field" placeholder="' . esc_attr__('Search threads...', 'advanced-threads') . '" 
                       value="' . esc_attr($search_value) . '" name="s" />
                <input type="hidden" name="post_type" value="ats_thread" />
                <button type="submit" class="search-submit">
                    <i class="ats-icon-search"></i>
                    <span class="screen-reader-text">' . __('Search', 'advanced-threads') . '</span>
                </button>
            </div>
        </form>';
        
        return $custom_form;
    }
    
    /**
     * Register widget areas
     */
    public function register_widget_areas() {
        register_sidebar(array(
            'name' => __('Thread Sidebar', 'advanced-threads'),
            'id' => 'ats-thread-sidebar',
            'description' => __('Appears on single thread pages', 'advanced-threads'),
            'before_widget' => '<div id="%1$s" class="widget ats-widget %2$s">',
            'after_widget' => '</div>',
            'before_title' => '<h3 class="widget-title">',
            'after_title' => '</h3>',
        ));
        
        register_sidebar(array(
            'name' => __('Threads Archive Sidebar', 'advanced-threads'),
            'id' => 'ats-archive-sidebar',
            'description' => __('Appears on threads archive and category pages', 'advanced-threads'),
            'before_widget' => '<div id="%1$s" class="widget ats-widget %2$s">',
            'after_widget' => '</div>',
            'before_title' => '<h3 class="widget-title">',
            'after_title' => '</h3>',
        ));
        
        register_sidebar(array(
            'name' => __('User Profile Sidebar', 'advanced-threads'),
            'id' => 'ats-profile-sidebar',
            'description' => __('Appears on user profile pages', 'advanced-threads'),
            'before_widget' => '<div id="%1$s" class="widget ats-widget %2$s">',
            'after_widget' => '</div>',
            'before_title' => '<h3 class="widget-title">',
            'after_title' => '</h3>',
        ));
    }
    
    /**
     * Add navigation menu items
     */
    public function add_nav_menu_items($items, $args) {
        // Add threads link to primary menu
        if ($args->theme_location === 'primary') {
            $threads_page_id = ats_get_option('page_threads', 0);
            
            if ($threads_page_id) {
                $threads_url = get_permalink($threads_page_id);
                $threads_title = __('Threads', 'advanced-threads');
                
                $threads_item = '<li class="menu-item menu-item-threads">
                    <a href="' . esc_url($threads_url) . '">' . esc_html($threads_title) . '</a>
                </li>';
                
                // Add after first menu item
                $items = preg_replace('/(<li[^>]*>.*?<\/li>)/', '$1' . $threads_item, $items, 1);
            }
        }
        
        return $items;
    }
    
    /**
     * Handle user login
     */
    public function on_user_login($user_login, $user) {
        $this->user_manager->update_last_seen($user->ID);
        
        // Award daily login points
        $last_login = get_user_meta($user->ID, 'ats_last_login', true);
        $today = date('Y-m-d');
        
        if ($last_login !== $today) {
            $this->user_manager->award_reputation_points($user->ID, 'daily_login');
            update_user_meta($user->ID, 'ats_last_login', $today);
        }
    }
    
    /**
     * Handle user registration
     */
    public function on_user_register($user_id) {
        // Create user profile
        $this->user_manager->create_user_profile($user_id);
        
        // Send welcome notification
        if (ats_get_option('send_welcome_notification', 1)) {
            ats_send_notification(
                $user_id,
                'welcome',
                __('Welcome to the community!', 'advanced-threads'),
                __('Welcome! Start by introducing yourself or creating your first thread.', 'advanced-threads'),
                home_url('/create-thread/')
            );
        }
    }
    
    // Helper methods for generating HTML content
    
    /**
     * Get thread header HTML
     */
    private function get_thread_header_html($thread) {
        ob_start();
        ?>
        <div class="ats-thread-header">
            <div class="thread-meta">
                <div class="thread-author">
                    <div class="author-avatar">
                        <img src="<?php echo esc_url($thread->author_avatar ?: ats_get_user_avatar($thread->author_id, 48)); ?>" 
                             alt="<?php echo esc_attr($thread->author_name); ?>">
                    </div>
                    <div class="author-info">
                        <div class="author-name">
                            <a href="<?php echo ats_get_user_profile_url($thread->author_id); ?>">
                                <?php echo esc_html($thread->author_name); ?>
                            </a>
                            <?php if ($thread->author_badge): ?>
                                <span class="author-badge badge-<?php echo esc_attr($thread->author_badge); ?>">
                                    <?php echo esc_html(ucfirst($thread->author_badge)); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="thread-date">
                            <?php printf(__('Posted %s ago', 'advanced-threads'), ats_time_ago($thread->created_at)); ?>
                            <?php if ($thread->updated_at !== $thread->created_at): ?>
                                <span class="thread-updated">
                                    (<?php printf(__('Updated %s ago', 'advanced-threads'), ats_time_ago($thread->updated_at)); ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="thread-stats">
                    <div class="stat-item views">
                        <i class="ats-icon-eye"></i>
                        <span class="stat-value"><?php echo ats_format_number($thread->view_count); ?></span>
                        <span class="stat-label"><?php _e('views', 'advanced-threads'); ?></span>
                    </div>
                    <div class="stat-item replies">
                        <i class="ats-icon-message-circle"></i>
                        <span class="stat-value"><?php echo number_format($thread->reply_count); ?></span>
                        <span class="stat-label"><?php _e('replies', 'advanced-threads'); ?></span>
                    </div>
                    <div class="stat-item votes">
                        <i class="ats-icon-thumbs-up"></i>
                        <span class="stat-value"><?php echo $thread->upvotes - $thread->downvotes; ?></span>
                        <span class="stat-label"><?php _e('votes', 'advanced-threads'); ?></span>
                    </div>
                </div>
            </div>
            
            <?php if ($thread->category): ?>
                <div class="thread-category">
                    <?php
                    $category = ats_get_category_by_slug($thread->category);
                    if ($category): ?>
                        <a href="<?php echo ats_get_category_url($thread->category); ?>" 
                           class="category-link"
                           style="color: <?php echo esc_attr($category->color); ?>">
                            <?php if ($category->icon): ?>
                                <i class="ats-icon-<?php echo esc_attr($category->icon); ?>"></i>
                            <?php endif; ?>
                            <?php echo esc_html($category->name); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($thread->tags): ?>
                <div class="thread-tags">
                    <?php
                    $tags = explode(',', $thread->tags);
                    foreach ($tags as $tag):
                        $tag = trim($tag);
                        if (!empty($tag)): ?>
                            <a href="<?php echo home_url('/threads/tag/' . urlencode($tag) . '/'); ?>" 
                               class="thread-tag">
                                #<?php echo esc_html($tag); ?>
                            </a>
                        <?php endif;
                    endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($thread->is_pinned || $thread->is_locked): ?>
                <div class="thread-badges">
                    <?php if ($thread->is_pinned): ?>
                        <span class="thread-badge pinned">
                            <i class="ats-icon-pin"></i>
                            <?php _e('Pinned', 'advanced-threads'); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($thread->is_locked): ?>
                        <span class="thread-badge locked">
                            <i class="ats-icon-lock"></i>
                            <?php _e('Locked', 'advanced-threads'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get thread footer HTML
     */
    private function get_thread_footer_html($thread) {
        ob_start();
        ?>
        <div class="ats-thread-footer">
            <?php if (ats_get_option('enable_voting', 1)): ?>
                <div class="thread-voting">
                    <div class="voting-buttons" data-thread-id="<?php echo $thread->id; ?>">
                        <?php
                        $user_vote = '';
                        if (is_user_logged_in()) {
                            $user_vote = $this->vote_manager->get_user_vote(get_current_user_id(), $thread->id, null);
                        }
                        ?>
                        
                        <button class="vote-btn upvote <?php echo $user_vote === 'up' ? 'active' : ''; ?>"
                                data-vote="up" 
                                <?php echo !is_user_logged_in() ? 'disabled' : ''; ?>>
                            <i class="ats-icon-chevron-up"></i>
                            <span class="vote-count"><?php echo number_format($thread->upvotes); ?></span>
                        </button>
                        
                        <div class="vote-score">
                            <?php echo $thread->upvotes - $thread->downvotes; ?>
                        </div>
                        
                        <button class="vote-btn downvote <?php echo $user_vote === 'down' ? 'active' : ''; ?>"
                                data-vote="down"
                                <?php echo !is_user_logged_in() ? 'disabled' : ''; ?>>
                            <i class="ats-icon-chevron-down"></i>
                            <span class="vote-count"><?php echo number_format($thread->downvotes); ?></span>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="thread-actions">
                <div class="social-actions">
                    <?php if (is_user_logged_in()): ?>
                        <?php if (ats_get_option('enable_following', 1)): ?>
                            <button class="action-btn follow-thread" 
                                    data-thread-id="<?php echo $thread->id; ?>"
                                    data-action="follow">
                                <i class="ats-icon-bell"></i>
                                <span><?php _e('Follow', 'advanced-threads'); ?></span>
                            </button>
                        <?php endif; ?>
                        
                        <button class="action-btn bookmark-thread" 
                                data-thread-id="<?php echo $thread->id; ?>">
                            <i class="ats-icon-bookmark"></i>
                            <span><?php _e('Bookmark', 'advanced-threads'); ?></span>
                        </button>
                    <?php endif; ?>
                    
                    <button class="action-btn share-thread" 
                            data-thread-id="<?php echo $thread->id; ?>"
                            data-title="<?php echo esc_attr($thread->title); ?>"
                            data-url="<?php echo esc_url(get_permalink()); ?>">
                        <i class="ats-icon-share-2"></i>
                        <span><?php _e('Share', 'advanced-threads'); ?></span>
                    </button>
                </div>
                
                <?php if (is_user_logged_in()): ?>
                    <div class="moderation-actions">
                        <?php if (get_current_user_id() === $thread->author_id || current_user_can('edit_posts')): ?>
                            <button class="action-btn edit-thread" 
                                    data-thread-id="<?php echo $thread->id; ?>">
                                <i class="ats-icon-edit-2"></i>
                                <span><?php _e('Edit', 'advanced-threads'); ?></span>
                            </button>
                        <?php endif; ?>
                        
                        <button class="action-btn report-thread" 
                                data-thread-id="<?php echo $thread->id; ?>"
                                data-type="thread">
                            <i class="ats-icon-flag"></i>
                            <span><?php _e('Report', 'advanced-threads'); ?></span>
                        </button>
                        
                        <?php if (current_user_can('moderate_comments')): ?>
                            <button class="action-btn pin-thread <?php echo $thread->is_pinned ? 'active' : ''; ?>" 
                                    data-thread-id="<?php echo $thread->id; ?>">
                                <i class="ats-icon-pin"></i>
                                <span><?php echo $thread->is_pinned ? __('Unpin', 'advanced-threads') : __('Pin', 'advanced-threads'); ?></span>
                            </button>
                            
                            <button class="action-btn lock-thread <?php echo $thread->is_locked ? 'active' : ''; ?>" 
                                    data-thread-id="<?php echo $thread->id; ?>">
                                <i class="ats-icon-lock"></i>
                                <span><?php echo $thread->is_locked ? __('Unlock', 'advanced-threads') : __('Lock', 'advanced-threads'); ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get thread replies HTML
     */
    private function get_thread_replies_html($thread) {
        $replies_per_page = ats_get_option('replies_per_page', 50);
        $replies = $this->thread_manager->get_thread_replies($thread->id, 1, $replies_per_page, 'oldest');
        
        ob_start();
        ?>
        <div class="ats-thread-replies" id="thread-replies">
            <div class="replies-header">
                <h3 class="replies-title">
                    <?php printf(_n('%d Reply', '%d Replies', $thread->reply_count, 'advanced-threads'), $thread->reply_count); ?>
                </h3>
                
                <?php if ($thread->reply_count > 0): ?>
                    <div class="replies-sort">
                        <select id="replies-sort-order">
                            <option value="oldest"><?php _e('Oldest First', 'advanced-threads'); ?></option>
                            <option value="newest"><?php _e('Newest First', 'advanced-threads'); ?></option>
                            <option value="most_voted"><?php _e('Most Voted', 'advanced-threads'); ?></option>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="replies-list" id="replies-list">
                <?php if (!empty($replies['data'])): ?>
                    <?php foreach ($replies['data'] as $reply): ?>
                        <?php $this->render_reply_item($reply); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-replies-message">
                        <p><?php _e('No replies yet. Be the first to respond!', 'advanced-threads'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($replies['total'] > $replies_per_page): ?>
                <div class="replies-pagination">
                    <button class="ats-btn ats-btn-outline load-more-replies" 
                            data-thread-id="<?php echo $thread->id; ?>"
                            data-page="2">
                        <?php _e('Load More Replies', 'advanced-threads'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get reply form HTML
     */
    private function get_reply_form_html($thread) {
        ob_start();
        ?>
        <div class="ats-reply-form-container" id="reply-form-container">
            <h4><?php _e('Post a Reply', 'advanced-threads'); ?></h4>
            
            <form class="ats-reply-form" id="reply-form" data-thread-id="<?php echo $thread->id; ?>">
                <?php wp_nonce_field('ats_add_reply', 'reply_nonce'); ?>
                
                <div class="form-group">
                    <?php if (ats_get_option('enable_rich_editor', 1)): ?>
                        <div class="reply-editor-container">
                            <div id="reply-editor" class="reply-editor"></div>
                        </div>
                        <textarea id="reply-content" name="reply_content" style="display: none;" required></textarea>
                    <?php else: ?>
                        <textarea id="reply-content" name="reply_content" 
                                  placeholder="<?php esc_attr_e('Write your reply...', 'advanced-threads'); ?>"
                                  rows="6" required></textarea>
                    <?php endif; ?>
                    
                    <div class="form-footer">
                        <div class="character-counter">
                            <span id="char-count">0</span> / <?php echo number_format(ats_get_option('max_content_length', 10000)); ?>
                        </div>
                        
                        <div class="form-actions">
                            <?php if (ats_get_option('enable_image_uploads', 1)): ?>
                                <button type="button" class="ats-btn ats-btn-outline btn-sm" id="add-image-btn">
                                    <i class="ats-icon-image"></i>
                                    <?php _e('Add Image', 'advanced-threads'); ?>
                                </button>
                                <input type="file" id="reply-image" name="reply_image" 
                                       accept="image/*" style="display: none;">
                            <?php endif; ?>
                            
                            <button type="button" class="ats-btn ats-btn-outline btn-sm" id="preview-reply-btn">
                                <i class="ats-icon-eye"></i>
                                <?php _e('Preview', 'advanced-threads'); ?>
                            </button>
                            
                            <button type="submit" class="ats-btn ats-btn-primary" id="submit-reply-btn">
                                <i class="ats-icon-send"></i>
                                <?php _e('Post Reply', 'advanced-threads'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            
            <div class="reply-preview" id="reply-preview" style="display: none;">
                <h5><?php _e('Preview', 'advanced-threads'); ?></h5>
                <div class="preview-content"></div>
                <button type="button" class="ats-btn ats-btn-outline btn-sm" id="edit-reply-btn">
                    <?php _e('Continue Editing', 'advanced-threads'); ?>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get login prompt HTML
     */
    private function get_login_prompt_html() {
        ob_start();
        ?>
        <div class="ats-login-prompt">
            <div class="login-message">
                <h4><?php _e('Join the Discussion', 'advanced-threads'); ?></h4>
                <p><?php _e('You need to be logged in to participate in this thread.', 'advanced-threads'); ?></p>
                
                <div class="login-actions">
                    <a href="<?php echo wp_login_url(get_permalink()); ?>" 
                       class="ats-btn ats-btn-primary">
                        <?php _e('Log In', 'advanced-threads'); ?>
                    </a>
                    
                    <?php if (get_option('users_can_register')): ?>
                        <a href="<?php echo wp_registration_url(); ?>" 
                           class="ats-btn ats-btn-outline">
                            <?php _e('Register', 'advanced-threads'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get locked thread notice HTML
     */
    private function get_locked_thread_notice_html() {
        ob_start();
        ?>
        <div class="ats-locked-notice">
            <div class="locked-message">
                <i class="ats-icon-lock"></i>
                <h4><?php _e('Thread Locked', 'advanced-threads'); ?></h4>
                <p><?php _e('This thread has been locked and no longer accepts new replies.', 'advanced-threads'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render individual reply item
     */
    private function render_reply_item($reply) {
        ?>
        <div class="ats-reply-item" id="reply-<?php echo $reply->id; ?>" data-reply-id="<?php echo $reply->id; ?>">
            <div class="reply-author">
                <div class="author-avatar">
                    <img src="<?php echo esc_url($reply->author_avatar ?: ats_get_user_avatar($reply->author_id, 40)); ?>" 
                         alt="<?php echo esc_attr($reply->author_name); ?>">
                </div>
                
                <div class="author-info">
                    <div class="author-name">
                        <a href="<?php echo ats_get_user_profile_url($reply->author_id); ?>">
                            <?php echo esc_html($reply->author_name); ?>
                        </a>
                        <?php if ($reply->author_badge): ?>
                            <span class="author-badge badge-<?php echo esc_attr($reply->author_badge); ?>">
                                <?php echo esc_html(ucfirst($reply->author_badge)); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="reply-date">
                        <?php printf(__('Posted %s ago', 'advanced-threads'), ats_time_ago($reply->created_at)); ?>
                    </div>
                </div>
            </div>
            
            <div class="reply-content">
                <?php echo wp_kses_post($reply->content); ?>
            </div>
            
            <div class="reply-actions">
                <div class="reply-voting">
                    <?php if (ats_get_option('enable_voting', 1)): ?>
                        <div class="voting-buttons" data-reply-id="<?php echo $reply->id; ?>">
                            <?php
                            $user_vote = '';
                            if (is_user_logged_in()) {
                                $user_vote = $this->vote_manager->get_user_vote(get_current_user_id(), null, $reply->id);
                            }
                            ?>
                            
                            <button class="vote-btn upvote <?php echo $user_vote === 'up' ? 'active' : ''; ?>"
                                    data-vote="up" 
                                    <?php echo !is_user_logged_in() ? 'disabled' : ''; ?>>
                                <i class="ats-icon-chevron-up"></i>
                                <span class="vote-count"><?php echo number_format($reply->upvotes); ?></span>
                            </button>
                            
                            <div class="vote-score">
                                <?php echo $reply->upvotes - $reply->downvotes; ?>
                            </div>
                            
                            <button class="vote-btn downvote <?php echo $user_vote === 'down' ? 'active' : ''; ?>"
                                    data-vote="down"
                                    <?php echo !is_user_logged_in() ? 'disabled' : ''; ?>>
                                <i class="ats-icon-chevron-down"></i>
                                <span class="vote-count"><?php echo number_format($reply->downvotes); ?></span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="reply-meta">
                    <?php if (is_user_logged_in()): ?>
                        <button class="action-btn reply-to-reply" 
                                data-reply-id="<?php echo $reply->id; ?>"
                                data-author="<?php echo esc_attr($reply->author_name); ?>">
                            <i class="ats-icon-message-circle"></i>
                            <span><?php _e('Reply', 'advanced-threads'); ?></span>
                        </button>
                        
                        <?php if (get_current_user_id() === $reply->author_id || current_user_can('edit_posts')): ?>
                            <button class="action-btn edit-reply" 
                                    data-reply-id="<?php echo $reply->id; ?>">
                                <i class="ats-icon-edit-2"></i>
                                <span><?php _e('Edit', 'advanced-threads'); ?></span>
                            </button>
                        <?php endif; ?>
                        
                        <button class="action-btn report-reply" 
                                data-reply-id="<?php echo $reply->id; ?>"
                                data-type="reply">
                            <i class="ats-icon-flag"></i>
                            <span><?php _e('Report', 'advanced-threads'); ?></span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    // Helper methods
    
    /**
     * Check if current page is threads-related
     */
    private function is_threads_page() {
        return is_singular('ats_thread') || 
               is_post_type_archive('ats_thread') || 
               $this->is_user_profile_page() ||
               get_query_var('ats_category');
    }
    
    /**
     * Check if current page is user profile page
     */
    private function is_user_profile_page() {
        return !empty(get_query_var('ats_user_profile'));
    }
    
    /**
     * Get current page type
     */
    private function get_current_page_type() {
        if (is_singular('ats_thread')) {
            return 'single_thread';
        } elseif (is_post_type_archive('ats_thread')) {
            return 'threads_archive';
        } elseif ($this->is_user_profile_page()) {
            return 'user_profile';
        } elseif (get_query_var('ats_category')) {
            return 'thread_category';
        }
        return 'other';
    }
    
    /**
     * Track thread view
     */
    private function track_thread_view($thread_id) {
        // Prevent tracking for bots and crawlers
        if ($this->is_bot_or_crawler()) {
            return;
        }
        
        // Use cookie to prevent duplicate views from same user
        $view_key = 'ats_viewed_' . $thread_id;
        if (!isset($_COOKIE[$view_key])) {
            $this->thread_manager->increment_view_count($thread_id);
            setcookie($view_key, '1', time() + (24 * 60 * 60), COOKIEPATH, COOKIE_DOMAIN);
        }
    }
    
    /**
     * Check if current request is from bot or crawler
     */
    private function is_bot_or_crawler() {
        $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        $bots = array('bot', 'crawler', 'spider', 'scraper', 'facebook', 'twitter');
        
        foreach ($bots as $bot) {
            if (strpos($user_agent, $bot) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get thread URL
     */
    private function get_thread_url($thread_id) {
        $thread = $this->thread_manager->get_thread($thread_id);
        if ($thread && $thread->post_id) {
            return get_permalink($thread->post_id);
        }
        return home_url('/threads/');
    }
    
    /**
     * Handle profile tab AJAX
     */
    private function handle_profile_tab_ajax($user_id, $tab) {
        if (!wp_verify_nonce($_POST['nonce'], 'ats_frontend_nonce')) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
        }
        
        $content = '';
        
        switch ($tab) {
            case 'threads':
                $content = $this->get_user_threads_tab($user_id);
                break;
            case 'replies':
                $content = $this->get_user_replies_tab($user_id);
                break;
            case 'activity':
                $content = $this->get_user_activity_tab($user_id);
                break;
            case 'badges':
                $content = $this->get_user_badges_tab($user_id);
                break;
            default:
                $content = $this->get_user_overview_tab($user_id);
                break;
        }
        
        wp_send_json_success(array('content' => $content));
    }
    
    /**
     * Get user overview tab content
     */
    private function get_user_overview_tab($user_id) {
        $profile = $this->user_manager->get_user_profile($user_id, false);
        $stats = $this->user_manager->get_user_stats($user_id);
        
        ob_start();
        ?>
        <div class="user-overview-tab">
            <div class="user-stats-grid">
                <div class="stat-box">
                    <div class="stat-number"><?php echo number_format($stats['thread_count']); ?></div>
                    <div class="stat-label"><?php _e('Threads Created', 'advanced-threads'); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo number_format($stats['reply_count']); ?></div>
                    <div class="stat-label"><?php _e('Replies Posted', 'advanced-threads'); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo number_format($stats['total_votes']); ?></div>
                    <div class="stat-label"><?php _e('Total Votes', 'advanced-threads'); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo number_format($profile->reputation_points ?? 0); ?></div>
                    <div class="stat-label"><?php _e('Reputation Points', 'advanced-threads'); ?></div>
                </div>
            </div>
            
            <?php if (!empty($profile->bio)): ?>
                <div class="user-bio">
                    <h4><?php _e('About', 'advanced-threads'); ?></h4>
                    <p><?php echo wp_kses_post($profile->bio); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="user-recent-activity">
                <h4><?php _e('Recent Activity', 'advanced-threads'); ?></h4>
                <?php echo $this->get_user_activity_summary($user_id, 5); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get user threads tab content
     */
    private function get_user_threads_tab($user_id) {
        $threads = $this->thread_manager->get_user_threads($user_id, 1, 10);
        
        ob_start();
        ?>
        <div class="user-threads-tab">
            <?php if (!empty($threads['data'])): ?>
                <div class="threads-list">
                    <?php foreach ($threads['data'] as $thread): ?>
                        <div class="thread-item">
                            <h5><a href="<?php echo get_permalink($thread->post_id); ?>"><?php echo esc_html($thread->title); ?></a></h5>
                            <div class="thread-meta">
                                <span class="thread-date"><?php echo ats_time_ago($thread->created_at); ?></span>
                                <span class="thread-stats">
                                    <?php printf(__('%d replies, %d views', 'advanced-threads'), $thread->reply_count, $thread->view_count); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($threads['total'] > 10): ?>
                    <button class="ats-btn ats-btn-outline load-more-user-content" 
                            data-user-id="<?php echo $user_id; ?>"
                            data-type="threads"
                            data-page="2">
                        <?php _e('Load More Threads', 'advanced-threads'); ?>
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <p class="no-content-message"><?php _e('No threads created yet.', 'advanced-threads'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get user replies tab content
     */
    private function get_user_replies_tab($user_id) {
        $replies = $this->thread_manager->get_user_replies($user_id, 1, 10);
        
        ob_start();
        ?>
        <div class="user-replies-tab">
            <?php if (!empty($replies['data'])): ?>
                <div class="replies-list">
                    <?php foreach ($replies['data'] as $reply): ?>
                        <div class="reply-item">
                            <div class="reply-content">
                                <?php echo wp_trim_words(wp_strip_all_tags($reply->content), 20); ?>
                            </div>
                            <div class="reply-meta">
                                <span class="reply-thread">
                                    <?php _e('In:', 'advanced-threads'); ?> 
                                    <a href="<?php echo get_permalink($reply->thread_post_id); ?>"><?php echo esc_html($reply->thread_title); ?></a>
                                </span>
                                <span class="reply-date"><?php echo ats_time_ago($reply->created_at); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($replies['total'] > 10): ?>
                    <button class="ats-btn ats-btn-outline load-more-user-content" 
                            data-user-id="<?php echo $user_id; ?>"
                            data-type="replies"
                            data-page="2">
                        <?php _e('Load More Replies', 'advanced-threads'); ?>
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <p class="no-content-message"><?php _e('No replies posted yet.', 'advanced-threads'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get user activity tab content
     */
    private function get_user_activity_tab($user_id) {
        $activities = $this->user_manager->get_user_activities($user_id, 1, 20);
        
        ob_start();
        ?>
        <div class="user-activity-tab">
            <?php if (!empty($activities['data'])): ?>
                <div class="activity-timeline">
                    <?php foreach ($activities['data'] as $activity): ?>
                        <div class="activity-item activity-<?php echo esc_attr($activity->type); ?>">
                            <div class="activity-icon">
                                <i class="ats-icon-<?php echo $this->get_activity_icon($activity->type); ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-description">
                                    <?php echo $this->format_activity_description($activity); ?>
                                </div>
                                <div class="activity-date">
                                    <?php echo ats_time_ago($activity->created_at); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($activities['total'] > 20): ?>
                    <button class="ats-btn ats-btn-outline load-more-user-content" 
                            data-user-id="<?php echo $user_id; ?>"
                            data-type="activity"
                            data-page="2">
                        <?php _e('Load More Activity', 'advanced-threads'); ?>
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <p class="no-content-message"><?php _e('No recent activity.', 'advanced-threads'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get user badges tab content
     */
    private function get_user_badges_tab($user_id) {
        $badges = $this->user_manager->get_user_badges($user_id);
        
        ob_start();
        ?>
        <div class="user-badges-tab">
            <?php if (!empty($badges)): ?>
                <div class="badges-grid">
                    <?php foreach ($badges as $badge): ?>
                        <div class="badge-item badge-<?php echo esc_attr($badge->type); ?>">
                            <div class="badge-icon">
                                <i class="ats-icon-<?php echo esc_attr($badge->icon); ?>"></i>
                            </div>
                            <div class="badge-info">
                                <h5 class="badge-title"><?php echo esc_html($badge->name); ?></h5>
                                <p class="badge-description"><?php echo esc_html($badge->description); ?></p>
                                <div class="badge-date">
                                    <?php printf(__('Earned %s ago', 'advanced-threads'), ats_time_ago($badge->earned_at)); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-content-message"><?php _e('No badges earned yet.', 'advanced-threads'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get user activity summary
     */
    private function get_user_activity_summary($user_id, $limit = 5) {
        $activities = $this->user_manager->get_user_activities($user_id, 1, $limit);
        
        ob_start();
        ?>
        <div class="activity-summary">
            <?php if (!empty($activities['data'])): ?>
                <?php foreach ($activities['data'] as $activity): ?>
                    <div class="activity-item">
                        <span class="activity-description">
                            <?php echo $this->format_activity_description($activity); ?>
                        </span>
                        <span class="activity-date">
                            <?php echo ats_time_ago($activity->created_at); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-activity"><?php _e('No recent activity.', 'advanced-threads'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get activity icon based on type
     */
    private function get_activity_icon($type) {
        $icons = array(
            'thread_created' => 'plus-circle',
            'reply_posted' => 'message-circle',
            'vote_cast' => 'thumbs-up',
            'badge_earned' => 'award',
            'profile_updated' => 'user',
            'thread_followed' => 'bell'
        );
        
        return $icons[$type] ?? 'activity';
    }
    
    /**
     * Format activity description
     */
    private function format_activity_description($activity) {
        switch ($activity->type) {
            case 'thread_created':
                return sprintf(
                    __('Created thread: <a href="%s">%s</a>', 'advanced-threads'),
                    esc_url($activity->object_url),
                    esc_html($activity->object_title)
                );
            case 'reply_posted':
                return sprintf(
                    __('Posted reply in: <a href="%s">%s</a>', 'advanced-threads'),
                    esc_url($activity->object_url),
                    esc_html($activity->object_title)
                );
            case 'vote_cast':
                return sprintf(
                    __('Voted on: <a href="%s">%s</a>', 'advanced-threads'),
                    esc_url($activity->object_url),
                    esc_html($activity->object_title)
                );
            case 'badge_earned':
                return sprintf(
                    __('Earned badge: %s', 'advanced-threads'),
                    esc_html($activity->object_title)
                );
            case 'profile_updated':
                return __('Updated profile', 'advanced-threads');
            case 'thread_followed':
                return sprintf(
                    __('Started following: <a href="%s">%s</a>', 'advanced-threads'),
                    esc_url($activity->object_url),
                    esc_html($activity->object_title)
                );
            default:
                return esc_html($activity->description ?? __('Unknown activity', 'advanced-threads'));
        }
    }
}

// Initialize the frontend class
ATS_Frontend::get_instance();