=== Really Simple Licensing ===
Contributors:      jameswlepage, jeffpaul
Donate link:       https://github.com/sponsors/jeffpaul
Tags:              licensing, rsl, ai, content, copyright
Requires at least: 5.0
Tested up to:      6.8
Requires PHP:      7.4
Stable tag:        0.0.5
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Really Simple Licensing (RSL) support. Define machine-readable licensing terms for AI and automated systems.

== Description ==

**RSL** brings complete support for the Really Simple Licensing (RSL) 1.0 standard to your site. RSL is a machine-readable format that allows content owners to clearly specify how their digital content can be used by AI systems, search engines, crawlers, and other automated tools.

### Why RSL Matters

As AI training and automated content processing become more prevalent, content creators need a standardized way to:
* Define clear usage rights for their content
* Specify compensation terms for AI training
* Control how their content is crawled and indexed
* Provide machine-readable licensing that systems can automatically respect

### Key Features

**Complete RSL Implementation**
* Full RSL 1.0 specification support
* All RSL elements: content, license, permits, prohibits, payment, legal, schema, copyright, terms
* Proper XML generation with correct namespacing
* URL pattern matching with wildcard support

**Multiple Integration Methods**
* HTML head injection with `<script type="application/rsl+xml">` tags
* HTTP Link headers for programmatic discovery
* robots.txt enhancement with RSL License directives
* RSS feed integration with RSL namespace
* Media file metadata embedding (images, PDFs, EPUB)

**Professional Admin Interface**
* Intuitive license creation and management
* Global site-wide licensing with per-post overrides
* Live XML preview and validation
* Bulk license operations
* Media library integration

**Advanced Features**
* License server integration and authentication
* REST API endpoints for programmatic access
* Support for encrypted content
* Geographic and user-type restrictions
* Comprehensive payment and compensation options

**WordPress Integration**
* Seamless WordPress admin integration
* Post/page meta boxes for individual licensing
* Shortcodes for displaying license information
* Hook-based architecture for extensibility
* Multisite network compatibility

### Common Use Cases

**Block AI Training**
Prevent your content from being used for AI training while allowing search indexing:
```
Prohibits: train-ai, train-genai
Permits: search, ai-summarize
Payment: Free
```

**Require Attribution**
Allow content use with proper attribution:
```
Permits: all
Payment: Attribution
Standard License: Creative Commons BY 4.0
```

**Commercial Licensing**
Offer paid licensing for AI training:
```
Permits: train-ai
Payment: Subscription - $99/month
Custom Terms: Your licensing page
```

**Educational Use Only**
Restrict usage to educational institutions:
```
Permits User Types: education, non-commercial
Prohibits User Types: commercial
```

### Quick Start

1. Install and activate the plugin
2. Go to RSL > Dashboard
3. Create your first license with "Add RSL License"
4. Set it as your global license
5. Enable integration methods (HTML, HTTP headers, robots.txt)
6. Your site now broadcasts machine-readable licensing terms!

### Integration Methods

**HTML Head Injection**
Automatically embeds RSL licenses in your page headers for crawler discovery.

**HTTP Link Headers**
Adds proper Link headers to HTTP responses following web standards.

**robots.txt Enhancement**
Extends your robots.txt with RSL License directives and AI Preferences compatibility.

**RSS Feed Integration**
Enhances your RSS feeds with RSL licensing information for each item.

**Media Metadata Embedding**
Embeds RSL licensing directly into uploaded images, PDFs, and EPUB files.

### Standards Compliant

* Full RSL 1.0 specification implementation
* Proper XML namespacing (https://rslstandard.org/rsl)
* Standard MIME types (application/rsl+xml)
* RFC-compliant HTTP headers
* Robots Exclusion Protocol compatibility
* RSS 2.0 module specification

### Privacy & Security

* No external data transmission (unless using license servers)
* Secure input validation and sanitization
* Proper WordPress nonce usage
* Capability-based access control
* No tracking or analytics

### Learn More

* [RSL Specification](https://rslstandard.org/rsl)
* [RSL API Documentation](https://rslstandard.org/api)
* [License Servers Guide](https://rslstandard.org/guide/license-servers)
* [Plugin Documentation](https://github.com/jeffpaul/rsl-wp)

== Installation ==

= Automatic Installation =
1. Go to Plugins > Add New in your WordPress admin
2. Search for "RSL"
3. Click Install Now and then Activate

= Manual Installation =
1. Download the plugin ZIP file
2. Go to Plugins > Add New > Upload Plugin
3. Choose the ZIP file and click Install Now
4. Activate the plugin

= After Installation =
1. Go to RSL > Dashboard
2. Click "Add RSL License" to create your first license
3. Configure your license terms and permissions
4. Set it as your global license in RSL > Dashboard
5. Enable desired integration methods
6. Save settings

Your WordPress site now supports machine-readable content licensing!

== Frequently Asked Questions ==

= What is RSL (Really Simple Licensing)? =

RSL is an XML-based standard for defining machine-readable licensing terms for digital content. It allows content owners to specify how their content can be used by AI systems, search engines, and other automated tools.

= Is this compatible with existing copyright notices? =

Yes! RSL complements traditional copyright notices by making them machine-readable. You can include copyright holder information in your RSL licenses.

= Can I use this with Creative Commons licenses? =

Absolutely! RSL supports standard license references. You can link to Creative Commons licenses using the standard URL field, making your CC licensing machine-readable.

= Does this work with caching plugins? =

Yes, RSL works with popular caching plugins like WP Rocket, W3 Total Cache, and LiteSpeed Cache. The licensing information is embedded during page generation.

= Can I license different parts of my site differently? =

Yes! You can create multiple licenses with different URL patterns (e.g., /blog/, /images/, /api/) or override the global license on specific posts and pages.

= Will this slow down my site? =

No. RSL adds minimal overhead - just small XML snippets to your HTML and database queries are optimized. The impact on site performance is negligible.

= Do I need a license server? =

No, license servers are optional. RSL works perfectly for declaring licensing terms without external services. License servers are only needed for paid licensing, authentication, or usage tracking.

= Is this compatible with WordPress multisite? =

Yes! Each site in a multisite network can have its own RSL configuration and licenses.

= What file formats support embedded metadata? =

Currently: JPEG, PNG, TIFF, WebP (XMP metadata), PDF (companion files), and EPUB (OPF metadata). More formats planned for future releases.

= Can I export/import license configurations? =

License data is stored in your WordPress database and included in standard WordPress exports. Manual export/import features are planned for a future release.

== Screenshots ==

1. **Main Settings Page** - Configure global licensing and integration options
2. **License Management** - View and manage all your RSL licenses
3. **License Editor** - Create detailed licensing terms with all RSL options
4. **Post/Page Integration** - Override global licenses for specific content
5. **Media Library Integration** - License assignment for individual media files
6. **Generated XML Preview** - See the RSL XML that will be generated
7. **robots.txt Integration** - Enhanced robots.txt with RSL directives
8. **RSS Feed Enhancement** - RSL licensing in feed items

== Changelog ==

= 0.0.3 =
* Complete testing infrastructure and CI/CD automation
* 28 comprehensive unit tests with 136 assertions
* GitHub Actions workflows for automated testing and releases
* Security scanning and code quality automation
* Professional development tools and documentation
* Clean production builds and release automation

= 0.0.2 =
* Add comprehensive OAuth 2.0 client credentials authentication system
* WordPress help tabs on all RSL admin pages
* Currency field fixes and validation improvements
* WooCommerce processor enhancements for all payment types
* Modular payment processor architecture
* Session-based payment flows
* Enhanced documentation and admin interface

= 0.0.1 =
* Initial alpha release
* Complete RSL 1.0 specification implementation
* WordPress admin interface for license management
* Multiple integration methods (HTML, HTTP headers, robots.txt, RSS, media)
* License server support with authentication
* REST API endpoints
* Media file metadata embedding

== Upgrade Notice ==

= 0.0.3 =
Major update with comprehensive testing infrastructure, automated CI/CD pipeline, and professional development tools. Includes 28 passing tests and enterprise-grade quality assurance.

= 0.0.2 =
Significant enhancements including OAuth 2.0 authentication, WooCommerce integration improvements, and modular payment architecture.

== Technical Details ==

**Requirements:**
* WordPress 5.0+
* PHP 7.4+
* MySQL 5.6+

**Integration Methods:**
* HTML: `<script type="application/rsl+xml">` embedding
* HTTP: Link headers with rel="license"
* robots.txt: License directive + AI Preferences
* RSS: RSL namespace module
* Media: XMP/OPF metadata embedding

**API Endpoints:**
* `/wp-json/rsl/v1/licenses` - List licenses
* `/wp-json/rsl/v1/licenses/{id}` - Get license
* `/wp-json/rsl/v1/validate` - Validate content licensing
* `/.well-known/rsl/` - Server discovery

**Supported Elements:**
All RSL 1.0 elements including content, license, permits, prohibits, payment (free/purchase/subscription/training/crawl/inference/attribution), legal (warranties/disclaimers), schema, copyright, and terms.

== Support ==

For support, documentation, and feature requests:

* [Plugin Documentation](https://github.com/jeffpaul/rsl-wp)
* [WordPress.org Support Forum](https://wordpress.org/support/plugin/rsl-licensing/)
* [RSL Specification](https://rslstandard.org/rsl)
* [RSL API Documentation](https://rslstandard.org/api)
* [License Servers Guide](https://rslstandard.org/guide/license-servers)

== Contributing ==

This plugin is open source! Contribute on [GitHub](https://github.com/jeffpaul/rsl-wp).

== License ==

This plugin is licensed under GPLv2 or later. The RSL standard is an open specification.
