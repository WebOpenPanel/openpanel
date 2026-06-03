# Changelog

## 0.1.0-beta (2026-06-04)

Initial beta release. All features tested on AlmaLinux 9.7 VPS.

### Installer
- One-line install: `bash install-openpanel.sh`
- Stack selection: `--stack=nginx_phpfpm` or `--stack=nginx_varnish_apache`
- Non-interactive mode: `--non-interactive`
- Automatic BIND DNS, Pure-FTPd, MariaDB, Redis setup
- Security hardening: /proc hidepid, ptrace_scope, cron.allow, UMASK 077

### Web Stack Manager
- Two stacks: nginx_phpfpm (default), nginx_varnish_apache
- Switch stacks without data loss
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

### API / WHMCS Module
- RESTful API on port 2087 (admin) and 2083 (user)
- Token-based auth with SHA-256 hashing
- Scope-based authorization
- WHMCS module: dry-run verified (account create, suspend, unsuspend, terminate)
- Endpoints: accounts, domains, DNS, email, FTP, databases, WordPress, abuse monitor, resource limits

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

### Test Results
- Fresh install validation: 143/143 PASS
- Cross-user isolation: 30/30 PASS
- Post-isolation regression: 24/24 PASS
- WordPress + Redis + Varnish: PASS
- API provisioning: PASS
- WHMCS module dry-run: PASS
