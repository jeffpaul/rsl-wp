# Developer Guide & API Reference

This comprehensive guide covers the RSL Licensing plugin's architecture, APIs, hooks, and integration patterns for developers.

## Plugin Architecture

### Core Classes

The plugin uses a modular class-based architecture:

#### `RSL_License` - License Management
- **Purpose**: CRUD operations and XML generation
- **Location**: `includes/class-rsl-license.php`
- **Key Methods**:
  - `create()` - Create new license
  - `get_by_id($id)` - Retrieve license
  - `generate_xml()` - Generate RSL XML
  - `matches_url($url)` - URL pattern matching

#### `RSL_Admin` - WordPress Admin Integration
- **Purpose**: Admin UI, AJAX handlers, Gutenberg panels
- **Location**: `includes/class-rsl-admin.php`
- **Features**:
  - License management interface
  - Post/page meta boxes
  - AJAX license operations
  - Settings pages

#### `RSL_Frontend` - Public Integration
- **Purpose**: HTML injection and HTTP headers
- **Location**: `includes/class-rsl-frontend.php`
- **Functions**:
  - `<script type="application/rsl+xml">` injection
  - HTTP Link header generation
  - Template integration

#### `RSL_Robots` - robots.txt Integration
- **Purpose**: robots.txt enhancement with RSL directives
- **Location**: `includes/class-rsl-robots.php`
- **Features**:
  - License directive injection
  - AI Preferences compatibility

#### `RSL_RSS` - Feed Integration  
- **Purpose**: RSS feed enhancement and RSL feeds
- **Location**: `includes/class-rsl-rss.php`
- **Capabilities**:
  - RSS namespace integration
  - Dedicated RSL license feeds
  - Per-item licensing

#### `RSL_Media` - Media File Integration
- **Purpose**: XMP/sidecar metadata embedding
- **Location**: `includes/class-rsl-media.php`
- **Support**: JPEG, PNG, TIFF, WebP, PDF, EPUB

#### `RSL_Server` - License Server
- **Purpose**: Server functionality and authentication
- **Location**: `includes/class-rsl-server.php`
- **Features**:
  - JWT token generation/validation
  - Modular payment processor integration
  - MCP-inspired session management
  - Crawler authentication
  - REST endpoints

#### `RSL_Payment_Registry` - Payment Processor Management
- **Purpose**: Manages available payment processors
- **Location**: `includes/class-rsl-payment-registry.php`
- **Features**:
  - Auto-discovery of payment processors
  - Extensible processor registration
  - Payment capability detection

#### `RSL_Session_Manager` - Session State Management
- **Purpose**: MCP-inspired session handling for AI agents
- **Location**: `includes/class-rsl-session-manager.php`
- **Features**:
  - Server-side session storage
  - Session lifecycle management
  - Automatic cleanup of expired sessions

#### `RSL_WooCommerce_Processor` - WooCommerce Integration
- **Purpose**: Handles all WooCommerce payment gateways
- **Location**: `includes/processors/class-rsl-woocommerce-processor.php`
- **Features**:
  - Automatic product creation for licenses
  - Support for all WooCommerce payment gateways (Stripe, PayPal, etc.)
  - Purchase and subscription payment types
  - Signed payment proof generation

## WordPress Hooks & Filters

### Available Filters

#### `rsl_crawler_ua_needles`
Filter crawler User-Agent detection patterns:

```php
add_filter('rsl_crawler_ua_needles', function($needles) {
    $needles[] = 'MyCustomBot';
    $needles[] = 'AI-Crawler';
    return $needles;
});
```

#### `rsl_supported_post_types`
Extend post types that show RSL meta boxes:

```php
add_filter('rsl_supported_post_types', function($post_types) {
    $post_types[] = 'product';
    $post_types[] = 'portfolio';
    return $post_types;
});
```

#### `rsl_license_price`
Modify license pricing dynamically:

```php
add_filter('rsl_license_price', function($price, $license_id, $client) {
    // Educational discount
    if (str_contains($client, '.edu')) {
        return $price * 0.5;
    }
    // Volume discount for enterprise
    if (str_contains($client, 'enterprise')) {
        return $price * 0.7;
    }
    return $price;
}, 10, 3);
```

#### `rsl_xml_output`
Filter generated RSL XML before output:

```php
add_filter('rsl_xml_output', function($xml, $license_id) {
    // Add custom elements or modify XML structure
    return $xml;
}, 10, 2);
```

### Available Actions

#### `rsl_license_created`
Triggered when a new license is created:

```php
add_action('rsl_license_created', function($license_id, $license_data) {
    // Send notification, log creation, etc.
    wp_mail(
        get_option('admin_email'),
        'New RSL License Created',
        "License ID: $license_id"
    );
}, 10, 2);
```

#### `rsl_license_updated`
Triggered after a license is updated, when at least one stored value changed. Receives the license ID, the sanitized fields that were written (including `updated_at`), and the license as it was before the update. Licenses are updated in place, so listen here if you need a history of changes:

```php
add_action('rsl_license_updated', function($license_id, $changes, $old_license) {
    // Record what changed and what it was before
    foreach ($changes as $field => $value) {
        if (isset($old_license[$field]) && $old_license[$field] != $value) {
            error_log("RSL license $license_id: $field changed from {$old_license[$field]} to $value");
        }
    }
}, 10, 3);
```

#### `rsl_payment_completed`
Triggered when a payment is completed:

```php
add_action('rsl_payment_completed', function($order_id, $license_id, $client) {
    // Custom post-payment processing
    update_option("rsl_stats_payments", get_option("rsl_stats_payments", 0) + 1);
}, 10, 3);
```

#### `rsl_token_generated`
Triggered when an access token is generated:

```php
add_action('rsl_token_generated', function($token, $license_id, $client) {
    // Log token generation for analytics
    error_log("RSL token generated for $client on license $license_id");
}, 10, 3);
```

## URL Pattern Matching

The plugin uses flexible URL pattern matching:

### Pattern Types

- **Absolute URL**: `https://example.com/blog/` - matches full URL
- **Server-relative**: `/blog/` - matches against request path
- **Wildcard patterns**: `/images/*` - matches any path starting with `/images/`
- **File extensions**: `*.pdf` - matches all PDF files
- **End anchors**: `/api/*$` - matches paths ending with the pattern

### Pattern Examples

```php
// Match entire site
$pattern = '/';

// Match specific directory and subdirectories
$pattern = '/blog/*';

// Match file types
$pattern = '*.jpg';
$pattern = '*.{jpg,png,gif}'; // Multiple extensions

// Match API endpoints
$pattern = '/wp-json/wp/v2/*';

// Match with end anchor
$pattern = '/download/*$';
```

### Custom Pattern Matching

```php
function my_custom_pattern_matcher($url, $pattern) {
    // Custom logic for pattern matching
    if ($pattern === 'premium_content') {
        return is_user_logged_in() && user_can('access_premium');
    }
    
    // Fall back to default pattern matching
    return RSL_License::matches_pattern($url, $pattern);
}

add_filter('rsl_pattern_matcher', 'my_custom_pattern_matcher', 10, 2);
```

## REST API Reference

### Core Endpoints

#### `GET /wp-json/rsl/v1/licenses`
List all active licenses.

**Parameters:**
- `per_page` (int): Number of licenses per page (default: 10)
- `page` (int): Page number (default: 1)
- `status` (string): Filter by status (`active`, `inactive`)

**Response:**
```json
{
  "licenses": [
    {
      "id": 1,
      "name": "Site Content License",
      "content_url": "/",
      "payment_type": "free",
      "created": "2025-09-12T10:00:00Z",
      "xml_url": "https://example.com/rsl-license/1/"
    }
  ],
  "total": 1,
  "pages": 1
}
```

#### `GET /wp-json/rsl/v1/licenses/{id}`
Get specific license details.

**Response:**
```json
{
  "id": 1,
  "name": "AI Training License",
  "content_url": "/",
  "payment_type": "purchase",
  "amount": 499.00,
  "currency": "USD",
  "permits": ["train-ai", "train-genai"],
  "server_url": "https://example.com/wp-json/rsl-olp/v1",
  "xml": "<rsl xmlns=\"https://rslstandard.org/rsl\">...</rsl>"
}
```

#### `POST /wp-json/rsl/v1/validate`
Validate license coverage for content.

**Request Body:**
```json
{
  "content_url": "https://example.com/blog/post",
  "usage_type": "train-ai",
  "user_type": "commercial"
}
```

**Response:**
```json
{
  "valid": true,
  "matches": [
    {
      "license_id": 1,
      "name": "Blog Content License",
      "pattern": "/blog/*",
      "payment_required": false,
      "xml_url": "https://example.com/rsl-license/1/"
    }
  ]
}
```

### License Server Endpoints

#### `POST /wp-json/rsl-olp/v1/token`
Generate access tokens for content licensing.

**Request Body:**
```json
{
  "license_id": 1,
  "client": "ai-company-crawler",
  "create_checkout": false
}
```

**Response (Free License):**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "expires_at": "2025-09-12T22:30:00+00:00",
  "license_url": "https://example.com/rsl-license/1/",
  "scope": "all"
}
```

**Response (Paid License):**
```json
{
  "payment_required": true,
  "checkout_url": "https://example.com/checkout/?add-to-cart=123",
  "amount": 499.00,
  "currency": "USD",
  "payment_type": "purchase"
}
```

#### `POST /wp-json/rsl-olp/v1/introspect`
Validate access tokens.

**Request Body:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
}
```

**Response:**
```json
{
  "active": true,
  "payload": {
    "iss": "https://example.com",
    "aud": "example.com",
    "sub": "ai-company-crawler",
    "lic": 1,
    "scope": "train-ai",
    "pattern": "/",
    "exp": 1726096200
  }
}
```

#### `POST /wp-json/rsl-olp/v1/session`
Create payment session (MCP-inspired).

**Request Body:**
```json
{
  "license_id": 2,
  "client": "ai-company-crawler"
}
```

**Response (Free License):**
```json
{
  "session_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "completed",
  "polling_url": "https://example.com/wp-json/rsl-olp/v1/session/550e8400-e29b-41d4-a716-446655440000",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "expires_at": "2025-09-12T22:30:00Z"
}
```

**Response (Paid License):**
```json
{
  "session_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "awaiting_payment",
  "polling_url": "https://example.com/wp-json/rsl-olp/v1/session/550e8400-e29b-41d4-a716-446655440000",
  "checkout_url": "https://example.com/checkout/?add-to-cart=123&rsl_session_id=550e8400-e29b-41d4-a716-446655440000",
  "processor": "WooCommerce",
  "expires_at": "2025-09-12T11:30:00Z"
}
```

#### `GET /wp-json/rsl-olp/v1/session/{session_id}`
Poll session status (MCP-inspired).

**Response (Awaiting Payment):**
```json
{
  "session_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "awaiting_payment",
  "checkout_url": "https://example.com/checkout/?add-to-cart=123",
  "message": "Payment required",
  "created_at": "2025-09-12T10:30:00Z",
  "expires_at": "2025-09-12T11:30:00Z"
}
```

**Response (Payment Complete):**
```json
{
  "session_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "proof_ready",
  "signed_proof": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "message": "Payment confirmed, use signed_proof to get token",
  "created_at": "2025-09-12T10:30:00Z",
  "expires_at": "2025-09-12T11:30:00Z"
}
```

## Discovery Endpoints

### `GET /.well-known/rsl/`
RSL server discovery endpoint.

**Response:**
```json
{
  "server": "RSL for WordPress v1.0.0",
  "site": "https://example.com",
  "endpoints": {
    "licenses": "https://example.com/wp-json/rsl/v1/licenses",
    "validate": "https://example.com/wp-json/rsl/v1/validate",
    "token": "https://example.com/wp-json/rsl-olp/v1/token",
    "introspect": "https://example.com/wp-json/rsl-olp/v1/introspect",
    "session": "https://example.com/wp-json/rsl-olp/v1/session",
    "session_status": "https://example.com/wp-json/rsl-olp/v1/session/{session_id}"
  },
  "feeds": {
    "rsl_licenses": "https://example.com/feed/rsl-licenses/",
    "rsl_query": "https://example.com/?rsl_feed=1"
  }
}
```

### `GET /rsl-license/{id}/`
Individual license XML output.

The response carries a strong `ETag` whose value is the quoted SHA-256 hex digest of the XML body, so a client can record exactly which version of a license it saw. Send it back in `If-None-Match` to get a `304 Not Modified` with no body when the license hasn't changed.

**Response:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<rsl xmlns="https://rslstandard.org/rsl">
  <content url="/">
    <license>
      <permits type="usage">train-ai</permits>
      <payment type="purchase">
        <amount currency="USD">499.00</amount>
      </payment>
      <server url="https://example.com/wp-json/rsl-olp/v1"/>
    </license>
  </content>
</rsl>
```

### `GET /feed/rsl-licenses/`
RSS feed of all licenses.

**Response:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:rsl="https://rslstandard.org/rsl" version="2.0">
  <channel>
    <title>RSL Licenses - Example Site</title>
    <link>https://example.com</link>
    <item>
      <title>Site Content License</title>
      <link>https://example.com/rsl-license/1/</link>
      <rsl:license>
        <rsl:payment type="free"/>
      </rsl:license>
    </item>
  </channel>
</rsl>
```

## Integration Examples

### Theme Integration

#### Display License Information
```php
// In your theme files
function show_content_license() {
    if (function_exists('rsl_get_license_info')) {
        $license = rsl_get_license_info();
        if ($license) {
            echo '<div class="content-license">';
            echo '<p>Content licensed under: ' . esc_html($license['name']) . '</p>';
            if ($license['payment_type'] === 'attribution') {
                echo '<p>Attribution required.</p>';
            }
            echo '</div>';
        }
    }
}

// In single.php or page.php
show_content_license();
```

#### Check License Requirements
```php
function can_ai_train_on_content() {
    if (function_exists('rsl_check_permission')) {
        return rsl_check_permission('train-ai', get_permalink());
    }
    return false;
}

if (can_ai_train_on_content()) {
    echo '<p>AI training permitted on this content.</p>';
} else {
    echo '<p>AI training not permitted.</p>';
}
```

### Payment Processor Extension

#### Creating Custom Payment Processors
```php
// Create a custom payment processor
class Custom_Payment_Processor implements RSL_Payment_Processor_Interface {
    
    public function get_id() {
        return 'custom_processor';
    }
    
    public function get_name() {
        return 'Custom Payment System';
    }
    
    public function is_available() {
        return function_exists('custom_payment_api');
    }
    
    public function get_supported_payment_types() {
        return ['purchase', 'subscription', 'credits'];
    }
    
    public function create_checkout_session($license, $client, $session_id, $options = []) {
        // Implement custom checkout logic
        return [
            'checkout_url' => 'https://custom-processor.com/checkout/' . $session_id
        ];
    }
    
    public function validate_payment_proof($license, $session_id, $proof_data) {
        // Implement custom validation logic
        return true;
    }
    
    public function generate_payment_proof($license, $session_id, $payment_data) {
        // Generate signed proof after payment
        return $this->sign_payment_proof($payment_data);
    }
}

// Register the processor
add_action('rsl_register_payment_processors', function($registry) {
    $registry->register_processor(new Custom_Payment_Processor());
});
```

#### Session-Based Payment Flow Example
```php
// AI agent integration example
function ai_agent_license_flow($license_id, $client_id) {
    // 1. Create session
    $session_response = wp_remote_post(home_url('/wp-json/rsl-olp/v1/session'), [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode([
            'license_id' => $license_id,
            'client' => $client_id
        ])
    ]);
    
    $session_data = json_decode(wp_remote_retrieve_body($session_response), true);
    
    // 2. If payment required, handle checkout
    if ($session_data['status'] === 'awaiting_payment') {
        // Direct user/system to checkout_url
        handle_payment_checkout($session_data['checkout_url']);
        
        // 3. Poll session until complete
        do {
            sleep(2);
            $status_response = wp_remote_get($session_data['polling_url']);
            $status = json_decode(wp_remote_retrieve_body($status_response), true);
        } while ($status['status'] === 'awaiting_payment');
        
        // 4. Get token with signed proof
        if ($status['status'] === 'proof_ready') {
            $token_response = wp_remote_post(home_url('/wp-json/rsl-olp/v1/token'), [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode([
                    'signed_proof' => $status['signed_proof']
                ])
            ]);
            
            return json_decode(wp_remote_retrieve_body($token_response), true);
        }
    }
    
    return $session_data; // Free license returns token immediately
}
```

### Plugin Integration

#### WooCommerce Integration
```php
// Add RSL licensing to WooCommerce products
add_action('woocommerce_single_product_summary', 'add_product_license_info', 25);
function add_product_license_info() {
    global $product;
    
    $product_url = get_permalink($product->get_id());
    $license = rsl_get_license_for_url($product_url);
    
    if ($license) {
        echo '<div class="product-license-info">';
        echo '<h4>Content Licensing</h4>';
        echo '<p>' . esc_html($license['name']) . '</p>';
        echo '</div>';
    }
}

// Custom license creation for products
add_action('woocommerce_product_options_general_product_data', 'add_rsl_product_fields');
function add_rsl_product_fields() {
    woocommerce_wp_select([
        'id' => '_rsl_license_id',
        'label' => 'RSL License',
        'options' => rsl_get_license_options(),
        'desc_tip' => true,
        'description' => 'Select RSL license for this product content'
    ]);
}
```

#### Custom Post Types
```php
// Add RSL support to custom post types
add_action('init', 'add_rsl_to_custom_post_types');
function add_rsl_to_custom_post_types() {
    add_filter('rsl_supported_post_types', function($post_types) {
        $post_types[] = 'portfolio';
        $post_types[] = 'testimonial';
        $post_types[] = 'product';
        return $post_types;
    });
}
```

### JavaScript Integration

#### Frontend License Display
```javascript
// Fetch license information via REST API
async function displayLicenseInfo() {
    const currentUrl = window.location.href;
    
    try {
        const response = await fetch('/wp-json/rsl/v1/validate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                content_url: currentUrl
            })
        });
        
        const data = await response.json();
        
        if (data.valid && data.matches.length > 0) {
            const license = data.matches[0];
            const licenseEl = document.createElement('div');
            licenseEl.className = 'rsl-license-notice';
            licenseEl.innerHTML = `
                <p>This content is licensed under: <strong>${license.name}</strong></p>
                <a href="${license.xml_url}" target="_blank">View License Details</a>
            `;
            document.body.appendChild(licenseEl);
        }
    } catch (error) {
        console.error('Error fetching license info:', error);
    }
}

// Run on page load
document.addEventListener('DOMContentLoaded', displayLicenseInfo);
```

## Local Development Setup

### WordPress Playground
Use the included `.wp-playground.json` configuration:

```bash
npx @wp-playground/cli server --auto-mount
```

### WP-CLI Setup
```bash
# Install WordPress
wp core install --url=http://localhost:8080 --title="RSL Dev Site" --admin_user=admin --admin_password=admin --admin_email=admin@example.com

# Install and activate plugin
wp plugin activate rsl-licensing

# Create test license
wp eval '
$license = RSL_License::create([
    "name" => "Test License",
    "content_url" => "/",
    "payment_type" => "free"
]);
echo "Created license ID: " . $license["id"];
'

# Set as global license
wp option update rsl_global_license_id 1
```

### Testing Integration
```bash
# Test RSL XML output
curl -s http://localhost:8080/ | grep -o '<script type="application/rsl+xml">.*</script>'

# Test robots.txt integration
curl http://localhost:8080/robots.txt

# Test RSS feed
curl http://localhost:8080/?rsl_feed=1

# Test API endpoints
curl http://localhost:8080/wp-json/rsl/v1/licenses
curl http://localhost:8080/.well-known/rsl/
```

## Debugging & Troubleshooting

### Enable Debug Mode
```php
// In wp-config.php
define('RSL_DEBUG', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Custom Debug Functions
```php
// Add to functions.php for debugging
function rsl_debug_license_matching($url = null) {
    if (!$url) $url = esc_url_raw($_SERVER['REQUEST_URI']);
    
    $licenses = RSL_License::get_all();
    foreach ($licenses as $license) {
        $matches = RSL_License::matches_url($url, $license['content_url']);
        echo "License '{$license['name']}' pattern '{$license['content_url']}' " . 
             ($matches ? 'MATCHES' : 'does not match') . " URL '$url'\n";
    }
}

// Debug token generation
function rsl_debug_token_generation($license_id, $client) {
    $token = RSL_Server::generate_token($license_id, $client);
    if ($token) {
        $decoded = RSL_Server::validate_token($token);
        var_dump('Token generated:', $token);
        var_dump('Token payload:', $decoded);
    } else {
        var_dump('Token generation failed');
    }
}
```

### Common Issues & Solutions

**Issue**: License XML not appearing in HTML
```php
// Check if global license is set
$global_license = get_option('rsl_global_license_id');
var_dump('Global license ID:', $global_license);

// Check if HTML injection is enabled
$settings = get_option('rsl_settings', []);
var_dump('HTML injection enabled:', $settings['html_injection'] ?? false);
```

**Issue**: Pattern matching not working
```php
// Test pattern matching manually
$result = RSL_License::matches_url('/blog/post-1/', '/blog/*');
var_dump('Pattern match result:', $result); // Should be true
```

**Issue**: WooCommerce integration problems
```php
// Check if WooCommerce is active
if (class_exists('WooCommerce')) {
    echo "WooCommerce is active\n";
    
    // Check if products are created for licenses
    $licenses = RSL_License::get_all(['payment_type' => ['purchase', 'subscription']]);
    foreach ($licenses as $license) {
        $product_id = get_option("rsl_wc_product_{$license['id']}");
        echo "License {$license['id']} product ID: $product_id\n";
    }
} else {
    echo "WooCommerce not active\n";
}
```

## Performance Considerations

### Caching
```php
// Implement license caching
function get_cached_license($license_id) {
    $cache_key = "rsl_license_$license_id";
    $license = wp_cache_get($cache_key);
    
    if ($license === false) {
        $license = RSL_License::get_by_id($license_id);
        wp_cache_set($cache_key, $license, '', 300); // 5 minutes
    }
    
    return $license;
}
```

### Database Optimization
```php
// Add indexes for better query performance
add_action('plugins_loaded', function() {
    global $wpdb;
    
    // Add index on content_url for faster pattern matching
    $wpdb->query("ALTER TABLE {$wpdb->prefix}rsl_licenses ADD INDEX idx_content_url (content_url)");
    
    // Add index on status for active license queries
    $wpdb->query("ALTER TABLE {$wpdb->prefix}rsl_licenses ADD INDEX idx_status (status)");
});
```

For additional information, see the [Payment Integration Guide](PAYMENTS.md) and [Testing Guide](TESTING.md).