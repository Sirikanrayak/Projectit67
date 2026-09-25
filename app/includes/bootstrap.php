<?php
declare(strict_types=1);

// ให้ session ฝั่งเซิร์ฟเวอร์อยู่ได้นานพอสำหรับผู้ใช้ที่ติ๊ก "จำฉันไว้" ตอนเข้าสู่ระบบ (คุกกี้จะยังจำกัดอายุตามปกติถ้าไม่ได้ติ๊ก)
ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));
session_start();

require_once __DIR__ . '/config.php';       // $pdo, helpers.php, seed.php
require_once __DIR__ . '/project_repo.php';
require_once __DIR__ . '/auth.php';
