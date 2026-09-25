<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login($pdo);

$fileId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT f.*, f.project_id FROM project_files f WHERE f.id = ?');
$stmt->execute([$fileId]);
$file = $stmt->fetch();
if (!$file) { http_response_code(404); die('ไม่พบไฟล์'); }

$project = get_project($pdo, (int) $file['project_id']);
if (!$project || !project_can_edit($project, $user)) { http_response_code(403); die('ไม่มีสิทธิ์เข้าถึงไฟล์นี้'); }

$path = __DIR__ . '/../storage/uploads/' . $file['stored_name'];
if (!is_file($path)) { http_response_code(404); die('ไม่พบไฟล์บนเซิร์ฟเวอร์'); }

header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . rawurlencode($file['original_name']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
