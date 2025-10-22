# 🏗️ WBSC Teacher Sync - Architecture Documentation

## 📖 Table of Contents
- [Overview](#overview)
- [System Architecture](#system-architecture)
- [Design Patterns](#design-patterns)
- [Components](#components)
- [Data Flow](#data-flow)
- [Sequence Diagrams](#sequence-diagrams)
- [Extension Guide](#extension-guide)

---

## 🎯 Overview

**Project:** WBSC Teacher Sync System
**Purpose:** Synchronize teacher course data from MariaDB (Moodle) to Oracle Database
**Direction:** MariaDB → Oracle (Reverse from main enrollment sync)
**Pattern:** Template Method + Service Layer

### Key Characteristics
- **Template-based**: Reusable for multiple sync types
- **Transactional**: ACID compliance
- **Batch processing**: Handles large datasets
- **Monitoring**: Comprehensive logging & statistics
- **Multi-interface**: Web UI + CLI

---

## 🏛️ System Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                     Presentation Layer                        │
├───────────────────────────┬──────────────────────────────────┤
│   Web Interface (UI)      │   Command Line Interface (CLI)   │
│   - Dashboard             │   - php spark teacher:sync       │
│   - AJAX Actions          │   - Cron Jobs                    │
│   - Statistics Display    │   - Scheduled Tasks              │
└───────────────┬───────────┴─────────────┬────────────────────┘
                │                         │
                └────────┬────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│                   Controller Layer                           │
│   TeacherSyncController                                      │
│   - Route handling                                           │
│   - Request validation                                       │
│   - Response formatting                                      │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│                    Service Layer                             │
│   ┌──────────────────────────────────────────────────────┐ │
│   │  BaseSyncService (Template)                          │ │
│   │  - sync()              → Main orchestration          │ │
│   │  - beforeSync()        → Pre-validation              │ │
│   │  - afterSync()         → Post-processing             │ │
│   │  - createSyncLog()     → Logging                     │ │
│   └──────────────────────────────────────────────────────┘ │
│   ┌──────────────────────────────────────────────────────┐ │
│   │  TeacherSyncService (Implementation)                 │ │
│   │  - extractSourceData() → Read from MariaDB          │ │
│   │  - clearTargetData()   → Truncate Oracle            │ │
│   │  - loadTargetData()    → Batch insert to Oracle     │ │
│   └──────────────────────────────────────────────────────┘ │
└────────────────────────┬────────────────────────────────────┘
                         │
        ┌────────────────┼────────────────┐
        │                │                │
        ▼                ▼                ▼
┌───────────────┐ ┌──────────────┐ ┌─────────────┐
│  MariaDB      │ │  Oracle      │ │  Log Model  │
│  (Source)     │ │  (Target)    │ │  (Tracking) │
│               │ │              │ │             │
│  View:        │ │  Table:      │ │  Table:     │
│  vw_course_   │ │  COURSE_     │ │  teacher_   │
│  teacher_     │ │  TEACHER_    │ │  sync_logs  │
│  detailed     │ │  DETAILED    │ │             │
└───────────────┘ └──────────────┘ └─────────────┘
```

---

## 🎨 Design Patterns

### 1. Template Method Pattern

**BaseSyncService** กำหนด skeleton ของ sync algorithm:

```php
abstract class BaseSyncService {
    // Template method
    public function sync() {
        $this->beforeSync();
        $data = $this->extractSourceData();    // Abstract
        $this->clearTargetData();              // Abstract
        $this->loadTargetData($data);          // Abstract
        $this->afterSync($data);
    }

    // Hooks
    protected function beforeSync() { }
    protected function afterSync($data) { }

    // Abstract methods (must implement)
    abstract protected function extractSourceData();
    abstract protected function clearTargetData();
    abstract protected function loadTargetData(array $data);
}
```

**Benefits:**
- ✅ Consistent workflow across all sync types
- ✅ Easy to extend for new sync operations
- ✅ DRY principle (Don't Repeat Yourself)
- ✅ Centralized error handling

### 2. Service Layer Pattern

Business logic แยกออกจาก Controller:

```
Controller (thin)
    ↓
Service (thick)
    ↓
Model/Database
```

**Benefits:**
- ✅ Testable business logic
- ✅ Reusable across interfaces (Web + CLI)
- ✅ Single Responsibility
- ✅ Easier maintenance

### 3. Repository Pattern (Implicit)

Models act as data access layer:

```php
TeacherSyncLogModel
    ↓
Database (teacher_sync_logs)
```

---

## 🧩 Components

### 1. BaseSyncService (Template)

**Location:** `app/Services/Sync/BaseSyncService.php`

**Responsibilities:**
- Define sync workflow template
- Transaction management
- Error handling
- Logging infrastructure
- Statistics calculation

**Key Methods:**
```php
sync()                  // Main orchestration
beforeSync()            // Pre-flight checks hook
afterSync()             // Post-processing hook
createSyncLog()         // Create log entry
updateSyncLog()         // Update log status
handleError()           // Error handling
getStatistics()         // Get sync statistics
```

### 2. TeacherSyncService (Implementation)

**Location:** `app/Services/Sync/TeacherSyncService.php`

**Responsibilities:**
- Extract data from MariaDB view
- Clear Oracle target table
- Batch insert to Oracle
- Data validation
- Custom business logic

**Configuration:**
```php
$sourceView = 'wbsc_lms_2020.vw_course_teacher_detailed';
$targetTable = 'WBSC.COURSE_TEACHER_DETAILED';
$batchSize = 1000;
```

### 3. TeacherSyncController

**Location:** `app/Controllers/Admin/TeacherSyncController.php`

**Responsibilities:**
- HTTP request handling
- AJAX endpoint management
- Response formatting
- UI data preparation

**Endpoints:**
- `GET /admin/teacher-sync` → Dashboard
- `POST /admin/teacher-sync/execute-sync` → Run sync
- `GET /admin/teacher-sync/get-statistics` → Get stats
- `GET /admin/teacher-sync/get-history` → Get logs
- `GET /admin/teacher-sync/download-log/{id}` → CSV export

### 4. TeacherSyncLogModel

**Location:** `app/Models/Sync/TeacherSyncLogModel.php`

**Responsibilities:**
- Database operations for logs
- Query optimization
- Statistics aggregation
- Log cleanup

**Schema:**
```sql
CREATE TABLE teacher_sync_logs (
    id INT PRIMARY KEY,
    sync_type VARCHAR(50),
    status ENUM('started', 'completed', 'failed'),
    result_data JSON,
    started_at DATETIME,
    completed_at DATETIME,
    created_at DATETIME,
    updated_at DATETIME
);
```

### 5. TeacherSync Command

**Location:** `app/Commands/TeacherSync.php`

**Responsibilities:**
- CLI interface
- Cron job execution
- Progress display
- Options handling

**Usage:**
```bash
php spark teacher:sync [--dry-run] [--force] [--verbose]
```

---

## 🔄 Data Flow

### Complete Sync Flow

```
┌─────────────────────────────────────────────────────────────┐
│ 1. INITIALIZATION                                           │
│    - Load configuration                                     │
│    - Connect to databases                                   │
│    - Create log entry (status: started)                     │
└────────────┬────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. VALIDATION (beforeSync)                                  │
│    - Check source view exists                               │
│    - Check target table exists                              │
│    - Verify connections                                     │
└────────────┬────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. EXTRACT (extractSourceData)                              │
│    MariaDB Query:                                           │
│    SELECT * FROM wbsc_lms_2020.vw_course_teacher_detailed   │
│                                                              │
│    Result: Array of records                                 │
└────────────┬────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. CLEAR (clearTargetData)                                  │
│    Oracle Query:                                            │
│    TRUNCATE TABLE WBSC.COURSE_TEACHER_DETAILED              │
│                                                              │
│    Result: Count of deleted records                         │
└────────────┬────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. LOAD (loadTargetData)                                    │
│    Batch Processing:                                        │
│    FOR EACH batch (1000 records):                           │
│        BEGIN TRANSACTION                                    │
│        INSERT INTO WBSC.COURSE_TEACHER_DETAILED VALUES(...) │
│        COMMIT                                               │
│                                                              │
│    Result: Count of inserted records                        │
└────────────┬────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────┐
│ 6. FINALIZE (afterSync)                                     │
│    - Calculate statistics                                   │
│    - Update log (status: completed)                         │
│    - Return result                                          │
└─────────────────────────────────────────────────────────────┘
```

### Error Handling Flow

```
Any Step Fails
    ↓
Try-Catch Block
    ↓
├─→ Rollback Transaction (if active)
├─→ Log Error Details
├─→ Update Log (status: failed)
└─→ Return Error Response
```

---

## 📊 Sequence Diagrams

### Web UI Sync Sequence

```
User → Controller → Service → MariaDB → Oracle → Log
  │         │          │          │        │       │
  │ Click   │          │          │        │       │
  │ Sync    │          │          │        │       │
  ├────────>│          │          │        │       │
  │         │ sync()   │          │        │       │
  │         ├─────────>│          │        │       │
  │         │          │ Query    │        │       │
  │         │          ├─────────>│        │       │
  │         │          │<─────────┤        │       │
  │         │          │ Records  │        │       │
  │         │          │ TRUNCATE │        │       │
  │         │          ├─────────────────>│        │
  │         │          │<─────────────────┤        │
  │         │          │ INSERT   │        │       │
  │         │          ├─────────────────>│        │
  │         │          │<─────────────────┤        │
  │         │          │ Save Log │        │       │
  │         │          ├─────────────────────────>│
  │         │          │<─────────────────────────┤
  │         │<─────────┤          │        │       │
  │         │ Result   │          │        │       │
  │<────────┤          │          │        │       │
  │ JSON    │          │          │        │       │
```

### CLI Sync Sequence

```
Cron → Command → Service → Database
  │       │         │          │
  │ Schedule       │          │
  ├──────>│         │          │
  │       │ Execute │          │
  │       ├────────>│          │
  │       │         │ sync()   │
  │       │         ├─────────>│
  │       │         │<─────────┤
  │       │<────────┤          │
  │       │ Log     │          │
  │       │ to file │          │
```

---

## 🔧 Extension Guide

### Adding New Sync Type

**Example: Student Enrollment Sync**

#### Step 1: Create Service

```php
// app/Services/Sync/StudentSyncService.php
namespace App\Services\Sync;

class StudentSyncService extends BaseSyncService
{
    protected string $sourceView = 'wbsc_lms_2020.vw_student_enrollment';
    protected string $targetTable = 'WBSC.STUDENT_ENROLLMENT';

    protected function getSyncType(): string {
        return 'student_sync';
    }

    protected function extractSourceData(): array {
        // Custom query logic
        $query = "SELECT * FROM {$this->sourceView}";
        return $this->sourceDb->query($query)->getResultArray();
    }

    protected function clearTargetData(): int {
        // Custom clear logic
        $this->targetDb->query("TRUNCATE TABLE {$this->targetTable}");
        return $this->targetDb->affectedRows();
    }

    protected function loadTargetData(array $data): int {
        // Custom insert logic
        $count = 0;
        foreach ($data as $row) {
            $this->targetDb->table($this->targetTable)->insert($row);
            $count++;
        }
        return $count;
    }
}
```

#### Step 2: Create Controller

```php
// app/Controllers/Admin/StudentSyncController.php
namespace App\Controllers\Admin;

class StudentSyncController extends BaseController
{
    protected StudentSyncService $syncService;

    public function index() {
        return view('admin/student_sync/index');
    }

    public function executeSync() {
        $result = $this->syncService->sync();
        return $this->response->setJSON($result);
    }
}
```

#### Step 3: Create Command

```php
// app/Commands/StudentSync.php
namespace App\Commands;

class StudentSync extends BaseCommand
{
    protected $name = 'student:sync';

    public function run(array $params) {
        $service = new StudentSyncService();
        $result = $service->sync();
        // Display result
    }
}
```

#### Step 4: Add Routes

```php
// app/Config/Routes.php
$routes->group('admin/student-sync', function($routes) {
    $routes->get('/', 'StudentSyncController::index');
    $routes->post('execute-sync', 'StudentSyncController::executeSync');
});
```

### Customization Points

#### 1. Add Custom Validation

```php
protected function beforeSync(array $options): void
{
    parent::beforeSync($options);

    // Custom validation
    if ($this->getSourceRecordCount() > 100000) {
        throw new Exception('Too many records for single sync');
    }
}
```

#### 2. Add Post-Processing

```php
protected function afterSync(array $data): void
{
    parent::afterSync($data);

    // Send notification
    $this->sendNotification($data);

    // Update cache
    $this->refreshCache();
}
```

#### 3. Custom Batch Size

```php
public function __construct()
{
    parent::__construct();
    $this->config['batch_size'] = 500; // Smaller batches
}
```

---

## 📈 Performance Considerations

### 1. Batch Processing
- Process 1000 records per transaction
- Prevents memory overflow
- Faster than single inserts

### 2. Index Strategy
```sql
-- Source (MariaDB)
CREATE INDEX idx_course_teacher ON vw_course_teacher_detailed(course_id, username);

-- Target (Oracle)
CREATE INDEX idx_teacher_course ON COURSE_TEACHER_DETAILED(COURSE_ID, USERNAME);
CREATE INDEX idx_teacher_term ON COURSE_TEACHER_DETAILED(CAT_TERM_ID);
```

### 3. Connection Pooling
```php
// Persistent connections
$this->oracle['pConnect'] = true;
```

### 4. Memory Management
```php
// Process in chunks
unset($data);
gc_collect_cycles();
```

---

## 🔐 Security Considerations

1. **SQL Injection**: Use parameterized queries
2. **Authentication**: Protect admin routes
3. **Authorization**: Role-based access
4. **Logging**: Sensitive data filtering
5. **Error Messages**: Don't expose DB details

---

## 📚 References

- [CodeIgniter 4 Documentation](https://codeigniter.com/user_guide/)
- [Template Method Pattern](https://refactoring.guru/design-patterns/template-method)
- [Service Layer Pattern](https://martinfowler.com/eaaCatalog/serviceLayer.html)

---

**Version:** 1.0.0
**Architecture:** Template Method + Service Layer
**Created:** October 2025