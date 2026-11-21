<?php use VeloForum\Core\Csrf; use VeloForum\Core\View; ?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:20px;">
  <div>
    <h1 style="margin:0;">Panel Administracyjny (ACP)</h1>
    <p class="vf-muted" style="margin:4px 0 0;">Zarządzanie strukturą działów, kontami użytkowników, grupami, reakcjami i widocznością SEO.</p>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a href="/mod" class="vf-btn vf-btn-secondary">Panel Moderatora</a>
    <a href="/migrate" class="vf-btn">Migrator IC5</a>
  </div>
</div>

<?php if (!empty($flash)): ?>
  <div class="vf-alert-success">
    <?= View::e($flash) ?>
  </div>
<?php endif; ?>

<?php
$tabCounts = [
  'overview'     => count($migrationLogs),
  'forums'       => count($forums),
  'members'      => (int) $stats['members'],
  'groups'       => count($groups),
  'achievements' => count($achievements ?? []),
  'reactions'    => count($reactionDefs),
  'customize'    => count($announcements ?? []) + count($prefixes ?? []) + count($profileFields ?? []),
  'chat'         => (int) ($chatStats['messages'] ?? 0),
  'settings'     => null,
];
$tabs = [
  'overview'     => 'Przegląd i Logi',
  'forums'       => 'Działy i Podkategorie',
  'members'      => 'Użytkownicy',
  'groups'       => 'Grupy',
  'achievements' => 'Osiągnięcia i Nagrody',
  'reactions'    => 'Reakcje',
  'customize'    => 'Wygląd i Moduły',
  'chat'         => 'Czat na żywo',
  'settings'     => 'Ustawienia i SEO',
];
?>

<div class="vf-acp-shell">
  <nav class="vf-acp-nav" aria-label="Nawigacja ACP">
    <?php foreach ($tabs as $key => $label): ?>
      <a href="/admin?tab=<?= $key ?>" class="vf-acp-nav-link<?= $tab === $key ? ' is-active' : '' ?>">
        <span><?= View::e($label) ?></span>
        <?php if ($tabCounts[$key] !== null): ?>
          <span class="vf-muted" style="font-variant-numeric:tabular-nums;"><?= (int) $tabCounts[$key] ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div style="min-width:0;">
    <?php if ($tab === 'overview'): ?>
      <div class="vf-kpi-grid">
        <a href="/admin?tab=members" class="vf-kpi-card" style="text-decoration:none;">
          <span>Użytkownicy</span>
          <strong><?= (int) $stats['members'] ?></strong>
        </a>
        <a href="/admin?tab=forums" class="vf-kpi-card" style="text-decoration:none;">
          <span>Działy</span>
          <strong><?= (int) $stats['forums'] ?></strong>
        </a>
        <div class="vf-kpi-card">
          <span>Tematy</span>
          <strong><?= (int) $stats['topics'] ?></strong>
        </div>
        <div class="vf-kpi-card">
          <span>Posty</span>
          <strong><?= (int) $stats['posts'] ?></strong>
        </div>
        <a href="/admin?tab=reactions" class="vf-kpi-card" style="text-decoration:none;">
          <span>Reakcje</span>
          <strong><?= (int) $stats['reactions'] ?></strong>
        </a>
        <a href="/admin?tab=members" class="vf-kpi-card" style="text-decoration:none;">
          <span>Zbanowani</span>
          <strong class="<?= (int) $stats['banned'] > 0 ? 'vf-status-danger' : '' ?>"><?= (int) $stats['banned'] ?></strong>
        </a>
      </div>

      <section class="vf-card">
        <div class="vf-card-head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
          <span>Szybkie skróty administracyjne</span>
          <span class="vf-muted" style="font-weight:500;">Skrzynka konfiguracji systemu</span>
        </div>
        <div class="vf-row" style="flex-wrap:wrap;gap:10px;">
          <a href="/admin?tab=customize" class="vf-btn">Studio Wyglądu i Modułów</a>
          <a href="/admin?tab=forums" class="vf-btn vf-btn-secondary">Dodaj dział lub podkategorię</a>
          <a href="/admin?tab=members" class="vf-btn vf-btn-secondary">Utwórz / edytuj konto</a>
          <a href="/admin?tab=settings" class="vf-btn vf-btn-secondary">Konfiguracja SEO i podgląd SERP</a>
          <a href="/sitemap.xml" class="vf-btn vf-btn-secondary">Mapa strony (sitemap.xml)</a>
          <form method="post" action="/admin/maintenance/recount" style="margin:0;">
            <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
            <button type="submit" class="vf-btn vf-btn-secondary">Przelicz liczniki forum i postów</button>
          </form>
        </div>
      </section>

      <?php
        $hs = $healthStats ?? ['files' => 0, 'total_bytes' => 0, 'active_presence' => 0, 'open_reports' => 0];
        $hsBytes = (int) ($hs['total_bytes'] ?? 0);
        $hsSize = $hsBytes >= 1048576 ? number_format($hsBytes / 1048576, 1, ',', ' ') . ' MB' : max(0, (int) round($hsBytes / 1024)) . ' KB';
      ?>
      <section class="vf-card">
        <div class="vf-card-head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
          <span>Zdrowie systemu i konserwacja</span>
          <span class="vf-muted" style="font-weight:500;">PHP <?= View::e(PHP_VERSION) ?></span>
        </div>
        <div style="padding:16px 20px;display:grid;gap:14px;">
          <div class="vf-health-grid">
            <div class="vf-subbox">
              <div class="vf-muted" style="font-size:0.75rem;">Pliki w /uploads</div>
              <strong style="font-size:1.1rem;"><?= (int) ($hs['files'] ?? 0) ?> <span class="vf-muted" style="font-size:0.78rem;font-weight:500;">(<?= View::e($hsSize) ?>)</span></strong>
            </div>
            <div class="vf-subbox">
              <div class="vf-muted" style="font-size:0.75rem;">Aktywne sesje (20 min)</div>
              <strong style="font-size:1.1rem;"><?= (int) ($hs['active_presence'] ?? 0) ?></strong>
            </div>
            <div class="vf-subbox">
              <div class="vf-muted" style="font-size:0.75rem;">Otwarte zgłoszenia</div>
              <strong style="font-size:1.1rem;" class="<?= (int) ($hs['open_reports'] ?? 0) > 0 ? 'vf-status-danger' : 'vf-status-ok' ?>"><?= (int) ($hs['open_reports'] ?? 0) ?></strong>
            </div>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <form method="post" action="/admin/maintenance/prune-presence" style="margin:0;">
              <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
              <button type="submit" class="vf-btn vf-btn-sm vf-btn-secondary">Wyczyść wygasłe sesje obecności</button>
            </form>
            <a href="/admin/maintenance/export-json" class="vf-btn vf-btn-sm vf-btn-secondary">Eksportuj strukturę forum (JSON)</a>
          </div>
        </div>
      </section>

      <section class="vf-card">
        <div class="vf-card-head">Historia migracji bazy IC5</div>
        <?php if (empty($migrationLogs)): ?>
          <div class="vf-row"><span class="vf-muted">Brak wpisów w vf_migration_logs.</span></div>
        <?php endif; ?>
        <?php foreach ($migrationLogs as $log): ?>
          <div class="vf-row">
            <div>
              <strong><?= View::e($log['source_engine']) ?></strong> · <?= View::e($log['status']) ?>
              <div class="vf-muted">
                Działy: <?= (int) $log['forums_migrated'] ?> · Tematy: <?= (int) $log['topics_migrated'] ?> ·
                Posty: <?= (int) $log['posts_migrated'] ?> · Reakcje: <?= (int) ($log['reactions_migrated'] ?? 0) ?> ·
                Uprawnienia: <?= (int) ($log['permissions_migrated'] ?? 0) ?>
              </div>
            </div>
            <div class="vf-muted"><?= date('d.m.Y H:i', (int) $log['executed_at']) ?></div>
          </div>
        <?php endforeach; ?>
      </section>

    <?php elseif ($tab === 'forums'): ?>
      <?php if (!empty($editingForum)): ?>
        <?php $efid = (int) $editingForum['id']; ?>
        <section class="vf-card">
          <div class="vf-card-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <span>Zaawansowana edycja działu / kategorii (IC5): <?= View::e($editingForum['name']) ?> (#<?= $efid ?>)</span>
            <a href="/admin?tab=forums" class="vf-muted">← Wróć do drzewa działów</a>
          </div>
          <form method="post" action="/admin/forums/save" style="padding:20px;display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));border-bottom:1px solid var(--border);">
            <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="id" value="<?= $efid ?>">
            <label>Nazwa działu / kategorii
              <input type="text" name="name" class="vf-input" value="<?= View::e($editingForum['name']) ?>" required>
            </label>
            <label>Dział nadrzędny (przenoszenie w drzewie)
              <select name="parent_id" class="vf-input">
                <option value="0">— Kategoria główna —</option>
                <?php foreach ($forums as $pf): ?>
                  <?php if ((int) $pf['id'] !== $efid): ?>
                    <option value="<?= (int) $pf['id'] ?>" <?= (int) ($editingForum['parent_id'] ?? 0) === (int) $pf['id'] ? 'selected' : '' ?>>
                      <?= str_repeat('— ', (int) $pf['depth']) . View::e($pf['name']) ?>
                    </option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Typ węzła
              <select name="is_category" class="vf-input">
                <option value="0" <?= (int) $editingForum['is_category'] === 0 ? 'selected' : '' ?>>Dział / Podkategoria (z tematami)</option>
                <option value="1" <?= (int) $editingForum['is_category'] === 1 ? 'selected' : '' ?>>Kategoria (tylko kontener)</option>
              </select>
            </label>
            <label>Kolejność wyświetlania
              <input type="number" name="position" class="vf-input" value="<?= (int) $editingForum['position'] ?>">
            </label>
            <label style="grid-column:1/-1;">Przekierowanie URL (opcjonalnie, forum typu link zewnętrzny)
              <input type="url" name="redirect_url" class="vf-input" value="<?= View::e($editingForum['redirect_url'] ?? '') ?>" placeholder="https://...">
            </label>
            <label style="grid-column:1/-1;">Opis działu
              <input type="text" name="description" class="vf-input" value="<?= View::e($editingForum['description'] ?? '') ?>">
            </label>
            <label>Tytuł SEO (opcjonalnie)
              <input type="text" name="seo_title" class="vf-input" value="<?= View::e($editingForum['seo_title'] ?? '') ?>">
            </label>
            <label>Opis meta SEO (opcjonalnie)
              <input type="text" name="seo_description" class="vf-input" value="<?= View::e($editingForum['seo_description'] ?? '') ?>">
            </label>
            <div class="vf-subbox" style="grid-column:1/-1;display:flex;gap:18px;flex-wrap:wrap;align-items:center;">
              <label class="vf-muted">
                <input type="hidden" name="is_locked" value="0">
                <input type="checkbox" name="is_locked" value="1" <?= (int) ($editingForum['is_locked'] ?? 0) === 1 ? 'checked' : '' ?>>
                Dział zamknięty (tylko odczyt)
              </label>
              <label class="vf-muted">
                <input type="hidden" name="allow_polls" value="0">
                <input type="checkbox" name="allow_polls" value="1" <?= (int) ($editingForum['allow_polls'] ?? 1) === 1 ? 'checked' : '' ?>>
                Zezwalaj na ankiety
              </label>
              <label class="vf-muted">
                <input type="hidden" name="allow_best_answer" value="0">
                <input type="checkbox" name="allow_best_answer" value="1" <?= (int) ($editingForum['allow_best_answer'] ?? 1) === 1 ? 'checked' : '' ?>>
                Tryb Q&amp;A (Najlepsza odpowiedź)
              </label>
              <label class="vf-muted">
                <input type="hidden" name="require_approval" value="0">
                <input type="checkbox" name="require_approval" value="1" <?= (int) ($editingForum['require_approval'] ?? 0) === 1 ? 'checked' : '' ?>>
                Kolejka akceptacji nowych postów
              </label>
            </div>
            <div style="grid-column:1/-1;">
              <button type="submit" class="vf-btn">Zapisz ustawienia działu</button>
            </div>
          </form>

          <div class="vf-card-head">Macierz uprawnień grup i delegacja moderacji kategorii (IC5)</div>
          <form method="post" action="/admin/forums/permissions" style="padding:20px;display:grid;gap:14px;">
            <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="forum_id" value="<?= $efid ?>">
            <p class="vf-muted" style="margin:0;">
              Zaznaczenie <strong>Moderacja kategorii</strong> dla wybranej grupy odblokowuje jej członkom Panel Moderatora (ModCP) oraz moderację liniową wyłącznie dla tej kategorii i jej poddziałów.
            </p>
            <div style="overflow-x:auto;">
              <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                <thead>
                  <tr style="border-bottom:1px solid var(--border);text-align:left;">
                    <th style="padding:8px 10px;">Grupa użytkowników</th>
                    <th style="padding:8px 10px;text-align:center;">Widzi dział</th>
                    <th style="padding:8px 10px;text-align:center;">Czyta tematy</th>
                    <th style="padding:8px 10px;text-align:center;">Tworzy tematy</th>
                    <th style="padding:8px 10px;text-align:center;">Odpowiada</th>
                    <th style="padding:8px 10px;text-align:center;">Moderacja kategorii</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($groups as $g): ?>
                    <?php
                      $gid = (int) $g['id'];
                      $row = $permissionsMatrix[$efid][$gid] ?? null;
                      $gStaff = (int) $g['is_admin'] === 1 || (int) $g['is_moderator'] === 1;
                      $gPost = (int) $g['can_post'] === 1;
                      $cView = $row ? (int) $row['can_view'] === 1 : true;
                      $cRead = $row ? (int) $row['can_read'] === 1 : true;
                      $cTopic = $row ? (int) $row['can_create_topic'] === 1 : $gPost;
                      $cReply = $row ? (int) $row['can_reply'] === 1 : $gPost;
                      $cMod = $row ? (int) $row['can_moderate'] === 1 : $gStaff;
                    ?>
                    <tr style="border-bottom:1px solid var(--border);">
                      <td style="padding:8px 10px;font-weight:600;color:<?= View::e($g['color_hex']) ?>;">
                        <?= View::e($g['name']) ?>
                      </td>
                      <td style="padding:8px 10px;text-align:center;">
                        <input type="checkbox" name="perms[<?= $gid ?>][can_view]" value="1" <?= $cView ? 'checked' : '' ?>>
                      </td>
                      <td style="padding:8px 10px;text-align:center;">
                        <input type="checkbox" name="perms[<?= $gid ?>][can_read]" value="1" <?= $cRead ? 'checked' : '' ?>>
                      </td>
                      <td style="padding:8px 10px;text-align:center;">
                        <input type="checkbox" name="perms[<?= $gid ?>][can_create_topic]" value="1" <?= $cTopic ? 'checked' : '' ?>>
                      </td>
                      <td style="padding:8px 10px;text-align:center;">
                        <input type="checkbox" name="perms[<?= $gid ?>][can_reply]" value="1" <?= $cReply ? 'checked' : '' ?>>
                      </td>
                      <td style="padding:8px 10px;text-align:center;">
                        <input type="checkbox" name="perms[<?= $gid ?>][can_moderate]" value="1" <?= $cMod ? 'checked' : '' ?>>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <label class="vf-muted" style="display:flex;align-items:center;gap:8px;">
              <input type="hidden" name="cascade_children" value="0">
              <input type="checkbox" name="cascade_children" value="1" checked>
              Zastosuj uprawnienia kaskadowo również do wszystkich podkategorii i poddziałów
            </label>
            <div>
              <button type="submit" class="vf-btn">Zapisz macierz uprawnień</button>
            </div>
          </form>
        </section>
      <?php endif; ?>

      <section class="vf-card">
        <div class="vf-card-head">Dodaj nową kategorię, dział lub podkategorię</div>
        <form method="post" action="/admin/forums/save" style="padding:20px;display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
          <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
          <label>Nazwa <input type="text" name="name" class="vf-input" required></label>
          <label>Dział nadrzędny
            <select name="parent_id" class="vf-input">
              <option value="0">— Kategoria główna —</option>
              <?php foreach ($forums as $f): ?>
                <option value="<?= (int) $f['id'] ?>"><?= str_repeat('— ', (int) $f['depth']) . View::e($f['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Typ
            <select name="is_category" class="vf-input">
              <option value="0">Dział / Podkategoria (z tematami)</option>
              <option value="1">Kategoria (tylko kontener)</option>
            </select>
          </label>
          <label>Kolejność <input type="number" name="position" class="vf-input" value="1"></label>
          <label style="grid-column:1/-1;">Przekierowanie URL (opcjonalnie) <input type="url" name="redirect_url" class="vf-input" placeholder="https://..."></label>
          <label style="grid-column:1/-1;">Opis działu <input type="text" name="description" class="vf-input"></label>
          <label>Tytuł SEO (opcjonalnie) <input type="text" name="seo_title" class="vf-input"></label>
          <label>Opis meta SEO (opcjonalnie) <input type="text" name="seo_description" class="vf-input"></label>
          <div class="vf-subbox" style="grid-column:1/-1;display:flex;gap:18px;flex-wrap:wrap;align-items:center;">
            <label class="vf-muted"><input type="hidden" name="is_locked" value="0"><input type="checkbox" name="is_locked" value="1"> Dział zamknięty</label>
            <label class="vf-muted"><input type="hidden" name="allow_polls" value="0"><input type="checkbox" name="allow_polls" value="1" checked> Ankiety włączone</label>
            <label class="vf-muted"><input type="hidden" name="allow_best_answer" value="0"><input type="checkbox" name="allow_best_answer" value="1" checked> Tryb Q&amp;A</label>
            <label class="vf-muted"><input type="hidden" name="require_approval" value="0"><input type="checkbox" name="require_approval" value="1"> Wymaga akceptacji moderatora</label>
          </div>
          <div style="grid-column:1/-1;"><button type="submit" class="vf-btn">Utwórz dział</button></div>
        </form>
      </section>

      <section class="vf-card">
        <div class="vf-card-head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
          <span>Istniejące działy i podkategorie (<?= count($forums) ?>)</span>
          <input type="search" class="vf-input" style="max-width:260px;padding:6px 10px;font-size:0.8125rem;" data-vf-filter-input="acp-forums" placeholder="Filtruj działy na żywo...">
        </div>
        <?php
          $groupsById = [];
          foreach ($groups as $gItem) {
            $groupsById[(int) $gItem['id']] = $gItem;
          }
        ?>
        <div data-vf-filter-list="acp-forums">
        <?php foreach ($forums as $f): ?>
          <?php
            $fid = (int) $f['id'];
            $modLabels = [];
            foreach ($permissionsMatrix[$fid] ?? [] as $gid => $permRow) {
              if ((int) ($permRow['can_moderate'] ?? 0) === 1 && isset($groupsById[$gid])) {
                $gRow = $groupsById[$gid];
                if ((int) $gRow['is_admin'] === 0 && (int) $gRow['is_moderator'] === 0) {
                  $modLabels[] = $gRow['name'];
                }
              }
            }
          ?>
          <div class="vf-row" style="flex-wrap:wrap;gap:12px;" data-vf-filter-item="<?= View::e(mb_strtolower($f['name'] . ' ' . ($f['description'] ?? '') . ' #' . $fid)) ?>">
            <div style="flex:1;min-width:240px;">
              <div style="font-weight:600;padding-left:<?= (int) $f['depth'] * 18 ?>px;">
                <?= (int) $f['depth'] > 0 ? '↳ ' : '' ?><?= View::e($f['name']) ?>
                <?php if ((int) ($f['is_locked'] ?? 0) === 1): ?>
                  <span class="vf-status-danger" style="font-size:0.75rem;margin-left:6px;">Zamknięty</span>
                <?php endif; ?>
                <?php if ((int) ($f['require_approval'] ?? 0) === 1): ?>
                  <span class="vf-status-warn" style="font-size:0.75rem;margin-left:6px;">Moderacja wstępna</span>
                <?php endif; ?>
                <?php if (!empty($f['redirect_url'])): ?>
                  <span class="vf-muted" style="font-size:0.75rem;margin-left:6px;">Przekierowanie URL</span>
                <?php endif; ?>
              </div>
              <div class="vf-muted" style="padding-left:<?= (int) $f['depth'] * 18 ?>px;">
                ID #<?= $fid ?> · ścieżka: <?= View::e($f['materialized_path']) ?> · <?= (int) $f['topics_count'] ?> tematów
                <?php if (!empty($modLabels)): ?>
                  · Mod. kategorii: <strong style="color:var(--ink);"><?= View::e(implode(', ', $modLabels)) ?></strong>
                <?php endif; ?>
              </div>
            </div>
            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
              <form method="post" action="/admin/forums/reorder" style="margin:0;">
                <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
                <input type="hidden" name="id" value="<?= $fid ?>">
                <input type="hidden" name="direction" value="up">
                <button type="submit" class="vf-btn vf-btn-secondary" title="Przesuń wyżej">↑</button>
              </form>
              <form method="post" action="/admin/forums/reorder" style="margin:0;">
                <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
                <input type="hidden" name="id" value="<?= $fid ?>">
                <input type="hidden" name="direction" value="down">
                <button type="submit" class="vf-btn vf-btn-secondary" title="Przesuń niżej">↓</button>
              </form>
              <a href="/admin?tab=forums&edit_forum=<?= $fid ?>" class="vf-btn">Edytuj i Uprawnienia</a>
              <form method="post" action="/admin/forums/delete" data-vf-confirm-delete style="margin:0;">
                <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
                <input type="hidden" name="id" value="<?= $fid ?>">
                <button type="submit" class="vf-btn vf-btn-danger" data-confirm-label="Potwierdź usunięcie">Usuń</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
      </section>

    <?php elseif ($tab === 'members'): ?>
      <?php if (!empty($editingMember)): ?>
        <?php
          $secIds = json_decode((string) ($editingMember['secondary_group_ids'] ?? '[]'), true);
          if (!is_array($secIds)) { $secIds = []; }
          $bannedUntilVal = !empty($editingMember['banned_until']) ? date('Y-m-d\TH:i', (int) $editingMember['banned_until']) : '';
        ?>
        <section class="vf-card">
          <div class="vf-card-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <span>Zaawansowana edycja użytkownika: <?= View::e($editingMember['username']) ?> (#<?= (int) $editingMember['id'] ?>)</span>
            <a href="/admin?tab=members" class="vf-muted">← Wróć do listy</a>
          </div>
          <form method="post" action="/admin/members/update" style="padding:20px;display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">
            <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="member_id" value="<?= (int) $editingMember['id'] ?>">

            <label>Nazwa użytkownika
              <input type="text" name="username" class="vf-input" value="<?= View::e($editingMember['username']) ?>" required>
            </label>
            <label>Adres e-mail
              <input type="email" name="email" class="vf-input" value="<?= View::e($editingMember['email']) ?>" required>
            </label>
            <label>Tytuł / ranga własna
              <input type="text" name="member_title" class="vf-input" value="<?= View::e($editingMember['member_title'] ?? '') ?>">
            </label>
            <label>URL awatara
              <input type="url" name="avatar_url" class="vf-input" value="<?= View::e($editingMember['avatar_url'] ?? '') ?>">
            </label>
            <label>URL tła profilowego (Cover)
              <input type="text" name="cover_url" class="vf-input" value="<?= View::e($editingMember['cover_url'] ?? '') ?>" placeholder="https://... lub /uploads/covers/...">
            </label>
            <label>Muzyka profilowa (YouTube URL)
              <input type="url" name="music_url" class="vf-input" value="<?= View::e($editingMember['music_url'] ?? '') ?>" placeholder="https://www.youtube.com/watch?v=...">
            </label>

            <label>Grupa główna
              <select name="primary_group_id" class="vf-input">
                <?php foreach ($groups as $g): ?>
                  <option value="<?= (int) $g['id'] ?>" <?= (int) $editingMember['primary_group_id'] === (int) $g['id'] ? 'selected' : '' ?>>
                    <?= View::e($g['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>

            <div>
              <div style="font-size:0.875rem;margin-bottom:6px;">Dodatkowe grupy</div>
              <div class="vf-subbox" style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php foreach ($groups as $g): ?>
                  <label class="vf-muted">
                    <input type="checkbox" name="secondary_group_ids[]" value="<?= (int) $g['id'] ?>" <?= in_array((int) $g['id'], $secIds, true) ? 'checked' : '' ?>>
                    <?= View::e($g['name']) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <label>Liczba postów
              <input type="number" name="posts_count" class="vf-input" value="<?= (int) $editingMember['posts_count'] ?>">
            </label>
            <label>Punkty reputacji
              <input type="number" name="reputation_points" class="vf-input" value="<?= (int) $editingMember['reputation_points'] ?>">
            </label>
            <label>Punkty ostrzeżeń (0–10)
              <input type="number" min="0" max="10" name="warning_points" class="vf-input" value="<?= (int) ($editingMember['warning_points'] ?? 0) ?>">
            </label>
            <label>Nowe hasło (opcjonalnie, Argon2id)
              <input type="password" name="new_password" class="vf-input" placeholder="Pozostaw puste, aby nie zmieniać">
            </label>

            <label style="grid-column:1/-1;">O mnie / Bio
              <textarea name="bio" rows="2" class="vf-textarea"><?= View::e($editingMember['bio'] ?? '') ?></textarea>
            </label>
            <label style="grid-column:1/-1;">Sygnatura pod postami
              <input type="text" name="signature" class="vf-input" value="<?= View::e($editingMember['signature'] ?? '') ?>">
            </label>

            <?php if (!empty($profileFields)): ?>
              <?php
                $emCustom = json_decode((string) ($editingMember['custom_fields'] ?? '{}'), true);
                if (!is_array($emCustom)) { $emCustom = []; }
              ?>
              <?php foreach ($profileFields as $pf): ?>
                <?php $fKey = (string) $pf['field_key']; ?>
                <label><?= View::e((string) $pf['label']) ?>
                  <input type="text" name="custom_fields[<?= View::e($fKey) ?>]" class="vf-input" value="<?= View::e((string) ($emCustom[$fKey] ?? '')) ?>" placeholder="<?= View::e((string) ($pf['placeholder'] ?? '')) ?>">
                </label>
              <?php endforeach; ?>
            <?php endif; ?>

            <div class="vf-subbox" style="grid-column:1/-1;display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));align-items:end;">
              <label class="<?= (int) $editingMember['is_banned'] === 1 ? 'vf-status-danger' : 'vf-muted' ?>">
                <input type="checkbox" name="is_banned" value="1" <?= (int) $editingMember['is_banned'] === 1 ? 'checked' : '' ?>>
                Konto zablokowane (Ban)
              </label>
              <label>Powód blokady
                <input type="text" name="ban_reason" class="vf-input" value="<?= View::e($editingMember['ban_reason'] ?? '') ?>">
              </label>
              <label>Wygasa (opcjonalnie)
                <input type="datetime-local" name="banned_until" class="vf-input" value="<?= View::e($bannedUntilVal) ?>">
              </label>
            </div>

            <div style="grid-column:1/-1;">
              <button type="submit" class="vf-btn">Zapisz pełny profil użytkownika</button>
            </div>
          </form>
        </section>
      <?php endif; ?>

      <section class="vf-card">
        <div class="vf-card-head">Utwórz nowe konto użytkownika</div>
        <form method="post" action="/admin/members/create" style="padding:20px;display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));align-items:end;">
          <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
          <label>Nazwa <input type="text" name="username" class="vf-input" required></label>
          <label>E-mail <input type="email" name="email" class="vf-input" required></label>
          <label>Hasło (min. 8 zn.) <input type="password" name="password" class="vf-input" required></label>
          <label>Grupa
            <select name="primary_group_id" class="vf-input">
              <?php foreach ($groups as $g): ?>
                <option value="<?= (int) $g['id'] ?>"><?= View::e($g['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Tytuł <input type="text" name="member_title" class="vf-input"></label>
          <div><button type="submit" class="vf-btn">Dodaj konto</button></div>
        </form>
      </section>

      <?php
        $mCounts = $memberCounts ?? ['all' => count($members), 'staff' => 0, 'warned' => 0, 'banned' => 0];
        $curMFilter = (string) ($memberFilter ?? 'all');
        $mFilterTabs = [
          'all'    => 'Wszyscy (' . (int) ($mCounts['all'] ?? 0) . ')',
          'staff'  => 'Ekipa (' . (int) ($mCounts['staff'] ?? 0) . ')',
          'warned' => 'Ostrzeżeni (' . (int) ($mCounts['warned'] ?? 0) . ')',
          'banned' => 'Zbanowani (' . (int) ($mCounts['banned'] ?? 0) . ')',
        ];
      ?>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <?php foreach ($mFilterTabs as $fKey => $fLbl): ?>
            <a href="/admin?tab=members&amp;filter=<?= View::e($fKey) ?>&amp;q=<?= urlencode((string) $memberQuery) ?>" class="vf-tab<?= $curMFilter === $fKey ? ' is-active' : '' ?>" style="border-radius:8px;border:1px solid var(--border);background:var(--surface);">
              <?= View::e($fLbl) ?>
            </a>
          <?php endforeach; ?>
        </div>
        <form method="get" action="/admin" style="display:flex;gap:8px;max-width:420px;flex:1;">
          <input type="hidden" name="tab" value="members">
          <input type="hidden" name="filter" value="<?= View::e($curMFilter) ?>">
          <input type="search" name="q" class="vf-input" value="<?= View::e($memberQuery) ?>" placeholder="Szukaj po nazwie lub e-mailu...">
          <button type="submit" class="vf-btn">Szukaj</button>
        </form>
      </div>
      <section class="vf-card">
        <div class="vf-card-head">Konta użytkowników (<?= count($members) ?>)</div>
        <?php if (empty($members)): ?>
          <div class="vf-row"><span class="vf-muted">Brak użytkowników pasujących do wybranego filtra.</span></div>
        <?php endif; ?>
        <?php foreach ($members as $m): ?>
          <?php
            $warnPts = (int) ($m['warning_points'] ?? 0);
            $isBanned = (int) $m['is_banned'] === 1;
          ?>
          <div class="vf-row" style="flex-wrap:wrap;">
            <div>
              <strong><?= View::formatUsername((string) $m['username'], $m['color_hex'] ?? null, $m['group_prefix'] ?? null, $m['group_suffix'] ?? null, $m['group_name'] ?? null) ?></strong>
              <span class="vf-muted">(<?= View::e($m['email']) ?>)</span>
              <div class="vf-muted">
                ID #<?= (int) $m['id'] ?> · Grupa: <strong style="color:<?= View::e(View::safeGroupColor($m['color_hex'] ?? null)) ?>;"><?= View::e($m['group_name'] ?? '') ?></strong> ·
                Hash: <?= View::e($m['legacy_algo']) ?> ·
                <span class="<?= $warnPts > 0 ? 'vf-status-warn' : '' ?>">Ostrzeżenia: <?= $warnPts ?>/10</span>
                <?php if ($isBanned): ?>
                  · <span class="vf-status-danger">ZBANOWANY</span>
                <?php endif; ?>
              </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
              <a href="/member/<?= (int) $m['id'] ?>" class="vf-btn vf-btn-secondary">Profil</a>
              <a href="/admin?tab=members&edit=<?= (int) $m['id'] ?>" class="vf-btn">Zaawansowana edycja</a>
            </div>
          </div>
        <?php endforeach; ?>
      </section>

    <?php elseif ($tab === 'groups'): ?>
      <?php if (!empty($editingGroup)): ?>
        <?php $egid = (int) $editingGroup['id']; ?>
        <section class="vf-card">
          <div class="vf-card-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <span>Zaawansowana edycja grupy (IC5): <?= View::e($editingGroup['name']) ?> (#<?= $egid ?>)</span>
            <a href="/admin?tab=groups" class="vf-muted">← Wróć do listy grup</a>
          </div>
          <form method="post" action="/admin/groups/save" style="padding:20px;display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));border-bottom:1px solid var(--border);">
            <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="id" value="<?= $egid ?>">
            <label>Nazwa grupy
              <input type="text" name="name" class="vf-input" value="<?= View::e($editingGroup['name']) ?>" required>
            </label>
            <label>Kolor rangi (HEX)
              <input type="text" name="color_hex" class="vf-input" value="<?= View::e($editingGroup['color_hex']) ?>">
            </label>
            <label>Limit antyspamowy (sekundy)
              <input type="number" name="flood_seconds" min="0" max="3600" class="vf-input" value="<?= (int) ($editingGroup['flood_seconds'] ?? 30) ?>">
            </label>
            <label style="grid-column:1/-1;">Opis grupy
              <input type="text" name="description" class="vf-input" value="<?= View::e($editingGroup['description'] ?? '') ?>">
            </label>
            <label>Prefix HTML formatowania (IC5)
              <input type="text" name="prefix_html" class="vf-input" value="<?= View::e($editingGroup['prefix_html'] ?? '') ?>" placeholder="<span style='color:...'>">
            </label>
            <label>Suffix HTML formatowania (IC5)
              <input type="text" name="suffix_html" class="vf-input" value="<?= View::e($editingGroup['suffix_html'] ?? '') ?>" placeholder="</span>">
            </label>
            <div class="vf-subbox" style="grid-column:1/-1;display:flex;gap:16px;flex-wrap:wrap;align-items:center;">
              <label class="vf-muted"><input type="hidden" name="is_admin" value="0"><input type="checkbox" name="is_admin" value="1" <?= (int) $editingGroup['is_admin'] === 1 ? 'checked' : '' ?>> Globalny Administrator</label>
              <label class="vf-muted"><input type="hidden" name="is_moderator" value="0"><input type="checkbox" name="is_moderator" value="1" <?= (int) $editingGroup['is_moderator'] === 1 ? 'checked' : '' ?>> Globalny Moderator</label>
              <label class="vf-muted"><input type="hidden" name="can_post" value="0"><input type="checkbox" name="can_post" value="1" <?= (int) $editingGroup['can_post'] === 1 ? 'checked' : '' ?>> Może pisać posty</label>
              <label class="vf-muted"><input type="hidden" name="can_create_polls" value="0"><input type="checkbox" name="can_create_polls" value="1" <?= (int) ($editingGroup['can_create_polls'] ?? 1) === 1 ? 'checked' : '' ?>> Może zakładać ankiety</label>
              <label class="vf-muted"><input type="hidden" name="can_edit_own" value="0"><input type="checkbox" name="can_edit_own" value="1" <?= (int) ($editingGroup['can_edit_own'] ?? 1) === 1 ? 'checked' : '' ?>> Edycja własnych postów</label>
              <label class="vf-muted"><input type="hidden" name="can_report" value="0"><input type="checkbox" name="can_report" value="1" <?= (int) ($editingGroup['can_report'] ?? 1) === 1 ? 'checked' : '' ?>> Zgłaszanie treści</label>
              <label class="vf-muted"><input type="hidden" name="bypass_flood" value="0"><input type="checkbox" name="bypass_flood" value="1" <?= (int) ($editingGroup['bypass_flood'] ?? 0) === 1 ? 'checked' : '' ?>> Omija limit antyspamowy</label>
              <label class="vf-muted"><input type="hidden" name="show_in_legend" value="0"><input type="checkbox" name="show_in_legend" value="1" <?= (int) ($editingGroup['show_in_legend'] ?? 1) === 1 ? 'checked' : '' ?>> Widoczna w legendzie grup na stronie głównej</label>
              <label class="vf-muted"><input type="hidden" name="can_set_profile_cover" value="0"><input type="checkbox" name="can_set_profile_cover" value="1" <?= (int) ($editingGroup['can_set_profile_cover'] ?? 0) === 1 ? 'checked' : '' ?>> Może ustawiać tło profilu (Cover)</label>
              <label class="vf-muted"><input type="hidden" name="can_set_profile_music" value="0"><input type="checkbox" name="can_set_profile_music" value="1" <?= (int) ($editingGroup['can_set_profile_music'] ?? 0) === 1 ? 'checked' : '' ?>> Może ustawiać muzykę na profilu (YouTube)</label>
              <label class="vf-muted"><input type="hidden" name="can_use_chat" value="0"><input type="checkbox" name="can_use_chat" value="1" <?= (int) ($editingGroup['can_use_chat'] ?? 1) === 1 ? 'checked' : '' ?>> Może pisać na czacie na żywo</label>
              <label class="vf-muted"><input type="hidden" name="can_moderate_chat" value="0"><input type="checkbox" name="can_moderate_chat" value="1" <?= (int) ($editingGroup['can_moderate_chat'] ?? 0) === 1 ? 'checked' : '' ?>> Moderacja czatu (usuwanie, przypinanie, wyciszanie)</label>
            </div>
            <div style="grid-column:1/-1;">
              <button type="submit" class="vf-btn">Zapisz profil grupy</button>
            </div>
          </form>

          <div class="vf-card-head">Uprawnienia i moderacja kategorii dla grupy „<?= View::e($editingGroup['name']) ?>”</div>
          <form method="post" action="/admin/groups/permissions" style="padding:20px;display:grid;gap:14px;">
            <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="group_id" value="<?= $egid ?>">
            <div style="overflow-x:auto;">
              <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                <thead>
                  <tr style="border-bottom:1px solid var(--border);text-align:left;">
                    <th style="padding:8px 10px;">Kategoria / Dział</th>
                    <th style="padding:8px 10px;text-align:center;">Widzi</th>
                    <th style="padding:8px 10px;text-align:center;">Czyta</th>
                    <th style="padding:8px 10px;text-align:center;">Nowe tematy</th>
                    <th style="padding:8px 10px;text-align:center;">Odpowiedzi</th>
                    <th style="padding:8px 10px;text-align:center;">Moderuje kategorię</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                    $gStaff = (int) $editingGroup['is_admin'] === 1 || (int) $editingGroup['is_moderator'] === 1;
                    $gPost = (int) $editingGroup['can_post'] === 1;
                  ?>
                  <?php foreach ($forums as $fItem): ?>
                    <?php
                      $fid = (int) $fItem['id'];
                      $row = $permissionsMatrix[$fid][$egid] ?? null;
                      $cView = $row ? (int) $row['can_view'] === 1 : true;
                      $cRead = $row ? (int) $row['can_read'] === 1 : true;
                      $cTopic = $row ? (int) $row['can_create_topic'] === 1 : $gPost;
                      $cReply = $row ? (int) $row['can_reply'] === 1 : $gPost;
                      $cMod = $row ? (int) $row['can_moderate'] === 1 : $gStaff;
                    ?>
                    <tr style="border-bottom:1px solid var(--border);">
                      <td style="padding:8px 10px;padding-left:<?= 10 + ((int) $fItem['depth'] * 18) ?>px;font-weight:<?= (int) $fItem['is_category'] === 1 ? '700' : '500' ?>;">
                        <?= (int) $fItem['depth'] > 0 ? '↳ ' : '' ?><?= View::e($fItem['name']) ?>
                      </td>
                      <td style="padding:8px 10px;text-align:center;"><input type="checkbox" name="perms[<?= $fid ?>][can_view]" value="1" <?= $cView ? 'checked' : '' ?>></td>
                      <td style="padding:8px 10px;text-align:center;"><input type="checkbox" name="perms[<?= $fid ?>][can_read]" value="1" <?= $cRead ? 'checked' : '' ?>></td>
                      <td style="padding:8px 10px;text-align:center;"><input type="checkbox" name="perms[<?= $fid ?>][can_create_topic]" value="1" <?= $cTopic ? 'checked' : '' ?>></td>
                      <td style="padding:8px 10px;text-align:center;"><input type="checkbox" name="perms[<?= $fid ?>][can_reply]" value="1" <?= $cReply ? 'checked' : '' ?>></td>
                      <td style="padding:8px 10px;text-align:center;"><input type="checkbox" name="perms[<?= $fid ?>][can_moderate]" value="1" <?= $cMod ? 'checked' : '' ?>></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <label class="vf-muted" style="display:flex;align-items:center;gap:8px;">
              <input type="hidden" name="cascade_moderation" value="0">
              <input type="checkbox" name="cascade_moderation" value="1" checked>
              Kaskaduj uprawnienia moderacji kategorii w dół do wszystkich poddziałów
            </label>
            <div>
              <button type="submit" class="vf-btn">Zapisz uprawnienia grupy w działach</button>
            </div>
          </form>
        </section>
      <?php endif; ?>

      <section class="vf-card">
        <div class="vf-card-head">Utwórz nową grupę użytkowników (IC5)</div>
        <form method="post" action="/admin/groups/save" style="padding:20px;display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));align-items:end;">
          <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
          <input type="hidden" name="id" value="0">
          <label>Nazwa grupy <input type="text" name="name" class="vf-input" placeholder="np. Moderatorzy Sprzętu" required></label>
          <label>Kolor HEX <input type="text" name="color_hex" class="vf-input" value="#0f766e"></label>
          <label>Opis <input type="text" name="description" class="vf-input" placeholder="Uprawnienia i zakres"></label>
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="can_post" value="0"><label class="vf-muted"><input type="checkbox" name="can_post" value="1" checked> Może pisać</label>
            <input type="hidden" name="show_in_legend" value="0"><label class="vf-muted"><input type="checkbox" name="show_in_legend" value="1" checked> W legendzie grup</label>
            <input type="hidden" name="can_set_profile_cover" value="0"><label class="vf-muted"><input type="checkbox" name="can_set_profile_cover" value="1"> Tło profilu (Cover)</label>
            <input type="hidden" name="can_set_profile_music" value="0"><label class="vf-muted"><input type="checkbox" name="can_set_profile_music" value="1"> Muzyka profilowa</label>
            <input type="hidden" name="can_use_chat" value="0"><label class="vf-muted"><input type="checkbox" name="can_use_chat" value="1" checked> Dostęp do czatu</label>
            <input type="hidden" name="can_moderate_chat" value="0"><label class="vf-muted"><input type="checkbox" name="can_moderate_chat" value="1"> Moderacja czatu</label>
            <input type="hidden" name="can_create_polls" value="1">
            <input type="hidden" name="can_edit_own" value="1">
            <input type="hidden" name="can_report" value="1">
            <input type="hidden" name="flood_seconds" value="15">
          </div>
          <div><button type="submit" class="vf-btn">Dodaj grupę</button></div>
        </form>
      </section>

      <section class="vf-card">
        <div class="vf-card-head">Grupy użytkowników i uprawnienia (<?= count($groups) ?>)</div>
        <?php foreach ($groups as $g): ?>
          <?php
            $gid = (int) $g['id'];
            $catModCount = 0;
            foreach ($permissionsMatrix as $fid => $byGroup) {
              if ((int) ($byGroup[$gid]['can_moderate'] ?? 0) === 1) {
                $catModCount++;
              }
            }
          ?>
          <form method="post" action="/admin/groups/save" class="vf-row" style="flex-wrap:wrap;gap:10px;">
            <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="id" value="<?= $gid ?>">
            <input type="hidden" name="description" value="<?= View::e($g['description'] ?? '') ?>">
            <input type="hidden" name="prefix_html" value="<?= View::e($g['prefix_html'] ?? '') ?>">
            <input type="hidden" name="suffix_html" value="<?= View::e($g['suffix_html'] ?? '') ?>">
            <input type="hidden" name="can_create_polls" value="<?= (int) ($g['can_create_polls'] ?? 1) ?>">
            <input type="hidden" name="can_edit_own" value="<?= (int) ($g['can_edit_own'] ?? 1) ?>">
            <input type="hidden" name="can_report" value="<?= (int) ($g['can_report'] ?? 1) ?>">
            <input type="hidden" name="bypass_flood" value="<?= (int) ($g['bypass_flood'] ?? 0) ?>">
            <input type="hidden" name="flood_seconds" value="<?= (int) ($g['flood_seconds'] ?? 30) ?>">
            <input type="hidden" name="can_set_profile_cover" value="<?= (int) ($g['can_set_profile_cover'] ?? 0) ?>">
            <input type="hidden" name="can_set_profile_music" value="<?= (int) ($g['can_set_profile_music'] ?? 0) ?>">
            <input type="hidden" name="can_use_chat" value="<?= (int) ($g['can_use_chat'] ?? 1) ?>">
            <input type="hidden" name="can_moderate_chat" value="<?= (int) ($g['can_moderate_chat'] ?? 0) ?>">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;flex:1;">
              <input type="text" name="name" class="vf-input" style="width:180px;" value="<?= View::e($g['name']) ?>" required>
              <input type="text" name="color_hex" class="vf-input" style="width:96px;" value="<?= View::e($g['color_hex']) ?>">
              <label class="vf-muted"><input type="hidden" name="is_admin" value="0"><input type="checkbox" name="is_admin" value="1" <?= (int) $g['is_admin'] === 1 ? 'checked' : '' ?>> Admin</label>
              <label class="vf-muted"><input type="hidden" name="is_moderator" value="0"><input type="checkbox" name="is_moderator" value="1" <?= (int) $g['is_moderator'] === 1 ? 'checked' : '' ?>> Moderator</label>
              <label class="vf-muted"><input type="hidden" name="can_post" value="0"><input type="checkbox" name="can_post" value="1" <?= (int) $g['can_post'] === 1 ? 'checked' : '' ?>> Może pisać</label>
              <label class="vf-muted"><input type="hidden" name="show_in_legend" value="0"><input type="checkbox" name="show_in_legend" value="1" <?= (int) ($g['show_in_legend'] ?? 1) === 1 ? 'checked' : '' ?>> W legendzie</label>
              <?php if ($catModCount > 0 && (int) $g['is_admin'] === 0 && (int) $g['is_moderator'] === 0): ?>
                <span class="vf-muted" style="font-size:0.75rem;">Mod. kategorii: <strong><?= $catModCount ?></strong></span>
              <?php endif; ?>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
              <a href="/admin?tab=groups&edit_group=<?= $gid ?>" class="vf-btn vf-btn-secondary">Edycja IC5 i Kategorie</a>
              <button type="submit" class="vf-btn">Zapisz grupę</button>
            </div>
          </form>
        <?php endforeach; ?>
      </section>

    <?php elseif ($tab === 'reactions'): ?>
      <section class="vf-card">
        <div class="vf-card-head">Konfiguracja reakcji i punktów reputacji</div>
        <?php foreach ($reactionDefs as $r): ?>
          <form method="post" action="/admin/reactions/save" class="vf-row">
            <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
              <span class="vf-muted">Kod: <code><?= View::e($r['code']) ?></code></span>
              <input type="text" name="label" class="vf-input" style="width:180px;" value="<?= View::e($r['label']) ?>" required>
              <label class="vf-muted">Punkty: <input type="number" name="points" class="vf-input" style="width:80px;display:inline-block;" value="<?= (int) $r['points'] ?>"></label>
            </div>
            <button type="submit" class="vf-btn">Zapisz</button>
          </form>
        <?php endforeach; ?>
      </section>

    <?php elseif ($tab === 'customize'): ?>
      <?php
        $curAccent = (string) ($settings['accent_color'] ?? 'blue');
        $curDefaultTheme = (string) ($settings['default_theme'] ?? 'light');
        $curCardRadius = (string) ($settings['card_radius'] ?? 'rounded');
        $curLayout = (string) ($settings['forum_layout'] ?? 'list');
        $curSidebarPos = (string) ($settings['sidebar_position'] ?? 'right');
        $curHeroEnabled = ($settings['hero_enabled'] ?? '1') === '1';
        $curHeroStyle = (string) ($settings['hero_style'] ?? 'dark-gradient');
        $defaultWidgets = [
          ['id' => 'custom', 'label' => 'Blok własny HTML/CTA', 'enabled' => true],
          ['id' => 'activity', 'label' => 'Ostatnia aktywność', 'enabled' => true],
          ['id' => 'reputation', 'label' => 'Liderzy reputacji', 'enabled' => true],
          ['id' => 'tags', 'label' => 'Popularne tagi', 'enabled' => true],
          ['id' => 'reactions', 'label' => 'Statystyka reakcji', 'enabled' => true],
          ['id' => 'stats', 'label' => 'Statystyki forum', 'enabled' => true],
        ];
        $widgetConfig = json_decode((string) ($settings['sidebar_widgets_json'] ?? ''), true);
        if (!is_array($widgetConfig) || $widgetConfig === []) {
          $widgetConfig = $defaultWidgets;
        } else {
          $widgetConfig = array_values(array_filter(
            $widgetConfig,
            static fn (array $w): bool => (string) ($w['id'] ?? '') !== 'online'
          ));
        }
      ?>
      <section class="vf-card">
        <div class="vf-card-head">Studio Wyglądu, Układu, Hero, Stopki i Widżetów Bocznych</div>
        <form method="post" action="/admin/customize/save" style="padding:20px;display:grid;gap:14px;">
          <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
          <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));">
            <label>Paleta akcentu
              <select name="accent_color" class="vf-input">
                <option value="blue"<?= $curAccent === 'blue' ? ' selected' : '' ?>>Błękit (Klasyczny)</option>
                <option value="emerald"<?= $curAccent === 'emerald' ? ' selected' : '' ?>>Szmaragd (Nowoczesny)</option>
                <option value="indigo"<?= $curAccent === 'indigo' ? ' selected' : '' ?>>Indygo (Premium)</option>
                <option value="amber"<?= $curAccent === 'amber' ? ' selected' : '' ?>>Bursztyn (Ciepły)</option>
                <option value="rose"<?= $curAccent === 'rose' ? ' selected' : '' ?>>Rubin (Wyrazisty)</option>
                <option value="slate"<?= $curAccent === 'slate' ? ' selected' : '' ?>>Grafit (Minimalistyczny)</option>
              </select>
            </label>
            <label>Domyślny motyw (nowi goście)
              <select name="default_theme" class="vf-input">
                <option value="light"<?= $curDefaultTheme === 'light' ? ' selected' : '' ?>>Jasny (Domyślny)</option>
                <option value="dark"<?= $curDefaultTheme === 'dark' ? ' selected' : '' ?>>Ciemny (Dark Mode)</option>
              </select>
            </label>
            <label>Zaokrąglenie kart
              <select name="card_radius" class="vf-input">
                <option value="rounded"<?= $curCardRadius === 'rounded' ? ' selected' : '' ?>>Standardowe (14px)</option>
                <option value="sharp"<?= $curCardRadius === 'sharp' ? ' selected' : '' ?>>Techniczne (8px)</option>
                <option value="soft"<?= $curCardRadius === 'soft' ? ' selected' : '' ?>>Miękkie (20px)</option>
              </select>
            </label>
            <label>Domyślny układ działów
              <select name="forum_layout" class="vf-input">
                <option value="list"<?= $curLayout === 'list' ? ' selected' : '' ?>>Lista klasyczna</option>
                <option value="grid"<?= $curLayout === 'grid' ? ' selected' : '' ?>>Siatka kart (2 kolumny)</option>
                <option value="compact"<?= $curLayout === 'compact' ? ' selected' : '' ?>>Widok kompaktowy</option>
              </select>
            </label>
            <label>Pozycja panelu bocznego
              <select name="sidebar_position" class="vf-input">
                <option value="right"<?= $curSidebarPos === 'right' ? ' selected' : '' ?>>Po prawej</option>
                <option value="left"<?= $curSidebarPos === 'left' ? ' selected' : '' ?>>Po lewej</option>
                <option value="none"<?= $curSidebarPos === 'none' ? ' selected' : '' ?>>Ukryty (pełna szerokość)</option>
              </select>
            </label>
            <label>Styl banera Hero
              <select name="hero_style" class="vf-input">
                <option value="dark-gradient"<?= $curHeroStyle === 'dark-gradient' ? ' selected' : '' ?>>Ciemny gradient (Obsidian)</option>
                <option value="ocean"<?= $curHeroStyle === 'ocean' ? ' selected' : '' ?>>Oceaniczny błękit</option>
                <option value="clean"<?= $curHeroStyle === 'clean' ? ' selected' : '' ?>>Czysta karta jasna/ciemna</option>
              </select>
            </label>
          </div>

          <div class="vf-subbox" style="display:grid;gap:12px;">
            <label style="font-weight:600;display:flex;align-items:center;gap:8px;">
              <input type="checkbox" name="hero_enabled" value="1"<?= $curHeroEnabled ? ' checked' : '' ?>>
              Wyświetlaj sekcję powitalną Hero na stronie głównej
            </label>
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
              <label>Slogan (nad tytułem Hero i w stopce)
                <input type="text" name="site_tagline" class="vf-input" value="<?= View::e((string) ($settings['site_tagline'] ?? '')) ?>" placeholder="np. Społeczność serwerów i graczy">
              </label>
              <label>Nagłówek Hero
                <input type="text" name="hero_title" class="vf-input" value="<?= View::e((string) ($settings['hero_title'] ?? '')) ?>">
              </label>
              <label>Podtytuł Hero
                <input type="text" name="hero_subtitle" class="vf-input" value="<?= View::e((string) ($settings['hero_subtitle'] ?? '')) ?>">
              </label>
              <label>Przycisk CTA w Hero (etykieta)
                <input type="text" name="hero_cta_label" class="vf-input" value="<?= View::e((string) ($settings['hero_cta_label'] ?? '')) ?>" placeholder="Domyślnie: Co nowego">
              </label>
              <label>Link CTA w Hero (URL)
                <input type="text" name="hero_cta_url" class="vf-input" value="<?= View::e((string) ($settings['hero_cta_url'] ?? '')) ?>" placeholder="/activity">
              </label>
              <label>Własny tekst w stopce forum
                <input type="text" name="footer_text" class="vf-input" value="<?= View::e((string) ($settings['footer_text'] ?? '')) ?>" placeholder="np. © 2026 Twoja Sieć Serwerów">
              </label>
            </div>
          </div>

          <div class="vf-subbox" style="display:grid;gap:10px;">
            <div style="font-weight:600;">Kolejność i widoczność widżetów bocznych</div>
            <div style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));">
              <?php foreach ($widgetConfig as $idx => $w): ?>
                <?php $wid = (string) ($w['id'] ?? ''); ?>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--surface);">
                  <label style="display:flex;align-items:center;gap:8px;font-size:0.8125rem;font-weight:600;">
                    <input type="checkbox" name="widgets[<?= View::e($wid) ?>][enabled]" value="1"<?= !empty($w['enabled']) ? ' checked' : '' ?>>
                    <?= View::e((string) ($w['label'] ?? $wid)) ?>
                  </label>
                  <input type="number" name="widgets[<?= View::e($wid) ?>][order]" value="<?= $idx + 1 ?>" min="1" max="20" class="vf-input" style="width:62px;padding:4px 8px;text-align:center;" title="Kolejność">
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="vf-subbox" style="display:grid;gap:12px;">
            <div style="font-weight:600;">Kreator własnego bloku w panelu bocznym</div>
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
              <label>Tytuł bloku <input type="text" name="custom_widget_title" class="vf-input" value="<?= View::e((string) ($settings['custom_widget_title'] ?? '')) ?>"></label>
              <label>Etykieta przycisku <input type="text" name="custom_widget_link_label" class="vf-input" value="<?= View::e((string) ($settings['custom_widget_link_label'] ?? '')) ?>"></label>
              <label>Adres URL przycisku <input type="text" name="custom_widget_link_url" class="vf-input" value="<?= View::e((string) ($settings['custom_widget_link_url'] ?? '')) ?>"></label>
            </div>
            <label>Treść bloku
              <textarea name="custom_widget_body" rows="2" class="vf-textarea"><?= View::e((string) ($settings['custom_widget_body'] ?? '')) ?></textarea>
            </label>
          </div>

          <div><button type="submit" class="vf-btn">Zapisz wygląd i moduły</button></div>
        </form>
      </section>

      <section class="vf-card" id="announcements">
        <div class="vf-card-head">Ogłoszenia globalne i komunikaty społeczności</div>
        <form method="post" action="/admin/announcements/save" style="padding:20px;display:grid;gap:12px;border-bottom:1px solid var(--border);">
          <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
          <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));">
            <label>Tytuł ogłoszenia <input type="text" name="title" class="vf-input" required placeholder="np. Nowa aktualizacja forum"></label>
            <label>Styl komunikatu
              <select name="tone" class="vf-input">
                <option value="info">Informacja (Akcent)</option>
                <option value="success">Sukces (Zielony)</option>
                <option value="warning">Uwaga (Bursztynowy)</option>
              </select>
            </label>
            <label>Miejsce wyświetlania
              <select name="placement" class="vf-input">
                <option value="home">Tylko strona główna</option>
                <option value="all">Wszystkie podstrony</option>
              </select>
            </label>
          </div>
          <label>Treść komunikatu <input type="text" name="content" class="vf-input" required placeholder="Krótki opis dla użytkowników..."></label>
          <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));align-items:end;">
            <label>Przycisk CTA (opcjonalnie) <input type="text" name="link_label" class="vf-input" placeholder="np. Sprawdź zmiany"></label>
            <label>Link CTA (opcjonalnie) <input type="text" name="link_url" class="vf-input" placeholder="/activity"></label>
            <div><button type="submit" class="vf-btn">Dodaj ogłoszenie</button></div>
          </div>
        </form>
        <?php foreach (($announcements ?? []) as $ann): ?>
          <?php $annId = (int) $ann['id']; ?>
          <div class="vf-row" style="flex-wrap:wrap;align-items:flex-start;">
            <div style="flex:1;min-width:240px;">
              <strong><?= View::e((string) $ann['title']) ?></strong>
              <span class="vf-muted"> · <?= View::e((string) $ann['tone']) ?> · <?= ($ann['placement'] ?? 'home') === 'all' ? 'Wszystkie strony' : 'Strona główna' ?> · <?= (int) $ann['is_active'] === 1 ? 'Aktywne' : 'Ukryte' ?></span>
              <div class="vf-muted"><?= View::e((string) $ann['content']) ?></div>
              <div class="vf-subbox" id="vf-edit-ann-<?= $annId ?>" style="margin-top:10px;" hidden>
                <form method="post" action="/admin/announcements/save" style="display:grid;gap:10px;">
                  <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
                  <input type="hidden" name="id" value="<?= $annId ?>">
                  <div style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
                    <input type="text" name="title" class="vf-input" value="<?= View::e((string) $ann['title']) ?>" required>
                    <select name="tone" class="vf-input">
                      <option value="info"<?= ($ann['tone'] ?? 'info') === 'info' ? ' selected' : '' ?>>Informacja</option>
                      <option value="success"<?= ($ann['tone'] ?? '') === 'success' ? ' selected' : '' ?>>Sukces</option>
                      <option value="warning"<?= ($ann['tone'] ?? '') === 'warning' ? ' selected' : '' ?>>Uwaga</option>
                    </select>
                    <select name="placement" class="vf-input">
                      <option value="home"<?= ($ann['placement'] ?? 'home') === 'home' ? ' selected' : '' ?>>Strona główna</option>
                      <option value="all"<?= ($ann['placement'] ?? '') === 'all' ? ' selected' : '' ?>>Wszystkie podstrony</option>
                    </select>
                  </div>
                  <input type="text" name="content" class="vf-input" value="<?= View::e((string) $ann['content']) ?>" required>
                  <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <input type="text" name="link_label" class="vf-input" style="flex:1;min-width:140px;" value="<?= View::e((string) ($ann['link_label'] ?? '')) ?>" placeholder="Przycisk CTA">
                    <input type="text" name="link_url" class="vf-input" style="flex:1;min-width:140px;" value="<?= View::e((string) ($ann['link_url'] ?? '')) ?>" placeholder="Link CTA">
                    <button type="submit" class="vf-btn vf-btn-sm">Zapisz</button>
                  </div>
                </form>
              </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
              <button type="button" class="vf-btn vf-btn-secondary vf-btn-sm" data-vf-toggle-target="vf-edit-ann-<?= $annId ?>" aria-expanded="false">Edytuj</button>
              <form method="post" action="/admin/announcements/toggle" style="margin:0;">
                <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
                <input type="hidden" name="id" value="<?= $annId ?>">
                <button type="submit" class="vf-btn vf-btn-secondary vf-btn-sm"><?= (int) $ann['is_active'] === 1 ? 'Ukryj' : 'Aktywuj' ?></button>
              </form>
              <form method="post" action="/admin/announcements/delete" data-vf-confirm-delete style="margin:0;">
                <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
                <input type="hidden" name="id" value="<?= $annId ?>">
                <button type="submit" class="vf-btn vf-btn-danger vf-btn-sm" data-confirm-label="Potwierdź">Usuń</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </section>

      <section class="vf-card" id="prefixes">
        <div class="vf-card-head">Prefiksy tematów (Dodawanie i Edycja)</div>
        <form method="post" action="/admin/prefixes/save" style="padding:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:end;border-bottom:1px solid var(--border);">
          <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
          <label>Etykieta prefiksu <input type="text" name="label" class="vf-input" required placeholder="np. Pytanie"></label>
          <label>Kolor HEX <input type="color" name="color_hex" class="vf-input" value="#2563eb" style="width:74px;height:42px;padding:4px;"></label>
          <button type="submit" class="vf-btn">Dodaj prefiks</button>
        </form>
        <?php foreach (($prefixes ?? []) as $px): ?>
          <div class="vf-row" style="flex-wrap:wrap;gap:10px;">
            <form method="post" action="/admin/prefixes/save" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;flex:1;margin:0;">
              <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
              <input type="hidden" name="id" value="<?= (int) $px['id'] ?>">
              <span class="vf-prefix" style="color:<?= View::e((string) $px['color_hex']) ?>;"><?= View::e((string) $px['label']) ?></span>
              <input type="text" name="label" class="vf-input" style="width:160px;" value="<?= View::e((string) $px['label']) ?>" required>
              <input type="color" name="color_hex" class="vf-input" value="<?= View::e((string) $px['color_hex']) ?>" style="width:52px;height:38px;padding:3px;">
              <input type="number" name="position" class="vf-input" style="width:70px;" value="<?= (int) ($px['position'] ?? 0) ?>" title="Kolejność">
              <button type="submit" class="vf-btn vf-btn-sm">Zapisz</button>
            </form>
            <form method="post" action="/admin/prefixes/delete" data-vf-confirm-delete style="margin:0;">
              <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
              <input type="hidden" name="id" value="<?= (int) $px['id'] ?>">
              <button type="submit" class="vf-btn vf-btn-danger vf-btn-sm" data-confirm-label="Potwierdź">Usuń</button>
            </form>
          </div>
        <?php endforeach; ?>
      </section>

      <section class="vf-card" id="profile-fields">
        <div class="vf-card-head">Dodatkowe pola profilu użytkowników (Dodawanie i Edycja)</div>
        <form method="post" action="/admin/profile-fields/save" style="padding:20px;display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));align-items:end;border-bottom:1px solid var(--border);">
          <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
          <label>Nazwa pola <input type="text" name="label" class="vf-input" required placeholder="np. Discord"></label>
          <label>Klucz techniczny <input type="text" name="field_key" class="vf-input" placeholder="np. discord"></label>
          <label>Podpowiedź (placeholder) <input type="text" name="placeholder" class="vf-input" placeholder="np. nick#0001"></label>
          <label class="vf-muted" style="display:flex;align-items:center;gap:8px;padding-bottom:10px;">
            <input type="checkbox" name="show_in_post" value="1" checked> Pokazuj przy postach
          </label>
          <div><button type="submit" class="vf-btn">Dodaj pole profilu</button></div>
        </form>
        <?php foreach (($profileFields ?? []) as $pf): ?>
          <div class="vf-row" style="flex-wrap:wrap;gap:10px;">
            <form method="post" action="/admin/profile-fields/save" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;flex:1;margin:0;">
              <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
              <input type="hidden" name="id" value="<?= (int) $pf['id'] ?>">
              <input type="text" name="label" class="vf-input" style="width:150px;" value="<?= View::e((string) $pf['label']) ?>" required title="Nazwa pola">
              <input type="text" name="field_key" class="vf-input" style="width:120px;" value="<?= View::e((string) $pf['field_key']) ?>" required title="Klucz">
              <input type="text" name="placeholder" class="vf-input" style="width:150px;" value="<?= View::e((string) ($pf['placeholder'] ?? '')) ?>" placeholder="Placeholder">
              <input type="number" name="position" class="vf-input" style="width:66px;" value="<?= (int) ($pf['position'] ?? 0) ?>" title="Kolejność">
              <label class="vf-muted" style="display:flex;align-items:center;gap:6px;font-size:0.8125rem;">
                <input type="checkbox" name="show_in_post" value="1"<?= (int) ($pf['show_in_post'] ?? 0) === 1 ? ' checked' : '' ?>> Przy postach
              </label>
              <button type="submit" class="vf-btn vf-btn-sm">Zapisz</button>
            </form>
            <form method="post" action="/admin/profile-fields/delete" data-vf-confirm-delete style="margin:0;">
              <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
              <input type="hidden" name="id" value="<?= (int) $pf['id'] ?>">
              <button type="submit" class="vf-btn vf-btn-danger vf-btn-sm" data-confirm-label="Potwierdź">Usuń</button>
            </form>
          </div>
        <?php endforeach; ?>
      </section>

    <?php elseif ($tab === 'achievements'): ?>
      <?php
        $achIcons = [
          'trophy' => 'Puchar (Trophy)',
          'star'   => 'Gwiazda (Star)',
          'shield' => 'Tarcza (Shield)',
          'flame'  => 'Płomień (Flame)',
          'zap'    => 'Błyskawica (Zap)',
          'award'  => 'Medal (Award)',
          'heart'  => 'Serce (Heart)',
          'crown'  => 'Korona (Crown)',
        ];
        $achRules = [
          'manual'            => 'Nagroda ręczna (nadawana w ACP)',
          'posts_count'       => 'Liczba postów ≥ próg',
          'topics_count'      => 'Liczba tematów ≥ próg',
          'reputation_points' => 'Punkty reputacji ≥ próg',
          'solved_count'      => 'Najlepsze odpowiedzi ≥ próg',
        ];
      ?>
      <section class="vf-card">
        <div class="vf-card-head">Dodaj nowe osiągnięcie lub nagrodę</div>
        <form method="post" action="/admin/achievements/save" style="padding:20px;display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));align-items:end;">
          <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
          <input type="hidden" name="id" value="0">
          <label>Tytuł osiągnięcia <input type="text" name="title" class="vf-input" required placeholder="np. Mistrz Pomocy"></label>
          <label>Ikona
            <select name="icon" class="vf-input">
              <?php foreach ($achIcons as $ik => $il): ?>
                <option value="<?= View::e($ik) ?>"><?= View::e($il) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Kolor akcentu <input type="color" name="color_hex" class="vf-input" value="#2563eb" style="height:42px;padding:4px;"></label>
          <label>Warunek odblokowania
            <select name="rule_type" class="vf-input">
              <?php foreach ($achRules as $rk => $rl): ?>
                <option value="<?= View::e($rk) ?>"><?= View::e($rl) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Próg (dla automatycznych) <input type="number" name="rule_threshold" class="vf-input" min="1" value="1"></label>
          <label>Kolejność <input type="number" name="position" class="vf-input" value="<?= count($achievements ?? []) + 1 ?>"></label>
          <label style="grid-column:1/-1;">Opis osiągnięcia <input type="text" name="description" class="vf-input" placeholder="Za co przyznawana jest odznaka..."></label>
          <label class="vf-muted"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked> Aktywne</label>
          <div><button type="submit" class="vf-btn">Dodaj osiągnięcie</button></div>
        </form>
      </section>

      <section class="vf-card">
        <div class="vf-card-head">Lista osiągnięć i nagród (<?= count($achievements ?? []) ?>)</div>
        <?php if (empty($achievements)): ?>
          <div class="vf-row"><span class="vf-muted">Brak zdefiniowanych osiągnięć.</span></div>
        <?php endif; ?>
        <?php foreach (($achievements ?? []) as $ach): ?>
          <?php $achId = (int) $ach['id']; ?>
          <div class="vf-row" style="flex-wrap:wrap;align-items:flex-start;">
            <div style="display:flex;align-items:flex-start;gap:12px;flex:1;min-width:250px;">
              <span class="vf-achievement-icon" style="background:<?= View::e(View::safeGroupColor($ach['color_hex'] ?? null, '#2563eb')) ?>;color:#fff;border-color:transparent;">
                <?= View::achievementIconSvg((string) ($ach['icon'] ?? 'trophy')) ?>
              </span>
              <div style="min-width:0;flex:1;">
                <div style="font-weight:700;">
                  <?= View::e((string) $ach['title']) ?>
                  <span class="vf-muted" style="font-weight:500;"> · <?= View::e($achRules[$ach['rule_type']] ?? (string) $ach['rule_type']) ?><?= ($ach['rule_type'] ?? 'manual') !== 'manual' ? ' (' . (int) $ach['rule_threshold'] . ')' : '' ?> · <?= (int) $ach['is_active'] === 1 ? 'Aktywne' : 'Ukryte' ?></span>
                </div>
                <?php if (!empty($ach['description'])): ?>
                  <div class="vf-muted"><?= View::e((string) $ach['description']) ?></div>
                <?php endif; ?>
                <div class="vf-subbox" id="vf-edit-ach-<?= $achId ?>" style="margin-top:10px;" hidden>
                  <form method="post" action="/admin/achievements/save" style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));align-items:end;">
                    <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
                    <input type="hidden" name="id" value="<?= $achId ?>">
                    <label>Tytuł <input type="text" name="title" class="vf-input" value="<?= View::e((string) $ach['title']) ?>" required></label>
                    <label>Ikona
                      <select name="icon" class="vf-input">
                        <?php foreach ($achIcons as $ik => $il): ?>
                          <option value="<?= View::e($ik) ?>"<?= ($ach['icon'] ?? 'trophy') === $ik ? ' selected' : '' ?>><?= View::e($il) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label>Kolor <input type="color" name="color_hex" class="vf-input" value="<?= View::e(View::safeGroupColor($ach['color_hex'] ?? null, '#2563eb')) ?>" style="height:40px;padding:3px;"></label>
                    <label>Reguła
                      <select name="rule_type" class="vf-input">
                        <?php foreach ($achRules as $rk => $rl): ?>
                          <option value="<?= View::e($rk) ?>"<?= ($ach['rule_type'] ?? 'manual') === $rk ? ' selected' : '' ?>><?= View::e($rl) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label>Próg <input type="number" name="rule_threshold" class="vf-input" min="1" value="<?= (int) ($ach['rule_threshold'] ?? 1) ?>"></label>
                    <label>Pozycja <input type="number" name="position" class="vf-input" value="<?= (int) ($ach['position'] ?? 0) ?>"></label>
                    <label style="grid-column:1/-1;">Opis <input type="text" name="description" class="vf-input" value="<?= View::e((string) ($ach['description'] ?? '')) ?>"></label>
                    <input type="hidden" name="is_active" value="<?= (int) ($ach['is_active'] ?? 1) ?>">
                    <div><button type="submit" class="vf-btn vf-btn-sm">Zapisz zmiany</button></div>
                  </form>
                </div>
              </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
              <button type="button" class="vf-btn vf-btn-secondary vf-btn-sm" data-vf-toggle-target="vf-edit-ach-<?= $achId ?>" aria-expanded="false">Edytuj</button>
              <form method="post" action="/admin/achievements/toggle" style="margin:0;">
                <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
                <input type="hidden" name="id" value="<?= $achId ?>">
                <button type="submit" class="vf-btn vf-btn-secondary vf-btn-sm"><?= (int) $ach['is_active'] === 1 ? 'Wyłącz' : 'Włącz' ?></button>
              </form>
              <form method="post" action="/admin/achievements/delete" data-vf-confirm-delete style="margin:0;">
                <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
                <input type="hidden" name="id" value="<?= $achId ?>">
                <button type="submit" class="vf-btn vf-btn-danger vf-btn-sm" data-confirm-label="Potwierdź">Usuń</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </section>

      <section class="vf-card" id="grant-award">
        <div class="vf-card-head">Przyznaj nagrodę lub osiągnięcie użytkownikowi</div>
        <form method="post" action="/admin/achievements/grant" style="padding:20px;display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));align-items:end;border-bottom:1px solid var(--border);">
          <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
          <label>Użytkownik
            <select name="member_id" class="vf-input" required>
              <?php foreach (($allMembersBrief ?? []) as $mb): ?>
                <option value="<?= (int) $mb['id'] ?>"><?= View::e((string) $mb['username']) ?> (#<?= (int) $mb['id'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Osiągnięcie / Nagroda
            <select name="achievement_id" class="vf-input" required>
              <?php foreach (($achievements ?? []) as $ach): ?>
                <option value="<?= (int) $ach['id'] ?>"><?= View::e((string) $ach['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Powód / dedykacja (opcjonalnie) <input type="text" name="note" maxlength="200" class="vf-input" placeholder="np. Zwycięzca konkursu wiosennego"></label>
          <div><button type="submit" class="vf-btn">Przyznaj odznakę</button></div>
        </form>
        <div class="vf-card-head">Ostatnio przyznane ręczne nagrody (<?= count($recentAwards ?? []) ?>)</div>
        <?php if (empty($recentAwards)): ?>
          <div class="vf-row"><span class="vf-muted">Brak ręcznie przyznanych nagród.</span></div>
        <?php else: ?>
          <?php foreach ($recentAwards as $aw): ?>
            <div class="vf-row" style="flex-wrap:wrap;gap:10px;">
              <div>
                <strong><?= View::e((string) ($aw['member_name'] ?? 'Użytkownik')) ?></strong>
                <span class="vf-muted">otrzymał(a)</span>
                <strong style="color:<?= View::e(View::safeGroupColor($aw['color_hex'] ?? null, '#2563eb')) ?>;"><?= View::e((string) ($aw['achievement_title'] ?? '')) ?></strong>
                <span class="vf-muted">· od <?= View::e((string) ($aw['awarded_by_name'] ?? 'Admin')) ?> · <?= View::e(View::formatRelativeTime((int) ($aw['awarded_at'] ?? time()))) ?></span>
                <?php if (!empty($aw['note'])): ?>
                  <div class="vf-muted">Notatka: „<?= View::e((string) $aw['note']) ?>”</div>
                <?php endif; ?>
              </div>
              <form method="post" action="/admin/achievements/revoke" style="margin:0;">
                <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
                <input type="hidden" name="achievement_id" value="<?= (int) $aw['achievement_id'] ?>">
                <input type="hidden" name="member_id" value="<?= (int) $aw['member_id'] ?>">
                <button type="submit" class="vf-btn vf-btn-danger vf-btn-sm">Cofnij</button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>

    <?php elseif ($tab === 'chat'): ?>
      <?php
        $chatEnabledVal = ($settings['chat_enabled'] ?? '1') === '1';
        $chatTitleVal = (string) ($settings['chat_title'] ?? 'Czat społeczności');
        $chatAnnouncementVal = (string) ($settings['chat_announcement'] ?? '');
        $chatPosVal = (string) ($settings['chat_position'] ?? 'top');
        $chatMaxLenVal = (int) ($settings['chat_max_length'] ?? 400);
        $chatFloodVal = (int) ($settings['chat_flood_seconds'] ?? 3);
        $chatGuestsVal = ($settings['chat_allow_guests'] ?? '1') === '1';
        $chatGifVal = ($settings['chat_allow_gif'] ?? '1') === '1';
        $chatReactsVal = ($settings['chat_allow_reactions'] ?? '1') === '1';
        $cStats = $chatStats ?? ['messages' => 0, 'muted' => 0];
      ?>
      <div class="vf-kpi-grid">
        <div class="vf-kpi-card">
          <span>Wiadomości na czacie</span>
          <strong><?= (int) ($cStats['messages'] ?? 0) ?></strong>
        </div>
        <div class="vf-kpi-card">
          <span>Wyciszeni użytkownicy</span>
          <strong><?= (int) ($cStats['muted'] ?? 0) ?></strong>
        </div>
        <div class="vf-kpi-card">
          <span>Status modułu</span>
          <strong class="<?= $chatEnabledVal ? 'vf-status-ok' : 'vf-status-danger' ?>"><?= $chatEnabledVal ? 'Włączony' : 'Wyłączony' ?></strong>
        </div>
      </div>

      <section class="vf-card">
        <div class="vf-card-head">Konfiguracja modułu Czatu na żywo</div>
        <form method="post" action="/admin/chat/settings" style="padding:20px;display:grid;gap:14px;">
          <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
          <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
            <label>Nagłówek czatu
              <input type="text" name="chat_title" class="vf-input" maxlength="80" value="<?= View::e($chatTitleVal) ?>" required>
            </label>
            <label>Umiejscowienie na stronie głównej
              <select name="chat_position" class="vf-input">
                <option value="top"<?= $chatPosVal === 'top' ? ' selected' : '' ?>>Góra strony (nad działami)</option>
                <option value="bottom"<?= $chatPosVal === 'bottom' ? ' selected' : '' ?>>Dół strony (pod działami)</option>
                <option value="hidden"<?= $chatPosVal === 'hidden' ? ' selected' : '' ?>>Ukryty na stronie głównej</option>
              </select>
            </label>
            <label>Maks. długość wiadomości (znaki)
              <input type="number" name="chat_max_length" class="vf-input" min="80" max="1000" value="<?= $chatMaxLenVal ?>">
            </label>
            <label>Limit antyspamowy (sekundy)
              <input type="number" name="chat_flood_seconds" class="vf-input" min="0" max="60" value="<?= $chatFloodVal ?>">
            </label>
          </div>
          <label>Ogłoszenie przypięte nad czatem (opcjonalnie)
            <input type="text" name="chat_announcement" class="vf-input" maxlength="320" value="<?= View::e($chatAnnouncementVal) ?>" placeholder="np. Zachowaj kulturę na czacie! Użyj /me lub wklejaj GIF z Klipy.com.">
          </label>
          <div class="vf-subbox" style="display:flex;gap:18px;flex-wrap:wrap;align-items:center;">
            <label class="vf-muted"><input type="checkbox" name="chat_enabled" value="1"<?= $chatEnabledVal ? ' checked' : '' ?>> Moduł czatu aktywny</label>
            <label class="vf-muted"><input type="checkbox" name="chat_allow_guests" value="1"<?= $chatGuestsVal ? ' checked' : '' ?>> Widoczny dla gości (tylko podgląd)</label>
            <label class="vf-muted"><input type="checkbox" name="chat_allow_gif" value="1"<?= $chatGifVal ? ' checked' : '' ?>> Integracja GIF z Klipy.com</label>
            <label class="vf-muted"><input type="checkbox" name="chat_allow_reactions" value="1"<?= $chatReactsVal ? ' checked' : '' ?>> Reakcje emoji na wiadomości</label>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <button type="submit" class="vf-btn">Zapisz konfigurację czatu</button>
            <a href="/admin?tab=groups" class="vf-btn vf-btn-secondary">Uprawnienia grup na czacie →</a>
          </div>
        </form>
      </section>

      <section class="vf-card">
        <div class="vf-card-head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
          <span>Moderacja i konserwacja czatu</span>
          <form method="post" action="/admin/chat/clear" data-vf-confirm-delete style="margin:0;">
            <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
            <button type="submit" class="vf-btn vf-btn-danger vf-btn-sm" data-confirm-label="Potwierdź wyczyszczenie czatu">Wyczyść całą historię czatu</button>
          </form>
        </div>
        <?php if (empty($chatMutedMembers)): ?>
          <div class="vf-row"><span class="vf-muted">Brak aktualnie wyciszonych użytkowników na czacie.</span></div>
        <?php else: ?>
          <?php foreach ($chatMutedMembers as $mm): ?>
            <div class="vf-row" style="flex-wrap:wrap;gap:10px;">
              <div>
                <strong><?= View::e((string) $mm['username']) ?></strong>
                <span class="vf-muted"> · wyciszenie do <?= date('d.m.Y H:i', (int) $mm['chat_muted_until']) ?></span>
              </div>
              <form method="post" action="/api/chat/mute" style="margin:0;">
                <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
                <input type="hidden" name="member_id" value="<?= (int) $mm['id'] ?>">
                <input type="hidden" name="minutes" value="0">
                <button type="submit" class="vf-btn vf-btn-sm vf-btn-secondary">Zdejmij wyciszenie</button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>

    <?php elseif ($tab === 'settings'): ?>
      <section class="vf-card">
        <div class="vf-card-head">Globalne ustawienia forum oraz SEO</div>
        <form method="post" action="/admin/settings/save" style="padding:20px;display:grid;gap:14px;max-width:680px;">
          <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
          <label>Nazwa społeczności (title / og:site_name)
            <input type="text" name="site_name" data-vf-seo-name class="vf-input" value="<?= View::e($settings['site_name'] ?? 'VeloForum') ?>" required>
          </label>
          <label>Domyślny opis meta description SEO
            <textarea name="site_description" rows="2" data-vf-seo-desc class="vf-textarea"><?= View::e($settings['site_description'] ?? '') ?></textarea>
          </label>

          <div class="vf-serp-preview">
            <div class="vf-muted" style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">
              Podgląd wyniku w Google (SERP)
            </div>
            <div style="font-size:0.75rem;color:var(--success-ink);">https://twoja-domena.pl › forum</div>
            <div data-vf-seo-title-preview style="font-size:1.0625rem;font-weight:600;color:var(--accent);margin-top:2px;">
              <?= View::e(($settings['site_name'] ?? 'VeloForum') . ' — Nowoczesne forum dyskusyjne') ?>
            </div>
            <div data-vf-seo-desc-preview class="vf-muted" style="margin-top:4px;line-height:1.45;">
              <?= View::e($settings['site_description'] ?? 'Nowoczesna społeczność dyskusyjna.') ?>
            </div>
          </div>

          <label>Rejestracja nowych kont
            <select name="registration_enabled" class="vf-input">
              <option value="1" <?= ($settings['registration_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>Włączona</option>
              <option value="0" <?= ($settings['registration_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>Wyłączona</option>
            </select>
          </label>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
            <label>Tematów na stronę <input type="number" name="topics_per_page" class="vf-input" value="<?= (int) ($settings['topics_per_page'] ?? 25) ?>"></label>
            <label>Postów na stronę <input type="number" name="posts_per_page" class="vf-input" value="<?= (int) ($settings['posts_per_page'] ?? 20) ?>"></label>
          </div>
          <label>Klucz API Klipy.com (opcjonalnie — wyszukiwarka GIF / klipów w edytorze)
            <input type="text" name="klipy_api_key" class="vf-input" value="<?= View::e($settings['klipy_api_key'] ?? '') ?>" placeholder="Wklej App Key z klipy.com/developers (lub zostaw puste dla trybu linków)">
            <span class="vf-muted" style="display:block;font-size:0.75rem;margin-top:4px;">Nawet bez klucza API użytkownicy mogą wklejać bezpośrednie linki z klipy.com w edytorze postów.</span>
          </label>
          <div><button type="submit" class="vf-btn">Zapisz ustawienia</button></div>
        </form>
      </section>
    <?php endif; ?>
  </div>
</div>
