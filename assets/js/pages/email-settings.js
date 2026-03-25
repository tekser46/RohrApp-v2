/**
 * RohrApp+ — Email Settings Page
 */
async function renderEmailSettingsPage(renderId) {
    panelLayout('email-settings', 'Email Einstellungen', '<div class="loading-inline"><div class="loading-spinner"></div></div>');

    try {
        const res = await API.get('email-settings');
        if (renderId && renderId !== Router.renderCount) return;
        const s = res.data || {};

        document.querySelector('.content-body').innerHTML = `
        <div class="card">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                <span class="card-title">IMAP Einstellungen</span>
                ${s.is_verified ? '<span class="badge badge-success">✓ Verbunden</span>' : '<span class="badge badge-muted">Nicht verbunden</span>'}
            </div>
            <div class="card-body">
                <form id="emailSettingsForm">
                    <div class="form-grid-3">
                        <div class="form-group" style="grid-column:span 2"><label class="form-label">E-Mail-Adresse</label><input class="form-input" name="email_address" value="${esc(s.email_address || '')}" placeholder="name@firma.de"></div>
                        <div class="form-group"><label class="form-label">Verschlüsselung</label>
                            <select class="form-select" name="imap_encryption">
                                <option value="ssl" ${s.imap_encryption === 'ssl' ? 'selected' : ''}>SSL</option>
                                <option value="tls" ${s.imap_encryption === 'tls' ? 'selected' : ''}>TLS</option>
                                <option value="none" ${s.imap_encryption === 'none' ? 'selected' : ''}>Keine</option>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">IMAP Server</label><input class="form-input" name="imap_host" value="${esc(s.imap_host || '')}" placeholder="imap.provider.de"></div>
                        <div class="form-group"><label class="form-label">IMAP Port</label><input class="form-input" name="imap_port" type="number" value="${s.imap_port || 993}" placeholder="993"></div>
                        <div class="form-group"><label class="form-label">Benutzername</label><input class="form-input" name="imap_username" value="${esc(s.imap_username || '')}" placeholder="name@firma.de"></div>
                        <div class="form-group" style="grid-column:span 2"><label class="form-label">Passwort ${s.imap_password_set ? '<span style="font-size:11px;color:var(--success)">(gespeichert)</span>' : ''}</label><input class="form-input" name="imap_password" type="password" placeholder="${s.imap_password_set ? '••••••••' : 'IMAP Passwort'}"></div>
                    </div>

                    <h3 class="form-section-title">SMTP Einstellungen (optional)</h3>
                    <div class="form-grid-3">
                        <div class="form-group"><label class="form-label">SMTP Server</label><input class="form-input" name="smtp_host" value="${esc(s.smtp_host || '')}" placeholder="smtp.provider.de"></div>
                        <div class="form-group"><label class="form-label">SMTP Port</label><input class="form-input" name="smtp_port" type="number" value="${s.smtp_port || 587}" placeholder="587"></div>
                        <div class="form-group"><label class="form-label">Verschlüsselung</label>
                            <select class="form-select" name="smtp_encryption">
                                <option value="tls" ${s.smtp_encryption === 'tls' ? 'selected' : ''}>TLS</option>
                                <option value="ssl" ${s.smtp_encryption === 'ssl' ? 'selected' : ''}>SSL</option>
                                <option value="none" ${s.smtp_encryption === 'none' ? 'selected' : ''}>Keine</option>
                            </select>
                        </div>
                    </div>

                    ${s.last_sync_at ? '<div style="margin-top:16px;font-size:12px;color:var(--text-light)">Letzte Synchronisierung: ' + formatDateTime(s.last_sync_at) + '</div>' : ''}

                    <div style="display:flex;gap:10px;margin-top:20px">
                        <button type="submit" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                            Speichern
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="testImapConnection()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            Verbindung testen
                        </button>
                    </div>
                </form>
            </div>
        </div>
        `;

        document.getElementById('emailSettingsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const body = Object.fromEntries(fd);
            // Don't send empty password (keep existing)
            if (!body.imap_password) delete body.imap_password;
            if (!body.smtp_password) delete body.smtp_password;
            try {
                await API.put('email-settings', body);
                Toast.success('Einstellungen gespeichert');
                renderEmailSettingsPage(Router.renderCount);
            } catch (err) { Toast.error(err.message); }
        });

    } catch (e) {
        document.querySelector('.content-body').innerHTML = `<div class="empty-state"><p>Fehler: ${esc(e.message)}</p></div>`;
    }
}

async function testImapConnection() {
    try {
        Toast.info('Verbindung wird getestet...');
        await API.post('email-settings/test');
        Toast.success('IMAP-Verbindung erfolgreich!');
        renderEmailSettingsPage(Router.renderCount);
    } catch (err) {
        Toast.error(err.message);
    }
}
