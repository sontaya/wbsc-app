<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGroupSyncLogs extends Migration
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
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['started', 'completed', 'failed'],
                'default' => 'started',
            ],
            'mode' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'api',
            ],
            'result_data' => [
                'type' => 'LONGTEXT',
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
        $this->forge->addKey('status');
        $this->forge->addKey('mode');
        $this->forge->createTable('group_sync_logs', true);
    }

    public function down()
    {
        $this->forge->dropTable('group_sync_logs', true);
    }
}
