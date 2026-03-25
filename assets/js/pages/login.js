/**
 * RohrApp+ — Login Page
 */
function renderLoginPage() {
    const app = document.getElementById('app');
    app.innerHTML = `
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-logo">
                <img src="assets/img/rohrapp.png" alt="RohrApp+" class="auth-logo-img">
                <h1 class="auth-title">RohrApp+</h1>
                <p class="auth-subtitle">Melden Sie sich bei Ihrem Konto an</p>
            </div>
            <form id="loginForm" class="auth-form">
                <div class="form-group">
                    <label class="form-label" for="email">E-Mail-Adresse</label>
                    <input type="email" id="email" class="form-input" placeholder="name@firma.de" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Passwort</label>
                    <input type="password" id="password" class="form-input" placeholder="Mindestens 8 Zeichen" required>
                </div>
                <button type="submit" class="btn btn-primary btn-full" id="loginBtn">
                    Anmelden
                </button>
                <div class="auth-error" id="loginError" style="display:none"></div>
            </form>
            <div class="auth-links">
                <a href="#/forgot-password" class="auth-link">Passwort vergessen?</a>
                <span class="auth-divider">|</span>
                <a href="#/register" class="auth-link">Konto erstellen</a>
            </div>
        </div>
    </div>`;

    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('loginBtn');
        const errDiv = document.getElementById('loginError');
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;

        btn.disabled = true;
        btn.textContent = 'Wird angemeldet...';
        errDiv.style.display = 'none';

        try {
            const res = await API.post('auth/login', { email, password });
            AppState.setUser(res.data.user);
            Toast.success('Willkommen zurück!');
            Router.navigate('dashboard');
        } catch (err) {
            errDiv.textContent = err.message;
            errDiv.style.display = 'block';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Anmelden';
        }
    });
}
