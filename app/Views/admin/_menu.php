<?php
$path = trim(service('request')->getUri()->getPath(), '/');

$menus = [
    ['label' => 'Teacher Sync', 'url' => site_url('admin/teacher-sync'), 'path' => 'admin/teacher-sync'],
    ['label' => 'Student Sync', 'url' => site_url('admin/student-sync'), 'path' => 'admin/student-sync'],
    ['label' => 'Course Auto', 'url' => site_url('admin/course-auto'), 'path' => 'admin/course-auto'],
    ['label' => 'Enroll Auto', 'url' => site_url('admin/enroll-auto'), 'path' => 'admin/enroll-auto'],
    ['label' => 'Course Image', 'url' => site_url('admin/course-image'), 'path' => 'admin/course-image'],
    ['label' => 'Group Sync', 'url' => site_url('admin/group-sync'), 'path' => 'admin/group-sync'],
    ['label' => 'Status', 'url' => site_url('admin/status'), 'path' => 'admin/status'],
];
?>

<style>
    .wbsc-nav-wrap {
        background: #0f172a;
        margin-bottom: 16px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.2);
    }

    .wbsc-nav {
        max-width: 1200px;
        margin: 0 auto;
        padding: 12px 16px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .wbsc-nav-link {
        display: inline-block;
        text-decoration: none;
        background: #1e293b;
        color: #e2e8f0;
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid #334155;
        font-size: 14px;
    }

    .wbsc-nav-link:hover {
        color: #ffffff;
        background: #334155;
    }

    .wbsc-nav-link.is-active {
        background: #0ea5e9;
        border-color: #0ea5e9;
        color: #ffffff;
    }
</style>

<nav class="wbsc-nav-wrap">
    <div class="wbsc-nav">
        <?php foreach ($menus as $item): ?>
            <?php $isActive = strpos($path, $item['path']) === 0; ?>
            <a class="wbsc-nav-link<?= $isActive ? ' is-active' : '' ?>" href="<?= esc($item['url']) ?>">
                <?= esc($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
