<?php use VeloForum\Core\Csrf; use VeloForum\Core\View; ?>
<?php
  $s = $siteSettings ?? [];
  $heroEnabled = ($s['hero_enabled'] ?? '1') === '1';
  $heroTitle = trim((string) ($s['hero_title'] ?? '')) !== '' ? (string) $s['hero_title'] : ($seo['title'] ?? 'VeloForum');
  $heroSubtitle = trim((string) ($s['hero_subtitle'] ?? '')) !== '' ? (string) $s['hero_subtitle'] : ($seo['description'] ?? 'Nowoczesna społeczność dyskusyjna.');
  $heroStyleClass = match ((string) ($s['hero_style'] ?? 'dark-gradient')) {
    'ocean' => ' vf-hero-ocean',
    'clean' => ' vf-hero-clean',
    default => '',
  };
  $sidebarPos = (string) ($s['sidebar_position'] ?? 'right');
  $gridClass = 'vf-home-grid' . ($sidebarPos === 'left' ? ' is-sidebar-left' : ($sidebarPos === 'none' ? ' is-no-sidebar' : ''));
  $curLayout = $layout ?? ($s['forum_layout'] ?? 'list');

  $defaultWidgets = [
    ['id' => 'custom', 'enabled' => true],
    ['id' => 'activity', 'enabled' => true],
    ['id' => 'reputation', 'enabled' => true],
    ['id' => 'tags', 'enabled' => true],
    ['id' => 'reactions', 'enabled' => true],
    ['id' => 'stats', 'enabled' => true],
  ];
  $widgets = json_decode((string) ($s['sidebar_widgets_json'] ?? ''), true);
  if (!is_array($widgets) || $widgets === []) {
    $widgets = $defaultWidgets;
  }

  $chevronSvg = '<svg class="vf-cat-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>';
  $msgIconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
  $lockIconSvg = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
  $extIconSvg = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
  $pinSvg = '<svg class="vf-status-icon is-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"/></svg>';
  $starSvg = '<svg class="vf-status-icon is-featured" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
  $solvedSvg = '<svg class="vf-status-icon is-solved" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>';

  $chatEnabled = ($s['chat_enabled'] ?? '1') === '1';
  $chatPos = (string) ($s['chat_position'] ?? 'top');
  $chatTitle = trim((string) ($s['chat_title'] ?? '')) !== '' ? (string) $s['chat_title'] : 'Czat społeczności';
  $chatAnnouncement = trim((string) ($s['chat_announcement'] ?? ''));
  $chatMaxLen = max(80, min(1000, (int) ($s['chat_max_length'] ?? 400)));
  $chatAllowGuests = ($s['chat_allow_guests'] ?? '1') === '1';
  $chatAllowGif = ($s['chat_allow_gif'] ?? '1') === '1';
  $chatAllowReactions = ($s['chat_allow_reactions'] ?? '1') === '1';
  $chatEmojis = ['👍', '❤️', '😂', '🔥', '🙌'];

  $chatBoxHtml = '';
  if ($chatEnabled && $chatPos !== 'hidden') {
    ob_start();
    $viewerId = !empty($currentUser) ? (int) $currentUser['id'] : 0;
    $lastChatId = !empty($chatMessages) ? (int) ($chatMessages[count($chatMessages) - 1]['id'] ?? 0) : 0;
?>
<section
  class="vf-card vf-chatbox"
  data-vf-chatbox
  data-csrf="<?= View::e(Csrf::token()) ?>"
  data-last-id="<?= $lastChatId ?>"
  data-max-len="<?= $chatMaxLen ?>"
  data-allow-gif="<?= $chatAllowGif ? '1' : '0' ?>"
  data-allow-reactions="<?= $chatAllowReactions ? '1' : '0' ?>"
  data-can-use="<?= !empty($canUseChat) ? '1' : '0' ?>"
  data-can-moderate="<?= !empty($canModerateChat) ? '1' : '0' ?>"
  data-viewer-id="<?= $viewerId ?>"
>
  <div class="vf-card-head vf-card-head-flex vf-chat-head">
    <span class="vf-head-icon-title">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
      <span><?= View::e($chatTitle) ?></span>
      <span class="vf-live-pill"><span class="vf-presence-dot is-online is-pulse" aria-hidden="true"></span><span>Na żywo</span></span>
    </span>
    <div style="display:flex;align-items:center;gap:6px;">
      <button type="button" class="vf-post-ico-btn" data-vf-chat-sound-btn data-tooltip="Dźwięk nowych wiadomości" aria-label="Przełącz dźwięk czatu" aria-pressed="false">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
      </button>
      <?php if (!empty($currentUser) && (int) ($currentUser['is_admin'] ?? 0) === 1): ?>
        <a href="/admin?tab=chat" class="vf-post-ico-btn" data-tooltip="Ustawienia czatu (ACP)" aria-label="Ustawienia czatu w ACP">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($chatAnnouncement !== ''): ?>
    <div class="vf-chat-announcement">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
      <span><?= View::e($chatAnnouncement) ?></span>
    </div>
  <?php endif; ?>

  <div class="vf-chat-pinned" data-vf-chat-pinned<?= empty($chatPinned) ? ' hidden' : '' ?>>
    <?php if (!empty($chatPinned)): ?>
      <span class="vf-chat-pinned-tag">Przypięte</span>
      <strong style="white-space:nowrap;"><?= View::e((string) $chatPinned['username']) ?>:</strong>
      <span class="vf-chat-pinned-text"><?= strip_tags((string) $chatPinned['body_html']) ?></span>
      <?php if (!empty($canModerateChat)): ?>
        <button type="button" class="vf-chat-unpin-btn" data-vf-chat-pin="<?= (int) $chatPinned['id'] ?>" title="Odepnij wiadomość">✕</button>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if (empty($currentUser) && !$chatAllowGuests): ?>
    <div style="padding:20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
      <span class="vf-muted">Czat społeczności jest widoczny wyłącznie dla zalogowanych użytkowników.</span>
      <a href="/login" class="vf-btn vf-btn-sm">Zaloguj się</a>
    </div>
  <?php else: ?>
    <div class="vf-chat-stream" data-vf-chat-list>
      <?php if (empty($chatMessages)): ?>
        <div class="vf-chat-empty" data-vf-chat-empty>Brak wiadomości na czacie. Przywitaj się jako pierwszy!</div>
      <?php else: ?>
        <?php foreach ($chatMessages as $cm): ?>
          <?php
            $cmId = (int) $cm['id'];
            $cmUid = (int) $cm['member_id'];
            $cmName = (string) ($cm['username'] ?? 'Użytkownik');
            $cmSlug = (string) ($cm['slug'] ?? strtolower($cmName));
            $cmInit = mb_strtoupper(mb_substr($cmName, 0, 2));
            $cmAtt = View::safeMediaUrl($cm['attachment_url'] ?? null);
            $cmIsOwn = $viewerId > 0 && $viewerId === $cmUid;
            $cmCanDel = $cmIsOwn || !empty($canModerateChat);
            $cmAt = (int) ($cm['created_at'] ?? time());
            $cmReacts = $cm['reactions'] ?? [];
          ?>
          <div class="vf-chat-msg<?= (int) ($cm['is_action'] ?? 0) === 1 ? ' is-action' : '' ?>" data-chat-msg-id="<?= $cmId ?>">
            <a href="/member/<?= $cmUid ?>-<?= View::e($cmSlug) ?>" class="vf-avatar vf-chat-avatar">
              <?php if (!empty($cm['avatar_url'])): ?>
                <img src="<?= View::e((string) $cm['avatar_url']) ?>" alt="<?= View::e($cmName) ?>" data-vf-initials="<?= View::e($cmInit) ?>" loading="lazy" decoding="async">
              <?php else: ?>
                <?= View::e($cmInit) ?>
              <?php endif; ?>
            </a>
            <div class="vf-chat-msg-body">
              <div class="vf-chat-msg-meta">
                <a href="/member/<?= $cmUid ?>-<?= View::e($cmSlug) ?>" class="vf-chat-author">
                  <?= View::formatUsername($cmName, $cm['group_color'] ?? null, $cm['group_prefix'] ?? null, $cm['group_suffix'] ?? null, $cm['group_name'] ?? null) ?>
                </a>
                <?php if (!empty($cm['reply_to_username'])): ?>
                  <span class="vf-chat-reply-tag">↪ @<?= View::e((string) $cm['reply_to_username']) ?></span>
                <?php endif; ?>
                <span class="vf-chat-time" title="<?= date('d.m.Y H:i', $cmAt) ?>"><?= View::e(View::formatRelativeTime($cmAt)) ?></span>
                <div class="vf-chat-msg-tools">
                  <?php if (!empty($canUseChat)): ?>
                    <button type="button" class="vf-chat-tool-btn" data-vf-chat-reply="<?= View::e($cmName) ?>" title="Odpowiedz @<?= View::e($cmName) ?>">@</button>
                  <?php endif; ?>
                  <?php if (!empty($canModerateChat)): ?>
                    <button type="button" class="vf-chat-tool-btn" data-vf-chat-pin="<?= $cmId ?>" title="Przypnij / Odepnij wiadomość">📌</button>
                    <?php if (!$cmIsOwn): ?>
                      <button type="button" class="vf-chat-tool-btn" data-vf-chat-mute="<?= $cmUid ?>" data-mute-name="<?= View::e($cmName) ?>" title="Wycisz na 30 min">🔇</button>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php if ($cmCanDel): ?>
                    <button type="button" class="vf-chat-tool-btn is-danger" data-vf-chat-delete="<?= $cmId ?>" title="Usuń wiadomość">✕</button>
                  <?php endif; ?>
                </div>
              </div>
              <?php if (trim((string) ($cm['body_html'] ?? '')) !== ''): ?>
                <div class="vf-chat-msg-text"><?= (string) $cm['body_html'] ?></div>
              <?php endif; ?>
              <?php if ($cmAtt !== ''): ?>
                <div class="vf-chat-media">
                  <?php if (preg_match('/\.(mp4|webm)(\?|$)/i', $cmAtt)): ?>
                    <video src="<?= View::e($cmAtt) ?>" autoplay loop muted playsinline controls></video>
                  <?php else: ?>
                    <img src="<?= View::e($cmAtt) ?>" alt="Klip z Klipy.com" loading="lazy">
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if ($chatAllowReactions): ?>
                <div class="vf-chat-reactions" data-vf-chat-reactions="<?= $cmId ?>">
                  <?php foreach ($cmReacts as $rItem): ?>
                    <button
                      type="button"
                      class="vf-chat-react-chip<?= !empty($rItem['reacted']) ? ' is-mine' : '' ?>"
                      data-vf-chat-react="<?= $cmId ?>"
                      data-emoji="<?= View::e((string) $rItem['emoji']) ?>"
                      <?= empty($canUseChat) ? 'disabled' : '' ?>
                    >
                      <span><?= View::e((string) $rItem['emoji']) ?></span>
                      <span><?= (int) $rItem['count'] ?></span>
                    </button>
                  <?php endforeach; ?>
                  <?php if (!empty($canUseChat)): ?>
                    <div class="vf-chat-react-adder">
                      <button type="button" class="vf-chat-react-plus" title="Dodaj reakcję">+</button>
                      <div class="vf-chat-react-tray">
                        <?php foreach ($chatEmojis as $ce): ?>
                          <button type="button" class="vf-chat-react-opt" data-vf-chat-react="<?= $cmId ?>" data-emoji="<?= View::e($ce) ?>"><?= $ce ?></button>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if (!empty($isChatMuted)): ?>
      <div class="vf-chat-notice is-warn">Twoje konto zostało tymczasowo wyciszone na czacie przez moderatora.</div>
    <?php elseif (!empty($canUseChat)): ?>
      <div class="vf-chat-composer-wrap">
        <div class="vf-chat-reply-banner" data-vf-chat-reply-banner hidden>
          <span>Odpowiadasz do <strong data-vf-chat-reply-name></strong></span>
          <button type="button" class="vf-chat-tool-btn" data-vf-chat-reply-clear title="Anuluj odpowiedź">✕</button>
        </div>
        <div class="vf-chat-popover" data-vf-chat-emoji-pop hidden></div>
        <div class="vf-chat-popover" data-vf-chat-gif-pop hidden></div>
        <form class="vf-chat-composer" data-vf-chat-form>
          <button type="button" class="vf-post-ico-btn" data-vf-chat-emoji-btn data-tooltip="Wstaw emoji" aria-label="Wstaw emoji">😊</button>
          <?php if ($chatAllowGif): ?>
            <button type="button" class="vf-post-ico-btn vf-chat-gif-btn" data-vf-chat-gif-btn data-tooltip="GIF z Klipy.com" aria-label="Wstaw GIF z Klipy.com">GIF</button>
          <?php endif; ?>
          <input
            type="text"
            class="vf-input vf-chat-input"
            data-vf-chat-input
            maxlength="<?= $chatMaxLen ?>"
            placeholder="Napisz na czacie... (użyj /me lub @nick)"
            autocomplete="off"
          >
          <button type="submit" class="vf-btn vf-btn-sm">Wyślij</button>
        </form>
      </div>
    <?php elseif (empty($currentUser)): ?>
      <div class="vf-chat-notice">
        <span><a href="/login" style="font-weight:700;">Zaloguj się</a>, aby pisać na czacie i dodawać reakcje.</span>
      </div>
    <?php else: ?>
      <div class="vf-chat-notice">Twoja grupa nie ma obecnie uprawnień do pisania na czacie.</div>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php
    $chatBoxHtml = (string) ob_get_clean();
  }
?>

<?php if (($_GET['marked'] ?? '') === '1'): ?>
  <div class="vf-alert-success">
    Wszystkie działy i wątki zostały oznaczone jako przeczytane.
  </div>
<?php endif; ?>

<?php foreach (($globalAnnouncements ?? []) as $ann): ?>
  <?php if (($ann['placement'] ?? 'home') === 'home'): ?>
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

<?php if ($heroEnabled): ?>
<?php
  $heroCtaLabel = trim((string) ($s['hero_cta_label'] ?? '')) !== '' ? (string) $s['hero_cta_label'] : 'Co nowego';
  $heroCtaUrl = trim((string) ($s['hero_cta_url'] ?? '')) !== '' ? (string) $s['hero_cta_url'] : '/activity';
  $heroTagline = trim((string) ($s['site_tagline'] ?? ''));
?>
<section class="vf-hero<?= $heroStyleClass ?>">
  <div style="max-width:580px;">
    <?php if ($heroTagline !== ''): ?>
      <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--accent-border);margin-bottom:6px;"><?= View::e($heroTagline) ?></div>
    <?php endif; ?>
    <h1 style="margin:0 0 6px;font-size:1.625rem;color:#fff;"><?= View::e($heroTitle) ?></h1>
    <p style="margin:0 0 14px;color:#cbd5e1;font-size:0.9rem;line-height:1.5;">
      <?= View::e($heroSubtitle) ?>
    </p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <a href="<?= View::e($heroCtaUrl) ?>" class="vf-btn"><?= View::e($heroCtaLabel) ?></a>
      <button type="button" class="vf-btn vf-btn-muted" data-vf-quick-jump>Szybki skok (Ctrl+K)</button>
    </div>
  </div>
  <div class="vf-hero-kpis">
    <div><span>Tematy</span><strong><?= (int) $stats['topics'] ?></strong></div>
    <div><span>Posty</span><strong><?= (int) $stats['posts'] ?></strong></div>
    <div><span>Użytkownicy</span><strong><?= (int) $stats['members'] ?></strong></div>
    <div><span>Rozwiązane</span><strong><?= (int) ($solvedTopicsCount ?? 0) ?></strong></div>
  </div>
</section>
<?php endif; ?>

<div class="<?= $gridClass ?>">
  <div style="min-width:0;">
    <div class="vf-feed-pills" role="navigation" aria-label="Szybkie widoki społeczności">
      <a href="/" class="vf-feed-pill is-active">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        <span>Struktura działów</span>
      </a>
      <a href="/activity?tab=activity" class="vf-feed-pill">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        <span>Co nowego?</span>
      </a>
      <a href="/activity?tab=latest" class="vf-feed-pill">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span>Najnowsze tematy</span>
      </a>
      <a href="/activity?tab=solved" class="vf-feed-pill">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
        <span>Rozwiązane</span>
      </a>
      <a href="/activity?tab=polls" class="vf-feed-pill">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        <span>Ankiety</span>
      </a>
      <?php if (!empty($currentUser)): ?>
        <a href="/bookmarks" class="vf-feed-pill">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg>
          <span>Zapisane</span>
        </a>
      <?php endif; ?>
      <a href="/members" class="vf-feed-pill">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>Użytkownicy</span>
      </a>
    </div>

    <div class="vf-layout-toolbar">
      <div class="vf-muted" style="font-weight:600;">Kategorie i działy forum</div>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <form method="post" action="/mark-all-read" style="margin:0;">
          <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
          <button type="submit" class="vf-react-trigger" title="Oznacz wszystkie działy jako przeczytane">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/></svg>
            <span>Oznacz jako przeczytane</span>
          </button>
        </form>
        <div class="vf-layout-switcher" aria-label="Widok działów">
          <a href="/?layout=list" class="<?= $curLayout === 'list' ? 'is-active' : '' ?>" title="Widok listy">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            <span>Lista</span>
          </a>
          <a href="/?layout=grid" class="<?= $curLayout === 'grid' ? 'is-active' : '' ?>" title="Widok siatki">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
            <span>Siatka</span>
          </a>
          <a href="/?layout=compact" class="<?= $curLayout === 'compact' ? 'is-active' : '' ?>" title="Widok kompaktowy">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            <span>Kompakt</span>
          </a>
        </div>
      </div>
    </div>

    <?php if ($chatPos === 'top' && $chatBoxHtml !== ''): ?>
      <?= $chatBoxHtml ?>
    <?php endif; ?>

    <?php if (!empty($featuredTopics)): ?>
      <section class="vf-card">
        <div class="vf-card-head vf-card-head-flex">
          <span class="vf-head-icon-title">
            <?= $starSvg ?>
            <span>Wyróżnione tematy</span>
          </span>
          <span class="vf-cat-count"><?= count($featuredTopics) ?> wątki</span>
        </div>
        <?php foreach ($featuredTopics as $pt): ?>
          <?php
            $ptId = (int) $pt['id'];
            $ptLast = (int) ($pt['last_post_at'] ?? $pt['created_at']);
          ?>
          <div class="vf-forum-row">
            <div style="min-width:0;">
              <div class="vf-topic-title-line">
                <?= $pinSvg ?>
                <?php if (!empty($pt['best_answer_post_id'])): ?><?= $solvedSvg ?><?php endif; ?>
                <?php if (!empty($pt['prefix_label'])): ?>
                  <span class="vf-prefix" style="color:<?= View::e((string) ($pt['prefix_color'] ?? '#2563eb')) ?>;"><?= View::e((string) $pt['prefix_label']) ?></span>
                <?php endif; ?>
                <a href="/topic/<?= $ptId ?>-<?= View::e((string) $pt['slug']) ?>" class="vf-topic-link">
                  <?= View::e((string) $pt['title']) ?>
                </a>
                <?php if ((int) ($pt['posts_count'] ?? 0) >= 10 || (int) ($pt['views_count'] ?? 0) >= 250): ?>
                  <span class="vf-hot-flag">· Gorący</span>
                <?php endif; ?>
              </div>
              <div class="vf-muted" style="margin-top:3px;">
                <a href="/forum/<?= (int) $pt['forum_id'] ?>-<?= View::e((string) $pt['forum_slug']) ?>"><?= View::e((string) $pt['forum_name']) ?></a>
                · Autor:
                <?php if (!empty($pt['author_id'])): ?>
                  <a href="/member/<?= (int) $pt['author_id'] ?>-<?= View::e((string) ($pt['author_slug'] ?? 'profil')) ?>">
                    <?= View::formatUsername((string) $pt['author_name'], $pt['author_group_color'] ?? null, $pt['author_group_prefix'] ?? null, $pt['author_group_suffix'] ?? null, $pt['author_group_name'] ?? null) ?>
                  </a>
                <?php else: ?>
                  <?= View::formatUsername((string) $pt['author_name'], $pt['author_group_color'] ?? null, $pt['author_group_prefix'] ?? null, $pt['author_group_suffix'] ?? null, $pt['author_group_name'] ?? null) ?>
                <?php endif; ?>
                · <span title="<?= date('d.m.Y H:i', $ptLast) ?>"><?= View::e(View::formatRelativeTime($ptLast)) ?></span>
              </div>
            </div>
            <div class="vf-forum-stats">
              <div><strong><?= max(0, (int) $pt['posts_count'] - 1) ?></strong><span>Odp.</span></div>
              <div><strong><?= (int) $pt['views_count'] ?></strong><span>Wyśw.</span></div>
            </div>
            <div class="vf-last-post">
              <?php $ptLp = (string) ($pt['last_poster_name'] ?? 'Użytkownik'); ?>
              <span class="vf-avatar">
                <?php if (!empty($pt['last_poster_avatar'])): ?>
                  <img src="<?= View::e((string) $pt['last_poster_avatar']) ?>" alt="<?= View::e($ptLp) ?>" data-vf-initials="<?= View::e(mb_strtoupper(mb_substr($ptLp, 0, 2))) ?>" loading="lazy" decoding="async">
                <?php else: ?>
                  <?= View::e(mb_strtoupper(mb_substr($ptLp, 0, 2))) ?>
                <?php endif; ?>
              </span>
              <div class="vf-last-post-meta">
                <div class="vf-last-post-title" style="font-weight:500;">
                  <?php if (!empty($pt['last_poster_id'])): ?>
                    <a href="/member/<?= (int) $pt['last_poster_id'] ?>-<?= View::e((string) ($pt['last_poster_slug'] ?? 'profil')) ?>">
                      <?= View::formatUsername($ptLp, $pt['last_poster_color'] ?? null, $pt['last_poster_prefix'] ?? null, $pt['last_poster_suffix'] ?? null, $pt['last_poster_group'] ?? null) ?>
                    </a>
                  <?php else: ?>
                    <?= View::formatUsername($ptLp, $pt['last_poster_color'] ?? null, $pt['last_poster_prefix'] ?? null, $pt['last_poster_suffix'] ?? null, $pt['last_poster_group'] ?? null) ?>
                  <?php endif; ?>
                </div>
                <div class="vf-last-post-sub" title="<?= date('d.m.Y H:i', $ptLast) ?>"><?= View::e(View::formatRelativeTime($ptLast)) ?></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if (empty($categories)): ?>
      <div class="vf-card">
        <div class="vf-card-head">Brak działów</div>
        <div class="vf-row">
          <span class="vf-muted">Baza jest pusta. Przejdź do <a href="/migrate">Migratora IC5</a>, aby zaimportować działy, tematy, posty i użytkowników.</span>
        </div>
      </div>
    <?php endif; ?>

    <?php foreach ($categories as $category): ?>
      <?php
        $catId = (int) $category['id'];
        $rowsToRender = !empty($category['children'])
          ? $category['children']
          : ((int) ($category['is_category'] ?? 1) === 0 ? [$category] : []);
        $catMods = $forumModGroups[$catId] ?? [];
      ?>
      <section class="vf-card vf-cat-card<?= $curLayout === 'compact' ? ' vf-forum-compact' : '' ?>">
        <div class="vf-cat-header">
          <div class="vf-cat-head-main">
            <button type="button" class="vf-cat-title-btn" data-vf-cat-toggle="<?= $catId ?>" aria-expanded="true">
              <?= $chevronSvg ?>
              <span><?= View::e($category['name']) ?></span>
            </button>
            <?php if (!empty($category['description']) || !empty($catMods)): ?>
              <div class="vf-cat-sub">
                <?php if (!empty($category['description'])): ?>
                  <span><?= View::e((string) $category['description']) ?></span>
                <?php endif; ?>
                <?php if (!empty($catMods)): ?>
                  <span><?= !empty($category['description']) ? ' · ' : '' ?>Mod.: <?php foreach ($catMods as $cmIdx => $cm): ?><?= $cmIdx > 0 ? ', ' : '' ?><strong style="color:<?= View::e((string) ($cm['color_hex'] ?? '#7c3aed')) ?>;"><?= View::e((string) $cm['name']) ?></strong><?php endforeach; ?></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="vf-cat-body">
          <?php if ($curLayout === 'grid'): ?>
            <div class="vf-forum-grid">
              <?php foreach ($rowsToRender as $forum): ?>
                <?php
                  $fid = (int) $forum['id'];
                  $isLocked = (int) ($forum['is_locked'] ?? 0) === 1;
                  $isRedirect = !empty($forum['redirect_url']);
                  $isUnread = !empty($unreadForumsMap[$fid]);
                  $iconClass = $isRedirect ? 'is-redirect' : ($isLocked ? 'is-locked' : ($isUnread ? 'is-unread' : ''));
                  $iconSvg = $isRedirect ? $extIconSvg : ($isLocked ? $lockIconSvg : $msgIconSvg);
                ?>
                <div class="vf-forum-grid-card">
                  <div class="vf-forum-main">
                    <span class="vf-forum-icon <?= $iconClass ?>" aria-hidden="true"><?= $iconSvg ?></span>
                    <div style="min-width:0;flex:1;">
                      <div class="vf-forum-title-row">
                        <?php if ($isRedirect): ?>
                          <a href="<?= View::e((string) $forum['redirect_url']) ?>" target="_blank" rel="noopener noreferrer" class="vf-forum-link"><?= View::e($forum['name']) ?> ↗</a>
                        <?php else: ?>
                          <a href="/forum/<?= $fid ?>-<?= View::e($forum['slug']) ?>" class="vf-forum-link"><?= View::e($forum['name']) ?></a>
                        <?php endif; ?>
                        <?php if ($isUnread && !$isRedirect): ?>
                          <span class="vf-unread-dot" title="Nowe posty"></span>
                        <?php endif; ?>
                        <?php if ($isLocked): ?>
                          <span class="vf-muted">(Zamknięty)</span>
                        <?php endif; ?>
                      </div>
                      <?php if (!empty($forum['description'])): ?>
                        <div class="vf-desc" style="margin-top:4px;"><?= View::e($forum['description']) ?></div>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;border-top:1px solid var(--border);padding-top:10px;font-size:0.78rem;font-variant-numeric:tabular-nums;">
                    <span class="vf-muted"><strong><?= (int) $forum['topics_count'] ?></strong> tematów · <strong><?= (int) $forum['posts_count'] ?></strong> postów</span>
                    <?php if (!empty($forum['last_topic_id']) && !empty($forum['last_topic_title'])): ?>
                      <a href="/topic/<?= (int) $forum['last_topic_id'] ?>-<?= View::e((string) ($forum['last_topic_slug'] ?? 'temat')) ?>" style="font-weight:600;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= View::e((string) $forum['last_topic_title']) ?>
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <?php foreach ($rowsToRender as $forum): ?>
              <?php
                $fid = (int) $forum['id'];
                $isLocked = (int) ($forum['is_locked'] ?? 0) === 1;
                $isRedirect = !empty($forum['redirect_url']);
                $isUnread = !empty($unreadForumsMap[$fid]);
                $iconClass = $isRedirect ? 'is-redirect' : ($isLocked ? 'is-locked' : ($isUnread ? 'is-unread' : ''));
                $iconSvg = $isRedirect ? $extIconSvg : ($isLocked ? $lockIconSvg : $msgIconSvg);
              ?>
              <div class="vf-forum-row">
                <div class="vf-forum-main">
                  <span class="vf-forum-icon <?= $iconClass ?>" aria-hidden="true"><?= $iconSvg ?></span>
                  <div style="min-width:0;flex:1;">
                    <div class="vf-forum-title-row">
                      <?php if ($isRedirect): ?>
                        <a href="<?= View::e((string) $forum['redirect_url']) ?>" target="_blank" rel="noopener noreferrer" class="vf-forum-link">
                          <?= View::e($forum['name']) ?> ↗
                        </a>
                        <span class="vf-muted">Link zewnętrzny</span>
                      <?php else: ?>
                        <a href="/forum/<?= $fid ?>-<?= View::e($forum['slug']) ?>" class="vf-forum-link">
                          <?= View::e($forum['name']) ?>
                        </a>
                      <?php endif; ?>
                      <?php if ($isUnread && !$isRedirect): ?>
                        <span class="vf-unread-dot" title="Nowe posty w dziale"></span>
                      <?php endif; ?>
                      <?php if ($isLocked): ?>
                        <span class="vf-muted">(Zamknięty)</span>
                      <?php endif; ?>
                    </div>
                    <?php if ($curLayout !== 'compact' && !empty($forum['description'])): ?>
                      <div class="vf-desc" style="margin-top:2px;"><?= View::e($forum['description']) ?></div>
                    <?php endif; ?>
                    <?php $fMods = $forumModGroups[$fid] ?? []; ?>
                    <?php if (!empty($fMods)): ?>
                      <div class="vf-muted" style="margin-top:4px;">
                        Opiekun:
                        <?php foreach ($fMods as $mIdx => $mg): ?>
                          <?= $mIdx > 0 ? ', ' : '' ?><strong style="color:<?= View::e((string) ($mg['color_hex'] ?? '#7c3aed')) ?>;"><?= View::e((string) $mg['name']) ?></strong>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($forum['children'])): ?>
                      <?php $miniSubIcon = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'; ?>
                      <div class="vf-subforums-wrap">
                        <div class="vf-subforums-2col">
                          <?php foreach ($forum['children'] as $sub): ?>
                            <?php
                              $subId = (int) $sub['id'];
                              $subUnread = !empty($unreadForumsMap[$subId]);
                            ?>
                            <a href="/forum/<?= $subId ?>-<?= View::e($sub['slug']) ?>" class="vf-subforum-chip<?= $subUnread ? ' is-unread' : '' ?>">
                              <span class="vf-subforum-chip-main">
                                <span class="vf-subforum-mini-ico" aria-hidden="true"><?= $miniSubIcon ?></span>
                                <span class="vf-subforum-name"><?= View::e($sub['name']) ?></span>
                              </span>
                            </a>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="vf-forum-stats">
                  <div><strong><?= (int) $forum['topics_count'] ?></strong><span>Tematów</span></div>
                  <div><strong><?= (int) $forum['posts_count'] ?></strong><span>Postów</span></div>
                </div>

                <div>
                  <?php if (!empty($forum['last_topic_id']) && !empty($forum['last_topic_title'])): ?>
                    <?php
                      $lpName = (string) ($forum['last_poster_name'] ?? 'Użytkownik');
                      $lpInitials = mb_strtoupper(mb_substr($lpName, 0, 2));
                      $lpAt = (int) ($forum['last_post_at'] ?? 0);
                    ?>
                    <div class="vf-last-post">
                      <span class="vf-avatar">
                        <?php if (!empty($forum['last_poster_avatar'])): ?>
                          <img src="<?= View::e((string) $forum['last_poster_avatar']) ?>" alt="<?= View::e($lpName) ?>" data-vf-initials="<?= View::e($lpInitials) ?>" loading="lazy" decoding="async">
                        <?php else: ?>
                          <?= View::e($lpInitials) ?>
                        <?php endif; ?>
                      </span>
                      <div class="vf-last-post-meta">
                        <a class="vf-last-post-title" href="/topic/<?= (int) $forum['last_topic_id'] ?>-<?= View::e((string) ($forum['last_topic_slug'] ?? 'temat')) ?>">
                          <?= View::e((string) $forum['last_topic_title']) ?>
                        </a>
                        <div class="vf-last-post-sub">
                          <?php if (!empty($forum['last_poster_id'])): ?>
                            <a href="/member/<?= (int) $forum['last_poster_id'] ?>-<?= View::e((string) ($forum['last_poster_slug'] ?? 'profil')) ?>">
                              <?= View::formatUsername($lpName, $forum['last_poster_color'] ?? null, $forum['last_poster_prefix'] ?? null, $forum['last_poster_suffix'] ?? null, $forum['last_poster_group'] ?? null) ?>
                            </a>
                          <?php else: ?>
                            <?= View::formatUsername($lpName, $forum['last_poster_color'] ?? null, $forum['last_poster_prefix'] ?? null, $forum['last_poster_suffix'] ?? null, $forum['last_poster_group'] ?? null) ?>
                          <?php endif; ?>
                          <?php if ($lpAt > 0): ?>
                            · <span title="<?= date('d.m.Y H:i', $lpAt) ?>"><?= View::e(View::formatRelativeTime($lpAt)) ?></span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="vf-muted">Brak tematów w dziale</div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    <?php endforeach; ?>

    <?php if ($chatPos === 'bottom' && $chatBoxHtml !== ''): ?>
      <?= $chatBoxHtml ?>
    <?php endif; ?>

    <?php
      $oMemCount = (int) ($onlineCount ?? count($onlineMembers ?? []));
      $oGuests = (int) ($onlineGuestsCount ?? 0);
      $visibleLegendGroups = array_values(array_filter(
        $forumGroups ?? [],
        static fn (array $fg): bool => (int) ($fg['show_in_legend'] ?? 1) === 1
      ));
    ?>
    <section class="vf-card vf-online-footer-card" data-vf-online-widget>
      <div class="vf-card-head vf-card-head-flex">
        <span class="vf-head-icon-title">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span>Kto jest online w społeczności</span>
        </span>
        <span class="vf-presence-badge is-online" data-vf-online-summary>
          <span class="vf-presence-dot is-online" aria-hidden="true"></span>
          <span data-vf-online-count-text><?= $oMemCount ?> aktywnych<?= $oGuests > 0 ? ' · ' . $oGuests . ' gości' : '' ?></span>
        </span>
      </div>
      <div class="vf-online-chips" data-vf-online-list>
        <?php if (empty($onlineMembers)): ?>
          <span class="vf-muted" style="font-size:0.84rem;">Brak zalogowanych użytkowników aktywnych w ostatnich 20 minutach.</span>
        <?php else: ?>
          <?php foreach ($onlineMembers as $om): ?>
            <?php
              $omLast = isset($om['last_seen']) ? (int) $om['last_seen'] : (isset($om['last_activity_at']) ? (int) $om['last_activity_at'] : time());
              $omName = (string) ($om['username'] ?? 'Użytkownik');
              $omInit = mb_strtoupper(mb_substr($omName, 0, 2));
              $omTitle = (!empty($om['topic_id']) && !empty($om['topic_title']))
                ? ('Czyta temat: ' . (string) $om['topic_title'])
                : View::presenceText($omLast, $omLast);
            ?>
            <a href="/member/<?= (int) $om['id'] ?>-<?= View::e((string) $om['slug']) ?>" class="vf-online-chip" title="<?= View::e($omTitle) ?>">
              <span class="vf-avatar" style="width:24px;height:24px;border-radius:7px;font-size:0.62rem;">
                <?php if (!empty($om['avatar_url'])): ?>
                  <img src="<?= View::e((string) $om['avatar_url']) ?>" alt="<?= View::e($omName) ?>" data-vf-initials="<?= View::e($omInit) ?>" loading="lazy" decoding="async">
                <?php else: ?>
                  <?= View::e($omInit) ?>
                <?php endif; ?>
                <span class="vf-avatar-online" style="width:7px;height:7px;border-width:1.5px;" aria-hidden="true"></span>
              </span>
              <span><?= View::formatUsername($omName, $om['group_color'] ?? null, $om['group_prefix'] ?? null, $om['group_suffix'] ?? null, $om['group_name'] ?? null) ?></span>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <?php if (!empty($visibleLegendGroups)): ?>
      <section class="vf-card">
        <div class="vf-card-head vf-card-head-flex">
          <span class="vf-head-icon-title">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>Legenda grup społeczności</span>
          </span>
          <a href="/members" style="font-size:0.78rem;font-weight:600;">Katalog użytkowników →</a>
        </div>
        <div class="vf-group-legend">
          <?php foreach ($visibleLegendGroups as $fg): ?>
            <span class="vf-group-chip">
              <span class="vf-group-swatch" style="background:<?= View::e(View::safeGroupColor($fg['color_hex'] ?? null)) ?>;" aria-hidden="true"></span>
              <?= View::formatUsername((string) $fg['name'], $fg['color_hex'] ?? null, $fg['prefix_html'] ?? null, $fg['suffix_html'] ?? null, $fg['name'] ?? null) ?>
            </span>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </div>

  <?php if ($sidebarPos !== 'none'): ?>
  <aside>
    <?php if (!empty($currentUser)): ?>
      <?php
        $cuName = (string) $currentUser['username'];
        $cuInit = mb_strtoupper(mb_substr($cuName, 0, 2));
        $cuRep = (int) ($currentUser['reputation_points'] ?? 0);
        $cuRank = View::reputationRank($cuRep);
      ?>
      <section class="vf-card">
        <div style="padding:14px 18px;display:flex;align-items:center;gap:12px;">
          <span class="vf-avatar vf-avatar-md">
            <?php if (!empty($currentUser['avatar_url'])): ?>
              <img src="<?= View::e((string) $currentUser['avatar_url']) ?>" alt="<?= View::e($cuName) ?>" data-vf-initials="<?= View::e($cuInit) ?>" loading="lazy" decoding="async">
            <?php else: ?>
              <?= View::e($cuInit) ?>
            <?php endif; ?>
            <span class="vf-avatar-online" title="Aktywny teraz"></span>
          </span>
          <div style="min-width:0;flex:1;">
            <a href="/member/<?= (int) $currentUser['id'] ?>-<?= View::e((string) ($currentUser['slug'] ?? 'profil')) ?>" style="display:block;font-size:0.92rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              <?= View::formatUsername($cuName, $currentUser['color_hex'] ?? null, $currentUser['group_prefix'] ?? null, $currentUser['group_suffix'] ?? null, $currentUser['group_name'] ?? null) ?>
            </a>
            <div style="font-size:0.74rem;font-weight:600;color:<?= View::e($cuRank['color']) ?>;">
              <?= View::e((string) ($currentUser['group_name'] ?? 'Użytkownik')) ?> · <?= View::e($cuRank['label']) ?>
            </div>
          </div>
        </div>
        <div style="padding:0 18px 14px;">
          <div class="vf-rep-bar" style="margin-top:0;" aria-hidden="true">
            <span style="width:<?= (int) $cuRank['pct'] ?>%;"></span>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:8px;font-size:0.75rem;color:var(--muted);font-variant-numeric:tabular-nums;">
            <span><strong><?= (int) ($currentUser['posts_count'] ?? 0) ?></strong> postów · <strong style="color:var(--accent);"><?= $cuRep ?> pkt</strong></span>
            <a href="/member/<?= (int) $currentUser['id'] ?>-<?= View::e((string) ($currentUser['slug'] ?? 'profil')) ?>" style="font-weight:600;">Mój profil →</a>
          </div>
        </div>
      </section>
    <?php endif; ?>
    <?php foreach ($widgets as $w): ?>
      <?php if (empty($w['enabled'])) { continue; } ?>
      <?php $wid = (string) ($w['id'] ?? ''); ?>

      <?php if ($wid === 'custom' && trim((string) ($s['custom_widget_title'] ?? '')) !== ''): ?>
        <section class="vf-card">
          <div class="vf-card-head vf-head-icon-title" style="width:100%;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
            <span><?= View::e((string) $s['custom_widget_title']) ?></span>
          </div>
          <div style="padding:16px 20px;display:grid;gap:12px;">
            <?php if (!empty($s['custom_widget_body'])): ?>
              <div class="vf-desc"><?= nl2br(View::e((string) $s['custom_widget_body'])) ?></div>
            <?php endif; ?>
            <?php if (!empty($s['custom_widget_link_url']) && !empty($s['custom_widget_link_label'])): ?>
              <div>
                <a href="<?= View::e((string) $s['custom_widget_link_url']) ?>" class="vf-btn vf-btn-sm">
                  <?= View::e((string) $s['custom_widget_link_label']) ?>
                </a>
              </div>
            <?php endif; ?>
          </div>
        </section>

      <?php elseif ($wid === 'activity'): ?>
        <section class="vf-card">
          <div class="vf-card-head vf-card-head-flex">
            <span class="vf-head-icon-title">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
              <span>Ostatnia aktywność</span>
              <span class="vf-live-pill"><span class="vf-presence-dot is-online is-pulse" aria-hidden="true"></span><span>Na żywo</span></span>
            </span>
            <a href="/activity" style="font-size:0.78rem;font-weight:600;">Więcej</a>
          </div>
          <div data-vf-live-activity>
          <?php foreach ($latestTopics as $t): ?>
            <?php $tAt = (int) ($t['last_post_at'] ?? $t['created_at'] ?? 0); ?>
            <div class="vf-row" style="display:block;padding:12px 18px;">
              <div style="font-weight:600;font-size:0.875rem;line-height:1.35;">
                <?php if (!empty($t['prefix_label'])): ?>
                  <span class="vf-prefix" style="color:<?= View::e((string) ($t['prefix_color'] ?? '#2563eb')) ?>;"><?= View::e((string) $t['prefix_label']) ?></span>
                <?php endif; ?>
                <a href="/topic/<?= (int) $t['id'] ?>-<?= View::e($t['slug']) ?>" style="color:var(--ink);"><?= View::e($t['title']) ?></a>
              </div>
              <div class="vf-muted" style="margin-top:3px;">
                <?= View::e($t['forum_name']) ?> ·
                <?php if (!empty($t['last_poster_id'])): ?>
                  <a href="/member/<?= (int) $t['last_poster_id'] ?>-<?= View::e((string) ($t['last_poster_slug'] ?? 'profil')) ?>">
                    <?= View::formatUsername((string) $t['last_poster_name'], $t['last_poster_color'] ?? null, $t['last_poster_prefix'] ?? null, $t['last_poster_suffix'] ?? null, $t['last_poster_group'] ?? null) ?>
                  </a>
                <?php else: ?>
                  <?= View::formatUsername((string) $t['last_poster_name'], $t['last_poster_color'] ?? null, $t['last_poster_prefix'] ?? null, $t['last_poster_suffix'] ?? null, $t['last_poster_group'] ?? null) ?>
                <?php endif; ?>
                <?php if ($tAt > 0): ?> · <?= View::e(View::formatRelativeTime($tAt)) ?><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          </div>
        </section>

      <?php elseif ($wid === 'reputation'): ?>
        <?php
          $lbRows = $topReputation ?? [];
          $topScore = max(1, (int) ($lbRows[0]['score'] ?? $lbRows[0]['reputation_points'] ?? 1));
        ?>
        <section class="vf-card" data-vf-leaderboard data-metric="reputation" data-period="all">
          <div class="vf-card-head vf-card-head-flex">
            <span class="vf-head-icon-title">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
              <span>Rankingi społeczności</span>
            </span>
            <a href="/members?sort=reputation" style="font-size:0.78rem;font-weight:600;">Katalog</a>
          </div>
          <div class="vf-lb-controls">
            <div class="vf-lb-tabs" role="tablist" aria-label="Metryka rankingu">
              <button type="button" class="vf-lb-tab is-active" data-vf-lb-metric="reputation">Reputacja</button>
              <button type="button" class="vf-lb-tab" data-vf-lb-metric="posts">Posty</button>
              <button type="button" class="vf-lb-tab" data-vf-lb-metric="topics">Tematy</button>
            </div>
            <div class="vf-lb-periods" role="group" aria-label="Okres rankingu">
              <button type="button" class="vf-lb-period" data-vf-lb-period="week">Tydzień</button>
              <button type="button" class="vf-lb-period" data-vf-lb-period="month">Miesiąc</button>
              <button type="button" class="vf-lb-period" data-vf-lb-period="year">Rok</button>
              <button type="button" class="vf-lb-period is-active" data-vf-lb-period="all">Ogólnie</button>
            </div>
          </div>
          <div class="vf-lb-list" data-vf-lb-list>
            <?php if (empty($lbRows)): ?>
              <div class="vf-row"><span class="vf-muted">Brak danych do wyświetlenia.</span></div>
            <?php else: ?>
              <?php foreach ($lbRows as $idx => $rm): ?>
                <?php
                  $repPts = (int) ($rm['score'] ?? $rm['reputation_points'] ?? 0);
                  $barPct = max(8, (int) round(($repPts / $topScore) * 100));
                  $rmName = (string) ($rm['username'] ?? 'Użytkownik');
                  $rmInit = mb_strtoupper(mb_substr($rmName, 0, 2));
                ?>
                <div class="vf-rep-row">
                  <div class="vf-rep-top">
                    <div class="vf-rep-user">
                      <span class="vf-rep-rank">#<?= $idx + 1 ?></span>
                      <span class="vf-avatar" style="width:32px;height:32px;border-radius:9px;">
                        <?php if (!empty($rm['avatar_url'])): ?>
                          <img src="<?= View::e((string) $rm['avatar_url']) ?>" alt="<?= View::e($rmName) ?>" data-vf-initials="<?= View::e($rmInit) ?>" loading="lazy" decoding="async">
                        <?php else: ?>
                          <?= View::e($rmInit) ?>
                        <?php endif; ?>
                      </span>
                      <div style="min-width:0;">
                        <a href="/member/<?= (int) $rm['id'] ?>-<?= View::e((string) $rm['slug']) ?>" style="display:block;font-size:0.84rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                          <?= View::formatUsername($rmName, $rm['group_color'] ?? null, $rm['group_prefix'] ?? null, $rm['group_suffix'] ?? null, $rm['group_name'] ?? null) ?>
                        </a>
                        <div style="font-size:0.72rem;font-weight:600;color:<?= View::e(View::safeGroupColor($rm['group_color'] ?? null, '#64748b')) ?>;">
                          <?= View::e((string) ($rm['group_name'] ?? 'Użytkownik')) ?>
                        </div>
                      </div>
                    </div>
                    <strong style="font-size:0.78rem;font-variant-numeric:tabular-nums;color:var(--accent);white-space:nowrap;"><?= $repPts ?> pkt</strong>
                  </div>
                  <div class="vf-rep-bar" aria-hidden="true">
                    <span style="width:<?= $barPct ?>%;"></span>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>

      <?php elseif ($wid === 'tags' && !empty($popularTags)): ?>
        <section class="vf-card">
          <div class="vf-card-head vf-head-icon-title" style="width:100%;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
            <span>Popularne tagi</span>
          </div>
          <div class="vf-tag-cloud">
            <?php foreach ($popularTags as $tg): ?>
              <a href="/tag/<?= View::e((string) $tg['tag_slug']) ?>" class="vf-tag-pill">
                #<?= View::e((string) $tg['tag_label']) ?>
                <span class="vf-muted">(<?= (int) $tg['total'] ?>)</span>
              </a>
            <?php endforeach; ?>
          </div>
        </section>

      <?php elseif ($wid === 'online'): ?>
        <?php continue; ?>

      <?php elseif ($wid === 'reactions' && !empty($reactionPulse)): ?>
        <section class="vf-card">
          <div class="vf-card-head vf-head-icon-title" style="width:100%;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
            <span>Ostatnie reakcje społeczności</span>
          </div>
          <?php $pulseEmojis = ['like' => '👍', 'thanks' => '❤️', 'helpful' => '🙌', 'insightful' => '💡']; ?>
          <?php foreach ($reactionPulse as $rp): ?>
            <?php $rpAt = (int) ($rp['created_at'] ?? 0); ?>
            <div class="vf-pulse-row">
              <span class="vf-pulse-emoji" aria-hidden="true"><?= $pulseEmojis[$rp['reaction_code'] ?? 'like'] ?? '👍' ?></span>
              <div style="min-width:0;flex:1;">
                <div style="font-size:0.82rem;line-height:1.4;color:var(--ink);">
                  <a href="/member/<?= (int) $rp['actor_id'] ?>-<?= View::e((string) ($rp['actor_slug'] ?? 'profil')) ?>">
                    <?= View::formatUsername((string) $rp['actor_name'], $rp['actor_group_color'] ?? null, $rp['actor_group_prefix'] ?? null, $rp['actor_group_suffix'] ?? null, $rp['actor_group_name'] ?? null) ?>
                  </a>
                  <span class="vf-muted">zareagował(a) w</span>
                  <a href="/topic/<?= (int) $rp['topic_id'] ?>-<?= View::e((string) $rp['topic_slug']) ?>#post-<?= (int) $rp['post_id'] ?>" style="font-weight:600;">
                    <?= View::e((string) $rp['topic_title']) ?>
                  </a>
                </div>
                <div class="vf-muted" style="margin-top:2px;font-size:0.74rem;">
                  <?= View::e((string) $rp['reaction_label']) ?>
                  <?php if ($rpAt > 0): ?> · <span title="<?= date('d.m.Y H:i', $rpAt) ?>"><?= View::e(View::formatRelativeTime($rpAt)) ?></span><?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </section>

      <?php elseif ($wid === 'stats'): ?>
        <?php
          $topicsTotal = max(0, (int) ($stats['topics'] ?? 0));
          $solvedTotal = max(0, (int) ($solvedTopicsCount ?? 0));
          $solveRate = $topicsTotal > 0 ? (int) round(($solvedTotal / $topicsTotal) * 100) : 0;
        ?>
        <section class="vf-card">
          <div class="vf-card-head vf-card-head-flex">
            <span class="vf-head-icon-title">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
              <span>Statystyki społeczności</span>
            </span>
            <a href="/members" style="font-size:0.78rem;font-weight:600;">Użytkownicy</a>
          </div>
          <div class="vf-row" style="padding:11px 18px;"><span>Użytkownicy</span><strong><?= (int) $stats['members'] ?></strong></div>
          <div class="vf-row" style="padding:11px 18px;"><span>Tematy</span><strong><?= $topicsTotal ?></strong></div>
          <div class="vf-row" style="padding:11px 18px;"><span>Posty</span><strong><?= (int) $stats['posts'] ?></strong></div>
          <div class="vf-row" style="padding:11px 18px;"><span>Wskaźnik rozwiązań</span><strong class="vf-solved-label"><?= $solveRate ?>% (<?= $solvedTotal ?>/<?= $topicsTotal ?>)</strong></div>
          <div class="vf-row" style="padding:11px 18px;"><span>Reakcje na postach</span><strong><?= (int) ($totalReactions ?? 0) ?></strong></div>
          <?php if (!empty($stats['newest']['username'])): ?>
            <div class="vf-row" style="padding:11px 18px;">
              <span>Najnowszy</span>
              <a href="/member/<?= (int) $stats['newest']['id'] ?>-<?= View::e((string) ($stats['newest']['slug'] ?? 'profil')) ?>">
                <?= View::formatUsername((string) $stats['newest']['username'], $stats['newest']['group_color'] ?? null, $stats['newest']['group_prefix'] ?? null, $stats['newest']['group_suffix'] ?? null, $stats['newest']['group_name'] ?? null) ?>
              </a>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    <?php endforeach; ?>
  </aside>
  <?php endif; ?>
</div>
