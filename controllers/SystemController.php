<?php
/**
 * SystemController — Version check & auto-update from GitHub
 */
class SystemController extends Controller
{
    private string $repoOwner = 'tekser46';
    private string $repoName  = 'RohrApp-v2';

    /**
     * GET /api/system/check-update
     */
    public function checkUpdate(array $params = []): void
    {
        $localVersion = $this->getLocalVersion();

        // Fetch remote version.json via GitHub API (no CDN cache)
        $url = "https://api.github.com/repos/{$this->repoOwner}/{$this->repoName}/contents/version.json?ref=main";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'RohrApp-Updater/1.0',
            CURLOPT_HTTPHEADER     => ['Accept: application/vnd.github.v3+json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            Response::error('Konnte Remote-Version nicht abrufen (HTTP ' . $httpCode . ')', 502);
        }

        $ghData = json_decode($response, true);
        $content = base64_decode($ghData['content'] ?? '');
        $remote = json_decode($content, true);
        if (!$remote) {
            Response::error('Version-Datei konnte nicht gelesen werden', 502);
        }
        $remoteVersion = $remote['version'] ?? '0.0.0';
        $updateAvailable = version_compare($remoteVersion, $localVersion, '>');

        Response::success([
            'local'            => $localVersion,
            'remote'           => $remoteVersion,
            'update_available' => $updateAvailable,
            'build'            => $remote['build'] ?? '',
            'changelog'        => $remote['changelog'] ?? '',
            'channel'          => $remote['channel'] ?? 'stable',
        ]);
    }

    /**
     * POST /api/system/do-update
     */
    public function doUpdate(array $params = []): void
    {
        $rootDir = dirname(__DIR__);
        $tmpFile = $rootDir . '/logs/update.zip';
        $tmpDir  = $rootDir . '/logs/update_tmp';

        // 1. Download zip from GitHub
        $zipUrl = "https://github.com/{$this->repoOwner}/{$this->repoName}/archive/refs/heads/main.zip";
        $ch = curl_init($zipUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'RohrApp-Updater/1.0',
        ]);
        $zipData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$zipData) {
            Response::error('Download fehlgeschlagen (HTTP ' . $httpCode . ')', 502);
        }

        file_put_contents($tmpFile, $zipData);

        // 2. Extract zip
        $zip = new ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            unlink($tmpFile);
            Response::error('ZIP konnte nicht geöffnet werden', 500);
        }

        // Clean old tmp
        if (is_dir($tmpDir)) {
            $this->deleteDir($tmpDir);
        }

        $zip->extractTo($tmpDir);
        $zip->close();

        // 3. Find extracted folder
        $extractedDir = null;
        foreach (scandir($tmpDir) as $d) {
            if ($d !== '.' && $d !== '..' && is_dir($tmpDir . '/' . $d)) {
                $extractedDir = $tmpDir . '/' . $d;
                break;
            }
        }

        if (!$extractedDir) {
            unlink($tmpFile);
            Response::error('Extrahierter Ordner nicht gefunden', 500);
        }

        // 4. Copy files — skip protected paths
        $skipPaths = ['logs', 'data', '.git', '.gitignore'];
        $skipFiles = ['config/database.php', 'config/app.php'];
        $updated = [];

        $this->copyUpdateFiles($extractedDir, $rootDir, $extractedDir, $updated, $skipPaths, $skipFiles);

        // 5. Cleanup
        unlink($tmpFile);
        $this->deleteDir($tmpDir);

        // 6. Log the update
        $newVersion = $this->getLocalVersion();
        $userId = Session::userId();
        $log = new ActivityLog();
        $log->create([
            'user_id'     => $userId,
            'action'      => 'system.update',
            'target_type' => 'system',
            'target_id'   => 0,
            'details'     => json_encode(['version' => $newVersion, 'files' => count($updated)]),
            'ip_address'  => Request::ip(),
        ]);

        Response::success([
            'version'       => $newVersion,
            'files_updated' => count($updated),
            'updated_files' => array_slice($updated, 0, 30),
        ], 'Update erfolgreich auf v' . $newVersion);
    }

    private function getLocalVersion(): string
    {
        $versionFile = dirname(__DIR__) . '/version.json';
        if (file_exists($versionFile)) {
            $v = json_decode(file_get_contents($versionFile), true);
            return $v['version'] ?? '0.0.0';
        }
        return '0.0.0';
    }

    private function copyUpdateFiles(string $src, string $dst, string $baseSrc, array &$updated, array $skipPaths, array $skipFiles): void
    {
        if (!is_dir($dst)) mkdir($dst, 0755, true);
        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            $relPath = ltrim(str_replace($baseSrc, '', $srcPath), '/\\');

            // Skip protected paths
            $skip = false;
            foreach ($skipPaths as $sp) {
                if (strpos($relPath, $sp) === 0) { $skip = true; break; }
            }
            if ($skip || in_array($relPath, $skipFiles)) continue;

            if (is_dir($srcPath)) {
                $this->copyUpdateFiles($srcPath, $dstPath, $baseSrc, $updated, $skipPaths, $skipFiles);
            } else {
                copy($srcPath, $dstPath);
                $updated[] = $relPath;
            }
        }
        closedir($dir);
    }

    private function deleteDir(string $dir): void
    {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath());
        }
        rmdir($dir);
    }
}
