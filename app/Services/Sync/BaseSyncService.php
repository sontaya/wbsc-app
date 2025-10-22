<?php

namespace App\Services\Sync;

use CodeIgniter\Database\BaseConnection;
use Exception;

/**
 * Base Sync Service Template
 * Template สำหรับการ Sync ข้อมูลระหว่างระบบ
 *
 * การใช้งาน: Extend class นี้และ implement abstract methods
 */
abstract class BaseSyncService
{
    protected BaseConnection $sourceDb;
    protected BaseConnection $targetDb;
    protected $logModel;
    protected array $config;
    protected string $syncType;

    public function __construct()
    {
        $this->loadConfig();
        $this->initializeDatabases();
    }

    /**
     * Load configuration
     */
    protected function loadConfig(): void
    {
        $this->config = [
            'source_db' => 'default',  // MariaDB
            'target_db' => 'oracle',   // Oracle
            'batch_size' => 1000,
            'max_retries' => 3,
        ];
    }

    /**
     * Initialize database connections
     */
    protected function initializeDatabases(): void
    {
        $this->sourceDb = \Config\Database::connect($this->config['source_db']);
        $this->targetDb = \Config\Database::connect($this->config['target_db']);
    }

    /**
     * Main sync process
     *
     * @param array $options Additional options
     * @return array Result with statistics
     */
    public function sync(array $options = []): array
    {
        $startTime = microtime(true);
        $logId = $this->createSyncLog('started');

        try {
            // Step 1: Validate
            $this->beforeSync($options);

            // Step 2: Extract data from source
            $sourceData = $this->extractSourceData();

            if (empty($sourceData)) {
                throw new Exception('No data found in source');
            }

            // Step 3: Clear target data
            $deletedCount = $this->clearTargetData();

            // Step 4: Load data to target
            $insertedCount = $this->loadTargetData($sourceData);

            // Step 5: Post-processing
            $this->afterSync($sourceData);

            // Calculate statistics
            $duration = round(microtime(true) - $startTime, 2);

            $result = [
                'status' => 'success',
                'log_id' => $logId,
                'statistics' => [
                    'total_records' => count($sourceData),
                    'deleted_records' => $deletedCount,
                    'inserted_records' => $insertedCount,
                    'duration_seconds' => $duration,
                ],
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            $this->updateSyncLog($logId, 'completed', $result);

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
     * Extract data from source database
     * Must be implemented by child class
     *
     * @return array Source data
     */
    abstract protected function extractSourceData(): array;

    /**
     * Clear data in target database
     * Must be implemented by child class
     *
     * @return int Number of deleted records
     */
    abstract protected function clearTargetData(): int;

    /**
     * Load data to target database
     * Must be implemented by child class
     *
     * @param array $data Data to load
     * @return int Number of inserted records
     */
    abstract protected function loadTargetData(array $data): int;

    /**
     * Get sync type name
     * Must be implemented by child class
     *
     * @return string Sync type
     */
    abstract protected function getSyncType(): string;

    /**
     * Hook: Before sync
     * Override this method for custom validation
     *
     * @param array $options Options
     */
    protected function beforeSync(array $options): void
    {
        // Override if needed
    }

    /**
     * Hook: After sync
     * Override this method for post-processing
     *
     * @param array $data Synced data
     */
    protected function afterSync(array $data): void
    {
        // Override if needed
    }

    /**
     * Create sync log entry
     *
     * @param string $status Initial status
     * @return int Log ID
     */
    protected function createSyncLog(string $status): int
    {
        if (!$this->logModel) {
            return 0;
        }

        return $this->logModel->insert([
            'sync_type' => $this->getSyncType(),
            'status' => $status,
            'started_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update sync log entry
     *
     * @param int $logId Log ID
     * @param string $status New status
     * @param array $result Result data
     */
    protected function updateSyncLog(int $logId, string $status, array $result): void
    {
        if (!$this->logModel || !$logId) {
            return;
        }

        $this->logModel->update($logId, [
            'status' => $status,
            'result_data' => json_encode($result),
            'completed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Handle error
     *
     * @param Exception $e Exception
     */
    protected function handleError(Exception $e): void
    {
        log_message('error', sprintf(
            '[%s] Sync failed: %s in %s:%d',
            $this->getSyncType(),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
    }

    /**
     * Get sync statistics
     *
     * @param int $limit Number of records
     * @return array Statistics
     */
    public function getStatistics(int $limit = 10): array
    {
        if (!$this->logModel) {
            return [];
        }

        return $this->logModel
            ->where('sync_type', $this->getSyncType())
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get last sync info
     *
     * @return array|null Last sync info
     */
    public function getLastSyncInfo(): ?array
    {
        if (!$this->logModel) {
            return null;
        }

        return $this->logModel
            ->where('sync_type', $this->getSyncType())
            ->orderBy('id', 'DESC')
            ->first();
    }
}