/**
 * RohrApp+ v2 — Main Application
 * Entry point: bootstraps auth check, registers routes, starts router.
 */
(async function () {
    'use strict';

    // ── Register routes ──
    Router.register('login',           renderLoginPage,          false);
    Router.register('register',        renderRegisterPage,       false);
    Router.register('forgot-password', renderForgotPasswordPage, false);
    Router.register('dashboard',       renderDashboardPage,      true);
    Router.register('license',         renderLicensePage,        true);
    Router.register('settings',        renderSettingsPage,       true);
    Router.register('email-settings',  renderEmailSettingsPage,  true);
    Router.register('emails',          renderEmailInboxPage,     true);
    Router.register('messages',        renderMessages,           true);
    Router.register('calls',           renderCallsPage,          true);
    Router.register('auftraege',       renderAuftraegePage,      true);
    Router.register('falsche-anrufe',  renderFalscheAnrufePage,  true);
    Router.register('admin/users',     renderAdminUsersPage,     true);
    Router.register('admin/requests',  renderAdminRequestsPage,  true);
    Router.register('admin/logs',      renderAdminLogsPage,      true);

    // ── Check if already authenticated ──
    try {
        const res = await API.get('auth/me');
        if (res.data && res.data.user) {
            AppState.setUser(res.data.user);
        }
    } catch (e) {
        // Not authenticated — that's fine
        AppState.clearUser();
    }

    // ── Start router ──
    Router.init();
})();
