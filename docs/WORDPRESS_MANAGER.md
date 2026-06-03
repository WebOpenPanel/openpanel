# WordPress Manager

**Version:** 0.1.0-beta

## Overview

OpenPanel includes a WordPress management API for automated installation, configuration, and optimization of WordPress sites.

## Features

### Install WordPress
- Downloads and installs latest WordPress via WP-CLI
- Creates dedicated MySQL database and user
- Configures `wp-config.php` with database credentials
- Sets up object cache (Redis) integration
- Returns database credentials for user reference

### Redis Object Cache
- Enables Redis object cache drop-in (`object-cache.php`)
- Configures unique prefix per site
- Uses dedicated Redis database index per site
- Integrates with WP Redis plugin

### Varnish Integration
- WordPress sites served through Varnish cache (if nginx_varnish_apache stack)
- Purge support via Varnish purge endpoint
- Cache headers configured for optimal hit rate

## API Usage

### Install WordPress

```bash
curl -sk -X POST https://server:2087/api/v1/wordpress/install \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_account_id": 1,
    "domain": "example.com",
    "site_title": "My Site",
    "admin_user": "admin",
    "admin_password": "SecurePass123!",
    "admin_email": "admin@example.com"
  }'
```

### Enable Redis

```bash
curl -sk -X POST https://server:2087/api/v1/wordpress/enable-redis \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "site_id": 1,
    "domain": "example.com"
  }'
```

## Database Layout

Each WordPress installation gets:
- Database: `{username}_wp`
- Database user: `{username}_wp`
- Database password: auto-generated, stored encrypted in DB

## File Layout

```
/home/{username}/public_html/
├── wp-config.php
├── wp-content/
│   └── object-cache.php  (Redis drop-in, if enabled)
├── wp-admin/
└── wp-includes/
```

## Limitations

- No WordPress update management
- No plugin/theme management
- No staging site support
- No backup/restore per site
- WP-CLI must be available at `/usr/local/bin/wp`
