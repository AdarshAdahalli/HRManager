<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/timezone_helper.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = "Manage Interns - " . APP_NAME;

requireHR();
$db = getDB();
$message = '';
$error = '';
$generatedCredentials = null;

// Handle Form Submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD NEW INTERN
    if ($action === 'add_intern') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $department = trim($_POST['department'] ?? 'Software Engineering');
        $country = trim($_POST['country'] ?? 'United States');
        $timezone = trim($_POST['timezone'] ?? 'America/New_York');
        $internId = trim($_POST['intern_id'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($internId)) {
            // Auto-generate Intern ID
            $internId = 'INT-' . date('Y') . '-' . sprintf('%03d', rand(10, 999));
        }

        if (empty($password)) {
            // Auto-generate Secure Password
            $password = 'Intern@' . rand(1000, 9999);
        }

        if (empty($name) || empty($email)) {
            $error = 'Name and email are required.';
        } else {
            // Check if email or intern_id already exists
            $check = $db->prepare("SELECT id FROM users WHERE email = :email OR intern_id = :id");
            $check->execute([':email' => $email, ':id' => $internId]);
            if ($check->fetch()) {
                $error = 'An intern with this Email or Intern ID already exists.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("
                    INSERT INTO users (intern_id, name, email, password, plain_temp_password, role, department, country, timezone, status)
                    VALUES (:intern_id, :name, :email, :password, :plain, 'intern', :dept, :country, :tz, 'active')
                ");
                $stmt->execute([
                    ':intern_id' => $internId,
                    ':name' => $name,
                    ':email' => $email,
                    ':password' => $hashedPassword,
                    ':plain' => $password,
                    ':dept' => $department,
                    ':country' => $country,
                    ':tz' => $timezone
                ]);

                $newUserId = (int)$db->lastInsertId();

                // Add default standard availability windows (Mon-Fri 09:00 - 17:00 in intern's timezone)
                $stmtAvail = $db->prepare("
                    INSERT INTO intern_availability (user_id, day_of_week, start_time, end_time, timezone)
                    VALUES (:uid, :day, '09:00:00', '17:00:00', :tz)
                ");
                for ($day = 1; $day <= 5; $day++) {
                    $stmtAvail->execute([':uid' => $newUserId, ':day' => $day, ':tz' => $timezone]);
                }

                $message = "Intern {$name} ({$internId}) has been successfully created!";
                $generatedCredentials = [
                    'name' => $name,
                    'intern_id' => $internId,
                    'email' => $email,
                    'password' => $password,
                    'timezone' => $timezone,
                    'country' => $country
                ];
            }
        }
    }

    // REMOVE INTERN
    if ($action === 'delete_intern') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 1) { // Never delete HR admin
            $stmt = $db->prepare("DELETE FROM users WHERE id = :id AND role = 'intern'");
            $stmt->execute([':id' => $userId]);
            $message = "Intern removed successfully from the system.";
        }
    }

    // RESET PASSWORD
    if ($action === 'reset_password') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newPass = 'Intern@' . rand(1000, 9999);
        $hashed = password_hash($newPass, PASSWORD_BCRYPT);

        $stmt = $db->prepare("
            UPDATE users SET password = :hash, plain_temp_password = :plain WHERE id = :id AND role = 'intern'
        ");
        $stmt->execute([':hash' => $hashed, ':plain' => $newPass, ':id' => $userId]);

        // Get user details
        $stmtUser = $db->prepare("SELECT * FROM users WHERE id = :id");
        $stmtUser->execute([':id' => $userId]);
        $u = $stmtUser->fetch();

        $generatedCredentials = [
            'name' => $u['name'],
            'intern_id' => $u['intern_id'],
            'email' => $u['email'],
            'password' => $newPass,
            'timezone' => $u['timezone'],
            'country' => $u['country'],
            'is_reset' => true
        ];
        $message = "Password reset successfully for {$u['name']}.";
    }
}

// Fetch All Interns
$stmt = $db->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id) as total_tasks,
           (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'completed') as completed_tasks,
           (SELECT COUNT(*) FROM difficulties WHERE user_id = u.id AND status = 'open') as open_difficulties
    FROM users u
    WHERE u.role = 'intern'
    ORDER BY u.created_at DESC
");
$internList = $stmt->fetchAll();

// Next suggested Intern ID
$nextId = 'INT-' . date('Y') . '-' . sprintf('%03d', count($internList) + 1);
$defaultPassword = 'Pass@' . rand(1000, 9999);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Header with Action -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Intern Directory & Onboarding</h1>
            <p class="text-sm text-slate-500">Add interns, auto-generate credentials, manage roles and world timezones.</p>
        </div>
        <button type="button" onclick="openModal('add-intern-modal')" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center justify-center">
            <i class="fa-solid fa-user-plus mr-2"></i>Add New Intern
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

    <?php if ($generatedCredentials): ?>
        <!-- Generated Credentials Display Card -->
        <div class="bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 text-white p-6 rounded-2xl shadow-xl border border-indigo-500/30">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="w-8 h-8 rounded-lg bg-emerald-500 text-slate-950 flex items-center justify-center font-bold">
                        <i class="fa-solid fa-key"></i>
                    </span>
                    <h3 class="text-lg font-bold"><?= isset($generatedCredentials['is_reset']) ? 'New Credentials Reset' : 'Credentials Generated Successfully' ?></h3>
                </div>
                <button type="button" onclick="copyCredentials()" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center">
                    <i class="fa-regular fa-copy mr-1.5"></i>Copy Credentials
                </button>
            </div>
            
            <p class="text-xs text-blue-200 mt-1">Share these login details with the intern to access their InternHub portal:</p>

            <div id="credentials-text" class="mt-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 bg-black/30 p-4 rounded-xl font-mono text-xs border border-white/10">
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase">Intern Name</span>
                    <strong class="text-white text-sm"><?= htmlspecialchars($generatedCredentials['name']) ?></strong>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase">Intern ID</span>
                    <strong class="text-amber-300 text-sm"><?= htmlspecialchars($generatedCredentials['intern_id']) ?></strong>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase">Login Email</span>
                    <strong class="text-blue-300 text-sm"><?= htmlspecialchars($generatedCredentials['email']) ?></strong>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px] uppercase">Generated Password</span>
                    <strong class="text-emerald-300 text-sm"><?= htmlspecialchars($generatedCredentials['password']) ?></strong>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Interns Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase font-semibold border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4">Intern ID & Name</th>
                        <th class="px-6 py-4">Department & Country</th>
                        <th class="px-6 py-4">Timezone</th>
                        <th class="px-6 py-4">Task Deliverables</th>
                        <th class="px-6 py-4">Temp Pass</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($internList)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                                <i class="fa-solid fa-user-slash text-3xl mb-2 text-slate-300"></i>
                                <p>No interns found in the system yet. Click "Add New Intern" above to onboard one!</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($internList as $intern): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-500 to-indigo-600 text-white font-bold flex items-center justify-center text-sm shadow-sm">
                                            <?= strtoupper(substr($intern['name'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900"><?= htmlspecialchars($intern['name']) ?></div>
                                            <div class="text-xs text-blue-600 font-mono"><?= htmlspecialchars($intern['intern_id']) ?> &bull; <?= htmlspecialchars($intern['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-slate-800"><?= htmlspecialchars($intern['department']) ?></div>
                                    <div class="text-xs text-slate-500 flex items-center mt-0.5">
                                        <i class="fa-solid fa-location-dot mr-1 text-slate-400"></i><?= htmlspecialchars($intern['country']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-mono text-xs font-semibold">
                                        <i class="fa-solid fa-earth-americas mr-1.5 text-blue-500"></i>
                                        <?= htmlspecialchars($intern['timezone']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-xs text-slate-700 font-medium">
                                        <span class="text-emerald-600 font-bold"><?= $intern['completed_tasks'] ?></span> of <?= $intern['total_tasks'] ?> completed
                                    </div>
                                    <?php if ($intern['open_difficulties'] > 0): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full bg-rose-100 text-rose-700 mt-1">
                                            <i class="fa-solid fa-triangle-exclamation mr-1"></i><?= $intern['open_difficulties'] ?> open blocker
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if (!empty($intern['plain_temp_password'])): ?>
                                        <code class="text-xs bg-slate-100 text-slate-800 px-2 py-1 rounded border border-slate-200"><?= htmlspecialchars($intern['plain_temp_password']) ?></code>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400">Encrypted</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-medium space-x-2">
                                    <!-- Reset Password -->
                                    <form method="POST" action="" class="inline" onsubmit="return confirm('Generate a new random password for <?= htmlspecialchars($intern['name']) ?>?')">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="user_id" value="<?= $intern['id'] ?>">
                                        <button type="submit" class="p-1.5 text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded" title="Reset Password">
                                            <i class="fa-solid fa-key"></i>
                                        </button>
                                    </form>

                                    <!-- Remove Intern -->
                                    <form method="POST" action="" class="inline" onsubmit="return confirm('Are you sure you want to remove intern <?= htmlspecialchars($intern['name']) ?>? This will delete their assigned tasks and logs.')">
                                        <input type="hidden" name="action" value="delete_intern">
                                        <input type="hidden" name="user_id" value="<?= $intern['id'] ?>">
                                        <button type="submit" class="p-1.5 text-rose-600 hover:text-rose-800 hover:bg-rose-50 rounded" title="Remove Intern">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Intern Modal -->
<div id="add-intern-modal" class="modal-container hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="modal-backdrop fixed inset-0 bg-slate-950/60 backdrop-blur-xs"></div>
    <div class="min-h-full flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <h3 class="text-lg font-bold text-slate-900 flex items-center">
                    <i class="fa-solid fa-user-plus text-blue-600 mr-2"></i>Onboard New Intern
                </h3>
                <button type="button" onclick="closeModal('add-intern-modal')" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form method="POST" action="" class="space-y-4 mt-4">
                <input type="hidden" name="action" value="add_intern">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Full Name</label>
                    <input type="text" name="name" required placeholder="e.g. Jordan Smith" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Intern ID</label>
                        <input type="text" name="intern_id" value="<?= htmlspecialchars($nextId) ?>" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Department</label>
                        <select name="department" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="Software Engineering">Software Engineering</option>
                            <option value="Frontend Development">Frontend Development</option>
                            <option value="Backend Development">Backend Development</option>
                            <option value="Cloud & DevOps">Cloud & DevOps</option>
                            <option value="Data & AI">Data & AI</option>
                            <option value="QA & Testing">QA & Testing</option>
                            <option value="Product Design / UI">Product Design / UI</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Email Address (Login)</label>
                    <input type="email" name="email" required placeholder="intern@company.com" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Country</label>
                        <input type="text" name="country" placeholder="e.g. United States" value="United States" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Timezone</label>
                        <select name="timezone" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <?php foreach (TimezoneHelper::getCommonTimezones() as $tzKey => $tzLabel): ?>
                                <option value="<?= htmlspecialchars($tzKey) ?>" <?= $tzKey === 'America/New_York' ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tzLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase">Auto-Generated Password</label>
                        <button type="button" onclick="regeneratePassField()" class="text-xs text-blue-600 hover:underline"><i class="fa-solid fa-arrows-rotate mr-1"></i>Regenerate</button>
                    </div>
                    <input id="generated-password-input" type="text" name="password" value="<?= htmlspecialchars($defaultPassword) ?>" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <p class="text-[11px] text-slate-400 mt-1">HR can copy this password or it will be shown immediately after creation.</p>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-3">
                    <button type="button" onclick="closeModal('add-intern-modal')" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md">Create & Issue Credentials</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function copyCredentials() {
        const text = `InternHub Login Credentials:\nName: <?= $generatedCredentials['name'] ?? '' ?>\nIntern ID: <?= $generatedCredentials['intern_id'] ?? '' ?>\nEmail: <?= $generatedCredentials['email'] ?? '' ?>\nPassword: <?= $generatedCredentials['password'] ?? '' ?>\nTimezone: <?= $generatedCredentials['timezone'] ?? '' ?>`;
        navigator.clipboard.writeText(text).then(() => {
            showToast('Credentials copied to clipboard!', 'success');
        });
    }

    function regeneratePassField() {
        const randPass = 'Pass@' + Math.floor(1000 + Math.random() * 9000);
        document.getElementById('generated-password-input').value = randPass;
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
