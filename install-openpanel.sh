#!/bin/bash
set -euo pipefail

LANG=en_US.UTF-8
export LANG

GITHUB_REPO="https://github.com/WebOpenPanel/openpanel.git"
INSTALL_DIR="/usr/local/openpanel"
PHP_VERSION="8.3"
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
        read -p "Reinstall/upgrade? (y/N): " confirm
        if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
            exit 0
        fi
        log "Proceeding with reinstall/upgrade..."
    fi

    if [ -e "/usr/local/cwpsrv/" ]; then
        warn "Legacy CWP detected at /usr/local/cwpsrv/. OpenPanel will be installed alongside."
        read -p "Continue? (y/N): " confirm
        if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
            exit 0
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

    read -p "MySQL root password (leave blank to auto-generate): " MYSQL_ROOT_PASSWORD
    if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
        MYSQL_ROOT_PASSWORD=$(openssl rand -base64 16 | tr -dc A-Za-z0-9 | head -c 20)
        log "Generated MySQL root password: $MYSQL_ROOT_PASSWORD"
    fi

    DB_PASSWORD=$(openssl rand -base64 16 | tr -dc A-Za-z0-9 | head -c 20)

    read -p "Admin username [admin]: " ADMIN_USER
    ADMIN_USER="${ADMIN_USER:-admin}"

    read -p "Admin email [admin@$(hostname -f 2>/dev/null || hostname)]: " ADMIN_EMAIL
    ADMIN_EMAIL="${ADMIN_EMAIL:-admin@$(hostname -f 2>/dev/null || hostname)}"

    read -s -p "Admin password (leave blank to auto-generate): " ADMIN_PASSWORD
    echo ""
    if [ -z "$ADMIN_PASSWORD" ]; then
        ADMIN_PASSWORD=$(openssl rand -base64 12 | tr -dc A-Za-z0-9 | head -c 16)
        log "Generated admin password: $ADMIN_PASSWORD"
    fi

    read -p "Server IP (auto-detected): " SERVER_IP
    if [ -z "$SERVER_IP" ]; then
        SERVER_IP=$(curl -4 -s --connect-timeout 5 ifconfig.me 2>/dev/null || ip -4 addr show scope global | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -1)
    fi
    log "Server IP: $SERVER_IP"

    read -p "Enable SSL for panel? (Y/n): " ENABLE_SSL
    ENABLE_SSL="${ENABLE_SSL:-Y}"

    read -p "Install CSF firewall? (Y/n): " INSTALL_CSF
    INSTALL_CSF="${INSTALL_CSF:-Y}"

    read -p "Install mail server (Postfix/Dovecot)? (Y/n): " INSTALL_MAIL
    INSTALL_MAIL="${INSTALL_MAIL:-Y}"

    read -p "Restart server after install? (y/N): " DO_RESTART
    DO_RESTART="${DO_RESTART:-N}"
}

install_repos() {
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
        mysqladmin -u root password "$MYSQL_ROOT_PASSWORD" 2>&1 | tee -a "$LOG_FILE"
        log "MySQL root password set"
    else
        log "MySQL root password already set, verifying..."
        if ! mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT 1;" &>/dev/null; then
            err "Cannot connect to MySQL with provided password"
        fi
    fi

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
    dnf -y install git curl wget unzip tar 2>&1 | tee -a "$LOG_FILE"
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
        git pull origin main 2>&1 | tee -a "$LOG_FILE"
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
    step "Creating Admin User"

    cd "$INSTALL_DIR"
    php artisan tinker --execute="
        \\App\\Models\\User::updateOrCreate(
            ['username' => '${ADMIN_USER}'],
            [
                'email' => '${ADMIN_EMAIL}',
                'password' => bcrypt('${ADMIN_PASSWORD}'),
                'role' => 'admin',
                'status' => 'active',
                'ip_address' => '${SERVER_IP}',
            ]
        );
    " 2>&1 | tee -a "$LOG_FILE"

    log "Admin user '$ADMIN_USER' created"
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

install_csf() {
    if [[ ! "$INSTALL_CSF" =~ ^[Yy]$ ]]; then
        return
    fi

    step "Installing CSF Firewall"

    cd /tmp
    rm -rf csf csf.tgz
    wget -q http://static.cdn-cwp.com/files/csf.tgz 2>&1 | tee -a "$LOG_FILE"
    tar -xzf csf.tgz
    cd csf
    sh install.sh 2>&1 | tee -a "$LOG_FILE"

    sed -i 's|TESTING = "1"|TESTING = "0"|' /etc/csf/csf.conf
    sed -i "s|80,110,113,443|80,110,113,443,2087,2083,2096|" /etc/csf/csf.conf

    csf -r 2>&1 | tee -a "$LOG_FILE"
    cd /
    rm -rf /tmp/csf /tmp/csf.tgz

    log "CSF Firewall installed and configured"
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

    systemctl enable postfix dovecot
    systemctl restart postfix dovecot

    log "Mail server installed"
}

save_credentials() {
    local cred_file="/root/.openpanel-credentials"
    cat > "$cred_file" <<EOF
# OpenPanel Installation Credentials
# Generated: $(date)
# ====================================

Panel URL (Admin): https://${SERVER_IP}:2087
Panel URL (User):  https://${SERVER_IP}:2083
Admin User:     ${ADMIN_USER}
Admin Email:    ${ADMIN_EMAIL}
Admin Password: ${ADMIN_PASSWORD}

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
    echo -e "${GREEN}║${NC}  Admin User:     ${YELLOW}${ADMIN_USER}${NC}"
    echo -e "${GREEN}║${NC}  Admin Password: ${YELLOW}${ADMIN_PASSWORD}${NC}"
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
        clone_project
        configure_env
        run_migrations
        create_admin_user
        build_assets
        generate_ssl
        configure_nginx
        setup_cron
        optimize_app
        install_csf
        install_mail
        save_credentials
    } 2>&1 | tee -a "$LOG_FILE"

    print_summary
}

main "$@"
