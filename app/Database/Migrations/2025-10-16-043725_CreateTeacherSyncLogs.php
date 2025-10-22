<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTeacherSyncLogs extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'sync_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'teacher_sync',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['started', 'completed', 'failed'],
                'default'    => 'started',
            ],
            'result_data' => [
                'type' => 'JSON',
                'null' => true,
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

        $this->forge->createTable('teacher_sync_logs', true);
    }

    public function down()
    {
        $this->forge->dropTable('teacher_sync_logs', true);
    }
}