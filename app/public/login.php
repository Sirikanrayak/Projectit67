<?php
require __DIR__ . '/../includes/bootstrap.php';

if (current_user($pdo)) redirect('dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = mb_strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'กรุณากรอกอีเมลและรหัสผ่าน';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        if (!$u || !password_verify($password, $u['password_hash'])) {
            $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
        } elseif ($u['status'] === 'pending') {
            $error = 'บัญชีของคุณกำลังรอผู้ดูแลระบบอนุมัติ';
        } elseif ($u['status'] === 'suspended') {
            $error = 'บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';
        } else {
            login_user($u);
            redirect('dashboard.php');
        }
    }
}

$pageTitle = 'เข้าสู่ระบบ | ระบบติดตามโครงงานนักเรียน';
require __DIR__ . '/../includes/layout/head.php';
?>
<div class="auth">
  <div class="auth-card">
    <div class="auth-head">
      <div class="logo">IT</div>
      <h1>ระบบติดตามโครงงานนักเรียน</h1>
      <p>สาขาวิชาเทคโนโลยีสารสนเทศ · วิทยาลัยเทคนิคนครนายก</p>
    </div>
    <div class="auth-tabs">
      <a class="active" href="login.php">เข้าสู่ระบบ</a>
      <a href="register.php">สมัครใช้งาน</a>
    </div>
    <form class="auth-body" method="post" action="login.php" novalidate>
      <?= csrf_field() ?>
      <?php if ($error): ?><div class="msg err">🚫 <?= esc($error) ?></div><?php endif; ?>
      <label class="f">อีเมล
        <input type="email" name="email" autocomplete="username" placeholder="you@example.com" value="<?= esc($_POST['email'] ?? '') ?>">
      </label>
      <label class="f">รหัสผ่าน
        <input type="password" name="password" autocomplete="current-password">
      </label>
      <button class="btn" type="submit">🔑 เข้าสู่ระบบ</button>
      <p class="hint">ลืมรหัสผ่าน? ติดต่อครูผู้ดูแลระบบเพื่อตั้งรหัสผ่านใหม่</p>
      <p class="hint">บัญชีทดลอง: admin@nayoktech.ac.th / Admin@2565</p>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
