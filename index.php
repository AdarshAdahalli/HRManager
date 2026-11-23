<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    $role = $_SESSION['user']['role'];
    if ($role === 'hr') {
        header("Location: " . BASE_URL . "/hr/index.php");
    } else {
        header("Location: " . BASE_URL . "/intern/index.php");
    }
    exit;
} else {
    header("Location: " . BASE_URL . "/login.php");
    exit;
}
