<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('projects.php');
csrf_check();

$pid = (int) ($_POST['project_id'] ?? 0);
$round = (int) ($_POST['round_no'] ?? 0);
$rating = $_POST['rating'] ?? '';
$project = get_project($pdo, $pid);

if ($project && in_array($round, [1, 2, 3], true) && in_array($rating, PROGRESS_RATINGS, true) && qc_can_sign($project, $user, 'any')) {
    $pdo->prepare(
        'INSERT INTO project_progress_rounds (project_id, round_no, rating, evaluated_by, evaluated_date)
         VALUES (:pid, :round, :rating, :by, CURDATE())
         ON DUPLICATE KEY UPDATE rating = VALUES(rating), evaluated_by = VALUES(evaluated_by), evaluated_date = VALUES(evaluated_date)'
    )->execute(['pid' => $pid, 'round' => $round, 'rating' => $rating, 'by' => $user['name']]);
    flash_set('ok', 'บันทึกผลการประเมินความก้าวหน้าแล้ว');
} else {
    flash_set('err', 'คุณไม่มีสิทธิ์ประเมินรอบนี้ หรือข้อมูลไม่ถูกต้อง');
}
redirect('project_detail.php?id=' . $pid);
