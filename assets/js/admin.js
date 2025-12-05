/**
 * WordPress Stories Plugin - Admin JavaScript
 */

(function($) {
    'use strict';
    
    /**
     * Story Admin functionality
     */
    var WPStoriesAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.initMediaManager();
            this.initDragAndDrop();
            this.bindEvents();
            this.initStoryFieldValidation();
            this.initThumbnailGeneration();
        },
        
        /**
         * Initialize media manager
         */
        initMediaManager: function() {
            var self = this;
            
            // Media upload button
            $('.wp-stories-add-media').on('click', function(e) {
                e.preventDefault();
                self.openMediaLibrary();
            });
            
            // Browse library button
            $('.wp-stories-browse-library').on('click', function(e) {
                e.preventDefault();
                self.openMediaLibraryBrowser();
            });
            
            // Check integrity button
            $('.wp-stories-check-integrity').on('click', function(e) {
                e.preventDefault();
                self.checkMediaIntegrity();
            });
            
            // Remove media button
            $(document).on('click', '.wp-stories-media-remove', function(e) {
                e.preventDefault();
                self.removeMediaItem($(this));
            });
            
            // Edit media button
            $(document).on('click', '.wp-stories-media-edit', function(e) {
                e.preventDefault();
                self.editMediaItem($(this));
            });
        },
        
        /**
         * Initialize drag and drop functionality
         */
        initDragAndDrop: function() {
            var self = this;
            
            $('#wp-stories-media-list').sortable({
                items: '.wp-stories-media-item',
                handle: '.wp-stories-media-handle',
                placeholder: 'ui-sortable-placeholder',
                tolerance: 'pointer',
                cursor: 'move',
                opacity: 0.8,
                start: function(event, ui) {
                    ui.placeholder.attr('data-placeholder-text', wpStoriesAdmin.strings.dragPlaceholder || 'Drop here');
                },
                update: function(event, ui) {
                    self.updateMediaOrder();
                }
            });
        },
        
        /**
         * Bind additional events
         */
        bindEvents: function() {
            var self = this;
            
            // Auto-save media order when changed
            $(document).on('change', '#wp-stories-media-ids', function() {
                self.updateMediaOrder();
            });
            
            // Confirm before removing media
            $(document).on('click', '.wp-stories-media-remove', function(e) {
                if (!confirm(wpStoriesAdmin.strings.confirmRemove || 'Are you sure you want to remove this media item?')) {
                    e.preventDefault();
                    return false;
                }
            });
        },
        
        /**
         * Open WordPress media library
         */
        openMediaLibrary: function() {
            var self = this;
            
            // Create media frame if it doesn't exist
            if (!this.mediaFrame) {
                this.mediaFrame = wp.media({
                    title: wpStoriesAdmin.strings.selectMedia || 'Select Media for Story',
                    button: {
                        text: wpStoriesAdmin.strings.addToStory || 'Add to Story'
                    },
                    multiple: true,
                    library: {
                        type: ['image', 'video']
                    }
                });
                
                // Handle media selection
                this.mediaFrame.on('select', function() {
                    var selection = self.mediaFrame.state().get('selection');
                    selection.each(function(attachment) {
                        self.addMediaItem(attachment.toJSON());
                    });
                });
            }
            
            // Open the media frame
            this.mediaFrame.open();
        },
        
        /**
         * Add media item to the list
         */
        addMediaItem: function(attachment) {
            var self = this;
            var $mediaList = $('#wp-stories-media-list');
            var $noMedia = $('.wp-stories-no-media');
            
            // Remove "no media" message if present
            if ($noMedia.length) {
                $noMedia.remove();
            }
            
            // Create media item HTML
            var mediaHtml = this.createMediaItemHtml(attachment);
            
            // Add to list
            $mediaList.append(mediaHtml);
            
            // Update hidden input
            this.updateMediaOrder();
            
            // Show success message
            this.showMessage(wpStoriesAdmin.strings.mediaAdded || 'Media added successfully', 'success');
        },
        
        /**
         * Create HTML for media item
         */
        createMediaItemHtml: function(attachment) {
            var mediaType = attachment.type;
            var thumbnailUrl = attachment.sizes && attachment.sizes.thumbnail ? 
                attachment.sizes.thumbnail.url : attachment.url;
            
            var html = '<div class="wp-stories-media-item" data-media-id="' + attachment.id + '">';
            html += '<div class="wp-stories-media-thumbnail">';
            
            if (mediaType === 'image') {
                html += '<img src="' + thumbnailUrl + '" alt="' + attachment.title + '">';
            } else {
                html += '<video src="' + attachment.url + '" preload="metadata"></video>';
                html += '<span class="wp-stories-media-type-indicator">Video</span>';
            }
            
            html += '</div>';
            html += '<div class="wp-stories-media-info">';
            html += '<strong>' + attachment.title + '</strong>';
            html += '<span class="wp-stories-media-type">' + mediaType.charAt(0).toUpperCase() + mediaType.slice(1) + '</span>';
            html += '</div>';
            html += '<div class="wp-stories-media-actions">';
            html += '<button type="button" class="button wp-stories-media-edit" data-media-id="' + attachment.id + '">Edit</button>';
            html += '<button type="button" class="button wp-stories-media-remove" data-media-id="' + attachment.id + '">Remove</button>';
            html += '</div>';
            html += '<div class="wp-stories-media-handle">';
            html += '<span class="dashicons dashicons-menu"></span>';
            html += '</div>';
            html += '</div>';
            
            return html;
        },
        
        /**
         * Remove media item
         */
        removeMediaItem: function($button) {
            var $mediaItem = $button.closest('.wp-stories-media-item');
            var $mediaList = $('#wp-stories-media-list');
            
            // Remove the item with animation
            $mediaItem.fadeOut(300, function() {
                $(this).remove();
                
                // Update media order
                WPStoriesAdmin.updateMediaOrder();
                
                // Show "no media" message if list is empty
                if ($mediaList.find('.wp-stories-media-item').length === 0) {
                    $mediaList.html('<div class="wp-stories-no-media"><p>No media added yet. Click "Add Media" to get started.</p></div>');
                }
                
                // Show success message
                WPStoriesAdmin.showMessage(wpStoriesAdmin.strings.mediaRemoved || 'Media removed successfully', 'success');
            });
        },
        
        /**
         * Edit media item
         */
        editMediaItem: function($button) {
            var mediaId = $button.data('media-id');
            var attachment = wp.media.attachment(mediaId);
            
            // Create edit frame
            var editFrame = wp.media({
                title: wpStoriesAdmin.strings.editMedia || 'Edit Media',
                button: {
                    text: wpStoriesAdmin.strings.updateMedia || 'Update Media'
                },
                multiple: false,
                library: {
                    type: ['image', 'video']
                }
            });
            
            // Pre-select the attachment
            editFrame.on('open', function() {
                var selection = editFrame.state().get('selection');
                selection.add(attachment);
            });
            
            // Handle update
            editFrame.on('select', function() {
                var updatedAttachment = editFrame.state().get('selection').first().toJSON();
                WPStoriesAdmin.updateMediaItemDisplay($button.closest('.wp-stories-media-item'), updatedAttachment);
            });
            
            editFrame.open();
        },
        
        /**
         * Update media item display
         */
        updateMediaItemDisplay: function($mediaItem, attachment) {
            var $thumbnail = $mediaItem.find('.wp-stories-media-thumbnail');
            var $info = $mediaItem.find('.wp-stories-media-info');
            var mediaType = attachment.type;
            var thumbnailUrl = attachment.sizes && attachment.sizes.thumbnail ? 
                attachment.sizes.thumbnail.url : attachment.url;
            
            // Update thumbnail
            if (mediaType === 'image') {
                $thumbnail.html('<img src="' + thumbnailUrl + '" alt="' + attachment.title + '">');
            } else {
                $thumbnail.html('<video src="' + attachment.url + '" preload="metadata"></video><span class="wp-stories-media-type-indicator">Video</span>');
            }
            
            // Update info
            $info.find('strong').text(attachment.title);
            $info.find('.wp-stories-media-type').text(mediaType.charAt(0).toUpperCase() + mediaType.slice(1));
            
            this.showMessage(wpStoriesAdmin.strings.mediaUpdated || 'Media updated successfully', 'success');
        },
        
        /**
         * Update media order in hidden input
         */
        updateMediaOrder: function() {
            var mediaIds = [];
            
            $('#wp-stories-media-list .wp-stories-media-item').each(function() {
                var mediaId = $(this).data('media-id');
                if (mediaId) {
                    mediaIds.push(mediaId);
                }
            });
            
            $('#wp-stories-media-ids').val(mediaIds.join(','));
        },
        
        /**
         * Show admin message
         */
        showMessage: function(message, type) {
            type = type || 'success';
            var $message = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Insert after page title
            $('.wrap h1').after($message);
            
            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },
        
        /**
         * Open media library browser modal
         */
        openMediaLibraryBrowser: function() {
            var self = this;
            
            // Create modal HTML
            var modalHtml = this.createMediaLibraryModal();
            $('body').append(modalHtml);
            
            // Initialize modal
            var $modal = $('#wp-stories-media-browser');
            $modal.show();
            
            // Load initial media
            this.loadMediaLibraryPage(1);
            
            // Bind modal events
            this.bindMediaBrowserEvents($modal);
        },
        
        /**
         * Create media library modal HTML
         */
        createMediaLibraryModal: function() {
            var html = '<div id="wp-stories-media-browser" class="wp-stories-modal" style="display:none;">';
            html += '<div class="wp-stories-modal-content">';
            html += '<div class="wp-stories-modal-header">';
            html += '<h2>Browse Media Library</h2>';
            html += '<button type="button" class="wp-stories-modal-close">&times;</button>';
            html += '</div>';
            html += '<div class="wp-stories-modal-body">';
            html += '<div class="wp-stories-media-filters">';
            html += '<input type="text" id="wp-stories-media-search" placeholder="Search media...">';
            html += '<select id="wp-stories-media-type-filter">';
            html += '<option value="">All Types</option>';
            html += '<option value="image">Images</option>';
            html += '<option value="video">Videos</option>';
            html += '</select>';
            html += '<button type="button" class="button" id="wp-stories-media-filter-btn">Filter</button>';
            html += '</div>';
            html += '<div id="wp-stories-media-grid" class="wp-stories-media-grid"></div>';
            html += '<div class="wp-stories-media-pagination"></div>';
            html += '</div>';
            html += '<div class="wp-stories-modal-footer">';
            html += '<button type="button" class="button button-primary" id="wp-stories-add-selected">Add Selected</button>';
            html += '<button type="button" class="button" id="wp-stories-cancel-selection">Cancel</button>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            
            return html;
        },
        
        /**
         * Bind media browser events
         */
        bindMediaBrowserEvents: function($modal) {
            var self = this;
            
            // Close modal
            $modal.find('.wp-stories-modal-close, #wp-stories-cancel-selection').on('click', function() {
                $modal.remove();
            });
            
            // Filter media
            $modal.find('#wp-stories-media-filter-btn').on('click', function() {
                self.loadMediaLibraryPage(1);
            });
            
            // Search on enter
            $modal.find('#wp-stories-media-search').on('keypress', function(e) {
                if (e.which === 13) {
                    self.loadMediaLibraryPage(1);
                }
            });
            
            // Media item selection
            $modal.on('click', '.wp-stories-media-item-browser', function() {
                $(this).toggleClass('selected');
            });
            
            // Add selected media
            $modal.find('#wp-stories-add-selected').on('click', function() {
                self.addSelectedMediaFromBrowser($modal);
            });
            
            // Pagination
            $modal.on('click', '.wp-stories-page-btn', function() {
                var page = $(this).data('page');
                self.loadMediaLibraryPage(page);
            });
        },
        
        /**
         * Load media library page
         */
        loadMediaLibraryPage: function(page) {
            var self = this;
            var $modal = $('#wp-stories-media-browser');
            var $grid = $modal.find('#wp-stories-media-grid');
            
            // Show loading
            $grid.html('<div class="wp-stories-loading">Loading media...</div>');
            
            var data = {
                action: 'wp_stories_get_media_library',
                nonce: wpStoriesAdmin.nonce,
                page: page,
                search: $modal.find('#wp-stories-media-search').val(),
                media_type: $modal.find('#wp-stories-media-type-filter').val()
            };
            
            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    self.renderMediaGrid(response.data);
                } else {
                    $grid.html('<div class="wp-stories-error">Error loading media: ' + response.data + '</div>');
                }
            }).fail(function() {
                $grid.html('<div class="wp-stories-error">Failed to load media library</div>');
            });
        },
        
        /**
         * Render media grid
         */
        renderMediaGrid: function(data) {
            var $modal = $('#wp-stories-media-browser');
            var $grid = $modal.find('#wp-stories-media-grid');
            var $pagination = $modal.find('.wp-stories-media-pagination');
            
            // Clear grid
            $grid.empty();
            
            if (data.media_items.length === 0) {
                $grid.html('<div class="wp-stories-no-media">No media found</div>');
                return;
            }
            
            // Render media items
            data.media_items.forEach(function(item) {
                var itemHtml = '<div class="wp-stories-media-item-browser" data-id="' + item.id + '">';
                itemHtml += '<div class="wp-stories-media-thumbnail">';
                
                if (item.type === 'image') {
                    itemHtml += '<img src="' + item.thumbnail + '" alt="' + item.title + '">';
                } else {
                    itemHtml += '<video src="' + item.url + '" preload="metadata"></video>';
                    itemHtml += '<span class="wp-stories-media-type-indicator">Video</span>';
                }
                
                itemHtml += '</div>';
                itemHtml += '<div class="wp-stories-media-info">';
                itemHtml += '<div class="wp-stories-media-title">' + item.title + '</div>';
                itemHtml += '<div class="wp-stories-media-meta">' + item.size + ' • ' + item.type + '</div>';
                itemHtml += '</div>';
                itemHtml += '<div class="wp-stories-media-select-indicator">✓</div>';
                itemHtml += '</div>';
                
                $grid.append(itemHtml);
            });
            
            // Render pagination
            this.renderPagination($pagination, data);
        },
        
        /**
         * Render pagination
         */
        renderPagination: function($container, data) {
            $container.empty();
            
            if (data.total_pages <= 1) {
                return;
            }
            
            var html = '<div class="wp-stories-pagination">';
            
            // Previous button
            if (data.current_page > 1) {
                html += '<button type="button" class="button wp-stories-page-btn" data-page="' + (data.current_page - 1) + '">Previous</button>';
            }
            
            // Page numbers
            for (var i = 1; i <= data.total_pages; i++) {
                if (i === data.current_page) {
                    html += '<span class="wp-stories-current-page">' + i + '</span>';
                } else {
                    html += '<button type="button" class="button wp-stories-page-btn" data-page="' + i + '">' + i + '</button>';
                }
            }
            
            // Next button
            if (data.current_page < data.total_pages) {
                html += '<button type="button" class="button wp-stories-page-btn" data-page="' + (data.current_page + 1) + '">Next</button>';
            }
            
            html += '</div>';
            $container.html(html);
        },
        
        /**
         * Add selected media from browser
         */
        addSelectedMediaFromBrowser: function($modal) {
            var self = this;
            var selectedIds = [];
            
            $modal.find('.wp-stories-media-item-browser.selected').each(function() {
                selectedIds.push($(this).data('id'));
            });
            
            if (selectedIds.length === 0) {
                this.showMessage('Please select media items to add', 'error');
                return;
            }
            
            var data = {
                action: 'wp_stories_add_existing_media',
                nonce: wpStoriesAdmin.nonce,
                post_id: $('#post_ID').val(),
                media_ids: selectedIds
            };
            
            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    self.showMessage(response.data.message, 'success');
                    
                    // Refresh the page to show added media
                    location.reload();
                } else {
                    self.showMessage('Error adding media: ' + response.data, 'error');
                }
            }).fail(function() {
                self.showMessage('Failed to add media', 'error');
            });
            
            $modal.remove();
        },
        
        /**
         * Check media integrity
         */
        checkMediaIntegrity: function() {
            var self = this;
            var mediaIds = [];
            
            $('#wp-stories-media-list .wp-stories-media-item').each(function() {
                var mediaId = $(this).data('media-id');
                if (mediaId) {
                    mediaIds.push(mediaId);
                }
            });
            
            if (mediaIds.length === 0) {
                this.showMessage('No media to check', 'info');
                return;
            }
            
            // Show loading message
            this.showMessage('Checking media integrity...', 'info');
            
            var data = {
                action: 'wp_stories_check_media_integrity',
                nonce: wpStoriesAdmin.nonce,
                post_id: $('#post_ID').val(),
                media_ids: mediaIds
            };
            
            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    var result = response.data;
                    
                    if (result.invalid.length === 0) {
                        self.showMessage('All media files are valid', 'success');
                    } else {
                        var message = 'Found ' + result.invalid.length + ' invalid media items. ';
                        message += 'Check the console for details.';
                        self.showMessage(message, 'warning');
                        
                        // Log details to console
                        console.warn('Invalid media items:', result.invalid);
                        
                        // Optionally highlight invalid items
                        result.invalid.forEach(function(item) {
                            var $mediaItem = $('.wp-stories-media-item[data-media-id="' + item.id + '"]');
                            $mediaItem.addClass('wp-stories-invalid-media');
                            $mediaItem.attr('title', 'Invalid: ' + item.error);
                        });
                    }
                } else {
                    self.showMessage('Error checking media integrity: ' + response.data, 'error');
                }
            }).fail(function() {
                self.showMessage('Failed to check media integrity', 'error');
            });
        },
        
        /**
         * Initialize thumbnail generation
         */
        initThumbnailGeneration: function() {
            var self = this;
            
            console.log('WP Stories Admin: Initializing thumbnail generation');
            
            // Generate when save/update button is clicked (BEFORE form submission)
            $('#save-post, #publish, input[name="save"]').on('click', function(e) {
                console.log('WP Stories Admin: Save button clicked');
                
                // Only generate if it's not a draft or trash
                var isDraft = $('#save-post').length > 0 && $('#save-post').val() === 'Guardar borrador';
                if (!isDraft) {
                    // Wait for post ID to be set if it's a new post
                    setTimeout(function() {
                        console.log('WP Stories Admin: Triggering thumbnail generation after button click');
                        self.generateThumbnails();
                    }, 200);
                }
            });
            
            // Also trigger after form is actually submitted (for when AJAX save completes)
            $(document).on('ajaxSuccess', function(event, xhr, settings) {
                if (settings.data && settings.data.indexOf('action=editpost') !== -1) {
                    console.log('WP Stories Admin: Post saved via AJAX, generating thumbnail');
                    setTimeout(function() {
                        self.generateThumbnails();
                    }, 1000);
                }
            });
            
            // Also generate when media is added
            var generateTimeout;
            $(document).on('DOMNodeInserted', '#wp-stories-media-list', function() {
                clearTimeout(generateTimeout);
                generateTimeout = setTimeout(function() {
                    console.log('WP Stories Admin: Media added, checking for thumbnail generation');
                    self.generateThumbnails();
                }, 1500);
            });
            
            // Generate on page load if post has media but no featured image
            setTimeout(function() {
                console.log('WP Stories Admin: Page loaded, checking if thumbnail needed');
                self.generateThumbnails();
            }, 2000);
        },
        
        /**
         * Generate thumbnails for stories (images or videos)
         */
        generateThumbnails: function() {
            var self = this;
            
            console.log('WP Stories Admin: Starting thumbnail generation');
            
            // Note: We always regenerate thumbnails, even if one exists
            // The server will handle deleting the old thumbnail
            
            // Get media IDs
            var mediaIds = $('#wp-stories-media-ids').val();
            if (!mediaIds) {
                console.log('WP Stories Admin: No media IDs found in hidden input');
                console.log('WP Stories Admin: Checking media list directly...');
                
                // Try to get media IDs from the media list items
                var mediaIdsFromList = [];
                $('#wp-stories-media-list .wp-stories-media-item').each(function() {
                    var mediaId = $(this).data('media-id') || $(this).attr('data-media-id');
                    if (mediaId) {
                        mediaIdsFromList.push(mediaId);
                    }
                });
                
                if (mediaIdsFromList.length === 0) {
                    console.log('WP Stories Admin: No media items found in list');
                    return;
                } else {
                    console.log('WP Stories Admin: Found media IDs from list:', mediaIdsFromList);
                    mediaIds = mediaIdsFromList.join(',');
                }
            }
            
            var mediaIdsArray = mediaIds.split(',').filter(function(id) { return id.trim(); });
            if (mediaIdsArray.length === 0) {
                console.log('WP Stories Admin: Empty media IDs array');
                return;
            }
            
            // CRITICAL: Always use the FIRST item (index 0) - this is the first item in the story
            // The order is maintained by the drag-and-drop functionality (updateMediaOrder)
            var firstMediaId = mediaIdsArray[0];
            firstMediaId = parseInt(firstMediaId, 10); // Ensure it's a valid integer
            
            if (!firstMediaId || isNaN(firstMediaId)) {
                console.error('WP Stories Admin: Invalid first media ID:', firstMediaId);
                return;
            }
            
            console.log('WP Stories Admin: Using FIRST media item (ID: ' + firstMediaId + ') for thumbnail generation');
            console.log('WP Stories Admin: Total media items in story: ' + mediaIdsArray.length);
            
            // Get media type - try multiple selectors
            var mediaType = '';
            var $firstMediaItem = $('#wp-stories-media-list .wp-stories-media-item:first-child');
            
            // Try to get from media-type span
            var $typeSpan = $firstMediaItem.find('.wp-stories-media-type');
            if ($typeSpan.length > 0) {
                mediaType = $typeSpan.text().toLowerCase().trim();
            }
            
            // If not found, check if there's a video element
            if (!mediaType || mediaType === '') {
                var $video = $firstMediaItem.find('video');
                if ($video.length > 0) {
                    mediaType = 'video';
                } else {
                    var $img = $firstMediaItem.find('img');
                    if ($img.length > 0) {
                        mediaType = 'image';
                    }
                }
            }
            
            console.log('WP Stories Admin: Media type:', mediaType);
            
            // Get post ID
            var postId = $('#post_ID').val();
            if (!postId || postId === '' || postId === '0') {
                console.warn('WP Stories Admin: No post ID found yet. This might be a new post.');
                console.warn('WP Stories Admin: Thumbnail will be generated after post is saved.');
                // For new posts, we'll try again after save
                return;
            }
            
            console.log('WP Stories Admin: Post ID:', postId);
            
            // Process based on media type
            if (mediaType === 'image') {
                console.log('WP Stories Admin: Detected image, setting as thumbnail directly');
                self.setImageAsThumbnail(firstMediaId, postId);
            } else if (mediaType === 'video') {
                console.log('WP Stories Admin: Detected video, generating thumbnail from frame');
                // Get video URL
                var videoUrl = '';
                var $video = $firstMediaItem.find('video');
                if ($video.length > 0) {
                    videoUrl = $video.attr('src');
                    if (!videoUrl) {
                        videoUrl = $video.data('src');
                    }
                }
                
                if (!videoUrl) {
                    console.warn('WP Stories Admin: Could not find video URL');
                    return;
                }
                
                console.log('WP Stories Admin: Video URL found:', videoUrl);
                self.generateThumbnailFromVideo(videoUrl, firstMediaId, postId);
            } else {
                console.log('WP Stories Admin: Unknown media type, skipping thumbnail generation');
                return;
            }
        },
        
        /**
         * Set image as thumbnail directly (no conversion needed)
         */
        setImageAsThumbnail: function(mediaId, postId) {
            var self = this;
            
            console.log('WP Stories Admin: Setting image as thumbnail');
            console.log('WP Stories Admin: Media ID:', mediaId);
            console.log('WP Stories Admin: Post ID:', postId);
            
            // Check if ajaxurl is defined
            if (typeof ajaxurl === 'undefined') {
                console.error('WP Stories Admin: ajaxurl is not defined!');
                return;
            }
            
            // Check if nonce is available
            var nonce = wpStoriesAdmin && wpStoriesAdmin.nonce ? wpStoriesAdmin.nonce : '';
            if (!nonce) {
                console.warn('WP Stories Admin: No nonce available');
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wp_stories_set_image_thumbnail',
                    media_id: mediaId,
                    post_id: postId,
                    nonce: nonce
                },
                success: function(response) {
                    console.log('WP Stories Admin: AJAX response received:', response);
                    if (response.success) {
                        console.log('WP Stories Admin: Image set as thumbnail successfully!');
                        console.log('WP Stories Admin: Thumbnail URL:', response.data.thumbnail_url);
                        console.log('WP Stories Admin: Thumbnail ID (attachment):', response.data.thumbnail_id);
                        
                        // Show success message
                        self.showMessage('Thumbnail set successfully', 'success');
                        
                        // Update the featured image preview in WordPress admin
                        if (typeof wp !== 'undefined' && wp.media && wp.media.featuredImage) {
                            // Force refresh of featured image
                            $('#set-post-thumbnail').html('');
                            $('#set-post-thumbnail').append('<img src="' + response.data.thumbnail_url + '" style="max-width: 100%; height: auto;" />');
                            $('#remove-post-thumbnail').show();
                        }
                        
                        // Also update thumbnail preview if exists
                        if ($('.wp-stories-thumbnail-preview').length > 0) {
                            $('.wp-stories-thumbnail-preview').attr('src', response.data.thumbnail_url);
                        }
                    } else {
                        console.error('WP Stories Admin: Failed to set image as thumbnail:', response.data);
                        self.showMessage('Failed to set thumbnail: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('WP Stories Admin: AJAX error setting image thumbnail');
                    console.error('WP Stories Admin: Status:', status);
                    console.error('WP Stories Admin: Error:', error);
                    console.error('WP Stories Admin: Response:', xhr.responseText);
                    self.showMessage('Error setting thumbnail: ' + error, 'error');
                }
            });
        },
        
        /**
         * Generate thumbnail from video at 3 seconds
         */
        generateThumbnailFromVideo: function(videoUrl, mediaId, postId) {
            var self = this;
            
            console.log('WP Stories Admin: Generating thumbnail from video:', videoUrl);
            
            // First, try to get existing thumbnail from WordPress (poster or auto-generated)
            // This is a fallback if the video cannot be loaded in the browser
            var tryUseWordPressThumbnail = function() {
                console.log('WP Stories Admin: Trying to use WordPress-generated thumbnail as fallback');
                
                // Try to get thumbnail from video attachment
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wp_stories_get_video_thumbnail',
                        media_id: mediaId,
                        post_id: postId,
                        nonce: wpStoriesAdmin && wpStoriesAdmin.nonce ? wpStoriesAdmin.nonce : ''
                    },
                    success: function(response) {
                        if (response.success && response.data.thumbnail_url) {
                            console.log('WP Stories Admin: Using WordPress-generated thumbnail:', response.data.thumbnail_url);
                            self.showMessage('Using WordPress-generated thumbnail', 'info');
                            
                            // Update featured image preview
                            if (typeof wp !== 'undefined' && wp.media && wp.media.featuredImage) {
                                $('#set-post-thumbnail').html('');
                                $('#set-post-thumbnail').append('<img src="' + response.data.thumbnail_url + '" style="max-width: 100%; height: auto;" />');
                                $('#remove-post-thumbnail').show();
                            }
                        } else {
                            console.error('WP Stories Admin: No WordPress thumbnail available');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('WP Stories Admin: Error getting WordPress thumbnail:', error);
                    }
                });
            };
            
            var video = document.createElement('video');
            video.crossOrigin = 'anonymous';
            video.muted = true;
            video.preload = 'metadata';
            
            // Add timeout to detect if video is not loading
            var loadTimeout = setTimeout(function() {
                console.warn('WP Stories Admin: Video loading timeout, trying fallback method');
                video.removeEventListener('loadedmetadata', loadMetadataHandler);
                video.removeEventListener('seeked', seekedHandler);
                video.removeEventListener('error', errorHandler);
                tryUseWordPressThumbnail();
            }, 10000); // 10 seconds timeout
            
            var loadMetadataHandler = function() {
                console.log('WP Stories Admin: Video metadata loaded, duration:', video.duration);
                clearTimeout(loadTimeout);
                
                // Seek to 3 seconds (or duration * 0.1 if video is shorter)
                var seekTime = Math.min(3, video.duration * 0.1);
                console.log('WP Stories Admin: Seeking to:', seekTime, 'seconds');
                video.currentTime = seekTime;
            };
            
            var seekedHandler = function() {
                console.log('WP Stories Admin: Video seeked, drawing to canvas');
                clearTimeout(loadTimeout);
                
                // Create canvas
                var canvas = document.createElement('canvas');
                canvas.width = 500;
                canvas.height = 800;
                var ctx = canvas.getContext('2d');
                
                try {
                    // Draw video frame
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    
                    // Convert to data URL
                    var thumbnailDataUrl = canvas.toDataURL('image/jpeg', 0.8);
                    console.log('WP Stories Admin: Thumbnail generated, data length:', thumbnailDataUrl.length);
                    
                    // Send to server via AJAX
                    self.saveThumbnailToServer(thumbnailDataUrl, mediaId, postId);
                    
                    // Clean up
                    video.removeEventListener('loadedmetadata', loadMetadataHandler);
                    video.removeEventListener('seeked', seekedHandler);
                    video.removeEventListener('error', errorHandler);
                } catch (error) {
                    console.error('WP Stories Admin: Error drawing video to canvas:', error);
                    console.log('WP Stories Admin: Falling back to WordPress thumbnail');
                    tryUseWordPressThumbnail();
                }
            };
            
            var errorHandler = function(e) {
                console.error('WP Stories Admin: Failed to load video for thumbnail:', e);
                clearTimeout(loadTimeout);
                console.log('WP Stories Admin: Falling back to WordPress thumbnail');
                tryUseWordPressThumbnail();
            };
            
            video.addEventListener('loadedmetadata', loadMetadataHandler);
            video.addEventListener('seeked', seekedHandler);
            video.addEventListener('error', errorHandler);
            
            video.src = videoUrl;
        },
        
        /**
         * Save thumbnail to server via AJAX
         */
        saveThumbnailToServer: function(thumbnailDataUrl, mediaId, postId) {
            var self = this;
            
            console.log('WP Stories Admin: Sending thumbnail to server');
            console.log('WP Stories Admin: Media ID:', mediaId);
            console.log('WP Stories Admin: Post ID:', postId);
            console.log('WP Stories Admin: Thumbnail data length:', thumbnailDataUrl.length);
            
            // Check if ajaxurl is defined
            if (typeof ajaxurl === 'undefined') {
                console.error('WP Stories Admin: ajaxurl is not defined!');
                return;
            }
            
            // Check if nonce is available
            var nonce = wpStoriesAdmin && wpStoriesAdmin.nonce ? wpStoriesAdmin.nonce : '';
            if (!nonce) {
                console.warn('WP Stories Admin: No nonce available');
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wp_stories_save_thumbnail',
                    thumbnail_data: thumbnailDataUrl,
                    media_id: mediaId,
                    post_id: postId,
                    nonce: nonce
                },
                success: function(response) {
                    console.log('WP Stories Admin: AJAX response received:', response);
                    if (response.success) {
                        console.log('WP Stories Admin: Thumbnail saved successfully!');
                        console.log('WP Stories Admin: Thumbnail URL:', response.data.thumbnail_url);
                        console.log('WP Stories Admin: Thumbnail ID (attachment):', response.data.thumbnail_id);
                        
                        // Show success message
                        self.showMessage('Thumbnail generated and saved successfully', 'success');
                        
                        // Update the featured image preview in WordPress admin
                        // This will refresh the thumbnail display
                        if (typeof wp !== 'undefined' && wp.media && wp.media.featuredImage) {
                            // Force refresh of featured image
                            $('#set-post-thumbnail').html('');
                            $('#set-post-thumbnail').append('<img src="' + response.data.thumbnail_url + '" style="max-width: 100%; height: auto;" />');
                            $('#remove-post-thumbnail').show();
                        }
                        
                        // Also update thumbnail preview if exists
                        if ($('.wp-stories-thumbnail-preview').length > 0) {
                            $('.wp-stories-thumbnail-preview').attr('src', response.data.thumbnail_url);
                        }
                    } else {
                        console.error('WP Stories Admin: Failed to save thumbnail:', response.data);
                        self.showMessage('Failed to save thumbnail: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('WP Stories Admin: AJAX error saving thumbnail');
                    console.error('WP Stories Admin: Status:', status);
                    console.error('WP Stories Admin: Error:', error);
                    console.error('WP Stories Admin: Response:', xhr.responseText);
                    self.showMessage('Error saving thumbnail: ' + error, 'error');
                }
            });
        },
        
        /**
         * Handle AJAX errors
         */
        handleAjaxError: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            this.showMessage(wpStoriesAdmin.strings.ajaxError || 'An error occurred. Please try again.', 'error');
        },
        
        /**
         * Initialize story field validation (titulo and detalle)
         */
        initStoryFieldValidation: function() {
            var $storyTitle = $('#story_title');
            var $storyDetail = $('#story_detail');
            
            // Validate on input - prevent spaces
            function validateSingleWord(e) {
                var $input = $(this);
                var value = $input.val();
                
                // Remove all spaces
                var cleanedValue = value.replace(/\s+/g, '');
                
                if (value !== cleanedValue) {
                    $input.val(cleanedValue);
                    
                    // Show warning message
                    var $warning = $input.siblings('.field-warning');
                    if ($warning.length === 0) {
                        $warning = $('<span class="field-warning" style="color: #dc3232; font-size: 12px; display: block; margin-top: 5px;">No se permiten espacios. Solo una palabra.</span>');
                        $input.after($warning);
                    }
                    
                    // Remove warning after 3 seconds
                    setTimeout(function() {
                        $warning.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 3000);
                }
            }
            
            // Bind validation to both fields
            if ($storyTitle.length > 0) {
                $storyTitle.on('input keyup paste', validateSingleWord);
            }
            
            if ($storyDetail.length > 0) {
                $storyDetail.on('input keyup paste', validateSingleWord);
            }
            
            // Additional validation on form submit
            $('form#post').on('submit', function(e) {
                var titleValue = $storyTitle.val();
                var detailValue = $storyDetail.val();
                var hasErrors = false;
                
                // Check title
                if (titleValue && /\s/.test(titleValue)) {
                    alert('El campo "Título" no puede contener espacios. Solo se permite una palabra.');
                    $storyTitle.focus();
                    hasErrors = true;
                }
                
                // Check detail
                if (!hasErrors && detailValue && /\s/.test(detailValue)) {
                    alert('El campo "Subtítulo" no puede contener espacios. Solo se permite una palabra.');
                    $storyDetail.focus();
                    hasErrors = true;
                }
                
                if (hasErrors) {
                    e.preventDefault();
                    return false;
                }
            });
        }
    };
    
    /**
     * Story List Table functionality
     */
    var WPStoriesListTable = {
        
        /**
         * Initialize list table functionality
         */
        init: function() {
            this.bindEvents();
            this.initBulkActions();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Handle bulk action notifications
            this.showBulkActionNotices();
        },
        
        /**
         * Initialize bulk actions
         */
        initBulkActions: function() {
            // Confirm bulk actions
            $('#doaction, #doaction2').on('click', function(e) {
                var action = $(this).siblings('select').val();
                
                if (action === 'wp_stories_extend_expiration' || action === 'wp_stories_remove_expiration') {
                    var checkedItems = $('input[name="post[]"]:checked').length;
                    
                    if (checkedItems === 0) {
                        alert(wpStoriesAdmin.strings.selectItems || 'Please select items to perform this action.');
                        e.preventDefault();
                        return false;
                    }
                    
                    var confirmMessage = action === 'wp_stories_extend_expiration' ? 
                        wpStoriesAdmin.strings.confirmExtend || 'Extend expiration by 24 hours for selected stories?' :
                        wpStoriesAdmin.strings.confirmRemoveExpiration || 'Remove expiration for selected stories?';
                    
                    if (!confirm(confirmMessage)) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        },
        
        /**
         * Show bulk action notices
         */
        showBulkActionNotices: function() {
            var urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.has('wp_stories_extended')) {
                var count = urlParams.get('wp_stories_extended');
                var message = count + ' ' + (count == 1 ? 'story' : 'stories') + ' extended successfully.';
                this.showNotice(message, 'success');
            }
            
            if (urlParams.has('wp_stories_expiration_removed')) {
                var count = urlParams.get('wp_stories_expiration_removed');
                var message = 'Expiration removed from ' + count + ' ' + (count == 1 ? 'story' : 'stories') + '.';
                this.showNotice(message, 'success');
            }
        },
        
        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);
        }
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Initialize based on current page
        if ($('#wp-stories-media-list').length) {
            WPStoriesAdmin.init();
        }
        
        if ($('.wp-list-table').length && window.location.href.indexOf('post_type=wp_story') > -1) {
            WPStoriesListTable.init();
        }
    });
    
})(jQuery);