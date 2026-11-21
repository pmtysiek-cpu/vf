<?php use VeloForum\Core\Csrf; use VeloForum\Core\View; ?>
<?php
  $chevronRightSvg = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>';
  $msgIconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
  $lockIconSvg = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
  $pinSvg = '<svg class="vf-status-icon is-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"/></svg>';
  $topicLockSvg = '<svg class="vf-status-icon is-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
  $solvedSvg = '<svg class="vf-status-icon is-solved" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>';

  $canCreateHere = !empty($currentUser)
    && (int) $forum['is_category'] === 0
    && !empty($forumPerms['can_create_topic'])
    && ((int) ($forum['is_locked'] ?? 0) === 0 || !empty($forumPerms['can_moderate']));
  $canPollsHere = (int) ($forum['allow_polls'] ?? 1) === 1 && !empty($forumPerms['can_create_polls']);
  $openCompose = ($_GET['compose'] ?? '') === '1';
?>

<nav class="vf-breadcrumb" aria-label="Ścieżka nawigacji">
  <a href="/">Forum</a>
  <?php foreach ($breadcrumbs as $bIdx => $crumb): ?>
    <?= $chevronRightSvg ?>
    <?php if ($bIdx === count($breadcrumbs) - 1): ?>
      <span class="vf-breadcrumb-cur"><?= View::e($crumb['name']) ?></span>
    <?php else: ?>
      <a href="/forum/<?= (int) $crumb['id'] ?>-<?= View::e($crumb['slug']) ?>"><?= View::e($crumb['name']) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
</nav>

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:18px;">
  <div style="min-width:0;">
    <h1 style="margin:0 0 6px;font-size:1.5rem;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
      <span><?= View::e($forum['name']) ?></span>
      <?php if ((int) ($forum['is_locked'] ?? 0) === 1): ?>
        <span class="vf-muted" style="font-weight:600;">(Zamknięty)</span>
      <?php endif; ?>
    </h1>
    <?php if (!empty($forum['description'])): ?>
      <p class="vf-desc" style="margin:0 0 4px;"><?= View::e($forum['description']) ?></p>
    <?php endif; ?>
    <?php if (!empty($categoryModGroups)): ?>
      <div class="vf-muted">
        Moderatorzy kategorii:
        <?php foreach ($categoryModGroups as $mIdx => $mg): ?>
          <?= $mIdx > 0 ? ', ' : '' ?><strong style="color:<?= View::e((string) ($mg['color_hex'] ?? '#7c3aed')) ?>;"><?= View::e((string) $mg['name']) ?></strong>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <?php if (!empty($currentUser)): ?>
      <form method="post" action="/forum/<?= (int) $forum['id'] ?>/follow" style="margin:0;">
        <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
        <button type="submit" class="vf-react-trigger<?= !empty($isFollowing) ? ' is-active' : '' ?>">
          <?= !empty($isFollowing) ? 'Obserwujesz dział' : 'Obserwuj dział' ?> (<?= (int) ($followCount ?? 0) ?>)
        </button>
      </form>
      <?php if ($canCreateHere): ?>
        <button type="button" class="vf-btn vf-btn-sm" data-vf-toggle-target="vf-new-topic-panel" aria-expanded="<?= $openCompose ? 'true' : 'false' ?>">
          + Nowy temat
        </button>
      <?php endif; ?>
    <?php else: ?>
      <span class="vf-muted">Obserwujących: <?= (int) ($followCount ?? 0) ?></span>
      <?php if ((int) $forum['is_category'] === 0): ?>
        <a href="/login" class="vf-btn vf-btn-sm vf-btn-secondary">Zaloguj się, aby pisać</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($queuedNotice)): ?>
  <div class="vf-alert-success">
    Twój temat został zapisany i oczekuje w kolejce na akceptację moderatora działu.
  </div>
<?php endif; ?>

<?php if ($canCreateHere): ?>
  <section class="vf-card vf-inline-panel" id="vf-new-topic-panel"<?= $openCompose ? '' : ' hidden' ?>>
    <div class="vf-card-head vf-card-head-flex">
      <span>
        Nowy temat w: <?= View::e($forum['name']) ?>
        <?= (int) ($forum['require_approval'] ?? 0) === 1 && empty($forumPerms['can_moderate']) ? ' (wymaga akceptacji moderatora)' : '' ?>
      </span>
      <button type="button" class="vf-icon-btn" data-vf-toggle-target="vf-new-topic-panel" aria-label="Zamknij formularz">✕</button>
    </div>
    <form method="post" action="/forum/<?= (int) $forum['id'] ?>/topic" enctype="multipart/form-data" style="padding:20px;display:grid;gap:12px;">
      <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php if (!empty($prefixes)): ?>
          <select name="prefix_id" class="vf-input" style="width:auto;min-width:170px;">
            <option value="0">— Bez prefiksu —</option>
            <?php foreach ($prefixes as $px): ?>
              <option value="<?= (int) $px['id'] ?>"><?= View::e((string) $px['label']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
        <input type="text" name="title" maxlength="250" class="vf-input" style="flex:1;min-width:220px;" placeholder="Tytuł tematu" required>
      </div>
      <input type="text" name="tags" maxlength="160" class="vf-input" placeholder="Tagi po przecinku (opcjonalnie, np. PHP 8.3, IC5, MySQL)">
      <textarea name="content" rows="5" class="vf-textarea" data-vf-wysiwyg placeholder="Treść pierwszego posta... (wpisz / dla bloków lub @ aby wspomnieć użytkownika)" required></textarea>
      <div class="vf-attach-picker">
        <label class="vf-attach-drop">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
          <span>Dodaj załączniki lub obrazy (maks. 5 plików, do 5 MB każdy)</span>
          <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt,.log,.cfg,.ini,.json,.xml,.zip,.sql" data-vf-attach-input hidden>
        </label>
        <div class="vf-attach-preview-list" data-vf-attach-preview hidden></div>
      </div>
      <?php if ($canPollsHere): ?>
        <details>
          <summary class="vf-muted" style="cursor:pointer;font-weight:600;">+ Dołącz ankietę do tematu (opcjonalnie)</summary>
          <div class="vf-subbox" style="display:grid;gap:10px;margin-top:10px;">
            <input type="text" name="poll_question" maxlength="250" class="vf-input" placeholder="Pytanie ankiety">
            <textarea name="poll_options" rows="3" class="vf-textarea" placeholder="Opcje odpowiedzi — każda w nowej linii (min. 2, maks. 5)"></textarea>
          </div>
        </details>
      <?php endif; ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button type="submit" class="vf-btn">Opublikuj temat</button>
        <button type="button" class="vf-btn vf-btn-secondary" data-vf-toggle-target="vf-new-topic-panel">Anuluj</button>
      </div>
    </form>
  </section>
<?php elseif (!empty($currentUser) && (int) $forum['is_category'] === 0): ?>
  <div class="vf-card" style="padding:13px 18px;margin-bottom:18px;">
    <span class="vf-muted">
      <?= (int) ($forum['is_locked'] ?? 0) === 1 ? 'Ten dział jest obecnie zamknięty dla nowych tematów.' : 'Twoja grupa nie posiada uprawnień do tworzenia nowych tematów w tym dziale.' ?>
    </span>
  </div>
<?php endif; ?>

<?php if (!empty($subforums)): ?>
  <section class="vf-card">
    <div class="vf-card-head vf-card-head-flex">
      <span class="vf-head-icon-title">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>
        <span>Podkategorie i działy</span>
      </span>
    </div>
    <div class="vf-subcat-grid">
      <?php foreach ($subforums as $sub): ?>
        <?php
          $subId = (int) $sub['id'];
          $subLocked = (int) ($sub['is_locked'] ?? 0) === 1;
          $subUnread = !empty($unreadForumsMap[$subId]);
          $subIconClass = $subLocked ? 'is-locked' : ($subUnread ? 'is-unread' : '');
        ?>
        <div class="vf-subcat-card">
          <div class="vf-forum-main">
            <span class="vf-forum-icon <?= $subIconClass ?>" style="width:36px;height:36px;border-radius:10px;margin-top:0;" aria-hidden="true"><?= $subLocked ? $lockIconSvg : $msgIconSvg ?></span>
            <div style="min-width:0;flex:1;">
              <div class="vf-forum-title-row">
                <a href="/forum/<?= $subId ?>-<?= View::e($sub['slug']) ?>" class="vf-forum-link">
                  <?= View::e($sub['name']) ?>
                </a>
                <?php if ($subUnread): ?><span class="vf-unread-dot" title="Nowe posty"></span><?php endif; ?>
              </div>
              <?php if (!empty($sub['description'])): ?>
                <div class="vf-desc" style="margin-top:3px;font-size:0.8rem;"><?= View::e($sub['description']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="vf-subcat-foot">
            <?php if (!empty($sub['last_topic_id']) && !empty($sub['last_topic_title'])): ?>
              <?php
                $subLp = (string) ($sub['last_poster_name'] ?? 'U');
                $subLpAt = (int) ($sub['last_post_at'] ?? 0);
              ?>
              <div style="min-width:0;flex:1;">
                <a class="vf-last-post-title" style="font-size:0.78rem;" href="/topic/<?= (int) $sub['last_topic_id'] ?>-<?= View::e((string) ($sub['last_topic_slug'] ?? 'temat')) ?>">
                  <?= View::e((string) $sub['last_topic_title']) ?>
                </a>
                <div class="vf-last-post-sub" style="font-size:0.72rem;">
                  <?php if (!empty($sub['last_poster_id'])): ?>
                    <a href="/member/<?= (int) $sub['last_poster_id'] ?>-<?= View::e((string) ($sub['last_poster_slug'] ?? 'profil')) ?>">
                      <?= View::formatUsername($subLp, $sub['last_poster_color'] ?? null, $sub['last_poster_prefix'] ?? null, $sub['last_poster_suffix'] ?? null, $sub['last_poster_group'] ?? null) ?>
                    </a>
                  <?php else: ?>
                    <?= View::formatUsername($subLp, $sub['last_poster_color'] ?? null, $sub['last_poster_prefix'] ?? null, $sub['last_poster_suffix'] ?? null, $sub['last_poster_group'] ?? null) ?>
                  <?php endif; ?>
                  <?php if ($subLpAt > 0): ?> · <span title="<?= date('d.m.Y H:i', $subLpAt) ?>"><?= View::e(View::formatRelativeTime($subLpAt)) ?></span><?php endif; ?>
                </div>
              </div>
            <?php else: ?>
              <span class="vf-muted" style="font-size:0.75rem;">Brak tematów</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ((int) $forum['is_category'] === 0): ?>
<?php
  $curSort = $sort ?? 'activity';
  $curFilter = $filter ?? 'all';
  $curPrefix = (int) ($prefixFilter ?? 0);
  $baseUrl = '/forum/' . (int) $forum['id'] . '-' . View::e($forum['slug']);
?>
<section class="vf-card">
  <div class="vf-card-head vf-card-head-flex">
    <span style="font-weight:700;font-size:0.9rem;">Tematy (<?= count($topics) ?>)</span>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:0.78rem;font-weight:600;">
      <input type="search" class="vf-input vf-forum-search-inline" data-vf-filter-input="forum-topics" placeholder="Filtruj tematy..." aria-label="Filtruj tematy w dziale">
      <a href="<?= $baseUrl ?>?filter=all&amp;sort=<?= View::e($curSort) ?>&amp;prefix=<?= $curPrefix ?>" class="vf-tab<?= $curFilter === 'all' ? ' is-active' : '' ?>">Wszystkie</a>
      <a href="<?= $baseUrl ?>?filter=solved&amp;sort=<?= View::e($curSort) ?>&amp;prefix=<?= $curPrefix ?>" class="vf-tab<?= $curFilter === 'solved' ? ' is-active' : '' ?>">Rozwiązane</a>
      <a href="<?= $baseUrl ?>?filter=polls&amp;sort=<?= View::e($curSort) ?>&amp;prefix=<?= $curPrefix ?>" class="vf-tab<?= $curFilter === 'polls' ? ' is-active' : '' ?>">Z ankietą</a>
      <form method="get" action="<?= $baseUrl ?>" style="display:inline-flex;align-items:center;gap:6px;margin:0;flex-wrap:wrap;">
        <input type="hidden" name="filter" value="<?= View::e($curFilter) ?>">
        <?php if (!empty($prefixes)): ?>
          <select name="prefix" class="vf-input" style="width:auto;padding:5px 10px;font-size:0.78rem;" onchange="this.form.submit()">
            <option value="0">Wszystkie prefiksy</option>
            <?php foreach ($prefixes as $px): ?>
              <option value="<?= (int) $px['id'] ?>"<?= $curPrefix === (int) $px['id'] ? ' selected' : '' ?>><?= View::e((string) $px['label']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
        <select name="sort" class="vf-input" style="width:auto;padding:5px 10px;font-size:0.78rem;" onchange="this.form.submit()">
          <option value="activity"<?= $curSort === 'activity' ? ' selected' : '' ?>>Ostatnia aktywność</option>
          <option value="newest"<?= $curSort === 'newest' ? ' selected' : '' ?>>Najnowsze tematy</option>
          <option value="replies"<?= $curSort === 'replies' ? ' selected' : '' ?>>Najwięcej odpowiedzi</option>
          <option value="views"<?= $curSort === 'views' ? ' selected' : '' ?>>Najpopularniejsze</option>
        </select>
      </form>
    </div>
  </div>
  <?php if (empty($topics)): ?>
    <div class="vf-row"><span class="vf-muted">Brak tematów spełniających wybrane kryteria w tym dziale.</span></div>
  <?php endif; ?>
  <div data-vf-filter-list="forum-topics">
  <?php foreach ($topics as $t): ?>
    <?php
      $tid = (int) $t['id'];
      $tags = $topicTags[$tid] ?? [];
      $lpName = (string) ($t['last_poster_name'] ?? 'U');
      $lpInit = mb_strtoupper(mb_substr($lpName, 0, 2));
      $isUnread = !empty($unreadTopicsMap[$tid]);
      $tCreated = (int) ($t['created_at'] ?? 0);
      $tLast = (int) ($t['last_post_at'] ?? $tCreated);
      $tagSearchHay = implode(' ', array_map(static fn (array $tg): string => (string) $tg['tag_label'], $tags));
    ?>
    <div class="vf-forum-row" data-vf-filter-item="<?= View::e((string) $t['title'] . ' ' . (string) $t['author_name'] . ' ' . $lpName . ' ' . $tagSearchHay) ?>">
      <div style="min-width:0;">
        <div class="vf-topic-title-line">
          <?php if ((int) $t['is_pinned'] === 1): ?><?= $pinSvg ?><?php endif; ?>
          <?php if ((int) $t['is_locked'] === 1): ?><?= $topicLockSvg ?><?php endif; ?>
          <?php if (!empty($t['best_answer_post_id'])): ?><?= $solvedSvg ?><?php endif; ?>
          <?php if (!empty($t['prefix_label'])): ?>
            <span class="vf-prefix" style="color:<?= View::e((string) ($t['prefix_color'] ?? '#2563eb')) ?>;"><?= View::e((string) $t['prefix_label']) ?></span>
          <?php endif; ?>
          <a href="/topic/<?= $tid ?>-<?= View::e($t['slug']) ?>" class="vf-topic-link<?= $isUnread ? ' is-unread' : '' ?>">
            <?= View::e($t['title']) ?>
          </a>
          <?php if ((int) ($t['posts_count'] ?? 0) >= 10 || (int) ($t['views_count'] ?? 0) >= 250): ?>
            <span class="vf-hot-flag">· Gorący</span>
          <?php endif; ?>
        </div>
        <div class="vf-muted" style="margin-top:4px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
          <span>Autor:
            <?php if (!empty($t['author_id'])): ?>
              <a href="/member/<?= (int) $t['author_id'] ?>-<?= View::e((string) ($t['author_slug'] ?? 'profil')) ?>">
                <?= View::formatUsername((string) $t['author_name'], $t['author_group_color'] ?? null, $t['author_group_prefix'] ?? null, $t['author_group_suffix'] ?? null, $t['author_group_name'] ?? null) ?>
              </a>
            <?php else: ?>
              <?= View::formatUsername((string) $t['author_name'], $t['author_group_color'] ?? null, $t['author_group_prefix'] ?? null, $t['author_group_suffix'] ?? null, $t['author_group_name'] ?? null) ?>
            <?php endif; ?>
          </span>
          <span aria-hidden="true">·</span>
          <span title="<?= date('d.m.Y H:i', $tCreated) ?>"><?= View::e(View::formatRelativeTime($tCreated)) ?></span>
          <?php if ($isUnread): ?>
            <span aria-hidden="true">·</span>
            <span class="vf-unread-label">Nieprzeczytany</span>
          <?php endif; ?>
          <?php if (!empty($t['best_answer_post_id'])): ?>
            <span aria-hidden="true">·</span>
            <span class="vf-solved-label">Rozwiązany</span>
          <?php endif; ?>
          <?php if (!empty($t['has_poll'])): ?>
            <span aria-hidden="true">·</span>
            <span class="vf-unread-label">Ankieta</span>
          <?php endif; ?>
          <?php if (!empty($tags)): ?>
            <?php foreach ($tags as $tg): ?>
              <span aria-hidden="true">·</span>
              <a href="/tag/<?= View::e($tg['tag_slug']) ?>">#<?= View::e($tg['tag_label']) ?></a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="vf-forum-stats">
        <div><strong><?= max(0, (int) $t['posts_count'] - 1) ?></strong><span>odp.</span></div>
        <div><strong><?= (int) $t['views_count'] ?></strong><span>wyśw.</span></div>
      </div>
      <div class="vf-last-post">
        <span class="vf-avatar">
          <?php if (!empty($t['last_poster_avatar'])): ?>
            <img src="<?= View::e((string) $t['last_poster_avatar']) ?>" alt="<?= View::e($lpName) ?>" data-vf-initials="<?= View::e($lpInit) ?>" loading="lazy" decoding="async">
          <?php else: ?>
            <?= View::e($lpInit) ?>
          <?php endif; ?>
        </span>
        <div class="vf-last-post-meta">
          <div class="vf-last-post-title" style="font-weight:500;">
            <?php if (!empty($t['last_poster_id'])): ?>
              <a href="/member/<?= (int) $t['last_poster_id'] ?>-<?= View::e((string) ($t['last_poster_slug'] ?? 'profil')) ?>">
                <?= View::formatUsername($lpName, $t['last_poster_color'] ?? null, $t['last_poster_prefix'] ?? null, $t['last_poster_suffix'] ?? null, $t['last_poster_group'] ?? null) ?>
              </a>
            <?php else: ?>
              <?= View::formatUsername($lpName, $t['last_poster_color'] ?? null, $t['last_poster_prefix'] ?? null, $t['last_poster_suffix'] ?? null, $t['last_poster_group'] ?? null) ?>
            <?php endif; ?>
          </div>
          <div class="vf-last-post-sub" title="<?= date('d.m.Y H:i', $tLast) ?>">
            <?= View::e(View::formatRelativeTime($tLast)) ?>
            <?php if (!empty($t['last_post_id'])): ?>
              · <a href="/topic/<?= $tid ?>-<?= View::e($t['slug']) ?>#post-<?= (int) $t['last_post_id'] ?>">Ostatni →</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if (($totalPages ?? 1) > 1): ?>
  <nav style="display:flex;gap:8px;align-items:center;" aria-label="Stronicowanie tematów">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <a href="<?= $baseUrl ?>?page=<?= $p ?>&amp;filter=<?= View::e($curFilter) ?>&amp;sort=<?= View::e($curSort) ?>&amp;prefix=<?= $curPrefix ?>"
         class="vf-page-btn<?= $p === $page ? ' is-active' : '' ?>">
        <?= $p ?>
      </a>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
