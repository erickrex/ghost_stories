<?php
/**
 * Story Frontend Class
 *
 * Handles frontend rendering and asset management for stories
 *
 * @package WP_Stories_Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Story_Frontend {
    
    /**
     * Initialize the frontend functionality
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('wp_footer', array($this, 'render_story_modal_template'));
        add_shortcode('wp_stories', array($this, 'render_stories_shortcode'));
    }
    
    /**
     * Conditionally enqueue frontend assets only when stories are present
     */
    public function enqueue_frontend_assets() {
        // Only load assets if stories are present on the page
        if ($this->page_has_stories()) {
            // TEMPORARY: Force non-minified until we create minified versions
            // Determine if we should use minified assets
            $use_minified = false; // Disabled until minified files are created
            $css_suffix = $use_minified ? '.min.css' : '.css';
            $js_suffix = $use_minified ? '.min.js' : '.js';
            
            // Enqueue optimized CSS with cache headers
            wp_enqueue_style(
                'wp-stories-frontend',
                plugin_dir_url(dirname(__FILE__)) . 'assets/css/frontend' . $css_suffix,
                array(),
                GHOST_STORIES_VERSION . '.' . time()
            );
            
            // Enqueue modal CSS
            wp_enqueue_style(
                'wp-stories-modal',
                plugin_dir_url(dirname(__FILE__)) . 'assets/css/modal.css',
                array('wp-stories-frontend'),
                GHOST_STORIES_VERSION . '.' . time()
            );
            
            // Add cache headers for CSS
            add_filter('style_loader_tag', array($this, 'add_cache_headers_to_css'), 10, 4);
            
            // Enqueue optimized JavaScript with lazy loading support
            // TEMPORARY: Using minimal version for debugging
            wp_enqueue_script(
                'wp-stories-frontend',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/frontend-minimal.js',
                array('jquery'),
                GHOST_STORIES_VERSION . '-minimal',
                true
            );
            
            // Add cache headers for JS
            add_filter('script_loader_tag', array($this, 'add_cache_headers_to_js'), 10, 3);
            
            // Preload critical assets for better performance
            $this->preload_critical_assets();
            
            // Localize script with enhanced configuration
            wp_localize_script('wp-stories-frontend', 'wpStories', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wp_stories_nonce'),
                'restUrl' => rest_url('wp-stories/v1/'),
                'restNonce' => wp_create_nonce('wp_rest'),
                'assetUrl' => plugin_dir_url(dirname(__FILE__)) . 'assets/',
                'version' => GHOST_STORIES_VERSION,
                'debug' => defined('WP_DEBUG') && WP_DEBUG,
                'lazyLoading' => $this->should_use_lazy_loading(),
                'adaptiveQuality' => $this->should_use_adaptive_quality(),
                'cdnUrl' => $this->get_cdn_url(),
                'performance' => array(
                    'preloadLimit' => $this->get_preload_limit(),
                    'memoryThreshold' => 0.8,
                    'networkAware' => true,
                    'deviceOptimization' => true
                )
            ));
            
            // Load lazy loading modules conditionally
            $this->enqueue_lazy_modules();
        }
    }
    
    /**
     * Check if the current page has stories
     */
    private function page_has_stories() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Check if page has stories block
        if (has_block('wp-stories/stories-block', $post)) {
            return true;
        }
        
        // Check if it's a story post type
        if (is_singular('wp_story')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Render stories for a given array of story IDs
     */
    public function render_stories($story_ids, $alignment = 'left') {
        // EMERGENCY FIX: Return empty on ANY error
        try {
            // Validate input
            if (empty($story_ids) || !is_array($story_ids)) {
                return '';
            }
            
            // Sanitize story IDs
            $story_ids = array_map('absint', $story_ids);
            $story_ids = array_filter($story_ids);
            
            if (empty($story_ids)) {
                return '';
            }
            
            // Get stories with error suppression
            $stories = @$this->get_active_stories($story_ids);
            
            if (empty($stories) || !is_array($stories)) {
                return '';
            }
            
            $output = '<div class="wp-stories-container wp-stories-align-' . esc_attr($alignment) . '">';
            $output .= '<div class="wp-stories-circles">';
            
            $rendered_count = 0;
            
            foreach ($stories as $story) {
                try {
                    // Skip if story is invalid
                    if (!$story || !isset($story->ID)) {
                        continue;
                    }
                    
                    $circle_html = @$this->render_story_circle($story);
                    
                    if (!empty($circle_html)) {
                        $output .= $circle_html;
                        $rendered_count++;
                    }
                } catch (Throwable $e) {
                    // Skip this story, continue with others
                    continue;
                }
            }
            
            $output .= '</div>';
            $output .= '</div>';
            
            // Return empty if no stories were rendered
            if ($rendered_count === 0) {
                return '';
            }
            
            return $output;
            
        } catch (Throwable $e) {
            // Catch EVERYTHING - never crash
            error_log('WP Stories ERROR in render_stories: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Render a single story circle
     */
    private function render_story_circle($story) {
        // EMERGENCY FIX: Return empty on ANY error
        try {
            // Validate story object
            if (!$story || !isset($story->ID)) {
                return '';
            }
            
            // Try to create story object - return empty if fails
            try {
                $story_obj = @new WP_Story($story->ID);
            } catch (Throwable $e) {
                return '';
            }
            
            // Get media items - return empty if fails
            try {
                $media_items = @$story_obj->get_media_items();
            } catch (Throwable $e) {
                return '';
            }
            
            if (empty($media_items) || !is_array($media_items)) {
                return '';
            }
            
            // Get thumbnail - prioritize featured image (like WP Story Premium)
            if (has_post_thumbnail($story->ID)) {
                $thumbnail_url = get_the_post_thumbnail_url($story->ID, 'medium');
            } else {
                // Fallback to saved thumbnail URL in meta
                $thumbnail_url = get_post_meta($story->ID, '_story_thumbnail_url', true);
            }
            
            // Check if story has video content
            $has_video = false;
            try {
                foreach ($media_items as $media) {
                    if ($media->get_type() === 'video') {
                        $has_video = true;
                        break;
                    }
                }
            } catch (Throwable $e) {
                // Ignore errors
            }
            
            // Build output
            $output = '<div class="wp-stories-circle-wrapper">';
            $circle_class = 'wp-stories-circle';
            $data_attributes = 'data-story-id="' . esc_attr($story->ID) . '"';
            if ($has_video) {
                $data_attributes .= ' data-has-video="true"';
            }
            $output .= '<div class="' . $circle_class . '" ' . $data_attributes . '>';
            $output .= '<div class="wp-stories-circle-inner">';
            $output .= '<div class="wp-stories-circle-content">';
            
            // Always show the saved thumbnail image
            if ($thumbnail_url) {
                $output .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($story->post_title) . '" class="wp-stories-thumbnail" />';
            } else {
                // Fallback if no thumbnail saved
                $output .= '<div class="wp-stories-video-bg"></div>';
            }
            
            $output .= '</div></div></div>';
            
            // Get story title and subtitle from meta
            $story_title = get_post_meta($story->ID, '_story_title', true);
            $story_subtitle = get_post_meta($story->ID, '_story_detail', true);
            
            // Story title (from meta field)
            if (!empty($story_title)) {
                $output .= '<span class="wp-stories-circle-label">' . esc_html($story_title) . '</span>';
            } else {
                // Fallback to post title if meta title is empty
                $output .= '<span class="wp-stories-circle-label">' . esc_html($story->post_title) . '</span>';
            }
            
            // Story subtitle (from meta field)
            if (!empty($story_subtitle)) {
                $output .= '<span class="wp-stories-circle-subtitle">' . esc_html($story_subtitle) . '</span>';
            }
            
            $output .= '</div>';
            
            return $output;
            
        } catch (Throwable $e) {
            // Never crash - just return empty
            return '';
        }
    }
    
    /**
     * Get active stories by IDs, filtering out expired ones
     */
    private function get_active_stories($story_ids) {
        // EMERGENCY FIX: Return empty array on ANY error
        try {
            $stories = array();
            
            foreach ($story_ids as $story_id) {
                try {
                    // Get post - skip if fails
                    $story = @get_post($story_id);
                    
                    if (!$story || $story->post_type !== 'wp_story' || $story->post_status !== 'publish') {
                        continue;
                    }
                    
                    // Check expiration - skip if fails
                    try {
                        if (@Story_Post_Type::is_story_expired($story_id)) {
                            continue;
                        }
                    } catch (Throwable $e) {
                        continue;
                    }
                    
                    // Validate media - skip if fails
                    try {
                        $story_obj = @new WP_Story($story_id);
                        if (!@$story_obj->has_valid_media()) {
                            continue;
                        }
                    } catch (Throwable $e) {
                        continue;
                    }
                    
                    $stories[] = $story;
                    
                } catch (Throwable $e) {
                    // Skip this story
                    continue;
                }
            }
            
            return $stories;
            
        } catch (Throwable $e) {
            // Return empty array - never crash
            return array();
        }
    }
    
    /**
     * Get story thumbnail URL
     */
    private function get_story_thumbnail($media_item) {
        try {
            if (!$media_item || !method_exists($media_item, 'get_type')) {
                return '';
            }
            
            $attachment_id = $media_item->get_attachment_id();
            
            if (!$attachment_id) {
                return '';
            }
            
            if ($media_item->get_type() === 'image') {
                $url = wp_get_attachment_image_url($attachment_id, 'medium');
                return $url ? $url : wp_get_attachment_image_url($attachment_id, 'thumbnail');
            } else {
                // For videos, get the poster/thumbnail
                $thumbnail_id = get_post_thumbnail_id($attachment_id);
                if ($thumbnail_id) {
                    return wp_get_attachment_image_url($thumbnail_id, 'medium');
                }
                
                // Try to get auto-generated video thumbnail
                $video_url = wp_get_attachment_url($attachment_id);
                if ($video_url) {
                    // Check if WordPress has generated a thumbnail for this video
                    $thumbnail_path = get_attached_file($attachment_id);
                    if ($thumbnail_path) {
                        $thumbnail_dir = dirname($thumbnail_path);
                        $thumbnail_name = pathinfo($thumbnail_path, PATHINFO_FILENAME);
                        $thumbnail_extensions = array('.jpg', '.jpeg', '.png', '.webp');
                        
                        foreach ($thumbnail_extensions as $ext) {
                            $possible_thumbnail = $thumbnail_dir . '/' . $thumbnail_name . $ext;
                            if (file_exists($possible_thumbnail)) {
                                $upload_dir = wp_upload_dir();
                                $thumbnail_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $possible_thumbnail);
                                return $thumbnail_url;
                            }
                        }
                    }
                }
                
                // Fallback to a default video thumbnail
                return GHOST_STORIES_URL . 'assets/images/video-placeholder.svg';
            }
        } catch (Exception $e) {
            error_log('WP Stories: Error getting thumbnail: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Get responsive image HTML
     */
    private function get_responsive_image($media_item, $alt_text = '') {
        $attachment_id = $media_item->get_attachment_id();
        
        if ($media_item->get_type() === 'image') {
            return wp_get_attachment_image(
                $attachment_id,
                'medium',
                false,
                array(
                    'class' => 'wp-stories-image',
                    'alt' => esc_attr($alt_text),
                    'loading' => 'lazy'
                )
            );
        } else {
            // For videos, show thumbnail
            $thumbnail_url = $this->get_story_thumbnail($media_item);
            return '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($alt_text) . '" class="wp-stories-video-thumb" loading="lazy">';
        }
    }
    
    /**
     * Format time remaining for display
     */
    private function format_time_remaining($seconds_remaining) {
        if ($seconds_remaining === null) {
            return ''; // No expiration
        }
        
        if ($seconds_remaining <= 0) {
            return ''; // Expired
        }
        
        $hours = floor($seconds_remaining / 3600);
        $days = floor($hours / 24);
        
        if ($days > 0) {
            return $days . 'd left';
        } else {
            return $hours . 'h left';
        }
    }
    
    /**
     * Render stories shortcode
     */
    public function render_stories_shortcode($atts) {
        $atts = shortcode_atts(array(
            'ids' => '',
            'alignment' => 'left'
        ), $atts);
        
        if (empty($atts['ids'])) {
            return '';
        }
        
        $story_ids = array_map('intval', explode(',', $atts['ids']));
        
        return $this->render_stories($story_ids, $atts['alignment']);
    }
    
    /**
     * Render the story modal template in footer
     */
    public function render_story_modal_template() {
        if ($this->page_has_stories()) {
            include plugin_dir_path(dirname(__FILE__)) . 'templates/story-modal.php';
        }
    }
    
    /**
     * Add cache headers to CSS files
     */
    public function add_cache_headers_to_css($tag, $handle, $href, $media) {
        if (strpos($handle, 'wp-stories') !== false) {
            // Add cache-friendly attributes
            $tag = str_replace('<link ', '<link crossorigin="anonymous" ', $tag);
            
            // Add preload hint for critical CSS
            if ($handle === 'wp-stories-frontend') {
                $preload_tag = '<link rel="preload" href="' . esc_url($href) . '" as="style" crossorigin="anonymous">' . "\n";
                $tag = $preload_tag . $tag;
            }
        }
        
        return $tag;
    }
    
    /**
     * Add cache headers to JavaScript files
     */
    public function add_cache_headers_to_js($tag, $handle, $src) {
        if (strpos($handle, 'wp-stories') !== false) {
            // Add cache-friendly attributes
            $tag = str_replace('<script ', '<script crossorigin="anonymous" ', $tag);
            
            // Add async loading for non-critical scripts
            if ($handle !== 'wp-stories-frontend') {
                $tag = str_replace('<script ', '<script async ', $tag);
            }
        }
        
        return $tag;
    }
    
    /**
     * Preload critical assets for better performance
     */
    private function preload_critical_assets() {
        $asset_url = plugin_dir_url(dirname(__FILE__)) . 'assets/';
        
        // Preload critical images
        $critical_images = array(
            'images/video-placeholder.svg',
            'images/thumbnails/placeholder-small.webp',
            'images/thumbnails/loading-small.webp'
        );
        
        foreach ($critical_images as $image) {
            echo '<link rel="preload" href="' . esc_url($asset_url . $image) . '" as="image" crossorigin="anonymous">' . "\n";
        }
        
        // Preload fonts if any
        $this->preload_fonts();
    }
    
    /**
     * Preload web fonts for better performance
     */
    private function preload_fonts() {
        // Add font preloading if custom fonts are used
        // This is a placeholder for future font optimization
    }
    
    /**
     * Enqueue lazy loading modules conditionally
     */
    private function enqueue_lazy_modules() {
        $asset_url = plugin_dir_url(dirname(__FILE__)) . 'assets/js/';
        
        // Only load touch module on touch devices
        if ($this->is_touch_device()) {
            wp_enqueue_script(
                'wp-stories-lazy-touch',
                $asset_url . 'lazy-touch.js',
                array(),
                GHOST_STORIES_VERSION,
                true
            );
        }
        
        // Only load video module if videos are present
        if ($this->page_has_videos()) {
            wp_enqueue_script(
                'wp-stories-lazy-video',
                $asset_url . 'lazy-video.js',
                array(),
                GHOST_STORIES_VERSION,
                true
            );
        }
    }
    
    /**
     * Check if device supports touch
     */
    private function is_touch_device() {
        // Simple user agent check (can be enhanced with JavaScript detection)
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        return preg_match('/(tablet|ipad|playbook)|(android(?!.*mobile))|(kindle|silk)/i', $user_agent) ||
               preg_match('/(mobi|ipod|phone|blackberry|opera mini|fennec|minimo|symbian|psp|nintendo ds|archos|skyfire|puffin|blazer|bolt|gobrowser|iris|maemo|semc|teashark|uzard)/i', $user_agent);
    }
    
    /**
     * Check if page has video stories
     */
    private function page_has_videos() {
        global $post;
        
        if (!$post || !has_block('wp-stories/stories-block', $post)) {
            return false;
        }
        
        // Parse blocks to check for video content
        $blocks = parse_blocks($post->post_content);
        
        foreach ($blocks as $block) {
            if ($block['blockName'] === 'wp-stories/stories-block') {
                $story_ids = $block['attrs']['selectedStories'] ?? array();
                
                foreach ($story_ids as $story_id) {
                    $story = new WP_Story($story_id);
                    if ($story->has_video_media()) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Determine if lazy loading should be used
     */
    private function should_use_lazy_loading() {
        // Enable lazy loading by default, disable for specific cases
        return !$this->is_amp_page() && !$this->is_critical_page();
    }
    
    /**
     * Determine if adaptive quality should be used
     */
    private function should_use_adaptive_quality() {
        // Enable adaptive quality for mobile devices and slow connections
        return $this->is_mobile_device() || $this->is_slow_connection();
    }
    
    /**
     * Get CDN URL if configured
     */
    private function get_cdn_url() {
        // Check for CDN configuration
        $cdn_url = get_option('wp_stories_cdn_url', '');
        
        if (empty($cdn_url)) {
            // Try to detect common CDN patterns
            $site_url = get_site_url();
            if (strpos($site_url, 'cloudfront.net') !== false || 
                strpos($site_url, 'cdn.') !== false) {
                return $site_url;
            }
        }
        
        return $cdn_url;
    }
    
    /**
     * Get preload limit based on device capabilities
     */
    private function get_preload_limit() {
        if ($this->is_low_end_device()) {
            return 1; // Minimal preloading for low-end devices
        } elseif ($this->is_mobile_device()) {
            return 2; // Moderate preloading for mobile
        } else {
            return 3; // Full preloading for desktop
        }
    }
    
    /**
     * Check if device is low-end
     */
    private function is_low_end_device() {
        // Simple heuristic based on user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Check for known low-end device patterns
        $low_end_patterns = array(
            '/Android [2-4]\./i',
            '/iPhone OS [6-9]_/i',
            '/Opera Mini/i',
            '/UC Browser/i'
        );
        
        foreach ($low_end_patterns as $pattern) {
            if (preg_match($pattern, $user_agent)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if device is mobile
     */
    private function is_mobile_device() {
        return wp_is_mobile();
    }
    
    /**
     * Check if connection is slow
     */
    private function is_slow_connection() {
        // Check for connection type hints if available
        $connection = $_SERVER['HTTP_DOWNLINK'] ?? null;
        
        if ($connection !== null && floatval($connection) < 1.0) {
            return true; // Less than 1 Mbps
        }
        
        // Fallback to user agent hints
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return strpos($user_agent, '2G') !== false || strpos($user_agent, '3G') !== false;
    }
    
    /**
     * Check if page is AMP
     */
    private function is_amp_page() {
        return function_exists('is_amp_endpoint') && is_amp_endpoint();
    }
    
    /**
     * Check if page is critical (homepage, landing pages)
     */
    private function is_critical_page() {
        return is_front_page() || is_home();
    }
    
    /**
     * Clear frontend caches
     */
    public static function clear_frontend_caches() {
        global $wpdb;
        
        // Delete all frontend story caches
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wp_stories_active_%' 
             OR option_name LIKE '_transient_timeout_wp_stories_active_%'"
        );
        
        // Clear asset optimization caches
        wp_cache_delete('wp_stories_asset_optimization', 'wp_stories');
        wp_cache_delete('wp_stories_thumbnail_cache', 'wp_stories');
    }
    
    /**
     * Get stories with expiration info for frontend
     *
     * @param array $story_ids Array of story IDs
     * @return array Array of story data with expiration info
     */
    public function get_stories_with_expiration($story_ids) {
        $stories_data = array();
        
        foreach ($story_ids as $story_id) {
            if (Story_Post_Type::is_story_expired($story_id)) {
                continue; // Skip expired stories
            }
            
            try {
                $story = new WP_Story($story_id);
                
                if (!$story->has_valid_media()) {
                    continue; // Skip stories without media
                }
                
                $stories_data[] = array(
                    'id' => $story->get_id(),
                    'title' => $story->get_title(),
                    'thumbnail_url' => $story->get_thumbnail_url('medium'),
                    'media_count' => $story->get_valid_media_count(),
                    'time_remaining' => $story->get_time_remaining(),
                    'formatted_time_remaining' => $story->get_formatted_time_remaining(),
                    'is_expired' => $story->is_expired(),
                );
                
            } catch (Exception $e) {
                // Skip invalid stories
                continue;
            }
        }
        
        return $stories_data;
    }
}