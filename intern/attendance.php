<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = "My Attendance - " . APP_NAME;

requireIntern();
$db = getDB();
$user = getCurrentUser();
$internTz = $user['timezone'] ?? 'UTC';

// Fetch attendance logs for this intern
$stmt = $db->prepare("
    SELECT * FROM attendance_logs 
    WHERE user_id = :uid 
    ORDER BY login_time DESC 
    LIMIT 50
");
$stmt->execute([':uid' => $user['id']]);
$logs = $stmt->fetchAll();

// Calculate total hours logged this week
$stmtWeek = $db->prepare("
    SELECT SUM(session_minutes) as total_mins 
    FROM attendance_logs 
    WHERE user_id = :uid AND login_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmtWeek->execute([':uid' => $user['id']]);
$weekMins = (int)$stmtWeek->fetchColumn();
$weekHours = floor($weekMins / 60);
$weekRemMins = $weekMins % 60;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">My Attendance History</h1>
            <p class="text-sm text-slate-500">Track your daily punches, login and logout times, and total active session duration.</p>
        </div>

        <div class="bg-white px-4 py-2.5 rounded-xl border border-slate-200 shadow-xs flex items-center space-x-3 text-xs">
            <span class="text-slate-500 font-medium">Logged this week:</span>
            <span class="font-mono font-bold text-emerald-600 text-sm"><?= $weekHours ?>h <?= $weekRemMins ?>m</span>
        </div>
    </div>

    <!-- Attendance Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase font-semibold border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Login Time (Local)</th>
                        <th class="px-6 py-4">Logout Time (Local)</th>
                        <th class="px-6 py-4">Session Duration</th>
                        <th class="px-6 py-4">IP Address</th>
                        <th class="px-6 py-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                                <i class="fa-regular fa-clock text-4xl mb-3 text-slate-300"></i>
                                <p class="text-base font-semibold text-slate-700">No attendance history logged yet.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $loginDt = new DateTime($log['login_time']);
                            try {
                                $loginDt->setTimezone(new DateTimeZone($internTz));
                                $loginFormatted = $loginDt->format('h:i A T');
                                $dateFormatted = $loginDt->format('M d, Y');
                            } catch (Exception $e) {
                                $loginFormatted = $log['login_time'];
                                $dateFormatted = date('M d, Y', strtotime($log['login_time']));
                            }

                            if (!empty($log['logout_time'])) {
                                $logoutDt = new DateTime($log['logout_time']);
                                try {
                                    $logoutDt->setTimezone(new DateTimeZone($internTz));
                                    $logoutFormatted = $logoutDt->format('h:i A T');
                                } catch (Exception $e) {
                                    $logoutFormatted = $log['logout_time'];
                                }
                            } else {
                                $logoutFormatted = '<span class="text-emerald-600 font-bold italic">Active Session</span>';
                            }

                            $durationHours = floor($log['session_minutes'] / 60);
                            $durationMins = $log['session_minutes'] % 60;
                            $durationText = ($log['status'] === 'active') 
                                ? "<span class='text-emerald-600 font-bold'>Active Now</span>" 
                                : sprintf('%02dh %02dm', $durationHours, $durationMins);
                            ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-slate-900">
                                    <?= $dateFormatted ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-slate-800">
                                    <?= $loginFormatted ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-slate-600">
                                    <?= $logoutFormatted ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-xs font-semibold text-slate-900">
                                    <?= $durationText ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-slate-400">
                                    <?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($log['status'] === 'active'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 flex items-center w-fit">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>Online
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
                                            Recorded
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
