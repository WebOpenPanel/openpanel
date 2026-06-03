#!/bin/bash
set -euo pipefail

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
        warn "Legacy CWP detected at /usr/local/cwpsrv/. OpenPanel will be installed alongside."
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

    if [[ "${NON_INTERACTIVE:-}" == "y" ]]; then
        MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-$(openssl rand -base64 16 | tr -dc A-Za-z0-9 | head -c 20)}"
        DB_PASSWORD=$(openssl rand -base64 16 | tr -dc A-Za-z0-9 | head -c 20)
        ROOT_PASSWORD="${ROOT_PASSWORD:-}"
        SERVER_IP="${SERVER_IP:-$(curl -4 -s --connect-timeout 5 ifconfig.me 2>/dev/null || ip -4 addr show scope global | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -1)}"
        ENABLE_SSL="${ENABLE_SSL:-Y}"
        INSTALL_MAIL="${INSTALL_MAIL:-Y}"
        DO_RESTART="${DO_RESTART:-N}"
        log "Non-interactive mode: using environment variables / defaults"
    else
        read -p "MySQL root password (leave blank to auto-generate): " MYSQL_ROOT_PASSWORD
        if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
            MYSQL_ROOT_PASSWORD=$(openssl rand -base64 16 | tr -dc A-Za-z0-9 | head -c 20)
            log "Generated MySQL root password: $MYSQL_ROOT_PASSWORD"
        fi

        DB_PASSWORD=$(openssl rand -base64 16 | tr -dc A-Za-z0-9 | head -c 20)

        read -p "Set root password for admin panel (leave blank to keep current): " ROOT_PASSWORD
        echo ""

        read -p "Server IP (auto-detected): " SERVER_IP
        if [ -z "$SERVER_IP" ]; then
            SERVER_IP=$(curl -4 -s --connect-timeout 5 ifconfig.me 2>/dev/null || ip -4 addr show scope global | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -1)
        fi

        read -p "Enable SSL for panel? (Y/n): " ENABLE_SSL
        ENABLE_SSL="${ENABLE_SSL:-Y}"

        read -p "Install mail server (Postfix/Dovecot)? (Y/n): " INSTALL_MAIL
        INSTALL_MAIL="${INSTALL_MAIL:-Y}"

        read -p "Restart server after install? (y/N): " DO_RESTART
        DO_RESTART="${DO_RESTART:-N}"
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

    if mysqladmin -u root status &>/dev/null 2>&1; then
        # Switch from unix_socket to password auth (AlmaLinux 9 default)
        mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password;" 2>&1 | tee -a "$LOG_FILE"
        mysqladmin -u root password "$MYSQL_ROOT_PASSWORD" 2>&1 | tee -a "$LOG_FILE"
        log "MySQL root password set"
    else
        log "MySQL root password already set, verifying..."
        if ! mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT 1;" &>/dev/null; then
            err "Cannot connect to MySQL with provided password"
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
        log "Existing installation found, backing up .env..."
        cp "$INSTALL_DIR/.env" "$INSTALL_DIR/.env.bak.$(date +%s)" 2>/dev/null || true
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

    cat > "$INSTALL_DIR/.env" <<EOF
APP_NAME="OpenPanel"
APP_ENV=production
APP_KEY=
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
MAIL_FROM_ADDRESS="noreply@$(hostname -f 2>/dev/null || hostname)"
MAIL_FROM_NAME="OpenPanel"
EOF

    cd "$INSTALL_DIR"
    php artisan key:generate --force 2>&1 | tee -a "$LOG_FILE"

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
        npm install --ignore-scripts 2>&1 | tee -a "$LOG_FILE"
        npm run build 2>&1 | tee -a "$LOG_FILE"
        log "Assets built"
    else
        warn "npm not available, using CDN assets"
    fi
}

configure_nginx() {
    step "Configuring Nginx"

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

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
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

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
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

    systemctl enable php-fpm
    systemctl restart php-fpm

    nginx -t 2>&1 | tee -a "$LOG_FILE" || err "Nginx configuration test failed"

    systemctl enable nginx
    systemctl restart nginx

    log "Nginx configured on port 2087"
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
    php artisan storage:link 2>&1 | tee -a "$LOG_FILE"

    chown -R nginx:nginx "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache"
    chmod -R 775 "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache"

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

install_dns() {
    step "Installing DNS Server (BIND)"

    dnf -y install bind bind-utils 2>&1 | tee -a "$LOG_FILE"

    if [ -f /etc/named.conf ]; then
        cp /etc/named.conf /etc/named.conf.bak.$(date +%s) 2>/dev/null || true
    fi

    # Allow queries from localhost and local networks
    if ! grep -q "allow-query.*localhost" /etc/named.conf 2>/dev/null; then
        sed -i 's/listen-on port 53.*/listen-on port 53 { 127.0.0.1; any; };/' /etc/named.conf 2>/dev/null || true
        sed -i 's/listen-on-v6.*/listen-on-v6 port 53 { ::1; any; };/' /etc/named.conf 2>/dev/null || true
        sed -i 's/allow-query.*/allow-query { localhost; any; };/' /etc/named.conf 2>/dev/null || true
    fi

    systemctl enable named
    systemctl restart named 2>&1 | tee -a "$LOG_FILE" || warn "Named failed to start"

    log "BIND DNS installed"
}

install_ftp() {
    step "Installing FTP Server (Pure-FTPd)"

    dnf -y install pure-ftpd 2>&1 | tee -a "$LOG_FILE"

    if [ -f /etc/pure-ftpd/pure-ftpd.conf ]; then
        cp /etc/pure-ftpd/pure-ftpd.conf /etc/pure-ftpd/pure-ftpd.conf.bak.$(date +%s) 2>/dev/null || true
        sed -i 's/^# PureDB/PureDB/' /etc/pure-ftpd/pure-ftpd.conf 2>/dev/null || true
        sed -i 's|PureDB.*@sysconfigdir@.*|PureDB /etc/pure-ftpd/pureftpd.pdb|' /etc/pure-ftpd/pure-ftpd.conf 2>/dev/null || true
        sed -i 's|^PureDB.*pureftpd.pdb$|PureDB /etc/pure-ftpd/pureftpd.pdb|' /etc/pure-ftpd/pure-ftpd.conf 2>/dev/null || true
        sed -i 's/^# NoAnonymous/NoAnonymous/' /etc/pure-ftpd/pure-ftpd.conf 2>/dev/null || true
    fi

    # Create required directories
    mkdir -p /etc/pure-ftpd
    echo "yes" > /etc/pure-ftpd/no_unix_privs 2>/dev/null || true

    systemctl enable pure-ftpd
    systemctl restart pure-ftpd 2>&1 | tee -a "$LOG_FILE" || warn "Pure-FTPd failed to start"

    log "Pure-FTPd installed"
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
            2082/tcp  # User panel HTTP
            2083/tcp  # User panel HTTPS
            2086/tcp  # Admin panel HTTP
            2087/tcp  # Admin panel HTTPS
            2095/tcp  # Webmail HTTP
            2096/tcp  # Webmail HTTPS
            30000:31000/tcp  # FTP passive
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
apache ALL=(root) NOPASSWD: /usr/sbin/useradd, /usr/sbin/userdel, /usr/sbin/usermod
apache ALL=(root) NOPASSWD: /usr/bin/passwd, /usr/sbin/chpasswd
apache ALL=(root) NOPASSWD: /usr/bin/chage
apache ALL=(root) NOPASSWD: /usr/bin/systemctl restart php-fpm, /usr/bin/systemctl reload php-fpm
apache ALL=(root) NOPASSWD: /usr/bin/systemctl restart nginx, /usr/bin/systemctl reload nginx
apache ALL=(root) NOPASSWD: /usr/bin/systemctl restart httpd, /usr/bin/systemctl reload httpd
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
Admin Password: (your root password — use 'passwd root' to change)

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
    echo -e "${GREEN}║${NC}  MySQL Root Pwd: ${YELLOW}${MYSQL_ROOT_PASSWORD}${NC}"
    echo -e "${GREEN}║${NC}  DB User:      ${YELLOW}${DB_USER}${NC}"
    echo -e "${GREEN}║${NC}  DB Password:  ${YELLOW}${DB_PASSWORD}${NC}"
    echo -e "${GREEN}║${NC}"
    echo -e "${GREEN}║${NC}  Install Dir:    ${INSTALL_DIR}"
    echo -e "${GREEN}║${NC}  PHP Version:    ${PHP_VERSION}"
    echo -e "${GREEN}║${NC}  Credentials:    /root/.openpanel-credentials"
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
    echo ""
    echo -e "${BLUE}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║            OpenPanel - Server Control Panel                  ║${NC}"
    echo -e "${BLUE}║                     Installer v1.0.0                        ║${NC}"
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
        configure_env
        run_migrations
        create_admin_user
        build_auth_helper
        build_assets
        generate_ssl
        configure_nginx
        setup_sudoers
        setup_cron
        optimize_app
        install_mail
        save_credentials
    } 2>&1 | tee -a "$LOG_FILE"

    print_summary
}

main "$@"
