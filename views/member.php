<?php use VeloForum\Core\Csrf; use VeloForum\Core\View; ?>
<?php
  $isOwn = !empty($currentUser) && (int) $currentUser['id'] === (int) $member['id'];
  $baseUrl = '/member/' . (int) $member['id'] . '-' . View::e((string) $member['slug']);
  $initials = mb_strtoupper(mb_substr((string) $member['username'], 0, 2));
  $chevronRightSvg = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>';

  $mLast = isset($member['last_activity_at']) ? (int) $member['last_activity_at'] : null;
  $mJoined = (int) $member['joined_at'];
  $mOnline = View::isOnline($mLast);
  $mPosts = (int) ($member['posts_count'] ?? 0);
  $mRep = (int) ($member['reputation_points'] ?? 0);
  $mSolved = (int) ($solvedCount ?? 0);
  $mRank = View::reputationRank($mRep);
  $mStaff = ((int) ($member['is_admin'] ?? 0) === 1 || (int) ($member['is_moderator'] ?? 0) === 1 || !empty($supervisedForums));

  $achievements = $achievementShowcase ?? [];
  $unlockedAchCount = 0;
  foreach ($achievements as $achItem) {
    if (!empty($achItem['unlocked'])) { $unlockedAchCount++; }
  }
  $achPct = (int) round(($unlockedAchCount / max(1, count($achievements))) * 100);
  $safeCover = View::safeMediaUrl($member['cover_url'] ?? null);

  $emojiByCode = ['like' => '👍', 'thanks' => '❤️', 'helpful' => '🙌', 'insightful' => '💡'];
  $totalReactReceived = 0;
  $maxReactCount = 1;
  foreach ($reactionStats ?? [] as $rsRow) {
    $cnt = (int) $rsRow['total'];
    $totalReactReceived += $cnt;
    if ($cnt > $maxReactCount) {
      $maxReactCount = $cnt;
    }
  }
?>
<nav class="vf-breadcrumb" aria-label="Ścieżka nawigacji">
  <a href="/">Forum</a>
  <?= $chevronRightSvg ?>
  <a href="/members">Użytkownicy</a>
  <?= $chevronRightSvg ?>
  <span class="vf-breadcrumb-cur"><?= View::e($member['username']) ?></span>
</nav>

<div class="vf-profile-topbar">
  <a href="/" class="vf-btn vf-btn-sm vf-btn-secondary">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
    <span>Powrót do forum</span>
  </a>
  <span class="vf-profile-slug"><?= View::e($baseUrl) ?></span>
</div>

<?php if (!empty($saved)): ?>
  <div class="vf-alert-success">
    Zapisano zmiany w Twoim profilu użytkownika.
  </div>
<?php endif; ?>

<?php if (!empty($profileError)): ?>
  <div class="vf-alert-danger" style="padding:14px 20px;margin-bottom:20px;border-radius:12px;border:1px solid var(--danger-border);background:var(--danger-bg);color:var(--danger-ink);font-size:0.875rem;font-weight:500;">
    <?= View::e((string) $profileError) ?>
  </div>
<?php endif; ?>

<section class="vf-card">
  <div class="vf-profile-hero<?= $safeCover ? ' has-cover' : '' ?>"<?= $safeCover ? ' style="--vf-cover:url(\'' . View::e($safeCover) . '\');"' : '' ?>>
    <div style="display:flex;align-items:center;gap:18px;min-width:0;flex:1;">
      <span class="vf-avatar vf-avatar-lg">
        <?php if (!empty($member['avatar_url'])): ?>
          <img src="<?= View::e((string) $member['avatar_url']) ?>" alt="<?= View::e($member['username']) ?>" data-vf-initials="<?= View::e($initials) ?>" loading="lazy" decoding="async">
        <?php else: ?>
          <?= View::e($initials) ?>
        <?php endif; ?>
        <?php if ($mOnline): ?>
          <span class="vf-avatar-online" style="width:14px;height:14px;border-width:2.5px;" title="Aktywny teraz"></span>
        <?php endif; ?>
      </span>
      <div style="min-width:0;">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <h1 style="margin:0;font-size:1.55rem;color:#fff;"><?= View::formatUsername(
            (string) $member['username'],
            $member['group_color'] ?? null,
            $member['group_prefix'] ?? null,
            $member['group_suffix'] ?? null,
            $member['group_name'] ?? null
          ) ?></h1>
          <span style="font-size:0.78rem;font-weight:700;color:#93c5fd;"><?= View::e($mRank['label']) ?></span>
        </div>
        <div style="font-size:0.875rem;margin-top:4px;color:#bfdbfe;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
          <span class="vf-group-swatch" style="background:<?= View::e(View::safeGroupColor($member['group_color'] ?? null, '#60a5fa')) ?>;" aria-hidden="true"></span>
          <strong style="color:#fff;"><?= View::e($member['group_name'] ?? 'Użytkownik') ?></strong>
          <?php if (empty($isProfileGlobalStaff) && !empty($supervisedForums)): ?>
            <span>· Moderator kategorii</span>
          <?php endif; ?>
          <?= !empty($member['member_title']) ? '<span>· ' . View::e((string) $member['member_title']) . '</span>' : '' ?>
        </div>
        <div style="font-size:0.76rem;color:#94a3b8;margin-top:8px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <span class="vf-presence-badge<?= $mOnline ? ' is-online' : '' ?>" style="background:rgba(15,23,42,0.55);border-color:rgba(148,163,184,0.28);color:<?= $mOnline ? '#86efac' : '#cbd5e1' ?>;">
            <span class="vf-presence-dot<?= $mOnline ? ' is-online' : ' is-offline' ?>" aria-hidden="true"></span>
            <?= View::e(View::presenceText($mLast, $mJoined)) ?>
          </span>
          <span>Dołączył: <?= date('d.m.Y', $mJoined) ?></span>
          <?php if ((int) ($member['is_banned'] ?? 0) === 1): ?>
            · <span style="color:#fca5a5;font-weight:600;">Konto zablokowane</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php if ($isOwn): ?>
      <a href="<?= $baseUrl ?>?tab=about" class="vf-btn" data-vf-tab-jump="about" data-vf-tabs-scope="member-profile">Edytuj profil</a>
    <?php elseif (!empty($currentUser)): ?>
      <a href="/messages?to=<?= (int) $member['id'] ?>" class="vf-btn">Wyślij wiadomość</a>
    <?php endif; ?>
  </div>

  <div class="vf-profile-kpis">
    <div class="vf-profile-kpi">
      <span>Wszystkie posty</span>
      <strong><?= $mPosts ?></strong>
    </div>
    <div class="vf-profile-kpi">
      <span>Założone tematy</span>
      <strong><?= (int) ($topicsCount ?? 0) ?></strong>
    </div>
    <div class="vf-profile-kpi">
      <span>Reputacja</span>
      <strong style="color:var(--accent);"><?= $mRep ?> pkt</strong>
    </div>
    <div class="vf-profile-kpi">
      <span>Najlepsze odpowiedzi</span>
      <strong class="vf-solved-label"><?= $mSolved ?></strong>
    </div>
  </div>
</section>

<div class="vf-profile-layout">
  <section class="vf-card" data-vf-tabs="member-profile">
    <nav class="vf-tabs" role="tablist" aria-label="Zakładki profilu">
      <a class="vf-tab<?= $tab === 'posts' ? ' is-active' : '' ?>" href="<?= $baseUrl ?>?tab=posts" role="tab" aria-selected="<?= $tab === 'posts' ? 'true' : 'false' ?>" data-vf-tab-target="posts">
        Ostatnie posty (<?= count($recentPosts ?? []) ?>)
      </a>
      <a class="vf-tab<?= $tab === 'topics' ? ' is-active' : '' ?>" href="<?= $baseUrl ?>?tab=topics" role="tab" aria-selected="<?= $tab === 'topics' ? 'true' : 'false' ?>" data-vf-tab-target="topics">
        Założone tematy (<?= (int) ($topicsCount ?? 0) ?>)
      </a>
      <a class="vf-tab<?= $tab === 'reactions' ? ' is-active' : '' ?>" href="<?= $baseUrl ?>?tab=reactions" role="tab" aria-selected="<?= $tab === 'reactions' ? 'true' : 'false' ?>" data-vf-tab-target="reactions">
        Reakcje i reputacja (<?= $totalReactReceived ?>)
      </a>
      <a class="vf-tab<?= $tab === 'about' ? ' is-active' : '' ?>" href="<?= $baseUrl ?>?tab=about" role="tab" aria-selected="<?= $tab === 'about' ? 'true' : 'false' ?>" data-vf-tab-target="about">
        <?= $isOwn ? 'O mnie i Edycja' : 'O mnie i Osiągnięcia' ?>
      </a>
    </nav>

    <div class="vf-tab-panel" data-vf-tab-panel="posts" role="tabpanel"<?= $tab !== 'posts' ? ' hidden' : '' ?>>
      <?php if (empty($recentPosts)): ?>
        <div class="vf-row"><span class="vf-muted">Ten użytkownik nie opublikował jeszcze żadnych postów.</span></div>
      <?php endif; ?>
      <?php foreach ($recentPosts as $p): ?>
        <?php
          $pAt = (int) ($p['created_at'] ?? 0);
          $isStarter = (int) ($p['is_first_post'] ?? 0) === 1;
        ?>
        <div class="vf-row" style="display:block;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px;">
            <span class="vf-post-type-tag"><?= $isStarter ? 'Post otwierający' : 'Odpowiedź' ?></span>
            <span class="vf-muted" title="<?= date('d.m.Y H:i', $pAt) ?>"><?= View::e(View::formatRelativeTime($pAt)) ?></span>
          </div>
          <a href="/topic/<?= (int) $p['topic_id'] ?>-<?= View::e((string) $p['topic_slug']) ?>#post-<?= (int) $p['id'] ?>" style="font-weight:700;font-size:0.95rem;color:var(--ink);">
            <?= View::e((string) $p['topic_title']) ?>
          </a>
          <div class="vf-muted" style="margin-top:2px;">
            Dział: <a href="/forum/<?= (int) ($p['forum_id'] ?? 0) ?>-<?= View::e((string) ($p['forum_slug'] ?? 'dzial')) ?>"><?= View::e((string) $p['forum_name']) ?></a>
          </div>
          <p style="margin:8px 0 0;font-size:0.86rem;color:var(--muted);line-height:1.5;">
            <?= View::e(mb_substr(trim(strip_tags((string) $p['content_html'])), 0, 220)) ?>
          </p>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="vf-tab-panel" data-vf-tab-panel="topics" role="tabpanel"<?= $tab !== 'topics' ? ' hidden' : '' ?>>
      <?php if (empty($recentTopics)): ?>
        <div class="vf-row"><span class="vf-muted">Brak założonych tematów przez tego użytkownika.</span></div>
      <?php endif; ?>
      <?php foreach ($recentTopics as $t): ?>
        <?php $tAt = (int) ($t['created_at'] ?? 0); ?>
        <div class="vf-row">
          <div style="min-width:0;">
            <a href="/topic/<?= (int) $t['id'] ?>-<?= View::e((string) $t['slug']) ?>" style="font-weight:700;color:var(--ink);">
              <?= View::e((string) $t['title']) ?>
            </a>
            <?php if (!empty($t['best_answer_post_id'])): ?>
              <span class="vf-solved-label" style="font-size:0.75rem;margin-left:6px;">· Rozwiązany</span>
            <?php endif; ?>
            <div class="vf-muted" style="margin-top:2px;">
              <?= View::e((string) $t['forum_name']) ?> · <span title="<?= date('d.m.Y H:i', $tAt) ?>"><?= View::e(View::formatRelativeTime($tAt)) ?></span>
            </div>
          </div>
          <div class="vf-muted" style="white-space:nowrap;font-variant-numeric:tabular-nums;">
            <?= max(0, (int) $t['posts_count'] - 1) ?> odp. · <?= (int) $t['views_count'] ?> wyśw.
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="vf-tab-panel" data-vf-tab-panel="reactions" role="tabpanel"<?= $tab !== 'reactions' ? ' hidden' : '' ?>>
      <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;background:var(--surface-soft);">
        <span class="vf-muted">Łącznie otrzymanych reakcji: <strong style="color:var(--ink);"><?= $totalReactReceived ?></strong></span>
        <span class="vf-muted">Bilans reputacji: <strong style="color:var(--accent);"><?= $mRep ?> pkt</strong></span>
      </div>
      <?php foreach ($reactionStats ?? [] as $rs): ?>
        <?php
          $cnt = (int) $rs['total'];
          $pct = $totalReactReceived > 0 ? (int) round(($cnt / $maxReactCount) * 100) : 0;
          $emoji = $emojiByCode[(string) ($rs['code'] ?? 'like')] ?? '👍';
        ?>
        <div class="vf-row" style="gap:16px;">
          <div style="display:flex;align-items:center;gap:10px;min-width:170px;">
            <span style="font-size:1.125rem;"><?= $emoji ?></span>
            <div>
              <strong><?= View::e((string) $rs['label']) ?></strong>
              <div class="vf-muted" style="font-size:0.75rem;"><?= (int) $rs['points'] >= 0 ? '+' . (int) $rs['points'] : (int) $rs['points'] ?> pkt reputacji</div>
            </div>
          </div>
          <div class="vf-react-meter" aria-hidden="true">
            <span style="width:<?= $pct ?>%;"></span>
          </div>
          <strong style="min-width:44px;text-align:right;font-variant-numeric:tabular-nums;"><?= $cnt ?>×</strong>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="vf-tab-panel" data-vf-tab-panel="about" role="tabpanel"<?= $tab !== 'about' ? ' hidden' : '' ?>>
      <?php
        $memberCustom = json_decode((string) ($member['custom_fields'] ?? '{}'), true);
        if (!is_array($memberCustom)) { $memberCustom = []; }
      ?>
      <div style="padding:20px;display:grid;gap:18px;">
        <div>
          <div class="vf-muted" style="font-weight:700;margin-bottom:4px;">O użytkowniku</div>
          <p style="margin:0;"><?= !empty($member['bio']) ? nl2br(View::e((string) $member['bio'])) : '<span class="vf-muted">Użytkownik nie uzupełnił jeszcze opisu.</span>' ?></p>
        </div>
        <?php if (!empty($profileFields)): ?>
          <div class="vf-subbox" style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
            <?php foreach ($profileFields as $pf): ?>
              <?php $pfVal = trim((string) ($memberCustom[$pf['field_key']] ?? '')); ?>
              <div>
                <div class="vf-muted" style="font-size:0.75rem;"><?= View::e((string) $pf['label']) ?></div>
                <div style="font-weight:600;font-size:0.875rem;"><?= $pfVal !== '' ? View::e($pfVal) : '<span class="vf-muted">Nie podano</span>' ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($member['signature'])): ?>
          <div style="padding-top:12px;border-top:1px dashed var(--border);" class="vf-muted">
            Sygnatura: <?= View::e((string) $member['signature']) ?>
          </div>
        <?php endif; ?>

        <div style="padding-top:14px;border-top:1px solid var(--border);">
          <div class="vf-muted" style="font-weight:700;margin-bottom:10px;">Osiągnięcia i nagrody społeczności (<?= $unlockedAchCount ?>/<?= count($achievements) ?>)</div>
          <div class="vf-achievements-grid">
            <?php foreach ($achievements as $ach): ?>
              <?php
                $achUnlocked = !empty($ach['unlocked']);
                $achColor = View::safeGroupColor($ach['color_hex'] ?? null, '#2563eb');
                $achIcon = (string) ($ach['icon'] ?? 'trophy');
                $achPctRow = (int) ($ach['progress_pct'] ?? ($achUnlocked ? 100 : 0));
                $isManualRule = ($ach['rule_type'] ?? 'manual') === 'manual';
              ?>
              <div class="vf-achievement-card<?= $achUnlocked ? ' is-unlocked' : '' ?>" style="--ach-color:<?= View::e($achColor) ?>;">
                <span class="vf-achievement-icon" aria-hidden="true">
                  <?= View::achievementIconSvg($achIcon) ?>
                </span>
                <div style="min-width:0;flex:1;">
                  <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;">
                    <div style="font-weight:700;font-size:0.84rem;color:var(--ink);"><?= View::e((string) $ach['title']) ?></div>
                    <?php if ($achUnlocked): ?>
                      <span style="font-size:0.7rem;font-weight:700;color:var(--success-ink);">Zdobyte</span>
                    <?php endif; ?>
                  </div>
                  <div class="vf-muted"><?= View::e((string) ($ach['description'] ?? '')) ?></div>
                  <?php if (!$isManualRule): ?>
                    <div class="vf-achievement-progress" aria-hidden="true">
                      <span style="width:<?= $achPctRow ?>%;"></span>
                    </div>
                    <div class="vf-achievement-meta">
                      <?= (int) ($ach['current_value'] ?? 0) ?> / <?= (int) ($ach['rule_threshold'] ?? 1) ?> (<?= $achPctRow ?>%)
                    </div>
                  <?php elseif ($achUnlocked && !empty($ach['awarded_by_name'])): ?>
                    <div class="vf-achievement-meta">
                      Nadane przez <?= View::e((string) $ach['awarded_by_name']) ?>
                      <?php if (!empty($ach['awarded_at'])): ?> · <?= View::e(View::formatRelativeTime((int) $ach['awarded_at'])) ?><?php endif; ?>
                    </div>
                  <?php endif; ?>
                  <?php if ($achUnlocked && !empty($ach['note'])): ?>
                    <div class="vf-achievement-note">„<?= View::e((string) $ach['note']) ?>”</div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if ($isOwn): ?>
          <form method="post" action="/member/profile" enctype="multipart/form-data" id="vf-edit-profile-form" style="padding-top:16px;border-top:1px solid var(--border);display:grid;gap:12px;">
            <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
            <h3 style="margin:0;font-size:1rem;">Edytuj mój profil</h3>
            <div>
              <label class="vf-muted" style="display:block;margin-bottom:4px;">Tytuł użytkownika</label>
              <input type="text" name="member_title" maxlength="120" class="vf-input" value="<?= View::e((string) ($member['member_title'] ?? '')) ?>">
            </div>
            <div class="vf-subbox" style="display:grid;gap:10px;">
              <label class="vf-muted" style="display:block;font-weight:700;">Avatar profilu (upload pliku lub adres URL)</label>
              <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                <span class="vf-avatar vf-avatar-md" data-vf-avatar-preview style="width:54px;height:54px;border-radius:14px;flex-shrink:0;">
                  <?php if (!empty($member['avatar_url'])): ?>
                    <img src="<?= View::e((string) $member['avatar_url']) ?>" alt="<?= View::e($member['username']) ?>" data-vf-initials="<?= View::e($initials) ?>">
                  <?php else: ?>
                    <?= View::e($initials) ?>
                  <?php endif; ?>
                </span>
                <div style="display:grid;gap:8px;flex:1;min-width:210px;">
                  <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <label class="vf-btn vf-btn-sm vf-btn-secondary" style="cursor:pointer;">
                      <span>Wgraj plik z dysku</span>
                      <input type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp,image/gif" data-vf-avatar-input hidden>
                    </label>
                    <span class="vf-muted" data-vf-avatar-filename style="font-size:0.75rem;">JPG, PNG, WebP, GIF · maks. 2 MB</span>
                  </div>
                  <input type="text" name="avatar_url" maxlength="500" class="vf-input" value="<?= View::e((string) ($member['avatar_url'] ?? '')) ?>" placeholder="lub wklej URL: https://...">
                  <?php if (!empty($member['avatar_url'])): ?>
                    <label style="display:inline-flex;align-items:center;gap:6px;font-size:0.78rem;color:var(--muted);cursor:pointer;">
                      <input type="checkbox" name="remove_avatar" value="1">
                      <span>Usuń obecny avatar i wróć do inicjałów</span>
                    </label>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <?php if (!empty($canSetCover)): ?>
              <div class="vf-subbox" style="display:grid;gap:10px;">
                <label class="vf-muted" style="display:block;font-weight:700;">Tło profilowe / karty posta (Cover)</label>
                <div
                  class="vf-cover-preview"
                  data-vf-cover-preview
                  <?= $safeCover ? 'style="background-image:linear-gradient(135deg, rgba(15,23,42,0.55), rgba(15,23,42,0.85)), url(\'' . View::e($safeCover) . '\');"' : '' ?>
                >
                  <span>Podgląd banera i tła autora pod postami</span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                  <label class="vf-btn vf-btn-sm vf-btn-secondary" style="cursor:pointer;">
                    <span>Wgraj tło z dysku</span>
                    <input type="file" name="cover_file" accept="image/jpeg,image/png,image/webp,image/gif" data-vf-cover-input hidden>
                  </label>
                  <span class="vf-muted" data-vf-cover-filename style="font-size:0.75rem;">JPG, PNG, WebP, GIF · maks. 4 MB</span>
                </div>
                <input type="text" name="cover_url" maxlength="500" class="vf-input" value="<?= View::e((string) ($member['cover_url'] ?? '')) ?>" placeholder="lub wklej URL grafiki tła: https://...">
                <?php if ($safeCover !== ''): ?>
                  <label style="display:inline-flex;align-items:center;gap:6px;font-size:0.78rem;color:var(--muted);cursor:pointer;">
                    <input type="checkbox" name="remove_cover" value="1">
                    <span>Usuń obecne tło profilowe</span>
                  </label>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="vf-subbox vf-muted" style="font-size:0.8rem;">
                Własne tło profilowe (Cover) może być ustawiane przez grupy posiadające odpowiednie uprawnienie nadane w Panelu Administracyjnym (ACP).
              </div>
            <?php endif; ?>

            <?php if (!empty($canSetMusic)): ?>
              <div class="vf-subbox" style="display:grid;gap:8px;">
                <label class="vf-muted" style="display:block;font-weight:700;">Muzyka profilowa (YouTube)</label>
                <input
                  type="url"
                  name="music_url"
                  maxlength="500"
                  class="vf-input"
                  value="<?= View::e((string) ($member['music_url'] ?? '')) ?>"
                  placeholder="https://www.youtube.com/watch?v=... lub https://youtu.be/..."
                >
                <span class="vf-muted" style="font-size:0.75rem;">Wklej link do utworu z YouTube (pozostaw puste, aby usunąć muzykę z profilu).</span>
              </div>
            <?php else: ?>
              <div class="vf-subbox vf-muted" style="font-size:0.8rem;">
                Muzyka profilowa (YouTube) może być ustawiana wyłącznie przez grupy z nadanym uprawnieniem w Panelu Administracyjnym (ACP).
              </div>
            <?php endif; ?>
            <div>
              <label class="vf-muted" style="display:block;margin-bottom:4px;">O mnie (Bio)</label>
              <textarea name="bio" rows="3" maxlength="1200" class="vf-textarea"><?= View::e((string) ($member['bio'] ?? '')) ?></textarea>
            </div>
            <div>
              <label class="vf-muted" style="display:block;margin-bottom:4px;">Sygnatura pod postami</label>
              <input type="text" name="signature" maxlength="400" class="vf-input" value="<?= View::e((string) ($member['signature'] ?? '')) ?>">
            </div>
            <?php if (!empty($profileFields)): ?>
              <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));">
                <?php foreach ($profileFields as $pf): ?>
                  <?php $fKey = (string) $pf['field_key']; ?>
                  <div>
                    <label class="vf-muted" style="display:block;margin-bottom:4px;"><?= View::e((string) $pf['label']) ?></label>
                    <input type="text" name="custom_fields[<?= View::e($fKey) ?>]" maxlength="200" class="vf-input" value="<?= View::e((string) ($memberCustom[$fKey] ?? '')) ?>" placeholder="<?= View::e((string) ($pf['placeholder'] ?? '')) ?>">
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <div><button type="submit" class="vf-btn">Zapisz mój profil</button></div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <aside>
    <section class="vf-card">
      <div class="vf-card-head vf-head-icon-title" style="width:100%;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span>Ranga i uprawnienia</span>
      </div>
      <div style="padding:16px 18px;display:grid;gap:14px;">
        <div>
          <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
            <strong style="color:<?= View::e($mRank['color']) ?>;font-size:0.9rem;"><?= View::e($mRank['label']) ?></strong>
            <span class="vf-muted" style="font-variant-numeric:tabular-nums;"><?= $mRep ?> pkt</span>
          </div>
          <div class="vf-rep-bar" aria-hidden="true">
            <span style="width:<?= (int) $mRank['pct'] ?>%;"></span>
          </div>
          <div class="vf-muted" style="margin-top:4px;font-size:0.74rem;">
            <?= $mRank['next_at'] !== null ? 'Do kolejnego poziomu: ' . max(0, $mRank['next_at'] - $mRep) . ' pkt' : 'Osiągnięto maksymalny poziom reputacji' ?>
          </div>
        </div>

        <div style="padding-top:12px;border-top:1px solid var(--border);display:grid;gap:8px;font-size:0.82rem;">
          <?php
            $daysSinceJoin = max(1, (int) floor((time() - $mJoined) / 86400));
            $postsPerDay = $mPosts / $daysSinceJoin;
            $rateLabel = $postsPerDay >= 0.5
              ? number_format($postsPerDay, 1, ',', ' ') . ' postów / dzień'
              : number_format($postsPerDay * 30, 1, ',', ' ') . ' postów / mies.';
          ?>
          <div style="display:flex;justify-content:space-between;gap:8px;">
            <span class="vf-muted">Grupa główna</span>
            <strong style="color:<?= View::e(View::safeGroupColor($member['group_color'] ?? null, '#2563eb')) ?>;"><?= View::e($member['group_name'] ?? 'Użytkownik') ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;gap:8px;">
            <span class="vf-muted">Średnia aktywność</span>
            <strong><?= View::e($rateLabel) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;gap:8px;">
            <span class="vf-muted">Osiągnięcia</span>
            <strong><?= $unlockedAchCount ?> / <?= count($achievements) ?> (<?= $achPct ?>%)</strong>
          </div>
          <div style="display:flex;justify-content:space-between;gap:8px;">
            <span class="vf-muted">Ostrzeżenia</span>
            <strong><?= (int) ($member['warning_points'] ?? 0) ?> / 10</strong>
          </div>
          <div style="display:flex;justify-content:space-between;gap:8px;">
            <span class="vf-muted">Otrzymane reakcje</span>
            <strong><?= $totalReactReceived ?></strong>
          </div>
        </div>
      </div>
    </section>

    <?php if (!empty($youtubeVideoId)): ?>
      <section class="vf-card">
        <div class="vf-card-head vf-head-icon-title" style="width:100%;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
          <span>Muzyka profilowa</span>
        </div>
        <div style="padding:14px 16px;">
          <div class="vf-profile-music-frame">
            <iframe
              src="https://www.youtube-nocookie.com/embed/<?= View::e($youtubeVideoId) ?>?rel=0"
              title="Muzyka profilowa użytkownika <?= View::e($member['username']) ?>"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              loading="lazy"
              allowfullscreen
            ></iframe>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <?php if (empty($isProfileGlobalStaff) && !empty($supervisedForums)): ?>
      <section class="vf-card">
        <div class="vf-card-head vf-head-icon-title" style="width:100%;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
          <span>Nadzorowane działy</span>
        </div>
        <?php foreach ($supervisedForums as $sf): ?>
          <a href="/forum/<?= (int) $sf['id'] ?>-<?= View::e($sf['slug']) ?>" class="vf-row" style="padding:11px 18px;color:var(--ink);font-weight:600;font-size:0.85rem;">
            <span><?= View::e($sf['name']) ?></span>
            <?= $chevronRightSvg ?>
          </a>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <section class="vf-card">
      <div class="vf-card-head vf-head-icon-title" style="width:100%;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
        <span>Rozkład reakcji</span>
      </div>
      <?php foreach (array_slice($reactionStats ?? [], 0, 4) as $rs): ?>
        <?php $emoji = $emojiByCode[(string) ($rs['code'] ?? 'like')] ?? '👍'; ?>
        <div class="vf-row" style="padding:10px 18px;">
          <span style="font-size:0.84rem;"><?= $emoji ?> <?= View::e((string) $rs['label']) ?></span>
          <strong style="font-variant-numeric:tabular-nums;"><?= (int) $rs['total'] ?>×</strong>
        </div>
      <?php endforeach; ?>
    </section>
  </aside>
</div>
