<?php

declare(strict_types=1);

namespace VeloForum\Repository;

use PDO;

final class NotificationRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function unreadCount(int $recipientId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM vf_notifications WHERE recipient_id = :uid AND is_read = 0'
        );
        $stmt->execute(['uid' => $recipientId]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public function recentForMember(int $recipientId, int $limit = 30): array
    {
        $stmt = $this->db->prepare(
            'SELECT n.*, t.title AS topic_title, t.slug AS topic_slug
             FROM vf_notifications n
             LEFT JOIN vf_topics t ON t.id = n.topic_id
             WHERE n.recipient_id = :uid
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue('uid', $recipientId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function create(
        int $recipientId,
        ?int $actorId,
        string $actorName,
        string $type,
        ?int $topicId,
        ?int $postId,
        string $message
    ): void {
        if ($actorId !== null && $recipientId === $actorId) {
            return;
        }

        $this->db->prepare(
            'INSERT INTO vf_notifications
             (recipient_id, actor_id, actor_name, type, topic_id, post_id, message, is_read, created_at)
             VALUES (:rec, :act, :aname, :type, :tid, :pid, :msg, 0, :at)'
        )->execute([
            'rec'   => $recipientId,
            'act'   => $actorId,
            'aname' => $actorName,
            'type'  => $type,
            'tid'   => $topicId,
            'pid'   => $postId,
            'msg'   => mb_substr($message, 0, 250),
            'at'    => time(),
        ]);
    }

    public function markAllRead(int $recipientId): void
    {
        $this->db->prepare('UPDATE vf_notifications SET is_read = 1 WHERE recipient_id = :uid')
            ->execute(['uid' => $recipientId]);
    }
}
