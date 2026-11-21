<?php

declare(strict_types=1);

namespace VeloForum\Controller;

use VeloForum\Core\Csrf;
use VeloForum\Core\View;
use VeloForum\Repository\AdminRepository;
use VeloForum\Repository\ForumRepository;
use VeloForum\Repository\ModRepository;
use VeloForum\Repository\NotificationRepository;
use VeloForum\Repository\TopicRepository;
use VeloForum\Service\AuthService;
use VeloForum\Service\PermissionService;
use VeloForum\Service\SeoService;

final class ModController
{
    public function __construct(
        private readonly View $view,
        private readonly ModRepository $mods,
        private readonly AdminRepository $admin,
        private readonly ForumRepository $forums,
        private readonly TopicRepository $topics,
        private readonly NotificationRepository $notifications,
        private readonly AuthService $auth,
        private readonly SeoService $seo,
        private readonly PermissionService $permissions
    ) {
    }

    public function index(): void
    {
        $user = $this->requireModerator();
        $tab = (string) ($_GET['tab'] ?? 'reports');
        $allowedTabs = ['reports', 'queue', 'topics', 'warnings', 'logs'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'reports';
        }

        $isGlobalStaff = $this->permissions->isGlobalStaff($user);
        $allowedForumIds = $isGlobalStaff ? null : $this->permissions->moderatedForumIds($user);
        $allForums = $this->forums->allOrdered();
        $supervisedForums = [];
        if ($allowedForumIds !== null) {
            $set = array_flip($allowedForumIds);
            foreach ($allForums as $f) {
                if (isset($set[(int) $f['id']])) {
                    $supervisedForums[] = $f;
                }
            }
        }

        $this->view->render('mod', [
            'pageTitle'        => 'Panel Moderatora (ModCP)',
            'tab'              => $tab,
            'isGlobalStaff'    => $isGlobalStaff,
            'supervisedForums' => $supervisedForums,
            'reports'          => $this->mods->listReports(null, 60, $allowedForumIds),
            'hiddenPosts'      => $this->mods->hiddenPosts(50, $allowedForumIds),
            'recentTopics'     => $this->mods->recentTopicsForMod(40, $allowedForumIds),
            'forums'           => $allForums,
            'members'          => $this->admin->listMembers('', 80),
            'modLogs'          => $this->mods->recentLogs(60),
            'currentUser'      => $user,
            'canAccessModCp'   => true,
            'unreadCount'      => $this->notifications->unreadCount((int) $user['id']),
            'flash'            => (string) ($_GET['saved'] ?? ''),
            'seo'              => $this->seo->build([
                'title'  => 'Panel Moderatora — VeloForum',
                'path'   => '/mod',
                'robots' => 'noindex, nofollow',
            ]),
        ]);
    }

    public function reportPost(array $params): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }
        $this->verifyCsrf();

        $postId = (int) ($params['id'] ?? 0);
        $post = $this->topics->findPostById($postId);
        if (!$post) {
            http_response_code(404);
            exit('Post nie istnieje.');
        }

        $category = (string) ($_POST['category'] ?? 'other');
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason !== '') {
            $this->mods->createReport(
                $postId,
                (int) $post['topic_id'],
                (int) $user['id'],
                (string) $user['username'],
                $category,
                $reason
            );
        }

        View::redirect("/topic/{$post['topic_id']}?reported=1#post-{$postId}");
    }

    public function handleReport(): void
    {
        $user = $this->requireModerator();
        $this->verifyCsrf();

        $reportId = (int) ($_POST['report_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? 'resolve');
        $note = trim((string) ($_POST['note'] ?? ''));
        $report = $this->mods->findReportById($reportId);

        if ($report) {
            $topic = $this->topics->findById((int) $report['topic_id']);
            if ($topic && !$this->permissions->forForum($user, (int) $topic['forum_id'])['can_moderate']) {
                http_response_code(403);
                exit('Brak uprawnień do moderowania tego działu.');
            }

            $status = $decision === 'dismiss' ? 'dismissed' : 'resolved';
            $this->mods->resolveReport(
                $reportId,
                $status,
                (int) $user['id'],
                (string) $user['username'],
                $note
            );

            if ($decision === 'resolve_hide') {
                $this->topics->setPostApproval((int) $report['post_id'], false);
                $this->mods->logAction(
                    (int) $user['id'],
                    (string) $user['username'],
                    'hide_post',
                    'post',
                    (int) $report['post_id'],
                    "Ukryto post #{$report['post_id']} w wyniku zgłoszenia #{$reportId}"
                );
            } elseif ($decision === 'resolve_warn') {
                $reportedPost = $this->topics->findPostById((int) $report['post_id']);
                $postAuthorId = $reportedPost ? (int) ($reportedPost['author_id'] ?? 0) : 0;
                if ($postAuthorId > 0 && $postAuthorId !== (int) $user['id']) {
                    $warnReason = $note !== '' ? $note : 'Ostrzeżenie za zgłoszony post #' . (int) $report['post_id'];
                    $this->mods->issueWarning($postAuthorId, 1, false, $warnReason, null);
                    $this->notifications->create(
                        $postAuthorId,
                        (int) $user['id'],
                        (string) $user['username'],
                        'system',
                        (int) $report['topic_id'],
                        (int) $report['post_id'],
                        "Otrzymałeś +1 pkt ostrzeżeń w związku ze zgłoszeniem: {$warnReason}"
                    );
                    $this->mods->logAction(
                        (int) $user['id'],
                        (string) $user['username'],
                        'warn_from_report',
                        'member',
                        $postAuthorId,
                        "Ostrzeżenie +1 pkt ze zgłoszenia #{$reportId}: {$warnReason}"
                    );
                }
            }

            $this->mods->logAction(
                (int) $user['id'],
                (string) $user['username'],
                $status === 'dismissed' ? 'dismiss_report' : 'resolve_report',
                'report',
                $reportId,
                $note !== '' ? $note : "Zamknięto zgłoszenie #{$reportId} ({$status})"
            );
        }

        View::redirect('/mod?tab=reports&saved=report');
    }

    public function togglePostApproval(): void
    {
        $user = $this->requireModerator();
        $this->verifyCsrf();

        $postId = (int) ($_POST['post_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? 'approve');
        $post = $this->topics->findPostById($postId);

        if ($post) {
            $topic = $this->topics->findById((int) $post['topic_id']);
            if ($topic && !$this->permissions->forForum($user, (int) $topic['forum_id'])['can_moderate']) {
                http_response_code(403);
                exit('Brak uprawnień do moderowania tego działu.');
            }

            if ($action === 'delete') {
                $this->topics->deletePost($postId);
                $this->mods->logAction(
                    (int) $user['id'],
                    (string) $user['username'],
                    'delete_post',
                    'post',
                    $postId,
                    "Usunięto post #{$postId} z tematu #{$post['topic_id']}"
                );
            } else {
                $approve = $action === 'approve';
                $this->topics->setPostApproval($postId, $approve);
                $this->mods->logAction(
                    (int) $user['id'],
                    (string) $user['username'],
                    $approve ? 'approve_post' : 'hide_post',
                    'post',
                    $postId,
                    ($approve ? 'Przywrócono' : 'Ukryto') . " post #{$postId}"
                );
            }
        }

        $redirect = (string) ($_POST['redirect'] ?? '/mod?tab=queue&saved=post');
        View::redirect($redirect !== '' ? $redirect : '/mod?tab=queue&saved=post');
    }

    public function moveTopic(): void
    {
        $user = $this->requireModerator();
        $this->verifyCsrf();

        $topicId = (int) ($_POST['topic_id'] ?? 0);
        $targetForumId = (int) ($_POST['target_forum_id'] ?? 0);
        $topic = $topicId > 0 ? $this->topics->findById($topicId) : null;
        if ($topic && $targetForumId > 0) {
            if (!$this->permissions->forForum($user, (int) $topic['forum_id'])['can_moderate']) {
                http_response_code(403);
                exit('Brak uprawnień do przenoszenia tematów z tego działu.');
            }
            $this->topics->moveTopic($topicId, $targetForumId);
            $this->mods->logAction(
                (int) $user['id'],
                (string) $user['username'],
                'move_topic',
                'topic',
                $topicId,
                "Przeniesiono temat #{$topicId} do działu #{$targetForumId}"
            );
        }

        View::redirect('/mod?tab=topics&saved=moved');
    }

    public function warnMember(): void
    {
        $user = $this->requireModerator();
        $this->verifyCsrf();

        $memberId = (int) ($_POST['member_id'] ?? 0);
        $points = max(0, min(10, (int) ($_POST['points'] ?? 1)));
        $reason = trim((string) ($_POST['reason'] ?? 'Naruszenie regulaminu społeczności.'));
        $applyBan = !empty($_POST['apply_ban']);
        $banHours = (int) ($_POST['ban_hours'] ?? 0);
        $bannedUntil = ($applyBan && $banHours > 0) ? time() + ($banHours * 3600) : null;

        if ($memberId > 0 && $memberId !== (int) $user['id']) {
            $this->mods->issueWarning($memberId, $points, $applyBan, $reason, $bannedUntil);
            $this->notifications->create(
                $memberId,
                (int) $user['id'],
                (string) $user['username'],
                'system',
                null,
                null,
                "Otrzymałeś ostrzeżenie (+{$points} pkt) od moderatora: {$reason}"
            );
            $this->mods->logAction(
                (int) $user['id'],
                (string) $user['username'],
                $applyBan ? 'warn_and_ban' : 'warn_member',
                'member',
                $memberId,
                "Nadano +{$points} pkt ostrzeżeń" . ($applyBan ? ' oraz blokadę konta' : '') . ": {$reason}"
            );
        }

        View::redirect('/mod?tab=warnings&saved=warn');
    }

    public function clearSanctions(): void
    {
        $user = $this->requireModerator();
        $this->verifyCsrf();

        $memberId = (int) ($_POST['member_id'] ?? 0);
        if ($memberId > 0) {
            $this->mods->clearSanctions($memberId);
            $this->notifications->create(
                $memberId,
                (int) $user['id'],
                (string) $user['username'],
                'system',
                null,
                null,
                'Moderator wyzerował Twoje punkty ostrzeżeń i zdjął ewentualną blokadę konta.'
            );
            $this->mods->logAction(
                (int) $user['id'],
                (string) $user['username'],
                'clear_sanctions',
                'member',
                $memberId,
                "Wyzerowano ostrzeżenia oraz zdjęto blokadę konta użytkownika #{$memberId}"
            );
        }

        View::redirect('/mod?tab=warnings&saved=cleared');
    }

    private function requireModerator(): array
    {
        $user = $this->auth->currentMember();
        if (!$user || !$this->permissions->canAccessModCp($user)) {
            http_response_code(403);
            exit('Dostęp wymaga uprawnień Moderatora, Opiekuna kategorii lub Administratora.');
        }
        return $user;
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }
    }
}
