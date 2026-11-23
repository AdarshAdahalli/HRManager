<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = "HR Dashboard - " . APP_NAME;

requireHR();
$db = getDB();
$hrUser = getCurrentUser();
$hrTz = DEFAULT_HR_TIMEZONE;

// Dashboard Metrics
$totalInterns = $db->query("SELECT COUNT(*) FROM users WHERE role = 'intern' AND status = 'active'")->fetchColumn();
$activeSessions = $db->query("SELECT COUNT(DISTINCT user_id) FROM attendance_logs WHERE status = 'active' AND login_time >= DATE_SUB(NOW(), INTERVAL 12 HOUR)")->fetchColumn();
$pendingTasks = $db->query("SELECT COUNT(*) FROM tasks WHERE status = 'pending'")->fetchColumn();
$inProgressTasks = $db->query("SELECT COUNT(*) FROM tasks WHERE status = 'in_progress'")->fetchColumn();
$completedTasks = $db->query("SELECT COUNT(*) FROM tasks WHERE status = 'completed'")->fetchColumn();
$openDifficulties = $db->query("SELECT COUNT(*) FROM difficulties WHERE status IN ('open', 'in_review')")->fetchColumn();
$upcomingMeetings = $db->query("SELECT COUNT(*) FROM meetings WHERE meeting_date >= CURRENT_DATE() AND status = 'scheduled'")->fetchColumn();

// Fetch Interns with recent attendance
$stmtInterns = $db->query("
    SELECT u.*, 
           (SELECT login_time FROM attendance_logs WHERE user_id = u.id ORDER BY id DESC LIMIT 1) as last_login,
           (SELECT status FROM attendance_logs WHERE user_id = u.id ORDER BY id DESC LIMIT 1) as session_status,
           (SELECT session_minutes FROM attendance_logs WHERE user_id = u.id ORDER BY id DESC LIMIT 1) as last_duration
    FROM users u
    WHERE u.role = 'intern' AND u.status = 'active'
    ORDER BY u.name ASC
");
$interns = $stmtInterns->fetchAll();

// Fetch Recent Difficulties / Blocker Alerts
$stmtDiff = $db->query("
    SELECT d.*, u.name as intern_name, u.intern_id, u.country, t.title as task_title
    FROM difficulties d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN tasks t ON d.task_id = t.id
    WHERE d.status IN ('open', 'in_review')
    ORDER BY 
        CASE d.severity 
            WHEN 'critical' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            ELSE 4 
        END, d.created_at DESC
    LIMIT 5
");
$recentDifficulties = $stmtDiff->fetchAll();

// Fetch Recent Tasks
$stmtTasks = $db->query("
    SELECT t.*, u.name as intern_name, u.intern_id
    FROM tasks t
    JOIN users u ON t.assigned_to = u.id
    ORDER BY t.updated_at DESC
    LIMIT 5
");
$recentTasks = $stmtTasks->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Top Welcome Banner -->
    <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-indigo-800 rounded-2xl p-6 text-white shadow-lg flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/20 text-white backdrop-blur-sm mb-2">
                <i class="fa-solid fa-bolt mr-1.5 text-amber-300"></i>HR Talent Operations Control
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight">Welcome back, <?= htmlspecialchars($hrUser['name']) ?></h1>
            <p class="text-sm text-blue-100 mt-1">Managing <?= $totalInterns ?> interns across global timezones. Coordinated HR time: <span class="font-bold underline"><?= htmlspecialchars($hrTz) ?></span>.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= BASE_URL ?>/hr/meeting_scheduler.php" class="px-4 py-2.5 bg-white text-blue-700 hover:bg-blue-50 font-bold text-xs rounded-xl shadow-md transition flex items-center">
                <i class="fa-solid fa-globe mr-2 text-indigo-600"></i>Timezone Overlap Matrix
            </a>
            <a href="<?= BASE_URL ?>/hr/interns.php" class="px-4 py-2.5 bg-blue-600/60 hover:bg-blue-600 border border-white/20 text-white font-bold text-xs rounded-xl transition flex items-center">
                <i class="fa-solid fa-user-plus mr-2"></i>Add Intern
            </a>
        </div>
    </div>

    <!-- Stat Metric Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Active Interns</span>
                <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-sm"><i class="fa-solid fa-users"></i></span>
            </div>
            <div class="mt-2 text-2xl font-extrabold text-slate-900"><?= $totalInterns ?></div>
            <div class="mt-1 text-xs text-slate-500 flex items-center">
                <span class="w-2 h-2 rounded-full bg-emerald-500 mr-1.5"></span>
                <span><?= $activeSessions ?> logged in recently</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Tasks In Progress</span>
                <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-sm"><i class="fa-solid fa-spinner"></i></span>
            </div>
            <div class="mt-2 text-2xl font-extrabold text-amber-600"><?= $inProgressTasks ?></div>
            <div class="mt-1 text-xs text-slate-500"><?= $pendingTasks ?> pending &bull; <?= $completedTasks ?> done</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Completed Tasks</span>
                <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm"><i class="fa-solid fa-circle-check"></i></span>
            </div>
            <div class="mt-2 text-2xl font-extrabold text-emerald-600"><?= $completedTasks ?></div>
            <div class="mt-1 text-xs text-emerald-700 font-medium">Verified deliverables</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Blockers / Help</span>
                <span class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center text-sm"><i class="fa-solid fa-triangle-exclamation"></i></span>
            </div>
            <div class="mt-2 text-2xl font-extrabold text-rose-600"><?= $openDifficulties ?></div>
            <div class="mt-1 text-xs text-slate-500">Need HR attention</div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Scheduled Meets</span>
                <span class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center text-sm"><i class="fa-solid fa-video"></i></span>
            </div>
            <div class="mt-2 text-2xl font-extrabold text-purple-600"><?= $upcomingMeetings ?></div>
            <div class="mt-1 text-xs text-slate-500">Global meetings upcoming</div>
        </div>
    </div>

    <!-- Main 2-Column Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Global Interns Live Directory -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900 flex items-center">
                        <i class="fa-solid fa-earth-americas text-blue-600 mr-2"></i>Global Interns Live Directory
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Real-time local time and attendance status per intern</p>
                </div>
                <a href="<?= BASE_URL ?>/hr/interns.php" class="text-xs font-semibold text-blue-600 hover:text-blue-700">Manage All &rarr;</a>
            </div>

            <div class="divide-y divide-slate-100 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 uppercase font-semibold">
                        <tr>
                            <th class="px-5 py-3">Intern</th>
                            <th class="px-4 py-3">Location & Timezone</th>
                            <th class="px-4 py-3">Their Local Time Now</th>
                            <th class="px-4 py-3">Attendance</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($interns as $intern): ?>
                            <?php
                            $localDt = new DateTime('now', new DateTimeZone($intern['timezone']));
                            $localTimeFormatted = $localDt->format('h:i A T');
                            $isOnline = ($intern['session_status'] === 'active');
                            ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-xs">
                                            <?= strtoupper(substr($intern['name'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-900 leading-tight"><?= htmlspecialchars($intern['name']) ?></p>
                                            <p class="text-[11px] text-slate-500 font-mono"><?= htmlspecialchars($intern['intern_id']) ?> &bull; <?= htmlspecialchars($intern['department']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span class="font-medium text-slate-800 text-xs block"><?= htmlspecialchars($intern['country']) ?></span>
                                    <span class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($intern['timezone']) ?></span>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="inline-flex items-center px-2 py-1 rounded-md bg-slate-100 text-slate-800 font-mono text-xs font-semibold">
                                        <i class="fa-regular fa-clock mr-1.5 text-slate-400"></i>
                                        <?= $localTimeFormatted ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <?php if ($isOnline): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>Online
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">
                                            Offline
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($intern['last_login'])): ?>
                                        <span class="block text-[10px] text-slate-400 mt-0.5">Last: <?= date('M d, H:i', strtotime($intern['last_login'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap text-right text-xs">
                                    <a href="<?= BASE_URL ?>/hr/tasks.php?intern_id=<?= $intern['id'] ?>" class="text-blue-600 hover:text-blue-800 font-semibold mr-3">Tasks</a>
                                    <a href="<?= BASE_URL ?>/hr/interns.php?edit=<?= $intern['id'] ?>" class="text-slate-500 hover:text-slate-700"><i class="fa-solid fa-pen-to-square"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Blockers / Difficulty Alerts -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs flex flex-col">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900 flex items-center">
                        <i class="fa-solid fa-triangle-exclamation text-rose-500 mr-2"></i>Blocker Alerts
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Difficulties flagged by interns</p>
                </div>
                <a href="<?= BASE_URL ?>/hr/difficulties.php" class="text-xs font-semibold text-rose-600 hover:text-rose-700">View All &rarr;</a>
            </div>

            <div class="p-4 flex-1 space-y-3">
                <?php if (empty($recentDifficulties)): ?>
                    <div class="text-center py-8 text-slate-400 text-xs">
                        <i class="fa-solid fa-circle-check text-emerald-500 text-3xl mb-2"></i>
                        <p>No active blockers reported. All interns are unblocked!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentDifficulties as $diff): ?>
                        <div class="p-3.5 rounded-xl border border-rose-100 bg-rose-50/40 hover:bg-rose-50 transition">
                            <div class="flex items-start justify-between gap-2">
                                <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded <?= $diff['severity'] === 'critical' ? 'bg-rose-600 text-white' : ($diff['severity'] === 'high' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') ?>">
                                    <?= htmlspecialchars($diff['severity']) ?>
                                </span>
                                <span class="text-[10px] text-slate-400 font-medium"><?= date('M d, H:i', strtotime($diff['created_at'])) ?></span>
                            </div>
                            <h4 class="text-sm font-bold text-slate-900 mt-1.5"><?= htmlspecialchars($diff['title']) ?></h4>
                            <p class="text-xs text-slate-600 line-clamp-2 mt-1"><?= htmlspecialchars($diff['description']) ?></p>
                            
                            <div class="mt-2.5 pt-2 border-t border-rose-100 flex items-center justify-between text-xs">
                                <span class="text-slate-500">By: <strong class="text-slate-700"><?= htmlspecialchars($diff['intern_name']) ?></strong></span>
                                <a href="<?= BASE_URL ?>/hr/difficulties.php?id=<?= $diff['id'] ?>" class="text-blue-600 hover:underline font-bold text-xs">
                                    Respond <i class="fa-solid fa-reply ml-1"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
