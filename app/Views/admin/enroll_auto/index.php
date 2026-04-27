<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Enroll Auto Import') ?></title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 24px; }
        code { background: #f4f4f4; padding: 2px 4px; }
        pre { background: #f8f8f8; border: 1px solid #ddd; padding: 12px; overflow: auto; }
        .alert { border: 1px solid #e2b93f; background: #fff9e6; padding: 12px; margin: 16px 0; }
        .alert h2 { margin-top: 0; }
        .alert ul { margin: 8px 0 0 18px; }
        .panel { border: 1px solid #ddd; padding: 12px; margin: 16px 0; background: #fafafa; }
        .btn { border: 1px solid #666; background: #fff; padding: 8px 12px; cursor: pointer; margin-right: 8px; }
        .btn.primary { background: #165f2d; border-color: #165f2d; color: #fff; }
        .options { margin: 8px 0; }
        .hint { color: #444; }
        .warn { color: #7a2a00; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
<h1><?= esc($title ?? 'Enroll Auto Import') ?></h1>
<p>Use API endpoints for automation:</p>
<ul>
    <li><code>GET /admin/enroll-auto/preview</code></li>
    <li><code>POST /admin/enroll-auto/run</code> with <code>{"mode":"api|csv","dry_run":true|false,"students_only":false,"teachers_only":false}</code></li>
    <li><code>GET /admin/enroll-auto/logs</code></li>
</ul>

<div class="panel">
    <h2>Run from GUI</h2>
    <p class="hint">Run dry-run first. Production will enroll/unenroll immediately.</p>
    <div class="options">
        <label><input id="studentsOnly" type="checkbox"> Students only</label>
        <label style="margin-left: 12px;"><input id="teachersOnly" type="checkbox"> Teachers only</label>
    </div>
    <p>
        <button class="btn" onclick="runEnrollAuto(true)">Start (dry-run)</button>
        <button class="btn primary" onclick="runEnrollAuto(false)">Start (production)</button>
    </p>
    <pre id="runResult">{"message":"No run yet"}</pre>
</div>

<div class="panel">
    <h2>Teacher Conflict Safety Check</h2>
    <p class="hint">Detect courses where Moodle has extra teachers not present in Oracle (risk of removal when running teacher import).</p>
    <p>
        <button class="btn" onclick="loadTeacherConflicts()">Check teacher conflicts</button>
    </p>
    <div id="teacherConflictContainer" class="hint">No check run yet.</div>
</div>

<?php if (!empty($statusWarning['has_warning'])): ?>
<div class="alert">
    <h2>Warning: Skipped Users Found</h2>
    <p>
        Latest run (log #<?= esc((string) ($statusWarning['latest_log_id'] ?? '-')) ?>)
        skipped <strong><?= esc((string) ($statusWarning['skipped'] ?? 0)) ?></strong> records.
    </p>
    <ul>
        <?php foreach (($statusWarning['reason_counts'] ?? []) as $reason => $count): ?>
            <li><code><?= esc((string) $reason) ?></code>: <?= esc((string) $count) ?></li>
        <?php endforeach; ?>
        <?php if (!empty($statusWarning['missing_courses'])): ?>
            <li>
                Missing courses in Moodle:
                <code><?= esc(implode(', ', (array) $statusWarning['missing_courses'])) ?></code>
            </li>
        <?php endif; ?>
        <?php if (!empty($statusWarning['empty_username'])): ?>
            <li>
                Empty username records: <?= esc((string) $statusWarning['empty_username']) ?>
                (verify source data in Oracle view)
            </li>
        <?php endif; ?>
    </ul>
    <p>
        Recommended: create missing courses first (<code>/admin/course-auto</code>) and run
        <code>student-sync</code>/<code>teacher-sync</code> after import.
    </p>
</div>
<?php endif; ?>

<h2>Preview</h2>
<pre><?= esc(json_encode($preview ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

<h2>Recent Logs</h2>
<pre><?= esc(json_encode($logs ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

<script>
let teacherConflictData = null;

function escapeHtml(input) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(input ?? '').replace(/[&<>"']/g, (m) => map[m]);
}

function getSelectedTeacherExclusions() {
    const out = [];
    const checks = document.querySelectorAll('.teacher-conflict-check:checked');
    checks.forEach((el) => {
        const shortname = String(el.getAttribute('data-course') || '').trim();
        if (shortname !== '') {
            out.push(shortname);
        }
    });
    return out;
}

function renderTeacherConflicts() {
    const container = document.getElementById('teacherConflictContainer');
    const data = teacherConflictData;

    if (!data || !Array.isArray(data.items)) {
        container.textContent = 'No data.';
        return;
    }

    if (data.items.length === 0) {
        container.innerHTML = `<p class="hint">No teacher conflicts found. Safe courses: ${Number(data.safe_courses || 0)}</p>`;
        return;
    }

    let rows = '';
    data.items.forEach((item) => {
        const moodleNames = (item.moodle_teachers || [])
            .map((t) => escapeHtml(t.fullname || t.username || 'Unknown'))
            .join(', ');
        const extraNames = (item.moodle_extra_teachers || [])
            .map((t) => escapeHtml(t.fullname || t.username || 'Unknown'))
            .join(', ');
        const oracleNames = (item.oracle_expected_teachers || [])
            .map((t) => escapeHtml(t.fullname || t.username || 'Unknown'))
            .join(', ');

        rows += `<tr>
            <td><input type="checkbox" class="teacher-conflict-check" data-course="${escapeHtml(item.course_shortname)}" checked></td>
            <td>${escapeHtml(item.course_shortname)}</td>
            <td>${escapeHtml(item.course_fullname || '')}</td>
            <td>${Number(item.oracle_expected_count || 0)}</td>
            <td>${Number(item.moodle_total_count || 0)}</td>
            <td>${Number(item.moodle_extra_count || 0)}</td>
            <td>${moodleNames || '-'}</td>
            <td>${extraNames || '-'}</td>
            <td>${oracleNames || '-'}</td>
        </tr>`;
    });

    container.innerHTML = `
        <p class="warn">Found ${Number(data.conflict_courses || 0)} conflict courses. Checked rows will be excluded from teacher enroll/unenroll run.</p>
        <table>
            <thead>
                <tr>
                    <th>Exclude</th>
                    <th>Course shortname</th>
                    <th>Course fullname</th>
                    <th>Oracle teachers</th>
                    <th>Moodle teachers</th>
                    <th>Moodle extra</th>
                    <th>Moodle names</th>
                    <th>Moodle extra names</th>
                    <th>Oracle names</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
    `;
}

async function loadTeacherConflicts() {
    const container = document.getElementById('teacherConflictContainer');
    container.textContent = 'Checking teacher conflicts...';

    try {
        const res = await fetch('/admin/enroll-auto/teacher-conflicts');
        const data = await res.json();
        if (!data.success) {
            container.textContent = 'Check failed: ' + JSON.stringify(data);
            return;
        }

        teacherConflictData = data.data;
        renderTeacherConflicts();
    } catch (error) {
        container.textContent = 'Check failed: ' + String(error);
    }
}

async function runEnrollAuto(dryRun) {
    const studentsOnly = document.getElementById('studentsOnly').checked;
    const teachersOnly = document.getElementById('teachersOnly').checked;
    const el = document.getElementById('runResult');

    if (studentsOnly && teachersOnly) {
        el.textContent = JSON.stringify({ success: false, message: 'Choose only one scope: students_only or teachers_only' }, null, 2);
        return;
    }

    const payload = {
        mode: 'api',
        dry_run: dryRun,
        students_only: studentsOnly,
        teachers_only: teachersOnly,
        exclude_teacher_courses: studentsOnly ? [] : getSelectedTeacherExclusions(),
    };

    el.textContent = JSON.stringify({ status: 'running', payload: payload }, null, 2);

    try {
        const res = await fetch('/admin/enroll-auto/run', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await res.json();
        el.textContent = JSON.stringify(data, null, 2);
    } catch (error) {
        el.textContent = JSON.stringify({ success: false, message: String(error) }, null, 2);
    }
}
</script>
</body>
</html>
