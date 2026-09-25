<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role($pdo, ['admin']);

$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editUser = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();
    if (!$editUser) { flash_set('err', 'ไม่พบผู้ใช้'); redirect('users.php'); }
}

$errors = [];
$values = $editUser ? [
    'name' => $editUser['name'], 'email' => $editUser['email'], 'role' => $editUser['role'], 'status' => $editUser['status'],
    'student_id' => $editUser['student_id'], 'level' => $editUser['level'], 'group' => $editUser['student_group'],
] : ['name' => '', 'email' => '', 'role' => 'student', 'status' => 'active', 'student_id' => '', 'level' => '', 'group' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['name', 'role', 'status', 'student_id', 'level', 'group'] as $k) $values[$k] = trim($_POST[$k] ?? '');
    $values['email'] = mb_strtolower(trim($_POST['email'] ?? ''));
    $pw = (string) ($_POST['password'] ?? '');

    $stmtEmail = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
    $stmtEmail->execute([$values['email'], $editId ?? 0]);
    $stmtSid = $pdo->prepare('SELECT id FROM users WHERE student_id = ? AND student_id <> "" AND id <> ?');
    $stmtSid->execute([$values['student_id'], $editId ?? 0]);
    $stmtTeacherName = $pdo->prepare("SELECT id FROM users WHERE role='teacher' AND name = ? AND id <> ?");
    $stmtTeacherName->execute([$values['name'], $editId ?? 0]);

    if ($values['name'] === '' || $values['email'] === '') {
        $errors[] = 'กรุณากรอกชื่อและอีเมล';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
    } elseif ($stmtEmail->fetch()) {
        $errors[] = 'อีเมลนี้มีผู้ใช้แล้ว';
    } elseif ($values['student_id'] !== '' && $stmtSid->fetch()) {
        $errors[] = 'รหัสนักเรียนนี้มีผู้ใช้แล้ว';
    } elseif ((!$editUser || $pw) && password_error($pw)) {
        $errors[] = password_error($pw);
    } elseif ($editUser && (int) $editUser['id'] === (int) $user['id'] && ($values['role'] !== 'admin' || $values['status'] !== 'active')) {
        $errors[] = 'ไม่สามารถลดสิทธิ์หรือระงับบัญชีของตัวเองได้';
    } elseif ($values['role'] === 'teacher' && $stmtTeacherName->fetch()) {
        $errors[] = 'มีบัญชีครูที่ปรึกษาชื่อนี้แล้ว';
    }

    if (!$errors) {
        if ($editUser) {
            if ($editUser['role'] === 'teacher' && $values['role'] === 'teacher') {
                rename_advisor_everywhere($pdo, $editUser['name'], $values['name']);
            }
            $sql = 'UPDATE users SET name=:name, email=:email, role=:role, status=:status, student_id=:sid, level=:level, student_group=:grp';
            $params = [
                'name' => $values['name'], 'email' => $values['email'], 'role' => $values['role'], 'status' => $values['status'],
                'sid' => $values['student_id'] ?: null, 'level' => $values['level'] ?: null, 'grp' => $values['group'] ?: null,
                'id' => $editUser['id'],
            ];
            if ($pw) { $sql .= ', password_hash=:hash'; $params['hash'] = password_hash($pw, PASSWORD_BCRYPT); }
            $sql .= ' WHERE id=:id';
            $pdo->prepare($sql)->execute($params);
            flash_set('ok', 'บันทึกข้อมูลผู้ใช้แล้ว');
        } else {
            $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, status, student_id, level, student_group)
                 VALUES (:name, :email, :hash, :role, :status, :sid, :level, :grp)'
            )->execute([
                'name' => $values['name'], 'email' => $values['email'], 'hash' => password_hash($pw, PASSWORD_BCRYPT),
                'role' => $values['role'], 'status' => $values['status'],
                'sid' => $values['student_id'] ?: null, 'level' => $values['level'] ?: null, 'grp' => $values['group'] ?: null,
            ]);
            flash_set('ok', 'เพิ่มผู้ใช้แล้ว');
        }
        redirect('users.php');
    }
}

$pageTitle = ($editUser ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้') . ' | ' . site_setting($pdo, 'site_name');
$activeView = 'users';
require __DIR__ . '/../includes/layout/head.php';
require __DIR__ . '/../includes/layout/app_nav.php';
?>
<main class="wrap">
  <div class="panel" style="max-width:560px;margin:0 auto">
    <h2><?= $editUser ? '✏️ แก้ไขผู้ใช้' : '➕ เพิ่มผู้ใช้' ?></h2>
    <form method="post" novalidate style="margin-top:14px">
      <?= csrf_field() ?>
      <?php if ($errors): ?><div class="msg err" style="margin-bottom:12px">🚫 <?= esc(implode(' · ', $errors)) ?></div><?php endif; ?>
      <div class="form-grid">
        <label class="f full">ชื่อ-นามสกุล <span class="req">*</span>
          <input type="text" name="name" value="<?= esc($values['name']) ?>">
        </label>
        <label class="f full">อีเมล <span class="req">*</span>
          <input type="email" name="email" value="<?= esc($values['email']) ?>">
        </label>
        <label class="f">บทบาท
          <select name="role">
            <?php foreach (ROLE_TEXT as $k => $t): ?><option value="<?= $k ?>" <?= $values['role'] === $k ? 'selected' : '' ?>><?= esc($t) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="f">สถานะ
          <select name="status">
            <?php foreach (USER_STATUS_TEXT as $k => $t): ?><option value="<?= $k ?>" <?= $values['status'] === $k ? 'selected' : '' ?>><?= esc($t) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="f full">รหัสนักเรียน/นักศึกษา
          <input type="text" name="student_id" value="<?= esc((string) $values['student_id']) ?>">
        </label>
        <label class="f">ระดับชั้น
          <select name="level">
            <option value="">-</option>
            <?php foreach (LEVELS as $lv): ?><option <?= $values['level'] === $lv ? 'selected' : '' ?>><?= esc($lv) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="f">กลุ่มเรียน
          <input type="text" name="group" value="<?= esc((string) $values['group']) ?>">
        </label>
        <label class="f full"><?= $editUser ? 'ตั้งรหัสผ่านใหม่ <small>(เว้นว่างถ้าไม่ต้องการเปลี่ยน)</small>' : 'รหัสผ่าน <span class="req">*</span>' ?>
          <input type="password" name="password" autocomplete="new-password">
        </label>
        <div class="pw-rules full"></div>
      </div>
      <div class="form-actions">
        <a class="btn ghost" href="users.php">ยกเลิก</a>
        <button class="btn" type="submit">💾 บันทึก</button>
      </div>
    </form>
  </div>
</main>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
