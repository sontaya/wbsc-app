<?php

namespace App\Services\Moodle;

use App\Models\Sync\EnrollAutoLogModel;
use Exception;

class EnrollAutoService extends BaseAutomationService
{
    protected EnrollAutoLogModel $logModel;
    protected CsvAutoService $csvService;

    public function __construct()
    {
        parent::__construct();
        $this->logModel = new EnrollAutoLogModel();
        $this->csvService = new CsvAutoService();
    }

    public function getStudentsToAdd(): array
    {
        $view = $this->automationConfig->studentComparisonView;
        $termPrefix = $this->automationConfig->termPrefix;
        $sql = "
            SELECT
                SC.USERNAME,
                SC.FIRST_NAME_TH AS FIRSTNAME,
                SC.LAST_NAME_TH AS LASTNAME,
                SC.USERNAME || '@mail.dusit.ac.th' AS EMAIL,
                '{$termPrefix}' || SC.SUB_SHOW AS COURSE_SHORTNAME,
                SC.SECTION_CODE AS GROUP_NAME,
                'student' AS ROLE_NAME
            FROM {$view} SC
            WHERE SC.ACTION_REQUIRED = 'ADD_TO_MOODLE'
              AND SC.USERNAME IS NOT NULL
            ORDER BY SC.SUB_SHOW, SC.USERNAME
        ";

        return $this->oracleDb->query($sql)->getResultArray();
    }

    public function getStudentsToRemove(): array
    {
        $view = $this->automationConfig->studentComparisonView;
        $termPrefix = $this->automationConfig->termPrefix;
        $sql = "
            SELECT
                SC.USERNAME,
                '{$termPrefix}' || SC.SUB_SHOW AS COURSE_SHORTNAME,
                'student' AS ROLE_NAME
            FROM {$view} SC
            WHERE SC.ACTION_REQUIRED IN ('REMOVE_FROM_MOODLE','OK_NOT_IN_MOODLE')
              AND SC.USERNAME IS NOT NULL
            ORDER BY SC.SUB_SHOW, SC.USERNAME
        ";

        return $this->oracleDb->query($sql)->getResultArray();
    }

    public function getTeachersToAdd(): array
    {
        $view = $this->automationConfig->teacherComparisonView;
        $sql = "
            SELECT
                NVL(TC.USERNAME, GP.USER_ID) AS USERNAME,
                TC.CITIZEN_CODE AS IDNUMBER,
                NVL(TC.FIRSTNAME, GP.FIRST_NAME_THA) AS FIRSTNAME,
                NVL(TC.LASTNAME, GP.LAST_NAME_THA) AS LASTNAME,
                NVL(TC.USERNAME, GP.USER_ID) || '@dusit.ac.th' AS EMAIL,
                TC.COURSE_SHORTNAME,
                'editingteacher' AS ROLE_NAME
            FROM {$view} TC
            LEFT JOIN VW_HR_PROFILE@LNK_RSDUE2M_SDPERSON GP
                ON TC.CITIZEN_CODE = GP.CITIZEN_CODE
            WHERE TC.ACTION = 'Add'
              AND TC.COURSE_SHORTNAME IS NOT NULL
              AND (TC.USERNAME IS NOT NULL OR TC.CITIZEN_CODE IS NOT NULL)
            ORDER BY TC.COURSE_SHORTNAME
        ";

        return $this->oracleDb->query($sql)->getResultArray();
    }

    public function getTeachersToRemove(): array
    {
        $view = $this->automationConfig->teacherComparisonView;
        $sql = "
            SELECT
                TC.USERNAME,
                TC.CITIZEN_CODE AS IDNUMBER,
                TC.COURSE_SHORTNAME,
                'editingteacher' AS ROLE_NAME
            FROM {$view} TC
            WHERE TC.ACTION = 'Del'
              AND TC.COURSE_SHORTNAME IS NOT NULL
              AND (TC.USERNAME IS NOT NULL OR TC.CITIZEN_CODE IS NOT NULL)
            ORDER BY TC.COURSE_SHORTNAME
        ";

        return $this->oracleDb->query($sql)->getResultArray();
    }

    public function previewChanges(): array
    {
        return [
            'students_add' => count($this->getStudentsToAdd()),
            'students_remove' => count($this->getStudentsToRemove()),
            'teachers_add' => count($this->getTeachersToAdd()),
            'teachers_remove' => count($this->getTeachersToRemove()),
        ];
    }

    public function runAutoImport(array $options = []): array
    {
        $mode = (string) ($options['mode'] ?? 'api');
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $studentsOnly = (bool) ($options['students_only'] ?? false);
        $teachersOnly = (bool) ($options['teachers_only'] ?? false);
        $excludeTeacherCourses = $this->normalizeCourseList($options['exclude_teacher_courses'] ?? []);

        $scope = 'all';
        if ($studentsOnly) {
            $scope = 'students';
        }
        if ($teachersOnly) {
            $scope = 'teachers';
        }

        $logId = $this->logModel->insert([
            'status' => 'started',
            'mode' => $mode,
            'scope' => $scope,
            'started_at' => $this->now(),
        ]);

        try {
            $studentsAdd = $teachersAdd = $studentsRemove = $teachersRemove = [];
            $teachersAddOriginal = 0;
            $teachersRemoveOriginal = 0;

            if (!$teachersOnly) {
                $studentsAdd = $this->getStudentsToAdd();
                $studentsRemove = $this->getStudentsToRemove();
            }
            if (!$studentsOnly) {
                $teachersAdd = $this->getTeachersToAdd();
                $teachersRemove = $this->getTeachersToRemove();
                $teachersAddOriginal = count($teachersAdd);
                $teachersRemoveOriginal = count($teachersRemove);

                if ($excludeTeacherCourses !== []) {
                    $teachersAdd = $this->filterRecordsByExcludedCourses($teachersAdd, $excludeTeacherCourses);
                    $teachersRemove = $this->filterRecordsByExcludedCourses($teachersRemove, $excludeTeacherCourses);
                }
            }

            $result = [
                'status' => 'success',
                'mode' => $mode,
                'dry_run' => $dryRun,
                'scope' => $scope,
                'statistics' => [
                    'students_add' => count($studentsAdd),
                    'students_remove' => count($studentsRemove),
                    'teachers_add' => count($teachersAdd),
                    'teachers_remove' => count($teachersRemove),
                ],
                'teacher_safety' => [
                    'excluded_courses' => $excludeTeacherCourses,
                    'teachers_add_excluded' => max(0, $teachersAddOriginal - count($teachersAdd)),
                    'teachers_remove_excluded' => max(0, $teachersRemoveOriginal - count($teachersRemove)),
                ],
                'actions' => [
                    'enrolled' => 0,
                    'unenrolled' => 0,
                    'skipped' => 0,
                    'skipped_reasons' => [],
                    'errors' => [],
                ],
                'csv' => [],
            ];

            if (!$dryRun) {
                if ($mode === 'csv') {
                    $result['csv'] = $this->generateCsvFallback($studentsAdd, $studentsRemove, $teachersAdd, $teachersRemove);
                } else {
                    $apiResult = $this->runApiImport($studentsAdd, $studentsRemove, $teachersAdd, $teachersRemove);
                    $result['actions'] = $apiResult;
                }
            } elseif ($mode !== 'csv') {
                $result['actions'] = $this->analyzeApiRecords($studentsAdd, $studentsRemove, $teachersAdd, $teachersRemove);
            }

            $this->logModel->update($logId, [
                'status' => 'completed',
                'completed_at' => $this->now(),
                'result_data' => json_encode($result),
            ]);

            return $result;
        } catch (Exception $e) {
            $error = ['status' => 'error', 'message' => $e->getMessage()];
            $this->logModel->update($logId, [
                'status' => 'failed',
                'completed_at' => $this->now(),
                'result_data' => json_encode($error),
            ]);
            return $error;
        }
    }

    public function getRecentLogs(int $limit = 20): array
    {
        return $this->logModel->orderBy('id', 'DESC')->limit($limit)->findAll();
    }

    public function detectTeacherConflicts(): array
    {
        $courses = $this->getOracleTeacherCourses();
        if ($courses === []) {
            return [
                'total_courses' => 0,
                'conflict_courses' => 0,
                'safe_courses' => 0,
                'recommended_exclude_courses' => [],
                'items' => [],
            ];
        }

        $courseShortnames = array_keys($courses);
        $moodleTeachersByCourse = $this->getMoodleTeachersByCourse($courseShortnames);

        $items = [];
        $recommendedExclude = [];

        foreach ($courseShortnames as $courseShortname) {
            $course = $courses[$courseShortname];
            $oracleExpectedTeachers = array_values($course['oracle_expected_teachers'] ?? []);
            $oracleUsernameSet = $course['oracle_username_set'] ?? [];

            $moodleTeacherMap = $moodleTeachersByCourse[$courseShortname] ?? [];
            $moodleTeachers = array_values($moodleTeacherMap);

            $moodleExtra = [];
            foreach ($moodleTeachers as $teacher) {
                $username = trim((string) ($teacher['username'] ?? ''));
                if ($username === '' || !isset($oracleUsernameSet[$username])) {
                    $moodleExtra[] = $teacher;
                }
            }

            if ($moodleExtra === []) {
                continue;
            }

            $items[] = [
                'course_shortname' => $courseShortname,
                'course_fullname' => (string) ($course['course_fullname'] ?? ''),
                'oracle_expected_count' => count($oracleExpectedTeachers),
                'moodle_total_count' => count($moodleTeachers),
                'moodle_extra_count' => count($moodleExtra),
                'oracle_expected_teachers' => $oracleExpectedTeachers,
                'moodle_teachers' => $moodleTeachers,
                'moodle_extra_teachers' => $moodleExtra,
            ];
            $recommendedExclude[] = $courseShortname;
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string) ($a['course_shortname'] ?? ''), (string) ($b['course_shortname'] ?? ''));
        });

        return [
            'total_courses' => count($courseShortnames),
            'conflict_courses' => count($items),
            'safe_courses' => max(0, count($courseShortnames) - count($items)),
            'recommended_exclude_courses' => $recommendedExclude,
            'items' => $items,
        ];
    }

    protected function runApiImport(array $studentsAdd, array $studentsRemove, array $teachersAdd, array $teachersRemove): array
    {
        $result = ['enrolled' => 0, 'unenrolled' => 0, 'skipped' => 0, 'skipped_reasons' => [], 'errors' => []];

        foreach (array_merge(
            $this->mapRecords($studentsAdd, 'add', 'student'),
            $this->mapRecords($teachersAdd, 'add', 'editingteacher')
        ) as $record) {
            $r = $this->applyEnrollRecord($record);
            $this->mergeCounters($result, $r);
        }

        foreach (array_merge(
            $this->mapRecords($studentsRemove, 'remove', 'student'),
            $this->mapRecords($teachersRemove, 'remove', 'editingteacher')
        ) as $record) {
            $r = $this->applyUnenrollRecord($record);
            $this->mergeCounters($result, $r);
        }

        return $result;
    }

    protected function analyzeApiRecords(array $studentsAdd, array $studentsRemove, array $teachersAdd, array $teachersRemove): array
    {
        $result = ['enrolled' => 0, 'unenrolled' => 0, 'skipped' => 0, 'skipped_reasons' => [], 'errors' => []];

        foreach (array_merge(
            $this->mapRecords($studentsAdd, 'add', 'student'),
            $this->mapRecords($teachersAdd, 'add', 'editingteacher'),
            $this->mapRecords($studentsRemove, 'remove', 'student'),
            $this->mapRecords($teachersRemove, 'remove', 'editingteacher')
        ) as $record) {
            try {
                $reasons = $this->validateRecordDependencies($record);
                if ($reasons === []) {
                    continue;
                }

                $result['skipped']++;
                $result['skipped_reasons'] = array_merge($result['skipped_reasons'], $reasons);
            } catch (Exception $e) {
                $result['errors'][] = $e->getMessage();
            }
        }

        return $result;
    }

    protected function mapRecords(array $records, string $action, string $role): array
    {
        $out = [];
        foreach ($records as $row) {
            $out[] = [
                'action' => $action,
                'role' => $role,
                'username' => (string) ($row['USERNAME'] ?? ''),
                'idnumber' => (string) ($row['IDNUMBER'] ?? ''),
                'course_shortname' => (string) ($row['COURSE_SHORTNAME'] ?? ''),
                'group_name' => (string) ($row['GROUP_NAME'] ?? ''),
            ];
        }
        return $out;
    }

    protected function applyEnrollRecord(array $record): array
    {
        try {
            $reasons = $this->validateRecordDependencies($record);
            if ($reasons !== []) {
                return ['skipped' => 1, 'skipped_reasons' => $reasons, 'errors' => []];
            }

            $user = $this->findUser($record['username'], $record['idnumber']);
            $course = $this->moodleApi->findCourseByShortname($record['course_shortname']);

            $this->moodleApi->enrollUsers([[
                'roleid' => $this->resolveRoleId($record['role']),
                'userid' => (int) $user['id'],
                'courseid' => (int) $course['id'],
            ]]);

            if ($record['group_name'] !== '') {
                $groupId = $this->ensureGroupExists((int) $course['id'], $record['group_name']);
                $this->moodleApi->addGroupMembers([[
                    'groupid' => $groupId,
                    'userid' => (int) $user['id'],
                ]]);
            }

            return ['enrolled' => 1, 'errors' => []];
        } catch (Exception $e) {
            return ['errors' => [$e->getMessage()]];
        }
    }

    protected function applyUnenrollRecord(array $record): array
    {
        try {
            $reasons = $this->validateRecordDependencies($record);
            if ($reasons !== []) {
                return ['skipped' => 1, 'skipped_reasons' => $reasons, 'errors' => []];
            }

            $user = $this->findUser($record['username'], $record['idnumber']);
            $course = $this->moodleApi->findCourseByShortname($record['course_shortname']);

            $this->moodleApi->unenrollUsers([[
                'roleid' => $this->resolveRoleId($record['role']),
                'userid' => (int) $user['id'],
                'courseid' => (int) $course['id'],
            ]]);

            return ['unenrolled' => 1, 'errors' => []];
        } catch (Exception $e) {
            return ['errors' => [$e->getMessage()]];
        }
    }

    protected function findUser(string $username, string $idnumber): ?array
    {
        if ($username !== '') {
            $user = $this->moodleApi->findUserByUsername($username);
            if ($user !== null) {
                return $user;
            }
        }

        if ($idnumber !== '') {
            return $this->moodleApi->findUserByIdnumber($idnumber);
        }

        return null;
    }

    protected function resolveRoleId(string $role): int
    {
        return $role === 'editingteacher'
            ? $this->automationConfig->editingTeacherRoleId
            : $this->automationConfig->studentRoleId;
    }

    protected function ensureGroupExists(int $courseId, string $groupName): int
    {
        $groups = $this->moodleApi->getCourseGroups($courseId);
        foreach ($groups as $group) {
            if (isset($group['name']) && (string) $group['name'] === $groupName) {
                return (int) $group['id'];
            }
        }

        $created = $this->moodleApi->createGroups([[
            'courseid' => $courseId,
            'name' => $groupName,
            'description' => 'Created by WBSC automation',
        ]]);

        if (!isset($created[0]['id'])) {
            throw new Exception('Cannot create group ' . $groupName . ' in course ' . $courseId);
        }

        return (int) $created[0]['id'];
    }

    protected function mergeCounters(array &$target, array $delta): void
    {
        foreach (['enrolled', 'unenrolled', 'skipped'] as $key) {
            $target[$key] += (int) ($delta[$key] ?? 0);
        }

        if (!empty($delta['skipped_reasons']) && is_array($delta['skipped_reasons'])) {
            $target['skipped_reasons'] = array_merge($target['skipped_reasons'], $delta['skipped_reasons']);
        }

        if (!empty($delta['errors']) && is_array($delta['errors'])) {
            $target['errors'] = array_merge($target['errors'], $delta['errors']);
        }
    }

    protected function buildSkippedReason(array $record, string $reason): array
    {
        return [
            'reason' => $reason,
            'action' => (string) ($record['action'] ?? ''),
            'role' => (string) ($record['role'] ?? ''),
            'username' => (string) ($record['username'] ?? ''),
            'idnumber' => (string) ($record['idnumber'] ?? ''),
            'course_shortname' => (string) ($record['course_shortname'] ?? ''),
            'group_name' => (string) ($record['group_name'] ?? ''),
        ];
    }

    protected function validateRecordDependencies(array $record): array
    {
        $user = $this->findUser($record['username'], $record['idnumber']);
        $course = $this->moodleApi->findCourseByShortname($record['course_shortname']);

        $reasons = [];
        if ($user === null) {
            $reasons[] = $this->buildSkippedReason($record, 'user_not_found');
        }
        if ($course === null) {
            $reasons[] = $this->buildSkippedReason($record, 'course_not_found');
        }

        return $reasons;
    }

    protected function generateCsvFallback(
        array $studentsAdd,
        array $studentsRemove,
        array $teachersAdd,
        array $teachersRemove
    ): array {
        $files = [];

        $addRows = [];
        foreach ($studentsAdd as $row) {
            $addRows[] = [
                $row['USERNAME'] ?? '',
                $row['FIRSTNAME'] ?? '',
                $row['LASTNAME'] ?? '',
                $row['EMAIL'] ?? '',
                $row['COURSE_SHORTNAME'] ?? '',
                $row['GROUP_NAME'] ?? '',
                'student',
            ];
        }
        foreach ($teachersAdd as $row) {
            $addRows[] = [
                $row['USERNAME'] ?? '',
                $row['FIRSTNAME'] ?? '',
                $row['LASTNAME'] ?? '',
                $row['EMAIL'] ?? '',
                $row['COURSE_SHORTNAME'] ?? '',
                '',
                'editingteacher',
            ];
        }

        $files['add'] = $this->csvService->writeEnrollmentCsv(
            'enroll_add',
            ['username', 'firstname', 'lastname', 'email', 'course1', 'group1', 'role1'],
            $addRows
        );

        $removeRows = [];
        foreach ($studentsRemove as $row) {
            $removeRows[] = [
                $row['USERNAME'] ?? '',
                $row['COURSE_SHORTNAME'] ?? '',
                '1',
            ];
        }
        foreach ($teachersRemove as $row) {
            $removeRows[] = [
                $row['USERNAME'] ?? '',
                $row['COURSE_SHORTNAME'] ?? '',
                '1',
            ];
        }

        $files['remove'] = $this->csvService->writeEnrollmentCsv(
            'enroll_remove',
            ['username', 'course1', 'delete'],
            $removeRows
        );

        return $files;
    }

    protected function getOracleTeacherCourses(): array
    {
        $view = $this->automationConfig->teacherComparisonView;

        $courseSql = "
            SELECT DISTINCT
                TC.COURSE_SHORTNAME,
                TC.COURSE_FULLNAME
            FROM {$view} TC
            WHERE TC.COURSE_SHORTNAME IS NOT NULL
            ORDER BY TC.COURSE_SHORTNAME
        ";

        $courseRows = $this->oracleDb->query($courseSql)->getResultArray();
        $courses = [];
        foreach ($courseRows as $row) {
            $courseShortname = trim((string) ($row['COURSE_SHORTNAME'] ?? ''));
            if ($courseShortname === '') {
                continue;
            }

            $courses[$courseShortname] = [
                'course_shortname' => $courseShortname,
                'course_fullname' => trim((string) ($row['COURSE_FULLNAME'] ?? '')),
                'oracle_expected_teachers' => [],
                'oracle_username_set' => [],
            ];
        }

        if ($courses === []) {
            return [];
        }

        $teacherSql = "
            SELECT
                TC.COURSE_SHORTNAME,
                TC.COURSE_FULLNAME,
                NVL(TC.USERNAME, GP.USER_ID) AS USERNAME,
                NVL(TC.FULLNAME_THA, NVL(TC.FIRSTNAME, GP.FIRST_NAME_THA) || ' ' || NVL(TC.LASTNAME, GP.LAST_NAME_THA)) AS FULLNAME
            FROM {$view} TC
            LEFT JOIN VW_HR_PROFILE@LNK_RSDUE2M_SDPERSON GP
                ON TC.CITIZEN_CODE = GP.CITIZEN_CODE
            WHERE TC.COURSE_SHORTNAME IS NOT NULL
              AND TC.ACTION IN ('Add', 'Match')
            ORDER BY TC.COURSE_SHORTNAME, FULLNAME
        ";

        $teacherRows = $this->oracleDb->query($teacherSql)->getResultArray();
        foreach ($teacherRows as $row) {
            $courseShortname = trim((string) ($row['COURSE_SHORTNAME'] ?? ''));
            if ($courseShortname === '' || !isset($courses[$courseShortname])) {
                continue;
            }

            $courseFullname = trim((string) ($row['COURSE_FULLNAME'] ?? ''));
            if ($courseFullname !== '' && ($courses[$courseShortname]['course_fullname'] ?? '') === '') {
                $courses[$courseShortname]['course_fullname'] = $courseFullname;
            }

            $username = trim((string) ($row['USERNAME'] ?? ''));
            $fullname = trim((string) ($row['FULLNAME'] ?? ''));

            $teacher = [
                'username' => $username !== '' ? $username : null,
                'fullname' => $fullname !== '' ? $fullname : null,
            ];

            $teacherKey = $username !== ''
                ? $username
                : '__no_username__|' . ($fullname !== '' ? $fullname : uniqid('teacher_', true));

            $courses[$courseShortname]['oracle_expected_teachers'][$teacherKey] = $teacher;
            if ($username !== '') {
                $courses[$courseShortname]['oracle_username_set'][$username] = true;
            }
        }

        return $courses;
    }

    protected function getMoodleTeachersByCourse(array $courseShortnames): array
    {
        if ($courseShortnames === []) {
            return [];
        }

        $roleId = (int) $this->automationConfig->editingTeacherRoleId;
        $moodleDb = \Config\Database::connect();
        $rows = $moodleDb->table('wbbs_course c')
            ->select("c.shortname AS COURSE_SHORTNAME, u.username AS USERNAME, CONCAT(u.firstname, ' ', u.lastname) AS FULLNAME", false)
            ->join('wbbs_context ctx', 'ctx.instanceid = c.id AND ctx.contextlevel = 50', 'inner')
            ->join('wbbs_role_assignments ra', 'ra.contextid = ctx.id AND ra.roleid = ' . $roleId, 'inner')
            ->join('wbbs_user u', 'u.id = ra.userid AND u.deleted = 0', 'inner')
            ->whereIn('c.shortname', $courseShortnames)
            ->groupBy('c.shortname, u.username, u.firstname, u.lastname')
            ->orderBy('c.shortname', 'ASC')
            ->orderBy('u.lastname', 'ASC')
            ->orderBy('u.firstname', 'ASC')
            ->get()
            ->getResultArray();

        $result = [];
        foreach ($rows as $row) {
            $courseShortname = trim((string) ($row['COURSE_SHORTNAME'] ?? ''));
            $username = trim((string) ($row['USERNAME'] ?? ''));
            if ($courseShortname === '' || $username === '') {
                continue;
            }

            if (!isset($result[$courseShortname])) {
                $result[$courseShortname] = [];
            }

            $result[$courseShortname][$username] = [
                'username' => $username,
                'fullname' => trim((string) ($row['FULLNAME'] ?? '')),
            ];
        }

        return $result;
    }

    protected function normalizeCourseList(mixed $courses): array
    {
        if (!is_array($courses)) {
            return [];
        }

        $out = [];
        foreach ($courses as $course) {
            $courseShortname = trim((string) $course);
            if ($courseShortname === '') {
                continue;
            }
            $out[$courseShortname] = true;
        }

        return array_keys($out);
    }

    protected function filterRecordsByExcludedCourses(array $records, array $excludeCourses): array
    {
        if ($excludeCourses === []) {
            return $records;
        }

        $excludeMap = array_fill_keys($excludeCourses, true);

        return array_values(array_filter($records, static function (array $row) use ($excludeMap): bool {
            $courseShortname = trim((string) ($row['COURSE_SHORTNAME'] ?? ''));
            if ($courseShortname === '') {
                return true;
            }

            return !isset($excludeMap[$courseShortname]);
        }));
    }
}
