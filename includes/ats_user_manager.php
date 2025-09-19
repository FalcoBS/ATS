<?php
/**
 * Advanced Threads System - User Manager
 * 
 * @package AdvancedThreadsSystem
 * @subpackage UserManager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ATS_User_Manager {
    
    private $wpdb;
    private $profiles_table;
    private $follows_table;
    private $threads_table;
    private $replies_table;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->profiles_table = $wpdb->prefix . 'ats_user_profiles';
        $this->follows_table = $wpdb->prefix . 'ats_follows';
        $this->threads_table = $wpdb->prefix . 'ats_threads';
        $this->replies_table = $wpdb->prefix . 'ats_replies';
        
        add_action('user_register', array($this, 'create_user_profile'));
        add_action('profile_update', array($this, 'sync_user_profile'));
        add_action('delete_user', array($this, 'cleanup_user_data'));
    }
    
    /**
     * Get user profile by ID
     *
     * @param int $user_id User ID
     * @param bool $create_if_missing Create profile if doesn't exist
     * @return object|null User profile object
     */
    public function get_user_profile($user_id, $create_if_missing = true) {
        $profile = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT up.*, u.display_name, u.user_email, u.user_login, u.user_registered
             FROM {$this->profiles_table} up
             RIGHT JOIN {$this->wpdb->users} u ON up.user_id = u.ID
             WHERE u.ID = %d",
            $user_id
        ));
        
        if (!$profile && $create_if_missing) {
            $this->create_user_profile($user_id);
            return $this->get_user_profile($user_id, false);
        }
        
        if ($profile) {
            // Parse JSON fields
            $profile->social_links = $profile->social_links ? json_decode($profile->social_links, true) : array();
            $profile->preferences = $profile->preferences ? json_decode($profile->preferences, true) : array();
            
            // Get additional stats
            $profile = $this->add_user_stats($profile);
            
            // Get reputation level
            $profile->reputation_level = ats_get_reputation_level($profile->reputation ?: 0);
            
            // Check if current user follows this user
            if (is_user_logged_in() && get_current_user_id() !== $user_id) {
                $profile->is_followed = $this->is_following(get_current_user_id(), $user_id, 'user');
            }
        }
        
        return apply_filters('ats_user_profile', $profile, $user_id);
    }
    
    /**
     * Create user profile
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function create_user_profile($user_id) {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }
        
        // Check if profile already exists
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT user_id FROM {$this->profiles_table} WHERE user_id = %d",
            $user_id
        ));
        
        if ($existing) {
            return true; // Already exists
        }
        
        $profile_data = array(
            'user_id' => $user_id,
            'display_name' => $user->display_name ?: $user->user_login,
            'bio' => '',
            'location' => '',
            'website' => '',
            'avatar' => '',
            'cover_image' => '',
            'reputation' => 0,
            'threads_count' => 0,
            'replies_count' => 0,
            'likes_received' => 0,
            'followers_count' => 0,
            'following_count' => 0,
            'badge' => '',
            'title' => '',
            'social_links' => json_encode(array()),
            'preferences' => json_encode($this->get_default_preferences()),
            'last_seen' => current_time('mysql'),
            'created_at' => current_time('mysql')
        );
        
        $result = $this->wpdb->insert(
            $this->profiles_table,
            $profile_data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Award welcome bonus
            $this->award_reputation_points($user_id, 'welcome_bonus', ats_get_option('welcome_bonus_points', 10));
            
            ats_log('User profile created', 'info', array('user_id' => $user_id));
            do_action('ats_user_profile_created', $user_id, $profile_data);
        }
        
        return (bool) $result;
    }
    
    /**
     * Update user profile
     *
     * @param int $user_id User ID
     * @param array $data Profile data
     * @return bool Success status
     */
    public function update_user_profile($user_id, $data) {
        // Check permissions
        if (!current_user_can('edit_user', $user_id) && get_current_user_id() !== $user_id) {
            return false;
        }
        
        $update_data = array();
        $update_format = array();
        
        // Sanitize and validate data
        if (isset($data['display_name'])) {
            $update_data['display_name'] = sanitize_text_field($data['display_name']);
            $update_format[] = '%s';
        }
        
        if (isset($data['bio'])) {
            $update_data['bio'] = sanitize_textarea_field($data['bio']);
            $update_format[] = '%s';
        }
        
        if (isset($data['location'])) {
            $update_data['location'] = sanitize_text_field($data['location']);
            $update_format[] = '%s';
        }
        
        if (isset($data['website'])) {
            $update_data['website'] = esc_url_raw($data['website']);
            $update_format[] = '%s';
        }
        
        if (isset($data['avatar'])) {
            $update_data['avatar'] = esc_url_raw($data['avatar']);
            $update_format[] = '%s';
        }
        
        if (isset($data['cover_image'])) {
            $update_data['cover_image'] = esc_url_raw($data['cover_image']);
            $update_format[] = '%s';
        }
        
        if (isset($data['title'])) {
            $update_data['title'] = sanitize_text_field($data['title']);
            $update_format[] = '%s';
        }
        
        if (isset($data['social_links']) && is_array($data['social_links'])) {
            $social_links = array();
            foreach ($data['social_links'] as $platform => $url) {
                if (!empty($url)) {
                    $social_links[sanitize_key($platform)] = esc_url_raw($url);
                }
            }
            $update_data['social_links'] = json_encode($social_links);
            $update_format[] = '%s';
        }
        
        if (isset($data['preferences']) && is_array($data['preferences'])) {
            $preferences = $this->sanitize_preferences($data['preferences']);
            $update_data['preferences'] = json_encode($preferences);
            $update_format[] = '%s';
        }
        
        // Admin-only fields
        if (current_user_can('edit_users')) {
            if (isset($data['badge'])) {
                $update_data['badge'] = sanitize_text_field($data['badge']);
                $update_format[] = '%s';
            }
            
            if (isset($data['reputation'])) {
                $update_data['reputation'] = intval($data['reputation']);
                $update_format[] = '%d';
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $update_data['updated_at'] = current_time('mysql');
        $update_format[] = '%s';
        
        $result = $this->wpdb->update(
            $this->profiles_table,
            $update_data,
            array('user_id' => $user_id),
            $update_format,
            array('%d')
        );
        
        if ($result !== false) {
            // Update WordPress user if needed
            if (isset($data['display_name'])) {
                wp_update_user(array(
                    'ID' => $user_id,
                    'display_name' => $data['display_name']
                ));
            }
            
            ats_log('User profile updated', 'info', array(
                'user_id' => $user_id,
                'updated_fields' => array_keys($update_data)
            ));
            
            do_action('ats_user_profile_updated', $user_id, $update_data);
        }
        
        return $result !== false;
    }
    
    /**
     * Follow/unfollow user, thread, or category
     *
     * @param int $follower_id Follower user ID
     * @param int $following_id Following object ID
     * @param string $following_type Following type (user, thread, category)
     * @param bool $follow Follow or unfollow
     * @return bool Success status
     */
    public function follow($follower_id, $following_id, $following_type = 'user', $follow = true) {
        if (!ats_get_option('enable_following', true)) {
            return false;
        }
        
        // Validate following type
        $valid_types = array('user', 'thread', 'category');
        if (!in_array($following_type, $valid_types)) {
            return false;
        }
        
        // Can't follow yourself
        if ($following_type === 'user' && $follower_id === $following_id) {
            return false;
        }
        
        if ($follow) {
            // Follow
            $result = $this->wpdb->replace(
                $this->follows_table,
                array(
                    'follower_id' => $follower_id,
                    'following_id' => $following_id,
                    'following_type' => $following_type,
                    'notifications' => 1,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%d', '%s')
            );
            
            if ($result) {
                // Send notification to followed user
                if ($following_type === 'user') {
                    $follower = get_userdata($follower_id);
                    if ($follower) {
                        ats_send_notification(
                            $following_id,
                            'new_follower',
                            __('New follower', 'advanced-threads'),
                            sprintf(__('%s is now following you', 'advanced-threads'), $follower->display_name),
                            ats_get_user_profile_url($follower_id),
                            $follower_id,
                            'user'
                        );
                    }
                }
                
                // Update follower counts
                $this->update_follow_counts($follower_id, $following_id, $following_type, 'increase');
                
                ats_log('Follow action', 'info', array(
                    'follower_id' => $follower_id,
                    'following_id' => $following_id,
                    'following_type' => $following_type,
                    'action' => 'follow'
                ));
                
                do_action('ats_user_followed', $follower_id, $following_id, $following_type);
            }
        } else {
            // Unfollow
            $result = $this->wpdb->delete(
                $this->follows_table,
                array(
                    'follower_id' => $follower_id,
                    'following_id' => $following_id,
                    'following_type' => $following_type
                ),
                array('%d', '%d', '%s')
            );
            
            if ($result) {
                // Update follower counts
                $this->update_follow_counts($follower_id, $following_id, $following_type, 'decrease');
                
                ats_log('Unfollow action', 'info', array(
                    'follower_id' => $follower_id,
                    'following_id' => $following_id,
                    'following_type' => $following_type,
                    'action' => 'unfollow'
                ));
                
                do_action('ats_user_unfollowed', $follower_id, $following_id, $following_type);
            }
        }
        
        return (bool) $result;
    }
    
    /**
     * Check if user is following another user/thread/category
     *
     * @param int $follower_id Follower user ID
     * @param int $following_id Following object ID
     * @param string $following_type Following type
     * @return bool Following status
     */
    public function is_following($follower_id, $following_id, $following_type = 'user') {
        return (bool) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->follows_table} 
             WHERE follower_id = %d AND following_id = %d AND following_type = %s",
            $follower_id,
            $following_id,
            $following_type
        ));
    }
    
    /**
     * Get user followers
     *
     * @param int $user_id User ID
     * @param int $limit Limit results
     * @return array Followers
     */
    public function get_user_followers($user_id, $limit = 50) {
        $sql = "SELECT f.follower_id, f.created_at,
                       u.display_name, u.user_login,
                       up.avatar, up.reputation, up.badge
                FROM {$this->follows_table} f
                LEFT JOIN {$this->wpdb->users} u ON f.follower_id = u.ID
                LEFT JOIN {$this->profiles_table} up ON f.follower_id = up.user_id
                WHERE f.following_id = %d AND f.following_type = 'user'
                ORDER BY f.created_at DESC
                LIMIT %d";
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $user_id, $limit));
    }
    
    /**
     * Get users that user is following
     *
     * @param int $user_id User ID
     * @param string $type Following type (user, thread, category)
     * @param int $limit Limit results
     * @return array Following list
     */
    public function get_user_following($user_id, $type = 'user', $limit = 50) {
        if ($type === 'user') {
            $sql = "SELECT f.following_id, f.created_at,
                           u.display_name, u.user_login,
                           up.avatar, up.reputation, up.badge
                    FROM {$this->follows_table} f
                    LEFT JOIN {$this->wpdb->users} u ON f.following_id = u.ID
                    LEFT JOIN {$this->profiles_table} up ON f.following_id = up.user_id
                    WHERE f.follower_id = %d AND f.following_type = 'user'
                    ORDER BY f.created_at DESC
                    LIMIT %d";
        } elseif ($type === 'thread') {
            $sql = "SELECT f.following_id, f.created_at,
                           t.title, t.author_id, t.reply_count, t.upvotes
                    FROM {$this->follows_table} f
                    LEFT JOIN {$this->threads_table} t ON f.following_id = t.id
                    WHERE f.follower_id = %d AND f.following_type = 'thread'
                    ORDER BY f.created_at DESC
                    LIMIT %d";
        } else {
            // Categories
            $categories_table = $this->wpdb->prefix . 'ats_categories';
            $sql = "SELECT f.following_id, f.created_at,
                           c.name, c.slug, c.color, c.thread_count
                    FROM {$this->follows_table} f
                    LEFT JOIN {$categories_table} c ON f.following_id = c.id
                    WHERE f.follower_id = %d AND f.following_type = 'category'
                    ORDER BY f.created_at DESC
                    LIMIT %d";
        }
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $user_id, $limit));
    }
    
    /**
     * Award reputation points to user
     *
     * @param int $user_id User ID
     * @param string $action Action type
     * @param int $points Points to award
     * @return bool Success status
     */
    public function award_reputation_points($user_id, $action, $points = null) {
        if (!ats_get_option('enable_reputation', true)) {
            return false;
        }
        
        $points_map = array(
            'welcome_bonus' => ats_get_option('welcome_bonus_points', 10),
            'create_thread' => ats_get_option('points_new_thread', 10),
            'create_reply' => ats_get_option('points_new_reply', 5),
            'receive_upvote' => ats_get_option('points_upvote_received', 2),
            'receive_downvote' => ats_get_option('points_downvote_received', -1),
            'solution_marked' => ats_get_option('points_solution_marked', 15),
            'daily_login' => ats_get_option('points_daily_login', 1),
            'profile_completed' => ats_get_option('points_profile_completed', 25),
            'first_thread' => ats_get_option('points_first_thread', 50),
            'first_reply' => ats_get_option('points_first_reply', 25)
        );
        
        if ($points === null) {
            $points = $points_map[$action] ?? 0;
        }
        
        if ($points == 0) {
            return false;
        }
        
        $result = $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->profiles_table} 
             SET reputation = GREATEST(0, reputation + %d), updated_at = %s 
             WHERE user_id = %d",
            $points,
            current_time('mysql'),
            $user_id
        ));
        
        if ($result) {
            // Check for level up
            $new_reputation = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT reputation FROM {$this->profiles_table} WHERE user_id = %d",
                $user_id
            ));
            
            $this->check_reputation_milestones($user_id, $new_reputation, $action);
            
            ats_log('Reputation points awarded', 'info', array(
                'user_id' => $user_id,
                'action' => $action,
                'points' => $points,
                'new_total' => $new_reputation
            ));
            
            do_action('ats_reputation_awarded', $user_id, $action, $points, $new_reputation);
        }
        
        return (bool) $result;
    }
    
    /**
     * Get user activity feed
     *
     * @param int $user_id User ID
     * @param int $limit Limit results
     * @return array Activity feed
     */
    public function get_user_activity($user_id, $limit = 20) {
        $activities = array();
        
        // Get recent threads
        $threads = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT 'thread' as type, id as item_id, title, created_at, upvotes, reply_count
             FROM {$this->threads_table} 
             WHERE author_id = %d AND status = 'published'
             ORDER BY created_at DESC 
             LIMIT %d",
            $user_id,
            $limit
        ));
        
        foreach ($threads as $thread) {
            $activities[] = array(
                'type' => 'thread',
                'action' => 'created',
                'item_id' => $thread->item_id,
                'title' => $thread->title,
                'created_at' => $thread->created_at,
                'metadata' => array(
                    'upvotes' => $thread->upvotes,
                    'reply_count' => $thread->reply_count
                )
            );
        }
        
        // Get recent replies
        $replies = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT 'reply' as type, r.id as item_id, r.content, r.created_at, r.upvotes,
                    t.title as thread_title, t.id as thread_id
             FROM {$this->replies_table} r
             LEFT JOIN {$this->threads_table} t ON r.thread_id = t.id
             WHERE r.author_id = %d AND r.status = 'published'
             ORDER BY r.created_at DESC 
             LIMIT %d",
            $user_id,
            $limit
        ));
        
        foreach ($replies as $reply) {
            $activities[] = array(
                'type' => 'reply',
                'action' => 'posted',
                'item_id' => $reply->item_id,
                'content' => ats_get_excerpt($reply->content, 100),
                'created_at' => $reply->created_at,
                'metadata' => array(
                    'thread_title' => $reply->thread_title,
                    'thread_id' => $reply->thread_id,
                    'upvotes' => $reply->upvotes
                )
            );
        }
        
        // Sort by date
        usort($activities, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        // Limit final results
        $activities = array_slice($activities, 0, $limit);
        
        return apply_filters('ats_user_activity', $activities, $user_id);
    }
    
    /**
     * Update user last seen timestamp
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function update_last_seen($user_id) {
        return $this->wpdb->update(
            $this->profiles_table,
            array('last_seen' => current_time('mysql')),
            array('user_id' => $user_id),
            array('%s'),
            array('%d')
        ) !== false;
    }
    
    /**
     * Get user leaderboard
     *
     * @param string $criteria Sort criteria (reputation, threads, replies, likes)
     * @param int $limit Limit results
     * @param string $timeframe Time period (all, month, week)
     * @return array Leaderboard
     */
    public function get_leaderboard($criteria = 'reputation', $limit = 50, $timeframe = 'all') {
        $valid_criteria = array('reputation', 'threads', 'replies', 'likes');
        if (!in_array($criteria, $valid_criteria)) {
            $criteria = 'reputation';
        }
        
        $order_by_map = array(
            'reputation' => 'up.reputation',
            'threads' => 'up.threads_count',
            'replies' => 'up.replies_count',
            'likes' => 'up.likes_received'
        );
        
        $sql = "SELECT up.user_id, up.reputation, up.threads_count, up.replies_count, up.likes_received, up.badge,
                       u.display_name, u.user_login, u.user_registered,
                       up.avatar, up.last_seen
                FROM {$this->profiles_table} up
                LEFT JOIN {$this->wpdb->users} u ON up.user_id = u.ID
                WHERE u.ID IS NOT NULL";
        
        // Add timeframe filter if not 'all'
        if ($timeframe === 'month') {
            $sql .= " AND u.user_registered >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        } elseif ($timeframe === 'week') {
            $sql .= " AND u.user_registered >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        }
        
        $sql .= " ORDER BY {$order_by_map[$criteria]} DESC, up.user_id ASC
                  LIMIT %d";
        
        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $limit));
        
        // Add ranking
        foreach ($results as $index => $user) {
            $user->rank = $index + 1;
            $user->reputation_level = ats_get_reputation_level($user->reputation);
        }
        
        return apply_filters('ats_leaderboard', $results, $criteria, $timeframe);
    }
    
    /**
     * Search users
     *
     * @param string $search_term Search term
     * @param array $args Search arguments
     * @return array Search results
     */
    public function search_users($search_term, $args = array()) {
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'order_by' => 'relevance'
        );
        
        $args = wp_parse_args($args, $defaults);
        $search_term = $this->wpdb->esc_like($search_term);
        
        $sql = "SELECT up.user_id, up.reputation, up.threads_count, up.replies_count, up.badge, up.bio,
                       u.display_name, u.user_login, u.user_email,
                       up.avatar, up.location, up.last_seen,
                       (CASE 
                        WHEN u.display_name LIKE %s THEN 3
                        WHEN u.user_login LIKE %s THEN 2
                        WHEN up.bio LIKE %s THEN 1
                        ELSE 0 END) as relevance_score
                FROM {$this->profiles_table} up
                LEFT JOIN {$this->wpdb->users} u ON up.user_id = u.ID
                WHERE u.ID IS NOT NULL
                AND (u.display_name LIKE %s OR u.user_login LIKE %s OR up.bio LIKE %s)";
        
        $search_like = '%' . $search_term . '%';
        $values = array($search_like, $search_like, $search_like, $search_like, $search_like, $search_like);
        
        if ($args['order_by'] === 'relevance') {
            $sql .= " ORDER BY relevance_score DESC, up.reputation DESC";
        } else {
            $sql .= " ORDER BY up.reputation DESC";
        }
        
        $sql .= " LIMIT %d OFFSET %d";
        $values[] = intval($args['limit']);
        $values[] = intval($args['offset']);
        
        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $values));
        
        foreach ($results as $user) {
            $user->reputation_level = ats_get_reputation_level($user->reputation);
            if (is_user_logged_in()) {
                $user->is_followed = $this->is_following(get_current_user_id(), $user->user_id, 'user');
            }
        }
        
        return $results;
    }
    
    /**
     * Sync WordPress user data with profile
     *
     * @param int $user_id User ID
     */
    public function sync_user_profile($user_id) {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return;
        }
        
        $this->wpdb->update(
            $this->profiles_table,
            array(
                'display_name' => $user->display_name,
                'updated_at' => current_time('mysql')
            ),
            array('user_id' => $user_id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Clean up user data on user deletion
     *
     * @param int $user_id User ID
     */
    public function cleanup_user_data($user_id) {
        // Delete profile
        $this->wpdb->delete(
            $this->profiles_table,
            array('user_id' => $user_id),
            array('%d')
        );
        
        // Delete follows
        $this->wpdb->delete(
            $this->follows_table,
            array('follower_id' => $user_id),
            array('%d')
        );
        
        $this->wpdb->delete(
            $this->follows_table,
            array('following_id' => $user_id, 'following_type' => 'user'),
            array('%d', '%s')
        );
        
        // Delete votes
        $votes_table = $this->wpdb->prefix . 'ats_votes';
        $this->wpdb->delete(
            $votes_table,
            array('user_id' => $user_id),
            array('%d')
        );
        
        // Delete notifications
        $notifications_table = $this->wpdb->prefix . 'ats_notifications';
        $this->wpdb->delete(
            $notifications_table,
            array('user_id' => $user_id),
            array('%d')
        );
        
        ats_log('User data cleaned up', 'info', array('user_id' => $user_id));
    }
    
    /**
     * Update all user statistics
     */
    public function update_all_user_stats() {
        // Update thread counts
        $this->wpdb->query(
            "UPDATE {$this->profiles_table} up
             SET threads_count = (
                 SELECT COUNT(*) FROM {$this->threads_table} t 
                 WHERE t.author_id = up.user_id AND t.status = 'published'
             )"
        );
        
        // Update reply counts
        $this->wpdb->query(
            "UPDATE {$this->profiles_table} up
             SET replies_count = (
                 SELECT COUNT(*) FROM {$this->replies_table} r 
                 WHERE r.author_id = up.user_id AND r.status = 'published'
             )"
        );
        
        // Update likes received counts
        $votes_table = $this->wpdb->prefix . 'ats_votes';
        $this->wpdb->query(
            "UPDATE {$this->profiles_table} up
             SET likes_received = (
                 SELECT COUNT(*) FROM {$votes_table} v
                 LEFT JOIN {$this->threads_table} t ON v.thread_id = t.id
                 LEFT JOIN {$this->replies_table} r ON v.reply_id = r.id
                 WHERE (t.author_id = up.user_id OR r.author_id = up.user_id) 
                 AND v.vote_type = 'up'
             )"
        );
        
        // Update follower counts
        $this->wpdb->query(
            "UPDATE {$this->profiles_table} up
             SET followers_count = (
                 SELECT COUNT(*) FROM {$this->follows_table} f 
                 WHERE f.following_id = up.user_id AND f.following_type = 'user'
             )"
        );
        
        // Update following counts
        $this->wpdb->query(
            "UPDATE {$this->profiles_table} up
             SET following_count = (
                 SELECT COUNT(*) FROM {$this->follows_table} f 
                 WHERE f.follower_id = up.user_id
             )"
        );
        
        ats_log('All user stats updated', 'info');
    }
    
    /**
     * Get default user preferences
     *
     * @return array Default preferences
     */
    private function get_default_preferences() {
        return array(
            'email_notifications' => array(
                'new_follower' => true,
                'new_reply' => true,
                'thread_upvote' => true,
                'reply_upvote' => true,
                'mention' => true,
                'weekly_digest' => true
            ),
            'privacy' => array(
                'show_email' => false,
                'show_last_seen' => true,
                'allow_follow' => true,
                'show_activity' => true
            ),
            'display' => array(
                'threads_per_page' => 20,
                'replies_per_page' => 50,
                'theme' => 'auto',
                'compact_view' => false
            )
        );
    }
    
    /**
     * Sanitize user preferences
     *
     * @param array $preferences User preferences
     * @return array Sanitized preferences
     */
    private function sanitize_preferences($preferences) {
        $defaults = $this->get_default_preferences();
        $sanitized = array();
        
        foreach ($defaults as $section => $section_defaults) {
            $sanitized[$section] = array();
            
            if (isset($preferences[$section]) && is_array($preferences[$section])) {
                foreach ($section_defaults as $key => $default_value) {
                    if (isset($preferences[$section][$key])) {
                        if (is_bool($default_value)) {
                            $sanitized[$section][$key] = (bool) $preferences[$section][$key];
                        } elseif (is_int($default_value)) {
                            $sanitized[$section][$key] = intval($preferences[$section][$key]);
                        } else {
                            $sanitized[$section][$key] = sanitize_text_field($preferences[$section][$key]);
                        }
                    } else {
                        $sanitized[$section][$key] = $default_value;
                    }
                }
            } else {
                $sanitized[$section] = $section_defaults;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Add user statistics to profile object
     *
     * @param object $profile Profile object
     * @return object Profile with added stats
     */
    private function add_user_stats($profile) {
        // Add online status
        if ($profile->last_seen) {
            $last_seen_timestamp = strtotime($profile->last_seen);
            $profile->is_online = (time() - $last_seen_timestamp) < 300; // 5 minutes
            $profile->last_seen_human = ats_time_ago($profile->last_seen);
        } else {
            $profile->is_online = false;
            $profile->last_seen_human = __('Never', 'advanced-threads');
        }
        
        // Calculate activity score
        $days_since_registration = $profile->user_registered ? 
            max(1, (time() - strtotime($profile->user_registered)) / DAY_IN_SECONDS) : 1;
        
        $profile->activity_score = round(
            ($profile->threads_count * 3 + $profile->replies_count + $profile->likes_received * 0.5) / $days_since_registration,
            2
        );
        
        // Get recent activity count
        $recent_activity = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM (
                SELECT created_at FROM {$this->threads_table} 
                WHERE author_id = %d AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                UNION ALL
                SELECT created_at FROM {$this->replies_table} 
                WHERE author_id = %d AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ) as recent",
            $profile->user_id,
            $profile->user_id
        ));
        
        $profile->recent_activity_count = intval($recent_activity);
        
        return $profile;
    }
    
    /**
     * Update follow counts
     *
     * @param int $follower_id Follower ID
     * @param int $following_id Following ID
     * @param string $following_type Following type
     * @param string $action increase or decrease
     */
    private function update_follow_counts($follower_id, $following_id, $following_type, $action) {
        $operator = $action === 'increase' ? '+' : '-';
        
        // Update follower's following count
        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->profiles_table} 
             SET following_count = GREATEST(0, following_count {$operator} 1), updated_at = %s 
             WHERE user_id = %d",
            current_time('mysql'),
            $follower_id
        ));
        
        // Update following user's followers count (only for user follows)
        if ($following_type === 'user') {
            $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->profiles_table} 
                 SET followers_count = GREATEST(0, followers_count {$operator} 1), updated_at = %s 
                 WHERE user_id = %d",
                current_time('mysql'),
                $following_id
            ));
        }
    }
    
    /**
     * Check for reputation milestones and award badges
     *
     * @param int $user_id User ID
     * @param int $new_reputation New reputation total
     * @param string $action Action that triggered reputation change
     */
    private function check_reputation_milestones($user_id, $new_reputation, $action) {
        $milestones = array(
            100 => 'newcomer',
            500 => 'contributor', 
            1000 => 'regular',
            2500 => 'expert',
            5000 => 'guru',
            10000 => 'legend'
        );
        
        $current_badge = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT badge FROM {$this->profiles_table} WHERE user_id = %d",
            $user_id
        ));
        
        $new_badge = '';
        foreach ($milestones as $points => $badge) {
            if ($new_reputation >= $points) {
                $new_badge = $badge;
            }
        }
        
        // Update badge if changed
        if ($new_badge && $new_badge !== $current_badge) {
            $this->wpdb->update(
                $this->profiles_table,
                array('badge' => $new_badge, 'updated_at' => current_time('mysql')),
                array('user_id' => $user_id),
                array('%s', '%s'),
                array('%d')
            );
            
            // Send congratulatory notification
            ats_send_notification(
                $user_id,
                'badge_earned',
                __('New badge earned!', 'advanced-threads'),
                sprintf(__('Congratulations! You earned the "%s" badge.', 'advanced-threads'), ucfirst($new_badge)),
                ats_get_user_profile_url($user_id),
                $user_id,
                'badge'
            );
            
            ats_log('Badge awarded', 'info', array(
                'user_id' => $user_id,
                'old_badge' => $current_badge,
                'new_badge' => $new_badge,
                'reputation' => $new_reputation
            ));
            
            do_action('ats_badge_awarded', $user_id, $new_badge, $new_reputation);
        }
        
        // Check for special action-based achievements
        $this->check_action_achievements($user_id, $action);
    }
    
    /**
     * Check for action-based achievements
     *
     * @param int $user_id User ID  
     * @param string $action Action performed
     */
    private function check_action_achievements($user_id, $action) {
        $achievements = array();
        
        switch ($action) {
            case 'create_thread':
                $thread_count = $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT threads_count FROM {$this->profiles_table} WHERE user_id = %d",
                    $user_id
                ));
                
                if ($thread_count == 1) {
                    $achievements[] = 'first_thread';
                } elseif ($thread_count == 10) {
                    $achievements[] = 'thread_starter';
                } elseif ($thread_count == 100) {
                    $achievements[] = 'thread_master';
                }
                break;
                
            case 'create_reply':
                $reply_count = $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT replies_count FROM {$this->profiles_table} WHERE user_id = %d",
                    $user_id
                ));
                
                if ($reply_count == 1) {
                    $achievements[] = 'first_reply';
                } elseif ($reply_count == 50) {
                    $achievements[] = 'conversationalist';
                } elseif ($reply_count == 500) {
                    $achievements[] = 'discussion_expert';
                }
                break;
        }
        
        // Award achievements
        foreach ($achievements as $achievement) {
            $this->award_achievement($user_id, $achievement);
        }
    }
    
    /**
     * Award achievement to user
     *
     * @param int $user_id User ID
     * @param string $achievement Achievement name
     */
    private function award_achievement($user_id, $achievement) {
        // Store achievements in user meta for now
        $user_achievements = get_user_meta($user_id, 'ats_achievements', true) ?: array();
        
        if (!in_array($achievement, $user_achievements)) {
            $user_achievements[] = $achievement;
            update_user_meta($user_id, 'ats_achievements', $user_achievements);
            
            // Send notification
            $achievement_titles = array(
                'first_thread' => __('First Thread', 'advanced-threads'),
                'first_reply' => __('First Reply', 'advanced-threads'),
                'thread_starter' => __('Thread Starter', 'advanced-threads'),
                'thread_master' => __('Thread Master', 'advanced-threads'),
                'conversationalist' => __('Conversationalist', 'advanced-threads'),
                'discussion_expert' => __('Discussion Expert', 'advanced-threads')
            );
            
            $title = $achievement_titles[$achievement] ?? ucfirst(str_replace('_', ' ', $achievement));
            
            ats_send_notification(
                $user_id,
                'achievement_earned',
                __('Achievement unlocked!', 'advanced-threads'),
                sprintf(__('You earned the "%s" achievement!', 'advanced-threads'), $title),
                ats_get_user_profile_url($user_id),
                $user_id,
                'achievement'
            );
            
            do_action('ats_achievement_awarded', $user_id, $achievement);
        }
    }
    
    /**
     * Get user's achievements
     *
     * @param int $user_id User ID
     * @return array User achievements
     */
    public function get_user_achievements($user_id) {
        return get_user_meta($user_id, 'ats_achievements', true) ?: array();
    }
    
    /**
     * Ban/unban user
     *
     * @param int $user_id User ID
     * @param bool $banned Ban status
     * @param string $reason Ban reason
     * @return bool Success status
     */
    public function set_user_banned($user_id, $banned = true, $reason = '') {
        if (!current_user_can('edit_users')) {
            return false;
        }
        
        $ban_data = array(
            'banned' => $banned,
            'ban_reason' => $reason,
            'ban_date' => $banned ? current_time('mysql') : null,
            'updated_at' => current_time('mysql')
        );
        
        // Add banned column if it doesn't exist
        $this->maybe_add_ban_columns();
        
        $result = $this->wpdb->update(
            $this->profiles_table,
            $ban_data,
            array('user_id' => $user_id),
            array('%d', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            if ($banned) {
                // Send ban notification
                ats_send_notification(
                    $user_id,
                    'user_banned',
                    __('Account suspended', 'advanced-threads'),
                    sprintf(__('Your account has been suspended. Reason: %s', 'advanced-threads'), $reason ?: __('Violation of community guidelines', 'advanced-threads')),
                    '',
                    $user_id,
                    'moderation'
                );
            }
            
            ats_log('User ban status changed', 'info', array(
                'user_id' => $user_id,
                'banned' => $banned,
                'reason' => $reason
            ));
            
            do_action('ats_user_ban_changed', $user_id, $banned, $reason);
        }
        
        return $result !== false;
    }
    
    /**
     * Check if user is banned
     *
     * @param int $user_id User ID
     * @return bool Ban status
     */
    public function is_user_banned($user_id) {
        return (bool) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT banned FROM {$this->profiles_table} WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Add ban columns if they don't exist
     */
    private function maybe_add_ban_columns() {
        $column_exists = $this->wpdb->get_results(
            "SHOW COLUMNS FROM {$this->profiles_table} LIKE 'banned'"
        );
        
        if (empty($column_exists)) {
            $this->wpdb->query(
                "ALTER TABLE {$this->profiles_table} 
                 ADD COLUMN banned tinyint(1) DEFAULT 0 AFTER preferences,
                 ADD COLUMN ban_reason text AFTER banned,
                 ADD COLUMN ban_date datetime NULL AFTER ban_reason"
            );
        }
    }
}