<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('projects.php');
csrf_check();

$pid = (int) ($_POST['project_id'] ?? 0);
$idx = (int) ($_POST['step_index'] ?? -1);
$project = get_project($pdo, $pid);

if ($project && project_can_edit($project, $user) && $idx >= 0 && $idx < count(STEPS)) {
    $steps = $project['steps'];
    $steps[$idx] = isset($_POST['value']);
    $pdo->prepare('UPDATE projects SET steps = ? WHERE id = ?')->execute([json_encode(array_values($steps)), $pid]);
}
redirect('project_detail.php?id=' . $pid);
