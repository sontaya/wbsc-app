<?php

namespace App\Models\Sync;

use CodeIgniter\Model;

class CourseAutoLogModel extends Model
{
    protected $table = 'course_auto_logs';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'status',
        'mode',
        'result_data',
        'started_at',
        'completed_at',
    ];
}
