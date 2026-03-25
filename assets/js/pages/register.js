/**
 * RohrApp+ — Register Page
 */
function renderRegisterPage() {
    const app = document.getElementById('app');
    app.innerHTML = `
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-logo">
                <img src="assets/img/rohrapp.png" alt="RohrApp+" class="auth-logo-img">
                <h1 class="auth-title">RohrApp+</h1>
                <p class="auth-subtitle">Erstellen Sie Ihr kostenloses Konto</p>
            </div>
            <form id="registerForm" class="auth-form">
                <div class="form-group">
                    <label class="form-label" for="regEmail">E-Mail-Adresse</label>
                    <input type="email" id="regEmail" class="form-input" placeholder="name@firma.de" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label" for="regPassword">Passwort</label>
                    <input type="password" id="regPassword" class="form-input" placeholder="Mindestens 8 Zeichen" required minlength="8">
                </div>
                <div class="form-group">
                    <label class="form-label" for="regPassword2">Passwort bestätigen</label>
                    <input type="password" id="regPassword2" class="form-input" placeholder="Passwort wiederholen" required minlength="8">
                </div>
                <button type="submit" class="btn btn-primary btn-full" id="regBtn">
                    Kostenlos registrieren
                </button>
                <div class="auth-error" id="regError" style="display:none"></div>
            </form>
            <div class="auth-links">
                <span class="auth-text">Bereits ein Konto?</span>
                <a href="#/login" class="auth-link">Anmelden</a>
            </div>
            <div class="auth-hint">
                Sie starten mit dem <strong>Demo-Paket</strong> — kostenlos und unverbindlich.
            </div>
        </div>
    </div>`;

    document.getElementById('registerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('regBtn');
        const errDiv = document.getElementById('regError');
        const email = document.getElementById('regEmail').value.trim();
        const password = document.getElementById('regPassword').value;
        const password2 = document.getElementById('regPassword2').value;

        errDiv.style.display = 'none';

        if (password !== password2) {
            errDiv.textContent = 'Passwörter stimmen nicht überein.';
            errDiv.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Wird erstellt...';

        try {
            const res = await API.post('auth/register', { email, password });
            AppState.setUser(res.data.user);
            Toast.success('Konto erfolgreich erstellt!');
            Router.navigate('dashboard');
        } catch (err) {
            errDiv.textContent = err.message;
            errDiv.style.display = 'block';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Kostenlos registrieren';
        }
    });
}
