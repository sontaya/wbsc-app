<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\Sync\TeacherSyncService;

/**
 * Teacher Sync CLI Command
 *
 * Usage: php spark teacher:sync [options]
 *
 * Options:
 *   --dry-run    Preview without executing
 *   --force      Force sync even if recently synced
 */
class TeacherSync extends BaseCommand
{
    protected $group = 'Sync';
    protected $name = 'teacher:sync';
    protected $description = 'Synchronize teacher data from MariaDB to Oracle';
    protected $usage = 'teacher:sync [options]';
    protected $arguments = [];
    protected $options = [
        '--dry-run' => 'Preview sync without executing',
        '--force'   => 'Force sync even if recently executed',
        '--verbose' => 'Show detailed output',
    ];

    /**
     * Execute command
     *
     * @param array $params Parameters
     */
    public function run(array $params)
    {
        CLI::write('═══════════════════════════════════════════', 'cyan');
        CLI::write('  WBSC Teacher Sync - CLI Command', 'cyan');
        CLI::write('═══════════════════════════════════════════', 'cyan');
        CLI::newLine();

        $options = [
            'dry_run' => CLI::getOption('dry-run'),
            'force' => CLI::getOption('force'),
            'verbose' => CLI::getOption('verbose'),
        ];

        try {
            $syncService = new TeacherSyncService();

            // Pre-flight checks
            $this->performPreFlightChecks($syncService, $options);

            // Dry run mode
            if ($options['dry_run']) {
                $this->executeDryRun($syncService);
                return;
            }

            // Check if recently synced (unless forced)
            if (!$options['force'] && $this->isRecentlySynced($syncService)) {
                CLI::write('Sync was executed recently. Use --force to override.', 'yellow');
                return;
            }

            // Execute sync
            $this->executeSync($syncService, $options);

        } catch (\Exception $e) {
            CLI::error('Fatal Error: ' . $e->getMessage());
            CLI::error('Stack trace: ' . $e->getTraceAsString());
            exit(1);
        }
    }

    /**
     * Perform pre-flight checks
     *
     * @param TeacherSyncService $service Service
     * @param array $options Options
     */
    protected function performPreFlightChecks(TeacherSyncService $service, array $options): void
    {
        CLI::write('Running pre-flight checks...', 'yellow');

        // Check source connection
        CLI::write('→ Checking MariaDB connection...', 'white');
        $sourceCount = $service->getSourceRecordCount();
        if ($sourceCount === 0) {
            CLI::write('  ✗ Warning: No records found in source', 'red');
        } else {
            CLI::write('  ✓ Source records: ' . number_format($sourceCount), 'green');
        }

        // Check target connection
        CLI::write('→ Checking Oracle connection...', 'white');
        $targetCount = $service->getTargetRecordCount();
        CLI::write('  ✓ Target records: ' . number_format($targetCount), 'green');

        CLI::newLine();
    }

    /**
     * Execute dry run
     *
     * @param TeacherSyncService $service Service
     */
    protected function executeDryRun(TeacherSyncService $service): void
    {
        CLI::write('═══ DRY RUN MODE ═══', 'yellow');
        CLI::newLine();

        CLI::write('Fetching preview data...', 'white');
        $preview = $service->getPreviewData(5);

        if (empty($preview)) {
            CLI::write('No data available', 'yellow');
            return;
        }

        CLI::write('Sample records (first 5):', 'cyan');
        CLI::newLine();

        // Display as table
        $this->displayPreviewTable($preview);

        CLI::newLine();
        CLI::write('Total records to sync: ' . count($preview), 'green');
        CLI::write('This is a DRY RUN - no changes will be made', 'yellow');
    }

    /**
     * Execute actual sync
     *
     * @param TeacherSyncService $service Service
     * @param array $options Options
     */
    protected function executeSync(TeacherSyncService $service, array $options): void
    {
        CLI::write('═══ STARTING SYNC ═══', 'green');
        CLI::newLine();

        $startTime = microtime(true);

        // Show progress bar if not verbose
        if (!$options['verbose']) {
            CLI::write('Progress: ', 'white', false);
        }

        // Execute sync
        $result = $service->sync($options);

        $duration = microtime(true) - $startTime;

        CLI::newLine(2);

        // Display results
        if ($result['status'] === 'success') {
            CLI::write('═══ SYNC COMPLETED ═══', 'green');
            CLI::newLine();

            $stats = $result['statistics'] ?? [];
            $this->displayStatistics($stats);

            CLI::newLine();
            CLI::write('✓ Sync completed successfully in ' . round($duration, 2) . 's', 'green');

        } else {
            CLI::write('═══ SYNC FAILED ═══', 'red');
            CLI::newLine();
            CLI::error('Error: ' . ($result['error'] ?? 'Unknown error'));
        }
    }

    /**
     * Check if recently synced
     *
     * @param TeacherSyncService $service Service
     * @return bool True if recently synced
     */
    protected function isRecentlySynced(TeacherSyncService $service): bool
    {
        $lastSync = $service->getLastSyncInfo();

        if (!$lastSync || $lastSync['status'] !== 'completed') {
            return false;
        }

        // Check if synced within last hour
        $lastSyncTime = strtotime($lastSync['completed_at']);
        $hourAgo = strtotime('-1 hour');

        return $lastSyncTime > $hourAgo;
    }

    /**
     * Display preview table
     *
     * @param array $data Data
     */
    protected function displayPreviewTable(array $data): void
    {
        if (empty($data)) {
            return;
        }

        // Get columns from first row
        $columns = array_keys($data[0]);

        // Header
        $header = implode(' | ', array_map(function($col) {
            return str_pad(substr($col, 0, 15), 15);
        }, $columns));

        CLI::write($header, 'yellow');
        CLI::write(str_repeat('─', strlen($header)), 'yellow');

        // Rows
        foreach ($data as $row) {
            $line = implode(' | ', array_map(function($val) {
                $val = $val ?? '';
                return str_pad(substr($val, 0, 15), 15);
            }, $row));
            CLI::write($line, 'white');
        }
    }

    /**
     * Display statistics
     *
     * @param array $stats Statistics
     */
    protected function displayStatistics(array $stats): void
    {
        $data = [
            ['Metric', 'Value'],
            ['─────────────────', '──────────'],
            ['Total Records', number_format($stats['total_records'] ?? 0)],
            ['Deleted Records', number_format($stats['deleted_records'] ?? 0)],
            ['Inserted Records', number_format($stats['inserted_records'] ?? 0)],
            ['Duration', ($stats['duration_seconds'] ?? 0) . 's'],
        ];

        foreach ($data as $index => $row) {
            $color = $index === 0 ? 'yellow' : ($index === 1 ? 'yellow' : 'white');
            CLI::write(sprintf('  %-20s  %s', $row[0], $row[1]), $color);
        }
    }
}