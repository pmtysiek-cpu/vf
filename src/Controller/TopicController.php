<?php

declare(strict_types=1);

namespace VeloForum\Controller;

use VeloForum\Core\Csrf;
use VeloForum\Core\View;
use VeloForum\Repository\AchievementRepository;
use VeloForum\Repository\AdminRepository;
use VeloForum\Repository\ForumRepository;
use VeloForum\Repository\ModRepository;
use VeloForum\Repository\NotificationRepository;
use VeloForum\Repository\PresenceRepository;
use VeloForum\Repository\ReactionRepository;
use VeloForum\Repository\TopicRepository;
use VeloForum\Service\AuthService;
use VeloForum\Service\ForumTreeService;
use VeloForum\Service\HtmlSanitizer;
use VeloForum\Service\MentionService;
use VeloForum\Service\PermissionService;
use VeloForum\Service\SeoService;
use VeloForum\Service\UploadService;

final class TopicController
{
    public function __construct(
        private readonly View $view,
        private readonly ForumRepository $forums,
        private readonly TopicRepository $topics,
        private readonly ReactionRepository $reactions,
        private readonly NotificationRepository $notifications,
        private readonly ForumTreeService $tree,
        private readonly AuthService $auth,
        private readonly SeoService $seo,
        private readonly MentionService $mentions,
        private readonly PermissionService $permissions,
        private readonly AdminRepository $admin,
        private readonly ?ModRepository $mod = null,
        private readonly ?PresenceRepository $presence = null,
        private readonly ?UploadService $uploads = null,
        private readonly string $publicRoot = '',
        private readonly ?AchievementRepository $achievements = null
    ) {
    }

    public function show(array $params): void
    {
        $topicId = (int) ($params['id'] ?? 0);
        $topic = $this->topics->findById($topicId);
        if (!$topic) {
            http_response_code(404);
            echo 'Temat nie został znaleziony.';
            return;
        }

        $user = $this->auth->currentMember();
        $forumId = (int) $topic['forum_id'];
        $forumPerms = $this->permissions->forForum($user, $forumId);
        if (!$forumPerms['can_view'] || !$forumPerms['can_read']) {
            http_response_code(403);
            echo 'Brak dostępu do tego wątku.';
            return;
        }

        if ((int) $topic['is_approved'] === 0 && !$forumPerms['can_moderate']) {
            http_response_code(403);
            echo 'Ten temat oczekuje na akceptację moderatora.';
            return;
        }

        $this->topics->incrementViews($topicId);
        if (!isset($_SESSION['_vf_topic_reads']) || !is_array($_SESSION['_vf_topic_reads'])) {
            $_SESSION['_vf_topic_reads'] = [];
        }
        $_SESSION['_vf_topic_reads'][$topicId] = time();
        if ($this->presence !== null) {
            $this->presence->touch($this->presence->resolveSessionKey(), $user, 'topic', $topicId, $forumId);
        }
        $perPage = 20;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $totalPosts = $this->topics->countPostsInTopic($topicId);
        $totalPages = max(1, (int) ceil($totalPosts / $perPage));
        $posts = $this->topics->postsInTopic($topicId, $page, $perPage);
        $breadcrumbs = $this->tree->buildBreadcrumbs($this->forums->allOrdered(), $forumId);

        $postIds = array_map(static fn (array $p): int => (int) $p['id'], $posts);
        $authorIds = array_values(array_unique(array_filter(
            array_map(static fn (array $p): int => (int) ($p['author_id'] ?? 0), $posts),
            static fn (int $id): bool => $id > 0
        )));
        $solvedByAuthor = $this->topics->solvedCountsForAuthors($authorIds);
        $topicParticipants = $this->topics->participantsInTopic($topicId, 10);

        $localModAuthorIds = [];
        $checkedAuthors = [];
        foreach ($posts as $pRow) {
            $aid = (int) ($pRow['author_id'] ?? 0);
            if ($aid <= 0 || isset($checkedAuthors[$aid])) {
                continue;
            }
            $checkedAuthors[$aid] = true;
            $pseudoMember = [
                'id'                  => $aid,
                'primary_group_id'    => (int) ($pRow['member_primary_group_id'] ?? 3),
                'secondary_group_ids' => $pRow['member_secondary_group_ids'] ?? '[]',
                'is_admin'            => (int) ($pRow['member_is_admin'] ?? 0),
                'is_moderator'        => (int) ($pRow['member_is_moderator'] ?? 0),
                'is_banned'           => 0,
            ];
            if ($this->permissions->forForum($pseudoMember, $forumId)['can_moderate']) {
                $localModAuthorIds[$aid] = true;
            }
        }

        $reactionDefs = $this->reactions->allDefinitions();
        $postReactions = $this->reactions->forPosts($postIds, $user ? (int) $user['id'] : null);
        $postAttachments = $this->topics->attachmentsForPosts($postIds);
        $achievementsByAuthor = $this->achievements !== null ? $this->achievements->unlockedForMembers($authorIds) : [];
        $topicPresence = $this->presence !== null
            ? $this->presence->activeSnapshot($topicId, 1200, 16)
            : ['topic_members' => [], 'topic_guests_count' => 0];
        $poll = $this->topics->pollForTopic($topicId, $user ? (int) $user['id'] : null);
        $tags = $this->topics->tagsForTopic($topicId);
        $isBookmarked = $user ? $this->topics->isBookmarked($topicId, (int) $user['id']) : false;

        $firstText = isset($posts[0]) ? strip_tags((string) $posts[0]['content_html']) : $topic['title'];
        $canonicalPath = "/topic/{$topic['id']}-{$topic['slug']}" . ($page > 1 ? "?page={$page}" : '');

        $this->view->render('topic', [
            'pageTitle'            => $topic['title'],
            'topic'                => $topic,
            'posts'                => $posts,
            'postAttachments'      => $postAttachments,
            'achievementsByAuthor' => $achievementsByAuthor,
            'topicPresence'        => $topicPresence,
            'solvedByAuthor'       => $solvedByAuthor,
            'topicParticipants'    => $topicParticipants,
            'localModAuthorIds'    => $localModAuthorIds,
            'forumPerms'        => $forumPerms,
            'categoryModGroups' => $this->permissions->categoryModeratorGroups($forumId),
            'isFollowing'       => $user ? $this->topics->isFollowing($topicId, (int) $user['id']) : false,
            'followCount'       => $this->topics->followCount($topicId),
            'breadcrumbs'       => $breadcrumbs,
            'page'              => $page,
            'totalPages'        => $totalPages,
            'reactionDefs'      => $reactionDefs,
            'postReactions'     => $postReactions,
            'poll'              => $poll,
            'tags'              => $tags,
            'prefixes'          => $this->admin->listPrefixes(),
            'profileFields'     => $this->admin->listProfileFields(),
            'isBookmarked'      => $isBookmarked,
            'queuedNotice'      => ($_GET['queued'] ?? '') === '1',
            'currentUser'       => $user,
            'canAccessModCp'    => $this->permissions->canAccessModCp($user),
            'unreadCount'       => $user ? $this->notifications->unreadCount((int) $user['id']) : 0,
            'seo'               => $this->seo->build([
                'title'       => $topic['title'] . ' — VeloForum',
                'description' => $firstText,
                'path'        => $canonicalPath,
                'og_type'     => 'article',
                'json_ld'     => [
                    $this->seo->breadcrumbJsonLd($breadcrumbs),
                    $this->seo->topicJsonLd($topic, $posts),
                ],
            ]),
        ]);
    }

    public function create(array $params): void
    {
        $user = $this->auth->currentMember();
        if (!$user || (int) ($user['can_post'] ?? 0) !== 1) {
            View::redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }

        $forumId = (int) ($params['id'] ?? 0);
        $forum = $this->forums->findById($forumId);
        $forumPerms = $this->permissions->forForum($user, $forumId);
        if (
            !$forum
            || (int) $forum['is_category'] === 1
            || !$forumPerms['can_view']
            || !$forumPerms['can_read']
            || !$forumPerms['can_create_topic']
            || ((int) ($forum['is_locked'] ?? 0) === 1 && !$forumPerms['can_moderate'])
        ) {
            http_response_code(403);
            exit('Nie masz uprawnień do zakładania tematów w tym dziale.');
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['content'] ?? ''));

        if ($title === '' || mb_strlen($title) > 250 || $body === '') {
            View::redirect("/forum/{$forumId}");
        }

        $sanitized = HtmlSanitizer::sanitizeSubmittedContent($body);
        $mentionResult = $this->mentions->process($sanitized);
        $isApproved = ((int) ($forum['require_approval'] ?? 0) === 0) || $forumPerms['can_moderate'];
        $prefixId = (int) ($_POST['prefix_id'] ?? 0);

        $topicId = $this->topics->createTopic(
            $forumId,
            (int) $user['id'],
            (string) $user['username'],
            $title,
            $mentionResult['html'],
            (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            $isApproved,
            $prefixId > 0 ? $prefixId : null
        );

        $tagsRaw = trim((string) ($_POST['tags'] ?? ''));
        if ($tagsRaw !== '') {
            $tagList = preg_split('/[,;]+/', $tagsRaw) ?: [];
            $this->topics->saveTags($topicId, $tagList);
        }

        if ((int) ($forum['allow_polls'] ?? 1) === 1 && $forumPerms['can_create_polls']) {
            $pollQuestion = trim((string) ($_POST['poll_question'] ?? ''));
            $pollOptionsRaw = trim((string) ($_POST['poll_options'] ?? ''));
            if ($pollQuestion !== '' && $pollOptionsRaw !== '') {
                $opts = preg_split('/\R+/', $pollOptionsRaw) ?: [];
                $this->topics->createPollForTopic($topicId, $pollQuestion, $opts);
            }
        }

        $topic = $this->topics->findById($topicId);
        $firstPostId = (int) ($topic['first_post_id'] ?? 0);
        if (
            $firstPostId > 0
            && $this->uploads !== null
            && $this->publicRoot !== ''
            && isset($_FILES['attachments'])
            && is_array($_FILES['attachments'])
        ) {
            $savedFiles = $this->uploads->uploadPostAttachments($_FILES['attachments'], (int) $user['id'], $this->publicRoot);
            if ($savedFiles !== []) {
                $this->topics->saveAttachments($firstPostId, $topicId, (int) $user['id'], $savedFiles);
            }
        }
        $notifiedIds = [(int) $user['id'] => true];

        if ($firstPostId > 0 && $mentionResult['mentionedMembers'] !== []) {
            $ids = array_map(static fn (array $m): int => (int) $m['id'], $mentionResult['mentionedMembers']);
            $this->topics->saveMentions($firstPostId, $ids);
            if ($isApproved) {
                foreach ($mentionResult['mentionedMembers'] as $mentioned) {
                    $mid = (int) $mentioned['id'];
                    if (isset($notifiedIds[$mid])) {
                        continue;
                    }
                    $notifiedIds[$mid] = true;
                    $this->notifications->create(
                        $mid,
                        (int) $user['id'],
                        (string) $user['username'],
                        'mention',
                        $topicId,
                        $firstPostId,
                        "{$user['username']} wspomniał(a) o Tobie w temacie „{$title}”."
                    );
                }
            }
        }

        if ($isApproved) {
            foreach ($this->forums->followerIds($forumId) as $followerId) {
                if (isset($notifiedIds[$followerId])) {
                    continue;
                }
                $notifiedIds[$followerId] = true;
                $this->notifications->create(
                    $followerId,
                    (int) $user['id'],
                    (string) $user['username'],
                    'system',
                    $topicId,
                    $firstPostId ?: null,
                    "{$user['username']} utworzył(a) nowy temat „{$title}” w obserwowanym dziale."
                );
            }
        }

        if (!$isApproved) {
            View::redirect("/forum/{$forumId}?queued=1");
        }

        View::redirect("/topic/{$topicId}");
    }

    public function reply(array $params): void
    {
        $user = $this->auth->currentMember();
        if (!$user || (int) ($user['can_post'] ?? 0) !== 1) {
            View::redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }

        $topicId = (int) ($params['id'] ?? 0);
        $topic = $this->topics->findById($topicId);
        if (!$topic) {
            http_response_code(404);
            exit('Temat nie istnieje.');
        }

        $forumId = (int) $topic['forum_id'];
        $forumPerms = $this->permissions->forForum($user, $forumId);
        $isLocked = (int) $topic['is_locked'] === 1 || (int) ($topic['forum_is_locked'] ?? 0) === 1;
        if (!$forumPerms['can_view'] || !$forumPerms['can_read'] || !$forumPerms['can_reply'] || ($isLocked && !$forumPerms['can_moderate'])) {
            http_response_code(403);
            exit('Brak uprawnień do odpowiedzi lub temat/dział jest zamknięty.');
        }

        $body = trim((string) ($_POST['content'] ?? ''));
        if ($body === '') {
            View::redirect("/topic/{$topicId}");
        }

        $sanitized = HtmlSanitizer::sanitizeSubmittedContent($body);
        $mentionResult = $this->mentions->process($sanitized);
        $isApproved = ((int) ($topic['forum_require_approval'] ?? 0) === 0) || $forumPerms['can_moderate'];

        $postId = $this->topics->addReply(
            $topicId,
            $forumId,
            (int) $user['id'],
            (string) $user['username'],
            $mentionResult['html'],
            (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            $isApproved
        );

        if (
            $postId > 0
            && $this->uploads !== null
            && $this->publicRoot !== ''
            && isset($_FILES['attachments'])
            && is_array($_FILES['attachments'])
        ) {
            $savedFiles = $this->uploads->uploadPostAttachments($_FILES['attachments'], (int) $user['id'], $this->publicRoot);
            if ($savedFiles !== []) {
                $this->topics->saveAttachments($postId, $topicId, (int) $user['id'], $savedFiles);
            }
        }

        $notifiedIds = [(int) $user['id'] => true];
        if ($isApproved && !empty($topic['author_id'])) {
            $authorId = (int) $topic['author_id'];
            if (!isset($notifiedIds[$authorId])) {
                $this->notifications->create(
                    $authorId,
                    (int) $user['id'],
                    (string) $user['username'],
                    'reply',
                    $topicId,
                    $postId,
                    "{$user['username']} odpowiedział(a) w Twoim temacie „{$topic['title']}”."
                );
                $notifiedIds[$authorId] = true;
            }
        }

        if ($mentionResult['mentionedMembers'] !== []) {
            $ids = array_map(static fn (array $m): int => (int) $m['id'], $mentionResult['mentionedMembers']);
            $this->topics->saveMentions($postId, $ids);
            if ($isApproved) {
                foreach ($mentionResult['mentionedMembers'] as $mentioned) {
                    $mid = (int) $mentioned['id'];
                    if (isset($notifiedIds[$mid])) {
                        continue;
                    }
                    $notifiedIds[$mid] = true;
                    $this->notifications->create(
                        $mid,
                        (int) $user['id'],
                        (string) $user['username'],
                        'mention',
                        $topicId,
                        $postId,
                        "{$user['username']} wspomniał(a) o Tobie w odpowiedzi w temacie „{$topic['title']}”."
                    );
                }
            }
        }

        if ($isApproved) {
            foreach ($this->topics->followerIds($topicId) as $followerId) {
                if (isset($notifiedIds[$followerId])) {
                    continue;
                }
                $notifiedIds[$followerId] = true;
                $this->notifications->create(
                    $followerId,
                    (int) $user['id'],
                    (string) $user['username'],
                    'reply',
                    $topicId,
                    $postId,
                    "Nowa odpowiedź użytkownika {$user['username']} w obserwowanym temacie „{$topic['title']}”."
                );
            }
        }

        if (!$isApproved) {
            View::redirect("/topic/{$topicId}?queued=1");
        }

        View::redirect("/topic/{$topicId}#post-{$postId}");
    }

    public function react(array $params): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }

        $postId = (int) ($params['id'] ?? 0);
        $reactionId = (int) ($_POST['reaction_id'] ?? 0);
        $post = $this->topics->findPostById($postId);
        if (!$post) {
            http_response_code(404);
            exit('Post nie istnieje.');
        }

        $outcome = $this->reactions->toggle($postId, (int) $user['id'], $reactionId);
        if ($outcome['action'] !== 'removed' && $outcome['post_author_id'] !== null) {
            $this->notifications->create(
                (int) $outcome['post_author_id'],
                (int) $user['id'],
                (string) $user['username'],
                'reaction',
                (int) $post['topic_id'],
                $postId,
                "{$user['username']} zareagował(a) „{$outcome['reaction_label']}” na Twój post."
            );
        }

        $acceptsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
        if ($acceptsJson) {
            $fresh = $this->reactions->forPosts([$postId], (int) $user['id']);
            $postData = $fresh[$postId] ?? ['counts' => [], 'user_reaction' => null];
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok'            => true,
                'action'        => $outcome['action'],
                'user_reaction' => $postData['user_reaction'],
                'counts'        => $postData['counts'],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        View::redirect("/topic/{$post['topic_id']}#post-{$postId}");
    }

    public function editPost(array $params): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }

        $postId = (int) ($params['id'] ?? 0);
        $post = $this->topics->findPostById($postId);
        if (!$post) {
            http_response_code(404);
            exit('Post nie istnieje.');
        }

        $topic = $this->topics->findById((int) $post['topic_id']);
        $forumPerms = $topic ? $this->permissions->forForum($user, (int) $topic['forum_id']) : ['can_moderate' => false, 'can_edit_own' => false];
        $isOwner = (int) $post['author_id'] === (int) $user['id'] && !empty($forumPerms['can_edit_own']);
        $isMod = !empty($forumPerms['can_moderate']);
        if (!$isOwner && !$isMod) {
            http_response_code(403);
            exit('Brak uprawnień do edycji tego posta.');
        }

        $body = trim((string) ($_POST['content'] ?? ''));
        if ($body !== '') {
            $sanitized = HtmlSanitizer::sanitizeSubmittedContent($body);
            $mentionResult = $this->mentions->process($sanitized);
            $this->topics->updatePost($postId, $mentionResult['html'], (string) $user['username']);
            $ids = array_map(static fn (array $m): int => (int) $m['id'], $mentionResult['mentionedMembers']);
            $this->topics->saveMentions($postId, $ids);
        }

        if (
            $this->uploads !== null
            && $this->publicRoot !== ''
            && isset($_FILES['attachments'])
            && is_array($_FILES['attachments'])
        ) {
            $savedFiles = $this->uploads->uploadPostAttachments($_FILES['attachments'], (int) $user['id'], $this->publicRoot);
            if ($savedFiles !== []) {
                $this->topics->saveAttachments($postId, (int) $post['topic_id'], (int) $user['id'], $savedFiles);
            }
        }

        View::redirect("/topic/{$post['topic_id']}#post-{$postId}");
    }

    public function editTopic(array $params): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }

        $topicId = (int) ($params['id'] ?? 0);
        $topic = $this->topics->findById($topicId);
        if (!$topic) {
            http_response_code(404);
            exit('Temat nie istnieje.');
        }

        $forumPerms = $this->permissions->forForum($user, (int) $topic['forum_id']);
        $isOwner = (int) ($topic['author_id'] ?? 0) === (int) $user['id'] && !empty($forumPerms['can_edit_own']);
        $isMod = !empty($forumPerms['can_moderate']);
        if (!$isOwner && !$isMod) {
            http_response_code(403);
            exit('Brak uprawnień do edycji tego tematu.');
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $prefixId = (int) ($_POST['prefix_id'] ?? 0);
        if ($title !== '' && mb_strlen($title) <= 250) {
            $this->topics->updateTopicMeta($topicId, $title, $prefixId > 0 ? $prefixId : null);
        }

        if (array_key_exists('tags', $_POST)) {
            $tagsRaw = trim((string) $_POST['tags']);
            $tagList = $tagsRaw !== '' ? (preg_split('/[,;]+/', $tagsRaw) ?: []) : [];
            $this->topics->saveTags($topicId, $tagList);
        }

        View::redirect("/topic/{$topicId}");
    }

    public function deletePost(array $params): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }

        $postId = (int) ($params['id'] ?? 0);
        $post = $this->topics->findPostById($postId);
        if (!$post) {
            http_response_code(404);
            exit('Post nie istnieje.');
        }

        $topic = $this->topics->findById((int) $post['topic_id']);
        $forumPerms = $topic ? $this->permissions->forForum($user, (int) $topic['forum_id']) : ['can_moderate' => false];
        if (empty($forumPerms['can_moderate']) || (int) ($post['is_first_post'] ?? 0) === 1) {
            http_response_code(403);
            exit('Brak uprawnień do usunięcia tego posta.');
        }

        if ($this->uploads !== null && $this->publicRoot !== '') {
            $atts = $this->topics->attachmentsForPosts([$postId]);
            foreach ($atts[$postId] ?? [] as $att) {
                $this->uploads->removeLocalFile($this->publicRoot, (string) ($att['stored_path'] ?? ''));
            }
        }
        $this->topics->deletePost($postId);
        View::redirect("/topic/{$post['topic_id']}");
    }

    public function saveModNote(array $params): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }

        $postId = (int) ($params['id'] ?? 0);
        $post = $this->topics->findPostById($postId);
        if (!$post) {
            http_response_code(404);
            exit('Post nie istnieje.');
        }

        $topic = $this->topics->findById((int) $post['topic_id']);
        $forumPerms = $topic ? $this->permissions->forForum($user, (int) $topic['forum_id']) : ['can_moderate' => false];
        if (empty($forumPerms['can_moderate'])) {
            http_response_code(403);
            exit('Brak uprawnień moderatorskich dla tego działu.');
        }

        $rawNote = trim((string) ($_POST['mod_note'] ?? ''));
        $clear = !empty($_POST['clear_note']);
        $finalNote = $clear ? '' : $rawNote;

        $this->topics->updatePostModNote(
            $postId,
            $finalNote !== '' ? $finalNote : null,
            (int) $user['id'],
            (string) $user['username']
        );

        if ($this->mod !== null) {
            $detail = $finalNote === ''
                ? "Usunięto notatkę moderatora przy poście #{$postId}."
                : 'Dodano/zaktualizowano notatkę moderatora: ' . mb_substr($finalNote, 0, 140);
            $this->mod->logAction(
                (int) $user['id'],
                (string) $user['username'],
                $finalNote === '' ? 'mod_note_clear' : 'mod_note_set',
                'post',
                $postId,
                $detail
            );
        }

        View::redirect("/topic/{$post['topic_id']}#post-{$postId}");
    }

    public function togglePin(array $params): void
    {
        $topicId = (int) ($params['id'] ?? 0);
        $this->requireForumModeratorCsrf($topicId);
        $this->topics->togglePin($topicId);
        $redirect = trim((string) ($_POST['redirect'] ?? ''));
        if ($redirect !== '' && str_starts_with($redirect, '/') && !str_starts_with($redirect, '//')) {
            View::redirect($redirect);
        }
        View::redirect("/topic/{$topicId}");
    }

    public function toggleFeature(array $params): void
    {
        $topicId = (int) ($params['id'] ?? 0);
        $this->requireForumModeratorCsrf($topicId);
        $this->topics->toggleFeatured($topicId);
        $redirect = trim((string) ($_POST['redirect'] ?? ''));
        if ($redirect !== '' && str_starts_with($redirect, '/') && !str_starts_with($redirect, '//')) {
            View::redirect($redirect);
        }
        View::redirect("/topic/{$topicId}");
    }

    public function toggleLock(array $params): void
    {
        $topicId = (int) ($params['id'] ?? 0);
        $this->requireForumModeratorCsrf($topicId);
        $this->topics->toggleLock($topicId);
        $redirect = trim((string) ($_POST['redirect'] ?? ''));
        if ($redirect !== '' && str_starts_with($redirect, '/') && !str_starts_with($redirect, '//')) {
            View::redirect($redirect);
        }
        View::redirect("/topic/{$topicId}");
    }

    public function votePoll(array $params): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }
        $topicId = (int) ($params['id'] ?? 0);
        $optionId = (int) ($_POST['option_id'] ?? 0);
        if ($topicId > 0 && $optionId > 0) {
            $this->topics->votePoll($topicId, $optionId, (int) $user['id']);
        }
        View::redirect("/topic/{$topicId}");
    }

    public function toggleBestAnswer(array $params): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }
        $topicId = (int) ($params['id'] ?? 0);
        $postId = (int) ($_POST['post_id'] ?? 0);
        $topic = $this->topics->findById($topicId);
        if ($topic && (int) ($topic['forum_allow_best_answer'] ?? 1) === 1) {
            $forumPerms = $this->permissions->forForum($user, (int) $topic['forum_id']);
            $isOwner = (int) $topic['author_id'] === (int) $user['id'];
            if ($isOwner || $forumPerms['can_moderate']) {
                $next = ((int) ($topic['best_answer_post_id'] ?? 0) === $postId) ? null : $postId;
                $this->topics->setBestAnswer($topicId, $next);
            }
        }
        View::redirect("/topic/{$topicId}#post-{$postId}");
    }

    public function toggleBookmark(array $params): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }
        $topicId = (int) ($params['id'] ?? 0);
        if ($topicId > 0) {
            $this->topics->toggleBookmark($topicId, (int) $user['id']);
        }
        View::redirect("/topic/{$topicId}");
    }

    public function toggleFollow(array $params): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }
        $topicId = (int) ($params['id'] ?? 0);
        if ($topicId > 0) {
            $this->topics->toggleFollow($topicId, (int) $user['id']);
        }
        View::redirect("/topic/{$topicId}");
    }

    public function reactors(array $params): void
    {
        $postId = (int) ($params['id'] ?? 0);
        $items = $postId > 0 ? $this->reactions->reactorsForPost($postId) : [];
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
    }

    public function downloadAttachment(array $params): void
    {
        $attId = (int) ($params['id'] ?? 0);
        $att = $attId > 0 ? $this->topics->findAttachmentById($attId) : null;
        if (!$att) {
            http_response_code(404);
            exit('Załącznik nie został znaleziony.');
        }

        $user = $this->auth->currentMember();
        $forumPerms = $this->permissions->forForum($user, (int) $att['forum_id']);
        if (!$forumPerms['can_view'] || !$forumPerms['can_read']) {
            http_response_code(403);
            exit('Brak uprawnień do pobrania tego załącznika.');
        }

        $relPath = str_replace(['..', '\\'], '', (string) $att['stored_path']);
        $uploadsRoot = realpath(rtrim($this->publicRoot, '/\\') . '/uploads');
        $fullPath = realpath(rtrim($this->publicRoot, '/\\') . $relPath);
        if ($uploadsRoot === false || $fullPath === false || !str_starts_with($fullPath, $uploadsRoot) || !is_file($fullPath)) {
            http_response_code(404);
            exit('Plik załącznika nie istnieje na serwerze.');
        }

        $this->topics->incrementAttachmentDownload($attId);
        $mime = (string) ($att['mime_type'] ?: 'application/octet-stream');
        $origName = str_replace(['"', "\r", "\n"], '_', (string) ($att['original_name'] ?: 'zalacznik'));
        $isImage = (int) ($att['is_image'] ?? 0) === 1;

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($fullPath));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($isImage ? 'inline' : 'attachment') . '; filename="' . $origName . '"');
        readfile($fullPath);
        exit;
    }

    public function deleteAttachment(array $params): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }

        $attId = (int) ($params['id'] ?? 0);
        $att = $attId > 0 ? $this->topics->findAttachmentById($attId) : null;
        if (!$att) {
            http_response_code(404);
            exit('Załącznik nie istnieje.');
        }

        $forumPerms = $this->permissions->forForum($user, (int) $att['forum_id']);
        $post = $this->topics->findPostById((int) $att['post_id']);
        $isOwner = $post && (int) ($post['author_id'] ?? 0) === (int) $user['id'] && !empty($forumPerms['can_edit_own']);
        $isMod = !empty($forumPerms['can_moderate']);
        if (!$isOwner && !$isMod) {
            http_response_code(403);
            exit('Brak uprawnień do usunięcia załącznika.');
        }

        if ($this->uploads !== null && $this->publicRoot !== '') {
            $this->uploads->removeLocalFile($this->publicRoot, (string) $att['stored_path']);
        }
        $this->topics->deleteAttachment($attId);
        View::redirect('/topic/' . (int) $att['topic_id'] . '#post-' . (int) $att['post_id']);
    }

    private function requireForumModeratorCsrf(int $topicId): void
    {
        $user = $this->auth->currentMember();
        $topic = $this->topics->findById($topicId);
        if (!$user || !$topic || !$this->permissions->forForum($user, (int) $topic['forum_id'])['can_moderate']) {
            http_response_code(403);
            exit('Brak uprawnień moderatorskich dla tego działu.');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }
    }
}
