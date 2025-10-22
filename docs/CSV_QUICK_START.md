# 🚀 Quick Start - CSV Generation

## 📦 ติดตั้งใน 7 Steps

### Step 1: สร้าง CsvGeneratorService.php

```bash
# สร้างไฟล์
touch app/Services/Sync/CsvGeneratorService.php
```

คัดลอก code จาก artifact → `app/Services/Sync/CsvGeneratorService.php`

### Step 2: แก้ไข TeacherSyncController.php

เปิด `app/Controllers/Admin/TeacherSyncController.php`

เพิ่ม 4 methods ก่อน `downloadLog()`:
```php
public function generateCsv() { ... }
public function getComparisonStats() { ... }
public function previewCsv() { ... }
public function downloadCsv() { ... }
```

### Step 3: แก้ไข Routes.php

เปิด `app/Config/Routes.php`

แก้ไข section `admin/teacher-sync`:
```php
$routes->post('generate-csv', 'TeacherSyncController::generateCsv');
$routes->get('get-comparison-stats', 'TeacherSyncController::getComparisonStats');
$routes->get('preview-csv', 'TeacherSyncController::previewCsv');
$routes->get('download-csv', 'TeacherSyncController::downloadCsv');
```

### Step 4: แก้ไข index.php (View)

เปิด `app/Views/admin/teacher_sync/index.php`

#### 4.1 เพิ่มปุ่ม Generate CSV

หาบรรทัด:
```html
<button id="btnSync" class="btn btn-sync">
```

เพิ่มข้างๆ:
```html
<button id="btnGenerateCsv" class="btn btn-generate-csv ms-2">
    <i class="fas fa-file-csv me-2"></i>
    Generate CSV
</button>
```

#### 4.2 เพิ่ม CSS

ใน `<style>` เพิ่ม:
```css
.btn-generate-csv {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    border: none;
    color: white;
    padding: 0.75rem 2rem;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-generate-csv:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    color: white;
}
```

#### 4.3 เพิ่ม Comparison Statistics Section

หลังจาก Statistics Cards เพิ่ม:
```html
<!-- Comparison Statistics -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="stat-card">
            <h5 class="mb-3">
                <i class="fas fa-chart-pie me-2"></i>
                Comparison Statistics
                <small class="text-muted">(Oracle vs Registry System)</small>
            </h5>
            <div class="row">
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-success">
                            <i class="fas fa-plus-circle me-2"></i>
                            <strong>Add (ต้องเพิ่ม)</strong>
                        </span>
                        <span id="statAdd" class="badge bg-success">-</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-danger">
                            <i class="fas fa-minus-circle me-2"></i>
                            <strong>Del (ต้องลบ)</strong>
                        </span>
                        <span id="statDel" class="badge bg-danger">-</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-info">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Match (ตรงกัน)</strong>
                        </span>
                        <span id="statMatch" class="badge bg-info">-</span>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button id="btnRefreshStats" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-sync-alt me-1"></i>
                    Refresh Statistics
                </button>
            </div>
        </div>
    </div>
</div>
```

#### 4.4 เพิ่ม JavaScript

ก่อน `</script>` เพิ่ม code จาก artifact `teacher_sync_js_extended`

### Step 5: แก้ไข .env

```ini
# เพิ่มบรรทัดนี้
moodle.csv.filePath = '/var/www/html/wbsc-app/writable/uploads/sync-data.csv'
```

### Step 6: สร้าง Directory

```bash
# ใน Docker container
docker exec -it php83-web bash
mkdir -p /var/www/html/wbsc-app/writable/uploads
chmod 755 /var/www/html/wbsc-app/writable/uploads
exit
```

### Step 7: Restart Container

```bash
docker-compose restart
```

---

## ✅ ทดสอบ

### 1. เข้า Dashboard

```
http://localhost:8083/wbsc-app/admin/teacher-sync
```

### 2. คลิก "Refresh Statistics"

ควรเห็น:
- Add: X records
- Del: Y records
- Match: Z records

### 3. คลิก "Generate CSV"

รอ 2-10 วินาที ควรเห็น:
- ✅ Success message
- 📊 File statistics
- 📥 Download button

### 4. คลิก "Download CSV"

ไฟล์ `sync-data-YYYYMMDD-HHMMSS.csv` จะถูกดาวน์โหลด

---

## 📝 ตัวอย่างไฟล์ CSV

```csv
add,editingteacher,1939990002827,2568-2-1093518
add,editingteacher,147933989,2568-2-1093714
add,editingteacher,1234567890123,2568-2-1093715
```

**Format:** `action,role,citizen_code,course_shortname`

---

## 🎯 Workflow

```
1. Start Sync           → ซิงค์ข้อมูลจาก MariaDB → Oracle
2. Refresh Statistics   → ดูข้อมูลเปรียบเทียบ
3. Generate CSV         → สร้างไฟล์ CSV
4. Download CSV         → ดาวน์โหลดไฟล์
5. Upload to Moodle     → อัปโหลดไป Moodle Flat File directory
6. Run Moodle Cron      → ประมวลผล enrolments
```

---

## 🐛 หากมีปัญหา

### View ไม่พบ
```sql
-- สร้าง view ใน Oracle
-- ใช้ไฟล์ COURSE_TEACHER_COMPARE.sql
```

### Permission denied
```bash
chmod 755 /var/www/html/wbsc-app/writable/uploads
```

### ไม่มีข้อมูล Add
```sql
-- ตรวจสอบ view
SELECT * FROM WBSC.COURSE_TEACHER_COMPARE WHERE ACTION = 'Add' LIMIT 10;
```

---

**เสร็จแล้ว!** ตอนนี้คุณสามารถ Generate CSV ได้แล้ว 🎉

Next: อัปโหลดไฟล์ไป Moodle และรัน Cron