<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('projects.php');
csrf_check();

$pid = (int) ($_POST['project_id'] ?? 0);
$fileId = (int) ($_POST['file_id'] ?? 0);
$project = get_project($pdo, $pid);

if ($project && project_can_edit($project, $user)) {
    $stmt = $pdo->prepare('SELECT * FROM project_files WHERE id = ? AND project_id = ?');
    $stmt->execute([$fileId, $pid]);
    $file = $stmt->fetch();
    if ($file) {
        $path = __DIR__ . '/../storage/uploads/' . $file['stored_name'];
        if (is_file($path)) unlink($path);
        $pdo->prepare('DELETE FROM project_files WHERE id = ?')->execute([$fileId]);
        flash_set('ok', 'ลบไฟล์แล้ว');
    }
}
redirect('project_detail.php?id=' . $pid);
