<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = "Report Blocker / Difficulty - " . APP_NAME;

requireIntern();
$db = getDB();
$user = getCurrentUser();
$message = '';
$error = '';
$selectedTaskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;

// Handle Form Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $taskId = !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $severity = in_array($_POST['severity'] ?? '', ['low', 'medium', 'high', 'critical']) ? $_POST['severity'] : 'medium';

    if (empty($title) || empty($description)) {
        $error = 'Title and description are required.';
    } else {
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
        $message = "Your difficulty report has been notified to HR! An HR coordinator will review and reply shortly.";
    }
}

// Fetch Intern's Assigned Tasks for dropdown
$stmtTasks = $db->prepare("SELECT id, title FROM tasks WHERE assigned_to = :uid ORDER BY title ASC");
$stmtTasks->execute([':uid' => $user['id']]);
$myTasks = $stmtTasks->fetchAll();

// Fetch History of reported difficulties
$stmtHistory = $db->prepare("
    SELECT d.*, t.title as task_title, hr.name as hr_name
    FROM difficulties d
    LEFT JOIN tasks t ON d.task_id = t.id
    LEFT JOIN users hr ON d.resolved_by = hr.id
    WHERE d.user_id = :uid
    ORDER BY d.created_at DESC
");
$stmtHistory->execute([':uid' => $user['id']]);
$history = $stmtHistory->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Report Blocker / Difficulty to HR</h1>
            <p class="text-sm text-slate-500">Stuck or facing technical / access hurdles? Notify HR immediately so we can help unblock you.</p>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800 flex items-center space-x-3">
            <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800 flex items-center space-x-3">
            <i class="fa-solid fa-circle-exclamation text-rose-600 text-lg"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- New Blocker Form -->
        <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200 p-6 shadow-xs h-fit">
            <h2 class="text-base font-bold text-slate-900 flex items-center pb-4 border-b border-slate-100">
                <i class="fa-solid fa-triangle-exclamation text-rose-500 mr-2"></i>Submit Help Request
            </h2>

            <form method="POST" action="" class="space-y-4 mt-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Issue Summary / Title</label>
                    <input type="text" name="title" required placeholder="e.g. Missing AWS IAM permissions to test Lambda" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-rose-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Related Task (Optional)</label>
                    <select name="task_id" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50 focus:ring-2 focus:ring-rose-500 focus:outline-none">
                        <option value="">General Issue (Not tied to a specific task)</option>
                        <?php foreach ($myTasks as $task): ?>
                            <option value="<?= $task['id'] ?>" <?= $selectedTaskId === $task['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($task['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Severity / Urgency</label>
                    <select name="severity" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50 font-semibold focus:ring-2 focus:ring-rose-500 focus:outline-none">
                        <option value="low">Low - Minor question or clarification</option>
                        <option value="medium" selected>Medium - Slowing down progress</option>
                        <option value="high">High - Completely blocked on this task</option>
                        <option value="critical">Critical - System down / urgent security issue</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Detailed Explanation</label>
                    <textarea name="description" rows="4" required placeholder="Describe what you tried, the error output or screenshot details, and what assistance you need from HR or mentors..." class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-rose-500 focus:outline-none"></textarea>
                </div>

                <button type="submit" class="w-full py-2.5 px-4 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center justify-center">
                    <i class="fa-solid fa-paper-plane mr-2"></i>Send Notification to HR
                </button>
            </form>
        </div>

        <!-- History of Reported Issues -->
        <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 p-6 shadow-xs flex flex-col">
            <h2 class="text-base font-bold text-slate-900 flex items-center pb-4 border-b border-slate-100">
                <i class="fa-solid fa-clock-rotate-left text-blue-600 mr-2"></i>My Reported Tickets & HR Responses
            </h2>

            <div class="mt-4 space-y-4 flex-1">
                <?php if (empty($history)): ?>
                    <div class="text-center py-12 text-slate-400 text-xs">
                        <i class="fa-regular fa-comment-dots text-4xl mb-2 text-slate-300"></i>
                        <p class="text-sm font-semibold text-slate-700">No difficulty tickets submitted.</p>
                        <p class="text-xs text-slate-500 mt-1">If you ever hit an impediment, submit a request on the left.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($history as $h): ?>
                        <?php
                        $statusBadge = match($h['status']) {
                            'resolved' => 'bg-emerald-100 text-emerald-800',
                            'in_review' => 'bg-amber-100 text-amber-800',
                            default => 'bg-rose-100 text-rose-800'
                        };
                        ?>
                        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 space-y-3">
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $statusBadge ?>">
                                        <?= str_replace('_', ' ', $h['status']) ?>
                                    </span>
                                    <span class="font-mono text-slate-400">#<?= $h['id'] ?></span>
                                    <?php if (!empty($h['task_title'])): ?>
                                        <span class="text-slate-500 truncate max-w-xs">&bull; Task: <strong><?= htmlspecialchars($h['task_title']) ?></strong></span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-slate-400"><?= date('M d, Y', strtotime($h['created_at'])) ?></span>
                            </div>

                            <h4 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($h['title']) ?></h4>
                            <p class="text-xs text-slate-600 leading-relaxed"><?= nl2br(htmlspecialchars($h['description'])) ?></p>

                            <!-- HR Response -->
                            <?php if (!empty($h['hr_response'])): ?>
                                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs space-y-1">
                                    <div class="flex items-center justify-between font-bold text-emerald-800">
                                        <span><i class="fa-solid fa-reply mr-1"></i>Response from HR (<?= htmlspecialchars($h['hr_name'] ?? 'HR Team') ?>):</span>
                                        <span class="text-[10px] text-emerald-700 font-normal"><?= $h['resolved_at'] ? date('M d, H:i', strtotime($h['resolved_at'])) : '' ?></span>
                                    </div>
                                    <p class="text-emerald-950 font-normal leading-relaxed"><?= nl2br(htmlspecialchars($h['hr_response'])) ?></p>
                                </div>
                            <?php else: ?>
                                <div class="text-[11px] text-slate-400 italic">
                                    <i class="fa-regular fa-clock mr-1"></i>Waiting for HR review...
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
