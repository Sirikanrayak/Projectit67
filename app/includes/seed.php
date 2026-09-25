<?php
declare(strict_types=1);

// รองรับฐานข้อมูลที่สร้างไว้ก่อนมี site settings — ขยายคอลัมน์และเติมค่าเริ่มต้นให้เสมอ (ปลอดภัยที่จะรันซ้ำ)
function ensure_site_settings(PDO $pdo): void
{
    $col = $pdo->query("SHOW COLUMNS FROM settings LIKE 'value'")->fetch();
    if ($col && stripos($col['Type'], 'text') === false) {
        $pdo->exec('ALTER TABLE settings MODIFY `value` TEXT');
    }

    $defaults = [
        'require_approval' => '1',
        'site_name' => 'ระบบติดตามโครงงานนักเรียน',
        'site_subtitle' => 'สาขาวิชาเทคโนโลยีสารสนเทศ · วิทยาลัยเทคนิคนครนายก',
        'site_logo' => '',
        'site_logo_text' => 'IT',
        'footer_text' => '©ศิริกัลยา 2565 แผนกวิชาเทคโนโลยีสารสนเทศ วิทยาลัยเทคนิคนครนายก · ข้อมูลบันทึกลงฐานข้อมูล MySQL',
    ];
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (`key`, `value`) VALUES (:k, :v)');
    foreach ($defaults as $k => $v) {
        $stmt->execute(['k' => $k, 'v' => $v]);
    }
}

function seed_if_empty(PDO $pdo): void
{
    $hasAdmin = (int) $pdo->query("SELECT COUNT(*) c FROM users WHERE role='admin'")->fetch()['c'];
    if ($hasAdmin > 0) return;

    $insertUser = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, status, student_id, level, student_group)
         VALUES (:name, :email, :hash, :role, :status, :student_id, :level, :student_group)'
    );

    $mkUser = function (array $data, string $password) use ($insertUser, $pdo): int {
        $insertUser->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'hash' => password_hash($password, PASSWORD_BCRYPT),
            'role' => $data['role'],
            'status' => $data['status'],
            'student_id' => $data['student_id'] ?? null,
            'level' => $data['level'] ?? null,
            'student_group' => $data['student_group'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    };

    $adminId = $mkUser([
        'name' => 'ผู้ดูแลระบบ', 'email' => 'admin@nayoktech.ac.th', 'role' => 'admin', 'status' => 'active',
    ], 'Admin@2565');
    $teacherId = $mkUser([
        'name' => 'ครูจงจิต บูรณศรี', 'email' => 'teacher@nayoktech.ac.th', 'role' => 'teacher', 'status' => 'active',
    ], 'Teacher@2565');
    $studentId = $mkUser([
        'name' => 'นายธนวัฒน์ ศรีสุข', 'email' => 'student@nayoktech.ac.th', 'role' => 'student', 'status' => 'active',
        'student_id' => '66301040001', 'level' => 'ปวส.2', 'student_group' => '1',
    ], 'Student@2565');
    $mkUser([
        'name' => 'ครูนฤมล ผลจันทร์', 'email' => 'teacher2@nayoktech.ac.th', 'role' => 'teacher', 'status' => 'active',
    ], 'Teacher@2565');

    $today = new DateTime('today');
    $addDays = fn (int $n) => (clone $today)->modify(($n >= 0 ? '+' : '') . $n . ' days')->format('Y-m-d');

    $insertProject = $pdo->prepare(
        'INSERT INTO projects (title, title_en, code, type, level, student_group, advisor, co_advisor, start_date, due_date, note, member_names, steps, evaluation)
         VALUES (:title, :title_en, :code, :type, :level, :student_group, :advisor, :co_advisor, :start_date, :due_date, :note, :member_names, :steps, :evaluation)'
    );
    $insertLog = $pdo->prepare('INSERT INTO project_logs (project_id, log_date, text, by_name) VALUES (:pid, :date, :text, :by)');
    $insertMember = $pdo->prepare('INSERT INTO project_members (project_id, user_id) VALUES (:pid, :uid)');

    $steps9 = fn (int $doneCount) => array_map(fn ($i) => $i < $doneCount, range(0, count(STEPS) - 1));

    $samples = [
        [
            'title' => 'ระบบจองห้องปฏิบัติการคอมพิวเตอร์ออนไลน์', 'title_en' => 'Online Computer Lab Booking System',
            'code' => 'IT-01', 'type' => 'พัฒนาเว็บไซต์/เว็บแอปพลิเคชัน', 'level' => 'ปวส.2', 'student_group' => '1',
            'advisor' => 'ครูจงจิต บูรณศรี', 'co_advisor' => '', 'start_date' => $addDays(-60), 'due_date' => $addDays(30),
            'note' => 'PHP, MySQL, Bootstrap', 'member_names' => [], 'done' => 5, 'member_user_id' => $studentId,
            'logs' => [['date' => $addDays(-5), 'text' => 'ออกแบบฐานข้อมูลเสร็จ เริ่มพัฒนาหน้าจองห้อง']],
        ],
        [
            'title' => 'แอปพลิเคชันเช็คชื่อเข้าแถวด้วย QR Code', 'title_en' => '', 'code' => 'IT-02',
            'type' => 'แอปพลิเคชันบนมือถือ', 'level' => 'ปวส.2', 'student_group' => '1',
            'advisor' => 'ครูนฤมล ผลจันทร์', 'co_advisor' => '', 'start_date' => $addDays(-60), 'due_date' => $addDays(12),
            'note' => 'Flutter, Firebase', 'member_names' => ['นายกิตติพงษ์ ทองดี', 'นายณัฐวุฒิ บุญมา'], 'done' => 4,
            'logs' => [['date' => $addDays(-2), 'text' => 'สอบความก้าวหน้า 50% ผ่าน ให้ปรับหน้ารายงาน']],
        ],
        [
            'title' => 'ระบบรดน้ำต้นไม้อัตโนมัติด้วย IoT', 'title_en' => '', 'code' => 'IT-03',
            'type' => 'IoT / สมองกลฝังตัว', 'level' => 'ปวช.3', 'student_group' => '1',
            'advisor' => 'ครูจงจิต บูรณศรี', 'co_advisor' => '', 'start_date' => $addDays(-60), 'due_date' => $addDays(-3),
            'note' => 'ESP32, Blynk, เซนเซอร์ความชื้นในดิน',
            'member_names' => ['นายภูมิพัฒน์ จันทร์เพ็ญ', 'นายศุภกร สายทอง', 'นายอนุชา ดีมาก'], 'done' => 3,
            'logs' => [['date' => $addDays(-10), 'text' => 'รอสั่งซื้ออุปกรณ์เซนเซอร์']],
        ],
        [
            'title' => 'ระบบคัดแยกขยะด้วยปัญญาประดิษฐ์จากภาพถ่าย', 'title_en' => '', 'code' => 'IT-04',
            'type' => 'ระบบปัญญาประดิษฐ์', 'level' => 'ปวช.3', 'student_group' => '1',
            'advisor' => 'ครูนฤมล ผลจันทร์', 'co_advisor' => '', 'start_date' => $addDays(-60), 'due_date' => $addDays(-10),
            'note' => 'Python, TensorFlow, Teachable Machine',
            'member_names' => ['นางสาวกมลชนก ใจงาม', 'นางสาวอรอุมา พรหมมา'], 'done' => 9,
            'logs' => [['date' => $addDays(-12), 'text' => 'ส่งรูปเล่มฉบับสมบูรณ์เรียบร้อย']],
            'evaluation' => [
                'scores' => ['content' => 23, 'creativity' => 22, 'execution' => 24, 'presentation' => 21],
                'comment' => 'ผลงานสมบูรณ์ ใช้งานได้จริง ควรเพิ่มความแม่นยำของโมเดลในสภาพแสงน้อย',
                'by' => 'ครูนฤมล ผลจันทร์', 'date' => $addDays(-9),
            ],
        ],
        [
            'title' => 'ระบบบริหารจัดการครุภัณฑ์แผนก', 'title_en' => '', 'code' => 'IT-05',
            'type' => 'ระบบฐานข้อมูล', 'level' => 'ปวส.2', 'student_group' => '1',
            'advisor' => 'ครูจงจิต บูรณศรี', 'co_advisor' => '', 'start_date' => $addDays(-3), 'due_date' => $addDays(45),
            'note' => 'Laravel, MariaDB', 'member_names' => ['นายจักรพันธ์ รุ่งเรือง'], 'done' => 0, 'logs' => [],
        ],
    ];

    foreach ($samples as $s) {
        $insertProject->execute([
            'title' => $s['title'], 'title_en' => $s['title_en'], 'code' => $s['code'], 'type' => $s['type'],
            'level' => $s['level'], 'student_group' => $s['student_group'], 'advisor' => $s['advisor'],
            'co_advisor' => $s['co_advisor'], 'start_date' => $s['start_date'], 'due_date' => $s['due_date'],
            'note' => $s['note'], 'member_names' => json_encode($s['member_names'], JSON_UNESCAPED_UNICODE),
            'steps' => json_encode($steps9($s['done'])),
            'evaluation' => isset($s['evaluation']) ? json_encode($s['evaluation'], JSON_UNESCAPED_UNICODE) : null,
        ]);
        $pid = (int) $pdo->lastInsertId();
        foreach ($s['logs'] as $log) {
            $insertLog->execute(['pid' => $pid, 'date' => $log['date'], 'text' => $log['text'], 'by' => $s['advisor']]);
        }
        if (!empty($s['member_user_id'])) {
            $insertMember->execute(['pid' => $pid, 'uid' => $s['member_user_id']]);
        }
    }

    unset($adminId, $teacherId);
}
