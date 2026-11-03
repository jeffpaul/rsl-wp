# RSL Payment Integration & WooCommerce Setup

This guide covers setting up paid licensing with RSL, including WooCommerce integration, payment flows, and business use cases.

## Overview

RSL supports multiple payment models for content licensing:
- **Free**: No payment required
- **Purchase**: One-time payment
- **Subscription**: Recurring payments
- **Attribution**: Credit/attribution required
- **Training**: AI training-specific licensing
- **Crawl**: Web crawling permissions
- **Inference**: AI inference usage rights

## WooCommerce Integration

### Prerequisites

1. **WordPress Requirements**
   - WordPress 5.0+
   - RSL Licensing plugin installed and activated

2. **WooCommerce Setup**
   - Install WooCommerce plugin
   - Complete WooCommerce setup wizard
   - Configure payment gateways (PayPal, Stripe, etc.)
   - Set your store currency

### Step-by-Step WooCommerce Setup

#### 1. Install WooCommerce

```bash
# Via WP-CLI
wp plugin install woocommerce --activate

# Or through WordPress admin:
# Plugins > Add New > Search "WooCommerce" > Install & Activate
```

#### 2. Create Paid Licenses

1. Go to **Settings > Add RSL License**
2. Fill in license details:
   - **Name**: "AI Training License"
   - **Content URL**: "/" (or specific path)
   - **Payment Type**: "Purchase" or "Subscription"
   - **Amount**: Enter price (e.g., 99.99)
   - **Currency**: Must match WooCommerce store currency
3. **Important**: Set **Server URL** to your built-in server:
   ```
   https://yoursite.com/wp-json/rsl-olp/v1
   ```
4. Save the license

#### 3. Enable License Server

1. Go to **Settings > RSL Licensing**
2. The plugin will automatically:
   - Create hidden WooCommerce products for paid licenses
   - Set up checkout URLs
   - Handle payment verification

### Payment Flow Examples

#### One-Time Purchase Flow

**Business Use Case**: AI company wants to train on your content once

```xml
<rsl xmlns="https://rslstandard.org/rsl">
  <content url="/">
    <license>
      <permits type="usage">train-ai,train-genai</permits>
      <payment type="purchase">
        <amount currency="USD">499.00</amount>
        <custom>https://yoursite.com/ai-training-license</custom>
      </payment>
      <server url="https://yoursite.com/wp-json/rsl-olp/v1"/>
    </license>
  </content>
</rsl>
```

**Customer Journey**:
1. AI crawler requests content licensing token
2. System responds with checkout URL
3. Customer completes WooCommerce purchase
4. System generates access token
5. Crawler can access content with token

#### Subscription Model

**Business Use Case**: Search engine wants ongoing content access

```xml
<rsl xmlns="https://rslstandard.org/rsl">
  <content url="/">
    <license>
      <permits type="usage">search,ai-summarize</permits>
      <payment type="subscription">
        <amount currency="USD">99.99</amount>
        <interval>monthly</interval>
      </payment>
      <server url="https://yoursite.com/wp-json/rsl-olp/v1"/>
    </license>
  </content>
</rsl>
```

**Requirements**:
- WooCommerce Subscriptions plugin (for recurring billing)
- Configured payment gateway supporting subscriptions

### API Integration

RSL now supports both **legacy token requests** and **modern session-based flows** (MCP-inspired).

#### Session-Based Flow (Recommended)

Modern AI agents should use the session-based approach for better security and user experience:

```bash
# 1. Create payment session
curl -X POST "https://yoursite.com/wp-json/rsl-olp/v1/session" \
  -H "Content-Type: application/json" \
  -d '{"license_id": 2, "client": "ai-company-crawler"}'

# Response (Paid License):
# {
#   "session_id": "550e8400-e29b-41d4-a716-446655440000",
#   "status": "awaiting_payment",
#   "polling_url": "https://yoursite.com/wp-json/rsl-olp/v1/session/550e8400-e29b-41d4-a716-446655440000",
#   "checkout_url": "https://yoursite.com/checkout/?add-to-cart=123&rsl_session_id=550e8400-e29b-41d4-a716-446655440000",
#   "processor": "WooCommerce",
#   "expires_at": "2025-09-12T11:30:00Z"
# }

# 2. Poll session status until payment complete
curl "https://yoursite.com/wp-json/rsl-olp/v1/session/550e8400-e29b-41d4-a716-446655440000"

# Response (Payment Complete):
# {
#   "status": "proof_ready",
#   "signed_proof": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
#   "message": "Payment confirmed, use signed_proof to get token"
# }

# 3. Exchange signed proof for access token
curl -X POST "https://yoursite.com/wp-json/rsl-olp/v1/token" \
  -H "Content-Type: application/json" \
  -d '{"signed_proof": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."}'
```

#### Legacy Token Request (Deprecated)

```bash
# For free license
curl -X POST "https://yoursite.com/wp-json/rsl-olp/v1/token" \
  -H "Content-Type: application/json" \
  -d '{"license_id": 1, "client": "crawler-name"}'
```

**Free License Response**:
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "expires_at": "2025-09-12T22:30:00+00:00",
  "license_url": "https://yoursite.com/rsl-license/1/"
}
```

#### Request Paid License

```bash
# For paid license - returns checkout URL
curl -X POST "https://yoursite.com/wp-json/rsl-olp/v1/token" \
  -H "Content-Type: application/json" \
  -d '{"license_id": 2, "client": "ai-company"}'
```

**Paid License Response**:
```json
{
  "payment_required": true,
  "checkout_url": "https://yoursite.com/checkout/?add-to-cart=123&rsl_client=ai-company",
  "amount": 499.00,
  "currency": "USD",
  "payment_type": "purchase"
}
```

#### Complete Purchase & Get Token

```bash
# After successful payment
curl -X POST "https://yoursite.com/wp-json/rsl-olp/v1/token" \
  -H "Content-Type: application/json" \
  -d '{"license_id": 2, "wc_order_key": "wc_order_abc123", "client": "ai-company"}'
```

**Success Response**:
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "expires_at": "2026-09-12T22:30:00+00:00",
  "license_url": "https://yoursite.com/rsl-license/2/",
  "order_id": 456,
  "payment_status": "completed"
}
```

## Business Use Cases & Pricing Models

### 1. AI Training Marketplace

**Target**: AI companies, researchers
**Model**: One-time purchase per dataset/content collection
**Pricing**: $100-$10,000 depending on content volume

```xml
<payment type="purchase">
  <amount currency="USD">2500.00</amount>
  <custom>https://yoursite.com/ai-training-terms</custom>
</payment>
```

### 2. Content Syndication

**Target**: News aggregators, content platforms
**Model**: Monthly/yearly subscriptions
**Pricing**: $50-$500/month

```xml
<payment type="subscription">
  <amount currency="USD">199.00</amount>
  <interval>monthly</interval>
  <custom>https://yoursite.com/syndication-agreement</custom>
</payment>
```

### 3. Search Engine Licensing

**Target**: Search engines, AI assistants
**Model**: Tiered subscriptions based on usage
**Pricing**: $1,000-$50,000/year

```xml
<payment type="subscription">
  <amount currency="USD">5000.00</amount>
  <interval>yearly</interval>
  <usage_limit>1000000</usage_limit>
</payment>
```

### 4. Educational Discounts

**Target**: Universities, research institutions
**Model**: Reduced-rate subscriptions
**Pricing**: 50-80% off commercial rates

```xml
<license>
  <permits type="usage">train-ai,research</permits>
  <permits type="user">education</permits>
  <payment type="subscription">
    <amount currency="USD">99.00</amount>
    <interval>yearly</interval>
    <discount>education</discount>
  </payment>
</license>
```

## Advanced Configuration

### Multiple Payment Tiers

Create different licenses for different use cases:

```xml
<!-- Basic AI Access -->
<license>
  <permits type="usage">ai-summarize</permits>
  <payment type="subscription">
    <amount currency="USD">49.99</amount>
    <interval>monthly</interval>
  </payment>
</license>

<!-- Full Training Rights -->
<license>
  <permits type="usage">train-ai,train-genai</permits>
  <payment type="purchase">
    <amount currency="USD">4999.00</amount>
  </payment>
</license>
```

### Geographic Pricing

Different pricing for different regions:

```xml
<license>
  <permits type="geo">US,CA,EU</permits>
  <payment type="subscription">
    <amount currency="USD">299.00</amount>
    <interval>monthly</interval>
  </payment>
</license>

<license>
  <permits type="geo">developing</permits>
  <payment type="subscription">
    <amount currency="USD">99.00</amount>
    <interval>monthly</interval>
  </payment>
</license>
```

### WooCommerce Subscriptions Integration

For recurring payments, install [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/):

1. **Install WooCommerce Subscriptions**
2. **Configure subscription settings**
3. **RSL will automatically**:
   - Create subscription products
   - Handle subscription renewals
   - Revoke access on cancellation

## Troubleshooting

### Common Issues

**❌ "Checkout URL not generated"**
- Verify WooCommerce is installed and activated
- Check that license has payment amount set
- Ensure server URL points to built-in server

**❌ "Payment completed but no token"**
- Check WooCommerce order status (must be 'completed')
- Verify order key is correct
- Check RSL server logs for errors

**❌ "Currency mismatch error"**
- License currency must match WooCommerce store currency
- Change either in Settings > General (WooCommerce) or license settings

**❌ "Token expired immediately"**
- Check server timezone settings
- Verify JWT token generation (look for PHP errors)
- Ensure license is still active

### Debug Mode

Enable debugging in wp-config.php:

```php
define('RSL_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs at `/wp-content/debug.log` for detailed error information.

### Testing Payment Flow

Use WooCommerce's test mode:

1. **Enable test payments** in WooCommerce settings
2. **Use test credit cards** (varies by payment gateway)
3. **Test the complete flow**:
   ```bash
   # Request checkout URL
   curl -X POST "https://yoursite.test/wp-json/rsl-olp/v1/token" \
     -H "Content-Type: application/json" \
     -d '{"license_id": 2, "create_checkout": true, "client": "test-client"}'
   
   # Complete test purchase in browser
   # Then get token with order key
   ```

## Integration Examples

### WordPress Hooks

```php
// Register a custom payment processor
add_action('rsl_register_payment_processors', function($registry) {
    $registry->register_processor(new My_Payment_Processor());
});

// Let orders created by hand (with no OAuth client recorded) be redeemed for a token.
// By default only the client that went through the token endpoint's checkout can redeem an order,
// so this loosens a security check: anyone who has the order key could then redeem it.
add_filter('rsl_verify_order_client', function($verified, $order, $client_id, $license, $bound_client) {
    return $verified || '' === $bound_client;
}, 10, 5);
```

See [the hooks reference in DEVELOPER.md](DEVELOPER.md#wordpress-hooks--filters) for every available hook.

### External License Servers

For advanced payment processing, point to external servers:

```xml
<server url="https://rslcollective.org/api"/>
```

The plugin will redirect payment requests to the external server while maintaining local license definitions.

## Support & Resources

- **WooCommerce Documentation**: https://woocommerce.com/documentation/
- **RSL Standard**: https://rslstandard.org
- **Payment Gateway Setup**: Varies by provider (PayPal, Stripe, etc.)
- **RSL Collective**: https://rslcollective.org (managed license server)

---

**Need help?** Check the [main documentation](../README.md) or [developer guide](DEVELOPER.md) for additional information.