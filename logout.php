<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

recordLogout();
header("Location: " . BASE_URL . "/login.php?logged_out=1");
exit;
