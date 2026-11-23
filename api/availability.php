<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/timezone_helper.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = getCurrentUser();
$db = getDB();
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $data['action'] ?? '';

try {
    // Convert a single intern time into HR physical time
    if ($action === 'convert_single_time') {
        $timeStr = trim($data['time'] ?? '09:00');
        $internTz = trim($data['intern_timezone'] ?? $user['timezone']);
        $hrTz = trim($data['hr_timezone'] ?? DEFAULT_HR_TIMEZONE);
        $date = trim($data['date'] ?? date('Y-m-d'));

        $conversion = TimezoneHelper::convertToHRTime($timeStr, $internTz, $hrTz, $date);
        echo json_encode($conversion);
        exit;
    }

    // Get live overlap matrix for a date
    if ($action === 'get_matrix') {
        $date = trim($data['date'] ?? date('Y-m-d'));
        $hrTz = trim($data['hr_timezone'] ?? DEFAULT_HR_TIMEZONE);

        // Fetch all active interns
        $stmtUsers = $db->query("SELECT id, name, country, timezone FROM users WHERE role = 'intern' AND status = 'active' ORDER BY name ASC");
        $interns = $stmtUsers->fetchAll();

        // Fetch all active availability windows
        $stmtAvail = $db->query("SELECT user_id, day_of_week, start_time, end_time, timezone FROM intern_availability WHERE is_active = 1");
        $allWindows = $stmtAvail->fetchAll();

        $windowsByUser = [];
        foreach ($allWindows as $w) {
            $windowsByUser[$w['user_id']][] = $w;
        }

        foreach ($interns as &$intern) {
            $intern['availability'] = $windowsByUser[$intern['id']] ?? [];
        }

        $matrix = TimezoneHelper::calculateOverlapMatrix($date, $hrTz, $interns);
        echo json_encode(['success' => true, 'matrix' => $matrix]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
