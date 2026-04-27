<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Moodle\MoodleApiService;
use CodeIgniter\HTTP\ResponseInterface;

class MoodleApiController extends BaseController
{
    protected MoodleApiService $moodleApi;

    public function __construct()
    {
        $this->moodleApi = new MoodleApiService();
    }

    public function index(): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Moodle API module is ready',
            'data' => [
                'config' => $this->moodleApi->getConfigSummary(),
                'endpoints' => [
                    'test_connection' => site_url('admin/moodle-api/test-connection'),
                    'site_info' => site_url('admin/moodle-api/site-info'),
                ],
            ],
        ]);
    }

    public function testConnection(): ResponseInterface
    {
        try {
            $result = $this->moodleApi->testConnection();

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Moodle API connection successful',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[MoodleApi] testConnection failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function siteInfo(): ResponseInterface
    {
        try {
            $result = $this->moodleApi->getSiteInfo();

            return $this->response->setJSON([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[MoodleApi] siteInfo failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
