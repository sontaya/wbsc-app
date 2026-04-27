<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Course Image Update') ?></title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 24px; }
        code { background: #f4f4f4; padding: 2px 4px; }
        pre { background: #f8f8f8; border: 1px solid #ddd; padding: 12px; overflow: auto; }
        .panel { border: 1px solid #ddd; padding: 12px; margin: 16px 0; background: #fafafa; }
        .row { margin: 8px 0; }
        label { display: inline-block; min-width: 110px; }
        input[type="text"], select { width: 340px; max-width: 100%; }
        .btn { border: 1px solid #666; background: #fff; padding: 8px 12px; cursor: pointer; margin-right: 8px; }
        .btn.primary { background: #165f2d; border-color: #165f2d; color: #fff; }
        .hint { color: #444; }
    </style>
</head>
<body>
<?= view('admin/_menu') ?>

<h1><?= esc($title ?? 'Course Image Update') ?></h1>
<p>This endpoint supports SSH execution. In Moodle 3.11, API mode cannot update course overview images.</p>
<ul>
    <li><code>GET /admin/course-image/categories</code></li>
    <li><code>GET /admin/course-image/courses-by-category?category_id=289</code></li>
    <li><code>POST /admin/course-image/run</code> multipart with <code>image</code>, <code>mode</code>, and <code>category_id</code> or <code>course_ids</code></li>
</ul>

<div class="panel">
    <h2>Run from GUI</h2>
    <p class="hint">For production run, image file is required. Dry-run checks target scope only.</p>

    <div class="row">
        <label for="mode">Mode</label>
        <select id="mode">
            <option value="ssh">ssh</option>
            <option value="api" disabled>api (not supported on Moodle 3.11)</option>
        </select>
    </div>

    <div class="row">
        <label for="categoryId">Category ID</label>
        <input id="categoryId" type="text" placeholder="e.g. 291">
    </div>

    <div class="row">
        <label for="courseIds">Course IDs</label>
        <input id="courseIds" type="text" placeholder="e.g. 101,102,103">
    </div>

    <div class="row">
        <label for="image">Image File</label>
        <input id="image" type="file" accept="image/*">
    </div>

    <div class="row">
        <button class="btn" onclick="runCourseImage(true)">Start (dry-run)</button>
        <button class="btn primary" onclick="runCourseImage(false)">Start (production)</button>
    </div>

    <pre id="runResult">{"message":"No run yet"}</pre>
</div>

<script>
async function runCourseImage(dryRun) {
    const mode = document.getElementById('mode').value;
    const categoryId = document.getElementById('categoryId').value.trim();
    const courseIds = document.getElementById('courseIds').value.trim();
    const imageInput = document.getElementById('image');
    const result = document.getElementById('runResult');

    if (categoryId === '' && courseIds === '') {
        result.textContent = JSON.stringify({ success: false, message: 'Provide category_id or course_ids' }, null, 2);
        return;
    }

    if (!dryRun && (!imageInput.files || imageInput.files.length === 0)) {
        result.textContent = JSON.stringify({ success: false, message: 'Image file is required for production run' }, null, 2);
        return;
    }

    const formData = new FormData();
    formData.append('mode', mode);
    formData.append('dry_run', dryRun ? 'true' : 'false');
    if (categoryId !== '') {
        formData.append('category_id', categoryId);
    }
    if (courseIds !== '') {
        formData.append('course_ids', courseIds);
    }
    if (imageInput.files && imageInput.files.length > 0) {
        formData.append('image', imageInput.files[0]);
    }

    result.textContent = JSON.stringify({ status: 'running', mode: mode, dry_run: dryRun }, null, 2);

    try {
        const res = await fetch('/admin/course-image/run', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        result.textContent = JSON.stringify(data, null, 2);
    } catch (error) {
        result.textContent = JSON.stringify({ success: false, message: String(error) }, null, 2);
    }
}
</script>
</body>
</html>
