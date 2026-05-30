# OpenPanel

OpenPanel is a modern, web-based server control panel built with Laravel. It provides a clean, intuitive interface for managing web hosting servers running AlmaLinux, Rocky Linux, RHEL, CentOS, or Oracle Linux (EL8/EL9).

## Features

### Server Management
- **System Monitoring** — Real-time CPU, memory, disk, and network usage
- **Process Manager** — View and manage running processes
- **Service Control** — Start, stop, restart system services (Nginx, MySQL, PHP-FPM, etc.)
- **Firewall (CSF)** — Full CSF/LFD firewall management with GUI
- **SSH Terminal** — Browser-based SSH access
- **Cron Jobs** — Visual cron job editor with logging
- **YUM/DNF Package Manager** — Install and manage system packages
- **Hostname & Network** — Configure hostname, DNS resolvers, network interfaces
- **Server Time** — NTP timezone and sync management
- **Root Password** — Change root password from the panel

### Web Server
- **Nginx & Apache** — Manage virtual hosts, rebuild configs
- **Varnish Cache** — Full Varnish HTTP accelerator management (install, configure, VCL editor, cache purge)
- **PHP Manager** — Multiple PHP versions, per-site PHP settings, php.ini editor
- **SSL/TLS** — Let's Encrypt, self-signed, custom certificates with auto-renewal
- **Webserver Rebuild** — Regenerate web server configuration files

### User Accounts
- **Account Management** — Create, edit, suspend, delete user accounts
- **Hosting Packages** — Define resource limits (disk, bandwidth, databases, emails, etc.)
- **Reseller Support** — Reseller-level account management

### Email
- **Email Accounts** — Create and manage mailboxes
- **Forwarders & Autoresponders** — Email routing and automated replies
- **DKIM & SPF** — Email authentication configuration
- **Postfix & Dovecot** — Full mail server configuration
- **Mail Queue** — View and manage the mail queue
- **Email Statistics** — Daily and weekly email traffic reports
- **Anti-Spam** — SpamAssassin, RBL, and custom spam filtering
- **SpamExperts** — Integration with SpamExperts filtering

### DNS
- **DNS Zone Editor** — Full DNS record management (A, AAAA, CNAME, MX, TXT, SRV, etc.)
- **DNS Templates** — Reusable DNS zone templates
- **Nameserver Configuration** — Custom nameserver setup
- **DNS Cluster** — Multi-server DNS synchronization

### Databases
- **MySQL/MariaDB** — Database and user management, process viewer, status monitor
- **phpMyAdmin** — Auto-login integration
- **PostgreSQL** — Basic PostgreSQL management
- **MongoDB** — MongoDB database management

### Backups
- **Backup Manager** — Schedule and manage server backups
- **Backup Configuration** — Per-account and full-server backup settings
- **Backup Monitor** — Real-time backup job monitoring
- **Remote Backups** — SSH/SFTP remote backup support

### Applications
- **Node.js Manager** — Install, configure, and manage Node.js applications with NVM support
- **File Manager** — Browser-based file management with editor
- **Tomcat** — Apache Tomcat application server management
- **Shoutcast** — Streaming server management
- **TeamSpeak** — TeamSpeak server management

### Security
- **CSF Firewall** — Full ConfigServer Security & Firewall integration
- **IP Management** — Allow/block IP addresses, NAT configuration
- **ModSecurity** — Web application firewall configuration
- **cgroups** — Resource limiting per account
- **Shell Access Control** — Manage SSH access per user
- **Login Security** — Brute-force protection and rate limiting
- **Malware Detection** — Maldet and RKHunter integration
- **Lynis Security Auditing** — System security score
- **Symlink Protection** — Prevent symlink attacks

### Advanced
- **Server Migration** — Transfer accounts from remote servers
- **WHMCS Integration** — Billing platform bridge API
- **API Access** — RESTful API for external integrations
- **CloudLinux** — CloudLinux integration and management
- **Cluster Management** — Multi-server cluster configuration
- **Auto Updates** — Automatic system and panel updates
- **Custom Hooks** — Pre/post action hook system
- **Themes & Languages** — Customizable UI themes and i18n support
- **Notifications** — System notification management
- **Email Stats & Monitoring** — Servercast and messenger integrations

### User Panel (Port 2083)
- **User Dashboard** — Account overview, disk usage, resource stats
- **Domain Management** — Add/remove subdomains, domain aliases
- **Email Accounts** — Create mailboxes, forwarders, autoresponders
- **MySQL Manager** — Create databases, users, assign privileges, phpMyAdmin
- **File Manager** — Browse, edit, upload files with permission management
- **FTP Accounts** — Create and manage FTP access per directory
- **Cron Jobs** — User-level cron job management
- **SSL Certificates** — Let's Encrypt and self-signed certificate generation
- **DNS Zone Editor** — Manage DNS records for owned domains
- **Statistics** — Disk usage breakdown, process count, login history
- **Backups** — Create, download, restore, delete account backups

## Requirements

### Minimum (web hosting only, no email)
| Resource | Value | Breakdown |
|----------|-------|-----------|
| RAM | 1 GB | OS ~200 MB, MariaDB ~256 MB, Nginx ~30 MB, PHP-FPM ~40 MB, CSF ~50 MB, BIND ~30 MB, Panel ~30 MB |
| Disk | 15 GB | OS ~4 GB, MariaDB data ~2 GB, panel + vendor ~500 MB, swap ~1 GB, logs + user data ~7 GB |

### Recommended (web + email hosting)
| Resource | Value | Breakdown |
|----------|-------|-----------|
| RAM | 4 GB | Above + Postfix ~50 MB, Dovecot ~50 MB, SpamAssassin ~150 MB, ClamAV ~400 MB, Varnish ~256 MB, headroom |
| Disk | 40 GB | Above + ClamAV signatures ~300 MB, mail storage, backups, additional headroom |

### Full Stack (all services enabled)
| Resource | Value | Breakdown |
|----------|-------|-----------|
| RAM | 8 GB | All services + multiple PHP-FPM pools, Node.js apps, Tomcat, Icecast, safety margin |
| Disk | 80 GB | All services + large mail queues, backup storage, application data |

### Service Memory Footprint Reference
| Service | Typical RAM | Notes |
|---------|-------------|-------|
| OS + systemd | 200 MB | Base EL8/EL9 install |
| MariaDB | 256 MB – 1 GB | Default `innodb_buffer_pool_size=128M`, scales with data |
| Nginx | 20 – 50 MB | Scales with connections |
| Apache (if used) | 50 – 200 MB | Only in Nginx+Apache stack |
| PHP-FPM | 30 – 50 MB per pool | One pool per PHP version in use |
| CSF / LFD | 40 – 100 MB | Firewall + login failure daemon |
| Postfix | 30 – 50 MB | SMTP server |
| Dovecot | 30 – 50 MB | IMAP/POP3 server |
| SpamAssassin | 100 – 200 MB | Spam filtering |
| ClamAV | 300 – 500 MB | Virus signatures alone ~200 MB |
| Varnish | 256 MB | Default malloc cache size |
| BIND / Named | 30 – 80 MB | DNS server |
| Pure-FTPd | 10 – 20 MB | FTP server |
| OpenPanel | 30 – 60 MB | Laravel PHP-FPM pool |

### Other
- **OS:** AlmaLinux 8/9, Rocky Linux 8/9, RHEL 8/9, CentOS 8/9, or Oracle Linux 8/9
- **Architecture:** x86_64
- **Access:** Root SSH access

## Quick Install

### EL9 (AlmaLinux 9, Rocky 9, RHEL 9, Oracle Linux 9)

```bash
curl -O https://raw.githubusercontent.com/WebOpenPanel/openpanel/main/openpanel-el9
bash openpanel-el9
```

### EL8 (AlmaLinux 8, Rocky 8, CentOS 8, Oracle Linux 8)

```bash
curl -O https://raw.githubusercontent.com/WebOpenPanel/openpanel/main/openpanel-el8
bash openpanel-el8
```

### Universal Installer (auto-detects OS version)

```bash
curl -O https://raw.githubusercontent.com/WebOpenPanel/openpanel/main/install-openpanel.sh
bash install-openpanel.sh
```

The installer will:
1. Install PHP 8.3, Nginx, MariaDB, and all dependencies
2. Clone OpenPanel from GitHub
3. Configure the database and run migrations
4. Generate an SSL certificate
5. Configure Nginx on ports **2087** (admin) and **2083** (user) with PHP-FPM
6. Create an admin user and display credentials

After installation, access the admin panel at: `https://your-server-ip:2087` and the user panel at: `https://your-server-ip:2083`

Credentials are saved to `/root/.openpanel-credentials`.

## Manual Install

```bash
# Clone the repository
git clone https://github.com/WebOpenPanel/openpanel.git /usr/local/openpanel
cd /usr/local/openpanel

# Install dependencies
composer install --no-dev --optimize-autoloader

# Configure environment
cp .env.example .env
php artisan key:generate

# Edit .env with your database credentials
# DB_DATABASE=open_panel
# DB_USERNAME=openpanel
# DB_PASSWORD=your_password

# Run migrations
php artisan migrate

# Create admin user
php artisan tinker
>>> \App\Models\User::create(['username' => 'admin', 'email' => 'admin@yourdomain.com', 'password' => bcrypt('your-password'), 'role' => 'admin', 'status' => 'active']);

# Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link

# Set permissions
chown -R nginx:nginx storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

## Tech Stack

- **Backend:** Laravel 13, PHP 8.3
- **Frontend:** Tailwind CSS, Alpine.js, Vite
- **Database:** MariaDB / MySQL
- **Web Server:** Nginx with PHP-FPM
- **OS Support:** EL8 and EL9 (AlmaLinux, Rocky, RHEL, CentOS, Oracle)

## Project Structure

```
app/
├── Http/
│   ├── Controllers/    # 35+ controllers for all panel features
│   └── Middleware/     # Auth, activity logging, admin checks
├── Models/             # Eloquent models for all entities
├── Providers/          # Service providers
└── Services/           # 49 service classes (business logic)

resources/views/        # Blade templates for all pages
routes/web.php          # All panel routes
database/
├── migrations/         # Database schema
└── seeders/            # Default data seeder

openpanel-el9           # EL9 one-line installer
openpanel-el8           # EL8 one-line installer
install-openpanel.sh    # Universal installer
```

## Contributing

Contributions are welcome. Please open an issue first to discuss what you would like to change.

## License

OpenPanel is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
