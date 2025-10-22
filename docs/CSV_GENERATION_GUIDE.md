# 📝 CSV Generation Guide - Teacher Sync

## 🎯 Overview

ระบบ CSV Generation ทำหน้าที่:
1. เปรียบเทียบข้อมูลที่ sync ไปยัง Oracle กับข้อมูลจริงในระบบทะเบียน
2. สร้างไฟล์ CSV สำหรับ Moodle Flat File Enrolment Plugin
3. แสดง Statistics การเปรียบเทียบ (Add/Del/Match)

---

## 📦 ไฟล์ที่ต้องสร้าง/แก้ไข

### 1. ไฟล์ใหม่ (3 ไฟล์)

| # | ไฟล์ | ตำแหน่ง |
|---|------|---------|
| 1 | `CsvGeneratorService.php` | `app/Services/Sync/` |
| 2 | `teacher-sync-extended.js` | เพิ่มใน `app/Views/admin/teacher_sync/index.php` |
| 3 | `COURSE_TEACHER_COMPARE` View | Oracle Database (มีแล้ว) |

### 2. ไฟล์ที่ต้องแก้ไข (3 ไฟล์)

| # | ไฟล์ | สิ่งที่แก้ไข |
|---|------|--------------|
| 1 | `TeacherSyncController.php` | เพิ่ม 4 methods ใหม่ |
| 2 | `Routes.php` | เพิ่ม 4 routes ใหม่ |
| 3 | `index.php` (View) | เพิ่ม UI components |

---

## 🛠️ Installation Steps

### Step 1: สร้าง CsvGeneratorService

**Location:** `app/Services/Sync/CsvGeneratorService.php`

คัดลอก code จาก artifact `csv_generator_service`

### Step 2: แก้ไข TeacherSyncController

**Location:** `app/Controllers/Admin/TeacherSyncController.php`

เพิ่ม 4 methods ใหม่:
- `generateCsv()` - สร้างไฟล์ CSV
- `getComparisonStats()` - ดูสถิติการเปรียบเทียบ
- `previewCsv()` - แสดงตัวอย่างไฟล์ CSV
- `downloadCsv()` - ดาวน์โหลดไฟล์ CSV

### Step 3: แก้ไข Routes

**Location:** `app/Config/Routes.php`

เพิ่ม routes ใหม่:
```php
$routes->post('generate-csv', 'TeacherSyncController::generateCsv');
$routes->get('get-comparison-stats', 'TeacherSyncController::getComparisonStats');
$routes->get('preview-csv', 'TeacherSyncController::previewCsv');
$routes->get('download-csv', 'TeacherSyncController::downloadCsv');
```

### Step 4: แก้ไข View (index.php)

**Location:** `app/Views/admin/teacher_sync/index.php`

เพิ่ม 3 ส่วน:
1. **ปุ่ม Generate CSV** (ข้างๆ ปุ่ม Start Sync)
2. **Comparison Statistics Section** (แสดง Add/Del/Match)
3. **JavaScript code** (จาก artifact `teacher_sync_js_extended`)

### Step 5: แก้ไข .env

เพิ่ม CSV file path (ถ้ายังไม่มี):

```ini
# CSV File Path for Moodle Flat File Plugin
moodle.csv.filePath = '/var/www/html/wbsc-app/writable/uploads/sync-data.csv'
```

หรือถ้าใช้ path ของ Moodle จริง:
```ini
moodle.csv.filePath = '/VolFreenas/wbsc2021/moodledata/enroll_files/sync-data.csv'
```

### Step 6: สร้าง Directory สำหรับ CSV

```bash
# ใน Docker container
docker exec -it php83-web bash

# สร้าง directory
mkdir -p /var/www/html/wbsc-app/writable/uploads
chmod 755 /var/www/html/wbsc-app/writable/uploads

# หรือถ้าใช้ Moodle path
mkdir -p /VolFreenas/wbsc2021/moodledata/enroll_files
chmod 755 /VolFreenas/wbsc2021/moodledata/enroll_files
```

### Step 7: Restart Container

```bash
docker-compose restart
```

---

## 🚀 Usage

### Web UI

#### 1. เข้า Dashboard

```
http://localhost:8083/wbsc-app/admin/teacher-sync
```

#### 2. ดู Comparison Statistics

- คลิก **"Refresh Statistics"** เพื่อดูสถิติการเปรียบเทียบ
- จะแสดง:
  - **Add** - จำนวนอาจารย์ที่ต้องเพิ่มเข้า Moodle
  - **Del** - จำนวนอาจารย์ที่ต้องลบออกจาก Moodle
  - **Match** - จำนวนอาจารย์ที่ตรงกัน

#### 3. Generate CSV

1. คลิก **"Generate CSV"**
2. รอ process เสร็จ (2-10 วินาที)
3. ดูข้อมูลไฟล์ที่สร้าง:
   - จำนวน records
   - ขนาดไฟล์
   - เวลาที่ใช้

#### 4. Preview CSV

- คลิก **"Preview"** เพื่อดูตัวอย่าง 20 บรรทัดแรก

#### 5. Download CSV

- คลิก **"Download CSV"** เพื่อดาวน์โหลดไฟล์

### CLI

```bash
# ใน Docker container
cd /var/www/html/wbsc-app

# สร้างคำสั่ง CLI ใหม่
php spark make:command GenerateCsv

# รัน command (ถ้าสร้างแล้ว)
php spark csv:generate
```

---

## 📊 CSV File Format

### Format: Moodle Flat File

```csv
add,editingteacher,1939990002827,2568-2-1093518
add,editingteacher,147933989,2568-2-1093714
```

### Structure

| Column | Description | Example |
|--------|-------------|---------|
| 1 | Action | `add` / `del` |
| 2 | Role | `editingteacher` / `teacher` |
| 3 | User ID Number | `1939990002827` (รหัสบัตรประชาชน) |
| 4 | Course ID Number | `2568-2-1093518` (รหัสวิชา) |

---

## 🔍 Oracle View - COURSE_TEACHER_COMPARE

### Purpose

เปรียบเทียบข้อมูล:
- **Left Side**: ข้อมูลจากระบบทะเบียน (`SDUEDU.VW_WBSC_COURSE_TEACHER682`)
- **Right Side**: ข้อมูลที่ sync ไป Oracle (`WBSC.COURSE_TEACHER_DETAILED`)

### Actions

| Action | Meaning | Description |
|--------|---------|-------------|
| `Add` | เพิ่ม | มีในระบบทะเบียน แต่ไม่มีใน Moodle |
| `Del` | ลบ | มีใน Moodle แต่ไม่มีในระบบทะเบียน |
| `Match` | ตรงกัน | มีทั้งสองฝั่ง ข้อมูลตรงกัน |

### Query Example

```sql
-- ดูข้อมูลที่ต้อง Add
SELECT *
FROM WBSC.COURSE_TEACHER_COMPARE
WHERE ACTION = 'Add'
AND CITIZEN_CODE IS NOT NULL
LIMIT 10;

-- นับจำนวนแต่ละ action
SELECT ACTION, COUNT(*) as count
FROM WBSC.COURSE_TEACHER_COMPARE
GROUP BY ACTION;
```

---

## 🔄 Complete Workflow

```
┌─────────────────┐
│ 1. Start Sync   │
│ (MariaDB→Oracle)│
└────────┬────────┘
         │
         ▼
┌─────────────────────────┐
│ 2. View Comparison      │
│ Statistics              │
│ (Add/Del/Match counts)  │
└────────┬────────────────┘
         │
         ▼
┌─────────────────┐
│ 3. Generate CSV │
│ (Add actions)   │
└────────┬────────┘
         │
         ▼
┌─────────────────────────┐
│ 4. Download/Preview CSV │
│ sync-data.csv           │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────────┐
│ 5. Upload to Moodle         │
│ Flat File Enrolment Plugin  │
└─────────────────────────────┘
         │
         ▼
┌──────────────────────────┐
│ 6. Run Moodle Cron       │
│ Process enrolments       │
└──────────────────────────┘
```

---

## 🧪 Testing

### Test 1: Generate CSV

```bash
# ใน Docker container
cd /var/www/html/wbsc-app

# เข้า PHP interactive
php -a

# Run code
$csvGenerator = new \App\Services\Sync\CsvGeneratorService();
$result = $csvGenerator->generateCsv();
print_r($result);
```

### Test 2: Check Comparison Stats

```bash
# ใน Docker container
mysql -h 10.202.1.30 -u WBSC -p'wbsc@sdu' EDUPRD

# ใน Oracle (ใช้ SQL*Plus หรือ DBeaver)
SELECT ACTION, COUNT(*) as count
FROM WBSC.COURSE_TEACHER_COMPARE
GROUP BY ACTION;
```

### Test 3: Verify CSV Format

```bash
# ดูไฟล์ CSV
cat /var/www/html/wbsc-app/writable/uploads/sync-data.csv

# หรือ
head -20 /var/www/html/wbsc-app/writable/uploads/sync-data.csv
```

Expected output:
```csv
add,editingteacher,1939990002827,2568-2-1093518
add,editingteacher,147933989,2568-2-1093714
```

---

## 🐛 Troubleshooting

### Problem 1: View not found

```
Error: Table 'WBSC.COURSE_TEACHER_COMPARE' doesn't exist
```

**Solution:**
```sql
-- ตรวจสอบว่า view มีหรือไม่
SELECT * FROM all_views WHERE view_name = 'COURSE_TEACHER_COMPARE';

-- ถ้าไม่มี ให้สร้าง view จากไฟล์ COURSE_TEACHER_COMPARE.sql
```

### Problem 2: Permission denied

```
Error: Failed to write CSV file
```

**Solution:**
```bash
# เช็ค permissions
ls -la /var/www/html/wbsc-app/writable/uploads/

# แก้ permissions
chmod 755 /var/www/html/wbsc-app/writable/uploads
chmod 644 /var/www/html/wbsc-app/writable/uploads/sync-data.csv
```

### Problem 3: No data in CSV

```
Message: No data to sync (no ADD actions found)
```

**Solution:**
- ตรวจสอบว่ามีข้อมูลใน view: `SELECT * FROM WBSC.COURSE_TEACHER_COMPARE WHERE ACTION = 'Add'`
- ตรวจสอบว่า sync ข้อมูลไปยัง Oracle แล้ว
- ตรวจสอบว่า CITIZEN_CODE ไม่เป็น NULL

### Problem 4: Oracle connection error

```
Error: ORA-12154: TNS:could not resolve the connect identifier
```

**Solution:**
- ตรวจสอบ Oracle connection ใน .env
- ทดสอบ connection: `php spark db:table WBSC.COURSE_TEACHER_COMPARE`

---

## 📈 Performance

### Expected Performance

| Metric | Value |
|--------|-------|
| Query Time | < 5 seconds |
| CSV Generation | < 2 seconds |
| File Write | < 1 second |
| **Total Duration** | **< 10 seconds** |

### For 1,000 Records

- CSV Size: ~50-100 KB
- Memory Usage: < 16 MB
- Disk Space: Minimal

---

## 🔐 Security

### File Permissions

```bash
# Directory: 755 (rwxr-xr-x)
chmod 755 /var/www/html/wbsc-app/writable/uploads

# File: 644 (rw-r--r--)
chmod 644 /var/www/html/wbsc-app/writable/uploads/sync-data.csv
```

### Access Control

- ✅ CSV file อยู่นอก `public/` folder
- ✅ ดาวน์โหลดผ่าน Controller เท่านั้น
- ✅ ตรวจสอบ authentication ก่อนดาวน์โหลด (ถ้ามี)

---

## 📝 Next Steps

1. ✅ สร้างไฟล์ทั้งหมดตามขั้นตอน
2. ✅ ทดสอบ Generate CSV ผ่าน Web UI
3. ✅ ตรวจสอบ CSV format ถูกต้อง
4. 📤 Upload CSV ไป Moodle Flat File directory
5. ⚙️ ตั้งค่า Moodle Flat File Plugin
6. 🔄 รัน Moodle Cron เพื่อ process enrolments
7. ✅ ตรวจสอบว่าอาจารย์ถูกเพิ่มเข้ารายวิชาแล้ว

---

## 🆘 Support

หากมีปัญหา:
1. เช็ค logs: `writable/logs/log-*.log`
2. เช็ค Oracle view: `SELECT * FROM WBSC.COURSE_TEACHER_COMPARE`
3. ทดสอบ Oracle connection
4. ตรวจสอบ file permissions

---

**Version:** 1.2.0
**Feature:** CSV Generation from Comparison
**Updated:** October 2025