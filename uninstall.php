<?php
/**
 * Plugin uninstall script
 * 
 * This file is executed when the plugin is deleted through the WordPress admin.
 * It performs comprehensive cleanup while preserving user data by default.
 * 
 * @package WP_Stories_Plugin
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Security check
if (!current_user_can('delete_plugins')) {
    exit;
}

/**
 * Comprehensive plugin cleanup
 */
function wp_stories_uninstall_cleanup() {
    global $wpdb;
    
    // 1. Delete plugin options
    $plugin_options = array(
        'wp_stories_plugin_activated',
        'wp_stories_plugin_version',
        'wp_stories_plugin_activation_time',
        'wp_stories_default_expiration',
        'wp_stories_max_media_per_story',
        'wp_stories_auto_cleanup_expired',
        'wp_stories_enable_analytics',
        'wp_stories_performance_mode',
        'wp_stories_cache_duration',
        'wp_stories_cache_active_stories',
        'wp_stories_cache_story_counts'
    );
    
    foreach ($plugin_options as $option) {
        delete_option($option);
    }
    
    // 2. Clean up all transients
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_wp_stories_%',
            '_transient_timeout_wp_stories_%'
        )
    );
    
    // 3. Clear scheduled events
    wp_clear_scheduled_hook('wp_stories_daily_cleanup');
    wp_clear_scheduled_hook('wp_stories_weekly_cache_cleanup');
    
    // 4. Clean up user meta (viewed stories tracking)
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
            'wp_stories_%'
        )
    );
    
    // 5. Optional: Remove story posts and media (only if user confirms)
    // This is commented out by default to prevent accidental data loss
    // Uncomment the following lines if you want to remove all story data on uninstall
    /*
    $story_posts = get_posts(array(
        'post_type' => 'wp_story',
        'numberposts' => -1,
        'post_status' => 'any'
    ));
    
    foreach ($story_posts as $post) {
        // Get associated media
        $media_ids = get_post_meta($post->ID, '_story_media_ids', true);
        
        // Optionally delete media files (be very careful with this)
        // if (is_array($media_ids)) {
        //     foreach ($media_ids as $media_id) {
        //         wp_delete_attachment($media_id, true);
        //     }
        // }
        
        // Delete the story post
        wp_delete_post($post->ID, true);
    }
    */
    
    // 6. Flush rewrite rules to clean up custom post type URLs
    flush_rewrite_rules();
    
    // 7. Clear any object cache
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // 8. Log uninstall for debugging (optional)
    error_log('WP Stories Plugin completely uninstalled at ' . current_time('mysql'));
}

// Execute cleanup
wp_stories_uninstall_cleanup();

// Note: Custom post type data and media files are intentionally preserved
// to prevent accidental data loss. Site administrators can manually delete
// wp_story posts if they want to remove all story data.
//
// To completely remove all data including stories and media:
// 1. Go to Posts > Stories in WordPress admin
// 2. Select all stories and delete them
// 3. Go to Media Library and delete associated media files
// 4. Then uninstall the plugin