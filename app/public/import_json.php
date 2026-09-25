<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role($pdo, ['admin']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $file = $_FILES['backup'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'กรุณาเลือกไฟล์สำรองข้อมูล (.json)';
    } else {
        $data = json_decode((string) file_get_contents($file['tmp_name']), true);
        $incomingProjects = $data['projects'] ?? null;
        $incomingUsers = $data['users'] ?? null;

        $validProjects = is_array($incomingProjects) && array_reduce(
            $incomingProjects,
            fn ($ok, $p) => $ok && is_array($p) && !empty($p['title']) && isset($p['steps']) && is_array($p['steps']),
            true
        );
        $validUsers = $incomingUsers === null || (
            is_array($incomingUsers) &&
            array_reduce($incomingUsers, fn ($ok, $u) => $ok || ($u['role'] === 'admin' && $u['status'] === 'active' && !empty($u['password_hash'])), false)
        );

        if (!$validProjects) {
            $error = 'ไฟล์ไม่ถูกต้อง ไม่สามารถนำเข้าได้ (รูปแบบโครงงานไม่ถูกต้อง)';
        } elseif (!$validUsers) {
            $error = 'ไฟล์ไม่ถูกต้อง ไม่มีบัญชีผู้ดูแลระบบที่ใช้งานได้ในข้อมูลผู้ใช้';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->exec('DELETE FROM projects');

                if (is_array($incomingUsers)) {
                    $pdo->exec('DELETE FROM users');
                    $insU = $pdo->prepare(
                        'INSERT INTO users (id, name, email, password_hash, role, status, student_id, level, student_group, created_at)
                         VALUES (:id, :name, :email, :hash, :role, :status, :sid, :level, :grp, :created)'
                    );
                    foreach ($incomingUsers as $u) {
                        $insU->execute([
                            'id' => $u['id'] ?? null, 'name' => $u['name'] ?? '', 'email' => mb_strtolower($u['email'] ?? ''),
                            'hash' => $u['password_hash'] ?? password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT),
                            'role' => $u['role'] ?? 'student', 'status' => $u['status'] ?? 'pending',
                            'sid' => $u['student_id'] ?? null, 'level' => $u['level'] ?? null, 'grp' => $u['student_group'] ?? null,
                            'created' => $u['created_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                    }
                }

                $validUserIds = array_column($pdo->query('SELECT id FROM users')->fetchAll(), 'id');
                $insP = $pdo->prepare(
                    'INSERT INTO projects (id, title, title_en, code, type, level, student_group, advisor, co_advisor,
                     start_date, due_date, note, member_names, steps, evaluation)
                     VALUES (:id, :title, :title_en, :code, :type, :level, :grp, :advisor, :co_advisor,
                     :start_date, :due_date, :note, :member_names, :steps, :evaluation)'
                );
                $insMember = $pdo->prepare('INSERT IGNORE INTO project_members (project_id, user_id) VALUES (?, ?)');
                $insLog = $pdo->prepare('INSERT INTO project_logs (project_id, log_date, text, by_name) VALUES (?, ?, ?, ?)');

                foreach ($incomingProjects as $p) {
                    $steps = array_pad(array_map('boolval', $p['steps']), count(STEPS), false);
                    $insP->execute([
                        'id' => $p['id'] ?? null, 'title' => $p['title'], 'title_en' => $p['title_en'] ?? '',
                        'code' => $p['code'] ?? '', 'type' => $p['type'] ?? '', 'level' => $p['level'] ?? '',
                        'grp' => $p['student_group'] ?? '', 'advisor' => $p['advisor'] ?? '', 'co_advisor' => $p['co_advisor'] ?? '',
                        'start_date' => $p['start_date'] ?? null, 'due_date' => $p['due_date'] ?? null, 'note' => $p['note'] ?? '',
                        'member_names' => json_encode($p['member_names'] ?? [], JSON_UNESCAPED_UNICODE),
                        'steps' => json_encode(array_values($steps)),
                        'evaluation' => !empty($p['evaluation']) ? json_encode($p['evaluation'], JSON_UNESCAPED_UNICODE) : null,
                    ]);
                    $pid = (int) $pdo->lastInsertId();
                    foreach (($p['member_ids'] ?? []) as $uid) {
                        if (in_array($uid, $validUserIds, false)) $insMember->execute([$pid, $uid]);
                    }
                    foreach (($p['logs'] ?? []) as $log) {
                        $insLog->execute([$pid, $log['log_date'] ?? date('Y-m-d'), $log['text'] ?? '', $log['by_name'] ?? '']);
                    }
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            // ถ้าบัญชีผู้ดูแลที่กำลังใช้งานอยู่ไม่มีอยู่ในข้อมูลที่นำเข้าแล้ว ให้ออกจากระบบเพื่อความปลอดภัย
            $stillExists = $pdo->prepare('SELECT id FROM users WHERE id = ?');
            $stillExists->execute([$user['id']]);
            if (!$stillExists->fetch()) {
                logout_user();
                flash_set('ok', 'นำเข้าข้อมูลแล้ว กรุณาเข้าสู่ระบบใหม่');
                redirect('login.php');
            }

            flash_set('ok', 'นำเข้าข้อมูล ' . count($incomingProjects) . ' โครงงานแล้ว');
            redirect('projects.php');
        }
    }
}

$pageTitle = 'นำเข้าข้อมูล | ระบบติดตามโครงงานนักเรียน';
$activeView = 'projects';
require __DIR__ . '/../includes/layout/head.php';
require __DIR__ . '/../includes/layout/app_nav.php';
?>
<main class="wrap">
  <div class="panel" style="max-width:560px;margin:0 auto">
    <h2>📥 นำเข้าข้อมูล (JSON)</h2>
    <p style="color:var(--muted)">ไฟล์ที่ได้จากปุ่ม “สำรองข้อมูล (JSON)” เท่านั้น ข้อมูลโครงงานปัจจุบันทั้งหมดจะถูกแทนที่ด้วยข้อมูลในไฟล์ที่เลือก</p>
    <?php if ($error): ?><div class="msg err" style="margin-bottom:12px">🚫 <?= esc($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" data-confirm="ยืนยันการนำเข้าข้อมูล?" data-confirm-text="ข้อมูลโครงงาน (และผู้ใช้ ถ้ามีในไฟล์) ปัจจุบันทั้งหมดจะถูกแทนที่" data-confirm-button="นำเข้าข้อมูล">
      <?= csrf_field() ?>
      <label class="f full">ไฟล์สำรองข้อมูล (.json)
        <input type="file" name="backup" accept=".json,application/json" required>
      </label>
      <div class="form-actions">
        <a class="btn ghost" href="projects.php">ยกเลิก</a>
        <button class="btn" type="submit">📥 นำเข้าข้อมูล</button>
      </div>
    </form>
  </div>
</main>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
