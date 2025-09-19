<?php
/**
 * Advanced Threads System - Thread Manager (reconstructed)
 *
 * Handles CRUD operations for threads and replies, plus simple listing/trending.
 *
 * @package AdvancedThreadsSystem
 * @subpackage ThreadManager
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ATS_Thread_Manager')) {

class ATS_Thread_Manager {

    /** @var wpdb */
    private $wpdb;

    private $threads_table;
    private $replies_table;
    private $votes_table;
    private $views_table;
    private $profiles_table;
    private $categories_table;
    private $follows_table;
    private $notifications_table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        $prefix = $wpdb->prefix;
        $this->threads_table       = $prefix . 'ats_threads';
        $this->replies_table       = $prefix . 'ats_replies';
        $this->votes_table         = $prefix . 'ats_votes';
        $this->views_table         = $prefix . 'ats_thread_views';
        $this->profiles_table      = $prefix . 'ats_user_profiles';
        $this->categories_table    = $prefix . 'ats_categories';
        $this->follows_table       = $prefix . 'ats_follows';
        $this->notifications_table = $prefix . 'ats_notifications';
    }

    /** ========== Threads ========== */

    /**
     * Create a new thread
     * @param array $data
     * @return int|false
     */
    public function create_thread($data) {
        $defaults = array(
            'title'     => '',
            'content'   => '',
            'author_id' => 0,
            'excerpt'   => '',
            'category'  => '',
            'status'    => 'published', // or 'pending'
        );
        $data = wp_parse_args($data, $defaults);

        if (empty($data['title']) || empty($data['content']) || empty($data['author_id'])) {
            return false;
        }

        $insert = array(
            'title'        => sanitize_text_field($data['title']),
            'content'      => function_exists('ats_sanitize_content') ? ats_sanitize_content($data['content']) : wp_kses_post($data['content']),
            'author_id'    => (int) $data['author_id'],
            'excerpt'      => !empty($data['excerpt']) ? sanitize_text_field($data['excerpt']) : (function_exists('ats_get_excerpt') ? ats_get_excerpt($data['content']) : wp_strip_all_tags(wp_trim_words($data['content'], 40))),
            'category'     => sanitize_text_field($data['category']),
            'status'       => sanitize_text_field($data['status']),
            'upvotes'      => 0,
            'downvotes'    => 0,
            'views'        => 0,
            'last_activity'=> current_time('mysql'),
            'created_at'   => current_time('mysql'),
            'updated_at'   => current_time('mysql'),
        );
        $format = array('%s','%s','%d','%s','%s','%s','%d','%d','%d','%s','%s','%s');

        $ok = $this->wpdb->insert($this->threads_table, $insert, $format);
        if (!$ok) {
            return false;
        }
        return (int) $this->wpdb->insert_id;
    }

    /**
     * Update a thread
     */
    public function update_thread($thread_id, $data) {
        $thread_id = (int) $thread_id;
        if ($thread_id <= 0) return false;

        $allowed = array('title','content','excerpt','category','status','last_activity','upvotes','downvotes','views');
        $update = array();
        $format = array();

        foreach ($allowed as $key) {
            if (!isset($data[$key])) continue;
            switch ($key) {
                case 'title':
                case 'excerpt':
                case 'category':
                case 'status':
                    $update[$key] = sanitize_text_field($data[$key]); $format[] = '%s'; break;
                case 'content':
                    $update[$key] = function_exists('ats_sanitize_content') ? ats_sanitize_content($data[$key]) : wp_kses_post($data[$key]); $format[] = '%s'; break;
                case 'last_activity':
                    $update[$key] = sanitize_text_field($data[$key]); $format[] = '%s'; break;
                case 'upvotes':
                case 'downvotes':
                case 'views':
                    $update[$key] = (int)$data[$key]; $format[] = '%d'; break;
            }
        }
        if (empty($update)) return false;

        $update['updated_at'] = current_time('mysql');
        $format[] = '%s';

        return false !== $this->wpdb->update($this->threads_table, $update, array('id' => $thread_id), $format, array('%d'));
    }

    public function get_thread($thread_id) {
        $thread_id = (int) $thread_id;
        if ($thread_id <= 0) return null;

        $sql = $this->wpdb->prepare("
            SELECT t.*, u.display_name AS author_name, p.avatar AS author_avatar
            FROM {$this->threads_table} t
            LEFT JOIN {$this->wpdb->users} u ON t.author_id = u.ID
            LEFT JOIN {$this->profiles_table} p ON t.author_id = p.user_id
            WHERE t.id = %d
            LIMIT 1
        ", $thread_id);

        return $this->wpdb->get_row($sql);
    }

    public function get_threads($args = array()) {
        $defaults = array(
            'status' => 'published',
            'category' => '',
            'order' => 'DESC',
            'orderby' => 'last_activity', // or created_at, views, upvotes
            'paged' => 1,
            'per_page' => (int) get_option('ats_threads_per_page', 20),
            'search' => '',
        );
        $args = wp_parse_args($args, $defaults);

        $where = array();
        $params = array();

        if (!empty($args['status'])) {
            $where[] = "t.status = %s";
            $params[] = $args['status'];
        }
        if (!empty($args['category'])) {
            $where[] = "t.category = %s";
            $params[] = $args['category'];
        }
        if (!empty($args['search'])) {
            $like = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = "(t.title LIKE %s OR t.content LIKE %s)";
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $orderby_whitelist = array('last_activity','created_at','views','upvotes','downvotes');
        $orderby = in_array($args['orderby'], $orderby_whitelist, true) ? $args['orderby'] : 'last_activity';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $limit = max(1, (int)$args['per_page']);
        $offset = max(0, ((int)$args['paged'] - 1) * $limit);

        $sql = "
            SELECT t.*, u.display_name AS author_name, p.avatar AS author_avatar
            FROM {$this->threads_table} t
            LEFT JOIN {$this->wpdb->users} u ON t.author_id = u.ID
            LEFT JOIN {$this->profiles_table} p ON t.author_id = p.user_id
            $where_sql
            ORDER BY t.$orderby $order
            LIMIT %d OFFSET %d
        ";

        $params_with_limit = array_merge($params, array($limit, $offset));
        $prepared = $this->wpdb->prepare($sql, $params_with_limit);
        return $this->wpdb->get_results($prepared);
    }

    public function get_trending_threads($limit = 10) {
        $limit = max(1, (int)$limit);
        $sql = $this->wpdb->prepare("
            SELECT t.*, u.display_name AS author_name, p.avatar AS author_avatar,
                   (t.upvotes - t.downvotes) AS score
            FROM {$this->threads_table} t
            LEFT JOIN {$this->wpdb->users} u ON t.author_id = u.ID
            LEFT JOIN {$this->profiles_table} p ON t.author_id = p.user_id
            WHERE t.status = %s
            ORDER BY score DESC, t.views DESC, t.last_activity DESC
            LIMIT %d
        ", 'published', $limit);

        return $this->wpdb->get_results($sql);
    }

    public function increment_view($thread_id) {
        $thread_id = (int)$thread_id;
        if ($thread_id <= 0) return false;

        $this->wpdb->query($this->wpdb->prepare("
            UPDATE {$this->threads_table}
            SET views = views + 1, last_activity = %s
            WHERE id = %d
        ", current_time('mysql'), $thread_id));

        return true;
    }

    public function search_threads($query, $limit = 10, $offset = 0) {
        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);
        $like = '%' . $this->wpdb->esc_like($query) . '%';

        $sql = $this->wpdb->prepare("
            SELECT id, title, excerpt, category, created_at
            FROM {$this->threads_table}
            WHERE status = 'published'
              AND (title LIKE %s OR content LIKE %s)
            ORDER BY last_activity DESC
            LIMIT %d OFFSET %d
        ", $like, $like, $limit, $offset);

        return $this->wpdb->get_results($sql);
    }

    /** ========== Replies ========== */

    public function add_reply($thread_id, $data) {
        $defaults = array(
            'author_id' => 0,
            'content'   => '',
        );
        $data = wp_parse_args($data, $defaults);
        $thread_id = (int)$thread_id;

        if ($thread_id <= 0 || empty($data['author_id']) || empty($data['content'])) {
            return false;
        }

        $insert = array(
            'thread_id'   => $thread_id,
            'author_id'   => (int)$data['author_id'],
            'content'     => function_exists('ats_sanitize_content') ? ats_sanitize_content($data['content']) : wp_kses_post($data['content']),
            'upvotes'     => 0,
            'downvotes'   => 0,
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
        );
        $format = array('%d','%d','%s','%d','%d','%s','%s');

        $ok = $this->wpdb->insert($this->replies_table, $insert, $format);
        if (!$ok) return false;

        // bump thread activity
        $this->update_thread($thread_id, array('last_activity' => current_time('mysql')));

        return (int)$this->wpdb->insert_id;
    }

    public function get_replies($thread_id, $args = array()) {
        $thread_id = (int)$thread_id;
        if ($thread_id <= 0) return array();

        $defaults = array(
            'order' => 'ASC',
            'paged' => 1,
            'per_page' => 50,
        );
        $args = wp_parse_args($args, $defaults);

        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        $limit = max(1, (int)$args['per_page']);
        $offset = max(0, ((int)$args['paged'] - 1) * $limit);

        $sql = $this->wpdb->prepare("
            SELECT r.*, u.display_name AS author_name, p.avatar AS author_avatar
            FROM {$this->replies_table} r
            LEFT JOIN {$this->wpdb->users} u ON r.author_id = u.ID
            LEFT JOIN {$this->profiles_table} p ON r.author_id = p.user_id
            WHERE r.thread_id = %d
            ORDER BY r.created_at $order
            LIMIT %d OFFSET %d
        ", $thread_id, $limit, $offset);

        return $this->wpdb->get_results($sql);
    }

}

} // class_exists

