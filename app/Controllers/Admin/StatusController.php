<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;

class StatusController extends BaseController
{
    public function index(): string
    {
        return view('admin/status/index', [
            'title' => 'System Status',
        ]);
    }

    public function data(): ResponseInterface
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => $this->buildStatusData(),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function buildStatusData(): array
    {
        $oracle = Database::connect('oracle');
        $automation = config('MoodleAutomation');

        $studentView = $automation->studentComparisonView;
        $teacherView = $automation->teacherComparisonView;

        $actionRows = $oracle->query(
            "SELECT SC.ACTION_REQUIRED, COUNT(*) AS TOTAL FROM {$studentView} SC GROUP BY SC.ACTION_REQUIRED ORDER BY SC.ACTION_REQUIRED"
        )->getResultArray();

        $actionRequiredCounts = [];
        foreach ($actionRows as $row) {
            $key = trim((string) ($row['ACTION_REQUIRED'] ?? ''));
            if ($key === '') {
                $key = 'NULL';
            }

            $actionRequiredCounts[$key] = (int) ($row['TOTAL'] ?? 0);
        }

        $addQueueRows = $oracle->query(
            "
            SELECT
                CASE WHEN SC.USERNAME IS NULL THEN 'without_username' ELSE 'with_username' END AS SEGMENT,
                COUNT(*) AS TOTAL
            FROM {$studentView} SC
            WHERE SC.ACTION_REQUIRED = 'ADD_TO_MOODLE'
            GROUP BY CASE WHEN SC.USERNAME IS NULL THEN 'without_username' ELSE 'with_username' END
            "
        )->getResultArray();

        $addQueueBreakdown = [
            'with_username' => 0,
            'without_username' => 0,
        ];

        foreach ($addQueueRows as $row) {
            $segment = (string) ($row['SEGMENT'] ?? '');
            if (isset($addQueueBreakdown[$segment])) {
                $addQueueBreakdown[$segment] = (int) ($row['TOTAL'] ?? 0);
            }
        }

        $teacherRows = $oracle->query(
            "SELECT TC.ACTION, COUNT(*) AS TOTAL FROM {$teacherView} TC GROUP BY TC.ACTION ORDER BY TC.ACTION"
        )->getResultArray();

        $teacherActionCounts = [];
        foreach ($teacherRows as $row) {
            $key = trim((string) ($row['ACTION'] ?? ''));
            if ($key === '') {
                $key = 'NULL';
            }

            $teacherActionCounts[$key] = (int) ($row['TOTAL'] ?? 0);
        }

        return [
            'student_action_required_counts' => $actionRequiredCounts,
            'add_to_moodle_breakdown' => $addQueueBreakdown,
            'teacher_action_counts' => $teacherActionCounts,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }
}
