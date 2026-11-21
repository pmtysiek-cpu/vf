<?php use VeloForum\Core\Csrf; use VeloForum\Core\View; use VeloForum\Service\HtmlSanitizer; ?>
<?php
  $chevronRightSvg = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>';
?>
<nav class="vf-breadcrumb" aria-label="Ścieżka nawigacji">
  <a href="/">Forum</a>
  <?php foreach ($breadcrumbs as $crumb): ?>
    <?= $chevronRightSvg ?>
    <a href="/forum/<?= (int) $crumb['id'] ?>-<?= View::e($crumb['slug']) ?>"><?= View::e($crumb['name']) ?></a>
  <?php endforeach; ?>
  <?= $chevronRightSvg ?>
  <span class="vf-breadcrumb-cur"><?= View::e($topic['title']) ?></span>
</nav>

<?php if (!empty($_GET['reported'])): ?>
  <div class="vf-alert-success">
    Zgłoszenie zostało przekazane do Panelu Moderatora. Dziękujemy za pomoc w utrzymaniu porządku.
  </div>
<?php endif; ?>

<?php if (!empty($queuedNotice)): ?>
  <div class="vf-alert-success">
    Twoja odpowiedź została zapisana i oczekuje w kolejce na akceptację moderatora działu.
  </div>
<?php endif; ?>

<?php
  $isModInForum = !empty($forumPerms['can_moderate']);
  $isTopicLocked = (int) $topic['is_locked'] === 1 || (int) ($topic['forum_is_locked'] ?? 0) === 1;
  $isTopicOwner = !empty($currentUser) && (int) ($topic['author_id'] ?? 0) === (int) $currentUser['id'];
  $canEditTopicMeta = !empty($currentUser) && ($isModInForum || ($isTopicOwner && !empty($forumPerms['can_edit_own'])));
  $totalTopicWords = 0;
  foreach ($posts as $wp) {
    $plainWp = trim(strip_tags((string) ($wp['content_html'] ?? '')));
    if ($plainWp !== '') {
      $totalTopicWords += count(preg_split('/\s+/u', $plainWp) ?: []);
    }
  }
  $readMinutes = max(1, (int) ceil($totalTopicWords / 200));
  $tagLabelsCsv = implode(', ', array_map(static fn (array $tg): string => (string) $tg['tag_label'], $tags ?? []));
  $topicCreatedAt = (int) ($topic['created_at'] ?? 0);
?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:18px;">
  <div style="min-width:0;">
    <h1 style="margin:0 0 6px;font-size:1.5rem;">
      <?php if (!empty($topic['prefix_label'])): ?>
        <span class="vf-prefix" style="color:<?= View::e((string) ($topic['prefix_color'] ?? '#2563eb')) ?>;"><?= View::e((string) $topic['prefix_label']) ?></span>
      <?php endif; ?>
      <?= View::e($topic['title']) ?>
    </h1>
    <p class="vf-muted" style="margin:0;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
      <span>Autor:
        <?php if (!empty($topic['author_id'])): ?>
          <a href="/member/<?= (int) $topic['author_id'] ?>-<?= View::e((string) ($topic['author_slug'] ?? 'profil')) ?>">
            <?= View::formatUsername((string) $topic['author_name'], $topic['author_group_color'] ?? null, $topic['author_group_prefix'] ?? null, $topic['author_group_suffix'] ?? null, $topic['author_group_name'] ?? null) ?>
          </a>
        <?php else: ?>
          <?= View::formatUsername((string) $topic['author_name'], $topic['author_group_color'] ?? null, $topic['author_group_prefix'] ?? null, $topic['author_group_suffix'] ?? null, $topic['author_group_name'] ?? null) ?>
        <?php endif; ?>
      </span>
      <span aria-hidden="true">·</span>
      <span title="<?= date('d.m.Y H:i', $topicCreatedAt) ?>"><?= View::e(View::formatRelativeTime($topicCreatedAt)) ?></span>
      <span aria-hidden="true">·</span>
      <span><?= (int) $topic['views_count'] ?> wyświetleń</span>
      <span aria-hidden="true">·</span>
      <span>~<?= $readMinutes ?> min czytania</span>
      <?php if ((int) ($topic['is_featured'] ?? 0) === 1): ?>
        <span aria-hidden="true">·</span>
        <span style="color:var(--accent);font-weight:700;">Wyróżniony</span>
      <?php endif; ?>
      <?php if ($isTopicLocked): ?>
        <span aria-hidden="true">·</span>
        <span style="color:var(--warn-ink);font-weight:600;">Temat zamknięty</span>
      <?php endif; ?>
      <?php if (!empty($topic['best_answer_post_id'])): ?>
        <span aria-hidden="true">·</span>
        <a href="#post-<?= (int) $topic['best_answer_post_id'] ?>" class="vf-solved-label">Przejdź do rozwiązania</a>
      <?php endif; ?>
      <?php if (!empty($tags)): ?>
        <?php foreach ($tags as $tg): ?>
          <span aria-hidden="true">·</span>
          <a href="/tag/<?= View::e($tg['tag_slug']) ?>">#<?= View::e($tg['tag_label']) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>
    </p>
  </div>

  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
    <button type="button" class="vf-react-trigger" data-vf-copy-link="#post-<?= (int) ($posts[0]['id'] ?? '') ?>" title="Kopiuj link do tematu">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
      <span>Udostępnij</span>
    </button>
    <?php if (!empty($currentUser)): ?>
      <form method="post" action="/topic/<?= (int) $topic['id'] ?>/follow" style="margin:0;">
        <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
        <button type="submit" class="vf-react-trigger<?= !empty($isFollowing) ? ' is-active' : '' ?>">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
          <span><?= !empty($isFollowing) ? 'Obserwujesz' : 'Obserwuj' ?> (<?= (int) ($followCount ?? 0) ?>)</span>
        </button>
      </form>
      <form method="post" action="/topic/<?= (int) $topic['id'] ?>/bookmark" style="margin:0;">
        <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
        <button type="submit" class="vf-react-trigger<?= !empty($isBookmarked) ? ' is-active' : '' ?>">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg>
          <span><?= !empty($isBookmarked) ? 'Zapisano' : 'Zapisz' ?></span>
        </button>
      </form>
    <?php endif; ?>
    <?php if ($canEditTopicMeta): ?>
      <button type="button" class="vf-react-trigger" data-vf-toggle-target="vf-edit-topic-panel" aria-expanded="false">
        Edytuj temat
      </button>
    <?php endif; ?>
    <?php if ($isModInForum): ?>
      <form method="post" action="/topic/<?= (int) $topic['id'] ?>/feature" style="margin:0;">
        <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
        <button type="submit" class="vf-react-trigger<?= (int) ($topic['is_featured'] ?? 0) === 1 ? ' is-active' : '' ?>" title="Wyróżnij na stronie głównej">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          <span><?= (int) ($topic['is_featured'] ?? 0) === 1 ? 'Wyróżniony' : 'Wyróżnij' ?></span>
        </button>
      </form>
      <form method="post" action="/topic/<?= (int) $topic['id'] ?>/pin" style="margin:0;">
        <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
        <button type="submit" class="vf-btn vf-btn-sm"><?= (int) $topic['is_pinned'] === 1 ? 'Odepnij' : 'Przypnij' ?></button>
      </form>
      <form method="post" action="/topic/<?= (int) $topic['id'] ?>/lock" style="margin:0;">
        <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
        <button type="submit" class="vf-btn vf-btn-sm"><?= (int) $topic['is_locked'] === 1 ? 'Otwórz' : 'Zamknij' ?></button>
      </form>
      <a href="/mod?tab=topics" class="vf-btn vf-btn-sm vf-btn-muted">ModCP</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($canEditTopicMeta): ?>
  <section class="vf-card vf-inline-panel" id="vf-edit-topic-panel" hidden>
    <div class="vf-card-head">Edycja tytułu, prefiksu i tagów tematu</div>
    <form method="post" action="/topic/<?= (int) $topic['id'] ?>/edit" style="padding:18px 20px;display:grid;gap:12px;">
      <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php if (!empty($prefixes)): ?>
          <select name="prefix_id" class="vf-input" style="width:auto;min-width:170px;">
            <option value="0">— Bez prefiksu —</option>
            <?php foreach ($prefixes as $px): ?>
              <option value="<?= (int) $px['id'] ?>"<?= (int) ($topic['prefix_id'] ?? 0) === (int) $px['id'] ? ' selected' : '' ?>>
                <?= View::e((string) $px['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
        <input type="text" name="title" maxlength="250" class="vf-input" style="flex:1;min-width:220px;" value="<?= View::e((string) $topic['title']) ?>" required>
      </div>
      <input type="text" name="tags" maxlength="160" class="vf-input" value="<?= View::e($tagLabelsCsv) ?>" placeholder="Tagi po przecinku (np. Dyskusja, Pomoc, Aktualizacja)">
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button type="submit" class="vf-btn vf-btn-sm">Zapisz zmiany tematu</button>
        <button type="button" class="vf-btn vf-btn-sm vf-btn-secondary" data-vf-toggle-target="vf-edit-topic-panel">Anuluj</button>
      </div>
    </form>
  </section>
<?php endif; ?>

<?php if (!empty($poll)): ?>
  <section class="vf-card">
    <div class="vf-card-head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
      <span>Ankieta: <?= View::e($poll['question']) ?></span>
      <span class="vf-muted">Łącznie głosów: <?= (int) $poll['total_votes'] ?></span>
    </div>
    <div style="padding:16px 20px;display:grid;gap:8px;">
      <?php foreach ($poll['options'] as $opt): ?>
        <?php
          $votes = (int) $opt['votes_count'];
          $totalV = max(0, (int) $poll['total_votes']);
          $pct = $totalV > 0 ? (int) round(($votes / $totalV) * 100) : 0;
          $isMine = (int) ($poll['my_option_id'] ?? 0) === (int) $opt['id'];
        ?>
        <form method="post" action="/topic/<?= (int) $topic['id'] ?>/vote" style="margin:0;">
          <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
          <input type="hidden" name="option_id" value="<?= (int) $opt['id'] ?>">
          <button type="submit" class="vf-poll-opt<?= $isMine ? ' is-selected' : '' ?>" <?= empty($currentUser) ? 'disabled' : '' ?>>
            <span class="vf-poll-bar" style="width:<?= $pct ?>%;"></span>
            <span class="vf-poll-row">
              <span><?= $isMine ? '✓ ' : '' ?><?= View::e($opt['label']) ?></span>
              <span><?= $pct ?>% (<?= $votes ?>)</span>
            </span>
          </button>
        </form>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if (!empty($topicParticipants)): ?>
  <div class="vf-participants-bar">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;min-width:0;">
      <span class="vf-muted" style="font-weight:600;white-space:nowrap;">Uczestnicy (<?= count($topicParticipants) ?>):</span>
      <div class="vf-participants-avatars">
        <?php foreach ($topicParticipants as $ptc): ?>
          <?php $ptcName = (string) ($ptc['username'] ?? 'Użytkownik'); ?>
          <?php $ptcInit = mb_strtoupper(mb_substr($ptcName, 0, 2)); ?>
          <a href="/member/<?= (int) $ptc['id'] ?>-<?= View::e((string) ($ptc['slug'] ?? 'profil')) ?>" class="vf-participant-chip" title="<?= View::e($ptcName) ?> (<?= (int) $ptc['post_cnt'] ?> postów)">
            <span class="vf-avatar" style="width:22px;height:22px;border-radius:7px;font-size:0.65rem;">
              <?php if (!empty($ptc['avatar_url'])): ?>
                <img src="<?= View::e((string) $ptc['avatar_url']) ?>" alt="<?= View::e($ptcName) ?>" data-vf-initials="<?= View::e($ptcInit) ?>" loading="lazy" decoding="async">
              <?php else: ?>
                <?= View::e($ptcInit) ?>
              <?php endif; ?>
            </span>
            <span><?= View::formatUsername($ptcName, $ptc['group_color'] ?? null, $ptc['group_prefix'] ?? null, $ptc['group_suffix'] ?? null, $ptc['group_name'] ?? null) ?></span>
            <?php if ((int) $ptc['post_cnt'] > 1): ?>
              <span class="vf-muted">(<?= (int) $ptc['post_cnt'] ?>)</span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if (!empty($currentUser) && !empty($forumPerms['can_reply']) && (!$isTopicLocked || $isModInForum)): ?>
      <a href="#reply-box" class="vf-btn vf-btn-sm">Szybka odpowiedź</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php
  $liveTopicMembers = $topicPresence['topic_members'] ?? [];
  $liveTopicGuests = (int) ($topicPresence['topic_guests_count'] ?? 0);
?>
<div
  class="vf-topic-live-bar"
  data-vf-topic-presence
  data-topic-id="<?= (int) $topic['id'] ?>"
  data-last-post-id="<?= (int) ($topic['last_post_id'] ?? 0) ?>"
  data-posts-count="<?= (int) ($topic['posts_count'] ?? 0) ?>"
>
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;min-width:0;flex:1;">
    <span class="vf-presence-badge is-online">
      <span class="vf-presence-dot is-online is-pulse" aria-hidden="true"></span>
      <span>Aktualnie w temacie</span>
    </span>
    <div class="vf-participants-avatars" data-vf-topic-viewers>
      <?php foreach ($liveTopicMembers as $tm): ?>
        <?php $tmName = (string) ($tm['username'] ?? 'Użytkownik'); $tmInit = mb_strtoupper(mb_substr($tmName, 0, 2)); ?>
        <a href="/member/<?= (int) $tm['id'] ?>-<?= View::e((string) ($tm['slug'] ?? 'profil')) ?>" class="vf-participant-chip">
          <span class="vf-avatar" style="width:20px;height:20px;border-radius:6px;font-size:0.6rem;">
            <?php if (!empty($tm['avatar_url'])): ?>
              <img src="<?= View::e((string) $tm['avatar_url']) ?>" alt="<?= View::e($tmName) ?>" data-vf-initials="<?= View::e($tmInit) ?>">
            <?php else: ?>
              <?= View::e($tmInit) ?>
            <?php endif; ?>
          </span>
          <span><?= View::formatUsername($tmName, $tm['group_color'] ?? null, $tm['group_prefix'] ?? null, $tm['group_suffix'] ?? null, $tm['group_name'] ?? null) ?></span>
        </a>
      <?php endforeach; ?>
      <?php if ($liveTopicGuests > 0): ?>
        <span class="vf-muted" style="font-size:0.78rem;">+ <?= $liveTopicGuests ?> gości</span>
      <?php elseif (empty($liveTopicMembers)): ?>
        <span class="vf-muted" style="font-size:0.78rem;">Tylko Ty przeglądasz ten temat</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="vf-live-new-banner" data-vf-topic-new-banner hidden>
  <span data-vf-topic-new-text>Pojawiły się nowe odpowiedzi w tym wątku.</span>
  <a href="/topic/<?= (int) $topic['id'] ?>-<?= View::e($topic['slug']) ?>?page=<?= (int) $totalPages ?>" class="vf-btn vf-btn-sm">Odśwież wątek</a>
</div>

<section class="vf-card" style="overflow:visible;">
  <?php
    $isTopicOwner = !empty($currentUser) && (int) ($topic['author_id'] ?? 0) === (int) $currentUser['id'];
    $allowBestAnswer = (int) ($topic['forum_allow_best_answer'] ?? 1) === 1;
  ?>
  <?php foreach ($posts as $postIndex => $post): ?>
    <?php
      $pid = (int) $post['id'];
      $aid = (int) ($post['author_id'] ?? 0);
      $pReact = $postReactions[$pid] ?? ['counts' => [], 'user_reaction' => null];
      $authorName = (string) ($post['member_username'] ?? $post['author_name']);
      $authorInitials = mb_strtoupper(mb_substr($authorName, 0, 2));
      $authorSlug = (string) ($post['member_slug'] ?? 'profil');
      $isBest = (int) ($topic['best_answer_post_id'] ?? 0) === $pid;
      $authorLast = isset($post['member_last_activity']) ? (int) $post['member_last_activity'] : null;
      $authorJoined = isset($post['member_joined']) ? (int) $post['member_joined'] : null;
      $authorOnline = View::isOnline($authorLast);
      $authorRep = (int) ($post['member_reputation'] ?? 0);
      $authorSolved = (int) ($solvedByAuthor[$aid] ?? 0);
      $authorRank = View::reputationRank($authorRep);
      $isLocalAuthorMod = $aid > 0 && !empty($localModAuthorIds[$aid]) && (int) ($post['member_is_admin'] ?? 0) === 0 && (int) ($post['member_is_moderator'] ?? 0) === 0;
      $authorCover = View::safeMediaUrl($post['member_cover_url'] ?? null);
      $authorBadges = $achievementsByAuthor[$aid] ?? [];
    ?>
    <article class="vf-post<?= $isBest ? ' vf-best-answer' : '' ?>" id="post-<?= $pid ?>">
      <div class="vf-post-author<?= $authorCover ? ' has-cover' : '' ?>"<?= $authorCover ? ' style="--vf-cover:url(\'' . View::e($authorCover) . '\');"' : '' ?>>
        <div style="display:flex;align-items:center;gap:12px;">
          <span class="vf-avatar vf-avatar-md" style="width:48px;height:48px;border-radius:14px;flex-shrink:0;">
            <?php if (!empty($post['avatar_url'])): ?>
              <img src="<?= View::e((string) $post['avatar_url']) ?>" alt="<?= View::e($authorName) ?>" data-vf-initials="<?= View::e($authorInitials) ?>" loading="lazy" decoding="async">
            <?php else: ?>
              <?= View::e($authorInitials) ?>
            <?php endif; ?>
            <?php if ($authorOnline): ?>
              <span class="vf-avatar-online" title="Aktywny teraz"></span>
            <?php endif; ?>
          </span>
          <div style="min-width:0;">
            <?php if ($aid > 0): ?>
              <a href="/member/<?= $aid ?>-<?= View::e($authorSlug) ?>" style="font-size:0.95rem;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= View::formatUsername($authorName, $post['group_color'] ?? null, $post['group_prefix'] ?? null, $post['group_suffix'] ?? null, $post['group_name'] ?? null) ?>
              </a>
            <?php else: ?>
              <div style="font-size:0.95rem;"><?= View::formatUsername($authorName, $post['group_color'] ?? null, $post['group_prefix'] ?? null, $post['group_suffix'] ?? null, $post['group_name'] ?? null) ?></div>
            <?php endif; ?>
            <div style="color:<?= View::e(View::safeGroupColor($post['group_color'] ?? null)) ?>;font-size:0.78rem;font-weight:700;display:flex;align-items:center;gap:5px;flex-wrap:wrap;">
              <span><?= View::e($post['group_name'] ?? 'Użytkownik') ?><?= $isLocalAuthorMod ? ' · Mod. działu' : '' ?></span>
            </div>
            <?php if (!empty($post['member_title'])): ?>
              <div class="vf-muted" style="font-size:0.74rem;margin-top:1px;"><?= View::e((string) $post['member_title']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="vf-author-rank" style="color:<?= View::e($authorRank['color']) ?>;">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>
          <span><?= View::e($authorRank['label']) ?></span>
        </div>

        <?php if ($authorBadges !== []): ?>
          <div class="vf-author-badges" aria-label="Odblokowane osiągnięcia">
            <?php foreach (array_slice($authorBadges, 0, 5) as $aBadge): ?>
              <span
                class="vf-author-badge-ico"
                style="background:<?= View::e(View::safeGroupColor($aBadge['color_hex'] ?? null, '#2563eb')) ?>;"
                title="<?= View::e((string) $aBadge['title']) ?>"
              ><?= View::achievementIconSvg((string) ($aBadge['icon'] ?? 'trophy')) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div>
          <span class="vf-presence-badge<?= $authorOnline ? ' is-online' : '' ?>">
            <span class="vf-presence-dot<?= $authorOnline ? ' is-online' : ' is-offline' ?>" aria-hidden="true"></span>
            <span><?= View::e(View::presenceText($authorLast, $authorJoined)) ?></span>
          </span>
        </div>

        <div class="vf-author-stats">
          <div><span>Posty</span><strong><?= (int) ($post['member_posts'] ?? 0) ?></strong></div>
          <div><span>Reputacja</span><strong style="color:var(--accent);"><?= $authorRep ?> pkt</strong></div>
          <div><span>Rozwiązania</span><strong><?= $authorSolved ?></strong></div>
        </div>

        <?php
          $mCustom = json_decode((string) ($post['member_custom_fields'] ?? '{}'), true);
          if (!is_array($mCustom)) { $mCustom = []; }
          $postVisibleFields = [];
          foreach (($profileFields ?? []) as $pf) {
            if ((int) ($pf['show_in_post'] ?? 0) === 1) {
              $val = trim((string) ($mCustom[$pf['field_key']] ?? ''));
              if ($val !== '') {
                $postVisibleFields[] = ['label' => (string) $pf['label'], 'value' => $val];
              }
            }
          }
        ?>
        <?php if ($postVisibleFields !== []): ?>
          <div class="vf-author-custom-fields">
            <?php foreach ($postVisibleFields as $pvf): ?>
              <div><span><?= View::e($pvf['label']) ?>:</span> <strong><?= View::e($pvf['value']) ?></strong></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="vf-post-body">
        <?php $pCreatedAt = (int) ($post['created_at'] ?? 0); ?>
        <div class="vf-muted" style="margin-bottom:12px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
          <span>
            Opublikowano <span title="<?= date('d.m.Y H:i', $pCreatedAt) ?>"><?= View::e(View::formatRelativeTime($pCreatedAt)) ?></span>
            <?php if (!empty($post['edited_at'])): ?>
              · Edytowano <span title="<?= date('d.m.Y H:i', (int) $post['edited_at']) ?>"><?= View::e(View::formatRelativeTime((int) $post['edited_at'])) ?></span> przez <?= View::e($post['edited_by_name'] ?? '') ?>
            <?php endif; ?>
            <?php if ($isBest): ?>
              · <strong class="vf-solved-label">Najlepsza odpowiedź</strong>
            <?php endif; ?>
          </span>
          <span style="display:inline-flex;gap:10px;align-items:center;">
            <?php if ($allowBestAnswer && (int) $post['is_first_post'] === 0 && ($isTopicOwner || $isModInForum)): ?>
              <form method="post" action="/topic/<?= (int) $topic['id'] ?>/best-answer" style="margin:0;">
                <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
                <input type="hidden" name="post_id" value="<?= $pid ?>">
                <button type="submit" class="vf-muted" style="background:none;border:0;padding:0;cursor:pointer;color:<?= $isBest ? '#059669' : 'var(--muted)' ?>;">
                  <?= $isBest ? 'Cofnij rozwiązanie' : 'Oznacz jako rozwiązanie' ?>
                </button>
              </form>
            <?php endif; ?>
            <a href="#post-<?= $pid ?>" class="vf-muted">#<?= $pid ?></a>
          </span>
        </div>
        <div class="vf-post-content" data-vf-post-content="<?= $pid ?>"><?= HtmlSanitizer::clean((string) $post['content_html']) ?></div>

        <?php $pAttachments = $postAttachments[$pid] ?? []; ?>
        <?php if ($pAttachments !== []): ?>
          <?php
            $imgAtts = array_values(array_filter($pAttachments, static fn (array $a): bool => (int) ($a['is_image'] ?? 0) === 1));
            $fileAtts = array_values(array_filter($pAttachments, static fn (array $a): bool => (int) ($a['is_image'] ?? 0) === 0));
          ?>
          <div class="vf-attachments-block">
            <?php if ($imgAtts !== []): ?>
              <div class="vf-attach-images">
                <?php foreach ($imgAtts as $imgA): ?>
                  <button type="button" class="vf-attach-img-btn" data-vf-lightbox-src="/attachment/<?= (int) $imgA['id'] ?>/download" data-vf-lightbox-alt="<?= View::e((string) $imgA['original_name']) ?>" title="<?= View::e((string) $imgA['original_name']) ?>">
                    <img src="/attachment/<?= (int) $imgA['id'] ?>/download" alt="<?= View::e((string) $imgA['original_name']) ?>" class="vf-attach-img" loading="lazy" decoding="async">
                  </button>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if ($fileAtts !== []): ?>
              <div class="vf-attach-files">
                <?php foreach ($fileAtts as $fileA): ?>
                  <?php
                    $fBytes = (int) ($fileA['file_size'] ?? 0);
                    $fSize = $fBytes >= 1048576 ? number_format($fBytes / 1048576, 1, ',', ' ') . ' MB' : max(1, (int) round($fBytes / 1024)) . ' KB';
                  ?>
                  <a href="/attachment/<?= (int) $fileA['id'] ?>/download" class="vf-attach-file-card">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    <span class="vf-attach-file-name"><?= View::e((string) $fileA['original_name']) ?></span>
                    <span class="vf-muted" style="font-size:0.72rem;white-space:nowrap;"><?= View::e($fSize) ?> · <?= (int) $fileA['downloads_count'] ?> pobr.</span>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($post['mod_note'])): ?>
          <div class="vf-mod-note" role="note" aria-label="Notatka moderatora">
            <div class="vf-mod-note-head">
              <span style="display:inline-flex;align-items:center;gap:6px;font-weight:700;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Notatka moderatora
              </span>
              <span class="vf-muted" style="font-size:0.75rem;">
                <?= View::e((string) ($post['mod_note_author'] ?? 'Moderator')) ?>
                <?php if (!empty($post['mod_note_at'])): ?>
                  · <?= View::e(View::formatRelativeTime((int) $post['mod_note_at'])) ?>
                <?php endif; ?>
              </span>
            </div>
            <div class="vf-mod-note-body"><?= nl2br(View::e((string) $post['mod_note'])) ?></div>
          </div>
        <?php endif; ?>

        <?php if (!empty($post['member_signature'])): ?>
          <div class="vf-post-signature"><?= View::e((string) $post['member_signature']) ?></div>
        <?php endif; ?>

        <?php
          $emojiMap = [
            'like'       => '👍',
            'thanks'     => '❤️',
            'helpful'    => '🙌',
            'insightful' => '💡',
          ];
          $userReactionId = $pReact['user_reaction'] ?? null;
          $activeDef = null;
          $totalReactions = 0;
          $summaryEmojis = [];
          foreach (($reactionDefs ?? []) as $rDef) {
            $rid = (int) $rDef['id'];
            $cnt = (int) ($pReact['counts'][$rid] ?? 0);
            $totalReactions += $cnt;
            if ($cnt > 0) {
              $summaryEmojis[] = ($emojiMap[$rDef['code']] ?? '👍');
            }
            if ($userReactionId === $rid) {
              $activeDef = $rDef;
            }
          }
          $btnEmoji = $activeDef ? ($emojiMap[$activeDef['code']] ?? '👍') : '👍';
          $btnLabel = $activeDef ? (string) $activeDef['label'] : 'Lubię to';
        ?>
        <?php
          $canEdit = !empty($currentUser) && (
              ((int) $post['author_id'] === (int) $currentUser['id'] && !empty($forumPerms['can_edit_own']))
              || $isModInForum
          );
          $canReport = !empty($currentUser) && !empty($forumPerms['can_report']);
          $canModPost = $isModInForum && (int) $post['is_first_post'] === 0;
        ?>
        <div class="vf-post-footer-bar" data-vf-react-widget="<?= $pid ?>">
          <div class="vf-post-footer-left">
            <?php if (!empty($currentUser)): ?>
              <div class="vf-react-picker-wrap">
                <button
                  type="button"
                  class="vf-react-trigger<?= $activeDef ? ' is-active' : '' ?>"
                  data-vf-react-trigger
                  aria-expanded="false"
                  aria-haspopup="true"
                >
                  <span data-vf-react-emoji><?= $btnEmoji ?></span>
                  <span data-vf-react-label><?= View::e($btnLabel) ?></span>
                </button>

                <div class="vf-react-popover" data-vf-react-popover role="menu" aria-label="Wybierz reakcję">
                  <?php foreach (($reactionDefs ?? []) as $rDef): ?>
                    <?php
                      $rid = (int) $rDef['id'];
                      $emoji = $emojiMap[$rDef['code']] ?? '👍';
                      $isMine = $userReactionId === $rid;
                    ?>
                    <form method="post" action="/post/<?= $pid ?>/react" data-vf-react-form style="display:inline;">
                      <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
                      <input type="hidden" name="reaction_id" value="<?= $rid ?>">
                      <button
                        type="submit"
                        class="vf-react-option<?= $isMine ? ' is-selected' : '' ?>"
                        data-reaction-id="<?= $rid ?>"
                        data-reaction-emoji="<?= $emoji ?>"
                        data-reaction-label="<?= View::e($rDef['label']) ?>"
                        title="<?= View::e($rDef['label']) ?>"
                        aria-label="<?= View::e($rDef['label']) ?>"
                      >
                        <span><?= $emoji ?></span>
                        <span class="vf-react-tip"><?= View::e($rDef['label']) ?></span>
                      </button>
                    </form>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php else: ?>
              <span class="vf-muted" style="font-size:0.75rem;">Zaloguj się, aby zareagować</span>
            <?php endif; ?>

            <div style="position:relative;">
              <button
                type="button"
                class="vf-react-summary"
                data-vf-react-summary
                data-post-id="<?= $pid ?>"
                title="Kliknij, aby zobaczyć kto zareagował"
                style="<?= $totalReactions === 0 ? 'display:none;' : '' ?>background:none;border:0;cursor:pointer;padding:4px 8px;border-radius:8px;"
              >
                <span data-vf-react-summary-icons><?= implode(' ', array_slice($summaryEmojis, 0, 3)) ?></span>
                <span data-vf-react-summary-count><?= $totalReactions ?></span>
              </button>
              <div class="vf-reactors-popover" data-vf-reactors-popover="<?= $pid ?>"></div>
            </div>
          </div>

          <div class="vf-post-actions-compact">
            <?php if (!empty($currentUser)): ?>
              <button
                type="button"
                class="vf-post-ico-btn"
                data-vf-quote-btn
                data-quote-author="<?= View::e($authorName) ?>"
                data-quote-post-id="<?= $pid ?>"
                data-tooltip="Cytuj wypowiedź"
                aria-label="Cytuj wypowiedź"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/></svg>
              </button>
              <?php if ($aid > 0): ?>
                <button
                  type="button"
                  class="vf-post-ico-btn"
                  data-vf-mention-btn
                  data-mention-id="<?= $aid ?>"
                  data-mention-username="<?= View::e($authorName) ?>"
                  data-mention-slug="<?= View::e($authorSlug) ?>"
                  data-mention-color="<?= View::e(View::safeGroupColor($post['group_color'] ?? null)) ?>"
                  data-tooltip="Oznacz @<?= View::e($authorName) ?>"
                  aria-label="Wspomnij @<?= View::e($authorName) ?> w odpowiedzi"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/></svg>
                </button>
              <?php endif; ?>
            <?php endif; ?>
            <?php if ($canEdit): ?>
              <button
                type="button"
                class="vf-post-ico-btn"
                data-vf-toggle-target="vf-edit-post-<?= $pid ?>"
                aria-expanded="false"
                data-tooltip="Edytuj post"
                aria-label="Edytuj post"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
              </button>
            <?php endif; ?>
            <button
              type="button"
              class="vf-post-ico-btn"
              data-vf-copy-link="#post-<?= $pid ?>"
              data-tooltip="Kopiuj link do posta"
              aria-label="Kopiuj link do posta"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            </button>
            <?php if ($canReport): ?>
              <button
                type="button"
                class="vf-post-ico-btn"
                data-vf-toggle-target="vf-report-post-<?= $pid ?>"
                aria-expanded="false"
                data-tooltip="Zgłoś post"
                aria-label="Zgłoś post"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" x2="4" y1="22" y2="15"/></svg>
              </button>
            <?php endif; ?>
            <?php if ($isModInForum): ?>
              <button
                type="button"
                class="vf-post-ico-btn<?= !empty($post['mod_note']) ? ' is-accent' : '' ?>"
                data-vf-toggle-target="vf-mod-note-<?= $pid ?>"
                aria-expanded="false"
                data-tooltip="<?= !empty($post['mod_note']) ? 'Edytuj notatkę moderatora' : 'Notatka moderatora' ?>"
                aria-label="Notatka moderatora"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              </button>
            <?php endif; ?>
            <?php if ($canModPost): ?>
              <form method="post" action="/mod/post/toggle-approve" style="margin:0;display:inline;">
                <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
                <input type="hidden" name="post_id" value="<?= $pid ?>">
                <input type="hidden" name="action" value="hide">
                <input type="hidden" name="redirect" value="/topic/<?= (int) $topic['id'] ?>">
                <button type="submit" class="vf-post-ico-btn" data-tooltip="Ukryj post (Mod)" aria-label="Ukryj post (Mod)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                </button>
              </form>
              <form method="post" action="/post/<?= $pid ?>/delete" data-vf-confirm-delete style="margin:0;display:inline;">
                <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
                <button type="submit" class="vf-post-ico-btn is-danger" data-tooltip="Usuń post (Mod)" data-confirm-label="Potwierdź?" aria-label="Usuń post (Mod)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($isModInForum): ?>
          <div class="vf-inline-panel vf-subbox" id="vf-mod-note-<?= $pid ?>" style="margin-top:12px;" hidden>
            <form method="post" action="/post/<?= $pid ?>/mod-note" style="display:grid;gap:8px;">
              <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
              <label style="font-size:0.8125rem;font-weight:600;">Oficjalna notatka moderatora widoczna pod tym postem</label>
              <textarea name="mod_note" rows="2" maxlength="1000" class="vf-textarea" placeholder="Np. Uwaga: prosimy trzymać się tematu lub zweryfikować załączone polecenia..."><?= View::e((string) ($post['mod_note'] ?? '')) ?></textarea>
              <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="submit" class="vf-btn vf-btn-sm">Zapisz notatkę</button>
                <?php if (!empty($post['mod_note'])): ?>
                  <button type="submit" name="clear_note" value="1" class="vf-btn vf-btn-sm vf-btn-danger">Usuń notatkę</button>
                <?php endif; ?>
                <button type="button" class="vf-btn vf-btn-sm vf-btn-secondary" data-vf-toggle-target="vf-mod-note-<?= $pid ?>">Anuluj</button>
              </div>
            </form>
          </div>
        <?php endif; ?>

        <?php if ($canEdit): ?>
          <div class="vf-inline-panel vf-subbox" id="vf-edit-post-<?= $pid ?>" style="margin-top:12px;" hidden>
            <form method="post" action="/post/<?= $pid ?>/edit" enctype="multipart/form-data" style="display:grid;gap:10px;">
              <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
              <textarea name="content" rows="4" class="vf-textarea" data-vf-wysiwyg required><?= View::e((string) $post['content_html']) ?></textarea>
              <div class="vf-attach-picker">
                <label class="vf-attach-drop">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                  <span>Dodaj nowe załączniki</span>
                  <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt,.log,.cfg,.ini,.json,.xml,.zip,.sql" data-vf-attach-input hidden>
                </label>
                <div class="vf-attach-preview-list" data-vf-attach-preview hidden></div>
              </div>
              <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="submit" class="vf-btn vf-btn-sm">Zapisz zmiany</button>
                <button type="button" class="vf-btn vf-btn-sm vf-btn-secondary" data-vf-toggle-target="vf-edit-post-<?= $pid ?>">Anuluj</button>
              </div>
            </form>
            <?php if (!empty($pAttachments)): ?>
              <div style="margin-top:10px;padding-top:10px;border-top:1px dashed var(--border);display:grid;gap:6px;">
                <span class="vf-muted" style="font-size:0.75rem;font-weight:700;">Obecne załączniki tego posta:</span>
                <?php foreach ($pAttachments as $attRow): ?>
                  <form method="post" action="/attachment/<?= (int) $attRow['id'] ?>/delete" style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin:0;font-size:0.8rem;">
                    <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
                    <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= View::e((string) $attRow['original_name']) ?></span>
                    <button type="submit" class="vf-btn vf-btn-sm vf-btn-danger" style="padding:3px 9px;font-size:0.74rem;">Usuń plik</button>
                  </form>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($canReport): ?>
          <div class="vf-inline-panel vf-subbox" id="vf-report-post-<?= $pid ?>" style="margin-top:12px;max-width:420px;" hidden>
            <form method="post" action="/post/<?= $pid ?>/report" style="display:grid;gap:8px;">
              <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
              <select name="category" class="vf-input">
                <option value="spam">Spam / reklama</option>
                <option value="harassment">Naruszenie kultury / obraźliwa treść</option>
                <option value="offtopic">Niezgodne z działem</option>
                <option value="other">Inny powód</option>
              </select>
              <input type="text" name="reason" maxlength="500" class="vf-input" placeholder="Opisz powód zgłoszenia..." required>
              <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="submit" class="vf-btn vf-btn-sm vf-btn-danger-solid">Wyślij zgłoszenie</button>
                <button type="button" class="vf-btn vf-btn-sm vf-btn-secondary" data-vf-toggle-target="vf-report-post-<?= $pid ?>">Anuluj</button>
              </div>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</section>

<?php if (($totalPages ?? 1) > 1): ?>
  <nav style="display:flex;gap:8px;margin-bottom:20px;" aria-label="Stronicowanie postów">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <a href="/topic/<?= (int) $topic['id'] ?>-<?= View::e($topic['slug']) ?>?page=<?= $p ?>"
         class="vf-page-btn<?= $p === $page ? ' is-active' : '' ?>">
        <?= $p ?>
      </a>
    <?php endfor; ?>
  </nav>
<?php endif; ?>

<?php if (!empty($currentUser) && !empty($forumPerms['can_reply']) && (!$isTopicLocked || $isModInForum)): ?>
  <section class="vf-card" id="reply-box">
    <div class="vf-card-head">
      Dodaj odpowiedź
      <?= (int) ($topic['forum_require_approval'] ?? 0) === 1 && !$isModInForum ? ' (wymaga akceptacji moderatora)' : '' ?>
    </div>
    <form method="post" action="/topic/<?= (int) $topic['id'] ?>/reply" enctype="multipart/form-data" style="padding:20px;display:grid;gap:12px;">
      <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
      <textarea name="content" rows="5" class="vf-textarea" data-vf-wysiwyg data-vf-reply-editor placeholder="Napisz odpowiedź... (wpisz / dla bloków lub @ aby wspomnieć użytkownika)" required></textarea>
      <div class="vf-attach-picker">
        <label class="vf-attach-drop">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
          <span>Dodaj załączniki lub obrazy (maks. 5 plików, do 5 MB każdy)</span>
          <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt,.log,.cfg,.ini,.json,.xml,.zip,.sql" data-vf-attach-input hidden>
        </label>
        <div class="vf-attach-preview-list" data-vf-attach-preview hidden></div>
      </div>
      <div><button type="submit" class="vf-btn">Wyślij odpowiedź</button></div>
    </form>
  </section>
<?php elseif (!empty($currentUser) && $isTopicLocked): ?>
  <div class="vf-card" style="padding:14px 20px;">
    <span class="vf-muted">Ten temat lub dział jest zamknięty — dodawanie nowych odpowiedzi zostało zablokowane.</span>
  </div>
<?php endif; ?>
