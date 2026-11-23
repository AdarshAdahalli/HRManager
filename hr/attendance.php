<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = "Intern Attendance Logs - " . APP_NAME;

requireHR();
$db = getDB();
$hrTz = DEFAULT_HR_TIMEZONE;

// Filters
$internIdFilter = isset($_GET['intern_id']) ? (int)$_GET['intern_id'] : 0;
$dateFilter = $_GET['date'] ?? '';

// Build Query
$sql = "
    SELECT a.*, u.name as intern_name, u.intern_id, u.timezone as intern_timezone, u.country
    FROM attendance_logs a
    JOIN users u ON a.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($internIdFilter > 0) {
    $sql .= " AND a.user_id = :uid";
    $params[':uid'] = $internIdFilter;
}
if (!empty($dateFilter)) {
    $sql .= " AND DATE(a.login_time) = :logindate";
    $params[':logindate'] = $dateFilter;
}

$sql .= " ORDER BY a.login_time DESC LIMIT 100";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Interns for filter
$interns = $db->query("SELECT id, name, intern_id FROM users WHERE role = 'intern' ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Attendance & Session Tracking</h1>
            <p class="text-sm text-slate-500">Real-time login and logout timestamps converted across HR physical time and intern timezones.</p>
        </div>
        <button type="button" onclick="window.print()" class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold text-xs rounded-xl shadow-xs transition flex items-center">
            <i class="fa-solid fa-print mr-2"></i>Print Attendance Sheet
        </button>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-center gap-3 text-xs">
        <span class="font-bold text-slate-500 uppercase"><i class="fa-solid fa-filter mr-1"></i>Filter Logs:</span>
        <form method="GET" action="" class="flex flex-wrap items-center gap-3">
            <select name="intern_id" onchange="this.form.submit()" class="px-3 py-1.5 border border-slate-300 rounded-lg bg-slate-50 font-medium">
                <option value="0">All Interns</option>
                <?php foreach ($interns as $i): ?>
                    <option value="<?= $i['id'] ?>" <?= $internIdFilter === $i['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($i['name']) ?> (<?= htmlspecialchars($i['intern_id']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>" onchange="this.form.submit()" class="px-3 py-1.5 border border-slate-300 rounded-lg bg-slate-50 font-medium">

            <?php if ($internIdFilter || $dateFilter): ?>
                <a href="<?= BASE_URL ?>/hr/attendance.php" class="text-rose-600 font-bold hover:underline">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Attendance Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase font-semibold border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4">Intern</th>
                        <th class="px-6 py-4">Login Time (HR Time)</th>
                        <th class="px-6 py-4">Intern Local Time</th>
                        <th class="px-6 py-4">Logout Time</th>
                        <th class="px-6 py-4">Session Duration</th>
                        <th class="px-6 py-4">IP Address</th>
                        <th class="px-6 py-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                <i class="fa-regular fa-clock text-4xl mb-3 text-slate-300"></i>
                                <p class="text-base font-semibold text-slate-700">No attendance sessions found.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $loginDt = new DateTime($log['login_time']);
                            $loginHr = $loginDt->format('M d, Y h:i A');

                            // Intern Local login time
                            $internLoginDt = clone $loginDt;
                            try {
                                $internLoginDt->setTimezone(new DateTimeZone($log['intern_timezone']));
                                $loginIntern = $internLoginDt->format('h:i A T');
                            } catch (Exception $e) {
                                $loginIntern = '—';
                            }

                            $logoutHr = !empty($log['logout_time']) ? date('M d, Y h:i A', strtotime($log['logout_time'])) : '<span class="text-emerald-600 font-semibold italic">Active (In Session)</span>';

                            $durationHours = floor($log['session_minutes'] / 60);
                            $durationMins = $log['session_minutes'] % 60;
                            $durationText = ($log['status'] === 'active') 
                                ? "<span class='text-emerald-600 font-bold'>Active Now</span>" 
                                : sprintf('%02dh %02dm', $durationHours, $durationMins);
                            ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($log['intern_name']) ?></div>
                                    <div class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($log['intern_id']) ?> &bull; <?= htmlspecialchars($log['country']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-slate-800">
                                    <?= $loginHr ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-mono text-xs font-semibold">
                                        <?= $loginIntern ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-slate-600">
                                    <?= $logoutHr ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-xs font-semibold text-slate-900">
                                    <?= $durationText ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-slate-500">
                                    <?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($log['status'] === 'active'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 flex items-center w-fit">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>In Session
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
                                            Completed
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
