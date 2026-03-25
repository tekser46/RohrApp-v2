/**
 * RohrApp+ — Anruf (Calls) Page
 * Features: Date picker (default today), search, Falsch/Auftrag buttons
 */

let _callSearch = '';
let _callDate = new Date().toISOString().split('T')[0]; // YYYY-MM-DD today
let _callPollTimer = null;
let _callLastCount = -1;

async function renderCallsPage(renderId) {
    panelLayout('calls', 'Anrufe', '<div class="loading-inline"><div class="loading-spinner"></div></div>');
    const content = document.querySelector('.content-body');
    if (!content) return;

    const qp = { per_page: 50, today: '0' };
    if (_callSearch) {
        qp.search = _callSearch;
    }
    // Always filter by selected date
    qp.date_from = _callDate;
    qp.date_to = _callDate;

    try {
        const res = await API.get('calls', qp);
        if (renderId && renderId !== Router.renderCount) return;

        const calls = res.data || [];
        const meta = res.meta || {};
        const isToday = _callDate === new Date().toISOString().split('T')[0];
        const dateLabel = isToday ? 'Heute' : formatDateDE(_callDate);

        let rows = '';
        calls.forEach(c => {
            const dirIcon = c.direction === 'in'
                ? '<span style="color:var(--success)" title="Eingehend">📞↙</span>'
                : '<span style="color:var(--primary)" title="Ausgehend">📞↗</span>';

            const statusBadge = {
                'ringing':  '<span class="badge badge-warning">Klingelt</span>',
                'answered': '<span class="badge badge-success">Angenommen</span>',
                'missed':   '<span class="badge badge-danger">Verpasst</span>',
                'busy':     '<span class="badge badge-secondary">Besetzt</span>',
                'hangup':   '<span class="badge badge-info">Aufgelegt</span>',
            }[c.status] || '<span class="badge badge-secondary">' + esc(c.status) + '</span>';

            const dur = c.duration > 0 ? formatDuration(c.duration) : '-';
            const missedStyle = c.status === 'missed' ? 'font-weight:700;color:var(--danger);' : '';

            const isFalsch = c.category === 'falsch';
            const isAuftrag = c.category === 'auftrag';
            const rowStyle = isFalsch ? 'opacity:0.4;background:#f5f5f5;' : isAuftrag ? 'background:#f0fdf4;' : '';

            let actions = '';
            if (c.category === 'none' || !c.category) {
                actions = `
                    <button class="btn btn-sm" style="background:#ef4444;color:#fff;font-size:11px;padding:3px 10px;border-radius:6px" onclick="event.stopPropagation();markFalsch(${c.id})">Falsch</button>
                    <button class="btn btn-sm" style="background:#10b981;color:#fff;font-size:11px;padding:3px 10px;border-radius:6px" onclick="event.stopPropagation();showAuftragForm(${c.id},'${esc(c.caller_name || '')}','${esc(c.from_number || '')}')">Auftrag</button>
                `;
            } else if (isFalsch) {
                actions = '<span style="font-size:11px;color:#999">❌ Falsch</span> <button class="btn btn-sm btn-secondary" style="font-size:10px;padding:2px 8px" onclick="event.stopPropagation();undoFalschCall(' + c.id + ')">↩</button>';
            } else if (isAuftrag) {
                actions = '<span style="font-size:11px;color:#10b981;font-weight:600">✅ Auftrag</span> <button class="btn btn-sm btn-secondary" style="font-size:10px;padding:2px 8px" onclick="event.stopPropagation();undoAuftragCall(' + c.id + ')">↩</button>';
            }

            rows += `<tr style="${missedStyle}${rowStyle}" data-call-id="${c.id}">
                <td style="text-align:center">${dirIcon}</td>
                <td>${esc(c.from_number || '-')}</td>
                <td>${esc(c.to_number || '-')}</td>
                <td>${esc(c.caller_name || '-')}</td>
                <td>${statusBadge}</td>
                <td>${dur}</td>
                <td style="font-size:12px;color:var(--text-light);white-space:nowrap">${formatDateTime(c.started_at)}</td>
                <td style="white-space:nowrap">${actions}</td>
            </tr>`;
        });

        content.innerHTML = `
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                    <div style="display:flex;align-items:center;gap:12px">
                        <span class="card-title">${dateLabel}</span>
                        <input type="date" class="form-input" value="${_callDate}" style="width:auto;height:32px;font-size:12px;padding:0 8px"
                            onchange="_callDate=this.value;renderCallsPage(Router.renderCount)">
                        ${!isToday ? '<button class="btn btn-sm btn-secondary" onclick="callGoToday()" style="font-size:11px">Heute</button>' : ''}
                        <span class="badge badge-info">${meta.total || calls.length} Anrufe</span>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center">
                        <div style="position:relative">
                            <input type="text" class="form-input" id="callSearchInput" placeholder="Name, Nummer..." value="${esc(_callSearch)}"
                                style="width:200px;height:32px;font-size:12px;padding-left:30px"
                                onkeydown="if(event.key==='Enter'){_callSearch=this.value;renderCallsPage(Router.renderCount)}">
                            <svg style="position:absolute;left:8px;top:8px;pointer-events:none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text-light)" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </div>
                        ${_callSearch ? '<button class="btn btn-sm btn-secondary" onclick="resetCallSearch()" style="font-size:11px">✕</button>' : ''}
                    </div>
                </div>
                <div class="card-body" style="padding:0">
                    ${rows
                        ? '<div style="overflow-x:auto"><table class="data-table"><thead><tr><th style="width:40px"></th><th>Von</th><th>An</th><th>Name</th><th>Status</th><th>Dauer</th><th>Datum</th><th>Aktion</th></tr></thead><tbody>' + rows + '</tbody></table></div>'
                        : '<div class="empty-state" style="padding:60px 20px"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--border)" stroke-width="1.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72"/></svg><p style="margin-top:16px">Keine Anrufe am ' + dateLabel + '</p></div>'}
                </div>
            </div>
            <div id="auftragFormContainer"></div>
        `;

        // Store count for polling comparison
        _callLastCount = meta.total || calls.length;

        // Start polling (only for today)
        startCallPolling();

    } catch (err) {
        content.innerHTML = '<div class="card"><div class="card-body"><p style="color:var(--danger)">' + esc(err.message) + '</p></div></div>';
    }
}

/** Poll for new calls every 3 seconds */
function startCallPolling() {
    stopCallPolling();
    _callPollTimer = setInterval(async function() {
        // Only poll if we're still on the calls page
        if (Router.currentPage !== 'calls') { stopCallPolling(); return; }

        try {
            const today = new Date().toISOString().split('T')[0];
            const res = await API.get('calls', { per_page: 1, today: '0', date_from: today, date_to: today });
            const newCount = (res.meta && res.meta.total) || 0;

            if (_callLastCount >= 0 && newCount > _callLastCount) {
                // New call arrived!
                _callLastCount = newCount;
                _callDate = today;
                _callSearch = '';
                renderCallsPage(Router.renderCount);
                // Show notification
                Toast.success('📞 Neuer Anruf eingegangen!');
                // Play sound if available
                try { new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQ==').play(); } catch(e) {}
            }
        } catch (e) {
            // Silently ignore polling errors
        }
    }, 3000);
}

function stopCallPolling() {
    if (_callPollTimer) { clearInterval(_callPollTimer); _callPollTimer = null; }
}

function callGoToday() {
    _callDate = new Date().toISOString().split('T')[0];
    _callSearch = '';
    renderCallsPage(Router.renderCount);
}

function resetCallSearch() {
    _callSearch = '';
    renderCallsPage(Router.renderCount);
}

function formatDateDE(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

/** Mark call as Falsch */
async function markFalsch(callId) {
    if (!confirm('Diesen Anruf als "Falscher Anruf" markieren?')) return;
    try {
        await API.post('calls/' + callId + '/categorize', { category: 'falsch' });
        const row = document.querySelector('tr[data-call-id="' + callId + '"]');
        if (row) {
            row.style.opacity = '0.4';
            row.style.background = '#f5f5f5';
            const actionTd = row.querySelector('td:last-child');
            if (actionTd) actionTd.innerHTML = '<span style="font-size:11px;color:#999">❌ Falsch</span> <button class="btn btn-sm btn-secondary" style="font-size:10px;padding:2px 8px" onclick="event.stopPropagation();undoFalschCall(' + callId + ')">↩</button>';
        }
        Toast.success('Als Falscher Anruf markiert');
    } catch (err) { Toast.error(err.message); }
}

async function undoFalschCall(callId) {
    try {
        await API.post('calls/' + callId + '/categorize', { category: 'none' });
        Toast.success('Rückgängig gemacht');
        renderCallsPage(Router.renderCount);
    } catch (err) { Toast.error(err.message); }
}

async function undoAuftragCall(callId) {
    // Check if auftrag has customer data
    try {
        const res = await API.get('auftraege?call_log_id=' + callId);
        const auftrag = res.data && res.data.length > 0 ? res.data[0] : null;

        if (auftrag && (auftrag.customer_name || auftrag.customer_phone || auftrag.customer_address)) {
            // Has customer data — show edit popup instead of deleting
            showAuftragEditPopup(auftrag);
            return;
        }

        // No customer data — confirm and remove
        if (!confirm('Auftrag-Markierung entfernen?')) return;
        await API.post('calls/' + callId + '/categorize', { category: 'none' });
        Toast.success('Rückgängig gemacht');
        renderCallsPage(Router.renderCount);
    } catch (err) { Toast.error(err.message); }
}

function showAuftragEditPopup(auftrag) {
    // Remove existing popup
    const old = document.getElementById('auftragEditPopup');
    if (old) old.remove();

    const jobTypes = ['Hauptleitung','Küche','Bad','Keller','Toilette'];
    const statusOpts = [
        { value: 'offen', label: 'Offen', color: '#3b82f6' },
        { value: 'in_bearbeitung', label: 'In Bearbeitung', color: '#f59e0b' },
        { value: 'erledigt', label: 'Erledigt', color: '#10b981' },
        { value: 'storniert', label: 'Storniert', color: '#ef4444' }
    ];

    const popup = document.createElement('div');
    popup.id = 'auftragEditPopup';
    popup.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center';
    popup.innerHTML = `
        <div style="background:#fff;border-radius:16px;padding:28px;width:520px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <h3 style="margin:0;font-size:18px">Auftrag bearbeiten</h3>
                <button onclick="document.getElementById('auftragEditPopup').remove()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#999">&times;</button>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;display:block;margin-bottom:4px">Kundenname</label>
                    <input class="form-input" id="aep_name" value="${esc(auftrag.customer_name || '')}">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;display:block;margin-bottom:4px">Telefon</label>
                    <input class="form-input" id="aep_phone" value="${esc(auftrag.customer_phone || '')}">
                </div>
                <div style="grid-column:1/-1">
                    <label style="font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;display:block;margin-bottom:4px">Adresse</label>
                    <input class="form-input" id="aep_address" value="${esc(auftrag.customer_address || '')}">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;display:block;margin-bottom:4px">PLZ</label>
                    <input class="form-input" id="aep_plz" value="${esc(auftrag.customer_plz || '')}">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;display:block;margin-bottom:4px">Stadt</label>
                    <input class="form-input" id="aep_city" value="${esc(auftrag.customer_city || '')}">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;display:block;margin-bottom:4px">Auftragstyp</label>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px">
                        ${jobTypes.map(jt => `<button class="btn btn-sm ${auftrag.job_type === jt ? 'btn-primary' : 'btn-secondary'}" onclick="document.querySelectorAll('.aep-jt').forEach(b=>b.className='btn btn-sm btn-secondary aep-jt');this.className='btn btn-sm btn-primary aep-jt';document.getElementById('aep_job_type').value='${jt}'" class="aep-jt">${jt}</button>`).join('')}
                        <input type="hidden" id="aep_job_type" value="${esc(auftrag.job_type || 'Hauptleitung')}">
                    </div>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;display:block;margin-bottom:4px">Status</label>
                    <select class="form-input" id="aep_status" style="padding:8px">
                        ${statusOpts.map(s => `<option value="${s.value}" ${auftrag.status === s.value ? 'selected' : ''}>${s.label}</option>`).join('')}
                    </select>
                </div>
                <div style="grid-column:1/-1">
                    <label style="font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;display:block;margin-bottom:4px">Notizen</label>
                    <textarea class="form-input" id="aep_notes" rows="3" style="resize:vertical">${esc(auftrag.notes || '')}</textarea>
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:20px">
                <button class="btn btn-sm" style="background:#ef4444;color:#fff" onclick="deleteAuftragFromPopup(${auftrag.id}, ${auftrag.call_log_id || 'null'})">🗑 Auftrag löschen</button>
                <div style="display:flex;gap:8px">
                    <button class="btn btn-sm btn-secondary" onclick="document.getElementById('auftragEditPopup').remove()">Abbrechen</button>
                    <button class="btn btn-sm btn-primary" onclick="saveAuftragFromPopup(${auftrag.id})">💾 Speichern</button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(popup);
    // Close on backdrop click
    popup.addEventListener('click', function(e) { if (e.target === popup) popup.remove(); });
}

async function saveAuftragFromPopup(auftragId) {
    try {
        await API.put('auftraege/' + auftragId, {
            customer_name: document.getElementById('aep_name').value,
            customer_phone: document.getElementById('aep_phone').value,
            customer_address: document.getElementById('aep_address').value,
            customer_plz: document.getElementById('aep_plz').value,
            customer_city: document.getElementById('aep_city').value,
            job_type: document.getElementById('aep_job_type').value,
            status: document.getElementById('aep_status').value,
            notes: document.getElementById('aep_notes').value
        });
        document.getElementById('auftragEditPopup').remove();
        Toast.success('Auftrag aktualisiert');
        renderCallsPage(Router.renderCount);
    } catch (err) { Toast.error(err.message); }
}

async function deleteAuftragFromPopup(auftragId, callLogId) {
    if (!confirm('Auftrag wirklich löschen und Markierung entfernen?')) return;
    try {
        await API.delete('auftraege/' + auftragId);
        if (callLogId) {
            await API.post('calls/' + callLogId + '/categorize', { category: 'none' });
        }
        document.getElementById('auftragEditPopup').remove();
        Toast.success('Auftrag gelöscht');
        renderCallsPage(Router.renderCount);
    } catch (err) { Toast.error(err.message); }
}

/** Show Auftrag creation form */
function showAuftragForm(callId, callerName, callerPhone) {
    const container = document.getElementById('auftragFormContainer');
    if (!container) return;

    const JOB_TYPES = ['Hauptleitung', 'Küche', 'Bad', 'Keller', 'Toilette'];
    const jobBtns = JOB_TYPES.map(j =>
        '<button type="button" class="job-type-btn" data-job="' + j + '" onclick="selectJobType(this,\'' + j + '\')" style="padding:10px 18px;border:2px solid var(--border);border-radius:10px;background:var(--card-bg);font-size:13px;font-weight:600;cursor:pointer;transition:all .15s">' + j + '</button>'
    ).join('');

    container.innerHTML = `
        <div class="card" style="margin-top:20px;border:2px solid var(--success)">
            <div class="card-header" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7)">
                <span class="card-title" style="color:var(--success)">📋 Neuer Auftrag</span>
                <button class="btn btn-sm btn-secondary" style="float:right" onclick="document.getElementById('auftragFormContainer').innerHTML=''">✕ Schließen</button>
            </div>
            <div class="card-body">
                <input type="hidden" id="af_call_id" value="${callId}">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Kundenname *</label>
                        <input class="form-input" id="af_name" value="${esc(callerName)}" placeholder="Vor- und Nachname">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Telefon</label>
                        <input class="form-input" id="af_phone" value="${esc(callerPhone)}" placeholder="+49...">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Adresse</label>
                        <input class="form-input" id="af_address" placeholder="Straße Nr.">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 2fr;gap:10px">
                        <div class="form-group" style="margin:0">
                            <label class="form-label">PLZ</label>
                            <input class="form-input" id="af_plz" placeholder="35398">
                        </div>
                        <div class="form-group" style="margin:0">
                            <label class="form-label">Stadt</label>
                            <input class="form-input" id="af_city" placeholder="Gießen">
                        </div>
                    </div>
                </div>

                <label class="form-label">Arbeitstyp *</label>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px" id="jobTypeContainer">
                    ${jobBtns}
                </div>
                <input type="hidden" id="af_job_type" value="">

                <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">Notizen</label>
                    <textarea class="form-textarea" id="af_notes" rows="2" placeholder="Zusätzliche Informationen..."></textarea>
                </div>

                <button class="btn btn-primary" onclick="submitAuftrag()" style="background:var(--success);border-color:var(--success);padding:10px 32px;font-size:14px">
                    ✓ Auftrag speichern
                </button>
            </div>
        </div>
    `;
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function selectJobType(btn, jobType) {
    document.querySelectorAll('.job-type-btn').forEach(b => {
        b.style.borderColor = 'var(--border)';
        b.style.background = 'var(--card-bg)';
        b.style.color = 'inherit';
    });
    btn.style.borderColor = 'var(--success)';
    btn.style.background = '#f0fdf4';
    btn.style.color = 'var(--success)';
    document.getElementById('af_job_type').value = jobType;
}

async function submitAuftrag() {
    const jobType = document.getElementById('af_job_type').value;
    if (!jobType) { Toast.error('Bitte wählen Sie einen Arbeitstyp'); return; }

    const data = {
        call_log_id:      document.getElementById('af_call_id').value,
        customer_name:    document.getElementById('af_name').value.trim(),
        customer_address: document.getElementById('af_address').value.trim(),
        customer_plz:     document.getElementById('af_plz').value.trim(),
        customer_city:    document.getElementById('af_city').value.trim(),
        customer_phone:   document.getElementById('af_phone').value.trim(),
        job_type:         jobType,
        notes:            document.getElementById('af_notes').value.trim(),
    };

    try {
        await API.post('auftraege', data);
        Toast.success('Auftrag erstellt!');
        document.getElementById('auftragFormContainer').innerHTML = '';
        const row = document.querySelector('tr[data-call-id="' + data.call_log_id + '"]');
        if (row) {
            row.style.background = '#f0fdf4';
            const actionTd = row.querySelector('td:last-child');
            if (actionTd) actionTd.innerHTML = '<span style="font-size:11px;color:#10b981;font-weight:600">✅ Auftrag</span>';
        }
    } catch (err) { Toast.error(err.message); }
}

function formatDuration(seconds) {
    if (!seconds || seconds <= 0) return '-';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return m > 0 ? m + ':' + String(s).padStart(2, '0') + ' Min' : s + ' Sek';
}
