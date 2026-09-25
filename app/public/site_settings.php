<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role($pdo, ['admin']);

$logoDir = __DIR__ . '/assets/branding';
$errors = [];

$values = [
    'site_name' => site_setting($pdo, 'site_name'),
    'site_subtitle' => site_setting($pdo, 'site_subtitle'),
    'site_logo_text' => site_setting($pdo, 'site_logo_text'),
    'footer_text' => site_setting($pdo, 'footer_text'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $values['site_name'] = trim($_POST['site_name'] ?? '');
    $values['site_subtitle'] = trim($_POST['site_subtitle'] ?? '');
    $values['site_logo_text'] = mb_substr(trim($_POST['site_logo_text'] ?? ''), 0, 4);
    $values['footer_text'] = trim($_POST['footer_text'] ?? '');

    if ($values['site_name'] === '') $errors[] = 'กรุณากรอกชื่อระบบ';
    if ($values['site_logo_text'] === '') $errors[] = 'กรุณากรอกตัวอักษรย่อของโลโก้';

    $newLogoFile = null;
    $removeLogo = isset($_POST['remove_logo']);
    $upload = $_FILES['logo'] ?? null;

    if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'อัปโหลดโลโก้ไม่สำเร็จ';
        } elseif ($upload['size'] > MAX_LOGO_BYTES) {
            $errors[] = 'ไฟล์โลโก้มีขนาดเกิน ' . fmt_bytes(MAX_LOGO_BYTES);
        } else {
            $info = @getimagesize($upload['tmp_name']);
            $mime = $info['mime'] ?? '';
            if (!$info || !isset(ALLOWED_LOGO_MIME[$mime])) {
                $errors[] = 'รองรับเฉพาะไฟล์รูปภาพ PNG, JPG, WEBP หรือ GIF เท่านั้น';
            } else {
                $ext = ALLOWED_LOGO_MIME[$mime];
                $newLogoFile = 'logo_' . bin2hex(random_bytes(10)) . '.' . $ext;
                if (!is_dir($logoDir)) mkdir($logoDir, 0775, true);
                if (!move_uploaded_file($upload['tmp_name'], $logoDir . '/' . $newLogoFile)) {
                    $errors[] = 'ไม่สามารถบันทึกไฟล์โลโก้ได้';
                    $newLogoFile = null;
                }
            }
        }
    }

    if (!$errors) {
        $oldLogo = site_setting($pdo, 'site_logo');

        set_site_setting($pdo, 'site_name', $values['site_name']);
        set_site_setting($pdo, 'site_subtitle', $values['site_subtitle']);
        set_site_setting($pdo, 'site_logo_text', $values['site_logo_text']);
        set_site_setting($pdo, 'footer_text', $values['footer_text']);

        if ($newLogoFile) {
            set_site_setting($pdo, 'site_logo', $newLogoFile);
            if ($oldLogo && is_file($logoDir . '/' . $oldLogo)) unlink($logoDir . '/' . $oldLogo);
        } elseif ($removeLogo && $oldLogo) {
            set_site_setting($pdo, 'site_logo', '');
            if (is_file($logoDir . '/' . $oldLogo)) unlink($logoDir . '/' . $oldLogo);
        }

        flash_set('ok', 'บันทึกการตั้งค่าระบบแล้ว');
        redirect('site_settings.php');
    }
}

$currentLogo = site_logo_url($pdo);
$pageTitle = 'ตั้งค่าระบบ | ' . site_setting($pdo, 'site_name');
$activeView = 'settings';
require __DIR__ . '/../includes/layout/head.php';
require __DIR__ . '/../includes/layout/app_nav.php';
?>
<main class="wrap">
  <div class="panel" style="max-width:640px;margin:0 auto">
    <h2>⚙️ ตั้งค่าระบบ</h2>
    <form method="post" enctype="multipart/form-data" novalidate style="margin-top:14px">
      <?= csrf_field() ?>
      <?php if ($errors): ?><div class="msg err" style="margin-bottom:12px">🚫 <?= esc(implode(' · ', $errors)) ?></div><?php endif; ?>

      <div class="section-title" style="margin-top:0">โลโก้ระบบ</div>
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:14px;flex-wrap:wrap">
        <div class="logo-preview">
          <?php if ($currentLogo): ?>
            <img src="<?= esc($currentLogo) ?>" alt="โลโก้ปัจจุบัน">
          <?php else: ?>
            <span><?= esc($values['site_logo_text']) ?></span>
          <?php endif; ?>
        </div>
        <div style="flex:1 1 240px;display:grid;gap:10px">
          <label class="file-drop" style="padding:10px">
            📎 คลิกเพื่อเลือกไฟล์โลโก้ใหม่ <small style="display:block">PNG, JPG, WEBP หรือ GIF · ไม่เกิน <?= fmt_bytes(MAX_LOGO_BYTES) ?></small>
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif">
          </label>
          <?php if ($currentLogo): ?>
            <label class="check"><input type="checkbox" name="remove_logo" value="1"> ลบโลโก้ปัจจุบัน (กลับไปใช้ตัวอักษรย่อแทน)</label>
          <?php endif; ?>
          <label class="f">ตัวอักษรย่อ <small>(แสดงแทนโลโก้เมื่อยังไม่ได้อัปโหลดรูป เช่น "IT")</small>
            <input type="text" name="site_logo_text" maxlength="4" value="<?= esc($values['site_logo_text']) ?>" style="max-width:120px">
          </label>
        </div>
      </div>

      <div class="section-title">ข้อมูลทั่วไป</div>
      <div class="form-grid">
        <label class="f full">ชื่อระบบ <span class="req">*</span>
          <input type="text" name="site_name" value="<?= esc($values['site_name']) ?>">
        </label>
        <label class="f full">ชื่อหน่วยงาน/สถาบัน <small>(แสดงใต้ชื่อระบบในส่วนหัว)</small>
          <input type="text" name="site_subtitle" value="<?= esc($values['site_subtitle']) ?>">
        </label>
        <label class="f full">ข้อความท้ายหน้าเว็บ (footer)
          <textarea name="footer_text" rows="2"><?= esc($values['footer_text']) ?></textarea>
        </label>
      </div>

      <div class="form-actions">
        <a class="btn ghost" href="dashboard.php">ยกเลิก</a>
        <button class="btn" type="submit">💾 บันทึก</button>
      </div>
    </form>
  </div>
</main>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
