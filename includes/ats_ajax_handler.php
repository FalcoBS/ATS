<?php
/**
 * Advanced Threads System - AJAX Handler
 * 
 * @package AdvancedThreadsSystem
 * @subpackage AjaxHandler
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ATS_AJAX_Handler {
    
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
        
        $this->init_ajax_hooks();
    }
    
    /**
     * Initialize AJAX hooks
     */
    private function init_ajax_hooks() {
        // Voting actions
        add_action('wp_ajax_ats_vote', array($this, 'handle_vote'));
        add_action('wp_ajax_nopriv_ats_vote', array($this, 'handle_vote_guest'));
        
        // Reply actions
        add_action('wp_ajax_ats_add_reply', array($this, 'handle_add_reply'));
        add_action('wp_ajax_ats_edit_reply', array($this, 'handle_edit_reply'));
        add_action('wp_ajax_ats_delete_reply', array($this, 'handle_delete_reply'));
        add_action('wp_ajax_ats_load_replies', array($this, 'handle_load_replies'));
        
        // Thread actions
        add_action('wp_ajax_ats_create_thread', array($this, 'handle_create_thread'));
        add_action('wp_ajax_ats_edit_thread', array($this, 'handle_edit_thread'));
        add_action('wp_ajax_ats_delete_thread', array($this, 'handle_delete_thread'));
        add_action('wp_ajax_ats_pin_thread', array($this, 'handle_pin_thread'));
        add_action('wp_ajax_ats_lock_thread', array($this, 'handle_lock_thread'));
        
        // Follow actions
        add_action('wp_ajax_ats_follow', array($this, 'handle_follow'));
        add_action('wp_ajax_ats_get_followers', array($this, 'handle_get_followers'));
        
        // User profile actions
        add_action('wp_ajax_ats_update_profile', array($this, 'handle_update_profile'));
        add_action('wp_ajax_ats_upload_avatar', array($this, 'handle_upload_avatar'));
        add_action('wp_ajax_ats_get_user_activity', array($this, 'handle_get_user_activity'));
        
        // Search and filtering
        add_action('wp_ajax_ats_search', array($this, 'handle_search'));
        add_action('wp_ajax_nopriv_ats_search', array($this, 'handle_search'));
        add_action('wp_ajax_ats_filter_threads', array($this, 'handle_filter_threads'));
        add_action('wp_ajax_nopriv_ats_filter_threads', array($this, 'handle_filter_threads'));
        
        // Moderation actions
        add_action('wp_ajax_ats_moderate_content', array($this, 'handle_moderate_content'));
        add_action('wp_ajax_ats_ban_user', array($this, 'handle_ban_user'));
        add_action('wp_ajax_ats_report_content', array($this, 'handle_report_content'));
        
        // Notifications
        add_action('wp_ajax_ats_mark_notification_read', array($this, 'handle_mark_notification_read'));
        add_action('wp_ajax_ats_get_notifications', array($this, 'handle_get_notifications'));
        
        // Image uploads
        add_action('wp_ajax_ats_upload_image', array($this, 'handle_upload_image'));
        
        // Live features
        add_action('wp_ajax_ats_heartbeat', array($this, 'handle_heartbeat'));
        add_action('wp_ajax_ats_typing_indicator', array($this, 'handle_typing_indicator'));
    }
    
    /**
     * Handle voting AJAX request
     */
    public function handle_vote() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to vote', 'advanced-threads'));
            return;
        }
        
        $user_id = get_current_user_id();
        $vote_type = sanitize_text_field($_POST['vote_type']);
        $thread_id = isset($_POST['thread_id']) ? intval($_POST['thread_id']) : null;
        $reply_id = isset($_POST['reply_id']) ? intval($_POST['reply_id']) : null;
        
        $result = $this->vote_manager->cast_vote($user_id, $vote_type, $thread_id, $reply_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Handle voting for non-logged users
     */
    public function handle_vote_guest() {
        wp_send_json_error(__('Please log in to vote', 'advanced-threads'));
    }
    
    /**
     * Handle add reply AJAX request
     */
    public function handle_add_reply() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to reply', 'advanced-threads'));
            return;
        }
        
        $thread_id = intval($_POST['thread_id']);
        $content = wp_kses_post($_POST['content']);
        $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
        $user_id = get_current_user_id();
        
        if (empty($content)) {
            wp_send_json_error(__('Reply content is required', 'advanced-threads'));
            return;
        }
        
        // Check if thread is locked
        $thread = $this->thread_manager->get_thread_by_id($thread_id, false);
        if (!$thread) {
            wp_send_json_error(__('Thread not found', 'advanced-threads'));
            return;
        }
        
        if ($thread->is_locked && !current_user_can('moderate_comments')) {
            wp_send_json_error(__('This thread is locked', 'advanced-threads'));
            return;
        }
        
        // Check permissions
        if (!ats_user_can('reply', $user_id)) {
            wp_send_json_error(__('You do not have permission to reply', 'advanced-threads'));
            return;
        }
        
        // Add reply to database
        $reply_id = $this->add_thread_reply($thread_id, $user_id, $content, $parent_id);
        
        if ($reply_id) {
            $reply = $this->get_reply_with_user_data($reply_id);
            
            // Award points
            $this->user_manager->award_reputation_points($user_id, 'create_reply');
            
            // Send notifications
            $this->send_reply_notifications($thread_id, $reply_id, $user_id, $parent_id);
            
            wp_send_json_success(array(
                'reply_id' => $reply_id,
                'reply_html' => $this->render_reply_html($reply),
                'message' => __('Reply added successfully', 'advanced-threads')
            ));
        } else {
            wp_send_json_error(__('Failed to add reply', 'advanced-threads'));
        }
    }
    
    /**
     * Handle follow/unfollow AJAX request
     */
    public function handle_follow() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to follow', 'advanced-threads'));
            return;
        }
        
        $user_id = get_current_user_id();
        $follow_type = sanitize_text_field($_POST['follow_type']);
        $target_id = intval($_POST['target_id']);
        $action = sanitize_text_field($_POST['action']); // follow or unfollow
        
        if (!in_array($follow_type, array('thread', 'user', 'category'))) {
            wp_send_json_error(__('Invalid follow type', 'advanced-threads'));
            return;
        }
        
        if (!in_array($action, array('follow', 'unfollow'))) {
            wp_send_json_error(__('Invalid action', 'advanced-threads'));
            return;
        }
        
        $result = $this->user_manager->toggle_follow($user_id, $target_id, $follow_type, $action);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Handle get followers AJAX request
     */
    public function handle_get_followers() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        $target_id = intval($_POST['target_id']);
        $follow_type = sanitize_text_field($_POST['follow_type']);
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 10;
        
        $followers = $this->user_manager->get_followers($target_id, $follow_type, $page, $per_page);
        
        wp_send_json_success(array(
            'followers' => $followers['data'],
            'total' => $followers['total'],
            'pages' => $followers['pages']
        ));
    }
    
    /**
     * Handle edit reply AJAX request
     */
    public function handle_edit_reply() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in', 'advanced-threads'));
            return;
        }
        
        $reply_id = intval($_POST['reply_id']);
        $content = wp_kses_post($_POST['content']);
        $user_id = get_current_user_id();
        
        if (empty($content)) {
            wp_send_json_error(__('Reply content is required', 'advanced-threads'));
            return;
        }
        
        // Check if user can edit this reply
        if (!$this->can_edit_reply($reply_id, $user_id)) {
            wp_send_json_error(__('You do not have permission to edit this reply', 'advanced-threads'));
            return;
        }
        
        $result = $this->thread_manager->update_reply($reply_id, $content);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Reply updated successfully', 'advanced-threads'),
                'edited_content' => $content
            ));
        } else {
            wp_send_json_error(__('Failed to update reply', 'advanced-threads'));
        }
    }
    
    /**
     * Handle delete reply AJAX request
     */
    public function handle_delete_reply() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in', 'advanced-threads'));
            return;
        }
        
        $reply_id = intval($_POST['reply_id']);
        $user_id = get_current_user_id();
        
        // Check if user can delete this reply
        if (!$this->can_delete_reply($reply_id, $user_id)) {
            wp_send_json_error(__('You do not have permission to delete this reply', 'advanced-threads'));
            return;
        }
        
        $result = $this->thread_manager->delete_reply($reply_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Reply deleted successfully', 'advanced-threads')
            ));
        } else {
            wp_send_json_error(__('Failed to delete reply', 'advanced-threads'));
        }
    }
    
    /**
     * Handle load replies AJAX request
     */
    public function handle_load_replies() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        $thread_id = intval($_POST['thread_id']);
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;
        $sort = sanitize_text_field($_POST['sort'] ?? 'newest');
        
        $replies = $this->thread_manager->get_thread_replies($thread_id, $page, $per_page, $sort);
        
        wp_send_json_success(array(
            'replies_html' => $this->render_replies_html($replies['data']),
            'total' => $replies['total'],
            'pages' => $replies['pages'],
            'current_page' => $page
        ));
    }
    
    /**
     * Handle create thread AJAX request
     */
    public function handle_create_thread() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to create a thread', 'advanced-threads'));
            return;
        }
        
        $user_id = get_current_user_id();
        $title = sanitize_text_field($_POST['title']);
        $content = wp_kses_post($_POST['content']);
        $category_id = intval($_POST['category_id']);
        $tags = isset($_POST['tags']) ? array_map('sanitize_text_field', $_POST['tags']) : array();
        
        if (empty($title) || empty($content)) {
            wp_send_json_error(__('Title and content are required', 'advanced-threads'));
            return;
        }
        
        if (!ats_user_can('create_thread', $user_id)) {
            wp_send_json_error(__('You do not have permission to create threads', 'advanced-threads'));
            return;
        }
        
        $thread_data = array(
            'title' => $title,
            'content' => $content,
            'author_id' => $user_id,
            'category_id' => $category_id,
            'tags' => $tags,
            'status' => 'published'
        );
        
        $thread_id = $this->thread_manager->create_thread($thread_data);
        
        if ($thread_id) {
            // Award points
            $this->user_manager->award_reputation_points($user_id, 'create_thread');
            
            wp_send_json_success(array(
                'thread_id' => $thread_id,
                'message' => __('Thread created successfully', 'advanced-threads'),
                'redirect_url' => get_permalink($thread_id)
            ));
        } else {
            wp_send_json_error(__('Failed to create thread', 'advanced-threads'));
        }
    }
    
    /**
     * Handle edit thread AJAX request
     */
    public function handle_edit_thread() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in', 'advanced-threads'));
            return;
        }
        
        $thread_id = intval($_POST['thread_id']);
        $title = sanitize_text_field($_POST['title']);
        $content = wp_kses_post($_POST['content']);
        $user_id = get_current_user_id();
        
        if (!$this->can_edit_thread($thread_id, $user_id)) {
            wp_send_json_error(__('You do not have permission to edit this thread', 'advanced-threads'));
            return;
        }
        
        $result = $this->thread_manager->update_thread($thread_id, array(
            'title' => $title,
            'content' => $content
        ));
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Thread updated successfully', 'advanced-threads')
            ));
        } else {
            wp_send_json_error(__('Failed to update thread', 'advanced-threads'));
        }
    }
    
    /**
     * Handle delete thread AJAX request
     */
    public function handle_delete_thread() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in', 'advanced-threads'));
            return;
        }
        
        $thread_id = intval($_POST['thread_id']);
        $user_id = get_current_user_id();
        
        if (!$this->can_delete_thread($thread_id, $user_id)) {
            wp_send_json_error(__('You do not have permission to delete this thread', 'advanced-threads'));
            return;
        }
        
        $result = $this->thread_manager->delete_thread($thread_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Thread deleted successfully', 'advanced-threads')
            ));
        } else {
            wp_send_json_error(__('Failed to delete thread', 'advanced-threads'));
        }
    }
    
    /**
     * Handle pin thread AJAX request
     */
    public function handle_pin_thread() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!current_user_can('moderate_comments')) {
            wp_send_json_error(__('You do not have permission to pin threads', 'advanced-threads'));
            return;
        }
        
        $thread_id = intval($_POST['thread_id']);
        $pin_status = $_POST['pin_status'] === 'true';
        
        $result = $this->thread_manager->set_thread_pinned($thread_id, $pin_status);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => $pin_status ? __('Thread pinned', 'advanced-threads') : __('Thread unpinned', 'advanced-threads'),
                'is_pinned' => $pin_status
            ));
        } else {
            wp_send_json_error(__('Failed to update thread', 'advanced-threads'));
        }
    }
    
    /**
     * Handle lock thread AJAX request
     */
    public function handle_lock_thread() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!current_user_can('moderate_comments')) {
            wp_send_json_error(__('You do not have permission to lock threads', 'advanced-threads'));
            return;
        }
        
        $thread_id = intval($_POST['thread_id']);
        $lock_status = $_POST['lock_status'] === 'true';
        
        $result = $this->thread_manager->set_thread_locked($thread_id, $lock_status);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => $lock_status ? __('Thread locked', 'advanced-threads') : __('Thread unlocked', 'advanced-threads'),
                'is_locked' => $lock_status
            ));
        } else {
            wp_send_json_error(__('Failed to update thread', 'advanced-threads'));
        }
    }
    
    /**
     * Handle update profile AJAX request
     */
    public function handle_update_profile() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in', 'advanced-threads'));
            return;
        }
        
        $user_id = get_current_user_id();
        $profile_data = array(
            'display_name' => sanitize_text_field($_POST['display_name']),
            'bio' => wp_kses_post($_POST['bio']),
            'location' => sanitize_text_field($_POST['location']),
            'website' => esc_url_raw($_POST['website']),
            'social_links' => array_map('esc_url_raw', $_POST['social_links'] ?? array())
        );
        
        $result = $this->user_manager->update_user_profile($user_id, $profile_data);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Profile updated successfully', 'advanced-threads')
            ));
        } else {
            wp_send_json_error(__('Failed to update profile', 'advanced-threads'));
        }
    }
    
    /**
     * Handle upload avatar AJAX request
     */
    public function handle_upload_avatar() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in', 'advanced-threads'));
            return;
        }
        
        if (empty($_FILES['avatar'])) {
            wp_send_json_error(__('No file uploaded', 'advanced-threads'));
            return;
        }
        
        $user_id = get_current_user_id();
        $result = $this->user_manager->upload_avatar($user_id, $_FILES['avatar']);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Handle get user activity AJAX request
     */
    public function handle_get_user_activity() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        $user_id = intval($_POST['user_id']);
        $activity_type = sanitize_text_field($_POST['activity_type'] ?? 'all');
        $page = intval($_POST['page'] ?? 1);
        $per_page = 10;
        
        $activity = $this->user_manager->get_user_activity($user_id, $activity_type, $page, $per_page);
        
        wp_send_json_success(array(
            'activity' => $activity['data'],
            'total' => $activity['total'],
            'pages' => $activity['pages']
        ));
    }
    
    /**
     * Handle search AJAX request
     */
    public function handle_search() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        $query = sanitize_text_field($_POST['query']);
        $search_type = sanitize_text_field($_POST['search_type'] ?? 'all');
        $page = intval($_POST['page'] ?? 1);
        $per_page = 10;
        
        if (empty($query)) {
            wp_send_json_error(__('Search query is required', 'advanced-threads'));
            return;
        }
        
        $results = $this->thread_manager->search($query, $search_type, $page, $per_page);
        
        wp_send_json_success(array(
            'results' => $results['data'],
            'total' => $results['total'],
            'pages' => $results['pages'],
            'query' => $query
        ));
    }
    
    /**
     * Handle filter threads AJAX request
     */
    public function handle_filter_threads() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        $filters = array(
            'category' => intval($_POST['category'] ?? 0),
            'tag' => sanitize_text_field($_POST['tag'] ?? ''),
            'sort' => sanitize_text_field($_POST['sort'] ?? 'newest'),
            'author' => intval($_POST['author'] ?? 0),
            'status' => sanitize_text_field($_POST['status'] ?? 'all')
        );
        
        $page = intval($_POST['page'] ?? 1);
        $per_page = 10;
        
        $threads = $this->thread_manager->get_filtered_threads($filters, $page, $per_page);
        
        wp_send_json_success(array(
            'threads_html' => $this->render_threads_html($threads['data']),
            'total' => $threads['total'],
            'pages' => $threads['pages'],
            'current_page' => $page
        ));
    }
    
    /**
     * Handle moderate content AJAX request
     */
    public function handle_moderate_content() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!current_user_can('moderate_comments')) {
            wp_send_json_error(__('You do not have permission to moderate', 'advanced-threads'));
            return;
        }
        
        $content_type = sanitize_text_field($_POST['content_type']);
        $content_id = intval($_POST['content_id']);
        $action = sanitize_text_field($_POST['action']);
        
        $result = $this->thread_manager->moderate_content($content_type, $content_id, $action);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Content moderated successfully', 'advanced-threads')
            ));
        } else {
            wp_send_json_error(__('Failed to moderate content', 'advanced-threads'));
        }
    }
    
    /**
     * Handle ban user AJAX request
     */
    public function handle_ban_user() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to ban users', 'advanced-threads'));
            return;
        }
        
        $user_id = intval($_POST['user_id']);
        $ban_duration = sanitize_text_field($_POST['ban_duration']);
        $reason = sanitize_text_field($_POST['reason']);
        
        $result = $this->user_manager->ban_user($user_id, $ban_duration, $reason);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('User banned successfully', 'advanced-threads')
            ));
        } else {
            wp_send_json_error(__('Failed to ban user', 'advanced-threads'));
        }
    }
    
    /**
     * Handle report content AJAX request
     */
    public function handle_report_content() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to report content', 'advanced-threads'));
            return;
        }
        
        $content_type = sanitize_text_field($_POST['content_type']);
        $content_id = intval($_POST['content_id']);
        $reason = sanitize_text_field($_POST['reason']);
        $description = wp_kses_post($_POST['description']);
        $reporter_id = get_current_user_id();
        
        $result = $this->thread_manager->report_content($content_type, $content_id, $reason, $description, $reporter_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Content reported successfully', 'advanced-threads')
            ));
        } else {
            wp_send_json_error(__('Failed to report content', 'advanced-threads'));
        }
    }
    
    /**
     * Handle mark notification read AJAX request
     */
    public function handle_mark_notification_read() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in', 'advanced-threads'));
            return;
        }
        
        $notification_id = intval($_POST['notification_id']);
        $user_id = get_current_user_id();
        
        $result = $this->user_manager->mark_notification_read($user_id, $notification_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Notification marked as read', 'advanced-threads')
            ));
        } else {
            wp_send_json_error(__('Failed to mark notification as read', 'advanced-threads'));
        }
    }
    
    /**
     * Handle get notifications AJAX request
     */
    public function handle_get_notifications() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in', 'advanced-threads'));
            return;
        }
        
        $user_id = get_current_user_id();
        $page = intval($_POST['page'] ?? 1);
        $per_page = 10;
        $unread_only = isset($_POST['unread_only']) ? (bool)$_POST['unread_only'] : false;
        
        $notifications = $this->user_manager->get_user_notifications($user_id, $page, $per_page, $unread_only);
        
        wp_send_json_success(array(
            'notifications' => $notifications['data'],
            'total' => $notifications['total'],
            'unread_count' => $notifications['unread_count'],
            'pages' => $notifications['pages']
        ));
    }
    
    /**
     * Handle upload image AJAX request
     */
    public function handle_upload_image() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to upload images', 'advanced-threads'));
            return;
        }
        
        if (empty($_FILES['image'])) {
            wp_send_json_error(__('No image uploaded', 'advanced-threads'));
            return;
        }
        
        $user_id = get_current_user_id();
        
        if (!ats_user_can('upload_images', $user_id)) {
            wp_send_json_error(__('You do not have permission to upload images', 'advanced-threads'));
            return;
        }
        
        $result = $this->upload_thread_image($_FILES['image']);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Handle heartbeat AJAX request
     */
    public function handle_heartbeat() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;
        $thread_id = isset($_POST['thread_id']) ? intval($_POST['thread_id']) : 0;
        
        // Update user's last activity
        if ($user_id) {
            $this->user_manager->update_last_activity($user_id);
        }
        
        $response = array(
            'server_time' => current_time('timestamp'),
            'user_online' => $user_id > 0
        );
        
        // Get thread-specific updates if thread_id is provided
        if ($thread_id) {
            $response['thread_updates'] = $this->get_thread_updates($thread_id);
            $response['new_replies_count'] = $this->get_new_replies_count($thread_id);
            $response['online_users'] = $this->get_thread_online_users($thread_id);
        }
        
        // Get user notifications if logged in
        if ($user_id) {
            $response['unread_notifications'] = $this->user_manager->get_unread_notifications_count($user_id);
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Handle typing indicator AJAX request
     */
    public function handle_typing_indicator() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Security check failed', 'advanced-threads'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in', 'advanced-threads'));
            return;
        }
        
        $user_id = get_current_user_id();
        $thread_id = intval($_POST['thread_id']);
        $is_typing = (bool)$_POST['is_typing'];
        
        // Store typing indicator in transient
        $transient_key = 'ats_typing_' . $thread_id . '_' . $user_id;
        
        if ($is_typing) {
            set_transient($transient_key, array(
                'user_id' => $user_id,
                'user_name' => wp_get_current_user()->display_name,
                'timestamp' => current_time('timestamp')
            ), 10); // 10 seconds
        } else {
            delete_transient($transient_key);
        }
        
        // Get current typing users
        $typing_users = $this->get_typing_users($thread_id);
        
        wp_send_json_success(array(
            'typing_users' => $typing_users
        ));
    }
    
    // Helper methods
    
    /**
     * Verify AJAX nonce
     */
    private function verify_nonce() {
        return wp_verify_nonce($_POST['nonce'] ?? '', 'ats_ajax_nonce');
    }
    
    /**
     * Add reply to thread
     */
    private function add_thread_reply($thread_id, $user_id, $content, $parent_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ats_replies';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'thread_id' => $thread_id,
                'user_id' => $user_id,
                'parent_id' => $parent_id,
                'content' => $content,
                'status' => 'published',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array(
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s'
            )
        );
        
        if ($result) {
            // Update thread reply count
            $this->thread_manager->update_reply_count($thread_id);
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get reply with user data
     */
    private function get_reply_with_user_data($reply_id) {
        global $wpdb;
        
        $query = $wpdb->prepare("
            SELECT r.*, u.display_name, u.user_email, u.user_login
            FROM {$wpdb->prefix}ats_replies r
            JOIN {$wpdb->users} u ON r.user_id = u.ID
            WHERE r.id = %d
        ", $reply_id);
        
        return $wpdb->get_row($query);
    }
    
    /**
     * Send reply notifications
     */
    private function send_reply_notifications($thread_id, $reply_id, $user_id, $parent_id = null) {
        // Get thread followers
        $followers = $this->user_manager->get_thread_followers($thread_id);
        
        // Get thread author
        $thread = $this->thread_manager->get_thread_by_id($thread_id, false);
        if ($thread && $thread->author_id != $user_id) {
            $followers[] = $thread->author_id;
        }
        
        // Get parent reply author if this is a nested reply
        if ($parent_id) {
            $parent_reply = $this->get_reply_with_user_data($parent_id);
            if ($parent_reply && $parent_reply->user_id != $user_id) {
                $followers[] = $parent_reply->user_id;
            }
        }
        
        // Remove duplicates and current user
        $followers = array_unique($followers);
        $followers = array_diff($followers, array($user_id));
        
        // Send notifications
        foreach ($followers as $follower_id) {
            $this->user_manager->create_notification($follower_id, 'new_reply', array(
                'thread_id' => $thread_id,
                'reply_id' => $reply_id,
                'user_id' => $user_id
            ));
        }
    }
    
    /**
     * Render reply HTML
     */
    private function render_reply_html($reply) {
        ob_start();
        include ATS_PLUGIN_DIR . 'templates/reply-item.php';
        return ob_get_clean();
    }
    
    /**
     * Render replies HTML
     */
    private function render_replies_html($replies) {
        ob_start();
        foreach ($replies as $reply) {
            include ATS_PLUGIN_DIR . 'templates/reply-item.php';
        }
        return ob_get_clean();
    }
    
    /**
     * Render threads HTML
     */
    private function render_threads_html($threads) {
        ob_start();
        foreach ($threads as $thread) {
            include ATS_PLUGIN_DIR . 'templates/thread-item.php';
        }
        return ob_get_clean();
    }
    
    /**
     * Check if user can edit reply
     */
    private function can_edit_reply($reply_id, $user_id) {
        global $wpdb;
        
        $reply = $wpdb->get_row($wpdb->prepare("
            SELECT user_id, created_at 
            FROM {$wpdb->prefix}ats_replies 
            WHERE id = %d
        ", $reply_id));
        
        if (!$reply) {
            return false;
        }
        
        // Allow moderators and admins
        if (current_user_can('moderate_comments')) {
            return true;
        }
        
        // Allow original author within edit time limit
        if ($reply->user_id == $user_id) {
            $edit_time_limit = apply_filters('ats_reply_edit_time_limit', 15 * MINUTE_IN_SECONDS);
            $created_timestamp = strtotime($reply->created_at);
            
            if (time() - $created_timestamp <= $edit_time_limit) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if user can delete reply
     */
    private function can_delete_reply($reply_id, $user_id) {
        global $wpdb;
        
        $reply = $wpdb->get_row($wpdb->prepare("
            SELECT user_id 
            FROM {$wpdb->prefix}ats_replies 
            WHERE id = %d
        ", $reply_id));
        
        if (!$reply) {
            return false;
        }
        
        // Allow moderators and admins
        if (current_user_can('moderate_comments')) {
            return true;
        }
        
        // Allow original author
        return $reply->user_id == $user_id;
    }
    
    /**
     * Check if user can edit thread
     */
    private function can_edit_thread($thread_id, $user_id) {
        $thread = get_post($thread_id);
        
        if (!$thread || $thread->post_type !== 'ats_thread') {
            return false;
        }
        
        // Allow moderators and admins
        if (current_user_can('edit_others_posts')) {
            return true;
        }
        
        // Allow original author within edit time limit
        if ($thread->post_author == $user_id) {
            $edit_time_limit = apply_filters('ats_thread_edit_time_limit', 30 * MINUTE_IN_SECONDS);
            $created_timestamp = strtotime($thread->post_date);
            
            if (time() - $created_timestamp <= $edit_time_limit) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if user can delete thread
     */
    private function can_delete_thread($thread_id, $user_id) {
        $thread = get_post($thread_id);
        
        if (!$thread || $thread->post_type !== 'ats_thread') {
            return false;
        }
        
        // Allow moderators and admins
        if (current_user_can('delete_others_posts')) {
            return true;
        }
        
        // Allow original author
        return $thread->post_author == $user_id;
    }
    
    /**
     * Upload thread image
     */
    private function upload_thread_image($file) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $uploadedfile = $file;
        $upload_overrides = array('test_form' => false);
        
        // Validate file type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($uploadedfile['type'], $allowed_types)) {
            return array(
                'success' => false,
                'message' => __('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.', 'advanced-threads')
            );
        }
        
        // Validate file size (5MB max)
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($uploadedfile['size'] > $max_size) {
            return array(
                'success' => false,
                'message' => __('File size too large. Maximum size is 5MB.', 'advanced-threads')
            );
        }
        
        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            return array(
                'success' => true,
                'url' => $movefile['url'],
                'file' => $movefile['file']
            );
        } else {
            return array(
                'success' => false,
                'message' => $movefile['error']
            );
        }
    }
    
    /**
     * Get thread updates for heartbeat
     */
    private function get_thread_updates($thread_id) {
        // Get recent replies, votes, etc.
        $last_check = isset($_POST['last_check']) ? intval($_POST['last_check']) : (time() - 30);
        
        global $wpdb;
        
        $updates = $wpdb->get_results($wpdb->prepare("
            SELECT 'reply' as type, id, user_id, created_at
            FROM {$wpdb->prefix}ats_replies 
            WHERE thread_id = %d AND UNIX_TIMESTAMP(created_at) > %d
            UNION
            SELECT 'vote' as type, id, user_id, created_at
            FROM {$wpdb->prefix}ats_votes 
            WHERE thread_id = %d AND UNIX_TIMESTAMP(created_at) > %d
            ORDER BY created_at DESC
        ", $thread_id, $last_check, $thread_id, $last_check));
        
        return $updates;
    }
    
    /**
     * Get new replies count
     */
    private function get_new_replies_count($thread_id) {
        global $wpdb;
        
        $last_check = isset($_POST['last_check']) ? intval($_POST['last_check']) : (time() - 30);
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}ats_replies 
            WHERE thread_id = %d AND UNIX_TIMESTAMP(created_at) > %d
        ", $thread_id, $last_check));
    }
    
    /**
     * Get online users for thread
     */
    private function get_thread_online_users($thread_id) {
        global $wpdb;
        
        // Get users who have been active in the last 5 minutes
        $online_threshold = time() - (5 * MINUTE_IN_SECONDS);
        
        $online_users = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT u.ID, u.display_name, u.user_email
            FROM {$wpdb->users} u
            JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = 'ats_last_activity'
            AND um.meta_value > %d
            AND u.ID IN (
                SELECT DISTINCT user_id 
                FROM {$wpdb->prefix}ats_user_activity 
                WHERE thread_id = %d AND timestamp > %d
            )
        ", $online_threshold, $thread_id, $online_threshold));
        
        return $online_users;
    }
    
    /**
     * Get typing users for thread
     */
    private function get_typing_users($thread_id) {
        global $wpdb;
        
        $typing_users = array();
        $transients = $wpdb->get_results($wpdb->prepare("
            SELECT option_name, option_value 
            FROM {$wpdb->options} 
            WHERE option_name LIKE %s
        ", '_transient_ats_typing_' . $thread_id . '_%'));
        
        foreach ($transients as $transient) {
            $data = maybe_unserialize($transient->option_value);
            if ($data && is_array($data)) {
                $typing_users[] = array(
                    'user_id' => $data['user_id'],
                    'user_name' => $data['user_name']
                );
            }
        }
        
        return $typing_users;
    }
}

// Initialize the AJAX handler
ATS_AJAX_Handler::get_instance();