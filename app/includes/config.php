<?php
declare(strict_types=1);

/**
 * โหลดค่าจากไฟล์ .env (ถ้ามี) — ค่าที่ตั้งไว้ใน environment จริง (เช่น Docker) มีความสำคัญกว่าเสมอ
 */
function load_env_file(string $path): void
{
    if (!is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
            $v = substr($v, 1, -1);
        }
        if ($k === '' || getenv($k) !== false) {
            continue;
        }
        putenv("$k=$v");
        $_ENV[$k] = $v;
    }
}

// .env อยู่ที่ราก repo (ถัดจากโฟลเดอร์ app) — บนเซิร์ฟเวอร์ xCloud ให้สร้างไฟล์นี้บนเซิร์ฟเวอร์เท่านั้น
load_env_file(dirname(__DIR__, 2) . '/.env');

function env_val(string $key, ?string $default = null): ?string
{
    $v = getenv($key);
    return $v !== false && $v !== '' ? $v : $default;
}

$dbHost = env_val('DB_HOST', 'db');
$dbPort = env_val('DB_PORT', '3306');
$dbName = env_val('DB_NAME', 'projectit67');
$dbUser = env_val('DB_USER', 'projectit67');
$dbPass = env_val('DB_PASSWORD', 'changeme');

$dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

// เผื่อคอนเทนเนอร์ web พร้อมก่อน MySQL ยอมรับการเชื่อมต่อในครั้งแรกที่สร้าง
$pdo = null;
$attempts = 0;
$lastError = null;
while ($attempts < 10) {
    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
        break;
    } catch (PDOException $e) {
        $lastError = $e;
        $attempts++;
        usleep(500000);
    }
}
if (!$pdo) {
    http_response_code(500);
    die('ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . htmlspecialchars($lastError ? $lastError->getMessage() : 'unknown error'));
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/seed.php';
ensure_site_settings($pdo);
ensure_teacher_assignments($pdo);
ensure_project_qc($pdo);
ensure_proposals_and_progress($pdo);
ensure_showcase_fields($pdo);
seed_if_empty($pdo);
