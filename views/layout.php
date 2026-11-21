<?php use VeloForum\Core\Csrf; use VeloForum\Core\View; ?>
<?php
  $s = $siteSettings ?? [];
  $defaultTheme = (string) ($s['default_theme'] ?? 'light');
  $cardRadiusKey = (string) ($s['card_radius'] ?? 'rounded');
  $radiusPx = match ($cardRadiusKey) {
    'sharp' => '8px',
    'soft'  => '20px',
    default => '16px',
  };
  $curPath = (string) ($currentPath ?? '/');
  $isNavActive = static function (string $prefix) use ($curPath): string {
    if ($prefix === '/') {
      return ($curPath === '/' || str_starts_with($curPath, '/forum') || str_starts_with($curPath, '/topic')) ? ' is-active' : '';
    }
    return str_starts_with($curPath, $prefix) ? ' is-active' : '';
  };
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= View::e($seo['title'] ?? (($pageTitle ?? 'VeloForum') . ' — VeloForum')) ?></title>
  <?php if (!empty($seo['description'])): ?>
    <meta name="description" content="<?= View::e($seo['description']) ?>">
  <?php endif; ?>
  <?php if (!empty($seo['robots'])): ?>
    <meta name="robots" content="<?= View::e($seo['robots']) ?>">
  <?php endif; ?>
  <?php if (!empty($seo['canonical'])): ?>
    <link rel="canonical" href="<?= View::e($seo['canonical']) ?>">
    <meta property="og:url" content="<?= View::e($seo['canonical']) ?>">
  <?php endif; ?>
  <meta property="og:type" content="<?= View::e($seo['og_type'] ?? 'website') ?>">
  <meta property="og:title" content="<?= View::e($seo['title'] ?? ($pageTitle ?? 'VeloForum')) ?>">
  <?php if (!empty($seo['description'])): ?>
    <meta property="og:description" content="<?= View::e($seo['description']) ?>">
  <?php endif; ?>
  <meta property="og:site_name" content="<?= View::e($seo['site_name'] ?? 'VeloForum') ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= View::e($seo['title'] ?? ($pageTitle ?? 'VeloForum')) ?>">
  <?php if (!empty($seo['description'])): ?>
    <meta name="twitter:description" content="<?= View::e($seo['description']) ?>">
  <?php endif; ?>
  <?php foreach (($seo['json_ld'] ?? []) as $ld): ?>
    <script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <?php endforeach; ?>
  <script>
    (function () {
      try {
        var saved = localStorage.getItem('vf-theme');
        if (saved === 'dark' || (!saved && <?= json_encode($defaultTheme === 'dark') ?>)) {
          document.documentElement.setAttribute('data-theme', 'dark');
        }
      } catch (e) {}
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700&display=swap" rel="stylesheet">
  <link rel="preload" as="style" href="/assets/style.css?v=3.7">
  <link rel="stylesheet" href="/assets/style.css?v=3.7">
  <?php
    $accentKey = (string) ($s['accent_color'] ?? 'blue');
    $accentPalettes = [
      'blue'    => ['#2563eb', '#1d4ed8', '#eff6ff', '#93c5fd', '#3b82f6', '#60a5fa', 'rgba(30, 58, 138, 0.42)', '#1d4ed8'],
      'emerald' => ['#059669', '#047857', '#ecfdf5', '#6ee7b7', '#10b981', '#34d399', 'rgba(6, 78, 59, 0.42)', '#047857'],
      'indigo'  => ['#4f46e5', '#4338ca', '#eef2ff', '#a5b4fc', '#6366f1', '#818cf8', 'rgba(49, 46, 129, 0.42)', '#4338ca'],
      'amber'   => ['#d97706', '#b45309', '#fffbeb', '#fcd34d', '#f59e0b', '#fbbf24', 'rgba(120, 53, 15, 0.38)', '#b45309'],
      'rose'    => ['#e11d48', '#be123c', '#fff1f2', '#fda4af', '#f43f5e', '#fb7185', 'rgba(136, 19, 55, 0.38)', '#be123c'],
      'slate'   => ['#334155', '#1e293b', '#f1f5f9', '#cbd5e1', '#64748b', '#94a3b8', 'rgba(51, 65, 85, 0.48)', '#475569'],
    ];
    $pal = $accentPalettes[$accentKey] ?? $accentPalettes['blue'];
    $siteBrand = (string) ($s['site_name'] ?? 'VeloForum');
    $siteTagline = trim((string) ($s['site_tagline'] ?? ''));
    $footerText = trim((string) ($s['footer_text'] ?? ''));
    $presencePageType = !empty($topic['id']) ? 'topic' : (!empty($forum['id']) ? 'forum' : (str_starts_with($curPath, '/messages') ? 'messages' : (str_starts_with($curPath, '/activity') ? 'activity' : (str_starts_with($curPath, '/members') ? 'members' : (str_starts_with($curPath, '/member/') ? 'member' : 'home')))));
  ?>
  <style>
    :root { --accent: <?= $pal[0] ?>; --accent-hover: <?= $pal[1] ?>; --accent-soft: <?= $pal[2] ?>; --accent-border: <?= $pal[3] ?>; --radius: <?= $radiusPx ?>; }
    html[data-theme="dark"] { --accent: <?= $pal[4] ?>; --accent-hover: <?= $pal[5] ?>; --accent-soft: <?= $pal[6] ?>; --accent-border: <?= $pal[7] ?>; }
  </style>
  <meta name="vf-base" content="<?= View::e($basePath ?? '') ?>">
  <meta name="vf-page-type" content="<?= View::e($presencePageType) ?>">
  <meta name="vf-topic-id" content="<?= !empty($topic['id']) ? (int) $topic['id'] : 0 ?>">
  <meta name="vf-forum-id" content="<?= !empty($topic['forum_id']) ? (int) $topic['forum_id'] : (!empty($forum['id']) ? (int) $forum['id'] : 0) ?>">
  <script src="/assets/app.js?v=3.7" defer></script>
</head>
<body>
  <div class="vf-nav-loader" data-vf-nav-loader aria-hidden="true"></div>
  <header class="vf-header">
    <div class="vf-header-inner">
      <div class="vf-header-left">
        <button type="button" class="vf-icon-btn vf-mobile-toggle" data-vf-mobile-toggle aria-expanded="false" aria-label="Menu nawigacji">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
        </button>
        <a href="/" class="vf-brand" style="display:inline-flex;align-items:center;gap:8px;">
          <span class="vf-brand-mark" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          </span>
          <span><?= View::e($siteBrand) ?></span>
        </a>
      </div>

      <nav class="vf-nav" data-vf-nav>
        <a href="/" class="<?= trim($isNavActive('/')) ?>">Forum</a>
        <a href="/activity" class="<?= trim($isNavActive('/activity')) ?>">Co nowego</a>
        <a href="/members" class="<?= trim($isNavActive('/members')) ?>">Użytkownicy</a>
        <?php if (!empty($currentUser)): ?>
          <a href="/bookmarks" class="<?= trim($isNavActive('/bookmarks')) ?>">Zapisane</a>
        <?php endif; ?>
        <?php if (!empty($currentUser) && (!empty($canAccessModCp) || (int) ($currentUser['is_moderator'] ?? 0) === 1 || (int) ($currentUser['is_admin'] ?? 0) === 1)): ?>
          <a href="/mod" class="<?= trim($isNavActive('/mod')) ?>">ModCP</a>
        <?php endif; ?>
        <?php if (!empty($currentUser) && (int) ($currentUser['is_admin'] ?? 0) === 1): ?>
          <a href="/admin" class="<?= trim($isNavActive('/admin')) ?>">ACP</a>
          <a href="/migrate" class="<?= trim($isNavActive('/migrate')) ?>">Migrator IC5</a>
        <?php endif; ?>
      </nav>

      <div class="vf-header-actions">
        <button type="button" class="vf-icon-btn vf-quick-jump-btn" data-vf-quick-jump title="Szukaj na forum (Ctrl+K)" aria-label="Szukaj na forum (Ctrl+K)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <span class="vf-hide-sm">Ctrl+K</span>
        </button>
        <button type="button" class="vf-icon-btn" data-vf-theme-toggle aria-label="Przełącz motyw jasny lub ciemny" title="Przełącz motyw">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
        </button>
        <?php if (!empty($currentUser)): ?>
          <a href="/messages" class="vf-icon-btn vf-header-icon-link<?= trim($isNavActive('/messages')) ?>" aria-label="Prywatne wiadomości" title="Wiadomości">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span class="vf-icon-badge" data-vf-pm-badge<?= empty($unreadMessagesCount) ? ' hidden' : '' ?>><?= (int) ($unreadMessagesCount ?? 0) ?></span>
          </a>
          <div class="vf-notif-wrap">
            <a href="/notifications" data-vf-notif-trigger class="vf-icon-btn vf-header-icon-link<?= trim($isNavActive('/notifications')) ?>" aria-label="Powiadomienia" title="Powiadomienia">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
              <span class="vf-icon-badge" data-vf-notif-badge<?= empty($unreadCount) ? ' hidden' : '' ?>><?= (int) ($unreadCount ?? 0) ?></span>
            </a>
            <div data-vf-notif-popover class="vf-notif-popover is-right" style="display:none;">
              <div class="vf-notif-head">
                <span>Powiadomienia</span>
                <a href="/notifications" style="font-weight:500;">Wszystkie</a>
              </div>
              <div data-vf-notif-list style="max-height:280px;overflow-y:auto;"></div>
            </div>
          </div>
          <a href="/member/<?= (int) $currentUser['id'] ?>-<?= View::e((string) ($currentUser['slug'] ?? 'profil')) ?>" class="vf-user-link">
            <?= View::formatUsername((string) $currentUser['username'], $currentUser['color_hex'] ?? null, $currentUser['group_prefix'] ?? null, $currentUser['group_suffix'] ?? null, $currentUser['group_name'] ?? null) ?>
          </a>
          <form method="post" action="/logout" style="display:inline;margin:0;">
            <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
            <button type="submit" class="vf-btn vf-btn-sm">Wyloguj</button>
          </form>
        <?php else: ?>
          <a href="/login" class="vf-btn vf-btn-sm">Zaloguj się</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <div class="vf-read-progress" data-vf-read-progress aria-hidden="true">
    <div class="vf-read-progress-bar"></div>
  </div>

  <main class="vf-container vf-page-enter">
    <?php foreach (($globalAnnouncements ?? []) as $ann): ?>
      <?php if (($ann['placement'] ?? 'home') === 'all'): ?>
        <?php $toneClass = match ($ann['tone'] ?? 'info') { 'success' => ' is-success', 'warning' => ' is-warning', default => '' }; ?>
        <div class="vf-announcement<?= $toneClass ?>" role="status">
          <div>
            <div class="vf-announcement-title"><?= View::e((string) $ann['title']) ?></div>
            <div class="vf-announcement-body"><?= View::e((string) $ann['content']) ?></div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
            <?php if (!empty($ann['link_url']) && !empty($ann['link_label'])): ?>
              <a href="<?= View::e((string) $ann['link_url']) ?>" class="vf-btn vf-btn-sm"><?= View::e((string) $ann['link_label']) ?></a>
            <?php endif; ?>
            <button type="button" class="vf-icon-btn" onclick="this.closest('.vf-announcement').remove()" aria-label="Zamknij ogłoszenie">✕</button>
          </div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
    <?= $content ?>
  </main>

  <footer class="vf-footer">
    <div class="vf-footer-inner">
      <div>
        <strong style="color:var(--ink);"><?= View::e($siteBrand) ?></strong>
        <?php if ($siteTagline !== ''): ?>
          <span class="vf-muted"> · <?= View::e($siteTagline) ?></span>
        <?php endif; ?>
        <?php if ($footerText !== ''): ?>
          <div class="vf-muted" style="font-size:0.8125rem;margin-top:4px;"><?= View::e($footerText) ?></div>
        <?php else: ?>
          <div class="vf-muted" style="font-size:0.8125rem;margin-top:4px;">&copy; <?= date('Y') ?> <?= View::e($siteBrand) ?> · Nowoczesne forum dyskusyjne</div>
        <?php endif; ?>
      </div>
      <div class="vf-footer-links">
        <a href="/">Forum</a>
        <a href="/activity">Co nowego</a>
        <a href="/members">Użytkownicy</a>
        <a href="/sitemap.xml">Mapa strony</a>
      </div>
    </div>
  </footer>

  <div class="vf-toast-stack" data-vf-toast-stack aria-live="polite"></div>

  <div class="vf-quick-modal" data-vf-quick-modal role="dialog" aria-modal="true" aria-label="Paleta szybkiego przejścia" hidden>
    <div class="vf-quick-dialog">
      <div class="vf-quick-head">
        <input type="search" class="vf-quick-input" data-vf-quick-input placeholder="Szukaj tematu, działu lub użytkownika..." autocomplete="off">
        <button type="button" class="vf-icon-btn" data-vf-quick-close aria-label="Zamknij">ESC</button>
      </div>
      <div class="vf-quick-results" data-vf-quick-results>
        <div class="vf-muted" style="padding:16px;text-align:center;">Wpisz frazę lub wybierz z najnowszych wątków...</div>
      </div>
    </div>
  </div>

  <button type="button" class="vf-scroll-top" data-vf-scroll-top aria-label="Przewiń na górę" hidden>
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg>
    <span>Do góry</span>
  </button>

  <nav class="vf-mobile-bottom-nav" aria-label="Nawigacja mobilna">
    <a href="/" class="vf-mob-nav-item<?= $isNavActive('/') ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span>Forum</span>
    </a>
    <a href="/activity" class="vf-mob-nav-item<?= $isNavActive('/activity') ?>">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      <span>Co nowego</span>
    </a>
    <button type="button" class="vf-mob-nav-item" data-vf-quick-jump aria-label="Szukaj na forum">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
      <span>Szukaj</span>
    </button>
    <?php if (!empty($currentUser)): ?>
      <a href="/messages" class="vf-mob-nav-item<?= $isNavActive('/messages') ?>">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span>Wiadomości</span>
        <span class="vf-mob-badge" data-vf-mob-pm-badge<?= empty($unreadMessagesCount) ? ' hidden' : '' ?>><?= (int) ($unreadMessagesCount ?? 0) ?></span>
      </a>
    <?php else: ?>
      <a href="/members" class="vf-mob-nav-item<?= $isNavActive('/members') ?>">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>Ludzie</span>
      </a>
    <?php endif; ?>
    <button type="button" class="vf-mob-nav-item" data-vf-mobile-sheet-toggle aria-expanded="false" aria-controls="vf-mobile-sheet">
      <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
      <span>Więcej</span>
      <span class="vf-mob-badge" data-vf-mob-notif-badge<?= empty($unreadCount) ? ' hidden' : '' ?>><?= (int) ($unreadCount ?? 0) ?></span>
    </button>
  </nav>

  <div class="vf-mobile-sheet-backdrop" data-vf-mobile-sheet-backdrop hidden>
    <div class="vf-mobile-sheet" id="vf-mobile-sheet" role="dialog" aria-modal="true" aria-label="Menu narzędzi i profilu">
      <div class="vf-mobile-sheet-handle" aria-hidden="true"></div>
      <div class="vf-mobile-sheet-head">
        <strong><?= !empty($currentUser) ? View::formatUsername((string) $currentUser['username'], $currentUser['color_hex'] ?? null, $currentUser['group_prefix'] ?? null, $currentUser['group_suffix'] ?? null, $currentUser['group_name'] ?? null) : View::e($siteBrand) ?></strong>
        <button type="button" class="vf-icon-btn" data-vf-mobile-sheet-close aria-label="Zamknij menu">✕</button>
      </div>
      <div class="vf-mobile-sheet-links">
        <a href="/members" class="vf-mobile-sheet-action">Użytkownicy</a>
        <a href="/activity?tab=solved" class="vf-mobile-sheet-action">Rozwiązane</a>
        <?php if (!empty($currentUser)): ?>
          <a href="/member/<?= (int) $currentUser['id'] ?>-<?= View::e((string) ($currentUser['slug'] ?? 'profil')) ?>" class="vf-mobile-sheet-action">Mój profil</a>
          <a href="/bookmarks" class="vf-mobile-sheet-action">Zapisane wątki</a>
          <a href="/messages" class="vf-mobile-sheet-action">
            Wiadomości<span data-vf-pm-count-text><?= !empty($unreadMessagesCount) ? ' (' . (int) $unreadMessagesCount . ')' : '' ?></span>
          </a>
          <a href="/notifications" class="vf-mobile-sheet-action">
            Powiadomienia<span data-vf-notif-count-text><?= !empty($unreadCount) ? ' (' . (int) $unreadCount . ')' : '' ?></span>
          </a>
        <?php else: ?>
          <a href="/login" class="vf-mobile-sheet-action">Zaloguj się / Rejestracja</a>
        <?php endif; ?>
        <?php if (!empty($currentUser) && (!empty($canAccessModCp) || (int) ($currentUser['is_moderator'] ?? 0) === 1 || (int) ($currentUser['is_admin'] ?? 0) === 1)): ?>
          <a href="/mod" class="vf-mobile-sheet-action">Panel Moderatora</a>
        <?php endif; ?>
        <?php if (!empty($currentUser) && (int) ($currentUser['is_admin'] ?? 0) === 1): ?>
          <a href="/admin" class="vf-mobile-sheet-action">Panel Admina (ACP)</a>
          <a href="/migrate" class="vf-mobile-sheet-action">Migrator IC5</a>
        <?php endif; ?>
        <button type="button" class="vf-mobile-sheet-action" data-vf-theme-toggle>
          <span>Przełącz motyw</span>
        </button>
      </div>
    </div>
  </div>

  <div class="vf-lightbox" data-vf-lightbox role="dialog" aria-modal="true" aria-label="Podgląd obrazu" hidden>
    <button type="button" class="vf-lightbox-close" data-vf-lightbox-close aria-label="Zamknij podgląd">✕</button>
    <img src="" alt="" data-vf-lightbox-img />
  </div>
  <div class="vf-hovercard" data-vf-hovercard role="dialog" aria-label="Wizytówka użytkownika" hidden></div>
</body>
</html>
