<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'System Status') ?></title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f8fafc; }
        .container { max-width: 980px; margin: 0 auto; padding: 20px 16px 30px; }
        .panel { background: #fff; border: 1px solid #dbe2ea; border-radius: 10px; padding: 16px; }
        .panel h1 { margin-top: 0; }
        .btn { border: 1px solid #0f766e; background: #0f766e; color: #fff; padding: 8px 12px; border-radius: 8px; cursor: pointer; }
        pre { background: #0f172a; color: #e2e8f0; padding: 12px; border-radius: 8px; overflow: auto; min-height: 220px; }
        .hint { color: #334155; }
    </style>
</head>
<body>
<?= view('admin/_menu') ?>

<div class="container">
    <div class="panel">
        <h1><?= esc($title ?? 'System Status') ?></h1>
        <p class="hint">เรียกข้อมูลจาก <code>/admin/status/data</code></p>
        <p>
            <button class="btn" onclick="loadStatusData()">Refresh</button>
        </p>
        <pre id="statusResult">{"message":"No data loaded"}</pre>
    </div>
</div>

<script>
async function loadStatusData() {
    const result = document.getElementById('statusResult');
    result.textContent = JSON.stringify({ status: 'loading' }, null, 2);

    try {
        const res = await fetch('/admin/status/data');
        const data = await res.json();
        result.textContent = JSON.stringify(data, null, 2);
    } catch (error) {
        result.textContent = JSON.stringify({ success: false, message: String(error) }, null, 2);
    }
}

loadStatusData();
</script>
</body>
</html>
