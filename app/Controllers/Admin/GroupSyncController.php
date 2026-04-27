<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Moodle\GroupSyncService;
use CodeIgniter\HTTP\ResponseInterface;

class GroupSyncController extends BaseController
{
    protected GroupSyncService $service;

    public function __construct()
    {
        $this->service = new GroupSyncService();
    }

    public function index(): string
    {
        return view('admin/group_sync/index', [
            'title' => 'Group Sync',
            'preview' => $this->service->previewGroupChanges(),
            'logs' => $this->service->getRecentLogs(10),
        ]);
    }

    public function preview(): ResponseInterface
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => $this->service->previewGroupChanges(),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function run(): ResponseInterface
    {
        try {
            $payload = $this->request->getJSON(true) ?: $this->request->getPost();
            $result = $this->service->bulkUpdateGroups([
                'mode' => $payload['mode'] ?? 'api',
                'dry_run' => filter_var($payload['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN),
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
}
