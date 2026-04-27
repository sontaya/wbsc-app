<?php

namespace App\Commands;

use App\Services\Moodle\EnrollAutoService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class EnrollAuto extends BaseCommand
{
    protected $group = 'Sync';
    protected $name = 'enroll:auto';
    protected $description = 'Auto import enrollments for students and teachers via Moodle API or CSV fallback';
    protected $usage = 'enroll:auto [--mode api|csv] [--dry-run] [--students-only] [--teachers-only]';
    protected $options = [
        '--mode' => 'api or csv',
        '--dry-run' => 'Preview only',
        '--students-only' => 'Run students only',
        '--teachers-only' => 'Run teachers only',
    ];

    public function run(array $params)
    {
        $mode = (string) (CLI::getOption('mode') ?? 'api');
        if (!in_array($mode, ['api', 'csv'], true)) {
            CLI::error('Invalid --mode. Use api or csv.');
            exit(1);
        }

        $service = new EnrollAutoService();
        $result = $service->runAutoImport([
            'mode' => $mode,
            'dry_run' => (bool) CLI::getOption('dry-run'),
            'students_only' => (bool) CLI::getOption('students-only'),
            'teachers_only' => (bool) CLI::getOption('teachers-only'),
        ]);

        if (($result['status'] ?? 'error') !== 'success') {
            CLI::error($result['message'] ?? 'Unknown error');
            exit(1);
        }

        CLI::write('Enroll auto import completed.', 'green');
        CLI::write('Mode: ' . $mode);
        CLI::write('Dry-run: ' . ($result['dry_run'] ? 'yes' : 'no'));

        foreach ($result['statistics'] as $k => $v) {
            CLI::write($k . ': ' . $v);
        }

        if ($mode === 'api') {
            CLI::write('Enrolled: ' . ($result['actions']['enrolled'] ?? 0));
            CLI::write('Unenrolled: ' . ($result['actions']['unenrolled'] ?? 0));
            CLI::write('Skipped: ' . ($result['actions']['skipped'] ?? 0));
            if (!empty($result['actions']['errors'])) {
                CLI::write('Errors: ' . count($result['actions']['errors']), 'yellow');
            }
        } else {
            CLI::write('CSV Add: ' . ($result['csv']['add']['path'] ?? '-'));
            CLI::write('CSV Remove: ' . ($result['csv']['remove']['path'] ?? '-'));
        }
    }
}
