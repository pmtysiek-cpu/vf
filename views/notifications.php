<?php use VeloForum\Core\Csrf; use VeloForum\Core\View; ?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:16px;">
  <h1 style="margin:0;">Powiadomienia</h1>
  <?php if (!empty($items)): ?>
    <form method="post" action="/notifications/read-all">
      <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
      <button type="submit" class="vf-btn">Oznacz wszystkie jako przeczytane</button>
    </form>
  <?php endif; ?>
</div>

<section class="vf-card">
  <?php if (empty($items)): ?>
    <div class="vf-row"><span class="vf-muted">Brak nowych powiadomień.</span></div>
  <?php endif; ?>
  <?php foreach ($items as $n): ?>
    <div class="vf-row vf-notif-row<?= (int) $n['is_read'] === 0 ? ' is-unread' : '' ?>">
      <div>
        <?php if (!empty($n['topic_id'])): ?>
          <a href="/topic/<?= (int) $n['topic_id'] ?>-<?= View::e($n['topic_slug'] ?? '') ?><?= !empty($n['post_id']) ? '#post-' . (int) $n['post_id'] : '' ?>" style="font-weight:600;">
            <?= View::e($n['message']) ?>
          </a>
        <?php else: ?>
          <span><?= View::e($n['message']) ?></span>
        <?php endif; ?>
        <div class="vf-muted"><?= date('d.m.Y H:i', (int) $n['created_at']) ?></div>
      </div>
      <div class="vf-muted"><?= (int) $n['is_read'] === 0 ? 'Nowe' : 'Przeczytane' ?></div>
    </div>
  <?php endforeach; ?>
</section>
