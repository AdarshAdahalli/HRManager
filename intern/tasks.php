<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = "My Tasks - " . APP_NAME;

requireIntern();
$db = getDB();
$user = getCurrentUser();

$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT * FROM tasks WHERE assigned_to = :uid";
$params = [':uid' => $user['id']];

if (!empty($statusFilter)) {
    $sql .= " AND status = :status";
    $params[':status'] = $statusFilter;
}
$sql .= " ORDER BY CASE status WHEN 'in_progress' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END, due_date ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">My Deliverables & Tasks</h1>
            <p class="text-sm text-slate-500">Track your assignments, update completion percentage, and alert HR if blocked.</p>
        </div>

        <div class="flex items-center space-x-2 bg-slate-200/80 p-1 rounded-xl text-xs font-semibold">
            <a href="<?= BASE_URL ?>/intern/tasks.php" class="px-3 py-1.5 rounded-lg transition <?= empty($statusFilter) ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">All</a>
            <a href="<?= BASE_URL ?>/intern/tasks.php?status=in_progress" class="px-3 py-1.5 rounded-lg transition <?= $statusFilter === 'in_progress' ? 'bg-white text-indigo-700 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900' ?>">In Progress</a>
            <a href="<?= BASE_URL ?>/intern/tasks.php?status=pending" class="px-3 py-1.5 rounded-lg transition <?= $statusFilter === 'pending' ? 'bg-white text-amber-700 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900' ?>">Pending</a>
            <a href="<?= BASE_URL ?>/intern/tasks.php?status=completed" class="px-3 py-1.5 rounded-lg transition <?= $statusFilter === 'completed' ? 'bg-white text-emerald-700 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900' ?>">Completed</a>
        </div>
    </div>

    <!-- Task Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php if (empty($tasks)): ?>
            <div class="col-span-full bg-white p-12 text-center rounded-2xl border border-slate-200 text-slate-400">
                <i class="fa-solid fa-list-check text-4xl mb-3 text-slate-300"></i>
                <p class="text-base font-semibold text-slate-700">No tasks in this category.</p>
            </div>
        <?php else: ?>
            <?php foreach ($tasks as $t): ?>
                <?php
                $isOverdue = (!empty($t['due_date']) && strtotime($t['due_date']) < strtotime(date('Y-m-d')) && $t['status'] !== 'completed');
                $priorityBadge = match($t['priority']) {
                    'urgent' => 'bg-rose-100 text-rose-800 border-rose-200',
                    'high' => 'bg-amber-100 text-amber-800 border-amber-200',
                    'medium' => 'bg-blue-100 text-blue-800 border-blue-200',
                    default => 'bg-slate-100 text-slate-700 border-slate-200',
                };
                $statusBadge = match($t['status']) {
                    'completed' => 'bg-emerald-100 text-emerald-800',
                    'in_progress' => 'bg-indigo-100 text-indigo-800',
                    default => 'bg-slate-100 text-slate-600',
                };
                ?>
                <div class="bg-white rounded-2xl border <?= $isOverdue ? 'border-rose-300' : 'border-slate-200' ?> p-5 shadow-xs flex flex-col justify-between hover:shadow-md transition">
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase border <?= $priorityBadge ?>">
                                <?= htmlspecialchars($t['priority']) ?>
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold capitalize <?= $statusBadge ?>">
                                <?= str_replace('_', ' ', $t['status']) ?>
                            </span>
                        </div>

                        <h3 class="text-base font-bold text-slate-900 mt-3 leading-snug"><?= htmlspecialchars($t['title']) ?></h3>
                        <p class="text-xs text-slate-600 mt-1.5 leading-relaxed"><?= htmlspecialchars($t['description'] ?: 'No description.') ?></p>

                        <?php if (!empty($t['notes'])): ?>
                            <div class="mt-3 p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-xs text-slate-600">
                                <span class="font-bold text-slate-700 block text-[10px] uppercase">My Last Note:</span>
                                <?= htmlspecialchars($t['notes']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Progress Bar -->
                        <div class="mt-4">
                            <div class="flex items-center justify-between text-xs font-semibold text-slate-600 mb-1">
                                <span>Progress</span>
                                <span class="font-mono text-slate-900"><?= $t['progress_percent'] ?>%</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                <div class="bg-blue-600 h-2 rounded-full transition-all duration-500" style="width: <?= $t['progress_percent'] ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 pt-4 border-t border-slate-100 space-y-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="<?= $isOverdue ? 'text-rose-600 font-bold' : 'text-slate-500' ?>">
                                <i class="fa-regular fa-calendar mr-1"></i>Due: <?= $t['due_date'] ? date('M d, Y', strtotime($t['due_date'])) : 'Open' ?>
                            </span>
                            <?php if ($t['status'] === 'completed'): ?>
                                <span class="text-emerald-600 font-bold"><i class="fa-solid fa-check mr-1"></i>Finished</span>
                            <?php endif; ?>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <button type="button" onclick="openProgressModal(<?= $t['id'] ?>, '<?= htmlspecialchars($t['title'], ENT_QUOTES) ?>', <?= $t['progress_percent'] ?>, '<?= $t['status'] ?>', '<?= htmlspecialchars($t['notes'] ?? '', ENT_QUOTES) ?>')" class="py-2 px-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-xs transition flex items-center justify-center">
                                <i class="fa-solid fa-pen-to-square mr-1.5"></i>Update
                            </button>
                            <a href="<?= BASE_URL ?>/intern/report_difficulty.php?task_id=<?= $t['id'] ?>" class="py-2 px-3 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-bold rounded-xl transition flex items-center justify-center">
                                <i class="fa-solid fa-triangle-exclamation mr-1.5"></i>Need Help
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Update Task Progress -->
<div id="update-progress-modal" class="modal-container hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-backdrop fixed inset-0 bg-slate-950/60 backdrop-blur-xs"></div>
    <div class="min-h-full flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <h3 class="text-base font-bold text-slate-900" id="prog-modal-task-title">Update Task Progress</h3>
                <button type="button" onclick="closeModal('update-progress-modal')" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form id="progress-form" onsubmit="submitProgress(event)" class="space-y-4 mt-4 text-xs">
                <input type="hidden" id="prog-task-id" name="task_id">

                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Status</label>
                    <select id="prog-status" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-semibold bg-slate-50 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed (100%)</option>
                    </select>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="font-bold text-slate-700 uppercase">Completion Percentage</label>
                        <span id="prog-percent-display" class="font-mono font-bold text-blue-600 text-sm">50%</span>
                    </div>
                    <input type="range" id="prog-percent" min="0" max="100" step="5" value="50" oninput="document.getElementById('prog-percent-display').textContent = this.value + '%'" class="w-full accent-blue-600">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Progress Notes / Summary</label>
                    <textarea id="prog-notes" rows="3" placeholder="Explain progress, commits pushed, or deliverables..." class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-2">
                    <button type="button" onclick="closeModal('update-progress-modal')" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-xl font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold shadow-md">Save Progress</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openProgressModal(taskId, title, percent, status, notes = '') {
        document.getElementById('prog-task-id').value = taskId;
        document.getElementById('prog-modal-task-title').textContent = title;
        document.getElementById('prog-percent').value = percent;
        document.getElementById('prog-percent-display').textContent = percent + '%';
        document.getElementById('prog-status').value = status;
        document.getElementById('prog-notes').value = notes;
        openModal('update-progress-modal');
    }

    async function submitProgress(e) {
        e.preventDefault();
        const taskId = document.getElementById('prog-task-id').value;
        const status = document.getElementById('prog-status').value;
        const percent = document.getElementById('prog-percent').value;
        const notes = document.getElementById('prog-notes').value;

        try {
            const resp = await fetch('<?= BASE_URL ?>/api/tasks.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_progress',
                    task_id: taskId,
                    status: status,
                    progress_percent: percent,
                    notes: notes
                })
            });
            const data = await resp.json();
            if (data.success) {
                closeModal('update-progress-modal');
                showToast(data.message, 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showToast(data.error || 'Failed to update', 'error');
            }
        } catch (err) {
            showToast('Network error updating task', 'error');
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
