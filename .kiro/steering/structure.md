# Project Structure

## Root Files

- `ghost-stories.php` - Main plugin file with activation/deactivation hooks
- `uninstall.php` - Cleanup logic when plugin is deleted
- `composer.json` - PHP dependencies and autoloading
- `phpunit.xml.dist` - PHPUnit test configuration
- `readme.txt` - WordPress.org plugin readme
- `README.md` - Developer documentation

## Directory Organization

### `/includes/` - Core PHP Classes

All business logic and WordPress integration:

- `class-wp-stories-plugin.php` - Main plugin class (singleton pattern)
- `class-story-post-type.php` - Custom post type registration and management
- `class-story-admin.php` - Admin interface and meta boxes
- `class-story-frontend.php` - Frontend rendering and shortcodes
- `class-story-api.php` - REST API endpoints
- `class-wp-story.php` - Story model/entity
- `class-story-media-item.php` - Media item model
- `class-story-collection.php` - Story collection management

### `/assets/` - Frontend Assets

Static files loaded on frontend:

- `/css/` - Stylesheets
  - `admin.css` - Admin interface styles
  - `frontend.css` - Story circles and modal styles
  - `modal.css` - Modal-specific styles
- `/js/` - JavaScript files
  - `admin.js` - Admin functionality (media management, drag-drop)
  - `frontend-minimal.js` - Modal viewer, navigation, touch gestures
- `/images/` - Static images
  - `video-placeholder.svg` - Video thumbnail fallback

### `/blocks/` - Gutenberg Blocks

Block editor integration:

- `/stories-block/` - Stories block
  - `index.js` - Block registration (vanilla JS, no build)
  - `editor.css` - Block editor styles
  - `style.css` - Block frontend styles

### `/templates/` - PHP Templates

Server-side rendered templates:

- `story-modal.php` - Modal HTML structure

### `/tests/` - Test Suite

PHPUnit tests:

- `bootstrap.php` - Test bootstrap file
- `/Unit/` - Unit tests for individual classes
- `/Integration/` - Integration tests for WordPress features

## Naming Conventions

### PHP Classes
- Prefix: `WP_Stories_` or `Story_`
- Format: `class-{name}.php` (lowercase with hyphens)
- Example: `class-story-post-type.php` → `Story_Post_Type`

### Custom Post Type
- Type: `wp_story`
- Meta keys: `_story_media_ids`, `_story_expiration_hours`, `_story_created_timestamp`

### CSS Classes
- Prefix: `wp-stories-`
- BEM-like naming: `.wp-stories-{block}__{element}--{modifier}`
- Example: `.wp-stories-circle`, `.wp-stories-modal-content`

### JavaScript
- Global object: `wpStories` (localized from PHP)
- Functions: camelCase
- Event handlers: `initialize{Feature}`, `handle{Event}`

### Constants
- Format: `GHOST_STORIES_{NAME}`
- Examples: `GHOST_STORIES_VERSION`, `GHOST_STORIES_PATH`

## File Loading Order

1. `ghost-stories.php` - Defines constants, includes main class
2. `class-wp-stories-plugin.php` - Initializes plugin, loads components
3. Component classes - Loaded via `include_dependencies()`
4. Assets - Conditionally enqueued based on page content

## WordPress Integration Points

- **Custom Post Type**: Registered in `Story_Post_Type::register_post_type()`
- **REST API**: Endpoints in `Story_API` class
- **Gutenberg Block**: Registered in `WP_Stories_Plugin::register_blocks()`
- **Shortcodes**: Registered in `Story_Frontend` class
- **Admin Pages**: Meta boxes in `Story_Admin` class
- **Cron Jobs**: Scheduled in activation hook for story cleanup
