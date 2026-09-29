<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('projects.php');
csrf_check();

$pid = (int) ($_POST['project_id'] ?? 0);
$project = get_project($pdo, $pid);

if (!$project || !project_can_edit($project, $user) || $user['role'] === 'student') {
    flash_set('err', 'คุณไม่มีสิทธิ์แก้ไขการแสดงผลของโครงงานนี้');
    redirect('project_detail.php?id=' . $pid);
}

$showcaseDir = __DIR__ . '/assets/showcase';
$errors = [];

$enabled = isset($_POST['showcase_enabled']) ? 1 : 0;
$link = trim($_POST['showcase_link'] ?? '');
$desc = trim($_POST['showcase_desc'] ?? '');

if ($link !== '' && !preg_match('#^https?://#i', $link)) {
    $errors[] = 'ลิงก์ต้องขึ้นต้นด้วย http:// หรือ https://';
}
if (word_count_th($desc) > SHOWCASE_DESC_MAX_WORDS) {
    $errors[] = 'คำอธิบายผลงานยาวเกิน ' . SHOWCASE_DESC_MAX_WORDS . ' คำ';
}

$newImageFile = null;
$removeImage = isset($_POST['remove_image']);
$upload = $_FILES['showcase_image'] ?? null;

if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'อัปโหลดรูปภาพไม่สำเร็จ';
    } elseif ($upload['size'] > MAX_SHOWCASE_IMAGE_BYTES) {
        $errors[] = 'ไฟล์รูปภาพมีขนาดเกิน ' . fmt_bytes(MAX_SHOWCASE_IMAGE_BYTES);
    } else {
        $info = @getimagesize($upload['tmp_name']);
        $mime = $info['mime'] ?? '';
        if (!$info || !isset(ALLOWED_LOGO_MIME[$mime])) {
            $errors[] = 'รองรับเฉพาะไฟล์รูปภาพ PNG, JPG, WEBP หรือ GIF เท่านั้น';
        } else {
            $ext = ALLOWED_LOGO_MIME[$mime];
            $newImageFile = 'proj' . $pid . '_' . bin2hex(random_bytes(10)) . '.' . $ext;
            if (!is_dir($showcaseDir)) mkdir($showcaseDir, 0775, true);
            if (!move_uploaded_file($upload['tmp_name'], $showcaseDir . '/' . $newImageFile)) {
                $errors[] = 'ไม่สามารถบันทึกไฟล์รูปภาพได้';
                $newImageFile = null;
            }
        }
    }
}

if (!$errors) {
    $oldImage = $project['showcase_image'];
    $imageToSave = $oldImage;
    if ($newImageFile) {
        $imageToSave = $newImageFile;
        if ($oldImage && is_file($showcaseDir . '/' . $oldImage)) unlink($showcaseDir . '/' . $oldImage);
    } elseif ($removeImage && $oldImage) {
        $imageToSave = '';
        if (is_file($showcaseDir . '/' . $oldImage)) unlink($showcaseDir . '/' . $oldImage);
    }

    $pdo->prepare(
        'UPDATE projects SET showcase_enabled = :enabled, showcase_image = :image,
         showcase_link = :link, showcase_desc = :desc WHERE id = :id'
    )->execute([
        'enabled' => $enabled, 'image' => $imageToSave, 'link' => $link, 'desc' => $desc, 'id' => $pid,
    ]);
    flash_set('ok', 'บันทึกการแสดงผลในหน้าแสดงผลงานแล้ว');
} else {
    flash_set('err', implode(' · ', $errors));
}
redirect('project_detail.php?id=' . $pid);
