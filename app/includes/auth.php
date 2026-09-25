<?php
declare(strict_types=1);

function current_user(PDO $pdo): ?array
{
    static $cached = null;
    static $resolved = false;
    if ($resolved) return $cached;
    $resolved = true;

    if (empty($_SESSION['user_id'])) return $cached = null;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user_id']);
        return $cached = null;
    }
    return $cached = $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
}

function logout_user(): void
{
    $_SESSION = [];
    session_destroy();
}

function require_login(PDO $pdo): array
{
    $user = current_user($pdo);
    if (!$user) redirect('login.php');
    return $user;
}

function require_role(PDO $pdo, array $roles): array
{
    $user = require_login($pdo);
    if (!in_array($user['role'], $roles, true)) {
        flash_set('err', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
        redirect('dashboard.php');
    }
    return $user;
}

function is_admin(array $user): bool
{
    return $user['role'] === 'admin';
}

function is_teacher(array $user): bool
{
    return $user['role'] === 'teacher';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . esc(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!$token || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        die('คำขอไม่ถูกต้อง (CSRF token mismatch) กรุณาย้อนกลับและลองใหม่');
    }
}
