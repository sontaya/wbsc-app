<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WBSC Admin Menu</title>
    <style>
        :root {
            --bg-1: #e0f2fe;
            --bg-2: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --primary: #0369a1;
            --primary-2: #0284c7;
            --line: #cbd5e1;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: linear-gradient(135deg, var(--bg-1) 0%, var(--bg-2) 60%);
            color: var(--text);
        }

        .container {
            max-width: 980px;
            margin: 0 auto;
            padding: 28px 16px 36px;
        }

        .hero {
            background: #0f172a;
            color: #ffffff;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.22);
        }

        .hero h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }

        .hero p {
            margin: 0;
            color: #cbd5e1;
        }

        .menu-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        .menu-btn {
            text-decoration: none;
            color: var(--text);
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 16px;
            display: block;
            box-shadow: 0 4px 14px rgba(2, 8, 23, 0.08);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .menu-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(2, 8, 23, 0.12);
            border-color: #7dd3fc;
        }

        .menu-btn strong {
            display: block;
            margin-bottom: 4px;
            color: var(--primary);
        }

        .menu-btn span {
            font-size: 13px;
            color: #334155;
        }
    </style>
</head>
<body>
    <div class="container">
        <section class="hero">
            <h1>WBSC Sync System</h1>
            <p>เลือกเมนูเพื่อเข้าใช้งานแต่ละระบบ</p>
        </section>

        <section class="menu-grid">
            <a class="menu-btn" href="<?= esc(site_url('admin/teacher-sync')) ?>">
                <strong>Teacher Sync</strong>
                <span>จัดการ sync ข้อมูลอาจารย์</span>
            </a>
            <a class="menu-btn" href="<?= esc(site_url('admin/student-sync')) ?>">
                <strong>Student Sync</strong>
                <span>จัดการ sync ข้อมูลนักศึกษา</span>
            </a>
            <a class="menu-btn" href="<?= esc(site_url('admin/course-auto')) ?>">
                <strong>Course Auto</strong>
                <span>สร้างรายวิชาอัตโนมัติ</span>
            </a>
            <a class="menu-btn" href="<?= esc(site_url('admin/enroll-auto')) ?>">
                <strong>Enroll Auto</strong>
                <span>นำเข้าการลงทะเบียนอัตโนมัติ</span>
            </a>
            <a class="menu-btn" href="<?= esc(site_url('admin/course-image')) ?>">
                <strong>Course Image</strong>
                <span>อัปเดตรูปภาพรายวิชา</span>
            </a>
            <a class="menu-btn" href="<?= esc(site_url('admin/group-sync')) ?>">
                <strong>Group Sync</strong>
                <span>ซิงก์กลุ่มเรียน</span>
            </a>
            <a class="menu-btn" href="<?= esc(site_url('admin/status')) ?>">
                <strong>Status</strong>
                <span>ดูสถานะภาพรวมระบบ</span>
            </a>
        </section>
    </div>
</body>
</html>
