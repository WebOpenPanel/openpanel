# Beta Release Checklist (0.1.0-beta)

## Pre-Release

- [x] Fresh install validation: 143/143 PASS
- [x] Cross-user isolation: 30/30 PASS
- [x] Post-isolation regression: 24/24 PASS
- [x] WordPress + Redis + Varnish: PASS
- [x] API provisioning: PASS
- [x] WHMCS module dry-run: PASS
- [x] named/DNS issue fixed and documented
- [x] No live secrets in repo
- [x] VERSION file set to 0.1.0-beta
- [x] CHANGELOG.md created
- [x] ROADMAP.md created
- [x] README.md updated for beta
- [x] Installer has --help, --stack, --non-interactive options

## Known Limitations

- WHMCS module: dry-run only (no live WHMCS tested)
- Roundcube: not integrated
- phpMyAdmin: not integrated
- FTPS: not supported
- DNS cluster: single-server only
- Remote backups: local only

## Post-Release Goals (0.2.0)

- Live WHMCS testing
- Roundcube send/receive
- phpMyAdmin CRUD
- FTPS with Let's Encrypt
- DNS cluster
- Remote backup destinations
- Multi-server support
