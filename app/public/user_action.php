<?php
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_role($pdo, ['admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('users.php');
csrf_check();

$action = $_POST['action'] ?? '';

if ($action === 'toggle_approval') {
    $value = isset($_POST['require_approval']) ? '1' : '0';
    $pdo->prepare('UPDATE settings SET value = ? WHERE `key` = "require_approval"')->execute([$value]);
    flash_set('ok', $value === '1' ? 'เปิดการอนุมัติผู้สมัครใหม่' : 'ผู้สมัครใหม่ใช้งานได้ทันที');
    redirect('users.php');
}

$id = (int) ($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$target = $stmt->fetch();
if (!$target) redirect('users.php');

$activeAdminCount = fn () => (int) $pdo->query("SELECT COUNT(*) c FROM users WHERE role='admin' AND status='active'")->fetch()['c'];

switch ($action) {
    case 'approve':
    case 'activate':
        $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$id]);
        flash_set('ok', ($action === 'approve' ? 'อนุมัติบัญชี ' : 'เปิดใช้งานบัญชี ') . $target['name'] . ' แล้ว');
        break;
    case 'suspend':
        if ($target['role'] === 'admin' && $activeAdminCount() <= 1) {
            flash_set('err', 'ต้องมีผู้ดูแลระบบที่ใช้งานได้อย่างน้อย 1 คน');
        } else {
            $pdo->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$id]);
            flash_set('ok', 'ระงับบัญชีแล้ว');
        }
        break;
    case 'delete':
        if ($target['role'] === 'admin' && $target['status'] === 'active' && $activeAdminCount() <= 1) {
            flash_set('err', 'ต้องมีผู้ดูแลระบบที่ใช้งานได้อย่างน้อย 1 คน');
        } elseif ((int) $target['id'] === (int) $admin['id']) {
            flash_set('err', 'ไม่สามารถลบบัญชีของตัวเองได้');
        } else {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash_set('ok', 'ลบผู้ใช้แล้ว');
        }
        break;
}
redirect('users.php');
