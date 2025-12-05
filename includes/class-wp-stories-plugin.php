<?php
/**
 * Main plugin class
 *
 * @package WP_Stories_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main WP Stories Plugin class
 */
class WP_Stories_Plugin {
    
    /**
     * Plugin instance
     *
     * @var WP_Stories_Plugin
     */
    private static $instance = null;
    
    /**
     * Plugin components
     *
     * @var array
     */
    private $components = array();
    
    /**
     * Get plugin instance (Singleton pattern)
     *
     * @return WP_Stories_Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Initialize the plugin
     */
    private function __construct() {
        $this->define_constants();
        $this->include_dependencies();
        $this->init_hooks();
        $this->init_components();
    }
    
    /**
     * Define additional plugin constants
     */
    private function define_constants() {
        // Additional constants can be defined here if needed
        if (!defined('GHOST_STORIES_INCLUDES_PATH')) {
            define('GHOST_STORIES_INCLUDES_PATH', GHOST_STORIES_PATH . 'includes/');
        }
        
        if (!defined('GHOST_STORIES_ASSETS_URL')) {
            define('GHOST_STORIES_ASSETS_URL', GHOST_STORIES_URL . 'assets/');
        }
        
        if (!defined('GHOST_STORIES_BLOCKS_PATH')) {
            define('GHOST_STORIES_BLOCKS_PATH', GHOST_STORIES_PATH . 'blocks/');
        }
    }
    
    /**
     * Include required files
     */
    private function include_dependencies() {
        // Core component files will be included here as they are created
        require_once GHOST_STORIES_INCLUDES_PATH . 'class-story-post-type.php';
        
        // Data model classes
        require_once GHOST_STORIES_INCLUDES_PATH . 'class-story-media-item.php';
        require_once GHOST_STORIES_INCLUDES_PATH . 'class-wp-story.php';
        require_once GHOST_STORIES_INCLUDES_PATH . 'class-story-collection.php';
        
        require_once GHOST_STORIES_INCLUDES_PATH . 'class-story-admin.php';
        require_once GHOST_STORIES_INCLUDES_PATH . 'class-story-api.php';
        require_once GHOST_STORIES_INCLUDES_PATH . 'class-story-frontend.php';
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Initialize plugin after WordPress is fully loaded
        add_action('init', array($this, 'init'));
        
        // Load plugin text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // NOTE: Frontend scripts are now enqueued by Story_Frontend class
        // Removed: add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Initialize Gutenberg blocks
        add_action('init', array($this, 'register_blocks'));
        
        // Cache management hooks
        add_action('save_post_wp_story', array($this, 'clear_story_caches'), 10, 1);
        add_action('delete_post', array($this, 'clear_story_caches_on_delete'), 10, 1);
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Components will be initialized here as they are created
        $this->components['post_type'] = new Story_Post_Type();
        $this->components['admin'] = new Story_Admin();
        $this->components['api'] = new Story_API();
        $this->components['frontend'] = new Story_Frontend();
    }
    
    /**
     * Initialize plugin functionality
     */
    public function init() {
        // Plugin initialization logic
        do_action('wp_stories_plugin_init');
    }
    
    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'ghost-stories',
            false,
            dirname(GHOST_STORIES_BASENAME) . '/languages'
        );
    }
    
    /**
     * Enqueue frontend assets (conditional loading for performance)
     * NOTE: Frontend assets are now handled by Story_Frontend class
     * This method is kept for backwards compatibility but does nothing
     */
    public function enqueue_frontend_assets() {
        // Frontend assets are now enqueued by Story_Frontend class
        // This prevents duplicate enqueuing and conflicts
        return;
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Load admin assets only on relevant admin pages
        if ($this->should_load_admin_assets($hook)) {
            wp_enqueue_style(
                'wp-stories-admin',
                GHOST_STORIES_ASSETS_URL . 'css/admin.css',
                array(),
                GHOST_STORIES_VERSION
            );
            
            wp_enqueue_script(
                'wp-stories-admin',
                GHOST_STORIES_ASSETS_URL . 'js/admin.js',
                array('jquery', 'wp-util'),
                GHOST_STORIES_VERSION,
                true
            );
            
            // Localize admin script
            wp_localize_script('wp-stories-admin', 'wpStoriesAdmin', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wp_stories_admin_nonce'),
                'strings' => array(
                    'confirmDelete' => __('Are you sure you want to delete this story?', 'wp-stories-plugin'),
                    'confirmRemove' => __('Are you sure you want to remove this media item?', 'wp-stories-plugin'),
                    'uploadError' => __('Error uploading file. Please try again.', 'wp-stories-plugin'),
                    'saveSuccess' => __('Story saved successfully.', 'wp-stories-plugin'),
                    'selectMedia' => __('Select Media for Story', 'wp-stories-plugin'),
                    'addToStory' => __('Add to Story', 'wp-stories-plugin'),
                    'editMedia' => __('Edit Media', 'wp-stories-plugin'),
                    'updateMedia' => __('Update Media', 'wp-stories-plugin'),
                    'mediaAdded' => __('Media added successfully', 'wp-stories-plugin'),
                    'mediaRemoved' => __('Media removed successfully', 'wp-stories-plugin'),
                    'mediaUpdated' => __('Media updated successfully', 'wp-stories-plugin'),
                    'ajaxError' => __('An error occurred. Please try again.', 'wp-stories-plugin'),
                    'dragPlaceholder' => __('Drop here', 'wp-stories-plugin'),
                    'selectItems' => __('Please select items to perform this action.', 'wp-stories-plugin'),
                    'confirmExtend' => __('Extend expiration by 24 hours for selected stories?', 'wp-stories-plugin'),
                    'confirmRemoveExpiration' => __('Remove expiration for selected stories?', 'wp-stories-plugin')
                )
            ));
        }
    }
    
    /**
     * Register Gutenberg blocks
     */
    public function register_blocks() {
        // Enqueue block assets first
        $this->enqueue_block_assets();
        
        // Register the stories block with render callback
        register_block_type('wp-stories/stories-block', array(
            'editor_script' => 'wp-stories-block-editor',
            'editor_style' => 'wp-stories-block-editor-style',
            'style' => 'wp-stories-block-style',
            'render_callback' => array($this, 'render_stories_block'),
            'attributes' => array(
                'selectedStories' => array(
                    'type' => 'array',
                    'default' => array()
                ),
                'alignment' => array(
                    'type' => 'string',
                    'default' => 'left'
                )
            )
        ));
    }
    
    /**
     * Enqueue block assets
     */
    private function enqueue_block_assets() {
        $block_url = GHOST_STORIES_URL . 'blocks/stories-block/';
        
        // Register block editor script (vanilla JS, no build required)
        wp_register_script(
            'wp-stories-block-editor',
            $block_url . 'index.js',
            array(
                'wp-blocks',
                'wp-element',
                'wp-block-editor',
                'wp-components',
                'wp-i18n',
                'wp-api-fetch',
                'wp-data'
            ),
            GHOST_STORIES_VERSION,
            true
        );
        
        // Register block editor styles
        wp_register_style(
            'wp-stories-block-editor-style',
            $block_url . 'editor.css',
            array('wp-edit-blocks'),
            GHOST_STORIES_VERSION
        );
        
        // Register block frontend styles
        wp_register_style(
            'wp-stories-block-style',
            $block_url . 'style.css',
            array(),
            GHOST_STORIES_VERSION
        );
    }
    
    /**
     * Render stories block on frontend
     */
    public function render_stories_block($attributes, $content) {
        // EMERGENCY FIX: Wrap EVERYTHING in try-catch to prevent ANY crash
        try {
            // Return empty if no attributes
            if (!is_array($attributes)) {
                return '';
            }
            
            $selected_stories = isset($attributes['selectedStories']) ? $attributes['selectedStories'] : array();
            
            // Return empty if no stories selected
            if (empty($selected_stories) || !is_array($selected_stories)) {
                return '';
            }
            
            // Return empty if frontend not initialized
            if (!isset($this->components['frontend'])) {
                return '';
            }
            
            $alignment = isset($attributes['alignment']) ? $attributes['alignment'] : 'left';
            
            // Call render with full error suppression
            $output = @$this->components['frontend']->render_stories($selected_stories, $alignment);
            
            // Return empty string if output is null or false
            return $output ? $output : '';
            
        } catch (Throwable $e) {
            // Catch EVERYTHING (Exception, Error, all throwables)
            error_log('WP Stories ERROR: ' . $e->getMessage());
            return ''; // Return empty, never crash
        }
    }
    
    /**
     * Check if frontend assets should be loaded
     *
     * @return bool
     */
    private function should_load_frontend_assets() {
        // Check if current page/post has stories block or is a story page
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Check if post content contains stories block
        if (has_block('wp-stories/stories-block', $post)) {
            return true;
        }
        
        // Check if it's a single story page
        if (is_singular('wp_story')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if admin assets should be loaded
     *
     * @param string $hook Current admin page hook
     * @return bool
     */
    private function should_load_admin_assets($hook) {
        // Load on story-related admin pages
        $story_pages = array(
            'post.php',
            'post-new.php',
            'edit.php'
        );
        
        if (in_array($hook, $story_pages)) {
            global $post_type;
            if ($post_type === 'wp_story') {
                return true;
            }
        }
        
        // Load on stories admin page (will be created in later tasks)
        if (strpos($hook, 'wp-stories') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get plugin component
     *
     * @param string $component Component name
     * @return mixed|null
     */
    public function get_component($component) {
        return isset($this->components[$component]) ? $this->components[$component] : null;
    }
    
    /**
     * Get plugin version
     *
     * @return string
     */
    public function get_version() {
        return GHOST_STORIES_VERSION;
    }
    
    /**
     * Get plugin path
     *
     * @return string
     */
    public function get_plugin_path() {
        return GHOST_STORIES_PATH;
    }
    
    /**
     * Get plugin URL
     *
     * @return string
     */
    public function get_plugin_url() {
        return GHOST_STORIES_URL;
    }
    
    /**
     * Clear story-related caches when a story is saved
     *
     * @param int $post_id Post ID
     */
    public function clear_story_caches($post_id) {
        // Clear expiration caches for this story
        if (isset($this->components['post_type'])) {
            $this->components['post_type']->clear_expiration_cache($post_id);
        }
        
        // Clear frontend caches
        Story_Frontend::clear_frontend_caches();
    }
    
    /**
     * Clear story caches when a story is deleted
     *
     * @param int $post_id Post ID
     */
    public function clear_story_caches_on_delete($post_id) {
        $post = get_post($post_id);
        
        if ($post && $post->post_type === Story_Post_Type::POST_TYPE) {
            $this->clear_story_caches($post_id);
        }
    }
}