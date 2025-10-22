# 🚀 WBSC Quick Reference Card

## 📍 URLs

```
Homepage:      http://localhost:8083/wbsc-app/
Teacher Sync:  http://localhost:8083/wbsc-app/admin/teacher-sync
Enrollment:    http://localhost:8083/wbsc-app/admin/enrollment-sync
```

## 🔧 Critical Files

| File | Location | Purpose |
|------|----------|---------|
| Entry Point | `index.php` | ROOT (not in public/) |
| Apache Config | `.htaccess` | URL rewriting + Security |
| App Config | `app/Config/App.php` | `indexPage = ''` |
| Environment | `.env` | Credentials + Settings |

## ⚙️ Configuration

### App.php
```php
$baseURL = 'http://localhost:8083/wbsc-app/';
$indexPage = '';  // Empty for clean URLs
```

### .htaccess
```apache
RewriteBase /wbsc-app/  # Must match your path
```

### .env
```ini
app.baseURL = 'http://localhost:8083/wbsc-app/'
app.indexPage = ''
```

## 🎯 CLI Commands

```bash
# Teacher Sync
php spark teacher:sync                  # Run sync
php spark teacher:sync --dry-run        # Preview only
php spark teacher:sync --force          # Ignore recent check
php spark teacher:sync --verbose        # Detailed output

# Enrollment Sync
php spark moodle:sync

# System
php spark migrate                       # Run migrations
php spark list                          # List all commands
```

## 🧪 Testing

```bash
# Quick URL test
http://localhost:8083/wbsc-app/test-urls.php

# Manual tests
curl -I http://localhost:8083/wbsc-app/                    # 200 OK
curl -I http://localhost:8083/wbsc-app/.env                # 403 Forbidden
curl -I http://localhost:8083/wbsc-app/index.php           # 301 Redirect
```

## 🔐 Protected URLs (Should return 403)

```
/.env
/composer.json
/.git/
/app/
/system/
/writable/
/*.md
```

## 📊 Database Connections

### MariaDB (default)
```ini
hostname = localhost
database = wbsc_lms_2020
username = root
port = 3306
```

### Oracle
```ini
hostname = oracle-server
database = ORCL
username = WBSC
port = 1521
```

## 🔄 Sync Operations

### Teacher Sync Flow
```
MariaDB View → Extract → Oracle Table
vw_course_teacher_detailed → TRUNCATE → COURSE_TEACHER_DETAILED
```

### Enrollment Sync Flow
```
Oracle View → Compare → Moodle DB → CSV → Moodle LMS
V_STUDENT_ENROLLMENT → Master Data → sync-data.csv → Enrolment
```

## 🐛 Quick Fixes

### 404 Not Found
```bash
# Enable mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 500 Error
```bash
# Check .htaccess syntax
apachectl configtest

# Check error log
tail -f /var/log/apache2/error.log
```

### CSS Not Loading
```php
// Use base_url() helper
<link href="<?= base_url('assets/css/style.css') ?>">
```

### index.php Still in URL
```php
// Check app/Config/App.php
public string $indexPage = '';  // Must be empty
```

## 🎯 Cron Jobs

```bash
# Teacher Sync - Daily at 2 AM
0 2 * * * cd /path/to/wbsc-app && php spark teacher:sync >> /var/log/teacher-sync.log 2>&1

# Enrollment Sync - Daily at 3 AM
0 3 * * * cd /path/to/wbsc-app && php spark moodle:sync >> /var/log/moodle-sync.log 2>&1
```

## 📝 Logs

```bash
# Application logs
tail -f writable/logs/log-$(date +%Y-%m-%d).log

# Specific sync
grep "TeacherSync" writable/logs/log-*.log

# Cron logs
tail -f /var/log/teacher-sync.log
```

## 🔑 Important Paths

```
Project Root:    D:/local-wwwroot/wbsc-app/
Entry Point:     D:/local-wwwroot/wbsc-app/index.php
App Folder:      D:/local-wwwroot/wbsc-app/app/
Config Files:    D:/local-wwwroot/wbsc-app/app/Config/
Services:        D:/local-wwwroot/wbsc-app/app/Services/Sync/
Views:           D:/local-wwwroot/wbsc-app/app/Views/admin/
Logs:            D:/local-wwwroot/wbsc-app/writable/logs/
```

## 🆘 Emergency Contacts

```
Documentation:
- SETUP_GUIDE.md        → Installation instructions
- CHANGES_SUMMARY.md    → What changed
- project-summary.txt   → Complete project info
- README.md             → Architecture

Support:
- Apache Docs:  https://httpd.apache.org/docs/
- CI4 Docs:     https://codeigniter.com/user_guide/
- Oracle:       https://docs.oracle.com/
```

## ⚡ Quick Commands

```bash
# Restart Apache (Windows XAMPP)
net stop Apache2.4
net start Apache2.4

# Restart Apache (Linux)
sudo systemctl restart apache2

# Clear CI4 cache
php spark cache:clear

# Check PHP modules
php -m | grep -E "oci8|mysqli"

# Check Apache modules
apache2ctl -M | grep rewrite

# Test database connection
php -r "\$db = new mysqli('localhost','root','pass','db'); echo \$db->connect_error ?: 'Connected';"
```

## 📈 Performance

```
Target Metrics:
- Page Load: < 2 seconds
- API Response: < 500ms
- Sync Duration: < 60 seconds (per 1000 records)
- Memory Usage: < 256MB
```

## ✅ Daily Checklist

```
Morning:
[ ] Check sync logs
[ ] Verify last night's cron runs
[ ] Check error logs
[ ] Monitor disk space

Weekly:
[ ] Review sync statistics
[ ] Clean old logs (keep last 30 days)
[ ] Check database size
[ ] Update documentation if needed
```

---

**Quick Access:**
- 🏠 Homepage: `/`
- 🎯 Teacher Sync: `/admin/teacher-sync`
- 📊 Enrollment: `/admin/enrollment-sync`
- 🧪 Test URLs: `/test-urls.php` (delete after use)

**Version:** v1.1.0 | **Updated:** October 2025