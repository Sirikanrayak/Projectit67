<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('projects.php');
csrf_check();

$pid = (int) ($_POST['project_id'] ?? 0);
$project = get_project($pdo, $pid);
if (!$project || !project_can_edit($project, $user)) redirect('projects.php');

$names = $_FILES['files']['name'] ?? [];
$tmp = $_FILES['files']['tmp_name'] ?? [];
$sizes = $_FILES['files']['size'] ?? [];
$errors = $_FILES['files']['error'] ?? [];
$count = is_array($names) ? count($names) : 0;

$countStmt = $pdo->prepare('SELECT COUNT(*) c FROM project_files WHERE project_id = ?');
$countStmt->execute([$pid]);
$existing = (int) $countStmt->fetch()['c'];

$uploadDir = __DIR__ . '/../storage/uploads';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

$ins = $pdo->prepare(
    'INSERT INTO project_files (project_id, stored_name, original_name, size, mime_type, uploaded_by)
     VALUES (:pid, :stored, :orig, :size, :mime, :by)'
);

$added = 0;
for ($i = 0; $i < $count; $i++) {
    if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
    if ($existing + $added >= MAX_FILES_PER_PROJECT) {
        flash_set('err', 'แนบไฟล์ได้สูงสุด ' . MAX_FILES_PER_PROJECT . ' ไฟล์ต่อโครงงาน');
        break;
    }
    if ($errors[$i] !== UPLOAD_ERR_OK) {
        flash_set('err', 'อัปโหลดไฟล์ “' . $names[$i] . '” ไม่สำเร็จ');
        continue;
    }
    if ($sizes[$i] > MAX_FILE_BYTES) {
        flash_set('err', 'ไฟล์ “' . $names[$i] . '” มีขนาดเกิน ' . fmt_bytes(MAX_FILE_BYTES));
        continue;
    }
    $stored = bin2hex(random_bytes(20));
    if (move_uploaded_file($tmp[$i], $uploadDir . '/' . $stored)) {
        $mime = function_exists('mime_content_type') ? (mime_content_type($uploadDir . '/' . $stored) ?: '') : '';
        $ins->execute([
            'pid' => $pid, 'stored' => $stored, 'orig' => $names[$i], 'size' => (int) $sizes[$i],
            'mime' => $mime, 'by' => $user['name'],
        ]);
        $added++;
    }
}
if ($added) flash_set('ok', $added > 1 ? "แนบไฟล์แล้ว $added ไฟล์" : 'แนบไฟล์แล้ว');

redirect('project_detail.php?id=' . $pid);
