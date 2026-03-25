/**
 * RohrApp+ — Websites Management (part of Settings)
 * Add/remove websites, get API keys for contact form integration
 */

async function renderWebsites() {
    const container = document.getElementById('websitesSection');
    if (!container) return;

    try {
        const res = await API.get('websites');
        const sites = res.data || [];

        let rows = '';
        sites.forEach(s => {
            rows += `
                <div class="website-card" style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border:1px solid var(--border);border-radius:8px;margin-bottom:8px;background:var(--bg)">
                    <div>
                        <div style="font-weight:600;font-size:14px">${esc(s.name || s.domain)}</div>
                        <div style="font-size:12px;color:var(--text-light)">${esc(s.domain)}</div>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center">
                        <div style="position:relative">
                            <input type="text" readonly class="form-input" value="${esc(s.api_key)}" style="font-size:11px;width:200px;height:30px;font-family:monospace;background:var(--card-bg)">
                            <button class="btn btn-sm btn-secondary" style="position:absolute;right:2px;top:2px;height:26px;font-size:10px" onclick="copyKey('${esc(s.api_key)}')">Kopieren</button>
                        </div>
                        <span class="badge ${s.is_active ? 'badge-success' : 'badge-secondary'}">${s.is_active ? 'Aktiv' : 'Inaktiv'}</span>
                        <button class="btn btn-sm btn-secondary" style="color:var(--danger)" onclick="deleteWebsite(${s.id})">✕</button>
                    </div>
                </div>
            `;
        });

        container.innerHTML = `
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                    <span class="card-title">Webseiten</span>
                    <button class="btn btn-sm btn-primary" onclick="showAddWebsite()">+ Website hinzufügen</button>
                </div>
                <div class="card-body">
                    ${rows || '<p style="color:var(--text-light);font-size:13px">Keine Webseiten konfiguriert. Fügen Sie eine Website hinzu, um Kontaktformular-Nachrichten zu empfangen.</p>'}
                    <div id="addWebsiteForm" style="display:none;margin-top:16px;padding:16px;background:var(--bg);border-radius:8px;border:1px solid var(--border)">
                        <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
                            <div class="form-group" style="margin:0;flex:1;min-width:200px">
                                <label class="form-label">Domain</label>
                                <input type="text" class="form-input" id="newWebDomain" placeholder="example.com">
                            </div>
                            <div class="form-group" style="margin:0;flex:1;min-width:200px">
                                <label class="form-label">Name (optional)</label>
                                <input type="text" class="form-input" id="newWebName" placeholder="Meine Webseite">
                            </div>
                            <button class="btn btn-primary" onclick="addWebsite()">Hinzufügen</button>
                            <button class="btn btn-secondary" onclick="document.getElementById('addWebsiteForm').style.display='none'">Abbrechen</button>
                        </div>
                    </div>
                    <div style="margin-top:16px;padding:14px;background:#f0f7ff;border-radius:8px;font-size:12px;color:var(--text-light)">
                        <strong>Integration:</strong> Verwenden Sie den API-Schlüssel, um Kontaktformulare mit RohrApp+ zu verbinden.<br>
                        <code style="display:block;margin-top:8px;padding:8px;background:var(--card-bg);border-radius:4px;font-size:11px">POST ${window.location.origin}/api/webhook/contact?token=IHR_API_KEY<br>Body: { "name": "...", "email": "...", "phone": "...", "subject": "...", "message": "..." }</code>
                    </div>
                </div>
            </div>
        `;
    } catch (err) {
        container.innerHTML = '<p style="color:var(--danger)">' + esc(err.message) + '</p>';
    }
}

function showAddWebsite() {
    document.getElementById('addWebsiteForm').style.display = 'block';
    document.getElementById('newWebDomain').focus();
}

async function addWebsite() {
    const domain = document.getElementById('newWebDomain').value.trim();
    const name = document.getElementById('newWebName').value.trim();
    if (!domain) { Toast.error('Domain ist erforderlich'); return; }

    try {
        await API.post('websites', { domain, name });
        Toast.success('Website hinzugefügt');
        renderWebsites();
    } catch (err) { Toast.error(err.message); }
}

async function deleteWebsite(id) {
    if (!confirm('Website wirklich entfernen?')) return;
    try {
        await API.delete('websites/' + id);
        Toast.success('Website entfernt');
        renderWebsites();
    } catch (err) { Toast.error(err.message); }
}

function copyKey(key) {
    navigator.clipboard.writeText(key).then(() => Toast.success('API-Schlüssel kopiert'));
}
