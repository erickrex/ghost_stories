# Implementation Plan

## Phase 1: Core Plugin Structure

- [x] 1. Create main plugin file and bootstrap
  - [x] 1.1 Create `ghost-stories.php` with plugin headers
    - Define plugin name, version, text domain
    - Set up plugin constants (GHOST_STORIES_VERSION, PATH, URL, BASENAME)
    - Include main plugin class
  - [x] 1.2 Implement activation hook
    - Check WordPress and PHP version requirements
    - Create database structure function
    - Set default plugin options
    - Schedule cleanup events
    - Flush rewrite rules
  - [x] 1.3 Implement deactivation hook
    - Clear scheduled events
    - Cleanup transients
    - Clear caches
    - Flush rewrite rules

- [x] 2. Create main plugin class (Singleton pattern)
  - [x] 2.1 Create `includes/class-wp-stories-plugin.php`
    - Implement singleton pattern with `get_instance()`
    - Define additional constants (INCLUDES_PATH, ASSETS_URL, BLOCKS_PATH)
  - [x] 2.2 Implement dependency loading
    - Include all component class files
    - Load in correct order (post type, models, admin, api, frontend)
  - [x] 2.3 Initialize WordPress hooks
    - Register init action
    - Register admin_enqueue_scripts action
    - Register cache management hooks
  - [x] 2.4 Initialize plugin components
    - Create instances of Story_Post_Type, Story_Admin, Story_API, Story_Frontend

## Phase 2: Custom Post Type and Data Models

- [x] 3. Implement custom post type
  - [x] 3.1 Create `includes/class-story-post-type.php`
    - Define POST_TYPE constant as 'wp_story'
    - Define meta key constants
  - [x] 3.2 Register post type with WordPress
    - Set up labels for admin interface
    - Configure capabilities
    - Enable REST API support
    - Set menu icon (dashicons-format-gallery)
  - [x] 3.3 Implement meta data handling
    - Save media IDs array
    - Save expiration hours
    - Set creation timestamp on publish
  - [x] 3.4 Implement expiration system
    - `is_story_expired()` with caching
    - `get_time_remaining()` calculation
    - `get_formatted_time_remaining()` for display
    - Scheduled cleanup cron job

- [x] 4. Create data model classes
  - [x] 4.1 Create `includes/class-wp-story.php`
    - Story entity with ID, title, media items
    - Expiration checking methods
    - Valid media filtering
  - [x] 4.2 Create `includes/class-story-media-item.php`
    - Media type detection (image/video)
    - URL and thumbnail handling
    - Responsive URL generation
  - [x] 4.3 Create `includes/class-story-collection.php`
    - Collection management
    - Filtering by expiration
    - Sorting methods

## Phase 3: Admin Interface

- [x] 5. Implement admin functionality
  - [x] 5.1 Create `includes/class-story-admin.php`
    - Register metaboxes using native WordPress API
    - Implement nonce verification
  - [x] 5.2 Implement media management metabox
    - Media upload via WordPress media library
    - Drag-and-drop reordering
    - Remove media functionality
  - [x] 5.3 Implement expiration settings metabox
    - Expiration hours input
    - Clear expiration option
  - [x] 5.4 Implement bulk actions
    - Extend expiration for selected stories
    - Remove expiration for selected stories
  - [x] 5.5 Create admin assets
    - `assets/css/admin.css` for admin styles
    - `assets/js/admin.js` for admin functionality

## Phase 4: REST API

- [x] 6. Implement REST API endpoints
  - [x] 6.1 Create `includes/class-story-api.php`
    - Define API namespace 'wp-stories/v1'
    - Register routes on rest_api_init
  - [x] 6.2 Implement GET /stories endpoint
    - Pagination support (per_page, page)
    - Include/exclude expired filter
    - Orderby and order parameters
    - Response with stories array and metadata
  - [x] 6.3 Implement GET /stories/{id} endpoint
    - Validate story ID
    - Check post type and status
    - Check expiration
    - Return full story data with media items
  - [x] 6.4 Implement POST /stories/{id}/view endpoint
    - CSRF protection with nonce
    - Track story views
  - [x] 6.5 Implement error handling
    - Comprehensive error logging
    - Appropriate HTTP status codes
    - User-friendly error messages

## Phase 5: Frontend Display

- [x] 7. Implement frontend rendering
  - [x] 7.1 Create `includes/class-story-frontend.php`
    - Conditional asset loading
    - Story circles rendering
    - Modal template inclusion
  - [x] 7.2 Implement story circles display
    - Responsive sizing
    - Gradient border styling
    - Title and subtitle display
  - [x] 7.3 Create modal template
    - `templates/story-modal.php`
    - Progress bars
    - Navigation controls
    - Media container
  - [x] 7.4 Create frontend assets
    - `assets/css/frontend.css` for circle styles
    - `assets/css/modal.css` for modal styles
    - `assets/js/frontend-minimal.js` for modal functionality

- [x] 8. Implement modal viewer functionality
  - [x] 8.1 Story circle click handling
    - Load story data via REST API
    - Open modal with story content
  - [x] 8.2 Media navigation
    - Previous/next media items
    - Progress bar updates
    - Auto-advance timer for images
  - [x] 8.3 Touch gesture support
    - Horizontal swipe for media navigation
    - Vertical swipe for story navigation
    - Tap areas for navigation
  - [x] 8.4 Video controls
    - Play/pause functionality
    - Volume toggle with global state
    - Progress synchronization
  - [x] 8.5 Keyboard navigation
    - Arrow keys for navigation
    - Escape to close modal

## Phase 6: Gutenberg Block

- [x] 9. Implement Gutenberg block
  - [x] 9.1 Create block structure
    - `blocks/stories-block/index.js`
    - `blocks/stories-block/editor.css`
    - `blocks/stories-block/style.css`
  - [x] 9.2 Register block with WordPress
    - Block name: 'wp-stories/stories-block'
    - Editor script and styles
    - Frontend styles
    - Render callback
  - [x] 9.3 Implement block editor interface
    - Story selection
    - Alignment options
  - [x] 9.4 Implement server-side rendering
    - Render callback in WP_Stories_Plugin
    - Error handling with try-catch

## Phase 7: Testing

- [x] 10. Set up testing infrastructure
  - [x] 10.1 Configure PHPUnit
    - Create `phpunit.xml.dist`
    - Create `tests/bootstrap.php`
    - Configure test suites (Unit, Integration)
  - [x] 10.2 Set up Composer dependencies
    - PHPUnit ^9.6
    - Brain Monkey ^2.6 for WordPress mocking

- [x] 11. Implement unit tests
  - [x] 11.1 Create `tests/Unit/StoryPostTypeTest.php`
    - Test post type registration
    - Test meta data handling
  - [x] 11.2 Create `tests/Unit/StoryFrontendRenderTest.php`
    - Test frontend rendering
    - Test conditional loading

## Phase 8: Plugin Renaming (Completed)

- [x] 12. Rename plugin from Mega Stories to Ghost Stories
  - [x] 12.1 Rename main plugin file
    - `mega-stories.php` → `ghost-stories.php`
  - [x] 12.2 Update all constants
    - `MEGA_STORIES_*` → `GHOST_STORIES_*`
  - [x] 12.3 Update all function names
    - `mega_stories_*` → `ghost_stories_*`
  - [x] 12.4 Update text domain
    - `mega-stories` → `ghost-stories`
  - [x] 12.5 Update option names
    - `mega_stories_*` → `ghost_stories_*`
  - [x] 12.6 Update console log prefixes
    - `[Mega Stories]` → `[Ghost Stories]`
  - [x] 12.7 Update API headers
    - `X-Mega-Stories-Version` → `X-Ghost-Stories-Version`
  - [x] 12.8 Update composer.json package name
  - [x] 12.9 Update documentation (README.md, steering files)

## Phase 9: Documentation

- [x] 13. Create documentation
  - [x] 13.1 Create README.md
    - Plugin description
    - Installation instructions
    - Usage guide
    - REST API documentation
    - Hooks and filters
    - Troubleshooting
  - [x] 13.2 Create readme.txt for WordPress.org
    - Plugin description
    - Installation
    - FAQ
    - Changelog
  - [x] 13.3 Create steering documentation
    - `product.md` - Product overview
    - `structure.md` - Project structure
    - `tech.md` - Technical stack

## Summary

All implementation tasks have been completed. The Ghost Stories plugin is fully functional with:

- ✅ Custom post type for story management
- ✅ Native WordPress admin interface with metaboxes
- ✅ REST API for frontend data access
- ✅ Gutenberg block for story embedding
- ✅ Mobile-first modal viewer with touch gestures
- ✅ Story expiration system with caching
- ✅ Conditional asset loading for performance
- ✅ Comprehensive error handling
- ✅ Unit test infrastructure
- ✅ Complete documentation
