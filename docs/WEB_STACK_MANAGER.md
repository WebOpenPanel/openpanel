# Web Stack Manager

**Version:** 0.1.0-beta

## Supported Stacks

### nginx_phpfpm
- nginx serves HTTP/HTTPS directly
- PHP-FPM handles PHP processing
- Simplest stack, best for most use cases

**Service flow:**
```
Client → nginx:80/443 → PHP-FPM (unix socket)
```

### nginx_varnish_apache
- nginx handles SSL termination and routing
- Varnish caches HTTP responses
- Apache handles `.htaccess` and PHP processing via mod_php/FPM
- Best for WordPress with caching

**Service flow:**
```
Client → nginx:80 → Varnish:6081 → Apache:8080 → PHP-FPM
Client → nginx:443 → (SSL termination) → Varnish:6081 → ...
```

## Stack Management

### Get Active Stack

```bash
curl -sk https://server:2087/api/v1/web-stack \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Stack Selection

Set during install:
```bash
sudo bash install-openpanel.sh --stack=nginx_varnish_apache
```

Or via environment variable:
```bash
OPENPANEL_WEB_STACK=nginx_varnish_apache bash install-openpanel.sh
```

## Per-User Configuration

Each hosting account gets:
- nginx vhost: `/etc/nginx/conf.d/users/{username}.conf`
- PHP-FPM pool: `/etc/php-fpm.d/users/{username}.conf`
- Apache vhost: `/etc/httpd/conf.d/users/{username}.conf` (if apache in stack)
- Varnish config: `/etc/varnish/conf.d/users/{username}.conf` (if varnish in stack)

## Vhost Features

- Per-user access logs
- Open_basedir restriction
- Symlink protection (`disable_symlinks if_not_owner`)
- Custom error pages
- SSL termination at nginx level

## Limitations

- No runtime stack switching (requires reinstall)
- No per-domain stack selection
- No custom nginx/Apache module management
- No HTTP/2 tuning per user
