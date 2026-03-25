/**
 * RohrApp+ — Admin Users Page
 */
async function renderAdminUsersPage(renderId) {
    panelLayout('admin/users', 'Benutzer', '<div class="loading-inline"><div class="loading-spinner"></div></div>');

    try {
        const res = await API.get('admin/users');
        if (renderId && renderId !== Router.renderCount) return;

        const users = res.data;
        const planColors = { demo: '#64748b', starter: '#0066a1', professional: '#059669' };

        let rows = '';
        users.forEach(u => {
            const name = [u.first_name, u.last_name].filter(Boolean).join(' ') || '-';
            const color = planColors[u.package_slug] || '#64748b';
            rows += `<tr>
                <td><strong>${esc(name)}</strong><br><span style="font-size:12px;color:var(--text-light)">${esc(u.email)}</span></td>
                <td>${esc(u.company_name || '-')}</td>
                <td><span class="badge" style="background:${color}15;color:${color}">${esc(u.package_name || '-')}</span></td>
                <td><span class="badge badge-${u.role === 'admin' ? 'danger' : 'muted'}">${esc(u.role)}</span></td>
                <td><span class="badge badge-${u.is_active ? 'success' : 'danger'}">${u.is_active ? 'Aktiv' : 'Inaktiv'}</span></td>
                <td style="font-size:12px">${formatDate(u.created_at)}</td>
                <td style="font-size:12px">${u.last_login_at ? formatDateTime(u.last_login_at) : '-'}</td>
            </tr>`;
        });

        document.querySelector('.content-body').innerHTML = `
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                    <span class="card-title">Alle Benutzer</span>
                    <span class="badge badge-info">${users.length}</span>
                </div>
                <div class="card-body" style="padding:0">
                    <div style="overflow-x:auto">
                        <table class="data-table">
                            <thead><tr><th>Name</th><th>Firma</th><th>Paket</th><th>Rolle</th><th>Status</th><th>Registriert</th><th>Letzter Login</th></tr></thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    } catch (e) {
        document.querySelector('.content-body').innerHTML = `<div class="empty-state"><p>Fehler: ${esc(e.message)}</p></div>`;
    }
}
