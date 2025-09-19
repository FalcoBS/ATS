<?php
/**
 * Plugin Name: Advanced Threads System
 * Plugin URI: https://yourdomain.com
 * Description: A powerful forum/threads system with upvotes, user profiles, and advanced features similar to Simple Flying.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: advanced-threads
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ATS_VERSION', '1.0.0');
define('ATS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ATS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ATS_PLUGIN_FILE', __FILE__);

/**
 * PLUGIN DIRECTORY STRUCTURE:
 * 
 * advanced-threads-system/
 * ├── advanced-threads-system.php          (Main plugin file)
 * ├── README.md
 * ├── uninstall.php
 * ├── includes/
 * │   ├── class-ats-core.php               (Main plugin class)
 * │   ├── class-ats-installer.php          (Database setup)
 * │   ├── class-ats-post-types.php         (Custom post types)
 * │   ├── class-ats-thread-manager.php     (Thread operations)
 * │   ├── class-ats-user-manager.php       (User profiles & stats)
 * │   ├── class-ats-vote-manager.php       (Voting system)
 * │   ├── class-ats-ajax-handler.php       (AJAX endpoints)
 * │   ├── class-ats-shortcodes.php         (Shortcodes)
 * │   └── functions.php                    (Helper functions)
 * ├── admin/
 * │   ├── class-ats-admin.php              (Admin panel)
 * │   ├── class-ats-settings.php           (Settings page)
 * │   ├── class-ats-moderation.php         (Content moderation)
 * │   └── views/
 * │       ├── settings-page.php
 * │       ├── moderation-page.php
 * │       └── dashboard-widget.php
 * ├── public/
 * │   ├── class-ats-frontend.php           (Frontend functionality)
 * │   ├── class-ats-templates.php          (Template loader)
 * │   └── class-ats-enqueue.php            (Scripts & styles)
 * ├── templates/
 * │   ├── single-thread.php                (Single thread template)
 * │   ├── archive-thread.php               (Threads listing)
 * │   ├── thread-card.php                  (Thread preview card)
 * │   ├── reply-item.php                   (Single reply template)
 * │   ├── user-profile.php                 (User profile page)
 * │   └── parts/
 * │       ├── thread-form.php
 * │       ├── reply-form.php
 * │       ├── voting-buttons.php
 * │       └── user-card.php
 * ├── assets/
 * │   ├── css/
 * │   │   ├── threads-frontend.css
 * │   │   ├── threads-admin.css
 * │   │   └── threads-responsive.css
 * │   ├── js/
 * │   │   ├── threads-frontend.js
 * │   │   ├── threads-admin.js
 * │   │   └── threads-editor.js
 * │   └── images/
 * │       ├── icons/
 * │       └── placeholders/
 * └── languages/
 *     ├── advanced-threads.pot
 *     └── advanced-threads-it_IT.po
 */

// Main plugin class
class AdvancedThreadsSystem {
    
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
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
    }
    
    private function load_dependencies() {
        // Core classes
        require_once ATS_PLUGIN_PATH . 'includes/functions.php';
        require_once ATS_PLUGIN_PATH . 'includes/class-ats-installer.php';
        require_once ATS_PLUGIN_PATH . 'includes/class-ats-core.php';
        require_once ATS_PLUGIN_PATH . 'includes/class-ats-post-types.php';
        require_once ATS_PLUGIN_PATH . 'includes/class-ats-thread-manager.php';
        require_once ATS_PLUGIN_PATH . 'includes/class-ats-user-manager.php';
        require_once ATS_PLUGIN_PATH . 'includes/class-ats-vote-manager.php';
        require_once ATS_PLUGIN_PATH . 'includes/class-ats-ajax-handler.php';
        require_once ATS_PLUGIN_PATH . 'includes/class-ats-shortcodes.php';
        
        // Admin classes
        if (is_admin()) {
            require_once ATS_PLUGIN_PATH . 'admin/class-ats-admin.php';
            require_once ATS_PLUGIN_PATH . 'admin/class-ats-settings.php';
            require_once ATS_PLUGIN_PATH . 'admin/class-ats-moderation.php';
        }
        
        // Public classes
        if (!is_admin()) {
            require_once ATS_PLUGIN_PATH . 'public/class-ats-frontend.php';
            require_once ATS_PLUGIN_PATH . 'public/class-ats-templates.php';
            require_once ATS_PLUGIN_PATH . 'public/class-ats-enqueue.php';
        }
    }
    
    public function init() {
        // Initialize core components
        ATS_Core::get_instance();
        ATS_Post_Types::get_instance();
        ATS_AJAX_Handler::get_instance();
        ATS_Shortcodes::get_instance();
        
        // Initialize admin or frontend
        if (is_admin()) {
            ATS_Admin::get_instance();
        } else {
            ATS_Frontend::get_instance();
        }
    }
    
    public function activate() {
        // Create database tables
        ATS_Installer::create_tables();
        
        // Create default pages
        ATS_Installer::create_pages();
        
        // Set default options
        ATS_Installer::set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'advanced-threads',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
}

// Initialize the plugin
function ats() {
    return AdvancedThreadsSystem::get_instance();
}

// Start the plugin
ats();
?>