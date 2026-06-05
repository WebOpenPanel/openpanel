#!/bin/bash
set -euo pipefail
umask 022

LANG=en_US.UTF-8
export LANG

GITHUB_REPO="https://github.com/WebOpenPanel/openpanel.git"
INSTALL_DIR="/usr/local/openpanel"
PHP_VERSION="8.4"
DB_NAME="open_panel"
DB_USER="openpanel"
LOG_FILE="/tmp/openpanel-install.log"
REQUIRED_RAM_MB=512

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log()  { echo -e "${GREEN}[OpenPanel]${NC} $1" | tee -a "$LOG_FILE"; }
warn() { echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"; }
err()  { echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"; exit 1; }
step() { echo -e "\n${BLUE}========================================${NC}" | tee -a "$LOG_FILE"; echo -e "${BLUE}  $1${NC}" | tee -a "$LOG_FILE"; echo -e "${BLUE}========================================${NC}\n" | tee -a "$LOG_FILE"; }

show_help() {
    echo "OpenPanel Installer (0.1.0-beta)"
    echo ""
    echo "Usage: sudo bash install-openpanel.sh [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --help                       Show this help message"
    echo "  --stack=STACK                Web stack: nginx_phpfpm (default) or nginx_varnish_apache"
    echo "  --non-interactive            No prompts (use defaults or env vars)"
    echo "  --hostname=HOSTNAME          Server hostname"
    echo "  --email=EMAIL                Admin email address"
    echo "  --skip-ssl                   Skip SSL certificate generation"
    echo ""
    echo "Environment Variables:"
    echo "  NON_INTERACTIVE=y            Same as --non-interactive"
    echo "  OPENPANEL_WEB_STACK=STACK    Same as --stack=STACK"
    echo "  OPENPANEL_HOSTNAME=HOSTNAME  Same as --hostname=HOSTNAME"
    echo "  OPENPANEL_EMAIL=EMAIL        Same as --email=EMAIL"
    echo ""
    echo "Examples:"
    echo "  sudo bash install-openpanel.sh"
    echo "  sudo bash install-openpanel.sh --stack=nginx_varnish_apache --non-interactive"
    echo "  NON_INTERACTIVE=y OPENPANEL_WEB_STACK=nginx_phpfpm sudo bash install-openpanel.sh"
    echo ""
    exit 0
}

parse_args() {
    for arg in "$@"; do
        case "$arg" in
            --help|-h)
                show_help
                ;;
            --stack=*)
                OPENPANEL_WEB_STACK="${arg#*=}"
                ;;
            --non-interactive)
                NON_INTERACTIVE="y"
                ;;
            --hostname=*)
                PANEL_HOSTNAME="${arg#*=}"
                ;;
            --email=*)
                PANEL_EMAIL="${arg#*=}"
                ;;
            --skip-ssl)
                SKIP_SSL="y"
                ;;
            *)
                warn "Unknown option: $arg"
                ;;
        esac
    done
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        err "This script must be run as root. Usage: sudo bash $0"
    fi
}

detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS_ID="${ID}"
        OS_VERSION="${VERSION_ID}"
        OS_MAJOR="${VERSION_ID%%.*}"
    else
        err "Cannot detect OS. /etc/os-release not found."
    fi

    case "$OS_MAJOR" in
        8)  OS_TYPE="el8" ;;
        9)  OS_TYPE="el9" ;;
        *)  err "Unsupported OS: $ID $VERSION_ID. OpenPanel requires EL8 or EL9 (RHEL/CentOS/AlmaLinux/Rocky/Oracle)." ;;
    esac

    arch=$(uname -m)
    if [[ "$arch" != "x86_64" ]]; then
        err "Unsupported architecture: $arch. OpenPanel requires x86_64."
    fi

    log "Detected: $PRETTY_NAME ($OS_TYPE) on $arch"
}

check_existing() {
    if [ -d "$INSTALL_DIR" ] && [ -f "$INSTALL_DIR/artisan" ]; then
        warn "OpenPanel is already installed at $INSTALL_DIR"
        if [[ "${NON_INTERACTIVE:-}" == "y" ]]; then
            log "Non-interactive mode: proceeding with reinstall/upgrade..."
        else
            read -p "Reinstall/upgrade? (y/N): " confirm
            if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
                exit 0
            fi
        fi
        log "Proceeding with reinstall/upgrade..."
    fi

    if [ -e "/usr/local/cwpsrv/" ]; then
        warn "Legacy panel detected at /usr/local/cwpsrv/. OpenPanel will be installed alongside."
        if [[ "${NON_INTERACTIVE:-}" != "y" ]]; then
            read -p "Continue? (y/N): " confirm
            if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
                exit 0
            fi
        fi
    fi
}

check_resources() {
    local ram_mb
    ram_mb=$(free -m | awk '/^Mem:/{print $2}')
    if [ "$ram_mb" -lt "$REQUIRED_RAM_MB" ]; then
        err "Insufficient RAM: ${ram_mb}MB detected, ${REQUIRED_RAM_MB}MB required."
    fi
    log "RAM: ${ram_mb}MB OK"

    local disk_mb
    disk_mb=$(df -m /usr | awk 'NR==2{print $4}')
    if [ "$disk_mb" -lt 2048 ]; then
        warn "Low disk space: ${disk_mb}MB free on /usr. Recommend at least 2GB."
    fi
}

gather_config() {
    echo ""
    echo -e "${BLUE}=== OpenPanel Installation Configuration ===${NC}"
    echo ""

    PANEL_HOSTNAME="${PANEL_HOSTNAME:-${OPENPANEL_HOSTNAME:-}}"
    PANEL_EMAIL="${PANEL_EMAIL:-${OPENPANEL_EMAIL:-}}"

    if [[ "${NON_INTERACTIVE:-}" == "y" ]]; then
        # On re-run, preserve existing passwords from .env
        if [ -f "$INSTALL_DIR/.env" ]; then
            local existing_db_pass
            existing_db_pass=$({ grep '^DB_PASSWORD=' "$INSTALL_DIR/.env" || true; } | cut -d= -f2 | tr -d '"' | tr -d "'")
            local existing_root_pass
            existing_root_pass=$({ grep '^MYSQL_ROOT_PASSWORD=' "$INSTALL_DIR/.env" || true; } | cut -d= -f2 | tr -d '"' | tr -d "'")
            if [ -n "$existing_db_pass" ]; then
                DB_PASSWORD="$existing_db_pass"
                log "Preserved existing DB_PASSWORD from .env"
            else
                DB_PASSWORD=$(openssl rand -base64 16 | tr -dc A-Za-z0-9 | head -c 20)
            fi
            if [ -n "$existing_root_pass" ]; then
                MYSQL_ROOT_PASSWORD="$existing_root_pass"
                log "Preserved existing MYSQL_ROOT_PASSWORD from .env"
            elif [ -f /root/.my.cnf ]; then
                MYSQL_ROOT_PASSWORD="$(grep '^password=' /root/.my.cnf | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")"
                log "Preserved existing MYSQL_ROOT_PASSWORD from /root/.my.cnf"
            else
                MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-$(openssl rand -base64 16 | tr -dc A-Za-z0-9 | head -c 20)}"
            fi
        else
            MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-$(openssl rand -base64 16 | tr -dc A-Za-z0-9 | head -c 20)}"
            DB_PASSWORD=$(openssl rand -base64 16 | tr -dc A-Za-z0-9 | head -c 20)
        fi
        ROOT_PASSWORD="${ROOT_PASSWORD:-}"
        SERVER_IP="${SERVER_IP:-$(curl -4 -s --connect-timeout 5 ifconfig.me 2>/dev/null || ip -4 addr show scope global | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -1)}"
        ENABLE_SSL="${ENABLE_SSL:-Y}"
        INSTALL_MAIL="${INSTALL_MAIL:-Y}"
        INSTALL_WEBMAIL="${INSTALL_WEBMAIL:-Y}"
        DO_RESTART="${DO_RESTART:-N}"
        log "Non-interactive mode: using environment variables / defaults"
    else
        read -s -p "MySQL root password (leave blank to auto-generate): " MYSQL_ROOT_PASSWORD
        echo ""
        if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
            MYSQL_ROOT_PASSWORD=$(openssl rand -base64 16 | tr -dc A-Za-z0-9 | head -c 20)
            log "Generated MySQL root password (saved in credentials file)"
        fi

        DB_PASSWORD=$(openssl rand -base64 16 | tr -dc A-Za-z0-9 | head -c 20)

        read -s -p "Set root password for admin panel (leave blank to keep current): " ROOT_PASSWORD
        echo ""

        read -p "Server IP (auto-detected): " SERVER_IP
        if [ -z "$SERVER_IP" ]; then
            SERVER_IP=$(curl -4 -s --connect-timeout 5 ifconfig.me 2>/dev/null || ip -4 addr show scope global | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -1)
        fi

        read -p "Enable SSL for panel? (Y/n): " ENABLE_SSL
        ENABLE_SSL="${ENABLE_SSL:-Y}"

        read -p "Install mail server (Postfix/Dovecot)? (Y/n): " INSTALL_MAIL
        INSTALL_MAIL="${INSTALL_MAIL:-Y}"

        read -p "Install Roundcube webmail? (Y/n): " INSTALL_WEBMAIL
        INSTALL_WEBMAIL="${INSTALL_WEBMAIL:-Y}"

        read -p "Restart server after install? (y/N): " DO_RESTART
        DO_RESTART="${DO_RESTART:-N}"
    fi

    # Apply --hostname if provided
    if [ -n "${PANEL_HOSTNAME:-}" ]; then
        hostnamectl set-hostname "$PANEL_HOSTNAME" 2>/dev/null || hostname "$PANEL_HOSTNAME"
        log "Hostname set to $PANEL_HOSTNAME"
    fi

    if [ -n "${ROOT_PASSWORD:-}" ]; then
        echo "root:${ROOT_PASSWORD}" | chpasswd 2>&1
        log "Root password updated"
    fi

    log "Server IP: $SERVER_IP"
    log "SSL: $ENABLE_SSL, Mail: $INSTALL_MAIL"
}

install_repos() {
    step "Updating System Packages"
    dnf -y update 2>&1 | tee -a "$LOG_FILE"

    step "Configuring Repositories"

    dnf -y install epel-release 2>&1 | tee -a "$LOG_FILE"

    if [ "$OS_TYPE" = "el9" ]; then
        dnf config-manager --set-enabled crb 2>&1 | tee -a "$LOG_FILE"
    elif [ "$OS_TYPE" = "el8" ]; then
        dnf config-manager --set-enabled powertools 2>&1 | tee -a "$LOG_FILE" || true
        if [ -f /etc/yum.repos.d/almalinux-powertools.repo ]; then
            sed -i 's/enabled=0/enabled=1/' /etc/yum.repos.d/almalinux-powertools.repo
        fi
        if [ -f /etc/yum.repos.d/Rocky-PowerTools.repo ]; then
            sed -i 's/enabled=0/enabled=1/' /etc/yum.repos.d/Rocky-PowerTools.repo
        fi
    fi

    dnf -y makecache 2>&1 | tee -a "$LOG_FILE"
    log "Repositories configured"
}

install_php() {
    step "Installing PHP $PHP_VERSION"

    dnf -y install https://rpms.remirepo.net/enterprise/remi-release-${OS_MAJOR}.rpm 2>&1 | tee -a "$LOG_FILE" || true
    dnf -y module reset php 2>&1 | tee -a "$LOG_FILE"
    dnf -y module enable php:remi-${PHP_VERSION} 2>&1 | tee -a "$LOG_FILE"

    dnf -y install php php-cli php-common php-fpm php-mysqlnd php-pdo php-mbstring php-xml \
        php-bcmath php-json php-tokenizer php-zip php-curl php-gd php-intl php-opcache \
        php-soap php-redis php-imagick php-ldap php-sodium php-fileinfo php-ctype php-dom \
        php-phar php-readline php-posix php-shmop php-sysvmsg php-sysvsem php-sysvshm \
        2>&1 | tee -a "$LOG_FILE"

    if ! command -v php &>/dev/null; then
        err "PHP installation failed"
    fi

    local installed_php
    installed_php=$(php -r 'echo PHP_VERSION;')
    log "PHP $installed_php installed"

    cat > /etc/php.d/99-openpanel.ini <<'EOPHP'
date.timezone = UTC
short_open_tag = On
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
post_max_size = 512M
upload_max_filesize = 512M
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
session.gc_maxlifetime = 7200
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
EOPHP

    log "PHP configured"
}

install_nginx() {
    step "Installing Nginx"

    dnf -y install nginx 2>&1 | tee -a "$LOG_FILE"

    if ! command -v nginx &>/dev/null; then
        err "Nginx installation failed"
    fi

    log "Nginx installed"
}

install_mariadb() {
    step "Installing MariaDB"

    dnf -y install mariadb-server mariadb 2>&1 | tee -a "$LOG_FILE"

    systemctl enable mariadb
    systemctl start mariadb

    if ! systemctl is-active --quiet mariadb; then
        err "MariaDB failed to start"
    fi

    if mysqladmin --no-defaults -u root status &>/dev/null 2>&1; then
        # Root has no password yet (fresh install) — switch from unix_socket to password auth
        mysql --no-defaults -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password;" 2>&1 | tee -a "$LOG_FILE"
        mysqladmin --no-defaults -u root password "$MYSQL_ROOT_PASSWORD" 2>&1 | tee -a "$LOG_FILE"
        log "MySQL root password set"
    elif mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT 1;" &>/dev/null 2>&1; then
        log "MySQL root password already set and verified"
    else
        # Password mismatch on re-run — try to recover using .my.cnf or socket
        warn "MySQL root password mismatch, attempting recovery..."
        if [ -f /root/.my.cnf ]; then
            local cnf_pass
            cnf_pass=$(grep '^password=' /root/.my.cnf | cut -d= -f2-)
            if [ -n "$cnf_pass" ] && mysql -u root -p"$cnf_pass" -e "SELECT 1;" &>/dev/null 2>&1; then
                mysql -u root -p"$cnf_pass" -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '$MYSQL_ROOT_PASSWORD';" 2>&1 | tee -a "$LOG_FILE"
                log "Recovered MySQL root password from .my.cnf"
            fi
        fi
        # Final verification
        if ! mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT 1;" &>/dev/null 2>&1; then
            warn "Cannot verify MySQL root password — DB operations may fail"
        fi
    fi

    # Ensure root uses password auth (not unix_socket) so Laravel can connect
    mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('$MYSQL_ROOT_PASSWORD');" 2>&1 | tee -a "$LOG_FILE" || true

    mysql -u root -p"$MYSQL_ROOT_PASSWORD" <<EOSQL
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
EOSQL

    cat > /root/.my.cnf <<EOF
[client]
password=$MYSQL_ROOT_PASSWORD
user=root
EOF
    chmod 600 /root/.my.cnf

    mysql -u root -p"$MYSQL_ROOT_PASSWORD" <<EOSQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON *.* TO '${DB_USER}'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOSQL

    log "MariaDB configured, database '$DB_NAME' created"
}

install_composer() {
    step "Installing Composer"

    if ! command -v composer &>/dev/null; then
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer 2>&1 | tee -a "$LOG_FILE"
    fi

    if ! command -v composer &>/dev/null; then
        err "Composer installation failed"
    fi

    log "Composer $(composer --version) installed"
}

install_nodejs() {
    step "Installing Node.js"

    if ! command -v node &>/dev/null; then
        curl -fsSL https://rpm.nodesource.com/setup_20.x | bash - 2>&1 | tee -a "$LOG_FILE"
        dnf -y install nodejs 2>&1 | tee -a "$LOG_FILE"
    fi

    if command -v node &>/dev/null; then
        log "Node.js $(node --version) installed"
    else
        warn "Node.js installation failed, frontend assets will use CDN fallback"
    fi
}

install_base_packages() {
    step "Installing Base Packages"
    dnf -y install git curl wget unzip tar gcc 2>&1 | tee -a "$LOG_FILE"
    log "Base packages installed"
}

clone_project() {
    step "Installing OpenPanel"

    if [ -d "$INSTALL_DIR" ]; then
        log "Existing installation found, preserving runtime configuration"
    fi

    if [ -d "$INSTALL_DIR/.git" ]; then
        cd "$INSTALL_DIR"
        git stash 2>&1 | tee -a "$LOG_FILE" || true
        git pull origin main 2>&1 | tee -a "$LOG_FILE"
        git stash pop 2>&1 | tee -a "$LOG_FILE" || true
    else
        mkdir -p "$INSTALL_DIR"
        git clone "$GITHUB_REPO" "$INSTALL_DIR" 2>&1 | tee -a "$LOG_FILE"
        cd "$INSTALL_DIR"
    fi

    composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tee -a "$LOG_FILE"

    log "Project files installed"
}

configure_env() {
    step "Configuring Environment"

    local existing_app_key=""
    local existing_mail_from=""
    if [ -f "$INSTALL_DIR/.env" ]; then
        existing_app_key=$({ grep '^APP_KEY=' "$INSTALL_DIR/.env" || true; } | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
        existing_mail_from=$({ grep '^MAIL_FROM_ADDRESS=' "$INSTALL_DIR/.env" || true; } | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
    fi
    local mail_from="${PANEL_EMAIL:-${existing_mail_from:-}}"
    if [ -z "$mail_from" ]; then
        mail_from="noreply@$(hostname -f 2>/dev/null || hostname)"
    fi

    local env_tmp
    env_tmp=$(mktemp)
    cat > "$env_tmp" <<EOF
APP_NAME="OpenPanel"
APP_ENV=production
APP_KEY=${existing_app_key}
APP_DEBUG=false
APP_URL=https://${SERVER_IP}:2087

APP_LOCALE=en
APP_FALLBACK_LOCALE=en

BCRYPT_ROUNDS=12
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
CACHE_STORE=file

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=25
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="${mail_from}"
MAIL_FROM_NAME="OpenPanel"
EOF

    if [ -f "$INSTALL_DIR/.env" ] && ! cmp -s "$env_tmp" "$INSTALL_DIR/.env"; then
        install -d -m 0700 /var/lib/openpanel/backups/env
        local env_backup="/var/lib/openpanel/backups/env/.env.bak.$(date +%s)"
        cp -p "$INSTALL_DIR/.env" "$env_backup" 2>/dev/null || true
        chmod 0600 "$env_backup" 2>/dev/null || true
        log "Existing .env backed up to secure root-only backup directory"
    fi

    if ! install -m 0640 -o root -g nginx "$env_tmp" "$INSTALL_DIR/.env" 2>/dev/null; then
        cp "$env_tmp" "$INSTALL_DIR/.env"
        chown root:root "$INSTALL_DIR/.env" 2>/dev/null || true
        chmod 0640 "$INSTALL_DIR/.env"
    fi
    rm -f "$env_tmp"

    cd "$INSTALL_DIR"
    if [ -n "$existing_app_key" ]; then
        log "Preserved existing APP_KEY"
    else
        php artisan key:generate --force 2>&1 | tee -a "$LOG_FILE"
        chown root:nginx "$INSTALL_DIR/.env" 2>/dev/null || chown root:root "$INSTALL_DIR/.env" 2>/dev/null || true
        chmod 0640 "$INSTALL_DIR/.env"
    fi

    log "Environment configured"
}

run_migrations() {
    step "Running Database Migrations"

    cd "$INSTALL_DIR"
    php artisan migrate --force 2>&1 | tee -a "$LOG_FILE"

    log "Migrations complete"
}

create_admin_user() {
    step "Verifying Admin Access"

    if ! id -u root &>/dev/null; then
        log "ERROR: root user not found"
        exit 1
    fi

    log "Admin user is root (uid 0) — authenticated via /etc/shadow"
}

build_auth_helper() {
    step "Building Auth Helper"

    mkdir -p "$INSTALL_DIR/bin"

    if [ -f "$INSTALL_DIR/bin/auth-check.c" ]; then
        gcc -o "$INSTALL_DIR/bin/auth-check" "$INSTALL_DIR/bin/auth-check.c" -lcrypt 2>&1 | tee -a "$LOG_FILE"
        chown root:root "$INSTALL_DIR/bin/auth-check"
        chmod 4755 "$INSTALL_DIR/bin/auth-check"
        log "Auth helper compiled and setuid root"
    else
        log "WARNING: auth-check.c not found, login will not work"
    fi
}

build_assets() {
    step "Building Frontend Assets"

    cd "$INSTALL_DIR"
    if command -v npm &>/dev/null; then
        local manifest="$INSTALL_DIR/public/build/manifest.json"
        local needs_build="n"

        if [[ "${OPENPANEL_REBUILD_ASSETS:-n}" == "y" || ! -f "$manifest" ]]; then
            needs_build="y"
        fi

        if [[ "$needs_build" == "y" ]]; then
            npm install --ignore-scripts 2>&1 | tee -a "$LOG_FILE"
            npm run build 2>&1 | tee -a "$LOG_FILE"
            log "Assets built"
        else
            log "Frontend assets already current"
        fi
    else
        warn "npm not available, using CDN assets"
    fi
}

repair_app_permissions() {
    [ -d "$INSTALL_DIR" ] || return

    find "$INSTALL_DIR" \
        -path "$INSTALL_DIR/.env" -prune -o \
        -path "$INSTALL_DIR/node_modules" -prune -o \
        -path "$INSTALL_DIR/storage" -prune -o \
        -path "$INSTALL_DIR/bootstrap/cache" -prune -o \
        -path "$INSTALL_DIR/bin/auth-check" -prune -o \
        -type d -exec chmod 0755 {} +

    find "$INSTALL_DIR" \
        -path "$INSTALL_DIR/.env" -prune -o \
        -path "$INSTALL_DIR/node_modules" -prune -o \
        -path "$INSTALL_DIR/storage" -prune -o \
        -path "$INSTALL_DIR/bootstrap/cache" -prune -o \
        -path "$INSTALL_DIR/bin/auth-check" -prune -o \
        -type f -exec chmod 0644 {} +

    [ -f "$INSTALL_DIR/artisan" ] && chmod 0755 "$INSTALL_DIR/artisan"
    [ -f "$INSTALL_DIR/install-openpanel.sh" ] && chmod 0755 "$INSTALL_DIR/install-openpanel.sh"
    [ -f "$INSTALL_DIR/tests/tools/file_change_scan.sh" ] && chmod 0755 "$INSTALL_DIR/tests/tools/file_change_scan.sh"
    [ -f "$INSTALL_DIR/tests/varnish_cache_regression.sh" ] && chmod 0755 "$INSTALL_DIR/tests/varnish_cache_regression.sh"
    [ -f "$INSTALL_DIR/bin/auth-check" ] && chmod 4755 "$INSTALL_DIR/bin/auth-check"
    [ -d "$INSTALL_DIR/node_modules/.bin" ] && find "$INSTALL_DIR/node_modules/.bin" -type f -exec chmod 0755 {} +

    if [ -f "$INSTALL_DIR/.env" ]; then
        chown root:nginx "$INSTALL_DIR/.env" 2>/dev/null || chown root:root "$INSTALL_DIR/.env" 2>/dev/null || true
        chmod 0640 "$INSTALL_DIR/.env"
    fi

    if [ -d "$INSTALL_DIR/storage" ] && [ -d "$INSTALL_DIR/bootstrap/cache" ]; then
        chown -R nginx:nginx "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache" 2>/dev/null || true
        chmod -R 775 "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache" 2>/dev/null || true
    fi
}

configure_nginx() {
    step "Configuring Nginx"

    # Include per-user vhosts (must be before catch-all server blocks)
    mkdir -p /etc/nginx/conf.d/users
    mkdir -p /etc/nginx/snippets
    if [ ! -f /etc/nginx/snippets/openpanel-phpmyadmin.conf ]; then
        echo "# OpenPanel phpMyAdmin include; populated when phpMyAdmin is installed" > /etc/nginx/snippets/openpanel-phpmyadmin.conf
    fi
    cat > /etc/nginx/conf.d/00-users.conf <<'EONGX0'
include /etc/nginx/conf.d/users/*.conf;
EONGX0

    cat > /etc/nginx/conf.d/openpanel.conf <<'EONGX'
server {
    listen 2087 ssl http2;
    listen [::]:2087 ssl http2;
    server_name _;

    root /usr/local/openpanel/public;
    index index.php;

    ssl_certificate /etc/pki/tls/certs/openpanel.crt;
    ssl_certificate_key /etc/pki/tls/private/openpanel.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    client_max_body_size 512M;
    client_body_timeout 300;
    client_header_timeout 300;

    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript;
    gzip_min_length 1000;

    include /etc/nginx/snippets/openpanel-phpmyadmin.conf;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include /etc/nginx/fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
EONGX

    cat > /etc/nginx/conf.d/openpanel-user.conf <<'EONGX2'
server {
    listen 2083 ssl http2;
    listen [::]:2083 ssl http2;
    server_name _;

    root /usr/local/openpanel/public;
    index index.php;

    ssl_certificate /etc/pki/tls/certs/openpanel.crt;
    ssl_certificate_key /etc/pki/tls/private/openpanel.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    client_max_body_size 256M;
    client_body_timeout 300;
    client_header_timeout 300;

    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript;
    gzip_min_length 1000;

    include /etc/nginx/snippets/openpanel-phpmyadmin.conf;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include /etc/nginx/fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
EONGX2

    # Configure PHP-FPM
    if [ -f /etc/php-fpm.d/www.conf ]; then
        sed -i 's/^user = apache/user = nginx/' /etc/php-fpm.d/www.conf
        sed -i 's/^group = apache/group = nginx/' /etc/php-fpm.d/www.conf
        sed -i 's|^listen = .*|listen = /run/php-fpm/www.sock|' /etc/php-fpm.d/www.conf
        sed -i 's/^;listen.owner = nobody/listen.owner = nginx/' /etc/php-fpm.d/www.conf
        sed -i 's/^;listen.group = nobody/listen.group = nginx/' /etc/php-fpm.d/www.conf
        sed -i 's/^pm.max_children = .*/pm.max_children = 50/' /etc/php-fpm.d/www.conf
        sed -i 's/^pm.start_servers = .*/pm.start_servers = 5/' /etc/php-fpm.d/www.conf
        sed -i 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 5/' /etc/php-fpm.d/www.conf
        sed -i 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 35/' /etc/php-fpm.d/www.conf
        sed -i 's/^pm.max_requests = .*/pm.max_requests = 500/' /etc/php-fpm.d/www.conf
    fi

    mkdir -p /run/php-fpm
    chown nginx:nginx /run/php-fpm
    mkdir -p /etc/php-fpm.d/users
    if ! grep -q 'include=/etc/php-fpm.d/users' /etc/php-fpm.conf 2>/dev/null; then
        echo 'include=/etc/php-fpm.d/users/*.conf' >> /etc/php-fpm.conf
    fi

    systemctl enable php-fpm
    systemctl restart php-fpm

    nginx -t 2>&1 | tee -a "$LOG_FILE" || err "Nginx configuration test failed"

    systemctl enable nginx
    systemctl restart nginx

    log "Nginx configured on port 2087"
}

install_httpd() {
    step "Installing Apache (httpd)"
    dnf -y install httpd 2>&1 | tee -a "$LOG_FILE"

    # Configure Apache to listen on 8080 (backend)
    sed -i 's/^Listen 80$/Listen 8080/' /etc/httpd/conf/httpd.conf 2>/dev/null || true
    sed -i 's/^Listen 80 /Listen 8080 /' /etc/httpd/conf/httpd.conf 2>/dev/null || true

    # Enable PHP-FPM for Apache
    if [ -f /etc/httpd/conf.modules.d/00-proxy.conf ]; then
        sed -i 's/^#\(.*proxy_fcgi.*\)/\1/' /etc/httpd/conf.modules.d/00-proxy.conf 2>/dev/null || true
    fi

    # Add PHP-FPM handler
    cat > /etc/httpd/conf.d/php-fpm.conf <<'APACHEPHP'
<FilesMatch \.php$>
    SetHandler "proxy:unix:/run/php-fpm/www.sock|fcgi://localhost"
</FilesMatch>
APACHEPHP

    # Include user vhost configs from subdirectory
    mkdir -p /etc/httpd/conf.d/users
    if ! grep -qF 'IncludeOptional conf.d/users/*.conf' /etc/httpd/conf/httpd.conf 2>/dev/null; then
        echo 'IncludeOptional conf.d/users/*.conf' >> /etc/httpd/conf/httpd.conf
    fi

    systemctl enable httpd 2>&1 | tee -a "$LOG_FILE"
    log "Apache installed on port 8080"
}

install_varnish() {
    step "Installing Varnish"

    # Install from EPEL or Varnish repo
    dnf -y install epel-release 2>&1 | tee -a "$LOG_FILE" || true
    dnf -y install varnish 2>&1 | tee -a "$LOG_FILE" || {
        # If not available in default repos, try Varnish Cache repo
        cat > /etc/yum.repos.d/varnish.repo <<'REPO'
[varnishcache_varnish70]
name=varnishcache_varnish70
baseurl=https://packagecloud.io/varnishcache/varnish70/el/9/$basearch
repo_gpgcheck=0
gpgcheck=0
enabled=1
gpgkey=https://packagecloud.io/varnishcache/varnish70/gpgkey
sslverify=1
sslcacert=/etc/pki/tls/certs/ca-bundle.crt
metadata_expire=300
REPO
        dnf -y install varnish 2>&1 | tee -a "$LOG_FILE"
    }

    # Configure default VCL
    mkdir -p /etc/varnish/conf.d/users
    {
        echo "# OpenPanel generated user VCL includes"
        for user_vcl in /etc/varnish/conf.d/users/*.vcl; do
            [ -f "$user_vcl" ] || continue
            printf 'include "%s";\n' "$user_vcl"
        done
    } > /etc/varnish/conf.d/openpanel-users.vcl
    chmod 0644 /etc/varnish/conf.d/openpanel-users.vcl
    cat > /etc/varnish/default.vcl <<'VCL'
vcl 4.0;

backend default {
    .host = "127.0.0.1";
    .port = "8080";
}

include "/etc/varnish/conf.d/openpanel-users.vcl";

sub vcl_recv {
    set req.http.X-Forwarded-For = client.ip;
    set req.http.X-Real-IP = client.ip;

    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }
    if (req.http.Authorization) {
        return (pass);
    }
    return (hash);
}

sub vcl_backend_response {
    if (bereq.url ~ "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot|webp)(\?.*)?$") {
        unset beresp.http.Set-Cookie;
        set beresp.ttl = 1d;
        set beresp.grace = 1h;
        return (deliver);
    }
    if (beresp.status != 200 && beresp.status != 301 && beresp.status != 302) {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }
    if (beresp.http.Surrogate-Control ~ "(?i)no-store") {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }
    if (beresp.http.Cache-Control ~ "(?i)(private|no-cache|no-store)") {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }
    if (beresp.http.Pragma ~ "(?i)no-cache") {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }
    set beresp.ttl = 5m;
    set beresp.grace = 1h;
}

sub vcl_deliver {
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
    } else {
        set resp.http.X-Cache = "MISS";
    }
}
VCL
    chmod 0644 /etc/varnish/default.vcl

    # Set Varnish to listen on 6081
    if [ -f /etc/varnish/varnish.params ]; then
        sed -i 's/^VARNISH_LISTEN_ADDRESS=.*/VARNISH_LISTEN_ADDRESS=127.0.0.1/' /etc/varnish/varnish.params 2>/dev/null || true
        sed -i 's/^VARNISH_LISTEN_PORT=.*/VARNISH_LISTEN_PORT=6081/' /etc/varnish/varnish.params 2>/dev/null || true
    fi
    # Override systemd to use port 6081
    mkdir -p /etc/systemd/system/varnish.service.d
    cat > /etc/systemd/system/varnish.service.d/override.conf <<'OVERRIDE'
[Service]
ExecStart=
ExecStart=/usr/sbin/varnishd -a 127.0.0.1:6081 -f /etc/varnish/default.vcl -s malloc,256m
OVERRIDE
    systemctl daemon-reload

    systemctl enable varnish 2>&1 | tee -a "$LOG_FILE"
    log "Varnish installed on port 6081 (backend: Apache 8080)"
}

configure_varnish_apache_nginx() {
    step "Configuring nginx_varnish_apache stack"

    # Replace nginx.conf with clean version (no default server block on port 80)
    cat > /etc/nginx/nginx.conf <<'NGINXMAIN'
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /run/nginx.pid;

include /usr/share/nginx/modules/*.conf;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';
    access_log /var/log/nginx/access.log main;

    sendfile on;
    tcp_nopush on;
    keepalive_timeout 65;
    types_hash_max_size 4096;
    client_max_body_size 512M;

    include /etc/nginx/conf.d/*.conf;
}
NGINXMAIN

    # Nginx: public 80/443 → proxy to Varnish 6081
    cat > /etc/nginx/conf.d/openpanel-stack.conf <<'NGINXV'
server {
    listen 80;
    server_name _;
    location / {
        proxy_pass http://127.0.0.1:6081;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
server {
    listen 443 ssl http2;
    server_name _;
    ssl_certificate /etc/pki/tls/certs/openpanel.crt;
    ssl_certificate_key /etc/pki/tls/private/openpanel.key;
    location / {
        proxy_pass http://127.0.0.1:6081;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
NGINXV

    nginx -t 2>&1 | tee -a "$LOG_FILE" || err "Nginx stack config failed"
    systemctl restart nginx

    # Apache: backend 8080
    httpd -t 2>&1 | tee -a "$LOG_FILE" || warn "Apache config test failed"
    systemctl restart httpd

    # Varnish: 6081 → Apache 8080
    varnishd -C -f /etc/varnish/default.vcl 2>&1 | tee -a "$LOG_FILE" || warn "Varnish VCL test failed"
    systemctl restart varnish

    # Save stack setting
    mkdir -p /etc/openpanel
    echo "nginx_varnish_apache" > /etc/openpanel/web_stack

    # Update DB stack setting (migrate defaults to nginx_phpfpm)
    if command -v mysql &>/dev/null; then
        DB_NAME="${DB_DATABASE:-open_panel}"
        mysql -e "UPDATE \`${DB_NAME}\`.\`web_stack_settings\` SET \`active_stack\`='nginx_varnish_apache' WHERE \`id\`=1;" 2>/dev/null || true
    fi

    log "Stack nginx_varnish_apache configured: nginx(80/443) → varnish(6081) → apache(8080)"
}

generate_ssl() {
    step "Generating SSL Certificate"

    mkdir -p /etc/pki/tls/certs /etc/pki/tls/private

    openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
        -keyout /etc/pki/tls/private/openpanel.key \
        -out /etc/pki/tls/certs/openpanel.crt \
        -subj "/C=US/ST=California/L=SanFrancisco/O=OpenPanel/CN=${SERVER_IP}" \
        2>&1 | tee -a "$LOG_FILE"

    chmod 600 /etc/pki/tls/private/openpanel.key
    chmod 644 /etc/pki/tls/certs/openpanel.crt

    log "Self-signed SSL certificate generated"
}

setup_cron() {
    step "Setting Up Cron Jobs"

    cat > /etc/cron.d/openpanel <<EOF
* * * * * cd ${INSTALL_DIR} && php artisan schedule:run >> /var/log/openpanel-schedule.log 2>&1
0 2 * * * cd ${INSTALL_DIR} && php artisan cache:clear >> /var/log/openpanel-cache.log 2>&1
0 3 * * 0 cd ${INSTALL_DIR} && php artisan view:clear >> /var/log/openpanel-cache.log 2>&1
*/5 * * * * cd ${INSTALL_DIR} && php artisan queue:work --stop-when-empty >> /var/log/openpanel-queue.log 2>&1
EOF
    chmod 0644 /etc/cron.d/openpanel

    log "Cron jobs configured"
}

optimize_app() {
    step "Optimizing Application"

    cd "$INSTALL_DIR"
    php artisan config:cache 2>&1 | tee -a "$LOG_FILE"
    php artisan route:cache 2>&1 | tee -a "$LOG_FILE"
    php artisan view:cache 2>&1 | tee -a "$LOG_FILE"
    if [ -e "$INSTALL_DIR/public/storage" ] || [ -L "$INSTALL_DIR/public/storage" ]; then
        log "Storage link already present"
    else
        php artisan storage:link 2>&1 | tee -a "$LOG_FILE"
    fi

    chown -R nginx:nginx "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache"
    chmod -R 775 "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache"
    repair_app_permissions

    log "Application optimized"
}

install_mail() {
    if [[ ! "$INSTALL_MAIL" =~ ^[Yy]$ ]]; then
        return
    fi

    step "Installing Mail Server"

    dnf -y install postfix dovecot dovecot-mysql dovecot-pigeonhole \
        cyrus-sasl-devel cyrus-sasl-sql 2>&1 | tee -a "$LOG_FILE"

    POSTFIX_PASSWORD=$(openssl rand -base64 12 | tr -dc A-Za-z0-9 | head -c 16)
    HOSTNAME=$(hostname -f 2>/dev/null || hostname)

    mysql -u root -p"$MYSQL_ROOT_PASSWORD" <<EOSQL
CREATE DATABASE IF NOT EXISTS postfix CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'postfix'@'localhost' IDENTIFIED BY '${POSTFIX_PASSWORD}';
GRANT ALL PRIVILEGES ON postfix.* TO 'postfix'@'localhost';
FLUSH PRIVILEGES;
EOSQL

    sed -i "s|inet_interfaces = localhost|inet_interfaces = all|" /etc/postfix/main.cf
    sed -i "s|#home_mailbox = Maildir/|home_mailbox = Maildir/|" /etc/postfix/main.cf
    sed -i "s|^myhostname =.*|myhostname = ${HOSTNAME}|" /etc/postfix/main.cf

    # Configure Dovecot for PAM auth + Maildir
    if [ -f /etc/dovecot/conf.d/10-auth.conf ]; then
        sed -i 's/^disable_plaintext_auth = yes/disable_plaintext_auth = no/' /etc/dovecot/conf.d/10-auth.conf 2>/dev/null || true
        sed -i 's/^#disable_plaintext_auth = yes/disable_plaintext_auth = no/' /etc/dovecot/conf.d/10-auth.conf 2>/dev/null || true
    fi
    if [ -f /etc/dovecot/conf.d/10-mail.conf ]; then
        sed -i 's|^#mail_location =.*|mail_location = maildir:~/Maildir|' /etc/dovecot/conf.d/10-mail.conf 2>/dev/null || true
        sed -i 's|^mail_location =.*|mail_location = maildir:~/Maildir|' /etc/dovecot/conf.d/10-mail.conf 2>/dev/null || true
    fi

    systemctl enable postfix dovecot
    systemctl restart postfix dovecot

    log "Mail server installed"
}

install_opendkim() {
    if [[ ! "$INSTALL_MAIL" =~ ^[Yy]$ ]]; then
        return
    fi

    step "Installing OpenDKIM"

    dnf -y install epel-release 2>&1 | tee -a "$LOG_FILE" || true
    if ! rpm -q opendkim >/dev/null 2>&1; then
        dnf -y install opendkim opendkim-tools 2>&1 | tee -a "$LOG_FILE" || \
            dnf -y install opendkim 2>&1 | tee -a "$LOG_FILE"
    fi

    mkdir -p /etc/opendkim/keys
    touch /etc/opendkim/KeyTable /etc/opendkim/SigningTable /etc/opendkim/TrustedHosts

    local host_name
    host_name=$(hostname -f 2>/dev/null || hostname)
    for host in 127.0.0.1 ::1 localhost "$host_name"; do
        grep -qxF "$host" /etc/opendkim/TrustedHosts 2>/dev/null || echo "$host" >> /etc/opendkim/TrustedHosts
    done

    cat > /etc/opendkim.conf <<'DKIMCONF'
Syslog                  yes
SyslogSuccess           yes
Canonicalization        relaxed/simple
Mode                    sv
SubDomains              no
OversignHeaders         From
UserID                  opendkim:opendkim
Socket                  inet:8891@localhost
PidFile                 /run/opendkim/opendkim.pid
UMask                   002
KeyTable                refile:/etc/opendkim/KeyTable
SigningTable            refile:/etc/opendkim/SigningTable
ExternalIgnoreList      refile:/etc/opendkim/TrustedHosts
InternalHosts           refile:/etc/opendkim/TrustedHosts
DKIMCONF

    chown -R opendkim:opendkim /etc/opendkim 2>/dev/null || true
    chmod 0755 /etc/opendkim
    chmod 0750 /etc/opendkim/keys
    chmod 0644 /etc/opendkim/KeyTable /etc/opendkim/SigningTable /etc/opendkim/TrustedHosts /etc/opendkim.conf

    postconf -e 'milter_default_action = accept'
    postconf -e 'milter_protocol = 6'
    postconf -e 'smtpd_milters = inet:127.0.0.1:8891'
    postconf -e 'non_smtpd_milters = inet:127.0.0.1:8891'

    postfix check 2>&1 | tee -a "$LOG_FILE" || err "Postfix validation failed after OpenDKIM setup"
    systemctl enable opendkim postfix
    systemctl restart opendkim postfix

    log "OpenDKIM installed"
}

install_roundcube() {
    if [[ ! "$INSTALL_MAIL" =~ ^[Yy]$ || ! "${INSTALL_WEBMAIL:-Y}" =~ ^[Yy]$ ]]; then
        return
    fi

    step "Installing Roundcube Webmail"

    dnf -y install epel-release 2>&1 | tee -a "$LOG_FILE" || true
    dnf -y install roundcubemail 2>&1 | tee -a "$LOG_FILE"

    local rc_pass=""
    local rc_key=""
    if [ -f /etc/roundcubemail/config.inc.php ]; then
        rc_pass=$(php -r '$c=@file_get_contents("/etc/roundcubemail/config.inc.php"); if (preg_match("#mysql://roundcube:([^@]+)@#", $c, $m)) echo $m[1];' 2>/dev/null || true)
        rc_key=$(php -r '$config=[]; @include "/etc/roundcubemail/config.inc.php"; echo $config["des_key"] ?? "";' 2>/dev/null || true)
    fi

    rc_pass="${rc_pass:-$(openssl rand -base64 24 | tr -dc A-Za-z0-9 | head -c 28)}"
    rc_key="${rc_key:-$(openssl rand -base64 24 | tr -dc A-Za-z0-9 | head -c 24)}"

    mysql -u root -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS roundcube CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'roundcube'@'localhost' IDENTIFIED BY '${rc_pass}';
ALTER USER 'roundcube'@'localhost' IDENTIFIED BY '${rc_pass}';
GRANT ALL PRIVILEGES ON roundcube.* TO 'roundcube'@'localhost';
FLUSH PRIVILEGES;
SQL

    if ! mysql -u root -p"$MYSQL_ROOT_PASSWORD" -N -e "SHOW TABLES FROM roundcube LIKE 'users';" | grep -q users; then
        mysql -u root -p"$MYSQL_ROOT_PASSWORD" roundcube < /usr/share/roundcubemail/SQL/mysql.initial.sql
    fi

    install -d -m 0750 -o nginx -g nginx /var/lib/roundcubemail/temp /var/log/roundcubemail
    chgrp -R nginx /etc/roundcubemail
    find /etc/roundcubemail -type d -exec chmod 0750 {} +
    find /etc/roundcubemail -type f -exec chmod 0640 {} +

    local rc_tmp
    rc_tmp=$(mktemp)
    cat > "$rc_tmp" <<PHP
<?php
include_once('/etc/roundcubemail/defaults.inc.php');
include_once('/etc/roundcubemail/main.inc.php');
\$config['db_dsnw'] = 'mysql://roundcube:${rc_pass}@localhost/roundcube';
\$config['default_host'] = '127.0.0.1';
\$config['default_port'] = 143;
\$config['smtp_server'] = '127.0.0.1';
\$config['smtp_port'] = 587;
\$config['smtp_user'] = '%u';
\$config['smtp_pass'] = '%p';
\$config['smtp_auth_type'] = 'LOGIN';
\$config['support_url'] = '';
\$config['product_name'] = 'OpenPanel Webmail';
\$config['des_key'] = '${rc_key}';
\$config['plugins'] = ['archive', 'zipdownload'];
\$config['skin'] = 'elastic';
\$config['temp_dir'] = '/var/lib/roundcubemail/temp';
\$config['log_dir'] = '/var/log/roundcubemail';
\$config['enable_installer'] = false;
\$config['log_driver'] = 'file';
\$config['drafts_mbox'] = 'Drafts';
\$config['junk_mbox'] = 'Junk';
\$config['sent_mbox'] = 'Sent';
\$config['trash_mbox'] = 'Trash';
\$config['create_default_folders'] = true;
\$config['imap_conn_options'] = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
\$config['smtp_conn_options'] = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
PHP
    php -l "$rc_tmp" >/dev/null
    install -m 0640 -o root -g nginx "$rc_tmp" /etc/roundcubemail/config.inc.php
    rm -f "$rc_tmp"

    cat > /etc/nginx/conf.d/openpanel-webmail.conf <<'NGINX'
server {
    listen 2095;
    listen [::]:2095;
    server_name _;

    location ^~ /.well-known/acme-challenge/ {
        root /var/www/html;
        try_files $uri =404;
    }

    return 301 https://$host:2096$request_uri;
}

server {
    listen 2096 ssl http2;
    listen [::]:2096 ssl http2;
    server_name _;

    root /usr/share/roundcubemail/public_html;
    index index.php index.html;

    ssl_certificate /etc/pki/tls/certs/openpanel.crt;
    ssl_certificate_key /etc/pki/tls/private/openpanel.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include /etc/nginx/fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_read_timeout 300;
    }

    location ~ ^/(README|INSTALL|LICENSE|CHANGELOG|UPGRADING)$ { deny all; }
    location ~ ^/(bin|SQL|config|logs|temp)/ { deny all; }
    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
    chmod 0644 /etc/nginx/conf.d/openpanel-webmail.conf

    nginx -t 2>&1 | tee -a "$LOG_FILE" || err "Nginx configuration test failed after Roundcube install"
    systemctl reload nginx 2>&1 | tee -a "$LOG_FILE" || systemctl restart nginx 2>&1 | tee -a "$LOG_FILE"
    systemctl restart php-fpm 2>&1 | tee -a "$LOG_FILE" || true

    log "Roundcube webmail installed on ports 2095/2096"
}

install_phpmyadmin() {
    if [[ ! "${INSTALL_PHPMYADMIN:-Y}" =~ ^[Yy]$ ]]; then
        return
    fi

    step "Installing phpMyAdmin"

    dnf -y install epel-release 2>&1 | tee -a "$LOG_FILE" || true
    dnf -y install phpMyAdmin 2>&1 | tee -a "$LOG_FILE" || dnf -y install phpmyadmin 2>&1 | tee -a "$LOG_FILE"

    local pma_src=""
    for candidate in /usr/share/phpMyAdmin /usr/share/phpmyadmin; do
        if [ -f "$candidate/index.php" ]; then
            pma_src="$candidate"
            break
        fi
    done
    [ -n "$pma_src" ] || err "phpMyAdmin package installed but index.php was not found"

    if [ "$pma_src" != "/usr/share/phpmyadmin" ]; then
        ln -sfn "$pma_src" /usr/share/phpmyadmin
    fi

    local pma_conf_dir="/etc/phpMyAdmin"
    [ -d /etc/phpmyadmin ] && pma_conf_dir="/etc/phpmyadmin"
    mkdir -p "$pma_conf_dir" /var/lib/phpMyAdmin/temp /var/lib/phpmyadmin/temp /etc/nginx/snippets
    chown -R nginx:nginx /var/lib/phpMyAdmin /var/lib/phpmyadmin 2>/dev/null || true
    chmod 0750 /var/lib/phpMyAdmin /var/lib/phpMyAdmin/temp /var/lib/phpmyadmin /var/lib/phpmyadmin/temp 2>/dev/null || true

    local pma_secret=""
    if [ -f "$pma_conf_dir/config.inc.php" ]; then
        pma_secret=$(php -r '$cfg=[]; @include $argv[1]; echo $cfg["blowfish_secret"] ?? "";' "$pma_conf_dir/config.inc.php" 2>/dev/null || true)
    fi
    pma_secret="${pma_secret:-$(openssl rand -base64 48 | tr -dc A-Za-z0-9 | head -c 32)}"

    local pma_tmp
    pma_tmp=$(mktemp)
    cat > "$pma_tmp" <<PHP
<?php
\$cfg['blowfish_secret'] = '${pma_secret}';
\$i = 0;
\$i++;
\$cfg['Servers'][\$i]['auth_type'] = 'cookie';
\$cfg['Servers'][\$i]['host'] = '127.0.0.1';
\$cfg['Servers'][\$i]['port'] = '3306';
\$cfg['Servers'][\$i]['connect_type'] = 'tcp';
\$cfg['Servers'][\$i]['compress'] = false;
\$cfg['Servers'][\$i]['AllowNoPassword'] = false;
\$cfg['Servers'][\$i]['AllowRoot'] = false;
\$cfg['Servers'][\$i]['AllowDeny']['order'] = 'deny,allow';
\$cfg['Servers'][\$i]['AllowDeny']['rules'] = ['deny root from all'];
\$cfg['TempDir'] = is_dir('/var/lib/phpMyAdmin/temp') ? '/var/lib/phpMyAdmin/temp' : '/var/lib/phpmyadmin/temp';
\$cfg['UploadDir'] = '';
\$cfg['SaveDir'] = '';
\$cfg['VersionCheck'] = false;
\$cfg['SendErrorReports'] = 'never';
\$cfg['PmaNoRelation_DisableWarning'] = true;
\$cfg['LoginCookieValidity'] = 1440;
PHP
    php -l "$pma_tmp" >/dev/null
    install -m 0640 -o root -g nginx "$pma_tmp" "$pma_conf_dir/config.inc.php"
    rm -f "$pma_tmp"

    mkdir -p /etc/phpMyAdmin
    chown root:nginx /etc/phpMyAdmin
    chmod 0750 /etc/phpMyAdmin
    cat > /etc/phpMyAdmin/openpanel-root-block.php <<'PHP'
<?php
if (isset($_POST['pma_username']) && strtolower(trim((string) $_POST['pma_username'])) === 'root') {
    http_response_code(403);
    exit('Root phpMyAdmin login is disabled by OpenPanel.');
}
PHP
    chown root:nginx /etc/phpMyAdmin/openpanel-root-block.php
    chmod 0640 /etc/phpMyAdmin/openpanel-root-block.php

    cat > /etc/nginx/snippets/openpanel-phpmyadmin.conf <<'NGINX'
location = /phpmyadmin {
    return 302 /phpmyadmin/;
}

location ~ ^/phpmyadmin/(setup|libraries|templates|vendor|sql|doc|test|tests|examples)/ {
    deny all;
}

location ~ ^/phpmyadmin/(.+\.php)$ {
    root /usr/share;
    include /etc/nginx/fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param DOCUMENT_ROOT /usr/share/phpmyadmin;
    fastcgi_param HTTPS on;
    fastcgi_param PHP_VALUE "auto_prepend_file=/etc/phpMyAdmin/openpanel-root-block.php";
    fastcgi_pass unix:/run/php-fpm/www.sock;
    fastcgi_read_timeout 300;
}

location /phpmyadmin/ {
    root /usr/share;
    index index.php;
    try_files $uri $uri/ /phpmyadmin/index.php?$query_string;
}
NGINX
    chmod 0644 /etc/nginx/snippets/openpanel-phpmyadmin.conf

    for panel_conf in /etc/nginx/conf.d/openpanel.conf /etc/nginx/conf.d/openpanel-user.conf; do
        if [ -f "$panel_conf" ] && ! grep -qF 'openpanel-phpmyadmin.conf' "$panel_conf"; then
            sed -i '/index index.php;/a\    include /etc/nginx/snippets/openpanel-phpmyadmin.conf;' "$panel_conf"
        fi
    done

    nginx -t 2>&1 | tee -a "$LOG_FILE" || err "Nginx configuration test failed after phpMyAdmin install"
    systemctl reload nginx 2>&1 | tee -a "$LOG_FILE" || systemctl restart nginx 2>&1 | tee -a "$LOG_FILE"
    systemctl restart php-fpm 2>&1 | tee -a "$LOG_FILE" || true

    log "phpMyAdmin installed at /phpmyadmin/ with cookie auth"
}

install_dns() {
    step "Installing DNS Server (BIND)"

    dnf -y install bind bind-utils 2>&1 | tee -a "$LOG_FILE"

    if [ -f /etc/named.conf ]; then
        cp /etc/named.conf /etc/named.conf.bak.$(date +%s) 2>/dev/null || true
    fi

    # Allow queries from any client (required for hosting DNS)
    sed -i 's/listen-on port 53 { 127.0.0.1; };/listen-on port 53 { 127.0.0.1; any; };/' /etc/named.conf 2>/dev/null || true
    sed -i 's/listen-on-v6 port 53 { ::1; };/listen-on-v6 port 53 { ::1; any; };/' /etc/named.conf 2>/dev/null || true
    sed -i 's/allow-query     { localhost; };/allow-query     { localhost; any; };/' /etc/named.conf 2>/dev/null || true

    # Validate config before starting
    if named-checkconf 2>/dev/null; then
        systemctl enable named
        systemctl restart named 2>&1 | tee -a "$LOG_FILE" || warn "Named failed to start"
    else
        warn "named.conf validation failed — restoring backup"
        local latest_bak
        latest_bak=$(ls -t /etc/named.conf.bak.* 2>/dev/null | head -1)
        [ -n "$latest_bak" ] && cp "$latest_bak" /etc/named.conf
        systemctl enable named
        systemctl restart named 2>&1 | tee -a "$LOG_FILE" || warn "Named still failed after backup restore"
    fi

    log "BIND DNS installed"
}

install_ftp() {
    step "Installing FTP Server (Pure-FTPd)"

    dnf -y install pure-ftpd 2>&1 | tee -a "$LOG_FILE"

    # Create required directories
    mkdir -p /etc/pure-ftpd /etc/pki/tls/private
    echo "yes" > /etc/pure-ftpd/no_unix_privs 2>/dev/null || true

    local pure_conf="/etc/pure-ftpd/pure-ftpd.conf"
    local ftps_cert="/etc/pki/tls/private/pure-ftpd.pem"
    local pure_rollback=""

    set_pureftpd_config() {
        local key="$1"
        local value="$2"
        local tmp
        tmp=$(mktemp)
        awk -v key="$key" -v value="$value" '
            BEGIN { done = 0; k = tolower(key) }
            {
                line = $0
                sub(/^[ \t#]*/, "", line)
                split(line, parts, /[ \t]+/)
                if (tolower(parts[1]) == k) {
                    if (!done) {
                        printf "%-28s %s\n", key, value
                        done = 1
                    }
                    next
                }
                print
            }
            END {
                if (!done) {
                    printf "%-28s %s\n", key, value
                }
            }
        ' "$pure_conf" > "$tmp" && install -m 0644 "$tmp" "$pure_conf"
        rm -f "$tmp"
    }

    if [ -f "$pure_conf" ]; then
        if [ ! -f /etc/pure-ftpd/pure-ftpd.conf.openpanel.orig ]; then
            cp "$pure_conf" /etc/pure-ftpd/pure-ftpd.conf.openpanel.orig 2>/dev/null || true
            chmod 0600 /etc/pure-ftpd/pure-ftpd.conf.openpanel.orig 2>/dev/null || true
        fi

        pure_rollback=$(mktemp)
        cp -p "$pure_conf" "$pure_rollback" 2>/dev/null || true

        if [ ! -s "$ftps_cert" ]; then
            local tmp_pem
            tmp_pem=$(mktemp)
            if [ -s /etc/pki/tls/private/openpanel.key ] && [ -s /etc/pki/tls/certs/openpanel.crt ]; then
                cat /etc/pki/tls/private/openpanel.key /etc/pki/tls/certs/openpanel.crt > "$tmp_pem"
            else
                local tmp_key tmp_crt
                tmp_key=$(mktemp)
                tmp_crt=$(mktemp)
                openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
                    -keyout "$tmp_key" \
                    -out "$tmp_crt" \
                    -subj "/C=US/ST=OpenPanel/L=OpenPanel/O=OpenPanel/OU=FTPS/CN=${HOSTNAME:-openpanel.local}" \
                    >/dev/null 2>&1
                cat "$tmp_key" "$tmp_crt" > "$tmp_pem"
                rm -f "$tmp_key" "$tmp_crt"
            fi
            install -m 0600 -o root -g root "$tmp_pem" "$ftps_cert"
            rm -f "$tmp_pem"
        fi
        chmod 0600 "$ftps_cert" 2>/dev/null || true

        set_pureftpd_config "PureDB" "/etc/pure-ftpd/pureftpd.pdb"
        set_pureftpd_config "ChrootEveryone" "yes"
        set_pureftpd_config "NoAnonymous" "yes"
        set_pureftpd_config "PassivePortRange" "30000 31000"
        set_pureftpd_config "TLS" "1"
        set_pureftpd_config "TLSCipherSuite" "HIGH"
        set_pureftpd_config "CertFile" "$ftps_cert"
    fi

    systemctl enable pure-ftpd
    if ! systemctl restart pure-ftpd 2>&1 | tee -a "$LOG_FILE"; then
        warn "Pure-FTPd failed after OpenPanel FTPS config; restoring previous config"
        [ -n "$pure_rollback" ] && [ -f "$pure_rollback" ] && cp -p "$pure_rollback" "$pure_conf"
        systemctl restart pure-ftpd 2>&1 | tee -a "$LOG_FILE" || warn "Pure-FTPd failed to start"
    fi
    rm -f "$pure_rollback" /etc/pure-ftpd/pure-ftpd.conf.openpanel.prev 2>/dev/null || true

    log "Pure-FTPd installed with explicit FTPS support"
}

install_firewall() {
    step "Configuring Firewall"

    dnf -y install firewalld 2>&1 | tee -a "$LOG_FILE"

    systemctl enable firewalld
    systemctl start firewalld 2>&1 | tee -a "$LOG_FILE" || warn "Firewalld failed to start"

    if systemctl is-active --quiet firewalld; then
        # Open required ports
        local ports=(
            22/tcp    # SSH
            80/tcp    # HTTP
            443/tcp   # HTTPS
            53/tcp    # DNS
            53/udp    # DNS
            21/tcp    # FTP
            25/tcp    # SMTP
            143/tcp   # IMAP
            587/tcp   # SMTP submission
            993/tcp   # IMAPS
            2082/tcp  # User panel HTTP
            2083/tcp  # User panel HTTPS
            2086/tcp  # Admin panel HTTP
            2087/tcp  # Admin panel HTTPS
            2095/tcp  # Webmail HTTP
            2096/tcp  # Webmail HTTPS
            30000-31000/tcp  # FTP passive
        )
        for port in "${ports[@]}"; do
            firewall-cmd --permanent --add-port="$port" 2>/dev/null || true
        done
        firewall-cmd --reload 2>&1 | tee -a "$LOG_FILE"
        log "Firewall configured"
    else
        warn "Firewalld not active, skipping firewall configuration"
    fi
}

install_wp_cli() {
    step "Installing WP-CLI"

    if ! command -v wp &>/dev/null; then
        curl -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar 2>&1 | tee -a "$LOG_FILE"
        chmod +x /usr/local/bin/wp
    fi

    if command -v wp &>/dev/null; then
        log "WP-CLI installed at /usr/local/bin/wp"
    else
        warn "WP-CLI installation failed"
    fi
}

install_redis() {
    step "Installing Redis"

    dnf -y install redis 2>&1 | tee -a "$LOG_FILE"

    if ! command -v redis-server &>/dev/null; then
        warn "Redis installation failed"
        return 1
    fi

    if [ -f /etc/redis/redis.conf ] || [ -f /etc/redis.conf ]; then
        REDIS_CONF=$( [ -f /etc/redis/redis.conf ] && echo "/etc/redis/redis.conf" || echo "/etc/redis.conf" )
        cp "$REDIS_CONF" "${REDIS_CONF}.bak.$(date +%s)" 2>/dev/null || true
        sed -i 's/^# maxmemory .*/maxmemory 256mb/' "$REDIS_CONF" 2>/dev/null || true
        sed -i 's/^maxmemory .*/maxmemory 256mb/' "$REDIS_CONF" 2>/dev/null || true
        sed -i 's/^# maxmemory-policy .*/maxmemory-policy allkeys-lru/' "$REDIS_CONF" 2>/dev/null || true
        sed -i 's/^maxmemory-policy .*/maxmemory-policy allkeys-lru/' "$REDIS_CONF" 2>/dev/null || true
    fi

    systemctl enable redis
    systemctl restart redis 2>&1 | tee -a "$LOG_FILE" || warn "Redis failed to start"

    if systemctl is-active --quiet redis; then
        log "Redis installed and running"
    else
        warn "Redis installed but not running"
    fi
}

setup_sudoers() {
    log "Configuring sudoers for panel operations..."
    cat > /etc/sudoers.d/openpanel <<'SUDOERS'
# OpenPanel: allow web panel to manage users and services
nginx ALL=(root) NOPASSWD: /usr/sbin/useradd, /usr/sbin/userdel, /usr/sbin/usermod
nginx ALL=(root) NOPASSWD: /usr/bin/passwd, /usr/sbin/chpasswd
nginx ALL=(root) NOPASSWD: /usr/bin/chage
nginx ALL=(root) NOPASSWD: /usr/sbin/semanage, /usr/sbin/restorecon
nginx ALL=(root) NOPASSWD: /usr/bin/systemctl restart php-fpm, /usr/bin/systemctl reload php-fpm
nginx ALL=(root) NOPASSWD: /usr/bin/systemctl restart nginx, /usr/bin/systemctl reload nginx
# Allow nginx/apache to run commands as hosting users (WP-CLI, file ops)
nginx ALL=(ALL) NOPASSWD: ALL
apache ALL=(root) NOPASSWD: /usr/sbin/useradd, /usr/sbin/userdel, /usr/sbin/usermod
apache ALL=(root) NOPASSWD: /usr/bin/passwd, /usr/sbin/chpasswd
apache ALL=(root) NOPASSWD: /usr/bin/chage
apache ALL=(root) NOPASSWD: /usr/bin/systemctl restart php-fpm, /usr/bin/systemctl reload php-fpm
apache ALL=(root) NOPASSWD: /usr/bin/systemctl restart nginx, /usr/bin/systemctl reload nginx
apache ALL=(root) NOPASSWD: /usr/bin/systemctl restart httpd, /usr/bin/systemctl reload httpd
apache ALL=(ALL) NOPASSWD: ALL
SUDOERS
    chmod 440 /etc/sudoers.d/openpanel
    log "Sudoers configured"
}

save_credentials() {
    local cred_file="/root/.openpanel-credentials"
    cat > "$cred_file" <<EOF
# OpenPanel Installation Credentials
# Generated: $(date)
# ====================================

Panel URL (Admin): https://${SERVER_IP}:2087
Panel URL (User):  https://${SERVER_IP}:2083
Admin User:     root (Linux root user)
Admin Password: (your root password - use 'passwd root' to change)

MySQL Root Password: ${MYSQL_ROOT_PASSWORD}
OpenPanel DB User:    ${DB_USER}
OpenPanel DB Password: ${DB_PASSWORD}
OpenPanel Database:   ${DB_NAME}

Install Directory:   ${INSTALL_DIR}
PHP Version:         ${PHP_VERSION}
OS Type:             ${OS_TYPE}

IMPORTANT: Save these credentials securely and delete this file!
EOF
    chmod 600 "$cred_file"

    log "Credentials saved to $cred_file"
}

harden_security() {
    step "Applying security hardening"

    # 1. Restrict /proc visibility (hide other users' processes)
    if ! grep -q 'hidepid=2' /etc/fstab; then
        # Get current /proc mount options
        local proc_opts=$(findmnt -n -o OPTIONS /proc 2>/dev/null || echo "defaults")
        if ! echo "$proc_opts" | grep -q 'hidepid'; then
            echo "proc    /proc    proc    defaults,hidepid=2,gid=proc    0 0" >> /etc/fstab
            mount -o remount,hidepid=2 /proc 2>/dev/null || true
            log "Applied hidepid=2 on /proc"
        fi
    fi

    # 2. Set kernel hardening sysctls
    cat > /etc/sysctl.d/99-openpanel-security.conf <<'SYSCTL'
kernel.yama.ptrace_scope = 1
fs.suid_dumpable = 0
SYSCTL
    sysctl -p /etc/sysctl.d/99-openpanel-security.conf 2>/dev/null || true
    log "Applied OpenPanel sysctl hardening"

    # 3. Disable core dumps
    echo "* hard core 0" > /etc/security/limits.d/99-openpanel.conf 2>/dev/null
    log "Disabled core dumps"

    # 4. Keep system umask compatible with root-managed service files.
    # Account isolation is enforced with per-account ownership, chmod, ACLs, and PHP-FPM pools.
    if grep -qE '^UMASK[[:space:]]+' /etc/login.defs 2>/dev/null; then
        sed -i 's/^UMASK[[:space:]].*/UMASK 022/' /etc/login.defs 2>/dev/null || true
    else
        echo "UMASK 022" >> /etc/login.defs 2>/dev/null
    fi
    log "Set default UMASK 022"

    # 5. Create proc group for hidepid
    if ! getent group proc > /dev/null 2>&1; then
        groupadd proc 2>/dev/null || true
        # Add nginx and apache to proc group so they can read /proc
        usermod -aG proc nginx 2>/dev/null || true
        usermod -aG proc apache 2>/dev/null || true
        log "Created proc group for hidepid"
    fi

    # 6. Restrict cron access
    echo "root" > /etc/cron.allow
    chmod 600 /etc/cron.allow
    log "Restricted cron access to root (hosting users use panel cron)"

    # 7. Set /tmp with nosuid,nodev
    mount -o remount,nosuid,nodev /tmp 2>/dev/null || true
    log "Hardened /tmp mount options"

    log "Security hardening complete"
}

print_summary() {
    echo ""
    echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║           OpenPanel Installation Complete!                   ║${NC}"
    echo -e "${GREEN}╠══════════════════════════════════════════════════════════════╣${NC}"
    echo -e "${GREEN}║${NC}  Admin Panel:    ${BLUE}https://${SERVER_IP}:2087${NC}"
    echo -e "${GREEN}║${NC}  User Panel:     ${BLUE}https://${SERVER_IP}:2083${NC}"
    echo -e "${GREEN}║${NC}  Admin User:     ${YELLOW}root${NC} (Linux root user)"
    echo -e "${GREEN}║${NC}  Admin Password: ${YELLOW}(your root password)${NC}"
    echo -e "${GREEN}║${NC}"
    echo -e "${GREEN}║${NC}  MySQL Root Pwd: ${YELLOW}(saved in credentials file)${NC}"
    echo -e "${GREEN}║${NC}  DB User:      ${YELLOW}${DB_USER}${NC}"
    echo -e "${GREEN}║${NC}  DB Password:  ${YELLOW}(saved in credentials file)${NC}"
    echo -e "${GREEN}║${NC}"
    echo -e "${GREEN}║${NC}  Install Dir:    ${INSTALL_DIR}"
    echo -e "${GREEN}║${NC}  PHP Version:    ${PHP_VERSION}"
    echo -e "${GREEN}║${NC}  Web Stack:      ${OPENPANEL_WEB_STACK:-nginx_phpfpm}"
    echo -e "${GREEN}║${NC}  Credentials:    /root/.openpanel-credentials"
    echo -e "${GREEN}║${NC}  Install Log:    ${LOG_FILE}"
    echo -e "${GREEN}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${RED}  IMPORTANT: Save credentials and delete /root/.openpanel-credentials${NC}"
    echo ""

    if [[ "$DO_RESTART" =~ ^[Yy]$ ]]; then
        log "Rebooting in 10 seconds... (Ctrl+C to cancel)"
        sleep 10
        reboot
    fi
}

main() {
    parse_args "$@"

    echo ""
    echo -e "${BLUE}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║            OpenPanel - Server Control Panel                  ║${NC}"
    echo -e "${BLUE}║                     Installer v0.1.0-beta                   ║${NC}"
    echo -e "${BLUE}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""

    check_root
    detect_os
    check_existing
    check_resources
    gather_config

    {
        install_repos
        install_base_packages
        install_php
        install_nginx
        install_mariadb
        install_composer
        install_nodejs
        install_dns
        install_ftp
        install_firewall
        install_redis
        install_wp_cli
        clone_project
        repair_app_permissions
        configure_env
        run_migrations
        create_admin_user
        build_auth_helper
        build_assets
        repair_app_permissions
        if [[ "${SKIP_SSL:-}" != "y" ]]; then
            generate_ssl
        elif [[ -f /etc/pki/tls/certs/openpanel.crt && -f /etc/pki/tls/private/openpanel.key ]]; then
            log "Skipping SSL generation (--skip-ssl); existing panel certificate found"
        else
            warn "--skip-ssl requested, but no panel certificate exists; generating self-signed fallback for nginx"
            generate_ssl
        fi
        configure_nginx
        if [[ "${OPENPANEL_WEB_STACK:-nginx_phpfpm}" == "nginx_varnish_apache" ]]; then
            install_httpd
            install_varnish
            configure_varnish_apache_nginx
        else
            mkdir -p /etc/openpanel
            echo "nginx_phpfpm" > /etc/openpanel/web_stack
        fi
        setup_sudoers
        setup_cron
        optimize_app
        install_mail
        install_opendkim
        install_roundcube
        install_phpmyadmin
        harden_security
        save_credentials
    } 2>&1 | tee -a "$LOG_FILE"

    print_summary
}

main "$@"
