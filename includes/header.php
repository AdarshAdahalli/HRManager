<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';

$currentUser = getCurrentUser();
$userRole = $currentUser['role'] ?? 'guest';
$userTimezone = $currentUser['timezone'] ?? DEFAULT_HR_TIMEZONE;
$hrTimezone = DEFAULT_HR_TIMEZONE;
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="h-full flex flex-col antialiased text-slate-800">

<?php if ($currentUser): ?>
    <!-- Top System Live Bar -->
    <header class="bg-slate-900 text-slate-200 border-b border-slate-800 text-xs py-1.5 px-4 sm:px-6">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center space-x-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold <?= $userRole === 'hr' ? 'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' ?>">
                    <i class="fa-solid <?= $userRole === 'hr' ? 'fa-user-tie' : 'fa-graduation-cap' ?> mr-1.5"></i>
                    <?= strtoupper($userRole) ?> PORTAL
                </span>
                <span class="text-slate-400">ID: <strong class="text-slate-200"><?= htmlspecialchars($currentUser['intern_id']) ?></strong></span>
                <span class="hidden md:inline text-slate-600">|</span>
                <span class="hidden md:inline text-slate-400"><i class="fa-solid fa-location-dot mr-1 text-slate-500"></i><?= htmlspecialchars($currentUser['country'] ?? 'Global') ?></span>
            </div>

            <!-- Timezone Live Clocks -->
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-1.5 text-slate-300 bg-slate-800/80 px-2.5 py-1 rounded border border-slate-700">
                    <i class="fa-regular fa-clock text-blue-400"></i>
                    <span class="text-slate-400 font-medium">Your Local Time:</span>
                    <span id="live-user-clock" class="font-mono font-semibold text-white">--:--:--</span>
                    <span class="text-[10px] text-slate-400 bg-slate-700 px-1 rounded"><?= htmlspecialchars($userTimezone) ?></span>
                </div>

                <?php if ($userTimezone !== $hrTimezone): ?>
                <div class="hidden lg:flex items-center space-x-1.5 text-slate-300 bg-slate-800/80 px-2.5 py-1 rounded border border-slate-700">
                    <i class="fa-solid fa-earth-americas text-amber-400"></i>
                    <span class="text-slate-400 font-medium">HR Physical Time:</span>
                    <span id="live-hr-clock" class="font-mono font-semibold text-amber-200">--:--:--</span>
                    <span class="text-[10px] text-slate-400 bg-slate-700 px-1 rounded"><?= htmlspecialchars($hrTimezone) ?></span>
                </div>
                <?php endif; ?>

                <!-- Active Session Badge -->
                <div class="flex items-center space-x-1 text-emerald-400 bg-emerald-950/60 border border-emerald-800 px-2 py-0.5 rounded text-[11px]">
                    <span class="relative flex h-2 w-2 mr-1">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span>Online</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Navigation Bar -->
    <?php require_once __DIR__ . '/navigation.php'; ?>
<?php endif; ?>

<main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
