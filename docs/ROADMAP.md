# OpenPanel Roadmap

## 0.1.0-beta (current)
- Core hosting control panel
- Two web stacks (nginx_phpfpm, nginx_varnish_apache)
- WordPress Manager with Redis/Varnish
- API + WHMCS module (dry-run verified)
- Open-source shared hosting isolation
- Abuse monitor

## 0.2.0 (planned)
- Live WHMCS integration testing
- Roundcube webmail: browser send/receive verification
- phpMyAdmin: browser CRUD verification
- FTPS support (TLS certificates via Let's Encrypt)
- DNS cluster support (multi-server zone transfers)
- Remote backup destinations (S3, FTP, rsync)
- Multi-server management
- Improved admin dashboard UI
- Public documentation site

## 0.3.0 (future)
- Reseller accounts
- Package-based resource enforcement in UI
- Fail2ban integration for brute-force protection
- ClamAV/Maldet malware scanning
- Automated backup scheduling with retention policies
- SSL certificate management (Let's Encrypt per-domain)
- Email deliverability (SPF, DKIM, DMARC auto-configuration)
- Staging sites for WordPress
- Server monitoring dashboard
