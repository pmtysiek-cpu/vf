<?php

declare(strict_types=1);

namespace VeloForum\Repository;

use PDO;

/**
 * Repozytorium prywatnych wiadomości (konwersacji 1-na-1 między użytkownikami z obsługą live pollingu).
 */
final class MessageRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function unreadCountForMember(int $memberId): int
    {
        if ($memberId <= 0) {
            return 0;
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(DISTINCT cp.conversation_id)
                 FROM vf_conversation_participants cp
                 INNER JOIN vf_conversations c ON c.id = cp.conversation_id
                 LEFT JOIN vf_messages lm ON lm.id = c.last_message_id
                 WHERE cp.member_id = :mid
                   AND cp.is_deleted = 0
                   AND c.last_message_at > cp.last_read_at
                   AND (lm.sender_id IS NULL OR lm.sender_id <> :mid2)'
            );
            $stmt->execute(['mid' => $memberId, 'mid2' => $memberId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listConversationsForMember(int $memberId, int $limit = 40): array
    {
        if ($memberId <= 0) {
            return [];
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT c.id, c.subject, c.starter_id, c.last_message_id, c.last_message_at, c.created_at,
                        cp.last_read_at,
                        lm.sender_id AS last_sender_id, lm.sender_name AS last_sender_name, lm.content_html AS last_content_html,
                        peer.member_id AS peer_id,
                        pm.username AS peer_username, pm.slug AS peer_slug, pm.avatar_url AS peer_avatar, pm.last_activity_at AS peer_last_activity_at,
                        pg.color_hex AS peer_color, pg.prefix_html AS peer_prefix, pg.suffix_html AS peer_suffix, pg.name AS peer_group
                 FROM vf_conversation_participants cp
                 INNER JOIN vf_conversations c ON c.id = cp.conversation_id
                 LEFT JOIN vf_messages lm ON lm.id = c.last_message_id
                 LEFT JOIN vf_conversation_participants peer ON peer.conversation_id = c.id AND peer.member_id <> :mid1
                 LEFT JOIN vf_members pm ON pm.id = peer.member_id
                 LEFT JOIN vf_groups pg ON pg.id = pm.primary_group_id
                 WHERE cp.member_id = :mid2 AND cp.is_deleted = 0
                 ORDER BY c.last_message_at DESC, c.id DESC
                 LIMIT :lim'
            );
            $stmt->bindValue('mid1', $memberId, PDO::PARAM_INT);
            $stmt->bindValue('mid2', $memberId, PDO::PARAM_INT);
            $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = [];
            foreach ($stmt->fetchAll() as $row) {
                $isUnread = ((int) ($row['last_message_at'] ?? 0) > (int) ($row['last_read_at'] ?? 0))
                    && ((int) ($row['last_sender_id'] ?? 0) !== $memberId);
                $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($row['last_content_html'] ?? ''))) ?? '');
                $snippet = mb_strlen($plain) > 90 ? (mb_substr($plain, 0, 87) . '…') : $plain;

                $rows[] = [
                    'id'                    => (int) $row['id'],
                    'subject'               => (string) $row['subject'],
                    'last_message_id'       => $row['last_message_id'] !== null ? (int) $row['last_message_id'] : null,
                    'last_message_at'       => (int) ($row['last_message_at'] ?? 0),
                    'last_sender_id'        => $row['last_sender_id'] !== null ? (int) $row['last_sender_id'] : null,
                    'last_sender_name'      => (string) ($row['last_sender_name'] ?? ''),
                    'last_snippet'          => $snippet,
                    'is_unread'             => $isUnread,
                    'peer_id'               => $row['peer_id'] !== null ? (int) $row['peer_id'] : null,
                    'peer_username'         => (string) ($row['peer_username'] ?: 'Użytkownik'),
                    'peer_slug'             => (string) ($row['peer_slug'] ?: 'user'),
                    'peer_avatar'           => $row['peer_avatar'] ?: null,
                    'peer_last_activity_at' => $row['peer_last_activity_at'] !== null ? (int) $row['peer_last_activity_at'] : null,
                    'peer_color'            => $row['peer_color'] ?: null,
                    'peer_prefix'           => (string) ($row['peer_prefix'] ?? ''),
                    'peer_suffix'           => (string) ($row['peer_suffix'] ?? ''),
                    'peer_group'            => $row['peer_group'] ?: null,
                ];
            }
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    public function findConversationForMember(int $convId, int $memberId): ?array
    {
        if ($convId <= 0 || $memberId <= 0) {
            return null;
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT c.id, c.subject, c.starter_id, c.last_message_id, c.last_message_at, c.created_at,
                        cp.last_read_at,
                        peer.member_id AS peer_id,
                        pm.username AS peer_username, pm.slug AS peer_slug, pm.avatar_url AS peer_avatar, pm.last_activity_at AS peer_last_activity_at,
                        pg.color_hex AS peer_color, pg.prefix_html AS peer_prefix, pg.suffix_html AS peer_suffix, pg.name AS peer_group
                 FROM vf_conversation_participants cp
                 INNER JOIN vf_conversations c ON c.id = cp.conversation_id
                 LEFT JOIN vf_conversation_participants peer ON peer.conversation_id = c.id AND peer.member_id <> :mid1
                 LEFT JOIN vf_members pm ON pm.id = peer.member_id
                 LEFT JOIN vf_groups pg ON pg.id = pm.primary_group_id
                 WHERE cp.conversation_id = :cid AND cp.member_id = :mid2 AND cp.is_deleted = 0
                 LIMIT 1'
            );
            $stmt->execute(['mid1' => $memberId, 'cid' => $convId, 'mid2' => $memberId]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function messagesInConversation(int $convId, int $afterId = 0, int $limit = 80): array
    {
        if ($convId <= 0) {
            return [];
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT ms.id, ms.conversation_id, ms.sender_id,
                        COALESCE(m.username, ms.sender_name) AS sender_name,
                        m.slug AS sender_slug, m.avatar_url AS sender_avatar,
                        g.color_hex AS sender_color, g.prefix_html AS sender_prefix,
                        g.suffix_html AS sender_suffix, g.name AS sender_group,
                        ms.content_html, ms.created_at
                 FROM vf_messages ms
                 LEFT JOIN vf_members m ON m.id = ms.sender_id
                 LEFT JOIN vf_groups g ON g.id = m.primary_group_id
                 WHERE ms.conversation_id = :cid AND ms.id > :after_id
                 ORDER BY ms.id ASC
                 LIMIT :lim'
            );
            $stmt->bindValue('cid', $convId, PDO::PARAM_INT);
            $stmt->bindValue('after_id', max(0, $afterId), PDO::PARAM_INT);
            $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    public function markConversationRead(int $convId, int $memberId): void
    {
        if ($convId <= 0 || $memberId <= 0) {
            return;
        }
        try {
            $this->db->prepare(
                'UPDATE vf_conversation_participants
                 SET last_read_at = :now
                 WHERE conversation_id = :cid AND member_id = :mid'
            )->execute(['now' => time(), 'cid' => $convId, 'mid' => $memberId]);
        } catch (\Throwable) {
            // Ignorujemy
        }
    }

    public function createConversation(
        int $senderId,
        string $senderName,
        int $recipientId,
        string $subject,
        string $contentHtml
    ): int {
        $now = time();
        $cleanSubject = mb_substr(trim($subject), 0, 185);
        if ($cleanSubject === '') {
            $cleanSubject = 'Wiadomość prywatna';
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'INSERT INTO vf_conversations (subject, starter_id, last_message_at, created_at)
                 VALUES (:subj, :sid, :now, :now2)'
            )->execute([
                'subj' => $cleanSubject,
                'sid'  => $senderId,
                'now'  => $now,
                'now2' => $now,
            ]);
            $convId = (int) $this->db->lastInsertId();

            $pStmt = $this->db->prepare(
                'INSERT INTO vf_conversation_participants (conversation_id, member_id, last_read_at, is_deleted, joined_at)
                 VALUES (:cid, :mid, :read_at, 0, :joined)'
            );
            $pStmt->execute([
                'cid'     => $convId,
                'mid'     => $senderId,
                'read_at' => $now,
                'joined'  => $now,
            ]);
            if ($recipientId !== $senderId) {
                $pStmt->execute([
                    'cid'     => $convId,
                    'mid'     => $recipientId,
                    'read_at' => 0,
                    'joined'  => $now,
                ]);
            }

            $mStmt = $this->db->prepare(
                'INSERT INTO vf_messages (conversation_id, sender_id, sender_name, content_html, created_at)
                 VALUES (:cid, :sid, :sname, :body, :at)'
            );
            $mStmt->execute([
                'cid'   => $convId,
                'sid'   => $senderId,
                'sname' => mb_substr($senderName, 0, 120),
                'body'  => $contentHtml,
                'at'    => $now,
            ]);
            $msgId = (int) $this->db->lastInsertId();

            $this->db->prepare(
                'UPDATE vf_conversations SET last_message_id = :mid, last_message_at = :at WHERE id = :cid'
            )->execute(['mid' => $msgId, 'at' => $now, 'cid' => $convId]);

            $this->db->commit();
            return $convId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function addReply(int $convId, int $senderId, string $senderName, string $contentHtml): int
    {
        $now = time();
        $this->db->beginTransaction();
        try {
            $mStmt = $this->db->prepare(
                'INSERT INTO vf_messages (conversation_id, sender_id, sender_name, content_html, created_at)
                 VALUES (:cid, :sid, :sname, :body, :at)'
            );
            $mStmt->execute([
                'cid'   => $convId,
                'sid'   => $senderId,
                'sname' => mb_substr($senderName, 0, 120),
                'body'  => $contentHtml,
                'at'    => $now,
            ]);
            $msgId = (int) $this->db->lastInsertId();

            $this->db->prepare(
                'UPDATE vf_conversations SET last_message_id = :mid, last_message_at = :at WHERE id = :cid'
            )->execute(['mid' => $msgId, 'at' => $now, 'cid' => $convId]);

            // Jeśli odbiorca wcześniej opuścił wątek, przywracamy go po otrzymaniu nowej odpowiedzi
            $this->db->prepare(
                'UPDATE vf_conversation_participants
                 SET is_deleted = 0
                 WHERE conversation_id = :cid'
            )->execute(['cid' => $convId]);

            $this->db->prepare(
                'UPDATE vf_conversation_participants
                 SET last_read_at = :now
                 WHERE conversation_id = :cid AND member_id = :mid'
            )->execute(['now' => $now, 'cid' => $convId, 'mid' => $senderId]);

            $this->db->commit();
            return $msgId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function leaveConversation(int $convId, int $memberId): void
    {
        try {
            $this->db->prepare(
                'UPDATE vf_conversation_participants
                 SET is_deleted = 1
                 WHERE conversation_id = :cid AND member_id = :mid'
            )->execute(['cid' => $convId, 'mid' => $memberId]);
        } catch (\Throwable) {
            // Ignorujemy
        }
    }
}
