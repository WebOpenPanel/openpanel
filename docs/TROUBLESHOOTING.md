# Troubleshooting

## Installer

### Installer hangs or fails
Check the log: `cat /var/log/openpanel-install.log | tail -50`

### MariaDB won't start
```bash
systemctl status mariadb
journalctl -u mariadb -n 20
```
Common: disk full, corrupted data dir. Try `rm -rf /var/lib/mysql/* && mysql_install_db`

### Nginx config test fails
```bash
nginx -t 2>&1
```
Check for duplicate server blocks or missing certificates.

### PHP-FPM won't start
```bash
php-fpm -t 2>&1
journalctl -u php-fpm -n 20
```
Common: pool config syntax error, missing socket directory.

## Panel

### 502 Bad Gateway
Panel PHP-FPM pool not running:
```bash
systemctl restart php-fpm
```

### 503 Service Unavailable
Panel application error:
```bash
tail -20 /usr/local/openpanel/storage/logs/laravel.log
```

### API returns 401
Token expired or invalid. Create new token via panel admin.

### API returns 502
PHP-FPM crashed. Check `php-fpm -t` and restart.

## Web Stacks

### WordPress returns 403
Apache/nginx can't read public_html. Check:
```bash
groups <username>        # nginx/apache should be in user's group
stat -c '%a' /home/<user>/public_html   # should be 750
```
Fix: `usermod -aG <username> nginx && usermod -aG <username> apache`

### Varnish returns 503
Apache not running or wrong port:
```bash
systemctl status httpd
curl -H "Host: domain.com" http://127.0.0.1:8080/
```

### Suspended site returns 200
Stale Varnish cache. Purge:
```bash
varnishadm "ban req.http Host == domain.com"
```

## DNS

### Named won't start
```bash
named-checkconf 2>&1
journalctl -u named -n 10
```
Common: syntax error in `/etc/named.conf`, missing zone file.

### Zone not resolving
```bash
dig @127.0.0.1 domain.com +short
named-checkzone domain.com /var/named/domain.com.db
```
Check zone is registered in `/etc/named.conf`.

## Isolation

### PHP open_basedir blocks legitimate access
Check pool config:
```bash
cat /etc/php-fpm.d/users/<username>.conf | grep open_basedir
```
Add required paths to `open_basedir` value.

### User can't write to public_html
```bash
stat -c '%a %U' /home/<user>/public_html
```
Should be `750 <user>`. Fix: `chown <user>:<user> /home/<user>/public_html`

## Services

### Check all services
```bash
for s in nginx php-fpm mariadb redis httpd varnish named postfix pure-ftpd; do
    echo "$s: $(systemctl is-active $s 2>/dev/null)"
done
```
