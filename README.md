# Ghost Stories (WP Stories Plugin)

Create and display Instagram-style stories on your WordPress site with an intuitive admin interface, optimized frontend, and gesture navigation.

## Description

Ghost Stories brings the "Stories" format to WordPress. Create engaging, ephemeral, and navigable content with a modern modal viewer. Ideal for news sites, blogs, e-commerce, and any website that wants to share stories in an impactful way.

### Features

- **Instagram-style Stories**: Modal viewer with touch navigation
- **Media Management**: Upload images and videos with drag-and-drop reordering
- **Expiration System**: Stories that automatically disappear
- **Gutenberg Block**: Insert stories on any page or post
- **REST API**: Endpoints for headless integrations
- **Responsive**: Desktop, tablet, and mobile
- **Touch Gestures**: Horizontal swipe (media) and vertical swipe (stories)
- **Keyboard Navigation**: Arrow keys and ESC on desktop
- **Performance Optimization**: Conditional loading, lazy load, and caching
- **Accessibility**: ARIA labels and accessible focus
- **Title and Subtitle**: Dedicated fields displayed under each circle
- **Video Controls**: Play/Pause, volume, synchronized progress bar

## Installation

### From the WordPress Admin

1. Download the plugin ZIP file
2. Go to **Plugins → Add New** in your WordPress admin
3. Click **Upload Plugin** and choose the ZIP file
4. Click **Install Now**
5. After installation, click **Activate Plugin**

### Manual Installation

1. Upload the `wp-stories-plugin` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress

### Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- PHP extensions: json, mbstring, gd

## Usage

### Creating Stories

1. Go to **Stories → Add New Story** in your WordPress admin
2. Enter a story title
3. Click **Add Media** to upload images or videos
4. Drag and drop to reorder media items
5. Set expiration time (optional)
6. Click **Publish**

### Displaying Stories

#### Using the Gutenberg Block

1. Edit any page or post
2. Add the **Stories** block
3. Select which stories to display
4. Choose alignment (left, center, right)
5. Publish your page

#### Using Shortcode

```
[wp_stories]
```

Display specific stories:
```
[wp_stories ids="1,2,3"]
```

#### Using Template Tag (PHP)

```php
<?php
if (function_exists('wp_stories_display')) {
    wp_stories_display();
}
?>
```

Display specific stories:
```php
<?php
if (function_exists('wp_stories_display')) {
    wp_stories_display(array(
        'story_ids' => array(1, 2, 3),
        'alignment' => 'center'
    ));
}
?>
```

### Story Settings

#### Expiration Hours
Defines how many hours a story remains visible. When it expires, it is automatically hidden.

- Leave empty for no expiration
- Recommended range: 1–168 hours (1 hour to 7 days)
- Default: 24 hours

#### Media Management

- **Add Media**: Upload new images/videos or select from media library
- **Reorder**: Drag and drop to change media order
- **Remove**: Click remove button to delete media from story
- **Edit**: Click edit to modify media details

### Bulk Actions

Select multiple stories and:
- **Extend Expiration**: Add 24 hours to expiration time
- **Remove Expiration**: Make stories permanent

## REST API

The plugin provides a REST API for accessing stories programmatically.

### Endpoints

#### Get All Stories
```
GET /wp-json/wp-stories/v1/stories
```

Parameters:
- `per_page` (int): Stories per page (default: 10, max: 100)
- `page` (int): Page number (default: 1)
- `include_expired` (bool): Include expired stories (default: false)
- `orderby` (string): Sort by date, title, or expiration (default: date)
- `order` (string): asc or desc (default: desc)

#### Get a Story
```
GET /wp-json/wp-stories/v1/stories/{id}
```

#### Register Story View
```
POST /wp-json/wp-stories/v1/stories/{id}/view
```

Requires nonce for CSRF protection.

### Examples

```javascript
// Fetch active stories
fetch('/wp-json/wp-stories/v1/stories')
    .then(response => response.json())
    .then(data => {
        console.log(data.stories);
    });

// Get specific story
fetch('/wp-json/wp-stories/v1/stories/123')
    .then(response => response.json())
    .then(story => {
        console.log(story.title);
        console.log(story.media_items);
    });
```

## File Structure

```
wp-stories-plugin/
├── assets/
│   ├── css/
│   │   ├── admin.css          # Admin interface styles
│   │   └── frontend.css       # Frontend modal styles
│   ├── images/
│   │   └── video-placeholder.svg
│   └── js/
│       ├── admin.js           # Admin functionality
│       └── frontend.js        # Frontend modal viewer
├── blocks/
│   └── stories-block/
│       ├── block.json         # Block configuration
│       ├── edit.js            # Block editor component
│       ├── editor.css         # Block editor styles
│       ├── index.js           # Block registration
│       ├── save.js            # Block save function
│       └── style.css          # Block frontend styles
├── includes/
│   ├── class-story-admin.php       # Admin interface
│   ├── class-story-api.php         # REST API endpoints
│   ├── class-story-collection.php  # Story collection management
│   ├── class-story-frontend.php    # Frontend display
│   ├── class-story-media-item.php  # Media item model
│   ├── class-story-post-type.php   # Custom post type
│   ├── class-wp-stories-plugin.php # Main plugin class
│   └── class-wp-story.php          # Story model
├── templates/
│   └── story-modal.php        # Modal template
├── readme.txt                 # WordPress.org readme
├── uninstall.php             # Cleanup on uninstall
└── wp-stories-plugin.php     # Main plugin file
```

## Hooks & Filters

### Actions

```php
// Fired when plugin is initialized
do_action('wp_stories_plugin_init');

// Fired when a story is viewed
do_action('wp_stories_story_viewed', $story_id);

// Fired when a story expires
do_action('wp_stories_story_expired', $story_id);
```

### Filters

```php
// Modify default expiration hours
add_filter('wp_stories_default_expiration', function($hours) {
    return 48; // 48 hours instead of 24
});

// Modify maximum media items per story
add_filter('wp_stories_max_media_items', function($max) {
    return 20; // 20 items instead of 10
});

// Modify allowed media types
add_filter('wp_stories_allowed_media_types', function($types) {
    return array('image/jpeg', 'image/png', 'video/mp4');
});
```

## Frontend Controls

### Desktop
- **Click left/right**: Navigate between media items
- **Arrow keys**: Navigate between media items
- **ESC key**: Close modal
- **Click X**: Close modal

### Mobile/Touch
- **Swipe left/right**: Navigate between media items
- **Tap left/right**: Navigate between media items
- **Tap X**: Close modal
- **Swipe down**: Close modal

### Progress Indicators
- Progress bars show current position in story
- Auto-advance after 5 seconds per media item
- Hold/touch to pause auto-advance

## Browser Compatibility

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile Safari (iOS 12+)
- Chrome Mobile (Android 8+)

## Performance

The plugin is optimized for performance:

- **Conditional Loading**: Assets only load when stories are present
- **Lazy Loading**: Images load as needed
- **Caching**: Story data is cached for faster retrieval
- **Optimized Queries**: Efficient database queries
- **Minification Ready**: Assets can be minified for production

## Security

Security features include:

- **Nonce Verification**: All AJAX requests verified
- **Capability Checks**: User permissions validated
- **Input Sanitization**: All user input sanitized
- **Output Escaping**: All output properly escaped
- **CSRF Protection**: Cross-site request forgery prevention
- **SQL Injection Prevention**: Prepared statements used
- **File Upload Validation**: Media files validated before upload

## Troubleshooting

### Stories Not Displaying

1. Check that stories are published (not draft)
2. Verify stories haven't expired
3. Clear WordPress cache
4. Check browser console for JavaScript errors

### Media Upload Issues

1. Check PHP upload_max_filesize setting
2. Verify file permissions on wp-content/uploads
3. Check allowed file types in WordPress settings
4. Review PHP error log for upload errors

### Modal Not Opening

1. Check for JavaScript conflicts with other plugins
2. Verify jQuery is loaded
3. Check browser console for errors
4. Try disabling other plugins temporarily

### Performance Issues

1. Enable WordPress caching plugin
2. Optimize images before upload
3. Limit number of media items per story
4. Use CDN for media files

## Changelog

### 1.0.0
- Initial release
- Instagram-style story viewer
- Admin interface for story management
- Gutenberg block integration
- REST API endpoints
- Expiration system
- Touch gesture support
- Keyboard navigation
- Responsive design

## Support

For support, please:

1. Check the documentation above
2. Review troubleshooting section
3. Check WordPress.org support forums
4. Submit issues on GitHub (if applicable)

## Credits

Developed with ❤️ for the WordPress community.

## License

This plugin is licensed under the GPL v2 or later.

```
Copyright (C) 2024

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## Privacy Policy

This plugin:
- Does not collect any user data
- Does not send data to external servers
- Does not use cookies for tracking
- Stores story view counts locally (optional)
- All data remains on your WordPress installation

## User Guide (Detailed)

For a complete guide with screenshots, best practices, customization, troubleshooting, and frequently asked questions, see: `USER_GUIDE.md`.

### Title and Subtitle Fields on the Frontend

- Displayed one below the other under each circle.
- Title from meta `_story_title` (fallback to `post_title` if not present).
- Subtitle from meta `_story_detail` (optional).
- Default typography: "Playwrite Australia Tasmania" (Google Fonts).
- Truncated with ellipsis and maximum width designed for the circle layout.
