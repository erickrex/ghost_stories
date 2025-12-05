<?php

use Brain\Monkey; 
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/class-story-frontend.php';

class StoryFrontendRenderTest extends TestCase {

    protected function setUp(): void {
        Monkey\setUp();
        // Mock WP hooks used in constructor
        \Brain\Monkey\Functions\when('add_action')->justReturn(true);
        \Brain\Monkey\Functions\when('add_shortcode')->justReturn(true);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
    }

    public function test_render_stories_returns_empty_with_invalid_input() {
        $frontend = new Story_Frontend();
        $this->assertSame('', $frontend->render_stories(null));
        $this->assertSame('', $frontend->render_stories(array()));
    }
}


