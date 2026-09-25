<?php
require __DIR__ . '/../includes/bootstrap.php';

if (current_user($pdo)) redirect('dashboard.php');

$error = '';
$okMsg = '';
$role = 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $role = ($_POST['role'] ?? 'student') === 'teacher' ? 'teacher' : 'student';
    $isStudent = $role === 'student';
    $name = trim($_POST['name'] ?? '');
    $studentId = trim($_POST['student_id'] ?? '');
    $level = trim($_POST['level'] ?? '');
    $group = trim($_POST['group'] ?? '');
    $email = mb_strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');

    $stmtEmail = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmtEmail->execute([$email]);
    $stmtSid = $pdo->prepare('SELECT id FROM users WHERE student_id = ? AND student_id <> ""');
    $stmtSid->execute([$studentId]);
    $stmtTeacherName = $pdo->prepare("SELECT id FROM users WHERE role = 'teacher' AND name = ?");
    $stmtTeacherName->execute([$name]);

    if ($name === '' || ($isStudent && $studentId === '') || $email === '' || $password === '') {
        $error = 'กรุณากรอกข้อมูลที่มีเครื่องหมาย * ให้ครบ';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'รูปแบบอีเมลไม่ถูกต้อง';
    } elseif ($stmtEmail->fetch()) {
        $error = 'อีเมลนี้ถูกใช้สมัครแล้ว';
    } elseif ($isStudent && $studentId !== '' && $stmtSid->fetch()) {
        $error = 'รหัสนักเรียนนี้ถูกใช้สมัครแล้ว';
    } elseif (!$isStudent && $stmtTeacherName->fetch()) {
        $error = 'มีบัญชีครูที่ปรึกษาชื่อนี้แล้ว';
    } elseif (password_error($password)) {
        $error = password_error($password);
    } elseif ($password !== $password2) {
        $error = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
    } else {
        $requireApproval = $pdo->query("SELECT value FROM settings WHERE `key`='require_approval'")->fetch()['value'] ?? '1';
        $status = (!$isStudent || $requireApproval === '1') ? 'pending' : 'active';

        $ins = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, status, student_id, level, student_group)
             VALUES (:name, :email, :hash, :role, :status, :sid, :level, :grp)'
        );
        $ins->execute([
            'name' => $name, 'email' => $email, 'hash' => password_hash($password, PASSWORD_BCRYPT),
            'role' => $role, 'status' => $status,
            'sid' => $isStudent ? $studentId : null, 'level' => $isStudent ? $level : null, 'grp' => $isStudent ? $group : null,
        ]);

        if ($status === 'active') {
            $newId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$newId]);
            login_user($stmt->fetch());
            redirect('dashboard.php');
        } else {
            $okMsg = 'สมัครใช้งานสำเร็จ กรุณารอผู้ดูแลระบบอนุมัติบัญชีก่อนเข้าสู่ระบบ';
        }
    }
}

$pageTitle = 'สมัครใช้งาน | ' . site_setting($pdo, 'site_name');
require __DIR__ . '/../includes/layout/head.php';
$siteName = site_setting($pdo, 'site_name');
$siteSubtitle = site_setting($pdo, 'site_subtitle');
$logoUrl = site_logo_url($pdo);
?>
<button type="button" class="hbtn theme-toggle-auth" id="btnTheme" title="สลับโหมดกลางวัน/กลางคืน">🌙</button>
<div class="auth">
  <div class="auth-card">
    <div class="auth-head">
      <?php if ($logoUrl): ?>
        <img class="logo logo-img" src="<?= esc($logoUrl) ?>" alt="<?= esc($siteName) ?>">
      <?php else: ?>
        <div class="logo"><?= esc(site_setting($pdo, 'site_logo_text')) ?></div>
      <?php endif; ?>
      <h1><?= esc($siteName) ?></h1>
      <?php if ($siteSubtitle !== ''): ?><p><?= esc($siteSubtitle) ?></p><?php endif; ?>
    </div>
    <div class="auth-tabs">
      <a href="login.php">เข้าสู่ระบบ</a>
      <a class="active" href="register.php">สมัครใช้งาน</a>
    </div>
    <form class="auth-body" method="post" action="register.php" novalidate>
      <?= csrf_field() ?>
      <?php if ($error): ?><div class="msg err">🚫 <?= esc($error) ?></div><?php endif; ?>
      <?php if ($okMsg): ?><div class="msg ok">✅ <?= esc($okMsg) ?></div><?php endif; ?>
      <div class="form-grid">
        <label class="f full">สมัครในฐานะ
          <select name="role" onchange="document.querySelectorAll('.reg-student').forEach(e=>e.hidden=this.value==='teacher');document.querySelectorAll('.reg-teacher').forEach(e=>e.hidden=this.value!=='teacher')">
            <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>นักเรียน/นักศึกษา</option>
            <option value="teacher" <?= $role === 'teacher' ? 'selected' : '' ?>>ครูที่ปรึกษา</option>
          </select>
        </label>
        <label class="f full">ชื่อ-นามสกุล <span class="req">*</span>
          <small class="reg-teacher" <?= $role !== 'teacher' ? 'hidden' : '' ?>>(ต้องตรงกับชื่อครูที่ปรึกษาในโครงงาน เช่น ครูจงจิต บูรณศรี)</small>
          <input type="text" name="name" placeholder="นายสมชาย ใจดี" value="<?= esc($_POST['name'] ?? '') ?>">
        </label>
        <label class="f full reg-student" <?= $role === 'teacher' ? 'hidden' : '' ?>>รหัสนักเรียน/นักศึกษา <span class="req">*</span>
          <input type="text" name="student_id" inputmode="numeric" value="<?= esc($_POST['student_id'] ?? '') ?>">
        </label>
        <label class="f reg-student" <?= $role === 'teacher' ? 'hidden' : '' ?>>ระดับชั้น
          <select name="level">
            <?php foreach (LEVELS as $lv): ?><option <?= ($_POST['level'] ?? '') === $lv ? 'selected' : '' ?>><?= esc($lv) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="f reg-student" <?= $role === 'teacher' ? 'hidden' : '' ?>>กลุ่มเรียน
          <input type="text" name="group" placeholder="เช่น 1" value="<?= esc($_POST['group'] ?? '') ?>">
        </label>
        <p class="hint full reg-teacher" style="text-align:left" <?= $role !== 'teacher' ? 'hidden' : '' ?>>บัญชีครูที่ปรึกษาต้องรอผู้ดูแลระบบอนุมัติก่อนใช้งานทุกครั้ง</p>
        <label class="f full">อีเมล <span class="req">*</span>
          <input type="email" name="email" autocomplete="email" placeholder="you@example.com" value="<?= esc($_POST['email'] ?? '') ?>">
        </label>
        <label class="f">รหัสผ่าน <span class="req">*</span>
          <input type="password" name="password" autocomplete="new-password">
        </label>
        <label class="f">ยืนยันรหัสผ่าน <span class="req">*</span>
          <input type="password" name="password2" autocomplete="new-password">
        </label>
        <div class="pw-rules full"></div>
      </div>
      <button class="btn" type="submit">📝 สมัครใช้งาน</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
