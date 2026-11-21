<?php

declare(strict_types=1);

namespace VeloForum\Service;

/**
 * Bezpieczna obsługa uploadu avatarów użytkowników oraz załączników do postów.
 */
final class UploadService
{
    private const AVATAR_MAX_BYTES = 2097152; // 2 MB
    private const COVER_MAX_BYTES = 4194304;  // 4 MB
    private const ATTACH_MAX_BYTES = 5242880; // 5 MB na plik
    private const ATTACH_MAX_COUNT = 5;

    /** @var array<string, string> */
    private const IMAGE_MIME_MAP = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    /** @var array<string, string[]> */
    private const ATTACHMENT_EXT_MIMES = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'gif'  => ['image/gif'],
        'pdf'  => ['application/pdf'],
        'txt'  => ['text/plain'],
        'log'  => ['text/plain'],
        'cfg'  => ['text/plain'],
        'ini'  => ['text/plain'],
        'json' => ['application/json', 'text/plain'],
        'xml'  => ['application/xml', 'text/xml', 'text/plain'],
        'sql'  => ['text/plain', 'application/sql'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
    ];

    /**
     * @return array{ok: bool, url?: string, error?: string}
     */
    public function uploadAvatar(array $file, int $memberId, string $publicRoot): array
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Nie wybrano pliku.'];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Błąd przesyłania avatara (kod ' . $err . ').'];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'Nieprawidłowy plik tymczasowy.'];
        }
        if ($size <= 0 || $size > self::AVATAR_MAX_BYTES) {
            return ['ok' => false, 'error' => 'Avatar może mieć maksymalnie 2 MB.'];
        }

        $mime = $this->detectMime($tmp);
        if (!isset(self::IMAGE_MIME_MAP[$mime])) {
            return ['ok' => false, 'error' => 'Dozwolone formaty avatara to JPG, PNG, WebP lub GIF.'];
        }

        $imgInfo = @getimagesize($tmp);
        if ($imgInfo === false || ($imgInfo[0] ?? 0) < 1 || ($imgInfo[1] ?? 0) < 1) {
            return ['ok' => false, 'error' => 'Plik nie jest prawidłowym obrazem.'];
        }

        $dir = rtrim($publicRoot, '/\\') . '/uploads/avatars';
        if (!$this->ensureDirectory($dir)) {
            return ['ok' => false, 'error' => 'Katalog uploads/avatars nie jest zapisywalny.'];
        }

        $ext = self::IMAGE_MIME_MAP[$mime];
        $filename = sprintf('av_%d_%s.%s', $memberId, bin2hex(random_bytes(6)), $ext);
        $dest = $dir . '/' . $filename;

        if (!@move_uploaded_file($tmp, $dest)) {
            return ['ok' => false, 'error' => 'Nie udało się zapisać pliku na serwerze.'];
        }
        @chmod($dest, 0644);

        return ['ok' => true, 'url' => '/uploads/avatars/' . $filename];
    }

    /**
     * @return array{ok: bool, url?: string, error?: string}
     */
    public function uploadCover(array $file, int $memberId, string $publicRoot): array
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Nie wybrano pliku tła.'];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Błąd przesyłania tła profilowego (kod ' . $err . ').'];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'Nieprawidłowy plik tymczasowy.'];
        }
        if ($size <= 0 || $size > self::COVER_MAX_BYTES) {
            return ['ok' => false, 'error' => 'Tło profilowe może mieć maksymalnie 4 MB.'];
        }

        $mime = $this->detectMime($tmp);
        if (!isset(self::IMAGE_MIME_MAP[$mime])) {
            return ['ok' => false, 'error' => 'Dozwolone formaty tła to JPG, PNG, WebP lub GIF.'];
        }

        $imgInfo = @getimagesize($tmp);
        if ($imgInfo === false || ($imgInfo[0] ?? 0) < 1 || ($imgInfo[1] ?? 0) < 1) {
            return ['ok' => false, 'error' => 'Plik nie jest prawidłowym obrazem.'];
        }

        $dir = rtrim($publicRoot, '/\\') . '/uploads/covers';
        if (!$this->ensureDirectory($dir)) {
            return ['ok' => false, 'error' => 'Katalog uploads/covers nie jest zapisywalny.'];
        }

        $ext = self::IMAGE_MIME_MAP[$mime];
        $filename = sprintf('cov_%d_%s.%s', $memberId, bin2hex(random_bytes(6)), $ext);
        $dest = $dir . '/' . $filename;

        if (!@move_uploaded_file($tmp, $dest)) {
            return ['ok' => false, 'error' => 'Nie udało się zapisać pliku tła na serwerze.'];
        }
        @chmod($dest, 0644);

        return ['ok' => true, 'url' => '/uploads/covers/' . $filename];
    }

    /**
     * @return array<int, array{original_name: string, stored_path: string, mime_type: string, file_size: int, is_image: int}>
     */
    public function uploadPostAttachments(array $files, int $memberId, string $publicRoot): array
    {
        if (!isset($files['name']) || !is_array($files['name'])) {
            return [];
        }

        $dir = rtrim($publicRoot, '/\\') . '/uploads/attachments';
        if (!$this->ensureDirectory($dir)) {
            return [];
        }

        $saved = [];
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if (count($saved) >= self::ATTACH_MAX_COUNT) {
                break;
            }
            $err = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err !== UPLOAD_ERR_OK) {
                continue;
            }
            $tmp = (string) ($files['tmp_name'][$i] ?? '');
            $size = (int) ($files['size'][$i] ?? 0);
            $rawName = (string) ($files['name'][$i] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0 || $size > self::ATTACH_MAX_BYTES) {
                continue;
            }

            $baseName = trim(basename(str_replace('\\', '/', $rawName)));
            $ext = strtolower((string) pathinfo($baseName, PATHINFO_EXTENSION));
            if ($ext === '' || !isset(self::ATTACHMENT_EXT_MIMES[$ext])) {
                continue;
            }

            $mime = $this->detectMime($tmp);
            $allowedMimes = self::ATTACHMENT_EXT_MIMES[$ext];
            if ($mime !== 'application/octet-stream' && !in_array($mime, $allowedMimes, true)) {
                continue;
            }
            if ($mime === 'application/octet-stream') {
                $mime = $allowedMimes[0];
            }

            $isImage = isset(self::IMAGE_MIME_MAP[$mime]) ? 1 : 0;
            if ($isImage === 1) {
                $imgInfo = @getimagesize($tmp);
                if ($imgInfo === false || ($imgInfo[0] ?? 0) < 1 || ($imgInfo[1] ?? 0) < 1) {
                    continue;
                }
            }

            $safeOriginal = preg_replace('/[^\p{L}\p{N}._\-\s]+/u', '_', $baseName) ?? ('plik.' . $ext);
            $safeOriginal = mb_substr(trim($safeOriginal), 0, 180) ?: ('plik.' . $ext);

            $storedFilename = sprintf('att_%d_%d_%s.%s', $memberId, time(), bin2hex(random_bytes(5)), $ext);
            $dest = $dir . '/' . $storedFilename;

            if (!@move_uploaded_file($tmp, $dest)) {
                continue;
            }
            @chmod($dest, 0644);

            $saved[] = [
                'original_name' => $safeOriginal,
                'stored_path'   => '/uploads/attachments/' . $storedFilename,
                'mime_type'     => $mime,
                'file_size'     => $size,
                'is_image'      => $isImage,
            ];
        }

        return $saved;
    }

    public function removeLocalFile(string $publicRoot, ?string $relativeUrl): void
    {
        if ($relativeUrl === null || !str_starts_with($relativeUrl, '/uploads/')) {
            return;
        }
        $cleanRel = str_replace(['..', '\\'], '', $relativeUrl);
        $full = rtrim($publicRoot, '/\\') . $cleanRel;
        $realUploads = realpath(rtrim($publicRoot, '/\\') . '/uploads');
        $realTarget = realpath($full);
        if ($realUploads !== false && $realTarget !== false && str_starts_with($realTarget, $realUploads) && is_file($realTarget)) {
            @unlink($realTarget);
        }
    }

    private function detectMime(string $tmpPath): string
    {
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string) @finfo_file($finfo, $tmpPath);
                @finfo_close($finfo);
                if ($mime !== '') {
                    return strtolower(trim(explode(';', $mime)[0]));
                }
            }
        }
        if (function_exists('mime_content_type')) {
            $mime = (string) @mime_content_type($tmpPath);
            if ($mime !== '') {
                return strtolower(trim(explode(';', $mime)[0]));
            }
        }
        return 'application/octet-stream';
    }

    private function ensureDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return is_dir($dir) && is_writable($dir);
    }
}
