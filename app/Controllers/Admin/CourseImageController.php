<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Moodle\CourseImageService;
use CodeIgniter\HTTP\ResponseInterface;

class CourseImageController extends BaseController
{
    protected CourseImageService $service;

    public function __construct()
    {
        $this->service = new CourseImageService();
    }

    public function index(): string
    {
        return view('admin/course_image/index', [
            'title' => 'Course Image Update',
        ]);
    }

    public function categories(): ResponseInterface
    {
        try {
            return $this->response->setJSON(['success' => true, 'data' => $this->service->getMoodleCategories()]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function coursesByCategory(): ResponseInterface
    {
        try {
            $categoryId = (int) ($this->request->getGet('category_id') ?? 0);
            if ($categoryId <= 0) {
                return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'category_id is required']);
            }

            return $this->response->setJSON([
                'success' => true,
                'data' => $this->service->getCoursesInCategory($categoryId),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function run(): ResponseInterface
    {
        try {
            $mode = (string) ($this->request->getPost('mode') ?? 'ssh');
            $dryRun = filter_var($this->request->getPost('dry_run') ?? false, FILTER_VALIDATE_BOOLEAN);
            $categoryId = $this->request->getPost('category_id');
            $courseIds = $this->request->getPost('course_ids');
            $uploaded = $this->request->getFile('image');

            $ids = [];
            if (!empty($courseIds)) {
                $ids = array_map('intval', array_filter(array_map('trim', explode(',', (string) $courseIds))));
            } elseif (!empty($categoryId)) {
                $courses = $this->service->getCoursesInCategory((int) $categoryId);
                foreach ($courses as $course) {
                    $ids[] = (int) ($course['id'] ?? 0);
                }
                $ids = array_values(array_filter($ids));
            }

            if ($dryRun) {
                return $this->response->setJSON([
                    'success' => true,
                    'data' => [
                        'status' => 'success',
                        'mode' => $mode,
                        'dry_run' => true,
                        'target_course_count' => count($ids),
                        'target_course_ids_sample' => array_slice($ids, 0, 30),
                        'has_image_file' => $uploaded !== null && $uploaded->isValid(),
                        'message' => 'Dry-run only: no image was uploaded and no course was updated.',
                    ],
                ]);
            }

            if ($uploaded === null || !$uploaded->isValid()) {
                return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Valid image file is required']);
            }

            $tempDir = WRITEPATH . 'uploads/course_images/';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $name = $uploaded->getRandomName();
            $uploaded->move($tempDir, $name, true);
            $imagePath = $tempDir . $name;

            if ($mode === 'api') {
                $result = $this->service->bulkUpdateImagesViaApi($ids, $imagePath);
            } else {
                $result = $this->service->bulkUpdateImagesViaSsh([
                    'local_image_path' => $imagePath,
                    'category_id' => $categoryId,
                    'course_ids' => $courseIds,
                ]);
            }

            return $this->response->setJSON(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
