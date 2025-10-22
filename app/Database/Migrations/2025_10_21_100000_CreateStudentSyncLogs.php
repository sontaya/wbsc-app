<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Create student_sync_logs table
 * Run: php spark migrate
 */
class CreateStudentSyncLogs extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'sync_type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'student_sync',
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['started', 'completed', 'failed'],
                'default' => 'started',
            ],
            'result_data' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'faculties_synced' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
                'default' => 0,
            ],
            'started_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('sync_type');
        $this->forge->addKey('status');
        $this->forge->addKey('completed_at');
        $this->forge->addKey('faculties_synced');

        $this->forge->createTable('student_sync_logs');

        echo "✓ Table 'student_sync_logs' created successfully.\n";
    }

    public function down()
    {
        $this->forge->dropTable('student_sync_logs');
        echo "✓ Table 'student_sync_logs' dropped.\n";
    }
}