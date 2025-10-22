<?php

namespace App\Models\Sync;

use CodeIgniter\Model;

/**
 * Teacher Sync Log Model
 * Store sync history and results
 */
class TeacherSyncLogModel extends Model
{
    protected $table = 'teacher_sync_logs';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'sync_type',
        'status',
        'result_data',
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
     *
     * @param string $status Status
     * @param int $limit Limit
     * @return array Logs
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
     *
     * @param int $limit Limit
     * @return array Logs
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
     *
     * @param int $limit Limit
     * @return array Logs
     */
    public function getRecentFailed(int $limit = 5): array
    {
        return $this->where('status', 'failed')
            ->orderBy('completed_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get sync statistics summary
     *
     * @return array Statistics
     */
    public function getStatisticsSummary(): array
    {
        $db = $this->db;

        $query = "
            SELECT
                COUNT(*) as total_syncs,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_syncs,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_syncs,
                AVG(
                    CASE
                        WHEN status = 'completed' AND result_data IS NOT NULL
                        THEN JSON_UNQUOTE(JSON_EXTRACT(result_data, '$.statistics.duration_seconds'))
                        ELSE NULL
                    END
                ) as avg_duration
            FROM {$this->table}
            WHERE sync_type = 'teacher_sync'
        ";

        $result = $db->query($query);
        return $result->getRowArray() ?? [];
    }

    /**
     * Clean old logs (keep last 100)
     *
     * @return int Number of deleted logs
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
 * CREATE TABLE teacher_sync_logs (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     sync_type VARCHAR(50) NOT NULL DEFAULT 'teacher_sync',
 *     status ENUM('started', 'completed', 'failed') NOT NULL,
 *     result_data JSON NULL,
 *     started_at DATETIME NOT NULL,
 *     completed_at DATETIME NULL,
 *     created_at DATETIME NULL,
 *     updated_at DATETIME NULL,
 *     INDEX idx_sync_type (sync_type),
 *     INDEX idx_status (status),
 *     INDEX idx_completed_at (completed_at)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */