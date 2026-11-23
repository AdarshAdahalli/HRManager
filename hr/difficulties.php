<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = "Blockers & Difficulties - " . APP_NAME;

requireHR();
$db = getDB();
$message = '';

// Handle HR Response
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $response = trim($_POST['hr_response'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['open', 'in_review', 'resolved']) ? $_POST['status'] : 'resolved';
    $resolvedAt = ($status === 'resolved') ? date('Y-m-d H:i:s') : null;

    $stmt = $db->prepare("
        UPDATE difficulties 
        SET hr_response = :response, status = :status, resolved_by = :hr_id, resolved_at = :resolved_at
        WHERE id = :id
    ");
    $stmt->execute([
        ':response' => $response,
        ':status' => $status,
        ':hr_id' => $_SESSION['user']['id'],
        ':resolved_at' => $resolvedAt,
        ':id' => $ticketId
    ]);
    $message = "Difficulty ticket #{$ticketId} updated successfully.";
}

// Fetch all difficulties
$filterStatus = $_GET['status'] ?? '';
$sql = "
    SELECT d.*, u.name as intern_name, u.intern_id, u.country, u.timezone,
           t.title as task_title,
           hr.name as resolver_name
    FROM difficulties d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN tasks t ON d.task_id = t.id
    LEFT JOIN users hr ON d.resolved_by = hr.id
    WHERE 1=1
";
$params = [];
if (!empty($filterStatus)) {
    $sql .= " AND d.status = :status";
    $params[':status'] = $filterStatus;
}
$sql .= " ORDER BY CASE d.status WHEN 'open' THEN 1 WHEN 'in_review' THEN 2 ELSE 3 END, d.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$difficulties = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Intern Blockers & Support Desk</h1>
            <p class="text-sm text-slate-500">Address technical roadblocks, permissions issues, and difficulties reported by interns.</p>
        </div>
        
        <!-- Status Tabs -->
        <div class="flex items-center space-x-2 bg-slate-200/80 p-1 rounded-xl text-xs font-semibold">
            <a href="<?= BASE_URL ?>/hr/difficulties.php" class="px-3 py-1.5 rounded-lg transition <?= empty($filterStatus) ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">All</a>
            <a href="<?= BASE_URL ?>/hr/difficulties.php?status=open" class="px-3 py-1.5 rounded-lg transition <?= $filterStatus === 'open' ? 'bg-white text-rose-600 shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">Open</a>
            <a href="<?= BASE_URL ?>/hr/difficulties.php?status=in_review" class="px-3 py-1.5 rounded-lg transition <?= $filterStatus === 'in_review' ? 'bg-white text-amber-600 shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">In Review</a>
            <a href="<?= BASE_URL ?>/hr/difficulties.php?status=resolved" class="px-3 py-1.5 rounded-lg transition <?= $filterStatus === 'resolved' ? 'bg-white text-emerald-600 shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">Resolved</a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800 flex items-center space-x-3">
            <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>

    <!-- Difficulties List -->
    <div class="space-y-4">
        <?php if (empty($difficulties)): ?>
            <div class="bg-white p-12 text-center rounded-2xl border border-slate-200 text-slate-400">
                <i class="fa-solid fa-circle-check text-emerald-500 text-4xl mb-3"></i>
                <p class="text-base font-semibold text-slate-700">No difficulty reports matching current criteria.</p>
                <p class="text-xs text-slate-500 mt-1">Interns can report roadblocks directly from their portal.</p>
            </div>
        <?php else: ?>
            <?php foreach ($difficulties as $d): ?>
                <?php
                $sevBadge = match($d['severity']) {
                    'critical' => 'bg-rose-600 text-white',
                    'high' => 'bg-rose-100 text-rose-800 border-rose-200',
                    'medium' => 'bg-amber-100 text-amber-800 border-amber-200',
                    default => 'bg-slate-100 text-slate-700 border-slate-200'
                };
                $statusBadge = match($d['status']) {
                    'resolved' => 'bg-emerald-100 text-emerald-800',
                    'in_review' => 'bg-amber-100 text-amber-800',
                    default => 'bg-rose-100 text-rose-800'
                };
                ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100">
                        <div class="flex items-center space-x-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border <?= $sevBadge ?>">
                                <?= htmlspecialchars($d['severity']) ?>
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold capitalize <?= $statusBadge ?>">
                                <?= str_replace('_', ' ', $d['status']) ?>
                            </span>
                            <span class="text-xs text-slate-400 font-mono">Ticket #<?= $d['id'] ?></span>
                        </div>
                        <div class="text-xs text-slate-400">
                            Reported <?= date('M d, Y h:i A', strtotime($d['created_at'])) ?>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-bold text-slate-900"><?= htmlspecialchars($d['title']) ?></h3>
                        <p class="text-sm text-slate-700 mt-1.5 leading-relaxed"><?= nl2br(htmlspecialchars($d['description'])) ?></p>

                        <?php if (!empty($d['task_title'])): ?>
                            <div class="mt-3 inline-flex items-center px-3 py-1 rounded-lg bg-blue-50 border border-blue-100 text-xs text-blue-700 font-medium">
                                <i class="fa-solid fa-list-check mr-2"></i>Linked Task: <strong class="ml-1"><?= htmlspecialchars($d['task_title']) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Intern Details -->
                    <div class="p-3 bg-slate-50 rounded-xl flex items-center justify-between text-xs text-slate-600">
                        <div class="flex items-center space-x-2">
                            <div class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 font-bold flex items-center justify-center text-[10px]">
                                <?= strtoupper(substr($d['intern_name'], 0, 1)) ?>
                            </div>
                            <span class="font-bold text-slate-800"><?= htmlspecialchars($d['intern_name']) ?></span>
                            <span class="text-slate-400 font-mono">(<?= htmlspecialchars($d['intern_id']) ?>)</span>
                        </div>
                        <div>
                            <i class="fa-solid fa-globe mr-1 text-slate-400"></i><?= htmlspecialchars($d['country']) ?> &bull; <?= htmlspecialchars($d['timezone']) ?>
                        </div>
                    </div>

                    <!-- HR Resolution / Response -->
                    <?php if (!empty($d['hr_response'])): ?>
                        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 space-y-1">
                            <div class="flex items-center justify-between font-bold text-emerald-800">
                                <span><i class="fa-solid fa-reply mr-1"></i>HR Guidance / Response:</span>
                                <span><?= $d['resolved_at'] ? date('M d, H:i', strtotime($d['resolved_at'])) : '' ?></span>
                            </div>
                            <p class="text-emerald-950 font-normal leading-relaxed"><?= nl2br(htmlspecialchars($d['hr_response'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Action Form -->
                    <div class="pt-3 border-t border-slate-100">
                        <form method="POST" action="" class="space-y-3">
                            <input type="hidden" name="ticket_id" value="<?= $d['id'] ?>">
                            <div class="flex flex-col sm:flex-row gap-2">
                                <input type="text" name="hr_response" value="<?= htmlspecialchars($d['hr_response'] ?? '') ?>" placeholder="Type response / solution note..." class="flex-1 px-3 py-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                <select name="status" class="px-3 py-2 border border-slate-300 rounded-xl text-xs font-semibold bg-slate-50">
                                    <option value="open" <?= $d['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                                    <option value="in_review" <?= $d['status'] === 'in_review' ? 'selected' : '' ?>>In Review</option>
                                    <option value="resolved" <?= $d['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                </select>
                                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition">
                                    Update Ticket
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
