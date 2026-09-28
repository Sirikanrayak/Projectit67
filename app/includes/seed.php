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

// รองรับฐานข้อมูลที่สร้างไว้ก่อนมีระบบ "ครูผู้สอนโครงงาน" — สร้างตารางถ้ายังไม่มี (ปลอดภัยที่จะรันซ้ำ)
function ensure_teacher_assignments(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS teacher_assignments (
          id             INT AUTO_INCREMENT PRIMARY KEY,
          user_id        INT NOT NULL,
          level          VARCHAR(20) NOT NULL,
          student_group  VARCHAR(20) NOT NULL,
          created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_teacher_level_group (user_id, level, student_group),
          CONSTRAINT fk_ta_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

// รองรับฐานข้อมูลที่สร้างไว้ก่อนมีระบบกำกับคุณภาพโครงงานด้านเทคโนโลยีสารสนเทศ — สร้างตารางถ้ายังไม่มี (ปลอดภัยที่จะรันซ้ำ)
function ensure_project_qc(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS project_qc (
          id            INT AUTO_INCREMENT PRIMARY KEY,
          project_id    INT NOT NULL,
          step_key      VARCHAR(20) NOT NULL,
          status        ENUM('pass','fail') NOT NULL,
          signed_by     VARCHAR(150) NOT NULL DEFAULT '',
          signed_date   DATE DEFAULT NULL,
          note          TEXT,
          updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_project_step (project_id, step_key),
          CONSTRAINT fk_qc_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

// รองรับฐานข้อมูลที่สร้างไว้ก่อนมีระบบเสนอชื่อโครงงานและรายงานความก้าวหน้าเป็นรอบทางการ — สร้างตารางถ้ายังไม่มี (ปลอดภัยที่จะรันซ้ำ)
function ensure_proposals_and_progress(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS project_proposals (
          id                   INT AUTO_INCREMENT PRIMARY KEY,
          student_id           INT NOT NULL,
          level                VARCHAR(20) NOT NULL DEFAULT '',
          student_group        VARCHAR(20) NOT NULL DEFAULT '',
          member_emails        TEXT,
          status               ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
          approved_project_id  INT DEFAULT NULL,
          reviewed_by          VARCHAR(150) NOT NULL DEFAULT '',
          reviewed_at          DATETIME DEFAULT NULL,
          review_note          TEXT,
          created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_pp_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
          CONSTRAINT fk_pp_project FOREIGN KEY (approved_project_id) REFERENCES projects(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS project_proposal_items (
          id            INT AUTO_INCREMENT PRIMARY KEY,
          proposal_id   INT NOT NULL,
          seq           TINYINT NOT NULL,
          title         VARCHAR(255) NOT NULL,
          method        TEXT,
          benefit       TEXT,
          is_selected   TINYINT(1) NOT NULL DEFAULT 0,
          CONSTRAINT fk_ppi_proposal FOREIGN KEY (proposal_id) REFERENCES project_proposals(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS project_progress_rounds (
          id              INT AUTO_INCREMENT PRIMARY KEY,
          project_id      INT NOT NULL,
          round_no        TINYINT NOT NULL,
          plan            TEXT,
          submitted_work  TEXT,
          submitted_date  DATE DEFAULT NULL,
          rating          VARCHAR(10) DEFAULT NULL,
          evaluated_by    VARCHAR(150) NOT NULL DEFAULT '',
          evaluated_date  DATE DEFAULT NULL,
          UNIQUE KEY uniq_project_round (project_id, round_no),
          CONSTRAINT fk_ppr_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS project_defense_requests (
          project_id       INT PRIMARY KEY,
          requested_by     VARCHAR(150) NOT NULL DEFAULT '',
          requested_date   DATE DEFAULT NULL,
          instructor_note  TEXT,
          instructor_by    VARCHAR(150) NOT NULL DEFAULT '',
          instructor_date  DATE DEFAULT NULL,
          CONSTRAINT fk_pdr_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
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
