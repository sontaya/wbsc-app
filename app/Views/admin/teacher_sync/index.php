<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title) ?> - WBSC Sync System</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #2563eb;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
        }

        body {
            background-color: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e40af 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
            margin-bottom: 1.5rem;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }

        .btn-sync {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-sync:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            color: white;
        }

        .btn-sync:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .btn-generate-csv {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-generate-csv:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
            color: white;
        }

        .btn-generate-csv:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .log-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .badge-status {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-weight: 500;
        }

        .spinner-sync {
            display: inline-block;
            margin-left: 0.5rem;
        }

        .alert-sync {
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .progress-sync {
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <?= view('admin/_menu') ?>

    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">
                        <i class="fas fa-chalkboard-teacher me-2"></i>
                        <?= esc($title) ?>
                    </h1>
                    <p class="mb-0 opacity-75">
                        MariaDB (Moodle) → Oracle Database Synchronization
                    </p>
                </div>
                <div>
                    <button id="btnSync" class="btn btn-sync">
                        <i class="fas fa-sync-alt me-2"></i>
                        Start Sync
                    </button>
                    <button id="btnGenerateCsv" class="btn btn-generate-csv ms-2">
                        <i class="fas fa-file-csv me-2"></i>
                        Generate CSV
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">

        <!-- Comparison Statistics - เพิ่มส่วนนี้ -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-pie me-2"></i>
                        Comparison Statistics
                        <small class="text-muted">(Oracle vs Registry System)</small>
                    </h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-success">
                                    <i class="fas fa-plus-circle me-2"></i>
                                    <strong>Add (ต้องเพิ่ม)</strong>
                                </span>
                                <span id="statAdd" class="badge bg-success">-</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-danger">
                                    <i class="fas fa-minus-circle me-2"></i>
                                    <strong>Del (ต้องลบ)</strong>
                                </span>
                                <span id="statDel" class="badge bg-danger">-</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-info">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Match (ตรงกัน)</strong>
                                </span>
                                <span id="statMatch" class="badge bg-info">-</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button id="btnRefreshStats" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-sync-alt me-1"></i>
                            Refresh Statistics
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Area -->
        <div id="alertArea"></div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <!-- Source Records -->
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small">Source (MariaDB)</p>
                            <h3 class="mb-0 fw-bold" id="sourceCount">
                                <?= number_format($sourceCount) ?>
                            </h3>
                            <small class="text-success">
                                <i class="fas fa-database"></i> Records
                            </small>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-file-import"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Target Records -->
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small">Target (Oracle)</p>
                            <h3 class="mb-0 fw-bold" id="targetCount">
                                <?= number_format($targetCount) ?>
                            </h3>
                            <small class="text-info">
                                <i class="fas fa-database"></i> Records
                            </small>
                        </div>
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="fas fa-file-export"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Last Sync Time -->
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small">Last Sync</p>
                            <h6 class="mb-0" id="lastSyncTime">
                                <?php if ($lastSync): ?>
                                    <?= date('d M y H:i', strtotime($lastSync['completed_at'])) ?>
                                <?php else: ?>
                                    Never
                                <?php endif; ?>
                            </h6>
                            <small class="text-muted">
                                <i class="fas fa-clock"></i>
                                <?php if ($lastSync): ?>
                                    <?= timeAgo($lastSync['completed_at']) ?>
                                <?php else: ?>
                                    No history
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-history"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small">Last Status</p>
                            <h6 class="mb-0">
                                <?php if ($lastSync): ?>
                                    <?php
                                        $statusClass = match($lastSync['status']) {
                                            'completed' => 'success',
                                            'failed' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?> badge-status">
                                        <?= strtoupper($lastSync['status']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary badge-status">NONE</span>
                                <?php endif; ?>
                            </h6>
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> System Status
                            </small>
                        </div>
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sync Progress (Hidden by default) -->
        <div id="syncProgress" class="alert alert-info alert-sync mb-4" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>
                    <i class="fas fa-spinner fa-spin me-2"></i>
                    Synchronization in Progress...
                </strong>
                <span id="progressPercent">0%</span>
            </div>
            <div class="progress progress-sync">
                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                     role="progressbar" style="width: 0%"></div>
            </div>
        </div>

        <!-- Recent Sync History -->
        <div class="log-table">
            <div class="p-4 border-bottom">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Recent Sync History
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Status</th>
                            <th>Started</th>
                            <th>Completed</th>
                            <th>Records</th>
                            <th>Duration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="logTableBody">
                        <?php if (!empty($recentLogs)): ?>
                            <?php foreach ($recentLogs as $log): ?>
                                <?php
                                    $result = json_decode($log['result_data'], true);
                                    $stats = $result['statistics'] ?? [];
                                ?>
                                <tr>
                                    <td class="fw-bold">#<?= $log['id'] ?></td>
                                    <td>
                                        <?php
                                            $badgeClass = match($log['status']) {
                                                'completed' => 'success',
                                                'failed' => 'danger',
                                                default => 'warning'
                                            };
                                        ?>
                                        <span class="badge bg-<?= $badgeClass ?> badge-status">
                                            <?= strtoupper($log['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d M y H:i', strtotime($log['started_at'])) ?></td>
                                    <td><?= $log['completed_at'] ? date('d M y H:i', strtotime($log['completed_at'])) : '-' ?></td>
                                    <td>
                                        <?php if (isset($stats['inserted_records'])): ?>
                                            <span class="badge bg-primary">
                                                <?= number_format($stats['inserted_records']) ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($stats['duration_seconds'])): ?>
                                            <?= number_format($stats['duration_seconds'], 2) ?>s
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary"
                                                onclick="viewLogDetails(<?= $log['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="<?= base_url('admin/teacher-sync/download-log/' . $log['id']) ?>"
                                           class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p class="mb-0">No sync history available</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const baseUrl = '<?= base_url() ?>';

        // Time ago helper
        <?php
        function timeAgo($datetime) {
            $time = strtotime($datetime);
            $diff = time() - $time;

            if ($diff < 60) return $diff . ' seconds ago';
            if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
            if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
            if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
            return date('d M Y', $time);
        }
        ?>

        // Execute Sync
        document.getElementById('btnSync').addEventListener('click', async function() {
            const btn = this;
            const originalText = btn.innerHTML;

            // Disable button
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Syncing...';

            // Show progress
            const progressDiv = document.getElementById('syncProgress');
            const progressBar = document.getElementById('progressBar');
            progressDiv.style.display = 'block';

            // Simulate progress
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += Math.random() * 20;
                if (progress > 90) progress = 90;
                progressBar.style.width = progress + '%';
                document.getElementById('progressPercent').textContent = Math.round(progress) + '%';
            }, 500);

            try {
                const response = await fetch(baseUrl + 'admin/teacher-sync/execute-sync', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                // Clear progress interval
                clearInterval(progressInterval);
                progressBar.style.width = '100%';
                document.getElementById('progressPercent').textContent = '100%';

                if (result.success) {
                    showAlert('success', 'Sync completed successfully!', result.data);
                    await refreshStatistics();
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert('danger', 'Sync failed: ' + result.message);
                }

            } catch (error) {
                clearInterval(progressInterval);
                showAlert('danger', 'Error: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
                setTimeout(() => {
                    progressDiv.style.display = 'none';
                    progressBar.style.width = '0%';
                }, 2000);
            }
        });

        // Show alert
        function showAlert(type, message, data = null) {
            const alertArea = document.getElementById('alertArea');
            let detailsHtml = '';

            if (data && data.statistics) {
                detailsHtml = `
                    <hr>
                    <strong>Statistics:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Total Records: ${data.statistics.total_records || 0}</li>
                        <li>Deleted: ${data.statistics.deleted_records || 0}</li>
                        <li>Inserted: ${data.statistics.inserted_records || 0}</li>
                        <li>Duration: ${data.statistics.duration_seconds || 0}s</li>
                    </ul>
                `;
            }

            alertArea.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show alert-sync" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                    ${message}
                    ${detailsHtml}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }

        // Refresh statistics
        async function refreshStatistics() {
            try {
                const response = await fetch(baseUrl + 'admin/teacher-sync/get-statistics', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const result = await response.json();

                if (result.success) {
                    document.getElementById('sourceCount').textContent =
                        new Intl.NumberFormat().format(result.data.source_count);
                    document.getElementById('targetCount').textContent =
                        new Intl.NumberFormat().format(result.data.target_count);
                }
            } catch (error) {
                console.error('Failed to refresh statistics:', error);
            }
        }

        // View log details (placeholder)
        function viewLogDetails(logId) {
            alert('View details for log #' + logId);
            // TODO: Implement modal with detailed log information
        }

        // Auto-refresh statistics every 30 seconds
        setInterval(refreshStatistics, 30000);

// Generate CSV
document.getElementById('btnGenerateCsv').addEventListener('click', async function() {
    const btn = this;
    const originalText = btn.innerHTML;

    // Disable button
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating...';

    try {
        const response = await fetch(baseUrl + 'admin/teacher-sync/generate-csv', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await response.json();

        if (result.success) {
            showAlert('success', 'CSV generated successfully!', result.data);

            // แสดงข้อมูลไฟล์
            if (result.data.statistics) {
                const stats = result.data.statistics;
                const detailsHtml = `
                    <hr>
                    <strong>File Details:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Records: ${stats.total_records || 0}</li>
                        <li>File: ${stats.csv_file || 'N/A'}</li>
                        <li>Size: ${stats.file_size || 'N/A'}</li>
                        <li>Duration: ${stats.duration_seconds || 0}s</li>
                    </ul>
                    <div class="mt-3">
                        <a href="${baseUrl}admin/teacher-sync/download-csv" class="btn btn-sm btn-success">
                            <i class="fas fa-download me-1"></i> Download CSV
                        </a>
                        <button onclick="previewCsv()" class="btn btn-sm btn-info ms-2">
                            <i class="fas fa-eye me-1"></i> Preview
                        </button>
                    </div>
                `;
                showAlert('success', result.message, null, detailsHtml);
            }

            // Refresh comparison stats
            await refreshComparisonStats();

        } else {
            showAlert('danger', 'CSV generation failed: ' + result.message);
        }

    } catch (error) {
        showAlert('danger', 'Error: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

// Refresh Comparison Statistics
document.getElementById('btnRefreshStats').addEventListener('click', async function() {
    await refreshComparisonStats();
});

// Function: Refresh comparison statistics
async function refreshComparisonStats() {
    const btnRefresh = document.getElementById('btnRefreshStats');
    const originalText = btnRefresh.innerHTML;

    btnRefresh.disabled = true;
    btnRefresh.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Loading...';

    try {
        const response = await fetch(baseUrl + 'admin/teacher-sync/get-comparison-stats', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const result = await response.json();

        if (result.success) {
            const stats = result.data;
            document.getElementById('statAdd').textContent = new Intl.NumberFormat().format(stats.Add || 0);
            document.getElementById('statDel').textContent = new Intl.NumberFormat().format(stats.Del || 0);
            document.getElementById('statMatch').textContent = new Intl.NumberFormat().format(stats.Match || 0);
        }
    } catch (error) {
        console.error('Failed to refresh comparison stats:', error);
    } finally {
        btnRefresh.disabled = false;
        btnRefresh.innerHTML = originalText;
    }
}

// Function: Preview CSV
async function previewCsv() {
    try {
        const response = await fetch(baseUrl + 'admin/teacher-sync/preview-csv?limit=20', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const result = await response.json();

        if (result.success) {
            const preview = result.data.preview;
            const fileInfo = result.data.file_info;

            let previewHtml = '<div class="mt-3"><h6>CSV Preview (first 20 lines):</h6>';
            previewHtml += '<pre class="bg-light p-3" style="max-height: 300px; overflow-y: auto;">';
            previewHtml += preview.join('\n');
            previewHtml += '</pre>';

            if (fileInfo) {
                previewHtml += '<small class="text-muted">';
                previewHtml += `File: ${fileInfo.path}<br>`;
                previewHtml += `Size: ${fileInfo.size}<br>`;
                previewHtml += `Modified: ${fileInfo.modified}`;
                previewHtml += '</small>';
            }

            previewHtml += '</div>';

            showAlert('info', 'CSV File Preview', null, previewHtml);
        }
    } catch (error) {
        showAlert('danger', 'Failed to preview CSV: ' + error.message);
    }
}

// Enhanced showAlert function to support custom HTML
function showAlert(type, message, data = null, customHtml = '') {
    const alertArea = document.getElementById('alertArea');
    let detailsHtml = '';

    if (data && data.statistics && !customHtml) {
        detailsHtml = `
            <hr>
            <strong>Statistics:</strong>
            <ul class="mb-0 mt-2">
                <li>Total Records: ${data.statistics.total_records || 0}</li>
                <li>Deleted: ${data.statistics.deleted_records || 0}</li>
                <li>Inserted: ${data.statistics.inserted_records || 0}</li>
                <li>Duration: ${data.statistics.duration_seconds || 0}s</li>
            </ul>
        `;
    }

    if (customHtml) {
        detailsHtml = customHtml;
    }

    alertArea.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show alert-sync" role="alert">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
            ${message}
            ${detailsHtml}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}

// Auto-load comparison stats on page load
document.addEventListener('DOMContentLoaded', async function() {
    await refreshComparisonStats();
});

// Refresh comparison stats every 60 seconds
setInterval(refreshComparisonStats, 60000);
    </script>
</body>
</html>
