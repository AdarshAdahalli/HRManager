<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));
?>
<nav class="bg-white border-b border-slate-200 shadow-sm sticky top-0 z-30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <!-- Brand Logo -->
                <a href="<?= $userRole === 'hr' ? BASE_URL . '/hr/index.php' : BASE_URL . '/intern/index.php' ?>" class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white shadow-md shadow-blue-500/20">
                        <i class="fa-solid fa-people-roof text-xl"></i>
                    </div>
                    <div>
                        <span class="text-lg font-bold tracking-tight text-slate-900 block leading-tight">Intern<span class="text-blue-600">Hub</span></span>
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Global LAMP Suite</span>
                    </div>
                </a>

                <!-- Desktop Menu Links -->
                <div class="hidden md:ml-8 md:flex md:space-x-1">
                    <?php if ($userRole === 'hr'): ?>
                        <!-- HR Navigation Items -->
                        <a href="<?= BASE_URL ?>/hr/index.php" class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium transition <?= ($currentPage === 'index.php' && $currentDir === 'hr') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                            <i class="fa-solid fa-chart-pie mr-2 text-blue-500"></i>Dashboard
                        </a>
                        <a href="<?= BASE_URL ?>/hr/interns.php" class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium transition <?= ($currentPage === 'interns.php') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                            <i class="fa-solid fa-users mr-2 text-indigo-500"></i>Interns
                        </a>
                        <a href="<?= BASE_URL ?>/hr/tasks.php" class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium transition <?= ($currentPage === 'tasks.php') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                            <i class="fa-solid fa-list-check mr-2 text-amber-500"></i>Task Board
                        </a>
                        <a href="<?= BASE_URL ?>/hr/difficulties.php" class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium transition <?= ($currentPage === 'difficulties.php') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                            <i class="fa-solid fa-triangle-exclamation mr-2 text-rose-500"></i>Blockers & Help
                        </a>
                        <a href="<?= BASE_URL ?>/hr/attendance.php" class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium transition <?= ($currentPage === 'attendance.php') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                            <i class="fa-regular fa-calendar-check mr-2 text-emerald-500"></i>Attendance
                        </a>
                        <a href="<?= BASE_URL ?>/hr/meeting_scheduler.php" class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium transition <?= ($currentPage === 'meeting_scheduler.php') ? 'bg-indigo-50 text-indigo-700 font-semibold border border-indigo-200' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                            <i class="fa-solid fa-globe mr-2 text-purple-600"></i>Timezone Scheduler
                        </a>
                    <?php else: ?>
                        <!-- Intern Navigation Items -->
                        <a href="<?= BASE_URL ?>/intern/index.php" class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium transition <?= ($currentPage === 'index.php' && $currentDir === 'intern') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                            <i class="fa-solid fa-gauge mr-2 text-emerald-500"></i>My Workspace
                        </a>
                        <a href="<?= BASE_URL ?>/intern/tasks.php" class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium transition <?= ($currentPage === 'tasks.php') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                            <i class="fa-solid fa-list-check mr-2 text-blue-500"></i>My Tasks
                        </a>
                        <a href="<?= BASE_URL ?>/intern/report_difficulty.php" class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium transition <?= ($currentPage === 'report_difficulty.php') ? 'bg-rose-50 text-rose-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                            <i class="fa-solid fa-circle-question mr-2 text-rose-500"></i>Report Blocker
                        </a>
                        <a href="<?= BASE_URL ?>/intern/my_schedule.php" class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium transition <?= ($currentPage === 'my_schedule.php') ? 'bg-purple-50 text-purple-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                            <i class="fa-solid fa-business-time mr-2 text-purple-500"></i>Free Timings & TZ
                        </a>
                        <a href="<?= BASE_URL ?>/intern/attendance.php" class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium transition <?= ($currentPage === 'attendance.php') ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                            <i class="fa-regular fa-clock mr-2 text-teal-500"></i>My Attendance
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Profile & Logout -->
            <div class="flex items-center space-x-3">
                <div class="hidden sm:flex flex-col text-right">
                    <span class="text-sm font-semibold text-slate-800 leading-tight"><?= htmlspecialchars($currentUser['name']) ?></span>
                    <span class="text-xs text-slate-500"><?= htmlspecialchars($currentUser['department'] ?? 'Team') ?></span>
                </div>

                <div class="h-9 w-9 rounded-full bg-slate-200 border border-slate-300 flex items-center justify-center font-bold text-slate-700 text-sm">
                    <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                </div>

                <a href="<?= BASE_URL ?>/logout.php" onclick="return confirm('Confirm Logout? Your session duration will be recorded in the attendance logs.')" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition">
                    <i class="fa-solid fa-arrow-right-from-bracket mr-1.5"></i>Logout
                </a>
            </div>
        </div>
    </div>
</nav>
