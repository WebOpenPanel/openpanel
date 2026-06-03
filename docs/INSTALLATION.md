# OpenPanel Installation Guide

## Supported OS

- AlmaLinux 9.x (recommended)
- Rocky Linux 9.x
- RHEL 9.x
- CentOS Stream 9

## Quick Install

```bash
curl -O https://raw.githubusercontent.com/WebOpenPanel/openpanel/main/install-openpanel.sh
bash install-openpanel.sh
```

### Options

```bash
bash install-openpanel.sh --help                    # Show all options
bash install-openpanel.sh --stack=nginx_phpfpm      # Nginx + PHP-FPM only
bash install-openpanel.sh --stack=nginx_varnish_apache  # Full stack with Varnish
bash install-openpanel.sh --non-interactive          # No prompts
bash install-openpanel.sh --hostname=panel.example.com
bash install-openpanel.sh --email=admin@example.com
bash install-openpanel.sh --skip-ssl                 # Skip SSL certificate generation
```

### Legacy Environment Variables

```bash
NON_INTERACTIVE=y OPENPANEL_WEB_STACK=nginx_phpfpm bash install-openpanel.sh
```

## What Gets Installed

- PHP 8.4 with PHP-FPM
- Nginx
- MariaDB 10.11
- Redis 7
- BIND (named) DNS server
- Pure-FTPd FTP server
- Postfix mail server
- OpenPanel Laravel application

### Optional (stack-dependent)

- Varnish 7 HTTP accelerator (`nginx_varnish_apache` stack)
- Apache httpd (`nginx_varnish_apache` stack)

## Ports

| Port | Service | Protocol |
|------|---------|----------|
| 2083 | User panel | HTTPS |
| 2087 | Admin panel | HTTPS |
| 2095 | Webmail | HTTPS |
| 80 | HTTP | TCP |
| 443 | HTTPS | TCP |
| 21 | FTP | TCP |
| 25 | SMTP | TCP |
| 53 | DNS | TCP/UDP |

## Post-Install

1. Access admin panel at `https://your-server-ip:2087`
2. Login with credentials from `/root/.openpanel-credentials`
3. Create hosting accounts via panel or API
4. Install WordPress via WordPress Manager

## Logs

- Installer log: `/var/log/openpanel-install.log`
- Panel log: `/usr/local/openpanel/storage/logs/laravel.log`
- Nginx: `/var/log/nginx/`
- PHP-FPM: `/var/log/php-fpm/`

## Uninstaller

Not yet available. Manual removal:

```bash
systemctl stop nginx php-fpm mariadb redis httpd varnish named
dnf remove -y openpanel 2>/dev/null
rm -rf /usr/local/openpanel
```
