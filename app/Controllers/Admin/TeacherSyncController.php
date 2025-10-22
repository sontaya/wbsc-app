<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Sync\TeacherSyncService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Teacher Sync Controller
 * Admin interface for managing teacher data synchronization
 */
class TeacherSyncController extends BaseController
{
    protected TeacherSyncService $syncService;

    public function __construct()
    {
        $this->syncService = new TeacherSyncService();
    }

    /**
     * Main dashboard
     *
     * @return string View
     */
    public function index(): string
    {
        $data = [
            'title' => 'Teacher Sync Management',
            'lastSync' => $this->syncService->getLastSyncInfo(),
            'sourceCount' => $this->syncService->getSourceRecordCount(),
            'targetCount' => $this->syncService->getTargetRecordCount(),
            'recentLogs' => $this->syncService->getStatistics(10),
        ];

        return view('admin/teacher_sync/index', $data);
    }

    /**
     * Execute sync (AJAX)
     *
     * @return ResponseInterface JSON response
     */
    public function executeSync(): ResponseInterface
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Invalid request method'
            ]);
        }

        try {
            // Execute sync
            $result = $this->syncService->sync();

            if ($result['status'] === 'success') {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'Sync completed successfully',
                    'data' => $result
                ]);
            } else {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'message' => $result['error'] ?? 'Sync failed',
                    'data' => $result
                ]);
            }

        } catch (\Exception $e) {
            log_message('error', '[TeacherSync] Execute failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Sync execution error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get statistics (AJAX)
     *
     * @return ResponseInterface JSON response
     */
    public function getStatistics(): ResponseInterface
    {
        try {
            $statistics = [
                'source_count' => $this->syncService->getSourceRecordCount(),
                'target_count' => $this->syncService->getTargetRecordCount(),
                'last_sync' => $this->syncService->getLastSyncInfo(),
            ];

            return $this->response->setJSON([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get sync history (AJAX)
     *
     * @return ResponseInterface JSON response
     */
    public function getHistory(): ResponseInterface
    {
        try {
            $limit = $this->request->getGet('limit') ?? 20;
            $history = $this->syncService->getStatistics($limit);

            return $this->response->setJSON([
                'success' => true,
                'data' => $history
            ]);

        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Failed to get history: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Preview source data (AJAX)
     *
     * @return ResponseInterface JSON response
     */
    public function previewData(): ResponseInterface
    {
        try {
            $limit = $this->request->getGet('limit') ?? 10;
            $preview = $this->syncService->getPreviewData($limit);

            return $this->response->setJSON([
                'success' => true,
                'data' => $preview,
                'count' => count($preview)
            ]);

        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Failed to get preview: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Generate CSV from comparison (AJAX)
     *
     * @return ResponseInterface JSON response
     */
    public function generateCsv(): ResponseInterface
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Invalid request method'
            ]);
        }

        try {
            $csvGenerator = new \App\Services\Sync\CsvGeneratorService();
            $result = $csvGenerator->generateCsv();

            if ($result['status'] === 'success') {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result
                ]);
            } else {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'message' => $result['message']
                ]);
            }

        } catch (\Exception $e) {
            log_message('error', '[TeacherSync] Generate CSV failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'CSV generation error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get comparison statistics (AJAX)
     *
     * @return ResponseInterface JSON response
     */
    public function getComparisonStats(): ResponseInterface
    {
        try {
            $csvGenerator = new \App\Services\Sync\CsvGeneratorService();
            $stats = $csvGenerator->getComparisonStatistics();

            return $this->response->setJSON([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Preview CSV file (AJAX)
     *
     * @return ResponseInterface JSON response
     */
    public function previewCsv(): ResponseInterface
    {
        try {
            $limit = $this->request->getGet('limit') ?? 20;
            $csvGenerator = new \App\Services\Sync\CsvGeneratorService();

            $preview = $csvGenerator->previewCsv($limit);
            $fileInfo = $csvGenerator->getCsvFileInfo();

            return $this->response->setJSON([
                'success' => true,
                'data' => [
                    'preview' => $preview,
                    'file_info' => $fileInfo,
                ]
            ]);

        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Failed to preview CSV: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Download CSV file
     *
     * @return ResponseInterface CSV download
     */
    public function downloadCsv(): ResponseInterface
    {
        try {
            $csvGenerator = new \App\Services\Sync\CsvGeneratorService();

            if (!$csvGenerator->csvFileExists()) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'message' => 'CSV file not found. Please generate it first.'
                ]);
            }

            $filePath = $csvGenerator->getCsvFilePath();
            $fileName = 'sync-data-' . date('Y-m-d-His') . '.csv';

            return $this->response
                ->setHeader('Content-Type', 'text/csv')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"')
                ->setBody(file_get_contents($filePath));

        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Failed to download CSV: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Download sync log as CSV
     *
     * @param int $logId Log ID
     * @return ResponseInterface CSV download
     */
    public function downloadLog(int $logId): ResponseInterface
    {
        try {
            $logModel = new \App\Models\Sync\TeacherSyncLogModel();
            $log = $logModel->find($logId);

            if (!$log) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'message' => 'Log not found'
                ]);
            }

            $resultData = json_decode($log['result_data'], true);

            // Generate CSV content
            $csv = $this->generateLogCSV($log, $resultData);

            // Set headers for download
            return $this->response
                ->setHeader('Content-Type', 'text/csv')
                ->setHeader('Content-Disposition', 'attachment; filename="teacher_sync_log_' . $logId . '.csv"')
                ->setBody($csv);

        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Failed to download log: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Generate CSV from log data
     *
     * @param array $log Log entry
     * @param array|null $resultData Result data
     * @return string CSV content
     */
    private function generateLogCSV(array $log, ?array $resultData): string
    {
        $output = fopen('php://temp', 'r+');

        // Header
        fputcsv($output, ['Field', 'Value']);

        // Basic info
        fputcsv($output, ['Log ID', $log['id']]);
        fputcsv($output, ['Sync Type', $log['sync_type']]);
        fputcsv($output, ['Status', $log['status']]);
        fputcsv($output, ['Started At', $log['started_at']]);
        fputcsv($output, ['Completed At', $log['completed_at']]);

        // Statistics
        if ($resultData && isset($resultData['statistics'])) {
            fputcsv($output, ['', '']);
            fputcsv($output, ['Statistics', '']);
            foreach ($resultData['statistics'] as $key => $value) {
                fputcsv($output, [ucwords(str_replace('_', ' ', $key)), $value]);
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}