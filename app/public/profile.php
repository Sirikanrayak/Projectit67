<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);

$error = '';
$values = ['name' => $user['name'], 'student_id' => $user['student_id'], 'level' => $user['level'], 'group' => $user['student_group']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $studentId = trim($_POST['student_id'] ?? '');
    $level = trim($_POST['level'] ?? '');
    $group = trim($_POST['group'] ?? '');
    $current = (string) ($_POST['current'] ?? '');
    $newPw = (string) ($_POST['password'] ?? '');
    $newPw2 = (string) ($_POST['password2'] ?? '');
    $values = ['name' => $name, 'student_id' => $studentId, 'level' => $level, 'group' => $group];

    $stmtSid = $pdo->prepare('SELECT id FROM users WHERE student_id = ? AND student_id <> "" AND id <> ?');
    $stmtSid->execute([$studentId, $user['id']]);
    $stmtName = $pdo->prepare("SELECT id FROM users WHERE role='teacher' AND name = ? AND id <> ?");
    $stmtName->execute([$name, $user['id']]);

    if ($name === '') {
        $error = 'กรุณากรอกชื่อ-นามสกุล';
    } elseif ($user['role'] === 'student' && $studentId !== '' && $stmtSid->fetch()) {
        $error = 'รหัสนักเรียนนี้มีผู้ใช้แล้ว';
    } elseif ($user['role'] === 'teacher' && $stmtName->fetch()) {
        $error = 'มีบัญชีครูที่ปรึกษาชื่อนี้แล้ว';
    } elseif (($newPw || $current) && !password_verify($current, $user['password_hash'])) {
        $error = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
    } elseif ($newPw && password_error($newPw)) {
        $error = password_error($newPw);
    } elseif ($newPw && $newPw !== $newPw2) {
        $error = 'รหัสผ่านใหม่ทั้งสองช่องไม่ตรงกัน';
    } else {
        if ($user['role'] === 'teacher') rename_advisor_everywhere($pdo, $user['name'], $name);
        $sql = 'UPDATE users SET name = :name';
        $params = ['name' => $name, 'id' => $user['id']];
        if ($user['role'] === 'student') {
            $sql .= ', student_id = :sid, level = :level, student_group = :grp';
            $params += ['sid' => $studentId, 'level' => $level, 'grp' => $group];
        }
        if ($newPw) {
            $sql .= ', password_hash = :hash';
            $params['hash'] = password_hash($newPw, PASSWORD_BCRYPT);
        }
        $sql .= ' WHERE id = :id';
        $pdo->prepare($sql)->execute($params);
        flash_set('ok', 'บันทึกข้อมูลบัญชีแล้ว');
        redirect('profile.php');
    }
}

$pageTitle = 'บัญชีของฉัน | ระบบติดตามโครงงานนักเรียน';
$activeView = '';
require __DIR__ . '/../includes/layout/head.php';
require __DIR__ . '/../includes/layout/app_nav.php';
?>
<main class="wrap">
  <div class="panel" style="max-width:560px;margin:0 auto">
    <h2>👤 บัญชีของฉัน</h2>
    <form method="post" novalidate style="margin-top:14px">
      <?= csrf_field() ?>
      <?php if ($error): ?><div class="msg err" style="margin-bottom:12px">🚫 <?= esc($error) ?></div><?php endif; ?>
      <div class="form-grid">
        <label class="f full">อีเมล
          <input type="email" value="<?= esc($user['email']) ?>" disabled>
        </label>
        <label class="f full">ชื่อ-นามสกุล <span class="req">*</span>
          <input type="text" name="name" value="<?= esc($values['name']) ?>">
        </label>
        <?php if ($user['role'] === 'student'): ?>
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
        <?php endif; ?>
      </div>
      <div class="section-title">เปลี่ยนรหัสผ่าน <small style="font-weight:400;color:var(--muted)">(เว้นว่างถ้าไม่ต้องการเปลี่ยน)</small></div>
      <div class="form-grid">
        <label class="f full">รหัสผ่านปัจจุบัน
          <input type="password" name="current" autocomplete="current-password">
        </label>
        <label class="f">รหัสผ่านใหม่
          <input type="password" name="password" autocomplete="new-password">
        </label>
        <label class="f">ยืนยันรหัสผ่านใหม่
          <input type="password" name="password2" autocomplete="new-password">
        </label>
        <div class="pw-rules full"></div>
      </div>
      <div class="form-actions">
        <a class="btn ghost" href="dashboard.php">ยกเลิก</a>
        <button class="btn" type="submit">💾 บันทึก</button>
      </div>
    </form>
  </div>
</main>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
