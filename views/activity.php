<?php use VeloForum\Core\View; ?>
<?php
  $curTab = $tab ?? 'activity';
  $chevronRightSvg = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>';
  $pinSvg = '<svg class="vf-status-icon is-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"/></svg>';
  $solvedSvg = '<svg class="vf-status-icon is-solved" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>';
  $tabHeadings = [
    'activity' => 'Strumień ostatniej aktywności',
    'latest'   => 'Najnowsze założone tematy',
    'solved'   => 'Wątki z wybranym rozwiązaniem',
    'polls'    => 'Tematy zawierające ankietę',
  ];
?>
<nav class="vf-breadcrumb" aria-label="Ścieżka nawigacji">
  <a href="/">Forum</a>
  <?= $chevronRightSvg ?>
  <span class="vf-breadcrumb-cur"><?= View::e($pageTitle ?? 'Co nowego?') ?></span>
</nav>

<div class="vf-feed-pills" role="navigation" aria-label="Widoki aktywności">
  <a href="/" class="vf-feed-pill">Struktura działów</a>
  <a href="/activity?tab=activity" class="vf-feed-pill<?= $curTab === 'activity' ? ' is-active' : '' ?>">Co nowego?</a>
  <a href="/activity?tab=latest" class="vf-feed-pill<?= $curTab === 'latest' ? ' is-active' : '' ?>">Najnowsze tematy</a>
  <a href="/activity?tab=solved" class="vf-feed-pill<?= $curTab === 'solved' ? ' is-active' : '' ?>">Rozwiązane</a>
  <a href="/activity?tab=polls" class="vf-feed-pill<?= $curTab === 'polls' ? ' is-active' : '' ?>">Ankiety</a>
</div>

<section class="vf-card">
  <div class="vf-card-head vf-card-head-flex">
    <span style="font-weight:700;font-size:0.9rem;"><?= View::e($tabHeadings[$curTab] ?? 'Strumień aktywności') ?></span>
    <span class="vf-cat-count"><?= count($latestTopics ?? []) ?> wątków</span>
  </div>
  <?php if (empty($latestTopics)): ?>
    <div class="vf-row"><span class="vf-muted">Brak dyskusji w tej kategorii widoku.</span></div>
  <?php endif; ?>
  <?php foreach ($latestTopics as $t): ?>
    <?php
      $tid = (int) $t['id'];
      $tLast = (int) ($t['last_post_at'] ?? $t['created_at'] ?? 0);
      $lpName = (string) ($t['last_poster_name'] ?? 'U');
      $lpInitials = mb_strtoupper(mb_substr($lpName, 0, 2));
      $isUnread = !empty($unreadTopicsMap[$tid]);
      $isHot = (int) ($t['posts_count'] ?? 0) >= 8;
      $tags = $topicTags[$tid] ?? [];
      $authorBadge = View::formatUsername(
        (string) ($t['author_name'] ?? 'Gość'),
        $t['author_group_color'] ?? null,
        $t['author_group_prefix'] ?? null,
        $t['author_group_suffix'] ?? null,
        $t['author_group_name'] ?? null
      );
      $lpBadge = View::formatUsername(
        $lpName,
        $t['last_poster_color'] ?? null,
        $t['last_poster_prefix'] ?? null,
        $t['last_poster_suffix'] ?? null,
        $t['last_poster_group'] ?? null
      );
    ?>
    <div class="vf-forum-row">
      <div style="min-width:0;">
        <div class="vf-topic-title-line">
          <?php if ((int) ($t['is_pinned'] ?? 0) === 1): ?><?= $pinSvg ?><?php endif; ?>
          <?php if (!empty($t['best_answer_post_id'])): ?><?= $solvedSvg ?><?php endif; ?>
          <?php if (!empty($t['prefix_label'])): ?>
            <span class="vf-prefix" style="color:<?= View::e((string) ($t['prefix_color'] ?? '#2563eb')) ?>;"><?= View::e((string) $t['prefix_label']) ?></span>
          <?php endif; ?>
          <a href="/topic/<?= $tid ?>-<?= View::e((string) $t['slug']) ?>" class="vf-topic-link<?= $isUnread ? ' is-unread' : '' ?>">
            <?= View::e((string) $t['title']) ?>
          </a>
        </div>
        <div class="vf-muted" style="margin-top:4px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
          <span>Dział: <a href="/forum/<?= (int) $t['forum_id'] ?>-<?= View::e((string) ($t['forum_slug'] ?? 'dzial')) ?>"><?= View::e((string) $t['forum_name']) ?></a></span>
          <span aria-hidden="true">·</span>
          <span>Autor:
            <?php if (!empty($t['author_id'])): ?>
              <a href="/member/<?= (int) $t['author_id'] ?>-<?= View::e((string) ($t['author_slug'] ?? 'profil')) ?>"><?= $authorBadge ?></a>
            <?php else: ?>
              <?= $authorBadge ?>
            <?php endif; ?>
          </span>
          <?php if ($isUnread): ?>
            <span aria-hidden="true">·</span>
            <span class="vf-unread-label">Nieprzeczytany</span>
          <?php endif; ?>
          <?php if ($isHot): ?>
            <span aria-hidden="true">·</span>
            <span class="vf-hot-flag">Gorący</span>
          <?php endif; ?>
          <?php if (!empty($t['has_poll'])): ?>
            <span aria-hidden="true">·</span>
            <span class="vf-unread-label">Ankieta</span>
          <?php endif; ?>
          <?php foreach ($tags as $tg): ?>
            <span aria-hidden="true">·</span>
            <a href="/tag/<?= View::e($tg['tag_slug']) ?>">#<?= View::e($tg['tag_label']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="vf-forum-stats">
        <div><strong><?= max(0, (int) $t['posts_count'] - 1) ?></strong><span>odp.</span></div>
        <div><strong><?= (int) $t['views_count'] ?></strong><span>wyśw.</span></div>
      </div>
      <div class="vf-last-post">
        <span class="vf-avatar">
          <?php if (!empty($t['last_poster_avatar'])): ?>
            <img src="<?= View::e((string) $t['last_poster_avatar']) ?>" alt="<?= View::e($lpName) ?>" data-vf-initials="<?= View::e($lpInitials) ?>" loading="lazy" decoding="async">
          <?php else: ?>
            <?= View::e($lpInitials) ?>
          <?php endif; ?>
        </span>
        <div class="vf-last-post-meta">
          <div class="vf-last-post-title" style="font-weight:500;">
            <?php if (!empty($t['last_poster_id'])): ?>
              <a href="/member/<?= (int) $t['last_poster_id'] ?>-<?= View::e((string) ($t['last_poster_slug'] ?? 'profil')) ?>"><?= $lpBadge ?></a>
            <?php else: ?>
              <?= $lpBadge ?>
            <?php endif; ?>
          </div>
          <div class="vf-last-post-sub" title="<?= date('d.m.Y H:i', $tLast) ?>">
            <?= View::e(View::formatRelativeTime($tLast)) ?>
            · <a href="/topic/<?= $tid ?>-<?= View::e((string) $t['slug']) ?>#reply-box">Ostatni →</a>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</section>
