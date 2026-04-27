<?php

namespace App\Services\Moodle;

use App\Models\Sync\CourseAutoLogModel;
use Exception;

class CourseAutoService extends BaseAutomationService
{
    protected CourseAutoLogModel $logModel;

    public function __construct()
    {
        parent::__construct();
        $this->logModel = new CourseAutoLogModel();
    }

    public function getCoursesFromRegistry(?string $facultyName = null): array
    {
        $view = $this->automationConfig->courseTeacherView;
        $sql = "
            SELECT DISTINCT
                CT.FA_NAME_TH,
                CT.COURSE_CODE_SHORT,
                CT.COURSE_SHORTNAME,
                CT.COURSE_FULLNAME
            FROM {$view} CT
            WHERE CT.COURSE_SHORTNAME IS NOT NULL
        ";

        $params = [];
        if ($facultyName !== null && $facultyName !== '') {
            $sql .= ' AND CT.FA_NAME_TH = ?';
            $params[] = $facultyName;
        }

        $sql .= ' ORDER BY CT.FA_NAME_TH, CT.COURSE_SHORTNAME';

        $rows = $this->oracleDb->query($sql, $params)->getResultArray();

        $result = [];
        $seenShortnames = [];
        foreach ($rows as $row) {
            $categoryId = $this->mapCategoryId((string) ($row['FA_NAME_TH'] ?? ''));
            if ($categoryId === null) {
                continue;
            }

            $rawShortname = trim((string) ($row['COURSE_SHORTNAME'] ?? ''));
            if ($rawShortname === '') {
                continue;
            }

            $rawFullname = trim((string) ($row['COURSE_FULLNAME'] ?? ''));
            $rawCourseCodeShort = trim((string) ($row['COURSE_CODE_SHORT'] ?? ''));
            $termPrefix = trim((string) $this->automationConfig->termPrefix);
            $courseCode = $rawCourseCodeShort;
            if ($courseCode === '' && $termPrefix !== '' && strpos($rawShortname, $termPrefix) === 0) {
                $courseCode = trim(substr($rawShortname, strlen($termPrefix)));
            }

            $courseShortname = $rawShortname;
            $courseFullname = $rawFullname !== '' ? $rawFullname : $rawShortname;

            if ($courseShortname === '' || isset($seenShortnames[$courseShortname])) {
                continue;
            }
            $seenShortnames[$courseShortname] = true;

            $result[] = [
                'faculty_name' => (string) $row['FA_NAME_TH'],
                'course_code' => $courseCode,
                'course_shortname' => $courseShortname,
                'course_fullname' => $courseFullname,
                'course_idnumber' => $courseShortname,
                'category_id' => $categoryId,
            ];
        }

        return $result;
    }

    public function previewNewCourses(?string $facultyName = null): array
    {
        $courses = $this->getCoursesFromRegistry($facultyName);
        $skippedNoCategory = $this->getSkippedNoCategory($facultyName);
        $skippedNoCategoryExisting = 0;
        foreach ($skippedNoCategory as $item) {
            if (!empty($item['exists_in_moodle'])) {
                $skippedNoCategoryExisting++;
            }
        }
        $missing = [];
        $existing = 0;

        foreach ($courses as $course) {
            $exists = $this->moodleApi->findCourseByShortname($course['course_shortname']) !== null;
            if ($exists) {
                $existing++;
                continue;
            }
            $missing[] = $course;
        }

        return [
            'total_source_rows' => count($courses) + count($skippedNoCategory),
            'total_registry_courses' => count($courses),
            'existing_moodle_courses' => $existing,
            'new_courses' => count($missing),
            'skipped_no_category_count' => count($skippedNoCategory),
            'skipped_no_category_existing' => $skippedNoCategoryExisting,
            'skipped_no_category_new' => count($skippedNoCategory) - $skippedNoCategoryExisting,
            'skipped_no_category_items' => $skippedNoCategory,
            'items' => $missing,
        ];
    }

    public function createCoursesManual(array $items): array
    {
        $result = [
            'created' => 0,
            'skipped_existing' => 0,
            'failed' => 0,
            'errors' => [],
            'items' => [],
        ];

        $allowedCategoryIds = array_values(array_map('intval', $this->automationConfig->categoryMapping));
        $seen = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $shortname = trim((string) ($item['course_shortname'] ?? $item['shortname'] ?? ''));
            if ($shortname === '' || isset($seen[$shortname])) {
                continue;
            }
            $seen[$shortname] = true;

            $fullname = trim((string) ($item['course_fullname'] ?? $item['fullname'] ?? ''));
            $categoryId = (int) ($item['category_id'] ?? 0);

            if (!in_array($categoryId, $allowedCategoryIds, true)) {
                $result['failed']++;
                $result['errors'][] = $shortname . ': invalid_category_id';
                continue;
            }

            $exists = $this->moodleApi->findCourseByShortname($shortname);
            if ($exists !== null) {
                $result['skipped_existing']++;
                continue;
            }

            try {
                $created = $this->moodleApi->createCourses([[
                    'shortname' => $shortname,
                    'idnumber' => $shortname,
                    'fullname' => $fullname !== '' ? $fullname : $shortname,
                    'categoryid' => $categoryId,
                    'format' => $this->automationConfig->defaultCourseFormat,
                    'numsections' => $this->automationConfig->defaultCourseSections,
                    'visible' => 1,
                ]]);

                if (!is_array($created) || !isset($created[0]) || !is_array($created[0])) {
                    $result['failed']++;
                    $result['errors'][] = $shortname . ': create_failed_invalid_response';
                    continue;
                }

                $result['created']++;
                $result['items'][] = $created[0];
            } catch (Exception $e) {
                $result['failed']++;
                $result['errors'][] = $shortname . ': ' . $e->getMessage();
            }
        }

        return $result;
    }

    public function getCategoryOptions(): array
    {
        $options = [];
        foreach ($this->automationConfig->categoryMapping as $name => $id) {
            $options[] = [
                'id' => (int) $id,
                'name' => (string) $name,
            ];
        }

        return $options;
    }

    public function createCourses(array $courses): array
    {
        if ($courses === []) {
            return ['created' => 0, 'failed' => 0, 'errors' => [], 'items' => []];
        }

        $payload = [];
        foreach ($courses as $course) {
            $shortname = (string) ($course['course_shortname'] ?? '');
            $payload[] = [
                'shortname' => $shortname,
                'idnumber' => $shortname,
                'fullname' => $course['course_fullname'] !== '' ? $course['course_fullname'] : $shortname,
                'categoryid' => (int) $course['category_id'],
                'format' => $this->automationConfig->defaultCourseFormat,
                'numsections' => $this->automationConfig->defaultCourseSections,
                'visible' => 1,
            ];
        }

        $createdItems = [];
        $errors = [];

        foreach ($this->chunk($payload) as $index => $batch) {
            try {
                $created = $this->moodleApi->createCourses($batch);
                if (is_array($created)) {
                    foreach ($created as $item) {
                        if (is_array($item)) {
                            $createdItems[] = $item;
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = 'Batch ' . ($index + 1) . ': ' . $e->getMessage();
            }
        }

        return [
            'created' => count($createdItems),
            'failed' => count($payload) - count($createdItems),
            'errors' => $errors,
            'items' => $createdItems,
        ];
    }

    public function generateCourseCsv(array $courses): array
    {
        $filename = 'course_auto_create_' . date('Ymd_His') . '.csv';
        $path = rtrim($this->automationConfig->csvOutputDir, '/') . '/' . $filename;

        $fp = fopen($path, 'w');
        if ($fp === false) {
            throw new Exception('Unable to create CSV file: ' . $path);
        }

        fputcsv($fp, ['shortname', 'fullname', 'idnumber', 'category', 'format', 'numsections']);
        foreach ($courses as $course) {
            fputcsv($fp, [
                $course['course_shortname'],
                $course['course_fullname'],
                $course['course_shortname'],
                $course['category_id'],
                $this->automationConfig->defaultCourseFormat,
                $this->automationConfig->defaultCourseSections,
            ]);
        }
        fclose($fp);

        return ['filename' => $filename, 'path' => $path, 'records' => count($courses)];
    }

    public function autoCreateCourses(array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $mode = (string) ($options['mode'] ?? 'api');
        $faculty = $options['faculty'] ?? null;

        $logId = $this->logModel->insert([
            'status' => 'started',
            'mode' => $mode,
            'started_at' => $this->now(),
        ]);

        try {
            $preview = $this->previewNewCourses($faculty);
            $result = [
                'status' => 'success',
                'mode' => $mode,
                'dry_run' => $dryRun,
                'statistics' => [
                    'total_source_rows' => $preview['total_source_rows'] ?? $preview['total_registry_courses'],
                    'total_registry_courses' => $preview['total_registry_courses'],
                    'existing_moodle_courses' => $preview['existing_moodle_courses'],
                    'new_courses' => $preview['new_courses'],
                    'skipped_no_category_count' => $preview['skipped_no_category_count'] ?? 0,
                ],
                'created' => 0,
                'csv_file' => null,
                'errors' => [],
            ];

            if (!$dryRun) {
                if ($mode === 'csv') {
                    $result['csv_file'] = $this->generateCourseCsv($preview['items']);
                    $result['created'] = $preview['new_courses'];
                } else {
                    $create = $this->createCourses($preview['items']);
                    $result['created'] = $create['created'];
                    $result['errors'] = $create['errors'];
                }
            }

            $this->logModel->update($logId, [
                'status' => 'completed',
                'completed_at' => $this->now(),
                'result_data' => json_encode($result),
            ]);

            return $result;
        } catch (Exception $e) {
            $result = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];

            $this->logModel->update($logId, [
                'status' => 'failed',
                'completed_at' => $this->now(),
                'result_data' => json_encode($result),
            ]);

            return $result;
        }
    }

    public function getRecentLogs(int $limit = 20): array
    {
        return $this->logModel->orderBy('id', 'DESC')->limit($limit)->findAll();
    }

    protected function mapCategoryId(string $facultyName): ?int
    {
        if (!isset($this->automationConfig->categoryMapping[$facultyName])) {
            return null;
        }

        return (int) $this->automationConfig->categoryMapping[$facultyName];
    }

    protected function getSkippedNoCategory(?string $facultyName = null): array
    {
        $view = $this->automationConfig->courseTeacherView;
        $sql = "
            SELECT DISTINCT
                CT.FA_NAME_TH,
                CT.COURSE_SHORTNAME,
                CT.COURSE_FULLNAME
            FROM {$view} CT
            WHERE CT.COURSE_SHORTNAME IS NOT NULL
        ";

        $params = [];
        if ($facultyName !== null && $facultyName !== '') {
            $sql .= ' AND CT.FA_NAME_TH = ?';
            $params[] = $facultyName;
        }

        $sql .= ' ORDER BY CT.FA_NAME_TH, CT.COURSE_SHORTNAME';

        $rows = $this->oracleDb->query($sql, $params)->getResultArray();
        $skipped = [];
        $seenShortnames = [];

        foreach ($rows as $row) {
            $faculty = trim((string) ($row['FA_NAME_TH'] ?? ''));
            if ($this->mapCategoryId($faculty) !== null) {
                continue;
            }

            $courseShortname = trim((string) ($row['COURSE_SHORTNAME'] ?? ''));
            if ($courseShortname === '' || isset($seenShortnames[$courseShortname])) {
                continue;
            }
            $seenShortnames[$courseShortname] = true;

            $exists = $this->moodleApi->findCourseByShortname($courseShortname) !== null;

            $skipped[] = [
                'faculty_name' => $faculty !== '' ? $faculty : null,
                'course_shortname' => $courseShortname,
                'course_fullname' => trim((string) ($row['COURSE_FULLNAME'] ?? '')),
                'reason' => 'missing_category_mapping',
                'exists_in_moodle' => $exists,
            ];
        }

        return $skipped;
    }

}
