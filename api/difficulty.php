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
    // Intern submits blocker/difficulty
    if ($action === 'submit_difficulty') {
        $taskId = !empty($data['task_id']) ? (int)$data['task_id'] : null;
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $severity = in_array($data['severity'] ?? '', ['low', 'medium', 'high', 'critical']) ? $data['severity'] : 'medium';

        if (empty($title) || empty($description)) {
            echo json_encode(['success' => false, 'error' => 'Title and description are required']);
            exit;
        }

        $stmt = $db->prepare("
            INSERT INTO difficulties (user_id, task_id, title, description, severity, status)
            VALUES (:uid, :tid, :title, :desc, :sev, 'open')
        ");
        $stmt->execute([
            ':uid' => $user['id'],
            ':tid' => $taskId,
            ':title' => $title,
            ':desc' => $description,
            ':sev' => $severity
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Your difficulty report has been sent to HR successfully!',
            'ticket_id' => $db->lastInsertId()
        ]);
        exit;
    }

    // HR responds / resolves difficulty
    if ($action === 'respond_difficulty') {
        if ($user['role'] !== 'hr') {
            echo json_encode(['success' => false, 'error' => 'Only HR can respond to difficulty tickets']);
            exit;
        }

        $ticketId = (int)($data['ticket_id'] ?? 0);
        $hrResponse = trim($data['hr_response'] ?? '');
        $status = in_array($data['status'] ?? '', ['open', 'in_review', 'resolved']) ? $data['status'] : 'resolved';
        $resolvedAt = ($status === 'resolved') ? date('Y-m-d H:i:s') : null;

        $stmt = $db->prepare("
            UPDATE difficulties
            SET hr_response = :response, status = :status, resolved_by = :hr_id, resolved_at = :res_at
            WHERE id = :id
        ");
        $stmt->execute([
            ':response' => $hrResponse,
            ':status' => $status,
            ':hr_id' => $user['id'],
            ':res_at' => $resolvedAt,
            ':id' => $ticketId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Difficulty ticket updated successfully!'
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
