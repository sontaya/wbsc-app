<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// Teacher Sync Routes (Admin Area)
$routes->group('admin/teacher-sync', ['namespace' => 'App\Controllers\Admin'], function($routes) {

    // Dashboard
    $routes->get('/', 'TeacherSyncController::index', ['as' => 'admin.teacher-sync']);

    // AJAX Actions - Sync
    $routes->post('execute-sync', 'TeacherSyncController::executeSync', ['as' => 'admin.teacher-sync.execute']);
    $routes->get('get-statistics', 'TeacherSyncController::getStatistics', ['as' => 'admin.teacher-sync.statistics']);
    $routes->get('get-history', 'TeacherSyncController::getHistory', ['as' => 'admin.teacher-sync.history']);
    $routes->get('preview-data', 'TeacherSyncController::previewData', ['as' => 'admin.teacher-sync.preview']);

    // AJAX Actions - CSV Generation (NEW)
    $routes->post('generate-csv', 'TeacherSyncController::generateCsv', ['as' => 'admin.teacher-sync.generate-csv']);
    $routes->get('get-comparison-stats', 'TeacherSyncController::getComparisonStats', ['as' => 'admin.teacher-sync.comparison-stats']);
    $routes->get('preview-csv', 'TeacherSyncController::previewCsv', ['as' => 'admin.teacher-sync.preview-csv']);

    // Download
    $routes->get('download-csv', 'TeacherSyncController::downloadCsv', ['as' => 'admin.teacher-sync.download-csv']);
    $routes->get('download-log/(:num)', 'TeacherSyncController::downloadLog/$1', ['as' => 'admin.teacher-sync.download-log']);
});


// Student Sync Routes
$routes->group('admin/student-sync', ['namespace' => 'App\Controllers\Admin'], function($routes) {

    // Dashboard
    $routes->get('/', 'StudentSyncController::index', ['as' => 'admin.student-sync']);

    // AJAX Actions - Sync
    $routes->post('execute-sync', 'StudentSyncController::executeSync', ['as' => 'admin.student-sync.execute']);
    $routes->get('get-statistics', 'StudentSyncController::getStatistics', ['as' => 'admin.student-sync.statistics']);
    $routes->get('get-history', 'StudentSyncController::getHistory', ['as' => 'admin.student-sync.history']);
    $routes->get('get-faculty-statistics', 'StudentSyncController::getFacultyStatistics', ['as' => 'admin.student-sync.faculty-stats']);
    $routes->get('preview-data', 'StudentSyncController::previewData', ['as' => 'admin.student-sync.preview']);

    // Download
    $routes->get('download-log/(:num)', 'StudentSyncController::downloadLog/$1', ['as' => 'admin.student-sync.download-log']);
});

/**
 * Optional: Protected Routes with Authentication
 *
 * If you have authentication middleware, wrap the routes:
 */

/*
$routes->group('admin/teacher-sync', [
    'namespace' => 'App\Controllers\Admin',
    'filter' => 'auth' // Your authentication filter
], function($routes) {
    // ... routes here
});
*/

/**
 * Example URL Structure:
 *
 * GET  /admin/teacher-sync                    → Dashboard
 * POST /admin/teacher-sync/execute-sync       → Execute sync (AJAX)
 * GET  /admin/teacher-sync/get-statistics     → Get statistics (AJAX)
 * GET  /admin/teacher-sync/get-history        → Get history (AJAX)
 * GET  /admin/teacher-sync/preview-data       → Preview source data (AJAX)
 * GET  /admin/teacher-sync/download-log/123   → Download log CSV
 */