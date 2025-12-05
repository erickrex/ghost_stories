# Design Document: Ghost Stories Plugin

## Overview

Ghost Stories is a lightweight WordPress plugin that provides Instagram-like story functionality. The plugin enables content creators to publish ephemeral, engaging visual content with an immersive modal viewing experience.

The plugin is built with:
1. Native WordPress APIs (no third-party framework dependencies)
2. Custom Post Type for story management
3. REST API for frontend data access
4. Gutenberg block for story embedding
5. Mobile-first modal viewer with touch gestures

## Architecture

### High-Level Architecture

```
Ghost Stories Plugin
├── Plugin Bootstrap (ghost-stories.php)
│   ├── Constants definition
│   ├── Activation/Deactivation hooks
│   ├── Scheduled events management
│   └── Default options setup
│
├── Core Classes (includes/)
│   ├── WP_Stories_Plugin (Singleton main class)
│   ├── Story_Post_Type (Custom post type registration)
│   ├── Story_Admin (Admin interface and metaboxes)
│   ├── Story_API (REST API endpoints)
│   ├── Story_Frontend (Frontend rendering)
│   ├── WP_Story (Story model/entity)
│   ├── Story_Media_Item (Media item model)
│   └── Story_Collection (Story collection management)
│
├── Gutenberg Block (blocks/stories-block/)
│   ├── index.js (Block registration)
│   ├── editor.css (Editor styles)
│   └── style.css (Frontend styles)
│
├── Assets (assets/)
│   ├── css/ (admin.css, frontend.css, modal.css)
│   ├── js/ (admin.js, frontend-minimal.js)
│   └── images/ (video-placeholder.svg)
│
└── Templates (templates/)
    └── story-modal.php (Modal HTML structure)
```

### File Structure

```
ghost-stories/
├── assets/
│   ├── css/
│   │   ├── admin.css           # Admin interface styles
│   │   ├── frontend.css        # Story circles styles
│   │   └── modal.css           # Modal viewer styles
│   ├── images/
│   │   └── video-placeholder.svg
│   └── js/
│       ├── admin.js            # Admin functionality
│       └── frontend-minimal.js # Modal viewer, navigation, touch gestures
├── blocks/
│   └── stories-block/
│       ├── index.js            # Block registration (vanilla JS)
│       ├── editor.css          # Block editor styles
│       └── style.css           # Block frontend styles
├── includes/
│   ├── class-wp-stories-plugin.php    # Main plugin class (singleton)
│   ├── class-story-post-type.php      # Custom post type registration
│   ├── class-story-admin.php          # Admin interface and metaboxes
│   ├── class-story-api.php            # REST API endpoints
│   ├── class-story-frontend.php       # Frontend rendering
│   ├── class-wp-story.php             # Story model
│   ├── class-story-media-item.php     # Media item model
│   └── class-story-collection.php     # Story collection management
├── templates/
│   └── story-modal.php         # Modal HTML template
├── tests/
│   ├── Unit/                   # Unit tests
│   ├── Integration/            # Integration tests
│   └── bootstrap.php           # Test bootstrap
├── ghost-stories.php           # Main plugin file
├── uninstall.php              # Cleanup on uninstall
├── composer.json              # PHP dependencies
└── phpunit.xml.dist           # PHPUnit configuration
```

## Components and Interfaces

### 1. Main Plugin Class (`WP_Stories_Plugin`)

Singleton pattern class that initializes all plugin components.

**Responsibilities:**
- Define plugin constants
- Include dependencies
- Initialize hooks
- Initialize components (post_type, admin, api, frontend)
- Register Gutenberg blocks
- Manage cache clearing

### 2. Custom Post Type (`Story_Post_Type`)

Registers and manages the `wp_story` custom post type.

**Meta Keys:**
- `_story_media_ids` - Array of attachment IDs
- `_story_expiration_hours` - Hours until expiration
- `_story_created_timestamp` - Creation timestamp

**Features:**
- Expiration system with caching
- Scheduled cleanup of expired stories
- REST API query filtering

### 3. Story Admin (`Story_Admin`)

Handles admin interface including metaboxes for story management.

**Features:**
- Media management metabox
- Expiration settings metabox
- Drag-and-drop media reordering
- Bulk actions for expiration management

### 4. REST API (`Story_API`)

Provides REST endpoints for frontend data access.

**Endpoints:**
- `GET /wp-json/wp-stories/v1/stories` - Get active stories
- `GET /wp-json/wp-stories/v1/stories/{id}` - Get specific story
- `POST /wp-json/wp-stories/v1/stories/{id}/view` - Track story views

**Features:**
- Comprehensive error handling
- Cache headers
- Input validation and sanitization

### 5. Story Frontend (`Story_Frontend`)

Handles frontend rendering of story circles and modal.

**Features:**
- Conditional asset loading
- Responsive image handling
- Video thumbnail generation
- Modal template rendering

### 6. Data Models

**WP_Story:**
- Story entity with media items
- Expiration checking
- Valid media filtering

**Story_Media_Item:**
- Media item with type detection
- Responsive URL generation
- Thumbnail handling

**Story_Collection:**
- Collection of stories
- Filtering and sorting
- Expiration filtering

## Data Models

### Story Post Meta Structure

```php
// Meta key: _story_media_ids
$media_ids = [123, 456, 789]; // Array of attachment IDs

// Meta key: _story_expiration_hours
$expiration_hours = 24; // Integer, hours until expiration

// Meta key: _story_created_timestamp
$created_timestamp = 1701792000; // Unix timestamp
```

### Plugin Options

```php
// Option: ghost_stories_default_expiration
$default_expiration = 24; // Default 24 hours

// Option: ghost_stories_max_media_per_story
$max_media = 10;

// Option: ghost_stories_auto_cleanup_expired
$auto_cleanup = true;

// Option: ghost_stories_cache_duration
$cache_duration = 3600; // 1 hour
```

## Frontend Architecture

### Story Circles Display
- Rendered via `Story_Frontend::render_stories()`
- CSS gradient borders for Instagram-like appearance
- Responsive sizing (90px mobile, 100px tablet, 120px desktop)

### Modal Viewer
- Full-screen modal with touch gestures
- Progress bars synchronized with media duration
- Video controls (play/pause, volume)
- Navigation (swipe, tap, keyboard arrows)

### JavaScript Architecture
- jQuery-based for WordPress compatibility
- Event-driven navigation
- Touch gesture handling
- Video progress synchronization

## Correctness Properties

### Property 1: Story Expiration Logic
*For any* story with a creation timestamp and expiration hours, the `is_story_expired()` function returns true if and only if current time exceeds creation time plus configured duration.

### Property 2: Media Validation
*For any* story, `has_valid_media()` returns true if and only if at least one media item exists with a valid attachment.

### Property 3: Cache Consistency
*For any* story modification, all related caches are cleared to ensure data consistency.

### Property 4: REST API Response Format
*For any* valid story request, the API returns a consistent JSON structure with all required fields.

### Property 5: Conditional Asset Loading
*For any* page without stories, frontend assets are not loaded to optimize performance.

## Error Handling

### API Error Responses
- 400: Invalid request parameters
- 404: Story not found
- 410: Story expired
- 500: Server error

### Logging
- Errors logged via `error_log()`
- Debug mode available via `WP_DEBUG`

## Testing Strategy

### Unit Tests
- Story model tests
- Media item tests
- Expiration logic tests
- Post type registration tests

### Integration Tests
- REST API endpoint tests
- Frontend rendering tests
- Block registration tests

### Test Framework
- PHPUnit 9.6
- Brain Monkey for WordPress mocking
