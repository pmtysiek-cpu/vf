<?php

declare(strict_types=1);

namespace VeloForum\Controller;

use VeloForum\Core\Csrf;
use VeloForum\Core\View;
use VeloForum\Repository\MemberRepository;
use VeloForum\Repository\MessageRepository;
use VeloForum\Repository\NotificationRepository;
use VeloForum\Repository\PresenceRepository;
use VeloForum\Service\AuthService;
use VeloForum\Service\HtmlSanitizer;
use VeloForum\Service\PermissionService;
use VeloForum\Service\SeoService;

final class MessageController
{
    public function __construct(
        private readonly View $view,
        private readonly MessageRepository $messages,
        private readonly MemberRepository $members,
        private readonly NotificationRepository $notifications,
        private readonly AuthService $auth,
        private readonly SeoService $seo,
        private readonly PermissionService $permissions,
        private readonly ?PresenceRepository $presence = null
    ) {
    }

    public function index(): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }

        $memberId = (int) $user['id'];
        if ($this->presence !== null) {
            $this->presence->touch($this->presence->resolveSessionKey(), $user, 'messages', null, null);
        }

        $activeConvId = (int) ($_GET['c'] ?? 0);
        $toMemberId = (int) ($_GET['to'] ?? 0);
        $composeRecipient = null;
        if ($toMemberId > 0 && $toMemberId !== $memberId) {
            $target = $this->members->findProfileById($toMemberId);
            if ($target && (int) ($target['is_banned'] ?? 0) === 0) {
                $composeRecipient = $target;
            }
        }

        $explicitConvSelected = (($_GET['c'] ?? '') !== '') || $composeRecipient !== null || (($_GET['new'] ?? '') === '1');
        $forceList = (($_GET['list'] ?? '') === '1');

        $conversations = $this->messages->listConversationsForMember($memberId, 50);
        if (!$forceList && $activeConvId <= 0 && $composeRecipient === null && $conversations !== []) {
            $activeConvId = (int) $conversations[0]['id'];
        }

        $activeConversation = null;
        $threadMessages = [];
        if ($activeConvId > 0) {
            $activeConversation = $this->messages->findConversationForMember($activeConvId, $memberId);
            if ($activeConversation !== null) {
                $this->messages->markConversationRead($activeConvId, $memberId);
                $threadMessages = $this->messages->messagesInConversation($activeConvId, 0, 100);
                foreach ($conversations as &$cItem) {
                    if ((int) $cItem['id'] === $activeConvId) {
                        $cItem['is_unread'] = false;
                    }
                }
                unset($cItem);
            }
        }

        $mobileThreadOpen = ($explicitConvSelected || $activeConversation !== null) && !$forceList && $explicitConvSelected;

        $pmError = (string) ($_SESSION['_vf_pm_error'] ?? '');
        unset($_SESSION['_vf_pm_error']);

        $this->view->render('messages', [
            'pageTitle'           => 'Wiadomości prywatne',
            'conversations'       => $conversations,
            'activeConversation'  => $activeConversation,
            'threadMessages'      => $threadMessages,
            'composeRecipient'    => $composeRecipient,
            'mobileThreadOpen'    => $mobileThreadOpen,
            'pmError'             => $pmError,
            'currentUser'         => $user,
            'canAccessModCp'      => $this->permissions->canAccessModCp($user),
            'unreadCount'         => $this->notifications->unreadCount($memberId),
            'unreadMessagesCount' => $this->messages->unreadCountForMember($memberId),
            'seo'                => $this->seo->build([
                'title'  => 'Wiadomości prywatne — VeloForum',
                'path'   => '/messages',
                'robots' => 'noindex, nofollow',
            ]),
        ]);
    }

    public function send(): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }
        if ((int) ($user['is_banned'] ?? 0) === 1) {
            http_response_code(403);
            exit('Zablokowane konto nie może wysyłać wiadomości.');
        }

        $senderId = (int) $user['id'];
        $recipientId = (int) ($_POST['recipient_id'] ?? 0);
        $recipientUsername = trim((string) ($_POST['recipient_username'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $rawBody = trim((string) ($_POST['content'] ?? ''));

        $recipient = null;
        if ($recipientId > 0) {
            $recipient = $this->members->findProfileById($recipientId);
        } elseif ($recipientUsername !== '') {
            $matches = $this->members->findByUsernames([$recipientUsername]);
            if ($matches !== []) {
                $recipient = $this->members->findProfileById((int) $matches[0]['id']);
            }
        }

        if (!$recipient || (int) $recipient['id'] === $senderId || (int) ($recipient['is_banned'] ?? 0) === 1) {
            $_SESSION['_vf_pm_error'] = 'Wybierz poprawnego odbiorcę wiadomości (innego aktywnego członka społeczności).';
            View::redirect('/messages');
        }

        $cleanHtml = HtmlSanitizer::sanitizeSubmittedContent($rawBody);
        if ($cleanHtml === '') {
            $_SESSION['_vf_pm_error'] = 'Treść wiadomości nie może być pusta.';
            View::redirect('/messages?to=' . (int) $recipient['id']);
        }

        $convId = $this->messages->createConversation(
            $senderId,
            (string) $user['username'],
            (int) $recipient['id'],
            $subject,
            $cleanHtml
        );

        View::redirect('/messages?c=' . $convId);
    }

    public function reply(array $params): void
    {
        $user = $this->auth->currentMember();
        $wantsJson = $this->wantsJsonResponse();

        if (!$user) {
            if ($wantsJson) {
                $this->jsonResponse(['ok' => false, 'error' => 'Wymagane logowanie.'], 401);
            }
            View::redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            if ($wantsJson) {
                $this->jsonResponse(['ok' => false, 'error' => 'Nieprawidłowy token CSRF.'], 403);
            }
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }
        if ((int) ($user['is_banned'] ?? 0) === 1) {
            if ($wantsJson) {
                $this->jsonResponse(['ok' => false, 'error' => 'Konto jest zablokowane.'], 403);
            }
            http_response_code(403);
            exit('Zablokowane konto nie może odpowiadać w konwersacjach.');
        }

        $convId = (int) ($params['id'] ?? 0);
        $memberId = (int) $user['id'];
        $conv = $this->messages->findConversationForMember($convId, $memberId);
        if (!$conv) {
            if ($wantsJson) {
                $this->jsonResponse(['ok' => false, 'error' => 'Konwersacja nie istnieje lub brak dostępu.'], 404);
            }
            http_response_code(404);
            exit('Konwersacja nie została znaleziona.');
        }

        $rawBody = trim((string) ($_POST['content'] ?? ''));
        $cleanHtml = HtmlSanitizer::sanitizeSubmittedContent($rawBody);
        if ($cleanHtml === '') {
            if ($wantsJson) {
                $this->jsonResponse(['ok' => false, 'error' => 'Wiadomość nie może być pusta.'], 422);
            }
            View::redirect('/messages?c=' . $convId);
        }

        $msgId = $this->messages->addReply($convId, $memberId, (string) $user['username'], $cleanHtml);

        if ($wantsJson) {
            $newMsgs = $this->messages->messagesInConversation($convId, max(0, $msgId - 1), 10);
            $created = null;
            foreach ($newMsgs as $m) {
                if ((int) $m['id'] === $msgId) {
                    $created = $this->serializeMessage($m, $memberId);
                    break;
                }
            }
            $this->jsonResponse([
                'ok'                    => true,
                'message'               => $created,
                'unread_messages_count' => $this->messages->unreadCountForMember($memberId),
            ]);
        }

        View::redirect('/messages?c=' . $convId);
    }

    public function leave(array $params): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }

        $convId = (int) ($params['id'] ?? 0);
        if ($convId > 0) {
            $this->messages->leaveConversation($convId, (int) $user['id']);
        }
        View::redirect('/messages');
    }

    public function apiLive(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $user = $this->auth->currentMember();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $memberId = (int) $user['id'];
        $convId = (int) ($_GET['c'] ?? 0);
        $afterId = max(0, (int) ($_GET['after_id'] ?? 0));

        $newMessages = [];
        $activeConvId = 0;
        if ($convId > 0) {
            $conv = $this->messages->findConversationForMember($convId, $memberId);
            if ($conv !== null) {
                $activeConvId = $convId;
                $this->messages->markConversationRead($convId, $memberId);
                $rows = $this->messages->messagesInConversation($convId, $afterId, 50);
                foreach ($rows as $m) {
                    $newMessages[] = $this->serializeMessage($m, $memberId);
                }
            }
        }

        $conversations = $this->messages->listConversationsForMember($memberId, 40);
        $convPayload = [];
        foreach ($conversations as $c) {
            $convPayload[] = [
                'id'               => (int) $c['id'],
                'subject'          => (string) $c['subject'],
                'last_snippet'     => (string) $c['last_snippet'],
                'last_message_at'  => (int) $c['last_message_at'],
                'relative_time'    => View::formatRelativeTime((int) $c['last_message_at']),
                'is_unread'        => (bool) $c['is_unread'] && (int) $c['id'] !== $activeConvId,
                'peer_id'          => $c['peer_id'],
                'peer_username'    => (string) $c['peer_username'],
                'peer_color'       => $c['peer_color'],
                'peer_prefix'      => (string) $c['peer_prefix'],
                'peer_suffix'      => (string) $c['peer_suffix'],
                'peer_group'       => $c['peer_group'],
                'peer_online'      => View::isOnline($c['peer_last_activity_at'] !== null ? (int) $c['peer_last_activity_at'] : null),
            ];
        }

        echo json_encode([
            'ok'                    => true,
            'unread_messages_count' => $this->messages->unreadCountForMember($memberId),
            'active_conversation_id'=> $activeConvId,
            'new_messages'          => $newMessages,
            'conversations'         => $convPayload,
        ], JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, mixed> */
    private function serializeMessage(array $m, int $viewerId): array
    {
        $senderId = (int) ($m['sender_id'] ?? 0);
        return [
            'id'            => (int) $m['id'],
            'sender_id'     => $senderId,
            'sender_name'   => (string) ($m['sender_name'] ?? 'Użytkownik'),
            'sender_slug'   => (string) ($m['sender_slug'] ?? 'user'),
            'sender_avatar' => $m['sender_avatar'] ?: null,
            'sender_color'  => $m['sender_color'] ?: null,
            'sender_prefix' => (string) ($m['sender_prefix'] ?? ''),
            'sender_suffix' => (string) ($m['sender_suffix'] ?? ''),
            'sender_group'  => $m['sender_group'] ?: null,
            'is_mine'       => $senderId === $viewerId,
            'content_html'  => (string) ($m['content_html'] ?? ''),
            'created_at'    => (int) ($m['created_at'] ?? time()),
            'relative_time' => View::formatRelativeTime((int) ($m['created_at'] ?? time())),
        ];
    }

    private function wantsJsonResponse(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return str_contains($accept, 'application/json') || $xhr === 'fetch' || $xhr === 'xmlhttprequest';
    }

    private function jsonResponse(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
