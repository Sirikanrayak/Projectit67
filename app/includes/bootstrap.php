<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config.php';       // $pdo, helpers.php, seed.php
require_once __DIR__ . '/project_repo.php';
require_once __DIR__ . '/auth.php';
