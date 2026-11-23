<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/timezone_helper.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = "Intern Workspace - " . APP_NAME;

requireIntern();
$db = getDB();
$user = getCurrentUser();
$internTz = $user['timezone'] ?? 'UTC';
$hrTz = DEFAULT_HR_TIMEZONE;

// Fetch Attendance Session Info
$stmtLog = $db->prepare("SELECT login_time, session_minutes FROM attendance_logs WHERE user_id = :id AND status = 'active' ORDER BY id DESC LIMIT 1");
$stmtLog->execute([':id' => $user['id']]);
$currentSession = $stmtLog->fetch();

$loginLocalTime = '—';
if ($currentSession && !empty($currentSession['login_time'])) {
    $dt = new DateTime($currentSession['login_time']);
    try {
        $dt->setTimezone(new DateTimeZone($internTz));
        $loginLocalTime = $dt->format('h:i A T');
    } catch (Exception $e) {
        $loginLocalTime = $currentSession['login_time'];
    }
}

// Tasks Metrics
$stmtTaskStats = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM tasks
    WHERE assigned_to = :id
");
$stmtTaskStats->execute([':id' => $user['id']]);
$taskStats = $stmtTaskStats->fetch();

// Fetch Assigned Tasks
$stmtTasks = $db->prepare("SELECT * FROM tasks WHERE assigned_to = :id ORDER BY CASE status WHEN 'in_progress' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END, due_date ASC");
$stmtTasks->execute([':id' => $user['id']]);
$myTasks = $stmtTasks->fetchAll();

// Fetch Upcoming Meetings for this intern
$stmtMeetings = $db->prepare("
    SELECT m.*, ma.local_start_time, ma.attendance_status
    FROM meetings m
    JOIN meeting_attendees ma ON m.id = ma.meeting_id
    WHERE ma.user_id = :uid AND m.meeting_date >= CURRENT_DATE() AND m.status = 'scheduled'
    ORDER BY m.meeting_date ASC, m.start_utc ASC
");
$stmtMeetings->execute([':uid' => $user['id']]);
$upcomingMeetings = $stmtMeetings->fetchAll();

// Fetch Recent Difficulties Reported
$stmtDiff = $db->prepare("SELECT * FROM difficulties WHERE user_id = :uid ORDER BY created_at DESC LIMIT 3");
$stmtDiff->execute([':uid' => $user['id']]);
$myDifficulties = $stmtDiff->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Top Welcome Card -->
    <div class="bg-gradient-to-r from-emerald-700 via-teal-700 to-cyan-800 rounded-2xl p-6 text-white shadow-lg flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/20 text-white backdrop-blur-sm mb-2">
                <i class="fa-solid fa-graduation-cap mr-1.5 text-emerald-200"></i>Intern Dashboard
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight">Welcome, <?= htmlspecialchars($user['name']) ?></h1>
            <p class="text-sm text-emerald-100 mt-1">
                ID: <span class="font-mono font-bold"><?= htmlspecialchars($user['intern_id']) ?></span> &bull; 
                Role: <span class="font-semibold"><?= htmlspecialchars($user['department'] ?? 'Software Engineering') ?></span> &bull; 
                Timezone: <span class="font-mono underline"><?= htmlspecialchars($internTz) ?></span>
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="<?= BASE_URL ?>/intern/report_difficulty.php" class="px-4 py-2.5 bg-rose-600 hover:bg-rose-700 font-bold text-xs rounded-xl shadow-md transition flex items-center text-white">
                <i class="fa-solid fa-circle-question mr-1.5"></i>Report Blocker / Difficulty
            </a>
            <a href="<?= BASE_URL ?>/intern/my_schedule.php" class="px-4 py-2.5 bg-white/20 hover:bg-white/30 border border-white/20 font-bold text-xs rounded-xl transition flex items-center text-white">
                <i class="fa-solid fa-business-time mr-1.5"></i>Update Free Timings
            </a>
        </div>
    </div>

    <!-- Active Session & Metrics Banner -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Attendance Session Box -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-xs font-bold text-slate-500 uppercase tracking-wider">
                    <span>Attendance Session</span>
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                </div>
                <div class="mt-3 flex items-center space-x-2">
                    <i class="fa-regular fa-clock text-emerald-600 text-xl"></i>
                    <div>
                        <span class="text-xs text-slate-500 block">Logged In Today At:</span>
                        <span class="text-base font-bold text-slate-900 font-mono"><?= $loginLocalTime ?></span>
                    </div>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="text-slate-500">Duration: <strong class="text-emerald-700"><?= $currentSession['session_minutes'] ?? 0 ?> mins</strong></span>
                <a href="<?= BASE_URL ?>/logout.php" onclick="return confirm('Ready to punch out and logout?')" class="text-rose-600 font-bold hover:underline">
                    End Session <i class="fa-solid fa-arrow-right-from-bracket ml-1"></i>
                </a>
            </div>
        </div>

        <!-- Completed Tasks -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500 uppercase tracking-wider">
                <span>Completed Tasks</span>
                <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm"><i class="fa-solid fa-check-double"></i></span>
            </div>
            <div class="mt-3 text-3xl font-extrabold text-emerald-600"><?= (int)$taskStats['completed'] ?></div>
            <p class="text-xs text-slate-500 mt-1">Deliverables submitted and approved</p>
        </div>

        <!-- In-Progress Tasks -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500 uppercase tracking-wider">
                <span>In Progress</span>
                <span class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm"><i class="fa-solid fa-spinner"></i></span>
            </div>
            <div class="mt-3 text-3xl font-extrabold text-indigo-600"><?= (int)$taskStats['in_progress'] ?></div>
            <p class="text-xs text-slate-500 mt-1">Active tasks currently being worked on</p>
        </div>

        <!-- Pending Tasks -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500 uppercase tracking-wider">
                <span>Pending / To-Do</span>
                <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-sm"><i class="fa-solid fa-hourglass-start"></i></span>
            </div>
            <div class="mt-3 text-3xl font-extrabold text-amber-600"><?= (int)$taskStats['pending'] ?></div>
            <p class="text-xs text-slate-500 mt-1">Upcoming assignments queued</p>
        </div>
    </div>

    <!-- Upcoming Meetings in Intern's Local Timezone -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div>
                <h2 class="text-base font-bold text-slate-900 flex items-center">
                    <i class="fa-solid fa-globe text-purple-600 mr-2"></i>Upcoming Global Team Meetings
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Times below are automatically converted to your physical local timezone (<strong class="text-slate-700"><?= htmlspecialchars($internTz) ?></strong>)</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-purple-50 text-purple-700">Auto Timezone Converted</span>
        </div>

        <div class="mt-4">
            <?php if (empty($upcomingMeetings)): ?>
                <div class="text-center py-8 text-slate-400 text-xs">
                    <i class="fa-regular fa-calendar-check text-3xl mb-2 text-slate-300"></i>
                    <p>No upcoming meetings scheduled right now.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($upcomingMeetings as $m): ?>
                        <?php
                        // Convert UTC meeting start to intern local time
                        $dtStart = new DateTime($m['start_utc'], new DateTimeZone('UTC'));
                        $dtStart->setTimezone(new DateTimeZone($internTz));
                        $dtEnd = new DateTime($m['end_utc'], new DateTimeZone('UTC'));
                        $dtEnd->setTimezone(new DateTimeZone($internTz));

                        $meetingDateLocal = $dtStart->format('D, M d, Y');
                        $meetingTimeLocal = $dtStart->format('h:i A') . ' - ' . $dtEnd->format('h:i A T');
                        ?>
                        <div class="p-4 rounded-xl border border-purple-200 bg-purple-50/50 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-purple-800 bg-purple-200/80 px-2 py-0.5 rounded-full"><i class="fa-regular fa-calendar mr-1"></i><?= $meetingDateLocal ?></span>
                                    <span class="text-slate-500 text-[11px]">HR Time: <?= date('h:i A', strtotime($m['start_time_hr'])) ?> (<?= DEFAULT_HR_TIMEZONE ?>)</span>
                                </div>
                                <h3 class="text-base font-bold text-slate-900 mt-2"><?= htmlspecialchars($m['title']) ?></h3>
                                <p class="text-xs text-slate-600 mt-1"><?= htmlspecialchars($m['description'] ?: 'General sync meeting') ?></p>

                                <div class="mt-3 p-2.5 rounded-lg bg-white border border-purple-100 flex items-center space-x-2 text-xs">
                                    <i class="fa-solid fa-clock text-purple-600"></i>
                                    <span class="text-slate-500">Your Local Time:</span>
                                    <strong class="text-purple-950 font-mono"><?= $meetingTimeLocal ?></strong>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-t border-purple-200/60 flex items-center justify-between text-xs">
                                <span class="text-emerald-700 font-semibold"><i class="fa-solid fa-circle-check mr-1"></i>You are invited</span>
                                <a href="<?= htmlspecialchars($m['meeting_link']) ?>" target="_blank" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg font-bold hover:bg-blue-700 transition flex items-center">
                                    <i class="fa-solid fa-video mr-1.5"></i>Join Meeting Link
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Assigned Tasks List -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div>
                <h2 class="text-base font-bold text-slate-900 flex items-center">
                    <i class="fa-solid fa-list-check text-blue-600 mr-2"></i>My Project Tasks & Progress
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Update task status, mark completed, or flag difficulties</p>
            </div>
            <a href="<?= BASE_URL ?>/intern/tasks.php" class="text-xs font-semibold text-blue-600 hover:text-blue-700">Detailed Board &rarr;</a>
        </div>

        <div class="mt-4 divide-y divide-slate-100">
            <?php if (empty($myTasks)): ?>
                <div class="text-center py-8 text-slate-400 text-xs">
                    <i class="fa-solid fa-circle-check text-emerald-500 text-3xl mb-2"></i>
                    <p>No active tasks assigned yet. Enjoy your day!</p>
                </div>
            <?php else: ?>
                <?php foreach ($myTasks as $t): ?>
                    <div class="py-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $t['priority'] === 'urgent' ? 'bg-rose-100 text-rose-800' : ($t['priority'] === 'high' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') ?>">
                                    <?= htmlspecialchars($t['priority']) ?>
                                </span>
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold capitalize <?= $t['status'] === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($t['status'] === 'in_progress' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-600') ?>">
                                    <?= str_replace('_', ' ', $t['status']) ?>
                                </span>
                                <span class="text-xs text-slate-400"><i class="fa-regular fa-calendar mr-1"></i>Due: <?= $t['due_date'] ? date('M d, Y', strtotime($t['due_date'])) : 'Open' ?></span>
                            </div>
                            <h4 class="text-sm font-bold text-slate-900 mt-1"><?= htmlspecialchars($t['title']) ?></h4>
                            <p class="text-xs text-slate-600 mt-0.5"><?= htmlspecialchars($t['description'] ?: 'No description') ?></p>
                            
                            <div class="mt-2 flex items-center space-x-2">
                                <div class="w-48 bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-blue-600 h-1.5 rounded-full" style="width: <?= $t['progress_percent'] ?>%"></div>
                                </div>
                                <span class="text-xs font-mono font-semibold text-slate-700"><?= $t['progress_percent'] ?>%</span>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 shrink-0">
                            <button type="button" onclick="openProgressModal(<?= $t['id'] ?>, '<?= htmlspecialchars($t['title'], ENT_QUOTES) ?>', <?= $t['progress_percent'] ?>, '<?= $t['status'] ?>')" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 transition">
                                <i class="fa-solid fa-pen mr-1"></i>Update Progress
                            </button>
                            <a href="<?= BASE_URL ?>/intern/report_difficulty.php?task_id=<?= $t['id'] ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-rose-50 text-rose-700 hover:bg-rose-100 transition" title="Report difficulty on this task">
                                <i class="fa-solid fa-triangle-exclamation mr-1"></i>Facing Blocker?
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
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
                    <label class="block font-bold text-slate-700 uppercase mb-1">Progress Notes / Updates</label>
                    <textarea id="prog-notes" rows="2" placeholder="Brief note on what was completed..." class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>
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
    function openProgressModal(taskId, title, percent, status) {
        document.getElementById('prog-task-id').value = taskId;
        document.getElementById('prog-modal-task-title').textContent = title;
        document.getElementById('prog-percent').value = percent;
        document.getElementById('prog-percent-display').textContent = percent + '%';
        document.getElementById('prog-status').value = status;
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
