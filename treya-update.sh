#!/bin/bash
# ============================================================
#  EC2 Treya Wireless Update Script
#  GitHub वरून latest UI files pull करून EC2 वर update करतो
#  Run as: sudo bash /opt/treya-update.sh
# ============================================================

GITHUB_REPO="https://raw.githubusercontent.com/TreyaWireless/treya-wireless-dashboard/main"
WEB_DIR="/usr/share/zabbix"
TMP_DIR="/tmp/treya_update"

echo "================================"
echo " Treya Wireless Update"
echo "================================"

# 1. Temp directory
rm -rf $TMP_DIR
mkdir -p $TMP_DIR

# 2. GitHub वरून latest code pull करा
echo "Pulling latest code from GitHub..."
dnf install -y git 2>/dev/null | tail -1
git clone https://github.com/TreyaWireless/treya-wireless-dashboard.git $TMP_DIR/repo

# 3. UI files copy करा
echo "Copying updated files to web directory..."
for DIR in "/usr/share/zabbix" "/usr/share/treya-wireless"; do
    if [ -d "$DIR" ]; then
        echo "Updating directory: $DIR"
        rsync -av --delete \
            $TMP_DIR/repo/ui/ \
            $DIR/
        
        # Ensure configuration directory and file symlinks exist
        mkdir -p /etc/treya/web /etc/treya-wireless/web
        if [ "$DIR" = "/usr/share/zabbix" ]; then
            ln -sf /etc/treya/web/treya.conf.php $DIR/conf/treya.conf.php 2>/dev/null || true
        else
            ln -sf /etc/treya-wireless/web/treya.conf.php $DIR/conf/treya.conf.php 2>/dev/null || true
        fi

        # Permissions fix
        chown -R nginx:nginx $DIR 2>/dev/null || \
        chown -R apache:apache $DIR 2>/dev/null || true
        find $DIR -type f -exec chmod 644 {} \; 2>/dev/null || true
        find $DIR -type d -exec chmod 755 {} \; 2>/dev/null || true
    fi
done

# 4. omada_monitor.py update करा (असेल तर)
if [ -f "$TMP_DIR/repo/omada_monitor.py" ]; then
    cp $TMP_DIR/repo/omada_monitor.py /usr/lib/zabbix/externalscripts/omada_monitor.py 2>/dev/null || true
    cp $TMP_DIR/repo/omada_monitor.py /usr/lib/treya-wireless/externalscripts/omada_monitor.py 2>/dev/null || true
    chmod +x /usr/lib/zabbix/externalscripts/omada_monitor.py 2>/dev/null || true
    chmod +x /usr/lib/treya-wireless/externalscripts/omada_monitor.py 2>/dev/null || true
    echo "omada_monitor.py updated."
fi

# Ensure conf files are readable
chmod 644 /etc/treya/web/treya.conf.php /etc/treya-wireless/web/treya.conf.php 2>/dev/null || true
chown -R nginx:nginx /etc/treya /etc/treya-wireless 2>/dev/null || \
chown -R apache:apache /etc/treya /etc/treya-wireless 2>/dev/null || true

# 6. PHP Cache clear
php -r "opcache_reset();" 2>/dev/null || true

# 7. Services restart
systemctl restart php-fpm
systemctl restart nginx

# 8. Cleanup
rm -rf $TMP_DIR

echo ""
echo "Update Complete!"
echo "Browser cache clear करा (Ctrl+Shift+R)"
echo "================================"
