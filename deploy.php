<?php
/**
 * RohrApp+ — Remote Deployer
 * Upload this SINGLE file to your hosting via FTP
 * Run it in browser: https://yourdomain.com/deploy.php
 * It downloads the latest release from GitHub and extracts it
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

$repo = 'tekser46/RohrApp-v2';
$branch = 'main';
$step = $_POST['step'] ?? $_GET['step'] ?? '';

// Security: simple deploy key
$deployKey = 'rohrapp2026';
$inputKey = $_POST['key'] ?? $_GET['key'] ?? '';

if ($step === 'download' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if ($inputKey !== $deployKey) {
        echo json_encode(['success' => false, 'message' => 'Falscher Deploy-Key']);
        exit;
    }

    try {
        $zipUrl = "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip";
        $zipFile = __DIR__ . '/deploy_temp.zip';
        $extractDir = __DIR__ . '/deploy_temp';

        // Download zip
        $ch = curl_init($zipUrl);
        $fp = fopen($zipFile, 'w');
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'RohrApp-Deploy/1.0',
        ]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$result || $httpCode !== 200 || filesize($zipFile) < 1000) {
            @unlink($zipFile);
            throw new Exception("Download fehlgeschlagen (HTTP {$httpCode})");
        }

        // Extract zip
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            throw new Exception('ZIP konnte nicht geöffnet werden');
        }

        // Extract to temp dir
        if (is_dir($extractDir)) {
            deleteDir($extractDir);
        }
        mkdir($extractDir, 0755, true);
        $zip->extractTo($extractDir);
        $zip->close();

        // Find the inner folder (GitHub adds repo-branch/ prefix)
        $innerDirs = glob($extractDir . '/*', GLOB_ONLYDIR);
        $sourceDir = $innerDirs[0] ?? $extractDir;

        // Files/dirs to SKIP (don't overwrite)
        $skip = ['config/database.php', 'config/app.php', 'logs', 'uploads', 'deploy.php', 'install.php'];

        // Copy files
        $copied = 0;
        $skipped = 0;
        copyDirectory($sourceDir, __DIR__, $skip, $copied, $skipped);

        // Cleanup
        @unlink($zipFile);
        deleteDir($extractDir);

        echo json_encode([
            'success' => true,
            'message' => "Deployment erfolgreich! {$copied} Dateien aktualisiert, {$skipped} übersprungen."
        ]);
    } catch (Exception $e) {
        @unlink($zipFile ?? '');
        if (isset($extractDir) && is_dir($extractDir)) deleteDir($extractDir);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

function copyDirectory(string $src, string $dst, array $skip, int &$copied, int &$skipped): void {
    $dir = opendir($src);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..' || $file === '.git') continue;

        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        $relativePath = ltrim(str_replace(__DIR__, '', $dstPath), '/\\');

        // Check skip list
        $shouldSkip = false;
        foreach ($skip as $skipItem) {
            if ($relativePath === $skipItem || str_starts_with($relativePath, $skipItem . '/')) {
                $shouldSkip = true;
                break;
            }
        }

        if ($shouldSkip) {
            $skipped++;
            continue;
        }

        if (is_dir($srcPath)) {
            if (!is_dir($dstPath)) mkdir($dstPath, 0755, true);
            copyDirectory($srcPath, $dstPath, $skip, $copied, $skipped);
        } else {
            copy($srcPath, $dstPath);
            $copied++;
        }
    }
    closedir($dir);
}

function deleteDir(string $dir): void {
    if (!is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RohrApp+ Deploy</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, sans-serif; background: #0a1628; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { background: #1e293b; border-radius: 16px; padding: 40px; max-width: 480px; width: 90%; }
        h1 { color: #3b82f6; font-size: 24px; margin-bottom: 20px; }
        .field { margin-bottom: 16px; }
        label { display: block; font-size: 13px; color: #94a3b8; margin-bottom: 4px; }
        input { width: 100%; padding: 10px; border: 1px solid #334155; border-radius: 8px; background: #0f172a; color: #e2e8f0; font-size: 14px; }
        button { width: 100%; padding: 12px; background: #3b82f6; color: #fff; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; margin-top: 10px; }
        button:hover { background: #2563eb; }
        button:disabled { background: #475569; }
        .msg { margin-top: 16px; padding: 12px; border-radius: 8px; font-size: 14px; display: none; }
        .msg.ok { background: #065f46; color: #6ee7b7; }
        .msg.err { background: #7f1d1d; color: #fca5a5; }
        .hint { font-size: 12px; color: #64748b; margin-top: 8px; }
    </style>
</head>
<body>
<div class="card">
    <h1>RohrApp+ Deploy</h1>
    <p style="color:#94a3b8;margin-bottom:20px">GitHub'dan son sürümü indir ve kur</p>

    <form id="deployForm">
        <div class="field">
            <label>Deploy Key</label>
            <input name="key" type="password" required placeholder="Deploy-Schlüssel eingeben">
        </div>
        <button type="submit" id="btn">Deploy starten</button>
    </form>

    <div id="msg" class="msg"></div>
    <p class="hint">Repo: <?= htmlspecialchars($repo) ?> (<?= htmlspecialchars($branch) ?> branch)<br>
    Config-Dateien (database.php, app.php) und uploads/ werden NICHT überschrieben.</p>
</div>

<script>
document.getElementById('deployForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btn');
    const msg = document.getElementById('msg');
    btn.disabled = true;
    btn.textContent = 'Downloading...';
    msg.style.display = 'none';
    try {
        const fd = new FormData(this);
        fd.append('step', 'download');
        const res = await fetch('deploy.php', { method: 'POST', body: fd });
        const data = await res.json();
        msg.style.display = 'block';
        if (data.success) {
            msg.className = 'msg ok';
            msg.innerHTML = data.message + '<br><a href="app.html" style="color:#6ee7b7">Zum Panel →</a>';
        } else {
            msg.className = 'msg err';
            msg.textContent = data.message;
        }
    } catch (err) {
        msg.style.display = 'block';
        msg.className = 'msg err';
        msg.textContent = 'Fehler: ' + err.message;
    }
    btn.disabled = false;
    btn.textContent = 'Deploy starten';
});
</script>
</body>
</html>
