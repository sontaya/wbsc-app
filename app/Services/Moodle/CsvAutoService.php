<?php

namespace App\Services\Moodle;

use Exception;

class CsvAutoService extends BaseAutomationService
{
    public function writeEnrollmentCsv(string $prefix, array $headers, array $rows): array
    {
        $filename = $prefix . '_' . date('Ymd_His') . '.csv';
        $path = rtrim($this->automationConfig->csvOutputDir, '/') . '/' . $filename;

        $fp = fopen($path, 'w');
        if ($fp === false) {
            throw new Exception('Unable to create CSV file: ' . $path);
        }

        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        $sharedPath = null;
        if ($this->automationConfig->csvSharedDir !== '') {
            $sharedPath = rtrim($this->automationConfig->csvSharedDir, '/') . '/' . $filename;
            $this->copyToShared($path, $sharedPath);
        }

        return [
            'filename' => $filename,
            'path' => $path,
            'shared_path' => $sharedPath,
            'records' => count($rows),
        ];
    }

    protected function copyToShared(string $from, string $to): void
    {
        $dir = dirname($to);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new Exception('Unable to create shared CSV directory: ' . $dir);
        }

        if (!copy($from, $to)) {
            throw new Exception('Unable to copy CSV to shared directory: ' . $to);
        }
    }
}
