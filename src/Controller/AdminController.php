<?php

declare(strict_types=1);

namespace VeloForum\Controller;

use VeloForum\Core\Csrf;
use VeloForum\Core\View;
use VeloForum\Repository\AchievementRepository;
use VeloForum\Repository\AdminRepository;
use VeloForum\Repository\ChatRepository;
use VeloForum\Repository\ForumRepository;
use VeloForum\Repository\NotificationRepository;
use VeloForum\Repository\ReactionRepository;
use VeloForum\Service\AuthService;
use VeloForum\Service\SeoService;

final class AdminController
{
    public function __construct(
        private readonly View $view,
        private readonly AdminRepository $admin,
        private readonly ForumRepository $forums,
        private readonly ReactionRepository $reactions,
        private readonly NotificationRepository $notifications,
        private readonly AuthService $auth,
        private readonly SeoService $seo,
        private readonly string $publicRoot = '',
        private readonly ?AchievementRepository $achievements = null,
        private readonly ?ChatRepository $chat = null
    ) {
    }

    public function index(): void
    {
        $user = $this->requireAdmin();
        $tab = (string) ($_GET['tab'] ?? 'overview');
        $memberQuery = trim((string) ($_GET['q'] ?? ''));
        $memberFilter = (string) ($_GET['filter'] ?? 'all');
        if (!in_array($memberFilter, ['all', 'staff', 'warned', 'banned'], true)) {
            $memberFilter = 'all';
        }
        $editMemberId = (int) ($_GET['edit'] ?? 0);
        $editingMember = $editMemberId > 0 ? $this->admin->findMemberById($editMemberId) : null;
        $forums = $this->forums->allOrdered();
        $groups = $this->admin->listGroups();
        $editForumId = (int) ($_GET['edit_forum'] ?? 0);
        $editGroupId = (int) ($_GET['edit_group'] ?? 0);

        $editingForum = null;
        if ($editForumId > 0) {
            foreach ($forums as $f) {
                if ((int) $f['id'] === $editForumId) {
                    $editingForum = $f;
                    break;
                }
            }
        }

        $editingGroup = null;
        if ($editGroupId > 0) {
            foreach ($groups as $g) {
                if ((int) $g['id'] === $editGroupId) {
                    $editingGroup = $g;
                    break;
                }
            }
        }

        $this->view->render('admin', [
            'pageTitle'         => 'Panel Administracyjny (ACP)',
            'tab'               => $tab,
            'stats'             => $this->admin->dashboardStats(),
            'healthStats'       => $this->admin->systemHealthStats($this->publicRoot),
            'settings'          => $this->admin->settings(),
            'migrationLogs'     => $this->admin->migrationLogs(10),
            'forums'            => $forums,
            'editingForum'      => $editingForum,
            'members'           => $this->admin->listMembers($memberQuery, 60, $memberFilter),
            'memberCounts'      => $this->admin->memberStatusCounts(),
            'memberFilter'      => $memberFilter,
            'editingMember'     => $editingMember,
            'memberQuery'       => $memberQuery,
            'groups'            => $groups,
            'editingGroup'      => $editingGroup,
            'permissionsMatrix' => $this->admin->permissionsMatrix(),
            'reactionDefs'      => $this->reactions->allDefinitions(),
            'announcements'     => $this->admin->listAnnouncements(),
            'prefixes'          => $this->admin->listPrefixes(),
            'profileFields'     => $this->admin->listProfileFields(),
            'achievements'      => $this->achievements ? $this->achievements->listAll(false) : [],
            'recentAwards'      => $this->achievements ? $this->achievements->recentAwards(25) : [],
            'allMembersBrief'   => $this->admin->listMembers('', 120, 'all'),
            'chatStats'         => $this->chat ? $this->chat->statsSummary() : ['messages' => 0, 'muted' => 0],
            'chatMutedMembers'  => $this->chat ? $this->chat->mutedMembers() : [],
            'flash'             => $_SESSION['_vf_acp_flash'] ?? null,
            'currentUser'       => $user,
            'canAccessModCp'    => true,
            'unreadCount'       => $this->notifications->unreadCount((int) $user['id']),
            'seo'               => $this->seo->build([
                'title'  => 'Panel Administracyjny — VeloForum',
                'path'   => '/admin',
                'robots' => 'noindex, nofollow',
            ]),
        ]);
        unset($_SESSION['_vf_acp_flash']);
    }

    public function saveForum(): void
    {
        $this->requireAdminCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $parentId = (int) ($_POST['parent_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));

        if ($name !== '') {
            $savedId = $this->admin->saveForum(
                $id > 0 ? $id : null,
                $parentId > 0 ? $parentId : null,
                (int) ($_POST['is_category'] ?? 0) === 1,
                $name,
                trim((string) ($_POST['description'] ?? '')),
                trim((string) ($_POST['seo_title'] ?? '')),
                trim((string) ($_POST['seo_description'] ?? '')),
                (int) ($_POST['position'] ?? 1),
                trim((string) ($_POST['redirect_url'] ?? '')),
                (int) ($_POST['is_locked'] ?? 0) === 1,
                (int) ($_POST['allow_polls'] ?? 1) === 1,
                (int) ($_POST['allow_best_answer'] ?? 1) === 1,
                (int) ($_POST['require_approval'] ?? 0) === 1
            );
            $_SESSION['_vf_acp_flash'] = "Zapisano strukturę i opcje działu „{$name}” (#{$savedId}).";
        }

        View::redirect('/admin?tab=forums');
    }

    public function reorderForum(): void
    {
        $this->requireAdminCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $direction = (string) ($_POST['direction'] ?? 'up');
        if ($id > 0 && in_array($direction, ['up', 'down'], true)) {
            $this->admin->reorderForum($id, $direction);
            $_SESSION['_vf_acp_flash'] = 'Zaktualizowano kolejność działów w drzewie.';
        }
        View::redirect('/admin?tab=forums');
    }

    public function saveForumPermissions(): void
    {
        $this->requireAdminCsrf();
        $forumId = (int) ($_POST['forum_id'] ?? 0);
        $cascade = (int) ($_POST['cascade_children'] ?? 0) === 1;
        $rawPerms = (array) ($_POST['perms'] ?? []);

        if ($forumId > 0) {
            $normalized = [];
            foreach ($this->admin->listGroups() as $g) {
                $gid = (int) $g['id'];
                $row = (array) ($rawPerms[$gid] ?? []);
                $normalized[$gid] = [
                    'can_view'         => !empty($row['can_view']),
                    'can_read'         => !empty($row['can_read']),
                    'can_create_topic' => !empty($row['can_create_topic']),
                    'can_reply'        => !empty($row['can_reply']),
                    'can_moderate'     => !empty($row['can_moderate']),
                ];
            }
            $this->admin->saveForumPermissions($forumId, $normalized, $cascade);
            $_SESSION['_vf_acp_flash'] = 'Zapisano macierz uprawnień działu' . ($cascade ? ' i podkategorii.' : '.');
        }

        View::redirect('/admin?tab=forums&edit_forum=' . $forumId);
    }

    public function deleteForum(): void
    {
        $this->requireAdminCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->admin->deleteForum($id);
            $_SESSION['_vf_acp_flash'] = "Usunięto dział #{$id} wraz z podrzędnymi wątkami.";
        }
        View::redirect('/admin?tab=forums');
    }

    public function updateMember(): void
    {
        $this->requireAdminCsrf();
        $memberId = (int) ($_POST['member_id'] ?? 0);
        if ($memberId > 0) {
            $existing = $this->admin->findMemberById($memberId);
            if ($existing) {
                $username = trim((string) ($_POST['username'] ?? $existing['username']));
                $email = trim((string) ($_POST['email'] ?? $existing['email']));
                $groupId = max(1, (int) ($_POST['primary_group_id'] ?? $existing['primary_group_id']));
                $secondary = array_map('intval', (array) ($_POST['secondary_group_ids'] ?? []));
                $title = trim((string) ($_POST['member_title'] ?? ''));
                $avatarUrl = trim((string) ($_POST['avatar_url'] ?? ''));
                $coverUrl = trim((string) ($_POST['cover_url'] ?? ''));
                if ($coverUrl !== '' && !preg_match('#^(https?://|/)#i', $coverUrl)) {
                    $coverUrl = '';
                }
                $rawMusicUrl = trim((string) ($_POST['music_url'] ?? ''));
                $ytId = $rawMusicUrl !== '' ? View::extractYoutubeId($rawMusicUrl) : null;
                $musicUrl = $ytId !== null ? ('https://www.youtube.com/watch?v=' . $ytId) : '';
                $bio = trim((string) ($_POST['bio'] ?? ''));
                $signature = trim((string) ($_POST['signature'] ?? ''));
                $postsCount = (int) ($_POST['posts_count'] ?? $existing['posts_count']);
                $reputation = (int) ($_POST['reputation_points'] ?? $existing['reputation_points']);
                $warnings = (int) ($_POST['warning_points'] ?? ($existing['warning_points'] ?? 0));
                $isBanned = (int) ($_POST['is_banned'] ?? 0) === 1;
                $banReason = trim((string) ($_POST['ban_reason'] ?? ''));
                $bannedUntilRaw = trim((string) ($_POST['banned_until'] ?? ''));
                $bannedUntil = $bannedUntilRaw !== '' ? strtotime($bannedUntilRaw) ?: null : null;
                $newPassword = trim((string) ($_POST['new_password'] ?? ''));
                $rawCustom = $_POST['custom_fields'] ?? null;
                $customFields = is_array($rawCustom) ? array_map(static fn ($v): string => (string) $v, $rawCustom) : null;

                $this->admin->updateMemberAdvanced(
                    $memberId,
                    $username !== '' ? $username : (string) $existing['username'],
                    $email !== '' ? $email : (string) $existing['email'],
                    $groupId,
                    $secondary,
                    $title,
                    $avatarUrl,
                    $bio,
                    $signature,
                    $postsCount,
                    $reputation,
                    $warnings,
                    $isBanned,
                    $banReason,
                    $bannedUntil,
                    $newPassword !== '' ? $newPassword : null,
                    $customFields,
                    $coverUrl,
                    $musicUrl
                );
                $_SESSION['_vf_acp_flash'] = "Zapisano pełny profil użytkownika {$username}.";
            }
        }

        View::redirect('/admin?tab=members');
    }

    public function createMember(): void
    {
        $this->requireAdminCsrf();
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $groupId = max(1, (int) ($_POST['primary_group_id'] ?? 3));
        $title = trim((string) ($_POST['member_title'] ?? ''));

        if ($username !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && mb_strlen($password) >= 8) {
            $this->admin->createMember($username, $email, $password, $groupId, $title);
            $_SESSION['_vf_acp_flash'] = "Utworzono nowe konto użytkownika {$username}.";
        } else {
            $_SESSION['_vf_acp_flash'] = 'Podaj poprawną nazwę, adres e-mail oraz hasło (min. 8 znaków).';
        }

        View::redirect('/admin?tab=members');
    }

    public function saveGroup(): void
    {
        $this->requireAdminCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $color = trim((string) ($_POST['color_hex'] ?? '#475569'));
        if ($name !== '') {
            $savedId = $this->admin->saveGroup(
                $id > 0 ? $id : null,
                $name,
                $color,
                (int) ($_POST['is_admin'] ?? 0) === 1,
                (int) ($_POST['is_moderator'] ?? 0) === 1,
                (int) ($_POST['can_post'] ?? 0) === 1,
                trim((string) ($_POST['description'] ?? '')),
                trim((string) ($_POST['prefix_html'] ?? '')),
                trim((string) ($_POST['suffix_html'] ?? '')),
                (int) ($_POST['can_create_polls'] ?? 0) === 1,
                (int) ($_POST['can_edit_own'] ?? 0) === 1,
                (int) ($_POST['can_report'] ?? 0) === 1,
                (int) ($_POST['bypass_flood'] ?? 0) === 1,
                (int) ($_POST['flood_seconds'] ?? 30),
                ((int) ($_POST['show_in_legend'] ?? 1)) === 1,
                (int) ($_POST['can_set_profile_cover'] ?? 0) === 1,
                (int) ($_POST['can_set_profile_music'] ?? 0) === 1,
                (int) ($_POST['can_use_chat'] ?? 0) === 1,
                (int) ($_POST['can_moderate_chat'] ?? 0) === 1
            );
            $_SESSION['_vf_acp_flash'] = "Zapisano ustawienia grupy „{$name}” (#{$savedId}).";
        }
        View::redirect('/admin?tab=groups');
    }

    public function saveGroupPermissions(): void
    {
        $this->requireAdminCsrf();
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $cascadeMod = (int) ($_POST['cascade_moderation'] ?? 1) === 1;
        $rawPerms = (array) ($_POST['perms'] ?? []);

        if ($groupId > 0) {
            $normalized = [];
            foreach ($this->forums->allOrdered() as $f) {
                $fid = (int) $f['id'];
                $row = (array) ($rawPerms[$fid] ?? []);
                $normalized[$fid] = [
                    'can_view'         => !empty($row['can_view']),
                    'can_read'         => !empty($row['can_read']),
                    'can_create_topic' => !empty($row['can_create_topic']),
                    'can_reply'        => !empty($row['can_reply']),
                    'can_moderate'     => !empty($row['can_moderate']),
                ];
            }
            $this->admin->saveGroupPermissionsMatrix($groupId, $normalized, $cascadeMod);
            $_SESSION['_vf_acp_flash'] = 'Zapisano uprawnienia kategorii i działów dla wybranej grupy.';
        }

        View::redirect('/admin?tab=groups&edit_group=' . $groupId);
    }

    public function saveReaction(): void
    {
        $this->requireAdminCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $label = trim((string) ($_POST['label'] ?? ''));
        $points = (int) ($_POST['points'] ?? 1);
        if ($id > 0 && $label !== '') {
            $this->admin->updateReaction($id, $label, $points);
            $_SESSION['_vf_acp_flash'] = "Zaktualizowano reakcję „{$label}”.";
        }
        View::redirect('/admin?tab=reactions');
    }

    public function saveSettings(): void
    {
        $this->requireAdminCsrf();
        $allowedKeys = ['site_name', 'site_description', 'registration_enabled', 'topics_per_page', 'posts_per_page', 'klipy_api_key'];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $_POST)) {
                $this->admin->saveSetting($key, trim((string) $_POST[$key]));
            }
        }
        $_SESSION['_vf_acp_flash'] = 'Zapisano globalne ustawienia forum i SEO.';
        View::redirect('/admin?tab=settings');
    }

    public function saveCustomize(): void
    {
        $this->requireAdminCsrf();

        $accent = (string) ($_POST['accent_color'] ?? 'blue');
        if (!in_array($accent, ['blue', 'emerald', 'indigo', 'amber', 'rose', 'slate'], true)) {
            $accent = 'blue';
        }
        $layout = (string) ($_POST['forum_layout'] ?? 'list');
        if (!in_array($layout, ['list', 'grid', 'compact'], true)) {
            $layout = 'list';
        }
        $sidebarPos = (string) ($_POST['sidebar_position'] ?? 'right');
        if (!in_array($sidebarPos, ['right', 'left', 'none'], true)) {
            $sidebarPos = 'right';
        }
        $heroStyle = (string) ($_POST['hero_style'] ?? 'dark-gradient');
        if (!in_array($heroStyle, ['dark-gradient', 'ocean', 'clean'], true)) {
            $heroStyle = 'dark-gradient';
        }
        $defaultTheme = (string) ($_POST['default_theme'] ?? 'light');
        if (!in_array($defaultTheme, ['light', 'dark'], true)) {
            $defaultTheme = 'light';
        }
        $cardRadius = (string) ($_POST['card_radius'] ?? 'rounded');
        if (!in_array($cardRadius, ['sharp', 'rounded', 'soft'], true)) {
            $cardRadius = 'rounded';
        }
        $heroCtaUrl = trim((string) ($_POST['hero_cta_url'] ?? ''));
        if ($heroCtaUrl !== '' && !preg_match('#^(https?://|/)#i', $heroCtaUrl)) {
            $heroCtaUrl = '';
        }

        $this->admin->saveSetting('accent_color', $accent);
        $this->admin->saveSetting('default_theme', $defaultTheme);
        $this->admin->saveSetting('card_radius', $cardRadius);
        $this->admin->saveSetting('forum_layout', $layout);
        $this->admin->saveSetting('sidebar_position', $sidebarPos);
        $this->admin->saveSetting('hero_enabled', !empty($_POST['hero_enabled']) ? '1' : '0');
        $this->admin->saveSetting('hero_title', trim((string) ($_POST['hero_title'] ?? '')));
        $this->admin->saveSetting('hero_subtitle', trim((string) ($_POST['hero_subtitle'] ?? '')));
        $this->admin->saveSetting('hero_style', $heroStyle);
        $this->admin->saveSetting('hero_cta_label', mb_substr(trim((string) ($_POST['hero_cta_label'] ?? '')), 0, 60));
        $this->admin->saveSetting('hero_cta_url', mb_substr($heroCtaUrl, 0, 200));
        $this->admin->saveSetting('footer_text', mb_substr(trim((string) ($_POST['footer_text'] ?? '')), 0, 250));
        $this->admin->saveSetting('site_tagline', mb_substr(trim((string) ($_POST['site_tagline'] ?? '')), 0, 140));
        $this->admin->saveSetting('custom_widget_title', trim((string) ($_POST['custom_widget_title'] ?? '')));
        $this->admin->saveSetting('custom_widget_body', trim((string) ($_POST['custom_widget_body'] ?? '')));
        $this->admin->saveSetting('custom_widget_link_label', trim((string) ($_POST['custom_widget_link_label'] ?? '')));
        $this->admin->saveSetting('custom_widget_link_url', trim((string) ($_POST['custom_widget_link_url'] ?? '')));

        $defaultLabels = [
            'custom'     => 'Blok własny HTML/CTA',
            'activity'   => 'Ostatnia aktywność',
            'reputation' => 'Liderzy reputacji',
            'tags'       => 'Popularne tagi',
            'reactions'  => 'Statystyka reakcji',
            'stats'      => 'Statystyki forum',
        ];
        $rawWidgets = (array) ($_POST['widgets'] ?? []);
        $normalizedWidgets = [];
        foreach ($defaultLabels as $wid => $wlabel) {
            $row = (array) ($rawWidgets[$wid] ?? []);
            $normalizedWidgets[] = [
                'id'      => $wid,
                'label'   => $wlabel,
                'enabled' => !empty($row['enabled']),
                'order'   => (int) ($row['order'] ?? (count($normalizedWidgets) + 1)),
            ];
        }
        usort($normalizedWidgets, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);
        $finalWidgets = array_map(static fn (array $w): array => [
            'id'      => $w['id'],
            'label'   => $w['label'],
            'enabled' => $w['enabled'],
        ], $normalizedWidgets);
        $this->admin->saveSetting('sidebar_widgets_json', json_encode($finalWidgets, JSON_UNESCAPED_UNICODE) ?: '[]');

        $_SESSION['_vf_acp_flash'] = 'Zapisano konfigurację wyglądu, układu i widżetów forum.';
        View::redirect('/admin?tab=customize');
    }

    public function recountStats(): void
    {
        $this->requireAdminCsrf();
        $this->admin->recountAllCounters();
        $_SESSION['_vf_acp_flash'] = 'Przeliczono i zsynchronizowano wszystkie liczniki tematów, postów i użytkowników.';
        View::redirect('/admin?tab=overview');
    }

    public function prunePresence(): void
    {
        $this->requireAdminCsrf();
        $removed = $this->admin->prunePresence(1200);
        $_SESSION['_vf_acp_flash'] = "Wyczyszczono wygasłe sesje obecności (usunięto wpisów: {$removed}).";
        View::redirect('/admin?tab=overview');
    }

    public function exportJson(): void
    {
        $this->requireAdmin();
        $payload = $this->admin->exportStructureJson();
        $filename = 'veloforum-structure-' . date('Y-m-d') . '.json';
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function saveAnnouncement(): void
    {
        $this->requireAdminCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        if ($title !== '' && $content !== '') {
            $this->admin->saveAnnouncement(
                $id > 0 ? $id : null,
                $title,
                $content,
                (string) ($_POST['tone'] ?? 'info'),
                (string) ($_POST['placement'] ?? 'home'),
                trim((string) ($_POST['link_label'] ?? '')),
                trim((string) ($_POST['link_url'] ?? '')),
                true
            );
            $_SESSION['_vf_acp_flash'] = "Opublikowano ogłoszenie globalne „{$title}”.";
        }
        View::redirect('/admin?tab=customize#announcements');
    }

    public function toggleAnnouncement(): void
    {
        $this->requireAdminCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->admin->toggleAnnouncement($id);
            $_SESSION['_vf_acp_flash'] = 'Zmieniono status wyświetlania ogłoszenia.';
        }
        View::redirect('/admin?tab=customize#announcements');
    }

    public function deleteAnnouncement(): void
    {
        $this->requireAdminCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->admin->deleteAnnouncement($id);
            $_SESSION['_vf_acp_flash'] = 'Usunięto ogłoszenie globalne.';
        }
        View::redirect('/admin?tab=customize#announcements');
    }

    public function savePrefix(): void
    {
        $this->requireAdminCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $label = trim((string) ($_POST['label'] ?? ''));
        $color = trim((string) ($_POST['color_hex'] ?? '#2563eb'));
        if ($label !== '') {
            $this->admin->savePrefix($id > 0 ? $id : null, $label, $color, (int) ($_POST['position'] ?? 0));
            $_SESSION['_vf_acp_flash'] = "Zapisano prefiks tematów „{$label}”.";
        }
        View::redirect('/admin?tab=customize#prefixes');
    }

    public function deletePrefix(): void
    {
        $this->requireAdminCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->admin->deletePrefix($id);
            $_SESSION['_vf_acp_flash'] = 'Usunięto prefiks tematów.';
        }
        View::redirect('/admin?tab=customize#prefixes');
    }

    public function saveProfileField(): void
    {
        $this->requireAdminCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $label = trim((string) ($_POST['label'] ?? ''));
        $fieldKey = trim((string) ($_POST['field_key'] ?? ''));
        if ($label !== '') {
            $this->admin->saveProfileField(
                $id > 0 ? $id : null,
                $fieldKey !== '' ? $fieldKey : $label,
                $label,
                trim((string) ($_POST['placeholder'] ?? '')),
                !empty($_POST['show_in_post']),
                (int) ($_POST['position'] ?? 0)
            );
            $_SESSION['_vf_acp_flash'] = "Zapisano dodatkowe pole profilu „{$label}”.";
        }
        View::redirect('/admin?tab=customize#profile-fields');
    }

    public function deleteProfileField(): void
    {
        $this->requireAdminCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->admin->deleteProfileField($id);
            $_SESSION['_vf_acp_flash'] = 'Usunięto pole profilu.';
        }
        View::redirect('/admin?tab=customize#profile-fields');
    }

    public function saveAchievement(): void
    {
        $this->requireAdminCsrf();
        if ($this->achievements === null) {
            View::redirect('/admin?tab=achievements');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title !== '') {
            $this->achievements->save(
                $id > 0 ? $id : null,
                $title,
                trim((string) ($_POST['description'] ?? '')),
                (string) ($_POST['icon'] ?? 'trophy'),
                (string) ($_POST['color_hex'] ?? '#2563eb'),
                (string) ($_POST['rule_type'] ?? 'manual'),
                max(1, (int) ($_POST['rule_threshold'] ?? 1)),
                (int) ($_POST['is_active'] ?? 1) === 1,
                (int) ($_POST['position'] ?? 0)
            );
            $_SESSION['_vf_acp_flash'] = "Zapisano osiągnięcie „{$title}”.";
        }
        View::redirect('/admin?tab=achievements');
    }

    public function toggleAchievement(): void
    {
        $this->requireAdminCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0 && $this->achievements !== null) {
            $this->achievements->toggleActive($id);
            $_SESSION['_vf_acp_flash'] = 'Zmieniono status osiągnięcia.';
        }
        View::redirect('/admin?tab=achievements');
    }

    public function deleteAchievement(): void
    {
        $this->requireAdminCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0 && $this->achievements !== null) {
            $this->achievements->delete($id);
            $_SESSION['_vf_acp_flash'] = 'Usunięto osiągnięcie wraz z jego nadaniami.';
        }
        View::redirect('/admin?tab=achievements');
    }

    public function grantAchievement(): void
    {
        $user = $this->requireAdminCsrf();
        $achievementId = (int) ($_POST['achievement_id'] ?? 0);
        $memberId = (int) ($_POST['member_id'] ?? 0);
        $note = trim((string) ($_POST['note'] ?? ''));
        if ($achievementId > 0 && $memberId > 0 && $this->achievements !== null) {
            $this->achievements->grant(
                $achievementId,
                $memberId,
                (int) $user['id'],
                (string) $user['username'],
                $note
            );
            $ach = $this->achievements->findById($achievementId);
            if ($ach) {
                $this->notifications->create(
                    $memberId,
                    (int) $user['id'],
                    (string) $user['username'],
                    'system',
                    null,
                    null,
                    "Przyznano Ci osiągnięcie: „{$ach['title']}”!"
                );
            }
            $_SESSION['_vf_acp_flash'] = 'Przyznano osiągnięcie wybranemu użytkownikowi.';
        }
        View::redirect('/admin?tab=achievements#grant-award');
    }

    public function revokeAchievement(): void
    {
        $this->requireAdminCsrf();
        $achievementId = (int) ($_POST['achievement_id'] ?? 0);
        $memberId = (int) ($_POST['member_id'] ?? 0);
        if ($achievementId > 0 && $memberId > 0 && $this->achievements !== null) {
            $this->achievements->revoke($achievementId, $memberId);
            $_SESSION['_vf_acp_flash'] = 'Cofnięto nadane osiągnięcie użytkownikowi.';
        }
        View::redirect('/admin?tab=achievements#grant-award');
    }

    private function requireAdmin(): array
    {
        $user = $this->auth->currentMember();
        if (!$user || (int) ($user['is_admin'] ?? 0) !== 1) {
            http_response_code(403);
            exit('Dostęp do Panelu Administracyjnego wymaga uprawnień administratora.');
        }
        return $user;
    }

    private function requireAdminCsrf(): array
    {
        $user = $this->requireAdmin();
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Nieprawidłowy token CSRF.');
        }
        return $user;
    }
}
