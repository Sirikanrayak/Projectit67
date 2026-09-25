<?php
declare(strict_types=1);

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
seed_if_empty($pdo);
