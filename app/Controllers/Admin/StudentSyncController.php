<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Sync\StudentSyncService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Student Sync Controller
 * Admin interface for managing student enrollment synchronization
 */
class StudentSyncController extends BaseController
{
    protected StudentSyncService $syncService;

    public function __construct()
    {
        $this->syncService = new StudentSyncService();
    }

    /**
     * Main dashboard
     */
    public function index(): string
    {

         // Add debug logging
        log_message('info', '[StudentSync] Dashboard accessed');

        try {
            $facultyConfig = $this->syncService->getFacultyConfig();
            log_message('info', '[StudentSync] Faculty config loaded: ' . count($facultyConfig) . ' faculties');

            // Get statistics for each faculty
            $facultyStats = [];
            foreach (array_keys($facultyConfig) as $facultyId) {
                $facultyStats[$facultyId] = [
                    'source_count' => $this->syncService->getSourceRecordCountByFaculty($facultyId),
                    'target_count' => $this->syncService->getTargetRecordCountByFaculty($facultyId),
                ];
            }

            $data = [
                'title' => 'Student Enrollment Sync Management',
                'lastSync' => $this->syncService->getLastSyncInfo(),
                'facultyConfig' => $facultyConfig,
                'facultyStats' => $facultyStats,
                'recentLogs' => $this->syncService->getStatistics(10),
            ];

        } catch (\Exception $e) {
            log_message('error', '[StudentSync] Dashboard error: ' . $e->getMessage());
            log_message('error', '[StudentSync] Stack: ' . $e->getTraceAsString());
            throw $e;
        }



        return view('admin/student_sync/index', $data);
    }

    /**
     * Execute sync (AJAX)
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
            // Support both JSON and POST data
            $json = $this->request->getJSON(true);
            $facultiesParam = $json['faculties'] ?? $this->request->getPost('faculties');

            // Convert to array if string (comma-separated)
            if (is_string($facultiesParam)) {
                $faculties = array_map('trim', explode(',', $facultiesParam));
                $faculties = array_filter($faculties);
            } else {
                $faculties = is_array($facultiesParam) ? $facultiesParam : [];
            }

            if (empty($faculties)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'No faculties selected'
                ]);
            }

            // Convert to integers
            $faculties = array_map('intval', $faculties);

            // Execute sync
            $result = $this->syncService->sync([
                'faculties' => $faculties
            ]);

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
            log_message('error', '[StudentSync] Execute failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Sync execution error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get statistics (AJAX)
     */
    public function getStatistics(): ResponseInterface
    {
        try {
            $facultyConfig = $this->syncService->getFacultyConfig();
            $facultyStats = [];

            // Only process valid faculty IDs from config
            foreach (array_keys($facultyConfig) as $facultyId) {
                $facultyStats[$facultyId] = [
                    'name' => $facultyConfig[$facultyId],
                    'source_count' => $this->syncService->getSourceRecordCountByFaculty($facultyId),
                    'target_count' => $this->syncService->getTargetRecordCountByFaculty($facultyId),
                ];
            }

            $statistics = [
                'faculty_stats' => $facultyStats,
                'last_sync' => $this->syncService->getLastSyncInfo(),
            ];

            return $this->response->setJSON([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            log_message('error', '[StudentSync] Get statistics failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get sync history (AJAX)
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
     * Get faculty statistics (AJAX)
     */
    public function getFacultyStatistics(): ResponseInterface
    {
        try {
            $facultyId = $this->request->getGet('faculty_id');

            if (!$facultyId) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Faculty ID required'
                ]);
            }

            $stats = [
                'faculty_id' => $facultyId,
                'source_count' => $this->syncService->getSourceRecordCountByFaculty($facultyId),
                'target_count' => $this->syncService->getTargetRecordCountByFaculty($facultyId),
                'recent_logs' => $this->syncService->getStatisticsByFaculty($facultyId, 5),
            ];

            return $this->response->setJSON([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Failed to get faculty statistics: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Preview data by faculty (AJAX)
     */
    public function previewData(): ResponseInterface
    {
        try {
            $facultyId = $this->request->getGet('faculty_id');
            $limit = $this->request->getGet('limit') ?? 10;

            if (!$facultyId) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Faculty ID required'
                ]);
            }

            // Extract preview data
            $preview = $this->syncService->extractSourceDataByFaculty($facultyId);
            $preview = array_slice($preview, 0, $limit);

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
     * Download sync log as CSV
     */
    public function downloadLog(int $logId): ResponseInterface
    {
        try {
            $logModel = new \App\Models\Sync\StudentSyncLogModel();
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
                ->setHeader('Content-Disposition', 'attachment; filename="student_sync_log_' . $logId . '.csv"')
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
        fputcsv($output, ['Faculties Synced', $log['faculties_synced']]);
        fputcsv($output, ['Started At', $log['started_at']]);
        fputcsv($output, ['Completed At', $log['completed_at']]);

        // Overall Statistics
        if ($resultData && isset($resultData['statistics'])) {
            fputcsv($output, ['', '']);
            fputcsv($output, ['Overall Statistics', '']);
            foreach ($resultData['statistics'] as $key => $value) {
                if ($key !== 'faculty_details') {
                    fputcsv($output, [ucwords(str_replace('_', ' ', $key)), $value]);
                }
            }
        }

        // Faculty Details
        if ($resultData && isset($resultData['statistics']['faculty_details'])) {
            fputcsv($output, ['', '']);
            fputcsv($output, ['Faculty Details', '']);
            fputcsv($output, ['Faculty ID', 'Faculty Name', 'Total', 'Inserted', 'Updated', 'Deleted', 'Unchanged']);

            foreach ($resultData['statistics']['faculty_details'] as $facultyId => $facultyStats) {
                fputcsv($output, [
                    $facultyId,
                    $facultyStats['faculty_name'],
                    $facultyStats['total_records'],
                    $facultyStats['inserted_records'],
                    $facultyStats['updated_records'],
                    $facultyStats['deleted_records'],
                    $facultyStats['unchanged_records'],
                ]);
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}