<?php

namespace App\Services\Moodle;

use CodeIgniter\Database\BaseConnection;
use Config\MoodleAutomation;
use Exception;

abstract class BaseAutomationService
{
    protected BaseConnection $oracleDb;
    protected MoodleApiService $moodleApi;
    protected MoodleAutomation $automationConfig;

    public function __construct()
    {
        $this->oracleDb = \Config\Database::connect('oracle');
        $this->moodleApi = new MoodleApiService();
        $this->automationConfig = config('MoodleAutomation');
        $this->ensureCsvOutputDir();
    }

    protected function ensureCsvOutputDir(): void
    {
        $dir = $this->automationConfig->csvOutputDir;
        if ($dir === '') {
            return;
        }

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new Exception('Unable to create CSV output directory: ' . $dir);
        }
    }

    protected function chunk(array $items, ?int $size = null): array
    {
        $batchSize = $size ?? max(1, $this->automationConfig->batchSize);
        return array_chunk($items, $batchSize);
    }

    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
