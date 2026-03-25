/**
 * RohrApp+ — Forgot Password Page
 */
function renderForgotPasswordPage() {
    const app = document.getElementById('app');
    app.innerHTML = `
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-logo">
                <img src="assets/img/rohrapp.png" alt="RohrApp+" class="auth-logo-img">
                <h1 class="auth-title">Passwort zurücksetzen</h1>
                <p class="auth-subtitle">Geben Sie Ihre E-Mail-Adresse ein</p>
            </div>
            <form id="forgotForm" class="auth-form">
                <div class="form-group">
                    <label class="form-label" for="forgotEmail">E-Mail-Adresse</label>
                    <input type="email" id="forgotEmail" class="form-input" placeholder="name@firma.de" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary btn-full" id="forgotBtn">
                    Link senden
                </button>
                <div class="auth-success" id="forgotSuccess" style="display:none"></div>
                <div class="auth-error" id="forgotError" style="display:none"></div>
            </form>
            <div class="auth-links">
                <a href="#/login" class="auth-link">← Zurück zur Anmeldung</a>
            </div>
        </div>
    </div>`;

    document.getElementById('forgotForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('forgotBtn');
        const errDiv = document.getElementById('forgotError');
        const sucDiv = document.getElementById('forgotSuccess');
        const email = document.getElementById('forgotEmail').value.trim();

        btn.disabled = true;
        btn.textContent = 'Wird gesendet...';
        errDiv.style.display = 'none';
        sucDiv.style.display = 'none';

        try {
            const res = await API.post('auth/forgot-password', { email });
            sucDiv.textContent = res.message || 'Falls ein Konto mit dieser E-Mail existiert, wurde ein Link gesendet.';
            sucDiv.style.display = 'block';
            btn.textContent = 'Gesendet ✓';
        } catch (err) {
            errDiv.textContent = err.message;
            errDiv.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Link senden';
        }
    });
}
