<?php
/**
 * Advanced Threads System - Helper Functions
 * 
 * @package AdvancedThreadsSystem
 * @subpackage Functions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get ATS option with default value
 *
 * @param string $option_name Option name
 * @param mixed $default Default value
 * @return mixed Option value
 */
function ats_get_option($option_name, $default = null) {
    return get_option('ats_' . $option_name, $default);
}

/**
 * Update ATS option
 *
 * @param string $option_name Option name
 * @param mixed $value Option value
 * @return bool Success status
 */
function ats_update_option($option_name, $value) {
    return update_option('ats_' . $option_name, $value);
}

/**
 * Delete ATS option
 *
 * @param string $option_name Option name
 * @return bool Success status
 */
function ats_delete_option($option_name) {
    return delete_option('ats_' . $option_name);
}

/**
 * Check if user can perform action
 *
 * @param string $action Action to check
 * @param int $user_id User ID (default: current user)
 * @return bool Permission status
 */
function ats_user_can($action, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return false;
    }
    
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return false;
    }
    
    switch ($action) {
        case 'create_thread':
            $required_role = ats_get_option('who_can_create_threads', 'subscriber');
            break;
            
        case 'reply':
            $required_role = ats_get_option('who_can_reply', 'subscriber');
            break;
            
        case 'vote':
            $required_role = ats_get_option('who_can_vote', 'subscriber');
            break;
            
        case 'moderate':
            return current_user_can('moderate_comments') || current_user_can('manage_options');
            
        case 'edit_any_thread':
            return current_user_can('edit_posts') || current_user_can('manage_options');
            
        default:
            return false;
    }
    
    // Check role hierarchy
    $role_hierarchy = array(
        'subscriber' => 1,
        'contributor' => 2,
        'author' => 3,
        'editor' => 4,
        'administrator' => 5
    );
    
    $user_level = 0;
    foreach ($user->roles as $role) {
        if (isset($role_hierarchy[$role])) {
            $user_level = max($user_level, $role_hierarchy[$role]);
        }
    }
    
    $required_level = $role_hierarchy[$required_role] ?? 1;
    
    return $user_level >= $required_level;
}

/**
 * Sanitize thread content
 *
 * @param string $content Raw content
 * @return string Sanitized content
 */
function ats_sanitize_content($content) {
    $allowed_html = array(
        'p' => array(),
        'br' => array(),
        'strong' => array(),
        'b' => array(),
        'em' => array(),
        'i' => array(),
        'u' => array(),
        'a' => array(
            'href' => array(),
            'title' => array(),
            'target' => array()
        ),
        'ul' => array(),
        'ol' => array(),
        'li' => array(),
        'blockquote' => array(),
        'code' => array(),
        'pre' => array(),
        'img' => array(
            'src' => array(),
            'alt' => array(),
            'width' => array(),
            'height' => array(),
            'class' => array()
        ),
        'h1' => array(),
        'h2' => array(),
        'h3' => array(),
        'h4' => array(),
        'h5' => array(),
        'h6' => array()
    );
    
    // Apply filter to allow customization
    $allowed_html = apply_filters('ats_allowed_html', $allowed_html);
    
    return wp_kses($content, $allowed_html);
}

/**
 * Format number for display (1K, 1M, etc.)
 *
 * @param int $number Number to format
 * @return string Formatted number
 */
function ats_format_number($number) {
    if ($number < 1000) {
        return number_format($number);
    }
    
    if ($number < 1000000) {
        return number_format($number / 1000, 1) . 'K';
    }
    
    return number_format($number / 1000000, 1) . 'M';
}

/**
 * Get time ago string
 *
 * @param string $datetime DateTime string
 * @return string Time ago string
 */
function ats_time_ago($datetime) {
    $timestamp = strtotime($datetime);
    
    if (!$timestamp) {
        return '';
    }
    
    $time_diff = time() - $timestamp;
    
    if ($time_diff < 60) {
        return __('Just now', 'advanced-threads');
    }
    
    if ($time_diff < 3600) {
        $minutes = floor($time_diff / 60);
        return sprintf(_n('%d minute ago', '%d minutes ago', $minutes, 'advanced-threads'), $minutes);
    }
    
    if ($time_diff < 86400) {
        $hours = floor($time_diff / 3600);
        return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'advanced-threads'), $hours);
    }
    
    if ($time_diff < 604800) {
        $days = floor($time_diff / 86400);
        return sprintf(_n('%d day ago', '%d days ago', $days, 'advanced-threads'), $days);
    }
    
    if ($time_diff < 2419200) {
        $weeks = floor($time_diff / 604800);
        return sprintf(_n('%d week ago', '%d weeks ago', $weeks, 'advanced-threads'), $weeks);
    }
    
    return date_i18n(get_option('date_format'), $timestamp);
}

/**
 * Get user profile URL
 *
 * @param int $user_id User ID
 * @return string Profile URL
 */
function ats_get_user_profile_url($user_id) {
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return '#';
    }
    
    return home_url('/profile/' . $user->user_login . '/');
}

/**
 * Get thread URL
 *
 * @param int $thread_id Thread ID or post ID
 * @return string Thread URL
 */
function ats_get_thread_url($thread_id) {
    $post = get_post($thread_id);
    if (!$post || $post->post_type !== 'ats_thread') {
        return '#';
    }
    
    return get_permalink($post);
}

/**
 * Get category URL
 *
 * @param string $category_slug Category slug
 * @return string Category URL
 */
function ats_get_category_url($category_slug) {
    return home_url('/threads/category/' . $category_slug . '/');
}

/**
 * Check if content contains blocked words
 *
 * @param string $content Content to check
 * @return bool True if contains blocked words
 */
function ats_contains_blocked_words($content) {
    $blocked_words = ats_get_option('blocked_words', '');
    
    if (empty($blocked_words)) {
        return false;
    }
    
    $words = array_map('trim', explode(',', strtolower($blocked_words)));
    $content_lower = strtolower($content);
    
    foreach ($words as $word) {
        if (!empty($word) && strpos($content_lower, $word) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Log ATS activity
 *
 * @param string $message Log message
 * @param string $level Log level (info, warning, error)
 * @param array $context Additional context
 */
function ats_log($message, $level = 'info', $context = array()) {
    if (!ats_get_option('enable_logging', false)) {
        return;
    }
    
    $log_entry = array(
        'timestamp' => current_time('mysql'),
        'level' => $level,
        'message' => $message,
        'context' => $context,
        'user_id' => get_current_user_id(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    );
    
    error_log('ATS [' . strtoupper($level) . '] ' . $message . ' ' . json_encode($context));
    
    // Store in database if needed
    do_action('ats_log_entry', $log_entry);
}

/**
 * Get user avatar URL
 *
 * @param int $user_id User ID
 * @param int $size Avatar size
 * @return string Avatar URL
 */
function ats_get_user_avatar($user_id, $size = 48) {
    global $wpdb;
    
    // Check for custom avatar first
    $profiles_table = $wpdb->prefix . 'ats_user_profiles';
    $custom_avatar = $wpdb->get_var($wpdb->prepare(
        "SELECT avatar FROM $profiles_table WHERE user_id = %d AND avatar != ''",
        $user_id
    ));
    
    if ($custom_avatar) {
        return $custom_avatar;
    }
    
    // Fallback to WordPress avatar
    return get_avatar_url($user_id, array('size' => $size));
}

/**
 * Send notification to user
 *
 * @param int $user_id User ID
 * @param string $type Notification type
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $action_url Action URL
 * @param int $related_id Related object ID
 * @param string $related_type Related object type
 * @return bool Success status
 */
function ats_send_notification($user_id, $type, $title, $message, $action_url = '', $related_id = null, $related_type = '') {
    global $wpdb;
    
    if (!ats_get_option('enable_notifications', 1)) {
        return false;
    }
    
    $notifications_table = $wpdb->prefix . 'ats_notifications';
    
    $result = $wpdb->insert(
        $notifications_table,
        array(
            'user_id' => $user_id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $action_url,
            'related_id' => $related_id,
            'related_type' => $related_type,
            'is_read' => 0,
            'created_at' => current_time('mysql')
        ),
        array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s')
    );
    
    if ($result) {
        // Send email notification if enabled
        if (ats_get_option('email_notifications', 1)) {
            ats_send_email_notification($user_id, $title, $message, $action_url);
        }
        
        // Trigger action for real-time notifications
        do_action('ats_notification_sent', $user_id, $type, $title, $message);
    }
    
    return (bool) $result;
}

/**
 * Send email notification
 *
 * @param int $user_id User ID
 * @param string $subject Email subject
 * @param string $message Email message
 * @param string $action_url Action URL
 * @return bool Success status
 */
function ats_send_email_notification($user_id, $subject, $message, $action_url = '') {
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return false;
    }
    
    $site_name = get_bloginfo('name');
    $email_subject = sprintf('[%s] %s', $site_name, $subject);
    
    $email_message = $message;
    if ($action_url) {
        $email_message .= "\n\n" . sprintf(__('View: %s', 'advanced-threads'), $action_url);
    }
    
    $email_message .= "\n\n" . sprintf(
        __('You can manage your notification preferences here: %s', 'advanced-threads'),
        home_url('/profile/' . $user->user_login . '/notifications/')
    );
    
    return wp_mail($user->user_email, $email_subject, $email_message);
}

/**
 * Get thread excerpt
 *
 * @param string $content Full content
 * @param int $length Excerpt length
 * @return string Excerpt
 */
function ats_get_excerpt($content, $length = 160) {
    $content = wp_strip_all_tags($content);
    $content = preg_replace('/\s+/', ' ', $content);
    
    if (strlen($content) <= $length) {
        return $content;
    }
    
    return substr($content, 0, $length) . '...';
}

/**
 * Validate and resize uploaded image
 *
 * @param array $file Uploaded file array
 * @param int $max_width Maximum width
 * @param int $max_height Maximum height
 * @return array Result with success status and file info
 */
function ats_process_image_upload($file, $max_width = 800, $max_height = 600) {
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    
    // Check file type
    $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
    if (!in_array($file['type'], $allowed_types)) {
        return array('success' => false, 'error' => __('Invalid file type', 'advanced-threads'));
    }
    
    // Check file size
    $max_size = ats_get_option('max_image_size', 2048) * 1024; // Convert KB to bytes
    if ($file['size'] > $max_size) {
        return array('success' => false, 'error' => __('File too large', 'advanced-threads'));
    }
    
    // Upload file
    $upload_overrides = array('test_form' => false);
    $movefile = wp_handle_upload($file, $upload_overrides);
    
    if ($movefile && !isset($movefile['error'])) {
        // Resize image if needed
        $image_editor = wp_get_image_editor($movefile['file']);
        if (!is_wp_error($image_editor)) {
            $current_size = $image_editor->get_size();
            
            if ($current_size['width'] > $max_width || $current_size['height'] > $max_height) {
                $image_editor->resize($max_width, $max_height, false);
                $image_editor->save($movefile['file']);
            }
        }
        
        return array(
            'success' => true,
            'url' => $movefile['url'],
            'file' => $movefile['file'],
            'type' => $file['type']
        );
    } else {
        return array('success' => false, 'error' => $movefile['error']);
    }
}

/**
 * Get reputation level name
 *
 * @param int $reputation Reputation points
 * @return string Level name
 */
function ats_get_reputation_level($reputation) {
    $levels = array(
        0 => __('Newcomer', 'advanced-threads'),
        100 => __('Member', 'advanced-threads'),
        500 => __('Regular', 'advanced-threads'),
        1000 => __('Valued Member', 'advanced-threads'),
        2500 => __('Expert', 'advanced-threads'),
        5000 => __('Guru', 'advanced-threads'),
        10000 => __('Legend', 'advanced-threads')
    );
    
    $user_level = __('Newcomer', 'advanced-threads');
    
    foreach ($levels as $points => $level) {
        if ($reputation >= $points) {
            $user_level = $level;
        }
    }
    
    return apply_filters('ats_reputation_level', $user_level, $reputation);
}

/**
 * Clean old data (called by cron)
 */
function ats_cleanup_old_data() {
    global $wpdb;
    
    // Clean old thread views
    $views_table = $wpdb->prefix . 'ats_thread_views';
    $deleted_views = $wpdb->query("DELETE FROM $views_table WHERE viewed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    
    // Clean old notifications
    $notifications_table = $wpdb->prefix . 'ats_notifications';
    $deleted_notifications = $wpdb->query("DELETE FROM $notifications_table WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY) AND is_read = 1");
    
    ats_log('Cleanup completed', 'info', array(
        'deleted_views' => $deleted_views,
        'deleted_notifications' => $deleted_notifications
    ));
    
    do_action('ats_cleanup_completed', $deleted_views, $deleted_notifications);
}

/**
 * Get default categories
 *
 * @return array Categories
 */
function ats_get_categories() {
    global $wpdb;
    
    $categories_table = $wpdb->prefix . 'ats_categories';
    
    return $wpdb->get_results(
        "SELECT * FROM $categories_table ORDER BY sort_order ASC, name ASC"
    );
}

/**
 * Get category by slug
 *
 * @param string $slug Category slug
 * @return object|null Category object
 */
function ats_get_category_by_slug($slug) {
    global $wpdb;
    
    $categories_table = $wpdb->prefix . 'ats_categories';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $categories_table WHERE slug = %s",
        $slug
    ));
}