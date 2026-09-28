<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('projects.php');
csrf_check();

$pid = (int) ($_POST['project_id'] ?? 0);
$stepKey = (string) ($_POST['step_key'] ?? '');
$status = in_array($_POST['status'] ?? '', ['pass', 'fail'], true) ? $_POST['status'] : null;
$note = trim($_POST['note'] ?? '');

$project = get_project($pdo, $pid);
$stepDef = null;
foreach (QC_STEPS as $s) {
    if ($s['key'] === $stepKey) { $stepDef = $s; break; }
}

if ($project && $stepDef && $status && qc_can_sign($project, $user, $stepDef['role'])) {
    $pdo->prepare(
        'INSERT INTO project_qc (project_id, step_key, status, signed_by, signed_date, note)
         VALUES (:pid, :key, :status, :by, :date, :note)
         ON DUPLICATE KEY UPDATE status = VALUES(status), signed_by = VALUES(signed_by),
             signed_date = VALUES(signed_date), note = VALUES(note)'
    )->execute([
        'pid' => $pid, 'key' => $stepKey, 'status' => $status,
        'by' => $user['name'], 'date' => date('Y-m-d'), 'note' => $note,
    ]);
    flash_set('ok', 'บันทึกผลการกำกับคุณภาพแล้ว');
} else {
    flash_set('err', 'คุณไม่มีสิทธิ์บันทึกขั้นตอนนี้');
}
redirect('project_detail.php?id=' . $pid);
