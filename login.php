<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect
if (isLoggedIn()) {
    $role = $_SESSION['user']['role'];
    header("Location: " . ($role === 'hr' ? BASE_URL . "/hr/index.php" : BASE_URL . "/intern/index.php"));
    exit;
}

$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($identifier) || empty($password)) {
        $error = 'Please enter your Intern ID / Email and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE (email = :id1 OR intern_id = :id2) AND status = 'active' LIMIT 1");
        $stmt->execute([':id1' => $identifier, ':id2' => $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            recordLogin($user);
            if ($user['role'] === 'hr') {
                header("Location: " . BASE_URL . "/hr/index.php");
            } else {
                header("Location: " . BASE_URL . "/intern/index.php");
            }
            exit;
        } else {
            $error = 'Invalid credentials or inactive account.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-6">
        <!-- Logo & Title -->
        <div class="text-center">
            <div class="mx-auto w-16 h-16 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white shadow-xl shadow-blue-500/20 mb-4">
                <i class="fa-solid fa-people-roof text-3xl"></i>
            </div>
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">Intern<span class="text-blue-600">Hub</span></h2>
            <p class="mt-1 text-sm text-slate-600">Global HR & Intern Management Suite (LAMP Stack)</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800 flex items-center space-x-3">
                <i class="fa-solid fa-circle-exclamation text-rose-500 text-lg"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Login Card -->
        <div class="bg-white py-8 px-6 sm:px-8 shadow-xl shadow-slate-200/50 rounded-2xl border border-slate-200">
            <form method="POST" action="" class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Intern ID or Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-regular fa-envelope"></i>
                        </div>
                        <input id="identifier" name="identifier" type="text" required placeholder="e.g. HR-ADMIN or hr@internhub.com" class="w-full pl-10 pr-3 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <input id="password" name="password" type="password" required placeholder="••••••••" class="w-full pl-10 pr-3 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                </div>

                <button type="submit" class="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-xl shadow-md text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition">
                    <i class="fa-solid fa-right-to-bracket mr-2"></i>Sign In & Log Attendance
                </button>
            </form>

            <!-- Quick Demo Login Presets -->
            <div class="mt-8 pt-6 border-t border-slate-200">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider text-center mb-3">1-Click Demo Logins</p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <button type="button" onclick="fillDemo('hr@internhub.com', 'Password123!')" class="p-2.5 rounded-lg border border-indigo-200 bg-indigo-50/70 hover:bg-indigo-100 text-indigo-900 font-semibold text-left transition flex items-center col-span-2">
                        <i class="fa-solid fa-user-tie text-indigo-600 mr-2 text-sm"></i>
                        <div>
                            <div>HR Manager (Sarah Jenkins)</div>
                            <div class="text-[10px] text-indigo-600 font-normal">hr@internhub.com</div>
                        </div>
                    </button>

                    <button type="button" onclick="fillDemo('alex@internhub.com', 'Password123!')" class="p-2 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-800 font-medium text-left transition">
                        <div class="font-bold flex items-center"><span class="mr-1">🇺🇸</span> Alex Rivera</div>
                        <div class="text-[10px] text-slate-500">New York (EDT)</div>
                    </button>

                    <button type="button" onclick="fillDemo('sofia@internhub.com', 'Password123!')" class="p-2 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-800 font-medium text-left transition">
                        <div class="font-bold flex items-center"><span class="mr-1">🇬🇧</span> Sofia Bennett</div>
                        <div class="text-[10px] text-slate-500">London (BST)</div>
                    </button>

                    <button type="button" onclick="fillDemo('aarav@internhub.com', 'Password123!')" class="p-2 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-800 font-medium text-left transition">
                        <div class="font-bold flex items-center"><span class="mr-1">🇮🇳</span> Aarav Patel</div>
                        <div class="text-[10px] text-slate-500">Bangalore (IST)</div>
                    </button>

                    <button type="button" onclick="fillDemo('kenji@internhub.com', 'Password123!')" class="p-2 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-800 font-medium text-left transition">
                        <div class="font-bold flex items-center"><span class="mr-1">🇯🇵</span> Kenji Sato</div>
                        <div class="text-[10px] text-slate-500">Tokyo (JST)</div>
                    </button>
                </div>
            </div>
        </div>

        <div class="text-center text-xs text-slate-500">
            Powered by Apache, PHP 8 & MySQL &bull; Database: <span class="font-medium text-slate-700"><?= Database::getDriver() === 'mysql' ? 'MySQL' : 'SQLite' ?></span>
        </div>
    </div>

    <script>
        function fillDemo(email, pass) {
            document.getElementById('identifier').value = email;
            document.getElementById('password').value = pass;
        }
    </script>
</body>
</html>
