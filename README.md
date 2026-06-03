# OpenPanel

Open-source web hosting control panel for AlmaLinux/Rocky Linux 9.x.

**Version:** 0.1.0-beta

## Features

### Web Stack Manager
- Two stacks: `nginx_phpfpm` (default), `nginx_varnish_apache`
- Stack switching without data loss
- Per-user nginx/Apache vhosts with security headers
- Varnish HTTP accelerator with per-user VCL

### Hosting Accounts
- Create, suspend, unsuspend, terminate via panel or API
- Per-user PHP-FPM pools with isolation (open_basedir, disable_functions, per-user sessions)
- Shell access: `/sbin/nologin` by default, admin-controlled
- Per-user directory layout: public_html, private, backups, logs, tmp

### WordPress Manager
- WP-CLI based installation running as target user
- Redis object cache integration
- Varnish-compatible configuration
- Automatic .htaccess management

### DNS
- BIND named with per-account zone management
- Automatic zone registration and cleanup

### Email
- Postfix virtual domain setup per account
- Per-user mail directories

### FTP
- Pure-FTPd with chroot isolation
- Per-account FTP users

### API
- RESTful API on ports 2087 (admin) and 2083 (user)
- Token-based auth with SHA-256 hashing
- Scope-based authorization

### WHMCS Module
- Account create, suspend, unsuspend, terminate
- Dry-run verified (no live WHMCS tested yet)

### Open-Source Security Isolation
- Filesystem: 711 home, 750 public_html, 700 private dirs
- PHP-FPM: per-user pools, open_basedir, session/upload isolation
- Symlink protection: nginx `disable_symlinks`, Apache `SymLinksIfOwnerMatch`
- Process: /proc hidepid=2, ptrace_scope=1, core dumps disabled
- Resource controls: nproc/nofile limits, cgroups v2, XFS disk quotas
- Backup archive validation (blocks zip-slip, traversal, symlinks)
- Abuse monitor: 11 check categories via API
- No paid/commercial dependencies

## Requirements

| Resource | Minimum | Recommended |
|----------|---------|-------------|
| OS | AlmaLinux/Rocky 9.x | AlmaLinux/Rocky 9.x |
| RAM | 2 GB | 4 GB |
| Disk | 20 GB | 40 GB |
| Arch | x86_64 | x86_64 |
| Access | Root SSH | Root SSH |

## Quick Install

```bash
curl -O https://raw.githubusercontent.com/WebOpenPanel/openpanel/main/install-openpanel.sh
bash install-openpanel.sh
```

Options:
- `--stack=nginx_phpfpm` — Nginx + PHP-FPM only
- `--stack=nginx_varnish_apache` — Full stack with Varnish
- `--non-interactive` — No prompts
- `--help` — Show all options

After install, access:
- Admin panel: `https://your-server-ip:2087`
- User panel: `https://your-server-ip:2083`

Credentials saved to `/root/.openpanel-credentials`.

## Known Limitations (0.1.0-beta)

- WHMCS module is dry-run verified only (no live WHMCS tested)
- Roundcube webmail: not yet integrated
- phpMyAdmin: not yet integrated
- FTPS: not yet supported
- DNS cluster: single-server only
- Remote backups: local only
- UI polish: functional but minimal

## License

[MIT](https://opensource.org/licenses/MIT)
