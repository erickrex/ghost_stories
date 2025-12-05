/**
 * WordPress Stories Plugin - Minimal Frontend JavaScript
 * Simplified version that focuses on core functionality
 */

(function($) {
    'use strict';
    
    
    // Wait for DOM to be ready
    $(document).ready(function() {
        
        // Verify dependencies
        if (typeof wpStories === 'undefined') {
            console.error('[Ghost Stories] CRITICAL: wpStories object not found!');
            console.error('[Ghost Stories] Available global objects:', Object.keys(window).filter(k => k.includes('Stories') || k.includes('stories')));
            return;
        }
        
        
        // Build stories array from DOM order (to match frontend order)
        buildStoriesArrayFromDOM();
        initializeStoryClicks();
        
        // Initialize modal close handlers
        initializeModalClose();
        
        // Initialize touch and navigation handlers for existing modal
        if ($('.wp-stories-modal').length > 0) {
            initializeTouchHandlers();
            initializeNavigation();
            initializeMediaControls();
            initializeProgressSync();
        }
        
        // Also initialize handlers when modal is opened
        $(document).on('click', '.wp-stories-circle', function() {
            setTimeout(function() {
                if ($('.wp-stories-modal').hasClass('active')) {
                    initializeTouchHandlers();
                    initializeNavigation();
                    initializeMediaControls();
                    initializeProgressSync();
                }
            }, 100);
        });
        
    });
    
    /**
     * Build stories array from DOM order (matches frontend order)
     */
    function buildStoriesArrayFromDOM() {
        allStoriesData = [];
        $('.wp-stories-circle').each(function() {
            var storyId = $(this).data('story-id');
            if (storyId) {
                allStoriesData.push({
                    id: parseInt(storyId)
                });
            }
        });
        console.log('[Ghost Stories] Stories order from DOM:', allStoriesData.map(function(s) { return s.id; }));
    }
    
    /**
     * Initialize story circle click handlers
     */
    function initializeStoryClicks() {
        $('.wp-stories-circle').on('click', function(e) {
            e.preventDefault();
            
            var $circle = $(this);
            var storyId = $circle.data('story-id');
            
            
            if (!storyId) {
                console.error('[Ghost Stories] No story ID found');
                showError('Invalid story');
                return;
            }
            
            // Find the story in our loaded stories
            var storyIndex = findStoryIndex(storyId);
            if (storyIndex === -1) {
                console.error('[Ghost Stories] Story not found in loaded stories:', storyId);
                showError('Story not found');
                return;
            }
            
            // Set current story index
            currentStoryIndex = storyIndex;
            
            // Show loading state
            $circle.addClass('loading');
            
            // Load and open story
            loadStory(storyId)
                .then(function(storyData) {
                    $circle.removeClass('loading');
                    openModal(storyData);
                })
                .catch(function(error) {
                    $circle.removeClass('loading').addClass('error');
                    console.error('[Ghost Stories] Error loading story:', error);
                    showError('Unable to load story: ' + error.message);
                });
        });
        
    }
    
    /**
     * Find story index in allStoriesData by ID
     */
    function findStoryIndex(storyId) {
        for (var i = 0; i < allStoriesData.length; i++) {
            if (allStoriesData[i].id == storyId) {
                return i;
            }
        }
        return -1;
    }
    
    /**
     * Load story data from API
     */
    function loadStory(storyId) {
        return new Promise(function(resolve, reject) {
            var url = wpStories.restUrl + 'stories/' + storyId;
            
            
            $.ajax({
                url: url,
                method: 'GET',
                timeout: 10000,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpStories.restNonce);
                },
                success: function(response) {
                    
                    // Validate response
                    if (!response || !response.media || response.media.length === 0) {
                        reject(new Error('Story has no media'));
                        return;
                    }
                    
                    resolve(response);
                },
                error: function(xhr, status, error) {
                    console.error('[Ghost Stories] API Error:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        error: error
                    });
                    
                    var errorMessage = 'Network error';
                    if (xhr.status === 404) {
                        errorMessage = 'Story not found';
                    } else if (xhr.status === 403) {
                        errorMessage = 'Access denied';
                    } else if (xhr.status === 0) {
                        errorMessage = 'Connection failed';
                    }
                    
                    reject(new Error(errorMessage));
                }
            });
        });
    }
    
    /**
     * Open modal with story data
     */
    function openModal(storyData) {
        
        // Reset global mute state for new story
        window.wpStoriesGlobalMutedState = true;
        
        // Store current story data
        currentStoryData = storyData;
        currentMediaIndex = 0;
        
        var $modal = $('.wp-stories-modal');
        
        // Modal should already exist from PHP template
        if ($modal.length === 0) {
            console.error('[Ghost Stories] CRITICAL: Modal template not found! Creating fallback...');
            createModal();
            $modal = $('.wp-stories-modal');
        }
        
        // Clear previous content
        $('.wp-stories-media-wrapper').empty();
        
        // Show modal with proper display
        $modal.css('display', 'flex');
        
        // Use setTimeout to ensure display is applied before adding active class
        setTimeout(function() {
            $modal.addClass('active').attr('aria-hidden', 'false');
            $('body').addClass('wp-stories-modal-open');
        }, 10);
        
        // Initialize progress bar
        updateProgressBar();
        
        // Load first media item
        if (storyData.media && storyData.media.length > 0) {
            loadMedia(storyData.media[0], storyData.title);
        } else {
            showError('No media available');
        }
        
        
    }
    
    /**
     * Load media into modal
     */
    function loadMedia(media, storyTitle) {
        
        var $wrapper = $('.wp-stories-media-wrapper');
        var mediaHtml = '';
        
        // Clear any existing timers
        clearPhotoTimer();
        
        if (media.type === 'image') {
            mediaHtml = '<img src="' + escapeHtml(media.url) + '" ' +
                       'alt="' + escapeHtml(storyTitle) + '" ' +
                       'class="wp-stories-media" ' +
                       'style="max-width: 100%; max-height: 80vh; object-fit: contain;">';
            
            // Use image as overlay background
            applyOverlayBackground(media.url);
            
            // Start 5-second timer for photos
            startPhotoTimer();
            
        } else if (media.type === 'video') {
            mediaHtml = '<video src="' + escapeHtml(media.url) + '" ' +
                       'class="wp-stories-media" ' +
                       'controls ' +
                       'autoplay ' +
                       'playsinline ' +
                       'style="width: 100%; height: 100%; object-fit: cover;">' +
                       '<p style="color: white;">Your browser does not support video playback.</p>' +
                       '</video>';
            
            // Generate thumbnail for video and apply to overlay
            generateModalThumbnail(media.url);
            
            // Initialize video progress sync
            setTimeout(function() {
                initializeProgressSync();
            }, 100);
        } else {
            mediaHtml = '<p style="color: white; text-align: center;">Unsupported media type: ' + escapeHtml(media.type) + '</p>';
        }
        
        $wrapper.html(mediaHtml);
        
    }
    
    /**
     * Generate thumbnail from video and apply to overlay background
     */
    function generateModalThumbnail(videoUrl) {
        
        var video = document.createElement('video');
        video.crossOrigin = 'anonymous';
        video.muted = true; // Keep muted for thumbnail generation
        video.preload = 'metadata';
        
        video.addEventListener('loadedmetadata', function() {
            
            // Create canvas for thumbnail
            var canvas = document.createElement('canvas');
            canvas.width = 500;  // Match modal width
            canvas.height = 800; // Reasonable height
            var ctx = canvas.getContext('2d');
            
            // Seek to 1 second or 10% of video duration
            var seekTime = Math.min(1, video.duration * 0.1);
            video.currentTime = seekTime;
        });
        
        video.addEventListener('seeked', function() {
            
            var canvas = document.createElement('canvas');
            canvas.width = 500;
            canvas.height = 800;
            var ctx = canvas.getContext('2d');
            
            // Draw video frame
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Convert to data URL and apply as overlay background
            var thumbnailUrl = canvas.toDataURL('image/jpeg', 0.8);
            applyOverlayBackground(thumbnailUrl);
            
        });
        
        video.addEventListener('error', function() {
            console.warn('[Ghost Stories] Failed to generate modal thumbnail');
        });
        
        video.src = videoUrl;
    }
    
    /**
     * Apply background image to decorative overlay
     */
    function applyOverlayBackground(imageUrl) {
        var $overlay = $('.wp-stories-decorative-overlay');
        
        if ($overlay.length > 0) {
            
            $overlay.css({
                'background-image': 'url(' + imageUrl + ')',
                'background-size': 'cover',
                'background-position': 'center',
                'background-repeat': 'no-repeat'
            });
            
        } else {
            console.error('[Ghost Stories] Overlay element not found!');
        }
    }
    
    /**
     * Current media state
     */
    var currentMediaIndex = 0;
    var currentStoryData = null;
    
    /**
     * Stories navigation state
     */
    var allStoriesData = [];
    var currentStoryIndex = 0;
    var isNavigatingToNextStory = false;
    
    /**
     * Initialize touch handlers for mobile swipe gestures
     */
    function initializeTouchHandlers() {
        var touchStartX = 0;
        var touchStartY = 0;
        var touchEndX = 0;
        var touchEndY = 0;
        var minSwipeDistance = 30; // Más sensible para gestos naturales
        
        $(document).on('touchstart', '.wp-stories-media-container', function(e) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        });
        
        $(document).on('touchend', '.wp-stories-media-container', function(e) {
            touchEndX = e.changedTouches[0].clientX;
            touchEndY = e.changedTouches[0].clientY;
            handleSwipe();
        });
        
        function handleSwipe() {
            var deltaX = touchEndX - touchStartX;
            var deltaY = touchEndY - touchStartY;
            
            // Calcular el ángulo del swipe para mejor detección
            var angle = Math.atan2(Math.abs(deltaY), Math.abs(deltaX)) * 180 / Math.PI;
            
            // Si el swipe es más vertical que horizontal (ángulo > 45°)
            if (Math.abs(deltaY) > Math.abs(deltaX) && Math.abs(deltaY) > minSwipeDistance && angle > 45) {
                if (deltaY > 0) {
                    // Swipe down - previous story
                    navigateToPreviousStory();
                } else {
                    // Swipe up - next story
                    navigateToNextStory();
                }
            }
            // Si el swipe es más horizontal que vertical (ángulo < 45°)
            else if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > minSwipeDistance && angle < 45) {
                if (deltaX > 0) {
                    // Swipe right - previous media
                    navigatePrevious();
                } else {
                    // Swipe left - next media
                    navigateNext();
                }
            }
        }
        
        // Mouse drag support for desktop
        var mouseStartX = 0;
        var mouseStartY = 0;
        var mouseEndX = 0;
        var mouseEndY = 0;
        var isMouseDown = false;
        
        $(document).on('mousedown', '.wp-stories-media-container', function(e) {
            isMouseDown = true;
            mouseStartX = e.clientX;
            mouseStartY = e.clientY;
        });
        
        $(document).on('mouseup', '.wp-stories-media-container', function(e) {
            if (isMouseDown) {
                isMouseDown = false;
                mouseEndX = e.clientX;
                mouseEndY = e.clientY;
                handleMouseDrag();
            }
        });
        
        $(document).on('mouseleave', '.wp-stories-media-container', function(e) {
            isMouseDown = false;
        });
        
        function handleMouseDrag() {
            var deltaX = mouseEndX - mouseStartX;
            var deltaY = mouseEndY - mouseStartY;
            
            // Calcular el ángulo del drag para mejor detección
            var angle = Math.atan2(Math.abs(deltaY), Math.abs(deltaX)) * 180 / Math.PI;
            
            // Si el drag es más vertical que horizontal (ángulo > 45°)
            if (Math.abs(deltaY) > Math.abs(deltaX) && Math.abs(deltaY) > minSwipeDistance && angle > 45) {
                if (deltaY > 0) {
                    // Drag down - previous story
                    navigateToPreviousStory();
                } else {
                    // Drag up - next story
                    navigateToNextStory();
                }
            }
            // Si el drag es más horizontal que vertical (ángulo < 45°)
            else if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > minSwipeDistance && angle < 45) {
                if (deltaX > 0) {
                    // Drag right - previous media
                    navigatePrevious();
                } else {
                    // Drag left - next media
                    navigateNext();
                }
            }
        }
        
        // Touch area click handlers
        $(document).on('click', '.wp-stories-touch-left', function(e) {
            e.stopPropagation();
            navigatePrevious();
        });
        
        $(document).on('click', '.wp-stories-touch-right', function(e) {
            e.stopPropagation();
            navigateNext();
        });
        
        $(document).on('click', '.wp-stories-touch-center', function(e) {
            e.stopPropagation();
            // Pause/resume functionality can be added here
        });
        
    }
    
    /**
     * Initialize navigation button handlers
     */
    function initializeNavigation() {
        // Remove existing handlers to avoid duplicates
        $(document).off('click', '.wp-stories-nav-prev');
        $(document).off('click', '.wp-stories-nav-next');
        $(document).off('click', '.wp-stories-story-nav-up');
        $(document).off('click', '.wp-stories-story-nav-down');
        $(document).off('keydown', '.wp-stories-nav-prev');
        $(document).off('keydown', '.wp-stories-nav-next');
        $(document).off('keydown', '.wp-stories-story-nav-up');
        $(document).off('keydown', '.wp-stories-story-nav-down');
        
        $(document).on('click', '.wp-stories-nav-prev', function(e) {
            e.preventDefault();
            e.stopPropagation();
            navigatePrevious();
        });
        
        $(document).on('click', '.wp-stories-nav-next', function(e) {
            e.preventDefault();
            e.stopPropagation();
            navigateNext();
        });
        
        // Story navigation (vertical)
        $(document).on('click', '.wp-stories-story-nav-up', function(e) {
            e.preventDefault();
            e.stopPropagation();
            navigateToPreviousStory();
        });
        
        $(document).on('click', '.wp-stories-story-nav-down', function(e) {
            e.preventDefault();
            e.stopPropagation();
            navigateToNextStory();
        });
        
        // Keyboard support for navigation buttons
        $(document).on('keydown', '.wp-stories-nav-prev', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                navigatePrevious();
            }
        });
        
        $(document).on('keydown', '.wp-stories-nav-next', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                navigateNext();
            }
        });
        
        $(document).on('keydown', '.wp-stories-story-nav-up', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                navigateToPreviousStory();
            }
        });
        
        $(document).on('keydown', '.wp-stories-story-nav-down', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                navigateToNextStory();
            }
        });
        
        // Keyboard navigation
        $(document).on('keydown', function(e) {
            if (!$('.wp-stories-modal').hasClass('active')) return;
            
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                navigatePrevious();
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                navigateNext();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                navigateToPreviousStory();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                navigateToNextStory();
            }
        });
    }
    
    /**
     * Initialize media control handlers (play/pause, volume)
     */
    function initializeMediaControls() {
        // Remove existing handlers to avoid duplicates
        $(document).off('click', '.wp-stories-play-control');
        $(document).off('click', '.wp-stories-sound-control');
        $(document).off('keydown', '.wp-stories-play-control');
        $(document).off('keydown', '.wp-stories-sound-control');
        
        // Play/Pause button
        $(document).on('click', '.wp-stories-play-control', function(e) {
            e.preventDefault();
            e.stopPropagation();
            togglePlayPause();
        });
        
        // Volume button
        $(document).on('click', '.wp-stories-sound-control', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleVolume();
        });
        
        // Keyboard support for div buttons
        $(document).on('keydown', '.wp-stories-play-control', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                togglePlayPause();
            }
        });
        
        $(document).on('keydown', '.wp-stories-sound-control', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                toggleVolume();
            }
        });
        
    }
    
    // Global variables to track animation frame and timers
    var progressAnimationFrame = null;
    var photoTimer = null;
    var photoProgressInterval = null;
    
    /**
     * Start 5-second timer for photos
     */
    function startPhotoTimer() {
        var photoDuration = 5000; // 5 seconds
        var startTime = Date.now();
        
        // Clear any existing photo timer
        clearPhotoTimer();
        
        // Start progress animation for photo
        startPhotoProgressAnimation(startTime, photoDuration);
        
        // Set timer to navigate to next media after 5 seconds
        photoTimer = setTimeout(function() {
            navigateNext();
        }, photoDuration);
    }
    
    /**
     * Clear photo timer
     */
    function clearPhotoTimer() {
        if (photoTimer) {
            clearTimeout(photoTimer);
            photoTimer = null;
        }
        
        if (photoProgressInterval) {
            clearInterval(photoProgressInterval);
            photoProgressInterval = null;
        }
        
        // Stop any photo progress animation
        stopPhotoProgressAnimation();
    }
    
    /**
     * Start smooth progress animation for photos
     */
    function startPhotoProgressAnimation(startTime, duration) {
        // Only start if not already running
        if (photoProgressInterval) {
            return;
        }
        
        photoProgressInterval = setInterval(function() {
            var elapsed = Date.now() - startTime;
            var progress = Math.min((elapsed / duration) * 100, 100);
            
            // Update current progress segment
            var $activeSegment = $('.wp-stories-progress-segment.active');
            if ($activeSegment.length > 0) {
                var $fill = $activeSegment.find('.wp-stories-progress-fill');
                $fill.css('width', progress + '%');
                
                // Remove any existing animation
                $fill.css('animation', 'none');
                $fill.css('transition', 'none');
            }
            
            // Stop when progress reaches 100%
            if (progress >= 100) {
                stopPhotoProgressAnimation();
            }
        }, 16); // ~60fps
    }
    
    /**
     * Stop photo progress animation
     */
    function stopPhotoProgressAnimation() {
        if (photoProgressInterval) {
            clearInterval(photoProgressInterval);
            photoProgressInterval = null;
        }
    }
    
    /**
     * Initialize progress bar synchronization with video
     */
    function initializeProgressSync() {
        var $video = $('.wp-stories-media-wrapper video');
        
        if ($video.length > 0) {
            var video = $video[0];
            
            
            // Remove existing event listeners to avoid duplicates
            video.removeEventListener('timeupdate', updateProgressFromVideo);
            video.removeEventListener('loadeddata', handleVideoLoaded);
            video.removeEventListener('ended', handleVideoEnded);
            video.removeEventListener('play', handleVideoPlay);
            video.removeEventListener('pause', handleVideoPause);
            
            // Add event listeners
            video.addEventListener('loadeddata', handleVideoLoaded);
            video.addEventListener('ended', handleVideoEnded);
            video.addEventListener('play', handleVideoPlay);
            video.addEventListener('pause', handleVideoPause);
            
            // If video is already loaded, initialize immediately
            if (video.readyState >= 2) {
                handleVideoLoaded();
            }
        }
    }
    
    /**
     * Update progress bar based on video current time
     */
    function updateProgressFromVideo() {
        var $video = $('.wp-stories-media-wrapper video');
        
        if ($video.length > 0) {
            var video = $video[0];
            
            if (video.duration && !isNaN(video.duration) && video.duration > 0) {
                var progress = (video.currentTime / video.duration) * 100;
                
                // Ensure progress is between 0 and 100
                progress = Math.max(0, Math.min(100, progress));
                
                // Update current progress segment
                var $activeSegment = $('.wp-stories-progress-segment.active');
                if ($activeSegment.length > 0) {
                    var $fill = $activeSegment.find('.wp-stories-progress-fill');
                    $fill.css('width', progress + '%');
                    
                    // Remove any existing animation
                    $fill.css('animation', 'none');
                    $fill.css('transition', 'none');
                }
                
            }
        }
    }
    
    /**
     * Handle video loaded event
     */
    function handleVideoLoaded() {
        var video = $('.wp-stories-media-wrapper video')[0];
        
        // Apply global mute state if it exists, otherwise default to muted
        if (typeof window.wpStoriesGlobalMutedState !== 'undefined') {
            video.muted = window.wpStoriesGlobalMutedState;
        } else {
            video.muted = true;
            window.wpStoriesGlobalMutedState = true;
        }
        
        // Update volume button state
        var $soundControl = $('.wp-stories-sound-control');
        if (video.muted) {
            $soundControl.addClass('muted');
            $soundControl.find('.sound-icon').hide();
            $soundControl.find('.mute-icon').show();
        } else {
            $soundControl.removeClass('muted');
            $soundControl.find('.sound-icon').show();
            $soundControl.find('.mute-icon').hide();
        }
        
        updateProgressFromVideo();
        
        // Update play button state based on video state
        updatePlayButtonState(video);
        
        // Start progress animation immediately when video is loaded
        startProgressAnimation();
    }
    
    /**
     * Handle video ended event
     */
    function handleVideoEnded() {
        
        // Stop progress animation
        stopProgressAnimation();
        
        // Move to next media or next story
        setTimeout(function() {
            navigateNext();
        }, 500);
    }
    
    /**
     * Start smooth progress animation
     */
    function startProgressAnimation() {
        // Only start if not already running
        if (progressAnimationFrame) {
            return;
        }
        
        function animateProgress() {
            updateProgressFromVideo();
            progressAnimationFrame = requestAnimationFrame(animateProgress);
        }
        
        animateProgress();
    }
    
    /**
     * Stop smooth progress animation
     */
    function stopProgressAnimation() {
        if (progressAnimationFrame) {
            cancelAnimationFrame(progressAnimationFrame);
            progressAnimationFrame = null;
        }
    }
    
    /**
     * Update play button state based on video state
     */
    function updatePlayButtonState(video) {
        var $playControl = $('.wp-stories-play-control');
        
        if (video.paused) {
            $playControl.addClass('paused');
            $playControl.find('.play-icon').show();
            $playControl.find('.pause-icon').hide();
        } else {
            $playControl.removeClass('paused');
            $playControl.find('.play-icon').hide();
            $playControl.find('.pause-icon').show();
        }
    }
    
    /**
     * Handle video play event
     */
    function handleVideoPlay() {
        var video = $('.wp-stories-media-wrapper video')[0];
        
        // Update play button state
        updatePlayButtonState(video);
        
        // Start smooth progress animation
        startProgressAnimation();
    }
    
    /**
     * Handle video pause event
     */
    function handleVideoPause() {
        var video = $('.wp-stories-media-wrapper video')[0];
        
        // Update play button state
        updatePlayButtonState(video);
        
        // Stop smooth progress animation
        stopProgressAnimation();
    }
    
    /**
     * Toggle play/pause functionality
     */
    function togglePlayPause() {
        var $playControl = $('.wp-stories-play-control');
        var $video = $('.wp-stories-media-wrapper video');
        
        if ($video.length > 0) {
            var video = $video[0];
            
            // Only try to play if video is ready
            if (video.readyState < 2) {
                return;
            }
            
            if (video.paused) {
                video.play().then(function() {
                    updatePlayButtonState(video);
                }).catch(function(error) {
                    console.error('[Ghost Stories] Error playing video:', error);
                });
            } else {
                video.pause();
                updatePlayButtonState(video);
            }
        }
    }
    
    /**
     * Toggle volume functionality
     * Syncs mute state across all videos in the story
     */
    function toggleVolume() {
        var $soundControl = $('.wp-stories-sound-control');
        var $video = $('.wp-stories-media-wrapper video');
        
        if ($video.length > 0) {
            var currentVideo = $video[0];
            var shouldMute = !currentVideo.muted;
            
            // Apply mute state to all videos in the current story
            if (currentStoryData && currentStoryData.media) {
                currentStoryData.media.forEach(function(mediaItem) {
                    if (mediaItem.type === 'video') {
                        // We'll store the mute state and apply it when loading each video
                        // For now, update the current video
                        currentVideo.muted = shouldMute;
                    }
                });
            }
            
            // Update UI state
            if (shouldMute) {
                $soundControl.addClass('muted');
                $soundControl.find('.sound-icon').hide();
                $soundControl.find('.mute-icon').show();
            } else {
                $soundControl.removeClass('muted');
                $soundControl.find('.sound-icon').show();
                $soundControl.find('.mute-icon').hide();
            }
            
            // Store global mute state to apply to all future videos
            window.wpStoriesGlobalMutedState = shouldMute;
        }
    }
    
    /**
     * Navigate to previous media item
     */
    function navigatePrevious() {
        if (!currentStoryData || !currentStoryData.media) return;
        
        // Clear any running timers
        clearPhotoTimer();
        stopProgressAnimation();
        
        if (currentMediaIndex > 0) {
            // Previous media in current story
            currentMediaIndex--;
            loadMedia(currentStoryData.media[currentMediaIndex], currentStoryData.title);
            updateProgressBar();
        } else {
            // First media - try to go to previous story
            navigateToPreviousStory();
        }
    }
    
    /**
     * Navigate to previous story
     */
    function navigateToPreviousStory() {
        if (currentStoryIndex > 0) {
            // Go to previous story
            currentStoryIndex--;
            var prevStory = allStoriesData[currentStoryIndex];
            
            // Load previous story
            loadStory(prevStory.id)
                .then(function(storyData) {
                    // Update current story data
                    currentStoryData = storyData;
                    
                    // Set to last media of previous story
                    currentMediaIndex = storyData.media.length - 1;
                    
                    // Load last media of previous story
                    loadMedia(storyData.media[currentMediaIndex], storyData.title);
                    updateProgressBar();
                })
                .catch(function(error) {
                    console.error('[Ghost Stories] Error loading previous story:', error);
                    // If previous story fails, stay at current story
                    currentMediaIndex = 0;
                });
        }
        // If at first story, do nothing (stay at first media of first story)
    }
    
    /**
     * Navigate to next media item
     */
    function navigateNext() {
        if (!currentStoryData || !currentStoryData.media) return;
        
        // Clear any running timers
        clearPhotoTimer();
        stopProgressAnimation();
        
        if (currentMediaIndex < currentStoryData.media.length - 1) {
            // Next media in current story
            currentMediaIndex++;
            loadMedia(currentStoryData.media[currentMediaIndex], currentStoryData.title);
            updateProgressBar();
        } else {
            // Last media item - try to go to next story
            navigateToNextStory();
        }
    }
    
    /**
     * Navigate to next story
     */
    function navigateToNextStory() {
        if (isNavigatingToNextStory) {
            return; // Prevent multiple calls
        }
        
        isNavigatingToNextStory = true;
        
        if (currentStoryIndex + 1 < allStoriesData.length) {
            // Go to next story
            currentStoryIndex++;
            var nextStory = allStoriesData[currentStoryIndex];
            
            // Load next story
            loadStory(nextStory.id)
                .then(function(storyData) {
                    // Update current story data
                    currentStoryData = storyData;
                    
                    // Reset media index for new story
                    currentMediaIndex = 0;
                    isNavigatingToNextStory = false;
                    
                    // Load first media of new story
                    loadMedia(storyData.media[0], storyData.title);
                    updateProgressBar();
                })
                .catch(function(error) {
                    console.error('[Ghost Stories] Error loading next story:', error);
                    isNavigatingToNextStory = false;
                    // If next story fails, close modal
                    closeModal();
                });
        } else {
            // Last story - close modal
            isNavigatingToNextStory = false;
            closeModal();
        }
    }
    
    /**
     * Update progress bar
     */
    function updateProgressBar() {
        if (!currentStoryData || !currentStoryData.media) return;
        
        var $container = $('.wp-stories-progress-container');
        $container.empty();
        
        for (var i = 0; i < currentStoryData.media.length; i++) {
            var segmentClass = 'wp-stories-progress-segment';
            if (i < currentMediaIndex) {
                segmentClass += ' completed';
            } else if (i === currentMediaIndex) {
                segmentClass += ' active';
            }
            
            $container.append(
                '<div class="' + segmentClass + '">' +
                    '<div class="wp-stories-progress-fill"></div>' +
                '</div>'
            );
        }
        
        // Initialize progress sync after creating progress bar
        setTimeout(function() {
            initializeProgressSync();
        }, 100);
    }
    
    /**
     * Create modal HTML structure with touch areas and navigation
     * ONLY used as fallback if PHP template fails to load
     */
    function createModal() {
        // Check if modal already exists (should be from PHP template)
        if ($('.wp-stories-modal').length > 0) {
            return;
        }
        
        console.warn('[Ghost Stories] Creating fallback modal - PHP template did not load!');
        
        var modalHtml = 
            '<div class="wp-stories-modal" role="dialog" aria-modal="true" aria-label="Story viewer" aria-hidden="true" tabindex="-1">' +
                '<div class="wp-stories-modal-overlay"></div>' +
                '<div class="wp-stories-modal-content">' +
                    '<div class="wp-stories-header">' +
                        '<div class="wp-stories-progress-container"></div>' +
                        '<div class="wp-stories-modal-close" role="button" tabindex="0" aria-label="Close stories">' +
                            '<span aria-hidden="true">&times;</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="wp-stories-media-container">' +
                        '<div class="wp-stories-media-wrapper"></div>' +
                        '<div class="wp-stories-navigation">' +
                            '<div class="wp-stories-nav-prev" role="button" tabindex="0" aria-label="Previous">&lsaquo;</div>' +
                            '<div class="wp-stories-nav-next" role="button" tabindex="0" aria-label="Next">&rsaquo;</div>' +
                        '</div>' +
                        '<div class="wp-stories-touch-areas">' +
                            '<div class="wp-stories-touch-left" data-action="prev"></div>' +
                            '<div class="wp-stories-touch-center" data-action="pause"></div>' +
                            '<div class="wp-stories-touch-right" data-action="next"></div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        
        $('body').append(modalHtml);
        
        // Initialize touch handlers
        initializeTouchHandlers();
        initializeNavigation();
        initializeMediaControls();
        initializeProgressSync();
    }
    
    /**
     * Initialize modal close handlers
     */
    function initializeModalClose() {
        $(document).off('click', '.wp-stories-modal-close');
        $(document).off('keydown', '.wp-stories-modal-close');
        
        $(document).on('click', '.wp-stories-modal-close, .wp-stories-modal-overlay', function(e) {
            e.preventDefault();
            closeModal();
        });
        
        // Keyboard support for close button
        $(document).on('keydown', '.wp-stories-modal-close', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                closeModal();
            }
        });
        
        // Close on Escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('.wp-stories-modal').hasClass('active')) {
                closeModal();
            }
        });
        
    }
    
    /**
     * Close modal
     */
    function closeModal() {
        
        var $modal = $('.wp-stories-modal');
        $modal.removeClass('active').attr('aria-hidden', 'true');
        $('body').removeClass('wp-stories-modal-open');
        
        // Clear all timers and animations
        clearPhotoTimer();
        stopProgressAnimation();
        
        // Stop any playing videos
        $('.wp-stories-media-wrapper video').each(function() {
            this.pause();
        });
        
        // Clear content
        $('.wp-stories-media-wrapper').empty();
        
        // Hide modal after transition
        setTimeout(function() {
            $modal.css('display', 'none');
        }, 300);
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        console.error('[Ghost Stories] Error:', message);
        
        // You can enhance this to show a user-friendly notification
        if (typeof console !== 'undefined') {
            console.error('[Ghost Stories] ' + message);
        }
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    
})(jQuery);
