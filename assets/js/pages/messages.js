/**
 * RohrApp+ — Nachrichten Page
 * Website contact form messages: list, detail, read/unread/archive/delete
 */

let _msgStatus = '';
let _msgSearch = '';
let _msgSort = 'date_desc';

async function renderMessages(renderId) {
    panelLayout('messages', 'Nachrichten', '<div class="loading-inline"><div class="loading-spinner"></div></div>');
    const content = document.querySelector('.content-body');
    if (!content) return;

    const queryParams = { per_page: 30 };
    if (_msgStatus) queryParams.status = _msgStatus;
    if (_msgSearch) queryParams.search = _msgSearch;
    if (_msgSort) queryParams.sort = _msgSort;

    try {
        const res = await API.get('messages', queryParams);
        if (renderId && renderId !== Router.renderCount) return;

        const msgs = res.data || [];
        const meta = res.meta || {};

        let rows = '';
        msgs.forEach(m => {
            const unread = m.status === 'unread' ? 'font-weight:700;' : '';
            const archived = m.status === 'archived' ? 'opacity:0.5;' : '';
            const statusIcon = m.status === 'unread'
                ? '<span class="msg-status-dot msg-unread" title="Ungelesen"></span>'
                : m.status === 'archived'
                    ? '<span class="msg-status-dot msg-archived" title="Archiviert">&#128451;</span>'
                    : '<span class="msg-status-dot msg-read" title="Gelesen"></span>';

            rows += `<tr style="cursor:pointer;${unread}${archived}" data-msg-id="${m.id}">
                <td style="width:30px;text-align:center" onclick="event.stopPropagation()">
                    <input type="checkbox" class="msg-checkbox" value="${m.id}" onchange="updateMsgBulk()">
                </td>
                <td style="width:24px;text-align:center">${statusIcon}</td>
                <td style="${unread}" onclick="viewMessage(${m.id})">${esc(m.sender_name || 'Unbekannt')}</td>
                <td style="${unread}" onclick="viewMessage(${m.id})">${esc(m.sender_email || '-')}</td>
                <td style="${unread}" onclick="viewMessage(${m.id})">${esc(m.subject || '(Kein Betreff)')}</td>
                <td style="font-size:12px;color:var(--text-light);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" onclick="viewMessage(${m.id})">${esc((m.message || '').substring(0, 100))}</td>
                <td style="font-size:12px;color:var(--text-light);white-space:nowrap" onclick="viewMessage(${m.id})">${formatDateTime(m.created_at)}</td>
                <td style="font-size:11px;color:var(--text-muted)" onclick="viewMessage(${m.id})">${esc(m.domain || '')}</td>
            </tr>`;
        });

        content.innerHTML = `
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                    <div style="display:flex;align-items:center;gap:12px">
                        <span class="card-title">Nachrichten</span>
                        <span class="badge badge-info">${meta.total || 0} Nachrichten</span>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <div style="position:relative">
                            <input type="text" class="form-input" id="msgSearch" placeholder="Suchen..." value="${esc(_msgSearch || '')}"
                                style="width:200px;height:32px;font-size:12px;padding-left:30px"
                                onkeydown="if(event.key==='Enter'){_msgSearch=this.value;renderMessages(Router.renderCount)}">
                            <svg style="position:absolute;left:8px;top:8px;pointer-events:none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text-light)" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </div>
                        <select class="form-select" style="width:auto;height:32px;font-size:12px;padding:0 8px" onchange="_msgSort=this.value;renderMessages(Router.renderCount)">
                            <option value="date_desc" ${_msgSort==='date_desc'?'selected':''}>Neueste zuerst</option>
                            <option value="date_asc" ${_msgSort==='date_asc'?'selected':''}>Älteste zuerst</option>
                        </select>
                        <button class="btn btn-sm btn-secondary ${_msgStatus === 'unread' ? 'btn-filter-active' : ''}" onclick="_msgStatus='unread';renderMessages(Router.renderCount)">Ungelesen</button>
                        <button class="btn btn-sm btn-secondary ${_msgStatus === 'archived' ? 'btn-filter-active' : ''}" onclick="_msgStatus='archived';renderMessages(Router.renderCount)">Archiviert</button>
                        <button class="btn btn-sm btn-secondary ${!_msgStatus ? 'btn-filter-active' : ''}" onclick="_msgStatus='';renderMessages(Router.renderCount)">Alle</button>
                    </div>
                </div>
                <div class="card-body" style="padding:0">
                    <div id="msgBulkActions" style="display:none;padding:8px 16px;background:var(--primary-light);border-bottom:1px solid var(--border);align-items:center;gap:12px">
                        <span id="msgBulkCount" style="font-size:13px;font-weight:600"></span>
                        <button class="btn btn-sm btn-secondary" style="color:var(--danger)" onclick="bulkDeleteMsgs()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            Löschen
                        </button>
                    </div>
                    ${rows
                        ? '<div style="overflow-x:auto"><table class="data-table"><thead><tr><th style="width:30px"><input type="checkbox" id="selectAllMsgs" onchange="toggleSelectAllMsgs(this)"></th><th style="width:24px"></th><th>Name</th><th>E-Mail</th><th>Betreff</th><th>Nachricht</th><th>Datum</th><th>Website</th></tr></thead><tbody>' + rows + '</tbody></table></div>'
                        : '<div class="empty-state" style="padding:60px 20px"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--border)" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><p style="margin-top:16px">Keine Nachrichten vorhanden</p><p style="font-size:12px;color:var(--text-muted);margin-top:8px">Nachrichten werden über Ihre Website-Formulare empfangen.</p></div>'}
                </div>
            </div>
            <div id="msgDetail"></div>
        `;
    } catch (err) {
        content.innerHTML = '<div class="card"><div class="card-body"><p style="color:var(--danger)">' + esc(err.message) + '</p></div></div>';
    }
}

/** View message detail */
async function viewMessage(id) {
    const detail = document.getElementById('msgDetail');
    if (!detail) return;

    detail.style.display = 'block';
    detail.innerHTML = '<div class="card" style="margin-top:20px"><div class="card-body"><div class="loading-inline"><div class="loading-spinner"></div></div></div></div>';

    try {
        const res = await API.get('messages/' + id);
        const m = res.data;

        // Update row to read state
        const row = document.querySelector('tr[data-msg-id="' + id + '"]');
        if (row) row.style.fontWeight = 'normal';

        detail.innerHTML = `
            <div class="card" style="margin-top:20px">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                    <div style="display:flex;align-items:center;gap:12px">
                        <span class="card-title" style="font-size:16px">${esc(m.subject || '(Kein Betreff)')}</span>
                        <span class="badge ${m.status === 'unread' ? 'badge-warning' : m.status === 'archived' ? 'badge-secondary' : 'badge-info'}">${m.status === 'unread' ? 'Ungelesen' : m.status === 'archived' ? 'Archiviert' : 'Gelesen'}</span>
                    </div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        ${m.status !== 'unread' ? `<button class="btn btn-sm btn-secondary" onclick="markMsgUnread(${m.id})">Als ungelesen</button>` : ''}
                        ${m.status !== 'archived' ? `<button class="btn btn-sm btn-secondary" onclick="archiveMsg(${m.id})">Archivieren</button>` : ''}
                        <button class="btn btn-sm btn-secondary" style="color:var(--danger)" onclick="deleteMsg(${m.id})">Löschen</button>
                        <button class="btn btn-sm btn-secondary" onclick="document.getElementById('msgDetail').style.display='none'">✕</button>
                    </div>
                </div>
                <div class="card-body">
                    <div style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:16px;font-size:13px;color:var(--text-light)">
                        <div><strong>Von:</strong> ${esc(m.sender_name || 'Unbekannt')} ${m.sender_email ? '&lt;' + esc(m.sender_email) + '&gt;' : ''}</div>
                        ${m.sender_phone ? '<div><strong>Tel:</strong> ' + esc(m.sender_phone) + '</div>' : ''}
                        <div><strong>Datum:</strong> ${formatDateTime(m.created_at)}</div>
                        ${m.domain ? '<div><strong>Website:</strong> ' + esc(m.domain) + '</div>' : ''}
                        ${m.ip_address ? '<div><strong>IP:</strong> ' + esc(m.ip_address) + '</div>' : ''}
                    </div>
                    <div style="background:var(--bg);border-radius:8px;padding:16px;line-height:1.7;white-space:pre-wrap;font-size:14px">${esc(m.message || 'Keine Nachricht')}</div>
                    ${m.sender_email ? `<div style="margin-top:16px;display:flex;gap:8px">
                        <button class="btn btn-sm btn-primary" onclick="showMessageReply(${m.id}, '${esc(m.sender_email).replace(/'/g, "\\'")}', '${esc(m.subject || '').replace(/'/g, "\\'")}', '${esc(m.sender_name || '').replace(/'/g, "\\'")}')">↩ Antworten</button>
                    </div>
                    <div id="msgReplyForm-${m.id}" style="display:none;margin-top:16px"></div>` : ''}
                </div>
            </div>
        `;
    } catch (err) {
        detail.innerHTML = '<div class="card" style="margin-top:20px"><div class="card-body"><p style="color:var(--danger)">' + esc(err.message) + '</p></div></div>';
    }
}

/** Show inline reply form */
function showMessageReply(msgId, toEmail, subject, senderName) {
    const container = document.getElementById('msgReplyForm-' + msgId);
    if (!container) return;

    if (container.style.display !== 'none') {
        container.style.display = 'none';
        return;
    }

    const reSubject = subject.startsWith('Re:') ? subject : 'Re: ' + subject;

    container.style.display = 'block';
    container.innerHTML = `
        <div class="card" style="border:1px solid var(--primary);border-radius:12px">
            <div class="card-header" style="background:var(--primary-light);border-radius:12px 12px 0 0">
                <span class="card-title" style="font-size:14px">↩ Antwort an ${esc(senderName)} &lt;${esc(toEmail)}&gt;</span>
            </div>
            <div class="card-body">
                <div class="form-group" style="margin-bottom:12px">
                    <label class="form-label" style="font-size:12px">Betreff</label>
                    <input class="form-input" id="msgReplySubject-${msgId}" value="${esc(reSubject)}">
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label class="form-label" style="font-size:12px">Nachricht</label>
                    <div id="msgReplyEditor-${msgId}" contenteditable="true" style="min-height:150px;border:1px solid var(--border);border-radius:8px;padding:12px;font-size:14px;line-height:1.6;background:#fff;outline:none"></div>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <button class="btn btn-primary" onclick="sendMessageReply(${msgId}, '${toEmail.replace(/'/g, "\\'")}')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        Senden
                    </button>
                    <button class="btn btn-secondary" onclick="document.getElementById('msgReplyForm-${msgId}').style.display='none'">Abbrechen</button>
                </div>
            </div>
        </div>
    `;

    // Focus editor
    setTimeout(() => {
        const editor = document.getElementById('msgReplyEditor-' + msgId);
        if (editor) editor.focus();
    }, 100);
}

/** Send reply via SMTP */
async function sendMessageReply(msgId, toEmail) {
    const subject = document.getElementById('msgReplySubject-' + msgId)?.value || '';
    const editor = document.getElementById('msgReplyEditor-' + msgId);
    const body = editor ? editor.innerHTML : '';

    if (!body.trim()) {
        Toast.error('Nachricht darf nicht leer sein');
        return;
    }

    const btn = event.target.closest('button');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<div class="loading-spinner" style="width:14px;height:14px;margin:0"></div> Senden...';
    }

    try {
        await API.post('emails/reply', {
            to: toEmail,
            subject: subject,
            body: body,
        });
        Toast.success('Antwort gesendet!');
        const container = document.getElementById('msgReplyForm-' + msgId);
        if (container) {
            container.innerHTML = '<div style="padding:12px;background:rgba(16,185,129,0.1);border-radius:8px;color:var(--success);font-size:13px">✓ Antwort erfolgreich gesendet</div>';
        }
    } catch (err) {
        Toast.error('Fehler: ' + err.message);
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = 'Senden';
        }
    }
}

/** Mark message as unread */
async function markMsgUnread(id) {
    try {
        await API.put('messages/' + id + '/unread');
        Toast.success('Als ungelesen markiert');
        renderMessages(Router.renderCount);
    } catch (err) { Toast.error(err.message); }
}

/** Archive message */
async function archiveMsg(id) {
    try {
        await API.put('messages/' + id + '/archive');
        Toast.success('Archiviert');
        renderMessages(Router.renderCount);
    } catch (err) { Toast.error(err.message); }
}

/** Delete message */
async function deleteMsg(id) {
    if (!confirm('Nachricht wirklich löschen?')) return;
    try {
        await API.delete('messages/' + id);
        Toast.success('Gelöscht');
        renderMessages(Router.renderCount);
    } catch (err) { Toast.error(err.message); }
}

/** Select all / deselect all */
function toggleSelectAllMsgs(masterCheckbox) {
    document.querySelectorAll('.msg-checkbox').forEach(cb => cb.checked = masterCheckbox.checked);
    updateMsgBulk();
}

/** Update bulk actions visibility */
function updateMsgBulk() {
    const checked = document.querySelectorAll('.msg-checkbox:checked');
    const bar = document.getElementById('msgBulkActions');
    const count = document.getElementById('msgBulkCount');
    if (bar) bar.style.display = checked.length > 0 ? 'flex' : 'none';
    if (count) count.textContent = checked.length + ' ausgewählt';
}

/** Bulk delete */
async function bulkDeleteMsgs() {
    const checked = document.querySelectorAll('.msg-checkbox:checked');
    if (checked.length === 0) return;
    if (!confirm(checked.length + ' Nachricht(en) wirklich löschen?')) return;

    const ids = Array.from(checked).map(cb => parseInt(cb.value));
    try {
        const res = await API.post('messages/bulk-delete', { ids: ids });
        Toast.success(res.message);
        renderMessages(Router.renderCount);
    } catch (err) { Toast.error(err.message); }
}
