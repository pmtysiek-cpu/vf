<?php

declare(strict_types=1);

namespace VeloForum\Controller;

use VeloForum\Core\Csrf;
use VeloForum\Core\View;
use VeloForum\Repository\AchievementRepository;
use VeloForum\Repository\AdminRepository;
use VeloForum\Repository\ChatRepository;
use VeloForum\Repository\ForumRepository;
use VeloForum\Repository\MemberRepository;
use VeloForum\Repository\MessageRepository;
use VeloForum\Repository\NotificationRepository;
use VeloForum\Repository\PresenceRepository;
use VeloForum\Repository\TopicRepository;
use VeloForum\Repository\ReactionRepository;
use VeloForum\Service\AuthService;
use VeloForum\Service\ForumTreeService;
use VeloForum\Service\PermissionService;
use VeloForum\Service\SeoService;
use VeloForum\Service\UploadService;

final class ForumController
{
    public function __construct(
        private readonly View $view,
        private readonly ForumRepository $forums,
        private readonly TopicRepository $topics,
        private readonly MemberRepository $members,
        private readonly NotificationRepository $notifications,
        private readonly ForumTreeService $tree,
        private readonly AuthService $auth,
        private readonly SeoService $seo,
        private readonly ReactionRepository $reactions,
        private readonly PermissionService $permissions,
        private readonly AdminRepository $admin,
        private readonly ?PresenceRepository $presence = null,
        private readonly ?UploadService $uploads = null,
        private readonly string $publicRoot = '',
        private readonly ?MessageRepository $messages = null,
        private readonly ?AchievementRepository $achievements = null,
        private readonly ?ChatRepository $chat = null
    ) {
    }

    public function index(): void
    {
        $user = $this->auth->currentMember();
        if ($this->presence !== null) {
            $this->presence->touch($this->presence->resolveSessionKey(), $user, 'home', null, null);
        }
        $flatAll = $this->forums->allOrdered();
        $flat = array_values(array_filter(
            $flatAll,
            fn (array $f): bool => $this->permissions->forForum($user, (int) $f['id'])['can_view']
        ));
        $forumModGroups = [];
        foreach ($flat as $f) {
            $fid = (int) $f['id'];
            $mods = $this->permissions->categoryModeratorGroups($fid);
            if ($mods !== []) {
                $forumModGroups[$fid] = $mods;
            }
        }

        $categories = $this->tree->buildTree($flat);
        $latestTopics = $this->topics->latestGlobal(8);
        $featuredTopics = $this->topics->featuredGlobal(6);
        $stats = $this->members->stats();
        $solvedTopicsCount = $this->topics->countSolvedTopics();
        $reactionPulse = $this->reactions->recentCommunityPulse(6);
        $siteSettings = $this->admin->settings();
        $layout = (string) ($_GET['layout'] ?? ($siteSettings['forum_layout'] ?? 'list'));
        if (!in_array($layout, ['list', 'grid', 'compact'], true)) {
            $layout = 'list';
        }

        $readAllAt = (int) ($_SESSION['_vf_read_all_at'] ?? 0);
        $topicReads = isset($_SESSION['_vf_topic_reads']) && is_array($_SESSION['_vf_topic_reads']) ? $_SESSION['_vf_topic_reads'] : [];
        $unreadForumsMap = [];
        foreach ($flat as $fItem) {
            $fid = (int) $fItem['id'];
            $lastPostAt = (int) ($fItem['last_post_at'] ?? 0);
            $lastTopicId = (int) ($fItem['last_topic_id'] ?? 0);
            if ($lastPostAt > 0 && $lastPostAt > $readAllAt) {
                $readAt = $lastTopicId > 0 ? (int) ($topicReads[$lastTopicId] ?? 0) : 0;
                $unreadForumsMap[$fid] = $readAt < $lastPostAt;
            } else {
                $unreadForumsMap[$fid] = false;
            }
        }

        $presenceSnapshot = $this->presence !== null
            ? $this->presence->activeSnapshot(null, 1200, 24)
            : [
                'online_members'       => $this->members->onlineMembers(24, 1200),
                'online_members_count' => $this->members->countOnline(1200),
                'online_guests_count'  => 0,
            ];

        $viewerId = $user ? (int) $user['id'] : null;
        $chatEnabled = ($siteSettings['chat_enabled'] ?? '1') === '1';
        $chatAllowGuests = ($siteSettings['chat_allow_guests'] ?? '1') === '1';
        $chatMessages = ($this->chat !== null && $chatEnabled && ($user !== null || $chatAllowGuests))
            ? $this->chat->recentMessages(45, 0, $viewerId)
            : [];
        $chatPinned = ($this->chat !== null && $chatEnabled && ($user !== null || $chatAllowGuests))
            ? $this->chat->pinnedMessage($viewerId)
            : null;

        $this->view->render('home', [
            'pageTitle'         => 'Strona główna społeczności',
            'categories'        => $categories,
            'forumModGroups'    => $forumModGroups,
            'featuredTopics'    => $featuredTopics,
            'latestTopics'      => $latestTopics,
            'reactionPulse'     => $reactionPulse,
            'totalReactions'    => $this->reactions->countAllReactions(),
            'topReputation'     => $this->members->leaderboard('reputation', 'all', 5),
            'popularTags'       => $this->topics->popularTags(10),
            'onlineMembers'     => $presenceSnapshot['online_members'],
            'onlineCount'       => (int) $presenceSnapshot['online_members_count'],
            'onlineGuestsCount' => (int) $presenceSnapshot['online_guests_count'],
            'unreadForumsMap'   => $unreadForumsMap,
            'solvedTopicsCount' => $solvedTopicsCount,
            'layout'            => $layout,
            'stats'             => $stats,
            'forumGroups'       => $this->admin->listGroups(),
            'chatMessages'      => $chatMessages,
            'chatPinned'        => $chatPinned,
            'canUseChat'        => $this->permissions->canUseChat($user),
            'canModerateChat'   => $this->permissions->canModerateChat($user),
            'isChatMuted'       => $this->permissions->isChatMuted($user),
            'currentUser'       => $user,
            'canAccessModCp'    => $this->permissions->canAccessModCp($user),
            'unreadCount'       => $user ? $this->notifications->unreadCount((int) $user['id']) : 0,
            'seo'               => $this->seo->build([
                'title'       => ($siteSettings['site_name'] ?? 'VeloForum') . ' — Nowoczesne forum dyskusjne społeczności',
                'description' => $siteSettings['site_description'] ?? 'Dołącz do dyskusji w działach tematycznych, przeglądaj wątki i wymieniaj się wiedzą ze społecznością.',
                'path'        => '/',
                'json_ld'     => [$this->seo->homeJsonLd()],
            ]),
        ]);
    }

    public function show(array $params): void
    {
        $forumId = (int) ($params['id'] ?? 0);
        $forum = $this->forums->findById($forumId);
        if (!$forum) {
            http_response_code(404);
            echo 'Dział nie został znaleziony.';
            return;
        }

        $user = $this->auth->currentMember();
        $forumPerms = $this->permissions->forForum($user, $forumId);
        if (!$forumPerms['can_view'] || !$forumPerms['can_read']) {
            http_response_code(403);
            echo 'Twoja grupa nie posiada uprawnień do przeglądania tego działu.';
            return;
        }

        if (!empty($forum['redirect_url'])) {
            View::redirect((string) $forum['redirect_url']);
        }
        if ($this->presence !== null) {
            $this->presence->touch($this->presence->resolveSessionKey(), $user, 'forum', null, $forumId);
        }

        $flat = $this->forums->allOrdered();
        $breadcrumbs = $this->tree->buildBreadcrumbs($flat, $forumId);
        $visibleFlat = array_values(array_filter(
            $flat,
            fn (array $s): bool => $this->permissions->forForum($user, (int) $s['id'])['can_view']
        ));
        $subforums = $this->tree->childrenOfNode($visibleFlat, $forumId);
        $perPage = 25;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $sort = (string) ($_GET['sort'] ?? 'activity');
        if (!in_array($sort, ['activity', 'newest', 'replies', 'views'], true)) {
            $sort = 'activity';
        }
        $filter = (string) ($_GET['filter'] ?? 'all');
        if (!in_array($filter, ['all', 'solved', 'polls'], true)) {
            $filter = 'all';
        }
        $prefixFilter = max(0, (int) ($_GET['prefix'] ?? 0));
        $prefixIdArg = $prefixFilter > 0 ? $prefixFilter : null;
        $totalTopics = $this->topics->countByForum($forumId, $filter, $prefixIdArg);
        $totalPages = max(1, (int) ceil($totalTopics / $perPage));
        $topics = $this->topics->listByForum($forumId, $page, $perPage, $sort, $filter, $prefixIdArg);
        $topicIds = array_map(static fn (array $t): int => (int) $t['id'], $topics);
        $topicTags = $this->topics->tagsForTopics($topicIds);
        $prefixes = $this->admin->listPrefixes();

        $readAllAt = (int) ($_SESSION['_vf_read_all_at'] ?? 0);
        $topicReads = isset($_SESSION['_vf_topic_reads']) && is_array($_SESSION['_vf_topic_reads']) ? $_SESSION['_vf_topic_reads'] : [];
        $unreadTopicsMap = [];
        foreach ($topics as $tpRow) {
            $tid = (int) $tpRow['id'];
            $lpAt = (int) ($tpRow['last_post_at'] ?? $tpRow['created_at'] ?? 0);
            $readAt = (int) ($topicReads[$tid] ?? 0);
            $unreadTopicsMap[$tid] = $lpAt > $readAllAt && $readAt < $lpAt;
        }
        $unreadForumsMap = [];
        foreach ($subforums as $sf) {
            $sfid = (int) $sf['id'];
            $sfLp = (int) ($sf['last_post_at'] ?? 0);
            $sfTid = (int) ($sf['last_topic_id'] ?? 0);
            $sfReadAt = $sfTid > 0 ? (int) ($topicReads[$sfTid] ?? 0) : 0;
            $unreadForumsMap[$sfid] = $sfLp > 0 && $sfLp > $readAllAt && $sfReadAt < $sfLp;
        }

        $canonicalPath = "/forum/{$forum['id']}-{$forum['slug']}" . ($page > 1 ? "?page={$page}" : '');

        $this->view->render('forum', [
            'pageTitle'         => $forum['seo_title'] ?: $forum['name'],
            'forum'             => $forum,
            'forumPerms'        => $forumPerms,
            'categoryModGroups' => $this->permissions->categoryModeratorGroups($forumId),
            'isFollowing'       => $user ? $this->forums->isFollowing($forumId, (int) $user['id']) : false,
            'followCount'       => $this->forums->followCount($forumId),
            'breadcrumbs'       => $breadcrumbs,
            'subforums'         => $subforums,
            'unreadForumsMap'   => $unreadForumsMap,
            'topics'            => $topics,
            'unreadTopicsMap'   => $unreadTopicsMap,
            'topicTags'         => $topicTags,
            'prefixes'          => $prefixes,
            'prefixFilter'      => $prefixFilter,
            'sort'              => $sort,
            'filter'            => $filter,
            'page'              => $page,
            'totalPages'        => $totalPages,
            'queuedNotice'      => ($_GET['queued'] ?? '') === '1',
            'currentUser'       => $user,
            'canAccessModCp'    => $this->permissions->canAccessModCp($user),
            'unreadCount'       => $user ? $this->notifications->unreadCount((int) $user['id']) : 0,
            'seo'               => $this->seo->build([
                'title'       => ($forum['seo_title'] ?: $forum['name']) . ' — VeloForum',
                'description' => $forum['seo_description'] ?: ($forum['description'] ?: "Dyskusje w dziale {$forum['name']}."),
                'path'        => $canonicalPath,
                'json_ld'     => [$this->seo->breadcrumbJsonLd($breadcrumbs)],
            ]),
        ]);
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
        $forumId = (int) ($params['id'] ?? 0);
        if ($forumId > 0 && $this->permissions->forForum($user, $forumId)['can_view']) {
            $this->forums->toggleFollow($forumId, (int) $user['id']);
        }
        View::redirect("/forum/{$forumId}");
    }

    public function byTag(array $params): void
    {
        $slug = trim((string) ($params['slug'] ?? ''));
        $results = $slug !== '' ? $this->topics->listByTag($slug, 40) : [];
        $tagLabel = $results[0]['tag_label'] ?? $slug;
        $user = $this->auth->currentMember();

        $this->view->render('search', [
            'pageTitle'      => 'Tag: #' . $tagLabel,
            'query'          => '#' . $tagLabel,
            'results'        => $results,
            'currentUser'    => $user,
            'canAccessModCp' => $this->permissions->canAccessModCp($user),
            'unreadCount'    => $user ? $this->notifications->unreadCount((int) $user['id']) : 0,
            'seo'            => $this->seo->build([
                'title' => "Tematy z tagiem #{$tagLabel} — VeloForum",
                'path'  => '/tag/' . urlencode($slug),
            ]),
        ]);
    }

    public function bookmarks(): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }
        $results = $this->topics->bookmarkedByMember((int) $user['id'], 50);

        $this->view->render('search', [
            'pageTitle'      => 'Zapisane wątki',
            'query'          => 'Zapisane wątki',
            'results'        => $results,
            'currentUser'    => $user,
            'canAccessModCp' => $this->permissions->canAccessModCp($user),
            'unreadCount'    => $this->notifications->unreadCount((int) $user['id']),
            'seo'            => $this->seo->build([
                'title'  => 'Zapisane wątki — VeloForum',
                'path'   => '/bookmarks',
                'robots' => 'noindex, nofollow',
            ]),
        ]);
    }

    public function search(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $results = mb_strlen($query) >= 2 ? $this->topics->search($query, 30) : [];
        $user = $this->auth->currentMember();

        $this->view->render('search', [
            'pageTitle'      => 'Wyszukiwarka',
            'query'          => $query,
            'results'        => $results,
            'currentUser'    => $user,
            'canAccessModCp' => $this->permissions->canAccessModCp($user),
            'unreadCount'    => $user ? $this->notifications->unreadCount((int) $user['id']) : 0,
            'seo'            => $this->seo->build([
                'title'  => $query !== '' ? "Wyniki: {$query} — VeloForum" : 'Wyszukiwarka — VeloForum',
                'path'   => '/search',
                'robots' => 'noindex, follow',
            ]),
        ]);
    }

    public function activityFeed(): void
    {
        $tab = (string) ($_GET['tab'] ?? 'activity');
        if (!in_array($tab, ['activity', 'latest', 'solved', 'polls'], true)) {
            $tab = 'activity';
        }
        $latestTopics = $this->topics->latestTopicsFiltered($tab, 30);
        $topicIds = array_map(static fn (array $t): int => (int) $t['id'], $latestTopics);
        $topicTags = $this->topics->tagsForTopics($topicIds);
        $readAllAt = (int) ($_SESSION['_vf_read_all_at'] ?? 0);
        $topicReads = isset($_SESSION['_vf_topic_reads']) && is_array($_SESSION['_vf_topic_reads']) ? $_SESSION['_vf_topic_reads'] : [];
        $unreadTopicsMap = [];
        foreach ($latestTopics as $tpRow) {
            $tid = (int) $tpRow['id'];
            $lpAt = (int) ($tpRow['last_post_at'] ?? $tpRow['created_at'] ?? 0);
            $readAt = (int) ($topicReads[$tid] ?? 0);
            $unreadTopicsMap[$tid] = $lpAt > $readAllAt && $readAt < $lpAt;
        }
        $user = $this->auth->currentMember();
        if ($this->presence !== null) {
            $this->presence->touch($this->presence->resolveSessionKey(), $user, 'activity', null, null);
        }

        $tabTitles = [
            'activity' => 'Co nowego?',
            'latest'   => 'Najnowsze tematy',
            'solved'   => 'Rozwiązane wątki',
            'polls'    => 'Tematy z ankietą',
        ];

        $this->view->render('activity', [
            'pageTitle'       => $tabTitles[$tab] ?? 'Co nowego?',
            'tab'             => $tab,
            'latestTopics'    => $latestTopics,
            'topicTags'       => $topicTags,
            'unreadTopicsMap' => $unreadTopicsMap,
            'currentUser'     => $user,
            'canAccessModCp'  => $this->permissions->canAccessModCp($user),
            'unreadCount'     => $user ? $this->notifications->unreadCount((int) $user['id']) : 0,
            'seo'             => $this->seo->build([
                'title'       => ($tabTitles[$tab] ?? 'Co nowego w społeczności') . ' — VeloForum',
                'description' => 'Bieżący strumień najnowszych tematów, rozwiązań i odpowiedzi na forum.',
                'path'        => '/activity',
            ]),
        ]);
    }

    public function markAllRead(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }
        $_SESSION['_vf_read_all_at'] = time();
        $user = $this->auth->currentMember();
        if ($user) {
            $this->notifications->markAllRead((int) $user['id']);
        }
        View::redirect('/?marked=1');
    }

    public function membersDirectory(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $sort = (string) ($_GET['sort'] ?? 'reputation');
        if (!in_array($sort, ['reputation', 'posts', 'newest'], true)) {
            $sort = 'reputation';
        }
        $list = $this->members->listAllMembers($query, $sort, 60);
        $memberIds = array_map(static fn (array $m): int => (int) $m['id'], $list);
        $topicsCountMap = $this->members->topicsCountMap($memberIds);
        $achievementsByMember = $this->achievements !== null ? $this->achievements->unlockedForMembers($memberIds) : [];
        $user = $this->auth->currentMember();
        if ($this->presence !== null) {
            $this->presence->touch($this->presence->resolveSessionKey(), $user, 'members', null, null);
        }

        $this->view->render('members', [
            'pageTitle'            => 'Użytkownicy społeczności',
            'members'              => $list,
            'topicsCountMap'       => $topicsCountMap,
            'achievementsByMember' => $achievementsByMember,
            'query'                => $query,
            'sort'                 => $sort,
            'currentUser'          => $user,
            'canAccessModCp'       => $this->permissions->canAccessModCp($user),
            'unreadCount'          => $user ? $this->notifications->unreadCount((int) $user['id']) : 0,
            'seo'                  => $this->seo->build([
                'title'       => 'Użytkownicy — VeloForum',
                'description' => 'Katalog członków społeczności oraz ranking reputacji.',
                'path'        => '/members',
            ]),
        ]);
    }

    public function memberProfile(array $params): void
    {
        $memberId = (int) ($params['id'] ?? 0);
        $member = $this->members->findProfileById($memberId);
        if (!$member) {
            http_response_code(404);
            echo 'Użytkownik nie został znaleziony.';
            return;
        }

        $tab = (string) ($_GET['tab'] ?? 'posts');
        if (!in_array($tab, ['posts', 'topics', 'reactions', 'about'], true)) {
            $tab = 'posts';
        }

        $user = $this->auth->currentMember();
        if ($this->presence !== null) {
            $this->presence->touch($this->presence->resolveSessionKey(), $user, 'member', null, null);
        }
        $profileError = (string) ($_SESSION['_vf_profile_error'] ?? '');
        unset($_SESSION['_vf_profile_error']);
        $isProfileGlobalStaff = $this->permissions->isGlobalStaff($member);
        $supervisedForums = [];
        if (!$isProfileGlobalStaff) {
            $modIds = array_flip($this->permissions->moderatedForumIds($member));
            foreach ($this->forums->allOrdered() as $f) {
                if (isset($modIds[(int) $f['id']])) {
                    $supervisedForums[] = $f;
                }
            }
        }

        $topicsCount = $this->members->countTopicsByMember($memberId);
        $solvedCount = $this->topics->countSolvedByMember($memberId);
        $achievementShowcase = $this->achievements !== null
            ? $this->achievements->profileShowcase($memberId, [
                'posts_count'       => (int) ($member['posts_count'] ?? 0),
                'topics_count'      => $topicsCount,
                'reputation_points' => (int) ($member['reputation_points'] ?? 0),
                'solved_count'      => $solvedCount,
            ])
            : [];

        $this->view->render('member', [
            'pageTitle'            => 'Profil: ' . $member['username'],
            'member'               => $member,
            'profileFields'        => $this->admin->listProfileFields(),
            'isProfileGlobalStaff' => $isProfileGlobalStaff,
            'supervisedForums'     => $supervisedForums,
            'canSetCover'          => $this->permissions->canSetProfileCover($user),
            'canSetMusic'          => $this->permissions->canSetProfileMusic($user),
            'youtubeVideoId'       => View::extractYoutubeId($member['music_url'] ?? null),
            'achievementShowcase'  => $achievementShowcase,
            'tab'                  => $tab,
            'topicsCount'          => $topicsCount,
            'solvedCount'          => $solvedCount,
            'recentTopics'         => $this->members->recentTopicsByMember($memberId, 12),
            'recentPosts'          => $this->members->recentPostsByMember($memberId, 12),
            'reactionStats'        => $this->members->reactionBreakdownForMember($memberId),
            'saved'                => ($_GET['saved'] ?? '') === '1',
            'profileError'         => $profileError,
            'currentUser'          => $user,
            'canAccessModCp'       => $this->permissions->canAccessModCp($user),
            'unreadCount'          => $user ? $this->notifications->unreadCount((int) $user['id']) : 0,
            'seo'                  => $this->seo->build([
                'title'       => $member['username'] . ' — Profil użytkownika — VeloForum',
                'description' => $member['bio'] ?: ('Profil członka społeczności ' . $member['username']),
                'path'        => "/member/{$member['id']}-{$member['slug']}",
            ]),
        ]);
    }

    public function updateProfile(): void
    {
        $user = $this->auth->currentMember();
        if (!$user) {
            View::redirect('/login');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo 'Nieprawidłowy token CSRF.';
            return;
        }

        $avatarUrl = trim((string) ($_POST['avatar_url'] ?? ''));
        if ($avatarUrl !== '' && !preg_match('#^(https?://|/)#i', $avatarUrl)) {
            $avatarUrl = '';
        }

        $removeAvatar = !empty($_POST['remove_avatar']);
        $oldAvatar = (string) ($user['avatar_url'] ?? '');

        if ($removeAvatar) {
            if ($this->uploads !== null && $this->publicRoot !== '') {
                $this->uploads->removeLocalFile($this->publicRoot, $oldAvatar);
            }
            $avatarUrl = '';
        } elseif (
            $this->uploads !== null
            && $this->publicRoot !== ''
            && isset($_FILES['avatar_file'])
            && is_array($_FILES['avatar_file'])
            && (int) ($_FILES['avatar_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ) {
            $uploadResult = $this->uploads->uploadAvatar($_FILES['avatar_file'], (int) $user['id'], $this->publicRoot);
            if ($uploadResult['ok'] && !empty($uploadResult['url'])) {
                if ($oldAvatar !== '' && $oldAvatar !== $uploadResult['url']) {
                    $this->uploads->removeLocalFile($this->publicRoot, $oldAvatar);
                }
                $avatarUrl = (string) $uploadResult['url'];
            } else {
                $_SESSION['_vf_profile_error'] = (string) ($uploadResult['error'] ?? 'Nie udało się wgrać avatara.');
                View::redirect('/member/' . (int) $user['id'] . '-' . urlencode((string) $user['slug']) . '?tab=about');
            }
        }

        $canSetCover = $this->permissions->canSetProfileCover($user);
        $coverUrl = (string) ($user['cover_url'] ?? '');
        if ($canSetCover) {
            $coverUrl = trim((string) ($_POST['cover_url'] ?? ''));
            if ($coverUrl !== '' && !preg_match('#^(https?://|/)#i', $coverUrl)) {
                $coverUrl = '';
            }
            $removeCover = !empty($_POST['remove_cover']);
            $oldCover = (string) ($user['cover_url'] ?? '');

            if ($removeCover) {
                if ($this->uploads !== null && $this->publicRoot !== '') {
                    $this->uploads->removeLocalFile($this->publicRoot, $oldCover);
                }
                $coverUrl = '';
            } elseif (
                $this->uploads !== null
                && $this->publicRoot !== ''
                && isset($_FILES['cover_file'])
                && is_array($_FILES['cover_file'])
                && (int) ($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            ) {
                $coverUpload = $this->uploads->uploadCover($_FILES['cover_file'], (int) $user['id'], $this->publicRoot);
                if ($coverUpload['ok'] && !empty($coverUpload['url'])) {
                    if ($oldCover !== '' && $oldCover !== $coverUpload['url']) {
                        $this->uploads->removeLocalFile($this->publicRoot, $oldCover);
                    }
                    $coverUrl = (string) $coverUpload['url'];
                } else {
                    $_SESSION['_vf_profile_error'] = (string) ($coverUpload['error'] ?? 'Nie udało się wgrać tła profilowego.');
                    View::redirect('/member/' . (int) $user['id'] . '-' . urlencode((string) $user['slug']) . '?tab=about');
                }
            }
        }

        $canSetMusic = $this->permissions->canSetProfileMusic($user);
        $musicUrl = (string) ($user['music_url'] ?? '');
        if ($canSetMusic) {
            $rawMusic = trim((string) ($_POST['music_url'] ?? ''));
            if ($rawMusic === '') {
                $musicUrl = '';
            } else {
                $ytId = View::extractYoutubeId($rawMusic);
                if ($ytId === null) {
                    $_SESSION['_vf_profile_error'] = 'Podaj prawidłowy link do filmu YouTube (np. https://youtu.be/... lub https://youtube.com/watch?v=...).';
                    View::redirect('/member/' . (int) $user['id'] . '-' . urlencode((string) $user['slug']) . '?tab=about');
                }
                $musicUrl = 'https://www.youtube.com/watch?v=' . $ytId;
            }
        }

        $rawCustom = $_POST['custom_fields'] ?? null;
        $customFields = is_array($rawCustom) ? array_map(static fn ($v): string => (string) $v, $rawCustom) : null;

        $this->members->updateOwnProfile(
            (int) $user['id'],
            (string) ($_POST['member_title'] ?? ''),
            (string) ($_POST['bio'] ?? ''),
            (string) ($_POST['signature'] ?? ''),
            $avatarUrl !== '' ? $avatarUrl : null,
            $customFields,
            $canSetCover ? ($coverUrl !== '' ? $coverUrl : null) : null,
            $canSetCover,
            $canSetMusic ? ($musicUrl !== '' ? $musicUrl : null) : null,
            $canSetMusic
        );

        View::redirect('/member/' . (int) $user['id'] . '-' . urlencode((string) $user['slug']) . '?tab=about&saved=1');
    }

    public function apiLeaderboards(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $metric = (string) ($_GET['metric'] ?? 'reputation');
        if (!in_array($metric, ['reputation', 'posts', 'topics'], true)) {
            $metric = 'reputation';
        }
        $period = (string) ($_GET['period'] ?? 'all');
        if (!in_array($period, ['week', 'month', 'year', 'all'], true)) {
            $period = 'all';
        }

        $rows = $this->members->leaderboard($metric, $period, 5);
        $unit = match ($metric) {
            'posts'  => 'postów',
            'topics' => 'tematów',
            default  => 'pkt',
        };

        $payloadRows = [];
        foreach ($rows as $r) {
            $payloadRows[] = [
                'id'             => (int) $r['id'],
                'username'       => (string) $r['username'],
                'slug'           => (string) $r['slug'],
                'avatar_url'     => View::safeMediaUrl($r['avatar_url'] ?? null),
                'score'          => (int) ($r['score'] ?? 0),
                'group_name'     => (string) ($r['group_name'] ?? ''),
                'group_color'    => View::safeGroupColor($r['group_color'] ?? null, '#475569'),
                'formatted_html' => View::formatUsername(
                    (string) $r['username'],
                    $r['group_color'] ?? null,
                    $r['group_prefix'] ?? null,
                    $r['group_suffix'] ?? null,
                    $r['group_name'] ?? null
                ),
                'profile_url'    => View::url('/member/' . (int) $r['id'] . '-' . urlencode((string) $r['slug'])),
            ];
        }

        echo json_encode([
            'ok'     => true,
            'metric' => $metric,
            'period' => $period,
            'unit'   => $unit,
            'rows'   => $payloadRows,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function apiMemberSearch(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $items = $this->members->searchByPrefix($query, 8);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
    }

    public function apiMemberCard(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: public, max-age=30');
        $memberId = (int) ($_GET['id'] ?? 0);
        if ($memberId <= 0) {
            http_response_code(400);
            echo '{"ok":false}';
            return;
        }
        $m = $this->members->hoverCardById($memberId);
        if (!$m) {
            http_response_code(404);
            echo '{"ok":false}';
            return;
        }
        $unlocked = $this->achievements !== null
            ? array_slice($this->achievements->unlockedForMembers([$memberId])[$memberId] ?? [], 0, 5)
            : [];
        $achPayload = [];
        foreach ($unlocked as $ach) {
            $achPayload[] = [
                'title' => (string) ($ach['title'] ?? ''),
                'color' => View::safeGroupColor($ach['color_hex'] ?? null, '#2563eb'),
                'svg'   => View::achievementIconSvg((string) ($ach['icon'] ?? 'trophy')),
            ];
        }

        $isOnline = View::isOnline(isset($m['last_activity_at']) ? (int) $m['last_activity_at'] : null);
        $slug = (string) ($m['slug'] ?? strtolower((string) $m['username']));
        echo json_encode([
            'ok'                => true,
            'id'                => (int) $m['id'],
            'username'          => (string) $m['username'],
            'avatar_url'        => View::safeMediaUrl($m['avatar_url'] ?? null),
            'cover_url'         => View::safeMediaUrl($m['cover_url'] ?? null),
            'member_title'      => (string) ($m['member_title'] ?? ''),
            'group_name'        => (string) ($m['group_name'] ?? ''),
            'group_color'       => View::safeGroupColor($m['group_color'] ?? null, '#475569'),
            'formatted_html'    => View::formatUsername(
                (string) $m['username'],
                $m['group_color'] ?? null,
                $m['group_prefix'] ?? null,
                $m['group_suffix'] ?? null,
                $m['group_name'] ?? null
            ),
            'is_online'         => $isOnline,
            'presence_text'     => View::presenceText(
                isset($m['last_activity_at']) ? (int) $m['last_activity_at'] : null,
                isset($m['joined_at']) ? (int) $m['joined_at'] : null
            ),
            'joined_text'       => !empty($m['joined_at']) ? date('d.m.Y', (int) $m['joined_at']) : '—',
            'posts_count'       => (int) ($m['posts_count'] ?? 0),
            'topics_count'      => (int) ($m['topics_count'] ?? 0),
            'reputation_points' => (int) ($m['reputation_points'] ?? 0),
            'profile_url'       => View::url('/member/' . (int) $m['id'] . '-' . urlencode($slug)),
            'pm_url'            => View::url('/messages?to=' . urlencode((string) $m['username'])),
            'achievements'      => $achPayload,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function apiQuickSearch(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'topics'  => $this->topics->quickSearch($query, 5),
            'forums'  => $this->forums->searchForums($query, 4),
            'members' => $this->members->searchByPrefix($query, 4),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function apiKlipy(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $settings = $this->admin->settings();
        $apiKey = trim((string) ($settings['klipy_api_key'] ?? ''));
        $query = trim((string) ($_GET['q'] ?? ''));
        $type = (string) ($_GET['type'] ?? 'gifs');
        if (!in_array($type, ['gifs', 'clips', 'stickers'], true)) {
            $type = 'gifs';
        }

        if ($apiKey === '') {
            echo json_encode([
                'configured' => false,
                'items'      => [],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $safeKey = rawurlencode($apiKey);
        $endpoint = $query !== ''
            ? "https://api.klipy.com/api/v1/{$safeKey}/{$type}/search?q=" . rawurlencode($query) . '&per_page=16&page=1'
            : "https://api.klipy.com/api/v1/{$safeKey}/{$type}/trending?per_page=16&page=1";

        $rawJson = '';
        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 5,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: VeloForum/2.7'],
                ]);
                $resp = curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if (is_string($resp) && $code >= 200 && $code < 300) {
                    $rawJson = $resp;
                }
            }
        } elseif ((bool) ini_get('allow_url_fopen')) {
            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'GET',
                    'timeout' => 5,
                    'header'  => "Accept: application/json\r\nUser-Agent: VeloForum/2.7\r\n",
                ],
            ]);
            $resp = @file_get_contents($endpoint, false, $ctx);
            if (is_string($resp)) {
                $rawJson = $resp;
            }
        }

        if ($rawJson === '') {
            echo json_encode([
                'configured' => true,
                'error'      => 'Nie udało się połączyć z API Klipy.com. Możesz wkleić link bezpośrednio.',
                'items'      => [],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $decoded = json_decode($rawJson, true);
        $rows = [];
        if (is_array($decoded)) {
            if (isset($decoded['data']['data']) && is_array($decoded['data']['data'])) {
                $rows = $decoded['data']['data'];
            } elseif (isset($decoded['data']) && is_array($decoded['data'])) {
                $rows = $decoded['data'];
            } elseif (isset($decoded['results']) && is_array($decoded['results'])) {
                $rows = $decoded['results'];
            }
        }

        $items = [];
        foreach (array_slice($rows, 0, 16) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $file = $row['file'] ?? [];
            $previewUrl = '';
            $mediaUrl = '';
            $mediaType = 'gif';

            if (is_array($file)) {
                $previewUrl = (string) ($file['sm']['webp']['url'] ?? $file['sm']['gif']['url'] ?? $file['md']['gif']['url'] ?? $file['hd']['gif']['url'] ?? $file['sm']['jpg']['url'] ?? '');
                if ($type === 'clips' && !empty($file['hd']['mp4']['url'])) {
                    $mediaUrl = (string) $file['hd']['mp4']['url'];
                    $mediaType = 'mp4';
                } elseif (!empty($file['md']['mp4']['url']) && empty($file['md']['gif']['url'])) {
                    $mediaUrl = (string) $file['md']['mp4']['url'];
                    $mediaType = 'mp4';
                } else {
                    $mediaUrl = (string) ($file['hd']['gif']['url'] ?? $file['md']['gif']['url'] ?? $file['hd']['webp']['url'] ?? $file['md']['webp']['url'] ?? $previewUrl);
                    $mediaType = str_ends_with(strtolower($mediaUrl), '.mp4') ? 'mp4' : 'gif';
                }
            }
            if ($previewUrl === '' && !empty($row['thumbnail_url'])) {
                $previewUrl = (string) $row['thumbnail_url'];
            }
            if ($mediaUrl === '' && !empty($row['url'])) {
                $mediaUrl = (string) $row['url'];
            }
            if ($mediaUrl === '' || !str_starts_with($mediaUrl, 'https://')) {
                continue;
            }
            if ($previewUrl === '') {
                $previewUrl = $mediaUrl;
            }

            $items[] = [
                'id'         => (string) ($row['id'] ?? $row['slug'] ?? count($items)),
                'title'      => mb_substr((string) ($row['title'] ?? 'Klipy'), 0, 80),
                'previewUrl' => $previewUrl,
                'mediaUrl'   => $mediaUrl,
                'mediaType'  => $mediaType,
            ];
        }

        echo json_encode([
            'configured' => true,
            'items'      => $items,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function apiPresencePing(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $user = $this->auth->currentMember();
        $pageType = trim((string) ($_POST['page_type'] ?? $_GET['page_type'] ?? 'home'));
        $topicId = (int) ($_POST['topic_id'] ?? $_GET['topic_id'] ?? 0);
        $forumId = (int) ($_POST['forum_id'] ?? $_GET['forum_id'] ?? 0);

        if ($this->presence !== null) {
            $this->presence->touch(
                $this->presence->resolveSessionKey(),
                $user,
                $pageType !== '' ? $pageType : 'home',
                $topicId > 0 ? $topicId : null,
                $forumId > 0 ? $forumId : null
            );
        }

        $this->outputLivePayload($user, $topicId > 0 ? $topicId : null);
    }

    public function apiPresenceLeave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        if ($this->presence !== null) {
            $this->presence->leave($this->presence->resolveSessionKey());
        }
        echo '{"ok":true}';
    }

    public function apiPresenceLive(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $user = $this->auth->currentMember();
        $topicId = (int) ($_GET['topic_id'] ?? 0);
        $this->outputLivePayload($user, $topicId > 0 ? $topicId : null);
    }

    private function outputLivePayload(?array $user, ?int $topicId): void
    {
        $snapshot = $this->presence !== null
            ? $this->presence->activeSnapshot($topicId, 1200, 24)
            : [
                'online_members'       => $this->members->onlineMembers(24, 1200),
                'online_members_count' => $this->members->countOnline(1200),
                'online_guests_count'  => 0,
                'topic_members'        => [],
                'topic_guests_count'   => 0,
            ];

        $topicState = null;
        if ($topicId !== null && $topicId > 0) {
            $t = $this->topics->findById($topicId);
            if ($t && $this->permissions->forForum($user, (int) $t['forum_id'])['can_read']) {
                $state = $this->topics->topicRealtimeState($topicId);
                if ($state) {
                    $topicState = [
                        'posts_count'      => (int) $state['posts_count'],
                        'last_post_id'     => (int) ($state['last_post_id'] ?? 0),
                        'last_post_at'     => (int) ($state['last_post_at'] ?? 0),
                        'last_poster_name' => (string) ($state['last_poster_name'] ?? ''),
                    ];
                }
            }
        }

        $latestRaw = $this->topics->latestGlobal(5);
        $latestActivity = [];
        foreach ($latestRaw as $lt) {
            $ltId = (int) $lt['id'];
            $ltSlug = (string) ($lt['slug'] ?? 'temat');
            $lpId = (int) ($lt['last_poster_id'] ?? 0);
            $lpName = (string) ($lt['last_poster_name'] ?? $lt['author_name'] ?? 'Gość');
            $lpSlug = (string) ($lt['last_poster_slug'] ?? strtolower($lpName));
            $latestActivity[] = [
                'id'                 => $ltId,
                'title'              => (string) $lt['title'],
                'url'                => View::url("/topic/{$ltId}-" . urlencode($ltSlug)),
                'forum_name'         => (string) ($lt['forum_name'] ?? ''),
                'forum_url'          => View::url('/forum/' . (int) $lt['forum_id'] . '-' . urlencode((string) ($lt['forum_slug'] ?? 'dzial'))),
                'last_poster_id'     => $lpId,
                'last_poster_name'   => $lpName,
                'last_poster_html'   => View::formatUsername(
                    $lpName,
                    $lt['last_poster_color'] ?? null,
                    $lt['last_poster_prefix'] ?? null,
                    $lt['last_poster_suffix'] ?? null,
                    $lt['last_poster_group'] ?? null
                ),
                'last_poster_url'    => $lpId > 0 ? View::url('/member/' . $lpId . '-' . urlencode($lpSlug)) : null,
                'last_poster_avatar' => View::safeMediaUrl($lt['last_poster_avatar'] ?? null),
                'relative_time'      => View::formatRelativeTime((int) ($lt['last_post_at'] ?? time())),
                'last_post_at'       => (int) ($lt['last_post_at'] ?? 0),
                'posts_count'        => (int) ($lt['posts_count'] ?? 1),
            ];
        }

        echo json_encode([
            'ok'                    => true,
            'server_time'           => time(),
            'unread_count'          => $user ? $this->notifications->unreadCount((int) $user['id']) : 0,
            'unread_messages_count' => ($user && $this->messages !== null) ? $this->messages->unreadCountForMember((int) $user['id']) : 0,
            'online_members'        => $snapshot['online_members'],
            'online_members_count'  => (int) $snapshot['online_members_count'],
            'online_guests_count'   => (int) $snapshot['online_guests_count'],
            'topic_members'         => $snapshot['topic_members'],
            'topic_guests_count'    => (int) $snapshot['topic_guests_count'],
            'topic_state'           => $topicState,
            'latest_activity'       => $latestActivity,
        ], JSON_UNESCAPED_UNICODE);
    }
}
