<?php
/**
 * Story Modal Template
 *
 * This template is loaded in the footer when stories are present on the page
 *
 * @package WP_Stories_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wp-stories-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Story viewer', 'wp-stories-plugin'); ?>" aria-hidden="true" tabindex="-1">
    <div class="wp-stories-modal-overlay" aria-hidden="true"></div>
    <div class="wp-stories-modal-content">
        <div class="wp-stories-header">
            <!-- Header vacío - controles movidos al video -->
        </div>
        
        <div class="wp-stories-media-container">
            <!-- Progress Bar Overlay -->
            <div class="wp-stories-progress-overlay">
                <div class="wp-stories-progress-container" role="progressbar" aria-label="<?php esc_attr_e('Story progress', 'wp-stories-plugin'); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <!-- Progress segments will be dynamically added here -->
                </div>
            </div>
            
            <!-- Controls Overlay -->
            <div class="wp-stories-controls-overlay">
                <div class="wp-stories-play-control" role="button" tabindex="0" aria-label="<?php esc_attr_e('Play/Pause story', 'wp-stories-plugin'); ?>">
                    <svg class="play-icon" viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                    <svg class="pause-icon" viewBox="0 0 24 24" fill="currentColor" width="16" height="16" style="display: none;">
                        <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                    </svg>
                </div>
                <div class="wp-stories-sound-control" role="button" tabindex="0" aria-label="<?php esc_attr_e('Toggle sound', 'wp-stories-plugin'); ?>">
                    <svg class="sound-icon" viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                        <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/>
                    </svg>
                    <svg class="mute-icon" viewBox="0 0 24 24" fill="currentColor" width="16" height="16" style="display: none;">
                        <path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/>
                    </svg>
                </div>
                <div class="wp-stories-modal-close" role="button" tabindex="0" aria-label="<?php esc_attr_e('Close stories', 'wp-stories-plugin'); ?>">
                    <span aria-hidden="true">&times;</span>
                </div>
            </div>
            
            <div class="wp-stories-media-wrapper" role="img" aria-live="polite">
                <!-- Media content will be loaded here dynamically -->
            </div>
            
            <!-- Decorative Overlay -->
            <div class="wp-stories-decorative-overlay">
                <div class="wp-stories-overlay-crown">👑</div>
                <div class="wp-stories-overlay-number">155</div>
                <div class="wp-stories-overlay-icons">
                    <span class="wp-stories-overlay-icon">🎃</span>
                    <span class="wp-stories-overlay-icon">🎃</span>
                    <span class="wp-stories-overlay-icon">🎃</span>
                </div>
            </div>
            
            <div class="wp-stories-navigation" aria-label="<?php esc_attr_e('Story navigation', 'wp-stories-plugin'); ?>">
                <div class="wp-stories-nav-prev" role="button" tabindex="0" aria-label="<?php esc_attr_e('Previous story', 'wp-stories-plugin'); ?>">
                    <span aria-hidden="true">&lsaquo;</span>
                </div>
                <div class="wp-stories-nav-next" role="button" tabindex="0" aria-label="<?php esc_attr_e('Next story', 'wp-stories-plugin'); ?>">
                    <span aria-hidden="true">&rsaquo;</span>
                </div>
            </div>
            
            <!-- Story Navigation (Vertical) - Hidden -->
            <div class="wp-stories-story-navigation" aria-label="<?php esc_attr_e('Navigate between stories', 'wp-stories-plugin'); ?>" style="display: none;">
                <div class="wp-stories-story-nav-up" role="button" tabindex="0" aria-label="<?php esc_attr_e('Previous story', 'wp-stories-plugin'); ?>">
                    <span aria-hidden="true">↑</span>
                </div>
                <div class="wp-stories-story-nav-down" role="button" tabindex="0" aria-label="<?php esc_attr_e('Next story', 'wp-stories-plugin'); ?>">
                    <span aria-hidden="true">↓</span>
                </div>
            </div>
            
            
            <!-- Touch areas for mobile devices -->
            <div class="wp-stories-touch-areas">
                <div class="wp-stories-touch-left" data-action="prev" aria-label="<?php esc_attr_e('Previous story', 'wp-stories-plugin'); ?>"></div>
                <div class="wp-stories-touch-right" data-action="next" aria-label="<?php esc_attr_e('Next story', 'wp-stories-plugin'); ?>"></div>
                <div class="wp-stories-touch-center" data-action="pause" aria-label="<?php esc_attr_e('Pause/Resume story', 'wp-stories-plugin'); ?>"></div>
            </div>
        </div>
        
        <!-- Loading indicator -->
        <div class="wp-stories-loading" style="display: none;" aria-live="polite">
            <div class="wp-stories-spinner" aria-label="<?php esc_attr_e('Loading story', 'wp-stories-plugin'); ?>"></div>
            <span class="sr-only"><?php esc_html_e('Loading story content...', 'wp-stories-plugin'); ?></span>
        </div>
        
        <!-- Error message -->
        <div class="wp-stories-error" style="display: none;" role="alert">
            <p><?php esc_html_e('Unable to load story. Please try again.', 'wp-stories-plugin'); ?></p>
            <button class="wp-stories-retry" type="button"><?php esc_html_e('Retry', 'wp-stories-plugin'); ?></button>
        </div>
    </div>
</div>

