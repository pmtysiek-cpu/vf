<?php use VeloForum\Core\Csrf; use VeloForum\Core\View; use VeloForum\Service\HtmlSanitizer; ?>
<?php
  $chevronRightSvg = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>';
  $showCompose = !empty($composeRecipient) || (($_GET['new'] ?? '') === '1');
  $activeConvId = !empty($activeConversation) ? (int) $activeConversation['id'] : 0;
  $lastMsgId = 0;
  foreach ($threadMessages ?? [] as $tm) {
    if ((int) $tm['id'] > $lastMsgId) {
      $lastMsgId = (int) $tm['id'];
    }
  }
?>
<nav class="vf-breadcrumb" aria-label="Ścieżka nawigacji">
  <a href="/">Forum</a>
  <?= $chevronRightSvg ?>
  <span class="vf-breadcrumb-cur">Wiadomości prywatne</span>
</nav>

<div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:18px;">
  <div>
    <h1 style="margin:0 0 4px;font-size:1.5rem;">Wiadomości prywatne</h1>
    <p class="vf-muted" style="margin:0;">Bezpośrednie rozmowy 1-na-1 z odświeżaniem na żywo.</p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <a href="/messages?new=1" class="vf-btn vf-btn-sm">+ Nowa wiadomość</a>
  </div>
</div>

<?php if (!empty($pmError)): ?>
  <div class="vf-alert-danger" style="padding:12px 16px;margin-bottom:16px;border-radius:12px;border:1px solid var(--danger-border);background:var(--danger-bg);color:var(--danger-ink);font-size:0.875rem;font-weight:500;">
    <?= View::e((string) $pmError) ?>
  </div>
<?php endif; ?>

<div
  class="vf-pm-shell vf-card<?= !empty($mobileThreadOpen) ? ' is-thread-open' : '' ?>"
  data-vf-pm-root
  data-conv-id="<?= $activeConvId ?>"
  data-last-msg-id="<?= $lastMsgId ?>"
  data-csrf="<?= View::e(Csrf::token()) ?>"
>
  <aside class="vf-pm-sidebar">
    <div class="vf-pm-sidebar-head">
      <span>Skrzynka (<?= count($conversations ?? []) ?>)</span>
      <span class="vf-muted" style="font-size:0.75rem;font-weight:500;">Live co 4s</span>
    </div>
    <div class="vf-pm-conv-list" data-vf-pm-conv-list>
      <?php if (empty($conversations)): ?>
        <div class="vf-muted" style="padding:22px 16px;text-align:center;font-size:0.84rem;">
          Brak konwersacji. Kliknij <strong>+ Nowa wiadomość</strong>, aby napisać do członka społeczności.
        </div>
      <?php else: ?>
        <?php foreach ($conversations as $conv): ?>
          <?php
            $cid = (int) $conv['id'];
            $isActive = $cid === $activeConvId && !$showCompose;
            $isUnread = !empty($conv['is_unread']);
            $pName = (string) ($conv['peer_username'] ?? 'Użytkownik');
            $pInit = mb_strtoupper(mb_substr($pName, 0, 2));
            $pOnline = View::isOnline($conv['peer_last_activity_at'] !== null ? (int) $conv['peer_last_activity_at'] : null);
          ?>
          <a href="/messages?c=<?= $cid ?>" class="vf-pm-conv-item<?= $isActive ? ' is-active' : '' ?><?= $isUnread ? ' is-unread' : '' ?>" data-conv-item-id="<?= $cid ?>">
            <span class="vf-avatar" style="width:36px;height:36px;border-radius:10px;flex-shrink:0;">
              <?php if (!empty($conv['peer_avatar'])): ?>
                <img src="<?= View::e((string) $conv['peer_avatar']) ?>" alt="<?= View::e($pName) ?>" data-vf-initials="<?= View::e($pInit) ?>" loading="lazy">
              <?php else: ?>
                <?= View::e($pInit) ?>
              <?php endif; ?>
              <?php if ($pOnline): ?>
                <span class="vf-avatar-online" style="width:9px;height:9px;border-width:1.5px;" title="Aktywny teraz"></span>
              <?php endif; ?>
            </span>
            <div style="min-width:0;flex:1;">
              <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;">
                <span class="vf-pm-peer-name">
                  <?= View::formatUsername($pName, $conv['peer_color'] ?? null, $conv['peer_prefix'] ?? null, $conv['peer_suffix'] ?? null, $conv['peer_group'] ?? null) ?>
                </span>
                <span class="vf-muted" style="font-size:0.7rem;white-space:nowrap;">
                  <?= View::e(View::formatRelativeTime((int) ($conv['last_message_at'] ?? 0))) ?>
                </span>
              </div>
              <div class="vf-pm-subject"><?= View::e((string) $conv['subject']) ?></div>
              <div class="vf-pm-snippet"><?= View::e((string) ($conv['last_snippet'] ?? '')) ?></div>
            </div>
            <?php if ($isUnread): ?>
              <span class="vf-pm-unread-dot" title="Nieprzeczytana wiadomość"></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </aside>

  <section class="vf-pm-chat">
    <?php if ($showCompose): ?>
      <div class="vf-pm-chat-head">
        <div style="display:flex;align-items:center;gap:10px;min-width:0;">
          <a href="/messages?list=1" class="vf-btn vf-btn-sm vf-btn-secondary vf-pm-back-btn">← Skrzynka</a>
          <div>
            <strong style="font-size:1rem;">Nowa wiadomość prywatna</strong>
            <div class="vf-muted" style="font-size:0.8rem;">Rozpocznij prywatny wątek z wybranym użytkownikiem.</div>
          </div>
        </div>
        <a href="/messages?list=1" class="vf-btn vf-btn-sm vf-btn-secondary">Anuluj</a>
      </div>
      <form method="post" action="/messages/send" class="vf-pm-compose-form">
        <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
        <input type="hidden" name="recipient_id" value="<?= !empty($composeRecipient) ? (int) $composeRecipient['id'] : 0 ?>" data-vf-pm-recipient-id>

        <div style="position:relative;">
          <label class="vf-muted" style="display:block;font-weight:600;margin-bottom:4px;">Odbiorca</label>
          <input
            type="text"
            name="recipient_username"
            class="vf-input"
            autocomplete="off"
            value="<?= !empty($composeRecipient) ? View::e((string) $composeRecipient['username']) : '' ?>"
            placeholder="Wpisz nazwę użytkownika..."
            data-vf-pm-recipient-input
            required
          >
          <div class="vf-pm-autocomplete" data-vf-pm-recipient-suggestions hidden></div>
        </div>

        <div>
          <label class="vf-muted" style="display:block;font-weight:600;margin-bottom:4px;">Temat rozmowy</label>
          <input type="text" name="subject" maxlength="180" class="vf-input" placeholder="Np. Pytanie dotyczące działu / serwera..." required>
        </div>

        <div>
          <label class="vf-muted" style="display:block;font-weight:600;margin-bottom:4px;">Treść wiadomości</label>
          <textarea name="content" rows="6" class="vf-textarea" placeholder="Napisz wiadomość..." required></textarea>
        </div>

        <div style="display:flex;gap:8px;">
          <button type="submit" class="vf-btn">Wyślij wiadomość</button>
        </div>
      </form>

    <?php elseif (!empty($activeConversation)): ?>
      <?php
        $peerName = (string) ($activeConversation['peer_username'] ?? 'Użytkownik');
        $peerInit = mb_strtoupper(mb_substr($peerName, 0, 2));
        $peerOnline = View::isOnline($activeConversation['peer_last_activity_at'] !== null ? (int) $activeConversation['peer_last_activity_at'] : null);
      ?>
      <div class="vf-pm-chat-head">
        <div style="display:flex;align-items:center;gap:10px;min-width:0;flex:1;">
          <a href="/messages?list=1" class="vf-btn vf-btn-sm vf-btn-secondary vf-pm-back-btn">← Skrzynka</a>
          <span class="vf-avatar" style="width:40px;height:40px;border-radius:11px;flex-shrink:0;">
            <?php if (!empty($activeConversation['peer_avatar'])): ?>
              <img src="<?= View::e((string) $activeConversation['peer_avatar']) ?>" alt="<?= View::e($peerName) ?>" data-vf-initials="<?= View::e($peerInit) ?>">
            <?php else: ?>
              <?= View::e($peerInit) ?>
            <?php endif; ?>
            <?php if ($peerOnline): ?>
              <span class="vf-avatar-online" title="Aktywny teraz"></span>
            <?php endif; ?>
          </span>
          <div style="min-width:0;">
            <div style="font-weight:700;font-size:0.98rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              <?= View::e((string) $activeConversation['subject']) ?>
            </div>
            <div class="vf-muted" style="font-size:0.78rem;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
              <span>Rozmowa z:</span>
              <?php if (!empty($activeConversation['peer_id'])): ?>
                <a href="/member/<?= (int) $activeConversation['peer_id'] ?>-<?= View::e((string) ($activeConversation['peer_slug'] ?? 'profil')) ?>">
                  <?= View::formatUsername($peerName, $activeConversation['peer_color'] ?? null, $activeConversation['peer_prefix'] ?? null, $activeConversation['peer_suffix'] ?? null, $activeConversation['peer_group'] ?? null) ?>
                </a>
              <?php else: ?>
                <?= View::formatUsername($peerName, $activeConversation['peer_color'] ?? null, $activeConversation['peer_prefix'] ?? null, $activeConversation['peer_suffix'] ?? null, $activeConversation['peer_group'] ?? null) ?>
              <?php endif; ?>
              <span>· <?= $peerOnline ? 'Aktywny teraz' : View::e(View::presenceText($activeConversation['peer_last_activity_at'] !== null ? (int) $activeConversation['peer_last_activity_at'] : null)) ?></span>
            </div>
          </div>
        </div>
        <form method="post" action="/messages/<?= $activeConvId ?>/leave" data-vf-confirm-delete style="margin:0;">
          <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
          <button type="submit" class="vf-btn vf-btn-sm vf-btn-secondary" data-confirm-label="Potwierdź usunięcie">Usuń ze skrzynki</button>
        </form>
      </div>

      <div class="vf-pm-thread" data-vf-pm-thread>
        <?php foreach ($threadMessages ?? [] as $msg): ?>
          <?php
            $isMine = (int) ($msg['sender_id'] ?? 0) === (int) ($currentUser['id'] ?? 0);
            $sName = (string) ($msg['sender_name'] ?? 'Użytkownik');
            $mTime = (int) ($msg['created_at'] ?? 0);
          ?>
          <div class="vf-pm-msg-row<?= $isMine ? ' is-mine' : ' is-peer' ?>" data-msg-id="<?= (int) $msg['id'] ?>">
            <div class="vf-pm-bubble<?= $isMine ? ' is-mine' : ' is-peer' ?>">
              <div class="vf-pm-bubble-meta">
                <span><?= View::formatUsername($sName, $msg['sender_color'] ?? null, $msg['sender_prefix'] ?? null, $msg['sender_suffix'] ?? null, $msg['sender_group'] ?? null) ?></span>
                <span title="<?= date('d.m.Y H:i', $mTime) ?>"><?= View::e(View::formatRelativeTime($mTime)) ?></span>
              </div>
              <div class="vf-pm-bubble-body"><?= HtmlSanitizer::clean((string) ($msg['content_html'] ?? '')) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <form method="post" action="/messages/<?= $activeConvId ?>/reply" class="vf-pm-reply-bar" data-vf-pm-reply-form>
        <input type="hidden" name="_csrf" value="<?= View::e(Csrf::token()) ?>">
        <textarea name="content" rows="2" class="vf-textarea" placeholder="Napisz odpowiedź... (Enter + Ctrl wysyła)" required data-vf-pm-reply-input></textarea>
        <button type="submit" class="vf-btn">Wyślij</button>
      </form>

    <?php else: ?>
      <div class="vf-pm-empty">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="color:var(--muted);" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <div style="font-weight:700;font-size:1.05rem;">Twoja skrzynka wiadomości</div>
        <p class="vf-muted" style="max-width:340px;margin:0;text-align:center;">Wybierz wątek z listy po lewej stronie lub rozpocznij nową prywatną rozmowę.</p>
        <a href="/messages?new=1" class="vf-btn vf-btn-sm">+ Nowa wiadomość</a>
      </div>
    <?php endif; ?>
  </section>
</div>
