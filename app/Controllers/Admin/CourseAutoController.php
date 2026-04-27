<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Moodle\CourseAutoService;
use CodeIgniter\HTTP\ResponseInterface;

class CourseAutoController extends BaseController
{
    protected CourseAutoService $service;

    public function __construct()
    {
        $this->service = new CourseAutoService();
    }

    public function index(): string
    {
        $data = [
            'title' => 'Course Auto Create',
            'logs' => $this->service->getRecentLogs(10),
            'categoryOptions' => $this->service->getCategoryOptions(),
        ];

        return view('admin/course_auto/index', $data);
    }

    public function preview(): ResponseInterface
    {
        try {
            $faculty = $this->request->getGet('faculty');
            $result = $this->service->previewNewCourses($faculty ?: null);

            return $this->response->setJSON(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function run(): ResponseInterface
    {
        try {
            $payload = $this->request->getJSON(true) ?: $this->request->getPost();
            $result = $this->service->autoCreateCourses([
                'dry_run' => filter_var($payload['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'mode' => $payload['mode'] ?? 'api',
                'faculty' => $payload['faculty'] ?? null,
            ]);

            if (($result['status'] ?? 'error') !== 'success') {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'data' => $result]);
            }

            return $this->response->setJSON(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function logs(): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => true,
            'data' => $this->service->getRecentLogs(20),
        ]);
    }

    public function createManual(): ResponseInterface
    {
        try {
            $payload = $this->request->getJSON(true) ?: $this->request->getPost();
            $items = $payload['items'] ?? [];

            if (!is_array($items)) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Invalid payload: items must be an array',
                ]);
            }

            $result = $this->service->createCoursesManual($items);

            return $this->response->setJSON([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
