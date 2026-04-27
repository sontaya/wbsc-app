<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class MoodleAutomation extends BaseConfig
{
    public string $termPrefix = '2569-1-';
    public string $courseTeacherView = '';
    public string $studentComparisonView = 'WBSC.VW_STUDENT_COURSE_COMPARISON';
    public string $teacherComparisonView = 'WBSC.COURSE_TEACHER_COMPARE';
    public string $csvOutputDir = WRITEPATH . 'uploads/moodle_csv/';
    public string $csvSharedDir = '';
    public int $batchSize = 200;
    public int $studentRoleId = 5;
    public int $editingTeacherRoleId = 3;
    public string $defaultCourseFormat = 'topics';
    public int $defaultCourseSections = 10;

    /**
     * Faculty name => Moodle category id
     * Override by env with JSON string in wbsc.categoryMapping
     */
    public array $categoryMapping = [];

    public function __construct()
    {
        parent::__construct();

        $this->termPrefix = (string) (env('wbsc.termPrefix') ?? $this->termPrefix);
        $this->courseTeacherView = (string) env('wbsc.courseTeacherView');
        $this->studentComparisonView = (string) (env('wbsc.studentComparisonView') ?? $this->studentComparisonView);
        $this->teacherComparisonView = (string) (env('wbsc.teacherComparisonView') ?? $this->teacherComparisonView);
        $this->csvSharedDir = (string) (env('wbsc.csvSharedDir') ?? $this->csvSharedDir);
        $this->batchSize = (int) (env('wbsc.batchSize') ?? $this->batchSize);
        $this->studentRoleId = (int) (env('wbsc.studentRoleId') ?? $this->studentRoleId);
        $this->editingTeacherRoleId = (int) (env('wbsc.editingTeacherRoleId') ?? $this->editingTeacherRoleId);

        $mapping = env('wbsc.categoryMapping');
        if (is_string($mapping) && trim($mapping) !== '') {
            $decoded = json_decode($mapping, true);
            if (is_array($decoded)) {
                $this->categoryMapping = $decoded;
            }
        }
    }
}
