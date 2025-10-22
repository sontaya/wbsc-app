<?php

namespace App\Services\Sync;

use App\Models\Sync\TeacherSyncLogModel;
use Exception;

/**
 * Teacher Sync Service
 * ดึงข้อมูลครูผู้สอนจาก MariaDB → Oracle
 */
class TeacherSyncService extends BaseSyncService
{
    protected string $sourceView = 'wbsc_lms_2020.vw_course_teacher_detailed';
    protected string $targetTable = 'WBSC.COURSE_TEACHER_DETAILED';

    public function __construct()
    {
        parent::__construct();
        $this->logModel = new TeacherSyncLogModel();
    }

    /**
     * Get sync type name
     */
    protected function getSyncType(): string
    {
        return 'teacher_sync';
    }

    /**
     * Extract data from MariaDB view
     *
     * @return array Teacher data
     */
    protected function extractSourceData(): array
    {
        log_message('info', '[TeacherSync] Extracting data from MariaDB view: ' . $this->sourceView);

        $query = "
            SELECT
                CAT_TYPE,
                CAT_FACULTY,
                CAT_TERM_ID,
                CAT_TERM,
                COURSE_ID,
                COURSE_SHORTNAME,
                COURSE_FULLNAME,
                COURSE_CREATE_DATE,
                USERNAME,
                IDNUMBER,
                FIRSTNAME,
                LASTNAME,
                USERROLE
            FROM {$this->sourceView}
            ORDER BY COURSE_ID, USERNAME
        ";

        $result = $this->sourceDb->query($query);
        $data = $result->getResultArray();

        log_message('info', sprintf(
            '[TeacherSync] Extracted %d records from source',
            count($data)
        ));

        return $data;
    }

    /**
     * Clear Oracle target table (TRUNCATE)
     *
     * @return int Number of deleted records
     */
    protected function clearTargetData(): int
    {
        log_message('info', '[TeacherSync] Clearing target table: ' . $this->targetTable);

        try {
            // Count before delete
            $countQuery = "SELECT COUNT(*) as total FROM {$this->targetTable}";
            $result = $this->targetDb->query($countQuery);
            $row = $result->getRow();
            $beforeCount = $row->total ?? 0;

            // Truncate table (fastest way to delete all records)
            $this->targetDb->query("TRUNCATE TABLE {$this->targetTable}");

            log_message('info', sprintf(
                '[TeacherSync] Cleared %d records from target',
                $beforeCount
            ));

            return $beforeCount;

        } catch (Exception $e) {
            log_message('error', '[TeacherSync] Clear failed: ' . $e->getMessage());
            throw new Exception('Failed to clear target table: ' . $e->getMessage());
        }
    }

    /**
     * Load data to Oracle in batches
     *
     * @param array $data Data to load
     * @return int Number of inserted records
     */
    protected function loadTargetData(array $data): int
    {
        log_message('info', '[TeacherSync] Loading data to Oracle table: ' . $this->targetTable);

        $insertedCount = 0;
        $batchSize = $this->config['batch_size'] ?? 1000;
        $batches = array_chunk($data, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            try {
                $this->targetDb->transStart();

                foreach ($batch as $row) {
                    $sql = $this->buildInsertSQL($row);
                    $this->targetDb->query($sql);
                    $insertedCount++;
                }

                $this->targetDb->transComplete();

                if ($this->targetDb->transStatus() === false) {
                    throw new Exception('Transaction failed for batch ' . ($batchIndex + 1));
                }

                log_message('info', sprintf(
                    '[TeacherSync] Batch %d/%d completed (%d records)',
                    $batchIndex + 1,
                    count($batches),
                    count($batch)
                ));

            } catch (Exception $e) {
                $this->targetDb->transRollback();
                log_message('error', sprintf(
                    '[TeacherSync] Batch %d failed: %s',
                    $batchIndex + 1,
                    $e->getMessage()
                ));
                throw $e;
            }
        }

        log_message('info', sprintf(
            '[TeacherSync] Successfully inserted %d records',
            $insertedCount
        ));

        return $insertedCount;
    }

    /**
     * Build INSERT SQL for Oracle
     *
     * @param array $row Data row
     * @return string SQL statement
     */
    private function buildInsertSQL(array $row): string
    {
        $columns = [];
        $values = [];

        foreach ($row as $key => $value) {
            $columns[] = strtoupper($key);

            if ($value === null) {
                $values[] = 'NULL';
            } else {
                // Escape single quotes for Oracle
                $escaped = str_replace("'", "''", $value);
                $values[] = "'" . $escaped . "'";
            }
        }

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->targetTable,
            implode(', ', $columns),
            implode(', ', $values)
        );

        return $sql;
    }

    /**
     * Validate before sync
     *
     * @param array $options Options
     */
    protected function beforeSync(array $options): void
    {
        // Check if source view exists
        if (!$this->checkSourceViewExists()) {
            throw new Exception('Source view does not exist: ' . $this->sourceView);
        }

        // Check if target table exists
        if (!$this->checkTargetTableExists()) {
            throw new Exception('Target table does not exist: ' . $this->targetTable);
        }

        log_message('info', '[TeacherSync] Pre-sync validation passed');
    }

    /**
     * Check if source view exists
     *
     * @return bool True if exists
     */
    private function checkSourceViewExists(): bool
    {
        try {
            $parts = explode('.', $this->sourceView);
            $schema = $parts[0];
            $view = $parts[1];

            $query = "
                SELECT COUNT(*) as count
                FROM information_schema.VIEWS
                WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
            ";

            $result = $this->sourceDb->query($query, [$schema, $view]);
            $row = $result->getRow();

            return ($row->count ?? 0) > 0;

        } catch (Exception $e) {
            log_message('error', '[TeacherSync] View check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if target table exists
     *
     * @return bool True if exists
     */
    private function checkTargetTableExists(): bool
    {
        try {
            $parts = explode('.', $this->targetTable);
            $owner = $parts[0];
            $table = $parts[1];

            $query = "
                SELECT COUNT(*) as count
                FROM all_tables
                WHERE owner = ?
                AND table_name = ?
            ";

            $result = $this->targetDb->query($query, [$owner, $table]);
            $row = $result->getRow();

            return ($row->COUNT ?? 0) > 0;

        } catch (Exception $e) {
            log_message('error', '[TeacherSync] Table check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get source record count
     *
     * @return int Record count
     */
    public function getSourceRecordCount(): int
    {
        try {
            $query = "SELECT COUNT(*) as total FROM {$this->sourceView}";
            $result = $this->sourceDb->query($query);
            $row = $result->getRow();
            return $row->total ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get target record count
     *
     * @return int Record count
     */
    public function getTargetRecordCount(): int
    {
        try {
            $query = "SELECT COUNT(*) as total FROM {$this->targetTable}";
            $result = $this->targetDb->query($query);
            $row = $result->getRow();
            return $row->total ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get preview data from source
     *
     * @param int $limit Limit
     * @return array Preview data
     */
    public function getPreviewData(int $limit = 10): array
    {
        try {
            $query = "SELECT * FROM {$this->sourceView} LIMIT ?";
            $result = $this->sourceDb->query($query, [$limit]);
            return $result->getResultArray();
        } catch (Exception $e) {
            log_message('error', '[TeacherSync] Preview failed: ' . $e->getMessage());
            return [];
        }
    }
}