<?php

declare(strict_types=1);

namespace VeloForum\Repository;

use PDO;

final class AchievementRepository
{
    public const VALID_ICONS = ['trophy', 'star', 'shield', 'flame', 'zap', 'award', 'heart', 'crown'];
    public const VALID_RULES = ['manual', 'posts_count', 'topics_count', 'reputation_points', 'solved_count'];

    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function listAll(bool $onlyActive = false): array
    {
        try {
            $sql = 'SELECT a.*, (SELECT COUNT(*) FROM vf_member_achievements ma WHERE ma.achievement_id = a.id) AS awarded_count
                    FROM vf_achievements a';
            if ($onlyActive) {
                $sql .= ' WHERE a.is_active = 1';
            }
            $sql .= ' ORDER BY a.position ASC, a.id ASC';
            return $this->db->query($sql)->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM vf_achievements WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function save(
        ?int $id,
        string $title,
        string $description,
        string $icon,
        string $colorHex,
        string $ruleType,
        int $ruleThreshold,
        bool $isActive,
        int $position = 0
    ): int {
        $safeTitle = mb_substr(trim($title), 0, 120);
        if ($safeTitle === '') {
            throw new \InvalidArgumentException('Tytuł osiągnięcia nie może być pusty.');
        }
        $safeDesc = mb_substr(trim($description), 0, 320);
        $safeIcon = in_array($icon, self::VALID_ICONS, true) ? $icon : 'trophy';
        $safeColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', trim($colorHex)) ? trim($colorHex) : '#2563eb';
        $safeRule = in_array($ruleType, self::VALID_RULES, true) ? $ruleType : 'manual';
        $safeThresh = max(1, min(1000000, $ruleThreshold));

        if ($id !== null && $id > 0) {
            $this->db->prepare(
                'UPDATE vf_achievements
                 SET title = :title, description = :descr, icon = :icon, color_hex = :color,
                     rule_type = :rtype, rule_threshold = :rthresh, is_active = :active, position = :pos
                 WHERE id = :id'
            )->execute([
                'title'   => $safeTitle,
                'descr'   => $safeDesc,
                'icon'    => $safeIcon,
                'color'   => $safeColor,
                'rtype'   => $safeRule,
                'rthresh' => $safeThresh,
                'active'  => $isActive ? 1 : 0,
                'pos'     => $position,
                'id'      => $id,
            ]);
            return $id;
        }

        $baseSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $safeTitle) ?? 'badge', '-'));
        if ($baseSlug === '') {
            $baseSlug = 'badge';
        }
        $code = mb_substr($baseSlug, 0, 52) . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
        $nextPos = $position > 0
            ? $position
            : ((int) $this->db->query('SELECT COALESCE(MAX(position), 0) FROM vf_achievements')->fetchColumn()) + 1;

        $this->db->prepare(
            'INSERT INTO vf_achievements
             (code, title, description, icon, color_hex, rule_type, rule_threshold, is_active, position, created_at)
             VALUES (:code, :title, :descr, :icon, :color, :rtype, :rthresh, :active, :pos, :at)'
        )->execute([
            'code'    => $code,
            'title'   => $safeTitle,
            'descr'   => $safeDesc,
            'icon'    => $safeIcon,
            'color'   => $safeColor,
            'rtype'   => $safeRule,
            'rthresh' => $safeThresh,
            'active'  => $isActive ? 1 : 0,
            'pos'     => $nextPos,
            'at'      => time(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function toggleActive(int $id): void
    {
        $this->db->prepare('UPDATE vf_achievements SET is_active = 1 - is_active WHERE id = :id')
            ->execute(['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM vf_achievements WHERE id = :id')
            ->execute(['id' => $id]);
    }

    public function grant(
        int $achievementId,
        int $memberId,
        ?int $awardedById,
        ?string $awardedByName,
        string $note = ''
    ): void {
        $safeNote = mb_substr(trim($note), 0, 255);
        $this->db->prepare(
            'INSERT INTO vf_member_achievements (achievement_id, member_id, awarded_by_id, awarded_by_name, note, awarded_at)
             VALUES (:aid, :mid, :bid, :bname, :note, :at)
             ON DUPLICATE KEY UPDATE
               awarded_by_id = VALUES(awarded_by_id),
               awarded_by_name = VALUES(awarded_by_name),
               note = VALUES(note),
               awarded_at = VALUES(awarded_at)'
        )->execute([
            'aid'   => $achievementId,
            'mid'   => $memberId,
            'bid'   => $awardedById,
            'bname' => $awardedByName !== null && $awardedByName !== '' ? mb_substr($awardedByName, 0, 120) : null,
            'note'  => $safeNote !== '' ? $safeNote : null,
            'at'    => time(),
        ]);
    }

    public function revoke(int $achievementId, int $memberId): void
    {
        $this->db->prepare(
            'DELETE FROM vf_member_achievements WHERE achievement_id = :aid AND member_id = :mid'
        )->execute(['aid' => $achievementId, 'mid' => $memberId]);
    }

    /** @return array<int, array<string, mixed>> */
    public function recentAwards(int $limit = 30): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT ma.*, a.title AS achievement_title, a.icon AS achievement_icon, a.color_hex AS achievement_color,
                        m.username AS member_username, m.slug AS member_slug
                 FROM vf_member_achievements ma
                 INNER JOIN vf_achievements a ON a.id = ma.achievement_id
                 INNER JOIN vf_members m ON m.id = ma.member_id
                 ORDER BY ma.awarded_at DESC, ma.id DESC
                 LIMIT :lim'
            );
            $stmt->bindValue('lim', max(1, $limit), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Wylicza zdobyte osiągnięcia dla zestawu użytkowników (zarówno ręczne jak i automatyczne wg progów).
     *
     * @param int[] $memberIds
     * @return array<int, array<int, array<string, mixed>>> Mapa member_id => lista odblokowanych osiągnięć
     */
    public function unlockedForMembers(array $memberIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $memberIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $achievements = $this->listAll(true);
        if ($achievements === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $memberStmt = $this->db->prepare(
            "SELECT m.id, m.posts_count, m.reputation_points,
                    COALESCE((SELECT COUNT(*) FROM vf_topics t WHERE t.author_id = m.id AND t.is_approved = 1), 0) AS topics_count,
                    COALESCE((SELECT COUNT(*) FROM vf_topics t2 INNER JOIN vf_posts p2 ON p2.id = t2.best_answer_post_id WHERE p2.author_id = m.id), 0) AS solved_count
             FROM vf_members m
             WHERE m.id IN ({$placeholders})"
        );
        $memberStmt->execute($ids);
        $statsByMember = [];
        foreach ($memberStmt->fetchAll() as $row) {
            $statsByMember[(int) $row['id']] = [
                'posts_count'       => (int) $row['posts_count'],
                'topics_count'      => (int) $row['topics_count'],
                'reputation_points' => (int) $row['reputation_points'],
                'solved_count'      => (int) $row['solved_count'],
            ];
        }

        $manualMap = [];
        try {
            $maStmt = $this->db->prepare(
                "SELECT ma.achievement_id, ma.member_id, ma.awarded_at, ma.awarded_by_name, ma.note
                 FROM vf_member_achievements ma
                 WHERE ma.member_id IN ({$placeholders})"
            );
            $maStmt->execute($ids);
            foreach ($maStmt->fetchAll() as $ma) {
                $manualMap[(int) $ma['member_id']][(int) $ma['achievement_id']] = $ma;
            }
        } catch (\Throwable) {
            $manualMap = [];
        }

        $out = [];
        foreach ($ids as $mid) {
            $stats = $statsByMember[$mid] ?? [
                'posts_count'       => 0,
                'topics_count'      => 0,
                'reputation_points' => 0,
                'solved_count'      => 0,
            ];
            $unlocked = [];
            foreach ($achievements as $ach) {
                $aid = (int) $ach['id'];
                $manualRow = $manualMap[$mid][$aid] ?? null;
                $ruleType = (string) ($ach['rule_type'] ?? 'manual');
                $threshold = max(1, (int) ($ach['rule_threshold'] ?? 1));
                $isAutoUnlocked = $ruleType !== 'manual' && (($stats[$ruleType] ?? 0) >= $threshold);

                if ($manualRow !== null || $isAutoUnlocked) {
                    $unlocked[] = [
                        'id'              => $aid,
                        'code'            => (string) $ach['code'],
                        'title'           => (string) $ach['title'],
                        'description'     => (string) $ach['description'],
                        'icon'            => (string) $ach['icon'],
                        'color_hex'       => (string) $ach['color_hex'],
                        'rule_type'       => $ruleType,
                        'rule_threshold'  => $threshold,
                        'is_manual'       => $manualRow !== null,
                        'awarded_at'      => $manualRow !== null ? (int) $manualRow['awarded_at'] : null,
                        'awarded_by_name' => $manualRow !== null ? ($manualRow['awarded_by_name'] ?? null) : null,
                        'note'            => $manualRow !== null ? ($manualRow['note'] ?? null) : null,
                    ];
                }
            }
            $out[$mid] = $unlocked;
        }

        return $out;
    }

    /**
     * Pełna gablota osiągnięć (odblokowane + w trakcie postępu) dla strony profilu użytkownika.
     *
     * @param array<string, int> $stats
     * @return array<int, array<string, mixed>>
     */
    public function profileShowcase(int $memberId, array $stats): array
    {
        $achievements = $this->listAll(true);
        if ($achievements === []) {
            return [];
        }

        $manualMap = [];
        try {
            $stmt = $this->db->prepare('SELECT * FROM vf_member_achievements WHERE member_id = :mid');
            $stmt->execute(['mid' => $memberId]);
            foreach ($stmt->fetchAll() as $ma) {
                $manualMap[(int) $ma['achievement_id']] = $ma;
            }
        } catch (\Throwable) {
            $manualMap = [];
        }

        $items = [];
        foreach ($achievements as $ach) {
            $aid = (int) $ach['id'];
            $ruleType = (string) ($ach['rule_type'] ?? 'manual');
            $threshold = max(1, (int) ($ach['rule_threshold'] ?? 1));
            $manualRow = $manualMap[$aid] ?? null;
            $currentVal = $ruleType === 'manual' ? ($manualRow !== null ? 1 : 0) : max(0, (int) ($stats[$ruleType] ?? 0));
            $unlocked = $manualRow !== null || ($ruleType !== 'manual' && $currentVal >= $threshold);
            $pct = $unlocked ? 100 : (int) min(99, max(0, round(($currentVal / $threshold) * 100)));

            $items[] = [
                'id'              => $aid,
                'code'            => (string) $ach['code'],
                'title'           => (string) $ach['title'],
                'description'     => (string) $ach['description'],
                'icon'            => (string) $ach['icon'],
                'color_hex'       => (string) $ach['color_hex'],
                'rule_type'       => $ruleType,
                'rule_threshold'  => $threshold,
                'current_value'   => $currentVal,
                'progress_pct'    => $pct,
                'unlocked'        => $unlocked,
                'is_manual'       => $manualRow !== null,
                'awarded_at'      => $manualRow !== null ? (int) $manualRow['awarded_at'] : null,
                'awarded_by_name' => $manualRow !== null ? ($manualRow['awarded_by_name'] ?? null) : null,
                'note'            => $manualRow !== null ? ($manualRow['note'] ?? null) : null,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            if ($a['unlocked'] !== $b['unlocked']) {
                return $a['unlocked'] ? -1 : 1;
            }
            return $b['progress_pct'] <=> $a['progress_pct'];
        });

        return $items;
    }
}
