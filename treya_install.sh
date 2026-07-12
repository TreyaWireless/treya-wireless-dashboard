#!/bin/bash
# ============================================================
#  Treya Wireless v7.0.28 - RHEL 10 / RHEL 9 Compatibility Installer
#  OS: Red Hat Enterprise Linux 10/9, AlmaLinux 9, Rocky Linux 9
#  Run as: sudo bash treya_install.sh
# ============================================================

set -e

# ── CONFIG ─────────────────────────────────────────────────
DB_NAME="treya"
DB_USER="treya"
DB_PASS="TreyaPass@123"
TIMEZONE="Asia/Kolkata"
RELEASE_URL="https://github.com/TreyaWireless/treya-wireless-rpms/releases/download/Treya_Wireless-v7.0.28_RPMs"

# Web Login Credentials
WEB_USER="treya"
WEB_PASS="redhat"
# ───────────────────────────────────────────────────────────

echo "========================================"
echo " Treya Wireless v7.0.28 Installation"
echo " (RHEL 10 / RHEL 9 Compatible)"
echo "========================================"

# Remove old invalid local repo file if present
rm -f /etc/yum.repos.d/treya-wireless.repo /etc/yum.repos.d/treya*.repo 2>/dev/null || true

# ── STEP 1: Enable EPEL Repository ─────────────────────────
echo ""
echo "[STEP 1] Setting up EPEL Repository..."
OS_VER=$(grep -oP 'VERSION_ID="\K[^"]+' /etc/os-release | cut -d. -f1 2>/dev/null || echo "9")

if [ "$OS_VER" = "10" ]; then
    echo "Detected RHEL 10 Environment."
    dnf install -y https://dl.fedoraproject.org/pub/epel/epel-release-latest-10.noarch.rpm 2>/dev/null || \
    dnf install -y https://dl.fedoraproject.org/pub/epel/epel-release-latest-9.noarch.rpm 2>/dev/null || true
else
    dnf install -y https://dl.fedoraproject.org/pub/epel/epel-release-latest-9.noarch.rpm 2>/dev/null || true
fi

# ── STEP 2: Install Core Web & Database Dependencies ────────────────
echo ""
echo "[STEP 2] Installing Core Web & Database Dependencies..."
dnf install -y mariadb-server nginx php-fpm php-mysqlnd php-gd php-mbstring php-bcmath php-xml php-json php-ldaps php-sockets 2>/dev/null || \
dnf install -y mariadb-server nginx php-fpm php-mysqlnd 2>/dev/null || true

systemctl enable --now mariadb 2>/dev/null || true

# ── STEP 3: Install Treya Wireless RPMs Direct from GitHub ──
echo ""
echo "[STEP 3] Downloading & Installing Treya Wireless RPMs..."

mkdir -p /tmp/treya_rpms_install
cd /tmp/treya_rpms_install
rm -rf /tmp/treya_rpms_install/*

echo "Downloading package files..."
curl -fsSL ${RELEASE_URL}/treya-wireless-web-deps-7.0.28-release1.el9.noarch.rpm -o treya-web-deps.rpm
curl -fsSL ${RELEASE_URL}/treya-wireless-web-7.0.28-release1.el9.noarch.rpm -o treya-web.rpm
curl -fsSL ${RELEASE_URL}/treya-wireless-web-mysql-7.0.28-release1.el9.noarch.rpm -o treya-web-mysql.rpm
curl -fsSL ${RELEASE_URL}/treya-wireless-nginx-conf-7.0.28-release1.el9.noarch.rpm -o treya-nginx.rpm
curl -fsSL ${RELEASE_URL}/treya-wireless-sql-scripts-7.0.28-release1.el9.noarch.rpm -o treya-sql.rpm
curl -fsSL ${RELEASE_URL}/treya-wireless-server-mysql-7.0.28-release1.el9.x86_64.rpm -o treya-server.rpm
curl -fsSL ${RELEASE_URL}/treya-wireless-agent-7.0.28-release1.el9.x86_64.rpm -o treya-agent.rpm

echo "Installing Treya Wireless packages..."
dnf install --nogpgcheck -y treya-*.rpm || \
rpm -Uvh --nodeps treya-*.rpm || \
dnf install --nogpgcheck -y --allowerasing treya-*.rpm

cd /

# ── STEP 4: System User, Log, and PID Directory Setup ────────
echo ""
echo "[STEP 4] Setting up treya_wireless system user and folders..."
# System users and groups
groupadd --system treya_wireless 2>/dev/null || true
useradd --system -g treya_wireless -d /var/lib/treya-wireless -s /sbin/nologin -c "Treya Wireless Daemon" treya_wireless 2>/dev/null || true

# Add web server and zabbix users to treya_wireless group so they can access the web/ config folder and cache
usermod -a -G treya_wireless nginx 2>/dev/null || true
usermod -a -G treya_wireless apache 2>/dev/null || true
usermod -a -G treya_wireless zabbix 2>/dev/null || true

# Create logs and run directories
mkdir -p /var/log/treya-wireless /var/run/treya-wireless
chown -R treya_wireless:treya_wireless /var/log/treya-wireless /var/run/treya-wireless
chmod 775 /var/log/treya-wireless /var/run/treya-wireless

# Setup shared cache directories with 777 permissions
mkdir -p /var/cache/treya-wireless/locks
chown -R treya_wireless:treya_wireless /var/cache/treya-wireless 2>/dev/null || true
chmod -R 777 /var/cache/treya-wireless 2>/dev/null || true

# Set MariaDB database setup
echo "Setting up MariaDB Database and User..."
mysql -uroot <<EOF 2>/dev/null || true
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
SET GLOBAL log_bin_trust_function_creators = 1;
FLUSH PRIVILEGES;
EOF

# ── STEP 5: Database Schema Import & Web Login Update ─────
echo ""
echo "[STEP 5] Importing Database Schema & Setting Web Credentials..."
if [ -f /usr/share/treya-wireless-sql-scripts/mysql/server.sql.gz ]; then
    zcat /usr/share/treya-wireless-sql-scripts/mysql/server.sql.gz | \
        mysql --default-character-set=utf8mb4 -u${DB_USER} -p${DB_PASS} ${DB_NAME} 2>/dev/null || true
elif [ -f /usr/share/zabbix-sql-scripts/mysql/server.sql.gz ]; then
    zcat /usr/share/zabbix-sql-scripts/mysql/server.sql.gz | \
        mysql --default-character-set=utf8mb4 -u${DB_USER} -p${DB_PASS} ${DB_NAME} 2>/dev/null || true
fi

mysql -u${DB_USER} -p${DB_PASS} ${DB_NAME} -e "UPDATE users SET username='${WEB_USER}', passwd='\$2b\$10\$4JXmvJmGQ/V.OKADfUpXFOMFKHz8Ed8EZsy5hBGt2aqJ3OZkWmAJK' WHERE userid=1 OR username='Admin';" 2>/dev/null || true

mysql -u${DB_USER} -p${DB_PASS} ${DB_NAME} -e "UPDATE host_inventory SET location_lat='19.2505', location_lon='73.3987' WHERE hostid=10084;" 2>/dev/null || true

# Optimize history text/log columns to prevent truncation of large JSON payloads
mysql -u${DB_USER} -p${DB_PASS} ${DB_NAME} -e "ALTER TABLE history_text MODIFY value MEDIUMTEXT NOT NULL; ALTER TABLE history_log MODIFY value MEDIUMTEXT NOT NULL;" 2>/dev/null || true

mysql -uroot -e "SET GLOBAL log_bin_trust_function_creators = 0;" 2>/dev/null || true

# ── STEP 6: Pre-generate Web Configuration (No Setup Wizard Needed) ─
echo ""
echo "[STEP 6] Generating Web Application Configuration..."
mkdir -p /etc/treya/web /etc/treya-wireless/web

cat <<EOF > /etc/treya/web/treya.conf.php
<?php
// Treya Wireless Web GUI configuration file.
\$DB['TYPE']     = 'MYSQL';
\$DB['SERVER']   = 'localhost';
\$DB['PORT']     = '0';
\$DB['DATABASE'] = '${DB_NAME}';
\$DB['USER']     = '${DB_USER}';
\$DB['PASSWORD'] = '${DB_PASS}';
\$DB['SCHEMA']   = '';

\$ZBX_SERVER      = 'localhost';
\$ZBX_SERVER_PORT = '10051';
\$ZBX_SERVER_NAME = 'Treya Wireless';

\$IMAGE_FORMAT_DEFAULT = IMAGE_FORMAT_PNG;
EOF

cp /etc/treya/web/treya.conf.php /etc/treya-wireless/web/treya.conf.php 2>/dev/null || true

# Set correct ownerships so both server daemon and web app can traverse and read
chown -R treya_wireless:treya_wireless /etc/treya /etc/treya-wireless 2>/dev/null || true
chmod 750 /etc/treya /etc/treya-wireless 2>/dev/null || true

chown -R nginx:nginx /etc/treya/web /etc/treya-wireless/web 2>/dev/null || \
chown -R apache:apache /etc/treya/web /etc/treya-wireless/web 2>/dev/null || true
chmod 755 /etc/treya/web /etc/treya-wireless/web 2>/dev/null || true
chmod 644 /etc/treya/web/treya.conf.php /etc/treya-wireless/web/treya.conf.php 2>/dev/null || true

# Symlink it to the conf directories so the web app can read it
ln -sf /etc/treya/web/treya.conf.php /usr/share/zabbix/conf/treya.conf.php 2>/dev/null || true
ln -sf /etc/treya-wireless/web/treya.conf.php /usr/share/treya-wireless/conf/treya.conf.php 2>/dev/null || true

# ── STEP 7: Server Config - DB credentials and paths ────────────────────
echo ""
echo "[STEP 7] Configuring Treya Server settings..."
CONF_FILE=""
[ -f /etc/treya-wireless/treya_server.conf ] && CONF_FILE="/etc/treya-wireless/treya_server.conf"
[ -f /etc/zabbix/zabbix_server.conf ] && CONF_FILE="/etc/zabbix/zabbix_server.conf"

if [ -n "$CONF_FILE" ]; then
    # Configure correct database parameters
    sed -i "s/^DBName=.*/DBName=${DB_NAME}/" "$CONF_FILE"
    sed -i "s/^DBUser=.*/DBUser=${DB_USER}/" "$CONF_FILE"
    sed -i "s/^# DBPassword=/DBPassword=/" "$CONF_FILE"
    sed -i "s/^DBPassword=.*/DBPassword=${DB_PASS}/" "$CONF_FILE"
    
    # Configure correct PID, LogFile, and ExternalScripts path permissions
    sed -i "s|^PidFile=.*|PidFile=/var/run/treya-wireless/treya_server.pid|" "$CONF_FILE"
    sed -i "s|^LogFile=.*|LogFile=/var/log/treya-wireless/treya_server.log|" "$CONF_FILE"
    sed -i "s|^# ExternalScripts=.*|ExternalScripts=/usr/lib/treya-wireless/externalscripts|" "$CONF_FILE"
    sed -i "s|^ExternalScripts=.*|ExternalScripts=/usr/lib/treya-wireless/externalscripts|" "$CONF_FILE"
    sed -i "s|^# Timeout=.*|Timeout=30|" "$CONF_FILE"
    sed -i "s|^Timeout=.*|Timeout=30|" "$CONF_FILE"
    
    # Ensure ownership is correct
    chown treya_wireless:treya_wireless "$CONF_FILE" 2>/dev/null || true
    chmod 640 "$CONF_FILE" 2>/dev/null || true
fi

# ── STEP 8: PHP Config - Timezone ──────────────────────────
echo ""
echo "[STEP 8] Configuring PHP-FPM Timezone (${TIMEZONE})..."
PHP_CONF=""
[ -f /etc/php-fpm.d/treya-wireless.conf ] && PHP_CONF="/etc/php-fpm.d/treya-wireless.conf"
[ -f /etc/php-fpm.d/zabbix.conf ] && PHP_CONF="/etc/php-fpm.d/zabbix.conf"

if [ -n "$PHP_CONF" ]; then
    sed -i "s|; php_value\[date.timezone\].*|php_value[date.timezone] = ${TIMEZONE}|" "$PHP_CONF"
    grep -q "date.timezone" "$PHP_CONF" || echo "php_value[date.timezone] = ${TIMEZONE}" >> "$PHP_CONF"
fi

# ── STEP 9: Fix Permissions ────────────────────────────────
echo ""
echo "[STEP 9] Fixing Web Directory Permissions..."
chown -R nginx:nginx /usr/share/zabbix /usr/share/treya-wireless 2>/dev/null || \
chown -R apache:apache /usr/share/zabbix /usr/share/treya-wireless 2>/dev/null || true

# Create standard externalscripts fallback symlink
mkdir -p /usr/lib/zabbix
ln -sf /usr/lib/treya-wireless/externalscripts /usr/lib/zabbix/externalscripts 2>/dev/null || true

# ── STEP 10: Start Services ────────────────────────────────
echo ""
echo "[STEP 10] Enabling and Starting Services..."
systemctl enable --now php-fpm 2>/dev/null || true
systemctl restart php-fpm 2>/dev/null || true

systemctl enable --now nginx 2>/dev/null || true
systemctl restart nginx 2>/dev/null || true

systemctl enable --now treya-wireless-server 2>/dev/null || systemctl enable --now zabbix-server 2>/dev/null || true
systemctl restart treya-wireless-server 2>/dev/null || systemctl restart zabbix-server 2>/dev/null || true

systemctl enable --now treya-wireless-agent 2>/dev/null || systemctl enable --now zabbix-agent 2>/dev/null || true

# ── STEP 10b: Import Custom Zabbix Templates ─────────────────
echo ""
echo "[STEP 10b] Importing Custom Zabbix Templates..."
if [ -f /home/ec2-user/treya-wireless-dashboard/import_template.py ]; then
    python3 /home/ec2-user/treya-wireless-dashboard/import_template.py 2>/dev/null || true
elif [ -f ./import_template.py ]; then
    python3 ./import_template.py 2>/dev/null || true
fi

# ── STEP 11: Firewall & SELinux ────────────────────────────
echo ""
echo "[STEP 11] Configuring Firewall & SELinux..."
if command -v firewall-cmd &>/dev/null; then
    firewall-cmd --permanent --add-port=80/tcp 2>/dev/null || true
    firewall-cmd --permanent --add-port=443/tcp 2>/dev/null || true
    firewall-cmd --permanent --add-port=10050/tcp 2>/dev/null || true
    firewall-cmd --permanent --add-port=10051/tcp 2>/dev/null || true
    firewall-cmd --reload 2>/dev/null || true
fi

# Apply SELinux contexts
if command -v semanage &>/dev/null; then
    # Server Logs
    semanage fcontext -a -t zabbix_log_t "/var/log/treya-wireless(/.*)?" 2>/dev/null || true
    restorecon -R -v /var/log/treya-wireless 2>/dev/null || true
    
    # Server PID File
    semanage fcontext -a -t zabbix_var_run_t "/var/run/treya-wireless(/.*)?" 2>/dev/null || true
    restorecon -R -v /var/run/treya-wireless 2>/dev/null || true
    
    # Server Config
    semanage fcontext -a -t zabbix_conf_t "/etc/treya-wireless(/.*)?" 2>/dev/null || true
    restorecon -R -v /etc/treya-wireless 2>/dev/null || true
    
    # Web Config files
    semanage fcontext -a -t httpd_sys_content_t "/etc/treya-wireless/web(/.*)?" 2>/dev/null || true
    restorecon -R -v /etc/treya-wireless/web 2>/dev/null || true
    
    # Web UI files RHEL security context
    restorecon -R -v /usr/share/treya-wireless 2>/dev/null || true
    
    # Network connections boolean
    setsebool -P httpd_can_network_connect 1 2>/dev/null || true
    setsebool -P httpd_can_connect_zabbix 1 2>/dev/null || true
fi

# ── STEP 12: Python Packages ───────────────────────────────
echo ""
echo "[STEP 12] Setting up Python dependencies..."
dnf install -y python3-pip python3-requests 2>/dev/null || true
pip3 install requests 2>/dev/null || true

# Install route sync script if available
if [ -f ./treya_route_sync.py ]; then
    cp ./treya_route_sync.py /usr/local/bin/treya_route_sync.py 2>/dev/null || true
    chmod +x /usr/local/bin/treya_route_sync.py 2>/dev/null || true
    (crontab -l 2>/dev/null | grep -v 'treya_route_sync.py'; echo '* * * * * /usr/bin/python3 /usr/local/bin/treya_route_sync.py >/dev/null 2>&1') | crontab -
    echo "treya_route_sync.py installed and cron job registered."
fi

# Clean temp directory
rm -rf /tmp/treya_rpms_install

# ── IP Detection ───────────────────────────────────────────
PRIVATE_IP=$(hostname -I | awk '{print $1}')
PUBLIC_IP=$(curl -s --max-time 5 http://checkip.amazonaws.com/ 2>/dev/null || \
            curl -s --max-time 5 https://api.ipify.org 2>/dev/null || \
            echo "Unknown")

# ── DONE ───────────────────────────────────────────────────
echo ""
echo "========================================"
echo " Treya Wireless Installation Complete!"
echo "========================================"
echo ""
echo "  Access URLs:"
echo "  Local  (LAN)     : http://${PRIVATE_IP}/"
echo "  Public (Internet): http://${PUBLIC_IP}/"
echo ""
echo "  Web Login Credentials:"
echo "  Username : ${WEB_USER}"
echo "  Password : ${WEB_PASS}"
echo ""
echo "  AWS EC2 Security Note:"
echo "  Make sure Security Group Inbound Rule allows Port 80 (HTTP)!"
echo "========================================"
