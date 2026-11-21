<?php

declare(strict_types=1);

use VeloForum\Controller\AdminController;
use VeloForum\Controller\AuthController;
use VeloForum\Controller\ChatController;
use VeloForum\Controller\ForumController;
use VeloForum\Controller\MessageController;
use VeloForum\Controller\MigratorController;
use VeloForum\Controller\ModController;
use VeloForum\Controller\NotificationController;
use VeloForum\Controller\SeoController;
use VeloForum\Controller\TopicController;
use VeloForum\Core\Autoloader;
use VeloForum\Core\Database;
use VeloForum\Core\Router;
use VeloForum\Core\View;
use VeloForum\Repository\AchievementRepository;
use VeloForum\Repository\AdminRepository;
use VeloForum\Repository\ChatRepository;
use VeloForum\Repository\ForumRepository;
use VeloForum\Repository\MemberRepository;
use VeloForum\Repository\MessageRepository;
use VeloForum\Repository\ModRepository;
use VeloForum\Repository\NotificationRepository;
use VeloForum\Repository\PresenceRepository;
use VeloForum\Repository\ReactionRepository;
use VeloForum\Repository\TopicRepository;
use VeloForum\Service\AuthService;
use VeloForum\Service\ForumTreeService;
use VeloForum\Service\MentionService;
use VeloForum\Service\PermissionService;
use VeloForum\Service\SeoService;
use VeloForum\Service\UploadService;

$root = dirname(__DIR__);
require_once $root . '/src/Core/Autoloader.php';
Autoloader::register($root . '/src');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (
    extension_loaded('zlib')
    && !headers_sent()
    && !ini_get('zlib.output_compression')
    && str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '')), 'gzip')
    && ob_get_level() === 0
) {
    @ob_start('ob_gzhandler');
}

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_samesite', 'Lax');
    if ($isHttps) {
        @ini_set('session.cookie_secure', '1');
    }
    @session_start();
}

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$scriptDir = rtrim(dirname($scriptName), '/');
if (str_ends_with($scriptDir, '/public')) {
    $basePath = substr($scriptDir, 0, -7);
} elseif ($scriptDir === '/public') {
    $basePath = '';
} else {
    $basePath = $scriptDir;
}
$basePath = ($basePath === '/' || $basePath === '.') ? '' : $basePath;

$configLoader = require $root . '/config/database.php';
$config = $configLoader($root);

$writeLog = static function (string $message) use ($root): void {
    error_log('[VeloForum] ' . $message);
    $logDir = $root . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    if (is_dir($logDir) && is_writable($logDir)) {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($logDir . '/veloforum.log', $line, FILE_APPEND | LOCK_EX);
    }
};

$renderDiagnostic = static function (string $title, string $summary, array $steps, string $techHint = ''): never {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html lang="pl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . $e($title) . ' — VeloForum</title>'
        . '<style>body{margin:0;padding:32px 16px;font-family:system-ui,-apple-system,sans-serif;background:#f8fafc;color:#0f172a;line-height:1.55}'
        . '.box{max-width:720px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:28px;box-shadow:0 4px 20px rgba(15,23,42,.05)}'
        . 'h1{margin:0 0 8px;font-size:1.375rem;color:#0f172a}p{margin:0 0 16px;color:#475569}'
        . 'ul{margin:0 0 18px;padding-left:20px;color:#334155}li{margin-bottom:8px}'
        . 'code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:0.875em;color:#0f172a}'
        . '.hint{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;padding:12px 14px;border-radius:8px;font-size:0.875rem;word-break:break-word}</style></head><body>'
        . '<div class="box"><h1>' . $e($title) . '</h1><p>' . $e($summary) . '</p><ul>';
    foreach ($steps as $step) {
        echo '<li>' . $step . '</li>';
    }
    echo '</ul>';
    if ($techHint !== '') {
        echo '<div class="hint"><strong>Szczegóły diagnostyczne:</strong> ' . $e($techHint) . '</div>';
    }
    echo '</div></body></html>';
    exit;
};

set_exception_handler(static function (\Throwable $e) use ($config, $writeLog): void {
    $writeLog($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    if (($config['app']['env'] ?? 'production') === 'local') {
        echo '<pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo '<div style="font-family:system-ui,sans-serif;max-width:640px;margin:40px auto;padding:24px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;color:#0f172a;">'
            . '<h2 style="margin:0 0 8px;">Wystąpił wewnętrzny błąd serwera</h2>'
            . '<p style="margin:0;color:#475569;">Szczegóły zostały zapisane w logach serwera oraz w pliku <code>storage/logs/veloforum.log</code> (jeśli katalog jest zapisywalny). '
            . 'Na czas diagnostyki możesz ustawić <code>APP_ENV="local"</code> w pliku <code>.env</code>.</p></div>';
    }
});

$missingExt = [];
foreach (['pdo', 'pdo_mysql', 'mbstring', 'json'] as $ext) {
    if (!extension_loaded($ext)) {
        $missingExt[] = $ext;
    }
}
if ($missingExt !== []) {
    $writeLog('Brak wymaganych rozszerzeń PHP: ' . implode(', ', $missingExt));
    $renderDiagnostic(
        'Brak wymaganych rozszerzeń PHP',
        'Serwer WWW uruchomił PHP (' . PHP_VERSION . '), ale brakuje modułów wymaganych przez VeloForum.',
        [
            'Włącz w panelu hostingu (DirectAdmin / cPanel / Select PHP Version) brakujące rozszerzenia: <code>' . htmlspecialchars(implode(', ', $missingExt), ENT_QUOTES, 'UTF-8') . '</code>.',
            'Zalecana wersja PHP to <code>8.3</code> lub nowsza.',
        ]
    );
}

if (empty($config['env_exists'])) {
    $writeLog('Brak pliku .env w katalogu głównym.');
    $renderDiagnostic(
        'Brak pliku konfiguracyjnego .env',
        'Skrypt został uruchomiony, ale w katalogu głównym brakuje pliku .env z danymi połączenia do bazy MySQL.',
        [
            'Skopiuj plik <code>.env.example</code> jako <code>.env</code> w katalogu głównym skryptu.',
            'Uzupełnij dane połączenia do bazy danych: <code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_DATABASE</code>, <code>DB_USERNAME</code>, <code>DB_PASSWORD</code>.',
            'Zaimportuj plik <code>database/schema.sql</code> w phpMyAdmin.',
        ]
    );
}

try {
    $db = Database::target($config['target']);
    Database::ensureCompatibleSchema($db, $root . '/database/schema.sql');
} catch (\Throwable $dbEx) {
    $msg = $dbEx->getMessage();
    $writeLog('Błąd bazy danych/schematu: ' . $msg);

    if (str_starts_with($msg, 'SCHEMA_MISSING:')) {
        $renderDiagnostic(
            'Wymagany import schematu bazy danych',
            'Połączenie z serwerem MySQL działa prawidłowo, ale docelowa baza nie zawiera jeszcze kompletnego schematu VeloForum.',
            [
                'Otwórz <strong>phpMyAdmin</strong> i wybierz bazę <code>' . htmlspecialchars((string) ($config['target']['database'] ?? ''), ENT_QUOTES, 'UTF-8') . '</code>.',
                'Zaimportuj plik <code>database/schema.sql</code> z paczki VeloForum.',
                'Odśwież tę stronę — forum i kreator migracji IC5 uruchomią się automatycznie.',
            ],
            trim(substr($msg, 15))
        );
    }

    $safeHint = 'Sprawdź dane w pliku .env (DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD).';
    if (stripos($msg, 'Access denied') !== false || str_contains($msg, '[1045]')) {
        $safeHint = 'Błąd autoryzacji MySQL (1045): nieprawidłowy użytkownik (DB_USERNAME), hasło (DB_PASSWORD) lub brak uprawnień dostępu z tego hosta.';
    } elseif (stripos($msg, 'Unknown database') !== false || str_contains($msg, '[1049]')) {
        $safeHint = 'Błąd bazy danych (1049): baza wskazana w DB_DATABASE nie istnieje na serwerze MySQL.';
    } elseif (stripos($msg, 'Connection refused') !== false || stripos($msg, 'timed out') !== false || str_contains($msg, '[2002]')) {
        $safeHint = 'Nie można połączyć z hostem MySQL (2002): na hostingu współdzielonym spróbuj ustawić DB_HOST="localhost" lub DB_HOST="127.0.0.1" zamiast zewnętrznego IP (albo upewnij się, że port 3306 jest otwarty).';
    }

    $renderDiagnostic(
        'Nie można połączyć z bazą danych MySQL',
        'Wystąpił problem z nawiązaniem połączenia z bazą docelową VeloForum.',
        [
            'Zweryfikuj ustawienia w pliku <code>.env</code> (host: <code>' . htmlspecialchars((string) ($config['target']['host'] ?? ''), ENT_QUOTES, 'UTF-8') . '</code>, baza: <code>' . htmlspecialchars((string) ($config['target']['database'] ?? ''), ENT_QUOTES, 'UTF-8') . '</code>).',
            'Jeśli MySQL działa na tym samym serwerze WWW, zmień <code>DB_HOST</code> na <code>localhost</code> lub <code>127.0.0.1</code>.',
            'Pełny komunikat błędu PDO został zapisany w logach oraz w <code>storage/logs/veloforum.log</code>.',
        ],
        $safeHint
    );
}

$view = new View($root . '/views', $basePath);

$forumRepo       = new ForumRepository($db);
$topicRepo       = new TopicRepository($db);
$memberRepo      = new MemberRepository($db);
$reactionRepo    = new ReactionRepository($db);
$notifRepo       = new NotificationRepository($db);
$adminRepo       = new AdminRepository($db);
$modRepo         = new ModRepository($db);
$presenceRepo    = new PresenceRepository($db);
$messageRepo     = new MessageRepository($db);
$achievementRepo = new AchievementRepository($db);
$chatRepo        = new ChatRepository($db);

$view->setSharedProvider(static function () use ($adminRepo, $messageRepo): array {
    $sessionMemberId = (int) ($_SESSION['vf_member_id'] ?? 0);
    return [
        'siteSettings'        => $adminRepo->settings(),
        'globalAnnouncements' => $adminRepo->activeAnnouncements(),
        'unreadMessagesCount' => $sessionMemberId > 0 ? $messageRepo->unreadCountForMember($sessionMemberId) : 0,
    ];
});

$canonicalBase = rtrim((string) ($config['app']['url'] ?? ''), '/');
if ($basePath !== '' && $canonicalBase !== '' && !str_ends_with($canonicalBase, $basePath)) {
    $canonicalBase .= $basePath;
}

$treeService       = new ForumTreeService();
$authService       = new AuthService($db);
$seoService        = new SeoService($config['app']['name'], $canonicalBase !== '' ? $canonicalBase : 'http://localhost');
$mentionService    = new MentionService($memberRepo);
$permissionService = new PermissionService($db);
$uploadService     = new UploadService();
$publicRoot        = $root . '/public';

$forumCtrl = new ForumController($view, $forumRepo, $topicRepo, $memberRepo, $notifRepo, $treeService, $authService, $seoService, $reactionRepo, $permissionService, $adminRepo, $presenceRepo, $uploadService, $publicRoot, $messageRepo, $achievementRepo, $chatRepo);
$topicCtrl = new TopicController($view, $forumRepo, $topicRepo, $reactionRepo, $notifRepo, $treeService, $authService, $seoService, $mentionService, $permissionService, $adminRepo, $modRepo, $presenceRepo, $uploadService, $publicRoot, $achievementRepo);
$msgCtrl   = new MessageController($view, $messageRepo, $memberRepo, $notifRepo, $authService, $seoService, $permissionService, $presenceRepo);
$chatCtrl  = new ChatController($chatRepo, $adminRepo, $authService, $permissionService);
$authCtrl  = new AuthController($view, $authService);
$notifCtrl = new NotificationController($view, $notifRepo, $authService, $seoService);
$seoCtrl   = new SeoController($db, $canonicalBase !== '' ? $canonicalBase : 'http://localhost');
$adminCtrl = new AdminController($view, $adminRepo, $forumRepo, $reactionRepo, $notifRepo, $authService, $seoService, $publicRoot, $achievementRepo, $chatRepo);
$modCtrl   = new ModController($view, $modRepo, $adminRepo, $forumRepo, $topicRepo, $notifRepo, $authService, $seoService, $permissionService);
$migCtrl   = new MigratorController($view, $db, $config['ic5'], $authService);

$router = new Router($basePath);
$router->get('/', static fn () => $forumCtrl->index());
$router->post('/mark-all-read', static fn () => $forumCtrl->markAllRead());
$router->get('/activity', static fn () => $forumCtrl->activityFeed());
$router->get('/members', static fn () => $forumCtrl->membersDirectory());
$router->get('/bookmarks', static fn () => $forumCtrl->bookmarks());
$router->get('/tag/{slug}', static fn (array $p) => $forumCtrl->byTag($p));
$router->get('/search', static fn () => $forumCtrl->search());
$router->get('/robots.txt', static fn () => $seoCtrl->robots());
$router->get('/sitemap.xml', static fn () => $seoCtrl->sitemap());

$router->get('/forum/{id}', static fn (array $p) => $forumCtrl->show($p));
$router->get('/forum/{id}-{slug}', static fn (array $p) => $forumCtrl->show($p));
$router->get('/topic/{id}', static fn (array $p) => $topicCtrl->show($p));
$router->get('/topic/{id}-{slug}', static fn (array $p) => $topicCtrl->show($p));
$router->get('/member/{id}', static fn (array $p) => $forumCtrl->memberProfile($p));
$router->get('/member/{id}-{slug}', static fn (array $p) => $forumCtrl->memberProfile($p));
$router->post('/member/profile', static fn () => $forumCtrl->updateProfile());
$router->get('/api/leaderboards', static fn () => $forumCtrl->apiLeaderboards());
$router->get('/api/members/search', static fn () => $forumCtrl->apiMemberSearch());
$router->get('/api/member-card', static fn () => $forumCtrl->apiMemberCard());
$router->get('/api/quick-search', static fn () => $forumCtrl->apiQuickSearch());
$router->get('/api/klipy', static fn () => $forumCtrl->apiKlipy());
$router->post('/api/presence/ping', static fn () => $forumCtrl->apiPresencePing());
$router->post('/api/presence/leave', static fn () => $forumCtrl->apiPresenceLeave());
$router->get('/api/presence/live', static fn () => $forumCtrl->apiPresenceLive());

$router->get('/api/chat/live', static fn () => $chatCtrl->apiLive());
$router->post('/api/chat/send', static fn () => $chatCtrl->apiSend());
$router->post('/api/chat/react', static fn () => $chatCtrl->apiReact());
$router->post('/api/chat/delete', static fn () => $chatCtrl->apiDelete());
$router->post('/api/chat/pin', static fn () => $chatCtrl->apiPin());
$router->post('/api/chat/mute', static fn () => $chatCtrl->apiMute());

$router->post('/forum/{id}/follow', static fn (array $p) => $forumCtrl->toggleFollow($p));
$router->post('/forum/{id}/topic', static fn (array $p) => $topicCtrl->create($p));
$router->post('/topic/{id}/follow', static fn (array $p) => $topicCtrl->toggleFollow($p));
$router->post('/topic/{id}/reply', static fn (array $p) => $topicCtrl->reply($p));
$router->post('/topic/{id}/vote', static fn (array $p) => $topicCtrl->votePoll($p));
$router->post('/topic/{id}/best-answer', static fn (array $p) => $topicCtrl->toggleBestAnswer($p));
$router->post('/topic/{id}/bookmark', static fn (array $p) => $topicCtrl->toggleBookmark($p));
$router->post('/topic/{id}/pin', static fn (array $p) => $topicCtrl->togglePin($p));
$router->post('/topic/{id}/feature', static fn (array $p) => $topicCtrl->toggleFeature($p));
$router->post('/topic/{id}/lock', static fn (array $p) => $topicCtrl->toggleLock($p));
$router->post('/topic/{id}/edit', static fn (array $p) => $topicCtrl->editTopic($p));
$router->post('/post/{id}/edit', static fn (array $p) => $topicCtrl->editPost($p));
$router->post('/post/{id}/mod-note', static fn (array $p) => $topicCtrl->saveModNote($p));
$router->post('/post/{id}/delete', static fn (array $p) => $topicCtrl->deletePost($p));
$router->post('/post/{id}/react', static fn (array $p) => $topicCtrl->react($p));
$router->get('/post/{id}/reactors', static fn (array $p) => $topicCtrl->reactors($p));
$router->post('/post/{id}/report', static fn (array $p) => $modCtrl->reportPost($p));
$router->get('/attachment/{id}/download', static fn (array $p) => $topicCtrl->downloadAttachment($p));
$router->post('/attachment/{id}/delete', static fn (array $p) => $topicCtrl->deleteAttachment($p));

$router->get('/notifications', static fn () => $notifCtrl->index());
$router->get('/api/notifications', static fn () => $notifCtrl->apiList());
$router->post('/notifications/read-all', static fn () => $notifCtrl->markAllRead());

$router->get('/messages', static fn () => $msgCtrl->index());
$router->post('/messages/send', static fn () => $msgCtrl->send());
$router->post('/messages/{id}/reply', static fn (array $p) => $msgCtrl->reply($p));
$router->post('/messages/{id}/leave', static fn (array $p) => $msgCtrl->leave($p));
$router->get('/api/messages/live', static fn () => $msgCtrl->apiLive());

$router->get('/mod', static fn () => $modCtrl->index());
$router->post('/mod/report/handle', static fn () => $modCtrl->handleReport());
$router->post('/mod/post/toggle-approve', static fn () => $modCtrl->togglePostApproval());
$router->post('/mod/topic/move', static fn () => $modCtrl->moveTopic());
$router->post('/mod/member/warn', static fn () => $modCtrl->warnMember());
$router->post('/mod/member/clear-sanctions', static fn () => $modCtrl->clearSanctions());

$router->get('/admin', static fn () => $adminCtrl->index());
$router->post('/admin/forums/save', static fn () => $adminCtrl->saveForum());
$router->post('/admin/forums/reorder', static fn () => $adminCtrl->reorderForum());
$router->post('/admin/forums/permissions', static fn () => $adminCtrl->saveForumPermissions());
$router->post('/admin/forums/delete', static fn () => $adminCtrl->deleteForum());
$router->post('/admin/members/update', static fn () => $adminCtrl->updateMember());
$router->post('/admin/members/create', static fn () => $adminCtrl->createMember());
$router->post('/admin/groups/save', static fn () => $adminCtrl->saveGroup());
$router->post('/admin/groups/permissions', static fn () => $adminCtrl->saveGroupPermissions());
$router->post('/admin/reactions/save', static fn () => $adminCtrl->saveReaction());
$router->post('/admin/settings/save', static fn () => $adminCtrl->saveSettings());
$router->post('/admin/customize/save', static fn () => $adminCtrl->saveCustomize());
$router->post('/admin/announcements/save', static fn () => $adminCtrl->saveAnnouncement());
$router->post('/admin/announcements/toggle', static fn () => $adminCtrl->toggleAnnouncement());
$router->post('/admin/announcements/delete', static fn () => $adminCtrl->deleteAnnouncement());
$router->post('/admin/prefixes/save', static fn () => $adminCtrl->savePrefix());
$router->post('/admin/prefixes/delete', static fn () => $adminCtrl->deletePrefix());
$router->post('/admin/profile-fields/save', static fn () => $adminCtrl->saveProfileField());
$router->post('/admin/profile-fields/delete', static fn () => $adminCtrl->deleteProfileField());
$router->post('/admin/achievements/save', static fn () => $adminCtrl->saveAchievement());
$router->post('/admin/achievements/toggle', static fn () => $adminCtrl->toggleAchievement());
$router->post('/admin/achievements/delete', static fn () => $adminCtrl->deleteAchievement());
$router->post('/admin/achievements/grant', static fn () => $adminCtrl->grantAchievement());
$router->post('/admin/achievements/revoke', static fn () => $adminCtrl->revokeAchievement());
$router->post('/admin/chat/save', static fn () => $chatCtrl->saveSettings());
$router->post('/admin/chat/clear', static fn () => $chatCtrl->clearHistory());
$router->post('/admin/chat/mute', static fn () => $chatCtrl->apiMute());
$router->post('/admin/maintenance/recount', static fn () => $adminCtrl->recountStats());
$router->post('/admin/maintenance/prune-presence', static fn () => $adminCtrl->prunePresence());
$router->get('/admin/maintenance/export-json', static fn () => $adminCtrl->exportJson());

$router->get('/login', static fn () => $authCtrl->loginForm());
$router->post('/login', static fn () => $authCtrl->login());
$router->post('/register', static fn () => $authCtrl->register());
$router->post('/logout', static fn () => $authCtrl->logout());

$router->get('/migrate', static fn () => $migCtrl->index());
$router->post('/migrate', static fn () => $migCtrl->run());

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
