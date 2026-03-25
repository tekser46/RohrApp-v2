/**
 * RohrApp+ — Admin Activity Logs Page
 */
async function renderAdminLogsPage(renderId) {
    panelLayout('admin/logs', 'Aktivität', '<div class="loading-inline"><div class="loading-spinner"></div></div>');

    try {
        const res = await API.get('admin/logs');
        if (renderId && renderId !== Router.renderCount) return;

        const logs = res.data;
        let rows = '';
        logs.forEach(l => {
            rows += `<tr>
                <td style="font-size:12px">${formatDateTime(l.created_at)}</td>
                <td>${esc(l.user_email || 'System')}</td>
                <td><span class="badge badge-muted">${esc(l.action)}</span></td>
                <td style="font-size:12px">${l.target_type ? esc(l.target_type) + (l.target_id ? ' #' + l.target_id : '') : '-'}</td>
                <td style="font-size:12px;color:var(--text-light)">${l.ip_address || '-'}</td>
            </tr>`;
        });

        document.querySelector('.content-body').innerHTML = `
            <div class="card">
                <div class="card-header"><span class="card-title">Aktivitätsprotokoll</span></div>
                <div class="card-body" style="padding:0">
                    ${logs.length
                        ? '<div style="overflow-x:auto"><table class="data-table"><thead><tr><th>Datum</th><th>Benutzer</th><th>Aktion</th><th>Ziel</th><th>IP</th></tr></thead><tbody>' + rows + '</tbody></table></div>'
                        : '<div class="empty-state"><p>Keine Aktivitäten vorhanden</p></div>'}
                </div>
            </div>
        `;
    } catch (e) {
        document.querySelector('.content-body').innerHTML = `<div class="empty-state"><p>Fehler: ${esc(e.message)}</p></div>`;
    }
}
