# Changelog

## 0.1.0-beta (2026-06-04)

Initial private beta release for single-server hosting on AlmaLinux/Rocky Linux 9.x.

### Installer
- One-line install: `bash install-openpanel.sh`
- Stack selection: `--stack=nginx_phpfpm` or `--stack=nginx_varnish_apache`
- Non-interactive mode: `--non-interactive`
- Optional flags: `--hostname`, `--email`, `--skip-ssl`
- Automatic BIND DNS, Pure-FTPd, MariaDB, Redis setup
- Security hardening: /proc hidepid, ptrace_scope, cron.allow, service-safe UMASK 022
- Install summary prints stack, credentials path, and log path

### Web Stack Manager
- Two stacks: nginx_phpfpm (default), nginx_varnish_apache
- Per-user nginx vhosts with security headers
- Apache vhosts with SymLinksIfOwnerMatch
- Varnish caching with per-user VCL

### Hosting Accounts
- Create, suspend, unsuspend, terminate via API
- Per-user PHP-FPM pools with open_basedir, disable_functions
- Per-user tmp/session directories
- Directory layout: public_html, private, backups, logs, .openpanel
- Shell access: /sbin/nologin by default

### WordPress Manager
- WP-CLI based installation running as target user
- Redis object cache integration
- Varnish-compatible configuration
- Automatic .htaccess management (additive, never overwrites CMS rules)
- Local account backup/restore is VPS-validated
- WordPress staging create/delete/push lifecycle is beta-ready with pre-push backups
- Scheduled local backup command is VPS-verified; remote backup validation still needs a separate target

### API / WHMCS Module
- RESTful API on port 2087 (admin) and 2083 (user)
- Token-based auth with SHA-256 hashing
- Scope-based authorization
- WHMCS module: dry-run verified; no live WHMCS instance tested
- WHMCS module code includes CreateAccount, SuspendAccount, UnsuspendAccount, TerminateAccount, ChangePassword, ChangePackage
- API endpoints implemented for accounts, DNS, email, database, SSL, WordPress, abuse monitor, and resource limits

### Open-Source Isolation
- Filesystem: 711 home, 750 public_html, 700 private dirs
- PHP-FPM: per-user pools, open_basedir, session/upload isolation
- Symlink protection: nginx disable_symlinks, Apache SymLinksIfOwnerMatch
- Process: /proc hidepid=2, ptrace_scope=1, core dumps disabled
- Resource controls: nproc/nofile limits, cgroups v2, XFS disk quotas
- Backup: archive validation (blocks zip-slip, traversal, symlinks)
- FTP: Pure-FTPd chroot isolation
- Security headers: X-Content-Type-Options, X-Frame-Options, Referrer-Policy
- Sensitive file blocking: .env, .bak, .sql, .log, .conf, .ini

### Abuse Monitor
- 11 check categories: world-writable files, CPU, memory, suspicious PHP, symlinks, processes, disk, mail queue, login failures, FTP chroot, backup usage
- API endpoint: GET /api/v1/abuse-monitor
- Admin-only access

### DNS
- BIND named with per-account zone registration
- Automatic zone file creation and named.conf registration
- Zone cleanup on account termination
- named regression issue fixed and revalidated

### Email and Webmail
- Postfix/Dovecot virtual mailboxes are beta-ready
- SMTP submission on `587` requires authentication
- Roundcube webmail is beta-ready on `2095/2096`
- OpenDKIM signing and SPF/DKIM/DMARC helper records are beta-ready
- External inbox placement still depends on public DNS/rDNS/IP reputation

### Databases and phpMyAdmin
- phpMyAdmin is beta-ready at `/phpmyadmin/` on panel ports
- phpMyAdmin uses cookie auth with OpenPanel-created MySQL users
- Root phpMyAdmin login is blocked server-side
- Browser/session database visibility and non-root CRUD validation passed
- Root database credentials are not exposed in phpMyAdmin config

### FTP / FTPS
- Plain FTP remains beta-ready
- Explicit FTPS is beta-ready on port `21`
- Passive FTPS uses `30000-31000/tcp`
- FTPS upload/download/delete/chroot and anonymous rejection validation passed
- Plain FTP remains enabled for beta compatibility

### Test Results
- Fresh install validation: 173/173 PASS
- Beta smoke test: 25/25 PASS
- Cross-user isolation: 30/30 PASS
- Post-isolation regression: 24/24 PASS
- WordPress + Redis + Varnish: PASS
- API provisioning: PASS
- WHMCS module dry-run: PASS
- Plain FTP validation: PASS
- Local account backup/restore validation: PASS
- Email virtual mailbox validation: PASS
- Roundcube browser mail validation: PASS
- DKIM signing validation: PASS
- phpMyAdmin browser CRUD validation: PASS
- FTPS validation: PASS
- Let's Encrypt user-domain SSL with Force HTTPS: PASS
- WordPress staging lifecycle validation: PASS
- Scheduled local backup command and retention validation: PASS

### Known Beta Limits
- DNS cluster: not implemented
- Multi-server support: not implemented
- External inbox placement is not claimed; provider rDNS and IP reputation still matter
- WordPress staging is validated for single-server private beta; broader rollback UI and matrix coverage remain future work
- Remote/off-server backup upload and restore are not beta-ready without a separate tested target
