# Technical Stack

## Core Technologies

- **Platform**: WordPress 5.0+ (PHP 7.4+)
- **Frontend**: Vanilla JavaScript with jQuery
- **Build System**: None - no build process required
- **Testing**: PHPUnit 9.6 with Brain Monkey for WordPress mocking

## PHP Architecture

- **Custom Post Type**: `wp_story` for story content
- **OOP Design**: Class-based architecture with singleton pattern for main plugin class
- **Namespace**: None (uses class prefixes: `WP_Stories_`, `Story_`)
- **WordPress APIs**: REST API, Custom Post Types, Meta API, Transients API

## Frontend Stack

- **JavaScript**: ES5-compatible vanilla JS (no transpilation)
- **jQuery**: Used for DOM manipulation and AJAX
- **Gutenberg**: Native WordPress block API (no JSX, no build)
- **CSS**: Modern CSS with custom properties, no preprocessor

## Key Dependencies

### PHP (Composer)
- `phpunit/phpunit`: ^9.6 (dev)
- `brain/monkey`: ^2.6 (dev)

### JavaScript (WordPress Core)
- `wp-blocks`, `wp-element`, `wp-block-editor`, `wp-components`
- `wp-i18n`, `wp-api-fetch`, `wp-data`
- jQuery (bundled with WordPress)

## Required PHP Extensions

- `json`: JSON encoding/decoding
- `mbstring`: Multibyte string handling
- `gd`: Image manipulation for thumbnails

## Common Commands

### Testing
```bash
# Run all tests
composer test

# Run PHPUnit directly
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Integration
```

### Development
```bash
# Install dependencies
composer install

# Install dev dependencies only
composer install --dev

# Update dependencies
composer update
```

### WordPress Integration
```bash
# Activate plugin (via WP-CLI)
wp plugin activate ghost-stories

# Deactivate plugin
wp plugin deactivate ghost-stories

# Flush rewrite rules after changes
wp rewrite flush
```

## Performance Optimizations

- **Conditional Loading**: Assets only load when stories are present on page
- **Caching**: Transient API for expiration checks and story data
- **Lazy Loading**: Images load as needed in modal
- **Request Animation Frame**: Smooth progress bar animations
