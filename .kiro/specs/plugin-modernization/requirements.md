# Requirements Document

## Introduction

This document specifies the requirements for the Ghost Stories WordPress plugin. Ghost Stories is a lightweight plugin that provides Instagram-like story functionality for WordPress websites.

The plugin delivers:
1. Custom post type for story management
2. Native WordPress admin interface
3. REST API for frontend data access
4. Gutenberg block for story embedding
5. Mobile-first modal viewer with touch gestures
6. Story expiration system

## Glossary

- **Ghost Stories Plugin**: WordPress plugin that provides Instagram-like story functionality
- **Story**: A single story post containing one or more media items (images/videos) displayed in a modal viewer
- **Story Circle**: The circular thumbnail displayed on the frontend that users click to view a story
- **Story Item**: An individual media slide within a story (image or video)
- **Story Timer/Expiration**: Feature that automatically hides stories after a configured duration
- **Gutenberg Block**: The native WordPress block editor component for embedding stories
- **Modal Viewer**: Full-screen overlay that displays story content with navigation controls

## Requirements

### Requirement 1: Custom Post Type

**User Story:** As a content creator, I want to create and manage stories through the WordPress admin, so that I can publish engaging visual content.

#### Acceptance Criteria

1. WHEN the plugin is activated THEN the plugin SHALL register a `wp_story` custom post type
2. WHEN a user creates a new Story THEN the plugin SHALL allow adding a title
3. WHEN a user edits a Story THEN the plugin SHALL display a metabox for managing media items
4. WHEN a user saves a Story THEN the plugin SHALL store media IDs in post meta
5. WHEN a Story is published THEN the plugin SHALL set a creation timestamp
6. WHEN the plugin registers the post type THEN it SHALL enable REST API support

### Requirement 2: Media Management

**User Story:** As a content creator, I want to add multiple images and videos to a story, so that I can create engaging multi-slide content.

#### Acceptance Criteria

1. WHEN a user clicks "Add Media" THEN the plugin SHALL open the WordPress media library
2. WHEN a user selects media THEN the plugin SHALL add the media to the story
3. WHEN a user drags media items THEN the plugin SHALL allow reordering via drag-and-drop
4. WHEN a user clicks remove on a media item THEN the plugin SHALL remove it from the story
5. WHEN media is saved THEN the plugin SHALL store an array of attachment IDs

### Requirement 3: Story Expiration System

**User Story:** As a site administrator, I want stories to automatically expire after a configured time, so that content remains fresh and relevant.

#### Acceptance Criteria

1. WHEN a user sets expiration hours THEN the plugin SHALL store the value in post meta
2. WHEN a story's age exceeds expiration hours THEN the plugin SHALL consider it expired
3. WHEN checking expiration THEN the plugin SHALL use cached results for performance
4. WHEN the cleanup cron runs THEN the plugin SHALL move expired stories to draft status
5. WHEN no expiration is set THEN the plugin SHALL treat the story as never expiring

### Requirement 4: REST API

**User Story:** As a frontend developer, I want to access story data via REST API, so that I can build dynamic story experiences.

#### Acceptance Criteria

1. WHEN a GET request is made to /wp-stories/v1/stories THEN the plugin SHALL return active stories
2. WHEN a GET request is made to /wp-stories/v1/stories/{id} THEN the plugin SHALL return the specific story
3. WHEN a story is expired THEN the API SHALL return a 410 Gone status
4. WHEN a story is not found THEN the API SHALL return a 404 Not Found status
5. WHEN the API returns stories THEN it SHALL include media items with URLs and types
6. WHEN the API returns a response THEN it SHALL include appropriate cache headers

### Requirement 5: Frontend Display

**User Story:** As a website visitor, I want to view stories in an engaging modal viewer, so that I can consume content in an immersive way.

#### Acceptance Criteria

1. WHEN stories are displayed THEN the plugin SHALL render story circles with gradient borders
2. WHEN a user clicks a story circle THEN the plugin SHALL open a full-screen modal
3. WHEN the modal opens THEN the plugin SHALL load story data via REST API
4. WHEN media is displayed THEN the plugin SHALL show progress bars for each item
5. WHEN an image is displayed THEN the plugin SHALL auto-advance after 5 seconds
6. WHEN a video is displayed THEN the plugin SHALL sync progress with video duration

### Requirement 6: Navigation

**User Story:** As a website visitor, I want to navigate through story content easily, so that I can view all media items.

#### Acceptance Criteria

1. WHEN a user swipes left/right THEN the plugin SHALL navigate between media items
2. WHEN a user swipes up/down THEN the plugin SHALL navigate between stories
3. WHEN a user taps left/right areas THEN the plugin SHALL navigate between media items
4. WHEN a user presses arrow keys THEN the plugin SHALL navigate accordingly
5. WHEN a user presses Escape THEN the plugin SHALL close the modal
6. WHEN a user clicks the close button THEN the plugin SHALL close the modal

### Requirement 7: Video Controls

**User Story:** As a website visitor, I want to control video playback, so that I can watch videos at my own pace.

#### Acceptance Criteria

1. WHEN a video loads THEN the plugin SHALL start it muted by default
2. WHEN a user clicks play/pause THEN the plugin SHALL toggle video playback
3. WHEN a user clicks the volume button THEN the plugin SHALL toggle mute state
4. WHEN mute state changes THEN the plugin SHALL persist it across videos in the session
5. WHEN a video ends THEN the plugin SHALL advance to the next media item

### Requirement 8: Gutenberg Block

**User Story:** As a content editor, I want to embed stories using the block editor, so that I can easily add stories to pages and posts.

#### Acceptance Criteria

1. WHEN the block editor loads THEN the plugin SHALL register the stories block
2. WHEN a user inserts the block THEN the plugin SHALL allow selecting stories
3. WHEN a user configures the block THEN the plugin SHALL allow setting alignment
4. WHEN the page renders THEN the plugin SHALL display the selected story circles
5. WHEN no stories are selected THEN the block SHALL render empty

### Requirement 9: Performance

**User Story:** As a site administrator, I want the plugin to be performant, so that it doesn't slow down my website.

#### Acceptance Criteria

1. WHEN a page has no stories THEN the plugin SHALL NOT load frontend assets
2. WHEN checking expiration THEN the plugin SHALL use transient caching
3. WHEN loading images THEN the plugin SHALL support lazy loading
4. WHEN the API responds THEN it SHALL include cache headers
5. WHEN assets are enqueued THEN they SHALL include version numbers for cache busting

### Requirement 10: Security

**User Story:** As a site administrator, I want the plugin to be secure, so that my website is protected.

#### Acceptance Criteria

1. WHEN saving metabox data THEN the plugin SHALL verify nonces
2. WHEN saving data THEN the plugin SHALL check user capabilities
3. WHEN outputting data THEN the plugin SHALL escape all values
4. WHEN accepting input THEN the plugin SHALL sanitize all values
5. WHEN the view endpoint is called THEN it SHALL require a valid nonce

### Requirement 11: WordPress Compatibility

**User Story:** As a site administrator, I want the plugin to work with modern WordPress, so that I can keep my site updated.

#### Acceptance Criteria

1. WHEN activated on WordPress 5.0+ THEN the plugin SHALL function without errors
2. WHEN activated with PHP 7.4+ THEN the plugin SHALL function without errors
3. WHEN using WordPress APIs THEN the plugin SHALL use non-deprecated functions
4. WHEN registering blocks THEN the plugin SHALL use the current Block API
5. WHEN enqueuing scripts THEN the plugin SHALL declare proper dependencies

### Requirement 12: Accessibility

**User Story:** As a website visitor using assistive technology, I want to access story content, so that I can enjoy the same experience as other users.

#### Acceptance Criteria

1. WHEN the modal opens THEN it SHALL have proper ARIA attributes
2. WHEN navigation buttons are rendered THEN they SHALL have ARIA labels
3. WHEN using keyboard THEN all controls SHALL be accessible
4. WHEN the modal is open THEN focus SHALL be trapped within it
5. WHEN the modal closes THEN focus SHALL return to the trigger element
