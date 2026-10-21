# Testing Guide

This guide covers testing the RSL Licensing plugin, including automated tests, manual testing procedures, and server functionality verification.

## Quick Test Setup

### WordPress Playground CLI Testing

Use WordPress Playground CLI for quick testing without local WordPress setup:

```bash
# Install Playground CLI globally (one-time setup)
npm install -g @wp-playground/cli

# Start WordPress with plugin auto-mounted
npx @wp-playground/cli server --auto-mount

# Plugin will be available at http://127.0.0.1:9400
# Visit wp-admin to configure RSL licenses and test functionality
```

### Automated Test Script

If available, run the automated test suite:

```bash
# In another terminal, run tests against the playground URL
./tests/rsl-server-test.sh http://127.0.0.1:9400

# Or with verbose output
./tests/rsl-server-test.sh http://127.0.0.1:9400 true
```

## Manual Testing Procedures

### 1. Free License Token Flow

Test basic token generation and validation:

```bash
# Generate token for free license
curl -X POST "http://127.0.0.1:9400/wp-json/rsl-olp/v1/token" \
  -H "Content-Type: application/json" \
  -d '{"license_id": 1, "client": "test-client"}'

# Expected response:
# {
#   "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
#   "expires_at": "2025-09-12T22:30:00+00:00",
#   "license_url": "http://127.0.0.1:9400/rsl-license/1/"
# }

# Validate token
curl -X POST "http://127.0.0.1:9400/wp-json/rsl-olp/v1/introspect" \
  -H "Content-Type: application/json" \
  -d '{"token": "YOUR_TOKEN_HERE"}'

# Expected response:
# {
#   "active": true,
#   "payload": {
#     "iss": "http://127.0.0.1:9400",
#     "aud": "127.0.0.1:9400",
#     "sub": "test-client",
#     "lic": 1,
#     "scope": "all",
#     "pattern": "/",
#     "exp": 1726096200
#   }
# }
```

### 2. WooCommerce Integration Testing

Test paid licensing with WooCommerce:

```bash
# Request checkout URL for paid license
curl -X POST "http://127.0.0.1:9400/wp-json/rsl-olp/v1/token" \
  -H "Content-Type: application/json" \
  -d '{"license_id": 2, "create_checkout": true}'

# Expected response:
# {
#   "payment_required": true,
#   "checkout_url": "http://127.0.0.1:9400/checkout/?add-to-cart=123&rsl_client=test-client",
#   "amount": 99.99,
#   "currency": "USD",
#   "payment_type": "purchase"
# }

# After completing payment, get token with order key
curl -X POST "http://127.0.0.1:9400/wp-json/rsl-olp/v1/token" \
  -H "Content-Type: application/json" \
  -d '{"license_id": 2, "wc_order_key": "wc_order_abc123", "client": "test-client"}'

# Expected response:
# {
#   "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
#   "expires_at": "2026-09-12T22:30:00+00:00",
#   "license_url": "http://127.0.0.1:9400/rsl-license/2/",
#   "order_id": 456,
#   "payment_status": "completed"
# }
```

### 3. Crawler Authentication Testing

Test crawler-only authentication:

```bash
# Request as crawler without token (should get 401 if license has server_url)
curl -A "GPTBot" "http://127.0.0.1:9400/protected-content"

# Expected: 401 Unauthorized with WWW-Authenticate header
# WWW-Authenticate: License realm="RSL", uri="http://127.0.0.1:9400/wp-json/rsl-olp/v1/token"

# Request with valid token
curl -A "GPTBot" \
  -H "Authorization: License YOUR_TOKEN_HERE" \
  "http://127.0.0.1:9400/protected-content"

# Expected: 200 OK (content served normally)

# Verify human visitors are never blocked
curl -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" \
  "http://127.0.0.1:9400/protected-content"

# Expected: 200 OK (no authentication challenge)
```

### 4. External Server Testing

Test external license server detection:

```bash
# Create license with external server_url pointing to RSL Collective or custom server
# Then test token request
curl -X POST "http://127.0.0.1:9400/wp-json/rsl-olp/v1/token" \
  -H "Content-Type: application/json" \
  -d '{"license_id": 3, "client": "test-client"}'

# Expected: 409 Conflict with redirection
# {
#   "error": "external_server", 
#   "server_url": "https://rslcollective.org/api",
#   "message": "License managed by external server"
# }
```

## RSL Server Implementation Tests

### JWT Token System Testing

✅ **Verify Token Generation**
- Firebase/php-jwt library with HS256 fallback
- Configurable TTL (default 1 hour)
- Proper audience and pattern validation

✅ **WooCommerce Integration**
- Auto-creates hidden virtual products for paid licenses
- Supports purchase (one-time) payment flow
- Subscription support with WooCommerce Subscriptions
- Currency validation against store settings

✅ **External Server Support**
- Detects when license points to external server
- Returns 409 with server_url for client redirection
- Maintains compatibility with RSL Collective and other providers

✅ **Security Features**
- Crawler-only authentication challenges
- JWT signature validation
- Token expiration and audience checks
- URL pattern matching validation

## Manual Testing Checklist

Complete this checklist for thorough testing:

- [ ] **Plugin Setup**
  - [ ] Install plugin and activate
  - [ ] Create free license with server_url = `http://yoursite.test/wp-json/rsl-olp/v1`
  - [ ] Create paid license with WooCommerce integration

- [ ] **Free License Flow**
  - [ ] Test free token generation via `/token` endpoint
  - [ ] Test token validation via `/introspect` endpoint
  - [ ] Verify token expiration handling

- [ ] **Paid License Flow**
  - [ ] Test checkout URL generation
  - [ ] Complete test purchase in WooCommerce
  - [ ] Verify token generation after payment
  - [ ] Test subscription renewals (if using subscriptions)

- [ ] **Crawler Authentication**
  - [ ] Test crawler authentication with valid/invalid tokens
  - [ ] Verify human visitors are never blocked
  - [ ] Test various crawler user agents

- [ ] **Integration Testing**
  - [ ] Verify RSL XML appears in HTML head
  - [ ] Check robots.txt integration
  - [ ] Test RSS feed enhancement
  - [ ] Validate API endpoints

- [ ] **External Servers**
  - [ ] Test external server detection (license with external server_url)
  - [ ] Verify proper redirection responses

## Test Coverage

The automated test suite covers:

### Core Functionality
- JWT token generation and validation
- License CRUD operations
- XML generation and validation
- URL pattern matching

### Integration Points  
- RSL XML output in HTML, robots.txt, and RSS
- REST API endpoints and error handling
- WordPress hooks and filters
- Database operations

### Payment Processing
- WooCommerce integration hooks
- Payment verification flows
- Subscription handling
- Currency validation

### Security & Authentication
- Crawler authentication framework
- Token expiration and validation
- Authorization header processing
- Rate limiting (if implemented)

### External Compatibility
- External server compatibility
- RSL Collective integration
- Standard compliance testing

## Debugging & Troubleshooting

### Enable Debug Mode

Add to wp-config.php:

```php
define('RSL_DEBUG', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Common Test Failures

**❌ Token generation fails**
- Check PHP version (requires 8.0+)
- Verify JWT library is loaded
- Check server timezone settings

**❌ WooCommerce integration broken**
- Confirm WooCommerce is active
- Verify product creation permissions
- Check currency settings match

**❌ Crawler authentication not working**
- Verify user agent detection
- Check Authorization header parsing
- Confirm license has server_url set

**❌ External server tests fail**
- Verify network connectivity
- Check SSL certificate validation
- Confirm external server is responding

### Log Analysis

Check `/wp-content/debug.log` for:
- RSL token generation attempts
- WooCommerce order processing
- External server communication
- Authentication failures

Example log entries:
```
[12-Sep-2025 10:30:00 UTC] RSL: Token generated for client 'test-client', license 1
[12-Sep-2025 10:31:00 UTC] RSL: Crawler 'GPTBot' authenticated successfully
[12-Sep-2025 10:32:00 UTC] RSL: WooCommerce order 456 completed, license 2 activated
```