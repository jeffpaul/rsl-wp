# Really Simple Licensing

![RSL Banner](https://github.com/jeffpaul/rsl-wp/blob/main/assets/banner-1544x500.png?raw=true)

[![Download Plugin](https://img.shields.io/badge/Download-Plugin%20ZIP-green?style=for-the-badge&logo=download)](https://github.com/jeffpaul/rsl-wp/releases/latest/download/rsl-licensing-0.0.5.zip)

[![Version](https://img.shields.io/badge/Version-0.0.5-blue)](https://github.com/jeffpaul/rsl-wp/releases/latest) [![Downloads](https://img.shields.io/github/downloads/jeffpaul/rsl-wp/total)](https://github.com/jeffpaul/rsl-wp/releases) [![License](https://img.shields.io/badge/License-GPL%20v2-blue)](https://www.gnu.org/licenses/gpl-2.0.html) [![Tests](https://img.shields.io/badge/Tests-28%20passing-green)](https://github.com/jeffpaul/rsl-wp/actions) [![Try Live](https://img.shields.io/badge/Try%20Live-WordPress%20Playground-blue?logo=wordpress)](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fjeffpaul%2Frsl-wp%2Fmain%2Fblueprint.json)

Complete Really Simple Licensing (RSL) support for WordPress sites. Define machine-readable licensing terms for your content, enabling AI companies, crawlers, and other automated systems to properly license your digital assets.

## Installation

**Latest Release:** [v0.0.5-alpha](https://github.com/jeffpaul/rsl-wp/releases/tag/v0.0.5-alpha) • **Size:** ~375KB

```bash
# Direct download via curl/wget
curl -L -o rsl-licensing.zip "https://github.com/jeffpaul/rsl-wp/releases/latest/download/rsl-licensing-0.0.5.zip"
wget -O rsl-licensing.zip "https://github.com/jeffpaul/rsl-wp/releases/latest/download/rsl-licensing-0.0.5.zip"

# Or build from source
git clone https://github.com/jeffpaul/rsl-wp.git
cd rsl-wp  
make zip  # Creates production-ready ZIP file
```

**Installation:** 
1. Download ZIP using the button above or from [Releases](https://github.com/jeffpaul/rsl-wp/releases)
2. Upload via WordPress Admin → Plugins → Add New → Upload Plugin
3. Activate and configure at RSL → Dashboard

> **Alpha Notice:** This is an early alpha release (v0.0.5-alpha) of the RSL for WordPress plugin. While feature-complete and RSL 1.0 specification compliant, it is intended for testing and development purposes. Please report any issues or feedback via GitHub Issues.

## What is RSL (Really Simple Licensing)?

**Really Simple Licensing (RSL)** is an open standard for machine-readable content licensing, making it easy for AI companies, crawlers, and other automated systems to understand how they can legally use your content.

### Key RSL Resources

- **[RSL Standard Website](https://rslstandard.org)** - Official specification and documentation
- **[Plugin Documentation](https://github.com/jeffpaul/rsl-wp/blob/main/README.md)** - Complete setup and usage guide
- **[Payment Integration Guide](https://github.com/jeffpaul/rsl-wp/blob/main/docs/PAYMENTS.md)** - WooCommerce and OAuth setup
- **[Developer API Guide](https://github.com/jeffpaul/rsl-wp/blob/main/docs/DEVELOPER.md)** - REST API and integration details
- **[GitHub Issues](https://github.com/jeffpaul/rsl-wp/issues)** - Support and bug reporting

### How RSL Works

RSL transforms traditional copyright notices into **machine-readable licensing terms**:

```xml
<!-- Traditional copyright notice -->
© 2024 Your Name. All rights reserved.

<!-- RSL machine-readable equivalent -->
<rsl xmlns="https://rslstandard.org/rsl">
  <content url="/">
    <license>
      <permits type="usage">search</permits>
      <prohibits type="usage">train-ai</prohibits>
      <payment type="subscription">
        <amount currency="USD">99.99</amount>
      </payment>
      <server url="https://yoursite.com/wp-json/rsl-olp/v1"/>
    </license>
  </content>
</rsl>
```

This enables:
- **AI companies** to automatically discover licensing terms
- **Search engines** to understand usage permissions  
- **Legal compliance** for automated content usage
- **Fair compensation** through built-in payment systems

## Features

### Core RSL Implementation
- **Full RSL 1.0 Specification Support** - Complete implementation of the official RSL standard
- **License Management System** - Create, edit, and manage multiple license configurations
- **Global and Per-Post Licensing** - Set site-wide licenses or override for specific content
- **Advanced Permission Controls** - Define permitted/prohibited usage, user types, and geographic restrictions

### Multiple Integration Methods
- **HTML Head Injection** - Automatically embed RSL licenses in page headers
- **HTTP Link Headers** - Add RSL license information to HTTP responses
- **robots.txt Integration** - Extend robots.txt with RSL License directives and AI Preferences compatibility
- **RSS Feed Enhancement** - Add RSL licensing to RSS feeds and create dedicated RSL feeds
- **Media File Metadata** - Embed RSL licenses directly in uploaded images, PDFs, and other media

### Professional Admin Interface
- **Intuitive License Builder** - Visual interface for creating complex licensing terms
- **Live XML Generation** - Preview and download RSL XML files
- **License Validation** - Built-in validation and error checking
- **Bulk Management** - Manage multiple licenses efficiently

### Developer Features
- **REST API Endpoints** - Programmatic access to license data
- **WordPress Abilities API** - Standardized interface for AI agents and automated systems
- **License Server Compatibility** - Integration with RSL License Servers
- **OAuth 2.0 Authentication** - Secure client credential authentication for paid licenses
- **Token Revocation System** - JWT-based tokens with automatic refund/cancellation handling
- **Rate Limiting Protection** - Built-in abuse prevention for API endpoints
- **Shortcodes** - Display license information anywhere on your site


## Quick Start

### 1. Create Your First License

1. Go to **RSL > Add New License**
2. Fill in basic information:
   - **Name**: "Site Content License"
   - **Content URL**: "/" (for entire site)
   - **Payment Type**: Choose appropriate option
3. Configure permissions as needed
4. Save the license

### 2. Set Global License

1. Navigate to **RSL > Dashboard**
2. Select your license from the "Global License" dropdown
3. Enable desired integration methods
4. Save settings

### 3. Verify Implementation

- Check your site's source code for RSL `<script>` tags
- Visit `yoursite.com/robots.txt` to see RSL directives
- Access `yoursite.com/?rsl_feed=1` for your RSL feed

## Payment Processing Architecture

RSL uses a **modular payment processor architecture** that supports multiple payment systems:

### WooCommerce Integration (Primary)

WooCommerce handles **all payment gateways** (Stripe, PayPal, Square, etc.) as a first-class citizen:

1. **Install WooCommerce** plugin and complete setup wizard
2. **Configure your payment gateways** in WooCommerce (Stripe, PayPal, etc.)
3. **Create paid license** in Settings > Add RSL License:
   - Set **Payment Type**: "Purchase" or "Subscription" 
   - Add **Amount**: e.g., 99.99
   - Set **Server URL**: `https://yoursite.com/wp-json/rsl-olp/v1`
4. **RSL automatically creates** hidden WooCommerce products
5. **AI companies pay** through your WooCommerce checkout using any configured gateway

### OAuth 2.0 Client Credentials (Simplified)

For **paid licenses**, RSL implements **OAuth 2.0 Client Credentials Grant only** - a simplified approach focused on API authentication rather than user authorization:

```bash
# 1. Register OAuth client (admin interface or CLI)
# Returns client_id and client_secret

# 2. Request access token with client credentials
curl -X POST https://yoursite.com/wp-json/rsl-olp/v1/token \
  -H "Authorization: Basic $(echo -n 'client_id:client_secret' | base64)" \
  -H "Content-Type: application/json" \
  -d '{
    "license_id": 1,
    "resource": "https://yoursite.com/content",
    "client": "my-ai-company"
  }'

# 3. Use token for licensed content access
curl -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
     https://yoursite.com/licensed-content
```

**Free licenses** remain authentication-free for maximum accessibility.

### Session-Based Payment Flow (MCP-Inspired)

For complex payment scenarios, RSL supports session-based flows:

```javascript
// 1. Create payment session
const session = await fetch('/wp-json/rsl-olp/v1/session', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ license_id: 2, client: 'openai-crawler' })
});

// 2. Poll session status until payment complete
const { session_id, polling_url } = await session.json();
// ... polling logic with exponential backoff ...

// 3. Extract token from completed session
const final = await fetch(`/wp-json/rsl-olp/v1/session/${session_id}`);
const { token } = await final.json();
```

### Extensible Architecture

The system supports additional payment processors through a plugin interface:

- **WooCommerce Processor**: Handles all WooCommerce payment gateways
- **Custom Processors**: Third-party plugins can add new payment methods
- **Enterprise Processors**: Direct integrations for large-scale licensing

**For detailed setup, pricing models, and business use cases, see [Payment Integration Guide](docs/PAYMENTS.md)**

## OAuth Client Management & Security

### OAuth 2.0 Client Registration

RSL uses **only the OAuth 2.0 Client Credentials Grant** - the simplest OAuth flow designed for machine-to-machine authentication. This is **not a full OAuth server** - just API key authentication with OAuth-standard formatting. **Paid licenses require authentication**, while **free licenses remain open** for maximum accessibility.

#### Creating OAuth Clients

**Method 1: WordPress Admin Interface**
1. Navigate to **RSL > OAuth Clients** (if available)
2. Click "Add New Client"
3. Enter client name (e.g., "OpenAI Crawler")
4. Copy the generated `client_id` and `client_secret` (shown only once)
5. Provide credentials to the AI company securely

**Method 2: WordPress CLI** (if available)
```bash
wp rsl oauth create-client "AI Company Name"
# Returns: client_id and client_secret
```

#### Client Credential Security
- **Client secrets are hashed** using WordPress password functions
- **Secrets shown only once** during creation
- **Client IDs are unique** and cannot be duplicated
- **Rate limiting applied** per client to prevent abuse

### Rate Limiting & Abuse Prevention

RSL implements comprehensive rate limiting to protect your licensing endpoints:

| Endpoint | Rate Limit | Purpose |
|----------|------------|---------|
| `/token` | 30 requests/minute | Prevent token minting abuse |
| `/introspect` | 100 requests/minute | Allow reasonable token validation |
| `/session` | 20 requests/minute | Session creation throttling |

**Rate limit headers** are included in all responses:
```
X-RateLimit-Limit: 30
X-RateLimit-Remaining: 25
X-RateLimit-Reset: 1672531200
```

**HTTP 429** responses include retry information:
```json
{
  "code": "rate_limit_exceeded",
  "message": "Rate limit exceeded. Maximum 30 requests per minute allowed.",
  "data": {
    "status": 429,
    "headers": {
      "Retry-After": "60"
    }
  }
}
```

### Token Revocation System

RSL implements comprehensive token revocation for refunds and cancellations:

#### Automatic Revocation Triggers
- **WooCommerce Order Refunded** → All order tokens revoked
- **WooCommerce Order Cancelled** → All order tokens revoked  
- **WooCommerce Subscription Cancelled** → All subscription tokens revoked
- **WooCommerce Subscription Expired** → All subscription tokens revoked

#### Token Validation
Every token includes a `jti` (JWT ID) claim for unique identification:
```json
{
  "iss": "https://yoursite.com",
  "sub": "client_abc123",
  "jti": "550e8400-e29b-41d4-a716-446655440000",
  "lic": 123,
  "exp": 1672617600
}
```

The `/introspect` endpoint checks both **expiration** and **revocation status**:
```bash
curl -X POST https://yoursite.com/wp-json/rsl-olp/v1/introspect \
  -H "Authorization: Basic $(echo -n 'client_id:client_secret' | base64)" \
  -d '{"token": "eyJ0eXAiOiJKV1Q..."}'

# Returns:
{
  "active": false  // if expired or revoked
}
```

### Security Best Practices

#### For Site Owners
1. **Store JWT secrets in wp-config.php**: `define('RSL_JWT_SECRET', 'your-secret');`
2. **Regularly rotate OAuth client credentials** for high-value licenses
3. **Monitor rate limit violations** in server logs
4. **Use HTTPS only** for all licensing endpoints
5. **Configure CORS carefully** - avoid wildcard origins

#### For AI Companies  
1. **Securely store client credentials** - never commit to version control
2. **Implement exponential backoff** for rate-limited requests
3. **Cache tokens until expiration** - don't request new tokens unnecessarily
4. **Handle HTTP 429 responses gracefully** with retry logic
5. **Include meaningful client identifiers** in requests

### Error Responses & Troubleshooting

RSL returns standard OAuth 2.0 error responses:

```json
{
  "error": "invalid_client",
  "error_description": "Client authentication failed"
}
```

Common errors:
- `invalid_client`: Wrong credentials or inactive client
- `invalid_license`: License not found or inactive  
- `invalid_resource`: Resource not covered by license
- `rate_limit_exceeded`: Too many requests
- `invalid_request`: Missing required parameters

## License Types & Use Cases

### Free Content
```xml
<rsl xmlns="https://rslstandard.org/rsl">
  <content url="/">
    <license>
      <payment type="free"/>
    </license>
  </content>
</rsl>
```

### AI Training Restrictions
```xml
<rsl xmlns="https://rslstandard.org/rsl">
  <content url="/">
    <license>
      <prohibits type="usage">train-ai,train-genai</prohibits>
      <payment type="free"/>
    </license>
  </content>
</rsl>
```

### Commercial Licensing
```xml
<rsl xmlns="https://rslstandard.org/rsl">
  <content url="/">
    <license>
      <permits type="usage">train-ai</permits>
      <payment type="subscription">
        <amount currency="USD">99.99</amount>
        <custom>https://example.com/licensing</custom>
      </payment>
      <server url="https://yoursite.com/wp-json/rsl-olp/v1"/>
    </license>
  </content>
</rsl>
```

### Attribution Required
```xml
<rsl xmlns="https://rslstandard.org/rsl">
  <content url="/images/">
    <license>
      <permits type="usage">all</permits>
      <payment type="attribution">
        <standard>https://creativecommons.org/licenses/by/4.0/</standard>
      </payment>
    </license>
  </content>
</rsl>
```

## Usage Examples

### Shortcodes

Display license information anywhere:

```php
// Show license link
[rsl_license format="link" text="View License"]

// Display license details
[rsl_license format="info"]

// Show XML (for developers)
[rsl_license format="xml"]
```

### Template Functions

```php
// Check if content has RSL license
if (function_exists('rsl_has_license')) {
    if (rsl_has_license()) {
        echo 'This content is protected by RSL licensing';
    }
}

// Get license information
$license_info = rsl_get_license_info();
if ($license_info) {
    echo 'License: ' . $license_info['name'];
}
```

## Configuration Options

### Global Settings

- **Global License** - Apply a license site-wide
- **HTML Injection** - Embed licenses in page headers
- **HTTP Headers** - Add Link headers to responses
- **robots.txt Integration** - Extend robots.txt with RSL directives
- **RSS Enhancement** - Add RSL to feed items
- **Media Metadata** - Embed licenses in uploaded files

### License Properties

#### Content & Server
- **Content URL** - Path or URL pattern (supports wildcards)
- **Server URL** - Optional RSL License Server
- **Encryption** - Mark content as encrypted

#### Permissions
- **Usage Types** - `all`, `train-ai`, `train-genai`, `ai-use`, `ai-summarize`, `search`
- **User Types** - `commercial`, `non-commercial`, `education`, `government`, `personal`
- **Geographic** - Country/region codes (ISO 3166-1 alpha-2)

#### Payment & Compensation
- **Payment Types** - `free`, `purchase`, `subscription`, `training`, `crawl`, `inference`, `attribution`
- **Amount & Currency** - Pricing information
- **Standard Licenses** - Link to CC, GPL, etc.
- **Custom Terms** - Your licensing page

#### Legal Information
- **Warranties** - What you guarantee
- **Disclaimers** - Legal protections
- **Copyright** - Rights holder information

## Integration Methods

### HTML Head Injection

Automatically adds RSL licenses to your pages:

```html
<script type="application/rsl+xml">
<rsl xmlns="https://rslstandard.org/rsl">
  <content url="">
    <license>
      <payment type="free"/>
    </license>
  </content>
</rsl>
</script>
```

### HTTP Headers

Adds licensing information to HTTP responses:

```
Link: <https://example.com/?rsl_license=1>; rel="license"; type="application/rsl+xml"
```

### robots.txt Enhancement

```
User-agent: *
Allow: /

# RSL Licensing Directive
License: https://example.com/?rsl_license=1

# AI Content Usage Preferences
Content-Usage: train-ai=n
```

### RSS Feeds

Enhanced feeds with RSL namespace:

```xml
<rss xmlns:rsl="https://rslstandard.org/rsl" version="2.0">
  <channel>
    <item>
      <title>Article Title</title>
      <rsl:content url="https://example.com/article">
        <rsl:license>
          <rsl:payment type="free"/>
        </rsl:license>
      </rsl:content>
    </item>
  </channel>
</rss>
```

## Media File Support

### Supported Formats
- **Images** - JPEG, PNG, TIFF, WebP (XMP metadata)
- **Documents** - PDF (companion RSL files)
- **eBooks** - EPUB (OPF metadata)

### Implementation
RSL metadata is embedded using industry-standard methods:
- XMP for images
- Companion .rsl.xml files for documents
- OPF metadata for EPUB files

## API Reference

### REST Endpoints

#### Public License API
- `GET /wp-json/rsl/v1/licenses` - List all active licenses
- `GET /wp-json/rsl/v1/licenses/{id}` - Get specific license details
- `POST /wp-json/rsl/v1/validate` - Validate content licensing

#### OAuth 2.0 License Server (RSL OLP)
- `POST /wp-json/rsl-olp/v1/token` - Issue access tokens (requires OAuth for paid licenses)
- `POST /wp-json/rsl-olp/v1/introspect` - Validate tokens (requires OAuth client auth)
- `GET /wp-json/rsl-olp/v1/key` - Key delivery endpoint (not implemented)

#### Session Management (MCP-Inspired)
- `POST /wp-json/rsl-olp/v1/session` - Create payment session
- `GET /wp-json/rsl-olp/v1/session/{id}` - Poll session status

### OAuth 2.0 Token Endpoint

**Request Format:**
```bash
POST /wp-json/rsl-olp/v1/token
Authorization: Basic <base64(client_id:client_secret)>
Content-Type: application/json

{
  "license_id": 1,
  "resource": "https://example.com/content",
  "client": "my-company-crawler"
}
```

**Response (Success):**
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "expires_at": "2024-01-01T12:00:00Z",
  "license_url": "https://example.com/rsl-license/1/"
}
```

**Response (Payment Required):**
```json
{
  "checkout_url": "https://example.com/checkout/?add-to-cart=123"
}
```

### Token Introspection Endpoint

**Request:**
```bash
POST /wp-json/rsl-olp/v1/introspect
Authorization: Basic <base64(client_id:client_secret)>
Content-Type: application/json

{
  "token": "eyJ0eXAiOiJKV1Q..."
}
```

**Response (Active Token):**
```json
{
  "active": true,
  "client_id": "rsl_abc123",
  "exp": 1672617600,
  "iat": 1672531200,
  "license_id": 1,
  "scope": "train-ai"
}
```

**Response (Inactive Token):**
```json
{
  "active": false
}
```

### WordPress Abilities API

When the WordPress Abilities API plugin is installed, RSL provides standardized abilities for:
- License management (create, update, delete, list)
- Content licensing validation and XML generation
- Token issuance and authentication

Abilities use semantic descriptions and JSON Schema validation, making them ideal for AI agents and automated systems.

### License Server Endpoints

- `/.well-known/rsl/` - RSL server discovery
- `/rsl-license/{id}/` - Individual license XML
- `/feed/rsl-licenses/` - RSL license feed

## Advanced Features

### License Server Integration

Connect to RSL License Servers for:
- License validation and authentication
- Payment processing
- Content encryption/decryption
- Usage tracking and reporting

### Authentication Support

Enforce license requirements:
```
Authorization: License <token>
```

Returns 401 Unauthorized for unlicensed access with proper WWW-Authenticate headers.

### Bulk Operations

- Import/export license configurations
- Apply licenses to existing content
- Batch media processing

## Troubleshooting

### Common Issues

**License not appearing in HTML**
- Check if HTML injection is enabled in settings
- Verify license is active and properly configured
- Ensure global license is selected or post has specific license

**robots.txt not updated**
- Confirm robots.txt integration is enabled
- Check WordPress permalink settings
- Verify global license is configured

**RSS feeds missing RSL data**
- Enable RSS feed integration in settings
- Check that posts have applicable licenses
- Verify feed URLs are working

### OAuth & Authentication Issues

**"invalid_client" errors**
- Verify client credentials are correct
- Check that client is active in the database
- Ensure Authorization header format: `Basic base64(client_id:client_secret)`
- Confirm HTTPS is being used

**"rate_limit_exceeded" errors**
- Wait for rate limit reset (check `X-RateLimit-Reset` header)
- Implement exponential backoff in client code
- Consider requesting rate limit increase for legitimate use cases
- Check for multiple clients using same credentials

**Token validation failures**
- Verify token hasn't expired (`exp` claim)
- Check if token was revoked due to refund/cancellation
- Ensure token is being sent in correct format: `Authorization: Bearer <token>`
- Validate that resource URL matches license coverage

**Payment/checkout issues**
- Verify WooCommerce is active and configured
- Check that products are being created automatically
- Ensure order status changes are triggering correctly
- Confirm webhook URLs are accessible

### Debug Mode

Add to wp-config.php for debugging:
```php
define('RSL_DEBUG', true);
```

This enables additional logging and validation checks.

## RSL 1.0 Specification Compliance

This plugin implements the complete RSL 1.0 draft specification:

### Elements
- `<rsl>`: Root emitted with xmlns `https://rslstandard.org/rsl`
- `<content>`: Supports absolute URLs and server-relative paths per RFC 9309 (wildcards `*` and `$` supported)
- `<license>`: Complete implementation with all sub-elements
- `<permits>` / `<prohibits>`: Usage (`all`, `train-ai`, `train-genai`, `ai-use`, `ai-summarize`, `search`), user types, and geographic restrictions
- `<payment>`: All types supported: `free`, `purchase`, `subscription`, `training`, `crawl`, `inference`, `attribution`, `royalty`
- `<legal>`: Warranties and disclaimers with controlled vocabulary
- `<schema>`: Schema.org CreativeWork integration
- `<copyright>`: Person/organization with contact information
- `<terms>`: Additional legal terms URL

### Integration Methods
- **HTML head**: `<script type="application/rsl+xml">`
- **HTTP Link header**: Standards-compliant Link headers
- **robots.txt**: License directive + AI Preferences compatibility
- **RSS**: RSL namespace module with per-item licensing
- **Media files**: XMP sidecar and companion file embedding

### Compliance & Best Practices
- Full RSL 1.0 specification implementation
- Proper XML namespace usage
- Standard MIME types and headers
- Compatible with RSL License Servers

### Performance Optimization
- Efficient database queries
- Proper caching headers
- Minimal front-end impact
- Optimized media processing

### Security Considerations
- **OAuth 2.0 client credential authentication** for paid license endpoints
- **JWT token-based authorization** with revocation support
- **Rate limiting** to prevent API abuse and brute force attacks
- **Input validation and sanitization** across all endpoints
- **SQL injection prevention** with prepared statements
- **CORS restrictions** to trusted origins only
- **Secure credential storage** using WordPress password hashing
- **HTTPS enforcement** for authentication endpoints

## Frequently Asked Questions

### Is this compatible with existing copyright notices?
Yes! RSL complements traditional copyright notices by making them machine-readable. You can include copyright holder information in your RSL licenses.

### Can I use this with Creative Commons licenses?
Absolutely! RSL supports standard license references. You can link to CC licenses using the standard URL field.

### Does this work with CDNs?
Yes, RSL licenses are embedded in HTML and HTTP headers, so they work with most CDN configurations.

### Can I license different parts of my site differently?
Yes! You can create multiple licenses with different URL patterns, or override the global license on specific posts/pages.

### Is this compatible with caching plugins?
Yes, RSL works with caching plugins. The licensing information is embedded during page generation.

## Contributing

We welcome contributions! Please:
1. Fork the repository
2. Create a feature branch
3. Follow WordPress coding standards
4. Test thoroughly
5. Submit a pull request

## Support

- **Documentation**: [RSL Standard Website](https://rslstandard.org) | [Plugin Documentation](https://github.com/jeffpaul/rsl-wp)
- **Issues**: Report bugs via GitHub Issues
- **Community**: Join RSL discussions

## License

This plugin is licensed under GPL v2 or later, allowing you to freely use, modify, and redistribute it.

## Changelog

See [CHANGELOG.md](docs/CHANGELOG.md) for detailed version history.

---

**Made for the open web and fair AI training practices.**