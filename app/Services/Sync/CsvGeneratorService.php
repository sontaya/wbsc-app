<?php

namespace App\Services\Sync;

use Exception;

/**
 * CSV Generator Service
 * สร้างไฟล์ CSV จากการเปรียบเทียบข้อมูล Teacher
 */
class CsvGeneratorService
{
    protected $oracleDb;
    protected string $csvFilePath;
    protected string $compareView = 'WBSC.COURSE_TEACHER_COMPARE';
    protected string $excludedStaffIdForAdd = '8888-888';

    public function __construct()
    {
        $this->oracleDb = \Config\Database::connect('oracle');

        // CSV File Path from .env or default
        $this->csvFilePath = getenv('moodle.csv.filePath')
            ?: '/var/www/html/wbsc-app/writable/uploads/sync-data.csv';
    }

    /**
     * Generate CSV file from comparison data
     *
     * @return array Result with statistics
     */
    public function generateCsv(): array
    {
        $startTime = microtime(true);

        try {
            // Step 1: Query comparison data
            $data = $this->getComparisonData();

            if (empty($data)) {
                return [
                    'status' => 'success',
                    'message' => 'No data to sync (no ADD actions found)',
                    'statistics' => [
                        'total_records' => 0,
                        'csv_file' => $this->csvFilePath,
                        'duration_seconds' => round(microtime(true) - $startTime, 2),
                    ],
                ];
            }

            // Step 2: Generate CSV content
            $csvContent = $this->buildCsvContent($data);

            // Step 3: Write to file
            $this->writeCsvFile($csvContent);

            // Step 4: Verify file
            $fileSize = $this->getFileSize();

            $duration = round(microtime(true) - $startTime, 2);

            return [
                'status' => 'success',
                'message' => 'CSV file generated successfully',
                'statistics' => [
                    'total_records' => count($data),
                    'csv_file' => $this->csvFilePath,
                    'file_size' => $fileSize,
                    'duration_seconds' => $duration,
                ],
            ];

        } catch (Exception $e) {
            log_message('error', '[CsvGenerator] Failed: ' . $e->getMessage());

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get comparison data from Oracle view
     *
     * @return array Comparison data
     */
    protected function getComparisonData(): array
    {
        log_message('info', '[CsvGenerator] Querying comparison data from: ' . $this->compareView);

        $query = "
            SELECT
                'add' as SYNC_ACTION,
                'editingteacher' as SYNC_ROLE,
                TC.CITIZEN_CODE,
                TC.COURSE_SHORTNAME
            FROM {$this->compareView} TC
            WHERE TC.ACTION = 'Add'
            AND TC.CITIZEN_CODE IS NOT NULL
            AND NVL(TRIM(TC.STAFF_ID), '') <> '{$this->excludedStaffIdForAdd}'
            ORDER BY TC.COURSE_SHORTNAME, TC.CITIZEN_CODE
        ";

        $result = $this->oracleDb->query($query);
        $data = $result->getResultArray();

        log_message('info', sprintf(
            '[CsvGenerator] Found %d records with ADD action',
            count($data)
        ));

        return $data;
    }

    /**
     * Build CSV content from data
     *
     * @param array $data Comparison data
     * @return string CSV content
     */
    protected function buildCsvContent(array $data): string
    {
        $lines = [];

        // No header - Moodle Flat File format
        // Format: action,role,user_idnumber,course_idnumber

        foreach ($data as $row) {
            $lines[] = sprintf(
                '%s,%s,%s,%s',
                strtolower($row['SYNC_ACTION']),     // add
                strtolower($row['SYNC_ROLE']),       // editingteacher
                $row['CITIZEN_CODE'],                 // user idnumber
                $row['COURSE_SHORTNAME']              // course idnumber
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Write CSV content to file
     *
     * @param string $content CSV content
     * @throws Exception
     */
    protected function writeCsvFile(string $content): void
    {
        // Create directory if not exists
        $directory = dirname($this->csvFilePath);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new Exception('Failed to create directory: ' . $directory);
            }
        }

        // Write file
        $bytes = file_put_contents($this->csvFilePath, $content);

        if ($bytes === false) {
            throw new Exception('Failed to write CSV file: ' . $this->csvFilePath);
        }

        // Set permissions
        chmod($this->csvFilePath, 0644);

        log_message('info', sprintf(
            '[CsvGenerator] CSV file written: %s (%d bytes)',
            $this->csvFilePath,
            $bytes
        ));
    }

    /**
     * Get file size in human-readable format
     *
     * @return string File size
     */
    protected function getFileSize(): string
    {
        if (!file_exists($this->csvFilePath)) {
            return '0 B';
        }

        $bytes = filesize($this->csvFilePath);

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get CSV file path
     *
     * @return string File path
     */
    public function getCsvFilePath(): string
    {
        return $this->csvFilePath;
    }

    /**
     * Check if CSV file exists
     *
     * @return bool True if exists
     */
    public function csvFileExists(): bool
    {
        return file_exists($this->csvFilePath);
    }

    /**
     * Get CSV file info
     *
     * @return array|null File info
     */
    public function getCsvFileInfo(): ?array
    {
        if (!$this->csvFileExists()) {
            return null;
        }

        return [
            'path' => $this->csvFilePath,
            'size' => $this->getFileSize(),
            'modified' => date('Y-m-d H:i:s', filemtime($this->csvFilePath)),
            'readable' => is_readable($this->csvFilePath),
        ];
    }

    /**
     * Preview CSV content
     *
     * @param int $lines Number of lines to preview
     * @return array Preview data
     */
    public function previewCsv(int $lines = 10): array
    {
        if (!$this->csvFileExists()) {
            return [];
        }

        $file = fopen($this->csvFilePath, 'r');
        $preview = [];
        $count = 0;

        while (($line = fgets($file)) !== false && $count < $lines) {
            $preview[] = trim($line);
            $count++;
        }

        fclose($file);

        return $preview;
    }

    /**
     * Get comparison statistics
     *
     * @return array Statistics
     */
    public function getComparisonStatistics(): array
    {
        try {
            $query = "
                SELECT
                    ACTION,
                    COUNT(*) as count
                FROM {$this->compareView}
                WHERE ACTION <> 'Add'
                   OR NVL(TRIM(STAFF_ID), '') <> '{$this->excludedStaffIdForAdd}'
                GROUP BY ACTION
            ";

            $result = $this->oracleDb->query($query);
            $data = $result->getResultArray();

            $stats = [
                'Add' => 0,
                'Del' => 0,
                'Match' => 0,
            ];

            foreach ($data as $row) {
                $stats[$row['ACTION']] = (int) $row['COUNT'];
            }

            return $stats;

        } catch (Exception $e) {
            log_message('error', '[CsvGenerator] Statistics failed: ' . $e->getMessage());
            return [
                'Add' => 0,
                'Del' => 0,
                'Match' => 0,
            ];
        }
    }
}
