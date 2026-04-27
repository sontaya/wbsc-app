<?php

namespace App\Commands;

use App\Services\Moodle\CourseImageService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CourseImageUpdate extends BaseCommand
{
    protected $group = 'Sync';
    protected $name = 'course:update-image';
    protected $description = 'Update Moodle course cover images via API or SSH fallback';
    protected $usage = 'course:update-image --image=/path/image.jpg [--mode ssh|api] [--category=289|--courses="1,2,abc"]';
    protected $options = [
        '--image' => 'Local image file path',
        '--mode' => 'ssh or api (default ssh)',
        '--category' => 'Moodle category id',
        '--courses' => 'Comma-separated course IDs or shortnames (ssh mode script supports both)',
    ];

    public function run(array $params)
    {
        $image = (string) (CLI::getOption('image') ?? '');
        $mode = (string) (CLI::getOption('mode') ?? 'ssh');
        $category = CLI::getOption('category');
        $courses = CLI::getOption('courses');

        if ($image === '') {
            CLI::error('--image is required');
            exit(1);
        }

        $service = new CourseImageService();

        if ($mode === 'api') {
            $courseIds = [];
            if ($courses) {
                $courseIds = array_map('intval', array_filter(array_map('trim', explode(',', (string) $courses))));
            } elseif ($category) {
                $list = $service->getCoursesInCategory((int) $category);
                foreach ($list as $course) {
                    $courseIds[] = (int) ($course['id'] ?? 0);
                }
                $courseIds = array_filter($courseIds);
            } else {
                CLI::error('Use --category or --courses with api mode');
                exit(1);
            }

            $result = $service->bulkUpdateImagesViaApi($courseIds, $image);
        } else {
            $result = $service->bulkUpdateImagesViaSsh([
                'local_image_path' => $image,
                'category_id' => $category,
                'course_ids' => $courses,
            ]);
        }

        CLI::write('Course image update completed.', 'green');
        foreach ($result as $key => $value) {
            if (is_array($value)) {
                CLI::write($key . ': ' . json_encode($value));
            } else {
                CLI::write($key . ': ' . (string) $value);
            }
        }
    }
}
