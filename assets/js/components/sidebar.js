/**
 * RohrApp+ — Sidebar Component
 * Generates sidebar HTML based on user role and current page.
 */
function renderSidebar(activePage) {
    const user = AppState.user;
    const role = user?.role || 'user';

    // Menu items — visibility controlled by role/package
    const menuItems = [
        { id: 'dashboard',      label: 'Dashboard',          icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>' },
        { id: 'license',        label: 'Lizenz',             icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 12l2 2 4-4"/></svg>' },
        { id: 'settings',       label: 'Einstellungen',      icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9V12a2 2 0 0 1 0 0z"/></svg>' },
        { id: 'email-settings', label: 'Email Einstellungen', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>', separator: true },
        { id: 'emails',         label: 'Email Inbox',        icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>' },
        { id: 'messages',       label: 'Nachrichten',        icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>' },
        { id: 'calls',          label: 'Anrufe',             icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>' },
        { id: 'auftraege',      label: 'Aufträge',           icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>' },
        { id: 'falsche-anrufe', label: 'Falsche Anrufe',     icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>', separator: true },
    ];

    // Admin items
    const adminItems = [
        { id: 'admin/users',    label: 'Benutzer',           icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>', adminOnly: true },
        { id: 'admin/requests', label: 'Anfragen',           icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>', adminOnly: true },
        { id: 'admin/logs',     label: 'Aktivität',          icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>', adminOnly: true },
    ];

    const allItems = role === 'admin' ? [...menuItems, ...adminItems] : menuItems;

    let navHtml = '';
    allItems.forEach(item => {
        if (item.adminOnly && role !== 'admin') return;
        if (item.separator) navHtml += '<div class="nav-separator"></div>';
        const active = activePage === item.id ? ' active' : '';
        navHtml += `<a href="#/${item.id}" class="nav-item${active}" data-page="${item.id}">
            <span class="nav-icon">${item.icon}</span>
            <span class="nav-label">${item.label}</span>
        </a>`;
    });

    return `
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="assets/img/rohrapp.png" alt="RohrApp+" class="sidebar-logo-img">
            <span class="sidebar-title">RohrApp+</span>
        </div>
        <nav class="sidebar-nav">${navHtml}</nav>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-avatar">${esc(AppState.initials())}</div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name">${esc(AppState.displayName())}</div>
                    <div class="sidebar-user-email">${esc(user?.email)}</div>
                </div>
            </div>
            <button class="btn-logout" onclick="doLogout()" title="Abmelden">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </button>
        </div>
    </aside>`;
}

/**
 * Panel layout wrapper — sidebar + main content
 * Smart: only rebuilds sidebar if not already present, otherwise updates content only
 */
function panelLayout(activePage, title, contentHtml) {
    const app = document.getElementById('app');
    const existing = document.getElementById('sidebar');

    if (existing) {
        // Sidebar exists — just update active state and content
        document.querySelectorAll('.nav-item').forEach(el => {
            const page = (el.getAttribute('href') || '').replace('#/', '');
            el.classList.toggle('active', page === activePage);
        });
        const header = document.querySelector('.content-header h1');
        if (header) header.textContent = title;
        const body = document.querySelector('.content-body');
        if (body) body.innerHTML = contentHtml;
    } else {
        // First render — build full layout
        app.innerHTML = `
        <div class="panel-layout">
            ${renderSidebar(activePage)}
            <main class="main-content">
                <div class="content-header"><h1>${esc(title)}</h1></div>
                <div class="content-body">${contentHtml}</div>
            </main>
        </div>`;
    }
}
