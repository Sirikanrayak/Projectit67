<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('projects.php');
csrf_check();

$pid = (int) ($_POST['project_id'] ?? 0);
$project = get_project($pdo, $pid);

if ($project && project_can_grade($project, $user)) {
    $scores = [];
    foreach (RUBRIC as $r) {
        $raw = $_POST[$r['key']] ?? '';
        $scores[$r['key']] = $raw === '' ? 0 : max(0, min($r['max'], (int) round((float) $raw)));
    }
    $evaluation = [
        'scores' => $scores,
        'comment' => trim($_POST['comment'] ?? ''),
        'by' => $user['name'],
        'date' => date('Y-m-d'),
    ];
    $pdo->prepare('UPDATE projects SET evaluation = ? WHERE id = ?')
        ->execute([json_encode($evaluation, JSON_UNESCAPED_UNICODE), $pid]);
    flash_set('ok', 'บันทึกผลการประเมินแล้ว');
}
redirect('project_detail.php?id=' . $pid);
