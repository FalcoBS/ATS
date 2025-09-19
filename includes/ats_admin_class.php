<?php
/**
 * Advanced Threads System - Admin Class (reconstructed)
 *
 * Provides the plugin Settings page and registers options used across the plugin.
 * This is a minimal, safe implementation to restore functionality without fatal errors.
 *
 * @package AdvancedThreadsSystem
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ATS_Admin')) {

class ATS_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add settings page under Settings → Advanced Threads
     */
    public function register_menu() {
        add_options_page(
            __('Advanced Threads', 'advanced-threads'),
            __('Advanced Threads', 'advanced-threads'),
            'manage_options',
            'advanced-threads',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register our plugin options
     */
    public function register_settings() {

        // Roles allowed to post / vote
        register_setting('ats_settings', 'ats_who_can_post', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'subscriber',
        ));

        register_setting('ats_settings', 'ats_who_can_vote', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'subscriber',
        ));

        // Moderation
        register_setting('ats_settings', 'ats_require_approval', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
            'default' => false,
        ));

        register_setting('ats_settings', 'ats_moderation_keywords', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_keywords'),
            'default' => '',
        ));

        // Appearance
        register_setting('ats_settings', 'ats_threads_per_page', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 20,
        ));

        add_settings_section(
            'ats_general',
            __('General', 'advanced-threads'),
            '__return_false',
            'ats_settings'
        );

        add_settings_field(
            'ats_who_can_post',
            __('Who can create threads', 'advanced-threads'),
            array($this, 'field_role_select'),
            'ats_settings',
            'ats_general',
            array(
                'option' => 'ats_who_can_post',
                'description' => __('Minimum role required to create new threads.', 'advanced-threads'),
            )
        );

        add_settings_field(
            'ats_who_can_vote',
            __('Who can vote', 'advanced-threads'),
            array($this, 'field_role_select'),
            'ats_settings',
            'ats_general',
            array(
                'option' => 'ats_who_can_vote',
                'description' => __('Minimum role required to up/down vote.', 'advanced-threads'),
            )
        );

        add_settings_section(
            'ats_moderation',
            __('Moderation', 'advanced-threads'),
            '__return_false',
            'ats_settings'
        );

        add_settings_field(
            'ats_require_approval',
            __('Require approval for new threads', 'advanced-threads'),
            array($this, 'field_checkbox'),
            'ats_settings',
            'ats_moderation',
            array(
                'option' => 'ats_require_approval',
                'label' => __('New threads are set to "pending" and must be approved by an admin.', 'advanced-threads'),
            )
        );

        add_settings_field(
            'ats_moderation_keywords',
            __('Moderation keywords (comma separated)', 'advanced-threads'),
            array($this, 'field_textarea'),
            'ats_settings',
            'ats_moderation',
            array(
                'option' => 'ats_moderation_keywords',
                'rows' => 4,
                'description' => __('If a title or content contains one of these keywords, mark the thread as pending for review.', 'advanced-threads'),
            )
        );

        add_settings_section(
            'ats_appearance',
            __('Appearance', 'advanced-threads'),
            '__return_false',
            'ats_settings'
        );

        add_settings_field(
            'ats_threads_per_page',
            __('Threads per page', 'advanced-threads'),
            array($this, 'field_number'),
            'ats_settings',
            'ats_appearance',
            array(
                'option' => 'ats_threads_per_page',
                'min' => 1,
                'max' => 100,
            )
        );
    }

    /** Sanitizers */

    public function sanitize_checkbox($value) {
        return !empty($value) ? 1 : 0;
    }

    public function sanitize_keywords($value) {
        $value = wp_strip_all_tags($value);
        // normalize commas
        $parts = array_filter(array_map('trim', explode(',', $value)));
        return implode(', ', $parts);
    }

    /** Field renderers */

    public function field_role_select($args) {
        $option = $args['option'];
        $current = get_option($option, 'subscriber');
        ?>
        <select name="<?php echo esc_attr($option); ?>">
            <option value="subscriber" <?php selected($current, 'subscriber'); ?>>
                <?php esc_html_e('Subscriber and above', 'advanced-threads'); ?>
            </option>
            <option value="contributor" <?php selected($current, 'contributor'); ?>>
                <?php esc_html_e('Contributor and above', 'advanced-threads'); ?>
            </option>
            <option value="author" <?php selected($current, 'author'); ?>>
                <?php esc_html_e('Author and above', 'advanced-threads'); ?>
            </option>
            <option value="editor" <?php selected($current, 'editor'); ?>>
                <?php esc_html_e('Editor and above', 'advanced-threads'); ?>
            </option>
            <option value="administrator" <?php selected($current, 'administrator'); ?>>
                <?php esc_html_e('Administrator only', 'advanced-threads'); ?>
            </option>
        </select>
        <p class="description"><?php echo isset($args['description']) ? esc_html($args['description']) : ''; ?></p>
        <?php
    }

    public function field_checkbox($args) {
        $option = $args['option'];
        $current = (int) get_option($option, 0);
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($option); ?>" value="1" <?php checked($current, 1); ?> />
            <?php echo isset($args['label']) ? esc_html($args['label']) : ''; ?>
        </label>
        <?php
    }

    public function field_textarea($args) {
        $option = $args['option'];
        $rows   = isset($args['rows']) ? max(2, (int) $args['rows']) : 4;
        $current = get_option($option, '');
        ?>
        <textarea name="<?php echo esc_attr($option); ?>" rows="<?php echo esc_attr($rows); ?>" class="large-text"><?php echo esc_textarea($current); ?></textarea>
        <p class="description"><?php echo isset($args['description']) ? esc_html($args['description']) : ''; ?></p>
        <?php
    }

    public function field_number($args) {
        $option = $args['option'];
        $min = isset($args['min']) ? (int) $args['min'] : 1;
        $max = isset($args['max']) ? (int) $args['max'] : 100;
        $current = absint(get_option($option, 20));
        ?>
        <input type="number" name="<?php echo esc_attr($option); ?>" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" value="<?php echo esc_attr($current); ?>" />
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Advanced Threads – Settings', 'advanced-threads'); ?></h1>
            <form action="options.php" method="post">
                <?php
                    settings_fields('ats_settings');
                    do_settings_sections('ats_settings');
                    submit_button(__('Save Settings', 'advanced-threads'));
                ?>
            </form>
        </div>
        <?php
    }

}

} // class_exists

// Bootstrap
ATS_Admin::get_instance();
