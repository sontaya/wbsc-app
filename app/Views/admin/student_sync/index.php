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
            --info-color: #06b6d4;
        }

        body {
            background-color: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .page-header {
            background: linear-gradient(135deg, var(--info-color) 0%, #0891b2 100%);
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

        .faculty-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 2px solid #e5e7eb;
            transition: all 0.2s;
            cursor: pointer;
        }

        .faculty-card:hover {
            border-color: var(--info-color);
            background: #f0f9ff;
        }

        .faculty-card.selected {
            border-color: var(--success-color);
            background: #f0fdf4;
        }

        .faculty-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
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
            transform: none;
        }

        .badge-count {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .log-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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

        .faculty-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.5rem;
        }

        .stats-badge {
            font-size: 0.85rem;
            padding: 0.25rem 0.6rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">
                        <i class="fas fa-user-graduate me-2"></i>
                        <?= esc($title) ?>
                    </h1>
                    <p class="mb-0 opacity-75">
                        MariaDB (Moodle) → Oracle Database - Incremental Sync
                    </p>
                </div>
                <div>
                    <button id="btnSync" class="btn btn-sync" disabled>
                        <i class="fas fa-sync-alt me-2"></i>
                        Start Sync
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">

        <!-- Alert Area -->
        <div id="alertArea"></div>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small">Total Faculties</p>
                            <h3 class="mb-0 fw-bold"><?= count($facultyConfig) ?></h3>
                            <small class="text-info">
                                <i class="fas fa-building"></i> Available
                            </small>
                        </div>
                        <div class="stat-icon bg-info bg-opacity-10 text-info" style="width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">
                            <i class="fas fa-university"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small">Selected Faculties</p>
                            <h3 class="mb-0 fw-bold" id="selectedCount">0</h3>
                            <small class="text-primary">
                                <i class="fas fa-check-square"></i> Selected
                            </small>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary" style="width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">
                            <i class="fas fa-tasks"></i>
                        </div>
                    </div>
                </div>
            </div>

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
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning" style="width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">
                            <i class="fas fa-history"></i>
                        </div>
                    </div>
                </div>
            </div>

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
                                    <span class="badge bg-<?= $statusClass ?> badge-count">
                                        <?= strtoupper($lastSync['status']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary badge-count">NONE</span>
                                <?php endif; ?>
                            </h6>
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> System Status
                            </small>
                        </div>
                        <div class="stat-icon bg-success bg-opacity-10 text-success" style="width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">
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
            <div id="progressDetails" class="mt-2 small text-muted"></div>
        </div>

        <!-- Faculty Selection -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-list-check me-2"></i>
                            Select Faculties to Sync
                        </h5>
                        <div>
                            <button id="btnSelectAll" class="btn btn-sm btn-outline-primary me-2">
                                <i class="fas fa-check-double me-1"></i>
                                Select All
                            </button>
                            <button id="btnDeselectAll" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>
                                Deselect All
                            </button>
                        </div>
                    </div>

                    <div class="row" id="facultyList">
                        <?php foreach ($facultyConfig as $facultyId => $facultyName): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="faculty-card" data-faculty-id="<?= $facultyId ?>">
                                    <div class="d-flex align-items-start">
                                        <input type="checkbox"
                                               class="faculty-checkbox me-3 mt-1"
                                               id="faculty_<?= $facultyId ?>"
                                               value="<?= $facultyId ?>">
                                        <div class="flex-grow-1">
                                            <label for="faculty_<?= $facultyId ?>" class="fw-bold mb-1" style="cursor: pointer;">
                                                [<?= $facultyId ?>] <?= esc($facultyName) ?>
                                            </label>
                                            <div class="faculty-stats">
                                                <div>
                                                    <span class="badge bg-primary stats-badge"
                                                          data-source-count="<?= $facultyId ?>">
                                                        <i class="fas fa-database"></i>
                                                        Source: <?= number_format($facultyStats[$facultyId]['source_count']) ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <span class="badge bg-info stats-badge"
                                                          data-target-count="<?= $facultyId ?>">
                                                        <i class="fas fa-server"></i>
                                                        Target: <?= number_format($facultyStats[$facultyId]['target_count']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
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
                            <th>Faculties</th>
                            <th>Started</th>
                            <th>Completed</th>
                            <th>Records</th>
                            <th>Changes</th>
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
                                        <span class="badge bg-<?= $badgeClass ?> badge-count">
                                            <?= strtoupper($log['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?= $log['faculties_synced'] ?? 0 ?> faculties
                                        </span>
                                    </td>
                                    <td><?= date('d M y H:i', strtotime($log['started_at'])) ?></td>
                                    <td><?= $log['completed_at'] ? date('d M y H:i', strtotime($log['completed_at'])) : '-' ?></td>
                                    <td>
                                        <?php if (isset($stats['total_records'])): ?>
                                            <span class="badge bg-secondary">
                                                <?= number_format($stats['total_records']) ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($stats['inserted_records'])): ?>
                                            <small>
                                                <span class="text-success me-1">+<?= number_format($stats['inserted_records']) ?></span>
                                                <span class="text-warning me-1">~<?= number_format($stats['updated_records'] ?? 0) ?></span>
                                                <span class="text-danger">-<?= number_format($stats['deleted_records'] ?? 0) ?></span>
                                            </small>
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
                                        <a href="<?= base_url('admin/student-sync/download-log/' . $log['id']) ?>"
                                           class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
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

        // Faculty selection handling
        const facultyCheckboxes = document.querySelectorAll('.faculty-checkbox');
        const selectedCountEl = document.getElementById('selectedCount');
        const btnSync = document.getElementById('btnSync');

        // Update selected count and sync button state
        function updateSelection() {
            const selected = Array.from(facultyCheckboxes).filter(cb => cb.checked);
            selectedCountEl.textContent = selected.length;
            btnSync.disabled = selected.length === 0;

            // Update faculty card styling
            document.querySelectorAll('.faculty-card').forEach(card => {
                const facultyId = card.dataset.facultyId;
                const checkbox = document.getElementById('faculty_' + facultyId);
                if (checkbox && checkbox.checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
        }

        // Faculty checkbox change
        facultyCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelection);
        });

        // Faculty card click
        document.querySelectorAll('.faculty-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (e.target.type !== 'checkbox' && e.target.tagName !== 'LABEL') {
                    const facultyId = this.dataset.facultyId;
                    const checkbox = document.getElementById('faculty_' + facultyId);
                    checkbox.checked = !checkbox.checked;
                    updateSelection();
                }
            });
        });

        // Select all button
        document.getElementById('btnSelectAll').addEventListener('click', function() {
            facultyCheckboxes.forEach(cb => cb.checked = true);
            updateSelection();
        });

        // Deselect all button
        document.getElementById('btnDeselectAll').addEventListener('click', function() {
            facultyCheckboxes.forEach(cb => cb.checked = false);
            updateSelection();
        });

        // Execute Sync
        btnSync.addEventListener('click', async function() {
            const selected = Array.from(facultyCheckboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.value);

            if (selected.length === 0) {
                showAlert('warning', 'Please select at least one faculty to sync');
                return;
            }

            const btn = this;
            const originalText = btn.innerHTML;

            // Confirm
            if (!confirm(`Sync ${selected.length} selected facult${selected.length > 1 ? 'ies' : 'y'}?`)) {
                return;
            }

            // Disable button
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Syncing...';

            // Show progress
            const progressDiv = document.getElementById('syncProgress');
            const progressBar = document.getElementById('progressBar');
            const progressDetails = document.getElementById('progressDetails');
            progressDiv.style.display = 'block';

            // Simulate progress
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90;
                progressBar.style.width = progress + '%';
                document.getElementById('progressPercent').textContent = Math.round(progress) + '%';
            }, 800);

            try {
                const response = await fetch(baseUrl + 'admin/student-sync/execute-sync', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        faculties: selected
                    })
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
                const stats = data.statistics;
                detailsHtml = `
                    <hr>
                    <strong>Overall Statistics:</strong>
                    <ul class="mb-2 mt-2">
                        <li>Faculties Synced: ${stats.faculties_synced || 0}</li>
                        <li>Total Records: ${stats.total_records || 0}</li>
                        <li>Inserted: <span class="text-success">${stats.inserted_records || 0}</span></li>
                        <li>Updated: <span class="text-warning">${stats.updated_records || 0}</span></li>
                        <li>Deleted: <span class="text-danger">${stats.deleted_records || 0}</span></li>
                        <li>Unchanged: ${stats.unchanged_records || 0}</li>
                        <li>Duration: ${stats.duration_seconds || 0}s</li>
                    </ul>
                `;

                if (stats.faculty_details) {
                    detailsHtml += '<strong>Faculty Details:</strong><ul class="small mb-0">';
                    for (const [facultyId, facultyStats] of Object.entries(stats.faculty_details)) {
                        detailsHtml += `<li>${facultyStats.faculty_name}: ${facultyStats.total_records} records
                            (+${facultyStats.inserted_records} ~${facultyStats.updated_records} -${facultyStats.deleted_records})</li>`;
                    }
                    detailsHtml += '</ul>';
                }
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
                const response = await fetch(baseUrl + 'admin/student-sync/get-statistics', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const result = await response.json();

                if (result.success && result.data.faculty_stats) {
                    Object.entries(result.data.faculty_stats).forEach(([facultyId, stats]) => {
                        const sourceEl = document.querySelector(`[data-source-count="${facultyId}"]`);
                        const targetEl = document.querySelector(`[data-target-count="${facultyId}"]`);

                        if (sourceEl) {
                            sourceEl.innerHTML = `<i class="fas fa-database"></i> Source: ${new Intl.NumberFormat().format(stats.source_count)}`;
                        }
                        if (targetEl) {
                            targetEl.innerHTML = `<i class="fas fa-server"></i> Target: ${new Intl.NumberFormat().format(stats.target_count)}`;
                        }
                    });
                }
            } catch (error) {
                console.error('Failed to refresh statistics:', error);
            }
        }

        // View log details (placeholder)
        function viewLogDetails(logId) {
            alert('View details for log #' + logId + '\n\nDetailed faculty breakdown and changes will be shown here.');
            // TODO: Implement modal with detailed log information
        }

        // Auto-refresh statistics every 60 seconds
        setInterval(refreshStatistics, 60000);

        // Initialize selection count
        updateSelection();
    </script>
</body>
</html>