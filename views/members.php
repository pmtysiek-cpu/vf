<?php use VeloForum\Core\View; ?>
<nav class="vf-breadcrumb" aria-label="Ścieżka nawigacji">
  <a href="/">Forum</a>
  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
  <span class="vf-breadcrumb-cur">Ludzie społeczności</span>
</nav>

<section class="vf-card">
  <div class="vf-card-head vf-card-head-flex">
    <span class="vf-head-icon-title">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span>Ludzie społeczności (<?= count($members) ?>)</span>
    </span>
    <form method="get" action="/members" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input type="search" name="q" value="<?= View::e($query) ?>" class="vf-input" style="width:190px;padding:7px 11px;font-size:0.84rem;" placeholder="Szukaj nicku...">
      <select name="sort" class="vf-input" style="width:auto;padding:7px 34px 7px 11px;font-size:0.84rem;" onchange="this.form.submit()">
        <option value="reputation" <?= $sort === 'reputation' ? 'selected' : '' ?>>Najwyższa reputacja</option>
        <option value="posts" <?= $sort === 'posts' ? 'selected' : '' ?>>Najwięcej postów</option>
        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Najnowsi użytkownicy</option>
      </select>
      <button type="submit" class="vf-btn vf-btn-sm">Filtruj</button>
    </form>
  </div>

  <?php if (empty($members)): ?>
    <div class="vf-row"><span class="vf-muted">Brak użytkowników pasujących do zapytania.</span></div>
  <?php else: ?>
    <div class="vf-members-grid">
      <?php foreach ($members as $m): ?>
        <?php
          $mid = (int) $m['id'];
          $initials = mb_strtoupper(mb_substr((string) $m['username'], 0, 2));
          $mLast = isset($m['last_activity_at']) ? (int) $m['last_activity_at'] : null;
          $mJoined = isset($m['joined_at']) ? (int) $m['joined_at'] : null;
          $mOnline = View::isOnline($mLast);
          $mCover = View::safeMediaUrl($m['cover_url'] ?? null);
          $mTopics = (int) ($topicsCountMap[$mid] ?? 0);
          $mBadges = $achievementsByMember[$mid] ?? [];
          $profileUrl = '/member/' . $mid . '-' . View::e((string) $m['slug']);
        ?>
        <article class="vf-member-card">
          <div class="vf-member-card-cover<?= $mCover ? ' has-cover' : '' ?>"<?= $mCover ? ' style="--vf-cover:url(\'' . View::e($mCover) . '\');"' : '' ?>></div>
          <div class="vf-member-card-body">
            <div class="vf-member-card-avatar-row">
              <a href="<?= $profileUrl ?>" class="vf-avatar vf-member-card-avatar">
                <?php if (!empty($m['avatar_url'])): ?>
                  <img src="<?= View::e((string) $m['avatar_url']) ?>" alt="<?= View::e($m['username']) ?>" data-vf-initials="<?= View::e($initials) ?>" loading="lazy" decoding="async">
                <?php else: ?>
                  <?= View::e($initials) ?>
                <?php endif; ?>
                <?php if ($mOnline): ?>
                  <span class="vf-avatar-online" title="Aktywny teraz"></span>
                <?php endif; ?>
              </a>
              <span class="vf-presence-badge<?= $mOnline ? ' is-online' : '' ?>">
                <span class="vf-presence-dot<?= $mOnline ? ' is-online' : ' is-offline' ?>" aria-hidden="true"></span>
                <span><?= $mOnline ? 'Online' : View::e(View::presenceText($mLast, $mJoined)) ?></span>
              </span>
            </div>

            <div>
              <a href="<?= $profileUrl ?>" style="font-size:1rem;font-weight:700;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= View::formatUsername(
                  (string) $m['username'],
                  $m['group_color'] ?? null,
                  $m['group_prefix'] ?? null,
                  $m['group_suffix'] ?? null,
                  $m['group_name'] ?? null
                ) ?>
              </a>
              <div style="font-size:0.78rem;font-weight:700;color:<?= View::e(View::safeGroupColor($m['group_color'] ?? null, '#64748b')) ?>;margin-top:2px;">
                <?= View::e((string) ($m['group_name'] ?? 'Użytkownik')) ?>
                <?php if (!empty($m['member_title'])): ?>
                  <span class="vf-muted" style="font-weight:500;"> · <?= View::e((string) $m['member_title']) ?></span>
                <?php endif; ?>
              </div>
            </div>

            <?php if ($mBadges !== []): ?>
              <div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;">
                <?php foreach (array_slice($mBadges, 0, 4) as $mb): ?>
                  <span
                    class="vf-author-badge-ico"
                    style="background:<?= View::e(View::safeGroupColor($mb['color_hex'] ?? null, '#2563eb')) ?>;"
                    title="<?= View::e((string) $mb['title']) ?>"
                  ><?= View::achievementIconSvg((string) ($mb['icon'] ?? 'trophy')) ?></span>
                <?php endforeach; ?>
                <span class="vf-muted" style="font-size:0.72rem;font-weight:600;"><?= count($mBadges) ?> osiągn.</span>
              </div>
            <?php endif; ?>

            <div class="vf-member-card-kpis">
              <div><strong><?= (int) $m['posts_count'] ?></strong><span>Postów</span></div>
              <div><strong><?= $mTopics ?></strong><span>Tematów</span></div>
              <div><strong style="color:var(--accent);"><?= (int) $m['reputation_points'] ?></strong><span>Reputacji</span></div>
            </div>

            <div class="vf-member-card-actions">
              <a href="<?= $profileUrl ?>" class="vf-btn vf-btn-sm" style="flex:1;">Zobacz profil</a>
              <?php if (!empty($currentUser) && (int) $currentUser['id'] !== $mid): ?>
                <a href="/messages?to=<?= $mid ?>" class="vf-btn vf-btn-sm vf-btn-secondary" title="Wyślij prywatną wiadomość">Wiadomość</a>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
