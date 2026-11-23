<?php
/**
 * Authentication & Session Management
 * Handles login, logout, role verification, and attendance logging.
 */

require_once __DIR__ . '/../config/database.php';

function isLoggedIn(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function getCurrentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}

function requireHR(): void {
    requireLogin();
    if ($_SESSION['user']['role'] !== 'hr') {
        header("Location: " . BASE_URL . "/intern/index.php");
        exit;
    }
}

function requireIntern(): void {
    requireLogin();
    if ($_SESSION['user']['role'] !== 'intern') {
        header("Location: " . BASE_URL . "/hr/index.php");
        exit;
    }
}

/**
 * Record Intern/HR Login into Attendance Logs
 */
function recordLogin(array $user): int {
    $db = getDB();
    $now = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 250);

    // Update last activity on user
    $stmtUser = $db->prepare("UPDATE users SET last_activity = :now WHERE id = :id");
    $stmtUser->execute([':now' => $now, ':id' => $user['id']]);

    // Insert attendance log
    $stmtLog = $db->prepare("
        INSERT INTO attendance_logs (user_id, login_time, ip_address, user_agent, status)
        VALUES (:user_id, :login_time, :ip, :agent, 'active')
    ");
    $stmtLog->execute([
        ':user_id' => $user['id'],
        ':login_time' => $now,
        ':ip' => $ip,
        ':agent' => $agent
    ]);

    $logId = (int)$db->lastInsertId();

    // Store in session
    $_SESSION['user'] = [
        'id' => $user['id'],
        'intern_id' => $user['intern_id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'department' => $user['department'],
        'country' => $user['country'],
        'timezone' => $user['timezone'],
        'login_time' => $now,
        'attendance_id' => $logId
    ];

    return $logId;
}

/**
 * Record Logout & Calculate Total Session Time
 */
function recordLogout(): void {
    if (isset($_SESSION['user']['attendance_id'])) {
        $logId = $_SESSION['user']['attendance_id'];
        $now = date('Y-m-d H:i:s');
        $db = getDB();

        // Calculate session minutes
        $stmtFind = $db->prepare("SELECT login_time FROM attendance_logs WHERE id = :id");
        $stmtFind->execute([':id' => $logId]);
        $log = $stmtFind->fetch();

        $sessionMinutes = 0;
        if ($log && !empty($log['login_time'])) {
            $diffSeconds = strtotime($now) - strtotime($log['login_time']);
            $sessionMinutes = max(1, (int)round($diffSeconds / 60));
        }

        $stmtUpdate = $db->prepare("
            UPDATE attendance_logs 
            SET logout_time = :logout_time, session_minutes = :minutes, status = 'completed'
            WHERE id = :id
        ");
        $stmtUpdate->execute([
            ':logout_time' => $now,
            ':minutes' => $sessionMinutes,
            ':id' => $logId
        ]);
    }

    // Destroy session
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
