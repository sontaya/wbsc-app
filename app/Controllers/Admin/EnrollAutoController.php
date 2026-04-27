<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Moodle\EnrollAutoService;
use CodeIgniter\HTTP\ResponseInterface;

class EnrollAutoController extends BaseController
{
    protected EnrollAutoService $service;

    public function __construct()
    {
        $this->service = new EnrollAutoService();
    }

    public function index(): string
    {
        $logs = $this->service->getRecentLogs(10);

        return view('admin/enroll_auto/index', [
            'title' => 'Enroll Auto Import',
            'logs' => $logs,
            'preview' => $this->service->previewChanges(),
            'statusWarning' => $this->buildStatusWarning($logs),
        ]);
    }

    public function preview(): ResponseInterface
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => $this->service->previewChanges(),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function run(): ResponseInterface
    {
        try {
            $payload = $this->request->getJSON(true) ?: $this->request->getPost();
            $result = $this->service->runAutoImport([
                'mode' => $payload['mode'] ?? 'api',
                'dry_run' => filter_var($payload['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'students_only' => filter_var($payload['students_only'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'teachers_only' => filter_var($payload['teachers_only'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'exclude_teacher_courses' => is_array($payload['exclude_teacher_courses'] ?? null)
                    ? $payload['exclude_teacher_courses']
                    : [],
            ]);

            if (($result['status'] ?? 'error') !== 'success') {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'data' => $result]);
            }

            return $this->response->setJSON(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function logs(): ResponseInterface
    {
        return $this->response->setJSON(['success' => true, 'data' => $this->service->getRecentLogs(20)]);
    }

    public function teacherConflicts(): ResponseInterface
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => $this->service->detectTeacherConflicts(),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function buildStatusWarning(array $logs): array
    {
        if ($logs === []) {
            return ['has_warning' => false];
        }

        $latest = $logs[0];
        $raw = $latest['result_data'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return ['has_warning' => false];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['has_warning' => false];
        }

        $actions = $decoded['actions'] ?? [];
        if (!is_array($actions)) {
            return ['has_warning' => false];
        }

        $reasons = $actions['skipped_reasons'] ?? [];
        if (!is_array($reasons) || $reasons === []) {
            return ['has_warning' => false];
        }

        $reasonCounts = [];
        $missingCourses = [];
        $emptyUsername = 0;

        foreach ($reasons as $reasonRow) {
            if (!is_array($reasonRow)) {
                continue;
            }

            $reason = (string) ($reasonRow['reason'] ?? 'unknown');
            $reasonCounts[$reason] = (int) ($reasonCounts[$reason] ?? 0) + 1;

            if ($reason === 'course_not_found') {
                $course = trim((string) ($reasonRow['course_shortname'] ?? ''));
                if ($course !== '') {
                    $missingCourses[$course] = true;
                }
            }

            if (trim((string) ($reasonRow['username'] ?? '')) === '') {
                $emptyUsername++;
            }
        }

        return [
            'has_warning' => true,
            'latest_log_id' => $latest['id'] ?? null,
            'latest_mode' => $latest['mode'] ?? null,
            'latest_completed_at' => $latest['completed_at'] ?? null,
            'skipped' => (int) ($actions['skipped'] ?? 0),
            'reason_counts' => $reasonCounts,
            'missing_courses' => array_keys($missingCourses),
            'empty_username' => $emptyUsername,
        ];
    }
}
