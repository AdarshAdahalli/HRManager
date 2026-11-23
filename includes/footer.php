</main>

<footer class="bg-white border-t border-slate-200 mt-auto py-4 text-xs text-slate-500">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2">
        <div class="flex items-center space-x-2">
            <span class="font-semibold text-slate-700"><?= APP_NAME ?></span>
            <span>&bull;</span>
            <span>LAMP Stack Intern Management Suite</span>
            <span>&bull;</span>
            <span class="text-slate-400">DB: <?= Database::getDriver() === 'mysql' ? '<span class="text-emerald-600 font-medium">MySQL Active</span>' : '<span class="text-amber-600 font-medium">SQLite Engine</span>' ?></span>
        </div>
        <div class="flex items-center space-x-4">
            <span>HR Node: <strong class="text-slate-600"><?= htmlspecialchars(DEFAULT_HR_TIMEZONE) ?></strong></span>
            <span>&bull;</span>
            <a href="https://github.com" target="_blank" class="hover:text-blue-600"><i class="fa-brands fa-github mr-1"></i>Documentation</a>
        </div>
    </div>
</footer>

<!-- Toast Notifications Container -->
<div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col space-y-2 pointer-events-none"></div>

<!-- Core Scripts -->
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script>
    // Initialize Live Clocks with IANA Timezones
    const userTz = "<?= htmlspecialchars($userTimezone) ?>";
    const hrTz   = "<?= htmlspecialchars($hrTimezone) ?>";

    function updateLiveClocks() {
        const now = new Date();
        const userClockEl = document.getElementById('live-user-clock');
        const hrClockEl = document.getElementById('live-hr-clock');

        if (userClockEl) {
            try {
                userClockEl.textContent = new Intl.DateTimeFormat('en-US', {
                    timeZone: userTz,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                }).format(now);
            } catch (e) {
                userClockEl.textContent = now.toLocaleTimeString();
            }
        }

        if (hrClockEl) {
            try {
                hrClockEl.textContent = new Intl.DateTimeFormat('en-US', {
                    timeZone: hrTz,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                }).format(now);
            } catch (e) {
                hrClockEl.textContent = now.toLocaleTimeString();
            }
        }
    }

    setInterval(updateLiveClocks, 1000);
    updateLiveClocks();

    // Heartbeat ping every 60 seconds to keep attendance alive
    setInterval(() => {
        fetch('<?= BASE_URL ?>/api/heartbeat.php', { method: 'POST' })
            .catch(err => console.debug('Heartbeat ping:', err));
    }, 60000);
</script>
</body>
</html>
