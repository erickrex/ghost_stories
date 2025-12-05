<?php
/**
 * Story API class
 *
 * Handles REST API endpoints for frontend data access
 *
 * @package WP_Stories_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Story API class
 */
class Story_API {
    
    /**
     * API namespace
     */
    const NAMESPACE = 'wp-stories/v1';
    
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
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // GET /wp-json/wp-stories/v1/stories - Get active stories
        register_rest_route(
            self::NAMESPACE,
            '/stories',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_stories'),
                'permission_callback' => array($this, 'get_stories_permissions_check'),
                'args' => $this->get_stories_collection_params(),
            )
        );
        
        // GET /wp-json/wp-stories/v1/stories/{id} - Get specific story
        register_rest_route(
            self::NAMESPACE,
            '/stories/(?P<id>\d+)',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_story'),
                'permission_callback' => array($this, 'get_story_permissions_check'),
                'args' => array(
                    'id' => array(
                        'description' => __('Unique identifier for the story.', 'wp-stories-plugin'),
                        'type' => 'integer',
                        'required' => true,
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric($param) && $param > 0 && $param <= PHP_INT_MAX;
                        },
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );
        
        // POST /wp-json/wp-stories/v1/stories/{id}/view - Track story views (with CSRF protection)
        register_rest_route(
            self::NAMESPACE,
            '/stories/(?P<id>\d+)/view',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'track_story_view'),
                'permission_callback' => array($this, 'track_view_permissions_check'),
                'args' => array(
                    'id' => array(
                        'description' => __('Unique identifier for the story.', 'wp-stories-plugin'),
                        'type' => 'integer',
                        'required' => true,
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric($param) && $param > 0 && $param <= PHP_INT_MAX;
                        },
                        'sanitize_callback' => 'absint',
                    ),
                    'nonce' => array(
                        'description' => __('Security nonce for CSRF protection.', 'wp-stories-plugin'),
                        'type' => 'string',
                        'required' => true,
                        'validate_callback' => function($param, $request, $key) {
                            return wp_verify_nonce($param, 'wp_stories_view_nonce');
                        },
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );
    }
    
    /**
     * Get stories collection with comprehensive error handling
     *
     * @param WP_REST_Request $request Full details about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function get_stories($request) {
        try {
            // Validate and sanitize parameters
            $per_page = $this->validate_per_page($request->get_param('per_page'));
            $page = $this->validate_page($request->get_param('page'));
            $include_expired = rest_sanitize_boolean($request->get_param('include_expired'));
            $orderby = $this->validate_orderby($request->get_param('orderby'));
            $order = $this->validate_order($request->get_param('order'));
            
            // Build query arguments with error handling
            $args = array(
                'posts_per_page' => $per_page,
                'paged' => $page,
                'orderby' => $orderby,
                'order' => $order,
                'meta_query' => array(
                    array(
                        'key' => '_story_media_ids',
                        'compare' => 'EXISTS'
                    )
                )
            );
            
            // Get stories collection with error handling
            $collection = $this->get_stories_collection_safely($args);
            if (is_wp_error($collection)) {
                return $collection;
            }
            
            // Filter expired stories if not requested
            if (!$include_expired) {
                try {
                    $collection = $collection->filter_expired();
                } catch (Exception $e) {
                    $this->log_error('Failed to filter expired stories', array(
                        'error' => $e->getMessage(),
                        'args' => $args
                    ));
                    // Continue with unfiltered collection
                }
            }
            
            // Sort by expiration if requested
            if ($orderby === 'expiration') {
                try {
                    $collection = $collection->sort_by_expiration($order);
                } catch (Exception $e) {
                    $this->log_error('Failed to sort by expiration', array(
                        'error' => $e->getMessage(),
                        'order' => $order
                    ));
                    // Continue with unsorted collection
                }
            }
            
            // Prepare response data with error handling
            $stories_data = array();
            $skipped_stories = 0;
            
            foreach ($collection->get_stories() as $story) {
                try {
                    // Validate story object
                    if (!$story instanceof WP_Story) {
                        $skipped_stories++;
                        continue;
                    }
                    
                    // Skip stories without valid media
                    if (!$story->has_valid_media()) {
                        $skipped_stories++;
                        continue;
                    }
                    
                    $story_data = $this->prepare_story_for_response($story);
                    if ($story_data) {
                        $stories_data[] = $story_data;
                    } else {
                        $skipped_stories++;
                    }
                    
                } catch (Exception $e) {
                    $this->log_error('Failed to prepare story for response', array(
                        'story_id' => $story ? $story->get_id() : 'unknown',
                        'error' => $e->getMessage()
                    ));
                    $skipped_stories++;
                }
            }
            
            // Log if stories were skipped
            if ($skipped_stories > 0) {
                $this->log_error('Skipped stories in collection', array(
                    'skipped_count' => $skipped_stories,
                    'total_requested' => count($collection->get_stories())
                ));
            }
            
            // Prepare response with validation
            $total_stories = count($stories_data);
            $total_pages = $per_page > 0 ? ceil($total_stories / $per_page) : 1;
            
            $response_data = array(
                'stories' => $stories_data,
                'total' => $total_stories,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => $total_pages,
                'skipped' => $skipped_stories,
                'timestamp' => current_time('c')
            );
            
            $response = new WP_REST_Response($response_data, 200);
            
            // Add appropriate cache headers based on content
            $cache_time = $this->calculate_cache_time($stories_data);
            $response->header('Cache-Control', 'public, max-age=' . $cache_time);
            $response->header('X-Ghost-Stories-Version', GHOST_STORIES_VERSION);
            
            return $response;
            
        } catch (Exception $e) {
            $this->log_error('Critical error in get_stories', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->get_params()
            ));
            
            return new WP_Error(
                'stories_fetch_error',
                __('Unable to fetch stories due to a server error. Please try again later.', 'wp-stories-plugin'),
                array('status' => 500, 'error_code' => 'STORIES_FETCH_FAILED')
            );
        }
    }
    
    /**
     * Get individual story with comprehensive error handling
     *
     * @param WP_REST_Request $request Full details about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function get_story($request) {
        // EMERGENCY DEBUG: Log everything
        error_log('=== GHOST STORIES API DEBUG ===');
        error_log('get_story called for ID: ' . $request->get_param('id'));
        
        try {
            $story_id = (int) $request->get_param('id');
            error_log('Story ID validated: ' . $story_id);
            
            // Validate story ID
            if ($story_id <= 0) {
                error_log('Invalid story ID: ' . $story_id);
                return new WP_Error(
                    'invalid_story_id',
                    __('Invalid story ID provided.', 'wp-stories-plugin'),
                    array('status' => 400, 'error_code' => 'INVALID_ID')
                );
            }
            
            // Check if story exists with error handling
            error_log('Getting post...');
            $post = get_post($story_id);
            if (!$post) {
                error_log('Post not found: ' . $story_id);
                $this->log_error('Story post not found', array('story_id' => $story_id));
                return new WP_Error(
                    'story_not_found',
                    __('The requested story could not be found.', 'wp-stories-plugin'),
                    array('status' => 404, 'error_code' => 'STORY_NOT_FOUND')
                );
            }
            error_log('Post found: ' . $post->post_title . ' (type: ' . $post->post_type . ')');
            
            // Validate post type
            if ($post->post_type !== Story_Post_Type::POST_TYPE) {
                $this->log_error('Invalid post type for story', array(
                    'story_id' => $story_id,
                    'post_type' => $post->post_type,
                    'expected' => Story_Post_Type::POST_TYPE
                ));
                return new WP_Error(
                    'invalid_story_type',
                    __('The requested content is not a story.', 'wp-stories-plugin'),
                    array('status' => 404, 'error_code' => 'INVALID_TYPE')
                );
            }
            
            // Check if story is published
            if ($post->post_status !== 'publish') {
                $this->log_error('Story not published', array(
                    'story_id' => $story_id,
                    'status' => $post->post_status
                ));
                return new WP_Error(
                    'story_not_available',
                    __('This story is not currently available.', 'wp-stories-plugin'),
                    array('status' => 404, 'error_code' => 'NOT_PUBLISHED')
                );
            }
            
            // Create story object with error handling
            error_log('Creating WP_Story object...');
            try {
                $story = new WP_Story($story_id);
                error_log('WP_Story object created successfully');
            } catch (Exception $e) {
                error_log('FAILED to create WP_Story: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
                $this->log_error('Failed to create story object', array(
                    'story_id' => $story_id,
                    'error' => $e->getMessage()
                ));
                return new WP_Error(
                    'story_load_error',
                    __('Unable to load story data.', 'wp-stories-plugin'),
                    array('status' => 500, 'error_code' => 'LOAD_FAILED')
                );
            } catch (Throwable $e) {
                error_log('FATAL ERROR creating WP_Story: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
                return new WP_Error(
                    'story_load_error',
                    __('Fatal error loading story data.', 'wp-stories-plugin'),
                    array('status' => 500, 'error_code' => 'FATAL_ERROR')
                );
            }
            
            // Check if story is expired
            try {
                if ($story->is_expired()) {
                    $this->log_error('Attempted to access expired story', array(
                        'story_id' => $story_id,
                        'expiration_hours' => $story->get_expiration_hours(),
                        'created_at' => $story->get_created_timestamp()
                    ));
                    return new WP_Error(
                        'story_expired',
                        __('This story has expired and is no longer available.', 'wp-stories-plugin'),
                        array('status' => 410, 'error_code' => 'EXPIRED') // Gone
                    );
                }
            } catch (Exception $e) {
                $this->log_error('Error checking story expiration', array(
                    'story_id' => $story_id,
                    'error' => $e->getMessage()
                ));
                // Continue - assume not expired if we can't check
            }
            
            // Check if story has valid media
            try {
                if (!$story->has_valid_media()) {
                    $this->log_error('Story has no valid media', array(
                        'story_id' => $story_id,
                        'media_count' => count($story->get_media_items())
                    ));
                    return new WP_Error(
                        'story_no_content',
                        __('This story has no available content.', 'wp-stories-plugin'),
                        array('status' => 404, 'error_code' => 'NO_MEDIA')
                    );
                }
            } catch (Exception $e) {
                $this->log_error('Error validating story media', array(
                    'story_id' => $story_id,
                    'error' => $e->getMessage()
                ));
                return new WP_Error(
                    'story_validation_error',
                    __('Unable to validate story content.', 'wp-stories-plugin'),
                    array('status' => 500, 'error_code' => 'VALIDATION_FAILED')
                );
            }
            
            // Prepare response with error handling
            try {
                $story_data = $this->prepare_story_for_response($story);
                if (!$story_data) {
                    throw new Exception('Failed to prepare story data');
                }
            } catch (Exception $e) {
                $this->log_error('Failed to prepare story response', array(
                    'story_id' => $story_id,
                    'error' => $e->getMessage()
                ));
                return new WP_Error(
                    'story_prepare_error',
                    __('Unable to prepare story data for display.', 'wp-stories-plugin'),
                    array('status' => 500, 'error_code' => 'PREPARE_FAILED')
                );
            }
            
            $response = new WP_REST_Response($story_data, 200);
            
            // Add cache headers with error handling
            try {
                $cache_time = $this->calculate_story_cache_time($story);
                $response->header('Cache-Control', 'public, max-age=' . $cache_time);
                $response->header('X-Ghost-Stories-Version', GHOST_STORIES_VERSION);
                $response->header('X-Story-ID', $story_id);
            } catch (Exception $e) {
                $this->log_error('Failed to set cache headers', array(
                    'story_id' => $story_id,
                    'error' => $e->getMessage()
                ));
                // Set default cache headers
                $response->header('Cache-Control', 'public, max-age=300');
            }
            
            return $response;
            
        } catch (Exception $e) {
            $this->log_error('Critical error in get_story', array(
                'story_id' => isset($story_id) ? $story_id : 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            
            return new WP_Error(
                'story_fetch_error',
                __('Unable to fetch story due to a server error. Please try again later.', 'wp-stories-plugin'),
                array('status' => 500, 'error_code' => 'CRITICAL_ERROR')
            );
        }
    }
    
    /**
     * Error handling and utility methods
     */
    
    /**
     * Log errors for debugging and troubleshooting
     *
     * @param string $message Error message
     * @param array $context Additional context data
     */
    private function log_error($message, $context = array()) {
        $log_data = array(
            'timestamp' => current_time('c'),
            'message' => $message,
            'context' => $context,
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'ip_address' => $this->get_client_ip(),
        );
        
        // Log to WordPress error log
        error_log('[WP Stories API Error] ' . wp_json_encode($log_data));
        
        // Store in database for admin review (optional)
        if (defined('WP_STORIES_LOG_ERRORS') && WP_STORIES_LOG_ERRORS) {
            $this->store_error_log($log_data);
        }
        
        // Trigger action for custom error handling
        do_action('wp_stories_api_error', $message, $context);
    }
    
    /**
     * Get client IP address safely
     *
     * @return string Client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    }
    
    /**
     * Store error log in database
     *
     * @param array $log_data Error log data
     */
    private function store_error_log($log_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wp_stories_error_log';
        
        // Create table if it doesn't exist
        $this->maybe_create_error_log_table();
        
        try {
            $wpdb->insert(
                $table_name,
                array(
                    'timestamp' => current_time('mysql'),
                    'message' => $log_data['message'],
                    'context' => wp_json_encode($log_data['context']),
                    'request_uri' => $log_data['request_uri'],
                    'user_agent' => $log_data['user_agent'],
                    'ip_address' => $log_data['ip_address'],
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s')
            );
        } catch (Exception $e) {
            // Silently fail to avoid infinite error loops
            error_log('[WP Stories] Failed to store error log: ' . $e->getMessage());
        }
    }
    
    /**
     * Create error log table if needed
     */
    private function maybe_create_error_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wp_stories_error_log';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            message text NOT NULL,
            context longtext,
            request_uri varchar(255),
            user_agent text,
            ip_address varchar(45),
            PRIMARY KEY (id),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Validate per_page parameter
     *
     * @param mixed $per_page Per page value
     * @return int Validated per page value
     */
    private function validate_per_page($per_page) {
        $per_page = absint($per_page);
        if ($per_page < 1) {
            $per_page = 10;
        } elseif ($per_page > 100) {
            $per_page = 100;
        }
        return $per_page;
    }
    
    /**
     * Validate page parameter
     *
     * @param mixed $page Page value
     * @return int Validated page value
     */
    private function validate_page($page) {
        $page = absint($page);
        return $page < 1 ? 1 : $page;
    }
    
    /**
     * Validate orderby parameter
     *
     * @param mixed $orderby Orderby value
     * @return string Validated orderby value
     */
    private function validate_orderby($orderby) {
        $allowed_orderby = array('date', 'title', 'expiration');
        $orderby = sanitize_text_field($orderby);
        return in_array($orderby, $allowed_orderby, true) ? $orderby : 'date';
    }
    
    /**
     * Validate order parameter
     *
     * @param mixed $order Order value
     * @return string Validated order value
     */
    private function validate_order($order) {
        $order = strtolower(sanitize_text_field($order));
        return in_array($order, array('asc', 'desc'), true) ? $order : 'desc';
    }
    
    /**
     * Get stories collection safely with error handling
     *
     * @param array $args Query arguments
     * @return Story_Collection|WP_Error Collection object or error
     */
    private function get_stories_collection_safely($args) {
        try {
            // Validate that Story_Collection class exists
            if (!class_exists('Story_Collection')) {
                throw new Exception('Story_Collection class not found');
            }
            
            $collection = Story_Collection::get_all_active($args);
            
            if (!$collection instanceof Story_Collection) {
                throw new Exception('Invalid collection object returned');
            }
            
            return $collection;
            
        } catch (Exception $e) {
            $this->log_error('Failed to get stories collection', array(
                'error' => $e->getMessage(),
                'args' => $args
            ));
            
            return new WP_Error(
                'collection_error',
                __('Unable to retrieve stories collection.', 'wp-stories-plugin'),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Calculate appropriate cache time for stories collection
     *
     * @param array $stories_data Stories data
     * @return int Cache time in seconds
     */
    private function calculate_cache_time($stories_data) {
        $min_cache_time = 300; // 5 minutes minimum
        $max_cache_time = 3600; // 1 hour maximum
        
        if (empty($stories_data)) {
            return $min_cache_time;
        }
        
        // Find the shortest expiration time
        $shortest_expiration = null;
        
        foreach ($stories_data as $story_data) {
            if (isset($story_data['expiration']['time_remaining_seconds'])) {
                $remaining = $story_data['expiration']['time_remaining_seconds'];
                if ($shortest_expiration === null || $remaining < $shortest_expiration) {
                    $shortest_expiration = $remaining;
                }
            }
        }
        
        if ($shortest_expiration !== null && $shortest_expiration < $max_cache_time) {
            return max($min_cache_time, $shortest_expiration);
        }
        
        return $max_cache_time;
    }
    
    /**
     * Calculate appropriate cache time for individual story
     *
     * @param WP_Story $story Story object
     * @return int Cache time in seconds
     */
    private function calculate_story_cache_time($story) {
        try {
            $time_remaining = $story->get_time_remaining();
            
            if ($time_remaining && $time_remaining < 3600) {
                // If expires in less than 1 hour, cache for remaining time (minimum 5 minutes)
                return max(300, $time_remaining);
            } else {
                // Otherwise cache for 1 hour
                return 3600;
            }
        } catch (Exception $e) {
            $this->log_error('Failed to calculate cache time', array(
                'story_id' => $story->get_id(),
                'error' => $e->getMessage()
            ));
            return 300; // Default to 5 minutes
        }
    }
    
    /**
     * Prepare story data for API response with error handling
     *
     * @param WP_Story $story Story object
     * @return array|null Prepared story data or null on error
     */
    private function prepare_story_for_response($story) {
        try {
            // Validate story object
            if (!$story instanceof WP_Story) {
                throw new Exception('Invalid story object provided');
            }
            
            $media_items = array();
            
            // Get valid media items with error handling
            try {
                $valid_media = $story->get_valid_media_items();
                if (empty($valid_media)) {
                    throw new Exception('No valid media items found');
                }
                
                foreach ($valid_media as $media_item) {
                    try {
                        // Validate media item
                        if (!$media_item instanceof Story_Media_Item) {
                            continue;
                        }
                        
                        $media_data = $media_item->to_array();
                        
                        // Validate required media data
                        if (empty($media_data['url']) || empty($media_data['type'])) {
                            continue;
                        }
                        
                        // Add responsive image URLs for better performance
                        if ($media_item->get_type() === 'image') {
                            try {
                                $responsive_urls = $media_item->get_responsive_urls();
                                if (!empty($responsive_urls)) {
                                    $media_data['responsive_urls'] = $responsive_urls;
                                }
                            } catch (Exception $e) {
                                // Continue without responsive URLs
                                $this->log_error('Failed to get responsive URLs', array(
                                    'media_id' => $media_item->get_attachment_id(),
                                    'error' => $e->getMessage()
                                ));
                            }
                        }
                        
                        $media_items[] = $media_data;
                        
                    } catch (Exception $e) {
                        $this->log_error('Failed to process media item', array(
                            'story_id' => $story->get_id(),
                            'media_id' => $media_item ? $media_item->get_attachment_id() : 'unknown',
                            'error' => $e->getMessage()
                        ));
                        continue;
                    }
                }
                
            } catch (Exception $e) {
                $this->log_error('Failed to get valid media items', array(
                    'story_id' => $story->get_id(),
                    'error' => $e->getMessage()
                ));
                return null;
            }
            
            // Ensure we have at least one valid media item
            if (empty($media_items)) {
                throw new Exception('No processable media items found');
            }
            
            // Calculate expiration data with error handling
            $expiration_data = array(
                'is_expired' => false,
            );
            
            try {
                $expiration_hours = $story->get_expiration_hours();
                if ($expiration_hours !== null) {
                    $expiration_data['hours'] = $expiration_hours;
                }
                
                $expiration_data['is_expired'] = $story->is_expired();
                
                $time_remaining = $story->get_time_remaining();
                if ($time_remaining !== null) {
                    $expiration_data['time_remaining_seconds'] = $time_remaining;
                    
                    try {
                        $expiration_data['time_remaining_formatted'] = $story->get_formatted_time_remaining();
                    } catch (Exception $e) {
                        // Use fallback formatting
                        $expiration_data['time_remaining_formatted'] = $this->format_time_remaining($time_remaining);
                    }
                    
                    // Add expiration timestamp for frontend calculations
                    $expiration_data['expires_at'] = gmdate('c', time() + $time_remaining);
                }
                
            } catch (Exception $e) {
                $this->log_error('Failed to calculate expiration data', array(
                    'story_id' => $story->get_id(),
                    'error' => $e->getMessage()
                ));
                // Continue with basic expiration data
            }
            
            // Get thumbnail URL with fallback
            $thumbnail_url = null;
            try {
                $thumbnail_url = $story->get_thumbnail_url('medium');
                if (empty($thumbnail_url) && !empty($media_items)) {
                    // Fallback to first media item thumbnail
                    $thumbnail_url = $media_items[0]['thumbnail_url'] ?? null;
                }
            } catch (Exception $e) {
                $this->log_error('Failed to get thumbnail URL', array(
                    'story_id' => $story->get_id(),
                    'error' => $e->getMessage()
                ));
            }
            
            // Get creation timestamp with fallback
            $created_timestamp = null;
            try {
                $created_timestamp = $story->get_created_timestamp();
                if (!$created_timestamp) {
                    // Fallback to post date
                    $post = get_post($story->get_id());
                    if ($post) {
                        $created_timestamp = strtotime($post->post_date_gmt);
                    }
                }
            } catch (Exception $e) {
                $this->log_error('Failed to get creation timestamp', array(
                    'story_id' => $story->get_id(),
                    'error' => $e->getMessage()
                ));
                $created_timestamp = time(); // Fallback to current time
            }
            
            // Prepare final response data
            $response_data = array(
                'id' => $story->get_id(),
                'title' => sanitize_text_field($story->get_title()),
                'media' => $media_items,
                'media_count' => count($media_items),
                'expiration' => $expiration_data,
                'created_at' => gmdate('c', $created_timestamp ?: time()),
            );
            
            // Add thumbnail URL if available
            if ($thumbnail_url) {
                $response_data['thumbnail_url'] = esc_url_raw($thumbnail_url);
            }
            
            // Sanitize the response data
            return $this->sanitize_story_data($response_data);
            
        } catch (Exception $e) {
            $this->log_error('Critical error preparing story response', array(
                'story_id' => $story ? $story->get_id() : 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            return null;
        }
    }
    
    /**
     * Format time remaining as fallback
     *
     * @param int $seconds Seconds remaining
     * @return string Formatted time string
     */
    private function format_time_remaining($seconds) {
        if ($seconds <= 0) {
            return '0s';
        }
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($hours >= 24) {
            $days = floor($hours / 24);
            return $days . 'd';
        } elseif ($hours > 0) {
            return $hours . 'h';
        } elseif ($minutes > 0) {
            return $minutes . 'm';
        } else {
            return $seconds . 's';
        }
    }
    
    /**
     * Check permissions for getting stories collection
     *
     * @param WP_REST_Request $request Full details about the request
     * @return bool|WP_Error True if the request has read access, WP_Error object otherwise
     */
    public function get_stories_permissions_check($request) {
        // Stories are public content, no authentication required
        return true;
    }
    
    /**
     * Check permissions for getting individual story
     *
     * @param WP_REST_Request $request Full details about the request
     * @return bool|WP_Error True if the request has read access, WP_Error object otherwise
     */
    public function get_story_permissions_check($request) {
        // Stories are public content, no authentication required
        return true;
    }
    
    /**
     * Get collection parameters for stories endpoint
     *
     * @return array Collection parameters
     */
    public function get_stories_collection_params() {
        return array(
            'page' => array(
                'description' => __('Current page of the collection.', 'wp-stories-plugin'),
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'description' => __('Maximum number of items to be returned in result set.', 'wp-stories-plugin'),
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
                'sanitize_callback' => 'absint',
            ),
            'orderby' => array(
                'description' => __('Sort collection by story attribute.', 'wp-stories-plugin'),
                'type' => 'string',
                'default' => 'date',
                'enum' => array('date', 'title', 'expiration'),
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'order' => array(
                'description' => __('Order sort attribute ascending or descending.', 'wp-stories-plugin'),
                'type' => 'string',
                'default' => 'desc',
                'enum' => array('asc', 'desc'),
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'include_expired' => array(
                'description' => __('Whether to include expired stories in the result.', 'wp-stories-plugin'),
                'type' => 'boolean',
                'default' => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ),
        );
    }
    
    /**
     * Sanitize story data for API response
     *
     * @param array $data Raw story data
     * @return array Sanitized story data
     */
    private function sanitize_story_data($data) {
        $sanitized = array();
        
        // Sanitize basic fields
        $sanitized['id'] = absint($data['id']);
        $sanitized['title'] = sanitize_text_field($data['title']);
        $sanitized['media_count'] = absint($data['media_count']);
        $sanitized['created_at'] = sanitize_text_field($data['created_at']);
        
        // Sanitize thumbnail URL
        if (isset($data['thumbnail_url']) && is_string($data['thumbnail_url'])) {
            $sanitized['thumbnail_url'] = esc_url_raw($data['thumbnail_url']);
        }
        
        // Sanitize media items
        if (isset($data['media']) && is_array($data['media'])) {
            $sanitized['media'] = array();
            foreach ($data['media'] as $media_item) {
                $sanitized_media = array(
                    'id' => absint($media_item['id']),
                    'type' => sanitize_text_field($media_item['type']),
                    'url' => isset($media_item['url']) && is_string($media_item['url']) ? esc_url_raw($media_item['url']) : '',
                    'thumbnail_url' => isset($media_item['thumbnail_url']) && is_string($media_item['thumbnail_url']) ? esc_url_raw($media_item['thumbnail_url']) : '',
                );
                
                if (isset($media_item['responsive_urls']) && is_array($media_item['responsive_urls'])) {
                    $sanitized_media['responsive_urls'] = array();
                    foreach ($media_item['responsive_urls'] as $size => $url) {
                        if (is_string($url)) {
                            $sanitized_media['responsive_urls'][sanitize_text_field($size)] = esc_url_raw($url);
                        }
                    }
                }
                
                $sanitized['media'][] = $sanitized_media;
            }
        }
        
        // Sanitize expiration data
        if (isset($data['expiration']) && is_array($data['expiration'])) {
            $sanitized['expiration'] = array(
                'is_expired' => (bool) $data['expiration']['is_expired'],
            );
            
            if (isset($data['expiration']['hours'])) {
                $sanitized['expiration']['hours'] = absint($data['expiration']['hours']);
            }
            
            if (isset($data['expiration']['time_remaining_seconds'])) {
                $sanitized['expiration']['time_remaining_seconds'] = absint($data['expiration']['time_remaining_seconds']);
            }
            
            if (isset($data['expiration']['time_remaining_formatted'])) {
                $sanitized['expiration']['time_remaining_formatted'] = sanitize_text_field($data['expiration']['time_remaining_formatted']);
            }
            
            if (isset($data['expiration']['expires_at'])) {
                $sanitized['expiration']['expires_at'] = sanitize_text_field($data['expiration']['expires_at']);
            }
        }
        
        return $sanitized;
    }
    


    
    /**
     * Permission callback for tracking story views
     *
     * @param WP_REST_Request $request Full details about the request
     * @return bool|WP_Error True if user can access, false or WP_Error otherwise
     */
    public function track_view_permissions_check($request) {
        // Rate limiting check (stricter for POST requests)
        if (!$this->check_api_rate_limit('track_view', 10)) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('View tracking rate limit exceeded.', 'wp-stories-plugin'),
                array('status' => 429)
            );
        }
        
        // Verify nonce for CSRF protection
        $nonce = $request->get_param('nonce');
        if (!wp_verify_nonce($nonce, 'wp_stories_view_nonce')) {
            return new WP_Error(
                'invalid_nonce',
                __('Invalid security token.', 'wp-stories-plugin'),
                array('status' => 403)
            );
        }
        
        return true;
    }
    
    /**
     * Track story view with security measures
     *
     * @param WP_REST_Request $request Full details about the request
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure
     */
    public function track_story_view($request) {
        try {
            $story_id = (int) $request->get_param('id');
            
            // Validate story exists and is accessible
            $story_check = $this->get_story($request);
            if (is_wp_error($story_check)) {
                return $story_check;
            }
            
            // Track the view (implement your tracking logic here)
            $view_data = array(
                'story_id' => $story_id,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
                'referer' => wp_get_referer() ? esc_url_raw(wp_get_referer()) : '',
                'timestamp' => current_time('mysql'),
                'user_id' => get_current_user_id()
            );
            
            // Store view data (you can implement database storage here)
            do_action('wp_stories_track_view', $view_data);
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => __('View tracked successfully', 'wp-stories-plugin')
            ), 200);
            
        } catch (Exception $e) {
            $this->log_error('Failed to track story view', array(
                'story_id' => isset($story_id) ? $story_id : 'unknown',
                'error' => $e->getMessage()
            ));
            
            return new WP_Error(
                'tracking_error',
                __('Unable to track view.', 'wp-stories-plugin'),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Check API rate limiting
     *
     * @param string $endpoint Endpoint name
     * @param int $limit Rate limit (requests per minute)
     * @return bool True if within limit, false otherwise
     */
    private function check_api_rate_limit($endpoint, $limit = 60) {
        $client_ip = $this->get_client_ip();
        $rate_limit_key = 'wp_stories_api_rate_' . $endpoint . '_' . md5($client_ip);
        $requests_count = get_transient($rate_limit_key);
        
        if ($requests_count === false) {
            set_transient($rate_limit_key, 1, MINUTE_IN_SECONDS);
            return true;
        }
        
        if ($requests_count >= $limit) {
            // Log rate limit violation
            $this->log_error('API rate limit exceeded', array(
                'endpoint' => $endpoint,
                'ip' => $client_ip,
                'requests' => $requests_count,
                'limit' => $limit
            ));
            return false;
        }
        
        set_transient($rate_limit_key, $requests_count + 1, MINUTE_IN_SECONDS);
        return true;
    }
    
    /**
     * Log API access for monitoring
     *
     * @param string $endpoint Endpoint accessed
     * @param array $context Additional context
     */
    private function log_api_access($endpoint, $context = array()) {
        // Only log if monitoring is enabled
        if (!defined('WP_STORIES_LOG_API_ACCESS') || !WP_STORIES_LOG_API_ACCESS) {
            return;
        }
        
        $log_data = array(
            'timestamp' => current_time('c'),
            'endpoint' => $endpoint,
            'context' => $context
        );
        
        // Store in database or file for analysis
        do_action('wp_stories_api_access_logged', $log_data);
    }
    

}