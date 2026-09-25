<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role($pdo, ['admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('projects.php');
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
$project = get_project($pdo, $id);
if ($project) {
    foreach (get_project_files($pdo, $id) as $f) {
        $path = __DIR__ . '/../storage/uploads/' . $f['stored_name'];
        if (is_file($path)) unlink($path);
    }
    $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
    flash_set('ok', 'ลบโครงงานแล้ว');
}
redirect('projects.php');
