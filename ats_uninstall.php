<?php
/**
 * Advanced Threads System - Uninstall Script
 * 
 * @package AdvancedThreadsSystem
 * @subpackage Uninstall
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user really wants to delete everything
if (!defined('ATS_REMOVE_ALL_DATA')) {
    return;
}

/**
 * Remove all plugin data when uninstalling
 * Only runs if ATS_REMOVE_ALL_DATA constant is defined
 */

global $wpdb;

// Get all blog IDs for multisite
$blog_ids = array();
if (is_multisite()) {
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
} else {
    $blog_ids[] = get_current_blog_id();
}

foreach ($blog_ids as $blog_id) {
    if (is_multisite()) {
        switch_to_blog($blog_id);
    }
    
    // Remove custom tables
    $tables_to_remove = array(
        $wpdb->prefix . 'ats_threads',
        $wpdb->prefix . 'ats_replies',
        $wpdb->prefix . 'ats_votes',
        $wpdb->prefix . 'ats_user_profiles', 
        $wpdb->prefix . 'ats_follows',
        $wpdb->prefix . 'ats_categories',
        $wpdb->prefix . 'ats_notifications',
        $wpdb->prefix . 'ats_thread_views',
        $wpdb->prefix . 'ats_reports'
    );
    
    foreach ($tables_to_remove as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
    
    // Remove all thread posts
    $thread_posts = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'ats_thread'");
    foreach ($thread_posts as $post_id) {
        wp_delete_post($post_id, true);
    }
    
    // Remove all profile posts  
    $profile_posts = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'ats_profile'");
    foreach ($profile_posts as $post_id) {
        wp_delete_post($post_id, true);
    }
    
    // Remove all plugin options
    $option_patterns = array(
        'ats_%',
        'widget_ats_%',
        '_transient_ats_%',
        '_transient_timeout_ats_%'
    );
    
    foreach ($option_patterns as $pattern) {
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '{$pattern}'");
    }
    
    // Remove user meta data
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ats_%'");
    
    // Remove post meta data
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_ats_%'");
    
    // Remove term meta data  
    if (taxonomy_exists('ats_thread_category')) {
        $wpdb->query("DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE 'ats_%'");
        
        // Remove terms and taxonomy
        $wpdb->delete($wpdb->term_taxonomy, array('taxonomy' => 'ats_thread_category'));
        $wpdb->delete($wpdb->term_taxonomy, array('taxonomy' => 'ats_thread_tag'));
    }
    
    // Remove scheduled crons
    wp_clear_scheduled_hook('ats_daily_cleanup');
    wp_clear_scheduled_hook('ats_weekly_digest');
    wp_clear_scheduled_hook('ats_reputation_update');
    wp_clear_scheduled_hook('ats_notification_cleanup');
    
    // Remove rewrite rules
    delete_option('rewrite_rules');
    
    // Remove uploaded files in ATS directory
    $upload_dir = wp_upload_dir();
    $ats_upload_dir = $upload_dir['basedir'] . '/ats-uploads/';
    
    if (is_dir($ats_upload_dir)) {
        ats_remove_directory($ats_upload_dir);
    }
    
    // Remove custom capabilities from roles
    $roles = array('administrator', 'editor', 'author', 'contributor', 'subscriber');
    
    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->remove_cap('create_threads');
            $role->remove_cap('edit_threads');
            $role->remove_cap('delete_threads');
            $role->remove_cap('moderate_threads');
            $role->remove_cap('manage_thread_categories');
        }
    }
    
    // Remove custom role if created
    remove_role('ats_moderator');
    
    // Clean up any remaining transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ats_%' OR option_name LIKE '_transient_timeout_ats_%'");
    
    if (is_multisite()) {
        restore_current_blog();
    }
}

// Remove network options for multisite
if (is_multisite()) {
    delete_site_option('ats_network_version');
    delete_site_option('ats_network_settings');
}

/**
 * Helper function to recursively remove directory
 */
function ats_remove_directory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $file_path = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($file_path)) {
            ats_remove_directory($file_path);
        } else {
            unlink($file_path);
        }
    }
    
    rmdir($dir);
}

// Log uninstallation
error_log('Advanced Threads System: Plugin uninstalled and all data removed');

// Clear any object cache
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

// Final cleanup message
// Note: This won't be seen by users, but helps with debugging
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('ATS Uninstall: All plugin data has been removed successfully');
}