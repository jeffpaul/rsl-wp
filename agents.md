# AGENTS.md

Instructions for AI coding agents working on the RSL Licensing WordPress plugin.

## Project Overview

This is a WordPress plugin implementing Really Simple Licensing (RSL) 1.0 specification for machine-readable content licensing. The plugin enables WordPress site owners to define licensing terms for AI companies, crawlers, and automated systems.

Core functionality:
- Complete RSL 1.0 specification implementation
- OAuth 2.0 license server with JWT authentication
- WooCommerce payment integration
- REST API endpoints for license validation
- Multiple integration methods (HTML, robots.txt, RSS, media)

## Architecture

### Core Components

- **`RSL_License`**: License CRUD operations and XML generation
- **`RSL_Server`**: License server with JWT authentication and REST API
- **`RSL_Payment_Registry`**: Manages modular payment processors
- **`RSL_Session_Manager`**: MCP-inspired session state management for AI payments
- **`RSL_WooCommerce_Processor`**: Handles all WooCommerce payment gateways
- **`RSL_Admin`**: WordPress admin interface and AJAX handlers
- **`RSL_Frontend`**: Public-facing HTML injection and HTTP headers
- **`RSL_Robots`**: robots.txt integration with RSL directives
- **`RSL_RSS`**: RSS feed enhancement with RSL namespace
- **`RSL_Media`**: Media file metadata embedding (XMP, sidecar files)

### Key Design Principles

1. **WooCommerce First-Class**: WooCommerce handles ALL payment gateways (Stripe, PayPal, etc.)
2. **Modular Architecture**: Payment processors are extensible via interface
3. **MCP-Inspired Sessions**: Server-side session management with polling (no webhooks)
4. **Security Focus**: JWT tokens, signed payment proofs, CORS restrictions
5. **WordPress Standards**: Follows WordPress coding standards and best practices

## Setup Commands

```bash
# Install dependencies
composer install --dev

# Start development environment
make playground
# OR manually: npx @wp-playground/cli server --auto-mount

# Run tests
make test

# Build production ZIP
make zip

# Run security checks
make security
```

## Testing Instructions

```bash
# Run working test suite (28 tests, 136 assertions)
vendor/bin/phpunit tests/unit/TestBasicFunctionality.php tests/unit/TestRSLLicense.php

# Quick smoke tests
./tests/run-tests.sh --quick

# Manual API testing
curl http://127.0.0.1:9400/.well-known/rsl/
curl http://127.0.0.1:9400/wp-json/rsl/v1/licenses
curl http://127.0.0.1:9400/robots.txt
```

## Code Style Guidelines

### PHP Standards

- Follow **WordPress Coding Standards**
- Use **proper escaping** (`esc_html()`, `esc_attr()`, `esc_url_raw()`)
- **Sanitize all input** (`sanitize_text_field()`, `sanitize_textarea_field()`)
- **Validate user capabilities** (`current_user_can()`)
- **Use proper nonces** for AJAX security

### Security Requirements

- **Never expose sensitive data** in client-side code
- **Always sanitize `$_SERVER['REQUEST_URI']`** with `esc_url_raw()`
- **Use `$this->add_cors_headers()`** instead of `Access-Control-Allow-Origin: *`
- **Validate payment proofs** cryptographically
- **Implement proper authentication** for license servers

### Database Operations

- **Use `$wpdb->prepare()`** for all queries with user input
- **Check `$wpdb->last_error`** after database operations
- **Use proper format specifiers** (e.g., `%s` for strings, `%f` for floats, `%d` for integers)
- **Ensure data array order** matches database schema order

## Common Patterns

### Adding New Payment Processors

```php
class Custom_Payment_Processor implements RSL_Payment_Processor_Interface {
    public function get_id() { return 'custom'; }
    public function get_name() { return 'Custom Payment System'; }
    public function is_available() { return function_exists('custom_payment_api'); }
    
    // Implement interface methods...
}

// Register via hook
add_action('rsl_register_payment_processors', function($registry) {
    $registry->register_processor(new Custom_Payment_Processor());
});
```

### Adding WordPress Hooks

```php
// Filter example
add_filter('rsl_token_ttl', function($ttl) {
    return 15 * MINUTE_IN_SECONDS;
});

// Action example
add_action('rsl_license_updated', function($license_id, $changes, $old_license) {
    // React to a license change
}, 10, 3);
```

The hooks that exist are listed in `docs/DEVELOPER.md`. When you add a hook, document it there too.

### AJAX Handlers

- **Always check nonces**: `check_ajax_referer('rsl_nonce', 'nonce')`
- **Verify capabilities**: `current_user_can('manage_options')`
- **Return proper JSON**: `wp_send_json_success()` or `wp_send_json_error()`
- **Sanitize all input**: Use appropriate sanitization functions

## Testing Guidelines

### Manual Testing Checklist

- [ ] Create free license and verify HTML injection
- [ ] Create paid license and test WooCommerce integration
- [ ] Test session-based payment flow
- [ ] Verify robots.txt integration
- [ ] Check RSS feed enhancement
- [ ] Test media file metadata embedding
- [ ] Validate all REST API endpoints

### Common Issues to Avoid

1. **Array/Format Mismatch**: Ensure license data array order matches database schema
2. **CORS Security**: Never use `Access-Control-Allow-Origin: *` 
3. **Input Sanitization**: Always sanitize `$_SERVER` variables
4. **Payment Type Support**: WooCommerce should support all RSL payment types
5. **Session Management**: Use server-side storage, not client-side

## Database Schema

The main table structure (`wp_rsl_licenses`):

```sql
CREATE TABLE wp_rsl_licenses (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    description text,
    content_url text NOT NULL,
    server_url varchar(255),
    encrypted tinyint(1) DEFAULT 0,
    lastmod datetime,
    permits_usage text,
    permits_user text,
    permits_geo text,
    prohibits_usage text,
    prohibits_user text,
    prohibits_geo text,
    payment_type varchar(50) DEFAULT 'free',
    standard_url varchar(255),
    custom_url varchar(255),
    amount decimal(10,2),
    currency varchar(3),
    warranty text,
    disclaimer text,
    schema_url varchar(255),
    copyright_holder varchar(255),
    copyright_type varchar(20),
    contact_email varchar(255),
    contact_url varchar(255),
    terms_url varchar(255),
    active tinyint(1) DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## File Structure

```
rsl-wp.php                           # Main plugin file
├── includes/
│   ├── class-rsl-license.php               # Core license management
│   ├── class-rsl-server.php                # License server and REST API
│   ├── class-rsl-admin.php                 # WordPress admin integration
│   ├── class-rsl-payment-registry.php      # Payment processor management
│   ├── class-rsl-session-manager.php       # Session state management
│   ├── interfaces/
│   │   └── interface-rsl-payment-processor.php
│   └── processors/
│       └── class-rsl-woocommerce-processor.php
├── admin/
│   ├── templates/                          # Admin page templates
│   ├── css/admin.css                       # Admin styling
│   └── js/admin.js                         # Admin JavaScript
└── docs/                                   # Documentation
```

## Debug Mode

Enable debug logging in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('RSL_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Use `rsl_log($message, $level)` for conditional logging that only outputs when debug mode is enabled.

## Build and Test Commands

The plugin doesn't require a build process, but testing can be done via:

```bash
# Start WordPress Playground
npx @wp-playground/cli server --auto-mount

# Test endpoints
curl http://127.0.0.1:9400/.well-known/rsl/
curl http://127.0.0.1:9400/wp-json/rsl/v1/licenses
curl http://127.0.0.1:9400/robots.txt
```

## PR Guidelines

1. **Follow WordPress coding standards**
2. **Maintain security best practices** 
3. **Add tests for new features** (use existing test framework)
4. **Test thoroughly** before submitting
5. **Ensure backwards compatibility** with existing licenses
6. **Update relevant documentation** files
7. **No emojis** in commit messages or documentation
8. **No AI co-authorship** in commits

### Before submitting PR:
```bash
# Run tests to ensure they pass
make test

# Check code quality
make lint

# Run security analysis
make security

# Build and test ZIP file
make zip
```

## Version Release Process

When releasing a new version, multiple files need to be updated in the correct order:

### Step 1: Update Version Numbers
```bash
# 1. Update main plugin file
# Edit rsl-wp.php:
# * Version: X.Y.Z (line ~6)
# define("RSL_PLUGIN_VERSION", "X.Y.Z"); (line ~35)

# 2. Update readme.txt for WordPress.org
# Edit readme.txt:
# Stable tag: X.Y.Z (line ~8)

# 3. Update README.md version badge
# Edit README.md:
# [![Version](https://img.shields.io/badge/Version-X.Y.Z-blue)]
```

### Step 2: Update Changelog Documentation
```bash
# 1. Update docs/CHANGELOG.md (follows Keep a Changelog format)
# Add new version section with:
# ## [X.Y.Z] - YYYY-MM-DD
# ### Added / Changed / Fixed / Removed sections

# 2. Update readme.txt changelog
# Add new version section:
# = X.Y.Z =
# * Brief bullet points of changes

# 3. Update upgrade notice in readme.txt
# = X.Y.Z =
# Brief description of why users should upgrade
```

### Step 3: Update Download Links (Manual)
```bash
# Edit README.md Quick Download section:
# - Latest Release: vX.Y.Z link
# - Download button URL
# - curl/wget commands with new version
# - File size information
# - Alpha notice version number
```

### Step 4: Create Release
```bash
# Commit version updates
git add rsl-wp.php readme.txt README.md docs/CHANGELOG.md
git commit -m "Release version X.Y.Z"

# Create and push tag (triggers automation)
git tag vX.Y.Z
git push origin main
git push origin vX.Y.Z

# GitHub Actions will automatically:
# - Run tests (28 tests, 136 assertions)
# - Build production ZIP (~700KB)
# - Create GitHub release
# - Upload assets with checksums
```

### Step 5: WordPress.org Deployment (if configured)
```bash
# Automatic deployment happens via GitHub Actions if:
# - WP_ORG_USERNAME and WP_ORG_PASSWORD secrets are set
# - Version follows semantic versioning (X.Y.Z)
# - All tests pass

# Manual deployment alternative:
# Use 10up/action-wordpress-plugin-deploy action
```

### Files Updated During Version Release:
- `rsl-wp.php` - Plugin header and version constant
- `readme.txt` - Stable tag, changelog, upgrade notice
- `README.md` - Version badge, download links, alpha notice
- `docs/CHANGELOG.md` - Detailed change documentation

### Version Numbering:
- **0.0.x** - Alpha releases (current)
- **0.x.y** - Beta releases
- **x.y.z** - Stable releases
- Follow semantic versioning for breaking changes

## Resources

- **WordPress Coding Standards**: https://developer.wordpress.org/coding-standards/
- **RSL 1.0 Specification**: https://rslstandard.org/rsl
- **RSL API Documentation**: https://rslstandard.org/api
- **RSL License Servers Guide**: https://rslstandard.org/guide/license-servers
- **WordPress REST API**: https://developer.wordpress.org/rest-api/
- **WooCommerce Documentation**: https://woocommerce.com/documentation/