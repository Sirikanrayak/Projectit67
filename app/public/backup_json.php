<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role($pdo, ['admin']);

$projects = get_all_projects($pdo);
$projectsOut = array_map(function ($p) use ($pdo) {
    return [
        'id' => (int) $p['id'], 'title' => $p['title'], 'title_en' => $p['title_en'], 'code' => $p['code'],
        'type' => $p['type'], 'level' => $p['level'], 'student_group' => $p['student_group'],
        'advisor' => $p['advisor'], 'co_advisor' => $p['co_advisor'], 'start_date' => $p['start_date'],
        'due_date' => $p['due_date'], 'note' => $p['note'], 'member_names' => $p['member_names'],
        'member_ids' => $p['member_ids'], 'steps' => $p['steps'], 'evaluation' => $p['evaluation'],
        'logs' => get_project_logs($pdo, (int) $p['id']),
    ];
}, $projects);

$users = $pdo->query('SELECT id, name, email, password_hash, role, status, student_id, level, student_group, created_at FROM users')->fetchAll();
$settings = $pdo->query('SELECT `key`, `value` FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);

$backup = [
    'version' => 1,
    'exported_at' => date('c'),
    'note' => 'ไฟล์แนบ (project_files) ไม่รวมอยู่ในไฟล์นี้ กรุณาสำรอง volume app/storage/uploads แยกต่างหาก',
    'users' => $users,
    'projects' => $projectsOut,
    'settings' => $settings,
];

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="backup-it-projects-' . date('Y-m-d') . '.json"');
echo json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
