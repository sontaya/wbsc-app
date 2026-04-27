<?php

namespace App\Services\Sync;

use App\Models\Sync\StudentSyncLogModel;
use Exception;

/**
 * Student Sync Service (Oracle Compatible Version)
 * ดึงข้อมูลการลงทะเบียนนักศึกษาจาก MariaDB → Oracle (Incremental Sync)
 *
 * Fixed: Oracle OCI8 bind parameter issues
 * - Use Query Builder instead of named parameters
 * - Use positional parameters (?) for raw queries
 */
class StudentSyncService extends BaseSyncService
{
    protected string $sourceView = 'wbsc_lms_2020.vw_course_student_detailed';
    protected string $targetTable = 'WBSC.COURSE_STUDENT_DETAILED';
    protected string $hashColumn = 'RECORD_HASH';

    /**
     * Faculty configuration (cat_term_id)
     */
    // protected array $facultyConfig = [
    //     271 => 'หมวดวิชาศึกษาทั่วไป (2/2568)',
    //     272 => 'คณะครุศาสตร์ (2/2568)',
    //     273 => 'คณะมนุษยศาสตร์และสังคมศาสตร์ (2/2568)',
    //     274 => 'คณะวิทยาศาสตร์และเทคโนโลยี (2/2568)',
    //     275 => 'คณะวิทยาการจัดการ (2/2568)',
    //     276 => 'คณะพยาบาลศาสตร์ (2/2568)',
    //     277 => 'โรงเรียนการเรือน (2/2568)',
    //     278 => 'โรงเรียนการท่องเที่ยวและการบริการ (2/2568)',
    //     279 => 'โรงเรียนกฎหมายและการเมือง (2/2568)',
    // ];

    protected array $facultyConfig = [];

    public function __construct()
    {
        parent::__construct();
        $this->logModel = new StudentSyncLogModel();
        $this->config['batch_size'] = 500;

        $facultyConfig = env('wbsc.studentSyncFacultyConfig');
        if (is_string($facultyConfig) && trim($facultyConfig) !== '') {
            $decoded = json_decode($facultyConfig, true);
            if (is_array($decoded)) {
                $normalized = [];
                foreach ($decoded as $facultyId => $facultyName) {
                    $normalized[(int) $facultyId] = (string) $facultyName;
                }

                $this->facultyConfig = $normalized;
            }
        }
    }

    protected function getSyncType(): string
    {
        return 'student_sync';
    }

    /**
     * Main sync with faculty selection
     */
    public function sync(array $options = []): array
    {
        $faculties = $options['faculties'] ?? array_keys($this->facultyConfig);

        if (!is_array($faculties)) {
            $faculties = [$faculties];
        }

        log_message('info', sprintf(
            '[StudentSync] Starting sync for %d faculties: %s',
            count($faculties),
            implode(', ', $faculties)
        ));

        $startTime = microtime(true);
        $logId = $this->createSyncLog('started');

        try {
            $this->beforeSync($options);

            $totalStats = [
                'total_records' => 0,
                'inserted_records' => 0,
                'updated_records' => 0,
                'deleted_records' => 0,
                'unchanged_records' => 0,
                'faculties_synced' => 0,
                'faculty_details' => [],
            ];

            foreach ($faculties as $facultyId) {
                if (!isset($this->facultyConfig[$facultyId])) {
                    log_message('warning', "[StudentSync] Invalid faculty ID: {$facultyId}");
                    continue;
                }

                log_message('info', sprintf(
                    '[StudentSync] Syncing faculty %d: %s',
                    $facultyId,
                    $this->facultyConfig[$facultyId]
                ));

                $facultyStats = $this->syncFaculty($facultyId);

                $totalStats['total_records'] += $facultyStats['total_records'];
                $totalStats['inserted_records'] += $facultyStats['inserted_records'];
                $totalStats['updated_records'] += $facultyStats['updated_records'];
                $totalStats['deleted_records'] += $facultyStats['deleted_records'];
                $totalStats['unchanged_records'] += $facultyStats['unchanged_records'];
                $totalStats['faculties_synced']++;
                $totalStats['faculty_details'][$facultyId] = $facultyStats;
            }

            $duration = round(microtime(true) - $startTime, 2);
            $totalStats['duration_seconds'] = $duration;

            $result = [
                'status' => 'success',
                'log_id' => $logId,
                'statistics' => $totalStats,
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            $this->updateSyncLog($logId, 'completed', $result);

            log_message('info', sprintf(
                '[StudentSync] Completed: %d faculties, %d inserted, %d updated, %d deleted, %d unchanged in %ss',
                $totalStats['faculties_synced'],
                $totalStats['inserted_records'],
                $totalStats['updated_records'],
                $totalStats['deleted_records'],
                $totalStats['unchanged_records'],
                $duration
            ));

            return $result;
        } catch (Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);

            $result = [
                'status' => 'error',
                'log_id' => $logId,
                'error' => $e->getMessage(),
                'duration_seconds' => $duration,
            ];

            $this->updateSyncLog($logId, 'failed', $result);
            $this->handleError($e);

            return $result;
        }
    }

    /**
     * Sync single faculty
     */
    protected function syncFaculty(int $facultyId): array
    {
        $stats = [
            'faculty_id' => $facultyId,
            'faculty_name' => $this->facultyConfig[$facultyId],
            'total_records' => 0,
            'inserted_records' => 0,
            'updated_records' => 0,
            'deleted_records' => 0,
            'unchanged_records' => 0,
        ];

        $sourceData = $this->extractSourceDataByFaculty($facultyId);
        $stats['total_records'] = count($sourceData);

        if (empty($sourceData)) {
            log_message('info', "[StudentSync] No data for faculty {$facultyId}");
            return $stats;
        }

        $existingData = $this->getExistingDataByFaculty($facultyId);

        $sourceMap = $this->buildRecordMap($sourceData);
        $existingMap = $this->buildRecordMap($existingData);

        $toInsert = [];
        $toUpdate = [];
        $toDelete = [];

        foreach ($sourceMap as $key => $record) {
            if (!isset($existingMap[$key])) {
                $toInsert[] = $record;
            } else {
                if ($record[$this->hashColumn] !== $existingMap[$key][$this->hashColumn]) {
                    $toUpdate[] = $record;
                } else {
                    $stats['unchanged_records']++;
                }
            }
        }

        foreach ($existingMap as $key => $record) {
            if (!isset($sourceMap[$key])) {
                $toDelete[] = $record;
            }
        }

        if (!empty($toInsert)) {
            $stats['inserted_records'] = $this->insertRecords($toInsert);
        }

        if (!empty($toUpdate)) {
            $stats['updated_records'] = $this->updateRecords($toUpdate);
        }

        if (!empty($toDelete)) {
            $stats['deleted_records'] = $this->deleteRecords($toDelete);
        }

        log_message('info', sprintf(
            '[StudentSync] Faculty %d: %d total, %d inserted, %d updated, %d deleted, %d unchanged',
            $facultyId,
            $stats['total_records'],
            $stats['inserted_records'],
            $stats['updated_records'],
            $stats['deleted_records'],
            $stats['unchanged_records']
        ));

        return $stats;
    }

    /**
     * Extract data from source for specific faculty
     * Using positional parameters (?) for compatibility
     *
     * PUBLIC: Can be called from Controller for preview
     */
    public function extractSourceDataByFaculty(int $facultyId): array
    {
        $query = "
            SELECT
                CAT_TYPE,
                CAT_FACULTY,
                CAT_TERM_ID,
                CAT_TERM,
                COURSE_ID,
                COURSE_SHORTNAME,
                COURSE_FULLNAME,
                USERNAME,
                IDNUMBER,
                FIRSTNAME,
                LASTNAME,
                USERROLE,
                STUDENT_GROUPS,
                STD_ENROLL_KEY
            FROM {$this->sourceView}
            WHERE CAT_TERM_ID = ?
            ORDER BY COURSE_ID, USERNAME
        ";

        $result = $this->sourceDb->query($query, [$facultyId]);
        $data = $result->getResultArray();

        foreach ($data as &$row) {
            $row[$this->hashColumn] = $this->calculateRecordHash($row);
        }

        return $data;
    }

    /**
     * Get existing data from target for specific faculty
     * Using Query Builder for Oracle compatibility
     */
    protected function getExistingDataByFaculty(int $facultyId): array
    {
        try {
            // Use Query Builder - Oracle compatible
            return $this->targetDb->table($this->targetTable)
                ->where('CAT_TERM_ID', $facultyId)
                ->get()
                ->getResultArray();
        } catch (Exception $e) {
            log_message('error', '[StudentSync] Get existing data failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Build record map with composite key
     */
    protected function buildRecordMap(array $data): array
    {
        $map = [];
        foreach ($data as $row) {
            $key = $row['COURSE_SHORTNAME'] . '|' . $row['USERNAME'];
            $map[$key] = $row;
        }
        return $map;
    }

    /**
     * Calculate hash for change detection
     */
    protected function calculateRecordHash(array $record): string
    {
        unset($record[$this->hashColumn]);
        ksort($record);
        $str = implode('|', array_map(function ($v) {
            return $v === null ? 'NULL' : (string)$v;
        }, $record));
        return md5($str);
    }

    /**
     * Insert new records
     */
    protected function insertRecords(array $records): int
    {
        if (empty($records)) {
            return 0;
        }

        $inserted = 0;
        $batches = array_chunk($records, $this->config['batch_size']);

        foreach ($batches as $batch) {
            try {
                $this->targetDb->transStart();

                foreach ($batch as $record) {
                    $sql = $this->buildInsertSQL($record);
                    $this->targetDb->query($sql);
                    $inserted++;
                }

                $this->targetDb->transComplete();

                if ($this->targetDb->transStatus() === false) {
                    throw new Exception('Insert transaction failed');
                }
            } catch (Exception $e) {
                $this->targetDb->transRollback();
                log_message('error', '[StudentSync] Insert failed: ' . $e->getMessage());
                throw $e;
            }
        }

        return $inserted;
    }

    /**
     * Update existing records
     */
    protected function updateRecords(array $records): int
    {
        if (empty($records)) {
            return 0;
        }

        $updated = 0;
        $batches = array_chunk($records, $this->config['batch_size']);

        foreach ($batches as $batch) {
            try {
                $this->targetDb->transStart();

                foreach ($batch as $record) {
                    $sql = $this->buildUpdateSQL($record);
                    $this->targetDb->query($sql);
                    $updated++;
                }

                $this->targetDb->transComplete();

                if ($this->targetDb->transStatus() === false) {
                    throw new Exception('Update transaction failed');
                }
            } catch (Exception $e) {
                $this->targetDb->transRollback();
                log_message('error', '[StudentSync] Update failed: ' . $e->getMessage());
                throw $e;
            }
        }

        return $updated;
    }

    /**
     * Delete records
     */
    protected function deleteRecords(array $records): int
    {
        if (empty($records)) {
            return 0;
        }

        $deleted = 0;

        try {
            $this->targetDb->transStart();

            foreach ($records as $record) {
                $sql = $this->buildDeleteSQL($record);
                $this->targetDb->query($sql);
                $deleted++;
            }

            $this->targetDb->transComplete();

            if ($this->targetDb->transStatus() === false) {
                throw new Exception('Delete transaction failed');
            }
        } catch (Exception $e) {
            $this->targetDb->transRollback();
            log_message('error', '[StudentSync] Delete failed: ' . $e->getMessage());
            throw $e;
        }

        return $deleted;
    }

    /**
     * Build INSERT SQL
     */
    private function buildInsertSQL(array $row): string
    {
        unset($row[$this->hashColumn]);

        $columns = [];
        $values = [];

        foreach ($row as $key => $value) {
            $columns[] = strtoupper($key);

            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $escaped = str_replace("'", "''", $value);
                $values[] = "'" . $escaped . "'";
            }
        }

        $columns[] = $this->hashColumn;
        $values[] = "'" . $this->calculateRecordHash($row) . "'";

        return sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->targetTable,
            implode(', ', $columns),
            implode(', ', $values)
        );
    }

    /**
     * Build UPDATE SQL
     */
    private function buildUpdateSQL(array $row): string
    {
        $courseShortname = $row['COURSE_SHORTNAME'];
        $username = $row['USERNAME'];
        unset($row[$this->hashColumn]);

        $setParts = [];

        foreach ($row as $key => $value) {
            if ($key === 'COURSE_SHORTNAME' || $key === 'USERNAME') {
                continue;
            }

            $column = strtoupper($key);
            if ($value === null) {
                $setParts[] = "{$column} = NULL";
            } else {
                $escaped = str_replace("'", "''", $value);
                $setParts[] = "{$column} = '{$escaped}'";
            }
        }

        $newHash = $this->calculateRecordHash($row);
        $setParts[] = "{$this->hashColumn} = '{$newHash}'";

        return sprintf(
            "UPDATE %s SET %s WHERE COURSE_SHORTNAME = '%s' AND USERNAME = '%s'",
            $this->targetTable,
            implode(', ', $setParts),
            str_replace("'", "''", $courseShortname),
            str_replace("'", "''", $username)
        );
    }

    /**
     * Build DELETE SQL
     */
    private function buildDeleteSQL(array $row): string
    {
        $courseShortname = str_replace("'", "''", $row['COURSE_SHORTNAME']);
        $username = str_replace("'", "''", $row['USERNAME']);

        return sprintf(
            "DELETE FROM %s WHERE COURSE_SHORTNAME = '%s' AND USERNAME = '%s'",
            $this->targetTable,
            $courseShortname,
            $username
        );
    }

    /**
     * Legacy methods for compatibility
     */
    protected function extractSourceData(): array
    {
        $allData = [];
        foreach (array_keys($this->facultyConfig) as $facultyId) {
            $allData = array_merge($allData, $this->extractSourceDataByFaculty($facultyId));
        }
        return $allData;
    }

    protected function clearTargetData(): int
    {
        return 0;
    }

    protected function loadTargetData(array $data): int
    {
        return 0;
    }

    /**
     * Get source record count by faculty
     * Using positional parameter for compatibility
     */
    public function getSourceRecordCountByFaculty(int $facultyId): int
    {
        try {
            $query = "SELECT COUNT(*) as total FROM {$this->sourceView} WHERE CAT_TERM_ID = ?";
            $result = $this->sourceDb->query($query, [$facultyId]);
            $row = $result->getRow();
            return $row->total ?? 0;
        } catch (Exception $e) {
            log_message('error', '[StudentSync] Source count failed for faculty ' . $facultyId . ': ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get target record count by faculty
     * Using Query Builder for Oracle compatibility
     */
    public function getTargetRecordCountByFaculty(int $facultyId): int
    {
        try {
            // Use Query Builder - works perfectly with Oracle
            $count = $this->targetDb->table($this->targetTable)
                ->where('CAT_TERM_ID', $facultyId)
                ->countAllResults();

            return $count;
        } catch (Exception $e) {
            log_message('error', '[StudentSync] Target count failed for faculty ' . $facultyId . ': ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get all faculties configuration
     */
    public function getFacultyConfig(): array
    {
        return $this->facultyConfig;
    }

    /**
     * Get statistics by faculty
     */
    public function getStatisticsByFaculty(int $facultyId, int $limit = 10): array
    {
        if (!$this->logModel) {
            return [];
        }

        return $this->logModel
            ->where('sync_type', $this->getSyncType())
            ->like('result_data', '"faculty_id":' . $facultyId)
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->findAll();
    }
}
