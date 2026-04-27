<?php

namespace App\Commands;

use App\Services\Moodle\MoodleApiService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class MoodleTestConnection extends BaseCommand
{
    protected $group = 'Sync';
    protected $name = 'moodle:test-connection';
    protected $description = 'Test Moodle REST API connectivity and show site info';
    protected $usage = 'moodle:test-connection';
    protected $arguments = [];
    protected $options = [];

    public function run(array $params)
    {
        CLI::write('═══════════════════════════════════════════', 'cyan');
        CLI::write('  Moodle API Connection Test', 'cyan');
        CLI::write('═══════════════════════════════════════════', 'cyan');
        CLI::newLine();

        $service = new MoodleApiService();
        $config = $service->getConfigSummary();

        CLI::write('Configuration', 'yellow');
        CLI::write('  Base URL: ' . ($config['base_url'] ?: '(not set)'));
        CLI::write('  Token: ' . ($config['token_configured'] ? 'configured' : 'missing'));
        CLI::write('  Timeout: ' . $config['timeout'] . 's');
        CLI::write('  Verify SSL: ' . ($config['verify_ssl'] ? 'true' : 'false'));
        CLI::newLine();

        try {
            $result = $service->testConnection();

            CLI::write('Connection Result', 'green');
            CLI::write('  Status: connected', 'green');
            CLI::write('  Site: ' . ($result['site_name'] ?? '-'));
            CLI::write('  URL: ' . ($result['site_url'] ?? '-'));
            CLI::write('  User: ' . ($result['username'] ?? '-') . ' (ID: ' . ($result['user_id'] ?? '-') . ')');
            CLI::write('  Release: ' . ($result['release'] ?? '-'));
            CLI::write('  Version: ' . ($result['version'] ?? '-'));
        } catch (\Throwable $e) {
            CLI::error('Connection failed: ' . $e->getMessage());
            exit(1);
        }
    }
}
