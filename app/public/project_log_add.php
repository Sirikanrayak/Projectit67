<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('projects.php');
csrf_check();

$pid = (int) ($_POST['project_id'] ?? 0);
$text = trim($_POST['text'] ?? '');
$project = get_project($pdo, $pid);

if ($project && project_can_edit($project, $user) && $text !== '') {
    $pdo->prepare('INSERT INTO project_logs (project_id, log_date, text, by_name) VALUES (?, CURDATE(), ?, ?)')
        ->execute([$pid, $text, $user['name']]);
    flash_set('ok', 'เพิ่มบันทึกแล้ว');
}
redirect('project_detail.php?id=' . $pid);
