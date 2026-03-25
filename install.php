<?php
/**
 * RohrApp+ v2 — One-Click Installer
 * Creates database, tables, config files, admin user, directories
 * Works on XAMPP (localhost) and All-Inkl shared hosting
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$rootDir = __DIR__;
$lockFile = $rootDir . '/logs/.installed';
$step = $_POST['step'] ?? $_GET['step'] ?? '';

// Already installed check
if (file_exists($lockFile) && $step !== 'reinstall') {
    if ($step === 'check') {
        header('Content-Type: application/json');
        echo json_encode(['installed' => true]);
        exit;
    }
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>RohrApp+</title></head><body style="font-family:sans-serif;text-align:center;padding:60px">';
    echo '<h2>RohrApp+ ist bereits installiert.</h2>';
    echo '<p><a href="app.html">Zum Panel &rarr;</a></p>';
    echo '</body></html>';
    exit;
}

// Handle AJAX install request
if ($step === 'install' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $host  = trim($_POST['db_host'] ?? '127.0.0.1');
    $port  = (int)($_POST['db_port'] ?? 3306);
    $name  = trim($_POST['db_name'] ?? 'rohrapp');
    $user  = trim($_POST['db_user'] ?? 'root');
    $pass  = $_POST['db_pass'] ?? '';
    $email = trim($_POST['admin_email'] ?? 'admin@rohrapp.de');
    $pw    = $_POST['admin_password'] ?? 'admin123';
    $url   = trim($_POST['app_url'] ?? '');

    // Auto-detect URL if not provided
    if (!$url) {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host2 = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $url = $proto . '://' . $host2;
    }
    $url = rtrim($url, '/');

    $errors = [];
    if (!$host) $errors[] = 'DB Host ist erforderlich';
    if (!$name) $errors[] = 'DB Name ist erforderlich';
    if (!$user) $errors[] = 'DB User ist erforderlich';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Gültige Admin-E-Mail erforderlich';
    if (strlen($pw) < 6) $errors[] = 'Admin-Passwort muss mindestens 6 Zeichen haben';

    if ($errors) {
        echo json_encode(['success' => false, 'message' => implode("\n", $errors)]);
        exit;
    }

    try {
        // 1. Test DB connection (connect without db name first to create it)
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            // If can't connect without db, try with db name directly
            try {
                $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
            } catch (PDOException $e2) {
                throw new Exception("DB-Verbindung fehlgeschlagen: " . $e2->getMessage());
            }
        }

        // 2. Use database
        $pdo->exec("USE `{$name}`");

        // 3. Run schema (statement by statement)
        $schemaFile = $rootDir . '/database/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception('schema.sql nicht gefunden');
        }
        $schemaSql = file_get_contents($schemaFile);
        // Remove SQL comments
        $schemaSql = preg_replace('/--[^\n]*/', '', $schemaSql);
        $schemaSql = preg_replace('/\/\*.*?\*\//s', '', $schemaSql);
        $schemaStatements = array_filter(array_map('trim', explode(';', $schemaSql)), fn($s) => strlen($s) > 5);
        foreach ($schemaStatements as $sqlStmt) {
            $pdo->exec($sqlStmt);
        }

        // 4. Check if packages exist, if not run seed
        $countStmt = $pdo->query("SELECT COUNT(*) FROM packages");
        if ((int)$countStmt->fetchColumn() === 0) {
            $seedFile = $rootDir . '/database/seed.sql';
            if (file_exists($seedFile)) {
                $seedSql = file_get_contents($seedFile);
                $seedSql = preg_replace('/--[^\n]*/', '', $seedSql);
                $seedStatements = array_filter(array_map('trim', explode(';', $seedSql)), fn($s) => strlen($s) > 5);
                foreach ($seedStatements as $seedStmt) {
                    $pdo->exec($seedStmt);
                }
            }
        }

        // 5. Create or update admin user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existingAdmin = $stmt->fetch();
        if ($existingAdmin) {
            // Update password for existing admin (seed might have wrong hash)
            $hash = password_hash($pw, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password_hash = ?, role = 'admin' WHERE id = ?")
                ->execute([$hash, $existingAdmin['id']]);
        } else {
            $hash = password_hash($pw, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (email, password_hash, role, is_active, email_verified_at) VALUES (?, ?, 'admin', 1, NOW())")
                ->execute([$email, $hash]);
            $adminId = (int)$pdo->lastInsertId();

            // Create profile
            $pdo->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, company_name) VALUES (?, 'System', 'Administrator', 'RohrApp+')")
                ->execute([$adminId]);

            // Create license (Professional, active, no expiry)
            $licKey = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4) . '-' .
                      substr(md5(uniqid(mt_rand(), true)), 0, 4) . '-' .
                      substr(md5(uniqid(mt_rand(), true)), 0, 4) . '-' .
                      substr(md5(uniqid(mt_rand(), true)), 0, 4));
            $pdo->prepare("INSERT INTO user_licenses (user_id, package_id, license_key, status, starts_at) VALUES (?, 3, ?, 'active', NOW())")
                ->execute([$adminId, $licKey]);

            // Create sipgate settings
            $webhookToken = bin2hex(random_bytes(16));
            $pdo->prepare("INSERT INTO sipgate_settings (user_id, webhook_token) VALUES (?, ?)")
                ->execute([$adminId, $webhookToken]);
        }

        // 6. Generate encryption key
        $encryptionKey = bin2hex(random_bytes(32));

        // 7. Write config/database.php
        $configDir = $rootDir . '/config';
        if (!is_dir($configDir)) mkdir($configDir, 0755, true);

        $dbConfig = "<?php\nreturn [\n" .
            "    'host'    => " . var_export($host, true) . ",\n" .
            "    'port'    => {$port},\n" .
            "    'name'    => " . var_export($name, true) . ",\n" .
            "    'user'    => " . var_export($user, true) . ",\n" .
            "    'pass'    => " . var_export($pass, true) . ",\n" .
            "    'charset' => 'utf8mb4',\n" .
            "];\n";
        file_put_contents($configDir . '/database.php', $dbConfig);

        // 8. Write config/app.php
        $appConfig = "<?php\nreturn [\n" .
            "    'name'            => 'RohrApp+',\n" .
            "    'url'             => " . var_export($url, true) . ",\n" .
            "    'encryption_key'  => '{$encryptionKey}',\n" .
            "    'session_name'    => 'rohrapp_session',\n" .
            "    'upload_path'     => __DIR__ . '/../uploads',\n" .
            "    'upload_max_size' => 5 * 1024 * 1024,\n" .
            "    'upload_allowed'  => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],\n" .
            "    'github_repo'     => 'tekser46/RohrApp-v2',\n" .
            "    'rate_limits'     => [\n" .
            "        'login'    => ['max' => 5,  'window' => 900],\n" .
            "        'register' => ['max' => 3,  'window' => 3600],\n" .
            "        'api'      => ['max' => 60, 'window' => 60],\n" .
            "    ],\n" .
            "];\n";
        file_put_contents($configDir . '/app.php', $appConfig);

        // 9. Create directories
        $dirs = ['logs', 'uploads/avatars', 'uploads/logos'];
        foreach ($dirs as $d) {
            $path = $rootDir . '/' . $d;
            if (!is_dir($path)) mkdir($path, 0755, true);
        }

        // 10. Write lock file
        file_put_contents($lockFile, json_encode([
            'installed_at' => date('Y-m-d H:i:s'),
            'version' => '2.0.0',
            'admin_email' => $email,
        ]));

        echo json_encode([
            'success' => true,
            'message' => 'Installation erfolgreich! Admin: ' . $email
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RohrApp+ Installation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0a1628; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { background: #1e293b; border-radius: 16px; padding: 40px; max-width: 500px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
        h1 { color: #3b82f6; font-size: 28px; margin-bottom: 8px; }
        .subtitle { color: #94a3b8; margin-bottom: 30px; }
        .field { margin-bottom: 16px; }
        label { display: block; font-size: 13px; color: #94a3b8; margin-bottom: 4px; }
        input { width: 100%; padding: 10px 14px; border: 1px solid #334155; border-radius: 8px; background: #0f172a; color: #e2e8f0; font-size: 14px; outline: none; }
        input:focus { border-color: #3b82f6; }
        .row { display: flex; gap: 12px; }
        .row .field { flex: 1; }
        .section { font-size: 15px; color: #3b82f6; margin: 24px 0 12px; font-weight: 600; }
        button { width: 100%; padding: 12px; background: #3b82f6; color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 20px; }
        button:hover { background: #2563eb; }
        button:disabled { background: #475569; cursor: not-allowed; }
        .msg { margin-top: 16px; padding: 12px; border-radius: 8px; font-size: 14px; }
        .msg.ok { background: #065f46; color: #6ee7b7; }
        .msg.err { background: #7f1d1d; color: #fca5a5; }
        .hint { font-size: 12px; color: #64748b; margin-top: 4px; }
    </style>
</head>
<body>
<div class="card">
    <h1>RohrApp+</h1>
    <p class="subtitle">Installationsassistent</p>

    <form id="installForm">
        <div class="section">Datenbank</div>
        <div class="row">
            <div class="field">
                <label>Host</label>
                <input name="db_host" value="127.0.0.1" required>
            </div>
            <div class="field" style="max-width:100px">
                <label>Port</label>
                <input name="db_port" value="3306" type="number" required>
            </div>
        </div>
        <div class="field">
            <label>Datenbankname</label>
            <input name="db_name" value="rohrapp" required>
            <div class="hint">Wird automatisch erstellt, falls nicht vorhanden</div>
        </div>
        <div class="row">
            <div class="field">
                <label>Benutzername</label>
                <input name="db_user" value="root" required>
            </div>
            <div class="field">
                <label>Passwort</label>
                <input name="db_pass" type="password">
            </div>
        </div>

        <div class="section">Admin-Konto</div>
        <div class="field">
            <label>E-Mail</label>
            <input name="admin_email" type="email" value="admin@rohrapp.de" required>
        </div>
        <div class="field">
            <label>Passwort</label>
            <input name="admin_password" type="password" value="admin123" required>
            <div class="hint">Mindestens 6 Zeichen</div>
        </div>

        <div class="section">Anwendung</div>
        <div class="field">
            <label>App URL</label>
            <input name="app_url" placeholder="https://rohrapp.de" value="">
            <div class="hint">Leer lassen für automatische Erkennung</div>
        </div>

        <button type="submit" id="btnInstall">Installieren</button>
    </form>

    <div id="msg" class="msg" style="display:none"></div>
</div>

<script>
document.getElementById('installForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnInstall');
    const msg = document.getElementById('msg');
    btn.disabled = true;
    btn.textContent = 'Installiere...';
    msg.style.display = 'none';

    try {
        const fd = new FormData(this);
        fd.append('step', 'install');

        const res = await fetch('install.php', { method: 'POST', body: fd });
        const data = await res.json();

        msg.style.display = 'block';
        if (data.success) {
            msg.className = 'msg ok';
            msg.innerHTML = data.message + '<br><br><a href="app.html" style="color:#6ee7b7;font-weight:600">Zum Panel &rarr;</a>';
            btn.style.display = 'none';
        } else {
            msg.className = 'msg err';
            msg.textContent = data.message;
            btn.disabled = false;
            btn.textContent = 'Installieren';
        }
    } catch (err) {
        msg.style.display = 'block';
        msg.className = 'msg err';
        msg.textContent = 'Fehler: ' + err.message;
        btn.disabled = false;
        btn.textContent = 'Installieren';
    }
});
</script>
</body>
</html>
