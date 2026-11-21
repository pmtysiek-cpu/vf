<?php

declare(strict_types=1);

namespace VeloForum\Repository;

use PDO;

final class TopicRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function countByForum(int $forumId, string $filter = 'all', ?int $prefixId = null): int
    {
        $extra = '';
        $params = ['fid' => $forumId];
        if ($filter === 'solved') {
            $extra .= ' AND best_answer_post_id IS NOT NULL';
        } elseif ($filter === 'polls') {
            $extra .= ' AND EXISTS (SELECT 1 FROM vf_polls pl WHERE pl.topic_id = vf_topics.id)';
        }
        if ($prefixId !== null && $prefixId > 0) {
            $extra .= ' AND prefix_id = :pid';
            $params['pid'] = $prefixId;
        }
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM vf_topics WHERE forum_id = :fid AND is_approved = 1{$extra}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public function listByForum(
        int $forumId,
        int $page = 1,
        int $perPage = 25,
        string $sort = 'activity',
        string $filter = 'all',
        ?int $prefixId = null
    ): array {
        $offset = max(0, ($page - 1) * $perPage);
        $filterSql = '';
        if ($filter === 'solved') {
            $filterSql .= ' AND t.best_answer_post_id IS NOT NULL';
        } elseif ($filter === 'polls') {
            $filterSql .= ' AND pl.id IS NOT NULL';
        }
        if ($prefixId !== null && $prefixId > 0) {
            $filterSql .= ' AND t.prefix_id = :prefix_id';
        }

        $orderCol = match ($sort) {
            'newest'  => 't.created_at DESC',
            'replies' => 't.posts_count DESC, t.last_post_at DESC',
            'views'   => 't.views_count DESC, t.last_post_at DESC',
            default   => 't.last_post_at DESC',
        };

        $stmt = $this->db->prepare(
            "SELECT t.*,
                    COALESCE(NULLIF(t.author_id, 0), amn.id) AS author_id,
                    COALESCE(m.username, amn.username, t.author_name) AS author_name,
                    COALESCE(m.slug, amn.slug) AS author_slug,
                    COALESCE(m.avatar_url, amn.avatar_url) AS author_avatar,
                    ag.color_hex AS author_group_color, ag.prefix_html AS author_group_prefix,
                    ag.suffix_html AS author_group_suffix, ag.name AS author_group_name,
                    COALESCE(NULLIF(t.last_poster_id, 0), lpn.id) AS last_poster_id,
                    COALESCE(lp.username, lpn.username, t.last_poster_name) AS last_poster_name,
                    COALESCE(lp.slug, lpn.slug) AS last_poster_slug,
                    COALESCE(lp.avatar_url, lpn.avatar_url) AS last_poster_avatar,
                    lg.color_hex AS last_poster_color, lg.prefix_html AS last_poster_prefix,
                    lg.suffix_html AS last_poster_suffix, lg.name AS last_poster_group,
                    tp.label AS prefix_label, tp.color_hex AS prefix_color,
                    (pl.id IS NOT NULL) AS has_poll
             FROM vf_topics t
             LEFT JOIN vf_members m ON m.id = t.author_id
             LEFT JOIN vf_members amn ON (t.author_id IS NULL OR t.author_id = 0) AND amn.username = t.author_name
             LEFT JOIN vf_groups ag ON ag.id = COALESCE(m.primary_group_id, amn.primary_group_id)
             LEFT JOIN vf_members lp ON lp.id = t.last_poster_id
             LEFT JOIN vf_members lpn ON (t.last_poster_id IS NULL OR t.last_poster_id = 0) AND lpn.username = t.last_poster_name
             LEFT JOIN vf_groups lg ON lg.id = COALESCE(lp.primary_group_id, lpn.primary_group_id)
             LEFT JOIN vf_polls pl ON pl.topic_id = t.id
             LEFT JOIN vf_topic_prefixes tp ON tp.id = t.prefix_id
             WHERE t.forum_id = :forum_id AND t.is_approved = 1{$filterSql}
             ORDER BY t.is_pinned DESC, {$orderCol}
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue('forum_id', $forumId, PDO::PARAM_INT);
        if ($prefixId !== null && $prefixId > 0) {
            $stmt->bindValue('prefix_id', $prefixId, PDO::PARAM_INT);
        }
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findById(int $topicId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, f.name AS forum_name, f.slug AS forum_slug,
                    f.allow_polls AS forum_allow_polls,
                    f.allow_best_answer AS forum_allow_best_answer,
                    f.is_locked AS forum_is_locked,
                    f.require_approval AS forum_require_approval,
                    tp.label AS prefix_label, tp.color_hex AS prefix_color,
                    COALESCE(NULLIF(t.author_id, 0), amn.id) AS author_id,
                    COALESCE(am.username, amn.username, t.author_name) AS author_name,
                    COALESCE(am.slug, amn.slug) AS author_slug,
                    ag.color_hex AS author_group_color, ag.prefix_html AS author_group_prefix,
                    ag.suffix_html AS author_group_suffix, ag.name AS author_group_name
             FROM vf_topics t
             INNER JOIN vf_forums f ON f.id = t.forum_id
             LEFT JOIN vf_topic_prefixes tp ON tp.id = t.prefix_id
             LEFT JOIN vf_members am ON am.id = t.author_id
             LEFT JOIN vf_members amn ON (t.author_id IS NULL OR t.author_id = 0) AND amn.username = t.author_name
             LEFT JOIN vf_groups ag ON ag.id = COALESCE(am.primary_group_id, amn.primary_group_id)
             WHERE t.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $topicId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function countPostsInTopic(int $topicId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM vf_posts WHERE topic_id = :tid AND is_approved = 1');
        $stmt->execute(['tid' => $topicId]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public function postsInTopic(int $topicId, int $page = 1, int $perPage = 20): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->db->prepare(
            'SELECT p.*,
                    COALESCE(NULLIF(p.author_id, 0), mn.id) AS author_id,
                    COALESCE(m.username, mn.username) AS member_username,
                    COALESCE(m.slug, mn.slug) AS member_slug,
                    COALESCE(m.avatar_url, mn.avatar_url) AS avatar_url,
                    COALESCE(m.cover_url, mn.cover_url) AS member_cover_url,
                    COALESCE(m.posts_count, mn.posts_count) AS member_posts,
                    COALESCE(m.reputation_points, mn.reputation_points) AS member_reputation,
                    COALESCE(m.joined_at, mn.joined_at) AS member_joined,
                    COALESCE(m.last_activity_at, mn.last_activity_at) AS member_last_activity,
                    COALESCE(m.member_title, mn.member_title) AS member_title,
                    COALESCE(m.signature, mn.signature) AS member_signature,
                    COALESCE(m.custom_fields, mn.custom_fields) AS member_custom_fields,
                    COALESCE(m.primary_group_id, mn.primary_group_id) AS member_primary_group_id,
                    COALESCE(m.secondary_group_ids, mn.secondary_group_ids) AS member_secondary_group_ids,
                    COALESCE(g.is_admin, 0) AS member_is_admin,
                    COALESCE(g.is_moderator, 0) AS member_is_moderator,
                    g.name AS group_name, g.color_hex AS group_color,
                    g.prefix_html AS group_prefix, g.suffix_html AS group_suffix
             FROM vf_posts p
             LEFT JOIN vf_members m ON m.id = p.author_id
             LEFT JOIN vf_members mn ON (p.author_id IS NULL OR p.author_id = 0) AND mn.username = p.author_name
             LEFT JOIN vf_groups g ON g.id = COALESCE(m.primary_group_id, mn.primary_group_id)
             WHERE p.topic_id = :topic_id AND p.is_approved = 1
             ORDER BY p.created_at ASC, p.id ASC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('topic_id', $topicId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param int[] $authorIds
     * @return array<int, int>
     */
    public function solvedCountsForAuthors(array $authorIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $authorIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->db->prepare(
                "SELECT p.author_id, COUNT(*) AS cnt
                 FROM vf_topics t
                 INNER JOIN vf_posts p ON p.id = t.best_answer_post_id
                 WHERE t.is_approved = 1 AND p.author_id IN ({$placeholders})
                 GROUP BY p.author_id"
            );
            $stmt->execute($ids);
            $map = [];
            while ($row = $stmt->fetch()) {
                $map[(int) $row['author_id']] = (int) $row['cnt'];
            }
            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function participantsInTopic(int $topicId, int $limit = 10): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT m.id, m.username, m.slug, m.avatar_url,
                        g.color_hex AS group_color, g.prefix_html AS group_prefix,
                        g.suffix_html AS group_suffix, g.name AS group_name,
                        COUNT(p.id) AS post_cnt, MAX(p.created_at) AS last_at
                 FROM vf_posts p
                 INNER JOIN vf_members m ON m.id = p.author_id
                 LEFT JOIN vf_groups g ON g.id = m.primary_group_id
                 WHERE p.topic_id = :tid AND p.is_approved = 1 AND p.author_id IS NOT NULL
                 GROUP BY m.id, m.username, m.slug, m.avatar_url, g.color_hex, g.prefix_html, g.suffix_html, g.name
                 ORDER BY post_cnt DESC, last_at DESC
                 LIMIT :limit'
            );
            $stmt->bindValue('tid', $topicId, PDO::PARAM_INT);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    public function findPostById(int $postId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM vf_posts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $postId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updatePost(int $postId, string $contentHtml, string $editorName): void
    {
        $this->db->prepare(
            'UPDATE vf_posts SET content_html = :html, edited_at = :at, edited_by_name = :by WHERE id = :id'
        )->execute([
            'html' => $contentHtml,
            'at'   => time(),
            'by'   => $editorName,
            'id'   => $postId,
        ]);
    }

    public function updatePostModNote(int $postId, ?string $note, ?int $modId, ?string $modName): void
    {
        $clean = $note !== null ? trim(mb_substr($note, 0, 1000)) : '';
        if ($clean === '') {
            $this->db->prepare(
                'UPDATE vf_posts
                 SET mod_note = NULL, mod_note_author_id = NULL, mod_note_author = NULL, mod_note_at = NULL
                 WHERE id = :id'
            )->execute(['id' => $postId]);
            return;
        }

        $this->db->prepare(
            'UPDATE vf_posts
             SET mod_note = :note, mod_note_author_id = :aid, mod_note_author = :aname, mod_note_at = :at
             WHERE id = :id'
        )->execute([
            'note'  => $clean,
            'aid'   => $modId,
            'aname' => mb_substr((string) $modName, 0, 120),
            'at'    => time(),
            'id'    => $postId,
        ]);
    }

    public function updateTopicMeta(int $topicId, string $title, ?int $prefixId): void
    {
        $cleanTitle = mb_substr(trim($title), 0, 250);
        if ($cleanTitle === '') {
            return;
        }
        $this->db->prepare(
            'UPDATE vf_topics SET title = :title, slug = :slug, prefix_id = :pid WHERE id = :id'
        )->execute([
            'title' => $cleanTitle,
            'slug'  => $this->slugify($cleanTitle),
            'pid'   => ($prefixId !== null && $prefixId > 0) ? $prefixId : null,
            'id'    => $topicId,
        ]);
    }

    public function togglePin(int $topicId): void
    {
        $this->db->prepare('UPDATE vf_topics SET is_pinned = 1 - is_pinned WHERE id = :id')
            ->execute(['id' => $topicId]);
    }

    public function toggleFeatured(int $topicId): void
    {
        $this->db->prepare('UPDATE vf_topics SET is_featured = 1 - is_featured WHERE id = :id')
            ->execute(['id' => $topicId]);
    }

    public function toggleLock(int $topicId): void
    {
        $this->db->prepare('UPDATE vf_topics SET is_locked = 1 - is_locked WHERE id = :id')
            ->execute(['id' => $topicId]);
    }

    public function setPostApproval(int $postId, bool $approved): void
    {
        $post = $this->findPostById($postId);
        if (!$post) {
            return;
        }
        $wasApproved = (int) $post['is_approved'] === 1;
        $nextApproved = $approved ? 1 : 0;
        $this->db->prepare('UPDATE vf_posts SET is_approved = :app WHERE id = :id')
            ->execute(['app' => $nextApproved, 'id' => $postId]);

        $topicId = (int) $post['topic_id'];
        if ((int) $post['is_first_post'] === 1) {
            $this->db->prepare('UPDATE vf_topics SET is_approved = :app WHERE id = :tid')
                ->execute(['app' => $nextApproved, 'tid' => $topicId]);
        }

        if ($wasApproved !== $approved) {
            $topic = $this->findById($topicId);
            if ($topic) {
                $forumId = (int) $topic['forum_id'];
                if ($approved) {
                    if ((int) $post['is_first_post'] === 1) {
                        $this->db->prepare('UPDATE vf_forums SET topics_count = topics_count + 1, posts_count = posts_count + 1, last_topic_id = :tid, last_post_at = :at WHERE id = :fid')
                            ->execute(['tid' => $topicId, 'at' => (int) $post['created_at'], 'fid' => $forumId]);
                    } else {
                        $this->db->prepare('UPDATE vf_topics SET posts_count = posts_count + 1, last_post_id = :pid, last_post_at = :at WHERE id = :tid')
                            ->execute(['pid' => $postId, 'at' => (int) $post['created_at'], 'tid' => $topicId]);
                        $this->db->prepare('UPDATE vf_forums SET posts_count = posts_count + 1 WHERE id = :fid')
                            ->execute(['fid' => $forumId]);
                    }
                } else {
                    if ((int) $post['is_first_post'] === 0) {
                        $this->db->prepare('UPDATE vf_topics SET posts_count = GREATEST(1, posts_count - 1) WHERE id = :tid')
                            ->execute(['tid' => $topicId]);
                    } else {
                        $this->db->prepare('UPDATE vf_forums SET topics_count = GREATEST(0, topics_count - 1) WHERE id = :fid')
                            ->execute(['fid' => $forumId]);
                    }
                    $this->db->prepare('UPDATE vf_forums SET posts_count = GREATEST(0, posts_count - 1) WHERE id = :fid')
                        ->execute(['fid' => $forumId]);
                }
            }
        }
    }

    public function deletePost(int $postId): void
    {
        $post = $this->findPostById($postId);
        if (!$post || (int) ($post['is_first_post'] ?? 0) === 1) {
            return;
        }
        $topicId = (int) $post['topic_id'];
        $topic = $this->findById($topicId);
        $wasApproved = (int) ($post['is_approved'] ?? 1) === 1;

        if ($topic && (int) ($topic['best_answer_post_id'] ?? 0) === $postId) {
            $this->db->prepare('UPDATE vf_topics SET best_answer_post_id = NULL WHERE id = :tid')
                ->execute(['tid' => $topicId]);
        }

        $this->db->prepare('DELETE FROM vf_posts WHERE id = :id')->execute(['id' => $postId]);

        if ($wasApproved) {
            $this->db->prepare('UPDATE vf_topics SET posts_count = GREATEST(1, posts_count - 1) WHERE id = :tid')
                ->execute(['tid' => $topicId]);
            if ($topic) {
                $this->db->prepare('UPDATE vf_forums SET posts_count = GREATEST(0, posts_count - 1) WHERE id = :fid')
                    ->execute(['fid' => (int) $topic['forum_id']]);
            }
        }
        if (!empty($post['author_id'])) {
            $this->db->prepare('UPDATE vf_members SET posts_count = GREATEST(0, posts_count - 1) WHERE id = :uid')
                ->execute(['uid' => (int) $post['author_id']]);
        }
    }

    public function moveTopic(int $topicId, int $targetForumId): void
    {
        $topic = $this->findById($topicId);
        if (!$topic || (int) $topic['forum_id'] === $targetForumId) {
            return;
        }
        $oldForumId = (int) $topic['forum_id'];
        $postsCount = (int) $topic['posts_count'];

        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE vf_topics SET forum_id = :fid WHERE id = :tid')
                ->execute(['fid' => $targetForumId, 'tid' => $topicId]);

            $this->db->prepare(
                'UPDATE vf_forums
                 SET topics_count = GREATEST(0, topics_count - 1),
                     posts_count = GREATEST(0, posts_count - :pc)
                 WHERE id = :fid'
            )->execute(['pc' => $postsCount, 'fid' => $oldForumId]);

            $this->db->prepare(
                'UPDATE vf_forums
                 SET topics_count = topics_count + 1,
                     posts_count = posts_count + :pc
                 WHERE id = :fid'
            )->execute(['pc' => $postsCount, 'fid' => $targetForumId]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @param int[] $memberIds */
    public function saveMentions(int $postId, array $memberIds): void
    {
        $this->db->prepare('DELETE FROM vf_post_mentions WHERE post_id = :pid')->execute(['pid' => $postId]);
        $now = time();
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO vf_post_mentions (post_id, member_id, created_at) VALUES (:pid, :mid, :at)'
        );
        foreach (array_unique($memberIds) as $mid) {
            if ((int) $mid > 0) {
                $stmt->execute(['pid' => $postId, 'mid' => (int) $mid, 'at' => $now]);
            }
        }
    }

    public function incrementViews(int $topicId): void
    {
        $this->db->prepare('UPDATE vf_topics SET views_count = views_count + 1 WHERE id = :id')
            ->execute(['id' => $topicId]);
    }

    public function createTopic(
        int $forumId,
        int $authorId,
        string $authorName,
        string $title,
        string $contentHtml,
        string $ipAddress,
        bool $isApproved = true,
        ?int $prefixId = null
    ): int {
        $this->db->beginTransaction();
        try {
            $now = time();
            $slug = $this->slugify($title);
            $appFlag = $isApproved ? 1 : 0;

            $topicStmt = $this->db->prepare(
                'INSERT INTO vf_topics
                 (forum_id, prefix_id, author_id, author_name, title, slug, posts_count, views_count, is_approved, created_at, last_post_at, last_poster_id, last_poster_name)
                 VALUES (:forum_id, :prefix_id, :author_id, :author_name, :title, :slug, 1, 1, :approved, :created, :last_post, :last_poster_id, :last_poster_name)'
            );
            $topicStmt->execute([
                'forum_id'         => $forumId,
                'prefix_id'        => ($prefixId !== null && $prefixId > 0) ? $prefixId : null,
                'author_id'        => $authorId,
                'author_name'      => $authorName,
                'title'            => $title,
                'slug'             => $slug,
                'approved'         => $appFlag,
                'created'          => $now,
                'last_post'        => $now,
                'last_poster_id'   => $authorId,
                'last_poster_name' => $authorName,
            ]);
            $topicId = (int) $this->db->lastInsertId();

            $postStmt = $this->db->prepare(
                'INSERT INTO vf_posts
                 (topic_id, author_id, author_name, content_html, is_first_post, is_approved, ip_address, created_at)
                 VALUES (:topic_id, :author_id, :author_name, :html, 1, :approved, :ip, :created)'
            );
            $postStmt->execute([
                'topic_id'    => $topicId,
                'author_id'   => $authorId,
                'author_name' => $authorName,
                'html'        => $contentHtml,
                'approved'    => $appFlag,
                'ip'          => $ipAddress,
                'created'     => $now,
            ]);
            $postId = (int) $this->db->lastInsertId();

            $this->db->prepare('UPDATE vf_topics SET first_post_id = :pid, last_post_id = :lpid WHERE id = :tid')
                ->execute(['pid' => $postId, 'lpid' => $postId, 'tid' => $topicId]);

            if ($isApproved) {
                $this->db->prepare(
                    'UPDATE vf_forums
                     SET topics_count = topics_count + 1, posts_count = posts_count + 1,
                         last_topic_id = :tid, last_post_at = :now,
                         last_poster_id = :uid, last_poster_name = :uname
                     WHERE id = :fid'
                )->execute([
                    'tid'   => $topicId,
                    'now'   => $now,
                    'uid'   => $authorId,
                    'uname' => $authorName,
                    'fid'   => $forumId,
                ]);
            }

            $this->db->prepare('UPDATE vf_members SET posts_count = posts_count + 1 WHERE id = :uid')
                ->execute(['uid' => $authorId]);

            $this->db->commit();
            return $topicId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function addReply(
        int $topicId,
        int $forumId,
        int $authorId,
        string $authorName,
        string $contentHtml,
        string $ipAddress,
        bool $isApproved = true
    ): int {
        $this->db->beginTransaction();
        try {
            $now = time();
            $appFlag = $isApproved ? 1 : 0;

            $postStmt = $this->db->prepare(
                'INSERT INTO vf_posts
                 (topic_id, author_id, author_name, content_html, is_first_post, is_approved, ip_address, created_at)
                 VALUES (:topic_id, :author_id, :author_name, :html, 0, :approved, :ip, :created)'
            );
            $postStmt->execute([
                'topic_id'    => $topicId,
                'author_id'   => $authorId,
                'author_name' => $authorName,
                'html'        => $contentHtml,
                'approved'    => $appFlag,
                'ip'          => $ipAddress,
                'created'     => $now,
            ]);
            $postId = (int) $this->db->lastInsertId();

            if ($isApproved) {
                $this->db->prepare(
                    'UPDATE vf_topics
                     SET posts_count = posts_count + 1, last_post_id = :pid,
                         last_post_at = :now, last_poster_id = :uid, last_poster_name = :uname
                     WHERE id = :tid'
                )->execute([
                    'pid'   => $postId,
                    'now'   => $now,
                    'uid'   => $authorId,
                    'uname' => $authorName,
                    'tid'   => $topicId,
                ]);

                $this->db->prepare(
                    'UPDATE vf_forums
                     SET posts_count = posts_count + 1, last_topic_id = :tid,
                         last_post_at = :now, last_poster_id = :uid, last_poster_name = :uname
                     WHERE id = :fid'
                )->execute([
                    'tid'   => $topicId,
                    'now'   => $now,
                    'uid'   => $authorId,
                    'uname' => $authorName,
                    'fid'   => $forumId,
                ]);
            }

            $this->db->prepare('UPDATE vf_members SET posts_count = posts_count + 1 WHERE id = :uid')
                ->execute(['uid' => $authorId]);

            $this->db->commit();
            return $postId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function latestGlobal(int $limit = 8): array
    {
        return $this->latestTopicsFiltered('activity', $limit);
    }

    /** @return array<int, array<string, mixed>> */
    public function latestTopicsFiltered(string $tab = 'activity', int $limit = 25): array
    {
        $whereExtra = '';
        $orderBy = 't.last_post_at DESC';
        if ($tab === 'latest') {
            $orderBy = 't.created_at DESC';
        } elseif ($tab === 'solved') {
            $whereExtra = ' AND t.best_answer_post_id IS NOT NULL';
        } elseif ($tab === 'polls') {
            $whereExtra = ' AND pl.id IS NOT NULL';
        }

        $stmt = $this->db->prepare(
            "SELECT t.*, f.name AS forum_name, f.slug AS forum_slug,
                    COALESCE(NULLIF(t.author_id, 0), amn.id) AS author_id,
                    COALESCE(am.username, amn.username, t.author_name) AS author_name,
                    COALESCE(am.slug, amn.slug) AS author_slug,
                    ag.color_hex AS author_group_color, ag.prefix_html AS author_group_prefix,
                    ag.suffix_html AS author_group_suffix, ag.name AS author_group_name,
                    COALESCE(NULLIF(t.last_poster_id, 0), lpn.id) AS last_poster_id,
                    COALESCE(lp.username, lpn.username, t.last_poster_name) AS last_poster_name,
                    COALESCE(lp.slug, lpn.slug) AS last_poster_slug,
                    COALESCE(lp.avatar_url, lpn.avatar_url) AS last_poster_avatar,
                    lg.color_hex AS last_poster_color, lg.prefix_html AS last_poster_prefix,
                    lg.suffix_html AS last_poster_suffix, lg.name AS last_poster_group,
                    tp.label AS prefix_label, tp.color_hex AS prefix_color,
                    (pl.id IS NOT NULL) AS has_poll
             FROM vf_topics t
             INNER JOIN vf_forums f ON f.id = t.forum_id
             LEFT JOIN vf_members am ON am.id = t.author_id
             LEFT JOIN vf_members amn ON (t.author_id IS NULL OR t.author_id = 0) AND amn.username = t.author_name
             LEFT JOIN vf_groups ag ON ag.id = COALESCE(am.primary_group_id, amn.primary_group_id)
             LEFT JOIN vf_members lp ON lp.id = t.last_poster_id
             LEFT JOIN vf_members lpn ON (t.last_poster_id IS NULL OR t.last_poster_id = 0) AND lpn.username = t.last_poster_name
             LEFT JOIN vf_groups lg ON lg.id = COALESCE(lp.primary_group_id, lpn.primary_group_id)
             LEFT JOIN vf_polls pl ON pl.topic_id = t.id
             LEFT JOIN vf_topic_prefixes tp ON tp.id = t.prefix_id
             WHERE t.is_approved = 1{$whereExtra}
             ORDER BY {$orderBy}
             LIMIT :limit"
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function pinnedGlobal(int $limit = 6): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, f.name AS forum_name, f.slug AS forum_slug,
                    COALESCE(NULLIF(t.author_id, 0), amn.id) AS author_id,
                    COALESCE(am.username, amn.username, t.author_name) AS author_name,
                    COALESCE(am.slug, amn.slug) AS author_slug,
                    ag.color_hex AS author_group_color, ag.prefix_html AS author_group_prefix,
                    ag.suffix_html AS author_group_suffix, ag.name AS author_group_name,
                    COALESCE(NULLIF(t.last_poster_id, 0), lpn.id) AS last_poster_id,
                    COALESCE(lp.username, lpn.username, t.last_poster_name) AS last_poster_name,
                    COALESCE(lp.slug, lpn.slug) AS last_poster_slug,
                    COALESCE(lp.avatar_url, lpn.avatar_url) AS last_poster_avatar,
                    lg.color_hex AS last_poster_color, lg.prefix_html AS last_poster_prefix,
                    lg.suffix_html AS last_poster_suffix, lg.name AS last_poster_group,
                    tp.label AS prefix_label, tp.color_hex AS prefix_color,
                    (pl.id IS NOT NULL) AS has_poll
             FROM vf_topics t
             INNER JOIN vf_forums f ON f.id = t.forum_id
             LEFT JOIN vf_members am ON am.id = t.author_id
             LEFT JOIN vf_members amn ON (t.author_id IS NULL OR t.author_id = 0) AND amn.username = t.author_name
             LEFT JOIN vf_groups ag ON ag.id = COALESCE(am.primary_group_id, amn.primary_group_id)
             LEFT JOIN vf_members lp ON lp.id = t.last_poster_id
             LEFT JOIN vf_members lpn ON (t.last_poster_id IS NULL OR t.last_poster_id = 0) AND lpn.username = t.last_poster_name
             LEFT JOIN vf_groups lg ON lg.id = COALESCE(lp.primary_group_id, lpn.primary_group_id)
             LEFT JOIN vf_polls pl ON pl.topic_id = t.id
             LEFT JOIN vf_topic_prefixes tp ON tp.id = t.prefix_id
             WHERE t.is_approved = 1 AND t.is_pinned = 1
             ORDER BY t.last_post_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function featuredGlobal(int $limit = 6): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, f.name AS forum_name, f.slug AS forum_slug,
                    COALESCE(NULLIF(t.author_id, 0), amn.id) AS author_id,
                    COALESCE(am.username, amn.username, t.author_name) AS author_name,
                    COALESCE(am.slug, amn.slug) AS author_slug,
                    ag.color_hex AS author_group_color, ag.prefix_html AS author_group_prefix,
                    ag.suffix_html AS author_group_suffix, ag.name AS author_group_name,
                    COALESCE(NULLIF(t.last_poster_id, 0), lpn.id) AS last_poster_id,
                    COALESCE(lp.username, lpn.username, t.last_poster_name) AS last_poster_name,
                    COALESCE(lp.slug, lpn.slug) AS last_poster_slug,
                    COALESCE(lp.avatar_url, lpn.avatar_url) AS last_poster_avatar,
                    lg.color_hex AS last_poster_color, lg.prefix_html AS last_poster_prefix,
                    lg.suffix_html AS last_poster_suffix, lg.name AS last_poster_group,
                    tp.label AS prefix_label, tp.color_hex AS prefix_color,
                    (pl.id IS NOT NULL) AS has_poll
             FROM vf_topics t
             INNER JOIN vf_forums f ON f.id = t.forum_id
             LEFT JOIN vf_members am ON am.id = t.author_id
             LEFT JOIN vf_members amn ON (t.author_id IS NULL OR t.author_id = 0) AND amn.username = t.author_name
             LEFT JOIN vf_groups ag ON ag.id = COALESCE(am.primary_group_id, amn.primary_group_id)
             LEFT JOIN vf_members lp ON lp.id = t.last_poster_id
             LEFT JOIN vf_members lpn ON (t.last_poster_id IS NULL OR t.last_poster_id = 0) AND lpn.username = t.last_poster_name
             LEFT JOIN vf_groups lg ON lg.id = COALESCE(lp.primary_group_id, lpn.primary_group_id)
             LEFT JOIN vf_polls pl ON pl.topic_id = t.id
             LEFT JOIN vf_topic_prefixes tp ON tp.id = t.prefix_id
             WHERE t.is_approved = 1 AND t.is_featured = 1
             ORDER BY t.last_post_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countSolvedTopics(): int
    {
        try {
            return (int) $this->db->query(
                'SELECT COUNT(*) FROM vf_topics WHERE is_approved = 1 AND best_answer_post_id IS NOT NULL'
            )->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function countSolvedByMember(int $memberId): int
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*)
                 FROM vf_topics t
                 INNER JOIN vf_posts p ON p.id = t.best_answer_post_id
                 WHERE t.is_approved = 1 AND p.author_id = :mid'
            );
            $stmt->execute(['mid' => $memberId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function search(string $query, int $limit = 30): array
    {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim($query)) . '%';
        $stmt = $this->db->prepare(
            'SELECT DISTINCT t.*, f.name AS forum_name, f.slug AS forum_slug,
                    COALESCE(NULLIF(t.author_id, 0), amn.id) AS author_id,
                    COALESCE(am.username, amn.username, t.author_name) AS author_name,
                    COALESCE(am.slug, amn.slug) AS author_slug,
                    ag.color_hex AS author_group_color, ag.prefix_html AS author_group_prefix,
                    ag.suffix_html AS author_group_suffix, ag.name AS author_group_name
             FROM vf_topics t
             INNER JOIN vf_forums f ON f.id = t.forum_id
             LEFT JOIN vf_members am ON am.id = t.author_id
             LEFT JOIN vf_members amn ON (t.author_id IS NULL OR t.author_id = 0) AND amn.username = t.author_name
             LEFT JOIN vf_groups ag ON ag.id = COALESCE(am.primary_group_id, amn.primary_group_id)
             LEFT JOIN vf_posts p ON p.topic_id = t.id
             WHERE t.is_approved = 1 AND (t.title LIKE :q1 OR p.content_html LIKE :q2)
             ORDER BY t.last_post_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('q1', $like);
        $stmt->bindValue('q2', $like);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function quickSearch(string $query, int $limit = 5): array
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            $stmt = $this->db->prepare(
                'SELECT t.id, t.title, t.slug, t.author_name, t.posts_count, f.name AS forum_name
                 FROM vf_topics t
                 INNER JOIN vf_forums f ON f.id = t.forum_id
                 WHERE t.is_approved = 1
                 ORDER BY t.last_post_at DESC
                 LIMIT :limit'
            );
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $trimmed) . '%';
        $stmt = $this->db->prepare(
            'SELECT t.id, t.title, t.slug, t.author_name, t.posts_count, f.name AS forum_name
             FROM vf_topics t
             INNER JOIN vf_forums f ON f.id = t.forum_id
             WHERE t.is_approved = 1 AND (t.title LIKE :q1 OR t.author_name LIKE :q2)
             ORDER BY t.last_post_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('q1', $like);
        $stmt->bindValue('q2', $like);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @param string[] $rawTags */
    public function saveTags(int $topicId, array $rawTags): void
    {
        $this->db->prepare('DELETE FROM vf_topic_tags WHERE topic_id = :tid')->execute(['tid' => $topicId]);
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO vf_topic_tags (topic_id, tag_slug, tag_label) VALUES (:tid, :slug, :label)'
        );
        $count = 0;
        foreach ($rawTags as $raw) {
            $label = trim(mb_substr((string) $raw, 0, 40));
            if ($label === '') {
                continue;
            }
            $slug = $this->slugify($label);
            $stmt->execute(['tid' => $topicId, 'slug' => $slug, 'label' => $label]);
            $count++;
            if ($count >= 5) {
                break;
            }
        }
    }

    /** @return array<int, array{tag_slug: string, tag_label: string}> */
    public function tagsForTopic(int $topicId): array
    {
        $stmt = $this->db->prepare('SELECT tag_slug, tag_label FROM vf_topic_tags WHERE topic_id = :tid ORDER BY tag_label ASC');
        $stmt->execute(['tid' => $topicId]);
        return $stmt->fetchAll();
    }

    /**
     * @param int[] $topicIds
     * @return array<int, array<int, array{tag_slug: string, tag_label: string}>>
     */
    public function tagsForTopics(array $topicIds): array
    {
        if (empty($topicIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($topicIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT topic_id, tag_slug, tag_label FROM vf_topic_tags WHERE topic_id IN ({$placeholders}) ORDER BY tag_label ASC"
        );
        $stmt->execute(array_values($topicIds));
        $grouped = [];
        while ($row = $stmt->fetch()) {
            $grouped[(int) $row['topic_id']][] = [
                'tag_slug'  => (string) $row['tag_slug'],
                'tag_label' => (string) $row['tag_label'],
            ];
        }
        return $grouped;
    }

    /** @return array<int, array<string, mixed>> */
    public function listByTag(string $tagSlug, int $limit = 40): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, f.name AS forum_name, f.slug AS forum_slug, tt.tag_label,
                    COALESCE(NULLIF(t.author_id, 0), amn.id) AS author_id,
                    COALESCE(am.username, amn.username, t.author_name) AS author_name,
                    COALESCE(am.slug, amn.slug) AS author_slug,
                    ag.color_hex AS author_group_color, ag.prefix_html AS author_group_prefix,
                    ag.suffix_html AS author_group_suffix, ag.name AS author_group_name
             FROM vf_topic_tags tt
             INNER JOIN vf_topics t ON t.id = tt.topic_id
             INNER JOIN vf_forums f ON f.id = t.forum_id
             LEFT JOIN vf_members am ON am.id = t.author_id
             LEFT JOIN vf_members amn ON (t.author_id IS NULL OR t.author_id = 0) AND amn.username = t.author_name
             LEFT JOIN vf_groups ag ON ag.id = COALESCE(am.primary_group_id, amn.primary_group_id)
             WHERE tt.tag_slug = :slug AND t.is_approved = 1
             ORDER BY t.last_post_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('slug', $tagSlug);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int, array{tag_slug: string, tag_label: string, total: int}> */
    public function popularTags(int $limit = 10): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT tag_slug, tag_label, COUNT(*) AS total
                 FROM vf_topic_tags
                 GROUP BY tag_slug, tag_label
                 ORDER BY total DESC, tag_label ASC
                 LIMIT :limit'
            );
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param string[] $options */
    public function createPollForTopic(int $topicId, string $question, array $options): void
    {
        $cleanOptions = array_values(array_filter(array_map(static fn ($o) => trim((string) $o), $options), static fn ($o) => $o !== ''));
        if (trim($question) === '' || count($cleanOptions) < 2) {
            return;
        }
        $cleanOptions = array_slice($cleanOptions, 0, 5);
        $this->db->beginTransaction();
        try {
            $this->db->prepare('INSERT INTO vf_polls (topic_id, question, created_at) VALUES (:tid, :q, :at)')
                ->execute(['tid' => $topicId, 'q' => mb_substr(trim($question), 0, 250), 'at' => time()]);
            $pollId = (int) $this->db->lastInsertId();
            $optStmt = $this->db->prepare(
                'INSERT INTO vf_poll_options (poll_id, label, votes_count, position) VALUES (:pid, :lbl, 0, :pos)'
            );
            foreach ($cleanOptions as $idx => $label) {
                $optStmt->execute(['pid' => $pollId, 'lbl' => mb_substr($label, 0, 180), 'pos' => $idx + 1]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function pollForTopic(int $topicId, ?int $memberId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM vf_polls WHERE topic_id = :tid LIMIT 1');
        $stmt->execute(['tid' => $topicId]);
        $poll = $stmt->fetch();
        if (!$poll) {
            return null;
        }
        $optStmt = $this->db->prepare('SELECT * FROM vf_poll_options WHERE poll_id = :pid ORDER BY position ASC, id ASC');
        $optStmt->execute(['pid' => (int) $poll['id']]);
        $options = $optStmt->fetchAll();

        $myOptionId = null;
        if ($memberId !== null) {
            $vStmt = $this->db->prepare('SELECT option_id FROM vf_poll_votes WHERE poll_id = :pid AND member_id = :mid LIMIT 1');
            $vStmt->execute(['pid' => (int) $poll['id'], 'mid' => $memberId]);
            $vRow = $vStmt->fetch();
            $myOptionId = $vRow ? (int) $vRow['option_id'] : null;
        }

        $totalVotes = 0;
        foreach ($options as $o) {
            $totalVotes += (int) $o['votes_count'];
        }

        return [
            'id'           => (int) $poll['id'],
            'question'     => (string) $poll['question'],
            'options'      => $options,
            'total_votes'  => $totalVotes,
            'my_option_id' => $myOptionId,
        ];
    }

    public function votePoll(int $topicId, int $optionId, int $memberId): void
    {
        $pollStmt = $this->db->prepare('SELECT id FROM vf_polls WHERE topic_id = :tid LIMIT 1');
        $pollStmt->execute(['tid' => $topicId]);
        $poll = $pollStmt->fetch();
        if (!$poll) {
            return;
        }
        $pollId = (int) $poll['id'];

        $optCheck = $this->db->prepare('SELECT id FROM vf_poll_options WHERE id = :oid AND poll_id = :pid LIMIT 1');
        $optCheck->execute(['oid' => $optionId, 'pid' => $pollId]);
        if (!$optCheck->fetch()) {
            return;
        }

        $this->db->beginTransaction();
        try {
            $existing = $this->db->prepare('SELECT option_id FROM vf_poll_votes WHERE poll_id = :pid AND member_id = :mid LIMIT 1');
            $existing->execute(['pid' => $pollId, 'mid' => $memberId]);
            $prev = $existing->fetch();

            if ($prev) {
                $prevOptId = (int) $prev['option_id'];
                if ($prevOptId !== $optionId) {
                    $this->db->prepare('UPDATE vf_poll_options SET votes_count = GREATEST(0, votes_count - 1) WHERE id = :oid')
                        ->execute(['oid' => $prevOptId]);
                    $this->db->prepare('UPDATE vf_poll_votes SET option_id = :oid, voted_at = :at WHERE poll_id = :pid AND member_id = :mid')
                        ->execute(['oid' => $optionId, 'at' => time(), 'pid' => $pollId, 'mid' => $memberId]);
                    $this->db->prepare('UPDATE vf_poll_options SET votes_count = votes_count + 1 WHERE id = :oid')
                        ->execute(['oid' => $optionId]);
                }
            } else {
                $this->db->prepare('INSERT INTO vf_poll_votes (poll_id, option_id, member_id, voted_at) VALUES (:pid, :oid, :mid, :at)')
                    ->execute(['pid' => $pollId, 'oid' => $optionId, 'mid' => $memberId, 'at' => time()]);
                $this->db->prepare('UPDATE vf_poll_options SET votes_count = votes_count + 1 WHERE id = :oid')
                    ->execute(['oid' => $optionId]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function setBestAnswer(int $topicId, ?int $postId): void
    {
        $this->db->prepare('UPDATE vf_topics SET best_answer_post_id = :pid WHERE id = :tid')
            ->execute(['pid' => $postId, 'tid' => $topicId]);
    }

    public function isBookmarked(int $topicId, int $memberId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM vf_topic_bookmarks WHERE topic_id = :tid AND member_id = :mid LIMIT 1');
        $stmt->execute(['tid' => $topicId, 'mid' => $memberId]);
        return (bool) $stmt->fetchColumn();
    }

    public function toggleBookmark(int $topicId, int $memberId): bool
    {
        if ($this->isBookmarked($topicId, $memberId)) {
            $this->db->prepare('DELETE FROM vf_topic_bookmarks WHERE topic_id = :tid AND member_id = :mid')
                ->execute(['tid' => $topicId, 'mid' => $memberId]);
            return false;
        }
        $this->db->prepare('INSERT INTO vf_topic_bookmarks (topic_id, member_id, created_at) VALUES (:tid, :mid, :at)')
            ->execute(['tid' => $topicId, 'mid' => $memberId, 'at' => time()]);
        return true;
    }

    /** @return array<int, array<string, mixed>> */
    public function bookmarkedByMember(int $memberId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, f.name AS forum_name, f.slug AS forum_slug, tb.created_at AS bookmarked_at,
                    COALESCE(NULLIF(t.author_id, 0), amn.id) AS author_id,
                    COALESCE(am.username, amn.username, t.author_name) AS author_name,
                    COALESCE(am.slug, amn.slug) AS author_slug,
                    ag.color_hex AS author_group_color, ag.prefix_html AS author_group_prefix,
                    ag.suffix_html AS author_group_suffix, ag.name AS author_group_name
             FROM vf_topic_bookmarks tb
             INNER JOIN vf_topics t ON t.id = tb.topic_id
             INNER JOIN vf_forums f ON f.id = t.forum_id
             LEFT JOIN vf_members am ON am.id = t.author_id
             LEFT JOIN vf_members amn ON (t.author_id IS NULL OR t.author_id = 0) AND amn.username = t.author_name
             LEFT JOIN vf_groups ag ON ag.id = COALESCE(am.primary_group_id, amn.primary_group_id)
             WHERE tb.member_id = :mid AND t.is_approved = 1
             ORDER BY tb.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('mid', $memberId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function isFollowing(int $topicId, int $memberId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM vf_follows WHERE target_type = "topic" AND target_id = :tid AND member_id = :mid LIMIT 1'
        );
        $stmt->execute(['tid' => $topicId, 'mid' => $memberId]);
        return (bool) $stmt->fetchColumn();
    }

    public function followCount(int $topicId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM vf_follows WHERE target_type = "topic" AND target_id = :tid'
        );
        $stmt->execute(['tid' => $topicId]);
        return (int) $stmt->fetchColumn();
    }

    public function toggleFollow(int $topicId, int $memberId): bool
    {
        if ($this->isFollowing($topicId, $memberId)) {
            $this->db->prepare(
                'DELETE FROM vf_follows WHERE target_type = "topic" AND target_id = :tid AND member_id = :mid'
            )->execute(['tid' => $topicId, 'mid' => $memberId]);
            return false;
        }
        $this->db->prepare(
            'INSERT INTO vf_follows (target_type, target_id, member_id, created_at) VALUES ("topic", :tid, :mid, :at)'
        )->execute(['tid' => $topicId, 'mid' => $memberId, 'at' => time()]);
        return true;
    }

    /** @return int[] */
    public function followerIds(int $topicId): array
    {
        $stmt = $this->db->prepare(
            'SELECT member_id FROM vf_follows WHERE target_type = "topic" AND target_id = :tid'
        );
        $stmt->execute(['tid' => $topicId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param array<int, array{original_name: string, stored_path: string, mime_type: string, file_size: int, is_image: int}> $items
     */
    public function saveAttachments(int $postId, int $topicId, ?int $uploaderId, array $items): void
    {
        if ($postId <= 0 || $topicId <= 0 || $items === []) {
            return;
        }
        $now = time();
        $stmt = $this->db->prepare(
            'INSERT INTO vf_attachments (post_id, topic_id, uploader_id, original_name, stored_path, mime_type, file_size, is_image, downloads_count, created_at)
             VALUES (:pid, :tid, :uid, :oname, :spath, :mime, :fsize, :isimg, 0, :at)'
        );
        foreach ($items as $item) {
            $stmt->execute([
                'pid'   => $postId,
                'tid'   => $topicId,
                'uid'   => $uploaderId,
                'oname' => $item['original_name'],
                'spath' => $item['stored_path'],
                'mime'  => $item['mime_type'],
                'fsize' => (int) $item['file_size'],
                'isimg' => (int) $item['is_image'],
                'at'    => $now,
            ]);
        }
    }

    /**
     * @param int[] $postIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function attachmentsForPosts(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }
        $clean = array_values(array_unique(array_filter(array_map('intval', $postIds), static fn (int $id): bool => $id > 0)));
        if ($clean === []) {
            return [];
        }
        try {
            $placeholders = implode(',', array_fill(0, count($clean), '?'));
            $stmt = $this->db->prepare(
                "SELECT id, post_id, topic_id, uploader_id, original_name, stored_path, mime_type, file_size, is_image, downloads_count, created_at
                 FROM vf_attachments
                 WHERE post_id IN ({$placeholders})
                 ORDER BY is_image DESC, id ASC"
            );
            $stmt->execute($clean);
            $grouped = [];
            while ($row = $stmt->fetch()) {
                $grouped[(int) $row['post_id']][] = $row;
            }
            return $grouped;
        } catch (\Throwable) {
            return [];
        }
    }

    public function findAttachmentById(int $attachmentId): ?array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT a.*, t.forum_id
                 FROM vf_attachments a
                 INNER JOIN vf_topics t ON t.id = a.topic_id
                 WHERE a.id = :id
                 LIMIT 1'
            );
            $stmt->execute(['id' => $attachmentId]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function deleteAttachment(int $attachmentId): void
    {
        $this->db->prepare('DELETE FROM vf_attachments WHERE id = :id')->execute(['id' => $attachmentId]);
    }

    public function incrementAttachmentDownload(int $attachmentId): void
    {
        $this->db->prepare('UPDATE vf_attachments SET downloads_count = downloads_count + 1 WHERE id = :id')
            ->execute(['id' => $attachmentId]);
    }

    public function topicRealtimeState(int $topicId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, posts_count, last_post_id, last_post_at, last_poster_name
             FROM vf_topics
             WHERE id = :id AND is_approved = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $topicId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function slugify(string $text): string
    {
        $slug = mb_strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? 'temat';
        return trim($slug, '-') ?: 'temat';
    }
}
