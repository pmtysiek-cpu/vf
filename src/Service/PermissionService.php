<?php

declare(strict_types=1);

namespace VeloForum\Service;

use PDO;

/**
 * Serwis rozstrzygania uprawnień działów i grup zgodny z Invision Community 5:
 * - obsługa wielu grup użytkownika (primary_group_id + secondary_group_ids) poprzez sumę logiczną OR,
 * - dziedziczenie can_moderate z kategorii/działu nadrzędnego w dół drzewa parent_id,
 * - zakres Panelu Moderatora (ModCP) ograniczony do nadzorowanych kategorii/działów.
 */
final class PermissionService
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $groupsCache = null;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $forumsCache = null;

    /** @var array<int, array<int, array<string, mixed>>>|null */
    private ?array $permsCache = null;

    /** @var array<int, array<int, array<string, mixed>>> */
    private array $modGroupsCache = [];

    public function __construct(private readonly PDO $db)
    {
    }

    public function resetCache(): void
    {
        $this->groupsCache = null;
        $this->forumsCache = null;
        $this->permsCache = null;
        $this->modGroupsCache = [];
    }

    /** @return int[] */
    public function memberGroupIds(?array $member): array
    {
        if (!$member) {
            return [2]; // Goście
        }

        $ids = [];
        $primary = (int) ($member['primary_group_id'] ?? 3);
        if ($primary > 0) {
            $ids[$primary] = $primary;
        }

        $rawSec = $member['secondary_group_ids'] ?? '[]';
        $secList = is_array($rawSec) ? $rawSec : json_decode((string) $rawSec, true);
        if (is_array($secList)) {
            foreach ($secList as $gid) {
                $id = (int) $gid;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }

    /** @return array<int, array<string, mixed>> */
    public function memberGroups(?array $member): array
    {
        $groups = $this->allGroups();
        $out = [];
        foreach ($this->memberGroupIds($member) as $gid) {
            if (isset($groups[$gid])) {
                $out[] = $groups[$gid];
            }
        }
        return $out;
    }

    public function isGlobalAdmin(?array $member): bool
    {
        if (!$member || (int) ($member['is_banned'] ?? 0) === 1) {
            return false;
        }
        if ((int) ($member['is_admin'] ?? 0) === 1) {
            return true;
        }
        foreach ($this->memberGroups($member) as $g) {
            if ((int) ($g['is_admin'] ?? 0) === 1) {
                return true;
            }
        }
        return false;
    }

    public function isGlobalStaff(?array $member): bool
    {
        if (!$member || (int) ($member['is_banned'] ?? 0) === 1) {
            return false;
        }
        if ((int) ($member['is_admin'] ?? 0) === 1 || (int) ($member['is_moderator'] ?? 0) === 1) {
            return true;
        }
        foreach ($this->memberGroups($member) as $g) {
            if ((int) ($g['is_admin'] ?? 0) === 1 || (int) ($g['is_moderator'] ?? 0) === 1) {
                return true;
            }
        }
        return false;
    }

    public function canSetProfileCover(?array $member): bool
    {
        if (!$member || (int) ($member['is_banned'] ?? 0) === 1) {
            return false;
        }
        if ($this->isGlobalAdmin($member)) {
            return true;
        }
        foreach ($this->memberGroups($member) as $g) {
            if ((int) ($g['is_admin'] ?? 0) === 1 || (int) ($g['can_set_profile_cover'] ?? 0) === 1) {
                return true;
            }
        }
        return false;
    }

    public function canSetProfileMusic(?array $member): bool
    {
        if (!$member || (int) ($member['is_banned'] ?? 0) === 1) {
            return false;
        }
        if ($this->isGlobalAdmin($member)) {
            return true;
        }
        foreach ($this->memberGroups($member) as $g) {
            if ((int) ($g['is_admin'] ?? 0) === 1 || (int) ($g['can_set_profile_music'] ?? 0) === 1) {
                return true;
            }
        }
        return false;
    }

    public function canUseChat(?array $member): bool
    {
        if (!$member || (int) ($member['is_banned'] ?? 0) === 1) {
            return false;
        }
        if ($this->isGlobalAdmin($member)) {
            return true;
        }
        foreach ($this->memberGroups($member) as $g) {
            if ((int) ($g['is_admin'] ?? 0) === 1 || (int) ($g['can_use_chat'] ?? 1) === 1) {
                return true;
            }
        }
        return false;
    }

    public function canModerateChat(?array $member): bool
    {
        if (!$member || (int) ($member['is_banned'] ?? 0) === 1) {
            return false;
        }
        if ($this->isGlobalAdmin($member) || $this->isGlobalStaff($member)) {
            return true;
        }
        foreach ($this->memberGroups($member) as $g) {
            if ((int) ($g['is_admin'] ?? 0) === 1 || (int) ($g['is_moderator'] ?? 0) === 1 || (int) ($g['can_moderate_chat'] ?? 0) === 1) {
                return true;
            }
        }
        return false;
    }

    public function isChatMuted(?array $member): bool
    {
        if (!$member) {
            return false;
        }
        $until = (int) ($member['chat_muted_until'] ?? 0);
        return $until > time();
    }

    /** @return int[] */
    public function ancestorChain(int $forumId): array
    {
        $forums = $this->allForums();
        $chain = [];
        $visited = [];
        $current = $forumId;

        while ($current > 0 && !isset($visited[$current]) && isset($forums[$current]) && count($chain) < 16) {
            $visited[$current] = true;
            $chain[] = $current;
            $parent = $forums[$current]['parent_id'] ?? null;
            $current = $parent !== null ? (int) $parent : 0;
        }

        return $chain;
    }

    /** @return int[] */
    public function descendantIds(int $rootId): array
    {
        $forums = $this->allForums();
        $result = [];
        $queue = [$rootId];
        $visited = [];

        while ($queue !== []) {
            $id = array_shift($queue);
            if ($id === null || isset($visited[$id])) {
                continue;
            }
            $visited[$id] = true;
            foreach ($forums as $fid => $f) {
                if ((int) ($f['parent_id'] ?? 0) === $id && !isset($visited[$fid])) {
                    $result[] = $fid;
                    $queue[] = $fid;
                }
            }
        }

        return $result;
    }

    /**
     * @return array{can_view: bool, can_read: bool, can_create_topic: bool, can_reply: bool, can_moderate: bool}
     */
    public function explicitOrInheritedGroupPermission(int $forumId, int $groupId): array
    {
        $groups = $this->allGroups();
        $group = $groups[$groupId] ?? null;
        $perms = $this->allPermissions();
        $chain = $this->ancestorChain($forumId);
        $groupIsStaff = $group !== null && ((int) ($group['is_admin'] ?? 0) === 1 || (int) ($group['is_moderator'] ?? 0) === 1);

        $inheritedMod = false;
        foreach ($chain as $ancId) {
            if (!empty($perms[$ancId][$groupId]['can_moderate'])) {
                $inheritedMod = true;
                break;
            }
        }

        if (isset($perms[$forumId][$groupId])) {
            $direct = $perms[$forumId][$groupId];
            return [
                'can_view'         => (int) $direct['can_view'] === 1,
                'can_read'         => (int) $direct['can_read'] === 1,
                'can_create_topic' => (int) $direct['can_create_topic'] === 1,
                'can_reply'        => (int) $direct['can_reply'] === 1,
                'can_moderate'     => ((int) $direct['can_moderate'] === 1) || $inheritedMod || $groupIsStaff,
            ];
        }

        foreach (array_slice($chain, 1) as $ancId) {
            if (isset($perms[$ancId][$groupId])) {
                $anc = $perms[$ancId][$groupId];
                return [
                    'can_view'         => (int) $anc['can_view'] === 1,
                    'can_read'         => (int) $anc['can_read'] === 1,
                    'can_create_topic' => (int) $anc['can_create_topic'] === 1,
                    'can_reply'        => (int) $anc['can_reply'] === 1,
                    'can_moderate'     => $inheritedMod || $groupIsStaff,
                ];
            }
        }

        $defaultPost = $group !== null && (int) ($group['can_post'] ?? 1) === 1;
        return [
            'can_view'         => true,
            'can_read'         => true,
            'can_create_topic' => $defaultPost,
            'can_reply'        => $defaultPost,
            'can_moderate'     => $inheritedMod || $groupIsStaff,
        ];
    }

    /**
     * @return array{
     *   can_view: bool,
     *   can_read: bool,
     *   can_create_topic: bool,
     *   can_reply: bool,
     *   can_moderate: bool,
     *   can_create_polls: bool,
     *   can_edit_own: bool,
     *   can_report: bool
     * }
     */
    public function forForum(?array $member, int $forumId): array
    {
        if (!$member) {
            $guestPerm = $this->explicitOrInheritedGroupPermission($forumId, 2);
            return [
                'can_view'         => $guestPerm['can_view'],
                'can_read'         => $guestPerm['can_read'],
                'can_create_topic' => false,
                'can_reply'        => false,
                'can_moderate'     => false,
                'can_create_polls' => false,
                'can_edit_own'     => false,
                'can_report'       => false,
            ];
        }

        if ((int) ($member['is_banned'] ?? 0) === 1) {
            return [
                'can_view'         => true,
                'can_read'         => true,
                'can_create_topic' => false,
                'can_reply'        => false,
                'can_moderate'     => false,
                'can_create_polls' => false,
                'can_edit_own'     => false,
                'can_report'       => false,
            ];
        }

        if ($this->isGlobalAdmin($member)) {
            return [
                'can_view'         => true,
                'can_read'         => true,
                'can_create_topic' => true,
                'can_reply'        => true,
                'can_moderate'     => true,
                'can_create_polls' => true,
                'can_edit_own'     => true,
                'can_report'       => true,
            ];
        }

        $groups = $this->memberGroups($member);
        $isGlobalMod = $this->isGlobalStaff($member);
        $canView = false;
        $canRead = false;
        $canCreateTopic = false;
        $canReply = false;
        $canModerate = $isGlobalMod;

        foreach ($this->memberGroupIds($member) as $gid) {
            $p = $this->explicitOrInheritedGroupPermission($forumId, $gid);
            if ($p['can_view']) {
                $canView = true;
            }
            if ($p['can_read']) {
                $canRead = true;
            }
            if ($p['can_create_topic']) {
                $canCreateTopic = true;
            }
            if ($p['can_reply']) {
                $canReply = true;
            }
            if ($p['can_moderate']) {
                $canModerate = true;
            }
        }

        if ($canModerate) {
            $canView = true;
            $canRead = true;
        }

        $canPolls = false;
        $canEdit = false;
        $canReport = false;
        foreach ($groups as $g) {
            if ((int) ($g['is_admin'] ?? 0) === 1 || (int) ($g['is_moderator'] ?? 0) === 1 || (int) ($g['can_create_polls'] ?? $g['can_post'] ?? 1) === 1) {
                $canPolls = true;
            }
            if ((int) ($g['is_admin'] ?? 0) === 1 || (int) ($g['is_moderator'] ?? 0) === 1 || (int) ($g['can_edit_own'] ?? 1) === 1) {
                $canEdit = true;
            }
            if ((int) ($g['can_report'] ?? 1) === 1) {
                $canReport = true;
            }
        }

        return [
            'can_view'         => $canView,
            'can_read'         => $canRead,
            'can_create_topic' => $canCreateTopic,
            'can_reply'        => $canReply,
            'can_moderate'     => $canModerate,
            'can_create_polls' => $canPolls,
            'can_edit_own'     => $canEdit,
            'can_report'       => $canReport,
        ];
    }

    /** @return int[] */
    public function moderatedForumIds(?array $member): array
    {
        if (!$member || (int) ($member['is_banned'] ?? 0) === 1) {
            return [];
        }
        $ids = [];
        foreach (array_keys($this->allForums()) as $fid) {
            $eff = $this->forForum($member, (int) $fid);
            if ($eff['can_moderate']) {
                $ids[] = (int) $fid;
            }
        }
        return $ids;
    }

    public function canAccessModCp(?array $member): bool
    {
        if (!$member || (int) ($member['is_banned'] ?? 0) === 1) {
            return false;
        }
        return $this->isGlobalStaff($member) || count($this->moderatedForumIds($member)) > 0;
    }

    /** @return array<int, array<string, mixed>> */
    public function categoryModeratorGroups(int $forumId): array
    {
        if (isset($this->modGroupsCache[$forumId])) {
            return $this->modGroupsCache[$forumId];
        }

        $chain = $this->ancestorChain($forumId);
        $perms = $this->allPermissions();
        $groups = $this->allGroups();
        $modGroupIds = [];

        foreach ($chain as $ancId) {
            foreach ($perms[$ancId] ?? [] as $gid => $row) {
                if ((int) ($row['can_moderate'] ?? 0) === 1) {
                    $modGroupIds[(int) $gid] = true;
                }
            }
        }

        $out = [];
        foreach (array_keys($modGroupIds) as $gid) {
            $g = $groups[$gid] ?? null;
            if ($g && (int) ($g['is_admin'] ?? 0) === 0 && (int) ($g['is_moderator'] ?? 0) === 0) {
                $out[] = $g;
            }
        }
        $this->modGroupsCache[$forumId] = $out;
        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    public function allGroups(): array
    {
        if ($this->groupsCache !== null) {
            return $this->groupsCache;
        }
        $rows = $this->db->query('SELECT * FROM vf_groups ORDER BY id ASC')->fetchAll();
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['id']] = $r;
        }
        $this->groupsCache = $map;
        return $map;
    }

    /** @return array<int, array<string, mixed>> */
    public function allForums(): array
    {
        if ($this->forumsCache !== null) {
            return $this->forumsCache;
        }
        $rows = $this->db->query('SELECT * FROM vf_forums ORDER BY position ASC, id ASC')->fetchAll();
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['id']] = $r;
        }
        $this->forumsCache = $map;
        return $map;
    }

    /** @return array<int, array<int, array<string, mixed>>> */
    public function allPermissions(): array
    {
        if ($this->permsCache !== null) {
            return $this->permsCache;
        }
        $rows = $this->db->query('SELECT * FROM vf_forum_permissions')->fetchAll();
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['forum_id']][(int) $r['group_id']] = $r;
        }
        $this->permsCache = $map;
        return $map;
    }
}
