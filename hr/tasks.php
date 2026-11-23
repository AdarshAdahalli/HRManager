<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = "Task Management - " . APP_NAME;

requireHR();
$db = getDB();
$message = '';
$error = '';

// Handle Create / Delete / Update Task
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_task') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $assignedTo = (int)($_POST['assigned_to'] ?? 0);
        $priority = $_POST['priority'] ?? 'medium';
        $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

        if (empty($title) || $assignedTo <= 0) {
            $error = 'Title and assigned intern are required.';
        } else {
            $stmt = $db->prepare("
                INSERT INTO tasks (title, description, assigned_to, created_by, priority, status, progress_percent, due_date)
                VALUES (:title, :description, :assigned_to, :created_by, :priority, 'pending', 0, :due_date)
            ");
            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':assigned_to' => $assignedTo,
                ':created_by' => $_SESSION['user']['id'],
                ':priority' => $priority,
                ':due_date' => $dueDate
            ]);
            $message = 'New task created and assigned successfully!';
        }
    }

    if ($action === 'delete_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
        $message = 'Task deleted successfully.';
    }

    if ($action === 'update_task_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        $percent = ($status === 'completed') ? 100 : (($status === 'pending') ? 0 : 50);
        $completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;

        $stmt = $db->prepare("
            UPDATE tasks SET status = :status, progress_percent = :percent, completed_at = :completed_at WHERE id = :id
        ");
        $stmt->execute([
            ':status' => $status,
            ':percent' => $percent,
            ':completed_at' => $completedAt,
            ':id' => $taskId
        ]);
        $message = 'Task status updated.';
    }
}

// Filters
$internFilter = isset($_GET['intern_id']) ? (int)$_GET['intern_id'] : 0;
$statusFilter = $_GET['status'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';

// Build Query
$sql = "
    SELECT t.*, u.name as intern_name, u.intern_id, u.country, u.timezone,
           (SELECT COUNT(*) FROM difficulties WHERE task_id = t.id AND status = 'open') as open_difficulties
    FROM tasks t
    JOIN users u ON t.assigned_to = u.id
    WHERE 1=1
";
$params = [];

if ($internFilter > 0) {
    $sql .= " AND t.assigned_to = :intern_id";
    $params[':intern_id'] = $internFilter;
}
if (!empty($statusFilter)) {
    $sql .= " AND t.status = :status";
    $params[':status'] = $statusFilter;
}
if (!empty($priorityFilter)) {
    $sql .= " AND t.priority = :priority";
    $params[':priority'] = $priorityFilter;
}

$sql .= " ORDER BY CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END, t.due_date ASC";
$stmtTasks = $db->prepare($sql);
$stmtTasks->execute($params);
$tasks = $stmtTasks->fetchAll();

// All active interns for dropdown
$interns = $db->query("SELECT id, name, intern_id FROM users WHERE role = 'intern' AND status = 'active' ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Intern Tasks Board</h1>
            <p class="text-sm text-slate-500">Assign project deliverables, monitor real-time completion percentages and deadlines.</p>
        </div>
        <button type="button" onclick="openModal('create-task-modal')" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center justify-center">
            <i class="fa-solid fa-plus mr-2"></i>Assign New Task
        </button>
    </div>

    <?php if (!empty($message)): ?>
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800 flex items-center space-x-3">
            <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-center gap-3 text-xs">
        <span class="font-bold text-slate-500 uppercase"><i class="fa-solid fa-filter mr-1"></i>Filters:</span>

        <form method="GET" action="" class="flex flex-wrap items-center gap-3">
            <select name="intern_id" onchange="this.form.submit()" class="px-3 py-1.5 border border-slate-300 rounded-lg bg-slate-50 font-medium">
                <option value="0">All Interns</option>
                <?php foreach ($interns as $i): ?>
                    <option value="<?= $i['id'] ?>" <?= $internFilter === $i['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($i['name']) ?> (<?= htmlspecialchars($i['intern_id']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status" onchange="this.form.submit()" class="px-3 py-1.5 border border-slate-300 rounded-lg bg-slate-50 font-medium">
                <option value="">All Statuses</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
            </select>

            <select name="priority" onchange="this.form.submit()" class="px-3 py-1.5 border border-slate-300 rounded-lg bg-slate-50 font-medium">
                <option value="">All Priorities</option>
                <option value="urgent" <?= $priorityFilter === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                <option value="high" <?= $priorityFilter === 'high' ? 'selected' : '' ?>>High</option>
                <option value="medium" <?= $priorityFilter === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="low" <?= $priorityFilter === 'low' ? 'selected' : '' ?>>Low</option>
            </select>

            <?php if ($internFilter || $statusFilter || $priorityFilter): ?>
                <a href="<?= BASE_URL ?>/hr/tasks.php" class="text-rose-600 font-bold hover:underline">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tasks Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php if (empty($tasks)): ?>
            <div class="col-span-full bg-white p-12 text-center rounded-2xl border border-slate-200 text-slate-400">
                <i class="fa-solid fa-list-check text-4xl mb-3 text-slate-300"></i>
                <p class="text-base font-semibold text-slate-700">No tasks match your filter criteria.</p>
                <p class="text-xs text-slate-500 mt-1">Assign a new task to an intern using the button above.</p>
            </div>
        <?php else: ?>
            <?php foreach ($tasks as $task): ?>
                <?php
                $isOverdue = (!empty($task['due_date']) && strtotime($task['due_date']) < strtotime(date('Y-m-d')) && $task['status'] !== 'completed');
                $priorityBadge = match($task['priority']) {
                    'urgent' => 'bg-rose-100 text-rose-800 border-rose-200',
                    'high' => 'bg-amber-100 text-amber-800 border-amber-200',
                    'medium' => 'bg-blue-100 text-blue-800 border-blue-200',
                    default => 'bg-slate-100 text-slate-700 border-slate-200',
                };
                $statusBadge = match($task['status']) {
                    'completed' => 'bg-emerald-100 text-emerald-800',
                    'in_progress' => 'bg-indigo-100 text-indigo-800',
                    default => 'bg-slate-100 text-slate-600',
                };
                ?>
                <div class="bg-white rounded-2xl border <?= $isOverdue ? 'border-rose-300' : 'border-slate-200' ?> p-5 shadow-xs flex flex-col justify-between hover:shadow-md transition">
                    <div>
                        <!-- Header with Badges -->
                        <div class="flex items-center justify-between gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase border <?= $priorityBadge ?>">
                                <?= htmlspecialchars($task['priority']) ?>
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold capitalize <?= $statusBadge ?>">
                                <?= str_replace('_', ' ', $task['status']) ?>
                            </span>
                        </div>

                        <!-- Title & Description -->
                        <h3 class="text-base font-bold text-slate-900 mt-3 leading-snug"><?= htmlspecialchars($task['title']) ?></h3>
                        <p class="text-xs text-slate-600 mt-1.5 line-clamp-3"><?= htmlspecialchars($task['description'] ?: 'No description provided.') ?></p>

                        <!-- Blocker Indicator -->
                        <?php if ($task['open_difficulties'] > 0): ?>
                            <div class="mt-3 p-2 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center">
                                <i class="fa-solid fa-triangle-exclamation mr-1.5 text-rose-600 animate-bounce"></i>
                                <span>Intern reported blocker on this task!</span>
                            </div>
                        <?php endif; ?>

                        <!-- Progress Bar -->
                        <div class="mt-4">
                            <div class="flex items-center justify-between text-xs font-semibold text-slate-600 mb-1">
                                <span>Progress</span>
                                <span class="font-mono text-slate-900"><?= $task['progress_percent'] ?>%</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                <div class="bg-blue-600 h-2 rounded-full transition-all duration-500" style="width: <?= $task['progress_percent'] ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer: Assigned Intern & Due Date -->
                    <div class="mt-5 pt-4 border-t border-slate-100">
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center space-x-2">
                                <div class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 font-bold flex items-center justify-center text-[10px]">
                                    <?= strtoupper(substr($task['intern_name'], 0, 1)) ?>
                                </div>
                                <span class="font-medium text-slate-800"><?= htmlspecialchars($task['intern_name']) ?></span>
                            </div>
                            <div class="<?= $isOverdue ? 'text-rose-600 font-bold' : 'text-slate-500' ?>">
                                <i class="fa-regular fa-calendar mr-1"></i><?= $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : 'No deadline' ?>
                            </div>
                        </div>

                        <!-- Action buttons -->
                        <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                            <!-- Quick Status Update -->
                            <form method="POST" action="" class="flex items-center space-x-1">
                                <input type="hidden" name="action" value="update_task_status">
                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                <select name="status" onchange="this.form.submit()" class="text-[11px] font-semibold py-1 px-2 border border-slate-300 rounded bg-slate-50">
                                    <option value="pending" <?= $task['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="completed" <?= $task['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                </select>
                            </form>

                            <!-- Delete Task -->
                            <form method="POST" action="" onsubmit="return confirm('Delete this task?');">
                                <input type="hidden" name="action" value="delete_task">
                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                <button type="submit" class="text-slate-400 hover:text-rose-600 p-1">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Create Task Modal -->
<div id="create-task-modal" class="modal-container hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-backdrop fixed inset-0 bg-slate-950/60 backdrop-blur-xs"></div>
    <div class="min-h-full flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <h3 class="text-lg font-bold text-slate-900 flex items-center">
                    <i class="fa-solid fa-plus-circle text-blue-600 mr-2"></i>Assign Project Task
                </h3>
                <button type="button" onclick="closeModal('create-task-modal')" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form method="POST" action="" class="space-y-4 mt-4">
                <input type="hidden" name="action" value="create_task">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Task Title</label>
                    <input type="text" name="title" required placeholder="e.g. Implement Timezone Overlap Calculation" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Description & Requirements</label>
                    <textarea name="description" rows="3" placeholder="Provide context, acceptance criteria or links..." class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Assign To Intern</label>
                        <select name="assigned_to" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <?php foreach ($interns as $i): ?>
                                <option value="<?= $i['id'] ?>">
                                    <?= htmlspecialchars($i['name']) ?> (<?= htmlspecialchars($i['intern_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Priority</label>
                        <select name="priority" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Due Date</label>
                    <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-3">
                    <button type="button" onclick="closeModal('create-task-modal')" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md">Assign Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
