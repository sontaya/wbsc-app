<?php

namespace App\Services\Moodle;

use Exception;

class CourseImageService extends BaseAutomationService
{
    public function getMoodleCategories(): array
    {
        $result = $this->moodleApi->callApi('core_course_get_categories', []);
        return is_array($result) ? $result : [];
    }

    public function getCoursesInCategory(int $categoryId): array
    {
        $courses = $this->moodleApi->callApi('core_course_get_courses', []);
        if (!is_array($courses)) {
            return [];
        }

        return array_values(array_filter($courses, static function ($course) use ($categoryId) {
            return isset($course['categoryid']) && (int) $course['categoryid'] === $categoryId;
        }));
    }

    public function bulkUpdateImagesViaApi(array $courseIds, string $imagePath): array
    {
        $updated = 0;
        $errors = [];

        foreach ($courseIds as $courseId) {
            try {
                $this->updateCourseImageViaApi((int) $courseId, $imagePath);
                $updated++;
            } catch (Exception $e) {
                $errors[] = 'Course ' . $courseId . ': ' . $e->getMessage();
            }
        }

        return [
            'updated' => $updated,
            'failed' => count($courseIds) - $updated,
            'errors' => $errors,
        ];
    }

    public function updateCourseImageViaApi(int $courseId, string $imagePath): void
    {
        if (!file_exists($imagePath)) {
            throw new Exception('Image file not found: ' . $imagePath);
        }

        throw new Exception('Moodle 3.11 image upload over API requires draft file workflow. Use SSH mode for reliable execution.');
    }

    public function bulkUpdateImagesViaSsh(array $options): array
    {
        $host = (string) env('moodle.ssh.host');
        $port = (int) (env('moodle.ssh.port') ?: 22);
        $username = (string) env('moodle.ssh.username');
        $moodlePath = (string) env('moodle.ssh.moodlePath');
        $phpBin = (string) (env('moodle.ssh.phpBin') ?: 'php');

        if ($host === '' || $username === '' || $moodlePath === '') {
            throw new Exception('Missing SSH settings in .env (moodle.ssh.host/username/moodlePath)');
        }

        if (!preg_match('/^[A-Za-z0-9._-]+$/', $host) || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            throw new Exception('Invalid SSH host or username format.');
        }

        $targetImagePath = $options['target_image_path'] ?? '/tmp/wbsc-course-cover.jpg';
        $localImagePath = $options['local_image_path'] ?? '';
        $categoryId = $options['category_id'] ?? null;
        $courseIds = $options['course_ids'] ?? null;

        if ($localImagePath === '' || !file_exists($localImagePath)) {
            throw new Exception('Local image path is missing or file does not exist.');
        }

        $endpoint = $username . '@' . $host;

        $scp = sprintf(
            'scp -P %d %s %s:%s 2>&1',
            $port,
            escapeshellarg($localImagePath),
            escapeshellarg($endpoint),
            escapeshellarg($targetImagePath)
        );
        $scpOut = shell_exec($scp);

        $command = sprintf(
            '%s %s/admin/cli/bulk_course_image_update.php --imagepath=%s',
            escapeshellarg($phpBin),
            escapeshellarg($moodlePath),
            escapeshellarg($targetImagePath)
        );

        if ($courseIds !== null && $courseIds !== '') {
            $command .= ' --courseids=' . escapeshellarg((string) $courseIds);
        } elseif ($categoryId !== null && $categoryId !== '') {
            $command .= ' --categoryid=' . escapeshellarg((string) $categoryId);
        } else {
            throw new Exception('Either category_id or course_ids is required for SSH mode.');
        }

        $ssh = sprintf(
            'ssh -p %d %s %s 2>&1',
            $port,
            escapeshellarg($endpoint),
            escapeshellarg($command)
        );
        $sshOut = shell_exec($ssh);

        return [
            'status' => 'success',
            'scp_output' => $scpOut,
            'ssh_output' => $sshOut,
            'executed_command' => $command,
        ];
    }
}
