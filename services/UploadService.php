<?php
/**
 * UploadService — File upload handling
 */
class UploadService
{
    /**
     * Upload a file from $_FILES
     * @param string $fieldName  Form field name (e.g. 'avatar')
     * @param string $subDir     Subdirectory (e.g. 'avatars', 'logos')
     * @param int    $userId     Owner user ID
     * @return string  Relative path to uploaded file
     * @throws Exception on failure
     */
    public static function upload(string $fieldName, string $subDir, int $userId): string
    {
        $config = require dirname(__DIR__) . '/config/app.php';

        if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Keine Datei hochgeladen oder Upload-Fehler.');
        }

        $file = $_FILES[$fieldName];

        // Check MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $config['upload_allowed'])) {
            throw new Exception('Dateityp nicht erlaubt. Erlaubt: JPG, PNG, GIF, WebP.');
        }

        // Check size
        if ($file['size'] > $config['upload_max_size']) {
            throw new Exception('Datei zu groß. Maximum: ' . ($config['upload_max_size'] / 1024 / 1024) . 'MB.');
        }

        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $ext = strtolower(preg_replace('/[^a-z0-9]/i', '', $ext));
        $filename = $subDir . '_' . $userId . '_' . time() . '.' . $ext;

        $uploadDir = $config['upload_path'] . '/' . $subDir;
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $destPath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new Exception('Upload fehlgeschlagen.');
        }

        // Return relative path (from public/ perspective — uploads is inside public/)
        return 'uploads/' . $subDir . '/' . $filename;
    }

    /**
     * Delete a previously uploaded file
     */
    public static function deleteFile(string $relativePath): void
    {
        if (!$relativePath) return;
        $config = require dirname(__DIR__) . '/config/app.php';
        $rootDir = dirname(__DIR__);
        // Try from upload_path config
        $absPath = $rootDir . '/' . ltrim(str_replace('../', '', $relativePath), '/');
        if (file_exists($absPath)) unlink($absPath);
    }
}
