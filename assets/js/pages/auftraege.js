/**
 * RohrApp+ — Aufträge Page (Liste/Tablo Görünümü)
 * Tabs: Alle | In Bearbeitung | Erledigt | Storniert
 * Status colors: Offen=blue, Bearbeitung=yellow, Erledigt=green, Storniert=red
 */

let _auftragSearch = '';
let _auftragTab = 'alle';
let _auftragDate = new Date().toISOString().split('T')[0];

async function renderAuftraegePage(renderId) {
    panelLayout('auftraege', 'Aufträge', '<div class="loading-inline"><div class="loading-spinner"></div></div>');
    const content = document.querySelector('.content-body');
    if (!content) return;

    const qp = {};
    if (_auftragSearch) qp.search = _auftragSearch;
    qp.date_from = _auftragDate;
    qp.date_to = _auftragDate;

    try {
        const res = await API.get('auftraege', qp);
        if (renderId && renderId !== Router.renderCount) return;

        const allItems = res.data || [];
        const isToday = _auftragDate === new Date().toISOString().split('T')[0];
        const dateLabel = isToday ? 'Heute' : formatAuftragDateDE(_auftragDate);

        // Count per status
        const countAlle = allItems.length;
        const countOffen = allItems.filter(a => a.status === 'offen').length;
        const countBearbeitung = allItems.filter(a => a.status === 'in_bearbeitung').length;
        const countErledigt = allItems.filter(a => a.status === 'erledigt').length;
        const countStorniert = allItems.filter(a => a.status === 'storniert').length;

        // Filter by tab
        let items;
        if (_auftragTab === 'bearbeitung') items = allItems.filter(a => a.status === 'in_bearbeitung');
        else if (_auftragTab === 'erledigt') items = allItems.filter(a => a.status === 'erledigt');
        else if (_auftragTab === 'storniert') items = allItems.filter(a => a.status === 'storniert');
        else items = allItems;

        const statusBadge = {
            'offen': '<span class="badge badge-info">Offen</span>',
            'in_bearbeitung': '<span class="badge badge-warning">In Bearbeitung</span>',
            'erledigt': '<span class="badge badge-success">Erledigt</span>',
            'storniert': '<span class="badge badge-danger">Storniert</span>',
        };

        const jobColors = {
            'Hauptleitung': '#3b82f6', 'Küche': '#f59e0b', 'Bad': '#06b6d4',
            'Keller': '#8b5cf6', 'Toilette': '#ec4899',
        };

        const rowBg = {
            'offen': 'background:rgba(219,234,254,0.35);',
            'in_bearbeitung': 'background:rgba(254,249,195,0.35);',
            'erledigt': 'background:rgba(209,250,229,0.35);',
            'storniert': 'background:rgba(254,226,226,0.35);opacity:0.7;',
        };

        let rows = '';
        items.forEach(a => {
            const jc = jobColors[a.job_type] || '#64748b';
            const bg = rowBg[a.status] || '';

            let actionBtns = '';
            if (a.status === 'offen') {
                actionBtns = `
                    <button class="btn btn-sm" style="background:#f59e0b;color:#fff;font-size:10px;padding:2px 8px" onclick="event.stopPropagation();confirmAuftragStatus(${a.id},'in_bearbeitung','In Bearbeitung setzen?')">🔧</button>
                    <button class="btn btn-sm" style="background:#10b981;color:#fff;font-size:10px;padding:2px 8px" onclick="event.stopPropagation();confirmAuftragStatus(${a.id},'erledigt','Als erledigt markieren?')">✓</button>
                    <button class="btn btn-sm" style="background:#ef4444;color:#fff;font-size:10px;padding:2px 8px" onclick="event.stopPropagation();confirmAuftragStatus(${a.id},'storniert','Wirklich stornieren?')">✗</button>`;
            } else if (a.status === 'in_bearbeitung') {
                actionBtns = `
                    <button class="btn btn-sm" style="background:#10b981;color:#fff;font-size:10px;padding:2px 8px" onclick="event.stopPropagation();confirmAuftragStatus(${a.id},'erledigt','Als erledigt markieren?')">✓</button>
                    <button class="btn btn-sm" style="background:#ef4444;color:#fff;font-size:10px;padding:2px 8px" onclick="event.stopPropagation();confirmAuftragStatus(${a.id},'storniert','Wirklich stornieren?')">✗</button>`;
            } else {
                actionBtns = `<button class="btn btn-sm btn-secondary" style="font-size:10px;padding:2px 8px" onclick="event.stopPropagation();confirmAuftragStatus(${a.id},'offen','Wieder öffnen?')">↩</button>`;
            }

            rows += `<tr style="cursor:pointer;${bg}" onclick="toggleAuftragDetail(${a.id})" data-auftrag-id="${a.id}">
                <td style="font-weight:600">${esc(a.customer_name || 'Unbekannt')}</td>
                <td>${a.customer_phone ? esc(a.customer_phone) : '<span style="color:#ccc">—</span>'}</td>
                <td>${esc(((a.customer_plz || '') + ' ' + (a.customer_city || '')).trim() || '—')}</td>
                <td><span style="display:inline-block;padding:2px 10px;border-radius:20px;background:${jc};color:#fff;font-size:10px;font-weight:600">${esc(a.job_type)}</span></td>
                <td>${statusBadge[a.status] || ''}</td>
                <td style="font-size:12px;color:var(--text-light)">${formatDateTime(a.created_at)}</td>
                <td style="white-space:nowrap" onclick="event.stopPropagation()">
                    <div style="display:flex;gap:3px">${actionBtns}</div>
                </td>
            </tr>
            <tr id="auftrag-detail-${a.id}" style="display:none">
                <td colspan="7" style="padding:0;border-top:none">
                    <div style="padding:20px 24px;background:#f8fafc;border-bottom:2px solid ${jc}">
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;font-size:13px">
                            <div>
                                <div style="font-size:10px;text-transform:uppercase;color:#94a3b8;font-weight:600;letter-spacing:.5px;margin-bottom:4px">Kundenname</div>
                                <div style="font-weight:600">${esc(a.customer_name || '—')}</div>
                            </div>
                            <div>
                                <div style="font-size:10px;text-transform:uppercase;color:#94a3b8;font-weight:600;letter-spacing:.5px;margin-bottom:4px">Telefon</div>
                                <div style="font-weight:600">${esc(a.customer_phone || '—')}</div>
                            </div>
                            <div>
                                <div style="font-size:10px;text-transform:uppercase;color:#94a3b8;font-weight:600;letter-spacing:.5px;margin-bottom:4px">Adresse</div>
                                <div style="font-weight:600">${esc(a.customer_address || '—')}</div>
                            </div>
                            <div>
                                <div style="font-size:10px;text-transform:uppercase;color:#94a3b8;font-weight:600;letter-spacing:.5px;margin-bottom:4px">PLZ / Stadt</div>
                                <div style="font-weight:600">${esc(((a.customer_plz || '') + ' ' + (a.customer_city || '')).trim() || '—')}</div>
                            </div>
                            <div>
                                <div style="font-size:10px;text-transform:uppercase;color:#94a3b8;font-weight:600;letter-spacing:.5px;margin-bottom:4px">Auftragstyp</div>
                                <div style="margin-top:2px"><span style="padding:3px 12px;border-radius:20px;background:${jc};color:#fff;font-size:11px;font-weight:600">${esc(a.job_type)}</span></div>
                            </div>
                            <div>
                                <div style="font-size:10px;text-transform:uppercase;color:#94a3b8;font-weight:600;letter-spacing:.5px;margin-bottom:4px">Erstellt am</div>
                                <div style="font-weight:600">${formatDateTime(a.created_at)}</div>
                            </div>
                            ${a.from_number ? '<div><div style="font-size:10px;text-transform:uppercase;color:#94a3b8;font-weight:600;letter-spacing:.5px;margin-bottom:4px">Anruf von</div><div style="font-weight:600">' + esc(a.from_number) + '</div></div>' : ''}
                        </div>
                        ${a.notes ? '<div style="margin-top:14px"><div style="font-size:10px;text-transform:uppercase;color:#94a3b8;font-weight:600;letter-spacing:.5px;margin-bottom:4px">Notizen</div><div style="padding:10px 14px;background:#fff;border-radius:8px;font-size:13px;line-height:1.5;border:1px solid #e2e8f0">' + esc(a.notes) + '</div></div>' : ''}
                    </div>
                </td>
            </tr>`;
        });

        function tabClass(t) { return _auftragTab === t ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-secondary'; }

        content.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <button class="${tabClass('alle')}" onclick="switchAuftragTab('alle')">
                        Alle <span class="badge" style="background:rgba(255,255,255,0.3);margin-left:4px">${countAlle}</span>
                    </button>
                    <button class="${tabClass('bearbeitung')}" onclick="switchAuftragTab('bearbeitung')">
                        🔧 Bearbeitung <span class="badge" style="background:rgba(255,255,255,0.3);margin-left:4px">${countBearbeitung}</span>
                    </button>
                    <button class="${tabClass('erledigt')}" onclick="switchAuftragTab('erledigt')">
                        ✅ Erledigt <span class="badge" style="background:rgba(255,255,255,0.3);margin-left:4px">${countErledigt}</span>
                    </button>
                    <button class="${tabClass('storniert')}" onclick="switchAuftragTab('storniert')">
                        ❌ Storniert <span class="badge" style="background:rgba(255,255,255,0.3);margin-left:4px">${countStorniert}</span>
                    </button>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <input type="date" class="form-input" value="${_auftragDate}" style="width:auto;height:32px;font-size:12px;padding:0 8px"
                        onchange="_auftragDate=this.value;renderAuftraegePage(Router.renderCount)">
                    ${!isToday ? '<button class="btn btn-sm btn-secondary" onclick="auftragGoToday()" style="font-size:11px">Heute</button>' : ''}
                    <div style="position:relative">
                        <input type="text" class="form-input" placeholder="Name, Telefon..." value="${esc(_auftragSearch)}"
                            style="width:180px;height:32px;font-size:12px;padding-left:30px"
                            onkeydown="if(event.key==='Enter'){_auftragSearch=this.value;renderAuftraegePage(Router.renderCount)}">
                        <svg style="position:absolute;left:8px;top:8px;pointer-events:none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text-light)" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </div>
                    ${_auftragSearch ? '<button class="btn btn-sm btn-secondary" onclick="resetAuftragSearch()" style="font-size:11px">✕</button>' : ''}
                </div>
            </div>
            <div class="card">
                <div class="card-body" style="padding:0">
                    ${rows
                        ? '<div style="overflow-x:auto"><table class="data-table"><thead><tr><th>Kundenname</th><th>Telefon</th><th>PLZ / Stadt</th><th>Typ</th><th>Status</th><th>Datum</th><th>Aktionen</th></tr></thead><tbody>' + rows + '</tbody></table></div>'
                        : '<div class="empty-state" style="padding:60px 20px"><p>Keine Aufträge am ' + dateLabel + '</p></div>'}
                </div>
            </div>
        `;
    } catch (err) {
        content.innerHTML = '<div class="card"><div class="card-body"><p style="color:var(--danger)">' + esc(err.message) + '</p></div></div>';
    }
}

/** Toggle inline detail row below clicked row */
let _openAuftragId = null;

function toggleAuftragDetail(id) {
    // Close previously open detail
    if (_openAuftragId && _openAuftragId !== id) {
        const prev = document.getElementById('auftrag-detail-' + _openAuftragId);
        if (prev) prev.style.display = 'none';
        const prevRow = document.querySelector('tr[data-auftrag-id="' + _openAuftragId + '"]');
        if (prevRow) prevRow.style.outline = '';
    }

    const detail = document.getElementById('auftrag-detail-' + id);
    if (!detail) return;

    const isOpen = detail.style.display !== 'none';
    detail.style.display = isOpen ? 'none' : 'table-row';
    _openAuftragId = isOpen ? null : id;

    // Highlight active row
    const row = document.querySelector('tr[data-auftrag-id="' + id + '"]');
    if (row) row.style.outline = isOpen ? '' : '2px solid var(--primary)';

    // Scroll into view
    if (!isOpen) {
        setTimeout(() => detail.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
    }
}

async function confirmAuftragStatus(id, status, msg) {
    if (!confirm(msg)) return;
    try {
        await API.put('auftraege/' + id + '/status', { status: status });
        Toast.success('Status aktualisiert');
        renderAuftraegePage(Router.renderCount);
    } catch (err) { Toast.error(err.message); }
}

function switchAuftragTab(tab) {
    _auftragTab = tab;
    renderAuftraegePage(Router.renderCount);
}

function auftragGoToday() {
    _auftragDate = new Date().toISOString().split('T')[0];
    _auftragSearch = '';
    renderAuftraegePage(Router.renderCount);
}

function resetAuftragSearch() {
    _auftragSearch = '';
    renderAuftraegePage(Router.renderCount);
}

function formatAuftragDateDE(d) {
    if (!d) return '';
    const parts = d.split('-');
    return parts[2] + '.' + parts[1] + '.' + parts[0];
}
