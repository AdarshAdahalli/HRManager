<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = "System Setup & Diagnostics - " . APP_NAME;

$message = '';
$error = '';
$driver = 'unknown';

try {
    $db = getDB();
    $driver = Database::getDriver();

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reseed') {
        // Reset and reseed
        if ($driver === 'mysql') {
            $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $tables = ['meeting_attendees', 'meetings', 'intern_availability', 'difficulties', 'tasks', 'attendance_logs', 'users'];
            foreach ($tables as $t) {
                $db->exec("DROP TABLE IF EXISTS `{$t}`");
            }
            $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

            $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
            $db->exec($schema);
            $seed = file_get_contents(__DIR__ . '/sql/seed.sql');
            $db->exec($seed);
            $message = 'MySQL Database successfully wiped and reseeded with default demo data!';
        } else {
            $sqlitePath = __DIR__ . '/data/intern_management.sqlite';
            if (file_exists($sqlitePath)) {
                unlink($sqlitePath);
            }
            // Re-instantiate
            Database::getConnection();
            $message = 'SQLite Database successfully re-initialized with fresh seed data!';
        }
    }

    // Counts
    $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $taskCount = $db->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
    $logCount = $db->query("SELECT COUNT(*) FROM attendance_logs")->fetchColumn();
    $diffCount = $db->query("SELECT COUNT(*) FROM difficulties")->fetchColumn();

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="py-12 px-4 sm:px-6 lg:px-8 max-w-3xl mx-auto">
    <div class="space-y-6">
        <div class="text-center">
            <h1 class="text-3xl font-extrabold text-slate-900">InternHub System Diagnostics</h1>
            <p class="text-sm text-slate-500 mt-1">LAMP Stack Installation & Database Verification</p>
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

        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
            <h2 class="text-base font-bold text-slate-900 pb-3 border-b border-slate-100 flex items-center">
                <i class="fa-solid fa-server text-blue-600 mr-2"></i>Environment Status
            </h2>

            <div class="grid grid-cols-2 gap-4 text-xs">
                <div class="p-3 bg-slate-50 rounded-xl">
                    <span class="text-slate-400 block text-[10px] uppercase font-bold">PHP Version</span>
                    <strong class="text-slate-900 text-sm"><?= PHP_VERSION ?></strong>
                </div>
                <div class="p-3 bg-slate-50 rounded-xl">
                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Database Driver</span>
                    <strong class="text-emerald-700 text-sm uppercase"><?= $driver ?> Engine</strong>
                </div>
                <div class="p-3 bg-slate-50 rounded-xl">
                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Configured HR Timezone</span>
                    <strong class="text-blue-700 text-sm"><?= DEFAULT_HR_TIMEZONE ?></strong>
                </div>
                <div class="p-3 bg-slate-50 rounded-xl">
                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Database Records</span>
                    <span class="text-slate-700"><?= $userCount ?? 0 ?> users &bull; <?= $taskCount ?? 0 ?> tasks &bull; <?= $logCount ?? 0 ?> attendance &bull; <?= $diffCount ?? 0 ?> blockers</span>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                <form method="POST" action="" onsubmit="return confirm('Wipe and restore fresh demo seed data?');">
                    <input type="hidden" name="action" value="reseed">
                    <button type="submit" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold transition flex items-center">
                        <i class="fa-solid fa-arrows-rotate mr-1.5"></i>Reset & Re-Seed Database
                    </button>
                </form>

                <a href="<?= BASE_URL ?>/login.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md transition flex items-center">
                    <i class="fa-solid fa-arrow-right-to-bracket mr-1.5"></i>Proceed to Login Portal
                </a>
            </div>
        </div>
    </div>
</body>
</html>
