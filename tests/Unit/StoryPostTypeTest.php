<?php

use Brain\Monkey; 
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/class-story-post-type.php';

class StoryPostTypeTest extends TestCase {

    protected function setUp(): void {
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
    }

    public function test_is_story_expired_without_expiration_returns_false() {
        \Brain\Monkey\Functions\when('get_transient')->justReturn(false);
        \Brain\Monkey\Functions\when('get_post_meta')->alias(function($post_id, $key){
            if ($key === Story_Post_Type::META_EXPIRATION_HOURS) {
                return null; // no expiration
            }
            if ($key === Story_Post_Type::META_CREATED_TIMESTAMP) {
                return time() - 3600; // 1h ago
            }
            return null;
        });
        \Brain\Monkey\Functions\when('set_transient')->justReturn(true);
        \Brain\Monkey\Functions\when('current_time')->justReturn(time());

        $this->assertFalse(Story_Post_Type::is_story_expired(123));
    }

    public function test_is_story_expired_with_past_expiration_returns_true() {
        \Brain\Monkey\Functions\when('get_transient')->justReturn(false);
        \Brain\Monkey\Functions\when('get_post_meta')->alias(function($post_id, $key){
            if ($key === Story_Post_Type::META_EXPIRATION_HOURS) {
                return 1; // 1 hour
            }
            if ($key === Story_Post_Type::META_CREATED_TIMESTAMP) {
                return time() - (3 * 3600); // 3h ago
            }
            return null;
        });
        \Brain\Monkey\Functions\when('set_transient')->justReturn(true);
        \Brain\Monkey\Functions\when('current_time')->justReturn(time());

        $this->assertTrue(Story_Post_Type::is_story_expired(456));
    }

    public function test_get_time_remaining_without_expiration_returns_null() {
        \Brain\Monkey\Functions\when('get_transient')->justReturn(false);
        \Brain\Monkey\Functions\when('get_post_meta')->alias(function($post_id, $key){
            if ($key === Story_Post_Type::META_EXPIRATION_HOURS) {
                return null;
            }
            if ($key === Story_Post_Type::META_CREATED_TIMESTAMP) {
                return time() - 3600;
            }
            return null;
        });
        \Brain\Monkey\Functions\when('set_transient')->justReturn(true);
        \Brain\Monkey\Functions\when('current_time')->justReturn(time());

        $this->assertNull(Story_Post_Type::get_time_remaining(789));
    }
}


