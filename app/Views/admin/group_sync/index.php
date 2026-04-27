<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Group Sync') ?></title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 24px; }
        code { background: #f4f4f4; padding: 2px 4px; }
        pre { background: #f8f8f8; border: 1px solid #ddd; padding: 12px; overflow: auto; }
        .panel { border: 1px solid #ddd; padding: 12px; margin: 16px 0; background: #fafafa; }
        .btn { border: 1px solid #666; background: #fff; padding: 8px 12px; cursor: pointer; margin-right: 8px; }
        .btn.primary { background: #165f2d; border-color: #165f2d; color: #fff; }
        .hint { color: #444; }
    </style>
</head>
<body>
<h1><?= esc($title ?? 'Group Sync') ?></h1>
<ul>
    <li><code>GET /admin/group-sync/preview</code></li>
    <li><code>POST /admin/group-sync/run</code> with <code>{"mode":"api|csv","dry_run":true|false}</code></li>
    <li><code>GET /admin/group-sync/logs</code></li>
</ul>

<div class="panel">
    <h2>Run from GUI</h2>
    <p class="hint">Run dry-run first. Production will move users between groups immediately.</p>
    <p>
        <button class="btn" onclick="runGroupSync(true)">Start (dry-run)</button>
        <button class="btn primary" onclick="runGroupSync(false)">Start (production)</button>
    </p>
    <pre id="runResult">{"message":"No run yet"}</pre>
</div>

<h2>Preview</h2>
<pre><?= esc(json_encode($preview ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

<h2>Recent Logs</h2>
<pre><?= esc(json_encode($logs ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

<script>
async function runGroupSync(dryRun) {
    const el = document.getElementById('runResult');
    el.textContent = JSON.stringify({ status: 'running', dry_run: dryRun }, null, 2);

    try {
        const res = await fetch('/admin/group-sync/run', {
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
</script>
</body>
</html>
