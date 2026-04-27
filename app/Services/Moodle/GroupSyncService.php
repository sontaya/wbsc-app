<?php

namespace App\Services\Moodle;

use App\Models\Sync\GroupSyncLogModel;
use Exception;

class GroupSyncService extends BaseAutomationService
{
    protected GroupSyncLogModel $logModel;
    protected CsvAutoService $csvService;

    public function __construct()
    {
        parent::__construct();
        $this->logModel = new GroupSyncLogModel();
        $this->csvService = new CsvAutoService();
    }

    public function getGroupUpdatesFromRegistry(): array
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
                SC.SECTION_CODE AS NEW_GROUP,
                SC.STUDENT_GROUPS AS OLD_GROUP
            FROM {$view} SC
            WHERE SC.ACTION_REQUIRED = 'UPDATE_GROUP_IN_MOODLE'
              AND SC.USERNAME IS NOT NULL
            ORDER BY SC.SUB_SHOW, SC.USERNAME
        ";

        return $this->oracleDb->query($sql)->getResultArray();
    }

    public function previewGroupChanges(): array
    {
        $rows = $this->getGroupUpdatesFromRegistry();
        return [
            'total' => count($rows),
            'items' => array_slice($rows, 0, 30),
        ];
    }

    public function bulkUpdateGroups(array $options = []): array
    {
        $mode = (string) ($options['mode'] ?? 'api');
        $dryRun = (bool) ($options['dry_run'] ?? false);

        $logId = $this->logModel->insert([
            'status' => 'started',
            'mode' => $mode,
            'started_at' => $this->now(),
        ]);

        try {
            $updates = $this->getGroupUpdatesFromRegistry();
            $result = [
                'status' => 'success',
                'mode' => $mode,
                'dry_run' => $dryRun,
                'total' => count($updates),
                'moved' => 0,
                'skipped' => 0,
                'errors' => [],
                'csv' => [],
            ];

            if (!$dryRun) {
                if ($mode === 'csv') {
                    $result['csv'] = $this->generateCsvFallback($updates);
                } else {
                    foreach ($updates as $row) {
                        $move = $this->moveUserGroup(
                            (string) ($row['USERNAME'] ?? ''),
                            (string) ($row['COURSE_SHORTNAME'] ?? ''),
                            (string) ($row['OLD_GROUP'] ?? ''),
                            (string) ($row['NEW_GROUP'] ?? '')
                        );

                        $result['moved'] += (int) ($move['moved'] ?? 0);
                        $result['skipped'] += (int) ($move['skipped'] ?? 0);
                        if (!empty($move['error'])) {
                            $result['errors'][] = $move['error'];
                        }
                    }
                }
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

    public function moveUserGroup(string $username, string $courseShortname, string $oldGroup, string $newGroup): array
    {
        try {
            if ($username === '' || $courseShortname === '' || $newGroup === '') {
                return ['moved' => 0, 'skipped' => 1];
            }

            $user = $this->moodleApi->findUserByUsername($username);
            $course = $this->moodleApi->findCourseByShortname($courseShortname);
            if ($user === null || $course === null) {
                return ['moved' => 0, 'skipped' => 1];
            }

            $courseId = (int) $course['id'];
            $userId = (int) $user['id'];
            $groups = $this->moodleApi->getCourseGroups($courseId);

            $oldGroupId = null;
            $newGroupId = null;

            foreach ($groups as $group) {
                if ((string) ($group['name'] ?? '') === $oldGroup) {
                    $oldGroupId = (int) $group['id'];
                }
                if ((string) ($group['name'] ?? '') === $newGroup) {
                    $newGroupId = (int) $group['id'];
                }
            }

            if ($oldGroupId !== null) {
                $this->moodleApi->deleteGroupMembers([[
                    'groupid' => $oldGroupId,
                    'userid' => $userId,
                ]]);
            }

            if ($newGroupId === null) {
                $created = $this->moodleApi->createGroups([[
                    'courseid' => $courseId,
                    'name' => $newGroup,
                    'description' => 'Created by WBSC automation',
                ]]);

                if (!isset($created[0]['id'])) {
                    throw new Exception('Cannot create group ' . $newGroup . ' in ' . $courseShortname);
                }
                $newGroupId = (int) $created[0]['id'];
            }

            $this->moodleApi->addGroupMembers([[
                'groupid' => $newGroupId,
                'userid' => $userId,
            ]]);

            return ['moved' => 1, 'skipped' => 0];
        } catch (Exception $e) {
            return ['moved' => 0, 'skipped' => 0, 'error' => $e->getMessage()];
        }
    }

    public function getRecentLogs(int $limit = 20): array
    {
        return $this->logModel->orderBy('id', 'DESC')->limit($limit)->findAll();
    }

    protected function generateCsvFallback(array $updates): array
    {
        $removeRows = [];
        $addRows = [];

        foreach ($updates as $row) {
            $removeRows[] = [
                $row['USERNAME'] ?? '',
                $row['COURSE_SHORTNAME'] ?? '',
                $row['OLD_GROUP'] ?? '',
            ];

            $addRows[] = [
                $row['USERNAME'] ?? '',
                $row['FIRSTNAME'] ?? '',
                $row['LASTNAME'] ?? '',
                $row['EMAIL'] ?? '',
                $row['COURSE_SHORTNAME'] ?? '',
                $row['NEW_GROUP'] ?? '',
                'student',
            ];
        }

        return [
            'remove_group' => $this->csvService->writeEnrollmentCsv(
                'group_remove',
                ['username', 'course1', 'group1'],
                $removeRows
            ),
            'add_group' => $this->csvService->writeEnrollmentCsv(
                'group_add',
                ['username', 'firstname', 'lastname', 'email', 'course1', 'group1', 'role1'],
                $addRows
            ),
        ];
    }
}
