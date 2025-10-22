# 📘 Teacher Sync Installation Guide

## 🎯 Overview
ระบบ Teacher Sync สำหรับดึงข้อมูลครูผู้สอนจาก MariaDB (Moodle) → Oracle Database แบบอัตโนมัติ

---

## 📋 Prerequisites

### 1. ความต้องการของระบบ
- **PHP**: >= 8.3
- **CodeIgniter**: 4.x
- **MariaDB/MySQL**: >= 10.x
- **Oracle Database**: >= 11g
- **PHP Extensions**:
  - `oci8` (Oracle)
  - `mysqli` (MariaDB)
  - `json`
  - `mbstring`

### 2. ตรวจสอบ PHP Extensions

```bash
# ตรวจสอบ OCI8
php -m | grep oci8

# ตรวจสอบ MySQLi
php -m | grep mysqli

# ถ้าไม่มี ติดตั้งดังนี้
# Ubuntu/Debian
sudo apt-get install php8.3-oci8
sudo apt-get install php8.3-mysql

# CentOS/RHEL
sudo yum install php-oci8
sudo yum install php-mysqlnd
```

---

## 🚀 Installation Steps

### Step 1: Copy Files

```
app/
├── Controllers/
│   └── Admin/
│       └── TeacherSyncController.php       ← Copy
├── Services/
│   └── Sync/
│       ├── BaseSyncService.php             ← Copy
│       └── TeacherSyncService.php          ← Copy
├── Models/
│   └── Sync/
│       └── TeacherSyncLogModel.php         ← Copy
├── Commands/
│   └── TeacherSync.php                     ← Copy
├── Views/
│   └── admin/
│       └── teacher_sync/
│           └── index.php                   ← Copy
└── Database/
    └── Migrations/
        └── YYYYMMDDHHIISS_CreateTeacherSyncLogs.php  ← Copy
```

### Step 2: Configure Database Connections

#### 2.1 แก้ไข `app/Config/Database.php`

```php
public array $default = [
    'hostname' => 'localhost',
    'username' => 'root',
    'password' => 'your_password',
    'database' => 'wbsc_lms_2020',
    'DBDriver' => 'MySQLi',
    'port'     => 3306,
];

public array $oracle = [
    'hostname' => 'oracle-server',
    'username' => 'WBSC',
    'password' => 'your_oracle_password',
    'database' => 'ORCL',
    'DBDriver' => 'OCI8',
    'port'     => 1521,
];
```

#### 2.2 หรือใช้ Environment Variables (`.env`)

```ini
# MariaDB
DB_HOST=localhost
DB_USERNAME=root
DB_PASSWORD=your_password
DB_DATABASE=wbsc_lms_2020
DB_PORT=3306

# Oracle
ORACLE_HOST=oracle-server
ORACLE_USERNAME=WBSC
ORACLE_PASSWORD=your_password
ORACLE_DATABASE=ORCL
ORACLE_PORT=1521
```

### Step 3: Run Database Migration

```bash
# สร้าง Log Table
php spark migrate

# ตรวจสอบว่า table ถูกสร้างแล้ว
# MariaDB
mysql -u root -p wbsc_lms_2020 -e "SHOW TABLES LIKE 'teacher_sync_logs';"

# ดู structure
mysql -u root -p wbsc_lms_2020 -e "DESC teacher_sync_logs;"
```

### Step 4: Configure Routes

เพิ่มใน `app/Config/Routes.php`:

```php
// Teacher Sync Routes
$routes->group('admin/teacher-sync', [
    'namespace' => 'App\Controllers\Admin'
], function($routes) {
    $routes->get('/', 'TeacherSyncController::index');
    $routes->post('execute-sync', 'TeacherSyncController::executeSync');
    $routes->get('get-statistics', 'TeacherSyncController::getStatistics');
    $routes->get('get-history', 'TeacherSyncController::getHistory');
    $routes->get('preview-data', 'TeacherSyncController::previewData');
    $routes->get('download-log/(:num)', 'TeacherSyncController::downloadLog/$1');
});
```

### Step 5: Create Oracle Table

เข้าไปใน Oracle Database และรัน:

```sql
CREATE TABLE WBSC.COURSE_TEACHER_DETAILED
(
  CAT_TYPE            VARCHAR2(255 BYTE),
  CAT_FACULTY         VARCHAR2(255 BYTE),
  CAT_TERM_ID         INTEGER,
  CAT_TERM            VARCHAR2(255 BYTE),
  COURSE_ID           INTEGER,
  COURSE_SHORTNAME    VARCHAR2(255 BYTE),
  COURSE_FULLNAME     VARCHAR2(255 BYTE),
  COURSE_CREATE_DATE  VARCHAR2(255 BYTE),
  USERNAME            VARCHAR2(255 BYTE),
  IDNUMBER            VARCHAR2(255 BYTE),
  FIRSTNAME           VARCHAR2(255 BYTE),
  LASTNAME            VARCHAR2(255 BYTE),
  USERROLE            VARCHAR2(255 BYTE)
);

-- Create indexes for performance
CREATE INDEX idx_teacher_course ON WBSC.COURSE_TEACHER_DETAILED(COURSE_ID, USERNAME);
CREATE INDEX idx_teacher_term ON WBSC.COURSE_TEACHER_DETAILED(CAT_TERM_ID);
```

---

## ✅ Testing

### Test 1: Connection Test

```bash
# Test via CLI
php spark teacher:sync --dry-run

# Expected output:
# ═══════════════════════════════════════════
#   WBSC Teacher Sync - CLI Command
# ═══════════════════════════════════════════
#
# Running pre-flight checks...
# → Checking MariaDB connection...
#   ✓ Source records: 1,234
# → Checking Oracle connection...
#   ✓ Target records: 0
```

### Test 2: Preview Data

เข้า URL: `http://localhost:8083/wbsc-app/admin/teacher-sync`

- ตรวจสอบ Statistics Cards
- กดปุ่ม "Start Sync"
- ดู Log History

### Test 3: CLI Sync

```bash
# Run sync manually
php spark teacher:sync

# With verbose output
php spark teacher:sync --verbose

# Force sync (ignore recent sync check)
php spark teacher:sync --force
```

---

## ⏰ Cron Job Setup

### Linux/Unix Cron

```bash
# แก้ไข crontab
crontab -e

# เพิ่ม job (รันทุกวันเวลา 02:00)
0 2 * * * cd /path/to/wbsc-app && php spark teacher:sync >> /var/log/teacher-sync.log 2>&1

# รันทุก 6 ชั่วโมง
0 */6 * * * cd /path/to/wbsc-app && php spark teacher:sync >> /var/log/teacher-sync.log 2>&1

# รันทุกวันจันทร์ เวลา 03:00
0 3 * * 1 cd /path/to/wbsc-app && php spark teacher:sync >> /var/log/teacher-sync.log 2>&1
```

### Windows Task Scheduler

1. เปิด **Task Scheduler**
2. Create Basic Task
3. ตั้งชื่อ: "WBSC Teacher Sync"
4. Trigger: Daily at 2:00 AM
5. Action: Start a program
   - Program: `C:\php\php.exe`
   - Arguments: `spark teacher:sync`
   - Start in: `D:\local-wwwroot\wbsc-app`

---

## 🔧 Configuration Options

### Service Configuration

แก้ไข `app/Services/Sync/TeacherSyncService.php`:

```php
// Source view name
protected string $sourceView = 'wbsc_lms_2020.vw_course_teacher_detailed';

// Target table name
protected string $targetTable = 'WBSC.COURSE_TEACHER_DETAILED';

// Batch size (records per transaction)
protected int $batchSize = 1000;

// Max retries on error
protected int $maxRetries = 3;
```

---

## 📊 Monitoring & Logs

### View Logs

```bash
# Application logs
tail -f writable/logs/log-*.log

# Cron logs
tail -f /var/log/teacher-sync.log
```

### Database Logs

```sql
-- View recent sync history
SELECT * FROM teacher_sync_logs
ORDER BY id DESC
LIMIT 10;

-- View failed syncs
SELECT * FROM teacher_sync_logs
WHERE status = 'failed'
ORDER BY id DESC;

-- Statistics summary
SELECT
    COUNT(*) as total_syncs,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    AVG(JSON_EXTRACT(result_data, '$.statistics.duration_seconds')) as avg_duration
FROM teacher_sync_logs
WHERE sync_type = 'teacher_sync';
```

---

## 🐛 Troubleshooting

### Problem: OCI8 Extension not loaded

**Solution:**
```bash
# Ubuntu/Debian
sudo apt-get install php8.3-oci8

# ถ้าต้องการ manual install
pecl install oci8

# Enable in php.ini
extension=oci8.so
```

### Problem: Connection timeout to Oracle

**Solution:**
```php
// Increase timeout in Database config
$this->oracle['timeout'] = 30;

// Test tnsping
tnsping ORCL
```

### Problem: Character encoding issues

**Solution:**
```php
// Oracle charset
$this->oracle['charset'] = 'AL32UTF8';

// MariaDB charset
$this->default['charset'] = 'utf8mb4';
```

---

## 🎯 Usage Examples

### Manual Sync via Web UI

1. เปิด: `http://localhost:8083/wbsc-app/admin/teacher-sync`
2. ดู Statistics
3. กด "Start Sync"
4. รอจนเสร็จ
5. ดาวน์โหลด Log CSV (optional)

### CLI Commands

```bash
# Preview data (no changes)
php spark teacher:sync --dry-run

# Normal sync
php spark teacher:sync

# Force sync (ignore recent check)
php spark teacher:sync --force

# Verbose output
php spark teacher:sync --verbose

# Combined
php spark teacher:sync --force --verbose
```

---

## 📝 API Endpoints

### GET `/admin/teacher-sync`
Dashboard UI

### POST `/admin/teacher-sync/execute-sync`
Execute sync (AJAX)

### GET `/admin/teacher-sync/get-statistics`
Get current statistics

### GET `/admin/teacher-sync/get-history?limit=20`
Get sync history

### GET `/admin/teacher-sync/preview-data?limit=10`
Preview source data

### GET `/admin/teacher-sync/download-log/{id}`
Download log as CSV

---

## 🔄 Extending to Other Syncs

Template-based design ทำให้สามารถขยายไปยัง sync อื่นๆ ได้ง่าย:

```php
// Example: StudentSyncService.php
class StudentSyncService extends BaseSyncService
{
    protected string $sourceView = 'wbsc_lms_2020.vw_student_enrollment';
    protected string $targetTable = 'WBSC.STUDENT_ENROLLMENT';

    protected function getSyncType(): string {
        return 'student_sync';
    }

    // Implement abstract methods...
}
```

---

## 📧 Support

หากพบปัญหา:
1. ตรวจสอบ logs ใน `writable/logs/`
2. ตรวจสอบ database connection
3. ทดสอบด้วย `--dry-run` ก่อน
4. ดู sync history ใน Admin UI

---

## ✨ Features Summary

- ✅ Template-based architecture (reusable)
- ✅ Transaction management
- ✅ Batch processing
- ✅ Error handling & logging
- ✅ Admin dashboard UI
- ✅ CLI command support
- ✅ Cron job ready
- ✅ Statistics & reporting
- ✅ Log download (CSV)
- ✅ Dry-run mode
- ✅ Preview data

---

**Version:** 1.0.0
**Last Updated:** October 2025