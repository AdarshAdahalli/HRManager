/**
 * InternHub Client Application JS
 */

// Toast notification helper
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `pointer-events-auto flex items-center space-x-2 px-4 py-3 rounded-lg shadow-lg text-sm text-white transform transition duration-300 translate-y-2 opacity-0 ${
        type === 'success' ? 'bg-emerald-600' :
        type === 'error' ? 'bg-rose-600' :
        type === 'warning' ? 'bg-amber-600' : 'bg-blue-600'
    }`;

    const icon = type === 'success' ? 'fa-circle-check' :
                 type === 'error' ? 'fa-circle-exclamation' :
                 type === 'warning' ? 'fa-triangle-exclamation' : 'fa-info-circle';

    toast.innerHTML = `<i class="fa-solid ${icon}"></i> <span>${message}</span>`;
    container.appendChild(toast);

    // Animate in
    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-2', 'opacity-0');
    });

    // Auto dismiss after 4 seconds
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Modal open/close helpers
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
}

// Close modals when clicking overlay
window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-backdrop')) {
        e.target.closest('.modal-container')?.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
});

// Auto-detect client timezone
function detectBrowserTimezone() {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    } catch (e) {
        return 'UTC';
    }
}
