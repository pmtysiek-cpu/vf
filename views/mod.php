<?php use VeloForum\Core\Csrf; use VeloForum\Core\View; ?>
<?php
  $openReportsCount = 0;
  foreach ($reports as $repRow) {
    if (($repRow['status'] ?? '') === 'open') {
      $openReportsCount++;
    }
  }
?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:20px;">
  <div>
    <h1 style="margin:0 0 4px;">Panel Moderatora (ModCP)</h1>
    <p class="vf-muted" style="margin:0;">Zgłoszenia społeczności, kolejka moderacji, przenoszenie wątków, ostrzeżenia oraz dziennik akcji.</p>
  </div>
  <a href="/" class="vf-btn vf-btn-secondary">Powrót na forum</a>
</div>

<?php if (!empty($flash)): ?>
  <div class="vf-alert-success">
    Akcja moderatorska została zapisana i odnotowana w dzienniku.
  </div>
<?php endif; ?>

<?php if (empty($isGlobalStaff)): ?>
  <section class="vf-card" style="margin-bottom:16px;">
    <div class="vf-card-head">Zakres Twojej moderacji kategorii (IC5)</div>
    <div class="vf-row" style="flex-wrap:wrap;gap:8px;">
      <span class="vf-muted">Posiadasz uprawnienia moderatorskie delegowane dla wybranych kategorii i działów:</span>
      <?php if (!empty($supervisedForums)): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <?php foreach ($supervisedForums as $sf): ?>
            <a href="/forum/<?= (int) $sf['id'] ?>-<?= View::e($sf['slug']) ?>" class="vf-btn vf-btn-secondary">
              <?= View::e($sf['name']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
<?php endif; ?>

<div class="vf-kpi-grid">
  <a href="/mod?tab=reports" class="vf-kpi-card" style="text-decoration:none;">
    <span>Otwarte zgłoszenia</span>
    <strong class="<?= $openReportsCount > 0 ? 'vf-status-danger' : 'vf-status-ok' ?>"><?= $openReportsCount ?></strong>
  </a>
  <a href="/mod?tab=queue" class="vf-kpi-card" style="text-decoration:none;">
    <span>Ukryte posty</span>
    <strong class="<?= count($hiddenPosts) > 0 ? 'vf-status-warn' : '' ?>"><?= count($hiddenPosts) ?></strong>
  </a>
  <a href="/mod?tab=topics" class="vf-kpi-card" style="text-decoration:none;">
    <span>Aktywne tematy</span>
    <strong><?= count($recentTopics) ?></strong>
  </a>
  <a href="/mod?tab=logs" class="vf-kpi-card" style="text-decoration:none;">
    <span>Wpisy w dzienniku</span>
    <strong><?= count($modLogs) ?></strong>
  </a>
</div>

<section class="vf-card">
  <nav class="vf-tabs" aria-label="Zakładki Panelu Moderatora">
    <?php
    $tabs = [
      'reports'  => 'Zgłoszenia (' . $openReportsCount . ')',
      'queue'    => 'Ukryte posty (' . count($hiddenPosts) . ')',
      'topics'   => 'Tematy (Przenoszenie)',
      'warnings' => 'Ostrzeżenia i Bany',
      'logs'     => 'Dziennik Moderacji',
    ];
    foreach ($tabs as $key => $label):
    ?>
      <a href="/mod?tab=<?= $key ?>" class="vf-tab<?= $tab === $key ? ' is-active' : '' ?>">
        <?= View::e($label) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php if ($tab === 'reports'): ?>
    <div class="vf-card-head">Zgłoszenia postów (<?= count($reports) ?>)</div>
    <?php if (empty($reports)): ?>
      <div class="vf-row"><span class="vf-muted">Brak zgłoszeń w kolejce.</span></div>
    <?php endif; ?>
    <?php foreach ($reports as $rep): ?>
      <div class="vf-row" style="align-items:flex-start;flex-wrap:wrap;">
        <div style="flex:1;min-width:260px;">
          <div style="font-weight:700;">
            <a href="/topic/<?= (int) $rep['topic_id'] ?>-<?= View::e($rep['topic_slug']) ?>#post-<?= (int) $rep['post_id'] ?>">
              <?= View::e($rep['topic_title']) ?> (Post #<?= (int) $rep['post_id'] ?>)
            </a>
          </div>
          <div class="vf-muted" style="margin-top:4px;">
            Kategoria: <strong><?= View::e($rep['category']) ?></strong> · Zgłosił(a):
            <?= View::formatUsername((string) $rep['reporter_name'], $rep['reporter_color'] ?? null, $rep['reporter_prefix'] ?? null, $rep['reporter_suffix'] ?? null, $rep['reporter_group'] ?? null) ?>
            · <?= date('d.m.Y H:i', (int) $rep['created_at']) ?> ·
            Status: <span class="<?= $rep['status'] === 'open' ? 'vf-status-danger' : 'vf-status-ok' ?>"><?= View::e($rep['status']) ?></span>
          </div>
          <div style="margin-top:8px;font-size:0.875rem;">Powód: <?= View::e($rep['reason']) ?></div>
          <div class="vf-subbox vf-muted" style="margin-top:8px;">
            Autor posta: <?= View::formatUsername((string) $rep['post_author_name'], $rep['post_author_color'] ?? null, $rep['post_author_prefix'] ?? null, $rep['post_author_suffix'] ?? null, $rep['post_author_group'] ?? null) ?> — <?= View::e(mb_strimwidth(strip_tags((string) $rep['post_html']), 0, 180, '...')) ?>
          </div>
        </div>
        <?php if ($rep['status'] === 'open'): ?>
          <form method="post" action="/mod/report/handle" style="display:grid;gap:8px;min-width:260px;">
            <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="report_id" value="<?= (int) $rep['id'] ?>">
            <input type="text" name="note" class="vf-input" placeholder="Notatka moderatorska...">
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <button type="submit" name="decision" value="resolve_hide" class="vf-btn vf-btn-sm vf-btn-danger-solid">Rozwiąż i ukryj</button>
              <button type="submit" name="decision" value="resolve_warn" class="vf-btn vf-btn-sm vf-btn-danger">Rozwiąż + ostrz. (+1)</button>
              <button type="submit" name="decision" value="resolve" class="vf-btn vf-btn-sm">Rozwiąż</button>
              <button type="submit" name="decision" value="dismiss" class="vf-btn vf-btn-sm vf-btn-muted">Odrzuć</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

  <?php elseif ($tab === 'queue'): ?>
    <div class="vf-card-head">Ukryte posty oczekujące na decyzję (<?= count($hiddenPosts) ?>)</div>
    <?php if (empty($hiddenPosts)): ?>
      <div class="vf-row"><span class="vf-muted">Kolejka ukrytych postów jest pusta.</span></div>
    <?php endif; ?>
    <?php foreach ($hiddenPosts as $hp): ?>
      <div class="vf-row" style="flex-wrap:wrap;">
        <div style="flex:1;min-width:240px;">
          <div style="font-weight:700;"><?= View::e($hp['topic_title']) ?> (Post #<?= (int) $hp['id'] ?>)</div>
          <div class="vf-muted">
            Autor: <?= View::formatUsername((string) $hp['author_name'], $hp['author_group_color'] ?? null, $hp['author_group_prefix'] ?? null, $hp['author_group_suffix'] ?? null, $hp['author_group_name'] ?? null) ?>
            · <?= date('d.m.Y H:i', (int) $hp['created_at']) ?>
          </div>
          <div style="margin-top:6px;font-size:0.875rem;"><?= View::e(mb_strimwidth(strip_tags((string) $hp['content_html']), 0, 220, '...')) ?></div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <form method="post" action="/mod/post/toggle-approve" style="margin:0;">
            <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="post_id" value="<?= (int) $hp['id'] ?>">
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="vf-btn">Przywróć</button>
          </form>
          <form method="post" action="/mod/post/toggle-approve" data-vf-confirm-delete style="margin:0;">
            <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="post_id" value="<?= (int) $hp['id'] ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="vf-btn vf-btn-danger" data-confirm-label="Potwierdź trwałe usunięcie">Usuń trwale</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>

  <?php elseif ($tab === 'topics'): ?>
    <div class="vf-card-head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
      <span>Szybkie zarządzanie i przenoszenie tematów między działami</span>
      <input type="search" class="vf-input" style="max-width:260px;padding:6px 10px;font-size:0.8125rem;" data-vf-filter-input="mod-topics" placeholder="Filtruj tematy na żywo...">
    </div>
    <div data-vf-filter-list="mod-topics">
    <?php foreach ($recentTopics as $t): ?>
      <?php
        $tid = (int) $t['id'];
        $tPinned = (int) ($t['is_pinned'] ?? 0) === 1;
        $tLocked = (int) ($t['is_locked'] ?? 0) === 1;
      ?>
      <div class="vf-row" style="flex-wrap:wrap;gap:10px;" data-vf-filter-item="<?= View::e(mb_strtolower($t['title'] . ' ' . $t['forum_name'] . ' ' . $t['author_name'])) ?>">
        <div style="flex:1;min-width:240px;">
          <a href="/topic/<?= $tid ?>-<?= View::e($t['slug']) ?>" style="font-weight:600;"><?= View::e($t['title']) ?></a>
          <div class="vf-muted">
            Dział: <?= View::e($t['forum_name']) ?> · Autor:
            <?= View::formatUsername((string) $t['author_name'], $t['author_group_color'] ?? null, $t['author_group_prefix'] ?? null, $t['author_group_suffix'] ?? null, $t['author_group_name'] ?? null) ?>
            <?php if ($tPinned): ?> · <span style="color:var(--accent);font-weight:600;">Przypięty</span><?php endif; ?>
            <?php if ($tLocked): ?> · <span class="vf-status-warn">Zamknięty</span><?php endif; ?>
          </div>
        </div>
        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
          <form method="post" action="/topic/<?= $tid ?>/pin" style="margin:0;">
            <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="redirect" value="/mod?tab=topics">
            <button type="submit" class="vf-btn vf-btn-sm vf-btn-secondary"><?= $tPinned ? 'Odepnij' : 'Przypnij' ?></button>
          </form>
          <form method="post" action="/topic/<?= $tid ?>/lock" style="margin:0;">
            <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="redirect" value="/mod?tab=topics">
            <button type="submit" class="vf-btn vf-btn-sm vf-btn-secondary"><?= $tLocked ? 'Otwórz' : 'Zamknij' ?></button>
          </form>
          <form method="post" action="/mod/topic/move" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin:0;">
            <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="topic_id" value="<?= $tid ?>">
            <select name="target_forum_id" class="vf-input" style="width:190px;padding:6px 10px;">
              <?php foreach ($forums as $f): ?>
                <?php if ((int) $f['is_category'] === 0): ?>
                  <option value="<?= (int) $f['id'] ?>" <?= (int) $f['id'] === (int) $t['forum_id'] ? 'selected' : '' ?>>
                    <?= View::e($f['name']) ?>
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="vf-btn vf-btn-sm">Przenieś</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
    </div>

  <?php elseif ($tab === 'warnings'): ?>
    <div class="vf-card-head">Nadaj ostrzeżenie lub zablokuj użytkownika</div>
    <form method="post" action="/mod/member/warn" style="padding:20px;display:grid;gap:12px;max-width:600px;border-bottom:1px solid var(--border);">
      <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
      <select name="member_id" class="vf-input" required>
        <?php foreach ($members as $m): ?>
          <option value="<?= (int) $m['id'] ?>"><?= View::e($m['username']) ?> (<?= (int) ($m['warning_points'] ?? 0) ?>/10 pkt<?= (int) $m['is_banned'] === 1 ? ' · ZBANOWANY' : '' ?>)</option>
        <?php endforeach; ?>
      </select>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <input type="number" name="points" min="0" max="10" value="1" class="vf-input" placeholder="Punkty ostrzeżeń">
        <input type="number" name="ban_hours" min="0" max="8760" value="0" class="vf-input" placeholder="Czas bana w godz. (0 = bezterminowy)">
      </div>
      <input type="text" name="reason" maxlength="255" class="vf-input" placeholder="Powód ostrzeżenia / blokady..." required>
      <label style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" name="apply_ban" value="1"> Nałóż blokadę konta (Ban)
      </label>
      <div><button type="submit" class="vf-btn vf-btn-danger-solid">Zastosuj decyzję</button></div>
    </form>

    <div class="vf-card-head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
      <span>Stan ostrzeżeń i blokad użytkowników</span>
      <input type="search" class="vf-input" style="max-width:260px;padding:6px 10px;font-size:0.8125rem;" data-vf-filter-input="mod-members" placeholder="Filtruj użytkowników...">
    </div>
    <div data-vf-filter-list="mod-members">
    <?php foreach ($members as $m): ?>
      <?php
        $warnPts = (int) ($m['warning_points'] ?? 0);
        $isBanned = (int) ($m['is_banned'] ?? 0) === 1;
      ?>
      <div class="vf-row" style="flex-wrap:wrap;" data-vf-filter-item="<?= View::e(mb_strtolower($m['username'] . ' ' . ($m['group_name'] ?? ''))) ?>">
        <div>
          <a href="/member/<?= (int) $m['id'] ?>" style="font-weight:600;">
            <?= View::formatUsername((string) $m['username'], $m['color_hex'] ?? null, $m['group_prefix'] ?? null, $m['group_suffix'] ?? null, $m['group_name'] ?? null) ?>
          </a>
          <div class="vf-muted">Grupa: <?= View::e($m['group_name'] ?? 'Użytkownik') ?></div>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;font-variant-numeric:tabular-nums;">
          <span class="<?= $warnPts > 0 ? 'vf-status-warn' : 'vf-muted' ?>"><?= $warnPts ?>/10 pkt</span>
          <?php if ($isBanned): ?>
            <span class="vf-status-danger">Zbanowany</span>
          <?php else: ?>
            <span class="vf-status-ok">Aktywne</span>
          <?php endif; ?>
          <?php if ($warnPts > 0 || $isBanned): ?>
            <form method="post" action="/mod/member/clear-sanctions" style="margin:0;">
              <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
              <input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>">
              <button type="submit" class="vf-btn vf-btn-sm vf-btn-secondary">Cofnij bana / wyzeruj</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    </div>

  <?php elseif ($tab === 'logs'): ?>
    <div class="vf-card-head">Dziennik akcji moderatorskich</div>
    <?php if (empty($modLogs)): ?>
      <div class="vf-row"><span class="vf-muted">Brak zapisanych akcji w dzienniku.</span></div>
    <?php endif; ?>
    <?php foreach ($modLogs as $log): ?>
      <div class="vf-row">
        <div>
          <?= View::formatUsername((string) $log['moderator_name'], $log['moderator_color'] ?? null, $log['moderator_prefix'] ?? null, $log['moderator_suffix'] ?? null, $log['moderator_group'] ?? null) ?>
          · <span><?= View::e($log['action']) ?></span>
          <div class="vf-muted"><?= View::e($log['details']) ?></div>
        </div>
        <div class="vf-muted"><?= date('d.m.Y H:i', (int) $log['created_at']) ?></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
