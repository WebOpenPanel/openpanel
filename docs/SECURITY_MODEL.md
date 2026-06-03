# Security Model

**Version:** 0.1.0-beta

## Architecture

OpenPanel runs as a Laravel application served by nginx (port 2083/2087). The web process runs as the `nginx` user. System operations use `sudo` via configured sudoers rules.

## Isolation Layers

### 1. Linux User Isolation
- Each hosting account gets a dedicated Linux user
- Home directory: `/home/{username}/`
- Home permissions: `711` (execute-only traversal)
- `public_html/`: `750` (owner + group read)
- SSH keys, logs, backups, private: `700` (owner-only)

### 2. PHP-FPM Process Isolation
- Each user gets a dedicated PHP-FPM pool
- Pool runs as the account user (not nginx)
- Separate `php_value` per pool (upload limits, memory, execution time)
- Pool config: `/etc/php-fpm.d/users/{username}.conf`

### 3. nginx Vhost Isolation
- Per-user vhost in `/etc/nginx/conf.d/users/{username}.conf`
- Access logs per user
- Symlink protection via `disable_symlinks if_not_owner`
- Open_basedir enforcement

### 4. FTP Isolation
- Pure-FTPd virtual users per account
- Chroot to home directory
- TLS available (not yet configured by default)

### 5. Cron Isolation
- Per-user crontab
- Restricted to account user context

### 6. Resource Controls (cgroups v2)
- Per-user cgroup slice
- CPU and memory limits
- Nproc/nofile limits via `/etc/security/limits.d/`

### 7. Abuse Monitoring
- CPU, memory, disk, process count checks
- Mail queue monitoring
- Login failure detection
- FTP chroot verification
- Backup usage tracking

## API Security

- Bearer token authentication
- Tokens stored as SHA-256 hashes
- Scoped permissions (`admin:all`, `accounts:create`, etc.)
- `*` wildcard scope for full access
- HTTPS-only (self-signed in beta)

## What Is NOT Isolated (Known Limitations)

- **No CageFS** — users can see system binaries and libraries
- **No kernel-level isolation** — no namespaces, no seccomp
- **No per-user /tmp** — shared `/tmp`
- **No email rate limiting** — open relay risk if Postfix not configured
- **No ClamAV** — no antivirus scanning
- **No Fail2ban** — no brute-force protection
- **No Let's Encrypt** — self-signed certs only

## Recommendations for Production

1. Configure Fail2ban for SSH, FTP, and web brute-force
2. Configure Postfix relay to prevent spam
3. Enable pure-ftpd TLS
4. Add ClamAV for file scanning
5. Set up firewall rules (firewalld)
6. Monitor abuse alerts via API
