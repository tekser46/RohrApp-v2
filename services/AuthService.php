<?php
/**
 * AuthService — Authentication business logic
 */
class AuthService
{
    private User $userModel;
    private LoginAttempt $loginAttemptModel;
    private PasswordReset $passwordResetModel;

    public function __construct()
    {
        $this->userModel          = new User();
        $this->loginAttemptModel  = new LoginAttempt();
        $this->passwordResetModel = new PasswordReset();
    }

    /**
     * Register a new user
     * @throws Exception on validation failure
     */
    public function register(string $email, string $password): array
    {
        $email = trim(strtolower($email));

        // Rate limit: max 5 registrations per IP per hour
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) AND id IN (SELECT user_id FROM activity_logs WHERE ip_address = ? AND action = 'register' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR))");
        $stmt->execute([$ip]);
        if ((int) $stmt->fetchColumn() >= 5) {
            throw new Exception('Zu viele Registrierungen. Bitte versuchen Sie es später.');
        }

        // Validation
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Bitte geben Sie eine gültige E-Mail-Adresse ein.');
        }
        if (strlen($password) < 8) {
            throw new Exception('Das Passwort muss mindestens 8 Zeichen lang sein.');
        }

        // Check duplicate
        if ($this->userModel->findByEmail($email)) {
            throw new Exception('Diese E-Mail-Adresse ist bereits registriert.');
        }

        // Create user + profile + demo license
        $hash   = password_hash($password, PASSWORD_DEFAULT);
        $userId = $this->userModel->register($email, $hash);
        $user   = $this->userModel->find($userId);

        // Start session
        $this->startSession($user);

        return $user;
    }

    /**
     * Login
     * @throws Exception on failure
     */
    public function login(string $email, string $password, string $ip): array
    {
        $email = trim(strtolower($email));

        // Rate limit check
        if ($this->loginAttemptModel->isLocked($ip)) {
            throw new Exception('Zu viele fehlgeschlagene Versuche. Bitte versuchen Sie es in 15 Minuten erneut.');
        }

        // Find user
        $user = $this->userModel->findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->loginAttemptModel->recordFailure($ip, $email);
            throw new Exception('E-Mail oder Passwort ist falsch.');
        }

        // Check active
        if (!$user['is_active']) {
            throw new Exception('Ihr Konto wurde deaktiviert.');
        }

        // Success — clear attempts, update last login
        $this->loginAttemptModel->clearAttempts($ip);
        $this->userModel->updateLastLogin($user['id'], $ip);

        // Start session
        $this->startSession($user);

        return $user;
    }

    /**
     * Logout
     */
    public function logout(): void
    {
        Session::destroy();
    }

    /**
     * Get current authenticated user with profile
     */
    public function getCurrentUser(): ?array
    {
        $userId = Session::userId();
        if (!$userId) return null;

        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT u.id, u.email, u.role, u.is_active, u.last_login_at,
                   p.first_name, p.last_name, p.company_name, p.avatar_path
            FROM users u
            LEFT JOIN user_profiles p ON p.user_id = u.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !$user['is_active']) {
            Session::destroy();
            return null;
        }

        return $user;
    }

    /**
     * Create password reset token
     * @throws Exception
     */
    public function forgotPassword(string $email): string
    {
        $email = trim(strtolower($email));
        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            // Don't reveal if email exists — still return success
            return '';
        }

        $token = $this->passwordResetModel->createToken($user['id']);

        // In production: send email with reset link
        // For now: return token (will be used in frontend)
        return $token;
    }

    /**
     * Reset password with token
     * @throws Exception
     */
    public function resetPassword(string $token, string $newPassword): void
    {
        if (strlen($newPassword) < 8) {
            throw new Exception('Das Passwort muss mindestens 8 Zeichen lang sein.');
        }

        $reset = $this->passwordResetModel->findValidToken($token);
        if (!$reset) {
            throw new Exception('Ungültiger oder abgelaufener Token.');
        }

        // Update password
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->userModel->update($reset['user_id'], ['password_hash' => $hash]);

        // Mark token used
        $this->passwordResetModel->markUsed($reset['id']);
    }

    /**
     * Change password (authenticated user)
     * @throws Exception
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        if (strlen($newPassword) < 8) {
            throw new Exception('Das Passwort muss mindestens 8 Zeichen lang sein.');
        }

        $user = $this->userModel->find($userId);
        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            throw new Exception('Aktuelles Passwort ist falsch.');
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->userModel->update($userId, ['password_hash' => $hash]);
    }

    /**
     * Start authenticated session
     */
    private function startSession(array $user): void
    {
        Session::regenerate(); // Fixation protection
        Session::set('user_id', $user['id']);
        Session::set('user_role', $user['role']);
        Session::set('csrf_token', bin2hex(random_bytes(32)));
    }
}
