<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('projects.php');
csrf_check();

$pid = (int) ($_POST['project_id'] ?? 0);
$action = $_POST['action'] ?? '';
$project = get_project($pdo, $pid);

if ($project && $action === 'request' && qc_can_sign($project, $user, 'advisor')) {
    $pdo->prepare(
        'INSERT INTO project_defense_requests (project_id, requested_by, requested_date)
         VALUES (:pid, :by, CURDATE())
         ON DUPLICATE KEY UPDATE requested_by = VALUES(requested_by), requested_date = VALUES(requested_date)'
    )->execute(['pid' => $pid, 'by' => $user['name']]);
    flash_set('ok', 'บันทึกคำขอเสนอสอบโครงการแล้ว');
} elseif ($project && $action === 'note' && qc_can_sign($project, $user, 'instructor')) {
    $note = trim($_POST['note'] ?? '');
    $pdo->prepare(
        'INSERT INTO project_defense_requests (project_id, instructor_note, instructor_by, instructor_date)
         VALUES (:pid, :note, :by, CURDATE())
         ON DUPLICATE KEY UPDATE instructor_note = VALUES(instructor_note), instructor_by = VALUES(instructor_by), instructor_date = VALUES(instructor_date)'
    )->execute(['pid' => $pid, 'note' => $note, 'by' => $user['name']]);
    flash_set('ok', 'บันทึกความเห็นของครูผู้สอนแล้ว');
} else {
    flash_set('err', 'คุณไม่มีสิทธิ์ดำเนินการนี้');
}
redirect('project_detail.php?id=' . $pid);
