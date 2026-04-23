# Manual Deployment: Analisis Saham di Windows Server 2016+

## Pre-Deployment Checklist

- [ ] Windows Server 2016+ sudah install & updated
- [ ] RDP access ke server tersedia
- [ ] Domain sudah pointing ke IP server
- [ ] Internet connection stable

---

## PHASE 1: WINDOWS SERVER PREP (15 menit)

### 1. Update Windows
```
Settings > Update & Security > Check for updates
(Tunggu hingga selesai, mungkin perlu restart)
```

### 2. Enable Required Windows Features
```powershell
# Jalankan PowerShell sebagai Administrator

# Enable IIS dengan CGI & Rewrite
Enable-WindowsOptionalFeature -Online -FeatureName IIS-WebServerRole
Enable-WindowsOptionalFeature -Online -FeatureName IIS-WebServer
Enable-WindowsOptionalFeature -Online -FeatureName IIS-CommonHttpFeatures
Enable-WindowsOptionalFeature -Online -FeatureName IIS-StaticContent
Enable-WindowsOptionalFeature -Online -FeatureName IIS-DefaultDocument
Enable-WindowsOptionalFeature -Online -FeatureName IIS-DirectoryBrowsing
Enable-WindowsOptionalFeature -Online -FeatureName IIS-HttpErrors
Enable-WindowsOptionalFeature -Online -FeatureName IIS-HealthAndDiagnostics
Enable-WindowsOptionalFeature -Online -FeatureName IIS-HttpLogging
Enable-WindowsOptionalFeature -Online -FeatureName IIS-LoggingLibraries
Enable-WindowsOptionalFeature -Online -FeatureName IIS-RequestMonitor
Enable-WindowsOptionalFeature -Online -FeatureName IIS-Security
Enable-WindowsOptionalFeature -Online -FeatureName IIS-URLAuthorization
Enable-WindowsOptionalFeature -Online -FeatureName IIS-IPSecurity
Enable-WindowsOptionalFeature -Online -FeatureName IIS-BasicAuthentication
Enable-WindowsOptionalFeature -Online -FeatureName IIS-WindowsAuthentication
Enable-WindowsOptionalFeature -Online -FeatureName IIS-ApplicationDevelopment
Enable-WindowsOptionalFeature -Online -FeatureName IIS-CGI
Enable-WindowsOptionalFeature -Online -FeatureName IIS-Rewrite

# Restart Server setelah enable features
Restart-Computer
```

### 3. Verify IIS Installed
```powershell
# Buka IIS Manager: Windows key > type "iis" > click "Internet Information Services (IIS) Manager"
# atau via PowerShell:
Get-WindowsFeature -Name IIS-WebServer
```

---

## PHASE 2: DATABASE SETUP - MySQL (20 menit)

### 1. Download MySQL for Windows
```
1. Buka: https://dev.mysql.com/downloads/mysql/
2. Download: MySQL Server 8.0 Community (Windows 64-bit)
3. Extract ke: C:\MySQL\mysql-8.0
```

### 2. Install MySQL as Windows Service
```powershell
# Run as Administrator
cd C:\MySQL\mysql-8.0\bin

# Initialize data directory
.\mysqld --initialize-insecure

# Install as service
.\mysqld --install MySQL80 --defaults-file=C:\MySQL\my.ini

# Start service
net start MySQL80

# Verify
mysql -u root
# Harus berhasil connect (prompt mysql>)
EXIT;
```

### 3. Secure MySQL
```powershell
# Run sebagai Administrator
mysql -u root

# Di MySQL prompt:
```

```sql
-- Set root password
ALTER USER 'root'@'localhost' IDENTIFIED BY 'RootPass123!@#';

-- Create database & user
CREATE DATABASE analisis_saham CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'saham_user'@'localhost' IDENTIFIED BY 'SahamPass123!@#';
GRANT ALL PRIVILEGES ON analisis_saham.* TO 'saham_user'@'localhost';
FLUSH PRIVILEGES;

-- Test
EXIT;
```

### 4. Test Database Connection
```powershell
mysql -u saham_user -p
# Masukkan password: SahamPass123!@#
# Harus berhasil connect

EXIT;
```

---

## PHASE 3: PHP SETUP (15 menit)

### 1. Download PHP for Windows
```
1. Buka: https://www.php.net/downloads
2. Download: PHP 8.1 (Thread Safe version untuk IIS)
3. Extract ke: C:\PHP\php-8.1
```

### 2. Configure PHP
```powershell
cd C:\PHP\php-8.1

# Rename config template
Copy-Item php.ini-production php.ini

# Edit php.ini (gunakan Notepad)
notepad php.ini
```

Ubah setting berikut di php.ini:
```ini
extension_dir = "C:\PHP\php-8.1\ext"

; Enable extensions
extension=php_curl.dll
extension=php_gd.dll
extension=php_mbstring.dll
extension=php_mysql.dll
extension=php_mysqli.dll
extension=php_pdo_mysql.dll
extension=php_openssl.dll

memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 50M
post_max_size = 50M
display_errors = Off
log_errors = On
error_log = "C:\PHP\php-error.log"
```

### 3. Register PHP with IIS
```powershell
# Run IIS Manager sebagai Administrator:
# 1. Click pada server name (left panel)
# 2. Double-click "Handler Mappings"
# 3. Right-click > "Add Module Mapping"
#    - Request path: *.php
#    - Module: FastCgiModule
#    - Executable: C:\PHP\php-8.1\php-cgi.exe
#    - Name: PHP81

# Atau via PowerShell:
cd "C:\Program Files\IIS\Microsoft Web Platform Installer"
# (tidak recommended, manual lebih reliable)
```

### 4. Test PHP
```powershell
# Create test file
notepad C:\inetpub\wwwroot\test.php

# Paste:
<?php
    phpinfo();
?>

# Buka browser: http://localhost/test.php
# Harus muncul PHP info page
```

---

## PHASE 4: APPLICATION DEPLOYMENT (15 menit)

### 1. Download & Extract Application
```powershell
# Via PowerShell (or use Git GUI if preferred)
cd C:\inetpub\wwwroot
git clone https://github.com/suparman91/analisis_saham.git
cd analisis_saham
```

### 2. Create db.php
```powershell
notepad C:\inetpub\wwwroot\analisis_saham\db.php
```

Paste:
```php
<?php
$db_host = 'localhost';
$db_user = 'saham_user';
$db_pass = 'SahamPass123!@#';
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
```powershell
mkdir C:\inetpub\wwwroot\analisis_saham\uploads
mkdir C:\inetpub\wwwroot\analisis_saham\logs
mkdir C:\inetpub\wwwroot\analisis_saham\cache
mkdir C:\inetpub\wwwroot\analisis_saham\backups

# Grant permissions untuk IIS (IUSR)
# Right-click folder > Properties > Security > Edit
# Add: IIS AppPool\DefaultAppPool (Full Control)
```

### 4. Import Database
```powershell
# Jika sudah ada dump file dari local:

# 1. Copy dump ke server (via SCP atau RDP copy)
Copy-Item "C:\Users\Admin\Downloads\dump.sql" "C:\Temp\dump.sql"

# 2. Import
cd C:\MySQL\mysql-8.0\bin
.\mysql -u saham_user -p analisis_saham < "C:\Temp\dump.sql"
# Masukkan password: SahamPass123!@#
```

---

## PHASE 5: IIS WEBSITE CONFIGURATION (10 menit)

### 1. Create IIS Website
```
1. Buka IIS Manager
2. Right-click "Sites" > "Add Website"
   - Site name: analisis-saham
   - Physical path: C:\inetpub\wwwroot\analisis_saham
   - Binding:
     * Type: http
     * IP: All Unassigned
     * Port: 80
     * Host name: yourdomain.com
   - Click OK
```

### 2. Set Default Document
```
1. Click website "analisis-saham" (left panel)
2. Double-click "Default Document"
3. Click "Add..." > tambah:
   - index.php
   - index.html
4. Move index.php ke atas (priority)
```

### 3. Enable URL Rewrite
```
1. Click website "analisis-saham"
2. Double-click "URL Rewrite"
3. Right-click > "Add Rule" > "New Inbound Rule"
   - Name: Analisis Saham Rewrite
   - Pattern: ^(.*)$
   - Append query string: checked
   - Rewrite URL: app.php?page={R:1}
   - Click OK
```

### 4. Handler Mapping PHP (jika belum)
```
1. Click website "analisis-saham"
2. Double-click "Handler Mappings"
3. Pastikan *.php sudah mapped ke FastCgiModule
```

---

## PHASE 6: SSL CERTIFICATE (15 menit)

### Option A: Let's Encrypt (Free) - via Certbot
```powershell
# Download & Install Certbot for Windows:
https://certbot.eff.org/instructions?ws=iis&os=windows

# Run sebagai Administrator:
certbot certonly --iis -d yourdomain.com -d www.yourdomain.com --email admin@yourdomain.com --agree-tos --non-interactive

# Certificate akan tersave di:
C:\Certbot\live\yourdomain.com\
```

### Option B: Self-Signed (untuk testing saja)
```powershell
# Run PowerShell sebagai Administrator

# Create self-signed cert
$cert = New-SelfSignedCertificate `
  -CertStoreLocation cert:\LocalMachine\My `
  -DnsName yourdomain.com,www.yourdomain.com `
  -FriendlyName "Analisis Saham"

# Note the thumbprint, gunakan di IIS
$cert.Thumbprint
```

### 6. Add HTTPS Binding to IIS
```
1. IIS Manager > analisis-saham > Bindings (right panel)
2. Click "Add..."
   - Type: https
   - IP: All Unassigned
   - Port: 443
   - Host name: yourdomain.com
   - SSL Certificate: (pilih cert yang sudah di-create)
3. Click OK

4. Edit HTTP binding untuk redirect ke HTTPS:
   - Click binding HTTP > Edit
   - Host name: yourdomain.com
   - Click OK
```

### 7. Setup HTTP to HTTPS Redirect
```
1. Click website "analisis-saham"
2. Double-click "URL Rewrite"
3. Click "Add Rule" > "New Inbound Rule"
   - Pattern: (.*)
   - Conditions: HTTPS = OFF
   - Action: Redirect
   - Redirect URL: https://yourdomain.com/{R:1}
   - Click OK
```

---

## PHASE 7: SECURITY HARDENING (10 menit)

### 1. Windows Firewall Setup
```powershell
# Run as Administrator

# Allow ports
New-NetFirewallRule -DisplayName "HTTP" -Direction Inbound -Action Allow -Protocol TCP -LocalPort 80
New-NetFirewallRule -DisplayName "HTTPS" -Direction Inbound -Action Allow -Protocol TCP -LocalPort 443
New-NetFirewallRule -DisplayName "RDP" -Direction Inbound -Action Allow -Protocol TCP -LocalPort 3389

# Block unnecessary ports
Set-NetFirewallProfile -DefaultInboundAction Block -DefaultOutboundAction Allow -NotifyOnListen True
```

### 2. IIS Security - Remove Server Version Header
```
1. IIS Manager > analisis-saham
2. Double-click "HTTP Response Headers"
3. Right-click "Server" > Remove
```

### 3. Disable Directory Browsing
```
1. IIS Manager > analisis-saham
2. Double-click "Directory Browsing"
3. Click "Disable" (right panel)
```

### 4. Add .htaccess-like Protection via IIS
```
1. IIS Manager > analisis-saham
2. Double-click "Request Filtering"
3. Click "File Name Extensions" tab
4. Right-click > "Add Denied File Name Extension"
   - .env
   - .sql
   - .json
   - .conf
   - .txt (optional, tergantung)
```

---

## PHASE 8: PERFORMANCE OPTIMIZATION (5 menit)

### 1. Enable Compression
```
1. IIS Manager > Server name
2. Double-click "Compression"
   - Enable static content compression: checked
   - Enable dynamic content compression: checked
3. Apply
```

### 2. Set Application Pool Recycling
```
1. IIS Manager > Application Pools > DefaultAppPool > Advanced Settings
   - Regular Time Interval: 1740 minutes (29 hours)
   - Idle Time-out: 1800 seconds (30 minutes)
   - Apply
```

---

## PHASE 9: MONITORING & LOGGING (5 menit)

### 1. Enable IIS Logging
```
1. IIS Manager > analisis-saham
2. Double-click "Logging"
   - Log Format: W3C
   - Directory: C:\inetpub\logs\LogFiles\
   - Apply
```

### 2. Setup PHP Error Logging
```powershell
# PHP errors sudah di-log ke C:\PHP\php-error.log (dari php.ini step)

# Buat folder untuk app logs
mkdir C:\inetpub\wwwroot\analisis_saham\logs
```

### 3. Monitor Real-Time via PowerShell
```powershell
# CPU & Memory
Get-Process | Sort-Object -Property WorkingSet -Descending | Select-Object -First 10

# IIS Status
Get-WebSite
Get-WebAppPool

# MySQL Status
Get-Service MySQL80

# View IIS logs live
Get-Content "C:\inetpub\logs\LogFiles\W3SVC1\u_ex*.log" -Wait
```

---

## PHASE 10: SCHEDULED TASKS (10 menit)

### 1. Daily Data Update (EOD)
```powershell
# Task Scheduler > Create Task

# General Tab:
# - Name: Analisis Saham - Daily Update
# - Run whether user is logged in or not: checked
# - Run with highest privileges: checked

# Triggers Tab:
# - New Trigger
#   * One time or Daily at 17:00 WIB (adjust timezone)
#   * Repeat every 1 day
#   * For a duration of: Indefinitely

# Actions Tab:
# - Start a program
#   * Program: C:\PHP\php-8.1\php.exe
#   * Arguments: C:\inetpub\wwwroot\analisis_saham\fetch_data.php
#   * Start in: C:\inetpub\wwwroot\analisis_saham

# Click OK
```

### 2. Scan History Cleanup
```powershell
# Task Scheduler > Create Task

# Actions Tab:
# - Program: C:\PHP\php-8.1\php.exe
# - Arguments: C:\inetpub\wwwroot\analisis_saham\cleanup_scan_history.php
# - Trigger: Daily at 00:00 (Midnight)

# Click OK
```

### 3. Database Backup
```powershell
# Task Scheduler > Create Task

# Actions Tab:
# - Program: C:\Windows\System32\cmd.exe
# - Arguments: /c "C:\MySQL\mysql-8.0\bin\mysqldump" -u saham_user -pSahamPass123!@# analisis_saham | "C:\Program Files\7-Zip\7z.exe" a -si "C:\inetpub\wwwroot\analisis_saham\backups\db_$(date +%Y%m%d_%H%M%S).sql.7z"
# - Trigger: Daily at 03:00 AM

# Click OK
```

---

## PHASE 11: TESTING & VERIFICATION (10 menit)

### 1. Test Application Access
```
1. Buka browser: https://yourdomain.com
2. Harus muncul landing page
3. Coba register akun test
4. Login & akses dashboard
5. Test scanner (BPJP/SWING)
```

### 2. Test Database
```powershell
mysql -u saham_user -p
# Masukkan password
USE analisis_saham;
SHOW TABLES;
SELECT COUNT(*) FROM prices;
EXIT;
```

### 3. Check Logs
```powershell
# IIS Log
Get-Content "C:\inetpub\logs\LogFiles\W3SVC1\u_ex*.log" -Tail 20

# PHP Error
Get-Content "C:\PHP\php-error.log" -Tail 20

# Event Viewer (System & Application logs)
eventvwr.msc
```

### 4. Performance Test
```powershell
# CPU & RAM
Get-Counter '\Processor(_Total)\% Processor Time'
Get-Counter '\Memory\% Committed Bytes In Use'

# Disk
Get-Volume

# Network
netstat -an | findstr ESTABLISHED | Measure-Object
```

---

## POST-LAUNCH CHECKLIST

- [ ] DNS pointing ke server IP ✓
- [ ] HTTP → HTTPS redirect working ✓
- [ ] SSL certificate valid & trusted ✓
- [ ] Database connected & data imported ✓
- [ ] Landing page accessible ✓
- [ ] User registration working ✓
- [ ] Login successful ✓
- [ ] Dashboard loads without errors ✓
- [ ] Scanner executes (BPJP/SWING) ✓
- [ ] Scheduled tasks created ✓
- [ ] Firewall rules active ✓
- [ ] IIS logging enabled ✓

---

## TROUBLESHOOTING

### Website Returns 500 Error
```powershell
# Check IIS log
Get-Content "C:\inetpub\logs\LogFiles\W3SVC1\u_ex*.log" -Tail 50

# Check PHP error log
Get-Content "C:\PHP\php-error.log" -Tail 50

# Verify DB connection
mysql -u saham_user -p analisis_saham -e "SELECT 1;"
```

### PHP Not Executing
```
1. IIS Manager > Handler Mappings
   - Ensure *.php mapped to FastCgiModule
2. IIS Manager > Server > ISAPI and CGI Restrictions
   - Ensure C:\PHP\php-8.1\php-cgi.exe is Allowed
```

### SSL Certificate Issues
```powershell
# List certificates
Get-ChildItem -Path Cert:\LocalMachine\My

# Renew Let's Encrypt cert (jika menggunakan)
certbot renew --force-renewal --iis
```

### MySQL Service Won't Start
```powershell
# Check service status
Get-Service MySQL80

# Start service
Start-Service MySQL80

# View MySQL error log
Get-Content "C:\MySQL\mysql-8.0\data\*.err"
```

### Permission Denied Error
```powershell
# Give IIS App Pool read/write access to app folder
icacls "C:\inetpub\wwwroot\analisis_saham" /grant "IIS AppPool\DefaultAppPool:(OI)(CI)F"

# Recursively set permissions
icacls "C:\inetpub\wwwroot\analisis_saham" /grant "IIS AppPool\DefaultAppPool:(OI)(CI)F" /T
```

---

## CRITICAL CREDENTIALS TO SECURE

```
Database:
- Username: saham_user
- Password: SahamPass123!@#

MySQL Root:
- Username: root
- Password: RootPass123!@#

STORE IN SECURE LOCATION (NOT IN GIT):
- Keep passwords in encrypted password manager
- Do NOT commit db.php to public repository
- Rotate passwords monthly for production
```

---

## SUPPORT COMMANDS (PowerShell)

```powershell
# Service management
Get-Service | Where {$_.Name -like "*MySQL*" -or $_.Name -like "*IIS*"}
Stop-Service MySQL80
Start-Service MySQL80
Restart-Service W3SVC  # Restart IIS

# Check listening ports
netstat -an | findstr LISTENING

# View running processes
Get-Process | Sort-Object WorkingSet -Descending | Select-Object -First 20

# File operations
Get-ChildItem -Path "C:\inetpub\wwwroot\analisis_saham" -Recurse -File | Measure-Object -Property Length -Sum

# Event log
Get-EventLog -LogName Application -Newest 50 -EntryType Error
```

---

**Estimated Total Time: 3-4 hours**

Test dengan 5-10 user terlebih dahulu sebelum public launch. Monitor resource usage selama 1-2 minggu sebelum scale up.
