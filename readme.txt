=== WP Stories Plugin ===
Contributors: yourname
Tags: stories, instagram, social media, gutenberg, mobile
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and display Instagram-like Stories on your WordPress website with an intuitive admin interface and responsive frontend experience.

== Description ==

WP Stories Plugin brings the popular Instagram Stories experience to your WordPress website. Create engaging story collections with images and videos, display them as attractive story circles, and provide your visitors with an immersive mobile-first viewing experience.

= Key Features =

* **Easy Story Creation**: Upload images and videos through the familiar WordPress media library
* **Gutenberg Integration**: Add Stories blocks anywhere on your site using the block editor
* **Mobile-First Design**: Optimized for mobile devices with touch gestures and responsive design
* **Automatic Expiration**: Set stories to automatically expire after a specified number of hours
* **Performance Optimized**: Conditional asset loading and optimized for low-end devices
* **Accessibility Ready**: Full keyboard navigation, screen reader support, and WCAG compliance
* **Cross-Browser Compatible**: Works on all modern browsers and devices

= How It Works =

1. **Create Stories**: Go to the WordPress admin and create new stories by uploading multiple images or videos
2. **Set Expiration**: Optionally set how many hours the story should remain visible
3. **Add to Pages**: Use the Stories Gutenberg block to display story circles on any page or post
4. **Engage Visitors**: Visitors can tap story circles to view content in a full-screen modal with automatic progression

= Perfect For =

* News websites showcasing breaking news
* E-commerce sites highlighting products
* Blogs sharing behind-the-scenes content
* Event websites promoting upcoming events
* Any site wanting to increase user engagement

= Technical Features =

* **Mobile-First**: Touch gestures, swipe navigation, and hold-to-pause functionality
* **Performance**: Lazy loading, conditional asset loading, and memory optimization
* **Security**: Proper sanitization, nonce verification, and capability checks
* **Accessibility**: ARIA labels, keyboard navigation, and screen reader support
* **SEO Friendly**: Proper semantic markup and meta data

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Go to Plugins > Add New
3. Search for "WP Stories Plugin"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin zip file
2. Log in to your WordPress admin panel
3. Go to Plugins > Add New > Upload Plugin
4. Choose the zip file and click "Install Now"
5. Activate the plugin

= After Installation =

1. Go to Stories in your WordPress admin to create your first story
2. Upload images or videos and set expiration if desired
3. Add a Stories block to any page or post using the Gutenberg editor
4. Select which stories to display in the block
5. Publish and view your stories on the frontend

== Frequently Asked Questions ==

= How do I create a story? =

Go to Stories in your WordPress admin panel, click "Add New Story", give it a title, and upload your images or videos using the media uploader. You can reorder media items by dragging and dropping.

= Can I set stories to expire automatically? =

Yes! When creating or editing a story, you can set the number of hours after which the story will automatically disappear from your website.

= How do I add stories to my pages? =

Use the Stories Gutenberg block in the block editor. You can select multiple stories to display as circles, and visitors can tap them to view the content.

= Are stories mobile-friendly? =

Absolutely! The plugin is designed mobile-first with touch gestures, swipe navigation, and optimized performance for mobile devices.

= Can visitors navigate between stories? =

Yes, visitors can use touch gestures (swipe), mouse clicks, or keyboard arrows to navigate between media items and stories. They can also hold to pause automatic progression.

= What media types are supported? =

The plugin supports all media types that WordPress supports, including images (JPEG, PNG, GIF) and videos (MP4, WebM, etc.).

= Is the plugin accessible? =

Yes, the plugin includes full accessibility features including keyboard navigation, screen reader support, ARIA labels, and WCAG 2.1 AA compliance.

= Does it work with my theme? =

The plugin is designed to work with any properly coded WordPress theme. It uses standard WordPress hooks and follows WordPress coding standards.

= Can I customize the appearance? =

The plugin includes CSS custom properties that make it easy to customize colors, sizes, and animations to match your theme.

= Is it performance optimized? =

Yes, the plugin includes conditional asset loading, lazy loading for media, memory optimization, and special optimizations for low-end devices.

== Screenshots ==

1. Story creation interface in WordPress admin
2. Stories block in Gutenberg editor with story selection
3. Story circles displayed on the frontend
4. Mobile story viewer with touch controls
5. Story progress indicators and navigation
6. Admin story management with expiration settings

== Changelog ==

= 1.1.0 =
* Fixed: Critical TypeError in REST API when sanitizing story data
* Fixed: Modal visibility issue - stories now display correctly when clicked
* Improved: Type checking for URL sanitization to prevent fatal errors
* Improved: Modal CSS forced visibility for better compatibility
* Enhanced: Error handling in story API responses

= 1.0.0 =
* Initial release
* Story creation and management interface
* Gutenberg Stories block
* Mobile-first story viewer with touch gestures
* Automatic story expiration
* Performance optimizations for mobile devices
* Full accessibility support
* Cross-browser compatibility
* Comprehensive security measures

== Upgrade Notice ==

= 1.0.0 =
Initial release of WP Stories Plugin. Create engaging Instagram-like stories on your WordPress website.

== Technical Details ==

= Minimum Requirements =
* WordPress 5.0 or higher
* PHP 7.4 or higher
* Modern web browser with JavaScript enabled

= Browser Support =
* Chrome 60+
* Firefox 55+
* Safari 10+
* Edge 16+
* iOS Safari 10+
* Android Chrome 60+

= Performance =
* Conditional asset loading (CSS/JS only load when stories are present)
* Lazy loading for all media content
* Memory optimization for low-end devices
* Minified and optimized assets
* Caching for database queries

= Security =
* All inputs sanitized and validated
* All outputs properly escaped
* Nonce verification for all forms
* Capability checks for admin operations
* No external dependencies or API calls

= Accessibility =
* WCAG 2.1 AA compliant
* Full keyboard navigation support
* Screen reader compatible
* High contrast mode support
* Reduced motion support for users with vestibular disorders

== Developer Information ==

= Hooks and Filters =

The plugin provides several hooks for developers:

* `wp_stories_plugin_init` - Fired when plugin initializes
* `wp_stories_before_story_save` - Before saving a story
* `wp_stories_after_story_save` - After saving a story
* `wp_stories_story_expired` - When a story expires
* `wp_stories_modal_content` - Filter modal content
* `wp_stories_circle_html` - Filter story circle HTML

= Custom Post Type =

Stories are stored as a custom post type `wp_story` with the following meta fields:
* `_story_media_ids` - Array of attachment IDs
* `_story_expiration_hours` - Hours until expiration
* `_story_created_timestamp` - Creation timestamp

= REST API =

The plugin provides REST API endpoints:
* `GET /wp-json/wp-stories/v1/stories` - Get active stories
* `GET /wp-json/wp-stories/v1/stories/{id}` - Get specific story

= Contributing =

The plugin is open source and welcomes contributions. Please follow WordPress coding standards and include tests for any new features.

== Support ==

For support, please use the WordPress.org support forums. For bug reports and feature requests, please provide detailed information about your environment and steps to reproduce any issues.

== Privacy ==

This plugin does not collect, store, or transmit any personal data. All story content is stored locally in your WordPress database. No external services are used.