/**
 * RohrApp+ — Falsche Anrufe Page
 * Grey-styled list of wrong/spam calls
 */

async function renderFalscheAnrufePage(renderId) {
    panelLayout('falsche-anrufe', 'Falsche Anrufe', '<div class="loading-inline"><div class="loading-spinner"></div></div>');
    const content = document.querySelector('.content-body');
    if (!content) return;

    try {
        const res = await API.get('calls/falsch');
        if (renderId && renderId !== Router.renderCount) return;

        const calls = res.data || [];

        let rows = '';
        calls.forEach(c => {
            rows += `<tr style="color:#999">
                <td>${esc(c.from_number || '-')}</td>
                <td>${esc(c.to_number || '-')}</td>
                <td>${esc(c.caller_name || '-')}</td>
                <td style="font-size:12px">${formatDateTime(c.started_at)}</td>
                <td><button class="btn btn-sm btn-secondary" style="font-size:10px" onclick="undoFalsch(${c.id})">Rückgängig</button></td>
            </tr>`;
        });

        content.innerHTML = `
            <div class="card" style="background:#fafafa">
                <div class="card-header">
                    <span class="card-title" style="color:#999">❌ Falsche Anrufe</span>
                    <span class="badge badge-secondary" style="margin-left:10px">${calls.length}</span>
                </div>
                <div class="card-body" style="padding:0">
                    ${rows
                        ? '<div style="overflow-x:auto"><table class="data-table" style="opacity:0.7"><thead><tr><th>Von</th><th>An</th><th>Name</th><th>Datum</th><th></th></tr></thead><tbody>' + rows + '</tbody></table></div>'
                        : '<div class="empty-state" style="padding:40px"><p style="color:#999">Keine falschen Anrufe</p></div>'}
                </div>
            </div>
        `;
    } catch (err) {
        content.innerHTML = '<div class="card"><div class="card-body"><p style="color:var(--danger)">' + esc(err.message) + '</p></div></div>';
    }
}

async function undoFalsch(callId) {
    try {
        await API.post('calls/' + callId + '/categorize', { category: 'none' });
        Toast.success('Rückgängig gemacht');
        renderFalscheAnrufePage(Router.renderCount);
    } catch (err) { Toast.error(err.message); }
}
