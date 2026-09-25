<?php
require __DIR__ . '/../includes/bootstrap.php';
redirect(current_user($pdo) ? 'dashboard.php' : 'login.php');
