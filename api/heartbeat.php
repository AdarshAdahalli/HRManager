<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = getCurrentUser();
$db = getDB();
$now = date('Y-m-d H:i:s');

try {
    // Update user activity
    $stmt = $db->prepare("UPDATE users SET last_activity = :now WHERE id = :id");
    $stmt->execute([':now' => $now, ':id' => $user['id']]);

    // Update active attendance session duration
    if (!empty($user['attendance_id'])) {
        $stmtLog = $db->prepare("SELECT login_time FROM attendance_logs WHERE id = :id AND status = 'active'");
        $stmtLog->execute([':id' => $user['attendance_id']]);
        $row = $stmtLog->fetch();

        if ($row && !empty($row['login_time'])) {
            $diffSeconds = strtotime($now) - strtotime($row['login_time']);
            $minutes = max(1, (int)round($diffSeconds / 60));

            $stmtUpdate = $db->prepare("UPDATE attendance_logs SET session_minutes = :min WHERE id = :id");
            $stmtUpdate->execute([':min' => $minutes, ':id' => $user['attendance_id']]);
        }
    }

    echo json_encode(['success' => true, 'timestamp' => $now]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
