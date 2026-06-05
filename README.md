# OpenPanel

Open-source hosting control panel for shared hosting on AlmaLinux 9.x and Rocky Linux 9.x.

**Version:** `0.1.0-beta`
**Release status:** `beta-ready`
**Use case:** private beta testing only

## What OpenPanel Is

OpenPanel packages a Linux web hosting stack, per-account isolation, API provisioning, and WordPress tooling into one open-source panel. The beta is focused on single-server shared hosting with no paid dependencies.

## Verified Beta Scope

- Installer: `fresh-install verified`
- Web stacks: `nginx_phpfpm`, `nginx_varnish_apache`
- Account lifecycle: create, suspend, unsuspend, terminate
- WordPress: install, Redis object cache, Varnish support, staging create/delete/push
- API provisioning: `beta-ready`
- WHMCS server module: `dry-run verified`
- Shared-hosting isolation: `beta-ready`
- Abuse monitor and resource limits: `beta-ready`
- DNS / `named`: `beta-ready`
- Email, Roundcube webmail, phpMyAdmin, plain FTP/FTPS, local backup/restore, scheduled local backup command, WordPress staging, and Let's Encrypt user-domain SSL: `beta-ready` / `VPS-verified`

## Verification Snapshot

- Fresh install validation: `173/173 PASS`
- Beta smoke test: `25/25 PASS`
- Open-source isolation: `30/30 PASS`
- Regression: `24/24 PASS`

## Supported OS

- AlmaLinux 9.x
- Rocky Linux 9.x

Other Enterprise Linux variants may partially work, but they are not part of the `0.1.0-beta` support promise.

## Supported Stacks

- `nginx_phpfpm` - nginx + PHP-FPM
- `nginx_varnish_apache` - nginx + Varnish + Apache + PHP-FPM

## Quick Install

```bash
curl -O https://raw.githubusercontent.com/WebOpenPanel/openpanel/main/install-openpanel.sh
sudo bash install-openpanel.sh
```

Common flags:

- `--help`
- `--stack=nginx_phpfpm`
- `--stack=nginx_varnish_apache`
- `--non-interactive`
- `--hostname=panel.example.com`
- `--email=admin@example.com`
- `--skip-ssl`

Legacy non-interactive style still works:

```bash
NON_INTERACTIVE=y OPENPANEL_WEB_STACK=nginx_phpfpm sudo bash install-openpanel.sh
```

After install:

- Admin panel: `https://192.0.2.10:2087`
- User panel: `https://192.0.2.10:2083`
- Credentials file: `/root/.openpanel-credentials`
- Installer log: `/tmp/openpanel-install.log`

## Key Features

- Per-account Linux users, home layout, PHP-FPM pools, and vhosts
- Account provisioning by panel API and WHMCS server module
- WordPress install via WP-CLI with Redis, per-domain Varnish shield/cache modes, and staging lifecycle
- BIND DNS zone registration and cleanup
- Postfix/Dovecot virtual mailboxes with authenticated SMTP submission
- Pure-FTPd account isolation with plain FTP and explicit FTPS
- Roundcube webmail on ports `2095/2096`
- OpenDKIM signing and SPF/DKIM/DMARC helper records
- phpMyAdmin on the panel path `/phpmyadmin/` with cookie auth and root-login blocking
- Let's Encrypt user-domain SSL via API with per-domain Force HTTPS
- Local backup/restore and scheduled local backup execution
- Abuse monitor with 11 check categories
- Resource controls with limits and cgroups helpers
- No paid or commercial dependencies required

## Beta Limitations

- WHMCS module is `dry-run verified`; no live WHMCS instance tested
- Roundcube is `beta-ready`; advanced webmail plugins are not part of the beta claim
- phpMyAdmin: `beta-ready` for OpenPanel-created MySQL users; root login is intentionally blocked
- Plain FTP and explicit FTPS are `beta-ready`; implicit FTPS on `990` is not implemented
- Let's Encrypt user-domain SSL is `beta-ready` via API; wildcard/DNS-01 certificates are not implemented
- Email is `beta-ready` for panel-managed virtual mailboxes, Roundcube, and DKIM signing; external inbox placement depends on DNS/rDNS/IP reputation
- DNS cluster: `not implemented`
- Multi-server support: `not implemented`
- Public documentation site: `not implemented`

## Public Repo Notes

- Documentation reflects current code and retained public test evidence
- Live secrets are not intended to ship in the public repository
- Legacy filenames may remain for compatibility, but content describes OpenPanel only

## License

[MIT](https://opensource.org/licenses/MIT)
