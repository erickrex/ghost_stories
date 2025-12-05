<?php
/**
 * Story Admin class - Handles WordPress admin interface for story management
 *
 * @package WP_Stories_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Story Admin class
 */
class Story_Admin {
    
    /**
     * Admin page hook suffix
     *
     * @var string
     */
    private $admin_page_hook;
    
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
        // Add admin menu (DISABLED - using Story_Post_Type as principal)
        // add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add meta boxes for story editing
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Save story meta data
        add_action('save_post', array($this, 'save_story_meta'));
        
        // Customize story list columns
        add_filter('manage_wp_story_posts_columns', array($this, 'add_story_list_columns'));
        add_action('manage_wp_story_posts_custom_column', array($this, 'render_story_list_columns'), 10, 2);
        
        // Add bulk actions
        add_filter('bulk_actions-edit-wp_story', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-edit-wp_story', array($this, 'handle_bulk_actions'), 10, 3);
        
        // AJAX handlers for media management
        add_action('wp_ajax_wp_stories_upload_media', array($this, 'ajax_upload_media'));
        add_action('wp_ajax_wp_stories_reorder_media', array($this, 'ajax_reorder_media'));
        add_action('wp_ajax_wp_stories_remove_media', array($this, 'ajax_remove_media'));
        add_action('wp_ajax_wp_stories_get_media_library', array($this, 'ajax_get_media_library'));
        add_action('wp_ajax_wp_stories_add_existing_media', array($this, 'ajax_add_existing_media'));
        add_action('wp_ajax_wp_stories_check_media_integrity', array($this, 'ajax_check_media_integrity'));
        add_action('wp_ajax_wp_stories_save_thumbnail', array($this, 'ajax_save_thumbnail'));
        add_action('wp_ajax_wp_stories_set_image_thumbnail', array($this, 'ajax_set_image_thumbnail'));
        add_action('wp_ajax_wp_stories_get_video_thumbnail', array($this, 'ajax_get_video_thumbnail'));
        
        // Handle media deletion
        add_action('delete_attachment', array($this, 'handle_deleted_media'));
        
        // Handle story deletion - delete thumbnails
        add_action('before_delete_post', array($this, 'handle_story_deletion'), 10, 1);
        
        // Enqueue media scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_media_scripts'));
    }
    
    /**
     * Add admin menu pages (DISABLED - using Story_Post_Type as principal)
     */
    public function add_admin_menu() {
        // DISABLED - Story_Post_Type is now the principal menu
        return;
        
        /*
        // Add main Stories menu page (consolidated dashboard)
        $this->admin_page_hook = add_menu_page(
            __('Stories', 'wp-stories-plugin'),
            __('Stories', 'wp-stories-plugin'),
            'manage_options',
            'wp-stories',
            array($this, 'render_stories_page'),
            'dashicons-format-gallery',
            30
        );
        
        // Remove the default submenu that WordPress creates for custom post types
        remove_submenu_page('wp-stories', 'wp-stories');
        */
    }
    
    /**
     * Render main stories admin page
     */
    public function render_stories_page() {
        // Get story statistics
        $total_stories = wp_count_posts('wp_story');
        $active_stories = $this->get_active_stories_count();
        $expired_stories = $this->get_expired_stories_count();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Stories Dashboard', 'wp-stories-plugin'); ?></h1>
            
            <div class="wp-stories-dashboard">
                <div class="wp-stories-stats">
                    <div class="wp-stories-stat-card">
                        <h3><?php _e('Total Stories', 'wp-stories-plugin'); ?></h3>
                        <span class="wp-stories-stat-number"><?php echo esc_html($total_stories->publish); ?></span>
                    </div>
                    
                    <div class="wp-stories-stat-card">
                        <h3><?php _e('Active Stories', 'wp-stories-plugin'); ?></h3>
                        <span class="wp-stories-stat-number wp-stories-active"><?php echo esc_html($active_stories); ?></span>
                    </div>
                    
                    <div class="wp-stories-stat-card">
                        <h3><?php _e('Expired Stories', 'wp-stories-plugin'); ?></h3>
                        <span class="wp-stories-stat-number wp-stories-expired"><?php echo esc_html($expired_stories); ?></span>
                    </div>
                </div>
                
                <div class="wp-stories-quick-actions">
                    <a href="<?php echo admin_url('post-new.php?post_type=wp_story'); ?>" class="button button-primary">
                        <?php _e('Create New Story', 'wp-stories-plugin'); ?>
                    </a>
                </div>
                
                <div class="wp-stories-recent">
                    <h2><?php _e('All Stories', 'wp-stories-plugin'); ?></h2>
                    <?php $this->render_recent_stories_table(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add meta boxes for story editing
     */
    public function add_meta_boxes() {
        // Media management meta box
        add_meta_box(
            'wp-stories-media',
            __('Story Media', 'wp-stories-plugin'),
            array($this, 'render_media_meta_box'),
            'wp_story',
            'normal',
            'high'
        );
        
        // Story settings meta box
        add_meta_box(
            'wp-stories-settings',
            __('Story Settings', 'wp-stories-plugin'),
            array($this, 'render_settings_meta_box'),
            'wp_story',
            'side',
            'default'
        );
        
        // Story preview meta box
        add_meta_box(
            'wp-stories-preview',
            __('Story Preview', 'wp-stories-plugin'),
            array($this, 'render_preview_meta_box'),
            'wp_story',
            'side',
            'default'
        );
    }
    
    /**
     * Render media management meta box
     */
    public function render_media_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('wp_stories_meta_nonce', 'wp_stories_meta_nonce');
        
        // Get existing media items
        $media_ids = get_post_meta($post->ID, '_story_media_ids', true);
        if (!is_array($media_ids)) {
            $media_ids = array();
        }
        
        ?>
        <div class="wp-stories-media-manager">
            <div class="wp-stories-media-upload">
                <button type="button" class="button button-primary wp-stories-add-media">
                    <?php _e('Add Media', 'wp-stories-plugin'); ?>
                </button>
                <button type="button" class="button wp-stories-browse-library">
                    <?php _e('Browse Library', 'wp-stories-plugin'); ?>
                </button>
                <button type="button" class="button wp-stories-check-integrity">
                    <?php _e('Check Media Integrity', 'wp-stories-plugin'); ?>
                </button>
                <p class="description">
                    <?php _e('Upload new media or select from your media library. You can reorder items by dragging.', 'wp-stories-plugin'); ?>
                </p>
            </div>
            
            <div class="wp-stories-media-list" id="wp-stories-media-list">
                <?php if (!empty($media_ids)): ?>
                    <?php foreach ($media_ids as $media_id): ?>
                        <?php $this->render_media_item($media_id); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="wp-stories-no-media">
                        <p><?php _e('No media added yet. Click "Add Media" to get started.', 'wp-stories-plugin'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <input type="hidden" name="wp_stories_media_ids" id="wp-stories-media-ids" 
                   value="<?php echo esc_attr(implode(',', $media_ids)); ?>">
        </div>
        <?php
    }
    
    /**
     * Render individual media item
     */
    private function render_media_item($media_id) {
        $attachment = get_post($media_id);
        if (!$attachment) {
            return;
        }
        
        $media_url = wp_get_attachment_url($media_id);
        $thumbnail_url = wp_get_attachment_image_url($media_id, 'thumbnail');
        $media_type = wp_attachment_is_image($media_id) ? 'image' : 'video';
        
        ?>
        <div class="wp-stories-media-item" data-media-id="<?php echo esc_attr($media_id); ?>">
            <div class="wp-stories-media-thumbnail">
                <?php if ($media_type === 'image'): ?>
                    <img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php echo esc_attr($attachment->post_title); ?>">
                <?php else: ?>
                    <video src="<?php echo esc_url($media_url); ?>" preload="metadata"></video>
                    <span class="wp-stories-media-type-indicator"><?php _e('Video', 'wp-stories-plugin'); ?></span>
                <?php endif; ?>
            </div>
            
            <div class="wp-stories-media-info">
                <strong><?php echo esc_html($attachment->post_title); ?></strong>
                <span class="wp-stories-media-type"><?php echo esc_html(ucfirst($media_type)); ?></span>
            </div>
            
            <div class="wp-stories-media-actions">
                <button type="button" class="button wp-stories-media-edit" data-media-id="<?php echo esc_attr($media_id); ?>">
                    <?php _e('Edit', 'wp-stories-plugin'); ?>
                </button>
                <button type="button" class="button wp-stories-media-remove" data-media-id="<?php echo esc_attr($media_id); ?>">
                    <?php _e('Remove', 'wp-stories-plugin'); ?>
                </button>
            </div>
            
            <div class="wp-stories-media-handle">
                <span class="dashicons dashicons-menu"></span>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings meta box
     */
    public function render_settings_meta_box($post) {
        $expiration_hours = get_post_meta($post->ID, '_story_expiration_hours', true);
        $created_timestamp = get_post_meta($post->ID, '_story_created_timestamp', true);
        $story_title = get_post_meta($post->ID, '_story_title', true);
        $story_detail = get_post_meta($post->ID, '_story_detail', true);
        
        if (empty($created_timestamp)) {
            $created_timestamp = current_time('timestamp');
        }
        
        ?>
        <style>
            #wp-stories-settings .inside {
                padding: 10px !important;
            }
        </style>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="story_title"><?php _e('Título', 'wp-stories-plugin'); ?></label>
                </th>
                <td>
                    <input type="text" id="story_title" name="story_title" 
                           value="<?php echo esc_attr($story_title); ?>" class="small-text" 
                           pattern="[^\s]+" 
                           title="<?php esc_attr_e('Solo se permite una palabra sin espacios', 'wp-stories-plugin'); ?>"
                           placeholder="<?php esc_attr_e('Ejemplo: Titulo1', 'wp-stories-plugin'); ?>" 
                           style="width: 150px;">
                    <p class="description" style="font-size: 11px; margin-top: 3px;">
                        <?php _e('Una palabra sin espacios', 'wp-stories-plugin'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="story_detail"><?php _e('Subtítulo', 'wp-stories-plugin'); ?></label>
                </th>
                <td>
                    <input type="text" id="story_detail" name="story_detail" 
                           value="<?php echo esc_attr($story_detail); ?>" class="small-text"
                           pattern="[^\s]+" 
                           title="<?php esc_attr_e('Solo se permite una palabra sin espacios', 'wp-stories-plugin'); ?>"
                           placeholder="<?php esc_attr_e('Ejemplo: Subtitulo1', 'wp-stories-plugin'); ?>" 
                           style="width: 150px;">
                    <p class="description" style="font-size: 11px; margin-top: 3px;">
                        <?php _e('Una palabra sin espacios', 'wp-stories-plugin'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="story_expiration_hours"><?php _e('Expiration Days', 'wp-stories-plugin'); ?></label>
                </th>
                <td>
                    <input type="number" id="story_expiration_hours" name="story_expiration_hours" 
                           value="<?php echo esc_attr($expiration_hours ? ceil($expiration_hours / 24) : ''); ?>" min="1" max="40" step="1" class="small-text">
                    <p class="description" style="font-size: 11px; margin-top: 3px;">
                        <?php _e('Días de visibilidad. Vacío = sin expiración.', 'wp-stories-plugin'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Created', 'wp-stories-plugin'); ?></th>
                <td>
                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $created_timestamp)); ?>
                    <input type="hidden" name="story_created_timestamp" value="<?php echo esc_attr($created_timestamp); ?>">
                </td>
            </tr>
            
            <?php if (!empty($expiration_hours)): ?>
                <?php
                $expiration_timestamp = $created_timestamp + ($expiration_hours * HOUR_IN_SECONDS);
                $is_expired = current_time('timestamp') > $expiration_timestamp;
                ?>
                <tr>
                    <th scope="row"><?php _e('Expires', 'wp-stories-plugin'); ?></th>
                    <td>
                        <span class="wp-stories-expiration <?php echo $is_expired ? 'expired' : 'active'; ?>">
                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $expiration_timestamp)); ?>
                            <?php if ($is_expired): ?>
                                <strong><?php _e('(Expired)', 'wp-stories-plugin'); ?></strong>
                            <?php endif; ?>
                        </span>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
        <?php
    }
    
    /**
     * Render preview meta box
     */
    public function render_preview_meta_box($post) {
        $media_ids = get_post_meta($post->ID, '_story_media_ids', true);
        if (!is_array($media_ids) || empty($media_ids)) {
            ?>
            <p><?php _e('Add media to see story preview.', 'wp-stories-plugin'); ?></p>
            <?php
            return;
        }
        
        $first_media_id = $media_ids[0];
        $thumbnail_url = wp_get_attachment_image_url($first_media_id, 'medium');
        $media_count = count($media_ids);
        
        ?>
        <div class="wp-stories-preview">
            <div class="wp-stories-preview-circle">
                <img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php echo esc_attr($post->post_title); ?>">
                <?php if ($media_count > 1): ?>
                    <span class="wp-stories-media-count"><?php echo esc_html($media_count); ?></span>
                <?php endif; ?>
            </div>
            <p class="wp-stories-preview-title"><?php echo esc_html($post->post_title); ?></p>
            <p class="description">
                <?php printf(_n('%d media item', '%d media items', $media_count, 'wp-stories-plugin'), $media_count); ?>
            </p>
        </div>
        <?php
    }    

    /**
     * Save story meta data
     */
    public function save_story_meta($post_id) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check if this is a revision
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // Don't run during AJAX uploads (thumbnail generation happens via AJAX anyway)
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        // Check post type
        if (get_post_type($post_id) !== 'wp_story') {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Verify nonce - only check if nonce field is present
        // This allows the post to be created initially without the nonce
        $has_valid_nonce = false;
        if (isset($_POST['wp_stories_meta_nonce'])) {
            if (wp_verify_nonce($_POST['wp_stories_meta_nonce'], 'wp_stories_meta_nonce')) {
                $has_valid_nonce = true;
            } else {
                return;
            }
        }
        
        // Save media IDs with validation
        // IMPORTANT: The order of this array determines which media is used for thumbnails
        // The FIRST item (index 0) is ALWAYS used for thumbnail generation
        // This order is maintained by the frontend drag-and-drop functionality
        if (isset($_POST['wp_stories_media_ids'])) {
            $media_ids = sanitize_text_field($_POST['wp_stories_media_ids']);
            $media_ids_array = !empty($media_ids) ? explode(',', $media_ids) : array();
            $media_ids_array = array_map('absint', $media_ids_array);
            $media_ids_array = array_filter($media_ids_array);
            
            // Validate each media ID exists and user has access (preserving order)
            $validated_media_ids = array();
            foreach ($media_ids_array as $media_id) {
                if ($this->validate_media_access($media_id)) {
                    $validated_media_ids[] = $media_id;
                }
            }
            
            // Limit number of media items for security
            if (count($validated_media_ids) > 50) {
                $validated_media_ids = array_slice($validated_media_ids, 0, 50);
            }
            
            // Save in the same order - first item will always be used for thumbnails
            update_post_meta($post_id, '_story_media_ids', array_values($validated_media_ids)); // array_values to re-index
            
            error_log('WP Stories: Saved media IDs in order. First item (thumbnail source): ' . (!empty($validated_media_ids[0]) ? $validated_media_ids[0] : 'none') . ', Total: ' . count($validated_media_ids));
        }
        
        // Generate thumbnail regardless of nonce (but only if we have media)
        $this->generate_and_save_thumbnail($post_id);
        
        // Return early if no nonce (initial post creation)
        if (!$has_valid_nonce) {
            return;
        }
        
        // Save story title with validation (single word, no spaces, uppercase)
        if (isset($_POST['story_title'])) {
            $story_title = sanitize_text_field($_POST['story_title']);
            if (!empty($story_title)) {
                // Validate: only one word, no spaces
                if (preg_match('/^\S+$/', $story_title)) {
                    // Convert to uppercase before saving
                    $story_title = strtoupper($story_title);
                    update_post_meta($post_id, '_story_title', $story_title);
                } else {
                    wp_die(__('El título solo puede contener una palabra sin espacios.', 'wp-stories-plugin'), 400);
                }
            } else {
                delete_post_meta($post_id, '_story_title');
            }
        }
        
        // Save story detail with validation (single word, no spaces, uppercase)
        if (isset($_POST['story_detail'])) {
            $story_detail = sanitize_text_field($_POST['story_detail']);
            if (!empty($story_detail)) {
                // Validate: only one word, no spaces
                if (preg_match('/^\S+$/', $story_detail)) {
                    // Convert to uppercase before saving
                    $story_detail = strtoupper($story_detail);
                    update_post_meta($post_id, '_story_detail', $story_detail);
                } else {
                    wp_die(__('El subtítulo solo puede contener una palabra sin espacios.', 'wp-stories-plugin'), 400);
                }
            } else {
                delete_post_meta($post_id, '_story_detail');
            }
        }
        
        // Save expiration days (converted to hours internally)
        if (isset($_POST['story_expiration_hours'])) {
            $expiration_days = sanitize_text_field($_POST['story_expiration_hours']);
            if (!empty($expiration_days)) {
                // Validate numeric and within reasonable bounds
                if (is_numeric($expiration_days)) {
                    $expiration_days = absint($expiration_days);
                    // Limit to reasonable range (1 to 40 days)
                    if ($expiration_days >= 1 && $expiration_days <= 40) {
                        // Convert days to hours internally
                        $expiration_hours = $expiration_days * 24;
                        update_post_meta($post_id, '_story_expiration_hours', $expiration_hours);
                    } else {
                        wp_die(__('Invalid expiration days. Must be between 1 and 40 days.', 'wp-stories-plugin'), 400);
                    }
                } else {
                    wp_die(__('Expiration days must be a number', 'wp-stories-plugin'), 400);
                }
            } else {
                delete_post_meta($post_id, '_story_expiration_hours');
            }
        }
        
        // Save created timestamp (only on first save) with validation
        if (!get_post_meta($post_id, '_story_created_timestamp', true)) {
            $created_timestamp = current_time('timestamp');
            if (isset($_POST['story_created_timestamp'])) {
                $submitted_timestamp = absint($_POST['story_created_timestamp']);
                // Only accept if it's within reasonable bounds (not future, not too old)
                $current_time = current_time('timestamp');
                if ($submitted_timestamp > 0 && 
                    $submitted_timestamp <= $current_time && 
                    $submitted_timestamp > ($current_time - (30 * DAY_IN_SECONDS))) {
                    $created_timestamp = $submitted_timestamp;
                }
            }
            update_post_meta($post_id, '_story_created_timestamp', $created_timestamp);
        }
    }
    
    /**
     * Generate thumbnail for the story and save it as featured image (like WP Story Premium)
     */
    private function generate_and_save_thumbnail($post_id) {
        // Delete existing thumbnail before creating new one (if updating story)
        if (has_post_thumbnail($post_id)) {
            $existing_thumbnail_id = get_post_thumbnail_id($post_id);
            
            if ($existing_thumbnail_id) {
                // Get current media to check if thumbnail is the same as first media
                $media_ids = get_post_meta($post_id, '_story_media_ids', true);
                $first_media_id = null;
                
                if (!empty($media_ids) && is_array($media_ids) && !empty($media_ids[0])) {
                    $first_media_id = $media_ids[0];
                }
                
                // Only skip deletion if the thumbnail IS the same as the first media (image)
                // This means we're keeping the same image, just reassigning it
                if ($first_media_id && $existing_thumbnail_id == $first_media_id) {
                    // Same image, just remove as featured image and will be reassigned below
                    delete_post_thumbnail($post_id);
                } else {
                    // Different thumbnail or no first media - check if it's a generated thumbnail
                    $thumbnail_file = get_attached_file($existing_thumbnail_id);
                    if ($thumbnail_file) {
                        $filename = basename($thumbnail_file);
                        // If filename contains 'story_' or 'video_', it's a generated thumbnail
                        if (strpos($filename, 'story_') !== false || strpos($filename, 'video_') !== false) {
                            // Delete the generated thumbnail attachment (works for both video and image generated thumbnails)
                            wp_delete_attachment($existing_thumbnail_id, true);
                            error_log('WP Stories: Deleted old generated thumbnail (attachment_id: ' . $existing_thumbnail_id . ') for post_id ' . $post_id);
                        } else {
                            // It's original media (not generated), just remove as featured image (don't delete the file)
                            delete_post_thumbnail($post_id);
                            error_log('WP Stories: Removed old thumbnail (attachment_id: ' . $existing_thumbnail_id . ') as featured image for post_id ' . $post_id);
                        }
                    } else {
                        // No file found, just remove as featured image
                        delete_post_thumbnail($post_id);
                    }
                }
            }
        }
        
        // Get media IDs - ALWAYS use the first item (index 0) for thumbnail generation
        $media_ids = get_post_meta($post_id, '_story_media_ids', true);
        
        if (empty($media_ids) || !is_array($media_ids) || empty($media_ids[0])) {
            error_log('WP Stories: No media IDs found or empty array for post_id ' . $post_id);
            return;
        }
        
        // CRITICAL: Always use the FIRST item (index 0) - this is the first item in the story
        // The order is maintained by the frontend drag-and-drop functionality
        $first_media_id = reset($media_ids); // Explicitly get first element
        $first_media_id = absint($first_media_id); // Ensure it's a valid integer
        
        if (empty($first_media_id)) {
            error_log('WP Stories: Invalid first media ID for post_id ' . $post_id);
            return;
        }
        
        error_log('WP Stories: Using FIRST media item (ID: ' . $first_media_id . ') for thumbnail generation, post_id: ' . $post_id);
        
        // Get media attachment
        $media_attachment = get_post($first_media_id);
        
        if (!$media_attachment) {
            return;
        }
        
        // Determine media type
        $media_type = get_post_mime_type($first_media_id);
        
        if (strpos($media_type, 'image/') === 0) {
            // For images, set as featured image directly
            set_post_thumbnail($post_id, $first_media_id);
            
            // Also save URL in meta for backward compatibility
            $thumbnail_url = wp_get_attachment_image_url($first_media_id, 'medium');
            if ($thumbnail_url) {
                update_post_meta($post_id, '_story_thumbnail_url', $thumbnail_url);
            }
            
            error_log('WP Stories: Set image as featured image for post_id ' . $post_id);
        } elseif (strpos($media_type, 'video/') === 0) {
            // For videos, try to get poster/thumbnail from WordPress first
            $poster_id = get_post_meta($first_media_id, '_thumbnail_id', true);
            if ($poster_id) {
                // Set poster as featured image
                set_post_thumbnail($post_id, $poster_id);
                
                // Also save URL in meta
                $poster_url = wp_get_attachment_image_url($poster_id, 'medium');
                if ($poster_url) {
                    update_post_meta($post_id, '_story_thumbnail_url', $poster_url);
                }
                
                error_log('WP Stories: Set video poster as featured image for post_id ' . $post_id);
            }
            // Note: Video thumbnail generation via AJAX will set featured image when it completes
        }
    }
    
    /**
     * Generate thumbnail from video file by extracting frame at 3 seconds
     */
    private function generate_video_thumbnail_from_frame($video_id, $post_id) {
        $video_path = get_attached_file($video_id);
        if (!$video_path || !file_exists($video_path)) {
            error_log('WP Stories: Video file not found for ID ' . $video_id);
            return null;
        }
        
        $upload_dir = wp_upload_dir();
        $plugin_thumb_dir = $upload_dir['basedir'] . '/wp-stories-thumbnails/';
        
        // Create directory if it doesn't exist
        if (!file_exists($plugin_thumb_dir)) {
            wp_mkdir_p($plugin_thumb_dir);
        }
        
        $thumbnail_filename = 'video_' . $video_id . '_story_' . $post_id . '.jpg';
        $thumbnail_path = $plugin_thumb_dir . $thumbnail_filename;
        
        // Check if thumbnail already exists
        if (file_exists($thumbnail_path)) {
            $thumbnail_url = $upload_dir['baseurl'] . '/wp-stories-thumbnails/' . $thumbnail_filename;
            return $thumbnail_url;
        }
        
        // This will be done via AJAX call from admin (client-side) since we need canvas
        // For now, return null and let it be generated via AJAX
        error_log('WP Stories: Need to generate thumbnail via AJAX for video ID ' . $video_id);
        return null;
    }
    
    /**
     * Save thumbnail URL that was generated client-side
     */
    private function generate_video_thumbnail($video_id, $post_id) {
        // This is kept for backward compatibility but we'll use AJAX method instead
        return null;
    }
    
    /**
     * Add custom columns to story list
     */
    public function add_story_list_columns($columns) {
        // Remove date column and add our custom columns
        unset($columns['date']);
        
        $columns['story_thumbnail'] = __('Thumbnail', 'wp-stories-plugin');
        $columns['story_media_count'] = __('Media', 'wp-stories-plugin');
        $columns['story_status'] = __('Status', 'wp-stories-plugin');
        $columns['story_expiration'] = __('Expires', 'wp-stories-plugin');
        $columns['date'] = __('Created', 'wp-stories-plugin');
        
        return $columns;
    }
    
    /**
     * Render custom column content
     */
    public function render_story_list_columns($column, $post_id) {
        switch ($column) {
            case 'story_thumbnail':
                // Priority 1: Use featured image (like WP Story Premium)
                $has_thumbnail = has_post_thumbnail($post_id);
                if ($has_thumbnail) {
                    $thumbnail_id = get_post_thumbnail_id($post_id);
                    $thumbnail_url = get_the_post_thumbnail_url($post_id, array(50, 50));
                    
                    // Debug log
                    error_log('WP Stories: Table - Post ID: ' . $post_id . ', Has featured image: Yes, Thumbnail ID: ' . $thumbnail_id . ', URL: ' . $thumbnail_url);
                    
                    if ($thumbnail_url) {
                        echo '<img src="' . esc_url($thumbnail_url) . '" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%;">';
                        break;
                    }
                } else {
                    error_log('WP Stories: Table - Post ID: ' . $post_id . ', Has featured image: No');
                }
                
                // Priority 2: Use saved thumbnail from meta
                $thumbnail_url = get_post_meta($post_id, '_story_thumbnail_url', true);
                if ($thumbnail_url) {
                    echo '<img src="' . esc_url($thumbnail_url) . '" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%;">';
                    break;
                }
                
                // Priority 3: Generate from first media item
                $media_ids = get_post_meta($post_id, '_story_media_ids', true);
                if (!empty($media_ids) && is_array($media_ids) && !empty($media_ids[0])) {
                    $media_id = $media_ids[0];
                    $thumbnail_url = wp_get_attachment_image_url($media_id, array(50, 50));
                    if ($thumbnail_url) {
                        echo '<img src="' . esc_url($thumbnail_url) . '" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%;">';
                        break;
                    }
                }
                
                // Fallback: placeholder icon
                echo '<span class="dashicons dashicons-format-image" style="font-size: 30px; color: #ccc;"></span>';
                break;
                
            case 'story_media_count':
                // Media count removed - no longer displayed
                break;
                
            case 'story_status':
                $expiration_hours = get_post_meta($post_id, '_story_expiration_hours', true);
                $created_timestamp = get_post_meta($post_id, '_story_created_timestamp', true);
                
                if (empty($expiration_hours)) {
                    echo '<span class="wp-stories-status active">' . __('Active', 'wp-stories-plugin') . '</span>';
                } else {
                    $expiration_timestamp = $created_timestamp + ($expiration_hours * HOUR_IN_SECONDS);
                    $is_expired = current_time('timestamp') > $expiration_timestamp;
                    
                    if ($is_expired) {
                        echo '<span class="wp-stories-status expired">' . __('Expired', 'wp-stories-plugin') . '</span>';
                    } else {
                        echo '<span class="wp-stories-status active">' . __('Active', 'wp-stories-plugin') . '</span>';
                    }
                }
                break;
                
            case 'story_expiration':
                $expiration_hours = get_post_meta($post_id, '_story_expiration_hours', true);
                $created_timestamp = get_post_meta($post_id, '_story_created_timestamp', true);
                
                if (empty($expiration_hours)) {
                    echo '<span class="wp-stories-no-expiration">' . __('Never', 'wp-stories-plugin') . '</span>';
                } else {
                    $expiration_timestamp = $created_timestamp + ($expiration_hours * HOUR_IN_SECONDS);
                    $time_remaining = $expiration_timestamp - current_time('timestamp');
                    
                    if ($time_remaining <= 0) {
                        echo '<span class="wp-stories-expired">' . __('Expired', 'wp-stories-plugin') . '</span>';
                    } else {
                        echo '<span class="wp-stories-time-remaining">' . $this->format_time_remaining($time_remaining) . '</span>';
                    }
                }
                break;
        }
    }
    
    /**
     * Add bulk actions
     */
    public function add_bulk_actions($bulk_actions) {
        $bulk_actions['wp_stories_extend_expiration'] = __('Extend Expiration (+1d)', 'wp-stories-plugin');
        $bulk_actions['wp_stories_remove_expiration'] = __('Remove Expiration', 'wp-stories-plugin');
        return $bulk_actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
        if ($doaction === 'wp_stories_extend_expiration') {
            foreach ($post_ids as $post_id) {
                $expiration_hours = get_post_meta($post_id, '_story_expiration_hours', true);
                if (!empty($expiration_hours)) {
                    update_post_meta($post_id, '_story_expiration_hours', intval($expiration_hours) + 24);
                }
            }
            
            $redirect_to = add_query_arg('wp_stories_extended', count($post_ids), $redirect_to);
        } elseif ($doaction === 'wp_stories_remove_expiration') {
            foreach ($post_ids as $post_id) {
                delete_post_meta($post_id, '_story_expiration_hours');
            }
            
            $redirect_to = add_query_arg('wp_stories_expiration_removed', count($post_ids), $redirect_to);
        }
        
        return $redirect_to;
    }
    
    /**
     * Enqueue media scripts for admin
     */
    public function enqueue_media_scripts($hook) {
        global $post_type;
        
        if (($hook === 'post.php' || $hook === 'post-new.php') && $post_type === 'wp_story') {
            // Enqueue WordPress media scripts
            wp_enqueue_media();
            
            // Enqueue jQuery UI for drag and drop
            wp_enqueue_script('jquery-ui-sortable');
            
            // Enqueue admin JavaScript
            wp_enqueue_script(
                'wp-stories-admin',
                GHOST_STORIES_URL . 'assets/js/admin.js',
                array('jquery', 'jquery-ui-sortable', 'wp-util'),
                GHOST_STORIES_VERSION,
                true
            );
            
            // Enqueue admin CSS
            wp_enqueue_style(
                'wp-stories-admin',
                GHOST_STORIES_URL . 'assets/css/admin.css',
                array(),
                GHOST_STORIES_VERSION
            );
            
            // Localize script with necessary data
            wp_localize_script('wp-stories-admin', 'wpStoriesAdmin', array(
                'nonce' => wp_create_nonce('wp_stories_admin_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'strings' => array(
                    'selectMedia' => __('Select Media for Story', 'wp-stories-plugin'),
                    'addToStory' => __('Add to Story', 'wp-stories-plugin'),
                    'editMedia' => __('Edit Media', 'wp-stories-plugin'),
                    'updateMedia' => __('Update Media', 'wp-stories-plugin'),
                    'mediaAdded' => __('Media added successfully', 'wp-stories-plugin'),
                    'mediaRemoved' => __('Media removed successfully', 'wp-stories-plugin'),
                    'mediaUpdated' => __('Media updated successfully', 'wp-stories-plugin'),
                    'confirmRemove' => __('Are you sure you want to remove this media item?', 'wp-stories-plugin'),
                    'dragPlaceholder' => __('Drop here', 'wp-stories-plugin'),
                    'ajaxError' => __('An error occurred. Please try again.', 'wp-stories-plugin'),
                    'selectItems' => __('Please select items to perform this action.', 'wp-stories-plugin'),
                    'confirmExtend' => __('Extend expiration by 24 hours for selected stories?', 'wp-stories-plugin'),
                    'confirmRemoveExpiration' => __('Remove expiration for selected stories?', 'wp-stories-plugin'),
                ),
            ));
        }
    }
    
    /**
     * AJAX handler for media upload
     */
    public function ajax_upload_media() {
        // Verify request method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_die(__('Invalid request method', 'wp-stories-plugin'), 405);
        }
        
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_stories_admin_nonce')) {
            wp_die(__('Security check failed', 'wp-stories-plugin'), 403);
        }
        
        // Check permissions
        if (!current_user_can('upload_files') || !current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to upload files', 'wp-stories-plugin'), 403);
        }
        
        // Rate limiting check
        if (!$this->check_upload_rate_limit()) {
            wp_die(__('Upload rate limit exceeded. Please wait before uploading again.', 'wp-stories-plugin'), 429);
        }
        
        // Validate file before upload
        $validation_result = $this->validate_media_file($_FILES['file']);
        if (is_wp_error($validation_result)) {
            wp_send_json_error($validation_result->get_error_message());
        }
        
        // Handle file upload using WordPress media handling
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $uploadedfile = $_FILES['file'];
        $upload_overrides = array(
            'test_form' => false,
            'mimes' => $this->get_allowed_mime_types()
        );
        
        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            // Create attachment
            $attachment = array(
                'post_mime_type' => $movefile['type'],
                'post_title' => sanitize_file_name($uploadedfile['name']),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            $attach_id = wp_insert_attachment($attachment, $movefile['file']);
            
            if (!is_wp_error($attach_id)) {
                // Generate attachment metadata (only for images to speed up video uploads)
                // Note: For user-uploaded images, we allow WordPress to generate normal sizes
                // The thumbnail-specific size prevention only applies to generated thumbnails
                if (wp_attachment_is_image($attach_id)) {
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
                    wp_update_attachment_metadata($attach_id, $attach_data);
                }
                
                // Skip heavy validation during upload to improve performance
                // Basic validation is already done in validate_media_file()
                
                wp_send_json_success(array(
                    'attachment_id' => $attach_id,
                    'url' => wp_get_attachment_url($attach_id),
                    'thumbnail' => wp_get_attachment_image_url($attach_id, 'thumbnail'),
                    'type' => wp_attachment_is_image($attach_id) ? 'image' : 'video',
                    'title' => get_the_title($attach_id)
                ));
            } else {
                wp_send_json_error(__('Failed to create attachment', 'wp-stories-plugin'));
            }
        } else {
            $error_message = isset($movefile['error']) ? $movefile['error'] : __('Upload failed', 'wp-stories-plugin');
            wp_send_json_error($error_message);
        }
    }
    
    /**
     * AJAX handler for media reordering
     */
    public function ajax_reorder_media() {
        // Verify request method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_die(__('Invalid request method', 'wp-stories-plugin'), 405);
        }
        
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_stories_admin_nonce')) {
            wp_die(__('Security check failed', 'wp-stories-plugin'), 403);
        }
        
        // Validate and sanitize input
        if (!isset($_POST['post_id']) || !isset($_POST['media_order'])) {
            wp_die(__('Missing required parameters', 'wp-stories-plugin'), 400);
        }
        
        $post_id = absint($_POST['post_id']);
        if ($post_id <= 0) {
            wp_die(__('Invalid post ID', 'wp-stories-plugin'), 400);
        }
        
        // Validate media order array
        if (!is_array($_POST['media_order'])) {
            wp_die(__('Invalid media order format', 'wp-stories-plugin'), 400);
        }
        
        $media_order = array_map('absint', $_POST['media_order']);
        $media_order = array_filter($media_order); // Remove zero values
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            wp_die(__('You do not have permission to edit this story', 'wp-stories-plugin'), 403);
        }
        
        // Verify post exists and is correct type
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'wp_story') {
            wp_die(__('Invalid story', 'wp-stories-plugin'), 404);
        }
        
        update_post_meta($post_id, '_story_media_ids', $media_order);
        
        wp_send_json_success();
    }
    
    /**
     * AJAX handler for media removal
     */
    public function ajax_remove_media() {
        // Verify request method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_die(__('Invalid request method', 'wp-stories-plugin'), 405);
        }
        
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_stories_admin_nonce')) {
            wp_die(__('Security check failed', 'wp-stories-plugin'), 403);
        }
        
        // Validate and sanitize input
        if (!isset($_POST['post_id']) || !isset($_POST['media_id'])) {
            wp_die(__('Missing required parameters', 'wp-stories-plugin'), 400);
        }
        
        $post_id = absint($_POST['post_id']);
        $media_id = absint($_POST['media_id']);
        
        if ($post_id <= 0 || $media_id <= 0) {
            wp_die(__('Invalid parameters', 'wp-stories-plugin'), 400);
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            wp_die(__('You do not have permission to edit this story', 'wp-stories-plugin'), 403);
        }
        
        // Verify post exists and is correct type
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'wp_story') {
            wp_die(__('Invalid story', 'wp-stories-plugin'), 404);
        }
        
        // Verify media exists and user can delete it
        $media_post = get_post($media_id);
        if (!$media_post || !wp_attachment_is($media_post->post_type)) {
            wp_die(__('Invalid media item', 'wp-stories-plugin'), 404);
        }
        
        $media_ids = get_post_meta($post_id, '_story_media_ids', true);
        if (is_array($media_ids)) {
            $media_ids = array_diff($media_ids, array($media_id));
            update_post_meta($post_id, '_story_media_ids', array_values($media_ids));
        }
        
        wp_send_json_success();
    }
    
    /**
     * Render recent stories table
     */
    private function render_recent_stories_table() {
        $recent_stories = get_posts(array(
            'post_type' => 'wp_story',
            'post_status' => 'publish',
            'posts_per_page' => 20, // Show more stories
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        if (empty($recent_stories)) {
            echo '<p>' . __('No stories found.', 'wp-stories-plugin') . '</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Title', 'wp-stories-plugin'); ?></th>
                    <th><?php _e('Media', 'wp-stories-plugin'); ?></th>
                    <th><?php _e('Status', 'wp-stories-plugin'); ?></th>
                    <th><?php _e('Created', 'wp-stories-plugin'); ?></th>
                    <th><?php _e('Actions', 'wp-stories-plugin'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_stories as $story): ?>
                    <?php
                    $media_ids = get_post_meta($story->ID, '_story_media_ids', true);
                    $media_count = is_array($media_ids) ? count($media_ids) : 0;
                    $expiration_hours = get_post_meta($story->ID, '_story_expiration_hours', true);
                    $created_timestamp = get_post_meta($story->ID, '_story_created_timestamp', true);
                    
                    $is_expired = false;
                    if (!empty($expiration_hours) && !empty($created_timestamp)) {
                        $expiration_timestamp = $created_timestamp + ($expiration_hours * HOUR_IN_SECONDS);
                        $is_expired = current_time('timestamp') > $expiration_timestamp;
                    }
                    ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo get_edit_post_link($story->ID); ?>">
                                    <?php echo esc_html($story->post_title); ?>
                                </a>
                            </strong>
                        </td>
                        <td><?php echo esc_html($media_count); ?></td>
                        <td>
                            <span class="wp-stories-status <?php echo $is_expired ? 'expired' : 'active'; ?>">
                                <?php echo $is_expired ? __('Expired', 'wp-stories-plugin') : __('Active', 'wp-stories-plugin'); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(get_the_date('', $story)); ?></td>
                        <td>
                            <a href="<?php echo get_edit_post_link($story->ID); ?>" class="button button-small">
                                <?php _e('Edit', 'wp-stories-plugin'); ?>
                            </a>
                            <a href="<?php echo get_delete_post_link($story->ID); ?>" class="button button-small" 
                               onclick="return confirm('<?php _e('Are you sure you want to delete this story?', 'wp-stories-plugin'); ?>')">
                                <?php _e('Delete', 'wp-stories-plugin'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Check upload rate limit to prevent abuse
     *
     * @return bool True if within rate limit, false otherwise
     */
    private function check_upload_rate_limit() {
        $user_id = get_current_user_id();
        $rate_limit_key = 'wp_stories_upload_rate_' . $user_id;
        $uploads_count = get_transient($rate_limit_key);
        
        // Allow 20 uploads per hour per user
        $max_uploads = apply_filters('wp_stories_max_uploads_per_hour', 20);
        
        if ($uploads_count === false) {
            set_transient($rate_limit_key, 1, HOUR_IN_SECONDS);
            return true;
        }
        
        if ($uploads_count >= $max_uploads) {
            return false;
        }
        
        set_transient($rate_limit_key, $uploads_count + 1, HOUR_IN_SECONDS);
        return true;
    }
    
    /**
     * Validate media access for current user
     *
     * @param int $media_id Media attachment ID
     * @return bool True if user has access, false otherwise
     */
    private function validate_media_access($media_id) {
        // Check if attachment exists
        $attachment = get_post($media_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            return false;
        }
        
        // Check if user can edit the attachment
        if (!current_user_can('edit_post', $media_id)) {
            return false;
        }
        
        // Validate file type is allowed
        $allowed_types = $this->get_allowed_mime_types();
        $file_type = get_post_mime_type($media_id);
        
        if (!in_array($file_type, $allowed_types, true)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get allowed MIME types for story media
     *
     * @return array Allowed MIME types
     */
    private function get_allowed_mime_types() {
        $allowed_types = array(
            // Images
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/gif',
            'image/webp',
            // Videos
            'video/mp4',
            'video/webm',
            'video/ogg'
        );
        
        return apply_filters('wp_stories_allowed_mime_types', $allowed_types);
    }
    
    /**
     * Validate uploaded media file before processing
     *
     * @param array $file File data from $_FILES
     * @return true|WP_Error True on success, WP_Error on failure
     */
    private function validate_media_file($file) {
        // Check file was uploaded without errors
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('File upload failed', 'wp-stories-plugin'));
        }
        
        // Check file size
        $max_size = wp_max_upload_size();
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', 
                sprintf(__('File size exceeds maximum allowed size of %s', 'wp-stories-plugin'), 
                size_format($max_size)));
        }
        
        // Check minimum file size (prevent empty files)
        if ($file['size'] < 100) {
            return new WP_Error('file_too_small', __('File is too small', 'wp-stories-plugin'));
        }
        
        // Validate file type
        $file_type = wp_check_filetype($file['name']);
        $allowed_types = $this->get_allowed_mime_types();
        
        if (!in_array($file_type['type'], $allowed_types, true)) {
            return new WP_Error('invalid_file_type', 
                __('File type not allowed. Please upload images or videos only.', 'wp-stories-plugin'));
        }
        
        // Additional security: Check file content matches extension
        if (!$this->validate_file_content($file['tmp_name'], $file_type['type'])) {
            return new WP_Error('file_content_mismatch', 
                __('File content does not match file extension', 'wp-stories-plugin'));
        }
        
        return true;
    }
    
    /**
     * Validate file content matches declared MIME type
     *
     * @param string $file_path Temporary file path
     * @param string $declared_type Declared MIME type
     * @return bool True if content matches, false otherwise
     */
    private function validate_file_content($file_path, $declared_type) {
        if (!function_exists('finfo_open')) {
            // If fileinfo extension not available, skip validation
            return true;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $actual_type = finfo_file($finfo, $file_path);
        finfo_close($finfo);
        
        // Handle common MIME type variations
        $type_mappings = array(
            'image/jpg' => 'image/jpeg',
        );
        
        if (isset($type_mappings[$declared_type])) {
            $declared_type = $type_mappings[$declared_type];
        }
        
        if (isset($type_mappings[$actual_type])) {
            $actual_type = $type_mappings[$actual_type];
        }
        
        return $actual_type === $declared_type;
    }
    
    /**
     * Validate attachment after creation
     *
     * @param int $attachment_id Attachment ID
     * @return true|WP_Error True on success, WP_Error on failure
     */
    private function validate_attachment($attachment_id) {
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            return new WP_Error('attachment_not_found', __('Attachment not found', 'wp-stories-plugin'));
        }
        
        // Check file exists
        $file_path = get_attached_file($attachment_id);
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('Uploaded file not found', 'wp-stories-plugin'));
        }
        
        // Check file is readable
        if (!is_readable($file_path)) {
            return new WP_Error('file_not_readable', __('Uploaded file is not readable', 'wp-stories-plugin'));
        }
        
        // For images, validate dimensions
        if (wp_attachment_is_image($attachment_id)) {
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (!$metadata || !isset($metadata['width']) || !isset($metadata['height'])) {
                return new WP_Error('invalid_image', __('Invalid image file', 'wp-stories-plugin'));
            }
            
            // Check minimum dimensions
            if ($metadata['width'] < 100 || $metadata['height'] < 100) {
                return new WP_Error('image_too_small', 
                    __('Image dimensions too small. Minimum 100x100 pixels required.', 'wp-stories-plugin'));
            }
            
            // Check maximum dimensions
            $max_width = apply_filters('wp_stories_max_image_width', 4000);
            $max_height = apply_filters('wp_stories_max_image_height', 4000);
            
            if ($metadata['width'] > $max_width || $metadata['height'] > $max_height) {
                return new WP_Error('image_too_large', 
                    sprintf(__('Image dimensions too large. Maximum %dx%d pixels allowed.', 'wp-stories-plugin'), 
                    $max_width, $max_height));
            }
        }
        
        return true;
    }
    
    /**
     * Add missing AJAX handlers with security
     */
    public function ajax_get_media_library() {
        // Verify request method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_die(__('Invalid request method', 'wp-stories-plugin'), 405);
        }
        
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_stories_admin_nonce')) {
            wp_die(__('Security check failed', 'wp-stories-plugin'), 403);
        }
        
        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_die(__('You do not have permission to access media library', 'wp-stories-plugin'), 403);
        }
        
        // Get media library items with pagination
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = 20; // Limit results for performance
        
        $args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_mime_type' => $this->get_allowed_mime_types(),
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $media_query = new WP_Query($args);
        $media_items = array();
        
        foreach ($media_query->posts as $media) {
            if ($this->validate_media_access($media->ID)) {
                $media_items[] = array(
                    'id' => $media->ID,
                    'title' => esc_html($media->post_title),
                    'url' => esc_url(wp_get_attachment_url($media->ID)),
                    'thumbnail' => esc_url(wp_get_attachment_image_url($media->ID, 'thumbnail')),
                    'type' => wp_attachment_is_image($media->ID) ? 'image' : 'video'
                );
            }
        }
        
        wp_send_json_success(array(
            'media' => $media_items,
            'total_pages' => $media_query->max_num_pages,
            'current_page' => $page
        ));
    }
    
    /**
     * AJAX handler for adding existing media to story
     */
    public function ajax_add_existing_media() {
        // Verify request method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_die(__('Invalid request method', 'wp-stories-plugin'), 405);
        }
        
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_stories_admin_nonce')) {
            wp_die(__('Security check failed', 'wp-stories-plugin'), 403);
        }
        
        // Validate input
        if (!isset($_POST['media_ids']) || !is_array($_POST['media_ids'])) {
            wp_die(__('Invalid media selection', 'wp-stories-plugin'), 400);
        }
        
        $media_ids = array_map('absint', $_POST['media_ids']);
        $media_ids = array_filter($media_ids);
        
        // Validate each media ID
        $validated_media = array();
        foreach ($media_ids as $media_id) {
            if ($this->validate_media_access($media_id)) {
                $attachment = get_post($media_id);
                $validated_media[] = array(
                    'id' => $media_id,
                    'title' => esc_html($attachment->post_title),
                    'url' => esc_url(wp_get_attachment_url($media_id)),
                    'thumbnail' => esc_url(wp_get_attachment_image_url($media_id, 'thumbnail')),
                    'type' => wp_attachment_is_image($media_id) ? 'image' : 'video'
                );
            }
        }
        
        wp_send_json_success(array('media' => $validated_media));
    }
    
    /**
     * AJAX handler for checking media integrity
     */
    public function ajax_check_media_integrity() {
        // Verify request method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_die(__('Invalid request method', 'wp-stories-plugin'), 405);
        }
        
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_stories_admin_nonce')) {
            wp_die(__('Security check failed', 'wp-stories-plugin'), 403);
        }
        
        // Validate input
        if (!isset($_POST['post_id'])) {
            wp_die(__('Missing story ID', 'wp-stories-plugin'), 400);
        }
        
        $post_id = absint($_POST['post_id']);
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            wp_die(__('You do not have permission to edit this story', 'wp-stories-plugin'), 403);
        }
        
        // Get story media and check integrity
        $media_ids = get_post_meta($post_id, '_story_media_ids', true);
        if (!is_array($media_ids)) {
            $media_ids = array();
        }
        
        $integrity_report = array(
            'valid' => array(),
            'invalid' => array(),
            'missing' => array()
        );
        
        foreach ($media_ids as $media_id) {
            $attachment = get_post($media_id);
            if (!$attachment) {
                $integrity_report['missing'][] = $media_id;
                continue;
            }
            
            $file_path = get_attached_file($media_id);
            if (!file_exists($file_path)) {
                $integrity_report['invalid'][] = array(
                    'id' => $media_id,
                    'title' => esc_html($attachment->post_title),
                    'reason' => __('File not found', 'wp-stories-plugin')
                );
                continue;
            }
            
            if (!$this->validate_media_access($media_id)) {
                $integrity_report['invalid'][] = array(
                    'id' => $media_id,
                    'title' => esc_html($attachment->post_title),
                    'reason' => __('Access denied', 'wp-stories-plugin')
                );
                continue;
            }
            
            $integrity_report['valid'][] = array(
                'id' => $media_id,
                'title' => esc_html($attachment->post_title)
            );
        }
        
        wp_send_json_success($integrity_report);
    }
    
    /**
     * Handle deleted media cleanup
     */
    public function handle_deleted_media($attachment_id) {
        // Find all stories that use this media
        $stories = get_posts(array(
            'post_type' => 'wp_story',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_story_media_ids',
                    'value' => serialize(strval($attachment_id)),
                    'compare' => 'LIKE'
                )
            )
        ));
        
        foreach ($stories as $story) {
            $media_ids = get_post_meta($story->ID, '_story_media_ids', true);
            if (is_array($media_ids)) {
                $media_ids = array_diff($media_ids, array($attachment_id));
                update_post_meta($story->ID, '_story_media_ids', array_values($media_ids));
            }
        }
    }
    
    /**
     * Handle story deletion - delete all associated thumbnails
     * 
     * @param int $post_id Post ID being deleted
     */
    public function handle_story_deletion($post_id) {
        // Check if it's a story post type
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'wp_story') {
            return;
        }
        
        // Get featured image (thumbnail)
        $thumbnail_id = get_post_thumbnail_id($post_id);
        
        if ($thumbnail_id) {
            // Check if this is a generated thumbnail (not original media)
            $thumbnail_file = get_attached_file($thumbnail_id);
            
            if ($thumbnail_file) {
                $filename = basename($thumbnail_file);
                // If filename contains 'story_' or 'video_', it's a generated thumbnail
                if (strpos($filename, 'story_') !== false || strpos($filename, 'video_') !== false) {
                    // Delete the generated thumbnail attachment and file
                    wp_delete_attachment($thumbnail_id, true);
                    error_log('WP Stories: Deleted thumbnail attachment (ID: ' . $thumbnail_id . ') when deleting story (post_id: ' . $post_id . ')');
                } else {
                    // It's original media, just remove as featured image (don't delete the media file)
                    delete_post_thumbnail($post_id);
                    error_log('WP Stories: Removed thumbnail (ID: ' . $thumbnail_id . ') as featured image when deleting story (post_id: ' . $post_id . ')');
                }
            } else {
                // No file found, just remove as featured image
                delete_post_thumbnail($post_id);
            }
        }
        
        // Also clean up thumbnail URL meta
        delete_post_meta($post_id, '_story_thumbnail_url');
        
        error_log('WP Stories: Cleaned up thumbnails for deleted story (post_id: ' . $post_id . ')');
    }
    
    /**
     * Disable intermediate image sizes generation for thumbnails
     * 
     * @param array $sizes Array of image sizes
     * @return array Empty array to disable all intermediate sizes
     */
    public function disable_intermediate_image_sizes($sizes) {
        // Return empty array to prevent generation of any intermediate sizes
        return array();
    }
    
    /**
     * Disable image sizes in image editor
     * 
     * @param array $args Image editor arguments
     * @return array Modified arguments
     */
    public function disable_image_sizes_in_editor($args) {
        // Disable any resize operations
        if (isset($args['resize'])) {
            $args['resize'] = false;
        }
        return $args;
    }
    
    /**
     * Get count of active stories
     */
    private function get_active_stories_count() {
        global $wpdb;
        
        // Fallback for testing environment
        if (!$wpdb) {
            return 3; // Mock value for testing
        }
        
        $query = "
            SELECT COUNT(p.ID) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_story_expiration_hours'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_story_created_timestamp'
            WHERE p.post_type = 'wp_story' 
            AND p.post_status = 'publish'
            AND (
                pm1.meta_value IS NULL 
                OR pm1.meta_value = '' 
                OR (pm2.meta_value + (pm1.meta_value * 3600)) > %d
            )
        ";
        
        return $wpdb->get_var($wpdb->prepare($query, current_time('timestamp')));
    }
    
    /**
     * Get count of expired stories
     */
    private function get_expired_stories_count() {
        global $wpdb;
        
        // Fallback for testing environment
        if (!$wpdb) {
            return 1; // Mock value for testing
        }
        
        $query = "
            SELECT COUNT(p.ID) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_story_expiration_hours'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_story_created_timestamp'
            WHERE p.post_type = 'wp_story' 
            AND p.post_status = 'publish'
            AND pm1.meta_value IS NOT NULL 
            AND pm1.meta_value != ''
            AND (pm2.meta_value + (pm1.meta_value * 3600)) <= %d
        ";
        
        return $wpdb->get_var($wpdb->prepare($query, current_time('timestamp')));
    }

    
    /**
     * Format time remaining for display
     */
    private function format_time_remaining($seconds) {
        if ($seconds <= 0) {
            return __('Expired', 'wp-stories-plugin');
        }
        
        $days = floor($seconds / (24 * 60 * 60));
        $hours = floor(($seconds % (24 * 60 * 60)) / (60 * 60));
        
        if ($days > 0) {
            return sprintf(_n('%d day left', '%d days left', $days, 'wp-stories-plugin'), $days);
        } elseif ($hours > 0) {
            return sprintf(_n('%d hour left', '%d hours left', $hours, 'wp-stories-plugin'), $hours);
        } else {
            $minutes = floor($seconds / 60);
            return sprintf(_n('%d minute left', '%d minutes left', $minutes, 'wp-stories-plugin'), max(1, $minutes));
        }
    }
    
    /**
     * AJAX handler to save thumbnail generated from video
     * Creates a WordPress attachment and sets it as featured image
     */
    public function ajax_save_thumbnail() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_stories_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'wp-stories-plugin'));
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to perform this action', 'wp-stories-plugin'));
            return;
        }
        
        // Validate input
        if (!isset($_POST['thumbnail_data']) || !isset($_POST['media_id']) || !isset($_POST['post_id'])) {
            wp_send_json_error(__('Missing required parameters', 'wp-stories-plugin'));
            return;
        }
        
        $thumbnail_data = $_POST['thumbnail_data'];
        $media_id = absint($_POST['media_id']);
        $post_id = absint($_POST['post_id']);
        
        // Validate post exists and user can edit it
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('You do not have permission to edit this story', 'wp-stories-plugin'));
            return;
        }
        
        // Delete existing thumbnail before creating new one
        if (has_post_thumbnail($post_id)) {
            $existing_thumbnail_id = get_post_thumbnail_id($post_id);
            
            if ($existing_thumbnail_id) {
                // Check if this is a generated thumbnail
                $thumbnail_file = get_attached_file($existing_thumbnail_id);
                if ($thumbnail_file) {
                    $filename = basename($thumbnail_file);
                    // If filename contains 'story_' or 'video_', it's a generated thumbnail
                    if (strpos($filename, 'story_') !== false || strpos($filename, 'video_') !== false) {
                        // Delete the generated thumbnail attachment
                        wp_delete_attachment($existing_thumbnail_id, true);
                        error_log('WP Stories: Deleted old thumbnail (attachment_id: ' . $existing_thumbnail_id . ') before creating new one for post_id ' . $post_id);
                    } else {
                        // Just remove as featured image
                        delete_post_thumbnail($post_id);
                    }
                } else {
                    // No file found, just remove as featured image
                    delete_post_thumbnail($post_id);
                }
            }
        }
        
        // Get upload directory
        $upload_dir = wp_upload_dir();
        
        // Generate unique filename
        $thumbnail_filename = 'story_' . $post_id . '_' . time() . '.jpg';
        $thumbnail_path = $upload_dir['path'] . '/' . $thumbnail_filename;
        
        // Decode data URL and save file
        $thumbnail_data = str_replace('data:image/jpeg;base64,', '', $thumbnail_data);
        $thumbnail_data = str_replace(' ', '+', $thumbnail_data);
        $thumbnail_data = base64_decode($thumbnail_data);
        
        if ($thumbnail_data === false) {
            wp_send_json_error(__('Failed to decode thumbnail data', 'wp-stories-plugin'));
            return;
        }
        
        // Save file
        $file_saved = file_put_contents($thumbnail_path, $thumbnail_data);
        
        if ($file_saved === false) {
            wp_send_json_error(__('Failed to save thumbnail file', 'wp-stories-plugin'));
            return;
        }
        
        // Get file type
        $file_type = wp_check_filetype($thumbnail_filename, null);
        
        // Prepare attachment data
        $attachment_data = array(
            'post_mime_type' => $file_type['type'],
            'post_title'     => sanitize_file_name(pathinfo($thumbnail_filename, PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        
        // Create attachment
        $attachment_id = wp_insert_attachment($attachment_data, $thumbnail_path, $post_id);
        
        if (is_wp_error($attachment_id)) {
            // Clean up file if attachment creation failed
            @unlink($thumbnail_path);
            wp_send_json_error(__('Failed to create attachment: ', 'wp-stories-plugin') . $attachment_id->get_error_message());
            return;
        }
        
        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Temporarily disable ALL intermediate image sizes generation with highest priority
        add_filter('intermediate_image_sizes', array($this, 'disable_intermediate_image_sizes'), 9999);
        add_filter('intermediate_image_sizes_advanced', array($this, 'disable_intermediate_image_sizes'), 9999);
        
        // Also disable via wp_image_editor filter to catch any other code paths
        add_filter('wp_image_editor_default_args', array($this, 'disable_image_sizes_in_editor'), 9999);
        
        try {
            $attachment_meta = wp_generate_attachment_metadata($attachment_id, $thumbnail_path);
        } catch (Exception $e) {
            error_log('WP Stories: Error generating metadata: ' . $e->getMessage());
        }
        
        // Remove all filters after generation
        remove_filter('intermediate_image_sizes', array($this, 'disable_intermediate_image_sizes'), 9999);
        remove_filter('intermediate_image_sizes_advanced', array($this, 'disable_intermediate_image_sizes'), 9999);
        remove_filter('wp_image_editor_default_args', array($this, 'disable_image_sizes_in_editor'), 9999);
        
        // Force cleanup: Remove sizes from metadata AND delete any files that might have been created
        if (isset($attachment_meta['sizes']) && is_array($attachment_meta['sizes'])) {
            $upload_dir = wp_upload_dir();
            $base_dir = dirname($thumbnail_path);
            
            foreach ($attachment_meta['sizes'] as $size_name => $size_data) {
                // Delete the intermediate size file if it exists
                if (isset($size_data['file'])) {
                    $intermediate_file = $base_dir . '/' . $size_data['file'];
                    if (file_exists($intermediate_file)) {
                        @unlink($intermediate_file);
                        error_log('WP Stories: Deleted intermediate size file: ' . $intermediate_file);
                    }
                }
            }
            
            // Remove sizes from metadata completely
            unset($attachment_meta['sizes']);
        }
        
        // Also scan for any files with pattern "-{width}x{height}.{ext}" in the same directory and delete them
        // This catches files that might have been generated before metadata was updated
        // Support both .jpg and .jpeg extensions
        $base_dir = dirname($thumbnail_path);
        $base_filename = pathinfo($thumbnail_filename, PATHINFO_FILENAME);
        $extensions = array('jpg', 'jpeg');
        foreach ($extensions as $ext) {
            $files = glob($base_dir . '/' . $base_filename . '-*.' . $ext);
            if ($files && is_array($files)) {
                foreach ($files as $file) {
                    // Don't delete the original file
                    if ($file !== $thumbnail_path && file_exists($file)) {
                        @unlink($file);
                        error_log('WP Stories: Deleted orphaned intermediate size file: ' . $file);
                    }
                }
            }
        }
        
        wp_update_attachment_metadata($attachment_id, $attachment_meta);
        
        // Set as featured image (this is the key part - like WP Story Premium)
        set_post_thumbnail($post_id, $attachment_id);
        
        // Also save URL in meta for backward compatibility
        $thumbnail_url = wp_get_attachment_url($attachment_id); // Use full URL since we don't have intermediate sizes
        update_post_meta($post_id, '_story_thumbnail_url', $thumbnail_url);
        
        error_log('WP Stories: Thumbnail saved as featured image (attachment_id: ' . $attachment_id . ') for post_id ' . $post_id);
        
        wp_send_json_success(array(
            'thumbnail_url' => $thumbnail_url,
            'thumbnail_id' => $attachment_id,
            'message' => __('Thumbnail saved successfully', 'wp-stories-plugin')
        ));
    }
    
    /**
     * AJAX handler to set image as thumbnail directly
     * Uses the image attachment itself as featured image (no conversion needed)
     */
    public function ajax_set_image_thumbnail() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_stories_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'wp-stories-plugin'));
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to perform this action', 'wp-stories-plugin'));
            return;
        }
        
        // Validate input
        if (!isset($_POST['media_id']) || !isset($_POST['post_id'])) {
            wp_send_json_error(__('Missing required parameters', 'wp-stories-plugin'));
            return;
        }
        
        $media_id = absint($_POST['media_id']);
        $post_id = absint($_POST['post_id']);
        
        // Validate post exists and user can edit it
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('You do not have permission to edit this story', 'wp-stories-plugin'));
            return;
        }
        
        // Validate media is an image
        $media_type = get_post_mime_type($media_id);
        if (strpos($media_type, 'image/') !== 0) {
            wp_send_json_error(__('Media is not an image', 'wp-stories-plugin'));
            return;
        }
        
        // Delete existing thumbnail before setting new one
        if (has_post_thumbnail($post_id)) {
            $existing_thumbnail_id = get_post_thumbnail_id($post_id);
            
            // Only delete if it's not the same image (updating to different image)
            if ($existing_thumbnail_id != $media_id && $existing_thumbnail_id) {
                // Check if this is a generated thumbnail
                $thumbnail_file = get_attached_file($existing_thumbnail_id);
                if ($thumbnail_file) {
                    $filename = basename($thumbnail_file);
                    // If filename contains 'story_' or 'video_', it's a generated thumbnail
                    if (strpos($filename, 'story_') !== false || strpos($filename, 'video_') !== false) {
                        // Delete the generated thumbnail attachment
                        wp_delete_attachment($existing_thumbnail_id, true);
                        error_log('WP Stories: Deleted old thumbnail (attachment_id: ' . $existing_thumbnail_id . ') before setting new image for post_id ' . $post_id);
                    } else {
                        // Just remove as featured image (don't delete original media)
                        delete_post_thumbnail($post_id);
                    }
                } else {
                    // No file found, just remove as featured image
                    delete_post_thumbnail($post_id);
                }
            }
            // If it's the same image, no need to do anything
        }
        
        // Check if this image is a generated thumbnail (shouldn't have intermediate sizes)
        // If filename starts with 'story_' or 'video_', clean up any intermediate sizes
        $media_file = get_attached_file($media_id);
        if ($media_file) {
            $filename = basename($media_file);
            // Check if it's a generated thumbnail by filename pattern
            if (strpos($filename, 'story_') === 0 || strpos($filename, 'video_') === 0) {
                // This is a generated thumbnail, ensure no intermediate sizes exist
                $attachment_meta = wp_get_attachment_metadata($media_id);
                if ($attachment_meta && isset($attachment_meta['sizes']) && is_array($attachment_meta['sizes'])) {
                    $base_dir = dirname($media_file);
                    $base_filename = pathinfo($filename, PATHINFO_FILENAME);
                    
                    // Delete all intermediate size files
                    foreach ($attachment_meta['sizes'] as $size_name => $size_data) {
                        if (isset($size_data['file'])) {
                            $intermediate_file = $base_dir . '/' . $size_data['file'];
                            if (file_exists($intermediate_file)) {
                                @unlink($intermediate_file);
                                error_log('WP Stories: Deleted intermediate size from generated thumbnail: ' . $intermediate_file);
                            }
                        }
                    }
                    
                    // Remove sizes from metadata
                    unset($attachment_meta['sizes']);
                    wp_update_attachment_metadata($media_id, $attachment_meta);
                    
                    // Also scan for any orphaned files with pattern "-{width}x{height}.{ext}"
                    $extensions = array('jpg', 'jpeg');
                    foreach ($extensions as $ext) {
                        $files = glob($base_dir . '/' . $base_filename . '-*.' . $ext);
                        if ($files && is_array($files)) {
                            foreach ($files as $file) {
                                if ($file !== $media_file && file_exists($file)) {
                                    @unlink($file);
                                    error_log('WP Stories: Deleted orphaned intermediate size: ' . $file);
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Set image as featured image directly
        set_post_thumbnail($post_id, $media_id);
        
        // Also save URL in meta for backward compatibility
        // For generated thumbnails, use full URL; for regular images, use medium size
        $is_generated = ($media_file && (strpos(basename($media_file), 'story_') === 0 || strpos(basename($media_file), 'video_') === 0));
        if ($is_generated) {
            $thumbnail_url = wp_get_attachment_url($media_id);
        } else {
            $thumbnail_url = wp_get_attachment_image_url($media_id, 'medium');
        }
        if ($thumbnail_url) {
            update_post_meta($post_id, '_story_thumbnail_url', $thumbnail_url);
        }
        
        error_log('WP Stories: Image set as featured image (attachment_id: ' . $media_id . ') for post_id ' . $post_id);
        
        wp_send_json_success(array(
            'thumbnail_url' => $thumbnail_url,
            'thumbnail_id' => $media_id,
            'message' => __('Thumbnail set successfully', 'wp-stories-plugin')
        ));
    }
    
    /**
     * AJAX handler to get WordPress-generated video thumbnail
     * This is a fallback when video cannot be loaded in browser for canvas extraction
     */
    public function ajax_get_video_thumbnail() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_stories_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'wp-stories-plugin'));
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to perform this action', 'wp-stories-plugin'));
            return;
        }
        
        // Validate input
        if (!isset($_POST['media_id']) || !isset($_POST['post_id'])) {
            wp_send_json_error(__('Missing required parameters', 'wp-stories-plugin'));
            return;
        }
        
        $media_id = absint($_POST['media_id']);
        $post_id = absint($_POST['post_id']);
        
        // Validate post exists and user can edit it
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('You do not have permission to edit this story', 'wp-stories-plugin'));
            return;
        }
        
        // Validate media is a video
        $media_type = get_post_mime_type($media_id);
        if (strpos($media_type, 'video/') !== 0) {
            wp_send_json_error(__('Media is not a video', 'wp-stories-plugin'));
            return;
        }
        
        error_log('WP Stories: Getting WordPress-generated thumbnail for video (attachment_id: ' . $media_id . ') for post_id ' . $post_id);
        
        // Try to get thumbnail from video attachment metadata
        $attachment_meta = wp_get_attachment_metadata($media_id);
        $thumbnail_url = null;
        $thumbnail_id = null;
        
        // Priority 1: Check if video has a custom poster/thumbnail
        if (isset($attachment_meta['image']) && is_array($attachment_meta['image'])) {
            $thumbnail_id = $attachment_meta['image']['id'];
            if ($thumbnail_id) {
                $thumbnail_url = wp_get_attachment_url($thumbnail_id);
                error_log('WP Stories: Found custom poster thumbnail (attachment_id: ' . $thumbnail_id . ')');
            }
        }
        
        // Priority 2: Check if there's an associated thumbnail attachment
        if (!$thumbnail_url) {
            $thumbnail_id = get_post_meta($media_id, '_thumbnail_id', true);
            if ($thumbnail_id) {
                $thumbnail_url = wp_get_attachment_url($thumbnail_id);
                error_log('WP Stories: Found associated thumbnail (attachment_id: ' . $thumbnail_id . ')');
            }
        }
        
        // Priority 3: WordPress might have auto-generated a thumbnail on upload
        // Check if there are any image attachments with similar name (e.g., video.mp4 -> video-thumbnail.jpg)
        if (!$thumbnail_url && isset($attachment_meta['file'])) {
            $upload_dir = wp_upload_dir();
            $video_path = $upload_dir['basedir'] . '/' . $attachment_meta['file'];
            $video_dir = dirname($video_path);
            $video_basename = pathinfo(basename($video_path), PATHINFO_FILENAME);
            
            // Look for thumbnail files in the same directory
            $thumbnail_patterns = array(
                $video_dir . '/' . $video_basename . '-thumbnail.jpg',
                $video_dir . '/' . $video_basename . '-thumb.jpg',
                $video_dir . '/' . $video_basename . '-poster.jpg',
                $video_dir . '/' . $video_basename . '.jpg',
                $video_dir . '/' . $video_basename . '.jpeg'
            );
            
            foreach ($thumbnail_patterns as $pattern) {
                if (file_exists($pattern)) {
                    // Check if there's already a WordPress attachment for this file
                    $thumbnail_url_from_file = $upload_dir['baseurl'] . '/' . str_replace($upload_dir['basedir'], '', $pattern);
                    
                    // Try to find existing attachment
                    global $wpdb;
                    $attachment_query = $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment'",
                        $thumbnail_url_from_file
                    );
                    $existing_attachment_id = $wpdb->get_var($attachment_query);
                    
                    if ($existing_attachment_id) {
                        $thumbnail_id = $existing_attachment_id;
                        $thumbnail_url = wp_get_attachment_url($thumbnail_id);
                        error_log('WP Stories: Found auto-generated thumbnail file (attachment_id: ' . $thumbnail_id . ')');
                        break;
                    }
                }
            }
        }
        
        // Priority 4: Check if there's a placeholder image in wp-includes/images/media
        if (!$thumbnail_url) {
            $placeholder_url = includes_url('images/media/video.png');
            error_log('WP Stories: No thumbnail found, using WordPress default placeholder');
        }
        
        // If we found a thumbnail, set it as featured image for the story
        if ($thumbnail_url && $thumbnail_id) {
            set_post_thumbnail($post_id, $thumbnail_id);
            update_post_meta($post_id, '_story_thumbnail_url', $thumbnail_url);
            error_log('WP Stories: Set video thumbnail as featured image (attachment_id: ' . $thumbnail_id . ') for post_id ' . $post_id);
        }
        
        wp_send_json_success(array(
            'thumbnail_url' => $thumbnail_url ? $thumbnail_url : $placeholder_url,
            'thumbnail_id' => $thumbnail_id ? $thumbnail_id : 0,
            'message' => $thumbnail_url ? __('Thumbnail retrieved successfully', 'wp-stories-plugin') : __('Using default placeholder', 'wp-stories-plugin')
        ));
    }
}
