<?php
// Simple bootstrap for unit tests with Brain Monkey (no full WP stack)

// Define ABSPATH so plugin files with guards don't exit
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

require_once __DIR__ . '/../vendor/autoload.php';


