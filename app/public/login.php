<?php
require __DIR__ . '/../includes/bootstrap.php';

if (current_user($pdo)) redirect('dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $login = trim($_POST['login'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if ($login === '' || $password === '') {
        $error = 'กรุณากรอกอีเมล/รหัสนักเรียน และรหัสผ่าน';
    } else {
        // รองรับทั้งเข้าสู่ระบบด้วยอีเมล (บุคลากร) และรหัสนักเรียน (นักเรียน) ในช่องเดียวกัน
        $stmt = $pdo->prepare(
            'SELECT * FROM users WHERE email = :email
             OR (student_id IS NOT NULL AND student_id <> "" AND student_id = :sid) LIMIT 1'
        );
        $stmt->execute(['email' => mb_strtolower($login), 'sid' => $login]);
        $u = $stmt->fetch();
        if (!$u || !password_verify($password, $u['password_hash'])) {
            $error = 'ข้อมูลเข้าสู่ระบบหรือรหัสผ่านไม่ถูกต้อง';
        } elseif ($u['status'] === 'pending') {
            $error = 'บัญชีของคุณกำลังรอผู้ดูแลระบบอนุมัติ';
        } elseif ($u['status'] === 'suspended') {
            $error = 'บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';
        } else {
            login_user($u, $remember);
            redirect('dashboard.php');
        }
    }
}

$pageTitle = 'เข้าสู่ระบบ | ' . site_setting($pdo, 'site_name');
require __DIR__ . '/../includes/layout/head.php';
$siteName = site_setting($pdo, 'site_name');
$siteSubtitle = site_setting($pdo, 'site_subtitle');
$logoUrl = site_logo_url($pdo);
?>
<button type="button" class="hbtn theme-toggle-auth" id="btnTheme" title="สลับโหมดกลางวัน/กลางคืน">🌙</button>
<div class="auth auth-split">
  <div class="auth-shell">
    <div class="auth-hero">
      <div class="auth-hero-mark">🎓</div>
      <h2><?= esc($siteName) ?></h2>
      <p><?= $siteSubtitle !== '' ? esc($siteSubtitle) : 'แพลตฟอร์มดิจิทัลสำหรับติดตามความคืบหน้าโครงงาน ตารางส่งงาน และประกาศของสาขาวิชา' ?></p>
    </div>
    <div class="auth-panel">
      <div class="auth-head">
        <?php if ($logoUrl): ?>
          <img class="logo logo-img" src="<?= esc($logoUrl) ?>" alt="<?= esc($siteName) ?>">
        <?php else: ?>
          <div class="logo"><?= esc(site_setting($pdo, 'site_logo_text')) ?></div>
        <?php endif; ?>
        <h1><?= esc($siteName) ?></h1>
        <p>เข้าสู่ระบบเพื่อใช้งาน</p>
      </div>

      <div class="role-tabs" id="roleTabs">
        <button type="button" class="active" data-role="student">👤 นักเรียน</button>
        <button type="button" data-role="staff">🧑‍🏫 บุคลากร</button>
      </div>

      <form class="auth-body" method="post" action="login.php" novalidate>
        <?= csrf_field() ?>
        <?php if ($error): ?><div class="msg err">🚫 <?= esc($error) ?></div><?php endif; ?>
        <label class="f">
          <span id="loginLabel">🎫 รหัสนักเรียน</span>
          <input type="text" name="login" id="loginInput" autocomplete="username" placeholder="เช่น 66301040001" value="<?= esc($_POST['login'] ?? '') ?>">
        </label>
        <label class="f">รหัสผ่าน
          <input type="password" name="password" autocomplete="current-password">
        </label>
        <div class="auth-row">
          <label class="check"><input type="checkbox" name="remember"> จำฉันไว้</label>
          <a href="#" id="forgotLink">ลืมรหัสผ่าน?</a>
        </div>
        <button class="btn" type="submit">🔒 เข้าสู่ระบบ</button>
        <p class="security-note">🛡️ ระบบมีความปลอดภัยตามมาตรฐานสถานศึกษา</p>
        <p class="hint">ยังไม่มีบัญชี? <a href="register.php">สมัครใช้งาน</a></p>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
