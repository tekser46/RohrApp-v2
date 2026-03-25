/**
 * RohrApp+ — Admin Upgrade Requests Page
 */
async function renderAdminRequestsPage(renderId) {
    panelLayout('admin/requests', 'Anfragen', '<div class="loading-inline"><div class="loading-spinner"></div></div>');

    try {
        const res = await API.get('admin/upgrade-requests');
        if (renderId && renderId !== Router.renderCount) return;

        const requests = res.data;
        const statusBadge = { pending: 'warning', approved: 'success', rejected: 'danger' };
        const statusLabel = { pending: 'Ausstehend', approved: 'Genehmigt', rejected: 'Abgelehnt' };

        let rows = '';
        requests.forEach(r => {
            const actions = r.status === 'pending'
                ? `<button class="btn btn-sm btn-primary" onclick="handleUpgradeRequest(${r.id},'approve')" style="margin-right:4px">✓ Genehmigen</button>
                   <button class="btn btn-sm btn-secondary" onclick="handleUpgradeRequest(${r.id},'reject')">✗ Ablehnen</button>`
                : (r.admin_note ? '<span style="font-size:12px;color:var(--text-light)">' + esc(r.admin_note) + '</span>' : '-');

            rows += `<tr>
                <td><strong>${esc(r.user_name || '-')}</strong><br><span style="font-size:12px;color:var(--text-light)">${esc(r.user_email)}</span>${r.user_company ? '<br><span style="font-size:11px;color:var(--text-muted)">' + esc(r.user_company) + '</span>' : ''}</td>
                <td>${esc(r.current_package_name)} → <strong>${esc(r.requested_package_name)}</strong></td>
                <td style="font-size:12px;max-width:200px">${r.user_message ? esc(r.user_message) : '-'}</td>
                <td><span class="badge badge-${statusBadge[r.status]}">${statusLabel[r.status]}</span></td>
                <td style="font-size:12px">${formatDateTime(r.created_at)}</td>
                <td>${actions}</td>
            </tr>`;
        });

        document.querySelector('.content-body').innerHTML = `
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                    <span class="card-title">Upgrade-Anfragen</span>
                    <span class="badge badge-info">${requests.filter(r => r.status === 'pending').length} ausstehend</span>
                </div>
                <div class="card-body" style="padding:0">
                    ${requests.length
                        ? '<div style="overflow-x:auto"><table class="data-table"><thead><tr><th>Benutzer</th><th>Upgrade</th><th>Nachricht</th><th>Status</th><th>Datum</th><th>Aktion</th></tr></thead><tbody>' + rows + '</tbody></table></div>'
                        : '<div class="empty-state"><p>Keine Anfragen vorhanden</p></div>'}
                </div>
            </div>
        `;
    } catch (e) {
        document.querySelector('.content-body').innerHTML = `<div class="empty-state"><p>Fehler: ${esc(e.message)}</p></div>`;
    }
}

async function handleUpgradeRequest(id, action) {
    let note = '';
    if (action === 'reject') {
        note = prompt('Grund für Ablehnung (optional):') || '';
    }
    try {
        await API.put('admin/upgrade-requests/' + id, { action, note });
        Toast.success(action === 'approve' ? 'Anfrage genehmigt!' : 'Anfrage abgelehnt.');
        renderAdminRequestsPage(Router.renderCount);
    } catch (err) {
        Toast.error(err.message);
    }
}
