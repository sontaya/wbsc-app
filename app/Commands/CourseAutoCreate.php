<?php

namespace App\Commands;

use App\Services\Moodle\CourseAutoService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CourseAutoCreate extends BaseCommand
{
    protected $group = 'Sync';
    protected $name = 'course:auto-create';
    protected $description = 'Auto-create Moodle courses from registry source (Oracle read-only)';
    protected $usage = 'course:auto-create [--dry-run] [--mode api|csv] [--faculty "Faculty Name"]';
    protected $options = [
        '--dry-run' => 'Preview without creating courses',
        '--mode' => 'api or csv (default: api)',
        '--faculty' => 'Filter by faculty name',
    ];

    public function run(array $params)
    {
        $service = new CourseAutoService();
        $mode = (string) (CLI::getOption('mode') ?? 'api');
        if (!in_array($mode, ['api', 'csv'], true)) {
            CLI::error('Invalid --mode. Use api or csv.');
            exit(1);
        }

        $result = $service->autoCreateCourses([
            'dry_run' => (bool) CLI::getOption('dry-run'),
            'mode' => $mode,
            'faculty' => CLI::getOption('faculty') ?: null,
        ]);

        if (($result['status'] ?? 'error') !== 'success') {
            CLI::error($result['message'] ?? 'Unknown error');
            exit(1);
        }

        CLI::write('Course auto-create completed.', 'green');
        CLI::write('Mode: ' . $mode);
        CLI::write('Dry-run: ' . ($result['dry_run'] ? 'yes' : 'no'));
        CLI::write('Total (registry): ' . ($result['statistics']['total_registry_courses'] ?? 0));
        CLI::write('Existing (Moodle): ' . ($result['statistics']['existing_moodle_courses'] ?? 0));
        CLI::write('New: ' . ($result['statistics']['new_courses'] ?? 0));
        CLI::write('Created: ' . ($result['created'] ?? 0));

        if (!empty($result['csv_file']['path'])) {
            CLI::write('CSV: ' . $result['csv_file']['path']);
        }

        if (!empty($result['errors'])) {
            CLI::newLine();
            CLI::write('Errors:', 'yellow');
            foreach ($result['errors'] as $error) {
                CLI::write('- ' . $error, 'yellow');
            }
        }
    }
}
