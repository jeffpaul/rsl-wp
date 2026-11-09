# Changelog

All notable changes to the RSL for WordPress plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Public, nonce-free license and feed URLs for canonical discovery (`get_license_url()`, `get_feed_url()`), plus `<link rel="license" type="application/rsl+xml">` in HTML output.
- RSS items now carry their own `<rsl:content>` block through the shared RSL serializer.
- `rsl_require_client_auth`, `rsl_builtin_server_url`, and `rsl_enable_builtin_cap` filters.
- RSL 1.0 document fields: `max-age`, payment `<accepts>`, `<reporting>`, `attestation`/`contact`/`proof` legal declarations, `<alternate>` representations, and inline Schema.org JSON-LD.
- Site-wide `/rsl.xml` document that groups licenses sharing a `content_url` into one `<content>` with multiple `<license>` children.

### Changed
- RSL 1.0 vocabulary: usage tokens are `all`, `ai-all`, `ai-train`, `ai-input`, `ai-index`, and `search`; payment types are `free`, `purchase`, `subscription`, `training`, `crawl`, `use`, `contribution`, and `attribution`. Existing rows and legacy tokens are migrated automatically.
- Token, introspect, and CAP responses now use RSL 1.0 shapes: `token_type: License`, `{error, error_description}`, `WWW-Authenticate: License`, and `Link: rel="license"` challenges.
- `/token` requires `grant_type=client_credentials` and client authentication by default. Session proofs are redeemed once via `session_id`.
- `lastmod` is stored and emitted in GMT; existing local-time rows are converted once during upgrade.
- Built-in server is advertised through the `server` attribute; `encrypted="true"` is only advertised for licenses with an external license server.
- `/key` is `POST` only and returns `501` until key delivery is implemented.
- RSL documents emit a `max-age` attribute (default 30 days) and omit `reporting` until both a category and profile are configured.

### Fixed
- Media RSL metadata now uses the attachment URL, emits a valid `<rsl:rsl>` XMP fragment, embeds XMP directly in JPEGs when possible, and labels sidecar files as sidecars instead of claiming every file is embedded.
- License edit links are nonce-protected.
- Public discovery URLs no longer require a nonce.
- CORS and rate-limit headers no longer emit after output has started.

## [0.0.4-alpha] - 2025-09-13

### Added
- **Comprehensive Translation Infrastructure**
  - GitHub Actions workflow for translation testing and validation
  - Translation coverage analysis with detailed reporting
  - Multi-line translation string detection and validation
  - WordPress i18n compliance checking for proper text domains
  - Automated PO/MO file validation using GNU gettext tools

- **Translation Files Improvements**
  - Updated Spanish (es_ES) translation files with proper headers
  - Fixed translation string formatting and removed fuzzy flags
  - Regenerated MO files for proper WordPress translation loading
  - Cleaned up duplicate and malformed translation entries

### Changed
- **WordPress Coding Standards Compliance**
  - Achieved 0 ERRORS in WordPress Coding Standards (WPCS) compliance
  - Fixed 23,714+ code formatting violations across 33 files using phpcbf
  - Implemented proper Yoda conditions throughout codebase
  - Added comprehensive PHPDoc documentation to all classes and methods
  - Standardized code formatting with consistent spacing and indentation

- **Text Domain Migration**
  - Updated text domain from 'rsl-licensing' to 'rsl-wp' for consistency
  - Applied text domain changes across all PHP files and templates
  - Updated translation files to match new text domain

- **Modern WordPress Translation Loading**
  - Removed unnecessary `load_plugin_textdomain()` calls (WordPress 4.6+ handles automatically)
  - Simplified translation initialization for better performance
  - Updated translation workflow to reflect modern WordPress practices

### Fixed
- **Critical Function Name Typos**
  - Fixed `gmgmdate()` → `gmdate()` in session manager (would cause fatal errors)
  - Fixed `upgmdate()` → `update()` in OAuth client class
  - Fixed `wp_wp_parse_url()` → `wp_parse_url()` in server class
  - Fixed incorrect WordPress table name handling in uninstall.php

- **Code Quality Improvements**
  - Fixed strict comparison operators (`===`, `!==`) throughout codebase
  - Implemented proper array search with strict type checking
  - Fixed inline comment punctuation and formatting
  - Resolved all WordPress.DB.PreparedSQL violations

- **Translation System Fixes**
  - Fixed msgmerge creating duplicate concatenated strings in PO files
  - Resolved translation file corruption issues during automated processing
  - Fixed backup file cleanup (.po~ files) in translation workflows

### Security
- **Enhanced Input Validation**
  - Improved prepared statement usage across all database queries
  - Fixed potential SQL injection vectors in table name handling
  - Enhanced sanitization for all user inputs

## [0.0.3-alpha] - 2025-09-13

### Added
- **Comprehensive Testing Infrastructure**
  - PHPUnit 9.6 testing framework with 28 passing tests (136 assertions)
  - Mock WordPress environment for reliable testing without full WP setup
  - Test suites covering unit, integration, security, and performance testing
  - Test fixtures and utilities for all plugin components

- **GitHub Actions CI/CD Pipeline**
  - Matrix testing across PHP 7.4-8.3 on Ubuntu
  - Automated plugin ZIP building for releases (production-only, ~700KB)
  - Security scanning with weekly scheduled vulnerability checks
  - Code quality validation and WordPress standards compliance
  - Automated WordPress.org deployment workflow

- **Professional Development Tools**
  - Makefile with comprehensive development commands
  - Security analysis script for vulnerability detection
  - Test runner script with colored output and HTML reporting
  - GitHub issue and PR templates with comprehensive checklists
  - Dependabot for automated dependency updates

- **Release Automation**
  - Automatic GitHub release creation with assets and checksums
  - Clean production ZIP builds excluding development files
  - Professional release notes with technical details
  - Download link automation for main repository page

### Changed
- Updated AGENTS.md to follow proper agents.md format and guidelines
- Enhanced README with prominent download section and working links
- Improved documentation with correct RSL specification URLs
- Cleaned commit history to remove AI co-authorship references

### Fixed
- Fixed GitHub Actions workflows to use non-deprecated actions (v3→v4)
- Resolved composer validation issues with missing license field
- Fixed CI class redefinition conflicts during coverage generation
- Corrected broken RSL documentation links in README and templates
- Removed emojis from all release templates and documentation

### Security
- Complete security test suite validating all input sanitization
- SQL injection prevention testing across all database queries
- XSS protection validation and JWT token manipulation resistance
- Rate limiting enforcement testing and authorization bypass prevention

## [0.0.2-alpha] - 2025-09-12

### Added
- **OAuth 2.0 Authentication System**
  - Client credentials flow for secure API access
  - JWT token generation and validation with Firebase library support
  - Automatic token revocation on refunds/cancellations
  - Rate limiting protection (30/min tokens, 100/min introspection, 20/min sessions)

- **Enhanced License Server**
  - RSL Open Licensing Protocol (OLP) endpoints
  - Session-based payment flows for complex licensing scenarios
  - Comprehensive error handling with standard OAuth 2.0 responses
  - Token introspection with revocation status checking

- **WooCommerce Integration**
  - Automatic product creation for paid licenses
  - Support for all RSL payment types through WooCommerce gateways
  - Order-based token generation and validation
  - Subscription support with WooCommerce Subscriptions

- **Security Improvements**
  - Enhanced REQUEST_URI sanitization using proper URL parsing
  - CORS origin validation instead of wildcard access
  - Consistent debug logging with rsl_log() function
  - Input validation and error handling improvements

- **Admin Enhancements**
  - WordPress help tabs across all admin pages
  - Improved form data handling to prevent duplicates
  - Dynamic currency symbol display
  - Enhanced error messaging and validation

### Changed
- Improved API error responses to follow OAuth 2.0 standards
- Enhanced token validation with proper expiration and audience checks
- Better rate limiting with informative HTTP headers

### Fixed
- Currency field corruption in admin forms
- Double URL encoding issues in frontend
- Form data duplication in admin interface
- Various security vulnerabilities identified by AI review

## [0.0.1-alpha] - 2025-09-11

### Added
- **Core RSL Implementation**
  - Full RSL 1.0 specification support
  - Complete XML generation with proper namespacing
  - Support for all RSL elements: content, license, permits, prohibits, payment, legal, schema, copyright, terms
  - URL pattern matching with wildcard support (* and $)

- **License Management System**
  - Database-backed license storage with full CRUD operations
  - Rich admin interface for creating and managing licenses
  - Support for multiple licenses per site
  - Global and per-post license assignment
  - License validation and error checking

- **Multiple Integration Methods**
  - HTML head injection with `<script type="application/rsl+xml">` tags
  - HTTP Link headers with proper rel="license" and type attributes
  - robots.txt integration with License directive and AI Preferences compatibility
  - RSS feed enhancement with RSL namespace and per-item licensing
  - Dedicated RSL license feeds at `/feed/rsl-licenses/`

- **Admin Interface**
  - Comprehensive settings page with global configuration
  - License list view with search, sort, and bulk actions
  - Advanced license editor with all RSL properties
  - Live XML preview and download functionality
  - Post/page meta boxes for individual license assignment
  - Media library integration with license column

- **Media File Support**
  - XMP metadata embedding for JPEG, PNG, TIFF, WebP images
  - PDF companion RSL files (.rsl.xml)
  - EPUB OPF metadata integration
  - Automatic processing on upload
  - Media library license assignment interface

- **License Server Integration**
  - RSL License Server protocol support
  - License authentication with Authorization: License headers
  - Proper 401 Unauthorized responses with WWW-Authenticate headers
  - REST API endpoints for programmatic access
  - .well-known/rsl/ discovery endpoint

- **Developer Features**
  - REST API with endpoints for licenses, validation, and discovery
  - WordPress rewrite rules for clean URLs
  - Proper query variable handling
  - Action and filter hooks for extensibility
  - Shortcode support for displaying license information

- **Permission System**
  - Usage type controls: all, train-ai, train-genai, ai-use, ai-summarize, search
  - User type restrictions: commercial, non-commercial, education, government, personal
  - Geographic limitations with ISO 3166-1 alpha-2 country codes
  - Both permits and prohibits elements with proper boolean logic

- **Payment & Compensation**
  - Multiple payment types: free, purchase, subscription, training, crawl, inference, attribution
  - Amount and currency specification with decimal precision
  - Standard license URL support (Creative Commons, GPL, etc.)
  - Custom license agreement URLs
  - Integration with RSL Collective and other license servers

- **Legal Framework**
  - Warranty declarations: ownership, authority, no-infringement, privacy-consent, no-malware
  - Disclaimer options: as-is, no-warranty, no-liability, no-indemnity
  - Copyright holder information with type and contact details
  - Schema.org CreativeWork integration

- **Security & Performance**
  - Input validation and sanitization throughout
  - SQL injection prevention with prepared statements
  - Proper nonce usage for form submissions
  - Capability checks for admin functions
  - Efficient database queries with appropriate indexing
  - Proper caching headers for static content

- **Standards Compliance**
  - Full RSL 1.0 specification compliance
  - Proper XML namespace usage (https://rslstandard.org/rsl)
  - Standard MIME types (application/rsl+xml)
  - RFC-compliant HTTP headers
  - robots.txt Robots Exclusion Protocol compatibility
  - RSS 2.0 module specification adherence

### Technical Details

- **Database Schema**
  - Single table design (`wp_rsl_licenses`) with 25+ fields
  - Proper MySQL data types and constraints
  - Automated table creation on activation
  - Support for WordPress multisite installations

- **File Structure**
  - Modular class-based architecture
  - Separation of concerns (admin, frontend, media, robots, RSS, server)
  - Template system for admin pages
  - Asset management for CSS/JS files

- **WordPress Integration**
  - Hook-based architecture following WordPress standards
  - Proper plugin activation/deactivation lifecycle
  - Internationalization ready (text domain: rsl-licensing)
  - WordPress coding standards compliance
  - Multisite network compatibility

### Browser Support
- Modern browsers supporting HTML5 and ES5+ JavaScript
- Graceful degradation for older browsers
- Mobile-responsive admin interface

### Server Requirements
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+ or MariaDB equivalent
- Apache/Nginx with rewrite support
- Optional: XMP libraries for advanced image metadata

---

## Development Notes

### Architecture Decisions
- **Single Table Storage**: Simplified schema for better performance and maintenance
- **Hook-Based Design**: Maximum compatibility with other WordPress plugins
- **XML Generation**: Pure PHP implementation without external dependencies
- **Caching Strategy**: Leverages WordPress caching with appropriate cache headers

### Future Considerations
- Content encryption support (planned for v1.1)
- Advanced license server features (payment processing, usage tracking)
- Additional media format support (video, audio metadata)
- Bulk import/export functionality
- Multi-language license support

### Known Limitations
- XMP embedding requires server-side image processing capabilities
- EPUB metadata modification requires ZIP manipulation libraries
- License server authentication is basic (token-based)
- No built-in payment processing (relies on external license servers)

---

**For detailed installation instructions, see [INSTALL.md](INSTALL.md)**
**For usage examples and API documentation, see [README.md](README.md)**
