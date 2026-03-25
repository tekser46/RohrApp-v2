<?php
/**
 * AuthController — Registration, Login, Logout, Password Reset
 */
class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * POST /api/auth/register
     */
    public function register(array $params = []): void
    {
        $email    = Request::input('email', '');
        $password = Request::input('password', '');

        try {
            $user = $this->authService->register($email, $password);
            Response::success([
                'user' => [
                    'id'    => $user['id'],
                    'email' => $user['email'],
                    'role'  => $user['role'],
                ],
                'csrf_token' => Session::csrfToken(),
            ], 'Registrierung erfolgreich', 201);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 422, 'VALIDATION_ERROR');
        }
    }

    /**
     * POST /api/auth/login
     */
    public function login(array $params = []): void
    {
        $email    = Request::input('email', '');
        $password = Request::input('password', '');
        $ip       = Request::ip();

        try {
            $user = $this->authService->login($email, $password, $ip);
            $me   = $this->authService->getCurrentUser();

            // Clear rate limit on successful login
            RateLimitMiddleware::clear('login');

            Response::success([
                'user'       => $me,
                'csrf_token' => Session::csrfToken(),
            ], 'Anmeldung erfolgreich');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 401, 'AUTH_ERROR');
        }
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(array $params = []): void
    {
        $this->authService->logout();
        Response::success(null, 'Abmeldung erfolgreich');
    }

    /**
     * GET /api/auth/me
     */
    public function me(array $params = []): void
    {
        $user = $this->authService->getCurrentUser();
        if (!$user) {
            Response::unauthorized();
        }

        Response::success([
            'user'       => $user,
            'csrf_token' => Session::csrfToken(),
        ]);
    }

    /**
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(array $params = []): void
    {
        $email = Request::input('email', '');

        try {
            $token = $this->authService->forgotPassword($email);
            // Always return success (don't reveal if email exists)
            Response::success([
                // In dev mode, return token for testing
                'debug_token' => ($this->config()['debug'] ?? false) ? $token : null,
            ], 'Falls ein Konto mit dieser E-Mail existiert, wurde ein Link zum Zurücksetzen gesendet.');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * POST /api/auth/reset-password
     */
    public function resetPassword(array $params = []): void
    {
        $token    = Request::input('token', '');
        $password = Request::input('password', '');

        try {
            $this->authService->resetPassword($token, $password);
            Response::success(null, 'Passwort erfolgreich zurückgesetzt.');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 422, 'VALIDATION_ERROR');
        }
    }
}
