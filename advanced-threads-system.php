<?php
/**
 * Plugin Name: Advanced Threads System
 * Plugin URI: https://example.com/advanced-threads-system
 * Description: A comprehensive forum and discussion system for WordPress with advanced features like voting, user profiles, reputation system, and real-time notifications.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: advanced-threads
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 *
 * @package AdvancedThreadsSystem
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ATS_VERSION', '1.0.0');
define('ATS_PLUGIN_FILE', __FILE__);
define('ATS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ATS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ATS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Define database table names
global $wpdb;
define('ATS_THREADS_TABLE', $wpdb->prefix . 'ats_threads');
define('ATS_REPLIES_TABLE', $wpdb->prefix . 'ats_replies');
define('ATS_VOTES_TABLE', $wpdb->prefix . 'ats_votes');
define('ATS_USER_PROFILES_TABLE', $wpdb->prefix . 'ats_user_profiles');
define('ATS_CATEGORIES_TABLE', $wpdb->prefix . 'ats_categories');
define('ATS_NOTIFICATIONS_TABLE', $wpdb->prefix . 'ats_notifications');
define('ATS_FOLLOWS_TABLE', $wpdb->prefix . 'ats_follows');
define('ATS_BADGES_TABLE', $wpdb->prefix . 'ats_badges');
define('ATS_USER_BADGES_TABLE', $wpdb->prefix . 'ats_user_badges');
define('ATS_REPORTS_TABLE', $wpdb->prefix . 'ats_reports');

/**
 * Main plugin class
 */
class Advanced_Threads_System {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Plugin lifecycle hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('Advanced_Threads_System', 'uninstall'));
        
        // WordPress initialization
        add_action('plugins_loaded', array($this, 'plugins_loaded'));
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'admin_init'));
        
        // Load textdomain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Plugin action links
        add_filter('plugin_action_links_' . ATS_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once ATS_PLUGIN_PATH . 'includes/ats_core_class.php';
        require_once ATS_PLUGIN_PATH . 'includes/ats_installer_class.php';
        require_once ATS_PLUGIN_PATH . 'includes/ats_post_types.php';
        require_once ATS_PLUGIN_PATH . 'includes/ats_functions.php';
        
        // Manager classes
        require_once ATS_PLUGIN_PATH . 'includes/ats_thread_manager.php';
        require_once ATS_PLUGIN_PATH . 'includes/ats_user_manager.php';
        require_once ATS_PLUGIN_PATH . 'includes/ats_vote_manager.php';
        
        // Admin classes
        if (is_admin()) {
            require_once ATS_PLUGIN_PATH . 'includes/ats_admin_class.php';
        }
        
        // Frontend classes
        if (!is_admin() || wp_doing_ajax()) {
            require_once ATS_PLUGIN_PATH . 'includes/ats_frontend_complete.php';
        }
        
        // AJAX handlers
        require_once ATS_PLUGIN_PATH . 'includes/ats_ajax_handler.php';
        
        // Shortcodes
        require_once ATS_PLUGIN_PATH . 'includes/ats_shortcodes.php';
        
        // Uninstall handler
        require_once ATS_PLUGIN_PATH . 'includes/ats_uninstall.php';
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check system requirements
        $this->check_requirements();
        
        // Run installer
        $installer = new ATS_Installer();
        $installer->install();
        
        // Clear permalinks
        flush_rewrite_rules();
        
        // Set activation flag
        update_option('ats_plugin_activated', true);
        update_option('ats_activation_time', current_time('timestamp'));
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('ats_daily_cleanup');
        wp_clear_scheduled_hook('ats_weekly_digest');
        
        // Clear permalinks
        flush_rewrite_rules();
        
        // Clear activation flag
        delete_option('ats_plugin_activated');
    }
    
    /**
     * Plugin uninstallation
     */
    public static function uninstall() {
        // Only run uninstall if explicitly enabled
        if (get_option('ats_remove_data_on_uninstall', 0)) {
            $uninstaller = new ATS_Uninstaller();
            $uninstaller->uninstall();
        }
    }
    
    /**
     * Plugins loaded hook
     */
    public function plugins_loaded() {
        // Check if plugin needs update
        $this->maybe_update();
        
        // Initialize core functionality
        if (class_exists('ATS_Core')) {
            ATS_Core::get_instance();
        }
    }
    
    /**
     * WordPress init hook
     */
    public function init() {
        // Register post types and taxonomies
        if (class_exists('ATS_Post_Types')) {
            new ATS_Post_Types();
        }
        
        // Initialize shortcodes
        if (class_exists('ATS_Shortcodes')) {
            new ATS_Shortcodes();
        }
        
        // Schedule recurring events
        $this->schedule_events();
    }
    
    /**
     * Admin init hook
     */
    public function admin_init() {
        // Initialize admin functionality
        if (is_admin() && class_exists('ATS_Admin')) {
            ATS_Admin::get_instance();
        }
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'advanced-threads',
            false,
            dirname(ATS_PLUGIN_BASENAME) . '/languages/'
        );
    }
    
    /**
     * Check system requirements
     */
    private function check_requirements() {
        $php_version = phpversion();
        $wp_version = get_bloginfo('version');
        
        if (version_compare($php_version, '7.4', '<')) {
            deactivate_plugins(ATS_PLUGIN_BASENAME);
            wp_die(
                sprintf(
                    __('Advanced Threads System requires PHP version 7.4 or higher. You are running version %s.', 'advanced-threads'),
                    $php_version
                ),
                __('Plugin Activation Error', 'advanced-threads'),
                array('back_link' => true)
            );
        }
        
        if (version_compare($wp_version, '5.0', '<')) {
            deactivate_plugins(ATS_PLUGIN_BASENAME);
            wp_die(
                sprintf(
                    __('Advanced Threads System requires WordPress version 5.0 or higher. You are running version %s.', 'advanced-threads'),
                    $wp_version
                ),
                __('Plugin Activation Error', 'advanced-threads'),
                array('back_link' => true)
            );
        }
        
        // Check for required extensions
        $required_extensions = array('mysqli', 'json', 'mbstring');
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                deactivate_plugins(ATS_PLUGIN_BASENAME);
                wp_die(
                    sprintf(
                        __('Advanced Threads System requires the %s PHP extension.', 'advanced-threads'),
                        $extension
                    ),
                    __('Plugin Activation Error', 'advanced-threads'),
                    array('back_link' => true)
                );
            }
        }
    }
    
    /**
     * Check if plugin needs update
     */
    private function maybe_update() {
        $installed_version = get_option('ats_version', '0.0.0');
        
        if (version_compare($installed_version, ATS_VERSION, '<')) {
            $installer = new ATS_Installer();
            $installer->update($installed_version);
            update_option('ats_version', ATS_VERSION);
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
    }
    
    /**
     * Add plugin action links
     */
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=ats-settings') . '">' . __('Settings', 'advanced-threads') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Add plugin row meta
     */
    public function plugin_row_meta($links, $file) {
        if ($file === ATS_PLUGIN_BASENAME) {
            $links[] = '<a href="https://example.com/docs" target="_blank">' . __('Documentation', 'advanced-threads') . '</a>';
            $links[] = '<a href="https://example.com/support" target="_blank">' . __('Support', 'advanced-threads') . '</a>';
        }
        return $links;
    }
}

// Initialize the plugin
Advanced_Threads_System::get_instance();

/**
 * Get the main plugin instance
 *
 * @return Advanced_Threads_System
 */
function ats() {
    return Advanced_Threads_System::get_instance();
}

/**
 * Plugin compatibility check
 */
function ats_compatibility_check() {
    // Check for conflicting plugins
    $conflicting_plugins = array(
        'bbpress/bbpress.php',
        'buddypress/bp-loader.php'
    );
    
    foreach ($conflicting_plugins as $plugin) {
        if (is_plugin_active($plugin)) {
            add_action('admin_notices', function() use ($plugin) {
                $plugin_name = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin)['Name'];
                echo '<div class="notice notice-warning"><p>';
                printf(
                    __('Advanced Threads System may conflict with %s. Please test functionality carefully.', 'advanced-threads'),
                    '<strong>' . $plugin_name . '</strong>'
                );
                echo '</p></div>';
            });
        }
    }
}
add_action('admin_init', 'ats_compatibility_check');

/**
 * Emergency deactivation function
 */
function ats_emergency_deactivation() {
    if (isset($_GET['ats_emergency_disable']) && current_user_can('manage_options')) {
        deactivate_plugins(ATS_PLUGIN_BASENAME);
        wp_redirect(admin_url('plugins.php'));
        exit;
    }
}
add_action('admin_init', 'ats_emergency_deactivation');