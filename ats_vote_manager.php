<?php
/**
 * Advanced Threads System - Vote Manager
 * 
 * @package AdvancedThreadsSystem
 * @subpackage VoteManager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ATS_Vote_Manager {
    
    private $wpdb;
    private $votes_table;
    private $threads_table;
    private $replies_table;
    private $profiles_table;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->votes_table = $wpdb->prefix . 'ats_votes';
        $this->threads_table = $wpdb->prefix . 'ats_threads';
        $this->replies_table = $wpdb->prefix . 'ats_replies';
        $this->profiles_table = $wpdb->prefix . 'ats_user_profiles';
    }
    
    /**
     * Cast a vote on thread or reply
     *
     * @param int $user_id User ID casting vote
     * @param string $vote_type Vote type (up/down)
     * @param int $thread_id Thread ID (if voting on thread)
     * @param int $reply_id Reply ID (if voting on reply)
     * @return array Result with status and data
     */
    public function cast_vote($user_id, $vote_type, $thread_id = null, $reply_id = null) {
        // Validate inputs
        if (!ats_get_option('enable_voting', true)) {
            return array('success' => false, 'message' => __('Voting is disabled', 'advanced-threads'));
        }
        
        if (!ats_user_can('vote', $user_id)) {
            return array('success' => false, 'message' => __('You do not have permission to vote', 'advanced-threads'));
        }
        
        if (!in_array($vote_type, array('up', 'down'))) {
            return array('success' => false, 'message' => __('Invalid vote type', 'advanced-threads'));
        }
        
        if (!$thread_id && !$reply_id) {
            return array('success' => false, 'message' => __('No target specified for vote', 'advanced-threads'));
        }
        
        // Get target info and validate
        $target_info = $this->get_vote_target_info($thread_id, $reply_id);
        if (!$target_info) {
            return array('success' => false, 'message' => __('Vote target not found', 'advanced-threads'));
        }
        
        // Prevent self-voting
        if ($target_info['author_id'] == $user_id) {
            return array('success' => false, 'message' => __('You cannot vote on your own content', 'advanced-threads'));
        }
        
        // Check for existing vote
        $existing_vote = $this->get_user_vote($user_id, $thread_id, $reply_id);
        
        if ($existing_vote === $vote_type) {
            // Remove vote (toggle off)
            return $this->remove_vote($user_id, $thread_id, $reply_id, $target_info);
        } elseif ($existing_vote) {
            // Change vote type
            return $this->change_vote($user_id, $vote_type, $thread_id, $reply_id, $target_info, $existing_vote);
        } else {
            // New vote
            return $this->add_vote($user_id, $vote_type, $thread_id, $reply_id, $target_info);
        }
    }
    
    /**
     * Get user's vote on specific content
     *
     * @param int $user_id User ID
     * @param int $thread_id Thread ID (optional)
     * @param int $reply_id Reply ID (optional)
     * @return string|null Vote type or null if no vote
     */
    public function get_user_vote($user_id, $thread_id = null, $reply_id = null) {
        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT vote_type FROM {$this->votes_table} 
             WHERE user_id = %d AND thread_id = %d AND reply_id = %d",
            $user_id,
            $thread_id ?: 0,
            $reply_id ?: 0
        ));
    }
    
    /**
     * Get vote counts for content
     *
     * @param int $thread_id Thread ID (optional)
     * @param int $reply_id Reply ID (optional)
     * @return array Vote counts
     */
    public function get_vote_counts($thread_id = null, $reply_id = null) {
        if ($thread_id) {
            $result = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT upvotes, downvotes FROM {$this->threads_table} WHERE id = %d",
                $thread_id
            ));
        } else {
            $result = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT upvotes, downvotes FROM {$this->replies_table} WHERE id = %d",
                $reply_id
            ));
        }
        
        return array(
            'upvotes' => $result ? intval($result->upvotes) : 0,
            'downvotes' => $result ? intval($result->downvotes) : 0,
            'total' => $result ? (intval($result->upvotes) - intval($result->downvotes)) : 0
        );
    }
    
    /**
     * Get users who voted on content
     *
     * @param int $thread_id Thread ID (optional)
     * @param int $reply_id Reply ID (optional)
     * @param string $vote_type Vote type filter (optional)
     * @param int $limit Limit results
     * @return array Voters
     */
    public function get_voters($thread_id = null, $reply_id = null, $vote_type = null, $limit = 50) {
        $sql = "SELECT v.user_id, v.vote_type, v.created_at,
                       u.display_name, u.user_login,
                       up.avatar, up.reputation, up.badge
                FROM {$this->votes_table} v
                LEFT JOIN {$this->wpdb->users} u ON v.user_id = u.ID
                LEFT JOIN {$this->profiles_table} up ON v.user_id = up.user_id
                WHERE 1=1";
        
        $where_values = array();
        
        if ($thread_id) {
            $sql .= " AND v.thread_id = %d AND v.reply_id IS NULL";
            $where_values[] = $thread_id;
        } elseif ($reply_id) {
            $sql .= " AND v.reply_id = %d AND v.thread_id IS NULL";
            $where_values[] = $reply_id;
        }
        
        if ($vote_type) {
            $sql .= " AND v.vote_type = %s";
            $where_values[] = $vote_type;
        }
        
        $sql .= " ORDER BY v.created_at DESC LIMIT %d";
        $where_values[] = $limit;
        
        if (empty($where_values)) {
            return array();
        }
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $where_values));
    }
    
    /**
     * Get top voted content
     *
     * @param string $type Content type (thread/reply)
     * @param string $timeframe Time period (day/week/month/all)
     * @param int $limit Limit results
     * @return array Top voted content
     */
    public function get_top_voted_content($type = 'thread', $timeframe = 'week', $limit = 10) {
        $date_condition = '';
        switch ($timeframe) {
            case 'day':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
                break;
            case 'week':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }
        
        if ($type === 'thread') {
            $sql = "SELECT t.id, t.title, t.upvotes, t.downvotes, t.reply_count, t.created_at,
                           (t.upvotes - t.downvotes) as net_votes,
                           u.display_name as author_name, up.avatar as author_avatar
                    FROM {$this->threads_table} t
                    LEFT JOIN {$this->wpdb->users} u ON t.author_id = u.ID
                    LEFT JOIN {$this->profiles_table} up ON t.author_id = up.user_id
                    WHERE t.status = 'published' {$date_condition}
                    ORDER BY net_votes DESC, t.upvotes DESC
                    LIMIT %d";
        } else {
            $sql = "SELECT r.id, r.content, r.upvotes, r.downvotes, r.created_at,
                           (r.upvotes - r.downvotes) as net_votes,
                           t.title as thread_title, t.id as thread_id,
                           u.display_name as author_name, up.avatar as author_avatar
                    FROM {$this->replies_table} r
                    LEFT JOIN {$this->threads_table} t ON r.thread_id = t.id
                    LEFT JOIN {$this->wpdb->users} u ON r.author_id = u.ID
                    LEFT JOIN {$this->profiles_table} up ON r.author_id = up.user_id
                    WHERE r.status = 'published' {$date_condition}
                    ORDER BY net_votes DESC, r.upvotes DESC
                    LIMIT %d";
        }
        
        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, $limit));
        
        // Add excerpt for replies
        if ($type === 'reply') {
            foreach ($results as $result) {
                $result->excerpt = ats_get_excerpt($result->content, 100);
            }
        }
        
        return $results;
    }
    
    /**
     * Get user's voting statistics
     *
     * @param int $user_id User ID
     * @return array Voting stats
     */
    public function get_user_voting_stats($user_id) {
        $stats = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(*) as total_votes,
                COUNT(CASE WHEN vote_type = 'up' THEN 1 END) as upvotes_given,
                COUNT(CASE WHEN vote_type = 'down' THEN 1 END) as downvotes_given,
                COUNT(CASE WHEN thread_id IS NOT NULL THEN 1 END) as thread_votes,
                COUNT(CASE WHEN reply_id IS NOT NULL THEN 1 END) as reply_votes
             FROM {$this->votes_table} 
             WHERE user_id = %d",
            $user_id
        ));
        
        // Get votes received
        $votes_received = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(*) as total_received,
                COUNT(CASE WHEN v.vote_type = 'up' THEN 1 END) as upvotes_received,
                COUNT(CASE WHEN v.vote_type = 'down' THEN 1 END) as downvotes_received
             FROM {$this->votes_table} v
             LEFT JOIN {$this->threads_table} t ON v.thread_id = t.id
             LEFT JOIN {$this->replies_table} r ON v.reply_id = r.id
             WHERE t.author_id = %d OR r.author_id = %d",
            $user_id, $user_id
        ));
        
        return array(
            'votes_given' => array(
                'total' => intval($stats->total_votes ?? 0),
                'upvotes' => intval($stats->upvotes_given ?? 0),
                'downvotes' => intval($stats->downvotes_given ?? 0),
                'thread_votes' => intval($stats->thread_votes ?? 0),
                'reply_votes' => intval($stats->reply_votes ?? 0)
            ),
            'votes_received' => array(
                'total' => intval($votes_received->total_received ?? 0),
                'upvotes' => intval($votes_received->upvotes_received ?? 0),
                'downvotes' => intval($votes_received->downvotes_received ?? 0),
                'net_votes' => intval($votes_received->upvotes_received ?? 0) - intval($votes_received->downvotes_received ?? 0)
            )
        );
    }
    
    /**
     * Get voting trends over time
     *
     * @param string $period Period (day/week/month)
     * @param int $periods Number of periods
     * @return array Voting trends
     */
    public function get_voting_trends($period = 'day', $periods = 7) {
        $date_format = '';
        $date_sub = '';
        
        switch ($period) {
            case 'day':
                $date_format = '%Y-%m-%d';
                $date_sub = 'DAY';
                break;
            case 'week':
                $date_format = '%Y-%u';
                $date_sub = 'WEEK';
                break;
            case 'month':
                $date_format = '%Y-%m';
                $date_sub = 'MONTH';
                break;
        }
        
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '{$date_format}') as period,
                    COUNT(*) as total_votes,
                    COUNT(CASE WHEN vote_type = 'up' THEN 1 END) as upvotes,
                    COUNT(CASE WHEN vote_type = 'down' THEN 1 END) as downvotes
                FROM {$this->votes_table}
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$periods} {$date_sub})
                GROUP BY period
                ORDER BY period ASC";
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Remove all votes from specific user (for moderation)
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function remove_user_votes($user_id) {
        if (!current_user_can('moderate_comments')) {
            return false;
        }
        
        // Get affected content before deletion
        $affected_content = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DISTINCT thread_id, reply_id FROM {$this->votes_table} WHERE user_id = %d",
            $user_id
        ));
        
        // Delete votes
        $result = $this->wpdb->delete(
            $this->votes_table,
            array('user_id' => $user_id),
            array('%d')
        );
        
        if ($result) {
            // Update vote counts for affected content
            foreach ($affected_content as $content) {
                if ($content->thread_id) {
                    $this->update_vote_counts_for_content('thread', $content->thread_id);
                }
                if ($content->reply_id) {
                    $this->update_vote_counts_for_content('reply', $content->reply_id);
                }
            }
            
            ats_log('User votes removed', 'info', array(
                'user_id' => $user_id,
                'votes_removed' => $result
            ));
        }
        
        return (bool) $result;
    }
    
    /**
     * Add new vote
     *
     * @param int $user_id User ID
     * @param string $vote_type Vote type
     * @param int $thread_id Thread ID
     * @param int $reply_id Reply ID
     * @param array $target_info Target information
     * @return array Result
     */
    private function add_vote($user_id, $vote_type, $thread_id, $reply_id, $target_info) {
        $vote_data = array(
            'user_id' => $user_id,
            'thread_id' => $thread_id,
            'reply_id' => $reply_id,
            'vote_type' => $vote_type,
            'created_at' => current_time('mysql')
        );
        
        $result = $this->wpdb->insert(
            $this->votes_table,
            $vote_data,
            array('%d', '%d', '%d', '%s', '%s')
        );
        
        if (!$result) {
            return array('success' => false, 'message' => __('Failed to save vote', 'advanced-threads'));
        }
        
        // Update vote counts
        $this->update_vote_counts($thread_id, $reply_id, $vote_type, 'add');
        
        // Award reputation to content author
        $points = $vote_type === 'up' ? 
            ats_get_option('points_upvote_received', 2) : 
            ats_get_option('points_downvote_received', -1);
        
        if ($points != 0) {
            $user_manager = new ATS_User_Manager();
            $user_manager->award_reputation_points($target_info['author_id'], 'receive_' . $vote_type . 'vote', $points);
        }
        
        // Send notification for upvotes
        if ($vote_type === 'up' && ats_get_option('notify_thread_upvote', true)) {
            $voter = get_userdata($user_id);
            $content_type = $thread_id ? 'thread' : 'reply';
            $content_title = $thread_id ? $target_info['title'] : ats_get_excerpt($target_info['content'], 50);
            
            ats_send_notification(
                $target_info['author_id'],
                'content_upvoted',
                sprintf(__('Your %s was upvoted', 'advanced-threads'), $content_type),
                sprintf(__('%s upvoted your %s: "%s"', 'advanced-threads'), 
                    $voter->display_name, $content_type, $content_title),
                $target_info['url'],
                $thread_id ?: $reply_id,
                $content_type
            );
        }
        
        $new_counts = $this->get_vote_counts($thread_id, $reply_id);
        
        ats_log('Vote added', 'info', array(
            'user_id' => $user_id,
            'vote_type' => $vote_type,
            'thread_id' => $thread_id,
            'reply_id' => $reply_id,
            'target_author' => $target_info['author_id']
        ));
        
        do_action('ats_vote_added', $user_id, $vote_type, $thread_id, $reply_id);
        
        return array(
            'success' => true,
            'action' => 'added',
            'vote_type' => $vote_type,
            'counts' => $new_counts,
            'message' => __('Vote recorded', 'advanced-threads')
        );
    }
    
    /**
     * Remove existing vote
     *
     * @param int $user_id User ID
     * @param int $thread_id Thread ID
     * @param int $reply_id Reply ID
     * @param array $target_info Target information
     * @return array Result
     */
    private function remove_vote($user_id, $thread_id, $reply_id, $target_info) {
        $existing_vote = $this->get_user_vote($user_id, $thread_id, $reply_id);
        
        $result = $this->wpdb->delete(
            $this->votes_table,
            array(
                'user_id' => $user_id,
                'thread_id' => $thread_id ?: null,
                'reply_id' => $reply_id ?: null
            ),
            array('%d', '%d', '%d')
        );
        
        if (!$result) {
            return array('success' => false, 'message' => __('Failed to remove vote', 'advanced-threads'));
        }
        
        // Update vote counts
        $this->update_vote_counts($thread_id, $reply_id, $existing_vote, 'remove');
        
        // Remove reputation points
        $points = $existing_vote === 'up' ? 
            -ats_get_option('points_upvote_received', 2) : 
            -ats_get_option('points_downvote_received', -1);
        
        if ($points != 0) {
            $user_manager = new ATS_User_Manager();
            $user_manager->award_reputation_points($target_info['author_id'], 'remove_' . $existing_vote . 'vote', $points);
        }
        
        $new_counts = $this->get_vote_counts($thread_id, $reply_id);
        
        ats_log('Vote removed', 'info', array(
            'user_id' => $user_id,
            'removed_vote_type' => $existing_vote,
            'thread_id' => $thread_id,
            'reply_id' => $reply_id
        ));
        
        do_action('ats_vote_removed', $user_id, $existing_vote, $thread_id, $reply_id);
        
        return array(
            'success' => true,
            'action' => 'removed',
            'vote_type' => null,
            'counts' => $new_counts,
            'message' => __('Vote removed', 'advanced-threads')
        );
    }
    
    /**
     * Change existing vote type
     *
     * @param int $user_id User ID
     * @param string $new_vote_type New vote type
     * @param int $thread_id Thread ID
     * @param int $reply_id Reply ID
     * @param array $target_info Target information
     * @param string $old_vote_type Old vote type
     * @return array Result
     */
    private function change_vote($user_id, $new_vote_type, $thread_id, $reply_id, $target_info, $old_vote_type) {
        $result = $this->wpdb->update(
            $this->votes_table,
            array(
                'vote_type' => $new_vote_type,
                'created_at' => current_time('mysql')
            ),
            array(
                'user_id' => $user_id,
                'thread_id' => $thread_id ?: null,
                'reply_id' => $reply_id ?: null
            ),
            array('%s', '%s'),
            array('%d', '%d', '%d')
        );
        
        if (!$result) {
            return array('success' => false, 'message' => __('Failed to update vote', 'advanced-threads'));
        }
        
        // Update vote counts (remove old, add new)
        $this->update_vote_counts($thread_id, $reply_id, $old_vote_type, 'remove');
        $this->update_vote_counts($thread_id, $reply_id, $new_vote_type, 'add');
        
        // Update reputation points
        $old_points = $old_vote_type === 'up' ? 
            -ats_get_option('points_upvote_received', 2) : 
            -ats_get_option('points_downvote_received', -1);
        
        $new_points = $new_vote_type === 'up' ? 
            ats_get_option('points_upvote_received', 2) : 
            ats_get_option('points_downvote_received', -1);
        
        $total_points = $old_points + $new_points;
        
        if ($total_points != 0) {
            $user_manager = new ATS_User_Manager();
            $user_manager->award_reputation_points($target_info['author_id'], 'change_vote', $total_points);
        }
        
        $new_counts = $this->get_vote_counts($thread_id, $reply_id);
        
        ats_log('Vote changed', 'info', array(
            'user_id' => $user_id,
            'old_vote_type' => $old_vote_type,
            'new_vote_type' => $new_vote_type,
            'thread_id' => $thread_id,
            'reply_id' => $reply_id
        ));
        
        do_action('ats_vote_changed', $user_id, $old_vote_type, $new_vote_type, $thread_id, $reply_id);
        
        return array(
            'success' => true,
            'action' => 'changed',
            'vote_type' => $new_vote_type,
            'counts' => $new_counts,
            'message' => __('Vote updated', 'advanced-threads')
        );
    }
    
    /**
     * Update vote counts in content tables
     *
     * @param int $thread_id Thread ID
     * @param int $reply_id Reply ID
     * @param string $vote_type Vote type
     * @param string $action Action (add/remove)
     */
    private function update_vote_counts($thread_id, $reply_id, $vote_type, $action) {
        $table = $thread_id ? $this->threads_table : $this->replies_table;
        $id_field = 'id';
        $id_value = $thread_id ?: $reply_id;
        
        $column = $vote_type === 'up' ? 'upvotes' : 'downvotes';
        $operator = $action === 'add' ? '+' : '-';
        
        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$table} SET {$column} = GREATEST(0, {$column} {$operator} 1), updated_at = %s WHERE {$id_field} = %d",
            current_time('mysql'),
            intval($id_value)
        ));
        
        // Update last activity for threads
        if ($thread_id && $action === 'add') {
            $this->wpdb->update(
                $this->threads_table,
                array('last_activity' => current_time('mysql')),
                array('id' => $thread_id),
                array('%s'),
                array('%d')
            );
        }
    }
    
    /**
     * Get vote target information
     *
     * @param int $thread_id Thread ID
     * @param int $reply_id Reply ID
     * @return array|null Target info
     */
    private function get_vote_target_info($thread_id, $reply_id) {
        if ($thread_id) {
            $result = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT t.author_id, t.title, t.status, p.post_status
                 FROM {$this->threads_table} t
                 LEFT JOIN {$this->wpdb->posts} p ON t.post_id = p.ID
                 WHERE t.id = %d",
                $thread_id
            ));
            
            if ($result && $result->status === 'published' && $result->post_status === 'publish') {
                return array(
                    'author_id' => $result->author_id,
                    'title' => $result->title,
                    'url' => ats_get_thread_url($thread_id)
                );
            }
        } elseif ($reply_id) {
            $result = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT r.author_id, r.content, r.status, r.thread_id
                 FROM {$this->replies_table} r
                 WHERE r.id = %d",
                $reply_id
            ));
            
            if ($result && $result->status === 'published') {
                return array(
                    'author_id' => $result->author_id,
                    'content' => $result->content,
                    'thread_id' => $result->thread_id,
                    'url' => ats_get_thread_url($result->thread_id) . '#reply-' . $reply_id
                );
            }
        }
        
        return null;
    }
    
    /**
     * Update vote counts for specific content (recalculate from votes table)
     *
     * @param string $type Content type (thread/reply)
     * @param int $content_id Content ID
     */
    private function update_vote_counts_for_content($type, $content_id) {
        if ($type === 'thread') {
            $counts = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT 
                    COUNT(CASE WHEN vote_type = 'up' THEN 1 END) as upvotes,
                    COUNT(CASE WHEN vote_type = 'down' THEN 1 END) as downvotes
                 FROM {$this->votes_table} 
                 WHERE thread_id = %d AND reply_id IS NULL",
                $content_id
            ));
            
            $this->wpdb->update(
                $this->threads_table,
                array(
                    'upvotes' => intval($counts->upvotes),
                    'downvotes' => intval($counts->downvotes),
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $content_id),
                array('%d', '%d', '%s'),
                array('%d')
            );
        } else {
            $counts = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT 
                    COUNT(CASE WHEN vote_type = 'up' THEN 1 END) as upvotes,
                    COUNT(CASE WHEN vote_type = 'down' THEN 1 END) as downvotes
                 FROM {$this->votes_table} 
                 WHERE reply_id = %d AND thread_id IS NULL",
                $content_id
            ));
            
            $this->wpdb->update(
                $this->replies_table,
                array(
                    'upvotes' => intval($counts->upvotes),
                    'downvotes' => intval($counts->downvotes),
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $content_id),
                array('%d', '%d', '%s'),
                array('%d')
            );
        }
    }
    
    /**
     * Get vote summary for admin dashboard
     *
     * @return array Vote summary
     */
    public function get_vote_summary() {
        $summary = $this->wpdb->get_row(
            "SELECT 
                COUNT(*) as total_votes,
                COUNT(CASE WHEN vote_type = 'up' THEN 1 END) as total_upvotes,
                COUNT(CASE WHEN vote_type = 'down' THEN 1 END) as total_downvotes,
                COUNT(CASE WHEN thread_id IS NOT NULL THEN 1 END) as thread_votes,
                COUNT(CASE WHEN reply_id IS NOT NULL THEN 1 END) as reply_votes,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as votes_today,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK) THEN 1 END) as votes_this_week
             FROM {$this->votes_table}"
        );
        
        return array(
            'total_votes' => intval($summary->total_votes ?? 0),
            'total_upvotes' => intval($summary->total_upvotes ?? 0),
            'total_downvotes' => intval($summary->total_downvotes ?? 0),
            'thread_votes' => intval($summary->thread_votes ?? 0),
            'reply_votes' => intval($summary->reply_votes ?? 0),
            'votes_today' => intval($summary->votes_today ?? 0),
            'votes_this_week' => intval($summary->votes_this_week ?? 0),
            'upvote_percentage' => $summary->total_votes > 0 ? 
                round(($summary->total_upvotes / $summary->total_votes) * 100, 1) : 0
        );
    }
    
    /**
     * Detect and handle vote manipulation
     *
     * @param int $user_id User ID to check
     * @return array Detection results
     */
    public function detect_vote_manipulation($user_id) {
        $issues = array();
        
        // Check for rapid voting
        $rapid_votes = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} 
             WHERE user_id = %d AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            $user_id
        ));
        
        if ($rapid_votes > 10) {
            $issues[] = 'rapid_voting';
        }
        
        // Check for systematic downvoting
        $recent_votes = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN vote_type = 'down' THEN 1 END) as downvotes
             FROM {$this->votes_table} 
             WHERE user_id = %d AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            $user_id
        ));
        
        if ($recent_votes->total > 5 && ($recent_votes->downvotes / $recent_votes->total) > 0.8) {
            $issues[] = 'systematic_downvoting';
        }
        
        // Check for targeting specific users
        $target_votes = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT 
                COALESCE(t.author_id, r.author_id) as target_author,
                COUNT(*) as vote_count
             FROM {$this->votes_table} v
             LEFT JOIN {$this->threads_table} t ON v.thread_id = t.id
             LEFT JOIN {$this->replies_table} r ON v.reply_id = r.id
             WHERE v.user_id = %d AND v.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
             GROUP BY target_author
             HAVING vote_count > 5",
            $user_id
        ));
        
        if (!empty($target_votes)) {
            $issues[] = 'targeting_users';
        }
        
        if (!empty($issues)) {
            ats_log('Vote manipulation detected', 'warning', array(
                'user_id' => $user_id,
                'issues' => $issues,
                'rapid_votes' => $rapid_votes,
                'recent_downvote_ratio' => $recent_votes->total > 0 ? 
                    round(($recent_votes->downvotes / $recent_votes->total) * 100, 1) : 0
            ));
            
            do_action('ats_vote_manipulation_detected', $user_id, $issues);
        }
        
        return array(
            'detected' => !empty($issues),
            'issues' => $issues,
            'details' => array(
                'rapid_votes' => $rapid_votes,
                'recent_votes' => $recent_votes,
                'targeted_authors' => $target_votes
            )
        );
    }
}