<?php

declare(strict_types=1);

namespace VeloForum\Controller;

use VeloForum\Core\Csrf;
use VeloForum\Core\View;
use VeloForum\Repository\NotificationRepository;
use VeloForum\Service\AuthService;
use VeloForum\Service\SeoService;

final class NotificationController
{
    public function __construct(
        private readonly View $view,
        private readonly NotificationRepository $notifications,
        private readonly AuthService $auth,
        private readonly SeoService $seo
    ) {
    }

    public function index(): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }

        $items = $this->notifications->recentForMember((int) $user['id'], 40);
        $unread = $this->notifications->unreadCount((int) $user['id']);

        $this->view->render('notifications', [
            'pageTitle'     => 'Powiadomienia',
            'items'         => $items,
            'currentUser'   => $user,
            'unreadCount'   => $unread,
            'seo'           => $this->seo->build([
                'title'  => 'Powiadomienia — VeloForum',
                'path'   => '/notifications',
                'robots' => 'noindex, nofollow',
            ]),
        ]);
    }

    public function apiList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $user = $this->auth->currentMember();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['items' => [], 'unread' => 0]);
            return;
        }

        $items = $this->notifications->recentForMember((int) $user['id'], 12);
        $unread = $this->notifications->unreadCount((int) $user['id']);
        echo json_encode(['items' => $items, 'unread' => $unread], JSON_UNESCAPED_UNICODE);
    }

    public function markAllRead(): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }

        $this->notifications->markAllRead((int) $user['id']);
        View::redirect('/notifications');
    }
}
