<?php
/**
 * Plugin Name: Ghost Stories
 * Plugin URI: https://example.com/ghost-stories
 * Description: Create and display Instagram-like Stories on your WordPress website with an intuitive admin interface and responsive frontend experience.
 * Version: 1.1.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ghost-stories
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GHOST_STORIES_VERSION', '1.1.0');
define('GHOST_STORIES_FILE', __FILE__);
define('GHOST_STORIES_PATH', plugin_dir_path(__FILE__));
define('GHOST_STORIES_URL', plugin_dir_url(__FILE__));
define('GHOST_STORIES_BASENAME', plugin_basename(__FILE__));

// Include the main plugin class
require_once GHOST_STORIES_PATH . 'includes/class-wp-stories-plugin.php';

/**
 * Initialize the plugin
 */
function ghost_stories_init() {
    return WP_Stories_Plugin::get_instance();
}

// Initialize plugin after WordPress is loaded
add_action('plugins_loaded', 'ghost_stories_init');

/**
 * Plugin activation hook
 */
function ghost_stories_activate() {
    // Ensure WordPress is loaded
    if (!function_exists('add_action')) {
        return;
    }
    
    // Check minimum requirements
    if (!ghost_stories_check_requirements()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('Ghost Stories requires WordPress 5.0+ and PHP 7.4+', 'ghost-stories'),
            __('Plugin Activation Error', 'ghost-stories'),
            array('back_link' => true)
        );
    }
    
    // Initialize plugin to register post types and flush rewrite rules
    ghost_stories_init();
    
    // Create necessary database tables or options
    ghost_stories_create_database_structure();
    
    // Set default plugin options
    ghost_stories_set_default_options();
    
    // Flush rewrite rules to ensure custom post type URLs work
    flush_rewrite_rules();
    
    // Set plugin activation flag and version
    update_option('ghost_stories_activated', true);
    update_option('ghost_stories_version', GHOST_STORIES_VERSION);
    update_option('ghost_stories_activation_time', current_time('timestamp'));
    
    // Schedule cleanup events
    ghost_stories_schedule_cleanup_events();
}
register_activation_hook(__FILE__, 'ghost_stories_activate');

/**
 * Plugin deactivation hook
 */
function ghost_stories_deactivate() {
    // Flush rewrite rules to clean up custom post type URLs
    flush_rewrite_rules();
    
    // Clean up scheduled events
    ghost_stories_clear_scheduled_events();
    
    // Clean up transients and temporary data
    ghost_stories_cleanup_transients();
    
    // Clear any cached data
    ghost_stories_clear_all_caches();
    
    // Remove activation flag but preserve settings
    delete_option('ghost_stories_activated');
    
    // Log deactivation for debugging
    error_log('Ghost Stories deactivated at ' . current_time('mysql'));
}
register_deactivation_hook(__FILE__, 'ghost_stories_deactivate');

/**
 * Check plugin requirements
 */
function ghost_stories_check_requirements() {
    global $wp_version;
    
    // Check WordPress version
    if (version_compare($wp_version, '5.0', '<')) {
        return false;
    }
    
    // Check PHP version
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        return false;
    }
    
    // Check required PHP extensions
    $required_extensions = array('json', 'mbstring', 'gd');
    foreach ($required_extensions as $extension) {
        if (!extension_loaded($extension)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Create necessary database structure
 */
function ghost_stories_create_database_structure() {
    // Currently using WordPress post meta, no custom tables needed
    // This function is reserved for future database structure needs
    
    // Ensure upload directory exists and is writable
    $upload_dir = wp_upload_dir();
    if (!file_exists($upload_dir['basedir'])) {
        wp_mkdir_p($upload_dir['basedir']);
    }
}

/**
 * Set default plugin options
 */
function ghost_stories_set_default_options() {
    $default_options = array(
        'ghost_stories_default_expiration' => 24, // 24 hours
        'ghost_stories_max_media_per_story' => 10,
        'ghost_stories_auto_cleanup_expired' => true,
        'ghost_stories_enable_analytics' => false,
        'ghost_stories_performance_mode' => 'auto',
        'ghost_stories_cache_duration' => 3600, // 1 hour
    );
    
    foreach ($default_options as $option_name => $default_value) {
        if (get_option($option_name) === false) {
            add_option($option_name, $default_value);
        }
    }
}

/**
 * Schedule cleanup events
 */
function ghost_stories_schedule_cleanup_events() {
    // Schedule daily cleanup of expired stories
    if (!wp_next_scheduled('ghost_stories_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'ghost_stories_daily_cleanup');
    }
    
    // Schedule weekly cache cleanup
    if (!wp_next_scheduled('ghost_stories_weekly_cache_cleanup')) {
        wp_schedule_event(time(), 'weekly', 'ghost_stories_weekly_cache_cleanup');
    }
}

/**
 * Clear scheduled events
 */
function ghost_stories_clear_scheduled_events() {
    wp_clear_scheduled_hook('ghost_stories_daily_cleanup');
    wp_clear_scheduled_hook('ghost_stories_weekly_cache_cleanup');
}

/**
 * Cleanup transients
 */
function ghost_stories_cleanup_transients() {
    global $wpdb;
    
    // Delete all plugin-related transients
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_ghost_stories_%',
            '_transient_timeout_ghost_stories_%'
        )
    );
}

/**
 * Clear all caches
 */
function ghost_stories_clear_all_caches() {
    // Clear object cache if available
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Clear any plugin-specific caches
    delete_option('ghost_stories_cache_active_stories');
    delete_option('ghost_stories_cache_story_counts');
}

/**
 * Plugin uninstall hook (defined in uninstall.php for security)
 */
