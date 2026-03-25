/**
 * RohrApp+ — Sipgate Settings Section
 * Renders inside the Settings page
 */

async function renderSipgateSection() {
    const container = document.getElementById('sipgateSection');
    if (!container) return;

    try {
        const res = await API.get('sipgate/settings');
        const s = res.data;
        const numbers = s.numbers || [];

        let numRows = '';
        numbers.forEach(n => {
            const blocked = n.is_blocked ? '<span class="badge badge-danger">Blockiert</span>' : '<span class="badge badge-success">Aktiv</span>';
            numRows += `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border:1px solid var(--border);border-radius:8px;margin-bottom:6px;background:var(--bg)">
                    <div>
                        <span style="font-weight:600;font-family:monospace;font-size:14px">${esc(n.number)}</span>
                        ${n.label ? '<span style="margin-left:8px;font-size:12px;color:var(--text-light)">' + esc(n.label) + '</span>' : ''}
                        ${n.block_name ? '<span style="margin-left:8px;font-size:11px;color:var(--text-muted)">[' + esc(n.block_name) + ']</span>' : ''}
                    </div>
                    <div style="display:flex;gap:6px;align-items:center">
                        ${blocked}
                        <button class="btn btn-sm btn-secondary" onclick="toggleNumberBlock(${n.id})" title="${n.is_blocked ? 'Entsperren' : 'Blockieren'}">${n.is_blocked ? '🔓' : '🚫'}</button>
                        <button class="btn btn-sm btn-secondary" style="color:var(--danger)" onclick="deleteNumber(${n.id})" title="Entfernen">✕</button>
                    </div>
                </div>
            `;
        });

        container.innerHTML = `
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                    <span class="card-title">Sipgate Einstellungen</span>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" id="sipgateEnabled" ${s.is_enabled ? 'checked' : ''} onchange="toggleSipgate(this.checked)">
                        <span style="font-size:13px">${s.is_enabled ? 'Aktiviert' : 'Deaktiviert'}</span>
                    </label>
                </div>
                <div class="card-body">
                    <!-- Webhook URL -->
                    <div style="margin-bottom:20px">
                        <label class="form-label">Webhook URL</label>
                        <div style="display:flex;gap:8px;align-items:center">
                            <input type="text" class="form-input" readonly value="${esc(s.webhook_url)}" id="webhookUrl"
                                style="font-family:monospace;font-size:11px;flex:1;background:var(--bg)">
                            <button class="btn btn-sm btn-secondary" onclick="navigator.clipboard.writeText(document.getElementById('webhookUrl').value);Toast.success('Kopiert!')">Kopieren</button>
                        </div>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:6px">
                            Tragen Sie diese URL in Ihrem Sipgate-Konto unter <strong>Einstellungen → Webhooks</strong> ein<br>
                            (sowohl für eingehende als auch ausgehende Anrufe).<br>
                            Die Zuordnung erfolgt automatisch über Ihre registrierten Telefonnummern.
                        </p>
                    </div>

                    <!-- Numbers -->
                    <div style="margin-bottom:16px">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                            <label class="form-label" style="margin:0">Telefonnummern</label>
                            <button class="btn btn-sm btn-primary" onclick="document.getElementById('addNumberForm').style.display='block'">+ Nummer hinzufügen</button>
                        </div>

                        <div id="addNumberForm" style="display:none;margin-bottom:14px;padding:14px;background:var(--bg);border-radius:8px;border:1px solid var(--border)">
                            <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
                                <div class="form-group" style="margin:0;flex:1;min-width:150px">
                                    <label class="form-label" style="font-size:11px">Nummer</label>
                                    <input type="text" class="form-input" id="newNumNumber" placeholder="+49 641 12345">
                                </div>
                                <div class="form-group" style="margin:0;flex:1;min-width:120px">
                                    <label class="form-label" style="font-size:11px">Bezeichnung</label>
                                    <input type="text" class="form-input" id="newNumLabel" placeholder="Büro">
                                </div>
                                <div class="form-group" style="margin:0;min-width:120px">
                                    <label class="form-label" style="font-size:11px">Block-Name</label>
                                    <input type="text" class="form-input" id="newNumBlock" placeholder="(optional)">
                                </div>
                                <button class="btn btn-primary" onclick="addSipgateNumber()">Hinzufügen</button>
                                <button class="btn btn-secondary" onclick="document.getElementById('addNumberForm').style.display='none'">Abbrechen</button>
                            </div>
                        </div>

                        ${numRows || '<p style="font-size:13px;color:var(--text-light)">Keine Nummern konfiguriert.</p>'}
                    </div>
                </div>
            </div>
        `;
    } catch (err) {
        container.innerHTML = '<p style="color:var(--danger)">' + esc(err.message) + '</p>';
    }
}

async function toggleSipgate(enabled) {
    try {
        await API.put('sipgate/settings', { is_enabled: enabled });
        Toast.success(enabled ? 'Sipgate aktiviert' : 'Sipgate deaktiviert');
    } catch (err) { Toast.error(err.message); }
}

async function addSipgateNumber() {
    const number = document.getElementById('newNumNumber').value.trim();
    const label = document.getElementById('newNumLabel').value.trim();
    const blockName = document.getElementById('newNumBlock').value.trim();
    if (!number) { Toast.error('Nummer ist erforderlich'); return; }

    try {
        await API.post('sipgate/numbers', { number, label, block_name: blockName });
        Toast.success('Nummer hinzugefügt');
        renderSipgateSection();
    } catch (err) { Toast.error(err.message); }
}

async function deleteNumber(id) {
    if (!confirm('Nummer wirklich entfernen?')) return;
    try {
        await API.delete('sipgate/numbers/' + id);
        Toast.success('Nummer entfernt');
        renderSipgateSection();
    } catch (err) { Toast.error(err.message); }
}

async function toggleNumberBlock(id) {
    try {
        await API.post('sipgate/numbers/' + id + '/block');
        Toast.success('Block-Status geändert');
        renderSipgateSection();
    } catch (err) { Toast.error(err.message); }
}
