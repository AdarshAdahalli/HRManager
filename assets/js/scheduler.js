/**
 * Smart Meeting Scheduler & Timezone Heatmap Coordinator
 */

document.addEventListener('DOMContentLoaded', () => {
    const dateInput = document.getElementById('scheduler-date');
    if (dateInput) {
        dateInput.addEventListener('change', () => loadOverlapMatrix(dateInput.value));
        loadOverlapMatrix(dateInput.value);
    }
});

let currentMatrixData = null;

async function loadOverlapMatrix(dateStr) {
    const container = document.getElementById('heatmap-container');
    const recommendationsContainer = document.getElementById('recommendations-container');
    if (!container) return;

    container.innerHTML = `
        <div class="col-span-full py-12 text-center text-slate-400">
            <i class="fa-solid fa-circle-notch fa-spin text-2xl text-blue-500 mb-2"></i>
            <p class="text-sm">Calculating timezone overlaps and attendee windows...</p>
        </div>
    `;

    try {
        const response = await fetch(`${BASE_URL}/api/availability.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_matrix', date: dateStr })
        });

        const result = await response.json();
        if (!result.success) {
            container.innerHTML = `<div class="col-span-full p-4 text-center text-rose-500">Failed to calculate matrix: ${result.error}</div>`;
            return;
        }

        currentMatrixData = result.matrix;
        renderHeatmap(result.matrix);
        renderRecommendations(result.matrix.recommendations);

    } catch (err) {
        console.error('Error loading matrix:', err);
        container.innerHTML = `<div class="col-span-full p-4 text-center text-rose-500">Network error calculating timezone matrix.</div>`;
    }
}

function renderHeatmap(matrix) {
    const container = document.getElementById('heatmap-container');
    if (!container) return;
    container.innerHTML = '';

    matrix.slots.forEach((slot, index) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `heatmap-cell heatmap-level-${slot.level} p-2 rounded-lg border text-center transition flex flex-col items-center justify-between min-h-[72px]`;
        
        btn.innerHTML = `
            <span class="text-[11px] font-bold text-slate-700">${slot.display_hr}</span>
            <div class="my-1">
                <span class="text-xs font-extrabold ${slot.percentage === 100 ? 'text-emerald-700' : slot.percentage >= 70 ? 'text-blue-700' : 'text-slate-600'}">
                    ${slot.available_count}/${slot.total_interns}
                </span>
            </div>
            <span class="text-[10px] font-semibold px-1 rounded ${slot.percentage === 100 ? 'bg-emerald-200 text-emerald-800' : slot.percentage >= 70 ? 'bg-blue-100 text-blue-800' : 'bg-slate-200 text-slate-600'}">
                ${slot.percentage}%
            </span>
        `;

        btn.addEventListener('click', () => showSlotDetails(slot, matrix.hr_timezone, matrix.date));
        container.appendChild(btn);
    });
}

function renderRecommendations(recommendations) {
    const container = document.getElementById('recommendations-container');
    if (!container) return;

    if (!recommendations || recommendations.length === 0) {
        container.innerHTML = `<p class="text-sm text-slate-500 text-center py-4">No overlapping slots found for this date. Check intern availability windows.</p>`;
        return;
    }

    let html = `<div class="space-y-3">`;
    recommendations.forEach((rec, idx) => {
        const isBest = idx === 0;
        html += `
            <div class="p-4 rounded-xl border ${isBest ? 'border-emerald-300 bg-emerald-50/60 shadow-sm' : 'border-slate-200 bg-white'} flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center space-x-2">
                        ${isBest ? '<span class="px-2 py-0.5 text-[11px] font-bold uppercase bg-emerald-600 text-white rounded-full"><i class="fa-solid fa-star mr-1"></i>Best Match</span>' : ''}
                        <h4 class="text-base font-bold text-slate-900">${rec.time_range_hr} (HR Time)</h4>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full ${rec.percentage === 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'}">
                            ${rec.available_count}/${rec.total_interns} Available (${rec.percentage}%)
                        </span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-600">
                        ${rec.intern_breakdown.map(ib => `
                            <span class="inline-flex items-center px-2 py-1 rounded bg-white border border-slate-200 shadow-2xs">
                                <span class="w-2 h-2 rounded-full mr-1.5 ${ib.is_available ? 'bg-emerald-500' : 'bg-rose-400'}"></span>
                                <strong class="mr-1">${ib.name}:</strong> ${ib.local_start}
                            </span>
                        `).join('')}
                    </div>
                </div>

                <button type="button" onclick="selectSlotForBooking('${rec.start_time_hr}', '${rec.end_time_hr}')" class="shrink-0 px-4 py-2 text-xs font-semibold rounded-lg shadow-sm text-white bg-blue-600 hover:bg-blue-700 transition flex items-center">
                    <i class="fa-regular fa-calendar-plus mr-1.5"></i>Schedule This Slot
                </button>
            </div>
        `;
    });
    html += `</div>`;
    container.innerHTML = html;
}

function showSlotDetails(slot, hrTimezone, dateStr) {
    const modal = document.getElementById('slot-detail-modal');
    if (!modal) return;

    document.getElementById('modal-slot-title').textContent = `${slot.full_hr} (${dateStr})`;
    document.getElementById('modal-slot-attendance').textContent = `${slot.available_count} of ${slot.total_interns} Interns Free (${slot.percentage}%)`;

    const listEl = document.getElementById('modal-slot-interns-list');
    listEl.innerHTML = '';

    Object.values(slot.attendee_status).forEach(intern => {
        const item = document.createElement('div');
        item.className = 'flex items-center justify-between p-3 rounded-lg border border-slate-100 bg-slate-50';
        item.innerHTML = `
            <div>
                <p class="text-sm font-bold text-slate-900">${intern.name}</p>
                <p class="text-xs text-slate-500"><i class="fa-solid fa-globe mr-1 text-slate-400"></i>${intern.country} &bull; ${intern.timezone}</p>
            </div>
            <div class="text-right">
                <span class="text-xs font-mono font-semibold block text-slate-700">${intern.local_time}</span>
                <span class="text-[11px] font-semibold ${intern.is_available ? 'text-emerald-600' : 'text-rose-500'}">
                    <i class="fa-solid ${intern.is_available ? 'fa-circle-check text-emerald-500' : 'fa-circle-xmark text-rose-500'} mr-1"></i>
                    ${intern.is_available ? 'Available' : 'Outside Free Hours'}
                </span>
            </div>
        `;
        listEl.appendChild(item);
    });

    const bookBtn = document.getElementById('modal-book-slot-btn');
    if (bookBtn) {
        bookBtn.onclick = () => {
            closeModal('slot-detail-modal');
            const endTimeStr = add30Minutes(slot.time_hr);
            selectSlotForBooking(slot.time_hr, endTimeStr);
        };
    }

    openModal('slot-detail-modal');
}

function selectSlotForBooking(startTime, endTime) {
    const startInput = document.getElementById('meeting_start_hr');
    const endInput = document.getElementById('meeting_end_hr');
    const scheduleModal = document.getElementById('create-meeting-modal');

    if (startInput) startInput.value = startTime;
    if (endInput) endInput.value = endTime;

    if (scheduleModal) {
        openModal('create-meeting-modal');
    }
}

function add30Minutes(timeStr) {
    const parts = timeStr.split(':');
    let h = parseInt(parts[0], 10);
    let m = parseInt(parts[1], 10) + 30;
    if (m >= 60) {
        h = (h + 1) % 24;
        m = 0;
    }
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}
