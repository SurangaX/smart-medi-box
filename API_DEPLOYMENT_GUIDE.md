# Smart Medi Box - API Server Setup & Deployment Guide

## 📋 System Requirements

### Minimum Requirements
- **PHP:** 7.4 or higher
- **MySQL:** 5.7.x or MariaDB 10.3+
- **Web Server:** Apache 2.4+ or Nginx 1.18+
- **RAM:** 512 MB
- **Disk:** 1 GB available space

### Recommended Requirements 
- **PHP:** 8.1+
- **MySQL:** 8.0+
- **Web Server:** Apache with mod_rewrite OR Nginx
- **RAM:** 1+ GB
- **Disk:** 5+ GB

### PHP Extensions Required
```bash
php-json       (usually enabled by default)
php-mysqli     (MySQL support)
php-curl       (for HTTP requests to Arduino/GSM)
php-date       (date/time functions)
php-filter     (input validation)
php-hash       (for token generation)
php-spl        (Standard PHP Library)
```

---

## 🔧 Installation Steps

### Step 1: Download & Extract Files

```bash
cd /var/www/html
mkdir smart-medi-box
cd smart-medi-box

# Copy all robot_api files from project
cp -r /path/to/project/robot_api .
cp /path/to/project/database_schema.sql .
cp /path/to/project/SYSTEM_DOCUMENTATION.md .
```

### Step 2: Create MySQL Database

```bash
# Connect to MySQL
mysql -u root -p

# In MySQL prompt:
CREATE DATABASE smart_medi_box CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'medi_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON smart_medi_box.* TO 'medi_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import schema
mysql -u medi_user -p smart_medi_box < database_schema.sql
```

### Step 3: Configure Database Connection

Edit `robot_api/db_config.php`:

```php
<?php
// Update these values
define('DB_HOST', 'localhost');
define('DB_USER', 'medi_user');
define('DB_PASSWORD', 'your_secure_password');
define('DB_NAME', 'smart_medi_box');

// Test connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'ERROR', 'message' => 'Database connection failed']);
    exit();
}
?>
```

### Step 4: Configure Apache

Create `/etc/apache2/sites-available/smart-medi-box.conf`:

```apache
<VirtualHost *:80>
    ServerName medi-box.local
    ServerAlias localhost
    DocumentRoot /var/www/html/smart-medi-box

    <Directory /var/www/html/smart-medi-box>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted

        # Enable mod_rewrite
        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteBase /
            RewriteRule ^index\.php$ - [L]
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule . /robot_api/index.php [L]
        </IfModule>
    </Directory>

    # Enable CORS for mobile apps
    Header set Access-Control-Allow-Origin "*"
    Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header set Access-Control-Allow-Headers "Content-Type, Authorization"

    # Logs
    ErrorLog ${APACHE_LOG_DIR}/medi-box-error.log
    CustomLog ${APACHE_LOG_DIR}/medi-box-access.log combined
</VirtualHost>
```

Enable the site:
```bash
sudo a2ensite smart-medi-box
sudo a2enmod rewrite
sudo a2enmod headers
sudo systemctl restart apache2
```

### Step 5: Configure Nginx (Alternative to Apache)

Create `/etc/nginx/sites-available/smart-medi-box`:

```nginx
server {
    listen 80;
    server_name medi-box.local;
    root /var/www/html/smart-medi-box;

    location /api {
        index index.php;
        if (!-e $request_filename) {
            rewrite ^(.+)$ /robot_api/index.php?url=$1 last;
        }
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }

    # Disable access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ /database_schema.sql {
        deny all;
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/smart-medi-box /etc/nginx/sites-enabled/
sudo systemctl restart nginx
```

---

## ✅ Verification Steps

### 1. Test Database Connection

```bash
# From project directory
php -r "
require 'robot_api/db_config.php';
if (\$conn->connect_error) {
    echo 'Connection failed: ' . \$conn->connect_error;
} else {
    echo 'Database connected successfully!';
    echo '\nTables created: ';
    \$result = \$conn->query('SHOW TABLES');
    while (\$row = \$result->fetch_array()) {
        echo '\n- ' . \$row[0];
    }
}
"
```

### 2. Test API Endpoint

```bash
# Test API status endpoint
curl -X GET "http://localhost/api/status"

# Expected response:
# {"status":"SUCCESS","message":"Smart Medi Box API Online","service":"Smart Medi Box","version":"1.0.0","endpoints":{...}}
```

### 3. Test User Registration

```bash
# Create test user
curl -X POST "http://localhost/api/auth/register" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "age": 25,
    "phone": "0777154321",
    "mac_address": "AA:BB:CC:DD:EE:FF"
  }'

# Expected response:
# {"status":"SUCCESS","user_id":"USER_20260413_A1B2C3","message":"User registered successfully"}
```

### 4. Verify Database Triggers

```mysql
-- Connect to database
mysql -u medi_user -p smart_medi_box

-- Check if temperature_settings was auto-created
SELECT * FROM temperature_settings WHERE user_id IN (SELECT id FROM users LIMIT 1);

-- Should show one row with default values
```

---

## 🔐 Security Configuration

### 1. File Permissions

```bash
# Set proper permissions
chmod 755 /var/www/html/smart-medi-box
chmod 755 /var/www/html/smart-medi-box/robot_api
chmod 644 /var/www/html/smart-medi-box/robot_api/*.php
chmod 600 /var/www/html/smart-medi-box/robot_api/db_config.php
```

### 2. Hide Sensitive Files

Create `.htaccess` in root:

```apache
# Deny access to sensitive files
<Files "database_schema.sql">
    Order allow,deny
    Deny from all
</Files>

<Files ~ "\.sql$">
    Order allow,deny
    Deny from all
</Files>

# Block admin files if created
<Files "admin*">
    Order allow,deny
    Deny from all
</Files>
```

### 3. Implement HTTPS/SSL

```bash
# Using Let's Encrypt (free)
sudo certbot certonly --apache -d medi-box.local

# Update Apache config to use SSL
<VirtualHost *:443>
    ServerName medi-box.local
    DocumentRoot /var/www/html/smart-medi-box
    
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/medi-box.local/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/medi-box.local/privkey.pem
</VirtualHost>

# Redirect HTTP to HTTPS
<VirtualHost *:80>
    ServerName medi-box.local
    Redirect permanent / https://medi-box.local/
</VirtualHost>
```

### 4. Database Security

```mysql
-- Revoke unnecessary privileges
REVOKE ALL PRIVILEGES ON *.* FROM 'medi_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON smart_medi_box.* TO 'medi_user'@'localhost';

-- Create read-only user for reporting
CREATE USER 'medi_readonly'@'localhost' IDENTIFIED BY 'readonly_password';
GRANT SELECT ON smart_medi_box.* TO 'medi_readonly'@'localhost';
FLUSH PRIVILEGES;
```

---

## 🚀 Production Deployment

### Option 1: Standard Hosting Provider

**GoDaddy / Bluehost / HostGator:**

1. Upload files via FTP to `public_html/medi-box/`
2. Create database via hosting control panel
3. Update `db_config.php` with provided credentials
4. Access at: `https://yourdomain.com/medi-box/api/status`

### Option 2: Cloud Deployment (AWS/Digital Ocean)

```bash
# SSH into Ubuntu instance
ssh ubuntu@your-server-ip

# Update system
sudo apt update && sudo apt upgrade -y

# Install requirements
sudo apt install -y apache2 php8.1 php8.1-mysql php8.1-curl php8.1-json
sudo apt install -y mysql-server

# Follow Steps 1-4 above

# Enable firewall
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### Option 3: Docker Containerization

Create `Dockerfile`:

```dockerfile
FROM php:8.1-apache
WORKDIR /var/www/html

RUN docker-php-ext-install mysqli json curl

COPY robot_api /var/www/html/robot_api
COPY database_schema.sql /var/www/html/

RUN a2enmod rewrite headers

EXPOSE 80
CMD ["apache2-foreground"]
```

Build & run:
```bash
docker build -t smart-medi-box .
docker run -d \
  -p 80:80 \
  -e DB_HOST=mysql \
  --link mysql \
  smart-medi-box
```

---

## 📊 Database Maintenance

### Backup Database

```bash
# Daily backup
mysqldump -u medi_user -p smart_medi_box > backup_$(date +%Y%m%d).sql

# Automated backup (add to crontab)
# 0 2 * * * mysqldump -u medi_user -p smart_medi_box > /backups/backup_$(date +\%Y\%m\%d).sql
```

### Restore Database

```bash
mysql -u medi_user -p smart_medi_box < backup_20260413.sql
```

### Monitor Database Size

```mysql
SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES
WHERE table_schema = 'smart_medi_box'
ORDER BY (data_length + index_length) DESC;
```

---

## 🔍 Monitoring & Logging

### Check PHP Errors

```bash
# View Apache error log
tail -f /var/apache2/logs/error.log

# View nginx error log
tail -f /var/log/nginx/error.log
```

### Monitor Database Connections

```mysql
-- See active connections
SHOW PROCESSLIST;

-- Kill long-running query (be careful!)
KILL QUERY process_id;
```

### Setup Application Logging

Add to `robot_api/index.php`:

```php
// Enable logging
error_log("API Request: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST Data: " . file_get_contents('php://input'));
}
```

---

## 🧪 Testing Checklist

- [ ] Database connected successfully
- [ ] API status endpoint responds
- [ ] User registration works
- [ ] Login/authentication works
- [ ] Schedule creation works
- [ ] Temperature readings display
- [ ] Alarm triggers on schedule time
- [ ] SMS notifications send
- [ ] Temperature logs are recorded
- [ ] Device sync works
- [ ] Arduino can fetch commands

---

## 🆘 Troubleshooting

### "Database connection failed"
```
Solution: Verify credentials in db_config.php
Check: mysql -u medi_user -p smart_medi_box -e "SHOW TABLES;"
```

### "404 Not Found" on API endpoint
```
Solution: Enable mod_rewrite in Apache
Check: sudo a2enmod rewrite && sudo systemctl restart apache2
```

### "CORS error" from mobile app
```
Solution: Verify CORS headers in index.php
Add: Header set Access-Control-Allow-Origin "*"
```

### "Out of memory" error
```
Solution: Increase PHP memory limit in php.ini
Find: memory_limit = 128M
Change to: memory_limit = 256M
```

### "Too many connections"
```
Solution: Increase MySQL max_connections
In my.cnf: max_connections = 200
```

---

## 📈 Performance Optimization

### 1. Enable Database Indexes (Already Done)
All critical tables have indexes on:
- user_id (foreign key)
- created_at (timestamp)
- schedule date

### 2. Cache API Responses

Add to `robot_api/index.php`:

```php
// Cache GET requests for 5 minutes
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Cache-Control: max-age=300, public');
}
```

### 3. Enable Gzip Compression

In Apache config:
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE application/json
    AddOutputFilterByType DEFLATE text/html
</IfModule>
```

### 4. Optimize Database Queries

Monitor slow queries:
```mysql
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;
```

---

## 🔗 Integration Points

### Arduino → API
```
Arduino sends: http://server/api/auth/verify?user_id=USER_123&mac=AA:BB:CC
API returns: {status, schedule, temperature_settings}
```

### Mobile App → API
```
App sends: POST /api/auth/register (QR scan data)
App receives: {user_id, token, schedules}
```

### Temperature Sensor → Database
```
Arduino writes: POST /api/temperature/log (readings)
Database stores: temperature_logs table
```

---

## 📞 Support & Maintenance

### Monthly Tasks
- [ ] Review error logs
- [ ] Check database size
- [ ] Verify backup success
- [ ] Test disaster recovery

### Quarterly Tasks
- [ ] Update PHP/MySQL versions
- [ ] Refresh SSL certificates
- [ ] Archive old logs
- [ ] Review user activity

---

**Version:** 1.0.0  
**Last Updated:** April 13, 2026  
**Maintainer:** Smart Medi Box Development Team
