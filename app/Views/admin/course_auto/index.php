<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Course Auto Create') ?></title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 24px; }
        code { background: #f4f4f4; padding: 2px 4px; }
        pre { background: #f8f8f8; border: 1px solid #ddd; padding: 12px; overflow: auto; }
        .panel { border: 1px solid #ddd; padding: 12px; margin: 16px 0; background: #fafafa; }
        .btn { border: 1px solid #666; background: #fff; padding: 8px 12px; cursor: pointer; margin-right: 8px; }
        .btn.primary { background: #165f2d; border-color: #165f2d; color: #fff; }
        .hint { color: #444; }
        .warn { color: #7a2a00; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f0f0f0; }
        select { min-width: 260px; }
    </style>
</head>
<body>
<h1><?= esc($title ?? 'Course Auto Create') ?></h1>
<p>Use API endpoints for automation:</p>
<ul>
    <li><code>GET /admin/course-auto/preview</code></li>
    <li><code>POST /admin/course-auto/run</code> with <code>{"mode":"api|csv","dry_run":true|false}</code></li>
    <li><code>GET /admin/course-auto/logs</code></li>
</ul>

<div class="panel">
    <h2>Run from GUI</h2>
    <p class="hint">Use <strong>dry-run</strong> to preview before production run.</p>
    <p>
        <button class="btn" onclick="runCourseAuto(true)">Start (dry-run)</button>
        <button class="btn primary" onclick="runCourseAuto(false)">Start (production)</button>
    </p>
    <pre id="runResult">{"message":"No run yet"}</pre>
</div>

<div class="panel">
    <h2>Orphan Courses (Missing Faculty Mapping)</h2>
    <p class="hint">For courses where Oracle faculty is NULL/unmapped, select a Moodle category manually and create them here.</p>
    <p>
        <button class="btn" onclick="loadOrphanCourses()">Load orphan courses</button>
        <button class="btn primary" onclick="createSelectedOrphans()">Create selected</button>
    </p>
    <div id="orphanContainer" class="hint">No data loaded.</div>
    <pre id="manualResult">{"message":"No manual create yet"}</pre>
</div>

<h2>Recent Logs</h2>
<pre><?= esc(json_encode($logs ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

<script>
const categoryOptions = <?= json_encode($categoryOptions ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let orphanItems = [];

async function runCourseAuto(dryRun) {
    const el = document.getElementById('runResult');
    el.textContent = JSON.stringify({ status: 'running', dry_run: dryRun }, null, 2);

    try {
        const res = await fetch('/admin/course-auto/run', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mode: 'api', dry_run: dryRun })
        });

        const data = await res.json();
        el.textContent = JSON.stringify(data, null, 2);
    } catch (error) {
        el.textContent = JSON.stringify({ success: false, message: String(error) }, null, 2);
    }
}

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

function renderOrphanCourses() {
    const container = document.getElementById('orphanContainer');

    if (!Array.isArray(orphanItems) || orphanItems.length === 0) {
        container.innerHTML = '<p class="hint">No orphan courses found.</p>';
        return;
    }

    let selectable = 0;
    orphanItems.forEach((item) => {
        if (!item.exists_in_moodle) {
            selectable++;
        }
    });

    const optionHtml = ['<option value="">Select category</option>']
        .concat(categoryOptions.map((opt) =>
            `<option value="${Number(opt.id)}">${escapeHtml(opt.name)} (#${Number(opt.id)})</option>`
        ))
        .join('');

    let rowsHtml = '';
    orphanItems.forEach((item, index) => {
        const exists = item.exists_in_moodle === true;
        const faculty = item.faculty_name === null || item.faculty_name === '' ? 'NULL' : item.faculty_name;

        rowsHtml += `<tr>
            <td><input type="checkbox" class="orphan-check" data-index="${index}" ${exists ? 'disabled' : ''}></td>
            <td>${escapeHtml(item.course_shortname)}</td>
            <td>${escapeHtml(item.course_fullname)}</td>
            <td>${escapeHtml(faculty)}</td>
            <td>${exists ? 'Yes' : 'No'}</td>
            <td>
                <select class="orphan-category" data-index="${index}" ${exists ? 'disabled' : ''}>
                    ${optionHtml}
                </select>
            </td>
        </tr>`;
    });

    container.innerHTML = `
        <p class="hint">Found ${orphanItems.length} orphan courses. ${selectable} still missing in Moodle and can be created.</p>
        <table>
            <thead>
                <tr>
                    <th>Select</th>
                    <th>Course shortname</th>
                    <th>Course fullname</th>
                    <th>Oracle faculty</th>
                    <th>Exists in Moodle</th>
                    <th>Target category</th>
                </tr>
            </thead>
            <tbody>${rowsHtml}</tbody>
        </table>
        <p class="warn">Courses marked "Exists in Moodle = Yes" are read-only here and will not be created again.</p>
    `;
}

async function loadOrphanCourses() {
    const container = document.getElementById('orphanContainer');
    container.textContent = 'Loading orphan courses...';

    try {
        const res = await fetch('/admin/course-auto/preview');
        const data = await res.json();

        if (!data.success) {
            container.textContent = 'Failed to load preview: ' + JSON.stringify(data);
            return;
        }

        orphanItems = data.data?.skipped_no_category_items || [];
        renderOrphanCourses();
    } catch (error) {
        container.textContent = 'Failed to load orphan courses: ' + String(error);
    }
}

async function createSelectedOrphans() {
    const resultEl = document.getElementById('manualResult');
    const checks = document.querySelectorAll('.orphan-check:checked');

    if (checks.length === 0) {
        resultEl.textContent = JSON.stringify({ success: false, message: 'No courses selected.' }, null, 2);
        return;
    }

    const items = [];
    const missingCategory = [];

    checks.forEach((checkEl) => {
        const index = Number(checkEl.getAttribute('data-index'));
        const item = orphanItems[index];
        const selectEl = document.querySelector(`.orphan-category[data-index="${index}"]`);
        const categoryId = Number(selectEl?.value || 0);

        if (!item || categoryId <= 0) {
            if (item?.course_shortname) {
                missingCategory.push(item.course_shortname);
            }
            return;
        }

        items.push({
            course_shortname: item.course_shortname,
            course_fullname: item.course_fullname,
            category_id: categoryId
        });
    });

    if (items.length === 0) {
        resultEl.textContent = JSON.stringify({
            success: false,
            message: 'Please select target category for selected courses.',
            missing_category_courses: missingCategory
        }, null, 2);
        return;
    }

    resultEl.textContent = JSON.stringify({ status: 'running', selected: items.length }, null, 2);

    try {
        const res = await fetch('/admin/course-auto/create-manual', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items })
        });

        const data = await res.json();
        resultEl.textContent = JSON.stringify(data, null, 2);

        if (data.success) {
            await loadOrphanCourses();
        }
    } catch (error) {
        resultEl.textContent = JSON.stringify({ success: false, message: String(error) }, null, 2);
    }
}
</script>
</body>
</html>
