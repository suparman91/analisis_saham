# Manual Deployment: Analisis Saham di Linux PVS (Ubuntu/CentOS)

## Pre-Deployment Checklist

- [ ] PVS sudah aktif dengan OS Linux (Ubuntu 20.04 LTS atau CentOS 7+)
- [ ] Akses SSH ke server tersedia
- [ ] Domain sudah pointing ke IP server
- [ ] SSL certificate ready (Let's Encrypt free)

---

## PHASE 1: SYSTEM SETUP (15-20 menit)

### 1. Update System
```bash
sudo apt update && sudo apt upgrade -y
# atau untuk CentOS:
# sudo yum update -y
```

### 2. Install Dependencies
```bash
sudo apt install -y \
  apache2 \
  php8.1 \
  php8.1-cli \
  php8.1-mysql \
  php8.1-curl \
  php8.1-gd \
  php8.1-mbstring \
  php8.1-xml \
  mysql-server \
  git \
  curl \
  wget \
  htop

# Enable Apache modules
sudo a2enmod rewrite
sudo a2enmod php8.1
sudo a2enmod ssl
sudo systemctl restart apache2
```

### 3. Verify PHP & MySQL
```bash
php -v
mysql --version
```

---

## PHASE 2: DATABASE SETUP (10 menit)

### 1. Secure MySQL
```bash
sudo mysql_secure_installation
# Jawab: Y ke semua pertanyaan (hapus root login tanpa password, hapus test db)
```

### 2. Login MySQL & Buat Database
```bash
sudo mysql -u root -p
# Masukkan password yang sudah di-set di langkah sebelumnya
```

### 3. Di MySQL Prompt, Jalankan:
```sql
CREATE DATABASE analisis_saham CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'saham_user'@'localhost' IDENTIFIED BY 'Aman123!@#';
GRANT ALL PRIVILEGES ON analisis_saham.* TO 'saham_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 4. Import Database Structure (jika ada dump file)
```bash
# Jika sudah export dari local, copy ke server dulu:
scp dump.sql user@your.server.ip:/home/user/dump.sql

# Kemudian import:
mysql -u saham_user -p analisis_saham < dump.sql
```

---

## PHASE 3: APPLICATION SETUP (15 menit)

### 1. Clone Repository ke Web Directory
```bash
cd /var/www
sudo git clone https://github.com/suparman91/analisis_saham.git
sudo chown -R www-data:www-data analisis_saham
sudo chmod -R 755 analisis_saham
cd analisis_saham
```

### 2. Setup db.php Connection
```bash
sudo nano db.php
```

Edit atau buat file dengan isi:
```php
<?php
$db_host = 'localhost';
$db_user = 'saham_user';
$db_pass = 'Aman123!@#';  // Ganti dengan password yang sudah di-set
$db_name = 'analisis_saham';

// PDO Connection (prioritas utama)
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("PDO Connection Error: " . $e->getMessage());
}

// Fallback mysqli
$mysqli = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
if (!$mysqli) {
    die("MySQLi Connection Error: " . mysqli_connect_error());
}
mysqli_set_charset($mysqli, "utf8mb4");

function db_connect() {
    global $pdo;
    return $pdo;
}
?>
```

### 3. Create Required Directories
```bash
sudo mkdir -p /var/www/analisis_saham/uploads
sudo mkdir -p /var/www/analisis_saham/logs
sudo mkdir -p /var/www/analisis_saham/cache
sudo chown -R www-data:www-data /var/www/analisis_saham/{uploads,logs,cache}
sudo chmod -R 775 /var/www/analisis_saham/{uploads,logs,cache}
```

---

## PHASE 4: APACHE VHOST SETUP (10 menit)

### 1. Create Apache VirtualHost
```bash
sudo nano /etc/apache2/sites-available/analisis-saham.conf
```

Paste isi berikut (ganti `yourdomain.com`):
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    ServerAdmin admin@yourdomain.com
    
    DocumentRoot /var/www/analisis_saham
    
    <Directory /var/www/analisis_saham>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteBase /
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^(.*)$ app.php?page=$1 [QSA,L]
        </IfModule>
    </Directory>
    
    # Error & Access Logs
    ErrorLog /var/log/apache2/analisis-saham-error.log
    CustomLog /var/log/apache2/analisis-saham-access.log combined
    
    # Redirect HTTP ke HTTPS (setelah SSL setup)
    # Redirect permanent / https://yourdomain.com/
</VirtualHost>
```

### 2. Enable VirtualHost & Disable Default
```bash
sudo a2ensite analisis-saham.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest
# Harus output: "Syntax OK"

sudo systemctl restart apache2
```

---

## PHASE 5: SSL CERTIFICATE (Let's Encrypt) (10 menit)

### 1. Install Certbot
```bash
sudo apt install -y certbot python3-certbot-apache
```

### 2. Generate SSL Certificate
```bash
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com --email admin@yourdomain.com --agree-tos --non-interactive
```

### 3. Auto-Renew Setup
```bash
sudo certbot renew --dry-run
# Jika OK, cron sudah auto-setup

# Verify cron:
sudo systemctl status certbot.timer
```

### 4. Edit VirtualHost untuk HTTPS redirect
```bash
sudo nano /etc/apache2/sites-available/analisis-saham.conf
```

Uncomment baris Redirect di port 80, dan tambah section port 443:
```apache
# Port 80 redirect ke HTTPS
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    Redirect permanent / https://yourdomain.com/
</VirtualHost>

# Port 443 - HTTPS (diupdate otomatis oleh certbot)
<VirtualHost *:443>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /var/www/analisis_saham
    
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/yourdomain.com/privkey.pem
    
    <Directory /var/www/analisis_saham>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog /var/log/apache2/analisis-saham-error.log
    CustomLog /var/log/apache2/analisis-saham-access.log combined
</VirtualHost>
```

### 5. Restart Apache
```bash
sudo apache2ctl configtest
sudo systemctl restart apache2
```

---

## PHASE 6: PHP OPTIMIZATION (5 menit)

### 1. Edit PHP Config
```bash
sudo nano /etc/php/8.1/apache2/php.ini
```

Ubah nilai:
```ini
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 50M
post_max_size = 50M
display_errors = Off
log_errors = On
error_log = /var/log/php_error.log
```

### 2. Restart Apache
```bash
sudo systemctl restart apache2
```

---

## PHASE 7: SECURITY HARDENING (10 menit)

### 1. Setup Firewall
```bash
# Ubuntu UFW
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# CentOS FirewallD
# sudo firewall-cmd --permanent --add-service=http
# sudo firewall-cmd --permanent --add-service=https
# sudo firewall-cmd --reload
```

### 2. Restrict Sensitive Files
```bash
sudo nano /etc/apache2/sites-available/analisis-saham.conf
```

Tambah di dalam VirtualHost:
```apache
# Deny access to config files
<FilesMatch "\.(env|json|txt|sql)$">
    Deny from all
</FilesMatch>

<DirectoryMatch "^/var/www/analisis_saham/(cache|logs|uploads)/.*">
    AddType text/plain .php
    php_flag engine off
</DirectoryMatch>
```

### 3. Setup .htaccess Security
```bash
sudo nano /var/www/analisis_saham/.htaccess
```

Isi:
```apache
# Disable directory listing
Options -Indexes

# Disable PHP execution in uploads folder
<Directory uploads>
    php_flag engine off
    AddType text/plain .php .phtml .php3 .php4 .php5 .phps
</Directory>

# Protect sensitive files
<FilesMatch "\.(env|sql|txt|json|conf)$">
    Deny from all
</FilesMatch>
```

---

## PHASE 8: TESTING & VERIFICATION (10 menit)

### 1. Test Database Connection
```bash
php -r "
require '/var/www/analisis_saham/db.php';
echo 'Database connected successfully!';
"
```

### 2. Check Application
```bash
# Via browser:
# https://yourdomain.com/

# Via CLI:
curl -I https://yourdomain.com/
# Harus response 200 OK
```

### 3. Monitor Logs
```bash
# Real-time monitoring
tail -f /var/log/apache2/analisis-saham-error.log
tail -f /var/log/apache2/analisis-saham-access.log

# PHP errors
tail -f /var/log/php_error.log
```

### 4. Performance Check
```bash
# CPU & Memory
htop

# Disk usage
df -h

# MySQL status
sudo systemctl status mysql
mysql -u saham_user -p -e "SELECT COUNT(*) FROM analisis_saham.prices;"
```

---

## PHASE 9: CRON JOBS SETUP (10 menit)

### 1. Edit Crontab
```bash
sudo nano /etc/cron.d/analisis-saham
```

Tambah jobs:
```cron
# Daily EOD price update (17:00 WIB = 10:00 UTC)
0 10 * * 1-5 www-data /usr/bin/php /var/www/analisis_saham/fetch_data.php >> /var/log/analisis-saham-cron.log 2>&1

# Scan history cleanup (00:00 WIB = 17:00 UTC kemarin)
0 17 * * * www-data /usr/bin/php /var/www/analisis_saham/cleanup_scan_history.php >> /var/log/analisis-saham-cron.log 2>&1

# Database backup (03:00 WIB = 20:00 UTC kemarin)
0 20 * * * www-data /usr/bin/mysqldump -u saham_user -pAman123!@# analisis_saham | gzip > /var/www/analisis_saham/backups/db_$(date +\%Y\%m\%d_\%H\%M\%S).sql.gz
```

### 2. Set Proper Permissions
```bash
sudo chmod 644 /etc/cron.d/analisis-saham
sudo systemctl restart cron
```

---

## PHASE 10: MONITORING & ALERTING (5 menit)

### 1. Setup Simple Status Check
```bash
sudo nano /usr/local/bin/check-app-health.sh
```

Isi:
```bash
#!/bin/bash
# Check if app is up
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" https://yourdomain.com)

if [ "$HTTP_CODE" != "200" ]; then
    echo "ALERT: App returned HTTP $HTTP_CODE" | mail -s "Analisis Saham Down!" admin@yourdomain.com
else
    echo "OK - $(date)" >> /var/log/app-health-check.log
fi
```

### 2. Make Executable & Cron
```bash
sudo chmod +x /usr/local/bin/check-app-health.sh

# Add to crontab (every 5 minutes)
*/5 * * * * /usr/local/bin/check-app-health.sh
```

---

## POST-LAUNCH CHECKLIST

- [ ] DNS pointing ke server IP ✓
- [ ] HTTPS working & certificate valid ✓
- [ ] Database connected ✓
- [ ] Login page accessible ✓
- [ ] Can create new user ✓
- [ ] Dashboard loads ✓
- [ ] Scanner runs without errors ✓
- [ ] Cron jobs executing ✓
- [ ] Firewall rules active ✓
- [ ] SSL auto-renewal working ✓

---

## TROUBLESHOOTING

### PHP Not Executing
```bash
sudo a2enmod php8.1
sudo systemctl restart apache2
```

### 500 Internal Server Error
```bash
tail -f /var/log/apache2/analisis-saham-error.log
# Check file permissions: sudo chown -R www-data:www-data /var/www/analisis_saham
```

### Database Connection Failed
```bash
# Test connection:
mysql -h localhost -u saham_user -p analisis_saham -e "SELECT 1;"

# Check MySQL is running:
sudo systemctl status mysql
```

### SSL Certificate Issues
```bash
# Renew manually:
sudo certbot renew --force-renewal

# Check certificate:
sudo certbot certificates
```

---

## Support Commands

```bash
# Restart services
sudo systemctl restart apache2
sudo systemctl restart mysql

# Check service status
systemctl status apache2
systemctl status mysql
systemctl status certbot.timer

# View logs
tail -100 /var/log/apache2/analisis-saham-error.log
journalctl -u apache2 -n 50

# Disk space
du -sh /var/www/analisis_saham

# Monitor in real-time
watch -n 5 'ps aux | grep -E "apache|mysql|php'
```

---

**Estimated Total Time: 2-3 hours**

Setelah selesai, test trial dengan 5-10 user dulu buat memastikan stability sebelum scale up.
