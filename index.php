<?php
/**
 * RohrApp+ v2 — Single Entry Point
 * All /api/* requests are routed here.
 */

// ── Error handling ──
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
ini_set('error_log', $logDir . '/error.log');

// Global exception handler — always return JSON
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]
    ]);
    exit;
});

// ── JSON content type ──
header('Content-Type: application/json; charset=UTF-8');

// ── Security Headers ──
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// ── CORS ──
$allowedOrigins = defined('CORS_ORIGINS') ? explode(',', CORS_ORIGINS) : ['*'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Autoloader ──
$baseDir = __DIR__;
spl_autoload_register(function ($class) use ($baseDir) {
    $dirs = ['core', 'controllers', 'models', 'services', 'middlewares', 'helpers'];
    foreach ($dirs as $dir) {
        $file = $baseDir . '/' . $dir . '/' . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// ── Session ──
Session::start();

// ── Get route ──
$route  = trim($_GET['route'] ?? '', '/');
$method = $_SERVER['REQUEST_METHOD'];

// ── Router setup ──
$router = new Router();

// Health check (no auth)
$router->get('health', ['HealthController', 'index']);

// ── Version info ──
$router->get('version', ['HealthController', 'version']);

// ── Faz 2: Auth routes (rate limited, no CSRF) ──
$rateLogin = [new RateLimitMiddleware(5, 900, 'login')];       // 5 per 15min
$rateRegister = [new RateLimitMiddleware(3, 3600, 'register')]; // 3 per hour
$rateForgot = [new RateLimitMiddleware(3, 3600, 'forgot')];     // 3 per hour
$router->post('auth/register',        ['AuthController', 'register'],       $rateRegister);
$router->post('auth/login',           ['AuthController', 'login'],          $rateLogin);
$router->post('auth/logout',          ['AuthController', 'logout']);
$router->get('auth/me',               ['AuthController', 'me'],             ['AuthMiddleware']);
$router->post('auth/forgot-password', ['AuthController', 'forgotPassword'], $rateForgot);
$router->post('auth/reset-password',  ['AuthController', 'resetPassword']);

// ── Faz 3: User, License, Dashboard ──
$auth     = ['AuthMiddleware'];
$authCsrf = ['AuthMiddleware', 'CsrfMiddleware']; // POST/PUT/DELETE with CSRF
$router->get('dashboard',        ['DashboardController', 'index'],    $auth);
$router->get('user/profile',     ['UserController', 'getProfile'],    $auth);
$router->put('user/profile',     ['UserController', 'updateProfile'], $authCsrf);
$router->post('user/avatar',     ['UserController', 'uploadAvatar'],  $authCsrf);
$router->post('user/logo',       ['UserController', 'uploadLogo'],    $authCsrf);
$router->put('user/password',    ['UserController', 'changePassword'],$authCsrf);
$router->get('license',          ['LicenseController', 'current'],    $auth);
$router->get('license/packages', ['LicenseController', 'packages'],   $auth);

// ── Faz 4: Upgrade Requests + Admin ──
$router->post('upgrade-requests',            ['UpgradeRequestController', 'create'], $authCsrf);
$router->get('upgrade-requests',             ['UpgradeRequestController', 'list'],   $auth);

$admin     = ['AdminMiddleware'];
$adminCsrf = ['AdminMiddleware', 'CsrfMiddleware'];
$router->get('admin/users',                  ['AdminController', 'users'],           $admin);
$router->get('admin/user/:id',               ['AdminController', 'userDetail'],      $admin);
$router->get('admin/upgrade-requests',       ['AdminController', 'upgradeRequests'], $admin);
$router->put('admin/upgrade-requests/:id',   ['AdminController', 'handleRequest'],   $adminCsrf);
$router->put('admin/user/:id/license',       ['AdminController', 'updateLicense'],   $adminCsrf);
$router->get('admin/logs',                   ['AdminController', 'logs'],            $admin);

// ── System Update (Admin only) ──
$router->get('system/check-update',          ['SystemController', 'checkUpdate'],    $admin);
$router->post('system/do-update',            ['SystemController', 'doUpdate'],       $adminCsrf);

// ── Faz 5: Email Settings + Inbox ──
$router->get('email-settings',       ['EmailSettingsController', 'get'],   $auth);
$router->put('email-settings',       ['EmailSettingsController', 'save'],  $authCsrf);
$router->post('email-settings/test', ['EmailSettingsController', 'test'],  $authCsrf);
$router->get('emails',               ['EmailInboxController', 'list'],     $auth);
$router->get('emails/folders',       ['EmailInboxController', 'folders'],  $auth);
$router->get('emails/:id',           ['EmailInboxController', 'detail'],   $auth);
$router->post('emails/refresh',      ['EmailInboxController', 'refresh'],  $auth);  // no CSRF — auto-refresh
$router->post('emails/bulk-delete',  ['EmailInboxController', 'bulkDelete'], $authCsrf);
$router->post('emails/reply',        ['EmailInboxController', 'reply'],    $authCsrf);
$router->post('emails/forward',      ['EmailInboxController', 'forward'],  $authCsrf);
$router->delete('emails/:id',        ['EmailInboxController', 'deleteEmail'], $authCsrf);
$router->post('emails/:id/read',     ['EmailInboxController', 'setRead'],  $auth);  // lightweight toggle
$router->post('emails/:id/star',     ['EmailInboxController', 'star'],     $auth);  // lightweight toggle
$router->post('emails/:id/color',    ['EmailInboxController', 'color'],    $auth);  // lightweight toggle

// ── Faz 6: Messages + Websites ──
$router->get('messages',               ['MessageController', 'list'],       $auth);
$router->get('messages/:id',           ['MessageController', 'detail'],     $auth);
$router->put('messages/:id/read',      ['MessageController', 'markRead'],   $auth);  // lightweight toggle
$router->put('messages/:id/unread',    ['MessageController', 'markUnread'], $auth);  // lightweight toggle
$router->put('messages/:id/archive',   ['MessageController', 'archive'],    $authCsrf);
$router->delete('messages/:id',        ['MessageController', 'delete'],     $authCsrf);
$router->post('messages/bulk-delete',  ['MessageController', 'bulkDelete'], $authCsrf);

$router->get('websites',              ['WebsiteController', 'list'],        $auth);
$router->post('websites',             ['WebsiteController', 'create'],      $authCsrf);
$router->delete('websites/:id',       ['WebsiteController', 'delete'],      $authCsrf);

// ── Faz 7: Sipgate + Calls ──
$router->get('sipgate/settings',          ['SipgateController', 'getSettings'],  $auth);
$router->put('sipgate/settings',          ['SipgateController', 'updateSettings'], $authCsrf);
$router->post('sipgate/numbers',          ['SipgateController', 'addNumber'],    $authCsrf);
$router->delete('sipgate/numbers/:id',    ['SipgateController', 'deleteNumber'], $authCsrf);
$router->post('sipgate/numbers/:id/block',['SipgateController', 'toggleBlock'],  $authCsrf);
$router->get('calls',                     ['CallController', 'list'],            $auth);
$router->get('calls/falsch',              ['CallController', 'falschList'],      $auth);
$router->get('calls/:id',                 ['CallController', 'detail'],          $auth);
$router->post('calls/:id/categorize',     ['CallController', 'categorize'],      $auth);
$router->get('auftraege',                 ['CallController', 'auftragList'],     $auth);
$router->post('auftraege',                ['CallController', 'auftragCreate'],   $authCsrf);
$router->put('auftraege/:id',             ['CallController', 'auftragUpdate'],   $authCsrf);
$router->put('auftraege/:id/status',      ['CallController', 'auftragStatus'],   $authCsrf);
$router->delete('auftraege/:id',          ['CallController', 'auftragDelete'],   $authCsrf);

// ── Webhook (public, no auth — uses token) ──
$router->post('webhook/contact',      ['WebhookController', 'contact']);
$rateWebhook = [new RateLimitMiddleware(60, 60, 'webhook')]; // 60 per minute
$router->post('webhook/sipgate',      ['WebhookController', 'sipgate'],   $rateWebhook);

// ── Dispatch ──
$router->dispatch($method, $route);
