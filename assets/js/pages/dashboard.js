/**
 * RohrApp+ — Dashboard Page
 */
async function renderDashboardPage(renderId) {
    panelLayout('dashboard', 'Dashboard', '<div class="loading-inline"><div class="loading-spinner"></div></div>');

    try {
        const res = await API.get('dashboard');
        if (renderId && renderId !== Router.renderCount) return; // stale — user navigated away
        const d = res.data;
        const lic = d.license;
        const stats = d.stats;

        const planColors = { demo: '#64748b', starter: '#0066a1', professional: '#059669' };
        const planColor = planColors[lic?.package_slug] || '#64748b';

        // Recent calls rows
        let callRows = '';
        if (d.recent_calls && d.recent_calls.length) {
            d.recent_calls.forEach(c => {
                const dir = c.direction === 'in' ? '↙' : '↗';
                const statusCls = c.status === 'answered' ? 'success' : c.status === 'missed' ? 'danger' : 'muted';
                callRows += `<tr>
                    <td>${dir} ${esc(c.from_number || '-')}</td>
                    <td>${esc(c.to_number || '-')}</td>
                    <td><span class="badge badge-${statusCls}">${esc(c.status)}</span></td>
                    <td>${formatDateTime(c.started_at)}</td>
                </tr>`;
            });
        }

        document.querySelector('.content-header h1').textContent = `Willkommen, ${AppState.displayName()}!`;
        document.querySelector('.content-body').innerHTML = `
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background:${planColor}15;color:${planColor}">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 12l2 2 4-4"/></svg>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value" style="color:${planColor}">${esc(lic?.package_name || 'Kein Paket')}</div>
                        <div class="stat-label">Aktuelles Paket</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(220,38,38,0.08);color:#dc2626">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value">${stats.unread_messages}</div>
                        <div class="stat-label">Ungelesene Nachrichten</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(5,150,105,0.08);color:#059669">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3"/></svg>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value">${stats.today_calls}</div>
                        <div class="stat-label">Anrufe heute</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(0,102,161,0.08);color:#0066a1">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89"/></svg>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value">${stats.unread_emails}</div>
                        <div class="stat-label">Ungelesene E-Mails</div>
                    </div>
                </div>
            </div>

            <!-- License Quick Info -->
            <div class="card" style="margin-top:20px">
                <div class="card-header"><span class="card-title">Lizenz-Übersicht</span></div>
                <div class="card-body">
                    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                        <div style="padding:10px 18px;background:${planColor}10;border-radius:10px;font-weight:700;color:${planColor}">${esc(lic?.package_name || '-')}</div>
                        <div style="font-size:13px;color:var(--text-light)">Schlüssel: <strong style="font-family:monospace">${esc(lic?.license_key || '-')}</strong></div>
                        <div style="font-size:13px;color:var(--text-light)">Status: <span class="badge badge-${lic?.status === 'active' ? 'success' : lic?.status === 'trial' ? 'info' : 'muted'}">${esc(lic?.status || '-')}</span></div>
                        ${lic?.expires_at ? '<div style="font-size:13px;color:var(--text-light)">Gültig bis: <strong>' + formatDate(lic.expires_at) + '</strong></div>' : ''}
                    </div>
                </div>
            </div>

            <!-- Recent Calls -->
            <div class="card" style="margin-top:20px">
                <div class="card-header"><span class="card-title">Letzte Anrufe</span></div>
                <div class="card-body" style="padding:0">
                    ${callRows ? '<table class="data-table"><thead><tr><th>Von</th><th>An</th><th>Status</th><th>Datum</th></tr></thead><tbody>' + callRows + '</tbody></table>'
                    : '<div class="empty-state"><p>Noch keine Anrufe vorhanden</p></div>'}
                </div>
            </div>
        `;
    } catch (e) {
        document.querySelector('.content-body').innerHTML = `<div class="empty-state"><p>Fehler beim Laden: ${esc(e.message)}</p></div>`;
    }
}

async function doLogout() {
    try { await API.post('auth/logout'); } catch (e) { /* ignore */ }
    AppState.clearUser();
    Router.navigate('login');
    Toast.info('Erfolgreich abgemeldet');
}
