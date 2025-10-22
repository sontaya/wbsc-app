<?php

namespace App\Models\Sync;

use CodeIgniter\Model;

/**
 * Student Sync Log Model
 * Store sync history and results for student enrollment sync
 */
class StudentSyncLogModel extends Model
{
    protected $table = 'student_sync_logs';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'sync_type',
        'status',
        'result_data',
        'faculties_synced',
        'started_at',
        'completed_at',
        'created_at',
        'updated_at'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    // Validation
    protected $validationRules = [
        'sync_type' => 'required|string|max_length[50]',
        'status' => 'required|string|in_list[started,completed,failed]',
    ];

    protected $validationMessages = [
        'sync_type' => [
            'required' => 'Sync type is required',
        ],
        'status' => [
            'required' => 'Status is required',
            'in_list' => 'Invalid status value',
        ],
    ];

    /**
     * Get logs by status
     */
    public function getByStatus(string $status, int $limit = 10): array
    {
        return $this->where('status', $status)
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get recent successful syncs
     */
    public function getRecentSuccessful(int $limit = 5): array
    {
        return $this->where('status', 'completed')
            ->orderBy('completed_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get recent failed syncs
     */
    public function getRecentFailed(int $limit = 5): array
    {
        return $this->where('status', 'failed')
            ->orderBy('completed_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get logs by faculty
     */
    public function getByFaculty(int $facultyId, int $limit = 10): array
    {
        return $this->where('sync_type', 'student_sync')
            ->like('result_data', '"faculty_id":' . $facultyId)
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get sync statistics summary
     */
    public function getStatisticsSummary(): array
    {
        $db = $this->db;

        $query = "
            SELECT
                COUNT(*) as total_syncs,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_syncs,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_syncs,
                SUM(CASE WHEN faculties_synced IS NOT NULL THEN faculties_synced ELSE 0 END) as total_faculties_synced,
                AVG(
                    CASE
                        WHEN status = 'completed' AND result_data IS NOT NULL
                        THEN JSON_UNQUOTE(JSON_EXTRACT(result_data, '$.statistics.duration_seconds'))
                        ELSE NULL
                    END
                ) as avg_duration
            FROM {$this->table}
            WHERE sync_type = 'student_sync'
        ";

        $result = $db->query($query);
        return $result->getRowArray() ?? [];
    }

    /**
     * Get aggregated statistics
     */
    public function getAggregatedStats(): array
    {
        $db = $this->db;

        $query = "
            SELECT
                SUM(JSON_UNQUOTE(JSON_EXTRACT(result_data, '$.statistics.total_records'))) as total_records,
                SUM(JSON_UNQUOTE(JSON_EXTRACT(result_data, '$.statistics.inserted_records'))) as total_inserted,
                SUM(JSON_UNQUOTE(JSON_EXTRACT(result_data, '$.statistics.updated_records'))) as total_updated,
                SUM(JSON_UNQUOTE(JSON_EXTRACT(result_data, '$.statistics.deleted_records'))) as total_deleted,
                SUM(JSON_UNQUOTE(JSON_EXTRACT(result_data, '$.statistics.unchanged_records'))) as total_unchanged
            FROM {$this->table}
            WHERE sync_type = 'student_sync'
            AND status = 'completed'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";

        $result = $db->query($query);
        return $result->getRowArray() ?? [];
    }

    /**
     * Clean old logs (keep last 100)
     */
    public function cleanOldLogs(): int
    {
        $keepCount = 100;

        $subquery = $this->builder()
            ->select('id')
            ->orderBy('id', 'DESC')
            ->limit($keepCount)
            ->getCompiledSelect();

        $deleted = $this->builder()
            ->whereNotIn('id', "($subquery)", false)
            ->delete();

        return $deleted;
    }
}

/*
 * Database Migration
 * Run: php spark migrate
 *
 * CREATE TABLE student_sync_logs (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     sync_type VARCHAR(50) NOT NULL DEFAULT 'student_sync',
 *     status ENUM('started', 'completed', 'failed') NOT NULL,
 *     result_data JSON NULL,
 *     faculties_synced INT NULL DEFAULT 0,
 *     started_at DATETIME NOT NULL,
 *     completed_at DATETIME NULL,
 *     created_at DATETIME NULL,
 *     updated_at DATETIME NULL,
 *     INDEX idx_sync_type (sync_type),
 *     INDEX idx_status (status),
 *     INDEX idx_completed_at (completed_at),
 *     INDEX idx_faculties (faculties_synced)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */