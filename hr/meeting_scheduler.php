<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/timezone_helper.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = "Global Timezone Meeting Scheduler - " . APP_NAME;

requireHR();
$db = getDB();
$hrTz = DEFAULT_HR_TIMEZONE;
$message = '';
$error = '';

// Handle Meeting Creation
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'schedule_meeting') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $meetingDate = trim($_POST['meeting_date'] ?? date('Y-m-d'));
    $startTimeHr = trim($_POST['start_time_hr'] ?? '18:00');
    $endTimeHr = trim($_POST['end_time_hr'] ?? '19:00');
    $meetingLink = trim($_POST['meeting_link'] ?? 'https://meet.google.com/new');

    if (empty($title) || empty($meetingDate) || empty($startTimeHr) || empty($endTimeHr)) {
        $error = 'Meeting title, date, start time and end time are required.';
    } else {
        try {
            // Convert HR times to UTC for universal storage
            $dtStartHr = new DateTime("{$meetingDate} {$startTimeHr}", new DateTimeZone($hrTz));
            $dtEndHr = new DateTime("{$meetingDate} {$endTimeHr}", new DateTimeZone($hrTz));

            $dtStartUtc = clone $dtStartHr;
            $dtStartUtc->setTimezone(new DateTimeZone('UTC'));

            $dtEndUtc = clone $dtEndHr;
            $dtEndUtc->setTimezone(new DateTimeZone('UTC'));

            // Insert into meetings table
            $stmtMeeting = $db->prepare("
                INSERT INTO meetings (title, description, meeting_date, start_time_hr, end_time_hr, start_utc, end_utc, meeting_link, created_by, status)
                VALUES (:title, :description, :mdate, :st_hr, :end_hr, :st_utc, :end_utc, :link, :created_by, 'scheduled')
            ");
            $stmtMeeting->execute([
                ':title' => $title,
                ':description' => $description,
                ':mdate' => $meetingDate,
                ':st_hr' => $startTimeHr,
                ':end_hr' => $endTimeHr,
                ':st_utc' => $dtStartUtc->format('Y-m-d H:i:s'),
                ':end_utc' => $dtEndUtc->format('Y-m-d H:i:s'),
                ':link' => $meetingLink,
                ':created_by' => $_SESSION['user']['id']
            ]);

            $meetingId = (int)$db->lastInsertId();

            // Fetch all active interns and invite them with their calculated local time
            $allInterns = $db->query("SELECT id, name, timezone FROM users WHERE role = 'intern' AND status = 'active'")->fetchAll();
            $stmtAttendee = $db->prepare("
                INSERT INTO meeting_attendees (meeting_id, user_id, local_start_time, attendance_status)
                VALUES (:mid, :uid, :local_time, 'pending')
            ");

            foreach ($allInterns as $intern) {
                $internLocalDt = clone $dtStartUtc;
                $internLocalDt->setTimezone(new DateTimeZone($intern['timezone']));
                $localFormatted = $internLocalDt->format('M d, h:i A T');

                $stmtAttendee->execute([
                    ':mid' => $meetingId,
                    ':uid' => $intern['id'],
                    ':local_time' => $localFormatted
                ]);
            }

            $message = "Global meeting '{$title}' scheduled successfully! Invitations sent to all " . count($allInterns) . " interns in their local timezones.";
        } catch (Exception $e) {
            $error = 'Failed to schedule meeting: ' . $e->getMessage();
        }
    }
}

// Fetch Upcoming Scheduled Meetings
$stmtUpcoming = $db->query("
    SELECT m.*, 
           (SELECT COUNT(*) FROM meeting_attendees WHERE meeting_id = m.id) as invitees_count
    FROM meetings m
    WHERE m.meeting_date >= CURRENT_DATE() AND m.status = 'scheduled'
    ORDER BY m.meeting_date ASC, m.start_time_hr ASC
");
$upcomingMeetings = $stmtUpcoming->fetchAll();

// All active interns for quick reference
$activeInterns = $db->query("SELECT id, name, country, timezone FROM users WHERE role = 'intern' AND status = 'active' ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-purple-100 text-purple-800">
                    <i class="fa-solid fa-clock-rotate-left mr-1"></i>Multi-Timezone Optimizer
                </span>
                <span class="text-xs text-slate-500 font-medium">HR Physical Node: <strong class="text-slate-800"><?= htmlspecialchars($hrTz) ?></strong></span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight mt-1">Smart Meeting Timezone Coordinator</h1>
            <p class="text-sm text-slate-500">Find the optimal adjustable time where interns from diff parts of the world can join together.</p>
        </div>

        <button type="button" onclick="openModal('create-meeting-modal')" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center justify-center">
            <i class="fa-solid fa-calendar-plus mr-2"></i>Schedule Meeting Manually
        </button>
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

    <!-- Interactive Timezone Converter Playground -->
    <div class="bg-gradient-to-r from-slate-900 to-indigo-950 text-white rounded-2xl p-6 shadow-xl border border-indigo-900/40">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 pb-4 border-b border-white/10">
            <div>
                <h3 class="text-base font-bold flex items-center">
                    <i class="fa-solid fa-calculator text-indigo-400 mr-2"></i>Instant Timezone Converter Calculator
                </h3>
                <p class="text-xs text-slate-300 mt-0.5">Test when an intern selects their preferred local time (e.g. 9:00 AM USA) and see what time it corresponds to in HR physical time.</p>
            </div>
            <div class="text-xs text-indigo-300 font-mono bg-white/10 px-3 py-1 rounded-lg">
                HR Timezone: <?= htmlspecialchars($hrTz) ?>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-12 gap-3 text-xs">
            <div class="sm:col-span-4">
                <label class="block text-slate-300 font-semibold mb-1">Select Intern & Timezone</label>
                <select id="calc-intern" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($activeInterns as $ai): ?>
                        <option value="<?= htmlspecialchars($ai['timezone']) ?>" data-name="<?= htmlspecialchars($ai['name']) ?>" <?= strpos($ai['timezone'], 'New_York') !== false ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ai['name']) ?> (<?= htmlspecialchars($ai['country']) ?> &bull; <?= htmlspecialchars($ai['timezone']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sm:col-span-3">
                <label class="block text-slate-300 font-semibold mb-1">Their Selected Local Time</label>
                <input id="calc-time" type="time" value="09:00" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm">
            </div>

            <div class="sm:col-span-2 flex items-end">
                <button type="button" onclick="runConversionCalculator()" class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-500 font-bold rounded-xl text-white shadow-md transition flex items-center justify-center">
                    <i class="fa-solid fa-arrows-rotate mr-1.5"></i>Convert
                </button>
            </div>

            <div class="sm:col-span-3 flex items-end">
                <div id="calc-result" class="w-full p-2.5 bg-indigo-900/50 border border-indigo-500/30 rounded-xl text-center font-mono">
                    <span class="text-slate-400 text-[10px] block">Converted to HR Time:</span>
                    <strong class="text-amber-300 text-sm" id="calc-result-hr">Click Convert</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Selection & Overlap Matrix Explorer -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div>
                <h2 class="text-lg font-bold text-slate-900 flex items-center">
                    <i class="fa-solid fa-chart-simple text-blue-600 mr-2"></i>24-Hour Overlap Heatmap (HR Physical Time)
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Click any 30-minute block to view each intern's local time and availability.</p>
            </div>

            <div class="flex items-center space-x-3">
                <label for="scheduler-date" class="text-xs font-bold text-slate-700">Date:</label>
                <input type="date" id="scheduler-date" value="<?= date('Y-m-d') ?>" class="px-3 py-1.5 border border-slate-300 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
        </div>

        <!-- Legend -->
        <div class="flex flex-wrap items-center gap-4 text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-100">
            <span class="font-bold text-slate-700">Attendance Legend:</span>
            <div class="flex items-center space-x-1.5">
                <span class="w-3.5 h-3.5 rounded bg-emerald-200 border border-emerald-400"></span>
                <span class="font-semibold text-emerald-800">100% Free (All Interns)</span>
            </div>
            <div class="flex items-center space-x-1.5">
                <span class="w-3.5 h-3.5 rounded bg-blue-100 border border-blue-300"></span>
                <span>70% - 99% Free</span>
            </div>
            <div class="flex items-center space-x-1.5">
                <span class="w-3.5 h-3.5 rounded bg-amber-100 border border-amber-300"></span>
                <span>40% - 69% Free</span>
            </div>
            <div class="flex items-center space-x-1.5">
                <span class="w-3.5 h-3.5 rounded bg-rose-100 border border-rose-300"></span>
                <span>1% - 39% Free</span>
            </div>
            <div class="flex items-center space-x-1.5">
                <span class="w-3.5 h-3.5 rounded bg-slate-100 border border-slate-200"></span>
                <span>0% Free</span>
            </div>
        </div>

        <!-- 48-Slot Heatmap Grid -->
        <div id="heatmap-container" class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-12 gap-2 text-xs">
            <!-- Dynamically populated by assets/js/scheduler.js -->
        </div>

        <!-- Top Recommended Common Slots -->
        <div class="pt-6 border-t border-slate-100">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-slate-900 flex items-center">
                    <i class="fa-solid fa-wand-magic-sparkles text-amber-500 mr-2"></i>Top Recommended Common Meeting Windows
                </h3>
                <span class="text-xs text-slate-400">Calculated across all intern free timing responses</span>
            </div>

            <div id="recommendations-container" class="space-y-3">
                <!-- Dynamically populated by assets/js/scheduler.js -->
            </div>
        </div>
    </div>

    <!-- Upcoming Scheduled Meetings -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
        <h2 class="text-base font-bold text-slate-900 flex items-center mb-4">
            <i class="fa-solid fa-video text-purple-600 mr-2"></i>Upcoming Scheduled Global Meetings
        </h2>

        <?php if (empty($upcomingMeetings)): ?>
            <p class="text-xs text-slate-400 py-4 text-center">No meetings currently scheduled. Select an optimal slot above to schedule one.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($upcomingMeetings as $m): ?>
                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/60 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span class="font-bold text-purple-700 bg-purple-100 px-2 py-0.5 rounded-full"><i class="fa-solid fa-calendar mr-1"></i><?= date('D, M d, Y', strtotime($m['meeting_date'])) ?></span>
                                <span class="font-mono text-slate-700 font-semibold"><?= date('h:i A', strtotime($m['start_time_hr'])) ?> - <?= date('h:i A', strtotime($m['end_time_hr'])) ?> (HR)</span>
                            </div>
                            <h3 class="text-base font-bold text-slate-900 mt-2"><?= htmlspecialchars($m['title']) ?></h3>
                            <p class="text-xs text-slate-600 mt-1"><?= htmlspecialchars($m['description'] ?: 'No agenda description') ?></p>
                        </div>
                        <div class="mt-4 pt-3 border-t border-slate-200 flex items-center justify-between text-xs">
                            <span class="text-slate-500"><i class="fa-solid fa-users mr-1"></i><?= $m['invitees_count'] ?> Interns Invited</span>
                            <a href="<?= htmlspecialchars($m['meeting_link']) ?>" target="_blank" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg font-bold hover:bg-blue-700 transition flex items-center">
                                <i class="fa-solid fa-video mr-1.5"></i>Join Meeting
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Slot Detail Modal -->
<div id="slot-detail-modal" class="modal-container hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-backdrop fixed inset-0 bg-slate-950/60 backdrop-blur-xs"></div>
    <div class="min-h-full flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div>
                    <h3 id="modal-slot-title" class="text-lg font-bold text-slate-900">Slot Details</h3>
                    <p id="modal-slot-attendance" class="text-xs font-semibold text-blue-600 mt-0.5">Attendance Overview</p>
                </div>
                <button type="button" onclick="closeModal('slot-detail-modal')" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <div class="mt-4">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Intern Availability & Converted Local Time</p>
                <div id="modal-slot-interns-list" class="space-y-2 max-h-72 overflow-y-auto pr-1">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                <button type="button" onclick="closeModal('slot-detail-modal')" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800">Close</button>
                <button type="button" id="modal-book-slot-btn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md">
                    <i class="fa-regular fa-calendar-plus mr-1.5"></i>Schedule This Slot
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Meeting Modal -->
<div id="create-meeting-modal" class="modal-container hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-backdrop fixed inset-0 bg-slate-950/60 backdrop-blur-xs"></div>
    <div class="min-h-full flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <h3 class="text-lg font-bold text-slate-900 flex items-center">
                    <i class="fa-solid fa-calendar-check text-blue-600 mr-2"></i>Schedule Global Intern Meeting
                </h3>
                <button type="button" onclick="closeModal('create-meeting-modal')" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form method="POST" action="" class="space-y-4 mt-4">
                <input type="hidden" name="action" value="schedule_meeting">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Meeting Title</label>
                    <input type="text" name="title" required placeholder="e.g. Weekly Global Intern Sync & Demo" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Agenda & Notes</label>
                    <textarea name="description" rows="2" placeholder="Weekly progress review, blocker resolution, and team Q&A..." class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Date</label>
                        <input type="date" name="meeting_date" value="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Start (HR Time)</label>
                        <input id="meeting_start_hr" type="time" name="start_time_hr" value="18:30" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">End (HR Time)</label>
                        <input id="meeting_end_hr" type="time" name="end_time_hr" value="19:30" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Meeting Link (Google Meet / Zoom)</label>
                    <input type="url" name="meeting_link" value="https://meet.google.com/new" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>

                <div class="p-3 bg-purple-50 rounded-xl border border-purple-100 text-xs text-purple-900 flex items-start space-x-2">
                    <i class="fa-solid fa-circle-info text-purple-600 mt-0.5"></i>
                    <span>When scheduled, all interns will automatically receive the meeting invitation converted into their exact physical local timezone!</span>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-3">
                    <button type="button" onclick="closeModal('create-meeting-modal')" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md">Confirm & Notify All Interns</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/scheduler.js"></script>
<script>
    async function runConversionCalculator() {
        const internTz = document.getElementById('calc-intern').value;
        const timeVal = document.getElementById('calc-time').value;
        const dateVal = document.getElementById('scheduler-date')?.value || new Date().toISOString().split('T')[0];
        const resEl = document.getElementById('calc-result-hr');

        resEl.textContent = 'Converting...';

        try {
            const resp = await fetch('<?= BASE_URL ?>/api/availability.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'convert_single_time',
                    time: timeVal,
                    intern_timezone: internTz,
                    hr_timezone: '<?= htmlspecialchars($hrTz) ?>',
                    date: dateVal
                })
            });
            const data = await resp.json();
            if (data.success) {
                resEl.textContent = data.hr_time;
                if (data.day_offset > 0) {
                    resEl.innerHTML += ' <span class="text-[10px] text-emerald-400">(+1 Day)</span>';
                } else if (data.day_offset < 0) {
                    resEl.innerHTML += ' <span class="text-[10px] text-amber-400">(-1 Day)</span>';
                }
            } else {
                resEl.textContent = 'Conversion error';
            }
        } catch (e) {
            resEl.textContent = 'Error';
        }
    }

    // Auto-run calculator on first load
    document.addEventListener('DOMContentLoaded', () => {
        runConversionCalculator();
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
