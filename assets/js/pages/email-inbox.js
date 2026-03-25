/**
 * RohrApp+ — Email Inbox Page
 * Features: IMAP folder tabs, star/color marking, file attachments, Wichtig tab
 */

/** Current state */
let _emailCurrentFolder = 'INBOX';
let _emailCurrentFilter = '';
let _emailFolders = [];
let _emailAttachments = []; // Files selected for upload
let _emailSearch = '';
let _emailSort = 'date_desc';

/** German labels for known IMAP folders */
const FOLDER_LABELS = {
    'INBOX':        'Posteingang',
    'Sent':         'Gesendet',
    'INBOX.Sent':   'Gesendet',
    'Drafts':       'Entwürfe',
    'INBOX.Drafts': 'Entwürfe',
    'Trash':        'Papierkorb',
    'INBOX.Trash':  'Papierkorb',
    'Spam':         'Spam',
    'Junk':         'Spam',
    'INBOX.Spam':   'Spam',
    'INBOX.Junk':   'Spam',
};

/** Get display label for a folder */
function folderLabel(name) {
    return FOLDER_LABELS[name] || name;
}

/** Color hex values */
const COLOR_MAP = {
    red:    '#dc2626',
    yellow: '#d97706',
    green:  '#059669',
    blue:   '#2563eb',
};

/** Main render */
async function renderEmailInboxPage(renderId) {
    panelLayout('emails', 'Email Inbox', '<div class="loading-inline"><div class="loading-spinner"></div></div>');

    // Load folders in parallel with emails
    try {
        let foldersPromise;
        if (_emailFolders.length === 0) {
            foldersPromise = API.get('emails/folders').then(r => {
                _emailFolders = r.data || [];
            }).catch(() => {
                _emailFolders = ['INBOX'];
            });
        } else {
            foldersPromise = Promise.resolve();
        }

        await foldersPromise;
        if (renderId && renderId !== Router.renderCount) return;

        await renderEmailList(renderId);
    } catch (e) {
        document.querySelector('.content-body').innerHTML = `<div class="empty-state"><p>Fehler: ${esc(e.message)}</p></div>`;
    }
}

/** Render folder tabs + email list */
async function renderEmailList(renderId) {
    const isStarred = _emailCurrentFolder === '__starred__';
    const queryParams = { per_page: 30 };

    if (isStarred) {
        queryParams.filter = 'starred';
    } else {
        if (_emailCurrentFilter) queryParams.filter = _emailCurrentFilter;
        if (_emailCurrentFolder) queryParams.folder = _emailCurrentFolder;
    }

    if (_emailSearch) queryParams.search = _emailSearch;
    if (_emailSort) queryParams.sort = _emailSort;

    const res = await API.get('emails', queryParams);
    if (renderId && renderId !== Router.renderCount) return;

    const emails = res.data || [];
    const meta = res.meta || {};

    // Build folder tabs
    let tabsHtml = '<div class="email-folder-tabs">';
    // Standard folders first
    const standardOrder = ['INBOX'];
    const restFolders = _emailFolders.filter(f => f !== 'INBOX');
    const allFolders = [...standardOrder.filter(f => _emailFolders.includes(f)), ...restFolders];

    allFolders.forEach(f => {
        const active = (!isStarred && _emailCurrentFolder === f) ? 'active' : '';
        tabsHtml += `<button class="email-folder-tab ${active}" onclick="switchFolder('${esc(f)}')">${esc(folderLabel(f))}</button>`;
    });

    // Wichtig (starred) tab
    const starredActive = isStarred ? 'active' : '';
    tabsHtml += `<button class="email-folder-tab email-folder-tab-wichtig ${starredActive}" onclick="switchFolder('__starred__')">&#9733; Wichtig</button>`;
    tabsHtml += '</div>';

    // Build email rows
    let rows = '';
    emails.forEach(e => {
        const isUnread = !e.is_read;
        const unreadStyle = isUnread ? 'font-weight:700;' : 'font-weight:400;';
        const rowBg = isUnread ? 'background:#f0f7ff;' : '';
        const starClass = e.is_starred ? 'email-star active' : 'email-star';
        const attach = e.has_attachments ? '<span class="email-attach-icon" title="Anhang">&#128206;</span> ' : '';
        const readIcon = isUnread
            ? '<span class="email-read-toggle unread" title="Als gelesen markieren">&#9679;</span>'
            : '<span class="email-read-toggle read" title="Als ungelesen markieren">&#9675;</span>';

        rows += `<tr style="cursor:pointer;${rowBg}" data-email-id="${e.id}" data-is-read="${e.is_read}">
            <td style="width:30px;text-align:center" onclick="event.stopPropagation()">
                <input type="checkbox" class="email-checkbox" value="${e.id}" onchange="updateBulkActions()">
            </td>
            <td style="width:24px;text-align:center" onclick="event.stopPropagation();toggleReadStatus(${e.id}, ${e.is_read}, this)">
                ${readIcon}
            </td>
            <td style="width:30px;text-align:center" onclick="event.stopPropagation();toggleStar(${e.id}, this)">
                <span class="${starClass}" title="Wichtig markieren">&#9733;</span>
            </td>
            <td style="width:16px;text-align:center;position:relative" onclick="event.stopPropagation();toggleInlineColorPicker(${e.id}, this)">
                <span class="email-color-dot${e.color ? '' : ' email-color-dot-empty'}" style="${e.color ? 'background:' + (COLOR_MAP[e.color] || 'transparent') : ''}" title="Farbe zuweisen"></span>
                <div class="inline-color-picker" id="icp-${e.id}" style="display:none">
                    <button onclick="event.stopPropagation();pickColor(${e.id},'red')" style="background:#dc2626"></button>
                    <button onclick="event.stopPropagation();pickColor(${e.id},'yellow')" style="background:#d97706"></button>
                    <button onclick="event.stopPropagation();pickColor(${e.id},'green')" style="background:#059669"></button>
                    <button onclick="event.stopPropagation();pickColor(${e.id},'blue')" style="background:#2563eb"></button>
                    <button onclick="event.stopPropagation();pickColor(${e.id},null)" class="icp-clear">&times;</button>
                </div>
            </td>
            <td style="${unreadStyle}" onclick="viewEmail(${e.id})">${esc(e.from_name || e.from_address)}</td>
            <td style="${unreadStyle}" onclick="viewEmail(${e.id})">${attach}${esc(e.subject || '(Kein Betreff)')}</td>
            <td style="font-size:12px;color:var(--text-light);max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" onclick="viewEmail(${e.id})">${esc(e.body_preview || '')}</td>
            <td style="font-size:12px;color:var(--text-light);white-space:nowrap" onclick="viewEmail(${e.id})">${formatDateTime(e.mail_date)}</td>
        </tr>`;
    });

    const title = isStarred ? 'Wichtig' : folderLabel(_emailCurrentFolder);

    document.querySelector('.content-body').innerHTML = `
        ${tabsHtml}
        <div class="card">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                <div style="display:flex;align-items:center;gap:12px">
                    <span class="card-title">${esc(title)}</span>
                    <span class="badge badge-info">${meta.total || 0} E-Mails</span>
                </div>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <div style="position:relative">
                        <input type="text" class="form-input" id="emailSearch" placeholder="Suchen..." value="${esc(_emailSearch || '')}"
                            style="width:200px;height:32px;font-size:12px;padding-left:30px"
                            onkeydown="if(event.key==='Enter'){_emailSearch=this.value;renderEmailList(Router.renderCount)}">
                        <svg style="position:absolute;left:8px;top:8px;pointer-events:none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text-light)" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </div>
                    <select class="form-select" style="width:auto;height:32px;font-size:12px;padding:0 8px" onchange="_emailSort=this.value;renderEmailList(Router.renderCount)">
                        <option value="date_desc" ${_emailSort==='date_desc'?'selected':''}>Neueste zuerst</option>
                        <option value="date_asc" ${_emailSort==='date_asc'?'selected':''}>Älteste zuerst</option>
                    </select>
                    <button class="btn btn-sm btn-secondary ${_emailCurrentFilter === 'unread' ? 'btn-filter-active' : ''}" onclick="filterEmails('unread')">Ungelesen</button>
                    <button class="btn btn-sm btn-secondary ${!_emailCurrentFilter ? 'btn-filter-active' : ''}" onclick="filterEmails('')">Alle</button>
                    <button class="btn btn-sm btn-primary" onclick="refreshEmails()" id="refreshBtn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        Aktualisieren
                    </button>
                </div>
            </div>
            <div class="card-body" style="padding:0">
                <div id="bulkActions" style="display:none;padding:8px 16px;background:var(--primary-light);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px">
                    <span id="bulkCount" style="font-size:13px;font-weight:600"></span>
                    <button class="btn btn-sm btn-secondary" style="color:var(--danger)" onclick="bulkDelete()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        Löschen
                    </button>
                </div>
                ${rows
                    ? '<div style="overflow-x:auto"><table class="data-table email-table"><thead><tr><th style="width:30px"><input type="checkbox" id="selectAllEmails" onchange="toggleSelectAll(this)"></th><th style="width:24px"></th><th style="width:30px"></th><th style="width:16px"></th><th>Von</th><th>Betreff</th><th>Vorschau</th><th>Datum</th></tr></thead><tbody>' + rows + '</tbody></table></div>'
                    : '<div class="empty-state" style="padding:60px 20px"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--border)" stroke-width="1.5"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89"/></svg><p style="margin-top:16px">Keine E-Mails vorhanden</p><p style="font-size:12px;color:var(--text-muted);margin-top:8px">Klicken Sie auf "Aktualisieren", um E-Mails abzurufen.</p></div>'}
            </div>
        </div>

        <!-- Email detail panel -->
        <div id="emailDetail" style="display:none"></div>

    `;
}

/** Switch folder tab */
function switchFolder(folder) {
    _emailCurrentFolder = folder;
    _emailCurrentFilter = '';
    renderEmailInboxPage(Router.renderCount);
}

/** Filter emails */
function filterEmails(filter) {
    _emailCurrentFilter = filter;
    renderEmailInboxPage(Router.renderCount);
}

/** Refresh emails from IMAP */
async function refreshEmails() {
    const btn = document.getElementById('refreshBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<div class="loading-spinner" style="width:14px;height:14px;margin:0"></div> Wird geladen...'; }

    try {
        const folder = _emailCurrentFolder === '__starred__' ? 'INBOX' : _emailCurrentFolder;
        const res = await API.post('emails/refresh', { folder });
        Toast.success(res.message || 'Aktualisiert');
        renderEmailInboxPage(Router.renderCount);
    } catch (err) {
        Toast.error(err.message);
        if (btn) { btn.disabled = false; btn.innerHTML = 'Aktualisieren'; }
    }
}

/** Toggle star */
/** Toggle read/unread status — instant DOM update */
async function toggleReadStatus(id, currentIsRead, td) {
    const newIsRead = currentIsRead ? 0 : 1;
    try {
        await API.post('emails/' + id + '/read', { is_read: newIsRead });
        // Update icon instantly
        if (newIsRead) {
            td.innerHTML = '<span class="email-read-toggle read" title="Als ungelesen markieren">&#9675;</span>';
        } else {
            td.innerHTML = '<span class="email-read-toggle unread" title="Als gelesen markieren">&#9679;</span>';
        }
        // Update row styling
        const row = td.closest('tr');
        if (row) {
            row.dataset.isRead = newIsRead;
            row.style.background = newIsRead ? '' : '#f0f7ff';
            // Update font weight on text cells (skip checkbox, read-toggle, star, color columns = first 4)
            const tds = row.querySelectorAll('td');
            for (let i = 4; i < tds.length; i++) {
                tds[i].style.fontWeight = newIsRead ? '400' : '700';
            }
        }
        // Update onclick to pass new state
        td.onclick = function(e) { e.stopPropagation(); toggleReadStatus(id, newIsRead, td); };
    } catch (err) {
        Toast.error(err.message);
    }
}

async function toggleStar(id, el) {
    try {
        await API.post('emails/' + id + '/star');
        // Re-render list to show updated star
        renderEmailList(Router.renderCount);
    } catch (err) {
        Toast.error(err.message);
    }
}

/** Toggle inline color picker in table row */
function toggleInlineColorPicker(id, td) {
    // Close all other open pickers
    document.querySelectorAll('.inline-color-picker').forEach(p => {
        if (p.id !== 'icp-' + id) p.style.display = 'none';
    });
    const picker = document.getElementById('icp-' + id);
    if (picker) {
        picker.style.display = picker.style.display === 'none' ? 'flex' : 'none';
    }
}

/** Pick color and update instantly */
async function pickColor(id, color) {
    // Close picker
    const picker = document.getElementById('icp-' + id);
    if (picker) picker.style.display = 'none';

    try {
        await API.post('emails/' + id + '/color', { color: color });
        renderEmailList(Router.renderCount);
    } catch (err) {
        Toast.error(err.message);
    }
}

/** View email detail */
async function viewEmail(id) {
    const detail = document.getElementById('emailDetail');
    if (!detail) return;

    detail.style.display = 'block';
    detail.innerHTML = '<div class="card" style="margin-top:20px"><div class="card-body"><div class="loading-inline"><div class="loading-spinner"></div></div></div></div>';

    try {
        const res = await API.get('emails/' + id);
        const e = res.data;
        window._currentEmail = e;

        // Mark row as read in DOM instantly
        const row = document.querySelector('tr[data-email-id="' + id + '"]');
        if (row) {
            row.style.background = '';
            row.dataset.isRead = '1';
            // Update read toggle icon
            const readTd = row.querySelectorAll('td')[1];
            if (readTd) {
                readTd.innerHTML = '<span class="email-read-toggle read" title="Als ungelesen markieren">&#9675;</span>';
                readTd.onclick = function(ev) { ev.stopPropagation(); toggleReadStatus(id, 1, readTd); };
            }
            // Update font weight on text cells (skip first 4: checkbox, read, star, color)
            const tds = row.querySelectorAll('td');
            for (let i = 4; i < tds.length; i++) {
                tds[i].style.fontWeight = '400';
            }
        }

        const starActive = e.is_starred ? 'active' : '';
        const colorBtns = ['red','yellow','green','blue'].map(c =>
            `<button class="email-color-btn-sm ${e.color === c ? 'selected' : ''}" style="background:${COLOR_MAP[c]}" onclick="setDetailColor(${e.id},'${c}')" title="${c}"></button>`
        ).join('') + `<button class="email-color-btn-sm email-color-btn-clear-sm ${!e.color ? 'selected' : ''}" onclick="setDetailColor(${e.id},null)" title="Keine">&times;</button>`;

        detail.innerHTML = `
        <div class="card" style="margin-top:20px">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0">
                    <span class="email-star-lg ${starActive}" onclick="toggleStarDetail(${e.id}, this)" title="Wichtig markieren" style="cursor:pointer">&#9733;</span>
                    <span class="card-title" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(e.subject || '(Kein Betreff)')}</span>
                    <div class="email-detail-colors">${colorBtns}</div>
                </div>
                <div style="display:flex;gap:6px">
                    <button class="btn btn-sm btn-primary" onclick="openReplyEditor()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
                        Antworten
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="openForwardEditor()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 17 20 12 15 7"/><path d="M4 18v-2a4 4 0 0 1 4-4h12"/></svg>
                        Weiterleiten
                    </button>
                    <button class="btn btn-sm btn-secondary" style="color:var(--danger)" onclick="deleteEmail(${e.id})" title="Löschen">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        Löschen
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="document.getElementById('emailDetail').style.display='none'">&times;</button>
                </div>
            </div>
            <div class="card-body">
                <div style="display:flex;gap:24px;margin-bottom:16px;font-size:13px;color:var(--text-light);flex-wrap:wrap">
                    <div><strong>Von:</strong> ${esc(e.from_name ? e.from_name + ' <' + e.from_address + '>' : e.from_address)}</div>
                    <div><strong>An:</strong> ${esc(e.to_address)}</div>
                    <div><strong>Datum:</strong> ${formatDateTime(e.mail_date)}</div>
                </div>
                <div class="email-body-content" style="padding:16px;background:var(--bg);border-radius:var(--radius);font-size:14px;line-height:1.7;white-space:pre-wrap">${esc(e.body_preview || 'Kein Inhalt verfügbar.')}</div>
            </div>
        </div>

        <!-- Reply/Forward Editor -->
        <div id="emailEditor" style="display:none" class="card" style="margin-top:16px">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                <span class="card-title" id="editorTitle">Antworten</span>
                <button class="btn btn-sm btn-secondary" onclick="closeEditor()">&times; Abbrechen</button>
            </div>
            <div class="card-body">
                <div class="form-group" style="margin-bottom:12px">
                    <label class="form-label">An</label>
                    <input class="form-input" id="editorTo" value="">
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label class="form-label">Betreff</label>
                    <input class="form-input" id="editorSubject" value="">
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label class="form-label">Nachricht</label>
                    <div class="editor-toolbar">
                        <button type="button" onclick="execCmd('bold')" title="Fett"><b>B</b></button>
                        <button type="button" onclick="execCmd('italic')" title="Kursiv"><i>I</i></button>
                        <button type="button" onclick="execCmd('underline')" title="Unterstrichen"><u>U</u></button>
                        <span class="toolbar-sep"></span>
                        <button type="button" onclick="execCmd('insertUnorderedList')" title="Liste">&bull; Liste</button>
                        <button type="button" onclick="execCmd('insertOrderedList')" title="Nummerierte Liste">1. Liste</button>
                        <span class="toolbar-sep"></span>
                        <button type="button" onclick="execCmd('formatBlock','<h3>')" title="Überschrift">H</button>
                        <button type="button" onclick="execCmd('formatBlock','<p>')" title="Absatz">&para;</button>
                        <span class="toolbar-sep"></span>
                        <button type="button" onclick="insertLink()" title="Link">&#128279;</button>
                    </div>
                    <div class="editor-content" id="editorBody" contenteditable="true"></div>
                </div>
                <div class="form-group" style="margin-bottom:12px">
                    <label class="form-label">Anhänge</label>
                    <div class="email-attachment-area">
                        <input type="file" id="editorAttachments" multiple style="display:none" onchange="handleAttachmentSelect(this)">
                        <button class="btn btn-sm btn-secondary" onclick="document.getElementById('editorAttachments').click()" type="button">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                            Dateien anhängen
                        </button>
                        <div id="attachmentList" class="email-attachment-list"></div>
                    </div>
                </div>
                <button class="btn btn-primary" id="editorSendBtn" onclick="sendEmail()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    Senden
                </button>
            </div>
        </div>`;
    } catch (err) {
        detail.innerHTML = `<div class="card" style="margin-top:20px"><div class="card-body"><div class="empty-state"><p>Fehler: ${esc(err.message)}</p></div></div></div>`;
    }
}

/** Toggle star from detail view */
/** Select all / deselect all */
function toggleSelectAll(masterCheckbox) {
    document.querySelectorAll('.email-checkbox').forEach(cb => {
        cb.checked = masterCheckbox.checked;
    });
    updateBulkActions();
}

/** Update bulk actions bar visibility */
function updateBulkActions() {
    const checked = document.querySelectorAll('.email-checkbox:checked');
    const bar = document.getElementById('bulkActions');
    const count = document.getElementById('bulkCount');
    if (bar) {
        bar.style.display = checked.length > 0 ? 'flex' : 'none';
    }
    if (count) {
        count.textContent = checked.length + ' ausgewählt';
    }
}

/** Bulk delete selected emails */
async function bulkDelete() {
    const checked = document.querySelectorAll('.email-checkbox:checked');
    if (checked.length === 0) return;
    if (!confirm(checked.length + ' E-Mail(s) wirklich löschen?')) return;

    const ids = Array.from(checked).map(cb => parseInt(cb.value));
    try {
        const res = await API.post('emails/bulk-delete', { ids: ids });
        Toast.success(res.message || ids.length + ' E-Mail(s) gelöscht');
        renderEmailList(Router.renderCount);
    } catch (err) {
        Toast.error(err.message);
    }
}

/** Delete single email */
async function deleteEmail(id) {
    if (!confirm('E-Mail wirklich löschen?')) return;
    try {
        await API.delete('emails/' + id);
        Toast.success('E-Mail gelöscht');
        document.getElementById('emailDetail').style.display = 'none';
        renderEmailList(Router.renderCount);
    } catch (err) {
        Toast.error(err.message);
    }
}

async function toggleStarDetail(id, el) {
    try {
        const res = await API.post(`emails/${id}/star`);
        if (res.data.is_starred) {
            el.classList.add('active');
        } else {
            el.classList.remove('active');
        }
        window._currentEmail.is_starred = res.data.is_starred;
    } catch (err) {
        Toast.error(err.message);
    }
}

/** Set color from detail view — updates both detail AND list */
async function setDetailColor(id, color) {
    try {
        await API.post('emails/' + id + '/color', { color: color });
        window._currentEmail.color = color;

        // 1. Update detail color buttons (highlight selected)
        document.querySelectorAll('.email-color-btn-sm').forEach(function(btn) {
            btn.classList.remove('selected');
        });
        if (color) {
            var sel = document.querySelector('.email-color-btn-sm[style*="' + COLOR_MAP[color] + '"]');
            if (sel) sel.classList.add('selected');
        } else {
            var clr = document.querySelector('.email-color-btn-clear-sm');
            if (clr) clr.classList.add('selected');
        }

        // 2. Update list row color dot instantly
        var row = document.querySelector('tr[data-email-id="' + id + '"]');
        if (row) {
            var dotTd = row.querySelectorAll('td')[3];
            if (dotTd) {
                var dot = dotTd.querySelector('.email-color-dot');
                if (dot) {
                    if (color && COLOR_MAP[color]) {
                        dot.style.background = COLOR_MAP[color];
                        dot.className = 'email-color-dot';
                    } else {
                        dot.style.background = '';
                        dot.className = 'email-color-dot email-color-dot-empty';
                    }
                }
            }
        }
    } catch (err) {
        Toast.error(err.message);
    }
}

/** Attachment handling */
function handleAttachmentSelect(input) {
    const files = Array.from(input.files);
    _emailAttachments = [..._emailAttachments, ...files];
    renderAttachmentList();
}

function removeAttachment(index) {
    _emailAttachments.splice(index, 1);
    renderAttachmentList();
}

function renderAttachmentList() {
    const list = document.getElementById('attachmentList');
    if (!list) return;

    if (_emailAttachments.length === 0) {
        list.innerHTML = '';
        return;
    }

    list.innerHTML = _emailAttachments.map((f, i) => {
        const sizeKB = (f.size / 1024).toFixed(1);
        return `<div class="email-attachment-item">
            <span class="email-attachment-name">&#128206; ${esc(f.name)} <small>(${sizeKB} KB)</small></span>
            <button class="email-attachment-remove" onclick="removeAttachment(${i})" title="Entfernen">&times;</button>
        </div>`;
    }).join('');
}

/** Open Reply Editor */
function openReplyEditor() {
    const e = window._currentEmail;
    if (!e) return;
    _emailAttachments = [];
    const editor = document.getElementById('emailEditor');
    editor.style.display = 'block';
    document.getElementById('editorTitle').textContent = 'Antworten';
    document.getElementById('editorTo').value = e.from_address;
    document.getElementById('editorSubject').value = (e.subject || '').startsWith('Re:') ? e.subject : 'Re: ' + (e.subject || '');
    document.getElementById('editorBody').innerHTML = '<br><br><div style="border-left:3px solid var(--border);padding-left:12px;color:var(--text-light);margin-top:12px">' +
        '<p style="font-size:12px">Am ' + formatDateTime(e.mail_date) + ' schrieb ' + esc(e.from_name ? e.from_name + ' <' + e.from_address + '>' : e.from_address) + ':</p>' +
        '<p>' + esc(e.body_preview || '') + '</p></div>';
    document.getElementById('editorSendBtn').onclick = () => sendEmail('reply');
    renderAttachmentList();
    editor.scrollIntoView({ behavior: 'smooth' });
}

/** Open Forward Editor */
function openForwardEditor() {
    const e = window._currentEmail;
    if (!e) return;
    _emailAttachments = [];
    const editor = document.getElementById('emailEditor');
    editor.style.display = 'block';
    document.getElementById('editorTitle').textContent = 'Weiterleiten';
    document.getElementById('editorTo').value = '';
    document.getElementById('editorSubject').value = (e.subject || '').startsWith('Fwd:') ? e.subject : 'Fwd: ' + (e.subject || '');
    document.getElementById('editorBody').innerHTML = '<br><br><div style="border-left:3px solid var(--border);padding-left:12px;color:var(--text-light);margin-top:12px">' +
        '<p style="font-size:12px">--- Weitergeleitete Nachricht ---<br>Von: ' + esc(e.from_name ? e.from_name + ' <' + e.from_address + '>' : e.from_address) + '<br>Datum: ' + formatDateTime(e.mail_date) + '<br>An: ' + esc(e.to_address || '') + '<br>Betreff: ' + esc(e.subject || '') + '</p>' +
        '<p>' + esc(e.body_preview || '') + '</p></div>';
    document.getElementById('editorSendBtn').onclick = () => sendEmail('forward');
    renderAttachmentList();
    editor.scrollIntoView({ behavior: 'smooth' });
    document.getElementById('editorTo').focus();
}

/** Close Editor */
function closeEditor() {
    const editor = document.getElementById('emailEditor');
    if (editor) editor.style.display = 'none';
    _emailAttachments = [];
}

/** Rich text editor commands */
function execCmd(cmd, value) {
    document.execCommand(cmd, false, value || null);
    document.getElementById('editorBody').focus();
}

function insertLink() {
    const url = prompt('Link-URL:', 'https://');
    if (url) document.execCommand('createLink', false, url);
}

/** Send email (reply or forward) — uses FormData when attachments present */
async function sendEmail(type) {
    const to = document.getElementById('editorTo').value.trim();
    const subject = document.getElementById('editorSubject').value.trim();
    const body = document.getElementById('editorBody').innerHTML;
    const e = window._currentEmail;

    if (!to) { Toast.error('Bitte Empfänger-Adresse eingeben'); return; }
    if (!body || body === '<br>') { Toast.error('Nachricht darf nicht leer sein'); return; }

    const btn = document.getElementById('editorSendBtn');
    btn.disabled = true;
    btn.innerHTML = '<div class="loading-spinner" style="width:14px;height:14px;margin:0"></div> Wird gesendet...';

    try {
        const endpoint = type === 'forward' ? 'emails/forward' : 'emails/reply';

        if (_emailAttachments.length > 0) {
            // Use FormData for attachments
            const fd = new FormData();
            fd.append('to', to);
            fd.append('subject', subject);
            fd.append('body', body);
            if (type !== 'forward' && e?.message_id) {
                fd.append('in_reply_to', e.message_id);
            }
            _emailAttachments.forEach(f => {
                fd.append('attachments[]', f);
            });
            await API.upload(endpoint, fd);
        } else {
            // Standard JSON
            await API.post(endpoint, {
                to, subject, body,
                in_reply_to: e?.message_id || '',
            });
        }

        Toast.success('E-Mail wurde gesendet!');
        closeEditor();
    } catch (err) {
        Toast.error(err.message);
        btn.disabled = false;
        btn.innerHTML = 'Senden';
    }
}
