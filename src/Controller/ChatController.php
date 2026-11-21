<?php

declare(strict_types=1);

namespace VeloForum\Controller;

use VeloForum\Core\Csrf;
use VeloForum\Core\View;
use VeloForum\Repository\AdminRepository;
use VeloForum\Repository\ChatRepository;
use VeloForum\Service\AuthService;
use VeloForum\Service\PermissionService;

final class ChatController
{
    public function __construct(
        private readonly ChatRepository $chat,
        private readonly AdminRepository $admin,
        private readonly AuthService $auth,
        private readonly PermissionService $permissions
    ) {
    }

    public function apiLive(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $user = $this->auth->currentMember();
        $settings = $this->admin->settings();
        if (($settings['chat_enabled'] ?? '1') !== '1') {
            echo json_encode(['ok' => true, 'enabled' => false, 'messages' => [], 'pinned' => null], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!$user && ($settings['chat_allow_guests'] ?? '1') !== '1') {
            echo json_encode(['ok' => true, 'enabled' => true, 'guest_hidden' => true, 'messages' => [], 'pinned' => null], JSON_UNESCAPED_UNICODE);
            return;
        }

        $afterId = max(0, (int) ($_GET['after_id'] ?? 0));
        $viewerId = $user ? (int) $user['id'] : null;
        $messages = $this->chat->recentMessages(45, $afterId, $viewerId);
        $pinned = $this->chat->pinnedMessage($viewerId);

        echo json_encode([
            'ok'               => true,
            'enabled'          => true,
            'can_use'          => $this->permissions->canUseChat($user),
            'can_moderate'     => $this->permissions->canModerateChat($user),
            'is_muted'         => $this->permissions->isChatMuted($user),
            'allow_gif'        => ($settings['chat_allow_gif'] ?? '1') === '1',
            'allow_reactions'  => ($settings['chat_allow_reactions'] ?? '1') === '1',
            'messages'         => array_map(fn (array $m): array => $this->formatMessagePayload($m, $user), $messages),
            'pinned'           => $pinned ? $this->formatMessagePayload($pinned, $user) : null,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function apiSend(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $user = $this->auth->currentMember();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Zaloguj się, aby pisać na czacie.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy token bezpieczeństwa CSRF.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $settings = $this->admin->settings();
        if (($settings['chat_enabled'] ?? '1') !== '1') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Czat jest obecnie wyłączony przez administratora.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!$this->permissions->canUseChat($user)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Twoja grupa nie ma uprawnień do pisania na czacie.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($this->permissions->isChatMuted($user)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Twoje konto jest tymczasowo wyciszone na czacie.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $floodSeconds = max(0, (int) ($settings['chat_flood_seconds'] ?? 3));
        if ($floodSeconds > 0 && !$this->permissions->canModerateChat($user)) {
            $lastTs = $this->chat->lastMessageTimestampByMember((int) $user['id']);
            if ($lastTs > 0 && (time() - $lastTs) < $floodSeconds) {
                $wait = $floodSeconds - (time() - $lastTs);
                http_response_code(429);
                echo json_encode(['ok' => false, 'error' => "Odczekaj {$wait} s przed kolejną wiadomością."], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        $maxLen = max(60, min(1000, (int) ($settings['chat_max_length'] ?? 400)));
        $rawText = (string) ($_POST['message'] ?? '');
        $allowGif = ($settings['chat_allow_gif'] ?? '1') === '1';
        $gifUrl = $allowGif ? trim((string) ($_POST['attachment_url'] ?? '')) : '';
        $replyTo = trim((string) ($_POST['reply_to_username'] ?? ''));

        $result = $this->chat->send(
            (int) $user['id'],
            (string) $user['username'],
            $rawText,
            $gifUrl !== '' ? $gifUrl : null,
            $replyTo !== '' ? $replyTo : null,
            $maxLen
        );

        if (!$result['ok']) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Nie udało się wysłać wiadomości.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $newId = (int) ($result['id'] ?? 0);
        $fresh = $this->chat->recentMessages(10, max(0, $newId - 1), (int) $user['id']);
        $created = null;
        foreach ($fresh as $row) {
            if ((int) $row['id'] === $newId) {
                $created = $this->formatMessagePayload($row, $user);
                break;
            }
        }

        echo json_encode([
            'ok'      => true,
            'message' => $created,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function apiReact(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $user = $this->auth->currentMember();
        if (!$user || !Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Brak autoryzacji lub tokena CSRF.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $settings = $this->admin->settings();
        if (($settings['chat_allow_reactions'] ?? '1') !== '1' || !$this->permissions->canUseChat($user)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Reakcje na czacie są wyłączone.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $msgId = (int) ($_POST['message_id'] ?? 0);
        $emoji = trim((string) ($_POST['emoji'] ?? ''));
        if ($msgId <= 0 || !$this->chat->findById($msgId)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Wiadomość nie istnieje.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $reactions = $this->chat->toggleReaction($msgId, (int) $user['id'], $emoji);
        echo json_encode([
            'ok'         => true,
            'message_id' => $msgId,
            'reactions'  => $reactions,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $user = $this->auth->currentMember();
        if (!$user || !Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        $msgId = (int) ($_POST['message_id'] ?? 0);
        $msg = $msgId > 0 ? $this->chat->findById($msgId) : null;
        if (!$msg) {
            http_response_code(404);
            echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        $canDel = ((int) $msg['member_id'] === (int) $user['id']) || $this->permissions->canModerateChat($user);
        if (!$canDel) {
            http_response_code(403);
            echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->chat->deleteMessage($msgId);
        echo json_encode(['ok' => true, 'deleted_id' => $msgId], JSON_UNESCAPED_UNICODE);
    }

    public function apiPin(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $user = $this->auth->currentMember();
        if (!$user || !$this->permissions->canModerateChat($user) || !Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        $msgId = (int) ($_POST['message_id'] ?? 0);
        if ($msgId <= 0 || !$this->chat->findById($msgId)) {
            http_response_code(404);
            echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        $pinned = $this->chat->togglePin($msgId);
        $pinnedMsg = $pinned ? $this->chat->pinnedMessage((int) $user['id']) : null;

        echo json_encode([
            'ok'        => true,
            'is_pinned' => $pinned,
            'pinned'    => $pinnedMsg ? $this->formatMessagePayload($pinnedMsg, $user) : null,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function apiMute(): void
    {
        $user = $this->auth->currentMember();
        if (!$user || !$this->permissions->canModerateChat($user) || !Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Brak uprawnień moderatora czatu.');
        }

        $memberId = (int) ($_POST['member_id'] ?? 0);
        $minutes = (int) ($_POST['minutes'] ?? 0);
        if ($memberId > 0 && $memberId !== (int) $user['id']) {
            $this->chat->muteMember($memberId, max(0, min(43200, $minutes)));
        }

        $acceptsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
        if ($acceptsJson) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => true, 'member_id' => $memberId, 'minutes' => $minutes], JSON_UNESCAPED_UNICODE);
            return;
        }

        $_SESSION['_vf_acp_flash'] = $minutes > 0
            ? "Wyciszono użytkownika #{$memberId} na czacie ({$minutes} min)."
            : "Zdjęto wyciszenie czatu dla użytkownika #{$memberId}.";
        View::redirect('/admin?tab=chat');
    }

    public function saveSettings(): void
    {
        $user = $this->auth->currentMember();
        if (!$user || !$this->permissions->isGlobalAdmin($user) || !Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Wymagane uprawnienia administratora.');
        }

        $position = (string) ($_POST['chat_position'] ?? 'top');
        if (!in_array($position, ['top', 'bottom', 'hidden'], true)) {
            $position = 'top';
        }

        $this->admin->saveSetting('chat_enabled', !empty($_POST['chat_enabled']) ? '1' : '0');
        $this->admin->saveSetting('chat_title', mb_substr(trim((string) ($_POST['chat_title'] ?? 'Czat społeczności')), 0, 80) ?: 'Czat społeczności');
        $this->admin->saveSetting('chat_announcement', mb_substr(trim((string) ($_POST['chat_announcement'] ?? '')), 0, 320));
        $this->admin->saveSetting('chat_position', $position);
        $this->admin->saveSetting('chat_max_length', (string) max(80, min(1000, (int) ($_POST['chat_max_length'] ?? 400))));
        $this->admin->saveSetting('chat_flood_seconds', (string) max(0, min(60, (int) ($_POST['chat_flood_seconds'] ?? 3))));
        $this->admin->saveSetting('chat_allow_guests', !empty($_POST['chat_allow_guests']) ? '1' : '0');
        $this->admin->saveSetting('chat_allow_gif', !empty($_POST['chat_allow_gif']) ? '1' : '0');
        $this->admin->saveSetting('chat_allow_reactions', !empty($_POST['chat_allow_reactions']) ? '1' : '0');

        $_SESSION['_vf_acp_flash'] = 'Zapisano ustawienia modułu czatu na żywo.';
        View::redirect('/admin?tab=chat');
    }

    public function clearHistory(): void
    {
        $user = $this->auth->currentMember();
        if (!$user || !$this->permissions->isGlobalAdmin($user) || !Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Wymagane uprawnienia administratora.');
        }

        $this->chat->clearAll();
        $_SESSION['_vf_acp_flash'] = 'Wyczyszczono całą historię wiadomości czatu na żywo.';
        View::redirect('/admin?tab=chat');
    }

    /** @return array<string, mixed> */
    public function formatMessagePayload(array $row, ?array $viewer): array
    {
        $memberId = (int) $row['member_id'];
        $username = (string) $row['username'];
        $slug = (string) ($row['slug'] ?? strtolower($username));
        $canMod = $this->permissions->canModerateChat($viewer);
        $isOwn = $viewer && (int) $viewer['id'] === $memberId;

        return [
            'id'                => (int) $row['id'],
            'member_id'         => $memberId,
            'username'          => $username,
            'profile_url'       => View::url('/member/' . $memberId . '-' . urlencode($slug)),
            'avatar_url'        => View::safeMediaUrl($row['avatar_url'] ?? null),
            'formatted_html'    => View::formatUsername(
                $username,
                $row['group_color'] ?? null,
                $row['group_prefix'] ?? null,
                $row['group_suffix'] ?? null,
                $row['group_name'] ?? null
            ),
            'body_html'         => (string) $row['body_html'],
            'attachment_url'    => View::safeMediaUrl($row['attachment_url'] ?? null),
            'is_action'         => (int) ($row['is_action'] ?? 0) === 1,
            'is_pinned'         => (int) ($row['is_pinned'] ?? 0) === 1,
            'reply_to_username' => $row['reply_to_username'] !== null ? (string) $row['reply_to_username'] : null,
            'created_at'        => (int) $row['created_at'],
            'relative_time'     => View::formatRelativeTime((int) $row['created_at']),
            'reactions'         => $row['reactions'] ?? [],
            'can_delete'        => $isOwn || $canMod,
            'can_pin'           => $canMod,
            'can_mute'          => $canMod && !$isOwn,
        ];
    }
}
