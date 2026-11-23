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
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $data['action'] ?? '';

try {
    if ($action === 'update_progress') {
        $taskId = (int)($data['task_id'] ?? 0);
        $status = $data['status'] ?? 'in_progress';
        $percent = max(0, min(100, (int)($data['progress_percent'] ?? 0)));
        $notes = trim($data['notes'] ?? '');

        // Verify task ownership if intern
        if ($user['role'] === 'intern') {
            $check = $db->prepare("SELECT id FROM tasks WHERE id = :id AND assigned_to = :uid");
            $check->execute([':id' => $taskId, ':uid' => $user['id']]);
            if (!$check->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Task not assigned to you']);
                exit;
            }
        }

        $completedAt = ($status === 'completed' || $percent === 100) ? date('Y-m-d H:i:s') : null;
        if ($percent === 100 && $status !== 'completed') {
            $status = 'completed';
        }

        $sql = "UPDATE tasks SET status = :status, progress_percent = :percent, completed_at = :completed_at";
        $params = [
            ':status' => $status,
            ':percent' => $percent,
            ':completed_at' => $completedAt,
            ':id' => $taskId
        ];

        if (!empty($notes)) {
            $sql .= ", notes = :notes";
            $params[':notes'] = $notes;
        }
        $sql .= " WHERE id = :id";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'success' => true,
            'message' => 'Task progress updated successfully!',
            'task_id' => $taskId,
            'status' => $status,
            'percent' => $percent
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
