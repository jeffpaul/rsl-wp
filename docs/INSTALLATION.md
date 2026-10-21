# Installation & Setup Guide

## System Requirements

- WordPress 5.0+
- PHP 8.0+  
- MySQL 5.6+ (or MariaDB)
- Web server with rewrite capability

## Installation

### WordPress Admin
1. Download plugin ZIP
2. Go to Plugins > Add New > Upload Plugin
3. Choose ZIP file and Install
4. Activate plugin

### WP-CLI
```bash
wp plugin install rsl-licensing.zip --activate
```

## Quick Setup

1. **Go to Settings > RSL Licensing**
2. **Create first license**: Settings > Add RSL License
   - Name: "Site Content License"  
   - Content URL: "/" (entire site)
   - Configure permissions and payment
3. **Set global license**: Select in Settings > RSL Licensing
4. **Enable integrations**: HTML injection, robots.txt, RSS, etc.
5. **Save settings**

## Verification

- **HTML**: View source for `<script type="application/rsl+xml">`
- **robots.txt**: Visit `/robots.txt` for License directive
- **RSS**: Check feed for `xmlns:rsl` namespace
- **API**: Test `/wp-json/rsl/v1/licenses`

## Advanced Configuration

### URL Patterns
- `/` - Entire site
- `/images/*` - Directory with wildcards
- `*.pdf` - File type patterns
- `/api/*$` - Path with end anchor

### License Server Integration
Set **Server URL** to:
- Built-in: `/wp-json/rsl-olp/v1` 
- RSL Collective: `https://rslcollective.org/api`
- Custom server: Your endpoint URL

### WooCommerce Integration
- Install WooCommerce for paid licensing
- Plugin auto-creates hidden virtual products
- Supports purchase and subscription payment flows

## Troubleshooting

**Plugin won't activate**: Check PHP version and WordPress requirements
**Settings not saving**: Verify file permissions and plugin conflicts  
**License not appearing**: Check theme calls `wp_head()` and integration settings
**robots.txt not updated**: Enable pretty permalinks and check .htaccess