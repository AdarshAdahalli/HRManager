<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/timezone_helper.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = "My Free Timings & Timezone - " . APP_NAME;

requireIntern();
$db = getDB();
$user = getCurrentUser();
$message = '';
$error = '';
$hrTz = DEFAULT_HR_TIMEZONE;

// Handle Form Submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // Update Timezone
    if ($action === 'update_timezone') {
        $newTz = trim($_POST['timezone'] ?? 'UTC');
        $stmt = $db->prepare("UPDATE users SET timezone = :tz WHERE id = :id");
        $stmt->execute([':tz' => $newTz, ':id' => $user['id']]);
        $_SESSION['user']['timezone'] = $newTz;
        $user['timezone'] = $newTz;
        $message = "Your timezone has been updated to {$newTz}.";
    }

    // Add Availability Window
    if ($action === 'add_window') {
        $dayOfWeek = (int)($_POST['day_of_week'] ?? 1);
        $startTime = trim($_POST['start_time'] ?? '09:00');
        $endTime = trim($_POST['end_time'] ?? '17:00');
        $internTz = $user['timezone'] ?? 'UTC';

        if ($startTime >= $endTime) {
            $error = 'End time must be later than start time.';
        } else {
            $stmt = $db->prepare("
                INSERT INTO intern_availability (user_id, day_of_week, start_time, end_time, timezone, is_active)
                VALUES (:uid, :day, :st, :et, :tz, 1)
            ");
            $stmt->execute([
                ':uid' => $user['id'],
                ':day' => $dayOfWeek,
                ':st' => $startTime . ':00',
                ':et' => $endTime . ':00',
                ':tz' => $internTz
            ]);
            $message = 'Availability window saved successfully!';
        }
    }

    // Set Standard Mon-Fri 9-5
    if ($action === 'set_standard_week') {
        $internTz = $user['timezone'] ?? 'UTC';
        // Clear existing
        $db->prepare("DELETE FROM intern_availability WHERE user_id = :uid")->execute([':uid' => $user['id']]);

        $stmt = $db->prepare("
            INSERT INTO intern_availability (user_id, day_of_week, start_time, end_time, timezone, is_active)
            VALUES (:uid, :day, '09:00:00', '17:00:00', :tz, 1)
        ");
        for ($day = 1; $day <= 5; $day++) {
            $stmt->execute([':uid' => $user['id'], ':day' => $day, ':tz' => $internTz]);
        }
        $message = 'Standard Monday - Friday (09:00 AM - 05:00 PM) free timings configured!';
    }

    // Delete Availability Window
    if ($action === 'delete_window') {
        $winId = (int)($_POST['window_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM intern_availability WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $winId, ':uid' => $user['id']]);
        $message = 'Availability window removed.';
    }
}

// Fetch Current Availability Windows
$stmtAvail = $db->prepare("
    SELECT * FROM intern_availability 
    WHERE user_id = :uid AND is_active = 1
    ORDER BY day_of_week ASC, start_time ASC
");
$stmtAvail->execute([':uid' => $user['id']]);
$windows = $stmtAvail->fetchAll();

$daysMap = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    7 => 'Sunday'
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Free Timings & Global Availability</h1>
            <p class="text-sm text-slate-500">Specify your available meeting windows in your local timezone so HR's system can calculate common slots.</p>
        </div>

        <form method="POST" action="" onsubmit="return confirm('Replace existing schedule with standard Mon-Fri 9:00 AM - 5:00 PM?');">
            <input type="hidden" name="action" value="set_standard_week">
            <button type="submit" class="px-4 py-2 bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200 font-bold text-xs rounded-xl transition flex items-center">
                <i class="fa-solid fa-magic mr-1.5"></i>Apply Quick Mon-Fri 9AM-5PM
            </button>
        </form>
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

    <!-- Timezone Setting Card -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
        <h2 class="text-base font-bold text-slate-900 flex items-center pb-4 border-b border-slate-100">
            <i class="fa-solid fa-globe text-blue-600 mr-2"></i>My Physical Location & Timezone
        </h2>

        <form method="POST" action="" class="mt-4 flex flex-col sm:flex-row items-end gap-3 text-xs">
            <input type="hidden" name="action" value="update_timezone">
            
            <div class="flex-1 w-full">
                <label class="block font-bold text-slate-700 uppercase mb-1">Your Local Timezone</label>
                <select id="user-timezone-select" name="timezone" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50 font-semibold focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <?php foreach (TimezoneHelper::getCommonTimezones() as $tzKey => $tzLabel): ?>
                        <option value="<?= htmlspecialchars($tzKey) ?>" <?= $user['timezone'] === $tzKey ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tzLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="button" onclick="autoDetectTz()" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition flex items-center shrink-0">
                <i class="fa-solid fa-location-crosshairs mr-1.5 text-blue-500"></i>Detect from Device
            </button>

            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-xs transition shrink-0">
                Save Timezone
            </button>
        </form>
    </div>

    <!-- Add Availability Window with Live Conversion Preview -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Add Window Form -->
        <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200 p-6 shadow-xs h-fit space-y-4">
            <h2 class="text-base font-bold text-slate-900 flex items-center pb-3 border-b border-slate-100">
                <i class="fa-regular fa-calendar-plus text-emerald-600 mr-2"></i>Add Available Time Window
            </h2>

            <form method="POST" action="" class="space-y-4 text-xs">
                <input type="hidden" name="action" value="add_window">

                <div>
                    <label class="block font-bold text-slate-700 uppercase mb-1">Day of the Week</label>
                    <select name="day_of_week" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-slate-50 font-semibold focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <?php foreach ($daysMap as $num => $dayName): ?>
                            <option value="<?= $num ?>"><?= $dayName ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 uppercase mb-1">Start Time (Your Time)</label>
                        <input type="time" id="avail-start" name="start_time" value="09:00" onchange="previewConvertedTimes()" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 uppercase mb-1">End Time (Your Time)</label>
                        <input type="time" id="avail-end" name="end_time" value="17:00" onchange="previewConvertedTimes()" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                </div>

                <!-- Dynamic Live Preview Box -->
                <div class="p-3 bg-gradient-to-br from-indigo-50 to-blue-50 border border-blue-200 rounded-xl text-xs space-y-1">
                    <span class="font-bold text-blue-900 block flex items-center">
                        <i class="fa-solid fa-calculator text-blue-600 mr-1.5"></i>Live HR Conversion Preview:
                    </span>
                    <p id="preview-text" class="text-blue-950 font-medium leading-relaxed">
                        Calculating converted physical time...
                    </p>
                    <span class="text-[10px] text-slate-500 block">HR Physical Timezone: <?= htmlspecialchars($hrTz) ?></span>
                </div>

                <button type="submit" class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-md transition flex items-center justify-center">
                    <i class="fa-solid fa-plus mr-1.5"></i>Save Available Slot
                </button>
            </form>
        </div>

        <!-- Current Schedule List -->
        <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 p-6 shadow-xs flex flex-col">
            <h2 class="text-base font-bold text-slate-900 flex items-center pb-3 border-b border-slate-100">
                <i class="fa-solid fa-calendar-days text-purple-600 mr-2"></i>My Active Free Hours Schedule
            </h2>

            <div class="mt-4 flex-1">
                <?php if (empty($windows)): ?>
                    <div class="text-center py-12 text-slate-400 text-xs">
                        <i class="fa-regular fa-clock text-4xl mb-2 text-slate-300"></i>
                        <p class="text-sm font-semibold text-slate-700">No free timings added yet.</p>
                        <p class="text-xs text-slate-500 mt-1">Add your available hours so HR can coordinate meetings around your schedule.</p>
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($windows as $w): ?>
                            <?php
                            $startLocal = date('h:i A', strtotime($w['start_time']));
                            $endLocal = date('h:i A', strtotime($w['end_time']));

                            // Convert to HR timezone for intern's information
                            $convStart = TimezoneHelper::convertToHRTime($w['start_time'], $user['timezone'], $hrTz);
                            $convEnd = TimezoneHelper::convertToHRTime($w['end_time'], $user['timezone'], $hrTz);
                            ?>
                            <div class="py-3 flex items-center justify-between text-xs hover:bg-slate-50/50 px-2 rounded-xl transition">
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <span class="font-bold text-slate-900 w-24"><?= $daysMap[$w['day_of_week']] ?></span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-mono font-semibold">
                                            <?= $startLocal ?> - <?= $endLocal ?>
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-1 font-mono">
                                        &rarr; HR Physical Time: <strong class="text-slate-700"><?= $convStart['hr_time'] ?? '' ?> - <?= $convEnd['hr_time'] ?? '' ?></strong>
                                    </div>
                                </div>

                                <form method="POST" action="" onsubmit="return confirm('Remove this availability slot?');">
                                    <input type="hidden" name="action" value="delete_window">
                                    <input type="hidden" name="window_id" value="<?= $w['id'] ?>">
                                    <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 rounded">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    function autoDetectTz() {
        const detected = detectBrowserTimezone();
        const select = document.getElementById('user-timezone-select');
        let matched = false;
        for (let opt of select.options) {
            if (opt.value === detected) {
                opt.selected = true;
                matched = true;
                break;
            }
        }
        if (matched) {
            showToast('Detected: ' + detected, 'success');
        } else {
            // Add detected option
            const newOpt = new Option(detected + ' (Detected)', detected, true, true);
            select.add(newOpt);
            showToast('Detected: ' + detected, 'success');
        }
    }

    async function previewConvertedTimes() {
        const st = document.getElementById('avail-start').value;
        const et = document.getElementById('avail-end').value;
        const userTz = "<?= htmlspecialchars($user['timezone']) ?>";
        const hrTz = "<?= htmlspecialchars($hrTz) ?>";
        const previewEl = document.getElementById('preview-text');

        try {
            const resp = await fetch('<?= BASE_URL ?>/api/availability.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'convert_single_time',
                    time: st,
                    intern_timezone: userTz,
                    hr_timezone: hrTz
                })
            });
            const data = await resp.json();
            if (data.success) {
                previewEl.innerHTML = `Your <strong>${st} - ${et}</strong> (${userTz}) = <strong class="text-blue-900">${data.hr_time}</strong> (${hrTz}) in HR physical time.`;
            }
        } catch (e) {
            previewEl.textContent = 'Conversion preview updating...';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        previewConvertedTimes();
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
