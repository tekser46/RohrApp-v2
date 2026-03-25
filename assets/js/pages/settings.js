/**
 * RohrApp+ — Settings Page (Profile, Company, Billing, Password)
 */
async function renderSettingsPage(renderId) {
    panelLayout('settings', 'Einstellungen', '<div class="loading-inline"><div class="loading-spinner"></div></div>');

    try {
        const res = await API.get('user/profile');
        if (renderId && renderId !== Router.renderCount) return;
        const p = res.data.profile || {};

        document.querySelector('.content-body').innerHTML = `
        <!-- Avatar & Logo -->
        <div class="card">
            <div class="card-header"><span class="card-title">Profilbilder</span></div>
            <div class="card-body">
                <div style="display:flex;gap:40px;flex-wrap:wrap">
                    <div style="text-align:center">
                        <div class="form-label" style="margin-bottom:8px">Profilbild</div>
                        <div class="upload-preview" onclick="uploadFile('avatar')">
                            ${p.avatar_path
                                ? '<img id="avatarPreview" src="' + esc(p.avatar_path) + '" class="upload-img">'
                                : '<div id="avatarPreview" class="upload-placeholder">' + esc(AppState.initials()) + '</div>'}
                            <div class="upload-overlay">📷</div>
                        </div>
                    </div>
                    <div style="text-align:center">
                        <div class="form-label" style="margin-bottom:8px">Firmenlogo</div>
                        <div class="upload-preview" onclick="uploadFile('logo')">
                            ${p.company_logo_path
                                ? '<img id="logoPreview" src="' + esc(p.company_logo_path) + '" class="upload-img">'
                                : '<div id="logoPreview" class="upload-placeholder-logo">Logo</div>'}
                            <div class="upload-overlay">📷</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Form -->
        <div class="card" style="margin-top:20px">
            <div class="card-header"><span class="card-title">Profil & Firma</span></div>
            <div class="card-body">
                <form id="profileForm">
                    <div class="form-grid-3">
                        <div class="form-group"><label class="form-label">Vorname</label><input class="form-input" name="first_name" value="${esc(p.first_name || '')}"></div>
                        <div class="form-group"><label class="form-label">Nachname</label><input class="form-input" name="last_name" value="${esc(p.last_name || '')}"></div>
                        <div class="form-group"><label class="form-label">Firmenname</label><input class="form-input" name="company_name" value="${esc(p.company_name || '')}"></div>
                        <div class="form-group"><label class="form-label">Telefon</label><input class="form-input" name="phone" value="${esc(p.phone || '')}" placeholder="+49 ..."></div>
                        <div class="form-group"><label class="form-label">E-Mail</label><input class="form-input" name="email" type="email" value="${esc(res.data.email || '')}"></div>
                    </div>

                    <h3 class="form-section-title">Adresse</h3>
                    <div class="form-grid-3">
                        <div class="form-group" style="grid-column:span 2"><label class="form-label">Straße & Hausnummer</label><input class="form-input" name="address_street" value="${esc(p.address_street || '')}"></div>
                        <div class="form-group"><label class="form-label">PLZ</label><input class="form-input" name="address_zip" value="${esc(p.address_zip || '')}"></div>
                        <div class="form-group"><label class="form-label">Stadt</label><input class="form-input" name="address_city" value="${esc(p.address_city || '')}"></div>
                        <div class="form-group"><label class="form-label">Land</label><input class="form-input" name="address_country" value="${esc(p.address_country || 'Deutschland')}"></div>
                    </div>

                    <h3 class="form-section-title">Rechnungsadresse</h3>
                    <div class="form-grid-3">
                        <div class="form-group" style="grid-column:span 2"><label class="form-label">Straße & Hausnummer</label><input class="form-input" name="billing_street" value="${esc(p.billing_street || '')}"></div>
                        <div class="form-group"><label class="form-label">PLZ</label><input class="form-input" name="billing_zip" value="${esc(p.billing_zip || '')}"></div>
                        <div class="form-group"><label class="form-label">Stadt</label><input class="form-input" name="billing_city" value="${esc(p.billing_city || '')}"></div>
                        <div class="form-group"><label class="form-label">Land</label><input class="form-input" name="billing_country" value="${esc(p.billing_country || '')}"></div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top:16px">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Speichern
                    </button>
                </form>
            </div>
        </div>

        <!-- Password -->
        <div class="card" style="margin-top:20px">
            <div class="card-header"><span class="card-title">Passwort ändern</span></div>
            <div class="card-body">
                <form id="passwordForm">
                    <div class="form-grid-3">
                        <div class="form-group"><label class="form-label">Aktuelles Passwort</label><input class="form-input" type="password" name="current_password" required></div>
                        <div class="form-group"><label class="form-label">Neues Passwort</label><input class="form-input" type="password" name="new_password" required minlength="8"></div>
                        <div class="form-group" style="align-self:end"><button type="submit" class="btn btn-primary">Passwort ändern</button></div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sipgate -->
        <div id="sipgateSection"><div class="card"><div class="card-body"><div class="loading-inline"><div class="loading-spinner"></div></div></div></div></div>

        <!-- Websites -->
        <div id="websitesSection"><div class="card"><div class="card-body"><div class="loading-inline"><div class="loading-spinner"></div></div></div></div></div>

        ${AppState.user?.role === 'admin' ? `
        <!-- System Update (Admin only) -->
        <div class="card">
            <div class="card-header"><span class="card-title">🔄 System-Update</span></div>
            <div class="card-body" id="updateSection">
                <div class="loading-inline"><div class="loading-spinner"></div></div>
            </div>
        </div>` : ''}
        `;

        // Load sipgate & website sections
        if (typeof renderSipgateSection === 'function') renderSipgateSection();
        if (typeof renderWebsites === 'function') renderWebsites();

        // Load update check (admin only)
        if (AppState.user?.role === 'admin') checkForUpdate();

        // Profile form handler
        document.getElementById('profileForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const body = Object.fromEntries(fd);
            try {
                await API.put('user/profile', body);
                Toast.success('Profil gespeichert');
                // Update state
                if (body.first_name || body.last_name) {
                    AppState.user.first_name = body.first_name;
                    AppState.user.last_name = body.last_name;
                }
            } catch (err) { Toast.error(err.message); }
        });

        // Password form handler
        document.getElementById('passwordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            try {
                await API.put('user/password', Object.fromEntries(fd));
                Toast.success('Passwort geändert');
                e.target.reset();
            } catch (err) { Toast.error(err.message); }
        });

    } catch (e) {
        document.querySelector('.content-body').innerHTML = `<div class="empty-state"><p>Fehler: ${esc(e.message)}</p></div>`;
    }
}

/**
 * File upload helper
 */
function uploadFile(type) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async () => {
        if (!input.files[0]) return;
        const fd = new FormData();
        fd.append('file', input.files[0]);
        const endpoint = type === 'avatar' ? 'user/avatar' : 'user/logo';
        try {
            const res = await fetch(API.baseUrl + '/' + endpoint, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: API.csrfToken ? {'X-CSRF-Token': API.csrfToken} : {},
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error?.message || 'Upload fehlgeschlagen');
            Toast.success(type === 'avatar' ? 'Profilbild aktualisiert' : 'Logo aktualisiert');
            // Refresh page to show new image
            renderSettingsPage();
        } catch (err) { Toast.error(err.message); }
    };
    input.click();
}

/**
 * System Update — check GitHub for new version
 */
async function checkForUpdate() {
    const section = document.getElementById('updateSection');
    if (!section) return;

    try {
        const res = await API.get('system/check-update');
        const d = res.data;

        if (d.update_available) {
            section.innerHTML = `
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
                    <div style="width:48px;height:48px;border-radius:12px;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </div>
                    <div>
                        <div style="font-size:15px;font-weight:700;color:var(--success)">Update verfügbar!</div>
                        <div style="font-size:13px;color:var(--text-light)">
                            Version <strong>${esc(d.local)}</strong> → <strong>${esc(d.remote)}</strong>
                            ${d.build ? ' (' + esc(d.build) + ')' : ''}
                        </div>
                        ${d.changelog ? '<div style="font-size:12px;color:var(--text-muted);margin-top:4px">' + esc(d.changelog) + '</div>' : ''}
                    </div>
                </div>
                <button class="btn btn-primary" id="doUpdateBtn" onclick="doSystemUpdate()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Jetzt aktualisieren
                </button>
            `;
        } else {
            section.innerHTML = `
                <div style="display:flex;align-items:center;gap:16px">
                    <div style="width:48px;height:48px;border-radius:12px;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div>
                        <div style="font-size:15px;font-weight:700">System ist aktuell</div>
                        <div style="font-size:13px;color:var(--text-light)">Version <strong>${esc(d.local)}</strong></div>
                    </div>
                </div>
                <button class="btn btn-secondary btn-sm" style="margin-top:14px" onclick="checkForUpdate()">Erneut prüfen</button>
            `;
        }
    } catch (err) {
        section.innerHTML = `
            <div style="color:var(--danger);font-size:13px">Update-Check fehlgeschlagen: ${esc(err.message)}</div>
            <button class="btn btn-secondary btn-sm" style="margin-top:10px" onclick="checkForUpdate()">Erneut prüfen</button>
        `;
    }
}

async function doSystemUpdate() {
    const btn = document.getElementById('doUpdateBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<div class="loading-spinner" style="width:14px;height:14px;margin:0;display:inline-block"></div> Wird aktualisiert...';
    }

    try {
        const res = await API.post('system/do-update');
        const d = res.data;
        const section = document.getElementById('updateSection');
        if (section) {
            section.innerHTML = `
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
                    <div style="width:48px;height:48px;border-radius:12px;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div>
                        <div style="font-size:15px;font-weight:700;color:var(--success)">Update erfolgreich!</div>
                        <div style="font-size:13px;color:var(--text-light)">Version <strong>${esc(d.version)}</strong> — ${d.files_updated} Dateien aktualisiert</div>
                    </div>
                </div>
                <button class="btn btn-primary btn-sm" onclick="location.reload()">Seite neu laden</button>
            `;
        }
        Toast.success('Update auf v' + d.version + ' erfolgreich!');
    } catch (err) {
        Toast.error('Update fehlgeschlagen: ' + err.message);
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = 'Erneut versuchen';
        }
    }
}
