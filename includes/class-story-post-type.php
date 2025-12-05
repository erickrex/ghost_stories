<?php
/**
 * Story Post Type class
 *
 * Handles registration and management of the wp_story custom post type
 *
 * @package WP_Stories_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Story Post Type class
 */
class Story_Post_Type {
    
    /**
     * Post type name
     */
    const POST_TYPE = 'wp_story';
    
    /**
     * Meta key for media IDs
     */
    const META_MEDIA_IDS = '_story_media_ids';
    
    /**
     * Meta key for expiration hours
     */
    const META_EXPIRATION_HOURS = '_story_expiration_hours';
    
    /**
     * Meta key for creation timestamp
     */
    const META_CREATED_TIMESTAMP = '_story_created_timestamp';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'register_post_type'));
        add_action('save_post_' . self::POST_TYPE, array($this, 'save_story_meta'), 10, 2);
        add_action('wp_insert_post', array($this, 'set_creation_timestamp'), 10, 3);
        add_filter('rest_' . self::POST_TYPE . '_query', array($this, 'filter_rest_query'), 10, 2);
        
        // Expiration system hooks
        add_action('save_post_' . self::POST_TYPE, array($this, 'clear_expiration_cache'), 10, 1);
        add_action('wp_stories_cleanup_expired', array($this, 'cleanup_expired_stories'));
        add_action('init', array($this, 'schedule_cleanup_cron'));
    }
    
    /**
     * Register the wp_story custom post type
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('Stories', 'Post type general name', 'wp-stories-plugin'),
            'singular_name'         => _x('Story', 'Post type singular name', 'wp-stories-plugin'),
            'menu_name'             => _x('Stories', 'Admin Menu text', 'wp-stories-plugin'),
            'name_admin_bar'        => _x('Story', 'Add New on Toolbar', 'wp-stories-plugin'),
            'add_new'               => __('Add New', 'wp-stories-plugin'),
            'add_new_item'          => __('Add New Story', 'wp-stories-plugin'),
            'new_item'              => __('New Story', 'wp-stories-plugin'),
            'edit_item'             => __('Edit Story', 'wp-stories-plugin'),
            'view_item'             => __('View Story', 'wp-stories-plugin'),
            'all_items'             => __('All Stories', 'wp-stories-plugin'),
            'search_items'          => __('Search Stories', 'wp-stories-plugin'),
            'parent_item_colon'     => __('Parent Stories:', 'wp-stories-plugin'),
            'not_found'             => __('No stories found.', 'wp-stories-plugin'),
            'not_found_in_trash'    => __('No stories found in Trash.', 'wp-stories-plugin'),
            'featured_image'        => _x('Story Featured Image', 'Overrides the "Featured Image" phrase', 'wp-stories-plugin'),
            'set_featured_image'    => _x('Set featured image', 'Overrides the "Set featured image" phrase', 'wp-stories-plugin'),
            'remove_featured_image' => _x('Remove featured image', 'Overrides the "Remove featured image" phrase', 'wp-stories-plugin'),
            'use_featured_image'    => _x('Use as featured image', 'Overrides the "Use as featured image" phrase', 'wp-stories-plugin'),
            'archives'              => _x('Story archives', 'The post type archive label', 'wp-stories-plugin'),
            'insert_into_item'      => _x('Insert into story', 'Overrides the "Insert into post" phrase', 'wp-stories-plugin'),
            'uploaded_to_this_item' => _x('Uploaded to this story', 'Overrides the "Uploaded to this post" phrase', 'wp-stories-plugin'),
            'filter_items_list'     => _x('Filter stories list', 'Screen reader text for the filter links', 'wp-stories-plugin'),
            'items_list_navigation' => _x('Stories list navigation', 'Screen reader text for the pagination', 'wp-stories-plugin'),
            'items_list'            => _x('Stories list', 'Screen reader text for the items list', 'wp-stories-plugin'),
        );
        
        $args = array(
            'labels'             => $labels,
            'description'        => __('Stories for the WordPress Stories plugin', 'wp-stories-plugin'),
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'capabilities'       => array(
                'edit_post'          => 'edit_stories',
                'read_post'          => 'read_stories',
                'delete_post'        => 'delete_stories',
                'edit_posts'         => 'edit_stories',
                'edit_others_posts'  => 'edit_others_stories',
                'publish_posts'      => 'publish_stories',
                'read_private_posts' => 'read_private_stories',
                'delete_posts'       => 'delete_stories',
                'delete_private_posts' => 'delete_private_stories',
                'delete_published_posts' => 'delete_published_stories',
                'delete_others_posts' => 'delete_others_stories',
                'edit_private_posts' => 'edit_private_stories',
                'edit_published_posts' => 'edit_published_stories',
            ),
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-format-gallery',
            'show_in_menu'       => true, // Show as main menu - Story_Post_Type is principal
            'supports'           => array('title', 'custom-fields'),
            'show_in_rest'       => true,
            'rest_base'          => 'wp_story',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        );
        
        register_post_type(self::POST_TYPE, $args);
        
        // Add custom capabilities to administrator role
        $this->add_story_capabilities();
    }
    
    /**
     * Add story capabilities to administrator role
     */
    private function add_story_capabilities() {
        $role = get_role('administrator');
        
        if ($role) {
            $capabilities = array(
                'edit_stories',
                'read_stories',
                'delete_stories',
                'edit_others_stories',
                'publish_stories',
                'read_private_stories',
                'delete_private_stories',
                'delete_published_stories',
                'delete_others_stories',
                'edit_private_stories',
                'edit_published_stories',
            );
            
            foreach ($capabilities as $capability) {
                $role->add_cap($capability);
            }
        }
    }
    
    /**
     * Save story meta data
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     */
    public function save_story_meta($post_id, $post) {
        // Verify nonce for security
        if (!isset($_POST['wp_stories_meta_nonce']) || 
            !wp_verify_nonce($_POST['wp_stories_meta_nonce'], 'wp_stories_save_meta')) {
            return;
        }
        
        // Check if user has permission to edit this post
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Don't save meta data for autosaves or revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Save media IDs
        if (isset($_POST['story_media_ids'])) {
            $media_ids = array_map('intval', $_POST['story_media_ids']);
            $media_ids = array_filter($media_ids); // Remove empty values
            update_post_meta($post_id, self::META_MEDIA_IDS, $media_ids);
        } else {
            delete_post_meta($post_id, self::META_MEDIA_IDS);
        }
        
        // Save expiration hours
        if (isset($_POST['story_expiration_hours'])) {
            $expiration_hours = intval($_POST['story_expiration_hours']);
            if ($expiration_hours > 0) {
                update_post_meta($post_id, self::META_EXPIRATION_HOURS, $expiration_hours);
            } else {
                delete_post_meta($post_id, self::META_EXPIRATION_HOURS);
            }
        } else {
            delete_post_meta($post_id, self::META_EXPIRATION_HOURS);
        }
    }
    
    /**
     * Set creation timestamp when story is first published
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     * @param bool    $update  Whether this is an existing post being updated
     */
    public function set_creation_timestamp($post_id, $post, $update) {
        // Only set timestamp for wp_story post type
        if ($post->post_type !== self::POST_TYPE) {
            return;
        }
        
        // Only set timestamp when post is first published (not on updates)
        if (!$update && $post->post_status === 'publish') {
            $existing_timestamp = get_post_meta($post_id, self::META_CREATED_TIMESTAMP, true);
            
            // Only set if timestamp doesn't already exist
            if (empty($existing_timestamp)) {
                update_post_meta($post_id, self::META_CREATED_TIMESTAMP, current_time('timestamp'));
            }
        }
        
        // Also set timestamp when post status changes from draft/pending to publish
        if ($update && $post->post_status === 'publish') {
            $existing_timestamp = get_post_meta($post_id, self::META_CREATED_TIMESTAMP, true);
            
            if (empty($existing_timestamp)) {
                update_post_meta($post_id, self::META_CREATED_TIMESTAMP, current_time('timestamp'));
            }
        }
    }
    
    /**
     * Filter REST API query to exclude expired stories
     *
     * @param array           $args    Query arguments
     * @param WP_REST_Request $request REST request object
     * @return array Modified query arguments
     */
    public function filter_rest_query($args, $request) {
        // Only show published stories (active stories)
        // Don't apply complex meta queries that block all stories
        
        // Ensure only published stories are shown
        if (!isset($args['post_status'])) {
            $args['post_status'] = 'publish';
        }
        
        return $args;
    }
    
    /**
     * Get story media IDs
     *
     * @param int $post_id Post ID
     * @return array Array of media attachment IDs
     */
    public static function get_media_ids($post_id) {
        $media_ids = get_post_meta($post_id, self::META_MEDIA_IDS, true);
        return is_array($media_ids) ? $media_ids : array();
    }
    
    /**
     * Get story expiration hours
     *
     * @param int $post_id Post ID
     * @return int|null Expiration hours or null if not set
     */
    public static function get_expiration_hours($post_id) {
        $expiration_hours = get_post_meta($post_id, self::META_EXPIRATION_HOURS, true);
        return !empty($expiration_hours) ? intval($expiration_hours) : null;
    }
    
    /**
     * Get story creation timestamp
     *
     * @param int $post_id Post ID
     * @return int|null Creation timestamp or null if not set
     */
    public static function get_creation_timestamp($post_id) {
        $timestamp = get_post_meta($post_id, self::META_CREATED_TIMESTAMP, true);
        return !empty($timestamp) ? intval($timestamp) : null;
    }
    
    /**
     * Check if story is expired
     *
     * @param int $post_id Post ID
     * @return bool True if story is expired, false otherwise
     */
    public static function is_story_expired($post_id) {
        // Check cache first
        $cache_key = 'wp_stories_expired_' . $post_id;
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result === 'expired';
        }
        
        $expiration_hours = self::get_expiration_hours($post_id);
        
        // If no expiration is set, story never expires
        if (empty($expiration_hours)) {
            // Cache for 1 hour since no expiration is set
            set_transient($cache_key, 'not_expired', 3600);
            return false;
        }
        
        $creation_timestamp = self::get_creation_timestamp($post_id);
        
        // If no creation timestamp, consider it not expired
        if (empty($creation_timestamp)) {
            // Cache for 1 hour
            set_transient($cache_key, 'not_expired', 3600);
            return false;
        }
        
        $current_time = current_time('timestamp');
        $expiration_time = $creation_timestamp + ($expiration_hours * 3600);
        $is_expired = $current_time > $expiration_time;
        
        // Cache the result
        if ($is_expired) {
            // Cache expired status for 24 hours
            set_transient($cache_key, 'expired', 86400);
        } else {
            // Cache not expired status for shorter time based on remaining time
            $time_remaining = $expiration_time - $current_time;
            $cache_duration = min($time_remaining, 3600); // Max 1 hour cache
            set_transient($cache_key, 'not_expired', $cache_duration);
        }
        
        return $is_expired;
    }
    
    /**
     * Get time remaining for story in seconds
     *
     * @param int $post_id Post ID
     * @return int|null Time remaining in seconds, null if no expiration set
     */
    public static function get_time_remaining($post_id) {
        // Check cache first
        $cache_key = 'wp_stories_time_remaining_' . $post_id;
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result === 'no_expiration' ? null : intval($cached_result);
        }
        
        $expiration_hours = self::get_expiration_hours($post_id);
        
        // If no expiration is set, return null
        if (empty($expiration_hours)) {
            // Cache for 1 hour since no expiration is set
            set_transient($cache_key, 'no_expiration', 3600);
            return null;
        }
        
        $creation_timestamp = self::get_creation_timestamp($post_id);
        
        // If no creation timestamp, return null
        if (empty($creation_timestamp)) {
            // Cache for 1 hour
            set_transient($cache_key, 'no_expiration', 3600);
            return null;
        }
        
        $current_time = current_time('timestamp');
        $expiration_time = $creation_timestamp + ($expiration_hours * 3600);
        $time_remaining = max(0, $expiration_time - $current_time);
        
        // Cache the result for a shorter duration based on remaining time
        $cache_duration = min($time_remaining > 0 ? $time_remaining / 10 : 300, 1800); // Max 30 minutes cache
        set_transient($cache_key, $time_remaining, $cache_duration);
        
        return $time_remaining;
    }
    
    /**
     * Get formatted time remaining string
     *
     * @param int $post_id Post ID
     * @return string|null Formatted time string (e.g., "12h left", "2d left") or null
     */
    public static function get_formatted_time_remaining($post_id) {
        // Check cache first
        $cache_key = 'wp_stories_formatted_time_' . $post_id;
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result === 'no_expiration' ? null : $cached_result;
        }
        
        $time_remaining = self::get_time_remaining($post_id);
        
        if ($time_remaining === null || $time_remaining <= 0) {
            // Cache for 5 minutes
            set_transient($cache_key, 'no_expiration', 300);
            return null;
        }
        
        $hours = floor($time_remaining / 3600);
        $days = floor($hours / 24);
        
        $formatted_time = '';
        if ($days > 0) {
            $formatted_time = sprintf(_n('%dd left', '%dd left', $days, 'wp-stories-plugin'), $days);
        } else {
            $formatted_time = sprintf(_n('%dh left', '%dh left', $hours, 'wp-stories-plugin'), $hours);
        }
        
        // Cache for 10 minutes or remaining time, whichever is shorter
        $cache_duration = min($time_remaining > 0 ? $time_remaining / 6 : 300, 600);
        set_transient($cache_key, $formatted_time, $cache_duration);
        
        return $formatted_time;
    }
    
    /**
     * Get all active (non-expired) stories
     *
     * @param array $args Additional query arguments
     * @return WP_Query Query object with active stories
     */
    public static function get_active_stories($args = array()) {
        $default_args = array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );
        
        $args = wp_parse_args($args, $default_args);
        
        // Add meta query to exclude expired stories
        if (!isset($args['meta_query'])) {
            $args['meta_query'] = array();
        }
        
        $current_time = current_time('timestamp');
        
        $args['meta_query'][] = array(
            'relation' => 'OR',
            // Stories with no expiration
            array(
                'key'     => self::META_EXPIRATION_HOURS,
                'compare' => 'NOT EXISTS',
            ),
            // Stories that are not expired
            array(
                'relation' => 'AND',
                array(
                    'key'     => self::META_EXPIRATION_HOURS,
                    'compare' => 'EXISTS',
                ),
                array(
                    'key'     => self::META_CREATED_TIMESTAMP,
                    'value'   => $current_time,
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                ),
            ),
        );
        
        return new WP_Query($args);
    }
    
    /**
     * Clear expiration cache for a story
     *
     * @param int $post_id Post ID
     */
    public function clear_expiration_cache($post_id) {
        // Only clear cache for wp_story post type
        if (get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }
        
        $cache_keys = array(
            'wp_stories_expired_' . $post_id,
            'wp_stories_time_remaining_' . $post_id,
            'wp_stories_formatted_time_' . $post_id,
        );
        
        foreach ($cache_keys as $cache_key) {
            delete_transient($cache_key);
        }
    }
    
    /**
     * Clear all expiration caches
     */
    public static function clear_all_expiration_caches() {
        global $wpdb;
        
        // Delete all story expiration transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wp_stories_expired_%' 
             OR option_name LIKE '_transient_timeout_wp_stories_expired_%'
             OR option_name LIKE '_transient_wp_stories_time_remaining_%'
             OR option_name LIKE '_transient_timeout_wp_stories_time_remaining_%'
             OR option_name LIKE '_transient_wp_stories_formatted_time_%'
             OR option_name LIKE '_transient_timeout_wp_stories_formatted_time_%'"
        );
    }
    
    /**
     * Schedule cleanup cron job
     */
    public function schedule_cleanup_cron() {
        if (!wp_next_scheduled('wp_stories_cleanup_expired')) {
            wp_schedule_event(time(), 'hourly', 'wp_stories_cleanup_expired');
        }
    }
    
    /**
     * Cleanup expired stories (optional scheduled task)
     * This moves expired stories to draft status instead of deleting them
     */
    public function cleanup_expired_stories() {
        // Get all published stories
        $args = array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => self::META_EXPIRATION_HOURS,
                    'compare' => 'EXISTS',
                ),
                array(
                    'key' => self::META_CREATED_TIMESTAMP,
                    'compare' => 'EXISTS',
                ),
            ),
        );
        
        $stories = get_posts($args);
        $cleanup_count = 0;
        
        foreach ($stories as $story) {
            if (self::is_story_expired($story->ID)) {
                // Move to draft status instead of deleting
                wp_update_post(array(
                    'ID' => $story->ID,
                    'post_status' => 'draft',
                ));
                
                // Clear cache for this story
                $this->clear_expiration_cache($story->ID);
                
                $cleanup_count++;
                
                // Log the cleanup action
                error_log("WP Stories Plugin: Moved expired story {$story->ID} to draft status");
            }
        }
        
        if ($cleanup_count > 0) {
            // Clear all caches after cleanup
            self::clear_all_expiration_caches();
            
            error_log("WP Stories Plugin: Cleaned up {$cleanup_count} expired stories");
        }
    }
    
    /**
     * Get expiration statistics
     *
     * @return array Statistics about story expiration
     */
    public static function get_expiration_statistics() {
        $stats = array(
            'total_stories' => 0,
            'stories_with_expiration' => 0,
            'expired_stories' => 0,
            'expiring_soon' => 0, // Within 24 hours
            'active_stories' => 0,
        );
        
        // Get all published stories
        $args = array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
        );
        
        $stories = get_posts($args);
        $stats['total_stories'] = count($stories);
        
        $current_time = current_time('timestamp');
        
        foreach ($stories as $story) {
            $expiration_hours = self::get_expiration_hours($story->ID);
            
            if ($expiration_hours) {
                $stats['stories_with_expiration']++;
                
                $creation_timestamp = self::get_creation_timestamp($story->ID);
                if ($creation_timestamp) {
                    $expiration_time = $creation_timestamp + ($expiration_hours * 3600);
                    $time_remaining = $expiration_time - $current_time;
                    
                    if ($time_remaining <= 0) {
                        $stats['expired_stories']++;
                    } elseif ($time_remaining <= 86400) { // 24 hours
                        $stats['expiring_soon']++;
                        $stats['active_stories']++;
                    } else {
                        $stats['active_stories']++;
                    }
                } else {
                    $stats['active_stories']++;
                }
            } else {
                $stats['active_stories']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get stories expiring within specified hours
     *
     * @param int $hours Number of hours to check
     * @return array Array of story IDs expiring within the specified time
     */
    public static function get_stories_expiring_within($hours = 24) {
        $args = array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => self::META_EXPIRATION_HOURS,
                    'compare' => 'EXISTS',
                ),
                array(
                    'key' => self::META_CREATED_TIMESTAMP,
                    'compare' => 'EXISTS',
                ),
            ),
        );
        
        $stories = get_posts($args);
        $expiring_stories = array();
        $current_time = current_time('timestamp');
        $check_until = $current_time + ($hours * 3600);
        
        foreach ($stories as $story) {
            $expiration_hours = self::get_expiration_hours($story->ID);
            $creation_timestamp = self::get_creation_timestamp($story->ID);
            
            if ($expiration_hours && $creation_timestamp) {
                $expiration_time = $creation_timestamp + ($expiration_hours * 3600);
                
                if ($expiration_time > $current_time && $expiration_time <= $check_until) {
                    $expiring_stories[] = array(
                        'id' => $story->ID,
                        'title' => $story->post_title,
                        'expires_at' => $expiration_time,
                        'time_remaining' => $expiration_time - $current_time,
                    );
                }
            }
        }
        
        // Sort by expiration time (soonest first)
        usort($expiring_stories, function($a, $b) {
            return $a['expires_at'] - $b['expires_at'];
        });
        
        return $expiring_stories;
    }
}