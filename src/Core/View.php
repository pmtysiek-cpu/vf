<?php

declare(strict_types=1);

namespace VeloForum\Core;

final class View
{
    /** @var (callable(): array<string, mixed>)|null */
    private $sharedProvider = null;

    private static string $basePath = '';

    public function __construct(private readonly string $viewsPath, string $basePath = '')
    {
        self::setBasePath($basePath);
    }

    public static function setBasePath(string $basePath): void
    {
        $trimmed = '/' . trim($basePath, '/');
        self::$basePath = $trimmed === '/' ? '' : $trimmed;
    }

    public static function basePath(): string
    {
        return self::$basePath;
    }

    public static function url(string $path): string
    {
        if (self::$basePath === '' || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return $path;
        }
        if ($path === self::$basePath || str_starts_with($path, self::$basePath . '/')) {
            return $path;
        }
        return self::$basePath . $path;
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . self::url($path));
        exit;
    }

    /** @param callable(): array<string, mixed> $provider */
    public function setSharedProvider(callable $provider): void
    {
        $this->sharedProvider = $provider;
    }

    public function render(string $template, array $data = []): void
    {
        $templateFile = $this->viewsPath . '/' . ltrim($template, '/') . '.php';
        if (!is_file($templateFile)) {
            throw new \RuntimeException("Nie znaleziono szablonu widoku: {$template}");
        }

        if ($this->sharedProvider !== null) {
            $shared = ($this->sharedProvider)();
            if (is_array($shared)) {
                $data = $data + $shared;
            }
        }
        $data['basePath'] = self::$basePath;
        $rawPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        if (self::$basePath !== '' && str_starts_with($rawPath, self::$basePath)) {
            $rawPath = substr($rawPath, strlen(self::$basePath)) ?: '/';
        }
        $data['currentPath'] = '/' . ltrim($rawPath, '/');

        extract($data, EXTR_SKIP);
        ob_start();
        require $templateFile;
        $content = (string) ob_get_clean();

        $layoutFile = $this->viewsPath . '/layout.php';
        ob_start();
        require $layoutFile;
        $html = (string) ob_get_clean();

        if (self::$basePath !== '') {
            $base = self::$basePath;
            $html = preg_replace_callback(
                '/\b(href|action|src)="(\/(?!\/)[^"]*)"/i',
                static function (array $m) use ($base): string {
                    $url = $m[2];
                    if ($url === $base || str_starts_with($url, $base . '/')) {
                        return $m[0];
                    }
                    return $m[1] . '="' . $base . $url . '"';
                },
                $html
            ) ?? $html;
        }

        echo $html;
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function isOnline(?int $lastActivityAt, int $windowSeconds = 1200): bool
    {
        if ($lastActivityAt === null || $lastActivityAt <= 0) {
            return false;
        }
        return (time() - $lastActivityAt) <= $windowSeconds;
    }

    public static function formatRelativeTime(?int $timestamp): string
    {
        if ($timestamp === null || $timestamp <= 0) {
            return 'brak danych';
        }

        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'przed chwilą';
        }
        if ($diff < 3600) {
            $mins = (int) floor($diff / 60);
            return $mins . ' min temu';
        }
        if ($diff < 86400 && date('Y-m-d', $timestamp) === date('Y-m-d')) {
            $hours = (int) floor($diff / 3600);
            return $hours . ' godz. temu';
        }
        if (date('Y-m-d', $timestamp) === date('Y-m-d', strtotime('-1 day'))) {
            return 'wczoraj o ' . date('H:i', $timestamp);
        }

        return date('d.m.Y H:i', $timestamp);
    }

    public static function presenceText(?int $lastActivityAt, ?int $joinedAt = null): string
    {
        $ts = ($lastActivityAt !== null && $lastActivityAt > 0) ? $lastActivityAt : $joinedAt;
        if (self::isOnline($ts)) {
            return 'Aktywny teraz';
        }
        if ($ts === null || $ts <= 0) {
            return 'Offline';
        }
        return 'Ostatnio: ' . self::formatRelativeTime($ts);
    }

    /**
     * @return array{label: string, color: string, next_at: int|null, pct: int}
     */
    public static function reputationRank(int $points): array
    {
        if ($points >= 150) {
            return ['label' => 'Weteran społeczności', 'color' => '#d97706', 'next_at' => null, 'pct' => 100];
        }
        if ($points >= 50) {
            $pct = (int) min(100, max(5, round((($points - 50) / 100) * 100)));
            return ['label' => 'Ekspert działu', 'color' => '#7c3aed', 'next_at' => 150, 'pct' => $pct];
        }
        if ($points >= 15) {
            $pct = (int) min(100, max(5, round((($points - 15) / 35) * 100)));
            return ['label' => 'Doświadczony', 'color' => '#059669', 'next_at' => 50, 'pct' => $pct];
        }
        if ($points >= 3) {
            $pct = (int) min(100, max(5, round((($points - 3) / 12) * 100)));
            return ['label' => 'Aktywny członek', 'color' => '#2563eb', 'next_at' => 15, 'pct' => $pct];
        }
        $pct = (int) min(100, max(4, round((max(0, $points) / 3) * 100)));
        return ['label' => 'Nowy uczestnik', 'color' => '#64748b', 'next_at' => 3, 'pct' => $pct];
    }

    public static function safeGroupColor(?string $hex, string $fallback = '#475569'): string
    {
        $clean = trim((string) $hex);
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $clean) === 1) {
            return $clean;
        }
        return $fallback;
    }

    public static function sanitizeGroupAffix(string $html): string
    {
        $trimmed = trim($html);
        if ($trimmed === '') {
            return '';
        }
        $clean = strip_tags($trimmed, '<span><strong><b><i><em><u><small>');
        $clean = preg_replace('/\son[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $clean) ?? $clean;
        $clean = preg_replace('/javascript\s*:/iu', '', $clean) ?? $clean;
        return $clean;
    }

    public static function formatUsername(
        string $username,
        ?string $colorHex = null,
        ?string $prefixHtml = null,
        ?string $suffixHtml = null,
        ?string $groupName = null
    ): string {
        $escapedName = self::e($username);
        $safeColor = self::safeGroupColor($colorHex, '');
        $titleAttr = $groupName !== null && trim($groupName) !== ''
            ? ' title="Grupa: ' . self::e(trim($groupName)) . '"'
            : '';

        $prefix = self::sanitizeGroupAffix((string) $prefixHtml);
        $suffix = self::sanitizeGroupAffix((string) $suffixHtml);

        if ($prefix !== '' && $suffix !== '') {
            $openTags = [];
            if (preg_match_all('/<(span|strong|b|i|em|u|small)\b/i', $prefix, $mOpen)) {
                $openTags = $mOpen[1];
            }
            $closeCount = preg_match_all('/<\/(span|strong|b|i|em|u|small)>/i', $suffix);
            if (count($openTags) === $closeCount) {
                $styleAttr = $safeColor !== '' ? ' style="color:' . self::e($safeColor) . ';"' : '';
                return '<span class="vf-user-formatted"' . $styleAttr . $titleAttr . '>' . $prefix . $escapedName . $suffix . '</span>';
            }
        }

        $styleAttr = $safeColor !== ''
            ? ' style="color:' . self::e($safeColor) . ';font-weight:700;"'
            : ' style="font-weight:700;"';

        return '<span class="vf-user-formatted"' . $styleAttr . $titleAttr . '>' . $escapedName . '</span>';
    }

    public static function safeMediaUrl(?string $url): ?string
    {
        $raw = trim((string) $url);
        if ($raw === '') {
            return null;
        }
        if (str_starts_with($raw, '/') && !str_starts_with($raw, '//')) {
            return $raw;
        }
        if (preg_match('#^https?://[^\s"\'<>]+$#i', $raw) === 1) {
            return $raw;
        }
        return null;
    }

    public static function extractYoutubeId(?string $url): ?string
    {
        $raw = trim((string) $url);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $raw) === 1) {
            return $raw;
        }
        if (preg_match('#^(?:https?://)?(?:www\.|m\.|music\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})#i', $raw, $m) === 1) {
            return $m[1];
        }
        return null;
    }

    public static function achievementIconSvg(string $icon): string
    {
        return match ($icon) {
            'star'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
            'flame'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>',
            'zap'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
            'award'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>',
            'heart'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
            'crown'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/></svg>',
            default  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>',
        };
    }
}
