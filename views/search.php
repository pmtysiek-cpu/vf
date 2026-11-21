<?php use VeloForum\Core\View; ?>
<h1 style="margin:0 0 8px;">Wyszukiwarka forum</h1>
<form method="get" action="/search" style="display:flex;gap:10px;margin-bottom:20px;max-width:520px;">
  <input type="search" name="q" class="vf-input" value="<?= View::e($query) ?>" placeholder="Wpisz frazę (min. 2 znaki)..." required>
  <button type="submit" class="vf-btn">Szukaj</button>
</form>

<section class="vf-card">
  <div class="vf-card-head">Wyniki wyszukiwania</div>
  <?php if (empty($results)): ?>
    <div class="vf-row"><span class="vf-muted">Brak wyników dla podanego zapytania.</span></div>
  <?php endif; ?>
  <?php foreach ($results as $t): ?>
    <?php
      $authorBadge = View::formatUsername(
        (string) ($t['author_name'] ?? 'Gość'),
        $t['author_group_color'] ?? null,
        $t['author_group_prefix'] ?? null,
        $t['author_group_suffix'] ?? null,
        $t['author_group_name'] ?? null
      );
      $lpAt = (int) ($t['last_post_at'] ?? 0);
    ?>
    <div class="vf-row">
      <div style="min-width:0;">
        <a href="/topic/<?= (int) $t['id'] ?>-<?= View::e($t['slug']) ?>" style="font-weight:700;color:var(--ink);"><?= View::e($t['title']) ?></a>
        <div class="vf-muted" style="margin-top:3px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
          <span><?= View::e($t['forum_name']) ?></span>
          <span aria-hidden="true">·</span>
          <span>Autor:
            <?php if (!empty($t['author_id'])): ?>
              <a href="/member/<?= (int) $t['author_id'] ?>-<?= View::e((string) ($t['author_slug'] ?? 'profil')) ?>"><?= $authorBadge ?></a>
            <?php else: ?>
              <?= $authorBadge ?>
            <?php endif; ?>
          </span>
        </div>
      </div>
      <div class="vf-muted" style="white-space:nowrap;" title="<?= date('d.m.Y H:i', $lpAt) ?>"><?= View::e(View::formatRelativeTime($lpAt)) ?></div>
    </div>
  <?php endforeach; ?>
</section>
