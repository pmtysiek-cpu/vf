<?php

declare(strict_types=1);

namespace VeloForum\Repository;

use PDO;
use VeloForum\Core\View;

final class ChatRepository
{
    public const ALLOWED_EMOJIS = ['👍', '❤️', '😂', '🔥', '🙌'];

    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function recentMessages(int $limit = 45, int $afterId = 0, ?int $viewerId = null): array
    {
        try {
            $safeLimit = max(5, min(80, $limit));
            if ($afterId > 0) {
                $stmt = $this->db->prepare(
                    'SELECT c.*, m.slug, m.avatar_url,
                            g.name AS group_name, g.color_hex AS group_color,
                            g.prefix_html AS group_prefix, g.suffix_html AS group_suffix
                     FROM vf_chat_messages c
                     LEFT JOIN vf_members m ON m.id = c.member_id
                     LEFT JOIN vf_groups g ON g.id = m.primary_group_id
                     WHERE c.id > :after_id
                     ORDER BY c.id ASC
                     LIMIT :lim'
                );
                $stmt->bindValue('after_id', $afterId, PDO::PARAM_INT);
                $stmt->bindValue('lim', $safeLimit, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll();
            } else {
                $stmt = $this->db->prepare(
                    'SELECT sub.* FROM (
                        SELECT c.*, m.slug, m.avatar_url,
                               g.name AS group_name, g.color_hex AS group_color,
                               g.prefix_html AS group_prefix, g.suffix_html AS group_suffix
                        FROM vf_chat_messages c
                        LEFT JOIN vf_members m ON m.id = c.member_id
                        LEFT JOIN vf_groups g ON g.id = m.primary_group_id
                        ORDER BY c.id DESC
                        LIMIT :lim
                    ) sub ORDER BY sub.id ASC'
                );
                $stmt->bindValue('lim', $safeLimit, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll();
            }

            if ($rows === []) {
                return [];
            }

            $msgIds = array_map(static fn (array $r): int => (int) $r['id'], $rows);
            $reactionsMap = $this->reactionsForMessages($msgIds, $viewerId);

            foreach ($rows as &$r) {
                $id = (int) $r['id'];
                $r['reactions'] = $reactionsMap[$id] ?? [];
            }
            unset($r);

            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    public function pinnedMessage(?int $viewerId = null): ?array
    {
        try {
            $stmt = $this->db->query(
                'SELECT c.*, m.slug, m.avatar_url,
                        g.name AS group_name, g.color_hex AS group_color,
                        g.prefix_html AS group_prefix, g.suffix_html AS group_suffix
                 FROM vf_chat_messages c
                 LEFT JOIN vf_members m ON m.id = c.member_id
                 LEFT JOIN vf_groups g ON g.id = m.primary_group_id
                 WHERE c.is_pinned = 1
                 ORDER BY c.id DESC
                 LIMIT 1'
            );
            $row = $stmt->fetch();
            if (!$row) {
                return null;
            }
            $reactionsMap = $this->reactionsForMessages([(int) $row['id']], $viewerId);
            $row['reactions'] = $reactionsMap[(int) $row['id']] ?? [];
            return $row;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param int[] $messageIds
     * @return array<int, array<int, array{emoji: string, count: int, reacted: bool}>>
     */
    public function reactionsForMessages(array $messageIds, ?int $viewerId = null): array
    {
        $cleanIds = array_values(array_unique(array_filter(array_map('intval', $messageIds), static fn (int $i): bool => $i > 0)));
        if ($cleanIds === []) {
            return [];
        }
        try {
            $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
            $stmt = $this->db->prepare(
                "SELECT message_id, emoji, COUNT(*) AS cnt,
                        MAX(CASE WHEN member_id = ? THEN 1 ELSE 0 END) AS mine
                 FROM vf_chat_reactions
                 WHERE message_id IN ({$placeholders})
                 GROUP BY message_id, emoji
                 ORDER BY cnt DESC, id ASC"
            );
            $params = array_merge([$viewerId ?? 0], $cleanIds);
            $stmt->execute($params);
            $out = [];
            while ($row = $stmt->fetch()) {
                $out[(int) $row['message_id']][] = [
                    'emoji'   => (string) $row['emoji'],
                    'count'   => (int) $row['cnt'],
                    'reacted' => (int) $row['mine'] === 1,
                ];
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    public function lastMessageTimestampByMember(int $memberId): int
    {
        try {
            $stmt = $this->db->prepare('SELECT MAX(created_at) FROM vf_chat_messages WHERE member_id = :mid');
            $stmt->execute(['mid' => $memberId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function findById(int $messageId): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM vf_chat_messages WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $messageId]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{ok: bool, id?: int, error?: string} */
    public function send(
        int $memberId,
        string $username,
        string $rawText,
        ?string $attachmentUrl = null,
        ?string $replyToUsername = null,
        int $maxLength = 400
    ): array {
        $text = trim($rawText);
        $safeGif = self::validateKlipyUrl($attachmentUrl);

        if ($text === '' && $safeGif === null) {
            return ['ok' => false, 'error' => 'Wpisz treść wiadomości lub wybierz GIF.'];
        }

        $isAction = false;
        if (str_starts_with($text, '/me ')) {
            $isAction = true;
            $text = trim(substr($text, 4));
            if ($text === '') {
                return ['ok' => false, 'error' => 'Podaj treść akcji po /me.'];
            }
        }

        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength);
        }

        $bodyHtml = $this->formatBodyHtml($text);
        $replyTo = $replyToUsername !== null && trim($replyToUsername) !== ''
            ? mb_substr(trim($replyToUsername), 0, 120)
            : null;

        $stmt = $this->db->prepare(
            'INSERT INTO vf_chat_messages
             (member_id, username, body_html, attachment_url, is_action, is_pinned, reply_to_username, created_at)
             VALUES (:mid, :uname, :html, :att, :act, 0, :reply, :at)'
        );
        $stmt->execute([
            'mid'   => $memberId,
            'uname' => mb_substr($username, 0, 120),
            'html'  => $bodyHtml,
            'att'   => $safeGif,
            'act'   => $isAction ? 1 : 0,
            'reply' => $replyTo,
            'at'    => time(),
        ]);

        return ['ok' => true, 'id' => (int) $this->db->lastInsertId()];
    }

    /** @return array<int, array{emoji: string, count: int, reacted: bool}> */
    public function toggleReaction(int $messageId, int $memberId, string $emoji): array
    {
        if (!in_array($emoji, self::ALLOWED_EMOJIS, true)) {
            return $this->reactionsForMessages([$messageId], $memberId)[$messageId] ?? [];
        }

        $check = $this->db->prepare(
            'SELECT id FROM vf_chat_reactions WHERE message_id = :mid AND member_id = :uid AND emoji = :em LIMIT 1'
        );
        $check->execute(['mid' => $messageId, 'uid' => $memberId, 'em' => $emoji]);
        $existingId = $check->fetchColumn();

        if ($existingId) {
            $this->db->prepare('DELETE FROM vf_chat_reactions WHERE id = :id')->execute(['id' => (int) $existingId]);
        } else {
            $this->db->prepare(
                'INSERT IGNORE INTO vf_chat_reactions (message_id, member_id, emoji, created_at) VALUES (:mid, :uid, :em, :at)'
            )->execute(['mid' => $messageId, 'uid' => $memberId, 'em' => $emoji, 'at' => time()]);
        }

        return $this->reactionsForMessages([$messageId], $memberId)[$messageId] ?? [];
    }

    public function deleteMessage(int $messageId): void
    {
        $this->db->prepare('DELETE FROM vf_chat_messages WHERE id = :id')->execute(['id' => $messageId]);
    }

    public function togglePin(int $messageId): bool
    {
        $msg = $this->findById($messageId);
        if (!$msg) {
            return false;
        }
        $wasPinned = (int) ($msg['is_pinned'] ?? 0) === 1;
        $this->db->exec('UPDATE vf_chat_messages SET is_pinned = 0 WHERE is_pinned = 1');
        if (!$wasPinned) {
            $this->db->prepare('UPDATE vf_chat_messages SET is_pinned = 1 WHERE id = :id')->execute(['id' => $messageId]);
            return true;
        }
        return false;
    }

    public function clearAll(): void
    {
        $this->db->exec('DELETE FROM vf_chat_reactions');
        $this->db->exec('DELETE FROM vf_chat_messages');
    }

    public function muteMember(int $memberId, int $minutes): void
    {
        $until = $minutes > 0 ? time() + ($minutes * 60) : null;
        $this->db->prepare('UPDATE vf_members SET chat_muted_until = :until WHERE id = :id')
            ->execute(['until' => $until, 'id' => $memberId]);
    }

    /** @return array<int, array<string, mixed>> */
    public function mutedMembers(): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT m.id, m.username, m.slug, m.chat_muted_until,
                        g.color_hex AS group_color, g.name AS group_name
                 FROM vf_members m
                 INNER JOIN vf_groups g ON g.id = m.primary_group_id
                 WHERE m.chat_muted_until IS NOT NULL AND m.chat_muted_until > :now
                 ORDER BY m.chat_muted_until DESC'
            );
            $stmt->execute(['now' => time()]);
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    public function statsSummary(): array
    {
        try {
            return [
                'messages' => (int) $this->db->query('SELECT COUNT(*) FROM vf_chat_messages')->fetchColumn(),
                'muted'    => count($this->mutedMembers()),
            ];
        } catch (\Throwable) {
            return ['messages' => 0, 'muted' => 0];
        }
    }

    public static function validateKlipyUrl(?string $url): ?string
    {
        $raw = trim((string) $url);
        if ($raw === '' || strlen($raw) > 500) {
            return null;
        }
        if (str_starts_with($raw, 'data:image/')) {
            return null;
        }
        $parts = @parse_url($raw);
        if (!is_array($parts)) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme !== 'https' || $host === '') {
            return null;
        }
        if ($host === 'klipy.com' || str_ends_with($host, '.klipy.com') || str_ends_with($host, '.klipy.co')) {
            return $raw;
        }
        return null;
    }

    private function formatBodyHtml(string $text): string
    {
        $escaped = View::e($text);
        $escaped = nl2br($escaped, false);
        $escaped = preg_replace(
            '/(?<![A-Za-z0-9_])@([A-Za-z0-9_.-]{2,40})/u',
            '<span class="vf-mention-chip">@$1</span>',
            $escaped
        ) ?? $escaped;
        return $escaped;
    }
}
