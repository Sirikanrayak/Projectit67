<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('projects.php');
csrf_check();

$pid = (int) ($_POST['project_id'] ?? 0);
$round = (int) ($_POST['round_no'] ?? 0);
$project = get_project($pdo, $pid);

if ($project && in_array($round, [1, 2, 3], true) && project_can_edit($project, $user)) {
    $plan = trim($_POST['plan'] ?? '');
    $work = trim($_POST['submitted_work'] ?? '');
    $pdo->prepare(
        'INSERT INTO project_progress_rounds (project_id, round_no, plan, submitted_work, submitted_date)
         VALUES (:pid, :round, :plan, :work, CURDATE())
         ON DUPLICATE KEY UPDATE plan = VALUES(plan), submitted_work = VALUES(submitted_work), submitted_date = VALUES(submitted_date)'
    )->execute(['pid' => $pid, 'round' => $round, 'plan' => $plan, 'work' => $work]);
    flash_set('ok', 'บันทึกรายงานความก้าวหน้าแล้ว');
} else {
    flash_set('err', 'คุณไม่มีสิทธิ์บันทึกรายงานนี้');
}
redirect('project_detail.php?id=' . $pid);
