<?php

namespace App\Commands;

use App\Services\Moodle\GroupSyncService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class GroupSync extends BaseCommand
{
    protected $group = 'Sync';
    protected $name = 'group:sync';
    protected $description = 'Sync Moodle groups from registry comparison view';
    protected $usage = 'group:sync [--mode api|csv] [--dry-run]';
    protected $options = [
        '--mode' => 'api or csv',
        '--dry-run' => 'Preview only',
    ];

    public function run(array $params)
    {
        $mode = (string) (CLI::getOption('mode') ?? 'api');
        if (!in_array($mode, ['api', 'csv'], true)) {
            CLI::error('Invalid --mode. Use api or csv.');
            exit(1);
        }

        $service = new GroupSyncService();
        $result = $service->bulkUpdateGroups([
            'mode' => $mode,
            'dry_run' => (bool) CLI::getOption('dry-run'),
        ]);

        if (($result['status'] ?? 'error') !== 'success') {
            CLI::error($result['message'] ?? 'Unknown error');
            exit(1);
        }

        CLI::write('Group sync completed.', 'green');
        CLI::write('Mode: ' . $mode);
        CLI::write('Dry-run: ' . ($result['dry_run'] ? 'yes' : 'no'));
        CLI::write('Total updates: ' . ($result['total'] ?? 0));
        CLI::write('Moved: ' . ($result['moved'] ?? 0));
        CLI::write('Skipped: ' . ($result['skipped'] ?? 0));

        if (!empty($result['errors'])) {
            CLI::write('Errors: ' . count($result['errors']), 'yellow');
        }

        if (!empty($result['csv'])) {
            CLI::write('CSV remove_group: ' . ($result['csv']['remove_group']['path'] ?? '-'));
            CLI::write('CSV add_group: ' . ($result['csv']['add_group']['path'] ?? '-'));
        }
    }
}
